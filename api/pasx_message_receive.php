<?php
/**
 * Webhook: nhận message từ ArrowHitech Profile gửi về
 * POST /api/pasx/message
 *
 * Headers:
 *   X-Webhook-Secret: <secret>   (cùng secret với /api/pasx/callback)
 *   Content-Type: application/json
 *
 * Body:
 * {
 *   "pasxId":     "PASX-xxxx",
 *   "pakdId":     123,              // optional — dùng để tra PAKD
 *   "oppId":      "456",            // optional — fallback tra PAKD qua odoo_opp_id
 *   "message":    "Nội dung...",
 *   "senderName": "Trần Văn B",
 *   "images":     ["https://..."]   // optional
 * }
 *
 * Response:
 * { "ok": true, "msg": "Message received", "messageId": 42 }
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Đảm bảo bảng lưu chat tồn tại ──
$conn->query("CREATE TABLE IF NOT EXISTS pakd_chat_messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id      INT NOT NULL,
    pasx_id      VARCHAR(64)                  DEFAULT NULL,
    direction    ENUM('sent','received')      NOT NULL DEFAULT 'received',
    sender_name  VARCHAR(255)                 DEFAULT NULL,
    message      TEXT                         DEFAULT NULL,
    images       JSON                         DEFAULT NULL,
    created_at   DATETIME                     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pakd_time (pakd_id, created_at)
)");

$raw_body = file_get_contents('php://input');

// ── Xác thực Webhook Secret ──
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
$cfg        = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
$expected   = $cfg['webhook_secret'] ?? '';

$received = $_SERVER['HTTP_X_WEBHOOK_SECRET']
         ?? $_SERVER['HTTP_X_API_KEY']
         ?? '';

if (!$expected) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Webhook secret chưa được cấu hình trên server']);
    exit;
}

if (!hash_equals($expected, $received)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized — sai X-Webhook-Secret']);
    exit;
}

// ── Parse body ──
$data = json_decode($raw_body, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid JSON body']);
    exit;
}

$pasx_id    = isset($data['pasxId'])     ? trim((string)$data['pasxId'])     : null;
$opp_id     = isset($data['oppId'])      ? trim((string)$data['oppId'])      : null;
$pakd_id_in = isset($data['pakdId'])     ? (int)$data['pakdId']              : null;
$message    = isset($data['message'])    ? trim((string)$data['message'])    : '';
$sender     = isset($data['senderName']) ? trim((string)$data['senderName']) : 'ArrowHitech Profile';
$images     = (isset($data['images']) && is_array($data['images']))
              ? array_values(array_filter($data['images'], 'is_string'))
              : [];

if (!$message && !$images) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Thiếu message hoặc images']);
    exit;
}

// ── Tra PAKD ──
$pakd_id = null;

if ($pakd_id_in) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE id = ? LIMIT 1");
    $lk->bind_param("i", $pakd_id_in);
    $lk->execute();
    $row = $lk->get_result()->fetch_assoc();
    $lk->close();
    if ($row) $pakd_id = (int)$row['id'];
}

if (!$pakd_id && $pasx_id) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE pasx_id = ? LIMIT 1");
    $lk->bind_param("s", $pasx_id);
    $lk->execute();
    $row = $lk->get_result()->fetch_assoc();
    $lk->close();
    if ($row) $pakd_id = (int)$row['id'];
}

if (!$pakd_id && $opp_id) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE odoo_opp_id = ? LIMIT 1");
    $lk->bind_param("s", $opp_id);
    $lk->execute();
    $row = $lk->get_result()->fetch_assoc();
    $lk->close();
    if ($row) $pakd_id = (int)$row['id'];
}

if (!$pakd_id) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy PAKD tương ứng (pakdId/pasxId/oppId)']);
    exit;
}

// ── Lưu message vào DB ──
$images_json = $images ? json_encode($images, JSON_UNESCAPED_UNICODE) : null;

$ins = $conn->prepare(
    "INSERT INTO pakd_chat_messages (pakd_id, pasx_id, direction, sender_name, message, images, created_at)
     VALUES (?, ?, 'received', ?, ?, ?, NOW())"
);
$ins->bind_param("issss", $pakd_id, $pasx_id, $sender, $message, $images_json);
$ins->execute();
$message_id = $conn->insert_id;
$ins->close();

// ── Tạo thông báo nội bộ cho AM ──
try {
    $conn->query("ALTER TABLE pasx_notifications ADD COLUMN message TEXT DEFAULT NULL");
} catch (\Throwable $e) {}

try {
    $pr = $conn->prepare("SELECT am_name, opportunity_name FROM pakd WHERE id=? LIMIT 1");
    $pr->bind_param("i", $pakd_id);
    $pr->execute();
    $pakd_row = $pr->get_result()->fetch_assoc();
    $pr->close();

    if (!empty($pakd_row['am_name'])) {
        $ur = $conn->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
        $ur->bind_param("s", $pakd_row['am_name']);
        $ur->execute();
        $user_row = $ur->get_result()->fetch_assoc();
        $ur->close();

        if ($user_row) {
            $am_uid   = (int)$user_row['id'];
            $opp_name = $pakd_row['opportunity_name'] ?? null;
            $ni = $conn->prepare(
                "INSERT INTO pasx_notifications
                    (user_id, pakd_id, pasx_id, event, status, opp_name, submitted_by, message)
                 VALUES (?, ?, ?, 'chat_message', 'unread', ?, ?, ?)"
            );
            $ni->bind_param("iisssss", $am_uid, $pakd_id, $pasx_id, $opp_name, $sender, $message);
            $ni->execute();
            $ni->close();
        }
    }
} catch (\Throwable $e) {}

http_response_code(200);
echo json_encode([
    'ok'        => true,
    'msg'       => 'Message received',
    'messageId' => $message_id,
    'pakdId'    => $pakd_id,
]);
