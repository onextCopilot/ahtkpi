<?php
require 'config/config.php';
require 'libs/OdooAPI.php';

$odoo = new OdooAPI();
$res = $odoo->getInvoices(5000, 0, ['owner_email' => 'hyun@arrowhitech.com']);
$invoices = $res['invoices'];

$feb_total = 0;
$count = 0;
foreach ($invoices as $inv) {
    $date = $inv['invoice_date'] ?: $inv['date'];
    if ($date && strpos($date, '2026-02') === 0) {
        $count++;
        // Use the same logic as in the app now
        $amountVnd = isset($inv['amount_total_signed']) ? abs((float) $inv['amount_total_signed']) : 0;
        $feb_total += $amountVnd;
        echo $inv['name'] . " | " . $date . " | " . $amountVnd . " VND\n";
    }
}
echo "Total Feb Invoices: $count\n";
echo "Total Feb Value: " . number_format($feb_total, 0) . " VND\n";
