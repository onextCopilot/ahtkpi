<?php
/**
 * Budget Module Installer
 * Run this file once to create the necessary database tables on the live site.
 * DELETE THIS FILE AFTER SUCCESSFUL INSTALLATION.
 */

require_once __DIR__ . '/config/config.php';

echo "<h2>Budget Module Installation</h2>";

// 1. Create budget_structure table
$sql_structure = "CREATE TABLE IF NOT EXISTS `budget_structure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) DEFAULT NULL,
  `quarter` int(11) DEFAULT NULL,
  `division` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `owner` varchar(255) DEFAULT NULL,
  `type` enum('division','category','item') DEFAULT 'item',
  `order_num` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_period` (`year`, `quarter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_structure)) {
    echo "<p style='color:green;'>✅ Table 'budget_structure' created/verified successfully.</p>";
} else {
    echo "<p style='color:red;'>❌ Error creating 'budget_structure': " . $conn->error . "</p>";
}

// 2. Create budget_values table
$sql_values = "CREATE TABLE IF NOT EXISTS `budget_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `value_type` varchar(50) NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_val` (`item_id`, `year`, `quarter`, `month`, `value_type`),
  FOREIGN KEY (`item_id`) REFERENCES `budget_structure`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_values)) {
    echo "<p style='color:green;'>✅ Table 'budget_values' created/verified successfully.</p>";
} else {
    echo "<p style='color:red;'>❌ Error creating 'budget_values': " . $conn->error . "</p>";
}

// 3. Ensure existing budget_structure columns if migration from old version
$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'year');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN year INT DEFAULT 2026 AFTER id', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'quarter');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN quarter INT DEFAULT 1 AFTER year', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

echo "<hr><p><b>Installation complete. Please delete 'install_budget.php' from your server now.</b></p>";
?>
