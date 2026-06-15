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

// Fetch current user email if not in session
if (!isset($_SESSION['email'])) {
    $stmt_e = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt_e->bind_param("i", $current_user_id);
    $stmt_e->execute();
    $res_e = $stmt_e->get_result();
    if ($row_e = $res_e->fetch_assoc()) {
        $_SESSION['email'] = $row_e['email'];
    }
}
$current_user_email = $_SESSION['email'] ?? '';

// Fetch user's team IDs
$user_team_ids = [];
$res_teams = $conn->query("SELECT team_id FROM user_sale_teams WHERE user_id = $current_user_id");
if ($res_teams) {
    while ($rt = $res_teams->fetch_assoc()) {
        $user_team_ids[] = intval($rt['team_id']);
    }
}

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
    // Check and add columns if not exists
    $columns_to_check = [
        'sale_team_id' => 'INT DEFAULT NULL AFTER am',
        'am_email' => 'VARCHAR(255) DEFAULT NULL AFTER am',
        'currency' => "VARCHAR(20) DEFAULT 'VND' AFTER amount",
        'invoice_date' => 'DATE DEFAULT NULL AFTER vat_invoice',
        'odoo_invoice_id' => 'INT DEFAULT NULL AFTER id',
        'original_amount' => 'DECIMAL(15, 2) DEFAULT 0 AFTER amount',
        'original_currency' => 'VARCHAR(20) DEFAULT NULL AFTER currency'
    ];

    foreach ($columns_to_check as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM debts LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE debts ADD COLUMN $col $definition");
        }
    }

    // Force amount precision fix
    $conn->query("ALTER TABLE debts MODIFY COLUMN amount DECIMAL(20, 2) DEFAULT 0");
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

        // Fetch AM Email
        $am_email = '';
        if (!empty($am)) {
            $stmt_am = $conn->prepare("SELECT email FROM users WHERE full_name = ? LIMIT 1");
            $stmt_am->bind_param("s", $am);
            $stmt_am->execute();
            $res_am_em = $stmt_am->get_result();
            if ($row_am_em = $res_am_em->fetch_assoc()) {
                $am_email = $row_am_em['email'];
            }
            $stmt_am->close();
        }
        if (empty($am_email) && $am === $_SESSION['full_name']) {
            $am_email = $_SESSION['email'] ?? '';
        }

        if ($_POST['action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO debts (company, sale_team_id, am, am_email, client_name, project_name, payment_milestone, expected_prod_date, expected_payment_date, invoice_status_class, amount, currency, invoice_status, vat_invoice, invoice_date, payment_status, payment_month, weekly_update, am_notes, delivery_notes, production_status, pl_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssssssssssssssssss", $company, $sale_team_id, $am, $am_email, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl);
        } else {
            // Edit
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE debts SET company=?, sale_team_id=?, am=?, am_email=?, client_name=?, project_name=?, payment_milestone=?, expected_prod_date=?, expected_payment_date=?, invoice_status_class=?, amount=?, currency=?, invoice_status=?, vat_invoice=?, invoice_date=?, payment_status=?, payment_month=?, weekly_update=?, am_notes=?, delivery_notes=?, production_status=?, pl_class=? WHERE id=?");
            $stmt->bind_param("sissssssssssssssssssssi", $company, $sale_team_id, $am, $am_email, $client, $project, $milestone, $prod_date, $pay_date, $inv_class, $amount, $currency_val, $inv_stat, $vat, $invoice_date_val, $pay_stat, $pay_month, $weekly, $am_note, $del_note, $prod_stat, $pl, $id);
        }

        if ($stmt->execute()) {
            header("Location: /my-debt");
            exit();
        } else {
            $error_message = "Error: " . $stmt->error;
            file_put_contents(__DIR__ . '/../../debug_file.log', date('Y-m-d H:i:s') . " SQL ERROR: " . $stmt->error . "\n", FILE_APPEND);
            echo "<script>alert('Save failed: " . addslashes($stmt->error) . "');</script>";
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
// Filter by current user's email (identical to My Reports logic)

// my-debt: chỉ lọc theo am_email của user hiện tại
$identity_sql = !empty($current_user_email)
    ? "d.am_email = '" . $conn->real_escape_string($current_user_email) . "'"
    : "1=1";

$filter_clauses = [];
if (!empty($_GET['invoice_status_class'])) {
    $inv_class_filter = $conn->real_escape_string($_GET['invoice_status_class']);
    if ($inv_class_filter === 'Xanh') {
        $filter_clauses[] = "(d.invoice_status_class = 'Xanh' OR d.invoice_status_class = 'Tốt')";
    } else {
        $filter_clauses[] = "d.invoice_status_class = '$inv_class_filter'";
    }
}

/**
 * Cờ (đã-thu, còn-nợ) của 1 dòng nợ, dựa trên amount_total/amount_residual của Odoo.
 *  - đã-thu (collected) = amount_total - amount_residual > 0
 *  - còn-nợ (owed)      = amount_residual > 0
 * Hóa đơn thu một phần (partial) sẽ TRUE cả hai. Không có dữ liệu Odoo thì fallback theo payment_status DB.
 * @return array [bool $hasCollected, bool $hasOwed]
 */
/**
 * Map tên công ty từ Odoo (company_id) về tên ngắn khớp với option trong form.
 *  - "AHT TECH JOINT STOCK COMPANY"     -> AHT TECH
 *  - "A1 CONSULTING SDN. BHD."          -> A1C MY (Malaysia)
 *  - "A1 CONSULTING JOINT STOCK COMPANY"-> A1VN
 * Không khớp -> trả nguyên tên Odoo.
 */
function shortCompanyName($odooName)
{
    $n = strtoupper(trim((string) $odooName));
    if ($n === '') return '';
    if (strpos($n, 'AHT TECH') !== false) return 'AHT TECH';
    if (strpos($n, 'SDN') !== false || strpos($n, 'BHD') !== false) return 'A1C MY';
    if (strpos($n, 'A1 CONSULTING') !== false || strpos($n, 'A1C') !== false || strpos($n, 'A1 ') !== false) return 'A1VN';
    return (string) $odooName;
}

function debtPaidOwedFlags($odoo_inv, $db_payment_status)
{
    if ($odoo_inv && isset($odoo_inv['amount_total']) && abs((float) $odoo_inv['amount_total']) > 0) {
        $total    = abs((float) $odoo_inv['amount_total']);
        $residual = isset($odoo_inv['amount_residual']) ? abs((float) $odoo_inv['amount_residual']) : 0;
        $collected = $total - $residual;
        return [$collected > 0.01, $residual > 0.01];
    }
    $paid = (strcasecmp(trim((string) $db_payment_status), 'Paid') === 0);
    return [$paid, !$paid];
}

/**
 * Dòng nợ có khớp bộ lọc trạng thái thanh toán không (dựa trên Odoo):
 *  - 'Draft'    : state = draft
 *  - 'Paid'     : đã thu được tiền (collected > 0) — gồm cả partial
 *  - 'Not paid' : còn nợ (owed > 0)               — gồm cả partial
 * Hóa đơn đã hủy (state = cancel) không khớp filter nào.
 */
function debtMatchesStatus($odoo_inv, $db_payment_status, $selected)
{
    if (empty($selected)) return true;
    $state = ($odoo_inv && isset($odoo_inv['state'])) ? (string) $odoo_inv['state'] : '';
    if ($state === 'draft')  return in_array('Draft', $selected);
    if ($state === 'cancel') return false;
    list($hasCollected, $hasOwed) = debtPaidOwedFlags($odoo_inv, $db_payment_status);
    if (in_array('Paid', $selected) && $hasCollected) return true;
    if (in_array('Not paid', $selected) && $hasOwed)  return true;
    return false;
}

// Trạng thái thanh toán được lọc ở PHP (dựa trên Odoo), không lọc bằng SQL.
$status_filter = [];
if (!empty($_GET['status'])) {
    $status_filter = is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']];
    $status_filter = array_values(array_filter($status_filter, function ($s) {
        return $s !== '';
    }));
}

// Lọc theo công ty (lọc PHP-side theo company suy ra từ Odoo, khớp cột hiển thị)
$company_filter = isset($_GET['company']) ? trim((string) $_GET['company']) : '';

if (!empty($_GET['q'])) {
    $search = $conn->real_escape_string($_GET['q']);
    $filter_clauses[] = "(d.client_name LIKE '%$search%' OR d.project_name LIKE '%$search%' OR d.vat_invoice LIKE '%$search%')";
}

$time_clauses = [];
if (!empty($_GET['year'])) {
    $year = intval($_GET['year']);
    $time_clauses[] = "YEAR(d.invoice_date) = $year";
}

if (!empty($_GET['quarter'])) {
    $qtr = intval($_GET['quarter']);
    if ($qtr == 1)
        $time_clauses[] = "MONTH(d.invoice_date) IN (1,2,3)";
    elseif ($qtr == 2)
        $time_clauses[] = "MONTH(d.invoice_date) IN (4,5,6)";
    elseif ($qtr == 3)
        $time_clauses[] = "MONTH(d.invoice_date) IN (7,8,9)";
    elseif ($qtr == 4)
        $time_clauses[] = "MONTH(d.invoice_date) IN (10,11,12)";
}

if (!empty($_GET['month'])) {
    $month = intval($_GET['month']);
    $time_clauses[] = "MONTH(d.invoice_date) = $month";
}

if (!empty($_GET['week'])) {
    $week_number = intval($_GET['week']);
    $filter_clauses[] = "(d.weekly_update LIKE '%Tuần $week_number%' OR d.weekly_update LIKE '%tuần $week_number%' OR d.weekly_update = '$week_number' OR d.weekly_update LIKE '%W$week_number%' OR d.weekly_update LIKE '%w$week_number%')";
}

// Build the final WHERE logic
// Identity must match ALWAYS.
// Then either: (it matches all active filters AND time filters) OR (it has no month)
$active_filters_sql = "1=1";
if (count($filter_clauses) > 0 || count($time_clauses) > 0) {
    $combined = array_merge($time_clauses, $filter_clauses);
    $active_filters_sql = implode(" AND ", $combined);
}

    $where_sql = "WHERE $identity_sql AND ($active_filters_sql OR d.invoice_date IS NULL OR d.invoice_date < '1000-01-01')";

$groupedDebts = [];
$monthTotals = [];
$monthPaid = [];   // tổng phần đã thu theo tháng
$monthUnpaid = []; // tổng phần còn nợ theo tháng
$total_amount_usd = 0;
$total_amount_vnd = 0;
$total_paid_vnd = 0;   // tổng phần ĐÃ thu (collected) của các dòng hiển thị
$total_unpaid_vnd = 0; // tổng phần CÒN nợ (owed) của các dòng hiển thị
$res = $conn->query("SELECT d.*, st.name as team_name FROM debts d LEFT JOIN sale_teams st ON d.sale_team_id = st.id $where_sql ORDER BY (d.invoice_date IS NULL OR d.invoice_date < '1000-01-01') DESC, d.invoice_date DESC, d.id DESC");

// Trigger cache refresh if needed (OdooAPI::getInvoices handles the 1-hour check internally)
$odoo->getInvoices(1, 0);
$odoo_map = $odoo->getInvoiceMap();

// File cache của getInvoiceMap() trễ tới 1 giờ, trong khi webhook giữ bảng odoo_invoices
// luôn mới (real-time). Patch số tiền/tệ/trạng thái mới nhất từ odoo_invoices vào $odoo_map
// để auto-sync bên dưới KHÔNG ghi đè debts bằng giá trị cũ (vd invoice vừa đổi 100k → 150k).
$freshInv = $conn->query("SELECT odoo_id, amount_total, amount_total_signed, currency_name, payment_state, invoice_date, invoice_date_due FROM odoo_invoices");
if ($freshInv) {
    while ($fi = $freshInv->fetch_assoc()) {
        $fid = (int) $fi['odoo_id'];
        if (!isset($odoo_map[$fid])) continue; // chỉ vá entry đã có trong cache (case bị clobber)
        $odoo_map[$fid]['amount_total']        = (float) $fi['amount_total'];
        $odoo_map[$fid]['amount_total_signed'] = (float) $fi['amount_total_signed'];
        if (!empty($fi['currency_name'])) {
            $odoo_map[$fid]['currency_id'] = [0, $fi['currency_name']]; // giữ shape [id, name]
        }
        // Chỉ override payment_state nếu giá trị mới là paid/in_payment,
        // HOẶC cache chưa ở trạng thái đã thanh toán.
        // Tránh trường hợp webhook cũ (not_paid) ghi đè file cache mới (paid).
        if (!empty($fi['payment_state'])) {
            $paidStates = ['paid', 'in_payment'];
            $cacheState = $odoo_map[$fid]['payment_state'] ?? '';
            if (in_array($fi['payment_state'], $paidStates) || !in_array($cacheState, $paidStates)) {
                $odoo_map[$fid]['payment_state'] = $fi['payment_state'];
            }
        }
        if (!empty($fi['invoice_date']))     $odoo_map[$fid]['invoice_date']     = $fi['invoice_date'];
        if (!empty($fi['invoice_date_due'])) $odoo_map[$fid]['invoice_date_due'] = $fi['invoice_date_due'];
    }
}

$odoo_name_map = [];
foreach ($odoo_map as $inv) {
    if (!empty($inv['name'])) {
        $odoo_name_map[$inv['name']] = $inv;
    }
}

// Tỉ giá theo TIỀN TỆ (rate = số đơn vị ngoại tệ trên 1 VND) — dùng quy đổi VND cho
// bản ghi KHÔNG link Odoo (vd record mới nhập tay). Tránh getRate() bị cross-company.
$currencyMap = $odoo->getCurrencies();

if ($res) {
    // Pre-fetch AM names to emails for one-time auto-population
    $am_name_map = [];
    $users_res = $conn->query("SELECT full_name, email FROM users WHERE is_am_bd = 1");
    if ($users_res) {
        while ($u = $users_res->fetch_assoc()) {
            $am_name_map[trim($u['full_name'])] = $u['email'];
        }
    }

    while ($row = $res->fetch_assoc()) {
        // Lọc theo trạng thái thanh toán dựa trên Odoo (Draft / Not paid / Paid). Partial khớp cả Paid lẫn Not paid.
        $oid_for_filter = (string) $row['odoo_invoice_id'];
        $odoo_inv_for_filter = isset($odoo_map[$oid_for_filter]) ? $odoo_map[$oid_for_filter] : null;
        if (count($status_filter) > 0 && !debtMatchesStatus($odoo_inv_for_filter, $row['payment_status'], $status_filter)) {
            continue;
        }

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

            // 2b. Company (lấy từ Odoo company_id, map về tên ngắn)
            $newCompany = is_array($inv['company_id'] ?? null) ? shortCompanyName($inv['company_id'][1]) : '';
            if ($newCompany !== '' && $newCompany !== ($row['company'] ?? '')) {
                $upSql[] = "company = ?";
                $upParams[] = $newCompany;
                $upTypes .= "s";
                $row['company'] = $newCompany;
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

            // Sync Invoice Date if different — CHỈ lấy invoice_date thật của Odoo,
            // KHÔNG fallback sang `date` (ngày hạch toán). Draft chưa có ngày hóa đơn → bỏ qua.
            $newInvDateVal = $inv['invoice_date'] ?: null;
            if ($newInvDateVal && $newInvDateVal !== ($row['invoice_date'] ?? '')) {
                $upSql[] = "invoice_date = ?";
                $upParams[] = $newInvDateVal;
                $upTypes .= "s";
                $row['invoice_date'] = $newInvDateVal;
                $date = $newInvDateVal; // update local
                $changed = true;
            }

            // Auto-populate am_email if missing using local name map
            if (empty($row['am_email'])) {
                $am_name = trim($row['am']);
                // Check direct match
                if (isset($am_name_map[$am_name])) {
                    $upSql[] = "am_email = ?";
                    $upParams[] = $am_name_map[$am_name];
                    $upTypes .= "s";
                    $row['am_email'] = $am_name_map[$am_name];
                    $changed = true;
                } else {
                    // Try partial match (Salesperson from Odoo often has full name)
                    foreach ($am_name_map as $fn => $em) {
                        if (stripos($fn, $am_name) !== false || stripos($am_name, $fn) !== false) {
                            $upSql[] = "am_email = ?";
                            $upParams[] = $am_name_map[$fn];
                            $upTypes .= "s";
                            $row['am_email'] = $em;
                            $changed = true;
                            break;
                        }
                    }
                }
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

        // Lọc theo công ty (sau khi $row['company'] đã được đồng bộ từ Odoo)
        if ($company_filter !== '' && ($row['company'] ?? '') !== $company_filter) {
            continue;
        }

        // Convert to VND using Odoo exchange rate ratio if available
        $vnd_value = 0;
        if (!empty($oid) && isset($odoo_map[$oid])) {
            $odoo_inv = $odoo_map[$oid];
            $odoo_total = (float) $odoo_inv['amount_total'];
            $odoo_signed = abs((float) $odoo_inv['amount_total_signed']);

            $vnd_multiplier = $odoo->getRate('VND', $date);
            if ($curr === 'VND') {
                $vnd_value = $amount;
            } else if ($odoo_total > 0) {
                $ratio = abs($odoo_signed / $odoo_total);
                if ($ratio > 100) {
                    // Ratio is high, likely already in VND (e.g. 25000 for USD)
                    $vnd_value = $amount * $ratio;
                } else {
                    // Ratio is low, likely in a different company currency (e.g. 1.0 for MYR, 0.25 for USD)
                    // Needs conversion to VND using the VND multiplier relative to the base.
                    $vnd_value = $amount * $ratio * $vnd_multiplier;
                }
            } else if ($odoo_total > 0 && $curr === 'VND') {
                 $vnd_value = $amount;
            }
        }

        // Fallback (bản ghi không link Odoo): quy đổi VND theo tỉ giá tiền tệ từ getCurrencies().
        // rate = số đơn vị ngoại tệ / 1 VND  => VND = amount / rate. VND thì rate = 1.
        if ($vnd_value <= 0) {
            if ($curr === 'VND') {
                $vnd_value = $amount;
            } else {
                $cr = isset($currencyMap[$curr]['rate']) ? (float) $currencyMap[$curr]['rate'] : 0;
                $vnd_value = ($cr > 0) ? ($amount / $cr) : $amount;
            }
        }

        // Odoo invoice dùng để tính phần đã thu / còn nợ / trạng thái hiển thị
        $odoo_inv_for_state = (!empty($oid) && isset($odoo_map[$oid])) ? $odoo_map[$oid] : null;
        $row['odoo_state'] = ($odoo_inv_for_state && isset($odoo_inv_for_state['state'])) ? (string) $odoo_inv_for_state['state'] : '';
        $row['odoo_payment_state'] = ($odoo_inv_for_state && isset($odoo_inv_for_state['payment_state'])) ? (string) $odoo_inv_for_state['payment_state'] : '';

        // Paid Amount (VND): phần đã thu = (amount_total - amount_residual) áp tỉ lệ lên giá trị VND của dòng.
        $paid_vnd = 0;
        $odoo_total_for_paid = ($odoo_inv_for_state && isset($odoo_inv_for_state['amount_total'])) ? abs((float) $odoo_inv_for_state['amount_total']) : 0;
        if ($odoo_total_for_paid > 0) {
            $residual = ($odoo_inv_for_state && isset($odoo_inv_for_state['amount_residual'])) ? abs((float) $odoo_inv_for_state['amount_residual']) : 0;
            $paid_fraction = max(0, min(1, ($odoo_total_for_paid - $residual) / $odoo_total_for_paid));
            $paid_vnd = $vnd_value * $paid_fraction;
        } else {
            // Không có dữ liệu Odoo: dựa theo trạng thái nhị phân trong DB
            $paid_vnd = (strcasecmp(trim((string) ($row['payment_status'] ?? '')), 'Paid') === 0) ? $vnd_value : 0;
        }
        $owed_vnd = max(0, $vnd_value - $paid_vnd);
        $row['paid_amount_vnd'] = $paid_vnd;

        // Phần đóng góp vào Total theo bộ lọc đang chọn:
        //  - filter Paid     -> chỉ phần đã thu
        //  - filter Not paid -> chỉ phần còn nợ
        //  - chọn cả hai / không lọc / Draft -> full
        $contrib_vnd = $vnd_value;
        if (count($status_filter) > 0) {
            $state_for_contrib = $row['odoo_state'];
            $paidSel = in_array('Paid', $status_filter);
            $notPaidSel = in_array('Not paid', $status_filter);
            if ($state_for_contrib === 'draft') {
                $contrib_vnd = $vnd_value;
            } elseif ($paidSel && $notPaidSel) {
                $contrib_vnd = $vnd_value;
            } elseif ($paidSel) {
                $contrib_vnd = $paid_vnd;
            } elseif ($notPaidSel) {
                $contrib_vnd = $owed_vnd;
            }
        }

        $total_amount_vnd += $contrib_vnd;
        $total_paid_vnd += $paid_vnd;     // chỉ tổng phần đã thu
        $total_unpaid_vnd += $owed_vnd;   // chỉ tổng phần còn nợ

        // Track total in USD for generic reference
        if ($curr === 'USD') {
            $total_amount_usd += $amount;
        } else if ($vnd_value > 0) {
            // Use 24000 as a generic VND/USD fallback for the USD summary if no direct USD rate
            $total_amount_usd += ($vnd_value / 24000);
        }

        // Grouping
        $row['amount_original'] = $amount;
        $row['currency_original'] = $curr;
        $row['amount'] = $vnd_value;
        $row['currency'] = 'VND';
        $row['formatted_original'] = formatCurrency($amount, $curr);

        $mKey = (!empty($row['invoice_date']) && $row['invoice_date'] > '1000-01-01') ? date('m/Y', strtotime($row['invoice_date'])) : 'Nợ chưa vào tháng';
        $groupedDebts[$mKey][] = $row;
        if (!isset($monthTotals[$mKey])) {
            $monthTotals[$mKey] = 0;
            $monthPaid[$mKey] = 0;
            $monthUnpaid[$mKey] = 0;
        }
        $monthTotals[$mKey] += $contrib_vnd;
        $monthPaid[$mKey] += $paid_vnd;
        $monthUnpaid[$mKey] += $owed_vnd;
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

// ── Confirm công nợ theo tuần hiện tại ──
$conn->query("CREATE TABLE IF NOT EXISTS debt_weekly_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, am_name VARCHAR(150), am_email VARCHAR(150),
    yr INT NOT NULL, wk INT NOT NULL, confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_uw (user_id, yr, wk)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$cur_wk = (int) date('W');
$cur_yr = (int) date('o');
$is_week_confirmed = false;
if ($cs = $conn->prepare("SELECT id FROM debt_weekly_confirmations WHERE user_id = ? AND yr = ? AND wk = ? LIMIT 1")) {
    $cs->bind_param("iii", $current_user_id, $cur_yr, $cur_wk);
    $cs->execute();
    if ($cs->get_result()->fetch_assoc()) $is_week_confirmed = true;
    $cs->close();
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
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            color: #334155;
        }

        /* Sticky Header */
        table.debt-table thead th {
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.03em;
            padding: 7px 8px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            z-index: 10;
            white-space: normal;
            line-height: 1.3;
            vertical-align: middle;
            min-width: 52px;
        }

        table.debt-table tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #1e293b;
            transition: background-color 0.15s;
        }

        /* Let long text columns wrap instead of forcing the table wider */
        table.debt-table tbody td.cell-company,
        table.debt-table tbody td.project-tooltip-trigger {
            white-space: normal;
            max-width: 200px;
            line-height: 1.35;
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

        /* Zebra striping theo từng dòng dữ liệu (bỏ qua dòng group header) */
        table.debt-table tbody tr.data-row.row-odd td {
            background-color: #ffffff;
        }

        table.debt-table tbody tr.data-row.row-even td {
            background-color: #f1f5f9;
        }

        table.debt-table tbody tr.data-row:hover td {
            background-color: #e0e7ff;
            cursor: pointer;
        }

        /* Dòng được chọn (click) — đè lên cả striping lẫn hover */
        table.debt-table tbody tr.data-row.row-selected td {
            background-color: #bfdbfe !important;
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

        .pay-partial {
            background-color: #fef3c7;
            color: #b45309;
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
            border: 1px solid #0f172a;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-add:hover {
            background: #334155;
            border-color: #334155;
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

        .group-paid {
            color: #2563eb;
            margin-left: 14px;
            font-weight: 600;
        }

        .group-unpaid {
            color: #dc2626;
            margin-left: 14px;
            font-weight: 600;
        }

        .total-badge {
            margin: 0;
            display: inline-flex;
            align-items: baseline;
            gap: 5px;
            white-space: nowrap;
            font-size: 0.8rem;
            font-weight: 700;
            color: #065f46;
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            padding: 7px 12px;
            border-radius: 8px;
            line-height: 1.1;
        }

        .total-badge .tb-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            opacity: 0.75;
        }

        .total-badge.paid-badge {
            color: #1d4ed8;
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .total-badge.unpaid-badge {
            color: #b91c1c;
            background: #fef2f2;
            border-color: #fca5a5;
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

        /* Filter Sidebar Drawer (giống /debt) */
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
            top: -6px;
            right: -6px;
            background: #2563eb;
            color: white;
            font-size: 10px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .filter-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 2000;
        }

        .filter-sidebar {
            position: fixed;
            top: 0;
            right: -360px;
            width: 360px;
            max-width: 90vw;
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

        .filter-sidebar-header .close {
            cursor: pointer;
            font-size: 1.6rem;
            line-height: 1;
            color: #94a3b8;
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
                    <?php
                    $sel_status = $status_filter; // mảng đã parse ở trên
                    // Đếm số filter đang áp dụng để hiện badge trên nút Bộ lọc (không gồm company vì đã ra ngoài)
                    $mydebt_filter_params = ['q', 'status', 'invoice_status_class', 'year', 'quarter', 'month', 'week'];
                    $mydebt_active = 0;
                    foreach ($mydebt_filter_params as $p) {
                        if (!empty($_GET[$p])) $mydebt_active++;
                    }
                    ?>
                    <!-- Confirm công nợ theo tuần -->
                    <div id="weekConfirmBox" data-confirmed="<?php echo $is_week_confirmed ? '1' : '0'; ?>"
                        data-wk="<?php echo $cur_wk; ?>" data-yr="<?php echo $cur_yr; ?>"
                        style="display:flex; align-items:center; gap:8px; margin-right:6px;">
                        <button type="button" id="weekConfirmBtn" onclick="toggleWeekConfirm()"
                            style="border:none; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13px; cursor:pointer; <?php echo $is_week_confirmed ? 'background:#dcfce7;color:#166534;border:1px solid #86efac;' : 'background:#16a34a;color:#fff;'; ?>">
                            <?php echo $is_week_confirmed ? '✓ Đã confirm Tuần ' . $cur_wk : 'Confirm Tuần ' . $cur_wk; ?>
                        </button>
                        <a href="#" id="weekUnconfirmLink" onclick="toggleWeekConfirm(true);return false;"
                            style="font-size:12px;color:#64748b;text-decoration:none; <?php echo $is_week_confirmed ? '' : 'display:none;'; ?>">Bỏ confirm</a>
                    </div>

                    <!-- Lọc theo công ty (đứng ngoài sidebar) -->
                    <select class="filter-select" onchange="setCompanyFilter(this.value)" title="Lọc theo công ty (từ Odoo)">
                        <option value="">Công ty: Tất cả</option>
                        <?php
                        $company_options = [
                            'AHT TECH' => 'AHT TECH JOINT STOCK COMPANY',
                            'A1VN'     => 'A1 CONSULTING JOINT STOCK COMPANY',
                            'A1C MY'   => 'A1 CONSULTING SDN. BHD.',
                        ];
                        foreach ($company_options as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($company_filter === $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Filter Sidebar Drawer -->
                    <div class="filter-sidebar-overlay" id="filterOverlay" onclick="toggleFilterSidebar()"></div>
                    <div class="filter-sidebar" id="filterSidebar">
                        <div class="filter-sidebar-header">
                            <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Bộ lọc dữ liệu</h3>
                            <span class="close" onclick="toggleFilterSidebar()">&times;</span>
                        </div>
                        <div class="filter-sidebar-body">
                            <form method="GET" id="filterForm">
                                <label class="filter-item-label">Tìm kiếm nhanh</label>
                                <input type="text" name="q" class="search-input"
                                    placeholder="Client, Project, Invoice..."
                                    value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">

                                <label class="filter-item-label">Trạng thái thanh toán</label>
                                <select name="status" class="filter-select" title="Draft = nháp · Not paid = posted còn nợ · Paid = đã thu (partial xuất hiện ở cả Paid lẫn Not paid)">
                                    <option value="">Tất cả</option>
                                    <option value="Draft" <?php echo in_array('Draft', $sel_status) ? 'selected' : ''; ?>>Draft</option>
                                    <option value="Not paid" <?php echo in_array('Not paid', $sel_status) ? 'selected' : ''; ?>>Not paid</option>
                                    <option value="Paid" <?php echo in_array('Paid', $sel_status) ? 'selected' : ''; ?>>Paid</option>
                                </select>

                                <label class="filter-item-label">Phân loại HĐ</label>
                                <select name="invoice_status_class" class="filter-select">
                                    <option value="">Tất cả</option>
                                    <option value="Trắng" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Trắng') ? 'selected' : ''; ?>>Trắng</option>
                                    <option value="Xanh" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Xanh') ? 'selected' : ''; ?>>Xanh (Tốt)</option>
                                    <option value="Tím" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Tím') ? 'selected' : ''; ?>>Tím</option>
                                    <option value="Đỏ" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Đỏ') ? 'selected' : ''; ?>>Đỏ</option>
                                    <option value="PP" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'PP') ? 'selected' : ''; ?>>PP</option>
                                    <option value="Draft" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="Done" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Done') ? 'selected' : ''; ?>>Done</option>
                                    <option value="Chưa xác định" <?php echo (isset($_GET['invoice_status_class']) && $_GET['invoice_status_class'] == 'Chưa xác định') ? 'selected' : ''; ?>>Chưa xác định</option>
                                </select>

                                <label class="filter-item-label">Năm</label>
                                <select name="year" class="filter-select">
                                    <option value="">Tất cả</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                        $sel = (isset($_GET['year']) && $_GET['year'] == $y) ? 'selected' : '';
                                        echo "<option value='$y' $sel>$y</option>";
                                    }
                                    ?>
                                </select>

                                <label class="filter-item-label">Quý</label>
                                <select name="quarter" class="filter-select">
                                    <option value="">Tất cả</option>
                                    <option value="1" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '1') ? 'selected' : ''; ?>>Q1</option>
                                    <option value="2" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '2') ? 'selected' : ''; ?>>Q2</option>
                                    <option value="3" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '3') ? 'selected' : ''; ?>>Q3</option>
                                    <option value="4" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '4') ? 'selected' : ''; ?>>Q4</option>
                                </select>

                                <label class="filter-item-label">Tháng</label>
                                <select name="month" class="filter-select">
                                    <option value="">Tất cả</option>
                                    <?php
                                    for ($m = 1; $m <= 12; $m++) {
                                        $sel = (isset($_GET['month']) && $_GET['month'] == $m) ? 'selected' : '';
                                        $mName = date('F', mktime(0, 0, 0, $m, 1));
                                        echo "<option value='$m' $sel>$mName</option>";
                                    }
                                    ?>
                                </select>

                                <label class="filter-item-label">Cập nhật tuần</label>
                                <select name="week" class="filter-select">
                                    <option value="">Tất cả</option>
                                    <?php
                                    for ($w = 1; $w <= 5; $w++) {
                                        $sel = (isset($_GET['week']) && $_GET['week'] == $w) ? 'selected' : '';
                                        echo "<option value='$w' $sel>Tuần $w</option>";
                                    }
                                    ?>
                                </select>
                                <button type="submit" style="display:none;"></button>
                            </form>
                        </div>
                        <div class="filter-sidebar-footer">
                            <button type="button" class="btn-cancel" style="flex:1;" onclick="window.location.href='?'">Xóa hết</button>
                            <button type="button" class="btn-submit" style="flex:1;" onclick="document.getElementById('filterForm').submit()">Áp dụng</button>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
                        <div class="total-badge" title="Tổng giá trị (theo bộ lọc)">
                            <span class="tb-label">Total</span><?php echo formatVND($total_amount_vnd); ?>
                        </div>
                        <div class="total-badge paid-badge" title="Tổng phần đã thu được">
                            <span class="tb-label">Paid</span><?php echo formatVND($total_paid_vnd); ?>
                        </div>
                        <div class="total-badge unpaid-badge" title="Tổng phần còn nợ">
                            <span class="tb-label">Unpaid</span><?php echo formatVND($total_unpaid_vnd); ?>
                        </div>
                    </div>

                    <button type="button" class="btn-filter-toggle" onclick="toggleFilterSidebar()" style="margin-left: 12px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                        <span>Bộ lọc</span>
                        <?php if ($mydebt_active > 0): ?><span class="filter-badge"><?php echo $mydebt_active; ?></span><?php endif; ?>
                    </button>

                    <button class="btn-add" onclick="openModal('add')" style="margin-left: 10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="3">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Record
                    </button>
                </div>

                <?php if (isset($error_message)): ?>
                    <div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="data-table-wrapper">
                    <table class="debt-table" id="myDebtsTable">
                        <thead>
                            <tr>
                                <th style="width: 30px !important; text-align: center;">#</th>
                                <th style="width: 50px; text-align: center;">Action</th>
                                <th>CTY</th>
                                <th>Sale Team</th>
                                <th>Tên khách hàng</th>
                                <th>Ngày hóa đơn</th>
                                <th>Exp. Prod Date</th>
                                <th>Exp. Pay Date</th>
                                <th>Phân loại HĐ</th>
                                <th>Tiền</th>
                                <th>Số tiền</th>
                                <th>Paid Amount</th>
                                <th>P&L</th>
                                <th>HĐ VAT</th>
                                <th>Trạng thái HĐ</th>
                                <th>Trạng thái TT</th>
                                <th>Tháng TT</th>
                                <th>Cập nhật tuần</th>
                                <th>Tên dự án</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $globalIdx = 1; ?>
                            <?php foreach ($groupedDebts as $monthName => $monthItems): ?>
                                <tr class="group-header">
                                    <td colspan="19">
                                        <?php echo (strpos($monthName, '/') !== false) ? "Tháng $monthName" : $monthName; ?>
                                        <span class="group-total">(Total:
                                            <?php echo formatVND($monthTotals[$monthName]); ?>)</span>
                                        <span class="group-paid">Paid: <?php echo formatVND($monthPaid[$monthName] ?? 0); ?></span>
                                        <span class="group-unpaid">Unpaid: <?php echo formatVND($monthUnpaid[$monthName] ?? 0); ?></span>
                                    </td>
                                </tr>
                                <?php foreach ($monthItems as $d): ?>
                                    <?php
                                    $is_highlight = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $d['id']);
                                    ?>
                                    <tr id="debt-row-<?php echo $d['id']; ?>"
                                        class="data-row <?php echo ($globalIdx % 2 === 0) ? 'row-even' : 'row-odd'; ?> <?php echo $is_highlight ? 'highlight-row' : ''; ?>"
                                        onclick="toggleRowSelect(this)"
                                        ondblclick="openModal('edit', <?php echo $d['id']; ?>)">
                                        <td style="text-align: center;"><?php echo $globalIdx++; ?></td>
                                        <td style="text-align:center; white-space: nowrap;">
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
                                        <td class="cell-company"><?php echo htmlspecialchars($d['client_name'] ?? ''); ?></td>
                                                <td><?php echo formatDate($d['invoice_date']); ?></td>
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
                                                    <?php echo !empty($d['formatted_original']) ? $d['formatted_original'] : ( !empty($d['original_amount']) ? formatCurrency($d['original_amount'], $d['original_currency'] ?? 'USD') : '-'); ?>
                                                </td>
                                                <td class="cell-amount">
                                                    <?php echo formatCurrency($d['amount'] ?? 0, 'VND'); ?>
                                                </td>
                                                <td class="cell-amount" style="color: #059669;">
                                                    <?php
                                                    $paidVnd = (float) ($d['paid_amount_vnd'] ?? 0);
                                                    echo $paidVnd > 0 ? formatCurrency($paidVnd, 'VND') : '-';
                                                    ?>
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
                                                    <?php
                                                    // Use Odoo payment_state (more granular) when available, else fall back to DB payment_status
                                                    $payState = $d['odoo_payment_state'] ?? '';
                                                    if ($payState === 'partial') {
                                                        $payCls = 'pay-partial';
                                                        $payLbl = 'Partial';
                                                    } elseif ($payState === 'paid' || $payState === 'in_payment') {
                                                        $payCls = 'pay-paid';
                                                        $payLbl = 'Paid';
                                                    } elseif ($payState === 'not_paid' || $payState === 'reversed') {
                                                        $payCls = 'pay-not-paid';
                                                        $payLbl = ($payState === 'reversed') ? 'Reversed' : 'Not paid';
                                                    } else {
                                                        // No Odoo match: fall back to stored binary status
                                                        $payCls = (stripos($d['payment_status'] ?? '', 'Not') !== false) ? 'pay-not-paid' : 'pay-paid';
                                                        $payLbl = $d['payment_status'] ?? '';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $payCls; ?>"><?php echo htmlspecialchars($payLbl); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($d['payment_month'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($d['weekly_update'] ?? ''); ?></td>
                                                <td class="project-tooltip-trigger" style="position: relative;">
                                                    <input type="text" value="<?php echo htmlspecialchars($d['project_name'] ?? ''); ?>"
                                                        class="project-autocomplete-input" autocomplete="off"
                                                        onclick="event.stopPropagation();"
                                                        onfocus="this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fff';"
                                                        onblur="setTimeout(() => { if (window.projectSuggestionsBox && projectSuggestionsBox.style.display === 'none') { this.style.borderColor = 'transparent'; this.style.backgroundColor = 'transparent'; updateInline(<?php echo $d['id']; ?>, 'project_name', this.value, this); } }, 300)"
                                                        style="width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-family: inherit; font-size: inherit; outline: none; box-sizing: border-box;">
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
                                required class="project-autocomplete-input" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="currency" id="currency">
                                <option value="USD">USD - US Dollar</option>
                                <option value="VND" selected>VND - Vietnam Dong</option>
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
                // Add mode: amount + currency được phép nhập/chọn
                const amtElAdd = document.getElementById('amount');
                amtElAdd.readOnly = false;
                amtElAdd.style.background = '';
                amtElAdd.style.opacity = '';
                amtElAdd.tabIndex = 0;
                amtElAdd.title = '';
                const curElAdd = document.getElementById('currency');
                curElAdd.style.pointerEvents = '';
                curElAdd.style.background = '';
                curElAdd.style.opacity = '';
                curElAdd.tabIndex = 0;
                curElAdd.title = '';
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
                // Amount: số tiền GỐC (theo tiền hóa đơn), KHÓA không cho sửa (đồng bộ từ Odoo)
                const amtEl = document.getElementById('amount');
                amtEl.value = (data.amount_original != null ? data.amount_original : data.amount);
                amtEl.readOnly = true;
                amtEl.style.background = '#f1f5f9';
                amtEl.style.opacity = '0.65';
                amtEl.tabIndex = -1;
                amtEl.title = 'Số tiền đồng bộ từ Odoo — không sửa được';
                // Currency: tiền tệ GỐC của hóa đơn, KHÓA không cho sửa khi edit
                const curEl = document.getElementById('currency');
                curEl.value = data.currency_original || data.currency || 'VND';
                curEl.style.pointerEvents = 'none';
                curEl.style.background = '#f1f5f9';
                curEl.style.opacity = '0.65';
                curEl.tabIndex = -1;
                curEl.title = 'Tiền tệ theo hóa đơn gốc — không sửa được';
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
        window.projectSuggestionsBox = document.createElement('div');
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

        // Click 1 dòng -> đổi nền (chọn); click lại -> trở về bình thường
        function toggleRowSelect(tr) {
            tr.classList.toggle('row-selected');
        }

        function setCompanyFilter(v) {
            const u = new URL(window.location);
            if (v) u.searchParams.set('company', v); else u.searchParams.delete('company');
            window.location = u.toString();
        }

        function toggleWeekConfirm(forceUnconfirm) {
            const box = document.getElementById('weekConfirmBox');
            const btn = document.getElementById('weekConfirmBtn');
            const link = document.getElementById('weekUnconfirmLink');
            const isConfirmed = box.dataset.confirmed === '1';
            const action = (forceUnconfirm || isConfirmed) ? 'unconfirm' : 'confirm';
            btn.disabled = true;
            const body = 'action=' + action + '&wk=' + box.dataset.wk + '&yr=' + box.dataset.yr;
            fetch('/api/debt_week_confirm.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(r => r.json()).then(d => {
                btn.disabled = false;
                if (!d.success) { alert('Lỗi: ' + (d.error || '')); return; }
                if (d.confirmed) {
                    box.dataset.confirmed = '1';
                    btn.textContent = '✓ Đã confirm Tuần ' + box.dataset.wk;
                    btn.style.cssText = 'border-radius:8px;padding:9px 16px;font-weight:700;font-size:13px;cursor:pointer;background:#dcfce7;color:#166534;border:1px solid #86efac;';
                    link.style.display = '';
                } else {
                    box.dataset.confirmed = '0';
                    btn.textContent = 'Confirm Tuần ' + box.dataset.wk;
                    btn.style.cssText = 'border:none;border-radius:8px;padding:9px 16px;font-weight:700;font-size:13px;cursor:pointer;background:#16a34a;color:#fff;';
                    link.style.display = 'none';
                }
            }).catch(() => { btn.disabled = false; alert('Lỗi kết nối'); });
        }

        function toggleFilterSidebar() {
            const sidebar = document.getElementById('filterSidebar');
            const overlay = document.getElementById('filterOverlay');
            if (!sidebar) return;
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

            function renderStyles() {
                let css = "";
                cols.forEach((th, index) => {
                    const colIndex = index + 1;
                    let w = widths[colIndex];
                    if (w) {
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
