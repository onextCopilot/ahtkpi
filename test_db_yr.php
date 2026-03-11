<?php
require 'config/config.php';
$year = (int)date('Y');
$res = $conn->query("SELECT payment_status, SUM(amount) as amt FROM debts WHERE YEAR(invoice_date) = $year GROUP BY payment_status");
while ($r = $res->fetch_assoc()) {
    echo $r['payment_status'] . " : " . $r['amt'] . "\n";
}
