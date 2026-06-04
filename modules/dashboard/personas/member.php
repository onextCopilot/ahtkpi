<?php
/**
 * Member dashboard persona — regular employee.
 *
 * Shows the user's personal KPI (Core/Key KPI assignments + achievement),
 * unread notifications, and quick links. Falls back to a department-KPI
 * summary for users who are not enrolled as a Core/Key member.
 *
 * Expects from the including scope: $conn, $user_id, $full_name, $avatar.
 */

// ── Department + member enrolment ─────────────────────────────────────────────
$dept_name = '';
$dept_id = 0;
$res_u = $conn->query("SELECT u.department_id, d.name AS dept_name
                       FROM users u LEFT JOIN departments d ON u.department_id = d.id
                       WHERE u.id = $user_id");
if ($res_u && ($r = $res_u->fetch_assoc())) {
    $dept_id   = (int) ($r['department_id'] ?? 0);
    $dept_name = $r['dept_name'] ?? '';
}

$member_type = null;
$res_m = $conn->query("SELECT member_type FROM core_kpi_members WHERE user_id = $user_id AND is_active = 1 LIMIT 1");
if ($res_m && ($r = $res_m->fetch_assoc())) $member_type = $r['member_type'];

// ── Current Core KPI cycle (active, else most recent) ─────────────────────────
$cycle = null;
$res_cy = $conn->query("SELECT id, name, year, quarter FROM core_kpi_cycles ORDER BY (status='active') DESC, year DESC, COALESCE(quarter,0) DESC LIMIT 1");
if ($res_cy && ($r = $res_cy->fetch_assoc())) $cycle = $r;

// ── Personal KPI assignments + latest achievement ────────────────────────────
$kpi_rows = [];
$overall_num = 0.0; $overall_den = 0.0;
if ($cycle) {
    $cid = (int) $cycle['id'];
    $res_a = $conn->query("SELECT a.id, a.target_value, a.weight, d.kpi_name, d.category
                           FROM core_kpi_assignments a
                           JOIN core_kpi_definitions d ON a.kpi_def_id = d.id
                           WHERE a.user_id = $user_id AND a.cycle_id = $cid
                           ORDER BY d.category, d.kpi_name");
    if ($res_a) {
        while ($a = $res_a->fetch_assoc()) {
            $aid = (int) $a['id'];
            $pct = null; $actual = null; $period = '';
            $res_r = $conn->query("SELECT achievement_pct, actual_value, year, month FROM core_kpi_results WHERE assignment_id = $aid ORDER BY year DESC, month DESC LIMIT 1");
            if ($res_r && ($rr = $res_r->fetch_assoc())) {
                $pct    = $rr['achievement_pct'] !== null ? (float) $rr['achievement_pct'] : null;
                $actual = $rr['actual_value'];
                $period = $rr['month'] ? sprintf('T%d/%d', $rr['month'], $rr['year']) : '';
            }
            $w = (float) ($a['weight'] ?: 1);
            if ($pct !== null) { $overall_num += $pct * $w; $overall_den += $w; }
            $kpi_rows[] = [
                'name'   => $a['kpi_name'],
                'cat'    => $a['category'],
                'target' => $a['target_value'],
                'actual' => $actual,
                'pct'    => $pct,
                'period' => $period,
            ];
        }
    }
}
$overall_pct = $overall_den > 0 ? round($overall_num / $overall_den) : null;

// ── Department KPI count (fallback context for non-core members) ──────────────
$dept_kpi_count = 0;
if ($dept_id) {
    $cur_year = (int) date('Y');
    $res_dk = $conn->query("SELECT COUNT(*) AS c FROM kpi_definitions WHERE department_id = $dept_id AND year = $cur_year");
    if ($res_dk && ($r = $res_dk->fetch_assoc())) $dept_kpi_count = (int) $r['c'];
}

// ── Unread notifications ──────────────────────────────────────────────────────
$notifs = []; $notif_count = 0;
$res_n = $conn->query("SELECT message, created_at FROM pasx_notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 6");
if ($res_n) {
    while ($n = $res_n->fetch_assoc()) $notifs[] = $n;
}
$res_nc = $conn->query("SELECT COUNT(*) AS c FROM pasx_notifications WHERE user_id = $user_id AND is_read = 0");
if ($res_nc && ($r = $res_nc->fetch_assoc())) $notif_count = (int) $r['c'];

function dm_pct_color(?float $pct): string
{
    if ($pct === null) return '#94a3b8';
    if ($pct >= 100) return '#059669';
    if ($pct >= 80) return '#2563eb';
    if ($pct >= 60) return '#f59e0b';
    return '#dc2626';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .dm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(225px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .dm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); position: relative; overflow: hidden; }
        .dm-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--c, #3b82f6); }
        .dm-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .dm-value { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.15; }
        .dm-sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .dm-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 99px; font-size: 13px; font-weight: 700; }
        .dm-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); margin-bottom: 24px; }
        .dm-panel h3 { margin: 0 0 4px; font-size: 1.05rem; color: #0f172a; }
        .dm-row { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
        @media (max-width: 880px) { .dm-row { grid-template-columns: 1fr; } }
        .dm-kpi { padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .dm-kpi:last-child { border-bottom: none; }
        .dm-kpi-top { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 6px; }
        .dm-bar { height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .dm-fill { height: 100%; border-radius: 99px; transition: width .6s ease; }
        .dm-links { display: flex; gap: 12px; flex-wrap: wrap; }
        .dm-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; }
        .dm-link.alt { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Dashboard';
            $page_subtitle = 'Xin chào, <strong>' . htmlspecialchars($full_name) . '</strong>';
            include __DIR__ . '/../../includes/topbar.php';
            ?>
            <div class="content-wrapper">

                <div class="dm-panel" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="margin:0;">KPI cá nhân của bạn</h3>
                        <p style="margin:4px 0 0; font-size:13px; color:#64748b;">
                            <?php echo $dept_name ? ('Phòng ' . htmlspecialchars($dept_name)) : 'Chưa gán phòng ban'; ?>
                            <?php if ($cycle): ?> · Chu kỳ <?php echo htmlspecialchars($cycle['name']); ?><?php endif; ?>
                        </p>
                    </div>
                    <?php if ($member_type): ?>
                        <span class="dm-badge" style="background:<?php echo $member_type === 'Core' ? '#7c3aed' : '#2563eb'; ?>; color:#fff;"><?php echo $member_type; ?> Member</span>
                    <?php endif; ?>
                </div>

                <!-- Stat cards -->
                <div class="dm-grid">
                    <div class="dm-card" style="--c:#10b981;">
                        <div class="dm-label">Tiến độ KPI tổng</div>
                        <div class="dm-value" style="color:<?php echo dm_pct_color($overall_pct === null ? null : (float)$overall_pct); ?>;"><?php echo $overall_pct !== null ? $overall_pct . '%' : '—'; ?></div>
                        <div class="dm-sub"><?php echo $overall_pct !== null ? 'Trung bình có trọng số' : 'Chưa có dữ liệu kết quả'; ?></div>
                    </div>
                    <div class="dm-card" style="--c:#3b82f6;">
                        <div class="dm-label">KPI được giao</div>
                        <div class="dm-value" style="color:#2563eb;"><?php echo count($kpi_rows); ?></div>
                        <div class="dm-sub"><?php echo $cycle ? ('Chu kỳ ' . htmlspecialchars($cycle['name'])) : 'Chưa có chu kỳ'; ?></div>
                    </div>
                    <div class="dm-card" style="--c:#f59e0b;">
                        <div class="dm-label">KPI phòng (năm nay)</div>
                        <div class="dm-value" style="color:#0f172a;"><?php echo $dept_kpi_count; ?></div>
                        <div class="dm-sub"><?php echo $dept_name ? htmlspecialchars($dept_name) : '—'; ?></div>
                    </div>
                    <div class="dm-card" style="--c:#f43f5e;">
                        <div class="dm-label">Thông báo chưa đọc</div>
                        <div class="dm-value" style="color:<?php echo $notif_count > 0 ? '#e11d48' : '#0f172a'; ?>;"><?php echo $notif_count; ?></div>
                        <div class="dm-sub">Cần xem &amp; xử lý</div>
                    </div>
                </div>

                <!-- KPI detail + notifications -->
                <div class="dm-row">
                    <div class="dm-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:8px;">Chi tiết KPI cá nhân</h3>
                        <?php if (empty($kpi_rows)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:8px 0 0;">
                                <?php echo $member_type ? 'Chưa có KPI nào được giao trong chu kỳ hiện tại.' : 'Bạn chưa tham gia Core/Key KPI. Xem KPI của phòng ban tại liên kết bên dưới.'; ?>
                            </p>
                        <?php else: ?>
                            <?php foreach ($kpi_rows as $k):
                                $c = dm_pct_color($k['pct']); ?>
                                <div class="dm-kpi">
                                    <div class="dm-kpi-top">
                                        <span style="font-weight:600; color:#0f172a; font-size:13px;"><?php echo htmlspecialchars($k['name']); ?>
                                            <?php if ($k['cat']): ?><span style="font-size:11px; color:#94a3b8; font-weight:500;"> · <?php echo htmlspecialchars($k['cat']); ?></span><?php endif; ?>
                                        </span>
                                        <span style="font-weight:700; color:<?php echo $c; ?>; font-size:13px; white-space:nowrap;"><?php echo $k['pct'] !== null ? round($k['pct']) . '%' : 'chưa có'; ?></span>
                                    </div>
                                    <div class="dm-bar"><div class="dm-fill" style="width:<?php echo $k['pct'] !== null ? min(100, max(0, (int) round($k['pct']))) : 0; ?>%; background:<?php echo $c; ?>;"></div></div>
                                    <div class="dm-sub">
                                        <?php if ($k['actual'] !== null && $k['actual'] !== ''): ?>Thực tế <?php echo htmlspecialchars($k['actual']); ?><?php endif; ?>
                                        <?php if ($k['target'] !== null && $k['target'] !== ''): ?> / Mục tiêu <?php echo htmlspecialchars($k['target']); ?><?php endif; ?>
                                        <?php if ($k['period']): ?> · <?php echo htmlspecialchars($k['period']); ?><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dm-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:10px;">Thông báo</h3>
                        <?php if (empty($notifs)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:0;">Không có thông báo chưa đọc. ✅</p>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <?php foreach ($notifs as $n): ?>
                                    <div style="padding:10px 12px; border:1px solid #e2e8f0; border-left:3px solid #3b82f6; border-radius:8px;">
                                        <div style="font-size:13px; color:#0f172a;"><?php echo htmlspecialchars($n['message'] ?: 'Thông báo'); ?></div>
                                        <div style="font-size:11px; color:#94a3b8; margin-top:4px;"><?php echo $n['created_at'] ? date('H:i d/m/Y', strtotime($n['created_at'])) : ''; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dm-panel">
                    <h3 style="margin-bottom:14px;">Đi tới chi tiết</h3>
                    <div class="dm-links">
                        <a class="dm-link" href="/kpi">📈 KPI phòng ban</a>
                        <a class="dm-link alt" href="/my-com">💰 Lương &amp; KPI của tôi</a>
                        <a class="dm-link alt" href="/profile">👤 Hồ sơ</a>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>
