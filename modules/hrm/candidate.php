<?php
/**
 * Candidate detail - profile + applications + add to a job pipeline.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/history.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$id = (int)($_GET['id'] ?? 0);
$c = $conn->query("SELECT c.*, s.name AS source_name FROM hrm_candidates c LEFT JOIN hrm_candidate_sources s ON s.id=c.source_id WHERE c.id = $id")->fetch_assoc();
if (!$c) { hrm_header('Không tìm thấy', '', 'candidates'); echo '<div class="rc-empty">Ứng viên không tồn tại.</div>'; hrm_footer(); exit; }

$apps = $conn->query("SELECT a.id, a.status, a.applied_at, j.title AS job_title, st.name AS stage_name
        FROM hrm_applications a JOIN hrm_jobs j ON j.id=a.job_id LEFT JOIN hrm_pipeline_stages st ON st.id=a.stage_id
        WHERE a.candidate_id = $id ORDER BY a.id DESC")->fetch_all(MYSQLI_ASSOC);
$openJobs = $conn->query("SELECT id, title FROM hrm_jobs WHERE status IN ('open','draft') ORDER BY COALESCE(source_created,source_start) DESC, CAST(external_id AS UNSIGNED) DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
$sources = $conn->query("SELECT id, name FROM hrm_candidate_sources WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$appStatus = ['active' => 'Đang xử lý', 'hired' => 'Đã tuyển', 'rejected' => 'Từ chối', 'hold' => 'Giữ', 'withdrawn' => 'Rút'];

hrm_header($c['full_name'], $c['current_position'] ?: 'Ứng viên', 'candidates');
?>
<div class="rc-toolbar">
    <a href="/hrm/candidates" class="rc-tab">← Kho ứng viên</a>
    <button class="rc-btn ghost" onclick="document.getElementById('editCandModal').style.display='flex'">Sửa thông tin</button>
</div>

<div class="rc-card">
    <div class="rc-grid2">
        <div><div class="rc-muted">Họ tên</div><div><b><?= h($c['full_name']) ?></b></div></div>
        <div><div class="rc-muted">Nguồn</div><div><?= h($c['source_name'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Email</div><div><?= h($c['email'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Điện thoại</div><div><?= h($c['phone'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Vị trí gần nhất</div><div><?= h($c['current_position'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Giới tính</div><div><?= h($c['gender'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Ngày sinh</div><div><?= h($c['dob'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Điểm</div><div><?= (float)$c['score'] ?></div></div>
        <div><div class="rc-muted">Vị trí ứng tuyển (gốc)</div><div><?= h($c['applied_job'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Giai đoạn (gốc)</div><div><?= h($c['applied_stage'] ?: '-') ?></div></div>
    </div>
    <?php if ($c['cv_path']): ?><div style="margin-top:12px"><a class="rc-btn" href="<?= h($c['cv_path']) ?>" target="_blank" rel="noopener">Xem CV</a></div><?php endif; ?>
</div>

<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Hồ sơ ứng tuyển (pipeline)</h3>
    <?php if ($apps): ?>
        <table class="rc-table"><thead><tr><th>Vị trí</th><th>Giai đoạn</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php foreach ($apps as $a): ?>
            <tr>
                <td><?= h($a['job_title']) ?></td>
                <td><?= h($a['stage_name'] ?: '-') ?></td>
                <td><span class="rc-badge rc-b-<?= $a['status']==='hired'?'approved':($a['status']==='rejected'?'rejected':'pending') ?>"><?= h($appStatus[$a['status']] ?? $a['status']) ?></span></td>
                <td style="text-align:right"><a class="rc-btn ghost" style="padding:5px 12px" href="/hrm/application?id=<?= $a['id'] ?>">Mở hồ sơ (Test/PV/Offer)</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    <?php else: ?>
        <div class="rc-muted">Ứng viên chưa được đưa vào pipeline tin nào.</div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;align-items:flex-end;margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px">
        <div class="rc-field" style="margin:0;flex:1;max-width:420px"><label>Đưa vào tin tuyển dụng</label>
            <select id="jobSel"><option value="0">- Chọn tin -</option>
                <?php foreach ($openJobs as $j): ?><option value="<?= $j['id'] ?>"><?= h($j['title']) ?></option><?php endforeach; ?>
            </select></div>
        <button class="rc-btn" onclick="addToJob()">Đưa vào pipeline</button>
    </div>
</div>

<!-- LỊCH SỬ HOẠT ĐỘNG -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Lịch sử hoạt động</h3>
    <?php
    $appIds = array_map(fn($a) => (int)$a['id'], $apps);
    $offerIds = [];
    if ($appIds) {
        $or = $conn->query("SELECT id FROM hrm_offers WHERE application_id IN (" . implode(',', $appIds) . ")");
        while ($or && ($x = $or->fetch_assoc())) { $offerIds[] = (int)$x['id']; }
    }
    hrm_render_history(hrm_history_events($conn, $appIds, $id, $offerIds));
    ?>
</div>

<!-- Modal sửa thông tin ứng viên -->
<div id="editCandModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:520px;max-width:94vw;max-height:90vh;overflow:auto">
        <h3 style="font-size:15px;margin-bottom:12px">Sửa thông tin ứng viên</h3>
        <form id="editCandForm" onsubmit="return false">
            <div class="rc-grid2">
                <div class="rc-field"><label>Họ tên *</label><input name="full_name" required value="<?= h($c['full_name']) ?>"></div>
                <div class="rc-field"><label>Nguồn</label><select name="source_id"><option value="0">-</option>
                    <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$c['source_id']===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Email</label><input name="email" type="email" value="<?= h($c['email']) ?>"></div>
                <div class="rc-field"><label>Điện thoại</label><input name="phone" value="<?= h($c['phone']) ?>"></div>
                <div class="rc-field"><label>Vị trí gần nhất</label><input name="current_position" value="<?= h($c['current_position']) ?>"></div>
                <div class="rc-field"><label>Giới tính</label><input name="gender" value="<?= h($c['gender']) ?>" placeholder="Nam / Nữ"></div>
                <div class="rc-field"><label>Ngày sinh</label><input name="dob" value="<?= h($c['dob']) ?>" placeholder="dd/mm/yyyy"></div>
                <div class="rc-field"><label>Điểm</label><input name="score" type="number" step="0.1" value="<?= (float)$c['score'] ?>"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
                <button class="rc-btn ghost" onclick="document.getElementById('editCandModal').style.display='none'">Hủy</button>
                <button class="rc-btn" onclick="saveCand()">Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
function saveCand(){
    const f=document.getElementById('editCandForm');
    if(!f.full_name.value.trim()){alert('Nhập họ tên');return;}
    const fd=new FormData(f);fd.append('action','update_candidate');fd.append('candidate_id',<?= $id ?>);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function addToJob(){
    const jid=document.getElementById('jobSel').value;
    if(jid=='0'){alert('Chọn tin tuyển dụng');return;}
    const fd=new FormData();fd.append('action','add_candidate_to_job');fd.append('candidate_id',<?= $id ?>);fd.append('job_id',jid);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.href='/hrm/application?id='+j.id:alert(j.error||'Lỗi');});
}
</script>
<?php
hrm_footer();
