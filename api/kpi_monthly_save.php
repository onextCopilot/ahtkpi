<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
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

$actual = stripFormat($body['actual_value'] ?? '');
$score = ($body['score'] !== null && $body['score'] !== '') ? floatval($body['score']) : null;
$notes = trim($body['notes'] ?? '');

$stmt = $conn->prepare("
    INSERT INTO kpi_monthly (kpi_def_id, year, month, actual_value, score, notes, updated_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        actual_value = VALUES(actual_value),
        score        = VALUES(score),
        notes        = VALUES(notes),
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
