<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
$api = new OdooAPI();

$invoices = $api->searchRead('account.move', [['move_type', '=', 'out_invoice']], ['id', 'name', 'invoice_line_ids'], 5, 0);

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

$lines = $api->searchRead('account.move.line', [['id', 'in', array_slice($line_ids, 0, 5)]], [], 5, 0);

if (empty($lines)) {
    die("No lines data");
}

// Just output the keys of the first line to see what fields exist
echo implode("\n", array_keys($lines[0]));

// specifically look for branch
foreach (array_keys($lines[0]) as $key) {
    if (stripos($key, 'branch') !== false) {
        echo "\nFound branch field: " . $key;
    }
    if (stripos($key, 'bc') !== false) {
        echo "\nFound bc field: " . $key;
    }
}
