<?php
require_once __DIR__ . '/../../config/config.php';

$sql = [
    "ALTER TABLE okr_results ADD COLUMN priority VARCHAR(20) DEFAULT 'medium'",
    "ALTER TABLE okr_results ADD COLUMN weight INT DEFAULT 0",
    "ALTER TABLE okr_key_activities ADD COLUMN priority VARCHAR(20) DEFAULT 'medium'",
    "ALTER TABLE okr_key_activities ADD COLUMN weight INT DEFAULT 0"
];

foreach ($sql as $s) {
    try {
        if ($conn->query($s)) {
            echo "Executed: $s\n";
        } else {
            echo "Skipped/Error: $s (" . $conn->error . ")\n";
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
}
echo "Migration complete.\n";
