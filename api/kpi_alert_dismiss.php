<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok' => false]); exit; }
$uid = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$quarter = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($input['quarter'] ?? ''));
if ($quarter === '') { echo json_encode(['ok' => false, 'error' => 'missing quarter']); exit; }
try {
    $conn->query("CREATE TABLE IF NOT EXISTS kpi_alert_dismissals (
        user_id INT NOT NULL, quarter VARCHAR(16) NOT NULL, dismissed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, quarter)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $conn->prepare("INSERT IGNORE INTO kpi_alert_dismissals (user_id, quarter) VALUES (?, ?)");
    $st->bind_param("is", $uid, $quarter);
    $st->execute();
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
