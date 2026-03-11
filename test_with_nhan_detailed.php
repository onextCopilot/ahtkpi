<?php
$url = 'https://erp18.merket.io/jsonrpc';
$db = 'ahterp';
$user = 'nhanntt@arrowhitech.com';
$key = '63cae93f38f5509a019be702fd7bdc74eccfa0fb';

function call($service, $method, $args) {
    global $url;
    $data = [
        'jsonrpc' => '2.0',
        'method' => 'call',
        'params' => ['service' => $service, 'method' => $method, 'args' => $args],
        'id' => rand()
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    return json_decode($res, true)['result'];
}

$uid = call('common', 'authenticate', [$db, $user, $key, []]);

$domain = [
    ['move_type', '=', 'out_invoice'],
    ['invoice_date', '>=', '2026-02-01'],
    ['invoice_date', '<=', '2026-02-28']
];

$fields = ['name', 'invoice_date', 'invoice_user_id', 'amount_total', 'payment_state', 'state'];
$invoices = call('object', 'execute_kw', [$db, $uid, $key, 'account.move', 'search_read', [$domain], ['fields' => $fields]]);

echo "ID | NAME | DATE | USER | TOTAL | P_STATE | STATE\n";
$hyun_count = 0;
foreach ($invoices as $inv) {
    $userName = is_array($inv['invoice_user_id']) ? $inv['invoice_user_id'][1] : '';
    if (stripos($userName, 'hyun') !== false) {
        $hyun_count++;
        $userId = is_array($inv['invoice_user_id']) ? $inv['invoice_user_id'][0] : 'N/A';
        echo $userId . " | " . $inv['name'] . " | " . $inv['invoice_date'] . " | " . $userName . " | " . $inv['amount_total'] . " | " . $inv['payment_state'] . " | " . $inv['state'] . "\n";
    }
}
echo "Total: $hyun_count\n";

// Also check the User ID for login hyun@arrowhitech.com
$hyun_user = call('object', 'execute_kw', [$db, $uid, $key, 'res.users', 'search_read', [[['login', '=', 'hyun@arrowhitech.com']]], ['fields' => ['id', 'name']]]);
echo "\nLogin hyun@arrowhitech.com has Odoo ID: " . ($hyun_user[0]['id'] ?? 'NONE') . " Name: " . ($hyun_user[0]['name'] ?? 'NONE') . "\n";
