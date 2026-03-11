<?php
require 'config/config.php';
require 'libs/OdooAPI.php';

// Mock session/user info if needed, but we can just call the methods
$odoo = new OdooAPI();

$email = 'hyun@arrowhitech.com';
$filters = ['owner_email' => $email];
$offset = 0;
$limit = 5000;

echo "--- DEBUGGING INVOICES FOR $email ---\n";
try {
    $result = $odoo->getInvoices($limit, $offset, $filters);
    $invoices = $result['invoices'];
    echo "Total returned by getInvoices: " . count($invoices) . "\n";
    
    $feb = 0;
    foreach ($invoices as $inv) {
        $date = $inv['invoice_date'] ?: $inv['date'];
        if ($date && strpos($date, '2026-02') === 0) {
            $feb++;
            echo $inv['name'] . " | " . $date . " | State: " . $inv['state'] . " | Total: " . $inv['amount_total'] . "\n";
        }
    }
    echo "Summary Feb: $feb\n";
    
    // Check Odoo User ID resolution
    $uid = $odoo->getOdooUserId($email);
    echo "Resolved Odoo User ID: " . ($uid ?? 'NULL') . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
