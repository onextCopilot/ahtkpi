<?php
require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS folio_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    folio_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_folio (user_id, folio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "Table folio_wishlist created successfully or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
