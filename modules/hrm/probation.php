<?php
/**
 * Probation review (§6 / Onboarding GĐ6) - weighted 50/20/20/10 -> Confirm/Extend/Reject.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$id = (int)($_GET['id'] ?? 0); // onboarding id when reviewing
$decLabel = ['confirm' => 'Chính thức (Confirm)', 'extend' => 'Gia hạn (Extend)', 'reject' => 'Không tiếp nhận (Reject)'];
$decBadge = ['confirm' => 'approved', 'extend' => 'pending', 'reject' => 'rejected'];

/* ── Review form ──────────────────────────────────────────────────────── */
if ($id) {
    $o = $conn->query('SELECT * FROM hrm_onboarding WHERE id = ' . $id)->fetch_assoc();
    if (!$o) { hrm_header('Không tìm thấy', '', 'probation'); echo '<div class="rc-empty">Không tồn tại.</div>'; hrm_footer(); exit; }
    $pr = $conn->query('SELECT * FROM hrm_probation_reviews WHERE onboarding_id = ' . $id . ' ORDER BY id DESC LIMIT 1')->fetch_assoc();

    hrm_header('Đánh giá thử việc: ' . $o['candidate_name'], $o['job_title'], 'probation');
    ?>
    <div class="rc-toolbar"><a href="/hrm/probation" class="rc-tab">← Danh sách</a></div>
    <div class="rc-card" style="max-width:680px">
        <h3 style="font-size:14px;margin-bottom:4px">Tiêu chí (theo trọng số)</h3>
        <div class="rc-muted" style="margin-bottom:14px">Tổng 100đ · ≥85 Chính thức · 75-84 Gia hạn · &lt;75 Không tiếp nhận</div>
        <form id="prForm" onsubmit="return false">
            <input type="hidden" name="onboarding_id" value="<?= $id ?>">
            <input type="hidden" name="application_id" value="<?= (int)$o['application_id'] ?>">
            <div class="rc-grid2">
                <div class="rc-field"><label>KPI công việc (/50)</label><input type="number" min="0" max="50" id="s_kpi" value="<?= (int)($pr['score_kpi'] ?? 0) ?>" oninput="calc()"></div>
                <div class="rc-field"><label>Chuyên môn (/20)</label><input type="number" min="0" max="20" id="s_comp" value="<?= (int)($pr['score_competency'] ?? 0) ?>" oninput="calc()"></div>
                <div class="rc-field"><label>Thái độ (/20)</label><input type="number" min="0" max="20" id="s_att" value="<?= (int)($pr['score_attitude'] ?? 0) ?>" oninput="calc()"></div>
                <div class="rc-field"><label>Văn hóa AHT (/10)</label><input type="number" min="0" max="10" id="s_cul" value="<?= (int)($pr['score_culture'] ?? 0) ?>" oninput="calc()"></div>
            </div>
            <div class="rc-card" style="background:#f8fafc;display:flex;justify-content:space-between;align-items:center;margin:4px 0 14px">
                <div>Tổng điểm: <b id="total" style="font-size:20px"><?= (int)($pr['total'] ?? 0) ?></b>/100</div>
                <div>Đề xuất: <span id="suggest" class="rc-badge rc-b-pending">-</span></div>
            </div>
            <div class="rc-field"><label>Quyết định</label>
                <select id="decision">
                    <?php foreach ($decLabel as $k => $v): ?><option value="<?= $k ?>"<?= ($pr['decision'] ?? '')===$k?' selected':'' ?>><?= h($v) ?></option><?php endforeach; ?>
                </select></div>
            <div class="rc-field"><label>Nhận xét</label><textarea id="notes" rows="3"><?= h($pr['notes'] ?? '') ?></textarea></div>
            <button class="rc-btn" onclick="savePr()">Lưu đánh giá</button>
        </form>
    </div>
    <script>
    function calc(){
        const t=(+document.getElementById('s_kpi').value||0)+(+document.getElementById('s_comp').value||0)+(+document.getElementById('s_att').value||0)+(+document.getElementById('s_cul').value||0);
        document.getElementById('total').textContent=t;
        const sug=document.getElementById('suggest');
        let d='reject',lbl='Không tiếp nhận',cls='rc-b-rejected';
        if(t>=85){d='confirm';lbl='Chính thức';cls='rc-b-approved';}else if(t>=75){d='extend';lbl='Gia hạn';cls='rc-b-pending';}
        sug.textContent=lbl; sug.className='rc-badge '+cls;
        return d;
    }
    const _sug=calc();
    <?php if (!$pr): ?>document.getElementById('decision').value=_sug;<?php endif; ?>
    function savePr(){
        const d={onboarding_id:<?= $id ?>,application_id:<?= (int)$o['application_id'] ?>,
            score_kpi:document.getElementById('s_kpi').value,score_competency:document.getElementById('s_comp').value,
            score_attitude:document.getElementById('s_att').value,score_culture:document.getElementById('s_cul').value,
            decision:document.getElementById('decision').value,notes:document.getElementById('notes').value};
        const fd=new FormData();fd.append('action','save_probation');for(const k in d)fd.append(k,d[k]);
        fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.href='/hrm/probation':alert(j.error||'Lỗi');});
    }
    </script>
    <?php hrm_footer(); exit;
}

/* ── List ─────────────────────────────────────────────────────────────── */
$rows = $conn->query("SELECT o.id, o.candidate_name, o.job_title, o.start_date, o.status,
        pr.total, pr.decision
        FROM hrm_onboarding o
        LEFT JOIN hrm_probation_reviews pr ON pr.id = (SELECT MAX(id) FROM hrm_probation_reviews WHERE onboarding_id=o.id)
        ORDER BY o.id DESC")->fetch_all(MYSQLI_ASSOC);

hrm_header('Đánh giá thử việc', 'Probation review - quyết định cuối kỳ', 'probation');
?>
<?php if (!$rows): ?>
    <div class="rc-empty">Chưa có nhân sự nào để đánh giá. Tạo onboarding trước ở module Onboarding.</div>
<?php else: ?>
<table class="rc-table">
    <thead><tr><th>Nhân sự</th><th>Vị trí</th><th>Bắt đầu</th><th>Hết 60 ngày</th><th>Tổng điểm</th><th>Kết quả</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $day60 = $r['start_date'] ? date('d/m/Y', strtotime('+60 days', strtotime($r['start_date']))) : '-'; ?>
        <tr>
            <td><b><?= h($r['candidate_name']) ?></b></td>
            <td><?= h($r['job_title'] ?: '-') ?></td>
            <td><?= $r['start_date'] ? date('d/m/Y', strtotime($r['start_date'])) : '-' ?></td>
            <td><?= $day60 ?></td>
            <td><?= $r['decision'] ? (int)$r['total'] . '/100' : '-' ?></td>
            <td><?= $r['decision'] ? '<span class="rc-badge rc-b-' . $decBadge[$r['decision']] . '">' . h($decLabel[$r['decision']]) . '</span>' : '<span class="rc-muted">Chưa đánh giá</span>' ?></td>
            <td style="text-align:right"><a class="rc-btn ghost" style="padding:5px 12px" href="/hrm/probation?id=<?= $r['id'] ?>"><?= $r['decision'] ? 'Xem / sửa' : 'Đánh giá' ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
hrm_footer();
