<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
try {
    $odoo = new OdooAPI();
    echo "Refreshing invoice cache...\n";
    $count = $odoo->refreshInvoiceCache();
    echo "Done! Found $count invoices.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
