<?php
require_once __DIR__ . '/../config/config.php';
$conn->query("ALTER TABLE kpi_audit_logs ADD COLUMN kpi_name VARCHAR(255) AFTER kpi_def_id");
echo "Migration OK - Column 'kpi_name' added to kpi_audit_logs.";
?>
