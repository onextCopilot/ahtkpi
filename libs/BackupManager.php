<?php

class BackupManager {
    private $conn;
    private $backupDir;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->backupDir = __DIR__ . '/../backups/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        } else {
            // Try to fix permissions if already exists
            @chmod($this->backupDir, 0777);
        }
    }

    /**
     * Create a database backup
     * @return array [success => bool, message => string, filename => string]
     */
    public function createBackup() {
        if (!is_writable($this->backupDir)) {
            return [
                'success' => false,
                'message' => 'Backup directory is not writable. Please check folder permissions (chmod 775 or 777 backups).'
            ];
        }

        $dbName = DB_NAME;
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$dbName}_{$timestamp}.sql";
        $filePath = $this->backupDir . $filename;

        try {
            $content = "-- AHT KPI Management System Database Backup\n";
            $content .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
            $content .= "-- Database: " . $dbName . "\n\n";
            $content .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $content .= "SET time_zone = \"+00:00\";\n\n";

            // Get all tables
            $tables = [];
            $result = $this->conn->query("SHOW TABLES");
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }

            foreach ($tables as $table) {
                // Table structure
                $result = $this->conn->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch_row();
                $content .= "\n\n-- Table structure for table `$table` --\n";
                $content .= "DROP TABLE IF EXISTS `$table`;\n";
                $content .= $row[1] . ";\n\n";

                // Table data
                $result = $this->conn->query("SELECT * FROM `$table`");
                $numFields = $result->field_count;

                $content .= "-- Dumping data for table `$table` --\n";
                
                while ($row = $result->fetch_row()) {
                    $content .= "INSERT INTO `$table` VALUES(";
                    for ($i = 0; $i < $numFields; $i++) {
                        if (isset($row[$i])) {
                            $val = $this->conn->real_escape_string($row[$i]);
                            $content .= '"' . $val . '"';
                        } else {
                            $content .= 'NULL';
                        }
                        if ($i < ($numFields - 1)) {
                            $content .= ',';
                        }
                    }
                    $content .= ");\n";
                }
                $content .= "\n\n";
            }

            $content .= "SET FOREIGN_KEY_CHECKS=1;\n";

            file_put_contents($filePath, $content);

            // Record in settings/log
            $this->updateLastBackupTime();

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => $filename,
                'size' => filesize($filePath)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }

    private function updateLastBackupTime() {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'backup_last_run'");
        if ($stmt) {
            $stmt->bind_param("s", $now);
            $stmt->execute();
        }
    }

    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . "*.sql");
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by date desc
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backups;
    }

    public function deleteBackup($filename) {
        // Security check: only allow alphanumeric, underscores, dots, and dashes
        if (preg_match('/^[a-zA-Z0-9_\.-]+$/', $filename)) {
            $filePath = $this->backupDir . $filename;
            if (file_exists($filePath) && strpos(realpath($filePath), realpath($this->backupDir)) === 0) {
                return unlink($filePath);
            }
        }
        return false;
    }
}
