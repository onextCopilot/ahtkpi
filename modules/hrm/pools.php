<?php
/**
 * Talent pools - quản lý các pool ứng viên. Route: /hrm/pools
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$pools = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM hrm_candidate_pools cp WHERE cp.pool_id=p.id) AS cand_count
    FROM hrm_pools p WHERE p.active=1 ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);

hrm_header('Talent pools', 'Nhóm ứng viên tiềm năng theo kỹ năng / mục đích', 'pools');
?>
<div class="rc-toolbar"><div></div><button class="rc-btn" onclick="openPool(0)">+ Tạo pool</button></div>

<?php if (!$pools): ?>
    <div class="rc-empty">Chưa có pool nào. Tạo pool (vd "Java Senior", "Sự kiện BKHN", "Dự phòng Sale") rồi gán ứng viên vào.</div>
<?php else: ?>
<div class="pool-grid">
    <?php foreach ($pools as $p): ?>
    <div class="pool-card">
        <div class="pool-top"><span class="pool-dot" style="background:<?= h($p['color']) ?>"></span>
            <b><?= h($p['name']) ?></b></div>
        <?php if ($p['description']): ?><div class="rc-muted" style="margin:4px 0 10px"><?= h($p['description']) ?></div><?php endif; ?>
        <div class="pool-count"><a href="/hrm/candidates?pool_id=<?= $p['id'] ?>"><b><?= (int)$p['cand_count'] ?></b> ứng viên</a></div>
        <div class="pool-actions">
            <button class="rc-btn ghost" style="padding:5px 12px" onclick='openPool(<?= (int)$p['id'] ?>, <?= json_encode($p, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Sửa</button>
            <button class="rc-btn ghost" style="padding:5px 12px;color:#dc2626" onclick="delPool(<?= (int)$p['id'] ?>, <?= (int)$p['cand_count'] ?>)">Xóa</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div id="poolModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:440px;max-width:94vw">
        <h3 style="font-size:15px;margin-bottom:12px" id="poolTitle">Tạo pool</h3>
        <form id="poolForm" onsubmit="return false">
            <input type="hidden" name="id" id="pl_id" value="0">
            <div class="rc-field"><label>Tên pool *</label><input name="name" id="pl_name" required placeholder="VD: Java Senior"></div>
            <div class="rc-field"><label>Mô tả</label><input name="description" id="pl_desc" placeholder="Ngắn gọn mục đích pool"></div>
            <div class="rc-field"><label>Màu</label><input type="color" name="color" id="pl_color" value="#7c3aed" style="width:60px;height:38px;padding:2px"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
                <button type="button" class="rc-btn ghost" onclick="document.getElementById('poolModal').style.display='none'">Hủy</button>
                <button type="button" class="rc-btn" onclick="savePool()">Lưu</button>
            </div>
        </form>
    </div>
</div>

<style>
.pool-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.pool-card{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:16px 18px}
.pool-top{display:flex;align-items:center;gap:9px;font-size:15px}
.pool-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0}
.pool-count{margin-bottom:12px}.pool-count a{color:#0e7490;text-decoration:none;font-size:13px}
.pool-actions{display:flex;gap:8px}
</style>
<script>
function openPool(id, d){
    document.getElementById('pl_id').value=id||0;
    document.getElementById('poolTitle').textContent=id?'Sửa pool':'Tạo pool';
    document.getElementById('pl_name').value=d?(d.name||''):'';
    document.getElementById('pl_desc').value=d?(d.description||''):'';
    document.getElementById('pl_color').value=d&&d.color?d.color:'#7c3aed';
    document.getElementById('poolModal').style.display='flex';
}
function savePool(){
    const f=document.getElementById('poolForm');if(!f.name.value.trim()){alert('Nhập tên pool');return;}
    const fd=new FormData(f);fd.append('action','pool_save');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function delPool(id,n){
    if(!confirm((n>0?('Pool còn '+n+' ứng viên. '):'')+'Ẩn pool này? (ứng viên vẫn giữ)'))return;
    const fd=new FormData();fd.append('action','pool_del');fd.append('id',id);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
</script>
<?php
hrm_footer();
