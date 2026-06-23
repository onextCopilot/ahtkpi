<?php
/**
 * LinkedIn OAuth 2.0 — bắt đầu cấp quyền & nhận callback.
 * Route: /hrm/linkedin-oauth
 *   - /hrm/linkedin-oauth?channel=ID        → chuyển hướng tới LinkedIn để cấp quyền
 *   - /hrm/linkedin-oauth?code=..&state=..   → LinkedIn gọi lại, đổi code lấy token
 */
require_once __DIR__ . '/lib/core.php';
hrm_require_login();
hrm_ensure_channels_schema($conn);

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Chỉ admin.');
}

$settingsUrl = '/hrm/settings?tab=channels_cfg';

function li_back(string $url, string $type, string $msg): void
{
    $sep = strpos($url, '?') !== false ? '&' : '?';
    header('Location: ' . $url . $sep . 'li_' . $type . '=' . rawurlencode($msg));
    exit;
}

/* ── Callback: LinkedIn trả code về ─────────────────────────────────── */
if (isset($_GET['code']) || isset($_GET['error'])) {
    if (isset($_GET['error'])) {
        li_back($settingsUrl, 'err', 'LinkedIn từ chối: ' . ($_GET['error_description'] ?? $_GET['error']));
    }
    $state = $_GET['state'] ?? '';
    if ($state === '' || $state !== ($_SESSION['li_oauth_state'] ?? '')) {
        li_back($settingsUrl, 'err', 'State không hợp lệ (thử kết nối lại).');
    }
    $channelId = (int)($_SESSION['li_oauth_channel'] ?? 0);
    unset($_SESSION['li_oauth_state'], $_SESSION['li_oauth_channel']);
    if (!$channelId) { li_back($settingsUrl, 'err', 'Thiếu kênh.'); }

    $st = $conn->prepare('SELECT * FROM hrm_channels WHERE id=?');
    $st->bind_param('i', $channelId);
    $st->execute();
    $ch = $st->get_result()->fetch_assoc();
    if (!$ch) { li_back($settingsUrl, 'err', 'Không tìm thấy kênh.'); }
    $cfg = json_decode($ch['config'] ?? '', true); if (!is_array($cfg)) { $cfg = []; }

    $r = hrm_linkedin_token_request([
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code'],
        'client_id'     => $cfg['client_id'] ?? '',
        'client_secret' => $cfg['client_secret'] ?? '',
        'redirect_uri'  => hrm_linkedin_redirect_uri(),
    ]);
    if (empty($r['ok'])) { li_back($settingsUrl, 'err', 'Đổi token thất bại: ' . ($r['error'] ?? '')); }

    $cfg = hrm_linkedin_apply_token($cfg, $r['data']);
    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
    $up = $conn->prepare('UPDATE hrm_channels SET config=? WHERE id=?');
    $up->bind_param('si', $json, $channelId);
    $up->execute();
    hrm_audit($conn, (int)$_SESSION['user_id'], 'linkedin_oauth', 'channel', $channelId, 'connected');
    li_back($settingsUrl, 'ok', 'Đã kết nối LinkedIn thành công!');
}

/* ── Bắt đầu: chuyển hướng người dùng tới LinkedIn ──────────────────── */
$channelId = (int)($_GET['channel'] ?? 0);
if (!$channelId) { li_back($settingsUrl, 'err', 'Thiếu kênh.'); }

$st = $conn->prepare('SELECT * FROM hrm_channels WHERE id=?');
$st->bind_param('i', $channelId);
$st->execute();
$ch = $st->get_result()->fetch_assoc();
if (!$ch || $ch['type'] !== 'linkedin') { li_back($settingsUrl, 'err', 'Kênh không hợp lệ.'); }
$cfg = json_decode($ch['config'] ?? '', true); if (!is_array($cfg)) { $cfg = []; }
if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
    li_back($settingsUrl, 'err', 'Hãy nhập Client ID + Client Secret và lưu kênh trước khi kết nối.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['li_oauth_state'] = $state;
$_SESSION['li_oauth_channel'] = $channelId;

header('Location: ' . hrm_linkedin_authorize_url($cfg['client_id'], $state));
exit;
