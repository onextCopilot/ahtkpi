<?php
/**
 * Đẩy thông tin thanh toán (hoá đơn) của một milestone sang hệ thống sản xuất (OS).
 * POST /api/milestone_push_payment   (session auth: admin hoặc AM của dự án)
 *
 * Body (form-urlencoded hoặc JSON):
 *   pakd_id           int   (bắt buộc)
 *   milestone_id      int   (id nội bộ trong pakd_milestones — bắt buộc)
 *   inv_id            int   (odoo_invoices.odoo_id — bắt buộc)
 *   production_price  float (tuỳ chọn)
 *   note              string(tuỳ chọn)
 *   paid_at           string YYYY-MM-DD (tuỳ chọn, override)
 *
 * Gọi:  POST {api_url}/integrations/os/milestones/{osMilestoneId}/payment
 * Header: X-API-Key, X-Timestamp, X-Request-Id, Content-Type: application/json
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập']); exit;
}
$user_id  = (int)$_SESSION['user_id'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

// Hỗ trợ cả JSON body lẫn form post
$in = $_POST;
if (empty($in)) {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (is_array($raw)) $in = $raw;
}

$pakd_id      = (int)($in['pakd_id'] ?? 0);
$milestone_id = (int)($in['milestone_id'] ?? 0);
$inv_id       = (int)($in['inv_id'] ?? 0);
$prod_price   = isset($in['production_price']) && $in['production_price'] !== ''
              ? (float)preg_replace('/[^\d.\-]/', '', (string)$in['production_price']) : null;
$note         = trim((string)($in['note'] ?? ''));
$paid_at_in   = trim((string)($in['paid_at'] ?? ''));

if (!$pakd_id || !$milestone_id || !$inv_id) {
    echo json_encode(['ok' => false, 'msg' => 'Thiếu tham số (pakd_id / milestone_id / inv_id)']); exit;
}

// ── Quyền: admin hoặc AM của dự án ─────────────────────────────────────────────
$pr = $conn->prepare("SELECT id, am_user_id, project_code FROM pakd WHERE id = ? LIMIT 1");
$pr->bind_param("i", $pakd_id);
$pr->execute();
$pakd = $pr->get_result()->fetch_assoc();
$pr->close();
if (!$pakd) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy dự án']); exit; }
if (!$is_admin && (int)($pakd['am_user_id'] ?? 0) !== $user_id) {
    echo json_encode(['ok' => false, 'msg' => 'Không có quyền']); exit;
}

// ── Milestone ──────────────────────────────────────────────────────────────────
$ms_stmt = $conn->prepare("SELECT * FROM pakd_milestones WHERE id = ? AND pakd_id = ? LIMIT 1");
$ms_stmt->bind_param("ii", $milestone_id, $pakd_id);
$ms_stmt->execute();
$ms = $ms_stmt->get_result()->fetch_assoc();
$ms_stmt->close();
if (!$ms) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy milestone']); exit; }
$os_milestone_id = (string)$ms['os_milestone_id'];
$project_code    = $ms['project_code'] ?: ($pakd['project_code'] ?? null);

// ── Hoá đơn ────────────────────────────────────────────────────────────────────
$iv = $conn->prepare("SELECT odoo_id, name, amount_total, amount_residual, currency_name, payment_state, invoice_date
                      FROM odoo_invoices WHERE odoo_id = ? LIMIT 1");
$iv->bind_param("i", $inv_id);
$iv->execute();
$inv = $iv->get_result()->fetch_assoc();
$iv->close();
if (!$inv) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy hoá đơn']); exit; }

// Map payment_state -> chữ hoa cho OS
$pmap = ['paid'=>'PAID','not_paid'=>'NOT_PAID','partial'=>'PARTIAL','in_payment'=>'IN_PAYMENT','reversed'=>'REVERSED'];
$payment_state = $pmap[strtolower((string)$inv['payment_state'])] ?? strtoupper((string)$inv['payment_state']);

// paidAt: ưu tiên input -> ngày HĐ -> hôm nay
$paid_at = $paid_at_in ?: ($inv['invoice_date'] ?: date('Y-m-d'));

// invoiceUrl từ Odoo
$odooBaseUrl = '';
try {
    $os = $conn->query("SELECT odoo_url FROM odoo_settings ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $odooBaseUrl = rtrim($os['odoo_url'] ?? '', '/');
} catch (\Throwable $e) {}
$invoice_url = $odooBaseUrl ? ($odooBaseUrl . '/web#id=' . (int)$inv['odoo_id'] . '&model=account.move&view_type=form') : null;

// ── Cấu hình ArrowHitech ────────────────────────────────────────────────────────
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
if (!file_exists($configFile)) { echo json_encode(['ok' => false, 'msg' => 'Chưa cấu hình ArrowHitech API (Settings)']); exit; }
$cfg       = json_decode(file_get_contents($configFile), true) ?: [];
$api_url   = rtrim($cfg['api_url'] ?? '', '/');
$api_token = $cfg['api_token'] ?? '';
if (!$api_url || !$api_token) { echo json_encode(['ok' => false, 'msg' => 'Thiếu api_url hoặc api_token']); exit; }
if (!$os_milestone_id) { echo json_encode(['ok' => false, 'msg' => 'Milestone thiếu os_milestone_id']); exit; }

// ── Payload ─────────────────────────────────────────────────────────────────────
$payload = [
    'occurredAt'  => gmdate('Y-m-d\TH:i:s') . '.000Z',
    'projectCode' => $project_code,
    'invoice'     => [
        'invoiceCode'     => $inv['name'],
        'amount'          => (float)$inv['amount_total'],
        'productionPrice' => $prod_price,
        'currency'        => $inv['currency_name'] ?: 'VND',
        'paidAt'          => $paid_at,
        'paymentState'    => $payment_state,
        'invoiceUrl'      => $invoice_url,
        'note'            => $note !== '' ? $note : null,
    ],
];

$endpoint   = $api_url . '/integrations/os/milestones/' . rawurlencode($os_milestone_id) . '/payment';
$timestamp  = (int)(microtime(true) * 1000);
$request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
    mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
    mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

$body_json = json_encode($payload, JSON_UNESCAPED_UNICODE);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body_json,
    CURLOPT_HTTPHEADER     => [
        'X-API-Key: '    . $api_token,
        'X-Timestamp: '  . $timestamp,
        'X-Request-Id: ' . $request_id,
        'Content-Type: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15,
]);
$response  = curl_exec($ch);
$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

// ── Ghi log outbound (dùng chung milestone_webhook_logs) ───────────────────────
function mpp_log($conn, $pakd_id, $os_milestone_id, $status, $payload_json, $http_code, $note) {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS milestone_webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY, pakd_id INT DEFAULT NULL, os_milestone_id VARCHAR(64) DEFAULT NULL,
            event VARCHAR(64) DEFAULT NULL, status VARCHAR(32) DEFAULT NULL, payload JSON DEFAULT NULL,
            http_status INT DEFAULT 200, note TEXT DEFAULT NULL, received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pakd (pakd_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ev = 'payment_pushed';
        $st = $conn->prepare("INSERT INTO milestone_webhook_logs (pakd_id, os_milestone_id, event, status, payload, http_status, note) VALUES (?,?,?,?,?,?,?)");
        $st->bind_param("issssis", $pakd_id, $os_milestone_id, $ev, $status, $payload_json, $http_code, $note);
        $st->execute(); $st->close();
    } catch (\Throwable $e) {}
}

$log_payload = json_encode(['request' => $payload, 'response' => $response ? (json_decode($response, true) ?: $response) : null], JSON_UNESCAPED_UNICODE);

if ($curl_err) {
    mpp_log($conn, $pakd_id, $os_milestone_id, 'error', $log_payload, $http_code ?: 0, 'curl: ' . $curl_err);
    echo json_encode(['ok' => false, 'msg' => 'Lỗi kết nối: ' . $curl_err]); exit;
}

$resp = json_decode($response, true);
if ($http_code >= 200 && $http_code < 300 && (($resp['success'] ?? false) === true || $http_code === 200)) {
    $data = $resp['data'] ?? [];
    $new_pay_status = $data['paymentStatus'] ?? null;       // vd "DONE"
    $total_paid     = isset($data['totalPaid']) ? (float)$data['totalPaid'] : (float)$inv['amount_total'];
    $resp_currency  = $data['currency'] ?? ($inv['currency_name'] ?: 'VND');

    // Cập nhật milestone: lưu hoá đơn đã gắn + trạng thái thanh toán
    foreach ([
        "ALTER TABLE pakd_milestones ADD COLUMN paid_invoice_code VARCHAR(64) DEFAULT NULL",
        "ALTER TABLE pakd_milestones ADD COLUMN paid_invoice_odoo_id INT DEFAULT NULL",
        "ALTER TABLE pakd_milestones ADD COLUMN paid_amount DECIMAL(20,2) DEFAULT NULL",
        "ALTER TABLE pakd_milestones ADD COLUMN production_price DECIMAL(20,2) DEFAULT NULL",
        "ALTER TABLE pakd_milestones ADD COLUMN paid_currency VARCHAR(10) DEFAULT NULL",
        "ALTER TABLE pakd_milestones ADD COLUMN paid_at DATE DEFAULT NULL",
        "ALTER TABLE pakd_milestones ADD COLUMN payment_pushed_at DATETIME DEFAULT NULL",
    ] as $sql) { try { $conn->query($sql); } catch (\Throwable $e) {} }

    $inv_code = $inv['name'];
    $inv_oid  = (int)$inv['odoo_id'];
    $pay_for_db = $new_pay_status ?: $payment_state;
    $up = $conn->prepare("UPDATE pakd_milestones
        SET paid_invoice_code=?, paid_invoice_odoo_id=?, paid_amount=?, production_price=?, paid_currency=?, paid_at=?, payment_status=?, payment_pushed_at=NOW()
        WHERE id=?");
    $up->bind_param("siddsssi", $inv_code, $inv_oid, $total_paid, $prod_price, $resp_currency, $paid_at, $pay_for_db, $milestone_id);
    $up->execute();
    $up->close();

    mpp_log($conn, $pakd_id, $os_milestone_id, 'ok', $log_payload, $http_code, 'invoice ' . $inv_code);
    echo json_encode(['ok' => true, 'msg' => 'Đã đẩy thanh toán sang hệ thống sản xuất', 'data' => $data, 'payment_status' => $pay_for_db]);
    exit;
}

// Lỗi từ API
$err_msg = $resp['message'] ?? $resp['error'] ?? ('HTTP ' . $http_code);
mpp_log($conn, $pakd_id, $os_milestone_id, 'error', $log_payload, $http_code, $err_msg);
echo json_encode(['ok' => false, 'msg' => 'API trả về lỗi: ' . $err_msg, 'http_code' => $http_code]);
