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
        is_excluded TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
} else {
    // Migration: add is_excluded if not exists
    $col_check = $conn->query("SHOW COLUMNS FROM sale_reports LIKE 'is_excluded'");
    if ($col_check->num_rows == 0) {
        $conn->query("ALTER TABLE sale_reports ADD COLUMN is_excluded TINYINT(1) DEFAULT 0");
    }
}

// Handle AJAX toggle exclude
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_exclude') {
    header('Content-Type: application/json');
    $odoo_id = intval($_POST['odoo_invoice_id']);
    // Get current state
    $stmt = $conn->prepare("SELECT is_excluded FROM sale_reports WHERE odoo_invoice_id = ?");
    $stmt->bind_param("i", $odoo_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $current = $row ? (int) $row['is_excluded'] : 0;
    $new_val = $current ? 0 : 1;
    $stmt2 = $conn->prepare("INSERT INTO sale_reports (odoo_invoice_id, is_excluded) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_excluded=?");
    $stmt2->bind_param("iii", $odoo_id, $new_val, $new_val);
    $stmt2->execute();
    echo json_encode(['success' => true, 'is_excluded' => $new_val]);
    exit();
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

// Generate Quarters for the last 2 years
$current_year = (int) date('Y');
$years = [$current_year, $current_year - 1];
$tabs = [];
// Generate in descending order: Q4 to Q1
foreach ($years as $y) {
    for ($q = 4; $q >= 1; $q--) {
        $tabs[] = "Q{$q}_{$y}";
    }
}

$current_q = ceil(date('n') / 3);
$default_tab = "Q{$current_q}_{$current_year}";
$active_tab = $_GET['quarter'] ?? $default_tab;

// Parse active tab date range
if (preg_match('/Q(\d+)_(\d+)/', $active_tab, $matches)) {
    $q = (int) $matches[1];
    $y = (int) $matches[2];
    $start_month = ($q - 1) * 3 + 1;
    $end_month = $q * 3;
    $start_date = "$y-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date('Y-m-t', strtotime("$y-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-01"));
} else {
    $start_date = '1970-01-01';
    $end_date = '2099-12-31';
}

$total_vnd = 0;
$filtered_invoices = [];

foreach ($invoices as &$inv) {
    $inv_date_str = $inv['invoice_date'] ?: $inv['date'];

    // Filter by quarter date
    if (!$inv_date_str || $inv_date_str < $start_date || $inv_date_str > $end_date) {
        continue;
    }

    // Use amount_total_signed for 100% accuracy from Odoo
    $amountVnd = isset($inv['amount_total_signed']) ? (float) $inv['amount_total_signed'] : 0;

    // Fallback if missing
    if ($amountVnd == 0 && $inv['amount_total'] > 0) {
        $currencyCode = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
        $rateSource = $odoo->getRate($currencyCode, $inv_date_str) ?: 1.0;
        $rateVnd = $odoo->getRate('VND', $inv_date_str) ?: 1.0;
        $amountVnd = $inv['amount_total'] * ($rateVnd / $rateSource);
    }

    $inv['calc_amount_vnd'] = $amountVnd;

    // Only add to total if not excluded
    $is_excluded = (int) ($local_data[$inv['id']]['is_excluded'] ?? 0);
    $inv['is_excluded'] = $is_excluded;
    if (!$is_excluded) {
        $total_vnd += $amountVnd;
    }

    $filtered_invoices[] = $inv;
}
unset($inv);

// Group by month
$grouped_invoices = [];
foreach ($filtered_invoices as $inv) {
    $inv_date_str = $inv['invoice_date'] ?: $inv['date'];
    $month_key = $inv_date_str ? date('Y-m', strtotime($inv_date_str)) : 'Unknown';
    $grouped_invoices[$month_key][] = $inv;
}
ksort($grouped_invoices);

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
        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        /* Prevent horizontal scroll on page */
        body {
            overflow-x: hidden;
        }

        .main-content {
            overflow-x: hidden;
        }

        .report-wrapper {
            padding: 1rem;
            max-width: 100%;
            height: calc(100vh - 80px);
            /* Fill screen */
            display: flex;
            flex-direction: column;
            overflow-x: auto;
            /* Allow horizontal scroll on wrapper now */
            overflow-y: auto;
            position: relative;
        }

        .tabs-container {
            display: flex;
            overflow-x: auto;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.5rem;
            padding-bottom: 0px;
            flex-shrink: 0;
            min-height: 48px;
        }

        .quarter-tab {
            padding: 10px 24px;
            font-weight: 600;
            font-size: 14px;
            color: #64748b;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            text-decoration: none;
            margin-right: 4px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .quarter-tab:hover {
            color: #0f172a;
            background-color: #f1f5f9;
        }

        .quarter-tab.active {
            color: #2563eb;
            background-color: #ffffff;
            border-color: #e2e8f0;
            border-bottom: 2px solid #ffffff;
            margin-bottom: -2px;
            /* Pull down to overlay container's bottom border */
            position: relative;
            z-index: 10;
        }

        table.report-table {
            width: max-content;
            /* Allow table to be wider than container */
            min-width: 100%;
            /* But at least 100% */
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            white-space: nowrap;
            background: white;
            border: 1px solid #ccc;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        /* Sticky Header */
        table.report-table thead th {
            position: sticky;
            top: 0;
            background-color: #004b75;
            /* Darker Blue */
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #003655;
            z-index: 10;
            white-space: normal;
            line-height: 1.3;
            vertical-align: middle;
            min-width: 100px;
            max-height: 52px;
            overflow: hidden;
        }

        /* Column borders in header */
        table.report-table thead th:not(:last-child) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        table.report-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #333;
        }

        /* Removed Sticky Columns as requested */

        table.report-table tr {
            background-color: white;
        }

        table.report-table tr:hover {
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
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
            background: #fff;
            color: #333;
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

        .month-group-header td {
            background: #f8fafc !important;
            color: #1e293b !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            border-top: 1px solid #cbd5e1 !important;
            border-bottom: 2px solid #cbd5e1 !important;
            padding: 12px 20px !important;
        }

        .month-total-row td {
            background: #f1f5f9 !important;
            font-weight: 600 !important;
            color: #475569 !important;
            border-top: 1px solid #e2e8f0 !important;
        }

        tr.row-excluded td {
            background: #fffde7 !important;
            opacity: 0.8;
        }

        tr.row-excluded:hover td {
            background: #fff9c4 !important;
        }

        .exclude-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #94a3b8;
            padding: 0;
        }

        .exclude-btn:hover {
            border-color: #f59e0b;
            background: #fef3c7;
            color: #d97706;
            transform: scale(1.1);
        }

        .exclude-btn.excluded {
            border-color: #f59e0b;
            background: #fef3c7;
            color: #d97706;
        }

        .exclude-btn svg {
            width: 14px;
            height: 14px;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Sale Reports - ' . str_replace('_', ' ', $active_tab);
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="report-wrapper">
                <div class="tabs-container">
                    <?php foreach ($tabs as $tab): ?>
                        <a href="?quarter=<?= urlencode($tab) ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                            class="quarter-tab <?= $active_tab === $tab ? 'active' : '' ?>">
                            <?= str_replace('_', ' ', $tab) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="header-controls">
                    <form method="GET" style="display: flex; gap: 1rem;">
                        <input type="hidden" name="quarter" value="<?= htmlspecialchars($active_tab) ?>">
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

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">STT</th>
                            <th style="width: 50px; text-align: center;">Loại trừ</th>
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
                        <?php if (empty($grouped_invoices)): ?>
                            <tr>
                                <td colspan="16" style="text-align:center; padding: 2rem;">No invoices found.</td>
                            </tr>
                        <?php else: ?>
                            <?php $stt = 1;
                            foreach ($grouped_invoices as $month_key => $month_invoices):
                                $display_month = $month_key !== 'Unknown' ? date('m / Y', strtotime($month_key . '-01')) : 'Unknown';
                                $month_subtotal = 0;
                                ?>
                                <tr class="month-group-header">
                                    <td colspan="16">THÁNG <?= $display_month ?></td>
                                </tr>
                                <?php foreach ($month_invoices as $inv):
                                    $odoo_id = $inv['id'];
                                    $l = $local_data[$odoo_id] ?? [];
                                    $inv_date_str = $inv['invoice_date'] ?: $inv['date'];
                                    $month_str = $inv_date_str ? date('d/m/Y', strtotime($inv_date_str)) : '';
                                    $is_excluded = (int) ($inv['is_excluded'] ?? 0);
                                    if (!$is_excluded)
                                        $month_subtotal += $inv['calc_amount_vnd'];
                                    ?>
                                    <tr class="invoice-row <?= $is_excluded ? 'row-excluded' : '' ?>"
                                        data-invoice-id="<?= $odoo_id ?>">
                                        <td style="text-align: center;">
                                            <?= $stt++ ?>
                                        </td>
                                        <!-- Loại trừ (cột 2) -->
                                        <td style="text-align: center;">
                                            <button class="exclude-btn <?= $is_excluded ? 'excluded' : '' ?>"
                                                onclick="toggleExclude(this, <?= $odoo_id ?>)"
                                                title="<?= $is_excluded ? 'Bỏ loại trừ invoice này' : 'Loại trừ invoice này khỏi tổng' ?>">
                                                <?php if ($is_excluded): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
                                                <?php else: ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
                                                <?php endif; ?>
                                            </button>
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
                                <tr class="month-total-row">
                                    <td colspan="8" style="text-align: right;">Cộng tháng <?= $display_month ?>:</td>
                                    <td style="text-align: right;"><?= formatMoney($month_subtotal, 'VND') ?></td>
                                    <td colspan="7"></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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

        function toggleExclude(btn, invoiceId) {
            const formData = new FormData();
            formData.append('action', 'toggle_exclude');
            formData.append('odoo_invoice_id', invoiceId);

            const svgExcluded = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;pointer-events:none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>';
            const svgNormal  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;pointer-events:none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>';

            fetch('', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const row = btn.closest('tr');
                        const isExcluded = data.is_excluded === 1;
                        row.classList.toggle('row-excluded', isExcluded);
                        btn.classList.toggle('excluded', isExcluded);
                        btn.innerHTML = isExcluded ? svgExcluded : svgNormal;
                        btn.title = isExcluded ? 'Bỏ loại trừ invoice này' : 'Loại trừ invoice này khỏi tổng';
                        showToast(isExcluded ? 'Đã loại trừ khỏi tổng!' : 'Đã bao gồm lại vào tổng!');
                        setTimeout(() => location.reload(), 800);
                    }
                })
                .catch(err => console.error(err));
        }

        // Make scrolling table scroll sync if needed
    </script>
</body>

</html>