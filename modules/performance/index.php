<?php
/**
 * Performance Management launcher - landing page for /performance.
 * App-launcher home gom toàn bộ ứng dụng KPI & OKR vào một chỗ để tiết kiệm
 * không gian sidebar. Mirrors the /projects launcher layout but uses its own
 * KPI/OKR tiles. Standard sidebar/topbar includes.
 */
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

// HR role không dùng nhóm module KPI/OKR (giống cách sidebar ẩn với HR).
if (($_SESSION['role'] ?? '') === 'hr') {
    header('Location: /dashboard');
    exit();
}

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$full_name = $_SESSION['full_name'] ?? '';

$h = (int)date('G');
$greet = $h < 11 ? 'Chào buổi sáng' : ($h < 13 ? 'Chào buổi trưa' : ($h < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));

/* ── KPI & OKR stats (defensive: table/columns may vary) ─────────────── */
function pm_scalar(mysqli $conn, string $sql): float {
    $r = @$conn->query($sql);
    if ($r && $row = $r->fetch_row()) { return (float)$row[0]; }
    return 0;
}
$cur_year    = (int)date('Y');
$cur_quarter = (int)ceil((int)date('n') / 3);

// OKR aggregates for the current year.
$okr_total     = (int) pm_scalar($conn, "SELECT COUNT(*) FROM okr_objectives WHERE year=$cur_year");
$okr_progress  = (int) round(pm_scalar($conn, "SELECT AVG(progress) FROM okr_objectives WHERE year=$cur_year"));
$okr_on_track  = (int) pm_scalar($conn, "SELECT COUNT(*) FROM okr_objectives WHERE year=$cur_year AND status='on_track'");
$okr_at_risk   = (int) pm_scalar($conn, "SELECT COUNT(*) FROM okr_objectives WHERE year=$cur_year AND status IN ('at_risk','behind','off_track')");
// KPI aggregates for the current year.
$kpi_total     = (int) pm_scalar($conn, "SELECT COUNT(*) FROM kpi_definitions WHERE year=$cur_year");

// stat: [label, number, desc, color, svg-inner]
$stats = [
    ['Objectives', $okr_total,    'OKR năm '.$cur_year, '#6366f1', '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'],
    ['On Track',   $okr_on_track, 'Đúng tiến độ',       '#16a34a', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
    ['At Risk',    $okr_at_risk,  'Cần chú ý',          '#f59e0b', '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    ['KPIs',       $kpi_total,    'Chỉ số năm '.$cur_year, '#06b6d4', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
];

// tile: [label, subtitle, href, gradient, svg-inner]
$tiles = [
    ['General KPI Management', 'Quản lý KPI theo tháng/quý', '/kpi', 'linear-gradient(135deg,#6366f1,#4338ca)',
        '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    ['Core & Key KPI', 'Chỉ số cốt lõi & then chốt', '/core-key-kpi', 'linear-gradient(135deg,#16a34a,#15803d)',
        '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
    ['OKR Management', 'Mục tiêu & kết quả then chốt', '/modules/okr', 'linear-gradient(135deg,#06b6d4,#0e7490)',
        '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'],
    ['Guides', 'Hướng dẫn sử dụng', '/guides', 'linear-gradient(135deg,#f59e0b,#b45309)',
        '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Management - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body{font-family:'Inter',sans-serif;margin:0}
    /* Immersive navy column (scoped to the launcher only). */
    .main-content{background:
        radial-gradient(1100px 520px at 85% -12%,rgba(59,130,246,.30),transparent 60%),
        radial-gradient(900px 600px at 0% 115%,rgba(99,102,241,.22),transparent 55%),
        linear-gradient(135deg,#0b1530 0%,#0e1b3a 50%,#101c40 100%) !important;
        min-height:100vh;padding:24px 36px !important}
    .pm-wrap{display:flex;flex-direction:column;min-height:calc(100vh - 96px)}
    /* Topbar blends into the gradient. */
    .main-content .top-bar{background:transparent !important;border:none !important;box-shadow:none !important;justify-content:flex-end !important}
    .main-content .top-bar .page-title{display:none !important}
    .main-content .top-bar .user-info{display:none !important}
    .main-content .top-bar .notification-bell{color:rgba(255,255,255,.85) !important}
    .main-content .top-bar .notification-bell:hover{background:rgba(255,255,255,.12) !important}

    .pm-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap;
        padding:4px 0 28px;margin-bottom:32px;border-bottom:1px solid rgba(255,255,255,.10);color:#fff}
    .pm-greet{font-size:13px;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,.6)}
    .pm-name{font-size:30px;font-weight:700;margin:2px 0 6px;line-height:1.1}
    .pm-sub{font-size:13px;color:rgba(255,255,255,.72);max-width:460px}

    /* cycle / progress widget (analogue of the projects FX widget) */
    .pm-cycle{display:flex;align-items:center;gap:18px;padding:14px 22px;border-radius:16px;
        background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);backdrop-filter:blur(6px)}
    .pm-ring{--p:0;width:64px;height:64px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;
        background:conic-gradient(#38bdf8 calc(var(--p)*1%),rgba(255,255,255,.12) 0)}
    .pm-ring-inner{width:50px;height:50px;border-radius:50%;background:#0e1b3a;display:flex;align-items:center;justify-content:center;
        font-size:15px;font-weight:800;color:#fff}
    .pm-cycle-item{text-align:left;color:#fff}
    .pm-cycle-pair{font-size:11px;letter-spacing:.5px;color:rgba(255,255,255,.55);text-transform:uppercase}
    .pm-cycle-val{font-size:21px;font-weight:700;line-height:1.15}
    .pm-cycle-meta{font-size:10.5px;color:rgba(255,255,255,.45);margin-top:2px}

    /* stat strip */
    .pm-stats{display:flex;flex-wrap:wrap;gap:14px;justify-content:center;margin-bottom:34px}
    .pm-stat{display:flex;align-items:center;gap:13px;min-width:180px;padding:14px 20px;border-radius:14px;
        background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09)}
    .pm-stat-dot{width:42px;height:42px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
    .pm-stat-dot svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .pm-stat-num{font-size:24px;font-weight:800;color:#fff;line-height:1;letter-spacing:-.01em}
    .pm-stat-lbl{font-size:11.5px;color:rgba(255,255,255,.6);margin-top:3px}

    .pm-sec{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.55);margin:0 0 18px;text-align:center}
    .pm-grid{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
    .pm-tile{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;gap:13px;width:170px;padding:16px 8px;border-radius:16px;text-decoration:none;transition:.16s}
    .pm-tile:hover{background:rgba(255,255,255,.05)}
    .pm-tile:hover .pm-ic{transform:translateY(-4px);box-shadow:0 16px 30px rgba(0,0,0,.45)}
    .pm-ic{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(0,0,0,.34);transition:.16s}
    .pm-ic svg{width:35px;height:35px;fill:none;stroke:#fff;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .pm-tl{font-size:14px;font-weight:700;color:#fff}
    .pm-st{font-size:11px;color:rgba(255,255,255,.55);margin-top:-7px}
    .pm-foot{margin-top:auto;padding-top:26px;border-top:1px solid rgba(255,255,255,.08);
        display:flex;align-items:baseline;gap:10px;color:rgba(255,255,255,.45)}
    .pm-foot b{color:rgba(255,255,255,.75);font-size:15px;letter-spacing:.5px}
    .pm-foot span{font-size:12px}
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = 'Performance Management'; include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="pm-wrap">
            <div class="pm-hero">
                <div>
                    <div class="pm-greet"><?= h($greet) ?> 👋</div>
                    <div class="pm-name"><?= h($full_name) ?></div>
                    <div class="pm-sub">Trung tâm quản trị mục tiêu &amp; hiệu suất. Chọn ứng dụng KPI hoặc OKR bên dưới để bắt đầu.</div>
                </div>
                <div class="pm-cycle">
                    <div class="pm-ring" style="--p:<?= max(0, min(100, $okr_progress)) ?>">
                        <div class="pm-ring-inner"><?= $okr_progress ?>%</div>
                    </div>
                    <div class="pm-cycle-item">
                        <div class="pm-cycle-pair">Chu kỳ hiện tại</div>
                        <div class="pm-cycle-val">Q<?= $cur_quarter ?> · <?= $cur_year ?></div>
                        <div class="pm-cycle-meta">Tiến độ OKR trung bình</div>
                    </div>
                </div>
            </div>

            <div class="pm-stats">
            <?php foreach ($stats as $s):
                [$lbl,$num,$desc,$color,$svg] = $s; ?>
                <div class="pm-stat">
                    <div class="pm-stat-dot" style="background:<?= $color ?>">
                        <svg viewBox="0 0 24 24"><?= $svg ?></svg>
                    </div>
                    <div>
                        <div class="pm-stat-num"><?= number_format($num) ?></div>
                        <div class="pm-stat-lbl"><?= h($lbl) ?> · <?= h($desc) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <div class="pm-sec">Ứng dụng</div>
            <div class="pm-grid">
            <?php foreach ($tiles as $t):
                [$label,$sub,$href,$grad,$svg] = $t; ?>
                <a href="<?= h($href) ?>" class="pm-tile">
                    <div class="pm-ic" style="background:<?= $grad ?>"><svg viewBox="0 0 24 24"><?= $svg ?></svg></div>
                    <div class="pm-tl"><?= h($label) ?></div>
                    <div class="pm-st"><?= h($sub) ?></div>
                </a>
            <?php endforeach; ?>
            </div>

            <div class="pm-foot">
                <b>Performance Management</b><span>Quản trị mục tiêu &amp; hiệu suất AHT — KPI &amp; OKR</span>
            </div>
        </div>
    </div>
</div>
</body>
</html>
