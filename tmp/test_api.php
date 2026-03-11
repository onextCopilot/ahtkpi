<?php
session_start();
$_SESSION['user_id'] = 1; // Simulate login
$_SESSION['can_view_invoice'] = 1;

$_SERVER['REQUEST_METHOD'] = 'POST';
$input = json_encode(['odoo_id' => 123, 'content' => 'Test note']);
// We can't easily mock php://input, but we can call the logic.

require_once __DIR__ . '/../api/customer_notes.php';
?>