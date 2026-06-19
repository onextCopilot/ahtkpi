<?php
/**
 * B1 - Yêu cầu tuyển dụng (HRF) + phê duyệt.
 * Modes: list (default) · ?new=1 (create form) · ?id=N (detail + approval).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/approval.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid     = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$myRoles = hrm_roles_of($conn, $uid);
$roleLabels = hrm_roles();
$departments = $conn->query('SELECT id,name FROM departments ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);
$offices     = $conn->query('SELECT id,name FROM hrm_offices WHERE active=1 ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);

$id  = (int)($_GET['id'] ?? 0);
$new  = isset($_GET['new']);
$edit = isset($_GET['edit']) && $id;

/* ─────────────────────────── CREATE / EDIT FORM ─────────────────────── */
if ($new || $edit) {
    $r = ['title'=>'','request_type'=>'replacement','level'=>'','department_id'=>0,'office_id'=>0,
          'quantity'=>1,'need_by_date'=>'','salary_min'=>0,'salary_max'=>0,'reason'=>'','jd'=>''];
    if ($edit) {
        $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $id)->fetch_assoc();
        if (!$req) { hrm_header('Không tìm thấy', '', 'requests'); echo '<div class="rc-empty">HRF không tồn tại.</div>'; hrm_footer(); exit; }
        $editable = ((int)$req['created_by'] === $uid || $isAdmin) && (in_array($req['status'], ['draft','rejected'], true) || $isAdmin);
        if (!$editable) { hrm_header('Không thể sửa', '', 'requests'); echo '<div class="rc-empty">HRF đang chờ duyệt/đã duyệt nên không thể sửa.</div>'; hrm_footer(); exit; }
        $r = array_merge($r, $req);
    }
    $sel = function ($a, $b) { return (string)$a === (string)$b ? ' selected' : ''; };
    hrm_header($edit ? ('Sửa HRF ' . $req['code']) : 'Tạo yêu cầu tuyển dụng', 'HRF - Hiring Request Form', 'requests');
    ?>
    <div class="rc-toolbar"><a href="<?= $edit ? '/hrm/requests?id='.$id : '/hrm/requests' ?>" class="rc-tab">← Quay lại</a></div>
    <div class="rc-card" style="max-width:760px">
        <form id="hrfForm" onsubmit="return false">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="rc-field"><label>Tên vị trí *</label><input name="title" required placeholder="VD: Senior PHP Developer" value="<?= h($r['title']) ?>"></div>
            <div class="rc-grid2">
                <div class="rc-field"><label>Loại yêu cầu</label>
                    <select name="request_type"><option value="replacement"<?= $sel($r['request_type'],'replacement') ?>>Thay thế (Replacement)</option><option value="new_hc"<?= $sel($r['request_type'],'new_hc') ?>>Tuyển mới (New Headcount)</option></select></div>
                <div class="rc-field"><label>Level</label><input name="level" placeholder="Junior / Middle / Senior / Lead" value="<?= h($r['level']) ?>"></div>
                <div class="rc-field"><label>Bộ phận</label><select name="department_id"><option value="0">-</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"<?= $sel($r['department_id'],$d['id']) ?>><?= h($d['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Văn phòng</label><select name="office_id"><option value="0">-</option>
                    <?php foreach ($offices as $o): ?><option value="<?= $o['id'] ?>"<?= $sel($r['office_id'],$o['id']) ?>><?= h($o['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Số lượng</label><input type="number" name="quantity" value="<?= (int)$r['quantity'] ?>" min="1"></div>
                <div class="rc-field"><label>Ngày cần onboard</label><input type="date" name="need_by_date" value="<?= h($r['need_by_date']) ?>"></div>
                <div class="rc-field"><label>Lương tối thiểu (VND)</label><input type="number" name="salary_min" value="<?= (int)$r['salary_min'] ?>"></div>
                <div class="rc-field"><label>Lương tối đa (VND)</label><input type="number" name="salary_max" value="<?= (int)$r['salary_max'] ?>"></div>
            </div>
            <div class="rc-field"><label>Lý do tuyển</label>
                <select name="reason">
                    <?php foreach (['Replacement', 'Growth', 'New Project'] as $opt): ?>
                        <option value="<?= $opt ?>"<?= $sel($r['reason'], $opt) ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rc-field"><label>Mô tả công việc (JD)</label>
                <input type="hidden" name="jd" id="jdInput">
                <div id="jdEditor" style="height:280px;background:#fff"></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px">
                <button class="rc-btn" onclick="saveHrf(true)"><?= $edit ? 'Lưu & gửi duyệt' : 'Gửi duyệt' ?></button>
                <button class="rc-btn ghost" onclick="saveHrf(false)"><?= $edit ? 'Lưu' : 'Lưu nháp' ?></button>
            </div>
        </form>
    </div>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
    var jdQuill = new Quill('#jdEditor', {theme:'snow', placeholder:'Mô tả công việc, trách nhiệm, yêu cầu... (tự chuyển sang tin tuyển dụng khi tạo tin)',
        modules:{toolbar:[[{header:[2,3,false]}],['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link'],['clean']]}});
    jdQuill.root.innerHTML = <?= json_encode($r['jd'] ?: '', JSON_UNESCAPED_UNICODE) ?>;
    var IS_EDIT = <?= $edit ? 'true' : 'false' ?>;
    function saveHrf(submit){
        const f=document.getElementById('hrfForm');
        if(!f.title.value.trim()){alert('Nhập tên vị trí');return;}
        document.getElementById('jdInput').value = jdQuill.getText().trim() ? jdQuill.root.innerHTML : '';
        const fd=new FormData(f); fd.append('action', IS_EDIT?'update_request':'save_request'); if(submit)fd.append('submit','1');
        fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            if(j.ok){location.href='/hrm/requests?id='+(j.id||<?= $id ?>);}else alert(j.error||'Lỗi');
        });
    }
    </script>
    <?php
    hrm_footer();
    exit;
}

/* ─────────────────────────── DETAIL ──────────────────────────────── */
if ($id) {
    $req = $conn->query('SELECT * FROM hrm_requests WHERE id = ' . $id)->fetch_assoc();
    if (!$req) { hrm_header('Không tìm thấy', '', 'requests'); echo '<div class="rc-empty">HRF không tồn tại.</div>'; hrm_footer(); exit; }

    $steps = hrm_approval_steps($conn, 'hrf', $id);
    $current = hrm_approval_current($conn, 'hrf', $id);
    $canAct = $current && hrm_user_has_role($conn, $uid, $current['approver_role']);
    $isOwner = (int)$req['created_by'] === $uid || $isAdmin;
    $creator = hrm_user($conn, (int)$req['created_by']);

    // A finished HRF (approved/rejected) can be reopened by whoever holds the last acted step's role.
    $lastActed = null;
    foreach ($steps as $s) { if ($s['acted_at']) { $lastActed = $s; } }
    $canReopen = in_array($req['status'], ['approved', 'rejected'], true) && $lastActed
                 && hrm_user_has_role($conn, $uid, $lastActed['approver_role']);

    hrm_header('HRF ' . $req['code'], $req['title'], 'requests');
    ?>
    <div class="rc-toolbar">
        <a href="/hrm/requests" class="rc-tab">← Danh sách</a>
        <div style="display:flex;gap:8px;align-items:center"><?= hrm_badge($req['status']) ?>
            <?php if ($isOwner && (in_array($req['status'], ['draft','rejected'], true) || $isAdmin)): ?><a class="rc-btn ghost" href="/hrm/requests?id=<?= $id ?>&edit=1">Sửa</a><?php endif; ?>
            <?php if ($isOwner && $req['status'] === 'draft'): ?><button class="rc-btn" onclick="act('submit_request',{id:<?= $id ?>})">Gửi duyệt</button><?php endif; ?>
            <?php if ($isOwner && in_array($req['status'], ['draft','pending'], true)): ?><button class="rc-btn ghost" onclick="act('cancel_request',{id:<?= $id ?>})">Hủy</button><?php endif; ?>
            <?php if ($req['status'] === 'approved'): ?><a class="rc-btn" href="/hrm/jobs">→ Tạo tin tuyển dụng</a><?php endif; ?>
            <?php if ($canReopen): ?><button class="rc-btn ghost" onclick="act('reopen_request',{id:<?= $id ?>})">Mở lại để duyệt lại</button><?php endif; ?>
        </div>
    </div>

    <div class="rc-card">
        <div class="rc-grid2">
            <div><div class="rc-muted">Vị trí</div><div><b><?= h($req['title']) ?></b></div></div>
            <div><div class="rc-muted">Loại</div><div><?= $req['request_type']==='new_hc'?'Tuyển mới':'Thay thế' ?></div></div>
            <div><div class="rc-muted">Level</div><div><?= h($req['level'] ?: '-') ?></div></div>
            <div><div class="rc-muted">Số lượng</div><div><?= (int)$req['quantity'] ?></div></div>
            <div><div class="rc-muted">Khoảng lương</div><div><?= number_format($req['salary_min']) ?> - <?= number_format($req['salary_max']) ?> <?= h($req['currency']) ?></div></div>
            <div><div class="rc-muted">Cần onboard</div><div><?= $req['need_by_date'] ? date('d/m/Y', strtotime($req['need_by_date'])) : '-' ?></div></div>
        </div>
        <div style="margin-top:12px"><div class="rc-muted">Lý do</div><div><?= nl2br(h($req['reason'] ?: '-')) ?></div></div>
        <?php if (!empty($req['jd'])): ?><div style="margin-top:12px"><div class="rc-muted">Mô tả công việc (JD)</div><div class="rc-rich"><?= $req['jd'] ?></div></div><?php endif; ?>
        <div class="rc-muted" style="margin-top:12px">Tạo bởi <?= h($creator['full_name']) ?> · <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></div>
    </div>

    <div class="rc-card">
        <h3 style="font-size:14px;margin-bottom:8px">Luồng phê duyệt</h3>
        <?php if (!$steps): ?><div class="rc-muted">Chưa gửi duyệt.</div><?php else: foreach ($steps as $s):
            $cls = $s['status']==='approved'?'ok':($s['status']==='rejected'?'no':(($current && $current['id']==$s['id'])?'cur':''));
            $sym = $s['status']==='approved'?'✓':($s['status']==='rejected'?'✕':$s['step_order']); ?>
            <div class="rc-step">
                <div class="rc-step-dot <?= $cls ?>"><?= $sym ?></div>
                <div style="flex:1">
                    <div><b><?= h(hrm_role_label($s['approver_role'])) ?></b>
                        <?php if ($s['status']==='pending' && $current && $current['id']==$s['id']): ?><span class="rc-badge rc-b-pending">Đang chờ</span><?php endif; ?>
                    </div>
                    <div class="rc-muted">
                        <?php
                        if ($s['acted_at']) {
                            $au = hrm_user($conn, (int)$s['acted_by']);
                            echo ($s['status']==='approved'?'Duyệt':'Từ chối').' bởi '.h($au['full_name']).' · '.date('d/m H:i', strtotime($s['acted_at']));
                        } elseif ($s['due_at']) {
                            echo 'Hạn xử lý: '.date('d/m/Y H:i', strtotime($s['due_at']));
                        } else {
                            echo 'Chưa tới lượt';
                        }
                        ?>
                    </div>
                    <?php if ($s['acted_at'] && $s['note']): ?><div class="rc-rich" style="margin-top:4px;background:#f8fafc;border:1px solid var(--bd);border-radius:7px;padding:8px 10px"><?= $s['note'] ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>

        <?php if ($canAct): ?>
        <div style="margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px">
            <div class="rc-field"><label>Ghi chú (bắt buộc khi từ chối)</label>
                <div id="noteEditor" style="height:170px;background:#fff"></div>
            </div>
            <div style="display:flex;gap:10px">
                <button class="rc-btn" onclick="decide('approve')">Phê duyệt</button>
                <button class="rc-btn danger" onclick="decide('reject')">Từ chối</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($canAct): ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <?php endif; ?>
    <script>
    function post(action,data){const fd=new FormData();fd.append('action',action);for(const k in data)fd.append(k,data[k]);
        return fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json());}
    function act(action,data){post(action,data).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
    <?php if ($canAct): ?>
    var noteQuill = new Quill('#noteEditor', {theme:'snow', placeholder:'Nhận xét / lý do...',
        modules:{toolbar:[['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link'],['clean']]}});
    function decide(kind){
        const txt = noteQuill.getText().trim();
        const note = txt ? noteQuill.root.innerHTML : '';
        if(kind==='reject' && !txt){alert('Nhập lý do từ chối');return;}
        post(kind,{approval_id:<?= $current['id'] ?? 0 ?>,note:note}).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
    }
    <?php endif; ?>
    </script>
    <?php
    hrm_footer();
    exit;
}

/* ─────────────────────────── LIST ────────────────────────────────── */
$tab = $_GET['tab'] ?? 'all';

if ($tab === 'mine') {
    $st = $conn->prepare('SELECT * FROM hrm_requests WHERE created_by = ? ORDER BY id DESC');
    $st->bind_param('i', $uid); $st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($tab === 'pending') {
    // HRFs whose current pending step is a role I hold.
    $rows = [];
    $all = $conn->query("SELECT * FROM hrm_requests WHERE status='pending' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
    foreach ($all as $r) {
        $cur = hrm_approval_current($conn, 'hrf', (int)$r['id']);
        if ($cur && ($isAdmin || in_array($cur['approver_role'], $myRoles, true))) { $rows[] = $r; }
    }
} else {
    $rows = $conn->query('SELECT * FROM hrm_requests ORDER BY id DESC LIMIT 200')->fetch_all(MYSQLI_ASSOC);
}

hrm_header('Yêu cầu tuyển dụng', 'HRF - Hiring Request Form', 'requests');
?>
<div class="rc-toolbar">
    <div class="rc-tabs">
        <a href="/hrm/requests?tab=all" class="rc-tab <?= $tab==='all'?'active':'' ?>">Tất cả</a>
        <a href="/hrm/requests?tab=mine" class="rc-tab <?= $tab==='mine'?'active':'' ?>">Của tôi</a>
        <a href="/hrm/requests?tab=pending" class="rc-tab <?= $tab==='pending'?'active':'' ?>">Chờ tôi duyệt</a>
    </div>
    <a href="/hrm/requests?new=1" class="rc-btn">+ Tạo HRF</a>
</div>

<?php if (!$rows): ?>
    <div class="rc-empty">Chưa có yêu cầu tuyển dụng nào.</div>
<?php else: ?>
<table class="rc-table">
    <thead><tr><th>Mã</th><th>Vị trí</th><th>Loại</th><th>SL</th><th>Cần onboard</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr onclick="location.href='/hrm/requests?id=<?= $r['id'] ?>'" style="cursor:pointer">
            <td><b><?= h($r['code']) ?></b></td>
            <td><?= h($r['title']) ?></td>
            <td><?= $r['request_type']==='new_hc'?'Tuyển mới':'Thay thế' ?></td>
            <td><?= (int)$r['quantity'] ?></td>
            <td><?= $r['need_by_date'] ? date('d/m/Y', strtotime($r['need_by_date'])) : '-' ?></td>
            <td><?= hrm_badge($r['status']) ?></td>
            <td class="rc-muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
hrm_footer();
