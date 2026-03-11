<?php
session_start();
$_SESSION['user_id'] = 1; // dummy for testing? No, I'll just use config.php directamente
require_once __DIR__ . '/config/config.php';
$sql = "SELECT cm.odoo_id, cm.am_bd_id, cm.delivery_owners, 
               COALESCE(latest_note.note_content, cm.account_note) as account_note,
               latest_note.author_name,
               latest_note.created_at as note_time,
               cm.company_source, cm.active_projects, cm.order_index
        FROM customers_metadata cm
        LEFT JOIN (
            SELECT cn1.odoo_id, cn1.note_content, cn1.created_at, u.full_name as author_name
            FROM customer_notes cn1
            JOIN users u ON cn1.user_id = u.id
            WHERE cn1.id = (SELECT MAX(id) FROM customer_notes cn2 WHERE cn2.odoo_id = cn1.odoo_id)
        ) latest_note ON cm.odoo_id = latest_note.odoo_id
        WHERE cm.is_key_account = 1
        ORDER BY cm.order_index ASC";
$res = $conn->query($sql);
if (!$res) {
    die("Query failed: " . $conn->error . "\n");
} else {
    echo "Query succeeded with " . $res->num_rows . " rows.\n";
}
?>