<?php
/**
 * Kế hoạch tuyển dụng - bảng tổng quan (ma trận định biên theo chu kỳ năm).
 * Mỗi dòng = 1 phòng ban. Cột nhóm:
 *   ĐẦU NĂM        : Định biên đã chốt · Nhân sự · Có thể đề xuất
 *   ĐỀ XUẤT ĐÃ DUYỆT: Tất cả · Tuyển mới · Tuyển thay thế   (lấy từ HRF đã duyệt trong năm)
 *   THÁNG 1..12    : Định biên · Thực tế · Cần tuyển · Có thể đề xuất
 * Ô nhập (định biên đã chốt, nhân sự, định biên/thực tế từng tháng) lưu qua /hrm/api.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_plan_tables($conn);

$uid     = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* ── Chu kỳ ───────────────────────────────────────────────────────────── */
$cycles = $conn->query("SELECT id, name, year FROM hrm_plan_cycles ORDER BY year DESC")->fetch_all(MYSQLI_ASSOC);
$cid    = (int)($_GET['cycle'] ?? 0);
$cycle  = null;
foreach ($cycles as $c) { if ((int)$c['id'] === $cid) { $cycle = $c; } }
if (!$cycle && $cycles) { $cycle = $cycles[0]; $cid = (int)$cycle['id']; }

$months = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6',
           'Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];

hrm_header('Kế hoạch tuyển dụng', 'Hoạch định định biên & nhu cầu tuyển dụng theo năm', 'plan');

if (!$cycle) {
    $yr = (int)date('Y');
    ?>
    <div class="rc-empty">
        <div style="font-size:15px;color:#334155;margin-bottom:14px">Chưa có chu kỳ tuyển dụng nào.</div>
        <button class="rc-btn" onclick="addCycle(<?= $yr ?>)">+ Tạo chu kỳ Năm <?= $yr ?></button>
    </div>
    <script>
    function addCycle(year){
        var name = prompt('Tên chu kỳ:', 'Năm ' + year);
        if (name === null) return;
        var fd = new FormData(); fd.append('action','add_plan_cycle'); fd.append('year', year); fd.append('name', name);
        fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            if(j.ok){ location.href='/hrm/plan?cycle='+j.id; } else alert(j.error||'Lỗi');
        });
    }
    </script>
    <?php
    hrm_footer();
    exit;
}

/* ── Dữ liệu ──────────────────────────────────────────────────────────── */
$departments = $conn->query('SELECT id, name FROM departments ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);

// Định biên đã lưu cho chu kỳ này: dept_id => line
$lines = [];
$removedSet = [];   // dept_id => true (đã xóa khỏi bảng của chu kỳ)
$lr = $conn->query('SELECT * FROM hrm_plan_lines WHERE cycle_id = ' . $cid);
while ($r = $lr->fetch_assoc()) {
    $lines[(int)$r['department_id']] = $r;
    if (!empty($r['removed'])) { $removedSet[(int)$r['department_id']] = true; }
}

// Phòng ban hiển thị (bỏ những phòng ban đã xóa) + danh sách đã xóa để thêm lại.
$removedDepts = [];
$departments = array_values(array_filter($departments, function ($d) use ($removedSet, &$removedDepts) {
    if (isset($removedSet[(int)$d['id']])) { $removedDepts[] = $d; return false; }
    return true;
}));

// HRF đã duyệt trong năm của chu kỳ, gộp theo phòng ban.
$appr = [];   // dept_id => [all, new, rep]
$yr   = (int)$cycle['year'];
$st = $conn->prepare("SELECT department_id,
        COALESCE(SUM(quantity),0) all_q,
        COALESCE(SUM(CASE WHEN request_type='new_hc' THEN quantity ELSE 0 END),0) new_q,
        COALESCE(SUM(CASE WHEN request_type='replacement' THEN quantity ELSE 0 END),0) rep_q
    FROM hrm_requests
    WHERE status='approved' AND YEAR(COALESCE(need_by_date, created_at)) = ?
    GROUP BY department_id");
$st->bind_param('i', $yr);
$st->execute();
$ar = $st->get_result();
while ($r = $ar->fetch_assoc()) {
    $appr[(int)$r['department_id']] = [(int)$r['all_q'], (int)$r['new_q'], (int)$r['rep_q']];
}

// Chuẩn hóa dữ liệu 1 phòng ban thành các con số dùng để render.
$mk = function (int $deptId) use ($lines, $appr) {
    $ln   = $lines[$deptId] ?? null;
    $chot = $ln ? (int)$ln['dinh_bien_chot'] : 0;
    $ns   = $ln ? (int)$ln['nhan_su'] : 0;
    $plan   = $ln ? json_decode((string)$ln['months_plan'], true)   : null;
    $actual = $ln ? json_decode((string)$ln['months_actual'], true) : null;
    if (!is_array($plan))   { $plan = []; }
    if (!is_array($actual)) { $actual = []; }
    $p = $a = [];
    for ($i = 0; $i < 12; $i++) { $p[$i] = max(0, (int)($plan[$i] ?? 0)); $a[$i] = max(0, (int)($actual[$i] ?? 0)); }
    [$all, $new, $rep] = $appr[$deptId] ?? [0, 0, 0];
    return ['chot' => $chot, 'ns' => $ns, 'plan' => $p, 'actual' => $a, 'all' => $all, 'new' => $new, 'rep' => $rep];
};

// Tổng cộng (server-side cho lần tải đầu; JS giữ đồng bộ khi sửa).
$T = ['chot'=>0,'ns'=>0,'canp'=>0,'all'=>0,'new'=>0,'rep'=>0,
      'plan'=>array_fill(0,12,0),'actual'=>array_fill(0,12,0),'need'=>array_fill(0,12,0),'prop'=>array_fill(0,12,0)];
$rows = [];
foreach ($departments as $d) {
    $deptId = (int)$d['id'];
    $v = $mk($deptId);
    $v['canp'] = max(0, $v['chot'] - $v['ns']);
    $v['need'] = $v['prop'] = [];
    for ($i = 0; $i < 12; $i++) {
        $need = max(0, $v['plan'][$i] - $v['actual'][$i]);
        $v['need'][$i] = $need;
        $v['prop'][$i] = max(0, $need - $v['all']);   // còn có thể đề xuất = cần tuyển - đã duyệt
    }
    $rows[$deptId] = ['name' => $d['name'], 'v' => $v];
    $T['chot'] += $v['chot']; $T['ns'] += $v['ns']; $T['canp'] += $v['canp'];
    $T['all'] += $v['all']; $T['new'] += $v['new']; $T['rep'] += $v['rep'];
    for ($i = 0; $i < 12; $i++) {
        $T['plan'][$i] += $v['plan'][$i]; $T['actual'][$i] += $v['actual'][$i];
        $T['need'][$i] += $v['need'][$i]; $T['prop'][$i] += $v['prop'][$i];
    }
}
?>
<style>
/* ── Plan Page ── */
.plan-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px;flex-wrap:wrap}
.plan-cycle-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.plan-cycle-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;white-space:nowrap}
.plan-pill{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#475569;text-decoration:none;padding:7px 14px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;transition:.15s}
.plan-pill:hover{border-color:#94a3b8;color:#0f172a}
.plan-pill.active{background:#0f172a;color:#fff;border-color:#0f172a;box-shadow:0 2px 8px rgba(15,23,42,.25)}
.plan-pill.active .plan-pill-x{color:rgba(255,255,255,.6)}
.plan-pill-x{font-size:15px;line-height:1;color:#94a3b8;cursor:pointer;margin-left:2px}
.plan-pill-x:hover{color:#dc2626}
.plan-add-btn{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#6366f1;background:#f5f3ff;border:1.5px dashed #c4b5fd;border-radius:9px;padding:7px 14px;cursor:pointer;transition:.15s}
.plan-add-btn:hover{background:#ede9fe;border-color:#a78bfa}

/* Summary bar */
.plan-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.plan-scard{background:#fff;border:1px solid #e8ecf0;border-radius:12px;padding:14px 16px;position:relative;overflow:hidden}
.plan-scard::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:12px 12px 0 0}
.plan-scard.blue::before{background:linear-gradient(90deg,#3b82f6,#6366f1)}
.plan-scard.green::before{background:linear-gradient(90deg,#10b981,#059669)}
.plan-scard.orange::before{background:linear-gradient(90deg,#f59e0b,#d97706)}
.plan-scard.red::before{background:linear-gradient(90deg,#ef4444,#dc2626)}
.plan-scard-val{font-size:26px;font-weight:800;color:#0f172a;line-height:1;margin-bottom:3px;font-variant-numeric:tabular-nums}
.plan-scard.blue .plan-scard-val{color:#2563eb}
.plan-scard.green .plan-scard-val{color:#059669}
.plan-scard.orange .plan-scard-val{color:#d97706}
.plan-scard.red .plan-scard-val{color:#dc2626}
.plan-scard-lbl{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px}

/* Removed depts */
.plan-readd{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;padding:10px 14px;background:#fafbfc;border:1px solid #e8ecf0;border-radius:10px}
.plan-readd-lbl{font-size:11.5px;color:#94a3b8;font-weight:600;white-space:nowrap}
.plan-readd .chip{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1.5px dashed #cbd5e1;border-radius:7px;padding:4px 10px;cursor:pointer;color:#334155;font-size:12px;font-weight:600;transition:.15s}
.plan-readd .chip:hover{border-color:#6366f1;color:#6366f1;background:#f5f3ff}

/* Table container */
.plan-scroll{overflow:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff;max-height:calc(100vh - 310px);box-shadow:0 1px 4px rgba(0,0,0,.06)}

/* Table core */
table.plan{border-collapse:separate;border-spacing:0;font-size:12px;white-space:nowrap;width:100%}
table.plan th,table.plan td{border-right:1px solid #eef1f5;border-bottom:1px solid #eef1f5;padding:5px 7px;text-align:center}
table.plan tbody td{min-width:44px}

/* Thead row 1 — group headers */
table.plan thead tr:first-child th{position:sticky;top:0;z-index:6;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;padding:5px 8px;line-height:1.2;white-space:nowrap}
table.plan thead tr:first-child th.grp-init{background:#f1f5f9;color:#475569}
table.plan thead tr:first-child th.grp-appr{background:#ecfdf5;color:#065f46}
table.plan thead tr:first-child th.grp-month{background:#eff6ff;color:#1e40af}
table.plan thead tr:first-child th.grp-month:nth-child(odd){background:#e0e7ff;color:#3730a3}
/* Thead row 2 — sub-column headers */
table.plan thead tr:nth-child(2) th{position:sticky;top:22px;z-index:6;background:#f8fafc;color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;min-width:44px;line-height:1.25;white-space:normal;padding:4px 6px}

/* Dept (frozen left) column */
table.plan .col-dept{position:sticky;left:0;z-index:2;box-sizing:border-box;background:#fff;text-align:left;width:185px;min-width:185px;max-width:185px;white-space:normal;font-weight:600;color:#0f172a;line-height:1.35;vertical-align:middle;font-size:12.5px}
table.plan .col-dept .col-dept-inner{display:flex;align-items:center;gap:6px;justify-content:space-between}
table.plan .dept-acts{flex:none;display:flex;gap:3px}
table.plan .dept-del,table.plan .dept-edit{width:22px;height:22px;line-height:1;border:none;border-radius:6px;background:#f1f5f9;color:#94a3b8;font-size:12px;cursor:pointer;opacity:0;transition:.15s;padding:0;display:inline-flex;align-items:center;justify-content:center}
table.plan tbody tr:hover .dept-del,table.plan tbody tr:hover .dept-edit{opacity:1}
table.plan .dept-del:hover{background:#fee2e2;color:#dc2626}
table.plan .dept-edit:hover{background:#dbeafe;color:#2563eb}

/* Frozen columns (init group) */
table.plan .col-chot,table.plan .col-ns,table.plan .col-canp,table.plan .col-dau{position:sticky;z-index:2;box-sizing:border-box;white-space:normal}
table.plan thead .col-dept,table.plan thead .col-chot,table.plan thead .col-ns,table.plan thead .col-canp,table.plan thead .col-dau{z-index:7}
table.plan .col-chot{left:185px;width:80px;min-width:80px;max-width:80px}
table.plan .col-ns{left:265px;width:54px;min-width:54px;max-width:54px}
table.plan .col-canp{left:319px;width:54px;min-width:54px;max-width:54px}
table.plan thead .col-dau{left:185px}
table.plan .col-canp,table.plan .col-dau{box-shadow:3px 0 0 #e2e8f0}

/* Approved group coloring */
table.plan td.grp-appr,table.plan th.grp-appr{border-left:2px solid #d1fae5;background-color:rgba(236,253,245,.5)}
table.plan thead th.grp-appr-sub{background:#ecfdf5;color:#065f46;border-left:2px solid #d1fae5}

/* Month group separators */
table.plan .grp-mo{border-left:2px solid #e0e7ff}
table.plan td.grp-mo,table.plan th.grp-mo{border-left:2px solid #dde4ff}

/* Zebra rows */
table.plan tbody tr.odd td,table.plan tbody tr.odd .col-dept{background:#fff}
table.plan tbody tr.even td,table.plan tbody tr.even .col-dept{background:#f9fafb}
table.plan tbody tr.odd:hover td,table.plan tbody tr.odd:hover .col-dept,
table.plan tbody tr.even:hover td,table.plan tbody tr.even:hover .col-dept{background:#eff6ff}
table.plan tbody tr.sel td,table.plan tbody tr.sel .col-dept,
table.plan tbody tr.sel:hover td,table.plan tbody tr.sel:hover .col-dept{background:#fff7ed}

/* Total row */
table.plan tbody tr.total td{position:sticky;top:22px;z-index:4;background:#fef9c3;font-weight:700;color:#0f172a;border-bottom:2px solid #fde68a;border-top:none;font-size:12.5px}
table.plan tbody tr.total .col-dept,table.plan tbody tr.total .col-chot,table.plan tbody tr.total .col-ns,table.plan tbody tr.total .col-canp{z-index:5;background:#fef9c3}

/* Cell value styles */
table.plan .calc{color:#2563eb;font-weight:600}
table.plan .mut{color:#cbd5e1}
table.plan .pos{color:#059669;font-weight:700}
table.plan .neg{color:#dc2626;font-weight:700}

/* Inline inputs */
table.plan input{width:36px;border:1px solid transparent;border-radius:5px;padding:2px 2px;text-align:center;font-size:11.5px;font-family:inherit;background:transparent;color:#0f172a;outline:none;-moz-appearance:textfield}
table.plan input::-webkit-outer-spin-button,table.plan input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
table.plan input:hover{border-color:#e2e8f0;background:#fff}
table.plan input:focus{border-color:#6366f1;background:#fff;box-shadow:0 0 0 3px rgba(99,102,241,.12)}

/* Footnote */
.plan-note{margin-top:10px;padding:10px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e8ecf0;font-size:12px;color:#64748b;display:flex;align-items:center;gap:8px}
.plan-note svg{width:14px;height:14px;flex-shrink:0;opacity:.5}

/* Modal */
.pm-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998;display:none;align-items:flex-start;justify-content:center;padding:40px 16px;overflow:auto;backdrop-filter:blur(2px)}
.pm-box{background:#fff;width:540px;max-width:100%;border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.25);overflow:hidden}
.pm-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:15px;color:#0f172a}
.pm-head-icon{width:32px;height:32px;background:#eff6ff;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;margin-right:8px;flex-shrink:0}
.pm-head-icon svg{width:16px;height:16px;color:#2563eb}
.pm-x{border:none;background:#f1f5f9;width:30px;height:30px;border-radius:8px;font-size:16px;cursor:pointer;color:#64748b;display:flex;align-items:center;justify-content:center;transition:.15s}
.pm-x:hover{background:#fee2e2;color:#dc2626}
.pm-body{padding:20px;max-height:72vh;overflow-y:auto}
.pm-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.pm-sec{display:flex;align-items:center;gap:8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6366f1;margin:18px 0 12px;}
.pm-sec::after{content:'';flex:1;height:1px;background:#e8ecf0}
.pm-months{display:grid;grid-template-columns:repeat(4,1fr);gap:8px 10px}
.pm-months .m label{display:block;font-size:10.5px;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase;letter-spacing:.3px}
.pm-months .m input{width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:.15s;text-align:center}
.pm-months .m input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.pm-foot{display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #f1f5f9;background:#fafbfc}
.pm-box .rc-field input[readonly]{background:#f8fafc;color:#64748b;cursor:not-allowed}
</style>

<div class="plan-header">
    <div>
        <div class="plan-cycle-bar">
            <span class="plan-cycle-lbl">Chu kỳ</span>
            <?php foreach ($cycles as $c): $act = (int)$c['id'] === $cid; ?>
                <a class="plan-pill <?= $act ? 'active' : '' ?>" href="/hrm/plan?cycle=<?= (int)$c['id'] ?>">
                    <?= h($c['name']) ?>
                    <?php if ($act && $isAdmin): ?><span class="plan-pill-x" title="Xóa chu kỳ" onclick="event.preventDefault();delCycle(<?= (int)$c['id'] ?>)">×</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <button class="plan-add-btn" onclick="addCycle()">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Thêm chu kỳ
            </button>
        </div>
    </div>
    <a href="/hrm/requests" class="rc-btn ghost" style="white-space:nowrap">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:5px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        Tạo yêu cầu tuyển dụng
    </a>
</div>

<!-- Summary KPI cards -->
<div class="plan-summary">
    <div class="plan-scard blue">
        <div class="plan-scard-val"><?= $T['chot'] ?></div>
        <div class="plan-scard-lbl">Tổng định biên đã chốt</div>
    </div>
    <div class="plan-scard green">
        <div class="plan-scard-val"><?= $T['ns'] ?></div>
        <div class="plan-scard-lbl">Nhân sự hiện tại</div>
    </div>
    <div class="plan-scard orange">
        <div class="plan-scard-val"><?= array_sum($T['need']) ?></div>
        <div class="plan-scard-lbl">Tổng nhu cầu tuyển (năm)</div>
    </div>
    <div class="plan-scard red">
        <div class="plan-scard-val"><?= $T['all'] ?></div>
        <div class="plan-scard-lbl">HRF đã duyệt (<?= $yr ?>)</div>
    </div>
</div>

<?php if ($removedDepts): ?>
<div class="plan-readd">
    <span class="plan-readd-lbl">Phòng ban đã ẩn:</span>
    <?php foreach ($removedDepts as $rd): ?>
        <span class="chip" onclick="restoreDept(<?= (int)$rd['id'] ?>)">
            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= h($rd['name']) ?>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
// Hiển thị giá trị định biên (read-only); số 0 làm mờ cho đỡ rối.
function pcell($val) {
    $n = (int)$val;
    return $n ? (string)$n : '<span class="mut">0</span>';
}
?>
<div class="plan-scroll">
<table class="plan">
    <thead>
        <tr>
            <th rowspan="2" class="col-dept" style="background:#f8fafc;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b">Phòng ban / Bộ phận</th>
            <th colspan="3" class="grp-init col-dau" style="background:#f1f5f9;color:#374151;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;border-left:2px solid #e2e8f0">📋 Đầu năm</th>
            <th colspan="3" class="grp-appr" style="background:#ecfdf5;color:#065f46;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;border-left:2px solid #a7f3d0">✅ HRF đã duyệt</th>
            <?php foreach ($months as $i => $mo): ?>
                <th colspan="4" class="grp-mo" style="background:<?= $i%2===0?'#eff6ff':'#e0e7ff' ?>;color:<?= $i%2===0?'#1e40af':'#3730a3' ?>;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px"><?= h($mo) ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <th class="col-chot" style="border-left:2px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:10px">Định biên</th>
            <th class="col-ns" style="background:#f8fafc;color:#64748b;font-size:10px">Nhân sự</th>
            <th class="col-canp" style="background:#f8fafc;color:#64748b;font-size:10px">Cần tuyển</th>
            <th class="grp-appr-sub grp-appr" style="font-size:10px">Tổng</th>
            <th style="background:#ecfdf5;color:#065f46;font-size:10px">Mới</th>
            <th style="background:#ecfdf5;color:#065f46;font-size:10px;border-right:2px solid #a7f3d0">Thay thế</th>
            <?php for ($i=0;$i<12;$i++): ?>
                <th class="grp-mo" style="background:#f8fafc;color:#64748b;font-size:10px">Định biên</th>
                <th style="background:#f8fafc;color:#64748b;font-size:10px">Thực tế</th>
                <th style="background:#f8fafc;color:#2563eb;font-size:10px;font-weight:800">Cần tuyển</th>
                <th style="background:#f8fafc;color:#9333ea;font-size:10px">Đề xuất</th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>
        <!-- Tổng số -->
        <tr class="total">
            <td class="col-dept">Tổng số</td>
            <td class="col-chot" data-t="chot"><?= $T['chot'] ?></td><td class="col-ns" data-t="ns"><?= $T['ns'] ?></td><td class="col-canp" data-t="canp"><?= $T['canp'] ?></td>
            <td class="grp-mo" data-t="all"><?= $T['all'] ?></td><td data-t="new"><?= $T['new'] ?></td><td data-t="rep"><?= $T['rep'] ?></td>
            <?php for ($i=0;$i<12;$i++): ?>
                <td class="grp-mo" data-t="plan" data-m="<?= $i ?>"><?= $T['plan'][$i] ?></td>
                <td data-t="actual" data-m="<?= $i ?>"><?= $T['actual'][$i] ?></td>
                <td data-t="need" data-m="<?= $i ?>"><?= $T['need'][$i] ?></td>
                <td data-t="prop" data-m="<?= $i ?>"><?= $T['prop'][$i] ?></td>
            <?php endfor; ?>
        </tr>
        <?php $ri = 0; foreach ($rows as $deptId => $row): $v = $row['v']; $ri++; ?>
        <tr data-dept="<?= $deptId ?>" data-appr="<?= $v['all'] ?>" class="<?= $ri % 2 ? 'odd' : 'even' ?>">
            <td class="col-dept"><div class="col-dept-inner"><span class="dept-name"><?= h($row['name']) ?></span><span class="dept-acts"><button type="button" class="dept-edit" title="Sửa định biên" onclick="openPlanModal(<?= $deptId ?>)">✎</button><button type="button" class="dept-del" title="Xóa phòng ban khỏi bảng" onclick="removeDept(<?= $deptId ?>, this)">×</button></span></div></td>
            <td class="col-chot"><?= pcell($v['chot']) ?></td>
            <td class="col-ns"><?= pcell($v['ns']) ?></td>
            <td class="calc col-canp" data-c="canp"><?= $v['canp'] ?></td>
            <td class="grp-mo <?= $v['all'] ? '' : 'mut' ?>"><?= $v['all'] ?></td>
            <td class="<?= $v['new'] ? '' : 'mut' ?>"><?= $v['new'] ?></td>
            <td class="<?= $v['rep'] ? '' : 'mut' ?>"><?= $v['rep'] ?></td>
            <?php for ($i=0;$i<12;$i++): ?>
                <td class="grp-mo"><?= pcell($v['plan'][$i]) ?></td>
                <td><?= pcell($v['actual'][$i]) ?></td>
                <td class="calc" data-c="need" data-m="<?= $i ?>"><?= $v['need'][$i] ?></td>
                <td class="calc" data-c="prop" data-m="<?= $i ?>"><?= $v['prop'][$i] ?></td>
            <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="plan-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>"HRF đã duyệt" lấy từ Yêu cầu tuyển dụng có trạng thái <strong>Đã duyệt</strong> trong năm <?= (int)$cycle['year'] ?>. Di chuột vào tên phòng ban và bấm ✎ để nhập định biên. "Cần tuyển" và "Đề xuất" được tính tự động.</span>
</div>

<!-- Modal nhập định biên theo phòng ban -->
<div id="planModal" class="pm-overlay">
    <div class="pm-box">
        <div class="pm-head">
            <div style="display:flex;align-items:center;gap:0">
                <span class="pm-head-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                <span id="pmTitle">Nhập định biên</span>
            </div>
            <button class="pm-x" onclick="closePlanModal()">×</button>
        </div>
        <div class="pm-body">
            <div class="pm-grid2">
                <div class="rc-field"><label>Chu kỳ</label><input id="pmCycle" readonly></div>
                <div class="rc-field"><label>Phòng ban</label><input id="pmDept" readonly></div>
            </div>
            <div class="pm-grid2">
                <div class="rc-field"><label>Định biên đã chốt</label><input type="number" min="0" id="pmChot" placeholder="0"></div>
                <div class="rc-field"><label>Nhân sự đầu năm</label><input type="number" min="0" id="pmNs" placeholder="0"></div>
            </div>
            <div class="pm-sec">Định biên theo tháng</div>
            <div class="pm-months" id="pmPlan"></div>
            <div class="pm-sec">Thực tế theo tháng</div>
            <div class="pm-months" id="pmActual"></div>
        </div>
        <div class="pm-foot">
            <button class="rc-btn ghost" onclick="closePlanModal()">Hủy</button>
            <button class="rc-btn" onclick="savePlanModal()">💾 Lưu định biên</button>
        </div>
    </div>
</div>

<script>
var CYCLE = <?= $cid ?>;
var CYCLE_NAME = <?= json_encode($cycle['name'], JSON_UNESCAPED_UNICODE) ?>;
var PLAN = <?php
    $planJs = [];
    foreach ($rows as $deptId => $row) {
        $v = $row['v'];
        $planJs[$deptId] = ['name' => $row['name'], 'chot' => $v['chot'], 'ns' => $v['ns'],
            'plan' => array_values($v['plan']), 'actual' => array_values($v['actual'])];
    }
    echo json_encode($planJs, JSON_UNESCAPED_UNICODE);
?>;

function addCycle(){
    var y = prompt('Năm của chu kỳ (vd 2026):', '<?= (int)date('Y') ?>');
    if (y === null) return;
    y = parseInt(y, 10); if (!y) { alert('Năm không hợp lệ'); return; }
    var name = prompt('Tên chu kỳ:', 'Năm ' + y);
    if (name === null) return;
    var fd = new FormData(); fd.append('action','add_plan_cycle'); fd.append('year', y); fd.append('name', name);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok){ location.href='/hrm/plan?cycle='+j.id; } else alert(j.error||'Lỗi');
    });
}
function delCycle(id){
    if(!confirm('Xóa chu kỳ này và toàn bộ định biên đã nhập?')) return;
    var fd = new FormData(); fd.append('action','del_plan_cycle'); fd.append('cycle_id', id);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok){ location.href='/hrm/plan'; } else alert(j.error||'Lỗi');
    });
}
function removeDept(deptId, btn){
    var name = btn.closest('tr').querySelector('.dept-name').textContent;
    if(!confirm('Xóa phòng ban "'+name+'" khỏi bảng kế hoạch của chu kỳ này?')) return;
    var fd = new FormData(); fd.append('action','remove_plan_dept'); fd.append('cycle_id', CYCLE); fd.append('department_id', deptId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok){ location.reload(); } else showToast(j.error||'Lỗi', 'error');
    }).catch(function(){ showToast('Lỗi mạng', 'error'); });
}
function restoreDept(deptId){
    var fd = new FormData(); fd.append('action','restore_plan_dept'); fd.append('cycle_id', CYCLE); fd.append('department_id', deptId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok){ location.reload(); } else showToast(j.error||'Lỗi', 'error');
    }).catch(function(){ showToast('Lỗi mạng', 'error'); });
}

// ── Nhập định biên qua form (modal), không inline ─────────────────────────
function gv(id){ var n = parseInt(document.getElementById(id).value, 10); return isNaN(n) || n < 0 ? 0 : n; }

function buildMonths(container, prefix){
    var html = '';
    for (var m=0;m<12;m++){ html += '<div class="m"><label>Tháng '+(m+1)+'</label><input type="number" min="0" id="'+prefix+m+'"></div>'; }
    container.innerHTML = html;
}

function openPlanModal(deptId){
    var d = PLAN[deptId]; if(!d) return;
    document.getElementById('pmTitle').textContent = 'Định biên · ' + d.name;
    document.getElementById('pmCycle').value = CYCLE_NAME;
    document.getElementById('pmDept').value  = d.name;
    document.getElementById('pmChot').value  = d.chot;
    document.getElementById('pmNs').value    = d.ns;
    buildMonths(document.getElementById('pmPlan'), 'pmP');
    buildMonths(document.getElementById('pmActual'), 'pmA');
    for (var m=0;m<12;m++){ document.getElementById('pmP'+m).value = d.plan[m]; document.getElementById('pmA'+m).value = d.actual[m]; }
    var box = document.getElementById('planModal');
    box.dataset.dept = deptId;
    box.style.display = 'flex';
}
function closePlanModal(){ document.getElementById('planModal').style.display = 'none'; }

function savePlanModal(){
    var dept = document.getElementById('planModal').dataset.dept;
    var plan = [], actual = [];
    for (var m=0;m<12;m++){ plan.push(gv('pmP'+m)); actual.push(gv('pmA'+m)); }
    var fd = new FormData();
    fd.append('action','save_plan_line');
    fd.append('cycle_id', CYCLE);
    fd.append('department_id', dept);
    fd.append('dinh_bien_chot', gv('pmChot'));
    fd.append('nhan_su', gv('pmNs'));
    fd.append('months_plan', JSON.stringify(plan));
    fd.append('months_actual', JSON.stringify(actual));
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(function(j){
        if(j.ok){ localStorage.setItem('job_toast', JSON.stringify({msg:'Đã lưu định biên', type:'success'})); location.reload(); }
        else showToast(j.error||'Lỗi lưu', 'error');
    }).catch(function(){ showToast('Lỗi mạng', 'error'); });
}

// Đóng modal khi bấm ra nền tối.
document.getElementById('planModal').addEventListener('click', function(e){ if (e.target === this) closePlanModal(); });

// Click vào dòng phòng ban -> bật/tắt nền chọn (bỏ qua khi bấm nút).
document.querySelector('table.plan').addEventListener('click', function(e){
    if (e.target.closest('button')) return;
    var tr = e.target.closest('tr[data-dept]'); if(!tr) return;
    tr.classList.toggle('sel');
});

// Đóng băng ngang 4 cột đầu = thuần CSS (left cố định). JS chỉ canh dòng sub-header
// dính ngay dưới dòng nhóm (top = chiều cao dòng nhóm), chạy lại khi layout/font đổi.
(function(){
    function syncHead(){
        var t = document.querySelector('table.plan'); if(!t || !t.tHead) return;
        var r1 = t.tHead.rows[0], r2 = t.tHead.rows[1]; if(!r2) return;
        var h = Math.round(r1.getBoundingClientRect().height);
        for (var i=0;i<r2.cells.length;i++){ r2.cells[i].style.top = h + 'px'; }
        // Ghim hàng "Tổng số" ngay dưới toàn bộ phần đầu bảng (top = chiều cao thead).
        var head = Math.round(t.tHead.getBoundingClientRect().height);
        var total = t.querySelector('tbody tr.total');
        if (total) { for (var j=0;j<total.cells.length;j++){ total.cells[j].style.top = head + 'px'; } }
    }
    function run(){ requestAnimationFrame(syncHead); }
    run();
    window.addEventListener('resize', run);
    window.addEventListener('load', run);
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(run); }
})();
</script>
<?php
hrm_footer();
