<?php
/**
 * Daily notification digest.
 *
 * For every active user with an email, gathers their notifications via
 * NotificationCenter and, if any exist, sends one summary email via the
 * configured SMTP (Mailer / system_settings).
 *
 * Schedule (crontab) — every morning at 07:00:
 *   0 7 * * * php /path/to/project/cron/notification_digest.php
 *
 * Manual web trigger (optional): /cron/notification_digest.php?secret=YOURSECRET
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/NotificationCenter.php';
require_once __DIR__ . '/../includes/Mailer.php';

// Optional shared-secret check for the web trigger.
if (php_sapi_name() !== 'cli') {
    $expected = getenv('CRON_SECRET') ?: '';
    if ($expected === '' || ($_GET['secret'] ?? '') !== $expected) {
        http_response_code(403);
        die("Forbidden.");
    }
}

if (!Mailer::isConfigured($conn)) {
    fwrite(STDERR, "[notification_digest] SMTP not configured — aborting.\n");
    exit(1);
}

$sev_color = ['danger' => '#dc2626', 'warning' => '#d97706', 'success' => '#16a34a', 'info' => '#2563eb'];
$base_url = getenv('APP_BASE_URL') ?: '';   // e.g. https://kpi.arrowhitech.com

$sent = 0; $skipped = 0;
$res = $conn->query("SELECT id, full_name, email, is_am_bd FROM users WHERE status = 'active' AND email IS NOT NULL AND email <> ''");
if (!$res) { fwrite(STDERR, "[notification_digest] user query failed\n"); exit(1); }

while ($u = $res->fetch_assoc()) {
    $session = [
        'user_id'   => (int) $u['id'],
        'full_name' => $u['full_name'],
        'is_am_bd'  => (int) $u['is_am_bd'],
    ];
    $items = NotificationCenter::items($conn, $session);
    if (empty($items)) { $skipped++; continue; }

    // Build HTML email
    $rows = '';
    foreach ($items as $it) {
        $c = $sev_color[$it['severity']] ?? '#2563eb';
        $link = $it['link'] ?? '';
        if ($link && $base_url && $link[0] === '/') $link = rtrim($base_url, '/') . $link;
        $linkHtml = $link ? '<a href="' . htmlspecialchars($link) . '" style="color:#2563eb;text-decoration:none;font-weight:600;">' . htmlspecialchars($it['link_label'] ?: 'Xem') . ' →</a>' : '';
        $rows .= '<tr><td style="padding:10px 12px;border-bottom:1px solid #eef2f7;border-left:4px solid ' . $c . ';">'
            . '<div style="font-weight:700;color:#0f172a;font-size:14px;">' . htmlspecialchars($it['title']) . '</div>'
            . '<div style="color:#475569;font-size:13px;margin:2px 0 4px;">' . htmlspecialchars($it['body']) . '</div>'
            . $linkHtml . '</td></tr>';
    }
    $count = count($items);
    $name = htmlspecialchars($u['full_name']);
    $dashLink = $base_url ? (rtrim($base_url, '/') . '/notifications') : '/notifications';
    $body = '<div style="font-family:Inter,Arial,sans-serif;max-width:640px;margin:0 auto;">'
        . '<h2 style="color:#0f172a;">Xin chào ' . $name . ',</h2>'
        . '<p style="color:#475569;font-size:14px;">Bạn có <strong>' . $count . '</strong> thông báo cần chú ý hôm nay:</p>'
        . '<table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">' . $rows . '</table>'
        . '<p style="margin-top:18px;"><a href="' . htmlspecialchars($dashLink) . '" style="background:#0f172a;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Mở trung tâm thông báo</a></p>'
        . '<p style="color:#94a3b8;font-size:12px;margin-top:20px;">Email tự động từ AHT KPI System.</p></div>';

    if (Mailer::sendSystem($conn, $u['email'], "[AHT KPI] Bạn có $count thông báo cần xử lý", $body)) {
        $sent++;
    } else {
        $skipped++;
    }
}

echo "[notification_digest] done. sent=$sent skipped=$skipped\n";
