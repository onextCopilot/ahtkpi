<?php
require_once __DIR__ . '/../../../config/config.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;

// Config file path
$configFile = __DIR__ . '/../../../config/jira_config.json';
$message = '';
$messageType = '';

// Load existing config
$config = [
    'jira_url' => 'https://cyclethis.com/',
    'jira_email' => '',
    'jira_token' => ''
];

if (file_exists($configFile)) {
    $savedConfig = json_decode(file_get_contents($configFile), true);
    if (is_array($savedConfig)) {
        $config = array_merge($config, $savedConfig);
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        $newConfig = [
            'jira_url' => rtrim($_POST['jira_url'], '/'),
            'jira_email' => trim($_POST['jira_email']),
            'jira_token' => trim($_POST['jira_token'])
        ];

        // Save to file
        if (file_put_contents($configFile, json_encode($newConfig, JSON_PRETTY_PRINT))) {
            $config = $newConfig;
            $message = "Đã lưu cấu hình Jira thành công!";
            $messageType = "success";
        } else {
            $message = "Không thể lưu file cấu hình. Vui lòng kiểm tra quyền ghi.";
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
    <title>Cấu hình Jira - AHT KPI</title>
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

        .btn-secondary {
            background: #fff;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
            color: #0f172a;
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

        .test-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 6px;
            display: none;
            font-size: 0.9rem;
        }

        .test-result.success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            display: block;
        }

        .test-result.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            display: block;
        }

        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
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
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $page_title = 'Cấu hình hệ thống';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="settings-container">



                <div class="settings-card">
                    <div class="settings-header">
                        <h2>Kết nối Jira Software</h2>
                        <p>Nhập thông tin xác thực để kết nối với Jira (cyclethis.com)</p>
                    </div>

                    <div class="settings-body">
                        <?php if ($message): ?>
                            <div
                                class="alert <?php echo ($messageType === 'success') ? 'alert-success' : 'alert-error'; ?>">
                                <?php if ($messageType === 'success'): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="jiraForm">
                            <input type="hidden" name="action" value="save_config">

                            <div class="form-group">
                                <label for="jira_url">Jira URL</label>
                                <input type="url" id="jira_url" name="jira_url" class="form-control"
                                    value="<?php echo htmlspecialchars($config['jira_url']); ?>" required
                                    placeholder="https://cyclethis.com/">
                                <small style="color: #64748b; display: block; margin-top: 5px;">Ví dụ:
                                    https://cyclethis.com/ hoặc https://your-domain.atlassian.net</small>
                            </div>

                            <div class="form-group">
                                <label for="jira_email">Email / Username</label>
                                <input type="text" id="jira_email" name="jira_email" class="form-control"
                                    value="<?php echo htmlspecialchars($config['jira_email']); ?>" required
                                    placeholder="user@example.com hoặc username">
                            </div>

                            <div class="form-group">
                                <label for="jira_token">API Token / Password</label>
                                <input type="password" id="jira_token" name="jira_token" class="form-control"
                                    value="<?php echo htmlspecialchars($config['jira_token']); ?>" required
                                    placeholder="Nhập API Token hoặc Password của bạn">
                                <small style="color: #64748b; display: block; margin-top: 5px;">
                                    Với Jira Cloud, hãy tạo <a href="https://id.atlassian.com/manage/api-tokens"
                                        target="_blank" style="color:#2563eb">API Token tại đây</a>.
                                    Với Jira Server, dùng mật khẩu hoặc Personal Access Token.
                                </small>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 2rem;">
                                <button type="submit" class="btn btn-primary">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z">
                                        </path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    Lưu cấu hình
                                </button>

                                <button type="button" class="btn btn-secondary" onclick="testConnection()" id="btnTest">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                    </svg>
                                    <span id="testBtnText">Kiểm tra kết nối</span>
                                </button>
                            </div>
                        </form>

                        <div id="testResult" class="test-result"></div>

                    </div>
                </div>

                <!-- Cache Management Section -->
                <div class="settings-card" style="margin-top: 2rem;">
                    <div class="settings-header">
                        <h2>Cache Projects</h2>
                        <p>Danh sách Project từ Jira được lưu trữ trong 24h để hiển thị ở các form tạo issue/worklog.
                        </p>
                    </div>
                    <div class="settings-body">
                        <div class="form-group">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <strong>Trạng thái Cache:</strong>
                                    <?php
                                    $cacheFile = __DIR__ . '/../../../cache/jira_projects.cache.php';
                                    if (file_exists($cacheFile)) {
                                        $cacheTime = date("H:i d/m/Y", filemtime($cacheFile));
                                        $cacheSize = round(filesize($cacheFile) / 1024, 2) . ' KB';

                                        // Read count
                                        $content = file_get_contents($cacheFile);
                                        $json = str_replace('<?php exit; ?>', '', $content);
                                        $projects = json_decode($json, true);
                                        $count = is_array($projects) ? count($projects) : 0;

                                        echo "<span style='color: #166534;'>Đã cache ($count projects, $cacheSize) - Cập nhật: $cacheTime</span>";
                                    } else {
                                        echo "<span style='color: #ea580c;'>Chưa có cache</span>";
                                    }
                                    ?>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="refreshCache()"
                                    id="btnRefreshCache">
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
            </div>
        </main>
    </div>

    <script>
        function testConnection() {
            // Existing testConnection code...
            const btn = document.getElementById('btnTest');
            const btnText = document.getElementById('testBtnText');
            const resultDiv = document.getElementById('testResult');

            btn.disabled = true;
            btnText.innerHTML = '<div class="spinner"></div> Đang kết nối...';
            resultDiv.style.display = 'none';
            resultDiv.className = 'test-result';

            const formData = new FormData(document.getElementById('jiraForm'));

            fetch('/api/test_jira.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btnText.textContent = 'Kiểm tra kết nối';

                    resultDiv.style.display = 'block';
                    if (data.success) {
                        resultDiv.className = 'test-result success';
                        resultDiv.innerHTML = `
                         <strong>Kết nối thành công!</strong><br>
                         Server Jira: ${data.info.serverTitle}<br>
                         Phiên bản: ${data.info.version}
                    `;
                    } else {
                        resultDiv.className = 'test-result error';
                        resultDiv.textContent = 'Lỗi kết nối: ' + (data.error || 'Unknown error');
                    }
                })
                .catch(error => {
                    console.error('Test connection error:', error);
                    btn.disabled = false;
                    btnText.textContent = 'Kiểm tra kết nối';
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'test-result error';
                    resultDiv.textContent = 'Lỗi hệ thống: ' + error.message;
                });
        }

        function refreshCache() {
            const btn = document.getElementById('btnRefreshCache');
            const btnText = document.getElementById('refreshBtnText');
            const resultDiv = document.getElementById('cacheResult');

            // Disable button
            btn.disabled = true;
            const originalText = btnText.textContent;
            btnText.innerHTML = 'Đang tải...';
            resultDiv.style.display = 'none';
            resultDiv.className = 'alert'; // Reset class

            fetch('/api/refresh_jira_cache.php')
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btnText.textContent = originalText;

                    resultDiv.style.display = 'block';
                    if (data.success) {
                        resultDiv.className = 'alert alert-success';
                        resultDiv.innerHTML = `<strong>Thành công!</strong> ${data.message} <br>Tự động tải lại trang sau 2s...`;
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        resultDiv.className = 'alert alert-error';
                        resultDiv.textContent = 'Lỗi: ' + (data.error || 'Unknown error');
                    }
                })
                .catch(error => {
                    console.error('Refresh cache error:', error);
                    btn.disabled = false;
                    btnText.textContent = originalText;
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'alert alert-error';
                    resultDiv.textContent = 'Lỗi hệ thống: ' + error.message;
                });
        }
    </script>
</body>

</html>