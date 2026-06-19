<?php
/**
 * B2-B3 - Tin tuyển dụng. Rich list (Base-style columns) + create from HRF + Excel import.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid = (int)$_SESSION['user_id'];

// Lookup maps.
$deptName = [];
foreach ($conn->query('SELECT id,name FROM departments') as $d) { $deptName[(int)$d['id']] = $d['name']; }
$office = [];
foreach ($conn->query('SELECT id,name,address FROM hrm_offices') as $o) { $office[(int)$o['id']] = $o; }

// Approved HRFs not yet turned into a job.
$openHrf = $conn->query("SELECT id, code, title, quantity FROM hrm_requests WHERE status='approved' AND job_id=0 ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Filters.
$q      = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$fDept   = (int)($_GET['dept'] ?? 0);
$tab     = $_GET['tab'] ?? 'all';          // all | mine
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 50; $offset = ($page - 1) * $per;

$where = []; $params = []; $types = '';
if ($q !== '')       { $where[] = '(j.title LIKE ? OR j.code LIKE ? OR j.external_id LIKE ?)'; $l = "%$q%"; array_push($params, $l, $l, $l); $types .= 'sss'; }
if ($fStatus !== '') { $where[] = 'j.status = ?'; $params[] = $fStatus; $types .= 's'; }
if ($fDept > 0)      { $where[] = 'j.department_id = ?'; $params[] = $fDept; $types .= 'i'; }
if ($tab === 'mine') { $where[] = 'j.created_by = ?'; $params[] = $uid; $types .= 'i'; }
$wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Total count.
$cst = $conn->prepare("SELECT COUNT(*) FROM hrm_jobs j$wsql");
if ($types) { $cst->bind_param($types, ...$params); }
$cst->execute(); $total = (int)$cst->get_result()->fetch_row()[0];

// Page rows with stats.
$sql = "SELECT j.*, u.full_name AS creator,
        (SELECT COUNT(*) FROM hrm_applications a WHERE a.job_id=j.id) AS apps,
        (SELECT COUNT(*) FROM hrm_applications a WHERE a.job_id=j.id AND a.status='hired') AS hired,
        (SELECT COUNT(DISTINCT i.application_id) FROM hrm_interviews i JOIN hrm_applications a ON a.id=i.application_id WHERE a.job_id=j.id) AS interviewed
        FROM hrm_jobs j LEFT JOIN users u ON u.id=j.created_by $wsql
        ORDER BY COALESCE(j.source_created, j.source_start) DESC, CAST(j.external_id AS UNSIGNED) DESC, j.created_at DESC, j.id DESC LIMIT ?, ?";
$st = $conn->prepare($sql);
$pt = $types . 'ii'; $pp = $params; $pp[] = $offset; $pp[] = $per;
$st->bind_param($pt, ...$pp);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$statusMeta = [
    'open'    => ['Đang tuyển', '#16a34a'],
    'draft'   => ['Bản nháp', '#94a3b8'],
    'on_hold' => ['Tạm dừng', '#d97706'],
    'closed'  => ['Đã đóng', '#dc2626'],
];
$today = strtotime('today');

hrm_header('Tin tuyển dụng', 'Danh sách tin tuyển dụng', 'jobs');
?>
<style>
/* Apple-style table */
.jb-scroll{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.04),0 0 0 1px rgba(0,0,0,.04);overflow-x:auto}
.jb-table{width:100%;border-collapse:collapse;
    font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','Segoe UI',Roboto,sans-serif;
    font-size:13px;color:#1d1d1f;-webkit-font-smoothing:antialiased}
.jb-table th{background:#fbfbfd;text-align:left;font-size:11px;font-weight:600;letter-spacing:.2px;color:#86868b;padding:14px 16px;border-bottom:1px solid #f0f0f2;white-space:nowrap}
.jb-table td{padding:16px;border-bottom:1px solid #f5f5f7;vertical-align:top;color:#1d1d1f;line-height:1.5}
.jb-table tr:last-child td{border-bottom:none}
.jb-table tr:hover td{background:#fafafa}
.jb-table th:first-child,.jb-table td:first-child{position:sticky;left:0;z-index:2;background:#fff;box-shadow:1px 0 0 #f0f0f2;min-width:380px;width:380px}
.jb-table th:first-child{background:#fbfbfd;z-index:3}
.jb-table tr:hover td:first-child{background:#fafafa}
.jb-table tbody tr:nth-child(odd) td,.jb-table tbody tr:nth-child(odd) td:first-child{background:#fafafb}
.jb-table tbody tr:hover td,.jb-table tbody tr:hover td:first-child{background:#f0f4fb !important}
.jb-title{font-weight:600;color:#0071e3;text-decoration:none;font-size:14px;letter-spacing:-.01em}
.jb-title:hover{text-decoration:underline}
.jb-tag{display:inline-block;font-size:9.5px;font-weight:600;letter-spacing:.2px;padding:3px 9px;border-radius:980px;margin:6px 5px 0 0}
.jb-tag.t{background:#f5f5f7;color:#6e6e73}.jb-tag.late{background:#fff1f0;color:#d70015}
.jb-dot{width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:7px;vertical-align:middle}
.jb-sub{font-size:12px;color:#86868b;margin-top:4px}
.jb-stat b{font-size:16px;color:#1d1d1f;font-weight:600}
</style>

<div class="rc-toolbar">
    <div class="rc-tabs">
        <a href="?tab=all" class="rc-tab <?= $tab==='all'?'active':'' ?>">Tất cả tin</a>
        <a href="?tab=mine" class="rc-tab <?= $tab==='mine'?'active':'' ?>">Tôi đã tạo</a>
    </div>
    <button class="rc-btn" onclick="document.getElementById('impJobModal').style.display='flex'">Import Excel</button>
</div>

<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <input name="q" value="<?= h($q) ?>" placeholder="Tìm tiêu đề / mã tin / ID" style="flex:1;min-width:220px;padding:8px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px">
    <select name="status" style="padding:8px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px">
        <option value="">Tất cả trạng thái</option>
        <?php foreach ($statusMeta as $k => $v): ?><option value="<?= $k ?>"<?= $fStatus===$k?' selected':'' ?>><?= $v[0] ?></option><?php endforeach; ?>
    </select>
    <select name="dept" style="padding:8px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px;max-width:220px">
        <option value="0">Tất cả phòng ban</option>
        <?php foreach ($deptName as $idd => $nm): ?><option value="<?= $idd ?>"<?= $fDept===$idd?' selected':'' ?>><?= h($nm) ?></option><?php endforeach; ?>
    </select>
    <button class="rc-btn ghost">Lọc</button>
</form>

<?php if ($openHrf): ?>
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">HRF đã duyệt - tạo tin tuyển dụng</h3>
    <table class="rc-table"><tbody>
    <?php foreach ($openHrf as $r): ?>
        <tr><td><b><?= h($r['code']) ?></b></td><td><?= h($r['title']) ?></td><td><?= (int)$r['quantity'] ?> vị trí</td>
            <td style="text-align:right"><button class="rc-btn" onclick="createJob(<?= $r['id'] ?>)">Tạo tin</button></td></tr>
    <?php endforeach; ?>
    </tbody></table>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="rc-empty"><?= $total ? 'Không có tin khớp bộ lọc.' : 'Chưa có tin tuyển dụng. Tạo từ HRF đã duyệt hoặc Import Excel.' ?></div>
<?php else: ?>
<div class="jb-scroll">
<table class="jb-table">
    <thead><tr>
        <th>Tin tuyển dụng</th><th>Bộ phận</th><th>Địa điểm văn phòng</th><th>Trạng thái</th>
        <th>SLA</th><th>ID tin</th><th>Mã vị trí</th><th>Ứng viên</th><th>Phỏng vấn</th>
        <th>Người quản lý</th><th>Người đăng tin</th><th>Phụ trách mặc định</th>
        <th>Thời gian tạo</th><th>Bắt đầu tuyển</th><th>Kết thúc tuyển</th><th>Ghi chú</th><th>Đề xuất tuyển dụng</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $j):
        $sm = $statusMeta[$j['status']] ?? [$j['status'], '#94a3b8'];
        $overdue = $j['deadline'] && strtotime($j['deadline']) < $today && $j['status'] === 'open';
        $off = $office[(int)$j['office_id']] ?? null;
    ?>
        <tr>
            <td>
                <a class="jb-title" href="/hrm/job?id=<?= $j['id'] ?>"><?= h($j['title']) ?></a>
                <div><span class="jb-tag t">Tin tuyển dụng</span><?php if ($overdue): ?><span class="jb-tag late">Quá hạn</span><?php endif; ?></div>
                <div class="jb-sub"><?= h($j['level'] ?: 'Nhân viên') ?> · <?= $j['salary_max']>0 ? number_format($j['salary_min']).'-'.number_format($j['salary_max']).' đ' : 'Thỏa thuận' ?></div>
                <div class="jb-sub">Tạo bởi <?= h($j['creator'] ?: '-') ?> · <?= date('d/m/Y', strtotime($j['created_at'])) ?><?= $j['deadline'] ? ' - ' . date('d/m/Y', strtotime($j['deadline'])) : '' ?></div>
            </td>
            <td><?= h($deptName[(int)$j['department_id']] ?? 'Chưa chọn') ?></td>
            <td style="max-width:220px">
                <?php if ($off): ?><div style="font-weight:600"><?= h($off['name']) ?></div><?php if ($off['address']): ?><div class="jb-sub"><?= h($off['address']) ?></div><?php endif; ?>
                <?php else: ?><span class="rc-muted">-</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap"><span class="jb-dot" style="background:<?= $sm[1] ?>"></span><?= h($sm[0]) ?>
                <div class="jb-sub"><?= $j['status']==='open'?'Đang đăng':'Chưa đăng' ?></div></td>
            <td style="white-space:nowrap">Số lượng: <b><?= (int)$j['headcount'] ?></b>
                <div class="jb-sub" style="<?= $overdue?'color:#dc2626':'' ?>"><?= $j['deadline'] ? 'Hạn: '.date('d/m/Y', strtotime($j['deadline'])) . ($overdue?' (quá hạn)':'') : 'Không thời hạn' ?></div></td>
            <td><?= h($j['external_id'] ?: ('#'.$j['id'])) ?></td>
            <td><?= h($j['code'] && $j['code']!==$j['external_id'] ? $j['code'] : '-') ?></td>
            <td class="jb-stat"><b><?= (int)$j['apps'] ?></b> ứng viên<div class="jb-sub">Đã tuyển: <?= (int)$j['hired'] ?>/<?= (int)$j['headcount'] ?></div></td>
            <td class="jb-stat"><b><?= (int)$j['interviewed'] ?></b> phỏng vấn</td>
            <td style="max-width:160px"><?= h($j['managers'] ?: '-') ?></td>
            <td><?= h($j['poster'] ?: ($j['creator'] ?: '-')) ?></td>
            <td><span class="rc-muted">-</span></td>
            <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($j['source_created'] ?: $j['created_at'])) ?></td>
            <td style="white-space:nowrap"><?= $j['source_start'] ? date('d/m/Y', strtotime($j['source_start'])) : '-' ?></td>
            <td style="white-space:nowrap"><?= $j['deadline'] ? date('d/m/Y', strtotime($j['deadline'])) : '-' ?></td>
            <td style="max-width:160px"><?= h($j['note'] ?: '-') ?></td>
            <td style="white-space:nowrap"><?= (int)$j['request_id'] > 0 ? '<a href="/hrm/requests?id=' . (int)$j['request_id'] . '" style="color:var(--rc2);font-weight:600">Xem HRF</a>' : '<span class="rc-muted">Không có đề xuất liên kết</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
$pages = (int)ceil($total / $per);
if ($pages > 1):
    $qs = $_GET; ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
    <div class="rc-muted">Hiển thị <?= $offset + 1 ?>-<?= min($offset + $per, $total) ?> / <?= $total ?> tin</div>
    <div style="display:flex;gap:6px">
        <?php if ($page > 1): $qs['page'] = $page - 1; ?><a class="rc-tab" href="?<?= h(http_build_query($qs)) ?>">← Trước</a><?php endif; ?>
        <span class="rc-tab active">Trang <?= $page ?>/<?= $pages ?></span>
        <?php if ($page < $pages): $qs['page'] = $page + 1; ?><a class="rc-tab" href="?<?= h(http_build_query($qs)) ?>">Sau →</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Import modal -->
<div id="impJobModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:480px;max-width:92vw">
        <h3 style="font-size:15px;margin-bottom:8px">Import tin tuyển dụng từ Excel</h3>
        <div class="rc-muted" style="margin-bottom:12px">Chọn file .xlsx xuất từ Base E-Hiring. Tự nhận diện cột, khớp phòng ban/văn phòng theo tên, chống trùng theo ID.</div>
        <form id="impJobForm" onsubmit="return false">
            <div class="rc-field"><input type="file" name="file" accept=".xlsx" required></div>
            <div id="impJobResult" class="rc-muted" style="margin-bottom:10px"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="rc-btn ghost" onclick="document.getElementById('impJobModal').style.display='none'">Đóng</button>
                <button type="button" class="rc-btn" id="impJobBtn" onclick="doImportJobs()">Import</button>
            </div>
        </form>
    </div>
</div>
<script>
function createJob(hrfId){
    const fd=new FormData();fd.append('action','create_job');fd.append('hrf_id',hrfId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.href='/hrm/job?id='+j.id+'&edit=1':alert(j.error||'Lỗi');});
}
function doImportJobs(){
    const f=document.getElementById('impJobForm');
    if(!f.file.files.length){alert('Chọn file');return;}
    const fd=new FormData(f);fd.append('action','import_jobs');
    document.getElementById('impJobBtn').disabled=true;
    document.getElementById('impJobResult').textContent='Đang import...';
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        document.getElementById('impJobBtn').disabled=false;
        if(j.ok){document.getElementById('impJobResult').innerHTML='✓ Thêm mới: <b>'+j.inserted+'</b> · Cập nhật: <b>'+j.updated+'</b> · Bỏ qua: '+j.skipped;setTimeout(()=>location.reload(),900);}
        else{document.getElementById('impJobResult').textContent='Lỗi: '+(j.error||'');}
    }).catch(()=>{document.getElementById('impJobBtn').disabled=false;document.getElementById('impJobResult').textContent='Lỗi kết nối';});
}
</script>
<?php
hrm_footer();
