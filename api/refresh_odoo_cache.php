<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../libs/OdooAPI.php';

try {
    $odoo = new OdooAPI();
    $count = $odoo->refreshCustomerCache();

    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "Cache updated successfully with $count records."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
