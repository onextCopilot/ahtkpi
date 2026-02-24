<?php
// api/sync_rates.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/OdooAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $odoo = new OdooAPI();
    $count = $odoo->refreshCurrencyRates();
    echo json_encode(['success' => true, 'message' => "Successfully synced {$count} currency rates."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
