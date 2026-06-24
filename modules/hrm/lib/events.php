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

/**
 * Thay biến trong template. Hỗ trợ cả {{ten_bien}} và {ten_bien} (kiểu Base).
 * Chỉ thay các biến có trong $vars; {{...}} còn sót sẽ bị dọn rỗng,
 * còn {single} lạ giữ nguyên để không phá HTML/CSS.
 */
function hrm_merge(string $tpl, array $vars): string
{
    foreach ($vars as $k => $val) {
        $val = (string)$val;
        $tpl = str_replace(['{{' . $k . '}}', '{' . $k . '}'], $val, $tpl);
    }
    // Dọn {{token}} không khớp biến nào -> rỗng.
    return preg_replace('/\{\{\s*\w+\s*\}\}/', '', $tpl);
}

/**
 * Tập biến đầy đủ cho email, tự nạp từ thực thể (application / hrf).
 * $extra (biến caller truyền vào) sẽ ghi đè giá trị tự nạp.
 */
function hrm_email_vars(mysqli $conn, string $entityType, int $entityId, array $extra = []): array
{
    $v = [
        'company'    => hrm_setting($conn, 'company_name', 'AHT TECH'),
        'today'      => date('d/m/Y'),
        'talent_url' => 'https://talent.arrowhitech.com/',
    ];
    $money = function ($min, $max, $cur) {
        $cur = $cur ?: 'VND';
        if ($max > 0) { return number_format((float)$min) . ' - ' . number_format((float)$max) . ' ' . $cur; }
        if ($min > 0) { return 'Từ ' . number_format((float)$min) . ' ' . $cur; }
        return 'Thỏa thuận';
    };

    // Đổ toàn bộ cột của 1 hàng vào $v với tiền tố (vd job_title, candidate_full_name).
    $dump = function (array $row, string $prefix) use (&$v) {
        foreach ($row as $k => $val) { if (!is_array($val)) { $v[$prefix . $k] = (string)$val; } }
    };

    if ($entityType === 'application' && $entityId) {
        $app = $conn->query("SELECT * FROM hrm_applications WHERE id = " . (int)$entityId)->fetch_assoc();
        if ($app) {
            $dump($app, 'app_');
            // Ứng viên: TẤT CẢ field (candidate_<col>) + alias thân thiện.
            $c = $conn->query("SELECT * FROM hrm_candidates WHERE id = " . (int)$app['candidate_id'])->fetch_assoc();
            if ($c) {
                $dump($c, 'candidate_');
                $v['fullname'] = $v['candidate_name'] = $c['full_name'] ?? '';
                $v['email'] = $c['email'] ?? '';
                $v['phone'] = $c['phone'] ?? '';
            }
            // Tin tuyển dụng: TẤT CẢ field (job_<col>) + alias.
            $j = $conn->query("SELECT * FROM hrm_jobs WHERE id = " . (int)$app['job_id'])->fetch_assoc();
            if ($j) {
                $dump($j, 'job_');
                $v['job'] = $v['job_title'] = $v['position'] = $j['title'] ?? '';
                $v['job_code'] = $j['code'] ?? '';
                $v['level'] = $j['level'] ?? '';
                $v['salary'] = $money($j['salary_min'] ?? 0, $j['salary_max'] ?? 0, 'VND');
                if (!empty($j['department_id'])) {
                    $d = $conn->query("SELECT name FROM departments WHERE id = " . (int)$j['department_id'])->fetch_assoc();
                    $v['department'] = $v['dept'] = $d['name'] ?? '';
                }
                if (!empty($j['office_id'])) {
                    $o = $conn->query("SELECT name FROM hrm_offices WHERE id = " . (int)$j['office_id'])->fetch_assoc();
                    $v['office'] = $v['location'] = $o['name'] ?? '';
                }
            }
            if (!empty($app['stage_id'])) {
                $ps = $conn->query("SELECT name FROM hrm_pipeline_stages WHERE id = " . (int)$app['stage_id'])->fetch_assoc();
                $v['stage'] = $ps['name'] ?? '';
            }
        }
    } elseif (($entityType === 'hrf' || $entityType === 'request') && $entityId) {
        $st = $conn->prepare("SELECT rq.title, rq.level, rq.salary_min, rq.salary_max, rq.currency,
                rq.need_by_date, d.name AS dept_name, o.name AS office_name
            FROM hrm_requests rq
            LEFT JOIN departments d ON d.id = rq.department_id
            LEFT JOIN hrm_offices o ON o.id = rq.office_id
            WHERE rq.id = ?");
        $st->bind_param('i', $entityId);
        $st->execute();
        if ($r = $st->get_result()->fetch_assoc()) {
            $dump($r, 'hrf_');
            $v['job'] = $v['job_title'] = $v['position'] = $r['title'];
            $v['level'] = $r['level'] ?: '';
            $v['department'] = $v['dept'] = $r['dept_name'] ?: '';
            $v['office'] = $v['location'] = $r['office_name'] ?: '';
            $v['salary'] = $money($r['salary_min'], $r['salary_max'], $r['currency']);
            $v['onboard_date'] = $r['need_by_date'] ? date('d/m/Y', strtotime($r['need_by_date'])) : '';
        }
    }
    $all = array_merge($v, $extra);
    // Link trong email phải tuyệt đối (mở từ hộp thư, ngoài domain) - link tương đối "/..." sẽ không bấm được.
    if (!empty($all['link']) && $all['link'][0] === '/') {
        $all['link'] = hrm_base_url() . $all['link'];
    }
    return $all;
}

/**
 * Gửi email tự động khi đơn ứng tuyển vào 1 giai đoạn (nếu có template gán cho stage đó).
 * Cấu hình lưu ở hrm_settings key: stage_email_<stage_id> = event_key của template.
 */
function hrm_stage_email_send(mysqli $conn, int $appId, int $stageId): void
{
    if (!$appId || !$stageId) { return; }
    $ekey = trim(hrm_setting($conn, 'stage_email_' . $stageId, ''));
    if ($ekey === '') { return; }
    $st = $conn->prepare("SELECT c.email FROM hrm_applications a JOIN hrm_candidates c ON c.id = a.candidate_id WHERE a.id = ?");
    $st->bind_param('i', $appId);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    if (!empty($r['email'])) {
        hrm_send_email($conn, $ekey, $r['email'], [], 'application', $appId);
    }
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

    $allVars = hrm_email_vars($conn, $entityType, $entityId, $vars);
    $subject = hrm_merge($tpl['subject'], $allVars);
    $body    = hrm_merge($tpl['body_html'], $allVars);

    // Sender theo loại email: ứng viên / nội bộ; fallback sender chung của HRM rồi mặc định hệ thống.
    $aud = $tpl['audience'] ?? '';
    $key = $aud === 'candidate' ? 'hrm_email_sender_candidate' : ($aud === 'internal' ? 'hrm_email_sender_internal' : '');
    $senderRef = $key ? hrm_setting($conn, $key, '') : '';
    if ($senderRef === '') { $senderRef = hrm_setting($conn, 'hrm_email_sender', ''); }

    $ok = false; $err = '';
    try {
        $ok = Mailer::send($conn, $to, $subject, $body, $senderRef !== '' ? ['sender' => $senderRef] : []);
        if (!$ok) { $err = Mailer::$lastError; }
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
