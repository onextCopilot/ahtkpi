<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
$uid = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id    = (int)($input['id'] ?? 0);
$all   = !empty($input['all']);
if ($all) {
    $conn->query("UPDATE pasx_notifications SET is_read=1 WHERE user_id=$uid AND is_read=0");
} elseif ($id) {
    $st = $conn->prepare("UPDATE pasx_notifications SET is_read=1 WHERE id=? AND user_id=?");
    $st->bind_param("ii", $id, $uid);
    $st->execute();
    $st->close();
}
echo json_encode(['ok'=>true]);
