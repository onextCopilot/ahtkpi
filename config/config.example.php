<?php
/**
 * Database Configuration Template
 * 
 * Copy this file to config.php and update with your database credentials
 */

// Database Configuration
define('DB_HOST', 'localhost');           // Database host
define('DB_USER', 'aht_kpi');       // Database username
define('DB_PASS', 'your_password');       // Database password
define('DB_NAME', 'aht_kpi');       // Database name

// Application Configuration
define('APP_NAME', 'AHT KPI Management');
define('APP_URL', 'http://localhost/AHT KPI/');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);      // Set to 1 if using HTTPS

// Start session
session_start();

// Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
