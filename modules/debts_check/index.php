<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';
require_once __DIR__ . '/../../includes/app_settings.php';

// Phải đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Lấy email nếu session chưa có
$full_name = $_SESSION['full_name'] ?? '';
$email = $_SESSION['email'] ?? '';
if (!$email) {
    if ($st = $conn->prepare("SELECT email FROM users WHERE id = ?")) {
        $st->bind_param("i", $_SESSION['user_id']);
        $st->execute();
        if ($r = $st->get_result()->fetch_assoc()) {
            $email = $r['email'] ?? '';
            $_SESSION['email'] = $email;
        }
        $st->close();
    }
}

// ACL: chỉ Hyun Cao & nhantt
$allowed_emails = ['hyun@arrowhitech.com', 'nhanntt@arrowhitech.com'];
$allowed_names  = ['Hyun Cao', 'Nguyen Thi Thanh Nhan'];
$can_access = in_array(strtolower($email), $allowed_emails, true) || in_array($full_name, $allowed_names, true);
if (!$can_access) {
    header("Location: /");
    exit();
}

// Bảng lưu cảnh báo "invoice chưa add vào Debts" gửi cho AM
$conn->query("CREATE TABLE IF NOT EXISTS debt_add_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    odoo_invoice_id VARCHAR(50),
    invoice_name VARCHAR(100),
    company VARCHAR(50),
    am_name VARCHAR(150),
    am_user_id INT DEFAULT NULL,
    amount DECIMAL(18,2) DEFAULT 0,
    currency VARCHAR(10),
    penalty_points INT DEFAULT 0,
    message TEXT,
    sender_id INT DEFAULT NULL,
    is_acknowledged TINYINT DEFAULT 0,
    acknowledged_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$send_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_warnings') {
    $items = json_decode($_POST['warnings_json'] ?? '[]', true);
    if (!is_array($items)) $items = [];
    $penalty = (int) app_setting_get($conn, 'debt_warning_penalty_points', '5');
    $sent = 0; $skipped_noam = 0; $skipped_dup = 0;

    foreach ($items as $it) {
        $oid = trim((string) ($it['id'] ?? ''));
        $invName = trim((string) ($it['name'] ?? ''));
        // Cần ít nhất odoo_invoice_id hoặc số hóa đơn (draft chưa có số vẫn gửi được vì có id)
        if ($oid === '' && $invName === '') continue;
        $amName = trim((string) ($it['am'] ?? ''));
        $amEmail = strtolower(trim((string) ($it['am_email'] ?? '')));
        $company = trim((string) ($it['company'] ?? ''));
        $amount = (float) ($it['amount'] ?? 0);
        $currency = trim((string) ($it['currency'] ?? ''));
        $dispName = $invName !== '' ? $invName : ('nháp #' . $oid);

        // Map AM theo EMAIL (login Odoo) -> users.email — chuẩn nhất, tránh sai do tên có dấu/nickname
        $amUserId = null;
        if ($amEmail !== '') {
            if ($us = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1")) {
                $us->bind_param("s", $amEmail);
                $us->execute();
                if ($u = $us->get_result()->fetch_assoc()) $amUserId = (int) $u['id'];
                $us->close();
            }
        }
        if (!$amUserId) { $skipped_noam++; continue; }

        // Dedupe (chưa acknowledge): ưu tiên theo odoo_invoice_id, không có thì theo số hóa đơn
        $dup = false;
        if ($oid !== '') {
            if ($ds = $conn->prepare("SELECT id FROM debt_add_warnings WHERE odoo_invoice_id = ? AND am_user_id = ? AND is_acknowledged = 0 LIMIT 1")) {
                $ds->bind_param("si", $oid, $amUserId); $ds->execute();
                if ($ds->get_result()->fetch_assoc()) $dup = true;
                $ds->close();
            }
        } else {
            if ($ds = $conn->prepare("SELECT id FROM debt_add_warnings WHERE invoice_name = ? AND am_user_id = ? AND is_acknowledged = 0 LIMIT 1")) {
                $ds->bind_param("si", $invName, $amUserId); $ds->execute();
                if ($ds->get_result()->fetch_assoc()) $dup = true;
                $ds->close();
            }
        }
        if ($dup) { $skipped_dup++; continue; }

        $msg = "Hóa đơn $dispName" . ($company ? " ($company)" : '') . " chưa được add vào Debts. Vui lòng add ngay, nếu không sẽ bị trừ $penalty điểm KPI.";
        if ($ins = $conn->prepare("INSERT INTO debt_add_warnings
            (odoo_invoice_id, invoice_name, company, am_name, am_user_id, amount, currency, penalty_points, message, sender_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
            $sid = (int) $_SESSION['user_id'];
            $ins->bind_param("ssssidsisi", $oid, $invName, $company, $amName, $amUserId, $amount, $currency, $penalty, $msg, $sid);
            if ($ins->execute()) $sent++;
            $ins->close();
        }
    }
    $send_result = "Đã gửi $sent cảnh báo (−$penalty điểm/HĐ)."
        . ($skipped_dup ? " Bỏ qua $skipped_dup (đã gửi trước đó)." : '')
        . ($skipped_noam ? " $skipped_noam HĐ chưa map được AM theo email (không gửi)." : '');
}

function shortCompanyName($odooName)
{
    $n = strtoupper(trim((string) $odooName));
    if ($n === '') return '';
    if (strpos($n, 'AHT TECH') !== false) return 'AHT TECH';
    if (strpos($n, 'SDN') !== false || strpos($n, 'BHD') !== false) return 'A1C MY';
    if (strpos($n, 'A1 CONSULTING') !== false || strpos($n, 'A1C') !== false || strpos($n, 'A1 ') !== false) return 'A1VN';
    return (string) $odooName;
}

// Hóa đơn nội bộ (intercompany): khách hàng chính là 1 công ty trong nhóm
function isInternalCustomer($partnerName)
{
    $n = strtoupper(trim((string) $partnerName));
    if ($n === '') return false;
    return strpos($n, 'AHT TECH') !== false
        || strpos($n, 'A1 CONSULTING') !== false
        || strpos($n, 'A1C CONSULTING') !== false;
}

$year = intval($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) $year = (int) date('Y');
$from = sprintf('%04d-01-01', $year);
$to   = sprintf('%04d-12-31', $year);

$tab = $_GET['tab'] ?? 'missing';
if (!in_array($tab, ['missing', 'incomplete', 'confirm'], true)) $tab = 'missing';

// ── Dữ liệu cho tab "Confirm tuần" ──
$confirm_done = [];   // AM đã confirm tuần đang xem
$confirm_pending = []; // AM (is_am_bd) chưa confirm
$cwk = isset($_GET['cwk']) ? (int) $_GET['cwk'] : (int) date('W');
$cyr = isset($_GET['cyr']) ? (int) $_GET['cyr'] : (int) date('o');
if ($cwk < 1 || $cwk > 53) $cwk = (int) date('W');
if ($cyr < 2000 || $cyr > 2100) $cyr = (int) date('o');
if ($tab === 'confirm') {
    $conn->query("CREATE TABLE IF NOT EXISTS debt_weekly_confirmations (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, am_name VARCHAR(150), am_email VARCHAR(150),
        yr INT NOT NULL, wk INT NOT NULL, confirmed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_uw (user_id, yr, wk)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $confirmedIds = [];
    if ($cc = $conn->prepare("SELECT user_id, am_name, am_email, confirmed_at FROM debt_weekly_confirmations WHERE yr = ? AND wk = ? ORDER BY confirmed_at ASC")) {
        $cc->bind_param("ii", $cyr, $cwk);
        $cc->execute();
        $rr = $cc->get_result();
        while ($x = $rr->fetch_assoc()) { $confirm_done[] = $x; $confirmedIds[(int) $x['user_id']] = true; }
        $cc->close();
    }
    // AM (is_am_bd) chưa confirm
    $ur = $conn->query("SELECT id, full_name, email FROM users WHERE is_am_bd = 1 ORDER BY full_name");
    if ($ur) {
        while ($u = $ur->fetch_assoc()) {
            if (!isset($confirmedIds[(int) $u['id']])) $confirm_pending[] = $u;
        }
    }
}

$error = '';
$missing = [];
$total_inv = 0;
$in_debts_count = 0;
$internal_skipped = 0;
$base_url = '';
$incomplete = []; // debts thiếu Exp. Pay Date / Phân loại HĐ

try {
    if ($tab === 'missing') {
        // ===== TAB 1: Invoice trên Odoo chưa add vào Debts =====
        $r = $conn->query("SELECT odoo_url FROM odoo_settings ORDER BY id DESC LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) $base_url = rtrim($row['odoo_url'], '/');

        $odoo = new OdooAPI();
        $currencies = $odoo->getCurrencies();

        // Lọc theo ngày hạch toán `date` (cả posted lẫn draft đều có), loại hóa đơn đã hủy.
        $fields = ['id', 'name', 'invoice_user_id', 'partner_id', 'amount_total', 'currency_id', 'invoice_date', 'date', 'create_date', 'state', 'payment_state', 'company_id'];
        $domain = [
            ['move_type', '=', 'out_invoice'],
            ['state', '!=', 'cancel'],
            ['date', '>=', $from],
            ['date', '<=', $to],
        ];
        $invs = $odoo->searchRead('account.move', $domain, $fields, 100000, 0);
        if (!is_array($invs)) $invs = [];

        // Map salesperson (invoice_user_id) -> EMAIL (login Odoo) để map AM theo email.
        $spIds = [];
        foreach ($invs as $i) {
            if (is_array($i['invoice_user_id'] ?? null)) $spIds[(int) $i['invoice_user_id'][0]] = true;
        }
        $spEmail = [];
        if ($spIds) {
            $users = $odoo->searchRead('res.users', [['id', 'in', array_keys($spIds)]], ['id', 'login'], 1000, 0);
            if (is_array($users)) {
                foreach ($users as $u) $spEmail[(int) $u['id']] = strtolower(trim((string) ($u['login'] ?? '')));
            }
        }

        // Khóa đối chiếu: ưu tiên odoo_invoice_id (DUY NHẤT toàn cục, không trùng giữa 3 công ty).
        // Số hóa đơn (tên) chỉ dùng fallback cho debts chưa có odoo_invoice_id (tránh trùng cross-company).
        $inDebtsById = [];
        $inDebtsByName = [];
        $dr = $conn->query("SELECT odoo_invoice_id, vat_invoice FROM debts");
        while ($x = $dr->fetch_assoc()) {
            $oid = (string) ($x['odoo_invoice_id'] ?? '');
            if ($oid !== '' && $oid !== '0') {
                $inDebtsById[$oid] = true;
            } elseif (!empty($x['vat_invoice'])) {
                $inDebtsByName[trim($x['vat_invoice'])] = true; // chỉ debts chưa link id
            }
        }

        foreach ($invs as $i) {
            $name = trim((string) ($i['name'] ?? ''));
            // KHÔNG bỏ qua draft chưa có số — vẫn đối chiếu được vì có odoo_invoice_id (duy nhất)

            $partnerName = is_array($i['partner_id']) ? $i['partner_id'][1] : '';
            if (isInternalCustomer($partnerName)) { $internal_skipped++; continue; }

            $total_inv++;
            $iid = (string) ($i['id'] ?? '');
            if (isset($inDebtsById[$iid]) || ($name !== '' && isset($inDebtsByName[$name]))) { $in_debts_count++; continue; }

            $cur = is_array($i['currency_id']) ? $i['currency_id'][1] : 'VND';
            $rate = isset($currencies[$cur]['rate']) ? (float) $currencies[$cur]['rate'] : 0;
            $amtVnd = ($rate > 0) ? ((float) $i['amount_total'] / $rate) : (float) $i['amount_total'];

            // Ngày tham chiếu: ưu tiên invoice_date, draft chưa có thì dùng ngày hạch toán `date`
            $refDate = !empty($i['invoice_date']) ? $i['invoice_date'] : ($i['date'] ?? '');

            $missing[] = [
                'id'       => $i['id'],
                'name'     => $name,
                'am'       => is_array($i['invoice_user_id']) ? $i['invoice_user_id'][1] : '',
                'am_email' => is_array($i['invoice_user_id']) ? ($spEmail[(int) $i['invoice_user_id'][0]] ?? '') : '',
                'customer' => is_array($i['partner_id']) ? $i['partner_id'][1] : '',
                'company'  => is_array($i['company_id']) ? shortCompanyName($i['company_id'][1]) : '',
                'amount'   => (float) $i['amount_total'],
                'currency' => $cur,
                'amount_vnd' => $amtVnd,
                'date'     => $refDate,
                'created'  => $i['create_date'] ?? '',
                // Số ngày chưa add = tính từ ngày TẠO trên Odoo (create_date, luôn là quá khứ);
                // KHÔNG dùng invoice_date vì draft có thể đặt ngày tương lai -> ra số âm. Kẹp >= 0.
                'days'     => !empty($i['create_date'])
                    ? max(0, (int) floor((time() - strtotime($i['create_date'])) / 86400))
                    : (!empty($refDate) ? max(0, (int) floor((time() - strtotime($refDate)) / 86400)) : null),
                'state'    => $i['state'] ?? '',
                'pay'      => $i['payment_state'] ?? '',
            ];
        }

        usort($missing, function ($a, $b) {
            return [$a['company'], $a['name']] <=> [$b['company'], $b['name']];
        });

        // ── Filters cho tab "Invoice chưa add" ──
        $amOptions = [];
        foreach ($missing as $m) {
            if (!empty($m['am'])) $amOptions[$m['am']] = true;
        }
        $amOptions = array_keys($amOptions);
        sort($amOptions);

        $f_company = trim((string) ($_GET['f_company'] ?? ''));
        $f_am      = trim((string) ($_GET['f_am'] ?? ''));
        $f_state   = trim((string) ($_GET['f_state'] ?? ''));
        $f_q       = trim((string) ($_GET['f_q'] ?? ''));
        $f_q_low   = mb_strtolower($f_q);

        $missingView = array_values(array_filter($missing, function ($m) use ($f_company, $f_am, $f_state, $f_q_low) {
            if ($f_company !== '' && $m['company'] !== $f_company) return false;
            if ($f_am !== '' && $m['am'] !== $f_am) return false;
            if ($f_state !== '' && $m['state'] !== $f_state) return false;
            if ($f_q_low !== '') {
                $hay = mb_strtolower(($m['name'] ?? '') . ' ' . ($m['customer'] ?? ''));
                if (mb_strpos($hay, $f_q_low) === false) return false;
            }
            return true;
        }));
        $view_vnd_total = array_sum(array_column($missingView, 'amount_vnd'));
        $has_filter = ($f_company !== '' || $f_am !== '' || $f_state !== '' || $f_q !== '');
    } else {
        // ===== TAB 2: Debts thiếu Exp. Pay Date HOẶC Phân loại HĐ =====
        // Company lấy từ Odoo (cache) để chuẩn, vì cột company trong DB đang cứng "AHT TECH".
        $odoo = new OdooAPI();
        $odoo_map = $odoo->getInvoiceMap();
        $odoo_name_map = [];
        foreach ($odoo_map as $iv) {
            if (!empty($iv['name'])) $odoo_name_map[$iv['name']] = $iv;
        }

        $yr = (int) $year;
        $sql = "SELECT d.id, d.company, d.odoo_invoice_id, d.vat_invoice, d.am, d.client_name, d.project_name,
                       d.invoice_date, d.amount, d.currency, d.expected_payment_date, d.invoice_status_class,
                       st.name AS team_name
                FROM debts d
                LEFT JOIN sale_teams st ON d.sale_team_id = st.id
                WHERE YEAR(d.invoice_date) = $yr
                  AND LOWER(TRIM(COALESCE(d.payment_status, ''))) <> 'paid'   -- bỏ qua HĐ đã thanh toán
                  AND (
                        d.expected_payment_date IS NULL
                     OR d.invoice_status_class IS NULL OR TRIM(d.invoice_status_class) = ''
                  )
                ORDER BY d.invoice_date DESC, d.id DESC";
        $ir = $conn->query($sql);
        if ($ir) {
            while ($x = $ir->fetch_assoc()) {
                // Suy ra company từ Odoo invoice (theo odoo_invoice_id hoặc số hóa đơn)
                $iv = null;
                $oid = (string) ($x['odoo_invoice_id'] ?? '');
                if ($oid !== '' && isset($odoo_map[$oid])) $iv = $odoo_map[$oid];
                elseif (!empty($x['vat_invoice']) && isset($odoo_name_map[$x['vat_invoice']])) $iv = $odoo_name_map[$x['vat_invoice']];
                if ($iv && isset($iv['company_id']) && is_array($iv['company_id'])) {
                    $x['company'] = shortCompanyName($iv['company_id'][1]);
                }
                $incomplete[] = $x;
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

function fmtMoney($n) { return number_format((float) $n, 0, ',', '.'); }
function fmtDate($d) { return ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : ''; }
$missing_vnd_total = array_sum(array_column($missing, 'amount_vnd'));
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debts Check</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { overflow-x: hidden; }
        .dc-wrap { padding: 1.5rem; }
        .dc-controls {
            display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
            background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1rem;
        }
        .dc-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 1rem; }
        .dc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; }
        .dc-card .lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; }
        .dc-card .val { font-size: 26px; font-weight: 700; margin-top: 6px; }
        .dc-card.total .val { color: #0f172a; }
        .dc-card.ok .val { color: #059669; }
        .dc-card.miss .val { color: #dc2626; }
        .dc-card .sub { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .dc-select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #fff; cursor: pointer; outline: none; }
        table.dc-table { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; font-size: 13px; }
        table.dc-table th { background: #f8fafc; color: #475569; text-align: left; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        table.dc-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
        table.dc-table tr:hover td { background: #f8fafc; }
        .dc-amt { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 600; }
        .dc-badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .b-posted { background: #dcfce7; color: #166534; }
        .b-draft { background: #fef9c3; color: #854d0e; }
        .b-cancel { background: #fee2e2; color: #991b1b; }
        .b-paid { background: #dcfce7; color: #166534; }
        .b-partial { background: #fef3c7; color: #b45309; }
        .b-notpaid { background: #fee2e2; color: #dc2626; }
        .dc-link { color: #2563eb; text-decoration: none; font-weight: 600; }
        .dc-link:hover { text-decoration: underline; }
        .dc-empty { text-align: center; padding: 40px; color: #94a3b8; }
        .co-tag { display:inline-block; padding:2px 8px; border-radius:6px; background:#f1f5f9; color:#475569; font-size:11px; font-weight:600; }
        .dc-tabs { display:flex; gap:6px; }
        .dc-tab { padding:8px 14px; border-radius:8px; font-size:14px; font-weight:600; color:#64748b; text-decoration:none; border:1px solid transparent; }
        .dc-tab:hover { background:#f1f5f9; color:#334155; }
        .dc-tab.active { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
        .dc-sendbtn { background:#dc2626; color:#fff; border:none; padding:9px 16px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; }
        .dc-sendbtn:hover { background:#b91c1c; }
        .dc-sendbtn:disabled { background:#fca5a5; cursor:not-allowed; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Debts Check';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="dc-wrap">
                <div class="dc-controls">
                    <div class="dc-tabs">
                        <a href="?tab=missing&year=<?php echo $year; ?>" class="dc-tab <?php echo $tab === 'missing' ? 'active' : ''; ?>">Invoice chưa add (Odoo)</a>
                        <a href="?tab=incomplete&year=<?php echo $year; ?>" class="dc-tab <?php echo $tab === 'incomplete' ? 'active' : ''; ?>">Debts thiếu thông tin</a>
                        <a href="?tab=confirm&year=<?php echo $year; ?>" class="dc-tab <?php echo $tab === 'confirm' ? 'active' : ''; ?>">Confirm theo tuần</a>
                    </div>
                    <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
                        <label style="font-size:13px; color:#64748b; font-weight:600;">Năm:</label>
                        <select class="dc-select" onchange="window.location='?tab=<?php echo $tab; ?>&year='+this.value">
                            <?php $cy = (int) date('Y'); for ($y = $cy; $y >= $cy - 4; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y === $year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div style="background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;padding:1rem;border-radius:8px;">
                        Lỗi khi lấy dữ liệu từ Odoo: <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php elseif ($tab === 'incomplete'): ?>
                    <?php
                    $miss_paydate = 0; $miss_class = 0;
                    foreach ($incomplete as $d) {
                        $noPay = empty($d['expected_payment_date']) || $d['expected_payment_date'] === '0000-00-00';
                        $noCls = trim((string) $d['invoice_status_class']) === '';
                        if ($noPay) $miss_paydate++;
                        if ($noCls) $miss_class++;
                    }
                    ?>
                    <div class="dc-cards">
                        <div class="dc-card miss">
                            <div class="lbl">Debts thiếu thông tin (<?php echo $year; ?>)</div>
                            <div class="val"><?php echo fmtMoney(count($incomplete)); ?></div>
                            <div class="sub">thiếu Exp. Pay Date hoặc Phân loại HĐ</div>
                        </div>
                        <div class="dc-card miss">
                            <div class="lbl">Thiếu Exp. Pay Date</div>
                            <div class="val"><?php echo fmtMoney($miss_paydate); ?></div>
                            <div class="sub">chưa có ngày dự kiến thu</div>
                        </div>
                        <div class="dc-card miss">
                            <div class="lbl">Thiếu Phân loại HĐ</div>
                            <div class="val"><?php echo fmtMoney($miss_class); ?></div>
                            <div class="sub">chưa phân loại</div>
                        </div>
                    </div>

                    <table class="dc-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>CTY</th>
                                <th>Số hóa đơn</th>
                                <th>AM</th>
                                <th>Khách hàng</th>
                                <th>Tên dự án</th>
                                <th>Ngày HĐ</th>
                                <th>Exp. Pay Date</th>
                                <th>Phân loại HĐ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($incomplete)): ?>
                                <tr><td colspan="9" class="dc-empty">🎉 Không có debts nào thiếu thông tin trong năm <?php echo $year; ?>.</td></tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($incomplete as $d):
                                    $noPay = empty($d['expected_payment_date']) || $d['expected_payment_date'] === '0000-00-00';
                                    $noCls = trim((string) $d['invoice_status_class']) === '';
                                ?>
                                    <tr>
                                        <td><?php echo $idx++; ?></td>
                                        <td><span class="co-tag"><?php echo htmlspecialchars($d['company']); ?></span></td>
                                        <td style="font-weight:600;"><?php echo htmlspecialchars($d['vat_invoice'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($d['am']); ?></td>
                                        <td><?php echo htmlspecialchars($d['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($d['project_name']); ?></td>
                                        <td><?php echo fmtDate($d['invoice_date']); ?></td>
                                        <td><?php echo $noPay ? '<span class="dc-badge b-notpaid">Thiếu</span>' : fmtDate($d['expected_payment_date']); ?></td>
                                        <td><?php echo $noCls ? '<span class="dc-badge b-notpaid">Thiếu</span>' : htmlspecialchars($d['invoice_status_class']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($incomplete)): ?>
                        <tfoot>
                            <tr style="background:#f8fafc; font-weight:700;">
                                <td colspan="7" style="text-align:right;">TỔNG</td>
                                <td><?php echo fmtMoney($miss_paydate); ?> thiếu</td>
                                <td><?php echo fmtMoney($miss_class); ?> thiếu</td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                <?php elseif ($tab === 'confirm'): ?>
                    <?php
                    // Dropdown 12 tuần gần nhất
                    $weekOpts = [];
                    for ($i = 0; $i < 12; $i++) {
                        $t = strtotime("-$i week");
                        $weekOpts[] = ['wk' => (int) date('W', $t), 'yr' => (int) date('o', $t)];
                    }
                    ?>
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
                        <div style="font-weight:700; font-size:16px; color:#0f172a;">AM đã confirm — Tuần <?php echo $cwk; ?>/<?php echo $cyr; ?></div>
                        <select class="dc-select" onchange="var p=this.value.split('|');window.location='?tab=confirm&year=<?php echo $year; ?>&cwk='+p[0]+'&cyr='+p[1];" style="margin-left:auto;">
                            <?php foreach ($weekOpts as $w): ?>
                                <option value="<?php echo $w['wk'] . '|' . $w['yr']; ?>" <?php echo ($w['wk'] === $cwk && $w['yr'] === $cyr) ? 'selected' : ''; ?>>
                                    Tuần <?php echo $w['wk']; ?> / <?php echo $w['yr']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dc-cards" style="grid-template-columns: repeat(2,1fr);">
                        <div class="dc-card ok">
                            <div class="lbl">Đã confirm</div>
                            <div class="val"><?php echo count($confirm_done); ?></div>
                            <div class="sub">Tuần <?php echo $cwk; ?>/<?php echo $cyr; ?></div>
                        </div>
                        <div class="dc-card miss">
                            <div class="lbl">Chưa confirm (AM/BD)</div>
                            <div class="val"><?php echo count($confirm_pending); ?></div>
                            <div class="sub">trong tổng AM/BD</div>
                        </div>
                    </div>

                    <table class="dc-table" style="margin-bottom:20px;">
                        <thead><tr><th>#</th><th>AM đã confirm</th><th>Email</th><th>Thời điểm confirm</th></tr></thead>
                        <tbody>
                            <?php if (empty($confirm_done)): ?>
                                <tr><td colspan="4" class="dc-empty">Chưa có AM nào confirm tuần này.</td></tr>
                            <?php else: $k = 1; foreach ($confirm_done as $c): ?>
                                <tr>
                                    <td><?php echo $k++; ?></td>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($c['am_name'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($c['am_email'] ?: ''); ?></td>
                                    <td><?php echo $c['confirmed_at'] ? date('d/m/Y H:i', strtotime($c['confirmed_at'])) : ''; ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($confirm_pending)): ?>
                    <div style="font-weight:700; font-size:14px; color:#b45309; margin:8px 0;">Chưa confirm</div>
                    <table class="dc-table">
                        <thead><tr><th>#</th><th>AM/BD</th><th>Email</th></tr></thead>
                        <tbody>
                            <?php $k = 1; foreach ($confirm_pending as $u): ?>
                                <tr>
                                    <td><?php echo $k++; ?></td>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($send_result): ?>
                        <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-weight:600;">✓ <?php echo htmlspecialchars($send_result); ?></div>
                    <?php endif; ?>
                    <div class="dc-cards">
                        <div class="dc-card total">
                            <div class="lbl">Tổng invoice <?php echo $year; ?> (Odoo)</div>
                            <div class="val"><?php echo fmtMoney($total_inv); ?></div>
                            <div class="sub">out_invoice · đã bỏ <?php echo fmtMoney($internal_skipped); ?> HĐ nội bộ</div>
                        </div>
                        <div class="dc-card ok">
                            <div class="lbl">Đã có trong Debts</div>
                            <div class="val"><?php echo fmtMoney($in_debts_count); ?></div>
                            <div class="sub">khớp theo số hóa đơn</div>
                        </div>
                        <div class="dc-card miss">
                            <div class="lbl">CHƯA add vào Debts</div>
                            <div class="val"><?php echo fmtMoney(count($missing)); ?></div>
                            <div class="sub">≈ <?php echo fmtMoney($missing_vnd_total); ?> ₫</div>
                        </div>
                    </div>

                    <!-- Filter bar -->
                    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:12px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                        <input type="hidden" name="tab" value="missing">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <input type="text" name="f_q" value="<?php echo htmlspecialchars($f_q); ?>" placeholder="Tìm số HĐ / khách hàng..." class="dc-select" style="min-width:220px;">
                        <select name="f_company" class="dc-select">
                            <option value="">Công ty: Tất cả</option>
                            <?php foreach (['AHT TECH', 'A1VN', 'A1C MY'] as $co): ?>
                                <option value="<?php echo $co; ?>" <?php echo $f_company === $co ? 'selected' : ''; ?>><?php echo $co; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="f_am" class="dc-select">
                            <option value="">AM: Tất cả</option>
                            <?php foreach ($amOptions as $amo): ?>
                                <option value="<?php echo htmlspecialchars($amo); ?>" <?php echo $f_am === $amo ? 'selected' : ''; ?>><?php echo htmlspecialchars($amo); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="f_state" class="dc-select">
                            <option value="">Trạng thái: Tất cả</option>
                            <option value="posted" <?php echo $f_state === 'posted' ? 'selected' : ''; ?>>Posted</option>
                            <option value="draft" <?php echo $f_state === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                        <button type="submit" class="dc-sendbtn" style="background:#4f46e5;">Lọc</button>
                        <?php if ($has_filter): ?>
                            <a href="?tab=missing&year=<?php echo $year; ?>" style="font-size:13px; color:#64748b; text-decoration:none;">Xóa lọc</a>
                        <?php endif; ?>
                        <span style="margin-left:auto; font-size:13px; color:#64748b; font-weight:600;">Hiển thị <?php echo count($missingView); ?>/<?php echo count($missing); ?> HĐ</span>
                    </form>

                    <form method="POST" id="warnForm">
                        <input type="hidden" name="action" value="send_warnings">
                        <input type="hidden" name="warnings_json" id="warnings_json">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                            <button type="button" class="dc-sendbtn" onclick="sendWarnings()">⚠ Gửi cảnh báo (<span id="selCount">0</span>)</button>
                            <span style="font-size:12px; color:#94a3b8;">Tích chọn các HĐ cần cảnh báo AM — sẽ trừ điểm KPI theo cấu hình.</span>
                        </div>

                    <table class="dc-table">
                        <thead>
                            <tr>
                                <th style="width:34px; text-align:center;"><input type="checkbox" id="selAll" onclick="toggleAll(this)"></th>
                                <th>#</th>
                                <th>CTY</th>
                                <th>Số hóa đơn</th>
                                <th>AM</th>
                                <th>Khách hàng</th>
                                <th>Ngày HĐ</th>
                                <th>Ngày tạo<br>(Odoo)</th>
                                <th style="text-align:center;">Số ngày<br>chưa add</th>
                                <th style="text-align:right;">Số tiền</th>
                                <th style="text-align:right;">≈ VND</th>
                                <th>HĐ</th>
                                <th>TT</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($missingView)): ?>
                                <tr><td colspan="14" class="dc-empty"><?php echo $has_filter ? 'Không có HĐ khớp bộ lọc.' : ('🎉 Tất cả invoice năm ' . $year . ' đã có trong Debts.'); ?></td></tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($missingView as $m):
                                    $sb = $m['state'] === 'posted' ? 'b-posted' : ($m['state'] === 'draft' ? 'b-draft' : 'b-cancel');
                                    $pb = ($m['pay'] === 'paid' || $m['pay'] === 'in_payment') ? 'b-paid' : ($m['pay'] === 'partial' ? 'b-partial' : 'b-notpaid');
                                ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <input type="checkbox" class="warn-cb" onchange="updateSel()"
                                                data-id="<?php echo htmlspecialchars($m['id']); ?>"
                                                data-name="<?php echo htmlspecialchars($m['name']); ?>"
                                                data-company="<?php echo htmlspecialchars($m['company']); ?>"
                                                data-am="<?php echo htmlspecialchars($m['am']); ?>"
                                                data-am-email="<?php echo htmlspecialchars($m['am_email'] ?? ''); ?>"
                                                data-amount="<?php echo htmlspecialchars($m['amount']); ?>"
                                                data-currency="<?php echo htmlspecialchars($m['currency']); ?>">
                                        </td>
                                        <td><?php echo $idx++; ?></td>
                                        <td><span class="co-tag"><?php echo htmlspecialchars($m['company']); ?></span></td>
                                        <td style="font-weight:600;"><?php echo $m['name'] !== '' ? htmlspecialchars($m['name']) : '<span style="color:#b45309;font-style:italic;">(Draft chưa có số)</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($m['am']); ?></td>
                                        <td><?php echo htmlspecialchars($m['customer']); ?></td>
                                        <td><?php echo fmtDate($m['date']); ?></td>
                                        <td style="white-space:nowrap; color:#64748b;"><?php echo $m['created'] ? date('d/m/Y H:i', strtotime($m['created'])) : '—'; ?></td>
                                        <td style="text-align:center; font-weight:700; <?php echo ($m['days'] !== null && $m['days'] > 14) ? 'color:#dc2626;' : (($m['days'] !== null && $m['days'] > 7) ? 'color:#b45309;' : 'color:#475569;'); ?>">
                                            <?php echo $m['days'] !== null ? $m['days'] . ' ngày' : '—'; ?>
                                        </td>
                                        <td class="dc-amt"><?php echo fmtMoney($m['amount']) . ' ' . htmlspecialchars($m['currency']); ?></td>
                                        <td class="dc-amt" style="color:#64748b;"><?php echo fmtMoney($m['amount_vnd']); ?></td>
                                        <td><span class="dc-badge <?php echo $sb; ?>"><?php echo htmlspecialchars($m['state']); ?></span></td>
                                        <td><span class="dc-badge <?php echo $pb; ?>"><?php echo htmlspecialchars($m['pay']); ?></span></td>
                                        <td><a class="dc-link" href="<?php echo htmlspecialchars($base_url . '/odoo/action-account.move_action/' . $m['id']); ?>" target="_blank">Mở ↗</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($missingView)): ?>
                        <tfoot>
                            <tr style="background:#f8fafc; font-weight:700;">
                                <td colspan="10" style="text-align:right;">TỔNG (<?php echo fmtMoney(count($missingView)); ?> HĐ)</td>
                                <td class="dc-amt"><?php echo fmtMoney($view_vnd_total); ?> ₫</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        function toggleAll(cb) {
            document.querySelectorAll('.warn-cb').forEach(c => c.checked = cb.checked);
            updateSel();
        }
        function updateSel() {
            const n = document.querySelectorAll('.warn-cb:checked').length;
            const el = document.getElementById('selCount');
            if (el) el.textContent = n;
        }
        function sendWarnings() {
            const checked = Array.from(document.querySelectorAll('.warn-cb:checked'));
            if (checked.length === 0) { alert('Hãy tích chọn ít nhất 1 hóa đơn.'); return; }
            if (!confirm('Gửi cảnh báo cho ' + checked.length + ' hóa đơn? AM sẽ bị trừ điểm KPI theo cấu hình.')) return;
            const items = checked.map(c => ({
                id: c.dataset.id, name: c.dataset.name, company: c.dataset.company,
                am: c.dataset.am, am_email: c.dataset.amEmail, amount: c.dataset.amount, currency: c.dataset.currency
            }));
            document.getElementById('warnings_json').value = JSON.stringify(items);
            document.getElementById('warnForm').submit();
        }
    </script>
</body>

</html>
