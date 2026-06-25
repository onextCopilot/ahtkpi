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
$openHrf = $conn->query("SELECT rq.*, u.full_name AS creator_name
    FROM hrm_requests rq
    LEFT JOIN users u ON u.id = rq.created_by
    WHERE rq.status='approved' AND rq.job_id=0 ORDER BY rq.id DESC")->fetch_all(MYSQLI_ASSOC);

// Khoảng lương dạng chuỗi.
$fmtHrfSalary = function (array $r): string {
    $cur = $r['currency'] ?: 'VND';
    $mn = (float)$r['salary_min']; $mx = (float)$r['salary_max'];
    if ($mn > 0 && $mx > 0) { return number_format($mn) . ' - ' . number_format($mx) . ' ' . $cur; }
    if ($mn > 0) { return 'Từ ' . number_format($mn) . ' ' . $cur; }
    if ($mx > 0) { return 'Tối đa ' . number_format($mx) . ' ' . $cur; }
    return 'Thỏa thuận';
};

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

// Stat cards — tổng quan theo tab scope (all | mine), không phụ thuộc bộ lọc tìm kiếm.
$statWhere = ($tab === 'mine') ? ' WHERE j.created_by = ' . $uid : '';
$srow = $conn->query("SELECT
        COUNT(*) AS total,
        SUM(j.status='open') AS open_cnt,
        SUM(j.status='open' AND j.deadline IS NOT NULL AND j.deadline < CURDATE()) AS overdue_cnt,
        SUM(j.status='draft') AS draft_cnt
        FROM hrm_jobs j$statWhere")->fetch_assoc();
$arow = $conn->query("SELECT COUNT(*) AS apps, SUM(a.status='hired') AS hired
        FROM hrm_applications a JOIN hrm_jobs j ON j.id=a.job_id$statWhere")->fetch_assoc();
$stat = [
    'total'   => (int)($srow['total'] ?? 0),
    'open'    => (int)($srow['open_cnt'] ?? 0),
    'overdue' => (int)($srow['overdue_cnt'] ?? 0),
    'draft'   => (int)($srow['draft_cnt'] ?? 0),
    'apps'    => (int)($arow['apps'] ?? 0),
    'hired'   => (int)($arow['hired'] ?? 0),
];

// Phụ trách mặc định: lấy BC + TA của giai đoạn đầu (sort_order nhỏ nhất)
// theo quy luật assign trong cài đặt (hrm_stage_owners, áp dụng theo phòng ban).
$firstStageId = (int)($conn->query('SELECT id FROM hrm_pipeline_stages ORDER BY sort_order, id LIMIT 1')->fetch_row()[0] ?? 0);
$deptOwners = [];   // department_id => ['bc' => name, 'ta' => name]
if ($firstStageId) {
    $ores = $conn->query("SELECT so.department_id, so.owner_type, u.full_name
        FROM hrm_stage_owners so JOIN users u ON u.id = so.user_id
        WHERE so.stage_id = $firstStageId");
    while ($o = $ores->fetch_assoc()) {
        $deptOwners[(int)$o['department_id']][$o['owner_type']] = $o['full_name'];
    }
}

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
/* ── Jobs page — modern shell ── */
/* Cho phép vùng nội dung co lại để bảng rộng dùng cuộn ngang nội bộ, không đẩy cả trang tràn ngang (fix nút bị cắt). */
.main-content{min-width:0}
.jb-wrap{display:grid;gap:18px;padding-bottom:20px;min-width:0;max-width:100%}

/* Toolbar — tabs + stats + action trên cùng một dòng */
.jb-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.jb-toolbar .jb-tabs,.jb-toolbar .jb-actbtn{flex-shrink:0}
.jb-tabs{display:inline-flex;background:#f1f4f8;border:1px solid #e6ebf1;border-radius:11px;padding:4px;gap:2px}
.jb-tab{font-size:13px;font-weight:600;color:#64748b;text-decoration:none;padding:7px 16px;border-radius:8px;transition:.16s;white-space:nowrap}
.jb-tab:hover{color:#0f172a}
.jb-tab.active{background:#fff;color:var(--rc);box-shadow:0 1px 3px rgba(10,37,42,.12),0 0 0 1px rgba(10,37,42,.04)}
.jb-actbtn{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;padding:9px 14px;border-radius:10px;border:1px solid var(--rc);background:linear-gradient(135deg,#0e3a40,#0a252a);color:#fff;box-shadow:0 6px 16px -6px rgba(10,37,42,.55);transition:.16s;white-space:nowrap}
.jb-actbtn:hover{transform:translateY(-1px);box-shadow:0 10px 22px -8px rgba(10,37,42,.6)}
.jb-actbtn svg{width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Dải thống kê gọn (một strip, vạch ngăn mảnh) — không phải card riêng */
.jb-stats{flex:1;min-width:0;display:flex;align-items:stretch;flex-wrap:wrap;background:#fff;border:1px solid #e8ecf0;border-radius:12px;padding:2px}
.jb-card-stat{flex:1 1 0;min-width:94px;display:flex;align-items:center;gap:7px;padding:5px 9px;position:relative;text-decoration:none;border-radius:8px;transition:.14s}
.jb-card-stat:hover{background:#f8fafc}
.jb-card-stat + .jb-card-stat::before{content:"";position:absolute;left:0;top:20%;height:60%;width:1px;background:#eef1f5}
.jb-card-stat .ic{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.jb-card-stat .ic svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.jb-card-stat .meta{min-width:0}
.jb-card-stat .val{font-size:18px;font-weight:800;line-height:1.05;font-variant-numeric:tabular-nums}
.jb-card-stat .lbl{font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.2px;margin-top:1px;white-space:nowrap}

/* Filter bar (card) */
.jb-filters{background:#fff;border:1px solid #e8ecf0;border-radius:14px;padding:12px 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.jb-search{flex:1 1 200px;min-width:160px;position:relative}
.jb-search svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;fill:none;stroke:#94a3b8;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.jb-search input{width:100%;padding:10px 12px 10px 38px;border:1px solid var(--bd);border-radius:10px;font-size:13px;outline:none;transition:.15s;background:#f9fafb}
.jb-search input:focus{border-color:var(--rc2);background:#fff;box-shadow:0 0 0 3px rgba(14,107,92,.1)}
.jb-sel{padding:10px 12px;border:1px solid var(--bd);border-radius:10px;font-size:13px;background:#f9fafb;color:#334155;outline:none;cursor:pointer;transition:.15s}
.jb-sel:focus{border-color:var(--rc2);background:#fff}
.jb-filter-btn{flex-shrink:0;display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;cursor:pointer;padding:10px 18px;border-radius:10px;border:1px solid var(--rc2);background:var(--rc2);color:#fff;transition:.15s}
.jb-filter-btn:hover{filter:brightness(1.05)}

/* HRF approved card */
.jb-hrf{background:linear-gradient(180deg,#f0fdf4,#fff 70%);border:1px solid #bbf7d0;border-radius:16px;padding:18px 20px;box-shadow:0 4px 16px -8px rgba(22,163,74,.25)}
.jb-hrf-hd{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.jb-hrf-hd .badge{display:inline-flex;width:24px;height:24px;border-radius:50%;background:#16a34a;color:#fff;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;box-shadow:0 4px 10px -2px rgba(22,163,74,.5)}
.jb-hrf-hd b{font-size:14.5px;color:#15803d;font-weight:700}
.jb-hrf-hd .cnt{background:#dcfce7;color:#15803d;font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;border:1px solid #bbf7d0}
.jb-hrf-tbl{width:100%;border-collapse:collapse;font-size:12.5px;white-space:nowrap}
.jb-hrf-tbl th{text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#86a892;padding:9px 12px;border-bottom:1px solid #d6f0de}
.jb-hrf-tbl td{padding:11px 12px;border-bottom:1px solid #edf6f0;color:#1f2937}
.jb-hrf-tbl tr:last-child td{border-bottom:none}
.jb-hrf-tbl tr:hover td{background:#f6fdf8}

/* Main list table */
.jb-scroll{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.04),0 0 0 1px #e8ecf0;overflow:hidden}
.jb-scroll-inner{overflow-x:auto}
.jb-table{width:100%;border-collapse:collapse;font-size:13px;color:#0f172a;-webkit-font-smoothing:antialiased}
.jb-table th{background:#f8fafc;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:#94a3b8;padding:13px 16px;border-bottom:1px solid #eef1f5;white-space:nowrap}
.jb-table td{padding:15px 16px;border-bottom:1px solid #f4f6f9;vertical-align:top;line-height:1.5}
.jb-table tr:last-child td{border-bottom:none}
.jb-table th:first-child,.jb-table td:first-child{position:sticky;left:0;z-index:2;background:#fff;box-shadow:1px 0 0 #eef1f5;min-width:340px;width:340px}
.jb-table th:first-child{background:#f8fafc;z-index:3}
.jb-table tbody tr:hover td,.jb-table tbody tr:hover td:first-child{background:#f6f9fe}
.jb-title{font-weight:700;color:var(--rc);text-decoration:none;font-size:14px;letter-spacing:-.01em}
.jb-title:hover{color:var(--rc2)}
.jb-tag{display:inline-block;font-size:10px;font-weight:600;letter-spacing:.2px;padding:3px 9px;border-radius:6px;margin:6px 5px 0 0}
.jb-tag.t{background:#eef2f7;color:#64748b}
.jb-tag.late{background:#fef2f2;color:#dc2626}
.jb-tag.role{background:#f1f4f8;color:#475569;margin:0}
.jb-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:4px 11px;border-radius:99px;white-space:nowrap}
.jb-dot{width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0}
.jb-sub{font-size:12px;color:#94a3b8;margin-top:4px}
.jb-stat b{font-size:16px;color:#0f172a;font-weight:700}
.jb-stat .pos{display:inline-flex;align-items:baseline;gap:4px}
.jb-progress{height:5px;background:#eef2f7;border-radius:99px;overflow:hidden;margin-top:6px;max-width:90px}
.jb-progress i{display:block;height:100%;background:#16a34a;border-radius:99px}
.jb-link2{color:var(--rc2);font-weight:600;text-decoration:none}
.jb-link2:hover{text-decoration:underline}

/* Empty state */
.jb-empty{text-align:center;padding:56px 24px;background:#fff;border:1px solid #e8ecf0;border-radius:16px}
.jb-empty svg{width:46px;height:46px;fill:none;stroke:#cbd5e1;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round;margin-bottom:12px}
.jb-empty .t{font-size:15px;font-weight:700;color:#475569;margin-bottom:4px}
.jb-empty .s{font-size:13px;color:#94a3b8}

/* Pagination */
.jb-pager{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.jb-pager .info{font-size:12.5px;color:#94a3b8}
.jb-pager .nav{display:flex;gap:6px;align-items:center}
.jb-pager a,.jb-pager span{font-size:13px;font-weight:600;padding:7px 14px;border-radius:9px;text-decoration:none}
.jb-pager a{color:#475569;border:1px solid var(--bd);background:#fff;transition:.15s}
.jb-pager a:hover{border-color:var(--rc2);color:var(--rc2)}
.jb-pager .cur{background:var(--rc);color:#fff}
</style>

<div class="jb-wrap">

<!-- Toolbar: tabs + stat cards + action trên cùng một dòng -->
<?php
$statCards = [
    ['val'=>$stat['total'],   'lbl'=>'Tổng tin',     'sub'=>'Toàn bộ tin tuyển dụng', 'color'=>'#0e6b5c', 'bg'=>'#ecfdf5', 'icon'=>'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
    ['val'=>$stat['open'],    'lbl'=>'Đang tuyển',   'sub'=>'Vị trí đang mở',         'color'=>'#16a34a', 'bg'=>'#f0fdf4', 'icon'=>'<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>'],
    ['val'=>$stat['overdue'], 'lbl'=>'Quá hạn',      'sub'=>'Cần xử lý gấp',          'color'=>'#dc2626', 'bg'=>'#fef2f2', 'icon'=>'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'],
    ['val'=>$stat['draft'],   'lbl'=>'Bản nháp',     'sub'=>'Chưa đăng',              'color'=>'#94a3b8', 'bg'=>'#f1f5f9', 'icon'=>'<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>'],
    ['val'=>$stat['apps'],    'lbl'=>'Tổng ứng viên','sub'=>'Trên tất cả tin',        'color'=>'#2563eb', 'bg'=>'#eff6ff', 'icon'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="18" cy="9" r="3"/><path d="m21.5 21-1.5-1.5"/>'],
    ['val'=>$stat['hired'],   'lbl'=>'Đã tuyển',     'sub'=>'Ứng viên trúng tuyển',   'color'=>'#7c3aed', 'bg'=>'#f5f3ff', 'icon'=>'<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
];
?>
<div class="jb-toolbar">
    <div class="jb-tabs">
        <a href="?tab=all" class="jb-tab <?= $tab==='all'?'active':'' ?>">Tất cả tin</a>
        <a href="?tab=mine" class="jb-tab <?= $tab==='mine'?'active':'' ?>">Tôi đã tạo</a>
    </div>
    <div class="jb-stats">
    <?php foreach ($statCards as $sc): ?>
    <div class="jb-card-stat" title="<?= h($sc['sub']) ?>">
        <div class="ic" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>"><svg viewBox="0 0 24 24"><?= $sc['icon'] ?></svg></div>
        <div class="meta">
            <div class="val" style="color:<?= $sc['color'] ?>"><?= (int)$sc['val'] ?></div>
            <div class="lbl"><?= h($sc['lbl']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <button class="jb-actbtn" onclick="document.getElementById('impJobModal').style.display='flex'">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Import Excel
    </button>
</div>

<!-- Filter bar -->
<form method="get" class="jb-filters">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="jb-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input name="q" value="<?= h($q) ?>" placeholder="Tìm tiêu đề / mã tin / ID">
    </div>
    <select name="status" class="jb-sel">
        <option value="">Tất cả trạng thái</option>
        <?php foreach ($statusMeta as $k => $v): ?><option value="<?= $k ?>"<?= $fStatus===$k?' selected':'' ?>><?= $v[0] ?></option><?php endforeach; ?>
    </select>
    <select name="dept" class="jb-sel" style="max-width:220px">
        <option value="0">Tất cả phòng ban</option>
        <?php foreach ($deptName as $idd => $nm): ?><option value="<?= $idd ?>"<?= $fDept===$idd?' selected':'' ?>><?= h($nm) ?></option><?php endforeach; ?>
    </select>
    <button class="jb-filter-btn">Lọc</button>
</form>

<?php if ($openHrf): ?>
<div class="jb-hrf">
    <div class="jb-hrf-hd">
        <span class="badge">✓</span>
        <b>HRF đã duyệt — sẵn sàng tạo tin tuyển dụng</b>
        <span class="cnt"><?= count($openHrf) ?></span>
    </div>
    <div style="overflow-x:auto">
    <table class="jb-hrf-tbl">
        <thead><tr>
            <th>Mã</th><th>Vị trí</th><th>Bộ phận</th><th>Văn phòng</th><th>Level</th><th>SL</th>
            <th>Loại</th><th>Hình thức</th><th>Kinh nghiệm</th><th>Khoảng lương</th><th>Ưu tiên</th>
            <th>Lý do</th><th>Người tạo</th><th>Ngày tạo</th><th>Cần onboard</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($openHrf as $r):
            $off = $office[(int)$r['office_id']] ?? null; ?>
            <tr>
                <td><b><?= h($r['code']) ?></b></td>
                <td><?= h($r['title']) ?></td>
                <td><?= h($deptName[(int)$r['department_id']] ?? '-') ?></td>
                <td><?php if ($off): ?><span style="display:inline-block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom" title="<?= h($off['name']) ?>"><?= h($off['name']) ?></span><?php else: ?>-<?php endif; ?></td>
                <td><?= h($r['level'] ?: '-') ?></td>
                <td><?= (int)$r['quantity'] ?></td>
                <td><?= $r['request_type'] === 'new_hc' ? 'Tuyển mới' : 'Thay thế' ?></td>
                <td><?= h($r['employment_type'] ?: '-') ?></td>
                <td><?= h($r['experience_required'] ?: '-') ?></td>
                <td><?= h($fmtHrfSalary($r)) ?></td>
                <td><?= h($r['priority'] ?: '-') ?></td>
                <td><?= h($r['reason'] ?: '-') ?></td>
                <td><?= h($r['creator_name'] ?: '-') ?></td>
                <td><?= $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-' ?></td>
                <td><?= $r['need_by_date'] ? date('d/m/Y', strtotime($r['need_by_date'])) : '-' ?></td>
                <td style="text-align:right"><button class="jb-filter-btn" style="background:#16a34a;border-color:#16a34a;padding:7px 14px" onclick="createJob(<?= $r['id'] ?>)">+ Tạo tin</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="jb-empty">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        <div class="t"><?= $total ? 'Không có tin khớp bộ lọc' : 'Chưa có tin tuyển dụng' ?></div>
        <div class="s"><?= $total ? 'Thử thay đổi từ khóa hoặc bộ lọc.' : 'Tạo tin từ HRF đã duyệt hoặc Import Excel.' ?></div>
    </div>
<?php else: ?>
<div class="jb-scroll"><div class="jb-scroll-inner">
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
                <?php if ($off): ?><div style="font-weight:600;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden" title="<?= h($off['name']) ?>"><?= h($off['name']) ?></div>
                <?php else: ?><span class="rc-muted">-</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <span class="jb-pill" style="background:<?= $sm[1] ?>1a;color:<?= $sm[1] ?>"><span class="jb-dot" style="background:<?= $sm[1] ?>"></span><?= h($sm[0]) ?></span>
                <div class="jb-sub"><?= $j['status']==='open'?'Đang đăng':'Chưa đăng' ?></div></td>
            <td style="white-space:nowrap">Số lượng: <b><?= (int)$j['headcount'] ?></b>
                <div class="jb-sub" style="<?= $overdue?'color:#dc2626;font-weight:600':'' ?>"><?= $j['deadline'] ? 'Hạn: '.date('d/m/Y', strtotime($j['deadline'])) . ($overdue?' (quá hạn)':'') : 'Không thời hạn' ?></div></td>
            <td><?= h($j['external_id'] ?: ('#'.$j['id'])) ?></td>
            <td><?= h($j['code'] && $j['code']!==$j['external_id'] ? $j['code'] : '-') ?></td>
            <td class="jb-stat"><span class="pos"><b><?= (int)$j['apps'] ?></b> ứng viên</span>
                <div class="jb-sub">Đã tuyển: <?= (int)$j['hired'] ?>/<?= (int)$j['headcount'] ?></div>
                <?php $hc=(int)$j['headcount']; $pct=$hc>0?min(100,round((int)$j['hired']*100/$hc)):0; ?>
                <div class="jb-progress"><i style="width:<?= $pct ?>%"></i></div></td>
            <td class="jb-stat"><span class="pos"><b><?= (int)$j['interviewed'] ?></b> phỏng vấn</span></td>
            <td style="max-width:160px"><?= h($j['managers'] ?: '-') ?></td>
            <td><?= h($j['poster'] ?: ($j['creator'] ?: '-')) ?></td>
            <td style="max-width:180px"><?php
                $ow = $deptOwners[(int)$j['department_id']] ?? [];
                $bc = $ow['bc'] ?? ''; $ta = $ow['ta'] ?? '';
                if ($bc || $ta): ?>
                    <?php if ($ta): ?><div style="display:flex;align-items:center;gap:6px"><span class="jb-tag role">TA</span> <?= h($ta) ?></div><?php endif; ?>
                    <?php if ($bc): ?><div style="display:flex;align-items:center;gap:6px;margin-top:5px"><span class="jb-tag role">BC</span> <?= h($bc) ?></div><?php endif; ?>
                <?php else: ?><span class="rc-muted">-</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($j['source_created'] ?: $j['created_at'])) ?></td>
            <td style="white-space:nowrap"><?= $j['source_start'] ? date('d/m/Y', strtotime($j['source_start'])) : '-' ?></td>
            <td style="white-space:nowrap"><?= $j['deadline'] ? date('d/m/Y', strtotime($j['deadline'])) : '-' ?></td>
            <td style="max-width:160px"><?= h($j['note'] ?: '-') ?></td>
            <td style="white-space:nowrap"><?= (int)$j['request_id'] > 0 ? '<a href="/hrm/requests?id=' . (int)$j['request_id'] . '" class="jb-link2">Xem HRF →</a>' : '<span class="rc-muted">Không có đề xuất liên kết</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
<?php
$pages = (int)ceil($total / $per);
if ($pages > 1):
    $qs = $_GET; ?>
<div class="jb-pager">
    <div class="info">Hiển thị <?= $offset + 1 ?>–<?= min($offset + $per, $total) ?> / <?= $total ?> tin</div>
    <div class="nav">
        <?php if ($page > 1): $qs['page'] = $page - 1; ?><a href="?<?= h(http_build_query($qs)) ?>">← Trước</a><?php endif; ?>
        <span class="cur">Trang <?= $page ?>/<?= $pages ?></span>
        <?php if ($page < $pages): $qs['page'] = $page + 1; ?><a href="?<?= h(http_build_query($qs)) ?>">Sau →</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

</div><!-- .jb-wrap -->

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
