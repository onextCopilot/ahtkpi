<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
$api = new OdooAPI();

$invoices = $api->searchRead('account.move', [['move_type', '=', 'out_invoice']], ['id', 'invoice_line_ids'], 10, 0);
$line_ids = [];
foreach ($invoices as $inv) {
    if (!empty($inv['invoice_line_ids'])) {
        $line_ids = array_merge($line_ids, $inv['invoice_line_ids']);
    }
}
$line_ids = array_slice($line_ids, 0, 5);

$fields_to_try = [
    'branch_id',
    'x_branch_id',
    'x_studio_branch_1',
    'x_studio_branch',
    'analytic_account_id', // this might contain the branch?
];

foreach ($fields_to_try as $field) {
    echo "Trying $field...\n";
    try {
        $lines = $api->searchRead('account.move.line', [['id', 'in', $line_ids]], ['id', 'name', $field], 5, 0);
        echo "SUCCESS WITH: $field\n";
        print_r($lines);
        break; // stop when we find one that works
    } catch (Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}
