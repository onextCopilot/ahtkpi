<?php
require_once __DIR__ . '/../../config/config.php';

// Helper: Safe Column Addition for Production Resilience
function addColIfNotExists($conn, $table, $col, $def) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            return $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        }
    } catch (Exception $e) { return false; }
    return true;
}

// Ensure Core Tables Exist
$conn->query("CREATE TABLE IF NOT EXISTS okr_objectives (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS okr_key_activities (id INT AUTO_INCREMENT PRIMARY KEY, objective_id INT, activity_name VARCHAR(255), progress DECIMAL(5,2) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS okr_results (id INT AUTO_INCREMENT PRIMARY KEY, objective_id INT, metric_name VARCHAR(255), current_value DECIMAL(15,2) DEFAULT 0, target_value DECIMAL(15,2) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS okr_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS okr_teams (id INT AUTO_INCREMENT PRIMARY KEY, team_name VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS okr_team_members (id INT AUTO_INCREMENT PRIMARY KEY, team_id INT, user_id INT, UNIQUE KEY `unique_membership` (`team_id`, `user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Fetch OKR Settings
$okr_settings = [];
$s_res = $conn->query("SELECT * FROM okr_settings");
if ($s_res) {
    while($s_row = $s_res->fetch_assoc()) $okr_settings[$s_row['setting_key']] = $s_row['setting_value'];
}

$color_high = $okr_settings['color_high'] ?? '#f2fdf5';
$color_mid  = $okr_settings['color_mid']  ?? '#fffcf0';
$color_low  = $okr_settings['color_low']  ?? '#fff1f2';
$text_high  = $okr_settings['text_high']  ?? '#166534';
$text_mid   = $okr_settings['text_mid']   ?? '#854d0e';
$text_low   = $okr_settings['text_low']   ?? '#991b1b';

$kr_color_high = $okr_settings['kr_color_high'] ?? $color_high;
$kr_color_mid  = $okr_settings['kr_color_mid']  ?? $color_mid;
$kr_color_low  = $okr_settings['kr_color_low']  ?? $color_low;
$kr_text_high  = $okr_settings['kr_text_high']  ?? $text_high;
$kr_text_mid   = $okr_settings['kr_text_mid']   ?? $text_mid;
$kr_text_low   = $okr_settings['kr_text_low']   ?? $text_low;

$obj_color_high = $okr_settings['obj_color_high'] ?? '#ffffff';
$obj_color_mid  = $okr_settings['obj_color_mid']  ?? '#ffffff';
$obj_color_low  = $okr_settings['obj_color_low']  ?? '#ffffff';
$obj_text_high  = $okr_settings['obj_text_high']  ?? '#1d1d1f';
$obj_text_mid   = $okr_settings['obj_text_mid']   ?? '#1d1d1f';
$obj_text_low   = $okr_settings['obj_text_low']   ?? '#1d1d1f';

// Synchronize Structural Requirements (Migration Block)
try {
    // ... items from objective table ...
    addColIfNotExists($conn, 'okr_objectives', 'team', 'VARCHAR(100) DEFAULT "Sales & Marketing"');
    addColIfNotExists($conn, 'okr_objectives', 'owner', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_objectives', 'owner_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_objectives', 'progress', 'DECIMAL(5,2) DEFAULT 0');
    addColIfNotExists($conn, 'okr_objectives', 'status', 'VARCHAR(100) DEFAULT "on_track"');
    addColIfNotExists($conn, 'okr_objectives', 'quarter', 'INT DEFAULT 1');
    addColIfNotExists($conn, 'okr_objectives', 'year', 'INT DEFAULT 2026');
    addColIfNotExists($conn, 'okr_key_activities', 'result_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'owner', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_key_activities', 'owner_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'weight', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'status', 'VARCHAR(100) DEFAULT "pending"');
    addColIfNotExists($conn, 'okr_objectives', 'cycle_id', 'INT DEFAULT 1');
    addColIfNotExists($conn, 'okr_objectives', 'sort_order', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_objectives', 'is_company_okr', 'TINYINT DEFAULT 0');
    
    // Key Results Table Enhancements
    addColIfNotExists($conn, 'okr_results', 'unit', 'VARCHAR(50) DEFAULT "%"');
    addColIfNotExists($conn, 'okr_results', 'status', 'VARCHAR(50) DEFAULT "pending"');
    addColIfNotExists($conn, 'okr_results', 'priority', 'VARCHAR(20) DEFAULT "medium"');
    addColIfNotExists($conn, 'okr_results', 'weight', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_results', 'owner_name', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_results', 'owner_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_results', 'owner_2_name', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_results', 'owner_2_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_results', 'owner_avatar', 'VARCHAR(10)');
    addColIfNotExists($conn, 'okr_results', 'activity_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_results', 'sort_order', 'INT DEFAULT 0');
    
    // Key Activities Table Enhancements
    addColIfNotExists($conn, 'okr_key_activities', 'status', 'VARCHAR(50) DEFAULT "pending"');
    addColIfNotExists($conn, 'okr_key_activities', 'priority', 'VARCHAR(20) DEFAULT "medium"');
    addColIfNotExists($conn, 'okr_key_activities', 'weight', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'owner_name', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_key_activities', 'owner_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'owner_avatar', 'VARCHAR(10)');
    addColIfNotExists($conn, 'okr_key_activities', 'kr_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'sort_order', 'INT DEFAULT 0');

    // Expand numeric columns to support large values
    @$conn->query("ALTER TABLE `okr_results` MODIFY COLUMN `target_value` DECIMAL(20,2) DEFAULT 0");
    @$conn->query("ALTER TABLE `okr_results` MODIFY COLUMN `current_value` DECIMAL(20,2) DEFAULT 0");
} catch (Throwable $e) { }

// Auto-migration for Explanations
$check_table = $conn->query("SHOW TABLES LIKE 'okr_explanations'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE `okr_explanations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `item_type` ENUM('metric', 'activity') NOT NULL,
        `content` TEXT NOT NULL,
        `user_id` INT NOT NULL,
        `user_name` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
addColIfNotExists($conn, 'okr_explanations', 'week_num', 'INT DEFAULT 0');
addColIfNotExists($conn, 'okr_explanations', 'progress_value', 'DECIMAL(5,2) DEFAULT 0');
addColIfNotExists($conn, 'okr_explanations', 'quarter', 'INT DEFAULT 0');
addColIfNotExists($conn, 'okr_explanations', 'year', 'INT DEFAULT 0');

// Module-specific Page Titles
$page_title = "OKR Management";
$page_subtitle = "Sales & Marketing OKRs";

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');
$current_full_name = $_SESSION['full_name'] ?? 'System / Anonymous';

// Time Context (Moved up for consistent AJAX scope)
$current_quarter = intval($_GET['quarter'] ?? ceil(date('n') / 3));
$current_year = intval($_GET['year'] ?? date('Y'));

// Calculate current week of the 13-week quarter
$q_start_month = ($current_quarter - 1) * 3 + 1;
// Find the first day of the quarter month
$first_day_of_q = new DateTime("$current_year-$q_start_month-01");
// Align to the Monday of that week (ISO-8601 week start)
$q_start_dt = clone $first_day_of_q;
if ($q_start_dt->format('N') != 1) {
    $q_start_dt->modify('last Monday');
}

$now_dt = new DateTime();
$interval = $q_start_dt->diff($now_dt);
$days_passed = $interval->days;
if ($now_dt < $q_start_dt) $days_passed = 0;
$current_week_num = min(13, max(1, floor($days_passed / 7) + 1));
$q_start_date_str = $q_start_dt->format('Y-m-d');

addColIfNotExists($conn, 'okr_key_activities', 'result_id', 'INT(11) DEFAULT 0');
addColIfNotExists($conn, 'users', 'hide_from_okr', 'TINYINT(1) DEFAULT 0');

// Handle naming inconsistencies across versions
if ($conn->query("SHOW COLUMNS FROM okr_key_activities LIKE 'activity_name'")->num_rows == 0) {
    if ($conn->query("SHOW COLUMNS FROM okr_key_activities LIKE 'name'")->num_rows > 0) {
        $conn->query("ALTER TABLE okr_key_activities CHANGE name activity_name VARCHAR(255)");
    }
}
if ($conn->query("SHOW COLUMNS FROM okr_results LIKE 'metric_name'")->num_rows == 0) {
    if ($conn->query("SHOW COLUMNS FROM okr_results LIKE 'name'")->num_rows > 0) {
        $conn->query("ALTER TABLE okr_results CHANGE name metric_name VARCHAR(255)");
    }
}

// Fix description column if it exists as NOT NULL without default
if ($conn->query("SHOW COLUMNS FROM okr_key_activities LIKE 'description'")->num_rows > 0) {
    $conn->query("ALTER TABLE okr_key_activities MODIFY description TEXT DEFAULT NULL");
}
if ($conn->query("SHOW COLUMNS FROM okr_results LIKE 'description'")->num_rows > 0) {
    $conn->query("ALTER TABLE okr_results MODIFY description TEXT DEFAULT NULL");
}

// Seed Database if Empty
$chk = $conn->query("SELECT COUNT(*) as c FROM okr_objectives")->fetch_assoc();
if ($chk['c'] == 0) {
    $conn->query("INSERT INTO okr_objectives (title, team, owner, progress, status) VALUES ('Deliver 1,500 new seats a year', 'Sales & Marketing', 'Executive Team', 65, 'on_track')");
    $oid = $conn->insert_id;
    
    $conn->query("INSERT INTO okr_results (objective_id, metric_name, target_value, current_value, unit, status, owner_avatar, owner_name) 
        VALUES ($oid, 'New seats per month', 150, 120, 'seats', 'on_track', 'S1', 'Sales 1')");
    $conn->query("INSERT INTO okr_results (objective_id, metric_name, target_value, current_value, unit, status, owner_avatar, owner_name) 
        VALUES ($oid, 'Deliver quality leads/mo', 10000, 5000, 'leads', 'at_risk', 'MKT', 'Marketing')");
    
    $conn->query("INSERT INTO okr_key_activities (objective_id, activity_name, status, progress, owner_avatar, owner_name) 
        VALUES ($oid, 'PPC Campaigns execution', 'in_progress', 80, 'AD', 'Ads Team')");
    $conn->query("INSERT INTO okr_key_activities (objective_id, activity_name, status, progress, owner_avatar, owner_name) 
        VALUES ($oid, 'Account-Based Marketing', 'delayed', 25, 'BS', 'B2B Sales')");
}

// Handle AJAX Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_okr_item') {
        $id = intval($_POST['id']);
        $type = $_POST['type'];
        $is_partial = isset($_POST['partial_update']);

        // Default values from DB if partial, else from POST
        $table = ($type === 'metric') ? 'okr_results' : 'okr_key_activities';
        $curr_q = $conn->query("SELECT * FROM $table WHERE id = $id");
        $curr_data = $curr_q->fetch_assoc();

        $val = $is_partial ? 0 : floatval($_POST['val'] ?? 0);
        $status = $is_partial ? ($curr_data['status'] ?? 'pending') : ($_POST['status'] ?? 'pending');
        $priority = $is_partial ? ($curr_data['priority'] ?? 'medium') : ($_POST['priority'] ?? 'medium');
        $weight = $is_partial ? intval($curr_data['weight'] ?? 0) : intval($_POST['weight'] ?? 0);
        $name = $is_partial ? ($type==='metric' ? $curr_data['metric_name'] : $curr_data['activity_name']) : trim($_POST['name'] ?? '');
        $owner_name = $is_partial ? ($curr_data['owner_name'] ?? '') : trim($_POST['owner_name'] ?? '');
        $owner_id = $is_partial ? intval($curr_data['owner_id'] ?? 0) : intval($_POST['owner_id'] ?? 0);
        $owner_2_name = $is_partial ? ($curr_data['owner_2_name'] ?? '') : trim($_POST['owner_2_name'] ?? '');
        $owner_2_id = $is_partial ? intval($curr_data['owner_2_id'] ?? 0) : intval($_POST['owner_2_id'] ?? 0);
        $explanation = trim($_POST['explanation'] ?? '');
        
        if ($is_partial) {
            $val = ($type === 'metric') ? ($curr_data['target_value'] > 0 ? ($curr_data['current_value']/$curr_data['target_value'])*100 : 0) : $curr_data['progress'];
        }
        
        // Find Objective ID
        $oid = 0;
        if($type === 'metric') {
            $check = $conn->query("SELECT objective_id, quarter, year FROM okr_results r JOIN okr_objectives o ON r.objective_id = o.id WHERE r.id = $id");
            if($c = $check->fetch_assoc()) {
                $oid = $c['objective_id'];
                $item_q = $c['quarter'];
                $item_y = $c['year'];
            }
        } else {
            $check = $conn->query("SELECT objective_id, quarter, year FROM okr_key_activities k JOIN okr_objectives o ON k.objective_id = o.id WHERE k.id = $id");
            if($c = $check->fetch_assoc()) {
                $oid = $c['objective_id'];
                $item_q = $c['quarter'];
                $item_y = $c['year'];
            }
        }

        // Weight check
        $table = ($type === 'metric') ? 'okr_results' : 'okr_key_activities';
        $sum_res = $conn->query("SELECT SUM(weight) as total FROM $table WHERE objective_id = $oid AND id != $id");
        $sum = $sum_res->fetch_assoc()['total'] ?? 0;
        if (($sum + $weight) > 100) {
            echo json_encode(['success' => false, 'error' => 'Tổng tỉ trọng không được quá 100% (Hiện tại: '.$sum.'%)']);
            exit();
        }

        $result_id = ($type === 'activity') ? intval($_POST['parent_id'] ?? ($curr_data['result_id'] ?? 0)) : 0;

        // Recalculate avatar initials for the owner
        $avatar = '';
        if(!empty($owner_name)) {
            $parts = explode(' ', $owner_name);
            $avatar = mb_substr(end($parts), 0, 1, "UTF-8");
        }

        if (!$is_partial) {
            $sort_order = intval($_POST['sort_order'] ?? ($curr_data['sort_order'] ?? 0));
            if ($type === 'metric') {
                $stmt = $conn->prepare("UPDATE okr_results SET metric_name = ?, current_value = ROUND((? / 100.0) * target_value, 2), status = ?, priority = ?, weight = ?, owner_name = ?, owner_id = ?, owner_2_name = ?, owner_2_id = ?, owner_avatar = ?, sort_order = ? WHERE id = ?");
                $stmt->bind_param("sdssisssisii", $name, $val, $status, $priority, $weight, $owner_name, $owner_id, $owner_2_name, $owner_2_id, $avatar, $sort_order, $id);
                $stmt->execute();
                
                // If an activity was selected to link, update that KA's result_id
                $link_activity_id = intval($_POST['activity_id'] ?? 0);
                if ($link_activity_id > 0) {
                    $conn->query("UPDATE okr_key_activities SET result_id = $id WHERE id = $link_activity_id");
                }
            } else {
                $stmt = $conn->prepare("UPDATE okr_key_activities SET activity_name = ?, progress = ?, status = ?, priority = ?, weight = ?, owner_name = ?, owner_id = ?, owner_avatar = ?, result_id = ?, sort_order = ? WHERE id = ?");
                $stmt->bind_param("sdssisssiii", $name, $val, $status, $priority, $weight, $owner_name, $owner_id, $avatar, $result_id, $sort_order, $id);
                $stmt->execute();
            }
        }
        
        // Save explanation & Weekly Progress
        $clean_explanation = trim(strip_tags($explanation));
        if (($explanation && !empty($clean_explanation)) || isset($_POST['week_num'])) {
            $u_id = $_SESSION['user_id'] ?? 0;
            $u_name = $_SESSION['user_full_name'] ?? ($_SESSION['name'] ?? 'System');
            $week_num = intval($_POST['week_num'] ?? $current_week_num);
            
            // Use item's quarter/year if not provided
            $save_q = intval($_POST['save_quarter'] ?? $item_q);
            $save_y = intval($_POST['save_year'] ?? $item_y);

            // Insert into explanations with progress and week
            $stmt_exp = $conn->prepare("INSERT INTO okr_explanations (item_id, item_type, content, user_id, user_name, week_num, progress_value, quarter, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_exp->bind_param("issisddii", $id, $type, $explanation, $u_id, $u_name, $week_num, $val, $save_q, $save_y);
            $stmt_exp->execute();
        }
        
        // Auto Update Progress for Parent KA and Objective
        if ($type === 'metric') {
            $obj_id_q = $conn->query("SELECT objective_id, activity_id FROM okr_results WHERE id = $id");
            if ($obj_id_row = $obj_id_q->fetch_assoc()) {
                $oid = $obj_id_row['objective_id'];
                $aid = $obj_id_row['activity_id'];

                // 1. Recalculate Parent Key Activity Progress
                if ($aid > 0) {
                    $conn->query("UPDATE okr_key_activities ka 
                                  SET progress = (
                                      SELECT 
                                        CASE 
                                            WHEN SUM(weight) > 0 THEN SUM(LEAST((current_value / NULLIF(target_value,0)) * 100, 100) * weight) / SUM(weight)
                                            ELSE AVG(LEAST((current_value / NULLIF(target_value,0)) * 100, 100))
                                        END
                                      FROM okr_results 
                                      WHERE activity_id = ka.id
                                  )
                                  WHERE ka.id = $aid");
                }

                // 2. Recalculate Objective Progress
                $conn->query("UPDATE okr_objectives o 
                              SET o.progress = (
                                  SELECT 
                                    CASE 
                                        WHEN SUM(weight) > 0 THEN SUM(LEAST((current_value / NULLIF(target_value,0)) * 100, 100) * weight) / SUM(weight)
                                        ELSE AVG(LEAST((current_value / NULLIF(target_value,0)) * 100, 100))
                                    END
                                  FROM okr_results 
                                  WHERE objective_id = o.id
                              )
                              WHERE o.id = $oid");
            }
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_POST['action'] === 'delete_okr_item') {
        $id = intval($_POST['id']);
        $type = $_POST['type']; // activity or metric
        
        $table = ($type === 'metric') ? 'okr_results' : 'okr_key_activities';
        
        // Delete item
        $conn->query("DELETE FROM $table WHERE id = $id");
        // Delete history
        $item_type = ($type === 'metric') ? 'metric' : 'activity';
        $conn->query("DELETE FROM okr_explanations WHERE item_id = $id AND item_type = '$item_type'");
        
        // Recalculate Progress for Parent KA and Objective
        if ($type === 'metric') {
            // We'd need to know the objective_id and activity_id before deletion or from remaining records
            // For simplicity, update all affected Objectives and KAs
            $conn->query("UPDATE okr_key_activities ka 
                          SET progress = (
                              SELECT 
                                CASE 
                                    WHEN SUM(weight) > 0 THEN SUM(LEAST((current_value / NULLIF(target_value,0)) * 100, 100) * weight) / SUM(weight)
                                    ELSE AVG(LEAST((current_value / NULLIF(target_value,0)) * 100, 100))
                                END
                              FROM okr_results 
                              WHERE activity_id = ka.id
                          )
                          WHERE ka.id IN (SELECT DISTINCT activity_id FROM okr_results WHERE activity_id > 0)");

            $conn->query("UPDATE okr_objectives o 
                          SET o.progress = (
                              SELECT 
                                CASE 
                                    WHEN SUM(weight) > 0 THEN SUM(LEAST((current_value / NULLIF(target_value,0)) * 100, 100) * weight) / SUM(weight)
                                    ELSE AVG(LEAST((current_value / NULLIF(target_value,0)) * 100, 100))
                                END
                              FROM okr_results 
                              WHERE objective_id = o.id
                          )
                          WHERE o.id IN (SELECT DISTINCT objective_id FROM okr_results)");
        }
        
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_POST['action'] === 'delete_objective') {
        $id = intval($_POST['id']);
        
        // Cascading deletion
        // 1. Explanations for KA
        $conn->query("DELETE FROM okr_explanations WHERE item_type = 'activity' AND item_id IN (SELECT id FROM okr_key_activities WHERE objective_id = $id)");
        // 2. Explanations for KR
        $conn->query("DELETE FROM okr_explanations WHERE item_type = 'metric' AND item_id IN (SELECT id FROM okr_results WHERE objective_id = $id)");
        // 3. KA
        $conn->query("DELETE FROM okr_key_activities WHERE objective_id = $id");
        // 4. KR
        $conn->query("DELETE FROM okr_results WHERE objective_id = $id");
        // 5. Objective
        $conn->query("DELETE FROM okr_objectives WHERE id = $id");
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($_POST['action'] === 'fetch_explanation_history') {
        $id = intval($_POST['id']);
        $type = $_POST['type'];
        $q = intval($_POST['quarter'] ?? $current_quarter);
        $y = intval($_POST['year'] ?? $current_year);
        
        $history = [];
        $res = $conn->query("SELECT * FROM okr_explanations WHERE item_id = $id AND item_type = '$type' ORDER BY created_at DESC");
        if ($res) {
            while($row = $res->fetch_assoc()) {
                $row['formatted_date'] = date('d/m/Y H:i', strtotime($row['created_at']));
                $history[] = $row;
            }
        }
        
        // Fetch specific weekly progress for current quarter
        $weekly = [];
        // We want the LATEST entry for each week to show in the grid
        $w_res = $conn->query("SELECT e1.week_num, e1.progress_value 
                               FROM okr_explanations e1
                               INNER JOIN (
                                   SELECT week_num, MAX(id) as max_id
                                   FROM okr_explanations
                                   WHERE item_id = $id AND item_type = '$type' AND quarter = $q AND year = $y
                                   GROUP BY week_num
                               ) e2 ON e1.id = e2.max_id
                               ORDER BY e1.week_num ASC");
        while($w = $w_res->fetch_assoc()) {
            $weekly[$w['week_num']] = [
                'progress' => (float)$w['progress_value']
            ];
        }

        echo json_encode(['success' => true, 'history' => $history, 'weekly' => (object)$weekly]);
        exit();
    }

    if ($_POST['action'] === 'add_okr_item') {
        $oid = intval($_POST['obj_id']);
        $type = $_POST['type']; // 'metric' or 'activity'
        $name = trim($_POST['name']);
        $owner_name = trim($_POST['owner_name']);
        $owner_id = intval($_POST['owner_id'] ?? 0);
        if (!$owner_name) $owner_name = 'User';
        
        $avatar = strtoupper(substr($owner_name, 0, 2));
        if(!empty($owner_name)) {
            $parts = explode(' ', $owner_name);
            $avatar = mb_substr(end($parts), 0, 1, "UTF-8");
        }

        $priority = $_POST['priority'] ?? 'medium';
        $weight = intval($_POST['weight'] ?? 0);

        // Weight check
        $table = ($type === 'metric') ? 'okr_results' : 'okr_key_activities';
        $sum_res = $conn->query("SELECT SUM(weight) as total FROM $table WHERE objective_id = $oid");
        $sum = $sum_res->fetch_assoc()['total'] ?? 0;
        if (($sum + $weight) > 100) {
            echo json_encode(['success' => false, 'error' => 'Tổng tỉ trọng không được quá 100% (Hiện tại: '.$sum.'%)']);
            exit();
        }

        $sort_order = intval($_POST['sort_order'] ?? 0);

        if ($type === 'metric') {
            $owner_2_id = intval($_POST['owner_2_id'] ?? 0);
            $owner_2_name = trim($_POST['owner_2_name'] ?? '');
            $stmt = $conn->prepare("INSERT INTO okr_results (objective_id, metric_name, target_value, unit, status, owner_name, owner_id, owner_2_name, owner_2_id, owner_avatar, priority, weight, sort_order) VALUES (?, ?, 100, '%', 'pending', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issisis sii", $oid, $name, $owner_name, $owner_id, $owner_2_name, $owner_2_id, $avatar, $priority, $weight, $sort_order);
            $stmt->execute();
            $new_kr_id = $conn->insert_id;
            
            // If an activity was selected to link
            $link_activity_id = intval($_POST['activity_id'] ?? 0);
            if ($link_activity_id > 0) {
                $conn->query("UPDATE okr_key_activities SET result_id = $new_kr_id WHERE id = $link_activity_id");
            }
        } else {
            $result_id = intval($_POST['parent_id'] ?? 0);
            $stmt = $conn->prepare("INSERT INTO okr_key_activities (objective_id, activity_name, progress, status, owner_name, owner_id, owner_avatar, priority, weight, result_id, sort_order) VALUES (?, ?, 0, 'pending', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississsii", $oid, $name, $owner_name, $owner_id, $avatar, $priority, $weight, $result_id, $sort_order);
            $stmt->execute();
        }

        // Auto Update Objective Progress if it's a KR
        if ($type === 'metric') {
            $conn->query("UPDATE okr_objectives o 
                          SET o.progress = (
                              SELECT 
                                CASE 
                                    WHEN SUM(weight) > 0 THEN SUM(LEAST((current_value / NULLIF(target_value,0)) * 100, 100) * weight) / SUM(weight)
                                    ELSE AVG(LEAST((current_value / NULLIF(target_value,0)) * 100, 100))
                                END
                              FROM okr_results 
                              WHERE objective_id = o.id
                          )
                          WHERE o.id = $oid");
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    if ($_POST['action'] === 'create_objective') {
        $title = trim($_POST['title']);
        $team = trim($_POST['team'] ?? '');
        $owner_id = intval($_POST['owner_id'] ?? 0);
        $owner_name = trim($_POST['owner_name']);
        if (!$owner_name) $owner_name = $team ?: $current_full_name;
        $status = $_POST['status'] ?? 'pending';
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $quarter = intval($_POST['quarter'] ?? 1);
        $year = intval($_POST['year'] ?? 2026);
        
        $stmt = $conn->prepare("INSERT INTO okr_objectives (title, team, owner, owner_id, status, progress, sort_order, quarter, year, cycle_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 1)");
        $stmt->bind_param("sssisiii", $title, $team, $owner_name, $owner_id, $status, $sort_order, $quarter, $year);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_POST['action'] === 'update_objective') {
        $oid = intval($_POST['id']);
        $title = trim($_POST['title']);
        $team = trim($_POST['team'] ?? '');
        $owner_id = intval($_POST['owner_id'] ?? 0);
        $owner_name = trim($_POST['owner_name']);
        $status = $_POST['status'] ?? 'on_track';
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $quarter = intval($_POST['quarter'] ?? 1);
        $year = intval($_POST['year'] ?? 2026);

        $stmt = $conn->prepare("UPDATE okr_objectives SET title=?, team=?, owner=?, owner_id=?, status=?, sort_order=?, quarter=?, year=? WHERE id=?");
        $stmt->bind_param("sssisiiii", $title, $team, $owner_name, $owner_id, $status, $sort_order, $quarter, $year, $oid);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_POST['action'] === 'fetch_annual_okrs') {
        $uid = intval($_POST['user_id']);
        $year = intval($_POST['year']);
        
        $u_res = $conn->query("SELECT full_name FROM users WHERE id = $uid");
        $uname = ($u_res && $row = $u_res->fetch_assoc()) ? $row['full_name'] : trim($_POST['user_name'] ?? '');
        
        if ($uid > 0) {
            $stmt = $conn->prepare("SELECT id, title, quarter, progress, status FROM okr_objectives WHERE owner_id = ? AND year = ? ORDER BY quarter ASC, sort_order ASC");
            $stmt->bind_param("ii", $uid, $year);
        } else {
            $stmt = $conn->prepare("SELECT id, title, quarter, progress, status FROM okr_objectives WHERE owner_id = 0 AND owner = ? AND year = ? ORDER BY quarter ASC, sort_order ASC");
            $stmt->bind_param("si", $uname, $year);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        
        $grouped = [];
        while($row = $res->fetch_assoc()) {
            // Fetch KRs for this objective
            $krs = [];
            $kr_res = $conn->query("SELECT metric_name, current_value, target_value, unit, status, priority, owner_name, owner_avatar FROM okr_results WHERE objective_id = " . $row['id']);
            if ($kr_res) {
                while($kr = $kr_res->fetch_assoc()) $krs[] = $kr;
            }
            $row['krs'] = $krs;
            $grouped[$row['quarter']][] = $row;
        }
        
        ob_start();
        if (empty($grouped)) {
            echo '<div style="text-align:center; padding:60px 20px; color: #86868b; background: white; border-radius: 12px; border: 1px dashed #d2d2d7; margin-top:20px;">
                    <i class="fas fa-calendar-times" style="font-size: 40px; margin-bottom:16px; opacity:0.3;"></i>
                    <p>No objectives found for ' . $year . '.</p>
                  </div>';
        } else {
            foreach ([1,2,3,4] as $q) {
                if (!isset($grouped[$q])) continue;
                $is_current = ($q == $current_quarter);
                $q_style = $is_current ? 'background:#fdfdff; border:1px solid #0071e3; box-shadow:0 8px 24px rgba(0,113,227,0.06);' : 'background:white; border:1px solid #e5e5ea; box-shadow:0 2px 8px rgba(0,0,0,0.02);';
                
                echo '<div class="annual-q-card" style="border-radius:16px; padding:20px; margin-bottom:20px; position:relative; '.$q_style.'">';
                if($is_current) {
                    echo '<div style="position:absolute; top:-10px; right:20px; background:#0071e3; color:white; font-size:9px; font-weight:900; padding:2px 10px; border-radius:10px; text-transform:uppercase; letter-spacing:0.05em; box-shadow:0 4px 8px rgba(0,0,0,0.1); z-index:2;">Current Qtr</div>';
                }
                echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid ' . ($is_current ? '#e6f2ff' : '#f2f2f7') . '; padding-bottom:10px;">';
                echo '<span style="font-weight:700; font-size:15px; color:#1d1d1f;"><i class="fas fa-calendar-day" style="color:#0071e3; margin-right:8px;"></i>QUARTER '.$q.'</span>';
                echo '<span style="font-size:12px; background:#f5f5f7; padding:2px 8px; border-radius:10px; font-weight:600;">'.count($grouped[$q]).' Items</span>';
                echo '</div>';
                foreach ($grouped[$q] as $obj) {
                    $status_color = ($obj['status'] === 'completed') ? '#10b981' : (($obj['status'] === 'at_risk') ? '#ef4444' : '#6366f1');
                    echo '<div style="margin-bottom:20px;">';
                        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:14px;">';
                            echo '<div style="display:flex; align-items:center; gap:8px; flex:1; padding-right:12px;">';
                                echo '<i class="fas fa-bullseye" style="color:#6366f1; font-size:12px; opacity:0.8;"></i>';
                                echo '<span style="color:#1d1d1f; font-weight:600; line-height:1.4;">'.htmlspecialchars($obj['title'] ?? '').'</span>';
                                echo '<span style="font-size:9px; background:'.$status_color.'15; color:'.$status_color.'; padding:1px 6px; border-radius:4px; font-weight:800; text-transform:uppercase; letter-spacing:0.02em;">'.$obj['status'].'</span>';
                            echo '</div>';
                            echo '<div style="font-weight:700; color:#1d1d1f; min-width:40px; text-align:right;">'.$obj['progress'].'%</div>';
                        echo '</div>';
                        
                        if (!empty($obj['krs'])) {
                            echo '<div style="padding-left:18px; border-left:1px solid #f2f2f7; margin-left:16px; padding-top:4px;">';
                            foreach ($obj['krs'] as $kr) {
                                $p_icon = ($kr['priority'] === 'high') ? '<i class="fas fa-fire" style="color:#ef4444; margin-right:4px; font-size:10px;"></i>' : '';
                                echo '<div style="display:flex; justify-content:space-between; font-size:12px; color:#515154; margin-bottom:8px;">';
                                echo '<div style="display:flex; align-items:center; gap:6px;">';
                                    echo $p_icon.'<i class="fas fa-crosshairs" style="font-size:10px; margin-right:4px; opacity:0.5;"></i>';
                                    echo '<span>'.htmlspecialchars($kr['metric_name'] ?? '').'</span>';
                                    echo '<span style="font-size:10px; color:#86868b; opacity:0.8;">— '.htmlspecialchars($kr['owner_name'] ?? '').'</span>';
                                echo '</div>';
                                echo '<span style="font-weight:600; color:#1d1d1f;">'.number_format($kr['current_value'] ?? 0, 1).' / '.number_format($kr['target_value'] ?? 0, 0).' '.htmlspecialchars($kr['unit'] ?? '').'</span>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    echo '</div>';
                }
                echo '</div>';
            }
        }
        $html = ob_get_clean();
        echo json_encode(['success' => true, 'html' => $html]);
        exit();
    }
    if($_POST['action'] === 'fetch_okr_teams_data') {
        if (!$is_admin) { echo json_encode(['success' => false]); exit(); }
        $teams = [];
        $res = $conn->query("SELECT * FROM okr_teams ORDER BY team_name ASC");
        while($row = $res->fetch_assoc()) {
            $tid = $row['id'];
            $m_res = $conn->query("SELECT user_id FROM okr_team_members WHERE team_id = $tid");
            $members = [];
            while($m = $m_res->fetch_assoc()) $members[] = (int)$m['user_id'];
            $row['members'] = $members;
            $teams[] = $row;
        }
        echo json_encode(['success' => true, 'teams' => $teams]);
        exit();
    }

    if($_POST['action'] === 'save_okr_new_team') {
        if (!$is_admin) { echo json_encode(['success' => false]); exit(); }
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if($id > 0) {
            $stmt = $conn->prepare("UPDATE okr_teams SET team_name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO okr_teams (team_name) VALUES (?)");
            $stmt->bind_param("s", $name);
        }
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit();
    }

    if($_POST['action'] === 'delete_okr_team_data') {
        if (!$is_admin) { echo json_encode(['success' => false]); exit(); }
        $id = intval($_POST['id'] ?? 0);
        $conn->query("DELETE FROM okr_teams WHERE id = $id");
        $conn->query("DELETE FROM okr_team_members WHERE team_id = $id");
        echo json_encode(['success' => true]);
        exit();
    }

    if($_POST['action'] === 'update_okr_team_members') {
        if (!$is_admin) { echo json_encode(['success' => false]); exit(); }
        $tid = intval($_POST['team_id'] ?? 0);
        $uids = $_POST['user_ids'] ?? [];
        $conn->query("DELETE FROM okr_team_members WHERE team_id = $tid");
        if(!empty($uids)) {
            $stmt = $conn->prepare("INSERT INTO okr_team_members (team_id, user_id) VALUES (?, ?)");
            foreach($uids as $uid) {
                $uid_int = intval($uid);
                $stmt->bind_param("ii", $tid, $uid_int);
                $stmt->execute();
            }
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($_POST['action'] === 'save_user_visibility') {
        if (!$is_admin) { echo json_encode(['success' => false]); exit(); }
        $hidden_users = $_POST['hidden_users'] ?? []; // Array of full_names
        
        // Reset all
        $conn->query("UPDATE users SET hide_from_okr = 0");
        
        if (!empty($hidden_users)) {
            $escaped = array_map(function($n) use ($conn) { return "'".$conn->real_escape_string($n)."'"; }, $hidden_users);
            $names_list = implode(',', $escaped);
            $conn->query("UPDATE users SET hide_from_okr = 1 WHERE full_name IN ($names_list)");
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($_POST['action'] === 'save_okr_settings') {
        if (!$is_admin) { echo json_encode(['success' => false]); exit(); }
        $settings = $_POST['settings'] ?? [];
        foreach($settings as $key => $val) {
            $stmt = $conn->prepare("INSERT INTO okr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $val, $val);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
        exit();
    }
}

// Fetch Users for Dropdown (Focusing on active participants)
$all_users_for_mgmt = [];
$u_res = $conn->query("SELECT id, full_name FROM users ORDER BY full_name ASC");
if($u_res) {
    while($u = $u_res->fetch_assoc()) $all_users_for_mgmt[] = $u;
}

$am_users = [];
try {
    // Try to fetch users marked as AM/BD (active OKR participants) and not hidden
    $am_res = $conn->query("SELECT id, full_name FROM users WHERE is_am_bd = 1 AND hide_from_okr = 0 ORDER BY full_name ASC");
    if ($am_res && $am_res->num_rows > 0) {
        while($u = $am_res->fetch_assoc()) { if(!empty($u['full_name'])) $am_users[] = $u; }
    } else {
        // Fallback to all non-hidden if no AM/BD flagged
        $am_res = $conn->query("SELECT id, full_name FROM users WHERE hide_from_okr = 0 ORDER BY full_name ASC");
        if ($am_res) {
            while($u = $am_res->fetch_assoc()) { if(!empty($u['full_name'])) $am_users[] = $u; }
        }
    }
} catch (Exception $e) {
    // Ultimate fallback if columns are missing
    $am_res = $conn->query("SELECT id, full_name FROM users ORDER BY full_name ASC LIMIT 50");
    if ($am_res) {
        while($u = $am_res->fetch_assoc()) $am_users[] = $u;
    }
}

if (empty($am_users)) {
    $am_users = [
        ['id' => 0, 'full_name' => 'Executive Team'],
        ['id' => 0, 'full_name' => 'Sales Team'],
        ['id' => 0, 'full_name' => 'Marketing Team']
    ];
}

// Fetch Teams for Tabs
// 1. New Logic: Fetch OKR Teams
$sale_teams_list = [];
try {
    $ot_res = $conn->query("SELECT team_name FROM okr_teams ORDER BY team_name ASC");
    if ($ot_res && $ot_res->num_rows > 0) {
        while($ot = $ot_res->fetch_assoc()) $sale_teams_list[] = $ot['team_name'];
    } else {
        // Migration Hint: If no OKR teams, maybe copy from old sale_teams once?
        $old_teams = $conn->query("SELECT name FROM sale_teams");
        if($old_teams && $old_teams->num_rows > 0) {
            while($ot = $old_teams->fetch_assoc()) {
                $tname = $conn->real_escape_string($ot['name']);
                $conn->query("INSERT INTO okr_teams (team_name) VALUES ('$tname')");
                $sale_teams_list[] = $ot['name'];
            }
        }
    }
} catch (Exception $e) {}

// Fallback logic
if (empty($sale_teams_list)) {
    $sale_teams_list = ['Team AHT BD Global'];
}

// Fetch Data for Render
$objectives = [];
$current_team_tab = $_GET['team'] ?? null;
$selected_user = $_GET['user'] ?? null;

// Find Current User's Teams (using OKR Teams)
$my_teams = [];
$uid = $_SESSION['user_id'];
$ut_res = $conn->query("SELECT ot.team_name FROM okr_team_members otm JOIN okr_teams ot ON otm.team_id = ot.id WHERE otm.user_id = $uid");
if ($ut_res) {
    while($ut = $ut_res->fetch_assoc()) $my_teams[] = $ut['team_name'];
}

$all_teams = [];
$at_res = $conn->query("SELECT name FROM sale_teams ORDER BY name ASC");
if ($at_res) {
    while($at = $at_res->fetch_assoc()) $all_teams[] = $at['name'];
}

// Default to user's first team if no tab selected and user has teams
if ($current_team_tab === null) {
    if (!empty($my_teams)) {
        $current_team_tab = $my_teams[0];
    } else {
        $current_team_tab = 'all';
    }
}

$team_members = [];
$selected_user_id = intval($_GET['user_id'] ?? 0);
$selected_user_name = null;

if ($current_team_tab !== 'all') {
    $safe_team = $conn->real_escape_string($current_team_tab);
    // Fetch members assigned to this specific OKR Team
    $members_res = $conn->query("
        SELECT DISTINCT u.id, u.full_name 
        FROM users u 
        JOIN okr_team_members otm ON u.id = otm.user_id 
        JOIN okr_teams ot ON otm.team_id = ot.id 
        WHERE ot.team_name = '$safe_team' AND u.hide_from_okr = 0
        ORDER BY u.full_name ASC
    ");
    while($m = $members_res->fetch_assoc()) {
        $team_members[] = $m;
        if ($m['id'] === $selected_user_id) $selected_user_name = $m['full_name'];
    }
}

$is_team_view = (isset($_GET['view']) && $_GET['view'] === 'team');

// Mặc định chọn người đầu tiên nếu chưa chọn user và không phải Team View
if ($selected_user_id === 0 && !empty($team_members) && !$is_team_view) {
    $selected_user_id = $team_members[0]['id'];
    $selected_user_name = $team_members[0]['full_name'];
}

$team_filter = '';
if ($current_team_tab !== 'all') {
    $safe_team = $conn->real_escape_string($current_team_tab);
    if ($is_team_view) {
        $team_filter = " WHERE (team = '$safe_team' AND (owner_id = 0 OR owner = '$safe_team'))";
    } elseif ($selected_user_id > 0) {
        $team_filter = " WHERE (owner_id = $selected_user_id OR (owner_id = 0 AND owner = '" . $conn->real_escape_string((string)$selected_user_name) . "'))";
    } else {
        $team_filter = " WHERE (team = '$safe_team')";
    }
}

// Add Time Filtering
if ($team_filter === '') {
    $team_filter = " WHERE 1=1";
}
if ($current_quarter > 0) {
    $team_filter .= " AND quarter = $current_quarter";
}
$team_filter .= " AND year = $current_year";

// Fetch Objectives with Owner's Team info (using OKR Teams)
$sql_o = "SELECT o.*, 
    (SELECT u.avatar FROM users u WHERE u.id = o.owner_id LIMIT 1) as owner_image,
    (SELECT ot.team_name FROM okr_teams ot 
     JOIN okr_team_members otm ON ot.id = otm.team_id 
     WHERE otm.user_id = o.owner_id LIMIT 1) as owner_team_name
    FROM okr_objectives o" . $team_filter . " ORDER BY sort_order ASC, id DESC";

$res_o = $conn->query($sql_o);
while ($o = $res_o->fetch_assoc()) {
    $oid = $o['id'];
    
    // Generate avatar initials for Objective owner
    $o_parts = explode(' ', $o['owner'] ?? '');
    $o['owner_avatar'] = !empty($o['owner']) ? mb_substr(end($o_parts), 0, 1, "UTF-8") : '??';
    
    // Fetch KRs (Results) first to build a parent map
    $results = [];
    $r_res = $conn->query("SELECT r.*, 
        (SELECT u.avatar FROM users u WHERE u.id = r.owner_id LIMIT 1) as owner_image,
        (SELECT u.avatar FROM users u WHERE u.id = r.owner_2_id LIMIT 1) as owner_2_image,
        (SELECT content FROM okr_explanations WHERE item_id = r.id AND item_type = 'metric' ORDER BY created_at DESC LIMIT 1) as latest_explanation FROM okr_results r WHERE r.objective_id = $oid ORDER BY sort_order ASC, FIELD(priority, 'high', 'medium', 'low') ASC, id DESC");
    if ($r_res) {
        while($r = $r_res->fetch_assoc()) {
            // Generate initials for owner 1
            $r_parts = explode(' ', $r['owner_name'] ?? '');
            $r['owner_avatar'] = !empty($r['owner_name']) ? mb_substr(end($r_parts), 0, 1, "UTF-8") : '??';
            
            // Generate initials for owner 2
            $o2_parts = explode(' ', $r['owner_2_name'] ?? '');
            $r['owner_2_avatar'] = !empty($r['owner_2_name']) ? mb_substr(end($o2_parts), 0, 1, "UTF-8") : '';
            $r['activities'] = []; // Placeholder for child KAs
            $results[$r['id']] = $r;
        }
    }
    
    // Fetch Activities (KAs) and assign to their parent KR if available
    $unlinked_activities = [];
    $a_res = $conn->query("SELECT a.*, 
        (SELECT u.avatar FROM users u WHERE u.id = a.owner_id LIMIT 1) as owner_image,
        (SELECT content FROM okr_explanations WHERE item_id = a.id AND item_type = 'activity' ORDER BY created_at DESC LIMIT 1) as latest_explanation FROM okr_key_activities a WHERE a.objective_id = $oid ORDER BY sort_order ASC, FIELD(priority, 'high', 'medium', 'low') ASC, id DESC");
    if ($a_res) {
        while($a = $a_res->fetch_assoc()) {
            // Generate initials for owner
            $a_parts = explode(' ', $a['owner_name'] ?? '');
            $a['owner_avatar'] = !empty($a['owner_name']) ? mb_substr(end($a_parts), 0, 1, "UTF-8") : '??';

            if ($a['result_id'] > 0 && isset($results[$a['result_id']])) {
                $results[$a['result_id']]['activities'][] = $a;
            } else {
                $unlinked_activities[] = $a;
            }
        }
    }
    
    $o['results'] = $results;
    $o['unlinked_activities'] = $unlinked_activities;
    $objectives[] = $o;
}

// Fetch Weekly Progress Map for all items to avoid N+1
$weekly_tracking_map = ['metric' => [], 'activity' => []];
$sql_w = "SELECT e1.item_id, e1.item_type, e1.week_num, e1.progress_value 
          FROM okr_explanations e1
          INNER JOIN (
              SELECT MAX(id) as max_id
              FROM okr_explanations
              WHERE quarter = $current_quarter AND year = $current_year
              GROUP BY week_num, item_id, item_type
          ) e2 ON e1.id = e2.max_id";
$res_w = $conn->query($sql_w);
if ($res_w) {
    while($w = $res_w->fetch_assoc()) {
        $weekly_tracking_map[$w['item_type']][$w['item_id']][$w['week_num']] = $w['progress_value'];
    }
}

// Fetch explanation counts (only count those with actual content)
$expl_counts = ['metric' => [], 'activity' => []];
$res_c = $conn->query("SELECT item_id, item_type, COUNT(*) as c 
                       FROM okr_explanations 
                       WHERE content IS NOT NULL 
                         AND TRIM(content) != '' 
                         AND TRIM(REPLACE(REPLACE(REPLACE(content, '<p>', ''), '</p>', ''), '<br>', '')) != ''
                       GROUP BY item_id, item_type");
if($res_c) {
    while($rc = $res_c->fetch_assoc()) $expl_counts[$rc['item_type']][$rc['item_id']] = $rc['c'];
}

// Group objectives by team
$grouped_objectives = [];
foreach ($objectives as $obj) {
    // Priority: Owner's actual team from DB > Explicit team column > Fallback
    $t = $obj['owner_team_name'] ?: ($obj['team'] ?: 'Unassigned Team');
    
    // Safety: If we are on a specific team tab, force the objective into that team's group 
    // if it passed the SQL filter but has a different owner_team_name calculated
    if ($current_team_tab !== 'all' && $current_team_tab !== null) {
        $t = $current_team_tab; 
    }
    
    $grouped_objectives[$t][] = $obj;
}

// Helper to get Status Badge Style
function getBadgeHtml($status) {
    $status = strtolower($status);
    $lbl = str_replace('_', ' ', ucwords($status));
    
    if ($status === 'on_track' || $status === 'in_progress' || $status === 'completed') {
        return '<span class="status-badge st-ontrack">' . $lbl . '</span>';
    }
    if ($status === 'at_risk' || $status === 'delayed') {
        return '<span class="status-badge st-atrisk">' . $lbl . '</span>';
    }
    return '<span class="status-badge st-pending">' . ($lbl ?: 'Pending') . '</span>';
}

function renderWeeklyTrackingDots($id, $type, $tracking_map, $current_week, $live_val = null) {
    $dots = '';
    for ($w = 1; $w <= 13; $w++) {
        $val = $tracking_map[$type][$id][$w] ?? null;
        
        // If it's the current week, prioritize the live value for visual consistency
        if ($w == $current_week && $live_val !== null) {
            $val = (float)$live_val;
        }

        $class = 'w-dot';
        if ($val !== null) {
            if ($val >= 90) $class .= ' st-high';
            else if ($val >= 70) $class .= ' st-mid';
            else $class .= ' st-low';
        }
        if ($w == $current_week) $class .= ' is-current';
        
        $title = "Week $w" . ($val !== null ? ": " . round((float)$val, 1) . "%" : ": No data");
        $dots .= "<span class='$class' title='$title'></span>";
    }
    return '<div class="weekly-mini-grid">' . $dots . '</div>';
}

function getLatestWeeklyProgress($id, $type, $map, $current_week, $live_fallback) {
    // Search all 13 weeks to find the absolute latest recorded progress 
    // This handles future entries (like 12% in W5/W6) as requested by the user
    for ($w = 13; $w >= 1; $w--) {
        if (isset($map[$type][$id][$w])) {
            return (float)$map[$type][$id][$w];
        }
    }
    return (float)$live_fallback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OKR Management - Sales & Marketing</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <!-- Professional Apple-Style OKR Dashboard Styling -->
    <style>
        .okr-dashboard {
            padding: 32px 40px;
            background-color: #fbfbfd;
            min-height: calc(100vh - 60px);
            font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, Arial, sans-serif;
        }
        .okr-page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
        .okr-title-group h2 { font-size: 32px; font-weight: 700; letter-spacing: -0.015em; color: #1d1d1f; margin: 0 0 4px 0; }
        .okr-title-group p { font-size: 15px; color: #86868b; margin: 0; font-weight: 400; }
        
        /* Modern Buttons */
        .btn-apple { background-color: #0071e3; color: white; border-radius: 980px; padding: 10px 24px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(0, 113, 227, 0.24); }
        .btn-apple:hover { background-color: #0077ed; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0, 113, 227, 0.32); }
        .btn-apple:active { transform: translateY(0); }
        
        .btn-secondary { background-color: #ffffff; color: #1d1d1f; border-radius: 980px; padding: 10px 20px; font-size: 14px; font-weight: 600; border: 1px solid #d2d2d7; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-secondary:hover { background-color: #f5f5f7; border-color: #86868b; }

        .okr-tabs { margin-bottom: 32px; display: flex; gap: 4px; border-bottom: 1px solid #e5e5ea; padding-bottom: 2px; }
        .tab-item { padding: 10px 20px; border-radius: 12px 12px 0 0; background: transparent; color: #86868b; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border-bottom: 2px solid transparent; }
        .tab-item:hover { color: #1d1d1f; background: #f5f5f7; }
        .tab-item.active { color: #0071e3; border-bottom: 2px solid #0071e3; }
        
        /* Member Ribbon & Time Filters */
        .member-ribbon { display: flex; gap: 12px; margin-bottom: 32px; flex-wrap: wrap; align-items: center; padding: 4px; }
        .member-item { display: flex; align-items: center; gap: 10px; padding: 6px 16px; background: #ffffff; border: 1px solid #e5e5ea; border-radius: 980px; text-decoration: none; color: #515154; font-size: 13px; font-weight: 500; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .member-item:hover { background: #f5f5f7; border-color: #d2d2d7; transform: translateY(-1px); }
        .member-item.active { background: #ffffff; color: #0071e3; border-color: #0071e3; box-shadow: 0 4px 12px rgba(0,113,227,0.12); }
        
        .member-avatar { width: 22px; height: 22px; border-radius: 50%; background: #f2f2f7; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: #515154; border: 1px solid #e5e5ea; }
        .member-item.active .member-avatar { background: #0071e3; color: #ffffff; border-color: #0071e3; }
        
        .btn-view-annual { margin-left: 6px; padding: 4px; font-size: 11px; color: #86868b; cursor: pointer; border: none; background: transparent; transition: all 0.2s; border-radius: 4px; display: flex; align-items: center; justify-content: center; }
        .btn-view-annual:hover { color: #0071e3; background: #f2f2f7; }

        .time-filter-group { display: flex; gap: 4px; background: #f2f2f7; padding: 4px; border-radius: 980px; align-items: center; border: 1px solid #e5e5ea; }
        .time-pill { padding: 4px 14px; border-radius: 980px; font-size: 12px; font-weight: 700; text-decoration: none; color: #86868b; transition: all 0.2s; }
        .time-pill:hover { color: #1d1d1f; }
        .time-pill.active { background: #ffffff; color: #0071e3; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .year-select { background: transparent; border: none; font-size: 12px; font-weight: 700; color: #1d1d1f; cursor: pointer; outline: none; padding: 0 8px; }

        /* Dashboard Analytics Cards */
        .apple-card { background: #ffffff; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.04); border: 1px solid #f2f2f7; }

        .okr-card { background: #ffffff; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #f2f2f7; margin-bottom: 40px; overflow: hidden; }
        .okr-card-header { padding: 32px; background: linear-gradient(to bottom, #ffffff, #fbfbfd); border-bottom: 1px solid #f2f2f7; display: flex; justify-content: space-between; align-items: flex-start; }
        
        .obj-left h3 { font-size: 24px; font-weight: 800; color: #1d1d1f; margin: 0; display: flex; align-items: center; gap: 16px; letter-spacing: -0.03em; line-height: 1.2; }
        .obj-left h3 i { color: #0071e3; font-size: 20px; text-shadow: 0 0 20px rgba(0, 113, 227, 0.15); }
        
        .obj-meta-row { margin-top: 14px; display: flex; gap: 12px; align-items: center; }
        .obj-meta-item { background: #f2f2f7; color: #515154; padding: 4px 12px; border-radius: 980px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; border: 1px solid #e5e5ea; transition: all 0.2s; }
        .obj-meta-item:hover { background: #ffffff; border-color: #0071e3; color: #0071e3; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .obj-meta-item i { font-size: 12px; opacity: 0.6; }
        .obj-meta-item strong { color: #1d1d1f; }
        .obj-meta-item:hover strong { color: #0071e3; }
        
        .okr-table { width: 100%; border-collapse: collapse; }
        .okr-table thead th { padding: 12px 20px; border-bottom: 1.5px solid #f2f2f7; font-weight: 700; color: #86868b; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; background: #fbfbfd; }
        .okr-table td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f8f8fa; }
        .okr-table tr:last-child td { border-bottom: none; }
        
        /* Activity vs KR distinction */
        /* Activity vs KR distinction - Dynamic Background based on Progress */
        .activity-header { 
            background: rgba(0, 113, 227, 0.04) !important; 
            border-top: 1px solid rgba(0, 113, 227, 0.12) !important; 
            border-bottom: 1px solid rgba(0, 113, 227, 0.05) !important;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        
        .activity-header.prog-high { background: <?php echo $color_high; ?> !important; color: <?php echo $text_high; ?> !important; border-top-color: rgba(0,0,0,0.05) !important; }
        .activity-header.prog-mid { background: <?php echo $color_mid; ?> !important; color: <?php echo $text_mid; ?> !important; border-top-color: rgba(0,0,0,0.05) !important; }
        .activity-header.prog-low { background: <?php echo $color_low; ?> !important; color: <?php echo $text_low; ?> !important; border-top-color: rgba(0,0,0,0.05) !important; }
        
        .row-kr-sub.prog-high { background: <?php echo $kr_color_high; ?> !important; color: <?php echo $kr_text_high; ?> !important; border-top-color: rgba(0,0,0,0.05) !important; }
        .row-kr-sub.prog-mid { background: <?php echo $kr_color_mid; ?> !important; color: <?php echo $kr_text_mid; ?> !important; border-top-color: rgba(0,0,0,0.05) !important; }
        .row-kr-sub.prog-low { background: <?php echo $kr_color_low; ?> !important; color: <?php echo $kr_text_low; ?> !important; border-top-color: rgba(0,0,0,0.05) !important; }
        
        .okr-card.obj-prog-high .okr-card-header { background: <?php echo $obj_color_high; ?> !important; color: <?php echo $obj_text_high; ?> !important; }
        .okr-card.obj-prog-mid .okr-card-header { background: <?php echo $obj_color_mid; ?> !important; color: <?php echo $obj_text_mid; ?> !important; }
        .okr-card.obj-prog-low .okr-card-header { background: <?php echo $obj_color_low; ?> !important; color: <?php echo $obj_text_low; ?> !important; }
        .okr-card.obj-prog-high .obj-left h3, .okr-card.obj-prog-high .obj-meta-item,
        .okr-card.obj-prog-mid .obj-left h3, .okr-card.obj-prog-mid .obj-meta-item,
        .okr-card.obj-prog-low .obj-left h3, .okr-card.obj-prog-low .obj-meta-item { color: inherit !important; }
        .okr-card.obj-prog-high .obj-left h3 i, .okr-card.obj-prog-high .obj-left h3 span,
        .okr-card.obj-prog-mid .obj-left h3 i, .okr-card.obj-prog-mid .obj-left h3 span,
        .okr-card.obj-prog-low .obj-left h3 i, .okr-card.obj-prog-low .obj-left h3 span { color: inherit !important; opacity: 1 !important; }

        .activity-header:hover, .row-kr-sub:hover { opacity: 0.9; }

        /* Priority-based borders for KA */
        .activity-header.row-prio-high { border-left-color: #ff3b30 !important; }
        .activity-header.row-prio-medium { border-left-color: #0071e3 !important; }
        
        .activity-header .item-main-title { font-size: 16px; font-weight: 700; color: inherit; letter-spacing: -0.01em; margin: 0; }
        .activity-header .item-number-circle { border-color: rgba(0, 113, 227, 0.3); color: #0071e3; background: #fff; box-shadow: 0 2px 6px rgba(0, 113, 227, 0.08); }
        .item-header-row { display: flex; align-items: center; gap: 12px; }
        
        /* Ensure child elements inherit color in colored rows */
        .prog-high .item-number-circle, .prog-mid .item-number-circle, .prog-low .item-number-circle { background: rgba(255,255,255,0.9); border-color: transparent; }
        .prog-high .btn-add-inline, .prog-mid .btn-add-inline, .prog-low .btn-add-inline { color: inherit; background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.2); }
        .prog-high .btn-add-inline:hover, .prog-mid .btn-add-inline:hover, .prog-low .btn-add-inline:hover { background: rgba(255,255,255,0.25); }
        .ka-badge, .kr-badge { padding: 2px 8px; border-radius: 6px; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; transition: all 0.2s; }
        .ka-badge { background: rgba(0, 71, 227, 0.08); color: #0071e3; border-color: rgba(0, 71, 227, 0.1); font-size: 11px; }
        .kr-badge { background: rgba(100, 116, 139, 0.08); color: #64748b; border-color: rgba(100, 116, 139, 0.1); font-size: 10px; margin-right: 8px; }

        .prog-high .ka-badge, .prog-mid .ka-badge, .prog-low .ka-badge,
        .prog-high .kr-badge, .prog-mid .kr-badge, .prog-low .kr-badge { background: rgba(255,255,255,0.2) !important; color: inherit !important; border-color: rgba(255,255,255,0.3) !important; }

        .row-kr-sub { 
            background-color: #ffffff; 
            transition: all 0.2s ease;
            color: #48484a;
        }
        .row-kr-sub:hover { 
            background-color: #f5f5f7 !important; 
        }
        .row-kr-sub.row-completed { background-color: <?php echo $kr_color_high; ?> !important; color: <?php echo $kr_text_high; ?> !important; opacity: 0.7; }
        .row-kr-sub.row-at-risk { background-color: <?php echo $kr_color_low; ?> !important; color: <?php echo $kr_text_low; ?> !important; }
        .row-kr-sub.row-completed:hover { background-color: #e8f5e9 !important; }
        .row-kr-sub.row-at-risk:hover { background-color: #ffe4e6 !important; }
        .row-at-risk { background-color: #fff1f2 !important; }
        .row-at-risk:hover { background-color: #ffe4e6 !important; }
        .row-kr-sub td { padding: 12px 20px !important; color: inherit; }
        .row-kr-sub .col-name { padding-left: 78px !important; position: relative; }
        .row-kr-sub .col-name::before {
            content: '';
            position: absolute;
            left: 46px;
            top: -12px;
            bottom: 50%;
            width: 24px;
            border-left: 1.5px solid #e5e5ea;
            border-bottom: 1.5px solid #e5e5ea;
            border-bottom-left-radius: 10px;
        }
        .row-kr-sub .item-main-title { font-size: 13.5px !important; color: inherit !important; font-weight: 500 !important; display: flex; align-items: center; gap: 8px; }
        .row-kr-sub .item-main-title::before { content: ''; width: 6px; height: 6px; background: #d2d2d7; border-radius: 50%; flex-shrink: 0; }

        /* Modern Progress Bars */
        .progress-tiny { width: 100%; height: 6px; background: #f2f2f7; border-radius: 10px; overflow: hidden; position: relative; }
        .progress-bar { height: 100%; border-radius: 10px; transition: width 0.8s cubic-bezier(0.65, 0, 0.35, 1); }
        .progress-bar[style*="width: 100%"] { background: linear-gradient(90deg, #34c759, #30d158); box-shadow: 0 0 8px rgba(52, 199, 89, 0.3); }

        /* Avatars & Badges */
        .user-avatar { width: 32px; height: 32px; font-size: 11px; background: #f2f2f7; color: #1d1d1f; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid #ffffff; box-shadow: 0 4px 8px rgba(0,0,0,0.06); }
        .avatar-purple { background: #f5f0ff; color: #af52de; }
        
        .status-badge { padding: 4px 12px; border-radius: 980px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .st-ontrack { background: #e3f9e5; color: #248a3d; }
        .st-ontrack::before { background: #34c759; box-shadow: 0 0 6px #34c759; }
        .st-atrisk { background: #fff4e5; color: #b25e09; }
        .st-atrisk::before { background: #ff9500; box-shadow: 0 0 6px #ff9500; }
        .st-pending { background: #f2f2f7; color: #86868b; }
        .st-pending::before { background: #86868b; }

        .btn-add-inline { color: #0071e3; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; padding: 6px 12px; border-radius: 980px; transition: all 0.2s; background: rgba(0, 113, 227, 0.05); border: 1px solid transparent; }
        .btn-add-inline:hover { background: rgba(0, 113, 227, 0.12); border-color: rgba(0, 113, 227, 0.2); }
        
        .btn-edit-row, .btn-delete-row { opacity: 0; padding: 8px; border-radius: 10px; transition: all 0.2s; background: transparent; border: none; cursor: pointer; color: #8e8e93; }
        .okr-table tr:hover .btn-edit-row, .okr-table tr:hover .btn-delete-row { opacity: 1; }
        .btn-edit-row:hover { background: #f2f2f7; color: #0071e3; }
        .btn-delete-row:hover { background: #fff1f0; color: #ff3b30; }

        .item-number-circle { width: 26px; height: 26px; background: #ffffff; color: #1d1d1f; border-radius: 10px; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e5ea; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        
        /* Sidebar Drawer */
        .apple-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.15); backdrop-filter: saturate(180%) blur(20px); z-index: 9999; display: none; justify-content: flex-end; }
        .apple-modal { background: rgba(255,255,255,0.92); width: 480px; height: 100%; box-shadow: -20px 0 60px rgba(0,0,0,0.05); transform: translateX(100%); transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; border-left: 1px solid rgba(0,0,0,0.05); }
        
        
        /* Celebration row */
        .row-completed { background-color: #f2fdf5 !important; }

        /* Weekly Mini Grid */
        .weekly-mini-grid { display: flex; gap: 4px; margin-top: 8px; align-items: center; }
        .w-dot { width: 7px; height: 7px; background: #e5e5ea; border-radius: 50%; transition: all 0.2s; position: relative; }
        .w-dot.st-high { background: #34c759; box-shadow: 0 0 4px rgba(52, 199, 89, 0.2); }
        .w-dot.st-mid { background: #ffcc00; box-shadow: 0 0 4px rgba(255, 204, 0, 0.2); }
        .w-dot.st-low { background: #ff3b30; box-shadow: 0 0 4px rgba(255, 59, 48, 0.2); }
        .w-dot.is-current { width: 9px; height: 9px; border: 1.5px solid #1d1d1f; box-shadow: 0 0 0 1.5px #fff; z-index: 1; }
        .w-dot:hover { transform: scale(1.6); z-index: 10; cursor: help; }
        .row-completed .item-main-title { color: #248a3d !important; text-decoration: line-through; opacity: 0.6; }

        .apple-modal.active { transform: translateX(0); }
        .swal2-container { z-index: 20000 !important; }
        
        .modal-body { flex: 1; overflow-y: auto; padding: 40px 32px; }
        .modal-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #1d1d1f; letter-spacing: -0.01em; }
        .modal-subtitle { font-size: 13px; color: #86868b; margin-bottom: 24px; line-height: 1.4; }
        .modal-control { margin-bottom: 24px; }
        .modal-control label { display: block; font-size: 13px; font-weight: 600; color: #515154; margin-bottom: 8px; }
        .modal-control input[type="number"], .modal-control input[type="text"], .modal-control select { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #d2d2d7; border-radius: 10px; font-size: 14px; font-family: inherit; outline: none; background: #fbfbfd; }
        .modal-control input:focus, .modal-control select:focus { border-color: #0071e3; background: #fff; box-shadow: 0 0 0 3px rgba(0,113,227,0.1); }
        
        .modal-actions { padding: 24px 32px; border-top: 1px solid #f1f5f9; background: #fbfbfd; display: flex; justify-content: space-between; gap: 12px; }
        
        #loadingSpinner, #addLoadingSpinner { display: none; margin-left: 8px; animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* Weekly Progress Styles */
        .week-pill { padding: 12px 8px; background: #ffffff; border: 1px solid #e5e5ea; border-radius: 14px; text-align: center; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; gap: 4px; position:relative; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .week-pill:hover { border-color: #0071e3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .week-pill.active { background: #1d1d1f !important; color: white !important; border-color: #1d1d1f !important; box-shadow: 0 8px 20px rgba(0,0,0,0.15); z-index: 2; }
        .week-pill .w-label { font-size: 10px; font-weight: 800; color: #86868b; text-transform: uppercase; letter-spacing: 0.02em; }
        .week-pill.active .w-label { color: rgba(255,255,255,0.7); }
        .week-pill .w-dates { font-size: 9px; font-weight: 500; color: #86868b; line-height: 1.2; }
        .week-pill.active .w-dates { color: rgba(255,255,255,0.6); }
        .week-pill .w-val { font-size: 14px; font-weight: 800; color: #1d1d1f; margin-top: 2px; }
        .week-pill.active .w-val { color: #ffffff; }
        
        /* Status Colors - Refined */
        .week-pill.st-high { background: #f2fdf5; color: #166534; border-color: #bbf7d0; }
        .week-pill.st-high .w-val { color: #166534; }
        .week-pill.st-mid { background: #fffcf0; color: #854d0e; border-color: #fef08a; }
        .week-pill.st-mid .w-val { color: #854d0e; }
        .week-pill.st-low { background: #fff1f2; color: #991b1b; border-color: #fecaca; }
        .week-pill.st-low .w-val { color: #991b1b; }

        .week-pill.is-current::before { content: 'NOW'; position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: #ff3b30; color: white; font-size: 7px; font-weight: 900; padding: 2px 6px; border-radius: 4px; border: 2px solid white; letter-spacing: 0.05em; }

    /* Accordion Styles Refined */
    .okr-card-header { cursor: pointer; position: relative; transition: background 0.2s; display: flex; align-items: center; }
    .okr-card-header:hover { background: #fbfbfd; }
    .chevron-icon { 
        font-size: 14px; 
        color: #1d1d1f; /* Black */
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); 
        margin-left: auto; /* Push to right */
        padding: 0 10px;
    }
    .okr-card-body-wrapper {
        display: grid;
        grid-template-rows: 1fr;
        transition: grid-template-rows 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }
    .okr-card.collapsed .okr-card-body-wrapper {
        grid-template-rows: 0fr;
    }
    .okr-card.collapsed { margin-bottom: 8px; }
    .okr-card.collapsed .chevron-icon { transform: rotate(-180deg); }

    
    /* Premium Explanation Sidebar Drawer */
    .explanation-sidebar {
        position: fixed;
        top: 0;
        right: -500px;
        width: 500px;
        height: 100%;
        background: #ffffff;
        box-shadow: -10px 0 40px rgba(0,0,0,0.1);
        z-index: 10001;
        transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        border-left: 1px solid #e5e5e7;
    }
    .explanation-sidebar.active { right: 0; }
    .explanation-sidebar .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid #f2f2f7;
        background: rgba(255,255,255,0.8);
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky; top:0; z-index: 10002;
    }
    .explanation-sidebar .sidebar-body {
        padding: 20px;
        flex: 1;
        overflow-y: auto;
        background-color: #fbfbfd;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .explanation-sidebar .sidebar-footer {
        padding: 24px;
        border-top: 1px solid #f2f2f7;
        background: #ffffff;
    }
    
    .history-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #e5e5e7;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .history-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        border-bottom: 1px solid #f2f2f7;
        padding-bottom: 6px;
    }
    .history-user { font-size: 13px; font-weight: 700; color: #1d1d1f; display: flex; align-items: center; gap: 8px; }
    .history-date { font-size: 11px; color: #86868b; }
    .history-text { font-size: 14px; color: #424245; line-height: 1.5; }
    .history-avatar { width: 24px; height: 24px; background: #f2f2f7; border-radius: 50%; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; color: #0071e3; border: 1px solid #0071e3; }

    .has-explanation-icon {
        color: #ff9500; /* Apple Warning Amber */
        font-size: 14px;
        margin-left: 8px;
        cursor: pointer;
        opacity: 0.8;
        vertical-align: middle;
    }
    .has-explanation-icon:hover { opacity: 1; transform: scale(1.2); color: #ff3b30; }
    
    .sidebar-overlay {
        position: fixed;
        top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.1);
        z-index: 10000;
        display: none;
        backdrop-filter: blur(2px);
    }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include __DIR__ . '/../includes/topbar.php'; ?>
            
            <div class="okr-dashboard">
                <div class="okr-page-header">
                    <div class="okr-title-group">
                        <h2>Sales & Marketing</h2>
                        <p>Track progress, ownership & statuses of your key objectives.</p>
                    </div>
                    <div style="display:flex; gap:16px; align-items:center;">
                        <div class="time-filter-group">
                            <?php 
                            $base_url = "?team=" . urlencode($current_team_tab) . ($selected_user ? "&user=".urlencode($selected_user) : "");
                            for($q=1;$q<=4;$q++): ?>
                                <a href="<?php echo $base_url . "&quarter=$q&year=$current_year"; ?>" class="time-pill <?php echo ($current_quarter == $q) ? 'active' : ''; ?>">Q<?php echo $q; ?></a>
                            <?php endfor; ?>
                            <div style="width:1px; height:16px; background:#d2d2d7; margin:0 4px;"></div>
                            <select class="year-select" onchange="window.location.href='<?php echo $base_url; ?>&quarter=<?php echo $current_quarter; ?>&year='+this.value">
                                <?php 
                                $start_y = date('Y') - 1;
                                $end_y = date('Y') + 1;
                                for($y=$start_y;$y<=$end_y;$y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($current_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php if ($is_admin): ?>
                            <button class="btn-secondary" onclick="openVisibilityModal()" title="Quản lý hiển thị Users"><i class="fas fa-users-cog"></i></button>
                            <button class="btn-secondary" onclick="openOkrSettingsModal()" title="Cài đặt màu sắc Progress"><i class="fas fa-palette"></i></button>
                            <button class="btn-secondary" onclick="openOkrTeamManagementModal()" title="Quản lý Team OKR"><i class="fas fa-users-class"></i> Manage Teams</button>
                        <?php endif; ?>
                        <button class="btn-apple" onclick="openCreateObjModal()"><i class="fas fa-plus"></i> Add Objective</button>
                    </div>
                </div>

                <div class="okr-tabs">
                    <a href="?team=all" class="tab-item <?php echo ($current_team_tab === 'all') ? 'active' : ''; ?>"><i class="fas fa-chart-pie" style="font-size:12px; margin-right:6px;"></i>Dashboard</a>
                    <?php foreach ($sale_teams_list as $t_name): ?>
                    <a href="?team=<?php echo urlencode($t_name); ?>" class="tab-item <?php echo ($current_team_tab === $t_name) ? 'active' : ''; ?>"><?php echo htmlspecialchars($t_name); ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if ($current_team_tab !== 'all' && !empty($team_members)): ?>
                <div class="member-ribbon">
                    <span style="font-size: 11px; color: #86868b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-right: 8px;">Members:</span>
                    
                    <!-- Team OKR Tab (Moved to front) -->
                    <div class="member-item team-tab <?php echo ($is_team_view) ? 'active' : ''; ?>" style="padding: 4px 12px; background: <?php echo $is_team_view ? '#0071e3' : '#f5f5f7'; ?>; border-radius:12px;">
                        <a href="?team=<?php echo urlencode($current_team_tab); ?>&view=team&quarter=<?php echo $current_quarter; ?>&year=<?php echo $current_year; ?>" style="text-decoration:none; color:<?php echo $is_team_view ? 'white' : '#1d1d1f'; ?>; display:flex; align-items:center; gap:8px; font-weight:700;">
                            <i class="fas fa-users-cog"></i>
                            <span style="white-space:nowrap;">Team OKR</span>
                        </a>
                        <button class="btn-view-annual" onclick="viewAnnualOKR(0, '<?php echo addslashes($current_team_tab); ?>', <?php echo $current_year; ?>)" title="Xem OKR của Team cả năm">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                    </div>
                    <?php foreach ($team_members as $m): ?>
                        <div class="member-item <?php echo ($selected_user_id == $m['id']) ? 'active' : ''; ?>" style="padding: 4px 10px; padding-right: 4px;">
                            <a href="?team=<?php echo urlencode($current_team_tab); ?>&user_id=<?php echo $m['id']; ?>&quarter=<?php echo $current_quarter; ?>&year=<?php echo $current_year; ?>" style="text-decoration:none; color:inherit; display:flex; align-items:center; gap:8px;">
                                <div class="member-avatar" style="flex-shrink:0;"><?php echo strtoupper(substr($m['full_name'], 0, 2)); ?></div>
                                <span style="white-space:nowrap;"><?php echo htmlspecialchars($m['full_name']); ?></span>
                            </a>
                            <button class="btn-view-annual" onclick="viewAnnualOKR(<?php echo $m['id']; ?>, '<?php echo addslashes($m['full_name']); ?>', <?php echo $current_year; ?>)" title="Xem OKR cả năm">
                                <i class="fas fa-calendar-alt"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                    
                </div>
                <?php endif; ?>

                <?php if ($current_team_tab === 'all'): ?>
                    <!-- DASHBOARD CONTENT -->
                    <div class="dashboard-analytics-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-top:20px; margin-bottom:30px;">
                        <?php 
                        $total_objs = count($objectives);
                        $status_dist = ['completed'=>0,'on_track'=>0,'delayed'=>0,'at_risk'=>0,'pending'=>0,'in_progress'=>0];
                        $prio_dist = ['high'=>0, 'medium'=>0, 'low'=>0];
                        $owner_counts = [];
                        $team_avg = []; $team_cnt = [];
                        foreach($objectives as $o) {
                            $status_dist[$o['status']] = ($status_dist[$o['status']] ?? 0) + 1;
                            
                            $p_val = strtolower($o['priority'] ?? 'medium');
                            if(!isset($prio_dist[$p_val])) $p_val = 'medium';
                            $prio_dist[$p_val]++;

                            $oname = $o['owner'] ?: 'Unassigned';
                            $owner_counts[$oname] = ($owner_counts[$oname] ?? 0) + 1;

                            $t = $o['owner_team_name'] ?: 'Other';
                            $team_avg[$t] = ($team_avg[$t] ?? 0) + $o['progress'];
                            $team_cnt[$t] = ($team_cnt[$t] ?? 0) + 1;
                        }
                        $chart_labels = array_keys($team_avg);
                        $chart_data = [];
                        foreach($chart_labels as $l) $chart_data[] = round($team_avg[$l]/($team_cnt[$l]?:1), 1);
                        
                        arsort($owner_counts);
                        $top_owners = array_slice($owner_counts, 0, 8);
                        ?>
                        <div class="apple-card" style="padding:24px; min-height:300px; display:flex; flex-direction:column;">
                            <h4 style="font-size:13px; font-weight:700; color:#86868b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:16px;">Team Progress (%)</h4>
                            <canvas id="teamProgressChart" style="max-height:220px;"></canvas>
                        </div>
                        <div class="apple-card" style="padding:24px; min-height:300px; display:flex; flex-direction:column;">
                            <h4 style="font-size:13px; font-weight:700; color:#86868b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:16px;">Status Distribution</h4>
                            <canvas id="statusDistChart" style="max-height:220px;"></canvas>
                        </div>
                        <div class="apple-card" style="padding:24px; min-height:300px; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
                            <div style="font-size:48px; font-weight:800; color:#1d1d1f;"><?php echo $total_objs; ?></div>
                            <div style="font-size:14px; font-weight:600; color:#86868b;">Total Objectives</div>
                            <div style="margin-top:20px; height:1px; background:#f2f2f7; width:60%;"></div>
                            <div style="margin-top:20px; display:flex; gap:16px;">
                                <div>
                                    <div style="font-size:20px; font-weight:700; color:#10b981;"><?php echo $status_dist['completed']; ?></div>
                                    <div style="font-size:11px; font-weight:700; color:#86868b; text-transform:uppercase;">Done</div>
                                </div>
                                <div>
                                    <div style="font-size:20px; font-weight:700; color:#ef4444;"><?php echo $status_dist['at_risk']; ?></div>
                                    <div style="font-size:11px; font-weight:700; color:#86868b; text-transform:uppercase;">At Risk</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($current_team_tab !== 'all' && empty($grouped_objectives)): ?>
                    <div style="text-align:center; padding: 60px 20px; color: #86868b; background: white; border-radius: 12px; border: 1px dashed #d2d2d7; margin-top:20px;">
                        <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom:16px; opacity:0.5;"></i>
                        <p>No objectives found for this team yet.</p>
                    </div>
                <?php endif; ?>

                <?php if ($current_team_tab !== 'all'): ?>
                <?php $global_obj_idx = 1; foreach ($grouped_objectives as $team_group_name => $objs_in_team): ?>
                    <div class="team-group-section" style="margin-top: 32px;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                            <h3 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #86868b; margin:0;"><?php echo htmlspecialchars($team_group_name); ?></h3>
                            <div style="flex:1; height:1px; background:#e5e7eb;"></div>
                            <span style="font-size: 12px; font-weight: 600; color: #1d1d1f; background: #f5f5f7; padding: 2px 8px; border-radius: 12px;"><?php echo count($objs_in_team); ?> Objectives</span>
                        </div>

                        <?php foreach ($objs_in_team as $obj): 
                            $obj_prog_class = 'obj-prog-low';
                            if ($obj['progress'] >= 90) $obj_prog_class = 'obj-prog-high';
                            else if ($obj['progress'] >= 70) $obj_prog_class = 'obj-prog-mid';
                        ?>
                        <div class="okr-card <?php echo (count($objs_in_team) > 3) ? 'collapsed' : ''; ?> <?php echo $obj_prog_class; ?>" id="obj-<?php echo $obj['id']; ?>">
                        <div class="okr-card-header" onclick="toggleObjectiveAccordion(this, event)">
                            <div class="obj-left">
                                <h3 data-id="<?php echo $obj['id']; ?>">
                                    <i class="fas fa-bullseye" style="color:#6366f1; margin-right:8px;"></i>
                                    <span style="color:#6366f1; opacity:0.8; font-weight:800; margin-right:4px;">OBJ <?php echo $global_obj_idx++; ?> :</span>
                                    <?php echo htmlspecialchars($obj['title']); ?>
                                </h3>
                                <div class="obj-meta-row">
                                    <div class="obj-meta-item" style="display:flex; align-items:center;">
                                        <div class="user-avatar" style="width:20px; height:20px; font-size:9px; margin-right:8px; border-width:1px; overflow:hidden;">
                                            <?php if(!empty($obj['owner_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($obj['owner_image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <?php echo $obj['owner_avatar']; ?>
                                            <?php endif; ?>
                                        </div>
                                        Owner: <strong><?php echo htmlspecialchars($obj['owner']); ?></strong>
                                    </div>
                                    <div class="obj-meta-item">
                                        <i class="fas fa-chart-line"></i>
                                        Progress: <strong><?php echo round($obj['progress'], 1); ?>%</strong>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:4px; margin-left:8px;">
                                        <button class="btn-edit-row btn-edit-objective" style="opacity:1;"
                                                data-id="<?php echo $obj['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($obj['title']); ?>"
                                                data-team="<?php echo htmlspecialchars($obj['team']); ?>"
                                                data-owner="<?php echo htmlspecialchars($obj['owner']); ?>"
                                                data-owner-id="<?php echo intval($obj['owner_id'] ?? 0); ?>"
                                                data-status="<?php echo htmlspecialchars($obj['status']); ?>"
                                                data-sort-order="<?php echo intval($obj['sort_order'] ?? 0); ?>"
                                                data-quarter="<?php echo intval($obj['quarter'] ?? 1); ?>"
                                                data-year="<?php echo intval($obj['year'] ?? 2026); ?>">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="btn-delete-row" style="opacity:1;" onclick="deleteObjective(<?php echo $obj['id']; ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <button class="btn-apple" style="padding: 6px 14px; font-size: 11px; height: 30px; margin-left:12px; box-shadow:none;" onclick="openAddModal(<?php echo $obj['id']; ?>, 'metric')">
                                            <i class="fas fa-plus"></i> Add KR
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="obj-right">
                                <?php echo getBadgeHtml($obj['status']); ?>
                                <i class="fas fa-chevron-down chevron-icon"></i>
                            </div>
                        </div>

                    <div class="okr-card-body-wrapper">
                    <div class="okr-card-body" style="padding: 0 12px 12px 12px; min-height:0;">
                        <table class="okr-table">
                            <thead>
                                <tr style="background: #fbfbfd; border-bottom: 1px solid #f2f2f7;">
                                    <th style="padding: 12px 16px; text-align: left; font-size: 11px; color: #86868b; text-transform: uppercase;">Key Result / Activity</th>
                                    <th style="width: 60px; text-align: center; font-size: 11px; color: #86868b; text-transform: uppercase;">Owner</th>
                                    <th style="width: 60px; text-align: center; font-size: 11px; color: #86868b; text-transform: uppercase;">Weight</th>
                                    <th style="width: 100px; text-align: left; font-size: 11px; color: #86868b; text-transform: uppercase;">Status</th>
                                    <th style="width: 180px; text-align: left; font-size: 11px; color: #86868b; text-transform: uppercase;">Progress (%) & Weekly Tracking</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($obj['results']) && empty($obj['unlinked_activities'])): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:60px 40px; color:#86868b;">
                                            <div style="font-size:14px; margin-bottom:12px; font-style:italic;">No data found. Start by adding a Key Result.</div>
                                            <button class="btn-apple" style="margin:0 auto; box-shadow:0 4px 12px rgba(0,113,227,0.1);" onclick="openAddModal(<?php echo $obj['id']; ?>, 'metric')">
                                                <i class="fas fa-plus-circle"></i> Add First Key Result
                                            </button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <!-- Results and their nested Activities -->
                                    <?php 
                                    $r_idx = 1; 
                                    foreach ($obj['results'] as $r): 
                                        $progress_pct = ($r['target_value'] > 0) ? ($r['current_value'] / $r['target_value']) * 100 : 0;
                                        $progress_pct = min(100, round($progress_pct, 1));
                                        
                                        $display_prog_kr = getLatestWeeklyProgress($r['id'], 'metric', $weekly_tracking_map, $current_week_num, $progress_pct); 
                                        $prog_class_kr = 'prog-low';
                                        if ($display_prog_kr >= 90) $prog_class_kr = 'prog-high';
                                        else if ($display_prog_kr >= 70) $prog_class_kr = 'prog-mid';

                                        $row_class_kr = 'activity-header row-prio-' . ($r['priority'] ?? 'medium') . ' ' . $prog_class_kr;
                                        if(($r['status'] ?? '') === 'completed') $row_class_kr .= ' row-completed';
                                    ?>
                                        <tr class="<?php echo $row_class_kr; ?>">
                                            <td class="col-name">
                                                <div class="item-header-row">
                                                    <span class="item-number-circle"><?php echo $r_idx++; ?></span>
                                                    <h4 class="item-main-title">
                                                        <span class="kr-badge">KR <?php echo ($r_idx - 1); ?></span>
                                                        <?php echo htmlspecialchars($r['metric_name'] ?? ''); ?>
                                                        <?php if(($expl_counts['metric'][$r['id']] ?? 0) > 0): ?>
                                                            <i class="fas fa-exclamation-circle has-explanation-icon" onclick="openExplanationSidebar(<?php echo $r['id']; ?>, 'metric', '<?php echo addslashes($r['metric_name'] ?? ''); ?>')" title="Xem giải trình/lưu ý"></i>
                                                        <?php endif; ?>
                                                    </h4>
                                                    <button class="btn-add-inline" title="Add activity to this result" onclick="openAddModal(<?php echo $obj['id']; ?>, 'activity', <?php echo $r['id']; ?>)" style="margin-left:8px;"><i class="fas fa-plus-circle"></i> Add KA</button>
                                                </div>
                                            </td>
                                            <td class="col-owner" style="text-align:center;">
                                                <div style="display:flex; justify-content:center; align-items:center;">
                                                    <div class="user-avatar avatar-purple" style="margin:0; overflow:hidden; position:relative; z-index:1; border: 1.5px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="<?php echo htmlspecialchars($r['owner_name'] ?? ''); ?>">
                                                        <?php if(!empty($r['owner_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($r['owner_image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <?php echo $r['owner_avatar'] ?? ''; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if(!empty($r['owner_2_name'])): ?>
                                                    <div class="user-avatar avatar-purple" style="margin-left:-12px; overflow:hidden; position:relative; z-index:2; border: 1.5px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="<?php echo htmlspecialchars($r['owner_2_name'] ?? ''); ?>">
                                                        <?php if(!empty($r['owner_2_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($r['owner_2_image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <?php echo $r['owner_2_avatar'] ?? ''; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="col-weight" style="text-align:center; font-weight:600;"><?php echo intval($r['weight'] ?? 0); ?>%</td>
                                            <td class="col-status"><?php echo getBadgeHtml($r['status'] ?? 'pending'); ?></td>
                                            <td class="col-progress">
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <span style="font-weight:700; font-size:12px; min-width:32px;"><span id="td-val-metric-<?php echo $r['id']; ?>"><?php echo intval($display_prog_kr); ?></span>%</span>
                                                    <div class="progress-tiny"><div class="progress-bar" id="td-bar-metric-<?php echo $r['id']; ?>" style="width: <?php echo $display_prog_kr; ?>%; background:#0071e3;"></div></div>
                                                </div>
                                                <?php echo renderWeeklyTrackingDots($r['id'], 'metric', $weekly_tracking_map, $current_week_num, $progress_pct); ?>
                                            </td>
                                            <td class="col-action">
                                                <div style="display:flex; align-items:center; gap:4px;">
                                                    <button class="btn-edit-row" onclick="openUpdateModal('<?php echo addslashes($r['metric_name'] ?? ''); ?>', 'metric', <?php echo $r['id']; ?>, <?php echo round($progress_pct, 1); ?>, 100, '<?php echo $r['status'] ?? 'pending'; ?>', <?php echo intval($r['owner_id'] ?? 0); ?>, <?php echo intval($r['owner_2_id'] ?? 0); ?>, '<?php echo $r['priority'] ?? 'medium'; ?>', <?php echo intval($r['weight'] ?? 0); ?>, <?php echo $obj['id']; ?>, 0, <?php echo intval($r['sort_order'] ?? 0); ?>)"><i class="fas fa-history"></i></button>
                                                    <button class="btn-delete-row" title="Delete KR" onclick="deleteOkrItem(<?php echo $r['id']; ?>, 'metric')"><i class="fas fa-trash-alt"></i></button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Nested Activities for this KR -->
                                        <?php if (!empty($r['activities'])): $ka_idx = 1; foreach ($r['activities'] as $ka): 
                                            $display_prog_ka = getLatestWeeklyProgress($ka['id'], 'activity', $weekly_tracking_map, $current_week_num, $ka['progress']); 
                                            $prog_class_ka = 'prog-low';
                                            if ($display_prog_ka >= 90) $prog_class_ka = 'prog-high';
                                            else if ($display_prog_ka >= 70) $prog_class_ka = 'prog-mid';
                                            
                                            $row_class_ka = 'row-kr-sub ' . $prog_class_ka;
                                            if (($ka['status'] ?? '') === 'completed') $row_class_ka .= ' row-completed';
                                        ?>
                                            <tr class="<?php echo $row_class_ka; ?>">
                                                <td class="col-name">
                                                    <h4 class="item-main-title">
                                                        <span class="ka-badge">KA <?php echo $ka_idx++; ?></span>
                                                        <?php echo htmlspecialchars($ka['activity_name'] ?? ''); ?>
                                                        <?php if(($expl_counts['activity'][$ka['id']] ?? 0) > 0): ?>
                                                            <i class="fas fa-exclamation-circle has-explanation-icon" onclick="openExplanationSidebar(<?php echo $ka['id']; ?>, 'activity', '<?php echo addslashes($ka['activity_name'] ?? ''); ?>')" title="Xem giải trình/lưu ý"></i>
                                                        <?php endif; ?>
                                                    </h4>
                                                </td>
                                                <td class="col-owner" style="text-align:center;">
                                                    <div class="user-avatar avatar-purple" style="margin:0 auto; overflow:hidden;" title="<?php echo htmlspecialchars($ka['owner_name'] ?? ''); ?>">
                                                        <?php if(!empty($ka['owner_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($ka['owner_image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <?php echo $ka['owner_avatar'] ?? '??'; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="col-weight" style="text-align:center; font-weight:500; color:#86868b;"><?php echo intval($ka['weight'] ?? 0); ?>%</td>
                                                <td class="col-status"><?php echo getBadgeHtml($ka['status']); ?></td>
                                                <td class="col-progress">
                                                    <div style="display:flex; align-items:center; gap:8px;">
                                                        <span style="font-weight:700; font-size:11px; color:#8e8e93; min-width:32px;"><span><?php echo intval($display_prog_ka); ?></span>%</span>
                                                        <div class="progress-tiny"><div class="progress-bar" style="width: <?php echo $display_prog_ka; ?>%; background:#34c759;"></div></div>
                                                    </div>
                                                    <?php echo renderWeeklyTrackingDots($ka['id'], 'activity', $weekly_tracking_map, $current_week_num, $ka['progress']); ?>
                                                </td>
                                                <td class="col-action">
                                                    <div style="display:flex; align-items:center; gap:4px;">
                                                        <button class="btn-edit-row" onclick="openUpdateModal('<?php echo addslashes($ka['activity_name'] ?? ''); ?>', 'activity', <?php echo $ka['id']; ?>, <?php echo floatval($ka['progress'] ?? 0); ?>, 100, '<?php echo $ka['status'] ?? 'pending'; ?>', <?php echo intval($ka['owner_id'] ?? 0); ?>, 0, '<?php echo $ka['priority'] ?? 'medium'; ?>', <?php echo intval($ka['weight'] ?? 0); ?>, <?php echo $obj['id']; ?>, <?php echo $r['id']; ?>, <?php echo intval($ka['sort_order'] ?? 0); ?>)"><i class="fas fa-history"></i></button>
                                                        <button class="btn-delete-row" title="Delete KA" onclick="deleteOkrItem(<?php echo $ka['id']; ?>, 'activity')"><i class="fas fa-trash-alt"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    <?php endforeach; ?>

                                    <!-- Unlinked Activities -->
                                    <?php if (!empty($obj['unlinked_activities'])): ?>
                                        <tr style="background:#fbfbfd;"><td colspan="6" style="font-size:10px; font-weight:700; color:#86868b; text-transform:uppercase; letter-spacing:0.05em; padding:12px 20px; border-top:1px solid #f2f2f7;">Other Activities (Unlinked) <button class="btn-add-inline" onclick="openAddModal(<?php echo $obj['id']; ?>, 'activity')" style="margin-left:8px;">+ Add KA</button></td></tr>
                                        <?php foreach ($obj['unlinked_activities'] as $ka): 
                                            $display_prog_ka = getLatestWeeklyProgress($ka['id'], 'activity', $weekly_tracking_map, $current_week_num, $ka['progress']); 
                                            $row_class = '';
                                            if (($ka['status'] ?? '') === 'completed') $row_class = 'row-completed';
                                        ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td class="col-name" style="padding-left:20px !important;">
                                                    <h4 class="item-main-title"><?php echo htmlspecialchars($ka['activity_name'] ?? ''); ?></h4>
                                                </td>
                                                <td class="col-owner" style="text-align:center;">
                                                    <div class="user-avatar" style="margin:0 auto; overflow:hidden;" title="<?php echo htmlspecialchars($ka['owner_name'] ?? ''); ?>">
                                                        <?php if(!empty($ka['owner_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($ka['owner_image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <?php echo $ka['owner_avatar'] ?? '??'; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="col-weight" style="text-align:center; color:#86868b; font-weight:500;"><?php echo intval($ka['weight'] ?? 0); ?>%</td>
                                                <td class="col-status"><?php echo getBadgeHtml($ka['status']); ?></td>
                                                <td class="col-progress">
                                                    <div style="display:flex; align-items:center; gap:8px;">
                                                        <span style="font-weight:700; font-size:11px; color:#8e8e93; min-width:32px;"><span><?php echo intval($display_prog_ka); ?></span>%</span>
                                                        <div class="progress-tiny"><div class="progress-bar" style="width: <?php echo $display_prog_ka; ?>%; background:#34c759;"></div></div>
                                                    </div>
                                                    <?php echo renderWeeklyTrackingDots($ka['id'], 'activity', $weekly_tracking_map, $current_week_num, $ka['progress']); ?>
                                                </td>
                                                <td class="col-action">
                                                    <div style="display:flex; align-items:center; gap:4px;">
                                                        <button class="btn-edit-row" onclick="openUpdateModal('<?php echo addslashes($ka['activity_name'] ?? ''); ?>', 'activity', <?php echo $ka['id']; ?>, <?php echo floatval($ka['progress'] ?? 0); ?>, 100, '<?php echo $ka['status'] ?? 'pending'; ?>', <?php echo intval($ka['owner_id'] ?? 0); ?>, 0, '<?php echo $ka['priority'] ?? 'medium'; ?>', <?php echo intval($ka['weight'] ?? 0); ?>, <?php echo $obj['id']; ?>, 0, <?php echo intval($ka['sort_order'] ?? 0); ?>)"><i class="fas fa-history"></i></button>
                                                        <button class="btn-delete-row" title="Delete KA" onclick="deleteOkrItem(<?php echo $ka['id']; ?>, 'activity')"><i class="fas fa-trash-alt"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                    </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Reusable Update Modal -->
    <div class="apple-modal-overlay" id="updateModalOverlay">
        <div class="apple-modal" id="updateModalContent">
            <div class="modal-body">
                <h3 class="modal-title" id="updateModalTitle">Update Progress</h3>
                <p class="modal-subtitle" id="updateItemName">Wait...</p>
                
                <input type="hidden" id="updateItemId">
                <input type="hidden" id="updateItemType">

                <div class="modal-control">
                    <label>Title / Description</label>
                    <input type="text" id="updateItemNameInput" placeholder="Enter title...">
                </div>

                <div class="modal-control">
                    <label>Progress (%) & Weekly Tracking (Q<?php echo $current_quarter; ?>)</label>
                    <div id="weeklyProgressGrid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; margin-top: 12px;">
                        <!-- Weeks 1-13 generated by JS -->
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="flex:1;">
                            <label style="font-size:11px; margin-bottom:4px;">Selected Week Progress</label>
                            <input type="number" id="updateItemVal" value="0" min="0" max="100" placeholder="0-100">
                        </div>
                        <div style="width:120px;">
                            <label style="font-size:11px; margin-bottom:4px;">Target</label>
                            <input type="text" value="100%" disabled style="background:#eee; text-align:center;">
                        </div>
                    </div>
                    <input type="hidden" id="updateItemWeek" value="<?php echo $current_week_num; ?>">
                </div>

                <div class="modal-control">
                    <label>Status</label>
                    <select id="updateItemStatus">
                        <option value="on_track">On Track</option>
                        <option value="in_progress">In Progress</option>
                        <option value="delayed">Delayed</option>
                        <option value="at_risk">At Risk</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="modal-control">
                    <label>Owner (Assignee)</label>
                    <select id="updateItemOwner">
                        <option value="0">-- Chọn User (Lấy từ Setting) --</option>
                        <?php foreach ($am_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['full_name']); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-control" id="updateItemOwner2Control" style="display:none;">
                    <label>Co-Owner (Assignee 2 - Only for KR)</label>
                    <select id="updateItemOwner2">
                        <option value="0">-- None / Extra Owner --</option>
                        <?php foreach ($am_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['full_name']); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-control">
                    <label>Priority, Weight (%) & Sort Order</label>
                    <div style="display:flex; gap:10px;">
                        <select id="updateItemPriority" style="flex:1;">
                            <option value="high">High Priority</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <input type="number" id="updateItemWeight" placeholder="Weight %" style="width:90px;" min="0" max="100">
                        <input type="number" id="updateItemSortOrder" placeholder="Sort" style="width:70px;" min="0">
                    </div>
                </div>
                <div class="modal-control">
                    <label>Giải trình / Ghi chú (Mới)</label>
                    <div id="quillEditor" style="height: 150px; background: #fbfbfd; border-radius: 8px; border: 1px solid #d2d2d7;"></div>
                    <input type="hidden" id="updateItemExplanation">
                </div>

                <div class="modal-control" id="updateItemParentResultControl" style="display:none;">
                    <label>Liên kết với Key Result (Kết quả then chốt)</label>
                    <select id="updateItemResult">
                        <option value="0">-- Không liên kết --</option>
                    </select>
                </div>
                <div class="modal-control" id="updateItemLinkActivityControl" style="display:none;">
                    <label>Liên kết với Công việc (Key Activity)</label>
                    <select id="updateItemActivity">
                        <option value="0">-- Không liên kết --</option>
                    </select>
                </div>
                <div class="history-box" id="updateItemHistory">
                    <p style="font-size:11px; color:#94a3b8; text-align:center;">Đang tải lịch sử giải trình...</p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                <button class="btn-apple" onclick="saveUpdateModal(this)">
                    Save Changes <i class="fas fa-spinner" id="loadingSpinner"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Reusable ADD Modal -->
    <div class="apple-modal-overlay" id="addModalOverlay">
        <div class="apple-modal" id="addModalContent">
            <div class="modal-body">
                <h3 class="modal-title" id="addModalTitle">Add New Item</h3>
                <p class="modal-subtitle">Define the new sub-item and assign an owner.</p>
                
                <input type="hidden" id="addModalObjId">
                <input type="hidden" id="addModalType">

                <div class="modal-control">
                    <label id="addModalNameLbl">Description / Name</label>
                    <input type="text" id="addModalName" placeholder="E.g. SEO Campaign">
                </div>

                <div class="modal-control">
                    <label>Owner (Assignee)</label>
                    <select id="addModalOwner">
                        <option value="0">-- Chọn User --</option>
                        <?php foreach ($am_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['full_name']); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-control" id="addModalOwner2Control" style="display:none;">
                    <label>Co-Owner (Assignee 2 - Only for KR)</label>
                    <select id="addModalOwner2">
                        <option value="0">-- None --</option>
                        <?php foreach ($am_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['full_name']); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="modal-control" id="addModalParentResultControl" style="display:none;">
                    <label>Linked Key Result</label>
                    <select id="addModalResult">
                        <option value="0">-- Không liên kết --</option>
                    </select>
                </div>
                <div class="modal-control" id="addModalActivityControl" style="display:none;">
                    <label>Link Existing Activity (KA)</label>
                    <select id="addModalActivity">
                        <option value="0">-- Không liên kết --</option>
                    </select>
                </div>

                <input type="hidden" id="addModalActivityId" value="0">
                <div class="modal-control">
                    <label>Priority, Weight (%) & Sort Order</label>
                    <div style="display:flex; gap:10px;">
                        <select id="addItemPriority" style="flex:1;">
                            <option value="high">High Priority</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <input type="number" id="addItemWeight" placeholder="Weight %" style="width:80px;" min="0" max="100" value="0">
                        <input type="number" id="addItemSort" placeholder="Sort" style="width:70px;" min="0" value="0">
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button class="btn-apple" onclick="saveAddModal(this)">
                    Create <i class="fas fa-spinner" id="addLoadingSpinner"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- OBJECTIVE MODAL -->
    <div class="apple-modal-overlay" id="objModalOverlay">
        <div class="apple-modal" id="objModalContent">
            <div class="modal-body">
                <h3 class="modal-title">Objective Details</h3>
                <p class="modal-subtitle">Modify the objective title, owner, and status.</p>
                <input type="hidden" id="objModalId" value="">
                
                <div class="modal-control">
                    <label>Objective Title</label>
                    <input type="text" id="objModalTitle">
                </div>
                
                <div class="modal-control">
                    <label>Owner (Assignee)</label>
                    <select id="objModalOwner">
                        <option value="0" disabled selected>-- Chọn Người sở hữu (User hoặc Team) --</option>
                        <optgroup label="Hệ thống / Teams">
                            <?php foreach ($all_teams as $tname): ?>
                                <option value="0" data-type="team" data-name="<?php echo htmlspecialchars($tname); ?>" data-team="<?php echo htmlspecialchars($tname); ?>">[Team] <?php echo htmlspecialchars($tname); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Thành viên cá nhân">
                            <?php foreach ($am_users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" data-type="user" data-name="<?php echo htmlspecialchars($u['full_name']); ?>" data-team="<?php echo htmlspecialchars($current_team_tab === 'all' ? '' : $current_team_tab); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="modal-control">
                    <label>Status</label>
                    <select id="objModalStatus">
                        <option value="pending">Pending</option>
                        <option value="on_track">On Track</option>
                        <option value="at_risk">At Risk</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="modal-row" style="display:flex; gap:16px;">
                    <div class="modal-control" style="flex:1;">
                        <label>Quarter</label>
                        <select id="objModalQuarter">
                            <option value="1">Q1</option>
                            <option value="2">Q2</option>
                            <option value="3">Q3</option>
                            <option value="4">Q4</option>
                        </select>
                    </div>
                    <div class="modal-control" style="flex:1;">
                        <label>Year</label>
                        <select id="objModalYear">
                            <?php 
                            $start_y = date('Y') - 1;
                            $end_y = date('Y') + 1;
                            for($y=$start_y;$y<=$end_y;$y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-control">
                    <label>Position / Sort Order</label>
                    <input type="number" id="objModalSortOrder" value="0" placeholder="Số nhỏ hiện trước">
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeObjectiveModal()">Cancel</button>
                <button class="btn-apple">
                    <span id="objModalBtnText">Save Changes</span>
                    <i class="fas fa-spinner" id="objLoadingSpinner" style="display:none; margin-left:8px; animation: spin 1s linear infinite;"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ANNUAL VIEW DRAWER -->
    <div class="apple-modal-overlay" id="annualDrawerOverlay">
        <div class="apple-modal" id="annualDrawerContent" style="width: 50%;">
            <div class="modal-body" style="background: #fbfbfd; padding: 48px 40px;">
                <h3 class="modal-title" id="annualDrawerTitle">Annual OKR Review</h3>
                <p class="modal-subtitle" id="annualDrawerSubtitle">Compact view of objectives across all quarters.</p>
                <div id="annualOKRContent"></div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeAnnualDrawer()" style="width:100%;">Close Review</button>
            </div>
        </div>
    </div>

    <!-- VISIBILITY SETTINGS MODAL -->
    <div class="apple-modal-overlay" id="visibilityModalOverlay">
        <div class="apple-modal" id="visibilityModalContent">
            <div class="modal-body">
                <h3 class="modal-title">Visibility Settings</h3>
                <p class="modal-subtitle">Select users to HIDE from the OKR member list.</p>
                <div id="visibilityUserList" style="display:flex; flex-direction:column; gap:10px;">
                    <?php 
                    $all_u_res = $conn->query("SELECT full_name, hide_from_okr FROM users WHERE role != 'admin' AND is_am_bd = 1 ORDER BY full_name ASC");
                    if ($all_u_res && $all_u_res->num_rows > 0):
                        while($uu = $all_u_res->fetch_assoc()):
                        ?>
                        <label style="display:flex; align-items:center; gap:12px; padding:12px; background:#fbfbfd; border:1px solid #e5e5ea; border-radius:12px; cursor:pointer;">
                            <input type="checkbox" class="vis-check" value="<?php echo htmlspecialchars($uu['full_name']); ?>" <?php echo $uu['hide_from_okr'] ? 'checked' : ''; ?> style="width:18px; height:18px;">
                            <span style="font-size:14px; color:#1d1d1f; font-weight:500;"><?php echo htmlspecialchars($uu['full_name']); ?></span>
                        </label>
                        <?php endwhile;
                    else:
                        echo '<p style="font-size:13px; color:#86868b; text-align:center; padding:20px;">Không tìm thấy User Sales/BD nào.</p>';
                    endif; ?>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeVisibilityModal()">Cancel</button>
                <button class="btn-apple" onclick="saveVisibilitySettings(this)">
                    Save Settings <i class="fas fa-spinner" id="visLoadingSpinner" style="display:none; margin-left:8px; animation: spin 1s linear infinite;"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- OKR COLOR SETTINGS MODAL -->
    <div class="apple-modal-overlay" id="okrSettingsModalOverlay">
        <div class="apple-modal" id="okrSettingsModalContent">
            <div class="modal-body" style="padding: 24px 32px;">
                <h4 style="margin: 0 0 15px 0; font-size: 14px; color: #ff9500; border-bottom: 1px solid #f2f2f7; padding-bottom: 8px;">OBJ Progress Colors (Objectives)</h4>
                <div class="modal-control">
                    <label>OBJ High Progress (>= 90%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_obj_color_high" value="<?php echo $obj_color_high; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_obj_text_high" value="<?php echo $obj_text_high; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
                <div class="modal-control">
                    <label>OBJ Medium Progress (70% - 89%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_obj_color_mid" value="<?php echo $obj_color_mid; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_obj_text_mid" value="<?php echo $obj_text_mid; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
                <div class="modal-control">
                    <label>OBJ Low Progress (< 70%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_obj_color_low" value="<?php echo $obj_color_low; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_obj_text_low" value="<?php echo $obj_text_low; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>

                <h4 style="margin: 30px 0 15px 0; font-size: 14px; color: #0071e3; border-bottom: 1px solid #f2f2f7; padding-bottom: 8px;">KA Progress Colors (Key Activities)</h4>
                <div class="modal-control">
                    <label>KA High Progress (>= 90%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_color_high" value="<?php echo $color_high; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_text_high" value="<?php echo $text_high; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
                <div class="modal-control">
                    <label>KA Medium Progress (70% - 89%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_color_mid" value="<?php echo $color_mid; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_text_mid" value="<?php echo $text_mid; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
                <div class="modal-control">
                    <label>KA Low Progress (< 70%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_color_low" value="<?php echo $color_low; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_text_low" value="<?php echo $text_low; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>

                <h4 style="margin: 30px 0 15px 0; font-size: 14px; color: #af52de; border-bottom: 1px solid #f2f2f7; padding-bottom: 8px;">KR Progress Colors (Key Results)</h4>
                <div class="modal-control">
                    <label>KR High Progress (>= 90%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_kr_color_high" value="<?php echo $kr_color_high; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_kr_text_high" value="<?php echo $kr_text_high; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
                <div class="modal-control">
                    <label>KR Medium Progress (70% - 89%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_kr_color_mid" value="<?php echo $kr_color_mid; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_kr_text_mid" value="<?php echo $kr_text_mid; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
                <div class="modal-control">
                    <label>KR Low Progress (< 70%)</label>
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;"><small style="color:#86868b;">Background</small><br><input type="color" id="cfg_kr_color_low" value="<?php echo $kr_color_low; ?>" style="height:38px; width:100%; padding:2px;"></div>
                        <div style="flex:1;"><small style="color:#86868b;">Font Color</small><br><input type="color" id="cfg_kr_text_low" value="<?php echo $kr_text_low; ?>" style="height:38px; width:100%; padding:2px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeOkrSettingsModal()">Cancel</button>
                <button class="btn-apple" onclick="saveOkrSettings(this)">
                    Save Colors <i class="fas fa-spinner" id="okrSettingsLoadingSpinner" style="display:none; margin-left:8px; animation: spin 1s linear infinite;"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- EXPLANATION SIDEBAR -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeExplanationSidebar()"></div>
    <div class="explanation-sidebar" id="explanationSidebar">
        <div class="sidebar-header">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700;">Explanations</h3>
                <span id="sidebarItemName" style="font-size:12px; color:#86868b;">Item Name</span>
            </div>
            <button class="btn-delete-row" style="opacity:1; background:none;" onclick="closeExplanationSidebar()">
                <i class="fas fa-times" style="font-size:20px;"></i>
            </button>
        </div>
        <div class="sidebar-body" id="sidebarHistoryContent">
            <!-- Loaded via AJAX -->
        </div>
        <div class="sidebar-footer">
            <input type="hidden" id="sidebarItemId">
            <input type="hidden" id="sidebarItemType">
            <label style="font-size:11px; font-weight:700; color:#86868b; text-transform:uppercase; margin-bottom:8px; display:block;">Quick Explanation (Current Week)</label>
            <div id="sidebarQuillEditor" style="height: 100px; background: #fff; border-radius: 8px; border: 1px solid #d2d2d7; margin-bottom:12px;"></div>
            <button class="btn-apple" style="width:100%;" onclick="saveSidebarExplanation(this)">
                Add Note <i class="fas fa-paper-plane" style="margin-left:8px;"></i>
                <i class="fas fa-spinner" id="sidebarLoading" style="display:none; margin-left:8px; animation:spin 1s linear infinite;"></i>
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        // Initialize Quill
        const quill = new Quill('#quillEditor', {
            theme: 'snow',
            placeholder: 'Nhập diễn giải hoặc ghi chú chi tiết tại đây...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        /* SIDEBAR EXPLANATION */
        const sidebarQuill = new Quill('#sidebarQuillEditor', {
            theme: 'snow',
            placeholder: 'Viết giải trình nhanh tại đây...',
            modules: { toolbar: [['bold', 'italic', 'underline'], ['clean']] }
        });

        function openExplanationSidebar(id, type, name) {
            document.getElementById('sidebarItemId').value = id;
            document.getElementById('sidebarItemType').value = type;
            document.getElementById('sidebarItemName').innerText = name;
            sidebarQuill.root.innerHTML = '';
            
            document.getElementById('sidebarHistoryContent').innerHTML = '<p style="font-size:12px; color:#86868b; text-align:center;">Loading history...</p>';
            document.getElementById('sidebarOverlay').style.display = 'block';
            document.getElementById('explanationSidebar').classList.add('active');

            $.post('/modules/okr/index.php', {
                action: 'fetch_explanation_history',
                id: id,
                type: type,
                quarter: <?php echo $current_quarter; ?>,
                year: <?php echo $current_year; ?>
            }, function(res) {
                if(res.success) {
                    // Filter history to show only non-empty explanations
                    const visibleHistory = res.history.filter(h => {
                        if (!h.content) return false;
                        const text = h.content.replace(/<[^>]*>/g, '').trim();
                        return text.length > 0;
                    });

                    if (visibleHistory.length > 0) {
                        let html = '';
                        visibleHistory.forEach(h => {
                            const avatarInitials = h.user_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                            html += `
                                <div class="history-card">
                                    <div class="history-card-header">
                                        <div class="history-user">
                                            <div class="history-avatar">${avatarInitials}</div>
                                            <span>${h.user_name}</span>
                                        </div>
                                        <span class="history-date">${h.formatted_date}</span>
                                    </div>
                                    <div class="history-text">${h.content}</div>
                                </div>
                            `;
                        });
                        document.getElementById('sidebarHistoryContent').innerHTML = html;
                    } else {
                        document.getElementById('sidebarHistoryContent').innerHTML = '<p style="font-size:12px; color:#86868b; text-align:center; padding:40px;">Chưa có nội dung giải trình nào được ghi lại.</p>';
                    }
                }
            }, 'json');
        }

        function closeExplanationSidebar() {
            document.getElementById('explanationSidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').style.display = 'none';
        }

        function saveSidebarExplanation(btn) {
            const id = document.getElementById('sidebarItemId').value;
            const type = document.getElementById('sidebarItemType').value;
            const content = sidebarQuill.root.innerHTML;
            if(sidebarQuill.getText().trim() === '') return alert('Vui lòng nhập nội dung giải trình!');

            document.getElementById('sidebarLoading').style.display = 'inline-block';
            $.post('/modules/okr/index.php', {
                action: 'update_okr_item',
                id: id,
                type: type,
                explanation: content,
                name: document.getElementById('sidebarItemName').innerText, // just to satisfy the handler
                week_num: <?php echo $current_week_num; ?>,
                save_quarter: <?php echo $current_quarter; ?>,
                save_year: <?php echo $current_year; ?>,
                partial_update: 1 // flag to indicate we only update explanation
            }, function(res) {
                document.getElementById('sidebarLoading').style.display = 'none';
                if(res.success) {
                    openExplanationSidebar(id, type, document.getElementById('sidebarItemName').innerText);
                }
            }, 'json');
        }

        /* UPDATE MODAL */
        function toggleObjectiveAccordion(header, event) {
            if (event.target.closest('button') || event.target.closest('.obj-meta-row') || event.target.closest('.obj-right')) {
                return;
            }
            const card = header.closest('.okr-card');
            card.classList.toggle('collapsed');
        }

    function openUpdateModal(itemName, type, id, currentValue, targetValue, status, ownerId, owner2Id, priority, weight, objId, resultId, sortOrder) {
            document.getElementById('updateModalTitle').innerText = 'Update ' + (type === 'metric' ? 'Key Result' : 'Activity');
            document.getElementById('updateItemId').value = id;
            document.getElementById('updateItemType').value = type;
            document.getElementById('updateItemName').innerText = itemName;
            document.getElementById('updateItemNameInput').value = itemName;
            document.getElementById('updateItemVal').value = currentValue;
            document.getElementById('updateItemStatus').value = status;
            document.getElementById('updateItemOwner').value = ownerId || 0;
            
            if (type === 'metric') {
                document.getElementById('updateItemOwner2Control').style.display = 'block';
                document.getElementById('updateItemOwner2').value = owner2Id || 0;
            } else {
                document.getElementById('updateItemOwner2Control').style.display = 'none';
            }

            document.getElementById('updateItemPriority').value = priority || 'medium';
            document.getElementById('updateItemWeight').value = weight || 0;
            document.getElementById('updateItemSortOrder').value = sortOrder || 0;
            document.getElementById('updateItemWeek').value = <?php echo $current_week_num; ?>;
            quill.root.innerHTML = ''; // Clear Editor

            // Handle parent Result linking UI for Key Activities
            const resultControl = document.getElementById('updateItemParentResultControl');
            const resultSelect = document.getElementById('updateItemResult');
            const linkActivityControl = document.getElementById('updateItemLinkActivityControl');
            const linkActivitySelect = document.getElementById('updateItemActivity');
            
            if (type === 'activity') {
                resultControl.style.display = 'block';
                linkActivityControl.style.display = 'none';
                resultSelect.innerHTML = '<option value="0">-- Không liên kết --</option>';
                const objCard = document.querySelector(`.okr-card-header h3[data-id="${objId}"]`)?.closest('.okr-card') || document.body;
                const krRows = objCard.querySelectorAll('table tr.activity-header'); 
                krRows.forEach(row => {
                   const editBtn = row.querySelector('.btn-edit-row');
                   if (!editBtn) return;
                   const matches = editBtn.getAttribute('onclick').match(/metric',\s*(\d+)/);
                   if (!matches) return;
                   const r_id = matches[1];
                   const r_name = row.querySelector('.item-main-title').innerText.replace(/^KR \d+/, '').trim();
                   const opt = document.createElement('option');
                   opt.value = r_id;
                   opt.innerText = r_name;
                   if (parseInt(r_id) === parseInt(resultId)) opt.selected = true;
                   resultSelect.appendChild(opt);
                });
            } else if (type === 'metric') {
                resultControl.style.display = 'none';
                linkActivityControl.style.display = 'block';
                linkActivitySelect.innerHTML = '<option value="0">-- Không liên kết --</option>';
                // Find unlinked KAs in this objective
                const objCard = document.querySelector(`.okr-card-header h3[data-id="${objId}"]`)?.closest('.okr-card') || document.body;
                const unlinkedKAs = objCard.querySelectorAll('tr:not(.activity-header):not(.row-kr-sub) .item-main-title');
                unlinkedKAs.forEach(title => {
                    const row = title.closest('tr');
                    const editBtn = row.querySelector('.btn-edit-row');
                    if (!editBtn) return;
                    const matches = editBtn.getAttribute('onclick').match(/activity',\s*(\d+)/);
                    if (!matches) return;
                    const a_id = matches[1];
                    const opt = document.createElement('option');
                    opt.value = a_id;
                    opt.innerText = title.innerText.trim();
                    linkActivitySelect.appendChild(opt);
                });
            } else {
                resultControl.style.display = 'none';
                linkActivityControl.style.display = 'none';
            }

            // Both KA and KR use progress % (0-100)
            document.getElementById('updateItemVal').value = currentValue;
            // Fetch Weekly Data & History
            $.post('/modules/okr/index.php', {
                action: 'fetch_explanation_history',
                id: id,
                type: type,
                quarter: <?php echo $current_quarter; ?>,
                year: <?php echo $current_year; ?>
            }, function(res) {
                // Populate Weekly Grid
                let gridHtml = '';
                const currentWeek = <?php echo $current_week_num; ?>;
                const selectedWeek = document.getElementById('updateItemWeek').value || currentWeek;
                
                const qStartDate = new Date('<?php echo $q_start_date_str; ?>');
                
                // Switch to Bi-weekly periods (7 periods for 13 weeks)
                for (let p = 1; p <= 7; p++) {
                    const startWeek = (p - 1) * 2 + 1;
                    const endWeek = Math.min(13, p * 2);
                    
                    const weekData = res.weekly && res.weekly[endWeek] ? res.weekly[endWeek] : null;
                    const hasData = weekData !== null;
                    const progress = hasData ? weekData.progress : '-';
                    
                    // Logic: highlight period if current week falls within it
                    const currentWeek = <?php echo $current_week_num; ?>;
                    const isCurrentPeriod = (currentWeek >= startWeek && currentWeek <= endWeek);
                    
                    const activeClass = (res.selectedPeriod == p || (!res.selectedPeriod && isCurrentPeriod)) ? 'active' : '';
                    
                    let statusClass = '';
                    if (hasData) {
                        if (progress >= 90) statusClass = 'st-high';
                        else if (progress >= 70) statusClass = 'st-mid';
                        else statusClass = 'st-low';
                    }
                    
                    const currentClass = isCurrentPeriod ? 'is-current' : '';

                    // Calculate Bi-weekly dates
                    let dStart = new Date(qStartDate);
                    dStart.setDate(qStartDate.getDate() + (startWeek - 1) * 7);
                    let dEnd = new Date(qStartDate);
                    dEnd.setDate(qStartDate.getDate() + (endWeek * 7) - 1);
                    
                    const dateRange = dStart.toLocaleDateString('vi-VN', {day:'2-digit', month:'2-digit'}) 
                                    + ' - ' 
                                    + dEnd.toLocaleDateString('vi-VN', {day:'2-digit', month:'2-digit'});
                    
                    const periodLabel = (startWeek == endWeek) ? `Week ${startWeek}` : `W${startWeek}-W${endWeek}`;
                    
                    gridHtml += `
                        <div class="week-pill ${activeClass} ${statusClass} ${currentClass}" title="${dateRange}" onclick="selectWeekForUpdate(${endWeek}, ${progress === '-' ? 0 : progress}, ${p})">
                            <span class="w-label">${periodLabel}</span>
                            <span class="w-dates">${dateRange}</span>
                            <span class="w-val">${progress}${hasData ? '%' : ''}</span>
                        </div>
                    `;
                }
                document.getElementById('weeklyProgressGrid').innerHTML = gridHtml;

                if(res.success && res.history.length > 0) {
                    let html = '<label style="font-size:11px; color:#475569; font-weight:800; text-transform:uppercase; margin-bottom:10px; display:block;">Lịch sử giải trình</label>';
                    res.history.forEach(h => {
                        const isMe = (h.user_id == <?php echo intval($_SESSION['user_id'] ?? 0); ?>);
                        html += `
                            <div class="history-item ${isMe ? 'me' : ''}">
                                <div class="history-meta">
                                    <span style="font-weight:700; color:#475569;">${h.user_name}</span>
                                    <span>${h.formatted_date}</span>
                                </div>
                                <div class="history-bubble">
                                    <div class="history-content">${h.content}</div>
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById('updateItemHistory').innerHTML = html;
                } else {
                    document.getElementById('updateItemHistory').innerHTML = '<p style="font-size:11px; color:#94a3b8; text-align:center;">Chưa có lịch sử giải trình.</p>';
                }
            }, 'json');
            
            // Set max for progress bar activities
            if(type === 'activity') {
                 document.getElementById('updateItemVal').max = 100;
            } else {
                 document.getElementById('updateItemVal').removeAttribute('max');
            }
            
            let modal = document.getElementById('updateModalOverlay');
            modal.style.display = 'flex';
            setTimeout(function() { document.getElementById('updateModalContent').classList.add('active'); }, 10);
        }

        function selectWeekForUpdate(week, val, periodIdx) {
            document.getElementById('updateItemWeek').value = week;
            document.getElementById('updateItemVal').value = val;
            
            // UI Update: Toggle active class
            document.querySelectorAll('.week-pill').forEach(p => p.classList.remove('active'));
            const pills = document.querySelectorAll('.week-pill');
            if (pills[periodIdx-1]) pills[periodIdx-1].classList.add('active');
        }

        function closeUpdateModal() {
            let modalContent = document.getElementById('updateModalContent');
            modalContent.classList.remove('active');
            setTimeout(function() { document.getElementById('updateModalOverlay').style.display = 'none'; }, 200);
        }

        function saveUpdateModal(btn) {
            const id = document.getElementById('updateItemId').value;
            const type = document.getElementById('updateItemType').value;
            const name = document.getElementById('updateItemNameInput').value;
            const val = document.getElementById('updateItemVal').value;
            const status = document.getElementById('updateItemStatus').value;
            
            const ownerSelect = document.getElementById('updateItemOwner');
            const owner_id = ownerSelect.value;
            const selectedOpt = ownerSelect.options[ownerSelect.selectedIndex];
            const owner_name = selectedOpt ? (selectedOpt.getAttribute('data-name') || '') : '';

            const owner2Select = document.getElementById('updateItemOwner2');
            const owner_2_id = owner2Select.value;
            const selectedOpt2 = owner2Select.options[owner2Select.selectedIndex];
            const owner_2_name = selectedOpt2 ? (selectedOpt2.getAttribute('data-name') || '') : '';

            const priority = document.getElementById('updateItemPriority').value;
            const weight = document.getElementById('updateItemWeight').value;
            const explanation = quill.root.innerHTML;
            
            if (quill.getText().trim().length === 0) {
                 // Option: don't save if empty, or just let it pass
            }
            
            document.getElementById('loadingSpinner').style.display = 'inline-block';

            const postData = {
                action: 'update_okr_item',
                id: id,
                type: type,
                name: name,
                val: val,
                status: status,
                owner_id: owner_id,
                owner_name: owner_name,
                owner_2_id: owner_2_id,
                owner_2_name: owner_2_name,
                priority: priority,
                weight: weight,
                sort_order: document.getElementById('updateItemSortOrder').value,
                explanation: explanation,
                week_num: document.getElementById('updateItemWeek').value,
                parent_id: (type === 'activity') ? document.getElementById('updateItemResult').value : 0,
                activity_id: (type === 'metric') ? document.getElementById('updateItemActivity').value : 0,
                save_quarter: <?php echo $current_quarter; ?>,
                save_year: <?php echo $current_year; ?>
            };

            $.post('/modules/okr/index.php', postData, function(res) {
                document.getElementById('loadingSpinner').style.display = 'none';
                if(res.success) {
                    if (status === 'completed') {
                        // Congratulatory Firework Burst
                        var duration = 3 * 1000;
                        var animationEnd = Date.now() + duration;
                        var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 10000 };

                        function randomInRange(min, max) {
                          return Math.random() * (max - min) + min;
                        }

                        var interval = setInterval(function() {
                          var timeLeft = animationEnd - Date.now();
                          if (timeLeft <= 0) return clearInterval(interval);
                          var particleCount = 50 * (timeLeft / duration);
                          confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
                          confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
                        }, 250);
                        
                        setTimeout(() => { location.reload(); }, 3200);
                    } else {
                        location.reload();
                    }
                } else {
                    alert(res.error || 'Error updating item');
                }
            }, 'json');
        }

        function deleteOkrItem(id, type) {
            if (!confirm("Bạn có chắc chắn muốn xóa mục này? Toàn bộ lịch sử giải trình cũng sẽ bị xóa.")) return;
            
            $.post('/modules/okr/index.php', {
                action: 'delete_okr_item',
                id: id,
                type: type
            }, function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert('Error deleting item');
                }
            }, 'json');
        }

        function deleteObjective(id) {
            if (!confirm("CẢNH BÁO: Bạn có chắc chắn muốn xóa Mục tiêu này? \n\nTất cả Key Activities, Key Results và lịch sử giải trình liên quan cũng sẽ bị xóa vĩnh viễn.")) return;
            
            $.post('/modules/okr/index.php', {
                action: 'delete_objective',
                id: id
            }, function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert('Error deleting objective');
                }
            }, 'json');
        }

        /* ADD MODAL */
        function openAddModal(obj_id, type, result_id = 0) {
            document.getElementById('addModalObjId').value = obj_id;
            document.getElementById('addModalType').value = type;
            document.getElementById('addModalName').value = '';
            document.getElementById('addModalOwner').value = '';

                const resultControl = document.getElementById('addModalParentResultControl');
                const resultSelect = document.getElementById('addModalResult');
                const activityControl = document.getElementById('addModalActivityControl');
                const activitySelect = document.getElementById('addModalActivity');

                if (type === 'activity') {
                    document.getElementById('addModalTitle').innerText = 'Add Key Activity';
                    document.getElementById('addModalNameLbl').innerText = 'Activity Description';
                    document.getElementById('addModalOwner2Control').style.display = 'none';
                    activityControl.style.display = 'none';
                    resultControl.style.display = 'block';
                    resultSelect.innerHTML = '<option value="0">-- Không liên kết --</option>';
                    
                    const objCard = document.querySelector(`.okr-card-header h3[data-id="${obj_id}"]`)?.closest('.okr-card') || document.body;
                    const krRows = objCard.querySelectorAll('table tr.activity-header');
                    krRows.forEach(row => {
                       const editBtn = row.querySelector('.btn-edit-row');
                       if(!editBtn) return;
                       const matches = editBtn.getAttribute('onclick').match(/metric',\s*(\d+)/);
                       if(!matches) return;
                       const r_id = matches[1];
                       const r_name = row.querySelector('.item-main-title').innerText.replace(/^KR \d+/, '').trim();
                       const opt = document.createElement('option');
                       opt.value = r_id;
                       opt.innerText = r_name;
                       if (parseInt(r_id) === parseInt(result_id)) opt.selected = true;
                       resultSelect.appendChild(opt);
                    });
                } else {
                    document.getElementById('addModalTitle').innerText = 'Add Target Result (Metric)';
                    document.getElementById('addModalNameLbl').innerText = 'Metric Description';
                    document.getElementById('addModalOwner2Control').style.display = 'block';
                    document.getElementById('addModalOwner2').value = '0';
                    resultControl.style.display = 'none';
                    activityControl.style.display = 'block';
                    activitySelect.innerHTML = '<option value="0">-- Không liên kết --</option>';
                    const objCard = document.querySelector(`.okr-card-header h3[data-id="${obj_id}"]`)?.closest('.okr-card') || document.body;
                    const unlinkedKAs = objCard.querySelectorAll('tr:not(.activity-header):not(.row-kr-sub) .item-main-title');
                    unlinkedKAs.forEach(title => {
                        const row = title.closest('tr');
                        const editBtn = row.querySelector('.btn-edit-row');
                        if (!editBtn) return;
                        const matches = editBtn.getAttribute('onclick').match(/activity',\s*(\d+)/);
                        if (!matches) return;
                        const a_id = matches[1];
                        const opt = document.createElement('option');
                        opt.value = a_id;
                        opt.innerText = title.innerText.trim();
                        activitySelect.appendChild(opt);
                    });
                }

            let modal = document.getElementById('addModalOverlay');
            modal.style.display = 'flex';
            setTimeout(function() { document.getElementById('addModalContent').classList.add('active'); }, 10);
        }

        function closeAddModal() {
            let modalContent = document.getElementById('addModalContent');
            modalContent.classList.remove('active');
            setTimeout(function() { document.getElementById('addModalOverlay').style.display = 'none'; }, 200);
        }

        function saveAddModal(btn) {
            const oid = document.getElementById('addModalObjId').value;
            const type = document.getElementById('addModalType').value;
            const name = document.getElementById('addModalName').value;
            
            const ownerSelect = document.getElementById('addModalOwner');
            const owner_id = ownerSelect.value;
            const selectedOpt = ownerSelect.options[ownerSelect.selectedIndex];
            const owner_name = selectedOpt ? (selectedOpt.getAttribute('data-name') || '') : '';

            const owner2Select = document.getElementById('addModalOwner2');
            const owner_2_id = owner2Select.value;
            const selectedOpt2 = owner2Select.options[owner2Select.selectedIndex];
            const owner_2_name = selectedOpt2 ? (selectedOpt2.getAttribute('data-name') || '') : '';

            const priority = document.getElementById('addItemPriority').value;
            const weight = document.getElementById('addItemWeight').value;
            const sort_order = document.getElementById('addItemSort').value;
            const result_id = (type === 'activity') ? document.getElementById('addModalResult').value : 0;
            const activity_id = (type === 'metric') ? document.getElementById('addModalActivity').value : 0;

            if (!name) { alert("Vui lòng nhập tên công việc/chỉ số!"); return; }

            document.getElementById('addLoadingSpinner').style.display = 'inline-block';

            $.post('/modules/okr/index.php', {
                action: 'add_okr_item',
                obj_id: oid,
                type: type,
                name: name,
                owner_id: owner_id,
                owner_name: owner_name,
                owner_2_id: owner_2_id,
                owner_2_name: owner_2_name,
                priority: priority,
                weight: weight,
                sort_order: sort_order,
                parent_id: result_id,
                activity_id: activity_id
            }, function(res) {
                document.getElementById('addLoadingSpinner').style.display = 'none';
                if(res.success) {
                    closeAddModal();
                    window.location.reload(); 
                } else {
                    alert(res.error || 'Error adding data');
                }
            }, 'json');
        }

        /* CREATE OBJECTIVE MODAL */
        function openCreateObjModal() {
            // Re-use update modal but clear it and change behavior
            document.getElementById('objModalId').value = '';
            document.getElementById('objModalTitle').value = '';
            
            // Default based on current view
            if (<?php echo $is_team_view ? 'true' : 'false'; ?>) {
               // Tìm option team tương ứng và chọn
               const teamName = '<?php echo addslashes($current_team_tab); ?>';
               $('#objModalOwner option[data-type="team"][data-name="'+teamName+'"]').prop('selected', true);
            } else {
               document.getElementById('objModalOwner').value = '<?php echo $_SESSION['user_id'] ?? 0; ?>';
            }
            document.getElementById('objModalStatus').value = 'on_track';
            document.getElementById('objModalSortOrder').value = '0';
            document.getElementById('objModalQuarter').value = '<?php echo $current_quarter; ?>';
            document.getElementById('objModalYear').value = '<?php echo $current_year; ?>';
            
            document.querySelector('#objModalOverlay .modal-title').innerText = 'Create New Objective';
            document.getElementById('objModalBtnText').innerText = 'Create Objective';
            document.querySelector('#objModalOverlay .btn-apple').setAttribute('onclick', 'saveCreateObjModal(this)');

            let modal = document.getElementById('objModalOverlay');
            modal.style.display = 'flex';
            setTimeout(function() { document.getElementById('objModalContent').classList.add('active'); }, 10);
        }

        function saveCreateObjModal(btn) {
            const title = document.getElementById('objModalTitle').value;
            const ownerSelect = document.getElementById('objModalOwner');
            const selectedOpt = ownerSelect.options[ownerSelect.selectedIndex];
            
            const owner_id = ownerSelect.value;
            const owner_name = selectedOpt.getAttribute('data-name') || '';
            let team = selectedOpt.getAttribute('data-team') || '<?php echo ($current_team_tab === "all") ? "" : addslashes($current_team_tab); ?>';
            
            // Nếu là Dashboard và chọn User, cố gắng gán team "Default" hoặc để trống
            if (!team && '<?php echo $current_team_tab; ?>' === 'all') team = 'General';

            const status = document.getElementById('objModalStatus').value;
            const sort_order = document.getElementById('objModalSortOrder').value;
            const quarter = document.getElementById('objModalQuarter').value;
            const year = document.getElementById('objModalYear').value;

            if (!title) { alert("Vui lòng nhập tên Mục tiêu!"); return; }

            document.getElementById('objLoadingSpinner').style.display = 'inline-block';

            $.post('/modules/okr/index.php', {
                action: 'create_objective',
                title: title,
                team: team,
                owner_id: owner_id,
                owner_name: owner_name,
                status: status,
                sort_order: sort_order,
                quarter: quarter,
                year: year
            }, function(res) {
                document.getElementById('objLoadingSpinner').style.display = 'none';
                if(res && res.success) {
                    window.location.reload(); 
                } else {
                    alert('Error creating objective');
                }
            }, 'json');
        }

        function openObjectiveModal(id, title, owner, status, sort_order, quarter, year, owner_id, team) {
            document.getElementById('objModalId').value = id;
            document.getElementById('objModalTitle').value = title;
            // Chọn owner đúng (Team hoặc User)
            if (owner_id == 0) {
                $('#objModalOwner option[data-type="team"][data-team="'+team+'"]').prop('selected', true);
            } else {
                document.getElementById('objModalOwner').value = owner_id;
            }
            document.getElementById('objModalStatus').value = status;
            document.getElementById('objModalSortOrder').value = sort_order || 0;
            document.getElementById('objModalQuarter').value = quarter || 1;
            document.getElementById('objModalYear').value = year || 2026;
            
            document.querySelector('#objModalOverlay .modal-title').innerText = 'Update Objective';
            document.getElementById('objModalBtnText').innerText = 'Save Changes';
            document.querySelector('#objModalOverlay .btn-apple').setAttribute('onclick', 'saveObjectiveModal(this)');

            let modal = document.getElementById('objModalOverlay');
            modal.style.display = 'flex';
            setTimeout(function() { document.getElementById('objModalContent').classList.add('active'); }, 10);
        }

        function closeObjectiveModal() {
            let modalContent = document.getElementById('objModalContent');
            modalContent.classList.remove('active');
            setTimeout(function() { document.getElementById('objModalOverlay').style.display = 'none'; }, 200);
        }

        function saveObjectiveModal(btn) {
            const id = document.getElementById('objModalId').value;
            const title = document.getElementById('objModalTitle').value;
            const ownerSelect = document.getElementById('objModalOwner');
            const selectedOpt = ownerSelect.options[ownerSelect.selectedIndex];
            
            const owner_id = ownerSelect.value;
            const owner_name = selectedOpt.getAttribute('data-name') || '';
            let team = selectedOpt.getAttribute('data-team') || '<?php echo ($current_team_tab === "all") ? "" : addslashes($current_team_tab); ?>';
            
            if (!team && '<?php echo $current_team_tab; ?>' === 'all') team = 'General';

            const status = document.getElementById('objModalStatus').value;
            const sort_order = document.getElementById('objModalSortOrder').value;
            const quarter = document.getElementById('objModalQuarter').value;
            const year = document.getElementById('objModalYear').value;

            if (!title) { alert("Vui lòng nhập tên Mục tiêu!"); return; }

            document.getElementById('objLoadingSpinner').style.display = 'inline-block';

            $.post('/modules/okr/index.php', {
                action: 'update_objective',
                id: id,
                title: title,
                team: team,
                owner_id: owner_id,
                owner_name: owner_name,
                status: status,
                sort_order: sort_order,
                quarter: quarter,
                year: year
            }, function(res) {
                document.getElementById('objLoadingSpinner').style.display = 'none';
                if(res && res.success) {
                    closeObjectiveModal();
                    window.location.reload(); 
                } else {
                    alert('Error updating objective data');
                }
            }, 'json').fail(function() {
                alert('Có lỗi kết nối đến CSDL. Hãy thử F5 nhé!');
                document.getElementById('objLoadingSpinner').style.display = 'none';
            });
        }
        $(document).on('click', '.btn-edit-objective', function() {
            const id = $(this).data('id');
            const title = $(this).data('title');
            const owner = $(this).data('owner');
            const owner_id = $(this).data('owner-id');
            const team = $(this).data('team');
            const status = $(this).data('status');
            const sort_order = $(this).data('sort-order');
            const quarter = $(this).data('quarter');
            const year = $(this).data('year');
            
             openObjectiveModal(id, title, owner, status, sort_order, quarter, year, owner_id, team);
        });

        function openVisibilityModal() {
            $('#visibilityModalOverlay').fadeIn(200);
            $('#visibilityModalContent').addClass('active');
        }
        function closeVisibilityModal() {
            $('#visibilityModalOverlay').fadeOut(200);
            $('#visibilityModalContent').removeClass('active');
        }
        
        function openOkrSettingsModal() {
            $('#okrSettingsModalOverlay').fadeIn(200);
            $('#okrSettingsModalContent').addClass('active');
        }
        function closeOkrSettingsModal() {
            $('#okrSettingsModalOverlay').fadeOut(200);
            $('#okrSettingsModalContent').removeClass('active');
        }
        function saveOkrSettings(btn) {
            const high = $('#cfg_color_high').val();
            const mid = $('#cfg_color_mid').val();
            const low = $('#cfg_color_low').val();
            const t_high = $('#cfg_text_high').val();
            const t_mid = $('#cfg_text_mid').val();
            const t_low = $('#cfg_text_low').val();

            const kr_high = $('#cfg_kr_color_high').val();
            const kr_mid = $('#cfg_kr_color_mid').val();
            const kr_low = $('#cfg_kr_color_low').val();
            const kr_t_high = $('#cfg_kr_text_high').val();
            const kr_t_mid = $('#cfg_kr_text_mid').val();
            const kr_t_low = $('#cfg_kr_text_low').val();

            const obj_high = $('#cfg_obj_color_high').val();
            const obj_mid = $('#cfg_obj_color_mid').val();
            const obj_low = $('#cfg_obj_color_low').val();
            const obj_t_high = $('#cfg_obj_text_high').val();
            const obj_t_mid = $('#cfg_obj_text_mid').val();
            const obj_t_low = $('#cfg_obj_text_low').val();
            
            $(btn).prop('disabled', true);
            $('#okrSettingsLoadingSpinner').show();
            
            $.post(window.location.href, {
                action: 'save_okr_settings',
                settings: {
                    color_high: high,
                    color_mid: mid,
                    color_low: low,
                    text_high: t_high,
                    text_mid: t_mid,
                    text_low: t_low,
                    kr_color_high: kr_high,
                    kr_color_mid: kr_mid,
                    kr_color_low: kr_low,
                    kr_text_high: kr_t_high,
                    kr_text_mid: kr_t_mid,
                    kr_text_low: kr_t_low,
                    obj_color_high: obj_high,
                    obj_color_mid: obj_mid,
                    obj_color_low: obj_low,
                    obj_text_high: obj_t_high,
                    obj_text_mid: obj_t_mid,
                    obj_text_low: obj_t_low
                }
            }, function(res) {
                $(btn).prop('disabled', false);
                $('#okrSettingsLoadingSpinner').hide();
                if(res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: 'Màu nền tiến độ đã được cập nhật.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Lỗi', 'Không thể lưu cài đặt.', 'error');
                }
            }, 'json');
        }

        function saveVisibilitySettings(btn) {
            const hidden_users = [];
            $('.vis-check:checked').each(function() {
                hidden_users.push($(this).val());
            });

            document.getElementById('visLoadingSpinner').style.display = 'inline-block';

            $.post('/modules/okr/index.php', {
                action: 'save_user_visibility',
                hidden_users: hidden_users
            }, function(res) {
                document.getElementById('visLoadingSpinner').style.display = 'none';
                if(res && res.success) {
                    location.reload();
                } else {
                    alert('Error saving visibility settings');
                }
            }, 'json');
        }

        function viewAnnualOKR(userId, userName, year) {
            document.getElementById('annualDrawerTitle').innerText = 'Annual OKR - ' + userName;
            document.getElementById('annualDrawerSubtitle').innerText = 'Performance Review for Year ' + year;
            document.getElementById('annualOKRContent').innerHTML = '<div style="text-align:center; padding:50px;"><i class="fas fa-spinner fa-spin" style="font-size:30px; color:#0071e3;"></i><p style="margin-top:10px; color:#86868b;">Đang tải dữ liệu cả năm...</p></div>';
            
            let modal = document.getElementById('annualDrawerOverlay');
            modal.style.display = 'flex';
            setTimeout(function() { document.getElementById('annualDrawerContent').classList.add('active'); }, 10);

            $.post('/modules/okr/index.php', {
                action: 'fetch_annual_okrs',
                user_id: userId,
                user_name: userName,
                year: year
            }, function(res) {
                if(res && res.success) {
                    document.getElementById('annualOKRContent').innerHTML = res.html;
                } else {
                    document.getElementById('annualOKRContent').innerHTML = '<p style="color:red; text-align:center;">Lỗi khi tải dữ liệu.</p>';
                }
            }, 'json');
        }

        function closeAnnualDrawer() {
            let modalContent = document.getElementById('annualDrawerContent');
            modalContent.classList.remove('active');
            setTimeout(function() { document.getElementById('annualDrawerOverlay').style.display = 'none'; }, 200);
        }

        <?php if ($current_team_tab === 'all'): ?>
        // Dashboard Charts
        const ctxTeam = document.getElementById('teamProgressChart');
        if (ctxTeam) {
            new Chart(ctxTeam, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Avg Progress',
                        data: <?php echo json_encode($chart_data); ?>,
                        backgroundColor: '#0071e3',
                        borderRadius: 6,
                        barThickness: 24
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, max: 100, ticks: { font: { size: 10 } } },
                        x: { ticks: { font: { size: 10 } } }
                    }
                }
            });
        }

        const ctxStatus = document.getElementById('statusDistChart');
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Done', 'On Track', 'In Progress', 'At Risk', 'Delayed', 'Pending'],
                    datasets: [{
                        data: [
                            <?php echo $status_dist['completed']; ?>,
                            <?php echo $status_dist['on_track']; ?>,
                            <?php echo $status_dist['in_progress']; ?>,
                            <?php echo $status_dist['at_risk']; ?>,
                            <?php echo $status_dist['delayed']; ?>,
                            <?php echo $status_dist['pending']; ?>
                        ],
                        backgroundColor: ['#10b981', '#6366f1', '#0ea5e9', '#ef4444', '#f59e0b', '#94a3b8'],
                        borderWidth: 0,
                        cutout: '70%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
    <!-- OKR Team Management Modal -->
    <div id="okrTeamManagementModal" class="apple-modal-overlay" onclick="if(event.target===this) closeOkrTeamManagementModal()">
        <div class="apple-modal">
            <div class="modal-body" style="padding-top:20px;">
                <h3 class="modal-title">OKR Teams Management</h3>
                <p class="modal-subtitle">Create teams and assign members specifically for OKRs.</p>
                
                <div class="modal-control">
                    <label>Add New Team</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="new_team_name" placeholder="Enter team name..." style="flex:1;">
                        <button class="btn-apple" onclick="saveNewOkrTeam()" style="padding:10px 20px;">Add</button>
                    </div>
                </div>

                <div id="okrTeamsListContainer" style="margin-top:24px;">
                    <!-- Teams will be loaded here -->
                </div>
            </div>
            <div class="modal-actions" style="background:#fff;">
                <button class="btn-secondary" onclick="closeOkrTeamManagementModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
    function openOkrTeamManagementModal() {
        $('#okrTeamManagementModal').fadeIn(200).css('display','flex').find('.apple-modal').addClass('active');
        loadOkrTeams();
    }
    function closeOkrTeamManagementModal() {
        $('#okrTeamManagementModal').find('.apple-modal').removeClass('active');
        setTimeout(() => $('#okrTeamManagementModal').fadeOut(200), 400);
    }
    function loadOkrTeams() {
        $('#okrTeamsListContainer').html('<p style="text-align:center; color:#86868b; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading teams...</p>');
        $.post(window.location.href, { action: 'fetch_okr_teams_data' }, function(res) {
            if(res.success) {
                let html = '';
                if(res.teams.length === 0) {
                    html = '<div style="text-align:center; padding:40px; color:#86868b; border:1px dashed #d2d2d7; border-radius:12px;">No teams created yet.</div>';
                } else {
                    res.teams.forEach(t => {
                        html += `
                        <div class="team-mgmt-item" style="background:#fbfbfd; border:1px solid #e5e5ea; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <strong style="font-size:15px; color:#1d1d1f;">${t.team_name}</strong>
                                <button class="btn-delete-row" style="opacity:1;" onclick="deleteOkrTeam(${t.id})"><i class="fas fa-trash-alt"></i></button>
                            </div>
                            <div class="modal-control" style="margin-bottom:0;">
                                <label style="font-size:11px;">Assign Members</label>
                                <select class="team-members-select" multiple data-team-id="${t.id}" style="height:120px; font-size:13px; background:#fff;">
                                    <?php foreach($all_users_for_mgmt as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn-apple" style="width:100%; margin-top:8px; font-size:12px; padding:8px;" onclick="saveTeamMembers(this, ${t.id})">Update Members</button>
                            </div>
                        </div>`;
                    });
                }
                $('#okrTeamsListContainer').html(html);
                
                // Set selections
                res.teams.forEach(t => {
                    let select = $(`.team-members-select[data-team-id="${t.id}"]`);
                    select.val(t.members);
                });
            }
        }, 'json');
    }
    function saveNewOkrTeam() {
        let name = $('#new_team_name').val().trim();
        if(!name) return;
        $.post(window.location.href, { action: 'save_okr_new_team', name: name }, function(res) {
            if(res.success) {
                $('#new_team_name').val('');
                loadOkrTeams();
                Swal.fire({ icon: 'success', title: 'Team added!', timer: 1000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể thêm team' });
            }
        }, 'json');
    }
    function deleteOkrTeam(id) {
        Swal.fire({
            title: 'Delete this team?',
            text: "Objectives associated with this team name will still exist but the tab will be gone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(window.location.href, { action: 'delete_okr_team_data', id: id }, function(res) {
                    if(res.success) {
                        loadOkrTeams();
                    }
                }, 'json');
            }
        });
    }
    function saveTeamMembers(btn, teamId) {
        let select = $(btn).siblings('.team-members-select');
        let userIds = select.val();
        $(btn).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $.post(window.location.href, { action: 'update_okr_team_members', team_id: teamId, user_ids: userIds }, function(res) {
            $(btn).prop('disabled', false).html('Update Members');
            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Members updated!', timer: 800, showConfirmButton: false });
            }
        }, 'json');
    }
    </script>
</body>
</html>
