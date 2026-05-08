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
    <title>Tin Tuyển dụng – E-Hiring</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/modules/hrm/sidebar.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
        .eh-wrapper{display:flex;height:100vh;overflow:hidden}
        .eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
        
        /* TOPBAR */
        .eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
        .eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none;position:relative}
        .eh-search::placeholder{color:rgba(255,255,255,0.4)}
        .top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
        .top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap;transition:background 0.2s}
        .top-btn:hover{background:rgba(255,255,255,0.2)}
        .top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
        .top-btn.primary:hover{background:#0284c7}
        .top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
        
        /* MAIN AREA */
        .eh-main{flex:1;overflow:hidden;display:flex;flex-direction:column;background:#fff}
        
        /* PAGE HEADER */
        .page-header{padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e5e7eb}
        .page-title{font-size:18px;font-weight:700;color:#111827}
        .header-tabs{display:flex;gap:24px}
        .header-tab{font-size:14px;color:#6b7280;text-decoration:none;padding-bottom:14px;margin-bottom:-17px;border-bottom:2px solid transparent;cursor:pointer;transition:all 0.2s}
        .header-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}
        
        /* FILTER BAR */
        .filter-bar{padding:12px 20px;display:flex;gap:12px;background:#f9fafb;border-bottom:1px solid #e5e7eb;align-items:center}
        .filter-input{padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;outline:none;min-width:200px}
        .filter-select{padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;outline:none;background:#fff;min-width:150px}
        .btn-icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;color:#6b7280}
        
        /* TABLE */
        .table-container{flex:1;overflow:auto;background:#fff}
        table{width:max-content;min-width:100%;border-collapse:collapse;table-layout:fixed}
        th{position:sticky;top:0;background:#f9fafb;padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;z-index:10}
        td{padding:16px;border-bottom:1px solid #f3f4f6;font-size:13px;vertical-align:top}
        tr:hover{background:#f8fafc}
        
        /* JOB COLUMN STYLES */
        .job-info-cell{display:flex;flex-direction:column;gap:4px}
        .job-title{font-size:14px;font-weight:600;color:#2563eb;text-decoration:none}
        .job-title:hover{text-decoration:underline}
        .job-code{font-size:11px;color:#9ca3af;font-weight:500}
        .job-tags{display:flex;gap:4px;margin-top:4px}
        .tag{font-size:10px;padding:2px 6px;border-radius:4px;font-weight:700}
        .tag-recruitment{background:#e0e7ff;color:#4338ca}
        .tag-expired{background:#fee2e2;color:#b91c1c}
        
        .meta-text{font-size:12px;color:#6b7280;line-height:1.4}
        .status-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:12px;font-size:11px;font-weight:600}
        .status-draft{background:#f3f4f6;color:#374151}
        .status-public{background:#dcfce7;color:#15803d}
        
        .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .stat-item{text-align:center}
        .stat-val{font-size:14px;font-weight:700;color:#111827}
        .stat-lbl{font-size:10px;color:#9ca3af}
        
        /* BOTTOM BAR */
        .bottom-bar{height:48px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid #e5e7eb;background:#fff;flex-shrink:0}
        .status-tabs{display:flex;gap:16px}
        .status-tab{font-size:12px;color:#6b7280;cursor:pointer;display:flex;align-items:center;gap:6px}
        .status-tab.active{color:#2563eb;font-weight:600}
        .status-count{background:#f3f4f6;padding:2px 6px;border-radius:10px;font-size:10px;color:#4b5563}
        .status-tab.active .status-count{background:#dbeafe;color:#2563eb}
        
        .pagination{display:flex;align-items:center;gap:12px;font-size:12px;color:#6b7280}
        .page-btn{padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;background:#fff;cursor:pointer}
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
                <button class="top-btn primary" onclick="location.href='/hrm/job-post-create'">⚡ Đăng tin tuyển dụng</button>
                <button class="top-btn">✦ Tạo chiến dịch</button>
                <button class="top-btn">🌐 Trang tuyển dụng</button>
                <div class="top-avatar">
                    <?php if($avatar): ?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else: ?><?=strtoupper(substr($full_name,0,1))?><?php endif; ?>
                </div>
            </div>
        </div>

        <main class="eh-main">
            <div class="page-header">
                <div class="page-title">Tin Tuyển dụng</div>
                <div class="header-tabs">
                    <div class="header-tab active" onclick="setOwnership('managed')">Tôi quản lý</div>
                    <div class="header-tab" onclick="setOwnership('created')">Tôi đã tạo</div>
                    <div class="header-tab" onclick="setOwnership('all')">Tất cả tin</div>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="top-btn" style="color:#2563eb;background:#eff6ff;border-color:#dbeafe" onclick="location.href='/hrm/job-post-create'">+ Đăng tin tuyển dụng</button>
                    <button class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </button>
                </div>
            </div>

            <div class="filter-bar">
                <input type="text" class="filter-input" placeholder="Tìm theo tiêu đề hoặc mã vị trí" id="searchInput">
                <select class="filter-select" id="deptFilter">
                    <option value="">Tất cả bộ phận</option>
                </select>
                <select class="filter-select" id="officeFilter">
                    <option value="">Tất cả địa điểm</option>
                </select>
                <select class="filter-select" id="statusFilter">
                    <option value="">Tất cả trạng thái</option>
                    <option value="public">Công khai</option>
                    <option value="private">Riêng tư</option>
                    <option value="draft">Bản nháp</option>
                    <option value="closed">Đã đóng</option>
                </select>
                <button class="top-btn" style="color:#6b7280;background:#fff;border-color:#d1d5db;padding:8px 16px" id="sortBtn">Sắp xếp</button>
            </div>

            <div class="table-container">
                <table id="jobsTable">
                    <thead>
                        <tr>
                            <th style="width:320px">Tin tuyển dụng</th>
                            <th style="width:100px">ID</th>
                            <th style="width:140px">Mã vị trí</th>
                            <th style="width:200px">Phòng ban - Địa điểm</th>
                            <th style="width:120px">Trạng thái</th>
                            <th style="width:150px">SLA & Chỉ tiêu</th>
                            <th style="width:180px">Thống kê ứng viên</th>
                            <th style="width:180px">Thống kê phỏng vấn</th>
                            <th style="width:160px">Người quản lý</th>
                            <th style="width:160px">Người đăng tin</th>
                            <th style="width:160px">Thời gian tạo</th>
                            <th style="width:160px">Kết thúc tuyển</th>
                            <th style="width:250px">Ghi chú</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="jobsBody">
                        <!-- Loaded via JS -->
                        <tr><td colspan="13" style="text-align:center;padding:40px;color:#9ca3af">Đang tải dữ liệu...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="bottom-bar">
                <div class="status-tabs">
                    <div class="status-tab active" data-status="all">Tất cả <span class="status-count" id="countAll">0</span></div>
                    <div class="status-tab" data-status="active">Đang tuyển <span class="status-count" id="countActive">0</span></div>
                    <div class="status-tab" data-status="closed">Đã đóng <span class="status-count" id="countClosed">0</span></div>
                    <div class="status-tab" data-status="draft">Bản nháp <span class="status-count" id="countDraft">0</span></div>
                </div>
                <div class="pagination">
                    <span>Hiển thị 50 kết quả mỗi trang</span>
                    <button class="page-btn">Trang trước</button>
                    <span>Trang 1 / 1</span>
                    <button class="page-btn">Trang sau</button>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
let jobsData = [];
let filters = {
    ownership: 'managed',
    search: '',
    dept: '',
    office: '',
    status: '',
    tabStatus: 'all'
};

async function loadInitialData() {
    try {
        const [deptRes, officeRes] = await Promise.all([
            fetch('/hrm/ajax-handler?action=get_depts'),
            fetch('/hrm/ajax-handler?action=get_offices')
        ]);
        
        const depts = await deptRes.json();
        const dSelect = document.getElementById('deptFilter');
        depts.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.innerText = d.name;
            dSelect.appendChild(opt);
        });

        const offices = await officeRes.json();
        const oSelect = document.getElementById('officeFilter');
        offices.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.name;
            opt.innerText = o.name;
            oSelect.appendChild(opt);
        });
        
        await fetchJobs();
    } catch (e) { console.error(e); }
}

async function fetchJobs() {
    const tbody = document.getElementById('jobsBody');
    tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:40px;color:#9ca3af">Đang tải dữ liệu...</td></tr>';
    
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_jobs&' + new URLSearchParams(filters));
        const result = await res.json();
        if (result.success) {
            jobsData = result.data;
            renderJobs();
            updateCounts(result.counts);
        }
    } catch (e) { console.error(e); }
}

function renderJobs() {
    const tbody = document.getElementById('jobsBody');
    if (jobsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;padding:40px;color:#9ca3af">Không tìm thấy tin tuyển dụng nào</td></tr>';
        return;
    }

    tbody.innerHTML = jobsData.map(j => `
        <tr>
            <td>
                <div class="job-info-cell">
                    <a href="/hrm/job-detail?id=${j.id}" class="job-title">${j.title}</a>
                    <div class="job-tags">
                        <span class="tag tag-recruitment">TIN TUYỂN DỤNG</span>
                        ${isExpired(j.deadline) ? '<span class="tag tag-expired">QUÁ HẠN</span>' : ''}
                    </div>
                </div>
            </td>
            <td><strong>#${j.id}</strong></td>
            <td><div class="job-code">${j.job_code || '---'}</div></td>
            <td>
                <div class="meta-text">
                    <strong>${j.dept_name || 'N/A'}</strong><br>
                    ${j.office || 'N/A'}
                </div>
            </td>
            <td>
                <span class="status-badge ${j.status === 'draft' ? 'status-draft' : 'status-public'}">
                    ${formatStatus(j.status)}
                </span>
            </td>
            <td>
                <div class="meta-text">
                    Chỉ tiêu: <strong>${j.quantity || 0}</strong><br>
                    Deadline: ${j.deadline || 'N/A'}
                </div>
            </td>
            <td>
                <div class="stats-grid">
                    <div class="stat-item"><div class="stat-val">${j.total_candidates || 0}</div><div class="stat-lbl">Ứng viên</div></div>
                    <div class="stat-item"><div class="stat-val" style="color:#16a34a">${j.hired_candidates || 0}</div><div class="stat-lbl">Đã tuyển</div></div>
                    <div class="stat-item"><div class="stat-val" style="color:#2563eb">${j.in_process || 0}</div><div class="stat-lbl">Đang xử lý</div></div>
                </div>
            </td>
            <td>
                <div class="stats-grid">
                    <div class="stat-item"><div class="stat-val">${j.interviews || 0}</div><div class="stat-lbl">Phỏng vấn</div></div>
                </div>
            </td>
            <td><div class="meta-text">${j.managers || '---'}</div></td>
            <td><div class="meta-text">${j.creator_name || 'Hệ thống'}</div></td>
            <td><div class="meta-text">${j.created_at ? j.created_at.split(' ')[0] : '---'}</div></td>
            <td><div class="meta-text">${j.deadline || '---'}</div></td>
            <td><div class="meta-text" style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${j.notes || ''}">${j.notes || '---'}</div></td>
            <td>
                <button class="btn-icon" style="width:28px;height:28px;color:#ef4444;border-color:#fca5a5;background:#fef2f2" onclick="deleteJob(${j.id})" title="Xóa tin tuyển dụng">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                </button>
            </td>
        </tr>
    `).join('');
}

function isExpired(deadline) {
    if (!deadline) return false;
    return new Date(deadline) < new Date();
}

function formatSalary(j) {
    if (j.salary_from && j.salary_to) return `${j.salary_from} - ${j.salary_to} ${j.currency}`;
    if (j.salary_from) return `Từ ${j.salary_from} ${j.currency}`;
    return 'Thỏa thuận';
}

function formatDateRange(j) {
    const start = j.created_at ? j.created_at.split(' ')[0] : 'N/A';
    const end = j.deadline || 'N/A';
    return `${start} — ${end}`;
}

function formatStatus(s) {
    const map = { draft: 'Bản nháp', public: 'Công khai', private: 'Riêng tư', closed: 'Đã đóng' };
    return map[s] || s;
}

async function deleteJob(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa tin tuyển dụng này? Các dữ liệu liên quan cũng sẽ bị xóa.')) return;
    try {
        const res = await fetch('/hrm/ajax-handler?action=delete_job', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            fetchJobs();
        } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa'));
        }
    } catch (e) {
        console.error(e);
        alert('Có lỗi xảy ra khi xóa tin tuyển dụng.');
    }
}

function updateCounts(counts) {
    document.getElementById('countAll').innerText = counts.all || 0;
    document.getElementById('countActive').innerText = counts.active || 0;
    document.getElementById('countClosed').innerText = counts.closed || 0;
    document.getElementById('countDraft').innerText = counts.draft || 0;
}

function setOwnership(val) {
    filters.ownership = val;
    document.querySelectorAll('.header-tab').forEach(t => t.classList.toggle('active', t.innerText.toLowerCase().includes(val === 'managed' ? 'quản lý' : (val === 'created' ? 'đã tạo' : 'tất cả'))));
    fetchJobs();
}

// Event Listeners
document.getElementById('searchInput').addEventListener('input', (e) => {
    filters.search = e.target.value;
    clearTimeout(window.searchTimer);
    window.searchTimer = setTimeout(fetchJobs, 500);
});

document.getElementById('deptFilter').addEventListener('change', (e) => {
    filters.dept = e.target.value;
    fetchJobs();
});

document.getElementById('officeFilter').addEventListener('change', (e) => {
    filters.office = e.target.value;
    fetchJobs();
});

document.getElementById('statusFilter').addEventListener('change', (e) => {
    filters.status = e.target.value;
    fetchJobs();
});

document.querySelectorAll('.status-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        filters.tabStatus = tab.dataset.status;
        fetchJobs();
    });
});

document.addEventListener('DOMContentLoaded', loadInitialData);
</script>
</body>
</html>
