<?php
require_once __DIR__ . '/config/config.php';
echo "Current DB: " . DB_NAME . "\n";
$conn->query("USE " . DB_NAME);

$sql = "ALTER TABLE customers_metadata ADD COLUMN order_index INT DEFAULT 0";
if ($conn->query($sql)) {
    echo "Column order_index added successfully.\n";
} else {
    echo "Error: " . $conn->error . "\n";
    // Check if it already exists
    $res = $conn->query("SHOW COLUMNS FROM customers_metadata LIKE 'order_index'");
    if ($res->num_rows > 0) {
        echo "Column order_index already exists.\n";
    }
}
?>