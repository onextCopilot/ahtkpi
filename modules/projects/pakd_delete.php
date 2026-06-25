<?php
/**
 * pakd_delete.php
 *
 * Xoá một phương án kinh doanh (PAKD).
 * Called via POST /projects/pakd/delete  (body: id=<int>)
 *
 * Quyền: admin xoá được mọi PAKD; AM/BD chỉ xoá được PAKD của chính mình
 * (am_user_id = user hiện tại HOẶC am_name = họ tên hiện tại).
 */

header('Content-Type: application/json');

$old_error_level = error_reporting(0);
require_once __DIR__ . '/../../config/config.php';
error_reporting($old_error_level);

// ── Auth check ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
$role = $_SESSION['role'] ?? 'user';
if (empty($_SESSION['is_am_bd']) && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Thiếu hoặc sai ID phương án.']);
    exit();
}

// ── Kiểm tra tồn tại + quyền sở hữu ───────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, am_user_id, am_name FROM pakd WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
    exit();
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy phương án.']);
    exit();
}

if ($role !== 'admin') {
    $my_id   = (int)$_SESSION['user_id'];
    $my_name = $_SESSION['full_name'] ?? '';
    $owns = ((int)($row['am_user_id'] ?? 0) === $my_id)
        || ($my_name !== '' && ($row['am_name'] ?? '') === $my_name);
    if (!$owns) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Bạn không có quyền xoá phương án này.']);
        exit();
    }
}

// ── Xoá ───────────────────────────────────────────────────────────────────────
$del = $conn->prepare("DELETE FROM pakd WHERE id = ?");
if (!$del) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
    exit();
}
$del->bind_param('i', $id);
if ($del->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Không thể xoá: ' . $del->error]);
}
$del->close();
