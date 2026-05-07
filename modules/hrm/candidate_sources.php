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
<title>Nguồn ứng viên – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}

.eh-main{flex:1;overflow-y:auto;padding:24px}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.page-title{font-size:20px;font-weight:700;color:#111827}

.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.card-header{padding:16px 20px;border-bottom:1px solid #f3f4f6;background:#f9fafb}
.card-title{font-size:14px;font-weight:700;color:#374151;text-transform:uppercase}

.source-list-header{display:flex;align-items:center;padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
.source-list-header .col-name{flex:1;margin-left:32px}
.source-list-header .col-status{width:80px;text-align:center;margin-right:24px;color:#44b92c}
.source-list-header .col-actions{width:80px;text-align:right}

.source-item{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid #f3f4f6;transition:background 0.2s}
.source-item:hover{background:#f9fafb}
.source-item:last-child{border-bottom:none}
.sort-handle{cursor:grab;color:#d1d5db;margin-right:16px}
.source-info{flex:1}
.source-name{font-size:14px;font-weight:600;color:#111827}
.source-type{font-size:11px;padding:2px 8px;border-radius:4px;background:#eff6ff;color:#2563eb;font-weight:600;margin-left:10px}
.source-type.internal{background:#ecfdf5;color:#059669}
.source-actions{display:flex;gap:8px;width:80px;justify-content:flex-end}
.action-btn{background:none;border:none;cursor:pointer;color:#9ca3af;padding:6px;border-radius:6px;transition:all 0.2s}
.action-btn:hover{background:#f3f4f6;color:#374151}
.action-btn.delete:hover{color:#ef4444}

.add-btn{background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px}

/* MODAL */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; width: 450px; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-header { padding: 16px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 15px; font-weight: 700; color: #374151; }
.modal-close { background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; }
.modal-body { padding: 20px; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; justify-content: flex-end; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.form-input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
.form-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.btn-secondary { background: #f3f4f6; color: #374151; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; }

/* SWITCH TOGGLE - MATCHING SCREENSHOT */
.switch { position: relative; display: inline-block; width: 40px; height: 24px; vertical-align: middle; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 6px; left: 0; right: 0; bottom: 6px; background-color: #d8e9d8; transition: .3s; border-radius: 12px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 0; bottom: -3px; background-color: #44b92c; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
input:checked + .slider { background-color: #c0e0c0; }
input:checked + .slider:before { transform: translateX(22px); background-color: #44b92c; }
input:not(:checked) + .slider:before { background-color: #94a3b8; }
input:not(:checked) + .slider { background-color: #e2e8f0; }

.status-label { font-size: 11px; font-weight: 700; color: #44b92c; text-transform: uppercase; margin-bottom: 4px; display: block; text-align: center; }
</style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="eh-content-col">
        <div class="eh-top">
            <div style="position:relative;flex:1;max-width:320px">
                <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:0.4" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search" placeholder="Tìm kiếm nguồn ứng viên..." onkeyup="filterSources(this.value)">
            </div>
            <div class="top-actions">
                <button class="top-btn primary" onclick="location.href='/hrm/job-post-create'">⚡ Đăng tin tuyển dụng</button>
                <button class="top-btn">✦ Tạo chiến dịch</button>
                <div class="top-avatar"><?php if($avatar): ?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else: ?><?=strtoupper(substr($full_name,0,1))?><?php endif; ?></div>
                <div class="top-user-info"><strong><?=htmlspecialchars($first_name)?></strong>BC Director</div>
            </div>
        </div>

        <main class="eh-main">
            <div class="page-header">
                <h1 class="page-title">Nguồn ứng viên</h1>
                <button class="add-btn" onclick="openModal()">+ Thêm nguồn mới</button>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">Danh sách nguồn ứng viên</span></div>
                <div class="source-list-header">
                    <div class="col-name">Tên nguồn</div>
                    <div class="col-status">Trạng thái</div>
                    <div class="col-actions">Thao tác</div>
                </div>
                <div id="sourceListContainer">
                    <!-- Content loaded via JS -->
                </div>
            </div>
        </main>
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="sourceModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Thêm nguồn mới</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Tên nguồn *</label>
                <input type="text" class="form-input" id="sourceName" placeholder="Ví dụ: LinkedIn, Facebook, VietnamWorks...">
            </div>
            <div class="form-group">
                <label class="form-label">Loại nguồn</label>
                <select class="form-input" id="sourceType">
                    <option value="external">External (Bên ngoài)</option>
                    <option value="internal">Internal (Nội bộ)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Trạng thái mặc định</label>
                <select class="form-input" id="sourceActive">
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal()">Bỏ qua</button>
            <button class="btn-primary" onclick="saveSource()">Lưu lại</button>
        </div>
    </div>
</div>

<script>
let sources = [];
let filteredSources = [];
let currentEditingId = null;

async function fetchSources() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_candidate_sources');
        const result = await res.json();
        if (result.success) {
            sources = result.data;
            filteredSources = sources;
            renderSources();
        }
    } catch (error) { console.error('Error fetching sources:', error); }
}

function filterSources(q) {
    q = q.toLowerCase();
    filteredSources = sources.filter(s => s.name.toLowerCase().includes(q));
    renderSources();
}

function renderSources() {
    const container = document.getElementById('sourceListContainer');
    container.innerHTML = filteredSources.map(s => `
        <div class="source-item" data-id="${s.id}">
            <div class="sort-handle">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                    <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                </svg>
            </div>
            <div class="source-info">
                <span class="source-name">${s.name}</span>
                <span class="source-type ${s.type}">${s.type === 'internal' ? 'Internal' : 'External'}</span>
            </div>
            <div style="width: 80px; margin-right: 24px; display: flex; align-items: center; justify-content: center;">
                <label class="switch">
                    <input type="checkbox" ${s.is_active == 1 ? 'checked' : ''} onchange="toggleActive(${s.id}, this.checked)">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="source-actions">
                <button class="action-btn" onclick="openModal(${s.id})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="action-btn delete" onclick="deleteSource(${s.id})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');

    new Sortable(container, {
        handle: '.sort-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: async () => {
            const items = container.querySelectorAll('.source-item');
            const order = Array.from(items).map(i => i.getAttribute('data-id'));
            await fetch('/hrm/ajax-handler?action=update_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'source', order: order })
            });
        }
    });
}

async function toggleActive(id, active) {
    try {
        await fetch('/hrm/ajax-handler?action=toggle_active', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, active: active ? 1 : 0 })
        });
        // Update local data without re-rendering everything if possible, 
        // but re-fetching is safer for sync
        const s = sources.find(x => x.id == id);
        if (s) s.is_active = active ? 1 : 0;
    } catch (error) { console.error('Error toggling status:', error); }
}

function openModal(id = null) {
    currentEditingId = id;
    const title = document.getElementById('modalTitle');
    if (id) {
        title.innerText = 'Sửa nguồn ứng viên';
        const s = sources.find(x => x.id == id);
        document.getElementById('sourceName').value = s.name;
        document.getElementById('sourceType').value = s.type;
        document.getElementById('sourceActive').value = s.is_active;
    } else {
        title.innerText = 'Thêm nguồn mới';
        document.getElementById('sourceName').value = '';
        document.getElementById('sourceType').value = 'external';
        document.getElementById('sourceActive').value = '1';
    }
    document.getElementById('sourceModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('sourceModal').style.display = 'none';
}

async function saveSource() {
    const name = document.getElementById('sourceName').value;
    const type = document.getElementById('sourceType').value;
    const is_active = document.getElementById('sourceActive').value;
    if (!name) return alert('Vui lòng nhập tên nguồn');

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_candidate_source', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentEditingId, name, type, is_active })
        });
        const result = await res.json();
        if (result.success) {
            await fetchSources();
            closeModal();
        }
    } catch (error) { console.error('Error saving source:', error); }
}

async function deleteSource(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa nguồn này?')) return;
    try {
        const res = await fetch('/hrm/ajax-handler?action=delete_candidate_source', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.success) await fetchSources();
    } catch (error) { console.error('Error deleting source:', error); }
}

document.addEventListener('DOMContentLoaded', fetchSources);
</script>
</body>
</html>
