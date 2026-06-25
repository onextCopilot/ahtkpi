<?php
/**
 * HRM Executive Dashboard — /hrm/dashboard
 * Real-time stats, recruitment funnel, SLA alerts, activity feed, dept workload.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/approval.php';
require_once __DIR__ . '/lib/kpi.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$uid     = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$myRoles = hrm_roles_of($conn, $uid);

/* ── Period filter ── */
$preset = $_GET['period'] ?? 'month';
if (!in_array($preset, ['month','quarter','all'], true)) { $preset = 'month'; }
[$from, $to, $periodLabel] = hrm_kpi_period($preset);

/* ── Data ── */
$counts  = hrm_dashboard_counts($conn);
$alerts  = hrm_dashboard_alerts($conn);
$depts   = hrm_dashboard_dept_load($conn);
$activity= hrm_dashboard_activity($conn, 12);
$m       = hrm_kpi_metrics($conn, $from, $to);

/* ── HRFs waiting for me to approve ── */
$waiting = [];
$pending = $conn->query("SELECT rq.*, d.name AS dept_name, o.name AS office_name, u.full_name AS creator_name
    FROM hrm_requests rq
    LEFT JOIN departments d ON d.id = rq.department_id
    LEFT JOIN hrm_offices o ON o.id = rq.office_id
    LEFT JOIN users u ON u.id = rq.created_by
    WHERE rq.status='pending' ORDER BY rq.id DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($pending as $r) {
    $cur = hrm_approval_current($conn, 'hrf', (int)$r['id']);
    if ($cur && hrm_user_has_role($conn, $uid, $cur['approver_role'])) {
        $waiting[] = $r + ['_due' => $cur['due_at']];
    }
}

/* ── Activity: relative time ── */
function hrm_rel_time(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60) return 'vừa xong';
    if ($diff < 3600) return (int)($diff/60) . ' phút trước';
    if ($diff < 86400) return (int)($diff/3600) . ' giờ trước';
    return (int)($diff/86400) . ' ngày trước';
}

/* ── Activity icon per entity ── */
function hrm_act_icon(string $action): string {
    $map = [
        'candidate_add'      => ['#2563eb', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>'],
        'hrf_create'         => ['#7c3aed', '<path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/>'],
        'hrf_update'         => ['#0284c7', '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'],
        'stage_move'         => ['#0e9f6e', '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>'],
        'candidate_update'   => ['#64748b', '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'],
        'application_hire'   => ['#16a34a', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
        'application_reject' => ['#dc2626', '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'],
        'approve'            => ['#16a34a', '<path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
        'reject'             => ['#dc2626', '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'],
        'job_create'         => ['#0284c7', '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
        'send_candidate_email' => ['#0e9f6e', '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'],
    ];
    $a = $action;
    foreach ($map as $k => $v) { if (str_contains($a, $k)) return '<div class="dsh-act-ic" style="background:' . $v[0] . '20;color:' . $v[0] . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $v[1] . '</svg></div>'; }
    return '<div class="dsh-act-ic" style="background:#f1f5f9;color:#64748b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>';
}

/* ── Activity action label ── */
function hrm_act_label(string $action, string $detail): string {
    $labels = [
        'candidate_add'       => 'Thêm ứng viên mới',
        'candidate_import'    => 'Import ứng viên',
        'hrf_create'          => 'Tạo HRF',
        'hrf_update'          => 'Cập nhật HRF',
        'hrf_cancel'          => 'Hủy HRF',
        'hrf_reopen'          => 'Mở lại HRF',
        'stage_move'          => 'Chuyển giai đoạn',
        'candidate_update'    => 'Cập nhật ứng viên',
        'application_hire'    => 'Xác nhận tuyển dụng',
        'application_reject'  => 'Từ chối ứng viên',
        'application_assign'  => 'Phân công ứng viên',
        'approve'             => 'Duyệt HRF',
        'reject'              => 'Từ chối HRF',
        'job_create'          => 'Tạo tin tuyển dụng',
        'job_save'            => 'Cập nhật tin tuyển dụng',
        'send_candidate_email'=> 'Gửi email ứng viên',
        'ta_review'           => 'TA Review CV',
    ];
    foreach ($labels as $k => $v) {
        if (str_contains($action, $k)) {
            // For candidate_import, show summary from detail instead of full detail
            if ($k === 'candidate_import') {
                preg_match('/ins=(\d+).*upd=(\d+).*skip=(\d+)/', $detail, $m);
                if ($m) return $v . ': <em>' . (int)$m[1] . ' thêm mới, ' . (int)$m[2] . ' cập nhật, ' . (int)$m[3] . ' bỏ qua</em>';
            }
            return $v . ($detail ? ': <em>' . htmlspecialchars($detail) . '</em>' : '');
        }
    }
    return htmlspecialchars($action) . ($detail ? ': <em>' . htmlspecialchars($detail) . '</em>' : '');
}

hrm_header('Tổng quan', 'Dashboard điều hành HRM - ' . $periodLabel, 'overview');
?>
<style>
/* ── Dashboard shell ── */
.dsh-wrap{display:grid;gap:18px;padding-bottom:32px}

/* ── Toolbar ── */
.dsh-toolbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px}
.dsh-toolbar-left{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.dsh-refresh-info{font-size:11.5px;color:#94a3b8;display:flex;align-items:center;gap:5px}
.dsh-spin{display:inline-block;width:11px;height:11px;border:2px solid #e2e8f0;border-top-color:#94a3b8;border-radius:50%;animation:dshSpin .6s linear infinite;opacity:0;transition:.2s}
.dsh-spin.active{opacity:1}
@keyframes dshSpin{to{transform:rotate(360deg)}}

/* ── Stat Cards ── */
.dsh-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px}
@media(max-width:1100px){.dsh-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.dsh-stats{grid-template-columns:repeat(2,1fr)}}
.dsh-stat{background:#fff;border:1px solid #e8ecf0;border-radius:14px;padding:18px 18px 14px;position:relative;overflow:hidden;transition:.18s;text-decoration:none;display:block;cursor:pointer}
.dsh-stat:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:#d1dae3}
.dsh-stat-bar{position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0}
.dsh-stat-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.dsh-stat-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.dsh-stat-val{font-size:30px;font-weight:800;line-height:1;margin-bottom:4px;font-variant-numeric:tabular-nums;transition:.3s}
.dsh-stat-label{font-size:11.5px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
.dsh-stat-sub{font-size:11px;color:#94a3b8;margin-top:3px}

/* ── Two-column grid ── */
.dsh-row2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.dsh-row3{display:grid;grid-template-columns:3fr 2fr;gap:18px}
@media(max-width:900px){.dsh-row2,.dsh-row3{grid-template-columns:1fr}}

/* ── Section cards ── */
.dsh-card{background:#fff;border:1px solid #e8ecf0;border-radius:14px;overflow:hidden}
.dsh-card-hd{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f1f5f9}
.dsh-card-hd h3{font-size:13.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;margin:0}
.dsh-card-hd h3 svg{width:15px;height:15px;flex-shrink:0}
.dsh-card-hd a{font-size:11.5px;color:#0071e3;text-decoration:none;font-weight:600}
.dsh-card-hd a:hover{text-decoration:underline}
.dsh-card-body{padding:16px 20px}

/* ── Funnel ── */
.dsh-funnel-row{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.dsh-funnel-row:last-child{margin-bottom:0}
.dsh-funnel-lbl{width:140px;font-size:12.5px;color:#475569;flex-shrink:0;font-weight:500}
.dsh-funnel-track{flex:1;background:#f1f5f9;border-radius:8px;overflow:hidden;height:28px}
.dsh-funnel-bar{height:100%;display:flex;align-items:center;padding:0 10px;border-radius:8px;min-width:36px;transition:width .5s cubic-bezier(.4,0,.2,1)}
.dsh-funnel-bar span{color:#fff;font-size:12px;font-weight:700;white-space:nowrap}
.dsh-funnel-conv{font-size:11px;color:#94a3b8;width:56px;text-align:right;flex-shrink:0}

/* ── KPI pills ── */
.dsh-kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dsh-kpi-pill{background:#f8fafc;border:1px solid #e8ecf0;border-radius:10px;padding:12px 14px}
.dsh-kpi-pill-val{font-size:22px;font-weight:800;line-height:1;margin-bottom:2px}
.dsh-kpi-pill-lbl{font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.dsh-kpi-pill-tgt{font-size:10.5px;color:#94a3b8;margin-top:2px}

/* ── Alerts ── */
.dsh-alert-list{display:flex;flex-direction:column;gap:0}
.dsh-alert-item{display:flex;align-items:flex-start;gap:10px;padding:11px 0;border-bottom:1px solid #f8fafc;text-decoration:none;transition:.12s;border-radius:4px}
.dsh-alert-item:last-child{border-bottom:none}
.dsh-alert-item:hover{background:#fafbfc;padding-left:4px}
.dsh-alert-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px}
.dsh-alert-body{flex:1;min-width:0}
.dsh-alert-type{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.dsh-alert-text{font-size:12.5px;color:#0f172a;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dsh-alert-time{font-size:11px;color:#94a3b8;margin-top:2px}
.dsh-no-alerts{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:28px 0;gap:8px;color:#94a3b8;font-size:13px}
.dsh-no-alerts svg{width:36px;height:36px;opacity:.4}

/* ── Activity feed ── */
.dsh-act-list{display:flex;flex-direction:column;gap:0}
.dsh-act-item{display:flex;align-items:flex-start;gap:11px;padding:10px 0;border-bottom:1px solid #f8fafc}
.dsh-act-item:last-child{border-bottom:none}
.dsh-act-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.dsh-act-ic svg{width:14px;height:14px}
.dsh-act-body{flex:1;min-width:0}
.dsh-act-line{font-size:12.5px;color:#0f172a;line-height:1.4}
.dsh-act-line em{color:#475569;font-style:normal}
.dsh-act-meta{font-size:11px;color:#94a3b8;margin-top:2px}

/* ── Dept table ── */
.dsh-dept-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.dsh-dept-tbl th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#94a3b8;padding:8px 12px;text-align:right;border-bottom:2px solid #f1f5f9}
.dsh-dept-tbl th:first-child{text-align:left}
.dsh-dept-tbl td{padding:9px 12px;border-bottom:1px solid #f8fafc;color:#0f172a;text-align:right;vertical-align:middle}
.dsh-dept-tbl td:first-child{text-align:left;font-weight:500;color:#334155;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dsh-dept-tbl tr:last-child td{border-bottom:none}
.dsh-dept-tbl tr:hover td{background:#fafbfc}
.dsh-num-badge{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:22px;padding:0 7px;border-radius:99px;font-size:12px;font-weight:700}

/* ── Approval table ── */
.dsh-approval-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.dsh-approval-tbl th{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:#94a3b8;padding:8px 12px;text-align:left;border-bottom:2px solid #f1f5f9;white-space:nowrap}
.dsh-approval-tbl td{padding:10px 12px;border-bottom:1px solid #f8fafc;color:#0f172a;vertical-align:middle}
.dsh-approval-tbl tr:last-child td{border-bottom:none}
.dsh-approval-tbl tr:hover td{background:#fafbfc}
.dsh-overdue{color:#dc2626;font-weight:700}
.dsh-empty{text-align:center;padding:28px;color:#94a3b8;font-size:13px}
</style>

<div class="dsh-wrap">

<!-- ── TOOLBAR ── -->
<div class="dsh-toolbar">
    <div class="dsh-toolbar-left">
        <div class="rc-tabs">
            <?php foreach (['month'=>'Tháng này','quarter'=>'Quý này','all'=>'Toàn bộ'] as $k=>$v): ?>
                <a href="/hrm/dashboard?period=<?= $k ?>" class="rc-tab <?= $preset===$k?'active':'' ?>"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
        <div class="dsh-refresh-info">
            <span class="dsh-spin" id="dshSpin"></span>
            <span id="dshUpdated">Cập nhật lúc <?= date('H:i') ?></span>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/hrm/kpi?period=<?= h($preset) ?>" class="rc-btn ghost">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            KPI chi tiết
        </a>
        <a href="/hrm/requests?new=1" class="rc-btn">+ Tạo HRF</a>
    </div>
</div>

<!-- ── STAT CARDS ── -->
<div class="dsh-stats" id="dshStats">
    <?php
    $statCards = [
        ['id'=>'hrf_pending',   'label'=>'HRF chờ duyệt',       'val'=>$counts['hrf_pending'],   'sub'=>'Yêu cầu tuyển dụng',    'color'=>'#f59e0b', 'bg'=>'#fffbeb', 'href'=>'/hrm/plan',       'icon'=>'<path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/><path d="M9 4h6"/><path d="m9 13 2 2 4-4"/>'],
        ['id'=>'jobs_open',     'label'=>'Vị trí đang mở',      'val'=>$counts['jobs_open'],     'sub'=>'Đang tuyển dụng',       'color'=>'#2563eb', 'bg'=>'#eff6ff', 'href'=>'/hrm/recruitment','icon'=>'<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
        ['id'=>'apps_active',   'label'=>'Ứng viên đang xử lý', 'val'=>$counts['apps_active'],   'sub'=>'Trong pipeline',        'color'=>'#0e9f6e', 'bg'=>'#ecfdf5', 'href'=>'/hrm/candidates', 'icon'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="18" cy="9" r="3"/><path d="m21.5 21-1.5-1.5"/>'],
        ['id'=>'offers_out',    'label'=>'Offer đang chờ',      'val'=>$counts['offers_out'],    'sub'=>'Chờ phản hồi',          'color'=>'#7c3aed', 'bg'=>'#f5f3ff', 'href'=>'/hrm/recruitment','icon'=>'<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'],
        ['id'=>'onb_active',    'label'=>'Đang onboarding',     'val'=>$counts['onb_active'],    'sub'=>'Hội nhập 60 ngày',      'color'=>'#0284c7', 'bg'=>'#e0f2fe', 'href'=>'/hrm/onboarding', 'icon'=>'<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
        ['id'=>'probation_due', 'label'=>'Sắp hạn thử việc',   'val'=>$counts['probation_due'], 'sub'=>'Trong 14 ngày tới',     'color'=>'#dc2626', 'bg'=>'#fef2f2', 'href'=>'/hrm/probation',  'icon'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    ];
    foreach ($statCards as $sc): ?>
    <a href="<?= h($sc['href']) ?>" class="dsh-stat" data-stat="<?= $sc['id'] ?>">
        <div class="dsh-stat-bar" style="background:<?= $sc['color'] ?>"></div>
        <div class="dsh-stat-icon" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>">
            <svg viewBox="0 0 24 24"><?= $sc['icon'] ?></svg>
        </div>
        <div class="dsh-stat-val" style="color:<?= $sc['color'] ?>" id="stat_<?= $sc['id'] ?>"><?= (int)$sc['val'] ?></div>
        <div class="dsh-stat-label"><?= h($sc['label']) ?></div>
        <div class="dsh-stat-sub"><?= h($sc['sub']) ?></div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── ROW: FUNNEL + KPI ── -->
<div class="dsh-row3">
    <div class="dsh-card">
        <div class="dsh-card-hd">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="#0e9f6e" stroke-width="2" stroke-linecap="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Phễu tuyển dụng
                <span style="font-size:11.5px;color:#94a3b8;font-weight:400">(<?= h($periodLabel) ?>)</span>
            </h3>
            <a href="/hrm/kpi?period=<?= h($preset) ?>">Xem KPI →</a>
        </div>
        <div class="dsh-card-body">
            <?php
            $funnel = [
                ['Vị trí đang tuyển', $m['funnel']['open'],        '#0e6b5c', null],
                ['CV nhận',           $m['funnel']['cv'],          '#2563eb', null],
                ['Đã phỏng vấn',      $m['funnel']['interviewed'], '#7c3aed', $m['funnel']['cv']    > 0 ? round($m['funnel']['interviewed'] * 100 / $m['funnel']['cv'])    . '%' : '—'],
                ['Offer',             $m['funnel']['offers'],      '#f59e0b', $m['funnel']['interviewed'] > 0 ? round($m['funnel']['offers'] * 100 / $m['funnel']['interviewed']) . '%' : '—'],
                ['Đã nhận việc',      $m['funnel']['joined'],      '#16a34a', $m['funnel']['offers'] > 0 ? round($m['funnel']['joined'] * 100 / $m['funnel']['offers'])     . '%' : '—'],
            ];
            $max = max(1, ...array_column($funnel, 1));
            foreach ($funnel as [$lbl, $val, $clr, $conv]): 
                $w = max(4, round($val * 100 / $max));
            ?>
            <div class="dsh-funnel-row">
                <div class="dsh-funnel-lbl"><?= h($lbl) ?></div>
                <div class="dsh-funnel-track">
                    <div class="dsh-funnel-bar" style="width:<?= $w ?>%;background:<?= $clr ?>">
                        <span><?= (int)$val ?></span>
                    </div>
                </div>
                <div class="dsh-funnel-conv"><?= $conv ?? '' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- KPI pills -->
    <div class="dsh-card">
        <div class="dsh-card-hd">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                KPI tuyển dụng
            </h3>
        </div>
        <div class="dsh-card-body">
            <div class="dsh-kpi-grid">
                <?php foreach ($m['kpi'] as $kpi):
                    $c = $kpi['ok'] === null ? '#64748b' : ($kpi['ok'] ? '#16a34a' : '#dc2626');
                    $bg = $kpi['ok'] === null ? '#f8fafc' : ($kpi['ok'] ? '#f0fdf4' : '#fef2f2');
                    $border = $kpi['ok'] === null ? '#e8ecf0' : ($kpi['ok'] ? '#bbf7d0' : '#fecaca');
                ?>
                <div class="dsh-kpi-pill" style="background:<?= $bg ?>;border-color:<?= $border ?>">
                    <div class="dsh-kpi-pill-val" style="color:<?= $c ?>"><?= $kpi['value'] === null ? '—' : h($kpi['value'] . $kpi['unit']) ?></div>
                    <div class="dsh-kpi-pill-lbl"><?= h($kpi['label']) ?></div>
                    <div class="dsh-kpi-pill-tgt">Mục tiêu: <?= h($kpi['target']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── ROW: ALERTS + ACTIVITY ── -->
<div class="dsh-row2">
    <!-- Alerts -->
    <div class="dsh-card">
        <div class="dsh-card-hd">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="<?= count($alerts) > 0 ? '#dc2626' : '#64748b' ?>" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Cảnh báo cần xử lý
                <?php if (count($alerts) > 0): ?>
                    <span style="background:#fef2f2;color:#dc2626;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;border:1px solid #fecaca"><?= count($alerts) ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="dsh-card-body" style="padding-top:8px;padding-bottom:8px;max-height:320px;overflow-y:auto">
            <?php if (!$alerts): ?>
            <div class="dsh-no-alerts">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Không có cảnh báo. Mọi thứ đang ổn ✓
            </div>
            <?php else: ?>
            <div class="dsh-alert-list">
                <?php foreach ($alerts as $al): ?>
                <a href="<?= h($al['link']) ?>" class="dsh-alert-item">
                    <div class="dsh-alert-dot" style="background:<?= h($al['color']) ?>"></div>
                    <div class="dsh-alert-body">
                        <div class="dsh-alert-type" style="color:<?= h($al['color']) ?>"><?= h($al['type']) ?></div>
                        <div class="dsh-alert-text"><?= h($al['text']) ?></div>
                        <?php if ($al['due']): ?>
                        <div class="dsh-alert-time">Hạn: <?= date('d/m H:i', strtotime($al['due'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#cbd5e1" stroke-width="2" style="flex-shrink:0;margin-top:3px"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity feed -->
    <div class="dsh-card">
        <div class="dsh-card-hd">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Hoạt động gần đây
            </h3>
        </div>
        <div class="dsh-card-body" style="padding-top:8px;padding-bottom:8px;max-height:320px;overflow-y:auto">
            <?php if (!$activity): ?>
            <div class="dsh-empty">Chưa có hoạt động nào.</div>
            <?php else: ?>
            <div class="dsh-act-list">
                <?php foreach ($activity as $act): ?>
                <div class="dsh-act-item">
                    <?= hrm_act_icon($act['action']) ?>
                    <div class="dsh-act-body">
                        <div class="dsh-act-line"><?= hrm_act_label($act['action'], $act['detail'] ?? '') ?></div>
                        <div class="dsh-act-meta">
                            <?= h($act['full_name'] ?: 'Hệ thống') ?> · <?= hrm_rel_time($act['created_at']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── ROW: DEPT WORKLOAD + HRF APPROVAL ── -->
<div class="dsh-row2">
    <!-- Dept workload -->
    <div class="dsh-card">
        <div class="dsh-card-hd">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Tuyển dụng theo phòng ban
            </h3>
        </div>
        <div class="dsh-card-body" style="padding:0">
            <?php if (!$depts): ?>
            <div class="dsh-empty">Chưa có dữ liệu phòng ban.</div>
            <?php else: ?>
            <table class="dsh-dept-tbl">
                <thead><tr>
                    <th>Phòng ban</th>
                    <th>Vị trí mở</th>
                    <th>Ứng viên XL</th>
                    <th>Tổng</th>
                </tr></thead>
                <tbody>
                <?php foreach (array_slice($depts, 0, 10) as $dname => $dc):
                    $total = $dc['open'] + $dc['active'];
                ?>
                <tr>
                    <td title="<?= h($dname) ?>"><?= h($dname) ?></td>
                    <td><span class="dsh-num-badge" style="background:#eff6ff;color:#2563eb"><?= (int)$dc['open'] ?></span></td>
                    <td><span class="dsh-num-badge" style="background:#f0fdf4;color:#16a34a"><?= (int)$dc['active'] ?></span></td>
                    <td><strong><?= $total ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- HRF waiting for me -->
    <div class="dsh-card">
        <div class="dsh-card-hd">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                HRF chờ tôi duyệt
                <?php if ($waiting): ?>
                    <span style="background:#fff7ed;color:#f59e0b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;border:1px solid #fed7aa"><?= count($waiting) ?></span>
                <?php endif; ?>
            </h3>
            <a href="/hrm/plan">Xem tất cả →</a>
        </div>
        <div class="dsh-card-body" style="padding:0">
            <?php if (!$waiting): ?>
            <div class="dsh-empty">Không có HRF nào chờ bạn duyệt.</div>
            <?php else: ?>
            <table class="dsh-approval-tbl">
                <thead><tr>
                    <th>Mã</th><th>Vị trí</th><th>Bộ phận</th><th>Hạn duyệt</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach (array_slice($waiting, 0, 8) as $r):
                    $overdue = $r['_due'] && strtotime($r['_due']) < time();
                ?>
                <tr>
                    <td><b><?= h($r['code']) ?></b></td>
                    <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['title']) ?></td>
                    <td><?= h($r['dept_name'] ?: '—') ?></td>
                    <td class="<?= $overdue ? 'dsh-overdue' : '' ?>"><?= $r['_due'] ? date('d/m H:i', strtotime($r['_due'])) : '—' ?><?= $overdue ? ' ⚠' : '' ?></td>
                    <td><a href="/hrm/requests?id=<?= $r['id'] ?>" class="rc-btn" style="padding:4px 12px;font-size:12px">Duyệt</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- .dsh-wrap -->

<script>
(function() {
    /* ── Auto-refresh stat cards every 60s ── */
    var INTERVAL = 60000;
    var spin = document.getElementById('dshSpin');
    var updEl = document.getElementById('dshUpdated');

    function pad(n) { return n < 10 ? '0' + n : n; }
    function nowStr() { var d = new Date(); return 'Cập nhật lúc ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }

    function refreshStats() {
        spin.classList.add('active');
        var fd = new FormData();
        fd.append('action', 'dashboard_counts');
        fetch('/hrm/api', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                var map = d.counts || {};
                Object.keys(map).forEach(function(k) {
                    var el = document.getElementById('stat_' + k);
                    if (el) {
                        var nv = map[k];
                        if (parseInt(el.textContent) !== nv) {
                            el.style.transform = 'scale(1.2)';
                            el.textContent = nv;
                            setTimeout(function() { el.style.transform = ''; }, 300);
                        }
                    }
                });
                updEl.textContent = nowStr();
            })
            .catch(function() {})
            .finally(function() { spin.classList.remove('active'); });
    }

    setInterval(refreshStats, INTERVAL);

    /* ── Animate funnel bars on load ── */
    document.querySelectorAll('.dsh-funnel-bar').forEach(function(bar) {
        var w = bar.style.width;
        bar.style.width = '0';
        setTimeout(function() { bar.style.width = w; }, 100);
    });

    /* ── Animate stat values counting up ── */
    document.querySelectorAll('.dsh-stat-val').forEach(function(el) {
        var target = parseInt(el.textContent, 10);
        if (isNaN(target) || target === 0) return;
        var start = 0, dur = 600, step = Math.ceil(target / (dur / 16));
        el.textContent = '0';
        var t = setInterval(function() {
            start = Math.min(start + step, target);
            el.textContent = start;
            if (start >= target) clearInterval(t);
        }, 16);
    });
})();
</script>
<?php
hrm_footer();
