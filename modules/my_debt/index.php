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
$avatar = $_SESSION['avatar'] ?? null;

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// --- DB INIT ---
$table_check = $conn->query("SHOW TABLES LIKE 'debts'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE debts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(50) DEFAULT 'AHT TECH',
        am VARCHAR(100),
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
        $currency_val = $_POST['currency'] ?? 'USD';
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

        if ($_POST['action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO debts (company, am, client_name, project_name, payment_milestone, expected_prod_date, expected_payment_date, invoice_status_class, amount, currency, invoice_status, vat_invoice, invoice_date, payment_status, payment_month, weekly_update, am_notes, delivery_notes, production_status, pl_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssdsssssssssss", $company, $am, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl);
        } else {
            // Edit
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE debts SET company=?, am=?, client_name=?, project_name=?, payment_milestone=?, expected_prod_date=?, expected_payment_date=?, invoice_status_class=?, amount=?, currency=?, invoice_status=?, vat_invoice=?, invoice_date=?, payment_status=?, payment_month=?, weekly_update=?, am_notes=?, delivery_notes=?, production_status=?, pl_class=? WHERE id=?");
            $stmt->bind_param("ssssssssdsssssssssssi", $company, $am, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl, $id);
        }

        if ($stmt->execute()) {
            header("Location: /my-debt");
            exit();
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }

    // DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM debts WHERE id=$id");
        header("Location: /my-debt");
        exit();
    }
}

// --- FILTERING & FETCH DATA ---
$where_clauses = [];
// Force filter by current user's first name
$user_first = explode(' ', trim($_SESSION['full_name']))[0];
$where_clauses[] = "am LIKE '%" . $conn->real_escape_string($user_first) . "%'";

if (!empty($_GET['status'])) {
    $status_filter = $conn->real_escape_string($_GET['status']);
    $where_clauses[] = "payment_status = '$status_filter'";
}

if (!empty($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $where_clauses[] = "(client_name LIKE '%$search%' OR project_name LIKE '%$search%' OR vat_invoice LIKE '%$search%')";
}

if (!empty($_GET['year'])) {
    $year = intval($_GET['year']);
    $where_clauses[] = "YEAR(invoice_date) = $year";
}

if (!empty($_GET['quarter'])) {
    $qtr = intval($_GET['quarter']);
    if ($qtr == 1)
        $where_clauses[] = "MONTH(invoice_date) IN (1,2,3)";
    elseif ($qtr == 2)
        $where_clauses[] = "MONTH(invoice_date) IN (4,5,6)";
    elseif ($qtr == 3)
        $where_clauses[] = "MONTH(invoice_date) IN (7,8,9)";
    elseif ($qtr == 4)
        $where_clauses[] = "MONTH(invoice_date) IN (10,11,12)";
}

if (!empty($_GET['month'])) {
    $month = intval($_GET['month']);
    $where_clauses[] = "MONTH(invoice_date) = $month";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$groupedDebts = [];
$monthTotals = [];
$total_amount_usd = 0;
$total_amount_vnd = 0;

$res = $conn->query("SELECT * FROM debts $where_sql ORDER BY invoice_date DESC, id DESC");
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

        // Grouping
        $row['amount_original'] = $amount;
        $row['currency_original'] = $curr;
        $row['amount'] = $vnd_value;
        $row['currency'] = 'VND';

        $mKey = !empty($row['invoice_date']) ? date('m/Y', strtotime($row['invoice_date'])) : 'No Date';
        $groupedDebts[$mKey][] = $row;
        if (!isset($monthTotals[$mKey]))
            $monthTotals[$mKey] = 0;
        $monthTotals[$mKey] += $vnd_value;
    }
}
$debts = []; // To keep debtsData for JS populated
foreach ($groupedDebts as $m => $items) {
    foreach ($items as $item)
        $debts[] = $item;
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

// Fetch Departments for Company / Sale Team Select
$sale_teams = [];
$res_dept = $conn->query("SELECT name FROM departments ORDER BY sort_order ASC, name ASC");
if ($res_dept && $res_dept->num_rows > 0) {
    while ($row_dept = $res_dept->fetch_assoc()) {
        $n = trim($row_dept['name']);
        if (!empty($n) && !in_array($n, $sale_teams)) {
            $sale_teams[] = $n;
        }
    }
} else {
    // Fallback if none found
    $sale_teams = ['AHT TECH', 'A1VN', 'A1C MY'];
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
            font-size: 13px;
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
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #003655;
            z-index: 10;
            white-space: normal;
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
            padding: 8px 10px;
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

        /* Badges/Pills */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }

        /* Invoice Status Colors - Uniform Width */
        .status-done,
        .status-tim,
        .status-xanh,
        .status-trang,
        .status-chuaxacdinh,
        .status-do,
        .status-select {
            display: inline-block;
            width: 110px;
            text-align: center;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            box-sizing: border-box;
            border: 1px solid transparent;
        }

        .status-done {
            background: #3b82f6;
            color: white;
        }

        .status-tim {
            background: #a855f7;
            color: white;
        }

        .status-xanh {
            background: #bae6fd;
            color: #0369a1;
        }

        .status-trang {
            background: #fce7f3;
            color: #be185d;
        }

        .status-chuaxacdinh {
            background: #f1f5f9;
            color: #64748b;
        }

        .status-do {
            background: #fee2e2;
            color: #b91c1c;
        }

        select.status-select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            font-family: inherit;
        }

        select.status-select:hover {
            border: 1px solid #cbd5e1;
            opacity: 0.9;
        }

        select.status-select:hover {
            border: 1px solid #cbd5e1;
            opacity: 0.9;
        }

        /* AM Colors */
        .am-badge {
            padding: 4px 10px;
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
        .status-d5 {
            background-color: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }

        .status-tot {
            background-color: #e0e7ff;
            color: #4338ca;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
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

        .group-header td {
            background-color: #f1f5f9 !important;
            font-weight: 700;
            color: #334155;
            padding: 12px 15px !important;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
        }

        .group-total {
            color: #10b981;
            margin-left: 10px;
        }

        .total-badge {
            margin-left: auto;
            margin-right: 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #475569;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 6px;
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

        /* Autocomplete Suggestions */
        .autocomplete-container {
            position: relative;
        }

        .suggestions-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1050;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 0 0 6px 6px;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
            color: #334155;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .suggestion-item strong {
            color: #2563eb;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'My Debts (' . htmlspecialchars($_SESSION['full_name']) . ')';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="debt-container">
                <div class="page-controls">
                    <form method="GET" class="filter-group">
                        <input type="text" name="q" class="search-input"
                            placeholder="Search Client, Project, Invoice..."
                            value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">

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

                    <div class="total-badge" style="background: #ecfdf5; border-color: #10b981; color: #065f46;">
                        Total: <?php echo formatVND($total_amount_vnd); ?>
                    </div>

                    <button class="btn-add" onclick="openModal('add')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="3">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Record
                    </button>
                </div>

                <div class="data-table-wrapper">
                    <table class="debt-table">
                        <thead>
                            <tr>
                                <th
                                    style="width: 30px !important; min-width: 30px !important; max-width: 30px !important; padding: 0 4px; text-align: center;">
                                    #</th>
                                <th style="text-align:center; width: 80px;">Action</th>
                                <th>CTY</th>
                                <th>AM</th>
                                <th>Tên<br>khách hàng</th>
                                <th>Tên dự án</th>
                                <th>Ngày<br>hóa đơn</th>
                                <th>Mốc<br>thanh toán</th>
                                <th>Ngày DK<br>hoàn thành SX</th>
                                <th>Ngày DK<br>khách TT</th>
                                <th>Phân loại<br>hóa đơn</th>
                                <th>Số tiền</th>
                                <th>P&L</th>
                                <th>Hóa đơn</th>
                                <th>HĐ<br>VAT</th>
                                <th>Trạng thái<br>TT</th>
                                <th>Tháng<br>TT</th>
                                <th>Cập nhật<br>tuần</th>
                                <th>Ghi chú<br>AM</th>
                                <th>Ghi chú<br>Delivery</th>
                                <th>Trạng thái<br>SX</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $globalIdx = 1; ?>
                            <?php foreach ($groupedDebts as $monthName => $monthItems): ?>
                                <tr class="group-header">
                                    <td colspan="19">
                                        Tháng <?php echo $monthName; ?>
                                        <span class="group-total">(Total:
                                            <?php echo formatVND($monthTotals[$monthName]); ?>)</span>
                                    </td>
                                </tr>
                                <?php foreach ($monthItems as $d): ?>
                                    <tr ondblclick="openModal('edit', <?php echo $d['id']; ?>)">
                                        <td style="text-align: center; padding: 4px;"><?php echo $globalIdx++; ?></td>
                                        <td style="text-align:center; white-space: nowrap;">
                                            <button class="btn-sync-row"
                                                onclick="syncDebt(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['vat_invoice']); ?>', this); event.stopPropagation();"
                                                title="Sync from Odoo"
                                                style="background:none; border:none; cursor:pointer; color:#0ea5e9; padding: 4px; margin-right: 5px;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M23 4v6h-6"></path>
                                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                                </svg>
                                            </button>
                                            <button class="btn-edit-row"
                                                onclick="openModal('edit', <?php echo $d['id']; ?>); event.stopPropagation();"
                                                title="Edit">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                            <form method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this debt?');"
                                                style="display:inline-block; margin-left: 5px;"
                                                onclick="event.stopPropagation();">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                                <button type="submit" class="btn-delete-row"
                                                    style="background:none; border:none; cursor:pointer; color:#ef4444; padding: 4px;"
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
                                        </td>
                                        <td class="cell-company"><?php echo htmlspecialchars($d['company']); ?></td>
                                        <td>
                                            <?php
                                            // Format AM Badge
                                            $am_class = 'am-default';
                                            if (stripos($d['am'], 'Emily') !== false)
                                                $am_class = 'am-emily';
                                            if (stripos($d['am'], 'Hyun') !== false)
                                                $am_class = 'am-hyun';
                                            if (stripos($d['am'], 'Ryan') !== false)
                                                $am_class = 'am-ryan';
                                            ?>
                                            <span
                                                class="badge am-badge <?php echo $am_class; ?>"><?php echo htmlspecialchars($d['am']); ?></span>
                                        </td>
                                        <td class="cell-company"><?php echo htmlspecialchars($d['client_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($d['project_name'] ?? ''); ?></td>
                                        <td><?php echo formatDate($d['invoice_date']); ?></td>
                                        <td style="position: relative;">
                                            <input type="text"
                                                value="<?php echo htmlspecialchars($d['payment_milestone'] ?? ''); ?>"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'payment_milestone', this.value, this)"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: inherit; outline: none; box-sizing: border-box;">
                                        </td>
                                        <td style="position: relative;">
                                            <input type="date" value="<?php echo $d['expected_prod_date']; ?>"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'expected_prod_date', this.value, this)"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: inherit; outline: none; box-sizing: border-box;">
                                        </td>
                                        <td style="position: relative;">
                                            <input type="date" value="<?php echo $d['expected_payment_date']; ?>"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'expected_payment_date', this.value, this)"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: inherit; outline: none; box-sizing: border-box;">
                                        </td>
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
                                            elseif ($st === 'Tốt')
                                                $bgClass = 'status-xanh'; // Legacy
                                            elseif ($st === 'Chưa xác định')
                                                $bgClass = 'status-chuaxacdinh';
                                            elseif ($st === 'Đỏ')
                                                $bgClass = 'status-do';
                                            elseif ($st === 'PP')
                                                $bgClass = 'status-pp';
                                            elseif ($st === 'Draft')
                                                $bgClass = 'status-draft';

                                            if ($st === 'Done' || $st === 'Tím' || $st === 'Đỏ') {
                                                echo "<span class='$bgClass' style='margin-top: 6px;'>" . htmlspecialchars($st) . "</span>";
                                            } else {
                                                // Editable Select
                                                ?>
                                                <select class="status-select <?php echo $bgClass; ?>" autocomplete="off"
                                                    onchange="this.className = 'status-select status-' + this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ''); updateInline(<?php echo $d['id']; ?>, 'invoice_status_class', this.value, this)"
                                                    onclick="event.stopPropagation();">
                                                    <option value="Trắng" <?php echo ($st === 'Trắng') ? 'selected' : ''; ?>>Trắng
                                                    </option>
                                                    <option value="Xanh" <?php echo ($st === 'Xanh' || $st === 'Tốt') ? 'selected' : ''; ?>>Xanh</option>
                                                    <option value="PP" <?php echo ($st === 'PP') ? 'selected' : ''; ?>>PP</option>
                                                    <option value="Draft" <?php echo ($st === 'Draft') ? 'selected' : ''; ?>>Draft
                                                    </option>
                                                    <option value="Chưa xác định" <?php echo ($st === 'Chưa xác định' || ($st !== 'Trắng' && $st !== 'Xanh' && $st !== 'Tốt' && $st !== 'PP' && $st !== 'Draft')) ? 'selected' : ''; ?>>Chưa xác định</option>
                                                </select>
                                                <?php
                                            }
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
                                        <td style="position: relative;">
                                            <input type="text"
                                                value="<?php echo htmlspecialchars($d['invoice_status'] ?? ''); ?>"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'invoice_status', this.value, this)"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: inherit; outline: none; box-sizing: border-box;">
                                        </td>
                                        <td><?php echo htmlspecialchars($d['vat_invoice'] ?? ''); ?></td>
                                        <td>
                                            <span
                                                class="badge <?php echo (stripos($d['payment_status'] ?? '', 'Not') !== false ? 'pay-not-paid' : 'pay-paid'); ?>">
                                                <?php echo htmlspecialchars($d['payment_status'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($d['payment_month'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($d['weekly_update'] ?? ''); ?></td>
                                        <td style="position: relative;">
                                            <input type="text" value="<?php echo htmlspecialchars($d['am_notes'] ?? ''); ?>"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'am_notes', this.value, this)"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: 0.85rem; color: #555; outline: none; box-sizing: border-box; text-overflow: ellipsis;">
                                        </td>
                                        <td style="position: relative;">
                                            <input type="text"
                                                value="<?php echo htmlspecialchars($d['delivery_notes'] ?? ''); ?>"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'delivery_notes', this.value, this)"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: 0.85rem; color: #555; outline: none; box-sizing: border-box; text-overflow: ellipsis;">
                                        </td>
                                        <td style="position: relative; text-align: center;">
                                            <?php
                                            $ps = $d['production_status'] ?? '';
                                            $prodClass = 'prod-dc2';
                                            if (stripos($ps, 'Overdue') !== false || stripos($ps, 'DC5') !== false)
                                                $prodClass = 'prod-dc5';
                                            elseif (stripos($ps, 'DC1') !== false)
                                                $prodClass = 'prod-dc1';
                                            ?>
                                            <select
                                                onchange="updateInline(<?php echo $d['id']; ?>, 'production_status', this.value, this)"
                                                onclick="event.stopPropagation();"
                                                style="width: 100%; border: 1px solid transparent; background: transparent; padding: 4px 8px; font-family: inherit; font-size: 0.85rem; cursor: pointer; border-radius: 4px; outline: none;">
                                                <option value="">-- Trạng thái --</option>
                                                <option value="BCITO" <?php echo ($ps === 'BCITO') ? 'selected' : ''; ?>>BCITO
                                                </option>
                                                <?php for ($i = 3; $i <= 10; $i++): ?>
                                                    <option value="BC<?php echo $i; ?>" <?php echo ($ps === 'BC' . $i) ? 'selected' : ''; ?>>BC<?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

                    <!-- Hidden fields to preserve data during update -->
                    <input type="hidden" name="vat_invoice" id="vat_invoice_input">
                    <input type="hidden" name="payment_month" id="payment_month_input">
                    <input type="hidden" name="weekly_update" id="weekly_update_input">
                    <input type="hidden" name="delivery_notes" id="delivery_notes_input">
                    <input type="hidden" name="invoice_status" id="invoice_status_input">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Sale Team / Company</label>
                            <select name="company" id="company">
                                <?php foreach ($sale_teams as $team): ?>
                                    <option value="<?php echo htmlspecialchars($team); ?>">
                                        <?php echo htmlspecialchars($team); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>AM</label>
                            <select name="am" id="am">
                                <?php foreach ($am_list as $am_name): ?>
                                    <option value="<?php echo htmlspecialchars($am_name); ?>">
                                        <?php echo htmlspecialchars($am_name); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group autocomplete-container">
                            <label>Client Name</label>
                            <input type="text" name="client_name" id="client_name" required autocomplete="off"
                                placeholder="Type to search Odoo customers...">
                            <div id="client_suggestions" class="suggestions-list"></div>
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
                                <option value="TB">TB (Average)</option>
                                <option value="Xấu">Xấu (Bad)</option>
                                <option value="Tốt">Tốt (Good)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" id="payment_status">
                                <option value="Not paid">Not paid</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Production Status</label>
                            <input type="text" name="production_status" id="production_status"
                                placeholder="e.g. DC1, DC5 ONFIT">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>AM Notes</label>
                        <textarea name="am_notes" id="am_notes" rows="2"></textarea>
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
        // Pass PHP array to JS safely
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
                // Find data by ID
                const data = debtsData.find(d => d.id == id);
                if (!data) return;

                document.getElementById('modalTitle').innerText = "Edit Record";
                document.getElementById('formAction').value = "edit";
                document.getElementById('editId').value = data.id;
                document.getElementById('btnDelete').style.display = "block";

                // Populate fields
                // Handle Sale Team / Company Dropdown safely
                const companySelect = document.getElementById('company');
                const compVal = data.company || 'AHT TECH';
                let compExists = false;
                for (let i = 0; i < companySelect.options.length; i++) {
                    if (companySelect.options[i].value === compVal) {
                        compExists = true;
                        break;
                    }
                }
                if (!compExists && compVal) {
                    const opt = document.createElement('option');
                    opt.value = compVal;
                    opt.text = compVal;
                    companySelect.add(opt);
                }
                companySelect.value = compVal;

                // Handle AM Dropdown safely
                const amSelect = document.getElementById('am');
                const amVal = data.am;
                let amExists = false;
                for (let i = 0; i < amSelect.options.length; i++) {
                    if (amSelect.options[i].value === amVal) {
                        amExists = true;
                        break;
                    }
                }
                if (!amExists && amVal) {
                    const opt = document.createElement('option');
                    opt.value = amVal;
                    opt.text = amVal;
                    amSelect.add(opt);
                }
                amSelect.value = amVal;

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

        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Autocomplete Logic
        const clientInput = document.getElementById('client_name');

        // Remove existing old suggestions
        const oldSuggestions = document.getElementById('client_suggestions');
        if (oldSuggestions) oldSuggestions.remove();

        const suggestionsBox = document.createElement('div');
        suggestionsBox.id = 'client_suggestions';
        suggestionsBox.style.cssText = `
            position: fixed;
            z-index: 2147483647;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            max-height: 250px;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: none;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        `;
        document.body.appendChild(suggestionsBox);

        let searchTimeout = null;

        clientInput.addEventListener('input', function () {
            const query = this.value.trim();
            if (searchTimeout) clearTimeout(searchTimeout);

            if (query.length < 1) {
                suggestionsBox.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetchSuggestions(query);
            }, 300);
        });

        // Show suggestions on focus
        clientInput.addEventListener('focus', function () {
            if (this.value.trim().length >= 1) {
                fetchSuggestions(this.value.trim());
            }
        });

        // Select text on click for easy edit
        clientInput.addEventListener('click', function () {
            // this.select(); // Optional
        });

        function updateSuggestionPosition() {
            if (suggestionsBox.style.display !== 'none' && suggestionsBox.style.display !== '') {
                const rect = clientInput.getBoundingClientRect();
                if (rect.width === 0) {
                    suggestionsBox.style.display = 'none';
                    return;
                }
                suggestionsBox.style.top = (rect.bottom + 2) + 'px';
                suggestionsBox.style.left = rect.left + 'px';
                suggestionsBox.style.width = rect.width + 'px';
            }
        }

        window.addEventListener('resize', updateSuggestionPosition);
        window.addEventListener('scroll', updateSuggestionPosition, true);

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (e.target !== clientInput && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
            }
        });

        // Close on modal scroll
        const modalBody = document.querySelector('.modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', updateSuggestionPosition);
        }

        function fetchSuggestions(query) {
            const rect = clientInput.getBoundingClientRect();
            if (rect.width === 0) return;

            // Show immediately to avoid lag feeling
            // suggestionsBox.style.display = 'block';
            // Better to wait for data

            fetch(`/api/customers.php?search=${encodeURIComponent(query)}&limit=10`)
                .then(response => response.json())
                .then(data => {
                    suggestionsBox.innerHTML = '';

                    suggestionsBox.style.display = 'block';
                    suggestionsBox.style.top = (rect.bottom + 2) + 'px';
                    suggestionsBox.style.left = rect.left + 'px';
                    suggestionsBox.style.width = rect.width + 'px';

                    if (data.data && data.data.length > 0) {
                        data.data.forEach(customer => {
                            const div = document.createElement('div');
                            div.style.padding = '10px 12px';
                            div.style.cursor = 'pointer';
                            div.style.borderBottom = '1px solid #f1f5f9';
                            div.style.color = '#334155';
                            div.style.backgroundColor = '#ffffff';

                            div.onmouseover = () => { div.style.backgroundColor = '#f8fafc'; div.style.color = '#0f172a'; };
                            div.onmouseout = () => { div.style.backgroundColor = '#ffffff'; div.style.color = '#334155'; };

                            const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                            const highlightedName = (customer.name || '').replace(regex, '<strong style="color:#2563eb">$1</strong>');

                            div.innerHTML = highlightedName;

                            div.onclick = (e) => {
                                e.stopPropagation();
                                clientInput.value = customer.name;
                                suggestionsBox.style.display = 'none';
                            };

                            suggestionsBox.appendChild(div);
                        });
                    } else {
                        const div = document.createElement('div');
                        div.style.padding = '10px 12px';
                        div.style.color = '#94a3b8';
                        div.style.fontStyle = 'italic';
                        div.innerText = 'Không tìm thấy kết quả';
                        suggestionsBox.appendChild(div);
                    }
                })
                .catch(err => {
                    console.error('Error fetching suggestions:', err);
                    suggestionsBox.style.display = 'none';
                });
        }

        // --- Project Autocomplete Logic ---
        const projectInput = document.getElementById('project_name');
        const projectSuggestionsBox = document.createElement('div');
        projectSuggestionsBox.id = 'project_suggestions_box';
        document.body.appendChild(projectSuggestionsBox); // Append to body

        Object.assign(projectSuggestionsBox.style, {
            position: 'fixed',
            background: 'white',
            border: '1px solid #e2e8f0',
            borderRadius: '8px',
            boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
            maxHeight: '250px',
            overflowY: 'auto',
            zIndex: '2147483647',
            display: 'none',
            fontSize: '0.9rem',
            width: '100%',
        });

        let projectSearchTimeout = null;

        if (projectInput) {
            projectInput.addEventListener('input', function () {
                const query = this.value.trim();
                if (projectSearchTimeout) clearTimeout(projectSearchTimeout);

                if (query.length < 1) {
                    projectSuggestionsBox.style.display = 'none';
                    return;
                }

                projectSearchTimeout = setTimeout(() => {
                    fetchProjectSuggestions(query);
                }, 300);
            });

            projectInput.addEventListener('focus', function () {
                if (this.value.trim().length >= 1) {
                    fetchProjectSuggestions(this.value.trim());
                }
            });

            function updateProjectSuggestionPosition() {
                if (projectSuggestionsBox.style.display !== 'none' && projectSuggestionsBox.style.display !== '') {
                    const rect = projectInput.getBoundingClientRect();
                    // Check if input is visible
                    if (rect.width === 0 || rect.top === 0) {
                        projectSuggestionsBox.style.display = 'none';
                        return;
                    }
                    projectSuggestionsBox.style.top = (rect.bottom + 2) + 'px';
                    projectSuggestionsBox.style.left = rect.left + 'px';
                    projectSuggestionsBox.style.width = rect.width + 'px';
                }
            }

            window.addEventListener('resize', updateProjectSuggestionPosition);
            window.addEventListener('scroll', updateProjectSuggestionPosition, true);

            // Re-use modalBody from client logic if available
            const modalBody = document.querySelector('.modal-body');
            if (modalBody) {
                modalBody.addEventListener('scroll', updateProjectSuggestionPosition);
            }

            document.addEventListener('click', function (e) {
                if (e.target !== projectInput && !projectSuggestionsBox.contains(e.target)) {
                    projectSuggestionsBox.style.display = 'none';
                }
            });

            function fetchProjectSuggestions(query) {
                const rect = projectInput.getBoundingClientRect();
                if (rect.width === 0) return;

                fetch(`/api/projects.php?search=${encodeURIComponent(query)}&limit=10`)
                    .then(response => response.json())
                    .then(data => {
                        projectSuggestionsBox.innerHTML = '';
                        // Set position before showing content to avoid jumps
                        updateProjectSuggestionPosition();

                        if (data.data && data.data.length > 0) {
                            projectSuggestionsBox.style.display = 'block';
                            updateProjectSuggestionPosition(); // Update again to be safe

                            data.data.forEach(project => {
                                const div = document.createElement('div');
                                div.style.padding = '10px 12px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #f1f5f9';
                                div.style.color = '#334155';
                                div.style.backgroundColor = '#ffffff';
                                div.style.transition = 'background 0.1s';

                                div.onmouseover = () => { div.style.backgroundColor = '#f8fafc'; div.style.color = '#0f172a'; };
                                div.onmouseout = () => { div.style.backgroundColor = '#ffffff'; div.style.color = '#334155'; };

                                // Format: [KEY] Name
                                const displayName = `[${project.key}] ${project.name}`;
                                const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                                const highlighted = displayName.replace(regex, '<strong style="color:#2563eb">$1</strong>');

                                div.innerHTML = highlighted;

                                div.onclick = (e) => {
                                    e.stopPropagation();
                                    projectInput.value = project.name; // Use Name only
                                    projectSuggestionsBox.style.display = 'none';
                                };

                                projectSuggestionsBox.appendChild(div);
                            });
                        } else {
                            projectSuggestionsBox.style.display = 'block';
                            updateProjectSuggestionPosition();

                            const div = document.createElement('div');
                            div.style.padding = '10px 12px';
                            div.style.color = '#94a3b8';
                            div.style.fontStyle = 'italic';
                            div.innerText = 'Không tìm thấy Project';
                            projectSuggestionsBox.appendChild(div);
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching project suggestions:', err);
                        projectSuggestionsBox.style.display = 'none';
                    });
            }
        }
        function syncDebt(id, invoiceName, btn) {
            if (!confirm(`Sync debt #${id} with latest data from Invoice ${invoiceName}?`)) return;

            const originalIcon = btn.innerHTML;
            // Simple spinner
            btn.innerHTML = '<svg style="animation: spin 1s linear infinite;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('id', id);
            formData.append('vat_invoice', invoiceName);

            fetch('/api/sync_debt.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Sync Error: ' + (data.error || 'Unknown error'));
                        btn.innerHTML = originalIcon;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    alert('Connection Error: ' + err.message);
                    btn.innerHTML = originalIcon;
                    btn.disabled = false;
                });
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
                        if (el) el.style.backgroundColor = '#fee2e2'; // Red flash
                    } else {
                        if (el) {
                            // Success Tick
                            const parent = el.parentElement;
                            const tick = document.createElement('span');
                            tick.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                            tick.style.position = 'absolute';
                            tick.style.right = '5px';
                            tick.style.top = '50%';
                            tick.style.transform = 'translateY(-50%)';
                            tick.style.zIndex = '10';
                            tick.style.pointerEvents = 'none';

                            // Remove existing tick if any
                            const oldTick = parent.querySelector('.inline-success-tick');
                            if (oldTick) oldTick.remove();

                            tick.classList.add('inline-success-tick');
                            parent.appendChild(tick);

                            // Fade out
                            setTimeout(() => {
                                tick.style.transition = 'opacity 0.5s';
                                tick.style.opacity = '0';
                                setTimeout(() => tick.remove(), 500);
                            }, 1500);
                        }
                    }
                })
                .catch(err => {
                    console.error('Update valid error:', err);
                    if (el) el.style.backgroundColor = '#fee2e2';
                });
        }

    </script>
</body>

</html>