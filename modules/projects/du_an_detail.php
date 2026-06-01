<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id      = $_SESSION['user_id'];
$role         = $_SESSION['role'] ?? 'user';
$my_full_name = $_SESSION['full_name'] ?? '';
$is_admin     = ($role === 'admin');
$pakd_id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pakd_id) {
    header('Location: /projects/du-an');
    exit();
}

// Ensure columns exist
foreach ([
    'assignment_date'  => 'DATETIME DEFAULT NULL',
    'expected_closing' => 'DATE DEFAULT NULL',
    'odoo_stage_id'    => 'INT DEFAULT NULL',
    'division_names'   => 'VARCHAR(500) DEFAULT NULL',
    'won_status'       => 'VARCHAR(20) DEFAULT NULL',
    'lost_reason'      => 'VARCHAR(255) DEFAULT NULL',
    'revenue'          => 'DECIMAL(20,2) DEFAULT 0',
    'gross_profit'     => 'DECIMAL(20,2) DEFAULT 0',
    'pasx_value'       => 'DECIMAL(20,2) DEFAULT 0',
    'contract_no'      => 'VARCHAR(255) DEFAULT NULL',
    'sales_order_no'   => 'VARCHAR(255) DEFAULT NULL',
    'purchase_order_no'=> 'VARCHAR(255) DEFAULT NULL',
    'timeline'         => 'TEXT DEFAULT NULL',
    'fin_data'         => 'JSON DEFAULT NULL',
    'approved_by_name' => 'VARCHAR(255) DEFAULT NULL',
    'approved_at'      => 'DATETIME DEFAULT NULL',
    'pasx_id'          => 'VARCHAR(64) DEFAULT NULL',
    'pasx_status'      => 'VARCHAR(32) DEFAULT NULL',
    'pasx_requested_at'=> 'DATETIME DEFAULT NULL',
    'project_type'     => "VARCHAR(50) DEFAULT 'external'",
] as $_col => $_def) {
    $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pakd' AND COLUMN_NAME='$_col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE pakd ADD COLUMN `$_col` $_def");
}
unset($_col, $_def, $r);

// Fetch the project
$stmt = $conn->prepare("SELECT * FROM pakd WHERE id = ? AND won_status = 'won'");
$stmt->bind_param("i", $pakd_id);
$stmt->execute();
$pakd = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pakd) {
    header('Location: /projects/du-an');
    exit();
}

// Access control: AM only sees their own projects
if (!$is_admin) {
    $owner_match = (!empty($pakd['am_user_id']) && (int)$pakd['am_user_id'] === $user_id)
                || (!empty($pakd['am_name'])    && $pakd['am_name'] === $my_full_name);
    if (!$owner_match) {
        header('HTTP/1.1 403 Forbidden');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;">
            <h2 style="color:#dc2626;">⛔ Bạn không có quyền xem dự án này</h2>
            <p style="color:#64748b;">Chỉ AM phụ trách mới có thể truy cập.</p>
            <a href="/projects/du-an" style="color:#6366f1;">← Quay lại danh sách</a>
        </body></html>';
        exit;
    }
}

// User avatar
$userAvatarMap = [];
$uRes = $conn->query("SELECT email, full_name, avatar FROM users WHERE email IS NOT NULL AND email != ''");
if ($uRes) while ($u = $uRes->fetch_assoc()) $userAvatarMap[strtolower($u['email'])] = $u;

// Financial data
$fin_saved     = !empty($pakd['fin_data']) ? (json_decode($pakd['fin_data'], true) ?? []) : [];
$fin_rev_gross = (float)($pakd['revenue']      ?? 0);
$fin_rev_net   = !empty($fin_saved['rev_net']) ? (float)$fin_saved['rev_net'] : $fin_rev_gross;
$fin_human_cost   = (float)($fin_saved['human_cost']    ?? 0);
$fin_overtime     = (float)($fin_saved['overtime_cost'] ?? 0);
$pasx_has_data    = ($fin_human_cost > 0 || $fin_overtime > 0);
$fin_prod_cost    = $pasx_has_data ? ($fin_human_cost + $fin_overtime) : (float)($pakd['pasx_value'] ?? 0);
$fin_gross_profit = (float)($pakd['gross_profit'] ?? 0);
$fin_margin_pct   = $fin_rev_net > 0 ? ($fin_gross_profit / $fin_rev_net * 100) : 0;
$fin_total_cost   = $fin_rev_net - $fin_gross_profit;

// PASX history (latest 5)
$pasx_logs = [];
try {
    $wl = $conn->prepare("SELECT id, event, status, http_status, received_at FROM pasx_webhook_logs WHERE pakd_id=? ORDER BY received_at DESC LIMIT 5");
    $wl->bind_param("i", $pakd_id);
    $wl->execute();
    $wlRes = $wl->get_result();
    while ($wRow = $wlRes->fetch_assoc()) $pasx_logs[] = $wRow;
    $wl->close();
} catch (\Throwable $e) {}

function formatVND3($num) {
    if ($num >= 1e9)  return number_format($num/1e9, 2, ',', '.').' tỷ';
    if ($num >= 1e6)  return number_format($num/1e6, 1, ',', '.').' triệu';
    if ($num >= 1e3)  return number_format($num/1e3, 0, ',', '.').'K';
    return number_format($num, 0, ',', '.');
}

function formatVNDFull($num) {
    return number_format($num, 0, ',', '.');
}

function avatarColor3($name) {
    $palette = [
        ['bg' => '#ddd6fe', 'fg' => '#5b21b6'],
        ['bg' => '#bfdbfe', 'fg' => '#1e40af'],
        ['bg' => '#bbf7d0', 'fg' => '#166534'],
        ['bg' => '#fed7aa', 'fg' => '#9a3412'],
        ['bg' => '#fde68a', 'fg' => '#92400e'],
        ['bg' => '#fbcfe8', 'fg' => '#9d174d'],
        ['bg' => '#a5f3fc', 'fg' => '#164e63'],
        ['bg' => '#d9f99d', 'fg' => '#3f6212'],
    ];
    return $palette[abs(crc32($name ?: '?')) % count($palette)];
}

$statusLabels = ['draft'=>'Nháp','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];
$statusColors = ['draft'=>'#64748b','pending'=>'#d97706','approved'=>'#16a34a','rejected'=>'#dc2626'];
$statusBg     = ['draft'=>'#f1f5f9','pending'=>'#fef9c3','approved'=>'#dcfce7','rejected'=>'#fee2e2'];
$st = $pakd['status'] ?? 'draft';

$pasx_status_labels = [
    'created'      => 'Đã gửi — Đang làm PASX',
    'processing'   => 'Đang xử lý',
    'completed'    => 'Hoàn thành',
    'approved'     => 'Đã duyệt',
    'rejected'     => 'Đã từ chối — Chờ rebuild',
    'pending_ceo'  => 'Chờ CEO duyệt',
    'cancelled'    => 'Đã hủy',
];
$pasx_status_colors = [
    'created'     => '#7c3aed',
    'processing'  => '#2563eb',
    'completed'   => '#16a34a',
    'approved'    => '#16a34a',
    'rejected'    => '#dc2626',
    'pending_ceo' => '#d97706',
    'cancelled'   => '#94a3b8',
];
$pst = $pakd['pasx_status'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pakd['name']) ?> — My Project</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5;
            --success: #16a34a; --warning: #d97706; --danger: #dc2626;
            --bg: #f8fafc; --card: #ffffff; --slate: #1e293b;
            --gray: #64748b; --lgray: #94a3b8; --border: #e2e8f0;
            --r-xl: 18px; --r-lg: 12px; --r-md: 8px; --r-sm: 6px;
            --sh-sm: 0 1px 3px rgba(0,0,0,.06); --sh-md: 0 4px 16px rgba(0,0,0,.08);
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--slate); margin: 0; }
        .main-content { flex: 1; padding: 0; min-height: 100vh; }

        /* ── Breadcrumb + Actions Bar ── */
        .top-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 32px; background: white; border-bottom: 1px solid var(--border);
            gap: 12px; flex-wrap: wrap;
        }
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--gray); }
        .breadcrumb a { color: var(--gray); text-decoration: none; }
        .breadcrumb a:hover { color: var(--primary); }
        .breadcrumb .sep { color: var(--lgray); }
        .breadcrumb .current { color: var(--slate); font-weight: 600; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .top-actions { display: flex; align-items: center; gap: 8px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: var(--r-md); font-size: 13px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; text-decoration: none; transition: all .15s; white-space: nowrap; }
        .btn-outline { background: white; color: var(--gray); border: 1px solid var(--border); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-green { background: #16a34a; color: white; }
        .btn-green:hover { background: #15803d; }

        /* ── Won Banner ── */
        .won-banner {
            display: flex; align-items: center; gap: 16px;
            padding: 14px 32px;
            background: linear-gradient(90deg, #fef3c7 0%, #fde68a 60%, #fef9c3 100%);
            border-bottom: 2px solid #f59e0b;
        }
        .won-banner-icon { font-size: 26px; flex-shrink: 0; }
        .won-banner-body { flex: 1; }
        .won-banner-title { font-size: 14px; font-weight: 800; color: #92400e; }
        .won-banner-sub   { font-size: 12px; color: #b45309; margin-top: 2px; }
        .won-badge { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff; font-size:12px; font-weight:800; padding:5px 14px; border-radius:6px; box-shadow:0 2px 8px rgba(245,158,11,.4); }

        /* ── Page Content ── */
        .page-content { padding: 28px 32px 48px; }

        /* ── Page header ── */
        .page-header { display: flex; align-items: flex-start; gap: 18px; margin-bottom: 28px; }
        .page-icon { width: 52px; height: 52px; background: linear-gradient(135deg, #16a34a, #15803d); border-radius: var(--r-lg); display: flex; align-items: center; justify-content: center; color: white; font-size: 22px; box-shadow: 0 4px 12px rgba(22,163,74,.3); flex-shrink: 0; }
        .page-header-body { flex: 1; min-width: 0; }
        .page-header-body h1 { font-size: 22px; font-weight: 800; margin: 0 0 6px; line-height: 1.2; }
        .page-header-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .status-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .odoo-link { font-size: 12px; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 500; }
        .odoo-link:hover { text-decoration: underline; }
        .dot-sep { color: var(--lgray); }

        /* ── Metric Cards Row ── */
        .metrics-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .metric-card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--sh-sm); padding: 18px 20px; }
        .metric-card.highlight { border-color: #bbf7d0; background: linear-gradient(135deg, #f0fdf4, #dcfce7); }
        .metric-card.highlight-warn { border-color: #fde68a; background: linear-gradient(135deg, #fffbeb, #fef9c3); }
        .metric-card.highlight-danger { border-color: #fecaca; background: linear-gradient(135deg, #fef2f2, #fee2e2); }
        .metric-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray); margin-bottom: 8px; }
        .metric-val { font-size: 24px; font-weight: 800; color: var(--slate); line-height: 1; }
        .metric-val.green { color: #16a34a; }
        .metric-val.blue  { color: #2563eb; }
        .metric-val.amber { color: #d97706; }
        .metric-sub { font-size: 12px; color: var(--gray); margin-top: 5px; }

        /* ── Two-column layout ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }

        /* ── Cards ── */
        .card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--sh-sm); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .card-header h3 { font-size: 14px; font-weight: 700; color: var(--slate); margin: 0; }
        .card-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .card-icon.green  { background: rgba(22,163,74,.1);  color: #16a34a; }
        .card-icon.blue   { background: rgba(37,99,235,.1);  color: #2563eb; }
        .card-icon.amber  { background: rgba(217,119,6,.1);  color: #d97706; }
        .card-icon.purple { background: rgba(124,58,237,.1); color: #7c3aed; }
        .card-icon.gray   { background: #f1f5f9;             color: #64748b; }
        .card-body { padding: 16px 20px; }

        /* ── Info Grid ── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .info-item { display: flex; flex-direction: column; gap: 4px; }
        .info-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray); }
        .info-value { font-size: 13px; font-weight: 500; color: var(--slate); display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .info-value.empty { color: var(--lgray); font-style: italic; }
        .info-grid.col-3 { grid-template-columns: 1fr 1fr 1fr; }

        /* ── AM Cell ── */
        .am-cell { display: flex; align-items: center; gap: 8px; }
        .am-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; }
        .am-initials { width: 28px; height: 28px; border-radius: 50%; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        /* ── Financial table ── */
        .fin-row { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); gap: 12px; }
        .fin-row:last-child { border-bottom: none; }
        .fin-row.total { background: #f8fafc; margin: 0 -20px; padding: 10px 20px; }
        .fin-row-label { flex: 1; font-size: 13px; color: var(--slate); }
        .fin-row-label .fin-sub { font-size: 11px; color: var(--lgray); margin-top: 1px; }
        .fin-row-val { font-size: 13px; font-weight: 700; text-align: right; white-space: nowrap; }
        .fin-row-pct { font-size: 11px; color: var(--gray); text-align: right; width: 56px; }
        .fin-row.cat .fin-row-label { font-weight: 700; color: #1e293b; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .fin-row.cat { background: #f8fafc; margin: 0 -20px; padding: 8px 20px; }
        .divider { border: none; border-top: 2px solid var(--border); margin: 4px 0; }

        /* ── PASX Status ── */
        .pasx-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .pasx-log-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .pasx-log-item:last-child { border-bottom: none; }
        .pasx-log-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
        .pasx-log-body { flex: 1; min-width: 0; }
        .pasx-log-event { font-size: 12px; font-weight: 600; color: var(--slate); }
        .pasx-log-date  { font-size: 11px; color: var(--lgray); margin-top: 2px; }

        /* ── Stage timeline ── */
        .stage-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border); }
        .stage-row:last-child { border-bottom: none; }
        .stage-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .stage-body { flex: 1; min-width: 0; }
        .stage-label { font-size: 12px; font-weight: 600; color: var(--slate); }
        .stage-date  { font-size: 11px; color: var(--lgray); margin-top: 1px; }
        .stage-val   { font-size: 12px; color: var(--gray); text-align: right; }

        /* ── Probability stars ── */
        .star-rating { display: inline-flex; gap: 1px; }
        .star-rating .fa-star     { font-size: 13px; color: #f59e0b; }
        .star-rating .fa-star.off { color: #d1d5db; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 28px 20px; color: var(--gray); }
        .empty-state i { font-size: 24px; color: var(--lgray); margin-bottom: 8px; display: block; }
        .empty-state p { font-size: 12px; margin: 0; }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .detail-grid { grid-template-columns: 1fr; }
            .metrics-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 680px) {
            .metrics-row { grid-template-columns: 1fr; }
            .page-content { padding: 16px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script>
    (function() {
        var _prev = localStorage.getItem('sidebar-collapsed');
        if (_prev !== 'true') {
            localStorage.setItem('sidebar-collapsed', 'true');
            window.addEventListener('beforeunload', function() {
                localStorage.setItem('sidebar-collapsed', _prev === null ? 'false' : _prev);
            }, { once: true });
        }
    })();
    </script>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = htmlspecialchars($pakd['name']); include __DIR__ . '/../includes/topbar.php'; ?>

        <!-- Top Bar -->
        <div class="top-bar">
            <div class="breadcrumb">
                <a href="/projects/du-an"><i class="fas fa-trophy" style="font-size:11px;"></i> My Project</a>
                <span class="sep">/</span>
                <span class="current"><?= htmlspecialchars($pakd['name']) ?></span>
            </div>
            <div class="top-actions">
                <a href="/projects/du-an" class="btn btn-outline">
                    <i class="fas fa-arrow-left" style="font-size:11px;"></i> Quay lại danh sách
                </a>
                <?php if ($is_admin || (!empty($pakd['am_user_id']) && (int)$pakd['am_user_id'] === $user_id)): ?>
                <a href="/projects/pakd/edit?id=<?= $pakd_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit" style="font-size:11px;"></i> Chỉnh sửa PAKD
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deal Won Banner -->
        <div class="won-banner">
            <div class="won-banner-icon">🏆</div>
            <div class="won-banner-body">
                <div class="won-banner-title">Chúc mừng! Opportunity này đã được Deal Won</div>
                <div class="won-banner-sub">
                    Stage Odoo: <strong><?= htmlspecialchars($pakd['odoo_stage_name'] ?? '—') ?></strong>
                    <?php if (!empty($pakd['assignment_date'])): ?>
                    &nbsp;·&nbsp; Assigned: <strong><?= date('d/m/Y', strtotime($pakd['assignment_date'])) ?></strong>
                    <?php endif; ?>
                </div>
            </div>
            <div class="won-badge"><i class="fas fa-trophy"></i> DEAL WON</div>
        </div>

        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-icon"><i class="fas fa-trophy"></i></div>
                <div class="page-header-body">
                    <h1><?= htmlspecialchars($pakd['name']) ?></h1>
                    <div class="page-header-meta">
                        <span class="status-chip" style="background:<?= htmlspecialchars($statusBg[$st] ?? '#f1f5f9') ?>;color:<?= htmlspecialchars($statusColors[$st] ?? '#64748b') ?>;">
                            <i class="fas fa-circle" style="font-size:7px;"></i>
                            <?= $statusLabels[$st] ?? $st ?>
                        </span>
                        <?php if (!empty($pakd['department'])): ?>
                        <span style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($pakd['department']) ?></span>
                        <span class="dot-sep">·</span>
                        <?php endif; ?>
                        <?php if (!empty($pakd['company_name'])): ?>
                        <span style="font-size:12px;color:var(--gray);"><i class="fas fa-building" style="font-size:10px;"></i> <?= htmlspecialchars($pakd['company_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($pakd['odoo_url'])): ?>
                        <a href="<?= htmlspecialchars($pakd['odoo_url']) ?>" target="_blank" class="odoo-link">
                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i> Odoo #<?= htmlspecialchars($pakd['odoo_opp_id'] ?? '') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="metrics-row">
                <div class="metric-card">
                    <div class="metric-label"><i class="fas fa-sack-dollar" style="margin-right:4px;color:#d97706;"></i> Giá trị Opp</div>
                    <div class="metric-val blue"><?= formatVND3($pakd['opp_value']) ?></div>
                    <div class="metric-sub"><?= htmlspecialchars($pakd['currency'] ?? 'VND') ?></div>
                </div>
                <div class="metric-card <?= $fin_rev_gross > 0 ? 'highlight' : '' ?>">
                    <div class="metric-label"><i class="fas fa-chart-line" style="margin-right:4px;color:#16a34a;"></i> Doanh thu</div>
                    <div class="metric-val green"><?= $fin_rev_gross > 0 ? formatVND3($fin_rev_gross) : '—' ?></div>
                    <div class="metric-sub">VND</div>
                </div>
                <div class="metric-card <?= $fin_gross_profit > 0 ? ($fin_margin_pct >= 20 ? 'highlight' : 'highlight-warn') : '' ?>">
                    <div class="metric-label"><i class="fas fa-coins" style="margin-right:4px;color:#7c3aed;"></i> Lợi nhuận gộp</div>
                    <div class="metric-val" style="color:<?= $fin_gross_profit > 0 ? ($fin_margin_pct >= 20 ? '#16a34a' : '#d97706') : 'var(--lgray)' ?>">
                        <?= $fin_gross_profit > 0 ? formatVND3($fin_gross_profit) : '—' ?>
                    </div>
                    <div class="metric-sub"><?= $fin_margin_pct > 0 ? 'Margin: ' . number_format($fin_margin_pct, 1) . '%' : 'Chưa cập nhật' ?></div>
                </div>
                <?php
                $prob = (int)($pakd['opp_probability'] ?? 0);
                $stars = max(0, min(5, (int)round($prob / 20)));
                ?>
                <div class="metric-card">
                    <div class="metric-label"><i class="fas fa-percent" style="margin-right:4px;color:#2563eb;"></i> Xác suất</div>
                    <div class="metric-val"><?= $prob ?>%</div>
                    <div class="metric-sub" style="display:flex;align-items:center;gap:2px;margin-top:6px;">
                        <span class="star-rating">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <i class="fas fa-star<?= $i<=$stars?'':' off' ?>"></i>
                            <?php endfor; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Two-column detail grid -->
            <div class="detail-grid">

                <!-- Left column -->
                <div>
                    <!-- Project Info -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green"><i class="fas fa-folder-open"></i></div>
                            <h3>Thông tin dự án</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-grid col-3">
                                <div class="info-item">
                                    <div class="info-label">AM / Sales</div>
                                    <div class="info-value">
                                        <?php
                                        $amEmail = $pakd['am_email'] ?? '';
                                        $amUser  = !empty($amEmail) ? ($userAvatarMap[strtolower($amEmail)] ?? null) : null;
                                        $c       = avatarColor3($pakd['am_name'] ?? '');
                                        ?>
                                        <div class="am-cell">
                                            <?php if ($amUser && !empty($amUser['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($amUser['avatar']) ?>" class="am-avatar" alt="">
                                            <?php else:
                                                $parts = array_filter(explode(' ', $pakd['am_name'] ?? ''));
                                                $ini   = strtoupper(($parts[0][0] ?? '') . (count($parts) > 1 ? end($parts)[0] : ''));
                                            ?>
                                                <div class="am-initials" style="background:<?= $c['bg'] ?>;color:<?= $c['fg'] ?>;"><?= htmlspecialchars($ini ?: '?') ?></div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($pakd['am_name'] ?: '—') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Bộ phận</div>
                                    <div class="info-value <?= empty($pakd['department']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['department'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Division</div>
                                    <div class="info-value <?= empty($pakd['division_names']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['division_names'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Loại dự án</div>
                                    <div class="info-value">
                                        <?php $pt = strtolower($pakd['project_type'] ?? 'external'); ?>
                                        <i class="fas <?= $pt === 'internal' ? 'fa-building' : 'fa-desktop' ?>" style="font-size:12px;color:var(--gray);"></i>
                                        <?= $pt === 'internal' ? 'Internal' : 'External' ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Ngày assign</div>
                                    <div class="info-value <?= empty($pakd['assignment_date']) ? 'empty' : '' ?>">
                                        <?= !empty($pakd['assignment_date']) ? date('d/m/Y', strtotime($pakd['assignment_date'])) : '—' ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Dự kiến đóng</div>
                                    <div class="info-value <?= empty($pakd['expected_closing']) ? 'empty' : '' ?>">
                                        <?= !empty($pakd['expected_closing']) ? date('d/m/Y', strtotime($pakd['expected_closing'])) : '—' ?>
                                    </div>
                                </div>
                                <div class="info-item" style="grid-column: span 3;">
                                    <div class="info-label">Opportunity</div>
                                    <div class="info-value <?= empty($pakd['opportunity_name']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['opportunity_name'] ?: '—') ?>
                                        <?php if (!empty($pakd['odoo_url'])): ?>
                                        &nbsp;<a href="<?= htmlspecialchars($pakd['odoo_url']) ?>" target="_blank" class="odoo-link">
                                            <i class="fas fa-external-link-alt"></i> Mở Odoo
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contract Info -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon amber"><i class="fas fa-file-contract"></i></div>
                            <h3>Thông tin hợp đồng</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Khách hàng</div>
                                    <div class="info-value <?= empty($pakd['company_name']) ? 'empty' : '' ?>">
                                        <i class="fas fa-building" style="font-size:11px;color:var(--lgray);"></i>
                                        <?= htmlspecialchars($pakd['company_name'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Số hợp đồng</div>
                                    <div class="info-value <?= empty($pakd['contract_no']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['contract_no'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Sales Order</div>
                                    <div class="info-value <?= empty($pakd['sales_order_no']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['sales_order_no'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Purchase Order</div>
                                    <div class="info-value <?= empty($pakd['purchase_order_no']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['purchase_order_no'] ?: '—') ?>
                                    </div>
                                </div>
                                <?php if (!empty($pakd['timeline'])): ?>
                                <div class="info-item" style="grid-column: span 2;">
                                    <div class="info-label">Thời gian triển khai</div>
                                    <div class="info-value"><?= htmlspecialchars($pakd['timeline']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <?php if ($fin_rev_gross > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon blue"><i class="fas fa-chart-pie"></i></div>
                            <h3>Tổng quan tài chính</h3>
                        </div>
                        <div class="card-body" style="padding: 0 20px;">
                            <div class="fin-row cat">
                                <div class="fin-row-label">Doanh thu</div>
                                <div class="fin-row-pct">100%</div>
                                <div class="fin-row-val" style="color:#16a34a;"><?= formatVNDFull($fin_rev_gross) ?> VND</div>
                            </div>
                            <?php if ($fin_rev_net !== $fin_rev_gross): ?>
                            <div class="fin-row">
                                <div class="fin-row-label" style="padding-left:12px;">Doanh thu thuần
                                    <div class="fin-sub">Sau giảm trừ</div>
                                </div>
                                <div class="fin-row-pct"></div>
                                <div class="fin-row-val" style="color:#2563eb;"><?= formatVNDFull($fin_rev_net) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($fin_prod_cost > 0): ?>
                            <div class="fin-row">
                                <div class="fin-row-label" style="padding-left:12px;">Chi phí sản xuất (PASX)
                                    <div class="fin-sub"><?= $pasx_has_data ? 'Human + Overtime cost' : 'Từ Phương án sản xuất' ?></div>
                                </div>
                                <div class="fin-row-pct"><?= $fin_rev_net > 0 ? number_format($fin_prod_cost/$fin_rev_net*100,1).'%' : '' ?></div>
                                <div class="fin-row-val" style="color:#dc2626;">-<?= formatVNDFull($fin_prod_cost) ?></div>
                            </div>
                            <?php endif; ?>
                            <hr class="divider">
                            <div class="fin-row cat" style="<?= $fin_margin_pct >= 20 ? 'background:#f0fdf4;' : ($fin_margin_pct > 0 ? 'background:#fffbeb;' : '') ?>">
                                <div class="fin-row-label">Lợi nhuận gộp</div>
                                <div class="fin-row-pct" style="color:<?= $fin_margin_pct >= 20 ? '#16a34a' : ($fin_margin_pct > 0 ? '#d97706' : 'var(--lgray)') ?>;">
                                    <?= $fin_margin_pct > 0 ? number_format($fin_margin_pct, 1).'%' : '—' ?>
                                </div>
                                <div class="fin-row-val" style="color:<?= $fin_gross_profit > 0 ? ($fin_margin_pct >= 20 ? '#16a34a' : '#d97706') : 'var(--lgray)' ?>;">
                                    <?= $fin_gross_profit > 0 ? formatVNDFull($fin_gross_profit).' VND' : '—' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right column -->
                <div>
                    <!-- PASX Status -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon purple"><i class="fas fa-cogs"></i></div>
                            <h3>Phương án sản xuất (PASX)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pakd['pasx_id'])): ?>
                            <div style="margin-bottom:12px;">
                                <div class="info-label" style="margin-bottom:6px;">Trạng thái</div>
                                <?php
                                $pstColor = $pasx_status_colors[$pst] ?? '#64748b';
                                $pstLabel = $pasx_status_labels[$pst] ?? strtoupper($pst);
                                ?>
                                <span class="pasx-status-badge" style="background:<?= $pstColor ?>1a;color:<?= $pstColor ?>;border:1px solid <?= $pstColor ?>44;">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    <?= htmlspecialchars($pstLabel) ?>
                                </span>
                            </div>
                            <div style="margin-bottom:12px;">
                                <div class="info-label" style="margin-bottom:4px;">PASX ID</div>
                                <code style="font-size:11px;background:#f1f5f9;padding:3px 7px;border-radius:4px;color:#475569;"><?= htmlspecialchars($pakd['pasx_id']) ?></code>
                            </div>
                            <?php if (!empty($pakd['pasx_requested_at'])): ?>
                            <div style="margin-bottom:12px;">
                                <div class="info-label" style="margin-bottom:4px;">Gửi yêu cầu lúc</div>
                                <div style="font-size:12px;color:var(--gray);"><?= date('d/m/Y H:i', strtotime($pakd['pasx_requested_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($pasx_has_data): ?>
                            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:4px;">
                                <div style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Chi phí nhận từ PASX</div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                    <span style="color:#64748b;">Human cost</span>
                                    <span style="font-weight:600;"><?= formatVNDFull($fin_human_cost) ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;">
                                    <span style="color:#64748b;">Overtime cost</span>
                                    <span style="font-weight:600;"><?= formatVNDFull($fin_overtime) ?></span>
                                </div>
                                <hr style="border:none;border-top:1px solid #bbf7d0;margin:8px 0;">
                                <div style="display:flex;justify-content:space-between;font-size:13px;">
                                    <span style="font-weight:700;color:#15803d;">Tổng chi phí SX</span>
                                    <span style="font-weight:800;color:#15803d;"><?= formatVNDFull($fin_prod_cost) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Chưa gửi yêu cầu Production Plan</p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($pasx_logs)): ?>
                            <div style="margin-top:14px;">
                                <div class="info-label" style="margin-bottom:8px;">Lịch sử PASX gần đây</div>
                                <?php foreach ($pasx_logs as $log):
                                    $logColor = $log['status'] === 'completed' || $log['status'] === 'approved' ? '#16a34a'
                                              : ($log['status'] === 'rejected' ? '#dc2626' : '#7c3aed');
                                ?>
                                <div class="pasx-log-item">
                                    <div class="pasx-log-dot" style="background:<?= $logColor ?>;"></div>
                                    <div class="pasx-log-body">
                                        <div class="pasx-log-event"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($log['event'] ?? ''))) ?></div>
                                        <div class="pasx-log-date"><?= !empty($log['received_at']) ? date('d/m/Y H:i', strtotime($log['received_at'])) : '—' ?></div>
                                    </div>
                                    <div style="font-size:11px;color:<?= $logColor ?>;font-weight:600;"><?= strtoupper($log['status'] ?? '') ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Approval info -->
                    <?php if ($st === 'approved' && !empty($pakd['approved_by_name'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green"><i class="fas fa-certificate"></i></div>
                            <h3>Thông tin duyệt</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Phê duyệt bởi</div>
                                    <div class="info-value"><?= htmlspecialchars($pakd['approved_by_name']) ?></div>
                                </div>
                                <?php if (!empty($pakd['approved_at'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Ngày duyệt</div>
                                    <div class="info-value"><?= date('d/m/Y H:i', strtotime($pakd['approved_at'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Key Dates -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon gray"><i class="fas fa-calendar-alt"></i></div>
                            <h3>Mốc thời gian</h3>
                        </div>
                        <div class="card-body" style="padding: 8px 20px;">
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-star" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Deal Won</div>
                                    <div class="stage-date">Cập nhật từ Odoo CRM</div>
                                </div>
                                <div class="stage-val"><?= !empty($pakd['assignment_date']) ? date('d/m/Y', strtotime($pakd['assignment_date'])) : '—' ?></div>
                            </div>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-calendar" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Dự kiến đóng</div>
                                    <div class="stage-date">Expected closing</div>
                                </div>
                                <div class="stage-val"><?= !empty($pakd['expected_closing']) ? date('d/m/Y', strtotime($pakd['expected_closing'])) : '—' ?></div>
                            </div>
                            <?php if (!empty($pakd['pasx_requested_at'])): ?>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-cog" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Gửi PASX</div>
                                    <div class="stage-date">Yêu cầu Production Plan</div>
                                </div>
                                <div class="stage-val"><?= date('d/m/Y', strtotime($pakd['pasx_requested_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($pakd['approved_at'])): ?>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-check" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Phê duyệt</div>
                                    <div class="stage-date">Bởi <?= htmlspecialchars($pakd['approved_by_name'] ?? '—') ?></div>
                                </div>
                                <div class="stage-val"><?= date('d/m/Y', strtotime($pakd['approved_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($pakd['created_at'])): ?>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f8fafc;color:#64748b;"><i class="fas fa-clock" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Tạo PAKD</div>
                                    <div class="stage-date">Trong hệ thống</div>
                                </div>
                                <div class="stage-val"><?= date('d/m/Y', strtotime($pakd['created_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </div><!-- /page-content -->
    </div>
</div>
</body>
</html>
