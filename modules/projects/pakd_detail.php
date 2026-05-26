<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$pakd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── AJAX: Request Production Plan ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
    header('Content-Type: application/json; charset=utf-8');
    $body    = json_decode(file_get_contents('php://input'), true);
    $pid     = (int)($body['pakdId'] ?? 0);

    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'pakdId không hợp lệ']); exit; }

    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API']); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token'] ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình']); exit; }

    $st = $conn->prepare("SELECT id, odoo_opp_id, department, division_names, opportunity_name, am_name, company_name, opp_value, project_type FROM pakd WHERE id=?");
    $st->bind_param("i", $pid);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$p) { echo json_encode(['ok'=>false,'msg'=>'Không tìm thấy PAKD #'.$pid]); exit; }

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $payload = [
        'pakdId'         => $p['id'],
        'oppId'          => $p['odoo_opp_id'],
        'departmentName' => $p['division_names'],
        'saleTeam'       => $p['department'],
        'oppName'        => $p['opportunity_name'],
        'amName'         => $p['am_name'],
        'companyName'    => $p['company_name'],
        'oppValue'       => (float)$p['opp_value'],
        'projectType'    => strtolower($p['project_type'] ?? 'external'),
    ];

    $ch = curl_init($api_url . '/integrations/os/pakd-created');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err]); exit; }
    if ($http_code >= 200 && $http_code < 300) {
        $res  = json_decode($response, true);
        $data = $res['data'] ?? [];
        $pasx_id     = $data['pasxId']    ?? null;
        $pasx_status = $data['status']    ?? null;
        $idempotent  = !empty($data['idempotent']);

        // Ensure columns exist (ignore duplicate column errors)
        foreach ([
            "ALTER TABLE pakd ADD COLUMN pasx_id VARCHAR(64) DEFAULT NULL",
            "ALTER TABLE pakd ADD COLUMN pasx_status VARCHAR(32) DEFAULT NULL",
            "ALTER TABLE pakd ADD COLUMN pasx_requested_at DATETIME DEFAULT NULL",
        ] as $sql) { try { $conn->query($sql); } catch (Exception $e) {} }

        // Save to DB
        $st2 = $conn->prepare("UPDATE pakd SET pasx_id=?, pasx_status=?, pasx_requested_at=NOW() WHERE id=?");
        $st2->bind_param("ssi", $pasx_id, $pasx_status, $pid);
        $st2->execute();
        $st2->close();

        $msg = $idempotent ? 'Yêu cầu đã tồn tại (idempotent).' : 'Đã gửi yêu cầu Production Plan thành công!';
        echo json_encode(['ok'=>true,'msg'=>$msg,'data'=>$res]);
    } else {
        $err = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        $err_msg = $err_val ?? ('HTTP ' . $http_code);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.$err_msg,'http_code'=>$http_code,'raw'=>$err]);
    }
    exit;
}

// ── AJAX: Save Division ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_division') {
    header('Content-Type: application/json; charset=utf-8');
    $pid      = (int)($_POST['id'] ?? 0);
    $division = trim($_POST['division_names'] ?? '');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']); exit; }
    $st = $conn->prepare("UPDATE pakd SET division_names=? WHERE id=?");
    $st->bind_param("si", $division, $pid);
    $ok = $st->execute();
    $st->close();
    echo json_encode(['ok' => $ok]);
    exit;
}

// ── AJAX: Approve PASX → tạo Project bên Profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_pasx') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID không hợp lệ']); exit; }

    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API']); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token']        ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình']); exit; }

    // Lấy thông tin pakd để gửi kèm
    $pr = $conn->prepare("SELECT opportunity_name, am_name, odoo_opp_id, pasx_id FROM pakd WHERE id=? LIMIT 1");
    $pr->bind_param("i", $pid);
    $pr->execute();
    $pk = $pr->get_result()->fetch_assoc();
    $pr->close();

    $body = [
        'message'  => 'Cảm ơn, PASX đã được approve, Sale/AM/BD sẽ chuyển báo giá cho khách hàng. Vui lòng chờ.. ',
        'oppName'  => $pk['opportunity_name'] ?? null,
        'amName'   => $pk['am_name']          ?? null,
        'oppId'    => $pk['odoo_opp_id']      ?? null,
        'pasxId'   => $pk['pasx_id']          ?? null,
    ];

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $ch = curl_init($api_url . '/integrations/os/pakd/' . $pid . '/approve');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err]); exit; }

    if ($http_code >= 200 && $http_code < 300) {
        // Cập nhật cả status và pasx_status = approved trong DB
        $st = $conn->prepare("UPDATE pakd SET status='approved', pasx_status='approved' WHERE id=?");
        $st->bind_param("i", $pid);
        $st->execute();
        $st->close();
        echo json_encode(['ok'=>true,'msg'=>'Approve thành công — Profile đang tạo Project']);
    } else {
        $err     = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.($err_val ?? 'HTTP '.$http_code)]);
    }
    exit;
}

// ── AJAX: Reject PASX ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_pasx') {
    header('Content-Type: application/json; charset=utf-8');
    $pid    = (int)($_POST['id']     ?? 0);
    $reason = trim($_POST['reason']  ?? '');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']); exit; }

    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API']); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token']        ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình']); exit; }

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $ch = curl_init($api_url . '/integrations/os/pakd/' . $pid . '/reject');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['reason' => $reason]),
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err]); exit; }

    if ($http_code >= 200 && $http_code < 300) {
        // Cập nhật pasx_status = rejected trong DB
        $st = $conn->prepare("UPDATE pakd SET pasx_status='rejected' WHERE id=?");
        $st->bind_param("i", $pid);
        $st->execute();
        $st->close();
        echo json_encode(['ok'=>true,'msg'=>'Đã gửi yêu cầu Reject / Rebuild PASX thành công']);
    } else {
        $err     = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.($err_val ?? 'HTTP '.$http_code)]);
    }
    exit;
}

// ── AJAX: PASX History ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_pasx_history') {
    header('Content-Type: application/json; charset=utf-8');
    $pid    = (int)($_POST['pakd_id'] ?? 0);
    $opp_id = trim($_POST['opp_id'] ?? '');

    // Đảm bảo cột opp_id tồn tại
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN opp_id VARCHAR(64) DEFAULT NULL AFTER pakd_id"); } catch (\Throwable $e) {}

    // Đảm bảo các cột mới tồn tại
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN submitted_by  VARCHAR(255) DEFAULT NULL AFTER note"); }       catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN submitted_at  DATETIME     DEFAULT NULL AFTER submitted_by"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN meta          JSON         DEFAULT NULL AFTER submitted_at"); } catch (\Throwable $e) {}

    $logs = [];
    if ($pid || $opp_id) {
        $stmt = $conn->prepare(
            "SELECT id, pakd_id, opp_id, pasx_id, event, status, payload,
                    http_status, note, submitted_by, submitted_at, received_at
             FROM pasx_webhook_logs
             WHERE pakd_id = ? OR opp_id = ?
             ORDER BY received_at DESC LIMIT 50"
        );
        $stmt->bind_param("is", $pid, $opp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // Parse payload để lấy humanCost, overtimeCost, pasxCost, _meta
            $pl = !empty($row['payload']) ? json_decode($row['payload'], true) : [];
            $row['humanCost']    = $pl['humanCost']    ?? null;
            $row['overtimeCost'] = $pl['overtimeCost'] ?? null;
            $row['pasxCost']     = (isset($pl['pasxCost']) && is_array($pl['pasxCost'])) ? $pl['pasxCost'] : null;
            // submittedBy: ưu tiên cột DB, fallback payload _meta
            if (empty($row['submitted_by']) && !empty($pl['_meta']['submittedBy']['fullName'])) {
                $row['submitted_by'] = $pl['_meta']['submittedBy']['fullName'];
            }
            if (empty($row['submitted_at']) && !empty($pl['_meta']['submittedAt'])) {
                $row['submitted_at'] = $pl['_meta']['submittedAt'];
            }
            unset($row['payload']); // không gửi toàn bộ payload
            $logs[] = $row;
        }
        $stmt->close();
    }
    echo json_encode(['ok' => true, 'logs' => $logs]);
    exit;
}

// ── AJAX: Save Project Type ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_project_type') {
    header('Content-Type: application/json; charset=utf-8');
    $type = strtolower(trim($_POST['project_type'] ?? ''));
    if (!in_array($type, ['internal', 'external'])) { echo json_encode(['ok'=>false,'msg'=>'Giá trị không hợp lệ']); exit; }
    $st = $conn->prepare("UPDATE pakd SET project_type=? WHERE id=?");
    $st->bind_param("si", $type, $pakd_id);
    $ok = $st->execute();
    $st->close();
    echo json_encode(['ok'=>$ok]);
    exit;
}

// ── AJAX Save Handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pakd') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']); exit; }

    // Ensure columns exist before UPDATE
    foreach ([
        "ALTER TABLE pakd ADD COLUMN fin_data    JSON          DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN contract_no VARCHAR(255)  DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN timeline    TEXT          DEFAULT NULL",
    ] as $sql) { try { $conn->query($sql); } catch (Exception $e) {} }

    // Merge fin_data: giữ lại các field từ callback (human_cost, overtime_cost) không bị ghi đè
    $fin_data_post = json_decode($_POST['fin_data'] ?? '{}', true) ?: [];
    $existing_row  = $conn->query("SELECT fin_data FROM pakd WHERE id=$pid")->fetch_assoc();
    $fin_data_db   = !empty($existing_row['fin_data']) ? (json_decode($existing_row['fin_data'], true) ?: []) : [];
    // Các field từ PASX callback - ưu tiên giữ giá trị DB nếu POST không có
    foreach (['human_cost', 'overtime_cost'] as $protected) {
        if (isset($fin_data_db[$protected]) && !isset($fin_data_post[$protected])) {
            $fin_data_post[$protected] = $fin_data_db[$protected];
        }
    }
    $fin_data     = json_encode($fin_data_post);
    $revenue      = (float)($_POST['revenue']      ?? 0);
    $gross_profit = (float)($_POST['gross_profit'] ?? 0);
    $contract_no  = trim($_POST['contract_no']  ?? '');
    $timeline     = trim($_POST['timeline']     ?? '');

    $stmt = $conn->prepare(
        "UPDATE pakd SET fin_data=?, revenue=?, gross_profit=?, contract_no=?, timeline=? WHERE id=?"
    );
    $stmt->bind_param("sddssi", $fin_data, $revenue, $gross_profit, $contract_no, $timeline, $pid);
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'msg' => 'Đã lưu thành công']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Lỗi DB: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// Update schema with missing fields for the UI
try {
    $conn->query("ALTER TABLE pakd ADD COLUMN revenue      DECIMAL(20,2) DEFAULT 0    AFTER opp_value");
    $conn->query("ALTER TABLE pakd ADD COLUMN gross_profit DECIMAL(20,2) DEFAULT 0    AFTER revenue");
    $conn->query("ALTER TABLE pakd ADD COLUMN pasx_value   DECIMAL(20,2) DEFAULT 0    AFTER gross_profit");
    $conn->query("ALTER TABLE pakd ADD COLUMN purchase_order_no VARCHAR(255) DEFAULT NULL AFTER sales_order_no");
    $conn->query("ALTER TABLE pakd ADD COLUMN contract_no  VARCHAR(255)  DEFAULT NULL");
    $conn->query("ALTER TABLE pakd ADD COLUMN timeline     TEXT          DEFAULT NULL");
    $conn->query("ALTER TABLE pakd ADD COLUMN fin_data     JSON          DEFAULT NULL");
} catch (Exception $e) {
    // Ignore duplicate column errors on subsequent loads
}

// Fetch data
$stmt = $conn->prepare("SELECT * FROM pakd WHERE id = ?");
$stmt->bind_param("i", $pakd_id);
$stmt->execute();
$pakd = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lấy danh sách divisions để làm dropdown
$division_options = [];
$dres = $conn->query("SELECT DISTINCT division_names FROM pakd WHERE division_names IS NOT NULL AND division_names != '' ORDER BY division_names");
if ($dres) while ($dr = $dres->fetch_row()) $division_options[] = $dr[0];

// Parse saved financial detail data
$fin_saved = !empty($pakd['fin_data']) ? (json_decode($pakd['fin_data'], true) ?? []) : [];

if (!$pakd) {
    // Provide default dummy data if not found so UI can be seen perfectly matching the screenshot
    $pakd = [
        'id' => 0,
        'name' => 'Tư vấn triển khai ERP - Huy Ngoc Phuong',
        'department' => 'BC ITQ',
        'am_name' => 'Nguyễn Thị Huyền Trâm',
        'project_type' => 'Trong công ty',
        'currency' => 'VND',
        'status' => 'approved',
        'opportunity_name' => 'RT ERP - CR điều chỉnh hoá đơn VAT',
        'company_name' => 'CÔNG TY CỔ PHẦN RT HOLDINGS',
        'opp_value' => 56000000,
        'opp_probability' => 100,
        'odoo_url' => '#',
        'contract_no' => '',
        'sales_order_no' => 'S00411',
        'purchase_order_no' => '',
        'timeline' => '',
        'revenue' => 280000000,
        'gross_profit' => 83300000,
        'pasx_value' => 157500000
    ];
}

// Calculate percentages
$pasx_percent = $pakd['revenue'] > 0 ? ($pakd['pasx_value'] / $pakd['revenue']) * 100 : ($pakd['id'] == 0 ? 56.25 : 0);

function formatVND($num) {
    return number_format($num, 0, ',', '.');
}

function pct($val, $base, $dec = 2) {
    if (!$base) return '—';
    return number_format($val / $base * 100, $dec);
}
// % of total cost: always ≥ 0, divides by totalCost not revNet
function pctCost($val, $total, $dec = 2) {
    if (!$total) return '—';
    return number_format(max(0, $val / $total * 100), $dec);
}

// Financial table calculations
$fin_rev_gross    = (float)($pakd['revenue'] ?? 0);
$fin_human_cost   = (float)($fin_saved['human_cost']    ?? 0); // cập nhật từ ArrowHitech callback
$fin_overtime     = (float)($fin_saved['overtime_cost'] ?? 0); // cập nhật từ ArrowHitech callback
// Dùng rev_net đã lưu từ JS (doanh thu thuần sau giảm trừ); fallback về revenue gross
$fin_rev_net      = !empty($fin_saved['rev_net']) ? (float)$fin_saved['rev_net'] : $fin_rev_gross;
// Nếu đã nhận được data từ callback thì dùng tổng human+overtime, không thì dùng pasx_value
$pasx_has_data    = ($fin_human_cost > 0 || $fin_overtime > 0);
$fin_prod_cost    = $pasx_has_data ? ($fin_human_cost + $fin_overtime) : (float)($pakd['pasx_value'] ?? 0);
$fin_sales_pct    = 2.0;
$fin_sales_comm   = (int)round(max(0, $fin_rev_gross) * $fin_sales_pct / 100); // base = gross revenue
$fin_presales_pct = 0.0;
$fin_mkt_pct      = 0.0;
$fin_sales_total  = $fin_sales_comm;
$fin_mgmt_pct     = 12.0;
$fin_mgmt         = (int)round(max(0, $fin_rev_net) * $fin_mgmt_pct / 100);
$fin_other_cost   = 0;
$fin_total_cost   = $fin_prod_cost + $fin_sales_total + $fin_mgmt + $fin_other_cost;
// Dùng gross_profit đã lưu từ DB (JS tính đủ tất cả các field nên chính xác hơn)
$fin_gross_profit_db = (float)($pakd['gross_profit'] ?? 0);
$fin_gross_profit    = $fin_gross_profit_db != 0 ? $fin_gross_profit_db : ($fin_rev_net - $fin_total_cost);
$fin_margin_pct      = $fin_rev_net > 0 ? ($fin_gross_profit / $fin_rev_net * 100) : 0;

$statusLabels = [
    'draft' => 'Nháp',
    'pending' => 'Chờ duyệt',
    'approved' => 'AM tiến hành gửi báo giá và xác nhận',
    'rejected' => 'Từ chối'
];
$statusLabel = $statusLabels[$pakd['status']] ?? 'Không xác định';

$statusColors = [
    'draft' => '#64748b',
    'pending' => '#d97706',
    'approved' => '#16a34a',
    'rejected' => '#dc2626'
];
$statusColor = $statusColors[$pakd['status']] ?? '#64748b';
$statusBgColor = [
    'draft' => '#f1f5f9',
    'pending' => '#fef3c7',
    'approved' => '#dcfce7',
    'rejected' => '#fee2e2'
];
$statusBg = $statusBgColor[$pakd['status']] ?? '#f1f5f9';
$statusBorderColor = [
    'draft' => 'rgba(100,116,139,0.2)',
    'pending' => 'rgba(217,119,6,0.3)',
    'approved' => 'rgba(22,163,74,0.3)',
    'rejected' => 'rgba(220,38,38,0.3)'
];
$statusBorder = $statusBorderColor[$pakd['status']] ?? 'rgba(100,116,139,0.2)';
$statusTextColor = [
    'draft' => '#1e293b',
    'pending' => '#92400e',
    'approved' => '#15803d',
    'rejected' => '#991b1b'
];
$statusText = $statusTextColor[$pakd['status']] ?? '#1e293b';
$statusIcon = [
    'draft' => 'fa-file',
    'pending' => 'fa-clock',
    'approved' => 'fa-check-square',
    'rejected' => 'fa-times-circle'
];
$iconClass = $statusIcon[$pakd['status']] ?? 'fa-circle';

// Override status bar khi PASX đang được xử lý
$pasx_done_statuses = ['completed', 'approved', 'cancelled']; // 'rejected' xử lý riêng bên dưới
$pasx_active = !empty($pakd['pasx_id'])
    && !in_array($pakd['pasx_status'] ?? '', $pasx_done_statuses)
    && ($pakd['status'] ?? '') !== 'approved';

if ($pasx_active) {
    if (($pakd['pasx_status'] ?? '') === 'rejected') {
        $statusLabel = 'Đã Reject PASX · Đang chờ Profile rebuild';
        $statusColor = '#b45309'; // amber-700
        $iconClass   = 'fa-clock';
    } else {
        $statusLabel = 'Đang làm PASX · ' . strtoupper($pakd['pasx_status'] ?? 'CREATED');
        $statusColor = '#7c3aed';
        $iconClass   = 'fa-cog fa-spin';
    }
}

function getProjectTypeIcon($type) {
    return strtolower(trim($type)) === 'external' ? 'fa-desktop' : 'fa-building';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết PAKD - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --bg: #f8fafc;
            --card: #ffffff;
            --slate: #1e293b;
            --gray: #64748b;
            --lgray: #94a3b8;
            --border: #e2e8f0;
            --r-md: 4px;
        }

        /* Reset gradient from dashboard.css */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            margin: 0; padding: 0;
            display: flex;
            color: var(--slate);
            font-size: 13px;
            line-height: 1.5;
        }

        /* Override dashboard.css main-content to match actual sidebar width */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 80px; }

        /* ── Top Metrics Bar ── */
        /* ── Combined status + metrics bar ── */
        .top-metrics-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 9px 32px;
            background: <?= $statusColor ?>;
            box-shadow: 0 2px 8px <?= $statusColor ?>44;
            font-size: 12.5px;
            color: #fff;
            flex-shrink: 0;
            transition: background .35s, box-shadow .35s;
        }
        .tmb-left  { display: flex; align-items: center; gap: 10px; }
        .tmb-right { display: flex; align-items: center; gap: 22px; }

        /* Status part (left) */
        .sb-icon {
            background: rgba(255,255,255,.2); color: #fff;
            width: 26px; height: 26px; border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .sb-text { display: flex; align-items: center; gap: 7px; font-weight: 600; font-size: 13px; color: #fff; }
        .sb-text .sb-sep { color: rgba(255,255,255,.65); font-weight: 400; }
        .sb-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 2px 8px; background: rgba(255,255,255,.2);
            border-radius: 4px; font-size: 12px; color: #fff; font-weight: 500;
        }

        /* Metrics part (right) */
        .metric-item { display: flex; align-items: center; gap: 0; }
        .metric-item .m-label { color: rgba(255,255,255,.75); margin-right: 5px; font-weight: 400; font-size: 12px; }
        .metric-item strong { font-size: 13px; font-weight: 700; color: #fff; }
        .metric-item .m-unit { font-size: 10px; color: rgba(255,255,255,.7); margin-left: 3px; font-weight: 400; }
        .metric-item .m-pct  { font-size: 11.5px; color: rgba(255,255,255,.8); margin-left: 4px; }
        .tmb-divider { width: 1px; height: 18px; background: rgba(255,255,255,.3); }

        /* ── Detail Container ── */
        .detail-container { padding: 28px 40px 40px; flex: 1; }


        /* ── Actions Row ── */
        .actions-row { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn-outline {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border: 1px solid var(--border); border-radius: var(--r-md);
            background: #fff; color: var(--slate); font-size: 12.5px; font-weight: 500;
            cursor: pointer; text-decoration: none; transition: background 0.15s, border-color 0.15s;
            line-height: 1.4;
        }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-outline.yellow {
            background: #fef9c3; border-color: #fde047; color: #a16207;
        }
        .btn-outline.yellow:hover { background: #fef08a; }

        /* ── Page Title ── */
        .page-title { font-size: 22px; font-weight: 700; color: var(--slate); margin: 0 0 20px 0; letter-spacing: -0.01em; }

        /* ── Metadata Box ── */
        .metadata-box {
            border: 1px solid var(--border); border-radius: var(--r-md);
            background: #ffffff;
            display: grid; grid-template-columns: repeat(6, 1fr);
            padding: 14px 20px; margin-bottom: 20px; gap: 16px;
        }
        .meta-item { display: flex; flex-direction: column; gap: 5px; }
        .meta-label {
            font-size: 10px; font-weight: 700; color: var(--gray);
            text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.2;
        }
        .meta-value {
            font-size: 12.5px; font-weight: 500; color: var(--slate);
            word-break: break-word; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
        }
        .meta-value a {
            color: var(--primary); text-decoration: none;
            display: inline-flex; align-items: center; gap: 3px; font-size: 12px;
        }
        .meta-value a:hover { text-decoration: underline; }

        /* ── Opportunity Row ── */
        .opp-row { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
        .opp-label {
            font-size: 10px; font-weight: 700; color: var(--gray);
            text-transform: uppercase; letter-spacing: 0.06em; width: 96px; flex-shrink: 0;
        }
        .opp-input-group {
            display: flex; align-items: stretch; border: 1px solid var(--border);
            border-radius: var(--r-md); background: #fff; width: 340px; overflow: hidden;
        }
        .opp-input-group input {
            border: none; padding: 8px 12px; font-size: 13px; flex: 1;
            color: var(--slate); outline: none; font-family: inherit; background: transparent;
        }
        .opp-input-group .clear-btn {
            background: none; border: none; border-left: 1px solid var(--border);
            padding: 0 12px; color: var(--lgray); cursor: pointer; display: flex; align-items: center;
        }
        .opp-info { display: flex; align-items: center; gap: 14px; font-size: 12px; color: var(--gray); }
        .opp-info-item { display: flex; align-items: center; gap: 5px; }
        .opp-info-item a { color: var(--primary); text-decoration: none; }
        .opp-info-item a:hover { text-decoration: underline; }

        /* ── Section Title ── */
        .section-title {
            font-size: 14px; font-weight: 700; color: var(--slate); margin: 0 0 12px 0;
            display: flex; align-items: center; gap: 7px;
        }

        /* ── Contract Box ── */
        .contract-box {
            background: #fefce8; border: 1px solid #fef08a; border-radius: var(--r-md); padding: 20px 22px;
        }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label {
            font-size: 10px; font-weight: 700; color: var(--gray);
            text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.2;
        }
        .form-control {
            padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--r-md);
            font-size: 13px; color: var(--slate); width: 100%; box-sizing: border-box;
            background: #fff; font-family: inherit; outline: none; line-height: 1.4;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        .form-select {
            appearance: none; padding-right: 32px;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px;
        }
        .field-link {
            font-size: 11px; color: var(--primary); text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .field-link:hover { text-decoration: underline; }
        .timeline-group { margin-top: 16px; }

        /* ── Financial Table ── */
        .pakd-table-section { margin-top: 28px; }
        .fin-table-wrap {
            border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden;
        }
        .fin-table {
            width: 100%; border-collapse: collapse; font-size: 12.5px;
            color: var(--slate); table-layout: fixed;
        }
        .fin-table colgroup .col-stt    { width: 64px; }
        .fin-table colgroup .col-item   { width: 320px; }
        .fin-table colgroup .col-desc   { }
        .fin-table colgroup .col-rate   { width: 96px; }
        .fin-table colgroup .col-amount { width: 170px; }
        .fin-table colgroup .col-ccy    { width: 52px; }
        .fin-table colgroup .col-action { width: 80px; }

        .fin-table thead th {
            background: #1e293b; color: #e2e8f0;
            padding: 9px 12px; text-align: left;
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            border: none; white-space: nowrap;
        }
        .fin-table thead th.r { text-align: right; }
        .fin-table thead th.c { text-align: center; }

        .fin-table td { padding: 7px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; overflow: hidden; }
        .fin-table tbody tr:last-child td { border-bottom: none; }

        /* Row type: main category */
        .fin-table tr.row-cat { background: #f1f5f9; }
        .fin-table tr.row-cat td { font-weight: 600; padding: 9px 12px; }
        /* Row 1: Doanh thu — xanh lá nhạt */
        .fin-table tr.row-rev { background: #dcfce7; }
        .fin-table tr.row-rev td { color: #166534; }
        /* Row 4: Tổng chi phí — cam nhạt */
        .fin-table tr.row-cost { background: #ffedd5; }
        .fin-table tr.row-cost td { color: #9a3412; }

        /* Row type: computed formula */
        .fin-table tr.row-formula { background: #fff; }
        .fin-table tr.row-formula td {
            font-weight: 700; padding: 10px 12px;
            border-top: 2px solid #e2e8f0; border-bottom: 2px solid #e2e8f0;
        }

        /* Row type: sub-item */
        .fin-table tr.row-sub { background: #fff; }

        /* Row type: locked (from production plan) */
        .fin-table tr.row-lock { background: #fafafa; }
        .fin-table tr.row-lock td { color: var(--gray); }

        /* Row type: detail (sub-sub) */
        .fin-table tr.row-detail { background: #fafafa; }

        /* Row type: section header (4.1, 4.2, 4.3, 4.4) */
        .fin-table tr.row-section { background: #eef4fb; }
        .fin-table tr.row-section td { font-weight: 500; color: #334155; }
        .fin-table tr.row-section .td-stt { font-weight: 600 !important; color: #475569; }

        /* Cell type helpers */
        .td-stt    { text-align: center; color: var(--lgray); font-size: 11px; font-weight: 400 !important; }
        .td-desc   { color: var(--gray); font-size: 12px; }
        .td-rate   { text-align: right; color: var(--gray); white-space: nowrap; }
        .td-amount { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .td-ccy    { text-align: center; color: var(--lgray); font-size: 11px; }
        .td-action { text-align: center; overflow: visible; }

        /* Indentation */
        .ind-1 { padding-left: 20px !important; }
        .ind-2 { padding-left: 36px !important; }

        /* Colored amounts */
        .amt-blue  { color: #3b82f6; }
        .amt-green { color: #16a34a; }

        /* Inline editable inputs */
        .fin-input {
            width: 100%; border: 1px solid transparent; border-radius: 4px;
            padding: 2px 6px; font-size: 12.5px; font-family: inherit;
            color: var(--slate); background: transparent; outline: none; box-sizing: border-box;
        }
        .fin-input:hover, .fin-input:focus { border-color: var(--border); background: #fff; }
        .fin-input::placeholder { color: var(--lgray); }
        .fin-input.r { text-align: right; }

        /* Percentage inline input */
        .pct-wrap  { display: inline-flex; align-items: center; gap: 3px; }
        .pct-inp   {
            width: 60px; text-align: right; border: 1px solid var(--border); border-radius: 4px;
            padding: 2px 6px; font-size: 12px; font-family: inherit;
            color: var(--slate); background: #fff; outline: none;
        }
        .pct-inp:focus { border-color: var(--primary); }
        .pct-sfx   { font-size: 11px; color: var(--gray); }
        .pct-res   { font-size: 12px; color: var(--gray); margin-left: 2px; }

        /* ── Inline save indicator ── */
        .field-saved {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            color: #16a34a; font-size: 11px; pointer-events: none;
            opacity: 0; transition: opacity 0.2s;
        }
        .field-saved.show { opacity: 1; }
        /* Cells need relative so indicator positions correctly */
        #fin-table td,
        .contract-box .form-group { position: relative; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php $page_title = ($pakd['opportunity_name'] ?? 'Chi tiết PAKD'); include __DIR__ . '/../includes/topbar.php'; ?>

        <!-- Combined status + metrics bar -->
        <div class="top-metrics-bar">
            <div class="tmb-left">
                <div class="sb-icon"><i class="fas <?= $iconClass ?>"></i></div>
                <div class="sb-text">
                    <span id="sb-label-text"><?= $statusLabel ?></span>
                    <span class="sb-sep">·</span>
                    <div class="sb-badge"><i class="fas <?= getProjectTypeIcon($pakd['project_type']) ?>"></i> <?= htmlspecialchars($pakd['project_type']) ?></div>
                </div>
            </div>
            <div class="tmb-right">
                <div class="metric-item">
                    <span class="m-label">Doanh thu thuần:</span>
                    <strong><?= formatVND($pakd['revenue']) ?></strong>
                    <span class="m-unit">VND</span>
                </div>
                <div class="tmb-divider"></div>
                <div class="metric-item">
                    <span class="m-label">Lợi nhuận gộp:</span>
                    <strong><?= formatVND($pakd['gross_profit']) ?></strong>
                    <span class="m-unit">VND</span>
                </div>
                <div class="tmb-divider"></div>
                <div class="metric-item">
                    <span class="m-label">PASX:</span>
                    <strong><?= formatVND($pakd['pasx_value']) ?></strong>
                    <span class="m-unit">VND</span>
                    <span class="m-pct">(<?= number_format($pasx_percent, 2) ?>%)</span>
                </div>
            </div>
        </div>

        <div class="detail-container">

            <!-- Actions Row -->
            <div class="actions-row">
                <a href="/projects/phuong-an-kinh-doanh" class="btn-outline">
                    <i class="fas fa-arrow-left" style="font-size:11px;"></i> Danh sách PAKD
                </a>
                <a href="#" class="btn-outline yellow">
                    <i class="fas fa-history" style="font-size:11px;"></i> Lịch sử (1 version)
                </a>
            </div>

            <!-- Title -->
            <h1 class="page-title"><?= htmlspecialchars($pakd['name']) ?></h1>

            <!-- Metadata Box -->
            <div class="metadata-box">
                <div class="meta-item">
                    <div class="meta-label">Department</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['department'] ?: '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">AM (SD/AM)</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['am_name'] ?: '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Lead/Opp Divisions</div>
                    <div class="meta-value">
                        <select id="sel-division" class="project-type-select" onchange="saveDivision(this.value)" style="max-width:200px;">
                            <option value="">— Chọn Division —</option>
                            <?php foreach ($division_options as $div): ?>
                                <option value="<?= htmlspecialchars($div) ?>" <?= $pakd['division_names'] === $div ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($div) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($pakd['division_names'] && !in_array($pakd['division_names'], $division_options)): ?>
                                <option value="<?= htmlspecialchars($pakd['division_names']) ?>" selected>
                                    <?= htmlspecialchars($pakd['division_names']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Loại dự án</div>
                    <div class="meta-value">
                        <select id="sel-project-type" class="project-type-select" onchange="saveProjectType(this.value)">
                            <option value="external" <?= strtolower($pakd['project_type']) === 'external' ? 'selected' : '' ?>>🖥 External</option>
                            <option value="internal" <?= strtolower($pakd['project_type']) === 'internal' ? 'selected' : '' ?>>🏢 Internal</option>
                        </select>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">PAKD Gốc</div>
                    <div class="meta-value" style="display:flex;align-items:center;gap:6px;">
                        <?= htmlspecialchars($pakd['name']) ?>
                        <a href="<?= htmlspecialchars($pakd['odoo_url'] ?: '#') ?>" target="_blank" title="Mở trong Odoo"><i class="fas fa-link" style="font-size:11px;"></i> Mở</a>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Currency</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['currency']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Status</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['status']) ?></div>
                </div>
            </div>

            <!-- Opportunity Row -->
            <div class="opp-row">
                <div class="opp-label">Opportunity</div>
                <div class="opp-input-group">
                    <input type="text" value="<?= htmlspecialchars($pakd['opportunity_name'] ?: '') ?>" placeholder="Chọn Opportunity...">
                    <button class="clear-btn"><i class="fas fa-times"></i></button>
                </div>
                <div class="opp-info">
                    <div class="opp-info-item"><i class="fas fa-building"></i> <?= htmlspecialchars($pakd['company_name'] ?: '—') ?></div>
                    <div class="opp-info-item"><i class="fas fa-sack-dollar" style="color:#d97706;"></i> <?= formatVND($pakd['opp_value']) ?></div>
                    <div class="opp-info-item"><i class="fas fa-chart-line" style="color:#dc2626;"></i> <?= number_format($pakd['opp_probability'], 0) ?>%</div>
                    <div class="opp-info-item">
                        <a href="<?= htmlspecialchars($pakd['odoo_url'] ?: '#') ?>" target="_blank"><i class="fas fa-link" style="font-size:11px;"></i> Mở trong Odoo</a>
                    </div>
                </div>
            </div>

            <!-- Contract Box -->
            <h3 class="section-title"><i class="fas fa-file-contract" style="color:var(--gray);"></i> Thông tin hợp đồng</h3>
            <div class="contract-box">
                <div class="form-grid">
                    <div class="form-group col-span-1">
                        <label class="form-label">Khách hàng</label>
                        <select class="form-control form-select">
                            <option><?= htmlspecialchars($pakd['company_name'] ?: 'Chọn khách hàng...') ?></option>
                        </select>
                        <a href="#" class="field-link"><i class="fas fa-link"></i> Mở khách hàng trong Odoo</a>
                    </div>
                    <div class="form-group col-span-1">
                        <label class="form-label">Số hợp đồng</label>
                        <input type="text" id="inp-contract-no" class="form-control" placeholder="vd: HD-2026-001" value="<?= htmlspecialchars($pakd['contract_no'] ?: '') ?>">
                    </div>
                    <div class="form-group col-span-1">
                        <label class="form-label">Sales Order No</label>
                        <select class="form-control form-select">
                            <option><?= htmlspecialchars($pakd['sales_order_no'] ?: 'Tìm SO từ Odoo...') ?></option>
                        </select>
                        <a href="#" class="field-link"><i class="fas fa-link"></i> Mở SO trong Odoo</a>
                    </div>
                    <div class="form-group col-span-1">
                        <label class="form-label">Purchase Order No</label>
                        <select class="form-control form-select">
                            <option><?= htmlspecialchars($pakd['purchase_order_no'] ?: 'Tìm PO từ Odoo...') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group timeline-group">
                    <label class="form-label">Thời gian triển khai</label>
                    <input type="text" id="inp-timeline" class="form-control" placeholder="vd: Q3-Q4/2026, 6 tháng từ 01/06/2026..." value="<?= htmlspecialchars($pakd['timeline'] ?: '') ?>">
                </div>
            </div>

            <!-- ── Financial Detail Table ── -->
            <div class="pakd-table-section">
                <h3 class="section-title" style="margin-bottom:12px;">
                    <i class="fas fa-table-cells-large" style="color:var(--gray);"></i>
                    Phương án Kinh doanh chi tiết
                </h3>
                <div class="fin-table-wrap">
                    <table class="fin-table" id="fin-table">
                        <colgroup>
                            <col class="col-stt">
                            <col class="col-item">
                            <col class="col-desc">
                            <col class="col-rate">
                            <col class="col-amount">
                            <col class="col-ccy">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="c">STT</th>
                                <th>Hạng mục</th>
                                <th>Diễn giải</th>
                                <th class="r">Tỷ lệ</th>
                                <th class="r">Số tiền</th>
                                <th class="c">CCY</th>
                                <th class="c">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 1. Doanh thu -->
                            <tr class="row-cat row-rev">
                                <td class="td-stt">1</td>
                                <td>Doanh thu</td>
                                <td></td>
                                <td class="td-rate">100.00%</td>
                                <td class="td-amount" id="r1-amt"><?= formatVND($fin_rev_gross) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">1.1</td>
                                <td class="ind-1"><input class="fin-input" value="Doanh thu dịch vụ - gói triển khai" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate" id="r11-rate">100.00%</td>
                                <td class="td-amount"><input class="fin-input r" id="r11-inp" value="<?= number_format($fin_rev_gross, 0, '', '') ?>" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">1.2</td>
                                <td class="ind-1"><input class="fin-input" value="Doanh thu khác" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate" id="r12-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r12-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 1.3 Change Requests -->
                            <tr class="row-sub row-cr-header">
                                <td class="td-stt">1.3</td>
                                <td class="ind-1">Change Requests</td>
                                <td><span style="font-size:11px;color:#64748b;">Các khoản thu thêm từ CR</span></td>
                                <td class="td-rate" id="r13-rate"></td>
                                <td class="td-amount" id="r13-amt" style="font-weight:600;color:#0f172a;">0</td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action">
                                    <button class="btn-add-cr" onclick="addCrRow()" title="Thêm Change Request">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                            <tbody id="cr-rows"></tbody>

                            <!-- 2. Khoản giảm trừ -->
                            <tr class="row-cat">
                                <td class="td-stt">2</td>
                                <td>Khoản giảm trừ doanh thu</td>
                                <td></td>
                                <td class="td-rate"></td>
                                <td class="td-amount" id="r2-amt">0</td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">2.1</td>
                                <td class="ind-1"><input class="fin-input" value="Chi hoa hồng cho đối tác" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" value="Chi phí feedback, hoa hồng cho CTV, đối tác" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r21-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">2.2</td>
                                <td class="ind-1"><input class="fin-input" value="Khoản giảm trừ khác" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r22-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 3. Doanh thu thuần (FORMULA) -->
                            <tr class="row-formula">
                                <td class="td-stt">3</td>
                                <td>Doanh thu thuần <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;" title="= Doanh thu - Khoản giảm trừ"></i></td>
                                <td class="td-desc">= Doanh thu - Khoản giảm trừ</td>
                                <td class="td-rate">100%</td>
                                <td class="td-amount amt-blue" id="r3-amt"><?= formatVND($fin_rev_net) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4. Tổng chi phí -->
                            <tr class="row-cat row-cost">
                                <td class="td-stt">4</td>
                                <td>Tổng chi phí <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;"></i></td>
                                <td></td>
                                <td class="td-rate" id="r4-rate"><?= pct($fin_total_cost, $fin_rev_net) ?>%</td>
                                <td class="td-amount" id="r4-amt"><?= formatVND($fin_total_cost) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.1 Chi phí sản xuất (LOCKED) -->
                            <tr class="row-lock row-section">
                                <td class="td-stt">4.1</td>
                                <td class="ind-1">
                                    Chi phí sản xuất
                                    <i class="fas fa-lock" style="color:var(--lgray);font-size:9px;margin-left:4px;" title="Khóa – từ Phương án sản xuất"></i>
                                    <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;margin-left:2px;"></i>
                                    <button class="btn-pasx-history" onclick="openPasxHistory()" title="Xem lịch sử cập nhật từ Profile">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </td>
                                <td class="td-desc">
                                    Lấy thông tin từ Phương án sản xuất (locked)
                                    <?php if (!empty($pakd['pasx_id'])): ?>
                                        <?php if ($pasx_has_data): ?>
                                            <span id="pasx-action-btns">
                                            <?php if ($fin_margin_pct >= 20): ?>
                                                <button class="btn-pasx-action btn-pasx-approve" onclick="pasxApprove()">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-pasx-action btn-pasx-ceo" onclick="pasxGetApproveCEO()">
                                                    <i class="fas fa-user-tie"></i> Get Approve (CEO)
                                                </button>
                                                <button class="btn-pasx-action btn-pasx-reject" onclick="pasxRejectRebuild()">
                                                    <i class="fas fa-redo"></i> Reject / Rebuild PASX
                                                </button>
                                            <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="pasx-processing-label">
                                                <i class="fas fa-clock"></i> Processing...
                                            </span>
                                            <button id="btn-resend-pasx" class="btn-resend-pasx" onclick="resendPasxRequest()" title="Gửi lại yêu cầu PASX">
                                                <i class="fas fa-redo"></i> Resend
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button id="btn-req-pasx"
                                                class="btn-req-pasx"
                                                onclick="requestProductionPlan()"
                                                title="Gửi yêu cầu tạo Phương án sản xuất">
                                            <i class="fas fa-paper-plane"></i>
                                            Request production plan
                                        </button>
                                        <span class="pasx-sent-badge" id="pasx-sent-badge" style="display:none"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-rate" id="r41-rate"><?= pctCost($fin_prod_cost, $fin_total_cost) ?>%</td>
                                <td class="td-amount"><?= formatVND($fin_prod_cost) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action">
                                    <button class="btn-pasx-history" onclick="openPasxHistory()" title="Review lịch sử từ ArrowHitech Profile">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="row-detail row-lock">
                                <td class="td-stt">4.1.1</td>
                                <td class="ind-2">Human Cost / Chi phí nhân công <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;"></i></td>
                                <td class="td-desc pasx-sub-desc">
                                    Từ Phương án sản xuất
                                    <?php if (!empty($pakd['pasx_id']) && !$pasx_has_data): ?>
                                        <span class="pasx-processing-label">
                                            <i class="fas fa-clock"></i> Processing...
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-rate" id="r411-rate"><?= $fin_human_cost > 0 ? pctCost($fin_human_cost, $fin_total_cost) . '%' : '' ?></td>
                                <td class="td-amount"><?= $fin_human_cost > 0 ? formatVND($fin_human_cost) : '' ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail row-lock">
                                <td class="td-stt">4.1.2</td>
                                <td class="ind-2">Chi phí làm việc ngoài giờ / Overtime cost <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;"></i></td>
                                <td class="td-desc pasx-sub-desc">
                                    Từ Phương án sản xuất
                                    <?php if (!empty($pakd['pasx_id']) && !$pasx_has_data): ?>
                                        <span class="pasx-processing-label">
                                            <i class="fas fa-clock"></i> Processing...
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-rate" id="r412-rate"><?= $fin_overtime > 0 ? pctCost($fin_overtime, $fin_total_cost) . '%' : '' ?></td>
                                <td class="td-amount"><?= $fin_overtime > 0 ? formatVND($fin_overtime) : '' ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.2 Chi phí bán hàng -->
                            <tr class="row-sub row-section">
                                <td class="td-stt">4.2</td>
                                <td class="ind-1">Chi phí bán hàng (kinh doanh)</td>
                                <td class="td-desc">Tuân thủ theo bảng phân bổ tùy thị trường</td>
                                <td class="td-rate" id="r42-rate"><?= number_format($fin_sales_pct, 2) ?>%</td>
                                <td class="td-amount" id="r42-amt"><?= formatVND($fin_sales_total) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.1</td>
                                <td class="ind-2"><input class="fin-input" value="Sales Commission"></td>
                                <td class="td-desc"><input class="fin-input" value="2% doanh thu"></td>
                                <td class="td-rate" id="r421-rate"></td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r421-pct" value="<?= $fin_sales_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r421-res">= <?= formatVND($fin_sales_comm) ?></span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.2</td>
                                <td class="ind-2"><input class="fin-input" value="Presales Commission"></td>
                                <td class="td-desc"><input class="fin-input" value="% doanh thu"></td>
                                <td class="td-rate" id="r422-rate"></td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r422-pct" value="<?= $fin_presales_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r422-res">= 0</span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.3</td>
                                <td class="ind-2"><input class="fin-input" value="MKT Commission"></td>
                                <td class="td-desc"><input class="fin-input" value="% doanh thu"></td>
                                <td class="td-rate" id="r423-rate"></td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r423-pct" value="<?= $fin_mkt_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r423-res">= 0</span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.4</td>
                                <td class="ind-2"><input class="fin-input" value="Chi phí bán hàng khác"></td>
                                <td class="td-desc"><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r424-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.3 Chi phí quản lý -->
                            <tr class="row-sub row-section">
                                <td class="td-stt">4.3</td>
                                <td class="ind-1">Chi phí quản lý + back office</td>
                                <td class="td-desc">12% doanh thu thuần — Tuân thủ bằng phân bổ</td>
                                <td class="td-rate" id="r43-rate"><?= number_format($fin_mgmt_pct, 2) ?>%</td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r43-pct" value="<?= $fin_mgmt_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r43-res">= <?= formatVND($fin_mgmt) ?></span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.4 Chi phí khác -->
                            <tr class="row-sub row-section">
                                <td class="td-stt">4.4</td>
                                <td class="ind-1">Chi phí khác</td>
                                <td></td>
                                <td class="td-rate" id="r44-rate"></td>
                                <td class="td-amount amt-blue" id="r44-amt">0</td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <?php
                            $other_items = ['4.4.1'=>'Công tác phí','4.4.2'=>'Chi phí đào tạo','4.4.3'=>'Chi phí teambuilding','4.4.4'=>'Chi phí tiếp khách','4.4.5'=>'Chi phí hội thảo, truyền thông'];
                            foreach ($other_items as $num => $label): ?>
                            <tr class="row-detail">
                                <td class="td-stt"><?= $num ?></td>
                                <td class="ind-2"><input class="fin-input" value="<?= $label ?>"></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount other-cost-cell"><input class="fin-input r" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- 5. Lợi nhuận gộp (FORMULA) -->
                            <tr class="row-formula">
                                <td class="td-stt">5</td>
                                <td>Lợi nhuận gộp (margin) <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;" title="= Doanh thu thuần - Tổng chi phí"></i></td>
                                <td class="td-desc">= Doanh thu thuần - Tổng chi phí</td>
                                <td class="td-rate" id="r5-rate"><?= pct($fin_gross_profit, $fin_rev_net) ?>%</td>
                                <td class="td-amount amt-green" id="r5-amt"><?= formatVND($fin_gross_profit) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }

        const FIN_PROD       = <?= (int)$fin_prod_cost ?>;
        const FIN_HUMAN      = <?= (int)$fin_human_cost ?>;
        const FIN_OVERTIME   = <?= (int)$fin_overtime ?>;
        const PAKD_ID        = <?= $pakd_id ?>;
        const PASX_HAS_DATA  = <?= $pasx_has_data ? 'true' : 'false' ?>;

        // ── Helpers ──
        function fin_fmt(n) {
            return Math.round(n).toLocaleString('vi-VN');
        }

        function fin_parse(val) {
            if (!val) return 0;
            return parseFloat(String(val).replace(/\./g, '').replace(',', '.')) || 0;
        }

        // Helper: % of total cost, always ≥ 0
        function costPct(val, total) {
            if (!total) return '—';
            return Math.max(0, val / total * 100).toFixed(2) + '%';
        }

        function fin_calc() {
            // ── Row 1: Doanh thu = sum(1.1 + 1.2 + 1.3 CR) ──
            const v11 = fin_parse(document.getElementById('r11-inp').value);
            const v12 = fin_parse(document.getElementById('r12-inp').value);
            const v13 = getCrTotal();
            const revGross = v11 + v12 + v13;
            document.getElementById('r1-amt').textContent = fin_fmt(revGross);
            document.getElementById('r11-rate').textContent = revGross > 0 ? (v11 / revGross * 100).toFixed(2) + '%' : '';
            document.getElementById('r13-amt').textContent = fin_fmt(v13);
            document.getElementById('r13-rate').textContent = v13 > 0 && revGross > 0 ? (v13 / revGross * 100).toFixed(2) + '%' : '';

            // ── Row 2: Khoản giảm trừ = sum(2.1 + 2.2) ──
            const v21 = fin_parse(document.getElementById('r21-inp').value);
            const v22 = fin_parse(document.getElementById('r22-inp').value);
            const deductions = v21 + v22;
            document.getElementById('r2-amt').textContent = fin_fmt(deductions);

            // ── Row 3: Doanh thu thuần ──
            const revNet = revGross - deductions;
            document.getElementById('r3-amt').textContent = fin_fmt(revNet);

            // ── Row 4.2: Sales commissions (base = revGross, always ≥ 0) ──
            const p421 = parseFloat(document.getElementById('r421-pct').value) || 0;
            const p422 = parseFloat(document.getElementById('r422-pct').value) || 0;
            const p423 = parseFloat(document.getElementById('r423-pct').value) || 0;
            const v421 = Math.max(0, revGross * p421 / 100);
            const v422 = Math.max(0, revGross * p422 / 100);
            const v423 = Math.max(0, revGross * p423 / 100);
            const v424 = fin_parse(document.getElementById('r424-inp').value);

            document.getElementById('r421-res').textContent = '= ' + fin_fmt(v421);
            document.getElementById('r422-res').textContent = '= ' + fin_fmt(v422);
            document.getElementById('r423-res').textContent = '= ' + fin_fmt(v423);

            const salesTotal = v421 + v422 + v423 + v424;
            document.getElementById('r42-amt').textContent = fin_fmt(salesTotal);

            // ── Row 4.3: Management (% of revNet) ──
            const p43 = parseFloat(document.getElementById('r43-pct').value) || 0;
            const v43 = Math.max(0, revNet * p43 / 100);
            document.getElementById('r43-res').textContent = '= ' + fin_fmt(v43);

            // ── Row 4.4: Other costs — collect individual values first ──
            let otherTotal = 0;
            const otherRows = []; // [{rateCell, val}] for rate update after totalCost
            document.querySelectorAll('.other-cost-cell input').forEach(function(inp) {
                const val = fin_parse(inp.value);
                otherTotal += val;
                const row = inp.closest('tr');
                const rateCell = row ? row.querySelector('.td-rate') : null;
                otherRows.push({ rateCell, val });
            });
            document.getElementById('r44-amt').textContent = fin_fmt(otherTotal);

            // ── Row 4: Total cost ──
            const totalCost = FIN_PROD + salesTotal + v43 + otherTotal;
            const totalPct = revNet > 0 ? (totalCost / revNet * 100).toFixed(2) : '0.00';
            document.getElementById('r4-rate').textContent = totalPct + '%';
            document.getElementById('r4-amt').textContent = fin_fmt(totalCost);

            // ── All cost sub-row rates: % of totalCost, always ≥ 0 ──
            const r41 = document.getElementById('r41-rate');
            if (r41) r41.textContent = costPct(FIN_PROD, totalCost);
            const r411 = document.getElementById('r411-rate');
            if (r411) r411.textContent = FIN_HUMAN > 0 ? costPct(FIN_HUMAN, totalCost) : '';
            const r412 = document.getElementById('r412-rate');
            if (r412) r412.textContent = FIN_OVERTIME > 0 ? costPct(FIN_OVERTIME, totalCost) : '';
            // 4.2 sub-items: % of totalCost
            document.getElementById('r421-rate').textContent = v421 > 0 ? costPct(v421, totalCost) : '';
            document.getElementById('r422-rate').textContent = v422 > 0 ? costPct(v422, totalCost) : '';
            document.getElementById('r423-rate').textContent = v423 > 0 ? costPct(v423, totalCost) : '';
            // 4.2 total, 4.3, 4.4 totals
            document.getElementById('r42-rate').textContent = costPct(salesTotal, totalCost);
            document.getElementById('r43-rate').textContent = costPct(v43, totalCost);
            const r44el = document.getElementById('r44-rate');
            if (r44el) r44el.textContent = otherTotal > 0 ? costPct(otherTotal, totalCost) : '';
            // 4.4 sub-item rates
            otherRows.forEach(function({ rateCell, val }) {
                if (rateCell) rateCell.textContent = val > 0 ? costPct(val, totalCost) : '';
            });

            // ── Row 5: Gross profit ──
            const grossProfit = revNet - totalCost;
            const grossPct = revNet > 0 ? (grossProfit / revNet * 100).toFixed(2) : '0.00';
            document.getElementById('r5-rate').textContent = grossPct + '%';
            document.getElementById('r5-amt').textContent = fin_fmt(grossProfit);

            // ── Cập nhật PASX action buttons theo margin realtime ──
            if (PASX_HAS_DATA) {
                const marginPct = revNet > 0 ? (grossProfit / revNet * 100) : 0;
                const container = document.getElementById('pasx-action-btns');
                if (container) {
                    if (marginPct >= 20) {
                        container.innerHTML = `
                            <button class="btn-pasx-action btn-pasx-approve" onclick="pasxApprove()">
                                <i class="fas fa-check"></i> Approve
                            </button>`;
                    } else {
                        container.innerHTML = `
                            <button class="btn-pasx-action btn-pasx-ceo" onclick="pasxGetApproveCEO()">
                                <i class="fas fa-user-tie"></i> Get Approve (CEO)
                            </button>
                            <button class="btn-pasx-action btn-pasx-reject" onclick="pasxRejectRebuild()">
                                <i class="fas fa-redo"></i> Reject / Rebuild PASX
                            </button>`;
                    }
                }
            }
        }

        // ── Inline save indicator ──
        function showFieldSaved(input) {
            const cell = input.closest('td') || input.closest('.form-group') || input.parentElement;
            if (!cell) return; // element removed from DOM (e.g. after row deletion)
            cell.querySelectorAll('.field-saved').forEach(e => e.remove());
            const badge = document.createElement('span');
            badge.className = 'field-saved';
            badge.innerHTML = '<i class="fas fa-check-circle"></i>';
            cell.appendChild(badge);
            requestAnimationFrame(() => badge.classList.add('show'));
            setTimeout(() => {
                badge.classList.remove('show');
                setTimeout(() => badge.remove(), 250);
            }, 2000);
        }

        // ── Autosave (triggered on blur of any editable field) ──
        function autosave(triggerInput) {
            fin_calc();
            const el = id => document.getElementById(id);
            const finData = {
                r11_amt:     fin_parse(el('r11-inp')?.value),
                r12_amt:     fin_parse(el('r12-inp')?.value),
                r21_amt:     fin_parse(el('r21-inp')?.value),
                r22_amt:     fin_parse(el('r22-inp')?.value),
                r421_pct:    parseFloat(el('r421-pct')?.value) || 0,
                r422_pct:    parseFloat(el('r422-pct')?.value) || 0,
                r423_pct:    parseFloat(el('r423-pct')?.value) || 0,
                r424_amt:    fin_parse(el('r424-inp')?.value),
                r43_pct:     parseFloat(el('r43-pct')?.value) || 0,
                other_costs: Array.from(document.querySelectorAll('.other-cost-cell input'))
                                  .map(i => fin_parse(i.value)),
                rev_net:         fin_parse(el('r3-amt')?.textContent), // doanh thu thuần (sau giảm trừ)
                change_requests: getCrData(),
            };
            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:       'save_pakd',
                    id:           PAKD_ID,
                    fin_data:     JSON.stringify(finData),
                    revenue:      fin_parse(el('r1-amt')?.textContent),
                    gross_profit: fin_parse(el('r5-amt')?.textContent),
                    contract_no:  (el('inp-contract-no')?.value || '').trim(),
                    timeline:     (el('inp-timeline')?.value    || '').trim(),
                })
            })
            .then(r => r.json())
            .then(data => { if (data.ok) showFieldSaved(triggerInput); })
            .catch(() => {});
        }

        // ── Load saved fin_data on page open ──
        (function fin_load() {
            const saved = <?= json_encode($fin_saved) ?>;
            if (!saved || !Object.keys(saved).length) return;
            const el = id => document.getElementById(id);
            const set = (id, val) => { const e = el(id); if (e && val !== undefined) e.value = val; };
            set('r11-inp',  saved.r11_amt  || '');
            set('r12-inp',  saved.r12_amt  || '');
            set('r21-inp',  saved.r21_amt  || '');
            set('r22-inp',  saved.r22_amt  || '');
            set('r421-pct', saved.r421_pct !== undefined ? saved.r421_pct : 2);
            set('r422-pct', saved.r422_pct !== undefined ? saved.r422_pct : 0);
            set('r423-pct', saved.r423_pct !== undefined ? saved.r423_pct : 0);
            set('r424-inp', saved.r424_amt || '');
            set('r43-pct',  saved.r43_pct  !== undefined ? saved.r43_pct : 12);
            if (Array.isArray(saved.other_costs)) {
                const cells = document.querySelectorAll('.other-cost-cell input');
                saved.other_costs.forEach((v, i) => { if (cells[i]) cells[i].value = v || ''; });
            }
            if (Array.isArray(saved.change_requests)) {
                saved.change_requests.forEach(function(cr) {
                    if (cr && (cr.name || cr.amount)) {
                        addCrRow(cr.name || '', cr.amount || 0);
                    }
                });
            }
        })();

        // ── Wire up autosave + init on DOM ready ──
        document.addEventListener('DOMContentLoaded', function () {
            // Attach blur → autosave to all financial table inputs
            document.querySelectorAll('#fin-table .fin-input, #fin-table .pct-inp').forEach(function (inp) {
                inp.addEventListener('blur', function () { autosave(this); });
                inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') this.blur(); });
            });
            // Contract box inputs
            ['inp-contract-no', 'inp-timeline'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('blur', function () { autosave(this); });
                    el.addEventListener('keydown', function (e) { if (e.key === 'Enter') this.blur(); });
                }
            });
            fin_calc();
        });

        // ── Save Project Type ──
        function saveProjectType(val) {
            fetch('/projects/pakd/edit?id=<?= (int)$pakd['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=save_project_type&project_type=' + encodeURIComponent(val)
            })
            .then(r => r.json())
            .then(d => { if (d.ok) showToast('Đã lưu loại dự án: ' + val, 'success'); });
        }

        // ── Request Production Plan ──
        function requestProductionPlan() {
            const btn = document.getElementById('btn-req-pasx');
            if (!btn || btn.disabled) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=<?= (int)$pakd['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pakdId: <?= (int)$pakd['id'] ?> })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showToast(data.msg, 'success');
                    const pasxId = data.data?.data?.pasxId ?? '';
                    const now = new Date();
                    const fmt = now.toLocaleDateString('vi-VN',{day:'2-digit',month:'2-digit',year:'numeric'})
                              + ' ' + now.toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'});
                    // Replace button with Processing label + badge
                    btn.outerHTML = `
                        <span class="pasx-processing-label">
                            <i class="fas fa-clock"></i> Processing...
                        </span>
                        <span class="pasx-sent-badge" style="display:inline-flex">
                            PASX: <code>${pasxId}</code>
                            <span class="pasx-sent-at">${fmt}</span>
                        </span>`;
                    // Add Processing label to 4.1.1 and 4.1.2
                    document.querySelectorAll('.pasx-sub-desc').forEach(el => {
                        el.insertAdjacentHTML('beforeend', `<span class="pasx-processing-label"><i class="fas fa-clock"></i> Processing...</span>`);
                    });
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Request production plan';
                }
            })
            .catch(err => {
                showToast('Lỗi kết nối: ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Request production plan';
            });
        }

        function showToast(msg, type) {
            const existing = document.getElementById('pasx-toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.id = 'pasx-toast';
            toast.style.cssText = [
                'position:fixed', 'bottom:24px', 'right:24px', 'z-index:9999',
                'padding:12px 20px', 'border-radius:8px', 'font-size:14px',
                'font-weight:500', 'box-shadow:0 4px 12px rgba(0,0,0,.15)',
                'display:flex', 'align-items:center', 'gap:10px',
                'max-width:420px', 'animation:toastIn .25s ease',
                type === 'success'
                    ? 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0'
                    : 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca'
            ].join(';');

            const icon = type === 'success'
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

            toast.innerHTML = icon + msg;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        function saveDivision(val) {
            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'save_division', id: PAKD_ID, division_names: val })
            })
            .then(r => r.json())
            .then(d => {
                const sel = document.getElementById('sel-division');
                if (d.ok) {
                    showToast('Đã lưu Division', 'success');
                    sel.style.borderColor = '#16a34a';
                    setTimeout(() => sel.style.borderColor = '', 2000);
                } else {
                    showToast('Lỗi lưu Division', 'error');
                }
            });
        }

        // ── Update status banner dynamically (không cần F5) ──
        function updateStatusBanner(label, color, iconCls) {
            const bar = document.querySelector('.top-metrics-bar');
            if (!bar) return;
            bar.style.background = color;
            bar.style.boxShadow  = '0 2px 8px ' + color + '44';
            const icon = bar.querySelector('.sb-icon i');
            if (icon) icon.className = 'fas ' + iconCls;
            const labelEl = document.getElementById('sb-label-text');
            if (labelEl) labelEl.textContent = label;
        }

        // ── Change Request helpers ──
        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function getCrTotal() {
            let total = 0;
            document.querySelectorAll('#cr-rows .cr-amt-inp').forEach(function(inp) {
                total += fin_parse(inp.value);
            });
            return total;
        }

        function getCrData() {
            const items = [];
            document.querySelectorAll('#cr-rows tr.cr-row').forEach(function(row) {
                const name = row.querySelector('.cr-name-inp')?.value || '';
                const amt  = fin_parse(row.querySelector('.cr-amt-inp')?.value || '0');
                items.push({ name: name, amount: amt });
            });
            return items;
        }

        function addCrRow(name, amount) {
            name   = name   !== undefined ? name   : '';
            amount = amount !== undefined ? amount : 0;
            const idx = Date.now() + '_' + Math.floor(Math.random() * 10000);
            const tr  = document.createElement('tr');
            tr.className = 'row-detail cr-row';
            tr.dataset.idx = idx;
            tr.innerHTML =
                '<td class="td-stt" style="color:#94a3b8;font-size:11px;">CR</td>' +
                '<td class="ind-2">' +
                    '<input class="cr-name-inp" value="' + escHtml(name) + '" placeholder="Tên Change Request..." ' +
                           'oninput="fin_calc()" onblur="autosave(this)">' +
                '</td>' +
                '<td><input class="fin-input" placeholder="Diễn giải..."></td>' +
                '<td class="td-rate"></td>' +
                '<td class="td-amount">' +
                    '<input class="cr-amt-inp" value="' + (amount || '') + '" placeholder="0" ' +
                           'oninput="fin_calc()" onblur="autosave(this)">' +
                '</td>' +
                '<td class="td-ccy">VND</td>' +
                '<td class="td-action">' +
                    '<button class="btn-del-cr" onclick="deleteCrRow(this)" title="Xóa CR này">' +
                        '<i class="fas fa-trash-alt"></i>' +
                    '</button>' +
                '</td>';
            document.getElementById('cr-rows').appendChild(tr);
            fin_calc();
            tr.querySelector('.cr-name-inp')?.focus();
        }

        function deleteCrRow(btn) {
            btn.closest('tr')?.remove();
            fin_calc();
            autosave(btn);
        }

        // ── Resend PASX Request ──
        function resendPasxRequest() {
            const btn = document.getElementById('btn-resend-pasx');
            if (!btn || btn.disabled) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=<?= (int)$pakd['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pakdId: <?= (int)$pakd['id'] ?> })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showToast(data.msg || 'Đã gửi lại yêu cầu PASX!', 'success');
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra khi gửi lại', 'error');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Resend';
            })
            .catch(function(err) {
                showToast('Lỗi kết nối: ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Resend';
            });
        }

        function pasxApprove() {
            // Dialog xác nhận
            const overlay = document.createElement('div');
            overlay.id = 'approve-dialog-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2000;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;width:400px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
                    <div style="padding:18px 20px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
                        <span style="width:32px;height:32px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-check" style="color:#16a34a;font-size:14px;"></i>
                        </span>
                        <div>
                            <div style="font-weight:700;font-size:15px;color:#0f172a;">Xác nhận Approve PASX</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">Profile sẽ tạo Project sau khi Approve</div>
                        </div>
                    </div>
                    <div style="padding:16px 20px;">
                        <p style="font-size:12.5px;color:#475569;margin:0 0 16px;">
                            Sau khi Approve, Profile sẽ tạo Project và trạng thái PAKD chuyển sang <strong>Approved</strong>.
                            Hành động này không thể hoàn tác.
                        </p>
                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button onclick="document.getElementById('approve-dialog-overlay').remove()"
                                style="padding:8px 16px;border:1px solid #e2e8f0;border-radius:7px;background:#fff;color:#475569;font-size:13px;cursor:pointer;font-weight:500;">
                                Hủy
                            </button>
                            <button id="btn-confirm-approve"
                                style="padding:8px 18px;border:none;border-radius:7px;background:#16a34a;color:#fff;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

            document.getElementById('btn-confirm-approve').addEventListener('click', function() {
                submitPasxApprove(this, overlay);
            });
        }

        function submitPasxApprove(btn, overlay) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'approve_pasx',
                    id:     PAKD_ID,
                })
            })
            .then(r => r.json())
            .then(data => {
                overlay.remove();
                if (data.ok) {
                    showToast(data.msg || 'Approve thành công!', 'success');
                    updateStatusBanner('AM tiến hành gửi báo giá và xác nhận', '#16a34a', 'fa-check-square');
                    // Ẩn nút approve, không cho bấm lại
                    const container = document.getElementById('pasx-action-btns');
                    if (container) container.innerHTML =
                        '<span style="font-size:12px;color:rgba(255,255,255,.8);"><i class="fas fa-check-circle"></i> Đã Approve</span>';
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Approve';
                }
            })
            .catch(() => {
                overlay.remove();
                showToast('Lỗi kết nối, vui lòng thử lại', 'error');
            });
        }

        function pasxGetApproveCEO() {
            showToast('Đang gửi yêu cầu phê duyệt lên CEO...', 'success');
            // TODO: gọi API get approve CEO
        }

        function pasxRejectRebuild() {
            // Hiện dialog nhập lý do
            const overlay = document.createElement('div');
            overlay.id = 'reject-dialog-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2000;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
                    <div style="padding:18px 20px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:8px;background:#fef2f2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-redo" style="color:#dc2626;font-size:14px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:#0f172a;font-size:.95rem;">Reject / Rebuild PASX</div>
                            <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Yêu cầu hệ thống Profile làm lại Phương án sản xuất</div>
                        </div>
                    </div>
                    <div style="padding:16px 20px;">
                        <label style="display:block;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                            Lý do từ chối <span style="color:#dc2626;">*</span>
                        </label>
                        <textarea id="reject-reason-inp" rows="3" placeholder="Nhập lý do cụ thể để bên Profile biết cần điều chỉnh gì..."
                            style="width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:9px 11px;font-size:.85rem;font-family:inherit;color:#0f172a;resize:vertical;outline:none;box-sizing:border-box;"></textarea>
                        <div id="reject-reason-err" style="display:none;color:#dc2626;font-size:.75rem;margin-top:4px;"><i class="fas fa-exclamation-circle"></i> Vui lòng nhập lý do</div>
                    </div>
                    <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:8px;">
                        <button onclick="document.getElementById('reject-dialog-overlay').remove()"
                            style="padding:7px 16px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;color:#64748b;font-size:.85rem;cursor:pointer;">
                            Huỷ
                        </button>
                        <button id="reject-confirm-btn" onclick="submitPasxReject()"
                            style="padding:7px 16px;border:none;border-radius:6px;background:#dc2626;color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-redo"></i> Xác nhận Reject
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
            setTimeout(() => document.getElementById('reject-reason-inp')?.focus(), 50);
        }

        function submitPasxReject() {
            const reason = (document.getElementById('reject-reason-inp')?.value || '').trim();
            const errEl  = document.getElementById('reject-reason-err');
            if (!reason) {
                errEl.style.display = 'block';
                document.getElementById('reject-reason-inp').style.borderColor = '#dc2626';
                return;
            }
            const btn = document.getElementById('reject-confirm-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'reject_pasx', id: PAKD_ID, reason })
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('reject-dialog-overlay')?.remove();
                if (data.ok) {
                    showToast(data.msg, 'success');
                    // Cập nhật action buttons
                    const container = document.getElementById('pasx-action-btns');
                    if (container) {
                        container.innerHTML = '<span style="font-size:12px;color:#b45309;font-weight:500;"><i class="fas fa-clock"></i> Đã Reject — đang chờ Profile rebuild</span>';
                    }
                    // Cập nhật status banner ngay lập tức
                    updateStatusBanner('Đã Reject PASX · Đang chờ Profile rebuild', '#b45309', 'fa-clock');
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo"></i> Xác nhận Reject';
                }
            })
            .catch(err => {
                showToast('Lỗi kết nối: ' + err.message, 'error');
                document.getElementById('reject-dialog-overlay')?.remove();
            });
        }
    </script>
    <style>
        @keyframes toastIn { from { transform: translateY(16px); opacity: 0; } to { transform: none; opacity: 1; } }

        .pasx-processing-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            padding: 2px 8px;
            background: #fefce8;
            border: 1px solid #fde047;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            color: #a16207;
        }
        .pasx-processing-label i { font-size: 10px; }

        .project-type-select {
            padding: 3px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13px;
            color: #334155;
            background: #fff;
            cursor: pointer;
            outline: none;
        }
        .project-type-select:focus { border-color: #3b82f6; }

        .pasx-sent-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            padding: 2px 8px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            font-size: 11px;
            color: #15803d;
            font-weight: 500;
        }
        .pasx-sent-badge i { color: #16a34a; }
        .pasx-sent-badge code {
            font-family: monospace;
            font-size: 11px;
            color: #166534;
            background: none;
        }
        .pasx-sent-badge .pasx-sent-at {
            color: #6b7280;
            font-weight: 400;
        }

        .btn-req-pasx {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 16px;
            padding: 4px 12px;
            background: #0ea5e9;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background .2s;
        }
        .btn-req-pasx:hover:not(:disabled) { background: #0284c7; }
        .btn-req-pasx:disabled { opacity: .55; cursor: not-allowed; }

        /* PASX action buttons (after data received) */
        .btn-pasx-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            padding: 4px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background .2s, opacity .2s;
        }
        .btn-pasx-approve {
            background: #16a34a;
            color: #fff;
        }
        .btn-pasx-approve:hover { background: #15803d; }

        .btn-pasx-ceo {
            background: #7c3aed;
            color: #fff;
        }
        .btn-pasx-ceo:hover { background: #6d28d9; }

        .btn-pasx-reject {
            background: #fff;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .btn-pasx-reject:hover { background: #fef2f2; }

        /* History icon button */
        .btn-pasx-history {
            background: none; border: none; cursor: pointer;
            color: #94a3b8; padding: 0 3px; font-size: 11px;
            vertical-align: middle; transition: color .2s;
        }
        .btn-pasx-history:hover { color: #6366f1; }

        /* History sidebar/drawer */
        .pasx-history-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.35); z-index: 1000;
            opacity: 0; transition: opacity .55s cubic-bezier(.25,.46,.45,.94);
        }
        .pasx-history-overlay.open { display: block; }
        .pasx-history-overlay.open-active { opacity: 1; }

        .pasx-history-modal {
            position: fixed; top: 0; right: 0;
            width: 50%; height: 100vh;
            background: #fff;
            box-shadow: -8px 0 40px rgba(15,23,42,.18);
            display: flex; flex-direction: column;
            transform: translateX(100%);
            transition: transform .55s cubic-bezier(.25,.46,.45,.94);
            border-radius: 0;
        }
        .pasx-history-overlay.open .pasx-history-modal {
            transform: translateX(0);
        }

        .phm-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 1.5rem; border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0; background: #fff;
            position: sticky; top: 0; z-index: 1;
        }
        .phm-header h3 { margin: 0; font-size: .95rem; font-weight: 600; color: #0f172a; }
        .phm-close {
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 6px;
            background: none; border: 1px solid #e2e8f0; cursor: pointer;
            color: #64748b; font-size: 1rem; line-height: 1; transition: background .15s;
        }
        .phm-close:hover { background: #f1f5f9; color: #0f172a; }
        .phm-body { padding: 1rem 1.5rem; overflow-y: auto; flex: 1; }

        /* PASX ID info strip in sidebar header */
        .phm-pasx-info {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            padding: 5px 10px; border-radius: 6px;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            font-size: .75rem;
        }
        .phm-pasx-label {
            font-size: .68rem; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .phm-pasx-code {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: .78rem; color: #4f46e5; font-weight: 600;
            background: #ede9fe; padding: 1px 6px; border-radius: 4px;
            letter-spacing: .02em;
        }
        .phm-pasx-at {
            display: flex; align-items: center; gap: 4px;
            color: #64748b; font-size: .73rem;
        }
        .phm-pasx-status {
            padding: 1px 7px; border-radius: 10px; font-size: .7rem; font-weight: 700;
            background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;
            letter-spacing: .04em;
        }
        .phm-loading { text-align: center; padding: 2rem; color: #94a3b8; font-size: .9rem; }
        .phm-empty  { text-align: center; padding: 2rem; color: #94a3b8; font-size: .875rem; }
        .phm-table  { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .phm-table th {
            background: #f8fafc; padding: 8px 10px; text-align: left;
            font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .phm-table td {
            padding: 8px 10px; border-bottom: 1px solid #f1f5f9;
            vertical-align: top; color: #334155;
        }
        .phm-table tr:hover td { background: #f8fafc; }
        .phm-badge {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: .75rem; font-weight: 500;
        }
        .phm-badge-ok     { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .phm-badge-err    { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .phm-badge-warn   { background: #fefce8; color: #a16207; border: 1px solid #fde047; }
        .phm-currency     { text-align: right; font-variant-numeric: tabular-nums; color: #1e40af; }

        /* Toggle button for PASX cost detail */
        .phm-toggle-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 12px; font-size: .75rem; font-weight: 500;
            background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
            cursor: pointer; white-space: nowrap; transition: background .15s;
        }
        .phm-toggle-btn:hover { background: #dbeafe; border-color: #93c5fd; }
        .phm-chevron { font-size: .65rem; transition: transform .2s; margin-left: 2px; }

        /* ── Change Request rows ── */
        .btn-add-cr {
            background: none; border: 1px solid #cbd5e1; border-radius: 4px;
            color: #64748b; cursor: pointer; padding: 3px 8px; font-size: 11px;
            transition: background .15s, border-color .15s, color .15s;
        }
        .btn-add-cr:hover { background: #6366f1; border-color: #6366f1; color: #fff; }

        .cr-name-inp {
            width: 100%; border: 1px solid transparent; border-radius: 4px;
            padding: 2px 6px; font-size: 12.5px; font-family: inherit;
            color: var(--slate); background: transparent; outline: none; box-sizing: border-box;
        }
        .cr-name-inp:hover, .cr-name-inp:focus { border-color: var(--border); background: #fff; }
        .cr-name-inp::placeholder { color: var(--lgray); }

        .cr-amt-inp {
            border: 1px solid transparent; border-radius: 4px;
            padding: 2px 6px; font-size: 12.5px; font-family: inherit;
            color: var(--slate); background: transparent; outline: none;
            text-align: right; box-sizing: border-box; width: 100%;
        }
        .cr-amt-inp:hover, .cr-amt-inp:focus { border-color: var(--border); background: #fff; }
        .cr-amt-inp::placeholder { color: var(--lgray); }

        .btn-del-cr {
            background: none; border: none; cursor: pointer;
            color: #94a3b8; padding: 2px 6px; font-size: 11px;
            transition: color .15s;
        }
        .btn-del-cr:hover { color: #dc2626; }

        /* Resend button */
        .btn-resend-pasx {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 8px;
            padding: 3px 10px;
            background: #fff;
            color: #6366f1;
            border: 1px solid #a5b4fc;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s, border-color .15s;
        }
        .btn-resend-pasx:hover:not(:disabled) { background: #eef2ff; border-color: #6366f1; }
        .btn-resend-pasx:disabled { opacity: .55; cursor: not-allowed; }
    </style>

<!-- PASX History Sidebar -->
<div class="pasx-history-overlay" id="pasxHistoryOverlay" onclick="closePasxHistory(event)">
    <div class="pasx-history-modal">
        <div class="phm-header">
            <div style="flex:1;min-width:0;">
                <h3 style="margin:0 0 6px 0;">
                    <i class="fas fa-history" style="color:#6366f1;margin-right:8px;"></i>Lịch sử cập nhật từ ArrowHitech Profile
                </h3>
                <?php if (!empty($pakd['pasx_id'])): ?>
                <div class="phm-pasx-info">
                    <span class="phm-pasx-label">PASX ID</span>
                    <code class="phm-pasx-code"><?= htmlspecialchars($pakd['pasx_id']) ?></code>
                    <?php if ($pakd['pasx_requested_at']): ?>
                        <span class="phm-pasx-at"><i class="fas fa-clock" style="font-size:10px;"></i> <?= date('d/m/Y H:i', strtotime($pakd['pasx_requested_at'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pakd['pasx_status'])): ?>
                        <span class="phm-pasx-status"><?= htmlspecialchars(strtoupper($pakd['pasx_status'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <button class="phm-close" onclick="closePasxHistory()" style="flex-shrink:0;margin-left:12px;">&times;</button>
        </div>
        <div class="phm-body" id="pasxHistoryBody">
            <div class="phm-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
        </div>
    </div>
</div>

<script>
function openPasxHistory() {
    const overlay = document.getElementById('pasxHistoryOverlay');
    overlay.classList.add('open');
    requestAnimationFrame(() => requestAnimationFrame(() => overlay.classList.add('open-active')));
    loadPasxHistory();
}

function closePasxHistory(e) {
    const overlay = document.getElementById('pasxHistoryOverlay');
    const modal   = overlay?.querySelector('.pasx-history-modal');
    if (!e || (e.target === overlay && modal && !modal.contains(e.target))) {
        overlay.classList.remove('open-active');
        setTimeout(() => overlay.classList.remove('open'), 550);
    }
}

function loadPasxHistory() {
    const body = document.getElementById('pasxHistoryBody');
    body.innerHTML = '<div class="phm-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

    fetch('/projects/pakd/edit?id=' + PAKD_ID, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action:   'get_pasx_history',
            pakd_id:  PAKD_ID,
            opp_id:   '<?= htmlspecialchars($pakd['odoo_opp_id'] ?? '') ?>',
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok || !data.logs || data.logs.length === 0) {
            body.innerHTML = '<div class="phm-empty"><i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>Chưa có lịch sử cập nhật</div>';
            return;
        }

        const fmtNum = n => n != null ? Number(n).toLocaleString('vi-VN') : '—';
        const fmtDate = s => {
            if (!s) return '—';
            const d = new Date(s.replace(' ', 'T'));
            return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        };
        const statusBadge = (s, http) => {
            if (s === 'auth_failed' || (http && http >= 400)) return `<span class="phm-badge phm-badge-err">${s || 'error'}</span>`;
            if (!s) return '<span class="phm-badge phm-badge-warn">—</span>';
            return `<span class="phm-badge phm-badge-ok">${s}</span>`;
        };

        const buildPathHtml = (path, name) => {
            const raw    = path || name || '—';
            const parts  = raw.split('>').map(s => s.trim()).filter(Boolean);
            if (parts.length <= 1) {
                return `<div style="font-weight:600;color:#0f172a;">${parts[0] || '—'}</div>`;
            }
            return parts.map((part, i) => {
                const isLast   = i === parts.length - 1;
                const indent   = i * 14;
                const arrow    = i > 0 ? '<span style="color:#cbd5e1;margin-right:5px;font-size:.7rem;">↳</span>' : '';
                const style    = isLast
                    ? `font-weight:600;color:#0f172a;`
                    : `color:#94a3b8;font-size:.75rem;`;
                return `<div style="padding-left:${indent}px;${style}line-height:1.5;">${arrow}${part}</div>`;
            }).join('');
        };

        const buildCostRows = (pasxCost) => {
            const active = (pasxCost || []).filter(c => (c.total || 0) > 0);
            if (!active.length) return '';
            const grandTotal = active.reduce((s, c) => s + (c.total || 0), 0);
            const rows = active.map(c => `
                <tr style="background:#fff;">
                    <td style="padding:8px 12px;">${buildPathHtml(c.path, c.name)}</td>
                    <td style="padding:8px 12px;text-align:right;color:#475569;white-space:nowrap;">${fmtNum(c.unit)}${c.unitType ? ' <span style="font-size:.7rem;color:#94a3b8;">'+c.unitType+'</span>' : ''}</td>
                    <td style="padding:8px 12px;text-align:right;color:#475569;white-space:nowrap;">${fmtNum(c.amount)}</td>
                    <td style="padding:8px 12px;text-align:right;font-weight:600;color:#1e40af;white-space:nowrap;">${fmtNum(c.total)} ₫</td>
                </tr>`).join('');
            const thStyle = 'padding:7px 12px;font-weight:600;color:#475569;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;';
            return `
                <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                    <thead><tr style="background:#f1f5f9;">
                        <th style="${thStyle}text-align:left;">Hạng mục / Path</th>
                        <th style="${thStyle}text-align:right;">Đơn giá</th>
                        <th style="${thStyle}text-align:right;">Số lượng</th>
                        <th style="${thStyle}text-align:right;">Thành tiền</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot><tr style="background:#f8fafc;border-top:2px solid #e2e8f0;">
                        <td colspan="3" style="padding:8px 12px;text-align:right;font-weight:700;color:#0f172a;">Tổng cộng</td>
                        <td style="padding:8px 12px;text-align:right;font-weight:700;color:#1d4ed8;white-space:nowrap;">${fmtNum(grandTotal)} ₫</td>
                    </tr></tfoot>
                </table>`;
        };

        let rowIdx = 0;
        let html = `
        <table class="phm-table">
            <thead>
                <tr>
                    <th>Thời gian nhận</th>
                    <th>Người gửi</th>
                    <th>Event / Status</th>
                    <th style="text-align:right">Human Cost</th>
                    <th style="text-align:right">Overtime Cost</th>
                    <th style="text-align:center">Chi tiết</th>
                </tr>
            </thead>
            <tbody>`;

        data.logs.forEach(log => {
            const activeCost = (log.pasxCost || []).filter(c => (c.total || 0) > 0);
            const hasCost = activeCost.length > 0;
            const rid = 'phm-cost-' + (rowIdx++);

            const submittedBy = log.submitted_by || '—';
            const submittedAt = log.submitted_at
                ? `<div style="font-size:.71rem;color:#94a3b8;margin-top:2px;">${fmtDate(log.submitted_at)}</div>`
                : '';

            const costBtn = hasCost
                ? `<button class="phm-toggle-btn" onclick="phmToggle('${rid}')">
                       <i class="fas fa-layer-group"></i> ${activeCost.length} hạng mục
                       <i class="fas fa-chevron-down phm-chevron" id="${rid}-icon"></i>
                   </button>`
                : `<span style="color:#cbd5e1;font-size:.78rem;">—</span>`;

            html += `
            <tr>
                <td style="white-space:nowrap;color:#64748b;vertical-align:top;">${fmtDate(log.received_at)}</td>
                <td style="vertical-align:top;">
                    <div style="font-weight:500;color:#334155;">${submittedBy}</div>${submittedAt}
                </td>
                <td style="vertical-align:top;">
                    <code style="font-size:.78rem;color:#6366f1;">${log.event || '—'}</code>
                    <div style="margin-top:4px;">${statusBadge(log.status, log.http_status)}</div>
                </td>
                <td class="phm-currency" style="vertical-align:top;">${log.humanCost != null ? fmtNum(log.humanCost) + ' ₫' : '—'}</td>
                <td class="phm-currency" style="vertical-align:top;">${log.overtimeCost != null ? fmtNum(log.overtimeCost) + ' ₫' : '—'}</td>
                <td style="text-align:center;vertical-align:top;">${costBtn}</td>
            </tr>
            ${hasCost ? `<tr id="${rid}" style="display:none;">
                <td colspan="6" style="padding:0;background:#f8fafc;border-bottom:2px solid #6366f1;">
                    ${buildCostRows(log.pasxCost)}
                </td>
            </tr>` : ''}`;
        });

        html += '</tbody></table>';
        body.innerHTML = html;
    })
    .catch(() => {
        body.innerHTML = '<div class="phm-empty" style="color:#dc2626;">Lỗi khi tải lịch sử</div>';
    });
}

function phmToggle(rid) {
    const row  = document.getElementById(rid);
    const icon = document.getElementById(rid + '-icon');
    if (!row) return;
    const open = row.style.display !== 'none';
    row.style.display  = open ? 'none' : 'table-row';
    if (icon) icon.style.transform = open ? '' : 'rotate(180deg)';
}

// Đóng bằng phím Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePasxHistory({target: document.getElementById('pasxHistoryOverlay')});
});
</script>
</body>
</html>
