<?php
/**
 * Onboarding list + create from a hired application (SOP On-boarding 60 days).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

// Hired applications not yet onboarded.
$readyHire = $conn->query("SELECT a.id, c.full_name, j.title, j.level
        FROM hrm_applications a
        JOIN hrm_candidates c ON c.id=a.candidate_id
        JOIN hrm_jobs j ON j.id=a.job_id
        WHERE a.status='hired' AND a.id NOT IN (SELECT application_id FROM hrm_onboarding WHERE application_id>0)
        ORDER BY a.updated_at DESC")->fetch_all(MYSQLI_ASSOC);

$rows = $conn->query("SELECT o.*,
        (SELECT COUNT(*) FROM hrm_onboarding_tasks t WHERE t.onboarding_id=o.id) AS n_tasks,
        (SELECT COUNT(*) FROM hrm_onboarding_tasks t WHERE t.onboarding_id=o.id AND t.done=1) AS n_done,
        (SELECT COUNT(*) FROM hrm_checkpoints c WHERE c.onboarding_id=o.id AND c.status='done') AS cp_done
        FROM hrm_onboarding o ORDER BY o.id DESC")->fetch_all(MYSQLI_ASSOC);

$statusLabel = ['preboarding' => 'Chuẩn bị', 'active' => 'Đang onboard', 'completed' => 'Hoàn thành', 'left' => 'Nghỉ'];

hrm_header('Onboarding', 'Hội nhập nhân sự mới 60 ngày', 'onboarding');
?>
<?php if ($readyHire): ?>
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Ứng viên đã tuyển - tạo onboarding</h3>
    <table class="rc-table"><tbody>
    <?php foreach ($readyHire as $a): ?>
        <tr>
            <td><b><?= h($a['full_name']) ?></b></td>
            <td><?= h($a['title']) ?><?= $a['level'] ? ' · ' . h($a['level']) : '' ?></td>
            <td style="text-align:right"><button class="rc-btn" onclick="createOnb(<?= $a['id'] ?>)">Tạo onboarding</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="rc-empty">Chưa có nhân sự onboarding. Tạo từ một ứng viên đã tuyển ở trên.</div>
<?php else: ?>
<table class="rc-table">
    <thead><tr><th>Nhân sự</th><th>Vị trí</th><th>Ngày bắt đầu</th><th>Công việc chuẩn bị</th><th>Checkpoint</th><th>Trạng thái</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): ?>
        <tr onclick="location.href='/hrm/onboarding-detail?id=<?= $o['id'] ?>'" style="cursor:pointer">
            <td><b><?= h($o['candidate_name']) ?></b></td>
            <td><?= h($o['job_title'] ?: '-') ?></td>
            <td><?= $o['start_date'] ? date('d/m/Y', strtotime($o['start_date'])) : '-' ?></td>
            <td><?= (int)$o['n_done'] ?>/<?= (int)$o['n_tasks'] ?></td>
            <td><?= (int)$o['cp_done'] ?>/6</td>
            <td><span class="rc-badge rc-b-<?= $o['status']==='completed'?'approved':($o['status']==='left'?'rejected':'pending') ?>"><?= h($statusLabel[$o['status']] ?? $o['status']) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
function createOnb(appId){
    const fd=new FormData();fd.append('action','create_onboarding');fd.append('application_id',appId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok)location.href='/hrm/onboarding-detail?id='+j.id;else alert(j.error||'Lỗi');
    });
}
</script>
<?php
hrm_footer();
