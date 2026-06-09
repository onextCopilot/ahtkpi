<?php
/**
 * Đẩy thông tin thanh toán (1 hoặc NHIỀU hoá đơn) của một milestone sang hệ thống sản xuất (OS).
 * POST /api/milestone_push_payment   (session auth: admin hoặc AM của dự án)
 *
 * Body (form-urlencoded hoặc JSON):
 *   pakd_id       int            (bắt buộc)
 *   milestone_id  int            (id nội bộ pakd_milestones — bắt buộc)
 *   inv_ids       int[]|string   (danh sách odoo_invoices.odoo_id — hỗ trợ mảng hoặc "1,2,3")
 *   inv_id        int            (tương thích ngược: 1 hoá đơn)
 *   note          string         (tuỳ chọn, áp cho mọi hoá đơn)
 *
 * Mỗi hoá đơn được đẩy bằng 1 lần gọi:
 *   POST {api_url}/integrations/os/milestones/{osMilestoneId}/payment
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) { echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập']); exit; }
$user_id  = (int)$_SESSION['user_id'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

$in = $_POST;
if (empty($in)) { $raw = json_decode(file_get_contents('php://input'), true); if (is_array($raw)) $in = $raw; }

$pakd_id      = (int)($in['pakd_id'] ?? 0);
$milestone_id = (int)($in['milestone_id'] ?? 0);
$note         = trim((string)($in['note'] ?? ''));

// Gom danh sách invoice id (mảng / chuỗi "1,2" / 1 giá trị)
$inv_ids = [];
if (isset($in['inv_ids'])) {
    $raw = $in['inv_ids'];
    if (is_array($raw)) $inv_ids = $raw;
    else                $inv_ids = explode(',', (string)$raw);
} elseif (isset($in['inv_id'])) {
    $inv_ids = [$in['inv_id']];
}
$inv_ids = array_values(array_unique(array_filter(array_map('intval', $inv_ids), fn($x) => $x > 0)));

if (!$pakd_id || !$milestone_id || empty($inv_ids)) {
    echo json_encode(['ok' => false, 'msg' => 'Thiếu tham số (pakd_id / milestone_id / inv_ids)']); exit;
}

// ── Quyền ────────────────────────────────────────────────────────────────────
$pr = $conn->prepare("SELECT id, am_user_id, project_code FROM pakd WHERE id = ? LIMIT 1");
$pr->bind_param("i", $pakd_id); $pr->execute();
$pakd = $pr->get_result()->fetch_assoc(); $pr->close();
if (!$pakd) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy dự án']); exit; }
if (!$is_admin && (int)($pakd['am_user_id'] ?? 0) !== $user_id) { echo json_encode(['ok' => false, 'msg' => 'Không có quyền']); exit; }

// ── Milestone ──────────────────────────────────────────────────────────────────
$ms_stmt = $conn->prepare("SELECT * FROM pakd_milestones WHERE id = ? AND pakd_id = ? LIMIT 1");
$ms_stmt->bind_param("ii", $milestone_id, $pakd_id); $ms_stmt->execute();
$ms = $ms_stmt->get_result()->fetch_assoc(); $ms_stmt->close();
if (!$ms) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy milestone']); exit; }
$os_milestone_id = (string)$ms['os_milestone_id'];
$project_code    = $ms['project_code'] ?: ($pakd['project_code'] ?? null);
if (!$os_milestone_id) { echo json_encode(['ok' => false, 'msg' => 'Milestone thiếu os_milestone_id']); exit; }

// ── Cấu hình ArrowHitech ────────────────────────────────────────────────────────
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
if (!file_exists($configFile)) { echo json_encode(['ok' => false, 'msg' => 'Chưa cấu hình ArrowHitech API (Settings)']); exit; }
$cfg       = json_decode(file_get_contents($configFile), true) ?: [];
$api_url   = rtrim($cfg['api_url'] ?? '', '/');
$api_token = $cfg['api_token'] ?? '';
if (!$api_url || !$api_token) { echo json_encode(['ok' => false, 'msg' => 'Thiếu api_url hoặc api_token']); exit; }

// Odoo base url cho invoiceUrl
$odooBaseUrl = '';
try { $os = $conn->query("SELECT odoo_url FROM odoo_settings ORDER BY id DESC LIMIT 1")->fetch_assoc(); $odooBaseUrl = rtrim($os['odoo_url'] ?? '', '/'); } catch (\Throwable $e) {}

// Đảm bảo odoo_invoices có cột payment_date (phòng khi Odoo hook chưa migrate trên server này)
$_pdchk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='odoo_invoices' AND COLUMN_NAME='payment_date'");
if ($_pdchk && $_pdchk->num_rows === 0) {
    try { $conn->query("ALTER TABLE odoo_invoices ADD COLUMN payment_date DATE DEFAULT NULL AFTER invoice_date_due"); } catch (\Throwable $e) {}
}

// ── Bảng con + cột milestone ────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd_milestone_invoices (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id           INT          DEFAULT NULL,
    milestone_id      INT          NOT NULL,
    os_milestone_id   VARCHAR(64)  DEFAULT NULL,
    invoice_odoo_id   INT          NOT NULL,
    invoice_code      VARCHAR(64)  DEFAULT NULL,
    invoice_status    VARCHAR(16)  DEFAULT NULL,
    payment_state     VARCHAR(16)  DEFAULT NULL,
    amount            DECIMAL(20,2) DEFAULT NULL,
    production_price  DECIMAL(20,2) DEFAULT NULL,
    currency          VARCHAR(10)  DEFAULT NULL,
    paid_at           DATE         DEFAULT NULL,
    note              VARCHAR(500) DEFAULT NULL,
    payment_pushed_at DATETIME     DEFAULT NULL,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ms_inv (milestone_id, invoice_odoo_id),
    INDEX idx_ms (milestone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

$endpoint    = $api_url . '/integrations/os/milestones/' . rawurlencode($os_milestone_id) . '/payment';
$occurred_at = gmdate('Y-m-d\TH:i:s') . '.000Z';

$results       = [];
$ok_count      = 0;
$last_pay_stat = null;

foreach ($inv_ids as $inv_id) {
    $iv = $conn->prepare("SELECT odoo_id, name, state, amount_total, currency_name, payment_state, invoice_date, payment_date
                          FROM odoo_invoices WHERE odoo_id = ? LIMIT 1");
    $iv->bind_param("i", $inv_id); $iv->execute();
    $inv = $iv->get_result()->fetch_assoc(); $iv->close();
    if (!$inv) { $results[] = ['inv_id' => $inv_id, 'ok' => false, 'msg' => 'Không tìm thấy hoá đơn']; continue; }

    $invoice_status = strtoupper((string)$inv['state']);                                  // DRAFT / POSTED
    $payment_state  = (strtolower((string)$inv['payment_state']) === 'paid') ? 'PAID' : 'UNPAID';
    $paid_at        = $inv['payment_date'] ?: ($inv['invoice_date'] ?: date('Y-m-d'));
    $amount         = (float)$inv['amount_total'];
    $prod_price     = $amount; // productionPrice = full tiền hoá đơn
    $currency       = $inv['currency_name'] ?: 'VND';
    $invoice_url    = $odooBaseUrl ? ($odooBaseUrl . '/web#id=' . (int)$inv['odoo_id'] . '&model=account.move&view_type=form') : null;

    $payload = [
        'occurredAt'  => $occurred_at,
        'projectCode' => $project_code,
        'invoice'     => [
            'invoiceCode'     => $inv['name'],
            'invoiceStatus'   => $invoice_status,
            'amount'          => $amount,
            'productionPrice' => $prod_price,
            'currency'        => $currency,
            'paidAt'          => $paid_at,
            'paymentState'    => $payment_state,
            'invoiceUrl'      => $invoice_url,
            'note'            => $note !== '' ? $note : null,
        ],
    ];

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
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

    $resp = json_decode($response, true);
    $log_payload = json_encode(['request' => $payload, 'response' => $response ? ($resp ?: $response) : null], JSON_UNESCAPED_UNICODE);

    if ($curl_err) {
        mpp_log($conn, $pakd_id, $os_milestone_id, 'error', $log_payload, $http_code ?: 0, 'curl: ' . $curl_err . ' | inv ' . $inv['name']);
        $results[] = ['inv_id' => $inv_id, 'code' => $inv['name'], 'ok' => false, 'msg' => 'Lỗi kết nối: ' . $curl_err];
        continue;
    }
    if ($http_code >= 200 && $http_code < 300 && (($resp['success'] ?? false) === true || $http_code === 200)) {
        $data           = $resp['data'] ?? [];
        $resp_pay_stat  = $data['paymentStatus'] ?? null;
        $total_paid     = isset($data['totalPaid']) ? (float)$data['totalPaid'] : $amount;
        $resp_currency  = $data['currency'] ?? $currency;
        if ($resp_pay_stat) $last_pay_stat = $resp_pay_stat;

        // Upsert vào bảng con
        $up = $conn->prepare("INSERT INTO pakd_milestone_invoices
            (pakd_id, milestone_id, os_milestone_id, invoice_odoo_id, invoice_code, invoice_status, payment_state, amount, production_price, currency, paid_at, note, payment_pushed_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE
              invoice_code=VALUES(invoice_code), invoice_status=VALUES(invoice_status), payment_state=VALUES(payment_state),
              amount=VALUES(amount), production_price=VALUES(production_price), currency=VALUES(currency),
              paid_at=VALUES(paid_at), note=VALUES(note), payment_pushed_at=NOW()");
        $inv_code = $inv['name']; $inv_oid = (int)$inv['odoo_id'];
        $up->bind_param("iisisssddss" . "s",
            $pakd_id, $milestone_id, $os_milestone_id, $inv_oid, $inv_code, $invoice_status, $payment_state,
            $amount, $prod_price, $resp_currency, $paid_at, $note);
        $up->execute(); $up->close();

        mpp_log($conn, $pakd_id, $os_milestone_id, 'ok', $log_payload, $http_code, 'invoice ' . $inv_code);
        $results[] = ['inv_id' => $inv_id, 'code' => $inv_code, 'ok' => true, 'paymentStatus' => $resp_pay_stat, 'totalPaid' => $total_paid, 'currency' => $resp_currency];
        $ok_count++;
    } else {
        $err_msg = $resp['message'] ?? $resp['error'] ?? ('HTTP ' . $http_code);
        mpp_log($conn, $pakd_id, $os_milestone_id, 'error', $log_payload, $http_code, $err_msg . ' | inv ' . $inv['name']);
        $results[] = ['inv_id' => $inv_id, 'code' => $inv['name'], 'ok' => false, 'msg' => $err_msg, 'http_code' => $http_code];
    }
}

// Cập nhật trạng thái thanh toán của milestone theo phản hồi cuối cùng (nếu có)
if ($last_pay_stat) {
    $um = $conn->prepare("UPDATE pakd_milestones SET payment_status=? WHERE id=?");
    $um->bind_param("si", $last_pay_stat, $milestone_id);
    $um->execute(); $um->close();
}

$total = count($results);
$msg   = $ok_count === $total
       ? "Đã đẩy $ok_count/$total hoá đơn sang hệ thống sản xuất"
       : "Đẩy thành công $ok_count/$total hoá đơn (có lỗi)";
echo json_encode(['ok' => $ok_count > 0, 'msg' => $msg, 'ok_count' => $ok_count, 'total' => $total, 'results' => $results]);
