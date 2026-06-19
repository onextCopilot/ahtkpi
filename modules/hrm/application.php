<?php
/**
 * Application journey - screening, test (B5), interview (B6), evaluation (B7),
 * offer (B8, reusing the approval engine).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/approval.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$myRoles = hrm_roles_of($conn, $uid);
$id  = (int)($_GET['id'] ?? 0);

$app = $conn->query("SELECT a.*, c.full_name, c.email, c.phone, j.title AS job_title, j.id AS job_id, s.name AS stage_name
        FROM hrm_applications a
        JOIN hrm_candidates c ON c.id=a.candidate_id
        JOIN hrm_jobs j ON j.id=a.job_id
        LEFT JOIN hrm_pipeline_stages s ON s.id=a.stage_id
        WHERE a.id = $id")->fetch_assoc();
if (!$app) { hrm_header('Không tìm thấy', '', 'jobs'); echo '<div class="rc-empty">Hồ sơ không tồn tại.</div>'; hrm_footer(); exit; }

$stages  = $conn->query('SELECT * FROM hrm_pipeline_stages WHERE active=1 ORDER BY sort_order')->fetch_all(MYSQLI_ASSOC);
$tests   = $conn->query("SELECT * FROM hrm_tests WHERE application_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$intvs   = $conn->query("SELECT * FROM hrm_interviews WHERE application_id=$id ORDER BY round")->fetch_all(MYSQLI_ASSOC);
$evals   = $conn->query("SELECT * FROM hrm_evaluations WHERE application_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$offer   = $conn->query("SELECT * FROM hrm_offers WHERE application_id=$id ORDER BY id DESC LIMIT 1")->fetch_assoc();
$users   = $conn->query("SELECT id, full_name FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$reasons = $conn->query('SELECT name FROM hrm_rejection_reasons WHERE active=1')->fetch_all(MYSQLI_ASSOC);

hrm_header($app['full_name'], $app['job_title'] . ' · ' . ($app['stage_name'] ?? ''), 'jobs');
?>
<div class="rc-toolbar">
    <a href="/hrm/job?id=<?= (int)$app['job_id'] ?>" class="rc-tab">← Pipeline</a>
    <div style="display:flex;gap:8px;align-items:center">
        <?php if ($app['status']==='active'): ?>
            <select id="moveSel" class="rc-tab" style="padding:7px 10px">
                <?php foreach ($stages as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id']==$app['stage_id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="rc-btn ghost" onclick="move()">Chuyển bước</button>
            <button class="rc-btn" onclick="hire()">Tuyển</button>
            <button class="rc-btn danger" onclick="reject()">Từ chối</button>
        <?php else: ?>
            <span class="rc-badge rc-b-<?= $app['status']==='hired'?'approved':'rejected' ?>"><?= $app['status']==='hired'?'Đã tuyển':'Từ chối' ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="rc-card">
    <div class="rc-grid2">
        <div><div class="rc-muted">Ứng viên</div><div><b><?= h($app['full_name']) ?></b></div></div>
        <div><div class="rc-muted">Vị trí</div><div><?= h($app['job_title']) ?></div></div>
        <div><div class="rc-muted">Email</div><div><?= h($app['email'] ?: '-') ?></div></div>
        <div><div class="rc-muted">Điện thoại</div><div><?= h($app['phone'] ?: '-') ?></div></div>
    </div>
</div>

<!-- TEST -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Test đầu vào (B5)</h3>
    <?php foreach ($tests as $t): ?>
        <div class="rc-step"><div style="flex:1"><b><?= h($t['test_type'] ?: 'Test') ?></b> · <?= (float)$t['score'] ?>/<?= (float)$t['max_score'] ?>
            <span class="rc-badge rc-b-<?= $t['passed']?'approved':'rejected' ?>"><?= $t['passed']?'Đạt':'Chưa đạt' ?></span></div></div>
    <?php endforeach; ?>
    <form onsubmit="return false" style="display:flex;gap:8px;align-items:flex-end;margin-top:8px">
        <div class="rc-field" style="margin:0"><label>Loại</label><input id="t_type" placeholder="Coding / Logic / English"></div>
        <div class="rc-field" style="margin:0;width:110px"><label>Điểm (/100)</label><input id="t_score" type="number" min="0" max="100"></div>
        <button class="rc-btn ghost" onclick="saveTest()">Lưu (đạt ≥70)</button>
    </form>
</div>

<!-- INTERVIEW -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Phỏng vấn (B6)</h3>
    <?php foreach ($intvs as $iv): $ivu = hrm_user($conn,(int)$iv['interviewer_id']); ?>
        <div class="rc-step"><div style="flex:1">Vòng <?= (int)$iv['round'] ?> · <?= h($iv['interview_type']) ?>
            · <?= $iv['scheduled_at']?date('d/m/Y H:i',strtotime($iv['scheduled_at'])):'(chưa hẹn)' ?>
            <span class="rc-muted">PV: <?= h($ivu['full_name'] ?: '-') ?></span></div></div>
    <?php endforeach; ?>
    <form onsubmit="return false" class="rc-grid2" style="margin-top:8px;align-items:end">
        <div class="rc-field" style="margin:0;width:90px"><label>Vòng</label><input id="iv_round" type="number" value="<?= count($intvs)+1 ?>" min="1"></div>
        <div class="rc-field" style="margin:0"><label>Hình thức</label><select id="iv_type"><option value="technical">Technical</option><option value="hr">HR / TA</option><option value="manager">Manager/Director</option></select></div>
        <div class="rc-field" style="margin:0"><label>Thời gian</label><input id="iv_at" type="datetime-local"></div>
        <div class="rc-field" style="margin:0"><label>Người phỏng vấn</label><select id="iv_by"><option value="0">-</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="rc-field" style="margin:0"><label>Địa điểm</label><input id="iv_loc" placeholder="Online / Văn phòng"></div>
        <div><button class="rc-btn ghost" onclick="schedIv()">Lên lịch + gửi thư mời</button></div>
    </form>
</div>

<!-- EVALUATION -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Đánh giá sau phỏng vấn (B7)</h3>
    <?php foreach ($evals as $e): $eu=hrm_user($conn,(int)$e['evaluator_id']); ?>
        <div class="rc-step"><div style="flex:1"><b><?= (float)$e['total_score'] ?>/100</b> · <?= h($e['recommendation']) ?>
            <span class="rc-muted"><?= h($eu['full_name']) ?><?= $e['comment']?' · '.h($e['comment']):'' ?></span></div></div>
    <?php endforeach; ?>
    <form onsubmit="return false" style="display:flex;gap:8px;align-items:flex-end;margin-top:8px;flex-wrap:wrap">
        <div class="rc-field" style="margin:0;width:120px"><label>Điểm (/100)</label><input id="e_score" type="number" min="0" max="100"></div>
        <div class="rc-field" style="margin:0"><label>Đề xuất</label><select id="e_rec"><option value="hire">Nên tuyển</option><option value="hold">Cân nhắc</option><option value="reject">Loại</option></select></div>
        <div class="rc-field" style="margin:0;flex:1;min-width:200px"><label>Nhận xét</label><input id="e_cmt"></div>
        <button class="rc-btn ghost" onclick="saveEval()">Lưu đánh giá</button>
    </form>
</div>

<!-- OFFER -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Offer (B8)</h3>
    <?php if (!$offer): ?>
        <form onsubmit="return false" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <div class="rc-field" style="margin:0;width:160px"><label>Lương (VND)</label><input id="o_salary" type="number"></div>
            <div class="rc-field" style="margin:0"><label>Ngày bắt đầu</label><input id="o_start" type="date"></div>
            <button class="rc-btn" onclick="createOffer()">Tạo offer</button>
        </form>
    <?php else:
        $offerSteps = hrm_approval_steps($conn, 'offer', (int)$offer['id']);
        $offerCur = hrm_approval_current($conn, 'offer', (int)$offer['id']);
        $canActOffer = $offerCur && hrm_user_has_role($conn, $uid, $offerCur['approver_role']);
        $offerStatusLabel = ['draft'=>'Nháp','pending_approval'=>'Chờ duyệt','sent'=>'Đã gửi','accepted'=>'Đã nhận','declined'=>'Từ chối','expired'=>'Hết hạn'];
    ?>
        <div style="margin-bottom:10px">
            <b><?= number_format((float)$offer['salary']) ?> <?= h($offer['currency']) ?></b>
            · Bắt đầu: <?= $offer['start_date']?date('d/m/Y',strtotime($offer['start_date'])):'-' ?>
            <span class="rc-badge rc-b-<?= in_array($offer['status'],['accepted','sent'])?'approved':($offer['status']==='declined'?'rejected':'pending') ?>"><?= h($offerStatusLabel[$offer['status']]??$offer['status']) ?></span>
        </div>
        <?php foreach ($offerSteps as $s):
            $cls=$s['status']==='approved'?'ok':($s['status']==='rejected'?'no':(($offerCur&&$offerCur['id']==$s['id'])?'cur':''));
            $sym=$s['status']==='approved'?'✓':($s['status']==='rejected'?'✕':$s['step_order']); ?>
            <div class="rc-step"><div class="rc-step-dot <?= $cls ?>"><?= $sym ?></div>
                <div style="flex:1"><b><?= h(hrm_role_label($s['approver_role'])) ?></b>
                    <span class="rc-muted"><?= $s['acted_at']?($s['status']==='approved'?'Đã duyệt':'Từ chối'):($offerCur&&$offerCur['id']==$s['id']?'đang chờ':'') ?></span></div></div>
        <?php endforeach; ?>
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <?php if ($offer['status']==='draft'): ?><button class="rc-btn" onclick="act('submit_offer',{offer_id:<?= $offer['id'] ?>})">Gửi duyệt</button><?php endif; ?>
            <?php if ($canActOffer): ?>
                <button class="rc-btn" onclick="actNote('approve',<?= $offerCur['id'] ?>)">Duyệt offer</button>
                <button class="rc-btn danger" onclick="actNote('reject',<?= $offerCur['id'] ?>)">Từ chối offer</button>
            <?php endif; ?>
            <?php if ($offer['status']==='sent'): ?>
                <button class="rc-btn" onclick="act('offer_response',{offer_id:<?= $offer['id'] ?>,response:'accept'})">Ứng viên nhận</button>
                <button class="rc-btn danger" onclick="act('offer_response',{offer_id:<?= $offer['id'] ?>,response:'decline'})">Ứng viên từ chối</button>
            <?php elseif (!$offerCur && $offer['status']==='sent'): ?>
            <?php endif; ?>
            <?php if ($offer['status']==='sent'): ?><span class="rc-muted">Offer đã gửi tới ứng viên.</span><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function post(a,d){const fd=new FormData();fd.append('action',a);for(const k in d)fd.append(k,d[k]);return fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json());}
function act(a,d){post(a,d).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function actNote(kind,aid){const n=prompt(kind==='reject'?'Lý do từ chối:':'Ghi chú (tùy chọn):')||'';if(kind==='reject'&&!n){return;}post(kind,{approval_id:aid,note:n}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function move(){act('move_stage',{application_id:<?= $id ?>,stage_id:document.getElementById('moveSel').value});}
function hire(){if(confirm('Xác nhận tuyển ứng viên này?'))act('hire_application',{application_id:<?= $id ?>});}
function reject(){const r=prompt('Lý do từ chối:')||'';if(!r)return;act('reject_application',{application_id:<?= $id ?>,reason:r});}
function saveTest(){act('save_test',{application_id:<?= $id ?>,test_type:document.getElementById('t_type').value,score:document.getElementById('t_score').value});}
function schedIv(){act('schedule_interview',{application_id:<?= $id ?>,round:document.getElementById('iv_round').value,interview_type:document.getElementById('iv_type').value,scheduled_at:document.getElementById('iv_at').value,interviewer_id:document.getElementById('iv_by').value,location:document.getElementById('iv_loc').value});}
function saveEval(){act('save_evaluation',{application_id:<?= $id ?>,total_score:document.getElementById('e_score').value,recommendation:document.getElementById('e_rec').value,comment:document.getElementById('e_cmt').value});}
function createOffer(){act('create_offer',{application_id:<?= $id ?>,salary:document.getElementById('o_salary').value,start_date:document.getElementById('o_start').value});}
</script>
<?php
hrm_footer();
