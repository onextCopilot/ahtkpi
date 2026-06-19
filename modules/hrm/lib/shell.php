<?php
/**
 * App-shell wrapper for HRM pages - reuses the global sidebar + topbar so the
 * recruitment module looks like the rest of AHT KPI.
 *
 *   hrm_header('Yêu cầu tuyển dụng', 'Quản lý HRF & phê duyệt');
 *   ... page content ...
 *   hrm_footer();
 */

function hrm_header(string $title, string $subtitle = '', ?string $nav = null): void
{
    global $conn;                       // sidebar.php + topbar.php read the global $conn
    $full_name = $_SESSION['full_name'] ?? '';
    ?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> · Tuyển dụng</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--rc:#0a252a;--rc2:#0e6b5c;--bd:#e2e8f0;--mut:#64748b}
        .rc-wrap{max-width:none}
        .rc-toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap}
        .rc-tabs{display:flex;gap:6px;flex-wrap:wrap}
        .rc-tab{font-size:13px;font-weight:600;color:var(--mut);text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--bd);background:#fff}
        .rc-tab.active{background:var(--rc);color:#fff;border-color:var(--rc)}
        .rc-btn{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;padding:9px 16px;border-radius:8px;border:1px solid var(--rc);background:var(--rc);color:#fff}
        .rc-btn.ghost{background:#fff;color:#334155;border-color:var(--bd)}
        .rc-btn.danger{background:#dc2626;border-color:#dc2626}
        .rc-card{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .rc-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--bd);border-radius:12px;overflow:hidden}
        .rc-table th{background:#f8fafc;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--mut);padding:11px 14px;border-bottom:1px solid var(--bd)}
        .rc-table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#0f172a}
        .rc-table tr:last-child td{border-bottom:none}
        .rc-table tr:hover td{background:#fafcff}
        .rc-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px}
        .rc-b-draft{background:#f1f5f9;color:#475569}.rc-b-pending{background:#fffbeb;color:#b45309}
        .rc-b-approved{background:#f0fdf4;color:#16a34a}.rc-b-rejected{background:#fef2f2;color:#dc2626}
        .rc-b-cancelled{background:#f1f5f9;color:#94a3b8}
        .rc-field{margin-bottom:14px}.rc-field label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:5px}
        .rc-field input,.rc-field select,.rc-field textarea{width:100%;padding:9px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px;font-family:inherit;outline:none}
        .rc-field input:focus,.rc-field select:focus,.rc-field textarea:focus{border-color:var(--rc2)}
        .rc-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
        .rc-empty{text-align:center;color:#94a3b8;padding:48px;background:#fff;border:1px solid var(--bd);border-radius:12px}
        .rc-step{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9}.rc-step:last-child{border:none}
        .rc-step-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;background:#f1f5f9;color:#64748b}
        .rc-step-dot.ok{background:#16a34a;color:#fff}.rc-step-dot.no{background:#dc2626;color:#fff}.rc-step-dot.cur{background:#b45309;color:#fff}
        .rc-muted{color:var(--mut);font-size:12px}
        .rc-rich{font-size:13.5px;color:#0f172a;line-height:1.6}
        .rc-rich h2{font-size:16px;margin:8px 0 4px}.rc-rich h3{font-size:14px;margin:8px 0 4px}
        .rc-rich ul,.rc-rich ol{margin:4px 0 4px 22px}.rc-rich p{margin:4px 0}
        .rc-rich a{color:var(--rc2)}
        /* Secondary in-page sidebar (modern) */
        .rc-layout{display:flex;gap:26px;align-items:flex-start}
        .rc-main{flex:1;min-width:0}
        .rc-sub{width:236px;flex:0 0 236px;position:sticky;top:16px;
            background:#fff;border:1px solid #eef1f5;border-radius:10px;padding:16px 12px;
            box-shadow:0 6px 24px -12px rgba(10,37,42,.18),0 1px 2px rgba(0,0,0,.03)}
        .rc-sub-head{display:flex;align-items:center;gap:12px;padding:4px 8px 14px;border-bottom:1px solid #f1f5f9;margin-bottom:12px}
        .rc-sub-logo{width:40px;height:40px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#0c3138,#0a252a);box-shadow:0 6px 16px -4px rgba(10,37,42,.45)}
        .rc-sub-logo svg{width:21px;height:21px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .rc-sub-head b{font-size:15px;color:#0f172a;display:block;line-height:1.2}
        .rc-sub-head small{font-size:11px;color:#94a3b8}
        .rc-sub-back{display:flex;align-items:center;gap:8px;padding:8px 10px;margin-bottom:10px;border-radius:7px;
            font-size:12.5px;font-weight:600;color:#64748b;text-decoration:none;background:#f8fafc;transition:.15s}
        .rc-sub-back:hover{background:#f1f5f9;color:#0f172a}
        .rc-sub-back svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .rc-sub-label{font-size:10.5px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:#b4bcc8;padding:0 10px;margin:4px 0 8px}
        .rc-sub-nav a{position:relative;display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:7px;
            font-size:13.5px;font-weight:500;color:#475569;text-decoration:none;margin-bottom:3px;transition:.16s}
        .rc-sub-nav a:hover{background:#f4f6f9;color:#0f172a}
        .rc-sub-nav a svg{width:18px;height:18px;flex-shrink:0;fill:none;stroke:#94a3b8;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:.16s}
        .rc-sub-nav a:hover svg{stroke:#0f172a}
        .rc-sub-nav a.active{background:linear-gradient(135deg,#0c3138,#0a252a);color:#fff;font-weight:600;
            box-shadow:0 8px 20px -6px rgba(10,37,42,.5)}
        .rc-sub-nav a.active svg{stroke:#fff}
        @media(max-width:860px){.rc-layout{flex-direction:column}.rc-sub{width:100%;flex:none;position:static}}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = $title;
            $page_subtitle = $subtitle;
            include __DIR__ . '/../../includes/topbar.php';
            ?>
            <div class="rc-wrap" style="padding:22px 2rem 48px">
<?php
    if ($nav !== null) {
        $GLOBALS['hrm_has_subnav'] = true;
        echo '<div class="rc-layout">';
        hrm_subnav($nav, ($_SESSION['role'] ?? '') === 'admin');
        echo '<div class="rc-main">';
    }
}

function hrm_footer(): void
{
    if (!empty($GLOBALS['hrm_has_subnav'])) {
        echo '</div></div>';            // close .rc-main + .rc-layout
        $GLOBALS['hrm_has_subnav'] = false;
    }
    ?>
            </div>
        </main>
    </div>
    <!-- Global "submitting" indicator and Toast: any POST to /hrm/api shows a busy banner. -->
    <script>
    function showToast(msg, type = 'success') {
        let t = document.getElementById('rc-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'rc-toast';
            t.style.cssText = 'position:fixed;top:24px;right:24px;padding:12px 20px;border-radius:8px;z-index:9999;box-shadow:0 8px 20px rgba(0,0,0,0.12);font-size:14px;opacity:0;transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);transform:translateY(-10px);pointer-events:none;line-height:1.4;display:flex;align-items:flex-start;gap:12px;max-width:380px;font-weight:500;';
            document.body.appendChild(t);
        }
        
        let bg = '#16a34a', icon = '✓'; // default success
        if (type === 'error') { bg = '#dc2626'; icon = '✕'; }
        else if (type === 'warning') { bg = '#d97706'; icon = '!'; }
        
        t.style.background = bg;
        t.style.color = '#fff';
        t.innerHTML = `<div style="margin-top:2px;display:flex;align-items:center;justify-content:center;width:18px;height:18px;background:rgba(255,255,255,0.25);border-radius:50%;font-size:11px;flex-shrink:0">${icon}</div><div style="white-space:pre-wrap">${msg}</div>`;
        t.style.opacity = '1';
        t.style.transform = 'translateY(0)';
        setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateY(-10px)'; }, 4500);
    }
    window.addEventListener('DOMContentLoaded', () => {
        const data = localStorage.getItem('job_toast');
        if (data) { 
            try {
                const parsed = JSON.parse(data);
                showToast(parsed.msg, parsed.type);
            } catch(e) {
                showToast(data); // fallback for old plain string
            }
            localStorage.removeItem('job_toast'); 
        }
    });

    (function(){
        var inFlight = 0, bar = null;
        function show(){
            if(!bar){
                var st=document.createElement('style');
                st.textContent='@keyframes rcspin{to{transform:rotate(360deg)}}';
                document.head.appendChild(st);
                bar=document.createElement('div');
                bar.style.cssText='position:fixed;top:16px;left:50%;transform:translateX(-50%);background:#0a252a;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 8px 24px rgba(0,0,0,.3);display:flex;align-items:center;gap:9px';
                bar.innerHTML='<span style="width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;display:inline-block;animation:rcspin .7s linear infinite"></span><span>Đang xử lý...</span>';
                document.body.appendChild(bar);
            }
            bar.style.display='flex';
        }
        function hide(){ if(bar) bar.style.display='none'; }
        var orig = window.fetch;
        window.fetch = function(u, o){
            var api = (typeof u==='string') && u.indexOf('/hrm/api')!==-1 && o && String(o.method||'').toUpperCase()==='POST';
            if(api){ inFlight++; show(); }
            var p = orig.apply(this, arguments);
            if(api){ var done=function(){ inFlight=Math.max(0,inFlight-1); if(inFlight===0) hide(); }; p.then(done, done); }
            return p;
        };
    })();
    </script>
</body>
</html>
<?php
}

/**
 * Secondary in-page sidebar. Two contexts:
 *  - recruitment keys: overview | requests | jobs
 *  - settings keys:    offices | roles | email | channels   (HRM-wide settings)
 */
function hrm_subnav(string $active, bool $isAdmin): void
{
    $settingsKeys = ['offices', 'pipeline', 'owners', 'roles', 'email', 'channels'];
    $isSettings = in_array($active, $settingsKeys, true);
    $simple = [
        'onboarding' => [
            '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
            'Onboarding', 'Hội nhập 60 ngày',
            [['onboarding', 'Nhân sự mới', '/hrm/onboarding', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 11 2 2 4-4"/>']],
        ],
        'probation' => [
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 2 2 4-4"/>',
            'Đánh giá thử việc', 'Probation review',
            [['probation', 'Danh sách', '/hrm/probation', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/>']],
        ],
        'kpi' => [
            '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'KPI & Báo cáo', 'Recruitment KPI',
            [['kpi', 'Tổng quan KPI', '/hrm/kpi', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>']],
        ],
    ];

    if (isset($simple[$active])) {
        [$logo, $title, $subtitle, $items] = $simple[$active];
        echo '<nav class="rc-sub">';
        echo '<div class="rc-sub-head"><div class="rc-sub-logo"><svg viewBox="0 0 24 24">' . $logo . '</svg></div>'
            . '<div><b>' . h($title) . '</b><small>' . h($subtitle) . '</small></div></div>';
        echo '<a class="rc-sub-back" href="/hrm"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Tất cả ứng dụng</a>';
        echo '<div class="rc-sub-label">Điều hướng</div><div class="rc-sub-nav">';
        foreach ($items as $it) {
            $cls = $it[0] === $active ? ' class="active"' : '';
            echo '<a href="' . $it[2] . '"' . $cls . '><svg viewBox="0 0 24 24">' . $it[3] . '</svg>' . h($it[1]) . '</a>';
        }
        echo '</div></nav>';
        return;
    }

    if ($isSettings) {
        $logo = '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>';
        $title = 'Cấu hình'; $subtitle = 'HRM Settings';
        $items = [
            ['offices', 'Văn phòng', '/hrm/settings?tab=offices', '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V10l-6-3"/><path d="M9 9h.01M9 13h.01M9 17h.01"/>'],
            ['pipeline', 'Giai đoạn & SLA', '/hrm/settings?tab=pipeline', '<line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>'],
            ['owners', 'Phụ trách giai đoạn', '/hrm/settings?tab=owners', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['roles', 'Vai trò tuyển dụng', '/hrm/settings?tab=roles', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>'],
            ['email', 'Email template', '/hrm/settings?tab=email', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/>'],
            ['channels', 'Kênh thông báo', '/hrm/settings?tab=channels', '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
        ];
    } else {
        $logo = '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>';
        $title = 'Tuyển dụng'; $subtitle = 'E-Hiring';
        $items = [
            ['overview', 'Tổng quan', '/hrm/recruitment', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
            ['requests', 'Yêu cầu tuyển dụng', '/hrm/requests', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
            ['jobs', 'Tin tuyển dụng', '/hrm/jobs', '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
            ['candidates', 'Ứng viên', '/hrm/candidates', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>'],
        ];
    }

    echo '<nav class="rc-sub">';
    echo '<div class="rc-sub-head"><div class="rc-sub-logo"><svg viewBox="0 0 24 24">' . $logo . '</svg></div>'
        . '<div><b>' . h($title) . '</b><small>' . h($subtitle) . '</small></div></div>';
    echo '<a class="rc-sub-back" href="/hrm"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Tất cả ứng dụng</a>';
    echo '<div class="rc-sub-label">' . ($isSettings ? 'Thiết lập' : 'Điều hướng') . '</div>';
    echo '<div class="rc-sub-nav">';
    foreach ($items as $it) {
        $cls = $it[0] === $active ? ' class="active"' : '';
        echo '<a href="' . $it[2] . '"' . $cls . '><svg viewBox="0 0 24 24">' . $it[3] . '</svg>' . h($it[1]) . '</a>';
    }
    echo '</div></nav>';
}

/** Render a status badge. */
function hrm_badge(string $status): string
{
    $labels = ['draft' => 'Nháp', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy'];
    $label = $labels[$status] ?? $status;
    return '<span class="rc-badge rc-b-' . h($status) . '">' . h($label) . '</span>';
}
