<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../config/config.php';
$odoo = new OdooAPI();
try {
    $invoices = $odoo->getInvoices(10000, 0);
    $total_v = 0;
    $total_i = 0;
    foreach ($invoices['invoices'] as $inv) {
        if (($inv['state'] ?? '') !== 'posted')
            continue;
        if (($inv['move_type'] ?? '') !== 'out_invoice')
            continue;

        $date_str = $inv['invoice_date'] ?: ($inv['date'] ?? null);
        if (!$date_str)
            continue;
        $inv_year = date('Y', strtotime($date_str));

        if ($inv_year === '2026') {
            $val = abs((float) ($inv['amount_total_signed'] ?? 0));
            if (($inv['x_studio_invoice_type_1'] ?? '') === 'Internal') {
                $total_i += $val;
            } else {
                $total_v += $val;
            }
        }
    }
    echo "2026 SUMMARY:\n";
    echo "Total Volume (Excl Internal): " . number_format($total_v, 2) . " VND\n";
    echo "Total Internal: " . number_format($total_i, 2) . " VND\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
