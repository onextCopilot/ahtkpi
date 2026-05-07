<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$_p = explode(' ', trim($full_name)); $first_name = end($_p);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mẫu phỏng vấn – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search-wrap{position:relative;flex:1;max-width:320px}
.eh-search-wrap svg{position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:.4}
.eh-search{width:100%;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}
.eh-main{flex:1;overflow-y:auto;padding:32px;background:#fff}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.page-title{font-size:22px;font-weight:700;color:#111827}
.btn-primary{background:#1d4ed8;border:none;color:#fff;padding:10px 20px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn-primary:hover{background:#1e40af}

/* Tabs */
.tabs-bar{display:flex;align-items:center;border-bottom:2px solid #e5e7eb;margin-bottom:0;gap:0}
.tab-btn{padding:10px 16px;font-size:12px;font-weight:600;color:#6b7280;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;transition:all .2s}
.tab-btn.active{color:#1d4ed8;border-bottom-color:#1d4ed8}
.tab-btn:hover{color:#374151}
.search-box{margin-left:auto;position:relative}
.search-box svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af}
.search-input{padding:8px 12px 8px 34px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;outline:none;width:220px}
.search-input:focus{border-color:#1d4ed8}

/* List */
.template-list{border-top:1px solid #f3f4f6}
.template-item{display:flex;align-items:center;gap:16px;padding:18px 0;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .15s}
.template-item:hover{background:#fafafa}
.tmpl-icon{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px}
.tmpl-icon.onsite{background:#3b82f6}
.tmpl-icon.online{background:#8b5cf6}
.tmpl-info{flex:1;min-width:0}
.tmpl-name{font-size:15px;font-weight:600;color:#1d4ed8;margin-bottom:4px;cursor:pointer}
.tmpl-name:hover{text-decoration:underline}
.tmpl-meta{font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tmpl-type-badge{display:inline-block;font-weight:700;color:#374151;margin-right:4px}
.tmpl-creator{font-size:11px;color:#9ca3af;margin-top:3px}
.tmpl-toggle{flex-shrink:0}
.toggle-switch{position:relative;width:44px;height:24px}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;border-radius:24px;transition:.3s}
.toggle-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s}
.toggle-switch input:checked + .toggle-slider{background:#22c55e}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(20px)}
.empty-state{text-align:center;padding:60px 20px;color:#9ca3af}
.empty-state svg{margin:0 auto 12px;display:block;opacity:.3}
.tmpl-actions{display:flex;gap:8px;flex-shrink:0}
.action-btn{background:none;border:none;color:#d1d5db;cursor:pointer;padding:4px;border-radius:4px;transition:color .15s}
.action-btn:hover{color:#ef4444}
</style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="eh-content-col">
        <div class="eh-top">
            <div class="eh-search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search" placeholder="Tìm kiếm trong toàn hệ thống">
            </div>
            <div class="top-actions">
                <a href="/hrm/interview-template-detail" class="top-btn" style="background:#0ea5e9;border-color:#0ea5e9">⚡ Đăng tin tuyển dụng</a>
                <div class="top-avatar"><?php if($avatar):?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else:?><?=strtoupper(substr($full_name,0,1))?><?php endif;?></div>
                <div class="top-user-info"><strong><?=htmlspecialchars($first_name)?></strong>BC Director</div>
            </div>
        </div>

        <main class="eh-main">
            <div class="page-header">
                <h1 class="page-title">Mẫu phỏng vấn</h1>
                <a href="/hrm/interview-template-detail" class="btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    THÊM MẪU PHỎNG VẤN MỚI
                </a>
            </div>

            <div style="display:flex;align-items:center;margin-bottom:0">
                <div class="tabs-bar" style="flex:1;border-bottom:none">
                    <button class="tab-btn active" data-filter="active" onclick="switchTab(this,'active')">ĐANG SỬ DỤNG (<span id="cnt-active">0</span>)</button>
                    <button class="tab-btn" data-filter="mine" onclick="switchTab(this,'mine')">TẠO BỞI TÔI (<span id="cnt-mine">0</span>)</button>
                    <button class="tab-btn" data-filter="inactive" onclick="switchTab(this,'inactive')">ĐANG TẠM KHOÁ (<span id="cnt-inactive">0</span>)</button>
                </div>
                <div class="search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input class="search-input" id="search-input" placeholder="Tìm nhanh mẫu phỏng vấn" oninput="filterList()">
                </div>
            </div>
            <div style="border-bottom:2px solid #e5e7eb;margin-bottom:0"></div>

            <div class="template-list" id="template-list">
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p>Đang tải...</p>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
let allTemplates = [];
let currentFilter = 'active';

async function loadTemplates(filter) {
    currentFilter = filter;
    try {
        const res = await fetch(`/hrm/ajax-handler?action=get_interview_templates&filter=${filter}`);
        const result = await res.json();
        if (result.success) {
            allTemplates = result.data;
            updateCounts();
            renderList(allTemplates);
        }
    } catch(e) { console.error(e); }
}

async function loadAllCounts() {
    const filters = ['active','mine','inactive'];
    for (const f of filters) {
        try {
            const res = await fetch(`/hrm/ajax-handler?action=get_interview_templates&filter=${f}`);
            const r = await res.json();
            if (r.success) document.getElementById('cnt-' + f).textContent = r.data.length;
        } catch(e){}
    }
}

function renderList(list) {
    const el = document.getElementById('template-list');
    if (!list.length) {
        el.innerHTML = '<div class="empty-state"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg><p>Chưa có mẫu phỏng vấn nào.</p></div>';
        return;
    }
    el.innerHTML = list.map(t => {
        const typeName = t.interview_type === 'online' ? 'ONLINE' : 'ONSITE';
        const typeClass = t.interview_type === 'online' ? 'online' : 'onsite';
        const icon = t.interview_type === 'online'
            ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>'
            : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
        const preview = (t.email_body || '').replace(/<[^>]+>/g, '').substring(0, 80);
        const createdTime = formatDateTime(t.created_at);
        return `<div class="template-item">
            <div class="tmpl-icon ${typeClass}">${icon}</div>
            <div class="tmpl-info">
                <div class="tmpl-name" onclick="location.href='/hrm/interview-template-detail?id=${t.id}'">${escHtml(t.name)}</div>
                <div class="tmpl-meta"><span class="tmpl-type-badge">${typeName}</span> — ${escHtml(preview)}${preview.length >= 80 ? '...' : ''}</div>
                <div class="tmpl-creator">Tạo bởi @${escHtml(t.creator_username || 'system')} lúc ${createdTime}</div>
            </div>
            <div class="tmpl-actions">
                <button class="action-btn" onclick="editTemplate(${t.id})" title="Chỉnh sửa">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="action-btn" onclick="deleteTemplate(${t.id})" title="Xoá">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>
            <div class="tmpl-toggle">
                <label class="toggle-switch">
                    <input type="checkbox" ${t.is_active == 1 ? 'checked' : ''} onchange="toggleTemplate(${t.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>`;
    }).join('');
}

function updateCounts() {
    // Will be updated on tab switch via loadAllCounts
}

function filterList() {
    const q = document.getElementById('search-input').value.toLowerCase();
    const filtered = allTemplates.filter(t => t.name.toLowerCase().includes(q) || (t.email_subject||'').toLowerCase().includes(q));
    renderList(filtered);
}

function switchTab(el, filter) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    loadTemplates(filter);
}

function editTemplate(id) {
    location.href = '/hrm/interview-template-detail?id=' + id;
}

async function toggleTemplate(id, isActive) {
    try {
        await fetch('/hrm/ajax-handler?action=toggle_interview_template', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, is_active: isActive ? 1 : 0})
        });
        loadAllCounts();
    } catch(e){}
}

async function deleteTemplate(id) {
    if (!confirm('Bạn có chắc muốn xoá mẫu phỏng vấn này?')) return;
    try {
        const res = await fetch('/hrm/ajax-handler?action=delete_interview_template', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        const r = await res.json();
        if (r.success) { loadTemplates(currentFilter); loadAllCounts(); }
    } catch(e){}
}

function formatDateTime(str) {
    if (!str) return '';
    const d = new Date(str);
    const hh = String(d.getHours()).padStart(2,'0');
    const mm = String(d.getMinutes()).padStart(2,'0');
    const dd = String(d.getDate()).padStart(2,'0');
    const mo = String(d.getMonth()+1).padStart(2,'0');
    const yy = d.getFullYear();
    return `${hh}:${mm} ${dd}/${mo}/${yy}`;
}

function escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadTemplates('active');
    loadAllCounts();
});
</script>
</body>
</html>
