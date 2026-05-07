<?php
ob_start();
file_put_contents(__DIR__ . '/../../debug_file.log', date('H:i:s') . " Executing system_settings.php\n", FILE_APPEND);
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { 
    file_put_contents(__DIR__ . '/../../debug_file.log', date('H:i:s') . " NO SESSION - redirecting to login\n", FILE_APPEND);
    header("Location: /login"); exit(); 
}
file_put_contents(__DIR__ . '/../../debug_file.log', date('H:i:s') . " SESSION OK user_id=" . $_SESSION['user_id'] . "\n", FILE_APPEND);
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$_name_parts = explode(' ', trim($full_name));
$first_name = end($_name_parts);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cài đặt hệ thống – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

.eh-main{flex:1;overflow-y:auto;padding:32px;background:#fff;display:flex;justify-content:center}
.eh-container{width:100%;max-width:900px}
.page-title{font-size:20px;font-weight:700;color:#111827;margin-bottom:32px}

.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:24px;overflow:hidden}
.card-header{padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:flex-start}
.card-title-group{flex:1}
.card-title{font-size:16px;font-weight:700;color:#111827;margin-bottom:4px}
.card-subtitle{font-size:13px;color:#6b7280}
.btn-outline{background:#fff;border:1px solid #2563eb;color:#2563eb;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.btn-outline:hover{background:#f0f7ff}

.pool-list{display:flex;flex-direction:column}
.pool-item{padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:16px}
.pool-item:last-child{border-bottom:none}
.pool-icon{width:40px;height:40px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af}
.pool-info{flex:1}
.pool-name{font-size:15px;font-weight:600;color:#111827;margin-bottom:4px}
.pool-meta{font-size:12px;color:#9ca3af}
.pool-actions{display:flex;gap:12px}
.action-btn{background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px}
.action-btn:hover{color:#2563eb}

/* MODAL */
.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:1000;display:none;align-items:center;justify-content:center}
.modal{background:#fff;width:500px;border-radius:10px;overflow:hidden;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1)}
.modal-header{padding:20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:16px;font-weight:700;color:#111827}
.modal-close{font-size:24px;color:#9ca3af;cursor:pointer}
.modal-body{padding:20px}
.modal-footer{padding:16px 20px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:12px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.form-input{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;outline:none}
.btn-grey{background:#f3f4f6;border:none;color:#374151;padding:10px 20px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.btn-blue{background:#2563eb;border:none;color:#fff;padding:10px 20px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
</style>
</head>
<body>
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
                <div class="top-avatar"><?php if($avatar): ?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else: ?><?=strtoupper(substr($full_name,0,1))?><?php endif; ?></div>
                <div class="top-user-info"><strong><?=htmlspecialchars($first_name)?></strong>BC Director</div>
            </div>
        </div>

        <main class="eh-main">
            <div class="eh-container">
                <h1 class="page-title">Cài đặt hệ thống</h1>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <h2 class="card-title">Talent pools</h2>
                            <p class="card-subtitle">Talent pool giúp lưu trữ thông tin ứng viên hiệu quả, hỗ trợ các hoạt động email marketing sau này</p>
                        </div>
                        <button class="btn-outline" onclick="openModal()">Thêm Talent pool</button>
                    </div>
                    <div class="pool-list" id="pool-list">
                        <!-- Loaded via JS -->
                    </div>
                </div>


            </div>
        </main>
    </div>
</div>

<!-- MODAL ADD/EDIT POOL -->
<div class="modal-overlay" id="pool-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modal-title">Thêm Talent pool</div>
            <div class="modal-close" onclick="closeModal()">&times;</div>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pool-id">
            <div class="form-group">
                <label class="form-label">Tên Talent pool *</label>
                <input type="text" class="form-input" id="pool-name" placeholder="Ví dụ: Pending Pool">
            </div>
            <div class="form-group">
                <label class="form-label">Mô tả</label>
                <textarea class="form-input" id="pool-desc" style="height:100px" placeholder="Mô tả về pool này..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-grey" onclick="closeModal()">Bỏ qua</button>
            <button class="btn-blue" onclick="savePool()">Lưu lại</button>
        </div>
    </div>
</div>

<script>
let talentPools = [];

async function fetchPools() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_talent_pools');
        const result = await res.json();
        if (result.success) {
            talentPools = result.data;
            renderPools();
        }
    } catch (e) { console.error(e); }
}

function renderPools() {
    const list = document.getElementById('pool-list');
    if (!talentPools.length) {
        list.innerHTML = '<div style="padding:40px; text-align:center; color:#9ca3af">Chưa có Talent pool nào.</div>';
        return;
    }
    list.innerHTML = talentPools.map(p => `
        <div class="pool-item">
            <div class="pool-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
            </div>
            <div class="pool-info">
                <div class="pool-name">${p.name}</div>
                <div class="pool-meta">Tạo bởi @${p.creator_username || 'system'} - ${formatTime(p.created_at)} - ${formatDate(p.created_at)}</div>
            </div>
            <div class="pool-actions">
                <button class="action-btn" onclick="editPool(${p.id})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="action-btn" onclick="deletePool(${p.id})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

function openModal() {
    document.getElementById('pool-id').value = '';
    document.getElementById('pool-name').value = '';
    document.getElementById('pool-desc').value = '';
    document.getElementById('modal-title').innerText = 'Thêm Talent pool';
    document.getElementById('pool-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('pool-modal').style.display = 'none';
}

function editPool(id) {
    const pool = talentPools.find(p => p.id == id);
    if (!pool) return;
    document.getElementById('pool-id').value = pool.id;
    document.getElementById('pool-name').value = pool.name;
    document.getElementById('pool-desc').value = pool.description || '';
    document.getElementById('modal-title').innerText = 'Sửa Talent pool';
    document.getElementById('pool-modal').style.display = 'flex';
}

async function savePool() {
    const id = document.getElementById('pool-id').value;
    const name = document.getElementById('pool-name').value;
    const description = document.getElementById('pool-desc').value;

    if (!name) return alert('Vui lòng nhập tên Talent pool');

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_talent_pool', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name, description })
        });
        const result = await res.json();
        if (result.success) {
            closeModal();
            fetchPools();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (e) { console.error(e); }
}

async function deletePool(id) {
    if (!confirm('Bạn có chắc muốn xóa Talent pool này?')) return;
    try {
        const res = await fetch('/hrm/ajax-handler?action=delete_talent_pool', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.success) {
            fetchPools();
        }
    } catch (e) { console.error(e); }
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.getDate().toString().padStart(2, '0') + '/' + (d.getMonth() + 1).toString().padStart(2, '0') + '/' + d.getFullYear();
}

document.addEventListener('DOMContentLoaded', fetchPools);
</script>
</body>
</html>
