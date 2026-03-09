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
    // Migration: add license_trading if not exists
    $col_check_lt = $conn->query("SHOW COLUMNS FROM sale_reports LIKE 'license_trading'");
    if ($col_check_lt->num_rows == 0) {
        $conn->query("ALTER TABLE sale_reports ADD COLUMN license_trading VARCHAR(20) DEFAULT ''");
    }
}

// Ensure confirmations table exists (append-only, all history kept)
$conn->query("CREATE TABLE IF NOT EXISTS sale_report_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quarter VARCHAR(20) NOT NULL,
    confirmed_at DATETIME NOT NULL,
    confirmed_by_name VARCHAR(255),
    type VARCHAR(20) DEFAULT 'confirmed'
)");
// Migrate: add type column if missing
$tc = $conn->query("SHOW COLUMNS FROM sale_report_confirmations LIKE 'type'");
if ($tc && $tc->num_rows === 0)
    $conn->query("ALTER TABLE sale_report_confirmations ADD COLUMN type VARCHAR(20) DEFAULT 'confirmed'");
// Drop old UNIQUE KEY if present (MySQL 5.x compat)
$idx_check = $conn->query("SHOW INDEX FROM sale_report_confirmations WHERE Key_name = 'uq_user_quarter'");
if ($idx_check && $idx_check->num_rows > 0)
    $conn->query("ALTER TABLE sale_report_confirmations DROP INDEX uq_user_quarter");

// Ensure edit audit log table exists
$conn->query("CREATE TABLE IF NOT EXISTS sale_report_edit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    odoo_invoice_id INT NOT NULL,
    quarter VARCHAR(20) NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255),
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    edited_at DATETIME NOT NULL
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
        $stmt_c = $conn->prepare("INSERT INTO sale_report_confirmations (user_id, quarter, confirmed_at, confirmed_by_name, type) VALUES (?,?,?,?,'confirmed')");
        $stmt_c->bind_param("isss", $uid, $quarter_key, $now, $uname);
        $stmt_c->execute();
        echo json_encode(['success' => true, 'confirmed_at' => $now, 'confirmed_by' => $uname]);
    }
    exit();
}

// Handle AJAX reset_draft — insert a 'reset' event (never delete history)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_draft') {
    header('Content-Type: application/json');
    $quarter_key = preg_replace('/[^A-Za-z0-9_]/', '', $_POST['quarter'] ?? '');
    $uid = (int) $_SESSION['user_id'];
    $uname = $_SESSION['full_name'] ?? 'Unknown';
    $now = date('Y-m-d H:i:s');
    $stmt_d = $conn->prepare("INSERT INTO sale_report_confirmations (user_id, quarter, confirmed_at, confirmed_by_name, type) VALUES (?,?,?,?,'reset')");
    $stmt_d->bind_param("isss", $uid, $quarter_key, $now, $uname);
    $stmt_d->execute();
    echo json_encode(['success' => true]);
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

// Handle AJAX confirm_commission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_commission') {
    header('Content-Type: application/json');
    $uid = (int) $_SESSION['user_id'];
    $uname = $_SESSION['full_name'] ?? 'Unknown';
    $quarter = $_POST['quarter'] ?? '';
    if (empty($quarter)) {
        echo json_encode(['success' => false, 'error' => 'Missing quarter']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO sale_report_confirmations (user_id, quarter, confirmed_at, confirmed_by_name, type) VALUES (?, ?, ?, ?, 'commission_confirmed')");
    $stmt->bind_param("isss", $uid, $quarter, $now, $uname);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'time' => date('H:i d/m/Y', strtotime($now)), 'by' => $uname]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit();
}

// Handle AJAX update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_inline') {
    header('Content-Type: application/json');
    $odoo_id = intval($_POST['odoo_invoice_id']);
    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['field']);
    $val = $_POST['value'];
    $quarter_key = preg_replace('/[^A-Za-z0-9_]/', '', $_POST['quarter'] ?? '');
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $uname = $_SESSION['full_name'] ?? 'Unknown';

    // Fetch current values for audit and auto-calc
    $res_current = $conn->query("SELECT `$field` as old_val, client_type, com_lead_source, bonus_license_trading FROM sale_reports WHERE odoo_invoice_id = $odoo_id LIMIT 1");
    $db_row = $res_current ? $res_current->fetch_assoc() : null;
    $old_val = $db_row['old_val'] ?? '';

    $current_client_type = ($field === 'client_type') ? $val : ($db_row['client_type'] ?? '');
    $current_lead_source = ($field === 'com_lead_source') ? $val : ($db_row['com_lead_source'] ?? 'No');
    $current_bonus_license_trading = ($field === 'bonus_license_trading') ? $val : ($db_row['bonus_license_trading'] ?? 'No');

    // Auto rules for com_1 (Base + Lead Source Bonus)
    if ($field === 'client_type' || $field === 'com_lead_source') {
        $base = 0;
        if ($current_client_type === 'Old client')
            $base = 0.5;
        elseif ($current_client_type === 'New client')
            $base = 1.0;

        $adder = ($current_lead_source === 'Yes') ? 0.3 : 0;

        $com1_numeric = $base + $adder;
        $com1_val = ($com1_numeric > 0) ? $com1_numeric . '%' : '';

        // Perform the update
        $stmt = $conn->prepare("INSERT INTO sale_reports (odoo_invoice_id, `$field`, com_1) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `$field`=?, com_1=?");
        $stmt->bind_param("issss", $odoo_id, $val, $com1_val, $val, $com1_val);
        $stmt->execute();

        // Log edit
        $now = date('Y-m-d H:i:s');
        $stmt_log = $conn->prepare("INSERT INTO sale_report_edit_log (odoo_invoice_id, quarter, user_id, user_name, field_name, old_value, new_value, edited_at) VALUES (?,?,?,?,?,?,?,?)");
        $stmt_log->bind_param("isssssss", $odoo_id, $quarter_key, $uid, $uname, $field, $old_val, $val, $now);
        $stmt_log->execute();

        echo json_encode(['success' => true, 'com_1' => $com1_val]);
    } elseif ($field === 'bonus_license_trading') {
        $lic_val = ($val === 'Yes') ? '10% NP' : '';
        $stmt = $conn->prepare("INSERT INTO sale_reports (odoo_invoice_id, bonus_license_trading, license_trading) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bonus_license_trading=?, license_trading=?");
        $stmt->bind_param("issss", $odoo_id, $val, $lic_val, $val, $lic_val);
        $stmt->execute();

        // Log edit
        $now = date('Y-m-d H:i:s');
        $stmt_log = $conn->prepare("INSERT INTO sale_report_edit_log (odoo_invoice_id, quarter, user_id, user_name, field_name, old_value, new_value, edited_at) VALUES (?,?,?,?,?,?,?,?)");
        $stmt_log->bind_param("isssssss", $odoo_id, $quarter_key, $uid, $uname, $field, $old_val, $val, $now);
        $stmt_log->execute();

        echo json_encode(['success' => true, 'license_trading' => $lic_val]);
    } else {
        $allowed = ['contract_type', 'presales', 'client_type', 'profit_pakd', 'net_profit', 'com_lead_source', 'bonus_license_trading', 'license_trading', 'com_1', 'com_2', 'note'];
        if (in_array($field, $allowed)) {
            $stmt = $conn->prepare("INSERT INTO sale_reports (odoo_invoice_id, `$field`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `$field`=?");
            $stmt->bind_param("iss", $odoo_id, $val, $val);
            $stmt->execute();
            // Log edit
            $now = date('Y-m-d H:i:s');
            $stmt_log = $conn->prepare("INSERT INTO sale_report_edit_log (odoo_invoice_id, quarter, user_id, user_name, field_name, old_value, new_value, edited_at) VALUES (?,?,?,?,?,?,?,?)");
            $stmt_log->bind_param("isssssss", $odoo_id, $quarter_key, $uid, $uname, $field, $old_val, $val, $now);
            $stmt_log->execute();
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
$past_paid_invoices = []; // For invoices from past quarters paid in this quarter

foreach ($invoices as &$inv) {
    $inv_date_str = $inv['invoice_date'] ?: $inv['date'];

    // Filter by quarter date
    if (!$inv_date_str || $inv_date_str < $start_date || $inv_date_str > $end_date) {

        // Check if modifying past invoices that got paid in this quarter
        if ($inv_date_str && $inv_date_str < $start_date) {
            $p_state = $inv['payment_state'] ?? '';
            if (in_array($p_state, ['paid', 'in_payment', 'partial'])) {
                $pay_widget = $inv['invoice_payments_widget'] ?? null;
                $paid_in_this_quarter = false;
                if ($pay_widget && $pay_widget !== 'false') {
                    $pw = is_array($pay_widget) ? $pay_widget : json_decode($pay_widget, true);
                    if (is_array($pw)) {
                        foreach ($pw['content'] ?? [] as $p) {
                            $pdate = $p['date'] ?? null;
                            // Check if payment falls in current quarter
                            if ($pdate && $pdate >= $start_date && $pdate <= $end_date && empty($p['is_exchange'])) {
                                $paid_in_this_quarter = true;
                                break;
                            }
                        }
                    }
                }
                if ($paid_in_this_quarter) {
                    $amountVnd = isset($inv['amount_total_signed']) ? (float) $inv['amount_total_signed'] : 0;
                    if ($amountVnd == 0 && $inv['amount_total'] > 0) {
                        $currencyCode = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
                        $rateSource = $odoo->getRate($currencyCode, $inv_date_str) ?: 1.0;
                        $rateVnd = $odoo->getRate('VND', $inv_date_str) ?: 1.0;
                        $amountVnd = $inv['amount_total'] * ($rateVnd / $rateSource);
                    }
                    $inv['calc_amount_vnd'] = $amountVnd;
                    $inv['is_excluded'] = (int) ($local_data[$inv['id']]['is_excluded'] ?? 0);
                    $inv['is_past_quarter'] = true;
                    $past_paid_invoices[] = $inv;
                }
            }
        }
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
    $eff_level_id = null;
    $v_q = isset($q) ? $q : ceil(date('n') / 3);
    $v_y = isset($y) ? $y : date('Y');

    try {
        $stmt_hist = $conn->prepare("
            SELECT sale_level_id FROM user_sale_level_history 
            WHERE user_id = ? AND (apply_year < ? OR (apply_year = ? AND apply_quarter <= ?))
            ORDER BY apply_year DESC, apply_quarter DESC LIMIT 1
        ");
        if ($stmt_hist) {
            $stmt_hist->bind_param("iiii", $u_id, $v_y, $v_y, $v_q);
            $stmt_hist->execute();
            $hist_res = $stmt_hist->get_result();
            if ($row = $hist_res->fetch_assoc()) {
                $eff_level_id = $row['sale_level_id'];
            }
        }
    } catch (Exception $e) {
        // Ignored, table might not exist yet or no db error catching
    }

    if ($eff_level_id) {
        $stmt_kpi = $conn->prepare("
            SELECT u.full_name, sl.level_name, sl.position_type, sl.color_badge,
                   sl.kpi_quarter_vnd, sl.kpi_yearly_vnd, sl.kpi_quarter_usd, sl.kpi_yearly_usd
            FROM users u
            LEFT JOIN sale_levels sl ON sl.id = ?
            WHERE u.id = ?
        ");
        $stmt_kpi->bind_param("ii", $eff_level_id, $u_id);
    } else {
        $stmt_kpi = $conn->prepare("
            SELECT u.full_name, sl.level_name, sl.position_type, sl.color_badge,
                   sl.kpi_quarter_vnd, sl.kpi_yearly_vnd, sl.kpi_quarter_usd, sl.kpi_yearly_usd
            FROM users u
            LEFT JOIN sale_levels sl ON u.sale_level_id = sl.id
            WHERE u.id = ?
        ");
        $stmt_kpi->bind_param("i", $u_id);
    }

    $stmt_kpi->execute();
    $kpi_row = $stmt_kpi->get_result()->fetch_assoc();
    if ($kpi_row && $kpi_row['level_name']) {
        $kpi_data = $kpi_row;
    }
}

// Fetch ALL confirmations for this quarter (history), newest first
$confirmations = [];
$confirmation = null; // latest one
$stmt_conf = $conn->prepare("SELECT * FROM sale_report_confirmations WHERE user_id=? AND quarter=? ORDER BY confirmed_at DESC");
$stmt_conf->bind_param("is", $u_id, $active_tab);
$stmt_conf->execute();
$conf_res = $stmt_conf->get_result();
while ($conf_row = $conf_res->fetch_assoc()) {
    $confirmations[] = $conf_row;
}
if (!empty($confirmations))
    $confirmation = $confirmations[0]; // latest

// Fetch specifically Commission Confirmations for history display
$comm_confirmations = [];
$res_cc = $conn->query("SELECT * FROM sale_report_confirmations WHERE user_id=$u_id AND quarter='$active_tab' AND type='commission_confirmed' ORDER BY confirmed_at DESC");
if ($res_cc) {
    while ($r = $res_cc->fetch_assoc())
        $comm_confirmations[] = $r;
}

// Find the latest KPI status (either 'confirmed' or 'reset')
$latest_kpi_event = null;
foreach ($confirmations as $c) {
    if ($c['type'] === 'confirmed' || $c['type'] === 'reset') {
        $latest_kpi_event = $c;
        break;
    }
}

// Find the latest Commission status
$latest_comm_event = null;
foreach ($confirmations as $c) {
    if ($c['type'] === 'commission_confirmed') {
        $latest_comm_event = $c;
        break;
    }
}

// Global state: locked if latest confirmation event is not a reset
$latest_type = !empty($confirmation) ? ($confirmation['type'] ?? 'confirmed') : null;
$is_locked = ($latest_type === 'confirmed' || $latest_type === 'commission_confirmed');
$is_confirmed = ($latest_type === 'confirmed' || $latest_type === 'commission_confirmed');

// Specific state flags
$kpi_is_confirmed = ($latest_kpi_event && $latest_kpi_event['type'] === 'confirmed');
$comm_is_confirmed = ($latest_type === 'commission_confirmed');

// Fetch edit log for this quarter (newest first)
$edit_log = [];
$stmt_elog = $conn->prepare("SELECT * FROM sale_report_edit_log WHERE user_id=? AND quarter=? ORDER BY edited_at DESC LIMIT 200");
$stmt_elog->bind_param("is", $u_id, $active_tab);
$stmt_elog->execute();
$elog_res = $stmt_elog->get_result();
while ($elog_row = $elog_res->fetch_assoc())
    $edit_log[] = $elog_row;

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
            overflow-x: hidden;
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
            background: #fff8f8 !important;
            border: 1.5px dashed #f87171 !important;
            position: relative;
            animation: fade-missing 2s ease-in-out 2;
        }

        @keyframes fade-missing {

            0%,
            100% {
                border-color: #f87171;
                background: #fff8f8;
            }

            50% {
                border-color: #fca5a5;
                background: #fff;
            }
        }

        .editable-cell.cell-locked {
            cursor: not-allowed !important;
            opacity: 0.75;
            position: relative;
        }

        .editable-cell.cell-locked::after {
            content: '🔒';
            position: absolute;
            top: 3px;
            right: 4px;
            font-size: 9px;
            opacity: 0.4;
            pointer-events: none;
        }

        .editable-cell.cell-locked:hover {
            background: inherit !important;
        }

        .draft-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            background: #fff;
            color: #dc2626;
            border: 1.5px solid #fca5a5;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .draft-btn:hover {
            background: #fef2f2;
            border-color: #f87171;
        }

        .locked-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            color: #92400e;
            font-weight: 500;
            margin-bottom: 0.5rem;
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

                <div
                    style="width: 100%; overflow-x: auto; flex-shrink: 0; margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">STT</th>
                                <th style="width: 130px;">Invoice #</th>
                                <th style="width: 100px; text-align: center;">Trạng thái TT</th>
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
                                <th style="width: 120px;">Net profit(USD)</th>
                                <!-- Target + %KPI skipped -->
                                <th style="width: 140px;">% Com (Lead source)</th>
                                <th style="width: 160px;">% Bonus License/trading</th>
                                <th style="width: 80px;">% Com 1</th>
                                <th style="width: 100px;">% Com 2</th>
                                <th style="width: 120px;">License/trading</th>
                                <th style="min-width: 200px;">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($grouped_invoices)): ?>
                                <tr>
                                    <td colspan="20" style="text-align:center; padding: 2rem;">No invoices found.</td>
                                </tr>
                            <?php else: ?>
                                <?php $stt = 1;
                                foreach ($grouped_invoices as $month_key => $month_invoices):
                                    $display_month = $month_key !== 'Unknown' ? date('m / Y', strtotime($month_key . '-01')) : 'Unknown';
                                    $month_subtotal = 0;
                                    ?>
                                    <tr class="month-group-header">
                                        <td colspan="20">THÁNG <?= $display_month ?></td>
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
                                            <!-- Invoice # (Odoo DB ID) -->
                                            <td
                                                style="font-family: 'Inconsolata', monospace; font-size: 12px; color: #64748b; white-space: nowrap; font-weight: 600; text-align: center;">
                                                #<?= $odoo_id ?>
                                            </td>
                                            <!-- Trạng thái TT -->
                                            <td style="text-align: center;">
                                                <?php
                                                $p_state = $inv['payment_state'] ?? '';
                                                if ($p_state === 'paid') {
                                                    echo '<span style="background:#d1fae5; color:#065f46; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase;">Paid</span>';
                                                } elseif ($p_state === 'in_payment') {
                                                    echo '<span style="background:#dbeafe; color:#1e40af; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase;">In Payment</span>';
                                                } else {
                                                    echo '<span style="background:#f1f5f9; color:#64748b; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase;">' . ($p_state ?: 'Unpaid') . '</span>';
                                                }
                                                ?>
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
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>"
                                                data-required-field="contract_type" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'contract_type', 'select', ['Service', 'Trading', 'Dedicated', 'License'])\"" : 'title="Đang bị khoá — Reset to Draft để sửa"' ?>>
                                                <?= htmlspecialchars($l['contract_type'] ?? '') ?>
                                            </td>

                                            <!-- Presales -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>"
                                                data-required-field="presales" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'presales', 'select', ['No presales', '0%', '0.25%', '0.5%'])\"" : 'title="Đang bị khoá — Reset to Draft để sửa"' ?>>
                                                <?= htmlspecialchars($l['presales'] ?? '') ?>
                                            </td>

                                            <!-- Loại khách hàng -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>"
                                                data-required-field="client_type" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'client_type', 'select', ['New client', 'Old client'])\"" : 'title="Đang bị khoá — Reset to Draft để sửa"' ?>>
                                                <?= htmlspecialchars($l['client_type'] ?? '') ?>
                                            </td>

                                            <!-- Giá trị -->
                                            <td style="text-align:right; font-family: Inconsolata, monospace;">
                                                <?= formatMoney($inv['amount_total'], is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND') ?>
                                            </td>

                                            <!-- %Profit trong PAKD -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'profit_pakd', 'text')\"" : 'title="Đang bị khoá"' ?>>
                                                <?= htmlspecialchars($l['profit_pakd'] ?? '') ?>
                                            </td>

                                            <!-- Net profit -->
                                            <?php
                                            $is_bonus_yes = ($l['bonus_license_trading'] ?? 'No') === 'Yes';
                                            $net_profit_val = trim($l['net_profit'] ?? '');
                                            $net_profit_style = ($is_bonus_yes && $net_profit_val === '') ? 'background: #fee2e2; border: 1.5px solid #ef4444;' : '';
                                            ?>
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>"
                                                id="net_profit_<?= $odoo_id ?>" style="<?= $net_profit_style ?>" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'net_profit', 'text')\"" : 'title="Đang bị khoá"' ?>>
                                                <?= htmlspecialchars($net_profit_val) ?>
                                            </td>

                                            <!-- % Com (Lead source) -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>"
                                                data-required-field="com_lead_source" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'com_lead_source', 'select', ['Yes', 'No'])\"" : 'title="Đang bị khoá — Reset to Draft để sửa"' ?>>
                                                <?= htmlspecialchars($l['com_lead_source'] ?? 'No') ?>
                                            </td>

                                            <!-- % Bonus License/trading -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>"
                                                data-required-field="bonus_license_trading" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'bonus_license_trading', 'select', ['Yes', 'No'])\"" : 'title="Đang bị khoá — Reset to Draft để sửa"' ?>>
                                                <?= htmlspecialchars($l['bonus_license_trading'] ?? 'No') ?>
                                            </td>

                                            <!-- % Com 1 -->
                                            <td id="com_1_<?= $odoo_id ?>"
                                                style="color: #c5221f; font-weight:600; background: #fdfaf6;">
                                                <?= htmlspecialchars($l['com_1'] ?? '') ?>
                                            </td>

                                            <!-- % Com 2 -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'com_2', 'select', ['0.5%', '1%', '1.5%', '2%', '2.5%', '3%'])\"" : 'title="Đang bị khoá"' ?>>
                                                <?= htmlspecialchars($l['com_2'] ?? '') ?>
                                            </td>

                                            <!-- License/trading -->
                                            <td id="lic_trd_<?= $odoo_id ?>"
                                                style="font-weight: 600; color: #1e40af; background: #f0f7ff; text-align: center;">
                                                <?= htmlspecialchars($l['license_trading'] ?? '') ?>
                                            </td>

                                            <!-- Note -->
                                            <td class="editable-cell <?= $is_locked ? 'cell-locked' : '' ?>" <?= !$is_locked ? "onclick=\"makeEditable(this, $odoo_id, 'note', 'text')\"" : 'title="Đang bị khoá"' ?>>
                                                <?= htmlspecialchars($l['note'] ?? '') ?>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="month-total-row">
                                        <td colspan="11" style="text-align: right;">Cộng tháng <?= $display_month ?>:</td>
                                        <td style="text-align: right;"><?= formatMoney($month_subtotal, 'VND') ?></td>
                                        <td colspan="8"></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

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
                            <div style="flex:1;">
                                <?php if ($is_confirmed): ?>
                                    <!-- STATE: Confirmed / Locked -->
                                    <div class="locked-banner">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                        </svg>
                                        Bảng đang bị khoá — chỉ xem. Nhấn <strong style="margin:0 3px;">Reset to Draft</strong>
                                        để cho phép chỉnh sửa.
                                    </div>
                                    <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                                        <div class="confirmed-badge">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2.5">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                                <polyline points="22 4 12 14.01 9 11.01" />
                                            </svg>
                                            <?php
                                            $kpi_date = ($latest_kpi_event) ? date('H:i d/m/Y', strtotime($latest_kpi_event['confirmed_at'])) : 'Unknown';
                                            ?>
                                            Đã xác nhận KPI — <?= $kpi_date ?>
                                        </div>
                                        <?php if (!$comm_is_confirmed): ?>
                                            <button class="confirm-btn" onclick="confirmKpi()"
                                                style="background:#64748b;box-shadow:none;font-size:12px;padding:7px 16px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="23 4 23 11 16 11" />
                                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 11" />
                                                </svg>
                                                Xác nhận lại KPI
                                            </button>
                                        <?php else: ?>
                                            <div class="confirmed-badge"
                                                style="background:#dcfce7; color:#166534; border-color:#86efac;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                                                </svg>
                                                ĐÃ CHỐT COMMISSION —
                                                <?= date('H:i d/m/Y', strtotime($latest_comm_event['confirmed_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <button class="draft-btn" onclick="resetToDraft()">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18.36 6.64A9 9 0 1 1 5.64 5.64" />
                                                <polyline points="15 2 21 2 21 8" />
                                                <line x1="21" y1="2" x2="14" y2="9" />
                                            </svg>
                                            Reset to Draft
                                        </button>
                                    </div>

                                <?php elseif ($latest_type === 'reset'): ?>
                                    <!-- STATE: Draft (after reset) -->
                                    <div class="locked-banner" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                            <path d="M7 11V7a5 5 0 0 1 9.9-1" />
                                        </svg>
                                        Đang ở trạng thái <strong style="margin:0 3px;">Draft</strong> — bảng đã được mở khoá.
                                        Xác nhận lại khi hoàn tất.
                                    </div>
                                    <button class="confirm-btn" onclick="confirmKpi()" id="confirmKpiBtn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                            <polyline points="22 4 12 14.01 9 11.01" />
                                        </svg>
                                        Xác nhận KPI Quý
                                    </button>

                                <?php else: ?>
                                    <!-- STATE: No history yet -->
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

                    <!-- ──── Audit Log ──── -->
                    <?php
                    // Merge confirmations + edit_log into one timeline sorted by time DESC
                    $audit_events = [];
                    foreach ($confirmations as $c) {
                        if (($c['type'] ?? 'confirmed') === 'commission_confirmed')
                            continue;
                        $audit_events[] = [
                            'time' => $c['confirmed_at'],
                            'type' => $c['type'] ?? 'confirmed',
                            'by' => $c['confirmed_by_name'],
                            'data' => $c,
                        ];
                    }
                    foreach ($edit_log as $e) {
                        $audit_events[] = [
                            'time' => $e['edited_at'],
                            'type' => 'edit',
                            'by' => $e['user_name'],
                            'data' => $e,
                        ];
                    }
                    usort($audit_events, fn($a, $b) => strcmp($b['time'], $a['time']));
                    ?>
                    <?php if (!empty($audit_events)): ?>
                        <div style="border-top: 1px dashed #e2e8f0; margin-top: 1.5rem; padding-top: 1.25rem;">
                            <div
                                style="font-size: 11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.75rem; display:flex; align-items:center; gap:6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                Lịch sử hoạt động (<?= count($audit_events) ?> sự kiện)
                            </div>
                            <div style="max-height: 220px; overflow-y: auto; display:flex; flex-direction:column; gap:5px;">
                                <?php foreach ($audit_events as $ev):
                                    $t = $ev['type'];
                                    if ($t === 'confirmed') {
                                        $dot_bg = '#d1fae5';
                                        $dot_border = '#6ee7b7';
                                        $dot_color = '#065f46';
                                        $dot_icon = '✓';
                                        $label = '<span style="background:#d1fae5;color:#065f46;padding:1px 8px;border-radius:10px;font-weight:600;font-size:11px;">✅ Đã xác nhận KPI</span>';
                                    } elseif ($t === 'reset') {
                                        $dot_bg = '#fef3c7';
                                        $dot_border = '#fde68a';
                                        $dot_color = '#92400e';
                                        $dot_icon = '↩';
                                        $label = '<span style="background:#fef3c7;color:#92400e;padding:1px 8px;border-radius:10px;font-weight:600;font-size:11px;">🔓 Reset to Draft</span>';
                                    } elseif ($t === 'edit') {
                                        $dot_bg = '#eff6ff';
                                        $dot_border = '#bfdbfe';
                                        $dot_color = '#1d4ed8';
                                        $dot_icon = '✎';
                                        $d = $ev['data'];
                                        $field_labels = [
                                            'contract_type' => 'Loại HĐ',
                                            'presales' => 'Presales',
                                            'client_type' => 'Loại KH',
                                            'profit_pakd' => '%Profit PAKD',
                                            'net_profit' => 'Net profit (USD)',
                                            'com_lead_source' => '% Com Lead',
                                            'bonus_license_trading' => '% Bonus',
                                            'com_1' => '% Com 1',
                                            'com_2' => '% Com 2',
                                            'note' => 'Note'
                                        ];
                                        $fn = $field_labels[$d['field_name']] ?? $d['field_name'];
                                        $old = htmlspecialchars($d['old_value'] ?? '');
                                        $new = htmlspecialchars($d['new_value'] ?? '');
                                        $inv = $d['odoo_invoice_id'] ?? 'N/A';
                                        $label = "<span style='font-size:11px;'>Sửa <strong>$fn</strong> (Invoice #$inv): "
                                            . ($old !== '' ? "<span style='color:#94a3b8;text-decoration:line-through;'>$old</span> → " : '')
                                            . "<strong style='color:#1d4ed8;'>$new</strong></span>";
                                    } else {
                                        $dot_bg = '#f1f5f9';
                                        $dot_border = '#e2e8f0';
                                        $dot_color = '#475569';
                                        $dot_icon = '•';
                                        $label = '<span style="font-size:11px;">Hoạt động: ' . htmlspecialchars($t) . '</span>';
                                    }
                                    ?>
                                    <div style="display:flex; align-items:baseline; gap:8px; font-size:12px; color:#475569;">
                                        <span
                                            style="width:20px;height:20px;border-radius:50%;background:<?= $dot_bg ?>;border:1.5px solid <?= $dot_border ?>;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px;color:<?= $dot_color ?>;font-weight:700;"><?= $dot_icon ?></span>
                                        <span style="flex:1;"><?= $label ?></span>
                                        <span
                                            style="white-space:nowrap;color:#94a3b8;font-size:11px;"><?= date('H:i:s • d/m/Y', strtotime($ev['time'])) ?>
                                            — <?= htmlspecialchars($ev['by']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- ══════════════════════════════════════════
                     BÁO CÁO THANH TOÁN — Paid Invoices
                ═══════════════════════════════════════════ -->
                <?php if ($is_confirmed): ?>
                    <?php
                    $paid_invoices_grouped = [];
                    $paid_total_vnd = 0;
                    $all_paid_candidates = array_merge($filtered_invoices, $past_paid_invoices);
                    foreach ($all_paid_candidates as $inv) {
                        $p_state = $inv['payment_state'] ?? '';
                        // Include partially paid and fully paid invoices
                        if (!in_array($p_state, ['paid', 'in_payment', 'partial']))
                            continue;
                        if ((int) ($inv['is_excluded'] ?? 0) === 1)
                            continue;

                        // Parse payment widget early
                        $pay_widget = $inv['invoice_payments_widget'] ?? null;
                        $giaingan_origin = 0;
                        $ngay_tien_ve_arr = [];
                        if ($pay_widget && $pay_widget !== 'false') {
                            $pw = is_array($pay_widget) ? $pay_widget : json_decode($pay_widget, true);
                            if (is_array($pw)) {
                                foreach ($pw['content'] ?? [] as $p) {
                                    if (!empty($p['is_exchange']))
                                        continue; // Không cộng chênh lệch tỷ giá (khác currency)
                                    $giaingan_origin += (float) ($p['amount'] ?? 0);
                                    if (!empty($p['date']))
                                        $ngay_tien_ve_arr[] = $p['date'];
                                }
                            }
                        }
                        if ($giaingan_origin <= 0)
                            continue; // Skip if nothing was actually disbursed yet
                
                        // Convert disbursed amount to VND for totals
                        $amt_total = (float) ($inv['amount_total'] ?? 0);
                        $calc_vnd = (float) ($inv['calc_amount_vnd'] ?? 0);
                        $ratio = $amt_total > 0 ? ($calc_vnd / $amt_total) : 1;
                        $giaingan_vnd_converted = $giaingan_origin * $ratio;

                        // Save parsed values for HTML rendering
                        $inv['parsed_giaingan_origin'] = $giaingan_origin;
                        $inv['parsed_giaingan_vnd'] = $giaingan_vnd_converted;
                        $inv['parsed_ngay_tien_ve'] = !empty($ngay_tien_ve_arr) ? date('d/m/Y', strtotime(max($ngay_tien_ve_arr))) : '';

                        $inv_date_str = $inv['invoice_date'] ?: $inv['date'];
                        if (!empty($inv['is_past_quarter'])) {
                            $month_key = 'ZZZ_PAST';
                        } else {
                            $month_key = $inv_date_str ? date('Y-m', strtotime($inv_date_str)) : 'Unknown';
                        }
                        $paid_invoices_grouped[$month_key][] = $inv;

                        // Add actual disbursed money to total, NOT the full invoice value
                        $paid_total_vnd += $giaingan_vnd_converted;
                    }
                    ksort($paid_invoices_grouped);
                    $odoo_url = $odoo->getUrl();
                    ?>
                    <div class="kpi-report" style="margin-top: 2rem;">
                        <div class="kpi-report-header">
                            <div class="kpi-report-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                    fill="none" stroke="#10b981" stroke-width="2.5">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                </svg>
                                Báo cáo Thanh toán (Đã thu)
                            </div>
                            <span class="kpi-quarter-label">📅 <?= htmlspecialchars($quarter_label) ?> &nbsp;·&nbsp;
                                <strong><?= array_sum(array_map('count', $paid_invoices_grouped)) ?></strong> hóa đơn
                                &nbsp;·&nbsp;
                                Tổng: <strong><?= number_format($paid_total_vnd / 1e9, 3) ?>B VND</strong>
                            </span>
                        </div>

                        <?php if (empty($paid_invoices_grouped)): ?>
                            <div class="kpi-no-level">✅ Chưa có hóa đơn nào được thanh toán (paid) trong quý này.</div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="report-table" style="margin-top: 1rem;">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;text-align:center;">STT</th>
                                            <th style="width:100px;">Invoice #</th>
                                            <th style="width:50px;text-align:center;">Loại trừ</th>
                                            <th style="width:150px;">Tên khách hàng</th>
                                            <th style="width:150px;">Tên Dự án</th>
                                            <th style="width:110px;">Mã dự án</th>
                                            <th style="width:95px;">Ngày ký HĐ</th>
                                            <th style="width:110px;">Loại HĐ</th>
                                            <th style="width:90px;">Presales</th>
                                            <th style="width:110px;">Loại KH</th>
                                            <th style="width:145px;text-align:right;">Giá trị HĐ/HD</th>
                                            <th style="width:120px;">%Profit PAKD</th>
                                            <th style="width:110px;">Net profit (USD)</th>
                                            <th style="width:140px;text-align:right;">Giá trị xuất VAT</th>
                                            <th style="width:140px;text-align:right;">Giá trị giải ngân</th>
                                            <th style="width:140px;text-align:right;">Giá trị giải ngân (USD)</th>
                                            <th style="width:80px;text-align:center;">Link Odoo</th>
                                            <th style="width:105px;">Ngày xuất VAT</th>
                                            <th style="width:115px;">Ngày tiền về</th>
                                            <th style="width:130px;">% Com Lead</th>
                                            <th style="width:150px;">% Bonus Lic/Trd</th>
                                            <th style="width:75px;">% Com 1</th>
                                            <th style="width: 90px;">% Com 2</th>
                                            <th style="width: 100px;">License/trading</th>
                                            <th style="width: 130px; text-align:right;">Commission (USD)</th>
                                            <th style="width:130px;text-align:right;">Com giữ lại (USD)</th>
                                            <th style="min-width:180px;">Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $pstt = 1;
                                        $quarter_total_giaingan_usd = 0;
                                        $quarter_total_comm1_usd = 0;
                                        $quarter_total_comm2_usd = 0;
                                        foreach ($paid_invoices_grouped as $month_key => $month_invs):
                                            if ($month_key === 'ZZZ_PAST') {
                                                $display_group = 'HÓA ĐƠN QUÝ TRƯỚC ĐƯỢC TT';
                                            } else {
                                                $display_group = 'THÁNG ' . ($month_key !== 'Unknown' ? date('m / Y', strtotime($month_key . '-01')) : 'Unknown');
                                            }
                                            $month_sub = 0;
                                            $month_giaingan_usd = 0;
                                            $month_comm1_usd = 0;
                                            $month_comm2_usd = 0;
                                            ?>
                                            <tr class="month-group-header">
                                                <td colspan="27"><?= $display_group ?></td>
                                            </tr>
                                            <?php foreach ($month_invs as $inv):
                                                $oid = $inv['id'];
                                                $l = $local_data[$oid] ?? [];
                                                $inv_date_str = $inv['invoice_date'] ?: $inv['date'];
                                                $month_str = $inv_date_str ? date('d/m/Y', strtotime($inv_date_str)) : '';

                                                // Add actual disbursed amount to month total
                                                $giaingan_origin = $inv['parsed_giaingan_origin'] ?? 0;
                                                $giaingan_vnd_converted = $inv['parsed_giaingan_vnd'] ?? 0;
                                                $ngay_tien_ve = $inv['parsed_ngay_tien_ve'] ?? '';
                                                $month_sub += $giaingan_vnd_converted;

                                                $com1_p = (float) str_replace(['%', ','], '', $l['com_1'] ?? '0');
                                                $com2_p = (float) str_replace(['%', ','], '', $l['com_2'] ?? '0');

                                                $currency_code = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';

                                                $rateSource = $odoo->getRate($currency_code, $inv_date_str) ?: 1.0;
                                                $rateUsd = $odoo->getRate('USD', $inv_date_str) ?: 1.0;
                                                $ratioUsd = $rateSource > 0 ? ($rateUsd / $rateSource) : 1;

                                                // Convert disbursed amount to USD FIRST before multiplying by commission
                                                $giaingan_usd = $giaingan_origin * $ratioUsd;

                                                // Bonus calculation: 10% of Net profit if Bonus is Yes ($100 for $1000)
                                                $is_bonus_yes = ($l['bonus_license_trading'] ?? 'No') === 'Yes';
                                                $net_profit_f = (float) str_replace(['$', ','], '', $l['net_profit'] ?? '0');
                                                $bonus_extra = ($is_bonus_yes && $net_profit_f > 0) ? ($net_profit_f * 0.1) : 0;

                                                $comm1_val_usd = ($giaingan_usd * ($com1_p / 100)) + $bonus_extra;
                                                $comm2_val_usd = $giaingan_usd * ($com2_p / 100);

                                                $month_giaingan_usd += $giaingan_usd;
                                                $month_comm1_usd += $comm1_val_usd;
                                                $month_comm2_usd += $comm2_val_usd;

                                                $quarter_total_giaingan_usd += $giaingan_usd;
                                                $quarter_total_comm1_usd += $comm1_val_usd;
                                                $quarter_total_comm2_usd += $comm2_val_usd;

                                                $vat_amount = (float) ($inv['amount_total'] ?? 0);
                                                $odoo_link = $odoo_url . '/web#id=' . $oid . '&model=account.move&view_type=form';
                                                ?>
                                                <tr class="invoice-row" data-invoice-id="<?= $oid ?>">
                                                    <td style="text-align:center;"><?= $pstt++ ?></td>
                                                    <td
                                                        style="font-family:monospace;font-size:12px;color:#64748b;text-align:center;font-weight:600;">
                                                        #<?= $oid ?></td>
                                                    <td style="text-align: center;">
                                                        <button class="exclude-btn" onclick="toggleExclude(this, <?= $oid ?>)"
                                                            title="Loại trừ (Remove) invoice này khỏi báo cáo">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                fill="currentColor">
                                                                <path
                                                                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                    <td><?= htmlspecialchars(is_array($inv['partner_id']) ? $inv['partner_id'][1] : '') ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($inv['ref'] ?: $inv['name']) ?></td>
                                                    <td><?= htmlspecialchars($inv['x_studio_project_code'] ?? '') ?></td>
                                                    <td><?= $month_str ?></td>
                                                    <td><?= htmlspecialchars($l['contract_type'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($l['presales'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($l['client_type'] ?? '') ?></td>
                                                    <td style="text-align:right;font-family:monospace;">
                                                        <?= formatMoney($vat_amount, $currency_code) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($l['profit_pakd'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($l['net_profit'] ?? '') ?></td>
                                                    <td style="text-align:right;font-family:monospace;color:#0f766e;font-weight:600;">
                                                        <?= formatMoney($vat_amount, $currency_code) ?>
                                                    </td>
                                                    <td style="text-align:right;font-family:monospace;color:#1d4ed8;font-weight:600;">
                                                        <?= $giaingan_origin > 0 ? formatMoney($giaingan_origin, $currency_code) : '<span style="color:#94a3b8">—</span>' ?>
                                                    </td>
                                                    <td style="text-align:right;font-family:monospace;color:#059669;font-weight:600;">
                                                        <?= $giaingan_usd > 0 ? formatMoney($giaingan_usd, 'USD') : '<span style="color:#94a3b8">—</span>' ?>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <a href="<?= htmlspecialchars($odoo_link) ?>" target="_blank"
                                                            title="Mở trong Odoo"
                                                            style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:#eff6ff;border:1.5px solid #bfdbfe;color:#2563eb;text-decoration:none;transition:all .2s;"
                                                            onmouseover="this.style.background='#2563eb';this.style.color='#fff'"
                                                            onmouseout="this.style.background='#eff6ff';this.style.color='#2563eb'">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13"
                                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                stroke-width="2.5">
                                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                                                <polyline points="15 3 21 3 21 9" />
                                                                <line x1="10" y1="14" x2="21" y2="3" />
                                                            </svg>
                                                        </a>
                                                    </td>
                                                    <td><?= $month_str ?></td>
                                                    <td style="color:#0f766e;font-weight:500;">
                                                        <?= $ngay_tien_ve ?: '<span style="color:#94a3b8">—</span>' ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($l['com_lead_source'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($l['bonus_license_trading'] ?? '') ?></td>
                                                    <td style="color:#c5221f;font-weight:600;">
                                                        <?= htmlspecialchars($l['com_1'] ?? '') ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($l['com_2'] ?? '') ?></td>
                                                    <td style="background:#f0f7ff; font-weight:600; color:#1e40af; text-align:center;">
                                                        <?= htmlspecialchars($l['license_trading'] ?? '') ?>
                                                    </td>
                                                    <td style="text-align:right;font-family:monospace;color:#b91c1c;font-weight:600;">
                                                        <?= $comm1_val_usd > 0 ? formatMoney($comm1_val_usd, 'USD') : '<span style="color:#d1d5db">—</span>' ?>
                                                    </td>
                                                    <td style="text-align:right;font-family:monospace;color:#b91c1c;font-weight:600;">
                                                        <?= $comm2_val_usd > 0 ? formatMoney($comm2_val_usd, 'USD') : '<span style="color:#d1d5db">—</span>' ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($l['note'] ?? '') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="month-total-row">
                                                <td colspan="14" style="text-align:right;">Tổng <?= $display_group ?>:</td>
                                                <td style="text-align:right;font-weight:700;color:#1d4ed8;">
                                                    <?= formatMoney($month_sub, 'VND') ?>
                                                </td>
                                                <td style="text-align:right;font-weight:700;color:#059669;">
                                                    <?= formatMoney($month_giaingan_usd, 'USD') ?>
                                                </td>
                                                <td colspan="8"></td>
                                                <td style="text-align:right;font-weight:700;color:#b91c1c;">
                                                    <?= formatMoney($month_comm1_usd, 'USD') ?>
                                                </td>
                                                <td style="text-align:right;font-weight:700;color:#b91c1c;">
                                                    <?= formatMoney($month_comm2_usd, 'USD') ?>
                                                </td>
                                                <td></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr style="background:#f1f5f9;font-weight:bold;">
                                            <td colspan="15" style="text-align:right;font-size:14px;padding: 1rem 0.75rem;">TỔNG
                                                CỘNG QUÝ:</td>
                                            <td style="text-align:right;color:#059669;font-size:14px;padding: 1rem 0.75rem;">
                                                <?= formatMoney($quarter_total_giaingan_usd, 'USD') ?>
                                            </td>
                                            <td colspan="8"></td>
                                            <td style="text-align:right;color:#b91c1c;font-size:14px;padding: 1rem 0.75rem;">
                                                <?= formatMoney($quarter_total_comm1_usd, 'USD') ?>
                                            </td>
                                            <td style="text-align:right;color:#b91c1c;font-size:14px;padding: 1rem 0.75rem;">
                                                <?= formatMoney($quarter_total_comm2_usd, 'USD') ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <?php
                                // Tính Commission theo rule quy định KPI
                                $kpi_pct = isset($pct_quarter) ? $pct_quarter : 0;

                                if ($kpi_pct < 70) {
                                    $payout_ratio = 0;
                                    $payout_label = "Dưới 70% KPI -> Nhận 0%";
                                } elseif ($kpi_pct < 100) {
                                    $payout_ratio = 0.7;
                                    $payout_label = "Từ 70% đến dưới 100% KPI -> Nhận 70%";
                                } else {
                                    $payout_ratio = 1.0;
                                    $payout_label = "Đạt >= 100% KPI -> Nhận 100%";
                                }

                                $final_comm1_usd = $quarter_total_comm1_usd * $payout_ratio;
                                $final_comm2_usd = $quarter_total_comm2_usd * $payout_ratio;
                                $total_com_usd = $final_comm1_usd + $final_comm2_usd;
                                ?>
                                <div
                                    style="margin-top: 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 1.5rem; max-width: 600px;">
                                    <h3
                                        style="margin-top:0; margin-bottom: 1rem; color: #1e293b; font-size: 16px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                            fill="none" stroke="#eab308" stroke-width="2.5">
                                            <circle cx="12" cy="8" r="7"></circle>
                                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                        </svg>
                                        TỔNG KẾT COMMISSION ĐƯỢC NHẬN
                                    </h3>

                                    <div
                                        style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: #475569; font-size: 14px;">
                                        <span>Tỉ lệ hoàn thành KPI (Quý):</span>
                                        <span
                                            style="font-weight: 600; color: #0f172a;"><?= number_format($kpi_pct, 1) ?>%</span>
                                    </div>
                                    <div
                                        style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: #475569; font-size: 14px;">
                                        <span>Hệ số Payout áp dụng:</span>
                                        <span
                                            style="font-weight: 600; color: <?= $payout_ratio == 0 ? '#ef4444' : ($payout_ratio == 1 ? '#10b981' : '#f59e0b') ?>;">
                                            <?= $payout_label ?>
                                        </span>
                                    </div>

                                    <div style="height: 1px; background: #e2e8f0; margin: 1rem 0;"></div>

                                    <div
                                        style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #475569; font-size: 14px;">
                                        <span>Tổng Commission (Com 1) x <?= $payout_ratio * 100 ?>%:</span>
                                        <span
                                            style="font-weight: 600; font-family: monospace; color: #b91c1c; font-size: 15px;">
                                            <?= formatMoney($final_comm1_usd, 'USD') ?>
                                        </span>
                                    </div>
                                    <div
                                        style="display: flex; justify-content: space-between; margin-bottom: 1rem; color: #475569; font-size: 14px;">
                                        <span>Tổng Com giữ lại (Com 2) x <?= $payout_ratio * 100 ?>%:</span>
                                        <span
                                            style="font-weight: 600; font-family: monospace; color: #b91c1c; font-size: 15px;">
                                            <?= formatMoney($final_comm2_usd, 'USD') ?>
                                        </span>
                                    </div>

                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; background: #fbbf24; color: #78350f; padding: 1rem; border-radius: 6px; font-weight: bold; font-size: 16px;">
                                        <span>THỰC NHẬN KỲ NÀY:</span>
                                        <span style="font-family: monospace; font-size: 20px;">
                                            <?= formatMoney($total_com_usd, 'USD') ?>
                                        </span>
                                    </div>
                                    <div
                                        style="text-align:right; font-size: 12px; color: #94a3b8; margin-top: 0.5rem; font-style: italic; margin-bottom: 1.5rem;">
                                        * Phụ thuộc vào chính sách chi trả của công ty theo từng thời kỳ.
                                    </div>

                                    <div style="border-top: 1px dashed #ced4da; padding-top: 1.5rem;">
                                        <button type="button" onclick="confirmCommission('<?= $active_tab ?>')"
                                            style="width: 100%; padding: 12px; background: <?= $comm_is_confirmed ? '#10b981' : '#2563eb' ?>; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;"
                                            onmouseover="this.style.background='<?= $comm_is_confirmed ? '#059669' : '#1d4ed8' ?>'"
                                            onmouseout="this.style.background='<?= $comm_is_confirmed ? '#10b981' : '#2563eb' ?>'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                            <?= $comm_is_confirmed ? 'ĐÃ XÁC NHẬN COMMISSION' : 'XÁC NHẬN COMMISSION' ?>
                                        </button>

                                        <div id="comm_history" style="margin-top: 1rem;">
                                            <?php if (!empty($comm_confirmations)): ?>
                                                <div
                                                    style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.025em;">
                                                    Lịch sử xác nhận:
                                                </div>
                                                <div
                                                    style="max-height: 150px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 6px; background: #f8fafc;">
                                                    <?php foreach ($comm_confirmations as $cc): ?>
                                                        <div
                                                            style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #475569; display: flex; justify-content: space-between; align-items: center;">
                                                            <span>
                                                                <span style="color: #0f172a; font-weight: 500;">✓</span>
                                                                <?= date('H:i d/m/Y', strtotime($cc['confirmed_at'])) ?>
                                                            </span>
                                                            <span style="color: #94a3b8; font-size: 11px;">Bởi:
                                                                <?= htmlspecialchars($cc['confirmed_by_name']) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; // end of if ($is_confirmed) for paid block ?>

            </div><!-- /.report-wrapper -->

        </main>


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
                formData.append('quarter', '<?= $active_tab ?>');

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            cell.innerHTML = newVal;
                            showToast("Đã lưu!");

                            // Trigger auto update for com_1 or license_trading
                            if (fieldName === 'client_type' || fieldName === 'com_lead_source') {
                                const com1Cell = document.getElementById('com_1_' + invoiceId);
                                if (com1Cell && data.com_1 !== undefined) {
                                    com1Cell.innerText = data.com_1;
                                }
                            }
                            if (fieldName === 'bonus_license_trading') {
                                const licCell = document.getElementById('lic_trd_' + invoiceId);
                                if (licCell && data.license_trading !== undefined) {
                                    licCell.innerText = data.license_trading;
                                }
                                // Update Net Profit style
                                const netProfitCell = document.getElementById('net_profit_' + invoiceId);
                                if (netProfitCell) {
                                    const npVal = netProfitCell.innerText.trim();
                                    if (newVal === 'Yes' && npVal === '') {
                                        netProfitCell.style.background = '#fee2e2';
                                        netProfitCell.style.border = '1.5px solid #ef4444';
                                    } else {
                                        netProfitCell.style.background = '';
                                        netProfitCell.style.border = '';
                                    }
                                }
                            }
                            if (fieldName === 'net_profit') {
                                const row = cell.closest('tr');
                                const bonusCell = row.querySelector('[data-required-field="bonus_license_trading"]');
                                if (bonusCell && bonusCell.innerText.trim() === 'Yes') {
                                    if (newVal.trim() === '') {
                                        cell.style.background = '#fee2e2';
                                        cell.style.border = '1.5px solid #ef4444';
                                    } else {
                                        cell.style.background = '';
                                        cell.style.border = '';
                                    }
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

            function confirmCommission(quarter) {
                if (!confirm('Bạn có chắc chắn muốn xác nhận số liệu Commission này không?')) return;

                const btn = event.currentTarget;
                const oldHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Đang xử lý...';

                const formData = new FormData();
                formData.append('action', 'confirm_commission');
                formData.append('quarter', quarter);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            showToast('Xác nhận thành công!');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('Lỗi: ' + res.error);
                            btn.disabled = false;
                            btn.innerHTML = oldHtml;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Đã có lỗi xảy ra');
                        btn.disabled = false;
                        btn.innerHTML = oldHtml;
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
                const requiredFields = ['contract_type', 'presales', 'client_type', 'com_lead_source', 'bonus_license_trading'];
                const fieldLabels = {
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

            function resetToDraft() {
                if (!confirm('Bạn chắc chắn muốn Reset to Draft? Bảng sẽ được mở khoá để chỉnh sửa.')) return;
                const formData = new FormData();
                formData.append('action', 'reset_draft');
                formData.append('quarter', '<?= $active_tab ?>');
                fetch('', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('🔓 Đã Reset to Draft!');
                            setTimeout(() => location.reload(), 700);
                        }
                    })
                    .catch(() => alert('Lỗi kết nối.'));
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