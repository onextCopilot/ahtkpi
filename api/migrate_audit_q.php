<?php
require_once __DIR__ . '/../config/config.php';
$conn->query("ALTER TABLE kpi_audit_logs ADD COLUMN quarter INT DEFAULT 0 AFTER month");
echo "Migration OK - Column 'quarter' added to kpi_audit_logs.";
?>
