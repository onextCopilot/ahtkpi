<?php
/**
 * HRM launcher - landing page for /hrm. An immersive Base.vn-style home: the
 * whole content column is a teal gradient, with greeting + live clock + Hanoi
 * weather and a grid of glass app tiles. Recruitment is live; later SOP phases
 * appear here as they ship.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$isAdmin   = ($_SESSION['role'] ?? '') === 'admin';
$full_name = $_SESSION['full_name'] ?? '';

$pendingHrf = (int)($conn->query("SELECT COUNT(*) c FROM hrm_requests WHERE status='pending'")->fetch_assoc()['c'] ?? 0);

$h = (int)date('G');
$greet = $h < 11 ? 'Chào buổi sáng' : ($h < 13 ? 'Chào buổi trưa' : ($h < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
$session = $h < 11 ? 'SÁNG' : ($h < 13 ? 'TRƯA' : ($h < 18 ? 'CHIỀU' : 'TỐI'));
$weekdays = ['CHỦ NHẬT', 'THỨ 2', 'THỨ 3', 'THỨ 4', 'THỨ 5', 'THỨ 6', 'THỨ 7'];
$dateLine = 'HÀ NỘI, ' . $session . ' ' . $weekdays[(int)date('w')] . ', ' . date('d/m/Y');

// tile: [label, subtitle, href|null, gradient, svg-path, badge|null]
$tiles = [
    ['Tổng quan', 'Dashboard điều hành', '/hrm/dashboard', 'linear-gradient(135deg,#0ea5e9,#0369a1)',
        '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        null],
    ['Kế hoạch tuyển dụng', 'Recruitment Plan', '/hrm/plan', 'linear-gradient(135deg,#6366f1,#4338ca)',
        '<path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/><path d="M9 4h6"/><path d="m9 13 2 2 4-4"/>',
        $pendingHrf ?: null],
    ['Tuyển dụng', 'E-Hiring', '/hrm/recruitment', 'linear-gradient(135deg,#0e9f6e,#057a55)',
        '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        null],
    ['Ứng viên', 'Kho ứng viên', '/hrm/candidates', 'linear-gradient(135deg,#06b6d4,#0e7490)',
        '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="18" cy="9" r="3"/><path d="m21.5 21-1.5-1.5"/>',
        null],
    ['Onboarding', 'Hội nhập 60 ngày', '/hrm/onboarding', 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
        '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>', null],
    ['Đánh giá thử việc', 'Probation review', '/hrm/probation', 'linear-gradient(135deg,#f59e0b,#b45309)',
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/>', null],
    ['KPI & Báo cáo', 'Recruitment KPI', '/hrm/kpi', 'linear-gradient(135deg,#a855f7,#7c3aed)',
        '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>', null],
];
if ($isAdmin) {
    $tiles[] = ['Cấu hình', 'Vai trò · Email · Kênh', '/hrm/settings', 'linear-gradient(135deg,#64748b,#475569)',
        '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>', null];
}

hrm_header('HRM', 'Hệ thống quản trị nhân sự AHT');
?>
<style>
/* Immersive teal column (scoped to the launcher only). */
.main-content{background:
    radial-gradient(1100px 500px at 88% -10%,rgba(16,159,110,.35),transparent 60%),
    radial-gradient(900px 600px at 5% 110%,rgba(37,99,235,.18),transparent 55%),
    linear-gradient(135deg,#06343a 0%,#0a252a 55%,#062b30 100%) !important;
    min-height:100vh}
/* launcher head spans full width; flex column so the footer sits at the bottom */
.rc-wrap{max-width:none;display:flex;flex-direction:column;min-height:calc(100vh - 96px)}
/* Topbar blends into the gradient; title moved to the page footer. */
.main-content .top-bar{background:transparent !important;border:none !important;box-shadow:none !important;justify-content:flex-end !important}
.main-content .top-bar .page-title{display:none !important}
/* Name already shown in the hero greeting → hide the duplicate in the topbar. */
.main-content .top-bar .user-info{display:none !important}
.main-content .top-bar .notification-bell{color:rgba(255,255,255,.85) !important}
.main-content .top-bar .notification-bell:hover{background:rgba(255,255,255,.12) !important}

.hrm-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap;
    padding:4px 0 28px;margin-bottom:20px;border-bottom:1px solid rgba(255,255,255,.10);color:#fff}
.hrm-greet{font-size:13px;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,.6)}
.hrm-name{font-size:30px;font-weight:700;margin:2px 0 6px;line-height:1.1}
.hrm-sub{font-size:13px;color:rgba(255,255,255,.72);max-width:430px}
.hrm-right{display:flex;align-items:center;gap:26px}
.hrm-clock{font-size:48px;font-weight:300;font-variant-numeric:tabular-nums;letter-spacing:1px;line-height:1;color:#fff}
.hrm-clock small{font-size:20px;opacity:.55;font-weight:300}
.hrm-date{font-size:11.5px;letter-spacing:.6px;color:rgba(255,255,255,.6);margin-top:7px}
.hrm-wx{display:flex;align-items:center;gap:12px;padding-left:26px;border-left:1px solid rgba(255,255,255,.15);min-width:140px;color:#fff}
.hrm-wx-ic{font-size:42px;line-height:1}
.hrm-wx-temp{font-size:27px;font-weight:600}
.hrm-wx-meta{font-size:11px;color:rgba(255,255,255,.62);line-height:1.5}
.hrm-wx.loading{opacity:.4}

.hrm-sec{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.55);margin:0 0 18px;text-align:center}
/* Boxless: just a round icon + label, Base.vn-style. */
.hrm-grid{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
.hrm-tile{position:relative;display:flex;flex-direction:column;align-items:center;text-align:center;gap:13px;width:156px;padding:16px 8px;border-radius:16px;text-decoration:none;transition:.16s}
.hrm-tile.live:hover{background:rgba(255,255,255,.05)}
.hrm-tile.live:hover .hrm-ic{transform:translateY(-4px);box-shadow:0 16px 30px rgba(0,0,0,.45)}
.hrm-tile.soon{opacity:.42;cursor:default}
.hrm-ic-wrap{position:relative}
.hrm-ic{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(0,0,0,.34);transition:.16s}
.hrm-ic svg{width:35px;height:35px;fill:none;stroke:#fff;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.hrm-tl{font-size:14px;font-weight:700;color:#fff}
.hrm-st{font-size:11px;color:rgba(255,255,255,.55);margin-top:-7px}
.hrm-soon-tag{position:absolute;top:-2px;right:-12px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:rgba(255,255,255,.2);color:#fff;padding:2px 8px;border-radius:99px;backdrop-filter:blur(4px)}
.hrm-badge{position:absolute;top:-2px;right:-6px;min-width:23px;height:23px;padding:0 6px;border-radius:99px;background:#ef4444;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid rgba(6,52,58,.6);box-shadow:0 2px 8px rgba(239,68,68,.5)}
.hrm-foot{margin-top:auto;padding-top:26px;border-top:1px solid rgba(255,255,255,.08);
    display:flex;align-items:baseline;gap:10px;color:rgba(255,255,255,.45)}
.hrm-foot b{color:rgba(255,255,255,.75);font-size:15px;letter-spacing:.5px}
.hrm-foot span{font-size:12px}
@media(max-width:720px){.hrm-hero{flex-direction:column;align-items:flex-start}.hrm-clock{font-size:38px}}
</style>

<div class="hrm-hero">
    <div>
        <div class="hrm-greet"><?= h($greet) ?> 👋</div>
        <div class="hrm-name"><?= h($full_name) ?></div>
        <div class="hrm-sub">Chào mừng đến hệ thống HRM AHT. Chọn ứng dụng bên dưới để bắt đầu - module Tuyển dụng đã sẵn sàng.</div>
    </div>
    <div class="hrm-right">
        <div>
            <div class="hrm-clock" id="hrmClock">--:--<small>:--</small></div>
            <div class="hrm-date"><?= h($dateLine) ?></div>
        </div>
        <div class="hrm-wx loading" id="hrmWx">
            <div class="hrm-wx-ic" id="wxIc">⛅</div>
            <div>
                <div class="hrm-wx-temp" id="wxTemp">--°</div>
                <div class="hrm-wx-meta" id="wxMeta">Đang tải…</div>
            </div>
        </div>
    </div>
</div>

<div class="hrm-sec">Ứng dụng</div>
<div class="hrm-grid">
<?php foreach ($tiles as $t):
    [$label,$sub,$href,$grad,$svg,$badge] = $t;
    if ($href !== null): ?>
    <a href="<?= h($href) ?>" class="hrm-tile live">
        <div class="hrm-ic-wrap">
            <div class="hrm-ic" style="background:<?= $grad ?>"><svg viewBox="0 0 24 24"><?= $svg ?></svg></div>
            <?php if ($badge): ?><span class="hrm-badge"><?= (int)$badge ?></span><?php endif; ?>
        </div>
        <div class="hrm-tl"><?= h($label) ?></div>
        <div class="hrm-st"><?= h($sub) ?></div>
    </a>
    <?php else: ?>
    <div class="hrm-tile soon" title="Sắp ra mắt">
        <div class="hrm-ic-wrap">
            <div class="hrm-ic" style="background:<?= $grad ?>"><svg viewBox="0 0 24 24"><?= $svg ?></svg></div>
            <span class="hrm-soon-tag">Sắp có</span>
        </div>
        <div class="hrm-tl"><?= h($label) ?></div>
        <div class="hrm-st"><?= h($sub) ?></div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
</div>

<div class="hrm-foot">
    <b>HRM</b><span>Hệ thống quản trị nhân sự AHT</span>
</div>

<script>
(function(){
    var el=document.getElementById('hrmClock');
    function tick(){var n=new Date(),p=function(x){return x<10?'0'+x:x;};
        el.innerHTML=p(n.getHours())+':'+p(n.getMinutes())+'<small>:'+p(n.getSeconds())+'</small>';}
    tick(); setInterval(tick,1000);
})();
(function(){
    var ICON={0:'☀️',1:'🌤️',2:'⛅',3:'☁️',45:'🌫️',48:'🌫️',51:'🌦️',53:'🌦️',55:'🌧️',
        61:'🌧️',63:'🌧️',65:'🌧️',71:'🌨️',80:'🌦️',81:'🌧️',82:'⛈️',95:'⛈️',96:'⛈️',99:'⛈️'};
    var LABEL={0:'Trời quang',1:'Ít mây',2:'Có mây',3:'Nhiều mây',45:'Sương mù',48:'Sương mù',
        51:'Mưa phùn',53:'Mưa phùn',55:'Mưa phùn',61:'Mưa nhẹ',63:'Mưa',65:'Mưa to',
        71:'Tuyết',80:'Mưa rào',81:'Mưa rào',82:'Mưa rào mạnh',95:'Dông',96:'Dông',99:'Dông'};
    var url='https://api.open-meteo.com/v1/forecast?latitude=21.0278&longitude=105.8342'
        +'&current=temperature_2m,weather_code,wind_speed_10m&daily=sunrise,sunset&timezone=Asia%2FBangkok';
    fetch(url).then(function(r){return r.json();}).then(function(d){
        var c=d.current||{}, code=c.weather_code, t=Math.round(c.temperature_2m);
        var sr=(d.daily&&d.daily.sunrise&&d.daily.sunrise[0]||'').slice(11,16);
        var ss=(d.daily&&d.daily.sunset&&d.daily.sunset[0]||'').slice(11,16);
        document.getElementById('wxIc').textContent=ICON[code]||'⛅';
        document.getElementById('wxTemp').textContent=t+'°C';
        document.getElementById('wxMeta').innerHTML=(LABEL[code]||'')+'<br>'
            +'🌬️ '+Math.round(c.wind_speed_10m)+' km/h · ☀️ '+sr+' · 🌙 '+ss;
        document.getElementById('hrmWx').classList.remove('loading');
    }).catch(function(){ document.getElementById('hrmWx').style.display='none'; });
})();
</script>
<?php
hrm_footer();
