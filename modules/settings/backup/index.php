<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/BackupManager.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

$backupManager = new BackupManager($conn);

// Ensure system_settings table exists and has backup keys
$table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($table_check->num_rows == 0) {
    // This should have been created by smtp settings, but just in case
    $create_sql = "CREATE TABLE system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_sql);
}

// Check for backup frequency keys
$backup_defaults = [
    'backup_enabled' => 'off',
    'backup_frequency' => 'daily',
    'backup_last_run' => 'Never',
    'backup_retention' => '30'
];

foreach ($backup_defaults as $key => $val) {
    $check_key = $conn->query("SELECT id FROM system_settings WHERE setting_key = '$key'");
    if ($check_key->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $val);
        $stmt->execute();
    }
}

// Handle Manual Backup
if (isset($_POST['action']) && $_POST['action'] === 'manual_backup') {
    $result = $backupManager->createBackup();
    if ($result['success']) {
        $success_message = "Backup created successfully: " . $result['filename'];
    } else {
        $error_message = "Backup failed: " . $result['message'];
    }
}

// Handle Delete Backup
if (isset($_GET['delete'])) {
    if ($backupManager->deleteBackup($_GET['delete'])) {
        $success_message = "Backup deleted successfully.";
    } else {
        $error_message = "Failed to delete backup.";
    }
}

// Handle Restore Backup
if (isset($_GET['restore'])) {
    $result = $backupManager->restoreBackup($_GET['restore']);
    if ($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}

// Handle Download Backup
if (isset($_GET['download'])) {
    $filename = $_GET['download'];
    $filePath = __DIR__ . '/../../../backups/' . $filename;
    if (file_exists($filePath) && preg_match('/^[a-zA-Z0-9_\.-]+$/', $filename)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit();
    }
}

// Handle Settings Update
if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $settings = [
        'backup_enabled' => isset($_POST['backup_enabled']) ? 'on' : 'off',
        'backup_frequency' => $_POST['backup_frequency'],
        'backup_retention' => $_POST['backup_retention']
    ];

    foreach ($settings as $key => $val) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $val, $key);
        $stmt->execute();
    }
    $success_message = "Backup settings updated successfully.";
}

// Fetch Current Settings
$current_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'backup_%'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$backups = $backupManager->listBackups();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Backup Settings - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .content-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            margin-bottom: 2rem;
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

        .form-group select, .form-group input[type="text"], .form-group input[type="number"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-danger {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .backup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .backup-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid var(--border-light);
            color: var(--text-secondary);
            font-weight: 600;
        }

        .backup-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-enabled { background: #dcfce7; color: #166534; }
        .status-disabled { background: #f3f4f6; color: #4b5563; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Database Autobackup';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div style="margin-bottom:1rem;">
                    <a href="/settings" style="color:var(--text-secondary); text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Settings
                    </a>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="grid" style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem;">
                    <div class="main-column">
                        <!-- Settings Form -->
                        <div class="settings-card">
                            <h2 style="font-size:1.25rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:10px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
                                </svg>
                                Backup Configuration
                            </h2>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_settings">
                                
                                <div class="form-group" style="display:flex; justify-content:space-between; align-items:center; background:#f9fafb; padding:1rem; border-radius:12px;">
                                    <div>
                                        <label style="margin-bottom:0; color:var(--text-primary);">Enable Autobackup</label>
                                        <p style="font-size:0.85rem; color:var(--text-secondary);">Automatically run database backups based on frequency</p>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="backup_enabled" <?php echo ($current_settings['backup_enabled'] ?? 'off') === 'on' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label>Backup Frequency</label>
                                    <select name="backup_frequency">
                                        <option value="hourly" <?php echo ($current_settings['backup_frequency'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Every Hour</option>
                                        <option value="daily" <?php echo ($current_settings['backup_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Once a Day</option>
                                        <option value="weekly" <?php echo ($current_settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Once a Week</option>
                                        <option value="monthly" <?php echo ($current_settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Once a Month</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Retention Policy (Days to keep)</label>
                                    <input type="number" name="backup_retention" value="<?php echo htmlspecialchars($current_settings['backup_retention'] ?? '30'); ?>" min="1" max="365">
                                    <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.5rem;">Number of days to keep backup files before auto-deletion.</p>
                                </div>

                                <div style="display:flex; justify-content:flex-end;">
                                    <button type="submit" class="btn btn-primary">Save Settings</button>
                                </div>
                            </form>
                        </div>

                        <!-- Backup List -->
                        <div class="settings-card">
                            <h2 style="font-size:1.25rem; margin-bottom:1.5rem;">Existing Backups</h2>
                            <div style="overflow-x: auto;">
                                <table class="backup-table">
                                    <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th>Date Created</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($backups)): ?>
                                            <tr>
                                                <td colspan="4" style="text-align:center; padding:2rem; color:var(--text-secondary);">No backups found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($backups as $b): ?>
                                                <tr>
                                                    <td style="font-family:monospace; font-size:0.9rem;"><?php echo htmlspecialchars($b['filename']); ?></td>
                                                    <td><?php echo $b['date']; ?></td>
                                                    <td><?php echo round($b['size'] / 1024 / 1024, 2); ?> MB</td>
                                                    <td>
                                                        <div style="display:flex; gap:0.5rem;">
                                                            <a href="?download=<?php echo urlencode($b['filename']); ?>" class="btn btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.85rem;">
                                                                Download
                                                            </a>
                                                            <a href="?restore=<?php echo urlencode($b['filename']); ?>" class="btn btn-warning" style="padding:0.4rem 0.8rem; font-size:0.85rem;" onclick="return confirm('WARNING: This will overwrite CURRENT database. It is recommended to create a new backup before restoring. Proceed?')">
                                                                Restore
                                                            </a>
                                                            <a href="?delete=<?php echo urlencode($b['filename']); ?>" class="btn btn-danger" style="padding:0.4rem 0.8rem; font-size:0.85rem;" onclick="return confirm('Are you sure you want to delete this backup?')">
                                                                Delete
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-column">
                        <div class="settings-card">
                            <h3 style="font-size:1.1rem; margin-bottom:1rem;">Quick Action</h3>
                            <p style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:1.5rem;">Need a backup right now? Trigger it manually.</p>
                            <form method="POST" style="width:100%;">
                                <input type="hidden" name="action" value="manual_backup">
                                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"></path>
                                    </svg>
                                    Run Backup Now
                                </button>
                            </form>
                        </div>

                        <div class="settings-card" style="background:#f0f9ff; border-color:#bae6fd;">
                            <h3 style="font-size:1rem; color:#0369a1; margin-bottom:0.5rem;">Security Note</h3>
                            <p style="font-size:0.85rem; color:#075985;">Backups are stored in a protected directory and cannot be accessed directly via URL. Only administrators can download them through this interface.</p>
                        </div>

                        <div class="settings-card">
                            <h3 style="font-size:1rem; margin-bottom:1rem;">Statistics</h3>
                            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
                                    <span style="color:var(--text-secondary);">Status:</span>
                                    <span class="status-badge <?php echo ($current_settings['backup_enabled'] ?? 'off') === 'on' ? 'status-enabled' : 'status-disabled'; ?>">
                                        <?php echo ($current_settings['backup_enabled'] ?? 'off') === 'on' ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
                                    <span style="color:var(--text-secondary);">Last Backup:</span>
                                    <span><?php echo $current_settings['backup_last_run'] ?? 'Never'; ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:0.9rem;">
                                    <span style="color:var(--text-secondary);">Total Files:</span>
                                    <span><?php echo count($backups); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
