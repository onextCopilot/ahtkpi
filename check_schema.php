<?php
require_once __DIR__ . '/config/config.php';

$tables = ['customers_metadata', 'key_account_metadata'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error or table does not exist: " . $conn->error . "\n";
    }
}
?>