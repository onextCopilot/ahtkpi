<?php
require 'config/config.php';
$tables = ['debts', 'users', 'user_sale_level_history'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $res = $conn->query("DESCRIBE $t");
    if ($res) {
        while ($r = $res->fetch_assoc())
            echo json_encode($r) . "\n";
    }
}
