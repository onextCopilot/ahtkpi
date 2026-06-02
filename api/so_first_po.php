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

if (!$body || !isset($body['so_odoo_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing so_odoo_id']);
    exit;
}

$so_odoo_id = (int) $body['so_odoo_id'];
$is_first_po = !empty($body['is_first_po']) ? 1 : 0;

$conn->query("CREATE TABLE IF NOT EXISTS so_first_po_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    so_odoo_id INT NOT NULL,
    is_first_po TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_so (user_id, so_odoo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO so_first_po_map (user_id, so_odoo_id, is_first_po) VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE is_first_po = VALUES(is_first_po)");

if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iii", $u_id, $so_odoo_id, $is_first_po);
$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

echo json_encode(['ok' => $ok, 'error' => $ok ? null : $err]);
