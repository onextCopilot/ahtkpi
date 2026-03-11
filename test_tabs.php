<?php
$_SESSION['user_id'] = 1;
$_SESSION['is_am_bd'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['full_name'] = 'Test';
$_GET['quarter'] = 'Q1_2026';
ob_start();
include 'modules/sale_reports/index.php';
$html = ob_get_clean();
if (strpos($html, '<div class="tabs-container">') !== false) {
    echo "TABS ARE IN HTML\n";
    // echo snippet around tabs
    $pos = strpos($html, '<div class="tabs-container">');
    echo substr($html, $pos, 800);
} else {
    echo "TABS NOT IN HTML\n";
}
