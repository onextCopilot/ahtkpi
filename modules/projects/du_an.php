<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'user';

// ── Migrate: ensure new columns exist ────────────────────────────────────────
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

// ── Load won stage from settings ─────────────────────────────────────────────
$wonStageId = null;
$wonStageName = null;
$wonRes = $conn->query("SELECT setting_key, setting_value FROM pakd_settings WHERE setting_key IN ('sync_won_stage_id','sync_stage_names')");
$wonSettings = [];
if ($wonRes) while ($r = $wonRes->fetch_assoc()) $wonSettings[$r['setting_key']] = $r['setting_value'];

if (!empty($wonSettings['sync_won_stage_id'])) {
    $wonStageId = (int)$wonSettings['sync_won_stage_id'];
}

// ── Load data ─────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');

$sortAllowed = [
    'name'             => 'p.name',
    'company_name'     => 'p.company_name',
    'am_name'          => 'p.am_name',
    'department'       => 'p.department',
    'opp_value'        => 'p.opp_value',
    'opp_probability'  => 'p.opp_probability',
    'odoo_stage_name'  => 'p.odoo_stage_name',
    'assignment_date'  => 'p.assignment_date',
    'expected_closing' => 'p.expected_closing',
    'created_at'       => 'p.created_at',
];
$sortCol = $_GET['sort'] ?? 'assignment_date';
$sortDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
if (!array_key_exists($sortCol, $sortAllowed)) { $sortCol = 'assignment_date'; $sortDir = 'DESC'; }
$orderBy = $sortAllowed[$sortCol] . ' ' . $sortDir;

// Filter: won stage from settings (odoo_stage_id), fallback to status='approved'
if ($wonStageId) {
    $where  = ["p.odoo_stage_id = {$wonStageId}"];
} else {
    $where  = ["p.status = 'approved'"];
}
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(p.name LIKE ? OR p.company_name LIKE ? OR p.am_name LIKE ?)";
    $like     = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM pakd p WHERE {$whereStr}");
$totalRecords = 0;
if ($countStmt) {
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalRecords = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
}

// Pagination
$limit      = 20;
$totalPages = max(1, ceil($totalRecords / $limit));
$page       = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset     = ($page - 1) * $limit;

// Fetch
$stmt = $conn->prepare("SELECT p.* FROM pakd p WHERE {$whereStr} ORDER BY {$orderBy} LIMIT ?, ?");
$pakdList = [];
if ($stmt) {
    $lp = $params;
    $lp[] = $offset; $lp[] = $limit;
    $stmt->bind_param($types . 'ii', ...$lp);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $pakdList[] = $row;
    $stmt->close();
}

// User avatar map
$userAvatarMap = [];
$uRes = $conn->query("SELECT email, full_name, avatar FROM users WHERE email IS NOT NULL AND email != ''");
if ($uRes) while ($u = $uRes->fetch_assoc()) $userAvatarMap[strtolower($u['email'])] = $u;

// Stats
$wonFilter  = $wonStageId ? "odoo_stage_id = {$wonStageId}" : "status = 'approved'";
$totalWon   = (int)($conn->query("SELECT COUNT(*) as c FROM pakd WHERE {$wonFilter}")->fetch_assoc()['c'] ?? 0);
$totalValue = (float)($conn->query("SELECT SUM(opp_value) as s FROM pakd WHERE {$wonFilter}")->fetch_assoc()['s'] ?? 0);

$viMonths = ['','Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];

function formatVND2($n) {
    if ($n >= 1e9)  return number_format($n/1e9, 1).'B';
    if ($n >= 1e6)  return number_format($n/1e6, 1).'M';
    if ($n >= 1e3)  return number_format($n/1e3, 0).'K';
    return number_format($n, 0);
}

function avatarColor2($name) {
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

function renderAM2($amName, $amEmail, $userAvatarMap) {
    $user = !empty($amEmail) ? ($userAvatarMap[strtolower($amEmail)] ?? null) : null;
    $name = htmlspecialchars($amName ?: '—');
    $c    = avatarColor2($amName ?? '');
    $bgStyle = "background:{$c['bg']};color:{$c['fg']}";
    if ($user && !empty($user['avatar'])) {
        $avatar   = '<img src="' . htmlspecialchars($user['avatar']) . '" class="am-avatar" alt="' . $name . '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">';
        $fallback = '<div class="am-initials" style="display:none;' . $bgStyle . '">' . htmlspecialchars(substr($amName ?? '', 0, 2)) . '</div>';
    } else {
        $avatar   = '';
        $parts    = array_filter(explode(' ', $amName ?? ''));
        $ini      = strtoupper(($parts[0][0] ?? '') . (count($parts) > 1 ? end($parts)[0] : ''));
        $fallback = '<div class="am-initials" style="' . $bgStyle . '">' . htmlspecialchars($ini ?: '?') . '</div>';
    }
    return '<div class="am-cell">' . $avatar . $fallback
        . '<div class="am-name">' . $name . '</div></div>';
}

function renderStars2($pct) {
    $filled = max(0, min(5, (int)round($pct / 20)));
    $html = '<span class="star-rating" title="' . (int)$pct . '%">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $filled
            ? '<i class="fas fa-star"></i>'
            : '<i class="fas fa-star off"></i>';
    }
    return $html . '</span>';
}

function sortTh2($label, $col, $currentSort, $currentDir, $extraGetParams = []) {
    $isActive = $currentSort === $col;
    $nextDir  = ($isActive && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $cls      = 'sortable' . ($isActive ? ' sort-' . strtolower($currentDir) : '');
    $qs       = array_merge($extraGetParams, ['sort' => $col, 'dir' => $nextDir]);
    $url      = '/projects/du-an?' . http_build_query($qs);
    $icon     = $isActive ? ($currentDir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    echo "<th class=\"{$cls}\" onclick=\"window.location='{$url}'\">{$label}<i class=\"fas {$icon} sort-icon\"></i></th>";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Project - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5;
            --success: #16a34a; --warning: #d97706; --danger: #dc2626;
            --bg: #f8fafc; --card: #ffffff; --slate: #1e293b;
            --gray: #64748b; --lgray: #94a3b8; --border: #e2e8f0;
            --r-xl: 18px; --r-lg: 12px; --r-md: 8px;
            --sh-sm: 0 1px 3px rgba(0,0,0,.06); --sh-md: 0 4px 16px rgba(0,0,0,.08);
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--slate); margin: 0; }
        .main-content { flex: 1; padding: 28px; min-height: 100vh; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header-left { display: flex; align-items: center; gap: 14px; }
        .page-icon { width: 48px; height: 48px; background: linear-gradient(135deg, #16a34a, #15803d); border-radius: var(--r-lg); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; box-shadow: 0 4px 12px rgba(22,163,74,.3); }
        .page-title h1 { font-size: 22px; font-weight: 700; margin: 0 0 3px; }
        .page-title p  { font-size: 13px; color: var(--gray); margin: 0; }

        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: var(--r-md); font-size: 13px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; text-decoration: none; transition: all .2s; white-space: nowrap; }
        .btn-outline { background: white; color: var(--gray); border: 1px solid var(--border); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: rgba(99,102,241,.04); }

        .stats-row { display: flex; gap: 16px; margin-bottom: 20px; }
        .stat-card { background: var(--card); border-radius: var(--r-lg); padding: 16px 20px; border: 1px solid var(--border); box-shadow: var(--sh-sm); display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 40px; height: 40px; border-radius: var(--r-md); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .stat-icon.green { background: rgba(22,163,74,.1);  color: var(--success); }
        .stat-icon.blue  { background: rgba(37,99,235,.1);  color: #2563eb; }
        .stat-val { font-size: 22px; font-weight: 700; color: var(--slate); line-height: 1; }
        .stat-lbl { font-size: 12px; color: var(--gray); margin-top: 3px; }

        .toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .search-wrap { position: relative; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--lgray); font-size: 13px; }
        .search-wrap input { padding: 9px 14px 9px 34px; border: 1px solid var(--border); border-radius: var(--r-md); font-size: 13px; font-family: inherit; color: var(--slate); background: white; outline: none; width: 260px; transition: all .2s; }
        .search-wrap input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99,102,241,.08); }

        .table-card { background: var(--card); border-radius: var(--r-md); border: 1px solid var(--border); box-shadow: var(--sh-sm); overflow: hidden; display: flex; flex-direction: column; }
        .table-wrap { overflow-x: auto; overflow-y: auto; max-height: calc(100vh - 280px); }
        .table-wrap thead th { position: sticky; top: 0; z-index: 10; box-shadow: 0 1px 0 var(--border); }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        thead th { padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--lgray); background: #f8fafc; white-space: nowrap; }
        thead th.sortable { cursor: pointer; user-select: none; }
        thead th.sortable:hover { color: var(--success); background: #f0fdf4; }
        thead th.sort-asc, thead th.sort-desc { color: var(--success); }
        .sort-icon { margin-left: 4px; font-size: 10px; opacity: .5; }
        thead th.sort-asc .sort-icon, thead th.sort-desc .sort-icon { opacity: 1; }

        tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:nth-child(odd)  { background: #ffffff; }
        tbody tr:nth-child(even) { background: #eef2f7; }
        tbody tr:hover { background: rgba(22,163,74,.06) !important; }
        tbody td { padding: 12px 16px; font-size: 13px; color: var(--slate); vertical-align: middle; }

        .month-group-row td { background: #f0fdf4; padding: 6px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--success); border-bottom: 1px solid #bbf7d0; cursor: default; }
        .month-group-row:hover { background: #f0fdf4 !important; }

        .opp-value { font-weight: 600; color: #2563eb; }
        .star-rating { display: inline-flex; gap: 1px; line-height: 1; }
        .star-rating .fa-star     { font-size: 13px; color: #f59e0b; }
        .star-rating .fa-star.off { color: #d1d5db; }

        .am-cell { display: flex; align-items: center; gap: 8px; }
        .am-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid var(--border); }
        .am-initials { width: 30px; height: 30px; border-radius: 50%; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .am-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
        .odoo-link { font-size: 11px; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 3px; font-weight: 500; }
        .odoo-link:hover { text-decoration: underline; }

        .empty-state { text-align: center; padding: 60px 24px; color: var(--gray); }
        .empty-state .empty-icon { width: 72px; height: 72px; background: rgba(22,163,74,.08); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-size: 32px; color: var(--success); opacity: .6; }
        .empty-state h3 { font-size: 17px; font-weight: 600; margin: 0 0 8px; color: var(--slate); }
        .empty-state p  { font-size: 13px; margin: 0; }

        .pagination { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-top: 1px solid var(--border); background: #f8fafc; }
        .page-info { font-size: 13px; color: var(--gray); }
        .page-links { display: flex; gap: 6px; }
        .page-btn { display: flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 10px; border-radius: 6px; border: 1px solid var(--border); background: white; color: var(--slate); font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all .15s; }
        .page-btn:hover:not(.disabled) { border-color: var(--success); color: var(--success); }
        .page-btn.active { background: var(--success); color: white; border-color: var(--success); }
        .page-btn.disabled { opacity: .5; cursor: not-allowed; background: #f1f5f9; }

        .toast { position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; color: white; display: flex; align-items: center; gap: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.18); animation: toastIn .3s ease; font-family: Inter, sans-serif; }
        .toast.success { background: #16a34a; }
        .toast.error   { background: #dc2626; }
        @keyframes toastIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
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
        <?php $page_title = 'Dự án'; include __DIR__ . '/../includes/topbar.php'; ?>

        <div class="page-header">
            <div class="page-header-left">
                <div class="page-icon"><i class="fas fa-trophy"></i></div>
                <div class="page-title">
                    <h1>My Project</h1>
                    <p>Business Plans ở stage Won<?= $wonStageId ? " (Stage ID: {$wonStageId})" : ' (status = Approved)' ?></p>
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-trophy"></i></div>
                <div>
                    <div class="stat-val"><?= $totalWon ?></div>
                    <div class="stat-lbl">Tổng dự án won</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="stat-val"><?= formatVND2($totalValue) ?> VND</div>
                    <div class="stat-lbl">Tổng giá trị</div>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchBox" placeholder="Tìm theo tên, khách hàng, AM..."
                           value="<?= htmlspecialchars($search) ?>"
                           onkeydown="if(event.key==='Enter')applyFilter()">
                </div>
                <button class="btn btn-outline" onclick="clearFilter()">
                    <i class="fas fa-times"></i> Xoá lọc
                </button>
            </div>
            <div style="font-size:12px;color:var(--lgray);"><?= $totalRecords ?> dự án</div>
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php
                            $qp = array_filter(['search' => $search, 'page' => ($page > 1 ? $page : null)]);
                            ?>
                            <th>#</th>
                            <?php sortTh2('Tên dự án',    'name',            $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('Khách hàng',   'company_name',    $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('AM',            'am_name',         $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('Bộ phận',       'department',      $sortCol, $sortDir, $qp) ?>
                            <th>Lead/Opp Divisions</th>
                            <?php sortTh2('Giá trị',       'opp_value',       $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('Xác suất',      'opp_probability', $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('Stage Odoo',    'odoo_stage_name', $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('Ngày assign',   'assignment_date', $sortCol, $sortDir, $qp) ?>
                            <?php sortTh2('Dự kiến đóng',  'expected_closing',$sortCol, $sortDir, $qp) ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($pakdList)): ?>
                        <tr><td colspan="11">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-trophy"></i></div>
                                <h3>Chưa có dự án nào</h3>
                                <p>Các Business Plans được duyệt (status = Approved) sẽ hiện tại đây.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php
                        $currentMonthKey = null;
                        $rowNum = 0;
                        foreach ($pakdList as $p):
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
                                    echo "<tr class=\"month-group-row\"><td colspan=\"11\"><i class=\"fas fa-calendar-alt\" style=\"margin-right:6px;\"></i>{$groupLabel}</td></tr>";
                                }
                            }
                            $rowNum++;
                        ?>
                        <tr>
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
                            <td><?= !empty($p['company_name']) ? htmlspecialchars($p['company_name']) : '—' ?></td>
                            <td><?= renderAM2($p['am_name'], $p['am_email'], $userAvatarMap) ?></td>
                            <td style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($p['department'] ?: '—') ?></td>
                            <td style="font-size:12px;color:var(--slate);"><?= !empty($p['division_names']) ? htmlspecialchars($p['division_names']) : '—' ?></td>
                            <td class="opp-value"><?= formatVND2($p['opp_value']) ?> <?= htmlspecialchars($p['currency']) ?></td>
                            <td><?= renderStars2($p['opp_probability']) ?></td>
                            <td style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($p['odoo_stage_name'] ?: '—') ?></td>
                            <td style="font-size:12px;color:var(--lgray);"><?= !empty($p['assignment_date']) ? date('d/m/Y', strtotime($p['assignment_date'])) : '—' ?></td>
                            <td style="font-size:12px;color:var(--lgray);"><?= !empty($p['expected_closing']) ? date('d/m/Y', strtotime($p['expected_closing'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRecords > 0): ?>
            <div class="pagination">
                <div class="page-info">
                    Hiển thị <strong><?= min($offset+1, $totalRecords) ?></strong> đến <strong><?= min($offset+$limit, $totalRecords) ?></strong> trong tổng số <strong><?= $totalRecords ?></strong> dự án
                </div>
                <div class="page-links">
                    <?php
                    $qs2     = array_filter(['search' => $search, 'sort' => ($sortCol !== 'assignment_date' ? $sortCol : null), 'dir' => ($sortDir !== 'DESC' ? $sortDir : null)]);
                    $baseUrl = '/projects/du-an?' . (empty($qs2) ? '' : http_build_query($qs2).'&') . 'page=';
                    ?>
                    <a href="<?= $page>1 ? $baseUrl.($page-1) : '#' ?>" class="page-btn <?= $page<=1?'disabled':'' ?>" <?= $page<=1?'onclick="return false;"':'' ?>><i class="fas fa-chevron-left"></i></a>
                    <?php
                    $sp = max(1, $page-2); $ep = min($totalPages, $page+2);
                    if ($sp > 1) { echo '<a href="'.$baseUrl.'1" class="page-btn">1</a>'; if ($sp > 2) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default;">...</span>'; }
                    for ($i=$sp; $i<=$ep; $i++) echo '<a href="'.$baseUrl.$i.'" class="page-btn '.($i===$page?'active':'').'">'.$i.'</a>';
                    if ($ep < $totalPages) { if ($ep < $totalPages-1) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default;">...</span>'; echo '<a href="'.$baseUrl.$totalPages.'" class="page-btn">'.$totalPages.'</a>'; }
                    ?>
                    <a href="<?= $page<$totalPages ? $baseUrl.($page+1) : '#' ?>" class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" <?= $page>=$totalPages?'onclick="return false;"':'' ?>><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function applyFilter() {
    const s = document.getElementById('searchBox').value.trim();
    window.location.href = '/projects/du-an' + (s ? '?search=' + encodeURIComponent(s) : '');
}
function clearFilter() {
    window.location.href = '/projects/du-an';
}
</script>
</body>
</html>
