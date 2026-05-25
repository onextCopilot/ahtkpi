<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$pakd_id = (int)($body['pakdId'] ?? 0);

if (!$pakd_id) {
    echo json_encode(['ok' => false, 'msg' => 'pakdId không hợp lệ']); exit;
}

// Load ArrowHitech config
$configFile = __DIR__ . '/../config/arrowhitech_config.json';
if (!file_exists($configFile)) {
    echo json_encode(['ok' => false, 'msg' => 'Chưa cấu hình ArrowHitech API. Vào Settings → ArrowHitech API để thiết lập.']); exit;
}
$cfg = json_decode(file_get_contents($configFile), true);
$api_url   = rtrim($cfg['api_url']   ?? '', '/');
$api_token = $cfg['api_token'] ?? '';

if (!$api_url || !$api_token) {
    echo json_encode(['ok' => false, 'msg' => 'Thiếu URL hoặc Token trong cấu hình ArrowHitech API.']); exit;
}

// Fetch pakd record
$stmt = $conn->prepare("SELECT id, odoo_opp_id, department, division_names, opportunity_name, am_name, company_name, opp_value FROM pakd WHERE id = ?");
$stmt->bind_param("i", $pakd_id);
$stmt->execute();
$pakd = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pakd) {
    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy PAKD #' . $pakd_id]); exit;
}

// Build request headers
$timestamp  = (int)(microtime(true) * 1000);
$request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$payload = [
    'pakdId'         => $pakd['id'],
    'oppId'          => $pakd['odoo_opp_id'],
    'departmentName' => $pakd['division_names'],
    'saleTeam'       => $pakd['department'],
    'oppName'        => $pakd['opportunity_name'],
    'amName'         => $pakd['am_name'],
    'companyName'    => $pakd['company_name'],
    'oppValue'       => (float)$pakd['opp_value'],
];

$ch = curl_init($api_url . '/integrations/os/pakd-created');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'X-API-Key: '      . $api_token,
        'X-Timestamp: '    . $timestamp,
        'X-Request-Id: '   . $request_id,
        'Content-Type: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);

if ($curl_err) {
    echo json_encode(['ok' => false, 'msg' => 'Lỗi kết nối: ' . $curl_err]); exit;
}

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode(['ok' => true, 'msg' => 'Đã gửi yêu cầu Production Plan thành công!', 'data' => json_decode($response, true)]);
} else {
    $err_body = json_decode($response, true);
    $err_msg  = $err_body['message'] ?? $err_body['error'] ?? ('HTTP ' . $http_code);
    echo json_encode(['ok' => false, 'msg' => 'API trả về lỗi: ' . $err_msg, 'http_code' => $http_code]);
}
