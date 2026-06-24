<?php
/**
 * Gộp 2 hồ sơ ứng viên trùng. Route: /hrm/candidates/merge?a=ID&b=ID
 * Chọn hồ sơ giữ lại + giá trị từng field, rồi gộp dữ liệu con.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$aId = (int)($_GET['a'] ?? 0); $bId = (int)($_GET['b'] ?? 0);
$a = $conn->query("SELECT * FROM hrm_candidates WHERE id=$aId")->fetch_assoc();
$b = $conn->query("SELECT * FROM hrm_candidates WHERE id=$bId")->fetch_assoc();
if (!$a || !$b || $aId === $bId) { hrm_header('Gộp hồ sơ', '', 'candidates'); echo '<div class="rc-empty">Cần 2 hồ sơ khác nhau (?a=&b=).</div>'; hrm_footer(); exit; }

$flds = [
    'full_name'=>'Họ tên','email'=>'Email','phone'=>'Điện thoại','current_position'=>'Vị trí gần nhất',
    'location'=>'Khu vực','expected_salary'=>'Lương kỳ vọng','languages'=>'Ngôn ngữ','years_exp'=>'Số năm KN',
    'dob'=>'Ngày sinh','gender'=>'Giới tính','linkedin_url'=>'LinkedIn','portfolio_url'=>'Portfolio','notes'=>'Ghi chú',
];
// đếm dữ liệu con
$cnt = function ($t, $id) use ($conn) { return (int)($conn->query("SELECT COUNT(*) c FROM $t WHERE candidate_id=$id")->fetch_assoc()['c'] ?? 0); };
$childInfo = function ($id) use ($conn, $cnt) {
    $apps = (int)($conn->query("SELECT COUNT(*) c FROM hrm_applications WHERE candidate_id=$id")->fetch_assoc()['c'] ?? 0);
    return "$apps ứng tuyển · " . $cnt('hrm_candidate_attachments',$id) . " tệp · " . $cnt('hrm_candidate_activities',$id) . " hoạt động";
};

hrm_header('Gộp hồ sơ trùng', $a['full_name'] . ' ↔ ' . $b['full_name'], 'candidates');
?>
<div class="rc-toolbar"><a href="/hrm/candidates" class="rc-tab">← Kho ứng viên</a><div></div></div>

<div class="rc-card">
    <div class="rc-muted" style="margin-bottom:12px">Chọn hồ sơ <b>giữ lại</b> (mọi ứng tuyển/tệp/hoạt động của hồ sơ kia sẽ chuyển sang, hồ sơ kia chuyển sang trạng thái Lưu trữ). Với mỗi trường, chọn giá trị muốn giữ.</div>

    <div class="mg-head">
        <label class="mg-pick"><input type="radio" name="keep" value="<?= $aId ?>" checked onchange="kept=<?= $aId ?>"> Giữ <b>#<?= $aId ?> · <?= h($a['full_name']) ?></b><div class="rc-muted"><?= h($childInfo($aId)) ?></div></label>
        <label class="mg-pick"><input type="radio" name="keep" value="<?= $bId ?>" onchange="kept=<?= $bId ?>"> Giữ <b>#<?= $bId ?> · <?= h($b['full_name']) ?></b><div class="rc-muted"><?= h($childInfo($bId)) ?></div></label>
    </div>

    <table class="mg-table">
        <thead><tr><th>Trường</th><th>Hồ sơ A (#<?= $aId ?>)</th><th>Hồ sơ B (#<?= $bId ?>)</th></tr></thead>
        <tbody>
        <?php foreach ($flds as $f => $lbl): $va = (string)($a[$f] ?? ''); $vb = (string)($b[$f] ?? ''); ?>
            <tr>
                <td class="rc-muted"><?= h($lbl) ?></td>
                <td><label><input type="radio" name="f_<?= $f ?>" value="a"<?= ($va!==''||$vb==='')?' checked':'' ?>> <?= h($va ?: '—') ?></label></td>
                <td><label><input type="radio" name="f_<?= $f ?>" value="b"<?= ($va===''&&$vb!=='')?' checked':'' ?>> <?= h($vb ?: '—') ?></label></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div id="mgErr" class="rc-muted" style="color:#dc2626;margin-top:10px"></div>
    <div style="display:flex;gap:8px;margin-top:14px">
        <a class="rc-btn ghost" href="/hrm/candidates">Hủy</a>
        <button class="rc-btn" id="mgBtn" onclick="doMerge()">Gộp hồ sơ</button>
    </div>
</div>

<style>
.mg-head{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.mg-pick{border:1px solid var(--bd);border-radius:10px;padding:12px 14px;cursor:pointer;font-size:14px}
.mg-table{width:100%;border-collapse:collapse;font-size:13px}
.mg-table th{text-align:left;color:#86868b;font-size:11px;font-weight:600;padding:8px 12px;border-bottom:1px solid #f0f0f2}
.mg-table td{padding:9px 12px;border-bottom:1px solid #f5f5f7;vertical-align:top}
.mg-table label{display:block;cursor:pointer}
</style>
<script>
const A=<?= $aId ?>, B=<?= $bId ?>, FLDS=<?= json_encode(array_keys($flds)) ?>;
const VAL={a:<?= json_encode(array_map(fn($f)=>(string)($a[$f]??''),array_keys($flds)),JSON_UNESCAPED_UNICODE) ?>,
           b:<?= json_encode(array_map(fn($f)=>(string)($b[$f]??''),array_keys($flds)),JSON_UNESCAPED_UNICODE) ?>};
let kept=A;
function doMerge(){
    const merged = kept===A ? B : A;
    const fd=new FormData();fd.append('action','cand_merge');fd.append('kept_id',kept);fd.append('merged_id',merged);
    FLDS.forEach((f,i)=>{const pick=document.querySelector('input[name=f_'+f+']:checked').value;fd.append(f, VAL[pick][i]);});
    document.getElementById('mgBtn').disabled=true;document.getElementById('mgErr').textContent='Đang gộp...';
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.ok){location.href='/hrm/candidate?id='+j.id;return;}
        document.getElementById('mgBtn').disabled=false;document.getElementById('mgErr').textContent=j.error||'Lỗi';
    }).catch(()=>{document.getElementById('mgBtn').disabled=false;document.getElementById('mgErr').textContent='Lỗi kết nối';});
}
</script>
<?php
hrm_footer();
