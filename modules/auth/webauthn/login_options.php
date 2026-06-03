<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');

if ($username === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username required']);
    exit;
}

// Look up user
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // Don't reveal whether user exists; return generic error
    http_response_code(404);
    echo json_encode(['error' => 'No passkey found for this account']);
    exit;
}

$userId = (int) $user['id'];

// Get registered credentials
$stmt = $conn->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$rows) {
    http_response_code(404);
    echo json_encode(['error' => 'No passkey registered for this account. Please sign in with password first.']);
    exit;
}

require_once __DIR__ . '/../../../libs/WebAuthn.php';

$credentialIds = array_map(fn($r) => base64_decode($r['credential_id']), $rows);
$host          = $_SERVER['HTTP_HOST'];
$rpId          = parse_url('http://' . $host, PHP_URL_HOST);
$scheme        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin        = $scheme . '://' . $host;

$webauthn = new WebAuthn('AHT KPI', $rpId, $origin);
$result   = $webauthn->getGetOptions($credentialIds);

$_SESSION['webauthn_challenge']      = $result['challenge'];
$_SESSION['webauthn_challenge_type'] = 'login';
$_SESSION['webauthn_login_user_id']  = $userId;

echo json_encode(['options' => $result['options']]);
