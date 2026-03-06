<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;
$error_message = '';
$success_message = '';
$reset_success_message = '';

// Get User ID
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($edit_id <= 0) {
    header("Location: /settings/users");
    exit();
}

// Auto-migrate sale_level_id column
$chk = $conn->query("SHOW COLUMNS FROM users LIKE 'sale_level_id'");
if ($chk && $chk->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN sale_level_id INT DEFAULT NULL");
}

// Auto-migrate user_sale_level_history table
$conn->query("
    CREATE TABLE IF NOT EXISTS user_sale_level_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sale_level_id INT NOT NULL,
        apply_quarter INT NOT NULL,
        apply_year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uidx_history (user_id, apply_year, apply_quarter)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Fetch User Data
function fetchUserData($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
$user = fetchUserData($conn, $edit_id);

if (!$user) {
    echo "User not found.";
    exit();
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // UPDATE PROFILE
    if (isset($_POST['update_profile'])) {
        $id = intval($_POST['id']);
        $email = trim($_POST['email']);
        $name = trim($_POST['full_name']);
        $emp_code = trim($_POST['employee_code']);
        $job = trim($_POST['job_title']);
        $level = $_POST['level'];
        $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : NULL;
        $status = $_POST['status'];
        $join_date = !empty($_POST['join_date']) ? $_POST['join_date'] : NULL;
        $is_am_bd = isset($_POST['is_am_bd']) ? 1 : 0;
        $can_view_invoice = isset($_POST['can_view_invoice']) ? 1 : 0;
        $can_view_all_debts = isset($_POST['can_view_all_debts']) ? 1 : 0;
        $team_ids = isset($_POST['team_ids']) ? $_POST['team_ids'] : [];
        $sale_level_id = ($is_am_bd && !empty($_POST['sale_level_id'])) ? intval($_POST['sale_level_id']) : null;
        $apply_quarter = !empty($_POST['apply_quarter']) ? intval($_POST['apply_quarter']) : null;
        $apply_year = !empty($_POST['apply_year']) ? intval($_POST['apply_year']) : null;
        $role_val = $_POST['role'] ?? 'user';

        $username = trim($_POST['username']);
        if (empty($username) && !empty($email)) {
            $parts = explode('@', $email);
            $username = $parts[0];
        }

        if ($id > 0 && !empty($email)) {
            try {
                // Check duplicates (email/code) excluding current user
                $check = $conn->prepare("SELECT id FROM users WHERE (email = ? OR employee_code = ?) AND id != ?");
                $check->bind_param("ssi", $email, $emp_code, $id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error_message = "Email or Employee Code already exists.";
                } else {
                    $sql = "UPDATE users SET username=?, email=?, full_name=?, employee_code=?, job_title=?, level=?, department_id=?, status=?, join_date=?, is_am_bd=?, can_view_invoice=?, can_view_all_debts=?, role=?, sale_level_id=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssissiiisii", $username, $email, $name, $emp_code, $job, $level, $dept_id, $status, $join_date, $is_am_bd, $can_view_invoice, $can_view_all_debts, $role_val, $sale_level_id, $id);
                    $stmt->execute();

                    // Update session if editing self
                    if ($id == $_SESSION['user_id']) {
                        $_SESSION['can_view_invoice'] = $can_view_invoice;
                        $_SESSION['can_view_all_debts'] = $can_view_all_debts;
                    }

                    // Update Teams
                    $conn->prepare("DELETE FROM user_sale_teams WHERE user_id = ?")->execute([$id]);
                    if ($is_am_bd && !empty($team_ids)) {
                        foreach ($team_ids as $tid) {
                            $ts = $conn->prepare("INSERT INTO user_sale_teams (user_id, team_id) VALUES (?, ?)");
                            $ts->bind_param("ii", $id, $tid);
                            $ts->execute();
                        }
                    }

                    // Insert/Update History
                    if ($sale_level_id && $apply_quarter && $apply_year) {
                        $hq = "INSERT INTO user_sale_level_history (user_id, sale_level_id, apply_quarter, apply_year) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE sale_level_id = ?";
                        $hst = $conn->prepare($hq);
                        $hst->bind_param("iiiii", $id, $sale_level_id, $apply_quarter, $apply_year, $sale_level_id);
                        $hst->execute();
                    }

                    $success_message = "User updated successfully!";

                    // Refresh data
                    $user = fetchUserData($conn, $id);
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }

    // RESET PASSWORD
    if (isset($_POST['reset_password'])) {
        $new_pass = bin2hex(random_bytes(4)); // 8 chars
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_pass, $edit_id);
            if ($stmt->execute()) {
                $reset_success_message = "Password reset successfully. New Password: <strong>$new_pass</strong>";

                // SEND EMAIL VIA PHPMAILER
                $mail = new PHPMailer(true);
                try {
                    // FETCH SMTP CONFIG
                    $smtp_host = '';
                    $smtp_user = '';
                    $smtp_pass = '';
                    $smtp_port = '';
                    $smtp_enc = '';
                    $smtp_from_email = '';
                    $smtp_from_name = '';

                    $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
                    $settings = [];
                    if ($res) {
                        while ($r = $res->fetch_assoc())
                            $settings[$r['setting_key']] = $r['setting_value'];
                    }

                    if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
                        throw new Exception("SMTP settings not configured. Please go to Settings > Email Configuration.");
                    }

                    $mail->isSMTP();
                    $mail->Host = $settings['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $settings['smtp_user'];
                    $mail->Password = $settings['smtp_pass'];
                    $mail->SMTPSecure = ($settings['smtp_encryption'] == 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : (($settings['smtp_encryption'] == 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : '');
                    $mail->Port = $settings['smtp_port'];
                    $mail->CharSet = 'UTF-8';

                    //Recipients
                    $from_email = !empty($settings['smtp_from_email']) ? $settings['smtp_from_email'] : 'no-reply@system.com';
                    $from_name = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : 'System Admin';

                    $mail->setFrom($from_email, $from_name);
                    $mail->addAddress($user['email'], $user['full_name']);

                    //Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Notification';
                    $mail->Body = "
                        <h2>Password Reset Notification</h2>
                        <p>Hello <b>" . htmlspecialchars($user['full_name']) . "</b>,</p>
                        <p>Your password has been reset by the administrator.</p>
                        <p>New Password: <strong style='font-size:16px; background:#eee; padding:5px 10px; border-radius:4px;'>$new_pass</strong></p>
                        <p>Please change your password immediately after logging in.</p>
                        <br>
                        <p>Regards,<br>$from_name</p>
                    ";

                    $mail->send();
                    $reset_success_message .= " (Email notification sent successfully)";
                } catch (Exception $e) {
                    $reset_success_message .= " <br><span style='color:red; font-size:0.9em'>(Email failed: {$e->getMessage()})</span>";
                }

            } else {
                $error_message = "Failed to reset password.";
            }
        }
    }

    // DELETE HISTORY ITEM
    if (isset($_POST['delete_history'])) {
        $history_id = intval($_POST['history_id']);
        $stmt_del = $conn->prepare("DELETE FROM user_sale_level_history WHERE id = ? AND user_id = ?");
        $stmt_del->bind_param("ii", $history_id, $edit_id);
        if ($stmt_del->execute()) {
            $success_message = "Xóa lịch sử level thành công!";
        }
    }
}

// Fetch Data for Dropdowns
$depts = [];
$d_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($d_res) {
    while ($r = $d_res->fetch_assoc()) {
        $depts[] = $r;
    }
}
$levels = ['Intern', 'Fresher', 'Junior', 'Middle', 'Senior', 'Lead', 'Principal', 'Manager', 'Director', 'CTO'];

// Fetch Sale Teams
$sale_teams = [];
$st_res = $conn->query("SELECT id, name FROM sale_teams ORDER BY order_num ASC, name ASC");
if ($st_res) {
    while ($r = $st_res->fetch_assoc()) {
        $sale_teams[] = $r;
    }
}

// Fetch Current User's Teams
$user_teams = [];
$ut_res = $conn->prepare("SELECT team_id FROM user_sale_teams WHERE user_id = ?");
$ut_res->bind_param("i", $edit_id);
$ut_res->execute();
$ut_res_result = $ut_res->get_result();
while ($r = $ut_res_result->fetch_assoc()) {
    $user_teams[] = $r['team_id'];
}

// Fetch Sale Levels grouped by position_type
// Ensure sale_levels table + required columns exist (safe for live server)
$sale_levels_grouped = [];
$sale_levels_flat = [];
$sl_table_check = $conn->query("SHOW TABLES LIKE 'sale_levels'");
if ($sl_table_check && $sl_table_check->num_rows > 0) {
    // Ensure position_type column exists
    $pt_chk = $conn->query("SHOW COLUMNS FROM sale_levels LIKE 'position_type'");
    if ($pt_chk && $pt_chk->num_rows == 0) {
        $conn->query("ALTER TABLE sale_levels ADD COLUMN position_type VARCHAR(100) NOT NULL DEFAULT 'BDE/BCE'");
    }
    // Ensure order_num column exists
    $on_chk = $conn->query("SHOW COLUMNS FROM sale_levels LIKE 'order_num'");
    if ($on_chk && $on_chk->num_rows == 0) {
        $conn->query("ALTER TABLE sale_levels ADD COLUMN order_num INT DEFAULT 0");
    }
    $sl_res = $conn->query("SELECT id, position_type, level_name FROM sale_levels ORDER BY position_type, order_num, id");
    if ($sl_res) {
        while ($r = $sl_res->fetch_assoc()) {
            $sale_levels_grouped[$r['position_type']][] = $r;
            $sale_levels_flat[] = $r;
        }
    }
}

// Fetch Sale Level History for this user
$level_history = [];
$sh_res = $conn->prepare("
    SELECT h.*, sl.level_name, sl.position_type 
    FROM user_sale_level_history h
    LEFT JOIN sale_levels sl ON h.sale_level_id = sl.id
    WHERE h.user_id = ?
    ORDER BY h.apply_year DESC, h.apply_quarter DESC
");
$sh_res->bind_param("i", $edit_id);
$sh_res->execute();
$sh_res_result = $sh_res->get_result();
while ($r = $sh_res_result->fetch_assoc()) {
    $level_history[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Settings</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        .content-wrapper {
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .edit-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            align-items: start;
        }

        .avatar-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: white;
            background: var(--gradient-primary);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
            overflow: hidden;
        }

        .avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.5rem;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-resigned {
            background: #f3f4f6;
            color: #374151;
        }

        .status-on_leave {
            background: #fef3c7;
            color: #92400e;
        }

        .form-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .permissions-box {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .toggle-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .toggle-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #eee;
        }

        .toggle-item:last-child {
            border-bottom: none;
        }

        .toggle-item label {
            margin-bottom: 0;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
        }

        .toggle-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .team-select-container {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #fff;
            transition: all 0.2s;
        }

        .btn-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }

        .btn-cancel {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-save {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
        }

        .btn-reset {
            width: 100%;
            margin-top: 1rem;
            padding: 0.75rem;
            background: #fff;
            border: 1px solid #fee2e2;
            color: #ef4444;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-reset:hover {
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 1024px) {
            .edit-layout {
                grid-template-columns: 1fr;
            }

            .avatar-card {
                display: flex;
                align-items: center;
                gap: 2rem;
                text-align: left;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Edit User';
            $page_subtitle = 'Manage user profile and account access';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <a href="/settings/users" class="btn-cancel"
                            style="padding:0.5rem 1rem; display:flex; align-items:center; gap:0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Back to Users
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($reset_success_message): ?>
                    <div class="alert alert-success"><?php echo $reset_success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="edit-layout">
                    <!-- Sidebar Column -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <div class="avatar-card">
                            <div class="avatar-large">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <h2 style="font-size:1.25rem; color:var(--text-primary); margin-bottom:0.25rem;">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </h2>
                            <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1rem;">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <span class="user-status-badge status-<?php echo $user['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['status'])); ?>
                            </span>
                        </div>

                        <div class="form-card">
                            <div class="section-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                Security
                            </div>
                            <p style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:1rem;">
                                Send a reset email to the user with a new password.
                            </p>
                            <form method="POST"
                                onsubmit="return confirm('Are you sure you want to reset the password for this user?');">
                                <input type="hidden" name="reset_password" value="1">
                                <button type="submit" class="btn-reset">
                                    Reset Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Form Column -->
                    <div class="form-card">
                        <div class="section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Account Information
                        </div>

                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

                            <div class="form-grid">
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>Full Name <span style="color:red">*</span></label>
                                    <input type="text" name="full_name"
                                        value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>System Role</label>
                                    <select name="role">
                                        <option value="user" <?php echo ($user['role'] == 'user' || empty($user['role'])) ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>
                                            Admin</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Username <span style="color:red">*</span></label>
                                    <input type="text" name="username"
                                        value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Employee Code</label>
                                    <input type="text" name="employee_code"
                                        value="<?php echo htmlspecialchars($user['employee_code'] ?? ''); ?>"
                                        placeholder="e.g. EMP-001">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>Email Address <span style="color:red">*</span></label>
                                    <input type="email" name="email"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>

                            <div class="section-title" style="margin-top:2rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                                Employment Details
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Job Title</label>
                                    <input type="text" name="job_title"
                                        value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>"
                                        placeholder="e.g. Software Engineer">
                                </div>
                                <div class="form-group">
                                    <label>Level</label>
                                    <select name="level">
                                        <?php foreach ($levels as $l): ?>
                                            <option value="<?php echo $l; ?>" <?php echo ($user['level'] == $l) ? 'selected' : ''; ?>>
                                                <?php echo $l; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department_id">
                                        <option value="">-- No Department --</option>
                                        <?php foreach ($depts as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo ($user['department_id'] == $d['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($user['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="resigned" <?php echo ($user['status'] == 'resigned') ? 'selected' : ''; ?>>Resigned</option>
                                        <option value="on_leave" <?php echo ($user['status'] == 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Join Date</label>
                                    <input type="date" name="join_date" value="<?php echo $user['join_date']; ?>">
                                </div>
                            </div>

                            <div class="section-title" style="margin-top:2rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                </svg>
                                Permissions & Teams
                            </div>

                            <div class="permissions-box">
                                <div class="toggle-group">
                                    <div class="toggle-item">
                                        <label for="is_am_bd">Is AM/BD Member</label>
                                        <input type="checkbox" name="is_am_bd" id="is_am_bd" <?php echo $user['is_am_bd'] ? 'checked' : ''; ?> onchange="toggleTeamSelect()">
                                    </div>
                                    <div class="toggle-item">
                                        <label for="can_view_invoice">Can View Invoices</label>
                                        <input type="checkbox" name="can_view_invoice" id="can_view_invoice" <?php echo $user['can_view_invoice'] ? 'checked' : ''; ?>>
                                    </div>
                                    <div class="toggle-item">
                                        <label for="can_view_all_debts">Can View All Debts</label>
                                        <input type="checkbox" name="can_view_all_debts" id="can_view_all_debts" <?php echo $user['can_view_all_debts'] ? 'checked' : ''; ?>>
                                    </div>
                                </div>

                                <div id="team_select_row" class="team-select-container"
                                    style="display: <?php echo $user['is_am_bd'] ? 'block' : 'none'; ?>;">
                                    <div class="form-group" style="margin-bottom:1rem">
                                        <label
                                            style="font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">Assigned
                                            Sale Teams</label>
                                        <select name="team_ids[]" id="team_ids" multiple
                                            style="height: 150px; border-radius: 8px;">
                                            <?php foreach ($sale_teams as $st): ?>
                                                <option value="<?php echo $st['id']; ?>" <?php echo in_array($st['id'], $user_teams) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($st['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small
                                            style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">Hold
                                            Command (Mac) or Control (Windows) to select multiple teams.</small>
                                    </div>

                                    <div class="form-group">
                                        <label
                                            style="font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                            📊 Sale Level (KPI Level)
                                        </label>
                                        <select name="sale_level_id" id="sale_level_id"
                                            style="border-radius: 8px; width:100%; padding:0.6rem; border:1px solid #D1D5DB; font-size:13px; color:#374151;">
                                            <option value="">-- Chưa chọn level --</option>
                                            <?php if (!empty($sale_levels_grouped)): ?>
                                                <?php foreach ($sale_levels_grouped as $pos_type => $pos_levels): ?>
                                                    <optgroup label="<?php echo htmlspecialchars($pos_type) ?>">
                                                        <?php foreach ($pos_levels as $sl): ?>
                                                            <option value="<?php echo $sl['id'] ?>" <?php echo ($user['sale_level_id'] == $sl['id']) ? 'selected' : '' ?>>
                                                                <?php echo htmlspecialchars($sl['level_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>

                                        <div id="level_effective_div"
                                            style="display: <?php echo !empty($user['sale_level_id']) ? 'flex' : 'none'; ?>; align-items:center; gap: 1rem; margin-top: 1rem;">
                                            <div style="flex: 1;">
                                                <label
                                                    style="font-size: 13px; color: var(--text-secondary); margin-bottom: 0.25rem;">Hiệu
                                                    lực từ Quý:</label>
                                                <select name="apply_quarter"
                                                    style="border-radius: 8px; width:100%; padding:0.6rem; border:1px solid #D1D5DB; font-size:13px; color:#374151;">
                                                    <?php
                                                    $cur_q = ceil(date('n') / 3);
                                                    for ($q = 1; $q <= 4; $q++): ?>
                                                        <option value="<?= $q ?>" <?= ($cur_q == $q) ? 'selected' : '' ?>>Quý
                                                            <?= $q ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div style="flex: 1;">
                                                <label
                                                    style="font-size: 13px; color: var(--text-secondary); margin-bottom: 0.25rem;">Hiệu
                                                    lực từ Năm:</label>
                                                <select name="apply_year"
                                                    style="border-radius: 8px; width:100%; padding:0.6rem; border:1px solid #D1D5DB; font-size:13px; color:#374151;">
                                                    <?php
                                                    $cur_y = date('Y');
                                                    for ($y = $cur_y - 1; $y <= $cur_y + 2; $y++): ?>
                                                        <option value="<?= $y ?>" <?= ($cur_y == $y) ? 'selected' : '' ?>>
                                                            <?= $y ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <?php if (empty($sale_levels_flat)): ?>
                                            <small style="color:#EF4444; margin-top:0.5rem; display:block;">
                                                ⚠️ Chưa có Sale Level nào. Vui lòng vào
                                                <a href="/settings/sale-levels" target="_blank"
                                                    style="color:#1D4ED8">Settings → Sale Level Setup</a>
                                                để khởi tạo dữ liệu.
                                            </small>
                                        <?php else: ?>
                                            <small
                                                style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">Chọn
                                                level KPI phù hợp với vị trí của thành viên này.</small>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($level_history)): ?>
                                        <div class="user-level-history" style="margin-top: 2rem;">
                                            <label
                                                style="font-weight: 600; color: var(--text-secondary); margin-bottom: 0.75rem; display:block;">
                                                📜 LỊCH SỬ THAY ĐỔI LEVEL (KPI)
                                            </label>
                                            <table
                                                style="width:100%; border-collapse: collapse; font-size: 13px; border: 1px solid #E5E7EB; border-radius: 8px; overflow: hidden;">
                                                <thead>
                                                    <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                                                        <th style="padding: 10px; text-align: left;">Thời điểm</th>
                                                        <th style="padding: 10px; text-align: left;">Level đã set</th>
                                                        <th style="padding: 10px; text-align: center;">Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($level_history as $h): ?>
                                                        <tr style="border-bottom: 1px solid #F3F4F6;">
                                                            <td style="padding: 10px;">Quý
                                                                <?= $h['apply_quarter'] ?> /
                                                                <?= $h['apply_year'] ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <span style="font-weight: 600; color: #1D4ED8;">
                                                                    <?= htmlspecialchars($h['level_name']) ?>
                                                                </span>
                                                                <div style="font-size: 11px; color: #6B7280; font-weight: 400;">
                                                                    <?= htmlspecialchars($h['position_type']) ?>
                                                                </div>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <form method="POST"
                                                                    onsubmit="return confirm('Bạn có chắc muốn xóa lịch sử level này?')"
                                                                    style="display: inline;">
                                                                    <input type="hidden" name="delete_history" value="1">
                                                                    <input type="hidden" name="history_id"
                                                                        value="<?= $h['id'] ?>">
                                                                    <button type="submit"
                                                                        style="background: none; border: none; color: #EF4444; cursor: pointer; padding: 4px;"
                                                                        title="Xóa lịch sử">
                                                                        <svg width="16" height="16" viewBox="0 0 24 24"
                                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                                            <path
                                                                                d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                                            </path>
                                                                        </svg>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="btn-actions">
                                <a href="/settings/users" class="btn-cancel">Cancel</a>
                                <button type="submit" class="btn-save">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        function toggleTeamSelect() {
            const isAmBd = document.getElementById('is_am_bd').checked;
            document.getElementById('team_select_row').style.display = isAmBd ? 'block' : 'none';
            // Reset sale level if unchecking
            if (!isAmBd) {
                document.getElementById('sale_level_id').value = '';
                document.getElementById('level_effective_div').style.display = 'none';
            }
        }

        document.getElementById('sale_level_id').addEventListener('change', function () {
            if (this.value) {
                document.getElementById('level_effective_div').style.display = 'flex';
            } else {
                document.getElementById('level_effective_div').style.display = 'none';
            }
        });
    </script>
</body>

</html>