<?php
session_start();
header('Content-Type: application/json');

// Prevent HTML error output interfering with JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

function jsonErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno))
        return false;
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
}
set_error_handler("jsonErrorHandler");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../libs/JiraAPI.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

try {
    $jira = new JiraAPI();
    $projects = $jira->getProjects(); // Force refresh is false by default

    // Filter by search
    if (!empty($search)) {
        $search = strtolower($search);
        $projects = array_filter($projects, function ($p) use ($search) {
            return (
                stripos($p['name'], $search) !== false ||
                stripos($p['key'], $search) !== false
            );
        });
    }

    // Limit results
    $projects = array_slice($projects, 0, $limit);

    // Re-index array for JSON
    $projects = array_values($projects);

    echo json_encode([
        'success' => true,
        'data' => $projects,
        'count' => count($projects)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
