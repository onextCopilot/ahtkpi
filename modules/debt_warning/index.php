<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';

$odoo = new OdooAPI();

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// Fetch latest avatar from DB to ensure it's up to date
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $avatar = $row['avatar'];
    $_SESSION['avatar'] = $avatar; // Sync session
} else {
    $avatar = $_SESSION['avatar'] ?? null;
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Fetch all sale teams
$all_teams = [];
$team_res = $conn->query("SELECT * FROM sale_teams ORDER BY name ASC");
if ($team_res) {
    while ($tr = $team_res->fetch_assoc())
        $all_teams[] = $tr;
}

// --- DB INIT ---
$table_check = $conn->query("SHOW TABLES LIKE 'debts'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE debts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(50) DEFAULT 'AHT TECH',
        am VARCHAR(100),
        sale_team_id INT DEFAULT NULL,
        client_name VARCHAR(255),
        project_name VARCHAR(255),
        payment_milestone VARCHAR(255),
        expected_prod_date DATE,
        expected_payment_date DATE,
        invoice_status_class VARCHAR(50), -- D5, Tốt...
        amount DECIMAL(15, 2) DEFAULT 0,
        pl_class VARCHAR(50), -- TB, Xấu...
        invoice_status VARCHAR(50),
        vat_invoice VARCHAR(50),
        payment_status VARCHAR(50), -- Not paid
        payment_month VARCHAR(50),
        weekly_update VARCHAR(50),
        am_notes TEXT,
        delivery_notes TEXT,
        production_status VARCHAR(100), -- DC5 ONFIT...
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
} else {
    // Check and add sale_team_id if not exists
    $check_col = $conn->query("SHOW COLUMNS FROM debts LIKE 'sale_team_id'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE debts ADD COLUMN sale_team_id INT DEFAULT NULL AFTER am");
    }
}

// --- HANDLE POST (Add/Edit/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD & EDIT
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
        $company = $_POST['company'] ?? 'AHT TECH';
        $am = $_POST['am'];
        $client = $_POST['client_name'];
        $project = $_POST['project_name'];
        $milestone = $_POST['payment_milestone'];
        $prod_date = !empty($_POST['expected_prod_date']) ? $_POST['expected_prod_date'] : NULL;
        $pay_date = !empty($_POST['expected_payment_date']) ? $_POST['expected_payment_date'] : NULL;
        $inv_class = $_POST['invoice_status_class'];
        $amount = floatval($_POST['amount']);
        $pl = $_POST['pl_class'];
        $inv_stat = $_POST['invoice_status'] ?? '';
        $invoice_date_val = $_POST['invoice_date'] ?? null;
        if (empty($invoice_date_val))
            $invoice_date_val = null;
        $vat = $_POST['vat_invoice'] ?? '';
        $pay_stat = $_POST['payment_status'];
        $pay_month = $_POST['payment_month'] ?? '';
        $weekly = $_POST['weekly_update'] ?? '';
        $am_note = $_POST['am_notes'] ?? '';
        $del_note = $_POST['delivery_notes'] ?? '';
        $prod_stat = $_POST['production_status'] ?? '';
        $currency_val = $_POST['currency'] ?? 'USD';
        $sale_team_id = !empty($_POST['sale_team_id']) ? intval($_POST['sale_team_id']) : NULL;

        // Lookup am_email từ full_name trong bảng users
        $am_email = '';
        if (!empty($am)) {
            $stmt_em = $conn->prepare("SELECT email FROM users WHERE full_name = ? LIMIT 1");
            $stmt_em->bind_param("s", $am);
            $stmt_em->execute();
            $res_em = $stmt_em->get_result();
            if ($row_em = $res_em->fetch_assoc()) {
                $am_email = $row_em['email'];
            }
            $stmt_em->close();
        }

        if ($_POST['action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO debts (company, am, am_email, sale_team_id, client_name, project_name, payment_milestone, expected_prod_date, expected_payment_date, invoice_status_class, amount, currency, invoice_status, vat_invoice, invoice_date, payment_status, payment_month, weekly_update, am_notes, delivery_notes, production_status, pl_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssissssssdsssssssssss", $company, $am, $am_email, $sale_team_id, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl);
        } else {
            // Edit
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE debts SET company=?, am=?, am_email=?, sale_team_id=?, client_name=?, project_name=?, payment_milestone=?, expected_prod_date=?, expected_payment_date=?, invoice_status_class=?, amount=?, currency=?, invoice_status=?, vat_invoice=?, invoice_date=?, payment_status=?, payment_month=?, weekly_update=?, am_notes=?, delivery_notes=?, production_status=?, pl_class=? WHERE id=?");
            $stmt->bind_param("sssissssssdsssssssssssi", $company, $am, $am_email, $sale_team_id, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl, $id);
        }

        if ($stmt->execute()) {
            header("Location: /debt");
            exit();
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }

    // DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM debts WHERE id=$id");
        header("Location: /debt");
        exit();
    }
}

// --- FILTERING & FETCH DATA ---
$where_clauses = [];

// ACL
$can_view_all_debts = isset($_SESSION['can_view_all_debts']) && $_SESSION['can_view_all_debts'] == 1;
if ($_SESSION['role'] === 'admin') {
    $can_view_all_debts = true;
}

$user_teams = [];
if (!$can_view_all_debts) {
    if (isset($_SESSION['is_am_bd']) && $_SESSION['is_am_bd'] == 1) {
        $am_name_esc = $conn->real_escape_string($_SESSION['full_name']);
        $where_clauses[] = "d.am = '$am_name_esc'";
    } else {
        $ut_res = $conn->prepare("SELECT team_id FROM user_sale_teams WHERE user_id = ?");
        $ut_res->bind_param("i", $current_user_id);
        $ut_res->execute();
        $ut_result = $ut_res->get_result();
        while ($r = $ut_result->fetch_assoc()) {
            $user_teams[] = $r['team_id'];
        }

        if (count($user_teams) > 0) {
            $in_teams = implode(',', $user_teams);
            $where_clauses[] = "d.sale_team_id IN ($in_teams)";
        } else {
            $where_clauses[] = "1=0"; // No access to any team's data
        }
    }
}

if (!empty($_GET['am'])) {
    $am_filter = $conn->real_escape_string($_GET['am']);
    $where_clauses[] = "d.am = '$am_filter'";
}
if (!empty($_GET['invoice_status_class'])) {
    $inv_class_filter = $conn->real_escape_string($_GET['invoice_status_class']);
    if ($inv_class_filter === 'Xanh') {
        $where_clauses[] = "(d.invoice_status_class = 'Xanh' OR d.invoice_status_class = 'Tốt')";
    } else {
        $where_clauses[] = "d.invoice_status_class = '$inv_class_filter'";
    }
}

if (!empty($_GET['status'])) {
    $status_filter = $conn->real_escape_string($_GET['status']);
    $where_clauses[] = "d.payment_status = '$status_filter'";
}

if (!empty($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $where_clauses[] = "(d.client_name LIKE '%$search%' OR d.project_name LIKE '%$search%' OR d.vat_invoice LIKE '%$search%')";
}

if (!empty($_GET['year'])) {
    $year = intval($_GET['year']);
    $where_clauses[] = "YEAR(d.invoice_date) = $year";
}

if (!empty($_GET['quarter'])) {
    $qtr = intval($_GET['quarter']);
    if ($qtr == 1)
        $where_clauses[] = "MONTH(d.invoice_date) IN (1,2,3)";
    elseif ($qtr == 2)
        $where_clauses[] = "MONTH(d.invoice_date) IN (4,5,6)";
    elseif ($qtr == 3)
        $where_clauses[] = "MONTH(d.invoice_date) IN (7,8,9)";
    elseif ($qtr == 4)
        $where_clauses[] = "MONTH(d.invoice_date) IN (10,11,12)";
}

if (!empty($_GET['month'])) {
    $month = intval($_GET['month']);
    $where_clauses[] = "MONTH(d.invoice_date) = $month";
}

if (!empty($_GET['week'])) {
    $week_number = intval($_GET['week']);
    $where_clauses[] = "(d.weekly_update LIKE '%Tuần $week_number%' OR d.weekly_update LIKE '%tuần $week_number%' OR d.weekly_update = '$week_number' OR d.weekly_update LIKE '%W$week_number%' OR d.weekly_update LIKE '%w$week_number%')";
}

$selected_team = $_GET['team'] ?? 'dashboard'; // Default to dashboard as requested

if (!$can_view_all_debts && !in_array($selected_team, ['dashboard', 'analytics'])) {
    if (!in_array($selected_team, $user_teams)) {
        $selected_team = 'dashboard';
    }
}

if ($selected_team !== 'all' && $selected_team !== 'dashboard' && $selected_team !== 'analytics') {
    if ($selected_team === 'undefined') {
        $where_clauses[] = "d.sale_team_id IS NULL";
    } else {
        $tid = intval($selected_team);
        $where_clauses[] = "d.sale_team_id = $tid";
    }
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$warningLevel30 = [];
$warningLevel60 = [];
$warningEmpty = [];
$total_warning_30 = 0;
$total_warning_60 = 0;
$total_warning_empty = 0;

$where_clauses[] = "d.payment_status = 'Not paid'";

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Ensure acl
if ($_SESSION['role'] !== 'admin' && empty($_SESSION['is_am_bd'])) {
    die("Access Denied");
}

$res = $conn->query("SELECT d.*, st.name as team_name 
                    FROM debts d 
                    LEFT JOIN sale_teams st ON d.sale_team_id = st.id 
                    $where_sql 
                    ORDER BY d.expected_payment_date ASC, d.id DESC");

$odoo_map = $odoo->getInvoiceMap();

if ($res) {
    $now = new DateTime();
    $now->setTime(0, 0, 0);
    while ($row = $res->fetch_assoc()) {
        $amount = (float) $row['amount'];
        $curr = $row['currency'] ?: 'USD';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
        $oid = $row['odoo_invoice_id'];

        // Convert to VND using Odoo ratio if available
        $vnd_value = 0;
        $vnd_multiplier = $odoo->getRate('VND', $date);

        if ($curr === 'VND') {
            $vnd_value = $amount;
        } else if (!empty($oid) && isset($odoo_map[$oid])) {
            $odoo_inv = $odoo_map[$oid];
            $odoo_total = (float) $odoo_inv['amount_total'];
            $odoo_signed = abs((float) $odoo_inv['amount_total_signed']);

            if ($odoo_total > 0) {
                $ratio = abs($odoo_signed / $odoo_total);
                if ($ratio > 100) {
                    // Ratio is high, likely already in VND (e.g. 25000 for USD)
                    $vnd_value = $amount * $ratio;
                } else {
                    // Ratio is low, likely in intermediate currency (e.g. 1.0 for MYR, 4.7 for USD to MYR base)
                    $vnd_value = $amount * $ratio * $vnd_multiplier;
                }
            }
        }

        // Fallback to manual rate calculation
        if ($vnd_value <= 0) {
            $rate = $odoo->getRate($curr, $date);
            $vnd_value = ($rate > 0) ? (($amount / $rate) * $vnd_multiplier) : $amount;
        }

        $row['amount_original'] = $amount;
        $row['currency_original'] = $curr;
        $row['amount'] = $vnd_value;
        $row['currency'] = 'VND';

        $exp_date_str = $row['expected_payment_date'];
        if (empty($exp_date_str) || trim($exp_date_str) === '' || $exp_date_str === '0000-00-00' || is_null($exp_date_str)) {
            $warningEmpty[] = $row;
            $total_warning_empty += $vnd_value;
        } else {
            $exp_date = new DateTime($exp_date_str);
            $exp_date->setTime(0, 0, 0);
            $diff = $now->diff($exp_date);

            // if invert == 1, $exp_date is earlier than $now (quá hạn)
            if ($diff->invert && $diff->days > 60) {
                $warningLevel60[] = $row;
                $total_warning_60 += $vnd_value;
            } elseif ($diff->invert && $diff->days > 30) {
                $warningLevel30[] = $row;
                $total_warning_30 += $vnd_value;
            }
        }
    }
}
$total_amount_vnd = $total_warning_30 + $total_warning_60 + $total_warning_empty;

// Helper for formatting currency
function formatCurrency($amount, $curr = 'USD')
{
    if ($curr === 'VND') {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }
    return '$' . number_format($amount, 2);
}

function formatVND($amount)
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Helper for Date display
function formatDate($date)
{
    return ($date && $date != '0000-00-00') ? date('d/m/Y', strtotime($date)) : '';
}

// Fetch AM / BD Users
$am_list = [];
$res_am = $conn->query("SELECT full_name FROM users WHERE is_am_bd = 1 ORDER BY full_name ASC");
if ($res_am && $res_am->num_rows > 0) {
    while ($row_am = $res_am->fetch_assoc()) {
        $n = trim($row_am['full_name']);
        if (!empty($n) && !in_array($n, $am_list)) {
            $am_list[] = $n;
        }
    }
} else {
    // Fallback if none found
    $am_list = ['Emily', 'Ryan', 'Hyun'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debt Management</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Prevent horizontal scroll on page */
        body {
            overflow-x: hidden;
        }

        .main-content {
            overflow-x: hidden;
        }

        /* Custom Table Styling to match screenshot */
        .debt-container {
            padding: 1rem;
            max-width: 100%;
            height: calc(100vh - 80px);
            /* Fill screen */
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            /* Prevent horizontal scroll on container */
        }

        .data-table-wrapper {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            flex: 1;
            overflow-x: auto;
            overflow-y: auto;
            position: relative;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            /* Softer shadow */
        }

        table.debt-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            /* Slightly larger text */
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            color: #334155;
        }

        /* Sticky Header */
        table.debt-table thead th {
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            /* Modern light gray header */
            color: #475569;
            /* Gray text */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            z-index: 10;
            white-space: normal;
            line-height: 1.4;
            vertical-align: middle;
            min-width: 120px;
        }

        table.debt-table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #1e293b;
            transition: background-color 0.15s;
        }

        /* Subtle Striping */
        table.debt-table tbody tr:nth-child(even) td {
            background-color: #fafafa;
        }

        table.debt-table tbody tr:hover td {
            background-color: #f1f5f9;
            cursor: pointer;
        }

        .cell-company {
            font-weight: 600;
            color: #0f172a;
        }

        .cell-amount {
            font-weight: 700;
            color: #0f172a;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* Tabs Styling - Modern Segmented Control */
        .team-tabs {
            display: inline-flex;
            flex-wrap: nowrap;
            gap: 4px;
            margin: 10px 0 20px 0;
            padding: 6px;
            overflow-x: auto;
            background: #f1f5f9;
            /* Modern gray track */
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            width: fit-content;
            max-width: 100%;
        }

        .team-tab {
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
        }

        .team-tab:hover {
            color: #334155;
            background: rgba(255, 255, 255, 0.6);
        }

        .team-tab.active {
            color: #0f172a;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            font-weight: 600;
            border-color: rgba(226, 232, 240, 0.8);
        }

        .tab-count {
            margin-left: 0;
            font-size: 0.75rem;
            background: rgba(148, 163, 184, 0.2);
            color: #475569;
            padding: 2px 8px;
            border-radius: 99px;
            /* Pill shape */
            transition: all 0.2s;
            min-width: 20px;
            text-align: center;
            line-height: 1.4;
        }

        .team-tab.active .tab-count {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 600;
        }

        /* Scrollbar for Tabs */
        .team-tabs::-webkit-scrollbar {
            height: 4px;
            /* Thin horizontal scrollbar */
            display: none;
            /* Hide by default, show on hover maybe? Or keep hidden for cleaner look on Mac */
        }

        /* On Mac, default scrollbars are often hidden anyway. */

        /* Dashboard Specific Styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(600px, 1fr));
            gap: 30px;
            padding: 10px 0;
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .dashboard-card-header {
            background: #fef08a;
            /* Yellowish like the image */
            padding: 12px 20px;
            border-bottom: 2px solid #eab308;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-card-title {
            color: #b91c1c;
            /* Reddish like the image */
            font-weight: 800;
            font-style: italic;
            font-size: 1.1rem;
        }

        .dashboard-card-subtitle {
            font-weight: 700;
            font-style: italic;
            color: #0f172a;
        }

        .db-table {
            width: 100%;
            border-collapse: collapse;
        }

        .db-table th {
            background: #64748b;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 0.85rem;
            border: 1px solid #475569;
        }

        .db-table td {
            padding: 10px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
            text-align: right;
        }

        .db-table td:first-child {
            text-align: left;
            font-weight: 600;
            background: #f8fafc;
            width: 200px;
        }

        .db-table tr.total-row {
            background: #f1f5f9;
            font-weight: 800;
        }

        .db-table tr.total-row td {
            border-top: 2px solid #94a3b8;
        }

        /* Badges/Pills */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
        }

        /* AM Colors */
        .am-badge {
            padding: 2px 8px;
            border-radius: 12px;
        }

        .am-emily {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .am-ryan {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .am-hyun {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Group Headers */
        .group-header td {
            background-color: #e2e8f0 !important;
            font-weight: 700;
            color: #0f172a;
            padding: 12px 16px !important;
            border-top: none;
            border-bottom: 2px solid #cbd5e1;
            font-size: 13px;
        }

        .group-header-am td {
            background-color: #f8fafc !important;
            font-weight: 600;
            color: #475569;
            padding: 10px 16px 10px 24px !important;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }

        .group-total {
            color: #10b981;
            margin-left: 10px;
        }

        /* Status Colors (Same as My Debt) */
        .status-done {
            background-color: #16a34a;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            width: 110px;
            text-align: center;
        }

        .status-tim {
            background-color: #a855f7;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            width: 110px;
            text-align: center;
        }

        .status-xanh {
            background-color: #2563eb;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            width: 110px;
            text-align: center;
        }

        .status-trang {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            width: 110px;
            text-align: center;
        }

        .status-chuaxacdinh {
            background-color: #f8fafc;
            color: #94a3b8;
            border: 1px solid #e2e8f0;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            width: 110px;
            text-align: center;
        }

        .status-do {
            background-color: #ef4444;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            width: 110px;
            text-align: center;
        }

        .status-select {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            width: 110px;
            text-align: center;
        }

        .status-select:focus {
            outline: 2px solid #bfdbfe;
        }

        .pl-tb {
            background-color: #fff7ed;
            color: #9a3412;
            border: 1px solid #ffedd5;
        }

        .pl-xau {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .pl-tot {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .pay-not-paid {
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .pay-paid {
            background-color: #dcfce7;
            color: #166534;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .prod-dc5 {
            background-color: #b91c1c;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }

        .prod-dc1 {
            background-color: #15803d;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }

        .prod-dc2 {
            background-color: #b45309;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }

        /* Controls */
        .page-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select,
        .search-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-input {
            width: 250px;
        }

        .filter-select:focus,
        .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-select {
            color: #475569;
            cursor: pointer;
        }

        .btn-add {
            background: #0f172a;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-add:hover {
            background: #334155;
        }

        .total-badge {
            margin-left: auto;
            margin-right: 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #065f46;
            background: #ecfdf5;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #10b981;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background-color: #fff;
            margin: 4vh auto;
            padding: 0;
            border: none;
            width: 600px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #0f172a;
        }

        .close {
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover {
            color: #0f172a;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 0 0 12px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: #475569;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3b82f6;
            outline: none;
        }

        .btn-cancel {
            padding: 0.6rem 1.2rem;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-submit {
            padding: 0.6rem 1.5rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .btn-delete {
            color: #ef4444;
            background: none;
            border: none;
            font-weight: 500;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-delete:hover {
            text-decoration: underline;
        }

        /* Actions Column */
        .btn-edit-row {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }

        .btn-edit-row:hover {
            color: #0f172a;
            background: #e2e8f0;
        }

        /* Scrollbar */
        .data-table-wrapper::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .data-table-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .data-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .btn-warn {
            background: none;
            border: none;
            color: #eab308;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .btn-warn:hover {
            color: #ca8a04;
            background: #fef9c3;
            transform: scale(1.1);
        }

        .btn-warn.sent {
            color: #2563eb;
            background: #dbeafe;
        }

        .btn-warn.loading {
            opacity: 0.5;
            cursor: wait;
        }

        .btn-bulk-warn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-size: 0.875rem;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .btn-bulk-warn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-bulk-warn:active {
            transform: translateY(0);
        }

        /* ── User Guide Section ── */
        .user-guide-section {
            margin: 2rem 1rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            line-height: 1.6;
            max-width: 100%;
        }

        .user-guide-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .user-guide-header h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .guide-item h3 {
            margin: 0 0 12px 0;
            font-size: 15px;
            font-weight: 700;
            color: #2563eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .guide-content {
            font-size: 13px;
            color: #475569;
        }

        .guide-content ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .guide-content li {
            margin-bottom: 8px;
        }

        .level-tag {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            margin-right: 4px;
        }

        .lv-1 {
            background: #fef9c3;
            color: #a16207;
        }

        .lv-2 {
            background: #ffedd5;
            color: #c2410c;
        }

        .lv-crit {
            background: #fee2e2;
            color: #b91c1c;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Debts Warning';

            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : '60_days';

            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="debt-container">
                <div class="page-controls">
                    <form method="GET" class="filter-group">
                        <input type="text" name="q" class="search-input"
                            placeholder="Search Client, Project, Invoice..."
                            value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">

                        <select name="am" class="filter-select" onchange="this.form.submit()">
                            <option value="">AM: All</option>
                            <?php foreach ($am_list as $am_name): ?>
                                <option value="<?php echo htmlspecialchars($am_name); ?>" <?php echo (isset($_GET['am']) && $_GET['am'] === $am_name) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($am_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">Status: All</option>
                            <option value="Not paid" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Not paid') ? 'selected' : ''; ?>>Not paid</option>
                            <option value="Paid" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                        </select>

                        <select name="invoice_status_class" class="filter-select" onchange="this.form.submit()">
                            <option value="">Phân loại HĐ: All</option>
                            <option value="Trắng" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Trắng') ? 'selected' : ''; ?>>Trắng</option>
                            <option value="Xanh" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Xanh') ? 'selected' : ''; ?>>Xanh (Tốt)</option>
                            <option value="Tím" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Tím') ? 'selected' : ''; ?>>Tím</option>
                            <option value="Đỏ" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Đỏ') ? 'selected' : ''; ?>>Đỏ</option>
                            <option value="PP" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'PP') ? 'selected' : ''; ?>>PP</option>
                            <option value="Draft" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="Done" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Done') ? 'selected' : ''; ?>>Done</option>
                            <option value="Chưa xác định" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Chưa xác định') ? 'selected' : ''; ?>>Chưa xác định
                            </option>
                        </select>

                        <select name="year" class="filter-select" onchange="this.form.submit()">
                            <option value="">Year: All</option>
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                $sel = (isset($_GET['year']) && $_GET['year'] == $y) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>

                        <select name="quarter" class="filter-select" onchange="this.form.submit()">
                            <option value="">Quarter: All</option>
                            <option value="1" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '1') ? 'selected' : ''; ?>>Q1</option>
                            <option value="2" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '2') ? 'selected' : ''; ?>>Q2</option>
                            <option value="3" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '3') ? 'selected' : ''; ?>>Q3</option>
                            <option value="4" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '4') ? 'selected' : ''; ?>>Q4</option>
                        </select>

                        <select name="month" class="filter-select" onchange="this.form.submit()">
                            <option value="">Month: All</option>
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $sel = (isset($_GET['month']) && $_GET['month'] == $m) ? 'selected' : '';
                                $mName = date('F', mktime(0, 0, 0, $m, 1));
                                echo "<option value='$m' $sel>$mName</option>";
                            }
                            ?>
                        </select>

                        <select name="week" class="filter-select" onchange="this.form.submit()">
                            <option value="">Tuần: All</option>
                            <?php
                            for ($w = 1; $w <= 5; $w++) {
                                $sel = (isset($_GET['week']) && $_GET['week'] == $w) ? 'selected' : '';
                                echo "<option value='$w' $sel>Tuần $w</option>";
                            }
                            ?>
                        </select>
                        <button type="submit" style="display:none;"></button>
                    </form>
                    <?php
                    $tabs = [
                        '60_days' => ['title' => 'Nợ xấu > 60 ngày', 'data' => $warningLevel60, 'total' => $total_warning_60],
                        '30_days' => ['title' => 'Quá hạn 30 ngày', 'data' => $warningLevel30, 'total' => $total_warning_30],
                        'empty' => ['title' => 'Chưa có ngày thanh toán', 'data' => $warningEmpty, 'total' => $total_warning_empty],
                    ];
                    $current_tab_total = isset($tabs[$active_tab]) ? $tabs[$active_tab]['total'] : $total_amount_vnd;
                    ?>
                    <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
                        <button type="button" class="btn-bulk-warn" onclick="sendBulkWarning()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            Gửi thông báo đã chọn
                        </button>
                        <div class="total-badge" style="margin: 0;">
                            Total: <?php echo formatVND($current_tab_total); ?>
                        </div>
                    </div>
                </div>


                <div class="table-wrapper"
                    style="overflow: visible; padding-bottom: 10px; border: none; background: transparent; box-shadow: none;">
                    <div class="team-tabs" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($tabs as $key => $tab): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => $key])); ?>"
                                class="team-tab <?php echo ($active_tab === $key) ? 'active' : ''; ?>">
                                <?php echo $tab['title']; ?> (<?php echo count($tab['data']); ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="debt-table">
                        <thead>
                            <tr>
                                <th style="width: 30px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                                <th style="width: 30px !important; text-align: center;">#</th>
                                <th style="width: 50px; text-align: center;">Warning</th>
                                <th>CTY</th>
                                <th>AM</th>
                                <th>Sale Team</th>
                                <th>Tên khách hàng</th>
                                <th>Tên dự án</th>
                                <th>Ngày hóa đơn</th>
                                <th>Mốc thanh toán</th>
                                <th>Exp. Prod Date</th>
                                <th>Exp. Pay Date</th>
                                <th>Phân loại HĐ</th>
                                <th>Số tiền</th>
                                <th>P&L</th>
                                <th>Hóa đơn</th>
                                <th>HĐ VAT</th>
                                <th>Trạng thái TT</th>
                                <th>Tháng TT</th>
                                <th>Cập nhật tuần</th>
                                <th>Ghi chú AM</th>
                                <th>Ghi chú Delivery</th>
                                <th>Trạng thái SX</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_data = $tabs[$active_tab]['data'];
                            $globalIdx = 1;
                            ?>
                            <?php if (count($current_data) > 0): ?>
                                <?php foreach ($current_data as $item): ?>
                                    <tr style="user-select: none;">
                                        <td style="text-align: center;">
                                            <input type="checkbox" class="debt-checkbox" value="<?= $item['id'] ?>">
                                        </td>
                                        <td style="text-align: center; color: #94a3b8; font-weight: 500;">
                                            <?php echo $globalIdx++; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            $bell_color = '#eab308'; // Default
                                            if ($active_tab === '60_days')
                                                $bell_color = '#ef4444';
                                            else if ($active_tab === '30_days')
                                                $bell_color = '#f59e0b';
                                            else if ($active_tab === 'empty')
                                                $bell_color = '#3b82f6';
                                            ?>
                                            <button class="btn-warn" style="color: <?= $bell_color ?>;"
                                                onclick="sendDebtWarning(<?= $item['id'] ?>, event)"
                                                title="Gửi cảnh báo cho AM">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                                </svg>
                                            </button>
                                        </td>
                                        <td class="cell-company"><?php echo htmlspecialchars($item['company'] ?? ''); ?></td>
                                        <td>
                                            <?php
                                            $am = $item['am'] ?? '';
                                            $cls = 'am-emily';
                                            if ($am === 'Ryan')
                                                $cls = 'am-ryan';
                                            else if ($am === 'Hyun')
                                                $cls = 'am-hyun';
                                            ?>
                                            <span
                                                class="badge am-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($am ?? ''); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['team_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['client_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['project_name'] ?? ''); ?></td>
                                        <td><?php echo formatDate($item['invoice_date']); ?></td>
                                        <td><?php echo htmlspecialchars($item['payment_milestone'] ?? ''); ?></td>
                                        <td><?php echo formatDate($item['expected_prod_date']); ?></td>
                                        <td style="font-weight: bold; color: #dc2626;">
                                            <?php echo formatDate($item['expected_payment_date']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $sc = $item['invoice_status_class'];
                                            $scc = 'status-chuaxacdinh';
                                            if ($sc == 'Done')
                                                $scc = 'status-done';
                                            elseif ($sc == 'Tím')
                                                $scc = 'status-tim';
                                            elseif ($sc == 'Xanh' || $sc == 'Tốt')
                                                $scc = 'status-xanh';
                                            elseif ($sc == 'Trắng')
                                                $scc = 'status-trang';
                                            elseif ($sc == 'Đỏ')
                                                $scc = 'status-do';
                                            ?>
                                            <span class="<?php echo $scc; ?>"><?php echo htmlspecialchars($sc ?: ''); ?></span>
                                        </td>
                                        <td class="cell-amount">
                                            <?php echo formatVND($item['amount']); ?>
                                            <?php if ($item['currency_original'] === 'USD' && $item['amount_original'] > 0): ?>
                                                <div style="font-size: 10px; color: #64748b; font-weight: normal; margin-top: 2px;">
                                                    ($<?php echo number_format($item['amount_original'], 2); ?>)
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $pl = $item['pl_class'];
                                            $plc = 'pl-tb'; // Default
                                            if ($pl === 'Tốt')
                                                $plc = 'pl-tot';
                                            elseif ($pl === 'Xấu')
                                                $plc = 'pl-xau';
                                            ?>
                                            <span
                                                class="badge <?php echo $plc; ?>"><?php echo htmlspecialchars($pl ?: 'TB'); ?></span>
                                        </td>
                                        <td style="color: #64748b; font-size: 0.85rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                            title="<?php echo htmlspecialchars($item['invoice_status'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($item['invoice_status'] ?? ''); ?>
                                        </td>
                                        <td style="color: #64748b; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($item['vat_invoice'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $ps = $item['payment_status'];
                                            $psc = 'pay-not-paid';
                                            ?>
                                            <span class="<?php echo $psc; ?>"><?php echo htmlspecialchars($ps ?? ''); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['payment_month'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['weekly_update'] ?? ''); ?></td>
                                        <td
                                            style="max-width: 200px; white-space: normal; font-size: 0.8rem; color: #475569; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($item['am_notes'] ?? '')); ?>
                                        </td>
                                        <td
                                            style="max-width: 200px; white-space: normal; font-size: 0.8rem; color: #475569; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($item['delivery_notes'] ?? '')); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $prs = $item['production_status'] ?? '';
                                            $prsc = 'prod-dc1'; // default
                                            if (strpos($prs, 'DC5') !== false)
                                                $prsc = 'prod-dc5';
                                            elseif (strpos($prs, 'Thêm') !== false)
                                                $prsc = 'prod-them';
                                            ?>
                                            <span
                                                class="badge <?php echo $prsc; ?> text-xs"><?php echo htmlspecialchars($prs ?? ''); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="23" style="text-align: center; padding: 20px; color: #64748b;">
                                        Không có dữ liệu
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── HƯỚNG DẪN QUẢN LÝ CÔNG NỢ ── -->
            <section class="user-guide-section">
                <div class="user-guide-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                        stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                    <h2>Hướng dẫn quản lý & Phân loại công nợ</h2>
                </div>

                <div class="guide-grid">
                    <div class="guide-item">
                        <h3>⚠️ Các mức độ cảnh báo (Warning Levels)</h3>
                        <div class="guide-content">
                            <ul>
                                <li><strong><span class="level-tag lv-1">Level 1</span> (> 30 ngày):</strong> Công nợ đã
                                    quá hạn thanh toán trên 1 tháng so với ngày dự kiến. AM cần liên hệ nhắc nợ gấp.
                                </li>
                                <li><strong><span class="level-tag lv-2">Level 2</span> (> 60 ngày):</strong> Công nợ
                                    quá hạn trên 2 tháng. Cần có giải pháp quyết liệt hoặc tạm dừng dịch vụ nếu cần.
                                </li>
                                <li><strong><span class="level-tag lv-crit">Critical</span> (Empty Date):</strong> Các
                                    Invoice chưa được cập nhật "Ngày dự kiến thu tiền". Đây là lỗi dữ liệu cần bổ sung
                                    ngay để hệ thống theo dõi.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="guide-item">
                        <h3>📈 Phân loại P&L Level</h3>
                        <div class="guide-content">
                            <ul>
                                <li><strong>Tốt (Xanh):</strong> Khách hàng thanh toán đúng hạn trong quá khứ, rủi ro
                                    thấp.</li>
                                <li><strong>Trung bình (Cam):</strong> Khách thỉnh thoảng chậm thanh toán 1-2 tuần.</li>
                                <li><strong>Xấu (Đỏ):</strong> Khách thường xuyên nợ đọng, khó liên lạc hoặc có tranh
                                    chấp.</li>
                            </ul>
                            <p style="margin-top:8px; font-style:italic">Note: P&L Level ảnh hưởng đến xếp hạng tín dụng
                                của khách hàng trong tương lai.</p>
                        </div>
                    </div>

                    <div class="guide-item">
                        <h3>🛠 Hướng dẫn cho AM / BD</h3>
                        <div class="guide-content">
                            <ol>
                                <li>Sử dụng ô <strong>"AM Note"</strong> để cập nhật tình hình liên hệ mới nhất với
                                    khách hàng.</li>
                                <li>Theo dõi cột <strong>"Weekly Update"</strong> để biết các chỉ đạo từ cấp quản lý
                                    hoặc bộ phận kế toán.</li>
                                <li>Nút <strong>"Gửi thông báo"</strong> (Chuông): Chỉ nhấn khi đã có thông tin cụ thể
                                    để hệ thống ghi nhận vết log xử lý nợ.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const activeTab = '<?= $active_tab ?>';

        document.getElementById('selectAll').addEventListener('change', function () {
            const checked = this.checked;
            document.querySelectorAll('.debt-checkbox').forEach(cb => {
                cb.checked = checked;
            });
        });

        function sendDebtWarning(debtId, event) {
            const btn = event ? event.currentTarget : null;
            if (btn && btn.classList.contains('loading')) return;

            if (btn) btn.classList.add('loading');
            return fetch('/api/send_debt_warning.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    debt_id: debtId,
                    warning_type: activeTab
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (btn) {
                        btn.classList.remove('loading');
                        if (data.success) {
                            btn.classList.add('sent');
                            btn.style.color = '#10b981';
                        }
                    }
                    return data;
                });
        }

        function sendBulkWarning() {
            const selected = Array.from(document.querySelectorAll('.debt-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                alert('Vui lòng chọn ít nhất một khoản nợ để gửi thông báo.');
                return;
            }

            if (!confirm(`Bạn có chắc muốn gửi thông báo cho ${selected.length} khoản nợ đã chọn?`)) {
                return;
            }

            const btn = document.querySelector('.btn-bulk-warn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner"></span> Đang gửi...';

            fetch('/api/send_debt_warning.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    debt_ids: selected,
                    warning_type: activeTab
                })
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    if (data.success) {
                        alert(`Đã gửi thành công ${data.count} thông báo!`);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + (data.errors ? data.errors.join('\n') : 'Không thể gửi thông báo'));
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    console.error(err);
                    alert('Lỗi kết nối server');
                });
        }
    </script>
</body>

</html>