<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['is_am_bd'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Test';
$_GET['quarter'] = 'Q1_2026';

ob_start();
include 'modules/sale_reports/index.php';
$output = ob_get_clean();

if (strpos($output, 'tabs-container') !== false) {
    echo "Found tabs-container!\n";
} else {
    echo "NO tabs-container found!\n";
}
echo "Length of output: " . strlen($output) . "\n";
