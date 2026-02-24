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

try {
    $jira = new JiraAPI();
    $projects = $jira->refreshProjectCache();
    $count = count($projects);

    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "Đã cache thành công $count projects từ Jira."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
