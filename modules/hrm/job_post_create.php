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
<title>Đăng tin tuyển dụng – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
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

.eh-main{flex:1;overflow-y:auto;background:#f3f4f6;padding:24px}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.page-title-group{display:flex;flex-direction:column;gap:4px}
.page-title{font-size:18px;font-weight:700;color:#111827}
.page-subtitle{font-size:12px;color:#6b7280}

.content-container{display:flex;gap:24px;max-width:1200px;margin:0 auto}
.stepper-col{width:260px;flex-shrink:0}
.stepper-item{display:flex;gap:12px;padding:16px;background:#fff;border:1px solid #e5e7eb;margin-bottom:-1px;cursor:pointer;position:relative}
.stepper-item:first-child{border-top-left-radius:8px;border-top-right-radius:8px}
.stepper-item:last-child{border-bottom-left-radius:8px;border-bottom-right-radius:8px}
.stepper-item.active{background:#2563eb;color:#fff;border-color:#2563eb;z-index:10}
.stepper-num{width:24px;height:24px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#6b7280;position:relative;z-index:2}
.stepper-item:not(:last-child):before{content:"";position:absolute;left:27px;top:40px;bottom:-20px;width:1px;background:#e5e7eb;z-index:1}
.stepper-item.active .stepper-num{background:#fff;color:#fff;width:12px;height:12px;margin:6px}
.stepper-item.active .stepper-num:after{content:"";position:absolute;width:12px;height:12px;background:#fff;border-radius:50%;left:0;top:0}
.stepper-info{flex:1}
.stepper-title{font-size:13px;font-weight:600}
.stepper-desc{font-size:11px;opacity:0.8}

.form-col{flex:1}
.form-section{background:#fff;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:24px;overflow:hidden}
.section-header{background:#f9fafb;padding:12px 20px;border-bottom:1px solid #e5e7eb;font-size:11px;font-weight:700;color:#374151;text-transform:uppercase}
.section-body{padding:24px}

.form-group{margin-bottom:20px}
.form-row{display:flex;gap:20px;margin-bottom:20px}
.form-row .form-group{flex:1;margin-bottom:0}
.form-label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:8px}
.form-input, .form-select, .form-textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;outline:none}
.form-input:focus, .form-select:focus{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,0.1)}

.salary-group{display:flex;align-items:center;gap:8px}
.salary-group input{width:100px}

.editor-placeholder{height:200px;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:14px}

.btn-primary{background:#2563eb;color:#fff;border:none;padding:12px 48px;border-radius:6px;font-weight:700;cursor:pointer;width:100%;font-size:14px}
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
            <div class="page-header">
                <div class="page-title-group">
                    <h1 class="page-title">Đăng tin tuyển dụng</h1>
                    <p class="page-subtitle">Đăng tin tuyển dụng mới cho CÔNG TY CỔ PHẦN DỊCH VỤ VÀ PHÁT TRIỂN CÔNG NGHỆ AHT</p>
                </div>
                <select class="form-select" style="width:250px">
                    <option>Sao chép nội dung từ tin đã đăng</option>
                </select>
            </div>

            <div class="content-container">
                <div class="stepper-col">
                    <div class="stepper-item active">
                        <div class="stepper-num">1</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Thông tin tuyển dụng</div>
                            <div class="stepper-desc">Các thông tin cơ bản</div>
                        </div>
                    </div>
                    <div class="stepper-item">
                        <div class="stepper-num">2</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Tiêu chí</div>
                            <div class="stepper-desc">Tiêu chí đánh giá ứng viên</div>
                        </div>
                    </div>
                    <div class="stepper-item">
                        <div class="stepper-num">3</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Quy trình tuyển dụng</div>
                            <div class="stepper-desc">Các giai đoạn của quy trình</div>
                        </div>
                    </div>
                    <div class="stepper-item">
                        <div class="stepper-num">4</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Đơn ứng tuyển</div>
                            <div class="stepper-desc">Đơn & câu hỏi ứng tuyển</div>
                        </div>
                    </div>
                    <div class="stepper-item">
                        <div class="stepper-num">5</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Đơn đánh giá</div>
                            <div class="stepper-desc">Sử dụng cho người phỏng vấn</div>
                        </div>
                    </div>
                    <div class="stepper-item">
                        <div class="stepper-num">6</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Câu hỏi phỏng vấn</div>
                            <div class="stepper-desc">Câu hỏi phỏng vấn</div>
                        </div>
                    </div>
                    <div class="stepper-item">
                        <div class="stepper-num">7</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Hoàn tất</div>
                            <div class="stepper-desc">Đánh giá & hoàn thành</div>
                        </div>
                    </div>
                </div>

                <div class="form-col">
                    <div class="form-section">
                        <div class="section-header">Thông tin cơ bản</div>
                        <div class="section-body">
                            <div class="form-group">
                                <label class="form-label">Tiêu đề tin tuyển dụng *</label>
                                <input type="text" class="form-input" id="title" placeholder="Tiêu đề tin tuyển dụng">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Mã tin tuyển dụng</label>
                                    <input type="text" class="form-input" id="jobCode" placeholder="AHT-Mã tin tuyển dụng">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Phòng ban</label>
                                    <select class="form-select" id="departmentId">
                                        <option value="0">-- Lựa chọn --</option>
                                        <!-- Loaded via JS -->
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Văn phòng *</label>
                                    <select class="form-select" id="office">
                                        <option value="">-- Lựa chọn --</option>
                                        <!-- Loaded via JS -->
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Mức lương</label>
                                    <div class="salary-group">
                                        <input type="number" class="form-input" id="salaryFrom" placeholder="Từ">
                                        <span>Đến</span>
                                        <input type="number" class="form-input" id="salaryTo" placeholder="Đến">
                                        <select class="form-select" id="currency" style="width:80px"><option>VND</option><option>USD</option></select>
                                        <label style="font-size:11px;display:flex;align-items:center;gap:4px;white-space:nowrap">
                                            <input type="checkbox" id="showSalary" checked> Hiển thị mức lương trên
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Số lượng cần tuyển</label>
                                    <input type="number" class="form-input" id="quantity" placeholder="Số lượng cần tuyển">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Loại hình công việc *</label>
                                    <select class="form-select" id="jobType">
                                        <option>Nhân viên toàn thời gian</option>
                                        <option>Nhân viên bán thời gian</option>
                                        <option>Thực tập sinh</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Thời hạn nộp đơn *</label>
                                    <input type="date" class="form-input" id="deadline">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">Miêu tả công việc</div>
                        <div class="section-body">
                            <textarea id="jobDescription" class="form-textarea" style="height:300px" placeholder="Nhập miêu tả công việc, nhiệm vụ chính và yêu cầu..."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">Talent Pool</div>
                        <div class="section-body">
                            <label class="form-label">Các ứng viên của đơn ứng tuyển này sẽ được lưu trong các talent pool sau đây</label>
                            <select class="form-select" id="talentPoolId">
                                <option value="0">-- LỰA CHỌN TALENT POOL --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">Thành viên</div>
                        <div class="section-body">
                            <label class="form-label">Thành viên quản lý (* những người có quyền xem toàn bộ thông tin ứng viên và xử lý quy trình tuyển dụng)</label>
                            <input type="text" class="form-input" id="managers" placeholder="gõ ID để gán thẻ">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">Ghi chú</div>
                        <div class="section-body">
                            <textarea id="notes" class="form-textarea" style="height:150px" placeholder="Ghi chú..."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">Thời hạn</div>
                        <div class="section-body">
                            <label class="form-label">Thời hạn hoàn thành toàn bộ quá trình ứng tuyển</label>
                            <input type="text" class="form-input" id="completionTime" placeholder="Thời hạn hoàn thành toàn bộ quá trình ứng tuyển">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">SEO</div>
                        <div class="section-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Tỉnh/Thành phố</label>
                                    <input type="text" class="form-input" id="city" placeholder="Ví dụ: Hà Nội">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Quận/Huyện</label>
                                    <input type="text" class="form-input" id="district" placeholder="Ví dụ: Quận Thanh Xuân">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Địa chỉ cụ thể</label>
                                    <input type="text" class="form-input" id="address" placeholder="Ví dụ: Số 47, đường Nguyễn Tuân">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Mã bưu điện</label>
                                    <input type="text" class="form-input" id="postalCode" placeholder="Ví dụ: 10000">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn-primary" onclick="saveJobPost()">LƯU LẠI</button>
                    <div style="height:100px"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function initEditors() {
    tinymce.init({
        selector: '#jobDescription, #notes',
        height: 300,
        menubar: false,
        branding: false,
        promotion: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic underline strikethrough | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help',
        content_style: 'body { font-family:Inter,sans-serif; font-size:14px }'
    });
}

async function loadFormData() {
    initEditors();
    try {
        // Fetch departments
        const deptRes = await fetch('/hrm/ajax-handler?action=get_depts');
        const depts = await deptRes.json();
        const dSelect = document.getElementById('departmentId');
        if (Array.isArray(depts)) {
            depts.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.innerText = d.name;
                dSelect.appendChild(opt);
            });
        }

        // Fetch offices
        const officeRes = await fetch('/hrm/ajax-handler?action=get_offices');
        const offices = await officeRes.json();
        const oSelect = document.getElementById('office');
        if (Array.isArray(offices)) {
            offices.forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.name; // Storing name as per VARCHAR schema
                opt.innerText = o.name;
                oSelect.appendChild(opt);
            });
        }

        // Fetch talent pools
        const poolRes = await fetch('/hrm/ajax-handler?action=get_talent_pools');
        const pools = await poolRes.json();
        const pSelect = document.getElementById('talentPoolId');
        if (pools.success && Array.isArray(pools.data)) {
            pools.data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.innerText = p.name;
                pSelect.appendChild(opt);
            });
        }
    } catch (e) { console.error(e); }
}

async function saveJobPost() {
    const data = {
        title: document.getElementById('title').value,
        job_code: document.getElementById('jobCode').value,
        department_id: document.getElementById('departmentId').value,
        office: document.getElementById('office').value,
        salary_from: document.getElementById('salaryFrom').value,
        salary_to: document.getElementById('salaryTo').value,
        currency: document.getElementById('currency').value,
        show_salary: document.getElementById('showSalary').checked ? 1 : 0,
        quantity: document.getElementById('quantity').value,
        job_type: document.getElementById('jobType').value,
        deadline: document.getElementById('deadline').value,
        job_description: tinymce.get('jobDescription').getContent(),
        talent_pool_id: document.getElementById('talentPoolId').value,
        managers: document.getElementById('managers').value,
        notes: tinymce.get('notes').getContent(),
        completion_time: document.getElementById('completionTime').value,
        city: document.getElementById('city').value,
        district: document.getElementById('district').value,
        address: document.getElementById('address').value,
        postal_code: document.getElementById('postalCode').value
    };

    if (!data.title) return alert('Vui lòng nhập tiêu đề tin tuyển dụng');

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_job_post', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            alert('Lưu tin tuyển dụng thành công');
            // Redirect or go to next step
        }
    } catch (e) { console.error(e); }
}

document.addEventListener('DOMContentLoaded', loadFormData);
</script>
</body>
</html>
