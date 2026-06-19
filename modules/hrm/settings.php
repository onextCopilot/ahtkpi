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
$offices = $conn->query("SELECT id, name, address, active FROM hrm_offices ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);

$titles = ['offices' => 'Văn phòng', 'pipeline' => 'Giai đoạn & SLA', 'owners' => 'Phụ trách giai đoạn', 'roles' => 'Vai trò tuyển dụng', 'email' => 'Email template', 'channels' => 'Kênh thông báo'];
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
    <h3 style="font-size:14px;margin-bottom:4px">SLA cho từng giai đoạn tuyển dụng</h3>
    <div class="rc-muted" style="margin-bottom:14px">Đặt thời hạn xử lý (giờ) cho mỗi bước. 0 = không áp SLA. Áp dụng cho ứng viên khi vào bước đó.</div>
    <table class="rc-table">
        <thead><tr><th>Giai đoạn</th><th>Mã</th><th style="width:160px">SLA (giờ)</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($stages as $s): ?>
            <tr>
                <td><b><?= h($s['name']) ?></b></td>
                <td class="rc-muted"><?= h($s['code']) ?></td>
                <td><input type="number" min="0" id="sla<?= $s['id'] ?>" value="<?= (int)$s['sla_hours'] ?>" style="width:110px;padding:7px 10px;border:1px solid var(--bd);border-radius:7px;font-size:13px"></td>
                <td style="text-align:right"><button class="rc-btn ghost" style="padding:5px 12px" id="slaBtn<?= $s['id'] ?>" onclick="saveSla(<?= $s['id'] ?>,this)">Lưu</button></td>
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
<?php foreach ($templates as $t): ?>
<div class="rc-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div><b><?= h($t['name']) ?></b> <span class="rc-muted"><?= h($t['event_key']) ?> · <?= h($t['audience']) ?></span></div>
        <label style="font-size:12px"><input type="checkbox" <?= $t['enabled']?'checked':'' ?> onchange="saveTpl(<?= $t['id'] ?>,this.closest('.rc-card'))"> Bật</label>
    </div>
    <div class="rc-field"><label>Tiêu đề</label><input data-f="subject" value="<?= h($t['subject']) ?>"></div>
    <div class="rc-field"><label>Nội dung (HTML, dùng {{biến}})</label><textarea data-f="body" rows="5"><?= h($t['body_html']) ?></textarea></div>
    <button class="rc-btn ghost" onclick="saveTpl(<?= $t['id'] ?>,this.closest('.rc-card'))">Lưu</button>
</div>
<?php endforeach; ?>
<script>
function saveTpl(id,card){
    const fd=new FormData();fd.append('action','save_email_template');fd.append('id',id);
    fd.append('subject',card.querySelector('[data-f=subject]').value);
    fd.append('body_html',card.querySelector('[data-f=body]').value);
    if(card.querySelector('input[type=checkbox]').checked)fd.append('enabled','1');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});
}
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
