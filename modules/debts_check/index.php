<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';

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
if (!in_array($tab, ['missing', 'incomplete'], true)) $tab = 'missing';

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

        $fields = ['id', 'name', 'invoice_user_id', 'partner_id', 'amount_total', 'currency_id', 'invoice_date', 'state', 'payment_state', 'company_id'];
        $domain = [
            ['move_type', '=', 'out_invoice'],
            ['invoice_date', '>=', $from],
            ['invoice_date', '<=', $to],
        ];
        $invs = $odoo->searchRead('account.move', $domain, $fields, 100000, 0);
        if (!is_array($invs)) $invs = [];

        $inDebts = [];
        $dr = $conn->query("SELECT DISTINCT vat_invoice FROM debts WHERE vat_invoice IS NOT NULL AND vat_invoice <> ''");
        while ($x = $dr->fetch_assoc()) $inDebts[trim($x['vat_invoice'])] = true;

        foreach ($invs as $i) {
            $name = trim((string) ($i['name'] ?? ''));
            if ($name === '' || $name === '/') continue;

            $partnerName = is_array($i['partner_id']) ? $i['partner_id'][1] : '';
            if (isInternalCustomer($partnerName)) { $internal_skipped++; continue; }

            $total_inv++;
            if (isset($inDebts[$name])) { $in_debts_count++; continue; }

            $cur = is_array($i['currency_id']) ? $i['currency_id'][1] : 'VND';
            $rate = isset($currencies[$cur]['rate']) ? (float) $currencies[$cur]['rate'] : 0;
            $amtVnd = ($rate > 0) ? ((float) $i['amount_total'] / $rate) : (float) $i['amount_total'];

            $missing[] = [
                'id'       => $i['id'],
                'name'     => $name,
                'am'       => is_array($i['invoice_user_id']) ? $i['invoice_user_id'][1] : '',
                'customer' => is_array($i['partner_id']) ? $i['partner_id'][1] : '',
                'company'  => is_array($i['company_id']) ? shortCompanyName($i['company_id'][1]) : '',
                'amount'   => (float) $i['amount_total'],
                'currency' => $cur,
                'amount_vnd' => $amtVnd,
                'date'     => $i['invoice_date'] ?? '',
                'state'    => $i['state'] ?? '',
                'pay'      => $i['payment_state'] ?? '',
            ];
        }

        usort($missing, function ($a, $b) {
            return [$a['company'], $a['name']] <=> [$b['company'], $b['name']];
        });
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
                    </table>
                <?php else: ?>
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

                    <table class="dc-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>CTY</th>
                                <th>Số hóa đơn</th>
                                <th>AM</th>
                                <th>Khách hàng</th>
                                <th>Ngày HĐ</th>
                                <th style="text-align:right;">Số tiền</th>
                                <th style="text-align:right;">≈ VND</th>
                                <th>HĐ</th>
                                <th>TT</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($missing)): ?>
                                <tr><td colspan="11" class="dc-empty">🎉 Tất cả invoice năm <?php echo $year; ?> đã có trong Debts.</td></tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($missing as $m):
                                    $sb = $m['state'] === 'posted' ? 'b-posted' : ($m['state'] === 'draft' ? 'b-draft' : 'b-cancel');
                                    $pb = ($m['pay'] === 'paid' || $m['pay'] === 'in_payment') ? 'b-paid' : ($m['pay'] === 'partial' ? 'b-partial' : 'b-notpaid');
                                ?>
                                    <tr>
                                        <td><?php echo $idx++; ?></td>
                                        <td><span class="co-tag"><?php echo htmlspecialchars($m['company']); ?></span></td>
                                        <td style="font-weight:600;"><?php echo htmlspecialchars($m['name']); ?></td>
                                        <td><?php echo htmlspecialchars($m['am']); ?></td>
                                        <td><?php echo htmlspecialchars($m['customer']); ?></td>
                                        <td><?php echo fmtDate($m['date']); ?></td>
                                        <td class="dc-amt"><?php echo fmtMoney($m['amount']) . ' ' . htmlspecialchars($m['currency']); ?></td>
                                        <td class="dc-amt" style="color:#64748b;"><?php echo fmtMoney($m['amount_vnd']); ?></td>
                                        <td><span class="dc-badge <?php echo $sb; ?>"><?php echo htmlspecialchars($m['state']); ?></span></td>
                                        <td><span class="dc-badge <?php echo $pb; ?>"><?php echo htmlspecialchars($m['pay']); ?></span></td>
                                        <td><a class="dc-link" href="<?php echo htmlspecialchars($base_url . '/odoo/action-account.move_action/' . $m['id']); ?>" target="_blank">Mở ↗</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>
