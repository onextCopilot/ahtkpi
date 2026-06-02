<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/OdooAPI.php';

$odoo = new OdooAPI();
$domain = [['id', '=', 6976]];
$fields = [
    'id', 'name', 'amount_total', 'amount_total_signed', 'currency_id'
];
$res = $odoo->searchRead('account.move', $domain, $fields, 1, 0);
print_r($res);
