<?php
/**
 * Job detail - JD (view/edit) + pipeline Kanban + add candidate.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);
$job = $conn->query('SELECT * FROM hrm_jobs WHERE id = ' . $id)->fetch_assoc();
if (!$job) { hrm_header('Không tìm thấy', '', 'jobs'); echo '<div class="rc-empty">Tin tuyển dụng không tồn tại.</div>'; hrm_footer(); exit; }

$departments = $conn->query('SELECT id,name FROM departments ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);
$offices     = $conn->query('SELECT id,name FROM hrm_offices WHERE active=1 ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);
$sources     = $conn->query('SELECT id,name FROM hrm_candidate_sources WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$statusLabel = ['draft' => 'Nháp', 'open' => 'Đang tuyển', 'on_hold' => 'Tạm dừng', 'closed' => 'Đã đóng'];

/* ── JD edit ──────────────────────────────────────────────────────────── */
if (isset($_GET['edit'])) {
    hrm_header('Sửa tin: ' . $job['title'], $job['code'], 'jobs');
    ?>
    <div class="rc-toolbar"><a href="/hrm/job?id=<?= $id ?>" class="rc-tab">← Quay lại tin</a></div>
    <div class="rc-card" style="max-width:820px">
        <form id="jobForm" onsubmit="return false">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="rc-field"><label>Tên vị trí *</label><input name="title" value="<?= h($job['title']) ?>" required></div>
            <div class="rc-grid2">
                <div class="rc-field"><label>Bộ phận</label><select name="department_id">
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $d['id']==$job['department_id']?'selected':'' ?>><?= h($d['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Văn phòng</label><select name="office_id"><option value="0">-</option>
                    <?php foreach ($offices as $o): ?><option value="<?= $o['id'] ?>" <?= $o['id']==$job['office_id']?'selected':'' ?>><?= h($o['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Level</label><input name="level" value="<?= h($job['level']) ?>"></div>
                <div class="rc-field"><label>Số lượng cần</label><input type="number" name="headcount" value="<?= (int)$job['headcount'] ?>" min="1"></div>
                <div class="rc-field"><label>Lương tối thiểu</label><input type="number" name="salary_min" value="<?= (int)$job['salary_min'] ?>"></div>
                <div class="rc-field"><label>Lương tối đa</label><input type="number" name="salary_max" value="<?= (int)$job['salary_max'] ?>"></div>
                <div class="rc-field"><label>Hạn nộp</label><input type="date" name="deadline" value="<?= h($job['deadline']) ?>"></div>
                <div class="rc-field"><label>Trạng thái</label><select name="status">
                    <?php foreach ($statusLabel as $k=>$v): ?><option value="<?= $k ?>" <?= $k==$job['status']?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="rc-field"><label>Mô tả công việc (JD)</label>
                <input type="hidden" name="description" id="jdInput">
                <div id="jdEditor" style="height:280px;background:#fff"></div>
            </div>
            <div class="rc-field"><label>Yêu cầu kỹ năng</label><textarea name="jd_skills" rows="3"><?= h($job['jd_skills']) ?></textarea></div>
            <div class="rc-field"><label>KPI thử việc</label><textarea name="probation_kpi" rows="3"><?= h($job['probation_kpi']) ?></textarea></div>
            <button class="rc-btn" onclick="saveJob()">Lưu tin</button>
        </form>
    </div>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
    var jdQuill = new Quill('#jdEditor', {theme:'snow', placeholder:'Mô tả công việc, trách nhiệm, yêu cầu...',
        modules:{
            toolbar: [[{header:[2,3,false]}],['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link','image'],['clean']]
        }});
    jdQuill.root.innerHTML = <?= json_encode($job['description'] ?: '', JSON_UNESCAPED_UNICODE) ?>;
    function saveJob(){
        document.getElementById('jdInput').value = jdQuill.getText().trim() ? jdQuill.root.innerHTML : '';
        const fd=new FormData(document.getElementById('jobForm'));fd.append('action','save_job');
        fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            if(j.ok) {
                let msg = 'Lưu tin tuyển dụng thành công!';
                if (j.sync && j.sync.ok) {
                    msg += '\nTrạng thái: Đã tự động đồng bộ lên Website.';
                } else if (j.sync && !j.sync.ok && j.sync.error) {
                    msg += '\nCảnh báo đồng bộ: ' + j.sync.error;
                }
                let type = 'success';
                if (j.sync && !j.sync.ok) { type = 'warning'; }
                localStorage.setItem('job_toast', JSON.stringify({msg: msg, type: type}));
                location.href='/hrm/job?id=<?= $id ?>';
            } else {
                showToast(j.error||'Lỗi', 'error');
            }
        });
    }
    </script>
    <?php hrm_footer(); exit;
}

/* ── Detail + pipeline ────────────────────────────────────────────────── */
$stages = $conn->query('SELECT * FROM hrm_pipeline_stages WHERE active=1 ORDER BY sort_order')->fetch_all(MYSQLI_ASSOC);
$apps = $conn->query("SELECT a.id, a.stage_id, a.status, a.applied_at, a.score, a.rating, a.owner_id, c.full_name, c.email, c.phone, c.current_position, src.name AS source_name, ow.full_name AS owner_name, ow.avatar AS owner_avatar
        FROM hrm_applications a JOIN hrm_candidates c ON c.id=a.candidate_id
        LEFT JOIN hrm_candidate_sources src ON src.id=c.source_id
        LEFT JOIN users ow ON ow.id=a.owner_id
        WHERE a.job_id=$id ORDER BY a.applied_at DESC")->fetch_all(MYSQLI_ASSOC);
// Active users for the inline assignee picker (+ JS map for no-reload update).
$pickUsers = $conn->query("SELECT id, full_name, avatar FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$usersJs = [];
foreach ($pickUsers as $u) { $usersJs[(int)$u['id']] = ['n' => $u['full_name'], 'av' => $u['avatar'] ?: '']; }
$byStage = [];
foreach ($apps as $a) { $byStage[(int)$a['stage_id']][] = $a; }
$appIds = array_map(fn($a) => (int)$a['id'], $apps);

// Stage lookup (code + current SLA hours) for live SLA computation.
$stageById = [];
foreach ($stages as $sg) { $stageById[(int)$sg['id']] = $sg; }

// Stage owners (BC / TA) for this job's department.
$ownerMap = [];
$jdept = (int)$job['department_id'];
if ($jdept) {
    $res = $conn->query("SELECT so.stage_id, so.owner_type, so.user_id, u.full_name, u.avatar FROM hrm_stage_owners so JOIN users u ON u.id=so.user_id WHERE so.department_id=$jdept");
    while ($r = $res->fetch_assoc()) { $ownerMap[(int)$r['stage_id']][$r['owner_type']] = ['id' => (int)$r['user_id'], 'name' => $r['full_name'], 'avatar' => $r['avatar']]; }
}
// Default assignee for a stage = its TA owner, else BC owner.
$stageDefault = function (int $stageId) use ($ownerMap) {
    return $ownerMap[$stageId]['ta'] ?? $ownerMap[$stageId]['bc'] ?? null;
};
// Explicit per-stage assignee for these applications.
$assignMap = [];
if ($appIds) {
    $res = $conn->query("SELECT aa.application_id, aa.stage_id, aa.user_id, u.full_name, u.avatar FROM hrm_application_assignees aa JOIN users u ON u.id=aa.user_id WHERE aa.application_id IN (" . implode(',', $appIds) . ")");
    while ($r = $res->fetch_assoc()) { $assignMap[(int)$r['application_id']][(int)$r['stage_id']] = ['id' => (int)$r['user_id'], 'name' => $r['full_name'], 'avatar' => $r['avatar']]; }
}

// Stage-entry time per application: latest SLA event matching each stage's code.
// We recompute the deadline from the CURRENT stage SLA setting (not the stored due_at),
// so changing SLA in settings updates the cards immediately.
$entryMap = [];
if ($appIds) {
    $res = $conn->query("SELECT entity_id, event_key, created_at FROM hrm_sla_events WHERE entity_type='application' AND entity_id IN (" . implode(',', $appIds) . ") ORDER BY id DESC");
    while ($r = $res->fetch_assoc()) {
        $eid = (int)$r['entity_id']; $k = $r['event_key'];
        if (!isset($entryMap[$eid][$k])) { $entryMap[$eid][$k] = $r['created_at']; }
    }
}
$hrm_dur = function (int $secs) { $h = (int)round($secs / 3600); return $h >= 24 ? (intdiv($h, 24) . ' ngày') : ($h . 'h'); };
// Returns [remaining_seconds] or null when the current stage has no SLA / no entry time.
$hrm_app_sla = function (array $a) use ($stageById, $entryMap) {
    $sg = $stageById[(int)$a['stage_id']] ?? null;
    if (!$sg || (int)$sg['sla_hours'] <= 0) { return null; }
    $code = strtolower($sg['code']);
    $entry = $entryMap[(int)$a['id']][$code] ?? null;
    if (!$entry) { return null; }
    return (strtotime($entry) + (int)$sg['sla_hours'] * 3600) - time();
};

// Avatar: initials + a stable color from the name.
$avatarPalette = ['#0071e3','#34c759','#ff9500','#af52de','#ff2d55','#5ac8fa','#ffcc00','#ff3b30','#30b0c7','#a2845e'];
$hrm_avatar = function (string $name) use ($avatarPalette) {
    $parts = preg_split('/\s+/', trim($name));
    $ini = mb_strtoupper(mb_substr(end($parts) ?: $name, 0, 1) . (count($parts) > 1 ? mb_substr($parts[0], 0, 1) : ''));
    $color = $avatarPalette[abs(crc32($name)) % count($avatarPalette)];
    return [$ini, $color];
};

// Kênh đăng tin + trạng thái đã đăng cho tin này.
$pubChannels = hrm_channels($conn, true);
$chPosts = [];
$pr = $conn->query('SELECT channel_id, status, post_url, posted_at FROM hrm_job_channel_posts WHERE job_id = ' . $id);
while ($x = $pr->fetch_assoc()) { $chPosts[(int)$x['channel_id']] = $x; }

hrm_header('Tin tuyển dụng', '', 'jobs');
?>
<div class="rc-toolbar">
    <a href="/hrm/jobs" class="rc-tab">← Tin tuyển dụng</a>
    <div style="display:flex;gap:8px">
        <?php if (!empty($job['channel_url'])): ?>
            <button class="rc-btn ghost" disabled style="opacity:0.6;cursor:not-allowed" title="Tin đã được tự động đồng bộ trên Web">Đã Publish</button>
        <?php else: ?>
            <button class="rc-btn ghost" onclick="syncWebsite(event)">Publish tin tuyển dụng</button>
        <?php endif; ?>
        <button class="rc-btn ghost" onclick="document.getElementById('postChModal').style.display='flex'">Đăng lên kênh</button>
        <button class="rc-btn" onclick="document.getElementById('addCand').style.display='flex'">+ Thêm ứng viên</button>
        <a href="/hrm/job?id=<?= $id ?>&edit=1" class="rc-btn ghost">Sửa tin</a>
    </div>
</div>

<!-- Modal: đăng tin lên kênh -->
<div id="postChModal" class="rc-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:14px;padding:22px;width:480px;max-width:92vw;max-height:86vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.2)">
        <h3 style="font-size:16px;margin:0 0 4px">Đăng tin lên kênh</h3>
        <p style="font-size:12px;color:#86868b;margin:0 0 16px">Chọn kênh để đăng tin tuyển dụng này (Facebook / LinkedIn / Webhook).</p>
        <?php if (!$pubChannels): ?>
            <div style="font-size:13px;color:#6e6e73;padding:10px 0">
                Chưa có kênh nào đang bật. Vào <a href="/hrm/settings?tab=channels_cfg" style="color:#0071e3">Cấu hình → Kênh đăng tin</a> để thêm.
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px">
            <?php foreach ($pubChannels as $c): $p = $chPosts[(int)$c['id']] ?? null; ?>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e8e8ed;border-radius:10px;font-size:13px;cursor:pointer">
                    <input type="checkbox" class="pc-ch" value="<?= (int)$c['id'] ?>">
                    <span style="flex:1"><?= h($c['icon']) ?> <b><?= h($c['name']) ?></b></span>
                    <?php if ($p): ?>
                        <?php if ($p['status'] === 'success'): ?>
                            <span style="font-size:11px;color:#16a34a">✓ Đã đăng <?= date('d/m H:i', strtotime($p['posted_at'])) ?><?php if (!empty($p['post_url'])): ?> · <a href="<?= h($p['post_url']) ?>" target="_blank" style="color:#0071e3">xem</a><?php endif; ?></span>
                        <?php else: ?>
                            <span style="font-size:11px;color:#dc2626">✕ Lỗi lần trước</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            </div>
            <div id="pcResult" style="font-size:12px;margin-bottom:12px"></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:flex-end;gap:8px">
            <button class="rc-btn ghost" onclick="document.getElementById('postChModal').style.display='none'">Đóng</button>
            <?php if ($pubChannels): ?><button class="rc-btn" id="pcBtn" onclick="postChannels(<?= $id ?>)">Đăng tin</button><?php endif; ?>
        </div>
    </div>
</div>

<?php
$scolors = ['open' => '#16a34a', 'draft' => '#94a3b8', 'on_hold' => '#d97706', 'closed' => '#dc2626'];
$sLabel = $statusLabel[$job['status']] ?? $job['status'];
$sColor = $scolors[$job['status']] ?? '#86868b';
$totalApps = count($apps);
$hiredApps = 0; foreach ($apps as $aa) { if ($aa['status'] === 'hired') { $hiredApps++; } }
$offName = ''; foreach ($offices as $o) { if ((int)$o['id'] === (int)$job['office_id']) { $offName = $o['name']; } }
$deptN = ''; foreach ($departments as $d) { if ((int)$d['id'] === (int)$job['department_id']) { $deptN = $d['name']; } }
$startD = $job['source_start'] ?: $job['created_at'];
?>
<h1 style="font-size:22px;font-weight:700;color:#1d1d1f;letter-spacing:-.02em;margin:2px 0 8px"><?= h($job['title']) ?></h1>
<div class="jh-meta">
    <?php if ($job['code']): ?><span class="jh-code"><?= h($job['code']) ?></span><?php endif; ?>
    <span class="jh-status"><i style="background:<?= $sColor ?>"></i><?= h($sLabel) ?></span>
    <span>&#9201; <?= date('d/m/Y', strtotime($startD)) ?> &mdash; <?= $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : 'Không thời hạn' ?></span>
    <span><b><?= $totalApps ?></b> ứng viên</span>
    <span>Đã tuyển <b><?= $hiredApps ?></b>/<?= (int)$job['headcount'] ?></span>
    <?php if ($deptN): ?><span><?= h($deptN) ?></span><?php endif; ?>
    <?php if ($offName): ?><span>&#128205; <?= h($offName) ?></span><?php endif; ?>
    <?php if (!empty($job['channel_url'])): ?><span>🌐 <a href="<?= h($job['channel_url']) ?>" target="_blank" style="color:#0071e3;text-decoration:none">Xem trên Website</a></span><?php endif; ?>
</div>
<style>
.jh-meta{display:flex;align-items:center;gap:8px 18px;flex-wrap:wrap;padding-bottom:16px;margin-bottom:18px;border-bottom:1px solid #eceef1;font-size:13px;color:#6e6e73}
.jh-meta b{color:#1d1d1f}
.jh-code{background:#e3f6e9;color:#2e844a;font-weight:700;font-size:12px;padding:3px 10px;border-radius:6px}
.jh-status{display:inline-flex;align-items:center;gap:6px;font-weight:600;color:#1d1d1f}
.jh-status i{width:8px;height:8px;border-radius:50%;display:inline-block}
</style>



<div class="kb-board">
<?php foreach ($stages as $idx => $s): $list = $byStage[(int)$s['id']] ?? [];
    $type = $s['stage_type'] ?? 'standard';
    $hc = $type === 'hired' ? '#2e844a' : ($type === 'rejected' ? '#ba0517' : ($type === 'offered' ? '#fe9339' : '#1b96ff'));
    $n = count($list);
?>
    <div class="kb-col" ondragover="event.preventDefault();this.classList.add('kb-over')" ondragleave="this.classList.remove('kb-over')" ondrop="this.classList.remove('kb-over');dropCard(event,<?= $s['id'] ?>)">
        <div class="kb-head<?= $idx===0?' first':'' ?>">
            <div class="kb-h1"><b><?= h($s['name']) ?></b><span class="kb-chev">»</span></div>
            <div class="kb-line"><i style="width:<?= $n?100:0 ?>%;background:<?= $hc ?>"></i></div>
            <div class="kb-h2"><span><?= $n ?> ứng viên · 0 Quá hạn</span><span class="kb-clock">&#9201; 0.00h</span></div>
        </div>
        <div class="kb-body">
        <?php if (!$list): ?><div class="kb-empty">Kéo ứng viên vào đây</div><?php endif; ?>
        <?php foreach ($list as $a): [$ini, $avc] = $hrm_avatar($a['full_name']); ?>
            <div class="kb-card" draggable="true" ondragstart="dragCard(event,<?= $a['id'] ?>)" onclick="location.href='/hrm/application?id=<?= $a['id'] ?>'">
                <div class="kb-top">
                    <div class="kb-av" style="background:<?= $avc ?>"><?= h($ini) ?></div>
                    <div style="flex:1;min-width:0">
                        <div class="kb-name"><?= h($a['full_name']) ?></div>
                        <div class="kb-sub"><?= h($a['phone'] ?: ($a['current_position'] ?: ($a['email'] ?: 'Không có SĐT'))) ?></div>
                    </div>
                    <?php if ($a['status']==='hired'): ?><span class="kb-tag ok">Tuyển</span><?php elseif ($a['status']==='rejected'): ?><span class="kb-tag no">Loại</span><?php endif; ?>
                </div>
                <div class="kb-stars" onclick="event.stopPropagation()">
                    <?php for ($i = 1; $i <= 5; $i++): ?><span class="kb-star<?= $i <= (int)$a['rating'] ? ' on' : '' ?>" onclick="rate(<?= $a['id'] ?>,<?= $i ?>,this)">&#9733;</span><?php endfor; ?>
                </div>
                <div class="kb-chips">
                    <?php if ($a['source_name']): ?><span class="kb-src">&#128225; <?= h($a['source_name']) ?></span><?php endif; ?>
                    <?php $rem = $a['status'] === 'active' ? $hrm_app_sla($a) : null;
                    if ($rem !== null):
                        $over = $rem < 0; $cls = $over ? 'over' : ($rem < 12 * 3600 ? 'warn' : 'ok'); ?>
                        <span class="kb-sla <?= $cls ?>">&#9201; SLA: <?= $over ? 'quá hạn ' . $hrm_dur(-$rem) : 'còn ' . $hrm_dur($rem) ?></span>
                    <?php endif; ?>
                </div>
                <?php
                $sid = (int)$s['id'];
                $assignee = $assignMap[$a['id']][$sid] ?? null;
                $default = $stageDefault($sid);
                $disp = $assignee ?: $default;
                $selVal = $assignee ? (int)$assignee['id'] : 0;
                $dispAv = ''; $dispBg = '';
                if ($disp) {
                    if (!empty($disp['avatar'])) { $dispAv = '<img src="' . h($disp['avatar']) . '" alt="">'; }
                    else { [$di, $dc] = $hrm_avatar($disp['name']); $dispAv = h($di); $dispBg = $dc; }
                }
                ?>
                <div class="kb-assign" onclick="event.stopPropagation()" title="Phụ trách giai đoạn này - bấm để đổi">
                    <span class="kb-assign-av<?= $disp ? '' : ' none' ?>" id="oav<?= $a['id'] ?>" style="<?= $dispBg ? 'background:' . $dispBg : '' ?>"><?= $dispAv ?: '+' ?></span>
                    <span class="kb-assign-nm" id="onm<?= $a['id'] ?>"><?= h($disp['name'] ?? 'Gán phụ trách') ?></span>
                    <select class="kb-assign-sel" data-def-nm="<?= h($default['name'] ?? '') ?>" data-def-av="<?= h($default['avatar'] ?? '') ?>" onchange="setOwner(<?= $a['id'] ?>,<?= $sid ?>,this.value,this)">
                        <option value="0"><?= $default ? 'Mặc định: ' . h($default['name']) : '- Chưa gán -' ?></option>
                        <?php foreach ($pickUsers as $pu): ?><option value="<?= $pu['id'] ?>"<?= $selVal===(int)$pu['id']?' selected':'' ?>><?= h($pu['full_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Add candidate modal -->
<div id="addCand" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div class="rc-card" style="width:440px;max-width:92vw">
        <h3 style="font-size:15px;margin-bottom:12px">Thêm ứng viên</h3>
        <form id="candForm" onsubmit="return false">
            <input type="hidden" name="job_id" value="<?= $id ?>">
            <div class="rc-field"><label>Họ tên *</label><input name="full_name" required></div>
            <div class="rc-field"><label>Email</label><input name="email" type="email"></div>
            <div class="rc-field"><label>Điện thoại</label><input name="phone"></div>
            <div class="rc-field"><label>Nguồn</label><select name="source_id"><option value="0">-</option>
                <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button class="rc-btn ghost" onclick="document.getElementById('addCand').style.display='none'">Hủy</button>
                <button class="rc-btn" onclick="addCand()">Thêm</button>
            </div>
        </form>
    </div>
</div>

<style>
.kb-board{display:flex;align-items:stretch;overflow-x:auto;overflow-y:hidden;padding:6px 0 0;gap:0;scrollbar-width:none;-ms-overflow-style:none}
.kb-board::-webkit-scrollbar{display:none;height:0}
.kb-col{min-width:252px;width:252px;flex-shrink:0;display:flex;flex-direction:column}
/* Base-style chevron header tab */
.kb-head{position:relative;background:#fbfbfd;padding:12px 30px 11px 18px;margin-right:-16px;
    box-shadow:inset 0 -1px 0 #e3e6ea;
    clip-path:polygon(0 0,calc(100% - 16px) 0,100% 50%,calc(100% - 16px) 100%,0 100%,16px 50%)}
.kb-head.first{padding-left:16px;clip-path:polygon(0 0,calc(100% - 16px) 0,100% 50%,calc(100% - 16px) 100%,0 100%)}
.kb-col:hover .kb-head{background:#f3f5f8}
.kb-h1{display:flex;align-items:center;justify-content:space-between}
.kb-h1 b{font-size:13.5px;font-weight:700;color:#1d1d1f;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kb-chev{color:#c2c8d0;font-size:15px;font-weight:700;margin-left:6px}
.kb-line{height:3px;border-radius:3px;background:#e7eaee;margin:9px 0 8px;overflow:hidden}
.kb-line i{display:block;height:100%}
.kb-h2{display:flex;align-items:center;justify-content:space-between;font-size:11.5px;color:#86868b}
.kb-clock{white-space:nowrap}
.kb-own{display:flex;align-items:center;gap:5px;margin-top:8px}
.kb-own-ic{width:14px;height:14px;fill:none;stroke:#a4adba;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.kb-ava-wrap{position:relative;display:inline-block;margin-left:3px}
.kb-ava{width:24px;height:24px;border-radius:50%;object-fit:cover;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:9px;border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.06);vertical-align:middle}
.kb-ava-r{position:absolute;bottom:-4px;right:-4px;background:#1d1d1f;color:#fff;font-size:7px;font-weight:700;font-style:normal;line-height:1;padding:1px 3px;border-radius:6px;border:1px solid #fff}
.kb-body{flex:1;min-height:0;overflow-y:auto;padding:12px 8px 14px;display:flex;flex-direction:column;gap:8px;border-right:1px solid #eef0f3;transition:background .12s;scrollbar-width:thin}
.kb-body::-webkit-scrollbar{width:6px}.kb-body::-webkit-scrollbar-thumb{background:#d6dbe2;border-radius:3px}
.kb-col:last-child .kb-body{border-right:none}
.kb-col.kb-over .kb-body{background:#eff6ff}
.kb-empty{border:1.5px dashed #d9dee5;border-radius:10px;color:#aab2bd;font-size:12px;text-align:center;padding:20px 8px}
.kb-card{background:#fff;border:1px solid #e5e9ef;border-radius:10px;padding:10px 11px;cursor:pointer;
    box-shadow:0 1px 2px rgba(0,0,0,.06);transition:.12s}
.kb-card:hover{box-shadow:0 6px 16px rgba(0,0,0,.12);transform:translateY(-2px)}
.kb-top{display:flex;align-items:center;gap:9px}
.kb-av{width:32px;height:32px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px}
.kb-name{font-weight:600;font-size:13.5px;color:#0071e3;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kb-sub{font-size:12px;color:#86868b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kb-chips{margin-top:7px;display:flex;flex-wrap:wrap;gap:5px}
.kb-src{font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px;background:#f5f5f7;color:#6e6e73}
.kb-sla{font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px}
.kb-sla.ok{background:#eef6ff;color:#0071e3}.kb-sla.warn{background:#fff4e5;color:#b25e00}.kb-sla.over{background:#fdeceb;color:#ba0517}
.kb-stars{margin-top:6px;display:flex;gap:1px}
.kb-star{color:#d6dbe2;font-size:14px;cursor:pointer;line-height:1;transition:.1s}
.kb-star:hover{transform:scale(1.15)}
.kb-star.on{color:#ffb400}
.kb-tag{font-size:10px;font-weight:700;padding:2px 8px;border-radius:980px;flex-shrink:0}
.kb-tag.ok{background:#e3f6e9;color:#2e844a}.kb-tag.no{background:#fdeceb;color:#ba0517}
.kb-assign{position:relative;display:flex;align-items:center;gap:6px;margin-top:8px;padding-top:7px;border-top:1px solid #f0f1f3;font-size:11px;color:#515154;cursor:pointer}
.kb-assign:hover{color:#0071e3}
.kb-assign-av{width:22px;height:22px;border-radius:50%;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:9px;flex-shrink:0}
.kb-assign-av img{width:100%;height:100%;object-fit:cover}
.kb-assign-av.none{background:#eceef1;color:#a4adba;font-size:14px;font-weight:400}
.kb-assign-nm{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kb-assign-sel{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
</style>
<script>
function sizeBoard(){var b=document.querySelector('.kb-board');if(!b)return;
    var top=b.getBoundingClientRect().top;
    b.style.height=Math.max(320,window.innerHeight-top-14)+'px';}
window.addEventListener('resize',sizeBoard);window.addEventListener('load',sizeBoard);sizeBoard();
var dragId=null;
function dragCard(e,id){dragId=id;e.dataTransfer.effectAllowed='move';}
function dropCard(e,stageId){e.preventDefault();if(!dragId)return;
    const fd=new FormData();fd.append('action','move_stage');fd.append('application_id',dragId);fd.append('stage_id',stageId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
    dragId=null;}
function addCand(){
    const f=document.getElementById('candForm');if(!f.full_name.value.trim()){alert('Nhập họ tên');return;}
    const fd=new FormData(f);fd.append('action','add_candidate');
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});
}
function syncWebsite(e){
    const btn = e.target; const old = btn.textContent;
    btn.textContent = 'Đang đồng bộ...'; btn.disabled = true;
    const fd = new FormData(); fd.append('action', 'sync_job_channel'); fd.append('id', <?= $id ?>);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(!j.ok) { showToast(j.error||'Lỗi đồng bộ', 'error'); btn.textContent = old; btn.disabled = false; }
        else { localStorage.setItem('job_toast', JSON.stringify({msg: 'Đã đẩy tin lên Website thành công!', type: 'success'})); location.reload(); }
    }).catch(()=>{ showToast('Lỗi mạng', 'error'); btn.textContent = old; btn.disabled = false; });
}
function postChannels(jobId){
    const ids = Array.from(document.querySelectorAll('.pc-ch:checked')).map(c=>c.value);
    const res = document.getElementById('pcResult');
    if(!ids.length){ res.innerHTML = '<span style="color:#dc2626">Chọn ít nhất 1 kênh.</span>'; return; }
    const btn = document.getElementById('pcBtn'); btn.disabled = true; btn.textContent = 'Đang đăng...';
    const fd = new FormData(); fd.append('action','post_job_channels'); fd.append('job_id',jobId); fd.append('channel_ids',ids.join(','));
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        btn.disabled = false; btn.textContent = 'Đăng tin';
        if(!j.ok){ res.innerHTML = '<span style="color:#dc2626">'+(j.error||'Lỗi')+'</span>'; return; }
        if(j.posted === j.total){
            localStorage.setItem('job_toast', JSON.stringify({msg:'Đã đăng tin lên '+j.posted+'/'+j.total+' kênh!', type:'success'}));
            location.reload();
        } else {
            let html = '<span style="color:#d97706">Đăng '+j.posted+'/'+j.total+' kênh. Lỗi: </span>';
            for(const k in j.results){ if(!j.results[k].ok){ html += '<div style="color:#dc2626">• '+(j.results[k].error||'lỗi')+'</div>'; } }
            res.innerHTML = html;
        }
    }).catch(()=>{ btn.disabled=false; btn.textContent='Đăng tin'; res.innerHTML='<span style="color:#dc2626">Lỗi mạng</span>'; });
}
var HRM_USERS = <?= json_encode($usersJs, JSON_UNESCAPED_UNICODE) ?>;
var HRM_PAL = ['#0071e3','#34c759','#ff9500','#af52de','#ff2d55','#5ac8fa','#ffcc00','#ff3b30','#30b0c7','#a2845e'];
function hrmInit(name){var p=name.trim().split(/\s+/);var last=p[p.length-1]||name;return ((last[0]||'')+(p.length>1?(p[0][0]||''):'')).toUpperCase();}
function hrmColor(name){var s=0;for(var i=0;i<name.length;i++)s+=name.charCodeAt(i);return HRM_PAL[s%HRM_PAL.length];}
function renderAvatar(av,nm,name,avatarUrl){
    if(!name){av.className='kb-assign-av none';av.style.background='';av.innerHTML='+';nm.textContent='Gán phụ trách';return;}
    av.className='kb-assign-av';nm.textContent=name;
    if(avatarUrl){av.style.background='';av.innerHTML='<img src="'+avatarUrl+'" alt="">';}
    else{av.style.background=hrmColor(name);av.textContent=hrmInit(name);}
}
function setOwner(appId,stageId,userId,sel){
    const fd=new FormData();fd.append('action','set_application_owner');fd.append('application_id',appId);fd.append('stage_id',stageId);fd.append('user_id',userId);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(!j.ok){alert(j.error||'Lỗi');return;}
        const av=document.getElementById('oav'+appId), nm=document.getElementById('onm'+appId);
        if(userId==='0'||userId===0){ renderAvatar(av,nm,sel.dataset.defNm||'',sel.dataset.defAv||''); return; }
        const u=HRM_USERS[userId]||{n:'',av:''};
        renderAvatar(av,nm,u.n,u.av);
    });
}
function rate(appId,val,el){
    const stars=el.parentNode.querySelectorAll('.kb-star');
    stars.forEach((s,i)=>s.classList.toggle('on', i<val));
    const fd=new FormData();fd.append('action','rate_application');fd.append('application_id',appId);fd.append('rating',val);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(!j.ok)alert(j.error||'Lỗi');});
}
</script>
<?php
hrm_footer();
