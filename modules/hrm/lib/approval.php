<?php
/**
 * Generic approval engine - reused by HRF (hrf) and Offer (offer).
 *
 * Flow steps come from hrm_approval_flows; approvers are resolved from
 * hrm_role_assignments at runtime. Each entity gets a set of hrm_approvals
 * rows; the "current" step is the lowest step_order still pending.
 *
 *   hrm_approval_start($conn, 'hrf', $id, $conditionKey, $actorId)
 *   hrm_approval_act($conn, $approvalId, $userId, 'approved'|'rejected', $note)
 *   hrm_approval_steps($conn, 'hrf', $id)   // for rendering
 */
require_once __DIR__ . '/events.php';

/** Begin an approval chain for an entity. Returns number of steps created. */
function hrm_approval_start(mysqli $conn, string $entityType, int $entityId, string $conditionKey, int $actorId): int
{
    // Load flow (fall back to the unconditioned flow).
    $flow = hrm_approval_flow($conn, $entityType, $conditionKey);
    if (!$flow) { return 0; }

    // Clear any prior rows (re-submit), then create fresh.
    $del = $conn->prepare('DELETE FROM hrm_approvals WHERE entity_type = ? AND entity_id = ?');
    $del->bind_param('si', $entityType, $entityId);
    $del->execute();

    $ins = $conn->prepare('INSERT INTO hrm_approvals (entity_type,entity_id,step_order,approver_role) VALUES (?,?,?,?)');
    foreach ($flow as $f) {
        $ins->bind_param('siis', $entityType, $entityId, $f['step_order'], $f['approver_role']);
        $ins->execute();
    }

    hrm_entity_set_status($conn, $entityType, $entityId, 'pending');
    hrm_approval_activate_current($conn, $entityType, $entityId, $actorId);
    hrm_audit($conn, $actorId, 'approval_start', $entityType, $entityId, $conditionKey);
    return count($flow);
}

/** Ordered flow rows for an entity_type/condition (with '' fallback). */
function hrm_approval_flow(mysqli $conn, string $entityType, string $conditionKey): array
{
    foreach ([$conditionKey, ''] as $cond) {
        $st = $conn->prepare('SELECT step_order, approver_role, sla_hours FROM hrm_approval_flows WHERE entity_type = ? AND condition_key = ? AND active = 1 ORDER BY step_order');
        $st->bind_param('ss', $entityType, $cond);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        if ($rows) { return $rows; }
    }
    return [];
}

/** The lowest-order still-pending approval row, or null. */
function hrm_approval_current(mysqli $conn, string $entityType, int $entityId): ?array
{
    $st = $conn->prepare('SELECT * FROM hrm_approvals WHERE entity_type = ? AND entity_id = ? AND status = "pending" ORDER BY step_order LIMIT 1');
    $st->bind_param('si', $entityType, $entityId);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

/** Set due_at on the current step + notify its approvers. */
function hrm_approval_activate_current(mysqli $conn, string $entityType, int $entityId, int $actorId): void
{
    $cur = hrm_approval_current($conn, $entityType, $entityId);
    if (!$cur) { return; }

    $sla = 48;
    $f = hrm_approval_flow($conn, $entityType, '');
    foreach ($f as $row) { if ((int)$row['step_order'] === (int)$cur['step_order']) { $sla = (int)$row['sla_hours']; } }
    $dueAt = date('Y-m-d H:i:s', strtotime("+{$sla} hours"));

    $up = $conn->prepare('UPDATE hrm_approvals SET due_at = ? WHERE id = ?');
    $up->bind_param('si', $dueAt, $cur['id']);
    $up->execute();

    hrm_sla_open($conn, $entityType, $entityId, 'approval_step_' . $cur['step_order'], $dueAt);
    hrm_approval_dispatch_request($conn, $entityType, $entityId, $cur['approver_role'], $dueAt);
}

/** Approve or reject the current step. Returns the resulting overall status. */
function hrm_approval_act(mysqli $conn, int $approvalId, int $userId, string $action, string $note): string
{
    $st = $conn->prepare('SELECT * FROM hrm_approvals WHERE id = ?');
    $st->bind_param('i', $approvalId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row || $row['status'] !== 'pending') { return 'invalid'; }

    // Authorisation: caller must hold the step's role (admin bypass in helper).
    if (!hrm_user_has_role($conn, $userId, $row['approver_role'])) { return 'forbidden'; }

    $entityType = $row['entity_type'];
    $entityId   = (int)$row['entity_id'];
    $newStatus  = ($action === 'approved') ? 'approved' : 'rejected';

    $up = $conn->prepare('UPDATE hrm_approvals SET status = ?, acted_at = NOW(), acted_by = ?, note = ? WHERE id = ?');
    $up->bind_param('sisi', $newStatus, $userId, $note, $approvalId);
    $up->execute();

    hrm_sla_satisfy($conn, $entityType, $entityId, 'approval_step_' . $row['step_order']);
    hrm_audit($conn, $userId, 'approval_' . $newStatus, $entityType, $entityId, 'step ' . $row['step_order'] . ($note ? ': ' . $note : ''));

    if ($action === 'rejected') {
        hrm_entity_set_status($conn, $entityType, $entityId, 'rejected');
        hrm_approval_dispatch_result($conn, $entityType, $entityId, 'rejected', $row['approver_role'], $note);
        return 'rejected';
    }

    // Approved - advance or finalise.
    if (hrm_approval_current($conn, $entityType, $entityId)) {
        hrm_approval_activate_current($conn, $entityType, $entityId, $userId);
        return 'pending';
    }
    hrm_entity_set_status($conn, $entityType, $entityId, 'approved');
    hrm_approval_dispatch_result($conn, $entityType, $entityId, 'approved', '', '');
    return 'approved';
}

/** All approval rows for rendering a timeline. */
function hrm_approval_steps(mysqli $conn, string $entityType, int $entityId): array
{
    $st = $conn->prepare('SELECT * FROM hrm_approvals WHERE entity_type = ? AND entity_id = ? ORDER BY step_order');
    $st->bind_param('si', $entityType, $entityId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    return $rows;
}

/* ── entity adapters (status + dispatch). Extend per entity_type. ─────── */

function hrm_entity_set_status(mysqli $conn, string $entityType, int $entityId, string $status): void
{
    if ($entityType === 'hrf') {
        $st = $conn->prepare('UPDATE hrm_requests SET status = ? WHERE id = ?');
        $st->bind_param('si', $status, $entityId);
        $st->execute();
    } elseif ($entityType === 'offer') {
        // Phase 2: map approved->sent-ready etc.
        $map = ['pending' => 'pending_approval', 'approved' => 'sent', 'rejected' => 'draft'];
        $s = $map[$status] ?? $status;
        $st = $conn->prepare('UPDATE hrm_offers SET status = ? WHERE id = ?');
        $st->bind_param('si', $s, $entityId);
        $st->execute();
    }
}

/** Notify approvers of the current step that they must act. */
function hrm_approval_dispatch_request(mysqli $conn, string $entityType, int $entityId, string $role, string $dueAt): void
{
    if ($entityType === 'offer') { hrm_offer_dispatch_request($conn, $entityId, $role, $dueAt); return; }
    if ($entityType !== 'hrf') { return; }

    hrm_ensure_email_templates($conn);   // bảo đảm template chuẩn trước khi gửi (live)
    $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $entityId)->fetch_assoc();
    if (!$req) { return; }

    // Khoảng lương + nhãn loại tuyển dụng cho email phê duyệt.
    $cur = $req['currency'] ?: 'VND';
    $sMin = (float)$req['salary_min']; $sMax = (float)$req['salary_max'];
    if ($sMin > 0 && $sMax > 0)      { $salaryRange = number_format($sMin) . ' - ' . number_format($sMax) . ' ' . $cur; }
    elseif ($sMin > 0)               { $salaryRange = 'Từ ' . number_format($sMin) . ' ' . $cur; }
    elseif ($sMax > 0)               { $salaryRange = 'Tối đa ' . number_format($sMax) . ' ' . $cur; }
    else                             { $salaryRange = 'Thỏa thuận'; }
    $typeLabel = $req['request_type'] === 'new_hc' ? 'Tuyển mới (tăng headcount)' : 'Thay thế';
    $jdHtml = trim((string)$req['jd']) !== '' ? nl2br($req['jd']) : '(Chưa cập nhật)';

    $approvers = hrm_users_with_role($conn, $role);
    $emails = [];
    foreach ($approvers as $uid) {
        $u = hrm_user($conn, $uid);
        $emails[] = ['to' => $u['email'], 'vars' => [
            'approver_name' => $u['full_name'], 'request_code' => $req['code'],
            'request_title' => $req['title'], 'quantity' => $req['quantity'],
            'level' => $req['level'], 'reason' => $req['reason'],
            'need_by_date' => $req['need_by_date'], 'due_at' => $dueAt,
            'request_type' => $typeLabel, 'employment_type' => $req['employment_type'] ?: '(Chưa cập nhật)',
            'experience_required' => $req['experience_required'] ?: '(Chưa cập nhật)',
            'salary_range' => $salaryRange, 'priority' => $req['priority'] ?: '(Chưa cập nhật)',
            'jd' => $jdHtml,
            'link' => '/hrm/requests?id=' . $entityId,
        ]];
    }

    hrm_dispatch($conn, 'hrf_approval_request', [
        'recipients'  => $approvers,
        'notif'       => [
            'title' => 'HRF chờ bạn duyệt: ' . $req['title'],
            'body'  => $req['code'] . ' · ' . $req['quantity'] . ' vị trí · hạn ' . date('d/m H:i', strtotime($dueAt)),
            'severity' => 'warning', 'link' => '/hrm/requests?id=' . $entityId,
        ],
        'email'       => $emails,
        'entity_type' => 'hrf', 'entity_id' => $entityId,
    ]);
}

/** Notify the requester of the final result. */
function hrm_approval_dispatch_result(mysqli $conn, string $entityType, int $entityId, string $result, string $role, string $note): void
{
    if ($entityType === 'offer') { hrm_offer_dispatch_result($conn, $entityId, $result, $note); return; }
    if ($entityType !== 'hrf') { return; }

    $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $entityId)->fetch_assoc();
    if (!$req) { return; }
    $requester = hrm_user($conn, (int)$req['created_by']);

    $eventKey = $result === 'approved' ? 'hrf_approved' : 'hrf_rejected';
    $title = $result === 'approved' ? 'HRF đã được duyệt: ' . $req['title'] : 'HRF bị từ chối: ' . $req['title'];

    hrm_dispatch($conn, $eventKey, [
        'recipients'  => [(int)$req['created_by']],
        'notif'       => [
            'title' => $title,
            'body'  => $req['code'] . ($note ? ' · ' . $note : ''),
            'severity' => $result === 'approved' ? 'success' : 'danger',
            'link' => '/hrm/requests?id=' . $entityId,
        ],
        'email'       => [['to' => $requester['email'], 'vars' => [
            'requester_name' => $requester['full_name'], 'request_code' => $req['code'],
            'request_title' => $req['title'], 'approver_role' => $role, 'note' => $note,
            'link' => '/hrm/requests?id=' . $entityId,
        ]]],
        'entity_type' => 'hrf', 'entity_id' => $entityId,
    ]);
}

/* ── Offer approval dispatch (B8) ─────────────────────────────────────── */

/** Load offer + application + candidate + job in one row. */
function hrm_offer_context(mysqli $conn, int $offerId): ?array
{
    $sql = 'SELECT o.*, a.id AS application_id, a.owner_id, c.full_name AS candidate_name, c.email AS candidate_email, j.title AS job_title
            FROM hrm_offers o
            JOIN hrm_applications a ON a.id = o.application_id
            JOIN hrm_candidates c ON c.id = a.candidate_id
            JOIN hrm_jobs j ON j.id = a.job_id
            WHERE o.id = ' . $offerId;
    return $conn->query($sql)->fetch_assoc() ?: null;
}

function hrm_offer_dispatch_request(mysqli $conn, int $offerId, string $role, string $dueAt): void
{
    $o = hrm_offer_context($conn, $offerId);
    if (!$o) { return; }
    $link = '/hrm/application?id=' . (int)$o['application_id'];
    $approvers = hrm_users_with_role($conn, $role);
    $emails = [];
    foreach ($approvers as $uid) {
        $u = hrm_user($conn, $uid);
        $emails[] = ['to' => $u['email'], 'vars' => [
            'approver_name' => $u['full_name'], 'candidate_name' => $o['candidate_name'],
            'job_title' => $o['job_title'], 'salary' => number_format((float)$o['salary']),
            'currency' => $o['currency'], 'due_at' => $dueAt, 'link' => $link,
        ]];
    }
    hrm_dispatch($conn, 'offer_approval_request', [
        'recipients'  => $approvers,
        'notif'       => [
            'title' => 'Offer chờ bạn duyệt: ' . $o['candidate_name'],
            'body'  => $o['job_title'] . ' · ' . number_format((float)$o['salary']) . ' ' . $o['currency'],
            'severity' => 'warning', 'link' => $link,
        ],
        'email'       => $emails,
        'entity_type' => 'offer', 'entity_id' => $offerId,
    ]);
}

function hrm_offer_dispatch_result(mysqli $conn, int $offerId, string $result, string $note): void
{
    $o = hrm_offer_context($conn, $offerId);
    if (!$o) { return; }
    $link = '/hrm/application?id=' . (int)$o['application_id'];
    $owner = (int)$o['owner_id'] ?: (int)$o['created_by'];
    $title = $result === 'approved'
        ? 'Offer đã duyệt - hãy gửi thư mời cho ứng viên: ' . $o['candidate_name']
        : 'Offer bị từ chối: ' . $o['candidate_name'];
    // KHÔNG tự gửi offer letter cho ứng viên. Sau khi duyệt, HR bấm nút
    // "Gửi email cho ứng viên" (chọn template Thư mời nhận việc) ở trang đơn ứng tuyển.
    hrm_dispatch($conn, $result === 'approved' ? 'offer_approved' : 'offer_rejected', [
        'recipients'  => [$owner],
        'notif'       => [
            'title' => $title,
            'body'  => $o['job_title'] . ($note ? ' · ' . $note : ''),
            'severity' => $result === 'approved' ? 'success' : 'danger', 'link' => $link,
        ],
        'entity_type' => 'offer', 'entity_id' => $offerId,
    ]);
}
