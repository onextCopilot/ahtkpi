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

/** Sidebar trượt phải 50% chứa timeline. Page chỉ cần thêm nút onclick="openHistory()". */
function hrm_history_sidebar(array $events): void
{
    ?>
    <div id="hsbOverlay" class="hsb-overlay" onclick="closeHistory()"></div>
    <aside id="hsbPanel" class="hsb-panel">
        <div class="hsb-head"><h3>Lịch sử hoạt động</h3><button class="hsb-x" onclick="closeHistory()">✕</button></div>
        <div class="hsb-body"><?php hrm_render_history($events); ?></div>
    </aside>
    <style>
    .hsb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1100}
    .hsb-panel{position:fixed;top:0;right:0;height:100vh;width:50vw;min-width:420px;max-width:94vw;background:#fff;z-index:1101;
        box-shadow:-8px 0 30px rgba(0,0,0,.18);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .25s ease}
    .hsb-panel.open{transform:translateX(0)}
    .hsb-head{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #eceef1}
    .hsb-head h3{font-size:15px;margin:0;font-weight:700}
    .hsb-x{background:none;border:none;font-size:15px;cursor:pointer;color:#86868b;line-height:1}
    .hsb-body{flex:1;overflow-y:auto;padding:6px 18px 18px}
    .hsb-body .rc-step{padding:9px 0;gap:10px}
    .hsb-body .rc-step-dot{width:22px;height:22px;font-size:11px}
    .hsb-body .rc-step b{font-size:12.5px}
    .hsb-body .rc-muted{font-size:11px;line-height:1.45}
    </style>
    <script>
    function openHistory(){document.getElementById('hsbOverlay').style.display='block';document.getElementById('hsbPanel').classList.add('open');}
    function closeHistory(){document.getElementById('hsbPanel').classList.remove('open');document.getElementById('hsbOverlay').style.display='none';}
    </script>
    <?php
}

/** Xuất HTML timeline (đặt trong .rc-card hoặc sidebar). */
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
