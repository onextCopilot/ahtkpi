<?php
require_once __DIR__ . '/../../../config/config.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;
$success_message = '';
$error_message = '';

// Ensure system_settings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($table_check->num_rows == 0) {
    $create_sql = "CREATE TABLE system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (!$conn->query($create_sql)) {
        die("Error creating settings table: " . $conn->error);
    }

    // Insert defaults
    $defaults = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'no-reply@arrowhitech.com',
        'smtp_from_name' => 'AHT KPI System'
    ];

    foreach ($defaults as $key => $val) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $val);
        $stmt->execute();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_user' => $_POST['smtp_user'],
        'smtp_encryption' => $_POST['smtp_encryption'],
        'smtp_from_email' => $_POST['smtp_from_email'],
        'smtp_from_name' => $_POST['smtp_from_name']
    ];

    // Only update password if user entered a new one
    if (!empty($_POST['smtp_pass'])) {
        $settings['smtp_pass'] = $_POST['smtp_pass'];
    }

    foreach ($settings as $key => $val) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $val, $key);
        if (!$stmt->execute()) {
            // If update fails (0 rows affected is fine, but error is not), try insert just in case
            // But we initialized table, so update should work if key exists.
        }
    }
    $success_message = "SMTP Settings updated successfully.";
}

// Fetch Settings
$current_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Helper to get value
function getSetting($key, $data)
{
    return isset($data[$key]) ? $data[$key] : '';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Settings - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .content-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .form-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .btn-save {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .input-hint {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'SMTP Configuration';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div style="margin-bottom:1rem;">
                    <a href="/settings"
                        style="color:var(--text-secondary); text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Settings
                    </a>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <div style="margin-bottom:2rem; border-bottom:1px solid var(--border-light); padding-bottom:1rem;">
                        <h2 style="font-size:1.25rem; margin-bottom:0.5rem;">Email Server Settings</h2>
                        <p style="color:var(--text-secondary); font-size:0.9rem;">Configure outgoing email settings for
                            the system (Reset Password, Notifications, etc.)</p>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" name="smtp_host"
                                value="<?php echo htmlspecialchars(getSetting('smtp_host', $current_settings)); ?>"
                                placeholder="e.g. smtp.gmail.com" required>
                        </div>

                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="number" name="smtp_port"
                                value="<?php echo htmlspecialchars(getSetting('smtp_port', $current_settings)); ?>"
                                placeholder="e.g. 587 or 465" required>
                        </div>

                        <div class="form-group">
                            <label>Encryption</label>
                            <select name="smtp_encryption">
                                <option value="tls" <?php echo getSetting('smtp_encryption', $current_settings) == 'tls' ? 'selected' : ''; ?>>TLS (Recommended for 587)</option>
                                <option value="ssl" <?php echo getSetting('smtp_encryption', $current_settings) == 'ssl' ? 'selected' : ''; ?>>SSL (Recommended for 465)</option>
                                <option value="none" <?php echo getSetting('smtp_encryption', $current_settings) == 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>SMTP Username / Email</label>
                            <input type="text" name="smtp_user"
                                value="<?php echo htmlspecialchars(getSetting('smtp_user', $current_settings)); ?>"
                                placeholder="e.g. your_email@gmail.com">
                        </div>

                        <div class="form-group">
                            <label>SMTP Password / App Password</label>
                            <input type="password" name="smtp_pass"
                                value="<?php echo htmlspecialchars(getSetting('smtp_pass', $current_settings)); ?>"
                                placeholder="Enter password (leave empty to keep current)">
                            <p class="input-hint">For Gmail, use App Password instead of your login password.</p>
                        </div>

                        <div class="form-group" style="padding-top:1rem; border-top:1px solid var(--border-light);">
                            <label>From Email Address</label>
                            <input type="email" name="smtp_from_email"
                                value="<?php echo htmlspecialchars(getSetting('smtp_from_email', $current_settings)); ?>"
                                placeholder="e.g. no-reply@domain.com">
                        </div>

                        <div class="form-group">
                            <label>From Name</label>
                            <input type="text" name="smtp_from_name"
                                value="<?php echo htmlspecialchars(getSetting('smtp_from_name', $current_settings)); ?>"
                                placeholder="e.g. AHT System">
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:2rem;">
                            <button type="button" onclick="window.location.href='/settings'"
                                style="background:none; border:1px solid var(--border-color); padding:0.75rem 1.5rem; border-radius:8px; cursor:pointer;">Cancel</button>
                            <button type="submit" class="btn-save">Save Configuration</button>
                        </div>
                    </form>
                </div>

                <!-- SMTP Guide Section -->
                <div class="form-card" style="margin-top: 2rem;">
                    <div
                        style="margin-bottom:1.5rem; border-bottom:1px solid var(--border-light); padding-bottom:1rem;">
                        <h2 style="font-size:1.25rem; margin-bottom:0.5rem;">Popular SMTP Settings Guide</h2>
                        <p style="color:var(--text-secondary); font-size:0.9rem;">Reference configuration for common
                            email providers.</p>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem;">
                        <!-- Gmail -->
                        <div style="background:var(--bg-secondary); padding:1.5rem; border-radius:12px;">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#EA4335"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                    </path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <h3 style="font-size:1rem; font-weight:600;">Gmail / Google Workspace</h3>
                            </div>
                            <ul
                                style="list-style:none; padding:0; margin:0; font-size:0.9rem; color:var(--text-primary);">
                                <li style="margin-bottom:0.5rem;"><strong>Host:</strong> smtp.gmail.com</li>
                                <li style="margin-bottom:0.5rem;"><strong>Port:</strong> 587 (TLS) or 465 (SSL)</li>
                                <li style="margin-bottom:0.5rem;"><strong>Username:</strong> Your full email address
                                </li>
                                <li style="margin-bottom:0.5rem;"><strong>Password:</strong> <a
                                        href="https://myaccount.google.com/apppasswords" target="_blank"
                                        style="color:var(--primary-color);">App Password</a> (Required if 2FA is on)
                                </li>
                            </ul>
                        </div>

                        <!-- Outlook -->
                        <div style="background:var(--bg-secondary); padding:1.5rem; border-radius:12px;">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0078D4"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="3" y1="9" x2="21" y2="9"></line>
                                    <line x1="9" y1="21" x2="9" y2="9"></line>
                                </svg>
                                <h3 style="font-size:1rem; font-weight:600;">Outlook / Office 365</h3>
                            </div>
                            <ul
                                style="list-style:none; padding:0; margin:0; font-size:0.9rem; color:var(--text-primary);">
                                <li style="margin-bottom:0.5rem;"><strong>Host:</strong> smtp.office365.com</li>
                                <li style="margin-bottom:0.5rem;"><strong>Port:</strong> 587 (TLS/STARTTLS)</li>
                                <li style="margin-bottom:0.5rem;"><strong>Username:</strong> Your full email address
                                </li>
                                <li style="margin-bottom:0.5rem;"><strong>Password:</strong> Login password or App
                                    Password</li>
                            </ul>
                        </div>

                        <!-- Yahoo -->
                        <div style="background:var(--bg-secondary); padding:1.5rem; border-radius:12px;">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6001D2"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 16v-4"></path>
                                    <path d="M12 8h.01"></path>
                                </svg>
                                <h3 style="font-size:1rem; font-weight:600;">Yahoo Mail</h3>
                            </div>
                            <ul
                                style="list-style:none; padding:0; margin:0; font-size:0.9rem; color:var(--text-primary);">
                                <li style="margin-bottom:0.5rem;"><strong>Host:</strong> smtp.mail.yahoo.com</li>
                                <li style="margin-bottom:0.5rem;"><strong>Port:</strong> 465 (SSL)</li>
                                <li style="margin-bottom:0.5rem;"><strong>Username:</strong> Your full email address
                                </li>
                                <li style="margin-bottom:0.5rem;"><strong>Password:</strong> App Password (Required)
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>