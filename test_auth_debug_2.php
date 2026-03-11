<?php
require 'config/config.php';
$result = $conn->query("SELECT * FROM odoo_settings ORDER BY id DESC LIMIT 1");
$settings = $result->fetch_assoc();
echo "Testing with: URL=" . $settings['odoo_url'] . " DB=" . $settings['odoo_database'] . " USER=" . $settings['odoo_username'] . " KEY=" . $settings['odoo_api_key'] . "\n";
$url = rtrim($settings['odoo_url'], '/') . '/jsonrpc';
$data = [
    'jsonrpc' => '2.0',
    'method' => 'call',
    'params' => [
        'service' => 'common',
        'method' => 'authenticate',
        'args' => [$settings['odoo_database'], $settings['odoo_username'], $settings['odoo_api_key'], []]
    ],
    'id' => rand(1,1000)
];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_VERBOSE, true);
$response = curl_exec($ch);
echo "\nRESPONSE: " . $response . "\n";
