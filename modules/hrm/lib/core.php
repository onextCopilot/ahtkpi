<?php
/**
 * HRM core helpers - bootstrap, auth, escaping, settings, roles, audit, SLA.
 * Every HRM page/endpoint starts with: require_once lib/core.php
 */
require_once __DIR__ . '/../../../config/config.php';

/** Require an authenticated session (redirect to login otherwise). */
function hrm_require_login(): void
{
    if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit(); }
}

/** HTML-escape shorthand. */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ── settings (key/value) ───────────────────────────────────────────── */
function hrm_setting(mysqli $conn, string $key, $default = null)
{
    $st = $conn->prepare('SELECT sval FROM hrm_settings WHERE skey = ?');
    $st->bind_param('s', $key);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    return $r ? $r['sval'] : $default;
}

function hrm_set_setting(mysqli $conn, string $key, string $val): void
{
    $st = $conn->prepare('INSERT INTO hrm_settings (skey,sval) VALUES (?,?) ON DUPLICATE KEY UPDATE sval = VALUES(sval)');
    $st->bind_param('ss', $key, $val);
    $st->execute();
}

/* ── recruitment roles ──────────────────────────────────────────────── */
/** Recruitment roles recognised by the system (SOP §3 + Step-1 requesters). */
function hrm_roles(): array
{
    return [
        'ta_specialist'      => 'TA Specialist',
        'ta_leader'          => 'TA Leader',
        'hrbp'               => 'HRBP',
        'hiring_manager'     => 'Hiring Manager',
        'delivery_manager'   => 'Delivery Manager',
        'head_of_department' => 'Head of Department',
        'bc_director'        => 'BC Director',
        'cdo'                => 'CDO',
        'cfo'                => 'CFO',
        'ceo'                => 'CEO',
    ];
}

/** Human label for an approver_role, supporting multi-role steps ("ceo,cdo"). */
function hrm_role_label(string $key): string
{
    $all = hrm_roles();
    $parts = array_map(fn($k) => $all[trim($k)] ?? trim($k), explode(',', $key));
    return implode(' / ', $parts);
}

/** All user_ids assigned ANY of the given role(s). $role may be "ceo,cdo". */
function hrm_users_with_role(mysqli $conn, string $role): array
{
    $roles = array_values(array_filter(array_map('trim', explode(',', $role))));
    if (!$roles) { return []; }
    $in = implode(',', array_fill(0, count($roles), '?'));
    $st = $conn->prepare("SELECT DISTINCT user_id FROM hrm_role_assignments WHERE rec_role IN ($in)");
    $st->bind_param(str_repeat('s', count($roles)), ...$roles);
    $st->execute();
    $res = $st->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) { $ids[] = (int)$row['user_id']; }
    return $ids;
}

/** Recruitment roles held by a user. */
function hrm_roles_of(mysqli $conn, int $userId): array
{
    $roles = [];
    $st = $conn->prepare('SELECT rec_role FROM hrm_role_assignments WHERE user_id = ?');
    $st->bind_param('i', $userId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $roles[] = $row['rec_role']; }
    return $roles;
}

/**
 * Whether the user may act on a step requiring $role (may be "ceo,cdo").
 * Approvals follow the assigned recruitment roles ONLY — no admin bypass:
 * an admin who is not assigned the role cannot approve.
 */
function hrm_user_has_role(mysqli $conn, int $userId, string $role): bool
{
    $need = array_filter(array_map('trim', explode(',', $role)));
    return (bool)array_intersect($need, hrm_roles_of($conn, $userId));
}

/* ── audit & SLA ────────────────────────────────────────────────────── */
function hrm_audit(mysqli $conn, int $userId, string $action, string $entityType, int $entityId, string $detail = ''): void
{
    $st = $conn->prepare('INSERT INTO hrm_audit_log (user_id,action,entity_type,entity_id,detail) VALUES (?,?,?,?,?)');
    $st->bind_param('issis', $userId, $action, $entityType, $entityId, $detail);
    $st->execute();
}

function hrm_sla_open(mysqli $conn, string $entityType, int $entityId, string $eventKey, string $dueAt): void
{
    $st = $conn->prepare('INSERT INTO hrm_sla_events (entity_type,entity_id,event_key,due_at) VALUES (?,?,?,?)');
    $st->bind_param('siss', $entityType, $entityId, $eventKey, $dueAt);
    $st->execute();
}

function hrm_sla_satisfy(mysqli $conn, string $entityType, int $entityId, string $eventKey): void
{
    $st = $conn->prepare('UPDATE hrm_sla_events SET satisfied_at = NOW() WHERE entity_type = ? AND entity_id = ? AND event_key = ? AND satisfied_at IS NULL');
    $st->bind_param('sis', $entityType, $entityId, $eventKey);
    $st->execute();
}

/** Generate a sequential code like HRF-2026-0007. */
function hrm_next_code(mysqli $conn, string $prefix, string $table): string
{
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';
    $st = $conn->prepare("SELECT code FROM `$table` WHERE code LIKE ? ORDER BY id DESC LIMIT 1");
    $st->bind_param('s', $like);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $seq = $r ? ((int)substr($r['code'], -4)) + 1 : 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $seq);
}

/** Look up a user's display name + email. */
function hrm_user(mysqli $conn, int $userId): array
{
    $st = $conn->prepare('SELECT id, full_name, email FROM users WHERE id = ?');
    $st->bind_param('i', $userId);
    $st->execute();
}

/**
 * Sync job to AHT Talent WordPress site.
 */
function hrm_sync_job_to_wp(mysqli $conn, int $jobId): array
{
    $apiKey = hrm_setting($conn, 'aht_api_key');
    if (!$apiKey) {
        return ['ok' => false, 'error' => 'Chưa cấu hình API Key cho AHT Talent (aht_api_key).'];
    }

    $st = $conn->prepare('SELECT j.*, d.name AS dept_name, o.name AS office_name FROM hrm_jobs j LEFT JOIN departments d ON j.department_id = d.id LEFT JOIN hrm_offices o ON j.office_id = o.id WHERE j.id = ?');
    $st->bind_param('i', $jobId);
    $st->execute();
    $job = $st->get_result()->fetch_assoc();
    if (!$job) {
        return ['ok' => false, 'error' => 'Không tìm thấy tin tuyển dụng.'];
    }

    // Format Salary
    $smin = (float)$job['salary_min'];
    $smax = (float)$job['salary_max'];
    $curr = $job['currency'] ?: 'VND';
    $salary = 'Thỏa thuận';
    if ($smin > 0 && $smax > 0) {
        $salary = number_format($smin) . ' - ' . number_format($smax) . ' ' . $curr;
    } elseif ($smin > 0) {
        $salary = 'Từ ' . number_format($smin) . ' ' . $curr;
    } elseif ($smax > 0) {
        $salary = 'Đến ' . number_format($smax) . ' ' . $curr;
    }

    $deptName = trim($job['dept_name'] ?? '');
    $lcDept = mb_strtolower($deptName);
    $dept = 'BackOffice'; // default fallback
    if (strpos($lcDept, 'it') !== false || strpos($lcDept, 'công nghệ') !== false || strpos($lcDept, 'dev') !== false) {
        $dept = 'IT';
    } elseif (strpos($lcDept, 'sales') !== false || strpos($lcDept, 'marketing') !== false || strpos($lcDept, 'kinh doanh') !== false || strpos($lcDept, 'mkt') !== false) {
        $dept = 'Sales/Marketing';
    } elseif (strpos($lcDept, 'bfsi') !== false || strpos($lcDept, 'tài chính') !== false || strpos($lcDept, 'ngân hàng') !== false) {
        $dept = 'BFSI';
    } elseif (strpos($lcDept, 'akdemy') !== false || strpos($lcDept, 'academy') !== false || strpos($lcDept, 'đào tạo') !== false) {
        $dept = 'Akdemy';
    } elseif (strpos($lcDept, 'remote') !== false || strpos($lcDept, 'expat') !== false || strpos($lcDept, 'hybrid') !== false) {
        $dept = 'Remote/Hybrid/Expat';
    }

    $deadline = !empty($job['deadline']) ? date('d/m/Y', strtotime($job['deadline'])) : '';
    $status = ($job['status'] === 'open') ? 'publish' : 'draft';

    $payload = [
        'title'      => $job['title'],
        'content'    => $job['description'] ?: '',
        'department' => $dept,
        'salary'     => $salary,
        'location'   => $job['office_name'] ?: '',
        'deadline'   => $deadline,
        'status'     => $status,
    ];

    $baseUrl = rtrim(hrm_setting($conn, 'aht_api_url', 'https://t.arrowhitech.com/wp-json/aht/v1/jobs'), '/');
    $channelId = (int)($job['channel_id'] ?? 0);

    $ch = curl_init();
    if ($channelId > 0) {
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/' . $channelId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    } else {
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    // curl_close() is deprecated in PHP 8.5+

    if ($err) {
        return ['ok' => false, 'error' => 'Lỗi kết nối API: ' . $err];
    }

    $resData = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
        $newId = (int)$resData['id'];
        $url = $resData['url'] ?? '';
        $upd = $conn->prepare('UPDATE hrm_jobs SET channel_id = ?, channel_url = ?, channel_synced_at = NOW() WHERE id = ?');
        $upd->bind_param('isi', $newId, $url, $jobId);
        $upd->execute();
        return ['ok' => true, 'id' => $newId, 'url' => $url];
    } else {
        $msg = $resData['message'] ?? 'Lỗi không xác định từ API';
        return ['ok' => false, 'error' => 'API (' . $httpCode . '): ' . $msg];
    }
}
