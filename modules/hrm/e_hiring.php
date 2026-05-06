<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$_name_parts = explode(' ', trim($full_name));
$first_name = end($_name_parts);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>E-Hiring – Tuyển dụng</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-inner-body{display:flex;flex:1;overflow:hidden}
/* TOPBAR */
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none;position:relative}
.eh-search::placeholder{color:rgba(255,255,255,0.4)}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap;transition:background 0.2s}
.top-btn:hover{background:rgba(255,255,255,0.2)}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-btn.primary:hover{background:#0284c7}
.top-icon-btn{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.08);border:none;color:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;cursor:pointer}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-avatar img{width:100%;height:100%;object-fit:cover}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}
/* BODY LAYOUT */
.eh-body{display:flex;flex:1;overflow:hidden}
/* MAIN CONTENT */
.eh-main{flex:1;overflow-y:auto;padding:16px}
.main-header{display:flex;align-items:center;gap:8px;margin-bottom:14px}
.main-header h2{font-size:15px;font-weight:600;color:#111827}
.main-header .icon-btn{width:28px;height:28px;border-radius:6px;background:#f3f4f6;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;transition:background 0.15s}
.main-header .icon-btn:hover{background:#e5e7eb}
.main-header .icon-btn:first-of-type{margin-left:auto}
/* JOB CARD */
.job-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:12px;display:flex;gap:12px}
.job-badge{width:64px;min-width:64px;display:flex;flex-direction:column;align-items:center;gap:4px}
.job-badge .count{font-size:18px;font-weight:700;color:#111827;line-height:1}
.job-badge .label{font-size:9px;color:#6b7280;text-align:center;line-height:1.2}
.job-badge .tag{background:#2563eb;color:#fff;font-size:9px;font-weight:700;padding:3px 6px;border-radius:4px;text-align:center;line-height:1.3;margin-top:2px}
.job-content{flex:1;min-width:0}
.job-title{font-size:14px;font-weight:600;color:#2563eb;text-decoration:none;display:block;margin-bottom:3px}
.job-title:hover{text-decoration:underline}
.job-meta{font-size:11px;color:#6b7280;margin-bottom:8px;line-height:1.5}
.job-meta span{margin-right:8px}
.job-stats{display:flex;gap:0;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden}
.stat-box{flex:1;padding:6px 4px;text-align:center;border-right:1px solid #e5e7eb}
.stat-box:last-child{border-right:none}
.stat-box .sv{font-size:14px;font-weight:700;color:#111827}
.stat-box .sl{font-size:9px;color:#9ca3af;line-height:1.3;margin-top:1px}
.stat-box .sv.red{color:#ef4444}
.job-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0;margin-top:4px}
/* RIGHT PANEL */
.eh-right{width:280px;min-width:280px;background:#fff;border-left:1px solid #e5e7eb;overflow-y:auto;padding:14px;flex-shrink:0}
.panel-section{margin-bottom:18px}
.panel-title{font-size:11px;font-weight:700;letter-spacing:0.5px;color:#6b7280;text-transform:uppercase;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #f3f4f6}
.stats-row{display:flex;gap:0;margin-bottom:6px}
.stat-col{flex:1;text-align:center}
.stat-col .sv{font-size:18px;font-weight:700;color:#111827}
.stat-col .sl{font-size:10px;color:#9ca3af;margin-top:2px}
.stat-col .sv.green{color:#22c55e}
.view-link{font-size:12px;color:#2563eb;text-decoration:none;display:block;margin-top:6px}
.view-link:hover{text-decoration:underline}
.empty-state{font-size:12px;color:#9ca3af;text-align:center;padding:12px 0}
.candidate-item{display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px solid #f3f4f6}
.candidate-item:last-child{border-bottom:none}
.cand-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;flex-shrink:0}
.cand-info{flex:1;min-width:0}
.cand-name{font-size:12px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cand-pos{font-size:11px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cand-time{font-size:10px;color:#9ca3af;margin-top:1px}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="eh-wrapper">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="eh-content-col">
    <div class="eh-top">
      <div style="position:relative;flex:1;max-width:320px">
        <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:0.4" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input class="eh-search" placeholder="Tìm kiếm trong toàn hệ thống">
      </div>
      <div class="top-actions">
        <button class="top-btn primary">⚡ Đăng tin tuyển dụng</button>
        <button class="top-btn">✦ Tạo chiến dịch</button>
        <button class="top-btn">🌐 Trang tuyển dụng</button>
        <button class="top-icon-btn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        </button>
        <div class="top-avatar">
          <?php if($avatar): ?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else: ?><?=strtoupper(substr($full_name,0,1))?><?php endif; ?>
        </div>
        <div class="top-user-info"><strong><?=htmlspecialchars($first_name)?></strong>BC Director</div>
      </div>
    </div>

    <div class="eh-inner-body">
      <!-- MAIN CONTENT -->
      <main class="eh-main">
    <div class="main-header">
      <h2>Các vị trí tuyển dụng mới nhất</h2>
      <button class="icon-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <button class="icon-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
      </button>
    </div>

    <!-- JOB CARDS -->
    <?php
    $jobs = [
      ['count'=>62,'title'=>'[ONSITE PROJECT] JAVA DEVELOPER','dept'=>'BFSI','loc'=>'Phường Yên Hòa, Hà Nội','salary'=>'VNĐ','chi'=>1,'time'=>'08/05 – 31/05/2026','views'=>1745,'nhs'=>34,'pv'=>1,'pvkh'=>0,'offered'=>0,'hired'=>1,'rejected'=>25],
      ['count'=>85,'title'=>'PROJECT MANAGER (GOV DOMAIN)','dept'=>'IT · AHT Tech Head Office','loc'=>'Tòa nhà MITEC, Cầu Giấy, Hà Nội','salary'=>'VNĐ','chi'=>1,'time'=>'08/05 – 31/05/2026','views'=>3620,'nhs'=>0,'pv'=>1,'pvkh'=>0,'offered'=>1,'hired'=>1,'rejected'=>83],
      ['count'=>22,'title'=>'[ONSITE PROJECT] MANUAL TESTER','dept'=>'BFSI','loc'=>'Phường Dịch Vọng Hậu, Hà Nội','salary'=>'VNĐ','chi'=>0,'time'=>'08/05 – 31/05/2026','views'=>1262,'nhs'=>6,'pv'=>2,'pvkh'=>0,'offered'=>0,'hired'=>2,'rejected'=>12],
      ['count'=>360,'title'=>'[BANKING PROJECT] MANUAL TESTER','dept'=>'BFSI','loc'=>'Lê Ngọc Hân, Hà Bà Trưng','salary'=>'VNĐ','chi'=>2,'time'=>'04/05 – 03/06/2026','views'=>14810,'nhs'=>7,'pv'=>3,'pvkh'=>0,'offered'=>10,'hired'=>0,'rejected'=>340],
      ['count'=>304,'title'=>'[BANKING PROJECT] MIDDLE/SENIOR BUSINESS ANALYST','dept'=>'BFSI','loc'=>'Lê Ngọc Hân, Hà Bà Trưng','salary'=>'VNĐ','chi'=>1,'time'=>'04/05 – 03/06/2026','views'=>6711,'nhs'=>5,'pv'=>2,'pvkh'=>0,'offered'=>3,'hired'=>0,'rejected'=>98],
    ];
    foreach($jobs as $j): ?>
    <div class="job-card">
      <div class="job-badge">
        <div class="count"><?=$j['count']?></div>
        <div class="label">ứng viên</div>
        <div class="tag">TIN TUYỂN DỤNG</div>
      </div>
      <div class="job-content">
        <a href="#" class="job-title"><?=htmlspecialchars($j['title'])?></a>
        <div class="job-meta">
          <span><?=htmlspecialchars($j['dept'])?></span>·
          <span><?=htmlspecialchars($j['loc'])?></span>·
          <span><?=$j['salary']?></span><br>
          Chi tiêu <?=$j['chi']?> · Thời gian: <?=htmlspecialchars($j['time'])?> · <strong><?=number_format($j['views'])?></strong> lượt xem
        </div>
        <div class="job-stats">
          <div class="stat-box"><div class="sv"><?=$j['nhs']?></div><div class="sl">Nhận hồ sơ</div></div>
          <div class="stat-box"><div class="sv"><?=$j['pv']?></div><div class="sl">Phỏng vấn</div></div>
          <div class="stat-box"><div class="sv"><?=$j['pvkh']?></div><div class="sl">PV Khách hàng</div></div>
          <div class="stat-box"><div class="sv"><?=$j['offered']?></div><div class="sl">Offered</div></div>
          <div class="stat-box"><div class="sv"><?=$j['hired']?></div><div class="sl">Hired</div></div>
          <div class="stat-box"><div class="sv red"><?=$j['rejected']?></div><div class="sl">Rejected</div></div>
        </div>
      </div>
      <div class="job-dot"></div>
    </div>
    <?php endforeach; ?>
  </main>

  <!-- RIGHT PANEL -->
  <aside class="eh-right">
    <div class="panel-section">
      <div class="panel-title">Thống kê ứng viên</div>
      <div class="stats-row">
        <div class="stat-col"><div class="sv">10</div><div class="sl">Hôm nay</div></div>
        <div class="stat-col"><div class="sv">31</div><div class="sl">07 ngày vừa qua</div></div>
        <div class="stat-col"><div class="sv">196</div><div class="sl">30 ngày vừa qua</div></div>
      </div>
    </div>
    <div class="panel-section">
      <div class="panel-title">Lượt xem trang tuyển dụng</div>
      <div class="stats-row">
        <div class="stat-col"><div class="sv">3,157</div><div class="sl">Hôm nay</div></div>
        <div class="stat-col"><div class="sv green">+16,084</div><div class="sl">Tuần này</div></div>
        <div class="stat-col"><div class="sv">+7,596,480</div><div class="sl">Tổng số lượt xem</div></div>
      </div>
    </div>
    <div class="panel-section">
      <div class="panel-title">Lịch phỏng vấn sắp tới</div>
      <div class="empty-state">Không có lịch phỏng vấn nào gần đây</div>
      <a href="#" class="view-link">Xem trên lịch biểu</a>
    </div>
    <div class="panel-section">
      <div class="panel-title">Ứng viên mới ứng tuyển gần đây</div>
      <?php
      $cands = [
        ['name'=>'Au Phung Mi','pos'=>'[HCM] SALES SUPPORT (B2B, GOO…','time'=>'Ứng tuyển 32 phút trước','color'=>'#f59e0b'],
        ['name'=>'Nguyen Ngoc Thuy Duong','pos'=>'[HCM] SALES SUPPORT (B2B, GOO…','time'=>'Ứng tuyển 36 phút trước','color'=>'#6366f1'],
        ['name'=>'Luong Cam Tu','pos'=>'[HCM] SALES SUPPORT (B2B, GOO…','time'=>'Ứng tuyển 36 phút trước','color'=>'#10b981'],
        ['name'=>'Trinh Nguyen Thu Trang','pos'=>'[HCM] SALES SUPPORT (B2B, GOO…','time'=>'Ứng tuyển 37 phút trước','color'=>'#ec4899'],
        ['name'=>'Vu Huy Hoang','pos'=>'[HCM] SALES SUPPORT (B2B, GOO…','time'=>'Ứng tuyển 39 phút trước','color'=>'#0ea5e9'],
      ];
      foreach($cands as $c): ?>
      <div class="candidate-item">
        <div class="cand-avatar" style="background:<?=$c['color']?>"><?=strtoupper(substr($c['name'],0,1))?></div>
        <div class="cand-info">
          <div class="cand-name"><?=htmlspecialchars($c['name'])?></div>
          <div class="cand-pos"><?=htmlspecialchars($c['pos'])?></div>
          <div class="cand-time"><?=htmlspecialchars($c['time'])?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <a href="#" class="view-link" style="margin-top:8px">Xem theo vị trí tuyển dụng</a>
    </aside>
  </div>
</div>
</div>
</body>
</html>
