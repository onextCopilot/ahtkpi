<?php
/**
 * Webhook: nhận callback từ ArrowHitech PASX
 * POST /api/pasx/callback
 * Header: X-Webhook-Secret: <secret>
 *
 * Lookup PAKD theo oppId (odoo_opp_id) — stable across environments
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Đảm bảo bảng log tồn tại ──
$conn->query("CREATE TABLE IF NOT EXISTS pasx_webhook_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id       INT DEFAULT NULL,
    opp_id        VARCHAR(64) DEFAULT NULL,
    pasx_id       VARCHAR(64) DEFAULT NULL,
    event         VARCHAR(64) DEFAULT NULL,
    payload       JSON DEFAULT NULL,
    status        VARCHAR(32) DEFAULT NULL,
    http_status   INT DEFAULT 200,
    note          TEXT DEFAULT NULL,
    received_at   DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Thêm cột opp_id vào log table nếu chưa có
try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN opp_id VARCHAR(64) DEFAULT NULL AFTER pakd_id"); } catch (\Throwable $e) {}

$raw_body = file_get_contents('php://input');

// ── Xác thực Webhook Secret ──
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
$cfg        = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
$expected   = $cfg['webhook_secret'] ?? '';

$received = $_SERVER['HTTP_X_WEBHOOK_SECRET']
         ?? $_SERVER['HTTP_X_API_KEY']  // fallback
         ?? '';

if (!$expected) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Webhook secret chưa được cấu hình trên server']);
    exit;
}

if (!hash_equals($expected, $received)) {
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

$opp_id      = isset($data['oppId'])       ? (string)$data['oppId']       : null;
$pasx_id     = $data['pasxId']             ?? null;
$event       = $data['event']              ?? 'callback';
$status      = $data['status']             ?? null;
$human_cost  = isset($data['humanCost'])   ? (float)$data['humanCost']    : null;
$overtime    = isset($data['overtimeCost'])? (float)$data['overtimeCost'] : null;

// ── Lookup pakd theo oppId (odoo_opp_id) ──
$pakd_id = null;
if ($opp_id) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE odoo_opp_id = ? LIMIT 1");
    $lk->bind_param("s", $opp_id);
    $lk->execute();
    $lk_row  = $lk->get_result()->fetch_assoc();
    $lk->close();
    $pakd_id = $lk_row ? (int)$lk_row['id'] : null;
}

// ── Ghi log ──
$log_stmt = $conn->prepare(
    "INSERT INTO pasx_webhook_logs (pakd_id, opp_id, pasx_id, event, payload, status, http_status, received_at)
     VALUES (?, ?, ?, ?, ?, ?, 200, NOW())"
);
$payload_json = json_encode($data, JSON_UNESCAPED_UNICODE);
$log_stmt->bind_param("isssss", $pakd_id, $opp_id, $pasx_id, $event, $payload_json, $status);
$log_stmt->execute();
$log_stmt->close();

// ── Cập nhật pakd nếu tìm được bản ghi ──
if ($pakd_id) {
    // Đảm bảo các cột tồn tại
    foreach ([
        "ALTER TABLE pakd ADD COLUMN pasx_status VARCHAR(32) DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN pasx_id VARCHAR(64) DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN pasx_requested_at DATETIME DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN fin_data JSON DEFAULT NULL",
    ] as $sql) { try { $conn->query($sql); } catch (\Throwable $e) {} }

    // Cập nhật pasx_status
    if ($status) {
        $st = $conn->prepare("UPDATE pakd SET pasx_status=? WHERE id=?");
        $st->bind_param("si", $status, $pakd_id);
        $st->execute();
        $st->close();
    }

    // Cập nhật fin_data với humanCost / overtimeCost nếu có
    if ($human_cost !== null || $overtime !== null) {
        try { $conn->query("ALTER TABLE pakd ADD COLUMN fin_data JSON DEFAULT NULL"); } catch (\Throwable $e) {}

        try {
            $fr = $conn->prepare("SELECT fin_data FROM pakd WHERE id=?");
            $fr->bind_param("i", $pakd_id);
            $fr->execute();
            $row      = $fr->get_result()->fetch_assoc();
            $fr->close();
            $fin_data = !empty($row['fin_data']) ? (json_decode($row['fin_data'], true) ?? []) : [];

            if ($human_cost !== null) $fin_data['human_cost']    = $human_cost;
            if ($overtime   !== null) $fin_data['overtime_cost'] = $overtime;

            $fu = $conn->prepare("UPDATE pakd SET fin_data=? WHERE id=?");
            $fj = json_encode($fin_data);
            $fu->bind_param("si", $fj, $pakd_id);
            $fu->execute();
            $fu->close();
        } catch (\Throwable $e) {
            error_log('[pasx_callback] fin_data update error: ' . $e->getMessage());
        }
    }
}

http_response_code(200);
echo json_encode([
    'ok'      => true,
    'msg'     => 'Callback received',
    'opp_id'  => $opp_id,
    'pakd_id' => $pakd_id,
    'event'   => $event,
    'status'  => $status,
]);
