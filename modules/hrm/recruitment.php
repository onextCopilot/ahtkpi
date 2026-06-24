<?php
/**
 * Recruitment dashboard - entered from the HRM launcher (/hrm).
 * Phase 1: HRF stats + approvals waiting on me + quick links.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/approval.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid     = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$myRoles = hrm_roles_of($conn, $uid);

$counts = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$res = $conn->query("SELECT status, COUNT(*) c FROM hrm_requests GROUP BY status");
while ($r = $res->fetch_assoc()) { if (isset($counts[$r['status']])) { $counts[$r['status']] = (int)$r['c']; } }

// HRFs awaiting my approval.
$waiting = [];
$pending = $conn->query("SELECT rq.*, d.name AS dept_name, o.name AS office_name, u.full_name AS creator_name
    FROM hrm_requests rq
    LEFT JOIN departments d ON d.id = rq.department_id
    LEFT JOIN hrm_offices o ON o.id = rq.office_id
    LEFT JOIN users u ON u.id = rq.created_by
    WHERE rq.status='pending' ORDER BY rq.id DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($pending as $r) {
    $cur = hrm_approval_current($conn, 'hrf', (int)$r['id']);
    if ($cur && hrm_user_has_role($conn, $uid, $cur['approver_role'])) { $waiting[] = $r + ['_due' => $cur['due_at']]; }
}

// Khoảng lương dạng chuỗi.
$fmtSalary = function (array $r): string {
    $cur = $r['currency'] ?: 'VND';
    $mn = (float)$r['salary_min']; $mx = (float)$r['salary_max'];
    if ($mn > 0 && $mx > 0) { return number_format($mn) . ' - ' . number_format($mx) . ' ' . $cur; }
    if ($mn > 0) { return 'Từ ' . number_format($mn) . ' ' . $cur; }
    if ($mx > 0) { return 'Tối đa ' . number_format($mx) . ' ' . $cur; }
    return 'Thỏa thuận';
};

hrm_header('Tuyển dụng', 'Tổng quan quy trình tuyển dụng AHT', 'overview');
?>
<div class="rc-toolbar">
    <div></div>
    <a href="/hrm/requests?new=1" class="rc-btn">+ Tạo HRF</a>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px">
    <?php
    $cards = [
        ['Nháp', $counts['draft'], '#64748b'],
        ['Chờ duyệt', $counts['pending'], '#b45309'],
        ['Đã duyệt', $counts['approved'], '#16a34a'],
        ['Từ chối', $counts['rejected'], '#dc2626'],
    ];
    foreach ($cards as $c): ?>
        <div class="rc-card" style="margin:0">
            <div class="rc-muted"><?= $c[0] ?></div>
            <div style="font-size:30px;font-weight:700;color:<?= $c[2] ?>"><?= $c[1] ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="rc-card">
    <h3 style="font-size:15px;margin-bottom:10px">HRF chờ bạn duyệt (<?= count($waiting) ?>)</h3>
    <?php if (!$waiting): ?>
        <div class="rc-muted">Không có yêu cầu nào chờ bạn.</div>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table class="rc-table" style="white-space:nowrap">
            <thead><tr>
                <th>Mã</th><th>Vị trí</th><th>Bộ phận</th><th>Văn phòng</th><th>Level</th><th>SL</th>
                <th>Loại</th><th>Hình thức</th><th>Kinh nghiệm</th><th>Khoảng lương</th><th>Ưu tiên</th>
                <th>Lý do</th><th>Người duyệt</th><th>Người tạo</th><th>Ngày tạo</th><th>Cần onboard</th>
                <th>Hạn xử lý</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($waiting as $r):
                $overdue = $r['_due'] && strtotime($r['_due']) < time(); ?>
                <tr>
                    <td><b><?= h($r['code']) ?></b></td>
                    <td><?= h($r['title']) ?></td>
                    <td><?= h($r['dept_name'] ?: '-') ?></td>
                    <td><?= h($r['office_name'] ?: '-') ?></td>
                    <td><?= h($r['level'] ?: '-') ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td><?= $r['request_type'] === 'new_hc' ? 'Tuyển mới' : 'Thay thế' ?></td>
                    <td><?= h($r['employment_type'] ?: '-') ?></td>
                    <td><?= h($r['experience_required'] ?: '-') ?></td>
                    <td><?= h($fmtSalary($r)) ?></td>
                    <td><?= h($r['priority'] ?: '-') ?></td>
                    <td><?= h($r['reason'] ?: '-') ?></td>
                    <td><?= !empty($r['approver_role']) ? h(hrm_role_label($r['approver_role'])) : '-' ?></td>
                    <td><?= h($r['creator_name'] ?: '-') ?></td>
                    <td><?= $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-' ?></td>
                    <td><?= $r['need_by_date'] ? date('d/m/Y', strtotime($r['need_by_date'])) : '-' ?></td>
                    <td style="color:<?= $overdue ? '#dc2626' : 'inherit' ?>"><?= $r['_due'] ? date('d/m H:i', strtotime($r['_due'])) : '-' ?><?= $overdue ? ' (quá hạn)' : '' ?></td>
                    <td><a href="/hrm/requests?id=<?= $r['id'] ?>" class="rc-btn" style="padding:5px 12px">Duyệt</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="rc-card">
    <div class="rc-muted">Các bước tiếp theo của quy trình (Pipeline, Onboarding, Thử việc, KPI/Báo cáo) sẽ được bổ sung ở các phase sau theo SOP.</div>
</div>
<?php
hrm_footer();
