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

// Synchronize Structural Requirements (Migration Block)
try {
    // Objectives Table Enhancements
    addColIfNotExists($conn, 'okr_objectives', 'team', 'VARCHAR(100) DEFAULT "Sales & Marketing"');
    addColIfNotExists($conn, 'okr_objectives', 'owner', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_objectives', 'owner_id', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_objectives', 'progress', 'DECIMAL(5,2) DEFAULT 0');
    addColIfNotExists($conn, 'okr_objectives', 'status', 'VARCHAR(100) DEFAULT "on_track"');
    addColIfNotExists($conn, 'okr_objectives', 'quarter', 'INT DEFAULT 1');
    addColIfNotExists($conn, 'okr_objectives', 'year', 'INT DEFAULT 2026');
    addColIfNotExists($conn, 'okr_objectives', 'cycle_id', 'INT DEFAULT 1');
    addColIfNotExists($conn, 'okr_objectives', 'sort_order', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_objectives', 'is_company_okr', 'TINYINT DEFAULT 0');
    
    // Key Results Table Enhancements
    addColIfNotExists($conn, 'okr_results', 'unit', 'VARCHAR(50) DEFAULT "%"');
    addColIfNotExists($conn, 'okr_results', 'status', 'VARCHAR(50) DEFAULT "pending"');
    addColIfNotExists($conn, 'okr_results', 'priority', 'VARCHAR(20) DEFAULT "medium"');
    addColIfNotExists($conn, 'okr_results', 'weight', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_results', 'owner_name', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_results', 'owner_avatar', 'VARCHAR(10)');
    
    // Key Activities Table Enhancements
    addColIfNotExists($conn, 'okr_key_activities', 'status', 'VARCHAR(50) DEFAULT "pending"');
    addColIfNotExists($conn, 'okr_key_activities', 'priority', 'VARCHAR(20) DEFAULT "medium"');
    addColIfNotExists($conn, 'okr_key_activities', 'weight', 'INT DEFAULT 0');
    addColIfNotExists($conn, 'okr_key_activities', 'owner_name', 'VARCHAR(255)');
    addColIfNotExists($conn, 'okr_key_activities', 'owner_avatar', 'VARCHAR(10)');

    // Expand numeric columns to support large values (e.g. billions/trillions in revenue targets)
    @$conn->query("ALTER TABLE `okr_results` MODIFY COLUMN `target_value` DECIMAL(20,2) DEFAULT 0");
    @$conn->query("ALTER TABLE `okr_results` MODIFY COLUMN `current_value` DECIMAL(20,2) DEFAULT 0");
} catch (Throwable $e) { /* Resilient against schema locks */ }

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

// Global Variable for Hide Flag
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
        $val = floatval($_POST['val']);
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $weight = intval($_POST['weight']);
        $explanation = trim($_POST['explanation'] ?? '');
        
        // Find Objective ID
        $oid = 0;
        if($type === 'metric') {
            $check = $conn->query("SELECT objective_id FROM okr_results WHERE id = $id");
            if($c = $check->fetch_assoc()) $oid = $c['objective_id'];
        } else {
            $check = $conn->query("SELECT objective_id FROM okr_key_activities WHERE id = $id");
            if($c = $check->fetch_assoc()) $oid = $c['objective_id'];
        }

        // Weight check
        $table = ($type === 'metric') ? 'okr_results' : 'okr_key_activities';
        $sum_res = $conn->query("SELECT SUM(weight) as total FROM $table WHERE objective_id = $oid AND id != $id");
        $sum = $sum_res->fetch_assoc()['total'] ?? 0;
        if (($sum + $weight) > 100) {
            echo json_encode(['success' => false, 'error' => 'Tổng tỉ trọng không được quá 100% (Hiện tại: '.$sum.'%)']);
            exit();
        }

        $name = trim($_POST['name'] ?? '');
        $owner_name = trim($_POST['owner'] ?? '');
        
        // Recalculate avatar initials for the new owner
        $avatar = '';
        if(!empty($owner_name)) {
            $parts = explode(' ', $owner_name);
            $avatar = mb_substr(end($parts), 0, 1, "UTF-8");
        }

        if ($type === 'metric') {
            // val is now progress % — compute current_value proportionally, keep target_value intact
            $stmt = $conn->prepare("UPDATE okr_results SET metric_name = ?, current_value = ROUND((? / 100.0) * target_value, 2), status = ?, priority = ?, weight = ?, owner_name = ?, owner_avatar = ? WHERE id = ?");
            $stmt->bind_param("sdssissi", $name, $val, $status, $priority, $weight, $owner_name, $avatar, $id);
        } else {
            $stmt = $conn->prepare("UPDATE okr_key_activities SET activity_name = ?, progress = ?, status = ?, priority = ?, weight = ?, owner_name = ?, owner_avatar = ? WHERE id = ?");
            $stmt->bind_param("sdssissi", $name, $val, $status, $priority, $weight, $owner_name, $avatar, $id);
        }
        $stmt->execute();
        
        // Save explanation only if it has real content (not just empty Quill tags)
        $clean_explanation = trim(strip_tags($explanation));
        if ($explanation && !empty($clean_explanation)) {
            $u_id = $_SESSION['user_id'] ?? 0;
            $u_name = $_SESSION['user_full_name'] ?? ($_SESSION['name'] ?? 'System');
            $stmt_exp = $conn->prepare("INSERT INTO okr_explanations (item_id, item_type, content, user_id, user_name) VALUES (?, ?, ?, ?, ?)");
            $stmt_exp->bind_param("issis", $id, $type, $explanation, $u_id, $u_name);
            $stmt_exp->execute();
        }
        
        // Auto Update Objective Progress based on Key Results (Metric/KR)
        if ($type === 'metric') {
            $obj_id_q = $conn->query("SELECT objective_id FROM okr_results WHERE id = $id");
            if ($obj_id_row = $obj_id_q->fetch_assoc()) {
                $oid = $obj_id_row['objective_id'];
                // Use simple average of progress % (Current / Target * 100)
                // Weighted Progress Calculation:
                // If sum of weights > 0, use weighted average. Otherwise use simple avg.
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
        
        // Recalculate Objective Progress if a KR was deleted
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
        $history = [];
        $res = $conn->query("SELECT * FROM okr_explanations WHERE item_id = $id AND item_type = '$type' ORDER BY created_at DESC");
        if ($res) {
            while($row = $res->fetch_assoc()) {
                $row['formatted_date'] = date('d/m/Y H:i', strtotime($row['created_at']));
                $history[] = $row;
            }
        }
        echo json_encode(['success' => true, 'history' => $history]);
        exit();
    }

    if ($_POST['action'] === 'add_okr_item') {
        $oid = intval($_POST['obj_id']);
        $type = $_POST['type']; // 'metric' or 'activity'
        $name = trim($_POST['name']);
        $owner_name = trim($_POST['owner_name']);
        if (!$owner_name) $owner_name = 'User';
        $owner_avatar = strtoupper(substr($owner_name, 0, 2));

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

        if ($type === 'metric') {
            $target = floatval($_POST['target']);
            $unit = $_POST['unit'] ?? '';
            $stmt = $conn->prepare("INSERT INTO okr_results (objective_id, metric_name, target_value, unit, owner_name, owner_avatar, priority, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdssssi", $oid, $name, $target, $unit, $owner_name, $owner_avatar, $priority, $weight);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO okr_key_activities (objective_id, activity_name, owner_name, owner_avatar, priority, weight) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $oid, $name, $owner_name, $owner_avatar, $priority, $weight);
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
}

// Fetch Users for Dropdown (Focusing on active participants)
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
$sale_teams_list = [];
try {
    $teams_res = $conn->query("SELECT name FROM sale_teams ORDER BY order_num ASC");
    if ($teams_res && $teams_res->num_rows > 0) {
        while($t = $teams_res->fetch_assoc()) {
            if(!empty($t['name'])) $sale_teams_list[] = $t['name'];
        }
    }
} catch (Exception $e) {}
if (empty($sale_teams_list)) {
    $sale_teams_list = ['Team BD/AM', 'Sales & Marketing']; // Fallbacks
}

// Fetch Data for Render
$objectives = [];
$current_team_tab = $_GET['team'] ?? null;
$selected_user = $_GET['user'] ?? null;

// Find Current User's Teams
$my_teams = [];
$uid = $_SESSION['user_id'];
$ut_res = $conn->query("SELECT st.name FROM user_sale_teams ust JOIN sale_teams st ON ust.team_id = st.id WHERE ust.user_id = $uid");
if ($ut_res) {
    while($ut = $ut_res->fetch_assoc()) $my_teams[] = $ut['name'];
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
    // Exclude users with 'admin' role and hidden users from members list
    $members_res = $conn->query("
        SELECT DISTINCT u.id, u.full_name 
        FROM users u 
        JOIN user_sale_teams ust ON u.id = ust.user_id 
        JOIN sale_teams st ON ust.team_id = st.id 
        WHERE st.name = '$safe_team' AND u.role != 'admin' AND u.hide_from_okr = 0
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

// Fetch Objectives with Owner's Team info
$sql_o = "SELECT o.*, 
    (SELECT st.name FROM sale_teams st 
     JOIN user_sale_teams ust ON st.id = ust.team_id 
     JOIN users u ON ust.user_id = u.id 
     WHERE u.full_name = o.owner LIMIT 1) as owner_team_name
    FROM okr_objectives o" . $team_filter . " ORDER BY sort_order ASC, id DESC";

$res_o = $conn->query($sql_o);
while ($o = $res_o->fetch_assoc()) {
    $o['results'] = [];
    $o['activities'] = [];
    
    $r_res = $conn->query("SELECT r.*, (SELECT content FROM okr_explanations WHERE item_id = r.id AND item_type = 'metric' ORDER BY created_at DESC LIMIT 1) as latest_explanation FROM okr_results r WHERE r.objective_id = " . $o['id'] . " ORDER BY FIELD(priority, 'high', 'medium', 'low') ASC, id DESC");
    while($r = $r_res->fetch_assoc()) $o['results'][] = $r;
    
    $a_res = $conn->query("SELECT a.*, (SELECT content FROM okr_explanations WHERE item_id = a.id AND item_type = 'activity' ORDER BY created_at DESC LIMIT 1) as latest_explanation FROM okr_key_activities a WHERE a.objective_id = " . $o['id'] . " ORDER BY FIELD(priority, 'high', 'medium', 'low') ASC, id DESC");
    while($a = $a_res->fetch_assoc()) $o['activities'][] = $a;
    
    $objectives[] = $o;
}

// Group objectives by team
$grouped_objectives = [];
foreach ($objectives as $obj) {
    // Priority: Owner's actual team from DB > Explicit team column > Fallback
    $t = $obj['owner_team_name'] ?: ($obj['team'] ?: 'Unassigned Team');
    $grouped_objectives[$t][] = $obj;
}

// Helper to get Status Badge Style
function getBadgeHtml($status) {
    if ($status === 'on_track' || $status === 'in_progress' || $status === 'completed') {
        $lbl = str_replace('_', ' ', ucwords($status));
        return '<span class="status-badge st-ontrack">'.$lbl.'</span>';
    }
    if ($status === 'at_risk' || $status === 'delayed') {
        $lbl = str_replace('_', ' ', ucwords($status));
        return '<span class="status-badge st-atrisk">'.$lbl.'</span>';
    }
    return '<span class="status-badge st-pending">Pending</span>';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .btn-apple { background-color: #0071e3; color: white; border-radius: 980px; padding: 10px 20px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-apple:hover { background-color: #0077ed; }
        .btn-secondary { background-color: #f5f5f7; color: #1d1d1f; border-radius: 980px; padding: 8px 16px; font-size: 13px; font-weight: 600; border: 1px solid #d2d2d7; cursor: pointer; transition: all 0.2s; }
        .btn-secondary:hover { background-color: #e5e5ea; }

        .okr-tabs { margin-bottom: 32px; display: flex; gap: 8px; border-bottom: 1px solid #e5e5ea; padding-bottom: 16px; }
        .tab-item { padding: 8px 16px; border-radius: 980px; background: transparent; color: #515154; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: 1px solid transparent; }
        .tab-item:hover { background: #e5e5ea; color: #1d1d1f; }
        .tab-item.active { background: #1d1d1f; color: #ffffff; }

        .member-ribbon { display: flex; gap: 12px; margin-bottom: 32px; flex-wrap: wrap; align-items: center; padding: 4px; }
        .member-item { display: flex; align-items: center; gap: 10px; padding: 6px 16px; background: #ffffff; border: 1px solid #e5e5ea; border-radius: 980px; text-decoration: none; color: #515154; font-size: 13px; font-weight: 500; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .member-item:hover { background: #f5f5f7; border-color: #d2d2d7; transform: translateY(-1px); }
        .member-item.active { background: #ffffff; color: #0071e3; border-color: #0071e3; box-shadow: 0 4px 12px rgba(0,113,227,0.12); }
        .member-avatar { width: 22px; height: 22px; border-radius: 50%; background: #f5f5f7; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: #515154; border: 1px solid #e5e5ea; }
        .member-item.active .member-avatar { background: #0071e3; color: #ffffff; border-color: #0071e3; }
        .btn-view-annual { margin-left: 6px; padding: 4px; font-size: 11px; color: #86868b; cursor: pointer; border: none; background: transparent; transition: all 0.2s; border-radius: 4px; display: flex; align-items: center; justify-content: center; }
        .btn-view-annual:hover { color: #0071e3; background: #f2f2f7; }
        .member-item.active .btn-view-annual { color: #0071e3; opacity: 0.8; }
        .member-item.active .btn-view-annual:hover { opacity: 1; background: #ffffff; }

        .time-filter-group { display: flex; gap: 8px; background: #f5f5f7; padding: 4px; border-radius: 980px; align-items: center; border: 1px solid #e5e5ea; }
        .time-pill { padding: 4px 12px; border-radius: 980px; font-size: 12px; font-weight: 600; text-decoration: none; color: #86868b; transition: all 0.2s; }
        .time-pill:hover { background: #e5e5ea; color: #1d1d1f; }
        .time-pill.active { background: #ffffff; color: #1d1d1f; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .year-select { background: transparent; border: none; font-size: 12px; font-weight: 700; color: #1d1d1f; cursor: pointer; outline: none; padding: 0 8px; }
        .okr-card { background: #ffffff; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.04); border: 1px solid #f2f2f7; margin-bottom: 40px; overflow: hidden; padding: 12px; }
        .okr-card-header { padding: 16px 24px; background: #f5f5f7; border-radius: 14px; border-bottom: none; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .obj-left { display: flex; align-items: center; gap: 20px; flex: 1; }
        .obj-title-group { display: flex; flex-direction: column; gap: 2px; }
        .obj-left h3 { font-size: 19px; font-weight: 700; color: #1d1d1f; margin: 0; display: flex; align-items: center; gap: 10px; letter-spacing: -0.015em; }
        .obj-meta-row { display: flex; gap: 20px; align-items: center; color: #86868b; font-size: 13px; }
        .meta-divider { height: 16px; width: 1px; background: #d2d2d7; }
        
        .okr-body-split { display: flex; background: transparent; gap: 12px; }
        .okr-col { flex: 1; background: #ffffff; border-radius: 0; border: none; }
        
        .section-label { padding: 12px 16px; background: #fbfbfd; border-radius: 10px; font-size: 11px; font-weight: 700; color: #86868b; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; border: 1px solid #f2f2f7; }
        .section-label i { color: #0071e3; font-size: 12px; }
        .section-label i { color: #6366f1; font-size: 13px; }
        .okr-table { width: 100%; border-collapse: collapse; }
        .okr-table td { padding: 10px 16px; font-size: 12px; border: none; border-bottom: 1px solid #f8fafc; color: #334155; }
        .okr-table tr:last-child td { border-bottom: none; }
        .okr-table tr:hover td { background-color: #fcfdfe; }
        .row-prio-high td { background-color: #fff1f2 !important; }
        .row-prio-high:hover td { background-color: #ffe4e6 !important; }
        .row-prio-medium td { background-color: #f0f9ff !important; }
        .row-prio-medium:hover td { background-color: #e0f2fe !important; }
        .row-prio-low td { background-color: #f8fafc !important; }
        .row-prio-low:hover td { background-color: #f1f5f9 !important; }
        
        /* Celebration Animation for Completed Items */
        @keyframes celebrate-bg {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes celebrate-particles {
            0% { background-position: 0 0, 15px 15px; opacity: 0; }
            50% { opacity: 0.3; }
            100% { background-position: 100px 100px, 120px 120px; opacity: 0; }
        }
        .row-completed {
            background: linear-gradient(-45deg, #f0fdf4, #dcfce7, #fdf4ff, #ecfdf5) !important;
            background-size: 400% 400% !important;
            animation: celebrate-bg 5s ease infinite !important;
            position: relative;
            z-index: 1;
        }
        .row-completed td { background: transparent !important; color: #166534 !important; }
        .row-completed::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            background-image: 
                radial-gradient(circle, #22c55e 1.5px, transparent 1.5px),
                radial-gradient(circle, #f59e0b 1.5px, transparent 1.5px),
                radial-gradient(circle, #ef4444 1px, transparent 1px),
                radial-gradient(circle, #6366f1 1.5px, transparent 1.5px);
            background-size: 60px 60px, 80px 80px, 100px 100px, 70px 70px;
            background-position: 0 0, 20px 20px, 40px 40px, 60px 60px;
            animation: celebrate-particles 3s linear infinite;
            z-index: -1;
        }
        
        .col-name { width: 50%; vertical-align: top; padding: 12px 16px !important; }
        .col-owner { width: 40px; }
        .col-weight { width: 50px; text-align: center; }
        .col-status { width: 80px; }
        .col-progress { width: 100px; }
        .col-action { width: 30px; text-align: right; }
        
        .user-avatar { width: 20px; height: 20px; font-size: 8px; background: #e2e8f0; color: #334155; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 1px solid #cbd5e1; }
        .avatar-blue { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
        .avatar-purple { background: #f3e8ff; color: #7e22ce; border-color: #e9d5ff; }

        .progress-tiny { width: 60px; height: 3px; background: #eee; border-radius: 2px; }
        .progress-bar { height: 100%; background-color: #34c759; border-radius: 4px; transition: width 0.3s ease; }

        .status-badge { padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; display: inline-flex; align-items: center; gap: 5px; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05); }
        .status-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
        .st-ontrack { background: #ecfdf5; color: #059669; }
        .history-box { margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 16px; max-height: 300px; overflow-y: auto; text-align: left; }
        textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 12px; color: #1e293b; background: #f8fafc; resize: vertical; margin-top: 5px; }
        textarea:focus { outline: none; border-color: #3b82f6; background: #fff; }
        .st-ontrack::before { background: #10b981; }
        .st-atrisk { background: #fffbeb; color: #d97706; }
        .st-atrisk::before { background: #f59e0b; }
        .st-pending { background: #f8fafc; color: #64748b; }
        .st-pending::before { background: #94a3b8; }
        
        .prio-badge { font-size: 9px; font-weight: 800; padding: 1px 4px; border-radius: 3px; text-transform: uppercase; margin-right: 4px; display: inline-flex; align-items: center; gap: 3px; }
        .prio-high { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
        .prio-medium { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
        .prio-low { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .val-target { font-size: 12px; font-weight: 600; }
        .val-unit { font-size: 10px; color: #888; }
        .btn-add-inline { border: none; background: transparent; color: #0071e3; font-size: 10px; font-weight: 600; cursor: pointer; padding: 0; }
        .btn-add-inline:hover { text-decoration: underline; }
        
        .btn-edit-row { opacity: 0; transition: opacity 0.2s; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; background: none; border: none; padding: 4px; color: #94a3b8; border-radius: 4px; }
        .btn-edit-row:hover { background: #f1f5f9; color: #6366f1; }
        .okr-card-header:hover .btn-edit-row, 
        .okr-card-header:hover .btn-delete-row,
        .okr-table tr:hover .btn-edit-row,
        .okr-table tr:hover .btn-delete-row { opacity: 1; }

        .btn-delete-row { opacity: 0; background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 13px; padding: 4px; transition: all 0.2s; margin-left:8px; display: inline-flex; align-items: center; justify-content: center; }
        .btn-delete-row:hover { color: #ef4444; }

        .item-header-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .item-number-circle { width: 22px; height: 22px; background: #f2f2f7; color: #86868b; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .obj-number-circle { width: 30px; height: 30px; background: #ffffff; color: #1d1d1f; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .item-main-title { font-weight: 700; color: #475569; margin: 0; font-size: 14px; letter-spacing: -0.01em; }
        .prio-badge { margin: 0; }
        
        /* Full-Width Borderless Callout Boxes for Latest Feedback */
        .latest-comment { margin-top: 8px; font-size: 11px; padding: 10px 14px; border-radius: 8px; display: flex; align-items: flex-start; gap: 8px; width: 100%; box-sizing: border-box; white-space: normal; line-height: 1.5; border: none; position: relative; }
        .latest-comment i { font-size: 11px; margin-top: 3px; flex-shrink: 0; }
        
        .rich-preview-inline { flex: 1; }
        .rich-preview-inline p { margin: 0; padding: 0; display: inline; }
        .rich-preview-inline strong, .rich-preview-inline b { font-weight: 700; }
        .rich-preview-inline i, .rich-preview-inline em { font-style: italic; }
        
        .callout-success { background: #dcfce7; color: #166534; }
        .callout-warning { background: #fef3c7; color: #92400e; }
        .callout-danger  { background: #fee2e2; color: #991b1b; }
        .callout-info    { background: #e0f2fe; color: #075985; }
        .callout-pending { background: #f1f5f9; color: #475569; }

        .history-box { margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 20px; text-align: left; }
        .history-item { margin-bottom: 16px; display: flex; flex-direction: column; align-items: flex-start; }
        .history-bubble { background: #f1f5f9; padding: 10px 14px; border-radius: 14px 14px 14px 2px; border: 1px solid #e2e8f0; max-width: 85%; position: relative; }
        .history-meta { font-size: 10px; color: #94a3b8; margin-bottom: 4px; padding-left: 2px; display: flex; gap: 8px; }
        .history-content { font-size: 12px; color: #1e293b; line-height: 1.5; }
        
        /* Message styles for 'me' or system could be added here if needed */
        .history-item.me { align-items: flex-end; }
        .history-item.me .history-bubble { background: #e0f2fe; border-color: #bae6fd; border-radius: 14px 14px 2px 14px; }
        .history-item.me .history-meta { flex-direction: row-reverse; padding-right: 2px; }

        /* Sidebar Drawer Styles */
        .apple-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); backdrop-filter: blur(4px); z-index: 9999; display: none; justify-content: flex-end; }
        .apple-modal { background: #ffffff; width: 450px; height: 100%; box-shadow: -10px 0 30px rgba(0,0,0,0.1); transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
        .apple-modal.active { transform: translateX(0); }
        
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
                    
                    <!-- Team OKR Tab -->
                    <div class="member-item team-tab <?php echo ($is_team_view) ? 'active' : ''; ?>" style="padding: 4px 12px; background: <?php echo $is_team_view ? '#0071e3' : '#f5f5f7'; ?>; border-radius:12px;">
                        <a href="?team=<?php echo urlencode($current_team_tab); ?>&view=team&quarter=<?php echo $current_quarter; ?>&year=<?php echo $current_year; ?>" style="text-decoration:none; color:<?php echo $is_team_view ? 'white' : '#1d1d1f'; ?>; display:flex; align-items:center; gap:8px; font-weight:700;">
                            <i class="fas fa-users-cog"></i>
                            <span style="white-space:nowrap;">Team OKR</span>
                        </a>
                        <button class="btn-view-annual" onclick="viewAnnualOKR(0, '<?php echo addslashes($current_team_tab); ?>', <?php echo $current_year; ?>)" title="Xem OKR của Team cả năm">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                    </div>
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

                        <?php foreach ($objs_in_team as $obj): ?>
                        <div class="okr-card" id="obj-<?php echo $obj['id']; ?>">
                        <div class="okr-card-header">
                        <div class="obj-left">
                            <div class="obj-title-group">
                                <h3 title="<?php echo htmlspecialchars($obj['title']); ?>">
                                    <i class="fas fa-bullseye" style="color:#6366f1; font-size:14px;"></i>
                                    <?php echo htmlspecialchars($obj['title']); ?>
                                    <button class="btn-edit-row btn-edit-objective" 
                                            title="Edit Objective" 
                                            data-id="<?php echo $obj['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($obj['title']); ?>"
                                            data-team="<?php echo htmlspecialchars($obj['team']); ?>"
                                            data-owner="<?php echo htmlspecialchars($obj['owner']); ?>"
                                            data-owner-id="<?php echo intval($obj['owner_id'] ?? 0); ?>"
                                            data-status="<?php echo htmlspecialchars($obj['status']); ?>"
                                            data-sort-order="<?php echo intval($obj['sort_order'] ?? 0); ?>"
                                            data-quarter="<?php echo intval($obj['quarter'] ?? 1); ?>"
                                            data-year="<?php echo intval($obj['year'] ?? 2026); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete-row" style="margin-left: 0;" onclick="deleteObjective(<?php echo $obj['id']; ?>)" title="Delete Objective">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </h3>
                                <div class="obj-meta-row">
                                    <div class="meta-group">
                                        <i class="fas fa-user-circle" style="font-size:11px; opacity:0.7;"></i>
                                        <span class="meta-lbl">Owner:</span>
                                        <span style="font-weight:600; color:#334155;"><?php echo htmlspecialchars($obj['owner'] ?? 'User'); ?></span>
                                    </div>
                                    <div class="meta-divider"></div>
                                    <div class="meta-group">
                                        <i class="fas fa-chart-line" style="font-size:11px; opacity:0.7;"></i>
                                        <span class="meta-lbl">Progress:</span>
                                        <span style="font-weight:800; color:#0f172a; min-width:30px;"><?php echo intval($obj['progress']); ?>%</span>
                                        <div class="progress-tiny" style="width:120px; background:#f1f5f9;"><div class="progress-bar" id="obj-progress-bar-<?php echo $obj['id']; ?>" style="width: <?php echo $obj['progress']; ?>%; height:100%; background:#10b981; border-radius:2px;"></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="obj-right">
                            <?php echo getBadgeHtml($obj['status']); ?>
                        </div>
                    </div>

                    <div class="okr-body-split">
                        <!-- Key Activities Column (LEFT) -->
                        <div class="okr-col">
                            <div class="section-label">
                                <span><i class="fas fa-tasks"></i> Key Activities</span>
                                <button class="btn-add-inline" onclick="openAddModal(<?php echo $obj['id']; ?>, 'activity')">+ Add KA</button>
                            </div>
                            <table class="okr-table">
                                <tbody>
                                    <?php if(empty($obj['activities'])): ?>
                                        <tr><td colspan="6" style="color:#b2b2b6; font-style:italic; text-align:center; padding:12px;">No activities</td></tr>
                                    <?php else: ?>
                                        <?php $a_idx = 1; foreach ($obj['activities'] as $a): 
                                            $row_class = 'row-prio-' . ($a['priority'] ?? 'medium');
                                            if(($a['status'] ?? '') === 'completed') $row_class .= ' row-completed';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td class="col-name" title="<?php echo htmlspecialchars($a['activity_name'] ?? ''); ?>">
                                                <div class="item-header-row">
                                                    <span class="item-number-circle"><?php echo $a_idx++; ?></span>
                                                    <?php if(($a['priority'] ?? 'medium') === 'high'): ?><span class="prio-badge prio-high"><i class="fas fa-fire"></i> High</span><?php endif; ?>
                                                    <?php if(($a['priority'] ?? 'medium') === 'medium'): ?><span class="prio-badge prio-medium"><i class="fas fa-minus"></i> Med</span><?php endif; ?>
                                                    <?php if(($a['priority'] ?? 'medium') === 'low'): ?><span class="prio-badge prio-low"><i class="fas fa-arrow-down"></i> Low</span><?php endif; ?>
                                                    
                                                    <h4 class="item-main-title"><i class="fas fa-walking" style="margin-right:6px; font-size:12px; opacity:0.6;"></i><?php echo htmlspecialchars($a['activity_name'] ?? ''); ?></h4>
                                                </div>
                                                
                                                <?php if(!empty($a['latest_explanation'])): 
                                                    $c_class = 'callout-pending'; $c_icon = 'fa-clock';
                                                    if($a['status'] === 'on_track') { $c_class = 'callout-success'; $c_icon = 'fa-check-circle'; }
                                                    if($a['status'] === 'completed') { $c_class = 'callout-success'; $c_icon = 'fa-trophy'; }
                                                    if($a['status'] === 'delayed') { $c_class = 'callout-warning'; $c_icon = 'fa-hourglass-half'; }
                                                    if($a['status'] === 'at_risk') { $c_class = 'callout-danger'; $c_icon = 'fa-exclamation-circle'; }
                                                    if($a['status'] === 'in_progress') { $c_class = 'callout-info'; $c_icon = 'fa-spinner fa-spin'; }
                                                ?>
                                                    <div class="latest-comment <?php echo $c_class; ?>" title="Giải trình gần nhất">
                                                        <i class="fas <?php echo $c_icon; ?>"></i> <div class="rich-preview-inline"><?php echo $a['latest_explanation']; ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-owner" title="Owner"><div class="user-avatar" title="<?php echo htmlspecialchars($a['owner_name'] ?? ''); ?>"><?php echo $a['owner_avatar'] ?? ''; ?></div></td>
                                            <td class="col-weight" title="Weight (%)" style="font-size:12px; font-weight:700; color:#1d1d1f;"><?php echo intval($a['weight'] ?? 0); ?>%</td>
                                            <td class="col-status" title="Status" id="td-status-activity-<?php echo $a['id']; ?>"><?php echo getBadgeHtml($a['status'] ?? 'pending'); ?></td>
                                            <td class="col-progress" title="Progress (%)">
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <span style="font-weight:600; min-width:26px;"><span id="td-val-activity-<?php echo $a['id']; ?>"><?php echo intval($a['progress']); ?></span>%</span>
                                                    <div class="progress-tiny"><div class="progress-bar" id="td-bar-activity-<?php echo $a['id']; ?>" style="width: <?php echo $a['progress']; ?>%; height:100%; background:#34c759;"></div></div>
                                                </div>
                                            </td>
                                            <td class="col-action">
                                                <div style="display:flex; align-items:center;">
                                                    <button class="btn-edit-row" onclick="openUpdateModal('<?php echo addslashes($a['activity_name'] ?? ''); ?>', 'activity', <?php echo $a['id']; ?>, <?php echo floatval($a['progress'] ?? 0); ?>, 100, '<?php echo $a['status'] ?? 'pending'; ?>', '<?php echo addslashes($a['owner_name'] ?? ''); ?>', '<?php echo $a['priority'] ?? 'medium'; ?>', <?php echo intval($a['weight'] ?? 0); ?>)"><i class="fas fa-pen"></i></button>
                                                    <button class="btn-delete-row" title="Delete Activity" onclick="deleteOkrItem(<?php echo $a['id']; ?>, 'activity')"><i class="fas fa-trash-alt"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Key Results Column (RIGHT) -->
                        <div class="okr-col">
                            <div class="section-label">
                                <span><i class="fas fa-bullseye"></i> Key Results</span>
                                <button class="btn-add-inline" onclick="openAddModal(<?php echo $obj['id']; ?>, 'metric')">+ Add KR</button>
                            </div>
                            <table class="okr-table">
                                <tbody>
                                    <?php if(empty($obj['results'])): ?>
                                        <tr><td colspan="6" style="color:#b2b2b6; font-style:italic; text-align:center; padding:12px;">No results</td></tr>
                                    <?php else: ?>
                                        <?php $r_idx = 1; foreach ($obj['results'] as $r): 
                                            $row_class = 'row-prio-' . ($r['priority'] ?? 'medium');
                                            if(($r['status'] ?? '') === 'completed') $row_class .= ' row-completed';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td class="col-name" title="<?php echo htmlspecialchars($r['metric_name'] ?? ''); ?>">
                                                <div class="item-header-row">
                                                    <span class="item-number-circle"><?php echo $r_idx++; ?></span>
                                                    <?php if(($r['priority'] ?? 'medium') === 'high'): ?><span class="prio-badge prio-high"><i class="fas fa-fire"></i> High</span><?php endif; ?>
                                                    <?php if(($r['priority'] ?? 'medium') === 'medium'): ?><span class="prio-badge prio-medium"><i class="fas fa-minus"></i> Med</span><?php endif; ?>
                                                    <?php if(($r['priority'] ?? 'medium') === 'low'): ?><span class="prio-badge prio-low"><i class="fas fa-arrow-down"></i> Low</span><?php endif; ?>
                                                    
                                                    <h4 class="item-main-title"><i class="fas fa-crosshairs" style="margin-right:6px; font-size:12px; opacity:0.6;"></i><?php echo htmlspecialchars($r['metric_name'] ?? ''); ?></h4>
                                                </div>
                                                
                                                <?php if(!empty($r['latest_explanation'])): 
                                                    $c_class = 'callout-pending'; $c_icon = 'fa-clock';
                                                    if($r['status'] === 'on_track') { $c_class = 'callout-success'; $c_icon = 'fa-check-circle'; }
                                                    if($r['status'] === 'completed') { $c_class = 'callout-success'; $c_icon = 'fa-trophy'; }
                                                    if($r['status'] === 'delayed') { $c_class = 'callout-warning'; $c_icon = 'fa-hourglass-half'; }
                                                    if($r['status'] === 'at_risk') { $c_class = 'callout-danger'; $c_icon = 'fa-exclamation-circle'; }
                                                    if($r['status'] === 'in_progress') { $c_class = 'callout-info'; $c_icon = 'fa-spinner fa-spin'; }
                                                ?>
                                                    <div class="latest-comment <?php echo $c_class; ?>" title="Giải trình gần nhất">
                                                        <i class="fas <?php echo $c_icon; ?>"></i> <div class="rich-preview-inline"><?php echo $r['latest_explanation']; ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-owner" title="Owner"><div class="user-avatar avatar-purple" title="<?php echo htmlspecialchars($r['owner_name'] ?? ''); ?>"><?php echo $r['owner_avatar'] ?? ''; ?></div></td>
                                            <td class="col-weight" title="Weight (%)" style="font-size:12px; font-weight:700; color:#1d1d1f;"><?php echo intval($r['weight'] ?? 0); ?>%</td>
                                            <td class="col-status" title="Status" id="td-status-metric-<?php echo $r['id']; ?>"><?php echo getBadgeHtml($r['status']); ?></td>
                                            <td class="col-progress" title="Current Value / Target Value">
                                                <?php 
                                                    $progress_pct = ($r['target_value'] > 0) ? ($r['current_value'] / $r['target_value']) * 100 : 0;
                                                    $progress_pct = min(100, $progress_pct);
                                                ?>
                                                <div style="display:flex; flex-direction:column; gap:4px;">
                                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                                        <span class="val-target"><span id="td-val-metric-<?php echo $r['id']; ?>"><?php echo floatval($r['current_value']); ?></span><span class="val-unit">/<?php echo floatval($r['target_value']).' '.$r['unit']; ?></span></span>
                                                    </div>
                                                    <div class="progress-tiny" style="width: 100%;"><div class="progress-bar" id="td-bar-metric-<?php echo $r['id']; ?>" style="width: <?php echo $progress_pct; ?>%; height:100%; background:#34c759;"></div></div>
                                                </div>
                                            </td>
                                            <td class="col-action">
                                                <div style="display:flex; align-items:center;">
                                                    <button class="btn-edit-row" onclick="openUpdateModal('<?php echo addslashes($r['metric_name'] ?? ''); ?>', 'metric', <?php echo $r['id']; ?>, <?php echo round($progress_pct, 1); ?>, 100, '<?php echo $r['status'] ?? 'pending'; ?>', '<?php echo addslashes($r['owner_name'] ?? ''); ?>', '<?php echo $r['priority'] ?? 'medium'; ?>', <?php echo intval($r['weight'] ?? 0); ?>)"><i class="fas fa-pen"></i></button>
                                                    <button class="btn-delete-row" title="Delete KR" onclick="deleteOkrItem(<?php echo $r['id']; ?>, 'metric')"><i class="fas fa-trash-alt"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
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
                    <label>Progress (%)</label>
                    <input type="number" id="updateItemVal" value="0" min="0" max="100" placeholder="0-100" style="width:100%;">
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
                        <option value="">-- Chọn User (Lấy từ Setting) --</option>
                        <?php foreach ($am_users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['full_name']); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-control">
                    <label>Priority & Weight (%)</label>
                    <div style="display:flex; gap:10px;">
                        <select id="updateItemPriority" style="flex:1;">
                            <option value="high">High Priority</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <input type="number" id="updateItemWeight" placeholder="Weight %" style="width:100px;" min="0" max="100">
                    </div>
                </div>
                <div class="modal-control">
                    <label>Giải trình / Ghi chú (Mới)</label>
                    <div id="quillEditor" style="height: 150px; background: #fbfbfd; border-radius: 8px; border: 1px solid #d2d2d7;"></div>
                    <input type="hidden" id="updateItemExplanation">
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
                        <option value="">-- Chọn User --</option>
                        <?php foreach ($am_users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['full_name']); ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-row" id="addModalMetricConfig" style="display:none; gap:16px; margin-top:16px;">
                    <div class="modal-control" style="flex: 2;">
                        <label>Target Value</label>
                        <input type="number" id="addModalTarget" value="0">
                    </div>
                    <div class="modal-control" style="flex: 1;">
                        <label>Unit</label>
                        <input type="text" id="addModalUnit" value="%">
                    </div>
                </div>

                <div class="modal-control">
                    <label>Priority & Weight (%)</label>
                    <div style="display:flex; gap:10px;">
                        <select id="addItemPriority" style="flex:1;">
                            <option value="high">High Priority</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <input type="number" id="addItemWeight" placeholder="Weight %" style="width:100px;" min="0" max="100" value="0">
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

        /* UPDATE MODAL */
        function openUpdateModal(itemName, type, id, currentValue, targetValue, status, ownerName, priority, weight) {
            document.getElementById('updateModalTitle').innerText = 'Update ' + (type === 'metric' ? 'Key Result' : 'Activity');
            document.getElementById('updateItemId').value = id;
            document.getElementById('updateItemType').value = type;
            document.getElementById('updateItemName').innerText = itemName;
            document.getElementById('updateItemNameInput').value = itemName;
            document.getElementById('updateItemVal').value = currentValue;
            document.getElementById('updateItemStatus').value = status;
            document.getElementById('updateItemOwner').value = ownerName || '';
            document.getElementById('updateItemPriority').value = priority || 'medium';
            document.getElementById('updateItemWeight').value = weight || 0;
            quill.root.innerHTML = ''; // Clear Editor

            // Both KA and KR use progress % (0-100)
            document.getElementById('updateItemVal').value = currentValue;
            document.getElementById('updateItemHistory').innerHTML = '<p style="font-size:11px; color:#94a3b8; text-align:center;">Đang tải lịch sử...</p>';
            $.post('/modules/okr/index.php', {
                action: 'fetch_explanation_history',
                id: id,
                type: type
            }, function(res) {
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
            const owner = document.getElementById('updateItemOwner').value;
            const priority = document.getElementById('updateItemPriority').value;
            const weight = document.getElementById('updateItemWeight').value;
            const explanation = quill.root.innerHTML;
            // Both KA and KR now use simple progress %, no separate target/unit fields
            
            if (quill.getText().trim().length === 0) {
                 // Option: don't save if empty, or just let it pass
            }
            
            document.getElementById('loadingSpinner').style.display = 'inline-block';

            $.post('/modules/okr/index.php', {
                action: 'update_okr_item',
                id: id,
                type: type,
                name: name,
                val: val,
                status: status,
                owner: owner,
                priority: priority,
                weight: weight,
                explanation: explanation
            }, function(res) {
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
        function openAddModal(obj_id, type) {
            document.getElementById('addModalObjId').value = obj_id;
            document.getElementById('addModalType').value = type;
            document.getElementById('addModalName').value = '';
            document.getElementById('addModalOwner').value = '';

            if (type === 'metric') {
                document.getElementById('addModalTitle').innerText = 'Add Target Result (Metric)';
                document.getElementById('addModalNameLbl').innerText = 'Metric Description';
                document.getElementById('addModalMetricConfig').style.display = 'flex';
            } else {
                document.getElementById('addModalTitle').innerText = 'Add Key Activity';
                document.getElementById('addModalNameLbl').innerText = 'Activity Description';
                document.getElementById('addModalMetricConfig').style.display = 'none';
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
            const owner = document.getElementById('addModalOwner').value;
            const target = document.getElementById('addModalTarget').value;
            const unit = document.getElementById('addModalUnit').value;
            const priority = document.getElementById('addItemPriority').value;
            const weight = document.getElementById('addItemWeight').value;

            if (!name) { alert("Vui lòng nhập tên công việc/chỉ số!"); return; }

            document.getElementById('addLoadingSpinner').style.display = 'inline-block';

            $.post('/modules/okr/index.php', {
                action: 'add_okr_item',
                obj_id: oid,
                type: type,
                name: name,
                owner_name: owner,
                target: target,
                unit: unit,
                priority: priority,
                weight: weight
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
            let modal = document.getElementById('visibilityModalOverlay');
            modal.style.display = 'flex';
            setTimeout(function() { document.getElementById('visibilityModalContent').classList.add('active'); }, 10);
        }

        function closeVisibilityModal() {
            let modalContent = document.getElementById('visibilityModalContent');
            modalContent.classList.remove('active');
            setTimeout(function() { document.getElementById('visibilityModalOverlay').style.display = 'none'; }, 200);
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
</body>
</html>
