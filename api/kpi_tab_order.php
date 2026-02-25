<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$order = $body['order'] ?? [];

if (empty($order) || !is_array($order)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order data']);
    exit();
}

// Ensure sort_order column exists
$check_col = $conn->query("SHOW COLUMNS FROM departments LIKE 'sort_order'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN sort_order INT DEFAULT 0");
}

$stmt = $conn->prepare("UPDATE departments SET sort_order = ? WHERE id = ?");
foreach ($order as $position => $dept_id) {
    $pos = intval($position) + 1;
    $id = intval($dept_id);
    if ($id > 0) {
        $stmt->bind_param("ii", $pos, $id);
        $stmt->execute();
    }
}

echo json_encode(['success' => true, 'message' => 'Order saved']);
