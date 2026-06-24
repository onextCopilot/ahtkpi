<?php
/**
 * Form đăng ký ứng viên CÔNG KHAI (không cần đăng nhập) - dùng tại sự kiện / QR.
 * Route: /hrm/intake?e=<event_id>
 * GET: hiển thị form. POST: ghi ứng viên vào kho (status new, gắn sự kiện).
 */
require_once __DIR__ . '/lib/core.php';   // nạp config + $conn (KHÔNG gọi hrm_require_login)
hrm_ensure_candidate_module($conn);

$eid = (int)($_GET['e'] ?? $_POST['e'] ?? 0);
$event = $eid ? $conn->query("SELECT * FROM hrm_events WHERE id=$eid AND active=1")->fetch_assoc() : null;

$done = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot chống bot.
    if (trim($_POST['website'] ?? '') !== '') { $done = true; }
    else {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        if ($name === '') { $err = 'Vui lòng nhập họ tên.'; }
        elseif ($email === '' && $phone === '') { $err = 'Nhập email hoặc số điện thoại.'; }
        else {
            // Nguồn "Sự kiện" (tạo nếu chưa có).
            $src = $conn->query("SELECT id FROM hrm_candidate_sources WHERE name='Sự kiện' LIMIT 1")->fetch_assoc();
            if (!$src) { $conn->query("INSERT INTO hrm_candidate_sources (name) VALUES ('Sự kiện')"); $srcId = $conn->insert_id; }
            else { $srcId = (int)$src['id']; }
            $pos = trim($_POST['current_position'] ?? ''); $note = trim($_POST['message'] ?? '');
            $dedup = hrm_candidate_dedup_key($email, $phone);
            // Bỏ qua nếu đã tồn tại (tránh trùng từ form công khai), vẫn báo thành công.
            $exist = $dedup !== '' ? $conn->query("SELECT id FROM hrm_candidates WHERE dedup_key='" . $conn->real_escape_string($dedup) . "' LIMIT 1")->fetch_assoc() : null;
            if (!$exist) {
                $evId = $event ? (int)$event['id'] : 0;
                $st = $conn->prepare("INSERT INTO hrm_candidates (full_name,email,phone,current_position,source_id,event_id,notes,status,dedup_key,last_activity_at) VALUES (?,?,?,?,?,?,?, 'new', ?, NOW())");
                $st->bind_param('ssssiiss', $name, $email, $phone, $pos, $srcId, $evId, $note, $dedup);
                $st->execute(); $cid = $st->insert_id;
                hrm_cand_activity($conn, $cid, 'create', 'Tự đăng ký qua form công khai' . ($event ? ' · sự kiện: ' . $event['name'] : ''), 0);
            }
            $done = true;
        }
    }
}
$company = hrm_setting($conn, 'company_name', 'AHT TECH');
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng ký ứng viên · <?= h($company) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0a252a;color:#1d1d1f;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:18px;box-shadow:0 20px 50px rgba(0,0,0,.3);width:100%;max-width:440px;padding:28px 26px}
.brand{font-size:13px;letter-spacing:.5px;text-transform:uppercase;color:#0e9f6e;font-weight:700}
h1{font-size:22px;margin:6px 0 4px}
.sub{color:#64748b;font-size:13.5px;margin-bottom:20px}
label{display:block;font-size:13px;font-weight:600;color:#334155;margin:12px 0 5px}
input,textarea{width:100%;padding:11px 13px;border:1px solid #d7dde5;border-radius:10px;font-size:14px;font-family:inherit}
input:focus,textarea:focus{outline:none;border-color:#0e9f6e;box-shadow:0 0 0 3px rgba(14,159,110,.15)}
.btn{width:100%;margin-top:18px;padding:13px;background:#0e9f6e;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer}
.btn:hover{background:#0b855c}
.hp{position:absolute;left:-9999px}
.err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:9px;padding:9px 12px;font-size:13px;margin-bottom:8px}
.ok{text-align:center;padding:20px 0}
.ok-ic{width:64px;height:64px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 14px}
.req{color:#dc2626}
</style></head><body>
<div class="card">
<?php if ($done): ?>
    <div class="ok">
        <div class="ok-ic">✓</div>
        <h1>Cảm ơn bạn!</h1>
        <p class="sub">Thông tin của bạn đã được ghi nhận<?= $event ? ' cho ' . h($event['name']) : '' ?>. Phòng Tuyển dụng <?= h($company) ?> sẽ liên hệ khi có cơ hội phù hợp.</p>
    </div>
<?php else: ?>
    <div class="brand"><?= h($company) ?> · Tuyển dụng</div>
    <h1>Đăng ký ứng viên</h1>
    <p class="sub"><?= $event ? 'Sự kiện: <b>' . h($event['name']) . '</b>' : 'Để lại thông tin để chúng tôi kết nối khi có vị trí phù hợp.' ?></p>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="e" value="<?= $eid ?>">
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
        <label>Họ và tên <span class="req">*</span></label>
        <input name="full_name" required value="<?= h($_POST['full_name'] ?? '') ?>">
        <label>Email</label>
        <input name="email" type="email" value="<?= h($_POST['email'] ?? '') ?>">
        <label>Số điện thoại</label>
        <input name="phone" value="<?= h($_POST['phone'] ?? '') ?>">
        <label>Vị trí quan tâm</label>
        <input name="current_position" value="<?= h($_POST['current_position'] ?? '') ?>">
        <label>Lời nhắn (tùy chọn)</label>
        <textarea name="message" rows="3"><?= h($_POST['message'] ?? '') ?></textarea>
        <button class="btn" type="submit">Gửi đăng ký</button>
    </form>
<?php endif; ?>
</div>
</body></html>
