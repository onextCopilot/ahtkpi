<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../config/config.php';
$odoo = new OdooAPI();
try {
    $invoices = $odoo->getInvoices(10000, 0); // Get from cache
    $total_2026 = 0;
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
            $total_2026 += abs((float) ($inv['amount_total_signed'] ?? 0));
        }
    }
    echo "Calculated Internal Total for 2026: " . number_format($total_2026, 2) . " VND\n";
    echo "Expected: 991,242,341.00\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
