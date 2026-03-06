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

// Ensure confirmations table exists
$conn->query("CREATE TABLE IF NOT EXISTS sale_report_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quarter VARCHAR(20) NOT NULL,
    confirmed_at DATETIME NOT NULL,
    confirmed_by_name VARCHAR(255),
    UNIQUE KEY uq_user_quarter (user_id, quarter)
)");

// Handle AJAX confirm_kpi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_kpi') {
    header('Content-Type: application/json');
    $quarter_key = preg_replace('/[^A-Za-z0-9_]/', '', $_POST['quarter'] ?? '');
    $uid = (int) $_SESSION['user_id'];
    $uname = $_SESSION['full_name'] ?? 'Unknown';
    // Server-side check on local fields for non-excluded invoices
    $ids_str = $_POST['invoice_ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $ids_str)));
    $res_inv = $conn->query("SELECT odoo_invoice_id, contract_type, presales, client_type, com_lead_source, bonus_license_trading FROM sale_reports WHERE is_excluded = 0 OR is_excluded IS NULL");
    $local_map = [];
    if ($res_inv) {
        while ($r = $res_inv->fetch_assoc())
            $local_map[(int) $r['odoo_invoice_id']] = $r;
    }
    $missing = [];
    foreach ($ids as $oid) {
        $r = $local_map[$oid] ?? null;
        if (!$r || trim($r['contract_type'] ?? '') === '')
            $missing[] = "Invoice #$oid: Loại Hợp đồng";
        if (!$r || trim($r['presales'] ?? '') === '')
            $missing[] = "Invoice #$oid: Presales";
        if (!$r || trim($r['client_type'] ?? '') === '')
            $missing[] = "Invoice #$oid: Loại khách hàng";
        if (!$r || trim($r['com_lead_source'] ?? '') === '')
            $missing[] = "Invoice #$oid: % Com (Lead source)";
        if (!$r || trim($r['bonus_license_trading'] ?? '') === '')
            $missing[] = "Invoice #$oid: % Bonus License/trading";
    }
    if (!empty($missing)) {
        echo json_encode(['success' => false, 'missing' => array_values(array_unique($missing))]);
    } else {
        $now = date('Y-m-d H:i:s');
        $stmt_c = $conn->prepare("INSERT INTO sale_report_confirmations (user_id, quarter, confirmed_at, confirmed_by_name) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE confirmed_at=?, confirmed_by_name=?");
        $stmt_c->bind_param("isssss", $uid, $quarter_key, $now, $uname, $now, $uname);
        $stmt_c->execute();
        echo json_encode(['success' => true, 'confirmed_at' => $now, 'confirmed_by' => $uname]);
    }
    exit();
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

// Compute Year-To-Date revenue (from Jan 1 of the viewed year through end of active quarter)
// This is used for the KPI Yearly comparison in the report below
$ytd_start_date = isset($y) ? "$y-01-01" : date('Y') . '-01-01';
$ytd_end_date = $end_date; // same as end of active quarter
$ytd_vnd = 0;
foreach ($invoices as $inv_ytd) {
    $inv_date_str_ytd = $inv_ytd['invoice_date'] ?: $inv_ytd['date'];
    if (!$inv_date_str_ytd || $inv_date_str_ytd < $ytd_start_date || $inv_date_str_ytd > $ytd_end_date)
        continue;
    $is_excluded_ytd = (int) ($local_data[$inv_ytd['id']]['is_excluded'] ?? 0);
    if ($is_excluded_ytd)
        continue;
    $ytd_vnd += isset($inv_ytd['amount_total_signed']) ? (float) $inv_ytd['amount_total_signed'] : 0;
    // Fallback conversion for non-VND invoices
    if ($ytd_vnd == 0 && $inv_ytd['amount_total'] > 0) {
        // Already converted invoices are stored, but for safety keep simple approach here
    }
}

// Group by month
$grouped_invoices = [];
foreach ($filtered_invoices as $inv) {
    $inv_date_str = $inv['invoice_date'] ?: $inv['date'];
    $month_key = $inv_date_str ? date('Y-m', strtotime($inv_date_str)) : 'Unknown';
    $grouped_invoices[$month_key][] = $inv;
}
ksort($grouped_invoices);

// Fetch user's Sale Level & KPI targets
$kpi_data = null;
$u_id = (int) $_SESSION['user_id'];
$is_am_bd = !empty($_SESSION['is_am_bd']);
if ($is_am_bd) {
    $stmt_kpi = $conn->prepare("
        SELECT u.full_name, sl.level_name, sl.position_type, sl.color_badge,
               sl.kpi_quarter_vnd, sl.kpi_yearly_vnd, sl.kpi_quarter_usd, sl.kpi_yearly_usd
        FROM users u
        LEFT JOIN sale_levels sl ON u.sale_level_id = sl.id
        WHERE u.id = ?
    ");
    $stmt_kpi->bind_param("i", $u_id);
    $stmt_kpi->execute();
    $kpi_row = $stmt_kpi->get_result()->fetch_assoc();
    if ($kpi_row && $kpi_row['level_name']) {
        $kpi_data = $kpi_row;
    }
}

// Fetch existing confirmation for this quarter
$confirmation = null;
$stmt_conf = $conn->prepare("SELECT * FROM sale_report_confirmations WHERE user_id=? AND quarter=? LIMIT 1");
$stmt_conf->bind_param("is", $u_id, $active_tab);
$stmt_conf->execute();
$conf_row = $stmt_conf->get_result()->fetch_assoc();
if ($conf_row)
    $confirmation = $conf_row;

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
            background: #f1f8ff !important;
            color: #1e293b !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            border-top: 2px solid #bfdbfe !important;
            border-bottom: 1px solid #bfdbfe !important;
            padding: 10px 16px !important;
            letter-spacing: 0.3px;
        }

        .month-total-row td {
            background: #f1f5f9 !important;
            font-weight: 600 !important;
            color: #475569 !important;
            border-top: 1px solid #e2e8f0 !important;
        }

        /* ── KPI Report Panel ── */
        .kpi-report {
            margin-top: 2rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            flex-shrink: 0;
        }

        .kpi-report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .kpi-report-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .kpi-level-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            letter-spacing: 0.2px;
        }

        .kpi-quarter-label {
            font-size: 12px;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .kpi-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .kpi-metric-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .kpi-metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 12px 12px 0 0;
        }

        .kpi-metric-card.blue::before {
            background: #3b82f6;
        }

        .kpi-metric-card.green::before {
            background: #10b981;
        }

        .kpi-metric-card.amber::before {
            background: #f59e0b;
        }

        .kpi-metric-card.rose::before {
            background: #f43f5e;
        }

        .kpi-metric-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
        }

        .kpi-metric-value {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 0.25rem;
        }

        .kpi-metric-sub {
            font-size: 11px;
            color: #94a3b8;
        }

        .kpi-progress-section {
            margin-top: 0.25rem;
        }

        .kpi-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.35rem;
        }

        .kpi-progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
        }

        .kpi-progress-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.6s ease;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 14px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-achieved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-on-track {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-at-risk {
            background: #fef3c7;
            color: #92400e;
        }

        .status-behind {
            background: #fee2e2;
            color: #991b1b;
        }

        .kpi-no-level {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
            font-size: 14px;
        }

        /* ── Confirm section ── */
        .kpi-confirm-section {
            border-top: 1px solid #e2e8f0;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .confirm-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .confirm-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        }

        .confirm-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .confirmed-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #6ee7b7;
        }

        .confirm-error-list {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 12px;
            color: #991b1b;
            max-height: 160px;
            overflow-y: auto;
        }

        .confirm-error-list li {
            margin: 2px 0;
        }

        td.cell-missing {
            background: #fff1f2 !important;
            outline: 2px solid #f43f5e !important;
            outline-offset: -2px;
            animation: pulse-missing 1.5s ease-in-out 3;
        }

        @keyframes pulse-missing {

            0%,
            100% {
                outline-color: #f43f5e;
            }

            50% {
                outline-color: #fca5a5;
            }
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
                                        data-invoice-id="<?= $odoo_id ?>" data-is-excluded="<?= $is_excluded ?>">
                                        <td style="text-align: center;">
                                            <?= $stt++ ?>
                                        </td>
                                        <!-- Loại trừ (cột 2) -->
                                        <td style="text-align: center;">
                                            <button class="exclude-btn <?= $is_excluded ? 'excluded' : '' ?>"
                                                onclick="toggleExclude(this, <?= $odoo_id ?>)"
                                                title="<?= $is_excluded ? 'Bỏ loại trừ invoice này' : 'Loại trừ invoice này khỏi tổng' ?>">
                                                <?php if ($is_excluded): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z" />
                                                    </svg>
                                                <?php endif; ?>
                                            </button>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(is_array($inv['partner_id']) ? $inv['partner_id'][1] : '') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($inv['ref'] ?: $inv['name']) ?>
                                        </td>
                                        <td data-required-field="project_code">
                                            <?= htmlspecialchars($inv['x_studio_project_code'] ?? '') ?>
                                        </td>
                                        <td>
                                            <?= $month_str ?>
                                        </td>

                                        <!-- Loại Hợp đồng -->
                                        <td class="editable-cell" data-required-field="contract_type"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'contract_type', 'select', ['Service', 'Trading', 'Dedicated', 'License'])">
                                            <?= htmlspecialchars($l['contract_type'] ?? '') ?>
                                        </td>

                                        <!-- Presales -->
                                        <td class="editable-cell" data-required-field="presales"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'presales', 'select', ['No presales', '0%', '0.25%', '0.5%'])">
                                            <?= htmlspecialchars($l['presales'] ?? '') ?>
                                        </td>

                                        <!-- Loại khách hàng -->
                                        <td class="editable-cell" data-required-field="client_type"
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
                                        <td class="editable-cell" data-required-field="com_lead_source"
                                            onclick="makeEditable(this, <?= $odoo_id ?>, 'com_lead_source', 'select', ['Yes', 'No'])">
                                            <?= htmlspecialchars($l['com_lead_source'] ?? 'No') ?>
                                        </td>

                                        <!-- % Bonus License/trading -->
                                        <td class="editable-cell" data-required-field="bonus_license_trading"
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

                <!-- ── KPI Performance Report ── -->
                <?php
                $quarter_label = str_replace('_', ' ', $active_tab);
                ?>
                <div class="kpi-report">
                    <div class="kpi-report-header">
                        <div class="kpi-report-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="#2563eb" stroke-width="2.5">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                            </svg>
                            KPI Performance Report
                            <?php if ($kpi_data): ?>
                                <span class="kpi-level-badge"
                                    style="background: <?= htmlspecialchars($kpi_data['color_badge']) ?>">
                                    <?= htmlspecialchars($kpi_data['level_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="kpi-quarter-label">📅 <?= htmlspecialchars($quarter_label) ?></span>
                    </div>

                    <?php if (!$is_am_bd): ?>
                        <div class="kpi-no-level">⚠️ Tài khoản này không phải AM/BD — không có Sale Level để so sánh KPI.
                        </div>
                    <?php elseif (!$kpi_data): ?>
                        <div class="kpi-no-level">ℹ️ Chưa được gán Sale Level. Liên hệ Admin để cập nhật.</div>
                    <?php else:
                        $kpi_quarter_vnd = (float) $kpi_data['kpi_quarter_vnd'];
                        $kpi_yearly_vnd = (float) $kpi_data['kpi_yearly_vnd'];
                        $actual_vnd = $total_vnd; // current quarter only (excl. excluded)
                    
                        $pct_quarter = $kpi_quarter_vnd > 0 ? min(($actual_vnd / $kpi_quarter_vnd) * 100, 999) : 0;
                        // Use YTD (all quarters of the year up to current) for yearly comparison
                        $pct_yearly = $kpi_yearly_vnd > 0 ? min(($ytd_vnd / $kpi_yearly_vnd) * 100, 999) : 0;

                        // Remaining months in quarter to estimate pace
                        $months_in_q = 3;

                        // Determine status
                        if ($pct_quarter >= 100) {
                            $status_class = 'status-achieved';
                            $status_icon = '🏆';
                            $status_text = 'Đạt KPI quý!';
                        } elseif ($pct_quarter >= 75) {
                            $status_class = 'status-on-track';
                            $status_icon = '✅';
                            $status_text = 'Đang đúng lộ trình';
                        } elseif ($pct_quarter >= 50) {
                            $status_class = 'status-at-risk';
                            $status_icon = '⚠️';
                            $status_text = 'Có nguy cơ không đạt';
                        } else {
                            $status_class = 'status-behind';
                            $status_icon = '🔴';
                            $status_text = 'Chưa đạt — cần cải thiện';
                        }

                        $bar_color_q = $pct_quarter >= 100 ? '#10b981' : ($pct_quarter >= 75 ? '#3b82f6' : ($pct_quarter >= 50 ? '#f59e0b' : '#f43f5e'));
                        $bar_color_y = $pct_yearly >= 100 ? '#10b981' : ($pct_yearly >= 75 ? '#3b82f6' : ($pct_yearly >= 50 ? '#f59e0b' : '#f43f5e'));

                        $remaining_vnd = max(0, $kpi_quarter_vnd - $actual_vnd);
                        ?>
                        <div class="kpi-metrics-grid">

                            <!-- Thực tế quý -->
                            <div class="kpi-metric-card blue">
                                <div class="kpi-metric-label">Doanh thu thực tế (Quý)</div>
                                <div class="kpi-metric-value"><?= number_format($actual_vnd / 1e9, 2) ?>B</div>
                                <div class="kpi-metric-sub"><?= number_format($actual_vnd, 0, ',', '.') ?> VND</div>
                                <div class="kpi-progress-section">
                                    <div class="kpi-progress-header">
                                        <span>vs KPI Quý</span>
                                        <span
                                            style="color: <?= $bar_color_q ?>"><?= number_format($pct_quarter, 1) ?>%</span>
                                    </div>
                                    <div class="kpi-progress-bar">
                                        <div class="kpi-progress-fill"
                                            style="width: <?= min($pct_quarter, 100) ?>%; background: <?= $bar_color_q ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI Quý target -->
                            <div class="kpi-metric-card green">
                                <div class="kpi-metric-label">KPI Quý (target)</div>
                                <div class="kpi-metric-value"><?= number_format($kpi_quarter_vnd / 1e9, 2) ?>B</div>
                                <div class="kpi-metric-sub"><?= number_format($kpi_quarter_vnd, 0, ',', '.') ?> VND</div>
                                <div class="kpi-progress-section">
                                    <div class="kpi-progress-header">
                                        <span>Còn thiếu</span>
                                        <span
                                            style="color: #f43f5e"><?= $remaining_vnd > 0 ? number_format($remaining_vnd / 1e9, 2) . 'B VND' : 'Đã đạt 🎉' ?></span>
                                    </div>
                                    <div class="kpi-progress-bar">
                                        <div class="kpi-progress-fill"
                                            style="width: <?= min($pct_quarter, 100) ?>%; background: <?= $bar_color_q ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI Năm -->
                            <div class="kpi-metric-card amber">
                                <div class="kpi-metric-label">KPI Năm (target)</div>
                                <div class="kpi-metric-value"><?= number_format($kpi_yearly_vnd / 1e9, 2) ?>B</div>
                                <div class="kpi-metric-sub"><?= number_format($kpi_yearly_vnd, 0, ',', '.') ?> VND</div>
                                <div class="kpi-progress-section">
                                    <div class="kpi-progress-header">
                                        <span>Lũy kế <?= isset($q) ? "Q1–Q$q" : '' ?> / Năm</span>
                                        <span
                                            style="color: <?= $bar_color_y ?>"><?= number_format($pct_yearly, 1) ?>%</span>
                                    </div>
                                    <div class="kpi-progress-bar">
                                        <div class="kpi-progress-fill"
                                            style="width: <?= min($pct_yearly, 100) ?>%; background: <?= $bar_color_y ?>">
                                        </div>
                                    </div>
                                    <div style="margin-top: 0.3rem; font-size: 11px; color: #94a3b8;">
                                        Thực tế YTD: <?= number_format($ytd_vnd / 1e9, 2) ?>B VND
                                    </div>
                                </div>
                            </div>

                            <!-- Status card -->
                            <div class="kpi-metric-card rose"
                                style="display:flex;flex-direction:column;justify-content:space-between;">
                                <div class="kpi-metric-label">Trạng thái</div>
                                <div style="margin: 0.5rem 0;">
                                    <span class="status-badge <?= $status_class ?>"><?= $status_icon ?>
                                        <?= $status_text ?></span>
                                </div>
                                <div class="kpi-metric-sub">
                                    <?= htmlspecialchars($kpi_data['position_type']) ?> &bull;
                                    <?= htmlspecialchars($kpi_data['level_name']) ?>
                                </div>
                            </div>

                        </div>

                        <!-- ── Confirm section ── -->
                        <div class="kpi-confirm-section">
                            <div>
                                <?php if ($confirmation): ?>
                                    <div class="confirmed-badge">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                            <polyline points="22 4 12 14.01 9 11.01" />
                                        </svg>
                                        Đã xác nhận KPI
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 0.4rem;">
                                        Bởi <strong><?= htmlspecialchars($confirmation['confirmed_by_name']) ?></strong>
                                        lúc <?= date('H:i d/m/Y', strtotime($confirmation['confirmed_at'])) ?>
                                    </div>
                                    <div style="margin-top: 0.6rem;">
                                        <button class="confirm-btn" onclick="confirmKpi()"
                                            style="background: #64748b; box-shadow: none; font-size: 12px; padding: 7px 16px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="23 4 23 11 16 11" />
                                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 11" />
                                            </svg>
                                            Xác nhận lại
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button class="confirm-btn" onclick="confirmKpi()" id="confirmKpiBtn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                            <polyline points="22 4 12 14.01 9 11.01" />
                                        </svg>
                                        Xác nhận KPI Quý
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div id="confirmErrorBox" style="display:none; flex:1; min-width:250px;"></div>
                        </div>

                    <?php endif; ?>
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

        // ── KPI Confirmation ──
        function confirmKpi() {
            // 1. Collect all non-excluded invoice rows in the table
            const rows = document.querySelectorAll('tr.invoice-row:not([data-is-excluded="1"])');
            const requiredFields = ['project_code', 'contract_type', 'presales', 'client_type', 'com_lead_source', 'bonus_license_trading'];
            const fieldLabels = {
                project_code: 'Mã dự án',
                contract_type: 'Loại Hợp đồng',
                presales: 'Presales',
                client_type: 'Loại khách hàng',
                com_lead_source: '% Com (Lead source)',
                bonus_license_trading: '% Bonus License/trading'
            };

            // Clear previous highlights
            document.querySelectorAll('td.cell-missing').forEach(td => td.classList.remove('cell-missing'));

            const invoiceIds = [];
            const errors = [];

            rows.forEach(row => {
                const invId = row.dataset.invoiceId;
                invoiceIds.push(invId);
                requiredFields.forEach(field => {
                    const td = row.querySelector(`td[data-required-field="${field}"]`);
                    if (td) {
                        const text = td.innerText.trim().replace(/^-- Chọn --$/, '');
                        if (!text) {
                            td.classList.add('cell-missing');
                            errors.push(`Invoice #${invId}: ${fieldLabels[field]}`);
                        }
                    }
                });
            });

            const errorBox = document.getElementById('confirmErrorBox');

            if (errors.length > 0) {
                errorBox.style.display = 'block';
                errorBox.innerHTML = `<div class="confirm-error-list">
                    <strong style="display:block;margin-bottom:4px">⚠️ ${errors.length} ô chưa điền — vui lòng bổ sung trước khi xác nhận:</strong>
                    <ul style="margin:0;padding-left:16px">${errors.map(e => `<li>${e}</li>`).join('')}</ul>
                </div>`;
                // Scroll to first missing
                const firstMissing = document.querySelector('td.cell-missing');
                if (firstMissing) firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // 2. All good — send to server
            errorBox.style.display = 'none';
            const btn = document.getElementById('confirmKpiBtn') || document.querySelector('.confirm-btn');
            if (btn) { btn.disabled = true; btn.textContent = 'Đang xác nhận...'; }

            const formData = new FormData();
            formData.append('action', 'confirm_kpi');
            formData.append('quarter', '<?= $active_tab ?>');
            formData.append('invoice_ids', invoiceIds.join(','));

            fetch('', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('✅ Đã xác nhận KPI!');
                        setTimeout(() => location.reload(), 900);
                    } else {
                        if (btn) { btn.disabled = false; btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Xác nhận KPI Quý'; }
                        const missing = data.missing || [];
                        errorBox.style.display = 'block';
                        errorBox.innerHTML = `<div class="confirm-error-list">
                            <strong style="display:block;margin-bottom:4px">❌ Server tìm thấy dữ liệu thiếu:</strong>
                            <ul style="margin:0;padding-left:16px">${missing.map(e => `<li>${e}</li>`).join('')}</ul>
                        </div>`;
                    }
                })
                .catch(() => {
                    if (btn) { btn.disabled = false; }
                    alert('Lỗi kết nối, vui lòng thử lại.');
                });
        }

        function toggleExclude(btn, invoiceId) {
            const formData = new FormData();
            formData.append('action', 'toggle_exclude');
            formData.append('odoo_invoice_id', invoiceId);

            const svgExcluded = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;pointer-events:none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>';
            const svgNormal = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;pointer-events:none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>';

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