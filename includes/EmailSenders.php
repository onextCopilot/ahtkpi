<?php
/**
 * EmailSenders — quản lý nhiều "người gửi" email dùng chung cho cả hệ thống.
 *
 * Mỗi sender là 1 mailbox Outlook/Microsoft 365 kết nối qua OAuth2 (Azure App),
 * theo chuẩn FluentSMTP. Gửi qua PHPMailer với AuthType XOAUTH2.
 *
 * Bảng: email_senders. Một sender được đánh dấu is_default để dùng mặc định.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\OAuthTokenProvider;

/** Token provider tối giản cho PHPMailer XOAUTH2 (không cần league/oauth2-client). */
class MsTokenProvider implements OAuthTokenProvider
{
    private $userEmail;
    private $accessToken;
    public function __construct(string $userEmail, string $accessToken)
    {
        $this->userEmail = $userEmail;
        $this->accessToken = $accessToken;
    }
    public function getOauth64()
    {
        return base64_encode('user=' . $this->userEmail . "\1auth=Bearer " . $this->accessToken . "\1\1");
    }
}

class EmailSenders
{
    const AUTH_BASE  = 'https://login.microsoftonline.com/';
    const SCOPE      = 'offline_access https://outlook.office.com/SMTP.Send';

    /** Tạo bảng nếu chưa có (live an toàn). */
    public static function ensure(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS email_senders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            from_email VARCHAR(190) NOT NULL,
            from_name VARCHAR(150) DEFAULT '',
            provider VARCHAR(20) NOT NULL DEFAULT 'ms_oauth',
            tenant_id VARCHAR(100) DEFAULT 'common',
            client_id VARCHAR(190) DEFAULT '',
            client_secret VARCHAR(255) DEFAULT '',
            refresh_token TEXT,
            access_token TEXT,
            token_expires INT DEFAULT 0,
            smtp_host VARCHAR(120) DEFAULT 'smtp.office365.com',
            smtp_port INT DEFAULT 587,
            active TINYINT DEFAULT 1,
            is_default TINYINT DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function all(mysqli $conn): array
    {
        self::ensure($conn);
        $res = $conn->query("SELECT * FROM email_senders ORDER BY is_default DESC, sort_order, id");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function find(mysqli $conn, int $id): ?array
    {
        $st = $conn->prepare("SELECT * FROM email_senders WHERE id=?");
        $st->bind_param('i', $id);
        $st->execute();
        return $st->get_result()->fetch_assoc() ?: null;
    }

    /** Sender mặc định đang active (đã kết nối). */
    public static function default(mysqli $conn): ?array
    {
        self::ensure($conn);
        $row = $conn->query("SELECT * FROM email_senders WHERE active=1 AND refresh_token IS NOT NULL AND refresh_token<>'' ORDER BY is_default DESC, sort_order, id LIMIT 1");
        return $row ? ($row->fetch_assoc() ?: null) : null;
    }

    /** Resolve sender theo id, theo from_email, hoặc mặc định. */
    public static function resolve(mysqli $conn, $ref = null): ?array
    {
        self::ensure($conn);
        if ($ref !== null && $ref !== '') {
            if (is_numeric($ref)) { $s = self::find($conn, (int)$ref); if ($s) return $s; }
            elseif (is_string($ref) && strpos($ref, '@') !== false) {
                $st = $conn->prepare("SELECT * FROM email_senders WHERE from_email=? LIMIT 1");
                $st->bind_param('s', $ref); $st->execute();
                $s = $st->get_result()->fetch_assoc(); if ($s) return $s;
            }
        }
        return self::default($conn);
    }

    public static function save(mysqli $conn, array $d): int
    {
        self::ensure($conn);
        $id = (int)($d['id'] ?? 0);
        if ($id) {
            $st = $conn->prepare("UPDATE email_senders SET name=?, from_email=?, from_name=?, tenant_id=?, client_id=?, client_secret=IF(?='', client_secret, ?) WHERE id=?");
            $sec = $d['client_secret'] ?? '';
            $st->bind_param('sssssssi', $d['name'], $d['from_email'], $d['from_name'], $d['tenant_id'], $d['client_id'], $sec, $sec, $id);
            $st->execute();
            return $id;
        }
        $st = $conn->prepare("INSERT INTO email_senders (name, from_email, from_name, tenant_id, client_id, client_secret) VALUES (?,?,?,?,?,?)");
        $st->bind_param('ssssss', $d['name'], $d['from_email'], $d['from_name'], $d['tenant_id'], $d['client_id'], $d['client_secret']);
        $st->execute();
        return $st->insert_id;
    }

    public static function delete(mysqli $conn, int $id): void
    {
        $st = $conn->prepare("DELETE FROM email_senders WHERE id=?");
        $st->bind_param('i', $id); $st->execute();
    }

    public static function setDefault(mysqli $conn, int $id): void
    {
        $conn->query("UPDATE email_senders SET is_default=0");
        $st = $conn->prepare("UPDATE email_senders SET is_default=1, active=1 WHERE id=?");
        $st->bind_param('i', $id); $st->execute();
    }

    public static function setActive(mysqli $conn, int $id, int $active): void
    {
        $st = $conn->prepare("UPDATE email_senders SET active=? WHERE id=?");
        $st->bind_param('ii', $active, $id); $st->execute();
    }

    /* ── OAuth2 (Microsoft 365 / Outlook) ───────────────────────────── */

    /** URL đăng nhập Microsoft để cấp quyền cho sender. */
    public static function authUrl(array $sender, string $redirectUri, string $state): string
    {
        $tenant = $sender['tenant_id'] ?: 'common';
        return self::AUTH_BASE . rawurlencode($tenant) . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id'     => $sender['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'response_mode' => 'query',
            'scope'         => self::SCOPE,
            'state'         => $state,
            'prompt'        => 'consent',
        ]);
    }

    /** Đổi authorization code lấy refresh/access token và lưu lại. */
    public static function exchangeCode(mysqli $conn, array $sender, string $code, string $redirectUri): array
    {
        $tok = self::tokenRequest($sender, [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirectUri,
        ]);
        if (empty($tok['refresh_token'])) {
            return ['ok' => false, 'error' => $tok['error_description'] ?? ($tok['error'] ?? 'Không nhận được refresh_token')];
        }
        self::storeTokens($conn, (int)$sender['id'], $tok);
        return ['ok' => true];
    }

    /** Lấy access token còn hạn (tự refresh nếu cần). */
    public static function accessToken(mysqli $conn, array $sender): ?string
    {
        if (!empty($sender['access_token']) && (int)$sender['token_expires'] > (time() + 60)) {
            return $sender['access_token'];
        }
        if (empty($sender['refresh_token'])) return null;
        $tok = self::tokenRequest($sender, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $sender['refresh_token'],
        ]);
        if (empty($tok['access_token'])) return null;
        self::storeTokens($conn, (int)$sender['id'], $tok);
        return $tok['access_token'];
    }

    private static function tokenRequest(array $sender, array $params): array
    {
        $tenant = $sender['tenant_id'] ?: 'common';
        $url = self::AUTH_BASE . rawurlencode($tenant) . '/oauth2/v2.0/token';
        $body = array_merge([
            'client_id'     => $sender['client_id'],
            'client_secret' => $sender['client_secret'],
            'scope'         => self::SCOPE,
        ], $params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) { $err = curl_error($ch); curl_close($ch); return ['error' => 'curl', 'error_description' => $err]; }
        curl_close($ch);
        $data = json_decode($resp, true);
        return is_array($data) ? $data : ['error' => 'parse', 'error_description' => 'Phản hồi không hợp lệ'];
    }

    private static function storeTokens(mysqli $conn, int $id, array $tok): void
    {
        $expires = time() + (int)($tok['expires_in'] ?? 3600);
        $access  = $tok['access_token'] ?? '';
        // refresh_token có thể không trả lại ở lần refresh — giữ cái cũ nếu vậy.
        if (!empty($tok['refresh_token'])) {
            $st = $conn->prepare("UPDATE email_senders SET access_token=?, token_expires=?, refresh_token=? WHERE id=?");
            $st->bind_param('sisi', $access, $expires, $tok['refresh_token'], $id);
        } else {
            $st = $conn->prepare("UPDATE email_senders SET access_token=?, token_expires=? WHERE id=?");
            $st->bind_param('sii', $access, $expires, $id);
        }
        $st->execute();
    }

    /* ── Gửi email qua sender (PHPMailer XOAUTH2) ───────────────────── */

    /**
     * Gửi 1 email qua sender chỉ định. Trả ['ok'=>bool, 'error'=>string].
     * Không ném lỗi để cron/caller tiếp tục chạy.
     */
    public static function send(mysqli $conn, array $sender, string $to, string $subject, string $html, string $fromNameOverride = ''): array
    {
        $token = self::accessToken($conn, $sender);
        if (!$token) return ['ok' => false, 'error' => 'Sender chưa kết nối hoặc token hết hạn. Hãy kết nối lại.'];
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $sender['smtp_host'] ?: 'smtp.office365.com';
            $mail->Port       = (int)($sender['smtp_port'] ?: 587);
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAuth   = true;
            $mail->AuthType   = 'XOAUTH2';
            $mail->setOAuth(new MsTokenProvider($sender['from_email'], $token));
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($sender['from_email'], $fromNameOverride ?: ($sender['from_name'] ?: $sender['from_email']));
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags($html));
            $mail->send();
            return ['ok' => true];
        } catch (\Throwable $e) {
            error_log('EmailSenders::send failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
