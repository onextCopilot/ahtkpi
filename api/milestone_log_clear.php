<?php
/**
 * Xoá log milestone_webhook_logs theo khoảng ngày.
 * Admin-only. POST { date_from: 'YYYY-MM-DD', date_to: 'YYYY-MM-DD' }
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$date_from = trim($body['date_from'] ?? '');
$date_to   = trim($body['date_to']   ?? '');

if (!$date_from || !$date_to) {
    http_response_code(400);
    echo json_encode(['error' => 'date_from và date_to là bắt buộc']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    http_response_code(400);
    echo json_encode(['error' => 'Định dạng ngày không hợp lệ (YYYY-MM-DD)']);
    exit;
}
if ($date_from > $date_to) {
    http_response_code(400);
    echo json_encode(['error' => 'date_from phải nhỏ hơn hoặc bằng date_to']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM milestone_webhook_logs WHERE DATE(received_at) BETWEEN ? AND ?");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['ok' => true, 'deleted' => $deleted, 'date_from' => $date_from, 'date_to' => $date_to]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
