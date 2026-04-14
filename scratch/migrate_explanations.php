<?php
require_once __DIR__ . '/../../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS okr_explanations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    item_type ENUM('metric', 'activity') NOT NULL,
    content TEXT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Table okr_explanations created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
