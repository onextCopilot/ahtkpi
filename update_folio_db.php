<?php
require_once __DIR__ . '/config/config.php';

/**
 * Migration script to create Folio-related tables
 * Access this file via browser to update the database
 */

// Simple security check: Only logged-in admins can run this
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("<h3>Unauthorized access. Please login as admin first.</h3>");
}

$queries = [
    "CREATE TABLE IF NOT EXISTS `folio_project_manual` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `jira_key` VARCHAR(50) NOT NULL UNIQUE,
        `budget` DECIMAL(20,2) DEFAULT 0,
        `plan_cost` DECIMAL(20,2) DEFAULT 0,
        `cost_rate` DECIMAL(20,2) DEFAULT 0,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `folio_id` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `folio_budget_cache` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `jira_key` VARCHAR(50) NOT NULL UNIQUE,
        `folio_id` VARCHAR(50) NOT NULL,
        `plan` DECIMAL(20,2) DEFAULT 0,
        `actual` DECIMAL(20,2) DEFAULT 0,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (folio_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `folio_wishlist` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `folio_id` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `user_folio` (`user_id`, `folio_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "<!DOCTYPE html><html><head><title>DB Migration</title><style>body{font-family:sans-serif;padding:40px;line-height:1.6;}</style></head><body>";
echo "<h2>Folio Database Migration</h2>";
echo "<hr>";

foreach ($queries as $i => $sql) {
    echo "<div>Executing Query " . ($i + 1) . "... ";
    if ($conn->query($sql)) {
        echo "<b style='color:green'>SUCCESS</b></div>";
    } else {
        echo "<b style='color:red'>FAILED</b>: " . htmlspecialchars($conn->error) . "</div>";
    }
}

echo "<hr>";
echo "<p><b>Migration completed!</b> You can now delete this file and go to the Folio module.</p>";
echo "<a href='/folio' style='display:inline-block;padding:10px 20px;background:#6366f1;color:#fff;text-decoration:none;border-radius:5px;'>Go to Folio</a>";
echo "</body></html>";
