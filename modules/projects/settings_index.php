<?php
/**
 * Project Settings — layout dạng tab sidebar (menu dọc trái + nội dung phải).
 * Routes: /projects/settings và /projects/settings/<tab> đều vào file này.
 * Thêm setting mới = thêm 1 mục vào $TABS + 1 partial trong modules/projects/settings/.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/app_settings.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit(); }
$role = $_SESSION['role'] ?? 'user';
if (empty($_SESSION['is_am_bd']) && $role !== 'admin') { header('Location: /dashboard'); exit(); }

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// tab: key => [label, icon, partial, admin_only]
$TABS = [
    'allocation' => ['Tỷ lệ phân bổ giao khoán', 'fa-percent', 'allocation_tab.php', true],
];

// Tab đang chọn (từ path /projects/settings/<tab>), mặc định = tab đầu tiên.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$active = 'allocation';
if (preg_match('#/projects/settings/([\w-]+)#', $path, $m) && isset($TABS[$m[1]])) {
    $active = $m[1];
}
// Bỏ qua tab admin-only nếu không phải admin
if (!empty($TABS[$active][3]) && $role !== 'admin') {
    header('Location: /projects'); exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Settings - AHT KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body{font-family:'Inter',sans-serif;margin:0}
    .main-content{padding:0 !important;background:#f8fafc}
    .ps-layout{display:flex;min-height:calc(100vh - 64px)}
    /* Sub-sidebar (tab dọc) */
    .ps-nav{width:268px;flex-shrink:0;background:#fff;border-right:1px solid #e2e8f0;padding:20px 14px;display:flex;flex-direction:column;gap:4px}
    .ps-nav-head{padding:6px 12px 14px;border-bottom:1px solid #eef2f7;margin-bottom:8px}
    .ps-nav-back{display:inline-flex;align-items:center;gap:7px;font-size:12px;color:#64748b;text-decoration:none}
    .ps-nav-back:hover{color:#4338ca}
    .ps-nav-title{font-size:17px;font-weight:700;color:#0f172a;margin-top:8px}
    .ps-nav a.ps-tab{display:flex;align-items:center;gap:11px;padding:11px 14px;border-radius:9px;color:#334155;text-decoration:none;font-size:14px;font-weight:500;transition:.12s}
    .ps-nav a.ps-tab:hover{background:#f1f5f9}
    .ps-nav a.ps-tab.active{background:#eef2ff;color:#4338ca;font-weight:600}
    .ps-nav a.ps-tab i{width:18px;text-align:center;font-size:14px;color:#94a3b8}
    .ps-nav a.ps-tab.active i{color:#4338ca}
    /* Content */
    .ps-content{flex:1;padding:26px 30px;overflow-x:auto}
    .ps-content-head{margin-bottom:18px}
    .ps-content-head h2{font-size:20px;font-weight:700;color:#0f172a;margin:0 0 5px}
    .ps-content-head p{font-size:13px;color:#64748b;margin:0;max-width:720px}
    /* Allocation table styles */
    .al-ok{background:#dcfce7;color:#166534;border:1px solid #86efac;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-weight:600}
    .al-note{font-size:12px;color:#b45309;background:#fef3c7;border:1px solid #fde68a;padding:10px 14px;border-radius:8px;margin-bottom:16px}
    .al-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
    .al-table-wrap{overflow-x:auto}
    table.al-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
    .al-table th,.al-table td{padding:10px 12px;border-bottom:1px solid #e2e8f0;text-align:center}
    .al-table thead th{background:#0f172a;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
    .al-table td.pt-name{text-align:left;font-weight:600;color:#334155;min-width:280px;padding:6px 12px}
    .al-name-input{width:100%;min-width:250px;padding:8px 10px;border:1px solid transparent;border-radius:6px;font-size:13px;font-weight:600;color:#334155;font-family:inherit;background:transparent;outline:none}
    .al-name-input:hover{border-color:#e2e8f0;background:#fff}
    .al-name-input:focus{border-color:#6366f1;background:#fff;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
    .al-input{width:64px;padding:7px 8px;border:1px solid #cbd5e1;border-radius:6px;text-align:right;font-size:13px;outline:none;font-family:inherit}
    .al-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
    .al-suffix{color:#94a3b8;font-size:12px;margin-left:2px}
    .al-table tbody tr:nth-child(even){background:#f8fafc}
    .al-btn{margin-top:18px;background:#4f46e5;color:#fff;border:none;padding:11px 22px;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:8px}
    .al-btn:hover{background:#4338ca}
    @media (max-width:820px){ .ps-layout{flex-direction:column} .ps-nav{width:auto;flex-direction:row;flex-wrap:wrap;border-right:none;border-bottom:1px solid #e2e8f0} }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = 'Project Settings'; include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="ps-layout">
            <nav class="ps-nav">
                <div class="ps-nav-head">
                    <a href="/projects" class="ps-nav-back"><i class="fas fa-arrow-left"></i> Projects</a>
                    <div class="ps-nav-title">Project Settings</div>
                </div>
                <?php foreach ($TABS as $key => $tab):
                    if (!empty($tab[3]) && $role !== 'admin') continue; ?>
                <a href="/projects/settings/<?= h($key) ?>" class="ps-tab <?= $active === $key ? 'active' : '' ?>">
                    <i class="fas <?= h($tab[1]) ?>"></i> <?= h($tab[0]) ?>
                </a>
                <?php endforeach; ?>
            </nav>
            <div class="ps-content">
                <?php
                $partial = __DIR__ . '/settings/' . basename($TABS[$active][2]);
                if (is_file($partial)) { include $partial; }
                else { echo '<p style="color:#64748b;">Chưa có nội dung cho mục này.</p>'; }
                ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
