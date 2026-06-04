<?php
/**
 * Unified mark-as-read endpoint for the NotificationCenter.
 *
 * POST JSON:
 *   { "all": true }                                  → mark all dismissible read
 *   { "type": "pasx",   "id": 12 }                   → one PASX notification
 *   { "type": "manual", "id": 7 }                    → one manual debt warning
 *   { "type": "debt",   "debt_id": 45, "level": 60 } → one overdue-debt warning
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/NotificationCenter.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$uid = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!empty($input['all'])) {
    $n = NotificationCenter::markAllRead($conn, $_SESSION);
    echo json_encode(['ok' => true, 'marked' => $n]);
    exit;
}

$type = $input['type'] ?? '';
$payload = ['type' => $type];
if ($type === 'pasx' || $type === 'manual') {
    $payload['id'] = (int) ($input['id'] ?? 0);
} elseif ($type === 'debt') {
    $payload['debt_id'] = (int) ($input['debt_id'] ?? 0);
    $payload['level'] = (int) ($input['level'] ?? 0);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad type']);
    exit;
}

$ok = NotificationCenter::markRead($conn, $uid, $payload);
echo json_encode(['ok' => $ok]);
