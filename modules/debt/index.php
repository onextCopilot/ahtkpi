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

// Fetch all users for AM/BD selection
$all_users = [];
$user_res = $conn->query("SELECT id, full_name, email FROM users ORDER BY full_name ASC");
if ($user_res) {
    while ($ur = $user_res->fetch_assoc())
        $all_users[] = $ur;
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
        $am = $_POST['am'] ?? '';
        $client = $_POST['client_name'] ?? '';
        $project = $_POST['project_name'] ?? '';
        $milestone = $_POST['payment_milestone'] ?? '';
        $prod_date = !empty($_POST['expected_prod_date']) ? $_POST['expected_prod_date'] : NULL;
        $pay_date = !empty($_POST['expected_payment_date']) ? $_POST['expected_payment_date'] : NULL;
        $inv_class = $_POST['invoice_status_class'] ?? 'Khác';
        $amount = floatval($_POST['amount'] ?? 0);
        $pl = $_POST['pl_class'] ?? 'TB';
        $inv_stat = $_POST['invoice_status'] ?? '';
        $invoice_date_val = $_POST['invoice_date'] ?? null;
        if (empty($invoice_date_val))
            $invoice_date_val = null;
        $vat = $_POST['vat_invoice'] ?? '';
        $pay_stat = $_POST['payment_status'] ?? 'Not paid';
        $pay_month = $_POST['payment_month'] ?? '';
        $weekly = $_POST['weekly_update'] ?? '';
        $am_note = $_POST['am_notes'] ?? '';
        $del_note = $_POST['delivery_notes'] ?? '';
        $prod_stat = $_POST['production_status'] ?? '';
        $currency_val = $_POST['currency'] ?? 'USD';
        $sale_team_id = !empty($_POST['sale_team_id']) ? intval($_POST['sale_team_id']) : NULL;

        $am_email = $_SESSION['email'] ?? null;
        if (!$am_email && isset($_SESSION['user_id'])) {
            $uStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $uStmt->bind_param("i", $_SESSION['user_id']);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            if ($uRow = $uRes->fetch_assoc()) {
                $am_email = $uRow['email'];
                $_SESSION['email'] = $am_email;
            }
        }

        if ($_POST['action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO debts (company, am, am_email, sale_team_id, client_name, project_name, payment_milestone, expected_prod_date, expected_payment_date, invoice_status_class, amount, currency, invoice_status, vat_invoice, invoice_date, payment_status, payment_month, weekly_update, am_notes, delivery_notes, production_status, pl_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssissssssdsssssssssss", $company, $am, $am_email, $sale_team_id, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl);
        } else {
            // Edit
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE debts SET company=?, am=?, am_email=?, sale_team_id=?, client_name=?, project_name=?, payment_milestone=?, expected_prod_date=?, expected_payment_date=?, invoice_status_class=?, amount=?, currency=?, invoice_status=?, vat_invoice=?, invoice_date=?, payment_status=?, payment_month=?, weekly_update=?, am_notes=?, delivery_notes=?, production_status=?, pl_class=? WHERE id=?");
            $stmt->bind_param("sssissssssdsssssssssssi", $company, $am, $am_email, $sale_team_id, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl, $id);
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
        if ($role !== 'admin') {
            http_response_code(403);
            die('Forbidden: Only admins can delete debt records.');
        }
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM debts WHERE id=$id");
        header("Location: /debt");
        exit();
    }
}

// --- FILTERING & FETCH DATA ---
$where_clauses = [];

// ACL
$can_view_all_debts = isset($_SESSION['can_view_all_debts']) && $_SESSION['can_view_all_debts'] == 1;
if ($_SESSION['role'] === 'admin') {
    $can_view_all_debts = true;
}

$user_teams = [];
if (!$can_view_all_debts) {
    $ut_res = $conn->prepare("SELECT team_id FROM user_sale_teams WHERE user_id = ?");
    $ut_res->bind_param("i", $current_user_id);
    $ut_res->execute();
    $ut_result = $ut_res->get_result();
    while ($r = $ut_result->fetch_assoc()) {
        $user_teams[] = $r['team_id'];
    }

    if (count($user_teams) > 0) {
        $in_teams = implode(',', $user_teams);
        $where_clauses[] = "d.sale_team_id IN ($in_teams)";
    } else {
        $where_clauses[] = "1=0"; // No access to any team's data
    }
}

if (!empty($_GET['am'])) {
    $vals = is_array($_GET['am']) ? $_GET['am'] : [$_GET['am']];
    $clean = [];
    foreach ($vals as $v) if ($v !== '') $clean[] = "'" . $conn->real_escape_string($v) . "'";
    if (count($clean) > 0) $where_clauses[] = "d.am IN (" . implode(',', $clean) . ")";
}

if (!empty($_GET['invoice_status_class'])) {
    $vals = is_array($_GET['invoice_status_class']) ? $_GET['invoice_status_class'] : [$_GET['invoice_status_class']];
    $clean = [];
    $has_xanh = false;
    foreach ($vals as $v) {
        if ($v !== '') {
            $clean[] = "'" . $conn->real_escape_string($v) . "'";
            if ($v === 'Xanh') $has_xanh = true;
        }
    }
    if ($has_xanh) $clean[] = "'Tốt'";
    if (count($clean) > 0) $where_clauses[] = "d.invoice_status_class IN (" . implode(',', $clean) . ")";
}

if (!empty($_GET['status'])) {
    $vals = is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']];
    $clean = [];
    foreach ($vals as $v) {
        if ($v !== '') {
            if ($v === 'Draft') $clean[] = "'Not paid'";
            else $clean[] = "'" . $conn->real_escape_string($v) . "'";
        }
    }
    if (count($clean) > 0) $where_clauses[] = "d.payment_status IN (" . implode(',', $clean) . ")";
}

if (!empty($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $where_clauses[] = "(d.client_name LIKE '%$search%' OR d.project_name LIKE '%$search%' OR d.vat_invoice LIKE '%$search%')";
}

if (!empty($_GET['year'])) {
    $vals = is_array($_GET['year']) ? $_GET['year'] : [$_GET['year']];
    $y_ins = [];
    foreach ($vals as $v) {
        $y = intval($v);
        if ($y > 2000) $y_ins[] = $y;
    }
    if (count($y_ins) > 0) $where_clauses[] = "YEAR(d.invoice_date) IN (" . implode(',', $y_ins) . ")";
}

if (!empty($_GET['quarter'])) {
    $vals = is_array($_GET['quarter']) ? $_GET['quarter'] : [$_GET['quarter']];
    $m_ins = [];
    foreach ($vals as $v) {
        if ($v == 1) $m_ins = array_merge($m_ins, [1,2,3]);
        if ($v == 2) $m_ins = array_merge($m_ins, [4,5,6]);
        if ($v == 3) $m_ins = array_merge($m_ins, [7,8,9]);
        if ($v == 4) $m_ins = array_merge($m_ins, [10,11,12]);
    }
    if (count($m_ins) > 0) $where_clauses[] = "MONTH(d.invoice_date) IN (" . implode(',', $m_ins) . ")";
}

if (!empty($_GET['month'])) {
    $vals = is_array($_GET['month']) ? $_GET['month'] : [$_GET['month']];
    $m_ins = [];
    foreach ($vals as $v) {
        $m = intval($v);
        if ($m > 0 && $m <= 12) $m_ins[] = $m;
    }
    if (count($m_ins) > 0) $where_clauses[] = "MONTH(d.invoice_date) IN (" . implode(',', $m_ins) . ")";
}

if (!empty($_GET['week'])) {
    $vals = is_array($_GET['week']) ? $_GET['week'] : [$_GET['week']];
    $ors = [];
    foreach ($vals as $v) {
        $w = intval($v);
        if ($w > 0) {
            $ors[] = "d.weekly_update LIKE '%Tuần $w%' OR d.weekly_update LIKE '%tuần $w%' OR d.weekly_update = '$w' OR d.weekly_update LIKE '%W$w%' OR d.weekly_update LIKE '%w$w%'";
        }
    }
    if (count($ors) > 0) $where_clauses[] = "(" . implode(" OR ", $ors) . ")";
}

if (!empty($_GET['date_from'])) {
    $date_from = $conn->real_escape_string($_GET['date_from']);
    $where_clauses[] = "d.invoice_date >= '$date_from'";
}

if (!empty($_GET['date_to'])) {
    $date_to = $conn->real_escape_string($_GET['date_to']);
    $where_clauses[] = "d.invoice_date <= '$date_to'";
}

if (!empty($_GET['exp_pay_date_from'])) {
    $exp_from = $conn->real_escape_string($_GET['exp_pay_date_from']);
    $where_clauses[] = "d.expected_payment_date >= '$exp_from'";
}

if (!empty($_GET['exp_pay_date_to'])) {
    $exp_to = $conn->real_escape_string($_GET['exp_pay_date_to']);
    $where_clauses[] = "d.expected_payment_date <= '$exp_to'";
}

if (!empty($_GET['pay_month_from'])) {
    $pm_from = $conn->real_escape_string($_GET['pay_month_from']);
    $where_clauses[] = "STR_TO_DATE(CONCAT('01/', d.payment_month), '%d/%m/%Y') >= DATE_FORMAT('$pm_from', '%Y-%m-01')";
}

if (!empty($_GET['pay_month_to'])) {
    $pm_to = $conn->real_escape_string($_GET['pay_month_to']);
    $where_clauses[] = "STR_TO_DATE(CONCAT('01/', d.payment_month), '%d/%m/%Y') <= LAST_DAY('$pm_to')";
}

$selected_team = $_GET['team'] ?? 'dashboard'; // Default to dashboard as requested

// Define teams_to_show globally for JS and PHP tabs
$teams_to_show = [];
if ($_SESSION['role'] === 'admin') {
    $teams_to_show = array_map(function ($t) {
        return ['id' => $t['id'], 'name' => $t['name']];
    }, $all_teams);
    $teams_to_show[] = ['id' => 'undefined', 'name' => 'UNDEFINED TEAM'];
} else {
    // For non-admins, show only their assigned teams
    foreach ($all_teams as $t) {
        if (in_array($t['id'], $user_teams)) {
            $teams_to_show[] = ['id' => $t['id'], 'name' => $t['name']];
        }
    }
}

if (!$can_view_all_debts && !in_array($selected_team, ['dashboard', 'analytics'])) {
    if (!in_array($selected_team, $user_teams)) {
        $selected_team = 'dashboard';
    }
}

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

// New Aggregators for Charts
$aging_data = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$debtor_totals = [];
$am_performance = [];

$res = $conn->query("SELECT d.*, st.name as team_name 
                    FROM debts d 
                    LEFT JOIN sale_teams st ON d.sale_team_id = st.id 
                    $where_sql 
                    ORDER BY d.invoice_date DESC, d.am ASC, d.id DESC");

$odoo_map = $odoo->getInvoiceMap();

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $oid = (string)$row['odoo_invoice_id'];
        $odoo_inv = isset($odoo_map[$oid]) ? $odoo_map[$oid] : null;

        // Strict Filter for "Draft" status (checking Odoo state)
        $status_filter = $_GET['status'] ?? [];
        if (!is_array($status_filter)) $status_filter = [$status_filter];
        
        if (in_array('Draft', $status_filter)) {
            $odoo_state = ($odoo_inv && isset($odoo_inv['state'])) ? (string)$odoo_inv['state'] : '';
            if ($row['payment_status'] === 'Not paid' && !in_array('Not paid', $status_filter)) {
                if ($odoo_state !== 'draft') continue;
            }
        }

        $amount = (float) $row['amount'];
        $curr = $row['currency'] ?: 'USD';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
        $oid = $row['odoo_invoice_id'];

        // Convert to VND using Odoo exchange rate ratio if available
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
                    // Ratio is high, likely already in VND (e.g. 25850 for USD)
                    $vnd_value = $amount * $ratio;
                } else {
                    // Ratio is low, likely in intermediate currency (e.g. 1.0 for MYR, 4.7 for USD to MYR base)
                    $vnd_value = $amount * $ratio * $vnd_multiplier;
                }
            } else if ($odoo_total > 0 && $curr === 'VND') {
                $vnd_value = $amount;
            }
        }

        // Fallback to manual rate calculation if no odoo data or ratio is 0
        if ($vnd_value <= 0) {
            $rate = $odoo->getRate($curr, $date);
            // AHT TECH is a VND company: getRate(curr) = foreign_currency per 1 VND,
            // so amount / rate already yields VND directly. No vnd_multiplier needed.
            $vnd_value = ($rate > 0) ? ($amount / $rate) : $amount;
        }

        $total_amount_vnd += $vnd_value;
        if ($curr === 'USD') {
            $total_amount_usd += $amount;
        } else if ($vnd_value > 0) {
            // Use 24000 as a generic VND/USD fallback for the summary if no direct USD rate
            $total_amount_usd += ($vnd_value / 24000);
        }

        $row['amount_original'] = $amount;
        $row['currency_original'] = $curr;
        $row['formatted_original'] = formatCurrency($amount, $curr);

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
        $row['amount'] = $vnd_value;
        $row['currency'] = 'VND';

        // Capture Odoo invoice state (posted / draft / cancel) for display
        $row['odoo_state'] = ($odoo_inv && isset($odoo_inv['state'])) ? (string)$odoo_inv['state'] : '';

        $mKey = !empty($row['invoice_date']) ? date('m/Y', strtotime($row['invoice_date'])) : 'No Date';
        // Aging calculation (unpaid only)
        if (!$is_paid_status) {
            $inv_date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');
            $diff = (time() - strtotime($inv_date)) / (60 * 60 * 24);
            if ($diff <= 30) $aging_data['0-30'] += $vnd_value;
            else if ($diff <= 60) $aging_data['31-60'] += $vnd_value;
            else if ($diff <= 90) $aging_data['61-90'] += $vnd_value;
            else $aging_data['90+'] += $vnd_value;

            // Top Debtors (unpaid only)
            $client = $row['client_name'] ?: 'Khách hàng ẩn danh';
            $debtor_totals[$client] = ($debtor_totals[$client] ?? 0) + $vnd_value;
        }

        // AM Performance
        $amKey = !empty($row['am']) ? $row['am'] : 'No AM';
        if (!isset($am_performance[$amKey])) $am_performance[$amKey] = ['paid' => 0, 'total' => 0];
        if ($is_paid_status) $am_performance[$amKey]['paid'] += $vnd_value;
        $am_performance[$amKey]['total'] += $vnd_value;

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

// Sort and slice Top 5 Debtors
arsort($debtor_totals);
$top_debtors = array_slice($debtor_totals, 0, 5, true);

// Calculate AM Efficiency
$am_efficiency_labels = [];
$am_efficiency_values = [];
foreach ($am_performance as $am => $stats) {
    if ($stats['total'] > 0) {
        $am_efficiency_labels[] = $am;
        $am_efficiency_values[] = round(($stats['paid'] / $stats['total']) * 100, 1);
    }
}

// ── Break-even chart data ─────────────────────────────────────────────────────
$bev_year = (int)date('Y');
if (!empty($_GET['year']) && !is_array($_GET['year'])) $bev_year = (int)$_GET['year'];
else if (!empty($_GET['year']) && is_array($_GET['year'])) $bev_year = (int)$_GET['year'][0];

$conn->query("CREATE TABLE IF NOT EXISTS budget_quarterly_status (year INT, quarter INT, rec_status INT DEFAULT 0, inv_status INT DEFAULT 0, plan_status INT DEFAULT 0, PRIMARY KEY(year, quarter))");
$conn->query("SET @col_bev = (SELECT COUNT(*) FROM information_schema.columns WHERE table_name='budget_quarterly_status' AND table_schema=DATABASE() AND column_name='plan_status')");
$conn->query("SET @sql_bev = IF(@col_bev=0,'ALTER TABLE budget_quarterly_status ADD COLUMN plan_status INT DEFAULT 0','SELECT 1')");
$conn->query("PREPARE stmt_bev FROM @sql_bev");
$conn->query("EXECUTE stmt_bev");

$bev_owner_filter = "";
if (!empty($_GET['am'])) {
    $vals = is_array($_GET['am']) ? $_GET['am'] : [$_GET['am']];
    $clean = [];
    foreach ($vals as $v) if ($v !== '') $clean[] = "'" . $conn->real_escape_string($v) . "'";
    if (count($clean) > 0) $bev_owner_filter .= " AND s.owner IN (" . implode(',', $clean) . ")";
} else if ($role !== 'admin') {
    $bev_owner_filter .= " AND s.owner = '" . $conn->real_escape_string($full_name) . "'";
}

// Sale Team filter mapping (if applicable to budget_structure)
if (!empty($_GET['team'])) {
    $vals = is_array($_GET['team']) ? $_GET['team'] : [$_GET['team']];
    $clean_teams = [];
    foreach ($vals as $v) {
        $t_id = intval($v);
        // Map team ID to name/division if needed, or if budget_structure has team_id
        $res_t = $conn->query("SELECT name FROM sale_teams WHERE id = $t_id");
        if ($res_t && $rt = $res_t->fetch_assoc()) {
            $clean_teams[] = "'" . $conn->real_escape_string($rt['name']) . "'";
        }
    }
    if (count($clean_teams) > 0) {
        $bev_owner_filter .= " AND s.division IN (" . implode(',', $clean_teams) . ")";
    }
}

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

    // Improved query with IFNULL and consistent filtering
    $res_r = $conn->query("SELECT SUM(IFNULL(s.$rec_col,0) + IFNULL(s.$inv_col,0)) as total 
                           FROM budget_structure s 
                           WHERE s.year=$bev_year AND s.quarter=$q AND s.type='item' $bev_owner_filter");
    $bev_revenue[$q-1] = floatval($res_r ? ($res_r->fetch_assoc()['total'] ?? 0) : 0);

    $res_e = $conn->query("SELECT SUM(v.amount) as total 
                           FROM budget_values v 
                           JOIN budget_structure s ON v.item_id=s.id AND s.year=$bev_year AND s.quarter=$q 
                           WHERE v.year=$bev_year AND v.quarter=$q AND v.month=0 AND v.value_type='$p_key' $bev_owner_filter");
    $bev_expense[$q-1] = floatval($res_e ? ($res_e->fetch_assoc()['total'] ?? 0) : 0);

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

// Helper for formatting currency
function formatCurrency($amount, $curr = 'USD')
{
    if ($curr === 'VND') {
        return number_format($amount, 0, ',', '.') . ' đ';
    }
    if ($curr === 'MYR' || $curr === 'RM') {
        return number_format($amount, 2, ',', '.') . ' RM';
    }
    if ($curr === 'SGD') {
        return 'S$' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'EUR') {
        return '€' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'JPY') {
        return '¥' . number_format($amount, 0, ',', '.');
    }
    if ($curr === 'KRW') {
        return '₩' . number_format($amount, 0, ',', '.');
    }
    if ($curr === 'GBP') {
        return '£' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'AUD') {
        return 'A$' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'CAD') {
        return 'C$' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'HKD') {
        return 'HK$' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'TWD') {
        return 'NT$' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'THB') {
        return number_format($amount, 2, ',', '.') . ' ฿';
    }
    if ($curr === 'INR') {
        return '₹' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'CNY') {
        return 'CN¥' . number_format($amount, 2, ',', '.');
    }
    if ($curr === 'CHF') {
        return 'CHF ' . number_format($amount, 2, ',', '.');
    }
    return '$' . number_format($amount, 2, ',', '.');
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

function is_filter_selected($key_name, $val) {
    if (!isset($_GET[$key_name])) return false;
    $v = $_GET[$key_name];
    if (is_array($v)) return in_array((string)$val, $v);
    return (string)$v === (string)$val;
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debt Management</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tom Select -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            flex: 1;
            overflow-x: auto;
            overflow-y: auto;
            position: relative;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            /* Softer shadow */
        }

        table.debt-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            /* Slightly larger text */
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            color: #334155;
        }

        /* Sticky Header */
        table.debt-table thead th {
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            /* Modern light gray header */
            color: #475569;
            /* Gray text */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            z-index: 10;
            white-space: normal;
            line-height: 1.4;
            vertical-align: middle;
            min-width: 120px;
        }

        table.debt-table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #1e293b;
            transition: background-color 0.15s;
        }

        /* Subtle Striping */
        table.debt-table tbody tr:nth-child(even) td {
            background-color: #fafafa;
        }

        table.debt-table tbody tr:hover td {
            background-color: #f1f5f9;
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
            font-variant-numeric: tabular-nums;
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

        .btn-team-detail {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: rgba(148, 163, 184, 0.1);
            color: #64748b;
            font-size: 10px;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
            margin-left: 2px;
        }

        .team-tab.active .btn-team-detail {
            background: #f1f5f9;
            color: #2563eb;
            border-color: #cbd5e1;
        }

        .btn-team-detail:hover {
            background: #2563eb !important;
            color: white !important;
        }

        /* Detail Sidebar (Team Info) */
        .detail-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 3000;
        }

        .detail-sidebar {
            position: fixed;
            top: 0;
            right: -720px;
            width: 720px;
            height: 100%;
            background: white;
            z-index: 3001;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .detail-sidebar.open {
            right: 0;
        }

        .detail-sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }

        .detail-sidebar-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-sidebar-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
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
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
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

        /* Group Headers */
        .group-header td {
            background-color: #e2e8f0 !important;
            font-weight: 700;
            color: #0f172a;
            padding: 12px 16px !important;
            border-top: none;
            border-bottom: 2px solid #cbd5e1;
            font-size: 13px;
        }

        .group-header-am td {
            background-color: #f8fafc !important;
            font-weight: 600;
            color: #475569;
            padding: 10px 16px 10px 24px !important;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
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

        .ts-wrapper.filter-select {
            padding: 0 !important;
        }
        .ts-wrapper.filter-select .ts-control {
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            min-height: 38px;
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

        /* Filter Sidebar Drawer */
        .filter-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 2000;
            transition: opacity 0.3s;
        }

        .filter-sidebar {
            position: fixed;
            top: 0;
            right: -350px;
            width: 350px;
            height: 100%;
            background: white;
            z-index: 2001;
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .filter-sidebar.open {
            right: 0;
        }

        .filter-sidebar-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }

        .filter-sidebar-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #0f172a;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-sidebar-body {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }

        .filter-sidebar-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 12px;
        }

        .filter-item-label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-sidebar .filter-select,
        .filter-sidebar .search-input {
            width: 100%;
            margin-bottom: 20px;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #cbd5e1;
        }

        .btn-filter-toggle {
            background: white;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            position: relative;
        }

        .btn-filter-toggle:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #f0f7ff;
        }

        .filter-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #2563eb;
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 0 0 2px white;
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

        .btn-delete-row {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-delete-row:hover {
            background-color: #fee2e2;
            color: #b91c1c;
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
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <?php
                        $active_chips = [];
                        $chip_labels = [
                            'am' => 'AM',
                            'status' => 'Trạng thái',
                            'invoice_status_class' => 'Phân loại',
                            'year' => 'Năm',
                            'quarter' => 'Quý',
                            'month' => 'Tháng',
                            'week' => 'Tuần',
                            'date_from' => 'Invoice Date Từ',
                            'date_to' => 'Invoice Date Đến',
                            'exp_pay_date_from' => 'Exp. Pay TT Từ',
                            'exp_pay_date_to' => 'Exp. Pay TT Đến',
                            'pay_month_from' => 'Paid Date Từ',
                            'pay_month_to' => 'Paid Date Đến',
                            'q' => 'Tìm kiếm'
                        ];

                        foreach ($chip_labels as $key => $label) {
                            if (!empty($_GET[$key])) {
                                $val = $_GET[$key];
                                $val_str = '';
                                if (is_array($val)) {
                                    $mapped = [];
                                    foreach($val as $v) {
                                        if ($key === 'month') {
                                            $mapped[] = date('m', mktime(0, 0, 0, (int)$v, 1));
                                        } else if ($key === 'quarter') {
                                            $mapped[] = 'Q' . $v;
                                        } else {
                                            $mapped[] = $v;
                                        }
                                    }
                                    $val_str = implode(', ', $mapped);
                                } else {
                                    if ($key === 'month') {
                                        $val_str = date('m', mktime(0, 0, 0, (int)$val, 1));
                                    } else if ($key === 'quarter') {
                                        $val_str = 'Q' . $val;
                                    } else if ($key === 'date_from' || $key === 'date_to' || $key === 'exp_pay_date_from' || $key === 'exp_pay_date_to' || $key === 'pay_month_from' || $key === 'pay_month_to') {
                                        $val_str = date('d/m/Y', strtotime($val));
                                    } else {
                                        $val_str = $val;
                                    }
                                }

                                // Build reset URL for this specific filter
                                $r_params = $_GET;
                                unset($r_params[$key]);
                                $reset_url = "?" . http_build_query($r_params);

                                echo '
                                <div style="display:flex; align-items:center; gap:8px; background:#f0f9ff; padding:4px 12px; border-radius:20px; font-size:11px; border:1px solid #bae6fd; color:#0369a1; white-space:nowrap;">
                                    <span>' . $label . ': <strong>' . htmlspecialchars($val_str) . '</strong></span>
                                    <a href="' . $reset_url . '" style="color:#38bdf8; text-decoration:none; font-size:16px; font-weight:bold; line-height:1;">&times;</a>
                                </div>';
                            }
                        }
                        ?>
                    </div>

                    <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                        <div class="total-badge">
                            Total: <?php echo formatVND($total_amount_vnd); ?>
                        </div>
                        
                        <button class="btn-filter-toggle" onclick="toggleFilterSidebar()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                            <span>Bộ lọc</span>
                            <?php 
                            $active_filters = 0;
                            $filter_params = ['am', 'status', 'invoice_status_class', 'year', 'quarter', 'month', 'week', 'date_from', 'date_to', 'exp_pay_date_from', 'exp_pay_date_to', 'pay_month_from', 'pay_month_to', 'q']; 
                            foreach($filter_params as $p) {
                                if(!empty($_GET[$p])) $active_filters++;
                            }
                            if($active_filters > 0) echo "<span class='filter-badge'>$active_filters</span>";
                            ?>
                        </button>

                        <?php
                        $exp_qs = http_build_query(array_filter([
                            'year' => $_GET['year'] ?? '', 'month' => $_GET['month'] ?? '', 'status' => $_GET['status'] ?? '',
                        ]));
                        ?>
                        <a class="btn-filter-toggle" href="/api/export/debts.php<?php echo $exp_qs ? ('?' . htmlspecialchars($exp_qs)) : ''; ?>" title="Xuất Excel" style="text-decoration:none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            <span>Xuất Excel</span>
                        </a>

                        <a class="btn-filter-toggle" href="/api/export/debts_pdf.php<?php echo $exp_qs ? ('?' . htmlspecialchars($exp_qs)) : ''; ?>" target="_blank" title="Xuất PDF" style="text-decoration:none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            <span>Xuất PDF</span>
                        </a>

                        <button class="btn-add" onclick="openModal()" style="display: none;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Thêm mới
                        </button>
                    </div>
                </div>

                <!-- Filter Sidebar Drawer -->
                <div class="filter-sidebar-overlay" id="filterOverlay" onclick="toggleFilterSidebar()"></div>
                <div class="filter-sidebar" id="filterSidebar">
                    <div class="filter-sidebar-header">
                        <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Bộ lọc dữ liệu</h3>
                        <span class="close" onclick="toggleFilterSidebar()">&times;</span>
                    </div>
                    <div class="filter-sidebar-body">
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="team" value="<?php echo htmlspecialchars($selected_team); ?>">
                            
                            <label class="filter-item-label">Tìm kiếm nhanh</label>

                            <label class="filter-item-label">Người quản lý (AM)</label>
                            <select name="am[]" multiple class="filter-select ts-multiselect" placeholder="Tất cả AM">
                                <option value="">Tất cả AM</option>
                                <?php foreach ($am_list as $am_name): ?>
                                    <option value="<?php echo htmlspecialchars($am_name); ?>" <?php echo is_filter_selected('am', $am_name) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($am_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label class="filter-item-label">Trạng thái thanh toán</label>
                            <select name="status[]" multiple class="filter-select ts-multiselect" placeholder="Tất cả trạng thái">
                                <option value="">Tất cả trạng thái</option>
                                <option value="Not paid" <?php echo is_filter_selected('status', 'Not paid') ? 'selected' : ''; ?>>Not paid</option>
                                <option value="Paid" <?php echo is_filter_selected('status', 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="Draft" <?php echo is_filter_selected('status', 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            </select>

                            <label class="filter-item-label">Phân loại Invoice</label>
                            <select name="invoice_status_class[]" multiple class="filter-select ts-multiselect" placeholder="Tất cả phân loại">
                                <option value="">Tất cả phân loại</option>
                                <option value="Trắng" <?php echo is_filter_selected('invoice_status_class', 'Trắng') ? 'selected' : ''; ?>>Trắng</option>
                                <option value="Xanh" <?php echo is_filter_selected('invoice_status_class', 'Xanh') ? 'selected' : ''; ?>>Xanh</option>
                                <option value="Tím" <?php echo is_filter_selected('invoice_status_class', 'Tím') ? 'selected' : ''; ?>>Tím</option>
                                <option value="Đỏ" <?php echo is_filter_selected('invoice_status_class', 'Đỏ') ? 'selected' : ''; ?>>Đỏ</option>
                                <option value="PP" <?php echo is_filter_selected('invoice_status_class', 'PP') ? 'selected' : ''; ?>>PP</option>
                                <option value="Draft" <?php echo is_filter_selected('invoice_status_class', 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="Done" <?php echo is_filter_selected('invoice_status_class', 'Done') ? 'selected' : ''; ?>>Done</option>
                                <option value="Chưa xác định" <?php echo is_filter_selected('invoice_status_class', 'Chưa xác định') ? 'selected' : ''; ?>>Chưa xác định</option>
                            </select>

                            <label class="filter-item-label">Khoảng thời gian ( Invoice Date)</label>
                            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:20px;">
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px;">
                                    <span style="font-size:12px; color:#94a3b8; width:30px;">Từ:</span>
                                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>" style="border:none; background:transparent; font-size:13px; outline:none; flex:1;">
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px;">
                                    <span style="font-size:12px; color:#94a3b8; width:30px;">Đến:</span>
                                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>" style="border:none; background:transparent; font-size:13px; outline:none; flex:1;">
                                </div>
                            </div>

                            <label class="filter-item-label">Exp. Pay Date (Ngày TT dự kiến)</label>
                            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:20px;">
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px;">
                                    <span style="font-size:12px; color:#94a3b8; width:30px;">Từ:</span>
                                    <input type="date" name="exp_pay_date_from" value="<?php echo htmlspecialchars($_GET['exp_pay_date_from'] ?? ''); ?>" style="border:none; background:transparent; font-size:13px; outline:none; flex:1;">
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px;">
                                    <span style="font-size:12px; color:#94a3b8; width:30px;">Đến:</span>
                                    <input type="date" name="exp_pay_date_to" value="<?php echo htmlspecialchars($_GET['exp_pay_date_to'] ?? ''); ?>" style="border:none; background:transparent; font-size:13px; outline:none; flex:1;">
                                </div>
                            </div>

                            <label class="filter-item-label">Paid Date ( Thời gian Invoice TT)</label>
                            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:20px;">
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px;">
                                    <span style="font-size:12px; color:#94a3b8; width:30px;">Từ:</span>
                                    <input type="date" name="pay_month_from" value="<?php echo htmlspecialchars($_GET['pay_month_from'] ?? ''); ?>" style="border:none; background:transparent; font-size:13px; outline:none; flex:1;">
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:8px 10px;">
                                    <span style="font-size:12px; color:#94a3b8; width:30px;">Đến:</span>
                                    <input type="date" name="pay_month_to" value="<?php echo htmlspecialchars($_GET['pay_month_to'] ?? ''); ?>" style="border:none; background:transparent; font-size:13px; outline:none; flex:1;">
                                </div>
                            </div>

                            <label class="filter-item-label">Thời gian (Theo Quý/Tháng)</label>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px;">
                                <select name="year[]" multiple class="filter-select ts-multiselect" placeholder="Năm" style="margin-bottom:0;">
                                    <option value="">Năm</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                        $sel = is_filter_selected('year', $y) ? 'selected' : '';
                                        echo "<option value='$y' $sel>$y</option>";
                                    }
                                    ?>
                                </select>
                                <select name="quarter[]" multiple class="filter-select ts-multiselect" placeholder="Quý" style="margin-bottom:0;">
                                    <option value="">Quý</option>
                                    <option value="1" <?php echo is_filter_selected('quarter', '1') ? 'selected' : ''; ?>>Q1</option>
                                    <option value="2" <?php echo is_filter_selected('quarter', '2') ? 'selected' : ''; ?>>Q2</option>
                                    <option value="3" <?php echo is_filter_selected('quarter', '3') ? 'selected' : ''; ?>>Q3</option>
                                    <option value="4" <?php echo is_filter_selected('quarter', '4') ? 'selected' : ''; ?>>Q4</option>
                                </select>
                            </div>

                            <select name="month[]" multiple class="filter-select ts-multiselect" placeholder="Chọn tháng">
                                <option value="">Chọn tháng</option>
                                <?php
                                for ($m = 1; $m <= 12; $m++) {
                                    $sel = is_filter_selected('month', $m) ? 'selected' : '';
                                    $mName = date('F', mktime(0, 0, 0, $m, 1));
                                    echo "<option value='$m' $sel>$mName</option>";
                                }
                                ?>
                            </select>

                             <label class="filter-item-label">Cập nhật Tuần</label>
                            <select name="week[]" multiple class="filter-select ts-multiselect" placeholder="Tất cả tuần" style="margin-bottom:20px;">
                                <option value="">Tất cả tuần</option>
                                <?php
                                for ($w = 1; $w <= 5; $w++) {
                                    $sel = is_filter_selected('week', $w) ? 'selected' : '';
                                    echo "<option value='$w' $sel>Tuần $w</option>";
                                }
                                ?>
                            </select>

                            <button type="submit" style="display:none;"></button>
                        </form>
                    </div>
                    <div class="filter-sidebar-footer">
                        <button type="button" class="btn-cancel" style="flex:1;" onclick="window.location.href='?team=<?php echo htmlspecialchars($selected_team); ?>'">Xóa hết</button>
                        <button type="button" class="btn-submit" style="flex:1;" onclick="document.getElementById('filterForm').submit()">Áp dụng</button>
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
                    // Get both sale_team_id and odoo_invoice_id to correctly filter by Odoo status when needed
                    $c_res = $conn->query("SELECT d.sale_team_id, d.odoo_invoice_id FROM debts d $cw_sql");
                    if ($c_res) {
                        while ($cr = $c_res->fetch_assoc()) {
                            $k = $cr['sale_team_id'] ?? 'undefined';
                            
                            // If strict "Draft" filter is active, we must match the Odoo state check used in the main loop
                            if (isset($_GET['status']) && $_GET['status'] === 'Draft') {
                                $oid = (string)$cr['odoo_invoice_id'];
                                $odoo_inv = isset($odoo_map[$oid]) ? $odoo_map[$oid] : null;
                                $odoo_state = ($odoo_inv && isset($odoo_inv['state'])) ? (string)$odoo_inv['state'] : '';
                                if ($odoo_state !== 'draft') {
                                    continue;
                                }
                            }
                            
                            if (!isset($counts[$k])) $counts[$k] = 0;
                            $counts[$k]++;
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
                        if (!$can_view_all_debts && !in_array($t['id'], $user_teams)) {
                            continue;
                        }
                        $tabs_data[$t['id']] = [
                            'id' => $t['id'],
                            'label' => $t['name'],
                            'url' => getTabUrl($t['id']),
                            'count' => $counts[$t['id']] ?? 0
                        ];
                    }

                    // 4. Undefined
                    if ($can_view_all_debts) {
                        $tabs_data['undefined'] = [
                            'id' => 'undefined',
                            'label' => 'Undefined',
                            'url' => getTabUrl('undefined'),
                            'count' => $counts['undefined'] ?? 0
                        ];
                    }

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
                            
                            <?php if (!in_array($tab['id'], ['dashboard', 'analytics', 'all'])): ?>
                            <div class="btn-team-detail" title="Xem chi tiết Team" onclick="toggleTeamDetail(event, '<?php echo $tab['id']; ?>', '<?php echo htmlspecialchars($tab['label']); ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"></path><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            </div>
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

                            <!-- Global Aging and Cash Flow Charts (Stacked by Team) -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:32px; margin-top:16px;">
                                <div style="background: white; border: 1px solid #e8e8e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h5 style="margin:0 0 16px 0; font-size:14px; color:#e11d48; font-weight:700; border-left:4px solid #e11d48; padding-left:12px; text-transform:uppercase; letter-spacing:0.5px;">Phân tích Tuổi nợ (Aging Report) - Theo Team</h5>
                                    <div style="height:380px;"><canvas id="globalAgingChart"></canvas></div>
                                </div>
                                <div style="background: white; border: 1px solid #e8e8e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h5 style="margin:0 0 16px 0; font-size:14px; color:#2563eb; font-weight:700; border-left:4px solid #2563eb; padding-left:12px; text-transform:uppercase; letter-spacing:0.5px;">Dòng tiền dự kiến về - Theo Team</h5>
                                    <div style="height:380px;"><canvas id="globalFlowChart"></canvas></div>
                                </div>
                            </div>
                            
                            <!-- New Debt Analytics Row -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px; margin-top:16px;">
                                <div style="background: white; border: 1px solid #e8e8e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h5 style="margin:0 0 16px 0; font-size:13px; color:#262626; font-weight:700; border-left:4px solid #1890ff; padding-left:12px; text-transform:uppercase;">Tỷ lệ Tuổi nợ (Phải thu)</h5>
                                    <div id="chart-debt-aging-pie" style="min-height: 280px;"></div>
                                </div>
                                <div style="background: white; border: 1px solid #e8e8e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <h5 style="margin:0 0 16px 0; font-size:13px; color:#262626; font-weight:700; border-left:4px solid #f5222d; padding-left:12px; text-transform:uppercase;">Top 5 Khách hàng nợ</h5>
                                    <div id="chart-top-debtors-bar" style="min-height: 280px;"></div>
                                </div>
                            </div>
                            
                            <div style="background: white; border: 1px solid #e8e8e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom:32px;">
                                <h5 style="margin:0 0 16px 0; font-size:13px; color:#262626; font-weight:700; border-left:4px solid #52c41a; padding-left:12px; text-transform:uppercase;">Hiệu suất thu hồi AM (%)</h5>
                                <div id="chart-am-efficiency-bar" style="min-height: 280px;"></div>
                            </div>

                            <!-- Break-even Chart Section -->
                            <div style="background:white; border: 1px solid #e8e8e8; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 32px; margin-top: 16px;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                                    <div>
                                        <h5 style="margin:0; color:#0f172a; font-size:14px; font-weight:700; border-left:4px solid #f59e0b; padding-left:12px; text-transform:uppercase; letter-spacing:0.5px;">📈 Break-even Analysis — <?php echo $bev_year; ?></h5>
                                        <p style="margin:4px 0 0 16px; font-size:12px; color:#64748b;">Điểm giao nhau giữa doanh thu và chi phí kế hoạch lũy kế theo quý.</p>
                                    </div>
                                    <?php if ($bep_quarter): ?>
                                    <div style="background:#fff7e6; border:1px solid #ffd591; border-radius:8px; padding:8px 20px; text-align:center;">
                                        <div style="font-size:10px; font-weight:700; color:#d48806; text-transform:uppercase; letter-spacing:.5px;">Điểm hòa vốn</div>
                                        <div style="font-size:18px; font-weight:800; color:#d48806;">Quý <?php echo $bep_quarter; ?></div>
                                    </div>
                                    <?php else: ?>
                                    <div style="background:#fff1f0; border:1px solid #ffa39e; border-radius:8px; padding:8px 20px; text-align:center;">
                                        <div style="font-size:10px; font-weight:700; color:#cf1322; text-transform:uppercase;">Chưa hòa vốn</div>
                                        <div style="font-size:14px; font-weight:700; color:#cf1322;"><?php echo $bev_year; ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div id="chart-breakeven-debt" style="min-height: 350px;"></div>
                                <div style="text-align:right; margin-top:12px;">
                                    <a href="/plan-budgeting/report" style="font-size:12px; color:#1890ff; text-decoration:none; font-weight:500;">→ Xem báo cáo ngân sách chi tiết</a>
                                </div>
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

                        <?php if (count($teams_to_show) > 0): ?>
                            <div class="dashboard-grid"
                                style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                <?php
                                foreach ($teams_to_show as $teamInfo):
                                    $tid = $teamInfo['id'];
                                    $data = $dashboardData[$tid] ?? [];
                                    ?>
                                    <div style="background: #fff; border: 1px solid #e8e8e8; border-radius: 8px; transition: all 0.3s ease; display:flex; flex-direction:column;"
                                        onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.borderColor='#91d5ff';"
                                        onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e8e8e8';">
                                        <div
                                            style="padding: 16px 20px; border-bottom: 1px solid #bae7ff; display: flex; justify-content: space-between; align-items: center; background: #e6f7ff; border-radius: 8px 8px 0 0;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="width: 8px; height: 8px; border-radius: 50%; background: #1890ff;">
                                                </div>
                                                <span
                                                    style="font-weight: 700; font-size: 14px; color: #002329; text-transform: uppercase;"><?php echo htmlspecialchars($teamInfo['name']); ?></span>
                                            </div>
                                            <span
                                                style="background: #fff; color: #0050b3; border: 1px solid #91d5ff; font-size: 11px; padding: 2px 10px; border-radius: 10px; font-weight: 600;">TEAM</span>
                                        </div>
                                        <div style="padding: 16px;">
                                            <!-- Table Section -->
                                            <div style="background: #fafafa; border-radius: 8px; padding: 12px; border: 1px solid #f0f0f0;">
                                                <h6 style="margin:0 0 12px 0; color:#1e293b; font-size:12px; font-weight:700; text-transform:uppercase; display:flex; align-items:center; gap:8px;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                                    Invoice Breakdown
                                                </h6>
                                                <table style="width: 100%; border-collapse: collapse; font-size: 12px; background:white;">
                                                <thead>
                                                    <tr style="border-bottom: 1px solid #f0f0f0; background: #f0f5ff;">
                                                        <th
                                                            style="text-align: left; padding: 8px 10px; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 10px; white-space: nowrap; width: 30%;">
                                                            Status</th>
                                                        <th
                                                            style="text-align: right; padding: 8px 10px; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 10px; white-space: nowrap;">
                                                            Pending (VND)</th>
                                                        <th
                                                            style="text-align: right; padding: 8px 10px; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 10px; white-space: nowrap;">
                                                            Paid (VND)</th>
                                                        <th
                                                            style="text-align: right; padding: 8px 10px; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 10px; white-space: nowrap;">
                                                            Total (VND)</th>
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
                                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                                                            <td style="padding: 10px 16px; display: flex; align-items: center; white-space: nowrap;">
                                                                <div
                                                                    style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $dColor; ?>; margin-right: 12px; flex-shrink:0;">
                                                                </div>
                                                                <span style="font-weight: 500; color: #334155;"><?php echo $st; ?></span>
                                                            </td>
                                                            <td
                                                                style="padding: 10px 16px; text-align: right; color: <?php echo $np > 0 ? '#ef4444' : '#94a3b8'; ?>; font-weight: 600;">
                                                                <?php echo $np > 0 ? number_format($np, 0, '.', '.') : '-'; ?>
                                                            </td>
                                                            <td
                                                                style="padding: 10px 16px; text-align: right; color: <?php echo $p > 0 ? '#10b981' : '#94a3b8'; ?>; font-weight: 600;">
                                                                <?php echo $p > 0 ? number_format($p, 0, '.', '.') : '-'; ?>
                                                            </td>
                                                            <td
                                                                style="padding: 10px 16px; text-align: right; font-weight: 700; color: #1e293b;">
                                                                <?php echo $row_t > 0 ? number_format($row_t, 0, '.', '.') : '-'; ?>
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
                                            </div> <!-- End of Table Container -->
                                        </div> <!-- End of padding: 16px (Body) -->
                                    </div> <!-- End of Card -->
                                <?php endforeach; ?>
                            </div> <!-- End of Grid -->
                        <?php endif; ?>

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

                        // Extract Monthly Debt Data
                        $monthlyData = [];
                        foreach ($monthTotals as $mKey => $total) {
                            if ($mKey === 'No Date')
                                continue;
                            $parts = explode('/', $mKey); // mm/yyyy
                            if (count($parts) === 2) {
                                $sortKey = $parts[1] . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                                $monthlyData[$sortKey] = [
                                    'label' => $mKey,
                                    'total' => $total
                                ];
                            }
                        }
                        ksort($monthlyData);
                        if (isset($monthTotals['No Date'])) {
                            $monthlyData['999999'] = [
                                'label' => 'No Date',
                                'total' => $monthTotals['No Date']
                            ];
                        }

                        $chartMonthlyLabels = array_map(function ($item) {
                            return $item['label'];
                        }, $monthlyData);
                        $chartMonthlyTotals = array_map(function ($item) {
                            return $item['total'];
                        }, $monthlyData);
                        ?>
                        <div
                            style="padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

                            <!-- Chart 0: Total Debt by Month -->
                            <div
                                style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 24px;">
                                <h3 style="margin-top:0; color:#262626; font-size:16px;">Total Debt By Month</h3>
                                <div id="chart-monthly-debt"></div>
                            </div>

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
                            const statusAmounts = <?php echo json_encode(array_values($statusPaid)); ?>;

                            const monthlyLabels = <?php echo json_encode(array_values($chartMonthlyLabels)); ?>;
                            const monthlyTotals = <?php echo json_encode(array_values($chartMonthlyTotals)); ?>;

                            // 0. Monthly Debt Area Chart
                            new ApexCharts(document.querySelector("#chart-monthly-debt"), {
                                series: [{ name: 'Total Debt', data: monthlyTotals }],
                                chart: { type: 'area', height: 350, toolbar: { show: false } },
                                dataLabels: { enabled: true, formatter: function (val) { return val > 0 ? (val / 1000000).toFixed(0) + "M" : "" }, style: { fontSize: '10px' } },
                                stroke: { curve: 'smooth', width: 2 },
                                colors: ['#0ea5e9'],
                                xaxis: { categories: monthlyLabels },
                                yaxis: { labels: { formatter: val => (val / 1000000).toFixed(0) + 'M' } },
                                tooltip: { y: { formatter: val => formatVND(val) } },
                                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.9, stops: [0, 90, 100] } },
                            }).render();

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
                                    <th>Tiền</th>
                                    <th>Số tiền</th>
                                    <th>P&L</th>
                                    <th>Hóa đơn</th>
                                    <th>HĐ VAT</th>
                                    <th>Trạng thái HĐ</th>
                                    <th>Trạng thái TT</th>
                                    <th>Tháng TT</th>
                                    <th>Cập nhật tuần</th>
                                    <th>Ghi chú AM</th>
                                    <th>Ghi chú Delivery</th>
                                    <th>Trạng thái SX</th>
                                    <th style="width: 50px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $globalIdx = 1; ?>
                                <?php foreach ($groupedDebts as $monthName => $ams): ?>
                                    <tr class="group-header">
                                        <td colspan="24">
                                            Tháng <?php echo $monthName; ?>
                                            <span class="group-total">(Total:
                                                <?php echo formatVND($monthTotals[$monthName]); ?>)</span>
                                        </td>
                                    </tr>
                                    <?php foreach ($ams as $amName => $monthItems): ?>
                                        <tr class="group-header-am">
                                            <td colspan="24">
                                                AM: <?php echo htmlspecialchars($amName); ?>
                                                <span class="group-total"
                                                    style="font-size: 0.8rem; font-weight: normal; opacity: 0.8;">(Subtotal:
                                                    <?php echo formatVND($amTotals[$monthName][$amName]); ?>)</span>
                                            </td>
                                        </tr>
                                        <?php foreach ($monthItems as $d): ?>
                                            <tr style="cursor: default;">
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
                                                <td class="cell-amount" style="color: #64748b;">
                                                    <?php echo !empty($d['formatted_original']) ? $d['formatted_original'] : (!empty($d['amount_original']) ? formatCurrency($d['amount_original'], $d['currency_original'] ?? 'USD') : '-'); ?>
                                                </td>
                                                <td class="cell-amount">
                                                    <?php echo formatCurrency($d['amount'] ?? 0, 'VND'); ?>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge <?php echo (stripos($d['pl_class'] ?? '', 'Xấu') !== false ? 'pl-xau' : ((stripos($d['pl_class'] ?? '', 'TB') !== false) ? 'pl-tb' : 'pl-tot')); ?>">
                                                        <?php echo htmlspecialchars($d['pl_class'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($d['invoice_status'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($d['vat_invoice'] ?? ''); ?></td>
                                                <td style="text-align: center;">
                                                    <?php
                                                    $oState = $d['odoo_state'] ?? '';
                                                    if ($oState === 'posted') {
                                                        echo '<span class="badge" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Posted</span>';
                                                    } elseif ($oState === 'draft') {
                                                        echo '<span class="badge" style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;">Draft</span>';
                                                    } elseif ($oState === 'cancel') {
                                                        echo '<span class="badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">Cancelled</span>';
                                                    } else {
                                                        echo '<span class="badge" style="background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0;">-</span>';
                                                    }
                                                    ?>
                                                </td>
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
                                                <td style="text-align: center;">
                                                    <?php if ($role === 'admin'): ?>
                                                        <button onclick="event.stopPropagation(); deleteRow(<?php echo $d['id']; ?>);" class="btn-delete-row" title="Xóa bản ghi">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                        </button>
                                                    <?php endif; ?>
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
                                <option value="">- chọn AM -</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
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
                                <option value="USD">USD - US Dollar</option>
                                <option value="VND">VND - Vietnam Dong</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="JPY">JPY - Japanese Yen</option>
                                <option value="GBP">GBP - British Pound</option>
                                <option value="SGD">SGD - Singapore Dollar</option>
                                <option value="MYR">MYR - Malaysian Ringgit</option>
                                <option value="AUD">AUD - Australian Dollar</option>
                                <option value="CAD">CAD - Canadian Dollar</option>
                                <option value="HKD">HKD - Hong Kong Dollar</option>
                                <option value="CNY">CNY - Chinese Yuan</option>
                                <option value="KRW">KRW - South Korean Won</option>
                                <option value="TWD">TWD - Taiwan Dollar</option>
                                <option value="THB">THB - Thai Baht</option>
                                <option value="INR">INR - Indian Rupee</option>
                                <option value="CHF">CHF - Swiss Franc</option>
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
                const isAdmin = ("<?php echo $role; ?>" === "admin");
                document.getElementById('btnDelete').style.display = isAdmin ? "block" : "none";

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

        function deleteRow(id) {
            if (confirm("Are you sure you want to delete this record forever?")) {
                document.getElementById('deleteId').value = id;
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

            // Initialize Tom Select for filter multi-selects
            document.querySelectorAll('.ts-multiselect').forEach(function(el) {
                new TomSelect(el, {
                    plugins: ['remove_button'],
                    hideSelected: false,
                    placeholder: el.getAttribute('placeholder') || 'Chọn giá trị',
                });
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

        // Initialize Dashboard Charts
        document.addEventListener('DOMContentLoaded', function() {
            if ("<?php echo $selected_team; ?>" === "dashboard") {
                const teamsToShow = <?php echo json_encode($teams_to_show); ?>;
                const agingCategories = ['Trong hạn', '1-30 ngày', '31-60 ngày', '61-90 ngày', '> 90 ngày', 'Chưa có ngày HT'];
                const expectedFlowMap = {}; // Will store Month -> {TeamID -> Amount}
                const agingMap = {}; // Will store Category -> {TeamID -> Amount}
                
                // Initialize maps
                agingCategories.forEach(cat => agingMap[cat] = {});
                
                const now = new Date();
                now.setHours(0,0,0,0);

                debtsData.forEach(d => {
                    const tid = d.sale_team_id || 'undefined';
                    const amt = (parseFloat(d.amount) || 0);
                    const expDateStr = d.expected_payment_date;
                    const payStat = d.payment_status || '';
                    const internalStat = (d.invoice_status || '').toLowerCase();
                    const isPaid = payStat === 'Paid';
                    const isDraft = internalStat === 'draft';

                    if (!isPaid && !isDraft) {
                        if (expDateStr) {
                            const expDate = new Date(expDateStr);
                            if (!isNaN(expDate.getTime())) {
                                expDate.setHours(0,0,0,0);
                                const diffDays = Math.floor((now - expDate) / (1000 * 60 * 60 * 24));

                                if (diffDays <= 0) {
                                    agingMap['Trong hạn'][tid] = (agingMap['Trong hạn'][tid] || 0) + amt;
                                    const monthLabel = `${(expDate.getMonth()+1)}/${expDate.getFullYear()}`;
                                    if (!expectedFlowMap[monthLabel]) expectedFlowMap[monthLabel] = {};
                                    expectedFlowMap[monthLabel][tid] = (expectedFlowMap[monthLabel][tid] || 0) + amt;
                                } else if (diffDays <= 30) agingMap['1-30 ngày'][tid] = (agingMap['1-30 ngày'][tid] || 0) + amt;
                                else if (diffDays <= 60) agingMap['31-60 ngày'][tid] = (agingMap['31-60 ngày'][tid] || 0) + amt;
                                else if (diffDays <= 90) agingMap['61-90 ngày'][tid] = (agingMap['61-90 ngày'][tid] || 0) + amt;
                                else agingMap['> 90 ngày'][tid] = (agingMap['> 90 ngày'][tid] || 0) + amt;
                            } else agingMap['Chưa có ngày HT'][tid] = (agingMap['Chưa có ngày HT'][tid] || 0) + amt;
                        } else agingMap['Chưa có ngày HT'][tid] = (agingMap['Chưa có ngày HT'][tid] || 0) + amt;
                    }
                });

                const teamColors = ['#1890ff', '#13c2c2', '#52c41a', '#fadb14', '#fa8c16', '#eb2f96', '#722ed1', '#2f54eb', '#fa541c', '#a0d911', '#595959', '#391085'];
                
                const formatVndShort = (val) => {
                    if (val >= 1000000000) return (val / 1000000000).toFixed(1) + ' tỷ';
                    if (val >= 1000000) return (val / 1000000).toFixed(0) + ' tr';
                    return val.toLocaleString();
                };

                const commonDataLabels = {
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > (ctx.chart.scales.y.max * 0.08),
                    formatter: (val, ctx) => {
                        const teamName = ctx.dataset.label;
                        return [teamName, formatVndShort(val)];
                    },
                    color: '#fff',
                    font: { weight: '700', size: 8, lineHeight: 1.2 },
                    anchor: 'center',
                    align: 'center',
                    textAlign: 'center',
                    textStrokeColor: 'rgba(0,0,0,0.6)',
                    textStrokeWidth: 1.5,
                    clip: true
                };
                
                const flowDataLabels = {
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > (ctx.chart.scales.y.max * 0.08),
                    formatter: (val, ctx) => {
                        const teamName = ctx.dataset.label;
                        return [teamName, formatVndShort(val)];
                    },
                    color: '#fff',
                    font: { weight: '700', size: 8, lineHeight: 1.2 },
                    anchor: 'center',
                    align: 'center',
                    textAlign: 'center',
                    textStrokeColor: 'rgba(0,0,0,0.6)',
                    textStrokeWidth: 1.5,
                    clip: true
                };

                // Prepare Datasets for Aging
                const agingDatasets = teamsToShow.map((team, idx) => ({
                    label: team.name,
                    data: agingCategories.map(cat => agingMap[cat][team.id] || 0),
                    backgroundColor: teamColors[idx % teamColors.length],
                    borderRadius: 4
                }));

                // Prepare Datasets for Flow
                const sortedFlowKeys = Object.keys(expectedFlowMap).sort((a,b) => {
                    const [ma, ya] = a.split('/');
                    const [mb, yb] = b.split('/');
                    return ya === yb ? ma - mb : ya - yb;
                });
                const flowDatasets = teamsToShow.map((team, idx) => ({
                    label: team.name,
                    data: sortedFlowKeys.map(k => expectedFlowMap[k][team.id] || 0),
                    backgroundColor: teamColors[idx % teamColors.length],
                    borderRadius: 4
                }));

                // Render Global Aging Chart
                new Chart(document.getElementById('globalAgingChart').getContext('2d'), {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: agingCategories,
                        datasets: agingDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatVndShort(ctx.raw)}` } },
                            datalabels: commonDataLabels
                        },
                        scales: {
                            x: { stacked: true, ticks: { font: { size: 10, weight: '600' } } },
                            y: { stacked: true, beginAtZero: true, ticks: { callback: (val) => formatVndShort(val), font: { size: 10 } } }
                        }
                    }
                });

                // Render Global Flow Chart
                new Chart(document.getElementById('globalFlowChart').getContext('2d'), {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: sortedFlowKeys,
                        datasets: flowDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatVndShort(ctx.raw)}` } },
                            datalabels: flowDataLabels
                        },
                        scales: {
                            x: { stacked: true, ticks: { font: { size: 10, weight: '600' } } },
                            y: { stacked: true, beginAtZero: true, ticks: { callback: (val) => formatVndShort(val), font: { size: 10 } } }
                        }
                    }
                });

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

                    new ApexCharts(document.querySelector('#chart-breakeven-debt'), {
                        series: [
                            { name: 'Doanh thu KH (lũy kế)', data: cumRev },
                            { name: 'Chi phí KH (lũy kế)', data: cumExp },
                            { name: 'Chi phí thực tế (lũy kế)', data: cumAct }
                        ],
                        chart: { type: 'line', height: 350, toolbar: { show: false }, zoom: { enabled: false } },
                        stroke: { curve: 'smooth', width: 1.5, dashArray: [0, 5, 0] },
                        colors: ['#10b981', '#3b82f6', '#ef4444'],
                        markers: { size: 3, strokeWidth: 0, hover: { size: 5 } },
                        xaxis: { categories: labels, axisBorder: { show: true } },
                        yaxis: { labels: { formatter: v => (v/1e9).toFixed(1)+' Tỷ' } },
                        annotations: { xaxis: bepAnnot },
                        tooltip: { shared: true, intersect: false, y: { formatter: val => val.toLocaleString('vi-VN')+' đ' } },
                        legend: { position: 'top', horizontalAlign: 'left' },
                        grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
                        fill: { type: 'solid', opacity: 1 }
                    }).render();
                    // New Debt Analytics Charts
                    (function() {
                        // 1. Aging Pie
                        new ApexCharts(document.querySelector("#chart-debt-aging-pie"), {
                            series: <?php echo json_encode(array_values($aging_data)); ?>,
                            chart: { type: 'donut', height: 280 },
                            labels: ['0-30 Ngày', '31-60 Ngày', '61-90 Ngày', '90+ Ngày'],
                            colors: ['#52c41a', '#1890ff', '#faad14', '#f5222d'],
                            legend: { position: 'bottom', fontSize: '11px' },
                            dataLabels: { 
                                enabled: true, 
                                formatter: (val, opts) => opts.w.config.series[opts.seriesIndex] > 0 ? val.toFixed(1) + '%' : ''
                            },
                            tooltip: { y: { formatter: val => val.toLocaleString('vi-VN') + ' đ' } }
                        }).render();

                        // 2. Top Debtors Bar
                        new ApexCharts(document.querySelector("#chart-top-debtors-bar"), {
                            series: [{ name: 'Số dư nợ', data: <?php echo json_encode(array_values($top_debtors)); ?> }],
                            chart: { type: 'bar', height: 280, toolbar: { show: false } },
                            plotOptions: { 
                                bar: { 
                                    borderRadius: 4, 
                                    horizontal: true,
                                    dataLabels: { position: 'top' }
                                } 
                            },
                            dataLabels: {
                                enabled: true,
                                formatter: val => (val/1e9).toFixed(1) + ' Tỷ đ',
                                offsetX: 40,
                                style: { fontSize: '11px', fontWeight: '700', colors: ['#334155'] }
                            },
                            colors: ['#f5222d'],
                            xaxis: { 
                                categories: <?php echo json_encode(array_keys($top_debtors)); ?>, 
                                labels: { formatter: v => (v/1e9).toFixed(1) + ' Tỷ' } 
                            },
                            tooltip: { y: { formatter: val => val.toLocaleString('vi-VN') + ' đ' } }
                        }).render();

                        // 3. AM Efficiency Bar
                        new ApexCharts(document.querySelector("#chart-am-efficiency-bar"), {
                            series: [{ name: 'Tỷ lệ thu hồi', data: <?php echo json_encode($am_efficiency_values); ?> }],
                            chart: { type: 'bar', height: 280, toolbar: { show: false } },
                            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%', dataLabels: { position: 'top' } } },
                            dataLabels: {
                                enabled: true,
                                formatter: val => val + '%',
                                offsetY: -20,
                                style: { fontSize: '11px', fontWeight: '700', colors: ["#1e293b"] }
                            },
                            colors: ['#52c41a'],
                            xaxis: { categories: <?php echo json_encode($am_efficiency_labels); ?>, labels: { style: { fontSize: '10px' } } },
                            yaxis: { max: 100, labels: { formatter: v => v + '%' } },
                            tooltip: { y: { formatter: val => val + '%' } }
                        }).render();
                    })();
                })();
            }
        });
    </script>
    <script>
        function clearDateRange() {
            const url = new URL(window.location.href);
            url.searchParams.delete('date_from');
            url.searchParams.delete('date_to');
            url.searchParams.delete('exp_pay_date_from');
            url.searchParams.delete('exp_pay_date_to');
            window.location.href = url.toString();
        }

        function toggleFilterSidebar() {
            const sidebar = document.getElementById('filterSidebar');
            const overlay = document.getElementById('filterOverlay');
            const isOpen = sidebar.classList.contains('open');
            
            if (isOpen) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function toggleTeamDetail(e, teamId, teamName) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            const sidebar = document.getElementById('teamDetailSidebar');
            const overlay = document.getElementById('teamDetailOverlay');
            const title = document.getElementById('teamDetailTitle');
            const body = document.getElementById('teamDetailBody');
            
            const isOpen = sidebar.classList.contains('open');
            
            if (isOpen) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            } else {
                title.innerText = teamName + ' - Báo cáo & Phân tích';
                body.innerHTML = '<div style="text-align:center; padding:50px; color:#94a3b8;"><div class="spinner" style="margin-bottom:10px;"></div>Đang tổng hợp báo cáo...</div>';
                
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                setTimeout(() => {
                    // Filter data for this team
                    const teamDebts = debtsData.filter(d => 
                        (teamId === 'all') ? true :
                        (teamId === 'undefined' ? (!d.sale_team_id) : (d.sale_team_id == teamId))
                    );

                    const totalVnd = teamDebts.reduce((acc, d) => acc + (parseFloat(d.amount) || 0), 0);
                    const paidVnd = teamDebts.reduce((acc, d) => acc + (d.payment_status === 'Paid' ? (parseFloat(d.amount) || 0) : 0), 0);
                    const unpaidVnd = totalVnd - paidVnd;
                    const count = teamDebts.length;

                    const statusClassGroups = {};
                    const statusClassCounts = {};
                    const invoiceStatusGroups = {};
                    const quarterGroups = {};
                    const amGroups = {};
                    const agingGroups = { 'Trong hạn': 0, '1-30 ngày': 0, '31-60 ngày': 0, '61-90 ngày': 0, '> 90 ngày': 0, 'Chưa có ngày HT': 0 };
                    const expectedFlowGroups = {};
                    const now = new Date();
                    now.setHours(0,0,0,0);

                    teamDebts.forEach(d => {
                        const sClass = d.invoice_status_class || 'Khác';
                        const iStatus = d.invoice_status || 'Chưa xác định';
                        const am = d.am || 'N/A';
                        const amt = (parseFloat(d.amount) || 0);
                        const expDateStr = d.expected_payment_date;
                        const payStat = d.payment_status || '';
                        const internalStat = (d.invoice_status || '').toLowerCase();
                        const isPaid = payStat === 'Paid';
                        const isDraft = internalStat === 'draft';

                        // Aging & Expected Flow Logic
                        if (!isPaid && !isDraft) {
                            if (expDateStr) {
                                const expDate = new Date(expDateStr);
                                if (!isNaN(expDate.getTime())) {
                                    expDate.setHours(0,0,0,0);
                                    const diffDays = Math.floor((now - expDate) / (1000 * 60 * 60 * 24));

                                    if (diffDays <= 0) {
                                        agingGroups['Trong hạn'] += amt;
                                        // Future Expected Flow
                                        const monthLabel = `Tháng ${(expDate.getMonth()+1)}/${expDate.getFullYear()}`;
                                        expectedFlowGroups[monthLabel] = (expectedFlowGroups[monthLabel] || 0) + amt;
                                    } else if (diffDays <= 30) agingGroups['1-30 ngày'] += amt;
                                    else if (diffDays <= 60) agingGroups['31-60 ngày'] += amt;
                                    else if (diffDays <= 90) agingGroups['61-90 ngày'] += amt;
                                    else agingGroups['> 90 ngày'] += amt;
                                } else {
                                    agingGroups['Chưa có ngày HT'] += amt;
                                }
                            } else {
                                agingGroups['Chưa có ngày HT'] += amt;
                            }
                        }

                        // Date / Quarter
                        const dObj = d.invoice_date ? new Date(d.invoice_date) : null;
                        if (dObj && !isNaN(dObj.getTime())) {
                            const year = dObj.getFullYear();
                            const quarter = Math.floor(dObj.getMonth() / 3) + 1;
                            const qKey = `Q${quarter} ${year}`;
                            quarterGroups[qKey] = (quarterGroups[qKey] || 0) + amt;
                        }

                        // Phân loại
                        statusClassGroups[sClass] = (statusClassGroups[sClass] || 0) + amt;
                        statusClassCounts[sClass] = (statusClassCounts[sClass] || 0) + 1;

                        // Trạng thái (Draft, Paid, Unpaid)
                        let statusKey = 'Unpaid';

                        if (internalStat === 'draft') {
                            statusKey = 'Draft';
                        } else if (payStat === 'Paid') {
                            statusKey = 'Paid';
                        } else {
                            statusKey = 'Unpaid';
                        }
                        
                        invoiceStatusGroups[statusKey] = (invoiceStatusGroups[statusKey] || 0) + amt;

                        // AM
                        amGroups[am] = (amGroups[am] || 0) + amt;
                    });

                    const formatVndShort = (val) => {
                        if (val >= 1000000000) return (val / 1000000000).toFixed(2) + ' tỷ';
                        if (val >= 1000000) return (val / 1000000).toFixed(1) + ' tr';
                        return val.toLocaleString();
                    };

                    body.innerHTML = `
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:24px;">
                            <div style="background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0; text-align:center;">
                                <div style="font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:4px; font-weight:600;">Tổng doanh thu</div>
                                <div style="font-size:20px; font-weight:800; color:#0f172a;">${formatVndShort(totalVnd)}</div>
                            </div>
                            <div style="background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0; text-align:center;">
                                <div style="font-size:11px; color:#64748b; text-transform:uppercase; margin-bottom:4px; font-weight:600;">Số lượng khoản</div>
                                <div style="font-size:20px; font-weight:800; color:#0f172a;">${count}</div>
                            </div>
                        </div>

                        <div style="margin-bottom:24px; background:white; border:1px solid #f1f5f9; border-radius:12px; padding:16px;">
                            <h5 style="margin:0 0 15px 0; font-size:13px; color:#475569;">Tỷ lệ Thanh toán (VND)</h5>
                            <div style="height:12px; background:#f1f5f9; border-radius:6px; overflow:hidden; display:flex; margin-bottom:10px;">
                                <div style="width:${(paidVnd/totalVnd*100)||0}%; background:#10b981;" title="Đã thu"></div>
                                <div style="width:${(unpaidVnd/totalVnd*100)||0}%; background:#ef4444;" title="Chưa thu"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:500;">
                                <span style="color:#059669;">● Đã thu: ${formatVndShort(paidVnd)}</span>
                                <span style="color:#dc2626;">● Còn nợ: ${formatVndShort(unpaidVnd)}</span>
                            </div>
                        </div>

                        <div style="margin-bottom:30px; background:#fcfcfc; padding:15px; border-radius:12px;">
                            <div style="display:flex; gap:10px; height:240px;">
                                <div style="flex:1;">
                                    <h5 style="margin:0 0 15px 0; font-size:12px; color:#0f172a; font-weight:700; border-left:3px solid #3b82f6; padding-left:10px;">Phân loại Invoice</h5>
                                    <div style="height:180px;"><canvas id="statusClassChartV"></canvas></div>
                                </div>
                                <div style="flex:1;">
                                    <h5 style="margin:0 0 15px 0; font-size:12px; color:#0f172a; font-weight:700; border-left:3px solid #10b981; padding-left:10px;">Tỷ lệ Trạng thái</h5>
                                    <div style="height:180px;"><canvas id="invoiceStatusPie"></canvas></div>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px; background:#fff; padding:15px; border-radius:12px;">
                            <div style="background:white; border-radius:12px;">
                                <h5 style="margin:0 0 15px 0; font-size:12px; color:#e11d48; font-weight:700; border-left:3px solid #e11d48; padding-left:10px;">Phân tích Tuổi nợ (Aging Report)</h5>
                                <div style="height:220px;"><canvas id="agingChart"></canvas></div>
                            </div>
                            <div style="background:white; border-radius:12px;">
                                <h5 style="margin:0 0 15px 0; font-size:12px; color:#2563eb; font-weight:700; border-left:3px solid #2563eb; padding-left:10px;">Dòng tiền dự kiến về</h5>
                                <div style="height:220px;"><canvas id="flowChart"></canvas></div>
                            </div>
                        </div>

                        <div style="margin-bottom:30px;">
                            <h5 style="margin:0 0 15px 0; font-size:13px; color:#475569; font-weight:700;">Doanh thu theo Quý</h5>
                            <div style="height:220px;"><canvas id="quarterChart"></canvas></div>
                        </div>

                        <div style="margin-bottom:30px;">
                            <h5 style="margin:0 0 15px 0; font-size:13px; color:#475569; font-weight:700;">Doanh thu theo AM</h5>
                            <div style="height:250px;"><canvas id="amChart"></canvas></div>
                        </div>
                    `;

                    const chartColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'];
                    
                    const commonDataLabels = {
                        color: '#fff',
                        font: { weight: 'bold', size: 10 },
                        formatter: (val) => val > 0 ? formatVndShort(val) : '',
                        anchor: 'center',
                        align: 'center',
                        offset: 0
                    };

                    // 1. Phân loại (Giá trị)
                    new Chart(document.getElementById('statusClassChartV').getContext('2d'), {
                        type: 'doughnut',
                        plugins: [ChartDataLabels],
                        data: {
                            labels: Object.keys(statusClassGroups),
                            datasets: [{
                                label: 'Phân loại',
                                data: Object.values(statusClassGroups),
                                backgroundColor: chartColors,
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: true, 
                                    position: 'bottom',
                                    labels: { boxWidth: 10, font: { size: 9 } }
                                },
                                datalabels: commonDataLabels
                            }
                        }
                    });

                    // 2. Trạng thái (Pie Ratio)
                    const statusLabels = ['Draft', 'Paid', 'Unpaid'];
                    const statusData = statusLabels.map(label => invoiceStatusGroups[label] || 0);
                    const statusColors = ['#94a3b8', '#10b981', '#ef4444'];

                    new Chart(document.getElementById('invoiceStatusPie').getContext('2d'), {
                        type: 'pie',
                        plugins: [ChartDataLabels],
                        data: {
                            labels: statusLabels,
                            datasets: [{
                                label: 'Trạng thái',
                                data: statusData,
                                backgroundColor: statusColors,
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: true, 
                                    position: 'bottom',
                                    labels: { boxWidth: 10, font: { size: 9 } }
                                },
                                datalabels: commonDataLabels
                            }
                        }
                    });

                    // 5. Aging Chart
                    new Chart(document.getElementById('agingChart').getContext('2d'), {
                        type: 'bar',
                        plugins: [ChartDataLabels],
                        data: {
                            labels: Object.keys(agingGroups),
                            datasets: [{
                                label: 'VND',
                                data: Object.values(agingGroups),
                                backgroundColor: ['#10b981', '#fcd34d', '#fb923c', '#ef4444', '#991b1b', '#64748b'],
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: { top: 35, bottom: 10 }
                            },
                            plugins: { 
                                legend: { display: false },
                                datalabels: { ...commonDataLabels, color: '#000', anchor: 'end', align: 'top', offset: 0 }
                            },
                            scales: {
                                y: { beginAtZero: true, display: false },
                                x: { 
                                    ticks: { 
                                        font: { size: 9 },
                                        autoSkip: false,
                                        maxRotation: 45,
                                        minRotation: 45
                                    } 
                                }
                            }
                        }
                    });

                    // 6. Expected Flow Chart
                    const sortedFlowKeys = Object.keys(expectedFlowGroups).sort();
                    new Chart(document.getElementById('flowChart').getContext('2d'), {
                        type: 'bar',
                        plugins: [ChartDataLabels],
                        data: {
                            labels: sortedFlowKeys,
                            datasets: [{
                                label: 'VND',
                                data: sortedFlowKeys.map(k => expectedFlowGroups[k]),
                                backgroundColor: '#bfdbfe',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false },
                                datalabels: { ...commonDataLabels, color: '#1e40af', anchor: 'end', align: 'top', offset: 0 }
                            },
                            scales: {
                                y: { beginAtZero: true, display: false },
                                x: { ticks: { font: { size: 9 } } }
                            }
                        }
                    });


                    // 3. Doanh thu theo Quý
                    const sortedQuarters = Object.keys(quarterGroups).sort((a, b) => {
                        const [qa, ya] = a.split(' ');
                        const [qb, yb] = b.split(' ');
                        if (ya !== yb) return ya - yb;
                        return qa.localeCompare(qb);
                    });

                    new Chart(document.getElementById('quarterChart').getContext('2d'), {
                        type: 'line',
                        plugins: [ChartDataLabels],
                        data: {
                            labels: sortedQuarters,
                            datasets: [{
                                label: 'Doanh thu',
                                data: sortedQuarters.map(q => quarterGroups[q]),
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#f59e0b',
                                borderWidth: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: true, 
                                    position: 'top',
                                    align: 'end',
                                    labels: { boxWidth: 10, font: { size: 10 } }
                                },
                                datalabels: {
                                    ...commonDataLabels,
                                    color: '#b45309',
                                    anchor: 'end',
                                    align: 'top',
                                    offset: 8
                                }
                            },
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    display: true, 
                                    grid: { display: false },
                                    ticks: { display: false }
                                },
                                x: { 
                                    grid: { display: true, color: '#f1f5f9' }, 
                                    ticks: { font: { size: 10, weight: '600' } } 
                                }
                            }
                        }
                    });

                    // 4. AM Chart
                    const sortedAmKeys = Object.keys(amGroups).sort((a, b) => amGroups[b] - amGroups[a]);

                    new Chart(document.getElementById('amChart').getContext('2d'), {
                        type: 'bar',
                        plugins: [ChartDataLabels],
                        data: {
                            labels: sortedAmKeys,
                            datasets: [{
                                label: 'Doanh thu',
                                data: sortedAmKeys.map(k => amGroups[k]),
                                backgroundColor: '#93c5fd',
                                hoverBackgroundColor: '#3b82f6',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: true, 
                                    position: 'top',
                                    align: 'end',
                                    labels: { boxWidth: 10, font: { size: 10 } }
                                },
                                datalabels: {
                                    ...commonDataLabels,
                                    color: '#1e40af',
                                    anchor: 'end',
                                    align: 'right',
                                    offset: 4
                                }
                            },
                            scales: {
                                x: { display: false },
                                y: { grid: { display: false }, ticks: { font: { size: 10, weight: '500' } } }
                            }
                        }
                    });

                }, 400);
            }
        }
    </script>
    
    <div class="detail-sidebar-overlay" id="teamDetailOverlay" onclick="toggleTeamDetail(event)"></div>
    <div class="detail-sidebar" id="teamDetailSidebar">
        <div class="detail-sidebar-header">
            <div class="detail-sidebar-title" id="teamDetailTitle">Chi tiết Team</div>
            <button class="btn-edit-row" onclick="toggleTeamDetail(event)" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>
        <div class="detail-sidebar-body" id="teamDetailBody">
            <!-- Content loaded via JS -->
        </div>
    </div>
</body>

</html>