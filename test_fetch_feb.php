<?php
require 'config/config.php';
require 'libs/OdooAPI.php';

$odoo = new OdooAPI();
echo "Refreshing Cache with 180 days window...\n";
$count = $odoo->refreshInvoiceCache();
echo "Total invoices in cache: $count\n";

$res = $odoo->getInvoices(5000, 0, ['owner_email' => 'hyun@arrowhitech.com']);
$invoices = $res['invoices'] ?? [];

$feb_count = 0;
foreach ($invoices as $inv) {
    $date = $inv['invoice_date'] ?: $inv['date'];
    if ($date && strpos($date, '2026-02') === 0) {
        $feb_count++;
        echo $inv['name'] . " | " . $date . " | " . $inv['amount_total'] . "\n";
    }
}
echo "Total Feb Invoices for hyun@arrowhitech.com: $feb_count\n";
