<?php
/**
 * EmailSenders — quản lý nhiều "người gửi" email dùng chung cho cả hệ thống.
 *
 * Mỗi sender là 1 cấu hình SMTP (kiểu SendGrid / Outlook / Gmail...).
 * SendGrid: host=smtp.sendgrid.net, port=587, username='apikey', password=API key,
 * from_email = sender đã verify trong SendGrid. Gửi qua PHPMailer (SMTP AUTH).
 *
 * Bảng: email_senders. Một sender được đánh dấu is_default để dùng mặc định.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

class EmailSenders
{
    /** Tạo bảng + bổ sung cột nếu thiếu (live an toàn). */
    public static function ensure(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS email_senders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            from_email VARCHAR(190) NOT NULL,
            from_name VARCHAR(150) DEFAULT '',
            provider VARCHAR(20) NOT NULL DEFAULT 'sendgrid',
            smtp_host VARCHAR(120) DEFAULT 'smtp.sendgrid.net',
            smtp_port INT DEFAULT 587,
            smtp_encryption VARCHAR(8) DEFAULT 'tls',
            smtp_user VARCHAR(190) DEFAULT 'apikey',
            smtp_pass TEXT,
            active TINYINT DEFAULT 1,
            is_default TINYINT DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Bảng có thể được tạo từ bản cũ -> đảm bảo đủ cột SMTP.
        foreach ([
            'provider'        => "VARCHAR(20) NOT NULL DEFAULT 'sendgrid'",
            'smtp_host'       => "VARCHAR(120) DEFAULT 'smtp.sendgrid.net'",
            'smtp_port'       => "INT DEFAULT 587",
            'smtp_encryption' => "VARCHAR(8) DEFAULT 'tls'",
            'smtp_user'       => "VARCHAR(190) DEFAULT 'apikey'",
            'smtp_pass'       => "TEXT",
            'reply_to'        => "VARCHAR(190) DEFAULT ''",
            'cc'              => "VARCHAR(400) DEFAULT ''",
            'bcc'             => "VARCHAR(400) DEFAULT ''",
        ] as $col => $def) {
            $r = $conn->query("SHOW COLUMNS FROM email_senders LIKE '" . $conn->real_escape_string($col) . "'");
            if ($r && $r->num_rows === 0) { $conn->query("ALTER TABLE email_senders ADD COLUMN `$col` $def"); }
        }
    }

    public static function all(mysqli $conn): array
    {
        self::ensure($conn);
        $res = $conn->query("SELECT * FROM email_senders ORDER BY is_default DESC, sort_order, id");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function find(mysqli $conn, int $id): ?array
    {
        self::ensure($conn);
        $st = $conn->prepare("SELECT * FROM email_senders WHERE id=?");
        $st->bind_param('i', $id);
        $st->execute();
        return $st->get_result()->fetch_assoc() ?: null;
    }

    /** Sender mặc định đang active và đã có API key/password. */
    public static function default(mysqli $conn): ?array
    {
        self::ensure($conn);
        $row = $conn->query("SELECT * FROM email_senders WHERE active=1 AND smtp_pass IS NOT NULL AND smtp_pass<>'' ORDER BY is_default DESC, sort_order, id LIMIT 1");
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
        $port = (int)$d['smtp_port'];
        $reply = $d['reply_to'] ?? ''; $cc = $d['cc'] ?? ''; $bcc = $d['bcc'] ?? '';
        if ($id) {
            // smtp_pass: để trống = giữ nguyên key cũ.
            $st = $conn->prepare("UPDATE email_senders SET name=?, from_email=?, from_name=?, smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_user=?, reply_to=?, cc=?, bcc=?, smtp_pass=IF(?='', smtp_pass, ?) WHERE id=?");
            $pass = $d['smtp_pass'] ?? '';
            $st->bind_param('ssssisssssssi', $d['name'], $d['from_email'], $d['from_name'], $d['smtp_host'], $port, $d['smtp_encryption'], $d['smtp_user'], $reply, $cc, $bcc, $pass, $pass, $id);
            $st->execute();
            return $id;
        }
        $st = $conn->prepare("INSERT INTO email_senders (name, from_email, from_name, smtp_host, smtp_port, smtp_encryption, smtp_user, smtp_pass, reply_to, cc, bcc) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('ssssissssss', $d['name'], $d['from_email'], $d['from_name'], $d['smtp_host'], $port, $d['smtp_encryption'], $d['smtp_user'], $d['smtp_pass'], $reply, $cc, $bcc);
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

    /* ── Gửi email qua sender (PHPMailer SMTP) ──────────────────────── */

    /** Tách danh sách email (phân tách bởi , ; xuống dòng hoặc khoảng trắng). */
    public static function parseList($v): array
    {
        if (is_array($v)) { $parts = $v; } else { $parts = preg_split('/[,;\s]+/', (string)$v); }
        $out = [];
        foreach ($parts as $p) { $p = trim($p); if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) { $out[strtolower($p)] = $p; } }
        return array_values($out);
    }

    /**
     * Gửi 1 email qua sender chỉ định. Trả ['ok'=>bool, 'error'=>string].
     * $opts: ['from_name'=>'', 'cc'=>'a@b,c@d', 'bcc'=>..., 'reply_to'=>...]
     * CC/BCC/Reply-To = mặc định của sender + giá trị truyền thêm trong $opts.
     * Không ném lỗi để cron/caller tiếp tục chạy.
     */
    public static function send(mysqli $conn, array $sender, string $to, string $subject, string $html, array $opts = []): array
    {
        if (empty($sender['smtp_pass'])) return ['ok' => false, 'error' => 'Sender chưa có API key / mật khẩu SMTP.'];
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $sender['smtp_host'] ?: 'smtp.sendgrid.net';
            $mail->Port = (int)($sender['smtp_port'] ?: 587);
            $enc = strtolower($sender['smtp_encryption'] ?: 'tls');
            if ($enc === 'ssl')      { $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc === 'none') { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
            else                     { $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; }
            $mail->SMTPAuth = true;
            $mail->Username = $sender['smtp_user'] ?: 'apikey';
            $mail->Password = $sender['smtp_pass'];
            $mail->CharSet  = 'UTF-8';
            $mail->setFrom($sender['from_email'], ($opts['from_name'] ?? '') ?: ($sender['from_name'] ?: $sender['from_email']));
            $mail->addAddress($to);

            // Reply-To: opts ưu tiên, fallback mặc định của sender.
            foreach (self::parseList(($opts['reply_to'] ?? '') ?: ($sender['reply_to'] ?? '')) as $a) { $mail->addReplyTo($a); }
            // CC/BCC = mặc định sender + thêm từ opts.
            foreach (self::parseList(array_merge(self::parseList($sender['cc'] ?? ''), self::parseList($opts['cc'] ?? ''))) as $a) { $mail->addCC($a); }
            foreach (self::parseList(array_merge(self::parseList($sender['bcc'] ?? ''), self::parseList($opts['bcc'] ?? ''))) as $a) { $mail->addBCC($a); }

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
