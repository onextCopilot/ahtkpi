<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$_p = explode(' ', trim($full_name)); $first_name = end($_p);
$template_id = (int)($_GET['id'] ?? 0);
$is_edit = $template_id > 0;
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $is_edit ? 'Chỉnh sửa mẫu phỏng vấn' : 'Thêm mẫu phỏng vấn' ?> – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
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
.eh-main{flex:1;overflow-y:auto;background:#f8fafc;display:flex;gap:0}

/* Left form area */
.form-area{flex:1;overflow-y:auto;padding:32px}
.breadcrumb{font-size:12px;color:#9ca3af;margin-bottom:16px}
.breadcrumb a{color:#6b7280;text-decoration:none}
.breadcrumb a:hover{color:#374151}
.page-title{font-size:22px;font-weight:700;color:#111827;margin-bottom:8px}
.section-title{font-size:15px;font-weight:600;color:#1d4ed8;margin-bottom:24px}
.form-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:28px;margin-bottom:20px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.form-label span.req{color:#ef4444}
.form-input{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;outline:none;transition:border .2s}
.form-input:focus{border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(29,78,216,.08)}
.form-select{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;outline:none;background:#fff;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.form-select:focus{border-color:#1d4ed8}
.form-hint{font-size:11px;color:#9ca3af;margin-top:4px}
.editor-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;display:block}
.editor-wrap{border:1px solid #d1d5db;border-radius:6px;overflow:hidden}

/* Checkbox inline */
.checkbox-row{display:flex;align-items:center;gap:8px;margin-top:8px}
.checkbox-row input{width:16px;height:16px;cursor:pointer;accent-color:#1d4ed8}
.checkbox-row label{font-size:13px;color:#374151;cursor:pointer}

/* Bottom actions */
.form-actions{background:#fff;border-top:1px solid #e5e7eb;padding:16px 28px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
.btn-cancel{background:#fff;border:1px solid #d1d5db;color:#374151;padding:10px 20px;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none}
.btn-save{background:#22c55e;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.btn-save:hover{background:#16a34a}
.btn-preview{background:#fff;border:1px solid #d1d5db;color:#374151;padding:10px 20px;border-radius:6px;font-size:13px;cursor:pointer}

/* Right sidebar - variables */
.vars-sidebar{width:300px;flex-shrink:0;overflow-y:auto;background:#fff;border-left:1px solid #e5e7eb;padding:20px}
.vars-header{font-size:12px;font-weight:700;color:#374151;letter-spacing:.05em;margin-bottom:8px}
.vars-intro{font-size:11px;color:#ef4444;margin-bottom:16px;line-height:1.5}
.var-group{margin-bottom:8px}
.var-tag{display:inline-block;background:#eff6ff;color:#1d4ed8;font-family:monospace;font-size:11px;padding:2px 8px;border-radius:4px;cursor:pointer;margin-bottom:4px;transition:background .15s}
.var-tag:hover{background:#dbeafe}
.var-desc{font-size:11px;color:#6b7280;margin-bottom:12px;line-height:1.4}
.var-example{font-size:10px;color:#9ca3af;font-style:italic}
hr.vars-divider{border:none;border-top:1px solid #f3f4f6;margin:12px 0}

/* Multi-select participants */
.multiselect-wrap{border:1px solid #d1d5db;border-radius:6px;background:#fff;position:relative;transition:border .2s}
.multiselect-wrap:focus-within{border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(29,78,216,.08)}
.multiselect-tags{display:flex;flex-wrap:wrap;gap:6px;padding:8px 10px;min-height:44px;cursor:text;align-items:center}
.multiselect-input{border:none;outline:none;font-size:14px;flex:1;min-width:160px;font-family:inherit;color:#374151}
.tag-item{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1d4ed8;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;flex-shrink:0}
.tag-item img{width:20px;height:20px;border-radius:50%;object-fit:cover}
.tag-item .tag-avatar-placeholder{width:20px;height:20px;border-radius:50%;background:#1d4ed8;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
.tag-remove{cursor:pointer;opacity:.5;font-size:14px;line-height:1;padding:0 2px}
.tag-remove:hover{opacity:1;color:#ef4444}
.multiselect-dropdown{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:999;max-height:260px;overflow-y:auto;margin-top:4px}
.dropdown-item{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;transition:background .15s}
.dropdown-item:hover{background:#f5f8ff}
.dropdown-item.selected{background:#eff6ff}
.dropdown-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0}
.dropdown-avatar-placeholder{width:32px;height:32px;border-radius:50%;background:#1d4ed8;color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.dropdown-info{flex:1;min-width:0}
.dropdown-name{font-size:13px;font-weight:600;color:#111827}
.dropdown-email{font-size:11px;color:#9ca3af}
.dropdown-role{font-size:10px;background:#e0f2fe;color:#0284c7;padding:1px 6px;border-radius:10px;font-weight:600}
.dropdown-empty{padding:16px;text-align:center;color:#9ca3af;font-size:13px}
.dropdown-loading{padding:16px;text-align:center;color:#9ca3af;font-size:13px}

.toast{position:fixed;bottom:24px;right:24px;background:#111827;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:9999;display:none;animation:slideUp .3s ease}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
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
                <button class="top-btn" style="background:#0ea5e9;border-color:#0ea5e9">⚡ Đăng tin tuyển dụng</button>
                <div class="top-avatar"><?php if($avatar):?><img src="<?=htmlspecialchars($avatar)?>" alt=""><?php else:?><?=strtoupper(substr($full_name,0,1))?><?php endif;?></div>
                <div class="top-user-info"><strong><?=htmlspecialchars($first_name)?></strong>BC Director</div>
            </div>
        </div>

        <div class="eh-main">
            <div class="form-area">
                <div class="breadcrumb">
                    <a href="/hrm/interview-templates">Mẫu phỏng vấn</a> &rsaquo;
                    <?= $is_edit ? 'Chỉnh sửa mẫu phỏng vấn' : 'Thêm mẫu phỏng vấn mới' ?>
                </div>
                <h1 class="page-title">Mẫu phỏng vấn</h1>
                <p class="section-title"><?= $is_edit ? 'Chỉnh sửa mẫu phỏng vấn' : 'Tạo mẫu phỏng vấn mới' ?></p>

                <div class="form-card">
                    <input type="hidden" id="template-id" value="<?= $template_id ?>">

                    <div class="form-group">
                        <label class="form-label">Tên <span class="req">*</span></label>
                        <input type="text" class="form-input" id="tpl-name" placeholder="Ví dụ: OS MBFS - MẪU THƯ MỜI PHỎNG VẤN">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Loại phỏng vấn <span class="req">*</span></label>
                        <select class="form-select" id="tpl-type">
                            <option value="onsite">Phỏng vấn tại văn phòng (phỏng vấn tại văn phòng)</option>
                            <option value="phone">Phỏng vấn qua điện thoại (phỏng vấn qua điện thoại)</option>
                            <option value="video">Phỏng vấn video (phỏng vấn online qua Video Conference)</option>
                            <option value="online">Phỏng vấn trực tuyến (phỏng vấn online qua Zoom, Google Meet, Skype, Vibe,...)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Thành viên tham gia phỏng vấn <span class="req">*</span></label>
                        <div class="multiselect-wrap" id="participants-wrap">
                            <div class="multiselect-tags" id="participants-tags">
                                <input type="text" class="multiselect-input" id="participants-search"
                                    placeholder="Tìm theo tên hoặc email..." autocomplete="off"
                                    oninput="searchHRManagers(this.value)" onfocus="showDropdown()" onblur="hideDropdown()">
                            </div>
                            <div class="multiselect-dropdown" id="participants-dropdown" style="display:none"></div>
                        </div>
                        <input type="hidden" id="tpl-participants">
                        <div class="form-hint">Chỉ HR Managers và HR Executives được phân quyền trong hệ thống</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Địa điểm phỏng vấn</label>
                        <input type="text" class="form-input" id="tpl-location" placeholder="Ví dụ: Tầng 9, Tòa nhà 5 Điện Biên Phủ, Ba Đình, Hà Nội">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tiêu đề mail</label>
                        <input type="text" class="form-input" id="tpl-subject" placeholder="Ví dụ: AHT TECH - THƯ MỜI PHỎNG VẤN VỊ TRÍ {job} - VÒNG PHỎNG VẤN KHÁCH HÀNG">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-row">
                            <input type="checkbox" id="tpl-notify" checked>
                            <label for="tpl-notify">Nhắc nhở để thêm lịch</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <span class="editor-label">Nội dung email được gửi tự động đến ứng viên để nhắc nhở về thời gian phỏng vấn</span>
                        <div class="editor-wrap">
                            <textarea id="tpl-body" style="min-height:300px;width:100%"></textarea>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0">
                        <span class="editor-label">Câu hỏi phỏng vấn được chuẩn bị trước (không bắt buộc)</span>
                        <div class="form-hint" style="margin-bottom:8px">Nội dung chi tiết các câu hỏi phỏng vấn bạn hoặc những người tham gia sẽ dùng hỏi ứng viên (các nội dung này sẽ KHÔNG gửi kèm email)</div>
                        <div class="editor-wrap">
                            <textarea id="tpl-questions" style="min-height:200px;width:100%"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="/hrm/interview-templates" class="btn-cancel">Gửi thử email này</a>
                    <div style="display:flex;gap:12px">
                        <a href="/hrm/interview-templates" class="btn-cancel">Bỏ qua</a>
                        <button class="btn-save" onclick="saveTemplate()"><?= $is_edit ? 'CHỈNH SỬA MẪU PHỎNG VẤN' : 'LƯU MẪU PHỎNG VẤN' ?></button>
                    </div>
                </div>
            </div>

            <!-- Variables Sidebar -->
            <div class="vars-sidebar">
                <div class="vars-header">BIẾN MẪU</div>
                <p class="vars-intro">Bạn có thể sử dụng các biến trên Tiêu đề và Nội dung email, hệ thống sẽ tự động thay thế các nội dung tương ứng.</p>
                <?php
                $vars = [
                    ['{fullname}', 'Họ và tên', 'Ví dụ: Nguyễn Văn A'],
                    ['{firstname}', 'Tên ứng viên', ''],
                    ['{lastname}', 'Họ ứng viên', ''],
                    ['{jobtitle}', 'Danh xưng, ví dụ: Anh, chị, cô, Ms, Mr', ''],
                    ['{time}', 'Thời gian phỏng vấn (định dạng 08:30 20/05/2025)', ''],
                    ['{time_display_vi}', 'Thời gian phỏng vấn - hiển thị bằng Tiếng Việt có chữa thứ (định dạng 08:30 Thứ 3, 08/30/2025)', ''],
                    ['{time_display_en}', '"Thời gian phỏng vấn - hiển thị bằng Tiếng Anh có chữa thứ (định dạng 08:30 Tuesday, 03/06/2025)"', ''],
                    ['{location}', 'Địa điểm phỏng vấn', ''],
                    ['{email}', 'Email nhà tuyển dụng', ''],
                    ['{job}', 'Tên tuyển dụng', ''],
                    ['{job_title_*}', 'Tên tiêu đề chính tin tin tuyển dụng', ''],
                    ['{job_note + title}', 'Tên tiêu đề phụ của tin tin tuyển dụng tuyển', ''],
                    ['{company}', 'Tên công ty', ''],
                    ['{logo}', 'Tên phòng ban', ''],
                    ['{fullname}', 'Tên văn phòng và địa chỉ', ''],
                    ['{user_[role]_hide_[user_name]}', 'Tên [Những giám hội] của người dùng (HR manager, HR executive) hiển tại', ''],
                    ['{user_fullname}', 'Tên của các người dùng hiển tại', ''],
                    ['{user_email}', 'Email của người dùng hiển tại', ''],
                    ['{user_phone}', 'Số điện thoại của người dùng hiển tại', ''],
                    ['{user_title}', 'Chức danh của người dùng hiển tại', ''],
                    ['{interviewer_name_*}', 'Tên của người phỏng vấn, * là số thứ tự của người phỏng vấn - bắt đầu từ 1', ''],
                    ['{interviewer_title_*}', 'Chức danh của người phỏng vấn, * là số thứ tự của người phỏng vấn - bắt đầu từ 1', ''],
                    ['{note}', 'Ghi chú thêm (nội dung nhập khi scan mail)', ''],
                ];
                foreach ($vars as $v): ?>
                <div class="var-group">
                    <span class="var-tag" onclick="insertVar('<?= htmlspecialchars($v[0]) ?>')"><?= htmlspecialchars($v[0]) ?></span>
                    <div class="var-desc"><?= htmlspecialchars($v[1]) ?><?= $v[2] ? '<br><span style="color:#9ca3af">' . htmlspecialchars($v[2]) . '</span>' : '' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const TEMPLATE_ID = <?= $template_id ?>;

// ── Multi-select Participants ────────────────────────────────────────
let allManagers = [];
let selectedParticipants = [];
let hideTimeout = null;

async function loadHRManagers() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_permissions');
        const r = await res.json();
        if (r.success) {
            const managers = (r.data.manager || []).map(u => ({...u, roleLabel: 'HR Manager'}));
            const execs    = (r.data.executive || []).map(u => ({...u, roleLabel: 'HR Executive'}));
            allManagers = [...managers, ...execs];
        }
    } catch(e) { console.error(e); }
}

function showDropdown() {
    clearTimeout(hideTimeout);
    renderDropdown(allManagers);
    document.getElementById('participants-dropdown').style.display = 'block';
}

function hideDropdown() {
    hideTimeout = setTimeout(() => {
        document.getElementById('participants-dropdown').style.display = 'none';
        document.getElementById('participants-search').value = '';
    }, 200);
}

function searchHRManagers(q) {
    q = q.toLowerCase();
    const filtered = q ? allManagers.filter(u =>
        u.full_name.toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q)
    ) : allManagers;
    renderDropdown(filtered);
    document.getElementById('participants-dropdown').style.display = 'block';
}

function renderDropdown(list) {
    const dd = document.getElementById('participants-dropdown');
    if (!list.length) {
        dd.innerHTML = '<div class="dropdown-empty">Không tìm thấy HR Manager/Executive nào</div>';
        return;
    }
    dd.innerHTML = list.map(u => {
        const isSelected = selectedParticipants.some(s => s.user_id == u.user_id);
        const avatarHtml = u.avatar
            ? `<img src="${escHtml(u.avatar)}" class="dropdown-avatar" alt="">`
            : `<div class="dropdown-avatar-placeholder">${escHtml((u.full_name||'?')[0].toUpperCase())}</div>`;
        return `<div class="dropdown-item ${isSelected ? 'selected' : ''}" onmousedown="toggleParticipant(${u.user_id})">
            ${avatarHtml}
            <div class="dropdown-info">
                <div class="dropdown-name">${escHtml(u.full_name)}</div>
                <div class="dropdown-email">${escHtml(u.email||'')}</div>
            </div>
            <span class="dropdown-role">${escHtml(u.roleLabel)}</span>
            ${isSelected ? '<span style="color:#22c55e;font-size:18px;font-weight:bold">✓</span>' : ''}
        </div>`;
    }).join('');
}

function toggleParticipant(userId) {
    const idx = selectedParticipants.findIndex(s => s.user_id == userId);
    if (idx >= 0) {
        selectedParticipants.splice(idx, 1);
    } else {
        const user = allManagers.find(u => u.user_id == userId);
        if (user) selectedParticipants.push(user);
    }
    renderTags();
    serializeParticipants();
    searchHRManagers(document.getElementById('participants-search').value);
}

function removeParticipant(userId) {
    selectedParticipants = selectedParticipants.filter(s => s.user_id != userId);
    renderTags();
    serializeParticipants();
}

function renderTags() {
    const wrap = document.getElementById('participants-tags');
    const input = document.getElementById('participants-search');
    wrap.querySelectorAll('.tag-item').forEach(t => t.remove());
    selectedParticipants.forEach(u => {
        const tag = document.createElement('div');
        tag.className = 'tag-item';
        const av = u.avatar
            ? `<img src="${escHtml(u.avatar)}" alt="">`
            : `<div class="tag-avatar-placeholder">${escHtml((u.full_name||'?')[0].toUpperCase())}</div>`;
        tag.innerHTML = `${av}<span>${escHtml(u.full_name)}</span><span class="tag-remove" onmousedown="event.preventDefault();removeParticipant(${u.user_id})">×</span>`;
        wrap.insertBefore(tag, input);
    });
}

function serializeParticipants() {
    document.getElementById('tpl-participants').value = selectedParticipants.map(u => u.user_id).join(',');
}

function loadParticipantsFromSaved(savedStr) {
    if (!savedStr) return;
    const ids = savedStr.split(',').map(s => parseInt(s.trim())).filter(Boolean);
    ids.forEach(id => {
        const user = allManagers.find(u => u.user_id == id);
        if (user && !selectedParticipants.some(s => s.user_id == id)) selectedParticipants.push(user);
    });
    renderTags();
    serializeParticipants();
}

// ── TinyMCE ──────────────────────────────────────────────────────────
tinymce.init({
    selector: '#tpl-body',
    height: 300,
    menubar: false,
    plugins: ['lists', 'link', 'table'],
    toolbar: 'undo redo | styleselect | fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link table | removeformat',
    content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; }',
    promotion: false,
    branding: false,
});

tinymce.init({
    selector: '#tpl-questions',
    height: 200,
    menubar: false,
    plugins: ['lists'],
    toolbar: 'undo redo | bold italic | bullist numlist | removeformat',
    content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; color: #6b7280; }',
    promotion: false,
    branding: false,
    placeholder: 'Nội dung chi tiết các câu hỏi phỏng vấn bạn hoặc những người tham gia sẽ dùng hỏi ứng viên (các nội dung này sẽ KHÔNG gửi kèm email)',
});

// ── Load existing template ────────────────────────────────────────────
async function loadTemplate() {
    if (!TEMPLATE_ID) return;
    try {
        const res = await fetch(`/hrm/ajax-handler?action=get_interview_template&id=${TEMPLATE_ID}`);
        const r = await res.json();
        if (r.success) {
            const t = r.data;
            document.getElementById('tpl-name').value = t.name || '';
            document.getElementById('tpl-type').value = t.interview_type || 'onsite';
            document.getElementById('tpl-location').value = t.location || '';
            document.getElementById('tpl-subject').value = t.email_subject || '';
            // Load participants as multi-select tags
            if (t.participants) loadParticipantsFromSaved(t.participants);
            setTimeout(() => {
                if (tinymce.get('tpl-body')) tinymce.get('tpl-body').setContent(t.email_body || '');
                if (tinymce.get('tpl-questions')) tinymce.get('tpl-questions').setContent(t.questions || '');
            }, 800);
        }
    } catch(e) { console.error(e); }
}

// ── Save ──────────────────────────────────────────────────────────────
async function saveTemplate() {
    const name = document.getElementById('tpl-name').value.trim();
    if (!name) { showToast('Vui lòng nhập tên mẫu phỏng vấn', 'error'); return; }

    const body      = tinymce.get('tpl-body')      ? tinymce.get('tpl-body').getContent()      : '';
    const questions = tinymce.get('tpl-questions') ? tinymce.get('tpl-questions').getContent() : '';

    const payload = {
        id: TEMPLATE_ID,
        name,
        interview_type: document.getElementById('tpl-type').value,
        participants:   document.getElementById('tpl-participants').value,
        location:       document.getElementById('tpl-location').value,
        email_subject:  document.getElementById('tpl-subject').value,
        email_body: body,
        questions,
        is_active: 1,
    };

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_interview_template', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const r = await res.json();
        if (r.success) {
            showToast('Lưu mẫu phỏng vấn thành công!');
            setTimeout(() => location.href = '/hrm/interview-templates', 1200);
        } else {
            showToast('Lỗi: ' + (r.message || 'Không thể lưu'), 'error');
        }
    } catch(e) { showToast('Lỗi kết nối!', 'error'); }
}

// ── Helpers ───────────────────────────────────────────────────────────
function insertVar(varName) {
    const active = tinymce.activeEditor;
    if (active) {
        active.insertContent(varName);
    } else {
        navigator.clipboard.writeText(varName).then(() => showToast('Đã copy: ' + varName));
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

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    // Load HR managers first, then restore saved participants
    await loadHRManagers();
    await loadTemplate();

    // Click outside closes dropdown
    document.addEventListener('click', (e) => {
        const wrap = document.getElementById('participants-wrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('participants-dropdown').style.display = 'none';
        }
    });
    // Click tags area to focus input
    document.getElementById('participants-tags').addEventListener('click', () => {
        document.getElementById('participants-search').focus();
    });
});
</script>
</body>
</html>
