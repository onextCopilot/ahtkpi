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

hrm_header('Sự kiện', 'Sự kiện tuyển dụng - nguồn ứng viên', 'events');
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
                    <button class="rc-btn ghost" style="padding:5px 12px" onclick='openEvent(<?= (int)$e['id'] ?>, <?= json_encode($e, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Sửa</button>
                    <button class="rc-btn ghost" style="padding:5px 12px;color:#dc2626" onclick="delEvent(<?= (int)$e['id'] ?>, <?= (int)$e['cand_count'] ?>)">Xóa</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
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
</script>
<?php
hrm_footer();
