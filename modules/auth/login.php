<?php
require_once __DIR__ . '/../../config/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /dashboard");
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter all required information';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, department_id, can_view_invoice, can_view_all_debts, is_am_bd, can_view_odoo_logs FROM users WHERE username = ?");
        if (!$stmt) {
            $error = "Lỗi Database: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['department_id'] = $user['department_id'];
                    $_SESSION['can_view_invoice'] = $user['can_view_invoice'];
                    $_SESSION['can_view_all_debts'] = $user['can_view_all_debts'];
                    $_SESSION['is_am_bd'] = $user['is_am_bd'];
                    $_SESSION['can_view_odoo_logs'] = $user['can_view_odoo_logs'] ?? 0;

                    header("Location: /dashboard");
                    exit();
                } else {
                    $error = 'Incorrect username or password';
                }
            } else {
                $error = 'Incorrect username or password';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Management System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="login-container">
        <div class="login-background">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <img src="https://www.arrowhitech.com/wp-content/uploads/2025/06/Logo.svg" alt="ArrowHitech Logo"
                        class="logo-icon">
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to your management system</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                        <path d="M12 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <circle cx="12" cy="16" r="1" fill="currentColor" />
                    </svg>
                    <span>
                        <?php echo htmlspecialchars($error); ?>
                    </span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required
                            autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path
                                d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        <span class="checkbox-label">Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <span>Sign In</span>
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </form>

            <div id="passkeySection" style="display:none; margin-top: 1rem;">
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                    <div style="flex:1; height:1px; background:#e2e8f0;"></div>
                    <span style="color:#94a3b8; font-size:0.8rem; white-space:nowrap;">or</span>
                    <div style="flex:1; height:1px; background:#e2e8f0;"></div>
                </div>
                <button type="button" id="btnPasskey" class="btn-login"
                    style="background:#f8fafc; border:1.5px solid #e2e8f0; color:#1e293b; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <ellipse cx="9" cy="9.5" rx="5" ry="5.5" stroke="#6366f1" stroke-width="2"/>
                        <path d="M4 9.5C4 6.74 6.24 4.5 9 4.5" stroke="#6366f1" stroke-width="2" stroke-linecap="round"/>
                        <path d="M14 13l1.5 1.5L19 10" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 14.5v5M7 19.5h4" stroke="#6366f1" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span id="btnPasskeyLabel">Sign in with Passkey / Biometric</span>
                </button>
            </div>

            <div id="passkeyError" style="display:none; margin-top:0.75rem; padding:0.75rem 1rem; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:10px; color:#dc2626; font-size:0.875rem;"></div>


        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
    (function () {
        const usernameInput  = document.getElementById('username');
        const passkeySection = document.getElementById('passkeySection');
        const btnPasskey     = document.getElementById('btnPasskey');
        const passkeyError   = document.getElementById('passkeyError');

        if (window.PublicKeyCredential) {
            passkeySection.style.display = 'block';
        }

        function b64urlToBuffer(b64url) {
            const b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
            const pad = (4 - b64.length % 4) % 4;
            const binary = atob(b64 + '='.repeat(pad));
            const buf = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) buf[i] = binary.charCodeAt(i);
            return buf.buffer;
        }

        function bufferToB64url(buf) {
            const bytes = new Uint8Array(buf);
            let bin = '';
            bytes.forEach(b => bin += String.fromCharCode(b));
            return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        function showError(msg) {
            passkeyError.textContent = msg;
            passkeyError.style.display = 'block';
        }

        btnPasskey.addEventListener('click', async () => {
            passkeyError.style.display = 'none';
            const username = usernameInput.value.trim();
            if (!username) {
                showError('Please enter your username first.');
                usernameInput.focus();
                return;
            }

            btnPasskey.disabled = true;
            document.getElementById('btnPasskeyLabel').textContent = 'Waiting for biometric...';

            try {
                const optRes = await fetch('/auth/webauthn/login-options', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username }),
                });
                const optData = await optRes.json();
                if (!optRes.ok) { showError(optData.error || 'Failed to start passkey login.'); return; }

                const opts = optData.options;
                opts.challenge = b64urlToBuffer(opts.challenge);
                opts.allowCredentials = (opts.allowCredentials || []).map(c => ({
                    ...c, id: b64urlToBuffer(c.id)
                }));

                const assertion = await navigator.credentials.get({ publicKey: opts });

                const payload = {
                    id:   assertion.id,
                    type: assertion.type,
                    response: {
                        clientDataJSON:    bufferToB64url(assertion.response.clientDataJSON),
                        authenticatorData: bufferToB64url(assertion.response.authenticatorData),
                        signature:         bufferToB64url(assertion.response.signature),
                        userHandle: assertion.response.userHandle
                            ? bufferToB64url(assertion.response.userHandle) : null,
                    },
                };

                const loginRes  = await fetch('/auth/webauthn/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const loginData = await loginRes.json();

                if (loginData.success) {
                    window.location.href = loginData.redirect || '/dashboard';
                } else {
                    showError(loginData.error || 'Authentication failed.');
                }
            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    showError('Biometric authentication was cancelled or timed out.');
                } else {
                    showError('Error: ' + err.message);
                }
            } finally {
                btnPasskey.disabled = false;
                document.getElementById('btnPasskeyLabel').textContent = 'Sign in with Passkey / Biometric';
            }
        });
    })();
    </script>
</body>

</html>
