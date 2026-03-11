<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../config/config.php';

$odoo = new OdooAPI();
$key_account_metadata = [];
$sql = "SELECT cm.odoo_id FROM customers_metadata cm WHERE cm.is_key_account = 1";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $key_account_metadata[(int) $row['odoo_id']] = $row;
}
$invoices_res = $odoo->getInvoices(100000);
$all_invoices = $invoices_res['invoices'];

$total_volume_vnd_by_year = [];
$internal_revenue_vnd_by_year = [];
foreach ($all_invoices as $inv) {
    if ($inv['state'] !== 'posted' || ($inv['move_type'] ?? '') !== 'out_invoice')
        continue;

    $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));
    $date_str = $inv['invoice_date'] ?: ($inv['date'] ?? null);
    if (!$date_str)
        continue;

    $inv_year = date('Y', strtotime($date_str));

    if (($inv['x_studio_invoice_type_1'] ?? '') === 'Internal') {
        if (!isset($internal_revenue_vnd_by_year[$inv_year])) {
            $internal_revenue_vnd_by_year[$inv_year] = 0;
        }
        $internal_revenue_vnd_by_year[$inv_year] += $amount_vnd;
        continue;
    }

    if (!isset($total_volume_vnd_by_year[$inv_year])) {
        $total_volume_vnd_by_year[$inv_year] = 0;
    }
    $total_volume_vnd_by_year[$inv_year] += $amount_vnd;
}

echo "Total Volume By Year:\n";
print_r($total_volume_vnd_by_year);
echo "\nInternal Revenue By Year:\n";
print_r($internal_revenue_vnd_by_year);
