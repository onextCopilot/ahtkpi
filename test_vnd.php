<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();
$odoo->getInvoices(5000, 0);
$odoo_map = $odoo->getInvoiceMap();

$stmt = $conn->prepare("SELECT d.* FROM debts d WHERE invoice_date >= '2026-01-01' AND invoice_date <= '2026-03-31'");
$stmt->execute();
$res = $stmt->get_result();
$tot_hyun = 0;
while($row = $res->fetch_assoc()) {
    $am = $row['am'];
    $oid = $row['odoo_invoice_id'];
    $amount = $row['amount'];
    
    $vnd_value = 0;
    if (!empty($oid) && isset($odoo_map[$oid])) {
        $odoo_inv = $odoo_map[$oid];
        $odoo_total = (float)$odoo_inv['amount_total'];
        $odoo_signed = abs((float)$odoo_inv['amount_total_signed']);
        if ($odoo_total > 0) {
            $vnd_value = $amount * ($odoo_signed / $odoo_total);
        }
    }
    if ($vnd_value <= 0) {
        $curr = $row['currency'];
        $date = $row['invoice_date'];
        $rate = $odoo->getRate($curr, $date);
        $vnd_value = ($rate > 0) ? ($amount / $rate) : $amount;
    }
    
    // We check if am is Hyun C.
    if (strpos($am, 'Hyun') !== false) {
        $tot_hyun += $vnd_value;
    }
}
echo "Tot Hyun: " . $tot_hyun . "\n";
