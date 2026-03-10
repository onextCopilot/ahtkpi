<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $odoo_id = isset($_GET['odoo_id']) ? (int) $_GET['odoo_id'] : 0;
    if (!$odoo_id) {
        echo json_encode(['success' => false, 'error' => 'Missing Odoo ID']);
        exit;
    }

    $sql = "SELECT n.*, u.full_name as author 
            FROM customer_notes n 
            JOIN users u ON n.user_id = u.id 
            WHERE n.odoo_id = ? 
            ORDER BY n.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $odoo_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $notes]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $odoo_id = (int) $data['odoo_id'];
    $content = trim($data['content']);
    $user_id = $_SESSION['user_id'];

    if (!$odoo_id || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO customer_notes (odoo_id, user_id, note_content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $odoo_id, $user_id, $content);

    if ($stmt->execute()) {
        // Also update the account_note "cache" in customers_metadata
        $updateStmt = $conn->prepare("UPDATE customers_metadata SET account_note = ? WHERE odoo_id = ?");
        $updateStmt->bind_param("si", $content, $odoo_id);
        $updateStmt->execute();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}
