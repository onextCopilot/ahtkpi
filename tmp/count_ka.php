<?php
require_once __DIR__ . '/../config/config.php';
$res = $conn->query("SELECT count(*) FROM customers_metadata");
$row = $res->fetch_row();
echo "Count: " . $row[0] . "\n";
$res2 = $conn->query("SELECT count(*) FROM customers_metadata WHERE is_key_account = 1");
$row2 = $res2->fetch_row();
echo "KA Count: " . $row2[0] . "\n";
