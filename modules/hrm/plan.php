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
.plan-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.plan-bar .lbl{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--mut);margin-right:4px}
.plan-pill{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#475569;text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--bd);background:#fff}
.plan-pill.active{background:var(--rc);color:#fff;border-color:var(--rc)}
.plan-pill .x{opacity:.7;font-weight:700}
.plan-add{font-size:13px;font-weight:600;color:var(--rc2);background:#fff;border:1px dashed #cbd5e1;border-radius:8px;padding:7px 12px;cursor:pointer}
.plan-scroll{overflow:auto;border:1px solid var(--bd);border-radius:12px;background:#fff;max-height:calc(100vh - 220px)}
table.plan{border-collapse:separate;border-spacing:0;font-size:12px;white-space:nowrap}
table.plan th,table.plan td{border-right:1px solid #eef1f5;border-bottom:1px solid #eef1f5;padding:4px 6px;text-align:center}
table.plan tbody td{min-width:42px}
table.plan thead th{position:sticky;top:0;z-index:6;background:#f8fafc;color:var(--mut);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.2px;line-height:1.25;white-space:normal}
table.plan thead tr:nth-child(2) th{top:19px;min-width:42px}
table.plan .grp{background:#f1f5f9;color:#334155;border-bottom:1px solid #e2e8f0}
table.plan .col-dept{position:sticky;left:0;z-index:2;box-sizing:border-box;background:#fff;text-align:left;width:180px;min-width:180px;max-width:180px;white-space:normal;font-weight:600;color:#0f172a;line-height:1.3;vertical-align:middle}
/* Bố cục tên + nút thao tác để trong wrapper, KHÔNG dùng display:flex trên chính <td>
   (flex làm ô mất tư cách table-cell -> position:sticky theo chiều dọc bị hỏng). */
table.plan .col-dept .col-dept-inner{display:flex;align-items:center;gap:6px;justify-content:space-between}
table.plan .dept-acts{flex:none;display:flex;gap:4px}
table.plan .dept-del,table.plan .dept-edit{width:20px;height:20px;line-height:1;border:none;border-radius:50%;background:#f1f5f9;color:#94a3b8;font-size:13px;cursor:pointer;opacity:0;transition:.15s;padding:0}
table.plan tbody tr:hover .dept-del,table.plan tbody tr:hover .dept-edit{opacity:1}
table.plan .dept-del:hover{background:#fee2e2;color:#dc2626}
table.plan .dept-edit:hover{background:#dbeafe;color:#2563eb}
/* Modal "Thêm/Sửa định biên" */
.pm-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;display:none;align-items:flex-start;justify-content:center;padding:40px 16px;overflow:auto}
.pm-box{background:#fff;width:520px;max-width:100%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden}
.pm-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--bd);font-weight:700;font-size:15px;color:#0f172a;background:#f8fafc}
.pm-x{border:none;background:none;font-size:20px;cursor:pointer;color:#94a3b8;line-height:1}
.pm-body{padding:18px;max-height:70vh;overflow:auto}
.pm-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.pm-sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#16a34a;margin:16px 0 10px;border-top:1px solid #f1f5f9;padding-top:14px}
.pm-months{display:grid;grid-template-columns:repeat(3,1fr);gap:10px 12px}
.pm-months .m label{display:block;font-size:11px;font-weight:600;color:#475569;margin-bottom:4px}
.pm-months .m input{width:100%;padding:7px 10px;border:1px solid var(--bd);border-radius:8px;font-size:13px;font-family:inherit;outline:none}
.pm-months .m input:focus{border-color:var(--rc2)}
.pm-foot{display:flex;justify-content:flex-end;gap:10px;padding:14px 18px;border-top:1px solid var(--bd);background:#fafbfc}
.pm-box .rc-field input[readonly]{background:#f1f5f9;color:#64748b}
.plan-readd{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;font-size:12.5px;color:var(--mut)}
.plan-readd .chip{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px dashed #cbd5e1;border-radius:8px;padding:5px 10px;cursor:pointer;color:#334155;font-weight:600}
.plan-readd .chip:hover{border-color:var(--rc2);color:var(--rc2)}
table.plan thead .col-dept{z-index:7;background:#f8fafc}
/* Khối 4 cột đầu đóng băng: width cố định (border-box) + left cộng dồn cố định -> sticky chuẩn, không phụ thuộc JS.
   Cộng dồn: dept 180 | chốt 84 | nhân sự 56 | đề xuất 56  => left 0 / 180 / 264 / 320 */
table.plan .col-chot,table.plan .col-ns,table.plan .col-canp,table.plan .col-dau{position:sticky;z-index:2;box-sizing:border-box;white-space:normal}
table.plan thead .col-chot,table.plan thead .col-ns,table.plan thead .col-canp,table.plan thead .col-dau{z-index:7}
table.plan .col-chot{left:180px;width:84px;min-width:84px;max-width:84px}
table.plan .col-ns{left:264px;width:56px;min-width:56px;max-width:56px}
table.plan .col-canp{left:320px;width:56px;min-width:56px;max-width:56px}
table.plan thead .col-dau{left:180px}
/* đường phân cách mép phải của khối đóng băng */
table.plan .col-canp,table.plan .col-dau{box-shadow:2px 0 0 #e2e8f0}
/* Zebra (chẵn/lẻ) - áp cho cả ô phòng ban dính trái */
table.plan tbody tr.odd td,table.plan tbody tr.odd .col-dept{background:#ffffff}
table.plan tbody tr.even td,table.plan tbody tr.even .col-dept{background:#f5f7fa}
/* Hover */
table.plan tbody tr.odd:hover td,table.plan tbody tr.odd:hover .col-dept,
table.plan tbody tr.even:hover td,table.plan tbody tr.even:hover .col-dept{background:#eef4ff}
/* Đang chọn (click để bật/tắt) - thắng cả zebra & hover nên đặt sau cùng */
table.plan tbody tr.sel td,table.plan tbody tr.sel .col-dept,
table.plan tbody tr.sel:hover td,table.plan tbody tr.sel:hover .col-dept{background:#fde9c8}
/* Hàng "Tổng số" ghim ngay dưới phần đầu bảng (top được JS đặt theo chiều cao thead) */
table.plan tbody tr.total td{position:sticky;top:38px;z-index:4;background:#fffaf0;font-weight:700;color:#0f172a;border-bottom:2px solid #e2e8f0}
table.plan tbody tr.total .col-dept,table.plan tbody tr.total .col-chot,table.plan tbody tr.total .col-ns,table.plan tbody tr.total .col-canp{z-index:5}
table.plan tbody tr.total .col-dept{background:#fffaf0}
table.plan input{width:34px;border:1px solid transparent;border-radius:5px;padding:2px 1px;text-align:center;font-size:11px;font-family:inherit;background:transparent;color:#0f172a;outline:none;-moz-appearance:textfield}
table.plan input::-webkit-outer-spin-button,table.plan input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
table.plan input:hover{border-color:#e2e8f0;background:#fff}
table.plan input:focus{border-color:var(--rc2);background:#fff;box-shadow:0 0 0 3px rgba(14,107,92,.1)}
table.plan .calc{color:#2563eb;font-weight:600}
table.plan .mut{color:#94a3b8}
table.plan .grp-mo{border-left:2px solid #e2e8f0}
table.plan td.grp-mo,table.plan th.grp-mo{border-left:2px solid #e2e8f0}
</style>

<div class="plan-bar">
    <span class="lbl">Chu kỳ</span>
    <?php foreach ($cycles as $c): $act = (int)$c['id'] === $cid; ?>
        <a class="plan-pill <?= $act ? 'active' : '' ?>" href="/hrm/plan?cycle=<?= (int)$c['id'] ?>">
            <?= h($c['name']) ?>
            <?php if ($act && $isAdmin): ?><span class="x" title="Xóa chu kỳ" onclick="event.preventDefault();delCycle(<?= (int)$c['id'] ?>)">×</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <button class="plan-add" onclick="addCycle()">+ Thêm chu kỳ</button>
</div>

<?php if ($removedDepts): ?>
<div class="plan-readd">
    <span>Phòng ban đã xóa:</span>
    <?php foreach ($removedDepts as $rd): ?>
        <span class="chip" onclick="restoreDept(<?= (int)$rd['id'] ?>)">+ <?= h($rd['name']) ?></span>
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
            <th rowspan="2" class="col-dept">Vị trí công việc</th>
            <th colspan="3" class="grp grp-mo col-dau">Đầu năm</th>
            <th colspan="3" class="grp grp-mo">Đề xuất đã duyệt</th>
            <?php foreach ($months as $mo): ?><th colspan="4" class="grp grp-mo"><?= h($mo) ?></th><?php endforeach; ?>
        </tr>
        <tr>
            <th class="grp-mo col-chot">Định biên đã chốt</th><th class="col-ns">Nhân sự</th><th class="col-canp">Đề xuất</th>
            <th class="grp-mo">Tất cả</th><th>Tuyển mới</th><th>Tuyển TT</th>
            <?php for ($i=0;$i<12;$i++): ?>
                <th class="grp-mo">Định biên</th><th>Thực tế</th><th>Cần tuyển</th><th>Đề xuất</th>
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

<div class="rc-muted" style="margin-top:10px">
    "Đề xuất đã duyệt" lấy từ Yêu cầu tuyển dụng (HRF) đã duyệt trong năm <?= (int)$cycle['year'] ?>.
    Bấm ✎ trên mỗi phòng ban để nhập định biên; "Đề xuất / Cần tuyển" tự tính.
</div>

<!-- Modal nhập định biên theo phòng ban -->
<div id="planModal" class="pm-overlay">
    <div class="pm-box">
        <div class="pm-head"><span id="pmTitle">Định biên</span><button class="pm-x" onclick="closePlanModal()">×</button></div>
        <div class="pm-body">
            <div class="pm-grid2">
                <div class="rc-field"><label>Chu kỳ</label><input id="pmCycle" readonly></div>
                <div class="rc-field"><label>Phòng ban</label><input id="pmDept" readonly></div>
            </div>
            <div class="pm-grid2">
                <div class="rc-field"><label>Định biên đã chốt</label><input type="number" min="0" id="pmChot"></div>
                <div class="rc-field"><label>Nhân sự (đầu năm)</label><input type="number" min="0" id="pmNs"></div>
            </div>
            <div class="pm-sec">Định biên theo tháng</div>
            <div class="pm-months" id="pmPlan"></div>
            <div class="pm-sec">Thực tế theo tháng</div>
            <div class="pm-months" id="pmActual"></div>
        </div>
        <div class="pm-foot">
            <button class="rc-btn ghost" onclick="closePlanModal()">Hủy</button>
            <button class="rc-btn" onclick="savePlanModal()">Lưu</button>
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
