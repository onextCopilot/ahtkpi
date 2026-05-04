<?php
/**
 * API: Get detailed folio breakdown (planned & actual)
 * GET /api/get_folio_detail.php?folio_id=2068&tab=actual
 */
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(30);

$folioId = intval($_GET['folio_id'] ?? 0);
$tab     = $_GET['tab'] ?? 'all'; 

if (!$folioId) {
    echo json_encode(['error' => 'Missing folio_id']);
    exit();
}

require_once __DIR__ . '/../libs/JiraAPI.php';
$jira = new JiraAPI();

try {
    // We use the JiraAPI to fetch data to ensure consistent auth and processing
    $result = $jira->getFolioDetailedBreakdown($folioId, $tab);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
