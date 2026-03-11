<?php
require 'config/config.php';
$tables = ['sale_orders', 'debts', 'users'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $res = $conn->query("DESCRIBE $t");
    while ($r = $res->fetch_assoc()) echo json_encode($r)."\n";
}
