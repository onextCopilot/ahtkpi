<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
header('Content-Type: application/json');

$def_id = intval($_GET['def_id'] ?? 0);
$year = intval($_GET['year'] ?? date('Y'));
$qi = intval($_GET['quarter'] ?? 0);
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

if ($def_id <= 0 || $qi < 1 || $qi > 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid params']);
    exit();
}

$q_months = [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8, 9], 4 => [10, 11, 12]];
$months = implode(',', $q_months[$qi]);

// Fetch audit logs for this KPI (include months of the quarter + the quarter itself)
$sql = "SELECT l.*, u.full_name as updater_name 
        FROM kpi_audit_logs l 
        LEFT JOIN users u ON l.updated_by = u.id 
        WHERE l.kpi_def_id = ? AND l.year = ? 
          AND (l.month IN ($months) OR (l.month = 0 AND l.quarter = ?))
        ORDER BY l.updated_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $def_id, $year, $qi, $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();

$logs = [];
while ($row = $res->fetch_assoc()) {
    $row['updated_at_fmt'] = date('H:i d/m/Y', strtotime($row['updated_at']));
    $logs[] = $row;
}

$has_more = count($logs) >= $limit;

echo json_encode(['success' => true, 'logs' => $logs, 'has_more' => $has_more]);
?>
