<?php
// api/debt_week_confirm.php — AM confirm / un-confirm công nợ theo tuần hiện tại
header('Content-Type: application/json');
$old = error_reporting(0);
require_once __DIR__ . '/../config/config.php';
error_reporting($old);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS debt_weekly_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    am_name VARCHAR(150),
    am_email VARCHAR(150),
    yr INT NOT NULL,
    wk INT NOT NULL,
    confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_uw (user_id, yr, wk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uid    = (int) $_SESSION['user_id'];
$name   = $_SESSION['full_name'] ?? '';
$email  = $_SESSION['email'] ?? '';
$action = $_POST['action'] ?? 'confirm';

// Mặc định tuần hiện tại (ISO week + ISO year)
$wk = isset($_POST['wk']) ? (int) $_POST['wk'] : (int) date('W');
$yr = isset($_POST['yr']) ? (int) $_POST['yr'] : (int) date('o');
if ($wk < 1 || $wk > 53) $wk = (int) date('W');
if ($yr < 2000 || $yr > 2100) $yr = (int) date('o');

try {
    if ($action === 'unconfirm') {
        $st = $conn->prepare("DELETE FROM debt_weekly_confirmations WHERE user_id = ? AND yr = ? AND wk = ?");
        $st->bind_param("iii", $uid, $yr, $wk);
        $st->execute();
        $st->close();
        echo json_encode(['success' => true, 'confirmed' => false, 'wk' => $wk, 'yr' => $yr]);
    } else {
        $st = $conn->prepare("INSERT INTO debt_weekly_confirmations (user_id, am_name, am_email, yr, wk, confirmed_at)
                              VALUES (?, ?, ?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE confirmed_at = NOW(), am_name = VALUES(am_name), am_email = VALUES(am_email)");
        $st->bind_param("issii", $uid, $name, $email, $yr, $wk);
        $st->execute();
        $st->close();
        echo json_encode(['success' => true, 'confirmed' => true, 'wk' => $wk, 'yr' => $yr]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
