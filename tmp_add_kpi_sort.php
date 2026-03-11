<?php
require 'config/config.php';

$res = $conn->query("SHOW COLUMNS FROM kpi_definitions LIKE 'sort_order'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE kpi_definitions ADD COLUMN sort_order INT DEFAULT 0 AFTER kpi_name");
    echo "Added sort_order.\n";
}

$res = $conn->query("SHOW COLUMNS FROM kpi_definitions LIKE 'group_order'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE kpi_definitions ADD COLUMN group_order INT DEFAULT 0 AFTER kpi_group");
    echo "Added group_order.\n";
}

echo "Done.";
