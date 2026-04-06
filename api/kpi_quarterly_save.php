<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// FIX: Always ensure special permissions are in session for this user
if (!isset($_SESSION['viewable_department_ids'])) {
    $su_stmt = $conn->prepare("SELECT viewable_department_ids, can_view_all_kpi FROM users WHERE id = ?");
    $su_stmt->bind_param("i", $_SESSION['user_id']);
    $su_stmt->execute();
    if ($su_row = $su_stmt->get_result()->fetch_assoc()) {
        $_SESSION['viewable_department_ids'] = $su_row['viewable_department_ids'] ?? '';
        $_SESSION['can_view_all_kpi'] = $su_row['can_view_all_kpi'] ?? 0;
    }
}
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$def_id = intval($body['kpi_def_id'] ?? 0);
$quarter = intval($body['quarter'] ?? 0);
$year = intval($body['year'] ?? 0);

if ($def_id <= 0 || $quarter < 1 || $quarter > 4 || $year < 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid params']);
    exit();
}
$uid = $_SESSION['user_id'];

// Strip formatting (dots used as thousand separators) before storing
function stripFormat($val)
{
    if ($val === null || $val === '')
        return '';
    $v = trim($val);
    if (preg_match('/^[\d.]+$/', str_replace(',', '.', $v))) {
        $dotCount = substr_count($v, '.');
        if ($dotCount > 1) {
            $v = str_replace('.', '', $v);
        }
    }
    return $v;
}

// 1. Get OLD row for auditing
$old_row = null;
$check_old = $conn->prepare("SELECT * FROM kpi_quarterly WHERE kpi_def_id=? AND year=? AND quarter=?");
$check_old->bind_param("iii", $def_id, $year, $quarter);
$check_old->execute();
$res_old = $check_old->get_result();
if ($r_old = $res_old->fetch_assoc()) {
    $old_row = $r_old;
}

$target = stripFormat($body['target_value'] ?? ($old_row ? $old_row['target_value'] : ''));
$wq = isset($body['weight_q']) && $body['weight_q'] !== '' ? floatval($body['weight_q']) : 
    ($old_row ? floatval($old_row['weight_q']) : 0);
$status = in_array($body['status'] ?? '', ['draft', 'active', 'completed', 'cancelled'])
    ? $body['status'] : ($old_row ? $old_row['status'] : 'draft');
$notes = isset($body['notes']) ? trim($body['notes']) : ($old_row ? $old_row['notes'] : '');

$chk = $conn->query("
    SELECT k.kpi_name, k.kpi_owner_id, k.department_id, d.owner_id as dept_owner_id, d.manager_id as dept_manager_id 
    FROM kpi_definitions k 
    LEFT JOIN departments d ON k.department_id = d.id 
    WHERE k.id = " . $def_id
);

if ($chk && $row = $chk->fetch_assoc()) {
    $v_depts = array_filter(explode(',', $_SESSION['viewable_department_ids'] ?? ''));
    $kpi_name = $row['kpi_name'] ?? '';
    $can_edit = ($_SESSION['role'] === 'admin'
        || ($_SESSION['can_view_all_kpi'] ?? 0) == 1
        || $_SESSION['user_id'] == $row['kpi_owner_id']
        || $_SESSION['user_id'] == $row['dept_owner_id']
        || $_SESSION['user_id'] == $row['dept_manager_id']
        || in_array($row['department_id'], $v_depts));

    if (!$can_edit) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied: Bạn không có quyền cập nhật KPI này']);
        exit();
    }
}

// 2. Audit Logging
function logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, $field, $old, $new, $uid) {
    if ((string)$old === (string)$new) return;
    $log_stmt = $conn->prepare("INSERT INTO kpi_audit_logs (kpi_def_id, kpi_name, year, month, quarter, field_name, old_value, new_value, updated_by) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?)");
    $log_stmt->bind_param("isiisssi", $def_id, $kpi_name, $year, $quarter, $field, $old, $new, $uid);
    $log_stmt->execute();
}

if ($old_row) {
    logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Target Quý', $old_row['target_value'] ?? '', $target, $uid);
    logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Trọng số Quý', $old_row['weight_q'] ?? '', $wq, $uid);
    logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Trạng thái Quý', $old_row['status'] ?? '', $status, $uid);
    logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Ghi chú Quý', $old_row['notes'] ?? '', $notes, $uid);
} else {
    if ($target !== '') logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Target Quý', '', $target, $uid);
    if ($wq > 0) logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Trọng số Quý', '', $wq, $uid);
    if ($status !== 'draft') logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Trạng thái Quý', '', $status, $uid);
    if ($notes !== '') logChangeQ($conn, $def_id, $kpi_name, $year, $quarter, 'Ghi chú Quý', '', $notes, $uid);
}


$stmt = $conn->prepare("
    INSERT INTO kpi_quarterly (kpi_def_id,quarter,year,target_value,weight_q,status,notes)
    VALUES (?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        target_value=VALUES(target_value),
        weight_q=VALUES(weight_q),
        status=VALUES(status),
        notes=VALUES(notes)
");
$stmt->bind_param("iiisdss", $def_id, $quarter, $year, $target, $wq, $status, $notes);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
}
