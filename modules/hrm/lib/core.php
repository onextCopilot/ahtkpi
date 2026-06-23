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
    $row = $st->get_result()->fetch_assoc();
    return $row ?: ['id' => $userId, 'full_name' => '', 'email' => ''];
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

/* ── multi-channel job posting (Facebook / LinkedIn API trực tiếp / webhook) ── */

/**
 * Tạo (nếu chưa có) các bảng cấu hình kênh đăng tin + migrate cột.
 * Gọi lazily từ các code path liên quan (settings, job page, api).
 *
 * hrm_channels.config: JSON chứa credential theo từng loại kênh:
 *   - facebook : {"page_id":"...","access_token":"...","api_version":"v25.0"}
 *   - linkedin : {"org_id":"...","access_token":"...","api_version":"202606"}
 *   - webhook  : dùng cột webhook_url + secret
 */
function hrm_ensure_channels_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) { return; }
    $conn->query("CREATE TABLE IF NOT EXISTS hrm_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        type VARCHAR(32) NOT NULL DEFAULT 'webhook',
        icon VARCHAR(40) DEFAULT '',
        webhook_url TEXT,
        secret VARCHAR(255) DEFAULT '',
        config TEXT,
        enabled TINYINT DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migrate: thêm cột config cho bản cài cũ.
    $col = $conn->query("SHOW COLUMNS FROM hrm_channels LIKE 'config'");
    if ($col && $col->num_rows === 0) { $conn->query("ALTER TABLE hrm_channels ADD COLUMN config TEXT AFTER secret"); }
    $conn->query("CREATE TABLE IF NOT EXISTS hrm_job_channel_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        channel_id INT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        http_code INT DEFAULT 0,
        response TEXT,
        post_url VARCHAR(500) DEFAULT '',
        posted_by INT DEFAULT 0,
        posted_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_job_channel (job_id, channel_id),
        KEY idx_job (job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

/** Danh sách loại kênh hỗ trợ. */
function hrm_channel_types(): array
{
    return [
        'facebook' => 'Facebook Page',
        'linkedin' => 'LinkedIn (Company Page)',
        'webhook'  => 'Webhook (tùy chỉnh)',
    ];
}

/** Danh sách kênh đăng tin. */
function hrm_channels(mysqli $conn, bool $onlyEnabled = false): array
{
    hrm_ensure_channels_schema($conn);
    $where = $onlyEnabled ? ' WHERE enabled = 1' : '';
    return $conn->query("SELECT * FROM hrm_channels$where ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
}

/** Build payload (mảng) cho 1 tin tuyển dụng. */
function hrm_build_job_payload(mysqli $conn, int $jobId): ?array
{
    $st = $conn->prepare('SELECT j.*, d.name AS dept_name, o.name AS office_name FROM hrm_jobs j LEFT JOIN departments d ON j.department_id = d.id LEFT JOIN hrm_offices o ON j.office_id = o.id WHERE j.id = ?');
    $st->bind_param('i', $jobId);
    $st->execute();
    $job = $st->get_result()->fetch_assoc();
    if (!$job) { return null; }

    $smin = (float)$job['salary_min'];
    $smax = (float)$job['salary_max'];
    $curr = $job['currency'] ?: 'VND';
    $salary = 'Thỏa thuận';
    if ($smin > 0 && $smax > 0)      { $salary = number_format($smin) . ' - ' . number_format($smax) . ' ' . $curr; }
    elseif ($smin > 0)               { $salary = 'Từ ' . number_format($smin) . ' ' . $curr; }
    elseif ($smax > 0)               { $salary = 'Đến ' . number_format($smax) . ' ' . $curr; }

    $deadline = !empty($job['deadline']) ? date('d/m/Y', strtotime($job['deadline'])) : '';

    return [
        'id'          => (int)$job['id'],
        'code'        => $job['code'] ?: '',
        'title'       => $job['title'],
        'department'  => trim($job['dept_name'] ?? ''),
        'level'       => $job['level'] ?: '',
        'location'    => $job['office_name'] ?: '',
        'salary'      => $salary,
        'headcount'   => (int)$job['headcount'],
        'deadline'    => $deadline,
        'description' => $job['description'] ?: '',
        'jd_skills'   => $job['jd_skills'] ?: '',
        'status'      => $job['status'],
        'apply_url'   => $job['channel_url'] ?: '',
    ];
}

/** Nội dung text dùng để đăng bài (Facebook/LinkedIn). */
function hrm_job_post_text(array $p): string
{
    $lines = ['📢 ' . ($p['title'] ?? '') . ' | ArrowHiTech tuyển dụng'];
    $meta = [];
    if (!empty($p['location'])) { $meta[] = '📍 ' . $p['location']; }
    if (!empty($p['level']))    { $meta[] = '💼 ' . $p['level']; }
    if ($meta)                  { $lines[] = implode('  ', $meta); }
    if (!empty($p['salary']))   { $lines[] = '💰 ' . $p['salary']; }
    if (!empty($p['deadline'])) { $lines[] = '⏰ Hạn nộp: ' . $p['deadline']; }

    $desc = trim(preg_replace('/\s+/', ' ', strip_tags((string)($p['description'] ?? ''))));
    if ($desc !== '') {
        if (mb_strlen($desc) > 600) { $desc = mb_substr($desc, 0, 600) . '…'; }
        $lines[] = '';
        $lines[] = $desc;
    }
    if (!empty($p['apply_url'])) { $lines[] = ''; $lines[] = '👉 Ứng tuyển: ' . $p['apply_url']; }
    return implode("\n", $lines);
}

/** Escape ký tự đặc biệt cho LinkedIn "Little Text Format". */
function hrm_linkedin_escape(string $s): string
{
    return preg_replace('/([\\\\\(\)\[\]\{\}<>@|#*_~])/', '\\\\$1', $s);
}

/* ── LinkedIn OAuth 2.0 (tự động lấy & làm mới token) ─────────────────── */

/** Base URL của site hiện tại (scheme + host). */
function hrm_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host;
}

/** Redirect URI cho OAuth LinkedIn (đăng ký đúng URL này trong app LinkedIn). */
function hrm_linkedin_redirect_uri(): string
{
    return hrm_base_url() . '/hrm/linkedin-oauth';
}

/** URL để người dùng cấp quyền (bước 1 OAuth). */
function hrm_linkedin_authorize_url(string $clientId, string $state, string $scope = 'w_organization_social r_organization_social'): string
{
    return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => $clientId,
        'redirect_uri'  => hrm_linkedin_redirect_uri(),
        'state'         => $state,
        'scope'         => $scope,
    ]);
}

/** POST tới endpoint token của LinkedIn, trả mảng JSON. */
function hrm_linkedin_token_request(array $form): array
{
    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, 'https://www.linkedin.com/oauth/v2/accessToken');
    curl_setopt($cu, CURLOPT_POST, true);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cu, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($cu, CURLOPT_POSTFIELDS, http_build_query($form));
    curl_setopt($cu, CURLOPT_TIMEOUT, 25);
    $resp = curl_exec($cu);
    $code = (int)curl_getinfo($cu, CURLINFO_HTTP_CODE);
    $err  = curl_error($cu);
    if ($err) { return ['ok' => false, 'error' => $err]; }
    $data = json_decode((string)$resp, true);
    if ($code >= 200 && $code < 300 && !empty($data['access_token'])) { return ['ok' => true, 'data' => $data]; }
    $msg = $data['error_description'] ?? ($data['error'] ?? ('HTTP ' . $code));
    return ['ok' => false, 'error' => $msg, 'http' => $code];
}

/** Gộp dữ liệu token (từ response LinkedIn) vào config kênh. */
function hrm_linkedin_apply_token(array $cfg, array $tok): array
{
    $now = time();
    $cfg['access_token'] = $tok['access_token'];
    $cfg['token_expires_at'] = $now + (int)($tok['expires_in'] ?? 0);
    if (!empty($tok['refresh_token'])) {
        $cfg['refresh_token'] = $tok['refresh_token'];
        $cfg['refresh_expires_at'] = $now + (int)($tok['refresh_token_expires_in'] ?? 0);
    }
    return $cfg;
}

/**
 * Đảm bảo access token LinkedIn còn hạn — nếu sắp/đã hết hạn và có refresh_token
 * thì tự làm mới và lưu lại vào DB. Trả config (đã cập nhật token nếu cần).
 */
function hrm_linkedin_ensure_token(mysqli $conn, int $channelId, array $cfg): array
{
    $exp = (int)($cfg['token_expires_at'] ?? 0);
    // Còn hạn (đệm 5 phút) → dùng luôn.
    if (!empty($cfg['access_token']) && $exp > time() + 300) { return $cfg; }
    // Hết hạn: thử refresh.
    $rt = $cfg['refresh_token'] ?? '';
    $cid = $cfg['client_id'] ?? '';
    $cs  = $cfg['client_secret'] ?? '';
    if ($rt === '' || $cid === '' || $cs === '') { return $cfg; } // không refresh được → giữ nguyên
    if (!empty($cfg['refresh_expires_at']) && (int)$cfg['refresh_expires_at'] < time()) { return $cfg; } // refresh token cũng hết hạn

    $r = hrm_linkedin_token_request([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $rt,
        'client_id'     => $cid,
        'client_secret' => $cs,
    ]);
    if (empty($r['ok'])) { return $cfg; }
    $cfg = hrm_linkedin_apply_token($cfg, $r['data']);
    // Lưu lại.
    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
    $st = $conn->prepare('UPDATE hrm_channels SET config=? WHERE id=?');
    $st->bind_param('si', $json, $channelId);
    $st->execute();
    return $cfg;
}

/** Đăng tin lên Facebook Page (Graph API). */
function hrm_post_to_facebook(array $cfg, array $payload): array
{
    $pageId = trim($cfg['page_id'] ?? '');
    $token  = trim($cfg['access_token'] ?? '');
    $ver    = trim($cfg['api_version'] ?? '') ?: 'v25.0';
    if ($pageId === '' || $token === '') { return ['ok' => false, 'error' => 'Thiếu Page ID hoặc Access Token Facebook.']; }

    $body = ['message' => hrm_job_post_text($payload), 'access_token' => $token];
    if (!empty($payload['apply_url'])) { $body['link'] = $payload['apply_url']; }

    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, 'https://graph.facebook.com/' . $ver . '/' . rawurlencode($pageId) . '/feed');
    curl_setopt($cu, CURLOPT_POST, true);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cu, CURLOPT_POSTFIELDS, http_build_query($body));
    curl_setopt($cu, CURLOPT_TIMEOUT, 25);
    $resp = curl_exec($cu);
    $code = (int)curl_getinfo($cu, CURLINFO_HTTP_CODE);
    $err  = curl_error($cu);
    if ($err) { return ['ok' => false, 'error' => 'Lỗi kết nối Facebook: ' . $err, 'raw' => $err]; }

    $rd = json_decode((string)$resp, true);
    if ($code >= 200 && $code < 300 && !empty($rd['id'])) {
        return ['ok' => true, 'http' => $code, 'url' => 'https://www.facebook.com/' . $rd['id'], 'raw' => (string)$resp];
    }
    $msg = $rd['error']['message'] ?? ('HTTP ' . $code);
    return ['ok' => false, 'error' => 'Facebook: ' . $msg, 'http' => $code, 'raw' => (string)$resp];
}

/** Helper: GET 1 URL trả JSON. */
function hrm_http_get_json(string $url): array
{
    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, $url);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cu, CURLOPT_TIMEOUT, 25);
    $resp = curl_exec($cu);
    $code = (int)curl_getinfo($cu, CURLINFO_HTTP_CODE);
    $err  = curl_error($cu);
    if ($err) { return ['ok' => false, 'error' => $err, 'http' => 0]; }
    return ['ok' => $code >= 200 && $code < 300, 'http' => $code, 'data' => json_decode((string)$resp, true), 'raw' => (string)$resp];
}

/**
 * Đổi token Facebook ngắn hạn → Page token dài hạn.
 * B1: fb_exchange_token → user token dài hạn. B2: /me/accounts → page token (không hết hạn).
 * Trả: ['ok'=>true, 'page_token','page_id','page_name'] hoặc ['ok'=>true,'pages'=>[...],'need_pick'=>true] hoặc error.
 */
function hrm_fb_long_lived_page_token(string $appId, string $appSecret, string $shortToken, string $pageId = '', string $ver = 'v25.0'): array
{
    $ver = $ver ?: 'v25.0';
    // B1: đổi sang user token dài hạn.
    $u = 'https://graph.facebook.com/' . $ver . '/oauth/access_token?grant_type=fb_exchange_token'
        . '&client_id=' . rawurlencode($appId)
        . '&client_secret=' . rawurlencode($appSecret)
        . '&fb_exchange_token=' . rawurlencode($shortToken);
    $r1 = hrm_http_get_json($u);
    if (!$r1['ok'] || empty($r1['data']['access_token'])) {
        $msg = $r1['data']['error']['message'] ?? ($r1['error'] ?? ('HTTP ' . ($r1['http'] ?? 0)));
        return ['ok' => false, 'error' => 'Đổi token dài hạn thất bại: ' . $msg];
    }
    $longUser = $r1['data']['access_token'];

    // B2: lấy danh sách Page + page token.
    $r2 = hrm_http_get_json('https://graph.facebook.com/' . $ver . '/me/accounts?fields=id,name,access_token&access_token=' . rawurlencode($longUser));
    if (!$r2['ok'] || !isset($r2['data']['data'])) {
        $msg = $r2['data']['error']['message'] ?? ($r2['error'] ?? ('HTTP ' . ($r2['http'] ?? 0)));
        return ['ok' => false, 'error' => 'Lấy danh sách Page thất bại: ' . $msg];
    }
    $pages = [];
    foreach ($r2['data']['data'] as $pg) {
        $pages[] = ['id' => $pg['id'] ?? '', 'name' => $pg['name'] ?? '', 'token' => $pg['access_token'] ?? ''];
    }
    if (!$pages) { return ['ok' => false, 'error' => 'Tài khoản không quản trị Page nào (hoặc token thiếu quyền pages_show_list).']; }

    // Nếu đã có page_id → chọn đúng Page đó.
    if ($pageId !== '') {
        foreach ($pages as $pg) {
            if ((string)$pg['id'] === (string)$pageId) {
                return ['ok' => true, 'page_token' => $pg['token'], 'page_id' => $pg['id'], 'page_name' => $pg['name']];
            }
        }
        return ['ok' => false, 'error' => 'Không tìm thấy Page ID ' . $pageId . ' trong các Page bạn quản trị.'];
    }
    // Chưa có page_id: nếu chỉ 1 Page thì tự dùng, nhiều Page thì để người dùng chọn.
    if (count($pages) === 1) {
        return ['ok' => true, 'page_token' => $pages[0]['token'], 'page_id' => $pages[0]['id'], 'page_name' => $pages[0]['name']];
    }
    return ['ok' => true, 'need_pick' => true, 'pages' => array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $pages), 'pages_full' => $pages];
}

/** Đăng tin lên LinkedIn Company Page (Posts API /rest/posts). */
function hrm_post_to_linkedin(array $cfg, array $payload): array
{
    $org   = trim($cfg['org_id'] ?? '');
    $token = trim($cfg['access_token'] ?? '');
    $ver   = trim($cfg['api_version'] ?? '') ?: '202606';
    if ($org === '' || $token === '') { return ['ok' => false, 'error' => 'Thiếu Organization ID hoặc Access Token LinkedIn.']; }
    $authorUrn = (strpos($org, 'urn:li:organization:') === 0) ? $org : ('urn:li:organization:' . $org);

    $body = [
        'author'        => $authorUrn,
        'commentary'    => hrm_linkedin_escape(hrm_job_post_text($payload)),
        'visibility'    => 'PUBLIC',
        'distribution'  => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
        'lifecycleState' => 'PUBLISHED',
        'isReshareDisabledByAuthor' => false,
    ];

    $restliId = '';
    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, 'https://api.linkedin.com/rest/posts');
    curl_setopt($cu, CURLOPT_POST, true);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cu, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
        'LinkedIn-Version: ' . $ver,
    ]);
    curl_setopt($cu, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    curl_setopt($cu, CURLOPT_TIMEOUT, 25);
    curl_setopt($cu, CURLOPT_HEADERFUNCTION, function ($c, $h) use (&$restliId) {
        if (stripos($h, 'x-restli-id:') === 0) { $restliId = trim(substr($h, strlen('x-restli-id:'))); }
        return strlen($h);
    });
    $resp = curl_exec($cu);
    $code = (int)curl_getinfo($cu, CURLINFO_HTTP_CODE);
    $err  = curl_error($cu);
    if ($err) { return ['ok' => false, 'error' => 'Lỗi kết nối LinkedIn: ' . $err, 'raw' => $err]; }

    if ($code >= 200 && $code < 300) {
        $url = $restliId ? ('https://www.linkedin.com/feed/update/' . $restliId . '/') : '';
        return ['ok' => true, 'http' => $code, 'url' => $url, 'raw' => (string)$resp];
    }
    $rd = json_decode((string)$resp, true);
    $msg = $rd['message'] ?? ('HTTP ' . $code);
    return ['ok' => false, 'error' => 'LinkedIn: ' . $msg, 'http' => $code, 'raw' => (string)$resp];
}

/** Đăng tin tới webhook tùy chỉnh (POST JSON). */
function hrm_post_to_webhook(array $ch, array $payload): array
{
    $url = trim($ch['webhook_url'] ?? '');
    if ($url === '') { return ['ok' => false, 'error' => 'Kênh chưa có Webhook URL.']; }
    $headers = ['Content-Type: application/json'];
    if (!empty($ch['secret'])) { $headers[] = 'X-Webhook-Secret: ' . $ch['secret']; }

    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, $url);
    curl_setopt($cu, CURLOPT_POST, true);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cu, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($cu, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($cu, CURLOPT_TIMEOUT, 20);
    curl_setopt($cu, CURLOPT_FOLLOWLOCATION, true);
    $resp = curl_exec($cu);
    $code = (int)curl_getinfo($cu, CURLINFO_HTTP_CODE);
    $err  = curl_error($cu);
    if ($err) { return ['ok' => false, 'error' => 'Lỗi kết nối: ' . $err, 'raw' => $err]; }

    $postUrl = '';
    $rd = json_decode((string)$resp, true);
    if (is_array($rd)) {
        foreach (['url', 'link', 'permalink', 'post_url'] as $k) {
            if (!empty($rd[$k]) && is_string($rd[$k])) { $postUrl = $rd[$k]; break; }
        }
    }
    if ($code >= 200 && $code < 300) { return ['ok' => true, 'http' => $code, 'url' => $postUrl, 'raw' => (string)$resp]; }
    return ['ok' => false, 'error' => 'Webhook trả về HTTP ' . $code, 'http' => $code, 'raw' => (string)$resp];
}

/**
 * Đăng 1 tin lên 1 kênh — dispatch theo loại kênh.
 * Ghi kết quả vào hrm_job_channel_posts (upsert theo job+channel).
 */
function hrm_post_job_to_channel(mysqli $conn, int $jobId, int $channelId, int $uid = 0): array
{
    hrm_ensure_channels_schema($conn);

    $st = $conn->prepare('SELECT * FROM hrm_channels WHERE id = ?');
    $st->bind_param('i', $channelId);
    $st->execute();
    $ch = $st->get_result()->fetch_assoc();
    if (!$ch)                 { return ['ok' => false, 'error' => 'Không tìm thấy kênh.']; }
    if (!(int)$ch['enabled']) { return ['ok' => false, 'error' => 'Kênh đang tắt.']; }

    $payload = hrm_build_job_payload($conn, $jobId);
    if ($payload === null)    { return ['ok' => false, 'error' => 'Không tìm thấy tin tuyển dụng.']; }
    $payload['channel'] = $ch['name'];

    $cfg = json_decode($ch['config'] ?? '', true);
    if (!is_array($cfg)) { $cfg = []; }

    switch ($ch['type']) {
        case 'facebook': $r = hrm_post_to_facebook($cfg, $payload); break;
        case 'linkedin':
            $cfg = hrm_linkedin_ensure_token($conn, $channelId, $cfg); // tự refresh nếu hết hạn
            $r = hrm_post_to_linkedin($cfg, $payload);
            break;
        default:         $r = hrm_post_to_webhook($ch, $payload); break;
    }

    $status   = !empty($r['ok']) ? 'success' : 'failed';
    $httpCode = (int)($r['http'] ?? 0);
    $postUrl  = $r['url'] ?? '';
    $respStore = !empty($r['ok']) ? ('OK: ' . ($r['raw'] ?? '')) : (($r['error'] ?? 'Lỗi') . ' | ' . ($r['raw'] ?? ''));
    if (mb_strlen($respStore) > 4000) { $respStore = mb_substr($respStore, 0, 4000); }

    $up = $conn->prepare('INSERT INTO hrm_job_channel_posts (job_id,channel_id,status,http_code,response,post_url,posted_by)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status=VALUES(status), http_code=VALUES(http_code), response=VALUES(response), post_url=VALUES(post_url), posted_by=VALUES(posted_by)');
    $up->bind_param('iisissi', $jobId, $channelId, $status, $httpCode, $respStore, $postUrl, $uid);
    $up->execute();

    return $r;
}
