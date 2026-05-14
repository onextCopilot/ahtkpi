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

// Thêm cột vision nếu chưa có
try {
    $conn->query("ALTER TABLE aihive_settings ADD COLUMN vision_api_key TEXT NULL");
    $conn->query("ALTER TABLE aihive_settings ADD COLUMN vision_base_url TEXT NULL");
} catch (Exception $e) {}

$full_name = $_SESSION['full_name'] ?? 'User';
$avatar = $_SESSION['avatar'] ?? '';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $api_key       = $_POST['api_key'] ?? '';
    $base_url      = $_POST['base_url'] ?? '';
    $vision_api_key  = $_POST['vision_api_key'] ?? '';
    $vision_base_url = $_POST['vision_base_url'] ?? '';

    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM aihive_settings");
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            $stmt = $conn->prepare("UPDATE aihive_settings SET api_key=?, base_url=?, vision_api_key=?, vision_base_url=? WHERE id=1");
            $stmt->bind_param("ssss", $api_key, $base_url, $vision_api_key, $vision_base_url);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO aihive_settings (api_key, base_url, agent_id, vision_api_key, vision_base_url) VALUES (?, ?, '', ?, ?)");
            $stmt->bind_param("ssss", $api_key, $base_url, $vision_api_key, $vision_base_url);
            $stmt->execute();
        }

        $success_message = "Cài đặt đã được lưu thành công!";
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
        .settings-container { padding: 2rem; max-width: 800px; margin: 0 auto; }
        .settings-card { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .settings-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid #e5e7eb; }
        .settings-header h2 { margin: 0 0 0.5rem 0; color: #1f2937; font-size: 22px; }
        .settings-header p { margin: 0; color: #6b7280; font-size: 14px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; font-size: 14px; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: all 0.3s; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-group .help-text { margin-top: 0.5rem; font-size: 12px; color: #6b7280; }
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn-save { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.3s; display: flex; align-items: center; gap: 0.5rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(37,99,235,0.3); }
        .btn-save.green { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .btn-save.green:hover { box-shadow: 0 8px 16px rgba(5,150,105,0.3); }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
        .info-box.green { background: #f0fdf4; border-color: #bbf7d0; }
        .info-box h4 { margin: 0 0 0.5rem 0; color: #1e40af; font-size: 14px; }
        .info-box.green h4 { color: #065f46; }
        .info-box p { margin: 0; color: #1e40af; font-size: 13px; line-height: 1.5; }
        .info-box.green p { color: #065f46; }
        .required { color: #ef4444; }
        .section-divider { display: flex; align-items: center; gap: 12px; margin: 2rem 0 1.5rem; }
        .section-divider span { font-size: 13px; font-weight: 600; color: #6b7280; white-space: nowrap; }
        .section-divider::before, .section-divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
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
                    <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error">❌ <?php echo $error_message; ?></div>
                <?php endif; ?>

                <form id="aihiveForm" method="POST">
                    <input type="hidden" name="action" value="save">

                    <!-- === CHAT / PRESALE WORKFLOW === -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h2>🤖 Cấu hình AI Chat (Presale Workflow)</h2>
                            <p>Dùng cho tính năng chat hỏi đáp, soạn SOW, phân tích tài liệu trong Presale Assistant</p>
                        </div>

                        <div class="info-box">
                            <h4>📌 Hướng dẫn:</h4>
                            <p>
                                API Key này được dùng cho chat bot presale. Có thể là Dify Chat App hoặc Workflow App.<br>
                                Base URL phải kết thúc bằng <code>/chat-messages</code> (Chat) hoặc <code>/workflows/run</code> (Workflow).
                            </p>
                        </div>

                        <div class="form-group">
                            <label>API Key <span class="required">*</span></label>
                            <input type="password" name="api_key" id="api_key"
                                value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>" required>
                            <div class="help-text">Mã API Key lấy từ Dify App → API Access</div>
                        </div>

                        <div class="form-group">
                            <label>Base URL <span class="required">*</span></label>
                            <input type="url" name="base_url" id="base_url"
                                value="<?php echo htmlspecialchars($settings['base_url'] ?? 'https://api.dify.ai/v1/workflows/run'); ?>" required>
                            <div class="help-text">Ví dụ: <code>https://api.dify.ai/v1/workflows/run</code> hoặc <code>https://api.dify.ai/v1/chat-messages</code></div>
                        </div>
                    </div>

                    <!-- === VISION AI WORKFLOW (RIÊNG) === -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h2>🖼️ Cấu hình AI Vision (Phân tích Mockup)</h2>
                            <p>Workflow riêng biệt dùng để phân tích ảnh thiết kế web — cần dùng model hỗ trợ Vision (GPT-4o, Gemini 1.5 Pro)</p>
                        </div>

                        <div class="info-box green">
                            <h4>✨ Lưu ý quan trọng:</h4>
                            <p>
                                Cần tạo một <strong>Dify App riêng</strong> (loại "Chat" — không phải Workflow) với model Vision:<br>
                                • GPT-4o hoặc GPT-4 Vision → qua OpenAI<br>
                                • Gemini 1.5 Pro / 2.0 Flash → qua Google AI<br>
                                Base URL phải là: <code>https://api.dify.ai/v1/chat-messages</code><br>
                                <strong>KHÔNG dùng Workflow App</strong> vì Workflow không nhận image input.
                            </p>
                        </div>

                        <div class="form-group">
                            <label>Vision API Key</label>
                            <input type="password" name="vision_api_key" id="vision_api_key"
                                value="<?php echo htmlspecialchars($settings['vision_api_key'] ?? ''); ?>">
                            <div class="help-text">API Key của Dify App Vision riêng (để trống = dùng chung với Chat API Key)</div>
                        </div>

                        <div class="form-group">
                            <label>Vision Base URL</label>
                            <input type="url" name="vision_base_url" id="vision_base_url"
                                value="<?php echo htmlspecialchars($settings['vision_base_url'] ?? 'https://api.dify.ai/v1/chat-messages'); ?>">
                            <div class="help-text">Ví dụ: <code>https://api.dify.ai/v1/chat-messages</code> (Chat App, không phải Workflow)</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save green">
                            💾 Lưu tất cả cài đặt
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

