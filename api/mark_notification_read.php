<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$debt_id = isset($input['debt_id']) ? intval($input['debt_id']) : 0;
$warning_level = isset($input['warning_level']) ? intval($input['warning_level']) : 0;
$action = isset($input['action']) ? $input['action'] : 'mark_one';

if ($action === 'mark_all') {
    // We expect the client to send a list of [{debt_id, warning_level}] to mark all currently unread ones.
    $notifications = isset($input['notifications']) ? $input['notifications'] : [];
    if (!empty($notifications) && is_array($notifications)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO debt_notifications_read (user_id, debt_id, warning_level) VALUES (?, ?, ?)");
        if ($stmt) {
            foreach ($notifications as $n) {
                $d_id = intval($n['debt_id']);
                $w_lvl = intval($n['warning_level']);
                if ($d_id > 0 && in_array($w_lvl, [30, 60])) {
                    $stmt->bind_param("iii", $user_id, $d_id, $w_lvl);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
    }
    echo json_encode(['success' => true]);
    exit();
}

// Mark one
if ($debt_id <= 0 || !in_array($warning_level, [30, 60])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$stmt = $conn->prepare("INSERT IGNORE INTO debt_notifications_read (user_id, debt_id, warning_level) VALUES (?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("iii", $user_id, $debt_id, $warning_level);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
