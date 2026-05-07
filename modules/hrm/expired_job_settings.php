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
<title>Tuỳ chọn cho tin tuyển dụng quá hạn – E-Hiring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/hrm/sidebar.css">
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

.eh-main{flex:1;overflow-y:auto;padding:32px}
.page-title{font-size:24px;font-weight:400;color:#1e293b;margin-bottom:8px}
.section-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-top:32px;margin-bottom:16px;border-bottom:1px dotted #e2e8f0;padding-bottom:8px}

.config-card{background:#fff;padding:24px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px}
.form-label-bold{display:block;font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px}
.form-select{width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;font-size:14px;outline:none;cursor:pointer}

.note-box{background:#fff1f2;border:1px solid #ffe4e6;color:#991b1b;padding:16px;border-radius:8px;font-size:13px;margin-top:12px;line-height:1.5}
.save-bar{position:fixed;bottom:24px;right:24px}
.btn-save{background:#2563eb;color:#fff;border:none;padding:10px 32px;border-radius:6px;font-weight:700;cursor:pointer;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)}
</style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="eh-content-col">
        <div class="eh-top">
            <div style="position:relative;flex:1;max-width:320px">
                <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:0.4" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search" placeholder="Tìm kiếm trong hệ thống">
            </div>
            <div class="top-actions">
                <button class="top-btn primary" onclick="location.href='/hrm/job-post-create'">⚡ Đăng tin tuyển dụng</button>
                <button class="top-btn">✦ Tạo chiến dịch</button>
                <div class="top-avatar"><?=strtoupper(substr($full_name,0,1))?></div>
            </div>
        </div>

        <main class="eh-main">
            <h1 class="page-title">Tuỳ chọn cho tin tuyển dụng quá hạn</h1>
            
            <div class="section-label">Tuỳ chọn trạng thái tin</div>
            <div class="config-card">
                <label class="form-label-bold">Tự động chuyển tin tuyển dụng về trạng thái ĐÓNG khi hết hạn</label>
                <select class="form-select" id="autoClose">
                    <option value="1">Bật</option>
                    <option value="0">Tắt</option>
                </select>
                <div class="note-box">
                    Lưu ý: Tính năng chỉ có hiệu lực 1 lần. Các tin tự động đóng sau khi hết hạn nếu được chuyển về trạng thái công khai sẽ không tự đóng nữa.
                </div>
            </div>

            <div class="section-label">Tuỳ chọn hiển thị</div>
            <div class="config-card">
                <label class="form-label-bold">Tự động ẩn tin trên trang tuyển dụng khi hết hạn</label>
                <select class="form-select" id="autoHide">
                    <option value="1">Bật</option>
                    <option value="0">Tắt</option>
                </select>
            </div>

            <div class="section-label">Tuỳ chọn thông báo</div>
            <div class="config-card">
                <label class="form-label-bold">Gửi mail thông báo 1-2 ngày trước khi tin tuyển dụng hết hạn</label>
                <select class="form-select" id="emailBefore">
                    <option value="1">Bật</option>
                    <option value="0">Tắt</option>
                </select>
            </div>

            <div class="save-bar">
                <button class="btn-save" onclick="saveSettings()">LƯU LẠI</button>
            </div>
        </main>
    </div>
</div>

<script>
async function loadData() {
    try {
        const res = await fetch('/hrm/ajax-handler?action=get_settings');
        const s = await res.json();
        if (s) {
            document.getElementById('autoClose').value = s.auto_close_expired || 0;
            document.getElementById('autoHide').value = s.auto_hide_expired || 0;
            document.getElementById('emailBefore').value = s.email_before_expiry || 0;
        }
    } catch (e) { console.error(e); }
}

async function saveSettings() {
    const data = {
        auto_close_expired: document.getElementById('autoClose').value,
        auto_hide_expired: document.getElementById('autoHide').value,
        email_before_expiry: document.getElementById('emailBefore').value
    };
    
    try {
        const res = await fetch('/hrm/ajax-handler?action=save_expired_job_settings', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            alert('Lưu cài đặt thành công');
        }
    } catch (e) { console.error(e); }
}

document.addEventListener('DOMContentLoaded', loadData);
</script>
</body>
</html>
