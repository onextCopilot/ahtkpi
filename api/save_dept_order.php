<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

/**
 * API to save the sort order of departments
 */

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['order'])) {
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit();
}

$order = $input['order'];

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE departments SET sort_order = ? WHERE id = ?");
    foreach ($order as $index => $id) {
        $sort_order = $index + 1;
        $dept_id = intval($id);
        $stmt->bind_param("ii", $sort_order, $dept_id);
        $stmt->execute();
    }
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
