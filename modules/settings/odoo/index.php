<?php
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS odoo_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    odoo_url TEXT NOT NULL,
    odoo_database TEXT NOT NULL,
    odoo_username TEXT NOT NULL,
    odoo_api_key TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$full_name = $_SESSION['full_name'] ?? 'User';
$avatar = $_SESSION['avatar'] ?? '';

$success_message = '';
$error_message = '';
$test_result = null;

// Handle test connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test') {
    $odoo_url = $_POST['odoo_url'] ?? '';
    $odoo_database = $_POST['odoo_database'] ?? '';
    $odoo_username = $_POST['odoo_username'] ?? '';
    $odoo_api_key = $_POST['odoo_api_key'] ?? '';

    try {
        $url = rtrim($odoo_url, '/') . '/jsonrpc';

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'common',
                'method' => 'authenticate',
                'args' => [
                    $odoo_database,
                    $odoo_username,
                    $odoo_api_key,
                    []
                ]
            ],
            'id' => rand(0, 1000000)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        // curl_close() is deprecated in PHP 8.5 and not needed

        if ($curlError) {
            throw new Exception("Connection error: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode);
        }

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            $errorMsg = $result['error']['data']['message'] ?? $result['error']['message'] ?? 'Unknown error';
            throw new Exception($errorMsg);
        }

        $uid = $result['result'] ?? null;

        if (!$uid || !is_numeric($uid)) {
            throw new Exception("Authentication failed. Please check your credentials.");
        }

        $test_result = [
            'success' => true,
            'message' => 'Kết nối thành công!',
            'user_id' => $uid,
            'database' => $odoo_database
        ];

    } catch (Exception $e) {
        $test_result = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }

    // Always return JSON for test action
    header('Content-Type: application/json');
    echo json_encode($test_result);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'save')) {
    $odoo_url = $_POST['odoo_url'] ?? '';
    $odoo_database = $_POST['odoo_database'] ?? '';
    $odoo_username = $_POST['odoo_username'] ?? '';
    $odoo_api_key = $_POST['odoo_api_key'] ?? '';

    try {
        // Check if settings exist
        $result = $conn->query("SELECT COUNT(*) as count FROM odoo_settings");
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE odoo_settings SET odoo_url = ?, odoo_database = ?, odoo_username = ?, odoo_api_key = ? WHERE id = 1");
            $stmt->bind_param("ssss", $odoo_url, $odoo_database, $odoo_username, $odoo_api_key);
            $stmt->execute();
        } else {
            // Insert new settings
            $stmt = $conn->prepare("INSERT INTO odoo_settings (odoo_url, odoo_database, odoo_username, odoo_api_key) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $odoo_url, $odoo_database, $odoo_username, $odoo_api_key);
            $stmt->execute();
        }

        $success_message = "Cài đặt Odoo API đã được lưu thành công!";
    } catch (Exception $e) {
        $error_message = "Lỗi khi lưu cài đặt: " . $e->getMessage();
    }
}

// Fetch current settings
$result = $conn->query("SELECT * FROM odoo_settings ORDER BY id DESC LIMIT 1");
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
    <title>Cài đặt Odoo API</title>
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

        .btn-save,
        .btn-test {
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

        .btn-save {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }

        .btn-test {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-test:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-box h4 {
            margin: 0 0 0.5rem 0;
            color: #1e40af;
            font-size: 14px;
        }

        .info-box p {
            margin: 0;
            color: #1e40af;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-box code {
            background: #dbeafe;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }

        .required {
            color: #ef4444;
        }

        .test-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
            display: none;
        }

        .test-result.show {
            display: block;
        }

        .test-result.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #16a34a;
        }

        .test-result.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #dc2626;
        }

        .test-result-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .test-result-details {
            font-size: 13px;
            margin-left: 1.75rem;
        }

        .spinner {
            border: 2px solid #f3f4f6;
            border-top: 2px solid #2563eb;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Cài đặt Odoo API';
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
                        <h2>Cấu hình kết nối Odoo</h2>
                        <p>Cấu hình thông tin để kết nối với Odoo ERP và lấy dữ liệu khách hàng</p>
                    </div>

                    <div class="info-box">
                        <h4>📌 Hướng dẫn cấu hình Odoo API:</h4>
                        <p>
                            <strong>1. Tìm Database Name:</strong><br>
                            • <strong>Odoo Cloud/SaaS:</strong> Thường là tên subdomain (ví dụ: nếu URL là
                            <code>mycompany.odoo.com</code> thì database là <code>mycompany</code>)<br>
                            • <strong>Odoo Self-hosted:</strong> Xem trong file config <code>/etc/odoo/odoo.conf</code>
                            hoặc hỏi admin<br>
                            • <strong>Cách khác:</strong> Đăng nhập Odoo → URL sẽ có dạng
                            <code>https://domain/web?db=<strong>DATABASE_NAME</strong></code><br>
                            <br>
                            <strong>2. Lấy API Key:</strong><br>
                            • Đăng nhập vào Odoo tại <strong>https://erp18.merket.io/odoo</strong><br>
                            • Click vào tên user (góc trên bên phải) → <strong>My Profile</strong> hoặc
                            <strong>Preferences</strong><br>
                            • Vào tab <strong>Account Security</strong><br>
                            • Tạo <strong>API Key</strong> mới (đặt tên ví dụ: "AHT KPI Integration")<br>
                            • Sao chép API Key và dán vào form bên dưới<br>
                            <br>
                            <strong>⚠️ Lưu ý:</strong> API Key chỉ hiển thị 1 lần khi tạo, hãy lưu lại cẩn thận!
                        </p>
                    </div>

                    <form id="odooForm" method="POST">
                        <input type="hidden" name="action" value="save">

                        <div class="form-group">
                            <label>Odoo URL <span class="required">*</span></label>
                            <input type="url" name="odoo_url" id="odoo_url"
                                value="<?php echo htmlspecialchars($settings['odoo_url'] ?? 'https://erp18.merket.io/odoo'); ?>"
                                required>
                            <div class="help-text">URL của Odoo instance (ví dụ: https://erp18.merket.io/odoo)</div>
                        </div>

                        <div class="form-group">
                            <label>Database Name <span class="required">*</span></label>
                            <input type="text" name="odoo_database" id="odoo_database"
                                value="<?php echo htmlspecialchars($settings['odoo_database'] ?? ''); ?>" required>
                            <div class="help-text">Tên database Odoo của bạn</div>
                        </div>

                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="odoo_username" id="odoo_username"
                                value="<?php echo htmlspecialchars($settings['odoo_username'] ?? ''); ?>" required>
                            <div class="help-text">Email đăng nhập Odoo</div>
                        </div>

                        <div class="form-group">
                            <label>API Key <span class="required">*</span></label>
                            <input type="password" name="odoo_api_key" id="odoo_api_key"
                                value="<?php echo htmlspecialchars($settings['odoo_api_key'] ?? ''); ?>" required>
                            <div class="help-text">API Key từ Odoo (được tạo trong Account Security)</div>
                        </div>

                        <div id="testResult" class="test-result"></div>

                        <div class="form-actions">
                            <button type="button" class="btn-test" onclick="testConnection()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                                <span id="testButtonText">Test kết nối</span>
                            </button>
                            <button type="submit" class="btn-save">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Lưu cài đặt
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Cache Management Section -->
                <div class="settings-card" style="margin-top: 2rem;">
                    <div class="settings-header">
                        <h2>Quản lý Cache Dữ liệu</h2>
                        <p>Dữ liệu khách hàng được lưu cache trong 24h để tăng tốc độ tải trang. Bạn có thể làm mới thủ
                            công
                            tại đây.</p>
                    </div>
                    <div class="form-group">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <strong>Trạng thái Cache:</strong>
                                <?php
                                $cacheFile = __DIR__ . '/../../../cache/customers.cache.php';
                                if (file_exists($cacheFile)) {
                                    $cacheTime = date("H:i d/m/Y", filemtime($cacheFile));
                                    $cacheSize = round(filesize($cacheFile) / 1024, 2) . ' KB';
                                    echo "<span style='color: green;'>Đã cache ($cacheSize) - Cập nhật lúc: $cacheTime</span>";
                                } else {
                                    echo "<span style='color: orange;'>Chưa có cache</span>";
                                }
                                ?>
                            </div>
                            <button type="button" class="btn-secondary" onclick="refreshCache()" id="btnRefreshCache">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M23 4v6h-6"></path>
                                    <path d="M1 20v-6h6"></path>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15">
                                    </path>
                                </svg>
                                <span id="refreshBtnText">Làm mới Cache ngay</span>
                            </button>
                        </div>
                        <div id="cacheResult" style="margin-top: 1rem; display: none;"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function testConnection() {
            const btn = document.querySelector('.btn-test');
            const btnText = document.getElementById('testButtonText');
            const resultDiv = document.getElementById('testResult');

            // Get form values
            const formData = new FormData();
            formData.append('action', 'test');
            formData.append('odoo_url', document.getElementById('odoo_url').value);
            formData.append('odoo_database', document.getElementById('odoo_database').value);
            formData.append('odoo_username', document.getElementById('odoo_username').value);
            formData.append('odoo_api_key', document.getElementById('odoo_api_key').value);

            // Disable button and show loading
            btn.disabled = true;
            btnText.innerHTML = '<div class="spinner"></div> Đang kiểm tra...';
            resultDiv.className = 'test-result';
            resultDiv.innerHTML = '';

            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(response => {
                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    // Check if response is JSON
                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        throw new Error("Server returned non-JSON response. Please check server configuration.");
                    }
                    return response.json();
                })
                .then(data => {
                    btn.disabled = false;
                    btnText.textContent = 'Test kết nối';

                    if (data.success) {
                        resultDiv.className = 'test-result success show';
                        resultDiv.innerHTML = `
                        <div class="test-result-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            ${data.message}
                        </div>
                        <div class="test-result-details">
                            User ID: ${data.user_id}<br>
                            Database: ${data.database}
                        </div>
                    `;
                    } else {
                        resultDiv.className = 'test-result error show';
                        resultDiv.innerHTML = `
                        <div class="test-result-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            Kết nối thất bại
                        </div>
                        <div class="test-result-details">
                            ${data.message}
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btnText.textContent = 'Test kết nối';

                    resultDiv.className = 'test-result error show';
                    resultDiv.innerHTML = `
                    <div class="test-result-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        Lỗi kết nối
                    </div>
                    <div class="test-result-details">
                        ${error.message}
                    </div>
                `;
                });
        }

        function refreshCache() {
            const btn = document.getElementById('btnRefreshCache');
            const btnText = document.getElementById('refreshBtnText');
            const resultDiv = document.getElementById('cacheResult');

            // Disable button
            btn.disabled = true;
            btnText.innerHTML = '<div class="spinner"></div> Đang tải dữ liệu...';
            resultDiv.style.display = 'none';
            resultDiv.className = 'test-result'; // reset class

            fetch('/api/refresh_odoo_cache.php')
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btnText.textContent = 'Làm mới Cache ngay';

                    resultDiv.style.display = 'block';
                    if (data.success) {
                        resultDiv.className = 'test-result success show';
                        resultDiv.innerHTML = `
                             <div class="test-result-header">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Thành công!
                            </div>
                            <div class="test-result-details">
                                ${data.message}
                                <br><small>Tải lại trang để thấy thông tin mới nhất.</small>
                            </div>
                        `;
                        // Auto reload after 2s
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        resultDiv.className = 'test-result error show';
                        resultDiv.textContent = 'Lỗi: ' + (data.error || 'Unknown error');
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btnText.textContent = 'Làm mới Cache ngay';
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'test-result error show';
                    resultDiv.textContent = 'Lỗi kết nối: ' + error.message;
                });
        }
    </script>
    <style>
        .btn-secondary {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #f3f4f6;
            color: #1f2937;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            border-color: #9ca3af;
        }

        .btn-secondary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
    </style>
</body>

</html>