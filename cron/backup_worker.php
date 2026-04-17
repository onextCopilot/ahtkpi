<?php
/**
 * Database Auto Backup Worker
 * This script should be triggered by a system cron job (e.g., every hour)
 * Example: 0 * * * * php /path/to/project/cron/backup_worker.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/BackupManager.php';

// Prevent web access
if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("Access denied. CLI only.");
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Settings
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'backup_%'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Check if backup is enabled
if (($settings['backup_enabled'] ?? 'off') !== 'on') {
    echo "Autobackup is disabled.\n";
    exit();
}

$frequency = $settings['backup_frequency'] ?? 'daily';
$lastRun = $settings['backup_last_run'] ?? null;
$shouldRun = false;

if (!$lastRun || $lastRun === 'Never') {
    $shouldRun = true;
} else {
    $lastTimestamp = strtotime($lastRun);
    $currentTime = time();
    $diff = $currentTime - $lastTimestamp;

    switch ($frequency) {
        case 'hourly':
            if ($diff >= 3600) $shouldRun = true;
            break;
        case 'daily':
            if ($diff >= 86400) $shouldRun = true;
            break;
        case 'weekly':
            if ($diff >= 604800) $shouldRun = true;
            break;
        case 'monthly':
            if ($diff >= 2592000) $shouldRun = true; // Approx 30 days
            break;
    }
}

if ($shouldRun) {
    echo "Running backup (Frequency: $frequency)...\n";
    $backupManager = new BackupManager($conn);
    $result = $backupManager->createBackup();
    
    if ($result['success']) {
        echo "Backup successful: " . $result['filename'] . "\n";
        
        // Retention cleanup
        $retentionDays = intval($settings['backup_retention'] ?? 30);
        $backups = $backupManager->listBackups();
        foreach ($backups as $b) {
            $fileTime = strtotime($b['date']);
            if ((time() - $fileTime) > ($retentionDays * 86400)) {
                echo "Deleting old backup: " . $b['filename'] . "\n";
                $backupManager->deleteBackup($b['filename']);
            }
        }
    } else {
        echo "Backup failed: " . $result['message'] . "\n";
    }
} else {
    echo "Not time to run backup yet. Last run: $lastRun\n";
}
