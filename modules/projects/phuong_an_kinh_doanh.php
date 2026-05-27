<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'user';

// ── Auto-create table if missing ──────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    odoo_opp_id     INT DEFAULT NULL UNIQUE COMMENT 'Odoo CRM opportunity ID',
    name            VARCHAR(500) NOT NULL,
    department      VARCHAR(100) DEFAULT NULL,
    am_name         VARCHAR(255) DEFAULT NULL,
    am_email        VARCHAR(255) DEFAULT NULL,
    am_user_id      INT DEFAULT NULL,
    project_type    VARCHAR(50) DEFAULT 'external',
    currency        VARCHAR(10) DEFAULT 'VND',
    status          ENUM('draft','pending','approved','rejected') DEFAULT 'draft',
    opportunity_name VARCHAR(500) DEFAULT NULL,
    company_name    VARCHAR(500) DEFAULT NULL,
    opp_value       DECIMAL(20,2) DEFAULT 0,
    opp_probability DECIMAL(5,2) DEFAULT 0,
    odoo_stage_name VARCHAR(255) DEFAULT NULL,
    contract_no     VARCHAR(255) DEFAULT NULL,
    sales_order_no  VARCHAR(255) DEFAULT NULL,
    timeline        VARCHAR(500) DEFAULT NULL,
    internal_notes  TEXT DEFAULT NULL,
    odoo_url        VARCHAR(500) DEFAULT NULL,
    synced_at       DATETIME DEFAULT NULL,
    created_by      INT DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Migrate: add new columns if the table already existed without them ────────
foreach ([
    'assignment_date' => 'DATETIME DEFAULT NULL',
    'expected_closing'=> 'DATE DEFAULT NULL',
    'odoo_stage_id'   => 'INT DEFAULT NULL',
    'division_names'  => 'VARCHAR(500) DEFAULT NULL',
] as $_col => $_def) {
    $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pakd' AND COLUMN_NAME='$_col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE pakd ADD COLUMN `$_col` $_def");
}
unset($_col, $_def, $r);

// ── Load data ─────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';

// Sorting
$sortAllowed = [
    'name'             => 'p.name',
    'company_name'     => 'p.company_name',
    'am_name'          => 'p.am_name',
    'department'       => 'p.department',
    'opp_value'        => 'p.opp_value',
    'opp_probability'  => 'p.opp_probability',
    'odoo_stage_name'  => 'p.odoo_stage_name',
    'status'           => 'p.status',
    'assignment_date'  => 'p.assignment_date',
    'expected_closing' => 'p.expected_closing',
    'created_at'       => 'p.created_at',
];
$sortCol = $_GET['sort'] ?? 'assignment_date';
$sortDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
if (!array_key_exists($sortCol, $sortAllowed)) { $sortCol = 'assignment_date'; $sortDir = 'DESC'; }
$orderBy = $sortAllowed[$sortCol] . ' ' . $sortDir;

$where  = ['1=1'];
$params = [];
$types  = '';

// ── Giới hạn AM chỉ xem PAKD của mình ──
$is_admin = ($role === 'admin');
if (!$is_admin) {
    $my_full_name = $_SESSION['full_name'] ?? '';
    $where[]  = "(p.am_user_id = ? OR p.am_name = ?)";
    $params[] = $user_id;
    $params[] = $my_full_name;
    $types   .= 'is';
}

if ($search !== '') {
    $where[]  = "(p.name LIKE ? OR p.company_name LIKE ? OR p.am_name LIKE ?)";
    $like     = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

if ($filter_status !== '') {
    $where[]  = "p.status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}

$whereStr = implode(' AND ', $where);

// Count total matching records
$countSql = "SELECT COUNT(*) as total FROM pakd p WHERE {$whereStr}";
$countStmt = $conn->prepare($countSql);
$totalRecords = 0;
if ($countStmt) {
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
}

// Pagination logic
$limit = 10;
$totalPages = ceil($totalRecords / $limit);
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $limit;

// Fetch data with limit & offset
$sql = "SELECT p.* FROM pakd p WHERE {$whereStr} ORDER BY {$orderBy} LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$pakdList = [];
if ($stmt) {
    $limitParams = $params;
    $limitParams[] = $offset;
    $limitParams[] = $limit;
    $limitTypes = $types . 'ii';
    
    if ($limitParams) $stmt->bind_param($limitTypes, ...$limitParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $pakdList[] = $row;
    $stmt->close();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = ['total' => 0, 'draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$statsRes = $conn->query("SELECT status, COUNT(*) as cnt FROM pakd GROUP BY status");
if ($statsRes) {
    while ($r = $statsRes->fetch_assoc()) {
        $stats[$r['status']] = (int)$r['cnt'];
        $stats['total'] += (int)$r['cnt'];
    }
}

// Last sync time
$lastSync = null;
$syncRes = $conn->query("SELECT MAX(synced_at) as last FROM pakd WHERE synced_at IS NOT NULL");
if ($syncRes && $row = $syncRes->fetch_assoc()) $lastSync = $row['last'];

// User avatar map (email → user info)
$userAvatarMap = [];
$uRes = $conn->query("SELECT email, full_name, avatar FROM users WHERE email IS NOT NULL AND email != ''");
if ($uRes) while ($u = $uRes->fetch_assoc()) $userAvatarMap[strtolower($u['email'])] = $u;

function sortTh($label, $col, $currentSort, $currentDir, $extraGetParams = []) {
    $isActive = $currentSort === $col;
    $nextDir  = ($isActive && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $cls      = 'sortable' . ($isActive ? ' sort-' . strtolower($currentDir) : '');
    $qs       = array_merge($extraGetParams, ['sort' => $col, 'dir' => $nextDir]);
    $url      = '/projects/phuong-an-kinh-doanh?' . http_build_query($qs);
    $icon     = $isActive
        ? ($currentDir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down')
        : 'fa-sort';
    echo "<th class=\"{$cls}\" onclick=\"window.location='{$url}'\">{$label}<i class=\"fas {$icon} sort-icon\"></i></th>";
}

$viMonths = ['','Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];

function avatarColor($name) {
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

function renderAM($amName, $amEmail, $userAvatarMap) {
    $user = !empty($amEmail) ? ($userAvatarMap[strtolower($amEmail)] ?? null) : null;
    $name = htmlspecialchars($amName ?: '—');
    $c    = avatarColor($amName ?? '');
    $bgStyle = "background:{$c['bg']};color:{$c['fg']}";
    if ($user && !empty($user['avatar'])) {
        $avatar   = '<img src="' . htmlspecialchars($user['avatar']) . '" class="am-avatar" alt="' . $name . '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">';
        $initials = '<div class="am-initials" style="display:none;' . $bgStyle . '">' . htmlspecialchars(substr($amName ?? '', 0, 2)) . '</div>';
    } else {
        $avatar   = '';
        $parts    = array_filter(explode(' ', $amName ?? ''));
        $ini      = strtoupper(($parts[0][0] ?? '') . (count($parts) > 1 ? end($parts)[0] : ''));
        $initials = '<div class="am-initials" style="' . $bgStyle . '">' . htmlspecialchars($ini ?: '?') . '</div>';
    }
    return '<div class="am-cell">'
        . $avatar . $initials
        . '<div class="am-info"><div class="am-name">' . $name . '</div></div>'
        . '</div>';
}

function renderStars($pct) {
    $filled = max(0, min(5, (int)round($pct / 20)));
    $html = '<span class="star-rating" title="' . (int)$pct . '%">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $filled
            ? '<i class="fas fa-star"></i>'
            : '<i class="fas fa-star off"></i>';
    }
    return $html . '</span>';
}

function statusLabel($s) {
    return ['draft' => 'Nháp', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'][$s] ?? $s;
}
function formatVND($n) {
    if ($n >= 1e9)  return number_format($n/1e9, 1).'B';
    if ($n >= 1e6)  return number_format($n/1e6, 1).'M';
    if ($n >= 1e3)  return number_format($n/1e3, 0).'K';
    return number_format($n, 0);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Plans - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --bg: #f8fafc;
            --card: #ffffff;
            --slate: #1e293b;
            --gray: #64748b;
            --lgray: #94a3b8;
            --border: #e2e8f0;
            --r-xl: 18px; --r-lg: 12px; --r-md: 8px;
            --sh-sm: 0 1px 3px rgba(0,0,0,.06);
            --sh-md: 0 4px 16px rgba(0,0,0,.08);
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--slate); margin: 0; }
        .main-content { flex: 1; padding: 28px; min-height: 100vh; }

        /* Page header */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
        }
        .page-header-left { display: flex; align-items: center; gap: 14px; }
        .page-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--r-lg); display: flex; align-items: center;
            justify-content: center; color: white; font-size: 20px;
            box-shadow: 0 4px 12px rgba(99,102,241,.3);
        }
        .page-title h1 { font-size: 22px; font-weight: 700; margin: 0 0 3px; }
        .page-title p  { font-size: 13px; color: var(--gray); margin: 0; }
        .header-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
        .header-buttons { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: var(--r-md);
            font-size: 13px; font-weight: 600; cursor: pointer;
            border: none; font-family: inherit; text-decoration: none;
            transition: all .2s; white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; box-shadow: 0 3px 10px rgba(99,102,241,.25);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(99,102,241,.35); }
        .btn-outline { background: white; color: var(--gray); border: 1px solid var(--border); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: rgba(99,102,241,.04); }
        .btn-sync {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white; box-shadow: 0 3px 10px rgba(14,165,233,.25);
        }
        /* Compact Stats */
        .compact-stats {
            display: flex; gap: 24px; margin-bottom: 20px; align-items: center;
        }
        .stat-item { font-size: 13px; font-weight: 500; }
        .stat-item strong { font-size: 15px; margin-left: 4px; }
        .btn-sync:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(14,165,233,.35); }
        .btn-sync.loading { opacity: .7; pointer-events: none; }

        /* Stats */
        .stats-row {
            display: grid; grid-template-columns: repeat(5, 1fr);
            gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card); border-radius: var(--r-lg);
            padding: 18px 20px; border: 1px solid var(--border);
            box-shadow: var(--sh-sm); display: flex; align-items: center; gap: 14px;
            transition: all .2s; cursor: default;
        }
        .stat-card:hover { box-shadow: var(--sh-md); transform: translateY(-2px); }
        .stat-card.clickable { cursor: pointer; }
        .stat-card.clickable:hover { border-color: rgba(99,102,241,.3); }
        .stat-icon { width: 40px; height: 40px; border-radius: var(--r-md); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .stat-icon.purple { background: rgba(99,102,241,.1); color: var(--primary); }
        .stat-icon.gray   { background: rgba(100,116,139,.1); color: #64748b; }
        .stat-icon.amber  { background: rgba(217,119,6,.1);   color: var(--warning); }
        .stat-icon.green  { background: rgba(22,163,74,.1);   color: var(--success); }
        .stat-icon.red    { background: rgba(220,38,38,.1);   color: var(--danger); }
        .stat-val { font-size: 22px; font-weight: 700; color: var(--slate); line-height: 1; }
        .stat-lbl { font-size: 12px; color: var(--gray); margin-top: 3px; }

        /* Toolbar */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; }
        .search-wrap { position: relative; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--lgray); font-size: 13px; }
        .search-wrap input {
            padding: 9px 14px 9px 34px; border: 1px solid var(--border);
            border-radius: var(--r-md); font-size: 13px; font-family: inherit;
            color: var(--slate); background: white; outline: none;
            width: 240px; transition: all .2s;
        }
        .search-wrap input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99,102,241,.08); }
        .filter-select {
            padding: 9px 12px; border: 1px solid var(--border); border-radius: var(--r-md);
            font-size: 13px; font-family: inherit; color: var(--slate);
            background: white; outline: none; cursor: pointer;
        }
        .filter-select:focus { border-color: #818cf8; }

        /* Table card */
        .table-card {
            background: var(--card); border-radius: var(--r-md);
            border: 1px solid var(--border); box-shadow: var(--sh-sm); overflow: hidden;
            display: flex; flex-direction: column;
        }
        .table-wrap { overflow-x: auto; overflow-y: auto; max-height: calc(100vh - 300px); }
        .table-wrap thead th { position: sticky; top: 0; z-index: 10; box-shadow: 0 1px 0 var(--border); }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        thead th {
            padding: 11px 16px; text-align: left;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: var(--lgray);
            background: #f8fafc;
            white-space: nowrap;
        }
        thead th.sortable {
            cursor: pointer; user-select: none;
        }
        thead th.sortable:hover { color: var(--primary); background: #f1f5f9; }
        thead th.sort-asc,
        thead th.sort-desc { color: var(--primary); }
        .sort-icon { margin-left: 4px; font-size: 10px; opacity: .5; }
        thead th.sort-asc  .sort-icon,
        thead th.sort-desc .sort-icon { opacity: 1; }

        /* Month group row */
        .month-group-row td {
            background: #f1f5f9;
            padding: 6px 16px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em; color: var(--primary);
            border-bottom: 1px solid var(--border);
            cursor: default;
        }
        .month-group-row:hover { background: #f1f5f9 !important; cursor: default; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; cursor: pointer; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:nth-child(odd)  { background: #ffffff; }
        tbody tr:nth-child(even) { background: #eef2f7; }
        tbody tr:hover { background: rgba(99,102,241,.06) !important; }
        tbody td { padding: 12px 16px; font-size: 13px; color: var(--slate); vertical-align: middle; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;
        }
        .status-badge.draft    { background: #f1f5f9; color: #64748b; }
        .status-badge.pending  { background: #fef9c3; color: #d97706; }
        .status-badge.approved { background: #dcfce7; color: #16a34a; }
        .status-badge.rejected { background: #fee2e2; color: #dc2626; }

        .opp-value { font-weight: 600; color: #2563eb; }
        .star-rating { display: inline-flex; gap: 1px; line-height: 1; }
        .star-rating .fa-star     { font-size: 13px; color: #f59e0b; }
        .star-rating .fa-star.off { color: #d1d5db; }

        .am-cell { display: flex; align-items: center; gap: 8px; }
        .am-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid var(--border); }
        .am-initials { width: 30px; height: 30px; border-radius: 50%; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .am-info { min-width: 0; }
        .am-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
        .odoo-link {
            font-size: 11px; color: var(--primary); text-decoration: none;
            display: inline-flex; align-items: center; gap: 3px; font-weight: 500;
        }
        .odoo-link:hover { text-decoration: underline; }

        .action-btn {
            background: none; border: none; cursor: pointer; padding: 5px 8px;
            border-radius: 6px; color: var(--lgray); font-size: 13px; transition: all .15s;
        }
        .action-btn:hover { background: #f1f5f9; color: var(--primary); }
        .action-btn.del:hover { color: var(--danger); }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 24px; color: var(--gray); }
        .empty-state .empty-icon {
            width: 72px; height: 72px; background: rgba(99,102,241,.08);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px; font-size: 32px; color: var(--primary); opacity: .6;
        }
        .empty-state h3 { font-size: 17px; font-weight: 600; margin: 0 0 8px; color: var(--slate); }
        .empty-state p  { font-size: 13px; margin: 0; }

        /* Sync info bar */
        .sync-info-bar {
            display: flex; align-items: center; justify-content: space-between; padding: 0 16px; min-height: 44px;
            background: rgba(14,165,233,.05); border-bottom: 1px solid rgba(14,165,233,.15);
            font-size: 12px; color: #0284c7; flex-wrap: wrap; gap: 10px;
        }
        .sync-info-bar i { color: #0ea5e9; }

        /* Pagination */
        .pagination { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-top: 1px solid var(--border); background: #f8fafc; border-bottom-left-radius: var(--r-md); border-bottom-right-radius: var(--r-md); }
        .page-info { font-size: 13px; color: var(--gray); }
        .page-links { display: flex; gap: 6px; }
        .page-btn { display: flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 10px; border-radius: 6px; border: 1px solid var(--border); background: white; color: var(--slate); font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all .15s; }
        .page-btn:hover:not(.disabled) { border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-btn.disabled { opacity: 0.5; cursor: not-allowed; background: #f1f5f9; }

        /* Toast */
        .toast {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            padding: 12px 20px; border-radius: 10px; font-size: 13px;
            font-weight: 600; color: white; display: flex; align-items: center; gap: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            animation: toastIn .3s ease; font-family: Inter, sans-serif;
        }
        .toast.success { background: #16a34a; }
        .toast.error   { background: #dc2626; }
        .toast.info    { background: #0284c7; }
        @keyframes toastIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }

        /* Sync progress overlay */
        .sync-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,.5);
            display: flex; align-items: center; justify-content: center; z-index: 9000;
            backdrop-filter: blur(3px);
        }
        .sync-box {
            background: white; border-radius: 20px; padding: 36px 40px;
            text-align: center; box-shadow: 0 24px 64px rgba(0,0,0,.2);
            min-width: 280px;
        }
        .sync-spinner {
            width: 52px; height: 52px; border: 4px solid #e2e8f0;
            border-top-color: #0ea5e9; border-radius: 50%;
            animation: spin 0.8s linear infinite; margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .sync-box h3 { font-size: 16px; font-weight: 700; margin: 0 0 6px; color: var(--slate); }
        .sync-box p  { font-size: 13px; color: var(--gray); margin: 0; }

        /* Modal */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(15,23,42,.4);
            display: flex; align-items: center; justify-content: center; z-index: 9999;
            backdrop-filter: blur(3px); animation: fadeIn .2s ease;
        }
        .modal-box {
            background: white; border-radius: var(--r-xl); width: 100%; max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,.15); display: flex; flex-direction: column;
            max-height: 90vh; overflow: hidden; animation: slideUp .3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-header {
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-title { font-size: 16px; font-weight: 700; color: var(--slate); display: flex; align-items: center; gap: 8px; }
        .modal-close {
            background: none; border: none; font-size: 24px; color: var(--gray); cursor: pointer;
            line-height: 1; padding: 0; transition: color .2s;
        }
        .modal-close:hover { color: var(--danger); }
        .modal-desc { padding: 16px 24px 0; font-size: 13px; color: var(--gray); line-height: 1.5; }
        .stage-search-wrap { padding: 16px 24px; position: relative; }
        .stage-search-wrap i { position: absolute; left: 36px; top: 50%; transform: translateY(-50%); color: var(--lgray); font-size: 13px; }
        .stage-search-wrap input {
            width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--border);
            border-radius: var(--r-md); font-size: 13px; font-family: inherit; color: var(--slate); outline: none;
        }
        .stage-search-wrap input:focus { border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        .stage-bulk-actions {
            padding: 10px 24px; background: #f8fafc; border-bottom: 1px solid var(--border); border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
        }
        .stage-bulk-btn {
            background: none; border: none; font-size: 12px; font-weight: 600; color: var(--primary);
            cursor: pointer; padding: 0; display: inline-flex; align-items: center; gap: 4px;
        }
        .stage-bulk-btn:hover { text-decoration: underline; }
        .stage-count-label { margin-left: auto; font-size: 12px; color: var(--gray); font-weight: 600; }
        .stage-list {
            flex: 1; overflow-y: auto; padding: 0; margin: 0; list-style: none;
            min-height: 200px; max-height: 40vh; background: #fafbfc;
        }
        .stage-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 24px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background .15s;
        }
        .stage-item:hover { background: white; }
        .stage-item label {
            display: flex; align-items: center; gap: 12px; cursor: pointer; flex: 1; margin: 0;
            font-size: 14px; font-weight: 500; color: var(--slate);
        }
        .stage-item input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary); }
        .stage-item .stage-prob { font-size: 11px; font-weight: 700; color: var(--success); background: #dcfce7; padding: 2px 6px; border-radius: 4px; }
        .stage-item .stage-won { font-size: 11px; font-weight: 700; color: var(--primary); background: #e0e7ff; padding: 2px 6px; border-radius: 4px; margin-left: 6px; }
        .stage-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--gray); font-size: 13px; gap: 12px; }
        .stage-spinner { width: 30px; height: 30px; border: 3px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; gap: 10px; background: #f8fafc; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(3,1fr); } }
        @media (max-width: 768px)  {
            .main-content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-wrap input { width: 100%; }
        }
    </style>
    <script>
    /* Collapse sidebar by default on this page; restore previous state on leave */
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
        <?php $page_title = 'Phương án kinh doanh'; include __DIR__ . '/../includes/topbar.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <div class="page-icon"><i class="fas fa-briefcase"></i></div>
                <div class="page-title">
                    <h1>Business Plans</h1>
                    <p>Quản lý và đồng bộ các phương án kinh doanh từ Odoo CRM · Opportunity ở giai đoạn Proposal</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="header-buttons">
                    <?php if ($role === 'admin' || $role === 'manager'): ?>
                    <button class="btn btn-sync" id="syncBtn" onclick="syncFromOdoo()">
                        <i class="fas fa-sync-alt"></i> Sync Odoo
                    </button>
                    <button class="btn btn-outline" id="settingsBtn" onclick="openSettings()" title="Cài đặt stage sync">
                        <i class="fas fa-sliders-h"></i> Cài đặt Stage
                    </button>
                    <?php endif; ?>
                    <a href="/projects/pakd/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tạo thủ công
                    </a>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchBox" placeholder="Tìm theo tên, khách hàng, AM..."
                           value="<?= htmlspecialchars($search) ?>"
                           onkeydown="if(event.key==='Enter')applyFilter()">
                </div>
                <select class="filter-select" id="statusFilter" onchange="applyFilter()">
                    <option value="">Tất cả trạng thái</option>
                    <option value="draft"    <?= $filter_status==='draft'    ?'selected':'' ?>>Nháp</option>
                    <option value="pending"  <?= $filter_status==='pending'  ?'selected':'' ?>>Chờ duyệt</option>
                    <option value="approved" <?= $filter_status==='approved' ?'selected':'' ?>>Đã duyệt</option>
                    <option value="rejected" <?= $filter_status==='rejected' ?'selected':'' ?>>Từ chối</option>
                </select>
                <button class="btn btn-outline" onclick="clearFilter()">
                    <i class="fas fa-times"></i> Xoá lọc
                </button>
            </div>
            <div class="toolbar-right" style="font-size:12px;color:var(--lgray);">
                <?php if ($lastSync): ?>
                <i class="fas fa-cloud-download-alt" style="color:#0ea5e9;"></i>
                Sync lần cuối: <strong><?= date('d/m/Y H:i', strtotime($lastSync)) ?></strong>
                <?php else: ?>
                <i class="fas fa-info-circle"></i> Chưa sync với Odoo
                <?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="sync-info-bar">
                <div style="display:flex; align-items:center; gap:8px;">
                    <?php if ($lastSync): ?>
                    <i class="fas fa-circle-check"></i>
                    <span>Dữ liệu được đồng bộ từ Odoo CRM. Nhấn <strong>Sync Odoo</strong> để cập nhật mới nhất.</span>
                    <?php else: ?>
                    <i class="fas fa-info-circle"></i>
                    <span>Nhấn <strong>Sync Odoo</strong> để đồng bộ dữ liệu.</span>
                    <?php endif; ?>
                </div>
                
                <!-- Stats -->
                <div class="compact-stats" style="margin-bottom: 0; gap: 10px; font-size: 13px; color: var(--slate);">
                    <div title="Tổng phương án">Tổng: <strong style="color:var(--primary); font-size:14px; margin-left:4px;"><?= $stats['total'] ?></strong></div>
                    <div style="color: #cbd5e1;">|</div>
                    <div title="Nháp">Nháp: <strong style="color:var(--slate); font-size:14px; margin-left:4px;"><?= $stats['draft'] ?></strong></div>
                    <div style="color: #cbd5e1;">|</div>
                    <div title="Chờ duyệt">Chờ duyệt: <strong style="color:var(--warning); font-size:14px; margin-left:4px;"><?= $stats['pending'] ?></strong></div>
                    <div style="color: #cbd5e1;">|</div>
                    <div title="Đã duyệt">Đã duyệt: <strong style="color:var(--success); font-size:14px; margin-left:4px;"><?= $stats['approved'] ?></strong></div>
                    <div style="color: #cbd5e1;">|</div>
                    <div title="Từ chối">Từ chối: <strong style="color:var(--danger); font-size:14px; margin-left:4px;"><?= $stats['rejected'] ?></strong></div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php
                            $qp = array_filter(['search'=>$search,'status'=>$filter_status,'page'=>($page>1?$page:null)]);
                            ?>
                            <th>#</th>
                            <?php sortTh('Tên phương án', 'name',            $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Khách hàng',    'company_name',    $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('AM',             'am_name',         $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Bộ phận',        'department',      $sortCol, $sortDir, $qp) ?>
                            <th>Lead/Opp Divisions</th>
                            <?php sortTh('Giá trị',        'opp_value',       $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Xác suất',       'opp_probability', $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Stage Odoo',     'odoo_stage_name', $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Trạng thái',     'status',          $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Ngày assign',    'assignment_date', $sortCol, $sortDir, $qp) ?>
                            <?php sortTh('Dự kiến đóng',   'expected_closing',$sortCol, $sortDir, $qp) ?>
                        </tr>
                    </thead>
                    <tbody id="pakdTableBody">
                    <?php if (empty($pakdList)): ?>
                        <tr><td colspan="11">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                                <h3>Chưa có phương án nào</h3>
                                <p>Nhấn <strong>Sync Odoo</strong> để tự động tạo từ Odoo CRM,<br>hoặc <strong>Tạo thủ công</strong> để tạo mới.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php
                        $currentMonthKey = null;
                        $rowNum = 0;
                        foreach ($pakdList as $i => $p):
                            // Month grouping (only when sorting by assignment_date)
                            if ($sortCol === 'assignment_date') {
                                $monthKey = !empty($p['assignment_date'])
                                    ? date('Y-m', strtotime($p['assignment_date']))
                                    : 'no-date';
                                if ($monthKey !== $currentMonthKey) {
                                    $currentMonthKey = $monthKey;
                                    $rowNum = 0;
                                    if ($monthKey === 'no-date') {
                                        $groupLabel = 'Chưa có ngày assign';
                                    } else {
                                        $m = (int)date('m', strtotime($p['assignment_date']));
                                        $y = date('Y', strtotime($p['assignment_date']));
                                        $groupLabel = $viMonths[$m] . ' ' . $y;
                                    }
                                    echo "<tr class=\"month-group-row\"><td colspan=\"12\"><i class=\"fas fa-calendar-alt\" style=\"margin-right:6px;\"></i>{$groupLabel}</td></tr>";
                                }
                            }
                            $rowNum++;
                        ?>
                        <tr class="pakd-row" data-href="/projects/pakd/edit?id=<?= $p['id'] ?>">
                            <td style="color:var(--lgray);font-size:12px;"><?= $rowNum ?></td>
                            <td style="max-width:280px;">
                                <div style="font-weight:600;color:var(--slate);margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['name']) ?>">
                                    <?= htmlspecialchars($p['name']) ?>
                                </div>
                                <?php if ($p['odoo_url']): ?>
                                <a href="<?= htmlspecialchars($p['odoo_url']) ?>" target="_blank" class="odoo-link">
                                    <i class="fas fa-external-link-alt"></i> Odoo #<?= $p['odoo_opp_id'] ?>
                                </a>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--slate);"><?= !empty($p['company_name']) ? htmlspecialchars($p['company_name']) : '—' ?></td>
                            <td><?= renderAM($p['am_name'], $p['am_email'], $userAvatarMap) ?></td>
                            <td style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($p['department'] ?: '—') ?></td>
                            <td style="font-size:12px;color:var(--slate);"><?= !empty($p['division_names']) ? htmlspecialchars($p['division_names']) : '—' ?></td>
                            <td class="opp-value"><?= formatVND($p['opp_value']) ?> <?= htmlspecialchars($p['currency']) ?></td>
                            <td><?= renderStars($p['opp_probability']) ?></td>
                            <td style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($p['odoo_stage_name'] ?: '—') ?></td>
                            <td>
                                <?php
                                $ps = $p['pasx_status'] ?? '';
                                $psMap = [
                                    'created'     => ['BP sản xuất đang lên PA', '#7c3aed', '#ede9fe'],
                                    'processing'  => ['PASX âm, Cần Review',     '#b45309', '#fef3c7'],
                                    'pending'     => ['PASX chờ duyệt',          '#d97706', '#fef3c7'],
                                    'pending_ceo' => ['Chờ CEO duyệt',           '#d97706', '#fef3c7'],
                                    'approved'    => ['PASX approved',           '#16a34a', '#dcfce7'],
                                    'rejected'    => ['PASX rejected',           '#b91c1c', '#fee2e2'],
                                    'completed'   => ['Hoàn thành',             '#0284c7', '#e0f2fe'],
                                    'cancelled'   => ['Đã huỷ',                 '#64748b', '#f1f5f9'],
                                ];
                                $psInfo = $ps ? ($psMap[$ps] ?? [strtoupper($ps), '#64748b', '#f1f5f9']) : null;
                                ?>
                                <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                    <span class="status-badge <?= $p['status'] ?>">
                                        <?= statusLabel($p['status']) ?>
                                    </span>
                                    <?php if ($psInfo): ?>
                                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;background:<?= $psInfo[2] ?>;color:<?= $psInfo[1] ?>;white-space:nowrap;line-height:1.5;">
                                        <span style="width:5px;height:5px;border-radius:50%;background:<?= $psInfo[1] ?>;flex-shrink:0;"></span>
                                        <?= htmlspecialchars($psInfo[0]) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--lgray);"><?= !empty($p['assignment_date']) ? date('d/m/Y', strtotime($p['assignment_date'])) : '—' ?></td>
                            <td style="font-size:12px;color:var(--lgray);"><?= !empty($p['expected_closing']) ? date('d/m/Y', strtotime($p['expected_closing'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalRecords > 0): ?>
            <div class="pagination">
                <div class="page-info">
                    Hiển thị <strong><?= min($offset + 1, $totalRecords) ?></strong> đến <strong><?= min($offset + $limit, $totalRecords) ?></strong> trong tổng số <strong><?= $totalRecords ?></strong> phương án
                </div>
                <div class="page-links">
                    <?php 
                        $qs = $_GET; 
                        unset($qs['page']); 
                        $baseUrl = '/projects/phuong-an-kinh-doanh?' . http_build_query($qs);
                        $baseUrl .= (empty($qs) ? '' : '&') . 'page=';
                    ?>
                    
                    <a href="<?= $page > 1 ? $baseUrl . ($page - 1) : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <?php 
                        if ($totalPages > 1) {
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1) {
                                echo '<a href="' . $baseUrl . '1" class="page-btn">1</a>';
                                if ($startPage > 2) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default;">...</span>';
                            }
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $active = $i === $page ? 'active' : '';
                                echo '<a href="' . $baseUrl . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
                            }
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default;">...</span>';
                                echo '<a href="' . $baseUrl . $totalPages . '" class="page-btn">' . $totalPages . '</a>';
                            }
                        } else {
                            echo '<a href="#" class="page-btn active" onclick="return false;">1</a>';
                        }
                    ?>
                    
                    <a href="<?= $page < $totalPages ? $baseUrl . ($page + 1) : '#' ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" <?= $page >= $totalPages ? 'onclick="return false;"' : '' ?>>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Sync overlay -->
<div class="sync-overlay" id="syncOverlay" style="display:none;">
    <div class="sync-box">
        <div class="sync-spinner"></div>
        <h3>Đang đồng bộ từ Odoo...</h3>
        <p id="syncProgress">Đang kết nối tới Odoo CRM</p>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal-backdrop" id="settingsModal" style="display:none;" onclick="closeSettingsOnBackdrop(event)">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-sliders-h" style="color:var(--primary);"></i>
                Cài đặt Stage Sync
            </div>
            <button class="modal-close" onclick="closeSettings()">&times;</button>
        </div>
        <div class="modal-desc">
            <label style="display:block; font-size:13px; font-weight:600; color:var(--slate); margin-bottom:6px;">
                Chọn Stage để đồng bộ (Sync) <span style="color:var(--danger);">*</span>
            </label>
            Chọn các <strong>stage</strong> từ Odoo CRM sẽ được tự động tạo PAKD khi Sync.
            Chỉ Opportunity ở các stage được chọn mới được đồng bộ.
        </div>

        <!-- Search stages -->
        <div class="stage-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="stageSearch" placeholder="Tìm stage..." oninput="filterStageList()">
        </div>

        <!-- Stage list -->
        <div class="stage-list" id="stageList">
            <div class="stage-loading">
                <div class="stage-spinner"></div>
                <span>Đang tải stages từ Odoo...</span>
            </div>
        </div>

        <!-- Select all / none -->
        <div class="stage-bulk-actions">
            <button class="stage-bulk-btn" onclick="selectAllStages()"><i class="fas fa-check-square"></i> Chọn tất cả</button>
            <button class="stage-bulk-btn" onclick="selectNoStages()"><i class="fas fa-square"></i> Bỏ chọn tất cả</button>
            <span class="stage-count-label" id="stageCountLabel"></span>
        </div>

        <!-- Won stage selection -->
        <div style="padding: 16px 24px; background: #fff; border-top: 1px solid var(--border);">
            <label style="display:block; font-size:13px; font-weight:600; color:var(--slate); margin-bottom:8px;">
                Chọn Stage đánh dấu là "Deal Won" <span style="color:var(--danger);">*</span>
            </label>
            <select id="wonStageSelect" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:var(--r-md); font-size:13px; outline:none; background:white;">
                <option value="">-- Chọn Stage Won --</option>
            </select>
        </div>

        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeSettings()">Huỷ</button>
            <button class="btn btn-primary" id="saveSettingsBtn" onclick="saveSettings()">
                <i class="fas fa-save"></i> Lưu cài đặt
            </button>
        </div>
    </div>
</div>

<script>
let allStages = [];
let savedStageIds = new Set();
let savedWonStageId = null;

function openSettings() {
    document.getElementById('settingsModal').style.display = 'flex';
    document.getElementById('stageSearch').value = '';
    loadStages();
}

function closeSettings() {
    document.getElementById('settingsModal').style.display = 'none';
}

function closeSettingsOnBackdrop(e) {
    if (e.target === document.getElementById('settingsModal')) {
        closeSettings();
    }
}

function loadStages() {
    const list = document.getElementById('stageList');
    list.innerHTML = `
        <div class="stage-loading">
            <div class="stage-spinner"></div>
            <span>Đang tải stages từ Odoo...</span>
        </div>
    `;
    
    fetch('/projects/pakd/settings?action=get_stages')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allStages = data.stages || [];
                savedStageIds = new Set(data.saved_ids || []);
                savedWonStageId = data.saved_won_stage_id;
                renderStages();
            } else {
                list.innerHTML = `<div style="padding:24px;text-align:center;color:#dc2626;">Lỗi tải dữ liệu: ${data.error}</div>`;
            }
        })
        .catch(err => {
            list.innerHTML = `<div style="padding:24px;text-align:center;color:#dc2626;">Lỗi kết nối: ${err.message}</div>`;
        });
}

function renderStages() {
    const list = document.getElementById('stageList');
    const q = document.getElementById('stageSearch').value.toLowerCase();
    const wonSelect = document.getElementById('wonStageSelect');
    
    list.innerHTML = '';
    
    // Clear dropdown except first option
    while (wonSelect.options.length > 1) {
        wonSelect.remove(1);
    }
    
    let visibleCount = 0;
    
    allStages.forEach(stage => {
        const name = stage.name || '';
        
        // Populate dropdown
        const opt = document.createElement('option');
        opt.value = stage.id;
        opt.textContent = name;
        if (savedWonStageId && savedWonStageId == stage.id) {
            opt.selected = true;
        }
        wonSelect.appendChild(opt);

        if (q && !name.toLowerCase().includes(q)) return;
        
        visibleCount++;
        const isChecked = savedStageIds.has(stage.id);
        const prob = stage.probability ? `<span class="stage-prob">${stage.probability}%</span>` : '';
        const won = stage.is_won ? `<span class="stage-won">Won</span>` : '';
        
        const div = document.createElement('div');
        div.className = 'stage-item';
        div.innerHTML = `
            <label>
                <input type="checkbox" class="stage-cb" value="${stage.id}" data-name="${name.replace(/"/g, '&quot;')}" ${isChecked ? 'checked' : ''} onchange="updateStageCount()">
                <span>${name}</span>
            </label>
            <div>${prob}${won}</div>
        `;
        list.appendChild(div);
    });
    
    if (visibleCount === 0) {
        list.innerHTML = `<div style="padding:24px;text-align:center;color:var(--gray);">Không tìm thấy stage nào.</div>`;
    }
    updateStageCount();
}

function filterStageList() {
    renderStages();
}

function selectAllStages() {
    document.querySelectorAll('.stage-cb').forEach(cb => cb.checked = true);
    updateStageCount();
}

function selectNoStages() {
    document.querySelectorAll('.stage-cb').forEach(cb => cb.checked = false);
    updateStageCount();
}

function updateStageCount() {
    const total = document.querySelectorAll('.stage-cb').length;
    const checked = document.querySelectorAll('.stage-cb:checked').length;
    document.getElementById('stageCountLabel').textContent = `Đã chọn: ${checked} / ${total}`;
}

function saveSettings() {
    const btn = document.getElementById('saveSettingsBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
    btn.disabled = true;
    
    const selectedIds = [];
    const selectedNames = [];
    document.querySelectorAll('.stage-cb:checked').forEach(cb => {
        selectedIds.push(cb.value);
        selectedNames.push(cb.getAttribute('data-name'));
    });
    
    const formData = new URLSearchParams();
    formData.append('action', 'save_stages');
    formData.append('stage_ids', JSON.stringify(selectedIds));
    formData.append('stage_names', JSON.stringify(selectedNames));
    
    const wonStageId = document.getElementById('wonStageSelect').value;
    if (wonStageId) {
        formData.append('won_stage_id', wonStageId);
    }
    
    fetch('/projects/pakd/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = '<i class="fas fa-save"></i> Lưu cài đặt';
        btn.disabled = false;
        
        if (data.success) {
            closeSettings();
            showToast(data.message, 'success');
        } else {
            showToast(data.error || 'Lỗi khi lưu.', 'error');
        }
    })
    .catch(err => {
        btn.innerHTML = '<i class="fas fa-save"></i> Lưu cài đặt';
        btn.disabled = false;
        showToast('Kết nối thất bại.', 'error');
    });
}

function applyFilter() {
    const s = document.getElementById('searchBox').value;
    const st = document.getElementById('statusFilter').value;
    const params = new URLSearchParams();
    if (s)  params.set('search', s);
    if (st) params.set('status', st);
    window.location.href = '/projects/phuong-an-kinh-doanh' + (params.toString() ? '?' + params.toString() : '');
}

function clearFilter() {
    window.location.href = '/projects/phuong-an-kinh-doanh';
}

function filterByStatus(st) {
    document.getElementById('statusFilter').value = st;
    applyFilter();
}

function syncFromOdoo() {
    const btn = document.getElementById('syncBtn');
    const overlay = document.getElementById('syncOverlay');
    const progress = document.getElementById('syncProgress');

    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang sync...';
    overlay.style.display = 'flex';
    progress.textContent = 'Đang kết nối tới Odoo CRM...';

    fetch('/projects/pakd/sync-odoo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=sync'
    })
    .then(r => r.json())
    .then(data => {
        overlay.style.display = 'none';
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Odoo';

        if (data.success) {
            showToast(
                `✓ ${data.message} (${data.total_fetched} opp tổng, ${data.proposal_count} ở Proposal)`,
                'success'
            );
            setTimeout(() => window.location.reload(), 1800);
        } else {
            showToast('Lỗi sync: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        overlay.style.display = 'none';
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Odoo';
        showToast('Kết nối thất bại: ' + err.message, 'error');
    });
}

function deletePakd(id, name) {
    if (!confirm(`Xoá phương án:\n"${name}"?\n\nHành động này không thể hoàn tác.`)) return;
    fetch('/projects/pakd/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Đã xoá phương án!', 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showToast('Lỗi: ' + d.error, 'error');
        }
    })
    .catch(() => showToast('Lỗi kết nối!', 'error'));
}

document.querySelectorAll('tr.pakd-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
        if (e.target.closest('a, button')) return;
        window.location.href = this.dataset.href;
    });
});

function showToast(msg, type = 'success') {
    const old = document.querySelector('.toast');
    if (old) old.remove();
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle'}"></i> <span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4500);
}
</script>
</body>
</html>
