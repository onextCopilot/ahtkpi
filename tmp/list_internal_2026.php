<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../config/config.php';
$odoo = new OdooAPI();
try {
    $invoices = $odoo->getInvoices(10000, 0);
    foreach ($invoices['invoices'] as $inv) {
        if (($inv['state'] ?? '') !== 'posted')
            continue;
        if (($inv['move_type'] ?? '') !== 'out_invoice')
            continue;

        $date_str = $inv['invoice_date'] ?: ($inv['date'] ?? null);
        if (!$date_str)
            continue;
        $inv_year = date('Y', strtotime($date_str));

        if ($inv_year === '2026' && ($inv['x_studio_invoice_type_1'] ?? '') === 'Internal') {
            echo "ID: " . $inv['id'] . " | Name: " . $inv['name'] . " | Signed: " . $inv['amount_total_signed'] . " | Total: " . $inv['amount_total'] . " | Currency: " . $inv['currency_id'][1] . " | Partner: " . $inv['partner_id'][1] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
