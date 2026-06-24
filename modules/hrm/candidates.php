<?php
/**
 * Kho ứng viên - danh sách + lọc nâng cao + thao tác hàng loạt + xuất + thêm thủ công.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/candidates.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$f    = hrm_candidate_filters();
$opts = hrm_candidate_filter_options($conn);
$rows = hrm_candidate_query($conn, $f, 500);
$statuses = hrm_candidate_statuses();
$total = (int)($conn->query("SELECT COUNT(*) c FROM hrm_candidates WHERE status<>'archived'")->fetch_assoc()['c'] ?? 0);
$qs = http_build_query(array_filter($f, fn($v) => $v !== '' && $v !== 0 && $v !== -1));

hrm_header('Ứng viên', 'Kho ứng viên (' . $total . ')', 'candidates');
?>
<form class="cd-filters" method="get" id="filterForm">
    <input name="q" value="<?= h($f['q']) ?>" placeholder="Tìm tên / email / SĐT / vị trí" class="cd-in" style="min-width:240px">
    <select name="source" class="cd-in"><option value="0">Tất cả nguồn</option>
        <?php foreach ($opts['sources'] as $s): ?><option value="<?= $s['id'] ?>"<?= $f['source']===(int)$s['id']?' selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select>
    <select name="event" class="cd-in"><option value="0">Tất cả sự kiện</option>
        <?php foreach ($opts['events'] as $e): ?><option value="<?= $e['id'] ?>"<?= $f['event']===(int)$e['id']?' selected':'' ?>><?= h($e['name']) ?></option><?php endforeach; ?></select>
    <select name="status" class="cd-in"><option value="">Mọi trạng thái</option>
        <?php foreach ($statuses as $k=>$lbl): ?><option value="<?= $k ?>"<?= $f['status']===$k?' selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?></select>
    <select name="owner" class="cd-in"><option value="0">Mọi người phụ trách</option>
        <?php foreach ($opts['owners'] as $o): ?><option value="<?= $o['id'] ?>"<?= $f['owner']===(int)$o['id']?' selected':'' ?>><?= h($o['full_name']) ?></option><?php endforeach; ?></select>
    <input name="skill" value="<?= h($f['skill']) ?>" placeholder="Kỹ năng" class="cd-in" style="width:130px">
    <input name="tag" value="<?= h($f['tag']) ?>" placeholder="Thẻ" class="cd-in" style="width:110px">
    <select name="pool" class="cd-in"><option value="">Pool: tất cả</option>
        <option value="1"<?= $f['pool']===1?' selected':'' ?>>Trong pool</option>
        <option value="0"<?= $f['pool']===0?' selected':'' ?>>Ngoài pool</option></select>
    <select name="has_cv" class="cd-in"><option value="">CV: tất cả</option>
        <option value="1"<?= $f['has_cv']==='1'?' selected':'' ?>>Có CV</option>
        <option value="0"<?= $f['has_cv']==='0'?' selected':'' ?>>Chưa có CV</option></select>
    <input type="date" name="from" value="<?= h($f['from']) ?>" class="cd-in" title="Tạo từ ngày">
    <input type="date" name="to" value="<?= h($f['to']) ?>" class="cd-in" title="Đến ngày">
    <button class="rc-btn ghost">Lọc</button>
    <a href="/hrm/candidates" class="rc-btn ghost">Xóa lọc</a>
    <div style="flex:1"></div>
    <a class="rc-btn ghost" href="/hrm/candidates/export?fmt=xls&<?= h($qs) ?>">Xuất Excel</a>
    <a class="rc-btn ghost" href="/hrm/candidates/export?fmt=csv&<?= h($qs) ?>">CSV</a>
    <button type="button" class="rc-btn" onclick="document.getElementById('addModal').style.display='flex'">+ Thêm ứng viên</button>
    <a class="rc-btn ghost" href="/hrm/candidates/import">Import Excel</a>
    <button type="button" class="rc-btn ghost" onclick="linkPipeline()" title="Gắn ứng viên đã có 'Tin ứng tuyển' vào pipeline tin tương ứng">Đồng bộ pipeline</button>
</form>

<!-- Thanh thao tác hàng loạt -->
<div id="bulkBar" style="display:none" class="cd-bulk">
    <span><b id="bulkCount">0</b> đã chọn</span>
    <input id="bulkTag" class="cd-in" placeholder="Thêm thẻ..." style="width:140px">
    <button class="rc-btn ghost" onclick="bulk('tag', document.getElementById('bulkTag').value)">Gắn thẻ</button>
    <select id="bulkStatus" class="cd-in"><option value="">Đổi trạng thái...</option>
        <?php foreach ($statuses as $k=>$lbl): ?><option value="<?= $k ?>"><?= h($lbl) ?></option><?php endforeach; ?></select>
    <button class="rc-btn ghost" onclick="bulk('status', document.getElementById('bulkStatus').value)">Áp dụng</button>
    <button class="rc-btn ghost" onclick="bulk('pool','')">+ Talent pool</button>
    <button class="rc-btn ghost" id="mergeBtn" style="display:none" onclick="mergeTwo()">Gộp 2 hồ sơ</button>
    <button class="rc-btn ghost" style="color:#dc2626" onclick="if(confirm('Lưu trữ các ứng viên đã chọn?'))bulk('delete','')">Lưu trữ</button>
</div>

<?php
$pal = ['#0071e3','#34c759','#ff9500','#af52de','#ff2d55','#5ac8fa','#ffcc00','#ff3b30','#30b0c7','#a2845e'];
$avatar = function ($name) use ($pal) {
    $p = preg_split('/\s+/', trim($name));
    $ini = mb_strtoupper(mb_substr(end($p) ?: $name, 0, 1) . (count($p) > 1 ? mb_substr($p[0], 0, 1) : ''));
    return [$ini, $pal[abs(crc32($name)) % count($pal)]];
};
$stCol = ['new'=>'#0071e3','active'=>'#b45309','pooled'=>'#7c3aed','hired'=>'#16a34a','blacklist'=>'#dc2626','archived'=>'#64748b'];
?>
<?php if (!$rows): ?>
    <div class="rc-empty"><?= $total ? 'Không có ứng viên khớp bộ lọc.' : 'Chưa có ứng viên. Bấm "+ Thêm ứng viên" hoặc "Import Excel".' ?></div>
<?php else: ?>
<div class="cd-scroll">
<table class="cd-table">
    <thead><tr>
        <th style="width:34px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
        <th>Họ và tên</th><th>Trạng thái</th><th>Liên hệ</th><th>Kỹ năng</th><th>Thẻ</th>
        <th>Nguồn</th><th>Sự kiện</th><th>Phụ trách</th><th>Vị trí ứng tuyển</th><th>Giai đoạn</th>
        <th>Đánh giá</th><th>Ngày tạo</th><th>CV</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $c): [$ini, $col] = $avatar($c['full_name']); ?>
        <tr data-id="<?= $c['id'] ?>">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="rowChk" value="<?= $c['id'] ?>" onclick="onCheck()"></td>
            <td onclick="location.href='/hrm/candidate?id=<?= $c['id'] ?>'"><div class="cd-name-cell">
                <span class="cd-av" style="background:<?= $col ?>"><?= h($ini) ?></span>
                <div style="min-width:0"><div class="cd-nm"><?= h($c['full_name']) ?></div>
                    <div class="cd-sub"><?= h($c['current_position'] ?: 'Không có chức danh') ?></div></div>
            </div></td>
            <td><span class="cd-badge" style="background:<?= ($stCol[$c['status']]??'#64748b') ?>1a;color:<?= $stCol[$c['status']]??'#64748b' ?>"><?= h($statuses[$c['status']] ?? $c['status']) ?></span><?= $c['talent_pool'] ? ' <span class="cd-badge" style="background:#f3e8ff;color:#7c3aed">Pool</span>' : '' ?></td>
            <td onclick="location.href='/hrm/candidate?id=<?= $c['id'] ?>'"><?= h($c['email'] ?: '-') ?><?php if ($c['phone']): ?><div class="cd-sub"><?= h($c['phone']) ?></div><?php endif; ?></td>
            <td class="cd-sub" style="max-width:200px;white-space:normal"><?= h($c['skill_list'] ?: '-') ?></td>
            <td><?= $c['tag_list'] ? implode(' ', array_map(fn($t)=>'<span class="cd-badge">'.h($t).'</span>', explode(',', $c['tag_list']))) : '-' ?></td>
            <td><?= h($c['source_name'] ?: '-') ?></td>
            <td><?= h($c['event_name'] ?: '-') ?></td>
            <td><?= h($c['owner_name'] ?: '-') ?></td>
            <td><?= h($c['app_job'] ?: ($c['applied_job'] ?: '-')) ?></td>
            <td><?php $stg=$c['app_stage'] ?: $c['applied_stage']; echo $stg ? '<span class="cd-badge">'.h($stg).'</span>' : '-'; ?></td>
            <td><?= (int)$c['rating'] ? str_repeat('★', (int)$c['rating']) : '-' ?></td>
            <td class="cd-sub"><?= $c['created_at'] ? date('d/m/Y', strtotime($c['created_at'])) : '-' ?></td>
            <td><?= $c['cv_path'] ? '<a href="'.h($c['cv_path']).'" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="cd-cv">Xem CV</a>' : '-' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<style>
.cd-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.cd-in{padding:8px 11px;border:1px solid var(--bd);border-radius:8px;font-size:13px;background:#fff}
.cd-bulk{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#eef6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px}
.cd-scroll{background:#fff;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.04),0 0 0 1px rgba(0,0,0,.04);overflow-x:auto;scrollbar-width:none}
.cd-scroll::-webkit-scrollbar{display:none}
.cd-table{width:100%;border-collapse:collapse;font-size:13px;color:#1d1d1f}
.cd-table th{background:#fbfbfd;text-align:left;font-size:11px;font-weight:600;color:#86868b;padding:13px 16px;border-bottom:1px solid #f0f0f2;white-space:nowrap}
.cd-table td{padding:12px 16px;border-bottom:1px solid #f5f5f7;vertical-align:middle;white-space:nowrap;cursor:pointer}
.cd-table tbody tr:hover td{background:#f7faff}
.cd-name-cell{display:flex;align-items:center;gap:10px}
.cd-av{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px}
.cd-nm{font-weight:600;color:#0071e3;font-size:13.5px}
.cd-sub{font-size:12px;color:#86868b;margin-top:2px}
.cd-badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:980px;background:#eef6ff;color:#0071e3;margin:1px}
.cd-cv{color:#0071e3;font-weight:600;text-decoration:none}.cd-cv:hover{text-decoration:underline}
</style>

<!-- Modal thêm ứng viên -->
<div id="addModal" class="cd-modal">
    <div class="rc-card" style="width:520px;max-width:94vw">
        <h3 style="font-size:15px;margin-bottom:12px">Thêm ứng viên vào kho</h3>
        <form id="addForm" onsubmit="return false">
            <div class="rc-grid2">
                <div class="rc-field"><label>Họ tên *</label><input name="full_name" required></div>
                <div class="rc-field"><label>Email</label><input name="email" type="email"></div>
                <div class="rc-field"><label>Điện thoại</label><input name="phone"></div>
                <div class="rc-field"><label>Vị trí gần nhất</label><input name="current_position"></div>
                <div class="rc-field"><label>Nguồn</label><select name="source_id"><option value="0">-</option>
                    <?php foreach ($opts['sources'] as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Sự kiện</label><select name="event_id"><option value="0">-</option>
                    <?php foreach ($opts['events'] as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div id="addErr" class="rc-muted" style="color:#dc2626;margin:6px 0"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="rc-btn ghost" onclick="document.getElementById('addModal').style.display='none'">Hủy</button>
                <button type="button" class="rc-btn" id="addBtn" onclick="addCand(false)">Lưu</button>
            </div>
        </form>
    </div>
</div>

<style>.cd-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center}</style>
<script>
function selectedIds(){return Array.from(document.querySelectorAll('.rowChk:checked')).map(c=>c.value);}
function onCheck(){const n=selectedIds().length;document.getElementById('bulkCount').textContent=n;document.getElementById('bulkBar').style.display=n?'flex':'none';document.getElementById('mergeBtn').style.display=n===2?'inline-flex':'none';}
function mergeTwo(){const ids=selectedIds();if(ids.length!==2){alert('Chọn đúng 2 hồ sơ để gộp');return;}location.href='/hrm/candidates/merge?a='+ids[0]+'&b='+ids[1];}
function linkPipeline(){
    if(!confirm('Gắn các ứng viên (có "Tin ứng tuyển" gốc) vào pipeline của tin tương ứng?'))return;
    const fd=new FormData();fd.append('action','cand_link_pipeline');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok)alert('Đã gắn '+j.linked+' ứng viên vào pipeline.'+(j.no_job?(' '+j.no_job+' ứng viên không tìm thấy tin khớp tên.'):''));
        else alert(j.error||'Lỗi');
        if(j.ok)location.reload();
    }).catch(()=>alert('Lỗi kết nối'));
}
function toggleAll(cb){document.querySelectorAll('.rowChk').forEach(c=>c.checked=cb.checked);onCheck();}
function bulk(op,value){
    const ids=selectedIds();if(!ids.length){alert('Chưa chọn ứng viên');return;}
    if((op==='tag'||op==='status')&&!value){alert('Nhập/chọn giá trị');return;}
    const fd=new FormData();fd.append('action','cand_bulk');fd.append('op',op);fd.append('value',value);fd.append('ids',ids.join(','));
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function addCand(force){
    const f=document.getElementById('addForm');if(!f.full_name.value.trim()){alert('Nhập họ tên');return;}
    const fd=new FormData(f);fd.append('action','cand_create');if(force)fd.append('force','1');
    document.getElementById('addBtn').disabled=true;document.getElementById('addErr').textContent='';
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        document.getElementById('addBtn').disabled=false;
        if(j.ok){location.href='/hrm/candidate?id='+j.id;return;}
        if(j.dup_id){document.getElementById('addErr').innerHTML=j.error+' <a href="/hrm/candidate?id='+j.dup_id+'">Mở hồ sơ</a> · <a href="#" onclick="addCand(true);return false;">Vẫn tạo</a>';}
        else document.getElementById('addErr').textContent=j.error||'Lỗi';
    }).catch(()=>{document.getElementById('addBtn').disabled=false;document.getElementById('addErr').textContent='Lỗi kết nối';});
}
</script>
<?php
hrm_footer();
