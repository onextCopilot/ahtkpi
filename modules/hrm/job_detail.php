<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$job_id = (int)($_GET['id'] ?? 0);

if (!$job_id) { header("Location: /hrm/openings"); exit(); }

// Fetch job details
global $conn;
$res = $conn->query("SELECT j.*, d.name as dept_name FROM hrm_job_posts j LEFT JOIN hrm_departments d ON j.department_id = d.id WHERE j.id = $job_id");
$job = $res ? $res->fetch_assoc() : null;

if (!$job) { die("Tin tuyển dụng không tồn tại"); }
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=htmlspecialchars($job['title'])?> – E-Hiring</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/modules/hrm/sidebar.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
        .eh-wrapper{display:flex;height:100vh;overflow:hidden}
        .eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
        
        /* TOPBAR */
        .eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0}
        .eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
        .top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
        .top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer}
        .top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
        .top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
        
        /* HEADER */
        .job-header{background:#fff;padding:16px 24px;border-bottom:1px solid #e5e7eb;flex-shrink:0}
        .header-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
        .job-title-row{display:flex;align-items:center;gap:12px}
        .job-title{font-size:20px;font-weight:700;color:#111827}
        .job-status{font-size:11px;font-weight:700;padding:4px 8px;border-radius:4px;text-transform:uppercase}
        .status-draft{background:#f3f4f6;color:#6b7280}
        .status-public{background:#dcfce7;color:#15803d}
        
        .header-meta{display:flex;gap:24px;font-size:13px;color:#6b7280}
        .meta-item{display:flex;align-items:center;gap:6px}
        .header-tabs{display:flex;gap:24px;margin-top:16px}
        .header-tab{font-size:14px;color:#6b7280;text-decoration:none;padding-bottom:12px;margin-bottom:-17px;border-bottom:2px solid transparent;cursor:pointer;font-weight:500}
        .header-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}
        
        /* PIPELINE */
        .pipeline-container{flex:1;overflow-x:auto;overflow-y:hidden;display:flex;padding:20px;gap:16px;background:#f3f4f6}
        .stage-column{width:300px;flex-shrink:0;display:flex;flex-direction:column;background:#ebedef;border-radius:8px;max-height:100%}
        .stage-header{padding:12px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(0,0,0,0.05)}
        .stage-name{font-size:13px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px}
        .stage-count{background:rgba(0,0,0,0.1);padding:2px 8px;border-radius:10px;font-size:11px;color:#4b5563}
        .stage-body{flex:1;overflow-y:auto;padding:8px;display:flex;flex-direction:column;gap:8px}
        
        /* CANDIDATE CARD */
        .candidate-card{background:#fff;border-radius:6px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer;border:1px solid transparent;transition:all 0.2s}
        .candidate-card:hover{border-color:#2563eb;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)}
        .cand-header{display:flex;gap:10px;margin-bottom:8px}
        .cand-avatar{width:32px;height:32px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;color:#6b7280;flex-shrink:0}
        .cand-name{font-size:14px;font-weight:600;color:#111827}
        .cand-source{font-size:11px;color:#9ca3af}
        .cand-footer{margin-top:8px;padding-top:8px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center}
        .cand-time{font-size:11px;color:#9ca3af}
        .cand-rating{color:#fbbf24;font-size:12px}
        
        /* SIDEBAR ACTIONS */
        .job-actions{display:flex;gap:8px}
        .action-btn{height:36px;padding:0 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;border:1px solid #d1d5db;background:#fff;color:#374151}
        .action-btn:hover{background:#f9fafb}
        .action-btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
        .action-btn.primary:hover{background:#1d4ed8}
    </style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="eh-content-col">
        <div class="eh-top">
            <div style="position:relative;flex:1;max-width:320px">
                <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:0.4" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search" placeholder="Tìm nhanh trong toàn hệ thống">
            </div>
            <div class="top-actions">
                <button class="top-btn" onclick="location.href='/hrm/openings'">← Danh sách tin</button>
                <div class="top-avatar"><?=strtoupper(substr($full_name,0,1))?></div>
            </div>
        </div>

        <header class="job-header">
            <div class="header-top">
                <div class="job-info-group">
                    <div class="job-title-row">
                        <h1 class="job-title"><?=htmlspecialchars($job['title'])?></h1>
                        <span class="job-status <?=$job['status'] === 'draft' ? 'status-draft' : 'status-public'?>">
                            <?=$job['status'] === 'draft' ? 'Bản nháp' : 'Đang tuyển'?>
                        </span>
                        <span style="font-size:12px;color:#9ca3af;font-weight:500">ID: #<?=$job_id?></span>
                    </div>
                    <div class="header-meta">
                        <div class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?=$job['office'] ?: 'Văn phòng chính'?>
                        </div>
                        <div class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Deadline: <?=$job['deadline'] ?: 'Không thời hạn'?>
                        </div>
                        <div class="meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Chỉ tiêu: <?=$job['quantity'] ?: 0?>
                        </div>
                    </div>
                </div>
                <div class="job-actions">
                    <button class="action-btn" onclick="location.href='/hrm/job-edit?id=<?=$job_id?>'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Thiết lập tin
                    </button>
                    <button class="action-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                        Chia sẻ
                    </button>
                    <button class="action-btn primary">
                        + Thêm ứng viên
                    </button>
                </div>
            </div>
            <div class="header-tabs">
                <div class="header-tab active">Pipeline</div>
                <div class="header-tab">Danh sách ứng viên</div>
                <div class="header-tab">Lịch phỏng vấn</div>
                <div class="header-tab">Báo cáo</div>
            </div>
        </header>

        <main class="pipeline-container" id="pipelineBoard">
            <!-- Stages will be loaded here -->
            <div style="display:flex;align-items:center;justify-content:center;flex:1;color:#9ca3af;font-size:14px">
                Đang tải dữ liệu Pipeline...
            </div>
        </main>
    </div>
</div>

<script>
async function loadPipeline() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_hiring_steps');
        const result = await res.json();
        if (result.success) {
            renderPipeline(result.data);
        }
    } catch (e) { console.error(e); }
}

function renderPipeline(stages) {
    const board = document.getElementById('pipelineBoard');
    board.innerHTML = '';
    
    // 1. Render các bước từ database
    stages.forEach(s => {
        board.appendChild(createStageColumn(s.id, s.name));
    });
    
    // 2. Render 3 bước fixed cứng (System stages)
    const systemStages = [
        { id: 'offered', name: 'Offered' },
        { id: 'hired', name: 'Hired' },
        { id: 'rejected', name: 'Rejected' }
    ];
    
    systemStages.forEach(s => {
        board.appendChild(createStageColumn(s.id, s.name, true));
    });
}

function createStageColumn(id, name, isSystem = false) {
    const col = document.createElement('div');
    col.className = 'stage-column';
    if (isSystem) col.style.background = '#f8fafc'; // Màu nền hơi khác cho bước hệ thống
    
    col.innerHTML = `
        <div class="stage-header">
            <div class="stage-name">${name} <span class="stage-count">0</span></div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
        </div>
        <div class="stage-body" id="stage-${id}">
            <div style="padding:40px 0; text-align:center; color:#9ca3af; font-size:12px; font-style:italic">
                Chưa có ứng viên
            </div>
        </div>
    `;
    return col;
}

document.addEventListener('DOMContentLoaded', loadPipeline);
</script>
</body>
</html>
