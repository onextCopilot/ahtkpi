<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
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
$years = [$current_year + 1, $current_year, $current_year - 1, $current_year - 2];

// Helper: Convert to VND
$odoo = new OdooAPI();

// 1. Fetch Recognised Revenue from Odoo (Sale Orders)
// We want state in ('sale', 'done') and date_order in the selected year.
$domain_so = [
    ['state', 'in', ['sale', 'done']],
    ['date_order', '>=', "$current_year-01-01"],
    ['date_order', '<=', "$current_year-12-31"]
];
$fields_so = [
    'user_id',
    'team_id',
    'amount_total',
    'currency_id',
    'date_order'
];

$sale_orders = [];
try {
    $sale_orders = $odoo->searchRead('sale.order', $domain_so, $fields_so, 0, 0);
} catch (Exception $e) {
    // Ignore error
}

$am_recognised = []; // group by AM name
foreach ($sale_orders as $so) {
    $date = $so['date_order'];
    if (!$date)
        continue;

    $m = (int) date('n', strtotime($date));
    $q = ceil($m / 3);

    // Determine AM name
    $am_name = 'Unknown';
    if (!empty($so['user_id']) && is_array($so['user_id'])) {
        $am_name = trim($so['user_id'][1]);
    } elseif (!empty($so['team_id']) && is_array($so['team_id'])) {
        $am_name = trim($so['team_id'][1]);
    }

    $amount = (float) $so['amount_total'];
    $currency_code = is_array($so['currency_id']) ? $so['currency_id'][1] : ($so['currency_id'] ?? 'VND');

    if ($currency_code !== 'VND') {
        $rateSource = $odoo->getRate($currency_code, $date) ?: 1.0;
        $rateVnd = $odoo->getRate('VND', $date) ?: 1.0;
        $amount = $amount * ($rateVnd / $rateSource);
    }

    if (!isset($am_recognised[$am_name])) {
        $am_recognised[$am_name] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
    }
    $am_recognised[$am_name]["Q$q"] += $amount;
}

// 2. Fetch Invoiced Revenue from Debts
$stmt = $conn->prepare("
    SELECT d.am, d.amount, d.currency, d.invoice_date, d.odoo_invoice_id, sr.is_excluded 
    FROM debts d 
    LEFT JOIN sale_reports sr ON d.odoo_invoice_id = sr.odoo_invoice_id 
    WHERE d.invoice_date >= ? AND d.invoice_date <= ?
");
$start_date = "$current_year-01-01";
$end_date = "$current_year-12-31";
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();

$odoo->getInvoices(10000, 0, []);
$odoo_map = $odoo->getInvoiceMap();

$am_invoiced = []; // group by AM name
while ($row = $res->fetch_assoc()) {
    if (!empty($row['is_excluded'])) {
        continue;
    }

    $date = $row['invoice_date'];
    if (!$date)
        continue;

    $m = (int) date('n', strtotime($date));
    $q = ceil($m / 3);

    $am_name = $row['am'] ? trim($row['am']) : 'Unknown';

    $amount = (float) $row['amount'];
    $currency_code = $row['currency'] ?? 'VND';
    $oid = $row['odoo_invoice_id'];

    $vnd_value = 0;
    if (!empty($oid) && isset($odoo_map[$oid])) {
        $inv = $odoo_map[$oid];
        $tot = (float) $inv['amount_total'];
        $sig = abs((float) $inv['amount_total_signed']);
        if ($tot > 0) {
            $vnd_value = $amount * ($sig / $tot);
        }
    }

    // Fallback to manual rate calculation
    if ($vnd_value <= 0 && $currency_code === 'VND') {
        $vnd_value = $amount;
    } elseif ($vnd_value <= 0 && $currency_code !== 'VND') {
        $rateSource = $odoo->getRate($currency_code, $date) ?: 1.0;
        $rateVnd = $odoo->getRate('VND', $date) ?: 1.0;
        $vnd_value = $amount * ($rateVnd / $rateSource);
    }

    if (!isset($am_invoiced[$am_name])) {
        $am_invoiced[$am_name] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
    }
    $am_invoiced[$am_name]["Q$q"] += $vnd_value;
}

// Merge AM lists
$db_ams = [];
$res_am = $conn->query("SELECT full_name FROM users WHERE is_am_bd = 1 ORDER BY full_name ASC");
if ($res_am) {
    while ($r = $res_am->fetch_assoc()) {
        $n = trim($r['full_name']);
        if (!empty($n)) {
            $db_ams[] = $n;
        }
    }
}
$all_ams = array_unique($db_ams);
sort($all_ams);

// Fetch budgets
$am_budgets = [];
foreach ($all_ams as $am_name) {
    if ($am_name === 'Unknown' || empty($am_name)) {
        $am_budgets[$am_name] = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
        continue;
    }

    // find user by full name
    $stmt_u = $conn->prepare("SELECT id, sale_level_id FROM users WHERE full_name LIKE ? OR username LIKE ? LIMIT 1");
    $like_name = "%" . $am_name . "%";
    $stmt_u->bind_param("ss", $like_name, $like_name);
    $stmt_u->execute();
    $u_res = $stmt_u->get_result();
    $u_row = $u_res->fetch_assoc();

    $uid = $u_row ? (int) $u_row['id'] : 0;
    $fallback_sale_level_id = $u_row ? (int) $u_row['sale_level_id'] : 0;

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

        }
    }
}

// --- NEW: Calculate Commission for AM BD users ---
$am_commissions = [];
$local_sale_reports = [];
$res_sr = $conn->query("SELECT * FROM sale_reports");
if ($res_sr) {
    while ($sr = $res_sr->fetch_assoc()) {
        $local_sale_reports[$sr['odoo_invoice_id']] = $sr;
    }
}

// Group Odoo invoices by AM from cache
$odoo_invoices_by_am = [];
foreach ($odoo_map as $oid => $inv) {
    $inv_am = '';
    if (!empty($inv['invoice_user_id']) && is_array($inv['invoice_user_id'])) {
        $inv_am = trim($inv['invoice_user_id'][1]);
    }
    if ($inv_am) {
        $odoo_invoices_by_am[$inv_am][] = $inv;
    }
}

foreach ($all_ams as $am_name) {
    if ($am_name === 'Unknown' || empty($am_name))
        continue;

    $am_commissions[$am_name] = [];
    $am_invoices = $odoo_invoices_by_am[$am_name] ?? [];

    for ($q = 1; $q <= 4; $q++) {
        $am_commissions[$am_name]["Q$q"] = ['com1' => 0, 'com2' => 0, 'total' => 0, 'kpi_pct' => 0];

        // 1. KPI Achievement Percentage
        $actual = $am_recognised[$am_name]["Q$q"] ?? 0;
        $budget = $am_budgets[$am_name]["Q$q"] ?? 0;
        $kpi_pct = ($budget > 0) ? ($actual / $budget) * 100 : 0;
        $am_commissions[$am_name]["Q$q"]['kpi_pct'] = $kpi_pct;

        // Payout Ratio Rules
        $payout_ratio = 1.0;
        if ($kpi_pct < 70)
            $payout_ratio = 0;
        elseif ($kpi_pct < 100)
            $payout_ratio = 0.7;

        // 2. Sum up commissions from payments in this quarter
        $q_com1 = 0;
        $q_com2 = 0;

        foreach ($am_invoices as $inv) {
            $oid = $inv['id'];
            if (!empty($local_sale_reports[$oid]['is_excluded']))
                continue;

            $pay_widget = $inv['invoice_payments_widget'] ?? null;
            if ($pay_widget && $pay_widget !== 'false') {
                $pw = is_array($pay_widget) ? $pay_widget : json_decode($pay_widget, true);
                if (is_array($pw) && isset($pw['content'])) {
                    foreach ($pw['content'] as $p) {
                        $pdate = $p['date'] ?? null;
                        if (!$pdate)
                            continue;

                        $pm = (int) date('n', strtotime($pdate));
                        $py = (int) date('Y', strtotime($pdate));
                        $pq = ceil($pm / 3);

                        // If payment happened in the specific year and quarter
                        if ($py == $current_year && $pq == $q && empty($p['is_exchange'])) {
                            $paid_amount_origin = (float) ($p['amount'] ?? 0);
                            $l = $local_sale_reports[$oid] ?? [];
                            $com1_pct = (float) str_replace('%', '', $l['com_1'] ?? '0');
                            $com2_pct = (float) str_replace('%', '', $l['com_2'] ?? '0');

                            // Convert to USD for commission (standardizing on USD as in individual reports)
                            $currency_code = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
                            $ratio_usd = 1.0;
                            if ($currency_code !== 'USD') {
                                $inv_date = $inv['invoice_date'] ?: $inv['date'];
                                $rateSource = $odoo->getRate($currency_code, $inv_date) ?: 1.0;
                                $rateUsd = $odoo->getRate('USD', $inv_date) ?: 1.0;
                                if ($rateSource > 0)
                                    $ratio_usd = $rateUsd / $rateSource;
                            }

                            $paid_usd = $paid_amount_origin * $ratio_usd;
                            $q_com1 += $paid_usd * ($com1_pct / 100);
                            $q_com2 += $paid_usd * ($com2_pct / 100);
                        }
                    }
                }
            }
        }

        $am_commissions[$am_name]["Q$q"]['com1'] = $q_com1 * $payout_ratio;
        $am_commissions[$am_name]["Q$q"]['com2'] = $q_com2 * $payout_ratio;
        $am_commissions[$am_name]["Q$q"]['total'] = ($q_com1 + $q_com2) * $payout_ratio;
    }
}

// Helpers
function formatMoney($val)
{
    if ($val == 0)
        return '-';
    return number_format($val, 0, '.', ',');
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
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        .report-page-container {
            padding: 1rem 2rem;
            max-width: 100%;
            height: calc(100vh - 80px);
            overflow-x: auto;
            overflow-y: auto;
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
            overflow: auto;
            max-height: calc(100vh - 180px);
            border: 1px solid #ccc;
        }

        table.revenue-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            white-space: nowrap;
            font-size: 13px;
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
            background-color: #004b75;
            color: white;
            font-weight: 600;
            padding: 10px 12px;
            text-align: center;
            border-bottom: 2px solid #003655;
            z-index: 10;
        }

        table.revenue-table thead th:not(:last-child) {
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        table.revenue-table thead tr:nth-child(2) th {
            top: 38px;
            /* Safe overlap */
            box-shadow: 0 -2px 0 0 #004b75;
            /* Cover any potential gaps */
            z-index: 9;
            border-top: none;
        }

        table.revenue-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #333;
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
                </div>

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
            </div>

            <!-- Commission Summary Table -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">TỔNG KẾT COMMISSION ĐƯỢC NHẬN (ƯỚC TÍNH)</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="background: #f8fafc; position: sticky; left: 0; z-index: 10;">AM
                                    BD</th>
                                <th colspan="4" style="background: #eff6ff;"> QUÝ 1 (USD)</th>
                                <th colspan="4" style="background: #f0fdf4;"> QUÝ 2 (USD)</th>
                                <th colspan="4" style="background: #fffbeb;"> QUÝ 3 (USD)</th>
                                <th colspan="4" style="background: #fdf2f8;"> QUÝ 4 (USD)</th>
                                <th rowspan="2" style="background: #1e293b; color: #fff;">TỔNG CẢ NĂM</th>
                            </tr>
                            <tr style="font-size: 11px;">
                                <!-- Q1 -->
                                <th style="background: #eff6ff;">% KPI</th>
                                <th style="background: #eff6ff;">Com 1</th>
                                <th style="background: #eff6ff;">Com 2</th>
                                <th style="background: #eff6ff; border-right: 2px solid #cbd5e1;">Tổng</th>
                                <!-- Q2 -->
                                <th style="background: #f0fdf4;">% KPI</th>
                                <th style="background: #f0fdf4;">Com 1</th>
                                <th style="background: #f0fdf4;">Com 2</th>
                                <th style="background: #f0fdf4; border-right: 2px solid #cbd5e1;">Tổng</th>
                                <!-- Q3 -->
                                <th style="background: #fffbeb;">% KPI</th>
                                <th style="background: #fffbeb;">Com 1</th>
                                <th style="background: #fffbeb;">Com 2</th>
                                <th style="background: #fffbeb; border-right: 2px solid #cbd5e1;">Tổng</th>
                                <!-- Q4 -->
                                <th style="background: #fdf2f8;">% KPI</th>
                                <th style="background: #fdf2f8;">Com 1</th>
                                <th style="background: #fdf2f8;">Com 2</th>
                                <th style="background: #fdf2f8; border-right: 2px solid #cbd5e1;">Tổng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grand_total_year = 0;
                            $q_totals = [
                                1 => ['com1' => 0, 'com2' => 0, 'total' => 0],
                                2 => ['com1' => 0, 'com2' => 0, 'total' => 0],
                                3 => ['com1' => 0, 'com2' => 0, 'total' => 0],
                                4 => ['com1' => 0, 'com2' => 0, 'total' => 0]
                            ];

                            foreach ($all_ams as $am):
                                if ($am === 'Unknown' || empty($am))
                                    continue;
                                $c = $am_commissions[$am] ?? null;
                                if (!$c)
                                    continue;

                                $am_year_total = $c['Q1']['total'] + $c['Q2']['total'] + $c['Q3']['total'] + $c['Q4']['total'];
                                $grand_total_year += $am_year_total;

                                for ($i = 1; $i <= 4; $i++) {
                                    $q_totals[$i]['com1'] += $c["Q$i"]['com1'];
                                    $q_totals[$i]['com2'] += $c["Q$i"]['com2'];
                                    $q_totals[$i]['total'] += $c["Q$i"]['total'];
                                }
                                ?>
                                <tr>
                                    <td style="background: #f8fafc; position: sticky; left: 0; font-weight: 600;">
                                        <?= htmlspecialchars($am) ?>
                                    </td>
                                    <!-- Q1 -->
                                    <td class="text-center"
                                        style="<?= $c['Q1']['kpi_pct'] >= 100 ? 'color: #10b981; font-weight:bold;' : ($c['Q1']['kpi_pct'] < 70 ? 'color: #ef4444;' : 'color: #f59e0b;') ?>">
                                        <?= number_format($c['Q1']['kpi_pct'], 1) ?>%
                                    </td>
                                    <td class="text-right"><?= formatUSD($c['Q1']['com1']) ?></td>
                                    <td class="text-right"><?= formatUSD($c['Q1']['com2']) ?></td>
                                    <td class="text-right" style="font-weight: 600; border-right: 2px solid #cbd5e1;">
                                        <?= formatUSD($c['Q1']['total']) ?></td>

                                    <!-- Q2 -->
                                    <td class="text-center"
                                        style="<?= $c['Q2']['kpi_pct'] >= 100 ? 'color: #10b981; font-weight:bold;' : ($c['Q2']['kpi_pct'] < 70 ? 'color: #ef4444;' : 'color: #f59e0b;') ?>">
                                        <?= number_format($c['Q2']['kpi_pct'], 1) ?>%
                                    </td>
                                    <td class="text-right"><?= formatUSD($c['Q2']['com1']) ?></td>
                                    <td class="text-right"><?= formatUSD($c['Q2']['com2']) ?></td>
                                    <td class="text-right" style="font-weight: 600; border-right: 2px solid #cbd5e1;">
                                        <?= formatUSD($c['Q2']['total']) ?></td>

                                    <!-- Q3 -->
                                    <td class="text-center"
                                        style="<?= $c['Q3']['kpi_pct'] >= 100 ? 'color: #10b981; font-weight:bold;' : ($c['Q3']['kpi_pct'] < 70 ? 'color: #ef4444;' : 'color: #f59e0b;') ?>">
                                        <?= number_format($c['Q3']['kpi_pct'], 1) ?>%
                                    </td>
                                    <td class="text-right"><?= formatUSD($c['Q3']['com1']) ?></td>
                                    <td class="text-right"><?= formatUSD($c['Q3']['com2']) ?></td>
                                    <td class="text-right" style="font-weight: 600; border-right: 2px solid #cbd5e1;">
                                        <?= formatUSD($c['Q3']['total']) ?></td>

                                    <!-- Q4 -->
                                    <td class="text-center"
                                        style="<?= $c['Q4']['kpi_pct'] >= 100 ? 'color: #10b981; font-weight:bold;' : ($c['Q4']['kpi_pct'] < 70 ? 'color: #ef4444;' : 'color: #f59e0b;') ?>">
                                        <?= number_format($c['Q4']['kpi_pct'], 1) ?>%
                                    </td>
                                    <td class="text-right"><?= formatUSD($c['Q4']['com1']) ?></td>
                                    <td class="text-right"><?= formatUSD($c['Q4']['com2']) ?></td>
                                    <td class="text-right" style="font-weight: 600; border-right: 2px solid #cbd5e1;">
                                        <?= formatUSD($c['Q4']['total']) ?></td>

                                    <td class="text-right" style="background: #f1f5f9; font-weight: 700;">
                                        <?= formatUSD($am_year_total) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8fafc; font-weight: 700;">
                                <td style="position: sticky; left: 0; background: #f8fafc;">TỔNG CỘNG</td>
                                <!-- Q1 -->
                                <td></td>
                                <td class="text-right"><?= formatUSD($q_totals[1]['com1']) ?></td>
                                <td class="text-right"><?= formatUSD($q_totals[1]['com2']) ?></td>
                                <td class="text-right" style="border-right: 2px solid #cbd5e1;">
                                    <?= formatUSD($q_totals[1]['total']) ?></td>
                                <!-- Q2 -->
                                <td></td>
                                <td class="text-right"><?= formatUSD($q_totals[2]['com1']) ?></td>
                                <td class="text-right"><?= formatUSD($q_totals[2]['com2']) ?></td>
                                <td class="text-right" style="border-right: 2px solid #cbd5e1;">
                                    <?= formatUSD($q_totals[2]['total']) ?></td>
                                <!-- Q3 -->
                                <td></td>
                                <td class="text-right"><?= formatUSD($q_totals[3]['com1']) ?></td>
                                <td class="text-right"><?= formatUSD($q_totals[3]['com2']) ?></td>
                                <td class="text-right" style="border-right: 2px solid #cbd5e1;">
                                    <?= formatUSD($q_totals[3]['total']) ?></td>
                                <!-- Q4 -->
                                <td></td>
                                <td class="text-right"><?= formatUSD($q_totals[4]['com1']) ?></td>
                                <td class="text-right"><?= formatUSD($q_totals[4]['com2']) ?></td>
                                <td class="text-right" style="border-right: 2px solid #cbd5e1;">
                                    <?= formatUSD($q_totals[4]['total']) ?></td>

                                <td class="text-right" style="background: #1e293b; color: #fff;">
                                    <?= formatUSD($grand_total_year) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>