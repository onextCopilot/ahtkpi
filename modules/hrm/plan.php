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
$lr = $conn->query('SELECT * FROM hrm_plan_lines WHERE cycle_id = ' . $cid);
while ($r = $lr->fetch_assoc()) { $lines[(int)$r['department_id']] = $r; }

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
table.plan th,table.plan td{border-right:1px solid #eef1f5;border-bottom:1px solid #eef1f5;padding:6px 8px;text-align:center}
table.plan thead th{position:sticky;top:0;z-index:3;background:#f8fafc;color:var(--mut);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
table.plan thead tr:nth-child(2) th{top:31px}
table.plan .grp{background:#f1f5f9;color:#334155;border-bottom:1px solid #e2e8f0}
table.plan .col-dept{position:sticky;left:0;z-index:2;background:#fff;text-align:left;min-width:210px;max-width:210px;white-space:normal;font-weight:600;color:#0f172a;box-shadow:1px 0 0 #e2e8f0}
table.plan thead .col-dept{z-index:4;background:#f8fafc}
table.plan tbody tr:hover td{background:#fafcff}
table.plan tbody tr:hover .col-dept{background:#fafcff}
table.plan .total td{background:#fffaf0;font-weight:700;color:#0f172a;border-bottom:2px solid #e2e8f0}
table.plan .total .col-dept{background:#fffaf0}
table.plan input{width:46px;border:1px solid transparent;border-radius:6px;padding:4px 2px;text-align:center;font-size:12px;font-family:inherit;background:transparent;color:#0f172a;outline:none}
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

<?php
// Helper render ô nhập / ô tính.
function pin($f, $val, $m = null) {  // editable input
    $ma = $m === null ? '' : ' data-m="' . (int)$m . '"';
    return '<input type="number" min="0" data-f="' . $f . '"' . $ma . ' value="' . (int)$val . '">';
}
?>
<div class="plan-scroll">
<table class="plan">
    <thead>
        <tr>
            <th rowspan="2" class="col-dept">Vị trí công việc</th>
            <th colspan="3" class="grp">Đầu năm</th>
            <th colspan="3" class="grp grp-mo">Đề xuất đã duyệt</th>
            <?php foreach ($months as $mo): ?><th colspan="4" class="grp grp-mo"><?= h($mo) ?></th><?php endforeach; ?>
        </tr>
        <tr>
            <th>Định biên đã chốt</th><th>Nhân sự</th><th>Có thể đề xuất</th>
            <th class="grp-mo">Tất cả</th><th>Tuyển mới</th><th>Tuyển thay thế</th>
            <?php for ($i=0;$i<12;$i++): ?>
                <th class="grp-mo">Định biên</th><th>Thực tế</th><th>Cần tuyển</th><th>Có thể đề xuất</th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>
        <!-- Tổng số -->
        <tr class="total">
            <td class="col-dept">Tổng số</td>
            <td data-t="chot"><?= $T['chot'] ?></td><td data-t="ns"><?= $T['ns'] ?></td><td data-t="canp"><?= $T['canp'] ?></td>
            <td class="grp-mo" data-t="all"><?= $T['all'] ?></td><td data-t="new"><?= $T['new'] ?></td><td data-t="rep"><?= $T['rep'] ?></td>
            <?php for ($i=0;$i<12;$i++): ?>
                <td class="grp-mo" data-t="plan" data-m="<?= $i ?>"><?= $T['plan'][$i] ?></td>
                <td data-t="actual" data-m="<?= $i ?>"><?= $T['actual'][$i] ?></td>
                <td data-t="need" data-m="<?= $i ?>"><?= $T['need'][$i] ?></td>
                <td data-t="prop" data-m="<?= $i ?>"><?= $T['prop'][$i] ?></td>
            <?php endfor; ?>
        </tr>
        <?php foreach ($rows as $deptId => $row): $v = $row['v']; ?>
        <tr data-dept="<?= $deptId ?>" data-appr="<?= $v['all'] ?>">
            <td class="col-dept"><?= h($row['name']) ?></td>
            <td><?= pin('chot', $v['chot']) ?></td>
            <td><?= pin('ns', $v['ns']) ?></td>
            <td class="calc" data-c="canp"><?= $v['canp'] ?></td>
            <td class="grp-mo <?= $v['all'] ? '' : 'mut' ?>"><?= $v['all'] ?></td>
            <td class="<?= $v['new'] ? '' : 'mut' ?>"><?= $v['new'] ?></td>
            <td class="<?= $v['rep'] ? '' : 'mut' ?>"><?= $v['rep'] ?></td>
            <?php for ($i=0;$i<12;$i++): ?>
                <td class="grp-mo"><?= pin('plan', $v['plan'][$i], $i) ?></td>
                <td><?= pin('actual', $v['actual'][$i], $i) ?></td>
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
    Ô nền trắng có thể nhập trực tiếp; "Có thể đề xuất / Cần tuyển" tự tính.
</div>

<script>
var CYCLE = <?= $cid ?>;

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

// Tính lại 1 dòng phòng ban + cập nhật dòng Tổng số.
function num(el){ var n = parseInt(el.value, 10); return isNaN(n) || n < 0 ? 0 : n; }

function recalcRow(tr){
    var appr = parseInt(tr.dataset.appr, 10) || 0;
    var chot = num(tr.querySelector('input[data-f="chot"]'));
    var ns   = num(tr.querySelector('input[data-f="ns"]'));
    tr.querySelector('[data-c="canp"]').textContent = Math.max(0, chot - ns);
    for (var m=0;m<12;m++){
        var plan = num(tr.querySelector('input[data-f="plan"][data-m="'+m+'"]'));
        var act  = num(tr.querySelector('input[data-f="actual"][data-m="'+m+'"]'));
        var need = Math.max(0, plan - act);
        tr.querySelector('[data-c="need"][data-m="'+m+'"]').textContent = need;
        tr.querySelector('[data-c="prop"][data-m="'+m+'"]').textContent = Math.max(0, need - appr);
    }
}

function recalcTotals(){
    var rows = document.querySelectorAll('table.plan tbody tr[data-dept]');
    var t = {chot:0,ns:0,canp:0,all:0,new:0,rep:0,
             plan:Array(12).fill(0),actual:Array(12).fill(0),need:Array(12).fill(0),prop:Array(12).fill(0)};
    rows.forEach(function(tr){
        t.chot += num(tr.querySelector('input[data-f="chot"]'));
        t.ns   += num(tr.querySelector('input[data-f="ns"]'));
        t.canp += parseInt(tr.querySelector('[data-c="canp"]').textContent,10)||0;
        var tds = tr.children;
        t.all += parseInt(tds[4].textContent,10)||0;
        t.new += parseInt(tds[5].textContent,10)||0;
        t.rep += parseInt(tds[6].textContent,10)||0;
        for (var m=0;m<12;m++){
            t.plan[m]   += num(tr.querySelector('input[data-f="plan"][data-m="'+m+'"]'));
            t.actual[m] += num(tr.querySelector('input[data-f="actual"][data-m="'+m+'"]'));
            t.need[m]   += parseInt(tr.querySelector('[data-c="need"][data-m="'+m+'"]').textContent,10)||0;
            t.prop[m]   += parseInt(tr.querySelector('[data-c="prop"][data-m="'+m+'"]').textContent,10)||0;
        }
    });
    var tot = document.querySelector('table.plan tbody tr.total');
    ['chot','ns','canp','all','new','rep'].forEach(function(k){ tot.querySelector('[data-t="'+k+'"]').textContent = t[k]; });
    ['plan','actual','need','prop'].forEach(function(k){
        for (var m=0;m<12;m++){ tot.querySelector('[data-t="'+k+'"][data-m="'+m+'"]').textContent = t[k][m]; }
    });
}

var saveTimers = {};
function saveRow(tr){
    var dept = tr.dataset.dept;
    var plan = [], actual = [];
    for (var m=0;m<12;m++){
        plan.push(num(tr.querySelector('input[data-f="plan"][data-m="'+m+'"]')));
        actual.push(num(tr.querySelector('input[data-f="actual"][data-m="'+m+'"]')));
    }
    var fd = new FormData();
    fd.append('action','save_plan_line');
    fd.append('cycle_id', CYCLE);
    fd.append('department_id', dept);
    fd.append('dinh_bien_chot', num(tr.querySelector('input[data-f="chot"]')));
    fd.append('nhan_su', num(tr.querySelector('input[data-f="ns"]')));
    fd.append('months_plan', JSON.stringify(plan));
    fd.append('months_actual', JSON.stringify(actual));
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(function(j){
        if(j.ok){ showToast('Đã lưu định biên'); } else showToast(j.error||'Lỗi lưu', 'error');
    }).catch(function(){ showToast('Lỗi mạng', 'error'); });
}

document.querySelector('table.plan').addEventListener('input', function(e){
    var inp = e.target.closest('input[data-f]'); if(!inp) return;
    var tr = inp.closest('tr[data-dept]'); if(!tr) return;
    recalcRow(tr); recalcTotals();
    clearTimeout(saveTimers[tr.dataset.dept]);
    saveTimers[tr.dataset.dept] = setTimeout(function(){ saveRow(tr); }, 700);
});
</script>
<?php
hrm_footer();
