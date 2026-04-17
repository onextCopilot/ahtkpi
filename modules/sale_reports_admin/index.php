<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Check if user is admin
require_once __DIR__ . '/../../config/config.php';
if ($_SESSION['role'] !== 'admin') {
    header('Location: /dashboard');
    exit;
}

require_once __DIR__ . '/../../libs/OdooAPI.php';

$current_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Handle AM Exclusion Toggle
if (isset($_GET['toggle_exclude_am'])) {
    $am_id = (int) $_GET['toggle_exclude_am'];
    $val = (int) $_GET['val'];
    $conn->query("UPDATE users SET exclude_from_report = $val WHERE id = $am_id");
    header("Location: /sale-reports-admin?year=" . $current_year);
    exit;
}
$years = [$current_year + 1, $current_year, $current_year - 1, $current_year - 2];

// Helper: Convert to VND
$odoo = new OdooAPI();

// 1. Fetch Achieved Revenue from Odoo (Invoices)
// We want move_type='out_invoice' and state='posted' and date in the selected year.
$start_date_fetch = ($current_year - 1) . "-01-01";
$domain_inv = [
    ['move_type', '=', 'out_invoice'],
    ['state', '=', 'posted'],
    ['invoice_date', '>=', $start_date_fetch]
];
$fields_inv = ['invoice_user_id', 'amount_total_signed', 'invoice_date', 'id', 'state', 'amount_total', 'currency_id', 'invoice_payments_widget', 'date', 'company_id', 'x_studio_invoice_type_1'];
$all_invoices_year = [];
try {
    $all_invoices_year = $odoo->searchRead('account.move', $domain_inv, $fields_inv, 0, 0);
} catch (Exception $e) {
}

// 2. Fetch Recognised Revenue from Odoo (Sale Orders)
// We want state in ['sale', 'done'] and date_order in the selected year.
$domain_so = [
    ['state', 'in', ['sale', 'done']],
    ['date_order', '>=', "$current_year-01-01"],
    ['date_order', '<=', "$current_year-12-31"]
];
$fields_so = ['user_id', 'amount_total', 'date_order', 'currency_id', 'id', 'company_id'];
$all_orders_year = [];
try {
    $all_orders_year = $odoo->searchRead('sale.order', $domain_so, $fields_so, 0, 0);
} catch (Exception $e) {
}

$am_recognised = [];
$am_invoiced = [];
$odoo_map = [];

// Pre-fetch sale_reports for exclusion check to avoid N+1 queries
$local_sale_reports = [];
$res_sr = $conn->query("SELECT * FROM sale_reports");
if ($res_sr) {
    while ($sr = $res_sr->fetch_assoc()) {
        $local_sale_reports[(int) $sr['odoo_invoice_id']] = $sr;
    }
}

// Add column if not exists (Safe check for MySQL compatibility)
$check_col = $conn->query("SHOW COLUMNS FROM `users` LIKE 'exclude_from_report'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `exclude_from_report` TINYINT(1) DEFAULT 0");
}

// Fetch all AMs from DB for mapping (excluding hidden ones)
$all_ams_data = [];
$res_am_all = $conn->query("SELECT id, full_name, email, sale_level_id FROM users WHERE is_am_bd = 1 AND (exclude_from_report = 0 OR exclude_from_report IS NULL) ORDER BY full_name ASC");
if ($res_am_all) {
    while ($r = $res_am_all->fetch_assoc()) {
        $all_ams_data[] = $r;
    }
}

// Build mapping: Odoo Salesperson ID -> Local Full Name
$sid_to_local_name = [];
foreach ($all_ams_data as $u) {
    if (empty($u['email']))
        continue;
    try {
        $oid = $odoo->getOdooUserId($u['email']);
        if ($oid) {
            $sid_to_local_name[$oid] = trim($u['full_name']);
        }
    } catch (Exception $e) {
    }
}

foreach ($all_invoices_year as $inv) {
    if (($inv['state'] ?? '') !== 'posted')
        continue;

    // EXCLUDE internal invoices as requested
    if (($inv['x_studio_invoice_type_1'] ?? '') === 'Internal')
        continue;

    $odoo_map[$inv['id']] = $inv;

    $date = $inv['invoice_date'] ?: ($inv['date'] ?? null);
    if (!$date)
        continue;
    $q = ceil((int) date('n', strtotime($date)) / 3);

    $sid = (isset($inv['invoice_user_id']) && is_array($inv['invoice_user_id'])) ? $inv['invoice_user_id'][0] : 0;
    $am_name = $sid_to_local_name[$sid] ?? 'Unknown';

    // Fallback if not matched by ID, try name match as last resort
    if ($am_name === 'Unknown' && !empty($inv['invoice_user_id'])) {
        $oname = trim($inv['invoice_user_id'][1]);
        $am_name = $oname;
    }

    // Properly detect base currency and convert to VND, respecting accountant's manual rates on invoice
    $currencyCode = is_array($inv['currency_id'] ?? null) ? $inv['currency_id'][1] : 'VND';
    $odoo_total = (float) ($inv['amount_total'] ?? 0);
    $odoo_signed = abs((float) ($inv['amount_total_signed'] ?? 0));
    $compName = is_array($inv['company_id'] ?? null) ? $inv['company_id'][1] : null;
    $vnd_multiplier = $odoo->getRate('VND', $date, $compName) ?: 1.0;

    $amount_vnd = 0;
    if ($currencyCode === 'VND') {
        $amount_vnd = $odoo_total;
    } else if ($odoo_total > 0 && $odoo_signed > 0) {
        $ratio = $odoo_signed / $odoo_total;
        if ($ratio > 100) {
            // Odoo Base currency is already VND
            $amount_vnd = $odoo_total * $ratio;
        } else {
            // Odoo Base currency is likely MYR or another intermediate currency
            $amount_vnd = $odoo_total * $ratio * $vnd_multiplier;
        }
    }

    // Fallback
    if ($amount_vnd == 0 && $odoo_total > 0) {
        $rateSource = $odoo->getRate($currencyCode, $date, $compName) ?: 1.0;
        $amount_vnd = ($rateSource > 0) ? (($odoo_total / $rateSource) * $vnd_multiplier) : $odoo_total;
    }

    // Check exclusion (using local map)
    if (!empty($local_sale_reports[$inv['id']]['is_excluded'])) {
        continue;
    }

    $inv['amount_vnd_custom'] = $amount_vnd;
    $odoo_map[$inv['id']] = $inv;

    // Only Invoiced Revenue from current year invoices
    $iy = (int) date('Y', strtotime($date));
    if ($iy == $current_year) {
        if (!isset($am_invoiced[$am_name])) {
            $am_invoiced[$am_name] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
        }
        $am_invoiced[$am_name]["Q$q"] += $amount_vnd;
    }
}

// Process Sale Orders for Recognised Revenue
foreach ($all_orders_year as $order) {
    $date = $order['date_order'];
    if (!$date)
        continue;
    $q = ceil((int) date('n', strtotime($date)) / 3);

    $sid = (isset($order['user_id']) && is_array($order['user_id'])) ? $order['user_id'][0] : 0;
    $am_name = $sid_to_local_name[$sid] ?? 'Unknown';

    if ($am_name === 'Unknown' && !empty($order['user_id'])) {
        $am_name = trim($order['user_id'][1]);
    }

    // Convert SO amount to VND intelligently based on original currency
    $currencyCode = is_array($order['currency_id'] ?? null) ? $order['currency_id'][1] : 'VND';
    $odoo_total = (float) ($order['amount_total'] ?? 0);

    $amount_vnd = $odoo_total;
    $compName = is_array($order['company_id'] ?? null) ? $order['company_id'][1] : null;
    if ($currencyCode !== 'VND' && $odoo_total > 0) {
        $rateSource = $odoo->getRate($currencyCode, $date, $compName) ?: 1.0;
        $rateVnd = $odoo->getRate('VND', $date, $compName) ?: 1.0;
        if ($rateSource > 0) {
            $amount_vnd = ($odoo_total / $rateSource) * $rateVnd;
        }
    }

    if (!isset($am_recognised[$am_name])) {
        $am_recognised[$am_name] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
    }
    $am_recognised[$am_name]["Q$q"] += $amount_vnd;
}

// Merge AM lists
$all_ams = [];
foreach ($all_ams_data as $r) {
    $n = trim($r['full_name']);
    if (!empty($n)) {
        $all_ams[] = $n;
    }
}
$all_ams = array_unique($all_ams);
sort($all_ams);

// Fetch budgets
$am_budgets = [];
$am_to_uid = [];
foreach ($all_ams_data as $u_row) {
    $am_name = trim($u_row['full_name']);
    $uid = (int) $u_row['id'];
    $am_to_uid[$am_name] = $uid;
    $fallback_sale_level_id = (int) $u_row['sale_level_id'];

    $am_budgets[$am_name] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];

    if ($uid > 0) {
        for ($q = 1; $q <= 4; $q++) {
            $eff_level_id = null;
            $stmt_hist = $conn->prepare("
                SELECT sale_level_id FROM user_sale_level_history 
                WHERE user_id = ? AND (apply_year < ? OR (apply_year = ? AND apply_quarter <= ?))
                ORDER BY apply_year DESC, apply_quarter DESC LIMIT 1
            ");
            if ($stmt_hist) {
                $stmt_hist->bind_param("iiii", $uid, $current_year, $current_year, $q);
                $stmt_hist->execute();
                $hist_res = $stmt_hist->get_result();
                if ($row = $hist_res->fetch_assoc()) {
                    $eff_level_id = $row['sale_level_id'];
                }
            }
            if (!$eff_level_id) {
                $eff_level_id = $fallback_sale_level_id;
            }

            if ($eff_level_id > 0) {
                $stmt_lvl = $conn->prepare("SELECT kpi_quarter_vnd FROM sale_levels WHERE id = ?");
                $stmt_lvl->bind_param("i", $eff_level_id);
                $stmt_lvl->execute();
                if ($l_row = $stmt_lvl->get_result()->fetch_assoc()) {
                    $am_budgets[$am_name]["Q$q"] = (float) $l_row['kpi_quarter_vnd'];
                }
            }
        }
    }
}

// --- NEW: Calculate Commission for AM BD users ---
$am_commissions = [];
// Fetch KPI confirmation status
$confirmations = [];
// Fetch all confirmation events for the current year, ordered by ID asc
// so that the LATEST event for each (user, quarter) overwrites previous ones
$res_conf = $conn->query("SELECT user_id, quarter, type FROM sale_report_confirmations WHERE quarter LIKE '%_$current_year' ORDER BY id ASC");
if ($res_conf) {
    while ($rc = $res_conf->fetch_assoc()) {
        $uid = (int) $rc['user_id'];
        $qkey = $rc['quarter'];
        // If latest is 'confirmed' or 'commission_confirmed', icon shows. 
        // If latest is 'reset', it becomes false and hides the icon.
        $confirmations[$uid][$qkey] = ($rc['type'] === 'confirmed' || $rc['type'] === 'commission_confirmed');
    }
}

// Group Odoo invoices by AM from cache
$odoo_invoices_by_am = [];
foreach ($odoo_map as $oid => $inv) {
    $sid = (isset($inv['invoice_user_id']) && is_array($inv['invoice_user_id'])) ? $inv['invoice_user_id'][0] : 0;
    $am_name = $sid_to_local_name[$sid] ?? 'Unknown';

    if ($am_name === 'Unknown' && !empty($inv['invoice_user_id'])) {
        $am_name = trim($inv['invoice_user_id'][1]);
    }

    if ($am_name) {
        $odoo_invoices_by_am[$am_name][] = $inv;
    }
}

foreach ($all_ams as $am_name) {
    if ($am_name === 'Unknown' || empty($am_name))
        continue;

    $am_commissions[$am_name] = ['uid' => $am_to_uid[$am_name] ?? 0];
    $am_invoices = $odoo_invoices_by_am[$am_name] ?? [];

    for ($q = 1; $q <= 4; $q++) {
        $am_commissions[$am_name]["Q$q"] = ['com1' => 0, 'com1_vnd' => 0, 'com2' => 0, 'total' => 0, 'total_vnd' => 0, 'kpi_pct' => 0];

        // 1. KPI Achievement Percentage
        $actual = $am_invoiced[$am_name]["Q$q"] ?? 0;
        $budget = $am_budgets[$am_name]["Q$q"] ?? 0;
        $kpi_pct = ($budget > 0) ? ($actual / $budget) * 100 : 0;
        $am_commissions[$am_name]["Q$q"]['kpi_pct'] = $kpi_pct;

        // Payout Ratio Rules
        $payout_ratio = 1.0;
        if ($kpi_pct < 70)
            $payout_ratio = 0;
        elseif ($kpi_pct < 100)
            $payout_ratio = 0.7;

        // 2. Sum up commissions for invoices relevant to this quarter
        $q_com1 = 0;
        $q_com2 = 0;

        // Define quarter date range
        $start_m = ($q - 1) * 3 + 1;
        $end_m = $q * 3;
        $q_start_date = "$current_year-" . str_pad($start_m, 2, '0', STR_PAD_LEFT) . "-01";
        $q_end_date = "";

        if ($q == 1)
            $q_end_date = "$current_year-03-31";
        elseif ($q == 2)
            $q_end_date = "$current_year-06-30";
        elseif ($q == 3)
            $q_end_date = "$current_year-09-30";
        elseif ($q == 4)
            $q_end_date = "$current_year-12-31";

        $q_com1_usd = 0;
        $q_com1_vnd = 0;
        $q_com2_usd = 0;
        $q_com2_vnd = 0;

        foreach ($am_invoices as $inv) {
            $oid = $inv['id'];
            if (!empty($local_sale_reports[$oid]['is_excluded']))
                continue;

            $inv_date_str = $inv['invoice_date'] ?: ($inv['date'] ?? null);
            if (!$inv_date_str)
                continue;

            $is_in_quarter = ($inv_date_str >= $q_start_date && $inv_date_str <= $q_end_date);
            $has_payment_in_quarter = false;

            $pay_widget = $inv['invoice_payments_widget'] ?? null;
            $giaingan_origin = 0;
            if ($pay_widget && $pay_widget !== 'false') {
                $pw = is_array($pay_widget) ? $pay_widget : json_decode($pay_widget, true);
                if (is_array($pw) && isset($pw['content'])) {
                    foreach ($pw['content'] as $p) {
                        if (!empty($p['is_exchange']))
                            continue;

                        $giaingan_origin += (float) ($p['amount'] ?? 0);

                        $pdate = $p['date'] ?? null;
                        if ($pdate && $pdate >= $q_start_date && $pdate <= $q_end_date) {
                            $has_payment_in_quarter = true;
                        }
                    }
                }
            }

            // An invoice contributes to this quarter's total commission if:
            // 1. It is dated in this quarter (and paid)
            // 2. OR it is from a past quarter but was paid in this quarter
            if (($is_in_quarter && $giaingan_origin > 0) || ($inv_date_str < $q_start_date && $has_payment_in_quarter)) {
                $l = $local_sale_reports[$oid] ?? [];
                $com1_pct = (float) str_replace(['%', ','], '', $l['com_1'] ?? '0');
                $com2_pct = (float) str_replace(['%', ','], '', $l['com_2'] ?? '0');

                $amt_total_odoo = (float) ($inv['amount_total'] ?? 0);
                $amount_vnd_odoo = (float) ($inv['amount_vnd_custom'] ?? 0);

                $currencyCode = is_array($inv['currency_id'] ?? null) ? $inv['currency_id'][1] : 'VND';
                $compName = is_array($inv['company_id'] ?? null) ? $inv['company_id'][1] : null;
                $rateSource = $odoo->getRate($currencyCode, $inv_date_str, $compName) ?: 1.0;
                $rateUsd = $odoo->getRate('USD', $inv_date_str, $compName) ?: 1.0;

                $ratio_usd = $rateSource > 0 ? ($rateUsd / $rateSource) : 1;
                $ratio_vnd = $amt_total_odoo > 0 ? ($amount_vnd_odoo / $amt_total_odoo) : 1;

                $giaingan_usd = $giaingan_origin * $ratio_usd;
                $giaingan_vnd = $giaingan_origin * $ratio_vnd;

                // Bonus logic (10% of Net profit)
                $is_bonus_yes = ($l['bonus_license_trading'] ?? 'No') === 'Yes';
                $net_profit_f = (float) str_replace(['$', ','], '', $l['net_profit'] ?? '0');
                $bonus_extra = ($is_bonus_yes && $net_profit_f > 0) ? ($net_profit_f * 0.1) : 0;

                $com1_val_usd = ($giaingan_usd * ($com1_pct / 100)) + $bonus_extra;
                // For VND, convert bonus_extra (USD) to VND using the same ratio as the rest of the invoice
                $bonus_extra_vnd = $bonus_extra * $ratio_vnd / ($ratio_usd ?: 1);
                $com1_val_vnd = ($giaingan_vnd * ($com1_pct / 100)) + $bonus_extra_vnd;

                $q_com1_usd += $com1_val_usd;
                $q_com1_vnd += $com1_val_vnd;
                $q_com2_usd += ($giaingan_usd * ($com2_pct / 100));
                $q_com2_vnd += ($giaingan_vnd * ($com2_pct / 100));
            }
        }

        $am_commissions[$am_name]["Q$q"]['com1'] = $q_com1_usd * $payout_ratio;
        $am_commissions[$am_name]["Q$q"]['com1_vnd'] = $q_com1_vnd * $payout_ratio;
        $am_commissions[$am_name]["Q$q"]['total'] = $am_commissions[$am_name]["Q$q"]['com1'];
        $am_commissions[$am_name]["Q$q"]['total_vnd'] = $am_commissions[$am_name]["Q$q"]['com1_vnd'];
    }
}

// Helpers
function formatMoney($val)
{
    if ($val == 0)
        return '-';
    // Use 2 decimals if it has a fractional part, otherwise 0
    $decimals = (floor($val) != $val) ? 2 : 0;
    return number_format($val, $decimals, '.', ',');
}

function calcPercent($actual, $budget)
{
    if ($budget == 0 || !$budget)
        return '#DIV/0!';
    $pct = ($actual / $budget) * 100;
    return number_format($pct, 2) . '%';
}

function formatUSD($val)
{
    if ($val == 0)
        return '-';
    return '$' . number_format($val, 2, '.', ',');
}

// Budget Placeholder (We will assume 0 budget for now)
$budget_placeholder = 0;

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Reports (Admin)</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            line-height: 1.5;
        }

        .report-page-container {
            padding: 1rem 2rem;
            max-width: 100%;
        }

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filter-controls select {
            padding: 0.5rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .table-responsive {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            border: 1px solid #ccc;
        }

        table.revenue-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            white-space: nowrap;
            font-size: 11.5px;
            letter-spacing: -0.01em;
            color: #0f172a;
        }

        table.revenue-table thead {
            background-color: #004b75;
            position: sticky;
            top: 0;
            z-index: 11;
            /* Ensure thead stays above the body */
        }

        table.revenue-table thead th {
            position: sticky;
            top: 0;
            background-color: #0f172a;
            color: #f8fafc;
            font-weight: 600;
            padding: 5px 6px;
            text-align: center;
            border-bottom: 1px solid #1e293b;
            z-index: 10;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
        }

        table.revenue-table thead th:not(:last-child) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        table.revenue-table thead tr:nth-child(2) th {

            /* Safe overlap */
            box-shadow: 0 -2px 0 0 #004b75;
            /* Cover any potential gaps */
            z-index: 9;
            border-top: none;
        }

        table.revenue-table tbody td {
            padding: 3px 6px;
            border-bottom: 1px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #0f172a;
            font-weight: 500;
        }

        table.revenue-table tbody tr.section-header td,
        table.revenue-table tbody tr.group-header td {
            border-right: none;
        }

        table.revenue-table tbody tr.section-header td {
            background-color: #61A667;
            color: white;
            font-weight: bold;
        }

        table.revenue-table tbody tr.group-header td {
            background-color: #e2e8f0;
            font-weight: 600;
            padding-left: 10px;
        }

        table.revenue-table tbody td.text-right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        table.revenue-table tbody td.text-center {
            text-align: center;
        }

        table.revenue-table tbody td.am-name {
            padding-left: 24px;
        }

        table.revenue-table tbody tr.total-row {
            background-color: #f8fafc;
            border-top: 2px solid #cbd5e1;
        }

        table.revenue-table tbody tr.total-row td {
            font-weight: 700;
            color: #0f172a;
        }

        /* Alternating row colors for Invoiced Revenue */
        table.revenue-table tbody tr.invoiced-row:nth-child(odd) td {
            background-color: #ffffff;
        }

        table.revenue-table tbody tr.invoiced-row:nth-child(even) td {
            background-color: #f1f5f9;
        }

        /* Set STT Column Width */
        table.revenue-table th:nth-child(1),
        table.revenue-table td:nth-child(1) {
            width: 40px;
            min-width: 40px;
            max-width: 40px;
            text-align: center;
        }

        /* Specialized CSS for the Commission Summary Table */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2.5rem;
            border: 1px solid #cbd5e1;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(to right, #f8fafc, #eff6ff);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .report-table-container {
            overflow-x: auto;
            position: relative;
            background: #fff;
        }

        table.report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 10.5px;
            white-space: nowrap;
            letter-spacing: -0.01em;
            color: #0f172a;
        }

        table.report-table th {
            padding: 3px 5px;
            text-align: center;
            font-weight: 700;
            border-bottom: 1px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        table.report-table td {
            padding: 1px 5px;
            border-bottom: 1px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
            color: #0f172a;
            vertical-align: middle;
            font-weight: 500;
        }

        table.report-table thead tr:first-child th {
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.075em;
            background-color: #f8fafc;
        }

        table.report-table td.text-right {
            text-align: right;
            font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
            font-weight: 500;
            font-size: 13px;
        }

        table.report-table td.text-center {
            text-align: center;
        }

        table.report-table tbody tr:hover td {
            background-color: #f1f5f9 !important;
        }

        table.report-table tbody tr:hover td.year-total {
            background-color: #1e293b !important;
            color: #fff !important;
        }

        table.report-table .sticky-col {
            position: sticky;
            left: 0;
            z-index: 10;
            background-color: #fff;
            border-right: 2px solid #cbd5e1 !important;
            font-weight: 600;
            color: #0f172a;
            box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.05);
        }

        table.report-table thead th.sticky-col {
            background-color: #f8fafc;
            z-index: 20;
        }

        table.report-table tfoot td.sticky-col {
            background-color: #f8fafc;
            z-index: 10;
        }

        .kpi-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 13px;
        }

        .kpi-high {
            background: #dcfce7;
            color: #166534;
        }

        .kpi-mid {
            background: #fef9c3;
            color: #854d0e;
        }

        .kpi-low {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Report Info Box Style */
        .report-guide-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 5px solid #3b82f6;
            padding: 1.25rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .report-guide-box h3 {
            color: #1e40af;
            font-size: 1rem;
            margin-top: 0;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .report-guide-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .report-guide-item {
            font-size: 0.875rem;
            color: #475569;
            line-height: 1.5;
            position: relative;
            padding-left: 1.25rem;
        }

        .report-guide-item::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #3b82f6;
            font-weight: bold;
        }

        .report-guide-item strong {
            color: #1e293b;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Admin Sale Reports';
            include __DIR__ . '/../includes/topbar.php';
            ?>
            <div class="report-page-container">
                <div class="filter-controls">
                    <form method="GET" action="">
                        <label style="font-weight:600; margin-right:8px">Năm:</label>
                        <select name="year" onchange="this.form.submit()">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <div class="am-visibility-settings" style="margin-left: auto; position: relative;">
                        <button onclick="document.getElementById('am-settings-menu').classList.toggle('show')"
                            style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path
                                    d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z">
                                </path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            Ẩn/Hiện AM/Sales
                        </button>
                        <div id="am-settings-menu" class="dropdown-menu shadow-lg"
                            style="display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; z-index: 1000; min-width: 240px; max-height: 400px; overflow-y: auto; padding: 12px;">
                            <h4
                                style="margin: 0 0 10px 0; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                                Danh sách AM/Sales</h4>
                            <?php
                            $all_db_ams = $conn->query("SELECT id, full_name, exclude_from_report FROM users WHERE is_am_bd = 1 ORDER BY full_name ASC");
                            while ($am_row = $all_db_ams->fetch_assoc()):
                                $is_excluded = (int) $am_row['exclude_from_report'];
                                ?>
                                <label
                                    style="display: flex; align-items: center; gap: 8px; padding: 6px 4px; cursor: pointer; font-size: 13px; border-radius: 4px;"
                                    onmouseover="this.style.background='#f8fafc'"
                                    onmouseout="this.style.background='transparent'">
                                    <input type="checkbox" <?= $is_excluded ? '' : 'checked' ?>
                                        onchange="window.location.href='/sale-reports-admin?year=<?= $current_year ?>&toggle_exclude_am=<?= $am_row['id'] ?>&val='+(this.checked ? 0 : 1)">
                                    <span
                                        style="<?= $is_excluded ? 'color: #94a3b8; text-decoration: line-through;' : 'color: #1e293b;' ?>">
                                        <?= htmlspecialchars($am_row['full_name']) ?>
                                    </span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <style>
                        #am-settings-menu.show {
                            display: block !important;
                        }

                        .dropdown-menu {
                            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                        }
                    </style>
                </div>

                <?php if (!empty($all_ams)): ?>
                    <div class="table-responsive">
                        <table class="revenue-table">
                            <thead>
                                <tr>
                                    <th rowspan="2">STT</th>
                                    <th rowspan="2">Sub-Category / AM</th>
                                    <th colspan="3">Q1</th>
                                    <th colspan="3">Q2</th>
                                    <th colspan="3">H1</th>
                                    <th colspan="3">Q3</th>
                                    <th colspan="3">Q4</th>
                                    <th colspan="3">Year Total</th>
                                </tr>
                                <tr>
                                    <th>Budget/KPI</th>
                                    <th>Actual</th>
                                    <th>% Achieved</th>
                                    <th>Budget/KPI</th>
                                    <th>Actual</th>
                                    <th>% Achieved</th>
                                    <th>Budget/KPI</th>
                                    <th>Actual</th>
                                    <th>% Achieved</th>
                                    <th>Budget/KPI</th>
                                    <th>Actual</th>
                                    <th>% Achieved</th>
                                    <th>Budget/KPI</th>
                                    <th>Actual</th>
                                    <th>% Achieved</th>
                                    <th>Budget/KPI</th>
                                    <th>Actual</th>
                                    <th>% Achieved</th>
                                </tr>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="section-header">
                                    <td colspan="20">Revenue</td>
                                </tr>

                                <!-- RECOGNISED REVENUE -->
                                <tr class="group-header">
                                    <td colspan="20">Recognised Revenue</td>
                                </tr>
                                <?php
                                $stt_rec = 1;
                                foreach ($all_ams as $am):
                                    $data = $am_recognised[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                                    $actual_q1 = $data['Q1'];
                                    $actual_q2 = $data['Q2'];
                                    $actual_q3 = $data['Q3'];
                                    $actual_q4 = $data['Q4'];
                                    $actual_h1 = $actual_q1 + $actual_q2;

                                    $b_data = $am_budgets[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                                    $b_q1 = $b_data['Q1'];
                                    $b_q2 = $b_data['Q2'];
                                    $b_q3 = $b_data['Q3'];
                                    $b_q4 = $b_data['Q4'];
                                    $b_h1 = $b_q1 + $b_q2;
                                    ?>
                                    <tr class="recognised-row">
                                        <td class="text-center"><?= $stt_rec++ ?></td>
                                        <td class="am-name"><?= htmlspecialchars($am) ?></td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right"><?= formatMoney($actual_q1) ?></td>
                                        <td class="text-center" style="color: #cbd5e1;">-</td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right"><?= formatMoney($actual_q2) ?></td>
                                        <td class="text-center" style="color: #cbd5e1;">-</td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right"><?= formatMoney($actual_h1) ?></td>
                                        <td class="text-center" style="color: #cbd5e1;">-</td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right"><?= formatMoney($actual_q3) ?></td>
                                        <td class="text-center" style="color: #cbd5e1;">-</td>
                                        <!-- Q4 specific columns from image -->
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right" style="color: #cbd5e1;">-</td>
                                        <td class="text-right">
                                            <?= formatMoney($actual_q1 + $actual_q2 + $actual_q3 + $actual_q4) ?>
                                        </td>
                                        <td class="text-center" style="color: #cbd5e1;">-</td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php
                                $total_rec_q1 = 0;
                                $total_rec_q2 = 0;
                                $total_rec_q3 = 0;
                                $total_rec_q4 = 0;
                                foreach ($all_ams as $am) {
                                    $data = $am_recognised[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                                    $total_rec_q1 += $data['Q1'];
                                    $total_rec_q2 += $data['Q2'];
                                    $total_rec_q3 += $data['Q3'];
                                    $total_rec_q4 += $data['Q4'];
                                }
                                $total_rec_h1 = $total_rec_q1 + $total_rec_q2;
                                $total_rec_year = $total_rec_q1 + $total_rec_q2 + $total_rec_q3 + $total_rec_q4;
                                ?>
                                <tr class="total-row">
                                    <td></td>
                                    <td>Total Recognised</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><?= formatMoney($total_rec_q1) ?></td>
                                    <td class="text-center">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><?= formatMoney($total_rec_q2) ?></td>
                                    <td class="text-center">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><?= formatMoney($total_rec_h1) ?></td>
                                    <td class="text-center">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><?= formatMoney($total_rec_q3) ?></td>
                                    <td class="text-center">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><?= formatMoney($total_rec_q4) ?></td>
                                    <td class="text-center">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right"><?= formatMoney($total_rec_year) ?></td>
                                    <td class="text-center">-</td>
                                </tr>

                                <!-- INVOICED REVENUE -->
                                <tr class="group-header">
                                    <td colspan="20">Invoiced Revenue</td>
                                </tr>
                                <?php
                                $stt_inv = 1;
                                foreach ($all_ams as $am):
                                    $data = $am_invoiced[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                                    $actual_q1 = $data['Q1'];
                                    $actual_q2 = $data['Q2'];
                                    $actual_q3 = $data['Q3'];
                                    $actual_q4 = $data['Q4'];
                                    $actual_h1 = $actual_q1 + $actual_q2;

                                    $b_data = $am_budgets[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                                    $b_q1 = $b_data['Q1'];
                                    $b_q2 = $b_data['Q2'];
                                    $b_q3 = $b_data['Q3'];
                                    $b_q4 = $b_data['Q4'];
                                    $b_h1 = $b_q1 + $b_q2;
                                    ?>
                                    <tr class="invoiced-row">
                                        <td class="text-center"><?= $stt_inv++ ?></td>
                                        <td class="am-name"><?= htmlspecialchars($am) ?></td>
                                        <td class="text-right"><?= formatMoney($b_q1) ?></td>
                                        <td class="text-right"><?= formatMoney($actual_q1) ?></td>
                                        <td class="text-center"><?= calcPercent($actual_q1, $b_q1) ?></td>
                                        <td class="text-right"><?= formatMoney($b_q2) ?></td>
                                        <td class="text-right"><?= formatMoney($actual_q2) ?></td>
                                        <td class="text-center"><?= calcPercent($actual_q2, $b_q2) ?></td>
                                        <td class="text-right"><?= formatMoney($b_h1) ?></td>
                                        <td class="text-right"><?= formatMoney($actual_h1) ?></td>
                                        <td class="text-center"><?= calcPercent($actual_h1, $b_h1) ?></td>
                                        <td class="text-right"><?= formatMoney($b_q3) ?></td>
                                        <td class="text-right"><?= formatMoney($actual_q3) ?></td>
                                        <td class="text-center"><?= calcPercent($actual_q3, $b_q3) ?></td>
                                        <!-- Q4 specific columns from image -->
                                        <td class="text-right"><?= formatMoney($b_q4) ?></td>
                                        <td class="text-right"><?= formatMoney($actual_q4) ?></td>
                                        <td class="text-center"><?= calcPercent($actual_q4, $b_q4) ?></td>
                                        <td class="text-right"><?= formatMoney($b_q1 + $b_q2 + $b_q3 + $b_q4) ?></td>
                                        <td class="text-right">
                                            <?= formatMoney($actual_q1 + $actual_q2 + $actual_q3 + $actual_q4) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= calcPercent($actual_q1 + $actual_q2 + $actual_q3 + $actual_q4, $b_q1 + $b_q2 + $b_q3 + $b_q4) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="total-row">
                                    <td></td>
                                    <td>Total Invoiced</td>
                                    <?php
                                    $total_b_q1 = 0;
                                    $total_b_q2 = 0;
                                    $total_b_q3 = 0;
                                    $total_b_q4 = 0;
                                    $total_a_q1 = 0;
                                    $total_a_q2 = 0;
                                    $total_a_q3 = 0;
                                    $total_a_q4 = 0;

                                    foreach ($all_ams as $am) {
                                        $b_data = $am_budgets[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                                        $a_data = $am_invoiced[$am] ?? ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];

                                        $total_b_q1 += $b_data['Q1'];
                                        $total_b_q2 += $b_data['Q2'];
                                        $total_b_q3 += $b_data['Q3'];
                                        $total_b_q4 += $b_data['Q4'];

                                        $total_a_q1 += $a_data['Q1'];
                                        $total_a_q2 += $a_data['Q2'];
                                        $total_a_q3 += $a_data['Q3'];
                                        $total_a_q4 += $a_data['Q4'];
                                    }

                                    $total_b_h1 = $total_b_q1 + $total_b_q2;
                                    $total_a_h1 = $total_a_q1 + $total_a_q2;

                                    $total_b_year = $total_b_q1 + $total_b_q2 + $total_b_q3 + $total_b_q4;
                                    $total_a_year = $total_a_q1 + $total_a_q2 + $total_a_q3 + $total_a_q4;
                                    ?>
                                    <td class="text-right"><?= formatMoney($total_b_q1) ?></td>
                                    <td class="text-right"><?= formatMoney($total_a_q1) ?></td>
                                    <td class="text-center"><?= calcPercent($total_a_q1, $total_b_q1) ?></td>
                                    <td class="text-right"><?= formatMoney($total_b_q2) ?></td>
                                    <td class="text-right"><?= formatMoney($total_a_q2) ?></td>
                                    <td class="text-center"><?= calcPercent($total_a_q2, $total_b_q2) ?></td>
                                    <td class="text-right"><?= formatMoney($total_b_h1) ?></td>
                                    <td class="text-right"><?= formatMoney($total_a_h1) ?></td>
                                    <td class="text-center"><?= calcPercent($total_a_h1, $total_b_h1) ?></td>
                                    <td class="text-right"><?= formatMoney($total_b_q3) ?></td>
                                    <td class="text-right"><?= formatMoney($total_a_q3) ?></td>
                                    <td class="text-center"><?= calcPercent($total_a_q3, $total_b_q3) ?></td>
                                    <td class="text-right"><?= formatMoney($total_b_q4) ?></td>
                                    <td class="text-right"><?= formatMoney($total_a_q4) ?></td>
                                    <td class="text-center"><?= calcPercent($total_a_q4, $total_b_q4) ?></td>
                                    <td class="text-right"><?= formatMoney($total_b_year) ?></td>
                                    <td class="text-right"><?= formatMoney($total_a_year) ?></td>
                                    <td class="text-center"><?= calcPercent($total_a_year, $total_b_year) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Commission Summary Table -->
                    <div class="card" style="margin-top: 3rem;">
                        <div class="card-header">
                            <h2 class="card-title">TỔNG KẾT COMMISSION ĐƯỢC NHẬN (ƯỚC TÍNH)</h2>
                            <span style="font-size: 12px; color: #64748b; font-weight: 500;">Năm <?= $current_year ?> • Toàn
                                bộ
                                AM/BD</span>
                        </div>
                        <div class="report-table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="sticky-col">AM BD</th>
                                        <th colspan="3"
                                            style="background: #eff6ff; border-bottom: 3px solid #3b82f6; color: #1d4ed8;">
                                            QUÝ 1
                                            (USD)</th>
                                        <th colspan="3"
                                            style="background: #f0fdf4; border-bottom: 3px solid #10b981; color: #059669;">
                                            QUÝ 2
                                            (USD)</th>
                                        <th colspan="3"
                                            style="background: #fffbeb; border-bottom: 3px solid #f59e0b; color: #b45309;">
                                            QUÝ 3
                                            (USD)</th>
                                        <th colspan="3"
                                            style="background: #fdf2f8; border-bottom: 3px solid #db2777; color: #9d174d;">
                                            QUÝ 4
                                            (USD)</th>
                                        <th rowspan="2" class="year-total"
                                            style="background: #1e293b; color: #fff; width: 120px;">TỔNG CẢ NĂM
                                        </th>
                                    </tr>
                                    <tr style="background: #f8fafc;">
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <th>% KPI</th>
                                            <th>Commission</th>
                                            <th style="border-right: 2px solid #cbd5e1; color: #1e293b; background: #f1f5f9;">
                                                Tổng
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $grand_total_year = 0;
                                    $grand_total_year_vnd = 0;
                                    $q_totals = [
                                        1 => ['com1' => 0, 'com1_vnd' => 0, 'com2' => 0, 'total' => 0, 'total_vnd' => 0],
                                        2 => ['com1' => 0, 'com1_vnd' => 0, 'com2' => 0, 'total' => 0, 'total_vnd' => 0],
                                        3 => ['com1' => 0, 'com1_vnd' => 0, 'com2' => 0, 'total' => 0, 'total_vnd' => 0],
                                        4 => ['com1' => 0, 'com1_vnd' => 0, 'com2' => 0, 'total' => 0, 'total_vnd' => 0]
                                    ];

                                    foreach ($all_ams as $am):
                                        if ($am === 'Unknown' || empty($am))
                                            continue;
                                        $c = $am_commissions[$am] ?? null;
                                        if (!$c)
                                            continue;

                                        $am_year_total = $c['Q1']['total'] + $c['Q2']['total'] + $c['Q3']['total'] + $c['Q4']['total'];
                                        $am_year_total_vnd = $c['Q1']['total_vnd'] + $c['Q2']['total_vnd'] + $c['Q3']['total_vnd'] + $c['Q4']['total_vnd'];
                                        $grand_total_year += $am_year_total;
                                        $grand_total_year_vnd += $am_year_total_vnd;

                                        for ($i = 1; $i <= 4; $i++) {
                                            $q_totals[$i]['com1'] += $c["Q$i"]['com1'];
                                            $q_totals[$i]['com1_vnd'] += $c["Q$i"]['com1_vnd'];
                                            $q_totals[$i]['com2'] += $c["Q$i"]['com2'];
                                            $q_totals[$i]['total'] += $c["Q$i"]['total'];
                                            $q_totals[$i]['total_vnd'] += $c["Q$i"]['total_vnd'];
                                        }
                                        ?>
                                        <tr style="cursor: pointer;"
                                            onclick="viewReport(<?= (int) ($c['uid'] ?? 0) ?>, 'Q1_<?= $current_year ?>')">
                                            <td class="sticky-col">
                                                <?= htmlspecialchars($am) ?>
                                            </td>
                                            <?php for ($i = 1; $i <= 4; $i++):
                                                $qi = "Q$i";
                                                $kpi = $c[$qi]['kpi_pct'] ?? 0;
                                                $badge_class = 'kpi-low';
                                                if ($kpi >= 100)
                                                    $badge_class = 'kpi-high';
                                                elseif ($kpi >= 70)
                                                    $badge_class = 'kpi-mid';
                                                ?>
                                                <td class="text-center"
                                                    onclick="event.stopPropagation(); viewReport(<?= (int) ($c['uid'] ?? 0) ?>, 'Q<?= $i ?>_<?= $current_year ?>')">
                                                    <div
                                                        style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                                        <span class="kpi-badge <?= $badge_class ?>">
                                                            <?= number_format($kpi, 1) ?>%
                                                        </span>
                                                        <?php
                                                        $uid = (int) ($c['uid'] ?? 0);
                                                        $q_key = "Q{$i}_{$current_year}";
                                                        if (!empty($confirmations[$uid][$q_key])): ?>
                                                            <span title="Đã xác nhận Q<?= $i ?>"
                                                                style="display: inline-flex; align-items: center;">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                                    viewBox="0 0 24 24" fill="#3b82f6" stroke="#1d4ed8" stroke-width="2"
                                                                    stroke-linecap="round" stroke-linejoin="round">
                                                                    <circle cx="12" cy="8" r="7"></circle>
                                                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88">
                                                                    </polyline>
                                                                </svg>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-right"
                                                    onclick="event.stopPropagation(); viewReport(<?= (int) ($c['uid'] ?? 0) ?>, 'Q<?= $i ?>_<?= $current_year ?>')"
                                                    style="font-size: 11.5px;">
                                                    <?php if (($c[$qi]['com1'] ?? 0) == 0): ?>
                                                        -
                                                    <?php else: ?>
                                                        <div><?= formatMoney($c[$qi]['com1_vnd'] ?? 0) ?> VND</div>
                                                        <div style="font-size: 10px; opacity: 0.9; font-weight: 500;">
                                                            (<?= formatUSD($c[$qi]['com1'] ?? 0) ?>)</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right"
                                                    onclick="event.stopPropagation(); viewReport(<?= (int) ($c['uid'] ?? 0) ?>, 'Q<?= $i ?>_<?= $current_year ?>')"
                                                    style="font-weight: 700; border-right: 2px solid #cbd5e1; background: #f8fafc; color: #0f172a; font-size: 11.5px;">
                                                    <?php if (($c[$qi]['total'] ?? 0) == 0): ?>
                                                        -
                                                    <?php else: ?>
                                                        <div><?= formatMoney($c[$qi]['total_vnd'] ?? 0) ?> VND</div>
                                                        <div style="font-size: 10px; opacity: 0.9; font-weight: 500;">
                                                            (<?= formatUSD($c[$qi]['total'] ?? 0) ?>)</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>

                                            <td class="text-right year-total"
                                                style="background: #1e293b; color: #fff; font-weight: 700; font-size: 13px;">
                                                <div><?= formatMoney($am_year_total_vnd) ?> VND</div>
                                                <div style="font-size: 10.5px; opacity: 0.95; font-weight: 500;">
                                                    (<?= formatUSD($am_year_total) ?>)</div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f8fafc; font-weight: 800; color: #0f172a;">
                                        <td class="sticky-col">TỔNG CỘNG</td>
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <td></td>
                                            <td class="text-right" style="font-size: 11.5px;">
                                                <?php if (($q_totals[$i]['com1'] ?? 0) == 0): ?>
                                                    -
                                                <?php else: ?>
                                                    <div><?= formatMoney($q_totals[$i]['com1_vnd']) ?> VND</div>
                                                    <div style="font-size: 10px; font-weight: 500;">
                                                        (<?= formatUSD($q_totals[$i]['com1']) ?>)</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right"
                                                style="border-right: 2px solid #cbd5e1; background: #f1f5f9; color: #1e293b; font-size: 11.5px;">
                                                <?php if (($q_totals[$i]['total'] ?? 0) == 0): ?>
                                                    -
                                                <?php else: ?>
                                                    <div><?= formatMoney($q_totals[$i]['total_vnd']) ?> VND</div>
                                                    <div style="font-size: 10px; font-weight: 500;">
                                                        (<?= formatUSD($q_totals[$i]['total']) ?>)</div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>

                                        <td class="text-right year-total"
                                            style="background: #0f172a; color: #fff; font-size: 12px; border: none;">
                                            <?php if ($grand_total_year == 0): ?>
                                                -
                                            <?php else: ?>
                                                <div><?= formatMoney($grand_total_year_vnd) ?> VND</div>
                                                <div style="font-size: 10px; font-weight: 500;">
                                                    (<?= formatUSD($grand_total_year) ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card"
                        style="margin: 2rem; padding: 2rem; text-align: center; border: 2px dashed #cbd5e1; border-radius: 12px; color: #64748b;">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5; margin-left: auto; margin-right: auto;"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p style="font-size: 1.125rem; font-weight: 600;">Không tìm thấy dữ liệu AM/BD</p>
                        <p style="font-size: 0.875rem;">Vui lòng kiểm tra lại cấu hình người dùng (is_am_bd = 1).</p>
                    </div>
                <?php endif; ?>

                <div class="report-guide-box" style="margin-top: 3rem;">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:20px; height:20px" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Hướng dẫn & Cơ chế lấy dữ liệu
                    </h3>
                    <ul class="report-guide-list">
                        <li class="report-guide-item">
                            <strong>Nguồn dữ liệu:</strong> Dữ liệu được đồng bộ trực tiếp từ hệ thống Odoo Invoices
                            (Hóa đơn) và Payments (Thanh toán).
                        </li>
                        <li class="report-guide-item">
                            <strong>Doanh thu KPI (Recognised):</strong> Tính dựa trên các hóa đơn Odoo ở trạng thái
                            <code>Posted</code>. Không bao gồm các hóa đơn bị loại trừ thủ công.
                        </li>
                        <li class="report-guide-item">
                            <strong>Invoiced Revenue (Actual):</strong> Tổng giá trị Invoice được ghi nhận trên hệ thống
                            Debts (Công nợ) tại trạng thái <code>Posted</code>.
                        </li>
                        <li class="report-guide-item">
                            <strong>Commission (Ước tính):</strong> Tính dựa trên số tiền khách hàng thực trả trong Quý.
                            Tỷ lệ nhận Commission (Payout Ratio) phụ thuộc vào % đạt KPI doanh thu của từng Quý (70-100%
                            nhận 70%, trên 100% nhận đủ 100%).
                        </li>
                        <li class="report-guide-item">
                            <strong>Tương tác:</strong> Click vào từng dòng hoặc ô trong bảng <strong>TỔNG KẾT
                                COMMISSION</strong> để xem chi tiết tính toán theo từng hóa đơn của AM/BD.
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    <script>
        function viewReport(userId, quarter) {
            if (!userId) {
                alert('Không tìm thấy ID người dùng tương ứng.');
                return;
            }
            window.location.href = `/detail-report?user_id=${userId}&quarter=${quarter}`;
        }
    </script>
</body>

</html>