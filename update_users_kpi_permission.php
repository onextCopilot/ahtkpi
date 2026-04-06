<?php
require_once __DIR__ . '/config/config.php';

// Add can_view_all_kpi to users table
$sql = "ALTER TABLE users ADD COLUMN can_view_all_kpi TINYINT(1) DEFAULT 0 AFTER role";
if ($conn->query($sql)) {
    echo "Added column can_view_all_kpi successfully.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
