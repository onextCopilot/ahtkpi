<?php
/**
 * HRM event dispatcher - one event fans out to in-app notifications,
 * email (templated), and the audit/SLA logs. Same event_key drives all
 * channels; each channel can be toggled in hrm_settings.
 *
 *   hrm_dispatch($conn, 'hrf_approval_request', [
 *       'recipients'  => [12, 30],          // user_ids -> in-app notification
 *       'notif'       => ['title'=>..., 'body'=>..., 'severity'=>'warning', 'link'=>'/hrm/requests?id=7'],
 *       'email'       => [['to'=>'a@x.com', 'vars'=>[...]]],  // 0+ emails, template by event_key
 *       'entity_type' => 'hrf', 'entity_id' => 7,
 *       'actor_id'    => 5,                  // who triggered (for audit)
 *   ]);
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/../../../includes/Mailer.php';

/** Replace {{token}} placeholders from $vars. */
function hrm_merge(string $tpl, array $vars): string
{
    return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($vars) {
        return isset($vars[$m[1]]) ? (string)$vars[$m[1]] : '';
    }, $tpl);
}

/** Insert an in-app notification (surfaced via NotificationCenter, kind=hrm). */
function hrm_notify(mysqli $conn, array $userIds, array $a): void
{
    if (hrm_setting($conn, 'notif_enabled', '1') !== '1') { return; }
    $title = $a['title'] ?? '';
    $body  = $a['body'] ?? '';
    $sev   = $a['severity'] ?? 'info';
    $link  = $a['link'] ?? '';
    $ek    = $a['event_key'] ?? '';
    $et    = $a['entity_type'] ?? '';
    $eid   = (int)($a['entity_id'] ?? 0);
    $st = $conn->prepare('INSERT INTO hrm_notifications (user_id,event_key,title,body,severity,link,entity_type,entity_id) VALUES (?,?,?,?,?,?,?,?)');
    foreach (array_unique(array_filter($userIds)) as $uid) {
        $uid = (int)$uid;
        $st->bind_param('issssssi', $uid, $ek, $title, $body, $sev, $link, $et, $eid);
        $st->execute();
    }
}

/** Render the template for $eventKey, send via Mailer, log to hrm_email_log. */
function hrm_send_email(mysqli $conn, string $eventKey, string $to, array $vars, string $entityType = '', int $entityId = 0): bool
{
    if (hrm_setting($conn, 'email_enabled', '1') !== '1' || $to === '') { return false; }

    $st = $conn->prepare('SELECT subject, body_html, enabled, audience FROM hrm_email_templates WHERE event_key = ?');
    $st->bind_param('s', $eventKey);
    $st->execute();
    $tpl = $st->get_result()->fetch_assoc();
    if (!$tpl || !$tpl['enabled']) { return false; }

    $subject = hrm_merge($tpl['subject'], $vars);
    $body    = hrm_merge($tpl['body_html'], $vars);

    // Sender theo loại email: ứng viên / nội bộ; fallback sender chung của HRM rồi mặc định hệ thống.
    $aud = $tpl['audience'] ?? '';
    $key = $aud === 'candidate' ? 'hrm_email_sender_candidate' : ($aud === 'internal' ? 'hrm_email_sender_internal' : '');
    $senderRef = $key ? hrm_setting($conn, $key, '') : '';
    if ($senderRef === '') { $senderRef = hrm_setting($conn, 'hrm_email_sender', ''); }

    $ok = false; $err = '';
    try {
        $ok = Mailer::send($conn, $to, $subject, $body, $senderRef !== '' ? ['sender' => $senderRef] : []);
    } catch (Throwable $e) { $err = $e->getMessage(); }

    $status = $ok ? 'sent' : 'failed';
    $lg = $conn->prepare('INSERT INTO hrm_email_log (event_key,to_email,subject,entity_type,entity_id,status,error) VALUES (?,?,?,?,?,?,?)');
    $lg->bind_param('ssssiss', $eventKey, $to, $subject, $entityType, $entityId, $status, $err);
    $lg->execute();
    return $ok;
}

/** Orchestrate all channels for one event. */
function hrm_dispatch(mysqli $conn, string $eventKey, array $ctx): void
{
    $et  = $ctx['entity_type'] ?? '';
    $eid = (int)($ctx['entity_id'] ?? 0);

    // In-app notifications.
    if (!empty($ctx['recipients']) && !empty($ctx['notif'])) {
        $n = $ctx['notif'] + ['event_key' => $eventKey, 'entity_type' => $et, 'entity_id' => $eid];
        hrm_notify($conn, $ctx['recipients'], $n);
    }

    // Emails (0+).
    foreach (($ctx['email'] ?? []) as $mail) {
        hrm_send_email($conn, $eventKey, $mail['to'] ?? '', $mail['vars'] ?? [], $et, $eid);
    }

    // Audit trail.
    hrm_audit($conn, (int)($ctx['actor_id'] ?? 0), 'event:' . $eventKey, $et, $eid, $ctx['audit'] ?? '');
}
