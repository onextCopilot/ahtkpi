<?php
require 'config/config.php';
$result = $conn->query("SELECT * FROM odoo_settings ORDER BY id DESC LIMIT 1");
if ($row = $result->fetch_assoc()) {
    echo "URL: " . $row['odoo_url'] . "\n";
    echo "DB: " . $row['odoo_database'] . "\n";
    echo "User: " . $row['odoo_username'] . "\n";
    echo "API Key length: " . strlen($row['odoo_api_key']) . "\n";
} else {
    echo "No settings found\n";
}
