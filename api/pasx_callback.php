<?php
/**
 * Webhook: nhận callback từ ArrowHitech PASX
 * POST /api/pasx/callback
 * Header: X-Webhook-Secret: <secret>
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Đảm bảo bảng log tồn tại ──
$conn->query("CREATE TABLE IF NOT EXISTS pasx_webhook_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id       INT DEFAULT NULL,
    pasx_id       VARCHAR(64) DEFAULT NULL,
    event         VARCHAR(64) DEFAULT NULL,
    payload       JSON DEFAULT NULL,
    status        VARCHAR(32) DEFAULT NULL,
    http_status   INT DEFAULT 200,
    note          TEXT DEFAULT NULL,
    received_at   DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$raw_body = file_get_contents('php://input');

// ── Xác thực Webhook Secret ──
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
$cfg        = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
$expected   = $cfg['webhook_secret'] ?? '';

$received = $_SERVER['HTTP_X_WEBHOOK_SECRET']
         ?? $_SERVER['HTTP_X_API_KEY']  // fallback
         ?? '';

if (!$expected) {
    // Log & reject nếu chưa cấu hình secret
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Webhook secret chưa được cấu hình trên server']);
    exit;
}

if (!hash_equals($expected, $received)) {
    // Log failed auth
    $conn->query("INSERT INTO pasx_webhook_logs (event, payload, http_status, note, received_at)
        VALUES ('auth_failed', '" . $conn->real_escape_string(json_encode(['raw' => substr($raw_body, 0, 500)])) . "', 401, 'Invalid X-Webhook-Secret', NOW())");
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

// ── Parse payload ──
$data = json_decode($raw_body, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid JSON body']);
    exit;
}

$pakd_id     = isset($data['pakdId'])      ? (int)$data['pakdId']      : null;
$pasx_id     = $data['pasxId']             ?? null;
$event       = $data['event']              ?? 'callback';
$status      = $data['status']             ?? null;
$human_cost  = isset($data['humanCost'])   ? (float)$data['humanCost']   : null;
$overtime    = isset($data['overtimeCost'])? (float)$data['overtimeCost']: null;

// ── Ghi log ──
$log_stmt = $conn->prepare(
    "INSERT INTO pasx_webhook_logs (pakd_id, pasx_id, event, payload, status, http_status, received_at)
     VALUES (?, ?, ?, ?, ?, 200, NOW())"
);
$payload_json = json_encode($data, JSON_UNESCAPED_UNICODE);
$log_stmt->bind_param("issss", $pakd_id, $pasx_id, $event, $payload_json, $status);
$log_stmt->execute();
$log_stmt->close();

// ── Cập nhật pakd nếu có pakdId ──
if ($pakd_id) {
    // Đảm bảo các cột tồn tại
    foreach ([
        "ALTER TABLE pakd ADD COLUMN pasx_status VARCHAR(32) DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN pasx_id VARCHAR(64) DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN pasx_requested_at DATETIME DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN fin_data JSON DEFAULT NULL",
    ] as $sql) { try { $conn->query($sql); } catch (Exception $e) {} }

    // Cập nhật status
    if ($status) {
        $st = $conn->prepare("UPDATE pakd SET pasx_status=? WHERE id=?");
        $st->bind_param("si", $status, $pakd_id);
        $st->execute();
        $st->close();
    }

    // Cập nhật fin_data với human_cost / overtime nếu có
    if ($human_cost !== null || $overtime !== null) {
        $fr = $conn->prepare("SELECT fin_data FROM pakd WHERE id=?");
        $fr->bind_param("i", $pakd_id);
        $fr->execute();
        $row      = $fr->get_result()->fetch_assoc();
        $fr->close();
        $fin_data = !empty($row['fin_data']) ? (json_decode($row['fin_data'], true) ?? []) : [];

        if ($human_cost !== null) $fin_data['human_cost']  = $human_cost;
        if ($overtime   !== null) $fin_data['overtime_cost'] = $overtime;

        $fu = $conn->prepare("UPDATE pakd SET fin_data=? WHERE id=?");
        $fj = json_encode($fin_data);
        $fu->bind_param("si", $fj, $pakd_id);
        $fu->execute();
        $fu->close();
    }
}

http_response_code(200);
echo json_encode([
    'ok'      => true,
    'msg'     => 'Callback received',
    'pakd_id' => $pakd_id,
    'event'   => $event,
    'status'  => $status,
]);
