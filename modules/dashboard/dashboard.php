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

// Role-based dashboard: render the persona-specific view when one exists.
// Other personas (ceo / manager / member) fall through to the shared dashboard below.
require_once __DIR__ . '/lib/persona.php';
$persona = resolveDashboardPersona();
if ($persona === 'am_bd') {
    require __DIR__ . '/personas/am_bd.php';
    exit();
}

// Get total users count (Admin only)
$total_users = 0;
if ($role === 'admin') {
    $total_users_query = "SELECT COUNT(*) as total FROM users";
    $total_users_result = $conn->query($total_users_query);
    $total_users = $total_users_result->fetch_assoc()['total'] ?? 0;
}

// Filter parameters
$filter_year = isset($_GET['year']) ? (int) $_GET['year'] : 0;
$filter_month = isset($_GET['month']) ? (int) $_GET['month'] : 0;

// Access Control Logic (Consistent with All Debts module)
$can_view_all_debts = ($role === 'admin');
$user_teams = [];
$where_clauses = [];

if (!$can_view_all_debts) {
    $ut_res = $conn->prepare("SELECT team_id FROM user_sale_teams WHERE user_id = ?");
    $ut_res->bind_param("i", $user_id);
    $ut_res->execute();
    $ut_result = $ut_res->get_result();
    while ($r = $ut_result->fetch_assoc()) {
        $user_teams[] = $r['team_id'];
    }
    if (count($user_teams) > 0) {
        $in_teams = implode(',', $user_teams);
        $where_clauses[] = "d.sale_team_id IN ($in_teams)";
    } else {
        $where_clauses[] = "1=0";
    }
}

// Period Filtering
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(d.invoice_date) = $filter_year";
}
if ($filter_month > 0) {
    $where_clauses[] = "MONTH(d.invoice_date) = $filter_month";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch Debt Data for Dashboard
require_once __DIR__ . '/../../libs/OdooAPI.php';
$odoo = new OdooAPI();

$total_debts = 0;
$total_paid_vnd = 0;
$total_unpaid_vnd = 0;
$teams_data = [];

$res = $conn->query("SELECT d.amount, d.currency, d.payment_status, d.invoice_date, d.odoo_invoice_id, st.name as team_name 
                    FROM debts d 
                    LEFT JOIN sale_teams st ON d.sale_team_id = st.id
                    $where_sql");

$odoo_map = $odoo->getInvoiceMap();
$rateVndDefault = $odoo->getRate('VND', date('Y-m-d')) ?: 1.0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $amount = (float) $row['amount'];
        $curr = $row['currency'] ?: 'USD';
        $p_status = $row['payment_status'] ?: '';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
        $oid = $row['odoo_invoice_id'];
        $t_name = $row['team_name'] ?: 'Undefined';

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

        // Fallback to manual rate calculation using robust ratio method
        if ($vnd_value <= 0) {
            $rateSource = $odoo->getRate($curr, $date) ?: 1.0;
            // AHT TECH is a VND company: getRate(curr) = foreign_currency per 1 VND,
            // so amount / rate already yields VND directly. No vnd_multiplier needed.
            $vnd_value = ($rateSource > 0) ? ($amount / $rateSource) : $amount;
        }

        $total_debts++; // Count every record in the filtered result

        $is_paid = (strcasecmp(trim($p_status), 'Paid') === 0);
        if ($is_paid) {
            $total_paid_vnd += $vnd_value;
            if (!isset($teams_data[$t_name]['paid']))
                $teams_data[$t_name]['paid'] = 0;
            $teams_data[$t_name]['paid'] += $vnd_value;
        } else {
            // Any status other than 'Paid' is considered Pending
            $total_unpaid_vnd += $vnd_value;
            if (!isset($teams_data[$t_name]['unpaid']))
                $teams_data[$t_name]['unpaid'] = 0;
            $teams_data[$t_name]['unpaid'] += $vnd_value;
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

// Fetch KPI Data (Filtered for non-admins)
$kpi_dept_names = [];
$kpi_counts = [];
$kpi_weights = [];
$kpi_where = "WHERE 1=1";

if ($role !== 'admin') {
    // Only show user's department
    $res_u = $conn->query("SELECT department_id FROM users WHERE id = $user_id");
    $u_dept = $res_u->fetch_assoc()['department_id'] ?? 0;
    $kpi_where .= " AND d.id = $u_dept";
}

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
        $kpi_where
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

// Fetch Plan & Budgeting Data (Filtered by Owner if not admin)
$pb_where = "WHERE 1=1";
if ($role !== 'admin') {
    $pb_where .= " AND bs.owner = '" . $conn->real_escape_string($full_name) . "'";
}
// Add time filters if applicable
if ($filter_year > 0) $pb_where .= " AND bv.year = $filter_year";

$pb_res = $conn->query("
    SELECT 
        SUM(CASE WHEN bv.value_type = 'Plan' THEN bv.amount ELSE 0 END) as total_plan,
        SUM(CASE WHEN bv.value_type = 'Actual' THEN bv.amount ELSE 0 END) as total_actual
    FROM budget_values bv
    JOIN budget_structure bs ON bv.item_id = bs.id
    $pb_where
");
$pb_data = $pb_res->fetch_assoc();
$dashboard_total_plan = (float)($pb_data['total_plan'] ?? 0);
$dashboard_total_actual = (float)($pb_data['total_actual'] ?? 0);

// ── Break-even chart data ─────────────────────────────────────────────────────
$bev_year = (int)date('Y');
if ($filter_year > 0) $bev_year = $filter_year;

$conn->query("CREATE TABLE IF NOT EXISTS budget_quarterly_status (year INT, quarter INT, rec_status INT DEFAULT 0, inv_status INT DEFAULT 0, plan_status INT DEFAULT 0, PRIMARY KEY(year, quarter))");
$conn->query("SET @col_bev = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name='budget_quarterly_status' AND table_schema=DATABASE() AND column_name='plan_status')");
$conn->query("SET @sql_bev = IF(@col_bev=0,'ALTER TABLE budget_quarterly_status ADD COLUMN plan_status INT DEFAULT 0','SELECT 1')");
$conn->query("PREPARE stmt_bev FROM @sql_bev");
$conn->query("EXECUTE stmt_bev");

$bev_owner_filter = ($role !== 'admin') ? " AND s.owner = '" . $conn->real_escape_string($full_name) . "'" : "";

$bev_ps_map = [];
$res_bev_ps = $conn->query("SELECT quarter, plan_status FROM budget_quarterly_status WHERE year = $bev_year");
if ($res_bev_ps) while ($r = $res_bev_ps->fetch_assoc()) $bev_ps_map[$r['quarter']] = intval($r['plan_status']);

$bev_revenue = [0,0,0,0];
$bev_expense = [0,0,0,0];
$bev_actual  = [0,0,0,0];

for ($q = 1; $q <= 4; $q++) {
    $ps = $bev_ps_map[$q] ?? 2; if ($ps == 0) $ps = 2;
    $rec_col = ($ps==1?'rec_rev_good':($ps==3?'rec_rev_bad':'rec_rev_avg'));
    $inv_col = ($ps==1?'inv_rev_good':($ps==3?'inv_rev_bad':'inv_rev_avg'));
    $p_key   = ($ps==1?'planned_good':($ps==3?'planned_bad':'planned_avg'));

    // Revenue
    $res_r = $conn->query("SELECT SUM(IFNULL(s.$rec_col,0) + IFNULL(s.$inv_col,0)) as total 
                           FROM budget_structure s 
                           WHERE s.year=$bev_year AND s.quarter=$q AND s.type='item' $bev_owner_filter");
    $bev_revenue[$q-1] = floatval($res_r ? ($res_r->fetch_assoc()['total'] ?? 0) : 0);

    // Planned Expense
    $res_e = $conn->query("SELECT SUM(v.amount) as total 
                           FROM budget_values v 
                           JOIN budget_structure s ON v.item_id=s.id AND s.year=$bev_year AND s.quarter=$q 
                           WHERE v.year=$bev_year AND v.quarter=$q AND v.month=0 AND v.value_type='$p_key' $bev_owner_filter");
    $bev_expense[$q-1] = floatval($res_e ? ($res_e->fetch_assoc()['total'] ?? 0) : 0);

    // Actual Expense
    $months_q = [1=>[1,2,3],2=>[4,5,6],3=>[7,8,9],4=>[10,11,12]][$q];
    $m_in = implode(',', $months_q);
    $res_a = $conn->query("SELECT SUM(v.amount) as total 
                           FROM budget_values v 
                           JOIN budget_structure s ON v.item_id=s.id AND s.year=$bev_year AND s.quarter=$q 
                           WHERE v.year=$bev_year AND v.quarter=$q AND v.month IN($m_in) AND v.value_type IN('actual_salary','actual_other') $bev_owner_filter");
    $bev_actual[$q-1] = floatval($res_a ? ($res_a->fetch_assoc()['total'] ?? 0) : 0);
}

// Find BEP quarter
$bev_cum_r=0; $bev_cum_e=0; $bep_quarter=null;
for($q=0;$q<4;$q++) { $bev_cum_r+=$bev_revenue[$q]; $bev_cum_e+=$bev_expense[$q]; if($bep_quarter===null && $bev_cum_r>=$bev_cum_e && $bev_cum_e>0) $bep_quarter=$q+1; }

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
                    <?php if ($role === 'admin'): ?>
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
                            <h3>Total Employees</h3>
                            <p class="stat-number">
                                <?php echo $total_users; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

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
                            <h3>All Debts</h3>
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
                            <p style="font-size: 1.1rem; font-weight: 700; color: #16a34a; margin: 0;">
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
                            <p style="font-size: 1.1rem; font-weight: 700; color: #dc2626; margin: 0;">
                                <?php echo number_format($total_unpaid_vnd, 0, ',', '.'); ?> ₫
                            </p>
                        </div>
                    </div>

                    <!-- Plan & Budget Stats -->
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20"></path>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Your Total Plan</h3>
                            <p style="font-size: 1.1rem; font-weight: 700; color: #2563eb; margin: 0;">
                                <?php echo number_format($dashboard_total_plan, 0, ',', '.'); ?> ₫
                            </p>
                        </div>
                    </div>

                    <div class="stat-card" style="border-left: 4px solid #7c3aed;">
                        <div class="stat-icon purple">
                             <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Actual Spend</h3>
                            <p style="font-size: 1.1rem; font-weight: 700; color: #7c3aed; margin: 0;">
                                <?php echo number_format($dashboard_total_actual, 0, ',', '.'); ?> ₫
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

                <!-- Break-even Chart -->
                <div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom:24px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                        <div>
                            <h3 style="margin:0; color:#0f172a; font-size:1.05rem;">📈 Break-even Analysis — <?php echo $bev_year; ?></h3>
                            <p style="margin:4px 0 0; font-size:12px; color:#64748b;">Điểm giao nhau giữa doanh thu và chi phí kế hoạch lũy kế theo quý.</p>
                        </div>
                        <?php if ($bep_quarter): ?>
                        <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:10px 18px; text-align:center;">
                            <div style="font-size:10px; font-weight:700; color:#15803d; text-transform:uppercase; letter-spacing:.5px;">Điểm hòa vốn</div>
                            <div style="font-size:20px; font-weight:800; color:#15803d;">Quý <?php echo $bep_quarter; ?></div>
                        </div>
                        <?php else: ?>
                        <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:10px; padding:10px 18px; text-align:center;">
                            <div style="font-size:10px; font-weight:700; color:#b91c1c; text-transform:uppercase;">Chưa hòa vốn</div>
                            <div style="font-size:13px; font-weight:700; color:#b91c1c;"><?php echo $bev_year; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="chart-breakeven-dash"></div>
                    <div style="text-align:right; margin-top:8px;">
                        <a href="/plan-budgeting/report" style="font-size:12px; color:#3b82f6; text-decoration:none;">→ Xem báo cáo chi tiết</a>
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
                    xaxis: { categories: kpiDeptNames, labels: { show: false } },
                    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'],
                    tooltip: { theme: 'light', y: { formatter: val => val + "%" } }
                }).render();

                // Break-even Chart
                (function() {
                    const revenue = <?php echo json_encode(array_map('floatval', $bev_revenue)); ?>;
                    const expense = <?php echo json_encode(array_map('floatval', $bev_expense)); ?>;
                    const actual  = <?php echo json_encode(array_map('floatval', $bev_actual)); ?>;
                    const labels  = ['Q1','Q2','Q3','Q4'];
                    
                    const cumRev = revenue.map((_,i) => revenue.slice(0,i+1).reduce((a,b)=>a+b,0));
                    const cumExp = expense.map((_,i) => expense.slice(0,i+1).reduce((a,b)=>a+b,0));
                    const cumAct = actual.map((_,i)  => actual.slice(0,i+1).reduce((a,b)=>a+b,0));
                    
                    let bepAnnot = [];
                    for (let i=0;i<4;i++) {
                        if (cumRev[i]>=cumExp[i] && cumExp[i]>0 && (i===0||cumRev[i-1]<cumExp[i-1])) {
                            bepAnnot.push({ x: labels[i], borderColor:'#f59e0b', strokeDashArray:4,
                                label:{borderColor:'#f59e0b',style:{color:'#fff',background:'#f59e0b',fontWeight:700},text:'🎯 Hòa vốn'} });
                        }
                    }

                    new ApexCharts(document.querySelector('#chart-breakeven-dash'), {
                        series: [
                            { name: 'Doanh thu KH (lũy kế)', data: cumRev },
                            { name: 'Chi phí KH (lũy kế)', data: cumExp },
                            { name: 'Chi phí thực tế (lũy kế)', data: cumAct }
                        ],
                        chart: { type: 'line', height: 320, toolbar: { show: false }, zoom: { enabled: false } },
                        stroke: { curve: 'smooth', width: 1.5, dashArray: [0, 5, 0] },
                        colors: ['#10b981','#3b82f6','#ef4444'],
                        markers: { size: 3, strokeWidth: 0, hover: { size: 5 } },
                        xaxis: { categories: labels, axisBorder: { show: true } },
                        yaxis: { labels: { formatter: v => (v/1e9).toFixed(1)+' Tỷ' } },
                        annotations: { xaxis: bepAnnot },
                        tooltip: { shared: true, intersect: false, y: { formatter: val => val.toLocaleString('vi-VN')+' đ' } },
                        legend: { position: 'top', horizontalAlign: 'left' },
                        grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
                        fill: { type: 'solid', opacity: 1 }
                    }).render();
                })();
            </script>
    </div>
    </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
</body>

</html>
