<?php
require_once __DIR__ . '/../../../config/config.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;
$error_message = '';
$success_message = '';

// --- AUTO MIGRATE TABLE ---
// Ensure table exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS sale_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(255) NOT NULL,
    kpi_level DECIMAL(15,2) DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 0,
    color_badge VARCHAR(50) DEFAULT '#1a73e8',
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // ADD
        if ($_POST['action'] === 'add') {
            $level_name = trim($_POST['level_name']);
            $kpi_level = isset($_POST['kpi_level']) ? floatval($_POST['kpi_level']) : 0;
            $commission_rate = isset($_POST['commission_rate']) ? floatval($_POST['commission_rate']) : 0;
            $color_badge = trim($_POST['color_badge']);
            $order_num = isset($_POST['order_num']) ? intval($_POST['order_num']) : 0;

            if (!empty($level_name)) {
                $sql = "INSERT INTO sale_levels (level_name, kpi_level, commission_rate, color_badge, order_num) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddsi", $level_name, $kpi_level, $commission_rate, $color_badge, $order_num);

                if ($stmt->execute()) {
                    $success_message = "Sale Level added successfully!";
                } else {
                    $error_message = "Error adding: " . $conn->error;
                }
            } else {
                $error_message = "Level Name is required.";
            }
        }
        // EDIT
        elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $level_name = trim($_POST['level_name']);
            $kpi_level = isset($_POST['kpi_level']) ? floatval($_POST['kpi_level']) : 0;
            $commission_rate = isset($_POST['commission_rate']) ? floatval($_POST['commission_rate']) : 0;
            $color_badge = trim($_POST['color_badge']);
            $order_num = isset($_POST['order_num']) ? intval($_POST['order_num']) : 0;

            if (!empty($level_name) && $id > 0) {
                $sql = "UPDATE sale_levels SET level_name = ?, kpi_level = ?, commission_rate = ?, color_badge = ?, order_num = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddsii", $level_name, $kpi_level, $commission_rate, $color_badge, $order_num, $id);

                if ($stmt->execute()) {
                    $success_message = "Sale Level updated!";
                } else {
                    $error_message = "Error updating: " . $conn->error;
                }
            } else {
                $error_message = "Invalid data.";
            }
        }
        // DELETE
        elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM sale_levels WHERE id = ?");
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
$levels = [];
$sql = "SELECT id, level_name, kpi_level, commission_rate, color_badge, order_num, created_at FROM sale_levels ORDER BY order_num ASC, id DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $levels[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Level Setup - Settings</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
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
            box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3);
        }

        .sheet-container {
            flex: 1;
            overflow: auto;
            background: white;
            border: 1px solid #dadce0;
        }

        .sheet-table {
            border-collapse: collapse;
            width: 100%;
            font-family: 'Roboto', arial, sans-serif;
            font-size: 13px;
            color: #202124;
        }

        .sheet-table th,
        .sheet-table td {
            border: 1px solid #e0e0e0;
            padding: 8px 12px;
            height: 32px;
            white-space: nowrap;
        }

        .sheet-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #3c4043;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .sheet-table tr:hover td {
            background-color: #f1f3f4;
        }

        .col-index {
            width: 50px;
            text-align: center;
            background-color: #f8f9fa;
            font-weight: 600;
            color: #70757a;
        }

        .col-actions {
            width: 100px;
            text-align: center;
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

        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .modal-footer {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .level-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Sale Level Setup';
            $page_subtitle = 'Manage Sale Levels, KPIs, and Commissions';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <?php if ($success_message): ?>
                    <div
                        style="background: #e6f4ea; color: #1e8e3e; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 13px;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div
                        style="background: #fce8e6; color: #c5221f; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 13px;">
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
                        Add Sale Level
                    </button>
                </div>

                <div class="sheet-container">
                    <table class="sheet-table">
                        <thead>
                            <tr>
                                <th class="col-index">ID</th>
                                <th>Level Name</th>
                                <th>KPI Level (Target)</th>
                                <th>Commission Rate (%)</th>
                                <th>Order</th>
                                <th>Created Time</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($levels as $l): ?>
                                <tr>
                                    <td class="col-index">
                                        <?php echo $l['id']; ?>
                                    </td>
                                    <td>
                                        <span class="level-badge"
                                            style="background-color: <?php echo htmlspecialchars($l['color_badge']); ?>;">
                                            <?php echo htmlspecialchars($l['level_name']); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 500;">
                                        <?php echo number_format($l['kpi_level'], 2); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($l['commission_rate'], 2); ?>%
                                    </td>
                                    <td>
                                        <?php echo $l['order_num']; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y H:i', strtotime($l['created_at'])); ?>
                                    </td>
                                    <td class="col-actions">
                                        <div style="display:flex; justify-content:center; gap:8px;">
                                            <button
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($l)); ?>)"
                                                style="border:none; background:none; cursor:pointer; color:#5f6368;"
                                                title="Edit">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                    </path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                    </path>
                                                </svg>
                                            </button>
                                            <form method="POST" style="display:inline;"
                                                onsubmit="return confirm('Are you sure?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                                <button type="submit"
                                                    style="border:none; background:none; cursor:pointer; color:#d93025;"
                                                    title="Delete">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
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
                            <?php if (empty($levels)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px; color: #70757a;">
                                        No sale levels configured yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="levelModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle" style="margin-top:0; font-size: 18px; margin-bottom: 20px;">Add Sale Level</h2>
            <form method="POST" id="levelForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="levelId" value="">

                <div class="form-group">
                    <label>Level Name <span style="color:red">*</span></label>
                    <input type="text" id="level_name" name="level_name" required
                        placeholder="e.g. Intern, Junior, Senior">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>KPI Level (Target Value)</label>
                        <input type="number" step="0.01" id="kpi_level" name="kpi_level" value="0"
                            placeholder="e.g. 10000000">
                    </div>

                    <div class="form-group">
                        <label>Commission Rate (%)</label>
                        <input type="number" step="0.01" id="commission_rate" name="commission_rate" value="0"
                            placeholder="e.g. 5.5">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Badge Color</label>
                        <input type="color" id="color_badge" name="color_badge" value="#1a73e8"
                            style="padding: 2px; height: 34px;">
                    </div>

                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" id="order_num" name="order_num" value="0">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-toolbar" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-toolbar btn-primary-toolbar">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('levelModal');
        const form = document.getElementById('levelForm');

        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add Sale Level';
            document.getElementById('formAction').value = 'add';
            document.getElementById('levelId').value = '';
            document.getElementById('color_badge').value = '#1a73e8';
            form.reset();
            modal.style.display = 'flex';
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').innerText = 'Edit Sale Level';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('levelId').value = data.id;
            document.getElementById('level_name').value = data.level_name;
            document.getElementById('kpi_level').value = data.kpi_level;
            document.getElementById('commission_rate').value = data.commission_rate;
            document.getElementById('color_badge').value = data.color_badge;
            document.getElementById('order_num').value = data.order_num;
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>

</html>