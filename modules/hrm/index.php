<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role      = $_SESSION['role'];
$avatar    = $_SESSION['avatar'] ?? null;

// Get hour for greeting
$hour = (int) date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

// First name only
$first_name = explode(' ', trim($full_name));
$first_name = end($first_name);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRM – Quản lý Nhân sự</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0d2e35 0%, #0a3d46 30%, #0d4a55 60%, #0a3a42 100%);
            min-height: 100vh;
            color: #fff;
            display: flex;
            overflow: hidden;
        }

        /* ── LEFT SIDEBAR ── */
        .hrm-sidebar {
            width: 200px;
            min-width: 200px;
            background: rgba(0,0,0,0.25);
            border-right: 1px solid rgba(255,255,255,0.06);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            backdrop-filter: blur(8px);
        }

        .hrm-sidebar .logo-area {
            padding: 0 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            margin-bottom: 12px;
        }

        .hrm-sidebar .logo-area img {
            height: 28px;
            filter: brightness(0) invert(1);
        }

        .sidebar-section {
            padding: 6px 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            margin-top: 4px;
        }

        .sidebar-item {
            display: block;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.18s;
            border-left: 2px solid transparent;
        }

        .sidebar-item:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
        }

        .sidebar-item.active {
            color: #fff;
            border-left-color: #4dd0e1;
            background: rgba(77,208,225,0.08);
        }

        /* ── MAIN CONTENT ── */
        .hrm-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── TOP BAR ── */
        .hrm-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 32px;
            background: rgba(0,0,0,0.15);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .hrm-topbar .company-name {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
        }

        .hrm-topbar .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4dd0e1, #0097a7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.25);
        }

        .topbar-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .topbar-icon-btn {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
            color: rgba(255,255,255,0.7);
        }

        .topbar-icon-btn:hover { background: rgba(255,255,255,0.15); }

        /* ── CONTENT AREA ── */
        .hrm-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 40px 32px 20px;
            overflow-y: auto;
        }

        /* ── SEARCH ── */
        .search-wrap {
            width: 100%;
            max-width: 580px;
            margin-bottom: 48px;
        }

        .search-box {
            width: 100%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 12px 18px 12px 44px;
            color: #fff;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.2s;
            backdrop-filter: blur(6px);
        }

        .search-box::placeholder { color: rgba(255,255,255,0.45); }
        .search-box:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(77,208,225,0.5);
        }

        .search-wrap-inner {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.4);
            pointer-events: none;
        }

        /* ── APPS GRID ── */
        .apps-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 32px 24px;
            justify-content: center;
            max-width: 680px;
            margin-bottom: 60px;
        }

        .app-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
            width: 90px;
            transition: transform 0.18s;
        }

        .app-item:hover { transform: translateY(-4px); }

        .app-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            transition: box-shadow 0.2s;
        }

        .app-item:hover .app-icon {
            box-shadow: 0 8px 24px rgba(0,0,0,0.35);
        }

        .app-name {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            text-align: center;
            line-height: 1.3;
        }

        .app-sub {
            font-size: 10px;
            color: rgba(255,255,255,0.5);
            text-align: center;
            margin-top: -4px;
        }

        /* icon colors */
        .icon-purple  { background: linear-gradient(135deg, #7e57c2, #512da8); }
        .icon-blue    { background: linear-gradient(135deg, #29b6f6, #0288d1); }
        .icon-teal    { background: linear-gradient(135deg, #26c6da, #00838f); }
        .icon-orange  { background: linear-gradient(135deg, #ffa726, #e65100); }
        .icon-green   { background: linear-gradient(135deg, #66bb6a, #2e7d32); }
        .icon-red     { background: linear-gradient(135deg, #ef5350, #b71c1c); }
        .icon-indigo  { background: linear-gradient(135deg, #5c6bc0, #283593); }
        .icon-pink    { background: linear-gradient(135deg, #ec407a, #880e4f); }
        .icon-cyan    { background: linear-gradient(135deg, #4dd0e1, #006064); }
        .icon-amber   { background: linear-gradient(135deg, #ffca28, #ff8f00); }

        /* ── BOTTOM INFO BAR ── */
        .bottom-bar {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 32px;
            padding: 16px 32px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .clock-block .time {
            font-size: 36px;
            font-weight: 300;
            letter-spacing: 2px;
            line-height: 1;
        }

        .clock-block .time span.secs {
            font-size: 20px;
            opacity: 0.6;
            font-weight: 300;
        }

        .clock-block .date {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-top: 4px;
        }

        .weather-block {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 24px;
            border-left: 1px solid rgba(255,255,255,0.12);
        }

        .weather-block .weather-icon { font-size: 40px; line-height: 1; }

        .weather-block .weather-info { font-size: 12px; color: rgba(255,255,255,0.6); }

        .weather-block .weather-temp {
            font-size: 22px;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
        }

        .greeting-block {
            padding-left: 24px;
            border-left: 1px solid rgba(255,255,255,0.12);
        }

        .greeting-block .greeting-text {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }

        .greeting-block .announcement {
            font-size: 11px;
            color: rgba(255,255,255,0.45);
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .announce-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .announce-nav button {
            background: rgba(255,255,255,0.1);
            border: none;
            color: rgba(255,255,255,0.6);
            width: 20px; height: 20px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }

        .announce-nav button:hover { background: rgba(255,255,255,0.2); }

        /* ── PAGE DOTS ── */
        .page-dots {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-bottom: 16px;
        }

        .page-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
        }

        .page-dot.active {
            background: rgba(255,255,255,0.85);
        }

        /* scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }
    </style>
</head>
<body>

<!-- LEFT SIDEBAR -->
<aside class="hrm-sidebar">
    <div class="logo-area">
        <img src="https://www.arrowhitech.com/wp-content/uploads/2025/06/Logo.svg" alt="AHT Logo">
    </div>

    <span class="sidebar-section">Tất cả ứng dụng</span>
    <a href="#" class="sidebar-item">WORK+</a>
    <a href="/hrm" class="sidebar-item active" style="border-left-color:#4dd0e1; background:rgba(77,208,225,0.08);">HRM+</a>

    <div style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.07); padding-top: 10px;">
        <a href="/dashboard" class="sidebar-item" style="font-size: 12px; color: rgba(255,255,255,0.55); display:flex; align-items:center; gap:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Về trang chủ
        </a>
        <a href="#" class="sidebar-item" style="font-size: 11px; color: rgba(255,255,255,0.4);">Cộng đồng hỏi đáp chia sẻ</a>
    </div>
</aside>

<!-- MAIN -->
<div class="hrm-main">

    <!-- TOP BAR -->
    <header class="hrm-topbar">
        <span class="company-name">AHT TECH JSC</span>
        <div class="topbar-right">
            <div class="topbar-avatar">
                <?php if ($avatar): ?>
                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <span style="font-size:13px; font-weight:600; color:rgba(255,255,255,0.85);"><?php echo htmlspecialchars($first_name); ?></span>
            <button class="topbar-icon-btn" title="Search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
            <button class="topbar-icon-btn" title="Notifications">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            </button>
            <button class="topbar-icon-btn" title="Apps">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <button class="topbar-icon-btn" title="Menu">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </header>

    <!-- CONTENT -->
    <div class="hrm-content">

        <!-- SEARCH -->
        <div class="search-wrap">
            <div class="search-wrap-inner">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" class="search-box" placeholder="Tìm kiếm ứng dụng">
            </div>
        </div>

        <!-- APPS -->
        <div class="apps-grid">

            <a href="/dashboard" class="app-item">
                <div class="app-icon icon-purple">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                </div>
                <span class="app-name">Platform</span>
                <span class="app-sub">Trang chủ</span>
            </a>



            <a href="#" class="app-item">
                <div class="app-icon icon-teal">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <span class="app-name">Message</span>
                <span class="app-sub">Nhắn tin</span>
            </a>

            <a href="#" class="app-item">
                <div class="app-icon icon-amber">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                </div>
                <span class="app-name">Drive</span>
                <span class="app-sub">Tài liệu</span>
            </a>

            <a href="/hrm/candidates" class="app-item">
                <div class="app-icon icon-indigo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <span class="app-name">Candidates</span>
                <span class="app-sub">Ứng viên</span>
            </a>

            <a href="/hrm/e-hiring.php" class="app-item">
                <div class="app-icon icon-cyan">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <span class="app-name">E-Hiring</span>
                <span class="app-sub">Tuyển dụng</span>
            </a>

            <a href="#" class="app-item">
                <div class="app-icon icon-green">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <span class="app-name">Test Center</span>
                <span class="app-sub">Kiểm tra Online</span>
            </a>

            <a href="#" class="app-item">
                <div class="app-icon icon-orange">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <span class="app-name">Account</span>
                <span class="app-sub">Tài khoản</span>
            </a>



        </div>

        <!-- PAGE DOTS -->
        <div class="page-dots">
            <div class="page-dot active"></div>
            <div class="page-dot"></div>
            <div class="page-dot"></div>
        </div>

    </div>

    <!-- BOTTOM BAR -->
    <div class="bottom-bar">

        <!-- CLOCK -->
        <div class="clock-block">
            <div class="time" id="hrm-clock">--:--<span class="secs" id="hrm-secs">--</span></div>
            <div class="date" id="hrm-date">--</div>
        </div>

        <!-- WEATHER -->
        <div class="weather-block">
            <div class="weather-icon" id="hrm-weather-icon">⛅</div>
            <div>
                <div class="weather-temp" id="hrm-temp">--°C</div>
                <div class="weather-info" id="hrm-location">Hà Nội</div>
            </div>
            <div style="margin-left:8px; font-size:11px; color:rgba(255,255,255,0.45);">
                <div>↑ <span id="hrm-hi">--</span></div>
                <div>↓ <span id="hrm-lo">--</span></div>
            </div>
        </div>



    </div>
</div>

<script>
// ── CLOCK ──
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('hrm-clock').childNodes[0].textContent = h + ':' + m;
    document.getElementById('hrm-secs').textContent = ':' + s;

    const days = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
    const months = ['tháng 1','tháng 2','tháng 3','tháng 4','tháng 5','tháng 6','tháng 7','tháng 8','tháng 9','tháng 10','tháng 11','tháng 12'];
    const d = days[now.getDay()];
    const mo = months[now.getMonth()];
    const date = now.getDate();
    const year = now.getFullYear();
    document.getElementById('hrm-date').textContent = `HÀ NỘI, ${d.toUpperCase()} ${date}/${String(now.getMonth()+1).padStart(2,'0')}/${year}`;
}
updateClock();
setInterval(updateClock, 1000);

// ── WEATHER (Open-Meteo, no API key needed) ──
(async function() {
    try {
        const geo = await fetch('https://nominatim.openstreetmap.org/search?q=Hanoi&format=json&limit=1').then(r=>r.json());
        const lat = geo[0]?.lat || 21.0285;
        const lon = geo[0]?.lon || 105.8542;
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&daily=temperature_2m_max,temperature_2m_min&timezone=Asia/Bangkok`;
        const data = await fetch(url).then(r=>r.json());
        const temp = Math.round(data.current_weather?.temperature ?? '--');
        const hi   = Math.round(data.daily?.temperature_2m_max?.[0] ?? '--');
        const lo   = Math.round(data.daily?.temperature_2m_min?.[0] ?? '--');
        const wc   = data.current_weather?.weathercode ?? 0;

        let icon = '☀️';
        if (wc === 0) icon = '☀️';
        else if (wc <= 3) icon = '🌤️';
        else if (wc <= 48) icon = '🌫️';
        else if (wc <= 67) icon = '🌧️';
        else if (wc <= 77) icon = '❄️';
        else if (wc <= 82) icon = '⛈️';
        else icon = '⛈️';

        document.getElementById('hrm-temp').textContent = temp + '°C';
        document.getElementById('hrm-hi').textContent   = hi + '°';
        document.getElementById('hrm-lo').textContent   = lo + '°';
        document.getElementById('hrm-weather-icon').textContent = icon;
    } catch(e) {
        document.getElementById('hrm-temp').textContent = '--°C';
    }
})();
</script>
</body>
</html>
