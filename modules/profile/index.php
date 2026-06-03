<?php
require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fix missing columns if needed
$schema_updated = false;
if (!array_key_exists('avatar', $user)) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
    $schema_updated = true;
}
if (!array_key_exists('phone', $user)) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    $schema_updated = true;
}
if ($schema_updated) {
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

// --- HANDLE AVATAR UPLOAD (AJAX & FORM) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    // ... (Keep existing avatar logic) ...
    $response = ['success' => false, 'message' => ''];
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../public/uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_size = $_FILES['avatar']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($file_ext, $allowed)) {
            if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    // Update Database
                    $avatar_url = '/public/uploads/avatars/' . $new_filename;
                    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->bind_param("si", $avatar_url, $user_id);

                    if ($stmt->execute()) {
                        $_SESSION['avatar'] = $avatar_url;
                        $response['success'] = true;
                        $response['message'] = 'Avatar updated successfully!';
                        $response['avatar_url'] = $avatar_url;

                        $success_message = 'Avatar updated successfully!';
                        $user['avatar'] = $avatar_url;
                    } else {
                        $response['message'] = 'Database error.';
                        $error_message = 'Database error.';
                    }
                } else {
                    $response['message'] = 'Failed to save file.';
                    $error_message = 'Failed to save file.';
                }
            } else {
                $response['message'] = 'File too large (Max 5MB).';
                $error_message = 'File too large (Max 5MB).';
            }
        } else {
            $response['message'] = 'Invalid file type.';
            $error_message = 'Invalid file type.';
        }
    } else {
        $response['message'] = 'No file uploaded.';
        $error_message = 'No file uploaded.';
    }

    // Return JSON if AJAX
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// --- HANDLE INFO UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);

    if (empty($full_name)) {
        $error_message = 'Full name is required';
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $phone, $user_id);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $success_message = 'Profile info updated successfully!';
            $user['full_name'] = $full_name;
            $user['phone'] = $phone;
        } else {
            $error_message = 'Failed to update profile info';
        }
    }
}

// --- HANDLE PASSWORD CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All password fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password must be at least 6 characters';
    } else {
        if (password_verify($current_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Failed to update password';
            }
        } else {
            $error_message = 'Current password is incorrect';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

    <style>
        .profile-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 2rem;
            align-items: start;
            max-width: 1400px;
            margin: 0 auto;
        }

        .avatar-card {
            text-align: center;
            position: sticky;
            top: 6rem;
        }

        .profile-avatar-large {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            position: relative;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            font-weight: 700;
            border: 5px solid white;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .profile-avatar-large:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.25);
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .upload-area {
            position: relative;
            margin-top: 1.5rem;
        }

        .file-input {
            display: none;
        }

        .btn-upload {
            background: white;
            border: 2px dashed var(--border-color);
            color: var(--text-secondary);
            padding: 1rem;
            border-radius: 12px;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .btn-upload:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        /* Improved Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-full {
            grid-column: span 2;
        }

        .form-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            background: white;
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            margin-bottom: 2rem;
        }

        .section-header {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header p {
            color: var(--text-secondary);
            margin-top: 0.25rem;
            font-size: 0.9rem;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-tertiary);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--text-primary);
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-group input:focus+.input-icon {
            color: var(--primary-color);
        }

        .form-group input:disabled {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            cursor: not-allowed;
            border-color: var(--border-light);
        }

        .btn-submit-group {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .btn-primary {
            min-width: 140px;
            justify-content: center;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }

        .crop-container {
            max-height: 60vh;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .crop-container img {
            max-width: 100%;
            max-height: 50vh;
            display: block;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            color: var(--text-primary);
        }

        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .avatar-card {
                position: static;
                margin-bottom: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-full {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $page_title = 'My Profile';
            $page_subtitle = 'Manage your account settings';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                            <path d="M15 9L9 15M9 9L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="profile-layout">
                    <!-- Column 1: Avatar -->
                    <div class="avatar-card">
                        <div class="form-section" style="padding: 2rem 1.5rem;">
                            <!-- Main Avatar Display -->
                            <div class="profile-avatar-large" id="mainAvatarDisplay">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin-bottom:0.5rem; font-size:1.25rem; font-weight:700;">
                                <?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <p style="color:var(--text-secondary); margin-bottom:1.5rem; font-size:0.9rem;">
                                <?php echo htmlspecialchars($user['email']); ?></p>

                            <div class="upload-area">
                                <label for="avatarInput" class="btn-upload">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    Change Avatar
                                </label>
                                <input type="file" id="avatarInput" class="file-input" accept="image/*">
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Info & Password -->
                    <div style="display:flex; flex-direction:column;">
                        <!-- Profile Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <h2>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        style="color:var(--primary-color);">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    Profile Information
                                </h2>
                                <p>Update your account's profile information and email address.</p>
                            </div>

                            <form method="POST">
                                <div class="form-grid">
                                    <div class="form-group form-full">
                                        <label for="full_name">Full Name</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="full_name" name="full_name"
                                                value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <div class="input-wrapper">
                                            <input type="email" id="email"
                                                value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                                </path>
                                                <polyline points="22,6 12,13 2,6"></polyline>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <div class="input-wrapper">
                                            <input type="tel" id="phone" name="phone"
                                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                placeholder="+84 123 456 789">
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                                </path>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="username"
                                                value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="role">Role</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="role"
                                                value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>"
                                                disabled>
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z">
                                                </path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="btn-submit-group">
                                    <button type="submit" name="update_profile" class="btn-primary">
                                        Save Information
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Passkeys / Biometric Login -->
                        <div class="form-section" id="passkeySection">
                            <div class="section-header">
                                <h2>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        style="color:var(--primary-color);">
                                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                                    </svg>
                                    Passkeys &amp; Biometric Login
                                </h2>
                                <p>Register your fingerprint, Face ID, or Windows Hello to sign in without a password.</p>
                            </div>

                            <div id="passkeyList" style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1rem;">
                                <p style="color:var(--text-secondary); font-size:0.9rem;">Loading...</p>
                            </div>

                            <button type="button" id="btnRegisterPasskey" class="btn-primary" style="max-width:220px;">
                                + Register New Passkey
                            </button>
                            <div id="passkeyMsg" style="display:none; margin-top:0.75rem; padding:0.75rem 1rem; border-radius:10px; font-size:0.875rem;"></div>
                        </div>

                        <!-- Change Password -->
                        <div class="form-section">
                            <div class="section-header">
                                <h2>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        style="color:var(--primary-color);">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    Change Password
                                </h2>
                                <p>Ensure your account is using a long, random password to stay secure.</p>
                            </div>

                            <form method="POST">
                                <div class="form-grid">
                                    <div class="form-group form-full">
                                        <label for="current_password">Current Password</label>
                                        <div class="input-wrapper">
                                            <input type="password" id="current_password" name="current_password"
                                                required>
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <div class="input-wrapper">
                                            <input type="password" id="new_password" name="new_password" required
                                                minlength="6">
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <div class="input-wrapper">
                                            <input type="password" id="confirm_password" name="confirm_password"
                                                required minlength="6">
                                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="btn-submit-group">
                                    <button type="submit" name="change_password" class="btn-primary">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- CROP MODAL (KEEP EXISTING) -->
    <div class="modal-overlay" id="cropModal">
        <div class="modal-content">
            <div class="section-header" style="border:none; margin-bottom:0;">
                <h2>Crop Profile Picture</h2>
                <p>Drag and resize to crop your avatar</p>
            </div>

            <div class="crop-container">
                <img id="imageToCrop" src="" alt="Image to crop">
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeCropModal()">Cancel</button>
                <button class="btn-primary" onclick="saveCroppedAvatar()" id="btnSaveCrop">
                    Crop & Save
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/dashboard.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
    // ── Passkey management ────────────────────────────────────────────────
    (function () {
        const listEl   = document.getElementById('passkeyList');
        const btnReg   = document.getElementById('btnRegisterPasskey');
        const msgEl    = document.getElementById('passkeyMsg');

        if (!window.PublicKeyCredential) {
            listEl.innerHTML = '<p style="color:var(--text-secondary);">Your browser does not support passkeys.</p>';
            btnReg.style.display = 'none';
            return;
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

        function showMsg(text, ok) {
            msgEl.textContent = text;
            msgEl.style.display = 'block';
            msgEl.style.background = ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
            msgEl.style.border     = ok ? '1px solid rgba(16,185,129,0.3)' : '1px solid rgba(239,68,68,0.3)';
            msgEl.style.color      = ok ? '#10b981' : '#ef4444';
            setTimeout(() => { msgEl.style.display = 'none'; }, 4000);
        }

        function formatDate(str) {
            if (!str) return 'Never';
            const d = new Date(str);
            return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
        }

        async function loadPasskeys() {
            const res  = await fetch('/auth/webauthn/list');
            const data = await res.json();
            const pks  = data.passkeys || [];

            if (!pks.length) {
                listEl.innerHTML = '<p style="color:var(--text-secondary); font-size:0.9rem;">No passkeys registered yet.</p>';
                return;
            }

            listEl.innerHTML = pks.map(pk => `
                <div style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 1rem;
                     border:1px solid var(--border-color); border-radius:10px; background:var(--bg-secondary);">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem;">${escHtml(pk.device_name)}</div>
                        <div style="font-size:0.8rem; color:var(--text-secondary);">
                            Registered: ${formatDate(pk.created_at)} &nbsp;·&nbsp;
                            Last used: ${formatDate(pk.last_used_at)}
                        </div>
                    </div>
                    <button onclick="deletePasskey(${pk.id})"
                        style="background:none; border:1px solid rgba(239,68,68,0.4); color:#ef4444;
                               padding:0.4rem 0.9rem; border-radius:8px; cursor:pointer; font-size:0.8rem;
                               transition:all 0.2s;"
                        onmouseover="this.style.background='rgba(239,68,68,0.1)'"
                        onmouseout="this.style.background='none'">
                        Remove
                    </button>
                </div>`).join('');
        }

        function escHtml(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        window.deletePasskey = async function (id) {
            if (!confirm('Remove this passkey?')) return;
            const res  = await fetch('/auth/webauthn/delete', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({id}),
            });
            const data = await res.json();
            if (data.success) { showMsg('Passkey removed.', true); loadPasskeys(); }
            else showMsg(data.error || 'Failed to remove passkey.', false);
        };

        btnReg.addEventListener('click', async () => {
            msgEl.style.display = 'none';
            btnReg.disabled = true;
            btnReg.textContent = 'Waiting for biometric...';

            try {
                const optRes  = await fetch('/auth/webauthn/register-options', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                });
                const optData = await optRes.json();
                if (!optRes.ok) { showMsg(optData.error || 'Error', false); return; }

                const opts = optData.options;
                opts.challenge      = b64urlToBuffer(opts.challenge);
                opts.user.id        = b64urlToBuffer(opts.user.id);
                opts.excludeCredentials = (opts.excludeCredentials || []).map(c => ({
                    ...c, id: b64urlToBuffer(c.id)
                }));

                const cred = await navigator.credentials.create({ publicKey: opts });

                const payload = {
                    id:   cred.id,
                    type: cred.type,
                    response: {
                        clientDataJSON:    bufferToB64url(cred.response.clientDataJSON),
                        attestationObject: bufferToB64url(cred.response.attestationObject),
                    },
                };

                const regRes  = await fetch('/auth/webauthn/register', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(payload),
                });
                const regData = await regRes.json();

                if (regData.success) {
                    showMsg('Passkey registered: ' + regData.deviceName, true);
                    loadPasskeys();
                } else {
                    showMsg(regData.error || 'Registration failed.', false);
                }
            } catch (err) {
                if (err.name === 'NotAllowedError') showMsg('Registration cancelled or timed out.', false);
                else showMsg('Error: ' + err.message, false);
            } finally {
                btnReg.disabled = false;
                btnReg.textContent = '+ Register New Passkey';
            }
        });

        loadPasskeys();
    })();
    </script>

    <script>
        // ... (Keep existing JS logic for Cropper) ...
        let cropper = null;
        const avatarInput = document.getElementById('avatarInput');
        const cropModal = document.getElementById('cropModal');
        const imageToCrop = document.getElementById('imageToCrop');
        const btnSaveCrop = document.getElementById('btnSaveCrop');

        avatarInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    imageToCrop.src = e.target.result;
                    openCropModal();
                };
                reader.readAsDataURL(file);
            }
            avatarInput.value = '';
        });

        function openCropModal() {
            cropModal.classList.add('show');
            if (cropper) cropper.destroy();
            cropper = new Cropper(imageToCrop, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                responsive: true,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        }

        function closeCropModal() {
            cropModal.classList.remove('show');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function saveCroppedAvatar() {
            if (!cropper) return;
            const originalBtnText = btnSaveCrop.innerHTML;
            btnSaveCrop.innerHTML = 'Saving...';
            btnSaveCrop.disabled = true;

            cropper.getCroppedCanvas({ width: 300, height: 300 }).toBlob(function (blob) {
                const formData = new FormData();
                formData.append('avatar', blob, 'avatar.png');
                formData.append('upload_avatar', 'true');

                fetch('/profile', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const mainAvatar = document.querySelector('#mainAvatarDisplay img');
                            const headerAvatar = document.querySelector('#headerAvatar img');

                            // Force refresh image with timestamp
                            const newSrc = data.avatar_url + '?t=' + new Date().getTime();

                            if (mainAvatar) mainAvatar.src = newSrc;
                            else document.getElementById('mainAvatarDisplay').innerHTML = `<img src="${newSrc}" alt="Profile">`;

                            if (headerAvatar) headerAvatar.src = newSrc;
                            else document.getElementById('headerAvatar').innerHTML = `<img src="${newSrc}" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;

                            closeCropModal();

                            // Show success alert
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success';
                            styleAlert(alertDiv, 'Avatar updated successfully!');
                            document.body.appendChild(alertDiv);
                            setTimeout(() => alertDiv.remove(), 3000);
                        } else {
                            alert(data.message || 'Upload failed');
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('An error occurred.');
                    })
                    .finally(() => {
                        btnSaveCrop.innerHTML = originalBtnText;
                        btnSaveCrop.disabled = false;
                    });
            }, 'image/png');
        }

        function styleAlert(div, msg) {
            div.style.position = 'fixed';
            div.style.top = '20px';
            div.style.right = '20px';
            div.style.zIndex = '2000';
            div.style.background = 'white';
            div.style.border = '1px solid #10b981';
            div.style.color = '#10b981';
            div.style.padding = '1rem';
            div.style.borderRadius = '10px';
            div.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.gap = '0.5rem';
            div.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> ${msg}`;
        }
    </script>
</body>

</html>