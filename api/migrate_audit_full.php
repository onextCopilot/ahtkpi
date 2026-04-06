<?php
require_once __DIR__ . '/../config/config.php';

// 1. Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS kpi_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kpi_def_id INT,
    kpi_name VARCHAR(255),
    year INT,
    month INT,
    quarter INT DEFAULT 0,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Ensure all columns exist (for cases where table already existed but was partial)
$cols = [
    'kpi_name' => "VARCHAR(255) AFTER kpi_def_id",
    'quarter' => "INT DEFAULT 0 AFTER month",
    'field_name' => "VARCHAR(100) AFTER quarter"
];

foreach ($cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM kpi_audit_logs LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE kpi_audit_logs ADD COLUMN $col $def");
    }
}

// 3. Fix: change old_value, new_value to TEXT to avoid truncation if needed
$conn->query("ALTER TABLE kpi_audit_logs MODIFY COLUMN old_value TEXT");
$conn->query("ALTER TABLE kpi_audit_logs MODIFY COLUMN new_value TEXT");

echo "Double Migration OK - kpi_audit_logs table and all columns are ready.";
?>
