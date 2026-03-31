<?php
require_once __DIR__ . '/config/config.php';

echo "Updating database to support values over 2 billion VND...\n";

// Fix budget_values table
$sql1 = "ALTER TABLE budget_values MODIFY amount DECIMAL(20,2)";
if ($conn->query($sql1) === TRUE) {
    echo "Successfully updated budget_values.amount to DECIMAL(20,2)\n";
} else {
    echo "Error updating budget_values: " . $conn->error . "\n";
}

// Fix budget_structure table planning & revenue fields
$structure_fields = [
    'planned', 
    'rec_rev_good', 'rec_rev_avg', 'rec_rev_bad', 
    'inv_rev_good', 'inv_rev_avg', 'inv_rev_bad'
];

foreach ($structure_fields as $field) {
    $sql2 = "ALTER TABLE budget_structure MODIFY $field DECIMAL(20,2)";
    if ($conn->query($sql2) === TRUE) {
        echo "Successfully updated budget_structure.$field to DECIMAL(20,2)\n";
    } else {
        echo "Error updating budget_structure.$field: " . $conn->error . "\n";
    }
}

echo "Database update completed.\n";
?>
