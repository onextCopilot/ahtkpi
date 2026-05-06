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
<title>Thông tin công ty – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
<style>
  .dept-item, .office-item { cursor: grab; }
  .dept-item:active, .office-item:active { cursor: grabbing; }
  .sortable-ghost { opacity: 0.4; background: #ebf5ff; }
</style>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
.eh-wrapper{display:flex;height:100vh;overflow:hidden}
.eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
.eh-top{height:48px;background:#0a252a;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
.eh-search{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none}
.eh-search::placeholder{color:rgba(255,255,255,0.4)}
.top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
.top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap}
.top-btn.primary{background:#0ea5e9;border-color:#0ea5e9}
.top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
.top-avatar img{width:100%;height:100%;object-fit:cover}
.top-user-info{font-size:11px;color:rgba(255,255,255,0.7);line-height:1.3}
.top-user-info strong{display:block;color:#fff;font-size:12px}
.eh-body{display:flex;flex:1;overflow:hidden}
.eh-main{flex:1;overflow-y:auto;padding:20px}
.page-header{font-size:18px;font-weight:700;color:#111827;margin-bottom:4px}
.page-sub{font-size:12px;color:#6b7280;margin-bottom:20px}
.content-layout{display:flex;gap:20px;align-items:flex-start}
.left-col{flex:1;display:flex;flex-direction:column;gap:16px}
.right-col{width:280px;min-width:280px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f3f4f6}
.card-title{font-size:13px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:0.5px}
.add-btn{background:#2563eb;color:#fff;border:none;padding:7px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px}
.dept-item{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid #f9fafb}
.dept-item:last-child{border-bottom:none}
.dept-name{font-size:13px;font-weight:600;color:#111827}
.dept-sub{font-size:11px;color:#9ca3af;margin-top:1px}
.edit-btn{width:26px;height:26px;border-radius:5px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#9ca3af;flex-shrink:0}
.edit-btn:hover{background:#f3f4f6;color:#374151}
.office-item{display:flex;align-items:flex-start;gap:10px;padding:10px 16px;border-bottom:1px solid #f9fafb}
.office-item:last-child{border-bottom:none}
.office-dot{width:10px;height:10px;border-radius:50%;background:#3b82f6;flex-shrink:0;margin-top:3px}
.office-info{flex:1}
.office-name{font-size:13px;font-weight:600;color:#111827}
.office-addr{font-size:11px;color:#6b7280;margin-top:2px;line-height:1.4}
/* RIGHT FORM */
.form-section-title{font-size:11px;font-weight:700;color:#2563eb;text-transform:uppercase;letter-spacing:0.5px;margin:14px 0 10px;padding-top:14px;border-top:1px solid #f3f4f6}
.form-section-title:first-child{margin-top:0;padding-top:0;border-top:none}
.form-group{margin-bottom:12px}
.form-label{font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px}
.form-input{width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:7px 10px;font-size:12px;color:#111827;outline:none;font-family:inherit}
.form-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.08)}
.form-textarea{width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:7px 10px;font-size:12px;color:#111827;outline:none;font-family:inherit;resize:vertical;min-height:60px}
.form-file{font-size:11px;color:#374151}
.save-btn{width:100%;background:#22c55e;color:#fff;border:none;padding:9px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;margin-top:8px}
.save-btn:hover{background:#16a34a}
.save-btn:hover{background:#16a34a}

/* MODAL STYLES */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000;
}
.modal {
    background: #fff; width: 500px; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.modal-header {
    background: #f3f4f6; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
}
.modal-header h3 { font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; }
.modal-close { cursor: pointer; color: #9ca3af; font-size: 20px; border: none; background: none; }
.modal-body { padding: 20px; }
.modal-footer { padding: 16px 20px; display: flex; gap: 10px; justify-content: center; background: #fff; border-top: none; }
.btn-grey { background: #e5e7eb; color: #4b5563; border: none; padding: 10px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; flex: 1; max-width: 140px; }
.btn-green { background: #44ce1b; color: #fff; border: none; padding: 10px 30px; border-radius: 6px; font-weight: 600; cursor: pointer; flex: 1; max-width: 140px; }
.modal-label { font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
.modal-input { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 13px; outline: none; margin-bottom: 15px; }
.modal-input:focus { border-color: #3b82f6; }
.modal-textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 13px; outline: none; margin-bottom: 15px; min-height: 80px; font-family: inherit; }
</style>
</head>
<body>
<!-- MODAL EDIT DEPT -->
<div class="modal-overlay" id="editDeptModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Sửa phòng ban</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <label class="modal-label">Tên *</label>
      <input type="text" class="modal-input" id="editDeptName" value="Sales/Marketing">
      
      <label class="modal-label">Mô tả</label>
      <textarea class="modal-textarea" id="editDeptDesc">Sales/Marketing</textarea>
      
      <label class="modal-label">Người quản lý</label>
      <input type="text" class="modal-input" id="editDeptManager" placeholder="Người quản lý">
      
      <label class="modal-label">Người có thể tạo đề xuất nhân tuyển</label>
      <input type="text" class="modal-input" id="editDeptCreators" placeholder="Nhập @ để tag">
      
      <label class="modal-label">Người theo dõi</label>
      <input type="text" class="modal-input" id="editDeptFollowers" placeholder="Người theo dõi">
    </div>
    <div class="modal-footer">
      <button class="btn-grey" onclick="closeModal()">Bỏ qua</button>
      <button class="btn-green" onclick="saveDept()">Lưu lại</button>
    </div>
  </div>
</div>

<!-- MODAL EDIT OFFICE -->
<div class="modal-overlay" id="editOfficeModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="officeModalTitle">Sửa văn phòng</h3>
      <button class="modal-close" onclick="closeOfficeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <label class="modal-label">Tên văn phòng</label>
      <input type="text" class="modal-input" id="editOfficeName" placeholder="Tên văn phòng">
      
      <label class="modal-label">Địa chỉ chi tiết</label>
      <textarea class="modal-textarea" id="editOfficeAddr" placeholder="Địa chỉ chi tiết"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-grey" onclick="closeOfficeModal()">Bỏ qua</button>
      <button class="btn-green" id="btnSaveOffice" onclick="saveOffice()">Sửa văn phòng</button>
    </div>
  </div>
</div>
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

    <main class="eh-main">
    <div class="page-header">Thông tin công ty</div>
    <div class="page-sub">DANH SÁCH PHÒNG BAN</div>

    <div class="content-layout">
      <div class="left-col">
        <!-- PHÒNG BAN -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Danh sách phòng ban</span>
          </div>
          <div id="deptListContainer">
            <!-- Content loaded via JS -->
          </div>
          <div style="padding:12px 16px">
            <button class="add-btn" onclick="openModal(null)">+ Thêm phòng ban mới</button>
          </div>
        </div>

        <!-- VĂN PHÒNG -->
        <div class="page-sub" style="margin-top:4px">DANH SÁCH VĂN PHÒNG</div>
        <div class="card">
          <div class="card-header"><span class="card-title">Danh sách văn phòng</span></div>
          <div id="officeListContainer">
            <!-- Content loaded via JS -->
          </div>
          <div style="padding:12px 16px">
            <button class="add-btn" onclick="openOfficeModal(null)">+ Thêm văn phòng mới</button>
          </div>
        </div>
      </div>

      <!-- RIGHT FORM -->
      <div class="right-col">
        <div class="form-section-title">Thông tin công ty</div>
        <div class="form-group">
          <label class="form-label">Tên công ty</label>
          <input class="form-input" id="setCompanyName" value="AHT TECH JSC">
        </div>
        <div class="form-group">
          <label class="form-label">Đường dẫn trang web công ty</label>
          <input class="form-input" id="setCompanyWebsite" value="https://www.arrowhitech.com">
        </div>
        <div class="form-group">
          <label class="form-label">Số điện thoại</label>
          <input class="form-input" id="setCompanyPhone" value="(024)32025289">
        </div>
        <div class="form-group">
          <label class="form-label">Địa chỉ công ty</label>
          <input class="form-input" id="setCompanyAddress" value="Tầng 8, MItec Tower, Đường Bình Nghị...">
        </div>

        <div class="form-section-title">Trang tuyển dụng</div>
        <div class="form-group">
          <label class="form-label">Tiêu đề trang tuyển dụng</label>
          <input class="form-input" id="setRecruitTitle" value="AHT TECH JSC - Tuyển dụng">
        </div>
        <div class="form-group">
          <label class="form-label">Đường dẫn trang tuyển dụng</label>
          <input class="form-input" id="setRecruitUrl" value="https://aht.talent.vn">
        </div>
        <div class="form-group">
          <label class="form-label">Mô tả</label>
          <textarea class="form-textarea" id="setRecruitDesc">tuyển dụng, AHT TECH JSC, hiring, talent, vn, candidate, ứng viên, hồ sơ, nộp đơn</textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Favicon (file)</label>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
            <img id="prevFavicon" src="" style="width:24px; height:24px; object-fit:contain; border:1px solid #ddd; border-radius:4px; display:none;">
            <input type="file" class="form-file" id="setFavicon" style="flex:1">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Logo trang bị (hình ảnh)</label>
          <div style="display:flex; flex-direction:column; gap:8px;">
            <img id="prevLogo" src="" style="max-width:150px; max-height:60px; object-fit:contain; border:1px solid #ddd; border-radius:4px; display:none;">
            <input type="file" class="form-file" id="setLogo">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Chữ đổ sổ</label>
          <div style="display:flex;gap:6px">
            <select class="form-input" style="flex:1" id="setSlaMode">
              <option>Dạ tiếng...</option>
              <option>CÔNG TY...</option>
            </select>
            <button style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer">🔍</button>
          </div>
        </div>
        <button class="save-btn" onclick="saveSettings()">Cập nhật</button>
      </div>
    </div>
    </main>
  </div>
</div>
<script>
let departments = [];
let offices = [];
let currentEditingId = null;
let currentOfficeId = null;

async function fetchData() {
    await fetchDepts();
    await fetchOffices();
    await fetchSettings();
}

async function fetchSettings() {
    try {
        const response = await fetch('/hrm/ajax-handler?action=get_settings');
        const data = await response.json();
        if (data) {
            document.getElementById('setCompanyName').value = data.company_name || '';
            document.getElementById('setCompanyWebsite').value = data.company_website || '';
            document.getElementById('setCompanyPhone').value = data.company_phone || '';
            document.getElementById('setCompanyAddress').value = data.company_address || '';
            document.getElementById('setRecruitTitle').value = data.recruit_title || '';
            document.getElementById('setRecruitUrl').value = data.recruit_url || '';
            document.getElementById('setRecruitDesc').value = data.recruit_desc || '';
            document.getElementById('setSlaMode').value = data.sla_mode || 'Dạ tiếng...';

            if (data.favicon) {
                const img = document.getElementById('prevFavicon');
                img.src = data.favicon;
                img.style.display = 'block';
            }
            if (data.logo) {
                const img = document.getElementById('prevLogo');
                img.src = data.logo;
                img.style.display = 'block';
            }
        }
    } catch (error) {
        console.error('Error fetching settings:', error);
    }
}

async function saveSettings() {
    const formData = new FormData();
    formData.append('company_name', document.getElementById('setCompanyName').value);
    formData.append('company_website', document.getElementById('setCompanyWebsite').value);
    formData.append('company_phone', document.getElementById('setCompanyPhone').value);
    formData.append('company_address', document.getElementById('setCompanyAddress').value);
    formData.append('recruit_title', document.getElementById('setRecruitTitle').value);
    formData.append('recruit_url', document.getElementById('setRecruitUrl').value);
    formData.append('recruit_desc', document.getElementById('setRecruitDesc').value);
    formData.append('sla_mode', document.getElementById('setSlaMode').value);

    const favicon = document.getElementById('setFavicon').files[0];
    const logo = document.getElementById('setLogo').files[0];
    if (favicon) formData.append('favicon', favicon);
    if (logo) formData.append('logo', logo);

    try {
        const response = await fetch('/hrm/ajax-handler?action=save_settings', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            // Refresh settings to show new files
            await fetchSettings();
            // Clear file inputs
            document.getElementById('setFavicon').value = '';
            document.getElementById('setLogo').value = '';
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        alert('Có lỗi xảy ra khi lưu cài đặt.');
    }
}

async function fetchDepts() {
    try {
        const response = await fetch('/hrm/ajax-handler?action=get_depts');
        const text = await response.text();
        console.log('Depts response:', text);
        departments = JSON.parse(text);
        renderDepts();
    } catch (error) {
        console.error('Error fetching departments:', error);
    }
}

async function fetchOffices() {
    try {
        const response = await fetch('/hrm/ajax-handler?action=get_offices');
        const text = await response.text();
        console.log('Offices response:', text);
        offices = JSON.parse(text);
        renderOffices();
    } catch (error) {
        console.error('Error fetching offices:', error);
    }
}

function renderDepts() {
    const container = document.getElementById('deptListContainer');
    if (!container) return;
    container.innerHTML = departments.map((d) => `
        <div class="dept-item" data-id="${d.id}" style="display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid #f3f4f6; background:#white;">
            <div class="sort-handle" style="color:#d1d5db; cursor:grab;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                    <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                </svg>
            </div>
            <div style="color:#9ca3af; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>
                </svg>
            </div>
            <div style="flex:1;">
                <div class="dept-name" style="font-size:14px; font-weight:600; color:#374151;">${d.name}</div>
                ${d.description ? `<div class="dept-sub" style="font-size:12px; color:#9ca3af; margin-top:2px;">${d.description}</div>` : ''}
            </div>
            <div style="display:flex; gap:4px;">
                <button class="edit-btn" onclick="openModal(${d.id})" style="background:none; border:none; cursor:pointer; color:#9ca3af; padding:4px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button onclick="deleteDept(${d.id})" style="background:none; border:none; cursor:pointer; color:#ef4444; padding:4px; opacity:0.6;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');

    new Sortable(container, {
        handle: '.sort-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: () => updateOrder('dept')
    });
}

function renderOffices() {
    const container = document.getElementById('officeListContainer');
    if (!container) return;
    container.innerHTML = offices.map((o) => `
        <div class="office-item" data-id="${o.id}" style="display:flex; align-items:flex-start; gap:12px; padding:12px 16px; border-bottom:1px solid #f3f4f6; background:#white;">
            <div class="sort-handle" style="color:#d1d5db; cursor:grab; margin-top:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                    <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                </svg>
            </div>
            <div style="width:8px; height:8px; border-radius:50%; background:#3b82f6; margin-top:8px; flex-shrink:0;"></div>
            <div style="flex:1;">
                <div class="office-name" style="font-size:14px; font-weight:600; color:#374151;">${o.name}</div>
                ${o.address ? `<div class="office-addr" style="font-size:12px; color:#9ca3af; margin-top:2px;">${o.address}</div>` : ''}
            </div>
            <div style="display:flex; gap:4px;">
                <button class="edit-btn" onclick="openOfficeModal(${o.id})" style="background:none; border:none; cursor:pointer; color:#9ca3af; padding:4px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button onclick="deleteOffice(${o.id})" style="background:none; border:none; cursor:pointer; color:#ef4444; padding:4px; opacity:0.6;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');

    new Sortable(container, {
        handle: '.sort-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: () => updateOrder('office')
    });
}

async function updateOrder(type) {
    const container = type === 'dept' ? document.getElementById('deptListContainer') : document.getElementById('officeListContainer');
    const items = container.querySelectorAll(type === 'dept' ? '.dept-item' : '.office-item');
    const order = Array.from(items).map(item => item.getAttribute('data-id'));

    try {
        await fetch('/hrm/ajax-handler?action=update_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, order: order })
        });
    } catch (error) {
        console.error('Error updating order:', error);
    }
}

async function saveOffice() {
    const name = document.getElementById('editOfficeName').value;
    const address = document.getElementById('editOfficeAddr').value;
    if (!name) return alert('Vui lòng nhập tên văn phòng');

    const data = { id: currentOfficeId, name, address };
    try {
        const response = await fetch('/hrm/ajax-handler?action=save_office', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            await fetchOffices();
            closeOfficeModal();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving office:', error);
    }
}

async function deleteOffice(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa văn phòng này?')) return;
    try {
        const response = await fetch('/hrm/ajax-handler?action=delete_office', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await response.json();
        if (result.success) {
            await fetchOffices();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting office:', error);
    }
}

function openOfficeModal(id) {
    currentOfficeId = id;
    const title = document.getElementById('officeModalTitle');
    const btn = document.getElementById('btnSaveOffice');
    
    if (id) {
        title.innerText = 'SỬA VĂN PHÒNG';
        btn.innerText = 'Sửa văn phòng';
        const office = offices.find(o => o.id == id);
        document.getElementById('editOfficeName').value = office.name;
        document.getElementById('editOfficeAddr').value = office.address || '';
    } else {
        title.innerText = 'THÊM VĂN PHÒNG MỚI';
        btn.innerText = 'Thêm văn phòng';
        document.getElementById('editOfficeName').value = '';
        document.getElementById('editOfficeAddr').value = '';
    }
    document.getElementById('editOfficeModal').style.display = 'flex';
}

function closeOfficeModal() {
    document.getElementById('editOfficeModal').style.display = 'none';
}

// Update the DOMContentLoaded listener
document.addEventListener('DOMContentLoaded', fetchData);

async function deleteDept(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa phòng ban này?')) return;
    
    try {
        const response = await fetch('/hrm/ajax-handler?action=delete_dept', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await response.json();
        if (result.success) {
            await fetchDepts();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting department:', error);
        alert('Có lỗi xảy ra khi xóa dữ liệu.');
    }
}

function openModal(id) {
    currentEditingId = id;
    const modalTitle = document.querySelector('.modal-header h3');
    
    if (id) {
        modalTitle.innerText = 'Sửa phòng ban';
        const dept = departments.find(d => d.id == id);
        document.getElementById('editDeptName').value = dept.name;
        document.getElementById('editDeptDesc').value = dept.description || '';
        document.getElementById('editDeptManager').value = dept.manager || '';
        document.getElementById('editDeptCreators').value = dept.creators || '';
        document.getElementById('editDeptFollowers').value = dept.followers || '';
    } else {
        modalTitle.innerText = 'Thêm phòng ban mới';
        document.getElementById('editDeptName').value = '';
        document.getElementById('editDeptDesc').value = '';
        document.getElementById('editDeptManager').value = '';
        document.getElementById('editDeptCreators').value = '';
        document.getElementById('editDeptFollowers').value = '';
    }
    
    document.getElementById('editDeptModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editDeptModal').style.display = 'none';
}

async function saveDept() {
    const name = document.getElementById('editDeptName').value;
    const description = document.getElementById('editDeptDesc').value;
    const manager = document.getElementById('editDeptManager').value;
    const creators = document.getElementById('editDeptCreators').value;
    const followers = document.getElementById('editDeptFollowers').value;
    
    if (!name) { alert('Vui lòng nhập tên phòng ban!'); return; }
    
    const data = {
        id: currentEditingId,
        name: name,
        description: description,
        manager: manager,
        creators: creators,
        followers: followers
    };

    try {
        const response = await fetch('/hrm/ajax-handler?action=save_dept', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            await fetchDepts();
            closeModal();
        } else {
            alert('Lỗi: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving department:', error);
        alert('Có lỗi xảy ra khi lưu dữ liệu.');
    }
}

// Tagging Logic
function initTagging(inputId, resultsId) {
    const input = document.getElementById(inputId);
    const results = document.createElement('div');
    results.id = resultsId;
    results.className = 'search-results';
    input.parentNode.style.position = 'relative';
    input.parentNode.appendChild(results);

    input.addEventListener('keyup', async (e) => {
        const val = e.target.value;
        if (!val.includes('@')) { results.style.display = 'none'; return; }
        
        const query = val.split('@').pop();
        if (query.length < 2) return;

        try {
            const res = await fetch('/hrm/ajax-handler?action=search_users&q=' + encodeURIComponent(query));
            const result = await res.json();
            if (result.success && result.data.length > 0) {
                results.style.display = 'block';
                results.innerHTML = result.data.map(u => `
                    <div class="search-item" onclick="addTag('${inputId}', '${resultsId}', '${u.full_name}')">
                        <div class="user-avatar" style="width:24px;height:24px;font-size:10px">${u.avatar ? `<img src="${u.avatar}">` : u.full_name[0].toUpperCase()}</div>
                        <span style="font-size:12px">${u.full_name}</span>
                    </div>
                `).join('');
            }
        } catch (error) { console.error('Search error:', error); }
    });
}

function addTag(inputId, resultsId, name) {
    const input = document.getElementById(inputId);
    const val = input.value;
    const parts = val.split('@');
    parts.pop();
    input.value = parts.join('@') + name + ', ';
    document.getElementById(resultsId).style.display = 'none';
    input.focus();
}

// Initial render
document.addEventListener('DOMContentLoaded', () => {
    fetchData();
    initTagging('editDeptManager', 'results-manager');
    initTagging('editDeptCreators', 'results-creators');
    initTagging('editDeptFollowers', 'results-followers');
});

// CSS for search results (inline for simplicity or add to <style>)
const style = document.createElement('style');
style.textContent = `
    .search-results { position: absolute; top: 100%; left: 0; width: 100%; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; display: none; z-index: 1100; margin-top: 2px; }
    .search-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
    .search-item:hover { background: #f9fafb; }
`;
document.head.appendChild(style);

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeModal();
        closeOfficeModal();
    }
}
</script>
</body>
</html>
