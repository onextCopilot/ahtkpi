<?php
/**
 * Projects launcher - landing page for /projects.
 * Base.vn-style home: an immersive green gradient column with a greeting and a
 * grid of glass app tiles (no clock/weather). Mirrors the /hrm launcher layout
 * but uses the standard sidebar/topbar includes.
 */
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$role = $_SESSION['role'] ?? 'user';
// Same gate as the Projects pages: AM/BD users + admin.
if (empty($_SESSION['is_am_bd']) && $role !== 'admin') {
    header('Location: /dashboard');
    exit();
}

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$full_name = $_SESSION['full_name'] ?? '';

/* ── Business stats (defensive: table/columns may vary) ─────────────── */
function pj_scalar(mysqli $conn, string $sql): int {
    $r = @$conn->query($sql);
    if ($r && $row = $r->fetch_row()) { return (int)$row[0]; }
    return 0;
}
$stat_total   = pj_scalar($conn, "SELECT COUNT(*) FROM pakd");
$stat_pending = pj_scalar($conn, "SELECT COUNT(*) FROM pakd WHERE status='pending'");
$stat_won     = pj_scalar($conn, "SELECT COUNT(*) FROM pakd WHERE won_status='won'");
$stat_open    = pj_scalar($conn, "SELECT COUNT(*) FROM pakd WHERE won_status IS NULL OR won_status=''");
$stats = [
    ['Phương án',  $stat_total,   'Tổng PA/SX',   '#6366f1'],
    ['Chờ duyệt',  $stat_pending, 'Đang chờ',     '#f59e0b'],
    ['Đang mở',    $stat_open,    'Chưa chốt',    '#06b6d4'],
    ['Deal Won',   $stat_won,     'Đã thắng',     '#16a34a'],
];

// CEO approver? (controls the CEO Review tile) — same logic as the sidebar.
$is_ceo_approver = false;
if (isset($conn)) {
    $caRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='pasx_ceo_approvers' LIMIT 1");
    if ($caRes && $caRow = $caRes->fetch_assoc()) {
        $caList = array_map('intval', json_decode($caRow['setting_value'] ?? '[]', true) ?: []);
        $is_ceo_approver = in_array((int)$_SESSION['user_id'], $caList, true);
    }
}

$h = (int)date('G');
$greet = $h < 11 ? 'Chào buổi sáng' : ($h < 13 ? 'Chào buổi trưa' : ($h < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));

// tile: [label, subtitle, href, gradient, svg-inner]
$tiles = [
    ['Business Plans', 'Phương án kinh doanh', '/projects/phuong-an-kinh-doanh', 'linear-gradient(135deg,#6366f1,#4338ca)',
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>'],
    ['Projects', 'Dự án của tôi', '/projects/du-an', 'linear-gradient(135deg,#16a34a,#15803d)',
        '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>'],
    ['Milestone Logs', 'Nhật ký milestone', '/projects/milestones/logs', 'linear-gradient(135deg,#06b6d4,#0e7490)',
        '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
];
if ($is_ceo_approver) {
    $tiles[] = ['CEO Review', 'Phê duyệt PA/SX', '/projects/ceo-review', 'linear-gradient(135deg,#f59e0b,#b45309)',
        '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><polyline points="9 11 12 14 22 4"/>'];
}
if ($role === 'admin') {
    $tiles[] = ['Settings', 'Cấu hình phân hệ Projects', '/projects/settings', 'linear-gradient(135deg,#64748b,#334155)',
        '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - AHT KPI</title>
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
    .pj-wrap{display:flex;flex-direction:column;min-height:calc(100vh - 96px)}
    /* Topbar blends into the gradient. */
    .main-content .top-bar{background:transparent !important;border:none !important;box-shadow:none !important;justify-content:flex-end !important}
    .main-content .top-bar .page-title{display:none !important}
    .main-content .top-bar .user-info{display:none !important}
    .main-content .top-bar .notification-bell{color:rgba(255,255,255,.85) !important}
    .main-content .top-bar .notification-bell:hover{background:rgba(255,255,255,.12) !important}

    .pj-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap;
        padding:4px 0 28px;margin-bottom:24px;border-bottom:1px solid rgba(255,255,255,.10);color:#fff}
    .pj-greet{font-size:13px;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,.6)}
    .pj-name{font-size:30px;font-weight:700;margin:2px 0 6px;line-height:1.1}
    .pj-sub{font-size:13px;color:rgba(255,255,255,.72);max-width:460px}
    /* live FX widget (business analogue of the weather widget) */
    .pj-fx{display:flex;align-items:center;gap:18px;padding:14px 20px;border-radius:16px;
        background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);backdrop-filter:blur(6px)}
    .pj-fx.loading{opacity:.45}
    .pj-fx-item{text-align:right;color:#fff}
    .pj-fx-item + .pj-fx-item{padding-left:18px;border-left:1px solid rgba(255,255,255,.14)}
    .pj-fx-pair{font-size:11px;letter-spacing:.5px;color:rgba(255,255,255,.55);text-transform:uppercase}
    .pj-fx-val{font-size:21px;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.15}
    .pj-fx-val small{font-size:11px;font-weight:500;color:rgba(255,255,255,.5)}
    .pj-fx-meta{font-size:10.5px;color:rgba(255,255,255,.45);margin-top:2px}

    /* business stat strip */
    .pj-stats{display:flex;flex-wrap:wrap;gap:14px;justify-content:center;margin-bottom:30px}
    .pj-stat{display:flex;align-items:center;gap:13px;min-width:170px;padding:14px 20px;border-radius:14px;
        background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09)}
    .pj-stat-dot{width:42px;height:42px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
    .pj-stat-dot svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .pj-stat-num{font-size:24px;font-weight:800;color:#fff;line-height:1;letter-spacing:-.01em}
    .pj-stat-lbl{font-size:11.5px;color:rgba(255,255,255,.6);margin-top:3px}

    .pj-sec{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.55);margin:0 0 18px;text-align:center}
    .pj-grid{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
    .pj-tile{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;gap:13px;width:156px;padding:16px 8px;border-radius:16px;text-decoration:none;transition:.16s}
    .pj-tile:hover{background:rgba(255,255,255,.05)}
    .pj-tile:hover .pj-ic{transform:translateY(-4px);box-shadow:0 16px 30px rgba(0,0,0,.45)}
    .pj-ic{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(0,0,0,.34);transition:.16s}
    .pj-ic svg{width:35px;height:35px;fill:none;stroke:#fff;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .pj-tl{font-size:14px;font-weight:700;color:#fff}
    .pj-st{font-size:11px;color:rgba(255,255,255,.55);margin-top:-7px}
    .pj-foot{margin-top:auto;padding-top:26px;border-top:1px solid rgba(255,255,255,.08);
        display:flex;align-items:baseline;gap:10px;color:rgba(255,255,255,.45)}
    .pj-foot b{color:rgba(255,255,255,.75);font-size:15px;letter-spacing:.5px}
    .pj-foot span{font-size:12px}
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = 'Projects'; include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="pj-wrap">
            <div class="pj-hero">
                <div>
                    <div class="pj-greet"><?= h($greet) ?> 👋</div>
                    <div class="pj-name"><?= h($full_name) ?></div>
                    <div class="pj-sub">Chào mừng đến hệ thống Projects AHT. Chọn ứng dụng bên dưới để bắt đầu.</div>
                </div>
                <div class="pj-fx loading" id="pjFx">
                    <div class="pj-fx-item">
                        <div class="pj-fx-pair">USD → VND</div>
                        <div class="pj-fx-val" id="fxUsd">--</div>
                    </div>
                    <div class="pj-fx-item">
                        <div class="pj-fx-pair">EUR → VND</div>
                        <div class="pj-fx-val" id="fxEur">--</div>
                        <div class="pj-fx-meta" id="fxMeta">Đang tải tỷ giá…</div>
                    </div>
                </div>
            </div>

            <div class="pj-stats">
            <?php foreach ($stats as $s):
                [$lbl,$num,$desc,$color] = $s; ?>
                <div class="pj-stat">
                    <div class="pj-stat-dot" style="background:<?= $color ?>">
                        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                    </div>
                    <div>
                        <div class="pj-stat-num"><?= number_format($num) ?></div>
                        <div class="pj-stat-lbl"><?= h($lbl) ?> · <?= h($desc) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <div class="pj-sec">Ứng dụng</div>
            <div class="pj-grid">
            <?php foreach ($tiles as $t):
                [$label,$sub,$href,$grad,$svg] = $t; ?>
                <a href="<?= h($href) ?>" class="pj-tile">
                    <div class="pj-ic" style="background:<?= $grad ?>"><svg viewBox="0 0 24 24"><?= $svg ?></svg></div>
                    <div class="pj-tl"><?= h($label) ?></div>
                    <div class="pj-st"><?= h($sub) ?></div>
                </a>
            <?php endforeach; ?>
            </div>

            <div class="pj-foot">
                <b>Projects</b><span>Quản lý phương án kinh doanh &amp; dự án AHT</span>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    // Live FX rates — the business analogue of the weather widget.
    function fmt(n){ return n ? Math.round(n).toLocaleString('vi-VN') : '--'; }
    fetch('https://open.er-api.com/v6/latest/USD').then(function(r){return r.json();}).then(function(d){
        var R=(d&&d.rates)||{}, usd=R.VND, eur=(R.VND&&R.EUR)?R.VND/R.EUR:0;
        document.getElementById('fxUsd').innerHTML = fmt(usd)+' <small>₫</small>';
        document.getElementById('fxEur').innerHTML = fmt(eur)+' <small>₫</small>';
        var t=(d&&d.time_last_update_utc||'').slice(5,16);
        document.getElementById('fxMeta').textContent = t ? ('Cập nhật '+t) : 'Tỷ giá tham khảo';
        document.getElementById('pjFx').classList.remove('loading');
    }).catch(function(){ document.getElementById('pjFx').style.display='none'; });
})();
</script>
</body>
</html>
