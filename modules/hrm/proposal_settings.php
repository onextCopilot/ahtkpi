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
<title>Cấu hình đề xuất – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-inner-body{display:flex;flex:1;overflow-y:auto;padding:0;flex-direction:column;background:#fff}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}

.page-header{padding:24px 24px 0;background:#fff}
.page-title{font-size:18px;font-weight:700;color:#111827;margin-bottom:16px}
.tabs-nav{display:flex;gap:24px;border-bottom:1px solid #e5e7eb}
.tab-item{padding:12px 0;font-size:12px;font-weight:700;color:#6b7280;cursor:pointer;text-transform:uppercase;position:relative}
.tab-item.active{color:#2563eb}
.tab-item.active::after{content:'';position:absolute;bottom:-1px;left:0;width:100%;height:2px;background:#2563eb}

.page-content{padding:24px;max-width:1000px;margin:0 auto;width:100%}
.settings-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:24px;overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center}
.card-title-group{display:flex;flex-direction:column}
.card-title{font-size:15px;font-weight:700;color:#111827}
.card-subtitle{font-size:12px;color:#6b7280;margin-top:4px}
.card-actions{display:flex;align-items:center;gap:12px}
.action-link{font-size:13px;color:#2563eb;text-decoration:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:color 0.2s}
.action-link:hover{color:#1d4ed8}

.card-body{padding:20px}
.empty-state{padding:40px 0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;text-align:center}
.empty-icon{width:48px;height:48px;margin-bottom:12px;opacity:0.3}

.form-row{display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f9fafb}
.form-row:last-child{border-bottom:none}
.form-label-col{flex:1;max-width:300px}
.form-label{font-size:13px;color:#374151;font-weight:500}
.form-input-col{flex:1}
.form-select{width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;outline:none;transition:border-color 0.2s}
.form-select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.1)}

/* BUTTONS */
.btn-blue {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 7px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.btn-blue:hover {
    background: #1d4ed8;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.btn-blue:active {
    transform: translateY(1px);
}

.btn-save {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-save:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 6px rgba(37,99,235,0.2);
}

.btn-cancel {
    background: #fff;
    border: 1px solid #d1d5db;
    color: #4b5563;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-cancel:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.btn-gray {
    background: #f3f4f6;
    color: #4b5563;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-gray:hover {
    background: #e5e7eb;
}

.btn-green {
    background: #10b981;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-green:hover {
    background: #059669;
    box-shadow: 0 4px 6px rgba(16,185,129,0.2);
}

/* DROPDOWN */
.add-dropdown{position:relative;display:inline-block}
.dropdown-menu{position:absolute;top:100%;right:0;background:#fff;border:1px solid #e5e7eb;box-shadow:0 4px 12px rgba(0,0,0,0.1);border-radius:6px;display:none;z-index:100;min-width:200px;margin-top:8px}
.dropdown-menu.active{display:block}
.dropdown-item{padding:10px 16px;font-size:13px;color:#374151;cursor:pointer;border-bottom:1px solid #f3f4f6}
.dropdown-item:last-child{border-bottom:none}
.dropdown-item:hover{background:#f9fafb;color:#2563eb}

/* AUTOCOMPLETE */
.autocomplete-container{position:relative}
.autocomplete-results{position:absolute;top:100%;left:0;width:100%;background:#fff;border:1px solid #d1d5db;border-top:none;border-radius:0 0 6px 6px;box-shadow:0 4px 6px rgba(0,0,0,0.1);display:none;z-index:2100;max-height:200px;overflow-y:auto}
.autocomplete-results.active{display:block}
.result-item{padding:8px 12px;display:flex;align-items:center;gap:10px;cursor:pointer;border-bottom:1px solid #f3f4f6}
.result-item:last-child{border-bottom:none}
.result-item:hover{background:#f0f7ff}
.result-avatar{width:24px;height:24px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700}
.result-info{display:flex;flex-direction:column}
.result-name{font-size:13px;font-weight:600;color:#111827}
.result-email{font-size:11px;color:#6b7280}
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
                <h1 class="page-title">Cấu hình đề xuất</h1>
                <div class="tabs-nav">
                    <div class="tab-item active" onclick="switchTab('recruitment')">ĐỀ XUẤT TUYỂN DỤNG</div>
                    <div class="tab-item" onclick="switchTab('hiring')">ĐỀ XUẤT NHẬN TUYỂN</div>
                </div>
            </div>

            <div class="page-content" id="tab-recruitment">
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-title">Người phê duyệt</span>
                            <span class="card-subtitle">Danh sách các khối người duyệt theo thứ tự của nhóm đề xuất</span>
                        </div>
                        <div class="card-actions">
                            <div class="add-dropdown">
                                <span class="action-link" onclick="toggleAddDropdown(event)">+ Thêm <span style="font-size:10px">▼</span></span>
                                <div class="dropdown-menu">
                                    <div class="dropdown-item" onclick="openModal('fixed')">Người duyệt cố định</div>
                                    <div class="dropdown-item" onclick="openModal('manager')">Quản lý trực tiếp</div>
                                    <div class="dropdown-item" onclick="openModal('dynamic')">Người duyệt linh động</div>
                                    <div class="dropdown-item" onclick="openModal('conditional')">Người duyệt theo điều kiện</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
                            Chưa cài đặt danh sách duyệt
                        </div>
                    </div>
                </div>

                <div class="settings-card" id="workflow-recruitment">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-title">Luồng phê duyệt</span>
                            <span class="card-subtitle">Thiết lập luồng phê duyệt đề xuất</span>
                        </div>
                        <div class="card-actions">
                            <button class="btn-blue" onclick="saveSettings()">Lưu lại</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-label-col"><span class="form-label">Luồng phê duyệt</span></div>
                            <div class="form-input-col">
                                <select class="form-select" name="approval_flow">
                                    <option value="sequential">Duyệt tuần tự</option>
                                    <option value="parallel">Duyệt song song</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-label-col"><span class="form-label">Ưu tiên vai trò người duyệt</span></div>
                            <div class="form-input-col">
                                <select class="form-select" name="role_priority">
                                    <option value="last">Ưu tiên vai trò duyệt của khối xuất hiện sau nhất</option>
                                    <option value="first">Ưu tiên vai trò duyệt của khối xuất hiện đầu tiên</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-label-col"><span class="form-label">Cho phép HRM sửa đề xuất sau khi đã duyệt hoàn toàn</span></div>
                            <div class="form-input-col">
                                <select class="form-select" name="hrm_edit_after_approval">
                                    <option value="0">Không cho phép</option>
                                    <option value="1">Cho phép</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-title">Người theo dõi</span>
                            <span class="card-subtitle">Người theo dõi có thể xem các đề xuất</span>
                        </div>
                        <div class="card-actions">
                            <span class="action-link" onclick="openAddFollower()">+ Thêm</span>
                        </div>
                    </div>
                    <div class="card-body" id="followers-recruitment">
                        <div class="empty-state">
                            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-content" id="tab-hiring" style="display:none">
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-title">Người phê duyệt</span>
                            <span class="card-subtitle">Danh sách các khối người duyệt theo thứ tự của nhóm đề xuất nhận tuyển</span>
                        </div>
                        <div class="card-actions">
                            <div class="add-dropdown">
                                <span class="action-link" onclick="toggleAddDropdown(event)">+ Thêm <span style="font-size:10px">▼</span></span>
                                <div class="dropdown-menu">
                                    <div class="dropdown-item" onclick="openModal('fixed')">Người duyệt cố định</div>
                                    <div class="dropdown-item" onclick="openModal('manager')">Quản lý trực tiếp</div>
                                    <div class="dropdown-item" onclick="openModal('dynamic')">Người duyệt linh động</div>
                                    <div class="dropdown-item" onclick="openModal('conditional')">Người duyệt theo điều kiện</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
                            Chưa cài đặt danh sách duyệt cho đề xuất nhận tuyển
                        </div>
                    </div>
                </div>
                
                <div class="settings-card" id="workflow-hiring">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-title">Luồng phê duyệt</span>
                            <span class="card-subtitle">Thiết lập luồng phê duyệt đề xuất nhận tuyển</span>
                        </div>
                        <div class="card-actions">
                            <button class="btn-blue" onclick="saveSettings()">Lưu lại</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-label-col"><span class="form-label">Luồng phê duyệt</span></div>
                            <div class="form-input-col">
                                <select class="form-select" name="approval_flow">
                                    <option value="sequential">Duyệt tuần tự</option>
                                    <option value="parallel">Duyệt song song</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-label-col"><span class="form-label">Ưu tiên vai trò người duyệt</span></div>
                            <div class="form-input-col">
                                <select class="form-select" name="role_priority">
                                    <option value="last">Ưu tiên vai trò duyệt của khối xuất hiện sau nhất</option>
                                    <option value="first">Ưu tiên vai trò duyệt của khối xuất hiện đầu tiên</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-label-col"><span class="form-label">Cho phép HRM sửa đề xuất sau khi đã duyệt hoàn toàn</span></div>
                            <div class="form-input-col">
                                <select class="form-select" name="hrm_edit_after_approval">
                                    <option value="0">Không cho phép</option>
                                    <option value="1">Cho phép</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-title">Người theo dõi</span>
                            <span class="card-subtitle">Người theo dõi có thể xem các đề xuất nhận tuyển</span>
                        </div>
                        <div class="card-actions">
                            <span class="action-link" onclick="openAddFollower()">+ Thêm</span>
                        </div>
                    </div>
                    <div class="card-body" id="followers-hiring">
                        <div class="empty-state">
                            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="eh-sidebar-right" id="add-item-sidebar">
    <div class="sidebar-header-right">
        <div class="sidebar-title" id="sidebar-title">Thêm người theo dõi</div>
        <div class="sidebar-close" onclick="closeSidebar()" style="cursor:pointer;font-size:20px">&times;</div>
    </div>
    <div class="sidebar-body-right">
        <div class="form-group" style="margin-bottom:20px">
            <label class="form-label" style="display:block;font-size:13px;font-weight:600;margin-bottom:8px">Chọn người dùng</label>
            <div class="autocomplete-container">
                <input type="text" class="form-select user-search-input" id="follower-search" data-user-id="" placeholder="Tìm kiếm tên hoặc email..." autocomplete="off">
                <div class="autocomplete-results"></div>
            </div>
        </div>
    </div>
    <div class="sidebar-footer-right">
        <button class="btn-cancel" onclick="closeSidebar()">Hủy</button>
        <button class="btn-save" onclick="saveFollower()">Lưu lại</button>
    </div>
</div>

<!-- MODAL FIXED APPROVER -->
<div class="eh-modal-overlay" id="modal-fixed">
    <div class="eh-modal">
        <div class="modal-header">
            <div class="modal-title">THÊM NGƯỜI DUYỆT CỐ ĐỊNH</div>
            <div class="sidebar-close" onclick="closeAllModals()">&times;</div>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Tên khối người duyệt</label>
                <input type="text" class="form-select" id="fixed-block-name" placeholder="Vai trò chung của khối người duyệt. VD: Ban giám đốc">
            </div>
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Người duyệt *</label>
                <div class="autocomplete-container">
                    <input type="text" class="form-select user-search-input" id="fixed-user-search" data-user-id="" placeholder="Nhập @ để tag" autocomplete="off">
                    <div class="autocomplete-results"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">SLA cho người duyệt tính theo giờ</label>
                <input type="text" class="form-select" id="fixed-sla" placeholder="SLA cho người duyệt tính theo giờ">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeAllModals()">Bỏ qua</button>
            <button class="btn-green" onclick="saveApprover('fixed')">Thêm</button>
        </div>
    </div>
</div>

<!-- MODAL MANAGER -->
<div class="eh-modal-overlay" id="modal-manager">
    <div class="eh-modal">
        <div class="modal-header">
            <div class="modal-title">THÊM QUẢN LÝ TRỰC TIẾP</div>
            <div class="sidebar-close" onclick="closeAllModals()">&times;</div>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Chỉ cho phép chọn quản lý trực tiếp được thiết lập trong Base Account</label>
                <select class="form-select" id="manager-restrict">
                    <option value="0">Không</option>
                    <option value="1">Có</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">SLA cho người duyệt tính theo giờ</label>
                <input type="text" class="form-select" id="manager-sla" placeholder="SLA cho người duyệt tính theo giờ">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeAllModals()">Bỏ qua</button>
            <button class="btn-green" onclick="saveApprover('manager')">Tạo mới</button>
        </div>
    </div>
</div>

<!-- MODAL DYNAMIC -->
<div class="eh-modal-overlay" id="modal-dynamic">
    <div class="eh-modal">
        <div class="modal-header">
            <div class="modal-title">THÊM NGƯỜI DUYỆT LINH ĐỘNG</div>
            <div class="sidebar-close" onclick="closeAllModals()">&times;</div>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Tên khối người duyệt *</label>
                <input type="text" class="form-select" id="dynamic-name" placeholder="Vai trò chung của khối người duyệt. VD: Ban giám đốc">
            </div>
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Chỉ cho phép chọn người duyệt linh động trong danh sách đã thiết lập</label>
                <select class="form-select" id="dynamic-restrict">
                    <option value="0">Không</option>
                    <option value="1">Có</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Bắt buộc gửi đề xuất đến khối người duyệt linh động này</label>
                <select class="form-select" id="dynamic-required">
                    <option value="0">Không</option>
                    <option value="1">Có</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">SLA cho người duyệt tính theo giờ</label>
                <input type="text" class="form-select" id="dynamic-sla" placeholder="SLA cho người duyệt tính theo giờ">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeAllModals()">Bỏ qua</button>
            <button class="btn-green" onclick="saveApprover('dynamic')">Thêm</button>
        </div>
    </div>
</div>

<!-- MODAL CONDITIONAL -->
<div class="eh-modal-overlay" id="modal-conditional">
    <div class="eh-modal">
        <div class="modal-header">
            <div class="modal-title">THÊM NGƯỜI DUYỆT THEO ĐIỀU KIỆN</div>
            <div class="sidebar-close" onclick="closeAllModals()">&times;</div>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Tên khối người duyệt *</label>
                <input type="text" class="form-select" id="conditional-name" placeholder="Vai trò chung của khối người duyệt. VD: Ban giám đốc">
            </div>
            <div class="form-group">
                <label class="form-label" style="display:block;font-size:12px;font-weight:700;margin-bottom:8px">Lựa chọn người duyệt khi đề xuất đáp ứng nhiều điều kiện cùng lúc</label>
                <select class="form-select" id="conditional-mode">
                    <option value="all">Chọn người duyệt ở tất cả điều kiện thoả mãn</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeAllModals()">Bỏ qua</button>
            <button class="btn-green" onclick="saveApprover('conditional')">Tạo mới</button>
        </div>
    </div>
</div>

<script>
let currentProposalType = 'recruitment';

function switchTab(tab) {
    currentProposalType = tab;
    document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.page-content').forEach(el => el.style.display = 'none');
    
    if (tab === 'recruitment') {
        document.querySelector('.tab-item:nth-child(1)').classList.add('active');
        document.getElementById('tab-recruitment').style.display = 'block';
    } else {
        document.querySelector('.tab-item:nth-child(2)').classList.add('active');
        document.getElementById('tab-hiring').style.display = 'block';
    }
    loadApprovers();
    loadSettings();
    loadFollowers();
}

function toggleAddDropdown(e) {
    e.stopPropagation();
    document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('active'));
    const menu = e.target.closest('.add-dropdown').querySelector('.dropdown-menu');
    menu.classList.toggle('active');
}

function openModal(type) {
    closeAllModals();
    document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('active'));
    document.getElementById('modal-' + type).classList.add('active');
}

function openAddFollower() {
    document.getElementById('sidebar-title').innerText = 'Thêm người theo dõi';
    document.getElementById('sidebar-overlay').classList.add('active');
    document.getElementById('add-item-sidebar').classList.add('active');
}

function closeSidebar() {
    document.getElementById('sidebar-overlay').classList.remove('active');
    document.getElementById('add-item-sidebar').classList.remove('active');
}

function closeAllModals() {
    document.querySelectorAll('.eh-modal-overlay').forEach(el => el.classList.remove('active'));
}

window.onclick = function(event) {
    if (!event.target.matches('.action-link')) {
        document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('active'));
    }
    if (!event.target.closest('.autocomplete-container')) {
        document.querySelectorAll('.autocomplete-results').forEach(el => el.classList.remove('active'));
    }
}

async function saveApprover(atype) {
    let payload = {
        proposal_type: currentProposalType,
        approver_type: atype
    };

    if (atype === 'fixed') {
        payload.block_name = document.getElementById('fixed-block-name').value;
        payload.user_id = document.getElementById('fixed-user-search').dataset.userId;
        payload.sla_hours = document.getElementById('fixed-sla').value;
        if (!payload.user_id) { alert('Vui lòng chọn người duyệt'); return; }
    } else if (atype === 'manager') {
        payload.metadata = JSON.stringify({ restrict: document.getElementById('manager-restrict').value });
        payload.sla_hours = document.getElementById('manager-sla').value;
    } else if (atype === 'dynamic') {
        payload.block_name = document.getElementById('dynamic-name').value;
        payload.metadata = JSON.stringify({ restrict: document.getElementById('dynamic-restrict').value, required: document.getElementById('dynamic-required').value });
        payload.sla_hours = document.getElementById('dynamic-sla').value;
        if (!payload.block_name) { alert('Vui lòng nhập tên khối'); return; }
    } else if (atype === 'conditional') {
        payload.block_name = document.getElementById('conditional-name').value;
        payload.metadata = JSON.stringify({ mode: document.getElementById('conditional-mode').value });
        if (!payload.block_name) { alert('Vui lòng nhập tên khối'); return; }
    }

    const res = await fetch('/hrm/ajax-handler?action=add_proposal_approver', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
        closeAllModals();
        loadApprovers();
        // Clear inputs
        document.querySelectorAll('.eh-modal input').forEach(el => el.value = '');
        document.querySelectorAll('.user-search-input').forEach(el => el.dataset.userId = '');
    }
}

async function loadApprovers() {
    const res = await fetch('/hrm/ajax-handler?action=get_proposal_approvers&type=' + currentProposalType);
    const json = await res.json();
    if (json.success) {
        renderApproversList(json.data);
    }
}

function renderApproversList(data) {
    const container = document.querySelector('#tab-' + currentProposalType + ' .settings-card:first-child .card-body');
    if (data.length === 0) {
        container.innerHTML = `<div class="empty-state"><svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>${currentProposalType === 'recruitment' ? 'Chưa cài đặt danh sách duyệt' : 'Chưa cài đặt danh sách duyệt cho đề xuất nhận tuyển'}</div>`;
        return;
    }

    container.innerHTML = data.map((item, index) => `
        <div class="form-row" style="padding:16px;background:#f9fafb;border-radius:8px;margin-bottom:12px;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:24px;height:24px;background:#e5e7eb;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#6b7280">${index + 1}</div>
                <div style="display:flex;flex-direction:column">
                    <span style="font-size:13px;font-weight:700;color:#111827">${getApproverTitle(item)}</span>
                    <span style="font-size:12px;color:#6b7280">${getApproverDetail(item)}</span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:16px">
                ${item.sla_hours ? `<span style="font-size:12px;color:#ef4444;font-weight:600">SLA: ${item.sla_hours}h</span>` : ''}
                <span class="action-link" style="color:#ef4444" onclick="deleteApprover(${item.id})">Xóa</span>
            </div>
        </div>
    `).join('');
}

function getApproverTitle(item) {
    if (item.approver_type === 'fixed') return item.full_name;
    if (item.approver_type === 'manager') return 'Quản lý trực tiếp';
    if (item.approver_type === 'dynamic') return item.block_name || 'Khối người duyệt linh động';
    if (item.approver_type === 'conditional') return item.block_name || 'Người duyệt theo điều kiện';
    return '';
}

function getApproverDetail(item) {
    if (item.approver_type === 'fixed') return item.block_name || 'Người duyệt cố định';
    if (item.approver_type === 'manager') return 'Người duyệt theo cấp quản lý';
    return 'Duyệt theo cấu hình riêng';
}

async function deleteApprover(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa người duyệt này?')) return;
    const res = await fetch('/hrm/ajax-handler?action=delete_proposal_approver', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    });
    if ((await res.json()).success) loadApprovers();
}

async function saveFollower() {
    const userId = document.getElementById('follower-search').dataset.userId;
    console.log('Saving follower:', userId, currentProposalType);
    if (!userId) { alert('Vui lòng chọn người dùng'); return; }

    try {
        const res = await fetch('/hrm/ajax-handler?action=add_proposal_follower', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                proposal_type: currentProposalType,
                user_id: userId
            })
        });
        const json = await res.json();
        console.log('Save response:', json);
        if (json.success) {
            closeSidebar();
            document.getElementById('follower-search').value = '';
            document.getElementById('follower-search').dataset.userId = '';
            loadFollowers();
        } else {
            alert('Lỗi khi lưu: ' + (json.message || 'Không rõ nguyên nhân'));
        }
    } catch (err) {
        console.error('Save error:', err);
        alert('Lỗi kết nối server');
    }
}

async function loadFollowers() {
    console.log('Loading followers for:', currentProposalType);
    const res = await fetch('/hrm/ajax-handler?action=get_proposal_followers&type=' + currentProposalType);
    const json = await res.json();
    if (json.success) {
        console.log('Followers loaded:', json.data);
        renderFollowersList(json.data);
    }
}

function renderFollowersList(data) {
    const container = document.getElementById('followers-' + currentProposalType);
    console.log('Rendering followers to container:', container);
    if (!container) return;
    
    if (data.length === 0) {
        container.innerHTML = `<div class="empty-state"><svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg></div>`;
        return;
    }

    container.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:12px">
            ${data.map(item => `
                <div style="display:flex;align-items:center;padding:12px;border:1px solid #e5e7eb;border-radius:8px;justify-content:space-between">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:32px;height:32px;background:#2563eb;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">${item.full_name.charAt(0).toUpperCase()}</div>
                        <div style="display:flex;flex-direction:column">
                            <span style="font-size:13px;font-weight:700;color:#111827">${item.full_name}</span>
                            <span style="font-size:11px;color:#6b7280">${item.email}</span>
                        </div>
                    </div>
                    <span class="action-link" style="color:#ef4444" onclick="deleteFollower(${item.id})">Xóa</span>
                </div>
            `).join('')}
        </div>
    `;
}

async function deleteFollower(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa người theo dõi này?')) return;
    const res = await fetch('/hrm/ajax-handler?action=delete_proposal_follower', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    });
    if ((await res.json()).success) loadFollowers();
}

async function saveSettings() {
    const card = document.getElementById('workflow-' + currentProposalType);
    const data = {
        proposal_type: currentProposalType,
        approval_flow: card.querySelector('[name="approval_flow"]').value,
        role_priority: card.querySelector('[name="role_priority"]').value,
        hrm_edit_after_approval: card.querySelector('[name="hrm_edit_after_approval"]').value
    };

    try {
        const res = await fetch('/hrm/ajax-handler?action=save_proposal_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            alert('Đã lưu cấu hình luồng phê duyệt thành công!');
        } else {
            alert('Lỗi khi lưu: ' + (json.message || 'Không rõ nguyên nhân'));
        }
    } catch (err) {
        alert('Lỗi kết nối server');
    }
}

async function loadSettings() {
    const res = await fetch('/hrm/ajax-handler?action=get_proposal_settings&type=' + currentProposalType);
    const json = await res.json();
    if (json.success && json.data) {
        const card = document.getElementById('workflow-' + currentProposalType);
        if (card) {
            card.querySelector('[name="approval_flow"]').value = json.data.approval_flow || 'sequential';
            card.querySelector('[name="role_priority"]').value = json.data.role_priority || 'last';
            card.querySelector('[name="hrm_edit_after_approval"]').value = json.data.hrm_edit_after_approval || '0';
        }
    }
}

// Followers and save settings logic can be added similarly

function initUserSearch() {
    const inputs = document.querySelectorAll('.user-search-input');
    inputs.forEach(input => {
        input.addEventListener('input', async (e) => {
            const val = e.target.value.trim();
            const resultsDiv = e.target.nextElementSibling;
            
            if (val.length < 2) {
                resultsDiv.classList.remove('active');
                return;
            }

            try {
                const res = await fetch('/hrm/ajax-handler?action=search_users&q=' + encodeURIComponent(val));
                const json = await res.json();
                
                if (json.success && json.data.length > 0) {
                    resultsDiv.innerHTML = json.data.map(user => `
                        <div class="result-item" onclick="selectUser('${user.full_name}', '${user.id}', this)">
                            <div class="result-avatar">${user.full_name.charAt(0).toUpperCase()}</div>
                            <div class="result-info">
                                <span class="result-name">${user.full_name}</span>
                                <span class="result-email">${user.email || ''}</span>
                            </div>
                        </div>
                    `).join('');
                    resultsDiv.classList.add('active');
                } else {
                    resultsDiv.classList.remove('active');
                }
            } catch (err) {
                console.error(err);
            }
        });
    });
}

function selectUser(name, id, el) {
    const container = el.closest('.autocomplete-container');
    const input = container.querySelector('input');
    input.value = name;
    input.dataset.userId = id;
    container.querySelector('.autocomplete-results').classList.remove('active');
}

// Initialize page
document.addEventListener('DOMContentLoaded', () => {
    initUserSearch();
    switchTab('recruitment');
});
</script>
</body>
</html>
