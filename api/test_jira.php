<?php
// Prevent HTML error output interfering with JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Log errors to a file instead
function jsonErrorHandler($errno, $errstr, $errfile, $errline)
{
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
}
set_error_handler("jsonErrorHandler");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$jira_url = rtrim($_POST['jira_url'] ?? '', '/');
$email = $_POST['jira_email'] ?? '';
$token = $_POST['jira_token'] ?? '';

if (empty($jira_url) || empty($email) || empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Prepare Basic Auth
$auth = base64_encode("$email:$token");

// Call Jira API: /rest/api/2/serverInfo (or /rest/api/2/myself)
$endpoint = "$jira_url/rest/api/2/serverInfo";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $auth",
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Enable only if SSL issues occur

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
// curl_close($ch); // Deprecated in PHP 8.0+

if ($error) {
    echo json_encode(['success' => false, 'error' => "Curl Error: $error"]);
    exit;
}

$data = json_decode($response, true);

if ($http_code === 200 && isset($data['baseUrl'])) {
    echo json_encode([
        'success' => true,
        'info' => [
            'serverTitle' => $data['serverTitle'] ?? 'Jira Server',
            'version' => $data['version'] ?? 'Unknown',
            'baseUrl' => $data['baseUrl']
        ]
    ]);
} elseif ($http_code === 401) {
    echo json_encode(['success' => false, 'error' => "Authentication failed (401). Check username/token."]);
} elseif ($http_code === 404) {
    echo json_encode(['success' => false, 'error' => "Resource not found (404). Check Jira URL."]);
} else {
    // Try to parse error message from Jira
    $msg = "HTTP Code: $http_code";
    if (isset($data['errorMessages'][0])) {
        $msg .= " - " . $data['errorMessages'][0];
    }
    echo json_encode(['success' => false, 'error' => $msg]);
}
