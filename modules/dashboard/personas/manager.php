<?php
/**
 * Manager dashboard persona — department head.
 *
 * Shows the department(s) this user manages: team roster with each member's
 * outstanding debt, the department KPI set with data-entry status, and a
 * team debt-to-collect total.
 *
 * Expects from the including scope: $conn, $user_id, $full_name, $avatar.
 */
require_once __DIR__ . '/../../../libs/OdooAPI.php';

$odoo = null;
$vnd_rates = ['VND' => 1.0];
try {
    $odoo = new OdooAPI();
    $curs = $odoo->getCurrencies();
    $r_vnd = (is_array($curs) && isset($curs['VND']['rate'])) ? (float) $curs['VND']['rate'] : 0.0;
    if ($r_vnd > 0) {
        foreach ($curs as $name => $info) {
            $r = isset($info['rate']) ? (float) $info['rate'] : 0.0;
            if ($r > 0) $vnd_rates[$name] = $r_vnd / $r;
        }
    }
} catch (Throwable $e) { /* VND-only fallback */ }

$to_vnd = fn($amt, $cur) => (float) $amt * ($vnd_rates[$cur ?: 'VND'] ?? 1.0);
$cur_year = (int) date('Y');
$cur_month = (int) date('n');

// ── Departments managed by this user (fallback: own department) ───────────────
$dept_ids = [];
$dept_names = [];
$res_d = $conn->query("SELECT id, name FROM departments WHERE manager_id = $user_id ORDER BY sort_order ASC, id ASC");
if ($res_d) {
    while ($r = $res_d->fetch_assoc()) { $dept_ids[] = (int) $r['id']; $dept_names[] = $r['name']; }
}
if (empty($dept_ids)) {
    $res_o = $conn->query("SELECT u.department_id, d.name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = $user_id");
    if ($res_o && ($r = $res_o->fetch_assoc()) && !empty($r['department_id'])) {
        $dept_ids[] = (int) $r['department_id'];
        $dept_names[] = $r['name'] ?? '';
    }
}
$dept_in = $dept_ids ? implode(',', array_map('intval', $dept_ids)) : '0';

// ── Team members ──────────────────────────────────────────────────────────────
$members = [];
$member_emails = [];
$res_m = $conn->query("SELECT id, full_name, email, job_title, is_am_bd, status
                       FROM users WHERE department_id IN ($dept_in)
                       ORDER BY (status='active') DESC, full_name ASC");
if ($res_m) {
    while ($r = $res_m->fetch_assoc()) {
        $members[$r['id']] = [
            'name'      => $r['full_name'],
            'email'     => $r['email'],
            'job_title' => $r['job_title'],
            'is_am_bd'  => (int) $r['is_am_bd'],
            'status'    => $r['status'],
            'debt_vnd'  => 0.0,
            'debt_cnt'  => 0,
        ];
        if (!empty($r['email'])) $member_emails[strtolower($r['email'])] = $r['id'];
    }
}
$active_members = array_filter($members, fn($m) => $m['status'] === 'active');

// ── Team debt to collect (unpaid), attributed by am_email ─────────────────────
$team_debt_vnd = 0.0; $team_debt_cnt = 0;
if ($member_emails) {
    $email_list = implode(',', array_map(fn($e) => "'" . $conn->real_escape_string($e) . "'", array_keys($member_emails)));
    $res_dt = $conn->query("SELECT am_email, amount, currency, payment_status FROM debts WHERE LOWER(am_email) IN ($email_list)");
    if ($res_dt) {
        while ($d = $res_dt->fetch_assoc()) {
            if (strcasecmp(trim($d['payment_status'] ?? ''), 'Paid') === 0) continue;
            $vnd = $to_vnd((float) $d['amount'], $d['currency'] ?: 'USD');
            $team_debt_vnd += $vnd;
            $team_debt_cnt++;
            $uid = $member_emails[strtolower($d['am_email'])] ?? null;
            if ($uid && isset($members[$uid])) { $members[$uid]['debt_vnd'] += $vnd; $members[$uid]['debt_cnt']++; }
        }
    }
}

// ── Department KPI set + data-entry status ────────────────────────────────────
$kpi_total = 0; $kpi_with_data = 0; $kpi_weight = 0.0;
$kpi_list = [];
$res_k = $conn->query("SELECT kd.id, kd.kpi_name, kd.kpi_group, kd.weight, kd.unit,
                              (SELECT COUNT(*) FROM kpi_monthly km WHERE km.kpi_def_id = kd.id AND km.year = $cur_year AND km.month = $cur_month) AS has_month
                       FROM kpi_definitions kd
                       WHERE kd.department_id IN ($dept_in) AND kd.year = $cur_year
                       ORDER BY kd.kpi_group, kd.kpi_name");
if ($res_k) {
    while ($k = $res_k->fetch_assoc()) {
        $kpi_total++;
        $kpi_weight += (float) $k['weight'];
        $has = (int) $k['has_month'] > 0;
        if ($has) $kpi_with_data++;
        $kpi_list[] = ['name' => $k['kpi_name'], 'group' => $k['kpi_group'], 'weight' => (float) $k['weight'], 'unit' => $k['unit'], 'has' => $has];
    }
}
$kpi_fill_pct = $kpi_total > 0 ? round($kpi_with_data / $kpi_total * 100) : 0;

$fmtVnd = fn($v) => number_format($v, 0, ',', '.') . ' ₫';

// Sort members by outstanding debt desc for the roster
uasort($members, fn($a, $b) => $b['debt_vnd'] <=> $a['debt_vnd']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Quản lý</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .mg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(225px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .mg-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); position: relative; overflow: hidden; }
        .mg-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--c, #3b82f6); }
        .mg-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .mg-value { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.15; }
        .mg-sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .mg-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 99px; font-size: 13px; font-weight: 700; }
        .mg-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); margin-bottom: 24px; }
        .mg-panel h3 { margin: 0 0 4px; font-size: 1.05rem; color: #0f172a; }
        .mg-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        @media (max-width: 880px) { .mg-row { grid-template-columns: 1fr; } }
        .mg-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .mg-item:last-child { border-bottom: none; }
        .mg-bar { height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin-top: 8px; }
        .mg-fill { height: 100%; border-radius: 99px; background: #2563eb; }
        .mg-links { display: flex; gap: 12px; flex-wrap: wrap; }
        .mg-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; }
        .mg-link.alt { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
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

                <div class="mg-panel" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="margin:0;">Tổng quan phòng ban</h3>
                        <p style="margin:4px 0 0; font-size:13px; color:#64748b;">
                            <?php echo $dept_names ? htmlspecialchars(implode(', ', array_filter($dept_names))) : 'Bạn chưa được gán quản lý phòng nào'; ?>
                        </p>
                    </div>
                    <span class="mg-badge" style="background:#0ea5e9; color:#fff;">Trưởng phòng</span>
                </div>

                <div class="mg-grid">
                    <div class="mg-card" style="--c:#3b82f6;">
                        <div class="mg-label">Thành viên</div>
                        <div class="mg-value" style="color:#2563eb;"><?php echo count($active_members); ?></div>
                        <div class="mg-sub"><?php echo count($members); ?> tổng (gồm nghỉ/inactive)</div>
                    </div>
                    <div class="mg-card" style="--c:#10b981;">
                        <div class="mg-label">KPI phòng (<?php echo $cur_year; ?>)</div>
                        <div class="mg-value" style="color:#059669;"><?php echo $kpi_total; ?></div>
                        <div class="mg-sub">Tổng trọng số <?php echo rtrim(rtrim(number_format($kpi_weight, 1), '0'), '.'); ?>%</div>
                    </div>
                    <div class="mg-card" style="--c:#f59e0b;">
                        <div class="mg-label">KPI đã nhập T<?php echo $cur_month; ?></div>
                        <div class="mg-value" style="color:#0f172a;"><?php echo $kpi_with_data; ?>/<?php echo $kpi_total; ?></div>
                        <div class="mg-sub"><?php echo $kpi_fill_pct; ?>% đã có số liệu tháng này</div>
                    </div>
                    <div class="mg-card" style="--c:#f43f5e;">
                        <div class="mg-label">Công nợ team cần thu</div>
                        <div class="mg-value" style="color:#e11d48; font-size:20px;"><?php echo $fmtVnd($team_debt_vnd); ?></div>
                        <div class="mg-sub"><?php echo $team_debt_cnt; ?> deal chưa thu</div>
                    </div>
                </div>

                <div class="mg-row">
                    <div class="mg-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:8px;">Thành viên &amp; công nợ phụ trách</h3>
                        <?php if (empty($members)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:8px 0 0;">Chưa có thành viên trong phòng.</p>
                        <?php else: ?>
                            <?php foreach ($members as $m): ?>
                                <div class="mg-item">
                                    <div style="min-width:0;">
                                        <div style="font-weight:600; color:#0f172a; font-size:13px;">
                                            <?php echo htmlspecialchars($m['name']); ?>
                                            <?php if ($m['is_am_bd']): ?><span style="font-size:10px; background:#eef2ff; color:#4338ca; padding:1px 6px; border-radius:99px; font-weight:700;">AM/BD</span><?php endif; ?>
                                            <?php if ($m['status'] !== 'active'): ?><span style="font-size:10px; background:#f1f5f9; color:#94a3b8; padding:1px 6px; border-radius:99px;"><?php echo htmlspecialchars($m['status']); ?></span><?php endif; ?>
                                        </div>
                                        <div style="font-size:11px; color:#94a3b8;"><?php echo htmlspecialchars($m['job_title'] ?: '—'); ?></div>
                                    </div>
                                    <div style="text-align:right; white-space:nowrap;">
                                        <?php if ($m['debt_vnd'] > 0): ?>
                                            <div style="font-weight:700; color:#e11d48; font-size:13px;"><?php echo $fmtVnd($m['debt_vnd']); ?></div>
                                            <div style="font-size:11px; color:#94a3b8;"><?php echo $m['debt_cnt']; ?> deal</div>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:#16a34a;">✓ sạch nợ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mg-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:8px;">KPI phòng — tiến độ nhập số liệu</h3>
                        <?php if ($kpi_total > 0): ?>
                            <div class="mg-bar"><div class="mg-fill" style="width:<?php echo $kpi_fill_pct; ?>%;"></div></div>
                            <div class="mg-sub" style="margin-bottom:8px;"><?php echo $kpi_with_data; ?>/<?php echo $kpi_total; ?> KPI đã có số liệu T<?php echo $cur_month; ?>/<?php echo $cur_year; ?></div>
                        <?php endif; ?>
                        <?php if (empty($kpi_list)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:8px 0 0;">Phòng chưa có KPI nào cho năm <?php echo $cur_year; ?>.</p>
                        <?php else: ?>
                            <?php foreach ($kpi_list as $k): ?>
                                <div class="mg-item">
                                    <div style="min-width:0;">
                                        <div style="font-weight:600; color:#0f172a; font-size:13px;"><?php echo htmlspecialchars($k['name']); ?></div>
                                        <div style="font-size:11px; color:#94a3b8;"><?php echo htmlspecialchars($k['group'] ?: 'KPI'); ?> · trọng số <?php echo rtrim(rtrim(number_format($k['weight'], 1), '0'), '.'); ?>%</div>
                                    </div>
                                    <span style="font-size:12px; font-weight:700; color:<?php echo $k['has'] ? '#16a34a' : '#dc2626'; ?>; white-space:nowrap;"><?php echo $k['has'] ? '✓ đã nhập' : '• thiếu'; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mg-panel">
                    <h3 style="margin-bottom:14px;">Đi tới chi tiết</h3>
                    <div class="mg-links">
                        <a class="mg-link" href="/kpi">📈 KPI phòng ban</a>
                        <a class="mg-link alt" href="/debt">📌 Công nợ</a>
                        <a class="mg-link alt" href="/core-key-kpi">🎯 Core KPI</a>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>
