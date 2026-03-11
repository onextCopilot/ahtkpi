<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();
$year = (int)date('Y');
$res = $conn->query("SELECT amount, currency, payment_status, invoice_date FROM debts WHERE YEAR(invoice_date) = $year");
$total_paid = 0;
$total_pending = 0;
while ($row = $res->fetch_assoc()) {
    $curr = $row['currency'] ?: 'USD';
    $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
    $rate = $odoo->getRate($curr, $date);
    $vnd_value = ($rate > 0) ? ((float)$row['amount'] / $rate) : (float)$row['amount'];
    
    // BUT what about invoice_status_class? Does dashboard ignore 'Hủy' or 'PP'?
    if (strcasecmp(trim($row['payment_status']), 'Paid') === 0) {
        $total_paid += $vnd_value;
    } else {
        $total_pending += $vnd_value;
    }
}
echo "Total Paid: " . number_format($total_paid, 0) . "\n";
echo "Total Pending: " . number_format($total_pending, 0) . "\n";
