<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $year = (int) ($data['year'] ?? 0);
    $quarter = (int) ($data['quarter'] ?? 0);
    $manual = isset($data['manual_kpi_pct']) && $data['manual_kpi_pct'] !== '' && $data['manual_kpi_pct'] !== null
        ? (float) $data['manual_kpi_pct'] : null;

    if (!$year || $quarter < 1 || $quarter > 4) {
        echo json_encode(['error' => 'Invalid year/quarter']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO user_quarter_kpi (user_id, year, quarter, manual_kpi_pct) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE manual_kpi_pct = VALUES(manual_kpi_pct)");
    if (!$stmt) {
        echo json_encode(['error' => $conn->error]);
        exit;
    }
    $stmt->bind_param("iiid", $user_id, $year, $quarter, $manual);
    $ok = $stmt->execute();
    echo json_encode(['ok' => $ok, 'error' => $stmt->error ?: null]);
    exit;
}

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT year, quarter, manual_kpi_pct FROM user_quarter_kpi WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($r = $res->fetch_assoc()) {
        $map["{$r['year']}-{$r['quarter']}"] = $r['manual_kpi_pct'];
    }
    echo json_encode($map);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
