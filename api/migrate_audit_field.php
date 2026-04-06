<?php
require_once __DIR__ . '/../config/config.php';
$conn->query("ALTER TABLE kpi_audit_logs ADD COLUMN field_name VARCHAR(100) DEFAULT 'actual_value' AFTER quarter");
echo "Migration OK - Column 'field_name' added to kpi_audit_logs.";
?>
