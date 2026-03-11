<?php
require 'config/config.php';
require 'libs/OdooAPI.php';

$odoo = new OdooAPI();
$res = $odoo->getInvoices(5000, 0, ['owner_email' => 'hyun@arrowhitech.com']);
$invoices = $res['invoices'];

echo "Found " . count($invoices) . " total for hyun\n";

$feb = 0;
foreach ($invoices as $inv) {
    $date = $inv['invoice_date'] ?: $inv['date'];
    if ($date && strpos($date, '2026-02') === 0) {
        $feb++;
        echo $inv['name'] . " | " . $date . " | " . ($inv['state'] ?? 'no state') . " | " . ($inv['payment_state'] ?? 'no p_state') . "\n";
    }
}
echo "Total Feb: $feb\n";
