<?php
require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Access control: Admin or Hyun Cao only
if ($_SESSION['role'] !== 'admin' && ($_SESSION['full_name'] ?? '') !== 'Hyun Cao') {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'] ?? 'User';

// Fetch customers for selection
require_once __DIR__ . '/../../libs/OdooAPI.php';
$customers = [];
try {
    $odoo = new OdooAPI();
    $customer_res = $odoo->getCustomers(1000, 0); // Get first 1000 customers from cache
    $customers = $customer_res['customers'] ?? [];
    // Sort customers by name
    usort($customers, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
} catch (Exception $e) {
    // Silence error, just no customers
}

// Auto create chat tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS presale_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS presale_chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES presale_projects(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS presale_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    role ENUM('system', 'user', 'assistant') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES presale_chat_sessions(id) ON DELETE CASCADE
)");

// Thêm bảng lưu trữ File cố định cho mỗi Dự án (Session)
$conn->query("CREATE TABLE IF NOT EXISTS presale_session_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NULL,
    project_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    extracted_text MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Thêm bảng chia sẻ dự án
$conn->query("CREATE TABLE IF NOT EXISTS presale_project_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$check_col = $conn->query("SHOW COLUMNS FROM presale_chat_sessions LIKE 'project_id'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE presale_chat_sessions ADD COLUMN project_id INT NULL AFTER user_id");
    $res = $conn->query("SELECT id, user_id, title, created_at FROM presale_chat_sessions");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pid = $row['id'];
            $uid = $row['user_id'];
            $title = $conn->real_escape_string($row['title'] ?: 'Untitled Project');
            $conn->query("INSERT INTO presale_projects (id, user_id, name) VALUES ($pid, $uid, '$title')");
            $conn->query("UPDATE presale_chat_sessions SET project_id = $pid WHERE id = $pid");
        }
    }
}
$check_col2 = $conn->query("SHOW COLUMNS FROM presale_session_files LIKE 'project_id'");
if ($check_col2 && $check_col2->num_rows == 0) {
    $conn->query("ALTER TABLE presale_session_files ADD COLUMN project_id INT NULL AFTER session_id");
    $conn->query("UPDATE presale_session_files SET project_id = session_id WHERE project_id IS NULL");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Assistant</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-sidebar: #f8fafc;
            --bg-chat: #fff;
            --text-primary: #0f172a;
            --border-color: #e2e8f0;
        }
        body.dark-mode {
            --bg-sidebar: #0f172a;
            --bg-chat: #1e293b;
            --text-primary: #f8fafc;
            --border-color: #334155;
            background: #0f172a;
            color: #f8fafc;
        }
        .assistant-container {
            background: var(--bg-chat);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            height: calc(100vh - 140px);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .chat-sidebar {
            width: 250px;
            border-right: 1px solid var(--border-color);
            background: var(--bg-sidebar);
            display: flex;
            flex-direction: column;
        }
        .dark-mode .assistant-container { background: #1e293b; border-color: #334155; }
        .dark-mode .chat-sidebar { background: #0f172a; border-right-color: #334155; }
        .dark-mode .chat-sidebar-header, .dark-mode .project-header { color: #f8fafc; }
        .dark-mode .chat-area { background: #1e293b; }
        .dark-mode .chat-input-area, .dark-mode .quick-actions { background: #0f172a; border-color: #334155; }
        .dark-mode .message.assistant { background: #334155; color: #f8fafc; border-color: #475569; }
        .dark-mode .message.user { background: #475569; color: #fff; }
        .dark-mode .chat-input-box textarea { background: #334155; color: #fff; border-color: #475569; }
        .dark-mode .project-header:hover { background: #334155; }
        
        .sidebar-search {
            padding: 8px 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .sidebar-search input {
            width: 100%;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            outline: none;
            background: var(--bg-chat);
            color: var(--text-primary);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: var(--bg-chat);
            margin: 5% auto;
            padding: 20px;
            border: 1px solid var(--border-color);
            width: 70%;
            max-height: 80vh;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .modal-body {
            overflow-y: auto;
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-primary);
        }
        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover { color: #000; }

        .drop-zone {
            border: 2px dashed transparent;
            transition: all 0.2s;
        }
        .drop-zone.dragover {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
        }
        .chat-sidebar-header {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #0f172a;
        }
        .new-chat-btn {
            display: block;
            width: calc(100% - 32px);
            margin: 16px auto;
            padding: 10px;
            background: #0f172a;
            color: white;
            text-align: center;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .new-chat-btn:hover { background: #1e293b; }
        .history-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px 16px;
        }
        .project-item { margin-bottom: 8px; }
        .project-header {
            padding: 10px; font-size: 14px; font-weight: 500; color: #1e293b;
            cursor: pointer; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;
        }
        .project-header:hover { background: #e2e8f0; }
        .project-header.active { background: #e0e7ff; color: #3730a3; }
        .project-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
        .project-header .delete-btn { color: #94a3b8; font-size: 16px; padding: 0 4px; display: none; }
        .project-header:hover .delete-btn { display: block; }
        .project-header .delete-btn:hover { color: #ef4444; }
        .chat-list { padding-left: 20px; margin-top: 4px; display: none; }
        .chat-item { 
            padding: 8px 10px; font-size: 13px; color: #475569; cursor: pointer; border-radius: 6px; margin-bottom: 2px;
            display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;
        }
        .chat-item:hover, .chat-item.active { background: #e2e8f0; color: #0f172a; }
        .chat-item .delete-chat-btn { opacity: 0; color: #94a3b8; padding: 0 4px; transition: all 0.2s; }
        .chat-item:hover .delete-chat-btn { opacity: 1; }
        .chat-item .delete-chat-btn:hover { color: #ef4444; background: #fee2e2; border-radius: 4px; }
        .new-chat-in-project { padding: 8px 10px; font-size: 13px; color: #4f46e5; cursor: pointer; font-weight: 500; }
        .new-chat-in-project:hover { text-decoration: underline; }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }
        .message.user {
            background: #f1f5f9;
            color: #0f172a;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
            white-space: pre-wrap;
        }
        .message.assistant {
            background: #eef2ff;
            color: #1e3a8a;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
            border: 1px solid #c7d2fe;
            white-space: normal;
        }
        .message.assistant p { margin: 0 0 10px 0; }
        .message.assistant p:last-child { margin-bottom: 0; }
        .message.assistant ul, .message.assistant ol { margin: 0 0 10px 20px; padding: 0; }
        .message.assistant pre { background: #1e293b; color: #fff; padding: 10px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
        .message.assistant code { font-family: monospace; background: rgba(0,0,0,0.1); padding: 2px 4px; border-radius: 3px; font-size: 13px; }

        /* Settings Sidebar */
        .settings-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: #fff;
            box-shadow: -4px 0 15px rgba(0,0,0,0.1);
            z-index: 2000;
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            border-left: 1px solid var(--border-color);
        }
        .settings-sidebar.active { right: 0; }
        .settings-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }
        .settings-sidebar-body {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
        }
        .settings-group { margin-bottom: 24px; }
        .settings-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
        }
        .settings-input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .settings-input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        /* Custom Customer Search */
        .customer-search-container { position: relative; }
        .customer-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-height: 250px;
            overflow-y: auto;
            z-index: 2100;
            display: none;
            margin-top: 4px;
        }
        .customer-option {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }
        .customer-option:hover { background: #f1f5f9; color: #4f46e5; }
        .customer-option.no-results { color: #94a3b8; cursor: default; }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1999;
            display: none;
        }
        .sidebar-overlay.active { display: block; }
        .message.assistant h1, .message.assistant h2, .message.assistant h3, .message.assistant h4 { 
            margin: 20px 0 12px 0; 
            font-size: 16px; 
            font-weight: 700;
            color: #1e293b; 
            letter-spacing: -0.01em;
        }

        .message.assistant h1:first-child, .message.assistant h2:first-child, .message.assistant h3:first-child { margin-top: 0; }
        .message.assistant table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .message.assistant th {
            background: #1e293b;
            color: #ffffff;
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid #1e293b;
        }
        .message.assistant td {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            color: #334155;
            line-height: 1.5;
        }

        .chat-input-area {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }
        .chat-input-box {
            display: flex;
            gap: 12px;
            align-items: center;
            position: relative;
        }
        .attach-btn {
            background: transparent;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .attach-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .chat-input-box textarea {
            flex: 1;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            resize: none;
            outline: none;
            font-family: inherit;
            font-size: 14px;
            height: 48px;
            transition: all 0.2s;
        }
        .chat-input-box textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
        
        /* CSS cho Mention Dropdown */
        .mention-dropdown {
            position: absolute;
            bottom: calc(100% + 10px);
            left: 110px; /* Căn chỉnh với textarea */
            width: 300px;
            max-height: 200px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1), 0 -2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 1000;
        }
        .dark-mode .mention-dropdown {
            background: #1e293b;
            border-color: #334155;
        }
        .mention-item {
            padding: 10px 12px;
            font-size: 13px;
            color: #334155;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dark-mode .mention-item {
            color: #e2e8f0;
            border-bottom-color: #334155;
        }
        .mention-item:hover, .mention-item.active {
            background: #f1f5f9;
        }
        .dark-mode .mention-item:hover, .dark-mode .mention-item.active {
            background: #334155;
        }
        .send-btn {
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            height: 48px;
        }
        .send-btn:hover { background: #4338ca; }
        .quick-actions {
            display: flex;
            gap: 10px;
            padding: 12px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            overflow-x: auto;
        }
        .quick-action-btn {
            padding: 6px 12px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            font-size: 12px;
            color: #475569;
            cursor: pointer;
            white-space: nowrap;
        }
        .quick-action-btn:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .quick-action-btn.active {
            background: #4f46e5;
            color: white !important;
            border-color: #4f46e5;
        }
        .attach-btn.recording {
            color: #ef4444;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); box-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }
            100% { transform: scale(1); }
        }
        /* AHT Proposal Template Styles - Premium Version */
        .quotation-container {
            background: white;
            color: #1a1a1a;
            padding: 0;
            max-width: 1000px;
            margin: 0 auto;
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-radius: 4px;
            position: relative;
        }
        
        /* Cover Page */
        .quote-cover {
            height: 900px;
            display: flex;
            position: relative;
            background: #fff;
            overflow: hidden;
        }
        .cover-sidebar {
            width: 120px;
            background: #1e293b;
            height: 100%;
        }
        .cover-content {
            flex: 1;
            padding: 80px 80px;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .cover-logo {
            text-align: right;
            margin-bottom: 40px;
        }
        .cover-logo img { height: 50px; }
        .cover-main-title {
            font-size: 72px;
            font-weight: 900;
            color: #1e293b;
            margin: 40px 0 15px 0;
            letter-spacing: -2px;
            line-height: 1;
        }
        .cover-sub-title {
            font-size: 24px;
            color: #64748b;
            font-weight: 400;
            margin-bottom: 40px;
            letter-spacing: 2px;
        }
        .cover-info {
            margin-top: 0;
            background: #f8fafc;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .cover-info p { margin: 6px 0; font-size: 14px; color: #475569; }
        .cover-info strong { color: #1e293b; font-size: 16px; }
        .cover-footer-info {
            margin-top: auto;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #94a3b8;
        }


        /* Content Pages */
        .quote-content-body { padding: 60px 80px; }
        .quotation-section { margin-bottom: 50px; }
        .quotation-section h2 {
            font-size: 22px;
            color: #1e293b;
            margin-bottom: 25px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .quotation-section h2::before {
            content: "";
            display: block;
            width: 4px;
            height: 24px;
            background: #4f46e5;
            border-radius: 2px;
        }
        .quote-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .quote-table th {
            background: #1e293b;
            color: white;
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid #1e293b;
        }
        .quote-table td {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            color: #334155;
            line-height: 1.5;
        }
        .quote-table tr:nth-child(even) { background: #f8fafc; }


        /* Summary Table Specifics - Handled by JS for better control */
        .summary-table-premium td:nth-child(2) {
            width: 150px;
        }

        /* Premium Table Styles for all tables in Proposal */
        .quotation-container table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 20px 0 !important;
            font-size: 13px !important;
            border: 1px solid #e2e8f0 !important;
        }
        .quotation-container table th {
            background-color: #1e293b !important;
            color: white !important;
            padding: 12px 15px !important;
            text-align: left !important;
            font-weight: 600 !important;
            border: 1px solid #1e293b !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .quotation-container table td {
            padding: 12px 15px !important;
            border: 1px solid #e2e8f0 !important;
            vertical-align: top !important;
            color: #334155 !important;
        }
        .quotation-container table tr:nth-child(even) {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .quotation-container h4 {
            margin-top: 30px !important;
            margin-bottom: 15px !important;
            color: #0f172a !important;
            border-left: 4px solid #1e293b !important;
            padding-left: 12px !important;
            font-size: 18px !important;
        }


        @media print {
            @page {
                size: auto;
                margin: 0;
            }
            *, *:before, *:after {
                box-sizing: border-box !important;
            }
            html, body {
                height: auto !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body > *:not(#quotation-modal) {
                display: none !important;
            }
            #quotation-modal {
                display: block !important;
                position: relative !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                overflow: visible !important;
            }
            #quotation-modal .modal-content {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                max-height: none !important;
                box-shadow: none !important;
                border: none !important;
                background: white !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            #quotation-print-area {
                display: block !important;
                overflow: visible !important;
                height: auto !important;
            }
            .no-print {
                display: none !important;
            }
            .quotation-container {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                overflow: visible !important;
                background: white !important;
                border: none !important;
            }
            .quote-cover { 
                height: 100vh !important;
                display: flex !important;
                overflow: hidden !important;
                position: relative !important;
                background: white !important;
                page-break-after: always;
                page-break-inside: avoid;
            }
            .cover-sidebar {
                width: 100px !important;
                background: #1e293b !important;
                height: 100% !important;
                -webkit-print-color-adjust: exact !important;
            }
            .cover-content {
                flex: 1 !important;
                padding: 30px 50px !important;
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                position: relative !important;
            }

            .cover-main-title {
                font-size: 60px !important; /* Thu nhỏ font cho landscape */
                margin: 20px 0 10px 0 !important;
                color: #1e293b !important;
                -webkit-print-color-adjust: exact !important;
            }
            .cover-sub-title {
                font-size: 18px !important;
                margin-bottom: 20px !important;
            }
            .cover-logo { margin-bottom: 20px !important; }
            .cover-info { padding: 15px !important; }
            
            .quotation-section { 
                page-break-inside: avoid; 
                display: block !important;
                width: 100% !important;
                margin-left: 0 !important;
                background: white !important;
                padding: 40px !important;
                border-bottom: 1px solid #f1f5f9;
            }
            .quote-content-body {
                padding: 0 !important;
            }
            table { 
                page-break-inside: auto !important; 
                width: 100% !important;
            }
            tr { page-break-inside: avoid !important; }
            thead { display: table-header-group !important; }
        }







    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Sale Assistant';
            $page_subtitle = 'AI-powered assistant for drafting proposals and Q&A';
            include __DIR__ . '/../includes/topbar.php';
            ?>
            <div class="content-wrapper">
                <div class="assistant-container">
                    <!-- Sidebar -->
                    <div class="chat-sidebar">
                        <div style="padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-weight: 600; font-size: 14px;">Presale AI</span>
                            <button id="dark-mode-toggle" style="background: none; border: none; cursor: pointer; font-size: 18px;" title="Chuyển chế độ Sáng/Tối">🌓</button>
                        </div>
                        <div class="sidebar-search">
                            <input type="text" id="project-search" placeholder="Tìm kiếm dự án...">
                        </div>
                        <div style="padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 8px;">
                            <a href="javascript:void(0)" class="new-chat-btn" id="new-chat-btn" onclick="handleNewChat()" style="margin: 0; width: 100%;">+ Chat mới</a>
                            <a href="javascript:void(0)" onclick="createNewProject()" style="font-size: 13px; color: #475569; text-align: center; text-decoration: none; border: 1px dashed #cbd5e1; padding: 6px; border-radius: 6px;">📁 Dự án mới</a>
                        </div>
                        <div class="history-list" id="history-list">
                            <?php
                            try {
                                $user_id_safe = (int)$_SESSION['user_id'];
                                $projects = $conn->query("
                                    SELECT DISTINCT p.id, p.name, p.user_id 
                                    FROM presale_projects p 
                                    LEFT JOIN presale_project_shares s ON p.id = s.project_id 
                                    WHERE p.user_id = $user_id_safe OR s.user_id = $user_id_safe 
                                    ORDER BY p.id DESC LIMIT 50
                                ");
                                if ($projects) {
                                    while ($p = $projects->fetch_assoc()) {
                                        $title = $p['name'] ? htmlspecialchars($p['name']) : 'New Project';
                                        $shared_badge = ($p['user_id'] != $user_id_safe) ? " <span style='font-size:10px;color:#f59e0b;border:1px solid #f59e0b;padding:1px 4px;border-radius:4px;'>Shared</span>" : "";
                                        
                                        echo "<div class='project-item' data-id='{$p['id']}'>";
                                        echo "  <div class='project-header' onclick='toggleProject({$p['id']}, this)'>";
                                        echo "      <span class='project-title'>📁 {$title}{$shared_badge}</span>";
                                        echo "      <span class='delete-btn' onclick='deleteProject(event, {$p['id']})' title='Xoá Dự án'>&times;</span>";
                                        echo "  </div>";
                                        echo "  <div class='chat-list' id='chat-list-{$p['id']}'>";
                                        $chats = $conn->query("SELECT id, title FROM presale_chat_sessions WHERE project_id = {$p['id']} ORDER BY id DESC");
                                        if ($chats) {
                                            while ($c = $chats->fetch_assoc()) {
                                                $chat_title = $c['title'] ? htmlspecialchars($c['title']) : 'Chat ' . $c['id'];
                                                $encoded_title = htmlspecialchars($c['title'] ?: 'Không có tiêu đề', ENT_QUOTES, 'UTF-8');
                                                echo "      <div class='chat-item' data-session-id='{$c['id']}' data-project-id='{$p['id']}' data-title='{$encoded_title}' onclick='openChat(this, {$p['id']}, {$c['id']})'>
                                                                <span style='flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;'>💬 {$chat_title}</span>
                                                                <span class='delete-chat-btn' onclick='deleteChat(event, {$c['id']})' title='Xoá chat'>&times;</span>
                                                            </div>";
                                            }
                                        }
                                        echo "      <div class='new-chat-in-project' onclick='createNewChat({$p['id']})'>+ Chat mới</div>";
                                        echo "  </div>";
                                        echo "</div>";
                                    }
                                }
                            } catch (Exception $e) {}
                            ?>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="chat-area">

                        <!-- Project Header -->
                        <div id="project-workspace-header" style="display: none; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h3 id="current-project-title" style="margin: 0; font-size: 16px; color: #0f172a;"></h3>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <select id="doc-type-select" class="settings-input" style="height: 32px; padding: 0 10px; font-size: 13px; width: auto; background: #fff; margin-right: 4px;">
                                        <option value="Xây dựng tài liệu báo giá">Xây dựng tài liệu báo giá</option>
                                        <option value="Xây dựng Sale Pitch">Xây dựng Sale Pitch</option>
                                        <option value="Xây dựng Tài liệu giải pháp">Xây dựng Tài liệu giải pháp</option>
                                        <option value="Phân tích & Tóm tắt RFP">Phân tích & Tóm tắt RFP</option>
                                    </select>
                                    <button class="quick-action-btn" style="height: 32px; display: flex; align-items: center;" onclick="shareProject()">🔗 Chia sẻ</button>
                                    <input type="file" id="project-file-upload" style="display: none;" accept=".pdf,.doc,.docx,.txt" multiple>
                                    <button class="send-btn" style="height: 32px; padding: 0 12px; font-size: 13px;" onclick="document.getElementById('project-file-upload').click()">+ Tải tài liệu lên</button>
                                    
                                    <div style="display: flex; align-items: center; gap: 8px; margin-left: 15px; border-left: 1px solid #e2e8f0; padding-left: 15px;">
                                        <button class="quick-action-btn" style="height: 32px; display: flex; align-items: center; background: #f1f5f9; color: #475569; border-color: #cbd5e1;" onclick="toggleSettingsSidebar()">
                                            ⚙️ Thiết lập Dự án
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="project-files-list" style="display: flex; flex-wrap: wrap; gap: 8px;">
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="chat-messages" id="chat-messages">
                            <div class="message assistant">
                                Xin chào! Tôi là trợ lý AI Sale/Presale. Tôi có thể giúp bạn trả lời câu hỏi nghiệp vụ, viết nháp Proposal/SOW, gợi ý Tech Stack, hoặc tìm kiếm Case Study tương tự.
                            </div>
                        </div>

                        <!-- Input -->
                        <div class="chat-input-area">
                            <!-- Quick Actions -->
                            <div class="quick-actions" style="padding: 0 0 12px 0; border: none; background: transparent;">
                                <?php
                                try {
                                    $prompts = $conn->query("SELECT action_key, title FROM presale_prompts ORDER BY id ASC");
                                    if ($prompts && $prompts->num_rows > 0) {
                                        while ($p = $prompts->fetch_assoc()) {
                                            echo "<button class='quick-action-btn' data-action='{$p['action_key']}'>" . htmlspecialchars($p['title']) . "</button>";
                                        }
                                    } else {
                                        echo "<button class='quick-action-btn' data-action='qna'>Q&A Presale</button>";
                                        echo "<button class='quick-action-btn' data-action='create_sow'>Tạo SOW / Proposal Draft</button>";
                                    }
                                } catch (Exception $e) {
                                    echo "<button class='quick-action-btn' data-action='qna'>Q&A Presale</button>";
                                    echo "<button class='quick-action-btn' data-action='create_sow'>Tạo SOW / Proposal Draft</button>";
                                }
                                ?>
                            </div>
                            <!-- Hiển thị tên file đã đính kèm -->
                            <div id="attachment-preview" style="display: none; padding-bottom: 8px; font-size: 13px; color: #4f46e5; align-items: center; gap: 6px;">
                                📎 <span id="attachment-name"></span>
                                <button type="button" id="remove-attachment" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 14px;">&times;</button>
                            </div>
                            
                            <div class="chat-input-box">
                                <div id="mention-dropdown" class="mention-dropdown" style="display:none;"></div>
                                <input type="file" id="file-upload" style="display: none;" accept=".pdf,.doc,.docx,.txt" />
                                <button class="attach-btn" id="attach-btn" title="Đính kèm tài liệu RFP/RFQ">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                </button>
                                <button class="attach-btn" id="voice-btn" title="Nhập liệu bằng giọng nói" style="margin-right: 8px;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                        <line x1="12" y1="19" x2="12" y2="23"></line>
                                        <line x1="8" y1="23" x2="16" y2="23"></line>
                                    </svg>
                                </button>
                                <textarea id="chat-input" placeholder="Nhập câu hỏi hoặc yêu cầu của bạn..." rows="1"></textarea>
                                <button class="send-btn" id="send-btn">Gửi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- File Preview Modal -->
    <div id="file-preview-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="preview-filename" style="margin:0;">Xem trước tài liệu</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="preview-content"></div>
        </div>
    </div>

    <!-- Quotation View Modal (AHT Style) -->
    <div id="quotation-modal" class="modal">
        <div class="modal-content" style="max-width: 1000px; padding: 0; background: #f1f5f9; overflow: hidden; display: flex; flex-direction: column;">
            <div class="no-print" style="padding: 12px 20px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 14px; color: #64748b;">Xem trước bản báo giá AHT TECH JSC</h3>
                <div style="display: flex; gap: 10px;">
                    <button class="send-btn" style="background: #64748b; height: 36px;" onclick="window.print()">🖨️ In / Xuất PDF</button>
                    <button class="send-btn" style="background: #ef4444; height: 36px;" onclick="closeQuoteModal()">Đóng</button>
                </div>
            </div>
            
            <div id="quotation-print-area" style="overflow-y: auto; flex: 1;">
                <div class="quotation-container">
                    <!-- Cover Page -->
                    <div class="quote-cover">
                        <div class="cover-sidebar"></div>
                        <div class="cover-content">
                                <div class="cover-logo" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                    <img src="https://aht.tech/wp-content/uploads/2021/04/logo-aht.png" alt="AHT Logo" style="height: 50px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div style="display: none; font-size: 32px; font-weight: 800; color: #333c4d; letter-spacing: -1px;">AHT<span style="color: #4f46e5;">.</span>TECH</div>
                                </div>
                            <div class="cover-main-title">PROPOSAL</div>
                            <div class="cover-sub-title">SOFTWARE DEVELOPMENT SERVICES</div>
                            <div class="cover-info">
                                <p>Version 1.0 - <strong><?php echo date('d/m/Y'); ?></strong></p>
                                <p>Proposal ID: <strong>#QT-<?php echo date('Ymd'); ?></strong></p>
                                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                                    <p>Prepared by: <strong>AHT TECH JSC</strong></p>
                                    <p>Proposed to: <strong id="quote-client-name-cover" style="color: #1e293b;">CLIENT NAME</strong></p>
                                </div>
                            </div>
                            <div class="cover-footer-info">
                                <div>
                                    <p><strong>AHT TECH JSC</strong></p>
                                    <p>Website: https://aht.tech</p>
                                </div>
                                <div style="text-align: right;">
                                    <p>Email: info@arrowhitech.com</p>
                                    <p>Tel: (+84) 2437 955 813</p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Content Body -->
                    <div class="quote-content-body">
                        <div id="quote-sections-container">
                            <!-- AI content (Scope & Estimation) will be rendered here -->
                        </div>

                        <!-- IV - TIMELINE -->
                        <div class="quotation-section">
                            <h2>VII - TIMELINE</h2>
                            <p style="font-size: 13px; color: #374151;">
                                AHT can complete it within <strong id="quote-total-days">...</strong> working days on the development site. It does not include time for testing and fixing bugs after UAT.<br><br>
                                The maximum time for UAT is <strong>01 month</strong>. The time for testing and fixing bugs after UAT might take <strong>... working days</strong>, depending on the amount of feedback. This does not include time for waiting for feedback from the Client.<br><br>
                                Within <strong>1 month</strong> since AHT provides the completed pages on the development server, if the Client does not do the reviewing and provides feedback for the pages/functions, the project will be considered completed.
                            </p>
                        </div>

                        <!-- V - PAYMENT TERM -->
                        <div class="quotation-section">
                            <h2>VIII - PAYMENT TERM</h2>
                            <p style="font-size: 13px; color: #374151; font-weight: 600;">The payment is divided into 3 milestones:</p>
                            <ul style="font-size: 13px; color: #374151; padding-left: 20px; line-height: 1.8;">
                                <li><strong>First payment:</strong> 50% of the total Contract value shall be made before starting the work.</li>
                                <li><strong>Second payment:</strong> 40% of the total Contract value shall be made when the work is completed on the development server. The second payment shall be made within 7 days since invoice receipt.</li>
                                <li><strong>Final payment:</strong> 10% of the total Contract value shall be made when the work is completed on the live server, OR after 1.5 months since all the tasks/pages are completed on development/staging server, whichever comes first.</li>
                            </ul>
                        </div>

                        <!-- VI - WARRANTY -->
                        <div class="quotation-section">
                            <h2>IX - WARRANTY</h2>
                            <p style="font-size: 13px; color: #374151;">AHT will provide <strong>3 months of support</strong> after the tasks are implemented on the live site for free bugs or fixing any issues related to the listed tasks but not include:</p>
                            <ul style="font-size: 13px; color: #374151; padding-left: 20px; list-style-type: '- ';">
                                <li>Any changes or additional updates.</li>
                                <li>Any issues/errors caused by versions upgrade (both the CMS and add-ons, etc) that are not done by us.</li>
                                <li>Any issues/conflicts/errors caused by new features or extensions or updates on the site that are not added or integrated by us.</li>
                                <li>Server/hosting issues.</li>
                                <li>Issues related to content updates made from other sides, not from AHT.</li>
                            </ul>
                        </div>

                        <!-- VII - RIGHTS AND OBLIGATIONS -->
                        <div class="quotation-section">
                            <h2>X - RIGHTS AND OBLIGATIONS OF CLIENT AND AHT TECH JSC</h2>
                            <p style="font-size: 13px; color: #374151; font-weight: 600;">1. Rights and obligations of the Client</p>
                            <ul style="font-size: 13px; color: #374151; padding-left: 20px; list-style-type: '- ';">
                                <li>The Client has the right to know and monitor the progress of the development of the platform.</li>
                                <li>The Client shall pay the project expenses to AHT Tech JSC according to the Contract.</li>
                                <li>The Client has the right to urge AHT Tech JSC to complete the project development according to the prescribed time.</li>
                            </ul>
                            <p style="font-size: 13px; color: #374151; font-weight: 600; margin-top: 10px;">2. Rights and obligations of AHT Tech JSC</p>
                            <ul style="font-size: 13px; color: #374151; padding-left: 20px; list-style-type: '- ';">
                                <li>AHT Tech JSC should report the progress of its work to the Client via mail or Jira system every 2 days.</li>
                                <li>AHT Tech JSC shall complete the agreed tasks within the time stipulated in the Contract.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendBtn = document.getElementById('send-btn');
            const chatInput = document.getElementById('chat-input');
            const chatMessages = document.getElementById('chat-messages');
            const mentionDropdown = document.getElementById('mention-dropdown');
            let mentionStartIndex = -1;
            window.projectFilesList = []; // Array lưu danh sách file hiện tại

            window.formatPremiumSummaryTable = function(table) {

                const rows = table.querySelectorAll('tr');
                if (rows.length === 0 || rows[0].cells.length !== 2) return false;
                
                const hasSummaryKeywords = Array.from(rows).some(r => {
                    const txt = r.cells[0].textContent.toLowerCase();
                    return txt.includes('total time') || txt.includes('working days') || txt.includes('hourly rate');
                });
                
                if (!hasSummaryKeywords) return false;

                table.classList.add('summary-table-premium');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse'; // Chuyển về collapse để grid chuẩn hơn
                table.style.marginTop = '15px';
                table.style.border = '1px solid #e2e8f0';
                
                const currentRate = parseFloat(document.getElementById('hourly-rate-select')?.value) || 15;

                rows.forEach((row, idx) => {
                    const labelCell = row.cells[0];
                    const valueCell = row.cells[1];
                    const labelText = labelCell.textContent.toLowerCase();
                    
                    labelCell.style.padding = '12px 15px';
                    labelCell.style.fontSize = '13px';
                    labelCell.style.border = '1px solid #e2e8f0'; // Thêm border cho mọi ô
                    
                    valueCell.style.padding = '12px 15px';
                    valueCell.style.textAlign = 'right';
                    valueCell.style.fontSize = '14px';
                    valueCell.style.fontWeight = '600';
                    valueCell.style.border = '1px solid #e2e8f0'; // Thêm border cho mọi ô

                    // 1. Header Row
                    if (idx === 0 && (labelText.includes('item') || labelText.includes('summary') || labelText.includes('hạng mục'))) {
                        row.style.background = '#1e293b';
                        labelCell.style.color = '#ffffff';
                        labelCell.style.background = '#1e293b';
                        labelCell.style.fontWeight = '700';
                        labelCell.style.textTransform = 'uppercase';
                        labelCell.style.fontSize = '11px';
                        labelCell.style.border = '1px solid #1e293b';
                        
                        valueCell.style.color = '#ffffff';
                        valueCell.style.background = '#1e293b';
                        valueCell.style.border = '1px solid #1e293b';
                        return;
                    }

                    // 2. Highlight hàng Tổng cộng cuối cùng (Light Style)
                    if (labelText.includes('total project cost') || labelText.includes('total cost (usd)')) {
                        row.style.background = '#ffffff';
                        labelCell.style.color = '#1e293b';
                        labelCell.style.background = '#ffffff';
                        labelCell.style.fontWeight = '700';
                        labelCell.style.borderTop = '2px solid #1e293b';
                        labelCell.style.borderBottom = '1px solid #e2e8f0';
                        
                        valueCell.style.color = '#000000'; // Đổi sang màu Đen theo yêu cầu
                        valueCell.style.background = '#ffffff';
                        valueCell.style.fontSize = '20px'; 
                        valueCell.style.fontWeight = '800';
                        valueCell.style.borderTop = '2px solid #1e293b';
                        valueCell.style.borderBottom = '1px solid #e2e8f0';


                        if (!valueCell.textContent.includes('$')) {
                            valueCell.textContent = '$' + valueCell.textContent;
                        }
                    } else {
                        // Hàng bình thường
                        labelCell.style.color = '#1e293b';
                        labelCell.style.background = (idx % 2 === 0) ? '#f8fafc' : '#ffffff';
                        valueCell.style.color = '#1e293b';
                        valueCell.style.background = (idx % 2 === 0) ? '#f8fafc' : '#ffffff';
                    }

                    if (labelText.includes('hourly rate')) {
                        valueCell.textContent = '$' + currentRate;
                    }
                });
            };





            // Logic xử lý tag tên file (@mention)
            chatInput.addEventListener('input', function(e) {
                const cursorPosition = this.selectionStart;
                const textBeforeCursor = this.value.substring(0, cursorPosition);
                
                // Tìm ký tự @ gần nhất trước con trỏ
                const lastAtSymbol = textBeforeCursor.lastIndexOf('@');
                
                if (lastAtSymbol !== -1 && (lastAtSymbol === 0 || textBeforeCursor[lastAtSymbol - 1] === ' ' || textBeforeCursor[lastAtSymbol - 1] === '\n')) {
                    const query = textBeforeCursor.substring(lastAtSymbol + 1);
                    
                    // Cho phép chữ, số, khoảng trắng, dấu gạch ngang, dấu chấm...
                    if (/^[\w\s.-]*$/.test(query)) {
                        mentionStartIndex = lastAtSymbol;
                        showMentionDropdown(query);
                        return;
                    }
                }
                
                hideMentionDropdown();
            });

            function showMentionDropdown(query) {
                if (!window.projectFilesList || window.projectFilesList.length === 0) {
                    hideMentionDropdown();
                    return;
                }

                // Lọc các file theo từ khóa
                const filteredFiles = window.projectFilesList.filter(f => f.file_name.toLowerCase().includes(query.toLowerCase()));
                
                if (filteredFiles.length === 0) {
                    hideMentionDropdown();
                    return;
                }

                mentionDropdown.innerHTML = '';
                filteredFiles.forEach((f, index) => {
                    const item = document.createElement('div');
                    item.className = 'mention-item' + (index === 0 ? ' active' : '');
                    item.innerHTML = `📄 <span>${f.file_name}</span>`;
                    
                    item.addEventListener('click', () => {
                        insertMention(f.file_name);
                    });
                    
                    mentionDropdown.appendChild(item);
                });
                
                mentionDropdown.style.display = 'block';
            }

            function hideMentionDropdown() {
                mentionDropdown.style.display = 'none';
                mentionStartIndex = -1;
            }

            function insertMention(fileName) {
                if (mentionStartIndex === -1) return;
                
                const before = chatInput.value.substring(0, mentionStartIndex);
                const after = chatInput.value.substring(chatInput.selectionStart);
                
                // Chèn @tên_file và thêm dấu cách
                const insertText = `@${fileName} `;
                
                chatInput.value = before + insertText + after;
                
                // Đưa con trỏ chuột về đúng vị trí sau khi chèn
                const newCursorPos = mentionStartIndex + insertText.length;
                chatInput.focus();
                chatInput.setSelectionRange(newCursorPos, newCursorPos);
                
                hideMentionDropdown();
            }

            // Xử lý phím mũi tên và phím Enter trong dropdown
            chatInput.addEventListener('keydown', function(e) {
                if (mentionDropdown.style.display === 'block') {
                    const items = mentionDropdown.querySelectorAll('.mention-item');
                    let activeIndex = Array.from(items).findIndex(item => item.classList.contains('active'));
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (activeIndex < items.length - 1) {
                            items[activeIndex]?.classList.remove('active');
                            items[activeIndex + 1].classList.add('active');
                            items[activeIndex + 1].scrollIntoView({block: 'nearest'});
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (activeIndex > 0) {
                            items[activeIndex]?.classList.remove('active');
                            items[activeIndex - 1].classList.add('active');
                            items[activeIndex - 1].scrollIntoView({block: 'nearest'});
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeIndex !== -1) {
                            items[activeIndex].click();
                        }
                    } else if (e.key === 'Escape') {
                        hideMentionDropdown();
                    }
                }
            });
            
            function addMessage(content, role) {
                const div = document.createElement('div');
                div.className = `message ${role}`;
                
                if (role === 'assistant') {
                    div.innerHTML = marked.parse(content);
                    
                    if (div.querySelector('table')) {
                        const tables = div.querySelectorAll('table');
                        tables.forEach(table => {
                            if (window.formatPremiumSummaryTable) {
                                window.formatPremiumSummaryTable(table);
                            }
                        });

                        const exportBtn = document.createElement('button');
                        exportBtn.innerHTML = '📥 Tải xuống Excel (.xlsx)';
                        exportBtn.style.cssText = 'margin-top: 10px; padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;';
                        exportBtn.onclick = function() {
                            const wb = XLSX.utils.book_new();
                            const tables = div.querySelectorAll('table');
                            tables.forEach((table, index) => {
                                const ws = XLSX.utils.table_to_sheet(table);
                                let sheetName = `Table ${index + 1}`;
                                if (index === 0) sheetName = "Development Scope";
                                if (index === 1) sheetName = "Estimation";
                                if (index === 2) sheetName = "Summary";
                                XLSX.utils.book_append_sheet(wb, ws, sheetName);
                            });
                            XLSX.writeFile(wb, "AHT_Development_Scope_Estimation.xlsx");
                        };
                        div.appendChild(exportBtn);

                        // THÊM: Nút xem bản báo giá chuyên nghiệp
                        const viewQuoteBtn = document.createElement('button');
                        viewQuoteBtn.innerHTML = '📄 Xem bản báo giá chuyên nghiệp';
                        viewQuoteBtn.style.cssText = 'margin-top: 10px; margin-left: 8px; padding: 6px 12px; background: #4f46e5; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;';
                        viewQuoteBtn.onclick = function() {
                            showQuotation(content);
                        };
                        div.appendChild(viewQuoteBtn);
                    }
                } else {
                    div.textContent = content;
                }
                
                chatMessages.appendChild(div);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            window.showQuotation = function(aiContent) {
                const modal = document.getElementById('quotation-modal');
                const container = document.getElementById('quote-sections-container');
                const coverClient = document.getElementById('quote-client-name-cover');
                
                // Update Cover Page
                let projectTitle = document.getElementById('current-project-title').textContent || "Valued Customer";
                // Loại bỏ icon thư mục nếu có
                projectTitle = projectTitle.replace(/📁\s*/, '').trim();
                coverClient.textContent = projectTitle;
                
                const tempDiv = document.createElement('div');
                // Loại bỏ các đoạn văn không cần thiết ở đầu (ví dụ: "Dưới đây là báo giá...")
                const cleanContent = aiContent.replace(/^(Dưới đây|Sau đây|Đây là).*\n+/i, '');
                tempDiv.innerHTML = marked.parse(cleanContent);
                container.innerHTML = '';
                
                let currentSection = null;
                Array.from(tempDiv.children).forEach(child => {
                    // Apply classes to tables
                    if (child.tagName === 'TABLE') {
                        child.classList.add('quote-table');
                        if (window.formatPremiumSummaryTable) {
                            window.formatPremiumSummaryTable(child);
                        }
                    }

                    // Tự động bóc tách h1, h2, h3 thành Section chuyên nghiệp
                    if (child.tagName === 'H1' || child.tagName === 'H2' || child.tagName === 'H3') {
                        currentSection = document.createElement('div');
                        currentSection.className = 'quotation-section';
                        const h2 = document.createElement('h2');
                        h2.textContent = child.textContent.replace(/^\d+\.\s*/, ''); // Xoá số thứ tự nếu AI đã ghi
                        currentSection.appendChild(h2);
                        container.appendChild(currentSection);
                    } else if (currentSection) {
                        currentSection.appendChild(child.cloneNode(true));
                    } else {
                        // Intro section
                        const introSection = document.createElement('div');
                        introSection.className = 'quotation-section';
                        introSection.appendChild(child.cloneNode(true));
                        container.appendChild(introSection);
                    }
                });
                
                // Cập nhật Timeline nếu tìm thấy số ngày làm việc
                const allText = tempDiv.textContent;
                const daysMatch = allText.match(/(\d+)\s*(working days|ngày làm việc)/i);
                if (daysMatch) {
                    const quoteDaysEl = document.getElementById('quote-total-days');
                    if (quoteDaysEl) quoteDaysEl.textContent = daysMatch[1];
                }

                modal.style.display = 'flex';
            };

            window.closeQuoteModal = function() {
                document.getElementById('quotation-modal').style.display = 'none';
            };

            let currentActionKey = 'qna';
            let currentProjectId = null;
            let currentSessionId = null; // Quản lý chat session


            // Tự động mở dự án đang active khi load trang
            const savedProjectId = localStorage.getItem('presale_active_project');
            if (savedProjectId) {
                currentProjectId = savedProjectId; // Khôi phục ID dự án đang active
                const header = document.querySelector(`.project-item[data-id="${savedProjectId}"] .project-header`);
                if (header) {
                    const chatList = document.getElementById('chat-list-' + savedProjectId);
                    if (chatList) {
                        chatList.style.display = 'block';
                        header.classList.add('active');
                    }
                }
            }

            // --- KHÔI PHỤC CÁC EVENT LISTENERS ---
            const attachBtn = document.getElementById('attach-btn');
            const fileUpload = document.getElementById('file-upload');
            const attachmentPreview = document.getElementById('attachment-preview');
            const attachmentName = document.getElementById('attachment-name');
            const removeAttachment = document.getElementById('remove-attachment');
            let selectedFile = null;

            if (attachBtn) attachBtn.addEventListener('click', () => fileUpload.click());

            if (fileUpload) fileUpload.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    selectedFile = this.files[0];
                    attachmentName.textContent = selectedFile.name;
                    attachmentPreview.style.display = 'flex';
                }
            });

            if (removeAttachment) removeAttachment.addEventListener('click', function() {
                selectedFile = null;
                fileUpload.value = '';
                attachmentPreview.style.display = 'none';
            });

            window.createNewProject = function() {
                const name = prompt("Nhập tên dự án mới:");
                if (!name) return;
                
                const fd = new FormData();
                fd.append('action', 'create_project');
                fd.append('name', name);
                
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        if (data.project_id) localStorage.setItem('presale_active_project', data.project_id);
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            };
            // ------------------------------------

            window.toggleProject = function(projectId, el) {
                const chatList = document.getElementById('chat-list-' + projectId);
                const isAlreadyOpen = chatList.style.display === 'block';
                
                document.querySelectorAll('.chat-list').forEach(list => list.style.display = 'none');
                document.querySelectorAll('.project-header').forEach(header => header.classList.remove('active'));
                
                if (!isAlreadyOpen) {
                    chatList.style.display = 'block';
                    el.classList.add('active');
                    localStorage.setItem('presale_active_project', projectId);
                } else {
                    localStorage.removeItem('presale_active_project');
                }
            };

            window.createNewChat = function(projectId) {
                localStorage.setItem('presale_active_project', projectId);
                const fd = new FormData();
                fd.append('action', 'create_chat');
                fd.append('project_id', projectId);
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
            };

            window.deleteChat = function(e, sessionId) {
                e.stopPropagation();
                if(!confirm('Bạn có chắc chắn muốn xoá lịch sử chat này không?')) return;
                
                const fd = new FormData();
                fd.append('action', 'delete_chat');
                fd.append('session_id', sessionId);
                
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            };

            window.deleteProject = function(e, id) {
                e.stopPropagation();
                if(!confirm('Bạn có chắc chắn muốn xoá dự án này cùng toàn bộ file và lịch sử chat không?')) return;
                
                const fd = new FormData();
                fd.append('action', 'delete_project');
                fd.append('project_id', id);
                
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            };

            window.shareProject = function() {
                if(!currentProjectId) return alert('Vui lòng chọn một dự án!');
                const fd = new FormData();
                fd.append('action', 'get_users');
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success && data.users.length > 0) {
                        let msg = "Nhập ID của User bạn muốn chia sẻ:\n\n";
                        data.users.forEach(u => msg += `[ID: ${u.id}] - ${u.username}\n`);
                        const targetId = prompt(msg);
                        if(targetId && !isNaN(targetId)) {
                            const fdShare = new FormData();
                            fdShare.append('action', 'share_project');
                            fdShare.append('project_id', currentProjectId);
                            fdShare.append('user_id', targetId);
                            fetch('/presale/ajax-handler', { method: 'POST', body: fdShare })
                            .then(r => r.json())
                            .then(shareData => {
                                if(shareData.success) alert("Đã chia sẻ thành công!");
                                else alert(shareData.message || "Lỗi khi chia sẻ");
                            });
                        }
                    } else {
                        alert("Không lấy được danh sách User");
                    }
                });
            };

            // Xử lý upload tài liệu dự án
            document.getElementById('project-file-upload').addEventListener('change', function() {
                if(!currentProjectId) return alert('Vui lòng chọn một dự án trước!');
                if(!this.files.length) return;
                
                const file = this.files[0];
                const fd = new FormData();
                fd.append('action', 'upload_project_file');
                fd.append('project_id', currentProjectId);
                fd.append('file', file);
                
                const btn = this.nextElementSibling;
                const oldText = btn.innerHTML;
                btn.innerHTML = 'Đang tải...';
                
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.innerHTML = oldText;
                    if(data.success) {
                        loadProjectFiles(currentProjectId);
                    } else {
                        alert(data.message);
                    }
                });
            });

            function loadProjectFiles(projectId) {
                const fd = new FormData();
                fd.append('action', 'get_project_files');
                fd.append('project_id', projectId);
                
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        window.projectFilesList = data.files; // Lưu vào biến global
                        const list = document.getElementById('project-files-list');
                        list.innerHTML = '';
                        data.files.forEach(f => {
                            const tag = document.createElement('div');
                            tag.style.cssText = 'padding: 4px 10px; background: #e0e7ff; color: #3730a3; border-radius: 4px; font-size: 12px; display: flex; align-items: center; gap: 6px;';
                            tag.innerHTML = `<span style="cursor:pointer;" onclick="previewFile(${f.id})">📄 ${f.file_name}</span> <span style="cursor:pointer; color:#ef4444;" onclick="deleteProjectFile(${f.id})">&times;</span>`;
                            list.appendChild(tag);
                        });
                    }
                });
            }

            window.previewFile = function(fileId) {
                const fd = new FormData();
                fd.append('action', 'get_file_content');
                fd.append('file_id', fileId);
                
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById('preview-filename').textContent = data.file_name;
                        document.getElementById('preview-content').textContent = data.content;
                        document.getElementById('file-preview-modal').style.display = 'block';
                    }
                });
            };

            window.closeModal = function() {
                document.getElementById('file-preview-modal').style.display = 'none';
            };

            // Drag & Drop Support
            const chatArea = document.querySelector('.chat-area');
            chatArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                chatArea.classList.add('dragover');
            });
            chatArea.addEventListener('dragleave', () => {
                chatArea.classList.remove('dragover');
            });
            chatArea.addEventListener('drop', (e) => {
                e.preventDefault();
                chatArea.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    fileUpload.files = e.dataTransfer.files;
                    fileUpload.dispatchEvent(new Event('change'));
                }
            });

            window.deleteProjectFile = function(fileId) {
                if(!confirm('Xoá tài liệu này?')) return;
                const fd = new FormData();
                fd.append('action', 'delete_project_file');
                fd.append('file_id', fileId);
                fetch('/presale/ajax-handler', { method: 'POST', body: fd })
                .then(() => loadProjectFiles(currentProjectId));
            };

            // Toggle Dark Mode
            const darkModeBtn = document.getElementById('dark-mode-toggle');
            if (darkModeBtn) {
                darkModeBtn.addEventListener('click', () => {
                    document.body.classList.toggle('dark-mode');
                    localStorage.setItem('presale_dark_mode', document.body.classList.contains('dark-mode'));
                });
                if (localStorage.getItem('presale_dark_mode') === 'true') {
                    document.body.classList.add('dark-mode');
                }
            }

            // Search Projects
            const projectSearch = document.getElementById('project-search');
            if (projectSearch) {
                projectSearch.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    document.querySelectorAll('.project-item').forEach(item => {
                        const title = item.querySelector('.project-title').textContent.toLowerCase();
                        item.style.display = title.includes(term) ? 'block' : 'none';
                    });
                });
            }

            async function sendMessage() {
                const text = chatInput.value.trim();
                if (!text && !selectedFile) return;

                // Add user message to UI
                let displayMsg = text;
                if (selectedFile) displayMsg = "📎 Đã gửi tệp: " + selectedFile.name + "\n" + displayMsg;
                addMessage(displayMsg, 'user');
                
                chatInput.value = '';
                chatInput.style.height = '48px';

                // Show typing indicator
                const assistantDiv = document.createElement('div');
                assistantDiv.className = 'message assistant';
                assistantDiv.innerHTML = '<span>AI đang suy nghĩ...</span>';
                chatMessages.appendChild(assistantDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;

                try {
                    const formData = new FormData();
                    const platform = document.getElementById('platform-select')?.value || '';
                    const projectType = document.getElementById('project-type-select')?.value || '';
                    const hourlyRate = document.getElementById('hourly-rate-select')?.value || '15';
                    const budget = document.getElementById('budget-input')?.value || '0';
                    const customerName = document.getElementById('customer-select')?.value || '';
                    const docType = document.getElementById('doc-type-select')?.value || '';
                    const figmaSummary = document.getElementById('figma-summary-input')?.value || '';
                    const visionSummary = document.getElementById('vision-summary-input')?.value || '';

                    formData.append('action', 'send_message');
                    formData.append('content', text);
                    formData.append('action_key', currentActionKey);
                    formData.append('stream', 'true');
                    
                    formData.append('platform', platform);
                    formData.append('project_type', projectType);
                    formData.append('doc_type', docType);
                    formData.append('figma_summary', figmaSummary);
                    formData.append('vision_summary', visionSummary);
                    formData.append('hourly_rate', hourlyRate);
                    formData.append('budget', budget);
                    formData.append('customer_name', customerName);
                    
                    if (currentProjectId) formData.append('project_id', currentProjectId);
                    if (currentSessionId) formData.append('session_id', currentSessionId);
                    if (selectedFile) formData.append('file', selectedFile);

                    const response = await fetch('/presale/ajax-handler', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let fullReply = "";
                    assistantDiv.innerHTML = ""; // Clear typing indicator

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;
                        
                        const chunk = decoder.decode(value, { stream: true });
                        const lines = chunk.split('\n');
                        
                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const dataStr = line.slice(6).trim();
                                if (dataStr === '[DONE]') continue;
                                
                                try {
                                    const data = JSON.parse(dataStr);
                                    if (data.text) {
                                        fullReply += data.text;
                                        assistantDiv.innerHTML = marked.parse(fullReply);
                                        chatMessages.scrollTop = chatMessages.scrollHeight;
                                    }
                                } catch (e) { console.error("JSON parse error in stream", e); }
                            }
                        }
                    }
                    
                    // Format Premium cho các bảng (nếu có)
                    const allTables = assistantDiv.querySelectorAll('table');
                    allTables.forEach(t => {
                        if (window.formatPremiumSummaryTable) {
                            window.formatPremiumSummaryTable(t);
                        }
                    });

                    // Nút Export Excel nếu có table
                    if (allTables.length > 0) {
                        const exportBtn = document.createElement('button');
                        exportBtn.innerHTML = '📥 Tải xuống Excel (.xlsx)';
                        exportBtn.style.cssText = 'margin-top: 10px; padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;';
                        exportBtn.onclick = function() {
                            const wb = XLSX.utils.book_new();
                            const tables = assistantDiv.querySelectorAll('table');
                            tables.forEach((table, index) => {
                                const ws = XLSX.utils.table_to_sheet(table);
                                XLSX.utils.book_append_sheet(wb, ws, `Table ${index + 1}`);
                            });
                            XLSX.writeFile(wb, "AI_Presale_Export.xlsx");
                        };
                        assistantDiv.appendChild(exportBtn);
                    }

                } catch (err) {
                    assistantDiv.innerHTML = 'Lỗi kết nối máy chủ hoặc AI không hỗ trợ Stream.';
                    console.error(err);
                } finally {
                    currentActionKey = 'qna';
                    selectedFile = null;
                    fileUpload.value = '';
                    attachmentPreview.style.display = 'none';
                }
            }

            sendBtn.addEventListener('click', sendMessage);
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Auto-resize textarea
            chatInput.addEventListener('input', function() {
                this.style.height = '48px';
                if (this.scrollHeight > 48 && this.scrollHeight < 150) {
                    this.style.height = this.scrollHeight + 'px';
                } else if (this.scrollHeight >= 150) {
                    this.style.height = '150px';
                }
            });

            // Quick actions
            document.querySelectorAll('.quick-action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    if (action) {
                        currentActionKey = action;
                        chatInput.value = "Tôi muốn " + this.textContent.trim() + ": \n";
                        chatInput.focus();
                    }
                });
            });

            // Voice Recognition
            const voiceBtn = document.getElementById('voice-btn');
            let recognition = null;
            let isRecording = false;

            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                recognition = new SpeechRecognition();
                recognition.continuous = true;
                recognition.interimResults = true;
                recognition.lang = 'vi-VN'; // Mặc định tiếng Việt

                recognition.onresult = (event) => {
                    let transcript = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        transcript += event.results[i][0].transcript;
                    }
                    chatInput.value = transcript;
                    chatInput.dispatchEvent(new Event('input')); // Trigger auto-resize
                };

                recognition.onerror = (event) => {
                    console.error('Speech recognition error:', event.error);
                    stopRecording();
                };

                recognition.onend = () => {
                    if (isRecording) stopRecording();
                };
            } else {
                voiceBtn.style.display = 'none';
                console.warn('Speech recognition not supported in this browser.');
            }

            function startRecording() {
                if (!recognition) return;
                isRecording = true;
                voiceBtn.classList.add('recording');
                voiceBtn.title = "Đang nghe... Nhấp để dừng";
                recognition.start();
            }

            function stopRecording() {
                isRecording = false;
                voiceBtn.classList.remove('recording');
                voiceBtn.title = "Nhập liệu bằng giọng nói";
                if (recognition) recognition.stop();
            }

            voiceBtn.addEventListener('click', () => {
                if (isRecording) {
                    stopRecording();
                } else {
                    startRecording();
                }
            });

            window.openChat = function(el, projectId, sessionId) {
                const projectTitle = el.getAttribute('data-title');
                document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
                el.classList.add('active');

                currentProjectId = projectId;
                currentSessionId = sessionId;
                localStorage.setItem('presale_active_project', projectId); // Đảm bảo ghi nhớ dự án đang xem chat
                document.getElementById('current-project-title').textContent = projectTitle;
                document.getElementById('project-workspace-header').style.display = 'block';
                loadProjectFiles(projectId);

                chatMessages.innerHTML = '<div style="text-align:center; padding: 20px; color:#64748b;">Đang tải dữ liệu...</div>';
                
                const formData = new FormData();
                formData.append('action', 'load_history');
                formData.append('session_id', sessionId);

                fetch('/presale/ajax-handler', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    chatMessages.innerHTML = '';
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(msg => addMessage(msg.content, msg.role));
                    } else {
                        chatMessages.innerHTML = '<div style="text-align:center; padding: 20px; color:#64748b;">Chưa có tin nhắn nào.</div>';
                    }
                })
                .catch(err => {
                    chatMessages.innerHTML = '<div style="text-align:center; padding: 20px; color:#ef4444;">Lỗi kết nối.</div>';
                    console.error(err);
                });
            };

            window.handleNewChat = function() {
                if (currentProjectId) {
                    createNewChat(currentProjectId);
                } else {
                    createNewProject();
                }
            };

            // Nút New Chat (Tổng quát ngoài Project)
            document.getElementById('new-chat-btn').addEventListener('click', function() {
                handleNewChat();
            });
            // Settings Sidebar Toggle
            window.toggleSettingsSidebar = function() {
                const sidebar = document.getElementById('settingsSidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            };

            // Custom Customer Search Logic
            const customerSearchInput = document.getElementById('customer-search-input');
            const customerDropdown = document.getElementById('customer-dropdown');
            const customerHiddenInput = document.getElementById('customer-select');

            if (customerSearchInput) {
                customerSearchInput.addEventListener('focus', () => {
                    renderCustomerDropdown(customerSearchInput.value);
                });

                customerSearchInput.addEventListener('input', (e) => {
                    renderCustomerDropdown(e.target.value);
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!customerSearchInput.contains(e.target) && !customerDropdown.contains(e.target)) {
                        customerDropdown.style.display = 'none';
                    }
                });
            }

            window.renderCustomerDropdown = function(query) {
                const term = query.toLowerCase().trim();
                const filtered = allCustomers.filter(c => c.name.toLowerCase().includes(term)).slice(0, 50);
                
                customerDropdown.innerHTML = '';
                
                if (filtered.length > 0) {
                    filtered.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'customer-option';
                        div.textContent = c.name;
                        div.onclick = () => {
                            customerSearchInput.value = c.name;
                            customerHiddenInput.value = c.name;
                            customerDropdown.style.display = 'none';
                        };
                        customerDropdown.appendChild(div);
                    });
                } else if (term.length > 0) {
                    const div = document.createElement('div');
                    div.className = 'customer-option no-results';
                    div.textContent = 'Không tìm thấy khách hàng. Nhấn Enter để dùng tên này.';
                    customerDropdown.appendChild(div);
                }
                
                customerDropdown.style.display = filtered.length > 0 || term.length > 0 ? 'block' : 'none';
            };

            if (customerSearchInput) {
                customerSearchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        const firstOption = customerDropdown.querySelector('.customer-option:not(.no-results)');
                        if (firstOption) {
                            firstOption.click();
                        } else {
                            customerHiddenInput.value = customerSearchInput.value;
                            customerDropdown.style.display = 'none';
                        }
                    }
                });
            }

            window.allCustomers = <?php echo json_encode($customers); ?>;

            window.fetchFigmaData = async function() {
                const url = document.getElementById('figma-url').value;
                const token = document.getElementById('figma-token').value;
                const status = document.getElementById('figma-status');
                const btn = document.getElementById('btn-fetch-figma');

                if (!url || !token) return alert('Vui lòng nhập đầy đủ URL và Token!');

                status.style.display = 'block';
                status.innerHTML = '⏳ Đang kết nối Figma API...';
                btn.disabled = true;

                try {
                    const fd = new FormData();
                    fd.append('action', 'fetch_figma_data');
                    fd.append('figma_url', url);
                    fd.append('figma_token', token);

                    const response = await fetch('/presale/ajax-handler', {
                        method: 'POST',
                        body: fd
                    });
                    const res = await response.json();

                    if (res.success) {
                        let totalScreens = 0;
                        res.data.pages.forEach(p => totalScreens += p.screens.length);
                        
                        status.innerHTML = `✅ Thành công: Tìm thấy <b>${res.data.pages.length}</b> trang, <b>${totalScreens}</b> màn hình.`;
                        document.getElementById('figma-summary-input').value = JSON.stringify(res.data);
                        
                        // Hiển thị danh sách chi tiết để người dùng xem
                        const preview = document.getElementById('figma-preview-list');
                        preview.style.display = 'block';
                        let html = '<div style="font-weight: 600; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">Chi tiết thiết kế:</div>';
                        res.data.pages.forEach(p => {
                            html += `<div style="color: #4f46e5; font-weight: 600; font-size: 11px; margin-top: 8px;">📄 ${p.name}</div>`;
                            p.screens.forEach(s => {
                                html += `<div style="padding-left: 10px; margin-top: 4px;">• <b>${s.name}</b></div>`;
                                if (s.sections && s.sections.length > 0) {
                                    html += `<div style="padding-left: 20px; font-size: 11px; color: #64748b;">└ ${s.sections.join(', ')}</div>`;
                                }
                            });
                        });
                        preview.innerHTML = html;
                        
                        // Thông báo vào ô chat để người dùng biết
                        chatInput.value = `Dữ liệu thiết kế Figma đã được tải: "${res.data.name}". Có ${res.data.pages.length} trang và ${totalScreens} màn hình chi tiết. Hãy lập SOW dựa trên cấu trúc này.`;
                        chatInput.focus();
                    } else {
                        status.innerHTML = `❌ Lỗi: ${res.message}`;
                    }
                } catch (e) {
                    status.innerHTML = '❌ Lỗi kết nối.';
                    console.error(e);
                } finally {
                    btn.disabled = false;
                }
            };

            window.analyzeMockupImage = async function() {
                const fileInput = document.getElementById('mockup-image-upload');
                const file = fileInput.files[0];
                const status = document.getElementById('vision-status');
                const btn = document.getElementById('btn-analyze-mockup');

                if (!file) return alert('Vui lòng chọn ảnh Mockup!');

                status.style.display = 'block';
                status.innerHTML = '⏳ Đang chạy YOLOv8 & OCR (Local)...';
                btn.disabled = true;

                try {
                    const fd = new FormData();
                    fd.append('action', 'analyze_design_image');
                    fd.append('mockup_image', file);

                    const response = await fetch('/presale/ajax-handler', {
                        method: 'POST',
                        body: fd
                    });
                    const res = await response.json();

                    if (res.success) {
                        status.innerHTML = `✅ Phân tích xong: Tìm thấy <b>${res.data.length}</b> khối nội dung.`;
                        document.getElementById('vision-summary-input').value = JSON.stringify(res.data);
                        
                        const preview = document.getElementById('vision-preview-list');
                        preview.style.display = 'block';
                        let html = '<div style="font-weight: 600; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">Kết quả Vision (Local):</div>';
                        res.data.forEach(v => {
                            html += `<div style="margin-top: 8px;">• <b>${v.name}</b></div>`;
                            html += `<div style="font-size: 11px; color: #64748b; padding-left: 10px;">${v.summary}</div>`;
                        });
                        preview.innerHTML = html;

                        chatInput.value = `Đã phân tích ảnh Mockup: Tìm thấy ${res.data.length} khối chức năng. Hãy lập SOW dựa trên các thành phần này.`;
                        chatInput.focus();
                    } else {
                        status.innerHTML = `❌ Lỗi: ${res.message}`;
                    }
                } catch (e) {
                    status.innerHTML = '❌ Lỗi kết nối Python.';
                    console.error(e);
                } finally {
                    btn.disabled = false;
                }
            };
        });
    </script>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSettingsSidebar()"></div>

    <!-- Project Settings Sidebar -->
    <div class="settings-sidebar" id="settingsSidebar">
        <div class="settings-sidebar-header">
            <h3 style="margin: 0; font-size: 18px; color: #0f172a;">Thiết lập Dự án</h3>
            <button onclick="toggleSettingsSidebar()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>
        <div class="settings-sidebar-body">
            <!-- Client Selection -->
            <div class="settings-group">
                <label>Khách hàng (Client)</label>
                <div class="customer-search-container">
                    <input type="text" id="customer-search-input" class="settings-input" placeholder="Tìm kiếm hoặc nhập tên khách hàng..." autocomplete="off">
                    <input type="hidden" id="customer-select">
                    <div id="customer-dropdown" class="customer-dropdown"></div>
                </div>
            </div>

            <!-- Platform Selection -->
            <div class="settings-group">
                <label>Nền tảng (Platform)</label>
                <select id="platform-select" class="settings-input">
                    <option value="Shopify">Shopify</option>
                    <option value="Magento">Magento</option>
                    <option value="ReactJS">ReactJS</option>
                    <option value="PHP/Laravel">PHP/Laravel</option>
                    <option value="WordPress">WordPress</option>
                    <option value="Mobile App">Mobile App</option>
                    <option value="Other">Khác</option>
                </select>
            </div>

            <!-- Project Type Selection -->
            <div class="settings-group">
                <label>Loại hình dự án (Project Type)</label>
                <select id="project-type-select" class="settings-input">
                    <option value="New Build">New Build</option>
                    <option value="Enhancement">Enhancement</option>
                    <option value="Migration">Migration</option>
                    <option value="Consulting">Consulting</option>
                </select>
            </div>

            <!-- Hourly Rate -->
            <div class="settings-group">
                <label>Đơn giá (Hourly Rate - USD)</label>
                <select id="hourly-rate-select" class="settings-input">
                    <option value="15">15 $ / h</option>
                    <option value="20">20 $ / h</option>
                    <option value="25">25 $ / h</option>
                    <option value="30">30 $ / h</option>
                    <option value="35">35 $ / h</option>
                    <option value="40">40 $ / h</option>
                </select>
            </div>

            <!-- Figma Integration -->
            <div class="settings-group" style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 15px;">
                <label style="color: #4f46e5; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                    <svg width="14" height="14" viewBox="0 0 38 57" fill="none"><path d="M19 28.5C19 25.8478 20.0536 23.3043 21.9289 21.4289C23.8043 19.5536 26.3478 18.5 29 18.5C31.6522 18.5 34.1957 19.5536 36.0711 21.4289C37.9464 23.3043 39 25.8478 39 28.5C39 31.1522 37.9464 33.6957 36.0711 35.5711C34.1957 37.4464 31.6522 38.5 29 38.5C26.3478 38.5 23.8043 37.4464 21.9289 35.5711C20.0536 33.6957 19 31.1522 19 28.5Z" fill="#1ABCFE"/><path d="M0 47.5C0 44.8478 1.05357 42.3043 2.92893 40.4289C4.8043 38.5536 7.34784 37.5 10 37.5C12.6522 37.5 15.1957 38.5536 17.0711 40.4289C18.9464 42.3043 20 44.8478 20 47.5C20 50.1522 18.9464 52.6957 17.0711 54.5711C15.1957 56.4464 12.6522 57.5 10 57.5C7.34784 57.5 4.8043 56.4464 2.92893 54.5711C1.05357 52.6957 0 50.1522 0 47.5Z" fill="#0ACF83"/><path d="M0 28.5C0 25.8478 1.05357 23.3043 2.92893 21.4289C4.8043 19.5536 7.34784 18.5 10 18.5H19V38.5H10C7.34784 38.5 4.8043 37.4464 2.92893 35.5711C1.05357 33.6957 0 31.1522 0 28.5Z" fill="#A259FF"/><path d="M0 9.5C0 6.84784 1.05357 4.3043 2.92893 2.42893C4.8043 0.553571 7.34784 -4.76837e-07 10 0H19V19H10C7.34784 19 4.8043 18.4464 2.92893 16.5711C1.05357 14.6957 0 12.1522 0 9.5Z" fill="#F24E1E"/><path d="M19 0H28C30.6522 0 33.1957 1.05357 35.0711 2.92893C36.9464 4.8043 38 7.34784 38 9.5C38 12.1522 36.9464 14.6957 35.0711 16.5711C33.1957 18.4464 30.6522 19 28 19H19V0Z" fill="#FF7262"/></svg>
                    Nhập dữ liệu từ Figma
                </label>
                <input type="text" id="figma-url" class="settings-input" placeholder="URL file Figma..." style="margin-top: 8px;">
                <input type="password" id="figma-token" class="settings-input" placeholder="Personal Access Token..." style="margin-top: 8px;">
                <button onclick="fetchFigmaData()" id="btn-fetch-figma" class="send-btn" style="width: 100%; margin-top: 8px; height: 36px; background: #000;">Phân tích thiết kế</button>
                <div id="figma-status" style="margin-top: 8px; font-size: 12px; color: #64748b; display: none;"></div>
                <div id="figma-preview-list" style="margin-top: 12px; font-size: 12px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; max-height: 300px; overflow-y: auto; display: none;"></div>
                <input type="hidden" id="figma-summary-input">
            </div>

            <!-- Vision Analysis (Local Mockup) -->
            <div class="settings-group" style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 15px;">
                <label style="color: #10b981; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                    🖼️ Phân tích ảnh Mockup (Local)
                </label>
                <input type="file" id="mockup-image-upload" class="settings-input" accept="image/*" style="margin-top: 8px;">
                <button onclick="analyzeMockupImage()" id="btn-analyze-mockup" class="send-btn" style="width: 100%; margin-top: 8px; height: 36px; background: #10b981;">Phân tích bằng YOLOv8</button>
                <div id="vision-status" style="margin-top: 8px; font-size: 12px; color: #64748b; display: none;"></div>
                <div id="vision-preview-list" style="margin-top: 12px; font-size: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px; border-radius: 6px; max-height: 300px; overflow-y: auto; display: none;"></div>
                <input type="hidden" id="vision-summary-input">
            </div>

            <!-- Target Budget -->
            <div class="settings-group">
                <label>Ngân sách dự kiến (Target Budget - USD)</label>
                <div style="position: relative;">
                    <input type="number" id="budget-input" class="settings-input" placeholder="0" min="0">
                    <span style="position: absolute; right: 12px; top: 10px; color: #94a3b8;">$</span>
                </div>
            </div>

            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                <button class="send-btn" style="width: 100%; height: 44px; font-size: 15px;" onclick="toggleSettingsSidebar()">Lưu thiết lập</button>
            </div>
        </div>
    </div>

</body>
</html>
