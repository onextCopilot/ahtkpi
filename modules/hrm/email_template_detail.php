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
<title><?= $is_edit ? 'Chỉnh sửa mẫu email' : 'Thêm mẫu email' ?> – E-Hiring</title>
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
.eh-main{flex:1;overflow-y:auto;background:#fff;display:flex;gap:0}

/* Left form area */
.form-area{flex:1;overflow-y:auto;padding:32px;background:#fff}
.breadcrumb{font-size:12px;color:#9ca3af;margin-bottom:16px}
.breadcrumb a{color:#6b7280;text-decoration:none}
.breadcrumb a:hover{color:#374151}
.page-title{font-size:22px;font-weight:700;color:#111827;margin-bottom:8px}
.section-title{font-size:15px;font-weight:600;color:#10b981;margin-bottom:24px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em}
.form-label span.req{color:#ef4444}
.form-input{width:100%;padding:10px 0;border:none;border-bottom:1px solid #d1d5db;font-size:14px;outline:none;transition:border .2s;font-weight:500;color:#111827}
.form-input:focus{border-color:#1d4ed8}
.form-hint{font-size:11px;color:#9ca3af;margin-top:4px}
.editor-label{font-size:11px;font-weight:700;color:#6b7280;margin-bottom:8px;display:block;text-transform:uppercase;letter-spacing:0.05em}
.editor-wrap{border:1px solid #d1d5db;border-radius:6px;overflow:hidden}

.file-attach-btn{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#6b7280;cursor:pointer}
.file-attach-btn:hover{color:#1d4ed8}

/* Bottom actions */
.form-actions{background:#fff;padding:16px 32px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;border-top:1px solid #e5e7eb}
.btn-cancel{background:#f3f4f6;border:none;color:#374151;padding:10px 20px;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none}
.btn-cancel:hover{background:#e5e7eb}
.btn-save{background:#22c55e;border:none;color:#fff;padding:10px 24px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.btn-save:hover{background:#16a34a}

/* Right sidebar - variables */
.vars-sidebar{width:320px;flex-shrink:0;overflow-y:auto;background:#fafafa;border-left:1px solid #e5e7eb;padding:20px}
.vars-header{font-size:14px;font-weight:700;color:#111827;margin-bottom:8px;text-transform:uppercase}
.vars-intro{font-size:11px;color:#059669;margin-bottom:20px;line-height:1.5}
.vars-section-title{font-size:12px;font-weight:700;color:#374151;margin-bottom:12px;margin-top:20px;padding-bottom:8px;border-bottom:1px solid #e5e7eb}
.var-group{margin-bottom:12px}
.var-tag{display:inline-block;color:#ef4444;font-family:monospace;font-size:12px;cursor:pointer;margin-bottom:4px;background:#fee2e2;padding:2px 6px;border-radius:4px;font-weight:500}
.var-tag:hover{background:#fecaca}
.var-desc{font-size:11px;color:#6b7280;line-height:1.4}

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
            <!-- Form Area -->
            <div style="flex:1;display:flex;flex-direction:column;overflow:hidden">
                <div class="form-area">
                    <div class="breadcrumb">
                        <a href="/hrm/email-templates">Danh sách mẫu email</a> &rsaquo;
                        <?= $is_edit ? 'Chỉnh sửa' : 'Thêm mới' ?>
                    </div>
                    <h1 class="page-title">Danh sách mẫu email</h1>
                    <p class="section-title"><?= $is_edit ? 'Chỉnh sửa mẫu email' : 'Tạo mẫu email mới' ?></p>

                    <input type="hidden" id="template-id" value="<?= $template_id ?>">

                    <div class="form-group" style="margin-top:32px">
                        <label class="form-label">TÊN MẪU EMAIL <span class="req">*</span></label>
                        <input type="text" class="form-input" id="tpl-name" placeholder="Ví dụ: BÁO FAIL VÒNG PHỎNG VẤN">
                    </div>

                    <div class="form-group">
                        <label class="form-label">TIÊU ĐỀ MAIL <span class="req">*</span></label>
                        <input type="text" class="form-input" id="tpl-subject" placeholder="Ví dụ: AHT TECH - THƯ CẢM ƠN PHỎNG VẤN VỊ TRÍ {job}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ĐÍNH KÈM TỆP TIN</label>
                        <div class="file-attach-btn" onclick="document.getElementById('file-upload').click()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            Nhấn để thêm tệp
                        </div>
                        <input type="file" id="file-upload" style="display:none" multiple>
                    </div>

                    <div class="form-group" style="margin-bottom:0">
                        <span class="editor-label">NỘI DUNG EMAIL</span>
                        <div class="editor-wrap">
                            <textarea id="tpl-body" style="min-height:400px;width:100%"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn-cancel" onclick="alert('Chức năng gửi thử đang được cập nhật')">Gửi thử cho tôi</button>
                    <button class="btn-save" onclick="saveTemplate()"><?= $is_edit ? 'HOÀN THÀNH CHỈNH SỬA' : 'TẠO MỚI MẪU EMAIL' ?></button>
                </div>
            </div>

            <!-- Variables Sidebar -->
            <div class="vars-sidebar">
                <div class="vars-header">BIẾN MẪU</div>
                <p class="vars-intro">Bạn có thể sử dụng các biến trên <strong>Tiêu đề và Nội dung email</strong>, hệ thống sẽ tự động thay thế các nội dung tương ứng.</p>
                
                <div class="vars-section-title">Biến mẫu thường dùng</div>
                <?php
                $vars_common = [
                    ['{fullname}', 'Họ và tên'],
                    ['{firstname} Hoặc {name}', 'Tên ứng viên'],
                    ['{lastname}', 'Họ ứng viên'],
                    ['{prefix}', 'Danh xưng, ví dụ: Anh, chị, Ms, Mr'],
                    ['{email}', 'Email nhận đơn tuyển dụng'],
                    ['{job}', 'Tin tuyển dụng'],
                    ['{joblink}', 'Liên kết đến thông tin vị trí tuyển dụng'],
                    ['{job_update_link}', 'Liên kết để ứng viên tự bổ sung thông tin ứng tuyển'],
                    ['{company}', 'Tên công ty'],
                    ['{dept}', 'Tên phòng ban'],
                    ['{office}', 'Tên văn phòng và địa chỉ'],
                    ['{me} Hoặc {user_firstname} Hoặc {user_name}', 'Tên (không gồm họ) của người dùng hiện tại'],
                    ['{user_fullname}', 'Tên đầy đủ của người dùng hiện tại'],
                    ['{user_email}', 'Email của người dùng hiện tại'],
                    ['{user_phone}', 'Số điện thoại của người dùng hiện tại'],
                    ['{user_title}', 'Chức danh của người dùng hiện tại'],
                    ['{note}', 'Ghi chú thêm (nội dung nhập khi scan mail)'],
                    ['{external_test_url}', 'Đường dẫn phần thi của ứng viên (Base Test)'],
                    ['{custom_*}', '* là input_key của trường tùy chỉnh'],
                ];
                foreach ($vars_common as $v): ?>
                <div class="var-group">
                    <span class="var-tag" onclick="insertVar('<?= htmlspecialchars(explode(' ', $v[0])[0]) ?>')"><?= htmlspecialchars($v[0]) ?></span>
                    <div class="var-desc"><?= htmlspecialchars($v[1]) ?></div>
                </div>
                <?php endforeach; ?>

                <div class="vars-section-title">Chỉ sử dụng khi thao tác hẹn một cuộc phỏng vấn</div>
                <?php
                $vars_interview = [
                    ['{time}', 'Thời gian phỏng vấn (định dạng: 08:30 20/05/2025)'],
                    ['{time_display_vi}', 'Thời gian phỏng vấn - hiển thị Tiếng Việt có chứa thứ'],
                    ['{time_display_en}', 'Thời gian phỏng vấn - hiển thị Tiếng Anh có chứa thứ'],
                    ['{location}', 'Địa điểm phỏng vấn'],
                    ['{cv_link}', 'Dẫn đến CV của ứng viên'],
                    ['{interviewer_name}', 'Tên người phỏng vấn'],
                    ['{previous_time}', 'Thời gian phỏng vấn trước khi thay đổi'],
                    ['{interview_detail_link}', 'Link cuộc phỏng vấn'],
                ];
                foreach ($vars_interview as $v): ?>
                <div class="var-group">
                    <span class="var-tag" onclick="insertVar('<?= htmlspecialchars($v[0]) ?>')"><?= htmlspecialchars($v[0]) ?></span>
                    <div class="var-desc"><?= htmlspecialchars($v[1]) ?></div>
                </div>
                <?php endforeach; ?>

                <div class="vars-section-title">Chỉ sử dụng khi thao tác hẹn phỏng vấn hàng loạt</div>
                <?php
                $vars_batch = [
                    ['{interviewer_name}', 'Tên người phỏng vấn'],
                    ['{num_interviews}', 'Số cuộc phỏng vấn'],
                    ['{location}', 'Địa điểm phỏng vấn'],
                    ['{list_interviews}', 'Danh sách phỏng vấn'],
                ];
                foreach ($vars_batch as $v): ?>
                <div class="var-group">
                    <span class="var-tag" onclick="insertVar('<?= htmlspecialchars($v[0]) ?>')"><?= htmlspecialchars($v[0]) ?></span>
                    <div class="var-desc"><?= htmlspecialchars($v[1]) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const TEMPLATE_ID = <?= $template_id ?>;

tinymce.init({
    selector: '#tpl-body',
    height: 400,
    menubar: false,
    plugins: ['lists', 'link', 'table', 'code'],
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | removeformat code',
    content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; line-height: 1.5; }',
    promotion: false,
    branding: false,
});

async function loadTemplate() {
    if (!TEMPLATE_ID) return;
    try {
        const res = await fetch(`/hrm/ajax-handler?action=get_email_template&id=${TEMPLATE_ID}`);
        const r = await res.json();
        if (r.success) {
            const t = r.data;
            document.getElementById('tpl-name').value = t.name || '';
            document.getElementById('tpl-subject').value = t.email_subject || '';
            setTimeout(() => {
                if (tinymce.get('tpl-body')) tinymce.get('tpl-body').setContent(t.email_body || '');
            }, 800);
        }
    } catch(e) { console.error(e); }
}

async function saveTemplate() {
    const name = document.getElementById('tpl-name').value.trim();
    if (!name) { showToast('Vui lòng nhập tên mẫu email', 'error'); return; }

    const subject = document.getElementById('tpl-subject').value.trim();
    if (!subject) { showToast('Vui lòng nhập tiêu đề email', 'error'); return; }

    const body = tinymce.get('tpl-body') ? tinymce.get('tpl-body').getContent() : '';

    const payload = {
        id: TEMPLATE_ID,
        name,
        email_subject: subject,
        email_body: body,
        is_active: 1
    };

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_email_template', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const r = await res.json();
        if (r.success) {
            showToast('Lưu mẫu email thành công!');
            setTimeout(() => location.href = '/hrm/email-templates', 1000);
        } else {
            showToast('Lỗi: ' + (r.message || 'Không thể lưu'), 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối!', 'error');
    }
}

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

document.addEventListener('DOMContentLoaded', loadTemplate);
</script>
</body>
</html>
