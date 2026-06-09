<?php
/**
 * Đẩy thông tin thanh toán (1 hoặc NHIỀU hoá đơn) của một milestone sang hệ thống sản xuất (OS).
 * POST /api/milestone_push_payment   (session auth: admin hoặc AM của dự án)
 *
 * Body: pakd_id, milestone_id, inv_ids[] (hoặc inv_id), note
 * Mỗi hoá đơn = 1 lần gọi POST {api_url}/integrations/os/milestones/{osMilestoneId}/payment
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/milestone_os_push.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) { echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập']); exit; }
$user_id  = (int)$_SESSION['user_id'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

$in = $_POST;
if (empty($in)) { $raw = json_decode(file_get_contents('php://input'), true); if (is_array($raw)) $in = $raw; }

$pakd_id      = (int)($in['pakd_id'] ?? 0);
$milestone_id = (int)($in['milestone_id'] ?? 0);
$note         = trim((string)($in['note'] ?? ''));

$inv_ids = [];
if (isset($in['inv_ids'])) {
    $raw = $in['inv_ids'];
    $inv_ids = is_array($raw) ? $raw : explode(',', (string)$raw);
} elseif (isset($in['inv_id'])) {
    $inv_ids = [$in['inv_id']];
}
$inv_ids = array_values(array_unique(array_filter(array_map('intval', $inv_ids), fn($x) => $x > 0)));

if (!$pakd_id || !$milestone_id || empty($inv_ids)) {
    echo json_encode(['ok' => false, 'msg' => 'Thiếu tham số (pakd_id / milestone_id / inv_ids)']); exit;
}

// Quyền: admin hoặc AM của dự án
$pr = $conn->prepare("SELECT am_user_id FROM pakd WHERE id = ? LIMIT 1");
$pr->bind_param("i", $pakd_id); $pr->execute();
$pakd = $pr->get_result()->fetch_assoc(); $pr->close();
if (!$pakd) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy dự án']); exit; }
if (!$is_admin && (int)($pakd['am_user_id'] ?? 0) !== $user_id) { echo json_encode(['ok' => false, 'msg' => 'Không có quyền']); exit; }

// Milestone phải thuộc dự án
$ms_stmt = $conn->prepare("SELECT id FROM pakd_milestones WHERE id = ? AND pakd_id = ? LIMIT 1");
$ms_stmt->bind_param("ii", $milestone_id, $pakd_id); $ms_stmt->execute();
$ms = $ms_stmt->get_result()->fetch_assoc(); $ms_stmt->close();
if (!$ms) { echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy milestone']); exit; }

// Đẩy từng hoá đơn
$results = []; $ok_count = 0;
foreach ($inv_ids as $inv_id) {
    $r = ms_os_push_invoice($conn, $milestone_id, $inv_id, $note);
    $r['inv_id'] = $inv_id;
    $results[] = $r;
    if (!empty($r['ok'])) $ok_count++;
}

$total = count($results);
$msg   = $ok_count === $total
       ? "Đã đẩy $ok_count/$total hoá đơn sang hệ thống sản xuất"
       : "Đẩy thành công $ok_count/$total hoá đơn (có lỗi)";
echo json_encode(['ok' => $ok_count > 0, 'msg' => $msg, 'ok_count' => $ok_count, 'total' => $total, 'results' => $results]);
