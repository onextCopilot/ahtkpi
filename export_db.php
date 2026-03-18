<?php
/**
 * Database Export Script
 * Parses database credentials directly from config/config.php and runs mysqldump.
 */

$configFile = __DIR__ . '/config/config.php';

// Check if config file exists
if (!file_exists($configFile)) {
    die("Error: Config file not found at {$configFile}.\nPlease ensure the config file is created.\n");
}

$content = file_get_contents($configFile);

$dbHost = 'localhost';
$dbUser = '';
$dbPass = '';
$dbName = '';

// Extract credentials via regex to parse the config safely without executing it
if (preg_match("/(?:define|const)\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $m)) {
    $dbHost = $m[1];
}
if (preg_match("/(?:define|const)\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $m)) {
    $dbUser = $m[1];
}
if (preg_match("/(?:define|const)\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i", $content, $m)) {
    $dbPass = $m[1];
}
if (preg_match("/(?:define|const)\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $m)) {
    $dbName = $m[1];
}

if (empty($dbName) || empty($dbUser)) {
    die("Error: Could not extract DB_NAME or DB_USER from the config file.\n");
}

// Generate the output filename
$outputFile = sprintf("export_%s_%s.sql", $dbName, date('Ymd_His'));

echo "Exporting database '{$dbName}' to '{$outputFile}'...\n";

// Set MYSQL_PWD environment variable to pass the password securely to mysqldump
if ($dbPass !== '') {
    putenv("MYSQL_PWD={$dbPass}");
}

// Build the mysqldump command
$cmd = sprintf(
    "mysqldump -h %s -u %s %s > %s",
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    escapeshellarg($dbName),
    escapeshellarg($outputFile)
);

// Execute the command
system($cmd, $returnVar);

// Unset the password to be safe
putenv("MYSQL_PWD");

if ($returnVar === 0) {
    echo "Database export successful: {$outputFile}\n";
} else {
    echo "Error during database export. Return code: {$returnVar}\n";
    exit(1);
}
