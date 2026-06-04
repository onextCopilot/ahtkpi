<?php
/**
 * Mailer — thin wrapper that sends mail using the SMTP settings stored in the
 * `system_settings` table (keys: smtp_host/port/user/pass/encryption/from_*).
 *
 * Centralises the SMTP-read + SimpleMailer pattern that was duplicated across
 * modules (pakd_detail, ceo_review, settings/users ...).
 *
 * Usage:
 *   require_once __DIR__ . '/Mailer.php';
 *   Mailer::sendSystem($conn, 'a@b.com', 'Subject', '<p>HTML body</p>');
 */
class Mailer
{
    /** Read smtp_* settings into an associative array. */
    public static function smtpConfig($conn): array
    {
        $smtp = [];
        $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
        if ($res) {
            while ($r = $res->fetch_assoc()) $smtp[$r['setting_key']] = $r['setting_value'];
        }
        return $smtp;
    }

    public static function isConfigured($conn): bool
    {
        $s = self::smtpConfig($conn);
        return !empty($s['smtp_host']) && !empty($s['smtp_user']) && !empty($s['smtp_pass']);
    }

    /**
     * Send one email using the configured SMTP. Returns true on success.
     * Never throws — logs and returns false so callers/cron keep running.
     */
    public static function sendSystem($conn, string $to, string $subject, string $htmlBody): bool
    {
        if (empty($to)) return false;
        $s = self::smtpConfig($conn);
        if (empty($s['smtp_host']) || empty($s['smtp_user']) || empty($s['smtp_pass'])) {
            return false;
        }
        try {
            require_once __DIR__ . '/../modules/includes/SimpleMailer.php';
            $mailer = new SimpleMailer(
                $s['smtp_host'],
                (int) ($s['smtp_port'] ?? 587),
                $s['smtp_user'],
                $s['smtp_pass']
            );
            $fromName = $s['smtp_from_name'] ?? 'AHT KPI System';
            return (bool) $mailer->send($to, $subject, $htmlBody, $fromName);
        } catch (\Throwable $e) {
            error_log('Mailer::sendSystem failed: ' . $e->getMessage());
            return false;
        }
    }
}
