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
$pending = $conn->query("SELECT * FROM hrm_requests WHERE status='pending' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($pending as $r) {
    $cur = hrm_approval_current($conn, 'hrf', (int)$r['id']);
    if ($cur && hrm_user_has_role($conn, $uid, $cur['approver_role'])) { $waiting[] = $r + ['_due' => $cur['due_at']]; }
}

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
        <table class="rc-table">
            <thead><tr><th>Mã</th><th>Vị trí</th><th>SL</th><th>Hạn xử lý</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($waiting as $r):
                $overdue = $r['_due'] && strtotime($r['_due']) < time(); ?>
                <tr>
                    <td><b><?= h($r['code']) ?></b></td>
                    <td><?= h($r['title']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td style="color:<?= $overdue ? '#dc2626' : 'inherit' ?>"><?= $r['_due'] ? date('d/m H:i', strtotime($r['_due'])) : '-' ?><?= $overdue ? ' (quá hạn)' : '' ?></td>
                    <td><a href="/hrm/requests?id=<?= $r['id'] ?>" class="rc-btn" style="padding:5px 12px">Duyệt</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="rc-card">
    <div class="rc-muted">Các bước tiếp theo của quy trình (Pipeline, Onboarding, Thử việc, KPI/Báo cáo) sẽ được bổ sung ở các phase sau theo SOP.</div>
</div>
<?php
hrm_footer();
