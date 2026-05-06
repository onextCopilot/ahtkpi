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
<title>Phân quyền sử dụng – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-inner-body{display:flex;flex:1;overflow-y:auto;padding:24px;flex-direction:column;gap:24px}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}

.page-header{margin-bottom:24px}
.page-title{font-size:20px;font-weight:700;color:#111827}
.page-desc{font-size:13px;color:#6b7280;margin-top:4px}

.permission-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.card-header{padding:20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:flex-start}
.role-title{font-size:16px;font-weight:700;color:#111827}
.role-desc{font-size:12px;color:#6b7280;margin-top:2px}
.create-btn{border:1px solid #2563eb;color:#2563eb;background:none;padding:8px 16px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}

.user-list{padding:0}
.user-item{display:flex;align-items:center;padding:12px 20px;border-bottom:1px solid #f9fafb;transition:background 0.2s}
.user-item:hover{background:#f9fafb}
.user-avatar{width:36px;height:36px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6b7280;overflow:hidden;margin-right:12px}
.user-avatar img{width:100%;height:100%;object-fit:cover}
.user-info{flex:1}
.user-name{font-size:14px;font-weight:600;color:#111827}
.user-dept{font-size:11px;color:#9ca3af}
.role-badge{background:#fff;border:1px solid #e5e7eb;padding:4px 12px;border-radius:4px;font-size:11px;font-weight:600;color:#6b7280;cursor:pointer}
.remove-btn{color:#ef4444;font-size:16px;background:none;border:none;cursor:pointer;margin-left:12px;opacity:0;transition:opacity 0.2s}
.user-item:hover .remove-btn{opacity:1}

.add-user-bar{padding:12px 20px;background:#f9fafb;display:flex;align-items:center;gap:12px;border-top:1px solid #f3f4f6;position:relative}
.add-icon{color:#9ca3af}
.add-input{flex:1;background:none;border:none;outline:none;font-size:13px;color:#111827}
.add-input::placeholder{color:#9ca3af}
.submit-add-btn{background:#3b82f6;color:#fff;border:none;padding:6px 16px;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer}

.search-results{position:absolute;bottom:100%;left:0;width:100%;background:#fff;border:1px solid #e5e7eb;box-shadow:0 -4px 12px rgba(0,0,0,0.1);max-height:200px;overflow-y:auto;display:none;z-index:10}
.search-item{display:flex;align-items:center;padding:10px 20px;cursor:pointer;border-bottom:1px solid #f3f4f6}
.search-item:hover{background:#f9fafb}
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
                <button class="top-btn">✦ Tạo chiến dịch</button>
                <button class="top-btn">🌐 Trang tuyển dụng</button>
                <div class="top-avatar"><?php if($avatar): ?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else: ?><?=strtoupper(substr($full_name,0,1))?><?php endif; ?></div>
                <div class="top-user-info"><strong><?=htmlspecialchars($first_name)?></strong>BC Director</div>
            </div>
        </div>

        <div class="eh-inner-body">
            <div class="page-header">
                <h1 class="page-title">Phân quyền sử dụng</h1>
                <p class="page-desc">Quản lý danh sách thành viên tham gia vào quy trình tuyển dụng của công ty.</p>
            </div>

            <!-- HR MANAGERS SECTION -->
            <div class="permission-card">
                <div class="card-header">
                    <div>
                        <div class="role-title">HR managers</div>
                        <div class="role-desc">Thành viên quản trị hệ thống, có thể xem thông tin toàn bộ ứng viên và các Talent pool</div>
                    </div>
                </div>
                <div class="user-list" id="list-manager"></div>
                <div class="add-user-bar">
                    <span class="add-icon">+</span>
                    <input type="text" class="add-input" placeholder="Thêm HR manager (gõ @ để gắn thẻ)" onkeyup="handleSearch(event, 'manager')">
                    <button class="submit-add-btn">Thêm</button>
                    <div class="search-results" id="search-manager"></div>
                </div>
            </div>

            <!-- HR EXECUTIVES SECTION -->
            <div class="permission-card">
                <div class="card-header">
                    <div>
                        <div class="role-title">HR executives</div>
                        <div class="role-desc">Thành viên thuộc bộ phận nhân sự, có thể xem và quản lý các vị trí tuyển dụng mình phụ trách</div>
                    </div>
                </div>
                <div class="user-list" id="list-executive"></div>
                <div class="add-user-bar">
                    <span class="add-icon">+</span>
                    <input type="text" class="add-input" placeholder="Thêm HR executive (gõ @ để gắn thẻ)" onkeyup="handleSearch(event, 'executive')">
                    <button class="submit-add-btn">Thêm</button>
                    <div class="search-results" id="search-executive"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function loadPermissions() {
    const res = await fetch('/hrm/ajax-handler?action=get_permissions');
    const result = await res.json();
    if (result.success) {
        renderList('manager', result.data.manager);
        renderList('executive', result.data.executive);
    }
}

function renderList(role, users) {
    const container = document.getElementById('list-' + role);
    container.innerHTML = users.map(u => `
        <div class="user-item">
            <div class="user-avatar">${u.avatar ? `<img src="${u.avatar}">` : u.full_name[0].toUpperCase()}</div>
            <div class="user-info">
                <div class="user-name">${u.full_name}</div>
                <div class="user-dept">${u.email}</div>
            </div>
            <div class="role-badge">${role === 'manager' ? 'HR Manager' : 'HR Executive'}</div>
            <button class="remove-btn" onclick="removePermission(${u.user_id}, '${role}')">&times;</button>
        </div>
    `).join('');
}

async function handleSearch(e, role) {
    const val = e.target.value;
    const resultsDiv = document.getElementById('search-' + role);
    if (!val.includes('@')) { resultsDiv.style.display = 'none'; return; }
    
    const query = val.split('@').pop();
    if (query.length < 2) return;

    const res = await fetch('/hrm/ajax-handler?action=search_users&q=' + encodeURIComponent(query));
    const result = await res.json();
    if (result.success && result.data.length > 0) {
        resultsDiv.style.display = 'block';
        resultsDiv.innerHTML = result.data.map(u => `
            <div class="search-item" onclick="addPermission(${u.id}, '${role}', '${role}')">
                <div class="user-avatar" style="width:24px;height:24px;font-size:10px">${u.avatar ? `<img src="${u.avatar}">` : u.full_name[0].toUpperCase()}</div>
                <span style="font-size:12px">${u.full_name}</span>
            </div>
        `).join('');
    }
}

async function addPermission(userId, role, section) {
    const res = await fetch('/hrm/ajax-handler?action=add_permission', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, role: role })
    });
    const result = await res.json();
    if (result.success) {
        document.querySelectorAll('.add-input').forEach(i => i.value = '');
        document.querySelectorAll('.search-results').forEach(r => r.style.display = 'none');
        loadPermissions();
    }
}

async function removePermission(userId, role) {
    const res = await fetch('/hrm/ajax-handler?action=remove_permission', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, role: role })
    });
    const result = await res.json();
    if (result.success) loadPermissions();
}

document.addEventListener('DOMContentLoaded', loadPermissions);
</script>
</body>
</html>
