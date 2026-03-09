<?php
// api/update_debt_inline.php
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Suppress potential session warnings from config.php on live server
$old_error_level = error_reporting(0);
require_once __DIR__ . '/../config/config.php';
error_reporting($old_error_level);

// Check session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$id = intval($_POST['id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

file_put_contents(__DIR__ . '/../debug_update.log', date('Y-m-d H:i:s') . " - ID: $id, Field: $field, Value: $value\n", FILE_APPEND);

if ($id <= 0 || empty($field)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

// Allowed fields
$allowedFields = [
    'payment_milestone',
    'expected_prod_date',
    'expected_payment_date',
    'invoice_status_class',
    'am_notes',
    'delivery_notes',
    'production_status',
    'invoice_status',
    'project_name',
    'pl_class'
];

if (!in_array($field, $allowedFields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Field not allowed']);
    exit();
}

// Handle empty date values -> NULL
if (($field === 'expected_prod_date' || $field === 'expected_payment_date') && empty($value)) {
    $value = null;
}

try {
    $sql = "UPDATE debts SET $field = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Database Error: " . $conn->error);
    }

    $stmt->bind_param("si", $value, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Update failed: ' . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
