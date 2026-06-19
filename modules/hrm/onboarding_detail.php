<?php
/**
 * Onboarding detail - role assignment + 60-day plan (tasks + checkpoints).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/onboarding.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$id = (int)($_GET['id'] ?? 0);
$o = $conn->query('SELECT * FROM hrm_onboarding WHERE id = ' . $id)->fetch_assoc();
if (!$o) { hrm_header('Không tìm thấy', '', 'onboarding'); echo '<div class="rc-empty">Onboarding không tồn tại.</div>'; hrm_footer(); exit; }

$users = $conn->query("SELECT id, full_name FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$tasks = $conn->query("SELECT * FROM hrm_onboarding_tasks WHERE onboarding_id=$id ORDER BY FIELD(phase,'preboarding','day1','week1'), id")->fetch_all(MYSQLI_ASSOC);
$cps   = $conn->query("SELECT * FROM hrm_checkpoints WHERE onboarding_id=$id ORDER BY due_date")->fetch_all(MYSQLI_ASSOC);
$cpDef = hrm_onb_checkpoints();
$hasPlan = !empty($tasks);

$byPhase = []; foreach ($tasks as $t) { $byPhase[$t['phase']][] = $t; }
$phaseLabel = ['preboarding' => 'Pre-boarding (trước ngày nhận việc)', 'day1' => 'Ngày đầu tiên', 'week1' => 'Tuần 1'];

$uname = function ($uid) use ($users) { foreach ($users as $u) { if ((int)$u['id'] === (int)$uid) { return $u['full_name']; } } return '-'; };

hrm_header('Onboarding: ' . $o['candidate_name'], $o['job_title'], 'onboarding');
?>
<div class="rc-toolbar"><a href="/hrm/onboarding" class="rc-tab">← Danh sách</a>
    <?php if ($hasPlan && $o['status'] !== 'completed'): ?><button class="rc-btn" onclick="act('complete_onboarding',{id:<?= $id ?>})">Hoàn thành onboarding</button><?php endif; ?>
</div>

<!-- Setup / roles -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Thiết lập & phân vai</h3>
    <form id="setupForm" onsubmit="return false">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="rc-grid2">
            <div class="rc-field"><label>Ngày bắt đầu (onboard)</label><input type="date" name="start_date" value="<?= h($o['start_date']) ?>"></div>
            <div class="rc-field"><label>Level</label><input name="level" value="<?= h($o['level']) ?>" placeholder="Junior / Middle / Senior / Lead"></div>
            <?php
            $roleSelects = ['manager_id' => 'Direct Manager', 'buddy_id' => 'Buddy / Mentor', 'ta_id' => 'TA phụ trách', 'bc_director_id' => 'BC Director'];
            foreach ($roleSelects as $field => $label): ?>
                <div class="rc-field"><label><?= $label ?></label>
                    <select name="<?= $field ?>"><option value="0">-</option>
                        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= (int)$o[$field]===(int)$u['id']?' selected':'' ?>><?= h($u['full_name']) ?></option><?php endforeach; ?>
                    </select></div>
            <?php endforeach; ?>
        </div>
        <button class="rc-btn" onclick="saveSetup()"><?= $hasPlan ? 'Lưu (tạo lại kế hoạch theo ngày bắt đầu)' : 'Lưu & tạo kế hoạch 60 ngày' ?></button>
    </form>
</div>

<?php if ($hasPlan): ?>
<!-- Tasks checklist -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Công việc chuẩn bị & ngày đầu</h3>
    <?php foreach ($byPhase as $phase => $list): ?>
        <div class="rc-muted" style="font-weight:700;margin:10px 0 6px"><?= h($phaseLabel[$phase] ?? $phase) ?></div>
        <?php foreach ($list as $t): ?>
            <label style="display:flex;align-items:flex-start;gap:10px;padding:7px 0;cursor:pointer">
                <input type="checkbox" <?= $t['done']?'checked':'' ?> onchange="toggleTask(<?= $t['id'] ?>,this.checked)" style="margin-top:3px">
                <span style="flex:1"><?= h($t['title']) ?>
                    <span class="rc-muted"> · <?= h(hrm_onb_owner_label($t['owner_role'])) ?><?= $t['due_date']?' · hạn '.date('d/m',strtotime($t['due_date'])):'' ?></span></span>
            </label>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<!-- Checkpoints -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Các mốc kiểm tra (checkpoint)</h3>
    <?php foreach ($cps as $c):
        $def = $cpDef[$c['checkpoint_key']] ?? [$c['checkpoint_key'], 0, ''];
        $overdue = $c['status'] !== 'done' && $c['due_date'] && strtotime($c['due_date']) < strtotime('today');
        $isW2 = $c['checkpoint_key'] === 'week2';
        $is30 = $c['checkpoint_key'] === 'day30';
    ?>
        <div style="border:1px solid var(--bd);border-radius:8px;padding:12px 14px;margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                <div><b><?= h($def[0]) ?></b>
                    <span class="rc-muted"> · <?= h(hrm_onb_owner_label($def[2])) ?><?= $c['due_date']?' · hạn '.date('d/m/Y',strtotime($c['due_date'])):'' ?></span>
                    <?php if ($c['status']==='done'): ?><span class="rc-badge rc-b-approved">Đã xong<?= $c['result_grade']?' · '.h($c['result_grade']):'' ?></span>
                    <?php elseif ($overdue): ?><span class="rc-badge rc-b-rejected">Quá hạn</span>
                    <?php else: ?><span class="rc-badge rc-b-pending">Chờ</span><?php endif; ?>
                </div>
                <button class="rc-btn ghost" style="padding:5px 12px" onclick="document.getElementById('cp<?= $c['id'] ?>').style.display=(document.getElementById('cp<?= $c['id'] ?>').style.display==='none'?'block':'none')">Chấm / ghi nhận</button>
            </div>
            <div id="cp<?= $c['id'] ?>" style="display:none;margin-top:10px">
                <?php if ($isW2 || $is30): ?>
                <div class="rc-grid2">
                    <div class="rc-field"><label>Thái độ /10</label><input type="number" min="0" max="10" id="att<?= $c['id'] ?>" value="<?= (int)$c['score_attitude'] ?>"></div>
                    <div class="rc-field"><label>Kỹ năng /10</label><input type="number" min="0" max="10" id="skl<?= $c['id'] ?>" value="<?= (int)$c['score_skill'] ?>"></div>
                    <div class="rc-field"><label>Hòa nhập /10</label><input type="number" min="0" max="10" id="int<?= $c['id'] ?>" value="<?= (int)$c['score_integration'] ?>"></div>
                    <?php if ($is30): ?><div class="rc-field"><label>Kết quả (A/B/C)</label><select id="grd<?= $c['id'] ?>">
                        <?php foreach (['','A','B','C'] as $g): ?><option value="<?= $g ?>"<?= $c['result_grade']===$g?' selected':'' ?>><?= $g?:'-' ?></option><?php endforeach; ?></select></div><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="rc-field"><label>Ghi chú</label><textarea id="nt<?= $c['id'] ?>" rows="2"><?= h($c['notes']) ?></textarea></div>
                <button class="rc-btn" onclick="saveCp(<?= $c['id'] ?>,<?= $isW2||$is30?'true':'false' ?>,<?= $is30?'true':'false' ?>)">Lưu & đánh dấu xong</button>
            </div>
        </div>
    <?php endforeach; ?>
    <div style="margin-top:6px"><a class="rc-btn" href="/hrm/probation?id=<?= $id ?>">Đánh giá thử việc cuối kỳ (Ngày 60) →</a></div>
</div>
<?php endif; ?>

<script>
function post(a,d){const fd=new FormData();fd.append('action',a);for(const k in d)fd.append(k,d[k]);return fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json());}
function act(a,d){post(a,d).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function saveSetup(){
    const f=document.getElementById('setupForm');const fd=new FormData(f);fd.append('action','save_onboarding');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function toggleTask(id,done){post('toggle_task',{id:id,done:done?1:0});}
function saveCp(id,scored,graded){
    const d={id:id,notes:document.getElementById('nt'+id).value};
    if(scored){d.score_attitude=document.getElementById('att'+id).value;d.score_skill=document.getElementById('skl'+id).value;d.score_integration=document.getElementById('int'+id).value;}
    if(graded){d.result_grade=document.getElementById('grd'+id).value;}
    act('save_checkpoint',d);
}
</script>
<?php
hrm_footer();
