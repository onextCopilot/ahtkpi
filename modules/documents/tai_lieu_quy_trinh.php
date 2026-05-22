<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$full_name = $_SESSION['full_name'] ?? '';

// Auto-migration: Create table for HTML files if not exists
$conn->query("CREATE TABLE IF NOT EXISTS quy_trinh_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Check and add allowed_departments column if it doesn't exist
$chk_dept = $conn->query("SHOW COLUMNS FROM quy_trinh_files LIKE 'allowed_departments'");
if ($chk_dept && $chk_dept->num_rows === 0) {
    $conn->query("ALTER TABLE quy_trinh_files ADD COLUMN allowed_departments TEXT DEFAULT NULL");
}

// Check and add allowed_users column if it doesn't exist
$chk_users = $conn->query("SHOW COLUMNS FROM quy_trinh_files LIKE 'allowed_users'");
if ($chk_users && $chk_users->num_rows === 0) {
    $conn->query("ALTER TABLE quy_trinh_files ADD COLUMN allowed_users TEXT DEFAULT NULL");
}

// Check and add allowed_roles column if it doesn't exist
$chk_roles = $conn->query("SHOW COLUMNS FROM quy_trinh_files LIKE 'allowed_roles'");
if ($chk_roles && $chk_roles->num_rows === 0) {
    $conn->query("ALTER TABLE quy_trinh_files ADD COLUMN allowed_roles TEXT DEFAULT NULL");
}

// Handle POST Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['html_file'])) {
    header('Content-Type: application/json');
    
    // Check permissions (Admin/Manager can upload)
    if ($role !== 'admin' && $role !== 'manager') {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền upload tài liệu.']);
        exit();
    }
    
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'Tiêu đề không được để trống.']);
        exit();
    }
    
    $file = $_FILES['html_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi tải file lên: ' . $file['error']]);
        exit();
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['html', 'htm', 'doc', 'docx', 'pdf'];
    if (!in_array($file_ext, $allowed_exts)) {
        echo json_encode(['status' => 'error', 'message' => 'Chỉ cho phép tải lên các file .html, .htm, .doc, .docx hoặc .pdf']);
        exit();
    }
    
    $target_dir = __DIR__ . "/../../public/uploads/quy_trinh/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Sanitize filename
    $safe_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
    $file_name = time() . '_' . $safe_name;
    $target_file = $target_dir . $file_name;
    
    $upload_success = false;
    if (defined('INTEGRATION_TEST') && INTEGRATION_TEST) {
        $upload_success = copy($file['tmp_name'], $target_file);
    } else {
        $upload_success = @move_uploaded_file($file['tmp_name'], $target_file);
    }
    
    if ($upload_success) {
        $file_url = "/public/uploads/quy_trinh/" . $file_name;
        $title_esc = $conn->real_escape_string($title);
        $file_url_esc = $conn->real_escape_string($file_url);
        $file_name_esc = $conn->real_escape_string($file_name);
        $file_size = (int)$file['size'];
        
        $allowed_roles_arr = isset($_POST['roles']) && is_array($_POST['roles']) ? array_filter(array_map(function($r) {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', $r);
        }, $_POST['roles'])) : [];
        $allowed_roles = implode(',', $allowed_roles_arr);
        $allowed_usrs = isset($_POST['users']) && is_array($_POST['users']) ? implode(',', array_map('intval', $_POST['users'])) : '';
        
        $allowed_roles_esc = $conn->real_escape_string($allowed_roles);
        $allowed_usrs_esc = $conn->real_escape_string($allowed_usrs);
        
        $sql = "INSERT INTO quy_trinh_files (title, file_path, file_name, file_size, uploaded_by, allowed_roles, allowed_users) 
                VALUES ('$title_esc', '$file_url_esc', '$file_name_esc', $file_size, $user_id, " . 
                ($allowed_roles === '' ? "NULL" : "'$allowed_roles_esc'") . ", " . 
                ($allowed_usrs === '' ? "NULL" : "'$allowed_usrs_esc'") . ")";
        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu cơ sở dữ liệu: ' . $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không thể lưu trữ file tải lên.']);
    }
    exit();
}

// Handle POST Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    header('Content-Type: application/json');
    
    // Check permissions
    if ($role !== 'admin' && $role !== 'manager') {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền xóa tài liệu.']);
        exit();
    }
    
    $file_id = (int)$_POST['id'];
    
    $res = $conn->query("SELECT file_path FROM quy_trinh_files WHERE id = $file_id");
    if ($res && $row = $res->fetch_assoc()) {
        $full_path = __DIR__ . '/../..' . $row['file_path'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        $conn->query("DELETE FROM quy_trinh_files WHERE id = $file_id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tài liệu trong hệ thống.']);
    }
    exit();
}

// Handle POST get_permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_permissions') {
    header('Content-Type: application/json');
    if ($role !== 'admin' && $role !== 'manager') {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện chức năng này.']);
        exit();
    }
    
    $file_id = (int)($_POST['id'] ?? 0);
    $res = $conn->query("SELECT allowed_roles, allowed_users FROM quy_trinh_files WHERE id = $file_id");
    if ($res && $row = $res->fetch_assoc()) {
        $roles_list = $row['allowed_roles'] ? explode(',', $row['allowed_roles']) : [];
        $users = $row['allowed_users'] ? explode(',', $row['allowed_users']) : [];
        echo json_encode([
            'status' => 'success',
            'roles' => $roles_list,
            'users' => array_map('intval', $users)
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tài liệu.']);
    }
    exit();
}

// Handle POST save_permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    header('Content-Type: application/json');
    if ($role !== 'admin' && $role !== 'manager') {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện chức năng này.']);
        exit();
    }
    
    $file_id = (int)($_POST['id'] ?? 0);
    $allowed_roles_arr = isset($_POST['roles']) && is_array($_POST['roles']) ? array_filter(array_map(function($r) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $r);
    }, $_POST['roles'])) : [];
    $allowed_roles = implode(',', $allowed_roles_arr);
    $allowed_usrs = isset($_POST['users']) && is_array($_POST['users']) ? implode(',', array_map('intval', $_POST['users'])) : '';
    
    $allowed_roles_esc = $conn->real_escape_string($allowed_roles);
    $allowed_usrs_esc = $conn->real_escape_string($allowed_usrs);
    
    $sql = "UPDATE quy_trinh_files 
            SET allowed_roles = " . ($allowed_roles === '' ? "NULL" : "'$allowed_roles_esc'") . ", 
                allowed_users = " . ($allowed_usrs === '' ? "NULL" : "'$allowed_usrs_esc'") . " 
            WHERE id = $file_id";
            
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu phân quyền: ' . $conn->error]);
    }
    exit();
}

// Fetch all HTML documents (filtered by permissions)
$search_q = isset($_GET['search']) ? trim($_GET['search']) : '';
$params = [];
$types = '';

if ($role === 'admin' || $role === 'manager') {
    // Admin and manager can see everything
    $query = "SELECT q.*, u.full_name as uploader 
              FROM quy_trinh_files q 
              LEFT JOIN users u ON q.uploaded_by = u.id";
    if (!empty($search_q)) {
        $query .= " WHERE q.title LIKE ? OR q.file_name LIKE ?";
        $like_val = "%$search_q%";
        $params[] = $like_val;
        $params[] = $like_val;
        $types .= 'ss';
    }
} else {
    // Normal user: can only see public OR allowed roles OR allowed users
    $query = "SELECT q.*, u.full_name as uploader 
              FROM quy_trinh_files q 
              LEFT JOIN users u ON q.uploaded_by = u.id 
              WHERE (
                  ((q.allowed_roles IS NULL OR q.allowed_roles = '') 
                   AND (q.allowed_users IS NULL OR q.allowed_users = ''))
                  OR FIND_IN_SET(?, q.allowed_roles) > 0
                  OR FIND_IN_SET(?, q.allowed_users) > 0
              )";
    $params[] = $role;
    $params[] = $user_id;
    $types .= 'si';
    
    if (!empty($search_q)) {
        $query .= " AND (q.title LIKE ? OR q.file_name LIKE ?)";
        $like_val = "%$search_q%";
        $params[] = $like_val;
        $params[] = $like_val;
        $types .= 'ss';
    }
}
$query .= " ORDER BY q.created_at DESC";

$stmt = $conn->prepare($query);
$files = [];
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $files[] = $row;
        }
    }
}

// Fetch roles and users list for permission management
$roles_list_avail = [
    'admin' => 'Quản trị viên (Admin)',
    'manager' => 'Quản lý (Manager)',
    'user' => 'Nhân viên (User)'
];
$users_list = [];
if ($role === 'admin' || $role === 'manager') {
    $users_res = $conn->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC");
    if ($users_res) {
        while ($row = $users_res->fetch_assoc()) {
            $users_list[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tài Liệu - Quy Trình - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Mammoth.js for client-side Word (.docx) file conversion/rendering -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-green: #34C759;
            --apple-red: #FF3B30;
            --apple-bg: #F5F5F7;
            --apple-card-bg: #FFFFFF;
            --apple-slate: #1E293B;
            --apple-gray: #64748B;
            --radius-xl: 20px;
            --radius-lg: 14px;
        }

        body { background-color: var(--apple-bg); font-family: 'Inter', sans-serif; }
        .main-content { flex: 1; padding: 32px; background: var(--apple-bg); min-height: 100vh; }
        
        .layout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            align-items: start;
        }

        .premium-card {
            background: var(--apple-card-bg);
            border-radius: var(--radius-xl);
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }

        .upload-form-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--apple-slate);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-slate);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 10px 14px;
            border-radius: var(--radius-lg);
            border: 1px solid #E2E8F0;
            font-size: 14px;
            font-family: inherit;
            background: #F8FAFC;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--apple-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
        }

        .file-drop-area {
            border: 2px dashed #CBD5E1;
            border-radius: var(--radius-lg);
            padding: 24px 16px;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .file-drop-area.dragover {
            border-color: var(--apple-blue);
            background: rgba(0,122,255,0.04);
        }

        .file-drop-area:hover {
            border-color: var(--apple-blue);
            background: rgba(0,122,255,0.02);
        }

        .file-drop-area i {
            font-size: 32px;
            color: var(--apple-gray);
            margin-bottom: 10px;
        }

        .file-drop-area span {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--apple-gray);
        }

        .file-drop-area input[type=file] {
            display: none;
        }

        .btn-premium {
            width: 100%;
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-premium:hover {
            background: #0062CC;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
        }

        .btn-premium:disabled {
            background: #CBD5E1;
            cursor: not-allowed;
            box-shadow: none;
        }

        .search-container {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 14px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border-radius: var(--radius-lg);
            border: 1px solid #E2E8F0;
            font-size: 14px;
            background: white;
            box-sizing: border-box;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
        }

        .btn-search {
            background: var(--apple-slate);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-search:hover {
            filter: brightness(1.1);
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* Segmented View Switcher Control */
        .view-switcher {
            display: inline-flex;
            background: #E2E8F0;
            padding: 2px;
            border-radius: 10px;
            gap: 2px;
            align-items: center;
            height: 36px;
            box-sizing: border-box;
            flex-shrink: 0;
        }

        .btn-switch {
            background: transparent;
            border: none;
            color: var(--apple-gray);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .btn-switch:hover {
            color: var(--apple-slate);
        }

        .btn-switch.active {
            background: white;
            color: var(--apple-blue);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Permission Selectors Styling */
        .checkbox-scrollbox {
            border: 1px solid #E2E8F0;
            border-radius: var(--radius-lg);
            max-height: 150px;
            overflow-y: auto;
            padding: 8px 12px;
            background: #F8FAFC;
            box-sizing: border-box;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            cursor: pointer;
            font-size: 13px;
            color: var(--apple-slate);
            border-bottom: 1px solid rgba(0,0,0,0.02);
            user-select: none;
        }
        .checkbox-item:last-child {
            border-bottom: none;
        }
        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--apple-blue);
            cursor: pointer;
            margin: 0;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-slate);
            margin-bottom: 6px;
        }

        /* List View Styling */
        .doc-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .doc-list .doc-card {
            flex-direction: row;
            height: auto;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            gap: 20px;
        }

        .doc-list .doc-card .doc-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 24px;
            overflow: visible;
        }

        .doc-list .doc-card .doc-title {
            margin: 0;
            width: 30%;
            min-width: 180px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-list .doc-card .doc-info {
            display: flex;
            align-items: center;
            gap: 24px;
            flex: 1;
        }

        .doc-list .doc-card .doc-info div {
            margin-bottom: 0;
            white-space: nowrap;
        }

        .doc-list .doc-card .doc-actions {
            margin-top: 0;
            border-top: none;
            padding-top: 0;
            flex-shrink: 0;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        @media (max-width: 1024px) {
            .doc-list .doc-card .doc-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .doc-list .doc-card .doc-title {
                width: 100%;
            }
            .doc-list .doc-card .doc-info {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 16px;
            }
        }

        @media (max-width: 768px) {
            .search-container {
                flex-direction: column;
                align-items: stretch;
            }
            .view-switcher {
                align-self: flex-start;
                margin-top: 4px;
            }
            .doc-list .doc-card {
                flex-direction: column;
                align-items: stretch;
                padding: 16px;
            }
            .doc-list .doc-card .doc-meta {
                gap: 12px;
            }
            .doc-list .doc-card .doc-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .doc-list .doc-card .doc-actions {
                margin-top: 12px;
                border-top: 1px solid #F1F5F9;
                padding-top: 12px;
                justify-content: space-between;
                width: 100%;
            }
        }

        /* Document format badge styles */
        .badge-type {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 8px;
            vertical-align: middle;
        }
        .badge-pdf {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }
        .badge-word {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }
        .badge-html {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        .doc-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 180px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .doc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            border-color: rgba(0,122,255,0.1);
        }

        .doc-header {
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .doc-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(0, 122, 255, 0.08);
            color: var(--apple-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .doc-meta {
            overflow: hidden;
            width: 100%;
        }

        .doc-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--apple-slate);
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-info {
            font-size: 12px;
            color: var(--apple-gray);
            line-height: 1.4;
        }

        .doc-info div {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }

        .doc-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            border-top: 1px solid #F1F5F9;
            padding-top: 12px;
        }

        .btn-view {
            color: var(--apple-blue);
            background: rgba(0, 122, 255, 0.08);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view:hover {
            background: rgba(0, 122, 255, 0.15);
            transform: scale(1.02);
        }

        .btn-delete {
            color: var(--apple-red);
            background: transparent;
            border: none;
            padding: 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-delete:hover {
            background: rgba(255, 59, 48, 0.08);
        }

        .btn-permission {
            color: var(--apple-blue);
            background: transparent;
            border: none;
            padding: 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-permission:hover {
            background: rgba(0, 122, 255, 0.08);
        }

        .btn-download-file {
            color: var(--apple-gray);
            background: #F1F5F9;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-download-file:hover {
            background: #E2E8F0;
            color: var(--apple-slate);
        }

        .btn-circle-link {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #F1F5F9;
            color: var(--apple-slate);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
            box-sizing: border-box;
        }

        .btn-circle-link:hover {
            background: #E2E8F0;
            transform: scale(1.05);
        }

        /* HTML Viewer Inline Page */
        .viewer-inline-container {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .viewer-inline-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 16px;
            gap: 16px;
        }

        .btn-back {
            background: #F1F5F9;
            color: var(--apple-slate);
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #E2E8F0;
            transform: translateX(-2px);
        }

        .viewer-inline-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--apple-slate);
            margin: 0;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .viewer-inline-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .viewer-inline-body {
            background: #F8FAFC;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.04);
            height: calc(100vh - 240px);
            min-height: 500px;
        }

        .viewer-inline-body:fullscreen {
            padding: 0;
            border: none;
            border-radius: 0;
        }

        .viewer-iframe-inline {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }

        /* Selected file banner */
        .selected-file-banner {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: rgba(52, 199, 89, 0.08);
            border: 1px solid rgba(52, 199, 89, 0.2);
            border-radius: var(--radius-lg);
            margin-top: 10px;
            font-size: 13px;
            color: #15803d;
            font-weight: 500;
        }

        .selected-file-banner i {
            font-size: 16px;
        }

        .selected-file-banner .remove-file {
            margin-left: auto;
            cursor: pointer;
            color: var(--apple-gray);
            font-weight: bold;
        }

        .selected-file-banner .remove-file:hover {
            color: var(--apple-red);
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: var(--radius-xl);
            border: 1px dashed #CBD5E1;
        }

        .empty-state i {
            font-size: 48px;
            color: var(--apple-gray);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--apple-slate);
            margin: 0 0 8px 0;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--apple-gray);
            margin: 0;
        }

        /* Upload Trigger Button */
        .btn-upload-trigger {
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            white-space: nowrap;
        }

        .btn-upload-trigger:hover {
            background: #0062CC;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
            transform: translateY(-1px);
        }

        .btn-upload-trigger:active {
            transform: translateY(0);
        }

        /* Upload Modal Styles */
        .upload-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 1900;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .upload-modal.active {
            opacity: 1;
        }

        .upload-modal-content {
            background: var(--apple-card-bg);
            border-radius: var(--radius-xl);
            padding: 32px;
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-sizing: border-box;
            position: relative;
        }

        .upload-modal.active .upload-modal-content {
            transform: scale(1);
        }

        .upload-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 16px;
        }

        .btn-close-modal {
            background: transparent;
            border: none;
            font-size: 24px;
            color: var(--apple-gray);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .btn-close-modal:hover {
            color: var(--apple-red);
            background: rgba(255, 59, 48, 0.05);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php $page_title = 'Tài Liệu - Quy Trình'; include __DIR__ . '/../includes/topbar.php'; ?>
            
            <div class="content-wrapper">
                <div class="layout-grid">
                    
                    <!-- Main Documents Section (List & Search) -->
                    <div id="mainDocSection">
                        <!-- Search header -->
                        <div class="search-container">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" placeholder="Tìm kiếm tên tài liệu quy trình..." value="<?= htmlspecialchars($search_q) ?>" onkeypress="handleSearchKeyPress(event)">
                            </div>
                            <button class="btn-search" onclick="triggerSearch()">Tìm kiếm</button>
                            
                            <div class="view-switcher">
                                <button id="gridBtn" class="btn-switch" onclick="switchView('grid')" title="Dạng lưới">
                                    <i class="fas fa-th-large"></i>
                                </button>
                                <button id="listBtn" class="btn-switch active" onclick="switchView('list')" title="Dạng danh sách">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>

                            <?php if ($role === 'admin' || $role === 'manager'): ?>
                                <button class="btn-upload-trigger" onclick="openUploadModal()">
                                    <i class="fas fa-plus"></i> Tải lên tài liệu
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- List -->
                        <?php if (empty($files)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-circle-xmark"></i>
                                <h3>Không tìm thấy tài liệu nào</h3>
                                <p>Chưa có tài liệu quy trình nào được tải lên hoặc từ khóa tìm kiếm không khớp.</p>
                            </div>
                        <?php else: ?>
                            <div id="docContainer" class="doc-list">
                                <?php foreach ($files as $file): ?>
                                    <?php 
                                    $file_ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                    $badge_class = 'badge-html';
                                    $badge_text = 'HTML';
                                    $badge_icon = 'far fa-file-code';
                                    if ($file_ext === 'pdf') {
                                        $badge_class = 'badge-pdf';
                                        $badge_text = 'PDF';
                                        $badge_icon = 'far fa-file-pdf';
                                    } elseif ($file_ext === 'doc' || $file_ext === 'docx') {
                                        $badge_class = 'badge-word';
                                        $badge_text = 'Word';
                                        $badge_icon = 'far fa-file-word';
                                    }
                                    ?>
                                    <div class="doc-card">
                                        <div class="doc-meta">
                                            <h4 class="doc-title" title="<?= htmlspecialchars($file['title']) ?>">
                                                <?= htmlspecialchars($file['title']) ?>
                                                <span class="badge-type <?= $badge_class ?>"><i class="<?= $badge_icon ?>"></i> <?= $badge_text ?></span>
                                            </h4>
                                            <div class="doc-info">
                                                <div><i class="fas fa-user-circle"></i> <?= htmlspecialchars($file['uploader'] ?: 'Ẩn danh') ?></div>
                                                <div><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($file['created_at'])) ?></div>
                                                <div><i class="fas fa-weight-hanging"></i> <?= round($file['file_size'] / 1024, 2) ?> KB</div>
                                            </div>
                                        </div>
                                        
                                        <div class="doc-actions">
                                            <div class="doc-action-group" style="display: flex; gap: 8px; align-items: center;">
                                                <button class="btn-view" onclick="openHtmlViewer('<?= htmlspecialchars($file['file_path']) ?>', '<?= htmlspecialchars(addslashes($file['title'])) ?>')">
                                                    <i class="fas fa-book-open"></i>
                                                    Mở trực tiếp
                                                </button>
                                                <a href="<?= htmlspecialchars($file['file_path']) ?>" download class="btn-download-file" title="Tải xuống tài liệu">
                                                    <i class="fas fa-download"></i>
                                                    Tải xuống
                                                </a>
                                            </div>
                                            
                                            <?php if ($role === 'admin' || $role === 'manager'): ?>
                                                <button class="btn-permission" onclick="openPermissionsModal(<?= $file['id'] ?>)" title="Phân quyền tài liệu này">
                                                    <i class="fas fa-user-shield"></i>
                                                </button>
                                                <button class="btn-delete" onclick="deleteDocument(<?= $file['id'] ?>)" title="Xóa tài liệu này">
                                                    <i class="fas fa-trash-can"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Inline Document Viewer (Initially Hidden) -->
                    <div id="inlineViewerSection" style="display: none;">
                        <div class="viewer-inline-container">
                            <div class="viewer-inline-header">
                                <button class="btn-back" onclick="closeHtmlViewer()">
                                    <i class="fas fa-arrow-left"></i> Quay lại danh sách
                                </button>
                                <h3 class="viewer-inline-title" id="viewerInlineTitle">Tên tài liệu quy trình</h3>
                                <div class="viewer-inline-controls" style="display: flex; gap: 8px; align-items: center;">
                                    <a class="btn-circle-link" id="viewerDownloadBtn" href="#" download title="Tải xuống tài liệu">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button class="btn-circle" onclick="toggleFullscreenInline()" title="Toàn màn hình">
                                        <i class="fas fa-expand" id="fullscreenIconInline"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="viewer-inline-body" id="inlineViewerBody">
                                <iframe id="viewerIframeInline" class="viewer-iframe-inline" sandbox="allow-same-origin allow-scripts allow-popups"></iframe>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <?php if ($role === 'admin' || $role === 'manager'): ?>
    <div id="uploadModal" class="upload-modal">
        <div class="upload-modal-content">
            <div class="upload-modal-header">
                <h3 class="upload-form-title" style="margin: 0;">
                    <i class="fas fa-file-arrow-up" style="color: var(--apple-blue);"></i>
                    Tải lên quy trình mới
                </h3>
                <button class="btn-close-modal" onclick="closeUploadModal()">&times;</button>
            </div>
            
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Tiêu đề quy trình</label>
                    <input type="text" name="title" id="title" class="form-input" placeholder="Ví dụ: Quy trình onboard nhân sự" required>
                </div>
                
                <div class="form-group">
                    <label>Tài liệu (HTML, Word, PDF)</label>
                    <div class="file-drop-area" id="fileDropArea" onclick="document.getElementById('html_file').click()">
                        <i class="fas fa-file-arrow-up"></i>
                        <span>Kéo thả hoặc click để chọn file HTML, Word (.doc, .docx) hoặc PDF</span>
                        <input type="file" name="html_file" id="html_file" accept=".html,.htm,.doc,.docx,.pdf" required onchange="handleFileSelect(this)">
                    </div>
                    <div id="selectedFileBanner" class="selected-file-banner">
                        <i class="fas fa-check-circle"></i>
                        <span id="selectedFileName" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">filename.html</span>
                        <span class="remove-file" onclick="clearFileSelect(event)">&times;</span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label"><i class="fas fa-user-shield"></i> Phân quyền hiển thị (Để trống nếu muốn công khai)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px;">
                        <div>
                            <label class="form-label" style="font-weight: 500; font-size: 12px; color: var(--apple-gray);">Theo vai trò</label>
                            <div class="checkbox-scrollbox">
                                <?php foreach ($roles_list_avail as $r_key => $r_name): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="roles[]" value="<?= htmlspecialchars($r_key) ?>">
                                        <span><?= htmlspecialchars($r_name) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" style="font-weight: 500; font-size: 12px; color: var(--apple-gray);">Theo nhân viên</label>
                            <div class="checkbox-scrollbox">
                                <?php foreach ($users_list as $usr): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="users[]" value="<?= $usr['id'] ?>">
                                        <span><?= htmlspecialchars($usr['full_name']) ?> (<?= htmlspecialchars($usr['username']) ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-premium" id="submitBtn" style="margin-top: 20px;">
                    <i class="fas fa-cloud-arrow-up"></i>
                    Tải tài liệu lên
                </button>
            </form>
        </div>
    </div>
    
    <!-- Permissions Edit Modal -->
    <div id="permissionsModal" class="upload-modal">
        <div class="upload-modal-content">
            <div class="upload-modal-header">
                <h3 class="upload-form-title" style="margin: 0;">
                    <i class="fas fa-user-shield" style="color: var(--apple-blue);"></i>
                    Phân quyền tài liệu
                </h3>
                <button class="btn-close-modal" onclick="closePermissionsModal()">&times;</button>
            </div>
            
            <form id="permissionsForm">
                <input type="hidden" name="action" value="save_permissions">
                <input type="hidden" name="id" id="permFileId" value="">
                
                <div class="form-group">
                    <label class="form-label" id="permFileTitle" style="font-size: 14px; font-weight: 500; color: var(--apple-slate); margin-bottom: 12px;"></label>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user-shield"></i> Phân quyền hiển thị (Để trống nếu muốn công khai)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px;">
                        <div>
                            <label class="form-label" style="font-weight: 500; font-size: 12px; color: var(--apple-gray);">Theo vai trò</label>
                            <div class="checkbox-scrollbox">
                                <?php foreach ($roles_list_avail as $r_key => $r_name): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="roles[]" value="<?= htmlspecialchars($r_key) ?>" class="perm-role-checkbox">
                                        <span><?= htmlspecialchars($r_name) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" style="font-weight: 500; font-size: 12px; color: var(--apple-gray);">Theo nhân viên</label>
                            <div class="checkbox-scrollbox">
                                <?php foreach ($users_list as $usr): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="users[]" value="<?= $usr['id'] ?>" class="perm-user-checkbox">
                                        <span><?= htmlspecialchars($usr['full_name']) ?> (<?= htmlspecialchars($usr['username']) ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-premium" id="savePermBtn" style="margin-top: 16px;">
                    <i class="fas fa-floppy-disk"></i>
                    Lưu phân quyền
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>



    <script>
        const dropArea = document.getElementById('fileDropArea');
        
        if (dropArea) {
            // Drag over
            dropArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropArea.classList.add('dragover');
            });

            // Drag leave
            dropArea.addEventListener('dragleave', () => {
                dropArea.classList.remove('dragover');
            });

            // Drop
            dropArea.addEventListener('drop', (e) => {
                e.preventDefault();
                dropArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const fileInput = document.getElementById('html_file');
                    fileInput.files = files;
                    handleFileSelect(fileInput);
                }
            });
        }

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                const ext = file.name.split('.').pop().toLowerCase();
                const allowed = ['html', 'htm', 'doc', 'docx', 'pdf'];
                if (!allowed.includes(ext)) {
                    alert('Chỉ được chọn các file .html, .htm, .doc, .docx hoặc .pdf');
                    input.value = '';
                    document.getElementById('selectedFileBanner').style.display = 'none';
                    return;
                }
                document.getElementById('selectedFileName').innerText = file.name;
                document.getElementById('selectedFileBanner').style.display = 'flex';
            }
        }

        function clearFileSelect(event) {
            event.stopPropagation();
            const fileInput = document.getElementById('html_file');
            if (fileInput) fileInput.value = '';
            document.getElementById('selectedFileBanner').style.display = 'none';
        }

        // Upload AJAX
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tải lên...';
                
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Tải lên tài liệu quy trình thành công!');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Có lỗi xảy ra khi tải lên.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Tải tài liệu lên';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Có lỗi mạng xảy ra khi tải lên.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Tải tài liệu lên';
                });
            });
        }

        // Delete Document AJAX
        function deleteDocument(id) {
            if (!confirm('Bạn có chắc chắn muốn xóa tài liệu quy trình này không? Hành động này không thể hoàn tác.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_file');
            formData.append('id', id);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Lỗi khi xóa tài liệu.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Có lỗi mạng xảy ra khi thực hiện xóa.');
            });
        }

        // Search logic
        function handleSearchKeyPress(e) {
            if (e.key === 'Enter') {
                triggerSearch();
            }
        }

        function triggerSearch() {
            const q = document.getElementById('searchInput').value.trim();
            window.location.href = '?search=' + encodeURIComponent(q);
        }

        // HTML Viewer Inline logic
        function openHtmlViewer(filePath, title) {
            document.getElementById('viewerInlineTitle').innerText = title;
            document.getElementById('viewerDownloadBtn').href = filePath;
            const iframe = document.getElementById('viewerIframeInline');
            
            // Extract extension
            const ext = filePath.split('.').pop().split('?')[0].toLowerCase();
            
            if (ext === 'pdf') {
                iframe.removeAttribute('sandbox');
                iframe.src = filePath + '?t=' + Date.now();
            } else if (ext === 'docx') {
                iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts');
                iframe.src = 'about:blank';
                // Show loading message inside iframe first
                const doc = iframe.contentWindow.document;
                doc.open();
                doc.write('<html><body style="font-family:sans-serif;padding:20px;color:#64748b;">Đang chuyển đổi và tải tài liệu Word...</body></html>');
                doc.close();

                if (typeof mammoth !== 'undefined') {
                    fetch(filePath)
                        .then(response => {
                            if (!response.ok) throw new Error('Không thể tải file.');
                            return response.arrayBuffer();
                        })
                        .then(arrayBuffer => {
                            mammoth.convertToHtml({arrayBuffer: arrayBuffer})
                                .then(result => {
                                    doc.open();
                                    doc.write(`
                                        <html>
                                        <head>
                                            <meta charset="UTF-8">
                                            <style>
                                                body {
                                                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                                                    line-height: 1.6;
                                                    color: #1e293b;
                                                    padding: 32px;
                                                    background: white;
                                                    max-width: 800px;
                                                    margin: 0 auto;
                                                }
                                                h1, h2, h3, h4 { color: #0f172a; margin-top: 1.5em; }
                                                p { margin-bottom: 1em; }
                                                table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                                                th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
                                                th { background: #f8fafc; }
                                            </style>
                                        </head>
                                        <body>
                                            ${result.value || '<p style="color:#64748b;text-align:center;">Tài liệu trống</p>'}
                                        </body>
                                        </html>
                                    `);
                                    doc.close();
                                })
                                .catch(err => {
                                    console.error(err);
                                    showDocError(iframe, filePath, 'Lỗi chuyển đổi tài liệu Word: ' + err.message);
                                });
                        })
                        .catch(err => {
                            console.error(err);
                            showDocError(iframe, filePath, 'Lỗi tải tài liệu: ' + err.message);
                        });
                } else {
                    showDocError(iframe, filePath, 'Không thể tải thư viện chuyển đổi Word (Mammoth.js).');
                }
            } else if (ext === 'doc') {
                iframe.removeAttribute('sandbox');
                showDocError(iframe, filePath, 'Trình duyệt không hỗ trợ xem trực tiếp định dạng .doc (Word 97-2003). Vui lòng tải file về thiết bị để xem.');
            } else {
                // HTML/HTM
                iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-popups');
                iframe.src = filePath + '?t=' + Date.now();
            }
            
            document.getElementById('mainDocSection').style.display = 'none';
            document.getElementById('inlineViewerSection').style.display = 'block';
            
            // Scroll to top of the content area
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showDocError(iframe, filePath, message) {
            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(`
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body {
                            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: center;
                            height: 100vh;
                            margin: 0;
                            background: #F8FAFC;
                            color: #1e293b;
                            text-align: center;
                            padding: 24px;
                            box-sizing: border-box;
                        }
                        .container {
                            max-width: 400px;
                            background: white;
                            padding: 32px;
                            border-radius: 16px;
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                        }
                        i { font-size: 48px; color: #64748b; margin-bottom: 16px; display: block; }
                        h3 { margin: 0 0 10px 0; font-size: 18px; font-weight: 600; }
                        p { font-size: 14px; color: #64748b; margin: 0 0 20px 0; line-height: 1.5; }
                        .btn-download {
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            background: #007AFF;
                            color: white;
                            text-decoration: none;
                            padding: 10px 20px;
                            border-radius: 8px;
                            font-weight: 600;
                            font-size: 14px;
                            transition: background 0.2s;
                        }
                        .btn-download:hover { background: #0062CC; }
                    </style>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                </head>
                <body>
                    <div class="container">
                        <i class="fas fa-file-arrow-down"></i>
                        <h3>Tải tài liệu xuống</h3>
                        <p>${message}</p>
                        <a href="${filePath}" download class="btn-download" target="_parent">
                            <i class="fas fa-download"></i> Tải xuống tài liệu
                        </a>
                    </div>
                </body>
                </html>
            `);
            doc.close();
        }

        function closeHtmlViewer() {
            document.getElementById('inlineViewerSection').style.display = 'none';
            document.getElementById('mainDocSection').style.display = 'block';
            
            document.getElementById('viewerIframeInline').src = 'about:blank';
            
            // Exit fullscreen if open
            if (document.fullscreenElement) {
                document.exitFullscreen();
            }
        }

        // Fullscreen Toggle for Inline Viewer
        function toggleFullscreenInline() {
            const body = document.getElementById('inlineViewerBody');
            const icon = document.getElementById('fullscreenIconInline');
            
            if (!document.fullscreenElement) {
                body.requestFullscreen().then(() => {
                    icon.className = 'fas fa-minimize';
                }).catch(err => {
                    console.error('Error entering fullscreen:', err);
                });
            } else {
                document.exitFullscreen();
                icon.className = 'fas fa-expand';
            }
        }

        // Listen for browser fullscreen exit event (e.g. Esc key pressed)
        document.addEventListener('fullscreenchange', function() {
            const icon = document.getElementById('fullscreenIconInline');
            if (!document.fullscreenElement && icon) {
                icon.className = 'fas fa-expand';
            }
        });

        // Upload Modal Controls
        function openUploadModal() {
            const modal = document.getElementById('uploadModal');
            if (modal) {
                modal.style.display = 'flex';
                // Trigger reflow to apply transition
                modal.offsetHeight;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Lock scrolling
            }
        }

        function closeUploadModal() {
            const modal = document.getElementById('uploadModal');
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
                document.body.style.overflow = ''; // Unlock scrolling
                
                // Clear form on close
                const uploadForm = document.getElementById('uploadForm');
                if (uploadForm) {
                    uploadForm.reset();
                }
                const selectedFileBanner = document.getElementById('selectedFileBanner');
                if (selectedFileBanner) {
                    selectedFileBanner.style.display = 'none';
                }
            }
        }

        // Close upload modal when clicking outside content
        const uploadModal = document.getElementById('uploadModal');
        if (uploadModal) {
            uploadModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeUploadModal();
                }
            });
        }

        // Permissions Modal Controls
        function openPermissionsModal(fileId) {
            const modal = document.getElementById('permissionsModal');
            if (!modal) return;
            
            document.body.style.overflow = 'hidden'; // Lock scrolling
            document.getElementById('permFileId').value = fileId;
            
            // Extract document title from UI
            let title = '';
            const cards = document.querySelectorAll('.doc-card');
            cards.forEach(card => {
                const btn = card.querySelector(`.btn-permission[onclick*="(${fileId})"]`);
                if (btn) {
                    const titleEl = card.querySelector('.doc-title');
                    if (titleEl) {
                        const clone = titleEl.cloneNode(true);
                        const badge = clone.querySelector('.badge-type');
                        if (badge) badge.remove();
                        title = clone.textContent.trim();
                    }
                }
            });
            document.getElementById('permFileTitle').innerText = 'Tài liệu: ' + title;
            
            // Reset checklists
            document.querySelectorAll('.perm-role-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.perm-user-checkbox').forEach(cb => cb.checked = false);
            
            const formData = new FormData();
            formData.append('action', 'get_permissions');
            formData.append('id', fileId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.roles && Array.isArray(data.roles)) {
                        data.roles.forEach(roleKey => {
                            const cb = document.querySelector(`.perm-role-checkbox[value="${roleKey}"]`);
                            if (cb) cb.checked = true;
                        });
                    }
                    if (data.users && Array.isArray(data.users)) {
                        data.users.forEach(userId => {
                            const cb = document.querySelector(`.perm-user-checkbox[value="${userId}"]`);
                            if (cb) cb.checked = true;
                        });
                    }
                    modal.style.display = 'flex';
                    modal.offsetHeight;
                    modal.classList.add('active');
                } else {
                    alert(data.message || 'Lỗi khi lấy thông tin phân quyền.');
                    document.body.style.overflow = '';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Có lỗi mạng xảy ra khi lấy thông tin phân quyền.');
                document.body.style.overflow = '';
            });
        }
        
        function closePermissionsModal() {
            const modal = document.getElementById('permissionsModal');
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
                document.body.style.overflow = ''; // Unlock scrolling
                
                const form = document.getElementById('permissionsForm');
                if (form) form.reset();
            }
        }
        
        const permissionsModal = document.getElementById('permissionsModal');
        if (permissionsModal) {
            permissionsModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePermissionsModal();
                }
            });
        }
        
        const permissionsForm = document.getElementById('permissionsForm');
        if (permissionsForm) {
            permissionsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const saveBtn = document.getElementById('savePermBtn');
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
                
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Lưu phân quyền thành công!');
                        closePermissionsModal();
                        window.location.reload();
                    } else {
                        alert(data.message || 'Có lỗi xảy ra khi lưu phân quyền.');
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-floppy-disk"></i> Lưu phân quyền';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Có lỗi mạng xảy ra khi lưu phân quyền.');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-floppy-disk"></i> Lưu phân quyền';
                });
            });
        }

        // Grid / List View Switcher logic
        function switchView(viewType) {
            const container = document.getElementById('docContainer');
            const gridBtn = document.getElementById('gridBtn');
            const listBtn = document.getElementById('listBtn');
            
            if (viewType === 'list') {
                if (container) {
                    container.classList.remove('doc-grid');
                    container.classList.add('doc-list');
                }
                if (gridBtn) gridBtn.classList.remove('active');
                if (listBtn) listBtn.classList.add('active');
            } else {
                if (container) {
                    container.classList.remove('doc-list');
                    container.classList.add('doc-grid');
                }
                if (gridBtn) gridBtn.classList.add('active');
                if (listBtn) listBtn.classList.remove('active');
            }
            localStorage.setItem('docViewPreference', viewType);
        }
        
        // Initialize view switcher on page load
        document.addEventListener('DOMContentLoaded', () => {
            const savedView = localStorage.getItem('docViewPreference') || 'list';
            switchView(savedView);
        });
    </script>
</body>
</html>
