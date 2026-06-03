<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['webauthn_challenge']) || ($_SESSION['webauthn_challenge_type'] ?? '') !== 'register') {
    http_response_code(400);
    echo json_encode(['error' => 'No active registration challenge']);
    exit;
}

require_once __DIR__ . '/../../../libs/WebAuthn.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['response'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$userId    = (int) $_SESSION['user_id'];
$challenge = $_SESSION['webauthn_challenge'];
$host      = $_SERVER['HTTP_HOST'];
$rpId      = parse_url('http://' . $host, PHP_URL_HOST);
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin    = $scheme . '://' . $host;

$webauthn = new WebAuthn('AHT KPI', $rpId, $origin);

try {
    $data = $webauthn->verifyRegistration(
        $input['response']['clientDataJSON'],
        $input['response']['attestationObject'],
        $challenge
    );

    $credentialIdB64 = base64_encode($data['credentialId']);
    $deviceName      = trim($input['deviceName'] ?? '') ?: self_detect_device();
    $signCount       = (int) $data['signCount'];

    // Check for duplicate
    $check = $conn->prepare("SELECT id FROM user_passkeys WHERE credential_id = ?");
    $check->bind_param("s", $credentialIdB64);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        throw new Exception('This device is already registered');
    }

    $stmt = $conn->prepare(
        "INSERT INTO user_passkeys (user_id, credential_id, public_key, sign_count, device_name) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issis", $userId, $credentialIdB64, $data['publicKey'], $signCount, $deviceName);

    if (!$stmt->execute()) {
        throw new Exception('Failed to save passkey: ' . $conn->error);
    }

    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_type']);
    echo json_encode(['success' => true, 'deviceName' => $deviceName, 'id' => $stmt->insert_id]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function self_detect_device(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iPhone / iPad';
    if (str_contains($ua, 'Android'))  return 'Android';
    if (str_contains($ua, 'Macintosh')) return 'Mac';
    if (str_contains($ua, 'Windows'))  return 'Windows PC';
    return 'Passkey';
}
