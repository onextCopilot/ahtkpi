<?php
require_once __DIR__ . '/../../config/config.php';

// Module-specific Page Titles
$page_title = "Plan & Budgeting";
$page_subtitle = "Quản lý Kế hoạch & Ngân sách";

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');
$current_full_name = $_SESSION['full_name'];

// ACCESSIBILITY CHECK: Admin or Owner of at least one item
if (!$is_admin) {
    $stmt_check = $conn->prepare("SELECT COUNT(*) as cnt FROM budget_structure WHERE owner = ?");
    $stmt_check->bind_param("s", $current_full_name);
    $stmt_check->execute();
    $owner_count = $stmt_check->get_result()->fetch_assoc()['cnt'] ?? 0;
    if ($owner_count == 0) {
        header("Location: /dashboard");
        exit();
    }
}

// Ensure history table exists
$conn->query("CREATE TABLE IF NOT EXISTS budget_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    item_id INT DEFAULT NULL,
    item_name VARCHAR(255) DEFAULT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

function logBudgetAction($conn, $user_id, $user_name, $type, $item_id, $item_name, $details) {
    $stmt = $conn->prepare("INSERT INTO budget_history (user_id, user_name, action_type, item_id, item_name, details) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $user_name, $type, $item_id, $item_name, $details);
    $stmt->execute();
}

$current_year = intval($_GET['year'] ?? date('Y'));
$current_quarter = intval($_GET['quarter'] ?? ceil(date('n') / 3));




$months_map = [
    1 => [1, 2, 3],
    2 => [4, 5, 6],
    3 => [7, 8, 9],
    4 => [10, 11, 12]
];
$months = $months_map[$current_quarter];


// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean(); // Clear any previous output (warnings etc)
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';

    if ($action === 'update_value') {
        $item_id = intval($_POST['item_id']);
        $month = intval($_POST['month']);
        $type = $_POST['type']; 
        $amount = floatval($_POST['amount']);
        $year = $current_year;
        $quarter = $current_quarter;

        $stmt = $conn->prepare("INSERT INTO budget_values (item_id, year, quarter, month, value_type, amount) 
                                VALUES (?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit();
        }
        $stmt->bind_param("iiiisd", $item_id, $year, $quarter, $month, $type, $amount);
        if ($stmt->execute()) {
            // Fetch item name for better logging
            $item_res = $conn->query("SELECT item_name FROM budget_structure WHERE id = $item_id");
            $item_info = $item_res->fetch_assoc();
            $item_display_name = $item_info['item_name'] ?? 'Unknown Item';

            // Log the change
            $m_name = date("F", mktime(0,0,0,$month,1));
            $details = "Updated $type for month $m_name to " . number_format($amount, 0, ',', '.') . " đ";
            logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'UPDATE_VALUE', $item_id, $item_display_name, $details);
            echo json_encode(['success' => true]);
        }
        else echo json_encode(['success' => false, 'error' => $stmt->error]);
        exit();
    }

    if ($action === 'add_item') {
        $division = $_POST['division'] ?? '';
        $category = $_POST['category'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $owner = $_POST['owner'] ?? '';
        $type = $_POST['type'] ?? 'item';
        
        $res = $conn->query("SELECT MAX(order_num) as m FROM budget_structure WHERE year = $current_year AND quarter = $current_quarter");
        $order_num = ($res->fetch_assoc()['m'] ?? 0) + 1;

        $stmt = $conn->prepare("INSERT INTO budget_structure (year, quarter, division, category, item_name, owner, type, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssi", $current_year, $current_quarter, $division, $category, $item_name, $owner, $type, $order_num);
        if ($stmt->execute()) {
            $last_id = $conn->insert_id;
            logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'ADD_ITEM', $last_id, $item_name, "Added new $type in $division / $category");
            echo json_encode(['success' => true]);
        }
        else echo json_encode(['success' => false, 'error' => $stmt->error]);
        exit();
    }

    // SECURITY CHECK: Only admin can modify structure
    $structural_actions = ['add_item_structure', 'edit_item_structure', 'delete_item_structure', 'clone_structure'];
    if (in_array($action, $structural_actions) && !$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Chỉ Administrator mới có quyền sửa đổi cấu trúc ngân sách.']);
        exit();
    }

    if ($action === 'clone_structure') {
        // Find previous quarter structure
        $prev_q = $current_quarter - 1;
        $prev_y = $current_year;
        if ($prev_q < 1) { $prev_q = 4; $prev_y--; }

        $res = $conn->query("SELECT * FROM budget_structure WHERE year = $prev_y AND quarter = $prev_q");
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'No structure found in previous quarter (' . $prev_q . '/' . $prev_y . ')']);
            exit();
        }

        while ($row = $res->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO budget_structure (year, quarter, division, category, item_name, owner, type, order_num) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssi", $current_year, $current_quarter, $row['division'], $row['category'], $row['item_name'], $row['owner'], $row['type'], $row['order_num']);
            $stmt->execute();
        }
        logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'CLONE', null, "Quarterly Structure", "Cloned structure from Q$prev_q/$prev_y to Q$current_quarter/$current_year");
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'edit_item_structure') {
        $id = intval($_POST['id']);
        $division = $_POST['division'] ?? '';
        $category = $_POST['category'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $owner = $_POST['owner'] ?? '';
        $order_num = intval($_POST['order_num'] ?? 0);
        $stmt = $conn->prepare("UPDATE budget_structure SET division=?, category=?, item_name=?, owner=?, type=?, order_num=? WHERE id=?");
        $stmt->bind_param("sssssii", $division, $category, $item_name, $owner, $type, $order_num, $id);
        if ($stmt->execute()) {
             logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'EDIT_STRUCTURE', $id, $item_name, "Updated structure info for $item_name (Type: $type)");
             echo json_encode(['success' => true]);
        }
        else echo json_encode(['success' => false, 'error' => $stmt->error]);
        exit();
    }

    if ($action === 'delete_item_structure') {
        $id = intval($_POST['id']);
        // Fetch name for log before delete
        $res = $conn->query("SELECT item_name FROM budget_structure WHERE id = $id");
        $i_name = $res->fetch_assoc()['item_name'] ?? 'Unknown Item';
        
        $conn->query("DELETE FROM budget_values WHERE item_id = $id");
        $conn->query("DELETE FROM budget_structure WHERE id = $id");
        
        logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'DELETE_ITEM', $id, $i_name, "Deleted item from structure");
        echo json_encode(['success' => true]);
        exit();
    }

}

// --- BUILD HIERARCHICAL STRUCTURE ---
$raw_structure = [];
$res = $conn->query("SELECT * FROM budget_structure ORDER BY order_num ASC, id ASC");
while ($row = $res->fetch_assoc()) $raw_structure[] = $row;

// --- ENSURE DB SCHEMA (v6 - Multi-Quarter support) ---
$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'year');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN year INT DEFAULT 2026 AFTER id', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql;");
$conn->query("EXECUTE stmt;");

$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'quarter');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN quarter INT DEFAULT 1 AFTER year', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql;");
$conn->query("EXECUTE stmt;");

$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'owner');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN owner VARCHAR(255) AFTER item_name', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql;");
$conn->query("EXECUTE stmt;");

// Fetch Users
$users = [];
$u_res = $conn->query("SELECT * FROM users ORDER BY full_name ASC");
if ($u_res) while ($u = $u_res->fetch_assoc()) $users[] = $u;

// --- BUILD HIERARCHICAL STRUCTURE (v5 - Schema Focused) ---
function clean_key($str) {
    if (!$str) return '';
    $s = preg_replace('/^[\s•\-\*]+/', '', (string)$str);
    return trim(preg_replace('/\s+/', ' ', $s));
}

$raw = [];
$res = $conn->query("SELECT * FROM budget_structure WHERE year = $current_year AND quarter = $current_quarter ORDER BY order_num ASC, id ASC");

while ($r = $res->fetch_assoc()) {
    // SECURITY FILTER: Improved matching with trim
    if (!$is_admin && $r['type'] === 'item') {
        if (trim($r['owner']) !== trim($current_full_name)) {
            continue; // Skip items owned by others
        }
    }
    $raw[] = $r;
}

// Second filter: Remove division/categories that have NO items left after first filter (for non-admins)
if (!$is_admin) {
    $filtered_raw = [];
    $division_has_items = [];
    $category_has_items = [];
    
    // First pass: identify divisions/categories with items
    foreach ($raw as $r) {
        if ($r['type'] === 'item' && $r['owner'] === $current_full_name) {
            if ($r['division']) $division_has_items[clean_key($r['division'])] = true;
            if ($r['category']) $category_has_items[clean_key($r['category'])] = true;
        }
    }
    
    // Second pass: rebuild list
    foreach ($raw as $r) {
        if ($r['type'] === 'division') {
            if (isset($division_has_items[clean_key($r['item_name'])])) $filtered_raw[] = $r;
        } elseif ($r['type'] === 'category') {
            if (isset($category_has_items[clean_key($r['item_name'])])) $filtered_raw[] = $r;
        } else {
            $filtered_raw[] = $r;
        }
    }
    $raw = $filtered_raw;
}

$div_nodes = [];
// dictionary for cat -> div mapping
// 1. Pass: Build Division and Category dictionary
foreach ($raw as $row) {
    $clean_name = clean_key($row['item_name']);
    if ($row['type'] === 'division') {
        $div_nodes[$clean_name] = ['data' => $row, 'children' => ['_root_' => ['items' => []]]];
    }
}

// 1.5. Pass: Build Category dictionary (must be under a division)
$current_div_key = '';
foreach ($raw as $row) {
    $clean_name = clean_key($row['item_name']);
    if ($row['type'] === 'division') {
        $current_div_key = $clean_name;
    } elseif ($row['type'] === 'category') {
        $d_key = clean_key($row['division'] ?: $current_div_key);
        if ($d_key && isset($div_nodes[$d_key])) {
            $div_nodes[$d_key]['children'][$clean_name] = ['data' => $row, 'items' => []];
        }
    }
}

// 2. Pass: Place Items
$homeless = [];
$current_div_key = ''; $current_cat_key = '';
foreach ($raw as $row) {
    if ($row['type'] === 'division') { $current_div_key = clean_key($row['item_name']); $current_cat_key = ''; continue; }
    if ($row['type'] === 'category') { $current_cat_key = clean_key($row['item_name']); continue; }
    
    if ($row['type'] === 'item') {
        $raw_d = clean_key($row['division']);
        $raw_c = clean_key($row['category']);
        $d_key = $raw_d ?: $current_div_key;
        $c_key = $raw_c ?: $current_cat_key;
        
        if ($d_key && isset($div_nodes[$d_key])) {
            if ($c_key && isset($div_nodes[$d_key]['children'][$c_key])) {
                $div_nodes[$d_key]['children'][$c_key]['items'][] = $row;
            } else {
                $div_nodes[$d_key]['children']['_root_']['items'][] = $row;
            }
        } else {
            $homeless[] = $row;
        }
    }
}

// 3. Assemble
$structure = [];
foreach ($div_nodes as $d_key => $d_node) {
    $structure[] = $d_node['data'];
    foreach ($d_node['children'] as $c_key => $c_node) {
        if ($c_key === '_root_') {
            foreach ($c_node['items'] as $item) $structure[] = $item;
        } else {
            $structure[] = $c_node['data'];
            foreach ($c_node['items'] as $item) $structure[] = $item;
        }
    }
}
$structure = array_merge($structure, $homeless);

// Fetch values for current year/quarter
$values = [];
$res_v = $conn->query("SELECT * FROM budget_values WHERE year = $current_year AND quarter = $current_quarter");
while ($row = $res_v->fetch_assoc()) {
    $values[$row['item_id']][$row['month']][$row['value_type']] = $row['amount'];
}

function get_val($values, $iid, $m, $t) {
    return $values[$iid][$m][$t] ?? 0;
}

function format_vnd($val) {
    if (!$val) return '0 đ';
    return number_format($val, 0, ',', '.') . ' đ';
}

// --- PRE-CALCULATE AGGREGATES FOR DIVISIONS & CATEGORIES ---
$div_totals = [];
$cat_totals = [];

// --- AGGREGATES CALCULATION ---
$div_totals = [];
$cat_totals = [];

foreach ($structure as $row) {
    if ($row['type'] !== 'item') continue;
    
    $did = trim($row['division'] ?? '');
    $cat_name = trim($row['category'] ?? '');
    $cid = $did . ' > ' . $cat_name;
    
    $iid = $row['id'];
    $keys = ['planned', 'actual_salary', 'actual_other'];
    $item_ms = [0, $months[0], $months[1], $months[2]];
    
    foreach ($item_ms as $m) {
        foreach ($keys as $k) {
            $val = get_val($values, $iid, $m, $k);
            if ($val == 0) continue;
            
            if ($did !== '') {
                if (!isset($div_totals[$did][$m][$k])) $div_totals[$did][$m][$k] = 0;
                $div_totals[$did][$m][$k] += $val;
            }
            if ($cat_name !== '') {
                if (!isset($cat_totals[$cid][$m][$k])) $cat_totals[$cid][$m][$k] = 0;
                $cat_totals[$cid][$m][$k] += $val;
            }
        }
    }
}

function get_display_val($row, $values, $div_totals, $cat_totals, $month, $type) {
    if ($row['type'] === 'item') {
        return get_val($values, $row['id'], $month, $type);
    }
    
    // Parent rows often have display prefixes like '-'
    $name = trim(str_replace(['-', '•'], '', $row['item_name'] ?? ''));
    if ($row['type'] === 'division') {
        return $div_totals[$name][$month][$type] ?? 0;
    }
    if ($row['type'] === 'category') {
        $did = trim($row['division'] ?? '');
        $cid = $did . ' > ' . $name;
        return $cat_totals[$cid][$month][$type] ?? 0;
    }
    return 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plan & Budgeting</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .budget-container { padding: 1.5rem; background: #f8fafc; min-height: calc(100vh - 80px); }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; }
        .table-wrapper { overflow-x: auto; border: 1px solid #e2e8f0; }
        table.budget-table { width: 100%; border-collapse: separate !important; border-spacing: 0 !important; font-size: 13px; white-space: nowrap; border-top: 1px solid #cbd5e1; border-left: 1px solid #cbd5e1; }
        
        table.budget-table thead th { 
            background: #1e3a8a !important; 
            color: white !important; 
            font-weight: 700; 
            padding: 12px 8px; 
            border: none;
            border-right: 1px dashed rgba(255,255,255,0.4) !important; 
            border-bottom: 1px dashed rgba(255,255,255,0.4) !important; 
            position: sticky; 
            top: 0; 
            z-index: 10; 
            box-sizing: border-box;
        }
        /* Top border for the very first header row only */
        table.budget-table thead tr:first-child th { border-top: 1px dashed rgba(255,255,255,0.4) !important; }
        /* Left border for the very first column cells */
        table.budget-table th:first-child, table.budget-table td:first-child { border-left: 1px dashed rgba(255,255,255,0.4) !important; }

        table.budget-table tbody td { padding: 8px; border: none; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; }
        
        table.budget-table .row-total td { 
            background: #1e3a8a !important; 
            color: white !important; 
            border: none;
            border-right: 1px dashed rgba(255,255,255,0.4) !important; 
            border-bottom: 1px dashed rgba(255,255,255,0.4) !important; 
            box-sizing: border-box;
        }
        
        /* Sticky Columns - Refined to prevent double borders */
        table.budget-table thead th:nth-child(1), .row-total td:nth-child(1) { position: sticky; left: 0; z-index: 11; background: #1e3a8a !important; border-left: 1px dashed rgba(255,255,255,0.4) !important; }
        table.budget-table thead th:nth-child(2), .row-total td:nth-child(2) { position: sticky; left: 40px; z-index: 11; background: #1e3a8a !important; }
        table.budget-table thead th:nth-child(3), .row-total td:nth-child(3) { position: sticky; left: 160px; z-index: 11; background: #1e3a8a !important; }

        /* Regular Data Rows (Solid) */
        table.budget-table tbody td:nth-child(1) { position: sticky; left: 0; z-index: 11; background: #fff; border-right: 1px solid #cbd5e1; }
        table.budget-table tbody td:nth-child(2) { position: sticky; left: 40px; z-index: 11; background: #fff; border-right: 1px solid #cbd5e1; }
        table.budget-table tbody td:nth-child(3) { position: sticky; left: 160px; z-index: 11; background: #fff; border-right: 2px solid #cbd5e1; width: 250px; }
        
        /* Fixed: Remove conflicting solid rule */
        table.budget-table thead th { background: #1e3a8a !important; }

        .row-division td { color: #000000; font-weight: 700; }
        .row-category td { color: #000000; font-weight: 600; }
        .type-division td:first-child { background: #1e3a8a !important; color: white !important; }
        
        .editable-cell { cursor: pointer; transition: all 0.2s; min-width: 100px; }
        .editable-cell:hover { background: #eff6ff !important; outline: 1px solid #3b82f6; }
        .cell-input { width: 100%; border: none; background: transparent; text-align: right; outline: none; padding: 2px; }
        
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; align-items: center; }
        .filter-select { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600; outline: none; }
        
        .btn-primary { background: #0f172a; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; border-radius: 12px; max-width: 800px; margin: 10vh auto; padding: 20px; }
        .form-group { margin-bottom: 1rem; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../includes/topbar.php'; ?>
            
            <div class="budget-container">
                <!-- Navigation Tabs -->
                <div style="display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 0;">
                    <a href="/plan-budgeting" style="padding: 12px 24px; border-radius: 8px 8px 0 0; background: white; border: 1px solid #e2e8f0; border-bottom: none; font-weight: 700; color: #0f172a; text-decoration: none; position: relative; bottom: -1px;">
                        Bảng quản lý
                    </a>
                    <a href="/plan-budgeting/report" style="padding: 12px 24px; border-radius: 8px 8px 0 0; background: transparent; border: 1px solid transparent; font-weight: 600; color: #64748b; text-decoration: none;">
                        Báo cáo / Dashboard
                    </a>
                </div>

                <div class="budget-header" style="display: flex; align-items: center; justify-content: space-between; background: white; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="font-weight: 700; color: #475569; white-space: nowrap;">XEM DỮ LIÊU:</div>
                        <select class="filter-select" onchange="location.href='?year=' + this.value + '&quarter=<?php echo $current_quarter; ?>'">
                            <?php 
                            $start_year = 2024;
                            $end_year = date('Y') + 1;
                            for ($y = $start_year; $y <= $end_year; $y++): 
                            ?>
                                <option value="<?php echo $y; ?>" <?php if($current_year == $y) echo 'selected'; ?>>Năm <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="filter-select" onchange="location.href='?year=<?php echo $current_year; ?>&quarter=' + this.value">
                            <option value="1" <?php if($current_quarter == 1) echo 'selected'; ?>>Quý 1 (T1-T3)</option>
                            <option value="2" <?php if($current_quarter == 2) echo 'selected'; ?>>Quý 2 (T4-T6)</option>
                            <option value="3" <?php if($current_quarter == 3) echo 'selected'; ?>>Quý 3 (T7-T9)</option>
                            <option value="4" <?php if($current_quarter == 4) echo 'selected'; ?>>Quý 4 (T10-T12)</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <button class="btn-primary" onclick="exportExcel()" style="background:#10b981;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>Xuất Excel
                        </button>
                        <div style="display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                            <input type="checkbox" id="planningMode" onchange="togglePlanningMode(this.checked)" style="cursor:pointer;">
                            <label for="planningMode" style="cursor:pointer; color:#475569;">Planning Mode</label>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                            <?php if ($is_admin): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; color: #1e40af; font-weight: 500; cursor: pointer; background: #fff; padding: 6px 12px; border-radius: 6px; border: 1px solid #dbeafe;">
                                    <input type="checkbox" id="budget-editing-mode" onchange="toggleEditingMode(this.checked)" style="width: 16px; height: 16px;">
                                    Editing Mode
                                </label>
                            <?php endif; ?>
                        </div>
                        <button class="btn-primary" onclick="openModal()">+ Quản lý cấu trúc</button>
                    </div>
                </div>

                <style>
                    .hidden { display: none !important; }
                    .planning-active .actual-col { display: none !important; }
                    /* Default: Actions column is hidden unless Editing Mode is ON */
                    .actions-col { display: none; }
                    .editing-active .actions-col { display: table-cell !important; }
                </style>

                <div class="card">
                    <?php if (empty($raw)): ?>
                        <div style="background: #f8fafc; border: 2px dashed #cbd5e1; padding: 3rem; border-radius: 12px; text-align: center; margin: 2rem;">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #475569; margin-bottom: 12px;">📊 Quý <?php echo $current_quarter; ?>/<?php echo $current_year; ?> chưa có cấu trúc</div>
                            <p style="color: #64748b; margin-bottom: 24px; font-size: 1rem;">
                                <?php if ($is_admin): ?>
                                    Bắt đầu lập ngân sách bằng cách sao chép cấu trúc từ quý trước hoặc thêm mới bộ phận.
                                <?php else: ?>
                                    Vui lòng liên hệ với Quản trị viên (Administrator) để thiết lập cấu trúc ngân sách cho kỳ này.
                                <?php endif; ?>
                            </p>
                            <?php if ($is_admin): ?>
                                <div style="display: flex; justify-content: center; gap: 16px;">
                                    <button class="btn-primary" onclick="cloneStructure()" style="background: #2563eb; padding: 12px 24px;">Sao chép từ Quý trước</button>
                                    <button class="btn-primary" onclick="openModal()" style="background: white; color: #2563eb; border: 1px solid #2563eb; padding: 12px 24px;">+ Thêm thủ công</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <div class="table-wrapper">
                        <table class="budget-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-center" width="50">STT</th>
                                    <th rowspan="2">Khối</th>
                                    <th rowspan="2">Bộ phận/ Khoản chi</th>
                                    <th rowspan="2">Owner</th>
                                    <th rowspan="2" class="text-center" width="100">Kế hoạch Q<?php echo $current_quarter; ?></th>
                                    <th colspan="2" class="text-center">Tháng <?php echo $months[0]; ?></th>
                                    <th colspan="2" class="text-center">Tháng <?php echo $months[1]; ?></th>
                                    <th colspan="2" class="text-center">Tháng <?php echo $months[2]; ?></th>
                                    <th rowspan="2" class="text-center" width="100" style="min-width: 140px;">Thực tế chi</th>
                                    <th rowspan="2" class="text-center actions-col" width="100">Thao tác</th>
                                </tr>
                                <tr>
                                    <th class="actual-col">Lương Gross</th><th class="actual-col">HĐ khác</th>
                                    <th class="actual-col">Lương Gross</th><th class="actual-col">HĐ khác</th>
                                    <th class="actual-col">Lương Gross</th><th class="actual-col">HĐ khác</th>
                                </tr>
                            </thead>
                            <tbody id="budget-body">
                                <?php 
                                // Calculate Grand Totals
                                $grand_totals = [];
                                foreach ($div_totals as $d => $m_data) {
                                    foreach ($m_data as $m => $k_data) {
                                        foreach ($k_data as $k => $v) {
                                            $grand_totals[$m][$k] = ($grand_totals[$m][$k] ?? 0) + $v;
                                        }
                                    }
                                }
                                ?>
                                <tr class="row-total" data-type="grand-total">
                                    <td class="text-center">#</td>
                                    <td colspan="3">TỔNG CỘNG TOÀN BỘ</td>
                                    <?php 
                                    $gt_planned = $grand_totals[0]['planned'] ?? 0;
                                    $gt_actual_m1 = ($grand_totals[$months[0]]['actual_salary'] ?? 0) + ($grand_totals[$months[0]]['actual_other'] ?? 0);
                                    $gt_actual_m2 = ($grand_totals[$months[1]]['actual_salary'] ?? 0) + ($grand_totals[$months[1]]['actual_other'] ?? 0);
                                    $gt_actual_m3 = ($grand_totals[$months[2]]['actual_salary'] ?? 0) + ($grand_totals[$months[2]]['actual_other'] ?? 0);
                                    $gt_total_actual = $gt_actual_m1 + $gt_actual_m2 + $gt_actual_m3;
                                    $gt_percent = ($gt_planned > 0) ? ($gt_total_actual / $gt_planned) * 100 : 0;
                                    ?>
                                    <td class="text-right" data-month="0" data-type="planned"><?php echo number_format($gt_planned, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" data-month="<?php echo $months[0]; ?>" data-type="actual_salary"><?php echo number_format($grand_totals[$months[0]]['actual_salary'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" data-month="<?php echo $months[0]; ?>" data-type="actual_other"><?php echo number_format($grand_totals[$months[0]]['actual_other'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" data-month="<?php echo $months[1]; ?>" data-type="actual_salary"><?php echo number_format($grand_totals[$months[1]]['actual_salary'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" data-month="<?php echo $months[1]; ?>" data-type="actual_other"><?php echo number_format($grand_totals[$months[1]]['actual_other'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" data-month="<?php echo $months[2]; ?>" data-type="actual_salary"><?php echo number_format($grand_totals[$months[2]]['actual_salary'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" data-month="<?php echo $months[2]; ?>" data-type="actual_other"><?php echo number_format($grand_totals[$months[2]]['actual_other'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right" style="color: #ffffff !important;">
                                        <?php echo number_format($gt_total_actual, 0, ',', '.'); ?> đ
                                    </td>
                                    <td class="actions-col"></td>
                                </tr>
                                <?php 
                                 $idx = 0;
                                 foreach ($structure as $row): 
                                     if ($row['type'] === 'division') $idx++;
                                     $row_class = ($row['type'] === 'division' ? 'row-division type-division' : ($row['type'] === 'category' ? 'row-category' : 'item-row'));
                                     $iid = $row['id'];
                                     
                                     // Normalize for data-attributes (remove display prefixes)
                                     $p_div = trim(str_replace(['-', '•'], '', $row['division'] ?? ''));
                                     $p_cat = trim(str_replace(['-', '•'], '', $row['category'] ?? ''));
                                     $this_name = trim(str_replace(['-', '•'], '', $row['item_name'] ?? ''));
                                     
                                     $planned = get_display_val($row, $values, $div_totals, $cat_totals, 0, 'planned');
                                     $actual_m1 = get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_other');
                                     $actual_m2 = get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_other');
                                     $actual_m3 = get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_other');
                                     $total_actual = $actual_m1 + $actual_m2 + $actual_m3;
                                     
                                     $percent = ($planned > 0) ? ($total_actual / $planned) * 100 : 0;
                                     $bg_highlight = '';
                                     if ($row['type'] === 'division') {
                                         // Level 1: Sky Blue BG / Black Text (always)
                                         $bg_highlight = 'background-color: #bfdbfe !important; color: #000000 !important; font-weight: 800; border-bottom: 2px solid #93c5fd;'; 
                                         if ($percent > 100) $bg_highlight .= ' border-left: 6px solid #ef4444;'; 
                                         elseif ($percent > 80) $bg_highlight .= ' border-left: 6px solid #f59e0b;'; 
                                     } elseif ($row['type'] === 'category') {
                                         // Level 2: Slate BG / Black Text (always)
                                         $bg_highlight = 'background-color: #f8fafc !important; color: #000000 !important; font-weight: 600; border-bottom: 1px dashed #cbd5e1;';
                                     } else {
                                         // Level 3: Item (Peach or Alerting BG) / Black Text (always)
                                         if ($percent > 100) $bg_highlight = 'background-color: #fee2e2 !important; color: #000000 !important;'; 
                                         elseif ($percent > 80) $bg_highlight = 'background-color: #fef3c7 !important; color: #000000 !important;';
                                         else $bg_highlight = 'background-color: #fff7ed !important; color: #000000 !important; border-bottom: 1px dashed #fed7aa;'; 
                                     }
                                 ?>
                                 <tr class="<?php echo $row_class; ?>" 
                                     style="<?php echo $bg_highlight; ?>"
                                     data-type="<?php echo $row['type']; ?>"
                                     data-div="<?php echo ($row['type'] === 'division' ? $this_name : $p_div); ?>"
                                     data-cat="<?php echo ($row['type'] === 'category' ? $this_name : $p_cat); ?>">
                                     <?php $cell_style = ($row['type'] === 'division' ? 'background: transparent !important; color: inherit !important;' : ''); ?>
                                     
                                     <td class="text-center" style="<?php echo $cell_style; ?>"><?php echo ($row['type'] === 'division' ? $idx : ''); ?></td>
                                     <td style="font-weight: 600; <?php echo $cell_style; ?>"><?php echo ($row['type'] === 'division' ? htmlspecialchars($row['item_name']) : ''); ?></td>
                                     <td style="padding-left: <?php echo ($row['type'] === 'category' ? '25px' : ($row['type'] === 'item' ? '45px' : '8px')); ?>; <?php echo $cell_style; ?>">
                                         <?php 
                                            $prefix = '';
                                            if ($row['type'] === 'category') $prefix = '• ';
                                            if ($row['type'] === 'item') $prefix = '- ';
                                            echo $prefix . htmlspecialchars($row['item_name']); 
                                         ?>
                                     </td>
                                     <td style="<?php echo $cell_style; ?>">
                                         <?php 
                                            $owner_parts = explode(' ', trim($row['owner'] ?? ''));
                                            echo htmlspecialchars($owner_parts[0]);
                                         ?>
                                     </td>
                                     
                                     <?php $is_editable = ($row['type'] === 'item' ? 'editable-cell' : ''); ?>
                                     
                                     <td class="text-right <?php echo $is_editable; ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="planned" style="font-weight:700; <?php echo ($row['type'] === 'division' ? $cell_style : 'color:#0f172a;'); ?>">
                                         <?php echo format_vnd($planned); ?>
                                     </td>
                                     
                                     <td class="text-right <?php echo $is_editable; ?> actual-col" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[0]; ?>" data-type="actual_salary" style="<?php echo $cell_style; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_salary')); ?>
                                     </td>
                                     <td class="text-right <?php echo $is_editable; ?> actual-col" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[0]; ?>" data-type="actual_other" style="<?php echo $cell_style; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_other')); ?>
                                     </td>
 
                                     <td class="text-right <?php echo $is_editable; ?> actual-col" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[1]; ?>" data-type="actual_salary" style="<?php echo $cell_style; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_salary')); ?>
                                     </td>
                                     <td class="text-right <?php echo $is_editable; ?> actual-col" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[1]; ?>" data-type="actual_other" style="<?php echo $cell_style; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_other')); ?>
                                     </td>
 
                                     <td class="text-right <?php echo $is_editable; ?> actual-col" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[2]; ?>" data-type="actual_salary" style="<?php echo $cell_style; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_salary')); ?>
                                     </td>
                                     <td class="text-right <?php echo $is_editable; ?> actual-col" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[2]; ?>" data-type="actual_other" style="<?php echo $cell_style; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_other')); ?>
                                     </td>
                                     
                                     <td class="text-right actual-col" style="font-weight:700; width:120px; <?php echo $cell_style; ?>">
                                         <?php echo format_vnd($total_actual); ?>
                                     </td>

                                     <td class="text-center actions-col" style="white-space:nowrap; <?php echo $cell_style; ?>">
                                         <button onclick='editStructure(<?php echo json_encode($row); ?>)' style="background:none; border:none; cursor:pointer;" title="Sửa">✏️</button>
                                         <button onclick='deleteStructure(<?php echo $row['id']; ?>)' style="background:none; border:none; cursor:pointer;" title="Xóa">🗑️</button>
                                     </td>
                                 </tr>
                                 <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Activity History Section - NEW PROFESSIONAL UI -->
                <?php 
                $h_limit = 10;
                $h_page = intval($_GET['hpage'] ?? 1);
                $h_offset = ($h_page - 1) * $h_limit;
                
                $total_h_res = $conn->query("SELECT COUNT(*) as total FROM budget_history");
                $total_h = $total_h_res->fetch_assoc()['total'] ?? 0;
                $total_h_pages = ceil($total_h / $h_limit);

                $history_res = $conn->query("SELECT * FROM budget_history ORDER BY created_at DESC LIMIT $h_limit OFFSET $h_offset");
                ?>
                <div class="card" style="margin-top: 40px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.05); background: #ffffff; border-radius: 16px; overflow: hidden; margin-bottom: 60px;">
                    <div style="padding: 24px 30px; background: #fff; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 42px; height: 42px; background: #eff6ff; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size: 1.1rem; font-weight: 700; color: #1e293b;">Lịch sử hoạt động</h3>
                                <p style="margin: 3px 0 0; font-size: 12px; color: #64748b; font-weight: 500;">Theo dõi chi tiết các thay đổi của ngân sách</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 6px 14px; border-radius: 30px; border: 1px solid #f1f5f9;">
                            <span style="display: inline-block; width: 6px; height: 6px; background: #10b981; border-radius: 50%;"></span>
                            <span style="font-size: 13px; color: #475569; font-weight: 700;">Tổng số: <?php echo number_format($total_h); ?></span>
                        </div>
                    </div>
                    
                    <div style="padding: 0;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <thead>
                                <tr style="background: #ffffff;">
                                    <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; border-bottom: 2px solid #f8fafc; width: 130px;">Thời gian</th>
                                    <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; border-bottom: 2px solid #f8fafc; width: 170px;">Tác giả</th>
                                    <th style="padding: 12px 20px; text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; border-bottom: 2px solid #f8fafc; width: 140px;">Hành động</th>
                                    <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; border-bottom: 2px solid #f8fafc;">Nội dung chi tiết</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history_res && $history_res->num_rows > 0): ?>
                                    <?php while($h = $history_res->fetch_assoc()): 
                                        $action_color = '#3b82f6'; // Update
                                        $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
                                        
                                        if($h['action_type'] === 'ADD_ITEM') {
                                            $action_color = '#10b981';
                                            $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>';
                                        }
                                        if($h['action_type'] === 'DELETE_ITEM' || $h['action_type'] === 'DELETE_STRUCTURE') {
                                            $action_color = '#ef4444';
                                            $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
                                        }
                                        if($h['action_type'] === 'CLONE_STRUCTURE') {
                                            $action_color = '#8b5cf6';
                                            $icon_svg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"></path></svg>';
                                        }
                                    ?>
                                        <tr style="border-bottom: 1px solid #f8fafc; transition: all 0.3s; cursor: default;" onmouseover="this.style.background='#fcfdff'" onmouseout="this.style.background='#fff'">
                                            <td style="padding: 10px 20px; color: #64748b; font-size: 12px; font-weight: 500;">
                                                <div style="color: #334155; font-weight: 600;"><?php echo date('H:i:s', strtotime($h['created_at'])); ?></div>
                                                <div style="font-size: 10px; margin-top: 1px; color: #94a3b8;"><?php echo date('d/m/Y', strtotime($h['created_at'])); ?></div>
                                            </td>
                                            <td style="padding: 10px 20px; vertical-align: middle;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div style="width: 28px; height: 28px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; color: #475569; overflow: hidden; border: 1px solid #e2e8f0;">
                                                        <?php 
                                                            $name_parts = explode(' ', trim($h['user_name']));
                                                            echo strtoupper(substr(end($name_parts), 0, 2));
                                                        ?>
                                                    </div>
                                                    <span style="font-weight: 600; color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($h['user_name']); ?></span>
                                                </div>
                                            </td>
                                            <td style="padding: 10px 20px; text-align: center;">
                                                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 6px; font-size: 9px; font-weight: 700; background: <?php echo $action_color; ?>10; color: <?php echo $action_color; ?>; letter-spacing: 0.02em;">
                                                    <?php echo $icon_svg; ?>
                                                    <?php echo str_replace('_', ' ', $h['action_type']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 10px 20px;">
                                                <div style="color: #334155; font-size: 12px; line-height: 1.4;"><?php echo htmlspecialchars($h['details']); ?></div>
                                                <?php if($h['item_name']): ?>
                                                    <div style="display: flex; align-items: center; gap: 4px; margin-top: 3px;">
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"></path></svg>
                                                        <span style="font-size: 10px; color: #94a3b8; font-weight: 500;">Mục: <strong style="color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($h['item_name']); ?></strong></span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="padding: 60px 40px; text-align: center; color: #94a3b8;">
                                        <div style="margin-bottom: 10px;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#e2e8f0" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"></path></svg></div>
                                        Chưa có lịch sử hoạt động nào được ghi nhận.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modern Pagination Pager -->
                    <?php if ($total_h_pages > 1): ?>
                        <div style="padding: 24px 30px; display: flex; justify-content: center; align-items: center; background: #fff; border-top: 1px solid #f8fafc;">
                            <?php 
                            $base_url = "plan-budgeting?year=$current_year&quarter=$current_quarter";
                            ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <a href="<?php echo $base_url; ?>&hpage=<?php echo max(1, $h_page - 1); ?>" 
                                   style="padding: 8px 14px; border-radius: 10px; background: #fff; border: 1px solid #e2e8f0; color: <?php echo $h_page > 1 ? '#475569' : '#cbd5e1'; ?>; text-decoration: none; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: all 0.2s; pointer-events: <?php echo $h_page > 1 ? 'auto' : 'none'; ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.02);"
                                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                                   onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                    Trước
                                </a>
                                
                                <div style="display: flex; gap: 5px;">
                                    <?php 
                                    $start_p = max(1, $h_page - 2);
                                    $end_p = min($total_h_pages, $h_page + 2);
                                    for ($i = $start_p; $i <= $end_p; $i++): ?>
                                        <a href="<?php echo $base_url; ?>&hpage=<?php echo $i; ?>" 
                                           style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: <?php echo $i == $h_page ? '#2563eb' : '#fff'; ?>; color: <?php echo $i == $h_page ? 'white' : '#64748b'; ?>; border: 1px solid <?php echo $i == $h_page ? '#2563eb' : '#e2e8f0'; ?>; text-decoration: none; font-size: 13px; font-weight: 700; transition: all 0.2; box-shadow: <?php echo $i == $h_page ? '0 4px 12px rgba(37,99,235,0.2)' : '0 2px 4px rgba(0,0,0,0.02)'; ?>;">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>

                                <a href="<?php echo $base_url; ?>&hpage=<?php echo min($total_h_pages, $h_page + 1); ?>" 
                                   style="padding: 8px 14px; border-radius: 10px; background: #fff; border: 1px solid #e2e8f0; color: <?php echo $h_page < $total_h_pages ? '#475569' : '#cbd5e1'; ?>; text-decoration: none; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: all 0.2s; pointer-events: <?php echo $h_page < $total_h_pages ? 'auto' : 'none'; ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.02);"
                                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                                   onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                                    Sau
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Structure Management Modal (Add/Edit) -->
    <div id="structureModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; margin:5% auto; padding:25px; width:500px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 id="modalTitle" style="margin:0; font-size:18px; color:#1e293b;">Quản lý cấu trúc</h3>
                <button onclick="closeStructureModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
            </div>
            
            <form id="structureForm" onsubmit="submitStructureForm(event)">
                <input type="hidden" name="action" id="modalAction" value="add_item">
                <input type="hidden" name="id" id="modalId">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Loại dòng</label>
                    <select name="type" id="modalType" onchange="toggleModalFields()" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                        <option value="item">Khoản mục (Level 3)</option>
                        <option value="category">Phòng ban (Level 2)</option>
                        <option value="division">Khối (Level 1)</option>
                    </select>
                </div>
                
                <div style="margin-bottom:15px;" id="divGroup">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Khối cha (Level 1)</label>
                    <input type="text" name="division" id="modalDivision" list="div-list" placeholder="Chọn hoặc nhập mới..." class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                    <datalist id="div-list">
                        <?php 
                        $unique_divs = array_unique(array_filter(array_column($structure, 'division')));
                        foreach ($unique_divs as $ud) echo "<option value='".htmlspecialchars($ud)."'>";
                        ?>
                    </datalist>
                </div>
                
                <div style="margin-bottom:15px;" id="catGroup">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Bộ phận cha (Level 2)</label>
                    <input type="text" name="category" id="modalCategory" list="cat-list" placeholder="Chọn hoặc nhập mới..." class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                    <datalist id="cat-list">
                        <?php 
                        $unique_cats = array_unique(array_filter(array_column($structure, 'category')));
                        foreach ($unique_cats as $uc) echo "<option value='".htmlspecialchars($uc)."'>";
                        ?>
                    </datalist>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Tên hiển thị</label>
                    <input type="text" name="item_name" id="modalName" placeholder="Ví dụ: BD Holdings..." class="form-control" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Owner (Người phụ trách)</label>
                    <input type="text" name="owner" id="modalOwner" list="user-list" placeholder="Chọn từ danh sách user..." class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                    <datalist id="user-list">
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['full_name']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Thứ tự (Số thứ tự)</label>
                    <input type="number" name="order_num" id="modalOrder" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:30px;">
                    <button type="button" onclick="closeStructureModal()" style="padding:10px 20px; background:#f1f5f9; border:none; border-radius:8px; font-weight:600; color:#475569; cursor:pointer;">Hủy</button>
                    <button type="submit" style="padding:10px 20px; background:#2563eb; border:none; border-radius:8px; font-weight:600; color:#fff; cursor:pointer;">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const structureModal = document.getElementById('structureModal');
        const structureForm = document.getElementById('structureForm');

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Thêm cấu trúc ngân sách';
            document.getElementById('modalAction').value = 'add_item';
            document.getElementById('modalId').value = '';
            structureForm.reset();
            toggleModalFields();
            structureModal.style.display = 'block';
        }

        function editStructure(row) {
            document.getElementById('modalTitle').innerText = 'Sửa cấu trúc ngân sách';
            document.getElementById('modalAction').value = 'edit_item_structure';
            document.getElementById('modalId').value = row.id;
            document.getElementById('modalType').value = row.type;
            document.getElementById('modalDivision').value = row.division || '';
            document.getElementById('modalCategory').value = row.category || '';
            document.getElementById('modalName').value = row.item_name;
            document.getElementById('modalOwner').value = row.owner || '';
            document.getElementById('modalOrder').value = row.order_num;
            toggleModalFields();
            structureModal.style.display = 'block';
        }

        function closeStructureModal() {
            structureModal.style.display = 'none';
        }

        function toggleModalFields() {
            const type = document.getElementById('modalType').value;
            const divGrp = document.getElementById('divGroup');
            const catGrp = document.getElementById('catGroup');
            
            if (type === 'division') {
                divGrp.style.display = 'none';
                catGrp.style.display = 'none';
            } else if (type === 'category') {
                divGrp.style.display = 'block';
                catGrp.style.display = 'none';
            } else {
                divGrp.style.display = 'block';
                catGrp.style.display = 'block';
            }
        }

        function deleteStructure(id) {
            if (confirm('Bạn có chắc chắn muốn xóa hạng mục này? Lưu ý: Tất cả dữ liệu của nó cũng sẽ bị xóa vĩnh viễn!')) {
                const fd = new FormData();
                fd.append('action', 'delete_item_structure');
                fd.append('id', id);
                fetch(location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => { if(res.success) location.reload(); });
            }
        }

        function submitStructureForm(e) {
            e.preventDefault();
            const fd = new FormData(e.target);
            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => { if(res.success) location.reload(); else alert('Lỗi: ' + (res.error || 'Không xác định')); });
        }

        function formatVND(n) {
            if (n === '' || n === null || isNaN(n)) return '0 đ';
            return parseInt(n).toLocaleString('de-DE') + ' đ';
        }

        function updateValue(iid, month, type, amount, cell) {
            const fd = new FormData();
            fd.append('action', 'update_value');
            fd.append('item_id', iid);
            fd.append('month', month);
            fd.append('type', type);
            fd.append('amount', amount);

            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cell.innerText = formatVND(amount);
                    cell.style.backgroundColor = '#dcfce7';
                    setTimeout(() => cell.style.backgroundColor = '', 1000);
                    const row = cell.closest('tr');
                    recalculateRowHierarchy(row.dataset.div, row.dataset.cat, month, type);
                }
            });
        }

        function recalculateRowHierarchy(div, cat, month, type) {
            if (cat) { updateAgg('category', div, cat, month, type); updateRowTotal('category', div, cat); }
            updateAgg('division', div, '', month, type); updateRowTotal('division', div, '');
            updateGrandTotal(month, type);
        }

        function updateGrandTotal(month, type) {
            let sum = 0;
            document.querySelectorAll(`.item-row .editable-cell[data-month="${month}"][data-type="${type}"]`).forEach(c => sum += parseInt(c.innerText.replace(/[^\d]/g, '')) || 0);
            const gtRow = document.querySelector('.row-total');
            if (gtRow) {
                const cell = gtRow.querySelector(`td[data-month="${month}"][data-type="${type}"]`);
                if (cell) cell.innerText = formatVND(sum);
                let total = 0;
                gtRow.querySelectorAll('td[data-month]:not([data-type="planned"])').forEach(c => total += parseInt(c.innerText.replace(/[^\d]/g, '')) || 0);
                gtRow.cells[gtRow.cells.length - 2].innerText = formatVND(total);
                const pl = parseInt(gtRow.querySelector('td[data-type="planned"]').innerText.replace(/[^\d]/g, '')) || 0;
                const perc = pl > 0 ? (total / pl) * 100 : 0;
                const container = gtRow.querySelector('td.text-right:nth-last-child(2)');
                if (container) container.innerText = formatVND(total);
            }
        }

        function updateAgg(targetType, div, cat, month, type) {
            let sum = 0;
            const sel = `.item-row[data-div="${div}"]` + (cat ? `[data-cat="${cat}"]` : '');
            document.querySelectorAll(sel).forEach(r => {
                const c = r.querySelector(`.editable-cell[data-month="${month}"][data-type="${type}"]`);
                if (c) sum += parseInt(c.innerText.replace(/[^\d]/g, '')) || 0;
            });
            let tSel = `.row-${targetType}[data-div="${div}"]` + (targetType === 'category' ? `[data-cat="${cat}"]` : '');
            const tRow = document.querySelector(tSel);
            if (tRow) {
                const tCell = tRow.querySelector(`td[data-month="${month}"][data-type="${type}"]`);
                if (tCell) tCell.innerText = formatVND(sum);
            }
        }

        function updateRowTotal(targetType, div, cat) {
            let sel = `.row-${targetType}[data-div="${div}"]` + (targetType === 'category' ? `[data-cat="${cat}"]` : '');
            const row = document.querySelector(sel);
            if (!row) return;
            let total = 0;
            row.querySelectorAll('.actual-col[data-type^="actual_"]').forEach(c => total += parseInt(c.innerText.replace(/[^\d]/g, '')) || 0);
            row.cells[row.cells.length - 2].innerText = formatVND(total);
        }

        document.querySelectorAll('.editable-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                if (this.querySelector('input')) return;
                const originalValue = this.innerText.replace(/[^\d]/g, '').trim() || 0;
                const originalPadding = this.style.padding;
                this.style.padding = '2px';
                const input = document.createElement('input');
                input.type = 'number';
                input.value = originalValue;
                input.style = 'width:100%; height:100%; border:1px solid #3b82f6; text-align:right; font:inherit; margin:0; padding:2px;';
                this.innerText = '';
                this.appendChild(input);
                input.focus();
                input.select();
                const finish = () => {
                    const newValue = input.value;
                    this.style.padding = originalPadding;
                    if (newValue !== originalValue) updateValue(cell.dataset.iid, cell.dataset.month, cell.dataset.type, newValue, cell);
                    else cell.innerText = formatVND(originalValue);
                };
                input.addEventListener('blur', finish);
                input.addEventListener('keydown', e => { if (e.key === 'Enter') finish(); if (e.key === 'Escape') { input.value = originalValue; finish(); } });
            });
        });

        function togglePlanningMode(active) {
            const table = document.querySelector('.budget-table');
            if (active) table.classList.add('planning-active');
            else table.classList.remove('planning-active');
            localStorage.setItem('budget_planning_mode', active);
        }

        function toggleEditingMode(active) {
            const table = document.querySelector('.budget-table');
            if (active) table.classList.add('editing-active');
            else table.classList.remove('editing-active');
            localStorage.setItem('budget_editing_mode', active);
        }

        // Initialize modes on load
        window.addEventListener('DOMContentLoaded', () => {
            const pMode = localStorage.getItem('budget_planning_mode') === 'true';
            const eMode = localStorage.getItem('budget_editing_mode') === 'true';
            
            const pCheck = document.getElementById('planningMode');
            const eCheck = document.getElementById('editingMode');
            
            if (pCheck) { pCheck.checked = pMode; togglePlanningMode(pMode); }
            if (eCheck) { eCheck.checked = eMode; toggleEditingMode(eMode); }
        });

        function cloneStructure() {
            if (!confirm('Bạn có chắc chắn muốn sao chép toàn bộ cấu trúc từ quý trước sang quý này không?')) return;
            const formData = new FormData();
            formData.append('action', 'clone_structure');
            fetch(location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Lỗi: ' + data.error);
            });
        }



        function exportExcel() {
            let csv = [];
            document.querySelectorAll('.budget-table tr').forEach(row => {
                let data = [];
                row.querySelectorAll('th, td').forEach(c => {
                    if (c.classList.contains('actual-col') && document.getElementById('planningMode').checked) return;
                    data.push('"' + c.innerText.replace(/"/g, '""').trim() + '"');
                });
                csv.push(data.join(','));
            });
            const blob = new Blob(["\uFEFF" + csv.join("\n")], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "Budget_Plan_<?php echo $current_year; ?>_Q<?php echo $current_quarter; ?>.csv";
            link.click();
        }
    </script>
</body>
</html>
