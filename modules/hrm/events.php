<?php
/**
 * Sự kiện tuyển dụng - nguồn ứng viên ngoài tin tuyển dụng (job fair, hội thảo, referral drive...).
 * Route: /hrm/events
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$events = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM hrm_candidates c WHERE c.event_id=e.id) AS cand_count
    FROM hrm_events e WHERE e.active=1 ORDER BY e.event_date DESC, e.id DESC")->fetch_all(MYSQLI_ASSOC);
$srcs = $conn->query("SELECT s.*, (SELECT COUNT(*) FROM hrm_candidates c WHERE c.source_id=s.id) AS cand_count
    FROM hrm_candidate_sources s WHERE s.active=1 ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

hrm_header('Sự kiện & nguồn', 'Nguồn ứng viên ngoài tin tuyển dụng', 'events');
?>
<div class="rc-toolbar">
    <div></div>
    <button class="rc-btn" onclick="openEvent(0)">+ Thêm sự kiện</button>
</div>

<div class="rc-card">
    <?php if (!$events): ?>
        <div class="rc-empty">Chưa có sự kiện. Tạo sự kiện để gắn ứng viên thu thập từ job fair, hội thảo, referral...</div>
    <?php else: ?>
    <table class="rc-table">
        <thead><tr><th>Tên sự kiện</th><th>Loại</th><th>Ngày</th><th>Địa điểm</th><th>Ứng viên</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
            <tr>
                <td><b><?= h($e['name']) ?></b></td>
                <td><?= h($e['type'] ?: '-') ?></td>
                <td><?= $e['event_date'] ? date('d/m/Y', strtotime($e['event_date'])) : '-' ?></td>
                <td><?= h($e['location'] ?: '-') ?></td>
                <td><?php if ((int)$e['cand_count']): ?><a href="/hrm/candidates?event=<?= $e['id'] ?>" class="rc-badge rc-b-pending"><?= (int)$e['cand_count'] ?> ứng viên</a><?php else: ?><span class="rc-muted">0</span><?php endif; ?></td>
                <td style="text-align:right;white-space:nowrap">
                    <button class="rc-btn ghost" style="padding:5px 12px" onclick="showQR(<?= (int)$e['id'] ?>, <?= json_encode($e['name'], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)">Link / QR</button>
                    <button class="rc-btn ghost" style="padding:5px 12px" onclick='openEvent(<?= (int)$e['id'] ?>, <?= json_encode($e, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Sửa</button>
                    <button class="rc-btn ghost" style="padding:5px 12px;color:#dc2626" onclick="delEvent(<?= (int)$e['id'] ?>, <?= (int)$e['cand_count'] ?>)">Xóa</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="rc-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="font-size:14px;margin:0">Nguồn ứng viên</h3>
        <button class="rc-btn ghost" onclick="addSource()">+ Thêm nguồn</button>
    </div>
    <?php if (!$srcs): ?><div class="rc-muted">Chưa có nguồn.</div><?php else: ?>
    <table class="rc-table"><thead><tr><th>Tên nguồn</th><th>Ứng viên</th><th></th></tr></thead><tbody>
    <?php foreach ($srcs as $s): ?>
        <tr><td><b><?= h($s['name']) ?></b></td>
            <td><?php if ((int)$s['cand_count']): ?><a href="/hrm/candidates?source=<?= $s['id'] ?>" class="rc-badge rc-b-pending"><?= (int)$s['cand_count'] ?></a><?php else: ?><span class="rc-muted">0</span><?php endif; ?></td>
            <td style="text-align:right;white-space:nowrap">
                <button class="rc-btn ghost" style="padding:5px 12px" onclick="renameSource(<?= $s['id'] ?>, <?= json_encode($s['name'], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)">Đổi tên</button>
                <button class="rc-btn ghost" style="padding:5px 12px;color:#dc2626" onclick="delSource(<?= $s['id'] ?>)">Ẩn</button>
            </td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
</div>

<!-- QR / Link modal -->
<div id="qrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:420px;max-width:94vw;text-align:center">
        <h3 style="font-size:15px;margin-bottom:4px">Link đăng ký công khai</h3>
        <div class="rc-muted" id="qrEvName" style="margin-bottom:14px"></div>
        <img id="qrImg" alt="QR" style="width:220px;height:220px;border:1px solid #eee;border-radius:10px">
        <div style="display:flex;gap:8px;margin-top:14px">
            <input id="qrLink" class="rc-in" readonly style="flex:1;padding:9px 11px;border:1px solid var(--bd);border-radius:8px;font-size:12.5px">
            <button class="rc-btn" onclick="copyLink()">Copy</button>
        </div>
        <div class="rc-muted" style="font-size:11.5px;margin-top:8px">Quét QR hoặc mở link để ứng viên tự đăng ký (không cần đăng nhập). Hồ sơ tự gắn vào sự kiện này.</div>
        <div style="margin-top:14px"><button class="rc-btn ghost" onclick="document.getElementById('qrModal').style.display='none'">Đóng</button>
            <a class="rc-btn ghost" id="qrOpen" target="_blank" rel="noopener">Mở thử</a></div>
    </div>
</div>

<!-- Modal -->
<div id="evModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:480px;max-width:94vw">
        <h3 style="font-size:15px;margin-bottom:12px" id="evTitle">Thêm sự kiện</h3>
        <form id="evForm" onsubmit="return false">
            <input type="hidden" name="id" id="ev_id" value="0">
            <div class="rc-field"><label>Tên sự kiện *</label><input name="name" id="ev_name" required placeholder="VD: Job Fair ĐH Bách Khoa 2026"></div>
            <div class="rc-grid2">
                <div class="rc-field"><label>Loại</label>
                    <select name="type" id="ev_type">
                        <option value="">-</option>
                        <?php foreach (['Job fair','Hội thảo / Seminar','Workshop','Referral drive','Online event','Khác'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                    </select></div>
                <div class="rc-field"><label>Ngày</label><input type="date" name="event_date" id="ev_date"></div>
            </div>
            <div class="rc-field"><label>Địa điểm</label><input name="location" id="ev_loc" placeholder="VD: ĐH Bách Khoa Hà Nội"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
                <button type="button" class="rc-btn ghost" onclick="document.getElementById('evModal').style.display='none'">Hủy</button>
                <button type="button" class="rc-btn" onclick="saveEvent()">Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE = <?= json_encode(hrm_base_url()) ?>;
function showQR(id, name){
    const url = BASE + '/hrm/intake?e=' + id;
    document.getElementById('qrEvName').textContent = name;
    document.getElementById('qrLink').value = url;
    document.getElementById('qrOpen').href = url;
    document.getElementById('qrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(url);
    document.getElementById('qrModal').style.display = 'flex';
}
function copyLink(){const i=document.getElementById('qrLink');i.select();document.execCommand('copy');}
function openEvent(id, data){
    document.getElementById('ev_id').value = id || 0;
    document.getElementById('evTitle').textContent = id ? 'Sửa sự kiện' : 'Thêm sự kiện';
    document.getElementById('ev_name').value = data ? (data.name||'') : '';
    document.getElementById('ev_type').value = data ? (data.type||'') : '';
    document.getElementById('ev_date').value = data && data.event_date ? data.event_date : '';
    document.getElementById('ev_loc').value  = data ? (data.location||'') : '';
    document.getElementById('evModal').style.display = 'flex';
}
function saveEvent(){
    const f=document.getElementById('evForm');
    if(!f.name.value.trim()){alert('Nhập tên sự kiện');return;}
    const fd=new FormData(f);fd.append('action','event_save');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function delEvent(id, n){
    const msg = n>0 ? ('Sự kiện còn '+n+' ứng viên gắn vào. Xóa sẽ ẩn sự kiện (giữ ứng viên). Tiếp tục?') : 'Xóa sự kiện này?';
    if(!confirm(msg))return;
    const fd=new FormData();fd.append('action','event_del');fd.append('id',id);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function saveSource(id, name){
    const fd=new FormData();fd.append('action','source_save');fd.append('id',id);fd.append('name',name);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function addSource(){const n=prompt('Tên nguồn mới (VD: Sự kiện, Referral, Headhunt):');if(n&&n.trim())saveSource(0,n.trim());}
function renameSource(id, cur){const n=prompt('Đổi tên nguồn:',cur);if(n&&n.trim())saveSource(id,n.trim());}
function delSource(id){if(!confirm('Ẩn nguồn này? (ứng viên đang dùng vẫn giữ)'))return;
    const fd=new FormData();fd.append('action','source_del');fd.append('id',id);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
</script>
<?php
hrm_footer();
