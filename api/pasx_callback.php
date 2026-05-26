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

// Thêm cột mới vào log table nếu chưa có
try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN opp_id        VARCHAR(64)  DEFAULT NULL AFTER pakd_id");   } catch (\Throwable $e) {}
try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN submitted_by  VARCHAR(255) DEFAULT NULL AFTER note");       } catch (\Throwable $e) {}
try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN submitted_at  DATETIME     DEFAULT NULL AFTER submitted_by"); } catch (\Throwable $e) {}
try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN meta          JSON         DEFAULT NULL AFTER submitted_at"); } catch (\Throwable $e) {}

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

$opp_id               = isset($data['oppId'])        ? (string)$data['oppId']       : null;
$pasx_id              = $data['pasxId']              ?? null;
$event                = $data['event']               ?? 'callback';
$status               = $data['status']              ?? null;
$human_cost           = isset($data['humanCost'])    ? (float)$data['humanCost']    : null;
$overtime             = isset($data['overtimeCost']) ? (float)$data['overtimeCost'] : null;
$pasx_cost            = (isset($data['pasxCost']) && is_array($data['pasxCost'])) ? $data['pasxCost'] : null;
$pakd_id_from_payload = isset($data['pakdId'])       ? (int)$data['pakdId']         : null;
// Ghi chú từ Profile
$pasx_note            = $data['resubmitNote'] ?? $data['note'] ?? $data['message'] ?? null;

// ── Parse _meta ──
$meta         = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : null;
$meta_opp_id  = $meta['oppId']                                    ?? null;   // oppId trong meta
$submitted_by = $meta['submittedBy']['fullName']                  ?? null;
// Ghi chú từ _meta.resubmitNote (field chính thức của Profile)
if (!$pasx_note && $meta) $pasx_note = $meta['resubmitNote'] ?? $meta['note'] ?? null;
$submitted_at = null;
if (!empty($meta['submittedAt'])) {
    try {
        $dt = new \DateTime($meta['submittedAt']);
        $submitted_at = $dt->format('Y-m-d H:i:s');
    } catch (\Throwable $e) {}
}
// Nếu không có oppId ở top-level thì lấy từ meta
if (!$opp_id && $meta_opp_id) $opp_id = (string)$meta_opp_id;

// ── Lookup pakd: 1) pakdId trực tiếp  2) oppId top-level / _meta ──
$pakd_id = null;
if ($pakd_id_from_payload) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE id = ? LIMIT 1");
    $lk->bind_param("i", $pakd_id_from_payload);
    $lk->execute();
    $lk_row  = $lk->get_result()->fetch_assoc();
    $lk->close();
    $pakd_id = $lk_row ? (int)$lk_row['id'] : null;
}
if (!$pakd_id && $opp_id) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE odoo_opp_id = ? LIMIT 1");
    $lk->bind_param("s", $opp_id);
    $lk->execute();
    $lk_row  = $lk->get_result()->fetch_assoc();
    $lk->close();
    $pakd_id = $lk_row ? (int)$lk_row['id'] : null;
}

// ── Ghi log ──
$log_stmt = $conn->prepare(
    "INSERT INTO pasx_webhook_logs
        (pakd_id, opp_id, pasx_id, event, payload, status, http_status, submitted_by, submitted_at, meta, received_at)
     VALUES (?, ?, ?, ?, ?, ?, 200, ?, ?, ?, NOW())"
);
$payload_json = json_encode($data, JSON_UNESCAPED_UNICODE);
$meta_json    = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
$log_stmt->bind_param("issssssss",
    $pakd_id, $opp_id, $pasx_id, $event, $payload_json, $status,
    $submitted_by, $submitted_at, $meta_json
);
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

    // Cập nhật fin_data với humanCost / overtimeCost / pasxCost nếu có
    if ($human_cost !== null || $overtime !== null || $pasx_cost !== null) {
        try { $conn->query("ALTER TABLE pakd ADD COLUMN fin_data JSON DEFAULT NULL"); } catch (\Throwable $e) {}

        try {
            $fr = $conn->prepare("SELECT fin_data FROM pakd WHERE id=?");
            $fr->bind_param("i", $pakd_id);
            $fr->execute();
            $row      = $fr->get_result()->fetch_assoc();
            $fr->close();
            $fin_data = !empty($row['fin_data']) ? (json_decode($row['fin_data'], true) ?? []) : [];

            if ($human_cost  !== null) $fin_data['human_cost']    = $human_cost;
            if ($overtime    !== null) $fin_data['overtime_cost'] = $overtime;
            if ($pasx_cost   !== null) $fin_data['pasx_cost']     = $pasx_cost; // array chi tiết nhân công
            if ($pasx_note   !== null) $fin_data['pasx_note']     = $pasx_note; // ghi chú từ Profile

            $fu = $conn->prepare("UPDATE pakd SET fin_data=? WHERE id=?");
            $fj = json_encode($fin_data, JSON_UNESCAPED_UNICODE);
            $fu->bind_param("si", $fj, $pakd_id);
            $fu->execute();
            $fu->close();
        } catch (\Throwable $e) {
            error_log('[pasx_callback] fin_data update error: ' . $e->getMessage());
        }
    }

    // ── Tạo thông báo cho AM ──
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS pasx_notifications (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            pakd_id       INT NOT NULL,
            pasx_id       VARCHAR(64) DEFAULT NULL,
            event         VARCHAR(64) DEFAULT NULL,
            status        VARCHAR(32) DEFAULT NULL,
            human_cost    DECIMAL(20,2) DEFAULT NULL,
            overtime_cost DECIMAL(20,2) DEFAULT NULL,
            opp_name      VARCHAR(255) DEFAULT NULL,
            submitted_by  VARCHAR(255) DEFAULT NULL,
            is_read       TINYINT(1) DEFAULT 0,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_read (user_id, is_read)
        )");

        // Lấy am_name và opportunity_name từ pakd
        $pr = $conn->prepare("SELECT am_name, opportunity_name FROM pakd WHERE id=? LIMIT 1");
        $pr->bind_param("i", $pakd_id);
        $pr->execute();
        $pakd_row = $pr->get_result()->fetch_assoc();
        $pr->close();

        if ($pakd_row) {
            $am_full_name = $pakd_row['am_name']          ?? null;
            $opp_name_val = $pakd_row['opportunity_name'] ?? null;

            if ($am_full_name) {
                $ur = $conn->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
                $ur->bind_param("s", $am_full_name);
                $ur->execute();
                $user_row = $ur->get_result()->fetch_assoc();
                $ur->close();

                if ($user_row) {
                    $am_user_id = (int)$user_row['id'];
                    try { $conn->query("ALTER TABLE pasx_notifications ADD COLUMN message TEXT DEFAULT NULL"); } catch (\Throwable $e) {}
                    $ni = $conn->prepare(
                        "INSERT INTO pasx_notifications
                            (user_id, pakd_id, pasx_id, event, status, human_cost, overtime_cost, opp_name, submitted_by, message)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $ni->bind_param("iisssddsss",
                        $am_user_id, $pakd_id, $pasx_id, $event, $status,
                        $human_cost, $overtime, $opp_name_val, $submitted_by, $pasx_note
                    );
                    $ni->execute();
                    $ni->close();
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[pasx_callback] notification insert error: ' . $e->getMessage());
    }
}

http_response_code(200);
echo json_encode([
    'ok'           => true,
    'msg'          => 'Callback received',
    'opp_id'       => $opp_id,
    'pakd_id'      => $pakd_id,
    'event'        => $event,
    'status'       => $status,
    'submitted_by' => $submitted_by,
]);
