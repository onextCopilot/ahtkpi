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

        if ($_POST['action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO debts (company, am, sale_team_id, client_name, project_name, payment_milestone, expected_prod_date, expected_payment_date, invoice_status_class, amount, currency, invoice_status, vat_invoice, invoice_date, payment_status, payment_month, weekly_update, am_notes, delivery_notes, production_status, pl_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssssdsssssssssss", $company, $am, $sale_team_id, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl);
        } else {
            // Edit
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE debts SET company=?, am=?, sale_team_id=?, client_name=?, project_name=?, payment_milestone=?, expected_prod_date=?, expected_payment_date=?, invoice_status_class=?, amount=?, currency=?, invoice_status=?, vat_invoice=?, invoice_date=?, payment_status=?, payment_month=?, weekly_update=?, am_notes=?, delivery_notes=?, production_status=?, pl_class=? WHERE id=?");
            $stmt->bind_param("ssissssssdsssssssssssi", $company, $am, $sale_team_id, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl, $id);
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
if (!empty($_GET['am'])) {
    $am_filter = $conn->real_escape_string($_GET['am']);
    $where_clauses[] = "d.am = '$am_filter'";
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

$selected_team = $_GET['team'] ?? 'dashboard'; // Default to dashboard as requested
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

$groupedDebts = [];
$monthTotals = [];
$amTotals = [];
$dashboardData = []; // team_id => [status_class => [pay_status => total_vnd]]
$total_amount_usd = 0;
$total_amount_vnd = 0;

$res = $conn->query("SELECT d.*, st.name as team_name 
                    FROM debts d 
                    LEFT JOIN sale_teams st ON d.sale_team_id = st.id 
                    $where_sql 
                    ORDER BY d.invoice_date DESC, d.am ASC, d.id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $amount = (float) $row['amount'];
        $curr = $row['currency'] ?: 'USD';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');

        // Convert to VND
        $rate = $odoo->getRate($curr, $date);
        $vnd_value = ($rate > 0) ? ($amount / $rate) : $amount;

        $total_amount_vnd += $vnd_value;
        if ($curr === 'USD') {
            $total_amount_usd += $amount;
        }

        // Dashboard aggregation
        $tId = $row['sale_team_id'] ?: 'undefined';
        $sClass = $row['invoice_status_class'] ?: 'Chưa xác định';
        $pStatus = $row['payment_status'] ?: 'Not paid';

        if (!isset($dashboardData[$tId]))
            $dashboardData[$tId] = [];
        if (!isset($dashboardData[$tId][$sClass]))
            $dashboardData[$tId][$sClass] = [
                'Paid' => 0,
                'Not paid' => 0,
                'Paid_Count' => 0,
                'Not_Paid_Count' => 0
            ];

        // Fix: Use strict strict comparison because 'Not paid' contains string 'Paid'
        $is_paid_status = (strcasecmp(trim($pStatus), 'Paid') === 0);

        if ($is_paid_status) {
            $dashboardData[$tId][$sClass]['Paid'] += $vnd_value;
            $dashboardData[$tId][$sClass]['Paid_Count']++;
        } else {
            // Include 'Not paid' and any other status
            $dashboardData[$tId][$sClass]['Not paid'] += $vnd_value;
            $dashboardData[$tId][$sClass]['Not_Paid_Count']++;
        }

        // Grouping
        $mKey = !empty($row['invoice_date']) ? date('m/Y', strtotime($row['invoice_date'])) : 'No Date';
        $amKey = !empty($row['am']) ? $row['am'] : 'No AM';
        $groupedDebts[$mKey][$amKey][] = $row;

        if (!isset($monthTotals[$mKey]))
            $monthTotals[$mKey] = 0;
        $monthTotals[$mKey] += $vnd_value;

        if (!isset($amTotals[$mKey][$amKey]))
            $amTotals[$mKey][$amKey] = 0;
        $amTotals[$mKey][$amKey] += $vnd_value;
    }
}
$debts = []; // To keep debtsData for JS populated
if (!empty($groupedDebts)) {
    foreach ($groupedDebts as $m => $ams) {
        foreach ($ams as $am => $items) {
            foreach ($items as $item) {
                $debts[] = $item;
            }
        }
    }
}

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
            border: 1px solid #ccc;
            flex: 1;
            overflow-x: auto;
            /* Horizontal scroll */
            overflow-y: auto;
            /* Vertical scroll */
            position: relative;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        table.debt-table {
            width: max-content;
            /* Allow table to be wider than container */
            min-width: 100%;
            /* But at least 100% */
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
            white-space: nowrap;
        }

        /* Sticky Header */
        table.debt-table thead th {
            position: sticky;
            top: 0;
            background-color: #004b75;
            /* Darker Blue */
            color: white;
            font-weight: 600;
            padding: 6px 8px;
            text-align: left;
            border-bottom: 2px solid #003655;
            z-index: 10;
            white-space: normal;
            /* Allow wrapping */
            line-height: 1.3;
            vertical-align: middle;
            min-width: 100px;
            /* Increased from 80px */
            max-height: 52px;
            /* ~2 lines at line-height 1.3 */
            overflow: hidden;
        }

        /* Column borders in header */
        table.debt-table thead th:not(:last-child) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        table.debt-table tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #333;
        }

        /* Row Stripes */
        table.debt-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        table.debt-table tbody tr:hover {
            background-color: #e3f2fd;
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

        /* Status Colors */
        .group-header td {
            background-color: #f1f5f9 !important;
            font-weight: 700;
            color: #334155;
            padding: 12px 15px !important;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
        }

        .group-header-am td {
            background-color: #f8fafc !important;
            font-weight: 600;
            color: #64748b;
            padding: 8px 15px 8px 30px !important;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
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
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'All Debts Overview';
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
                            <option value="Emily" <?php echo (isset($_GET['am']) && $_GET['am'] == 'Emily') ? 'selected' : ''; ?>>Emily</option>
                            <option value="Ryan" <?php echo (isset($_GET['am']) && $_GET['am'] == 'Ryan') ? 'selected' : ''; ?>>Ryan</option>
                            <option value="Hyun" <?php echo (isset($_GET['am']) && $_GET['am'] == 'Hyun') ? 'selected' : ''; ?>>Hyun</option>
                        </select>

                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">Status: All</option>
                            <option value="Not paid" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Not paid') ? 'selected' : ''; ?>>Not paid</option>
                            <option value="Paid" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
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
                        <button type="submit" style="display:none;"></button>
                    </form>
                    <div class="total-badge">
                        Total: <?php echo formatVND($total_amount_vnd); ?>
                    </div>
                </div>

                <div class="team-tabs" id="sortable-tabs">
                    <?php
                    // Calculate counts for each tab
                    $base_where = [];
                    // We want to keep other filters (q, am, status, date) when counting
                    $count_where_clauses = $where_clauses;
                    // Remove existing team filter from count query to get totals for all tabs
                    $count_where_clauses = array_filter($count_where_clauses, function ($c) {
                        return strpos($c, 'd.sale_team_id') === false;
                    });

                    $cw_sql = count($count_where_clauses) > 0 ? "WHERE " . implode(" AND ", $count_where_clauses) : "";

                    $counts = [];
                    $c_res = $conn->query("SELECT d.sale_team_id, COUNT(*) as total FROM debts d $cw_sql GROUP BY d.sale_team_id");
                    if ($c_res) {
                        while ($cr = $c_res->fetch_assoc()) {
                            $k = $cr['sale_team_id'] ?? 'undefined';
                            $counts[$k] = $cr['total'];
                        }
                    }

                    // Helper to build URL with kept params
                    function getTabUrl($team_val)
                    {
                        $params = $_GET;
                        $params['team'] = $team_val;
                        return "?" . http_build_query($params);
                    }

                    // Build Tabs Data Array
                    $tabs_data = [];

                    // 1. Dashboard
                    $tabs_data['dashboard'] = [
                        'id' => 'dashboard',
                        'label' => 'Dashboard',
                        'url' => getTabUrl('dashboard'),
                        'count' => null
                    ];

                    // 2. Analytics
                    $tabs_data['analytics'] = [
                        'id' => 'analytics',
                        'label' => 'Analytics',
                        'url' => getTabUrl('analytics'),
                        'count' => null
                    ];

                    // 3. Teams
                    foreach ($all_teams as $t) {
                        $tabs_data[$t['id']] = [
                            'id' => $t['id'],
                            'label' => $t['name'],
                            'url' => getTabUrl($t['id']),
                            'count' => $counts[$t['id']] ?? 0
                        ];
                    }

                    // 4. Undefined
                    $tabs_data['undefined'] = [
                        'id' => 'undefined',
                        'label' => 'Undefined',
                        'url' => getTabUrl('undefined'),
                        'count' => $counts['undefined'] ?? 0
                    ];

                    // Sorting Logic based on Cookie
                    $ordered_tabs = [];
                    if (isset($_COOKIE['debt_tab_order'])) {
                        $order_ids = json_decode($_COOKIE['debt_tab_order'], true);
                        if (is_array($order_ids)) {
                            foreach ($order_ids as $oid) {
                                if (isset($tabs_data[$oid])) {
                                    $ordered_tabs[] = $tabs_data[$oid];
                                    unset($tabs_data[$oid]);
                                }
                            }
                        }
                    }
                    // Append remaining tabs that weren't in the cookie
                    foreach ($tabs_data as $tab) {
                        $ordered_tabs[] = $tab;
                    }
                    ?>

                    <?php foreach ($ordered_tabs as $tab): ?>
                        <a href="<?php echo $tab['url']; ?>"
                            class="team-tab <?php echo $selected_team == $tab['id'] ? 'active' : ''; ?>"
                            data-id="<?php echo $tab['id']; ?>">
                            <?php echo htmlspecialchars($tab['label']); ?>
                            <?php if ($tab['count'] !== null): ?>
                                <span class="tab-count"><?php echo $tab['count']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var el = document.getElementById('sortable-tabs');
                        var sortable = Sortable.create(el, {
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            onEnd: function (evt) {
                                var order = sortable.toArray(); // Gets data-id attributes
                                // Save to cookie for 1 year
                                document.cookie = "debt_tab_order=" + JSON.stringify(order) + "; path=/; max-age=31536000";
                            }
                        });
                    });
                </script>
                <style>
                    /* Dragging visual cues */
                    .sortable-ghost {
                        opacity: 0.5;
                        background: #f0f5ff;
                    }

                    .team-tabs {
                        user-select: none;
                        /* Prevent text selection while dragging */
                    }

                    .team-tab {
                        cursor: grab;
                        /* Show grab cursor */
                    }

                    .team-tab:active {
                        cursor: grabbing;
                    }
                </style>

                <div class="data-table-wrapper">
                    <?php if ($selected_team === 'dashboard'): ?>
                        <?php
                        // Order for status classes
                        $status_order = ['Done', 'PP', 'Draft', 'Đỏ', 'Tím', 'Trắng', 'Xanh', 'Chưa xác định'];

                        // Calculate General Report Data
                        $generalReport = [];
                        foreach ($dashboardData as $teamData) {
                            foreach ($teamData as $sClass => $payStats) {
                                if (!isset($generalReport[$sClass])) {
                                    $generalReport[$sClass] = [
                                        'Paid' => 0,
                                        'Not paid' => 0,
                                        'Paid_Count' => 0,
                                        'Not_Paid_Count' => 0
                                    ];
                                }
                                $generalReport[$sClass]['Paid'] += $payStats['Paid'] ?? 0;
                                $generalReport[$sClass]['Not paid'] += $payStats['Not paid'] ?? 0;
                                $generalReport[$sClass]['Paid_Count'] += $payStats['Paid_Count'] ?? 0;
                                $generalReport[$sClass]['Not_Paid_Count'] += $payStats['Not_Paid_Count'] ?? 0;
                            }
                        }
                        ?>

                        <!-- General Report Section (Ant Design Enhanced) -->
                        <div
                            style="padding: 0 0 40px 0; width: 100%; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

                            <?php
                            // Calculate High Level Stats
                            $h_total_paid = 0;
                            $h_total_not_paid = 0;
                            $h_count_paid = 0;
                            $h_count_not_paid = 0;

                            foreach ($dashboardData as $td)
                                foreach ($td as $s) {
                                    $h_total_paid += $s['Paid'] ?? 0;
                                    $h_total_not_paid += $s['Not paid'] ?? 0;
                                    $h_count_paid += $s['Paid_Count'] ?? 0;
                                    $h_count_not_paid += $s['Not_Paid_Count'] ?? 0;
                                }
                            $h_total_val = $h_total_paid + $h_total_not_paid;
                            $h_completion = $h_total_val > 0 ? ($h_total_paid / $h_total_val) * 100 : 0;
                            ?>

                            <!-- Ant Stats Grid -->
                            <div
                                style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px;">
                                <?php
                                $cards = [
                                    [
                                        'title' => 'PAID AMOUNT',
                                        'value' => '<span style="background:#f6ffed; padding:4px 8px; border-radius:4px; color:#389e0d;">' . number_format($h_total_paid, 0, ',', '.') . ' <span style="font-size:0.6em; vertical-align:top; color:#52c41a;">VND</span></span>',
                                        'icon_bg' => '#f6ffed', // Ant Green-1
                                        'icon_color' => '#52c41a', // Ant Green-6
                                        'footer_label' => 'Total Invoices',
                                        'footer_val' => $h_count_paid,
                                        'icon' => '<path d="M20 6L9 17l-5-5"/>'
                                    ],
                                    [
                                        'title' => 'NOT PAID AMOUNT',
                                        'value' => '<span style="background:#fff1f0; padding:4px 8px; border-radius:4px; color:#cf1322;">' . number_format($h_total_not_paid, 0, ',', '.') . ' <span style="font-size:0.6em; vertical-align:top; color:#ff4d4f;">VND</span></span>',
                                        'icon_bg' => '#fff1f0', // Ant Red-1
                                        'icon_color' => '#ff4d4f', // Ant Red-6
                                        'footer_label' => 'Pending Invoices',
                                        'footer_val' => $h_count_not_paid,
                                        'icon' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12" y2="16"/>'
                                    ],
                                    [
                                        'title' => 'COMPLETION RATE',
                                        'value' => '<span style="background:#e6f7ff; padding:4px 8px; border-radius:4px; color:#096dd9;">' . number_format($h_completion, 1) . '%</span>',
                                        'icon_bg' => '#e6f7ff', // Ant Blue-1
                                        'icon_color' => '#1890ff', // Ant Blue-6
                                        'footer_label' => 'Progress',
                                        'footer_val' => 'Target 100%',
                                        'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
                                    ],
                                    [
                                        'title' => 'TOTAL VOLUME',
                                        'value' => '<span style="background:#fff7e6; padding:4px 8px; border-radius:4px; color:#d48806;">' . number_format($h_total_val, 0, ',', '.') . ' <span style="font-size:0.6em; vertical-align:top; color:#faad14;">VND</span></span>',
                                        'icon_bg' => '#fff7e6', // Ant Gold-1
                                        'icon_color' => '#faad14', // Ant Gold-6
                                        'footer_label' => 'Teams',
                                        'footer_val' => count($all_teams) . ' Active',
                                        'icon' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'
                                    ]
                                ];

                                foreach ($cards as $card):
                                    ?>
                                    <div style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #f0f0f0; transition: all 0.3s; position: relative;"
                                        onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'"
                                        onmouseout="this.style.boxShadow='none'">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <div>
                                                <div
                                                    style="color: #8c8c8c; font-size: 12px; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                                                    <?php echo $card['title']; ?>
                                                </div>
                                                <div
                                                    style="color: #262626; font-size: 28px; line-height: 1.2; font-weight: 600; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                                    <?php echo $card['value']; ?>
                                                </div>
                                            </div>
                                            <div
                                                style="width: 48px; height: 48px; border-radius: 50%; background: <?php echo $card['icon_bg']; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $card['icon_color']; ?>;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"><?php echo $card['icon']; ?></svg>
                                            </div>
                                        </div>
                                        <div
                                            style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0; font-size: 13px; color: #8c8c8c; display: flex; justify-content: space-between;">
                                            <span><?php echo $card['footer_label']; ?></span>
                                            <span
                                                style="color: #262626; font-weight: 500;"><?php echo $card['footer_val']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Detailed Breakdown Table -->
                            <div
                                style="background: #fff; border: 1px solid #e8e8e8; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden;">
                                <div
                                    style="padding: 20px 24px; border-bottom: 2px solid #e8e8e8; font-size: 16px; font-weight: 700; color: #262626; display:flex; align-items:center; justify-content:space-between;">
                                    <span>Detailed Breakdown</span>
                                    <span
                                        style="font-size: 12px; font-weight: normal; color: #8c8c8c; background: #f5f5f5; padding: 4px 8px; border-radius: 4px;">Updated
                                        Now</span>
                                </div>
                                <div style="overflow-x: auto;">
                                    <style>
                                        .ant-table-row:hover {
                                            background-color: #fafafa !important;
                                        }

                                        .ant-table-header th {
                                            background: #e6f7ff;
                                            color: #1890ff;
                                            font-weight: 700;
                                            text-transform: uppercase;
                                            font-size: 12px;
                                            letter-spacing: 0.5px;
                                            padding: 16px 24px;
                                            border-bottom: 2px solid #91d5ff;
                                        }

                                        .ant-table-cell {
                                            padding: 16px 24px;
                                            border-bottom: 1px solid #f0f0f0;
                                            color: #262626;
                                            font-size: 14px;
                                        }

                                        .ant-table-footer td {
                                            background: #fffbe6;
                                            font-weight: 700;
                                            color: #262626;
                                            padding: 16px 24px;
                                            border-top: 2px solid #ffe58f;
                                        }
                                    </style>
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead class="ant-table-header">
                                            <tr>
                                                <th style="text-align: left;">Status</th>
                                                <th style="text-align: right;">Count (Paid)</th>
                                                <th style="text-align: right;">Count (Not Paid)</th>
                                                <th style="text-align: right;">Total Count</th>
                                                <th style="text-align: right;">Paid (VND)</th>
                                                <th style="text-align: right;">Not Paid (VND)</th>
                                                <th style="text-align: right;">Total (VND)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $grand_cnt_paid = 0;
                                            $grand_cnt_not_paid = 0;
                                            $grand_amt_paid = 0;
                                            $grand_amt_not_paid = 0;

                                            foreach ($status_order as $st):
                                                $cnt_paid = $generalReport[$st]['Paid_Count'] ?? 0;
                                                $cnt_not_paid = $generalReport[$st]['Not_Paid_Count'] ?? 0;
                                                $amt_paid = $generalReport[$st]['Paid'] ?? 0;
                                                $amt_not_paid = $generalReport[$st]['Not paid'] ?? 0;

                                                $row_cnt_total = $cnt_paid + $cnt_not_paid;
                                                $row_amt_total = $amt_paid + $amt_not_paid;

                                                $grand_cnt_paid += $cnt_paid;
                                                $grand_cnt_not_paid += $cnt_not_paid;
                                                $grand_amt_paid += $amt_paid;
                                                $grand_amt_not_paid += $amt_not_paid;

                                                // Ant Design Status Dots
                                                $dotColor = '#d9d9d9'; // default grey
                                                if ($st === 'Done')
                                                    $dotColor = '#52c41a'; // green
                                                elseif ($st === 'Đỏ')
                                                    $dotColor = '#ff4d4f'; // red
                                                elseif ($st === 'Tím')
                                                    $dotColor = '#722ed1'; // purple
                                                elseif ($st === 'Xanh')
                                                    $dotColor = '#1890ff'; // blue
                                                elseif ($st === 'PP')
                                                    $dotColor = '#faad14'; // gold
                                                ?>
                                                <tr class="ant-table-row" style="transition: all 0.3s;">
                                                    <td class="ant-table-cell">
                                                        <span
                                                            style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo $dotColor; ?>; margin-right: 12px;"></span>
                                                        <span
                                                            style="font-weight: 500; font-size: 14px;"><?php echo $st; ?></span>
                                                    </td>
                                                    <td class="ant-table-cell" style="text-align: right;">
                                                        <?php echo $cnt_paid > 0 ? "<span style='background:#f6ffed; border:1px solid #b7eb8f; color:#389e0d; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;'>$cnt_paid</span>" : '<span style="color:#d9d9d9;">-</span>'; ?>
                                                    </td>
                                                    <td class="ant-table-cell" style="text-align: right;">
                                                        <?php echo $cnt_not_paid > 0 ? "<span style='background:#fff1f0; border:1px solid #ffa39e; color:#cf1322; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;'>$cnt_not_paid</span>" : '<span style="color:#d9d9d9;">-</span>'; ?>
                                                    </td>
                                                    <td class="ant-table-cell"
                                                        style="text-align: right; font-weight: 600; color: #262626;">
                                                        <?php echo $row_cnt_total; ?>
                                                    </td>
                                                    <td class="ant-table-cell" style="text-align: right; color: #595959;">
                                                        <?php echo $amt_paid > 0 ? number_format($amt_paid, 0, ',', '.') . ' <span style="font-size:10px; color:#8c8c8c;">VND</span>' : '-'; ?>
                                                    </td>
                                                    <td class="ant-table-cell"
                                                        style="text-align: right; color: <?php echo $amt_not_paid > 0 ? '#ff4d4f' : '#595959'; ?>;">
                                                        <?php echo $amt_not_paid > 0 ? number_format($amt_not_paid, 0, ',', '.') . ' <span style="font-size:10px; color:#cf1322;">VND</span>' : '-'; ?>
                                                    </td>
                                                    <td class="ant-table-cell"
                                                        style="text-align: right; font-weight: 600; color: #262626;">
                                                        <?php echo number_format($row_amt_total, 0, ',', '.') . ' <span style="font-size:10px; color:#8c8c8c;">VND</span>'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="ant-table-footer">
                                                <td style="text-transform: uppercase; font-size: 13px; color: #262626;">
                                                    Total</td>
                                                <td style="text-align: right; color: #8c8c8c;">
                                                    <?php echo number_format($grand_cnt_paid); ?>
                                                </td>
                                                <td style="text-align: right; color: #cf1322;">
                                                    <?php echo number_format($grand_cnt_not_paid); ?>
                                                </td>
                                                <td style="text-align: right; color: #262626;">
                                                    <?php echo number_format($grand_cnt_paid + $grand_cnt_not_paid); ?>
                                                </td>
                                                <td style="text-align: right; color: #389e0d;">
                                                    <?php echo number_format($grand_amt_paid, 0, ',', '.') . ' <span style="font-size:10px">VND</span>'; ?>
                                                </td>
                                                <td style="text-align: right; color: #cf1322;">
                                                    <?php echo number_format($grand_amt_not_paid, 0, ',', '.') . ' <span style="font-size:10px">VND</span>'; ?>
                                                </td>
                                                <td style="text-align: right; font-size: 15px; color: #1f1f1f;">
                                                    <?php echo number_format($grand_amt_paid + $grand_amt_not_paid, 0, ',', '.') . ' <span style="font-size:10px">VND</span>'; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Team Cards Grid (Ant Design Enhanced) -->
                        <div class="dashboard-grid"
                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(550px, 1fr)); gap: 24px;">
                            <?php
                            $teams_to_show = array_map(function ($t) {
                                return ['id' => $t['id'], 'name' => $t['name']];
                            }, $all_teams);
                            $teams_to_show[] = ['id' => 'undefined', 'name' => 'UNDEFINED TEAM'];
                            foreach ($teams_to_show as $teamInfo):
                                $tid = $teamInfo['id'];
                                $data = $dashboardData[$tid] ?? [];
                                ?>
                                <div style="background: #fff; border: 1px solid #e8e8e8; border-radius: 8px; transition: all 0.3s ease; display:flex; flex-direction:column;"
                                    onmouseover="this.style.borderColor='#40a9ff'; this.style.boxShadow='0 4px 12px rgba(24, 144, 255, 0.15)'"
                                    onmouseout="this.style.borderColor='#e8e8e8'; this.style.boxShadow='none'">
                                    <div
                                        style="padding: 16px 20px; border-bottom: 1px solid #bae7ff; display: flex; justify-content: space-between; align-items: center; background: #e6f7ff; border-radius: 8px 8px 0 0;">
                                        <span
                                            style="font-weight: 700; color: #003a8c; font-size: 16px;"><?php echo htmlspecialchars($teamInfo['name']); ?></span>
                                        <span
                                            style="background: #fff; color: #0050b3; border: 1px solid #91d5ff; font-size: 11px; padding: 2px 10px; border-radius: 10px; font-weight: 600;">TEAM</span>
                                    </div>
                                    <div style="flex:1;">
                                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                            <thead>
                                                <tr style="border-bottom: 1px solid #f0f0f0; background: #f0f5ff;">
                                                    <th
                                                        style="text-align: left; padding: 12px 20px; color: #096dd9; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                                                        Status</th>
                                                    <th
                                                        style="text-align: right; padding: 12px 20px; color: #096dd9; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                                                        Pending <span style="text-transform:none;">(VND)</span></th>
                                                    <th
                                                        style="text-align: right; padding: 12px 20px; color: #096dd9; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                                                        Paid <span style="text-transform:none;">(VND)</span></th>
                                                    <th
                                                        style="text-align: right; padding: 12px 20px; color: #096dd9; font-weight: 600; font-size: 12px; text-transform: uppercase;">
                                                        Total <span style="text-transform:none;">(VND)</span></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $t_not_paid = 0;
                                                $t_paid = 0;
                                                foreach ($status_order as $st):
                                                    $np = $data[$st]['Not paid'] ?? 0;
                                                    $p = $data[$st]['Paid'] ?? 0;
                                                    $row_t = $np + $p;
                                                    $t_not_paid += $np;
                                                    $t_paid += $p;

                                                    $dColor = '#d9d9d9';
                                                    if ($st === 'Done')
                                                        $dColor = '#52c41a';
                                                    elseif ($st === 'Đỏ')
                                                        $dColor = '#ff4d4f';
                                                    elseif ($st === 'Tím')
                                                        $dColor = '#722ed1';
                                                    elseif ($st === 'Xanh')
                                                        $dColor = '#1890ff';
                                                    elseif ($st === 'PP')
                                                        $dColor = '#faad14';
                                                    ?>
                                                    <tr style="border-bottom: 1px solid #f9f9f9;">
                                                        <td style="padding: 10px 20px; color: #262626;">
                                                            <span
                                                                style="width:6px; height:6px; background:<?php echo $dColor; ?>; display:inline-block; border-radius:50%; margin-right:8px;"></span>
                                                            <?php echo $st; ?>
                                                        </td>
                                                        <td
                                                            style="padding: 10px 20px; text-align: right; color: <?php echo $np > 0 ? '#cf1322' : '#bfbfbf'; ?>; font-weight:<?php echo $np > 0 ? '600' : '400'; ?>;">
                                                            <?php echo $np > 0 ? number_format($np, 0, ',', '.') : '-'; ?>
                                                        </td>
                                                        <td
                                                            style="padding: 10px 20px; text-align: right; color: <?php echo $p > 0 ? '#389e0d' : '#bfbfbf'; ?>;">
                                                            <?php echo $p > 0 ? number_format($p, 0, ',', '.') : '-'; ?>
                                                        </td>
                                                        <td
                                                            style="padding: 10px 20px; text-align: right; color: #262626; font-weight: 600;">
                                                            <?php echo $row_t > 0 ? number_format($row_t, 0, ',', '.') : '-'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr style="background: #fafafa;">
                                                    <td
                                                        style="padding: 12px 20px; font-weight: 600; color: #595959; font-size: 12px; text-transform: uppercase;">
                                                        Total</td>
                                                    <td
                                                        style="padding: 12px 20px; text-align: right; color: #cf1322; font-weight: 700;">
                                                        <?php echo number_format($t_not_paid, 0, ',', '.'); ?>
                                                    </td>
                                                    <td
                                                        style="padding: 12px 20px; text-align: right; color: #389e0d; font-weight: 700;">
                                                        <?php echo number_format($t_paid, 0, ',', '.'); ?>
                                                    </td>
                                                    <td
                                                        style="padding: 12px 20px; text-align: right; font-weight: 700; color: #1f1f1f;">
                                                        <?php echo number_format($t_not_paid + $t_paid, 0, ',', '.'); ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($selected_team === 'analytics'): ?>
                        <?php
                        // Aggregating Data for Charts
                        $chartTeams = [];
                        $chartPaid = [];
                        $chartNotPaid = [];
                        $chartTotal = [];
                        $chartCompletion = [];

                        $status_order = ['Done', 'PP', 'Draft', 'Đỏ', 'Tím', 'Trắng', 'Xanh', 'Chưa xác định'];
                        $statusLabels = array_keys($status_order);
                        $statusPaid = array_fill_keys($statusLabels, 0);
                        $statusNotPaid = array_fill_keys($statusLabels, 0);
                        $statusCount = array_fill_keys($statusLabels, 0);

                        foreach ($all_teams as $t) {
                            $chartTeams[] = $t['name'];
                            $tid = $t['id'];
                            $d = $dashboardData[$tid] ?? [];

                            $p = 0;
                            $np = 0;
                            foreach ($d as $st => $vals) {
                                $p += $vals['Paid'] ?? 0;
                                $np += $vals['Not paid'] ?? 0;

                                if (isset($statusPaid[$st])) {
                                    $statusPaid[$st] += $vals['Paid'] ?? 0;
                                    $statusNotPaid[$st] += $vals['Not paid'] ?? 0;
                                    $statusCount[$st] += ($vals['Paid_Count'] ?? 0) + ($vals['Not_Paid_Count'] ?? 0);
                                }
                            }
                            $chartPaid[] = $p;
                            $chartNotPaid[] = $np;
                            $chartTotal[] = $p + $np;
                            $chartCompletion[] = ($p + $np) > 0 ? round(($p / ($p + $np)) * 100, 1) : 0;
                        }

                        // Top Teams Logic
                        $topTeams = array_map(function ($n, $v, $p, $np) {
                            return ['name' => $n, 'total' => $v, 'paid' => $p, 'not_paid' => $np];
                        }, $chartTeams, $chartTotal, $chartPaid, $chartNotPaid);
                        usort($topTeams, function ($a, $b) {
                            return $b['total'] <=> $a['total'];
                        });
                        $top5 = array_slice($topTeams, 0, 5);
                        ?>
                        <div
                            style="padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px;">
                                <!-- Chart 1: Paid vs Not Paid (Global) -->
                                <div
                                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h3 style="margin-top:0; color:#262626; font-size:16px;">Global Debt Overview
                                    </h3>
                                    <div id="chart-global-pie"></div>
                                </div>
                                <!-- Chart 2: Completion Rate Distribution -->
                                <div
                                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h3 style="margin-top:0; color:#262626; font-size:16px;">Completion Rate by Team
                                    </h3>
                                    <div id="chart-completion-bar"></div>
                                </div>
                                <!-- Chart 3: Revenue by Team -->
                                <div
                                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h3 style="margin-top:0; color:#262626; font-size:16px;">Paid Amount by Team
                                    </h3>
                                    <div id="chart-team-paid"></div>
                                </div>
                                <!-- Chart 4: Outstanding by Team -->
                                <div
                                    style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h3 style="margin-top:0; color:#262626; font-size:16px;">Outstanding Debt by
                                        Team</h3>
                                    <div id="chart-team-outstanding"></div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // Helpers
                            const formatVND = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);

                            // Data passed from PHP
                            const teamNames = <?php echo json_encode($chartTeams); ?>;
                            const dataPaid = <?php echo json_encode($chartPaid); ?>;
                            const dataNotPaid = <?php echo json_encode($chartNotPaid); ?>;
                            const dataCompletion = <?php echo json_encode($chartCompletion); ?>;

                            const statusLabels = <?php echo json_encode($statusLabels); ?>;
                            const statusCounts = <?php echo json_encode(array_values($statusCount)); ?>;
                            const statusAmounts = <?php echo json_encode(array_values($statusPaid)); // Using paid amounts for simplification or could sum paid+notpaid ?>;

                            // 1. Global Pie
                            new ApexCharts(document.querySelector("#chart-global-pie"), {
                                series: [<?php echo array_sum($chartPaid); ?>, <?php echo array_sum($chartNotPaid); ?>],
                                chart: { type: 'donut', height: 350 },
                                labels: ['Paid', 'Not Paid'],
                                colors: ['#52c41a', '#ff4d4f'],
                                dataLabels: { enabled: true, formatter: function (val, opts) { return formatVND(opts.w.globals.series[opts.seriesIndex]) } },
                                plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total Volume', formatter: function (w) { return formatVND(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } } }
                            }).render();

                            // 2. Completion Bar
                            new ApexCharts(document.querySelector("#chart-completion-bar"), {
                                series: [{ name: 'Completion Rate', data: dataCompletion }],
                                chart: { type: 'bar', height: 350 },
                                plotOptions: { bar: { borderRadius: 4, horizontal: true, distributed: true, dataLabels: { position: 'bottom' } } },
                                dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, formatter: function (val, opt) { return val + "%" } },
                                xaxis: { categories: teamNames, max: 100 },
                                colors: ['#1890ff'],
                                tooltip: { y: { formatter: val => val + "%" } }
                            }).render();

                            // 3. Paid Amount Bar
                            new ApexCharts(document.querySelector("#chart-team-paid"), {
                                series: [{ name: 'Paid Amount', data: dataPaid }],
                                chart: { type: 'bar', height: 350 },
                                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%', dataLabels: { position: 'top' } } },
                                dataLabels: { enabled: true, formatter: function (val) { return (val / 1000000).toFixed(0) + "M" }, offsetY: -20, style: { fontSize: '10px', colors: ["#304758"] } },
                                xaxis: { categories: teamNames },
                                colors: ['#52c41a'],
                                yaxis: { labels: { formatter: val => (val / 1000000).toFixed(0) + 'M' } },
                                tooltip: { y: { formatter: val => formatVND(val) } }
                            }).render();

                            // 4. Outstanding Bar
                            new ApexCharts(document.querySelector("#chart-team-outstanding"), {
                                series: [{ name: 'Pending Amount', data: dataNotPaid }],
                                chart: { type: 'bar', height: 350 },
                                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%', dataLabels: { position: 'top' } } },
                                dataLabels: { enabled: true, formatter: function (val) { return (val / 1000000).toFixed(0) + "M" }, offsetY: -20, style: { fontSize: '10px', colors: ["#304758"] } },
                                xaxis: { categories: teamNames },
                                colors: ['#ff4d4f'],
                                yaxis: { labels: { formatter: val => (val / 1000000).toFixed(0) + 'M' } },
                                tooltip: { y: { formatter: val => formatVND(val) } }
                            }).render();
                        </script>
                    <?php else: ?>
                        <table class="debt-table">
                            <thead>
                                <tr>
                                    <th style="width: 30px !important; text-align: center;">#</th>
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
                                <?php $globalIdx = 1; ?>
                                <?php foreach ($groupedDebts as $monthName => $ams): ?>
                                    <tr class="group-header">
                                        <td colspan="22">
                                            Tháng <?php echo $monthName; ?>
                                            <span class="group-total">(Total:
                                                <?php echo formatVND($monthTotals[$monthName]); ?>)</span>
                                        </td>
                                    </tr>
                                    <?php foreach ($ams as $amName => $monthItems): ?>
                                        <tr class="group-header-am">
                                            <td colspan="22">
                                                AM: <?php echo htmlspecialchars($amName); ?>
                                                <span class="group-total"
                                                    style="font-size: 0.8rem; font-weight: normal; opacity: 0.8;">(Subtotal:
                                                    <?php echo formatVND($amTotals[$monthName][$amName]); ?>)</span>
                                            </td>
                                        </tr>
                                        <?php foreach ($monthItems as $d): ?>
                                            <td style="text-align: center;"><?php echo $globalIdx++; ?></td>
                                            <td class="cell-company"><?php echo htmlspecialchars($d['company']); ?></td>
                                            <td>
                                                <?php
                                                // Format AM Badge
                                                $am_val = $d['am'] ?? '';
                                                $am_class = 'am-default';
                                                if (stripos($am_val, 'Emily') !== false)
                                                    $am_class = 'am-emily';
                                                if (stripos($am_val, 'Hyun') !== false)
                                                    $am_class = 'am-hyun';
                                                if (stripos($am_val, 'Ryan') !== false)
                                                    $am_class = 'am-ryan';
                                                ?>
                                                <span
                                                    class="badge am-badge <?php echo $am_class; ?>"><?php echo htmlspecialchars($am_val); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($d['team_name'])): ?>
                                                    <span class="badge"
                                                        style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-size: 11px;">
                                                        <?php echo htmlspecialchars($d['team_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="cell-company client-tooltip-trigger"
                                                data-client-name="<?php echo htmlspecialchars($d['client_name'] ?? ''); ?>"
                                                style="cursor: pointer; position: relative;">
                                                <?php echo htmlspecialchars($d['client_name'] ?? ''); ?>
                                            </td>
                                            <td class="project-tooltip-trigger"
                                                data-project-name="<?php echo htmlspecialchars($d['project_name'] ?? ''); ?>"
                                                style="position: relative; cursor: pointer;">
                                                <?php echo htmlspecialchars($d['project_name'] ?? ''); ?>
                                            </td>
                                            <td><?php echo formatDate($d['invoice_date']); ?></td>
                                            <td><?php echo htmlspecialchars($d['payment_milestone'] ?? ''); ?></td>
                                            <td><?php echo $d['expected_prod_date']; ?></td>
                                            <td><?php echo $d['expected_payment_date']; ?></td>
                                            <td style="position: relative; text-align: center;">
                                                <?php
                                                $st = $d['invoice_status_class'] ?? '';
                                                $bgClass = 'status-chuaxacdinh'; // Default
                                                if ($st === 'Done')
                                                    $bgClass = 'status-done';
                                                elseif ($st === 'Tím')
                                                    $bgClass = 'status-tim';
                                                elseif ($st === 'Xanh')
                                                    $bgClass = 'status-xanh';
                                                elseif ($st === 'Trắng')
                                                    $bgClass = 'status-trang';
                                                elseif ($st === 'PP')
                                                    $bgClass = 'status-pp';
                                                elseif ($st === 'Draft')
                                                    $bgClass = 'status-draft';
                                                elseif ($st === 'Chưa xác định')
                                                    $bgClass = 'status-chuaxacdinh';
                                                elseif ($st === 'Đỏ')
                                                    $bgClass = 'status-do';

                                                echo "<span class='$bgClass'>" . htmlspecialchars($st) . "</span>";
                                                ?>
                                            </td>
                                            <td class="cell-amount">
                                                <?php echo formatCurrency($d['amount'] ?? 0, $d['currency'] ?? 'USD'); ?>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge <?php echo (stripos($d['pl_class'] ?? '', 'Xấu') !== false ? 'pl-xau' : ((stripos($d['pl_class'] ?? '', 'TB') !== false) ? 'pl-tb' : 'pl-tot')); ?>">
                                                    <?php echo htmlspecialchars($d['pl_class'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['invoice_status'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($d['vat_invoice'] ?? ''); ?></td>
                                            <td>
                                                <span
                                                    class="badge <?php echo (stripos($d['payment_status'] ?? '', 'Not') !== false ? 'pay-not-paid' : 'pay-paid'); ?>">
                                                    <?php echo htmlspecialchars($d['payment_status'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['payment_month'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($d['weekly_update'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($d['am_notes'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($d['delivery_notes'] ?? ''); ?></td>
                                            <td style="position: relative; text-align: center;">
                                                <?php
                                                $ps = $d['production_status'] ?? '';
                                                $prodClass = 'prod-dc2';
                                                if (stripos($ps, 'Overdue') !== false || stripos($ps, 'DC5') !== false)
                                                    $prodClass = 'prod-dc5';
                                                elseif (stripos($ps, 'DC1') !== false)
                                                    $prodClass = 'prod-dc1';
                                                ?>
                                                <?php echo htmlspecialchars($d['production_status'] ?? ''); ?>
                                            </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Record</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>

            <form method="POST" id="mainForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="editId" value="">

                    <!-- Hidden fields for non-manual sync -->
                    <input type="hidden" name="vat_invoice" id="vat_invoice_input">
                    <input type="hidden" name="payment_month" id="payment_month_input">
                    <input type="hidden" name="weekly_update" id="weekly_update_input">
                    <input type="hidden" name="delivery_notes" id="delivery_notes_input">
                    <input type="hidden" name="invoice_status" id="invoice_status_input">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Company</label>
                            <select name="company" id="company">
                                <option>AHT TECH</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>AM</label>
                            <select name="am" id="am">
                                <option>Emily</option>
                                <option>Ryan</option>
                                <option>Hyun</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sale Team</label>
                            <select name="sale_team_id" id="sale_team_id">
                                <option value="">- Undefined -</option>
                                <?php foreach ($all_teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Client Name</label>
                            <input type="text" name="client_name" id="client_name" required>
                        </div>
                        <div class="form-group">
                            <label>Project Name</label>
                            <input type="text" name="project_name" id="project_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label>Payment Milestone</label>
                            <input type="text" name="payment_milestone" id="payment_milestone"
                                placeholder="e.g. Inv 08.2023">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Currency</label>
                            <select name="currency" id="currency">
                                <option value="USD">USD</option>
                                <option value="VND">VND</option>
                                <option value="EUR">EUR</option>
                                <option value="JPY">JPY</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 2;">
                            <label>Amount</label>
                            <input type="number" step="0.01" name="amount" id="amount" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Exp. Prod. Date</label>
                            <input type="date" name="expected_prod_date" id="expected_prod_date">
                        </div>
                        <div class="form-group">
                            <label>Exp. Payment Date</label>
                            <input type="date" name="expected_payment_date" id="expected_payment_date">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Invoice Date</label>
                            <input type="date" name="invoice_date" id="invoice_date">
                        </div>
                        <div class="form-group">
                            <label>Invoice Status Class</label>
                            <select name="invoice_status_class" id="invoice_status_class">
                                <option value="Trắng">Trắng</option>
                                <option value="Xanh">Xanh</option>
                                <option value="Done">Done</option>
                                <option value="Tím">Tím</option>
                                <option value="PP">PP</option>
                                <option value="Draft">Draft</option>
                                <option value="Chưa xác định">Chưa xác định</option>
                                <option value="Đỏ">Đỏ</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>P&L Class</label>
                            <select name="pl_class" id="pl_class">
                                <option value="Tốt">Tốt</option>
                                <option value="TB">TB</option>
                                <option value="Xấu">Xấu</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" id="payment_status">
                                <option value="Not paid">Not paid</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Production Status</label>
                            <input type="text" name="production_status" id="production_status">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label>AM Notes</label>
                        <textarea name="am_notes" id="am_notes" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-delete" id="btnDelete" onclick="deleteItem()"
                        style="display:none;">Delete this record</button>
                    <div style="margin-left:auto; display:flex; gap:10px;">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-submit">Save Record</button>
                    </div>
                </div>
            </form>

            <!-- Hidden delete form -->
            <form id="deleteForm" method="POST" style="display:none;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
            </form>
        </div>
    </div>

    <script>
        const debtsData = <?php echo json_encode($debts); ?>;
        const modal = document.getElementById('itemModal');
        const form = document.getElementById('mainForm');

        function openModal(mode, id = null) {
            modal.style.display = "block";

            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = "Add New Record";
                document.getElementById('formAction').value = "add";
                document.getElementById('editId').value = "";
                document.getElementById('btnDelete').style.display = "none";
                form.reset();
            } else {
                const data = debtsData.find(d => d.id == id);
                if (!data) return;

                document.getElementById('modalTitle').innerText = "Edit Record";
                document.getElementById('formAction').value = "edit";
                document.getElementById('editId').value = data.id;
                document.getElementById('btnDelete').style.display = "block";

                document.getElementById('company').value = data.company || 'AHT TECH';
                document.getElementById('am').value = data.am;
                document.getElementById('sale_team_id').value = data.sale_team_id || '';
                document.getElementById('client_name').value = data.client_name;
                document.getElementById('project_name').value = data.project_name;
                document.getElementById('payment_milestone').value = data.payment_milestone;
                document.getElementById('amount').value = data.amount;
                document.getElementById('currency').value = data.currency || 'USD';
                document.getElementById('expected_prod_date').value = data.expected_prod_date;
                document.getElementById('expected_payment_date').value = data.expected_payment_date;
                document.getElementById('invoice_status_class').value = data.invoice_status_class;
                document.getElementById('pl_class').value = data.pl_class;
                document.getElementById('payment_status').value = data.payment_status;
                document.getElementById('production_status').value = data.production_status;
                document.getElementById('am_notes').value = data.am_notes;

                // Hidden fields
                document.getElementById('vat_invoice_input').value = data.vat_invoice || '';
                document.getElementById('payment_month_input').value = data.payment_month || '';
                document.getElementById('weekly_update_input').value = data.weekly_update || '';
                document.getElementById('delivery_notes_input').value = data.delivery_notes || '';
                document.getElementById('invoice_status_input').value = data.invoice_status || '';
                document.getElementById('invoice_date').value = data.invoice_date || '';
            }
        }

        function closeModal() {
            modal.style.display = "none";
        }

        function deleteItem() {
            if (confirm("Are you sure you want to delete this record forever?")) {
                document.getElementById('deleteId').value = document.getElementById('editId').value;
                document.getElementById('deleteForm').submit();
            }
        }

        function updateInline(id, field, value, el) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('field', field);
            formData.append('value', value);

            fetch('/api/update_debt_inline.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert('Update failed: ' + (data.error || 'Unknown error'));
                        if (el) el.style.backgroundColor = '#fee2e2';
                    } else {
                        if (el) {
                            const parent = el.parentElement;
                            const tick = document.createElement('span');
                            tick.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                            tick.style.position = 'absolute';
                            tick.style.right = '5px';
                            tick.style.top = '50%';
                            tick.style.transform = 'translateY(-50%)';
                            tick.style.zIndex = '10';
                            tick.style.pointerEvents = 'none';

                            const oldTick = parent.querySelector('.inline-success-tick');
                            if (oldTick) oldTick.remove();

                            tick.classList.add('inline-success-tick');
                            parent.appendChild(tick);

                            setTimeout(() => {
                                tick.style.transition = 'opacity 0.5s';
                                tick.style.opacity = '0';
                                setTimeout(() => tick.remove(), 500);
                            }, 2000);
                        }
                    }
                })
                .catch(err => console.error('Error:', err));
        }

        function syncDebt(id, invoiceName, btn) {
            if (!invoiceName) {
                alert('No Invoice Number found to sync.');
                return;
            }

            const icon = btn.querySelector('svg');
            icon.style.animation = 'spin 1s linear infinite';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('debt_id', id);
            formData.append('invoice_name', invoiceName);

            fetch('/api/sync_debt.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    icon.style.animation = '';
                    btn.disabled = false;
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Sync error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    icon.style.animation = '';
                    btn.disabled = false;
                    alert('Connection error');
                    console.error(err);
                });
        }

        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Jira Tooltip Logic
        document.addEventListener('DOMContentLoaded', function () {
            const tooltip = document.createElement('div');
            tooltip.id = 'jira-project-tooltip';
            tooltip.style.position = 'absolute';
            tooltip.style.zIndex = '1000';
            tooltip.style.background = '#fff';
            tooltip.style.border = '1px solid #dfe1e6';
            tooltip.style.borderRadius = '3px';
            tooltip.style.boxShadow = '0 4px 8px -2px rgba(9, 30, 66, 0.25), 0 0 1px rgba(9, 30, 66, 0.31)';
            tooltip.style.display = 'none';
            tooltip.style.maxWidth = '320px';
            document.body.appendChild(tooltip);

            // Drag Functionality
            let isDragging = false;
            let currentX;
            let currentY;
            let initialX;
            let initialY;
            let xOffset = 0;
            let yOffset = 0;

            document.addEventListener('mousedown', function (e) {
                if (e.target.closest('.jira-tooltip-header')) {
                    isDragging = true;
                    const rect = tooltip.getBoundingClientRect();
                    // Offset of mouse relative to tooltip top-left
                    initialX = e.clientX - rect.left;
                    initialY = e.clientY - rect.top;
                    e.preventDefault(); // Prevent text selection
                }
            });

            document.addEventListener('mouseup', function () {
                isDragging = false;
            });

            document.addEventListener('mousemove', function (e) {
                if (isDragging) {
                    e.preventDefault();
                    // New position = mouse position - offset + scroll
                    tooltip.style.left = (e.clientX - initialX + window.scrollX) + "px";
                    tooltip.style.top = (e.clientY - initialY + window.scrollY) + "px";
                }
            });

            const cache = {};

            // Close tooltip when clicking outside
            document.addEventListener('click', function (e) {
                if (!e.target.closest('#jira-project-tooltip') && !e.target.closest('.project-tooltip-trigger')) {
                    tooltip.style.display = 'none';
                }
            });

            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('.project-tooltip-trigger');
                if (!trigger) return;

                const projectName = trigger.getAttribute('data-project-name');
                if (!projectName) return;

                // Toggle visibility if clicking the same trigger?
                // For now, just ensure it opens/repositions
                const rect = trigger.getBoundingClientRect();
                tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
                tooltip.style.left = (rect.left + window.scrollX) + 'px';
                tooltip.style.display = 'block';

                if (cache[projectName]) {
                    if (cache[projectName] === '404') {
                        tooltip.innerHTML = '<div style="padding:10px; color:#cf1322;">Project not found</div><span onclick="document.getElementById(\'jira-project-tooltip\').style.display=\'none\'; event.stopPropagation();" style="position: absolute; top: 5px; right: 8px; cursor: pointer; color: #999; font-weight: bold; font-size: 16px; line-height: 1;">&times;</span>';
                    } else {
                        tooltip.innerHTML = cache[projectName];
                    }
                } else {
                    tooltip.innerHTML = '<div style="padding:10px; color:#6b778c; font-size:12px;">Loading Jira info...</div>';

                    fetch(`/api/get_jira_project_info.php?project_name=${encodeURIComponent(projectName)}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Not found');
                            return response.text();
                        })
                        .then(html => {
                            cache[projectName] = html;
                            if (tooltip.style.display === 'block') {
                                tooltip.innerHTML = html;
                            }
                        })
                        .catch(err => {
                            cache[projectName] = '404';
                            tooltip.innerHTML = '<div style="padding:10px; color:#cf1322;">Project not found</div><span onclick="document.getElementById(\'jira-project-tooltip\').style.display=\'none\'; event.stopPropagation();" style="position: absolute; top: 5px; right: 8px; cursor: pointer; color: #999; font-weight: bold; font-size: 16px; line-height: 1;">&times;</span>';
                        });
                }
            });
        });
        // Client Tooltip Logic
        document.addEventListener('DOMContentLoaded', function () {
            const tooltip = document.createElement('div');
            tooltip.id = 'client-info-tooltip';
            tooltip.style.position = 'absolute';
            tooltip.style.zIndex = '1000';
            tooltip.style.background = '#fff';
            tooltip.style.border = '1px solid #dfe1e6';
            tooltip.style.borderRadius = '3px';
            tooltip.style.boxShadow = '0 4px 8px -2px rgba(9, 30, 66, 0.25), 0 0 1px rgba(9, 30, 66, 0.31)';
            tooltip.style.display = 'none';
            tooltip.style.maxWidth = '600px';
            document.body.appendChild(tooltip);

            // Drag Functionality
            let isDragging = false;
            let initialX;
            let initialY;

            document.addEventListener('mousedown', function (e) {
                if (e.target.closest('.client-tooltip-header')) {
                    isDragging = true;
                    const rect = tooltip.getBoundingClientRect();
                    initialX = e.clientX - rect.left;
                    initialY = e.clientY - rect.top;
                    e.preventDefault();
                }
            });

            document.addEventListener('mouseup', function () {
                isDragging = false;
            });

            document.addEventListener('mousemove', function (e) {
                if (isDragging) {
                    e.preventDefault();
                    tooltip.style.left = (e.clientX - initialX + window.scrollX) + "px";
                    tooltip.style.top = (e.clientY - initialY + window.scrollY) + "px";
                }
            });

            const cache = {};

            document.addEventListener('click', function (e) {
                if (!e.target.closest('#client-info-tooltip') && !e.target.closest('.client-tooltip-trigger')) {
                    tooltip.style.display = 'none';
                }
            });

            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('.client-tooltip-trigger');
                if (!trigger) return;

                const clientName = trigger.getAttribute('data-client-name');
                if (!clientName) return;

                const rect = trigger.getBoundingClientRect();
                tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
                tooltip.style.left = (rect.left + window.scrollX) + 'px';
                tooltip.style.display = 'block';

                if (cache[clientName]) {
                    if (cache[clientName] === '404') {
                        tooltip.innerHTML = '<div style="padding:10px; color:#cf1322;">Info not found</div><span onclick="document.getElementById(\'client-info-tooltip\').style.display=\'none\'; event.stopPropagation();" style="position: absolute; top: 10px; right: 10px; cursor: pointer; color: #999; font-weight: bold; font-size: 16px; line-height: 1;">&times;</span>';
                    } else {
                        tooltip.innerHTML = cache[clientName];
                    }
                } else {
                    tooltip.innerHTML = '<div style="padding:10px; color:#6b778c; font-size:12px;">Loading Customer info...</div>';

                    fetch(`/api/get_client_info.php?client_name=${encodeURIComponent(clientName)}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Not found');
                            return response.text();
                        })
                        .then(html => {
                            cache[clientName] = html;
                            if (tooltip.style.display === 'block') {
                                tooltip.innerHTML = html;
                            }
                        })
                        .catch(err => {
                            cache[clientName] = '404';
                            tooltip.innerHTML = '<div style="padding:10px; color:#cf1322;">Info not found</div><span onclick="document.getElementById(\'client-info-tooltip\').style.display=\'none\'; event.stopPropagation();" style="position: absolute; top: 10px; right: 10px; cursor: pointer; color: #999; font-weight: bold; font-size: 16px; line-height: 1;">&times;</span>';
                        });
                }
            });
        });
    </script>
</body>

</html>