<?php
// trigger update
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
        sale_team VARCHAR(100),
        sale_team_id INT DEFAULT NULL,
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
        $sale_team_id = !empty($_POST['sale_team_id']) ? intval($_POST['sale_team_id']) : NULL;
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
            $stmt = $conn->prepare("INSERT INTO debts (company, sale_team_id, am, client_name, project_name, payment_milestone, expected_prod_date, expected_payment_date, invoice_status_class, amount, currency, invoice_status, vat_invoice, invoice_date, payment_status, payment_month, weekly_update, am_notes, delivery_notes, production_status, pl_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssssssssdsssssssss", $company, $sale_team_id, $am, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl);
        } else {
            // Edit
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE debts SET company=?, sale_team_id=?, am=?, client_name=?, project_name=?, payment_milestone=?, expected_prod_date=?, expected_payment_date=?, invoice_status_class=?, amount=?, currency=?, invoice_status=?, vat_invoice=?, invoice_date=?, payment_status=?, payment_month=?, weekly_update=?, am_notes=?, delivery_notes=?, production_status=?, pl_class=? WHERE id=?");
            $stmt->bind_param("sisssssssssdsssssssssi", $company, $sale_team_id, $am, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl, $id);
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
$where_clauses[] = "d.am LIKE '%" . $conn->real_escape_string($user_first) . "%'";

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

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$groupedDebts = [];
$monthTotals = [];
$total_amount_usd = 0;
$total_amount_vnd = 0;

$res = $conn->query("SELECT d.*, st.name as team_name FROM debts d LEFT JOIN sale_teams st ON d.sale_team_id = st.id $where_sql ORDER BY d.invoice_date DESC, d.id DESC");

// Trigger cache refresh if needed (OdooAPI::getInvoices handles the 1-hour check internally)
$odoo->getInvoices(1, 0);
$odoo_map = $odoo->getInvoiceMap();
$odoo_name_map = [];
foreach ($odoo_map as $inv) {
    if (!empty($inv['name'])) {
        $odoo_name_map[$inv['name']] = $inv;
    }
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $amount = (float) $row['amount'];
        $curr = $row['currency'] ?: 'USD';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
        $oid = $row['odoo_invoice_id'];

        // AUTO SYNC LOGIC: Update debt record from Odoo map if mismatch found
        $inv = null;
        if (!empty($oid) && isset($odoo_map[$oid])) {
            $inv = $odoo_map[$oid];
        } elseif (!empty($row['vat_invoice']) && isset($odoo_name_map[$row['vat_invoice']])) {
            $inv = $odoo_name_map[$row['vat_invoice']];
        }

        if ($inv) {
            $changed = false;
            $upSql = [];
            $upParams = [];
            $upTypes = "";

            // 1. Odoo ID check
            if (empty($oid) && !empty($inv['id'])) {
                $upSql[] = "odoo_invoice_id = ?";
                $upParams[] = $inv['id'];
                $upTypes .= "i";
                $row['odoo_invoice_id'] = $inv['id'];
                $oid = $inv['id'];
                $changed = true;
            }

            // 2. Amount and Currency
            $newAmt = (float) $inv['amount_total'];
            $newCurr = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
            if (abs($newAmt - (float) $row['amount']) > 0.01 || $newCurr !== ($row['currency'] ?? '')) {
                $upSql[] = "amount = ?, original_amount = ?, currency = ?";
                $upParams[] = $newAmt;
                $upParams[] = $newAmt;
                $upParams[] = $newCurr;
                $upTypes .= "dds";
                $row['amount'] = $newAmt;
                $row['currency'] = $newCurr;
                $amount = $newAmt; // update local for VND calc
                $curr = $newCurr;   // update local
                $changed = true;
            }

            // 3. Payment Status & Fields (Logic from api/sync_debt.php)
            $pState = $inv['payment_state'] ?? '';
            $newPayStat = ($pState === 'paid' || $pState === 'in_payment') ? 'Paid' : 'Not paid';

            // Calculate Payment Date for status class/month/week
            $paymentDate = $inv['write_date'] ?? null;
            if (!empty($inv['invoice_payments_widget'])) {
                $widget = $inv['invoice_payments_widget'];
                if (is_string($widget))
                    $widget = json_decode($widget, true);
                if (!empty($widget['content'])) {
                    $dates = array_column($widget['content'], 'date');
                    if ($dates)
                        $paymentDate = max($dates);
                }
            }

            $paymentMonth = '';
            $weeklyUpdate = '';
            $invoiceStatusClass = $row['invoice_status_class']; // Start with existing

            if ($newPayStat === 'Paid') {
                if (!empty($paymentDate)) {
                    $ts = strtotime($paymentDate);
                    $currentMonth = date('Y-m');
                    $paidMonth = date('Y-m', $ts);
                    $paymentMonth = date('m/Y', $ts);
                    $dayOfMonth = date('j', $ts);
                    $weekOfMonth = ceil($dayOfMonth / 7);
                    $weeklyUpdate = "Tuần " . $weekOfMonth;

                    if ($paidMonth === $currentMonth)
                        $invoiceStatusClass = 'Tím';
                    else if ($paidMonth < $currentMonth)
                        $invoiceStatusClass = 'Done';
                    else
                        $invoiceStatusClass = 'Tím';
                } else {
                    $invoiceStatusClass = 'Done';
                }
            } else {
                // Not paid - check if overdue (> 60 days from invoice date)
                $invDate = $inv['invoice_date'] ?? $inv['date'] ?? '';
                if (!empty($invDate)) {
                    $invTs = strtotime($invDate);
                    if (floor((time() - $invTs) / 86400) > 60)
                        $invoiceStatusClass = 'Đỏ';
                    else if ($pState === 'draft')
                        $invoiceStatusClass = 'Draft';
                }
            }



            // Check if any of these derived fields changed
            if (
                $newPayStat !== ($row['payment_status'] ?? '') ||
                $paymentMonth !== ($row['payment_month'] ?? '') ||
                $weeklyUpdate !== ($row['weekly_update'] ?? '') ||
                $invoiceStatusClass !== ($row['invoice_status_class'] ?? '')
            ) {

                $upSql[] = "payment_status = ?, payment_month = ?, weekly_update = ?, invoice_status_class = ?";
                $upParams[] = $newPayStat;
                $upParams[] = $paymentMonth;
                $upParams[] = $weeklyUpdate;
                $upParams[] = $invoiceStatusClass;
                $upTypes .= "ssss";

                $row['payment_status'] = $newPayStat;
                $row['payment_month'] = $paymentMonth;
                $row['weekly_update'] = $weeklyUpdate;
                $row['invoice_status_class'] = $invoiceStatusClass;
                $changed = true;
            }

            // Sync VAT Invoice if different
            $newVat = $inv['name'] ?? '';
            if ($newVat && $newVat !== ($row['vat_invoice'] ?? '')) {
                $upSql[] = "vat_invoice = ?";
                $upParams[] = $newVat;
                $upTypes .= "s";
                $row['vat_invoice'] = $newVat;
                $changed = true;
            }

            // Sync Invoice Date if different
            $newInvDateVal = $inv['invoice_date'] ?: $inv['date'];
            if ($newInvDateVal && $newInvDateVal !== ($row['invoice_date'] ?? '')) {
                $upSql[] = "invoice_date = ?";
                $upParams[] = $newInvDateVal;
                $upTypes .= "s";
                $row['invoice_date'] = $newInvDateVal;
                $date = $newInvDateVal; // update local
                $changed = true;
            }

            if ($changed) {
                $upSql[] = "updated_at = NOW()";
                $updateStr = implode(", ", $upSql);
                $stmtUp = $conn->prepare("UPDATE debts SET $updateStr WHERE id = ?");
                $upParams[] = $row['id'];
                $upTypes .= "i";
                $stmtUp->bind_param($upTypes, ...$upParams);
                $stmtUp->execute();
                $stmtUp->close();
            }
        }

        // Convert to VND using Odoo exchange rate ratio if available
        $vnd_value = 0;
        if (!empty($oid) && isset($odoo_map[$oid])) {
            $odoo_inv = $odoo_map[$oid];
            $odoo_total = (float) $odoo_inv['amount_total'];
            $odoo_signed = abs((float) $odoo_inv['amount_total_signed']);

            if ($odoo_total > 0) {
                // Apply ratio from Odoo to the debt amount
                $vnd_value = $amount * ($odoo_signed / $odoo_total);
            }
        }

        // Fallback to manual rate calculation if needed
        if ($vnd_value <= 0) {
            $rate = $odoo->getRate($curr, $date);
            $vnd_value = ($rate > 0) ? ($amount / $rate) : $amount;
        }

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
$all_teams = [];
$team_res = $conn->query("SELECT * FROM sale_teams ORDER BY order_num ASC, id DESC");
if ($team_res && $team_res->num_rows > 0) {
    while ($tr = $team_res->fetch_assoc()) {
        $all_teams[] = $tr;
    }
} else {
    // Fallback if none found
    $all_teams = [
        ['id' => null, 'name' => 'AHT TECH'],
        ['id' => null, 'name' => 'A1VN'],
        ['id' => null, 'name' => 'A1C MY']
    ];
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
            overflow: visible;
            /* Changed from hidden to allow resizer handles */
            position: sticky !important;
            /* Ensure sticky wins */
        }

        /* Ensure th is relative for resizers */
        table.debt-table thead th {
            position: sticky !important;
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

        /* Sticky Columns */
        table.debt-table th:nth-child(1),
        table.debt-table tr:not(.group-header) td:nth-child(1) {
            box-sizing: border-box;
            position: sticky;
            left: 0;
            z-index: 8;
            width: 40px;
            min-width: 40px;
            max-width: 40px;
        }

        table.debt-table th:nth-child(2),
        table.debt-table tr:not(.group-header) td:nth-child(2) {
            box-sizing: border-box;
            position: sticky;
            left: 40px;
            z-index: 8;
            width: 80px;
            min-width: 80px;
            max-width: 80px;
            text-align: center;
        }

        table.debt-table th:nth-child(3),
        table.debt-table tr:not(.group-header) td:nth-child(3) {
            box-sizing: border-box;
            position: sticky;
            left: 120px;
            z-index: 8;
            width: 80px;
            min-width: 80px;
            max-width: 80px;
        }

        table.debt-table th:nth-child(4),
        table.debt-table tr:not(.group-header) td:nth-child(4) {
            box-sizing: border-box;
            position: sticky;
            left: 200px;
            z-index: 8;
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        table.debt-table th:nth-child(5),
        table.debt-table tr:not(.group-header) td:nth-child(5) {
            box-sizing: border-box;
            position: sticky;
            left: 300px;
            z-index: 8;
            width: 140px;
            min-width: 140px;
            max-width: 140px;
        }

        table.debt-table th:nth-child(6),
        table.debt-table tr:not(.group-header) td:nth-child(6) {
            box-sizing: border-box;
            position: sticky;
            left: 440px;
            z-index: 8;
            width: 220px;
            min-width: 220px;
            max-width: 220px;
        }

        table.debt-table th:nth-child(7),
        table.debt-table tr:not(.group-header) td:nth-child(7) {
            box-sizing: border-box;
            position: sticky;
            left: 660px;
            z-index: 8;
            width: 220px;
            min-width: 220px;
            max-width: 220px;
            border-right: 1px solid #cbd5e1 !important;
            box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.1);
        }

        .col-resizer {
            width: 8px;
            /* Slightly wider */
            height: 100%;
            position: absolute;
            right: 0;
            top: 0;
            cursor: col-resize;
            z-index: 20;
            /* Higher than sticky columns */
            user-select: none;
        }

        .col-resizer:hover,
        .col-resizer.active {
            background-color: rgba(14, 165, 233, 0.5);
            /* Blue highlight on hover */
            border-right: 2px solid #0ea5e9;
        }

        .resizing {
            cursor: col-resize;
            user-select: none;
        }

        .sticky-editable-cell {
            position: sticky !important;
            z-index: 8;
        }

        table.debt-table tr:not(.group-header) td:nth-child(1),
        table.debt-table tr:not(.group-header) td:nth-child(2),
        table.debt-table tr:not(.group-header) td:nth-child(3),
        table.debt-table tr:not(.group-header) td:nth-child(4),
        table.debt-table tr:not(.group-header) td:nth-child(5),
        table.debt-table tr:not(.group-header) td:nth-child(6),
        table.debt-table tr:not(.group-header) td:nth-child(7) {
            background-color: inherit;
        }

        table.debt-table th:nth-child(1),
        table.debt-table th:nth-child(2),
        table.debt-table th:nth-child(3),
        table.debt-table th:nth-child(4),
        table.debt-table th:nth-child(5),
        table.debt-table th:nth-child(6),
        table.debt-table th:nth-child(7) {
            z-index: 12;
            background-color: #004b75;
        }

        table.debt-table tbody {
            background-color: #fff;
        }

        table.debt-table tbody tr {
            background-color: #fff;
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

        /* Professional Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(15, 23, 42, 0.5);
            /* Darker backdrop */
            backdrop-filter: blur(6px);
            /* Glassmorphism background effect */
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #ffffff;
            margin: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 680px;
            /* Wider for better 2-column spacing */
            max-height: 90vh;
            /* Keeps it inside viewport */
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalScaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        @keyframes modalScaleIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close {
            font-size: 1.75rem;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
            line-height: 1;
        }

        .close:hover {
            color: #ef4444;
            transform: scale(1.1);
        }

        .modal-body {
            padding: 2rem;
            overflow-y: auto;
            flex: 1;
            /* allow it to take up remaining space */
            background-color: #ffffff;
        }

        /* Customize scrollbar for modal-body */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .modal-footer {
            padding: 1.25rem 2rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 0 0 16px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-cancel {
            padding: 0.7rem 1.5rem;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-submit {
            padding: 0.7rem 2rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-delete {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: #ffffff;
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

        /* Highlighting Row */
        .highlight-row {
            background-color: #fff9c4 !important;
            /* Soft yellow */
            transition: background-color 2s ease-out;
            border: 2px solid #fbc02d;
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
                    <table class="debt-table" id="myDebtsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Action</th>
                                <th>CTY</th>
                                <th>Sale Team</th>
                                <th>AM</th>
                                <th>Tên<br>khách hàng</th>
                                <th>Tên dự án</th>
                                <th>Ngày<br>hóa đơn</th>
                                <th>Mốc<br>thanh toán</th>
                                <th>Ngày DK<br>hoàn thành SX</th>
                                <th>Ngày DK<br>khách TT</th>
                                <th>Phân loại<br>hóa đơn</th>
                                <th>Tiền</th>
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
                                    <td colspan="23">
                                        <div style="position: sticky; left: 20px; display: inline-block; z-index: 13;">
                                            Tháng <?php echo $monthName; ?>
                                            <span class="group-total">(Total:
                                                <?php echo formatVND($monthTotals[$monthName]); ?>)</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php foreach ($monthItems as $d): ?>
                                    <?php
                                    $is_highlight = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $d['id']);
                                    ?>
                                    <tr id="debt-row-<?php echo $d['id']; ?>"
                                        class="<?php echo $is_highlight ? 'highlight-row' : ''; ?>"
                                        ondblclick="openModal('edit', <?php echo $d['id']; ?>)">
                                        <td style="text-align: center; padding: 4px;"><?php echo $globalIdx++; ?></td>
                                        <td style="text-align:center; white-space: nowrap; padding: 0;">
                                            <button class="btn-sync-row"
                                                onclick="syncDebt(<?php echo $d['id']; ?>, <?php echo htmlspecialchars(json_encode((string) ($d['vat_invoice'] ?? '')), ENT_QUOTES); ?>, this); event.stopPropagation();"
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
                                        <td><?php echo htmlspecialchars($d['team_name'] ?? ''); ?></td>
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
                                        <td class="sticky-editable-cell">
                                            <input type="text" value="<?php echo htmlspecialchars($d['project_name'] ?? ''); ?>"
                                                class="project-autocomplete-input" autocomplete="off"
                                                onclick="event.stopPropagation();"
                                                onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                onblur="setTimeout(() => { if (projectSuggestionsBox.style.display === 'none') { this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'project_name', this.value, this); } }, 300)"
                                                        style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: inherit; outline: none; box-sizing: border-box;">
                                                </td>
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
                                                                <option value="Tím" <?php echo ($st === 'Tím') ? 'selected' : ''; ?>>Tím</option>
                                                                <option value="PP" <?php echo ($st === 'PP') ? 'selected' : ''; ?>>PP</option>
                                                                <option value="Draft" <?php echo ($st === 'Draft') ? 'selected' : ''; ?>>Draft
                                                                </option>
                                                                <option value="Chưa xác định" <?php echo ($st === 'Chưa xác định' || ($st !== 'Trắng' && $st !== 'Xanh' && $st !== 'Tốt' && $st !== 'PP' && $st !== 'Draft' && $st !== 'Tím')) ? 'selected' : ''; ?>>Chưa xác định</option>
                                                            </select>
                                                            <?php
                                                    }
                                                    ?>
                                                </td>
                                                <td class="cell-amount" style="color: #64748b;">
                                                    <?php echo !empty($d['original_amount']) ? formatCurrency($d['original_amount'], $d['original_currency'] ?? $d['currency'] ?? 'USD') : '-'; ?>
                                                </td>
                                                <td class="cell-amount">
                                                    <?php echo formatCurrency($d['amount'] ?? 0, $d['currency'] ?? 'USD'); ?>
                                                </td>
                                                <td style="position: relative; text-align: center;">
                                                    <?php
                                                    $plVal = $d['pl_class'] ?? '';
                                                    $plBadgeClass = (stripos($plVal, 'Xấu') !== false ? 'pl-xau' : ((stripos($plVal, 'TB') !== false) ? 'pl-tb' : 'pl-tot'));
                                                    ?>
                                                    <select class="badge <?php echo $plBadgeClass; ?>"
                                                        onchange="this.className = 'badge ' + (this.value.includes('Xấu') ? 'pl-xau' : (this.value.includes('TB') ? 'pl-tb' : 'pl-tot')); updateInline(<?php echo $d['id']; ?>, 'pl_class', this.value, this)"
                                                        onclick="event.stopPropagation();"
                                                        style="width: 100%; border: 1px solid transparent; background: transparent; padding: 4px 8px; font-family: inherit; font-size: 0.85rem; cursor: pointer; text-align-last: center; outline: none;">
                                                        <option value="Tốt" <?php echo ($plVal === 'Tốt') ? 'selected' : ''; ?>>Tốt
                                                        </option>
                                                        <option value="TB" <?php echo ($plVal === 'TB') ? 'selected' : ''; ?>>TB</option>
                                                        <option value="Xấu" <?php echo ($plVal === 'Xấu') ? 'selected' : ''; ?>>Xấu
                                                        </option>
                                                    </select>
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

            <form method="POST" id="mainForm"
                style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
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
                            <label>Company</label>
                            <select name="company" id="company">
                                <option value="AHT TECH">AHT TECH</option>
                                <option value="A1VN">A1VN</option>
                                <option value="A1C MY">A1C MY</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sale Team</label>
                            <select name="sale_team_id" id="sale_team_id">
                                <option value="">-- Select Team
                                    --</option>
                                <?php foreach ($all_teams as $team): ?>
                                        <option value="<?php echo htmlspecialchars($team['id']); ?>">
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>AM</label>
                            <select name="am" id="am">
                                <?php foreach ($am_list as $am_name): ?>
                                        <option value="<?php echo htmlspecialchars($am_name); ?>">
                                            <?php echo htmlspecialchars($am_name); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Milestone</label>
                            <input type="text" name="payment_milestone" id="payment_milestone"
                                placeholder="e.g. Inv 08.2023">
                        </div>
                    </div>

                    <div class="form-row" style="grid-template-columns: 2fr 1fr;">
                        <div class="form-group autocomplete-container">
                            <label>Client Name</label>
                            <input type="text" name="client_name" id="client_name" required autocomplete="off"
                                placeholder="Type to search Odoo customers...">
                            <div id="client_suggestions" class="suggestions-list"></div>
                        </div>
                        <div class="form-group">
                            <label>Project Name</label>
                            <input type="text" name="project_name" id="project_name"
                                requiredclass="project-autocomplete-input" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="currency" id="currency">
                                <option value="USD">USD</option>
                                <option value="VND">VND</option>
                                <option value="EUR">EUR</option>
                                <option value="JPY">JPY</option>
                            </select>
                        </div>
                        <div class="form-group">
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
                            <input type="text" name="production_status" id="production_status"
                                placeholder="e.g. DC1, DC5 ONFIT">
                        </div>
                        <div class="form-group">
                            <label>AM Notes</label>
                            <textarea name="am_notes" id="am_notes" rows="1"
                                style="min-height: 44px; padding: 0.75rem 1rem;"></textarea>
                        </div>
                    </div>
                </div> <!-- end modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn-delete" id="btnDelete" onclick="deleteItem()"
                        style="display:none;">Delete
                        this record</button>
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
            modal.style.display = "flex"; // Changed from block to flex to support centering

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
                document.getElementById('company').value = data.company || 'AHT TECH';

                // Handle Sale Team Dropdown safely
                const saleTeamSelect = document.getElementById('sale_team_id');
                saleTeamSelect.value = data.sale_team_id || '';

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

        // Highlight and scroll logic
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('highlight_id');
            if (highlightId) {
                const targetRow = document.getElementById('debt-row-' + highlightId);
                if (targetRow) {
                    setTimeout(() => {
                        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 500);

                    // Remove highlight effect after a while
                    setTimeout(() => {
                        targetRow.classList.remove('highlight-row');
                        targetRow.style.border = 'none';
                    }, 5000);
                }
            }
        });

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

            if (query.length < 1) { suggestionsBox.style.display = 'none'; return; } searchTimeout = setTimeout(() => {
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

        // --- Project Autocomplete Logic (Generic for Modal and Inline) ---
        const projectSuggestionsBox = document.createElement('div');
        projectSuggestionsBox.id = 'project_suggestions_box';
        document.body.appendChild(projectSuggestionsBox);

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
        });

        let projectSearchTimeout = null;
        let activeProjectInput = null;

        function updateProjectSuggestionPosition() {
            if (activeProjectInput && projectSuggestionsBox.style.display !== 'none') {
                const rect = activeProjectInput.getBoundingClientRect();
                if (rect.width === 0 || rect.top === 0) {
                    projectSuggestionsBox.style.display = 'none';
                    return;
                }
                projectSuggestionsBox.style.top = (rect.bottom + 2) + 'px';
                projectSuggestionsBox.style.left = rect.left + 'px';
                projectSuggestionsBox.style.width = rect.width + 'px';
            }
        }

        // Global Event Delegation for all project inputs
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('project-autocomplete-input')) {
                const input = e.target;
                const query = input.value.trim();
                activeProjectInput = input;

                if (projectSearchTimeout) clearTimeout(projectSearchTimeout);
                if (query.length < 1) {
                    projectSuggestionsBox.style.display = 'none';
                    return;
                }

                projectSearchTimeout = setTimeout(() => {
                    fetchProjectSuggestions(query, input);
                }, 300);
            }
        });

        document.addEventListener('focusin', function (e) {
            if (e.target.classList.contains('project-autocomplete-input')) {
                const input = e.target;
                const query = input.value.trim();
                activeProjectInput = input;
                if (query.length >= 1) {
                    fetchProjectSuggestions(query, input);
                }
            }
        });

        window.addEventListener('resize', updateProjectSuggestionPosition);
        window.addEventListener('scroll', updateProjectSuggestionPosition, true);

        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('project-autocomplete-input') && !projectSuggestionsBox.contains(e.target)) {
                projectSuggestionsBox.style.display = 'none';
            }
        });

        function fetchProjectSuggestions(query, input) {
            fetch(`/api/projects.php?search=${encodeURIComponent(query)}&limit=10`)
                .then(response => response.json())
                .then(data => {
                    projectSuggestionsBox.innerHTML = '';
                    updateProjectSuggestionPosition();

                    if (data.data && data.data.length > 0) {
                        projectSuggestionsBox.style.display = 'block';
                        updateProjectSuggestionPosition();

                        data.data.forEach(project => {
                            const div = document.createElement('div');
                            div.style.padding = '10px 12px';
                            div.style.cursor = 'pointer';
                            div.style.borderBottom = '1px solid #f1f5f9';
                            div.style.color = '#334155';
                            div.style.backgroundColor = '#ffffff';

                            div.onmouseover = () => { div.style.backgroundColor = '#f8fafc'; div.style.color = '#0f172a'; };
                            div.onmouseout = () => { div.style.backgroundColor = '#ffffff'; div.style.color = '#334155'; };

                            const displayName = `[${project.key}] ${project.name}`;
                            const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                            const highlighted = displayName.replace(regex, '<strong style="color:#2563eb">$1</strong>');

                            div.innerHTML = highlighted;

                            div.onclick = (e) => {
                                e.stopPropagation();
                                const oldValue = input.value;
                                input.value = project.name;
                                projectSuggestionsBox.style.display = 'none';
                                
                                // Explicitly trigger update for inline inputs to ensure it saves
                                if (input.classList.contains('project-autocomplete-input') && typeof updateInline === 'function') {
                                    // Match the row ID from the input's context if possible
                                    // The input in the table has updateInline(id, 'project_name', ...) in its onblur
                                    // We'll trigger a 'change' event or call updateInline directly if we can find the parameters
                                    // For simplicity and safety, we'll just trigger the blur logic manually but skip the timeout issue
                                    input.blur(); 
                                }
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
                });
        }
        function syncDebt(id, invoiceName, btn) {
            if (!confirm(`Sync debt #${id} with latest data from Invoice ${invoiceName}?`)) return;

            const originalIcon = btn.innerHTML;
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
                            }, 1500);
                        }
                    }
                })
                .catch(err => {
                    console.error('Update Error:', err);
                    if (el) el.style.backgroundColor = '#fee2e2';
                });
        }

        document.addEventListener("DOMContentLoaded", function () {
            const tableId = "myDebtsTable";
            const table = document.getElementById(tableId);
            if (!table) return;

            const storeKey = "my_debts_col_widths";
            let widths = {};
            try {
                widths = JSON.parse(localStorage.getItem(storeKey)) || {};
            } catch (e) { }

            const cols = table.querySelectorAll('thead th');
            const styleEl = document.createElement('style');
            document.head.appendChild(styleEl);

            const defaultStickyWidths = [40, 80, 80, 100, 140, 220, 220];

            function renderStyles() {
                let css = "";
                let runningLeft = 0;

                cols.forEach((th, index) => {
                    const c                                      olIndex = index + 1;
                    const isSticky = colIndex <= 7;
                    let w = widths[colIndex];

                    if (isSticky) {
                        const actualW = w || defaultStickyWidths[index];
                        css += "#" + tableId + " th:nth-child(" + colIndex + "), #" + tableId + " tr:not(.group-header) td:nth-child(" + colIndex + ") { " +
                            "left: " + runningLeft + "px !important; " +
                            "width: " + actualW + "px !important; " +
                            "min-width: " + actualW + "px !important; " +
                            "max-width: " + actualW + "px !important; " +
                            "}\n";
                        runningLeft += actualW;
                    } else if (w) {
                        css += "#" + tableId + " th:nth-child(" + colIndex + "), #" + tableId + " tr:not(.group-header) td:nth-child(" + colIndex + ") { " +
                            "width: " + w + "px !important; " +
                            "min-width: " + w + "px !important; " +
                            "max-width: " + w + "px !important; " +
                            "}\n";
                    }
                });
                styleEl.innerHTML = css;
            }

            renderStyles();

            cols.forEach((th, index) => {
                const colIndex = index + 1;
                const resizer = document.createElement('div');
                resizer.className = 'col-resizer';
                th.appendChild(resizer);

                let x = 0, w = 0;

                const mouseDownHandler = function (e) {
                    x = e.clientX;
                    w = th.getBoundingClientRect().width;
                    document.addEventListener('mousemove', mouseMoveHandler);
                    document.addEventListener('mouseup', mouseUpHandler);
                    document.body.style.cursor = 'col-resize';
                    document.body.classList.add('resizing');
                    resizer.classList.add('active');
                    e.stopPropagation();
                    e.preventDefault();
                };

                const mouseMoveHandler = function (e) {
                    const dx = e.clientX - x;
                    let newW = w + dx;
                    if (newW < 30) newW = 30;
                    widths[colIndex] = newW;
                    renderStyles();
                };

                const mouseUpHandler = function () {
                    document.removeEventListener('mousemove', mouseMoveHandler);
                    document.removeEventListener('mouseup', mouseUpHandler);
                    document.body.style.cursor = '';
                    document.body.classList.remove('resizing');
                    resizer.classList.remove('active');
                    localStorage.setItem(storeKey, JSON.stringify(widths));
                };

                resizer.addEventListener('mousedown', mouseDownHandler);
            });
        }); </script>
</body>

</html>