<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$full_name = $_SESSION['full_name'] ?? '';

// Tính toán max filesize hệ thống
function get_bytes($val) {
    if(empty($val)) return 0;
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}
$upload_max = get_bytes(ini_get('upload_max_filesize'));
$post_max = get_bytes(ini_get('post_max_size'));
$max_size_bytes = min($upload_max ?: 2097152, $post_max ?: 8388608); // default fallback to 2M, 8M
$max_size_mb = floor($max_size_bytes / (1024 * 1024));
if ($max_size_mb <= 0) $max_size_mb = 2; // fallback display

// Auto-migration: Create document_categories table first
$conn->query("CREATE TABLE IF NOT EXISTS document_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    allowed_roles TEXT DEFAULT NULL, -- Comma separated roles
    allowed_levels TEXT DEFAULT NULL, -- Comma separated levels (Member, Leader, etc)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES document_categories(id) ON DELETE CASCADE
)");

// Migration: Check columns
function syncDocCatColumn($conn, $col, $def) {
    if($conn->query("SHOW COLUMNS FROM document_categories LIKE '$col'")->num_rows == 0) 
        $conn->query("ALTER TABLE document_categories ADD $col $def");
}
syncDocCatColumn($conn, 'allowed_roles', 'TEXT DEFAULT NULL');
syncDocCatColumn($conn, 'allowed_levels', 'TEXT DEFAULT NULL');

function syncDocTagColumn($conn, $col, $def) {
    if($conn->query("SHOW COLUMNS FROM document_tags LIKE '$col'")->num_rows == 0) 
        $conn->query("ALTER TABLE document_tags ADD $col $def");
}
syncDocTagColumn($conn, 'bg_color', 'VARCHAR(20) DEFAULT NULL');
syncDocTagColumn($conn, 'border_color', 'VARCHAR(20) DEFAULT NULL');
syncDocTagColumn($conn, 'text_color', 'VARCHAR(20) DEFAULT NULL');
syncDocTagColumn($conn, 'icon', 'VARCHAR(50) DEFAULT NULL');

// Auto-migration: Create document_tags table
$conn->query("CREATE TABLE IF NOT EXISTS document_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) DEFAULT '#64748B',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Auto-migration: Create document_tag_map table
$conn->query("CREATE TABLE IF NOT EXISTS document_tag_map (
    doc_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (doc_id, tag_id),
    FOREIGN KEY (doc_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES document_tags(id) ON DELETE CASCADE
)");

// Pre-fill tags if empty
$check_tags = $conn->query("SELECT count(*) as count FROM document_tags");
if ($check_tags && $check_tags->fetch_assoc()['count'] == 0) {
    $conn->query("INSERT INTO document_tags (name, color) VALUES 
        ('Important', '#EF4444'),
        ('Outdated', '#EA580C'),
        ('Draft', '#64748B'),
        ('Private', '#1E293B'),
        ('Verified', '#16A34A')
    ");
}

// Check if category_id or label exists in documents
if($conn->query("SHOW COLUMNS FROM documents LIKE 'category_id'")->num_rows == 0) {
    $conn->query("ALTER TABLE documents ADD category_id INT DEFAULT NULL"); 
}
if($conn->query("SHOW COLUMNS FROM documents LIKE 'label'")->num_rows == 0) {
    $conn->query("ALTER TABLE documents ADD label VARCHAR(50) DEFAULT NULL"); 
}

// Pre-fill categories if empty
$check_cat = $conn->query("SELECT count(*) as count FROM document_categories");
if ($check_cat && $check_cat->fetch_assoc()['count'] == 0) {
    $conn->query("INSERT INTO document_categories (name, parent_id, allowed_roles, allowed_levels) VALUES 
        ('Quy định & Chính sách', NULL, 'admin,manager,user', 'Member,Leader,Manager,Director,C-Level'),
        ('Tài liệu hướng dẫn', NULL, 'admin,manager,user', 'Member,Leader,Manager,Director,C-Level'),
        ('Tài liệu kỹ thuật', NULL, 'admin,manager', 'Manager,Director,C-Level'),
        ('Báo cáo CLO', NULL, 'admin', 'C-Level')
    ");
}

// Auto-migration: Create documents table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT DEFAULT NULL,
    label VARCHAR(50) DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle POST APIs (Upload, Add Cat, Delete Cat, Move Doc, Update Perms)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        if ($_POST['action'] === 'add_category') {
            $name = $conn->real_escape_string($_POST['name']);
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 'NULL';
            $roles = isset($_POST['roles']) ? (is_array($_POST['roles']) ? implode(',', $_POST['roles']) : $_POST['roles']) : '';
            $levels = isset($_POST['levels']) ? (is_array($_POST['levels']) ? implode(',', $_POST['levels']) : $_POST['levels']) : '';
            $conn->query("INSERT INTO document_categories (name, parent_id, allowed_roles, allowed_levels) VALUES ('$name', $parent_id, '$roles', '$levels')");
            echo json_encode(['status' => 'success']);
            exit();
        }
        
        if ($_POST['action'] === 'delete_category') {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM document_categories WHERE id = $id");
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($_POST['action'] === 'add_tag') {
            $name = $conn->real_escape_string($_POST['name']);
            $color = $conn->real_escape_string($_POST['color'] ?? '#64748B');
            $bg = !empty($_POST['bg_color']) ? "'" . $conn->real_escape_string($_POST['bg_color']) . "'" : "NULL";
            $border = !empty($_POST['border_color']) ? "'" . $conn->real_escape_string($_POST['border_color']) . "'" : "NULL";
            $text = !empty($_POST['text_color']) ? "'" . $conn->real_escape_string($_POST['text_color']) . "'" : "NULL";
            $icon = !empty($_POST['icon']) ? "'" . $conn->real_escape_string($_POST['icon']) . "'" : "NULL";
            
            $conn->query("INSERT INTO document_tags (name, color, bg_color, border_color, text_color, icon) 
                          VALUES ('$name', '$color', $bg, $border, $text, $icon)");
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($_POST['action'] === 'delete_tag') {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM document_tags WHERE id = $id");
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($_POST['action'] === 'move_document') {
            $doc_id = (int)$_POST['doc_id'];
            $cat_id = !empty($_POST['cat_id']) ? (int)$_POST['cat_id'] : 'NULL';
            $label = !empty($_POST['label']) ? "'" . $conn->real_escape_string($_POST['label']) . "'" : 'NULL';
            $conn->query("UPDATE documents SET category_id = $cat_id, label = $label WHERE id = $doc_id");
            
            // Multi-Tags
            $conn->query("DELETE FROM document_tag_map WHERE doc_id = $doc_id");
            if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                foreach($_POST['tags'] as $tag_val) {
                    if (is_numeric($tag_val)) {
                        $tag_id = (int)$tag_val;
                    } else {
                        $safe_tag_name = $conn->real_escape_string($tag_val);
                        $conn->query("INSERT IGNORE INTO document_tags (name) VALUES ('$safe_tag_name')");
                        $tag_id = $conn->insert_id ?: $conn->query("SELECT id FROM document_tags WHERE name = '$safe_tag_name'")->fetch_assoc()['id'] ?? 0;
                    }
                    if ($tag_id > 0) {
                        $conn->query("INSERT IGNORE INTO document_tag_map (doc_id, tag_id) VALUES ($doc_id, $tag_id)");
                    }
                }
            }
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($_POST['action'] === 'update_label') {
            $doc_id = (int)$_POST['doc_id'];
            $label = !empty($_POST['label']) ? "'" . $conn->real_escape_string($_POST['label']) . "'" : 'NULL';
            $conn->query("UPDATE documents SET label = $label WHERE id = $doc_id");
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($_POST['action'] === 'delete_document') {
            $id = (int)$_POST['id'];
            $res = $conn->query("SELECT file_path FROM documents WHERE id = $id");
            if ($row = $res->fetch_assoc()) {
                if (file_exists($row['file_path'])) unlink($row['file_path']);
            }
            $conn->query("DELETE FROM documents WHERE id = $id");
            echo json_encode(['status' => 'success']);
            exit();
        }

        if ($_POST['action'] === 'update_category_permissions') {
            $cat_id = (int)$_POST['cat_id'];
            $roles = isset($_POST['roles']) ? (is_array($_POST['roles']) ? implode(',', $_POST['roles']) : $_POST['roles']) : '';
            $levels = isset($_POST['levels']) ? (is_array($_POST['levels']) ? implode(',', $_POST['levels']) : $_POST['levels']) : '';
            $conn->query("UPDATE document_categories SET allowed_roles = '$roles', allowed_levels = '$levels' WHERE id = $cat_id");
            echo json_encode(['status' => 'success']);
            exit();
        }
    }

    if (isset($_FILES['doc_file'])) {
        $title = $conn->real_escape_string($_POST['title']);
        $desc = $conn->real_escape_string($_POST['description']);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 'NULL';
        $label = !empty($_POST['label']) ? "'" . $conn->real_escape_string($_POST['label']) . "'" : 'NULL';
        $target_dir = __DIR__ . "/../../public/uploads/documents/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES["doc_file"]["name"]);
        $target_file = $target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $file_size = $_FILES["doc_file"]["size"];
        if (move_uploaded_file($_FILES["doc_file"]["tmp_name"], $target_file)) {
            $file_url = "/public/uploads/documents/" . $file_name;
            $sql = "INSERT INTO documents (title, description, category_id, label, file_path, file_name, file_type, file_size, uploaded_by) 
                    VALUES ('$title', '$desc', $category_id, $label, '$file_url', '$file_name', '$file_type', $file_size, $user_id)";
            $conn->query($sql);
            $new_doc_id = $conn->insert_id;

            // Multi-Tags for new upload
            if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                foreach($_POST['tags'] as $tag_val) {
                    if (is_numeric($tag_val)) {
                        $tag_id = (int)$tag_val;
                    } else {
                        $safe_tag_name = $conn->real_escape_string($tag_val);
                        $conn->query("INSERT IGNORE INTO document_tags (name) VALUES ('$safe_tag_name')");
                        $tag_id = $conn->insert_id ?: $conn->query("SELECT id FROM document_tags WHERE name = '$safe_tag_name'")->fetch_assoc()['id'] ?? 0;
                    }
                    if ($tag_id > 0) {
                        $conn->query("INSERT IGNORE INTO document_tag_map (doc_id, tag_id) VALUES ($new_doc_id, $tag_id)");
                    }
                }
            }

            if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['status' => 'success']); exit(); }
            header("Location: /documents?upload=success"); exit();
        } else {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Upload thất bại. Mã lỗi: ' . ($_FILES['doc_file']['error'])]);
                exit();
            }
        }
    }
}

// Fetch all available tags
$available_tags = [];
$tag_res = $conn->query("SELECT * FROM document_tags ORDER BY name ASC");
if($tag_res) while($t = $tag_res->fetch_assoc()) $available_tags[] = $t;

// Fetch current user level for precision filtering
$u_res = $conn->query("SELECT level FROM users WHERE id = $user_id");
$user_level = $u_res ? $u_res->fetch_assoc()['level'] : 'Member';
$available_levels = ['Member', 'Leader', 'Manager', 'Director', 'C-Level'];

// Fetch all categories to build the structure
$all_cats = [];
$cat_query = $conn->query("SELECT * FROM document_categories ORDER BY name ASC");
if ($cat_query) while($r = $cat_query->fetch_assoc()) $all_cats[] = $r;

$explicit_allowed_ids = [];
$norm_role = strtolower(trim($role));
$norm_level = strtolower(trim($user_level));

foreach ($all_cats as $cat) {
    if ($role === 'admin') {
        $explicit_allowed_ids[] = (int)$cat['id'];
        continue;
    }
    
    // Convert allowed lists to lowercase and trim
    $allowed_roles = !empty($cat['allowed_roles']) ? array_map(function($v) { return strtolower(trim($v)); }, explode(',', $cat['allowed_roles'])) : [];
    $allowed_levels = !empty($cat['allowed_levels']) ? array_map(function($v) { return strtolower(trim($v)); }, explode(',', $cat['allowed_levels'])) : [];
    
    // Check if role and level are in the allowed lists (empty means allowed for all)
    $role_match = empty($allowed_roles) || in_array($norm_role, $allowed_roles);
    $level_match = empty($allowed_levels) || in_array($norm_level, $allowed_levels);

    if ($role_match && $level_match) {
        $explicit_allowed_ids[] = (int)$cat['id'];
    }
}

// Build final list including all descendants of allowed cats AND all ancestors to root
$visible_ids = [];
foreach ($explicit_allowed_ids as $id) {
    if (!in_array($id, $visible_ids)) $visible_ids[] = $id;
    
    // Add all children/descendants (Inheritance)
    $desc_ids = getDescendantIds($all_cats, $id);
    foreach($desc_ids as $did) if(!in_array($did, $visible_ids)) $visible_ids[] = $did;
    
    // Add all parents up to root (Hierarchy structural integrity)
    $curr_id = $id;
    while ($curr_id !== null) {
        $parent_id = null;
        foreach($all_cats as $c) if((int)$c['id'] === $curr_id) { $parent_id = $c['parent_id']; break; }
        if ($parent_id !== null) {
            $parent_id = (int)$parent_id;
            if (!in_array($parent_id, $visible_ids)) $visible_ids[] = $parent_id;
            $curr_id = $parent_id;
        } else {
            $curr_id = null;
        }
    }
}

$categories = [];
foreach ($all_cats as $cat) {
    if (in_array((int)$cat['id'], $visible_ids)) {
        $categories[] = $cat;
    }
}

function getDescendantIds($cats, $pid) {
    if ($pid === null) return [];
    $pid = (int)$pid;
    $ids = [];
    foreach($cats as $c) {
        $parent_id = ($c['parent_id'] !== null) ? (int)$c['parent_id'] : null;
        if ($parent_id === $pid) {
            $ids[] = (int)$c['id'];
            $child_ids = getDescendantIds($cats, (int)$c['id']);
            $ids = array_merge($ids, $child_ids);
        }
    }
    return array_unique($ids);
}

$cat_filter = "";
$current_cat_name = 'Tất cả tài liệu';
if (isset($_GET['cat_id']) && is_numeric($_GET['cat_id'])) {
    foreach ($categories as $c) {
        if ((int)$c['id'] === (int)$_GET['cat_id']) {
            $current_cat_name = $c['name'];
            break;
        }
    }
    // Include the category itself plus all its descendants
    $selected_cat_ids = array_merge([(int)$_GET['cat_id']], getDescendantIds($categories, (int)$_GET['cat_id']));
    $cat_filter .= " AND d.category_id IN (" . implode(',', $selected_cat_ids) . ") ";
} else if ($role !== 'admin') {
    // If no category selected, limit to documents in categories current user can see
    $visible_cat_ids = array_column($categories, 'id');
    if (!empty($visible_cat_ids)) {
        $cat_filter .= " AND (d.category_id IN (" . implode(',', $visible_cat_ids) . ") OR d.category_id IS NULL) ";
    } else {
        $cat_filter .= " AND d.category_id IS NULL ";
    }
}

// Search Filter
$search_q = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
if (!empty($search_q)) {
    $cat_filter .= " AND (d.title LIKE '%$search_q%' OR d.description LIKE '%$search_q%' OR d.file_name LIKE '%$search_q%') ";
}

// Sort Logic
$sort_by = $_GET['sort'] ?? 'newest';
$order_clause = "d.created_at DESC"; // Default
switch ($sort_by) {
    case 'oldest': $order_clause = "d.created_at ASC"; break;
    case 'name_asc': $order_clause = "d.title ASC"; break;
    case 'name_desc': $order_clause = "d.title DESC"; break;
    case 'size_desc': $order_clause = "d.file_size DESC"; break;
    case 'size_asc': $order_clause = "d.file_size ASC"; break;
}

// Get direct sub-categories for Google Drive-like view
$direct_sub_cats = [];
$active_cat_id_raw = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : null;
$descendant_ids = $active_cat_id_raw !== null ? getDescendantIds($categories, $active_cat_id_raw) : [];

foreach ($categories as $c) {
    if (!empty($search_q)) {
        // Search mode: find matching folders
        if (stripos($c['name'], $search_q) !== false) {
            if ($active_cat_id_raw === null) {
                $direct_sub_cats[] = $c;
            } else {
                // Only show if it's within current category hierarchy
                if ((int)$c['id'] === $active_cat_id_raw || in_array((int)$c['id'], $descendant_ids)) {
                    $direct_sub_cats[] = $c;
                }
            }
        }
    } else {
        // Normal mode: show direct children only
        if ($active_cat_id_raw === null) {
            if ($c['parent_id'] === null) $direct_sub_cats[] = $c;
        } else {
            if ((int)$c['parent_id'] === $active_cat_id_raw) $direct_sub_cats[] = $c;
        }
    }
}

function getCategoryPath($all_cats, $id) {
    $path = [];
    $curr_id = $id;
    while ($curr_id !== null) {
        $found = false;
        foreach ($all_cats as $c) {
            if ((int)$c['id'] === $curr_id) {
                array_unshift($path, $c);
                $curr_id = $c['parent_id'] !== null ? (int)$c['parent_id'] : null;
                $found = true;
                break;
            }
        }
        if (!$found) break;
    }
    return $path;
}

$breadcrumb_path = isset($_GET['cat_id']) ? getCategoryPath($categories, (int)$_GET['cat_id']) : [];

// Pagination Logic
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

$total_docs_query = $conn->query("SELECT COUNT(DISTINCT d.id) as total FROM documents d WHERE 1=1 $cat_filter");
$total_docs = $total_docs_query ? $total_docs_query->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_docs / $items_per_page);

$docs = $conn->query("SELECT d.*, u.full_name as uploader, 
                             GROUP_CONCAT(CONCAT(t.name, '||', IFNULL(t.color,''), '||', IFNULL(t.bg_color,''), '||', IFNULL(t.border_color,''), '||', IFNULL(t.text_color,''), '||', IFNULL(t.icon,'')) SEPARATOR ':::') as tags_info,
                             GROUP_CONCAT(t.id) as tag_ids
                      FROM documents d 
                      LEFT JOIN users u ON d.uploaded_by = u.id 
                      LEFT JOIN document_tag_map dtm ON d.id = dtm.doc_id 
                      LEFT JOIN document_tags t ON dtm.tag_id = t.id 
                      WHERE 1=1 $cat_filter 
                      GROUP BY d.id 
                      ORDER BY $order_clause 
                      LIMIT $items_per_page OFFSET $offset");
$all_docs = [];
if ($docs) while($row = $docs->fetch_assoc()) $all_docs[] = $row;

function buildCategoryOptions($cats, $pid = null, $prefix = '') {
    $html = '';
    foreach($cats as $c) {
        if($c['parent_id'] == $pid) {
            $html .= '<option value="'.$c['id'].'">'.$prefix.$c['name'].'</option>';
            $html .= buildCategoryOptions($cats, $c['id'], $prefix.'— ');
        }
    }
    return $html;
}

function buildCategoryTreeLinks($cats, $pid = null, $active_id = null, $level = 0) {
    global $role;
    $html = '';
    
    // Check if this level has children
    $has_children_at_all = false;
    foreach($cats as $c) if($c['parent_id'] == $pid) { $has_children_at_all = true; break; }
    
    if($has_children_at_all && $pid !== null) {
        $contains_active = false;
        if($active_id) {
            $descendants = getDescendantIds($cats, $pid);
            if(in_array((int)$active_id, $descendants)) $contains_active = true;
        }
        $display = $contains_active ? 'block' : 'none';
        $html .= '<ul class="cat-sub-tree" id="sub-'.$pid.'" style="display:'.$display.';">';
    }

    foreach($cats as $c) {
        if($c['parent_id'] == $pid) {
            $is_active_class = ($active_id == $c['id']) ? 'active' : '';
            
            // Check if this specific category has children
            $has_sub = false;
            foreach($cats as $sub) if((int)$sub['parent_id'] === (int)$c['id']) { $has_sub = true; break; }
            
            $html .= '<li style="position:relative;">';
            
            $toggle_btn = '';
            if($has_sub) {
                $contains_active_sub = false;
                if($active_id) {
                    $sub_desc = getDescendantIds($cats, (int)$c['id']);
                    if(in_array((int)$active_id, $sub_desc)) $contains_active_sub = true;
                }
                $rotation = $contains_active_sub ? 'rotate(90deg)' : 'rotate(0deg)';
                $toggle_btn = '<button onclick="toggleSub(event, '.$c['id'].')" class="tree-toggle" style="transform:'.$rotation.';"><i class="fas fa-chevron-right"></i></button>';
            } else {
                $toggle_btn = '<div style="width:20px;"></div>'; // Spacer if no sub
            }

            $actions_html = '';
            if ($role === 'admin') {
                $actions_html .= '<button onclick="event.preventDefault(); event.stopPropagation(); showCatQuickSetup('.$c['id'].', \''.addslashes($c['name']).'\', \''.addslashes($c['allowed_roles'] ?? '').'\', \''.addslashes($c['allowed_levels'] ?? '').'\')" class="btn-setup-inline" title="Phân quyền"><i class="fas fa-shield-halved"></i></button>';
                $actions_html .= '<button onclick="showQuickAddCat(event, '.$c['id'].', \''.addslashes($c['name']).'\')" class="btn-add-inline" title="Thêm danh mục con"><i class="fas fa-plus"></i></button>';
            }

            $html .= '<a href="?cat_id='.$c['id'].'" class="cat-tree-item '.$is_active_class.'">';
            $html .= $toggle_btn;
            $html .= '<i class="fas fa-folder cat-folder-icon"></i>';
            $html .= '<span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'.htmlspecialchars($c['name']).'</span>';
            $html .= $actions_html;
            $html .= '</a>';
            
            if($has_sub) {
                $html .= buildCategoryTreeLinks($cats, $c['id'], $active_id, $level + 1);
            }
            
            $html .= '</li>';
        }
    }
    
    if($has_children_at_all && $pid !== null) {
        $html .= '</ul>';
    }
    return $html;
}

function buildCategoryManagerList($cats, $pid = null, $level = 0) {
    $html = '';
    foreach($cats as $c) {
        if($c['parent_id'] == $pid) {
            $indent = $level * 24;
            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #F1F5F9; padding-left:'.$indent.'px;">';
            $html .= '<div style="display:flex; align-items:center; gap:8px;"><span style="color:#94A3B8;">📁</span><span style="font-weight:500; font-size:14px; color:#1E293B;">'.$c['name'].'</span></div>';
            $html .= '<button type="button" onclick="deleteCategory('.$c['id'].')" style="color:#EF4444; background:none; border:none; border-radius:6px; padding:4px 8px; cursor:pointer; font-size:12px; font-weight:600;" onmouseover="this.style.background=\'#FEF2F2\'" onmouseout="this.style.background=\'none\'">Xóa</button>';
            $html .= '</div>';
            $html .= buildCategoryManagerList($cats, $c['id'], $level + 1);
        }
    }
    return $html;
}

function renderDocLabel($row) {
    $html = '';
    // Priority 1: Dynamic Tags
    if (!empty($row['tags_info'])) {
        $tags = explode(':::', $row['tags_info']);
        foreach($tags as $t_str) {
            $parts = explode('||', $t_str);
            if (count($parts) < 2) continue;
            $name = $parts[0] ?? '';
            $base_color = $parts[1] ?: '#64748B';
            $bg = $parts[2] ?: ($base_color . '15');
            $border = $parts[3] ?: ($base_color . '30');
            $text = $parts[4] ?: $base_color;
            $icon = $parts[5] ?? '';
            
            $icon_html = $icon ? '<i class="'.$icon.'" style="margin-right:4px;"></i>' : '';
            $html .= '<span style="display:inline-block; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; background:'.$bg.'; color:'.$text.'; border:1px solid '.$border.'; text-transform:uppercase; letter-spacing:0.5px; margin-left:6px; vertical-align:middle;">'.$icon_html.$name.'</span>';
        }
    }
    // Priority 2: Legacy Label (Single)
    else if (!empty($row['label'])) {
        $label = $row['label'];
        $colors = [
            'Outdated' => ['bg' => '#FFF7ED', 'text' => '#EA580C'],
            'Important' => ['bg' => '#FEF2F2', 'text' => '#EF4444'],
            'Draft' => ['bg' => '#F8FAFC', 'text' => '#64748B'],
            'Private' => ['bg' => '#F1F5F9', 'text' => '#1E293B'],
            'Verified' => ['bg' => '#F0FDF4', 'text' => '#16A34A']
        ];
        $style = isset($colors[$label]) ? $colors[$label] : ['bg' => '#F1F5F9', 'text' => '#475569'];
        $html .= '<span style="display:inline-block; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; background:'.$style['bg'].'; color:'.$style['text'].'; text-transform:uppercase; letter-spacing:0.5px; margin-left:6px; vertical-align:middle;">'.$label.'</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tài liệu & Văn bản - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/page-flip@2.0.7/dist/js/page-flip.browser.min.js"></script>
    <script src="https://unpkg.com/jszip/dist/jszip.min.js"></script>
    <script src="https://unpkg.com/mammoth@1.4.8/mammoth.browser.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-slate: #1E293B;
            --apple-gray: #64748B;
            --apple-bg: #F5F5F7;
            --apple-card-bg: rgba(255, 255, 255, 0.8);
            --radius-xl: 20px;
            --radius-lg: 14px;
        }

        body { background-color: var(--apple-bg); }
        .main-content { flex: 1; padding: 32px; background: var(--apple-bg); min-height: 100vh; }
        
        .btn-apple { 
            background: var(--apple-blue); 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: var(--radius-lg); 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-apple:hover { transform: scale(1.02); filter: brightness(1.1); box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3); }

        .btn-apple-sec { 
            background: rgba(0, 122, 255, 0.08); 
            color: var(--apple-blue); 
            border: none; 
            padding: 8px 16px; 
            border-radius: var(--radius-lg); 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            transition: all 0.2s;
            gap: 6px;
        }
        .btn-apple-sec:hover { background: rgba(0, 122, 255, 0.12); }
        .btn-apple-sec svg { flex-shrink: 0; }

        .doc-card { 
            background: white; 
            border-radius: var(--radius-xl); 
            padding: 24px; 
            border: 1px solid rgba(0,0,0,0.03); 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .doc-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.06); }

        /* Modal Styles */
        .modal-fullscreen { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 2000; 
            flex-direction: column; 
        }
        .modal-dark { background: rgba(0,0,0,0.8); color: #fff; }
        .modal-header { padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .modal-header-dark { border-bottom-color: rgba(255,255,255,0.1); }
        .modal-body { flex: 1; overflow: auto; padding: 40px; display: flex; justify-content: center; }
        .modal-body-dark { background: transparent; align-items: center; }
        
        #docx-container { width: 100%; max-width: 900px; }
        .docx-wrapper { background: white !important; padding: 60px !important; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        #book-viewport { transition: transform 0.2s ease-out; transform-origin: center center; display: flex; align-items: center; justify-content: center; }
        .page { background-color: white; overflow: hidden; position: relative; }
        .page-footer { position: absolute; bottom: 12px; width: 100%; text-align: center; color: #86868B; font-size: 11px; font-weight: 600; opacity: 0.8; }
        
        .pdf-btn { background: rgba(255,100,255,0.1); color: white; border: none; padding: 8px 16px; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; backdrop-filter: blur(10px); }
        .pdf-btn:hover { background: rgba(255,255,255,0.2); }

        #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); color: var(--apple-slate); display: none; align-items: center; justify-content: center; z-index: 3000; flex-direction: column; gap: 15px; }
        .file-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; background: #F1F5F9; color: var(--apple-blue); }
        
        .upload-drop-zone { border: 2px dashed #E2E8F0; border-radius: 20px; padding: 40px; text-align: center; background: #F8FAFC; cursor: pointer; transition: all 0.3s; }
        .upload-drop-zone:hover { border-color: var(--apple-blue); background: #EFF6FF; }
        
        .doc-list-item { background: white; border-radius: 16px; padding: 16px 20px; display: flex; align-items: center; gap: 20px; border: 1px solid rgba(0,0,0,0.03); transition: all 0.2s; }
        .doc-list-item:hover { transform: scale(1.01); box-shadow: 0 10px 20px rgba(0,0,0,0.04); border-color: var(--apple-blue); }
        
        .view-btn { padding: 0; width: 36px; height: 36px; border: none; background: #F1F5F9; border-radius: 10px; cursor: pointer; color: var(--apple-gray); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .view-btn.active { color: var(--apple-blue); background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        .persistent-drop-zone { margin-top: 30px; border: 2px dashed #E2E8F0; border-radius: 24px; padding: 40px; background: rgba(255,255,255,0.5); text-align: center; transition: all 0.3s; }
        .persistent-drop-zone:hover { border-color: var(--apple-blue); background: white; }

        .cat-tree-container { padding: 0; user-select: none; }
        .cat-tree-item { 
            display: flex; 
            align-items: center; 
            padding: 8px 12px; 
            margin: 2px 0; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500; 
            color: #475569; 
            transition: all 0.2s ease; 
            position: relative;
            cursor: pointer;
        }
        .cat-tree-item:hover { background-color: #F1F5F9; color: #1E293B; }
        .cat-tree-item.active { background-color: #007AFF !important; color: #FFFFFF !important; }
        
        .cat-folder-icon { color: #EAB308; margin-right: 8px; font-size: 15px; flex-shrink: 0; }
        .cat-tree-item.active .cat-folder-icon { color: #FFFFFF !important; }
        
        .tree-toggle { 
            width: 16px; 
            height: 16px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #94A3B8; 
            transition: transform 0.2s; 
            margin-right: 4px; 
            font-size: 10px; 
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
        }
        .cat-tree-item.active .tree-toggle { color: #FFFFFF; }
        
        .cat-sub-tree { 
            padding-left: 20px !important; 
            position: relative; 
            margin-left: 10px !important; 
            border-left: 1px solid #CBD5E1; 
            list-style: none;
        }
        
        .cat-tree-item::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            width: 10px;
            height: 1px;
            background-color: #CBD5E1;
            display: none;
        }
        .cat-sub-tree .cat-tree-item::before { display: block; }

        .btn-add-inline, .btn-setup-inline {
            opacity: 0;
            padding: 2px 6px;
            border-radius: 4px;
            background: white;
            color: #007AFF;
            border: 1px solid #E2E8F0;
            margin-left: 4px;
            transition: opacity 0.2s;
            font-size: 10px;
            font-weight: 800;
        }
        .btn-setup-inline {
            color: #64748B;
            margin-left: auto;
        }
        .cat-tree-item:hover .btn-add-inline, .cat-tree-item:hover .btn-setup-inline { opacity: 1; }
        .cat-tree-item.active .btn-add-inline, .cat-tree-item.active .btn-setup-inline { background: rgba(255,255,255,0.2); color: white; border: none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php $page_title = 'Tài liệu & Văn bản'; include __DIR__ . '/../includes/topbar.php'; ?>
            <div class="content-wrapper" style="display:flex; gap:24px; align-items: flex-start;">
                <!-- Sidebar Danh mục -->
                <div style="width: 250px; background: white; border-radius: 16px; padding: 20px; border: 1px solid #E2E8F0; position: sticky; top: 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
                        <h3 style="margin:0; font-size:15px; font-weight:800; color:#0F172A;">Danh mục</h3>
                        <?php if ($role === 'admin'): ?>
                        <div style="display:flex; gap:5px;">
                            <button onclick="showQuickAddCat(event, null)" style="border:none; background:#EFF6FF; cursor:pointer; color:#007AFF; width:24px; height:24px; border-radius:6px; display:flex; align-items:center; justify-content:center;" title="Thêm danh mục gốc">
                                <i class="fas fa-plus" style="font-size:12px;"></i>
                            </button>
                            <?php 
                            $curr_cat_id = $_GET['cat_id'] ?? null;
                            $curr_cat_data = null;
                            if ($curr_cat_id) {
                                foreach ($all_cats as $ac) {
                                    if ((int)$ac['id'] === (int)$curr_cat_id) { $curr_cat_data = $ac; break; }
                                }
                            }
                            ?>
                            <button onclick="<?= $curr_cat_data ? 'showCatQuickSetup('.$curr_cat_data['id'].', \''.addslashes($curr_cat_data['name']).'\', \''.addslashes($curr_cat_data['allowed_roles'] ?? '').'\', \''.addslashes($curr_cat_data['allowed_levels'] ?? '').'\')' : 'showCatModal()' ?>" style="border:none; background:#F8FAFC; border:1px solid #E2E8F0; cursor:pointer; color:#64748B; width:24px; height:24px; border-radius:6px; display:flex; align-items:center; justify-content:center;" title="Cài đặt danh mục">
                                <i class="fas fa-cog" style="font-size:12px;"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <ul class="cat-tree-container" style="list-style:none; padding:0; margin:0;">
                        <li>
                            <a href="/documents" class="cat-tree-item <?= !isset($_GET['cat_id']) ? 'active' : '' ?>">
                                <div style="width:20px;"></div>
                                <i class="fas fa-folder-open cat-folder-icon" style="color:#64748B;"></i>
                                <span style="font-weight:700;">Tất cả tài liệu</span>
                            </a>
                        </li>
                        <?= buildCategoryTreeLinks($categories, null, $_GET['cat_id'] ?? null) ?>
                    </ul>
                </div>
                
                <div style="flex: 1; position: relative;" id="mainContentArea">
                    <!-- Breadcrumbs -->
                    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 14px; color: #64748B; background: white; padding: 10px 20px; border-radius: 12px; border: 1px solid #E2E8F0;">
                        <a href="/documents" style="text-decoration: none; color: <?= !isset($_GET['cat_id']) ? '#0F172A; font-weight: 700;' : '#64748B;' ?>"><i class="fas fa-home" style="margin-right:4px;"></i> Tất cả</a>
                        <?php foreach ($breadcrumb_path as $p): ?>
                            <i class="fas fa-chevron-right" style="font-size: 10px; opacity: 0.3;"></i>
                            <a href="?cat_id=<?= $p['id'] ?>" style="text-decoration: none; color: <?= (int)($_GET['cat_id'] ?? 0) === (int)$p['id'] ? '#0F172A; font-weight: 700;' : '#64748B;' ?>"><?= htmlspecialchars($p['name']) ?></a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Search & Sort Bar -->
                    <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                        <div style="flex: 1; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94A3B8;"></i>
                            <form action="" method="GET" style="margin: 0;">
                                <?php if (isset($_GET['cat_id'])): ?>
                                    <input type="hidden" name="cat_id" value="<?= $_GET['cat_id'] ?>">
                                <?php endif; ?>
                                <?php if (isset($_GET['sort'])): ?>
                                    <input type="hidden" name="sort" value="<?= $_GET['sort'] ?>">
                                <?php endif; ?>
                                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Tìm kiếm tài liệu, mô tả..." style="width: 100%; padding: 12px 12px 12px 45px; border-radius: 12px; border: 1px solid #E2E8F0; font-size: 14px; outline: none; transition: all 0.2s;" onfocus="this.style.borderColor='#007AFF'; this.style.boxShadow='0 0 0 4px rgba(0,122,255,0.05)'" onblur="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'">
                            </form>
                        </div>
                        <div style="width: 220px;">
                            <select onchange="location.href=this.value" style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #E2E8F0; font-size: 14px; outline: none; background: white; cursor: pointer; color: #475569; font-weight: 600;">
                                <?php 
                                $uri_q = !empty($search_q) ? "&search=" . urlencode($search_q) : "";
                                $uri_c = isset($_GET['cat_id']) ? "&cat_id=" . $_GET['cat_id'] : "";
                                ?>
                                <option value="?sort=newest<?= $uri_q . $uri_c ?>" <?= ($sort_by === 'newest' ? 'selected' : '') ?>>📅 Mới nhất</option>
                                <option value="?sort=oldest<?= $uri_q . $uri_c ?>" <?= ($sort_by === 'oldest' ? 'selected' : '') ?>>📅 Cũ nhất</option>
                                <option value="?sort=name_asc<?= $uri_q . $uri_c ?>" <?= ($sort_by === 'name_asc' ? 'selected' : '') ?>>🔤 Tên A-Z</option>
                                <option value="?sort=name_desc<?= $uri_q . $uri_c ?>" <?= ($sort_by === 'name_desc' ? 'selected' : '') ?>>🔤 Tên Z-A</option>
                                <option value="?sort=size_desc<?= $uri_q . $uri_c ?>" <?= ($sort_by === 'size_desc' ? 'selected' : '') ?>>⚖️ Dung lượng lớn</option>
                                <option value="?sort=size_asc<?= $uri_q . $uri_c ?>" <?= ($sort_by === 'size_asc' ? 'selected' : '') ?>>⚖️ Dung lượng nhỏ</option>
                            </select>
                        </div>
                    </div>

                    <!-- Toolbar: Always visible for view switching and upload -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 12px 16px; border-radius: 16px; border: 1px solid #E2E8F0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        <div style="display: flex; background: #F1F5F9; padding: 4px; border-radius: 10px; gap: 4px;">
                            <button id="viewGrid" onclick="switchView('grid')" class="view-btn active" style="padding: 8px; border: none; background: transparent; border-radius: 8px; cursor: pointer; color: #64748B;" title="Dạng lưới">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            </button>
                            <button id="viewList" onclick="switchView('list')" class="view-btn" style="padding: 8px; border: none; background: transparent; border-radius: 8px; cursor: pointer; color: #64748B;" title="Dạng danh sách">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                            </button>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <?php if ($role === 'admin'): ?>
                            <button onclick="showManageTagsModal()" class="btn-apple-sec" style="margin:0; background:#F8FAFC; border:1px solid #E2E8F0; padding:8px 16px; border-radius:10px; font-weight:700; color:#475569; display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                Quản lý nhãn
                            </button>
                            <button onclick="createNewTagPrompt()" class="btn-apple-sec" style="margin:0; background:#F8FAFC; border:1px solid #E2E8F0; padding:8px 16px; border-radius:10px; font-weight:700; color:#475569; display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01"/></svg>
                                Tạo nhãn
                            </button>
                            <button class="btn-apple" onclick="showUploadModal()" style="margin:0;"><span>+ Tải lên tài liệu</span></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(empty($all_docs) && empty($direct_sub_cats)): ?>
                        <div style="background: white; border-radius: 24px; padding: 60px 40px; text-align: center; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
                            <div style="width: 80px; height: 80px; background: #F8FAFC; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                                <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                            </div>
                            <h3 style="margin: 0 0 12px; font-weight: 800; font-size: 20px; color: #0F172A;">Chưa có dữ liệu nào</h3>
                            <p style="margin: 0 auto 32px; font-size: 15px; color: #64748B; max-width: 400px; line-height: 1.6;">
                                Danh mục <span style="font-weight: 700; color: #007AFF;">"<?= htmlspecialchars($current_cat_name) ?>"</span> hiện chưa có thư mục hoặc tài liệu nào.
                            </p>
                            <?php if ($role === 'admin'): ?>
                            <div style="display:flex; gap:12px; justify-content:center;">
                                <button class="btn-apple" onclick="showQuickAddCat(event, <?= $active_cat_id_raw ?: 'null' ?>)" style="background:#F1F5F9 !important; color:#475569 !important;">+ Tạo thư mục mới</button>
                                <button class="btn-apple" onclick="showUploadModal()">+ Tải lên tài liệu</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Folders Section -->
                        <?php if(!empty($direct_sub_cats)): ?>
                            <div id="folderGridHeader" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <h4 style="margin: 0; font-size: 13px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Thư mục</h4>
                                <div style="flex:1; height:1px; background:#F1F5F9;"></div>
                            </div>
                            <div id="folderGridContainer" class="folder-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 30px;">
                                <?php foreach ($direct_sub_cats as $sub): ?>
                                    <div style="position:relative; group">
                                        <a href="?cat_id=<?= $sub['id'] ?>" style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: white; border: 1px solid #E2E8F0; border-radius: 12px; text-decoration: none; color: #1E293B; transition: all 0.2s;" onmouseover="this.style.borderColor='#007AFF'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'" onmouseout="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'">
                                            <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <i class="fas fa-folder" style="color: #007AFF; font-size: 16px;"></i>
                                            </div>
                                            <span style="font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;"><?= htmlspecialchars($sub['name']) ?></span>
                                        </a>
                                        <?php if ($role === 'admin'): ?>
                                        <div style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display:flex; gap:2px;">
                                             <button onclick="event.preventDefault(); event.stopPropagation(); showCatQuickSetup(<?= $sub['id'] ?>, '<?= addslashes($sub['name']) ?>', '<?= addslashes($sub['allowed_roles'] ?? '') ?>', '<?= addslashes($sub['allowed_levels'] ?? '') ?>')" style="background:none; border:none; color:#94A3B8; cursor:pointer; padding:4px;" title="Phân quyền"><i class="fas fa-shield-halved" style="font-size:11px;"></i></button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Documents Section -->
                        <?php if(!empty($all_docs)): ?>
                            <div id="docGridHeader" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <h4 style="margin: 0; font-size: 13px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Tài liệu</h4>
                                <div style="flex:1; height:1px; background:#F1F5F9;"></div>
                            </div>
                            <?php endif; ?>

                        <div id="docGridContainer" class="doc-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                            <?php foreach($all_docs as $row): 
                                $ext = strtolower($row['file_type']); 
                                $safe_title = htmlspecialchars($row['title'], ENT_QUOTES);
                                $b64_title = base64_encode($row['title']);
                                $b64_path = base64_encode($row['file_path']);
                            ?>
                                <div class="doc-card" style="padding: 20px;">
                                    <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 16px;">
                                        <div class="file-icon" style="background:#F8FAFC; color:#007AFF; border: 1px solid #E2E8F0;"><?= $ext ?></div>
                                        <div style="flex: 1; overflow: hidden;">
                                            <h3 style="font-size: 15px; font-weight: 700; color: #1E293B; margin: 0; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= $safe_title ?>">
                                                <?= $safe_title ?>
                                                <?= renderDocLabel($row) ?>
                                            </h3>
                                            <p style="font-size: 12px; color: #64748B; margin-top: 6px; margin-bottom: 0; display:flex; justify-content:space-between;">
                                                <span><?= date('d/m/Y', strtotime($row['created_at'])) ?></span>
                                                <span><?= round($row['file_size']/1024/1024, 2) ?> MB</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px; margin-top: 20px;">
                                        <button onclick="handleView(atob('<?= $b64_path ?>'), atob('<?= $b64_title ?>'), '<?= $ext ?>')" class="btn-apple-sec" style="flex: 1; font-size: 13px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            Xem
                                        </button>
                                        <a href="<?= $row['file_path'] ?>" download class="btn-apple-sec" style="width: 36px; height: 36px; padding: 0; flex-shrink: 0; border-radius: 10px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        </a>
                                        <?php if($role === 'admin'): ?>
                                        <button onclick="showMoveModal(<?= $row['id'] ?>, atob('<?= $b64_title ?>'), '<?= $row['label'] ?>', '<?= $row['tag_ids'] ?>')" class="btn-apple-sec" style="width: 36px; height: 36px; padding: 0; flex-shrink: 0; border-radius: 10px;" title="Di chuyển">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 5L6 9H2V15H6L11 19V5Z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                                        </button>
                                        <button onclick="confirmDelete(<?= $row['id'] ?>, atob('<?= $b64_title ?>'))" class="btn-apple-sec" style="width: 36px; height: 36px; padding: 0; flex-shrink: 0; border-radius: 10px; color: #EF4444;" title="Xóa">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="docListContainer" class="doc-list" style="display: none; flex-direction: column; gap: 12px;">
                            <div style="padding: 10px 16px; display:flex; font-size:12px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">
                                <span style="width:40px;"></span>
                                <span style="flex:1;">Tên tài liệu</span>
                                <span style="width:150px;">Người đăng</span>
                                <span style="width:100px;">Dung lượng</span>
                                <span style="width:120px;">Ngày tạo</span>
                                <span style="width:120px; text-align:right;">Thao tác</span>
                            </div>
                            
                            <?php foreach($all_docs as $row): 
                                $ext = strtolower($row['file_type']); 
                                $safe_title = htmlspecialchars($row['title'], ENT_QUOTES);
                                $b64_title = base64_encode($row['title']);
                                $b64_path = base64_encode($row['file_path']);
                            ?>
                                <div class="doc-list-item">
                                    <div class="file-icon" style="width:36px; height:36px; font-size:9px; background:#F8FAFC; border:1px solid #E2E8F0; color:#007AFF; flex-shrink:0;"><?= $ext ?></div>
                                    <div style="flex:1; overflow:hidden;">
                                        <h4 style="margin:0; font-size:14px; font-weight:600; color:#1E293B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= $safe_title ?>
                                            <?= renderDocLabel($row) ?>
                                        </h4>
                                        <p style="margin:2px 0 0; font-size:11px; color:#94A3B8;"><?= htmlspecialchars($row['file_name']) ?></p>
                                    </div>
                                    <div style="width:150px; font-size:13px; color:#475569;"><?= htmlspecialchars($row['uploader'] ?? 'Hệ thống') ?></div>
                                    <div style="width:100px; font-size:13px; color:#475569;"><?= round($row['file_size']/1024/1024, 2) ?> MB</div>
                                    <div style="width:120px; font-size:13px; color:#475569;"><?= date('d/m/Y', strtotime($row['created_at'])) ?></div>
                                    <div style="width:120px; display:flex; justify-content:flex-end; gap:8px;">
                                        <button onclick="handleView(atob('<?= $b64_path ?>'), atob('<?= $b64_title ?>'), '<?= $ext ?>')" class="view-btn" style="padding:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                                        <a href="<?= $row['file_path'] ?>" download class="view-btn" style="padding:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>
                                        <?php if($role === 'admin'): ?>
                                        <button onclick="showMoveModal(<?= $row['id'] ?>, atob('<?= $b64_title ?>'), '<?= $row['label'] ?>', '<?= $row['tag_ids'] ?>')" class="view-btn" style="padding:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 5L6 9H2V15H6L11 19V5Z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg></button>
                                        <button onclick="confirmDelete(<?= $row['id'] ?>, atob('<?= $b64_title ?>'))" class="view-btn" style="padding:6px; color: #EF4444;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if($role === 'admin'): ?>
                            <div class="persistent-drop-zone" id="bottomDropZone" style="padding: 20px; margin-top: 30px; border-style: dotted;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:#007AFF;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span style="font-weight: 600; font-size: 14px;">Kéo thêm tài liệu vào đây để tải lên nhanh</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pagination UI -->
                        <?php if ($total_pages > 1): ?>
                            <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 40px; padding: 20px 0;">
                                <?php 
                                $base_url = "?";
                                if (isset($_GET['cat_id'])) $base_url .= "cat_id=" . $_GET['cat_id'] . "&";
                                if (isset($_GET['search'])) $base_url .= "search=" . urlencode($_GET['search']) . "&";
                                if (isset($_GET['sort'])) $base_url .= "sort=" . $_GET['sort'] . "&";
                                ?>
                                
                                <?php if ($current_page > 1): ?>
                                    <a href="<?= $base_url ?>page=<?= $current_page - 1 ?>" style="padding: 10px 18px; background: white; border: 1px solid #E2E8F0; border-radius: 12px; color: #475569; text-decoration: none; font-weight: 600; transition: all 0.2s; font-size: 14px;" onmouseover="this.style.borderColor='#007AFF'; this.style.color='#007AFF'" onmouseout="this.style.borderColor='#E2E8F0'; this.style.color='#475569'">« Trước</a>
                                <?php endif; ?>

                                <?php 
                                $start_btn = max(1, $current_page - 2);
                                $end_btn = min($total_pages, $current_page + 2);
                                
                                for ($i = $start_btn; $i <= $end_btn; $i++): 
                                ?>
                                    <a href="<?= $base_url ?>page=<?= $i ?>" style="width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; transition: all 0.2s; <?= $i === $current_page ? 'background: #007AFF; color: white; border: 1px solid #007AFF; box-shadow: 0 4px 12px rgba(0,122,255,0.2);' : 'background: white; border: 1px solid #E2E8F0; color: #475569;' ?>" <?= $i !== $current_page ? 'onmouseover="this.style.borderColor=\'#007AFF\'; this.style.color=\'#007AFF\'" onmouseout="this.style.borderColor=\'#E2E8F0\'; this.style.color=\'#475569\'"' : '' ?>>
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?= $base_url ?>page=<?= $current_page + 1 ?>" style="padding: 10px 18px; background: white; border: 1px solid #E2E8F0; border-radius: 12px; color: #475569; text-decoration: none; font-weight: 600; transition: all 0.2s; font-size: 14px;" onmouseover="this.style.borderColor='#007AFF'; this.style.color='#007AFF'" onmouseout="this.style.borderColor='#E2E8F0'; this.style.color='#475569'">Tiếp »</a>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; font-size: 13px; color: #94A3B8; margin-bottom: 20px;">
                                Đang hiển thị trang <?= $current_page ?> / <?= $total_pages ?> (Tổng cộng <?= $total_docs ?> tài liệu)
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- PDF/Book Mode -->
    <div id="pdfModal" class="modal-fullscreen modal-dark">
        <div class="modal-header modal-header-dark">
            <h3 id="book-title" style="margin:0; font-size: 14px; color:#aaa;">Tài liệu</h3>
            <div style="display:flex; align-items:center; gap:20px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size: 11px; color:#888; text-transform:uppercase;">Layout:</span>
                    <select id="pdf-layout" onchange="applyPdfLayout()" style="background:#333; color:white; border:1px solid #555; border-radius:6px; padding:4px 8px; font-size:12px; cursor:pointer;">
                        <option value="portrait">Dọc (A4 Book)</option>
                        <option value="landscape">Ngang (Landscape Book)</option>
                        <option value="single">Một trang (Single)</option>
                    </select>
                </div>
                <div style="display:flex; align-items:center; gap:8px; border-left:1px solid #444; padding-left:20px;">
                    <button onclick="zoomOut()" class="pdf-btn">➖</button>
                    <span id="zoom-val" style="font-size: 13px; font-weight:700; min-width:40px; text-align:center;">100%</span>
                    <button onclick="zoomIn()" class="pdf-btn">➕</button>
                </div>
                <div style="display:flex; align-items:center; gap:15px; border-left:1px solid #444; padding-left:20px;">
                    <button onclick="pageFlip?pageFlip.flipPrev():null" class="pdf-btn">◀ Pre</button>
                    <span class="page-info">Trang <span id="current-page">1</span> / <span id="total-pages">-</span></span>
                    <button onclick="pageFlip?pageFlip.flipNext():null" class="pdf-btn">Next ▶</button>
                </div>
            </div>
            <button onclick="closeBook()" style="background:#dc2626; color:white; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-weight:600;">Đóng</button>
        </div>
        <div class="modal-body modal-body-dark"><div id="book-viewport"><div id="book-container"></div></div></div>
    </div>

    <!-- Docx Mode -->
    <div id="docxModal" class="modal-fullscreen">
        <div class="modal-header">
            <h3 id="docx-title" style="margin:0; font-size: 15px; color:#1f2937;">Văn bản</h3>
            <button onclick="closeDocx()" style="background:#dc2626; color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-weight:700;">Đóng Trình Xem</button>
        </div>
        <div class="modal-body"><div id="docx-container"></div></div>
    </div>

    <!-- Upload/Loading remain same... -->
    <div id="uploadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div style="background:#fff; width:480px; border-radius:24px; padding:40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <h2 style="margin:0 0 25px; font-weight:800; font-size: 22px; color: #0F172A;">Tải lên tài liệu mới</h2>
            <form id="uploadForm" onsubmit="handleAjaxUpload(event)" enctype="multipart/form-data">
                
                <div class="upload-drop-zone" id="dropZone">
                    <div class="upload-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div style="font-weight: 600; color: #334155; font-size: 14px;">Kéo thả file vào đây hoặc nhấn để chọn</div>
                    <div style="font-size: 12px; color: #94A3B8; margin-top: 5px;">Hỗ trợ .pdf, .doc, .docx (Tối đa <span id="maxSizeText"><?= $max_size_mb ?>MB</span>)</div>
                    <input type="file" name="doc_file" id="doc_file" accept=".pdf,.doc,.docx" required onchange="handleFileSelect(this)">
                </div>
                <input type="hidden" name="ajax" value="1">
                
                <div id="filePreview" class="file-preview">
                    <div style="display:flex; align-items:center; gap:12px; overflow: hidden;">
                        <div class="file-icon" style="width: 36px; height: 36px; font-size: 9px; background:#E2E8F0; margin: 0; color:#475569;" id="previewIcon">DOC</div>
                        <div class="file-preview-info">
                            <div class="file-name" id="previewFileName" title="">filename.pdf</div>
                            <div class="file-size" id="previewFileSize">1.2 MB</div>
                        </div>
                    </div>
                    <div class="remove-file" onclick="clearFileSelection()" title="Xóa file này">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </div>
                </div>
                
                <div id="fileErrorMsg" class="file-error-msg"></div>

                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569;">Thuộc danh mục</label>
                    <select name="category_id" id="upload_category" style="width:100%; padding:14px; border:1px solid #E2E8F0; border-radius:12px; font-size: 14px; font-family: inherit; transition: all 0.2s; background: #fff; appearance: auto;" onfocus="this.style.borderColor='#007AFF'; this.style.outline='none'" onblur="this.style.borderColor='#E2E8F0'">
                        <option value="">-- Không chọn (Mặc định) --</option>
                        <?= buildCategoryOptions($categories) ?>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #475569;">Gán nhãn (Nhiều nhãn)</label>
                    <div style="background:#fff;">
                        <select id="upload-tags" name="tags[]" multiple placeholder="Chọn hoặc tạo nhãn mới..." autocomplete="off">
                            <?php foreach($available_tags as $tag): ?>
                                <option value="<?= $tag['id'] ?>"><?= $tag['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569;">Tiêu đề tài liệu</label>
                    <input type="text" name="title" id="docTitle" placeholder="Nhập tiêu đề hiển thị" required style="width:100%; padding:14px; border:1px solid #E2E8F0; border-radius:12px; font-size: 14px; font-family: inherit; transition: all 0.2s;" onfocus="this.style.borderColor='#007AFF'; this.style.outline='none'" onblur="this.style.borderColor='#E2E8F0'">
                </div>
                
                <div style="margin-bottom: 25px;">
                    <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569;">Mô tả thêm (Tùy chọn)</label>
                    <textarea name="description" id="docDesc" placeholder="Nhập tóm tắt nội dung..." style="width:100%; padding:14px; border:1px solid #E2E8F0; border-radius:12px; height:90px; resize:none; font-size: 14px; font-family: inherit; transition: all 0.2s;" onfocus="this.style.borderColor='#007AFF'; this.style.outline='none'" onblur="this.style.borderColor='#E2E8F0'"></textarea>
                </div>
                
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="hideUploadModal()" class="btn-apple-sec" style="flex:1; padding: 12px; border-radius: 12px; background: #F1F5F9; color: #475569; justify-content: center;">Hủy bỏ</button>
                    <button type="submit" id="submitUploadBtn" class="btn-apple" style="flex:2; padding: 12px; border-radius: 12px; background: #007AFF; box-shadow: 0 4px 12px rgba(0,122,255,0.3);">Tải lên ngay</button>
                </div>
            </form>
        </div>
    </div>
    <div id="loading-overlay"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;"></div><div style="margin-top:15px; font-weight:600;">Đang xử lý tài liệu...</div></div>

    <div id="catModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div style="background:#fff; width:520px; border-radius:24px; padding:35px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h2 style="margin:0; font-weight:800; font-size: 20px; color: #0F172A;" id="setupCatName">Quản lý Danh mục</h2>
                    <p style="margin:4px 0 0; font-size:12px; color:#64748B;">Thiết lập quyền truy cập cho danh mục này</p>
                </div>
                <button onclick="hideCatModal()" style="background:none; border:none; cursor:pointer; font-size:32px; font-weight:300; line-height:1; color:#94A3B8;">&times;</button>
            </div>
            
            <input type="hidden" id="setupCatId">
            <div style="background: #F8FAFC; padding: 20px; border-radius: 16px; border: 1px solid #E2E8F0; margin-bottom:25px;">
                <div style="display:flex; flex-direction:column; gap:15px;">
                    <div>
                        <span style="font-size:13px; font-weight:700; color:#1E293B; display:block; margin-bottom:10px;">Vai trò hệ thống (Roles):</span>
                        <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                            <?php foreach(['admin', 'manager', 'user'] as $r): ?>
                                <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;"><input type="checkbox" name="setup_roles" value="<?= $r ?>" class="perm-check"> <?= ucfirst($r) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="border-top:1px dashed #E2E8F0; pt:15px; margin-top:5px;">
                        <span style="font-size:13px; font-weight:700; color:#1E293B; display:block; margin-bottom:10px; margin-top:10px;">Cấp bậc nhân sự (Levels):</span>
                        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                            <?php foreach(['Member', 'Leader', 'Manager', 'Director', 'C-Level'] as $l): ?>
                                <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;"><input type="checkbox" name="setup_levels" value="<?= $l ?>" class="perm-check"> <?= $l ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button onclick="hideCatModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#F1F5F9; font-weight:600; cursor:pointer;">Hủy bỏ</button>
                <button onclick="saveCatQuickPerms()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#007AFF; color:white; font-weight:600; cursor:pointer;">Lưu thay đổi</button>
            </div>
            
            <?php if($role === 'admin'): ?>
            <div style="margin-top:25px; pt:20px; border-top:1px solid #F1F5F9;">
                <button onclick="deleteCategory(document.getElementById('setupCatId').value)" style="background:none; border:none; color:#EF4444; font-size:12px; cursor:pointer; text-decoration:underline;">Xóa danh mục này vĩnh viễn</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Add Category Modal -->
    <div id="quickAddCatModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1200; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div style="background:#fff; width:450px; border-radius:24px; padding:30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; font-size:18px; font-weight:800;" id="quickAddCatTitle">Thêm danh mục</h3>
                <button onclick="hideQuickAddCat()" style="background:none; border:none; cursor:pointer; font-size:28px; color:#94A3B8;">&times;</button>
            </div>
            
            <input type="hidden" id="quickAddParentId">
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px; color:#475569;">Tên danh mục mới:</label>
                <input type="text" id="newCatName" placeholder="Ví dụ: Tài liệu kỹ thuật, Hợp đồng..." style="width:100%; padding:12px; border:1px solid #E2E8F0; border-radius:12px; font-size:14px;">
            </div>

            <div style="display:flex; gap:12px;">
                <button onclick="hideQuickAddCat()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#F1F5F9; font-weight:600; cursor:pointer;">Hủy bỏ</button>
                <button onclick="saveNewCategory()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#007AFF; color:white; font-weight:600; cursor:pointer;">Tạo ngay</button>
            </div>
        </div>
    </div>

    <!-- Move Document Modal -->
    <div id="moveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1100; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div style="background:#fff; width:400px; border-radius:24px; padding:30px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <h3 style="margin:0 0 20px; font-size:18px; font-weight:800;">Di chuyển tài liệu</h3>
            <p id="moveDocTitle" style="font-size:14px; color:#64748B; margin-bottom:20px; font-style:italic;"></p>
            <input type="hidden" id="moveDocId">
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px; color:#475569;">Gán nhãn (Nhiều nhãn):</label>
                <div style="background:#fff;">
                    <select id="move-tags" name="tags[]" multiple placeholder="Chọn hoặc tạo nhãn mới..." autocomplete="off">
                        <?php foreach($available_tags as $tag): ?>
                            <option value="<?= $tag['id'] ?>"><?= $tag['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:25px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:8px;">Chọn danh mục mới:</label>
                <select id="moveDestId" style="width:100%; padding:12px; border:1px solid #E2E8F0; border-radius:10px; background:#F8FAFC;">
                    <option value="">-- Mặc định (Không danh mục) --</option>
                    <?= buildCategoryOptions($categories) ?>
                </select>
            </div>
            <div style="display:flex; gap:12px;">
                <button onclick="hideMoveModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#F1F5F9; font-weight:600; cursor:pointer;">Hủy</button>
                <button onclick="confirmMove()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#007AFF; color:white; font-weight:600; cursor:pointer;">Di chuyển</button>
            </div>
        </div>
    </div>

    <!-- Tag Management Modal -->
    <div id="manageTagsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1200; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div style="background:#fff; width:500px; border-radius:24px; padding:35px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0; font-weight:800; font-size: 20px; color: #0F172A;">Quản lý Nhãn tài liệu</h2>
                <button onclick="hideManageTagsModal()" style="background:none; border:none; cursor:pointer; font-size:32px; font-weight:300; color:#94A3B8;">&times;</button>
            </div>
            
            <div style="max-height:400px; overflow-y:auto; padding-right:10px;">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php if(empty($available_tags)): ?>
                        <p style="text-align:center; color:#64748B; font-size:14px; padding:20px;">Chưa có nhãn nào được tạo.</p>
                    <?php else: ?>
                        <?php foreach($available_tags as $tag): 
                            $base_color = $tag['color'] ?: '#64748B';
                            $bg = $tag['bg_color'] ?: ($base_color . '15');
                            $border = $tag['border_color'] ?: ($base_color . '30');
                            $text = $tag['text_color'] ?: $base_color;
                            $icon = $tag['icon'] ?: 'fa-solid fa-tag';
                        ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="display:inline-block; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:700; background:<?= $bg ?>; color:<?= $text ?>; border:1px solid <?= $border ?>; text-transform:uppercase; letter-spacing:0.5px;">
                                        <i class="<?= $icon ?>" style="margin-right:5px;"></i><?= htmlspecialchars($tag['name']) ?>
                                    </span>
                                </div>
                                <button onclick="deleteTag(<?= $tag['id'] ?>, '<?= addslashes($tag['name']) ?>')" style="background:#FEF2F2; color:#EF4444; border:1px solid #FEE2E2; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='#FEF2F2'">Xóa</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex; gap:12px; margin-top:25px; border-top:1px solid #F1F5F9; pt:20px;">
                <button onclick="hideManageTagsModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#F1F5F9; font-weight:600; cursor:pointer;">Đóng</button>
                <button onclick="hideManageTagsModal(); createNewTagPrompt();" style="flex:1; padding:12px; border:none; border-radius:10px; background:#007AFF; color:white; font-weight:600; cursor:pointer;">+ Thêm nhãn mới</button>
            </div>
        </div>
    </div>
    <div id="tagModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1200; align-items:center; justify-content:center; backdrop-filter: blur(5px);">
        <div style="background:#fff; width:450px; border-radius:24px; padding:35px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0; font-weight:800; font-size: 20px; color: #0F172A;">Thiết lập nhãn mới</h2>
                <button onclick="hideTagModal()" style="background:none; border:none; cursor:pointer; font-size:32px; font-weight:300; color:#94A3B8;">&times;</button>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:15px;">
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Tên nhãn (Ví dụ: Hot, New, Internal)</label>
                    <input type="text" id="tag-name" placeholder="Tên nhãn..." style="width:100%; padding:12px; border:1px solid #E2E8F0; border-radius:10px;">
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Màu chữ</label>
                        <input type="color" id="tag-text-color" value="#1e293b" style="width:100%; height:40px; border:1px solid #E2E8F0; border-radius:8px; padding:2px; cursor:pointer;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Màu nền</label>
                        <input type="color" id="tag-bg-color" value="#f1f5f9" style="width:100%; height:40px; border:1px solid #E2E8F0; border-radius:8px; padding:2px; cursor:pointer;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Màu viền</label>
                        <input type="color" id="tag-border-color" value="#e2e8f0" style="width:100%; height:40px; border:1px solid #E2E8F0; border-radius:8px; padding:2px; cursor:pointer;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Chọn Icon (FontAwesome)</label>
                        <div style="background:#f8fafc; border:1px solid #E2E8F0; border-radius:12px; padding:15px;">
                            <input type="text" id="tag-icon-search" placeholder="Tìm icon (ví dụ: star, lock...)" style="width:100%; padding:8px 12px; border:1px solid #E2E8F0; border-radius:8px; font-size:12px; margin-bottom:12px;" oninput="filterIcons(this.value)">
                            <div id="icon-picker-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); gap:8px; max-height:150px; overflow-y:auto; padding:5px;">
                                <!-- Icons will be loaded here via JS -->
                            </div>
                            <input type="hidden" id="tag-icon" value="fa-solid fa-tag">
                        </div>
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:10px;">Xem trước:</label>
                    <div id="tag-preview" style="padding:15px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1; text-align:center;">
                        <span id="preview-badge" style="display:inline-block; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:700; border:1px solid #e2e8f0; text-transform:uppercase; letter-spacing:0.5px;">
                            <i id="preview-icon" class="fa-solid fa-tag" style="margin-right:5px;"></i>
                            <span id="preview-text">MẪU NHÃN</span>
                        </span>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:12px; margin-top:25px;">
                <button onclick="hideTagModal()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#F1F5F9; font-weight:600; cursor:pointer;">Hủy bỏ</button>
                <button onclick="saveNewTag()" style="flex:1; padding:12px; border:none; border-radius:10px; background:#007AFF; color:white; font-weight:600; cursor:pointer;">Tạo nhãn ngay</button>
            </div>
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
        let pageFlip = null;
        let currentScale = 1.0;
        let tsUploadTags = null;
        let tsMoveTags = null;

        function showCatModal() { document.getElementById('catModal').style.display='flex'; }
        function hideCatModal() { document.getElementById('catModal').style.display='none'; }
        
        function showQuickAddCat(e, parentId, parentName) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            document.getElementById('quickAddParentId').value = parentId || '';
            document.getElementById('quickAddCatTitle').innerText = parentName ? 'Thêm danh mục con vào "' + parentName + '"' : 'Thêm danh mục gốc';
            document.getElementById('newCatName').value = '';
            document.getElementById('quickAddCatModal').style.display = 'flex';
            setTimeout(() => document.getElementById('newCatName').focus(), 100);
        }

        function hideQuickAddCat() {
            document.getElementById('quickAddCatModal').style.display = 'none';
        }

        async function saveNewCategory() {
            const name = document.getElementById('newCatName').value;
            const parentId = document.getElementById('quickAddParentId').value;
            
            if (!name) return alert("Vui lòng nhập tên danh mục");

            const formData = new FormData();
            formData.append('action', 'add_category');
            formData.append('name', name);
            formData.append('parent_id', parentId);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
                if (res.status === 'success') window.location.reload();
                else alert("Lỗi khi tạo danh mục.");
            } catch (e) {
                alert("Đã xảy ra lỗi hệ thống.");
            }
        }

        async function deleteCategory(id) {
            if (!confirm("Bạn có chắc chắn muốn xóa danh mục này? Tất cả danh mục con cũng sẽ bị ảnh hưởng.")) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('id', id);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
                if (res.status === 'success') window.location.reload();
                else alert("Lỗi khi xóa danh mục.");
            } catch (e) {
                alert("Đã xảy ra lỗi hệ thống.");
            }
        }
        
        function showMoveModal(id, title, label, tagIds) {
            document.getElementById('moveDocId').value = id;
            document.getElementById('moveDocTitle').innerText = title;
            
            // Multi-tags handling for Tom Select
            if (tsMoveTags) {
                tsMoveTags.clear();
                if (tagIds) {
                    const ids = tagIds.toString().split(',');
                    tsMoveTags.setValue(ids);
                }
            }
            
            document.getElementById('moveModal').style.display = 'flex';
        }
        function hideMoveModal() { document.getElementById('moveModal').style.display = 'none'; }

        // Icon Picker Logic
        const commonIcons = [
            'fa-tag', 'fa-star', 'fa-fire', 'fa-shield-halved', 'fa-circle-check', 'fa-triangle-exclamation',
            'fa-lock', 'fa-eye-slash', 'fa-clock', 'fa-bookmark', 'fa-heart', 'fa-bolt', 'fa-check', 'fa-xmark',
            'fa-info-circle', 'fa-file-pdf', 'fa-file-word', 'fa-file-excel', 'fa-gem', 'fa-trophy', 'fa-flag',
            'fa-user', 'fa-ghost', 'fa-bug', 'fa-paperclip', 'fa-link', 'fa-eye', 'fa-key', 'fa-gift', 'fa-bell',
            'fa-comment', 'fa-envelope', 'fa-folder', 'fa-image', 'fa-video', 'fa-cloud', 'fa-code', 'fa-gear'
        ];

        function renderIconPicker() {
            const grid = document.getElementById('icon-picker-grid');
            grid.innerHTML = commonIcons.map(icon => `
                <div class="icon-item" onclick="selectIcon('${icon}')" data-icon="${icon}" style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; background:white; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='#007AFF'; this.style.background='#eff6ff'" onmouseout="if(this.dataset.selected !== 'true') {this.style.borderColor='#e2e8f0'; this.style.background='white';}">
                    <i class="fa-solid ${icon}" style="font-size:16px; color:#64748B;"></i>
                </div>
            `).join('');
            selectIcon('fa-tag'); // Default
        }

        function selectIcon(icon) {
            const fullIcon = 'fa-solid ' + icon;
            document.getElementById('tag-icon').value = fullIcon;
            
            // Highlight selected
            document.querySelectorAll('.icon-item').forEach(el => {
                const isSelected = el.dataset.icon === icon;
                el.dataset.selected = isSelected;
                el.style.borderColor = isSelected ? '#007AFF' : '#e2e8f0';
                el.style.background = isSelected ? '#eff6ff' : 'white';
                const i = el.querySelector('i');
                if(i) i.style.color = isSelected ? '#007AFF' : '#64748B';
            });
            
            updateTagPreview();
        }

        function filterIcons(query) {
            const q = query.toLowerCase();
            document.querySelectorAll('.icon-item').forEach(el => {
                const icon = el.dataset.icon.toLowerCase();
                el.style.display = icon.includes(q) ? 'flex' : 'none';
            });
        }

        function showManageTagsModal() { document.getElementById('manageTagsModal').style.display = 'flex'; }
        function hideManageTagsModal() { document.getElementById('manageTagsModal').style.display = 'none'; }

        async function deleteTag(id, name) {
            if (!confirm(`Bạn có chắc muốn xóa nhãn "${name}"? Thao tác này sẽ gỡ nhãn khỏi tất cả tài liệu hiện có.`)) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_tag');
            formData.append('id', id);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
                if (res.status === 'success') window.location.reload();
                else alert("Lỗi khi xóa nhãn.");
            } catch (e) {
                alert("Đã xảy ra lỗi hệ thống.");
            }
        }

        function createNewTagPrompt() {
            renderIconPicker();
            document.getElementById('tagModal').style.display = 'flex';
        }
        function hideTagModal() {
            document.getElementById('tagModal').style.display = 'none';
        }

        // Live Preview Logic
        function updateTagPreview() {
            const name = document.getElementById('tag-name').value || 'MẪU NHÃN';
            const text = document.getElementById('tag-text-color').value;
            const bg = document.getElementById('tag-bg-color').value;
            const border = document.getElementById('tag-border-color').value;
            const icon = document.getElementById('tag-icon').value;

            const badge = document.getElementById('preview-badge');
            const previewText = document.getElementById('preview-text');
            const previewIcon = document.getElementById('preview-icon');

            previewText.innerText = name;
            badge.style.color = text;
            badge.style.backgroundColor = bg;
            badge.style.borderColor = border;
            
            previewIcon.className = icon || 'fa-solid fa-tag';
            previewIcon.style.display = 'inline-block';
        }

        document.getElementById('tag-name').addEventListener('input', updateTagPreview);
        document.getElementById('tag-text-color').addEventListener('input', updateTagPreview);
        document.getElementById('tag-bg-color').addEventListener('input', updateTagPreview);
        document.getElementById('tag-border-color').addEventListener('input', updateTagPreview);

        async function saveNewTag() {
            const name = document.getElementById('tag-name').value;
            if (!name) return alert("Vui lòng nhập tên nhãn");

            const formData = new FormData();
            formData.append('action', 'add_tag');
            formData.append('name', name);
            formData.append('text_color', document.getElementById('tag-text-color').value);
            formData.append('bg_color', document.getElementById('tag-bg-color').value);
            formData.append('border_color', document.getElementById('tag-border-color').value);
            formData.append('icon', document.getElementById('tag-icon').value);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData }).then(r => r.json());
                if (res.status === 'success') window.location.reload();
                else alert("Lỗi khi tạo nhãn. Vui lòng thử lại.");
            } catch (e) {
                alert("Đã xảy ra lỗi hệ thống.");
            }
        }

        function confirmMove() {
            const docId = document.getElementById('moveDocId').value;
            const catId = document.getElementById('moveDestId').value;
            const formData = new FormData();
            formData.append('action', 'move_document');
            formData.append('doc_id', docId);
            formData.append('cat_id', catId);
            
            if (tsMoveTags) {
                const values = tsMoveTags.getValue();
                values.forEach(val => formData.append('tags[]', val));
            }

            fetch('', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if(res.status === 'success') window.location.reload();
                    else alert('Lỗi di chuyển/cập nhật');
                });
        }

        function confirmDelete(id, title) {
            if (confirm(`Bạn có chắc chắn muốn xóa tài liệu "${title}" không? Hành động này không thể hoàn tác.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_document');
                formData.append('id', id);

                fetch('', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success') window.location.reload();
                        else alert('Lỗi khi xóa tài liệu');
                    });
            }
        }

        function showCatQuickSetup(id, name, roles, levels) {
            document.getElementById('setupCatId').value = id;
            document.getElementById('setupCatName').innerText = name;
            
            // Clear checks
            document.querySelectorAll('.perm-check').forEach(cb => cb.checked = false);
            
            // Set checks
            if(roles) roles.split(',').forEach(r => {
                const cb = document.querySelector(`input[name="setup_roles"][value="${r}"]`);
                if(cb) cb.checked = true;
            });
            if(levels) levels.split(',').forEach(l => {
                const cb = document.querySelector(`input[name="setup_levels"][value="${l}"]`);
                if(cb) cb.checked = true;
            });
            
            document.getElementById('catModal').style.display = 'flex';
        }

        async function saveCatQuickPerms() {
            const catId = document.getElementById('setupCatId').value;
            const roles = Array.from(document.querySelectorAll('input[name="setup_roles"]:checked')).map(cb => cb.value);
            const levels = Array.from(document.querySelectorAll('input[name="setup_levels"]:checked')).map(cb => cb.value);
            
            const formData = new FormData();
            formData.append('action', 'update_category_permissions');
            formData.append('cat_id', catId);
            // Changed to match the single variable handling in PHP
            formData.append('roles', roles.join(','));
            formData.append('levels', levels.join(','));
            
            try {
                const res = await fetch('', { method: 'POST', body: formData });
                const result = await res.json();
                if(result.status === 'success') {
                    window.location.reload();
                } else alert('Lỗi cập nhật');
            } catch(e) { alert('Hệ thống bận'); }
        }

        function switchView(mode) {
            const grid = document.getElementById('docGridContainer');
            const list = document.getElementById('docListContainer');
            const btnGrid = document.getElementById('viewGrid');
            const btnList = document.getElementById('viewList');
            
            if(mode === 'grid') {
                if(grid) grid.style.display = 'grid';
                if(list) list.style.display = 'none';
                if(btnGrid) btnGrid.classList.add('active');
                if(btnList) btnList.classList.remove('active');
            } else {
                if(grid) grid.style.display = 'none';
                if(list) list.style.display = 'flex';
                if(btnGrid) btnGrid.classList.remove('active');
                if(btnList) btnList.classList.add('active');
            }
            localStorage.setItem('doc_view_mode', mode);
        }

        // Initialize view mode and Global Drag & Drop
        document.addEventListener('DOMContentLoaded', () => {
            const savedMode = localStorage.getItem('doc_view_mode') || 'list';
            switchView(savedMode);

            // Initialize Tom Select for Tags
            const tsConfig = {
                plugins: ['remove_button'],
                create: true,
                persist: false,
                onItemAdd: function() {
                    this.setTextboxValue('');
                    this.refreshOptions();
                }
            };
            tsUploadTags = new TomSelect('#upload-tags', tsConfig);
            tsMoveTags = new TomSelect('#move-tags', tsConfig);

            // Add Enter key listener for new category name
            document.getElementById('newCatName').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    saveNewCategory();
                }
            });

            // Unified Drag & Drop Logic
            const fileInput = document.getElementById('doc_file');
            const dropZones = document.querySelectorAll('.persistent-drop-zone');

            dropZones.forEach(zone => {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    zone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    }, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    zone.addEventListener(eventName, () => zone.classList.add('dragover'), false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    zone.addEventListener(eventName, () => zone.classList.remove('dragover'), false);
                });

                zone.addEventListener('drop', (e) => {
                    const files = e.dataTransfer.files;
                    if (files && files.length > 0) {
                        showUploadModal();
                        fileInput.files = files;
                        handleFileSelect(fileInput);
                        
                        // Auto-select current category
                        const urlParams = new URLSearchParams(window.location.search);
                        const currentCatId = urlParams.get('cat_id');
                        if (currentCatId) {
                            const catSelect = document.querySelector('select[name="category_id"]');
                            if (catSelect) catSelect.value = currentCatId;
                        }
                    }
                });

                // Also allow clicking the zone to upload
                zone.addEventListener('click', () => {
                    fileInput.click();
                });
            });

            // Prevent global browser drop behavior
            window.addEventListener('dragover', e => e.preventDefault());
            window.addEventListener('drop', e => e.preventDefault());

            // Local DropZone Logic (Inner Modal)
            const dropZone = document.getElementById('dropZone');
            if (dropZone) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
                });
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
                });
                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
                });
                
                dropZone.addEventListener('drop', (e) => {
                    let dt = e.dataTransfer;
                    if (dt.files.length) {
                        fileInput.files = dt.files;
                        handleFileSelect(fileInput);
                    }
                }, false);
            }
        });

        async function updateCatPerm(catId) {
            // Deprecated - replaced by saveCatQuickPerms
        }

        // Configuration from PHP
        const CONFIG_MAX_BYTES = <?= $max_size_bytes ?>;
        const CONFIG_MAX_MB = <?= $max_size_mb ?>;

        function showUploadModal() { 
            const modal = document.getElementById('uploadModal');
            modal.style.display = 'flex'; 
            clearFileSelection(); 
            
            const catSelect = document.getElementById('upload_category');
            if (catSelect) {
                const urlParams = new URLSearchParams(window.location.search);
                const currentCat = urlParams.get('cat_id');
                if (currentCat) {
                    catSelect.value = currentCat;
                    catSelect.style.pointerEvents = 'none';
                    catSelect.style.background = '#F8FAFC';
                    catSelect.style.color = '#64748B';
                    catSelect.style.cursor = 'not-allowed';
                    catSelect.tabIndex = -1;
                } else {
                    catSelect.value = '';
                    catSelect.style.pointerEvents = 'auto';
                    catSelect.style.background = '#fff';
                    catSelect.style.color = 'inherit';
                    catSelect.style.cursor = 'default';
                    catSelect.tabIndex = 0;
                }
            }
        }
        function hideUploadModal() { 
            document.getElementById('uploadModal').style.display='none'; 
            clearFileSelection();
            document.getElementById('docTitle').value = '';
            document.getElementById('docDesc').value = '';
        }

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('dropZone').style.display = 'none';
                document.getElementById('filePreview').style.display = 'flex';
                
                document.getElementById('previewFileName').innerText = file.name;
                document.getElementById('previewFileName').title = file.name;
                
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                document.getElementById('previewFileSize').innerText = sizeMB + ' MB';
                
                const ext = file.name.split('.').pop().toLowerCase();
                document.getElementById('previewIcon').innerText = ext.toUpperCase().substring(0, 3);
                
                const titleInput = document.getElementById('docTitle');
                if (!titleInput.value) {
                    titleInput.value = file.name.split('.').slice(0, -1).join('.');
                }

                const errorMsg = document.getElementById('fileErrorMsg');
                const submitBtn = document.getElementById('submitUploadBtn');
                
                if (file.size > CONFIG_MAX_BYTES) {
                    errorMsg.innerText = `Cảnh báo: File có dung lượng (${sizeMB} MB) lớn hơn giới hạn của Server là ${CONFIG_MAX_MB} MB. Server có thể sẽ từ chối tự động!`;
                    errorMsg.style.display = 'block';
                    submitBtn.style.background = '#EF4444';
                    submitBtn.style.boxShadow = '0 4px 12px rgba(239,68,68,0.3)';
                } else {
                    errorMsg.style.display = 'none';
                    submitBtn.style.background = '#007AFF';
                    submitBtn.style.boxShadow = '0 4px 12px rgba(0,122,255,0.3)';
                }
            }
        }

        function clearFileSelection() {
            const fileInput = document.getElementById('doc_file');
            fileInput.value = '';
            document.getElementById('dropZone').style.display = 'block';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('fileErrorMsg').style.display = 'none';
            const submitBtn = document.getElementById('submitUploadBtn');
            submitBtn.style.background = '#007AFF';
            submitBtn.style.boxShadow = '0 4px 12px rgba(0,122,255,0.3)';
        }

        async function handleAjaxUpload(event) {
            event.preventDefault();
            const form = event.target;
            if (form.doc_file.files[0] && form.doc_file.files[0].size > CONFIG_MAX_BYTES) {
                alert(`File quá lớn! Dung lượng tối đa cho phép là ${CONFIG_MAX_MB} MB.`);
                return;
            }

            const formData = new FormData(form);
            const submitBtn = form.querySelector('[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Đang tải...';
            document.getElementById('loading-overlay').style.display = 'flex';
            document.querySelector('#loading-overlay div:nth-child(2)').innerText = 'Đang tải tài liệu lên...';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch(e) {
                    console.error("Non-JSON response:", text);
                    alert("Lỗi hệ thống: Phản hồi không hợp lệ từ máy chủ.");
                    return;
                }
                
                if (result.status === 'success') {
                    hideUploadModal();
                    form.reset();
                    window.location.reload();
                } else {
                    alert(result.message || "Lỗi khi tải lên!");
                }
            } catch (err) {
                alert("Đã xảy ra lỗi khi tải lên.");
                console.error(err);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Tải lên';
                document.getElementById('loading-overlay').style.display = 'none';
                document.querySelector('#loading-overlay div:nth-child(2)').innerText = 'Đang xử lý tài liệu...';
            }
        }

        async function handleView(path, title, ext) {
            if (ext === 'pdf') {
                openBook(path, title);
            } else if (ext === 'docx' || ext === 'doc') {
                openDocx(path, title);
            } else {
                window.open(path, '_blank');
            }
        }

        async function openDocx(path, title) {
            document.getElementById('loading-overlay').style.display = 'flex';
            document.getElementById('docx-title').innerText = title;
            const container = document.getElementById('docx-container');
            container.innerHTML = '';
            
            try {
                const response = await fetch(path);
                const blob = await response.blob();
                await docx.renderAsync(blob, container);
                document.getElementById('docxModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            } catch (err) {
                alert("Lỗi đọc văn bản: " + err.message);
            }
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function closeDocx() {
            document.getElementById('docxModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Keep existing PDF functions (openBook, closeBook, zoomIn/Out)
        // PDF View scaling and management
        function closeBook() {
            if (pageFlip) {
                try { pageFlip.destroy(); } catch(err) { console.warn("PageFlip destroy failed", err); }
                pageFlip = null;
            }
            const modal = document.getElementById('pdfModal');
            if (modal) modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function updateZoom() {
            const valEl = document.getElementById('zoom-val');
            if (valEl) valEl.innerText = Math.round(currentScale * 100) + '%';
            const viewport = document.getElementById('book-viewport');
            if (viewport) viewport.style.transform = `scale(${currentScale})`;
        }

        function zoomIn() { if (currentScale < 2) { currentScale += 0.1; updateZoom(); } }
        function zoomOut() { if (currentScale > 0.5) { currentScale -= 0.1; updateZoom(); } }

        function applyPdfLayout() {
            const layoutEl = document.getElementById('pdf-layout');
            const container = document.getElementById('book-container');
            const totalPagesEl = document.getElementById('total-pages');
            const currentPageEl = document.getElementById('current-page');
            
            if (!layoutEl || !container || !totalPagesEl) return;
            
            const layout = layoutEl.value;
            const totalPages = parseInt(totalPagesEl.innerText) || 0;
            
            if (pageFlip) {
                try { pageFlip.destroy(); } catch(e) {}
                pageFlip = null;
            }
            
            let config = { 
                width: 500, 
                height: 700, 
                size: "fixed", 
                showCover: false, 
                usePortrait: false,
                drawShadow: true,
                flippingTime: 800,
                startPage: 0
            };
            
            if (layout === 'landscape') {
                config.width = 800; 
                config.height = 560;
            } else if (layout === 'single') {
                config.usePortrait = true;
                config.width = 550; 
                config.height = 770;
            }
            
            try {
                pageFlip = new St.PageFlip(container, config);
                pageFlip.loadFromHTML(document.querySelectorAll('.page'));
                
                pageFlip.on('flip', (e) => { 
                    const p1 = e.data + 1; 
                    const p2 = Math.min(e.data + 2, totalPages);
                    if (currentPageEl) {
                        const text = (config.usePortrait || p1 === p2 || p1 === totalPages) ? p1 : (p1 + '-' + p2);
                        currentPageEl.innerText = text; 
                    }
                });
                
                if (currentPageEl) {
                    currentPageEl.innerText = config.usePortrait ? '1' : '1-2';
                }
            } catch (err) {
                console.error("Layout initialization failed", err);
            }
        }

        async function openBook(pdfPath, title) {
            document.getElementById('loading-overlay').style.display = 'flex';
            document.getElementById('book-title').innerText = title;
            const container = document.getElementById('book-container');
            container.innerHTML = ''; currentScale = 1.0; updateZoom();

            try {
                const pdf = await pdfjsLib.getDocument(pdfPath).promise;
                document.getElementById('total-pages').innerText = pdf.numPages;
                
                // Determine initial layout based on first page aspect ratio
                const firstPage = await pdf.getPage(1);
                const view = firstPage.getViewport({ scale: 1 });
                if (view.width > view.height) {
                    document.getElementById('pdf-layout').value = 'landscape';
                } else {
                    document.getElementById('pdf-layout').value = 'portrait';
                }

                for (let i = 1; i <= pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const viewport = page.getViewport({ scale: 1.5 });
                    const canvas = document.createElement('canvas');
                    canvas.height = viewport.height; canvas.width = viewport.width;
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                    const pageDiv = document.createElement('div');
                    pageDiv.className = 'page ' + (i % 2 === 1 ? '--left' : '--right');
                    pageDiv.appendChild(canvas);
                    const f = document.createElement('div'); f.className = 'page-footer'; f.innerText = i;
                    pageDiv.appendChild(f); container.appendChild(pageDiv);
                }
                document.getElementById('pdfModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                // Use the new layout logic
                applyPdfLayout();
                
            } catch (err) { alert("Lỗi: " + err.message); }
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function toggleSub(e, id) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const sub = document.getElementById('sub-' + id);
            const btn = document.querySelector(`button[onclick*="toggleSub(event, ${id})"]`);
            if (!sub) return;
            
            if (sub.style.display === 'none') {
                sub.style.display = 'block';
                if (btn) btn.style.transform = 'rotate(90deg)';
            } else {
                sub.style.display = 'none';
                if (btn) btn.style.transform = 'rotate(0deg)';
            }
        }

        // Removed redundant declarations causing SyntaxError
    </script>
    <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
</body>
</html>
