<?php
require_once __DIR__ . '/../config/config.php';

// Sync latest note from customer_notes to customers_metadata
$sql = "UPDATE customers_metadata cm
        JOIN (
            SELECT odoo_id, note_content
            FROM customer_notes
            WHERE id IN (SELECT MAX(id) FROM customer_notes GROUP BY odoo_id)
        ) latest_notes ON cm.odoo_id = latest_notes.odoo_id
        SET cm.account_note = latest_notes.note_content";

if ($conn->query($sql)) {
    echo "Sync completed. Rows affected: " . $conn->affected_rows;
} else {
    echo "Error: " . $conn->error;
}
?>