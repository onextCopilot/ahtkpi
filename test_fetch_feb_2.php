<?php
require 'config/config.php';
require 'libs/OdooAPI.php';

$odoo = new OdooAPI();
// Authenticate manually to confirm keys
try {
    // We can't call private authenticate(), but refreshInvoiceCache calls it.
    // Let's just try getInvoices directly - it handles auth.
    // Use high offset/limit to ensure we query everything
    $res = $odoo->getInvoices(5000, 0, ['owner_email' => 'hyun@arrowhitech.com']);
    $invoices = $res['invoices'] ?? [];
    echo "Found " . count($invoices) . " total invoices for hyun\n";
    
    $feb = 0;
    foreach($invoices as $inv) {
        $date = $inv['invoice_date'] ?: $inv['date'];
        if ($date && strpos($date, '2026-02') === 0) {
            $feb++;
            echo "FEBRUARY: " . $inv['name'] . " | " . $date . " | " . $inv['amount_total'] . "\n";
        }
    }
    echo "Summary Feb: $feb\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
