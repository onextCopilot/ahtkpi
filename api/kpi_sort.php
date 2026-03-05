<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['sort_data'])) {
    echo json_encode(['success' => false, 'error' => 'No data']);
    exit();
}

$data = $input['sort_data'];

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE kpi_definitions SET kpi_group = ?, group_order = ?, sort_order = ? WHERE id = ?");
    foreach ($data as $item) {
        $id = intval($item['id']);
        $group = $item['group'] ?? '';
        $group_order = intval($item['group_order']);
        $sort_order = intval($item['sort_order']);

        $stmt->bind_param("siii", $group, $group_order, $sort_order, $id);
        $stmt->execute();
    }
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
