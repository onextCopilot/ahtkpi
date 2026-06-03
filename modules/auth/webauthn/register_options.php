<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../libs/WebAuthn.php';

$userId      = (int) $_SESSION['user_id'];
$username    = $_SESSION['username'];
$fullName    = $_SESSION['full_name'];
$host        = $_SERVER['HTTP_HOST'];
$rpId        = parse_url('http://' . $host, PHP_URL_HOST);
$scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin      = $scheme . '://' . $host;

// Collect existing credential IDs to exclude (prevent duplicate registration)
$stmt = $conn->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$rows       = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$existingIds = array_map(fn($r) => base64_decode($r['credential_id']), $rows);

$webauthn = new WebAuthn('AHT KPI', $rpId, $origin);
$result   = $webauthn->getCreateOptions($userId, $username, $fullName, $existingIds);

$_SESSION['webauthn_challenge']      = $result['challenge'];
$_SESSION['webauthn_challenge_type'] = 'register';

echo json_encode(['options' => $result['options']]);
