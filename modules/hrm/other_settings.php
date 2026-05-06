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
<title>Các tùy chọn khác – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-inner-body{display:flex;flex:1;overflow-y:auto;padding:32px;gap:40px;background:#fff;justify-content:center}
.eh-main-container{display:flex;width:100%;max-width:1200px;gap:40px}
.eh-settings-form{flex:1;max-width:850px}
.eh-right-nav{width:260px;flex-shrink:0;display:none} /* Hidden for now or show if needed */
@media (min-width: 1400px) { .eh-right-nav { display: block; } }

.right-nav-card{border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;position:sticky;top:0}
.right-nav-header{padding:16px;background:#f9fafb;font-size:14px;font-weight:700;border-bottom:1px solid #e5e7eb}
.right-nav-item{padding:12px 16px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background 0.2s}
.right-nav-item:hover{background:#f9fafb}
.right-nav-item.active{background:#eef2ff;color:#2563eb;font-weight:600;border-left:3px solid #2563eb}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}

.section{max-width:900px}
.section-title{font-size:16px;font-weight:700;color:#111827;text-transform:uppercase;margin-bottom:8px}
.section-subtitle{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;border-bottom:1px solid #f3f4f6;padding-bottom:8px}

.process-table{width:100%;border-collapse:collapse;margin-bottom:16px}
.process-row{border-bottom:1px solid #f9fafb}
.process-row td{padding:12px 0;font-size:13px}
.drag-handle{color:#d1d5db;cursor:grab;padding-right:12px}
.step-name{font-weight:600;color:#374151}
.step-meta{color:#9ca3af;font-size:12px;text-align:right}
.step-action{color:#3b82f6;cursor:pointer;font-weight:600;margin-left:12px}

.add-step-bar{background:#fff8e6;border:1px dashed #ffd666;padding:12px;border-radius:6px;display:flex;align-items:center;gap:8px;font-size:13px;color:#856404;cursor:pointer;margin-bottom:24px}

.status-list{display:flex;flex-direction:column;gap:12px;margin-top:16px}
.status-item{display:flex;align-items:center;gap:12px;font-size:13px}
.status-dot{width:8px;height:8px;border-radius:50%}

.form-group{margin-bottom:24px}
.form-label{display:block;font-size:13px;font-weight:700;color:#111827;margin-bottom:8px}
.form-select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;outline:none}
.info-box{background:#fff1f0;border:1px solid #ffa39e;padding:12px 16px;border-radius:6px;margin-top:12px;font-size:12px;color:#cf1322;line-height:1.5}

.save-footer{position:sticky;bottom:0;background:#fff;padding:16px 24px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;margin:0 -24px -24px}
.save-btn{background:#2563eb;color:#fff;border:none;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer}

/* SLIDE SIDEBAR */
.sidebar-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:1000;display:none;opacity:0;transition:opacity 0.3s}
.eh-sidebar-right{position:fixed;top:0;right:-450px;width:450px;height:100%;background:#fff;z-index:1001;box-shadow:-4px 0 12px rgba(0,0,0,0.1);transition:right 0.3s ease;display:flex;flex-direction:column}
.sidebar-overlay.active{display:block;opacity:1}
.eh-sidebar-right.active{right:0}
.sidebar-header{padding:20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center}
.sidebar-title{font-size:16px;font-weight:700;color:#111827}
.sidebar-close{font-size:24px;color:#9ca3af;cursor:pointer;line-height:1}
.sidebar-body{padding:24px;flex:1;overflow-y:auto}
.sidebar-footer{padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:12px}
.btn-cancel{background:#fff;border:1px solid #d1d5db;color:#374151;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.btn-save{background:#2563eb;color:#fff;border:none;padding:8px 24px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
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
            <div class="eh-main-container">
                <div class="eh-settings-form">
                    <h1 class="section-title">Các tùy chọn khác</h1>
                
                <div style="margin-top:32px">
                    <h2 class="section-subtitle">QUY TRÌNH TUYỂN DỤNG TIÊU CHUẨN</h2>
                    <table class="process-table">
                        <tbody id="step-list"></tbody>
                    </table>
                    <div class="add-step-bar" onclick="openSidebar()">+ Thêm một bước trong quy trình tuyển dụng chuẩn (nhấn Enter để xem thêm)</div>
                    
                    <div class="status-list">
                        <div class="status-item"><span class="status-dot" style="background:#52c41a"></span> <strong style="color:#52c41a">Offered</strong> <span style="flex:1;color:#9ca3af;font-size:12px;margin-left:12px">Mẫu Email - Đồng ý đề nghị tuyển dụng cho bước này</span> <span class="step-action">Sửa</span></div>
                        <div class="status-item"><span class="status-dot" style="background:#1890ff"></span> <strong style="color:#1890ff">Hired</strong> <span style="flex:1;color:#9ca3af;font-size:12px;margin-left:12px">Mẫu Email - Chính thức tuyển dụng ứng viên</span> <span class="step-action">Sửa</span></div>
                        <div class="status-item"><span class="status-dot" style="background:#ff4d4f"></span> <strong style="color:#ff4d4f">Rejected</strong> <span style="flex:1;color:#9ca3af;font-size:12px;margin-left:12px">Mẫu Email - Loại ứng viên</span> <span class="step-action">Sửa</span></div>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">LỰA CHỌN MÃ TIN TUYỂN DỤNG</h2>
                    <div class="form-group">
                        <label class="form-label">Bắt buộc mã tin tuyển dụng hay không?</label>
                        <select class="form-select" id="require_job_code">
                            <option value="0">Không, không bắt buộc</option>
                            <option value="1">Có, bắt buộc</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">TÙY CHỌN PHƯƠNG THỨC ĐÁNH GIÁ ỨNG VIÊN</h2>
                    <div class="form-group">
                        <label class="form-label">Cách thức đánh giá</label>
                        <select class="form-select" id="evaluation_method">
                            <option value="general">Đánh giá tổng hợp</option>
                            <option value="criteria">Đánh giá chi tiết theo tiêu chí</option>
                        </select>
                        <div class="info-box">Lưu ý: đánh giá tổng hợp là phương thức mặc định.</div>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">TỰ ĐỘNG TẠO ỨNG TUYỂN TỪ EMAIL GỬI TRỰC TIẾP VÀO HÒM TUYỂN DỤNG</h2>
                    <div class="form-group">
                        <label class="form-label">Tìm và tạo ứng tuyển dựa theo tiêu đề email</label>
                        <select class="form-select" id="auto_create_from_email">
                            <option value="0">Không</option>
                            <option value="1">Có</option>
                        </select>
                        <div class="info-box">Lưu ý: Tiêu đề email phải có dạng [Mã tin tuyển dụng]<dấu cách>-Tìm ứng viên-<dấu cách><Tên ứng viên><dấu cách><Email ứng viên><dấu cách><Số điện thoại ứng viên (dấu cách)> (nếu có)</div>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">QUYỀN XÓA DỮ LIỆU MẶC ĐỊNH</h2>
                    <div class="form-group">
                        <label class="form-label">Cấp thành viên tối thiểu có thể xóa ứng viên và tin tuyển dụng, thiết lập mặc định khi tạo tin mới</label>
                        <select class="form-select" id="min_delete_permission">
                            <option value="admin">Chủ quản hệ thống</option>
                            <option value="manager">HR Manager</option>
                        </select>
                        <div class="info-box">Lưu ý: đây là giá trị mặc định thiết lập ban đầu khi tạo mới tin. Quyền xóa dữ liệu có thể được thay đổi trong từng tin tuyển dụng.</div>
                    </div>
                    <div style="margin-top:40px">
                    <h2 class="section-subtitle">QUYỀN TRÍCH XUẤT DỮ LIỆU MẶC ĐỊNH</h2>
                    <div class="form-group">
                        <label class="form-label">Cấp thành viên tối thiểu có thể trích xuất dữ liệu, thiết lập mặc định khi tạo tin mới</label>
                        <select class="form-select" id="min_export_permission">
                            <option value="admin">Chủ quản hệ thống</option>
                            <option value="manager">HR Manager</option>
                        </select>
                        <div class="info-box">Lưu ý: đây là giá trị mặc định thiết lập ban đầu khi tạo mới tin. Quyền trích xuất dữ liệu có thể được thay đổi trong từng tin tuyển dụng.</div>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">CAPTCHA CHO TRANG TUYỂN DỤNG</h2>
                    <div class="form-group">
                        <label class="form-label">Tắt captcha</label>
                        <select class="form-select" id="enable_captcha">
                            <option value="0">KHÔNG, giữ captcha bắt buộc theo mặc định</option>
                            <option value="1">CÓ, tắt captcha</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">TÙY CHỈNH MẪU EMAIL GỬI TỚI NGƯỜI THAM GIA PHỎNG VẤN</h2>
                    <div class="form-group">
                        <label class="form-label">Mẫu email mời phỏng vấn</label>
                        <select class="form-select" id="email_interview_invitation">
                            <option value="0">-- Không sử dụng mẫu email --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mẫu email cập nhật thời gian phỏng vấn</label>
                        <select class="form-select" id="email_interview_update">
                            <option value="0">-- Không sử dụng mẫu email --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Thông báo hủy phỏng vấn</label>
                        <select class="form-select" id="email_interview_cancel">
                            <option value="0">-- Không sử dụng mẫu email --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mẫu email mời phỏng vấn hàng loạt</label>
                        <select class="form-select" id="email_interview_bulk">
                            <option value="0">-- Không sử dụng mẫu email --</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">PHỎNG VẤN</h2>
                    <div class="form-group">
                        <label class="form-label">Chế độ hiển thị CV trong lịch phỏng vấn</label>
                        <select class="form-select" id="interview_cv_display">
                            <option value="restricted">Hạn chế - Người dùng phải đăng nhập để xem</option>
                            <option value="public">Công khai - Có thể xem trực tiếp</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:40px">
                    <h2 class="section-subtitle">QUYỀN LIÊN KẾT VỚI QUY TRÌNH ONBOARD</h2>
                    <div class="form-group">
                        <label class="form-label">Phương thức phân quyền liên kết tin tuyển dụng với quy trình tại Base Onboard</label>
                        <select class="form-select" id="onboard_integration_permission">
                            <option value="manager">Chỉ thành viên là người quản lý quy trình có thể liên kết</option>
                            <option value="all">Tất cả thành viên tham gia quy trình có thể liên kết</option>
                        </select>
                    </div>
                </div>
                <div class="save-footer">
                    <button class="save-btn" onclick="saveSettings()">Lưu lại</button>
                </div>
            </div> <!-- End eh-settings-form -->
        </div> <!-- End eh-main-container -->
    </div> <!-- End eh-inner-body -->
</div>

<!-- SIDEBAR ADD STEP -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="eh-sidebar-right" id="add-step-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">Thêm bước tuyển dụng</div>
        <div class="sidebar-close" onclick="closeSidebar()">&times;</div>
    </div>
    <div class="sidebar-body">
        <div class="form-group">
            <label class="form-label">Tên bước tuyển dụng</label>
            <input type="text" class="form-select" id="new_step_name" placeholder="Ví dụ: Phỏng vấn vòng 2">
        </div>
        <div class="form-group">
            <label class="form-label">Mã bước (không bắt buộc)</label>
            <input type="text" class="form-select" id="new_step_code" placeholder="Ví dụ: pv_2">
        </div>
    </div>
    <div class="sidebar-footer">
        <button class="btn-cancel" onclick="closeSidebar()">Hủy</button>
        <button class="btn-save" onclick="submitAddStep()">Lưu lại</button>
    </div>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar-overlay').classList.add('active');
    document.getElementById('add-step-sidebar').classList.add('active');
    document.getElementById('new_step_name').value = '';
    document.getElementById('new_step_code').value = '';
}

function closeSidebar() {
    document.getElementById('sidebar-overlay').classList.remove('active');
    document.getElementById('add-step-sidebar').classList.remove('active');
}

async function submitAddStep() {
    const name = document.getElementById('new_step_name').value;
    const code = document.getElementById('new_step_code').value || name.toLowerCase().replace(/\s+/g, '_');
    
    if (!name) return alert('Vui lòng nhập tên bước!');

    await fetch('/hrm/ajax-handler?action=add_hiring_step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, code })
    });
    closeSidebar();
    fetchData();
}

async function fetchData() {
    // Get general settings
    const resSett = await fetch('/hrm/ajax-handler?action=get_settings');
    const sett = await resSett.json();
    if (sett) {
        document.getElementById('require_job_code').value = sett.require_job_code || 0;
        document.getElementById('evaluation_method').value = sett.evaluation_method || 'general';
        document.getElementById('auto_create_from_email').value = sett.auto_create_from_email || 0;
        document.getElementById('min_delete_permission').value = sett.min_delete_permission || 'admin';
        document.getElementById('min_export_permission').value = sett.min_export_permission || 'admin';
        document.getElementById('enable_captcha').value = sett.enable_captcha || 0;
        document.getElementById('email_interview_invitation').value = sett.email_interview_invitation || 0;
        document.getElementById('email_interview_update').value = sett.email_interview_update || 0;
        document.getElementById('email_interview_cancel').value = sett.email_interview_cancel || 0;
        document.getElementById('email_interview_bulk').value = sett.email_interview_bulk || 0;
        document.getElementById('interview_cv_display').value = sett.interview_cv_display || 'restricted';
        document.getElementById('onboard_integration_permission').value = sett.onboard_integration_permission || 'manager';
    }

    // Get hiring steps
    const resSteps = await fetch('/hrm/ajax-handler?action=get_hiring_steps');
    const steps = await resSteps.json();
    if (steps.success) {
        renderSteps(steps.data);
    }
}

function renderSteps(steps) {
    const list = document.getElementById('step-list');
    list.innerHTML = steps.map(s => `
        <tr class="process-row" data-id="${s.id}">
            <td style="width:30px"><span class="drag-handle">::</span></td>
            <td class="step-name">${s.name}</td>
            <td class="step-meta">
                ${s.code || 'no_code'} | Mẫu Email (${s.email_count}) | Thời gian (${s.duration}h)
                <span class="step-action" onclick="deleteStep(${s.id})">Xóa</span>
            </td>
        </tr>
    `).join('');

    new Sortable(list, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: async function() {
            const ids = Array.from(list.querySelectorAll('tr')).map(tr => tr.dataset.id);
            await fetch('/hrm/ajax-handler?action=save_hiring_steps', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ steps: ids })
            });
        }
    });
}

async function deleteStep(id) {
    if (!confirm('Bạn có chắc muốn xóa bước này?')) return;
    await fetch('/hrm/ajax-handler?action=delete_hiring_step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    fetchData();
}

async function saveSettings() {
    const data = {
        require_job_code: document.getElementById('require_job_code').value,
        evaluation_method: document.getElementById('evaluation_method').value,
        auto_create_from_email: document.getElementById('auto_create_from_email').value,
        min_delete_permission: document.getElementById('min_delete_permission').value,
        min_export_permission: document.getElementById('min_export_permission').value,
        enable_captcha: document.getElementById('enable_captcha').value,
        email_interview_invitation: document.getElementById('email_interview_invitation').value,
        email_interview_update: document.getElementById('email_interview_update').value,
        email_interview_cancel: document.getElementById('email_interview_cancel').value,
        email_interview_bulk: document.getElementById('email_interview_bulk').value,
        interview_cv_display: document.getElementById('interview_cv_display').value,
        onboard_integration_permission: document.getElementById('onboard_integration_permission').value
    };

    const res = await fetch('/hrm/ajax-handler?action=save_other_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
        alert('Đã cập nhật các tùy chọn thành công!');
    }
}

document.addEventListener('DOMContentLoaded', fetchData);
</script>
</body>
</html>
