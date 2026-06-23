<?php
/**
 * Recruitment settings (admin) - recruitment-role assignment, email templates,
 * channel toggles. Phase 1 essentials; expands in later phases.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

if (($_SESSION['role'] ?? '') !== 'admin') {
    hrm_header('Cấu hình', '', 'offices'); echo '<div class="rc-empty">Chỉ admin truy cập được trang này.</div>'; hrm_footer(); exit;
}

$roleLabels = hrm_roles();
$users = $conn->query("SELECT id, full_name, email FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$assignments = $conn->query("SELECT ra.id, ra.user_id, ra.rec_role, u.full_name FROM hrm_role_assignments ra JOIN users u ON u.id=ra.user_id ORDER BY ra.rec_role, u.full_name")->fetch_all(MYSQLI_ASSOC);
$byRole = [];
foreach ($assignments as $a) { $byRole[$a['rec_role']][] = $a; }
$templates = $conn->query("SELECT * FROM hrm_email_templates ORDER BY audience, event_key")->fetch_all(MYSQLI_ASSOC);
require_once __DIR__ . '/../../includes/EmailSenders.php';
$emailSenders = EmailSenders::all($conn);
$hrmSenderSel  = hrm_setting($conn, 'hrm_email_sender', '');
$hrmSenderCand = hrm_setting($conn, 'hrm_email_sender_candidate', '');
$hrmSenderInt  = hrm_setting($conn, 'hrm_email_sender_internal', '');
// Render options cho 1 dropdown sender.
$senderOpts = function ($sel, $emptyLabel) use ($emailSenders) {
    $h = '<option value="">' . $emptyLabel . '</option>';
    foreach ($emailSenders as $s) {
        $lbl = h($s['name'] . ' · ' . $s['from_email']) . (empty($s['smtp_pass']) ? ' (chưa cấu hình)' : '');
        $h .= '<option value="' . (int)$s['id'] . '"' . ((string)$sel === (string)$s['id'] ? ' selected' : '') . '>' . $lbl . '</option>';
    }
    return $h;
};
$offices = $conn->query("SELECT id, name, address, active FROM hrm_offices ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);

$titles = ['offices' => 'Văn phòng', 'pipeline' => 'Giai đoạn & SLA', 'owners' => 'Phụ trách giai đoạn', 'roles' => 'Vai trò tuyển dụng', 'email' => 'Email template', 'channels' => 'Kênh thông báo', 'channels_cfg' => 'Kênh đăng tin'];
$stages = $conn->query("SELECT id,code,name,sla_hours,sort_order FROM hrm_pipeline_stages ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$deptList = $conn->query("SELECT id,name FROM departments ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$ownerDept = (int)($_GET['dept'] ?? ($deptList[0]['id'] ?? 0));
$ownerMap = [];
if ($ownerDept) {
    $r = $conn->query("SELECT stage_id, owner_type, user_id FROM hrm_stage_owners WHERE department_id = $ownerDept");
    while ($x = $r->fetch_assoc()) { $ownerMap[(int)$x['stage_id']][$x['owner_type']] = (int)$x['user_id']; }
}
$tab = $_GET['tab'] ?? 'offices';
if (!isset($titles[$tab])) { $tab = 'offices'; }
hrm_header('Cấu hình · ' . $titles[$tab], 'Thiết lập HRM', $tab);
?>

<?php if ($tab === 'offices'): ?>
<div class="rc-card" style="max-width:720px">
    <h3 style="font-size:14px;margin-bottom:10px">Văn phòng làm việc <span class="rc-muted">(<?= count($offices) ?>)</span></h3>
    <div style="margin-bottom:14px">
        <div class="rc-field" style="margin-bottom:8px"><label>Tên văn phòng</label><input id="offName" placeholder="VD: AHT TECH HEAD OFFICE"></div>
        <div class="rc-field" style="margin-bottom:8px"><label>Địa chỉ</label><input id="offAddr" placeholder="Số nhà, đường, phường, thành phố"></div>
        <button class="rc-btn" onclick="addOffice()">Thêm văn phòng</button>
    </div>
    <?php if (!$offices): ?><div class="rc-muted">Chưa có văn phòng nào.</div>
    <?php else: ?>
    <div class="rc-muted" style="margin-bottom:8px">Kéo <b>⠿</b> để sắp xếp · bấm <b>Sửa</b> để chỉnh tên/địa chỉ.</div>
    <div id="offList">
        <?php foreach ($offices as $o): ?>
        <div class="off-row" draggable="true" data-id="<?= $o['id'] ?>">
            <span class="off-handle" title="Kéo để sắp xếp">⠿</span>
            <div class="off-body">
                <div class="off-view">
                    <div class="off-name"><?= h($o['name']) ?></div>
                    <div class="off-addr rc-muted"><?= h($o['address']) ?></div>
                </div>
                <div class="off-edit" style="display:none">
                    <input class="off-i-name" value="<?= h($o['name']) ?>" placeholder="Tên văn phòng">
                    <input class="off-i-addr" value="<?= h($o['address']) ?>" placeholder="Địa chỉ">
                </div>
            </div>
            <div class="off-actions">
                <a href="#" class="off-a-edit" onclick="editRow(this);return false">Sửa</a>
                <a href="#" class="off-a-save" style="display:none" onclick="saveRow(this);return false">Lưu</a>
                <a href="#" class="off-a-cancel" style="display:none" onclick="cancelRow(this);return false">Hủy</a>
                <a href="#" onclick="rmOffice(<?= $o['id'] ?>);return false" style="color:#dc2626">Xóa</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<style>
.off-row{display:flex;align-items:flex-start;gap:12px;padding:11px 12px;border:1px solid var(--bd);border-radius:8px;margin-bottom:8px;background:#fff;transition:.12s}
.off-row.dragging{opacity:.4}
.off-row.over{border-color:var(--rc2);box-shadow:0 0 0 2px rgba(14,107,92,.15)}
.off-handle{cursor:grab;color:#cbd5e1;font-size:18px;line-height:1.2;user-select:none;padding-top:1px}
.off-handle:active{cursor:grabbing}
.off-body{flex:1;min-width:0}
.off-name{font-weight:600;font-size:13.5px;color:#0f172a}
.off-addr{margin-top:2px}
.off-edit input{width:100%;padding:7px 10px;border:1px solid var(--bd);border-radius:7px;font-size:13px;margin-bottom:6px;font-family:inherit}
.off-actions{display:flex;gap:10px;flex-shrink:0;font-size:12.5px;font-weight:600;white-space:nowrap}
.off-actions a{text-decoration:none;color:#475569}
</style>
<script>
function post(a,d){const fd=new FormData();fd.append('action',a);for(const k in d)fd.append(k,d[k]);return fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json());}
function addOffice(){const n=document.getElementById('offName').value.trim();if(!n){alert('Nhập tên văn phòng');return;}
    post('save_office',{name:n,address:document.getElementById('offAddr').value.trim()}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function rmOffice(id){if(confirm('Xóa văn phòng này?'))post('remove_office',{id:id}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}

function editRow(a){const r=a.closest('.off-row');r.querySelector('.off-view').style.display='none';r.querySelector('.off-edit').style.display='block';
    a.style.display='none';r.querySelector('.off-a-save').style.display='';r.querySelector('.off-a-cancel').style.display='';r.draggable=false;}
function cancelRow(a){const r=a.closest('.off-row');r.querySelector('.off-view').style.display='';r.querySelector('.off-edit').style.display='none';
    r.querySelector('.off-a-edit').style.display='';r.querySelector('.off-a-save').style.display='none';a.style.display='none';r.draggable=true;
    r.querySelector('.off-i-name').value=r.querySelector('.off-name').textContent;r.querySelector('.off-i-addr').value=r.querySelector('.off-addr').textContent;}
function saveRow(a){const r=a.closest('.off-row');const name=r.querySelector('.off-i-name').value.trim();if(!name){alert('Nhập tên');return;}
    const addr=r.querySelector('.off-i-addr').value.trim();
    post('update_office',{id:r.dataset.id,name:name,address:addr}).then(j=>{if(!j.ok){alert(j.error||'Lỗi');return;}
        r.querySelector('.off-name').textContent=name;r.querySelector('.off-addr').textContent=addr;
        r.querySelector('.off-view').style.display='';r.querySelector('.off-edit').style.display='none';
        r.querySelector('.off-a-edit').style.display='';r.querySelector('.off-a-save').style.display='none';r.querySelector('.off-a-cancel').style.display='none';r.draggable=true;});}

// Drag-drop reorder
(function(){
    const list=document.getElementById('offList');if(!list)return;let dragEl=null;
    list.addEventListener('dragstart',e=>{const row=e.target.closest('.off-row');if(!row||!row.draggable)return;dragEl=row;row.classList.add('dragging');});
    list.addEventListener('dragend',()=>{if(dragEl)dragEl.classList.remove('dragging');list.querySelectorAll('.over').forEach(x=>x.classList.remove('over'));dragEl=null;});
    list.addEventListener('dragover',e=>{e.preventDefault();const row=e.target.closest('.off-row');if(!row||row===dragEl)return;
        list.querySelectorAll('.over').forEach(x=>x.classList.remove('over'));row.classList.add('over');
        const rect=row.getBoundingClientRect();const after=(e.clientY-rect.top)/rect.height>0.5;
        list.insertBefore(dragEl, after?row.nextSibling:row);});
    list.addEventListener('drop',e=>{e.preventDefault();list.querySelectorAll('.over').forEach(x=>x.classList.remove('over'));
        const ids=[...list.querySelectorAll('.off-row')].map(r=>r.dataset.id);
        post('reorder_offices',{order:ids.join(',')}).then(j=>{if(!j.ok)alert(j.error||'Lỗi sắp xếp');});});
})();
</script>

<?php elseif ($tab === 'pipeline'): ?>
<div class="rc-card" style="max-width:680px">
    <h3 style="font-size:14px;margin-bottom:4px">SLA &amp; email gợi ý cho từng giai đoạn</h3>
    <div class="rc-muted" style="margin-bottom:14px">SLA: thời hạn xử lý (giờ), 0 = không áp. Email gợi ý: chọn template cho mỗi giai đoạn - <b>không tự gửi</b>, mà hiện nút "Gửi mẫu này" ở trang đơn ứng tuyển khi ứng viên đang ở bước đó để bạn gửi thủ công.</div>
    <table class="rc-table">
        <thead><tr><th>Giai đoạn</th><th>Mã</th><th style="width:120px">SLA (giờ)</th><th>Email gợi ý cho giai đoạn</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($stages as $s): $mapKey = hrm_setting($conn, 'stage_email_' . $s['id'], ''); ?>
            <tr>
                <td><b><?= h($s['name']) ?></b></td>
                <td class="rc-muted"><?= h($s['code']) ?></td>
                <td><input type="number" min="0" id="sla<?= $s['id'] ?>" value="<?= (int)$s['sla_hours'] ?>" style="width:90px;padding:7px 10px;border:1px solid var(--bd);border-radius:7px;font-size:13px"></td>
                <td><select onchange="saveStageEmail(<?= $s['id'] ?>,this.value)" style="width:100%;padding:7px 10px;border:1px solid var(--bd);border-radius:7px;font-size:13px">
                    <option value="">— Không gửi —</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= h($t['event_key']) ?>" <?= $mapKey === $t['event_key'] ? 'selected' : '' ?>><?= h($t['name']) ?> (<?= h($t['audience']) ?>)<?= $t['enabled'] ? '' : ' - đang tắt' ?></option>
                    <?php endforeach; ?>
                </select></td>
                <td style="text-align:right"><button class="rc-btn ghost" style="padding:5px 12px" id="slaBtn<?= $s['id'] ?>" onclick="saveSla(<?= $s['id'] ?>,this)">Lưu SLA</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function saveSla(id,btn){
    const v=document.getElementById('sla'+id).value;
    const fd=new FormData();fd.append('action','save_stage_sla');fd.append('stage_id',id);fd.append('sla_hours',v);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(!j.ok){alert(j.error||'Lỗi');return;}
        const old=btn.textContent;btn.textContent='Đã lưu ✓';btn.style.color='#16a34a';btn.style.borderColor='#16a34a';
        setTimeout(()=>{btn.textContent=old;btn.style.color='';btn.style.borderColor='';},1500);
    });
}
function saveStageEmail(stageId,ekey){
    const fd=new FormData();fd.append('action','save_setting');fd.append('skey','stage_email_'+stageId);fd.append('sval',ekey);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});
}
</script>

<?php elseif ($tab === 'owners'): ?>
<div class="rc-card" style="max-width:820px">
    <h3 style="font-size:14px;margin-bottom:4px">Phụ trách từng giai đoạn theo phòng ban</h3>
    <div class="rc-muted" style="margin-bottom:14px">Mỗi giai đoạn chọn 1 người phụ trách phía BC và 1 phía TA. Áp dụng theo phòng ban của tin tuyển dụng.</div>
    <form method="get" style="margin-bottom:16px">
        <input type="hidden" name="tab" value="owners">
        <div class="rc-field" style="margin:0;max-width:360px"><label>Phòng ban</label>
            <select name="dept" onchange="this.form.submit()">
                <?php foreach ($deptList as $d): ?><option value="<?= $d['id'] ?>"<?= $ownerDept===(int)$d['id']?' selected':'' ?>><?= h($d['name']) ?></option><?php endforeach; ?>
            </select></div>
    </form>
    <table class="rc-table">
        <thead><tr><th>Giai đoạn</th><th>Phụ trách BC</th><th>Phụ trách TA</th></tr></thead>
        <tbody>
        <?php foreach ($stages as $s): $bc = $ownerMap[(int)$s['id']]['bc'] ?? 0; $ta = $ownerMap[(int)$s['id']]['ta'] ?? 0; ?>
            <tr>
                <td><b><?= h($s['name']) ?></b></td>
                <td><select onchange="saveOwner(<?= $s['id'] ?>,'bc',this.value)" style="width:100%;padding:7px 10px;border:1px solid var(--bd);border-radius:7px;font-size:13px">
                    <option value="0">- Chọn -</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= $bc===(int)$u['id']?' selected':'' ?>><?= h($u['full_name']) ?></option><?php endforeach; ?>
                </select></td>
                <td><select onchange="saveOwner(<?= $s['id'] ?>,'ta',this.value)" style="width:100%;padding:7px 10px;border:1px solid var(--bd);border-radius:7px;font-size:13px">
                    <option value="0">- Chọn -</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= $ta===(int)$u['id']?' selected':'' ?>><?= h($u['full_name']) ?></option><?php endforeach; ?>
                </select></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function saveOwner(stageId,type,userId){
    const fd=new FormData();fd.append('action','save_stage_owner');fd.append('department_id',<?= $ownerDept ?>);
    fd.append('stage_id',stageId);fd.append('owner_type',type);fd.append('user_id',userId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});
}
</script>

<?php elseif ($tab === 'roles'): ?>
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Gán vai trò tuyển dụng cho người dùng</h3>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:8px">
        <div class="rc-field" style="margin:0;min-width:240px"><label>Người dùng</label>
            <select id="u"><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?><?= $u['email']?' ('.h($u['email']).')':'' ?></option><?php endforeach; ?></select></div>
        <div class="rc-field" style="margin:0;min-width:200px"><label>Vai trò</label>
            <select id="r"><?php foreach ($roleLabels as $k=>$v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?></select></div>
        <button class="rc-btn" onclick="assign()">Gán</button>
    </div>
</div>
<?php foreach ($roleLabels as $k=>$v): ?>
<div class="rc-card" style="padding:14px 18px">
    <div style="font-weight:700;margin-bottom:8px"><?= h($v) ?> <span class="rc-muted"><?= $k ?></span></div>
    <?php if (empty($byRole[$k])): ?><div class="rc-muted">Chưa gán ai.</div>
    <?php else: foreach ($byRole[$k] as $a): ?>
        <span class="rc-badge rc-b-approved" style="margin:2px 4px 2px 0"><?= h($a['full_name']) ?>
            <a href="#" onclick="rmRole(<?= $a['id'] ?>);return false" style="color:#dc2626;margin-left:6px;text-decoration:none">✕</a></span>
    <?php endforeach; endif; ?>
</div>
<?php endforeach; ?>
<script>
function post(a,d){const fd=new FormData();fd.append('action',a);for(const k in d)fd.append(k,d[k]);return fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json());}
function assign(){post('assign_role',{user_id:document.getElementById('u').value,rec_role:document.getElementById('r').value}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function rmRole(id){if(confirm('Gỡ vai trò này?'))post('remove_role',{id:id}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
</script>

<?php elseif ($tab === 'email'): ?>
<div class="rc-card" style="margin-bottom:14px">
    <h3 style="font-size:14px;margin-bottom:6px">Biến chèn được vào tiêu đề / nội dung</h3>
    <div class="rc-muted" style="margin-bottom:10px">Dùng cú pháp <code>{{tên_biến}}</code> hoặc <code>{tên_biến}</code> (kiểu Base). Hệ thống tự thay bằng dữ liệu thật khi gửi.</div>
    <div style="display:flex;flex-wrap:wrap;gap:6px 10px;font-size:12px">
        <?php
        $varDocs = [
            'fullname' => 'Tên ứng viên', 'candidate_name' => 'Tên ứng viên', 'email' => 'Email ứng viên',
            'phone' => 'SĐT ứng viên', 'job' => 'Tên vị trí', 'job_title' => 'Tên vị trí', 'position' => 'Tên vị trí',
            'job_code' => 'Mã vị trí', 'level' => 'Level', 'department' => 'Phòng ban', 'office' => 'Văn phòng',
            'location' => 'Địa điểm', 'salary' => 'Khoảng lương', 'stage' => 'Giai đoạn', 'company' => 'Tên công ty',
            'today' => 'Ngày hôm nay', 'onboard_date' => 'Ngày onboard (HRF)', 'talent_url' => 'Link trang tuyển dụng',
        ];
        foreach ($varDocs as $k => $lbl): ?>
            <span style="background:#f1f5f9;border:1px solid var(--bd);border-radius:6px;padding:3px 8px"><code>{<?= $k ?>}</code> · <?= h($lbl) ?></span>
        <?php endforeach; ?>
    </div>
    <div class="rc-muted" style="margin-top:10px;font-size:11.5px">Ngoài các biến trên, <b>mọi field</b> của ứng viên/tin/HRF đều dùng được qua tiền tố: <code>{candidate_&lt;field&gt;}</code>, <code>{job_&lt;field&gt;}</code>, <code>{hrf_&lt;field&gt;}</code> (vd <code>{candidate_dob}</code>, <code>{candidate_current_position}</code>, <code>{job_deadline}</code>, <code>{job_headcount}</code>). Tên field theo cột trong DB.</div>
</div>

<div class="rc-card" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="document.getElementById('newTplBox').style.display=document.getElementById('newTplBox').style.display==='none'?'block':'none'">
        <h3 style="font-size:14px;margin:0">+ Tạo template mới</h3>
        <span class="rc-muted" style="font-size:12px">Bấm để mở/đóng</span>
    </div>
    <div id="newTplBox" style="display:none;margin-top:12px">
        <div class="rc-grid2">
            <div class="rc-field"><label>Tên template *</label><input id="nt_name" placeholder="VD: Mời test đầu vào"></div>
            <div class="rc-field"><label>Đối tượng</label>
                <select id="nt_audience"><option value="candidate">Ứng viên (candidate)</option><option value="internal">Nội bộ (internal)</option></select></div>
        </div>
        <div class="rc-field"><label>Mã (event_key) - để trống sẽ tự tạo</label><input id="nt_key" placeholder="vd: invite_test (hệ thống thêm tiền tố custom_)"></div>
        <div class="rc-field"><label>Tiêu đề</label><input id="nt_subject" placeholder="Dùng {{biến}} hoặc {biến}"></div>
        <div class="rc-field"><label>Nội dung (HTML)</label><textarea id="nt_body" rows="5"></textarea></div>
        <label style="font-size:12px;display:block;margin-bottom:8px"><input type="checkbox" id="nt_enabled" checked> Bật ngay</label>
        <button class="rc-btn" onclick="createTpl()">Tạo template</button>
    </div>
</div>

<?php foreach ($templates as $t): $isCustom = strpos($t['event_key'], 'custom_') === 0; ?>
<div class="rc-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div class="rc-muted" style="font-size:11.5px"><?= h($t['event_key']) ?> · <?= h($t['audience']) ?></div>
        <div style="display:flex;align-items:center;gap:12px">
            <label style="font-size:12px"><input type="checkbox" <?= $t['enabled']?'checked':'' ?> onchange="saveTpl(<?= $t['id'] ?>,this.closest('.rc-card'))"> Bật</label>
            <?php if ($isCustom): ?><button class="rc-btn ghost" style="padding:4px 10px;color:#dc2626" onclick="delTpl(<?= $t['id'] ?>)">Xóa</button><?php endif; ?>
        </div>
    </div>
    <div class="rc-field"><label>Tên template</label><input data-f="name" value="<?= h($t['name']) ?>"></div>
    <div class="rc-field"><label>Tiêu đề (subject)</label><input data-f="subject" value="<?= h($t['subject']) ?>"></div>
    <div class="rc-field"><label>Nội dung (HTML, dùng {{biến}})</label><textarea data-f="body" rows="5"><?= h($t['body_html']) ?></textarea></div>
    <button class="rc-btn ghost" onclick="saveTpl(<?= $t['id'] ?>,this.closest('.rc-card'))">Lưu</button>
</div>
<?php endforeach; ?>
<script>
function saveTpl(id,card){
    const fd=new FormData();fd.append('action','save_email_template');fd.append('id',id);
    const nameEl=card.querySelector('[data-f=name]');if(nameEl)fd.append('name',nameEl.value);
    fd.append('subject',card.querySelector('[data-f=subject]').value);
    fd.append('body_html',card.querySelector('[data-f=body]').value);
    if(card.querySelector('input[type=checkbox]').checked)fd.append('enabled','1');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});
}
function createTpl(){
    const name=document.getElementById('nt_name').value.trim();
    if(!name){alert('Nhập tên template');return;}
    const fd=new FormData();fd.append('action','create_email_template');
    fd.append('name',name);
    fd.append('audience',document.getElementById('nt_audience').value);
    fd.append('event_key',document.getElementById('nt_key').value);
    fd.append('subject',document.getElementById('nt_subject').value);
    fd.append('body_html',document.getElementById('nt_body').value);
    if(document.getElementById('nt_enabled').checked)fd.append('enabled','1');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function delTpl(id){
    if(!confirm('Xóa template này?'))return;
    const fd=new FormData();fd.append('action','delete_email_template');fd.append('id',id);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
</script>

<?php elseif ($tab === 'channels_cfg'):
    $channels = hrm_channels($conn);
    $chTypes  = hrm_channel_types();
    // Dữ liệu kênh cho JS (để nút "Sửa" nạp lại form). Chỉ admin xem trang này.
    $chJs = [];
    foreach ($channels as $c) {
        $cfg = json_decode($c['config'] ?? '', true); if (!is_array($cfg)) { $cfg = []; }
        $chJs[(int)$c['id']] = [
            'name' => $c['name'], 'type' => $c['type'], 'icon' => $c['icon'],
            'webhook_url' => $c['webhook_url'], 'secret' => $c['secret'], 'config' => $cfg,
        ];
    }
    $liRedirect = hrm_linkedin_redirect_uri();
?>
<?php if (isset($_GET['li_ok'])): ?><div class="rc-card" style="max-width:680px;margin-bottom:12px;border-left:3px solid #16a34a;color:#15803d;font-size:13px"><?= h($_GET['li_ok']) ?></div><?php endif; ?>
<?php if (isset($_GET['li_err'])): ?><div class="rc-card" style="max-width:680px;margin-bottom:12px;border-left:3px solid #dc2626;color:#b91c1c;font-size:13px"><?= h($_GET['li_err']) ?></div><?php endif; ?>
<div class="rc-card" style="max-width:680px">
    <h3 style="font-size:14px;margin-bottom:4px" id="chFormTitle">Thêm kênh đăng tin</h3>
    <p class="rc-muted" style="font-size:12px;margin-bottom:10px;line-height:1.5">
        Đăng tin tuyển dụng <b>trực tiếp qua API</b> của nền tảng (miễn phí — chỉ cần tạo app developer & access token).
        Facebook dùng Graph API <code>/feed</code>; LinkedIn dùng Posts API <code>/rest/posts</code> (cần quyền admin Company Page).
    </p>

    <details class="ch-guide" style="margin-bottom:14px">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:#0071e3">📖 Hướng dẫn lấy thông tin cấu hình (bấm để mở)</summary>
        <div style="font-size:12px;line-height:1.6;color:#3a3a3c;padding:10px 2px 2px">
            <b>🔷 Facebook Page</b> (dễ nhất)
            <ol style="margin:4px 0 10px 18px;padding:0">
                <li>Tạo App tại <i>developers.facebook.com/apps</i> (loại Business).</li>
                <li>Vào <i>Graph API Explorer</i> → chọn app → <b>Get Page Access Token</b> → chọn Page → cấp quyền <code>pages_manage_posts</code>, <code>pages_read_engagement</code>.</li>
                <li>Gọi <code>GET me/accounts</code> để lấy <b>Page ID</b> và <b>access_token</b> của Page.</li>
                <li>Nên đổi sang <b>token dài hạn</b> (token Page gần như không hết hạn) — xem chi tiết trong file hướng dẫn.</li>
                <li>Điền: Page ID + Page Access Token, version để <code>v25.0</code>.</li>
            </ol>
            <b>🔷 LinkedIn (Company Page)</b> — cần app được LinkedIn duyệt <i>Community Management API</i> (mất thời gian, có thể bị từ chối)
            <ol style="margin:4px 0 10px 18px;padding:0">
                <li>Tạo app tại <i>linkedin.com/developers/apps</i>, gắn với Company Page và xác minh.</li>
                <li>Tab Products → request <b>Community Management API</b>; scope <code>w_organization_social</code>.</li>
                <li>Chạy OAuth 2.0 để lấy <b>Access Token</b> (admin Company Page cấp quyền).</li>
                <li>Lấy <b>Organization ID</b> từ URL trang quản trị <code>/company/{ID}/admin/</code> hoặc API <code>organizationAcls</code>.</li>
                <li>Điền: Organization ID + Access Token, version để <code>202606</code>.</li>
            </ol>
            <b>🔷 Webhook</b>: dán URL nhận dữ liệu (POST JSON), Secret tùy chọn (header <code>X-Webhook-Secret</code>).
            <div style="margin-top:8px">📄 Hướng dẫn đầy đủ kèm lệnh API: <code>docs/huong-dan-kenh-dang-tin.md</code> trong mã nguồn.</div>
        </div>
    </details>

    <input type="hidden" id="chId" value="">
    <div class="rc-field" style="margin-bottom:8px"><label>Loại kênh</label>
        <select id="chType" onchange="chToggleFields()">
            <?php foreach ($chTypes as $k => $lbl): ?><option value="<?= $k ?>"><?= h($lbl) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="rc-field" style="margin-bottom:8px"><label>Tên kênh</label><input id="chName" placeholder="VD: Facebook ArrowHiTech"></div>
    <div class="rc-field" style="margin-bottom:8px"><label>Icon (emoji, tùy chọn)</label><input id="chIcon" placeholder="VD: 📘 hoặc 💼" style="max-width:140px"></div>

    <!-- Facebook -->
    <div class="ch-fields" data-type="facebook" style="display:none">
        <div class="rc-field" style="margin-bottom:8px"><label>Facebook Page ID</label><input id="fbPage" placeholder="VD: 1234567890"></div>
        <div class="rc-field" style="margin-bottom:8px"><label>Page Access Token</label><input id="fbToken" placeholder="EAAB... (dán token rồi đổi sang dài hạn bên dưới)"></div>
        <div class="rc-field" style="margin-bottom:8px"><label>Graph API version</label><input id="fbVer" placeholder="v25.0" value="v25.0" style="max-width:160px"></div>
        <p class="rc-muted" style="font-size:11px;line-height:1.5">Cần quyền <code>pages_manage_posts</code>, <code>pages_read_engagement</code>, <code>pages_show_list</code>. Lấy token tại Graph API Explorer.</p>

        <div style="border:1px dashed #c7c7cc;border-radius:10px;padding:12px;margin-top:6px;background:#fbfbfd">
            <div style="font-size:12px;font-weight:600;margin-bottom:8px">🔁 Đổi sang token dài hạn (không hết hạn)</div>
            <div class="rc-field" style="margin-bottom:8px"><label>App ID</label><input id="fbAppId" placeholder="App ID (Settings → Basic)"></div>
            <div class="rc-field" style="margin-bottom:8px"><label>App Secret</label><input id="fbAppSecret" placeholder="App Secret — KHÔNG được lưu lại"></div>
            <button type="button" class="rc-btn ghost" id="fbExBtn" onclick="fbExchange()">Đổi token dài hạn</button>
            <div id="fbExResult" style="font-size:12px;margin-top:8px"></div>
            <p class="rc-muted" style="font-size:11px;line-height:1.5;margin-top:6px">Hệ thống dùng token hiện tại + App ID/Secret để lấy <b>Page token dài hạn</b> rồi tự điền vào ô trên. App Secret chỉ dùng tạm thời, không lưu.</p>
            <div style="font-size:11px;line-height:1.6;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:8px 10px;margin-top:8px;color:#9a3412">
                ⚠️ <b>Lỗi "access token does not belong to application …"?</b><br>
                Token và App ID/Secret phải <b>cùng một app</b>. Token của bạn đang được tạo bởi app khác (thường là app mặc định của Graph API Explorer).<br>
                Cách sửa: mở <i>Graph API Explorer</i> → góc trên phải chọn đúng <b>Meta App</b> (đúng App ID ở trên) → <b>Get Page Access Token</b> → chọn Page → cấp lại quyền → <b>Generate Access Token</b> → copy token mới dán vào ô Page Access Token, rồi bấm "Đổi token dài hạn" lại.
            </div>
        </div>
    </div>
    <!-- LinkedIn -->
    <div class="ch-fields" data-type="linkedin" style="display:none">
        <div class="rc-field" style="margin-bottom:8px"><label>Organization ID (Company Page)</label><input id="liOrg" placeholder="VD: 5515715 (hoặc urn:li:organization:5515715)"></div>
        <div class="rc-field" style="margin-bottom:8px"><label>Client ID</label><input id="liClientId" placeholder="Client ID của app LinkedIn"></div>
        <div class="rc-field" style="margin-bottom:8px"><label>Client Secret</label><input id="liClientSecret" placeholder="Primary Client Secret"></div>
        <div class="rc-field" style="margin-bottom:8px"><label>LinkedIn-Version (YYYYMM)</label><input id="liVer" placeholder="202606" value="202606" style="max-width:160px"></div>

        <div style="border:1px dashed #c7c7cc;border-radius:10px;padding:12px;margin-top:6px;background:#fbfbfd">
            <div style="font-size:12px;font-weight:600;margin-bottom:6px">🔗 Kết nối bằng OAuth (token tự lấy & tự làm mới)</div>
            <div style="font-size:11px;line-height:1.6;color:#3a3a3c">
                1) Mở app LinkedIn → tab <b>Auth</b> → thêm <b>Authorized redirect URL</b> đúng giá trị sau:
                <div style="display:flex;gap:6px;margin:6px 0">
                    <input id="liRedirect" readonly value="<?= h($liRedirect) ?>" style="font-size:11px;background:#f1f5f9">
                    <button type="button" class="rc-btn ghost" onclick="navigator.clipboard.writeText(document.getElementById('liRedirect').value)">Copy</button>
                </div>
                2) Nhập Org ID + Client ID + Client Secret ở trên → bấm <b>Lưu/Thêm kênh</b>.<br>
                3) Bấm <b>Kết nối LinkedIn</b> ở dòng kênh trong danh sách bên dưới → đăng nhập admin Company Page → xong.
            </div>
            <div id="liManualWrap" style="margin-top:8px">
                <div class="rc-field"><label style="font-size:11px;color:#86868b">Hoặc dán Access Token thủ công (tùy chọn — sẽ hết hạn sau ~2 tháng)</label><input id="liToken" placeholder="Để trống nếu dùng nút Kết nối OAuth"></div>
            </div>
            <p class="rc-muted" style="font-size:11px;line-height:1.5;margin-top:6px">Cần app được duyệt <b>Community Management API</b> (scope <code>w_organization_social</code>) và bạn là Admin Company Page.</p>
        </div>
    </div>
    <!-- Webhook -->
    <div class="ch-fields" data-type="webhook" style="display:none">
        <div class="rc-field" style="margin-bottom:8px"><label>Webhook URL</label><input id="chUrl" placeholder="https://...."></div>
        <div class="rc-field" style="margin-bottom:8px"><label>Secret (tùy chọn — header X-Webhook-Secret)</label><input id="chSecret" placeholder="Để trống nếu không cần"></div>
    </div>

    <button class="rc-btn" id="chSubmit" onclick="submitChannel()">+ Thêm kênh</button>
    <button class="rc-btn ghost" id="chCancel" style="display:none" onclick="resetChForm()">Hủy</button>
</div>

<div class="rc-card" style="max-width:680px;margin-top:16px">
    <h3 style="font-size:14px;margin-bottom:10px">Danh sách kênh <span class="rc-muted">(<?= count($channels) ?>)</span></h3>
    <?php if (!$channels): ?>
        <div class="rc-muted" style="font-size:13px">Chưa có kênh nào. Thêm kênh đầu tiên ở trên.</div>
    <?php else: ?>
    <table class="rc-table"><thead><tr><th>Kênh</th><th>Loại</th><th>Thông tin</th><th style="width:70px">Bật</th><th style="width:100px"></th></tr></thead><tbody>
    <?php foreach ($channels as $c):
        $cfg = json_decode($c['config'] ?? '', true); if (!is_array($cfg)) { $cfg = []; }
        $info = ''; $liConnect = false;
        if ($c['type'] === 'facebook')      { $info = 'Page ' . h($cfg['page_id'] ?? '') . (!empty($cfg['access_token']) ? ' · có token' : ' · ⚠ thiếu token'); }
        elseif ($c['type'] === 'linkedin')  {
            $info = 'Org ' . h($cfg['org_id'] ?? '');
            if (!empty($cfg['access_token'])) {
                $exp = (int)($cfg['token_expires_at'] ?? 0);
                if ($exp && $exp < time())      { $info .= ' · <span style="color:#dc2626">token hết hạn</span>'; }
                elseif ($exp)                   { $info .= ' · <span style="color:#16a34a">đã kết nối</span> (hết hạn ' . date('d/m/Y', $exp) . ')'; }
                else                            { $info .= ' · có token'; }
            } else {
                $info .= ' · <span style="color:#d97706">chưa kết nối</span>';
            }
            $liConnect = !empty($cfg['client_id']) && !empty($cfg['client_secret']);
        }
        else                                { $info = h($c['webhook_url']); }
    ?>
        <tr>
            <td><b><?= h($c['icon']) ?> <?= h($c['name']) ?></b></td>
            <td style="font-size:12px"><?= h($chTypes[$c['type']] ?? $c['type']) ?></td>
            <td style="word-break:break-all;font-size:12px"><?= $info ?></td>
            <td><label class="rc-switch"><input type="checkbox" <?= (int)$c['enabled'] ? 'checked' : '' ?> onchange="toggleChannel(<?= (int)$c['id'] ?>,this.checked)"></label></td>
            <td style="text-align:right;white-space:nowrap">
                <?php if ($c['type'] === 'linkedin' && $liConnect): ?>
                    <a href="/hrm/linkedin-oauth?channel=<?= (int)$c['id'] ?>" class="ch-act" style="color:#0a66c2">Kết nối</a>
                <?php endif; ?>
                <a href="javascript:" class="ch-act" onclick="editChannel(<?= (int)$c['id'] ?>)">Sửa</a>
                <a href="javascript:" class="ch-act" style="color:#dc2626" onclick="rmChannel(<?= (int)$c['id'] ?>)">Xóa</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</div>
<style>
.rc-card input,.rc-card select{width:100%;padding:8px 11px;border:1px solid var(--bd);border-radius:8px;font-size:13px}
.ch-act{font-size:12px;font-weight:600;color:#0071e3;text-decoration:none;margin-left:10px}
.rc-switch input{width:auto}
.rc-card code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:11px}
</style>
<script>
var CH_DATA = <?= json_encode($chJs, JSON_UNESCAPED_UNICODE) ?>;
function post(a,d){const fd=new FormData();fd.append('action',a);for(const k in d)fd.append(k,d[k]);return fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json());}
function $v(id){return (document.getElementById(id).value||'').trim();}
function chToggleFields(){
    const t=document.getElementById('chType').value;
    document.querySelectorAll('.ch-fields').forEach(e=>e.style.display=(e.dataset.type===t?'block':'none'));
}
function submitChannel(){
    const name=$v('chName'); if(!name){alert('Nhập tên kênh');return;}
    const id=$v('chId'); const type=document.getElementById('chType').value;
    const d={name:name,icon:$v('chIcon'),type:type};
    if(type==='facebook'){ d.page_id=$v('fbPage'); d.access_token=$v('fbToken'); d.api_version=$v('fbVer'); }
    else if(type==='linkedin'){ d.org_id=$v('liOrg'); d.client_id=$v('liClientId'); d.client_secret=$v('liClientSecret'); d.access_token=$v('liToken'); d.api_version=$v('liVer'); }
    else { d.webhook_url=$v('chUrl'); d.secret=$v('chSecret'); }
    if(id){ d.id=id; }
    post(id?'update_channel':'save_channel',d).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function editChannel(id){
    const c=CH_DATA[id]; if(!c)return; const cfg=c.config||{};
    document.getElementById('chId').value=id;
    document.getElementById('chType').value=c.type; chToggleFields();
    document.getElementById('chName').value=c.name||''; document.getElementById('chIcon').value=c.icon||'';
    if(c.type==='facebook'){ document.getElementById('fbPage').value=cfg.page_id||''; document.getElementById('fbToken').value=cfg.access_token||''; document.getElementById('fbVer').value=cfg.api_version||'v25.0'; }
    else if(c.type==='linkedin'){ document.getElementById('liOrg').value=cfg.org_id||''; document.getElementById('liClientId').value=cfg.client_id||''; document.getElementById('liClientSecret').value=cfg.client_secret||''; document.getElementById('liToken').value=''; document.getElementById('liVer').value=cfg.api_version||'202606'; }
    else { document.getElementById('chUrl').value=c.webhook_url||''; document.getElementById('chSecret').value=c.secret||''; }
    document.getElementById('chFormTitle').textContent='Sửa kênh: '+(c.name||'');
    document.getElementById('chSubmit').textContent='Lưu thay đổi';
    document.getElementById('chCancel').style.display='';
    window.scrollTo({top:0,behavior:'smooth'});
}
function resetChForm(){
    document.getElementById('chId').value=''; document.getElementById('chName').value=''; document.getElementById('chIcon').value='';
    ['fbPage','fbToken','liOrg','liClientId','liClientSecret','liToken','chUrl','chSecret'].forEach(i=>{const e=document.getElementById(i);if(e)e.value='';});
    document.getElementById('fbVer').value='v25.0'; document.getElementById('liVer').value='202606';
    document.getElementById('chFormTitle').textContent='Thêm kênh đăng tin';
    document.getElementById('chSubmit').textContent='+ Thêm kênh';
    document.getElementById('chCancel').style.display='none';
}
function toggleChannel(id,on){post('toggle_channel',{id:id,enabled:on?1:''}).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});}
function rmChannel(id){if(confirm('Xóa kênh này?'))post('remove_channel',{id:id}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function fbExchange(){
    const res=document.getElementById('fbExResult');
    const appId=$v('fbAppId'), secret=$v('fbAppSecret'), token=$v('fbToken');
    if(!appId||!secret||!token){ res.innerHTML='<span style="color:#dc2626">Nhập App ID, App Secret và Page Access Token (token hiện tại) trước.</span>'; return; }
    const btn=document.getElementById('fbExBtn'); btn.disabled=true; const old=btn.textContent; btn.textContent='Đang đổi...';
    post('fb_exchange_token',{app_id:appId,app_secret:secret,short_token:token,page_id:$v('fbPage'),api_version:$v('fbVer')}).then(j=>{
        btn.disabled=false; btn.textContent=old;
        if(!j.ok){ res.innerHTML='<span style="color:#dc2626">'+(j.error||'Lỗi')+'</span>'; return; }
        if(j.need_pick){
            let h='<span style="color:#d97706">Bạn quản trị nhiều Page — nhập <b>Page ID</b> vào ô trên rồi bấm lại:</span><ul style="margin:6px 0 0 16px">';
            j.pages.forEach(p=>{h+='<li>'+p.name+' — ID: <code>'+p.id+'</code></li>';}); h+='</ul>';
            res.innerHTML=h; return;
        }
        document.getElementById('fbToken').value=j.page_token;
        if(j.page_id) document.getElementById('fbPage').value=j.page_id;
        document.getElementById('fbAppSecret').value='';
        res.innerHTML='<span style="color:#16a34a">✓ Đã lấy token dài hạn cho Page <b>'+(j.page_name||j.page_id)+'</b>. Bấm "'+document.getElementById('chSubmit').textContent.trim()+'" để lưu kênh.</span>';
    }).catch(()=>{ btn.disabled=false; btn.textContent=old; res.innerHTML='<span style="color:#dc2626">Lỗi mạng</span>'; });
}
chToggleFields();
</script>

<?php else: ?>
<div class="rc-card" style="max-width:560px">
    <h3 style="font-size:14px;margin-bottom:10px">Bật/tắt kênh thông báo</h3>
    <?php
    $toggles = ['email_enabled' => 'Gửi email', 'notif_enabled' => 'Thông báo trong app'];
    foreach ($toggles as $k => $label):
        $on = hrm_setting($conn, $k, '1') === '1'; ?>
        <label style="display:block;padding:8px 0"><input type="checkbox" <?= $on?'checked':'' ?> onchange="setToggle('<?= $k ?>',this.checked)"> <?= $label ?></label>
    <?php endforeach; ?>
    <div class="rc-field" style="margin-top:10px"><label>Lưu trữ dữ liệu (tháng)</label>
        <input id="ret" type="number" value="<?= h(hrm_setting($conn,'retention_months','24')) ?>" onblur="setSetting('retention_months',this.value)"></div>
</div>
<div class="rc-card" style="max-width:560px;margin-top:16px">
    <h3 style="font-size:14px;margin-bottom:6px">Email sender cho HRM</h3>
    <div class="rc-muted" style="margin-bottom:12px">Gán người gửi theo từng loại email tuyển dụng. Danh sách lấy từ <a href="/settings/smtp" style="color:var(--rc2)">Email Senders chung</a>.</div>

    <div class="rc-field"><label>Email ứng viên (CV received, offer letter, từ chối...)</label>
        <select onchange="setSetting('hrm_email_sender_candidate',this.value)"><?= $senderOpts($hrmSenderCand, '— Theo sender chung HRM —') ?></select></div>

    <div class="rc-field"><label>Email nội bộ (phê duyệt HRF / offer, thông báo...)</label>
        <select onchange="setSetting('hrm_email_sender_internal',this.value)"><?= $senderOpts($hrmSenderInt, '— Theo sender chung HRM —') ?></select></div>

    <div class="rc-field" style="border-top:1px solid var(--bd);padding-top:12px"><label>Sender chung HRM (fallback khi 2 mục trên để trống)</label>
        <select onchange="setSetting('hrm_email_sender',this.value)"><?= $senderOpts($hrmSenderSel, '— Dùng sender mặc định hệ thống —') ?></select></div>

    <?php if (!$emailSenders): ?><div class="rc-muted">Chưa có sender nào. <a href="/settings/smtp" style="color:var(--rc2)">Thêm tại đây</a>.</div><?php endif; ?>
</div>
<div class="rc-card" style="max-width:560px;margin-top:16px">
    <h3 style="font-size:14px;margin-bottom:10px">Cấu hình API Kênh Tuyển Dụng (AHT Talent)</h3>
    <div class="rc-field"><label>API URL</label>
        <input value="<?= h(hrm_setting($conn,'aht_api_url','https://t.arrowhitech.com/wp-json/aht/v1/jobs')) ?>" onblur="setSetting('aht_api_url',this.value)"></div>
    <div class="rc-field"><label>API Key</label>
        <input value="<?= h(hrm_setting($conn,'aht_api_key','')) ?>" onblur="setSetting('aht_api_key',this.value)" placeholder="X-API-Key"></div>
</div>
<script>
function setSetting(k,v){const fd=new FormData();fd.append('action','save_setting');fd.append('skey',k);fd.append('sval',v);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});}
function setToggle(k,on){setSetting(k,on?'1':'0');}
</script>
<?php endif; ?>
<?php
hrm_footer();
