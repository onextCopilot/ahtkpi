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
<title>Tiêu chí đánh giá ứng viên – E-Hiring</title>
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

.eh-main{flex:1;overflow-y:auto;padding:24px}
.page-title{font-size:20px;font-weight:600;color:#111827;margin-bottom:16px}

.note-pink{background:#fef2f2;border:1px solid #fee2e2;color:#991b1b;padding:12px 16px;border-radius:6px;font-size:13px;margin-bottom:24px}

.add-bar{display:flex;gap:12px;margin-bottom:24px;background:#fff;padding:16px;border-radius:8px;border:1px solid #e5e7eb}
.add-input{flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:14px;outline:none}
.add-select{width:200px;padding:8px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:14px;outline:none;background:#f9fafb}
.add-btn{background:#2563eb;color:#fff;border:none;padding:8px 24px;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer}

.group-container{margin-bottom:16px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;overflow:hidden}
.group-header{display:flex;align-items:center;padding:12px 20px;background:#f9fafb;border-bottom:1px solid #e5e7eb;cursor:pointer}
.group-name{flex:1;font-size:14px;font-weight:700;color:#374151}
.group-actions{display:flex;gap:12px;font-size:12px;color:#9ca3af}
.group-actions span{cursor:pointer}
.group-actions span:hover{color:#2563eb}
.group-actions span.delete:hover{color:#ef4444}

.criterion-item{display:flex;align-items:center;padding:12px 20px;border-bottom:1px solid #f3f4f6}
.criterion-item:last-child{border-bottom:none}
.criterion-text{flex:1;font-size:13px;color:#4b5563;display:flex;align-items:center;gap:12px}
.criterion-select{padding:4px 8px;border:1px solid #e5e7eb;border-radius:4px;font-size:12px;background:#f9fafb;color:#6b7280;outline:none}

.arrow-icon{width:16px;height:16px;margin-right:8px;transition:transform 0.2s}
.group-container.collapsed .arrow-icon{transform:rotate(-90deg)}
.group-container.collapsed .group-body{display:none}

/* MODAL */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; width: 400px; border-radius: 12px; overflow: hidden; }
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
                <input class="eh-search" placeholder="Tìm nhanh trong toàn hệ thống">
            </div>
            <div class="top-actions">
                <button class="top-btn primary" onclick="location.href='/hrm/job-post-create'">⚡ Đăng tin tuyển dụng</button>
                <button class="top-btn">✦ Tạo chiến dịch</button>
                <div class="top-avatar"><?=strtoupper(substr($full_name,0,1))?></div>
            </div>
        </div>

        <main class="eh-main">
            <h1 class="page-title">Tiêu chí đánh giá ứng viên</h1>
            
            <div class="note-pink">
                Tiêu chí đánh giá giúp bạn xây dựng tiêu chí đánh giá ứng viên chính xác. Bạn có thể sắp xếp tiêu chí đánh giá cùng các bộ câu hỏi cho mỗi vị trí tuyển dụng.
            </div>

            <div class="add-bar">
                <input type="text" class="add-input" id="newCriterionText" placeholder="Thêm tiêu chí">
                <select class="add-select" id="newCriterionGroup">
                    <option value="">-- Chọn nhóm --</option>
                    <!-- Groups loaded via JS -->
                </select>
                <button class="add-btn" onclick="addCriterion()">Thêm</button>
            </div>

            <div id="evaluationGroups">
                <!-- Groups loaded via JS -->
            </div>
        </main>
    </div>
</div>

<!-- MODAL FOR GROUP EDIT -->
<div class="modal-overlay" id="groupModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Sửa tên nhóm</h3>
            <button onclick="closeGroupModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <input type="text" id="groupNameInput" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px">
        </div>
        <div class="modal-footer">
            <button onclick="closeGroupModal()" style="padding:8px 16px;background:#f3f4f6;border:none;border-radius:4px;cursor:pointer">Hủy</button>
            <button onclick="updateGroupName()" style="padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer">Lưu</button>
        </div>
    </div>
</div>

<script>
let groups = [];
let editingGroupId = null;

async function loadData() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_evaluation_data');
        const result = await res.json();
        if (result.success) {
            groups = result.data;
            render();
        }
    } catch (e) { console.error(e); }
}

function render() {
    const container = document.getElementById('evaluationGroups');
    const select = document.getElementById('newCriterionGroup');
    
    // Update select
    select.innerHTML = '<option value="">-- Chọn nhóm --</option>' + groups.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
    
    // Render groups
    container.innerHTML = groups.map(g => `
        <div class="group-container" id="group-${g.id}">
            <div class="group-header" onclick="toggleGroup(${g.id})">
                <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                <span class="group-name">${g.name}</span>
                <div class="group-actions">
                    <span onclick="event.stopPropagation(); editGroup(${g.id}, '${g.name}')">Chỉnh sửa</span>
                    <span class="delete" onclick="event.stopPropagation(); deleteGroup(${g.id})">Xóa</span>
                </div>
            </div>
            <div class="group-body">
                ${g.criteria.map(c => `
                    <div class="criterion-item">
                        <div class="criterion-text">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            ${c.criterion_text}
                        </div>
                        <select class="criterion-select" onchange="moveCriterion(${c.id}, this.value)">
                            ${groups.map(gg => `<option value="${gg.id}" ${gg.id == g.id ? 'selected' : ''}>${gg.name}</option>`).join('')}
                        </select>
                        <button onclick="deleteCriterion(${c.id})" style="background:none;border:none;color:#ef4444;margin-left:12px;cursor:pointer;font-size:12px">Xóa</button>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');
}

function toggleGroup(id) {
    document.getElementById('group-' + id).classList.toggle('collapsed');
}

async function addCriterion() {
    const text = document.getElementById('newCriterionText').value;
    const gid = document.getElementById('newCriterionGroup').value;
    if (!text || !gid) return alert('Vui lòng nhập tên tiêu chí và chọn nhóm');
    
    await fetch('/hrm/ajax-handler?action=save_evaluation_criterion', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({group_id: gid, criterion_text: text})
    });
    document.getElementById('newCriterionText').value = '';
    loadData();
}

async function deleteCriterion(id) {
    if (!confirm('Xóa tiêu chí này?')) return;
    await fetch('/hrm/ajax-handler?action=delete_evaluation_criterion', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id})
    });
    loadData();
}

async function moveCriterion(id, gid) {
    await fetch('/hrm/ajax-handler?action=move_criterion', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id, group_id: gid})
    });
    loadData();
}

function editGroup(id, name) {
    editingGroupId = id;
    document.getElementById('groupNameInput').value = name;
    document.getElementById('groupModal').style.display = 'flex';
}

function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

async function updateGroupName() {
    const name = document.getElementById('groupNameInput').value;
    await fetch('/hrm/ajax-handler?action=save_evaluation_group', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: editingGroupId, name})
    });
    closeGroupModal();
    loadData();
}

async function deleteGroup(id) {
    if (!confirm('Xóa nhóm này sẽ xóa tất cả tiêu chí bên trong. Bạn chắc chắn?')) return;
    await fetch('/hrm/ajax-handler?action=delete_evaluation_group', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id})
    });
    loadData();
}

document.addEventListener('DOMContentLoaded', loadData);
</script>
</body>
</html>
