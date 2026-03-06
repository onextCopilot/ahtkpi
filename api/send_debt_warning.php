<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$debt_ids = isset($input['debt_ids']) ? $input['debt_ids'] : [];
if (isset($input['debt_id'])) {
    $debt_ids[] = intval($input['debt_id']);
}
$warning_type = isset($input['warning_type']) ? $input['warning_type'] : 'manual';

if (empty($debt_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No debt IDs provided']);
    exit();
}

// 1. Ensure table and column exists
$conn->query("CREATE TABLE IF NOT EXISTS debt_manual_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debt_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    warning_type VARCHAR(50) DEFAULT 'manual',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Check if warning_type column exists
$check_col = $conn->query("SHOW COLUMNS FROM debt_manual_warnings LIKE 'warning_type'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE debt_manual_warnings ADD COLUMN warning_type VARCHAR(50) DEFAULT 'manual' AFTER receiver_id");
}

$success_count = 0;
$errors = [];

$sender_id = $_SESSION['user_id'];

foreach ($debt_ids as $did) {
    $did = intval($did);
    if ($did <= 0)
        continue;

    // 2. Find AM for this debt
    $stmt = $conn->prepare("SELECT am FROM debts WHERE id = ?");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    $res = $stmt->get_result();
    $debt_data = $res->fetch_assoc();
    if (!$debt_data || empty($debt_data['am'])) {
        $errors[] = "Debt $did: No AM assigned";
        continue;
    }
    $am_name = trim($debt_data['am']);

    // 3. Find user_id for this AM
    $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
    $stmt->bind_param("s", $am_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $am_user = $res->fetch_assoc();
    if (!$am_user) {
        $errors[] = "Debt $did: AM user '$am_name' not found";
        continue;
    }
    $receiver_id = $am_user['id'];

    // 4. Insert warning
    $stmt = $conn->prepare("INSERT INTO debt_manual_warnings (debt_id, sender_id, receiver_id, warning_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $did, $sender_id, $receiver_id, $warning_type);
    if ($stmt->execute()) {
        $success_count++;
    } else {
        $errors[] = "Debt $did: " . $conn->error;
    }
}

echo json_encode(['success' => $success_count > 0, 'count' => $success_count, 'errors' => $errors]);
?>