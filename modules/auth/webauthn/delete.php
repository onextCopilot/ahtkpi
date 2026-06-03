<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id    = (int) ($input['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid passkey ID']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt   = $conn->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Passkey not found']);
    exit;
}

echo json_encode(['success' => true]);
