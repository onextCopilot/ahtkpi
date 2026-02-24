<?php
require_once __DIR__ . '/libs/OdooAPI.php';

$odoo = new OdooAPI();
try {
    echo "Refreshing cache...\n";
    $count = $odoo->refreshInvoiceCache();
    echo "Refreshed $count invoices.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
