<?php
/**
 * HRM AJAX endpoint - single handler for recruitment write actions.
 * Route: /hrm/api   ·   responds JSON.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/approval.php';

header('Content-Type: application/json; charset=utf-8');
hrm_require_login();

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jout($ok, $data = []) { echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE); exit; }

switch ($action) {

    /* ── HRF: create (draft) or create + submit ───────────────────────── */
    case 'save_request': {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { jout(false, ['error' => 'Thiếu tên vị trí']); }

        $code        = hrm_next_code($conn, 'HRF', 'hrm_requests');
        $dept        = (int)($_POST['department_id'] ?? 0);
        $office      = (int)($_POST['office_id'] ?? 0);
        $level       = trim($_POST['level'] ?? '');
        $qty         = max(1, (int)($_POST['quantity'] ?? 1));
        $smin        = (float)($_POST['salary_min'] ?? 0);
        $smax        = (float)($_POST['salary_max'] ?? 0);
        $needBy      = $_POST['need_by_date'] ?? null; if (!$needBy) { $needBy = null; }
        $reason      = trim($_POST['reason'] ?? '');
        $jd          = trim($_POST['jd'] ?? '');
        $type        = ($_POST['request_type'] ?? 'replacement') === 'new_hc' ? 'new_hc' : 'replacement';
        $emp         = trim($_POST['employment_type'] ?? '');
        $expr        = trim($_POST['experience_required'] ?? '');
        $prio        = trim($_POST['priority'] ?? '') ?: 'Trung bình';
        $approver    = (string)($_POST['approver_role'] ?? ''); if (!isset(hrm_hrf_approver_roles()[$approver])) { $approver = ''; }
        $submit      = !empty($_POST['submit']);

        if ($dept <= 0)             { jout(false, ['error' => 'Chọn bộ phận']); }
        if ($level === '')          { jout(false, ['error' => 'Nhập Level']); }
        if (!$needBy)               { jout(false, ['error' => 'Chọn ngày cần onboard']); }
        if ($emp === '')            { jout(false, ['error' => 'Chọn hình thức làm việc']); }
        if ($submit && $approver === '') { jout(false, ['error' => 'Chọn người phê duyệt']); }

        hrm_ensure_request_columns($conn);
        $st = $conn->prepare('INSERT INTO hrm_requests (code,title,department_id,office_id,level,quantity,salary_min,salary_max,need_by_date,reason,jd,request_type,employment_type,experience_required,priority,approver_role,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"draft",?)');
        $st->bind_param('ssiisiddssssssssi', $code, $title, $dept, $office, $level, $qty, $smin, $smax, $needBy, $reason, $jd, $type, $emp, $expr, $prio, $approver, $uid);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        $id = $st->insert_id;
        hrm_audit($conn, $uid, 'hrf_create', 'hrf', $id, $code);

        if ($submit) {
            $n = hrm_approval_start($conn, 'hrf', $id, $type, $uid);
            if ($n === 0) { jout(false, ['error' => 'Chưa cấu hình luồng duyệt HRF']); }
        }
        jout(true, ['id' => $id, 'code' => $code]);
    }

    /* ── HRF: update an editable request (draft / rejected) ───────────── */
    case 'update_request': {
        $id = (int)($_POST['id'] ?? 0);
        $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $id)->fetch_assoc();
        if (!$req) { jout(false, ['error' => 'Không tìm thấy HRF']); }
        $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
        if ((int)$req['created_by'] !== $uid && !$isAdmin) { jout(false, ['error' => 'Không có quyền']); }
        if (!in_array($req['status'], ['draft','rejected'], true) && !$isAdmin) { jout(false, ['error' => 'HRF không thể sửa ở trạng thái này']); }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') { jout(false, ['error' => 'Thiếu tên vị trí']); }
        $dept = (int)($_POST['department_id'] ?? 0); $office = (int)($_POST['office_id'] ?? 0);
        $level = trim($_POST['level'] ?? ''); $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $smin = (float)($_POST['salary_min'] ?? 0); $smax = (float)($_POST['salary_max'] ?? 0);
        $needBy = ($_POST['need_by_date'] ?? '') ?: null;
        $reason = trim($_POST['reason'] ?? ''); $jd = trim($_POST['jd'] ?? '');
        $type = ($_POST['request_type'] ?? 'replacement') === 'new_hc' ? 'new_hc' : 'replacement';
        $emp = trim($_POST['employment_type'] ?? '');
        $expr = trim($_POST['experience_required'] ?? '');
        $prio = trim($_POST['priority'] ?? '') ?: 'Trung bình';
        $approver = (string)($_POST['approver_role'] ?? ''); if (!isset(hrm_hrf_approver_roles()[$approver])) { $approver = ''; }
        $submit = !empty($_POST['submit']);

        if ($dept <= 0)    { jout(false, ['error' => 'Chọn bộ phận']); }
        if ($level === '') { jout(false, ['error' => 'Nhập Level']); }
        if (!$needBy)      { jout(false, ['error' => 'Chọn ngày cần onboard']); }
        if ($emp === '')   { jout(false, ['error' => 'Chọn hình thức làm việc']); }
        if ($submit && $approver === '') { jout(false, ['error' => 'Chọn người phê duyệt']); }

        hrm_ensure_request_columns($conn);
        $st = $conn->prepare('UPDATE hrm_requests SET title=?,department_id=?,office_id=?,level=?,quantity=?,salary_min=?,salary_max=?,need_by_date=?,reason=?,jd=?,request_type=?,employment_type=?,experience_required=?,priority=?,approver_role=? WHERE id=?');
        $st->bind_param('siisiddssssssssi', $title, $dept, $office, $level, $qty, $smin, $smax, $needBy, $reason, $jd, $type, $emp, $expr, $prio, $approver, $id);
        $st->execute();
        hrm_audit($conn, $uid, 'hrf_update', 'hrf', $id, '');

        if ($submit) {
            $n = hrm_approval_start($conn, 'hrf', $id, $type, $uid);   // resets prior steps + sets pending
            if ($n === 0) { jout(false, ['error' => 'Chưa cấu hình luồng duyệt HRF']); }
        } elseif ($req['status'] === 'rejected') {
            $conn->query('UPDATE hrm_requests SET status="draft" WHERE id=' . $id);  // back to draft on save
        }
        jout(true, ['id' => $id]);
    }

    /* ── HRF: submit an existing draft for approval ───────────────────── */
    case 'submit_request': {
        $id = (int)($_POST['id'] ?? 0);
        $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $id)->fetch_assoc();
        if (!$req) { jout(false, ['error' => 'Không tìm thấy HRF']); }
        if ((int)$req['created_by'] !== $uid && ($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Không có quyền']); }
        if ($req['status'] !== 'draft') { jout(false, ['error' => 'HRF đã được gửi']); }
        if (!isset(hrm_hrf_approver_roles()[$req['approver_role'] ?? ''])) { jout(false, ['error' => 'Chọn người phê duyệt trước khi gửi']); }
        $n = hrm_approval_start($conn, 'hrf', $id, $req['request_type'], $uid);
        if ($n === 0) { jout(false, ['error' => 'Chưa cấu hình luồng duyệt HRF']); }
        jout(true, ['steps' => $n]);
    }

    /* ── HRF: reopen a finished request so it can be re-decided ───────── */
    case 'reopen_request': {
        $id = (int)($_POST['id'] ?? 0);
        $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $id)->fetch_assoc();
        if (!$req) { jout(false, ['error' => 'Không tìm thấy HRF']); }
        if (!in_array($req['status'], ['approved', 'rejected'], true)) { jout(false, ['error' => 'HRF chưa kết thúc duyệt']); }
        if ((int)$req['job_id'] > 0) { jout(false, ['error' => 'HRF đã tạo tin tuyển dụng, không thể mở lại']); }

        // Last acted step.
        $step = $conn->query("SELECT * FROM hrm_approvals WHERE entity_type='hrf' AND entity_id=$id AND acted_at IS NOT NULL ORDER BY step_order DESC LIMIT 1")->fetch_assoc();
        if (!$step) { jout(false, ['error' => 'Không có bước duyệt để mở lại']); }
        if (!hrm_user_has_role($conn, $uid, $step['approver_role'])) { jout(false, ['error' => 'Bạn không phải người duyệt bước này']); }

        $sla = 48;
        foreach (hrm_approval_flow($conn, 'hrf', '') as $f) { if ((int)$f['step_order'] === (int)$step['step_order']) { $sla = (int)$f['sla_hours']; } }
        $due = date('Y-m-d H:i:s', strtotime("+{$sla} hours"));
        $up = $conn->prepare('UPDATE hrm_approvals SET status="pending", acted_at=NULL, acted_by=0, note="", due_at=? WHERE id=?');
        $up->bind_param('si', $due, $step['id']);
        $up->execute();
        $conn->query("UPDATE hrm_requests SET status='pending' WHERE id=$id");
        hrm_audit($conn, $uid, 'hrf_reopen', 'hrf', $id, 'từ ' . $req['status']);
        jout(true);
    }

    /* ── Approval: approve / reject current step ──────────────────────── */
    case 'approve': case 'reject': {
        $approvalId = (int)($_POST['approval_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $result = hrm_approval_act($conn, $approvalId, $uid, $action === 'approve' ? 'approved' : 'rejected', $note);
        if (in_array($result, ['invalid', 'forbidden'], true)) {
            jout(false, ['error' => $result === 'forbidden' ? 'Bạn không phải người duyệt bước này' : 'Bước duyệt không hợp lệ']);
        }
        jout(true, ['result' => $result]);
    }

    /* ── HRF: cancel ──────────────────────────────────────────────────── */
    case 'cancel_request': {
        $id = (int)($_POST['id'] ?? 0);
        $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $id)->fetch_assoc();
        if (!$req) { jout(false, ['error' => 'Không tìm thấy']); }
        if ((int)$req['created_by'] !== $uid && ($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Không có quyền']); }
        $conn->query('UPDATE hrm_requests SET status = "cancelled" WHERE id = ' . $id);
        hrm_audit($conn, $uid, 'hrf_cancel', 'hrf', $id, '');
        jout(true);
    }

    /* ── Settings: recruitment-role assignment (admin) ────────────────── */
    case 'assign_role': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $u = (int)($_POST['user_id'] ?? 0);
        $role = trim($_POST['rec_role'] ?? '');
        if (!$u || !array_key_exists($role, hrm_roles())) { jout(false, ['error' => 'Dữ liệu không hợp lệ']); }
        $st = $conn->prepare('INSERT IGNORE INTO hrm_role_assignments (user_id,rec_role) VALUES (?,?)');
        $st->bind_param('is', $u, $role);
        $st->execute();
        jout(true);
    }
    case 'remove_role': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $rid = (int)($_POST['id'] ?? 0);
        $conn->query('DELETE FROM hrm_role_assignments WHERE id = ' . $rid);
        jout(true);
    }

    /* ── HRM Access: cấp/thu hồi quyền truy cập HRM (admin only) ──────── */
    case 'hrm_access_add': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $targetId = (int)($_POST['user_id'] ?? 0);
        if (!$targetId) { jout(false, ['error' => 'Chọn user']); }
        // Đọc danh sách hiện tại.
        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='hrm_allowed_user_ids' LIMIT 1");
        $ids = [];
        if ($res && $row = $res->fetch_assoc()) {
            $ids = array_map('intval', json_decode($row['setting_value'] ?? '[]', true) ?: []);
        }
        if (!in_array($targetId, $ids, true)) { $ids[] = $targetId; }
        $json = json_encode(array_values($ids));
        $st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('hrm_allowed_user_ids', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $st->bind_param('s', $json);
        $st->execute();
        hrm_audit($conn, $uid, 'hrm_access_grant', 'user', $targetId, '');
        jout(true);
    }
    case 'hrm_access_remove': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $targetId = (int)($_POST['user_id'] ?? 0);
        if (!$targetId) { jout(false, ['error' => 'Thiếu dữ liệu']); }
        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='hrm_allowed_user_ids' LIMIT 1");
        $ids = [];
        if ($res && $row = $res->fetch_assoc()) {
            $ids = array_map('intval', json_decode($row['setting_value'] ?? '[]', true) ?: []);
        }
        $ids = array_values(array_filter($ids, fn($i) => $i !== $targetId));
        $json = json_encode($ids);
        $st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('hrm_allowed_user_ids', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $st->bind_param('s', $json);
        $st->execute();
        hrm_audit($conn, $uid, 'hrm_access_revoke', 'user', $targetId, '');
        jout(true);
    }

    /* ── Settings: email template + toggles ───────────────────────────── */
    case 'save_email_template': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $id = (int)($_POST['id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body_html'] ?? '';
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        if (isset($_POST['name'])) {
            $name = trim($_POST['name']);
            if ($name === '') { jout(false, ['error' => 'Tên template không được trống']); }
            $st = $conn->prepare('UPDATE hrm_email_templates SET name = ?, subject = ?, body_html = ?, enabled = ? WHERE id = ?');
            $st->bind_param('sssii', $name, $subject, $body, $enabled, $id);
        } else {
            $st = $conn->prepare('UPDATE hrm_email_templates SET subject = ?, body_html = ?, enabled = ? WHERE id = ?');
            $st->bind_param('ssii', $subject, $body, $enabled, $id);
        }
        $st->execute();
        jout(true);
    }
    case 'create_email_template': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu tên template']); }
        $audience = ($_POST['audience'] ?? 'candidate') === 'internal' ? 'internal' : 'candidate';
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body_html'] ?? '';
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        // event_key: từ input hoặc slug từ tên; chuẩn hóa + đảm bảo duy nhất.
        $base = strtolower(trim($_POST['event_key'] ?? '')) ?: strtolower($name);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base);
        $base = trim($base, '_'); if ($base === '') { $base = 'custom'; }
        if (!preg_match('/^custom_/', $base)) { $base = 'custom_' . $base; }   // tránh đụng event_key hệ thống
        $ekey = $base; $i = 1;
        $chk = $conn->prepare('SELECT id FROM hrm_email_templates WHERE event_key = ?');
        while (true) {
            $chk->bind_param('s', $ekey); $chk->execute();
            if (!$chk->get_result()->fetch_row()) { break; }
            $ekey = $base . '_' . (++$i);
        }
        $st = $conn->prepare('INSERT INTO hrm_email_templates (event_key, name, subject, body_html, audience, enabled) VALUES (?,?,?,?,?,?)');
        $st->bind_param('sssssi', $ekey, $name, $subject, $body, $audience, $enabled);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        jout(true, ['id' => $st->insert_id, 'event_key' => $ekey]);
    }
    case 'delete_email_template': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $id = (int)($_POST['id'] ?? 0);
        $st = $conn->prepare('DELETE FROM hrm_email_templates WHERE id = ?');
        $st->bind_param('i', $id); $st->execute();
        jout(true);
    }
    case 'save_setting': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        hrm_set_setting($conn, trim($_POST['skey'] ?? ''), trim($_POST['sval'] ?? ''));
        jout(true);
    }

    /* ── Settings: kênh đăng tin (Facebook / LinkedIn / Webhook) ──────── */
    case 'save_channel':
    case 'update_channel': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        hrm_ensure_channels_schema($conn);
        $cid  = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu tên kênh']); }
        $type = $_POST['type'] ?? 'webhook';
        if (!array_key_exists($type, hrm_channel_types())) { $type = 'webhook'; }
        $icon   = trim($_POST['icon'] ?? '');
        $url    = '';
        $secret = '';
        $config = null;
        if ($type === 'facebook') {
            $config = json_encode([
                'page_id'      => trim($_POST['page_id'] ?? ''),
                'access_token' => trim($_POST['access_token'] ?? ''),
                'api_version'  => trim($_POST['api_version'] ?? '') ?: 'v25.0',
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'linkedin') {
            // Giữ lại token OAuth đã có khi sửa kênh (không ghi đè bằng form).
            $existing = [];
            if ($cid) {
                $er = $conn->query('SELECT config FROM hrm_channels WHERE id=' . (int)$cid)->fetch_assoc();
                $tmp = json_decode($er['config'] ?? '', true);
                if (is_array($tmp)) { $existing = $tmp; }
            }
            $cfgArr = [
                'org_id'        => trim($_POST['org_id'] ?? ''),
                'client_id'     => trim($_POST['client_id'] ?? ''),
                'client_secret' => trim($_POST['client_secret'] ?? ''),
                'api_version'   => trim($_POST['api_version'] ?? '') ?: '202606',
            ];
            // Bảo toàn token (OAuth tự lấy). Nếu admin nhập access_token thủ công thì ưu tiên cái mới.
            foreach (['access_token', 'refresh_token', 'token_expires_at', 'refresh_expires_at'] as $kk) {
                if (isset($existing[$kk])) { $cfgArr[$kk] = $existing[$kk]; }
            }
            $manualToken = trim($_POST['access_token'] ?? '');
            if ($manualToken !== '') { $cfgArr['access_token'] = $manualToken; }
            $config = json_encode($cfgArr, JSON_UNESCAPED_UNICODE);
        } else {
            $url    = trim($_POST['webhook_url'] ?? '');
            $secret = trim($_POST['secret'] ?? '');
        }

        if ($action === 'update_channel') {
            if (!$cid) { jout(false, ['error' => 'Thiếu dữ liệu']); }
            $st = $conn->prepare('UPDATE hrm_channels SET name=?, type=?, icon=?, webhook_url=?, secret=?, config=? WHERE id=?');
            $st->bind_param('ssssssi', $name, $type, $icon, $url, $secret, $config, $cid);
            $st->execute();
            jout(true);
        }
        $st = $conn->prepare('INSERT INTO hrm_channels (name,type,icon,webhook_url,secret,config,enabled) VALUES (?,?,?,?,?,?,1)');
        $st->bind_param('ssssss', $name, $type, $icon, $url, $secret, $config);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        hrm_audit($conn, $uid, 'channel_add', 'channel', $st->insert_id, $name);
        jout(true, ['id' => $st->insert_id]);
    }
    case 'toggle_channel': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        hrm_ensure_channels_schema($conn);
        $cid = (int)($_POST['id'] ?? 0);
        $on  = !empty($_POST['enabled']) ? 1 : 0;
        $st = $conn->prepare('UPDATE hrm_channels SET enabled=? WHERE id=?');
        $st->bind_param('ii', $on, $cid);
        $st->execute();
        jout(true);
    }
    case 'remove_channel': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        hrm_ensure_channels_schema($conn);
        $cid = (int)($_POST['id'] ?? 0);
        $conn->query('DELETE FROM hrm_channels WHERE id = ' . $cid);
        $conn->query('DELETE FROM hrm_job_channel_posts WHERE channel_id = ' . $cid);
        jout(true);
    }

    /* Đổi token Facebook ngắn hạn → Page token dài hạn (không lưu App Secret). */
    case 'fb_exchange_token': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $appId      = trim($_POST['app_id'] ?? '');
        $appSecret  = trim($_POST['app_secret'] ?? '');
        $shortToken = trim($_POST['short_token'] ?? '');
        $pageId     = trim($_POST['page_id'] ?? '');
        $ver        = trim($_POST['api_version'] ?? '') ?: 'v25.0';
        if ($appId === '' || $appSecret === '' || $shortToken === '') {
            jout(false, ['error' => 'Cần nhập App ID, App Secret và token hiện tại.']);
        }
        $r = hrm_fb_long_lived_page_token($appId, $appSecret, $shortToken, $pageId, $ver);
        if (empty($r['ok'])) { jout(false, ['error' => $r['error'] ?? 'Lỗi đổi token']); }
        if (!empty($r['need_pick'])) { jout(true, ['need_pick' => true, 'pages' => $r['pages']]); }
        jout(true, ['page_token' => $r['page_token'], 'page_id' => $r['page_id'], 'page_name' => $r['page_name']]);
    }

    /* Đăng 1 tin lên nhiều kênh đã chọn. */
    case 'post_job_channels': {
        $jid = (int)($_POST['job_id'] ?? 0);
        $ids = array_filter(array_map('intval', explode(',', $_POST['channel_ids'] ?? '')));
        if (!$jid || !$ids) { jout(false, ['error' => 'Chọn ít nhất 1 kênh']); }
        $results = []; $okCount = 0;
        foreach ($ids as $cid) {
            $r = hrm_post_job_to_channel($conn, $jid, $cid, $uid);
            if (!empty($r['ok'])) { $okCount++; }
            $results[$cid] = $r;
        }
        hrm_audit($conn, $uid, 'job_post_channels', 'job', $jid, implode(',', $ids));
        jout(true, ['posted' => $okCount, 'total' => count($ids), 'results' => $results]);
    }

    /* ── Settings: per-department stage owners (BC / TA) ──────────────── */
    case 'save_stage_owner': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $dept = (int)($_POST['department_id'] ?? 0);
        $stage = (int)($_POST['stage_id'] ?? 0);
        $otype = ($_POST['owner_type'] ?? '') === 'ta' ? 'ta' : 'bc';
        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$dept || !$stage) { jout(false, ['error' => 'Thiếu dữ liệu']); }
        if ($userId > 0) {
            $st = $conn->prepare('INSERT INTO hrm_stage_owners (department_id,stage_id,owner_type,user_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)');
            $st->bind_param('iisi', $dept, $stage, $otype, $userId);
            $st->execute();
        } else {
            $st = $conn->prepare('DELETE FROM hrm_stage_owners WHERE department_id=? AND stage_id=? AND owner_type=?');
            $st->bind_param('iis', $dept, $stage, $otype);
            $st->execute();
        }
        jout(true);
    }

    /* ── Settings: per-stage SLA (hours) ──────────────────────────────── */
    case 'save_stage_sla': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $sid = (int)($_POST['stage_id'] ?? 0);
        $hours = max(0, (int)($_POST['sla_hours'] ?? 0));
        $st = $conn->prepare('UPDATE hrm_pipeline_stages SET sla_hours = ? WHERE id = ?');
        $st->bind_param('ii', $hours, $sid);
        $st->execute();
        jout(true);
    }

    /* ── Settings: offices (master data for the office field) ─────────── */
    case 'save_office': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $name = trim($_POST['name'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu tên văn phòng']); }
        $st = $conn->prepare('INSERT INTO hrm_offices (name, address, active) VALUES (?, ?, 1)');
        $st->bind_param('ss', $name, $addr);
        $st->execute();
        jout(true, ['id' => $st->insert_id]);
    }
    case 'update_office': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $oid = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        if (!$oid || $name === '') { jout(false, ['error' => 'Thiếu dữ liệu']); }
        $st = $conn->prepare('UPDATE hrm_offices SET name = ?, address = ? WHERE id = ?');
        $st->bind_param('ssi', $name, $addr, $oid);
        $st->execute();
        jout(true);
    }
    case 'remove_office': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $oid = (int)($_POST['id'] ?? 0);
        $conn->query('DELETE FROM hrm_offices WHERE id = ' . $oid);
        jout(true);
    }
    case 'reorder_offices': {
        if (($_SESSION['role'] ?? '') !== 'admin') { jout(false, ['error' => 'Chỉ admin']); }
        $ids = array_filter(array_map('intval', explode(',', $_POST['order'] ?? '')));
        $pos = 1;
        $st = $conn->prepare('UPDATE hrm_offices SET sort_order = ? WHERE id = ?');
        foreach ($ids as $oid) { $st->bind_param('ii', $pos, $oid); $st->execute(); $pos++; }
        jout(true);
    }

    /* ════════════════ Phase 2: jobs · pipeline · offer ════════════════ */

    /* Create a job from an approved HRF. */
    case 'create_job': {
        $hrfId = (int)($_POST['hrf_id'] ?? 0);
        $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $hrfId)->fetch_assoc();
        if (!$req || $req['status'] !== 'approved') { jout(false, ['error' => 'HRF chưa được duyệt']); }
        if ((int)$req['job_id'] > 0) { jout(false, ['error' => 'HRF đã có tin', 'id' => (int)$req['job_id']]); }
        $code = hrm_next_code($conn, 'JOB', 'hrm_jobs');
        $jd = $req['jd'] ?? '';
        $st = $conn->prepare('INSERT INTO hrm_jobs (request_id,code,title,department_id,office_id,level,salary_min,salary_max,headcount,description,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,"draft",?)');
        $st->bind_param('issiisddisi', $hrfId, $code, $req['title'], $req['department_id'], $req['office_id'], $req['level'], $req['salary_min'], $req['salary_max'], $req['quantity'], $jd, $uid);
        $st->execute();
        $jid = $st->insert_id;
        $conn->query("UPDATE hrm_requests SET job_id=$jid WHERE id=$hrfId");
        hrm_audit($conn, $uid, 'job_create', 'job', $jid, 'from HRF ' . $req['code']);
        hrm_sync_job_to_wp($conn, $jid);
        jout(true, ['id' => $jid]);
    }

    case 'save_job': {
        $jid = (int)($_POST['id'] ?? 0);
        $f = [
            'title' => trim($_POST['title'] ?? ''), 'department_id' => (int)($_POST['department_id'] ?? 0),
            'office_id' => (int)($_POST['office_id'] ?? 0), 'level' => trim($_POST['level'] ?? ''),
            'headcount' => max(1, (int)($_POST['headcount'] ?? 1)), 'salary_min' => (float)($_POST['salary_min'] ?? 0),
            'salary_max' => (float)($_POST['salary_max'] ?? 0), 'deadline' => ($_POST['deadline'] ?? '') ?: null,
            'status' => $_POST['status'] ?? 'draft', 'description' => $_POST['description'] ?? '',
            'jd_skills' => $_POST['jd_skills'] ?? '', 'probation_kpi' => $_POST['probation_kpi'] ?? '',
        ];
        if ($f['title'] === '') { jout(false, ['error' => 'Thiếu tên vị trí']); }
        $st = $conn->prepare('UPDATE hrm_jobs SET title=?,department_id=?,office_id=?,level=?,headcount=?,salary_min=?,salary_max=?,deadline=?,status=?,description=?,jd_skills=?,probation_kpi=? WHERE id=?');
        $st->bind_param('siisiddsssssi', $f['title'],$f['department_id'],$f['office_id'],$f['level'],$f['headcount'],$f['salary_min'],$f['salary_max'],$f['deadline'],$f['status'],$f['description'],$f['jd_skills'],$f['probation_kpi'],$jid);
        $st->execute();
        hrm_audit($conn, $uid, 'job_save', 'job', $jid, '');
        $sync = hrm_sync_job_to_wp($conn, $jid);
        jout(true, ['sync' => $sync]);
    }

    case 'sync_job_channel': {
        $jid = (int)($_POST['id'] ?? 0);
        if (!$jid) { jout(false, ['error' => 'Thiếu dữ liệu']); }
        $res = hrm_sync_job_to_wp($conn, $jid);
        if (!$res['ok']) { jout(false, ['error' => $res['error'] ?? 'Lỗi đồng bộ']); }
        jout(true, ['url' => $res['url'] ?? '']);
    }

    /* Add candidate + create application at SCREENING. */
    case 'add_candidate': {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $name = trim($_POST['full_name'] ?? '');
        if (!$jobId || $name === '') { jout(false, ['error' => 'Thiếu thông tin']); }
        $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $src = (int)($_POST['source_id'] ?? 0);
        $st = $conn->prepare('INSERT INTO hrm_candidates (full_name,email,phone,source_id,created_by) VALUES (?,?,?,?,?)');
        $st->bind_param('sssii', $name, $email, $phone, $src, $uid);
        $st->execute(); $cid = $st->insert_id;

        $stage = $conn->query("SELECT id,sla_hours FROM hrm_pipeline_stages WHERE code='SCREENING'")->fetch_assoc();
        $sid = (int)($stage['id'] ?? 0);
        $st2 = $conn->prepare('INSERT INTO hrm_applications (candidate_id,job_id,stage_id,owner_id) VALUES (?,?,?,?)');
        $st2->bind_param('iiii', $cid, $jobId, $sid, $uid);
        $st2->execute(); $aid = $st2->insert_id;

        if (!empty($stage['sla_hours'])) {
            hrm_sla_open($conn, 'application', $aid, 'screening', date('Y-m-d H:i:s', strtotime('+' . (int)$stage['sla_hours'] . ' hours')));
        }
        $job = $conn->query('SELECT title FROM hrm_jobs WHERE id=' . $jobId)->fetch_assoc();
        if ($email) { hrm_send_email($conn, 'cv_received', $email, ['candidate_name' => $name, 'job_title' => $job['title'] ?? ''], 'application', $aid); }
        hrm_audit($conn, $uid, 'candidate_add', 'application', $aid, $name);
        jout(true, ['id' => $aid]);
    }

    case 'move_stage': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $sid = (int)($_POST['stage_id'] ?? 0);
        if ($aid <= 0 || $sid <= 0) { jout(false, ['error' => 'Thiếu ứng viên hoặc giai đoạn']); }
        if (!$conn->query('SELECT id FROM hrm_applications WHERE id=' . $aid)->fetch_assoc()) { jout(false, ['error' => 'Không tìm thấy ứng viên']); }
        $stage = $conn->query('SELECT code,name,sla_hours,stage_type FROM hrm_pipeline_stages WHERE id=' . $sid)->fetch_assoc();
        if (!$stage) { jout(false, ['error' => 'Không tìm thấy giai đoạn']); }
        // Status theo loại cột đích: cột Loại -> rejected, cột Tuyển -> hired, còn lại -> active
        // (cho phép kéo cả ứng viên đã loại/giữ/rút và tự mở lại).
        $newStatus = $stage['stage_type'] === 'rejected' ? 'rejected'
                   : ($stage['stage_type'] === 'hired' ? 'hired' : 'active');
        $st = $conn->prepare('UPDATE hrm_applications SET stage_id=?, status=? WHERE id=?');
        $st->bind_param('isi', $sid, $newStatus, $aid);
        $st->execute();
        if (!empty($stage['sla_hours'])) {
            hrm_sla_open($conn, 'application', $aid, strtolower($stage['code']), date('Y-m-d H:i:s', strtotime('+' . (int)$stage['sla_hours'] . ' hours')));
        }
        hrm_audit($conn, $uid, 'stage_move', 'application', $aid, $stage['name'] ?? '');
        jout(true);
    }

    /* ── TA Review (BƯỚC 4: SCREENING) ────────────────────────────────── */
    case 'ta_review_get': {
        $aid = (int)($_POST['application_id'] ?? $_GET['application_id'] ?? 0);
        if (!$aid) { jout(false, ['error' => 'Thiếu ứng viên']); }
        hrm_ensure_screening_table($conn);
        $st = $conn->prepare('SELECT r.*, u.full_name AS reviewer FROM hrm_screening_reviews r LEFT JOIN users u ON u.id=r.reviewed_by WHERE r.application_id=?');
        $st->bind_param('i', $aid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();

        // Thông tin ứng viên + lịch sử ứng tuyển (kiểm tra trùng / đã apply job nào).
        $cand = null; $history = [];
        $ast = $conn->prepare('SELECT c.id, c.full_name, c.email, c.phone, c.cv_path FROM hrm_applications a JOIN hrm_candidates c ON c.id=a.candidate_id WHERE a.id=?');
        $ast->bind_param('i', $aid);
        $ast->execute();
        $cand = $ast->get_result()->fetch_assoc();
        if ($cand) {
            // Gom các bản ghi ứng viên cùng người (trùng email/SĐT) để bắt trường hợp apply lại bằng hồ sơ khác.
            $ids = [(int)$cand['id']];
            $email = trim($cand['email'] ?? ''); $phone = trim($cand['phone'] ?? '');
            if ($email !== '' || $phone !== '') {
                $ds = $conn->prepare('SELECT id FROM hrm_candidates WHERE (email<>"" AND email=?) OR (phone<>"" AND phone=?)');
                $ds->bind_param('ss', $email, $phone);
                $ds->execute();
                $dr = $ds->get_result();
                while ($x = $dr->fetch_assoc()) { $ids[] = (int)$x['id']; }
            }
            $ids = array_values(array_unique($ids));
            $in = implode(',', array_map('intval', $ids));
            $hr = $conn->query("SELECT a.id, a.job_id, a.status, a.applied_at, j.title AS job_title,
                    ps.name AS stage_name, c.cv_path
                FROM hrm_applications a
                JOIN hrm_jobs j ON j.id=a.job_id
                LEFT JOIN hrm_pipeline_stages ps ON ps.id=a.stage_id
                JOIN hrm_candidates c ON c.id=a.candidate_id
                WHERE a.candidate_id IN ($in)
                ORDER BY a.applied_at DESC");
            while ($x = $hr->fetch_assoc()) {
                $x['is_current'] = ((int)$x['id'] === $aid);
                $history[] = $x;
            }
        }
        jout(true, ['review' => $row ?: null, 'candidate' => $cand, 'history' => $history]);
    }

    case 'ta_review_save': {
        $aid = (int)($_POST['application_id'] ?? 0);
        if (!$aid) { jout(false, ['error' => 'Thiếu ứng viên']); }
        hrm_ensure_screening_table($conn);
        // Field rich text: chỉ giữ thẻ định dạng cơ bản, loại bỏ script/handler.
        $rt = fn($s) => trim(strip_tags($s ?? '', '<b><strong><i><em><u><ul><ol><li><br><p><div><span>'));
        $background = $rt($_POST['background'] ?? '');
        $experience = $rt($_POST['experience'] ?? '');
        $salary     = trim($_POST['salary'] ?? '');
        $orientation= $rt($_POST['orientation'] ?? '');
        $notice     = trim($_POST['notice_period'] ?? '');
        $languages  = trim($_POST['languages'] ?? '');
        $reference  = $rt($_POST['reference_check'] ?? '');
        $note       = $rt($_POST['note'] ?? '');
        $result     = $_POST['result'] ?? '';
        $allowed    = ['', 'reject', 'hold', 'send_hm', 'interview'];
        if (!in_array($result, $allowed, true)) { $result = ''; }

        $st = $conn->prepare('INSERT INTO hrm_screening_reviews
            (application_id,background,experience,salary,orientation,notice_period,languages,reference_check,result,note,reviewed_by,reviewed_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE
            background=VALUES(background),experience=VALUES(experience),salary=VALUES(salary),
            orientation=VALUES(orientation),notice_period=VALUES(notice_period),languages=VALUES(languages),
            reference_check=VALUES(reference_check),result=VALUES(result),note=VALUES(note),
            reviewed_by=VALUES(reviewed_by),reviewed_at=NOW()');
        $st->bind_param('isssssssssi', $aid, $background, $experience, $salary, $orientation, $notice, $languages, $reference, $result, $note, $uid);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }

        // Side-effect theo kết quả TA Review.
        if ($result === 'reject') {
            $plainNote = trim(strip_tags($note));
            $rj = $conn->prepare('UPDATE hrm_applications SET status="rejected", reject_reason=? WHERE id=?');
            $rj->bind_param('si', $plainNote, $aid); $rj->execute();
        } elseif ($result === 'hold') {
            $conn->query("UPDATE hrm_applications SET status='hold' WHERE id=$aid");
        } elseif ($result === 'interview') {
            $stage = $conn->query("SELECT id,code,sla_hours FROM hrm_pipeline_stages WHERE code='INTERVIEW'")->fetch_assoc();
            if ($stage) {
                $sid = (int)$stage['id'];
                $conn->query("UPDATE hrm_applications SET stage_id=$sid, status='active' WHERE id=$aid");
                if (!empty($stage['sla_hours'])) {
                    hrm_sla_open($conn, 'application', $aid, strtolower($stage['code']), date('Y-m-d H:i:s', strtotime('+' . (int)$stage['sla_hours'] . ' hours')));
                }
            }
        }
        // send_hm và '' (lưu nháp): giữ nguyên ở Screening.

        hrm_audit($conn, $uid, 'ta_review', 'application', $aid, $result);
        jout(true, ['result' => $result]);
    }

    case 'set_application_owner': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $sid = (int)($_POST['stage_id'] ?? 0);
        $owner = (int)($_POST['user_id'] ?? 0);
        if (!$aid || !$sid) { jout(false, ['error' => 'Thiếu dữ liệu']); }
        if ($owner > 0) {
            $st = $conn->prepare('INSERT INTO hrm_application_assignees (application_id,stage_id,user_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)');
            $st->bind_param('iii', $aid, $sid, $owner);
            $st->execute();
            $conn->query("UPDATE hrm_applications SET owner_id=$owner WHERE id=$aid"); // keep current handler in sync
        } else {
            $st = $conn->prepare('DELETE FROM hrm_application_assignees WHERE application_id=? AND stage_id=?');
            $st->bind_param('ii', $aid, $sid);
            $st->execute();
        }
        hrm_audit($conn, $uid, 'application_assign', 'application', $aid, "stage=$sid owner=$owner");
        jout(true);
    }

    case 'rate_application': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $rating = max(0, min(5, (int)($_POST['rating'] ?? 0)));
        $st = $conn->prepare('UPDATE hrm_applications SET rating = ? WHERE id = ?');
        $st->bind_param('ii', $rating, $aid);
        $st->execute();
        jout(true);
    }

    case 'reject_application': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $rej = $conn->query("SELECT id FROM hrm_pipeline_stages WHERE code='REJECTED'")->fetch_assoc();
        $st = $conn->prepare('UPDATE hrm_applications SET status="rejected", reject_reason=?, stage_id=? WHERE id=?');
        $rid = (int)($rej['id'] ?? 0);
        $st->bind_param('sii', $reason, $rid, $aid);
        $st->execute();
        // Không tự gửi email cho ứng viên ở đây - email từ chối gửi thủ công qua nút "Gửi email cho ứng viên".
        hrm_audit($conn, $uid, 'application_reject', 'application', $aid, $reason);
        jout(true);
    }

    case 'hire_application': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $hired = $conn->query("SELECT id FROM hrm_pipeline_stages WHERE code='HIRED'")->fetch_assoc();
        $hid = (int)($hired['id'] ?? 0);
        $conn->query("UPDATE hrm_applications SET status='hired', stage_id=$hid WHERE id=$aid");
        hrm_audit($conn, $uid, 'application_hire', 'application', $aid, '');
        jout(true);
    }

    /* ── Gửi email thủ công cho ứng viên (chọn template, không auto) ──── */
    case 'send_candidate_email': {
        $aid  = (int)($_POST['application_id'] ?? 0);
        $ekey = trim($_POST['event_key'] ?? '');
        if (!$aid || $ekey === '') { jout(false, ['error' => 'Thiếu dữ liệu']); }
        $row = $conn->query("SELECT c.email FROM hrm_applications a JOIN hrm_candidates c ON c.id=a.candidate_id WHERE a.id=$aid")->fetch_assoc();
        if (empty($row['email'])) { jout(false, ['error' => 'Ứng viên chưa có email']); }
        $ok = hrm_send_email($conn, $ekey, $row['email'], [], 'application', $aid);
        if (!$ok) { jout(false, ['error' => 'Gửi thất bại: ' . (Mailer::$lastError ?: 'kiểm tra template đang Bật và sender đã cấu hình')]); }
        hrm_audit($conn, $uid, 'send_candidate_email', 'application', $aid, $ekey);
        jout(true, ['to' => $row['email']]);
    }

    /* ── Sửa thông tin ứng viên (ghi audit thay đổi) ─────────────────── */
    case 'update_candidate': {
        hrm_ensure_candidate_module($conn);
        $cid = (int)($_POST['candidate_id'] ?? 0);
        if (!$cid) { jout(false, ['error' => 'Thiếu ứng viên']); }
        $old = $conn->query("SELECT * FROM hrm_candidates WHERE id=$cid")->fetch_assoc();
        if (!$old) { jout(false, ['error' => 'Không tìm thấy ứng viên']); }
        $name = trim($_POST['full_name'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu họ tên']); }
        $f = fn($k) => trim($_POST[$k] ?? '');
        $email = $f('email'); $phone = $f('phone');
        $statuses = hrm_candidate_statuses();
        $status = isset($statuses[$_POST['status'] ?? '']) ? $_POST['status'] : ($old['status'] ?: 'new');
        $dedup = hrm_candidate_dedup_key($email, $phone);

        $st = $conn->prepare('UPDATE hrm_candidates SET full_name=?, email=?, phone=?, current_position=?, gender=?, dob=?,
            score=?, source_id=?, status=?, owner_id=?, rating=?, linkedin_url=?, portfolio_url=?, location=?, years_exp=?,
            event_id=?, expected_salary=?, notice_period=?, languages=?, classification=?, notes=?, dedup_key=? WHERE id=?');
        $score = (float)($_POST['score'] ?? 0); $src = (int)($_POST['source_id'] ?? 0);
        $owner = (int)($_POST['owner_id'] ?? 0); $rating = (int)($_POST['rating'] ?? 0);
        $years = (float)($_POST['years_exp'] ?? 0); $event = (int)($_POST['event_id'] ?? 0);
        $pos=$f('current_position'); $gender=$f('gender'); $dob=$f('dob'); $li=$f('linkedin_url');
        $pf=$f('portfolio_url'); $loc=$f('location'); $exp=$f('expected_salary'); $np=$f('notice_period');
        $lang=$f('languages'); $cls=$f('classification'); $notes=$f('notes');
        $types = 'ssssss' . 'di' . 's' . 'ii' . 'sss' . 'd' . 'i' . 'sssss' . 's' . 'i';
        $st->bind_param($types, $name,$email,$phone,$pos,$gender,$dob, $score,$src, $status, $owner,$rating,
            $li,$pf,$loc, $years, $event, $exp,$np,$lang,$cls,$notes, $dedup, $cid);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }

        $changes = [];
        foreach (['full_name'=>'Họ tên','email'=>'Email','phone'=>'SĐT','status'=>'Trạng thái'] as $k=>$lbl) {
            $nv = $k==='status' ? $status : ($k==='full_name'?$name:($k==='email'?$email:$phone));
            if ((string)($old[$k] ?? '') !== (string)$nv) { $changes[] = $lbl; }
        }
        if ($changes) {
            hrm_audit($conn, $uid, 'candidate_update', 'candidate', $cid, implode(', ', $changes));
            hrm_cand_activity($conn, $cid, 'update', 'Cập nhật: ' . implode(', ', $changes), $uid);
        }
        jout(true);
    }

    /* ════════════════ Candidate / Talent module (P1) ══════════════════ */
    case 'cand_create': {
        hrm_ensure_candidate_module($conn);
        $name = trim($_POST['full_name'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu họ tên']); }
        $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        $src = (int)($_POST['source_id'] ?? 0); $event = (int)($_POST['event_id'] ?? 0);
        $pos = trim($_POST['current_position'] ?? '');
        $dedup = hrm_candidate_dedup_key($email, $phone);
        // Chống trùng: nếu trùng email/sđt -> báo về để người dùng quyết định.
        if ($dedup !== '' && empty($_POST['force'])) {
            $d = $conn->prepare('SELECT id, full_name FROM hrm_candidates WHERE dedup_key=? LIMIT 1');
            $d->bind_param('s', $dedup); $d->execute();
            if ($dup = $d->get_result()->fetch_assoc()) {
                jout(false, ['error' => 'Trùng với ứng viên "' . $dup['full_name'] . '". Mở hồ sơ đó hoặc bấm lại để vẫn tạo.', 'dup_id' => (int)$dup['id']]);
            }
        }
        $st = $conn->prepare("INSERT INTO hrm_candidates (full_name,email,phone,source_id,event_id,current_position,status,owner_id,dedup_key,created_by,last_activity_at) VALUES (?,?,?,?,?,?, 'new', ?, ?, ?, NOW())");
        $st->bind_param('sssiisiii', $name,$email,$phone,$src,$event,$pos,$uid,$dedup,$uid);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        $cid = $st->insert_id;
        hrm_cand_activity($conn, $cid, 'create', 'Tạo hồ sơ ứng viên', $uid);
        hrm_audit($conn, $uid, 'candidate_create', 'candidate', $cid, $name);
        jout(true, ['id' => $cid]);
    }

    case 'cand_activity_add': {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['note','call','email','meeting'], true) ? $_POST['type'] : 'note';
        if (!$cid || $body === '') { jout(false, ['error' => 'Thiếu nội dung']); }
        hrm_cand_activity($conn, $cid, $type, $body, $uid);
        jout(true);
    }

    case 'cand_reminder_add': {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        $due = trim($_POST['due_at'] ?? ''); $note = trim($_POST['note'] ?? '');
        if (!$cid || $due === '') { jout(false, ['error' => 'Thiếu thời gian']); }
        $owner = (int)($_POST['owner_id'] ?? $uid) ?: $uid;
        $st = $conn->prepare('INSERT INTO hrm_candidate_reminders (candidate_id,due_at,note,owner_id,created_by) VALUES (?,?,?,?,?)');
        $st->bind_param('issii', $cid, $due, $note, $owner, $uid);
        $st->execute();
        hrm_cand_activity($conn, $cid, 'note', 'Đặt nhắc việc: ' . ($note ?: $due), $uid);
        jout(true);
    }
    case 'cand_reminder_done': {
        $rid = (int)($_POST['reminder_id'] ?? 0);
        $conn->query('UPDATE hrm_candidate_reminders SET done=1 WHERE id=' . $rid);
        jout(true);
    }

    case 'cand_skill_add': {
        $cid = (int)($_POST['candidate_id'] ?? 0); $skill = trim($_POST['skill'] ?? '');
        $level = trim($_POST['level'] ?? '');
        if (!$cid || $skill === '') { jout(false, ['error' => 'Thiếu kỹ năng']); }
        $st = $conn->prepare('INSERT INTO hrm_candidate_skills (candidate_id,skill,level) VALUES (?,?,?)');
        $st->bind_param('iss', $cid, $skill, $level); $st->execute();
        jout(true, ['id' => $st->insert_id]);
    }
    case 'cand_skill_del': { $conn->query('DELETE FROM hrm_candidate_skills WHERE id=' . (int)($_POST['id'] ?? 0)); jout(true); }

    case 'cand_exp_add': {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        if (!$cid) { jout(false, ['error' => 'Thiếu ứng viên']); }
        $co=trim($_POST['company']??''); $ti=trim($_POST['title']??''); $pe=trim($_POST['period']??''); $sm=trim($_POST['summary']??'');
        $st = $conn->prepare('INSERT INTO hrm_candidate_experience (candidate_id,company,title,period,summary) VALUES (?,?,?,?,?)');
        $st->bind_param('issss', $cid,$co,$ti,$pe,$sm); $st->execute();
        jout(true, ['id' => $st->insert_id]);
    }
    case 'cand_exp_del': { $conn->query('DELETE FROM hrm_candidate_experience WHERE id=' . (int)($_POST['id'] ?? 0)); jout(true); }

    case 'cand_edu_add': {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        if (!$cid) { jout(false, ['error' => 'Thiếu ứng viên']); }
        $sc=trim($_POST['school']??''); $dg=trim($_POST['degree']??''); $mj=trim($_POST['major']??''); $yr=trim($_POST['grad_year']??'');
        $st = $conn->prepare('INSERT INTO hrm_candidate_education (candidate_id,school,degree,major,grad_year) VALUES (?,?,?,?,?)');
        $st->bind_param('issss', $cid,$sc,$dg,$mj,$yr); $st->execute();
        jout(true, ['id' => $st->insert_id]);
    }
    case 'cand_edu_del': { $conn->query('DELETE FROM hrm_candidate_education WHERE id=' . (int)($_POST['id'] ?? 0)); jout(true); }

    case 'cand_tag_add': {
        $cid = (int)($_POST['candidate_id'] ?? 0); $tag = trim($_POST['tag'] ?? '');
        if (!$cid || $tag === '') { jout(false, ['error' => 'Thiếu thẻ']); }
        $st = $conn->prepare('INSERT IGNORE INTO hrm_candidate_tags (candidate_id,tag) VALUES (?,?)');
        $st->bind_param('is', $cid, $tag); $st->execute();
        jout(true);
    }
    case 'cand_tag_del': {
        $cid = (int)($_POST['candidate_id'] ?? 0); $tag = trim($_POST['tag'] ?? '');
        $st = $conn->prepare('DELETE FROM hrm_candidate_tags WHERE candidate_id=? AND tag=?');
        $st->bind_param('is', $cid, $tag); $st->execute();
        jout(true);
    }

    case 'cand_attach_add': {
        hrm_ensure_candidate_module($conn);
        $cid = (int)($_POST['candidate_id'] ?? 0);
        if (!$cid) { jout(false, ['error' => 'Thiếu ứng viên']); }
        [$path, $err] = hrm_save_upload('file', 'attachments');
        if (!$path) { jout(false, ['error' => $err]); }
        $label = trim($_POST['label'] ?? '') ?: basename($path);
        $type = in_array($_POST['type'] ?? '', ['cv','cert','portfolio','other'], true) ? $_POST['type'] : 'cv';
        $st = $conn->prepare('INSERT INTO hrm_candidate_attachments (candidate_id,file_path,label,type,uploaded_by) VALUES (?,?,?,?,?)');
        $st->bind_param('isssi', $cid,$path,$label,$type,$uid); $st->execute();
        // Nếu chưa có CV chính, đặt file CV đầu tiên làm cv_path.
        if ($type === 'cv') {
            $cur = $conn->query("SELECT cv_path FROM hrm_candidates WHERE id=$cid")->fetch_assoc();
            if (empty($cur['cv_path'])) {
                $u = $conn->prepare('UPDATE hrm_candidates SET cv_path=? WHERE id=?'); $u->bind_param('si', $path, $cid); $u->execute();
            }
        }
        hrm_cand_activity($conn, $cid, 'note', 'Tải lên tệp: ' . $label, $uid);
        jout(true, ['id' => $st->insert_id, 'path' => $path]);
    }
    case 'cand_attach_del': { $conn->query('DELETE FROM hrm_candidate_attachments WHERE id=' . (int)($_POST['id'] ?? 0)); jout(true); }

    case 'cand_bulk': {
        hrm_ensure_candidate_module($conn);
        $ids = array_values(array_filter(array_map('intval', explode(',', $_POST['ids'] ?? ''))));
        $op  = $_POST['op'] ?? '';
        if (!$ids) { jout(false, ['error' => 'Chưa chọn ứng viên']); }
        $in = implode(',', $ids);
        if ($op === 'tag') {
            $tag = trim($_POST['value'] ?? ''); if ($tag === '') { jout(false, ['error' => 'Thiếu thẻ']); }
            $stmt = $conn->prepare('INSERT IGNORE INTO hrm_candidate_tags (candidate_id,tag) VALUES (?,?)');
            foreach ($ids as $cid) { $stmt->bind_param('is', $cid, $tag); $stmt->execute(); }
        } elseif ($op === 'status') {
            $v = $_POST['value'] ?? ''; if (!isset(hrm_candidate_statuses()[$v])) { jout(false, ['error' => 'Trạng thái không hợp lệ']); }
            $conn->query("UPDATE hrm_candidates SET status='" . $conn->real_escape_string($v) . "' WHERE id IN ($in)");
        } elseif ($op === 'pool') {
            $conn->query("UPDATE hrm_candidates SET talent_pool=1, status=IF(status='new','pooled',status) WHERE id IN ($in)");
        } elseif ($op === 'owner') {
            $v = (int)($_POST['value'] ?? 0);
            $conn->query("UPDATE hrm_candidates SET owner_id=$v WHERE id IN ($in)");
        } elseif ($op === 'delete') {
            $conn->query("UPDATE hrm_candidates SET status='archived' WHERE id IN ($in)");
        } else { jout(false, ['error' => 'Thao tác không hợp lệ']); }
        hrm_audit($conn, $uid, 'candidate_bulk', 'candidate', 0, $op . ' x' . count($ids));
        jout(true, ['n' => count($ids)]);
    }

    /* ── Sự kiện tuyển dụng (nguồn ứng viên ngoài tin tuyển dụng) ───────── */
    case 'event_save': {
        hrm_ensure_candidate_module($conn);
        $eid  = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu tên sự kiện']); }
        $type = trim($_POST['type'] ?? '');
        $date = ($_POST['event_date'] ?? '') ?: null;
        $loc  = trim($_POST['location'] ?? '');
        if ($eid > 0) {
            $st = $conn->prepare('UPDATE hrm_events SET name=?, type=?, event_date=?, location=? WHERE id=?');
            $st->bind_param('ssssi', $name, $type, $date, $loc, $eid);
            $st->execute();
        } else {
            $st = $conn->prepare('INSERT INTO hrm_events (name,type,event_date,location) VALUES (?,?,?,?)');
            $st->bind_param('ssss', $name, $type, $date, $loc);
            $st->execute(); $eid = $st->insert_id;
        }
        hrm_audit($conn, $uid, 'event_save', 'event', $eid, $name);
        jout(true, ['id' => $eid]);
    }
    case 'event_del': {
        $eid = (int)($_POST['id'] ?? 0);
        $n = (int)($conn->query("SELECT COUNT(*) c FROM hrm_candidates WHERE event_id=$eid")->fetch_assoc()['c'] ?? 0);
        if ($n > 0) { $conn->query("UPDATE hrm_events SET active=0 WHERE id=$eid"); } // còn ứng viên -> ẩn
        else        { $conn->query("DELETE FROM hrm_events WHERE id=$eid"); }
        jout(true);
    }

    /* ── Nguồn ứng viên ─────────────────────────────────────────────────── */
    case 'source_save': {
        $sid = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? '');
        if ($name === '') { jout(false, ['error' => 'Thiếu tên nguồn']); }
        $active = isset($_POST['active']) ? (int)!!$_POST['active'] : 1;
        if ($sid > 0) { $st = $conn->prepare('UPDATE hrm_candidate_sources SET name=?, active=? WHERE id=?'); $st->bind_param('sii', $name, $active, $sid); $st->execute(); }
        else { $st = $conn->prepare('INSERT INTO hrm_candidate_sources (name,active) VALUES (?,1)'); $st->bind_param('s', $name); $st->execute(); $sid = $st->insert_id; }
        jout(true, ['id' => $sid]);
    }
    case 'source_del': {
        $sid = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE hrm_candidate_sources SET active=0 WHERE id=$sid"); // ẩn, giữ liên kết ứng viên
        jout(true);
    }

    /* ── Import wizard: bước 1 đọc file -> trả headers + rows ───────────── */
    case 'cand_import_parse': {
        if (empty($_FILES['file']['tmp_name'])) { jout(false, ['error' => 'Chưa chọn file']); }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $rows = [];
        try {
            if ($ext === 'csv') {
                $fh = fopen($_FILES['file']['tmp_name'], 'r');
                while (($r = fgetcsv($fh)) !== false) { $rows[] = $r; }
                fclose($fh);
            } else {
                require_once __DIR__ . '/lib/xlsx.php';
                $rows = hrm_xlsx_rows($_FILES['file']['tmp_name']);
            }
        } catch (Throwable $e) { jout(false, ['error' => 'Lỗi đọc file: ' . $e->getMessage()]); }
        // Dòng đầu không rỗng = header.
        $hdrIdx = -1;
        foreach ($rows as $i => $r) { if (count(array_filter($r, fn($x) => trim((string)$x) !== '')) >= 2) { $hdrIdx = $i; break; } }
        if ($hdrIdx < 0) { jout(false, ['error' => 'File rỗng hoặc không đọc được']); }
        $headers = array_map(fn($x) => trim((string)$x), $rows[$hdrIdx]);
        $data = array_slice($rows, $hdrIdx + 1, 2000);
        jout(true, ['headers' => $headers, 'rows' => $data, 'total' => count($data)]);
    }

    /* ── Import wizard: bước 2 ghi dữ liệu theo mapping + chống trùng ───── */
    case 'cand_import_commit': {
        hrm_ensure_candidate_module($conn);
        $map  = json_decode($_POST['map'] ?? '[]', true) ?: [];   // field => colIndex
        $rows = json_decode($_POST['rows'] ?? '[]', true) ?: [];
        $mode = in_array($_POST['mode'] ?? '', ['skip','update','create'], true) ? $_POST['mode'] : 'skip';
        $defSource = (int)($_POST['default_source'] ?? 0);
        $defEvent  = (int)($_POST['default_event'] ?? 0);
        $downloadCv = !empty($_POST['download_cv']);
        if (!$rows) { jout(false, ['error' => 'Không có dòng dữ liệu']); }
        if (empty($map['full_name']) && $map['full_name'] !== 0 && $map['full_name'] !== '0') { jout(false, ['error' => 'Phải gán cột Họ tên']); }

        $col = function ($row, $field) use ($map) {
            if (!isset($map[$field]) || $map[$field] === '') return '';
            $i = (int)$map[$field];
            return trim((string)($row[$i] ?? ''));
        };
        // dd/mm/yyyy -> Y-m-d (Base export); rỗng / 0/0/0 -> null.
        $toDate = function ($v) {
            $v = trim($v);
            if ($v === '' || strpos($v, '0/0/0') !== false) return null;
            $d = DateTime::createFromFormat('d/m/Y', $v);
            return $d ? $d->format('Y-m-d') : null;
        };
        // Cột chuỗi map trực tiếp (key wizard == tên cột DB).
        $strCols = ['current_position','location','expected_salary','languages','dob','gender','linkedin_url',
                    'notes','classification','campaign','id_card','applied_job','applied_stage','tags',
                    'office_text','reject_reason','cv_path','external_id','interview_date'];

        $ins = $upd = $skip = $cvOk = $cvFail = 0;
        foreach ($rows as $row) {
            $name = $col($row, 'full_name');
            if ($name === '') { $skip++; continue; }
            $email = $col($row, 'email'); $phone = $col($row, 'phone');
            $dedup = hrm_candidate_dedup_key($email, $phone);
            $years = (float)$col($row, 'years_exp');
            $score = (float)str_replace(',', '.', $col($row, 'score'));
            $appliedDate = $toDate($col($row, 'applied_date'));

            // Dựng cặp cột=>giá trị động (chỉ field được map & có dữ liệu).
            $set = []; // col => [value, type]
            foreach ($strCols as $cn) { $v = $col($row, $cn); if ($v !== '') { $set[$cn] = [$v, 's']; } }
            if ($years > 0) { $set['years_exp'] = [$years, 'd']; }
            if ($score > 0) { $set['score'] = [$score, 'd']; }
            if ($appliedDate) { $set['applied_date'] = [$appliedDate, 's']; }
            if ($defSource) { $set['source_id'] = [$defSource, 'i']; }
            if ($defEvent)  { $set['event_id']  = [$defEvent, 'i']; }

            $existing = null;
            if ($dedup !== '') {
                $d = $conn->prepare('SELECT id FROM hrm_candidates WHERE dedup_key=? LIMIT 1');
                $d->bind_param('s', $dedup); $d->execute(); $existing = $d->get_result()->fetch_assoc();
            }
            if ($existing && $mode === 'skip') {
                $skip++;
                // Vẫn BỔ SUNG CV cho hồ sơ cũ nếu nó chưa có CV (không đụng field khác).
                if ($downloadCv && isset($set['cv_path']) && preg_match('#^https?://#i', $set['cv_path'][0])) {
                    $cid0 = (int)$existing['id'];
                    $cur = $conn->query("SELECT cv_path FROM hrm_candidates WHERE id=$cid0")->fetch_assoc();
                    // Bổ sung khi chưa có CV, hoặc CV đang là link ngoài (chưa localize).
                    if (empty($cur['cv_path']) || preg_match('#^https?://#i', $cur['cv_path'])) {
                        $local = hrm_download_cv($set['cv_path'][0], $name);
                        if ($local !== '') {
                            $u = $conn->prepare('UPDATE hrm_candidates SET cv_path=? WHERE id=?'); $u->bind_param('si', $local, $cid0); $u->execute();
                            $lbl = 'CV (import)';
                            $q = $conn->prepare("INSERT INTO hrm_candidate_attachments (candidate_id,file_path,label,type,uploaded_by) VALUES (?,?,?, 'cv', ?)");
                            $q->bind_param('issi', $cid0, $local, $lbl, $uid); $q->execute();
                            $cvOk++;
                        } else { $cvFail++; }
                    }
                }
                continue;
            }

            // Tải CV từ link ngoài về server (chỉ cho dòng sẽ ghi) -> thay cv_path bằng đường dẫn nội bộ.
            $cvLocal = '';
            if ($downloadCv && isset($set['cv_path']) && preg_match('#^https?://#i', $set['cv_path'][0])) {
                $local = hrm_download_cv($set['cv_path'][0], $name);
                if ($local !== '') { $set['cv_path'] = [$local, 's']; $cvLocal = $local; $cvOk++; }
                else { $cvFail++; }
            }
            if ($existing && $mode === 'update') {
                $cid = (int)$existing['id'];
                $sets = ['full_name=?']; $vals = [$name]; $types = 's';
                foreach ($set as $cn => [$v, $t]) { $sets[] = "$cn=?"; $vals[] = $v; $types .= $t; }
                $vals[] = $cid; $types .= 'i';
                $st = $conn->prepare('UPDATE hrm_candidates SET ' . implode(',', $sets) . ' WHERE id=?');
                $st->bind_param($types, ...$vals); $st->execute(); $upd++;
            } else {
                $cols = ['full_name', 'email', 'phone']; $vals = [$name, $email, $phone]; $types = 'sss';
                foreach ($set as $cn => [$v, $t]) { if (in_array($cn, ['email','phone'], true)) continue; $cols[] = $cn; $vals[] = $v; $types .= $t; }
                $cols[] = 'status';    $vals[] = 'new';   $types .= 's';
                $cols[] = 'dedup_key'; $vals[] = $dedup;  $types .= 's';
                $cols[] = 'created_by';$vals[] = $uid;    $types .= 'i';
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $st = $conn->prepare('INSERT INTO hrm_candidates (' . implode(',', $cols) . ', last_activity_at) VALUES (' . $ph . ', NOW())');
                $st->bind_param($types, ...$vals); $st->execute(); $cid = $st->insert_id; $ins++;
            }
            // CV tải về -> lưu thành 1 tệp đính kèm.
            if ($cvLocal !== '' && !empty($cid)) {
                $lbl = 'CV (import)';
                $q = $conn->prepare("INSERT INTO hrm_candidate_attachments (candidate_id,file_path,label,type,uploaded_by) VALUES (?,?,?, 'cv', ?)");
                $q->bind_param('issi', $cid, $cvLocal, $lbl, $uid); $q->execute();
            }
            // Kỹ năng (tách ; hoặc ,).
            $sk = $col($row, 'skills');
            if ($sk !== '' && !empty($cid)) {
                foreach (preg_split('/[;,]/', $sk) as $s) { $s = trim($s); if ($s !== '') { $q = $conn->prepare('INSERT INTO hrm_candidate_skills (candidate_id,skill) VALUES (?,?)'); $q->bind_param('is', $cid, $s); $q->execute(); } }
            }
        }
        hrm_audit($conn, $uid, 'candidate_import', 'candidate', 0, "ins=$ins upd=$upd skip=$skip cv=$cvOk/" . ($cvOk + $cvFail));
        jout(true, ['inserted' => $ins, 'updated' => $upd, 'skipped' => $skip, 'cv_ok' => $cvOk, 'cv_fail' => $cvFail]);
    }

    /* ── Gộp 2 hồ sơ trùng ──────────────────────────────────────────────── */
    case 'cand_merge': {
        hrm_ensure_candidate_module($conn);
        $keep = (int)($_POST['kept_id'] ?? 0);
        $drop = (int)($_POST['merged_id'] ?? 0);
        if (!$keep || !$drop || $keep === $drop) { jout(false, ['error' => 'Chọn 2 hồ sơ khác nhau']); }
        $a = $conn->query("SELECT * FROM hrm_candidates WHERE id=$keep")->fetch_assoc();
        $b = $conn->query("SELECT * FROM hrm_candidates WHERE id=$drop")->fetch_assoc();
        if (!$a || !$b) { jout(false, ['error' => 'Không tìm thấy hồ sơ']); }

        // Cập nhật giá trị field đã chọn lên hồ sơ giữ lại.
        $allow = ['full_name','email','phone','current_position','location','expected_salary','languages',
                  'dob','gender','linkedin_url','portfolio_url','notes','source_id','event_id','owner_id','years_exp'];
        $sets = []; $vals = []; $types = '';
        foreach ($allow as $fld) {
            if (!array_key_exists($fld, $_POST)) { continue; }
            $sets[] = "$fld=?"; $vals[] = $_POST[$fld];
            $types .= in_array($fld, ['source_id','event_id','owner_id'], true) ? 'i' : (in_array($fld, ['years_exp'], true) ? 'd' : 's');
        }
        // dedup_key tính lại theo email/phone cuối cùng.
        $finEmail = array_key_exists('email', $_POST) ? $_POST['email'] : $a['email'];
        $finPhone = array_key_exists('phone', $_POST) ? $_POST['phone'] : $a['phone'];
        $sets[] = 'dedup_key=?'; $vals[] = hrm_candidate_dedup_key($finEmail, $finPhone); $types .= 's';
        $vals[] = $keep; $types .= 'i';
        $st = $conn->prepare('UPDATE hrm_candidates SET ' . implode(',', $sets) . ' WHERE id=?');
        $st->bind_param($types, ...$vals); $st->execute();

        // Chuyển dữ liệu con sang hồ sơ giữ.
        $conn->query("UPDATE hrm_applications SET candidate_id=$keep WHERE candidate_id=$drop");
        foreach (['hrm_candidate_skills','hrm_candidate_experience','hrm_candidate_education',
                  'hrm_candidate_attachments','hrm_candidate_activities','hrm_candidate_reminders'] as $t) {
            $conn->query("UPDATE $t SET candidate_id=$keep WHERE candidate_id=$drop");
        }
        $conn->query("UPDATE IGNORE hrm_candidate_tags SET candidate_id=$keep WHERE candidate_id=$drop");
        $conn->query("DELETE FROM hrm_candidate_tags WHERE candidate_id=$drop");
        // Lấy CV chính nếu hồ sơ giữ chưa có.
        if (empty($a['cv_path']) && !empty($b['cv_path'])) {
            $u = $conn->prepare('UPDATE hrm_candidates SET cv_path=? WHERE id=?'); $u->bind_param('si', $b['cv_path'], $keep); $u->execute();
        }
        // Lưu hồ sơ bị gộp (archived) + log.
        $conn->query("UPDATE hrm_candidates SET status='archived', dedup_key='', notes=CONCAT(COALESCE(notes,''),'\n[Đã gộp vào #$keep]') WHERE id=$drop");
        $payload = json_encode(['kept' => $a, 'merged' => $b], JSON_UNESCAPED_UNICODE);
        $lg = $conn->prepare('INSERT INTO hrm_candidate_merges (kept_id,merged_id,payload,actor_id) VALUES (?,?,?,?)');
        $lg->bind_param('iisi', $keep, $drop, $payload, $uid); $lg->execute();
        hrm_cand_activity($conn, $keep, 'note', 'Đã gộp hồ sơ #' . $drop . ' (' . $b['full_name'] . ') vào đây', $uid);
        hrm_audit($conn, $uid, 'candidate_merge', 'candidate', $keep, "merged #$drop");
        jout(true, ['id' => $keep]);
    }

    case 'save_test': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $type = trim($_POST['test_type'] ?? ''); $score = (float)($_POST['score'] ?? 0);
        $passed = $score >= 70 ? 1 : 0;
        $st = $conn->prepare('INSERT INTO hrm_tests (application_id,test_type,score,passed,evaluator_id,taken_at) VALUES (?,?,?,?,?,NOW())');
        $st->bind_param('isdii', $aid, $type, $score, $passed, $uid);
        $st->execute();
        hrm_audit($conn, $uid, 'test_save', 'application', $aid, $type . ' ' . $score);
        jout(true);
    }

    case 'schedule_interview': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $round = max(1, (int)($_POST['round'] ?? 1));
        $type = trim($_POST['interview_type'] ?? 'technical');
        $at = ($_POST['scheduled_at'] ?? '') ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at'])) : null;
        $by = (int)($_POST['interviewer_id'] ?? 0);
        $loc = trim($_POST['location'] ?? '');
        $st = $conn->prepare('INSERT INTO hrm_interviews (application_id,round,interview_type,scheduled_at,interviewer_id,location) VALUES (?,?,?,?,?,?)');
        $st->bind_param('iissis', $aid, $round, $type, $at, $by, $loc);
        $st->execute();
        $info = $conn->query("SELECT c.full_name,c.email,j.title FROM hrm_applications a JOIN hrm_candidates c ON c.id=a.candidate_id JOIN hrm_jobs j ON j.id=a.job_id WHERE a.id=$aid")->fetch_assoc();
        $vars = ['candidate_name'=>$info['full_name'],'job_title'=>$info['title'],'interview_time'=>$at?date('d/m/Y H:i',strtotime($at)):'(sẽ thông báo)','interview_type'=>$type,'location'=>$loc];
        hrm_dispatch($conn, 'interview_invitation', [
            'recipients' => $by ? [$by] : [],
            'notif' => $by ? ['title'=>'Bạn được phân công phỏng vấn: '.$info['full_name'],'body'=>$info['title'].' · '.($at?date('d/m H:i',strtotime($at)):''),'severity'=>'info','link'=>'/hrm/application?id='.$aid] : [],
            'email' => !empty($info['email']) ? [['to'=>$info['email'],'vars'=>$vars]] : [],
            'entity_type'=>'application','entity_id'=>$aid,'actor_id'=>$uid,
        ]);
        jout(true);
    }

    case 'save_evaluation': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $score = (float)($_POST['total_score'] ?? 0);
        $rec = trim($_POST['recommendation'] ?? '');
        $cmt = trim($_POST['comment'] ?? '');
        $st = $conn->prepare('INSERT INTO hrm_evaluations (application_id,evaluator_id,total_score,recommendation,comment) VALUES (?,?,?,?,?)');
        $st->bind_param('iidss', $aid, $uid, $score, $rec, $cmt);
        $st->execute();
        $conn->query('UPDATE hrm_applications SET score=' . $score . ' WHERE id=' . $aid);
        hrm_audit($conn, $uid, 'evaluation_save', 'application', $aid, $rec . ' ' . $score);
        jout(true);
    }

    case 'create_offer': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $salary = (float)($_POST['salary'] ?? 0);
        $start = ($_POST['start_date'] ?? '') ?: null;
        $st = $conn->prepare('INSERT INTO hrm_offers (application_id,salary,start_date,created_by) VALUES (?,?,?,?)');
        $st->bind_param('idsi', $aid, $salary, $start, $uid);
        $st->execute(); $oid = $st->insert_id;
        $conn->query("UPDATE hrm_applications SET owner_id=$uid WHERE id=$aid AND owner_id=0");
        hrm_audit($conn, $uid, 'offer_create', 'offer', $oid, '');
        jout(true, ['id' => $oid]);
    }

    case 'submit_offer': {
        $oid = (int)($_POST['offer_id'] ?? 0);
        $n = hrm_approval_start($conn, 'offer', $oid, '', $uid);
        if ($n === 0) { jout(false, ['error' => 'Chưa cấu hình luồng duyệt offer']); }
        jout(true, ['steps' => $n]);
    }

    case 'offer_response': {
        $oid = (int)($_POST['offer_id'] ?? 0);
        $resp = ($_POST['response'] ?? '') === 'accept' ? 'accepted' : 'declined';
        $offer = $conn->query('SELECT * FROM hrm_offers WHERE id=' . $oid)->fetch_assoc();
        if (!$offer) { jout(false, ['error' => 'Không tìm thấy offer']); }
        $conn->query("UPDATE hrm_offers SET status='$resp', responded_at=NOW() WHERE id=$oid");
        if ($resp === 'accepted') {
            $hired = $conn->query("SELECT id FROM hrm_pipeline_stages WHERE code='HIRED'")->fetch_assoc();
            $hid = (int)($hired['id'] ?? 0);
            $conn->query('UPDATE hrm_applications SET status="hired", stage_id=' . $hid . ' WHERE id=' . (int)$offer['application_id']);
        }
        hrm_audit($conn, $uid, 'offer_' . $resp, 'offer', $oid, '');
        jout(true);
    }

    /* ════════════════ Phase 3: onboarding (60-day) ════════════════════ */

    case 'create_onboarding': {
        $aid = (int)($_POST['application_id'] ?? 0);
        $app = $conn->query("SELECT a.*, c.full_name, j.title AS job_title, j.level
                FROM hrm_applications a JOIN hrm_candidates c ON c.id=a.candidate_id JOIN hrm_jobs j ON j.id=a.job_id
                WHERE a.id=$aid AND a.status='hired'")->fetch_assoc();
        if (!$app) { jout(false, ['error' => 'Ứng viên chưa ở trạng thái đã tuyển']); }
        $exists = $conn->query("SELECT id FROM hrm_onboarding WHERE application_id=$aid")->fetch_assoc();
        if ($exists) { jout(true, ['id' => (int)$exists['id']]); }
        $st = $conn->prepare('INSERT INTO hrm_onboarding (application_id,candidate_name,job_title,level,ta_id,status) VALUES (?,?,?,?,?,"preboarding")');
        $st->bind_param('isssi', $aid, $app['full_name'], $app['job_title'], $app['level'], $uid);
        $st->execute(); $oid = $st->insert_id;
        hrm_audit($conn, $uid, 'onboarding_create', 'onboarding', $oid, $app['full_name']);
        jout(true, ['id' => $oid]);
    }

    case 'save_onboarding': {
        $oid = (int)($_POST['id'] ?? 0);
        $o = $conn->query('SELECT * FROM hrm_onboarding WHERE id=' . $oid)->fetch_assoc();
        if (!$o) { jout(false, ['error' => 'Không tìm thấy']); }
        $start = ($_POST['start_date'] ?? '') ?: null;
        $level = trim($_POST['level'] ?? '');
        $mgr = (int)($_POST['manager_id'] ?? 0); $buddy = (int)($_POST['buddy_id'] ?? 0);
        $ta = (int)($_POST['ta_id'] ?? 0); $bc = (int)($_POST['bc_director_id'] ?? 0);
        $status = $start ? 'active' : 'preboarding';
        $st = $conn->prepare('UPDATE hrm_onboarding SET start_date=?,level=?,manager_id=?,buddy_id=?,ta_id=?,bc_director_id=?,status=? WHERE id=?');
        $st->bind_param('ssiiiisi', $start, $level, $mgr, $buddy, $ta, $bc, $status, $oid);
        $st->execute();

        if ($start) {
            require_once __DIR__ . '/lib/onboarding.php';
            hrm_onb_generate_plan($conn, $oid, $start);
        }
        hrm_audit($conn, $uid, 'onboarding_save', 'onboarding', $oid, '');

        // Notify manager / buddy / bc that they own this onboarding.
        $recips = array_filter([$mgr, $buddy, $bc]);
        if ($recips) {
            hrm_dispatch($conn, 'onboarding_assigned', [
                'recipients' => $recips,
                'notif' => ['title' => 'Bạn được phân công onboarding: ' . $o['candidate_name'],
                            'body' => ($o['job_title'] ?: '') . ($start ? ' · bắt đầu ' . date('d/m/Y', strtotime($start)) : ''),
                            'severity' => 'info', 'link' => '/hrm/onboarding-detail?id=' . $oid],
                'entity_type' => 'onboarding', 'entity_id' => $oid, 'actor_id' => $uid,
            ]);
        }
        jout(true);
    }

    case 'toggle_task': {
        $tid = (int)($_POST['id'] ?? 0);
        $done = !empty($_POST['done']) ? 1 : 0;
        $st = $conn->prepare('UPDATE hrm_onboarding_tasks SET done=?, done_at=' . ($done ? 'NOW()' : 'NULL') . ' WHERE id=?');
        $st->bind_param('ii', $done, $tid);
        $st->execute();
        jout(true);
    }

    case 'save_checkpoint': {
        $cid = (int)($_POST['id'] ?? 0);
        $att = (int)($_POST['score_attitude'] ?? 0); $skl = (int)($_POST['score_skill'] ?? 0); $intg = (int)($_POST['score_integration'] ?? 0);
        $grade = trim($_POST['result_grade'] ?? ''); $notes = trim($_POST['notes'] ?? '');
        $st = $conn->prepare('UPDATE hrm_checkpoints SET score_attitude=?,score_skill=?,score_integration=?,result_grade=?,notes=?,status="done",done_at=NOW() WHERE id=?');
        $st->bind_param('iiissi', $att, $skl, $intg, $grade, $notes, $cid);
        $st->execute();
        $row = $conn->query('SELECT onboarding_id, checkpoint_key FROM hrm_checkpoints WHERE id=' . $cid)->fetch_assoc();
        hrm_audit($conn, $uid, 'checkpoint_done', 'onboarding', (int)($row['onboarding_id'] ?? 0), $row['checkpoint_key'] ?? '');
        jout(true);
    }

    case 'complete_onboarding': {
        $oid = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE hrm_onboarding SET status='completed' WHERE id=$oid");
        hrm_audit($conn, $uid, 'onboarding_complete', 'onboarding', $oid, '');
        jout(true);
    }

    /* ════════════════ Phase 4: probation review ═══════════════════════ */
    case 'save_probation': {
        $oid = (int)($_POST['onboarding_id'] ?? 0);
        $aid = (int)($_POST['application_id'] ?? 0);
        $o = $conn->query('SELECT * FROM hrm_onboarding WHERE id=' . $oid)->fetch_assoc();
        if (!$o) { jout(false, ['error' => 'Không tìm thấy onboarding']); }
        $kpi = min(50, max(0, (int)($_POST['score_kpi'] ?? 0)));
        $comp = min(20, max(0, (int)($_POST['score_competency'] ?? 0)));
        $att = min(20, max(0, (int)($_POST['score_attitude'] ?? 0)));
        $cul = min(10, max(0, (int)($_POST['score_culture'] ?? 0)));
        $total = $kpi + $comp + $att + $cul;
        $decision = in_array($_POST['decision'] ?? '', ['confirm','extend','reject'], true) ? $_POST['decision'] : ($total >= 85 ? 'confirm' : ($total >= 75 ? 'extend' : 'reject'));
        $notes = trim($_POST['notes'] ?? '');

        $prev = $conn->query('SELECT id FROM hrm_probation_reviews WHERE onboarding_id=' . $oid . ' ORDER BY id DESC LIMIT 1')->fetch_assoc();
        if ($prev) {
            $st = $conn->prepare('UPDATE hrm_probation_reviews SET application_id=?,score_kpi=?,score_competency=?,score_attitude=?,score_culture=?,total=?,decision=?,reviewed_by=?,reviewed_at=NOW(),notes=? WHERE id=?');
            $pid = (int)$prev['id'];
            $st->bind_param('iiiiiisisi', $aid, $kpi, $comp, $att, $cul, $total, $decision, $uid, $notes, $pid);
        } else {
            $st = $conn->prepare('INSERT INTO hrm_probation_reviews (onboarding_id,application_id,score_kpi,score_competency,score_attitude,score_culture,total,decision,reviewed_by,reviewed_at,notes) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)');
            $st->bind_param('iiiiiiisis', $oid, $aid, $kpi, $comp, $att, $cul, $total, $decision, $uid, $notes);
        }
        $st->execute();

        // Reflect decision on onboarding lifecycle.
        $newStatus = $decision === 'confirm' ? 'completed' : ($decision === 'reject' ? 'left' : 'active');
        $conn->query("UPDATE hrm_onboarding SET status='$newStatus' WHERE id=$oid");
        hrm_audit($conn, $uid, 'probation_' . $decision, 'onboarding', $oid, $total . '/100');

        // Notify HRBP + manager of the result; on confirm, flag TA recruitment complete (Probation Bonus).
        $recips = array_filter([(int)$o['manager_id'], (int)$o['bc_director_id']]);
        foreach (hrm_users_with_role($conn, 'hrbp') as $u) { $recips[] = $u; }
        if ($recips) {
            hrm_dispatch($conn, 'probation_result', [
                'recipients' => $recips,
                'notif' => ['title' => 'Kết quả thử việc: ' . $o['candidate_name'],
                            'body' => $total . '/100 · ' . ($decision === 'confirm' ? 'Chính thức' : ($decision === 'extend' ? 'Gia hạn' : 'Không tiếp nhận')),
                            'severity' => $decision === 'confirm' ? 'success' : ($decision === 'reject' ? 'danger' : 'warning'),
                            'link' => '/hrm/probation?id=' . $oid],
                'entity_type' => 'onboarding', 'entity_id' => $oid, 'actor_id' => $uid,
            ]);
        }
        if ($decision === 'confirm' && (int)$o['ta_id']) {
            hrm_dispatch($conn, 'ta_recruitment_completed', [
                'recipients' => [(int)$o['ta_id']],
                'notif' => ['title' => 'Hoàn thành tuyển dụng: ' . $o['candidate_name'],
                            'body' => 'Nhân sự pass probation - đủ điều kiện Probation Bonus.',
                            'severity' => 'success', 'link' => '/hrm/probation?id=' . $oid],
                'entity_type' => 'onboarding', 'entity_id' => $oid, 'actor_id' => $uid,
            ]);
        }
        jout(true);
    }

    /* ════════════════ Candidate pool: Excel import ════════════════════ */
    case 'import_candidates': {
        if (empty($_FILES['file']['tmp_name'])) { jout(false, ['error' => 'Chưa chọn file']); }
        require_once __DIR__ . '/lib/xlsx.php';
        try { $rows = hrm_xlsx_rows($_FILES['file']['tmp_name']); }
        catch (Throwable $e) { jout(false, ['error' => 'Lỗi đọc file: ' . $e->getMessage()]); }

        // Locate header row (has both "ID" and "Email").
        $hdrIdx = -1;
        foreach ($rows as $i => $r) {
            if (in_array('ID', $r, true) && in_array('Email', $r, true)) { $hdrIdx = $i; break; }
        }
        if ($hdrIdx < 0) { jout(false, ['error' => 'Không tìm thấy dòng tiêu đề (ID/Email)']); }
        $map = [];
        foreach ($rows[$hdrIdx] as $col => $label) { if ($label !== '') { $map[$label] = $col; } }
        $get = function (array $r, string $label) use ($map) { return isset($map[$label]) ? ($r[$map[$label]] ?? '') : ''; };

        // Source cache (name -> id), create if missing.
        $srcCache = [];
        $srcId = function (string $name) use ($conn, &$srcCache) {
            $name = trim($name);
            if ($name === '') { return 0; }
            $key = mb_strtolower($name);
            if (isset($srcCache[$key])) { return $srcCache[$key]; }
            $st = $conn->prepare('SELECT id FROM hrm_candidate_sources WHERE name = ? LIMIT 1');
            $st->bind_param('s', $name); $st->execute();
            $row = $st->get_result()->fetch_assoc();
            if ($row) { return $srcCache[$key] = (int)$row['id']; }
            $ins = $conn->prepare('INSERT INTO hrm_candidate_sources (name) VALUES (?)');
            $ins->bind_param('s', $name); $ins->execute();
            return $srcCache[$key] = $ins->insert_id;
        };

        $ins = 0; $upd = 0; $skip = 0;
        for ($i = $hdrIdx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $name = trim($get($r, 'Tên'));
            if ($name === '') { $skip++; continue; }
            $ext = trim($get($r, 'ID'));
            $email = trim($get($r, 'Email'));
            $phone = trim($get($r, 'Số điện thoại')) ?: trim($get($r, 'phone'));
            $pos = trim($get($r, 'Công việc gần nhất'));
            $cv = trim($get($r, 'Đường dẫn CV'));
            $gender = trim($get($r, 'Giới tính'));
            $dob = trim($get($r, 'Ngày tháng năm sinh')); if ($dob === '0/0/0') { $dob = ''; }
            $score = (float)str_replace(',', '.', trim($get($r, 'Điểm')));
            $job = trim($get($r, 'Tên tin tuyển dụng'));
            $stage = trim($get($r, 'Giai đoạn'));
            $sid = $srcId($get($r, 'Nguồn'));
            // Extra columns from the Base export.
            $classification = trim($get($r, 'Phân loại'));
            $campaign = trim($get($r, 'Chiến dịch'));
            $idcard = trim($get($r, 'Số CMT'));
            $tags = trim($get($r, 'Thẻ'));
            $appliedDate = preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $get($r, 'Ngày ứng tuyển'), $m) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
            $interview = trim($get($r, 'Thời gian phỏng vấn'));
            $officeTxt = trim($get($r, 'Văn phòng'));
            $rejReason = trim($get($r, 'Lý do từ chối'));
            $updatedSrc = trim($get($r, 'Thời gian cập nhật gần nhất'));

            // Find existing by external_id, else by email.
            $existing = null;
            if ($ext !== '') {
                $st = $conn->prepare('SELECT id FROM hrm_candidates WHERE external_id = ? LIMIT 1');
                $st->bind_param('s', $ext); $st->execute(); $existing = $st->get_result()->fetch_assoc();
            }
            if (!$existing && $email !== '') {
                $st = $conn->prepare('SELECT id FROM hrm_candidates WHERE email = ? LIMIT 1');
                $st->bind_param('s', $email); $st->execute(); $existing = $st->get_result()->fetch_assoc();
            }

            // Build SET clause (admin import, values escaped).
            $esc = fn($v) => "'" . $conn->real_escape_string((string)$v) . "'";
            $set = "external_id=" . $esc($ext) . ",full_name=" . $esc($name) . ",email=" . $esc($email)
                . ",phone=" . $esc($phone) . ",source_id=" . (int)$sid . ",cv_path=" . $esc($cv)
                . ",current_position=" . $esc($pos) . ",gender=" . $esc($gender) . ",dob=" . $esc($dob)
                . ",score=" . (float)$score . ",applied_job=" . $esc($job) . ",applied_stage=" . $esc($stage)
                . ",classification=" . $esc($classification) . ",campaign=" . $esc($campaign) . ",id_card=" . $esc($idcard)
                . ",tags=" . $esc($tags) . ",applied_date=" . ($appliedDate ? $esc($appliedDate) : 'NULL')
                . ",interview_date=" . $esc($interview) . ",office_text=" . $esc($officeTxt)
                . ",reject_reason=" . $esc($rejReason) . ",updated_src=" . $esc($updatedSrc);

            if ($existing) {
                $conn->query("UPDATE hrm_candidates SET $set WHERE id=" . (int)$existing['id']);
                $upd++;
            } else {
                $conn->query("INSERT INTO hrm_candidates SET $set, created_by=" . (int)$uid);
                $ins++;
            }
        }
        hrm_audit($conn, $uid, 'candidate_import', 'candidate', 0, "ins=$ins upd=$upd skip=$skip");
        jout(true, ['inserted' => $ins, 'updated' => $upd, 'skipped' => $skip]);
    }

    /* Attach an existing pool candidate to a job pipeline (create application). */
    case 'add_candidate_to_job': {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        $jid = (int)($_POST['job_id'] ?? 0);
        if (!$cid || !$jid) { jout(false, ['error' => 'Thiếu dữ liệu']); }
        $exist = $conn->query("SELECT id FROM hrm_applications WHERE candidate_id=$cid AND job_id=$jid LIMIT 1")->fetch_assoc();
        if ($exist) { jout(true, ['id' => (int)$exist['id']]); }
        $stage = $conn->query("SELECT id,sla_hours FROM hrm_pipeline_stages WHERE code='SCREENING'")->fetch_assoc();
        $sid = (int)($stage['id'] ?? 0);
        $st = $conn->prepare('INSERT INTO hrm_applications (candidate_id,job_id,stage_id,owner_id) VALUES (?,?,?,?)');
        $st->bind_param('iiii', $cid, $jid, $sid, $uid);
        $st->execute(); $aid = $st->insert_id;
        if (!empty($stage['sla_hours'])) {
            hrm_sla_open($conn, 'application', $aid, 'screening', date('Y-m-d H:i:s', strtotime('+' . (int)$stage['sla_hours'] . ' hours')));
        }
        hrm_audit($conn, $uid, 'candidate_to_pipeline', 'application', $aid, "cand=$cid job=$jid");
        jout(true, ['id' => $aid]);
    }

    /* ════════════════ Job openings: Excel import ══════════════════════ */
    case 'import_jobs': {
        if (empty($_FILES['file']['tmp_name'])) { jout(false, ['error' => 'Chưa chọn file']); }
        require_once __DIR__ . '/lib/xlsx.php';
        try { $rows = hrm_xlsx_rows($_FILES['file']['tmp_name']); }
        catch (Throwable $e) { jout(false, ['error' => 'Lỗi đọc file: ' . $e->getMessage()]); }

        $hdrIdx = -1;
        foreach ($rows as $i => $r) {
            if (in_array('ID', $r, true) && in_array('Trạng thái', $r, true)) { $hdrIdx = $i; break; }
        }
        if ($hdrIdx < 0) { jout(false, ['error' => 'Không tìm thấy dòng tiêu đề (ID/Trạng thái)']); }
        $map = [];
        foreach ($rows[$hdrIdx] as $col => $label) { if ($label !== '') { $map[$label] = $col; } }
        $get = function (array $r, string $label) use ($map) { return isset($map[$label]) ? trim($r[$map[$label]] ?? '') : ''; };
        $toDate = function (string $d) { return preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $d, $m) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null; };

        // Department name -> id (match only, company-wide table, no create).
        $deptMap = [];
        foreach ($conn->query('SELECT id,name FROM departments') as $d) { $deptMap[mb_strtolower(trim($d['name']))] = (int)$d['id']; }
        // Office name -> id. Offices are a managed setting (Cấu hình > Văn phòng):
        // map by name to an EXISTING office only (never create). Exact match first,
        // else prefix match (setting name is the start of the file's value, which may
        // append an address or list several offices). Longest setting name wins.
        $offCache = []; $offList = [];
        foreach ($conn->query('SELECT id,name FROM hrm_offices') as $o) {
            $lc = mb_strtolower(trim($o['name']));
            $offCache[$lc] = (int)$o['id'];
            $offList[] = [$lc, (int)$o['id']];
        }
        usort($offList, fn($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        $officeId = function (string $name) use ($offCache, $offList) {
            $k = mb_strtolower(trim($name));
            if ($k === '') { return 0; }
            if (isset($offCache[$k])) { return $offCache[$k]; }
            foreach ($offList as $o) { if (mb_strpos($k, $o[0]) === 0) { return $o[1]; } }
            return 0;
        };
        $mapStatus = function (string $s) {
            $s = mb_strtolower($s);
            if (strpos($s, 'publish') !== false) { return 'open'; }
            if (strpos($s, 'draft') !== false) { return 'draft'; }
            if (strpos($s, 'clos') !== false || strpos($s, 'unpublish') !== false || strpos($s, 'đóng') !== false) { return 'closed'; }
            return 'open';
        };

        $ins = 0; $upd = 0; $skip = 0;
        for ($i = $hdrIdx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $title = mb_substr($get($r, 'Tên'), 0, 200);
            $ext = $get($r, 'ID');
            if ($title === '' || $ext === '') { $skip++; continue; }
            $dept = $deptMap[mb_strtolower($get($r, 'Tên phòng ban'))] ?? 0;
            $office = $officeId($get($r, 'Tên văn phòng'));
            $headcount = max(1, (int)$get($r, 'Số lượng cần tuyển'));
            $status = $mapStatus($get($r, 'Trạng thái'));
            $deadline = $toDate($get($r, 'Thời gian kết thúc tuyển dụng'));
            $managers = mb_substr($get($r, 'Người quản lý'), 0, 255);
            $poster = mb_substr($get($r, 'Người tạo'), 0, 100);
            $sCreated = $toDate($get($r, 'Thời gian tạo hồ sơ'));
            $sStart = $toDate($get($r, 'Thời gian bắt đầu tuyển dụng'));

            $exist = $conn->query('SELECT id FROM hrm_jobs WHERE external_id = "' . $conn->real_escape_string($ext) . '" LIMIT 1')->fetch_assoc();
            if ($exist) {
                $jid = (int)$exist['id'];
                $st = $conn->prepare('UPDATE hrm_jobs SET title=?,department_id=?,office_id=?,headcount=?,status=?,deadline=?,managers=?,poster=?,source_created=?,source_start=? WHERE id=?');
                $st->bind_param('siiissssssi', $title, $dept, $office, $headcount, $status, $deadline, $managers, $poster, $sCreated, $sStart, $jid);
                $st->execute(); $upd++;
            } else {
                $st = $conn->prepare('INSERT INTO hrm_jobs (external_id,code,title,department_id,office_id,headcount,status,deadline,managers,poster,source_created,source_start,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $st->bind_param('sssiiissssssi', $ext, $ext, $title, $dept, $office, $headcount, $status, $deadline, $managers, $poster, $sCreated, $sStart, $uid);
                $st->execute(); $ins++;
            }
        }
        hrm_audit($conn, $uid, 'job_import', 'job', 0, "ins=$ins upd=$upd skip=$skip");
        jout(true, ['inserted' => $ins, 'updated' => $upd, 'skipped' => $skip]);
    }

    /* ── Kế hoạch tuyển dụng: thêm chu kỳ ─────────────────────────────── */
    case 'add_plan_cycle': {
        hrm_ensure_plan_tables($conn);
        $year = (int)($_POST['year'] ?? 0);
        if ($year < 2000 || $year > 2100) { jout(false, ['error' => 'Năm không hợp lệ']); }
        $name = trim($_POST['name'] ?? '') ?: ('Năm ' . $year);
        $exists = $conn->query("SELECT id FROM hrm_plan_cycles WHERE year=" . $year)->fetch_assoc();
        if ($exists) { jout(true, ['id' => (int)$exists['id']]); }
        $st = $conn->prepare('INSERT INTO hrm_plan_cycles (name, year) VALUES (?, ?)');
        $st->bind_param('si', $name, $year);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        $id = $st->insert_id;
        hrm_audit($conn, $uid, 'plan_cycle_add', 'plan', $id, $name);
        jout(true, ['id' => $id]);
    }

    /* ── Kế hoạch tuyển dụng: xóa chu kỳ ──────────────────────────────── */
    case 'del_plan_cycle': {
        hrm_ensure_plan_tables($conn);
        $cid = (int)($_POST['cycle_id'] ?? 0);
        if ($cid <= 0) { jout(false, ['error' => 'Thiếu chu kỳ']); }
        $conn->query("DELETE FROM hrm_plan_lines WHERE cycle_id=" . $cid);
        $conn->query("DELETE FROM hrm_plan_cycles WHERE id=" . $cid);
        hrm_audit($conn, $uid, 'plan_cycle_del', 'plan', $cid, '');
        jout(true);
    }

    /* ── Kế hoạch tuyển dụng: lưu định biên 1 phòng ban trong chu kỳ ──── */
    case 'save_plan_line': {
        hrm_ensure_plan_tables($conn);
        $cid  = (int)($_POST['cycle_id'] ?? 0);
        $dept = (int)($_POST['department_id'] ?? 0);
        if ($cid <= 0 || $dept <= 0) { jout(false, ['error' => 'Thiếu chu kỳ hoặc phòng ban']); }

        $chot   = max(0, (int)($_POST['dinh_bien_chot'] ?? 0));
        $nhansu = max(0, (int)($_POST['nhan_su'] ?? 0));
        // Chuẩn hóa 12 giá trị tháng (>=0) cho cả định biên & thực tế.
        $norm = function ($raw) {
            $arr = json_decode((string)$raw, true);
            if (!is_array($arr)) { $arr = []; }
            $out = [];
            for ($i = 0; $i < 12; $i++) { $out[$i] = max(0, (int)($arr[$i] ?? 0)); }
            return $out;
        };
        $plan   = json_encode($norm($_POST['months_plan'] ?? ''),   JSON_UNESCAPED_UNICODE);
        $actual = json_encode($norm($_POST['months_actual'] ?? ''), JSON_UNESCAPED_UNICODE);

        $st = $conn->prepare('INSERT INTO hrm_plan_lines (cycle_id, department_id, dinh_bien_chot, nhan_su, months_plan, months_actual)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE dinh_bien_chot=VALUES(dinh_bien_chot), nhan_su=VALUES(nhan_su),
                months_plan=VALUES(months_plan), months_actual=VALUES(months_actual)');
        $st->bind_param('iiiiss', $cid, $dept, $chot, $nhansu, $plan, $actual);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        jout(true);
    }

    /* ── Kế hoạch tuyển dụng: xóa 1 phòng ban khỏi bảng (đánh dấu removed) ─ */
    case 'remove_plan_dept': {
        hrm_ensure_plan_tables($conn);
        $cid  = (int)($_POST['cycle_id'] ?? 0);
        $dept = (int)($_POST['department_id'] ?? 0);
        if ($cid <= 0 || $dept <= 0) { jout(false, ['error' => 'Thiếu chu kỳ hoặc phòng ban']); }
        $empty = json_encode(array_fill(0, 12, 0));
        $st = $conn->prepare('INSERT INTO hrm_plan_lines (cycle_id, department_id, months_plan, months_actual, removed)
            VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE removed=1');
        $st->bind_param('iiss', $cid, $dept, $empty, $empty);
        if (!$st->execute()) { jout(false, ['error' => $conn->error]); }
        hrm_audit($conn, $uid, 'plan_dept_remove', 'plan', $cid, 'dept=' . $dept);
        jout(true);
    }

    /* ── Kế hoạch tuyển dụng: thêm lại phòng ban vào bảng ─────────────── */
    case 'restore_plan_dept': {
        hrm_ensure_plan_tables($conn);
        $cid  = (int)($_POST['cycle_id'] ?? 0);
        $dept = (int)($_POST['department_id'] ?? 0);
        if ($cid <= 0 || $dept <= 0) { jout(false, ['error' => 'Thiếu chu kỳ hoặc phòng ban']); }
        $st = $conn->prepare('UPDATE hrm_plan_lines SET removed=0 WHERE cycle_id=? AND department_id=?');
        $st->bind_param('ii', $cid, $dept);
        $st->execute();
        hrm_audit($conn, $uid, 'plan_dept_restore', 'plan', $cid, 'dept=' . $dept);
        jout(true);
    }

    default:
        jout(false, ['error' => 'Unknown action: ' . $action]);
}
