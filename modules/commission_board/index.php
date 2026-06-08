<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$role      = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// Access: admin or Hyun Cao only.
if (!($role === 'admin' || $full_name === 'Hyun Cao')) {
    header("Location: /dashboard");
    exit();
}

// ── Year selection ──
$current_year     = (int) date('Y');
$selected_year    = isset($_GET['year']) ? (int) $_GET['year'] : $current_year;
$available_years  = [$current_year, $current_year - 1, $current_year - 2];
if (!in_array($selected_year, $available_years, true)) $available_years[] = $selected_year;
rsort($available_years);

// ── Ensure confirmation table + snapshot columns exist ──
$conn->query("CREATE TABLE IF NOT EXISTS my_com_confirmation (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    year         SMALLINT NOT NULL,
    quarter      TINYINT NOT NULL,
    status       ENUM('draft','confirmed') DEFAULT 'draft',
    confirmed_at DATETIME DEFAULT NULL,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_yq (user_id, year, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
function cb_ensure_col($conn, $col, $ddl) {
    $col = $conn->real_escape_string($col);
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='my_com_confirmation' AND COLUMN_NAME='$col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE my_com_confirmation ADD COLUMN $col $ddl");
}
foreach ([
    'snap_total' => 'DECIMAL(20,2) DEFAULT 0', 'snap_com1' => 'DECIMAL(20,2) DEFAULT 0',
    'snap_com2' => 'DECIMAL(20,2) DEFAULT 0', 'snap_ai' => 'DECIMAL(20,2) DEFAULT 0',
    'snap_so_com' => 'DECIMAL(20,2) DEFAULT 0', 'snap_license' => 'DECIMAL(20,2) DEFAULT 0',
    'snap_yb' => 'DECIMAL(20,2) DEFAULT 0', 'snap_kpi_pct' => 'DECIMAL(8,2) DEFAULT 0',
    'snap_revenue' => 'DECIMAL(20,2) DEFAULT 0', 'snap_kpi_target' => 'DECIMAL(20,2) DEFAULT 0',
    'snap_position' => "VARCHAR(50) DEFAULT ''", 'snap_level' => "VARCHAR(100) DEFAULT ''",
] as $col => $ddl) cb_ensure_col($conn, $col, $ddl);

// Ensure is_marketer exists on older live DBs (idempotent) so the query below never fatals.
try {
    $mc_col = $conn->query("SHOW COLUMNS FROM users LIKE 'is_marketer'");
    if ($mc_col && $mc_col->num_rows === 0) $conn->query("ALTER TABLE users ADD COLUMN is_marketer TINYINT(1) DEFAULT 0");
} catch (Throwable $e) { /* ignore */ }

// ── AM/BD + Marketer users (with fallback level from users.sale_level_id) ──
$users = [];
$ures = $conn->query("SELECT u.id, u.full_name, u.email, u.is_am_bd, u.is_marketer, sl.position_type AS fb_position, sl.level_name AS fb_level
    FROM users u
    LEFT JOIN sale_levels sl ON u.sale_level_id = sl.id
    WHERE u.is_am_bd = 1 OR u.is_marketer = 1
    ORDER BY u.full_name");
if ($ures) while ($r = $ures->fetch_assoc()) $users[(int)$r['id']] = $r;

// ── Latest sale level (position + level) per user ──
$level_map = [];
$lres = $conn->query("SELECT h.user_id, sl.position_type, sl.level_name
    FROM user_sale_level_history h
    JOIN sale_levels sl ON h.sale_level_id = sl.id
    JOIN (SELECT user_id, MAX(apply_year * 10 + apply_quarter) AS mk
          FROM user_sale_level_history GROUP BY user_id) m
      ON m.user_id = h.user_id AND (h.apply_year * 10 + h.apply_quarter) = m.mk");
if ($lres) while ($r = $lres->fetch_assoc()) $level_map[(int)$r['user_id']] = $r;

// ── Confirmations for the selected year ──
$confirm = []; // [user_id][quarter] = row
$cstmt = $conn->prepare("SELECT user_id, quarter, status, confirmed_at, snap_total, snap_com1, snap_com2,
    snap_ai, snap_so_com, snap_license, snap_yb, snap_kpi_pct, snap_revenue, snap_kpi_target, snap_position, snap_level
    FROM my_com_confirmation WHERE year = ?");
$cstmt->bind_param("i", $selected_year);
$cstmt->execute();
$cres = $cstmt->get_result();
while ($r = $cres->fetch_assoc()) $confirm[(int)$r['user_id']][(int)$r['quarter']] = $r;
$cstmt->close();

// ── Helpers ──
function cb_fmt_short($n) {
    if (abs($n) >= 1e9) return number_format($n / 1e9, 2) . ' tỷ';
    if (abs($n) >= 1e6) return number_format($n / 1e6, 1) . ' tr';
    if ($n == 0) return '0';
    return number_format($n, 0, '.', ',');
}

// Resolve each user's position/level (history → users.sale_level_id → confirmed snapshot).
// Drop users flagged is_am_bd but with no real position assigned (e.g. Steve Le).
foreach ($users as $uid => &$u) {
    $pos = $level_map[$uid]['position_type'] ?? ($u['fb_position'] ?? '');
    $lvl = $level_map[$uid]['level_name']    ?? ($u['fb_level'] ?? '');
    if (!$pos) {
        for ($q = 4; $q >= 1; $q--) {
            if (!empty($confirm[$uid][$q]['snap_position'])) { $pos = $confirm[$uid][$q]['snap_position']; $lvl = $confirm[$uid][$q]['snap_level']; break; }
        }
    }
    // Marketers have no sale-level position; keep them under a "Marketing" label.
    if (!$pos && !empty($u['is_marketer'])) { $pos = 'Marketing'; }
    if (!$pos) { unset($users[$uid]); continue; }
    $u['_pos'] = $pos;
    $u['_lvl'] = $lvl;
}
unset($u);

// Column totals (confirmed only)
$col_totals = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$grand_total = 0; $grand_rev = 0; $confirmed_count = 0;
foreach ($users as $uid => $u) {
    for ($q = 1; $q <= 4; $q++) {
        $c = $confirm[$uid][$q] ?? null;
        if ($c && $c['status'] === 'confirmed') {
            $col_totals[$q] += (float) $c['snap_total'];
            $grand_total    += (float) $c['snap_total'];
            $grand_rev      += (float) $c['snap_revenue'];
            $confirmed_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Board · <?= $selected_year ?></title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .cb { padding: 1rem 1.25rem; max-width:100%; font-family:'Inter', sans-serif; }
        .cb-bar { display:flex; align-items:center; gap:1rem; margin-bottom:1rem; }
        .cb-bar h2 { margin:0; font-size:18px; color:#1e293b; font-weight:700; }
        .cb-year { padding:0.35rem 0.7rem; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:600; color:#374151; background:#fff; cursor:pointer; outline:none; }
        .cb-stats { display:flex; gap:0.75rem; margin-bottom:1.25rem; flex-wrap:wrap; }
        .cb-stat { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem 1.1rem; min-width:160px; }
        .cb-stat .l { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; }
        .cb-stat .v { font-size:18px; font-weight:700; color:#0f172a; margin-top:2px; }
        .cb-table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
        table.cb-table { width:100%; border-collapse:collapse; font-size:13px; }
        .cb-table th { background:#f8fafc; color:#5f6368; font-weight:700; text-align:left; padding:10px 12px; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
        .cb-table th.num, .cb-table td.num { text-align:right; }
        .cb-table td { padding:9px 12px; border-bottom:1px solid #f1f5f9; }
        .cb-table tbody tr:nth-child(odd) td { background:#eef2f7; }
        .cb-table tbody tr:nth-child(even) td { background:#fff; }
        .cb-table tbody tr:hover td { background:#e0ebff; }
        .cb-table tbody tr:nth-child(odd) td.cb-total-col { background:#e3e9f1; }
        .cb-table tbody tr:nth-child(even) td.cb-total-col { background:#eff3f9; }
        .cb-table tbody tr:hover td.cb-total-col { background:#d4e0f5; }
        .cb-name-col { width:230px; }
        .cb-email { color:#64748b; font-size:12px; }
        .cb-rev-yr { font-size:12px; color:#475569; font-weight:600; }
        .cb-conf-pill { display:inline-block; font-size:11px; font-weight:700; color:#94a3b8; background:#f1f5f9; border-radius:10px; padding:1px 8px; }
        .cb-conf-pill.on { color:#16a34a; background:#dcfce7; }
        .cb-user { font-weight:600; color:#1e293b; }
        .cb-pos { display:inline-block; background:#3b82f615; color:#3b82f6; border:1px solid #3b82f630; border-radius:4px; padding:1px 7px; font-size:10px; font-weight:700; margin-top:2px; }
        .cb-cell { display:inline-flex; flex-direction:column; align-items:flex-end; gap:2px; text-decoration:none; min-width:74px; }
        .cb-amt-row { display:inline-flex; align-items:center; gap:5px; }
        .cb-cell .amt { font-weight:700; color:#1d4ed8; }
        .cb-cell.confirmed .amt { color:#16a34a; }
        .cb-cell.draft .amt { color:#cbd5e1; font-weight:500; }
        .cb-check { color:#16a34a; flex-shrink:0; }
        .cb-link { color:#94a3b8; }
        .cb-link:hover { color:#2563eb; }
        .cb-sub { display:inline-flex; align-items:center; gap:5px; }
        .cb-rev { font-size:10px; color:#94a3b8; }
        .kpi-badge { font-size:10px; font-weight:700; border-radius:4px; padding:0 5px; line-height:15px; }
        .kpi-badge.kpi-good { background:#dcfce7; color:#16a34a; }
        .kpi-badge.kpi-warn { background:#fef9c3; color:#a16207; }
        .kpi-badge.kpi-bad  { background:#fee2e2; color:#dc2626; }
        .cb-total-col { background:#f8fafc; font-weight:700; }
        .cb-foot td { background:#f0fdf4; font-weight:700; color:#15803d; border-top:2px solid #bbf7d0; }
        .cb-empty { text-align:center; color:#94a3b8; padding:2rem; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Commission Board'; include __DIR__ . '/../includes/topbar.php'; ?>

        <div class="cb">
            <div class="cb-bar">
                <h2>Commission Board</h2>
                <select class="cb-year" onchange="location.href='/commission-board?year='+this.value">
                    <?php foreach ($available_years as $yr): ?>
                        <option value="<?= $yr ?>" <?= $yr === $selected_year ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:12px;color:#94a3b8;">Tổng hợp Commission của AM / BD theo quý · Năm <?= $selected_year ?></span>
            </div>

            <div class="cb-stats">
                <div class="cb-stat">
                    <div class="l">AM / BD</div>
                    <div class="v"><?= count($users) ?> người</div>
                </div>
                <div class="cb-stat">
                    <div class="l">Đã xác nhận</div>
                    <div class="v" style="color:#16a34a;"><?= $confirmed_count ?> quý</div>
                </div>
                <div class="cb-stat">
                    <div class="l">Tổng Com (đã xác nhận)</div>
                    <div class="v" style="color:#16a34a;"><?= cb_fmt_short($grand_total) ?></div>
                </div>
            </div>

            <div class="cb-table-wrap">
                <table class="cb-table">
                    <thead>
                        <tr>
                            <th class="cb-name-col">Nhân viên</th>
                            <th>Email</th>
                            <th class="num">Q1</th>
                            <th class="num">Q2</th>
                            <th class="num">Q3</th>
                            <th class="num">Q4</th>
                            <th class="num">DT năm</th>
                            <th class="num">Đã XN</th>
                            <th class="num cb-total-col">Tổng Com năm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="9" class="cb-empty">Không có nhân viên AM / BD / Marketer nào (cần bật cờ <code>is_am_bd</code> hoặc <code>is_marketer</code>)</td></tr>
                        <?php else: foreach ($users as $uid => $u):
                            $pos     = $u['_pos'] ?? '';
                            $lvlname = $u['_lvl'] ?? '';
                            $row_total = 0; $row_rev = 0; $row_conf = 0;
                        ?>
                        <tr>
                            <td class="cb-name-col">
                                <div class="cb-user"><?= htmlspecialchars($u['full_name'] ?: ('User #' . $uid)) ?></div>
                                <?php if ($pos): ?><span class="cb-pos"><?= htmlspecialchars($pos) ?></span> <span style="font-size:10px;color:#94a3b8;"><?= htmlspecialchars($lvlname) ?></span><?php endif; ?>
                            </td>
                            <td class="cb-email"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <?php for ($q = 1; $q <= 4; $q++):
                                $c = $confirm[$uid][$q] ?? null;
                                $is_conf = $c && $c['status'] === 'confirmed';
                                $amt = $is_conf ? (float) $c['snap_total'] : 0;
                                if ($is_conf) { $row_total += $amt; $row_rev += (float) $c['snap_revenue']; $row_conf++; }
                                $kpi  = $is_conf ? (float) $c['snap_kpi_pct'] : 0;
                                $rev  = $is_conf ? (float) $c['snap_revenue'] : 0;
                                $tgt  = $is_conf ? (float) $c['snap_kpi_target'] : 0;
                                $kcls = $kpi >= 80 ? 'good' : ($kpi >= 60 ? 'warn' : 'bad');
                                $detail = '/my-com?user_id=' . $uid . '&year=' . $selected_year . '&quarter=' . $q;
                                $tip = $is_conf
                                    ? ('Đã xác nhận ' . ($c['confirmed_at'] ? date('d/m/Y H:i', strtotime($c['confirmed_at'])) : '') . " — Com1 " . cb_fmt_short($c['snap_com1']) . " · Com2 " . cb_fmt_short($c['snap_com2']) . " · AI " . cb_fmt_short($c['snap_ai']) . " · 1stPO " . cb_fmt_short($c['snap_so_com']) . " · License " . cb_fmt_short($c['snap_license']) . ' · KPI ' . number_format($kpi, 1) . '% (' . cb_fmt_short($rev) . ' / ' . cb_fmt_short($tgt) . ')')
                                    : 'Chưa xác nhận — click để xem chi tiết';
                            ?>
                            <td class="num">
                                <a class="cb-cell <?= $is_conf ? 'confirmed' : 'draft' ?>" href="<?= $detail ?>" title="<?= htmlspecialchars($tip) ?>">
                                    <?php if ($is_conf): ?>
                                        <div class="cb-amt-row">
                                            <span class="amt"><?= cb_fmt_short($amt) ?></span>
                                            <svg class="cb-check" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                                        </div>
                                        <div class="cb-sub">
                                            <span class="kpi-badge kpi-<?= $kcls ?>"><?= number_format($kpi, 0) ?>%</span>
                                            <span class="cb-rev"><?= cb_fmt_short($rev) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="cb-amt-row">
                                            <span class="amt">—</span>
                                            <svg class="cb-link" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <?php endfor; ?>
                            <td class="num"><span class="cb-rev-yr"><?= $row_rev > 0 ? cb_fmt_short($row_rev) : '—' ?></span></td>
                            <td class="num"><span class="cb-conf-pill <?= $row_conf > 0 ? 'on' : '' ?>"><?= $row_conf ?>/4</span></td>
                            <td class="num cb-total-col"><?= $row_total > 0 ? cb_fmt_short($row_total) : '—' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($users)): ?>
                    <tfoot>
                        <tr class="cb-foot">
                            <td colspan="2">Tổng (đã xác nhận)</td>
                            <td class="num"><?= $col_totals[1] > 0 ? cb_fmt_short($col_totals[1]) : '—' ?></td>
                            <td class="num"><?= $col_totals[2] > 0 ? cb_fmt_short($col_totals[2]) : '—' ?></td>
                            <td class="num"><?= $col_totals[3] > 0 ? cb_fmt_short($col_totals[3]) : '—' ?></td>
                            <td class="num"><?= $col_totals[4] > 0 ? cb_fmt_short($col_totals[4]) : '—' ?></td>
                            <td class="num"><?= $grand_rev > 0 ? cb_fmt_short($grand_rev) : '—' ?></td>
                            <td class="num"><?= $confirmed_count ?></td>
                            <td class="num cb-total-col"><?= $grand_total > 0 ? cb_fmt_short($grand_total) : '—' ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <div style="font-size:11px;color:#94a3b8;margin-top:8px;">
                Mỗi ô đã xác nhận: <strong>Com</strong> (trên) · <span class="kpi-badge kpi-good" style="font-size:9px;">KPI%</span> đạt được + <span style="color:#94a3b8;">doanh thu</span> (dưới). ✓ = đã xác nhận (số liệu chốt tại thời điểm xác nhận). Ô chưa xác nhận hiển thị "—". Click bất kỳ ô nào để mở trang chi tiết giống My Com. KPI màu: <span style="color:#16a34a;">≥80%</span> · <span style="color:#a16207;">60–80%</span> · <span style="color:#dc2626;">&lt;60%</span>.
            </div>
        </div>
    </main>
</div>
</body>
</html>
