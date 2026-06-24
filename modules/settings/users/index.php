<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config/config.php';

// AJAX: Get history
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_level_history') {
    $uid = intval($_GET['user_id']);
    $res = $conn->query("
        SELECT h.*, sl.level_name, sl.position_type 
        FROM user_sale_level_history h
        LEFT JOIN sale_levels sl ON h.sale_level_id = sl.id
        WHERE h.user_id = $uid
        ORDER BY apply_year DESC, apply_quarter DESC
    ");
    $list = [];
    while ($r = $res->fetch_assoc())
        $list[] = $r;
    header('Content-Type: application/json');
    echo json_encode($list);
    exit;
}

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$current_user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;
$error_message = '';
$success_message = '';

// --- AUTO MIGRATE USERS TABLE ---
// Add essential columns for IT/Tech Company Staff Management
$alter_queries = [
    "ADD COLUMN IF NOT EXISTS job_title VARCHAR(100) DEFAULT NULL",
    "ADD COLUMN IF NOT EXISTS level VARCHAR(50) DEFAULT 'Member'", // Intern, Fresher, Junior, Middle, Senior, Lead, Principal, CTO
    "ADD COLUMN IF NOT EXISTS department_id INT DEFAULT NULL",
    "ADD COLUMN IF NOT EXISTS employee_code VARCHAR(20) DEFAULT NULL UNIQUE",
    "ADD COLUMN IF NOT EXISTS skills TEXT DEFAULT NULL", // Comma separated tags
    "ADD COLUMN IF NOT EXISTS join_date DATE DEFAULT NULL",
    "ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'resigned', 'on_leave') DEFAULT 'active'",
    "ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL",
    "ADD COLUMN IF NOT EXISTS can_view_invoice TINYINT(1) DEFAULT 0",
    "ADD COLUMN IF NOT EXISTS can_view_all_debts TINYINT(1) DEFAULT 0"
];

// Columns check helper
function addColumnIfNotExists($conn, $table, $column, $definition)
{
    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

addColumnIfNotExists($conn, 'users', 'job_title', "VARCHAR(100) DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'level', "VARCHAR(50) DEFAULT 'Member'");
addColumnIfNotExists($conn, 'users', 'department_id', "INT DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'employee_code', "VARCHAR(20) DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'skills', "TEXT DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'join_date', "DATE DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'status', "ENUM('active', 'inactive', 'resigned', 'on_leave') DEFAULT 'active'");
addColumnIfNotExists($conn, 'users', 'phone', "VARCHAR(20) DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'can_view_invoice', "TINYINT(1) DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'can_view_all_debts', "TINYINT(1) DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'can_view_odoo_logs', "TINYINT(1) DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'is_am_bd', "TINYINT(1) DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'is_marketer', "TINYINT(1) DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'can_view_all_kpi', "TINYINT(1) DEFAULT 0");
addColumnIfNotExists($conn, 'users', 'viewable_department_ids', "TEXT DEFAULT NULL");
addColumnIfNotExists($conn, 'users', 'avatar', "VARCHAR(255) DEFAULT NULL"); // Ensure avatar exists

// --- USER SALE LEVEL HISTORY TABLE ---
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

// --- USER SALE TEAMS TABLE ---
$conn->query("CREATE TABLE IF NOT EXISTS user_sale_teams (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES sale_teams(id) ON DELETE CASCADE
)");

// Add FK to Department (Check first)
$check_fk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'users' AND
CONSTRAINT_NAME = 'fk_user_dept'");
if ($check_fk->num_rows == 0) {
    // Check if departments table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'departments'");
    if ($check_table->num_rows > 0) {
        $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON
DELETE SET NULL");
    }
}

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // ADD USER
        if ($_POST['action'] === 'add') {
            $email = trim($_POST['email']);
            $name = trim($_POST['full_name']);
            $emp_code = trim($_POST['employee_code']);
            $job = trim($_POST['job_title']);
            $level = $_POST['level'];
            $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : NULL;
            $status = $_POST['status'];
            $join_date = !empty($_POST['join_date']) ? $_POST['join_date'] : NULL;
            $can_view_invoice = isset($_POST['can_view_invoice']) ? 1 : 0;
            $can_view_all_debts = isset($_POST['can_view_all_debts']) ? 1 : 0;
            $can_view_all_kpi = isset($_POST['can_view_all_kpi']) ? 1 : 0;
            $can_view_odoo_logs = isset($_POST['can_view_odoo_logs']) ? 1 : 0;
            $viewable_dept_ids = isset($_POST['viewable_dept_ids']) ? implode(',', $_POST['viewable_dept_ids']) : '';
            $is_am_bd = isset($_POST['is_am_bd']) ? 1 : 0;
            $is_marketer = isset($_POST['is_marketer']) ? 1 : 0;
            $team_ids = isset($_POST['team_ids']) ? $_POST['team_ids'] : [];
            $role_val = $_POST['role'] ?? 'user';
            $sale_level_id = ($is_am_bd && !empty($_POST['sale_level_id'])) ? intval($_POST['sale_level_id']) : null;
            $apply_quarter = !empty($_POST['apply_quarter']) ? intval($_POST['apply_quarter']) : null;
            $apply_year = !empty($_POST['apply_year']) ? intval($_POST['apply_year']) : null;
            $username = trim($_POST['username']);
            // Auto-generate username from email if empty
            if (empty($username) && !empty($email)) {
                $parts = explode('@', $email);
                $username = $parts[0];
            }

            // Default password for new user: '123456' (Should be changed later)
            $password = password_hash('123456', PASSWORD_DEFAULT);

            if (!empty($email) && !empty($name) && !empty($username)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO users (username, email, full_name, password, employee_code, job_title, level, department_id, status, join_date, can_view_invoice, can_view_all_debts, can_view_all_kpi, can_view_odoo_logs, viewable_department_ids, is_am_bd, is_marketer, role, sale_level_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param(
                        "sssssssissiiiisiisi",
                        $username,
                        $email,
                        $name,
                        $password,
                        $emp_code,
                        $job,
                        $level,
                        $dept_id,
                        $status,
                        $join_date,
                        $can_view_invoice,
                        $can_view_all_debts,
                        $can_view_all_kpi,
                        $can_view_odoo_logs,
                        $viewable_dept_ids,
                        $is_am_bd,
                        $is_marketer,
                        $role_val,
                        $sale_level_id
                    );
                    $stmt->execute();
                    $new_id = $conn->insert_id;

                    // Handle teams
                    if ($is_am_bd && !empty($team_ids)) {
                        foreach ($team_ids as $tid) {
                            $ts = $conn->prepare("INSERT INTO user_sale_teams (user_id, team_id) VALUES (?, ?)");
                            $ts->bind_param("ii", $new_id, $tid);
                            $ts->execute();
                        }
                    }

                    // Insert History
                    if ($sale_level_id && $apply_quarter && $apply_year) {
                        $hq = "INSERT INTO user_sale_level_history (user_id, sale_level_id, apply_quarter, apply_year) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE sale_level_id = ?";
                        $hst = $conn->prepare($hq);
                        $hst->bind_param("iiiii", $new_id, $sale_level_id, $apply_quarter, $apply_year, $sale_level_id);
                        $hst->execute();
                    }

                    $success_message = "Member added successfully!";
                } catch (Exception $e) {
                    if ($conn->errno == 1062) { // Duplicate entry
                        $error_message = "Username, Email or Employee Code already exists.";
                    } else {
                        $error_message = "Error: " . $e->getMessage();
                    }
                }
            } else {
                $error_message = "Username, Name and Email are required.";
            }
        }
        // EDIT USER
        elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $email = trim($_POST['email']);
            $name = trim($_POST['full_name']);
            $emp_code = trim($_POST['employee_code']);
            $job = trim($_POST['job_title']);
            $level = $_POST['level'];
            $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : NULL;
            $status = $_POST['status'];
            $join_date = !empty($_POST['join_date']) ? $_POST['join_date'] : NULL;
            $can_view_invoice = isset($_POST['can_view_invoice']) ? 1 : 0;
            $can_view_all_debts = isset($_POST['can_view_all_debts']) ? 1 : 0;
            $can_view_all_kpi = isset($_POST['can_view_all_kpi']) ? 1 : 0;
            $can_view_odoo_logs = isset($_POST['can_view_odoo_logs']) ? 1 : 0;
            $viewable_dept_ids = isset($_POST['viewable_dept_ids']) ? implode(',', $_POST['viewable_dept_ids']) : '';
            $is_am_bd = isset($_POST['is_am_bd']) ? 1 : 0;
            $is_marketer = isset($_POST['is_marketer']) ? 1 : 0;
            $team_ids = isset($_POST['team_ids']) ? $_POST['team_ids'] : [];
            $role_val = $_POST['role'] ?? 'user';
            $sale_level_id = ($is_am_bd && !empty($_POST['sale_level_id'])) ? intval($_POST['sale_level_id']) : null;
            $apply_quarter = !empty($_POST['apply_quarter']) ? intval($_POST['apply_quarter']) : null;
            $apply_year = !empty($_POST['apply_year']) ? intval($_POST['apply_year']) : null;

            $username = trim($_POST['username']);
            if (empty($username) && !empty($email)) {
                $parts = explode('@', $email);
                $username = $parts[0];
            }

            if ($id > 0 && !empty($email)) {
                try {
                    $sql = "UPDATE users SET username=?, email=?, full_name=?, employee_code=?, job_title=?, level=?, department_id=?, status=?, join_date=?, can_view_invoice=?, can_view_all_debts=?, can_view_all_kpi=?, can_view_odoo_logs=?, viewable_department_ids=?, is_am_bd=?, is_marketer=?, role=?, sale_level_id=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssissiiiisiisii", $username, $email, $name, $emp_code, $job, $level, $dept_id, $status, $join_date, $can_view_invoice, $can_view_all_debts, $can_view_all_kpi, $can_view_odoo_logs, $viewable_dept_ids, $is_am_bd, $is_marketer, $role_val, $sale_level_id, $id);
                    ;
                    $stmt->execute();

                    // Update Teams
                    $conn->query("DELETE FROM user_sale_teams WHERE user_id = $id");
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

                    $success_message = "Member updated!";
                } catch (Exception $e) {
                    if ($conn->errno == 1062) {
                        $error_message = "Email or Employee Code already exists.";
                    } else {
                        $error_message = "Error: " . $e->getMessage();
                    }
                }
            }
        }
        // DELETE HISTORY
        elseif ($_POST['action'] === 'delete_history') {
            $h_id = intval($_POST['history_id']);
            $conn->query("DELETE FROM user_sale_level_history WHERE id = $h_id");
            $success_message = "Xóa lịch sử level thành công!";
        }
        // SET PASSWORD
        elseif ($_POST['action'] === 'set_password') {
            $id = intval($_POST['id']);
            $new_pass = trim($_POST['new_password']);
            $should_send_email = isset($_POST['send_email']) ? 1 : 0;

            if ($id > 0 && !empty($new_pass)) {
                try {
                    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_pass, $id);
                    if ($stmt->execute()) {
                        $success_message = "Password updated successfully.";

                        if ($should_send_email) {
                            // Fetch user info for email
                            $u_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
                            $u_stmt->bind_param("i", $id);
                            $u_stmt->execute();
                            $user_to_mail = $u_stmt->get_result()->fetch_assoc();

                            if ($user_to_mail) {
                                // Gửi qua hệ thống Email Senders (sender mặc định, fallback SMTP cũ).
                                require_once __DIR__ . '/../../../includes/Mailer.php';
                                $body = "
                                        <h2>Password Update Notification</h2>
                                        <p>Hello <b>" . htmlspecialchars($user_to_mail['full_name']) . "</b>,</p>
                                        <p>Your password has been updated by the administrator.</p>
                                        <p>New Password: <strong style='font-size:16px; background:#eee; padding:5px 10px; border-radius:4px;'>$new_pass</strong></p>
                                        <p>Please change your password immediately after logging in.</p>
                                        <br>
                                        <p>Regards,<br>AHT System</p>
                                    ";
                                $sent = Mailer::send($conn, $user_to_mail['email'], 'Your Account Password has been updated', $body);
                                if ($sent) {
                                    $success_message .= " (Email sent successfully)";
                                } else {
                                    $error_message = "Email could not be sent. Hãy cấu hình Email Senders tại /settings/smtp.";
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
            }
        }
        // DELETE USER
    }
}

// --- FETCH DATA ---
$users = [];
$sql = "SELECT u.*, d.name as department_name,
        sl.level_name as sale_level_name, sl.position_type as sale_position_type, sl.color_badge as sale_color_badge,
        (SELECT GROUP_CONCAT(st.name) FROM user_sale_teams ust JOIN sale_teams st ON ust.team_id = st.id WHERE ust.user_id = u.id) as team_names,
        (SELECT GROUP_CONCAT(team_id) FROM user_sale_teams WHERE user_id = u.id) as team_ids
FROM users u
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN sale_levels sl ON u.sale_level_id = sl.id
ORDER BY u.id DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Levels Constant
$levels = ['Member', 'Leader', 'Manager', 'Director' , 'C-Level'];

// Fetch Sale Teams for Dropdown
$sale_teams = [];
$st_res = $conn->query("SELECT id, name FROM sale_teams ORDER BY order_num ASC, name ASC");
if ($st_res) {
    while ($r = $st_res->fetch_assoc()) {
        $sale_teams[] = $r;
    }
}

// Fetch Departments for Dropdown
$depts = [];
$d_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($d_res) {
    while ($r = $d_res->fetch_assoc()) {
        $depts[] = $r;
    }
}

// Migrate sale_level_id in users table
addColumnIfNotExists($conn, 'users', 'sale_level_id', 'INT DEFAULT NULL');

// Fetch Sale Levels for Dropdown
$sale_levels_grouped = [];
$sl_tbl = $conn->query("SHOW TABLES LIKE 'sale_levels'");
if ($sl_tbl && $sl_tbl->num_rows > 0) {
    $pt_chk = $conn->query("SHOW COLUMNS FROM sale_levels LIKE 'position_type'");
    if ($pt_chk && $pt_chk->num_rows == 0) {
        $conn->query("ALTER TABLE sale_levels ADD COLUMN position_type VARCHAR(100) NOT NULL DEFAULT 'BDE/BCE'");
    }
    $on_chk = $conn->query("SHOW COLUMNS FROM sale_levels LIKE 'order_num'");
    if ($on_chk && $on_chk->num_rows == 0) {
        $conn->query("ALTER TABLE sale_levels ADD COLUMN order_num INT DEFAULT 0");
    }
    $sl_res = $conn->query("SELECT id, position_type, level_name FROM sale_levels ORDER BY position_type, order_num, id");
    if ($sl_res) {
        while ($r = $sl_res->fetch_assoc()) {
            $sale_levels_grouped[$r['position_type']][] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Settings</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        /* Shared Styles from Departments Module + Custom */
        .content-wrapper {
            padding: 1rem;
            height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }

        .sheet-toolbar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dadce0;
            border-bottom: none;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .btn-toolbar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            color: #3c4043;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-toolbar:hover {
            background: #f1f3f4;
            color: #202124;
        }

        .btn-primary-toolbar {
            background: #1a73e8;
            color: white;
            border-color: #1a73e8;
        }

        .sheet-container {
            flex: 1;
            overflow: auto;
            background: white;
            border: 1px solid #dadce0;
            position: relative;
        }

        .sheet-table {
            border-collapse: collapse;
            width: 100%;
            font-family: 'Roboto', arial, sans-serif;
            font-size: 13px;
            color: #202124;
            min-width: 1200px;
        }

        .sheet-table th,
        .sheet-table td {
            border: 1px solid #e0e0e0;
            padding: 6px 12px;
            height: 40px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .sheet-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #3c4043;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #dadce0;
        }

        .sheet-table tr:hover td {
            background-color: #e8f0fe !important;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-dept {
            background: #e8f0fe;
            color: #1967d2;
            border: 1px solid #d2e3fc;
            border-radius: 12px;
        }

        .badge-level-intern {
            background: #f1f3f4;
            color: #5f6368;
        }

        .badge-level-junior {
            background: #e6f4ea;
            color: #137333;
        }

        .badge-level-middle {
            background: #e8f0fe;
            color: #1967d2;
        }

        .badge-level-senior {
            background: #fce8e6;
            color: #c5221f;
        }

        .badge-level-lead {
            background: #f3e8fd;
            color: #9334e6;
        }

        .badge-status-active {
            background: #e6f4ea;
            color: #1e8e3e;
            border: 1px solid #ceead6;
        }

        .badge-status-inactive {
            background: #fce8e6;
            color: #c5221f;
            border: 1px solid #fad2cf;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-team {
            background: #e8f0fe;
            color: #1967d2;
            border: 1px solid #d2e3fc;
            font-size: 11px;
            padding: 2px 8px;
            margin: 2px;
            display: inline-block;
        }

        .member-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            color: #5f6368;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
            color: #3c4043;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #dadce0;
            border-radius: 5px;
            font-size: 13px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }

        .modal-section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5f6368;
            margin: 24px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #eee;
        }

        .permissions-box {
            background: #f8f9fa;
            border: 1px solid #dadce0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 8px;
        }

        .toggle-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toggle-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 0;
        }

        .toggle-item label {
            margin-bottom: 0;
            font-weight: 500;
            cursor: pointer;
        }

        .toggle-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .team-select-container {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #dadce0;
        }

        .modal-footer {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-save {
            padding: 10px 24px;
            background: #1a73e8;
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            box-shadow: 0 4px 10px rgba(26, 115, 232, 0.2);
        }

        .btn-save:hover {
            background: #1557b0;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(26, 115, 232, 0.3);
        }

        .btn-cancel {
            padding: 10px 24px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 6px;
            color: #5f6368;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #f8f9fa;
            color: #202124;
            border-color: #bdc1c6;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'User Management';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <?php if ($success_message): ?>
                    <div
                        style="background: #e6f4ea; color: #1e8e3e; padding: 10px; border-radius: 4px; margin-bottom: 10px; font-size: 13px; border: 1px solid #ceead6;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div
                        style="background: #fce8e6; color: #c5221f; padding: 10px; border-radius: 4px; margin-bottom: 10px; font-size: 13px; border: 1px solid #fad2cf;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="sheet-toolbar">
                    <button class="btn-toolbar btn-primary-toolbar" onclick="openAddModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add User
                    </button>
                    <div style="flex:1"></div>
                    <input type="text" id="searchInput" placeholder="Search member, email, position..."
                        style="padding: 6px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 13px; width: 250px;"
                        onkeyup="filterTable()">
                </div>

                <div class="sheet-container">
                    <table class="sheet-table" id="userTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">ID</th>
                                <th style="width: 80px;">Code</th>
                                <th style="width: 20%;">User Profile</th>
                                <th style="width: 13%;">Position</th>
                                <th style="width: 8%;">Level</th>
                                <th style="width: 12%;">Department</th>
                                <th style="width: 12%;">Sale Teams</th>
                                <th style="width: 14%;">Sale Level</th>
                                <th style="width: 8%;">Status</th>
                                <th style="width: 8%;">Joined</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u):
                                $level_class = 'badge-level-junior';
                                if (strpos(strtolower($u['level']), 'senior') !== false)
                                    $level_class = 'badge-level-senior';
                                elseif (strpos(strtolower($u['level']), 'lead') !== false)
                                    $level_class = 'badge-level-lead';
                                elseif (strpos(strtolower($u['level']), 'middle') !== false)
                                    $level_class = 'badge-level-middle';
                                elseif (strpos(strtolower($u['level']), 'intern') !== false)
                                    $level_class = 'badge-level-intern';
                                ?>
                                <tr>
                                    <td>
                                        <?php echo $u['id']; ?>
                                    </td>
                                    <td style="font-family: monospace; color: #5f6368;">
                                        <?php echo htmlspecialchars($u['employee_code'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <div class="member-info">
                                            <?php if ($u['avatar']): ?>
                                                <img src="<?php echo htmlspecialchars($u['avatar']); ?>" class="member-avatar">
                                            <?php else: ?>
                                                <div class="member-avatar">
                                                    <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 500; color: #202124;">
                                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                                </div>
                                                <div style="font-size: 11px; color: #5f6368;">
                                                    <?php echo htmlspecialchars($u['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($u['job_title'] ?? 'N/A'); ?>
                                    </td>
                                    <td><span class="badge <?php echo $level_class; ?>">
                                            <?php echo htmlspecialchars($u['level']); ?>
                                        </span></td>
                                    <td>
                                        <?php if ($u['department_name']): ?>
                                            <span class="badge badge-dept">
                                                <?php echo htmlspecialchars($u['department_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['team_names']): ?>
                                            <?php
                                            $teams = explode(',', $u['team_names']);
                                            foreach ($teams as $team): ?>
                                                <span class="badge badge-team"><?php echo htmlspecialchars(trim($team)); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #9aa0a6; font-size: 11px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['sale_level_name'])): ?>
                                            <span style="
                                                display:inline-block; padding:2px 8px;
                                                border-radius:10px; font-size:11px; font-weight:600;
                                                background:<?= htmlspecialchars($u['sale_color_badge'] ?? '#1D4ED8') ?>22;
                                                color:<?= htmlspecialchars($u['sale_color_badge'] ?? '#1D4ED8') ?>;
                                                border:1px solid <?= htmlspecialchars($u['sale_color_badge'] ?? '#1D4ED8') ?>44;
                                                white-space:nowrap;
                                            ">
                                                <?= htmlspecialchars($u['sale_level_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#9aa0a6; font-size:11px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php echo $u['status'] == 'active' ? 'badge-status-active' : 'badge-status-inactive'; ?>">
                                            <?php echo ucfirst($u['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $u['join_date'] ? date('M d, Y', strtotime($u['join_date'])) : '-'; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="#" onclick='openEditModal(<?php echo json_encode($u); ?>); return false;'
                                            style="display:inline-flex; border:none; background:none; cursor:pointer; color:#1a73e8; text-decoration:none;"
                                            title="Edit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                        <a href="#"
                                            onclick='openPasswordModal(<?php echo $u['id']; ?>, "<?php echo addslashes($u['full_name']); ?>"); return false;'
                                            style="display:inline-flex; border:none; background:none; cursor:pointer; color:#f4b400; text-decoration:none; margin-left:8px;"
                                            title="Set Password">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </a>
                                        <?php if (!empty($u['can_view_invoice'])): ?>
                                            <span title="Can View Invoices" style="margin-left: 5px; color: #1e8e3e;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14 2 14 8 20 8"></polyline>
                                                    <line x1="12" y1="18" x2="12" y2="12"></line>
                                                    <line x1="9" y1="15" x2="15" y2="15"></line>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle" style="margin-top:0; margin-bottom: 20px;">Add User</h2>
            <form method="POST" id="userForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="userId" value="">

                <div class="modal-section-title">General Information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span style="color:red">*</span></label>
                        <input type="text" name="full_name" id="full_name" required placeholder="Enter full name">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="hr">HR (chỉ HRM + Tài liệu)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username <span style="color:red">*</span></label>
                        <input type="text" name="username" id="username" required placeholder="admin">
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:red">*</span></label>
                        <input type="email" name="email" id="email" required placeholder="user@example.com">
                    </div>
                </div>

                <div class="modal-section-title">Job Details</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee Code</label>
                        <input type="text" name="employee_code" id="employee_code" placeholder="E.g. AHT-001">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="resigned">Resigned</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Job Title (Position)</label>
                        <input type="text" name="job_title" id="job_title" placeholder="e.g. Backend Developer">
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level" id="level">
                            <?php foreach ($levels as $l): ?>
                                <option value="<?php echo $l; ?>">
                                    <?php echo $l; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" id="department_id">
                            <option value="">-- None --</option>
                            <?php foreach ($depts as $d): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Join Date</label>
                        <input type="date" name="join_date" id="join_date">
                    </div>
                </div>

                <div class="modal-section-title">Permissions & Teams</div>
                <div class="permissions-box">
                    <div class="toggle-group">
                        <div class="toggle-item">
                            <label for="can_view_invoice" style="font-size: 13px; color: #3c4043;">Allow View
                                Invoices</label>
                            <input type="checkbox" name="can_view_invoice" id="can_view_invoice">
                        </div>
                        <div class="toggle-item">
                            <label for="can_view_all_debts" style="font-size: 13px; color: #3c4043;">Can View All
                                Debts</label>
                            <input type="checkbox" name="can_view_all_debts" id="can_view_all_debts">
                        </div>
                        <div class="toggle-item">
                            <label for="can_view_all_kpi" style="font-size: 13px; color: #3c4043;">Can View All
                                KPIs (Full access)</label>
                            <input type="checkbox" name="can_view_all_kpi" id="can_view_all_kpi">
                        </div>
                        <div class="toggle-item">
                            <label for="can_view_odoo_logs" style="font-size: 13px; color: #3c4043;">Can View Odoo Webhook Logs</label>
                            <input type="checkbox" name="can_view_odoo_logs" id="can_view_odoo_logs">
                        </div>

                        <div id="viewable_depts_row" class="team-select-container" style="border-top:none; margin-top:5px; padding-top:0;">
                            <label style="display:block; margin-bottom: 8px; color: #3c4043; font-size: 13px; font-weight: 500;">Can View Specific Teams</label>
                            <select name="viewable_dept_ids[]" id="viewable_dept_ids" multiple placeholder="Select Teams...">
                                <?php foreach ($depts as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="toggle-item">
                            <label for="is_am_bd" style="font-size: 13px; color: #3c4043;">Is AM/BD Member</label>
                            <input type="checkbox" name="is_am_bd" id="is_am_bd" onchange="toggleTeamSelect()">
                        </div>
                        <div class="toggle-item">
                            <label for="is_marketer" style="font-size: 13px; color: #3c4043;">Is Marketer</label>
                            <input type="checkbox" name="is_marketer" id="is_marketer">
                        </div>
                    </div>

                    <div id="team_select_row" class="team-select-container" style="display:none;">
                        <div class="form-group" style="margin-bottom:12px">
                            <label style="margin-bottom: 8px; color: #5f6368; font-weight: 600;">Assign Sale
                                Teams</label>
                            <select name="team_ids[]" id="team_ids" multiple placeholder="Select Sale Teams...">
                                <?php foreach ($sale_teams as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="margin-bottom: 8px; color: #5f6368; font-weight: 600;">📊 Sale Level (KPI
                                Level)</label>
                            <select name="sale_level_id" id="sale_level_id"
                                style="width:100%; border-radius:6px; padding:8px 10px; border:1px solid #dadce0; font-size:13px; color:#202124;">
                                <option value="">-- Chưa chọn level --</option>
                                <?php if (!empty($sale_levels_grouped)): ?>
                                    <?php foreach ($sale_levels_grouped as $pos_type => $pos_levels): ?>
                                        <optgroup label="<?= htmlspecialchars($pos_type) ?>">
                                            <?php foreach ($pos_levels as $sl): ?>
                                                <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['level_name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option disabled>⚠️ Chưa có data — vào Settings > Sale Level Setup</option>
                                <?php endif; ?>
                            </select>

                            <div id="level_effective_div"
                                style="display: none; align-items:center; gap: 8px; margin-top: 10px;">
                                <div style="flex: 1;">
                                    <label style="font-size: 11px; color: #5f6368; margin-bottom: 4px;">Hiệu lực từ
                                        Quý:</label>
                                    <select name="apply_quarter"
                                        style="border-radius: 5px; width:100%; padding:6px 10px; border:1px solid #dadce0; font-size:12px; color:#202124;">
                                        <?php
                                        $cur_q = ceil(date('n') / 3);
                                        for ($q = 1; $q <= 4; $q++): ?>
                                            <option value="<?= $q ?>" <?= ($cur_q == $q) ? 'selected' : '' ?>>Quý <?= $q ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-size: 11px; color: #5f6368; margin-bottom: 4px;">Hiệu lực từ
                                        Năm:</label>
                                    <select name="apply_year"
                                        style="border-radius: 5px; width:100%; padding:6px 10px; border:1px solid #dadce0; font-size:12px; color:#202124;">
                                        <?php
                                        $cur_y = date('Y');
                                        for ($y = $cur_y - 1; $y <= $cur_y + 2; $y++): ?>
                                            <option value="<?= $y ?>" <?= ($cur_y == $y) ? 'selected' : '' ?>><?= $y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <small style="color:#70757a; margin-top:6px; display:block;">Chọn level KPI phù hợp với vị
                                <div id="modal_level_history"
                                    style="margin-top: 20px; border-top: 1px dashed #dadce0; padding-top: 15px; display: none;">
                                    <label
                                        style="margin-bottom: 10px; color: #5f6368; font-weight: 600; font-size: 12px; display: block;">📜
                                        LỊCH SỬ LEVEL</label>
                                    <div id="history_list_container"></div>
                                </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save User</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content" style="width: 400px;">
            <h2 style="margin-top:0; margin-bottom: 20px;">Set Password</h2>
            <p id="passwordTargetName" style="font-size: 14px; color: #5f6368; margin-bottom: 20px;"></p>
            <form method="POST">
                <input type="hidden" name="action" value="set_password">
                <input type="hidden" name="id" id="passUserId">

                <div class="form-group">
                    <label>New Password</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="new_password" id="newPasswordInput" required
                            placeholder="Strong password">
                        <button type="button" class="btn-toolbar" onclick="generateStrongPassword()"
                            style="flex-shrink: 0; padding: 0 12px; height: 38px;">
                            Auto
                        </button>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 15px;">
                    <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="send_email" id="sendEmailCheckbox" checked style="width: auto;">
                        <label for="sendEmailCheckbox" style="margin-bottom: 0; cursor: pointer;">Send email to
                            user</label>
                    </div>
                </div>

                <div class="modal-footer" style="margin-top: 30px;">
                    <button type="button" class="btn-cancel" onclick="closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn-save">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const userId = document.getElementById('userId');

        // Fields
        const fullName = document.getElementById('full_name');
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const empCode = document.getElementById('employee_code');
        const jobTitle = document.getElementById('job_title');
        const level = document.getElementById('level');
        const deptId = document.getElementById('department_id');
        const status = document.getElementById('status');
        const joinDate = document.getElementById('join_date');
        const canViewInvoice = document.getElementById('can_view_invoice');
        const canViewAllDebts = document.getElementById('can_view_all_debts');
        const role = document.getElementById('role');

        let tsViewableDepts, tsTeamIds;

        document.addEventListener('DOMContentLoaded', () => {
            tsViewableDepts = new TomSelect('#viewable_dept_ids', {
                plugins: ['remove_button'],
                persist: false,
                create: false,
            });
            tsTeamIds = new TomSelect('#team_ids', {
                plugins: ['remove_button'],
                persist: false,
                create: false,
            });
        });

        function openAddModal() {
            modalTitle.textContent = 'Add User';
            formAction.value = 'add';
            userId.value = '';
            // Reset form
            fullName.value = '';
            username.value = '';
            email.value = '';
            empCode.value = '';
            jobTitle.value = '';
            level.value = 'Junior';
            deptId.value = '';
            status.value = 'active';
            role.value = 'user';
            joinDate.value = '';
            canViewInvoice.checked = false;
            canViewAllDebts.checked = false;
            document.getElementById('can_view_all_kpi').checked = false;
            
            if(tsViewableDepts) tsViewableDepts.clear();
            
            document.getElementById('is_am_bd').checked = false;
            document.getElementById('is_marketer').checked = false;
            if(tsTeamIds) tsTeamIds.clear();

            // Hide history
            document.getElementById('modal_level_history').style.display = 'none';
            toggleTeamSelect();

            modal.classList.add('show');
        }

        function openEditModal(user) {
            modalTitle.textContent = 'Edit User';
            formAction.value = 'edit';
            userId.value = user.id;

            fullName.value = user.full_name;
            username.value = user.username || user.email.split('@')[0]; // Fallback
            email.value = user.email;
            empCode.value = user.employee_code || '';
            jobTitle.value = user.job_title || '';
            level.value = user.level || 'Junior';
            deptId.value = user.department_id || '';
            status.value = user.status || 'active';
            role.value = user.role || 'user';
            joinDate.value = user.join_date || '';
            canViewInvoice.checked = user.can_view_invoice == 1;
            canViewAllDebts.checked = user.can_view_all_debts == 1;
            document.getElementById('can_view_all_kpi').checked = user.can_view_all_kpi == 1;
            document.getElementById('can_view_odoo_logs').checked = user.can_view_odoo_logs == 1;
            
            // Set viewable depts
            if(tsViewableDepts) {
                tsViewableDepts.clear();
                if(user.viewable_department_ids) {
                    tsViewableDepts.setValue(user.viewable_department_ids.split(','));
                }
            }

            document.getElementById('is_am_bd').checked = user.is_am_bd == 1;
            document.getElementById('is_marketer').checked = user.is_marketer == 1;

            // Set teams
            if(tsTeamIds) {
                tsTeamIds.clear();
                if(user.team_ids) {
                    tsTeamIds.setValue(user.team_ids.split(','));
                }
            }
            // Set sale level
            const saleLevelSel = document.getElementById('sale_level_id');
            if (saleLevelSel) {
                saleLevelSel.value = user.sale_level_id || '';
                if (user.sale_level_id) {
                    document.getElementById('level_effective_div').style.display = 'flex';
                } else {
                    document.getElementById('level_effective_div').style.display = 'none';
                }
            }
            // Load history
            loadLevelHistory(user.id);
            toggleTeamSelect();

            modal.classList.add('show');
        }

        async function loadLevelHistory(uid) {
            const container = document.getElementById('history_list_container');
            const historyDiv = document.getElementById('modal_level_history');
            container.innerHTML = '<p style="font-size:11px; color:#666;">Đang tải...</p>';

            try {
                const response = await fetch(`?ajax_action=get_level_history&user_id=${uid}`);
                const data = await response.json();

                if (data.length > 0) {
                    historyDiv.style.display = 'block';
                    let html = '<table style="width:100%; border-collapse: collapse; font-size: 11px; border: 1px solid #eee;">';
                    data.forEach(h => {
                        html += `
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 6px;">Q${h.apply_quarter}/${h.apply_year}</td>
                            <td style="padding: 6px; font-weight: 600; color: #1a73e8;">${h.level_name}</td>
                            <td style="padding: 6px; text-align: right;">
                                <button type="button" onclick="deleteHistory(${h.id}, ${uid})" style="background:none; border:none; color:#d93025; cursor:pointer; padding:0;">Xóa</button>
                            </td>
                        </tr>`;
                    });
                    html += '</table>';
                    container.innerHTML = html;
                } else {
                    historyDiv.style.display = 'none';
                }
            } catch (e) {
                container.innerHTML = '<p style="color:red; font-size:11px;">Lỗi tải dữ liệu</p>';
            }
        }

        function deleteHistory(hid, uid) {
            if (!confirm('Xóa lịch sử level này?')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_history">
                <input type="hidden" name="history_id" value="${hid}">
                <input type="hidden" name="id" value="${uid}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function closeModal() { modal.classList.remove('show'); }
        window.onclick = function (event) { if (event.target == modal) closeModal(); }

        function toggleTeamSelect() {
            const isAmBd = document.getElementById('is_am_bd').checked;
            document.getElementById('team_select_row').style.display = isAmBd ? 'block' : 'none';
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

        function filterTable() {
            var input, filter, table, tr, td, i;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("userTable");
            tr = table.getElementsByTagName("tr");
            for (i = 1; i < tr.length; i++) {
                // Search in Name/Email (col 2), Code (col 1), Position (col 3)
                var tdCode = tr[i].getElementsByTagName("td")[1];
                var tdProfile = tr[i].getElementsByTagName("td")[2];
                var tdPos = tr[i].getElementsByTagName("td")[3];

                if (tdCode || tdProfile || tdPos) {
                    var txtCode = tdCode.textContent || tdCode.innerText;
                    var txtProfile = tdProfile.textContent || tdProfile.innerText;
                    var txtPos = tdPos.textContent || tdPos.innerText;

                    if (txtCode.toUpperCase().indexOf(filter) > -1 ||
                        txtProfile.toUpperCase().indexOf(filter) > -1 ||
                        txtPos.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        const passModal = document.getElementById('passwordModal');
        const passUserId = document.getElementById('passUserId');
        const passTargetName = document.getElementById('passwordTargetName');
        const newPasswordInput = document.getElementById('newPasswordInput');

        function openPasswordModal(id, name) {
            passUserId.value = id;
            passTargetName.textContent = "Setting password for: " + name;
            newPasswordInput.value = "";
            passModal.classList.add('show');
            generateStrongPassword(); // Auto generate initially
        }

        function closePasswordModal() { passModal.classList.remove('show'); }

        function generateStrongPassword() {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let pass = "";
            for (let i = 0; i < 12; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            newPasswordInput.value = pass;
        }
    </script>
</body>

</html>
