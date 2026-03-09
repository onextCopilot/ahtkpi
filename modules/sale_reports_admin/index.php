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
        $am_name = $so['user_id'][1];
    } elseif (!empty($so['team_id']) && is_array($so['team_id'])) {
        $am_name = $so['team_id'][1];
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
$all_ams = array_unique(array_merge(array_keys($am_recognised), array_keys($am_invoiced)));
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

            if ($eff_level_id) {
                $stmt_kpi = $conn->prepare("SELECT kpi_quarter_vnd FROM sale_levels WHERE id = ?");
                if ($stmt_kpi) {
                    $stmt_kpi->bind_param("i", $eff_level_id);
                    $stmt_kpi->execute();
                    $kpi_res = $stmt_kpi->get_result();
                    if ($kr = $kpi_res->fetch_assoc()) {
                        $am_budgets[$am_name]["Q$q"] = (float) $kr['kpi_quarter_vnd'];
                    }
                }
            }
        }
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
        }

        table.revenue-table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
            font-size: 13px;
        }

        table.revenue-table th,
        table.revenue-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
        }

        table.revenue-table thead th {
            background-color: #0f172a;
            color: #fff;
            font-weight: 600;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        table.revenue-table tbody tr.section-header {
            background-color: #61A667;
            color: white;
            font-weight: bold;
        }

        table.revenue-table tbody tr.group-header {
            background-color: #e2e8f0;
            font-weight: 600;
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
                                <th rowspan="2">Category</th>
                                <th rowspan="2">Sub-Category / AM</th>
                                <th colspan="3">Q1</th>
                                <th colspan="3">Q2</th>
                                <th colspan="3">H1</th>
                                <th colspan="3">Q3</th>
                                <th colspan="3">Q4</th>
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
                                <th>Budget/KPI - Tốt</th>
                                <th>Budget/KPI - Trung Bình</th>
                                <th>Budget/KPI - Xấu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="section-header">
                                <td>Revenue</td>
                                <td colspan="16"></td>
                            </tr>

                            <!-- RECOGNISED REVENUE -->
                            <tr class="group-header">
                                <td style="background:#fff"></td>
                                <td>Recognised Revenue</td>
                                <td colspan="15"></td>
                            </tr>
                            <?php foreach ($all_ams as $am):
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
                                <tr>
                                    <td></td>
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
                                    <td class="text-right"><?= formatMoney($b_q4 * 0.8) ?></td>
                                    <td class="text-right"><?= formatMoney($b_q4 * 0.5) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- INVOICED REVENUE -->
                            <tr class="group-header">
                                <td style="background:#fff"></td>
                                <td>Invoiced Revenue</td>
                                <td colspan="15"></td>
                            </tr>
                            <?php foreach ($all_ams as $am):
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
                                <tr>
                                    <td></td>
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
                                    <td class="text-right"><?= formatMoney($b_q4 * 0.8) ?></td>
                                    <td class="text-right"><?= formatMoney($b_q4 * 0.5) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>