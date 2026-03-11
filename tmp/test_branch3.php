<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
$api = new OdooAPI();

$invoices = $api->searchRead('account.move', [['move_type', '=', 'out_invoice']], ['id', 'name', 'invoice_line_ids'], 10, 0);

if (empty($invoices)) {
    die("No invoices");
}

$line_ids = [];
foreach ($invoices as $inv) {
    if (!empty($inv['invoice_line_ids'])) {
        $line_ids = array_merge($line_ids, $inv['invoice_line_ids']);
    }
}

if (empty($line_ids)) {
    die("No lines");
}

$fields_to_try = [
    'x_studio_branch',
    'branch_id',
    'x_branch_id',
    'x_studio_project_code',
    'analytic_account_id',
    'name'
];

$lines = $api->searchRead('account.move.line', [['id', 'in', array_slice($line_ids, 0, 10)]], $fields_to_try, 10, 0);

if (empty($lines)) {
    die("No lines data");
}

print_r($lines);
