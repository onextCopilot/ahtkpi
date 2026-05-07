<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$_p = explode(' ', trim($full_name));
$first_name = end($_p);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách mẫu email – E-Hiring</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/modules/hrm/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1a1a2e; height: 100vh; overflow: hidden; }
        .eh-wrapper { display: flex; height: 100vh; overflow: hidden; }
        .eh-content-col { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Top Navigation */
        .eh-top { height: 48px; background: #0a252a; display: flex; align-items: center; padding: 0 16px; gap: 12px; flex-shrink: 0; border-bottom: 1px solid #123a41; }
        .eh-search-wrap { position: relative; flex: 1; max-width: 320px; }
        .eh-search-wrap svg { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); opacity: 0.4; }
        .eh-search { width: 100%; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px; padding: 6px 12px 6px 32px; color: #fff; font-size: 13px; outline: none; }
        .eh-search::placeholder { color: rgba(255, 255, 255, 0.5); }
        .eh-search:focus { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.3); }
        .top-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .top-btn { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }
        .top-btn:hover { background: rgba(255, 255, 255, 0.15); }
        .top-avatar { width: 32px; height: 32px; border-radius: 50%; background: #0ea5e9; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #fff; overflow: hidden; }
        .top-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .top-user-info { font-size: 11px; color: rgba(255, 255, 255, 0.7); line-height: 1.3; }
        .top-user-info strong { display: block; color: #fff; font-size: 12px; }

        /* Main Content */
        .eh-main { flex: 1; overflow-y: auto; background: #fff; display: flex; flex-direction: column; }
        .header-section { padding: 24px 32px 0 32px; flex-shrink: 0; }
        .page-title { font-size: 22px; font-weight: 600; color: #111827; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .btn-add { background: #3b82f6; color: #fff; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
        .btn-add:hover { background: #2563eb; }

        /* Tabs & Search */
        .tabs-row { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #e5e7eb; padding-bottom: 0; margin-bottom: 0; }
        .tabs { display: flex; gap: 24px; }
        .tab-item { font-size: 11px; font-weight: 600; color: #9ca3af; padding-bottom: 12px; cursor: pointer; text-transform: uppercase; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab-item:hover { color: #4b5563; }
        .tab-item.active { color: #374151; border-bottom-color: #374151; }
        
        .list-search { position: relative; margin-bottom: 8px; }
        .list-search svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .list-search input { padding: 8px 12px 8px 32px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 13px; width: 240px; outline: none; background: #f9fafb; }
        .list-search input:focus { border-color: #d1d5db; background: #fff; }

        /* List Area */
        .list-container { padding: 0 32px 32px 32px; overflow-y: auto; flex: 1; }
        .tpl-item { display: flex; align-items: flex-start; padding: 16px 0; border-bottom: 1px solid #f3f4f6; transition: background 0.15s; text-decoration: none; color: inherit; }
        .tpl-item:hover { background: #f9fafb; }
        .tpl-icon { width: 40px; height: 40px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 16px; color: #9ca3af; flex-shrink: 0; }
        .tpl-icon svg { width: 20px; height: 20px; }
        .tpl-content { flex: 1; min-width: 0; padding-right: 16px; }
        .tpl-name { font-size: 15px; font-weight: 600; color: #3b82f6; margin-bottom: 4px; display: inline-flex; align-items: center; gap: 8px; }
        .tpl-name:hover { text-decoration: underline; }
        .tpl-desc { font-size: 13px; color: #6b7280; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 6px; }
        .tpl-desc strong { color: #4b5563; }
        .tpl-meta { font-size: 12px; color: #9ca3af; display: flex; align-items: center; gap: 4px; }
        
        /* Actions */
        .tpl-actions { display: flex; align-items: center; gap: 16px; flex-shrink: 0; padding-top: 4px; }
        
        /* Toggle Switch */
        .switch { position: relative; display: inline-block; width: 36px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(16px); }

        /* Favorite Star */
        .star-btn { color: #d1d5db; cursor: pointer; transition: color 0.2s; display: flex; align-items: center; }
        .star-btn:hover { color: #fbbf24; }
        .star-btn.active { color: #fbbf24; fill: #fbbf24; }

        .empty-state { padding: 40px; text-align: center; color: #6b7280; font-size: 14px; }
        
        /* Toast */
        .toast { position: fixed; bottom: 24px; right: 24px; background: #111827; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 13px; z-index: 9999; display: none; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="eh-content-col">
        <!-- Top bar -->
        <div class="eh-top">
            <div class="eh-search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search" placeholder="Tìm kiếm trong toàn hệ thống">
            </div>
            <div class="top-actions">
                <button class="top-btn" style="background:#0ea5e9;border-color:#0ea5e9">⚡ Đăng tin tuyển dụng</button>
                <div class="top-avatar">
                    <?php if ($avatar): ?>
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($full_name, 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="top-user-info">
                    <strong><?= htmlspecialchars($first_name) ?></strong>
                    BC Director
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="eh-main">
            <div class="header-section">
                <div class="page-title">
                    Danh sách mẫu email
                    <a href="/hrm/email-template-detail" class="btn-add">THÊM MẪU EMAIL MỚI</a>
                </div>
                
                <div class="tabs-row">
                    <div class="tabs">
                        <div class="tab-item active" onclick="setFilter('active')" id="tab-active">ĐANG SỬ DỤNG (0)</div>
                        <div class="tab-item" onclick="setFilter('mine')" id="tab-mine">TẠO BỞI TÔI (0)</div>
                        <div class="tab-item" onclick="setFilter('inactive')" id="tab-inactive">ĐANG TẠM KHOÁ (0)</div>
                    </div>
                    <div class="list-search">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="searchInput" placeholder="Tìm nhanh mẫu email" oninput="filterLocal()">
                    </div>
                </div>
            </div>

            <div class="list-container" id="template-list">
                <div class="empty-state">Đang tải dữ liệu...</div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast">Cập nhật thành công</div>

<script>
let currentFilter = 'active';
let allData = [];

function setFilter(filter) {
    document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
    document.getElementById(`tab-${filter}`).classList.add('active');
    currentFilter = filter;
    loadTemplates();
}

async function loadTemplates() {
    try {
        const res = await fetch(`/hrm/ajax-handler?action=get_email_templates&filter=${currentFilter}`);
        const r = await res.json();
        if (r.success) {
            allData = r.data;
            updateTabCounts(allData.length);
            renderList(allData);
        }
    } catch (e) {
        console.error(e);
        document.getElementById('template-list').innerHTML = '<div class="empty-state">Lỗi kết nối</div>';
    }
}

function updateTabCounts(count) {
    // We only update the current tab count for simplicity, real app would fetch all 3 counts
    const tab = document.getElementById(`tab-${currentFilter}`);
    const name = currentFilter === 'active' ? 'ĐANG SỬ DỤNG' : (currentFilter === 'mine' ? 'TẠO BỞI TÔI' : 'ĐANG TẠM KHOÁ');
    tab.textContent = `${name} (${count})`;
}

function filterLocal() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    if (!q) {
        renderList(allData);
        return;
    }
    const filtered = allData.filter(t => t.name.toLowerCase().includes(q) || (t.email_subject||'').toLowerCase().includes(q));
    renderList(filtered);
}

function renderList(list) {
    const container = document.getElementById('template-list');
    if (!list.length) {
        container.innerHTML = '<div class="empty-state">Không có mẫu email nào.</div>';
        return;
    }

    container.innerHTML = list.map(t => {
        const date = new Date(t.created_at).toLocaleString('vi-VN', {hour:'2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'});
        
        // Strip HTML from body for preview
        let bodyPreview = t.email_body || '';
        bodyPreview = bodyPreview.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (bodyPreview.length > 80) bodyPreview = bodyPreview.substring(0, 80) + '...';

        return `
            <div class="tpl-item">
                <div class="tpl-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div class="tpl-content">
                    <a href="/hrm/email-template-detail?id=${t.id}" class="tpl-name">${escHtml(t.name)}</a>
                    <div class="tpl-desc">
                        <strong>${escHtml(t.email_subject || 'Không có tiêu đề')}</strong> &mdash; ${escHtml(bodyPreview)}
                    </div>
                    <div class="tpl-meta">
                        Tạo bởi @${escHtml(t.creator_username || 'unknown')} lúc ${date} &middot; 0 Vị trí công việc
                    </div>
                </div>
                <div class="tpl-actions">
                    <div class="star-btn ${t.is_favorite == 1 ? 'active' : ''}" onclick="toggleFavorite(${t.id}, ${t.is_favorite == 1 ? 0 : 1})">
                        <svg width="18" height="18" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="${t.is_favorite == 1 ? 'currentColor' : 'none'}"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <label class="switch">
                        <input type="checkbox" ${t.is_active == 1 ? 'checked' : ''} onchange="toggleActive(${t.id}, this.checked ? 1 : 0)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        `;
    }).join('');
}

async function toggleActive(id, isActive) {
    try {
        const res = await fetch('/hrm/ajax-handler?action=toggle_email_template', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, is_active: isActive})
        });
        const r = await res.json();
        if (r.success) {
            showToast('Cập nhật trạng thái thành công');
            setTimeout(loadTemplates, 500);
        } else {
            showToast('Lỗi: ' + (r.message || 'Không thể cập nhật'), 'error');
            loadTemplates(); // revert
        }
    } catch (e) {
        showToast('Lỗi kết nối', 'error');
        loadTemplates(); // revert
    }
}

async function toggleFavorite(id, isFav) {
    try {
        const res = await fetch('/hrm/ajax-handler?action=toggle_email_template', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, is_favorite: isFav})
        });
        const r = await res.json();
        if (r.success) {
            loadTemplates();
        } else {
            showToast('Lỗi: ' + (r.message || 'Không thể cập nhật'), 'error');
        }
    } catch (e) {
        showToast('Lỗi kết nối', 'error');
    }
}

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = type === 'error' ? '#ef4444' : '#111827';
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

function escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', loadTemplates);
</script>
</body>
</html>
