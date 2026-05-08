<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$job_id = (int)($_GET['id'] ?? 0);

if (!$job_id) { header("Location: /hrm/openings"); exit(); }

?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chỉnh sửa tin tuyển dụng – E-Hiring</title>
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

.btn-primary{background:#2563eb;color:#fff;border:none;padding:12px 48px;border-radius:6px;font-weight:700;cursor:pointer;width:100%;font-size:14px}
.btn-secondary{background:#f3f4f6;color:#374151;border:1px solid #d1d5db;padding:12px 32px;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px}

/* Modal styles */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; width: 450px; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-header { padding: 16px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 20px; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 12px; justify-content: flex-end; }

/* Step 2 Refinements */
.criteria-table th { padding: 12px; text-align: left; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
.criteria-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; }
.mandatory-row { display: flex; gap: 8px; margin-bottom: 12px; align-items: center; }
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
                <button class="top-btn" onclick="location.href='/hrm/job-detail?id=<?=$job_id?>'">← Quay lại Tin chi tiết</button>
                <div class="top-avatar"><?=strtoupper(substr($full_name,0,1))?></div>
            </div>
        </div>

        <main class="eh-main">
            <div class="page-header">
                <div class="page-title-group">
                    <h1 class="page-title">Chỉnh sửa tin tuyển dụng</h1>
                    <p class="page-subtitle" id="jobSubTitle">Đang tải thông tin...</p>
                </div>
            </div>

            <div class="content-container">
                <div class="stepper-col">
                    <div class="stepper-item active" onclick="goToStep(1)">
                        <div class="stepper-num">1</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Thông tin tuyển dụng</div>
                            <div class="stepper-desc">Các thông tin cơ bản</div>
                        </div>
                    </div>
                    <div class="stepper-item" onclick="goToStep(2)">
                        <div class="stepper-num">2</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Tiêu chí</div>
                            <div class="stepper-desc">Tiêu chí đánh giá ứng viên</div>
                        </div>
                    </div>
                    <div class="stepper-item" onclick="goToStep(3)">
                        <div class="stepper-num">3</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Quy trình tuyển dụng</div>
                            <div class="stepper-desc">Các giai đoạn của quy trình</div>
                        </div>
                    </div>
                    <div class="stepper-item" onclick="goToStep(4)">
                        <div class="stepper-num">4</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Đơn ứng tuyển</div>
                            <div class="stepper-desc">Đơn & câu hỏi ứng tuyển</div>
                        </div>
                    </div>
                    <div class="stepper-item" onclick="goToStep(5)">
                        <div class="stepper-num">5</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Đơn đánh giá</div>
                            <div class="stepper-desc">Sử dụng cho người phỏng vấn</div>
                        </div>
                    </div>
                    <div class="stepper-item" onclick="goToStep(6)">
                        <div class="stepper-num">6</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Câu hỏi phỏng vấn</div>
                            <div class="stepper-desc">Câu hỏi phỏng vấn</div>
                        </div>
                    </div>
                    <div class="stepper-item" onclick="goToStep(7)">
                        <div class="stepper-num">7</div>
                        <div class="stepper-info">
                            <div class="stepper-title">Hoàn tất</div>
                            <div class="stepper-desc">Đánh giá & hoàn thành</div>
                        </div>
                    </div>
                </div>

                <div class="form-col">
                    <!-- STEP 1: THÔNG TIN TUYỂN DỤNG -->
                    <div id="step-1-content">
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
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Văn phòng *</label>
                                        <select class="form-select" id="office">
                                            <option value="">-- Lựa chọn --</option>
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
                                <textarea id="jobDescription" class="form-textarea" style="height:300px"></textarea>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">Talent Pool</div>
                            <div class="section-body">
                                <select class="form-select" id="talentPoolId">
                                    <option value="0">-- LỰA CHỌN TALENT POOL --</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">Thành viên</div>
                            <div class="section-body">
                                <input type="text" class="form-input" id="managers" placeholder="gõ ID để gán thẻ">
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">Ghi chú</div>
                            <div class="section-body">
                                <textarea id="notes" class="form-textarea" style="height:150px"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: TIÊU CHÍ -->
                    <div id="step-2-content" style="display:none">
                        <div class="form-section">
                            <div class="section-header">Tiêu chí đánh giá</div>
                            <div class="section-body">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;gap:20px">
                                    <p style="font-size:13px;color:#6b7280;line-height:1.5">Lựa chọn các tiêu chí để đánh giá ứng viên cho vị trí tuyển dụng này.</p>
                                    <div style="display:flex;gap:8px;flex-shrink:0">
                                        <button class="btn-secondary" style="padding:8px 16px;font-size:13px" onclick="openMultiCriterionModal()">Thêm nhiều</button>
                                        <button class="btn-primary" style="padding:8px 16px;font-size:13px;width:auto" onclick="openCriterionModal()">+ Thêm tiêu chí</button>
                                    </div>
                                </div>

                                <table class="criteria-table" style="width:100%;border-collapse:collapse">
                                    <thead style="background:#f9fafb">
                                        <tr>
                                            <th>Tiêu chí</th>
                                            <th style="width:150px">Điểm kỳ vọng</th>
                                            <th style="width:100px">Trọng số</th>
                                            <th style="text-align:center;width:60px">#</th>
                                        </tr>
                                    </thead>
                                    <tbody id="selectedCriteriaBody">
                                        <tr><td colspan="4" style="text-align:center;padding:40px;color:#9ca3af">Đang tải...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">Các yêu cầu bắt buộc khác</div>
                            <div class="section-body">
                                <div id="mandatoryRequirementsList"></div>
                                <button class="btn-secondary" style="margin-top:12px;padding:8px 16px;font-size:13px" onclick="addMandatoryRow()">+ Thêm yêu cầu bắt buộc</button>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: QUY TRÌNH TUYỂN DỤNG -->
                    <div id="step-3-content" style="display:none">
                        <div class="form-section">
                            <div class="section-header">Các giai đoạn tuyển dụng</div>
                            <div class="section-body">
                                <p style="font-size:13px;color:#6b7280;margin-bottom:20px">Thiết lập các giai đoạn mà ứng viên sẽ trải qua. Bạn có thể thêm, bớt hoặc thay đổi thứ tự các giai đoạn.</p>
                                
                                <div id="hiringStagesList">
                                    <!-- Stages will be rendered here -->
                                </div>

                                <button class="btn-secondary" style="margin-top:16px;padding:8px 16px;font-size:13px" onclick="addStageRow()">+ Thêm giai đoạn</button>
                            </div>
                        </div>
                    </div>
                    <!-- STEP 4: ĐƠN ỨNG TUYỂN -->
                    <div id="step-4-content" style="display:none">
                        <div class="form-section">
                            <div class="section-header">Các trường thông tin cơ bản</div>
                            <div class="section-body">
                                <table class="criteria-table" style="width:100%;border-collapse:collapse">
                                    <thead style="background:#f9fafb">
                                        <tr>
                                            <th>Trường thông tin</th>
                                            <th style="width:100px;text-align:center">Hiển thị</th>
                                            <th style="width:100px;text-align:center">Bắt buộc</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appFieldsBody">
                                        <!-- Fields will be rendered here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">Câu hỏi tùy chỉnh</div>
                            <div class="section-body">
                                <div id="customQuestionsList"></div>
                                <button class="btn-secondary" style="margin-top:16px;padding:8px 16px;font-size:13px" onclick="addCustomQuestion()">+ Thêm câu hỏi tùy chỉnh</button>
                            </div>
                        </div>
                    </div>
                    <div id="step-5-content" style="display:none;padding:40px;text-align:center;background:#fff;border-radius:8px;border:1px solid #e5e7eb">
                        <h3 style="margin-bottom:16px">Đơn đánh giá</h3>
                        <p style="color:#6b7280">Đang phát triển...</p>
                    </div>
                    <div id="step-6-content" style="display:none;padding:40px;text-align:center;background:#fff;border-radius:8px;border:1px solid #e5e7eb">
                        <h3 style="margin-bottom:16px">Câu hỏi phỏng vấn</h3>
                        <p style="color:#6b7280">Đang phát triển...</p>
                    </div>
                    <!-- STEP 7: HOÀN TẤT -->
                    <div id="step-7-content" style="display:none">
                        <div class="form-section">
                            <div class="section-header">Trạng thái tin tuyển dụng</div>
                            <div class="section-body">
                                <div style="display:flex;gap:12px;margin-bottom:24px">
                                    <button class="btn-secondary" id="status-draft" onclick="updateJobStatus('draft')">Lưu nháp</button>
                                    <button class="btn-secondary" id="status-public" onclick="updateJobStatus('public')" style="border-color:#10b981; color:#10b981">Công khai</button>
                                    <button class="btn-secondary" id="status-private" onclick="updateJobStatus('private')" style="border-color:#f59e0b; color:#f59e0b">Nội bộ</button>
                                    <button class="btn-secondary" id="status-closed" onclick="updateJobStatus('closed')" style="border-color:#ef4444; color:#ef4444">Đóng tin</button>
                                </div>

                                <div style="padding:20px; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb">
                                    <h4 style="margin-bottom:12px; font-size:14px">Thông tin chung</h4>
                                    <div id="jobSummaryContent" style="font-size:13px; line-height:1.8; color:#4b5563">
                                        <!-- Summary will be rendered here -->
                                    </div>
                                </div>
                                
                                <div style="margin-top:24px">
                                    <label class="form-label">Đường dẫn ứng tuyển công khai</label>
                                    <div style="display:flex; gap:8px">
                                        <input type="text" class="form-input" id="publicLink" readonly value="https://ahtkpi.vn/hrm/apply?id=<?=$job_id?>">
                                        <button class="btn-secondary" style="width:auto; padding:0 16px" onclick="window.open(document.getElementById('publicLink').value)">Xem trang</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;margin-top:24px;padding:24px 0;border-top:1px solid #e5e7eb">
                        <button class="btn-secondary" id="saveStayBtn" onclick="saveCurrentStep(false)">LƯU THAY ĐỔI</button>
                        <button class="btn-primary" id="saveNextBtn" style="width:auto" onclick="saveCurrentStep(true)">LƯU & TIẾP TỤC</button>
                    </div>
                    <div style="height:100px"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modals -->
<div class="modal-overlay" id="criterionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="font-size:16px">Thêm tiêu chí</h3>
            <button onclick="closeCriterionModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Chọn tiêu chí</label>
                <select class="form-select" id="modalCriterionId">
                    <option value="">-- Chọn tiêu chí --</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Điểm kỳ vọng</label>
                    <select class="form-select" id="modalExpectedScore">
                        <option value="1/5">1/5</option><option value="2/5">2/5</option><option value="3/5" selected>3/5</option><option value="4/5">4/5</option><option value="5/5">5/5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Trọng số</label>
                    <input type="number" class="form-input" id="modalWeight" value="1" min="1">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeCriterionModal()" class="btn-secondary" style="padding:8px 16px">Hủy</button>
            <button onclick="addSelectedCriterion()" class="top-btn primary" style="padding:8px 24px">Thêm</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="multiCriterionModal">
    <div class="modal" style="width:600px">
        <div class="modal-header">
            <h3 style="font-size:16px">Thêm nhiều tiêu chí</h3>
            <button onclick="closeMultiCriterionModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body" style="max-height:500px;overflow-y:auto">
            <div id="multiCriterionList"></div>
        </div>
        <div class="modal-footer">
            <button onclick="closeMultiCriterionModal()" class="btn-secondary" style="padding:8px 16px">Hủy</button>
            <button onclick="addMultiCriteria()" class="top-btn primary" style="padding:8px 24px">Xong</button>
        </div>
    </div>
</div>

<!-- Stage Settings Modal -->
<div class="modal-overlay" id="stageModal">
    <div class="modal" style="width:500px">
        <div class="modal-header">
            <h3 style="font-size:16px">Thiết lập giai đoạn</h3>
            <button onclick="closeStageModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="modalStageIndex">
            <div class="form-group">
                <label class="form-label">Tên giai đoạn</label>
                <input type="text" class="form-input" id="modalStageName">
            </div>
            <div class="form-group">
                <label class="form-label">Email tự động</label>
                <select class="form-select" id="modalStageEmailId">
                    <option value="0">-- Không gửi --</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Mẫu phỏng vấn</label>
                <select class="form-select" id="modalStageInterviewId">
                    <option value="0">-- Không chọn --</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Thời gian hoàn thành (giờ)</label>
                    <input type="number" class="form-input" id="modalStageDuration" value="24">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:24px">
                    <input type="checkbox" id="modalStageManualReview"> <span style="font-size:12px;font-weight:600">Review trước khi gửi</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeStageModal()" class="btn-secondary" style="padding:8px 16px">Hủy</button>
            <button onclick="saveStageModal()" class="top-btn primary" style="padding:8px 24px">Lưu lại</button>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
const jobId = <?=$job_id?>;
let allCriteriaGroups = [];
let selectedCriteria = [];
let hiringStages = [];
let emailTemplates = [];
let interviewTemplates = [];

async function loadAllData() {
    initEditors();
    try {
        // Load options
        const [deptRes, officeRes, poolRes, evalRes, jobRes, critRes, jobStagesRes, emailTemplatesRes, interviewTemplatesRes] = await Promise.all([
            fetch('/hrm/ajax-handler?action=get_depts'),
            fetch('/hrm/ajax-handler?action=get_offices'),
            fetch('/hrm/ajax-handler?action=get_talent_pools'),
            fetch('/hrm/ajax-handler?action=get_evaluation_data'),
            fetch('/hrm/ajax-handler?action=get_jobs&id=' + jobId),
            fetch('/hrm/ajax-handler?action=get_job_criteria&job_id=' + jobId),
            fetch('/hrm/ajax-handler?action=get_job_hiring_steps&job_id=' + jobId),
            fetch('/hrm/ajax-handler?action=get_email_templates'),
            fetch('/hrm/ajax-handler?action=get_interview_templates')
        ]);

        const depts = await deptRes.json();
        const dSelect = document.getElementById('departmentId');
        depts.forEach(d => { dSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`; });

        const offices = await officeRes.json();
        const oSelect = document.getElementById('office');
        offices.forEach(o => { oSelect.innerHTML += `<option value="${o.name}">${o.name}</option>`; });

        const pools = await poolRes.json();
        if (pools.success) {
            const pSelect = document.getElementById('talentPoolId');
            pools.data.forEach(p => { pSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
        }

        const evalData = await evalRes.json();
        if (evalData.success) {
            allCriteriaGroups = evalData.data;
            populateCriterionSelect();
        }

        // Populate Job Data
        const jobData = await jobRes.json();
        if (jobData.success && jobData.data.length > 0) {
            const j = jobData.data[0];
            document.getElementById('jobSubTitle').innerText = j.title + ' (#' + j.id + ')';
            document.getElementById('title').value = j.title;
            document.getElementById('jobCode').value = j.job_code;
            document.getElementById('departmentId').value = j.department_id;
            document.getElementById('office').value = j.office;
            document.getElementById('salaryFrom').value = j.salary_from;
            document.getElementById('salaryTo').value = j.salary_to;
            document.getElementById('currency').value = j.currency;
            document.getElementById('showSalary').checked = j.show_salary == 1;
            document.getElementById('quantity').value = j.quantity;
            document.getElementById('jobType').value = j.job_type;
            document.getElementById('deadline').value = j.deadline;
            document.getElementById('talentPoolId').value = j.talent_pool_id;
            document.getElementById('managers').value = j.managers;
            tinymce.get('jobDescription').setContent(j.job_description || '');
            tinymce.get('notes').setContent(j.notes || '');
            highlightStatus(j.status);
            updateJobSummary();
        }

        // Populate Criteria
        const criteriaData = await critRes.json();
        if (criteriaData.success) {
            selectedCriteria = criteriaData.criteria;
            renderSelectedCriteria();
            criteriaData.mandatory.forEach(m => addMandatoryRow(m.requirement_text));
        }

        // Populate Stages
        const stagesData = await jobStagesRes.json();
        if (stagesData.success) {
            hiringStages = stagesData.data;
            renderHiringStages();
        }

        const emailData = await emailTemplatesRes.json();
        if (emailData.success) emailTemplates = emailData.data;

        const interviewData = await interviewTemplatesRes.json();
        if (interviewData.success) interviewTemplates = interviewData.data;

        renderHiringStages();

        // Load Application Form Data
        const appRes = await fetch('/hrm/ajax-handler?action=get_job_application_form&job_id=' + jobId);
        const appData = await appRes.json();
        if (appData.success) {
            renderApplicationFields(appData.fields);
            renderCustomQuestions(appData.questions);
        }

    } catch (e) { console.error(e); }
}

function initEditors() {
    tinymce.init({
        selector: '#jobDescription, #notes',
        height: 250,
        menubar: false,
        branding: false,
        plugins: ['lists', 'link', 'image', 'code', 'table'],
        toolbar: 'undo redo | bold italic | bullist numlist | link image | code',
        content_style: 'body { font-family:Inter,sans-serif; font-size:14px }'
    });
}

function goToStep(step) {
    currentStep = step;
    document.querySelectorAll('.stepper-item').forEach((item, idx) => {
        item.classList.toggle('active', idx + 1 === currentStep);
    });
    for (let i = 1; i <= 7; i++) {
        const el = document.getElementById(`step-${i}-content`);
        if (el) el.style.display = (i === currentStep) ? 'block' : 'none';
    }
}

async function saveCurrentStep(next) {
    let success = false;
    if (currentStep === 1) success = await saveStep1();
    else if (currentStep === 2) success = await saveStep2();
    else if (currentStep === 3) success = await saveStep3();
    else if (currentStep === 4) success = await saveStep4();
    else if (currentStep === 7) success = await saveStep7();
    else success = true;

    if (success && next) {
        if (currentStep < 7) goToStep(currentStep + 1);
        else location.href = '/hrm/job-detail?id=' + jobId;
    } else if (success) {
        alert('Đã lưu thay đổi');
    }
}

async function saveStep1() {
    const data = {
        id: jobId,
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
        notes: tinymce.get('notes').getContent()
    };
    try {
        const res = await fetch('/hrm/ajax-handler?action=save_job_post', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) updateJobSummary();
        return result.success;
    } catch (e) { return false; }
}

async function saveStep2() {
    const data = {
        job_id: jobId,
        criteria: selectedCriteria,
        mandatory: Array.from(document.querySelectorAll('.mandatory-row-input')).map(input => ({requirement_text: input.value}))
    };
    try {
        const res = await fetch('/hrm/ajax-handler?action=save_job_criteria', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        return result.success;
    } catch (e) { return false; }
}

async function saveStep3() {
    const data = {
        job_id: jobId,
        steps: hiringStages.map((s, idx) => ({
            name: document.getElementById(`stage-name-${idx}`).value,
            email_template_id: document.getElementById(`stage-email-${idx}`).value,
            interview_template_id: document.getElementById(`stage-interview-${idx}`).value,
            is_locked: s.is_locked || 0,
            stage_type: s.stage_type || 'standard'
        }))
    };
    try {
        const res = await fetch('/hrm/ajax-handler?action=save_job_hiring_steps', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            // Reload stages to get proper IDs/data
            const refresh = await fetch('/hrm/ajax-handler?action=get_job_hiring_steps&job_id=' + jobId);
            const refreshData = await refresh.json();
            if (refreshData.success) {
                hiringStages = refreshData.data;
                renderHiringStages();
            }
        }
        return result.success;
    } catch (e) { return false; }
}

// Reuse helper functions from job_post_create.php logic
function populateCriterionSelect() {
    const select = document.getElementById('modalCriterionId');
    select.innerHTML = '<option value="">-- Chọn tiêu chí --</option>';
    allCriteriaGroups.forEach(g => {
        const group = document.createElement('optgroup');
        group.label = g.name;
        g.criteria.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.innerText = c.criterion_text;
            group.appendChild(opt);
        });
        select.appendChild(group);
    });
}

function openCriterionModal() { document.getElementById('criterionModal').style.display = 'flex'; }
function closeCriterionModal() { document.getElementById('criterionModal').style.display = 'none'; }
function openMultiCriterionModal() {
    const container = document.getElementById('multiCriterionList');
    container.innerHTML = allCriteriaGroups.map(g => `
        <div style="margin-bottom:20px">
            <h4 style="font-size:12px;color:#374151;background:#f9fafb;padding:6px 12px;border-radius:4px;margin-bottom:12px;border:1px solid #e5e7eb">${g.name}</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                ${g.criteria.map(c => `
                    <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;padding:4px 0">
                        <input type="checkbox" class="multi-crit-cb" value="${c.id}" ${selectedCriteria.find(sc => sc.id == c.id) ? 'checked' : ''}>
                        <span style="color:#4b5563">${c.criterion_text}</span>
                    </label>
                `).join('')}
            </div>
        </div>
    `).join('');
    document.getElementById('multiCriterionModal').style.display = 'flex';
}
function closeMultiCriterionModal() { document.getElementById('multiCriterionModal').style.display = 'none'; }

function addSelectedCriterion() {
    const cid = document.getElementById('modalCriterionId').value;
    if (!cid || selectedCriteria.find(c => c.id == cid)) return;
    const criterion = findCriterion(cid);
    selectedCriteria.push({
        id: cid,
        criterion_text: criterion.criterion_text,
        group_name: criterion.group_name,
        expected_score: document.getElementById('modalExpectedScore').value,
        weight: document.getElementById('modalWeight').value
    });
    renderSelectedCriteria();
    closeCriterionModal();
}

function addMultiCriteria() {
    const cbs = document.querySelectorAll('.multi-crit-cb:checked');
    const newCriteria = [];
    cbs.forEach(cb => {
        const cid = cb.value;
        const existing = selectedCriteria.find(sc => sc.id == cid);
        if (existing) newCriteria.push(existing);
        else {
            const criterion = findCriterion(cid);
            newCriteria.push({ id: cid, criterion_text: criterion.criterion_text, group_name: criterion.group_name, expected_score: '3/5', weight: 1 });
        }
    });
    selectedCriteria = newCriteria;
    renderSelectedCriteria();
    closeMultiCriterionModal();
}

function findCriterion(id) {
    for (const g of allCriteriaGroups) {
        const c = g.criteria.find(crit => crit.id == id);
        if (c) return { ...c, group_name: g.name };
    }
    return null;
}

function renderSelectedCriteria() {
    const tbody = document.getElementById('selectedCriteriaBody');
    if (selectedCriteria.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:#9ca3af">Chưa có tiêu chí nào</td></tr>';
        return;
    }
    tbody.innerHTML = selectedCriteria.map((c, idx) => `
        <tr>
            <td><div style="font-weight:500">${c.criterion_text}</div><div style="font-size:11px;color:#9ca3af">${c.group_name}</div></td>
            <td><select class="form-select" onchange="selectedCriteria[${idx}].expected_score=this.value"><option value="1/5" ${c.expected_score==='1/5'?'selected':''}>1/5</option><option value="2/5" ${c.expected_score==='2/5'?'selected':''}>2/5</option><option value="3/5" ${c.expected_score==='3/5'?'selected':''}>3/5</option><option value="4/5" ${c.expected_score==='4/5'?'selected':''}>4/5</option><option value="5/5" ${c.expected_score==='5/5'?'selected':''}>5/5</option></select></td>
            <td><input type="number" class="form-input" value="${c.weight}" min="1" onchange="selectedCriteria[${idx}].weight=this.value"></td>
            <td style="text-align:center"><button onclick="selectedCriteria.splice(${idx},1);renderSelectedCriteria()" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:18px">&times;</button></td>
        </tr>
    `).join('');
}

function addMandatoryRow(text = '') {
    const container = document.getElementById('mandatoryRequirementsList');
    const div = document.createElement('div');
    div.className = 'mandatory-row';
    div.innerHTML = `<input type="text" class="form-input mandatory-row-input" value="${text}"><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:18px">&times;</button>`;
    container.appendChild(div);
}

function renderHiringStages() {
    const container = document.getElementById('hiringStagesList');
    if (!container) return;
    
    const standardStages = hiringStages.filter(s => !s.stage_type || s.stage_type === 'standard');
    const qualifiedStages = hiringStages.filter(s => ['offered', 'hired'].includes(s.stage_type));
    const rejectedStages = hiringStages.filter(s => s.stage_type === 'rejected');

    const renderStageRow = (s, idx, totalIdx) => {
        const emailName = s.email_template_id > 0 ? (emailTemplates.find(t => t.id == s.email_template_id)?.name || 'Mẫu đã chọn') : 'Nhấn để chọn mẫu';
        
        let iconHtml = `<div style="width:32px; height:32px; background:#f3f4f6; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#6b7280; font-size:13px">${totalIdx + 1}</div>`;
        
        if (s.stage_type === 'offered') {
            iconHtml = `<div style="color:#10b981"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg></div>`;
        } else if (s.stage_type === 'hired') {
            iconHtml = `<div style="color:#10b981"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>`;
        } else if (s.stage_type === 'rejected') {
            iconHtml = `<div style="color:#ef4444"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></div>`;
        }

        return `
            <div class="stage-item" style="display:flex; align-items:center; gap:20px; padding:16px; background:#fff; border-bottom:1px solid #f3f4f6; transition:all 0.2s">
                <div style="width:40px; display:flex; justify-content:center; flex-shrink:0">
                    ${iconHtml}
                </div>
                <div style="width:2px; height:40px; background:#f3f4f6; margin:0 10px"></div>
                <div style="flex:1">
                    <div style="font-size:11px; color:#9ca3af; margin-bottom:4px">Bước ${totalIdx + 1}</div>
                    <div style="font-weight:600; font-size:15px; color:#111827">${s.name}</div>
                </div>
                <div style="flex:1.5">
                    <div style="font-size:11px; color:#9ca3af; margin-bottom:4px">Mẫu email hoặc phỏng vấn <svg style="display:inline; vertical-align:middle" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                    <div style="color:#2563eb; cursor:pointer; font-size:13px; text-decoration:underline; text-underline-offset:4px; border-bottom:1px dotted #2563eb" onclick="openStageModal(${totalIdx})">${emailName}</div>
                </div>
                <div style="display:flex; gap:12px; align-items:center">
                    ${!s.is_locked ? `
                        <div style="display:flex; gap:4px">
                            <button class="top-btn" style="background:#fff; border-color:#e5e7eb; color:#6b7280; padding:4px 8px; font-size:10px" onclick="moveStage(${totalIdx}, -1)">↑</button>
                            <button class="top-btn" style="background:#fff; border-color:#e5e7eb; color:#6b7280; padding:4px 8px; font-size:10px" onclick="moveStage(${totalIdx}, 1)">↓</button>
                        </div>
                        <button class="top-btn" style="background:#f9fafb; border-color:#e5e7eb; color:#6b7280; padding:6px" onclick="openStageModal(${totalIdx})"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
                        <button class="top-btn" style="background:#fff; border-color:#ef4444; color:#ef4444; padding:6px" onclick="removeStage(${totalIdx})"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg></button>
                    ` : `<button class="top-btn" style="background:#f9fafb; border-color:#e5e7eb; color:#6b7280; padding:6px" onclick="openStageModal(${totalIdx})"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>`}
                </div>
            </div>
        `;
    };

    let html = `
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden">
            <div style="padding:16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center">
                <div style="font-weight:700; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px">Quy trình tuyển dụng tiêu chuẩn</div>
                <button class="btn-secondary" style="font-size:11px; padding:4px 12px" onclick="resetToDefaultWorkflow()">Khôi phục quy trình chuẩn</button>
            </div>
            <div id="standardStagesContainer">
                ${standardStages.map((s, idx) => renderStageRow(s, idx, hiringStages.indexOf(s))).join('')}
            </div>
            <div style="padding:12px; background:#fff">
                <button class="top-btn" style="background:#fff; border:1px dashed #d1d5db; color:#374151; width:100%; padding:10px; border-radius:6px; font-weight:600; font-size:13px" onclick="addStageRow()">+ Thêm giai đoạn mới</button>
            </div>
            
            <div style="padding:16px; background:#f9fafb; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; font-weight:700; font-size:12px; color:#10b981; text-transform:uppercase; letter-spacing:0.5px">Đạt yêu cầu</div>
            <div>
                ${qualifiedStages.map((s, idx) => renderStageRow(s, idx, hiringStages.indexOf(s))).join('')}
            </div>

            <div style="padding:16px; background:#f9fafb; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; font-weight:700; font-size:12px; color:#ef4444; text-transform:uppercase; letter-spacing:0.5px">Đã từ chối</div>
            <div>
                ${rejectedStages.map((s, idx) => renderStageRow(s, idx, hiringStages.indexOf(s))).join('')}
            </div>
        </div>
    `;

    container.innerHTML = html;
}

function openStageModal(idx) {
    const s = hiringStages[idx];
    document.getElementById('modalStageIndex').value = idx;
    document.getElementById('modalStageName').value = s.name;
    document.getElementById('modalStageDuration').value = s.duration || 24;
    document.getElementById('modalStageManualReview').checked = s.manual_review == 1;
    
    // Fill emails
    const eSelect = document.getElementById('modalStageEmailId');
    eSelect.innerHTML = '<option value="0">-- Không gửi --</option>';
    emailTemplates.forEach(t => {
        eSelect.innerHTML += `<option value="${t.id}" ${s.email_template_id == t.id ? 'selected' : ''}>[Email] ${t.name}</option>`;
    });
    
    // Fill interviews
    const iSelect = document.getElementById('modalStageInterviewId');
    iSelect.innerHTML = '<option value="0">-- Không chọn --</option>';
    interviewTemplates.forEach(t => {
        iSelect.innerHTML += `<option value="${t.id}" ${s.interview_template_id == t.id ? 'selected' : ''}>[Phỏng vấn] ${t.name}</option>`;
    });

    document.getElementById('stageModal').classList.add('active');
}

function closeStageModal() {
    document.getElementById('stageModal').classList.remove('active');
}

function saveStageModal() {
    const idx = document.getElementById('modalStageIndex').value;
    const s = hiringStages[idx];
    s.name = document.getElementById('modalStageName').value;
    s.email_template_id = document.getElementById('modalStageEmailId').value;
    s.interview_template_id = document.getElementById('modalStageInterviewId').value;
    s.duration = document.getElementById('modalStageDuration').value;
    s.manual_review = document.getElementById('modalStageManualReview').checked ? 1 : 0;
    
    renderHiringStages();
    closeStageModal();
}

async function resetToDefaultWorkflow() {
    if (!confirm('Bạn có chắc chắn muốn xóa quy trình hiện tại và khôi phục về quy trình chuẩn trong cài đặt?')) return;
    
    try {
        const res = await fetch('/hrm/ajax-handler?action=reset_job_hiring_steps', {
            method: 'POST',
            body: JSON.stringify({ job_id: jobId })
        });
        const result = await res.json();
        if (result.success) {
            // Re-load steps
            const res2 = await fetch(`/hrm/ajax-handler?action=get_job_hiring_steps&job_id=${jobId}`);
            const result2 = await res2.json();
            if (result2.success) {
                hiringStages = result2.data;
                renderHiringStages();
                showToast('Đã khôi phục quy trình chuẩn');
            }
        }
    } catch (e) {
        console.error(e);
        showToast('Lỗi khi khôi phục quy trình', 'error');
    }
}

function moveStage(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= hiringStages.length) return;
    if (hiringStages[idx].is_locked || hiringStages[newIdx].is_locked) return;
    
    const temp = hiringStages[idx];
    hiringStages[idx] = hiringStages[newIdx];
    hiringStages[newIdx] = temp;
    renderHiringStages();
}

function addStageRow() {
    hiringStages.splice(hiringStages.findIndex(s => s.is_locked), 0, { name: 'Giai đoạn mới', email_template_id: 0, interview_template_id: 0, is_locked: 0, stage_type: 'standard' });
    renderHiringStages();
}

function removeStage(idx) {
    if (hiringStages[idx].is_locked) return;
    hiringStages.splice(idx, 1);
    renderHiringStages();
}

function moveStage(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= hiringStages.length || hiringStages[newIdx].is_locked || hiringStages[idx].is_locked) return;
    const temp = hiringStages[idx];
    hiringStages[idx] = hiringStages[newIdx];
    hiringStages[newIdx] = temp;
    renderHiringStages();
}

function renderApplicationFields(fields) {
    const tbody = document.getElementById('appFieldsBody');
    const fieldLabels = {
        'full_name': 'Họ và tên',
        'email': 'Email',
        'phone': 'Số điện thoại',
        'cv': 'CV (Đính kèm file)',
        'cover_letter': 'Thư giới thiệu (Cover Letter)',
        'photo': 'Ảnh chân dung',
        'linkedin': 'Link LinkedIn',
        'github': 'Link GitHub',
        'website': 'Website/Portfolio',
        'address': 'Địa chỉ hiện tại',
        'gender': 'Giới tính',
        'dob': 'Ngày sinh',
        'hometown': 'Quê quán',
        'current_residence': 'Nơi ở hiện nay'
    };

    tbody.innerHTML = Object.keys(fieldLabels).map(key => {
        const f = fields.find(field => field.field_name === key) || { is_show: 1, is_required: 0 };
        const isAlwaysReq = (key === 'full_name' || key === 'email' || key === 'cv');
        
        return `
            <tr>
                <td style="font-size:13px;font-weight:500">${fieldLabels[key]}</td>
                <td style="text-align:center">
                    <input type="checkbox" class="app-field-show" data-field="${key}" ${f.is_show ? 'checked' : ''} ${isAlwaysReq ? 'disabled' : ''}>
                </td>
                <td style="text-align:center">
                    <input type="checkbox" class="app-field-req" data-field="${key}" ${f.is_required ? 'checked' : ''} ${isAlwaysReq ? 'disabled' : ''}>
                </td>
            </tr>
        `;
    }).join('');
}

function renderCustomQuestions(questions) {
    const container = document.getElementById('customQuestionsList');
    container.innerHTML = questions.map((q, idx) => `
        <div class="form-section" style="margin-bottom:12px; border-style:dashed" id="q-row-${idx}">
            <div class="section-body" style="padding:16px">
                <div class="form-group">
                    <label class="form-label">Câu hỏi</label>
                    <input type="text" class="form-input q-text" value="${q.question_text}" placeholder="Nhập nội dung câu hỏi">
                </div>
                <div class="form-row" style="margin-bottom:0">
                    <div class="form-group">
                        <label class="form-label">Loại câu hỏi</label>
                        <select class="form-select q-type" onchange="toggleQOptions(${idx})">
                            <option value="text" ${q.question_type==='text'?'selected':''}>Văn bản ngắn</option>
                            <option value="longtext" ${q.question_type==='longtext'?'selected':''}>Văn bản dài</option>
                            <option value="select" ${q.question_type==='select'?'selected':''}>Nhiều lựa chọn (Dropdown)</option>
                            <option value="checkbox" ${q.question_type==='checkbox'?'selected':''}>Đánh dấu (Checkbox)</option>
                        </select>
                    </div>
                    <div class="form-group q-options-group" style="display: ${['select','checkbox'].includes(q.question_type) ? 'block' : 'none'}">
                        <label class="form-label">Các lựa chọn (phân cách bằng dấu phẩy)</label>
                        <input type="text" class="form-input q-options" value="${q.options || ''}">
                    </div>
                    <div class="form-group" style="flex:0; white-space:nowrap; display:flex; align-items:center; gap:8px; padding-top:24px">
                        <input type="checkbox" class="q-req" ${q.is_required ? 'checked' : ''}> <span style="font-size:12px">Bắt buộc</span>
                        <button class="top-btn" style="background:#fff; border-color:#ef4444; color:#ef4444; padding:8px" onclick="this.closest('.form-section').remove()">&times;</button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function addCustomQuestion() {
    const container = document.getElementById('customQuestionsList');
    const idx = container.children.length;
    const div = document.createElement('div');
    div.className = 'form-section';
    div.style.marginBottom = '12px';
    div.style.borderStyle = 'dashed';
    div.innerHTML = `
        <div class="section-body" style="padding:16px">
            <div class="form-group">
                <label class="form-label">Câu hỏi</label>
                <input type="text" class="form-input q-text" placeholder="Nhập nội dung câu hỏi">
            </div>
            <div class="form-row" style="margin-bottom:0">
                <div class="form-group">
                    <label class="form-label">Loại câu hỏi</label>
                    <select class="form-select q-type" onchange="this.closest('.form-section').querySelector('.q-options-group').style.display = (['select','checkbox'].includes(this.value) ? 'block' : 'none')">
                        <option value="text">Văn bản ngắn</option>
                        <option value="longtext">Văn bản dài</option>
                        <option value="select">Nhiều lựa chọn (Dropdown)</option>
                        <option value="checkbox">Đánh dấu (Checkbox)</option>
                    </select>
                </div>
                <div class="form-group q-options-group" style="display:none">
                    <label class="form-label">Các lựa chọn (phân cách bằng dấu phẩy)</label>
                    <input type="text" class="form-input q-options">
                </div>
                <div class="form-group" style="flex:0; white-space:nowrap; display:flex; align-items:center; gap:8px; padding-top:24px">
                    <input type="checkbox" class="q-req"> <span style="font-size:12px">Bắt buộc</span>
                    <button class="top-btn" style="background:#fff; border-color:#ef4444; color:#ef4444; padding:8px" onclick="this.closest('.form-section').remove()">&times;</button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(div);
}

function toggleQOptions(idx) {
    const row = document.getElementById(`q-row-${idx}`);
    const type = row.querySelector('.q-type').value;
    row.querySelector('.q-options-group').style.display = (['select','checkbox'].includes(type) ? 'block' : 'none');
}

async function saveStep4() {
    const fields = Array.from(document.querySelectorAll('.app-field-show')).map(cb => ({
        field_name: cb.dataset.field,
        is_show: cb.checked ? 1 : 0,
        is_required: document.querySelector(`.app-field-req[data-field="${cb.dataset.field}"]`).checked ? 1 : 0
    }));

    const questions = Array.from(document.getElementById('customQuestionsList').children).map(div => ({
        question_text: div.querySelector('.q-text').value,
        question_type: div.querySelector('.q-type').value,
        options: div.querySelector('.q-options').value,
        is_required: div.querySelector('.q-req').checked ? 1 : 0
    })).filter(q => q.question_text.trim() !== '');

    const data = { job_id: jobId, fields, questions };

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_job_application_form', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        return result.success;
    } catch (e) { return false; }
}

async function saveStep7() {
    return true; // Status is saved separately
}

function updateJobSummary() {
    const title = document.getElementById('title').value;
    const dept = document.getElementById('departmentId').options[document.getElementById('departmentId').selectedIndex]?.text || 'Chưa chọn';
    const office = document.getElementById('office').value || 'Chưa chọn';
    const deadline = document.getElementById('deadline').value || 'Không có';
    
    document.getElementById('jobSummaryContent').innerHTML = `
        <div><strong>Tiêu đề:</strong> ${title}</div>
        <div><strong>Phòng ban:</strong> ${dept}</div>
        <div><strong>Văn phòng:</strong> ${office}</div>
        <div><strong>Thời hạn:</strong> ${deadline}</div>
        <div><strong>Số lượng:</strong> ${document.getElementById('quantity').value || 0}</div>
        <div><strong>Tiêu chí:</strong> ${selectedCriteria.length} tiêu chí đã chọn</div>
        <div><strong>Giai đoạn:</strong> ${hiringStages.length} giai đoạn đã thiết lập</div>
    `;
}

async function updateJobStatus(status) {
    try {
        const res = await fetch('/hrm/ajax-handler?action=update_job_status', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id: jobId, status: status })
        });
        const result = await res.json();
        if (result.success) {
            alert('Đã cập nhật trạng thái: ' + status);
            highlightStatus(status);
        }
    } catch (e) { console.error(e); }
}

function highlightStatus(status) {
    const btns = ['draft', 'public', 'private', 'closed'];
    btns.forEach(b => {
        const el = document.getElementById('status-' + b);
        if (el) {
            el.style.background = (b === status) ? '#e5e7eb' : '#fff';
            el.style.fontWeight = (b === status) ? '700' : '400';
        }
    });
}

document.addEventListener('DOMContentLoaded', loadAllData);
</script>
</body>
</html>
