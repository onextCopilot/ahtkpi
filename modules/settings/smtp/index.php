<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/EmailSenders.php';

// Auth
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
if ($_SESSION['role'] !== 'admin') { header("Location: /dashboard"); exit(); }

$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;

EmailSenders::ensure($conn);
$flash = ['type' => '', 'msg' => ''];

/* ── POST actions ──────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_sender') {
        EmailSenders::save($conn, [
            'id' => (int)($_POST['id'] ?? 0),
            'name' => trim($_POST['name'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? ''),
            'smtp_host' => trim($_POST['smtp_host'] ?? '') ?: 'smtp.sendgrid.net',
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587) ?: 587,
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_user' => trim($_POST['smtp_user'] ?? '') ?: 'apikey',
            'smtp_pass' => trim($_POST['smtp_pass'] ?? ''),
            'reply_to' => trim($_POST['reply_to'] ?? ''),
            'cc' => trim($_POST['cc'] ?? ''),
            'bcc' => trim($_POST['bcc'] ?? ''),
        ]);
        header("Location: /settings/smtp?ok=" . urlencode('Đã lưu sender.')); exit();
    }
    if ($act === 'delete_sender') { EmailSenders::delete($conn, (int)$_POST['id']); header("Location: /settings/smtp?ok=" . urlencode('Đã xóa sender.')); exit(); }
    if ($act === 'set_default')   { EmailSenders::setDefault($conn, (int)$_POST['id']); header("Location: /settings/smtp?ok=" . urlencode('Đã đặt sender mặc định.')); exit(); }
    if ($act === 'toggle_active') { EmailSenders::setActive($conn, (int)$_POST['id'], (int)$_POST['active']); header("Location: /settings/smtp"); exit(); }
    if ($act === 'test_send') {
        $sender = EmailSenders::find($conn, (int)$_POST['id']);
        $to = trim($_POST['test_to'] ?? '');
        if ($sender && $to) {
            $opts = [
                'reply_to' => trim($_POST['test_reply_to'] ?? ''),
                'cc'       => trim($_POST['test_cc'] ?? ''),
                'bcc'      => trim($_POST['test_bcc'] ?? ''),
            ];
            $r = EmailSenders::send($conn, $sender, $to, 'Test email - AHT', '<p>Đây là email kiểm tra gửi từ <b>' . htmlspecialchars($sender['from_email']) . '</b> qua AHT system.</p>', $opts);
            header("Location: /settings/smtp?" . ($r['ok'] ? 'ok=' : 'err=') . urlencode($r['ok'] ? "Đã gửi email test tới $to" : ('Gửi thất bại: ' . $r['error']))); exit();
        }
        header("Location: /settings/smtp?err=" . urlencode('Thiếu thông tin gửi test.')); exit();
    }
}

if (isset($_GET['ok']))  { $flash = ['type' => 'success', 'msg' => $_GET['ok']]; }
if (isset($_GET['err'])) { $flash = ['type' => 'error', 'msg' => $_GET['err']]; }

$senders = EmailSenders::all($conn);
$editId = ($_GET['edit'] ?? '') === 'new' ? 0 : (int)($_GET['edit'] ?? -1);
$showForm = isset($_GET['edit']);
$edit = ($editId > 0) ? EmailSenders::find($conn, $editId) : null;
$e = $edit ?: ['id'=>0,'name'=>'','from_email'=>'','from_name'=>'','smtp_host'=>'smtp.sendgrid.net','smtp_port'=>587,'smtp_encryption'=>'tls','smtp_user'=>'apikey','reply_to'=>'','cc'=>'','bcc'=>''];
function ev($v) { return htmlspecialchars((string)$v); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Senders (SMTP) - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .content-wrapper{max-width:980px;margin:0 auto;padding:2rem}
        .form-card{background:#fff;border-radius:16px;border:1px solid var(--border-color);padding:2rem;box-shadow:0 4px 6px -1px rgba(0,0,0,.05)}
        .form-group{margin-bottom:1.25rem}
        .form-group label{display:block;margin-bottom:.4rem;font-weight:500;color:var(--text-secondary)}
        .form-group input,.form-group select{width:100%;padding:.7rem;border:1px solid var(--border-color);border-radius:8px;font-size:.95rem;box-sizing:border-box}
        .btn-save{background:var(--primary-color);color:#fff;border:none;padding:.7rem 1.3rem;border-radius:8px;font-weight:600;cursor:pointer}
        .btn-ghost{background:none;border:1px solid var(--border-color);padding:.55rem 1rem;border-radius:8px;cursor:pointer;font-size:.85rem;color:var(--text-primary);text-decoration:none;display:inline-block}
        .alert{padding:1rem;border-radius:8px;margin-bottom:1.5rem}
        .alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .input-hint{font-size:.82rem;color:#6b7280;margin-top:.25rem}
        .sender{border:1px solid var(--border-color);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1rem}
        .sender.def{border-color:#1a82e2;box-shadow:0 0 0 2px rgba(26,130,226,.12)}
        .s-top{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
        .s-name{font-weight:700;font-size:1rem}
        .s-mail{color:var(--text-secondary);font-size:.88rem}
        .badge{font-size:.7rem;font-weight:700;padding:.18rem .6rem;border-radius:980px}
        .b-def{background:#e0efff;color:#0050a0}.b-on{background:#dcfce7;color:#166534}
        .b-off{background:#f1f5f9;color:#64748b}.b-warn{background:#fff4e5;color:#b25e00}
        .s-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.9rem;align-items:center}
        .s-actions form{display:inline}
        .code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#f1f5f9;padding:.15rem .45rem;border-radius:6px;font-size:.82rem;word-break:break-all}
        .test-inline input{padding:.45rem .6rem;border:1px solid var(--border-color);border-radius:7px;font-size:.82rem}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        details.guide summary{cursor:pointer;font-weight:600}
        .step{font-size:.9rem;margin:.4rem 0}
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Email Senders (SMTP)'; include __DIR__ . '/../../../modules/includes/topbar.php'; ?>
        <div class="content-wrapper">
            <div style="margin-bottom:1rem"><a href="/settings" class="btn-ghost">← Back to Settings</a></div>

            <?php if ($flash['msg']): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
            <?php endif; ?>

            <div class="form-card">
                <div style="margin-bottom:1.25rem;border-bottom:1px solid var(--border-light);padding-bottom:1rem;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <h2 style="font-size:1.25rem;margin-bottom:.3rem">Email Senders (SMTP / SendGrid)</h2>
                        <p style="color:var(--text-secondary);font-size:.9rem;margin:0">Thêm nhiều địa chỉ gửi. SendGrid: host <span class="code">smtp.sendgrid.net</span>, user <span class="code">apikey</span>, password = API key. Sender <b>mặc định</b> dùng cho mọi email hệ thống.</p>
                    </div>
                    <a href="/settings/smtp?edit=new" class="btn-save">+ Thêm sender</a>
                </div>

                <?php if (!$senders): ?>
                    <p style="color:var(--text-secondary)">Chưa có sender nào. Bấm "+ Thêm sender".</p>
                <?php endif; ?>

                <?php foreach ($senders as $s): $configured = !empty($s['smtp_pass']); ?>
                    <div class="sender <?= $s['is_default'] ? 'def' : '' ?>">
                        <div class="s-top">
                            <span class="s-name"><?= ev($s['name']) ?></span>
                            <span class="s-mail"><?= ev($s['from_email']) ?><?= $s['from_name'] ? ' · ' . ev($s['from_name']) : '' ?></span>
                            <?php if ($s['is_default']): ?><span class="badge b-def">MẶC ĐỊNH</span><?php endif; ?>
                            <?php if ($configured): ?><span class="badge b-on">Đã cấu hình</span><?php else: ?><span class="badge b-warn">Thiếu API key</span><?php endif; ?>
                            <?php if (!$s['active']): ?><span class="badge b-off">Tắt</span><?php endif; ?>
                        </div>
                        <div class="s-mail" style="margin-top:.3rem"><?= ev($s['smtp_host']) ?>:<?= (int)$s['smtp_port'] ?> · <?= ev(strtoupper($s['smtp_encryption'])) ?> · user <span class="code"><?= ev($s['smtp_user']) ?></span></div>
                        <?php if (!empty($s['reply_to']) || !empty($s['cc']) || !empty($s['bcc'])): ?>
                        <div class="s-mail" style="margin-top:.2rem">
                            <?= !empty($s['reply_to']) ? 'Reply-To: ' . ev($s['reply_to']) . ' · ' : '' ?>
                            <?= !empty($s['cc']) ? 'CC: ' . ev($s['cc']) . ' · ' : '' ?>
                            <?= !empty($s['bcc']) ? 'BCC: ' . ev($s['bcc']) : '' ?>
                        </div>
                        <?php endif; ?>
                        <div class="s-actions">
                            <a href="/settings/smtp?edit=<?= (int)$s['id'] ?>" class="btn-ghost">Sửa</a>
                            <?php if (!$s['is_default']): ?>
                            <form method="POST"><input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn-ghost" type="submit">Đặt mặc định</button></form>
                            <?php endif; ?>
                            <form method="POST"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="active" value="<?= $s['active'] ? 0 : 1 ?>"><button class="btn-ghost" type="submit"><?= $s['active'] ? 'Tắt' : 'Bật' ?></button></form>
                            <form method="POST" onsubmit="return confirm('Xóa sender này?')"><input type="hidden" name="action" value="delete_sender"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn-ghost" type="submit" style="color:#dc2626">Xóa</button></form>
                        </div>
                        <?php if ($configured): ?>
                        <form method="POST" class="test-inline" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.7rem;padding-top:.7rem;border-top:1px dashed var(--border-color)">
                            <input type="hidden" name="action" value="test_send"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <span style="font-size:.8rem;color:var(--text-secondary)">Gửi thử:</span>
                            <input type="email" name="test_to" placeholder="To (email nhận) *" required style="min-width:180px">
                            <input type="text" name="test_reply_to" placeholder="Reply-To (tùy chọn)" style="min-width:160px">
                            <input type="text" name="test_cc" placeholder="CC (cách nhau dấu phẩy)" style="min-width:180px">
                            <input type="text" name="test_bcc" placeholder="BCC" style="min-width:140px">
                            <button class="btn-ghost" type="submit">Gửi</button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($showForm): ?>
            <div class="form-card" style="margin-top:1.5rem">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h2 style="font-size:1.15rem;margin:0"><?= $e['id'] ? 'Sửa sender' : 'Thêm sender mới' ?></h2>
                    <button type="button" class="btn-ghost" onclick="fillSendGrid()">⚡ Điền sẵn SendGrid</button>
                </div>
                <form method="POST" id="senderForm">
                    <input type="hidden" name="action" value="save_sender">
                    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                    <div class="grid2">
                        <div class="form-group"><label>Tên hiển thị (nội bộ) *</label><input name="name" required value="<?= ev($e['name']) ?>" placeholder="VD: HR Mailbox"></div>
                        <div class="form-group"><label>From Name</label><input name="from_name" value="<?= ev($e['from_name']) ?>" placeholder="VD: AHT Tuyển dụng"></div>
                    </div>
                    <div class="form-group"><label>From Email (đã verify trong SendGrid) *</label><input id="f_from" name="from_email" type="email" required value="<?= ev($e['from_email']) ?>" placeholder="no-reply@arrowhitech.com">
                        <p class="input-hint">Phải là Single Sender / domain đã xác thực trong SendGrid.</p></div>
                    <div class="grid2">
                        <div class="form-group"><label>SMTP Host</label><input id="f_host" name="smtp_host" value="<?= ev($e['smtp_host']) ?>" placeholder="smtp.sendgrid.net"></div>
                        <div class="form-group"><label>Port</label><input id="f_port" name="smtp_port" type="number" value="<?= ev($e['smtp_port']) ?>" placeholder="587"></div>
                    </div>
                    <div class="grid2">
                        <div class="form-group"><label>Encryption</label>
                            <select id="f_enc" name="smtp_encryption">
                                <?php foreach (['tls'=>'TLS / STARTTLS (587)','ssl'=>'SSL (465)','none'=>'None'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= ($e['smtp_encryption']??'tls')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="form-group"><label>Username</label><input id="f_user" name="smtp_user" value="<?= ev($e['smtp_user']) ?>" placeholder="apikey">
                            <p class="input-hint">SendGrid: luôn là <span class="code">apikey</span>.</p></div>
                    </div>
                    <div class="form-group"><label>Password / API Key <?= $e['id'] ? '(để trống nếu không đổi)' : '*' ?></label>
                        <input name="smtp_pass" type="password" <?= $e['id'] ? '' : 'required' ?> placeholder="SG.xxxxx (SendGrid API Key)"></div>

                    <div class="form-group" style="border-top:1px solid var(--border-light);padding-top:1rem">
                        <label>Reply-To (mặc định)</label>
                        <input name="reply_to" value="<?= ev($e['reply_to']) ?>" placeholder="hr@arrowhitech.com">
                        <p class="input-hint">Địa chỉ nhận khi người ta bấm "Reply". Để trống = trả về From Email.</p></div>
                    <div class="grid2">
                        <div class="form-group"><label>CC (mặc định)</label><input name="cc" value="<?= ev($e['cc']) ?>" placeholder="a@x.com, b@y.com"></div>
                        <div class="form-group"><label>BCC (mặc định)</label><input name="bcc" value="<?= ev($e['bcc']) ?>" placeholder="archive@arrowhitech.com"></div>
                    </div>
                    <p class="input-hint" style="margin:-.6rem 0 1rem">CC/BCC nhiều địa chỉ ngăn cách bởi dấu phẩy. Áp dụng cho mọi email gửi từ sender này.</p>

                    <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:.5rem">
                        <a href="/settings/smtp" class="btn-ghost">Hủy</a>
                        <button type="submit" class="btn-save">Lưu sender</button>
                    </div>
                </form>
            </div>
            <script>
            function fillSendGrid(){
                document.getElementById('f_host').value='smtp.sendgrid.net';
                document.getElementById('f_port').value='587';
                document.getElementById('f_enc').value='tls';
                document.getElementById('f_user').value='apikey';
            }
            </script>
            <?php endif; ?>

            <div class="form-card" style="margin-top:1.5rem">
                <details class="guide" open>
                    <summary>Hướng dẫn lấy SendGrid API Key</summary>
                    <div style="margin-top:1rem">
                        <div class="step">1. Đăng nhập <b>app.sendgrid.com → Settings → API Keys → Create API Key</b> (quyền tối thiểu <i>Mail Send</i>).</div>
                        <div class="step">2. Copy key dạng <span class="code">SG.xxxxxxxx</span> (chỉ hiện 1 lần).</div>
                        <div class="step">3. Xác thực người gửi: <b>Settings → Sender Authentication</b> (Single Sender hoặc Domain) cho địa chỉ <i>From Email</i>.</div>
                        <div class="step">4. Tạo sender ở trên: Host <span class="code">smtp.sendgrid.net</span>, Port <span class="code">587</span>, User <span class="code">apikey</span>, Password = API Key. Lưu rồi bấm <b>Gửi thử</b>.</div>
                    </div>
                </details>
            </div>
        </div>
    </main>
</div>
</body>
</html>
