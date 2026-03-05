<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Check permission: only admin or is_am_bd
if (empty($_SESSION['is_am_bd']) && $_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;

// Ensure table exists
$table_check = $conn->query("SHOW TABLES LIKE 'sale_reports'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE sale_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        odoo_invoice_id INT UNIQUE,
        invoice_name VARCHAR(100),
        contract_type VARCHAR(50), 
        presales VARCHAR(50), 
        client_type VARCHAR(50), 
        profit_pakd VARCHAR(255),
        net_profit VARCHAR(255),
        com_lead_source VARCHAR(10) DEFAULT 'No',
        bonus_license_trading VARCHAR(10) DEFAULT 'No',
        com_1 VARCHAR(20),
        com_2 VARCHAR(20),
        note TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Handle AJAX update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_inline') {
    header('Content-Type: application/json');
    $odoo_id = intval($_POST['odoo_invoice_id']);
    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['field']);
    $val = $_POST['value'];

    // Auto rules for com_1
    if ($field === 'client_type') {
        $com1_val = ($val === 'Old client') ? '0.5%' : (($val === 'New client') ? '1%' : '');
        $stmt = $conn->prepare("INSERT INTO sale_reports (odoo_invoice_id, client_type, com_1) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE client_type=?, com_1=?");
        $stmt->bind_param("issss", $odoo_id, $val, $com1_val, $val, $com1_val);
        $stmt->execute();
        echo json_encode(['success' => true, 'com_1' => $com1_val]);
    } else {
        $allowed = ['contract_type', 'presales', 'client_type', 'profit_pakd', 'net_profit', 'com_lead_source', 'bonus_license_trading', 'com_1', 'com_2', 'note'];
        if (in_array($field, $allowed)) {
            $stmt = $conn->prepare("INSERT INTO sale_reports (odoo_invoice_id, $field) VALUES (?, ?) ON DUPLICATE KEY UPDATE $field=?");
            $stmt->bind_param("iss", $odoo_id, $val, $val);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid field']);
        }
    }
    exit();
}

$odoo = new OdooAPI();
$search = $_GET['search'] ?? '';

// Fetch all Odoo Invoices for logged in AM/BD user
$limit = 5000;
$filters = [];
// Admin bypasses email filter but we can keep it for the user if strictly "theo users đăng nhập"
// User requested: "list toàn bộ từ phần quản lý invoice theo users đăng nhập có type is am bd"
// We'll enforce the current user's email filter just like My Invoices
if ($_SESSION['role'] !== 'admin' || true) { // Always filter by logged-in user email
    $u_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $filters['owner_email'] = $row['email'];
        // Also add search filter
        if ($search)
            $filters['search'] = $search;
    }
}

try {
    $result = $odoo->getInvoices($limit, 0, $filters);
    $invoices = $result['invoices'] ?? [];
} catch (Exception $e) {
    $invoices = [];
}

// Fetch local data to merge
$local_data = [];
$res_local = $conn->query("SELECT * FROM sale_reports");
if ($res_local) {
    while ($r = $res_local->fetch_assoc()) {
        $local_data[$r['odoo_invoice_id']] = $r;
    }
}

// Pre-process list and sum totals
$total_vnd = 0;
foreach ($invoices as &$inv) {
    // Determine VND Amount (using static rate approach for simplicity, like invoice list)
    $currencyCode = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
    $invoiceDate = $inv['date'] ?: $inv['invoice_date'];
    $rateSource = $odoo->getRate($currencyCode, $invoiceDate) ?: 1.0;
    $rateVnd = $odoo->getRate('VND', $invoiceDate);
    $amountVnd = $inv['amount_total'] * ($rateVnd / $rateSource);
    $inv['calc_amount_vnd'] = $amountVnd;
    $total_vnd += $amountVnd;
}
unset($inv);

// Helper
function formatMoney($amount, $currency_code)
{
    return number_format($amount, 2) . ' ' . $currency_code;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Reports</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .report-wrapper {
            padding: 1.5rem;
        }

        .table-card {
            background: white;
            border: 1px solid #c0c0c0;
            overflow-x: auto;
            max-height: calc(100vh - 200px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #000;
            min-width: 1800px;
        }

        th {
            background: #fce8cd;
            color: #333;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #c0c0c0;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 4px 8px;
            border: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        tr:hover td {
            background-color: #f1f3f4;
        }

        .editable-cell {
            cursor: pointer;
            transition: background 0.1s;
        }

        .editable-cell:hover {
            background-color: #e8f0fe !important;
        }

        select.inline-input,
        input.inline-input {
            width: 100%;
            padding: 4px;
            border: 1px solid #1a73e8;
            border-radius: 4px;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
            background: #fff;
        }

        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .total-summary {
            font-size: 15px;
            font-weight: 600;
            color: #1a73e8;
            background: #e8f0fe;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            z-index: 9999;
            display: none;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Sale Reports';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="report-wrapper">
                <div class="header-controls">
                    <form method="GET" style="display: flex; gap: 1rem;">
                        <input type="text" name="search" placeholder="Search Invoices..."
                            value="<?= htmlspecialchars($search) ?>"
                            style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; border-radius: 6px; width: 300px;">
                        <button type="submit"
                            style="padding: 0.5rem 1rem; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer;">Search</button>
                    </form>
                    <div class="total-summary">
                        Tổng Giá trị:
                        <?= formatMoney($total_vnd, 'VND') ?>
                    </div>
                </div>

                <div class="table-card" id="scrollArea">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">STT</th>
                                <th style="width: 150px;">Tên khách hàng</th>
                                <th style="width: 150px;">Tên Dự án</th>
                                <th style="width: 120px;">Mã dự án</th>
                                <th style="width: 100px;">Ngày ký Hợp đồng</th>
                                <th style="width: 120px;">Loại Hợp đồng</th>
                                <th style="width: 100px;">Presales</th>
                                <th style="width: 120px;">Loại khách hàng</th>
                                <th style="width: 150px; text-align:right;">Giá trị HĐ / Hóa đơn</th>
                                <th style="width: 140px;">%Profit trong PAKD</th>
                                <th style="width: 120px;">Net profit</th>
                                <!-- Target + %KPI skipped -->
                                <th style="width: 140px;">% Com (Lead source)</th>
                                <th style="width: 160px;">% Bonus License/trading</th>
                                <th style="width: 80px;">% Com 1</th>
                                <th style="width: 100px;">% Com 2</th>
                                <th style="min-width: 200px;">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="16" style="text-align:center; padding: 2rem;">No invoices found.</td>
                                </tr>
                            <?php else: ?>
                                <?php $stt = 1;
                                foreach ($invoices as $inv):
                                    $odoo_id = $inv['id'];
                                    $l = $local_data[$odoo_id] ?? [];
                                    $inv_date_str = $inv['invoice_date'] ?: $inv['date'];
                                    $month_str = $inv_date_str ? date('m/Y', strtotime($inv_date_str)) : '';
                                    ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <?= $stt++ ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(is_array($inv['partner_id']) ? $inv['partner_id'][1] : '') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($inv['ref'] ?: $inv['name']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($inv['x_studio_project_code'] ?? '') ?>
                                        </td>
                                        <td>
                                            <?= $month_str ?>
                                        </td>

                                        <!-- Loại Hợp đồng -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'contract_type', 'select', ['Service', 'Trading', 'Dedicated', 'License'])">
                                            <?= htmlspecialchars($l['contract_type'] ?? '') ?>
                                        </td>

                                        <!-- Presales -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'presales', 'select', ['No presales', '0%', '0.25%', '0.5%'])">
                                            <?= htmlspecialchars($l['presales'] ?? '') ?>
                                        </td>

                                        <!-- Loại khách hàng -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'client_type', 'select', ['New client', 'Old client'])">
                                            <?= htmlspecialchars($l['client_type'] ?? '') ?>
                                        </td>

                                        <!-- Giá trị -->
                                        <td style="text-align:right; font-family: Inconsolata, monospace;">
                                            <?= formatMoney($inv['amount_total'], is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND') ?>
                                        </td>

                                        <!-- %Profit trong PAKD -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'profit_pakd', 'text')">
                                            <?= htmlspecialchars($l['profit_pakd'] ?? '') ?>
                                        </td>

                                        <!-- Net profit -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'net_profit', 'text')">
                                            <?= htmlspecialchars($l['net_profit'] ?? '') ?>
                                        </td>

                                        <!-- % Com (Lead source) -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'com_lead_source', 'select', ['Yes', 'No'])">
                                            <?= htmlspecialchars($l['com_lead_source'] ?? 'No') ?>
                                        </td>

                                        <!-- % Bonus License/trading -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'bonus_license_trading', 'select', ['Yes', 'No'])">
                                            <?= htmlspecialchars($l['bonus_license_trading'] ?? 'No') ?>
                                        </td>

                                        <!-- % Com 1 -->
                                        <td id="com_1_<?= $odoo_id ?>"
                                            style="color: #c5221f; font-weight:600; background: #fdfaf6;">
                                            <?= htmlspecialchars($l['com_1'] ?? '') ?>
                                        </td>

                                        <!-- % Com 2 -->
                                        <td class="editable-cell"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'com_2', 'select', ['0.5%', '1%', '1.5%', '2%', '2.5%', '3%'])">
                                            <?= htmlspecialchars($l['com_2'] ?? '') ?>
                                        </td>

                                        <!-- Note -->
                                        <td class="editable-cell" onclick="makeEditable(this, <?= $odoo_id ?>, 'note', 'text')">
                                            <?= htmlspecialchars($l['note'] ?? '') ?>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="toast" class="toast">Saved!</div>

    <script>
        let currentEditing = null;

        function makeEditable(cell, invoiceId, fieldName, inputType, options = []) {
            if (currentEditing === cell) return;
            if (currentEditing) saveCurrentEdit();

            currentEditing = cell;
            const currentVal = cell.innerText.trim();

            // Save old val
            cell.setAttribute('data-old-val', currentVal);
            cell.innerHTML = '';

            let input;
            if (inputType === 'select') {
                input = document.createElement('select');
                input.className = 'inline-input';

                // Adding a neutral empty option
                let optNull = document.createElement('option');
                optNull.value = '';
                optNull.text = '-- Chọn --';
                input.appendChild(optNull);

                options.forEach(opt => {
                    let option = document.createElement('option');
                    option.value = opt;
                    option.text = opt;
                    if (opt === currentVal) option.selected = true;
                    input.appendChild(option);
                });
            } else {
                input = document.createElement('input');
                input.type = 'text';
                input.className = 'inline-input';
                input.value = currentVal;
            }

            cell.appendChild(input);
            input.focus();

            // Handle blur and enter to save
            input.addEventListener('blur', () => saveEdit(cell, input, invoiceId, fieldName));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cell.innerHTML = cell.getAttribute('data-old-val');
                    currentEditing = null;
                }
            });
        }

        function saveCurrentEdit() {
            if (!currentEditing) return;
            const input = currentEditing.querySelector('.inline-input');
            if (input) input.blur();
        }

        function saveEdit(cell, input, invoiceId, fieldName) {
            const newVal = input.value;
            const oldVal = cell.getAttribute('data-old-val');

            if (newVal === oldVal) {
                cell.innerHTML = newVal;
                currentEditing = null;
                return;
            }

            // Set optimistic UI
            cell.innerHTML = '<span style="color:#9aa0a6;">Saving...</span>';
            currentEditing = null;

            const formData = new FormData();
            formData.append('action', 'update_inline');
            formData.append('odoo_invoice_id', invoiceId);
            formData.append('field', fieldName);
            formData.append('value', newVal);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        cell.innerHTML = newVal;
                        showToast("Đã lưu!");

                        // Trigger auto update for com_1 if client_type was edited
                        if (fieldName === 'client_type') {
                            const com1Cell = document.getElementById('com_1_' + invoiceId);
                            if (com1Cell && data.com_1 !== undefined) {
                                com1Cell.innerText = data.com_1;
                            }
                        }
                    } else {
                        alert('Lỗi: ' + (data.error || 'Unknown error'));
                        cell.innerHTML = oldVal;
                    }
                })
                .catch(err => {
                    console.error(err);
                    cell.innerHTML = oldVal;
                    alert('Mạng lỗi, không thể lưu.');
                });
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.innerText = msg;
            toast.style.display = 'block';
            toast.style.opacity = '1';
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => { toast.style.display = 'none'; }, 300);
            }, 2000);
        }

        // Auto-save on outside click
        document.addEventListener('mousedown', function (e) {
            if (currentEditing && !currentEditing.contains(e.target)) {
                saveCurrentEdit();
            }
        });

        // Make scrolling table scroll sync if needed
    </script>
</body>

</html>