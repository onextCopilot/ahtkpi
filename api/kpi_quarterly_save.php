<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
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

$target = trim($body['target_value'] ?? '');
$wq = isset($body['weight_q']) && $body['weight_q'] !== '' ? floatval($body['weight_q']) : 0;
$status = in_array($body['status'] ?? '', ['draft', 'active', 'completed', 'cancelled'])
    ? $body['status'] : 'draft';
$notes = trim($body['notes'] ?? '');

$chk = $conn->query("
    SELECT k.kpi_owner_id, d.owner_id as dept_owner_id, d.manager_id as dept_manager_id 
    FROM kpi_definitions k 
    LEFT JOIN departments d ON k.department_id = d.id 
    WHERE k.id = " . $def_id
);
if ($chk && $row = $chk->fetch_assoc()) {
    $can_edit = ($_SESSION['role'] === 'admin'
        || $_SESSION['user_id'] == $row['kpi_owner_id']
        || $_SESSION['user_id'] == $row['dept_owner_id']
        || $_SESSION['user_id'] == $row['dept_manager_id']);

    if (!$can_edit) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied: Bạn không có quyền cập nhật KPI này']);
        exit();
    }
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
