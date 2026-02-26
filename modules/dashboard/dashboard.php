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
$filter_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$filter_month = isset($_GET['month']) ? (int) $_GET['month'] : 0; // 0 for all

// Fetch Debt Data for Dashboard
require_once __DIR__ . '/../../libs/OdooAPI.php';
$odoo = new OdooAPI();
$total_debts = 0;
$total_paid_vnd = 0;
$total_unpaid_vnd = 0;
$teams_data = [];

$debt_conditions = ["1=1"];
if ($filter_year > 0) {
    $debt_conditions[] = "YEAR(d.invoice_date) = $filter_year";
}
if ($filter_month > 0) {
    $debt_conditions[] = "MONTH(d.invoice_date) = $filter_month";
}
$debt_where = implode(" AND ", $debt_conditions);

$res = $conn->query("SELECT d.amount, d.currency, d.payment_status, d.invoice_date, st.name as team_name FROM debts d LEFT JOIN sale_teams st ON d.sale_team_id = st.id WHERE $debt_where");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $total_debts++;
        $curr = $row['currency'] ?: 'USD';
        $t_name = $row['team_name'] ?: 'Undefined';
        $p_status = $row['payment_status'] ?: '';

        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
        $rate = $odoo->getRate($curr, $date);
        $vnd_value = ($rate > 0) ? ((float) $row['amount'] / $rate) : (float) $row['amount'];

        if (strcasecmp(trim($p_status), 'Paid') === 0) {
            $total_paid_vnd += $vnd_value;
            if (!isset($teams_data[$t_name]['paid']))
                $teams_data[$t_name]['paid'] = 0;
            $teams_data[$t_name]['paid'] += $vnd_value;
        } else {
            $total_unpaid_vnd += $vnd_value;
            if (!isset($teams_data[$t_name]['unpaid']))
                $teams_data[$t_name]['unpaid'] = 0;
            $teams_data[$t_name]['unpaid'] += $vnd_value;
        }
    }
}

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
$res_kpi = $conn->query("
    SELECT d.name AS dept_name, 
           COUNT(k.id) AS total_kpi,
           COALESCE(SUM(k.weight), 0) AS total_weight
    FROM departments d
    LEFT JOIN kpi_definitions k ON d.id = k.department_id AND k.year = $filter_year
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

                        <?php if ($filter_month > 0 || $filter_year != date('Y')): ?>
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
                            <p class="stat-number"><?php echo $total_debts; ?></p>
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