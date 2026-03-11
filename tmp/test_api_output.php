<?php
// Mocking the environment
$_SESSION['user_id'] = 1;
$_SESSION['can_view_invoice'] = 1;
ob_start();
require_once __DIR__ . '/../api/key_accounts_stats.php';
$output = ob_get_clean();
$data = json_decode($output, true);
echo "Internal Revenue Logic Check:\n";
print_r($data['internal_revenue_vnd_by_year'] ?? 'NOT FOUND');
?>