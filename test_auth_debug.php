<?php
require 'config/config.php';
$result = $conn->query("SELECT * FROM odoo_settings ORDER BY id DESC LIMIT 1");
$settings = $result->fetch_assoc();
$url = rtrim($settings['odoo_url'], '/') . '/jsonrpc';
$data = [
    'jsonrpc' => '2.0',
    'method' => 'call',
    'params' => [
        'service' => 'common',
        'method' => 'authenticate',
        'args' => [$settings['odoo_database'], $settings['odoo_username'], $settings['odoo_api_key'], []]
    ],
    'id' => 1
];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
echo "RESPONSE: " . $response . "\n";
