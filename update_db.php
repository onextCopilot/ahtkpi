<?php
require_once __DIR__ . '/config/config.php';

echo "<h3>Database Migration Setup</h3>";

// Safe wrapper for adding columns
function addColumnIfNotExists($conn, $table, $column, $definition)
{
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE '$table'");
    if ($tableExists && $tableExists->num_rows > 0) {
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($res && $res->num_rows == 0) {
            if ($conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
                echo "<p style='color:green'>+ Added `$column` to `$table`.</p>";
            } else {
                echo "<p style='color:red'>- Failed to add `$column` to `$table`: " . $conn->error . "</p>";
            }
        } else {
            // echo "<p style='color:gray'>* Column `$column` already exists in `$table`.</p>";
        }
    }
}

// 1. Create essential table structures if they completely don't exist
$base_tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        email VARCHAR(100),
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS sale_teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        order_num INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS user_sale_teams (
        user_id INT NOT NULL,
        team_id INT NOT NULL,
        PRIMARY KEY (user_id, team_id)
    )",
    "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

foreach ($base_tables as $sql) {
    $conn->query($sql);
}

// 2. Add missing columns dynamically
$user_columns = [
    'avatar' => 'varchar(255) DEFAULT NULL',
    'phone' => 'varchar(20) DEFAULT NULL',
    'job_title' => 'varchar(100) DEFAULT NULL',
    'level' => "varchar(50) DEFAULT 'Junior'",
    'department_id' => 'int(11) DEFAULT NULL',
    'employee_code' => 'varchar(20) DEFAULT NULL',
    'skills' => 'text',
    'join_date' => 'date DEFAULT NULL',
    'status' => "enum('active','inactive','resigned','on_leave') DEFAULT 'active'",
    'can_view_invoice' => "tinyint(1) DEFAULT '0'",
    'is_am_bd' => "tinyint(1) DEFAULT '0'"
];
foreach ($user_columns as $col => $def) {
    addColumnIfNotExists($conn, 'users', $col, $def);
}

$dept_columns = [
    'parent_id' => 'int(11) DEFAULT NULL',
    'owner_id' => 'int(11) DEFAULT NULL',
    'manager_id' => 'int(11) DEFAULT NULL',
    'sort_order' => "int(11) DEFAULT '0'"
];
foreach ($dept_columns as $col => $def) {
    addColumnIfNotExists($conn, 'departments', $col, $def);
}

// 3. Populate default System Settings
$settings = [
    ['smtp_host', 'smtp.gmail.com'],
    ['smtp_port', '587'],
    ['smtp_encryption', 'tls'],
    ['smtp_user', ''],
    ['smtp_pass', '']
];
foreach ($settings as $s) {
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('{$s[0]}', '{$s[1]}')");
}

echo "<h3>✅ Database migration completed successfully! You can now log in.</h3>";
echo "<p><a href='/login'>Go to Login</a></p>";
