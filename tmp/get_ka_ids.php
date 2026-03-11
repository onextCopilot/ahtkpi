<?php
require_once __DIR__ . '/../config/config.php';
$res = $conn->query("SELECT odoo_id FROM customers_metadata WHERE is_key_account = 1");
$ids = [];
while ($row = $res->fetch_assoc()) {
    $ids[] = $row['odoo_id'];
}
echo implode(',', $ids);
