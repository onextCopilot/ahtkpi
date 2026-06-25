<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { header("Location: /dashboard"); exit(); }

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$full_name = $_SESSION['full_name'] ?? '';

// Online users (active within the last 15 minutes)
$online_users = [];
$res = $conn->query("SELECT id, full_name, email, role, avatar, last_active FROM users WHERE last_active >= NOW() - INTERVAL 15 MINUTE ORDER BY last_active DESC");
if ($res) while ($row = $res->fetch_assoc()) $online_users[] = $row;

$h = (int)date('G');
$greet = $h < 11 ? 'Chào buổi sáng' : ($h < 13 ? 'Chào buổi trưa' : ($h < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));

// tile: [label, subtitle, href, gradient, svg-inner]
$tiles = [
    ['Departments', 'Phòng ban & phân cấp', '/settings/departments', 'linear-gradient(135deg,#6366f1,#4338ca)',
        '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
    ['Core Members', 'Nhân sự chủ chốt', '/settings/core-members', 'linear-gradient(135deg,#0ea5e9,#0369a1)',
        '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['Users', 'Tài khoản & vai trò', '/settings/users', 'linear-gradient(135deg,#16a34a,#15803d)',
        '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['SMTP', 'Cấu hình email', '/settings/smtp', 'linear-gradient(135deg,#8b5cf6,#6d28d9)',
        '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'],
    ['Cảnh báo Công nợ', 'Điểm phạt KPI', '/settings/debt-warning', 'linear-gradient(135deg,#ef4444,#b91c1c)',
        '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    ['Odoo API', 'Tích hợp Odoo ERP', '/settings/odoo', 'linear-gradient(135deg,#a855f7,#7e22ce)',
        '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'],
    ['Jira Software', 'Theo dõi issue', '/settings/jira', 'linear-gradient(135deg,#2563eb,#1d4ed8)',
        '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'],
    ['Sale Teams', 'Đội sale & thành viên', '/settings/teams', 'linear-gradient(135deg,#14b8a6,#0f766e)',
        '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['Sale Levels', 'Cấp bậc & chỉ tiêu', '/settings/sale-levels', 'linear-gradient(135deg,#f59e0b,#b45309)',
        '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>'],
    ['Database Backup', 'Sao lưu tự động', '/settings/backup', 'linear-gradient(135deg,#0891b2,#155e75)',
        '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>'],
    ['Odoo Currency Rates', 'Tỷ giá từ Odoo', '/settings/odoo-rates', 'linear-gradient(135deg,#22c55e,#15803d)',
        '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'],
    ['AI Workflow', 'Cấu hình AI agent', '/settings/workflow', 'linear-gradient(135deg,#6366f1,#4338ca)',
        '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
    ['AI Hive (Presale)', 'API & Model trợ lý AI', '/settings/aihive', 'linear-gradient(135deg,#7c3aed,#5b21b6)',
        '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/>'],
    ['ArrowHitech API', 'Tích hợp Profile API', '/settings/arrowhitech', 'linear-gradient(135deg,#f97316,#c2410c)',
        '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M7 8h.01M7 12h10M7 16h10"/>'],
    ['Presale Prompts', 'Prompt & quick actions', '/settings/presale-prompts', 'linear-gradient(135deg,#eab308,#a16207)',
        '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="10" x2="15" y2="10"/><line x1="9" y1="14" x2="15" y2="14"/>'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - AHT KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body{font-family:'Inter',sans-serif;margin:0}
    .main-content{background:
        radial-gradient(1100px 520px at 85% -12%,rgba(59,130,246,.30),transparent 60%),
        radial-gradient(900px 600px at 0% 115%,rgba(99,102,241,.22),transparent 55%),
        linear-gradient(135deg,#0b1530 0%,#0e1b3a 50%,#101c40 100%) !important;
        min-height:100vh;padding:24px 36px !important}
    .st-wrap{display:flex;flex-direction:column;min-height:calc(100vh - 96px)}
    .main-content .top-bar{background:transparent !important;border:none !important;box-shadow:none !important;justify-content:flex-end !important}
    .main-content .top-bar .page-title{display:none !important}
    .main-content .top-bar .user-info{display:none !important}
    .main-content .top-bar .notification-bell{color:rgba(255,255,255,.85) !important}
    .main-content .top-bar .notification-bell:hover{background:rgba(255,255,255,.12) !important}

    .st-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap;
        padding:4px 0 28px;margin-bottom:30px;border-bottom:1px solid rgba(255,255,255,.10);color:#fff}
    .st-greet{font-size:13px;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,.6)}
    .st-name{font-size:30px;font-weight:700;margin:2px 0 6px;line-height:1.1}
    .st-sub{font-size:13px;color:rgba(255,255,255,.72);max-width:520px}

    .st-sec{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.55);margin:0 0 18px;text-align:center}
    .st-grid{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
    .st-tile{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;gap:13px;width:168px;padding:16px 8px;border-radius:16px;text-decoration:none;transition:.16s}
    .st-tile:hover{background:rgba(255,255,255,.05)}
    .st-tile:hover .st-ic{transform:translateY(-4px);box-shadow:0 16px 30px rgba(0,0,0,.45)}
    .st-ic{width:78px;height:78px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(0,0,0,.34);transition:.16s}
    .st-ic svg{width:33px;height:33px;fill:none;stroke:#fff;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .st-tl{font-size:13.5px;font-weight:700;color:#fff}
    .st-st{font-size:11px;color:rgba(255,255,255,.55);margin-top:-7px}

    /* Online users — glass strip */
    .st-online{margin-top:36px;padding:18px 20px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09)}
    .st-online-head{display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:600;margin-bottom:16px}
    .st-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.2);animation:stpulse 2s infinite}
    .st-online-meta{margin-left:auto;font-size:12px;font-weight:400;color:rgba(255,255,255,.5)}
    .st-online-list{display:flex;flex-wrap:wrap;gap:12px}
    .st-ou{display:flex;align-items:center;gap:10px;padding:9px 14px;background:rgba(255,255,255,.05);border-radius:12px;border:1px solid rgba(255,255,255,.08)}
    .st-ou-av{width:34px;height:34px;border-radius:50%;object-fit:cover;background:#6366f1;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px}
    .st-ou-name{font-size:13px;font-weight:500;color:#fff}
    .st-ou-role{font-size:11px;color:rgba(255,255,255,.5);text-transform:capitalize}
    @keyframes stpulse{0%{box-shadow:0 0 0 0 rgba(16,185,129,.4)}70%{box-shadow:0 0 0 6px rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}

    .st-foot{margin-top:auto;padding-top:26px;border-top:1px solid rgba(255,255,255,.08);
        display:flex;align-items:baseline;gap:10px;color:rgba(255,255,255,.45)}
    .st-foot b{color:rgba(255,255,255,.75);font-size:15px;letter-spacing:.5px}
    .st-foot span{font-size:12px}
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = 'System Settings'; include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="st-wrap">
            <div class="st-hero">
                <div>
                    <div class="st-greet"><?= h($greet) ?> 👋</div>
                    <div class="st-name"><?= h($full_name) ?></div>
                    <div class="st-sub">Trung tâm cấu hình hệ thống. Chọn một module bên dưới để quản lý cấu hình &amp; tài nguyên.</div>
                </div>
            </div>

            <div class="st-sec">Cấu hình hệ thống</div>
            <div class="st-grid">
            <?php foreach ($tiles as $t):
                [$label,$sub,$href,$grad,$svg] = $t; ?>
                <a href="<?= h($href) ?>" class="st-tile">
                    <div class="st-ic" style="background:<?= $grad ?>"><svg viewBox="0 0 24 24"><?= $svg ?></svg></div>
                    <div class="st-tl"><?= h($label) ?></div>
                    <div class="st-st"><?= h($sub) ?></div>
                </a>
            <?php endforeach; ?>
            </div>

            <div class="st-online">
                <div class="st-online-head">
                    <span class="st-dot"></span>
                    Online Users (<?= count($online_users) ?>)
                    <span class="st-online-meta">Hoạt động trong 15 phút qua</span>
                </div>
                <div class="st-online-list">
                    <?php foreach ($online_users as $ou): ?>
                    <div class="st-ou">
                        <?php if (!empty($ou['avatar'])): ?>
                            <img src="<?= h($ou['avatar']) ?>" class="st-ou-av" alt="Avatar">
                        <?php else: ?>
                            <div class="st-ou-av"><?= strtoupper(substr($ou['full_name'] ?? '?', 0, 1)) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="st-ou-name"><?= h($ou['full_name']) ?></div>
                            <div class="st-ou-role"><?= h($ou['role']) ?> · Active just now</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($online_users)): ?>
                        <span style="color:rgba(255,255,255,.5);font-size:13px;">Không có ai online lúc này.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="st-foot">
                <b>System Settings</b><span>Quản lý cấu hình &amp; tài nguyên hệ thống AHT</span>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/dashboard.js"></script>
</body>
</html>
