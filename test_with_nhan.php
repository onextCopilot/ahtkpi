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
        'params' => [
            'service' => $service,
            'method' => $method,
            'args' => $args
        ],
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

echo "Authenticating as nhanntt...\n";
$uid = call('common', 'authenticate', [$db, $user, $key, []]);
if (!$uid) {
    die("Auth failed for nhan\n");
}
echo "Auth success, UID: $uid\n";

echo "Searching invoices for hyun@arrowhitech.com in Feb 2026...\n";
// Odoo domain: invoice_user_id or owner? Usually it's 'invoice_user_id' in account.move
// But first let's see what invoices hyun has
$domain = [
    ['move_type', '=', 'out_invoice'],
    ['invoice_date', '>=', '2026-02-01'],
    ['invoice_date', '<=', '2026-02-28']
];

$fields = ['name', 'invoice_date', 'invoice_user_id', 'amount_total', 'payment_state'];
$invoices = call('object', 'execute_kw', [$db, $uid, $key, 'account.move', 'search_read', [$domain], ['fields' => $fields]]);

$hyun_count = 0;
foreach ($invoices as $inv) {
    // Check if the invoice user is hyun
    $userName = is_array($inv['invoice_user_id']) ? $inv['invoice_user_id'][1] : '';
    // hyun's name might be "Hyun cao" or similar. Let's look for substring.
    if (stripos($userName, 'hyun') !== false) {
        $hyun_count++;
        echo $inv['name'] . " | " . $inv['invoice_date'] . " | User: " . $userName . " | " . $inv['amount_total'] . "\n";
    }
}
echo "Total Feb invoices found for hyun-related user: $hyun_count\n";
echo "Total invoices in Feb for all users: " . count($invoices) . "\n";
