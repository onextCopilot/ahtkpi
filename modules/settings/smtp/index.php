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

// Redirect URI dùng cho Azure (phải đăng ký y hệt trong App registration).
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$redirectUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/settings/smtp';

$flash = ['type' => '', 'msg' => ''];

/* ── OAuth callback: Microsoft trả code về ─────────────────────────── */
if (isset($_GET['code'], $_GET['state'])) {
    [$sid, $tok] = array_pad(explode(':', $_GET['state'], 2), 2, '');
    $sid = (int)$sid;
    if (!hash_equals($_SESSION['oauth_state'] ?? '', (string)$_GET['state'])) {
        header("Location: /settings/smtp?err=" . urlencode('State không hợp lệ, thử lại.')); exit();
    }
    unset($_SESSION['oauth_state']);
    $sender = EmailSenders::find($conn, $sid);
    if ($sender) {
        $r = EmailSenders::exchangeCode($conn, $sender, $_GET['code'], $redirectUri);
        $msg = $r['ok'] ? 'Kết nối Outlook thành công cho: ' . $sender['from_email'] : ('Kết nối thất bại: ' . $r['error']);
        header("Location: /settings/smtp?" . ($r['ok'] ? 'ok=' : 'err=') . urlencode($msg)); exit();
    }
    header("Location: /settings/smtp"); exit();
}
if (isset($_GET['error'])) {
    header("Location: /settings/smtp?err=" . urlencode($_GET['error_description'] ?? $_GET['error'])); exit();
}

/* ── Bắt đầu kết nối: chuyển hướng sang Microsoft ──────────────────── */
if (isset($_GET['connect'])) {
    $sender = EmailSenders::find($conn, (int)$_GET['connect']);
    if ($sender && $sender['client_id']) {
        $state = $sender['id'] . ':' . bin2hex(random_bytes(8));
        $_SESSION['oauth_state'] = $state;
        header("Location: " . EmailSenders::authUrl($sender, $redirectUri, $state)); exit();
    }
    header("Location: /settings/smtp?err=" . urlencode('Thiếu Client ID cho sender này.')); exit();
}

/* ── POST actions ──────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_sender') {
        $id = EmailSenders::save($conn, [
            'id' => (int)($_POST['id'] ?? 0),
            'name' => trim($_POST['name'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? ''),
            'tenant_id' => trim($_POST['tenant_id'] ?? '') ?: 'common',
            'client_id' => trim($_POST['client_id'] ?? ''),
            'client_secret' => trim($_POST['client_secret'] ?? ''),
        ]);
        header("Location: /settings/smtp?ok=" . urlencode('Đã lưu sender. Bấm "Kết nối Outlook" để cấp quyền.')); exit();
    }
    if ($act === 'delete_sender') { EmailSenders::delete($conn, (int)$_POST['id']); header("Location: /settings/smtp?ok=" . urlencode('Đã xóa sender.')); exit(); }
    if ($act === 'set_default')   { EmailSenders::setDefault($conn, (int)$_POST['id']); header("Location: /settings/smtp?ok=" . urlencode('Đã đặt sender mặc định.')); exit(); }
    if ($act === 'toggle_active') { EmailSenders::setActive($conn, (int)$_POST['id'], (int)$_POST['active']); header("Location: /settings/smtp"); exit(); }
    if ($act === 'test_send') {
        $sender = EmailSenders::find($conn, (int)$_POST['id']);
        $to = trim($_POST['test_to'] ?? '');
        if ($sender && $to) {
            $r = EmailSenders::send($conn, $sender, $to, 'Test email - AHT', '<p>Đây là email kiểm tra từ <b>' . htmlspecialchars($sender['from_email']) . '</b> qua AHT system.</p>');
            header("Location: /settings/smtp?" . ($r['ok'] ? 'ok=' : 'err=') . urlencode($r['ok'] ? "Đã gửi email test tới $to" : ('Gửi thất bại: ' . $r['error']))); exit();
        }
        header("Location: /settings/smtp?err=" . urlencode('Thiếu thông tin gửi test.')); exit();
    }
    // Lưu SMTP fallback cũ
    if ($act === 'save_legacy') {
        $map = ['smtp_host','smtp_port','smtp_user','smtp_encryption','smtp_from_email','smtp_from_name'];
        foreach ($map as $k) {
            $v = $_POST[$k] ?? '';
            $st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $st->bind_param('ss', $k, $v); $st->execute();
        }
        if (!empty($_POST['smtp_pass'])) {
            $k = 'smtp_pass'; $v = $_POST['smtp_pass'];
            $st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $st->bind_param('ss', $k, $v); $st->execute();
        }
        header("Location: /settings/smtp?ok=" . urlencode('Đã lưu SMTP fallback.')); exit();
    }
}

if (isset($_GET['ok']))  { $flash = ['type' => 'success', 'msg' => $_GET['ok']]; }
if (isset($_GET['err'])) { $flash = ['type' => 'error', 'msg' => $_GET['err']]; }

$senders = EmailSenders::all($conn);

// Edit target
$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? EmailSenders::find($conn, $editId) : null;

// Legacy SMTP
$legacy = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
if ($res) { while ($r = $res->fetch_assoc()) { $legacy[$r['setting_key']] = $r['setting_value']; } }
function lg($k, $d) { return htmlspecialchars($d[$k] ?? ''); }
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
        .sender.def{border-color:#0078D4;box-shadow:0 0 0 2px rgba(0,120,212,.12)}
        .s-top{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
        .s-name{font-weight:700;font-size:1rem}
        .s-mail{color:var(--text-secondary);font-size:.88rem}
        .badge{font-size:.7rem;font-weight:700;padding:.18rem .6rem;border-radius:980px}
        .b-def{background:#e0efff;color:#0050a0}
        .b-on{background:#dcfce7;color:#166534}
        .b-off{background:#f1f5f9;color:#64748b}
        .b-warn{background:#fff4e5;color:#b25e00}
        .s-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.9rem;align-items:center}
        .s-actions form{display:inline}
        .code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#f1f5f9;padding:.15rem .45rem;border-radius:6px;font-size:.82rem;word-break:break-all}
        .test-inline input{padding:.45rem .6rem;border:1px solid var(--border-color);border-radius:7px;font-size:.82rem}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        details.guide{margin-top:1.5rem}
        details.guide summary{cursor:pointer;font-weight:600}
        .step{font-size:.9rem;color:var(--text-primary);margin:.4rem 0}
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Email Senders (SMTP)'; include __DIR__ . '/../../../modules/includes/topbar.php'; ?>
        <div class="content-wrapper">
            <div style="margin-bottom:1rem">
                <a href="/settings" class="btn-ghost">← Back to Settings</a>
            </div>

            <?php if ($flash['msg']): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
            <?php endif; ?>

            <!-- Danh sách senders -->
            <div class="form-card">
                <div style="margin-bottom:1.25rem;border-bottom:1px solid var(--border-light);padding-bottom:1rem;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <h2 style="font-size:1.25rem;margin-bottom:.3rem">Email Senders (Outlook / Microsoft 365)</h2>
                        <p style="color:var(--text-secondary);font-size:.9rem;margin:0">Thêm nhiều địa chỉ gửi. Kết nối qua OAuth2 (Azure App) như FluentSMTP. Sender <b>mặc định</b> được dùng cho mọi email hệ thống.</p>
                    </div>
                    <a href="/settings/smtp?edit=new" class="btn-save">+ Thêm sender</a>
                </div>

                <div style="font-size:.85rem;margin-bottom:1rem">Redirect URI cần khai báo trong Azure App:
                    <span class="code"><?= ev($redirectUri) ?></span>
                </div>

                <?php if (!$senders): ?>
                    <p style="color:var(--text-secondary)">Chưa có sender nào. Bấm "+ Thêm sender" để bắt đầu.</p>
                <?php endif; ?>

                <?php foreach ($senders as $s): $connected = !empty($s['refresh_token']); ?>
                    <div class="sender <?= $s['is_default'] ? 'def' : '' ?>">
                        <div class="s-top">
                            <span class="s-name"><?= ev($s['name']) ?></span>
                            <span class="s-mail"><?= ev($s['from_email']) ?><?= $s['from_name'] ? ' · ' . ev($s['from_name']) : '' ?></span>
                            <?php if ($s['is_default']): ?><span class="badge b-def">MẶC ĐỊNH</span><?php endif; ?>
                            <?php if ($connected): ?><span class="badge b-on">Đã kết nối</span><?php else: ?><span class="badge b-warn">Chưa kết nối</span><?php endif; ?>
                            <?php if (!$s['active']): ?><span class="badge b-off">Tắt</span><?php endif; ?>
                        </div>
                        <div class="s-mail" style="margin-top:.3rem">Tenant: <span class="code"><?= ev($s['tenant_id']) ?></span> · Client ID: <span class="code"><?= ev(substr($s['client_id'], 0, 10)) ?>…</span></div>

                        <div class="s-actions">
                            <a href="/settings/smtp?connect=<?= (int)$s['id'] ?>" class="btn-ghost"><?= $connected ? 'Kết nối lại' : 'Kết nối Outlook' ?></a>
                            <a href="/settings/smtp?edit=<?= (int)$s['id'] ?>" class="btn-ghost">Sửa</a>
                            <?php if (!$s['is_default']): ?>
                            <form method="POST" onsubmit="return true"><input type="hidden" name="action" value="set_default"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn-ghost" type="submit">Đặt mặc định</button></form>
                            <?php endif; ?>
                            <form method="POST"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="active" value="<?= $s['active'] ? 0 : 1 ?>"><button class="btn-ghost" type="submit"><?= $s['active'] ? 'Tắt' : 'Bật' ?></button></form>
                            <form method="POST" onsubmit="return confirm('Xóa sender này?')"><input type="hidden" name="action" value="delete_sender"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn-ghost" type="submit" style="color:#dc2626">Xóa</button></form>
                            <?php if ($connected): ?>
                            <form method="POST" class="test-inline" style="display:flex;gap:.4rem;align-items:center;margin-left:auto">
                                <input type="hidden" name="action" value="test_send"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <input type="email" name="test_to" placeholder="email nhận test" required>
                                <button class="btn-ghost" type="submit">Gửi thử</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Form thêm/sửa sender -->
            <?php if ($edit !== null || $editId === 0 && isset($_GET['edit'])): $e = $edit ?: ['id'=>0,'name'=>'','from_email'=>'','from_name'=>'','tenant_id'=>'common','client_id'=>'']; ?>
            <div class="form-card" id="editform" style="margin-top:1.5rem">
                <h2 style="font-size:1.15rem;margin-bottom:1rem"><?= $e['id'] ? 'Sửa sender' : 'Thêm sender mới' ?></h2>
                <form method="POST">
                    <input type="hidden" name="action" value="save_sender">
                    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                    <div class="grid2">
                        <div class="form-group"><label>Tên hiển thị (nội bộ) *</label><input name="name" required value="<?= ev($e['name']) ?>" placeholder="VD: HR Mailbox"></div>
                        <div class="form-group"><label>From Name (tên người gửi)</label><input name="from_name" value="<?= ev($e['from_name']) ?>" placeholder="VD: AHT Tuyển dụng"></div>
                    </div>
                    <div class="form-group"><label>From Email (mailbox Outlook đã uỷ quyền) *</label><input name="from_email" type="email" required value="<?= ev($e['from_email']) ?>" placeholder="hr@arrowhitech.com">
                        <p class="input-hint">Phải là chính mailbox dùng để đăng nhập &amp; cấp quyền OAuth.</p></div>
                    <div class="grid2">
                        <div class="form-group"><label>Tenant ID</label><input name="tenant_id" value="<?= ev($e['tenant_id']) ?>" placeholder="common hoặc Directory (tenant) ID">
                            <p class="input-hint">Tenant đơn: điền tenant ID. Đa tenant: <span class="code">common</span>.</p></div>
                        <div class="form-group"><label>Client ID (Application ID) *</label><input name="client_id" required value="<?= ev($e['client_id']) ?>" placeholder="GUID từ Azure App"></div>
                    </div>
                    <div class="form-group"><label>Client Secret <?= $e['id'] ? '(để trống nếu không đổi)' : '*' ?></label><input name="client_secret" type="password" <?= $e['id'] ? '' : 'required' ?> placeholder="Secret value từ Azure App"></div>
                    <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1rem">
                        <a href="/settings/smtp" class="btn-ghost">Hủy</a>
                        <button type="submit" class="btn-save">Lưu sender</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Hướng dẫn Azure -->
            <div class="form-card" style="margin-top:1.5rem">
                <details class="guide" open>
                    <summary>Hướng dẫn tạo Azure App (OAuth2 cho Outlook/M365)</summary>
                    <div style="margin-top:1rem">
                        <div class="step">1. Vào <b>portal.azure.com → Microsoft Entra ID → App registrations → New registration</b>.</div>
                        <div class="step">2. Redirect URI (loại <b>Web</b>): <span class="code"><?= ev($redirectUri) ?></span></div>
                        <div class="step">3. <b>Certificates &amp; secrets → New client secret</b> → copy <i>Value</i> (chính là Client Secret).</div>
                        <div class="step">4. <b>API permissions → Add → Microsoft Graph / Office 365 Exchange Online → Delegated → SMTP.Send</b> (và <span class="code">offline_access</span>). Grant admin consent.</div>
                        <div class="step">5. Copy <b>Application (client) ID</b> và <b>Directory (tenant) ID</b> vào form trên, lưu, rồi bấm <b>Kết nối Outlook</b> để đăng nhập mailbox &amp; cấp quyền.</div>
                        <div class="step" style="color:#b25e00">Lưu ý: tenant của bạn phải bật <b>Authenticated SMTP (SMTP AUTH)</b> cho mailbox đó.</div>
                    </div>
                </details>
            </div>

            <!-- SMTP fallback cũ -->
            <div class="form-card" style="margin-top:1.5rem">
                <details class="guide">
                    <summary>SMTP fallback (dùng khi không có sender nào kết nối)</summary>
                    <form method="POST" style="margin-top:1rem">
                        <input type="hidden" name="action" value="save_legacy">
                        <div class="grid2">
                            <div class="form-group"><label>SMTP Host</label><input name="smtp_host" value="<?= lg('smtp_host',$legacy) ?>" placeholder="smtp.office365.com"></div>
                            <div class="form-group"><label>Port</label><input name="smtp_port" value="<?= lg('smtp_port',$legacy) ?>" placeholder="587"></div>
                        </div>
                        <div class="grid2">
                            <div class="form-group"><label>Encryption</label>
                                <select name="smtp_encryption">
                                    <?php $enc = $legacy['smtp_encryption'] ?? 'tls'; foreach (['tls'=>'TLS','ssl'=>'SSL','none'=>'None'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= $enc===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="form-group"><label>Username / Email</label><input name="smtp_user" value="<?= lg('smtp_user',$legacy) ?>"></div>
                        </div>
                        <div class="form-group"><label>Password / App Password</label><input type="password" name="smtp_pass" placeholder="Để trống nếu không đổi"></div>
                        <div class="grid2">
                            <div class="form-group"><label>From Email</label><input type="email" name="smtp_from_email" value="<?= lg('smtp_from_email',$legacy) ?>"></div>
                            <div class="form-group"><label>From Name</label><input name="smtp_from_name" value="<?= lg('smtp_from_name',$legacy) ?>"></div>
                        </div>
                        <div style="display:flex;justify-content:flex-end"><button class="btn-save" type="submit">Lưu SMTP fallback</button></div>
                    </form>
                </details>
            </div>

        </div>
    </main>
</div>
</body>
</html>
