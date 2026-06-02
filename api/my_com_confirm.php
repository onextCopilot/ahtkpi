<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$u_id = (int) $_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['year'], $body['quarter'], $body['action'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

$year    = (int) $body['year'];
$quarter = (int) $body['quarter'];
$action  = $body['action']; // 'confirm' | 'reset'

if (!in_array($action, ['confirm', 'reset'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS my_com_confirmation (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    year         SMALLINT NOT NULL,
    quarter      TINYINT NOT NULL,
    status       ENUM('draft','confirmed') DEFAULT 'draft',
    confirmed_at DATETIME DEFAULT NULL,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_yq (user_id, year, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($action === 'confirm') {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO my_com_confirmation (user_id, year, quarter, status, confirmed_at)
        VALUES (?,?,?,'confirmed',?)
        ON DUPLICATE KEY UPDATE status='confirmed', confirmed_at=VALUES(confirmed_at)");
    $stmt->bind_param("iiis", $u_id, $year, $quarter, $now);
} else {
    $stmt = $conn->prepare("INSERT INTO my_com_confirmation (user_id, year, quarter, status)
        VALUES (?,?,?,'draft')
        ON DUPLICATE KEY UPDATE status='draft', confirmed_at=NULL");
    $stmt->bind_param("iii", $u_id, $year, $quarter);
}

if (!$stmt) { echo json_encode(['ok' => false, 'error' => $conn->error]); exit; }
$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

echo json_encode(['ok' => $ok, 'error' => $ok ? null : $err]);
