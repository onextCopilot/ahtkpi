<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/app_settings.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}
// Chỉ admin được cấu hình
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /");
    exit();
}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pts = max(0, (int) ($_POST['penalty_points'] ?? 0));
    app_setting_set($conn, 'debt_warning_penalty_points', (string) $pts, (int) $_SESSION['user_id']);
    $saved = true;
}

$penalty_points = (int) app_setting_get($conn, 'debt_warning_penalty_points', '5');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cấu hình Cảnh báo Công nợ</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .sw-wrap { padding: 1.5rem; max-width: 640px; }
        .sw-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; }
        .sw-card h2 { margin:0 0 6px; font-size:18px; color:#0f172a; }
        .sw-card p.desc { margin:0 0 20px; color:#64748b; font-size:13px; }
        .sw-label { display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px; }
        .sw-input { width:160px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:15px; outline:none; }
        .sw-hint { font-size:12px; color:#94a3b8; margin-top:6px; }
        .sw-btn { margin-top:20px; background:#4f46e5; color:#fff; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; }
        .sw-ok { background:#dcfce7; color:#166534; border:1px solid #86efac; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-weight:600; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php $page_title = 'Cấu hình Cảnh báo Công nợ'; include __DIR__ . '/../../includes/topbar.php'; ?>
            <div class="sw-wrap">
                <?php if ($saved): ?><div class="sw-ok">✓ Đã lưu cấu hình.</div><?php endif; ?>
                <div class="sw-card">
                    <h2>Cảnh báo Công nợ — Điểm phạt</h2>
                    <p class="desc">Số điểm KPI bị trừ cho mỗi cảnh báo "invoice chưa add vào Debts" gửi cho AM (dùng ở trang Debts Check).</p>
                    <form method="POST">
                        <label class="sw-label">Điểm trừ mỗi cảnh báo</label>
                        <input type="number" name="penalty_points" class="sw-input" min="0" step="1" value="<?php echo $penalty_points; ?>">
                        <div class="sw-hint">Ví dụ: 5 = mỗi invoice bị cảnh báo trừ 5 điểm KPI của AM.</div>
                        <button type="submit" class="sw-btn">Lưu cấu hình</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
