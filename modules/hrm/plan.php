<?php
/**
 * Kế hoạch tuyển dụng - landing/overview của module "Kế hoạch tuyển dụng".
 * Tổng hợp nhu cầu tuyển (HRF): số lượng theo trạng thái, theo phòng ban,
 * và các HRF đang chờ chính bạn duyệt. "Yêu cầu tuyển dụng" (/hrm/requests)
 * nằm trong module này.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/approval.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid     = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Số HRF + tổng headcount theo trạng thái.
$counts = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$heads  = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$res = $conn->query("SELECT status, COUNT(*) c, COALESCE(SUM(quantity),0) q FROM hrm_requests GROUP BY status");
while ($r = $res->fetch_assoc()) {
    if (isset($counts[$r['status']])) { $counts[$r['status']] = (int)$r['c']; $heads[$r['status']] = (int)$r['q']; }
}

// Nhu cầu tuyển theo phòng ban (chỉ tính HRF chờ duyệt + đã duyệt = kế hoạch còn hiệu lực).
$byDept = $conn->query("
    SELECT d.name, COUNT(*) c, COALESCE(SUM(r.quantity),0) q
    FROM hrm_requests r
    LEFT JOIN departments d ON d.id = r.department_id
    WHERE r.status IN ('pending','approved')
    GROUP BY r.department_id ORDER BY q DESC, c DESC LIMIT 12
")->fetch_all(MYSQLI_ASSOC);

// HRF đang chờ chính tôi duyệt.
$waiting = [];
$pending = $conn->query("SELECT * FROM hrm_requests WHERE status='pending' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($pending as $r) {
    $cur = hrm_approval_current($conn, 'hrf', (int)$r['id']);
    if ($cur && hrm_user_has_role($conn, $uid, $cur['approver_role'])) { $waiting[] = $r + ['_due' => $cur['due_at']]; }
}

hrm_header('Kế hoạch tuyển dụng', 'Hoạch định nhu cầu tuyển dụng AHT', 'plan');
?>
<div class="rc-toolbar">
    <div></div>
    <a href="/hrm/requests?new=1" class="rc-btn">+ Tạo yêu cầu tuyển dụng</a>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px">
    <?php
    $cards = [
        ['Nháp', $counts['draft'], $heads['draft'], '#64748b'],
        ['Chờ duyệt', $counts['pending'], $heads['pending'], '#b45309'],
        ['Đã duyệt', $counts['approved'], $heads['approved'], '#16a34a'],
        ['Từ chối', $counts['rejected'], $heads['rejected'], '#dc2626'],
    ];
    foreach ($cards as $c): ?>
        <div class="rc-card" style="margin:0">
            <div class="rc-muted"><?= $c[0] ?></div>
            <div style="font-size:30px;font-weight:700;color:<?= $c[3] ?>"><?= $c[1] ?></div>
            <div class="rc-muted"><?= (int)$c[2] ?> headcount</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="rc-card">
    <h3 style="font-size:15px;margin-bottom:10px">Nhu cầu tuyển theo phòng ban</h3>
    <?php if (!$byDept): ?>
        <div class="rc-muted">Chưa có yêu cầu tuyển dụng nào đang chờ duyệt hoặc đã duyệt.</div>
    <?php else: ?>
        <table class="rc-table">
            <thead><tr><th>Phòng ban</th><th>Số HRF</th><th>Headcount</th></tr></thead>
            <tbody>
            <?php foreach ($byDept as $d): ?>
                <tr>
                    <td><?= h($d['name'] ?: '(Chưa gán)') ?></td>
                    <td><?= (int)$d['c'] ?></td>
                    <td><b><?= (int)$d['q'] ?></b></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
<?php
hrm_footer();
