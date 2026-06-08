<?php
/**
 * Webhook: nhận đồng bộ milestone từ hệ thống sản xuất (OS / ArrowHitech)
 * POST /integrations/hrm/milestones/sync
 * Header: X-Api-Key: <key>
 *
 * Body:
 * {
 *   "event": "created" | "updated" | "deleted",
 *   "occurredAt": "2026-06-08T03:21:00.000Z",
 *   "pakd":      { "pakdId": "123", "integrationId": "321", "oppId": "1", "projectCode": "MAVDS2601VAP" },
 *   "milestone": { "milestoneId": "...", "order": 1, "name": "...", "type": "CLIENT",
 *                  "status": "IN_PROGRESS", "startDate": "2026-06-01", "deliveryDate": "2026-06-30",
 *                  "budgetPercent": 0.25, "billablePercent": 0.30, "billableHours": 120,
 *                  "deliverables": "...", "acceptanceCriteria": "...",
 *                  "onTime": "PENDING", "paymentStatus": "NOT_YET",
 *                  "createdAt": "...", "updatedAt": "..." }
 * }
 *
 * Response: { "success": true, "data": { "milestoneId": "...", "osMilestoneId": "OSM-...", "received": true } }
 *
 * Resolve pakd: 1) pakd.pakdId (= pakd.id)  2) pakd.oppId (= odoo_opp_id)  3) pakd.projectCode (= project_code)
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

/** Trả lỗi chuẩn */
function ms_fail(int $code, string $msg, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/** ISO-8601 / 'Y-m-d' -> MySQL DATETIME (hoặc null) */
function ms_dt($v): ?string {
    if (empty($v) || !is_string($v)) return null;
    try { return (new \DateTime($v))->format('Y-m-d H:i:s'); } catch (\Throwable $e) { return null; }
}
/** 'Y-m-d' -> MySQL DATE (hoặc null) */
function ms_date($v): ?string {
    if (empty($v) || !is_string($v)) return null;
    try { return (new \DateTime($v))->format('Y-m-d'); } catch (\Throwable $e) { return null; }
}

/** Ghi log mọi event webhook (audit) */
function ms_log($conn, ?int $pakd_id, ?string $os_milestone_id, ?string $event, ?string $status, ?string $payload, int $http_status, ?string $note): void {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS milestone_webhook_logs (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            pakd_id         INT          DEFAULT NULL,
            os_milestone_id VARCHAR(64)  DEFAULT NULL,
            event           VARCHAR(64)  DEFAULT NULL,
            status          VARCHAR(32)  DEFAULT NULL,
            payload         JSON         DEFAULT NULL,
            http_status     INT          DEFAULT 200,
            note            TEXT         DEFAULT NULL,
            received_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pakd (pakd_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st = $conn->prepare("INSERT INTO milestone_webhook_logs
            (pakd_id, os_milestone_id, event, status, payload, http_status, note)
            VALUES (?,?,?,?,?,?,?)");
        $st->bind_param("issssis", $pakd_id, $os_milestone_id, $event, $status, $payload, $http_status, $note);
        $st->execute();
        $st->close();
    } catch (\Throwable $e) { error_log('[milestones_sync] log error: ' . $e->getMessage()); }
}

/** Tạo thông báo cho AM của dự án khi milestone thay đổi */
function ms_notify($conn, ?int $pakd_id, string $event, array $ms): void {
    if (!$pakd_id) return; // chưa map được dự án -> không có AM để báo
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
            message       TEXT DEFAULT NULL,
            is_read       TINYINT(1) DEFAULT 0,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_read (user_id, is_read)
        )");
        try { $conn->query("ALTER TABLE pasx_notifications ADD COLUMN message TEXT DEFAULT NULL"); } catch (\Throwable $e) {}

        // Lấy AM + tên cơ hội
        $pr = $conn->prepare("SELECT am_user_id, am_name, opportunity_name FROM pakd WHERE id=? LIMIT 1");
        $pr->bind_param("i", $pakd_id);
        $pr->execute();
        $row = $pr->get_result()->fetch_assoc();
        $pr->close();
        if (!$row) return;

        $am_user_id = (int)($row['am_user_id'] ?? 0);
        if (!$am_user_id && !empty($row['am_name'])) {
            $ur = $conn->prepare("SELECT id FROM users WHERE full_name=? LIMIT 1");
            $ur->bind_param("s", $row['am_name']);
            $ur->execute();
            $u = $ur->get_result()->fetch_assoc();
            $ur->close();
            if ($u) $am_user_id = (int)$u['id'];
        }
        if (!$am_user_id) return;

        $opp_name = $row['opportunity_name'] ?? null;
        $status   = isset($ms['status']) ? (string)$ms['status'] : null;
        $msid     = isset($ms['milestoneId']) ? (string)$ms['milestoneId'] : null;
        $mname    = !empty($ms['name']) ? (string)$ms['name'] : 'Milestone';
        $evMap    = ['created'=>'milestone_created','updated'=>'milestone_updated','deleted'=>'milestone_deleted'];
        $verbMap  = ['created'=>'được tạo','updated'=>'được cập nhật','deleted'=>'đã bị xoá'];
        $nev      = $evMap[$event]   ?? 'milestone_updated';
        $verb     = $verbMap[$event] ?? 'được cập nhật';
        $msg      = 'Milestone "' . $mname . '" ' . $verb . ($status ? ' · ' . $status : '');
        $submitted_by = null;

        $ni = $conn->prepare("INSERT INTO pasx_notifications
            (user_id, pakd_id, pasx_id, event, status, opp_name, submitted_by, message)
            VALUES (?,?,?,?,?,?,?,?)");
        $ni->bind_param("iissssss", $am_user_id, $pakd_id, $msid, $nev, $status, $opp_name, $submitted_by, $msg);
        $ni->execute();
        $ni->close();
    } catch (\Throwable $e) { error_log('[milestones_sync] notify error: ' . $e->getMessage()); }
}

// ── Bảng milestone ───────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd_milestones (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id             INT            DEFAULT NULL,
    os_milestone_id     VARCHAR(64)    NOT NULL,
    integration_id      VARCHAR(64)    DEFAULT NULL,
    opp_id              VARCHAR(64)    DEFAULT NULL,
    project_code        VARCHAR(64)    DEFAULT NULL,
    sort_order          INT            DEFAULT 0,
    name                VARCHAR(500)   DEFAULT NULL,
    type                VARCHAR(32)    DEFAULT NULL,
    status              VARCHAR(32)    DEFAULT NULL,
    start_date          DATE           DEFAULT NULL,
    delivery_date       DATE           DEFAULT NULL,
    budget_percent      DECIMAL(8,4)   DEFAULT NULL,
    billable_percent    DECIMAL(8,4)   DEFAULT NULL,
    billable_hours      DECIMAL(12,2)  DEFAULT NULL,
    deliverables        TEXT           DEFAULT NULL,
    acceptance_criteria TEXT           DEFAULT NULL,
    on_time             VARCHAR(32)    DEFAULT NULL,
    payment_status      VARCHAR(32)    DEFAULT NULL,
    os_created_at       DATETIME       DEFAULT NULL,
    os_updated_at       DATETIME       DEFAULT NULL,
    raw_payload         JSON           DEFAULT NULL,
    synced_at           DATETIME       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_os_milestone (os_milestone_id),
    INDEX idx_pakd (pakd_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Đảm bảo pakd có cột project_code để lưu mã dự án từ hệ thống sản xuất
try { $conn->query("ALTER TABLE pakd ADD COLUMN project_code VARCHAR(64) DEFAULT NULL"); } catch (\Throwable $e) {}

$raw_body = file_get_contents('php://input');

// ── Xác thực X-Api-Key ─────────────────────────────────────────────────────────
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
$cfg        = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
$expected   = $cfg['milestone_api_key'] ?? $cfg['webhook_secret'] ?? '';

$received = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';

if ($expected === '') {
    ms_fail(500, 'Milestone API key chưa được cấu hình trên server');
}
if (!is_string($received) || !hash_equals($expected, $received)) {
    ms_log($conn, null, null, 'auth_failed', null, substr($raw_body, 0, 500), 401, 'Invalid X-Api-Key');
    ms_fail(401, 'Unauthorized');
}

// ── Parse payload ──────────────────────────────────────────────────────────────
$data = json_decode($raw_body, true);
if (!is_array($data)) {
    ms_log($conn, null, null, 'invalid_json', null, substr($raw_body, 0, 500), 400, 'Invalid JSON body');
    ms_fail(400, 'Invalid JSON body');
}

$event = $data['event'] ?? 'updated';
$pk    = is_array($data['pakd'] ?? null)      ? $data['pakd']      : [];
$ms    = is_array($data['milestone'] ?? null) ? $data['milestone'] : [];

$os_milestone_id = isset($ms['milestoneId']) ? (string)$ms['milestoneId'] : '';
if ($os_milestone_id === '') {
    ms_fail(400, 'Thiếu milestone.milestoneId');
}

$pakd_id_in     = isset($pk['pakdId'])        ? (string)$pk['pakdId']        : '';
$integration_id = isset($pk['integrationId']) ? (string)$pk['integrationId'] : null;
$opp_id         = isset($pk['oppId'])         ? (string)$pk['oppId']         : null;
$project_code   = isset($pk['projectCode'])   ? (string)$pk['projectCode']   : null;

// ── Resolve pakd_id: pakdId -> oppId -> projectCode ─────────────────────────────
$pakd_id = null;
if ($pakd_id_in !== '' && ctype_digit($pakd_id_in)) {
    $lk = $conn->prepare("SELECT id FROM pakd WHERE id = ? LIMIT 1");
    $lk->bind_param("i", $pakd_id_in);
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
if (!$pakd_id && $project_code) {
    // Cột project_code có thể chưa tồn tại — bọc try để không vỡ
    try {
        $lk = $conn->prepare("SELECT id FROM pakd WHERE project_code = ? LIMIT 1");
        if ($lk) {
            $lk->bind_param("s", $project_code);
            $lk->execute();
            $row = $lk->get_result()->fetch_assoc();
            $lk->close();
            if ($row) $pakd_id = (int)$row['id'];
        }
    } catch (\Throwable $e) {}
}

// ── Backfill project_code lên pakd (chỉ ghi khi đang trống, tránh đè dữ liệu) ──
if ($pakd_id && $project_code) {
    try {
        $bf = $conn->prepare("UPDATE pakd SET project_code = ? WHERE id = ? AND (project_code IS NULL OR project_code = '')");
        $bf->bind_param("si", $project_code, $pakd_id);
        $bf->execute();
        $bf->close();
    } catch (\Throwable $e) {}
}

// ── Event: deleted ──────────────────────────────────────────────────────────────
if ($event === 'deleted') {
    $del = $conn->prepare("DELETE FROM pakd_milestones WHERE os_milestone_id = ?");
    $del->bind_param("s", $os_milestone_id);
    $del->execute();
    $del->close();
    ms_log($conn, $pakd_id, $os_milestone_id, 'deleted', $ms['status'] ?? null, $raw_body, 200,
        $pakd_id ? null : 'Không map được dự án (orphan)');
    ms_notify($conn, $pakd_id, 'deleted', $ms);
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data'    => ['milestoneId' => $os_milestone_id, 'osMilestoneId' => null, 'received' => true, 'deleted' => true],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Upsert (created / updated) ──────────────────────────────────────────────────
$sort_order      = isset($ms['order']) ? (int)$ms['order'] : 0;
$name            = isset($ms['name']) ? (string)$ms['name'] : null;
$type            = isset($ms['type']) ? (string)$ms['type'] : null;
$status          = isset($ms['status']) ? (string)$ms['status'] : null;
$start_date      = ms_date($ms['startDate']    ?? null);
$delivery_date   = ms_date($ms['deliveryDate'] ?? null);
$budget_pct      = isset($ms['budgetPercent'])   ? (float)$ms['budgetPercent']   : null;
$billable_pct    = isset($ms['billablePercent']) ? (float)$ms['billablePercent'] : null;
$billable_hours  = isset($ms['billableHours'])   ? (float)$ms['billableHours']   : null;
$deliverables    = isset($ms['deliverables']) ? (string)$ms['deliverables'] : null;
$acceptance      = isset($ms['acceptanceCriteria']) ? (string)$ms['acceptanceCriteria'] : null;
$on_time         = isset($ms['onTime']) ? (string)$ms['onTime'] : null;
$payment_status  = isset($ms['paymentStatus']) ? (string)$ms['paymentStatus'] : null;
$os_created_at   = ms_dt($ms['createdAt'] ?? null);
$os_updated_at   = ms_dt($ms['updatedAt'] ?? null);
$raw_json        = json_encode($data, JSON_UNESCAPED_UNICODE);

$sql = "INSERT INTO pakd_milestones
    (pakd_id, os_milestone_id, integration_id, opp_id, project_code, sort_order, name, type, status,
     start_date, delivery_date, budget_percent, billable_percent, billable_hours, deliverables,
     acceptance_criteria, on_time, payment_status, os_created_at, os_updated_at, raw_payload, synced_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE
        pakd_id=VALUES(pakd_id), integration_id=VALUES(integration_id), opp_id=VALUES(opp_id),
        project_code=VALUES(project_code), sort_order=VALUES(sort_order), name=VALUES(name),
        type=VALUES(type), status=VALUES(status), start_date=VALUES(start_date),
        delivery_date=VALUES(delivery_date), budget_percent=VALUES(budget_percent),
        billable_percent=VALUES(billable_percent), billable_hours=VALUES(billable_hours),
        deliverables=VALUES(deliverables), acceptance_criteria=VALUES(acceptance_criteria),
        on_time=VALUES(on_time), payment_status=VALUES(payment_status),
        os_created_at=VALUES(os_created_at), os_updated_at=VALUES(os_updated_at),
        raw_payload=VALUES(raw_payload), synced_at=NOW()";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    ms_fail(500, 'DB prepare error: ' . $conn->error);
}
$stmt->bind_param(
    "issssisssssdddsssssss",
    $pakd_id, $os_milestone_id, $integration_id, $opp_id, $project_code, $sort_order,
    $name, $type, $status, $start_date, $delivery_date,
    $budget_pct, $billable_pct, $billable_hours, $deliverables, $acceptance,
    $on_time, $payment_status, $os_created_at, $os_updated_at, $raw_json
);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    ms_fail(500, 'DB write error: ' . $conn->error);
}

// Lấy id nội bộ để sinh osMilestoneId echo về
$internal_id = null;
$gid = $conn->prepare("SELECT id FROM pakd_milestones WHERE os_milestone_id = ? LIMIT 1");
$gid->bind_param("s", $os_milestone_id);
$gid->execute();
$grow = $gid->get_result()->fetch_assoc();
$gid->close();
if ($grow) $internal_id = (int)$grow['id'];

// Log + thông báo cho AM (event = created | updated)
$log_event = ($event === 'created') ? 'created' : 'updated';
ms_log($conn, $pakd_id, $os_milestone_id, $log_event, $status, $raw_body, 200,
    $pakd_id ? null : 'Không map được dự án (orphan)');
ms_notify($conn, $pakd_id, $log_event, $ms);

http_response_code(200);
echo json_encode([
    'success' => true,
    'data'    => [
        'milestoneId'   => $os_milestone_id,
        'osMilestoneId' => $internal_id ? ('OSM-' . $internal_id) : null,
        'received'      => true,
        'pakdId'        => $pakd_id,            // null nếu chưa map được dự án
        'matched'       => $pakd_id !== null,
    ],
], JSON_UNESCAPED_UNICODE);
