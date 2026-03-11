<?php
require_once __DIR__ . '/config/config.php';

// Get all databases
$res = $conn->query("SHOW DATABASES");
$databases = [];
while ($row = $res->fetch_row()) {
    $db = $row[0];
    if (in_array($db, ['information_schema', 'mysql', 'performance_schema', 'sys']))
        continue;
    $databases[] = $db;
}

foreach ($databases as $db) {
    echo "Processing database: $db\n";
    $conn->select_db($db);

    // Check if table exists
    $tables = $conn->query("SHOW TABLES LIKE 'customers_metadata'");
    if ($tables && $tables->num_rows > 0) {
        echo "  - Table customers_metadata found.\n";
        // Try to add the column
        $sql = "ALTER TABLE customers_metadata ADD COLUMN order_index INT DEFAULT 0";
        if ($conn->query($sql)) {
            echo "  - Column 'order_index' added successfully.\n";
        } else {
            if (stripos($conn->error, 'Duplicate column name') !== false) {
                echo "  - Column 'order_index' already exists.\n";
            } else {
                echo "  - Error adding column: " . $conn->error . "\n";
            }
        }
    } else {
        echo "  - Table customers_metadata NOT found.\n";
    }
}

// Specifically for login_system (as per config)
$conn->select_db('login_system');
$conn->query("ALTER TABLE customers_metadata ADD COLUMN IF NOT EXISTS order_index INT DEFAULT 0");
?>