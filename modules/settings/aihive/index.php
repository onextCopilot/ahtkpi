<?php
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS aihive_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key TEXT NOT NULL,
    base_url TEXT NOT NULL,
    agent_id TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$full_name = $_SESSION['full_name'] ?? 'User';
$avatar = $_SESSION['avatar'] ?? '';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $api_key = $_POST['api_key'] ?? '';
    $base_url = $_POST['base_url'] ?? '';
    $agent_id = ''; // Không cần dùng nữa

    try {
        // Check if settings exist
        $result = $conn->query("SELECT COUNT(*) as count FROM aihive_settings");
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE aihive_settings SET api_key = ?, base_url = ? WHERE id = 1");
            $stmt->bind_param("ss", $api_key, $base_url);
            $stmt->execute();
        } else {
            // Insert new settings
            $stmt = $conn->prepare("INSERT INTO aihive_settings (api_key, base_url, agent_id) VALUES (?, ?, '')");
            $stmt->bind_param("ss", $api_key, $base_url);
            $stmt->execute();
        }

        $success_message = "Cài đặt AI Hive API đã được lưu thành công!";
    } catch (Exception $e) {
        $error_message = "Lỗi khi lưu cài đặt: " . $e->getMessage();
    }
}

// Fetch current settings
$result = $conn->query("SELECT * FROM aihive_settings ORDER BY id DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
} else {
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt AI Hive (Presale Assistant)</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-container {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .settings-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
        .settings-header h2 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
            font-size: 24px;
        }
        .settings-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-group .help-text {
            margin-top: 0.5rem;
            font-size: 12px;
            color: #6b7280;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn-save {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-box h4 { margin: 0 0 0.5rem 0; color: #1e40af; font-size: 14px; }
        .info-box p { margin: 0; color: #1e40af; font-size: 13px; line-height: 1.5; }
        .required { color: #ef4444; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Cài đặt AI Hive (Presale)';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="settings-container">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="settings-card">
                    <div class="settings-header">
                        <h2>Cấu hình kết nối AI Hive</h2>
                        <p>Cấu hình thông tin API Key để kích hoạt trợ lý AI thông minh cho phân hệ Presale</p>
                    </div>

                    <div class="info-box">
                        <h4>📌 Hướng dẫn:</h4>
                        <p>
                            Dữ liệu cấu hình API Key này được dùng để gọi tới aihive.ai, từ đó trợ lý ảo trong tính năng <strong>Sale/Presale Assistant</strong> có thể trả lời tự động dựa trên dữ liệu công ty.<br>
                            Nếu không điền thông tin, hệ thống sẽ tự động sử dụng kịch bản demo (mock data) thay thế.
                        </p>
                    </div>

                    <form id="aihiveForm" method="POST">
                        <input type="hidden" name="action" value="save">

                        <div class="form-group">
                            <label>API Key <span class="required">*</span></label>
                            <input type="password" name="api_key" id="api_key"
                                value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>" required>
                            <div class="help-text">Mã API Key lấy từ tài khoản aihive.ai của bạn</div>
                        </div>

                        <div class="form-group">
                            <label>Base URL <span class="required">*</span></label>
                            <input type="url" name="base_url" id="base_url"
                                value="<?php echo htmlspecialchars($settings['base_url'] ?? 'https://api.aihive.ai/v1'); ?>"
                                required>
                            <div class="help-text">URL gốc của API (Mặc định: https://api.aihive.ai/v1)</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-save">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
        </main>
    </div>
</body>
</html>
