<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$p = explode(' ', trim($full_name)); $first_name = end($p);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lý do từ chối – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f8fafc;color:#1e293b;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}

.eh-main{flex:1;overflow-y:auto;padding:32px}
.page-title{font-size:24px;font-weight:400;color:#1e293b;margin-bottom:8px}
.section-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:16px;border-bottom:1px dotted #e2e8f0;padding-bottom:8px}

.config-card{background:#fff;padding:24px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:32px}
.form-label-bold{display:block;font-size:13px;font-weight:700;color:#1e293b;margin-bottom:8px}
.form-select{width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;font-size:14px;outline:none;cursor:pointer}

.list-section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.tabs{display:flex;gap:24px;border-bottom:1px solid #e2e8f0;flex:1}
.tab-item{padding:8px 0;font-size:12px;font-weight:700;color:#94a3b8;cursor:pointer;text-transform:uppercase;position:relative}
.tab-item.active{color:#1e293b}
.tab-item.active:after{content:"";position:absolute;bottom:-1px;left:0;width:100%;height:2px;background:#2563eb}

.search-box{position:relative;margin-left:auto;margin-right:16px}
.search-input{padding:8px 12px 8px 32px;border:1px solid #e2e8f0;border-radius:4px;font-size:13px;width:240px;outline:none;background:#f8fafc}
.create-btn{background:#2563eb;color:#fff;border:none;padding:8px 24px;border-radius:4px;font-size:12px;font-weight:700;cursor:pointer;text-transform:uppercase}

.reason-item{display:flex;align-items:center;padding:16px 0;border-bottom:1px solid #f1f5f9}
.reason-icon{width:40px;height:40px;border-radius:50%;background:#3b82f6;display:flex;align-items:center;justify-content:center;color:#fff;margin-right:16px}
.reason-content{flex:1}
.reason-title{font-size:14px;font-weight:700;color:#1e293b}
.reason-code{font-size:11px;color:#94a3b8;margin-top:2px}
.reason-meta{font-size:11px;color:#94a3b8;margin-top:2px}

/* SWITCH TOGGLE */
.switch { position: relative; display: inline-block; width: 40px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 6px; left: 0; right: 0; bottom: 6px; background-color: #d8e9d8; transition: .3s; border-radius: 12px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 0; bottom: -3px; background-color: #44b92c; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
input:checked + .slider { background-color: #c0e0c0; }
input:checked + .slider:before { transform: translateX(22px); background-color: #44b92c; }
input:not(:checked) + .slider:before { background-color: #94a3b8; }
input:not(:checked) + .slider { background-color: #e2e8f0; }

.more-btn{background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:20px;padding:0 8px}
.action-btn{background:none;border:none;cursor:pointer;color:#9ca3af;padding:6px;border-radius:6px;transition:all 0.2s}
.action-btn:hover{background:#f3f4f6;color:#374151}
.action-btn.delete:hover{color:#ef4444}

/* MODAL */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; width: 450px; border-radius: 12px; overflow: hidden; }
.modal-header { padding: 16px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 20px; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; justify-content: flex-end; }
</style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="eh-content-col">
        <div class="eh-top">
            <div style="position:relative;flex:1;max-width:320px">
                <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:0.4" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search" placeholder="Tìm kiếm trong hệ thống">
            </div>
            <div class="top-actions">
                <button class="top-btn primary" onclick="location.href='/hrm/job-post-create'">⚡ Đăng tin tuyển dụng</button>
                <button class="top-btn">✦ Tạo chiến dịch</button>
                <div class="top-avatar"><?=strtoupper(substr($full_name,0,1))?></div>
            </div>
        </div>

        <main class="eh-main">
            <h1 class="page-title">Lý do từ chối</h1>
            
            <div class="section-label">Quản lý tính năng lý do từ chối</div>
            <div class="config-card">
                <label class="form-label-bold">Lý do từ chối là bắt buộc?</label>
                <select class="form-select" id="mandatorySetting" onchange="updateMandatory()">
                    <option value="0">Không, không bắt buộc</option>
                    <option value="1">Có, bắt buộc</option>
                </select>
            </div>

            <div class="section-label">Quản lý lý do từ chối</div>
            <div class="list-section-header">
                <div class="tabs">
                    <div class="tab-item active" onclick="switchTab(1, this)">Đang sử dụng</div>
                    <div class="tab-item" onclick="switchTab(0, this)">Đang tạm khóa</div>
                </div>
                <div class="search-box">
                    <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#cbd5e1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input class="search-input" placeholder="Tìm kiếm lý do từ chối" onkeyup="filterReasons(this.value)">
                </div>
                <button class="create-btn" onclick="openModal()">Tạo mới</button>
            </div>

            <div id="reasonsList">
                <!-- Content via JS -->
            </div>
        </main>
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="reasonModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Thêm lý do từ chối</h3>
            <button onclick="closeModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Tên lý do *</label>
                <input type="text" id="reasonText" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px" placeholder="Ví dụ: Đã có offer công ty khác">
            </div>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Mã lý do (Slug)</label>
                <input type="text" id="reasonCode" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px" placeholder="ví-du-slug">
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" style="padding:8px 16px;background:#f3f4f6;border:none;border-radius:4px;cursor:pointer">Bỏ qua</button>
            <button onclick="saveReason()" style="padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer">Lưu lại</button>
        </div>
    </div>
</div>

<script>
let currentStatus = 1;
let reasons = [];
let filtered = [];
let currentEditingId = null;

async function loadData() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_rejection_reasons&status=' + currentStatus);
        const result = await res.json();
        if (result.success) {
            reasons = result.data;
            filtered = reasons;
            render();
        }
        
        const settingsRes = await fetch('/hrm/ajax-handler?action=get_settings');
        const settings = await settingsRes.json();
        if (settings) {
            document.getElementById('mandatorySetting').value = settings.rejection_reason_mandatory || 0;
        }
    } catch (e) { console.error(e); }
}

function render() {
    const container = document.getElementById('reasonsList');
    container.innerHTML = filtered.map(r => `
        <div class="reason-item">
            <div class="reason-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>
                </svg>
            </div>
            <div class="reason-content">
                <div class="reason-title">${r.reason_text}</div>
                <div class="reason-code">${r.reason_code}</div>
                <div class="reason-meta">Tạo bởi @${r.created_by} lúc ${formatDate(r.created_at)}</div>
            </div>
            <div style="margin-right:24px">
                <label class="switch">
                    <input type="checkbox" ${r.is_active == 1 ? 'checked' : ''} onchange="toggleReason(${r.id}, this.checked)">
                    <span class="slider"></span>
                </label>
            </div>
            <div style="display:flex; gap:8px">
                <button class="action-btn" onclick="openModal(${r.id})" title="Sửa">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="action-btn delete" onclick="deleteReason(${r.id})" title="Xóa">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');
}

async function deleteReason(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa lý do này?')) return;
    try {
        const res = await fetch('/hrm/ajax-handler?action=delete_rejection_reason', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id})
        });
        const result = await res.json();
        if (result.success) loadData();
    } catch (e) { console.error(e); }
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
}

function filterReasons(q) {
    q = q.toLowerCase();
    filtered = reasons.filter(r => r.reason_text.toLowerCase().includes(q) || r.reason_code.toLowerCase().includes(q));
    render();
}

function switchTab(status, el) {
    currentStatus = status;
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    loadData();
}

async function updateMandatory() {
    const val = document.getElementById('mandatorySetting').value;
    await fetch('/hrm/ajax-handler?action=save_rejection_mandatory', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({mandatory: val})
    });
}

async function toggleReason(id, active) {
    await fetch('/hrm/ajax-handler?action=toggle_rejection_reason', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id, active: active?1:0})
    });
    loadData();
}

function openModal(id = null) {
    currentEditingId = id;
    if (id) {
        const r = reasons.find(x => x.id == id);
        document.getElementById('modalTitle').innerText = 'Sửa lý do từ chối';
        document.getElementById('reasonText').value = r.reason_text;
        document.getElementById('reasonCode').value = r.reason_code;
    } else {
        document.getElementById('modalTitle').innerText = 'Thêm lý do từ chối';
        document.getElementById('reasonText').value = '';
        document.getElementById('reasonCode').value = '';
    }
    document.getElementById('reasonModal').style.display = 'flex';
}

function closeModal() { document.getElementById('reasonModal').style.display = 'none'; }

async function saveReason() {
    const text = document.getElementById('reasonText').value;
    const code = document.getElementById('reasonCode').value;
    if (!text) return alert('Vui lòng nhập tên lý do');
    
    await fetch('/hrm/ajax-handler?action=save_rejection_reason', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: currentEditingId, reason_text: text, reason_code: code})
    });
    closeModal();
    loadData();
}

document.addEventListener('DOMContentLoaded', loadData);
</script>
</body>
</html>
