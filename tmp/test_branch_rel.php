<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
$api = new OdooAPI();

$domain = [
    ['move_id.move_type', '=', 'out_invoice'],
    ['branch_id.name', 'ilike', 'bc']
];

try {
    $lines = $api->searchRead('account.move.line', $domain, ['move_id', 'branch_id', 'move_name'], 10, 0);
    print_r($lines);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
