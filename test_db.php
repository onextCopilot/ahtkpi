<?php
require 'config/config.php';
$res = $conn->query("SELECT payment_status, SUM(amount) as amt FROM debts GROUP BY payment_status");
while ($r = $res->fetch_assoc()) {
    echo $r['payment_status'] . " : " . $r['amt'] . "\n";
}
