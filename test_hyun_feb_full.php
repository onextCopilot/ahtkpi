<?php
$url = 'https://erp18.merket.io/jsonrpc';
$db = 'ahterp';
$user = 'nhanntt@arrowhitech.com';
$key = '63cae93f38f5509a019be702fd7bdc74eccfa0fb';

function call($service, $method, $args) {
    global $url;
    $data = ['jsonrpc' => '2.0', 'method' => 'call', 'params' => ['service' => $service, 'method' => $method, 'args' => $args], 'id' => rand()];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    return json_decode($res, true)['result'];
}

$uid = call('common', 'authenticate', [$db, $user, $key, []]);

// Search for Hyun's user ID
$hyun_user = call('object', 'execute_kw', [$db, $uid, $key, 'res.users', 'search_read', [[['login', '=', 'hyun@arrowhitech.com']]], ['fields' => ['id']]]);
$hyun_id = $hyun_user[0]['id'];

// Search ALL invoices where salesperson is Hyun in Feb 2026
$domain = [
    ['move_type', '=', 'out_invoice'],
    ['invoice_user_id', '=', $hyun_id],
    '|',
    '&', ['invoice_date', '>=', '2026-02-01'], ['invoice_date', '<=', '2026-02-28'],
    '&', ['date', '>=', '2026-02-01'], ['date', '<=', '2026-02-28']
];

$fields = ['name', 'invoice_date', 'date', 'amount_total', 'payment_state', 'state'];
$invoices = call('object', 'execute_kw', [$db, $uid, $key, 'account.move', 'search_read', [$domain], ['fields' => $fields]]);

echo "Total invoices found: " . count($invoices) . "\n";
echo "NAME | INV_DATE | ACC_DATE | TOTAL | P_STATE | STATE\n";
foreach ($invoices as $inv) {
    echo ($inv['name'] ?: '[DRAFT]') . " | " . ($inv['invoice_date'] ?: 'null') . " | " . ($inv['date'] ?: 'null') . " | " . $inv['amount_total'] . " | " . $inv['payment_state'] . " | " . $inv['state'] . "\n";
}
