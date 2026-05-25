<?php
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'] ?? 'User';
$avatar = $_SESSION['avatar'] ?? '';

$configFile = __DIR__ . '/../../../config/arrowhitech_config.json';
$message = '';
$messageType = '';

$config = [
    'api_url'        => 'https://api-profile.arrowhitech.com',
    'api_token'      => '',
    'webhook_secret' => '',
];

if (file_exists($configFile)) {
    $saved = json_decode(file_get_contents($configFile), true);
    if (is_array($saved)) {
        // chỉ lấy các key cần thiết, bỏ htpasswd cũ nếu có
        foreach (['api_url', 'api_token', 'webhook_secret'] as $k) {
            if (isset($saved[$k])) $config[$k] = $saved[$k];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $new_secret = trim($_POST['webhook_secret'] ?? '');
    $config = [
        'api_url'        => rtrim(trim($_POST['api_url'] ?? ''), '/'),
        'api_token'      => trim($_POST['api_token'] ?? ''),
        'webhook_secret' => $new_secret ?: $config['webhook_secret'],
    ];

    if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
        $message = 'Đã lưu cấu hình ArrowHitech API thành công!';
        $messageType = 'success';
    } else {
        $message = 'Không thể lưu file cấu hình. Vui lòng kiểm tra quyền ghi.';
        $messageType = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cấu hình ArrowHitech API - AHT KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .settings-header { background: #f8fafc; padding: 1.5rem 2rem; border-bottom: 1px solid #e2e8f0; }
        .settings-header h2 { margin: 0; color: #0f172a; font-size: 1.25rem; font-weight: 600; }
        .settings-header p { margin: .5rem 0 0; color: #64748b; font-size: .875rem; }
        .settings-body { padding: 2rem; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: .5rem; font-weight: 500; color: #334155; font-size: .95rem; }

        .badge-auto {
            font-size: .7rem; font-weight: 600; padding: 2px 7px;
            border-radius: 20px; margin-left: 6px; vertical-align: middle;
            background: #e0f2fe; color: #0369a1;
        }

        .form-control {
            width: 100%; padding: .75rem 1rem; border: 1px solid #cbd5e1;
            border-radius: 6px; font-size: .95rem; transition: all .2s;
            background: #fff; box-sizing: border-box;
        }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .form-control[readonly] { background: #f8fafc; color: #64748b; cursor: default; }

        .input-group { display: flex; gap: 8px; align-items: stretch; }
        .input-group .form-control { flex: 1; }

        .input-with-toggle { position: relative; flex: 1; }
        .input-with-toggle .form-control { padding-right: 3rem; }
        .toggle-visibility {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #64748b;
            padding: 0; display: flex; align-items: center;
        }
        .toggle-visibility:hover { color: #1e293b; }

        .help-text { margin-top: .4rem; font-size: .8rem; color: #64748b; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: .75rem 1.25rem; border-radius: 6px; font-weight: 500;
            cursor: pointer; transition: all .2s; border: none; gap: 6px; font-size: .875rem;
            white-space: nowrap; flex-shrink: 0;
        }
        .btn-primary  { background: #2563eb; color: white; }
        .btn-primary:hover  { background: #1d4ed8; }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-ghost { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .btn-ghost:hover { background: #dcfce7; }

        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; font-size: .9rem; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .share-box {
            background: #0f172a; color: #e2e8f0; border-radius: 8px;
            padding: 1rem 1.25rem; font-family: 'Courier New', monospace;
            font-size: .82rem; line-height: 2; margin-top: .75rem;
        }
        .share-box .key   { color: #7dd3fc; }
        .share-box .val   { color: #86efac; }
        .share-box .label { color: #94a3b8; font-style: italic; font-family: Inter, sans-serif; font-size: .78rem; }

        .section-divider {
            border: none; border-top: 1px solid #e2e8f0;
            margin: 2rem 0;
        }

        .section-title {
            font-size: .8rem; font-weight: 600; color: #94a3b8;
            text-transform: uppercase; letter-spacing: .06em;
            margin-bottom: 1.25rem;
        }

        .info-box {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;
            padding: 1rem 1.25rem; margin-bottom: 1.5rem;
            font-size: .875rem; color: #1e40af; line-height: 1.6;
        }
        .info-box strong { color: #1e3a8a; }
        .info-box code {
            background: #dbeafe; padding: 1px 5px; border-radius: 4px;
            font-family: 'Courier New', monospace; font-size: .8rem; border: 1px solid #93c5fd;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php
        $page_title = 'Cấu hình ArrowHitech API';
        include __DIR__ . '/../../../modules/includes/topbar.php';
        ?>

        <div class="settings-container">

            <?php if ($message): ?>
                <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php if ($messageType === 'success'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="settings-card">
                <div class="settings-header">
                    <h2>Kết nối ArrowHitech Profile API</h2>
                    <p>Cấu hình để hệ thống AHT KPI gọi ArrowHitech và nhận callback từ ArrowHitech</p>
                </div>

                <div class="settings-body">
                    <form method="POST" id="arrowForm">
                        <input type="hidden" name="action" value="save">

                        <!-- ── SECTION 1: Gọi đi ArrowHitech ── -->
                        <div class="section-title">Phần 1 — Cấu hình gọi ArrowHitech (do ArrowHitech cấp)</div>

                        <div class="form-group">
                            <label for="api_url">API URL <span style="color:#ef4444">*</span></label>
                            <input type="url" id="api_url" name="api_url" class="form-control"
                                   value="<?php echo htmlspecialchars($config['api_url']); ?>"
                                   placeholder="https://api-profile.arrowhitech.com" required>
                            <div class="help-text">Base URL của ArrowHitech API (không có dấu / ở cuối)</div>
                        </div>

                        <div class="form-group">
                            <label for="api_token">API Token (X-API-Key) <span style="color:#ef4444">*</span></label>
                            <div class="input-group">
                                <div class="input-with-toggle">
                                    <input type="password" id="api_token" name="api_token" class="form-control"
                                           value="<?php echo htmlspecialchars($config['api_token']); ?>"
                                           placeholder="Token do ArrowHitech cấp" required>
                                    <button type="button" class="toggle-visibility" onclick="toggleField('api_token','eyeToken','eyeOffToken')">
                                        <svg id="eyeToken" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <svg id="eyeOffToken" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="copyField('api_token')">Copy</button>
                            </div>
                            <div class="help-text">Token do ArrowHitech cấp — sẽ gửi qua header <code>X-API-Key</code> trong mỗi request</div>
                        </div>

                        <hr class="section-divider">

                        <!-- ── SECTION 2: Webhook nhận callback ── -->
                        <div class="section-title">Phần 2 — Webhook nhận callback từ ArrowHitech (cung cấp cho họ)</div>

                        <div class="info-box">
                            Tạo <strong>Webhook Secret</strong> bên dưới rồi share cho ArrowHitech.
                            Họ sẽ gửi kèm secret này qua header <code>X-Webhook-Secret</code> mỗi khi callback về hệ thống — để xác thực request hợp lệ.
                        </div>

                        <div class="form-group">
                            <label for="webhook_secret">Webhook Secret <span style="color:#ef4444">*</span></label>
                            <div class="input-group">
                                <div class="input-with-toggle">
                                    <input type="password" id="webhook_secret" name="webhook_secret" class="form-control"
                                           value="<?php echo htmlspecialchars($config['webhook_secret']); ?>"
                                           placeholder="Bấm Generate để tạo token ngẫu nhiên">
                                    <button type="button" class="toggle-visibility" onclick="toggleField('webhook_secret','eyeSecret','eyeOffSecret')">
                                        <svg id="eyeSecret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <svg id="eyeOffSecret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-ghost" onclick="generateSecret()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                                    Generate
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="copyField('webhook_secret')">Copy</button>
                            </div>
                            <div class="help-text">Sau khi lưu, copy secret này và cung cấp cho team ArrowHitech</div>
                        </div>

                        <!-- Share box -->
                        <?php if ($config['webhook_secret']): ?>
                        <div class="form-group">
                            <label>Thông tin cung cấp cho ArrowHitech</label>
                            <div class="share-box">
                                <span class="label"># Header xác thực phải gửi kèm mỗi callback request</span><br>
                                <span class="key">X-Webhook-Secret:</span> <span class="val"><?php echo htmlspecialchars($config['webhook_secret']); ?></span>
                            </div>
                            <button type="button" class="btn btn-secondary" style="margin-top:8px;font-size:.8rem;padding:.5rem 1rem;" onclick="copyShareBox()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copy secret
                            </button>
                        </div>
                        <?php endif; ?>

                        <hr class="section-divider">

                        <div style="margin-top:1.5rem;">
                            <button type="submit" class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                Lưu cấu hình
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function toggleField(inputId, eyeId, eyeOffId) {
    const input  = document.getElementById(inputId);
    const eye    = document.getElementById(eyeId);
    const eyeOff = document.getElementById(eyeOffId);
    const show   = input.type === 'password';
    input.type        = show ? 'text' : 'password';
    eye.style.display    = show ? 'none' : '';
    eyeOff.style.display = show ? '' : 'none';
}

function copyField(inputId) {
    const el  = document.getElementById(inputId);
    const val = el.value || el.defaultValue;
    navigator.clipboard.writeText(val).then(() => showToast('Đã copy!'));
}

function generateSecret() {
    const arr = new Uint8Array(32);
    crypto.getRandomValues(arr);
    const hex = Array.from(arr).map(b => b.toString(16).padStart(2,'0')).join('');
    const input = document.getElementById('webhook_secret');
    input.value = hex;
    input.type  = 'text'; // show it after generating
    document.getElementById('eyeSecret').style.display    = 'none';
    document.getElementById('eyeOffSecret').style.display = '';
}

function copyShareBox() {
    const secret = document.getElementById('webhook_secret').value;
    navigator.clipboard.writeText(secret).then(() => showToast('Đã copy secret!'));
}

function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position:'fixed', bottom:'24px', right:'24px', background:'#1e293b', color:'#f1f5f9',
        padding:'10px 18px', borderRadius:'8px', fontSize:'.875rem', zIndex:9999,
        boxShadow:'0 4px 12px rgba(0,0,0,.3)', transition:'opacity .3s'
    });
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = 0; setTimeout(() => t.remove(), 300); }, 2000);
}
</script>
</body>
</html>
