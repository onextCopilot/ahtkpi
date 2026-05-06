<?php
require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS hrm_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    manager VARCHAR(255),
    creators TEXT,
    followers TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    // Insert initial data if table is empty
    $check = $conn->query("SELECT COUNT(*) as count FROM hrm_departments");
    $row = $check->fetch_assoc();
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO hrm_departments (name, description) VALUES 
            ('Sales/Marketing', 'Sales/Marketing'),
            ('Backoffice', ''),
            ('AHT Thái Nguyên', 'Số 8, Đường Trưng, Phường Tân Thịnh, Thái Nguyên'),
            ('AHT Phú Thọ', 'Số 15, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì – Phú Thọ'),
            ('IT', ''),
            ('BFSI', ''),
            ('Remote/Hybrid', 'Remote/Hybrid'),
            ('delivery', 'delivery')");
    }
    echo "Table hrm_departments created and initialized successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
