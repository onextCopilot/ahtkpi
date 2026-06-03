<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt   = $conn->prepare(
    "SELECT id, device_name, created_at, last_used_at FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$passkeys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['passkeys' => $passkeys]);
