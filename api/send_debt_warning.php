<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$debt_id = isset($input['debt_id']) ? intval($input['debt_id']) : 0;

if ($debt_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid debt ID']);
    exit();
}

// 1. Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS debt_manual_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debt_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
)");

// 2. Find AM for this debt
$stmt = $conn->prepare("SELECT am FROM debts WHERE id = ?");
$stmt->bind_param("i", $debt_id);
$stmt->execute();
$res = $stmt->get_result();
$debt_data = $res->fetch_assoc();
if (!$debt_data || empty($debt_data['am'])) {
    echo json_encode(['success' => false, 'error' => 'Debt not found or no AM assigned']);
    exit();
}
$am_name = trim($debt_data['am']);

// 3. Find user_id for this AM
$stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
$stmt->bind_param("s", $am_name);
$stmt->execute();
$res = $stmt->get_result();
$am_user = $res->fetch_assoc();
if (!$am_user) {
    echo json_encode(['success' => false, 'error' => 'AM user not found in system']);
    exit();
}
$receiver_id = $am_user['id'];
$sender_id = $_SESSION['user_id'];

// 4. Insert warning
$stmt = $conn->prepare("INSERT INTO debt_manual_warnings (debt_id, sender_id, receiver_id) VALUES (?, ?, ?)");
$stmt->bind_param("iii", $debt_id, $sender_id, $receiver_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>