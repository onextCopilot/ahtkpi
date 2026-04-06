<?php
require_once __DIR__ . '/../config/config.php';
$conn->query("
CREATE TABLE IF NOT EXISTS kpi_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kpi_def_id INT,
    year INT,
    month INT,
    old_value VARCHAR(255),
    new_value VARCHAR(255),
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Migration OK - Table kpi_audit_logs created.";
?>
