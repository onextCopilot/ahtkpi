<?php
require_once __DIR__ . '/../../../config/config.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$message = '';
$messageType = '';

// Fetch current setting from database
$ai_agent_key = '';
$res = $conn->query("SELECT setting_value FROM okr_settings WHERE setting_key = 'ai_agent_key'");
if ($res && $row = $res->fetch_assoc()) {
    $ai_agent_key = $row['setting_value'];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        $new_key = trim($_POST['ai_agent_key']);
        
        $stmt = $conn->prepare("INSERT INTO okr_settings (setting_key, setting_value) VALUES ('ai_agent_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("ss", $new_key, $new_key);
        
        if ($stmt->execute()) {
            $ai_agent_key = $new_key;
            $message = "Đã lưu API Key cho AI Workflow thành công!";
            $messageType = "success";
        } else {
            $message = "Lỗi khi lưu vào cơ sở dữ liệu: " . $conn->error;
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Workflow Settings - AHT KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .settings-header {
            background: #f8fafc;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .settings-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .settings-header p {
            margin: 0.5rem 0 0;
            color: #64748b;
            font-size: 0.875rem;
        }

        .settings-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            gap: 8px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $page_title = 'AI Workflow Settings';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="settings-container">
                <div class="settings-card">
                    <div class="settings-header">
                        <h2>AI Agent & Workflow Configuration</h2>
                        <p>Nhập API Key để sử dụng tính năng gợi ý OKR (KR & KA) tự động từ AI.</p>
                    </div>

                    <div class="settings-body">
                        <?php if ($message): ?>
                            <div class="alert <?php echo ($messageType === 'success') ? 'alert-success' : 'alert-error'; ?>">
                                <?php if ($messageType === 'success'): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_config">

                            <div class="form-group">
                                <label for="ai_agent_key">AI Agent API Key</label>
                                <input type="password" id="ai_agent_key" name="ai_agent_key" class="form-control"
                                    value="<?php echo htmlspecialchars($ai_agent_key); ?>" required
                                    placeholder="Enter API Key">
                                <small style="color: #64748b; display: block; margin-top: 5px;">
                                    API Key này được sử dụng để gọi Workflow gợi ý Key Results và Key Activities.
                                </small>
                            </div>

                            <div style="margin-top: 2rem;">
                                <button type="submit" class="btn btn-primary">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    Lưu cấu hình
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>
