<?php
require_once __DIR__ . '/../../../config/config.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;
$error_message = '';
$success_message = '';

// --- AUTO MIGRATE TABLE ---
// Ensure table exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Cleanup duplicates
// $conn->query("DELETE t1 FROM departments t1 INNER JOIN departments t2 WHERE t1.id < t2.id AND t1.name = t2.name"); 
// Check unique constraint
$check_index = $conn->query("SHOW INDEX FROM departments WHERE Key_name = 'unique_name'");
if ($check_index->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE departments ADD CONSTRAINT unique_name UNIQUE (name)");
    } catch (Exception $e) {
        // Ignore duplicate key error if it already exists
    }
}

// Add parent_id
if ($conn->query("SHOW COLUMNS FROM departments LIKE 'parent_id'")->num_rows == 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN parent_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE departments ADD CONSTRAINT fk_parent_dept FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL");
}

// Add owner_id
if ($conn->query("SHOW COLUMNS FROM departments LIKE 'owner_id'")->num_rows == 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN owner_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE departments ADD CONSTRAINT fk_dept_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL");
}

// Add manager_id
if ($conn->query("SHOW COLUMNS FROM departments LIKE 'manager_id'")->num_rows == 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN manager_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE departments ADD CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL");
}

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // ADD
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : NULL;
            $owner_id = !empty($_POST['owner_id']) ? intval($_POST['owner_id']) : NULL;
            $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : NULL;

            if (!empty($name)) {
                $sql = "INSERT INTO departments (name, description, parent_id, owner_id, manager_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiii", $name, $desc, $parent_id, $owner_id, $manager_id);

                try {
                    $stmt->execute();
                    $success_message = "Department added successfully!";
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062) {
                        $error_message = "Department '" . htmlspecialchars($name) . "' already exists.";
                    } else {
                        $error_message = "Error adding: " . $e->getMessage();
                    }
                }
            } else {
                $error_message = "Name is required.";
            }
        }
        // EDIT
        elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : NULL;
            $owner_id = !empty($_POST['owner_id']) ? intval($_POST['owner_id']) : NULL;
            $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : NULL;

            if ($id == $parent_id) {
                $error_message = "Cannot be own parent.";
            } elseif (!empty($name) && $id > 0) {
                $sql = "UPDATE departments SET name = ?, description = ?, parent_id = ?, owner_id = ?, manager_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiiii", $name, $desc, $parent_id, $owner_id, $manager_id, $id);

                try {
                    $stmt->execute();
                    $success_message = "Department updated!";
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062) {
                        $error_message = "Department '" . htmlspecialchars($name) . "' already exists.";
                    } else {
                        $error_message = "Error updating: " . $e->getMessage();
                    }
                }
            } else {
                $error_message = "Invalid data.";
            }
        }
        // DELETE
        elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success_message = "Deleted successfully!";
                } else {
                    $error_message = "Error deleting: " . $conn->error;
                }
            }
        }
    }
}

// --- FETCH DATA ---
$departments = [];
// Select departments with Parent, Owner, and Manager info
$sql = "SELECT d.id, d.name, d.description, d.created_at, d.parent_id, d.owner_id, d.manager_id, 
               p.name as parent_name, 
               u1.full_name as owner_name, u1.avatar as owner_avatar,
               u2.full_name as manager_name, u2.avatar as manager_avatar
        FROM departments d 
        LEFT JOIN departments p ON d.parent_id = p.id 
        LEFT JOIN users u1 ON d.owner_id = u1.id 
        LEFT JOIN users u2 ON d.manager_id = u2.id
        ORDER BY d.name ASC"; // Order by name for alphabet sorting within levels

$result = $conn->query($sql);
$raw_departments = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['children'] = []; // Initialize children
        $raw_departments[$row['id']] = $row;
    }
}

// Build Tree
$tree = [];
foreach ($raw_departments as $id => $node) {
    if ($node['parent_id'] && isset($raw_departments[$node['parent_id']])) {
        $raw_departments[$node['parent_id']]['children'][] = &$raw_departments[$id];
    } else {
        $tree[] = &$raw_departments[$id];
    }
}

// Flatten for Display
$departments = [];
function flattenTree($nodes, $level = 0, &$result_array)
{
    foreach ($nodes as $node) {
        $node_copy = $node;
        unset($node_copy['children']); // Remove children from copy to avoid recursion in display
        $node_copy['level'] = $level;
        $result_array[] = $node_copy;
        if (!empty($node['children'])) {
            flattenTree($node['children'], $level + 1, $result_array);
        }
    }
}
flattenTree($tree, 0, $departments);

$all_depts_list = $departments; // Use flat list for dropdown but keep indentation logic? maybe later. For now just flat list is fine or we can indent dropdown too.

// Fetch Users for Dropdown
$users_list = [];
$u_res = $conn->query("SELECT id, full_name, email FROM users ORDER BY full_name ASC");
if ($u_res) {
    while ($u_row = $u_res->fetch_assoc()) {
        $users_list[] = $u_row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments (Sheet View) - Settings</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        /* Override Dashboard Styles for Full-Width Sheet View */
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

        .btn-primary-toolbar:hover {
            background: #1557b0;
            color: white;
            box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3);
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
            min-width: 1000px;
        }

        .sheet-table th,
        .sheet-table td {
            border: 1px solid #e0e0e0;
            padding: 4px 8px;
            height: 32px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-sizing: border-box;
        }

        .sheet-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #3c4043;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
            user-select: none;
            cursor: pointer;
            border-bottom: 2px solid #dadce0;
        }

        .sheet-table th:hover {
            background-color: #e8eaed;
        }

        .sheet-table tr:hover td {
            background-color: #e8f0fe !important;
        }

        .col-index {
            background-color: #f8f9fa;
            text-align: center;
            width: 40px;
            min-width: 40px;
            color: #70757a;
            font-weight: 600;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid #dadce0 !important;
        }

        .sheet-table th.col-index {
            z-index: 15;
        }

        .col-actions {
            width: 80px;
            text-align: center;
        }

        .badge-cell {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: #e8f0fe;
            color: #1967d2;
            border: 1px solid #d2e3fc;
        }

        /* Owner Badge Style (Yellow/Orange) */
        .badge-owner {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px 2px 2px;
            border-radius: 12px;
            background: #FDF4E7;
            color: #8F550C;
            border: 1px solid #F8E3C3;
            font-size: 11px;
            font-weight: 500;
        }

        .owner-avatar-img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid white;
        }

        .owner-avatar-initial {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #F59E0B;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            border: 1px solid white;
        }

        /* Manager Badge Style (Blue/Green) */
        .badge-manager {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px 2px 2px;
            border-radius: 12px;
            background: #E6F4EA;
            color: #137333;
            border: 1px solid #CEEAD6;
            font-size: 11px;
            font-weight: 500;
        }

        .manager-avatar-img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid white;
        }

        .manager-avatar-initial {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #1E8E3E;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            border: 1px solid white;
        }

        .sort-icon {
            display: inline-block;
            width: 0;
            height: 0;
            margin-left: 5px;
            vertical-align: middle;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            opacity: 0.3;
        }

        .th-sort-asc .sort-icon {
            border-bottom: 4px solid #202124;
            opacity: 1;
        }

        .th-sort-desc .sort-icon {
            border-top: 4px solid #202124;
            opacity: 1;
        }

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
            border-radius: 8px;
            width: 450px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
            color: #3c4043;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 13px;
            font-family: 'Roboto', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: 2px solid #1a73e8;
            border-color: transparent;
        }

        .modal-footer {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-cancel {
            padding: 8px 16px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            color: #3c4043;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-save {
            padding: 8px 16px;
            background: #1a73e8;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Departments List';
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
                        Add Row
                    </button>
                    <div style="width: 1px; height: 20px; background: #dadce0; margin: 0 4px;"></div>
                    <button class="btn-toolbar" onclick="printTable()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Print
                    </button>
                    <div style="flex:1"></div>
                    <input type="text" id="searchInput" placeholder="Search..."
                        style="padding: 6px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 13px; font-family: 'Roboto'; width: 200px;"
                        onkeyup="filterTable()">
                </div>

                <div class="sheet-container">
                    <table class="sheet-table" id="deptTable">
                        <thead>
                            <tr>
                                <th class="col-index" onclick="sortTable(0)" style="cursor:pointer;">ID <span
                                        class="sort-icon"></span></th>
                                <th onclick="sortTable(1)" style="width: 20%;">Department Name <span
                                        class="sort-icon"></span></th>
                                <th onclick="sortTable(2)" style="width: 15%;">Parent Dept <span
                                        class="sort-icon"></span></th>
                                <th onclick="sortTable(3)" style="width: 15%;">Owner <span class="sort-icon"></span>
                                </th>
                                <th onclick="sortTable(4)" style="width: 15%;">Manager <span class="sort-icon"></span>
                                </th> <!-- Manager Col -->
                                <th onclick="sortTable(5)" style="width: 20%;">Description <span
                                        class="sort-icon"></span></th>
                                <th onclick="sortTable(6)" style="width: 15%;">Created At <span
                                        class="sort-icon"></span></th>
                                <th class="col-actions">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td class="col-index"><?php echo $dept['id']; ?></td>
                                    <td style="font-weight: 500;">
                                        <?php echo str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $dept['level']); ?>
                                        <?php if ($dept['level'] > 0)
                                            echo "- "; ?>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($dept['parent_name'])): ?>
                                            <span
                                                class="badge-cell"><?php echo htmlspecialchars($dept['parent_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($dept['owner_name'])): ?>
                                            <span class="badge-owner">
                                                <?php if (!empty($dept['owner_avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($dept['owner_avatar']); ?>"
                                                        class="owner-avatar-img" alt="Avatar">
                                                <?php else: ?>
                                                    <span
                                                        class="owner-avatar-initial"><?php echo strtoupper(substr($dept['owner_name'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($dept['owner_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($dept['manager_name'])): ?>
                                            <span class="badge-manager">
                                                <?php if (!empty($dept['manager_avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($dept['manager_avatar']); ?>"
                                                        class="manager-avatar-img" alt="Avatar">
                                                <?php else: ?>
                                                    <span
                                                        class="manager-avatar-initial"><?php echo strtoupper(substr($dept['manager_name'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($dept['manager_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($dept['description']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($dept['created_at'])); ?></td>
                                    <td class="col-actions">
                                        <div style="display:flex; justify-content:center; gap:4px;">
                                            <button
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($dept)); ?>)"
                                                style="border:none; background:none; cursor:pointer; color:#5f6368;"
                                                title="Edit">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                    </path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                    </path>
                                                </svg>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Delete row?');"
                                                style="display:inline; margin:0;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                <button type="submit"
                                                    style="border:none; background:none; cursor:pointer; color:#5f6368;"
                                                    title="Delete">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path
                                                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
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
    <div id="deptModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle" style="margin-top:0; font-size: 18px; margin-bottom: 20px;">Add Department</h2>
            <form method="POST" id="deptForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="deptId" value="">

                <div class="form-group">
                    <label>Name <span style="color:red">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label>Parent Department</label>
                    <select name="parent_id" id="parent_id">
                        <option value="">-- None --</option>
                        <?php foreach ($all_depts_list as $p_dept): ?>
                            <option value="<?php echo $p_dept['id']; ?>">
                                <?php echo str_repeat("&nbsp;&nbsp;", $p_dept['level']); ?>
                                <?php if ($p_dept['level'] > 0)
                                    echo "- "; ?>
                                <?php echo htmlspecialchars($p_dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display:flex; gap:16px;">
                    <div style="flex:1;">
                        <label>Owner (Tech Lead)</label>
                        <select name="owner_id" id="owner_id">
                            <option value="">-- Select Owner --</option>
                            <?php foreach ($users_list as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Manager (PM/Director)</label>
                        <select name="manager_id" id="manager_id">
                            <option value="">-- Select Manager --</option>
                            <?php foreach ($users_list as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function sortTable(n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = document.getElementById("deptTable");
            switching = true;
            dir = "asc";

            const headers = table.getElementsByTagName("TH");
            for (let h of headers) {
                h.classList.remove("th-sort-asc", "th-sort-desc");
            }

            while (switching) {
                switching = false;
                rows = table.rows;
                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];

                    var xContent = x.innerText.trim();
                    var yContent = y.innerText.trim();

                    var xNum = parseFloat(xContent);
                    var yNum = parseFloat(yContent);

                    if (!isNaN(xNum) && !isNaN(yNum) && n === 0) {
                        xContent = xNum;
                        yContent = yNum;
                    } else {
                        xContent = xContent.toLowerCase();
                        yContent = yContent.toLowerCase();
                    }

                    if (dir == "asc") {
                        if (xContent > yContent) { shouldSwitch = true; break; }
                    } else if (dir == "desc") {
                        if (xContent < yContent) { shouldSwitch = true; break; }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount++;
                } else {
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
            if (dir === "asc") headers[n].classList.add("th-sort-asc");
            else headers[n].classList.add("th-sort-desc");
        }

        function filterTable() {
            var input, filter, table, tr, td, i;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("deptTable");
            tr = table.getElementsByTagName("tr");
            for (i = 1; i < tr.length; i++) {
                // Name (1), Parent (2), Owner (3), Manager (4)
                var tdName = tr[i].getElementsByTagName("td")[1];
                var tdParent = tr[i].getElementsByTagName("td")[2];
                var tdOwner = tr[i].getElementsByTagName("td")[3];
                var tdManager = tr[i].getElementsByTagName("td")[4];

                if (tdName || tdParent || tdOwner || tdManager) {
                    var txtName = tdName ? tdName.innerText : "";
                    var txtParent = tdParent ? tdParent.innerText : "";
                    var txtOwner = tdOwner ? tdOwner.innerText : "";
                    var txtManager = tdManager ? tdManager.innerText : "";

                    if (txtName.toUpperCase().indexOf(filter) > -1 ||
                        txtParent.toUpperCase().indexOf(filter) > -1 ||
                        txtOwner.toUpperCase().indexOf(filter) > -1 ||
                        txtManager.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        const modal = document.getElementById('deptModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const deptId = document.getElementById('deptId');
        const nameInput = document.getElementById('name');
        const descInput = document.getElementById('description');
        const parentSelect = document.getElementById('parent_id');
        const ownerSelect = document.getElementById('owner_id');
        const managerSelect = document.getElementById('manager_id');

        function openAddModal() {
            modalTitle.textContent = 'Add Department';
            formAction.value = 'add';
            deptId.value = '';
            nameInput.value = '';
            descInput.value = '';
            parentSelect.value = '';
            ownerSelect.value = '';
            managerSelect.value = ''; // Reset manager

            for (let i = 0; i < parentSelect.options.length; i++) parentSelect.options[i].disabled = false;
            modal.classList.add('show');
        }

        function openEditModal(dept) {
            modalTitle.textContent = 'Edit Department';
            formAction.value = 'edit';
            deptId.value = dept.id;
            nameInput.value = dept.name;
            descInput.value = dept.description;
            parentSelect.value = dept.parent_id || '';
            ownerSelect.value = dept.owner_id || '';
            managerSelect.value = dept.manager_id || ''; // Set manager

            for (let i = 0; i < parentSelect.options.length; i++) {
                parentSelect.options[i].disabled = (parentSelect.options[i].value == dept.id);
            }
            modal.classList.add('show');
        }

        function closeModal() { modal.classList.remove('show'); }
        window.onclick = function (event) { if (event.target == modal) closeModal(); }

        function printTable() { window.print(); }
    </script>
</body>

</html>