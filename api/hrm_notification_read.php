<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false]); exit; }
$uid = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id  = (int) ($input['id'] ?? 0);
$all = !empty($input['all']);
try {
    if ($all) {
        $st = $conn->prepare("UPDATE hrm_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $st->bind_param("i", $uid);
        $st->execute();
    } elseif ($id) {
        $st = $conn->prepare("UPDATE hrm_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $st->bind_param("ii", $id, $uid);
        $st->execute();
    }
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
