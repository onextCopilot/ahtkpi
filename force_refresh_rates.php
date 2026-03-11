<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
try {
    $odoo = new OdooAPI();
    echo "Refreshing currency rates cache...\n";
    $count = $odoo->refreshCurrencyRates();
    echo "Done! Found $count rates.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
