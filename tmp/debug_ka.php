<?php
require_once __DIR__ . '/../config/config.php';
$res = $conn->query("SELECT * FROM customers_metadata LIMIT 10");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
