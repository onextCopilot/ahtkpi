<?php
require_once __DIR__ . '/../../../config/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['webauthn_challenge']) || ($_SESSION['webauthn_challenge_type'] ?? '') !== 'login') {
    http_response_code(400);
    echo json_encode(['error' => 'No active login challenge']);
    exit;
}

require_once __DIR__ . '/../../../libs/WebAuthn.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['response'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$challenge = $_SESSION['webauthn_challenge'];
$userId    = (int) ($_SESSION['webauthn_login_user_id'] ?? 0);

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Session expired, please try again']);
    exit;
}

$host   = $_SERVER['HTTP_HOST'];
$rpId   = parse_url('http://' . $host, PHP_URL_HOST);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . $host;

// Find credential
$credentialIdB64 = base64_encode(WebAuthn::fromBase64url($input['id']));

$stmt = $conn->prepare(
    "SELECT p.id, p.public_key, p.sign_count,
            u.id as uid, u.username, u.full_name, u.role, u.department_id,
            u.can_view_invoice, u.can_view_all_debts, u.is_am_bd, u.can_view_odoo_logs
     FROM user_passkeys p
     JOIN users u ON u.id = p.user_id
     WHERE p.credential_id = ? AND p.user_id = ?"
);
$stmt->bind_param("si", $credentialIdB64, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Credential not found']);
    exit;
}

$webauthn = new WebAuthn('AHT KPI', $rpId, $origin);

try {
    $newSignCount = $webauthn->verifyAuthentication(
        $input['response']['clientDataJSON'],
        $input['response']['authenticatorData'],
        $input['response']['signature'],
        $challenge,
        $row['public_key'],
        (int) $row['sign_count']
    );

    // Update sign count and last used timestamp
    $upd = $conn->prepare("UPDATE user_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?");
    $upd->bind_param("ii", $newSignCount, $row['id']);
    $upd->execute();

    // Clear challenge
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_challenge_type'], $_SESSION['webauthn_login_user_id']);

    // Create session — same fields as password login
    $_SESSION['user_id']            = $row['uid'];
    $_SESSION['username']           = $row['username'];
    $_SESSION['full_name']          = $row['full_name'];
    $_SESSION['role']               = $row['role'];
    $_SESSION['department_id']      = $row['department_id'];
    $_SESSION['can_view_invoice']   = $row['can_view_invoice'];
    $_SESSION['can_view_all_debts'] = $row['can_view_all_debts'];
    $_SESSION['is_am_bd']           = $row['is_am_bd'];
    $_SESSION['can_view_odoo_logs'] = $row['can_view_odoo_logs'] ?? 0;

    echo json_encode(['success' => true, 'redirect' => '/dashboard']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
