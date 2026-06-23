<?php
/**
 * Timeline lịch sử hoạt động của ứng viên / đơn ứng tuyển.
 * Gộp hrm_audit_log (hành động) + hrm_email_log (email đã gửi), sắp theo thời gian.
 */
require_once __DIR__ . '/core.php';

/** Nhãn + icon + class màu cho từng action. [label, icon, class] */
function hrm_action_meta(string $action): array
{
    $m = [
        'candidate_update'      => ['Cập nhật thông tin ứng viên', '✎', 'cur'],
        'candidate_add'         => ['Thêm vào pipeline', '+', 'ok'],
        'candidate_add_webhook' => ['Ứng viên ứng tuyển (web)', '+', 'ok'],
        'candidate_to_pipeline' => ['Thêm vào tin tuyển dụng', '+', 'ok'],
        'stage_move'            => ['Chuyển giai đoạn', '→', 'cur'],
        'ta_review'             => ['TA Review (sàng lọc)', '✎', 'cur'],
        'application_assign'    => ['Gán phụ trách', '◷', ''],
        'application_reject'    => ['Từ chối ứng viên', '✕', 'no'],
        'application_hire'      => ['Tuyển (hired)', '✓', 'ok'],
        'test_save'             => ['Lưu kết quả test', '✎', ''],
        'evaluation_save'       => ['Lưu đánh giá sau PV', '✎', ''],
        'offer_create'          => ['Tạo offer', '✎', 'cur'],
        'offer_accepted'        => ['Ứng viên nhận offer', '✓', 'ok'],
        'offer_declined'        => ['Ứng viên từ chối offer', '✕', 'no'],
        'approval_start'        => ['Bắt đầu luồng duyệt', '◷', 'cur'],
        'approval_approved'     => ['Đã duyệt', '✓', 'ok'],
        'approval_rejected'     => ['Từ chối duyệt', '✕', 'no'],
    ];
    return $m[$action] ?? [$action, '•', ''];
}

/**
 * Trả về danh sách sự kiện đã chuẩn hóa, mới nhất trước.
 * $appIds: id các đơn ứng tuyển; $candidateId: id ứng viên; $offerIds: id offer liên quan.
 */
function hrm_history_events(mysqli $conn, array $appIds, int $candidateId = 0, array $offerIds = []): array
{
    $appIds   = array_values(array_filter(array_map('intval', $appIds)));
    $offerIds = array_values(array_filter(array_map('intval', $offerIds)));
    $events = [];

    // 1) Audit log
    $conds = [];
    if ($appIds)      { $conds[] = "(entity_type='application' AND entity_id IN (" . implode(',', $appIds) . "))"; }
    if ($candidateId) { $conds[] = "(entity_type='candidate' AND entity_id=" . (int)$candidateId . ")"; }
    if ($offerIds)    { $conds[] = "(entity_type='offer' AND entity_id IN (" . implode(',', $offerIds) . "))"; }
    if ($conds) {
        $res = $conn->query("SELECT a.action, a.detail, a.created_at, a.user_id, u.full_name AS actor
            FROM hrm_audit_log a LEFT JOIN users u ON u.id = a.user_id
            WHERE " . implode(' OR ', $conds) . " ORDER BY a.created_at DESC");
        while ($res && ($r = $res->fetch_assoc())) {
            if ($r['action'] === 'send_candidate_email') { continue; } // tránh trùng với email_log
            [$label, $icon, $cls] = hrm_action_meta($r['action']);
            $events[] = [
                'at' => $r['created_at'], 'icon' => $icon, 'cls' => $cls,
                'title' => $label,
                'detail' => $r['detail'],
                'actor' => $r['actor'] ?: ((int)$r['user_id'] ? '#' . (int)$r['user_id'] : 'Hệ thống'),
            ];
        }
    }

    // 2) Email log
    $econds = [];
    if ($appIds)   { $econds[] = "(entity_type='application' AND entity_id IN (" . implode(',', $appIds) . "))"; }
    if ($offerIds) { $econds[] = "(entity_type='offer' AND entity_id IN (" . implode(',', $offerIds) . "))"; }
    if ($econds) {
        $res = $conn->query("SELECT event_key, to_email, subject, status, created_at
            FROM hrm_email_log WHERE " . implode(' OR ', $econds) . " ORDER BY created_at DESC");
        while ($res && ($r = $res->fetch_assoc())) {
            $events[] = [
                'at' => $r['created_at'], 'icon' => '✉', 'cls' => ($r['status'] === 'sent' ? 'ok' : 'no'),
                'title' => 'Email: ' . ($r['subject'] ?: $r['event_key']),
                'detail' => 'Tới ' . $r['to_email'] . ' · ' . ($r['status'] === 'sent' ? 'đã gửi' : 'gửi lỗi'),
                'actor' => '',
            ];
        }
    }

    usort($events, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
    return $events;
}

/** Xuất HTML timeline (đặt trong .rc-card). */
function hrm_render_history(array $events): void
{
    if (!$events) { echo '<div class="rc-muted">Chưa có hoạt động nào.</div>'; return; }
    foreach ($events as $e) {
        $sub = [];
        if (!empty($e['detail'])) { $sub[] = h($e['detail']); }
        if (!empty($e['actor']))  { $sub[] = h($e['actor']); }
        $sub[] = date('d/m/Y H:i', strtotime($e['at']));
        echo '<div class="rc-step"><div class="rc-step-dot ' . h($e['cls']) . '">' . $e['icon'] . '</div>'
            . '<div style="flex:1"><div><b>' . h($e['title']) . '</b></div>'
            . '<div class="rc-muted">' . implode(' · ', $sub) . '</div></div></div>';
    }
}
