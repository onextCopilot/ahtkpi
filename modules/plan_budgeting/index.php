<?php
require_once __DIR__ . '/../../config/config.php';

// Module-specific Page Titles
$page_title = "Plan & Budgeting";
$page_subtitle = "Quản lý Kế Hoạch & Ngân sách";

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');
$current_full_name = $_SESSION['full_name'] ?? 'System / Anonymous';

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
    if (!$user_name) $user_name = 'Unknown User';
    if (!$item_name) $item_name = 'N/A';
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
    if (ob_get_level()) ob_clean(); // Clear any previous output (warnings etc) safely
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';

    if ($action === 'save_rev_settings') {
        if (!$is_admin) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit(); }
        $red = floatval($_POST['red'] ?? 0);
        $yellow = floatval($_POST['yellow'] ?? 0);
        $green = floatval($_POST['green'] ?? 0);
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        $keys = ['budget_rev_red' => $red, 'budget_rev_yellow' => $yellow, 'budget_rev_green' => $green];
        foreach ($keys as $k => $v) {
            $v_str = (string)$v;
            $stmt->bind_param("sss", $k, $v_str, $v_str);
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
                exit();
            }
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'save_cost_settings') {
        if (!$is_admin) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit(); }
        $yellow = floatval($_POST['yellow'] ?? 80);
        $red = floatval($_POST['red'] ?? 100);
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        $keys = ['budget_cost_yellow' => $yellow, 'budget_cost_red' => $red];
        foreach ($keys as $k => $v) {
            $v_str = (string)$v;
            $stmt->bind_param("sss", $k, $v_str, $v_str);
            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
                exit();
            }
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'save_grand_total_settings') {
        if (!$is_admin) { echo json_encode(['success' => false, 'error' => 'Permission denied']); exit(); }
        $blocks = $_POST['blocks'] ?? [];
        if (!is_array($blocks)) $blocks = [];
        
        $v_str = json_encode($blocks);
        $k = 'budget_grand_total_blocks';
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $k, $v_str, $v_str);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
            exit();
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'update_value') {
        $item_id = intval($_POST['item_id']);
        $month = intval($_POST['month']);
        $type = $_POST['type']; 
        $amount = floatval($_POST['amount'] ?? 0);
        $year = $current_year;
        $quarter = $current_quarter;

        // Support for Revenue Fields in budget_structure
        $rev_fields = ['rec_rev_good', 'rec_rev_avg', 'rec_rev_bad', 'inv_rev_good', 'inv_rev_avg', 'inv_rev_bad'];
        if (in_array($type, $rev_fields)) {
            $stmt = $conn->prepare("UPDATE budget_structure SET $type = ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $item_id);
            if ($stmt->execute()) {
                 logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'UPDATE_REVENUE', $item_id, '', "Updated $type to " . number_format($amount, 0, ',', '.') . " đ");
                 echo json_encode(['success' => true]);
            } else echo json_encode(['success' => false, 'error' => $stmt->error]);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO budget_values (item_id, year, quarter, month, value_type, amount) 
                                VALUES (?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit();
        }
        $stmt->bind_param("iiiisd", $item_id, $year, $quarter, $month, $type, $amount);
        if ($stmt->execute()) {
            // If updating actual_salary (now used as total Thực chi), clear actual_other
            if ($type === 'actual_salary') {
                $conn->query("DELETE FROM budget_values WHERE item_id = $item_id AND year = $year AND quarter = $quarter AND month = $month AND value_type = 'actual_other'");
            }
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
        $acct_abbreviation = $_POST['acct_abbreviation'] ?? '';
        $type = $_POST['type'] ?? 'item';
        
        $res = $conn->query("SELECT MAX(order_num) as m FROM budget_structure WHERE year = $current_year AND quarter = $current_quarter");
        $order_num = ($res->fetch_assoc()['m'] ?? 0) + 1;

        $stmt = $conn->prepare("INSERT INTO budget_structure (year, quarter, division, category, item_name, owner, acct_abbreviation, type, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssssi", $current_year, $current_quarter, $division, $category, $item_name, $owner, $acct_abbreviation, $type, $order_num);
        if ($stmt->execute()) {
            $last_id = $conn->insert_id;
            logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'ADD_ITEM', $last_id, $item_name, "Added new $type in $division / $category ($acct_abbreviation)");
            echo json_encode(['success' => true]);
        }
        else echo json_encode(['success' => false, 'error' => $stmt->error]);
        exit();
    }

    if ($action === 'update_revenue_status') {
        $section = $_POST['section'] ?? '';
        $status = intval($_POST['status'] ?? 0);
        $col = '';
        if ($section === 'rec') $col = 'rec_status';
        elseif ($section === 'inv') $col = 'inv_status';
        elseif ($section === 'plan') $col = 'plan_status';
        
        if ($col) {
            $stmt = $conn->prepare("INSERT INTO budget_quarterly_status (year, quarter, $col) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $col = VALUES($col)");
            $stmt->bind_param("iii", $current_year, $current_quarter, $status);
            if ($stmt->execute()) echo json_encode(['success' => true]);
            else echo json_encode(['success' => false, 'error' => $stmt->error]);
            exit();
        }
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
            $stmt = $conn->prepare("INSERT INTO budget_structure (year, quarter, division, category, item_name, owner, acct_abbreviation, type, order_num) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssssi", $current_year, $current_quarter, $row['division'], $row['category'], $row['item_name'], $row['owner'], $row['acct_abbreviation'], $row['type'], $row['order_num']);
            $stmt->execute();
        }
        logBudgetAction($conn, $_SESSION['user_id'], $current_full_name, 'CLONE', null, "Quarterly Structure", "Cloned structure from Q$prev_q/$prev_y to Q$current_quarter/$current_year");
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'edit_item_structure') {
        $id = intval($_POST['id']);
        
        $res = $conn->query("SELECT * FROM budget_structure WHERE id = $id");
        $old_row = $res->fetch_assoc();

        $division = $_POST['division'] ?? '';
        $category = $_POST['category'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $owner = $_POST['owner'] ?? '';
        $acct_abbreviation = $_POST['acct_abbreviation'] ?? '';
        $type = $_POST['type'] ?? 'item';
        $order_num = intval($_POST['order_num'] ?? 0);
        $stmt = $conn->prepare("UPDATE budget_structure SET division=?, category=?, item_name=?, owner=?, acct_abbreviation=?, type=?, order_num=? WHERE id=?");
        $stmt->bind_param("ssssssii", $division, $category, $item_name, $owner, $acct_abbreviation, $type, $order_num, $id);
        if ($stmt->execute()) {
             if ($old_row) {
                 if ($old_row['type'] === 'division' && $old_row['item_name'] !== $item_name) {
                     $stmt_casc = $conn->prepare("UPDATE budget_structure SET division = ? WHERE division = ?");
                     $stmt_casc->bind_param("ss", $item_name, $old_row['item_name']);
                     $stmt_casc->execute();
                 }
                 if ($old_row['type'] === 'category' && $old_row['item_name'] !== $item_name) {
                     $stmt_casc = $conn->prepare("UPDATE budget_structure SET category = ? WHERE category = ? AND division = ?");
                     $stmt_casc->bind_param("sss", $item_name, $old_row['item_name'], $old_row['division']);
                     $stmt_casc->execute();
                 }
                 if ($old_row['type'] === 'category' && $old_row['division'] !== $division) {
                     $stmt_casc = $conn->prepare("UPDATE budget_structure SET division = ? WHERE category = ? AND division = ?");
                     $stmt_casc->bind_param("sss", $division, $old_row['item_name'], $old_row['division']);
                     $stmt_casc->execute();
                 }
             }

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

    if ($action === 'get_item_drilldown') {
        $id = intval($_POST['id']);
        $year = intval($_POST['year']);
        $quarter = intval($_POST['quarter']);

        $res = $conn->query("SELECT * FROM budget_structure WHERE id = $id");
        $current_item = $res->fetch_assoc();
        if (!$current_item) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit();
        }

        $history_data = [];
        $temp_y = $year;
        $temp_q = $quarter;

        for ($i = 0; $i < 4; $i++) {
            // Find item in this specific y/q
            $stmt = $conn->prepare("SELECT * FROM budget_structure WHERE item_name = ? AND division = ? AND category = ? AND year = ? AND quarter = ? LIMIT 1");
            $stmt->bind_param("sssii", $current_item['item_name'], $current_item['division'], $current_item['category'], $temp_y, $temp_q);
            $stmt->execute();
            $item_info = $stmt->get_result()->fetch_assoc();

            // Fetch quarterly status (to know which revenue scenario was selected)
            $res_stat = $conn->query("SELECT rec_status, inv_status FROM budget_quarterly_status WHERE year = $temp_y AND quarter = $temp_q");
            $status = $res_stat->fetch_assoc() ?? ['rec_status' => 2, 'inv_status' => 2]; // Default to Avg

            $sal_total = 0;
            $oth_total = 0;
            $rec_good = 0; $rec_avg = 0; $rec_bad = 0;
            $inv_good = 0; $inv_avg = 0; $inv_bad = 0;

            if ($item_info) {
                // Expenses
                $item_id = $item_info['id'];
                $res_sal = $conn->query("SELECT SUM(amount) as s FROM budget_values WHERE item_id = $item_id AND year = $temp_y AND quarter = $temp_q AND value_type = 'actual_salary'");
                $sal_total = floatval($res_sal->fetch_assoc()['s'] ?? 0);
                $res_oth = $conn->query("SELECT SUM(amount) as s FROM budget_values WHERE item_id = $item_id AND year = $temp_y AND quarter = $temp_q AND value_type = 'actual_other'");
                $oth_total = floatval($res_oth->fetch_assoc()['s'] ?? 0);

                // Income scenarios
                $rec_good = floatval($item_info['rec_rev_good'] ?? 0);
                $rec_avg  = floatval($item_info['rec_rev_avg'] ?? 0);
                $rec_bad  = floatval($item_info['rec_rev_bad'] ?? 0);
                
                $inv_good = floatval($item_info['inv_rev_good'] ?? 0);
                $inv_avg  = floatval($item_info['inv_rev_avg'] ?? 0);
                $inv_bad  = floatval($item_info['inv_rev_bad'] ?? 0);
            }

            $history_data[] = [
                'period' => "Q$temp_q/" . substr($temp_y, 2),
                'salary' => $sal_total,
                'other' => $oth_total,
                'total_expense' => $sal_total + $oth_total,
                'rec' => ['good' => $rec_good, 'avg' => $rec_avg, 'bad' => $rec_bad],
                'inv' => ['good' => $inv_good, 'avg' => $inv_avg, 'bad' => $inv_bad],
                'year' => $temp_y,
                'quarter' => $temp_q
            ];

            // Step back one quarter
            $temp_q--;
            if ($temp_q < 1) { $temp_q = 4; $temp_y--; }
        }

        $history_data = array_reverse($history_data);

        echo json_encode([
            'success' => true,
            'current_item' => $current_item,
            'history' => $history_data
        ]);
        exit();
    }

    if ($action === 'get_owner_performance') {
        $owner = trim($_POST['owner'] ?? '');
        $year = intval($_POST['year']);
        $quarter = intval($_POST['quarter']);

        if (empty($owner)) {
            echo json_encode(['success' => false, 'error' => 'Owner name is empty']);
            exit();
        }

        // Level-aware Ownership Retrieval:
        // 1. Find all identifiers (Division, Category) that this person explicitly owns at a grouping level
        $stmt_owned = $conn->prepare("SELECT item_name, division, category, type FROM budget_structure WHERE owner = ? AND year = ? AND quarter = ?");
        $stmt_owned->bind_param("sii", $owner, $year, $quarter);
        $stmt_owned->execute();
        $owned_groups = $stmt_owned->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $owned_divs = []; $owned_cats = [];
        foreach ($owned_groups as $og) {
            // Use item_name for headers as it contains the group name that matches descendant division/category columns
            if ($og['type'] === 'division') $owned_divs[] = $og['item_name'];
            if ($og['type'] === 'category') $owned_cats[] = $og['item_name'];
        }

        // 2. Fetch all leaf items (Level 3) that are either directly owned OR belong to an owned group
        $params = [$owner]; $types = "s";
        $sub_conds = ["owner = ?"];
        if (!empty($owned_divs)) {
            $placeholders = implode(',', array_fill(0, count($owned_divs), '?'));
            $sub_conds[] = "division IN ($placeholders)";
            foreach ($owned_divs as $d) { $params[] = $d; $types .= "s"; }
        }
        if (!empty($owned_cats)) {
            $placeholders = implode(',', array_fill(0, count($owned_cats), '?'));
            $sub_conds[] = "category IN ($placeholders)";
            foreach ($owned_cats as $c) { $params[] = $c; $types .= "s"; }
        }

        $sql = "SELECT * FROM budget_structure WHERE (" . implode(" OR ", $sub_conds) . ") AND type = 'item' AND year = ? AND quarter = ? ORDER BY order_num ASC";
        $params[] = $year; $params[] = $quarter; $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total_rec_avg = 0; $total_inv_avg = 0; $total_salary = 0; $total_other = 0;
        $item_list = [];
        $divisions = [];
        $acct_abbr = '';

        foreach ($items as $item) {
            $iid = $item['id'];
            if (empty($acct_abbr)) $acct_abbr = $item['acct_abbreviation'];
            if (!in_array($item['division'], $divisions)) $divisions[] = $item['division'];

            $res_v = $conn->query("SELECT SUM(amount) as s, value_type FROM budget_values WHERE item_id = $iid AND year = $year AND quarter = $quarter AND value_type IN ('actual_salary', 'actual_other') GROUP BY value_type");
            $sal = 0; $oth = 0;
            while($v = $res_v->fetch_assoc()) {
                if ($v['value_type'] === 'actual_salary') $sal = floatval($v['s']);
                else $oth = floatval($v['s']);
            }
            $total_salary += $sal; $total_other += $oth;
            $total_rec_avg += floatval($item['rec_rev_avg']);
            $total_inv_avg += floatval($item['inv_rev_avg']);
            $item_list[] = [
                'name' => $item['item_name'],
                'division' => $item['division'],
                'category' => $item['category'],
                'rec' => ['good' => floatval($item['rec_rev_good']), 'avg' => floatval($item['rec_rev_avg']), 'bad' => floatval($item['rec_rev_bad'])],
                'inv' => ['good' => floatval($item['inv_rev_good']), 'avg' => floatval($item['inv_rev_avg']), 'bad' => floatval($item['inv_rev_bad'])],
                'salary' => $sal,
                'other' => $oth,
                'expense' => $sal + $oth
            ];
        }

        echo json_encode([
            'success' => true, 'owner' => $owner,
            'year' => $year, 'quarter' => $quarter,
            'abbr' => $acct_abbr,
            'divisions' => implode(', ', array_filter($divisions)),
            'item_count' => count($item_list),
            'summary' => ['rec_avg' => $total_rec_avg, 'inv_avg' => $total_inv_avg, 'salary' => $total_salary, 'other' => $total_other, 'total_expense' => $total_salary + $total_other],
            'items' => $item_list
        ]);
        exit();
    }

    if ($action === 'get_user_history_logs') {
        $owner = $_POST['owner'] ?? '';
        $offset = intval($_POST['offset'] ?? 0);
        $limit = 5;

        $stmt = $conn->prepare("SELECT * FROM budget_history WHERE user_name = ? ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("sii", $owner, $limit, $offset);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'has_more' => (count($logs) === $limit)
        ]);
        exit();
    }

}

// --- BUILD HIERARCHICAL STRUCTURE ---
$raw_structure = [];
$res = $conn->query("SELECT * FROM budget_structure ORDER BY order_num ASC, id ASC");
while ($row = $res->fetch_assoc()) $raw_structure[] = $row;

// --- ENSURE DB SCHEMA (v6 - Multi-Quarter support) ---
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(255) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("ALTER TABLE budget_values MODIFY COLUMN value_type VARCHAR(100)");

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

$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'acct_abbreviation');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN acct_abbreviation VARCHAR(100) AFTER owner', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql;");
$conn->query("EXECUTE stmt;");

$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'rec_rev_good');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN rec_rev_good DECIMAL(19,2) DEFAULT 0 AFTER acct_abbreviation, ADD COLUMN rec_rev_avg DECIMAL(19,2) DEFAULT 0 AFTER rec_rev_good, ADD COLUMN rec_rev_bad DECIMAL(19,2) DEFAULT 0 AFTER rec_rev_avg, ADD COLUMN inv_rev_good DECIMAL(19,2) DEFAULT 0 AFTER rec_rev_bad, ADD COLUMN inv_rev_avg DECIMAL(19,2) DEFAULT 0 AFTER inv_rev_good, ADD COLUMN inv_rev_bad DECIMAL(19,2) DEFAULT 0 AFTER inv_rev_avg, ADD COLUMN rec_rev_status INT DEFAULT 0 AFTER rec_rev_bad, ADD COLUMN inv_rev_status INT DEFAULT 0 AFTER inv_rev_bad', 'SELECT 1');");
$conn->query("PREPARE stmt FROM @sql;");
$conn->query("EXECUTE stmt;");

// Quarterly Status table (Global scenario selection)
$conn->query("CREATE TABLE IF NOT EXISTS budget_quarterly_status (year INT, quarter INT, rec_status INT DEFAULT 0, inv_status INT DEFAULT 0, plan_status INT DEFAULT 0, PRIMARY KEY(year, quarter))");

$q_status_res = $conn->query("SELECT * FROM budget_quarterly_status WHERE year = $current_year AND quarter = $current_quarter");
$q_status = $q_status_res->fetch_assoc() ?: ['rec_status' => 0, 'inv_status' => 0, 'plan_status' => 0];

// Ensure V7 schema update (Status columns) if V6 was already run without them
$conn->query("SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'budget_structure' AND table_schema = DATABASE() AND column_name = 'rec_rev_status');");
$conn->query("SET @sql = IF(@col_exists = 0, 'ALTER TABLE budget_structure ADD COLUMN rec_rev_status INT DEFAULT 0 AFTER rec_rev_bad, ADD COLUMN inv_rev_status INT DEFAULT 0 AFTER inv_rev_bad', 'SELECT 1');");
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
// Initial truly empty check (will be updated after structure build)
$is_truly_empty = (count($raw) === 0);

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

// 1. Pass: Build Division dictionary
$div_nodes = [];
foreach ($raw as $row) {
    if ($row['type'] === 'division') {
        $clean_name = clean_key($row['item_name']);
        $div_nodes[$clean_name] = ['data' => $row, 'children' => ['_root_' => ['items' => []]]];
    }
}

// 1.5. Pass: Build Category dictionary
$current_div_key = '';
$cat_to_div_map = []; // Maps Category -> Division
$div_cat_map_for_js = []; // For the frontend dynamic dropdown
$all_cats_for_js = [];
foreach ($raw as $row) {
    if ($row['type'] === 'division') {
        $current_div_key = clean_key($row['item_name']);
    } elseif ($row['type'] === 'category') {
        $clean_name = clean_key($row['item_name']);
        $d_key = clean_key($row['division']) ?: $current_div_key;
        if ($d_key && isset($div_nodes[$d_key])) {
            $div_nodes[$d_key]['children'][$clean_name] = ['data' => $row, 'items' => []];
            $cat_to_div_map[$clean_name] = $d_key;
        }
        $raw_div_name = trim($row['division'] ?: $div_nodes[$d_key]['data']['item_name'] ?? '');
        $raw_cat_name = trim($row['item_name']);
        if ($raw_div_name) $div_cat_map_for_js[$raw_div_name][] = $raw_cat_name;
        $all_cats_for_js[] = $raw_cat_name;
    }
}

foreach ($div_cat_map_for_js as $k => $v) $div_cat_map_for_js[$k] = array_values(array_unique($v));
$all_cats_for_js = array_values(array_unique($all_cats_for_js));

// 2. Pass: Place Items
$homeless = [];
$current_div_key = ''; $current_cat_key = '';
foreach ($raw as $row) {
    if ($row['type'] === 'division') { $current_div_key = clean_key($row['item_name']); $current_cat_key = ''; continue; }
    if ($row['type'] === 'category') { $current_cat_key = clean_key($row['item_name']); continue; }
    
    if ($row['type'] === 'item') {
        $raw_d = clean_key($row['division']);
        $raw_c = clean_key($row['category']);
        $c_key = $raw_c ?: $current_cat_key;
        $d_key = $raw_d ?: $current_div_key;
        
        // Smart Resolution: if item has a valid category, we lookup which division the category ACTUALLY belongs to
        if ($c_key && isset($cat_to_div_map[$c_key])) {
            $d_key = $cat_to_div_map[$c_key]; // Override with correct parent division
        }

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

// RECALCULATE: Consider it empty if no items at all or no items of type 'item' (Level 3)
$is_truly_empty = empty($structure);
$has_actual_items = false;
foreach ($structure as $s) { if ($s['type'] === 'item') { $has_actual_items = true; break; } }

// If we have some structure but NO items, we might still want to show the duplicate button
// But for now, let's strictly follow the user: "nếu chưa có bản ghi nào"
// Use $is_truly_empty for the big card, but also consider showing the button if count of items is 0.

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
$grand_totals = []; // Reset and rebuild with revenue

// --- LOAD SETTINGS (THRESHOLDS & GRAND TOTAL FILTERS) ---
$settings_map = [];
$res_set = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'budget_%'");
if ($res_set) {
    while ($r = $res_set->fetch_assoc()) {
        if ($r['setting_key'] === 'budget_grand_total_blocks') {
            $settings_map[$r['setting_key']] = json_decode($r['setting_value'], true) ?: [];
        } else {
            $settings_map[$r['setting_key']] = floatval($r['setting_value']);
        }
    }
}
$grand_total_blocks = $settings_map['budget_grand_total_blocks'] ?? null;

foreach ($structure as $row) {
    if ($row['type'] !== 'item') continue;
    
    $did = trim($row['division'] ?? '');
    $cat_name = trim($row['category'] ?? '');
    $cid = $did . ' > ' . $cat_name;
    
    $iid = $row['id'];
    
    // Revenue Fields
    $rev_keys = ['rec_rev_good', 'rec_rev_avg', 'rec_rev_bad', 'inv_rev_good', 'inv_rev_avg', 'inv_rev_bad'];
    foreach ($rev_keys as $rk) {
        $val = floatval($row[$rk] ?? 0);
        if ($val == 0) continue;
        if ($did !== '') {
            $div_totals[$did]['_rev_'][$rk] = ($div_totals[$did]['_rev_'][$rk] ?? 0) + $val;
            
            // Check grand total inclusion for revenue
            if ($grand_total_blocks === null || in_array($did, $grand_total_blocks)) {
                $grand_totals['_rev_'][$rk] = ($grand_totals['_rev_'][$rk] ?? 0) + $val;
            }
        }
        if ($cat_name !== '') $cat_totals[$cid]['_rev_'][$rk] = ($cat_totals[$cid]['_rev_'][$rk] ?? 0) + $val;
    }

    $keys = ['planned', 'actual_salary', 'actual_other', 'planned_good', 'planned_avg', 'planned_bad'];
    $item_ms = [0, $months[0], $months[1], $months[2]];
    
    foreach ($item_ms as $m) {
        foreach ($keys as $k) {
            $val = get_val($values, $iid, $m, $k);
            if ($val == 0) continue;
            
            if ($did !== '') {
                if (!isset($div_totals[$did][$m][$k])) $div_totals[$did][$m][$k] = 0;
                $div_totals[$did][$m][$k] += $val;
                // For Expenses/Plans, always sum all rows for Grand Total
                $grand_totals[$m][$k] = ($grand_totals[$m][$k] ?? 0) + $val;
            }
            if ($cat_name !== '') {
                if (!isset($cat_totals[$cid][$m][$k])) $cat_totals[$cid][$m][$k] = 0;
                $cat_totals[$cid][$m][$k] += $val;
            }
        }
    }
}

function get_rev_val($row, $div_totals, $cat_totals, $type) {
    if ($row['type'] === 'item') return floatval($row[$type] ?? 0);
    $did = trim($row['division'] ?? '');
    if ($row['type'] === 'division') {
        $d_name = trim($row['item_name'] ?? '');
        return $div_totals[$d_name]['_rev_'][$type] ?? 0;
    }
    if ($row['type'] === 'category') {
        $c_name = trim($row['item_name'] ?? '');
        $cid = $did . ' > ' . $c_name;
        return $cat_totals[$cid]['_rev_'][$type] ?? 0;
    }
    return 0;
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

// --- USE PRE-LOADED THRESHOLDS ---
$rev_red = $settings_map['budget_rev_red'] ?? 50;
$rev_yellow = $settings_map['budget_rev_yellow'] ?? 80;
$rev_green = $settings_map['budget_rev_green'] ?? 100;

$cost_yellow = $settings_map['budget_cost_yellow'] ?? 80;
$cost_red = $settings_map['budget_cost_red'] ?? 100;

function get_rev_bg_color($val, $planned, $red, $yellow, $green) {
    if ($val == 0 || $planned == 0) return '';
    $pct = ($val / $planned) * 100;
    if ($pct > $green) return 'background-color: #f3e8ff !important; color: #6b21a8 !important; font-weight: bold; border: 1px dashed #d8b4fe !important; outline: 1px solid #d8b4fe;'; // Purple
    if ($pct > $yellow) return 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold; border: 1px dashed #bbf7d0 !important; outline: 1px solid #bbf7d0;'; // Green
    if ($pct > $red) return 'background-color: #fef08a !important; color: #854d0e !important; font-weight: bold; border: 1px dashed #fde047 !important; outline: 1px solid #fde047;'; // Yellow
    return 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold; border: 1px dashed #fecaca !important; outline: 1px solid #fecaca;'; // Red
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plan & Budgeting</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        .budget-container { padding: 1.5rem; background: #f8fafc; min-height: calc(100vh - 80px); }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; }
        .table-wrapper { overflow-x: auto; overflow-y: auto; max-height: calc(100vh - 250px); border: 1px solid #e2e8f0; }
        table.budget-table { width: 100%; border-collapse: separate !important; border-spacing: 0 !important; font-size: 13px; white-space: nowrap; border-top: 1px solid #cbd5e1; border-left: 1px solid #cbd5e1; }
        
        /* Uniform blue background for all headers as requested */
        table.budget-table thead th { 
            background: #1e3a8a !important; 
            color: white !important; 
            font-weight: 700; 
            padding: 8px 4px; /* Reduced padding for more compact header */
            border: none !important;
            border-right: 1px dashed #ffffff !important; 
            border-bottom: 1px dashed #ffffff !important; /* Restored horizontal separation border */
            position: sticky !important; 
            top: 0; 
            z-index: 20 !important; /* Minimal priority for header */
            box-sizing: border-box;
            border-top: none !important;
            height: 40px; /* Explicit height to sync with sticky offsets */
        }

        /* Group column classes now use the same uniform blue theme */
        table.budget-table thead .col-rec, 
        table.budget-table thead .col-inv, 
        table.budget-table thead .col-month { 
            background-color: #1e3a8a !important; 
            color: white !important; 
        }

        /* Only bottom-most header cells get a bottom border */
        table.budget-table thead tr:last-child th,
        table.budget-table thead th[rowspan="2"] {
             border-bottom: 1px dashed #ffffff !important;
        }

        /* Second row of header alignment (STT, etc) */
        table.budget-table thead tr:nth-child(2) th {
            top: 40px !important; /* Must override the general top: 0 policy */
        }
        
        /* Third row of header alignment (Radio buttons, M1/M2) */
        table.budget-table thead tr:nth-child(3) th {
            top: 80px !important; /* Offset for Super Header + Row 2 */
            padding: 4px 4px; /* Even tighter for the radio button row */
        }

        /* Reset all other sides to prevent solid look from overlap */
        table.budget-table th, table.budget-table td { 
            border-left: none !important; 
            border-top: none !important; 
        }

        table.budget-table tbody td { 
            padding: 8px; 
            border-right: 1px dashed #94a3b8 !important; 
            border-bottom: 1px dashed #94a3b8 !important; 
            font-size: 11px; /* Data row font size as requested */
        }
        
        table.budget-table .row-total td { 
            background: #1e3a8a !important; 
            color: white !important; 
            border: none !important;
            border-right: 1px dashed #ffffff !important; 
            border-bottom: 1px dashed #ffffff !important; 
            box-sizing: border-box;
        }
        
        /* Sticky Columns - Highest priority for the first two columns */
        table.budget-table thead th.col-stt, table.budget-table .row-total td.col-stt, table.budget-table tbody td.col-stt { 
            position: sticky !important; left: 0 !important; z-index: 10 !important; min-width: 40px; 
        }
        
        .col-block { min-width: 120px; }
        table.budget-table thead th.col-block, table.budget-table .row-total td.col-block, table.budget-table tbody td.col-block { 
            position: sticky !important; left: 40px !important; z-index: 10 !important; 
        }
        
        .col-dept { min-width: 250px; }
        table.budget-table thead th.col-dept, table.budget-table .row-total td.col-dept, table.budget-table tbody td.col-dept { 
            position: sticky !important; left: 160px !important; z-index: 5 !important; 
        }
        
        /* Adjustment if Khối is hidden - must be highly specific to override individual row styles */
        table.budget-table.block-hidden th.col-dept,
        table.budget-table.block-hidden tbody td.col-dept,
        table.budget-table.block-hidden tbody .row-division td.col-dept,
        table.budget-table.block-hidden tbody .row-category td.col-dept,
        table.budget-table.block-hidden .row-total td.col-dept { 
            left: 40px !important; 
        }

        /* Standard body cell background - Allow row highlights to show through while remaining sticky */
        table.budget-table tbody td.col-stt,
        table.budget-table tbody td.col-block,
        table.budget-table tbody td.col-dept {
            background-color: inherit !important; /* Take the highlight color of the parent row */
        }

        table.budget-table thead th.col-stt,
        table.budget-table thead th.col-block {
            background-color: #1e3a8a !important;
            z-index: 30 !important; /* Top-left corner cells must stay above generic headers */
        }

        table.budget-table thead th.col-dept {
            background-color: #1e3a8a !important;
            z-index: 30 !important; /* Top-left corner cells must stay above generic headers */
        }
        
        /* Ensure specific rows maintain highlights while sticky */
        table.budget-table tbody .row-division td.col-stt { left: 0 !important; position: sticky !important; }
        table.budget-table tbody .row-division td.col-block { left: 40px !important; position: sticky !important; }
        table.budget-table tbody .row-division td.col-dept { left: 160px !important; position: sticky !important; }
        
        table.budget-table tbody .row-category td.col-stt { left: 0 !important; position: sticky !important; }
        table.budget-table tbody .row-category td.col-block { left: 40px !important; position: sticky !important; }
        table.budget-table tbody .row-category td.col-dept { left: 160px !important; position: sticky !important; }

        table.budget-table .row-total td.col-stt { left: 0 !important; position: sticky !important; background: #1e3a8a !important; color: #ffffff !important; }
        table.budget-table .row-total td.col-block { left: 40px !important; position: sticky !important; background: #1e3a8a !important; color: #ffffff !important; }
        table.budget-table .row-total td.col-dept { left: 160px !important; position: sticky !important; background: #1e3a8a !important; color: #ffffff !important; }


        /* Fixed: Remove conflicting solid rule */
        table.budget-table thead th { background: #1e3a8a !important; }

        .row-division td { color: #000000; font-weight: 700; }
        .row-category td { color: #000000; font-weight: 600; }
        .type-division td:first-child { background: #1e3a8a !important; color: white !important; }
        
        .editable-cell { cursor: pointer; transition: all 0.2s; min-width: 100px; }
        .editable-cell:hover { background: #eff6ff !important; outline: 1px solid #3b82f6; }
        .cell-input { width: 100%; border: none; background: transparent; text-align: right; outline: none; padding: 2px; }
        
        .col-hidden { display: none !important; }
        .column-settings-dropdown { position: relative; display: inline-block; }
        .column-settings-content { 
            display: none; position: absolute; right: 0; background-color: #ffffff; 
            min-width: 200px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
            z-index: 100; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;
            margin-top: 8px;
        }
        .column-settings-dropdown.open .column-settings-content { display: block; }
        .column-option { display: flex; align-items: center; gap: 10px; padding: 6px 0; cursor: pointer; font-size: 13px; color: #475569; }
        .column-option:hover { color: #1e293b; }
        .column-option input { cursor: pointer; }
        .filter-select { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600; outline: none; }
        
        .btn-primary { background: #0f172a; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; border-radius: 12px; max-width: 800px; margin: 10vh auto; padding: 20px; }
        .form-group { margin-bottom: 1rem; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }

        /* Drilldown Sidebar */
        .drilldown-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1999;
            backdrop-filter: blur(2px);
        }
        .drilldown-sidebar {
            position: fixed;
            top: 0; right: -600px;
            width: 600px; height: 100%;
            background: white;
            z-index: 2000;
            box-shadow: -5px 0 25px rgba(0,0,0,0.1);
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        .drilldown-sidebar.open { right: 0; }
        .drilldown-header {
            padding: 24px;
            background: #1e3a8a;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .drilldown-content {
            padding: 24px;
            overflow-y: auto;
            flex-grow: 1;
        }
        .drilldown-close {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 36px; height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .drilldown-close:hover { background: rgba(255,255,255,0.2); }
        .drilldown-section { margin-bottom: 30px; }
        .drilldown-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom:  profile;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .comp-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .comp-period { font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px; }
        .comp-value { font-size: 18px; font-weight: 700; color: #0f172a; }
        .comp-diff { font-size: 12px; margin-top: 8px; display: flex; align-items: center; gap: 4px; }
        .clickable-vtkt { cursor: pointer; text-decoration: none; transition: color 0.2s; }
        .clickable-vtkt:hover { color: #2563eb !important; background: rgba(37, 99, 235, 0.05); }
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

                <div class="budget-header" style="display: flex; align-items: center; justify-content: space-between; background: white; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; gap: 12px; flex-wrap: wrap;">
                    <!-- Left: Filter Controls -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Xem dữ liệu:</span>
                        <select class="filter-select" style="font-size:13px; padding:6px 10px;" onchange="location.href='?year=' + this.value + '&quarter=<?php echo $current_quarter; ?>'">
                            <?php 
                            $start_year = 2024;
                            $end_year = date('Y') + 1;
                            for ($y = $start_year; $y <= $end_year; $y++): 
                            ?>
                                <option value="<?php echo $y; ?>" <?php if($current_year == $y) echo 'selected'; ?>>Năm <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="filter-select" style="font-size:13px; padding:6px 10px;" onchange="location.href='?year=<?php echo $current_year; ?>&quarter=' + this.value">
                            <option value="1" <?php if($current_quarter == 1) echo 'selected'; ?>>Quý 1</option>
                            <option value="2" <?php if($current_quarter == 2) echo 'selected'; ?>>Quý 2</option>
                            <option value="3" <?php if($current_quarter == 3) echo 'selected'; ?>>Quý 3</option>
                            <option value="4" <?php if($current_quarter == 4) echo 'selected'; ?>>Quý 4</option>
                        </select>
                    </div>

                    <!-- Right: Action Controls -->
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <!-- Export -->
                        <button class="btn-primary" onclick="exportExcel()" style="background:#10b981; padding: 7px 14px; font-size:13px; display:flex; align-items:center; gap:6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>Xuất Excel
                        </button>

                        <!-- Divider -->
                        <div style="width:1px; height:28px; background:#e2e8f0;"></div>

                        <!-- Modes group -->
                        <div style="display:flex; align-items:center; gap:6px; background:#f8fafc; padding: 5px 10px; border-radius:8px; border:1px solid #e2e8f0;">
                            <input type="checkbox" id="planningMode" onchange="togglePlanningMode(this.checked)" style="display:none;">
                            <?php if ($is_admin): ?>
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer; font-size:13px; color:#1e40af; white-space:nowrap;">
                                <input type="checkbox" id="budget-editing-mode" onchange="toggleEditingMode(this.checked)" style="cursor:pointer; width:14px; height:14px;">
                                Editing Mode
                            </label>
                            <?php endif; ?>
                        </div>

                        <!-- Divider -->
                        <div style="width:1px; height:28px; background:#e2e8f0;"></div>

                        <!-- Column visibility -->
                        <div class="column-settings-dropdown">
                            <button class="btn-primary" style="background:white; color:#475569; border:1px solid #cbd5e1; display:flex; align-items:center; gap:6px; padding:7px 12px; font-size:13px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"></path></svg>Ẩn/Hiện cột
                            </button>
                            <div class="column-settings-content">
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-block', this.checked)"> Khối</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-vtkt', this.checked)"> VTKT</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-owner', this.checked)"> Owner</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-rec', this.checked)"> Recognised Revenue</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-inv', this.checked)"> Invoiced Revenue</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-plan', this.checked)"> Kế Hoạch Chi Quý</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-plan', this.checked)"> Kế Hoạch Chi Quý</label>
                                <label class="column-option"><input type="checkbox" checked onchange="toggleCol('col-actual', this.checked)"> Thực chi</label>
                            </div>
                        </div>

                        <!-- Structure management -->
                        <button class="btn-primary" onclick="openModal()" style="background:white; color:#0f172a; border:1px solid #e2e8f0; padding:7px 12px; font-size:13px;">+ Quản lý cấu trúc</button>

                        <?php if ($is_admin): ?>
                        <!-- Divider -->
                        <div style="width:1px; height:28px; background:#e2e8f0;"></div>
                        <!-- Alert Settings Dropdown -->
                        <div class="column-settings-dropdown">
                            <button class="btn-primary" style="background:white; color:#64748b; border:1px solid #e2e8f0; display:flex; align-items:center; gap:6px; padding:7px 12px; font-size:13px;">
                                &#9881; Cài đặt
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                            <div class="column-settings-content" style="min-width:180px;">
                                <div onclick="openRevSettingsModal()" style="display:flex; align-items:center; gap:10px; padding:9px 10px; cursor:pointer; border-radius:6px; font-size:13px; color:#4338ca; font-weight:600; transition:background 0.15s;" onmouseover="this.style.background='#f5f3ff'" onmouseout="this.style.background='transparent'">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.07 4.93a10 10 0 0 1 0 14.14M16.24 7.76a6 6 0 0 1 0 8.49M4.93 19.07a10 10 0 0 1 0-14.14M7.76 16.24a6 6 0 0 1 0-8.49"></path></svg>
                                    Cảnh báo Doanh thu
                                </div>
                                <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                                <div onclick="openCostSettingsModal()" style="display:flex; align-items:center; gap:10px; padding:9px 10px; cursor:pointer; border-radius:6px; font-size:13px; color:#991b1b; font-weight:600; transition:background 0.15s;" onmouseover="this.style.background='#fff1f2'" onmouseout="this.style.background='transparent'">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.07 4.93a10 10 0 0 1 0 14.14M16.24 7.76a6 6 0 0 1 0 8.49M4.93 19.07a10 10 0 0 1 0-14.14M7.76 16.24a6 6 0 0 1 0-8.49"></path></svg>
                                    Cảnh báo Chi phí
                                </div>
                                <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                                <div onclick="openGrandTotalSettingsModal()" style="display:flex; align-items:center; gap:10px; padding:9px 10px; cursor:pointer; border-radius:6px; font-size:13px; color:#0f766e; font-weight:600; transition:background 0.15s;" onmouseover="this.style.background='#ecfdf5'" onmouseout="this.style.background='transparent'">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    Cấu hình Tổng doanh thu
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <style>
                    .col-hidden { display: none !important; }
                    .hidden { display: none !important; }
                    .planning-active .actual-col { display: none !important; }
                    /* Default: Actions column is hidden unless Editing Mode is ON */
                    .actions-col { display: none; }
                    .editing-active .actions-col { display: table-cell !important; }
                    /* Radio styling */
                    .rev-status-radio {
                        appearance: none;
                        -webkit-appearance: none;
                        width: 18px;
                        height: 18px;
                        border: 2px solid #cbd5e1;
                        border-radius: 50%;
                        background: #fff;
                        cursor: pointer;
                        position: relative;
                        transition: all 0.2s;
                        margin: 0;
                        flex-shrink: 0;
                    }
                    .rev-status-radio:checked {
                        border-color: #2563eb;
                        background: #2563eb;
                    }
                    .rev-status-radio:checked::after {
                        content: '';
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 8px;
                        height: 8px;
                        background: #fff;
                        border-radius: 50%;
                    }
                    .rev-cell-inner {
                        display: flex;
                        align-items: center;
                        justify-content: flex-end;
                        gap: 10px;
                        width: 100%;
                    }
                    /* Highlighting */
                    .active-scenario { 
                        background-color: rgba(37, 99, 235, 0.04) !important;
                        box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.1);
                    }
                    th.active-scenario {
                        box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.05) !important;
                        position: relative;
                    }
                    /* Indicator dot for active scenario in header */
                    th.active-scenario::after {
                        content: '';
                        position: absolute;
                        bottom: 2px;
                        left: 50%;
                        transform: translateX(-50%);
                        width: 4px;
                        height: 4px;
                        border-radius: 50%;
                        background: currentColor;
                    }
                </style>

                <div class="card">
                    <?php if (!$has_actual_items): ?>
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
                                    <th id="sh-info" colspan="5" class="text-center" style="background:#1e3a8a!important; position: sticky; left: 0; z-index: 31; font-size: 14px; border-bottom: 1px dashed #ffffff !important;">Information</th>
                                    <th id="sh-income" colspan="6" class="text-center" style="background:#1e3a8a!important; font-size: 14px; border-bottom: 1px dashed #ffffff !important;">Income</th>
                                    <th id="sh-exp" colspan="11" class="text-center" style="background:#1e3a8a!important; font-size: 14px; border-bottom: 1px dashed #ffffff !important;">Expenses</th>
                                </tr>
                                <tr>
                                    <th rowspan="2" class="text-center col-stt" width="50" data-sh="info">STT</th>
                                    <th rowspan="2" class="col-block" data-sh="info">Khối</th>
                                    <th rowspan="2" class="col-dept" data-sh="info">Bộ phận/ Khoản chi</th>
                                    <th rowspan="2" class="text-center col-vtkt" data-sh="info">VTKT</th>
                                    <th rowspan="2" class="col-owner" data-sh="info">Owner</th>
                                    <th colspan="3" class="text-center col-rec" data-sh="income">Recognised Revenue</th>
                                    <th colspan="3" class="text-center col-inv" data-sh="income">Invoiced Revenue</th>
                                    <th colspan="3" class="text-center col-plan" data-sh="exp">Kế Hoạch Chi Quý <?php echo $current_quarter; ?></th>
                                    <th class="text-center col-month col-actual" data-sh="exp">Tháng <?php echo $months[0]; ?></th>
                                    <th class="text-center col-month col-actual" data-sh="exp">Tháng <?php echo $months[1]; ?></th>
                                    <th class="text-center col-month col-actual" data-sh="exp">Tháng <?php echo $months[2]; ?></th>
                                    <th rowspan="2" class="text-center col-actual" width="100" style="min-width: 140px;" data-sh="exp">Thực chi</th>
                                    <th rowspan="2" class="text-center actions-col" width="100" data-sh="exp">Thao tác</th>
                                </tr>
                                <tr>
                                    <th class="text-center col-rec <?php echo ($q_status['rec_status'] == 1 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Tốt
                                    </th>
                                    <th class="text-center col-rec <?php echo ($q_status['rec_status'] == 2 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Trung bình
                                    </th>
                                    <th class="text-center col-rec <?php echo ($q_status['rec_status'] == 3 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Xấu
                                    </th>
                                    <th class="text-center col-inv <?php echo ($q_status['inv_status'] == 1 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Tốt
                                    </th>
                                    <th class="text-center col-inv <?php echo ($q_status['inv_status'] == 2 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Trung bình
                                    </th>
                                    <th class="text-center col-inv <?php echo ($q_status['inv_status'] == 3 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Xấu
                                    </th>
                                    <th class="text-center col-plan <?php echo ($q_status['plan_status'] == 1 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Tốt
                                    </th>
                                    <th class="text-center col-plan <?php echo ($q_status['plan_status'] == 2 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Trung bình
                                    </th>
                                    <th class="text-center col-plan <?php echo ($q_status['plan_status'] == 3 ? 'active-scenario' : ''); ?>" style="font-size: 11px; width: 112px; min-width: 112px;">
                                        Xấu
                                    </th>
                                    <th class="actual-col col-actual">Thực chi</th>
                                    <th class="actual-col col-actual">Thực chi</th>
                                    <th class="actual-col col-actual">Thực chi</th>
                                </tr>
                            </thead>
                            <tbody id="budget-body">
                                <?php 
                                // Grand Totals were already calculated and filtered in the earlier logic loop
                                ?>
                                <tr class="row-total" data-type="grand-total">
                                    <td class="text-center col-stt" style="background: #1e3a8a !important; color: white !important;">#</td>
                                    <td class="col-block" style="background: #1e3a8a !important;"></td>
                                    <td class="col-dept" style="background: #1e3a8a !important; color: white !important; font-weight: 700;">TỔNG CỘNG TOÀN BỘ</td>
                                    <td class="col-vtkt" style="background: #1e3a8a !important;"></td>
                                    <td class="col-owner" style="background: #1e3a8a !important;"></td>
                                    <td class="text-right col-rec" style="background: #1e3a8a !important; color: white !important;"><?php echo format_vnd($grand_totals['_rev_']['rec_rev_good'] ?? 0); ?></td>
                                    <td class="text-right col-rec" style="background: #1e3a8a !important; color: white !important;"><?php echo format_vnd($grand_totals['_rev_']['rec_rev_avg'] ?? 0); ?></td>
                                    <td class="text-right col-rec" style="background: #1e3a8a !important; color: white !important;"><?php echo format_vnd($grand_totals['_rev_']['rec_rev_bad'] ?? 0); ?></td>
                                    <td class="text-right col-inv" style="background: #1e3a8a !important; color: white !important;"><?php echo format_vnd($grand_totals['_rev_']['inv_rev_good'] ?? 0); ?></td>
                                    <td class="text-right col-inv" style="background: #1e3a8a !important; color: white !important;"><?php echo format_vnd($grand_totals['_rev_']['inv_rev_avg'] ?? 0); ?></td>
                                    <td class="text-right col-inv" style="background: #1e3a8a !important; color: white !important;"><?php echo format_vnd($grand_totals['_rev_']['inv_rev_bad'] ?? 0); ?></td>
                                    <?php 
                                    $p_status = $q_status['plan_status'] ?? 2; 
                                    if ($p_status == 0) $p_status = 2; // Default to Average
                                    $p_key = ($p_status == 1 ? 'planned_good' : ($p_status == 3 ? 'planned_bad' : 'planned_avg'));
                                    
                                    $gt_planned = $grand_totals[0][$p_key] ?? 0;
                                    $gt_actual_m1 = ($grand_totals[$months[0]]['actual_salary'] ?? 0) + ($grand_totals[$months[0]]['actual_other'] ?? 0);
                                    $gt_actual_m2 = ($grand_totals[$months[1]]['actual_salary'] ?? 0) + ($grand_totals[$months[1]]['actual_other'] ?? 0);
                                    $gt_actual_m3 = ($grand_totals[$months[2]]['actual_salary'] ?? 0) + ($grand_totals[$months[2]]['actual_other'] ?? 0);
                                    $gt_total_actual = $gt_actual_m1 + $gt_actual_m2 + $gt_actual_m3;
                                    $gt_percent = ($gt_planned > 0) ? ($gt_total_actual / $gt_planned) * 100 : 0;
                                    ?>
                                    <td class="text-right col-plan <?php echo ($q_status['plan_status'] == 1 ? 'active-scenario' : ''); ?>" data-month="0" data-type="planned_good" style="background: #1e3a8a !important; color: white !important; font-weight: 700;"><?php echo number_format($grand_totals[0]['planned_good'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right col-plan <?php echo ($q_status['plan_status'] == 2 ? 'active-scenario' : ''); ?>" data-month="0" data-type="planned_avg" style="background: #1e3a8a !important; color: white !important; font-weight: 700;"><?php echo number_format($grand_totals[0]['planned_avg'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right col-plan <?php echo ($q_status['plan_status'] == 3 ? 'active-scenario' : ''); ?>" data-month="0" data-type="planned_bad" style="background: #1e3a8a !important; color: white !important; font-weight: 700;"><?php echo number_format($grand_totals[0]['planned_bad'] ?? 0, 0, ',', '.'); ?> đ</td>
                                    <td class="text-right actual-col col-actual" data-month="<?php echo $months[0]; ?>" style="background: #1e3a8a !important; color: white !important;"><?php echo number_format(($grand_totals[$months[0]]['actual_salary'] ?? 0) + ($grand_totals[$months[0]]['actual_other'] ?? 0), 0, ',', '.'); ?> đ</td>
                                    <td class="text-right actual-col col-actual" data-month="<?php echo $months[1]; ?>" style="background: #1e3a8a !important; color: white !important;"><?php echo number_format(($grand_totals[$months[1]]['actual_salary'] ?? 0) + ($grand_totals[$months[1]]['actual_other'] ?? 0), 0, ',', '.'); ?> đ</td>
                                    <td class="text-right actual-col col-actual" data-month="<?php echo $months[2]; ?>" style="background: #1e3a8a !important; color: white !important;"><?php echo number_format(($grand_totals[$months[2]]['actual_salary'] ?? 0) + ($grand_totals[$months[2]]['actual_other'] ?? 0), 0, ',', '.'); ?> đ</td>
                                    <td class="text-right col-actual" style="background: #1e3a8a !important; color: white !important; font-weight: 700;">
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
                                     
                                     $planned = get_display_val($row, $values, $div_totals, $cat_totals, 0, $p_key);
                                     $actual_m1 = get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_other');
                                     $actual_m2 = get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_other');
                                     $actual_m3 = get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_other');
                                     $total_actual = $actual_m1 + $actual_m2 + $actual_m3;
                                     
                                     $percent = ($planned > 0) ? ($total_actual / $planned) * 100 : 0;
                                     $row_bg = '';
                                     $data_bg = '';
                                     if ($row['type'] === 'division') {
                                         // Level 1: Sky Blue BG / Black Text (always)
                                         $row_bg = 'background-color: #bfdbfe !important; color: #000000 !important; font-weight: 800; border-bottom: 2px solid #93c5fd;'; 
                                         if ($percent >= $cost_red) { $row_bg .= ' border-left: 6px solid #ef4444;'; $data_bg = 'background-color: #ffcccc !important;'; }
                                         elseif ($percent >= $cost_yellow) { $row_bg .= ' border-left: 6px solid #f59e0b;'; $data_bg = 'background-color: #fde047 !important;'; }
                                     } elseif ($row['type'] === 'category') {
                                         // Level 2: Slate BG / Black Text (always)
                                         $row_bg = 'background-color: #f8fafc !important; color: #000000 !important; font-weight: 600; border-bottom: 1px dashed #cbd5e1;';
                                         if ($percent >= $cost_red) { $data_bg = 'background-color: #ffcccc !important;'; }
                                         elseif ($percent >= $cost_yellow) { $data_bg = 'background-color: #fde047 !important;'; }
                                     } else {
                                         // Level 3: Item (Peach BG) / Black Text (always)
                                         $row_bg = 'background-color: #fff7ed !important; color: #000000 !important; border-bottom: 1px dashed #fed7aa;'; 
                                         if ($percent >= $cost_red) $data_bg = 'background-color: #ffcccc !important;'; 
                                         elseif ($percent >= $cost_yellow) $data_bg = 'background-color: #fde047 !important;';
                                     }
                                 ?>
                                 <tr class="<?php echo $row_class; ?>" 
                                     style="<?php echo $row_bg; ?>"
                                     data-type="<?php echo $row['type']; ?>"
                                     data-div="<?php echo ($row['type'] === 'division' ? $this_name : $p_div); ?>"
                                     data-cat="<?php echo ($row['type'] === 'category' ? $this_name : $p_cat); ?>">
                                     <?php $cell_style = ''; // Removed transparent background hack which broke sticky layering ?>
                                     
                                     <td class="text-center col-stt" style="<?php echo $cell_style; ?>"><?php echo ($row['type'] === 'division' ? $idx : ''); ?></td>
                                     <td class="col-block" style="font-weight: 600; <?php echo $cell_style; ?>">
                                         <?php echo ($row['type'] !== 'division' ? htmlspecialchars($p_div) : ''); ?>
                                     </td>
                                     <td class="col-dept" style="padding-left: <?php echo ($row['type'] === 'category' ? '25px' : ($row['type'] === 'item' ? '45px' : '8px')); ?>; font-weight: 700; <?php echo $cell_style; ?>">
                                         <?php 
                                            if ($row['type'] === 'division') {
                                                echo htmlspecialchars($row['item_name']);
                                            } else {
                                                $prefix = '';
                                                if ($row['type'] === 'category') $prefix = '• ';
                                                if ($row['type'] === 'item') $prefix = '- ';
                                                echo $prefix . htmlspecialchars($row['item_name']); 
                                            }
                                         ?>
                                     </td>
                                     <td class="text-center col-vtkt <?php echo trim($row['acct_abbreviation'] ?? '') !== '' ? 'clickable-vtkt' : ''; ?>" 
                                         <?php if(trim($row['acct_abbreviation'] ?? '') !== ''): ?>
                                         onclick="openDrilldownSidebar(<?php echo $row['id']; ?>, <?php echo $current_year; ?>, <?php echo $current_quarter; ?>)"
                                         <?php endif; ?>
                                         style="font-size: 11px; color: #64748b; font-weight: 600; <?php echo $cell_style; ?>">
                                         <?php echo htmlspecialchars($row['acct_abbreviation'] ?? ''); ?>
                                     </td>
                                     <td class="col-owner" style="cursor: pointer; <?php echo $cell_style; ?>" onclick="openOwnerSidebar('<?php echo addslashes($row['owner'] ?? ''); ?>', <?php echo $current_year; ?>, <?php echo $current_quarter; ?>)">
                                         <?php 
                                            $owner_parts = explode(' ', trim($row['owner'] ?? ''));
                                            echo htmlspecialchars($owner_parts[0]);
                                         ?>
                                     </td>
                                     
                                     <?php $is_editable = ($row['type'] === 'item' ? 'editable-cell' : ''); ?>
                                     
                                     <!-- Recognised Revenue Data -->
                                     <?php $val = get_rev_val($row, $div_totals, $cat_totals, 'rec_rev_good'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-rec <?php echo ($q_status['rec_status'] == 1 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="rec_rev_good" style="font-size: 11px; <?php echo get_rev_bg_color($val, $planned, $rev_red, $rev_yellow, $rev_green); ?>"><?php echo format_vnd($val); ?></td>
                                     <?php $val = get_rev_val($row, $div_totals, $cat_totals, 'rec_rev_avg'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-rec <?php echo ($q_status['rec_status'] == 2 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="rec_rev_avg" style="font-size: 11px; <?php echo get_rev_bg_color($val, $planned, $rev_red, $rev_yellow, $rev_green); ?>"><?php echo format_vnd($val); ?></td>
                                     <?php $val = get_rev_val($row, $div_totals, $cat_totals, 'rec_rev_bad'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-rec <?php echo ($q_status['rec_status'] == 3 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="rec_rev_bad" style="font-size: 11px; <?php echo get_rev_bg_color($val, $planned, $rev_red, $rev_yellow, $rev_green); ?>"><?php echo format_vnd($val); ?></td>
                                     
                                     <!-- Invoiced Revenue Data -->
                                     <?php $val = get_rev_val($row, $div_totals, $cat_totals, 'inv_rev_good'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-inv <?php echo ($q_status['inv_status'] == 1 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="inv_rev_good" style="font-size: 11px; <?php echo get_rev_bg_color($val, $planned, $rev_red, $rev_yellow, $rev_green); ?>"><?php echo format_vnd($val); ?></td>
                                     <?php $val = get_rev_val($row, $div_totals, $cat_totals, 'inv_rev_avg'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-inv <?php echo ($q_status['inv_status'] == 2 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="inv_rev_avg" style="font-size: 11px; <?php echo get_rev_bg_color($val, $planned, $rev_red, $rev_yellow, $rev_green); ?>"><?php echo format_vnd($val); ?></td>
                                     <?php $val = get_rev_val($row, $div_totals, $cat_totals, 'inv_rev_bad'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-inv <?php echo ($q_status['inv_status'] == 3 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="inv_rev_bad" style="font-size: 11px; <?php echo get_rev_bg_color($val, $planned, $rev_red, $rev_yellow, $rev_green); ?>"><?php echo format_vnd($val); ?></td>
                                     
                                     <?php $is_editable = ($row['type'] === 'item' ? 'editable-cell' : ''); ?>
                                     
                                     <?php $p_good = get_display_val($row, $values, $div_totals, $cat_totals, 0, 'planned_good'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-plan <?php echo ($q_status['plan_status'] == 1 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="planned_good" style="font-weight:700; <?php echo ($row['type'] === 'division' ? '' : 'color:#0f172a;'); ?> <?php echo $data_bg; ?>">
                                         <?php echo format_vnd($p_good); ?>
                                     </td>
                                     <?php $p_avg = get_display_val($row, $values, $div_totals, $cat_totals, 0, 'planned_avg'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-plan <?php echo ($q_status['plan_status'] == 2 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="planned_avg" style="font-weight:700; <?php echo ($row['type'] === 'division' ? '' : 'color:#0f172a;'); ?> <?php echo $data_bg; ?>">
                                         <?php echo format_vnd($p_avg); ?>
                                     </td>
                                     <?php $p_bad = get_display_val($row, $values, $div_totals, $cat_totals, 0, 'planned_bad'); ?>
                                     <td class="text-right <?php echo $is_editable; ?> col-plan <?php echo ($q_status['plan_status'] == 3 ? 'active-scenario' : ''); ?>" data-iid="<?php echo $iid; ?>" data-month="0" data-type="planned_bad" style="font-weight:700; <?php echo ($row['type'] === 'division' ? '' : 'color:#0f172a;'); ?> <?php echo $data_bg; ?>">
                                         <?php echo format_vnd($p_bad); ?>
                                     </td>
                                     
                                     <td class="text-right <?php echo $is_editable; ?> actual-col col-actual" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[0]; ?>" data-type="actual_salary" style="<?php echo $data_bg; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[0], 'actual_other')); ?>
                                     </td>
                                     <td class="text-right <?php echo $is_editable; ?> actual-col col-actual" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[1]; ?>" data-type="actual_salary" style="<?php echo $data_bg; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[1], 'actual_other')); ?>
                                     </td>
                                     <td class="text-right <?php echo $is_editable; ?> actual-col col-actual" data-iid="<?php echo $iid; ?>" data-month="<?php echo $months[2]; ?>" data-type="actual_salary" style="<?php echo $data_bg; ?>">
                                         <?php echo format_vnd(get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_salary') + get_display_val($row, $values, $div_totals, $cat_totals, $months[2], 'actual_other')); ?>
                                     </td>
                                     
                                     <td class="text-right actual-col col-actual" style="font-weight:700; width:120px; <?php echo $data_bg; ?>">
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

    <!-- Drilldown Sidebar -->
    <div class="drilldown-overlay" id="drilldownOverlay" onclick="closeDrilldownSidebar()"></div>
    <div class="drilldown-sidebar" id="drilldownSidebar">
        <div class="drilldown-header">
            <div>
                <div id="dd-title" style="font-size: 20px; font-weight: 700; margin-bottom: 4px;">Detail Drilldown</div>
                <div id="dd-subtitle" style="font-size: 13px; opacity: 0.9; font-weight: 500;">Comparision with previous quarter</div>
            </div>
            <button class="drilldown-close" onclick="closeDrilldownSidebar()">&times;</button>
        </div>
        <div class="drilldown-content">
            <div class="drilldown-section">
                <div class="drilldown-title">Thông tin chung</div>
                <div id="dd-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <!-- General info pops up here -->
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 2px;">Khối</div>
                        <div id="dd-info-div" style="font-size: 13px; font-weight: 600; color: #1e293b;">-</div>
                    </div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 2px;">Bộ phận</div>
                        <div id="dd-info-cat" style="font-size: 13px; font-weight: 600; color: #1e293b;">-</div>
                    </div>
                </div>
            </div>

            <div class="drilldown-section">
                <div class="drilldown-title">So sánh với Quý trước</div>
                <div class="comparison-grid">
                    <div class="comp-card">
                        <div class="comp-period" id="dd-period-prev">Quý trước</div>
                        <div class="comp-value" id="dd-value-prev">0 đ</div>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Tổng chi phí thực tế</div>
                    </div>
                    <div class="comp-card" style="border-color: #3b82f633; background: #eff6ff88;">
                        <div class="comp-period" id="dd-period-curr">Quý này</div>
                        <div class="comp-value" id="dd-value-curr">0 đ</div>
                        <div id="dd-compare-stat" class="comp-diff">
                            <!-- Comparison percentage here -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="drilldown-section">
                <div class="drilldown-title">Recognised Revenue (3 Scenarios)</div>
                <div id="dd-chart-rec" style="background: #fff; border-radius: 12px; padding: 10px; min-height: 180px;"></div>
            </div>

            <div class="drilldown-section">
                <div class="drilldown-title">Invoiced Revenue (3 Scenarios)</div>
                <div id="dd-chart-inv" style="background: #fff; border-radius: 12px; padding: 10px; min-height: 180px;"></div>
            </div>

            <div class="drilldown-section">
                <div class="drilldown-title">Expense Breakdown</div>
                <div id="dd-chart-expenses" style="background: #fff; border-radius: 12px; padding: 10px; min-height: 180px;"></div>
            </div>

            <div class="drilldown-section">
                <div class="drilldown-title">Chi tiết số liệu</div>
                <div id="dd-details-table-wrap" style="overflow-x: auto;">
                    <!-- Detailed monthly comparison table -->
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0;">Tháng</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #e2e8f0;">Quý trước</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #e2e8f0;">Quý này</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #e2e8f0;">Biến động</th>
                            </tr>
                        </thead>
                        <tbody id="dd-table-body">
                            <!-- Rows pop up here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- OWNER PERFORMANCE SIDEBAR -->
    <div id="ownerSidebar" class="drilldown-sidebar">
        <div class="drilldown-header" style="background: #1e3a8a; border-bottom: 1px solid #3b82f6;">
            <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.1em;">Owner Performance</div>
            <div id="os-title" style="font-size: 24px; font-weight: 800; color: #ffffff;">Owner Name</div>
            <div id="os-period" style="font-size: 13px; color: #cbd5e1; margin-top: 4px; font-weight: 500;">Dữ liệu quý hiện tại</div>
        </div>
        <div class="drilldown-content">
            <!-- User Information Section -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px;">
                <div id="os-avatar-initial" style="width: 60px; height: 60px; background: #1e3a8a; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; text-transform: uppercase;">M</div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span id="os-info-name" style="font-size: 18px; font-weight: 800; color: #1e293b;"></span>
                        <span id="os-info-abbr" style="background: #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 100px; text-transform: uppercase;"></span>
                    </div>
                    <div style="font-size: 13px; color: #64748b; margin-bottom: 8px;">
                        <i class="fas fa-sitemap" style="width: 16px;"></i> <span id="os-info-division"></span>
                    </div>
                    <div style="display: flex; gap: 16px;">
                        <div style="font-size: 12px; color: #1e293b; font-weight: 600;">
                            <span id="os-info-count" style="color: #3b82f6;">0</span> hạng mục đang quản lý
                        </div>
                    </div>
                </div>
            </div>

            <div class="drilldown-section">
                <div class="drilldown-title">Hạng mục sở hữu</div>
                <div id="os-table-wrap" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px; min-width: 900px;">
                        <thead id="os-table-head"></thead>
                        <tbody id="os-table-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="drilldown-section" style="margin-top: 32px;">
                <div class="drilldown-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Lịch sử cập nhật</span>
                    <i class="fas fa-history" style="color: #94a3b8; font-size: 14px;"></i>
                </div>
                <div id="os-logs-container" style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px;"></div>
                <button id="os-load-more" onclick="loadUserLogs()" style="width: 100%; margin-top: 16px; padding: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 600; color: #64748b; cursor: pointer; display: none;">Xem thêm lịch sử</button>
            </div>
        </div>
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
                    <div style="position:relative;">
                        <input type="text" name="division" id="modalDivision" autocomplete="off" placeholder="Chọn hoặc nhập mới..." class="form-control" style="width:100%; padding:10px 30px 10px 10px; border:1px solid #cbd5e1; border-radius:8px;" 
                               onfocus="showCustomDropdown('modalDivision', 'div-dropdown', ALL_DIVS)" 
                               oninput="showCustomDropdown('modalDivision', 'div-dropdown', ALL_DIVS)" 
                               onblur="setTimeout(()=>document.getElementById('div-dropdown').style.display='none', 200)">
                        <button type="button" tabindex="-1" onclick="document.getElementById('modalDivision').value=''; showCustomDropdown('modalDivision', 'div-dropdown', ALL_DIVS); document.getElementById('modalDivision').focus(); document.getElementById('modalCategory').value = '';" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:none; border:none; color:#cbd5e1; font-size:18px; cursor:pointer; padding:5px;">&times;</button>
                        <div id="div-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:100; max-height:200px; overflow-y:auto; margin-top:5px;"></div>
                    </div>
                </div>
                
                <div style="margin-bottom:15px;" id="catGroup">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Bộ phận cha (Level 2)</label>
                    <div style="position:relative;">
                        <input type="text" name="category" id="modalCategory" autocomplete="off" placeholder="Chọn hoặc nhập mới..." class="form-control" style="width:100%; padding:10px 30px 10px 10px; border:1px solid #cbd5e1; border-radius:8px;"
                               onfocus="showCustomDropdown('modalCategory', 'cat-dropdown', ALL_CATS)" 
                               oninput="showCustomDropdown('modalCategory', 'cat-dropdown', ALL_CATS)" 
                               onblur="setTimeout(()=>document.getElementById('cat-dropdown').style.display='none', 200)">
                        <button type="button" tabindex="-1" onclick="document.getElementById('modalCategory').value=''; showCustomDropdown('modalCategory', 'cat-dropdown', ALL_CATS); document.getElementById('modalCategory').focus();" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:none; border:none; color:#cbd5e1; font-size:18px; cursor:pointer; padding:5px;">&times;</button>
                        <div id="cat-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:100; max-height:200px; overflow-y:auto; margin-top:5px;"></div>
                    </div>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Tên hiển thị</label>
                    <input type="text" name="item_name" id="modalName" placeholder="Ví dụ: BD Holdings..." class="form-control" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Owner (Người phụ trách)</label>
                    <div style="position:relative;">
                        <input type="text" name="owner" id="modalOwner" autocomplete="off" placeholder="Chọn từ danh sách user..." class="form-control" style="width:100%; padding:10px 30px 10px 10px; border:1px solid #cbd5e1; border-radius:8px;"
                               onfocus="showCustomDropdown('modalOwner', 'user-dropdown', ALL_USERS)" 
                               oninput="showCustomDropdown('modalOwner', 'user-dropdown', ALL_USERS)" 
                               onblur="setTimeout(()=>document.getElementById('user-dropdown').style.display='none', 200)">
                        <button type="button" tabindex="-1" onclick="document.getElementById('modalOwner').value=''; showCustomDropdown('modalOwner', 'user-dropdown', ALL_USERS); document.getElementById('modalOwner').focus();" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:none; border:none; color:#cbd5e1; font-size:18px; cursor:pointer; padding:5px;">&times;</button>
                        <div id="user-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:100; max-height:200px; overflow-y:auto; margin-top:5px;"></div>
                    </div>
                </div>
                
                <div style="margin-bottom:15px;" id="acctAbbrGroup">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#475569;">Khoản mục Viết tắt theo Kế toán</label>
                    <input type="text" name="acct_abbreviation" id="modalAcctAbbr" placeholder="Ví dụ: Lương, HĐ khác..." class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
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

    <?php if ($is_admin): ?>
    <!-- Revenue Alert Settings Modal -->
    <div id="revSettingsModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; margin:5% auto; padding:25px; width:450px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; font-size:18px; color:#1e293b;">Cấu hình màu Cảnh báo</h3>
                <button onclick="document.getElementById('revSettingsModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
            </div>
            <form onsubmit="saveRevSettings(event)">
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#991b1b;">Màu Đỏ (&lt;= X%)</label>
                    <input type="number" id="set_red" value="<?php echo $rev_red; ?>" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#854d0e;">Màu Vàng (&lt;= Y%)</label>
                    <input type="number" id="set_yellow" value="<?php echo $rev_yellow; ?>" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#166534;">Màu Xanh (&lt;= Z%) <br><span style="color:#6b21a8; font-weight: 500;">(Lớn hơn Z% là màu Tím)</span></label>
                    <input type="number" id="set_green" value="<?php echo $rev_green; ?>" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                    <button type="submit" style="padding:10px 20px; background:#4f46e5; border:none; border-radius:8px; font-weight:600; color:#fff; cursor:pointer;">Lưu cài đặt</button>
                </div>
            </form>
        </div>
    </div>
    <div id="costSettingsModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; margin:5% auto; padding:25px; width:450px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; font-size:18px; color:#1e293b;">Cấu hình màu Cảnh báo Chi Phí</h3>
                <button onclick="document.getElementById('costSettingsModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
            </div>
            <form onsubmit="saveCostSettings(event)">
                <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Dựa trên tỷ lệ Thực chi / Kế Hoạch (%).</p>
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#854d0e;">Màu Vàng (>= Y%)</label>
                    <input type="number" id="set_cost_yellow" value="<?php echo $cost_yellow; ?>" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:#991b1b;">Màu Đỏ (>= X%)</label>
                    <input type="number" id="set_cost_red" value="<?php echo $cost_red; ?>" class="form-control" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                    <button type="submit" style="padding:10px 20px; background:#4f46e5; border:none; border-radius:8px; font-weight:600; color:#fff; cursor:pointer;">Lưu cài đặt</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Grand Total Settings Modal -->
    <?php
    $all_divisions = [];
    foreach ($structure as $row) {
        if ($row['type'] === 'division' && !empty($row['item_name'])) {
            $all_divisions[] = trim($row['item_name']);
        }
    }
    $all_divisions = array_unique($all_divisions);
    ?>
    <div id="grandTotalSettingsModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; margin:10vh auto; padding:25px; width:450px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; font-size:18px; color:#1e293b;">Cấu hình Tính tổng</h3>
                <button onclick="document.getElementById('grandTotalSettingsModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
            </div>
            <form onsubmit="saveGrandTotalSettings(event)">
                <p style="font-size:13px; color:#64748b; margin-bottom:15px;">Chọn các khối sẽ được cộng dồn vào dòng TỔNG CỘNG TOÀN BỘ (áp dụng cho các cột Doanh thu).</p>
                <div style="max-height: 250px; overflow-y: auto; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px;">
                    <?php if (empty($all_divisions)): ?>
                        <div style="font-size:13px; color:#94a3b8; font-style:italic;">Chưa có khối nào trong cấu trúc ngân sách.</div>
                    <?php else: ?>
                        <?php foreach ($all_divisions as $div): 
                            $isChecked = ($grand_total_blocks === null || in_array($div, $grand_total_blocks)) ? 'checked' : '';
                        ?>
                        <label style="display:flex; margin-bottom:10px; font-size:14px; color:#334155; cursor:pointer; align-items:center; gap:8px;">
                            <input type="checkbox" name="gt_blocks[]" value="<?php echo htmlspecialchars($div); ?>" <?php echo $isChecked; ?> style="width:16px; height:16px; cursor:pointer; accent-color:#0f766e;">
                            <?php echo htmlspecialchars($div); ?>
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:12px;">
                    <button type="submit" style="padding:10px 20px; background:#0f766e; border:none; border-radius:8px; font-weight:600; color:#fff; cursor:pointer; transition: background 0.15s;" onmouseover="this.style.background='#0d9488'" onmouseout="this.style.background='#0f766e'">Lưu cấu hình</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const REV_THRESHOLDS = {
            red: <?php echo $rev_red; ?>,
            yellow: <?php echo $rev_yellow; ?>,
            green: <?php echo $rev_green; ?>
        };

        function openRevSettingsModal() {
            document.getElementById('revSettingsModal').style.display = 'block';
        }
        function saveRevSettings(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'save_rev_settings');
            fd.append('red', document.getElementById('set_red').value);
            fd.append('yellow', document.getElementById('set_yellow').value);
            fd.append('green', document.getElementById('set_green').value);
            fetch(location.href, { method: 'POST', body: fd })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch(err) {
                    console.error("Lỗi Parse JSON:", text);
                    throw new Error("Dữ liệu trả về không đúng định dạng. F12 kiểm tra console.");
                }
            })
            .then(res => {
                if(res.success) location.reload();
                else alert('Lỗi: '+ res.error);
            })
            .catch(err => alert("Lỗi hệ thống: " + err.message));
        }

        function openCostSettingsModal() {
            document.getElementById('costSettingsModal').style.display = 'block';
        }

        function saveCostSettings(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'save_cost_settings');
            fd.append('yellow', document.getElementById('set_cost_yellow').value);
            fd.append('red', document.getElementById('set_cost_red').value);
            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.success) location.reload();
                else alert('Lỗi: '+ res.error);
            })
            .catch(err => alert("Lỗi hệ thống: " + err.message));
        }

        function openGrandTotalSettingsModal() {
            document.getElementById('grandTotalSettingsModal').style.display = 'block';
        }

        function saveGrandTotalSettings(e) {
            e.preventDefault();
            const form = e.target;
            const checkboxes = form.querySelectorAll('input[name="gt_blocks[]"]:checked');
            const blocks = Array.from(checkboxes).map(cb => cb.value);
            
            const fd = new FormData();
            fd.append('action', 'save_grand_total_settings');
            blocks.forEach(b => fd.append('blocks[]', b));
            
            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.success) location.reload();
                else alert('Lỗi: '+ res.error);
            })
            .catch(err => alert("Lỗi hệ thống: " + err.message));
        }

        function updateRevenueStatusGlobal(section, status) {
            const fd = new FormData();
            fd.append('action', 'update_revenue_status');
            fd.append('section', section);
            fd.append('status', status);

            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.success) location.reload();
                else alert('Lỗi: ' + (res.error || 'Thao tác thất bại'));
            });
        }

        <?php 
        $all_unique_divs = [];
        foreach ($structure as $row) {
            if ($row['type'] === 'division' && trim($row['item_name']) !== '') {
                $all_unique_divs[] = trim($row['item_name']);
            }
            if (trim($row['division']) !== '') {
                $all_unique_divs[] = trim($row['division']);
            }
        }
        $unique_divs = array_unique($all_unique_divs);
        ?>
        const ALL_DIVS = <?php echo json_encode(array_values($unique_divs)); ?>;
        const ALL_USERS = <?php echo json_encode(array_values(array_column($users, 'full_name'))); ?>;
        const CAT_BY_DIV = <?php echo json_encode($div_cat_map_for_js); ?>;
        const ALL_CATS = <?php echo json_encode($all_cats_for_js); ?>;
        
        const normalizeStr = s => (s||'').toLowerCase().replace(/\s+/g, ' ').trim();
        const CAT_BY_DIV_LOWER = {};
        for(let k in CAT_BY_DIV) CAT_BY_DIV_LOWER[normalizeStr(k)] = CAT_BY_DIV[k];

        function showCustomDropdown(inputId, listId, dataArray) {
            const input = document.getElementById(inputId);
            const drop = document.getElementById(listId);
            const type = document.getElementById('modalType').value;
            const val = input.value.trim().toLowerCase();
            
            let sourceArray = dataArray;
            
            // Only apply strict filtering logic for categories when adding/editing a level 3 item
            if (inputId === 'modalCategory' && type === 'item') {
                const divInputRaw = document.getElementById('modalDivision').value;
                const divInput = normalizeStr(divInputRaw);
                
                if (divInput && CAT_BY_DIV_LOWER[divInput]) {
                    sourceArray = CAT_BY_DIV_LOWER[divInput];
                } else if (divInput) {
                    sourceArray = []; // strict filtering: unknown division means no known categories
                } else {
                    sourceArray = ALL_CATS; // fallback
                }
            }

            const filtered = sourceArray.filter(x => x.toLowerCase().includes(val));
            drop.innerHTML = '';
            
            if (filtered.length === 0) {
                drop.style.display = 'none';
                return;
            }
            
            filtered.forEach(item => {
                const el = document.createElement('div');
                el.textContent = item;
                el.style.padding = '10px 15px';
                el.style.cursor = 'pointer';
                el.style.fontSize = '13px';
                el.style.color = '#334155';
                el.style.borderBottom = '1px solid #f8fafc';
                el.style.transition = 'background 0.2s';
                
                el.onmouseover = () => el.style.background = '#f1f5f9';
                el.onmouseout = () => el.style.background = '#fff';
                el.onmousedown = (e) => { 
                    e.preventDefault(); // Prevents input blur from firing before click registers
                    input.value = item;
                    drop.style.display = 'none';
                    if (inputId === 'modalDivision') {
                        // clear category if division changes
                        document.getElementById('modalCategory').value = '';
                    }
                };
                drop.appendChild(el);
            });
            
            drop.style.display = 'block';
        }

        function updateCatDatalist() {
            // Deprecated logic. Now handled directly in showCustomDropdown
        }

        const structureModal = document.getElementById('structureModal');
        const structureForm = document.getElementById('structureForm');

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Thêm cấu trúc ngân sách';
            document.getElementById('modalAction').value = 'add_item';
            document.getElementById('modalId').value = '';
            structureForm.reset();
            toggleModalFields();
            updateCatDatalist();
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
            document.getElementById('modalAcctAbbr').value = row.acct_abbreviation || '';
            document.getElementById('modalOrder').value = row.order_num;
            toggleModalFields();
            updateCatDatalist();
            structureModal.style.display = 'block';
        }

        function closeStructureModal() {
            structureModal.style.display = 'none';
        }

        function toggleModalFields() {
            const type = document.getElementById('modalType').value;
            const divGrp = document.getElementById('divGroup');
            const catGrp = document.getElementById('catGroup');
            const acctGrp = document.getElementById('acctAbbrGroup');
            
            if (type === 'division') {
                divGrp.style.display = 'none';
                catGrp.style.display = 'none';
                acctGrp.style.display = 'none';
            } else if (type === 'category') {
                divGrp.style.display = 'block';
                catGrp.style.display = 'none';
                acctGrp.style.display = 'none';
            } else {
                divGrp.style.display = 'block';
                catGrp.style.display = 'block';
                acctGrp.style.display = 'block';
            }
            // Ensure datalist refilters if type changes
            updateCatDatalist();
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
                    if (type.startsWith('rec_rev') || type.startsWith('inv_rev')) {
                        const row = cell.closest('tr');
                        const planCell = row.querySelector('.col-plan.active-scenario') || row.querySelector('.col-plan');
                        const planVal = parseInt((planCell ? planCell.innerText : '0').replace(/[^\d]/g, '')) || 0;
                        if (planVal > 0 && amount > 0) {
                            const pct = (amount / planVal) * 100;
                            cell.style.cssText = '';
                            if (pct > REV_THRESHOLDS.green) cell.style.cssText = 'background-color: #f3e8ff !important; color: #6b21a8 !important; font-weight: bold; border: 1px dashed #d8b4fe !important; outline: 1px solid #d8b4fe;';
                            else if (pct > REV_THRESHOLDS.yellow) cell.style.cssText = 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold; border: 1px dashed #bbf7d0 !important; outline: 1px solid #bbf7d0;';
                            else if (pct > REV_THRESHOLDS.red) cell.style.cssText = 'background-color: #fef08a !important; color: #854d0e !important; font-weight: bold; border: 1px dashed #fde047 !important; outline: 1px solid #fde047;';
                            else cell.style.cssText = 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold; border: 1px dashed #fecaca !important; outline: 1px solid #fecaca;';
                        } else {
                            cell.style.cssText = '';
                        }
                    } else {
                        cell.style.backgroundColor = '#dcfce7';
                        setTimeout(() => cell.style.backgroundColor = '', 1000);
                    }
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
                gtRow.querySelectorAll('td[data-month]:not([data-type^="planned"])').forEach(c => total += parseInt(c.innerText.replace(/[^\d]/g, '')) || 0);
                gtRow.cells[gtRow.cells.length - 2].innerText = formatVND(total);
                const plCell = gtRow.querySelector('td[data-type^="planned"].active-scenario') || gtRow.querySelector('td[data-type^="planned"]');
                const pl = parseInt((plCell ? plCell.innerText : '0').replace(/[^\d]/g, '')) || 0;
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

        function updateSuperHeaders() {
            ['info', 'income', 'exp'].forEach(shId => {
                const sh = document.getElementById('sh-' + shId);
                if (sh) {
                    let span = 0;
                    document.querySelectorAll(`th[data-sh="${shId}"]`).forEach(th => {
                        const isHiddenByToggle = th.classList.contains('col-hidden');
                        const style = window.getComputedStyle(th);
                        if (!isHiddenByToggle && style.display !== 'none') {
                            span += th.colSpan || 1;
                        }
                    });
                    sh.colSpan = span;
                    sh.style.display = span > 0 ? 'table-cell' : 'none';
                }
            });
        }

        function togglePlanningMode(active) {
            const table = document.querySelector('.budget-table');
            if (active) table.classList.add('planning-active');
            else table.classList.remove('planning-active');
            localStorage.setItem('budget_planning_mode', active);
            updateSuperHeaders();
        }

        function toggleEditingMode(active) {
            const table = document.querySelector('.budget-table');
            if (active) table.classList.add('editing-active');
            else table.classList.remove('editing-active');
            localStorage.setItem('budget_editing_mode', active);
            updateSuperHeaders();
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
        
        function toggleCol(colClass, show) {
            document.querySelectorAll('.' + colClass).forEach(el => {
                if (show) el.classList.remove('col-hidden');
                else el.classList.add('col-hidden');
            });
            
            // Handle sticky gaps
            const table = document.querySelector('.budget-table');
            if (colClass === 'col-block') {
                if (!show) table.classList.add('block-hidden');
                else table.classList.remove('block-hidden');
             }
            
            localStorage.setItem('budget_col_' + colClass, show);
            updateSuperHeaders();
        }

        function initColumnVisibility() {
            const cols = ['col-block', 'col-vtkt', 'col-owner', 'col-rec', 'col-inv', 'col-plan', 'col-actual'];
            cols.forEach(c => {
                const state = localStorage.getItem('budget_col_' + c);
                if (state !== null) {
                    const isChecked = state === 'true';
                    toggleCol(c, isChecked);
                    // Update checkbox in settings
                    const cb = document.querySelector(`input[onchange*="${c}"]`);
                    if (cb) cb.checked = isChecked;
                }
            });
            
            // Initial sticky check
            const table = document.querySelector('.budget-table');
            if (localStorage.getItem('budget_col_col-block') === 'false') {
                table.classList.add('block-hidden');
            }
            
            updateSuperHeaders();
        }

        window.addEventListener('DOMContentLoaded', initColumnVisibility);

        // --- DRILLDOWN SIDEBAR LOGIC ---
        let recChart = null;
        let invChart = null;
        let expenseChart = null;

        function openDrilldownSidebar(id, year, quarter) {
            const overlay = document.getElementById('drilldownOverlay');
            const sidebar = document.getElementById('drilldownSidebar');
            
            overlay.style.display = 'block';
            sidebar.classList.add('open');

            const fd = new FormData();
            fd.append('action', 'get_item_drilldown');
            fd.append('id', id);
            fd.append('year', year);
            fd.append('quarter', quarter);

            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderDrilldownData(data);
                } else {
                    alert('Lỗi: ' + data.error);
                    closeDrilldownSidebar();
                }
            })
            .catch(err => {
                console.error(err);
                closeDrilldownSidebar();
            });
        }

        function closeDrilldownSidebar() {
            document.getElementById('drilldownOverlay').style.display = 'none';
            document.getElementById('drilldownSidebar').classList.remove('open');
            document.getElementById('ownerSidebar').classList.remove('open');
        }

        // --- OWNER SIDEBAR LOGIC ---
        let currentOwnerOffset = 0;
        let currentOwnerName = '';

        function openOwnerSidebar(owner, year, quarter) {
            if (!owner) return;
            currentOwnerName = owner;
            currentOwnerOffset = 0;

            const overlay = document.getElementById('drilldownOverlay');
            const sidebar = document.getElementById('ownerSidebar');
            
            overlay.style.display = 'block';
            sidebar.classList.add('open');

            // Reset logs
            document.getElementById('os-logs-container').innerHTML = '';
            document.getElementById('os-load-more').style.display = 'none';

            const fd = new FormData();
            fd.append('action', 'get_owner_performance');
            fd.append('owner', owner);
            fd.append('year', year);
            fd.append('quarter', quarter);

            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderOwnerSidebarData(data);
                    loadUserLogs(); // Initial load of logs
                } else {
                    alert('Lỗi: ' + data.error);
                    closeDrilldownSidebar();
                }
            });
        }

        function loadUserLogs() {
            const fd = new FormData();
            fd.append('action', 'get_user_history_logs');
            fd.append('owner', currentOwnerName);
            fd.append('offset', currentOwnerOffset);

            const btn = document.getElementById('os-load-more');
            btn.innerText = 'Đang tải...';

            fetch(location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderUserLogs(data.logs);
                    currentOwnerOffset += data.logs.length;
                    btn.style.display = data.has_more ? 'block' : 'none';
                    btn.innerText = 'Xem thêm lịch sử';
                }
            });
        }

        function renderUserLogs(logs) {
            const container = document.getElementById('os-logs-container');
            if (logs.length === 0 && currentOwnerOffset === 0) {
                container.innerHTML = '<div style="font-size: 13px; color: #94a3b8; text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px; border: 1px dashed #e2e8f0;">Chưa có lịch sử cập nhật.</div>';
                return;
            }

            const fieldLabels = {
                'rec_rev_good': 'Rec Rev (Good)',
                'rec_rev_avg': 'Rec Rev (Avg)',
                'rec_rev_bad': 'Rec Rev (Bad)',
                'inv_rev_good': 'Inv Rev (Good)',
                'inv_rev_avg': 'Inv Rev (Avg)',
                'inv_rev_bad': 'Inv Rev (Bad)',
                'actual_salary': 'Lương thực tế',
                'actual_other': 'Chi phí khác'
            };

            logs.forEach(log => {
                const item = document.createElement('div');
                item.style.cssText = 'padding: 8px 0; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; align-items: flex-start;';
                
                let icon = 'fa-edit';
                let iconColor = '#3b82f6';
                if (log.action_type === 'DELETE_ITEM') { icon = 'fa-trash-alt'; iconColor = '#ef4444'; }
                if (log.action_type === 'ADD_ITEM') { icon = 'fa-plus-circle'; iconColor = '#10b981'; }

                let displayDetails = log.details;
                // Remove redundant words
                displayDetails = displayDetails.replace('Updated ', '').replace('for month ', '• ');
                Object.keys(fieldLabels).forEach(key => {
                    displayDetails = displayDetails.replace(key, fieldLabels[key]);
                });

                // Format timestamp
                const dt = new Date(log.created_at);
                const timeStr = dt.getHours().toString().padStart(2, '0') + ':' + dt.getMinutes().toString().padStart(2, '0');
                const dateStr = dt.getDate().toString().padStart(2, '0') + '/' + (dt.getMonth() + 1).toString().padStart(2, '0');

                item.innerHTML = `
                    <div style="width: 24px; height: 24px; background: ${iconColor}15; color: ${iconColor}; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; margin-top: 2px;">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div style="flex: 1; display: flex; justify-content: space-between; gap: 10px;">
                        <div>
                            <div style="font-size: 12px; font-weight: 700; color: #1e293b;">${log.item_name}</div>
                            <div style="font-size: 12px; color: #475569;">${displayDetails}</div>
                        </div>
                        <div style="text-align: right; flex-shrink: 0;">
                            <div style="font-size: 11px; font-weight: 700; color: #1e293b;">${timeStr}</div>
                            <div style="font-size: 10px; color: #94a3b8;">${dateStr}</div>
                        </div>
                    </div>
                `;
                container.appendChild(item);
            });
        }

        function renderOwnerSidebarData(data) {
            document.getElementById('os-title').innerText = data.owner;
            document.getElementById('os-period').innerText = 'Dữ liệu Q' + data.quarter + '/' + data.year;
            
            // Populate Identity Info
            document.getElementById('os-info-name').innerText = data.owner;
            document.getElementById('os-info-abbr').innerText = data.abbr || 'N/A';
            document.getElementById('os-info-division').innerText = data.divisions || 'N/A';
            document.getElementById('os-info-count').innerText = data.item_count;
            document.getElementById('os-avatar-initial').innerText = (data.owner ? data.owner.charAt(0) : '?');
            
            const head = document.getElementById('os-table-head');
            const body = document.getElementById('os-table-body');
            
            head.innerHTML = `
                <tr style="background: #f8fafc;">
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0; width: 140px;">Hạng mục</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0; background: #f0fdf4;" colspan="3">Recognised Revenue (G/A/B)</th>
                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0; background: #fdf2f8;" colspan="3">Invoiced Revenue (G/A/B)</th>
                    <th style="padding: 10px; text-align: right; border-bottom: 2px solid #e2e8f0; font-weight: 800;">Thực chi</th>
                </tr>
            `;

            body.innerHTML = '';
            if (data.items.length === 0) {
                body.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center; color: #64748b;">Không có dữ liệu hạng mục.</td></tr>';
                return;
            }

            let lastDivision = '';
            let lastCategory = '';
            data.items.forEach(it => {
                // Division Header (Level 1)
                if (it.division !== lastDivision) {
                    const divRow = document.createElement('tr');
                    divRow.style.background = '#e2e8f0';
                    divRow.innerHTML = `
                        <td colspan="8" style="padding: 10px 12px; font-weight: 800; color: #1e3a8a; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; border-top: 15px solid #fff;">
                            <i class="fas fa-sitemap" style="margin-right: 8px;"></i> KHỐI: ${it.division}
                        </td>
                    `;
                    body.appendChild(divRow);
                    lastDivision = it.division;
                    lastCategory = ''; // Reset category when division changes
                }

                // Category Header (Level 2)
                if (it.category !== lastCategory) {
                    const headerRow = document.createElement('tr');
                    headerRow.style.background = '#f1f5f9';
                    headerRow.innerHTML = `
                        <td colspan="8" style="padding: 8px 12px 8px 30px; font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0;">
                            <i class="fas fa-folder-open" style="margin-right: 6px; color: #94a3b8;"></i> ${it.category}
                        </td>
                    `;
                    body.appendChild(headerRow);
                    lastCategory = it.category;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 10px 10px 10px 48px; border-bottom: 1px solid #f1f5f9; font-weight: 600; background: #fff; position: relative;">
                        <span style="color: #cbd5e1; position: absolute; left: 32px;">└─</span>
                        <div style="font-size: 11px; color: #1e293b;">${it.name}</div>
                    </td>
                    
                    <td style="padding: 4px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #166534; background: #f0fdf4;">${formatVND(it.rec.good)}</td>
                    <td style="padding: 4px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #1e40af; background: #eff6ff;">${formatVND(it.rec.avg)}</td>
                    <td style="padding: 4px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #991b1b; background: #fef2f2;">${formatVND(it.rec.bad)}</td>
                    
                    <td style="padding: 4px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #166534; background: #f0fdf4;">${formatVND(it.inv.good)}</td>
                    <td style="padding: 4px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #1e40af; background: #eff6ff;">${formatVND(it.inv.avg)}</td>
                    <td style="padding: 4px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #991b1b; background: #fef2f2;">${formatVND(it.inv.bad)}</td>
                    
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 800;">${formatVND(it.expense)}</td>
                `;
                body.appendChild(tr);
            });
        }

        function renderDrilldownData(data) {
            const item = data.current_item;
            const history = data.history;

            document.getElementById('dd-title').innerText = item.item_name;
            document.getElementById('dd-info-div').innerText = item.division || 'N/A';
            document.getElementById('dd-info-cat').innerText = item.category || 'N/A';
            
            const curr = history[3];
            const prev = history[2];

            document.getElementById('dd-period-prev').innerText = 'Tổng ' + prev.period;
            document.getElementById('dd-period-curr').innerText = 'Tổng ' + curr.period;

            document.getElementById('dd-value-curr').innerText = formatVND(curr.total_expense);
            document.getElementById('dd-value-prev').innerText = formatVND(prev.total_expense);

            const diffStat = document.getElementById('dd-compare-stat');
            if (prev.total_expense > 0) {
                const diffPerc = ((curr.total_expense - prev.total_expense) / prev.total_expense) * 100;
                const sign = diffPerc >= 0 ? '+' : '';
                const color = diffPerc > 0 ? '#ef4444' : '#10b981';
                const arrow = diffPerc > 0 ? '↗' : '↘';
                diffStat.innerHTML = `<span style="color: ${color}; font-weight: 700;">${arrow} ${sign}${diffPerc.toFixed(1)}%</span> so với Q trước`;
            } else {
                diffStat.innerHTML = '<span style="color: #64748b;">N/A</span>';
            }

            // Sort newest to oldest for the table
            const tableHistory = [...history].reverse();
            
            const tableBody = document.getElementById('dd-table-body');
            tableBody.innerHTML = '';
            
            document.querySelector('#dd-details-table-wrap thead tr').innerHTML = `
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0; width: 70px;">Quý</th>
                <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0; border-left: 1px solid #e263eb22; background: #f0f9ff; color: #0369a1; font-size: 10px;" colspan="3">Recognised Rev (G/A/B)</th>
                <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0; border-left: 1px solid #e263eb22; background: #fdf2f8; color: #9d174d; font-size: 10px;" colspan="3">Invoiced Rev (G/A/B)</th>
                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #e2e8f0; font-weight: 800;">Thực chi</th>
            `;

            tableHistory.forEach(h => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9; font-weight: 700; background: #f8fafc;">${h.period}</td>
                    
                    <td style="padding: 8px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 11px; color: #166534; background: #f0fdf4;">${formatVND(h.rec.good)}</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 11px; color: #1e40af; background: #eff6ff;">${formatVND(h.rec.avg)}</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 11px; color: #991b1b; background: #fef2f2;">${formatVND(h.rec.bad)}</td>
                    
                    <td style="padding: 8px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 11px; color: #166534; background: #f0fdf4;">${formatVND(h.inv.good)}</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 11px; color: #1e40af; background: #eff6ff;">${formatVND(h.inv.avg)}</td>
                    <td style="padding: 8px 4px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 11px; color: #991b1b; background: #fef2f2;">${formatVND(h.inv.bad)}</td>
                    
                    <td style="padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 800;">${formatVND(h.total_expense)}</td>
                `;
                tableBody.appendChild(tr);
            });

            // --- RECOGNISED REVENUE CHART (3 Scenarios) ---
            if (recChart) recChart.destroy();
            recChart = new ApexCharts(document.querySelector("#dd-chart-rec"), {
                series: [
                    { name: 'Tốt (Good)', data: history.map(h => h.rec.good) },
                    { name: 'Trung bình (Avg)', data: history.map(h => h.rec.avg) },
                    { name: 'Xấu (Bad)', data: history.map(h => h.rec.bad) }
                ],
                chart: { type: 'line', height: 200, toolbar: { show: false } },
                stroke: { curve: 'smooth', width: 3 },
                dataLabels: { 
                    enabled: true, 
                    formatter: v => (v/1000000).toFixed(1) + 'M', 
                    style: { fontSize: '9px' },
                    offsetY: -5 
                },
                colors: ['#10b981', '#3b82f6', '#ef4444'],
                xaxis: { categories: history.map(h => h.period) },
                yaxis: { labels: { formatter: v => (v/1000000).toFixed(1) + 'M' } },
                legend: { position: 'top', horizontalAlign: 'right' }
            });
            recChart.render();

            // --- INVOICED REVENUE CHART (3 Scenarios) ---
            if (invChart) invChart.destroy();
            invChart = new ApexCharts(document.querySelector("#dd-chart-inv"), {
                series: [
                    { name: 'Tốt (Good)', data: history.map(h => h.inv.good) },
                    { name: 'Trung bình (Avg)', data: history.map(h => h.inv.avg) },
                    { name: 'Xấu (Bad)', data: history.map(h => h.inv.bad) }
                ],
                chart: { type: 'line', height: 200, toolbar: { show: false } },
                stroke: { curve: 'smooth', width: 3, dashArray: [0, 0, 4] },
                dataLabels: { 
                    enabled: true, 
                    formatter: v => (v/1000000).toFixed(1) + 'M', 
                    style: { fontSize: '9px' },
                    offsetY: -5 
                },
                colors: ['#059669', '#2563eb', '#dc2626'],
                xaxis: { categories: history.map(h => h.period) },
                yaxis: { labels: { formatter: v => (v/1000000).toFixed(1) + 'M' } },
                legend: { position: 'top', horizontalAlign: 'right' }
            });
            invChart.render();

            // --- EXPENSE CHART (Stacked Bar) ---
            if (expenseChart) expenseChart.destroy();
            expenseChart = new ApexCharts(document.querySelector("#dd-chart-expenses"), {
                series: [
                    { name: 'Thực chi', data: history.map(h => h.total_expense) }
                ],
                chart: { type: 'bar', height: 200, toolbar: { show: false } },
                dataLabels: { 
                    enabled: true, 
                    formatter: v => (v/1000000).toFixed(1) + 'M',
                    style: { fontSize: '9px' }
                },
                colors: ['#3b82f6'],
                xaxis: { categories: history.map(h => h.period) },
                legend: { position: 'top', horizontalAlign: 'right' }
            });
            expenseChart.render();
        }

        // --- Dropdown click-to-toggle (thay thế CSS hover) ---
        document.querySelectorAll('.column-settings-dropdown').forEach(function(dropdown) {
            var btn = dropdown.querySelector('button');
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = dropdown.classList.contains('open');
                // Đóng tất cả dropdowns khác
                document.querySelectorAll('.column-settings-dropdown.open').forEach(function(d) {
                    d.classList.remove('open');
                });
                if (!isOpen) dropdown.classList.add('open');
            });
        });

        // Click ra ngoài thì đóng hết
        document.addEventListener('click', function() {
            document.querySelectorAll('.column-settings-dropdown.open').forEach(function(d) {
                d.classList.remove('open');
            });
        });

        // Click bên trong content không đóng dropdown
        document.querySelectorAll('.column-settings-content').forEach(function(content) {
            content.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
