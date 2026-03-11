<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();

$reflector = new ReflectionClass('OdooAPI');
$method = $reflector->getMethod('searchRead');
$method->setAccessible(true);

$domain = [['invoice_user_id', '=', 117], ['invoice_date', '>=', '2026-02-01'], ['invoice_date', '<=', '2026-02-28']];
$fields = ['name', 'currency_id', 'amount_total', 'amount_total_signed', 'invoice_date'];
$invoices = $method->invoke($odoo, 'account.move', $domain, $fields, 20, 0);

echo "NAME | DATE | CURR | TOTAL | SIGNED (VND?)\n";
foreach ($invoices as $inv) {
    echo $inv['name'] . " | " . $inv['invoice_date'] . " | " . $inv['currency_id'][1] . " | " . $inv['amount_total'] . " | " . $inv['amount_total_signed'] . "\n";
}
