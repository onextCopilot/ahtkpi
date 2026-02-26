<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'login_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
} else {
    die("Error creating database: " . $conn->error);
}

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    // Check if admin user exists
    $check_admin = "SELECT * FROM users WHERE username = 'admin'";
    $result = $conn->query($check_admin);

    if ($result->num_rows == 0) {
        // Create default admin user
        $admin_username = 'admin';
        $admin_password = password_hash('@admin123', PASSWORD_DEFAULT);
        $admin_full_name = 'Administrator';
        $admin_email = 'admin@system.com';
        $admin_role = 'admin';

        $insert_admin = "INSERT INTO users (username, password, full_name, email, role) 
                        VALUES ('$admin_username', '$admin_password', '$admin_full_name', '$admin_email', '$admin_role')";

        if ($conn->query($insert_admin) === TRUE) {
            // Admin user created successfully
        }
    }
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Auto-refresh user permissions from DB to ensure instant updates
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT role, can_view_invoice, can_view_all_debts, is_am_bd, email, department_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['role'] = $row['role'];
        $_SESSION['can_view_invoice'] = $row['can_view_invoice'];
        $_SESSION['can_view_all_debts'] = $row['can_view_all_debts'];
        $_SESSION['is_am_bd'] = $row['is_am_bd'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['department_id'] = $row['department_id'] ?? null;
    }
    $stmt->close();
}
?>