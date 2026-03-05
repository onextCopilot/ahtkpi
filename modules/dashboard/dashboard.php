<?php
require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;

// Get total users count
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = $conn->query($total_users_query);
$total_users = $total_users_result->fetch_assoc()['total'];

// Filter parameters
// Filter parameters
$filter_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$filter_month = isset($_GET['month']) ? (int) $_GET['month'] : 0; // 0 for all

// Fetch Debt Data for Dashboard
require_once __DIR__ . '/../../libs/OdooAPI.php';
$odoo = new OdooAPI();

// ACL for data view
$isAdmin = ($role === 'admin');
$isAM = (isset($_SESSION['is_am_bd']) && $_SESSION['is_am_bd'] == 1);
$user_email = '';
if (!$isAdmin) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res_email = $stmt->get_result();
    $user_email = $res_email->fetch_assoc()['email'] ?? '';
}

$total_debts = 0;
$total_paid_vnd = 0;
$total_unpaid_vnd = 0;
$teams_data = [];
$processed_odoo_ids = [];

// 1. Fetch from Local Debts Table
$debt_where = "1=1";
if (!$isAdmin && !empty($user_email)) {
    // Only show debts where this user is the AM (approximated by email)
    // Most debts have 'am' as Name, but we can try to match or just show global if desired.
    // However, the user said numbers are "incorrect", likely because they expect personal view or better global view.
    // Let's keep it global for now as it's the main dashboard, but fix the calculation.
}

$res = $conn->query("SELECT d.amount, d.currency, d.payment_status, d.invoice_date, d.odoo_invoice_id, st.name as team_name
                    FROM debts d
                    LEFT JOIN sale_teams st ON d.sale_team_id = st.id");

$odoo_map = $odoo->getInvoiceMap();

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $curr = $row['currency'] ?: 'USD';
        $t_name = $row['team_name'] ?: 'Undefined';
        $p_status = $row['payment_status'] ?: '';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
        $inv_year = (int) date('Y', strtotime($date));
        $inv_month = (int) date('n', strtotime($date));
        $oid = $row['odoo_invoice_id'];

        // Convert to VND using Odoo ratio if available
        $vnd_value = 0;
        if (!empty($oid) && isset($odoo_map[$oid])) {
            $odoo_inv = $odoo_map[$oid];
            $odoo_total = (float) $odoo_inv['amount_total'];
            $odoo_signed = abs((float) $odoo_inv['amount_total_signed']);

            if ($odoo_total > 0) {
                // Apply proportional ratio
                $vnd_value = ((float) $row['amount']) * ($odoo_signed / $odoo_total);
            }
        }

        // Fallback to manual rate calculation
        if ($vnd_value <= 0) {
            $rate = $odoo->getRate($curr, $date);
            $vnd_value = ($rate > 0) ? ((float) $row['amount'] / $rate) : (float) $row['amount'];
        }

        // Tracking processed Odoo IDs (optional for stats here since we aren't merging anymore)
        if (!empty($oid)) {
            $processed_odoo_ids[] = $oid;
        }

        $is_paid = (strcasecmp(trim($p_status), 'Paid') === 0);

        // Logic: Paid counts in the selected period. Unpaid counts as long as it's not from the future.
        if ($is_paid) {
            if ($inv_year === $filter_year && ($filter_month === 0 || $inv_month === $filter_month)) {
                $total_paid_vnd += $vnd_value;
                if (!isset($teams_data[$t_name]['paid']))
                    $teams_data[$t_name]['paid'] = 0;
                $teams_data[$t_name]['paid'] += $vnd_value;
            }
        } else {
            // Pending: include all outstanding balance up to the end of the filtered period
            // Avoid future invoices if a specific month is selected
            $filter_date_limit = date('Y-m-t', strtotime("$filter_year-" . ($filter_month ?: 12) . "-01"));
            if ($date <= $filter_date_limit) {
                $total_unpaid_vnd += $vnd_value;
                if (!isset($teams_data[$t_name]['unpaid']))
                    $teams_data[$t_name]['unpaid'] = 0;
                $teams_data[$t_name]['unpaid'] += $vnd_value;
            }
        }
    }
}

// Calculate Total Debts as the sum of everything (matching "All Debts" overview)
$total_debts_vnd = $total_paid_vnd + $total_unpaid_vnd;

$chart_teams = [];
$chart_paid = [];
$chart_unpaid = [];
foreach ($teams_data as $t => $d) {
    $chart_teams[] = $t;
    $chart_paid[] = $d['paid'] ?? 0;
    $chart_unpaid[] = $d['unpaid'] ?? 0;
}

// Fetch KPI Data
$kpi_dept_names = [];
$kpi_counts = [];
$kpi_weights = [];
$kpi_join_condition = "d.id = k.department_id";
if ($filter_year > 0) {
    $kpi_join_condition .= " AND k.year = $filter_year";
}

$res_kpi = $conn->query("
        SELECT d.name AS dept_name, 
               COUNT(k.id) AS total_kpi,
               COALESCE(SUM(k.weight), 0) AS total_weight
        FROM departments d
        LEFT JOIN kpi_definitions k ON $kpi_join_condition
        GROUP BY d.id
        ORDER BY d.sort_order ASC, d.id ASC
    ");
if ($res_kpi) {
    while ($row = $res_kpi->fetch_assoc()) {
        $kpi_dept_names[] = $row['dept_name'];
        $kpi_counts[] = (int) $row['total_kpi'];
        $kpi_weights[] = (float) $row['total_weight'];
    }
}

// Fetch New Customers
$customer_period = isset($_GET['customer_period']) ? $_GET['customer_period'] : 'month'; // 'month', 'quarter', 'year'
$customers_result = [];
try {
    $customers_result = $odoo->getCustomers(10000);
} catch (Exception $e) {
}

$all_customers = $customers_result['customers'] ?? [];
$new_customers = [];

$cur_year = (int) date('Y');
$cur_month = (int) date('n');
$cur_quarter = ceil($cur_month / 3);

foreach ($all_customers as $c) {
    if (empty($c['create_date']))
        continue;
    $c_time = strtotime($c['create_date']);
    $c_year = (int) date('Y', $c_time);
    $c_month = (int) date('n', $c_time);
    $c_quarter = ceil($c_month / 3);

    $match = false;
    if ($customer_period === 'month') {
        if ($c_year === $cur_year && $c_month === $cur_month)
            $match = true;
    } elseif ($customer_period === 'quarter') {
        if ($c_year === $cur_year && $c_quarter === $cur_quarter)
            $match = true;
    } elseif ($customer_period === 'year') {
        if ($c_year === $cur_year)
            $match = true;
    }

    if ($match) {
        $new_customers[] = $c;
    }
}
usort($new_customers, function ($a, $b) {
    $timeA = strtotime($a['create_date']);
    $timeB = strtotime($b['create_date']);
    if ($timeA == $timeB)
        return 0;
    return ($timeA > $timeB) ? -1 : 1;
});

// Calculate pagination for new customers
$cpage = isset($_GET['cpage']) ? max(1, (int) $_GET['cpage']) : 1;
$climit = 10;
$total_new = count($new_customers);
$total_cpages = ceil($total_new / $climit);
if ($total_cpages > 0 && $cpage > $total_cpages)
    $cpage = $total_cpages;
$coffset = ($cpage - 1) * $climit;

$paged_customers = array_slice($new_customers, $coffset, $climit);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Management System</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $page_title = 'Dashboard';
            $page_subtitle = 'Welcome back, <strong>' . htmlspecialchars($full_name) . '</strong>';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

                <!-- Filter Form -->
                <div
                    style="background: white; padding: 15px 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; align-items: center; gap: 15px;">
                    <form method="GET" action=""
                        style="display: flex; gap: 15px; align-items: center; width: 100%; margin: 0;">
                        <strong style="color: #334155;">Filter Dashboard:</strong>

                        <select name="year"
                            style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; background: #f8fafc; font-weight: 500; color: #1e293b; cursor: pointer;">
                            <option value="0" <?php echo ($filter_year == 0) ? 'selected' : ''; ?>>All Years</option>
                            <?php
                            $start_year = 2024;
                            $cur_year = (int) date('Y') + 1;
                            for ($y = $cur_year; $y >= $start_year; $y--) {
                                $sel = ($y == $filter_year) ? 'selected' : '';
                                echo "<option value=\"$y\" $sel>Year $y</option>";
                            }
                            ?>
                        </select>

                        <select name="month"
                            style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; background: #f8fafc; font-weight: 500; color: #1e293b; cursor: pointer;">
                            <option value="0">All Months</option>
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $sel = ($m == $filter_month) ? 'selected' : '';
                                $m_pad = str_pad($m, 2, '0', STR_PAD_LEFT);
                                echo "<option value=\"$m\" $sel>Month $m_pad</option>";
                            }
                            ?>
                        </select>

                        <button type="submit"
                            style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s;">
                            Apply
                        </button>

                        <?php if ($filter_month > 0 || ($filter_year != date('Y') && isset($_GET['year']))): ?>
                            <a href="/dashboard"
                                style="color: #64748b; text-decoration: none; font-size: 14px; margin-left: auto;">Reset
                                Filters</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Total Users</h3>
                            <p class="stat-number">
                                <?php echo $total_users; ?>
                            </p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor"
                                    stroke-width="2" />
                                <path d="M3 9H21" stroke="currentColor" stroke-width="2" />
                                <path d="M9 21V9" stroke="currentColor" stroke-width="2" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Total Debts</h3>
                            <p class="stat-number"><?php echo number_format($total_debts_vnd, 0, ',', '.'); ?> ₫</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                                <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Total Paid (VND)</h3>
                            <p class="stat-number" style="font-size: 1.2rem; color: #16a34a;">
                                <?php echo number_format($total_paid_vnd, 0, ',', '.'); ?> ₫
                            </p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                                <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Pending (VND)</h3>
                            <p class="stat-number" style="font-size: 1.2rem; color: #dc2626;">
                                <?php echo number_format($total_unpaid_vnd, 0, ',', '.'); ?> ₫
                            </p>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 24px;">
                    <div
                        style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <h3
                            style="margin-top:0; color: #0f172a; font-size: 1.1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                            Global Debt Overview</h3>
                        <div id="chart-global-pie"></div>
                    </div>
                    <div
                        style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <h3
                            style="margin-top:0; color: #0f172a; font-size: 1.1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                            Debt by Sale Teams</h3>
                        <div id="chart-teams-bar"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                    <div
                        style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <h3
                            style="margin-top:0; color: #0f172a; font-size: 1.1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                            KPIs Count by Department (YTD)</h3>
                        <div id="chart-kpis-count"></div>
                    </div>
                    <div
                        style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <h3
                            style="margin-top:0; color: #0f172a; font-size: 1.1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                            Total KPI Weights by Department (YTD)</h3>
                        <div id="chart-kpis-weight"></div>
                    </div>
                </div>

                <!-- New Customers Section -->
                <div id="new-customers-section"
                    style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0; color: #0f172a; font-size: 1.1rem;">
                            New Customers
                        </h3>
                        <form method="GET" action="#new-customers-section"
                            style="margin: 0; display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                            <input type="hidden" name="month" value="<?php echo htmlspecialchars($filter_month); ?>">
                            <span style="font-size: 14px; color: #64748b;">Filter:</span>
                            <select name="customer_period" onchange="this.form.submit()"
                                style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; background: #f8fafc; font-weight: 500; color: #1e293b; cursor: pointer;">
                                <option value="month" <?php echo $customer_period === 'month' ? 'selected' : ''; ?>>This
                                    Month</option>
                                <option value="quarter" <?php echo $customer_period === 'quarter' ? 'selected' : ''; ?>>
                                    This Quarter</option>
                                <option value="year" <?php echo $customer_period === 'year' ? 'selected' : ''; ?>>This
                                    Year</option>
                            </select>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                    <th style="padding: 12px; font-weight: 600; color: #475569;">Customer Name</th>
                                    <th style="padding: 12px; font-weight: 600; color: #475569;">Email</th>
                                    <th style="padding: 12px; font-weight: 600; color: #475569;">Phone</th>
                                    <th style="padding: 12px; font-weight: 600; color: #475569;">Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($new_customers)): ?>
                                    <tr>
                                        <td colspan="4" style="padding: 20px; text-align: center; color: #64748b;">
                                            No new customers found for the selected period.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paged_customers as $c): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 12px; color: #0f172a; font-weight: 500;">
                                                <?php echo htmlspecialchars($c['name'] ?? ''); ?>
                                            </td>
                                            <td style="padding: 12px; color: #475569;">
                                                <?php echo htmlspecialchars($c['email'] ?: '-'); ?>
                                            </td>
                                            <td style="padding: 12px; color: #475569;">
                                                <?php echo htmlspecialchars($c['phone'] ?: ($c['mobile'] ?: '-')); ?>
                                            </td>
                                            <td style="padding: 12px; color: #475569;">
                                                <?php
                                                if (!empty($c['create_date'])) {
                                                    echo date('d/m/Y H:i', strtotime($c['create_date']));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_cpages > 1): ?>
                        <div
                            style="display: flex; justify-content: flex-end; align-items: center; margin-top: 15px; gap: 10px;">
                            <?php
                            $buildQuery = function ($p) use ($filter_year, $filter_month, $customer_period) {
                                return "?year=$filter_year&month=$filter_month&customer_period=$customer_period&cpage=$p#new-customers-section";
                            };
                            ?>
                            <?php if ($cpage > 1): ?>
                                <a href="<?php echo $buildQuery($cpage - 1); ?>"
                                    style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #475569; font-size: 14px; background: white;">&lsaquo;
                                    Prev</a>
                            <?php else: ?>
                                <span
                                    style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; font-size: 14px; background: #f8fafc; cursor: not-allowed;">&lsaquo;
                                    Prev</span>
                            <?php endif; ?>

                            <span style="font-size: 14px; color: #475569;">Page <strong><?php echo $cpage; ?></strong> of
                                <?php echo $total_cpages; ?></span>

                            <?php if ($cpage < $total_cpages): ?>
                                <a href="<?php echo $buildQuery($cpage + 1); ?>"
                                    style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #475569; font-size: 14px; background: white;">Next
                                    &rsaquo;</a>
                            <?php else: ?>
                                <span
                                    style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; color: #94a3b8; font-size: 14px; background: #f8fafc; cursor: not-allowed;">Next
                                    &rsaquo;</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                const formatVND = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
                const teamNames = <?php echo json_encode($chart_teams); ?>;
                const dataPaid = <?php echo json_encode($chart_paid); ?>;
                const dataNotPaid = <?php echo json_encode($chart_unpaid); ?>;

                new ApexCharts(document.querySelector("#chart-global-pie"), {
                    series: [<?php echo $total_paid_vnd; ?>, <?php echo $total_unpaid_vnd; ?>],
                    chart: { type: 'donut', height: 350 },
                    labels: ['Paid', 'Not Paid'],
                    colors: ['#22c55e', '#ef4444'],
                    dataLabels: { enabled: true, formatter: function (val, opts) { return formatVND(opts.w.globals.series[opts.seriesIndex]) } },
                    plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total Volume', formatter: function (w) { return formatVND(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } } }
                }).render();

                new ApexCharts(document.querySelector("#chart-teams-bar"), {
                    series: [{ name: 'Paid', data: dataPaid }, { name: 'Pending', data: dataNotPaid }],
                    chart: { type: 'bar', height: 350, stacked: true },
                    plotOptions: { bar: { borderRadius: 4, dataLabels: { position: 'top' } } },
                    dataLabels: { enabled: true, formatter: function (val) { return val > 0 ? (val / 1000000).toFixed(0) + "M" : "" }, style: { fontSize: '10px', colors: ["#304758"] } },
                    xaxis: { categories: teamNames },
                    colors: ['#22c55e', '#ef4444'],
                    yaxis: { labels: { formatter: val => (val / 1000000).toFixed(0) + 'M' } },
                    tooltip: { y: { formatter: val => formatVND(val) } }
                }).render();

                const kpiDeptNames = <?php echo json_encode($kpi_dept_names); ?>;
                const kpiCounts = <?php echo json_encode($kpi_counts); ?>;
                const kpiWeights = <?php echo json_encode($kpi_weights); ?>;

                new ApexCharts(document.querySelector("#chart-kpis-count"), {
                    series: [{ name: 'KPI Count', data: kpiCounts }],
                    chart: { type: 'bar', height: 350 },
                    plotOptions: { bar: { borderRadius: 4, distributed: true, dataLabels: { position: 'top' } } },
                    dataLabels: { enabled: true, style: { fontSize: '12px', colors: ["#304758"] } },
                    xaxis: { categories: kpiDeptNames, labels: { show: false } }, // Hide x-axis labels to save space since we have legend/tooltip
                    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'],
                    tooltip: { theme: 'light' }
                }).render();

                new ApexCharts(document.querySelector("#chart-kpis-weight"), {
                    series: [{ name: 'Total Weight', data: kpiWeights }],
                    chart: { type: 'bar', height: 350 },
                    plotOptions: { bar: { borderRadius: 4, distributed: true, dataLabels: { position: 'top' } } },
                    dataLabels: { enabled: true, formatter: function (val) { return val + "%" }, style: { fontSize: '12px', colors: ["#304758"] } },
                    xaxis: { categories: kpiDeptNames, labels: { show: false } }, // Hide x-axis labels to save space since we have legend/tooltip
                    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'],
                    tooltip: { theme: 'light', y: { formatter: val => val + "%" } }
                }).render();
            </script>
    </div>
    </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
</body>

</html>