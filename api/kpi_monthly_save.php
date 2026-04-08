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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);

$def_id = intval($body['kpi_def_id'] ?? 0);
$year = intval($body['year'] ?? 0);
$month = intval($body['month'] ?? 0);
$uid = $_SESSION['user_id'];

if ($def_id <= 0 || $year < 2000 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid params']);
    exit();
}

$chk = $conn->query("
    SELECT k.kpi_owner_id, k.department_id, d.owner_id as dept_owner_id, d.manager_id as dept_manager_id 
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


// Strip formatting (dots used as thousand separators) before storing
function stripFormat($val)
{
    if ($val === null || $val === '')
        return '';
    // Remove thousand-separator dots: "12.000.000" -> "12000000"
    // Keep decimal comma as dot: "12,5" -> "12.5"
    $v = trim($val);
    // If it looks like a formatted number (digits and dots only), strip dots
    if (preg_match('/^[\d.]+$/', str_replace(',', '.', $v))) {
        // Count dots: if more than one dot, they're thousand separators
        $dotCount = substr_count($v, '.');
        if ($dotCount > 1) {
            $v = str_replace('.', '', $v);
        }
    }
    return $v;
}

// 1. Get OLD row for auditing
$old_row = null;
$check_old = $conn->prepare("SELECT * FROM kpi_monthly WHERE kpi_def_id=? AND year=? AND month=?");
$check_old->bind_param("iii", $def_id, $year, $month);
$check_old->execute();
$res_old = $check_old->get_result();
if ($r_old = $res_old->fetch_assoc()) {
    $old_row = $r_old;
}

// 2. Prepare new values
$actual = stripFormat($body['actual_value'] ?? '');
$score = isset($body['score']) ? (($body['score'] !== null && $body['score'] !== '') ? floatval($body['score']) : null) : null;
$notes = isset($body['notes']) ? trim($body['notes']) : null;

// 3. Audit Logging
function logChange($conn, $def_id, $kpi_name, $year, $month, $field, $old, $new, $uid) {
    if ($old == $new) return;
    $log_stmt = $conn->prepare("INSERT INTO kpi_audit_logs (kpi_def_id, kpi_name, year, month, quarter, field_name, old_value, new_value, updated_by) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)");
    $log_stmt->bind_param("isiisssi", $def_id, $kpi_name, $year, $month, $field, $old, $new, $uid);
    $log_stmt->execute();
}

if ($old_row) {
    logChange($conn, $def_id, $kpi_name, $year, $month, 'Giá trị thực tế', $old_row['actual_value'] ?? '', $actual, $uid);
    if (isset($body['score'])) logChange($conn, $def_id, $kpi_name, $year, $month, 'Điểm số', $old_row['score'] ?? '', $score ?? '', $uid);
    if (isset($body['notes'])) logChange($conn, $def_id, $kpi_name, $year, $month, 'Ghi chú', $old_row['notes'] ?? '', $notes ?? '', $uid);
} else {
    // New Record
    if ($actual !== '') logChange($conn, $def_id, $kpi_name, $year, $month, 'Giá trị thực tế', '', $actual, $uid);
    if ($score !== null) logChange($conn, $def_id, $kpi_name, $year, $month, 'Điểm số', '', $score, $uid);
    if ($notes !== null) logChange($conn, $def_id, $kpi_name, $year, $month, 'Ghi chú', '', $notes, $uid);
}

$stmt = $conn->prepare("
    INSERT INTO kpi_monthly (kpi_def_id, year, month, actual_value, score, notes, updated_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        actual_value = VALUES(actual_value),
        score        = " . (isset($body['score']) ? "VALUES(score)" : "score") . ",
        notes        = " . (isset($body['notes']) ? "VALUES(notes)" : "notes") . ",
        updated_by   = VALUES(updated_by),
        updated_at   = CURRENT_TIMESTAMP
");
$stmt->bind_param("iiisdsi", $def_id, $year, $month, $actual, $score, $notes, $uid);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'actual_stored' => $actual, 'score' => $score]);
} else {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
}
