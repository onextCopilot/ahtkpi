<?php
// api/debt_warning_ack.php — AM xác nhận "đã nhận thông tin" các cảnh báo invoice chưa add Debts
header('Content-Type: application/json');
$old = error_reporting(0);
require_once __DIR__ . '/../config/config.php';
error_reporting($old);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$uid = (int) $_SESSION['user_id'];
$acked = 0;
try {
    // Nếu có id cụ thể -> ack 1; không thì ack tất cả của user
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id > 0) {
        $st = $conn->prepare("UPDATE debt_add_warnings SET is_acknowledged = 1, acknowledged_at = NOW() WHERE id = ? AND am_user_id = ? AND is_acknowledged = 0");
        $st->bind_param("ii", $id, $uid);
    } else {
        $st = $conn->prepare("UPDATE debt_add_warnings SET is_acknowledged = 1, acknowledged_at = NOW() WHERE am_user_id = ? AND is_acknowledged = 0");
        $st->bind_param("i", $uid);
    }
    $st->execute();
    $acked = $st->affected_rows;
    $st->close();
    echo json_encode(['success' => true, 'acked' => $acked]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
