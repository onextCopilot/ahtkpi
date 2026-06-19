<?php
/**
 * Candidate pool - list / search + Excel import (Base E-Hiring export format).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$q   = trim($_GET['q'] ?? '');
$src = (int)($_GET['source'] ?? 0);
$sources = $conn->query('SELECT id,name FROM hrm_candidate_sources WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$where = []; $params = []; $types = '';
if ($q !== '')  { $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)'; $like = "%$q%"; array_push($params, $like, $like, $like); $types .= 'sss'; }
if ($src > 0)   { $where[] = 'c.source_id = ?'; $params[] = $src; $types .= 'i'; }
$sql = "SELECT c.*, s.name AS source_name FROM hrm_candidates c LEFT JOIN hrm_candidate_sources s ON s.id=c.source_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.id DESC LIMIT 500';
$st = $conn->prepare($sql);
if ($types) { $st->bind_param($types, ...$params); }
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$total = (int)($conn->query('SELECT COUNT(*) c FROM hrm_candidates')->fetch_assoc()['c'] ?? 0);

hrm_header('Ứng viên', 'Kho ứng viên (' . $total . ')', 'candidates');
?>
<div class="rc-toolbar">
    <form class="rc-tabs" method="get" style="gap:8px">
        <input name="q" value="<?= h($q) ?>" placeholder="Tìm tên / email / SĐT" style="padding:8px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px;min-width:220px">
        <select name="source" style="padding:8px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px">
            <option value="0">Tất cả nguồn</option>
            <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"<?= $src===(int)$s['id']?' selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="rc-btn ghost">Lọc</button>
    </form>
    <button class="rc-btn" onclick="document.getElementById('impModal').style.display='flex'">Import Excel</button>
</div>

<?php
$pal = ['#0071e3','#34c759','#ff9500','#af52de','#ff2d55','#5ac8fa','#ffcc00','#ff3b30','#30b0c7','#a2845e'];
$avatar = function ($name) use ($pal) {
    $p = preg_split('/\s+/', trim($name));
    $ini = mb_strtoupper(mb_substr(end($p) ?: $name, 0, 1) . (count($p) > 1 ? mb_substr($p[0], 0, 1) : ''));
    return [$ini, $pal[abs(crc32($name)) % count($pal)]];
};
?>
<?php if (!$rows): ?>
    <div class="rc-empty"><?= $total ? 'Không có ứng viên khớp bộ lọc.' : 'Chưa có ứng viên. Bấm "Import Excel" để nhập từ file Base E-Hiring.' ?></div>
<?php else: ?>
<div class="cd-scroll">
<table class="cd-table">
    <thead><tr>
        <th>Họ và tên</th><th>Thông tin liên hệ</th><th>Phân loại</th><th>Vị trí ứng tuyển</th><th>Giai đoạn</th>
        <th>Lý do từ chối</th><th>Nguồn</th><th>Medium</th><th>Văn phòng</th><th>Thẻ</th><th>Điểm</th>
        <th>Giới tính</th><th>Ngày sinh</th><th>Ngày ứng tuyển</th><th>Ngày phỏng vấn</th><th>Cập nhật cuối</th><th>CV</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $c): [$ini, $col] = $avatar($c['full_name']); ?>
        <tr onclick="location.href='/hrm/candidate?id=<?= $c['id'] ?>'">
            <td><div class="cd-name-cell">
                <span class="cd-av" style="background:<?= $col ?>"><?= h($ini) ?></span>
                <div style="min-width:0"><div class="cd-nm"><?= h($c['full_name']) ?></div>
                    <div class="cd-sub"><?= h($c['current_position'] ?: 'Không có chức danh') ?></div></div>
            </div></td>
            <td><?= h($c['email'] ?: '-') ?><?php if ($c['phone']): ?><div class="cd-sub"><?= h($c['phone']) ?></div><?php endif; ?></td>
            <td><?= h($c['classification'] ?: '-') ?></td>
            <td><?= h($c['applied_job'] ?: '-') ?></td>
            <td><?= $c['applied_stage'] ? '<span class="cd-badge">' . h($c['applied_stage']) . '</span>' : '-' ?></td>
            <td><?= h($c['reject_reason'] ?: '-') ?></td>
            <td><?= h($c['source_name'] ?: '-') ?></td>
            <td><?= h($c['campaign'] ?: '-') ?></td>
            <td><?= h($c['office_text'] ?: '-') ?></td>
            <td><?= $c['tags'] ? '<span class="cd-badge">' . h($c['tags']) . '</span>' : '-' ?></td>
            <td><?= (float)$c['score'] > 0 ? (float)$c['score'] : '-' ?></td>
            <td><?= h($c['gender'] ?: '-') ?></td>
            <td><?= h($c['dob'] ?: '-') ?></td>
            <td class="cd-sub"><?= $c['applied_date'] ? date('d/m/Y', strtotime($c['applied_date'])) : '-' ?></td>
            <td class="cd-sub"><?= h($c['interview_date'] ?: '-') ?></td>
            <td class="cd-sub"><?= h($c['updated_src'] ?: '-') ?></td>
            <td><?= $c['cv_path'] ? '<a href="' . h($c['cv_path']) . '" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="cd-cv">Xem CV</a>' : '-' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<style>
.cd-scroll{background:#fff;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.04),0 0 0 1px rgba(0,0,0,.04);overflow-x:auto;scrollbar-width:none}
.cd-scroll::-webkit-scrollbar{display:none}
.cd-table{width:100%;border-collapse:collapse;font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','Segoe UI',sans-serif;font-size:13px;color:#1d1d1f}
.cd-table th{background:#fbfbfd;text-align:left;font-size:11px;font-weight:600;letter-spacing:.2px;color:#86868b;padding:13px 16px;border-bottom:1px solid #f0f0f2;white-space:nowrap}
.cd-table td{padding:12px 16px;border-bottom:1px solid #f5f5f7;vertical-align:middle;white-space:nowrap}
.cd-table tr{cursor:pointer}
.cd-table tbody tr:hover td{background:#f7faff}
.cd-table th:first-child,.cd-table td:first-child{position:sticky;left:0;background:#fff;z-index:2;box-shadow:1px 0 0 #f0f0f2;min-width:240px}
.cd-table th:first-child{background:#fbfbfd;z-index:3}
.cd-table tbody tr:hover td:first-child{background:#f7faff}
.cd-name-cell{display:flex;align-items:center;gap:10px}
.cd-av{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px}
.cd-nm{font-weight:600;color:#0071e3;font-size:13.5px;letter-spacing:-.01em}
.cd-sub{font-size:12px;color:#86868b;margin-top:2px}
.cd-badge{font-size:11px;font-weight:600;padding:3px 9px;border-radius:980px;background:#eef6ff;color:#0071e3}
.cd-cv{color:#0071e3;font-weight:600;text-decoration:none}.cd-cv:hover{text-decoration:underline}
</style>
<?php endif; ?>

<!-- Import modal -->
<div id="impModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:480px;max-width:92vw">
        <h3 style="font-size:15px;margin-bottom:8px">Import ứng viên từ Excel</h3>
        <div class="rc-muted" style="margin-bottom:12px">Chọn file .xlsx xuất từ Base E-Hiring (Danh sách ứng viên). Hệ thống tự nhận diện cột và chống trùng theo ID/Email.</div>
        <form id="impForm" onsubmit="return false">
            <div class="rc-field"><input type="file" name="file" accept=".xlsx" required></div>
            <div id="impResult" class="rc-muted" style="margin-bottom:10px"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="rc-btn ghost" onclick="document.getElementById('impModal').style.display='none'">Đóng</button>
                <button type="button" class="rc-btn" id="impBtn" onclick="doImport()">Import</button>
            </div>
        </form>
    </div>
</div>
<script>
function doImport(){
    const f=document.getElementById('impForm');
    if(!f.file.files.length){alert('Chọn file');return;}
    const fd=new FormData(f);fd.append('action','import_candidates');
    document.getElementById('impBtn').disabled=true;
    document.getElementById('impResult').textContent='Đang import...';
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        document.getElementById('impBtn').disabled=false;
        if(j.ok){document.getElementById('impResult').innerHTML='✓ Thêm mới: <b>'+j.inserted+'</b> · Cập nhật: <b>'+j.updated+'</b> · Bỏ qua: '+j.skipped;setTimeout(()=>location.reload(),900);}
        else{document.getElementById('impResult').textContent='Lỗi: '+(j.error||'');}
    }).catch(()=>{document.getElementById('impBtn').disabled=false;document.getElementById('impResult').textContent='Lỗi kết nối';});
}
</script>
<?php
hrm_footer();
