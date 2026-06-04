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

// ── KPI monthly trend (personal avg achievement_pct across the cycle year) ────
$trend_year = $cycle ? (int) $cycle['year'] : (int) date('Y');
$trend = array_fill(1, 12, null);
$trend_has_data = false;
if ($cycle) {
    $cid = (int) $cycle['id'];
    $res_t = $conn->query("SELECT r.month, AVG(r.achievement_pct) AS avg_pct
                           FROM core_kpi_results r
                           JOIN core_kpi_assignments a ON r.assignment_id = a.id
                           WHERE a.user_id = $user_id AND a.cycle_id = $cid AND r.year = $trend_year
                             AND r.achievement_pct IS NOT NULL
                           GROUP BY r.month ORDER BY r.month");
    if ($res_t) {
        while ($t = $res_t->fetch_assoc()) {
            $mo = (int) $t['month'];
            if ($mo >= 1 && $mo <= 12) { $trend[$mo] = round((float) $t['avg_pct'], 1); $trend_has_data = true; }
        }
    }
}

// ── My OKR (objectives / key activities / results owned by me) ────────────────
$my_okr = [];
$okr_tbl = $conn->query("SHOW TABLES LIKE 'okr_key_activities'");
if ($okr_tbl && $okr_tbl->num_rows > 0) {
    $has_owner = $conn->query("SHOW COLUMNS FROM okr_key_activities LIKE 'owner_id'");
    if ($has_owner && $has_owner->num_rows > 0) {
        $res_ka = $conn->query("SELECT ka.activity_name AS name, ka.progress AS pct, o.title AS obj
                                FROM okr_key_activities ka
                                LEFT JOIN okr_objectives o ON ka.objective_id = o.id
                                WHERE ka.owner_id = $user_id ORDER BY ka.id DESC LIMIT 10");
        if ($res_ka) {
            while ($k = $res_ka->fetch_assoc()) {
                $my_okr[] = ['name' => $k['name'], 'obj' => $k['obj'], 'pct' => $k['pct'] !== null ? (float) $k['pct'] : null];
            }
        }
    }
    // Results owned by me (current/target → %)
    $has_owner_r = $conn->query("SHOW COLUMNS FROM okr_results LIKE 'owner_id'");
    if ($has_owner_r && $has_owner_r->num_rows > 0) {
        $res_kr = $conn->query("SELECT r.metric_name AS name, r.current_value, r.target_value, o.title AS obj
                                FROM okr_results r
                                LEFT JOIN okr_objectives o ON r.objective_id = o.id
                                WHERE r.owner_id = $user_id ORDER BY r.id DESC LIMIT 10");
        if ($res_kr) {
            while ($k = $res_kr->fetch_assoc()) {
                $tv = (float) $k['target_value']; $cv = (float) $k['current_value'];
                $my_okr[] = ['name' => $k['name'], 'obj' => $k['obj'], 'pct' => $tv > 0 ? min(100, round($cv / $tv * 100, 1)) : null];
            }
        }
    }
}

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

                <?php $dash_view = 'member'; include __DIR__ . '/_view_switch.php'; ?>

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

                <!-- KPI trend + My OKR -->
                <div class="dm-row">
                    <div class="dm-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:4px;">Xu hướng KPI <?php echo $trend_year; ?></h3>
                        <?php if ($trend_has_data): ?>
                            <div id="dm-trend"></div>
                        <?php else: ?>
                            <p style="font-size:13px; color:#94a3b8; margin:8px 0 0;">Chưa đủ dữ liệu theo tháng để vẽ xu hướng.</p>
                        <?php endif; ?>
                    </div>
                    <div class="dm-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:8px;">OKR của tôi</h3>
                        <?php if (empty($my_okr)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:8px 0 0;">Bạn chưa được gán OKR nào.</p>
                        <?php else: ?>
                            <?php foreach ($my_okr as $o):
                                $c = dm_pct_color($o['pct']); ?>
                                <div class="dm-kpi">
                                    <div class="dm-kpi-top">
                                        <span style="font-weight:600; color:#0f172a; font-size:13px;"><?php echo htmlspecialchars($o['name'] ?: '—'); ?>
                                            <?php if ($o['obj']): ?><span style="font-size:11px; color:#94a3b8; font-weight:500;"> · <?php echo htmlspecialchars($o['obj']); ?></span><?php endif; ?>
                                        </span>
                                        <span style="font-weight:700; color:<?php echo $c; ?>; font-size:13px; white-space:nowrap;"><?php echo $o['pct'] !== null ? round($o['pct']) . '%' : '—'; ?></span>
                                    </div>
                                    <div class="dm-bar"><div class="dm-fill" style="width:<?php echo $o['pct'] !== null ? min(100, max(0, (int) round($o['pct']))) : 0; ?>%; background:<?php echo $c; ?>;"></div></div>
                                </div>
                            <?php endforeach; ?>
                            <div style="text-align:right; margin-top:10px;"><a href="/modules/okr" style="font-size:12px; color:#3b82f6; text-decoration:none;">→ Xem OKR đầy đủ</a></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dm-panel">
                    <h3 style="margin-bottom:14px;">Tài liệu &amp; lối tắt</h3>
                    <div class="dm-links">
                        <a class="dm-link" href="/kpi">📈 KPI phòng ban</a>
                        <a class="dm-link alt" href="/modules/okr">🎯 OKR</a>
                        <a class="dm-link alt" href="/tai-lieu-quy-trinh">📚 Tài liệu &amp; quy trình</a>
                        <a class="dm-link alt" href="/guides">📖 Hướng dẫn</a>
                        <a class="dm-link alt" href="/my-com">💰 Lương &amp; KPI</a>
                        <a class="dm-link alt" href="/profile">👤 Hồ sơ &amp; mật khẩu</a>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <?php if ($trend_has_data): ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        (function () {
            const data = <?php echo json_encode(array_map(fn($v) => $v === null ? null : (float) $v, array_values($trend))); ?>;
            const cats = <?php echo json_encode(array_map(fn($m) => 'T' . $m, array_keys($trend))); ?>;
            new ApexCharts(document.querySelector('#dm-trend'), {
                series: [{ name: 'KPI %', data: data }],
                chart: { type: 'line', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
                stroke: { curve: 'smooth', width: 3 },
                colors: ['#2563eb'],
                markers: { size: 4, hover: { size: 6 } },
                dataLabels: { enabled: false },
                xaxis: { categories: cats },
                yaxis: { labels: { formatter: v => Math.round(v) + '%' } },
                annotations: { yaxis: [{ y: 100, borderColor: '#10b981', strokeDashArray: 4, label: { text: 'Mục tiêu 100%', style: { color: '#fff', background: '#10b981' } } }] },
                grid: { borderColor: '#f1f5f9' },
                tooltip: { y: { formatter: v => v === null ? 'chưa có' : v + '%' } }
            }).render();
        })();
    </script>
    <?php endif; ?>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>
