<?php
/**
 * Hồ sơ ứng viên 360 - tabs: tổng quan, kỹ năng/KN/học vấn, tệp, ứng tuyển, hoạt động.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/history.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$id = (int)($_GET['id'] ?? 0);
$c = $conn->query("SELECT c.*, s.name AS source_name, ev.name AS event_name, u.full_name AS owner_name
    FROM hrm_candidates c
    LEFT JOIN hrm_candidate_sources s ON s.id=c.source_id
    LEFT JOIN hrm_events ev ON ev.id=c.event_id
    LEFT JOIN users u ON u.id=c.owner_id WHERE c.id=$id")->fetch_assoc();
if (!$c) { hrm_header('Không tìm thấy', '', 'candidates'); echo '<div class="rc-empty">Ứng viên không tồn tại.</div>'; hrm_footer(); exit; }

$tags     = array_column($conn->query("SELECT tag FROM hrm_candidate_tags WHERE candidate_id=$id ORDER BY tag")->fetch_all(MYSQLI_ASSOC), 'tag');
$skills   = $conn->query("SELECT * FROM hrm_candidate_skills WHERE candidate_id=$id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$exps     = $conn->query("SELECT * FROM hrm_candidate_experience WHERE candidate_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$edus     = $conn->query("SELECT * FROM hrm_candidate_education WHERE candidate_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$attach   = $conn->query("SELECT * FROM hrm_candidate_attachments WHERE candidate_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$acts     = $conn->query("SELECT a.*, u.full_name AS actor FROM hrm_candidate_activities a LEFT JOIN users u ON u.id=a.actor_id WHERE a.candidate_id=$id ORDER BY a.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$reminders= $conn->query("SELECT r.*, u.full_name AS owner FROM hrm_candidate_reminders r LEFT JOIN users u ON u.id=r.owner_id WHERE r.candidate_id=$id AND r.done=0 ORDER BY r.due_at")->fetch_all(MYSQLI_ASSOC);
$allPools = $conn->query("SELECT id,name,color FROM hrm_pools WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$candPools = $conn->query("SELECT p.id,p.name,p.color FROM hrm_candidate_pools cp JOIN hrm_pools p ON p.id=cp.pool_id WHERE cp.candidate_id=$id AND p.active=1 ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$candPoolIds = array_column($candPools, 'id');
$apps = $conn->query("SELECT a.id, a.status, a.applied_at, j.title AS job_title, st.name AS stage_name
        FROM hrm_applications a JOIN hrm_jobs j ON j.id=a.job_id LEFT JOIN hrm_pipeline_stages st ON st.id=a.stage_id
        WHERE a.candidate_id = $id ORDER BY a.id DESC")->fetch_all(MYSQLI_ASSOC);
$openJobs = $conn->query("SELECT id, title FROM hrm_jobs WHERE status IN ('open','draft') ORDER BY COALESCE(source_created,source_start) DESC, CAST(external_id AS UNSIGNED) DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
$sources = $conn->query("SELECT id, name FROM hrm_candidate_sources WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$events  = $conn->query("SELECT id, name FROM hrm_events WHERE active=1 ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$users   = $conn->query("SELECT id, full_name FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$statuses = hrm_candidate_statuses();
$appStatus = ['active'=>'Đang xử lý','hired'=>'Đã tuyển','rejected'=>'Từ chối','hold'=>'Giữ','withdrawn'=>'Rút'];
$actLabel = ['note'=>'Ghi chú','call'=>'Cuộc gọi','email'=>'Email','meeting'=>'Gặp mặt','create'=>'Tạo','update'=>'Cập nhật','stage'=>'Pipeline'];

hrm_header($c['full_name'], ($c['current_position'] ?: 'Ứng viên') . ' · ' . ($statuses[$c['status']] ?? $c['status']), 'candidates');
?>
<div class="rc-toolbar">
    <a href="/hrm/candidates" class="rc-tab">← Kho ứng viên</a>
    <div style="display:flex;gap:8px">
        <button class="rc-btn ghost" onclick="openHistory()">Lịch sử hệ thống</button>
        <button class="rc-btn ghost" onclick="show('edit')">Sửa thông tin</button>
    </div>
</div>

<div class="cd-tabs">
    <button class="cd-tab on" data-t="overview" onclick="tab(this)">Tổng quan</button>
    <button class="cd-tab" data-t="profile" onclick="tab(this)">Kỹ năng & kinh nghiệm</button>
    <button class="cd-tab" data-t="files" onclick="tab(this)">Tệp đính kèm (<?= count($attach) ?>)</button>
    <button class="cd-tab" data-t="apps" onclick="tab(this)">Ứng tuyển (<?= count($apps) ?>)</button>
    <button class="cd-tab" data-t="timeline" onclick="tab(this)">Hoạt động</button>
</div>

<!-- ── TAB: TỔNG QUAN ── -->
<div class="cd-pane" id="p-overview">
    <div class="rc-card">
        <div class="rc-grid2">
            <div><div class="rc-muted">Họ tên</div><div><b><?= h($c['full_name']) ?></b></div></div>
            <div><div class="rc-muted">Trạng thái</div><div><?= h($statuses[$c['status']] ?? $c['status']) ?><?= $c['talent_pool'] ? ' · <b style="color:#7c3aed">Talent pool</b>' : '' ?></div></div>
            <div><div class="rc-muted">Email</div><div><?= h($c['email'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Điện thoại</div><div><?= h($c['phone'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Vị trí gần nhất</div><div><?= h($c['current_position'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Khu vực</div><div><?= h($c['location'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Số năm KN</div><div><?= (float)$c['years_exp'] ?: '-' ?></div></div>
            <div><div class="rc-muted">Lương kỳ vọng</div><div><?= h($c['expected_salary'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Nguồn</div><div><?= h($c['source_name'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Sự kiện</div><div><?= h($c['event_name'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Người phụ trách</div><div><?= h($c['owner_name'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Đánh giá</div><div><?= (int)$c['rating'] ? str_repeat('★', (int)$c['rating']) : '-' ?></div></div>
            <div><div class="rc-muted">LinkedIn</div><div><?= $c['linkedin_url'] ? '<a href="'.h($c['linkedin_url']).'" target="_blank" rel="noopener">Hồ sơ</a>' : '-' ?></div></div>
            <div><div class="rc-muted">Portfolio</div><div><?= $c['portfolio_url'] ? '<a href="'.h($c['portfolio_url']).'" target="_blank" rel="noopener">Link</a>' : '-' ?></div></div>
            <div><div class="rc-muted">Số CMND/CCCD</div><div><?= h($c['id_card'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Phân loại</div><div><?= h($c['classification'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Chiến dịch</div><div><?= h($c['campaign'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Ngày ứng tuyển</div><div><?= $c['applied_date'] ? date('d/m/Y', strtotime($c['applied_date'])) : '-' ?></div></div>
            <div><div class="rc-muted">Vị trí ứng tuyển (gốc)</div><div><?= h($c['applied_job'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Giai đoạn (gốc)</div><div><?= h($c['applied_stage'] ?: '-') ?></div></div>
            <?php if (!empty($c['reject_reason'])): ?><div><div class="rc-muted">Lý do từ chối</div><div><?= h($c['reject_reason']) ?></div></div><?php endif; ?>
            <?php if (!empty($c['office_text'])): ?><div><div class="rc-muted">Văn phòng (text)</div><div><?= h($c['office_text']) ?></div></div><?php endif; ?>
        </div>
        <div style="margin-top:14px"><div class="rc-muted" style="margin-bottom:6px">Thẻ (tags)</div>
            <div id="tagBox">
                <?php foreach ($tags as $t): ?><span class="cd-tag"><?= h($t) ?> <a href="#" onclick="delTag('<?= h(addslashes($t)) ?>');return false">×</a></span><?php endforeach; ?>
            </div>
            <div style="margin-top:8px;display:flex;gap:8px"><input id="newTag" class="cd-in" placeholder="Thêm thẻ..." style="width:160px"><button class="rc-btn ghost" onclick="addTag()">Thêm thẻ</button></div>
        </div>
        <div style="margin-top:14px"><div class="rc-muted" style="margin-bottom:6px">Talent pools <a href="/hrm/pools" style="font-size:11px;margin-left:6px">Quản lý</a></div>
            <div id="poolBox">
                <?php foreach ($candPools as $p): ?><span class="cd-tag" style="background:<?= h($p['color']) ?>1a;color:<?= h($p['color']) ?>"><?= h($p['name']) ?> <a href="#" style="color:inherit" onclick="delPool(<?= (int)$p['id'] ?>);return false">×</a></span><?php endforeach; ?>
                <?php if (!$candPools): ?><span class="rc-muted" style="font-size:12px">Chưa thuộc pool nào.</span><?php endif; ?>
            </div>
            <div style="margin-top:8px;display:flex;gap:8px">
                <select id="poolSel" class="cd-in" style="width:200px"><option value="">+ Thêm vào pool...</option>
                    <?php foreach ($allPools as $p): if (in_array((int)$p['id'], $candPoolIds, true)) continue; ?><option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach; ?>
                </select>
                <button class="rc-btn ghost" onclick="addPool()">Thêm</button>
            </div>
        </div>
        <?php if ($c['notes']): ?><div style="margin-top:14px"><div class="rc-muted">Ghi chú</div><div><?= nl2br(h($c['notes'])) ?></div></div><?php endif; ?>
    </div>

    <div class="rc-card">
        <h3 class="cd-h3">Nhắc việc (follow-up)</h3>
        <div id="remList">
        <?php if ($reminders): foreach ($reminders as $r): ?>
            <div class="cd-rem" id="rem<?= $r['id'] ?>"><span><b><?= date('d/m H:i', strtotime($r['due_at'])) ?></b> · <?= h($r['note'] ?: '(không ghi chú)') ?> <span class="rc-muted">— <?= h($r['owner'] ?: '') ?></span></span>
                <button class="rc-btn ghost" style="padding:3px 10px" onclick="remDone(<?= $r['id'] ?>)">Hoàn tất</button></div>
        <?php endforeach; else: ?><div class="rc-muted">Chưa có nhắc việc.</div><?php endif; ?>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"><input type="datetime-local" id="remDue" class="cd-in"><input id="remNote" class="cd-in" placeholder="Nội dung nhắc" style="flex:1;min-width:180px"><button class="rc-btn" onclick="addRem()">+ Đặt nhắc</button></div>
    </div>
</div>

<!-- ── TAB: KỸ NĂNG & KINH NGHIỆM ── -->
<div class="cd-pane" id="p-profile" style="display:none">
    <div class="rc-card">
        <h3 class="cd-h3">Kỹ năng</h3>
        <div id="skillBox" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
            <?php foreach ($skills as $s): ?><span class="cd-tag" id="sk<?= $s['id'] ?>"><?= h($s['skill']) ?><?= $s['level']?' · '.h($s['level']):'' ?> <a href="#" onclick="delSkill(<?= $s['id'] ?>);return false">×</a></span><?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><input id="skName" class="cd-in" placeholder="Kỹ năng (vd PHP)"><input id="skLevel" class="cd-in" placeholder="Mức (vd Senior)" style="width:140px"><button class="rc-btn ghost" onclick="addSkill()">+ Thêm</button></div>
    </div>
    <div class="rc-card">
        <h3 class="cd-h3">Kinh nghiệm làm việc</h3>
        <div id="expBox">
            <?php foreach ($exps as $e): ?><div class="cd-item" id="ex<?= $e['id'] ?>"><div><b><?= h($e['title']) ?></b><?= $e['company']?' @ '.h($e['company']):'' ?> <span class="rc-muted"><?= h($e['period']) ?></span><?= $e['summary']?'<div class="cd-sub">'.nl2br(h($e['summary'])).'</div>':'' ?></div><a href="#" onclick="delExp(<?= $e['id'] ?>);return false" class="cd-x">×</a></div><?php endforeach; ?>
        </div>
        <div class="rc-grid2" style="margin-top:8px"><input id="exTitle" class="cd-in" placeholder="Chức danh"><input id="exCompany" class="cd-in" placeholder="Công ty"><input id="exPeriod" class="cd-in" placeholder="Thời gian (2020-2023)"><input id="exSummary" class="cd-in" placeholder="Mô tả ngắn"></div>
        <button class="rc-btn ghost" style="margin-top:8px" onclick="addExp()">+ Thêm kinh nghiệm</button>
    </div>
    <div class="rc-card">
        <h3 class="cd-h3">Học vấn</h3>
        <div id="eduBox">
            <?php foreach ($edus as $e): ?><div class="cd-item" id="ed<?= $e['id'] ?>"><div><b><?= h($e['degree']) ?></b><?= $e['major']?' · '.h($e['major']):'' ?><?= $e['school']?' — '.h($e['school']):'' ?> <span class="rc-muted"><?= h($e['grad_year']) ?></span></div><a href="#" onclick="delEdu(<?= $e['id'] ?>);return false" class="cd-x">×</a></div><?php endforeach; ?>
        </div>
        <div class="rc-grid2" style="margin-top:8px"><input id="edSchool" class="cd-in" placeholder="Trường"><input id="edDegree" class="cd-in" placeholder="Bằng cấp"><input id="edMajor" class="cd-in" placeholder="Chuyên ngành"><input id="edYear" class="cd-in" placeholder="Năm TN"></div>
        <button class="rc-btn ghost" style="margin-top:8px" onclick="addEdu()">+ Thêm học vấn</button>
    </div>
</div>

<!-- ── TAB: TỆP ── -->
<div class="cd-pane" id="p-files" style="display:none">
    <div class="rc-card">
        <h3 class="cd-h3">Tệp đính kèm (CV, chứng chỉ, portfolio)</h3>
        <table class="rc-table" id="fileTable"><thead><tr><th>Tên</th><th>Loại</th><th>Ngày</th><th></th></tr></thead><tbody>
        <?php foreach ($attach as $a): ?><tr id="att<?= $a['id'] ?>"><td><a href="<?= h($a['file_path']) ?>" target="_blank" rel="noopener"><?= h($a['label']) ?></a></td><td><?= h($a['type']) ?></td><td class="cd-sub"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td><td style="text-align:right"><a href="#" class="cd-x" onclick="delAtt(<?= $a['id'] ?>);return false">×</a></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php if (!$attach): ?><div class="rc-muted">Chưa có tệp nào.</div><?php endif; ?>
        <form id="attForm" onsubmit="return false" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="file" name="file" required>
            <select name="type" class="cd-in"><option value="cv">CV</option><option value="cert">Chứng chỉ</option><option value="portfolio">Portfolio</option><option value="other">Khác</option></select>
            <input name="label" class="cd-in" placeholder="Nhãn (tùy chọn)">
            <button class="rc-btn" onclick="upAtt()">Tải lên</button>
        </form>
    </div>
</div>

<!-- ── TAB: ỨNG TUYỂN ── -->
<div class="cd-pane" id="p-apps" style="display:none">
    <div class="rc-card">
        <h3 class="cd-h3">Hồ sơ ứng tuyển (pipeline)</h3>
        <?php if ($apps): ?>
        <table class="rc-table"><thead><tr><th>Vị trí</th><th>Giai đoạn</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php foreach ($apps as $a): ?>
            <tr><td><?= h($a['job_title']) ?></td><td><?= h($a['stage_name'] ?: '-') ?></td>
                <td><span class="rc-badge rc-b-<?= $a['status']==='hired'?'approved':($a['status']==='rejected'?'rejected':'pending') ?>"><?= h($appStatus[$a['status']] ?? $a['status']) ?></span></td>
                <td style="text-align:right"><a class="rc-btn ghost" style="padding:5px 12px" href="/hrm/application?id=<?= $a['id'] ?>">Mở hồ sơ</a></td></tr>
        <?php endforeach; ?></tbody></table>
        <?php else: ?><div class="rc-muted">Chưa được đưa vào pipeline tin nào.</div><?php endif; ?>
        <div style="display:flex;gap:8px;align-items:flex-end;margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px">
            <div class="rc-field" style="margin:0;flex:1;max-width:420px"><label>Đưa vào tin tuyển dụng</label>
                <select id="jobSel"><option value="0">- Chọn tin -</option>
                    <?php foreach ($openJobs as $j): ?><option value="<?= $j['id'] ?>"><?= h($j['title']) ?></option><?php endforeach; ?></select></div>
            <button class="rc-btn" onclick="addToJob()">Đưa vào pipeline</button>
        </div>
    </div>
</div>

<!-- ── TAB: HOẠT ĐỘNG ── -->
<div class="cd-pane" id="p-timeline" style="display:none">
    <div class="rc-card">
        <h3 class="cd-h3">Thêm ghi chú / hoạt động</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><select id="actType" class="cd-in"><option value="note">Ghi chú</option><option value="call">Cuộc gọi</option><option value="email">Email</option><option value="meeting">Gặp mặt</option></select>
            <input id="actBody" class="cd-in" placeholder="Nội dung..." style="flex:1;min-width:240px"><button class="rc-btn" onclick="addAct()">Lưu</button></div>
    </div>
    <div class="rc-card">
        <h3 class="cd-h3">Dòng thời gian</h3>
        <div id="actBox" class="cd-timeline">
        <?php if ($acts): foreach ($acts as $a): ?>
            <div class="cd-tl"><div class="cd-tl-dot"></div><div><div><span class="cd-badge"><?= h($actLabel[$a['type']] ?? $a['type']) ?></span> <?= nl2br(h($a['body'])) ?></div>
                <div class="cd-sub"><?= h($a['actor'] ?: 'Hệ thống') ?> · <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></div></div></div>
        <?php endforeach; else: ?><div class="rc-muted">Chưa có hoạt động.</div><?php endif; ?>
        </div>
    </div>
</div>

<?php
$appIds = array_map(fn($a) => (int)$a['id'], $apps);
$offerIds = [];
if ($appIds) {
    $or = $conn->query("SELECT id FROM hrm_offers WHERE application_id IN (" . implode(',', $appIds) . ")");
    while ($or && ($x = $or->fetch_assoc())) { $offerIds[] = (int)$x['id']; }
}
hrm_history_sidebar(hrm_history_events($conn, $appIds, $id, $offerIds));
?>

<!-- Modal sửa thông tin -->
<div id="editModal" class="cd-modal">
    <div class="rc-card" style="width:640px;max-width:96vw;max-height:90vh;overflow:auto">
        <h3 style="font-size:15px;margin-bottom:12px">Sửa thông tin ứng viên</h3>
        <form id="editForm" onsubmit="return false">
            <div class="rc-grid2">
                <div class="rc-field"><label>Họ tên *</label><input name="full_name" required value="<?= h($c['full_name']) ?>"></div>
                <div class="rc-field"><label>Trạng thái</label><select name="status"><?php foreach ($statuses as $k=>$lbl): ?><option value="<?= $k ?>"<?= $c['status']===$k?' selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Email</label><input name="email" type="email" value="<?= h($c['email']) ?>"></div>
                <div class="rc-field"><label>Điện thoại</label><input name="phone" value="<?= h($c['phone']) ?>"></div>
                <div class="rc-field"><label>Vị trí gần nhất</label><input name="current_position" value="<?= h($c['current_position']) ?>"></div>
                <div class="rc-field"><label>Khu vực</label><input name="location" value="<?= h($c['location']) ?>"></div>
                <div class="rc-field"><label>Số năm KN</label><input name="years_exp" type="number" step="0.5" value="<?= (float)$c['years_exp'] ?>"></div>
                <div class="rc-field"><label>Lương kỳ vọng</label><input name="expected_salary" value="<?= h($c['expected_salary']) ?>"></div>
                <div class="rc-field"><label>Đánh giá (0-5)</label><input name="rating" type="number" min="0" max="5" value="<?= (int)$c['rating'] ?>"></div>
                <div class="rc-field"><label>Nguồn</label><select name="source_id"><option value="0">-</option><?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$c['source_id']===(int)$s['id']?' selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Sự kiện</label><select name="event_id"><option value="0">-</option><?php foreach ($events as $e): ?><option value="<?= $e['id'] ?>"<?= (int)$c['event_id']===(int)$e['id']?' selected':'' ?>><?= h($e['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Người phụ trách</label><select name="owner_id"><option value="0">-</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= (int)$c['owner_id']===(int)$u['id']?' selected':'' ?>><?= h($u['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>LinkedIn URL</label><input name="linkedin_url" value="<?= h($c['linkedin_url']) ?>"></div>
                <div class="rc-field"><label>Portfolio URL</label><input name="portfolio_url" value="<?= h($c['portfolio_url']) ?>"></div>
            </div>
            <div class="rc-field"><label>Ghi chú</label><textarea name="notes" rows="3"><?= h($c['notes']) ?></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:8px"><button type="button" class="rc-btn ghost" onclick="document.getElementById('editModal').style.display='none'">Hủy</button><button type="button" class="rc-btn" onclick="saveCand()">Lưu</button></div>
        </form>
    </div>
</div>

<style>
.cd-tabs{display:flex;gap:4px;border-bottom:1px solid #e5e9ef;margin-bottom:16px;flex-wrap:wrap}
.cd-tab{padding:9px 16px;background:none;border:none;border-bottom:2px solid transparent;font-size:13.5px;font-weight:500;color:#64748b;cursor:pointer}
.cd-tab.on{color:#0071e3;border-bottom-color:#0071e3}
.cd-h3{font-size:14px;margin-bottom:10px}
.cd-in{padding:8px 11px;border:1px solid var(--bd);border-radius:8px;font-size:13px;background:#fff}
.cd-tag{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:4px 10px;border-radius:980px;background:#eef6ff;color:#0071e3;margin:2px}
.cd-tag a{color:#94a3b8;text-decoration:none;font-weight:700}
.cd-item{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9}
.cd-x{color:#dc2626;text-decoration:none;font-weight:700}
.cd-sub{font-size:12px;color:#86868b;margin-top:2px}
.cd-rem{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
.cd-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center}
.cd-timeline{position:relative}
.cd-tl{display:flex;gap:12px;padding:0 0 16px 0;position:relative}
.cd-tl-dot{width:10px;height:10px;border-radius:50%;background:#0071e3;flex-shrink:0;margin-top:5px;box-shadow:0 0 0 3px #eef6ff}
</style>
<script>
const CID = <?= $id ?>;
function tab(b){document.querySelectorAll('.cd-tab').forEach(x=>x.classList.remove('on'));b.classList.add('on');
    document.querySelectorAll('.cd-pane').forEach(p=>p.style.display='none');document.getElementById('p-'+b.dataset.t).style.display='block';}
function show(w){if(w==='edit')document.getElementById('editModal').style.display='flex';}
function post(data,cb){const fd=new FormData();for(const k in data)fd.append(k,data[k]);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?(cb?cb(j):location.reload()):alert(j.error||'Lỗi');});}
function saveCand(){const f=document.getElementById('editForm');if(!f.full_name.value.trim()){alert('Nhập họ tên');return;}
    const fd=new FormData(f);fd.append('action','update_candidate');fd.append('candidate_id',CID);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function addTag(){const v=document.getElementById('newTag').value.trim();if(!v)return;post({action:'cand_tag_add',candidate_id:CID,tag:v});}
function addPool(){const v=document.getElementById('poolSel').value;if(!v){alert('Chọn pool');return;}post({action:'cand_pool_add',candidate_id:CID,pool_id:v});}
function delPool(pid){post({action:'cand_pool_del',candidate_id:CID,pool_id:pid});}
function delTag(t){post({action:'cand_tag_del',candidate_id:CID,tag:t});}
function addSkill(){const n=document.getElementById('skName').value.trim();if(!n)return;post({action:'cand_skill_add',candidate_id:CID,skill:n,level:document.getElementById('skLevel').value.trim()});}
function delSkill(id){post({action:'cand_skill_del',id:id});}
function addExp(){const t=document.getElementById('exTitle').value.trim();if(!t){alert('Nhập chức danh');return;}
    post({action:'cand_exp_add',candidate_id:CID,title:t,company:document.getElementById('exCompany').value.trim(),period:document.getElementById('exPeriod').value.trim(),summary:document.getElementById('exSummary').value.trim()});}
function delExp(id){post({action:'cand_exp_del',id:id});}
function addEdu(){const s=document.getElementById('edSchool').value.trim();if(!s){alert('Nhập trường');return;}
    post({action:'cand_edu_add',candidate_id:CID,school:s,degree:document.getElementById('edDegree').value.trim(),major:document.getElementById('edMajor').value.trim(),grad_year:document.getElementById('edYear').value.trim()});}
function delEdu(id){post({action:'cand_edu_del',id:id});}
function delAtt(id){if(confirm('Xóa tệp này?'))post({action:'cand_attach_del',id:id});}
function upAtt(){const f=document.getElementById('attForm');if(!f.file.files.length){alert('Chọn file');return;}
    const fd=new FormData(f);fd.append('action','cand_attach_add');fd.append('candidate_id',CID);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function addAct(){const b=document.getElementById('actBody').value.trim();if(!b)return;post({action:'cand_activity_add',candidate_id:CID,type:document.getElementById('actType').value,body:b});}
function addRem(){const d=document.getElementById('remDue').value;if(!d){alert('Chọn thời gian');return;}
    post({action:'cand_reminder_add',candidate_id:CID,due_at:d.replace('T',' ')+':00',note:document.getElementById('remNote').value.trim()});}
function remDone(id){post({action:'cand_reminder_done',reminder_id:id});}
function addToJob(){const jid=document.getElementById('jobSel').value;if(jid=='0'){alert('Chọn tin tuyển dụng');return;}
    post({action:'add_candidate_to_job',candidate_id:CID,job_id:jid},j=>location.href='/hrm/application?id='+j.id);}
</script>
<?php
hrm_footer();
