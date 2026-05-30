<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$role = $_SESSION['role'];

$u_id = (int) $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? '';
if (empty($user_email)) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $user_email = $row['email'];
        $_SESSION['email'] = $user_email;
    }
}

// ── Year selection ──
$current_year = (int) date('Y');
$current_month = (int) date('n');
$current_quarter = (int) ceil($current_month / 3);
$selected_year = isset($_GET['year']) ? (int) $_GET['year'] : $current_year;
$available_years = [$current_year, $current_year - 1, $current_year - 2];

$year_from = $selected_year . '-01-01';
$year_to = $selected_year . '-12-31';

// ── Fetch invoices from Odoo cache ──
$all_invoices = [];
$error = null;
try {
    $odoo = new OdooAPI();
    $filters = ['owner_email' => $user_email];
    $all_result = $odoo->getInvoices(10000, 0, $filters);
    $all_invoices = $all_result['invoices'] ?? [];
} catch (Exception $e) {
    $error = $e->getMessage();
}

$excluded_types = ['Internal', 'Commission', 'License'];

// ── PAKD data for EBT ──
$pakd_list = [];
$pakd_stmt = $conn->prepare("SELECT id, name, company_name, revenue, gross_profit, currency, status, contract_no, sales_order_no FROM pakd WHERE am_user_id = ? OR am_email = ? ORDER BY name");
if ($pakd_stmt) {
    $pakd_stmt->bind_param("is", $u_id, $user_email);
    $pakd_stmt->execute();
    $pakd_res = $pakd_stmt->get_result();
    while ($r = $pakd_res->fetch_assoc()) $pakd_list[] = $r;
    $pakd_stmt->close();
}
$pakd_map = [];
foreach ($pakd_list as $p) $pakd_map[$p['id']] = $p;

// Invoice → PAKD mappings (for EBT)
$inv_pakd_map = [];
$map_stmt = $conn->prepare("SELECT invoice_id, pakd_id, manual_ebt FROM invoice_pakd_map WHERE user_id = ?");
if ($map_stmt) {
    $map_stmt->bind_param("i", $u_id);
    $map_stmt->execute();
    $map_res = $map_stmt->get_result();
    while ($mr = $map_res->fetch_assoc()) $inv_pakd_map[(int)$mr['invoice_id']] = $mr;
    $map_stmt->close();
}

// ── Yearly Bonus rate ──
// Rate is chosen ONCE per year from the year's AVERAGE EBT ratio (%A_EBT):
//   %A_EBT ≥ 20%   → 4%
//   %A_EBT ≥ 12.5% → 2%
//   else           → 0%
// Yearly Bonus = rate × S_EBT  (S_EBT = total EBT/profit for the year, in VND).
function yb_rate_from_avg($avg_ebt_pct) {
    if ($avg_ebt_pct === null) return 0;
    if ($avg_ebt_pct >= 20)   return 0.04;
    if ($avg_ebt_pct >= 12.5) return 0.02;
    return 0;
}

// Resolve the payment date of an invoice (latest reconciled payment, else write_date).
function yb_payment_date($inv) {
    $pay_date = null;
    if (!empty($inv['invoice_payments_widget'])) {
        $widget = $inv['invoice_payments_widget'];
        if (is_string($widget)) $widget = json_decode($widget, true);
        if (!empty($widget['content'])) {
            $dates = array_column($widget['content'], 'date');
            if ($dates) $pay_date = max($dates);
        }
    }
    if (!$pay_date && ($inv['payment_state'] ?? '') === 'paid') $pay_date = $inv['write_date'] ?? null;
    return $pay_date ? substr($pay_date, 0, 10) : '';
}

// ── Aggregate EBT from invoices PAID during the selected year ──
// For each paid invoice we know its revenue (VND) and EBT ratio (from PAKD/manual).
//   EBT amount (profit) = revenue × EBT% / 100
//   S_EBT  = Σ EBT amount   (total profit for the year)
//   S_Rev  = Σ revenue       (only invoices that HAVE an EBT figure)
//   %A_EBT = S_EBT / S_Rev × 100   (year's average EBT ratio)
// The rate is then chosen ONCE from %A_EBT and applied to the whole S_EBT.
$quarter_ebt = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];   // S_EBT per quarter
$quarter_revenue = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
$quarter_count = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$total_ebt = 0.0;          // S_EBT (total profit, VND)
$total_revenue_ebt = 0.0;  // revenue of invoices with a known EBT (denominator for %A_EBT)
$total_revenue_paid = 0.0; // all paid revenue (info only)
$no_ebt_count = 0;
$inv_rows = [];

foreach ($all_invoices as $inv) {
    $inv_type = $inv['x_studio_invoice_type_1'] ?? '';
    if (in_array($inv_type, $excluded_types)) continue;
    if (($inv['state'] ?? '') === 'cancel') continue;
    $ps = $inv['payment_state'] ?? '';
    if ($ps !== 'paid' && $ps !== 'in_payment') continue;

    $pay_date = yb_payment_date($inv);
    if (!$pay_date || $pay_date < $year_from || $pay_date > $year_to) continue;

    $pq = (int) ceil(((int) substr($pay_date, 5, 2)) / 3);
    if ($pq < 1 || $pq > 4) continue;

    $inv_id = (int) $inv['id'];
    $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));
    if ($amount_vnd == 0) $amount_vnd = (float) ($inv['amount_total'] ?? 0);

    // EBT ratio from manual entry or linked PAKD
    $m = $inv_pakd_map[$inv_id] ?? null;
    $ebt = null;
    if ($m && $m['manual_ebt'] !== null && $m['manual_ebt'] !== '') {
        $ebt = (float) $m['manual_ebt'];
    } elseif ($m && (int)$m['pakd_id'] && isset($pakd_map[(int)$m['pakd_id']])) {
        $lp = $pakd_map[(int)$m['pakd_id']];
        $ebt = $lp['revenue'] > 0 ? ($lp['gross_profit'] / $lp['revenue'] * 100) : 0;
    }

    $total_revenue_paid += $amount_vnd;
    $quarter_revenue[$pq] += $amount_vnd;
    $quarter_count[$pq] += 1;

    if ($ebt === null) { $no_ebt_count++; continue; }   // no EBT data → can't contribute profit

    $ebt_amount = $amount_vnd * $ebt / 100;   // profit (VND) for this invoice
    $total_ebt += $ebt_amount;
    $total_revenue_ebt += $amount_vnd;
    $quarter_ebt[$pq] += $ebt_amount;

    $cust = is_array($inv['partner_id'] ?? null) ? ($inv['partner_id'][1] ?? '') : '';
    $inv_rows[] = [
        'name'        => $inv['name'] ?? ('#' . $inv_id),
        'customer'    => $cust,
        'pay_date'    => $pay_date,
        'quarter'     => $pq,
        'amount_vnd'  => $amount_vnd,
        'ebt'         => $ebt,
        'ebt_amount'  => $ebt_amount,
    ];
}

// ── Year-level rate from average EBT ratio, then Yearly Bonus = rate × S_EBT ──
$avg_ebt_pct = $total_revenue_ebt > 0 ? ($total_ebt / $total_revenue_ebt * 100) : null;
$yearly_rate = yb_rate_from_avg($avg_ebt_pct);
$total_yb = $total_ebt * $yearly_rate;
$quarter_yb = [];
foreach ([1, 2, 3, 4] as $q) $quarter_yb[$q] = $quarter_ebt[$q] * $yearly_rate;

// Sort invoices by payment date (newest first)
usort($inv_rows, fn($a, $b) => strcmp($b['pay_date'], $a['pay_date']));
$ebt_inv_count = count($inv_rows);

// ── Helpers ──
function mc_fmt($n) { return number_format($n, 0, '.', ','); }
function mc_fmt_short($n) {
    if (abs($n) >= 1e9) return number_format($n / 1e9, 2) . ' tỷ';
    if (abs($n) >= 1e6) return number_format($n / 1e6, 1) . ' tr';
    return number_format($n, 0, '.', ',');
}
function mc_pct($n) { return $n === null ? '–' : number_format($n, 1) . '%'; }

$quarter_label = [1 => 'Jan – Mar', 2 => 'Apr – Jun', 3 => 'Jul – Sep', 4 => 'Oct – Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yearly Bonus - <?= $selected_year ?></title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .mycom { padding: 1rem 1.25rem; max-width: 100%; font-family: 'Inter', sans-serif; }

        /* Quarter nav */
        .q-nav { display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; }
        .year-sel { padding:0.35rem 0.6rem; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:600; color:#374151; background:#fff; cursor:pointer; outline:none; }
        .q-tabs { display:flex; gap:0.25rem; }
        .qt { padding:0.35rem 1rem; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; font-weight:500; color:#64748b; background:#fff; cursor:pointer; text-decoration:none; transition:all .15s; }
        .qt:hover { border-color:#93c5fd; color:#1d4ed8; background:#eff6ff; }
        .qt.active { background:#2563eb; color:#fff; border-color:#2563eb; font-weight:600; }
        .qt-yb { border-color:#fcd34d; color:#b45309; background:#fffbeb; font-weight:600; }
        .qt-yb:hover { border-color:#f59e0b; color:#92400e; background:#fef3c7; }
        .qt-yb.active { background:#d97706; color:#fff; border-color:#d97706; }
        .q-label { font-size:12px; color:#94a3b8; margin-left:0.5rem; }

        /* Hero */
        .yb-hero { background:linear-gradient(135deg,#92400e 0%,#d97706 60%,#f59e0b 100%); border-radius:14px; padding:1.5rem 1.75rem; margin-bottom:1.25rem; color:#fff; box-shadow:0 6px 20px rgba(217,119,6,.25); }
        .yb-hero .yh-label { font-size:13px; font-weight:600; color:#fde68a; text-transform:uppercase; letter-spacing:.06em; }
        .yb-hero .yh-value { font-size:42px; font-weight:800; margin:0.25rem 0; line-height:1.1; }
        .yb-hero .yh-value small { font-size:18px; font-weight:600; opacity:.85; }
        .yb-hero .yh-sub { font-size:13px; color:#fef3c7; }
        .yb-hero .yh-meta { display:flex; gap:1.5rem; margin-top:0.9rem; flex-wrap:wrap; }
        .yb-hero .yh-meta div { font-size:12px; color:#fde68a; }
        .yb-hero .yh-meta strong { display:block; font-size:16px; font-weight:700; color:#fff; }

        /* Quarter cards */
        .yb-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; margin-bottom:1.25rem; }
        .yb-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.1rem; position:relative; overflow:hidden; }
        .yb-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:#f59e0b; }
        .yb-card.cur::before { height:4px; background:linear-gradient(90deg,#d97706,#fbbf24); }
        .yb-card .qc-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem; }
        .yb-card .qc-q { font-size:13px; font-weight:700; color:#92400e; }
        .yb-card .qc-mon { font-size:11px; color:#94a3b8; }
        .yb-card .qc-value { font-size:22px; font-weight:700; color:#b45309; }
        .yb-card .qc-sub { font-size:11px; color:#64748b; margin-top:3px; }
        .yb-card .qc-tag { font-size:10px; padding:2px 6px; border-radius:4px; display:inline-block; margin-top:6px; background:#fef3c7; color:#92400e; }
        .yb-card .qc-tag.cur-tag { background:#d97706; color:#fff; }

        /* Rules box */
        .yb-rules { background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; padding:0.85rem 1.1rem; margin-bottom:1.25rem; font-size:12px; color:#92400e; }
        .yb-rules strong { color:#78350f; }
        .yb-rules .chip { display:inline-block; background:#fde68a; color:#78350f; border-radius:6px; padding:2px 8px; margin:0 4px; font-weight:600; }

        /* Table */
        .t-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; }
        .t-head h3 { font-size:14px; font-weight:700; color:#0f172a; margin:0; }
        .t-head .t-note { font-size:12px; color:#94a3b8; }
        .t-card { background:#fff; border:1px solid #c0c0c0; overflow:auto; max-height:calc(100vh - 480px); }
        table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:13px; color:#000; }
        th { background:#f8f9fa; color:#5f6368; font-weight:bold; text-align:left; padding:4px 8px; border:1px solid #e0e0e0; white-space:nowrap; height:30px; position:sticky; top:0; z-index:1; }
        td { padding:4px 8px; border:1px solid #e0e0e0; color:#202124; white-space:nowrap; height:25px; vertical-align:middle; }
        tr:hover td { background-color:#f1f3f4; }
        .amt { font-family:'Inconsolata',monospace; text-align:right; }
        .b-q { padding:2px 7px; border-radius:10px; font-size:11px; font-weight:600; background:#fef3c7; color:#92400e; }
        .b-rate { padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600; }
        .b-rate.r4 { background:#dcfce7; color:#15803d; }
        .b-rate.r2 { background:#fef9c3; color:#854d0e; }
        .yb-amt { color:#b45309; font-weight:700; }
        .empty { padding:2rem; text-align:center; color:#94a3b8; font-size:13px; }

        @media (max-width:768px) {
            .yb-grid { grid-template-columns:1fr 1fr; }
            .yb-hero .yh-value { font-size:32px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Yearly Bonus'; include __DIR__ . '/../includes/topbar.php'; ?>

        <div class="mycom">
            <?php if ($error): ?>
                <div style="background:#fce8e6;color:#c5221f;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:13px;">
                    Error: <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- ─── Navigation ─── -->
            <div class="q-nav">
                <select class="year-sel" onchange="location.href='/my-com/yearly-bonus?year='+this.value">
                    <?php foreach ($available_years as $yr): ?>
                        <option value="<?= $yr ?>" <?= $yr === $selected_year ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="q-tabs">
                    <?php for ($q = 1; $q <= 4; $q++):
                        $cls = 'qt';
                        if ($q === $current_quarter && $selected_year === $current_year) $cls .= ' cur';
                    ?>
                        <a href="/my-com?year=<?= $selected_year ?>&quarter=<?= $q ?>" class="<?= $cls ?>">Q<?= $q ?></a>
                    <?php endfor; ?>
                    <a href="/my-com/yearly-bonus?year=<?= $selected_year ?>" class="qt qt-yb active">★ Yearly Bonus</a>
                </div>
                <span class="q-label">Cả năm <?= $selected_year ?> · theo ngày thanh toán</span>
            </div>

            <!-- ─── Hero: total yearly bonus ─── -->
            <div class="yb-hero">
                <div class="yh-label">Yearly Bonus <?= $selected_year ?> (ước tính)</div>
                <div class="yh-value"><?= mc_fmt($total_yb) ?> <small>VND</small></div>
                <div class="yh-sub">Yearly Bonus = tỉ lệ × S_EBT &nbsp;=&nbsp; <strong><?= $yearly_rate > 0 ? number_format($yearly_rate * 100, 0) . '%' : '0%' ?></strong> × <?= mc_fmt_short($total_ebt) ?> &nbsp;(tỉ lệ chọn theo %A_EBT cả năm)</div>
                <div class="yh-meta">
                    <div>S_EBT — Tổng EBT năm<strong><?= mc_fmt_short($total_ebt) ?> VND</strong></div>
                    <div>%A_EBT — EBT TB năm<strong><?= $avg_ebt_pct === null ? '–' : number_format($avg_ebt_pct, 1) . '%' ?></strong></div>
                    <div>Tỉ lệ Yearly Bonus<strong><?= $yearly_rate > 0 ? number_format($yearly_rate * 100, 0) . '%' : '0% (chưa đạt 12.5%)' ?></strong></div>
                    <div>Doanh thu đã thu (năm)<strong><?= mc_fmt_short($total_revenue_paid) ?> VND</strong></div>
                </div>
            </div>

            <!-- ─── Per-quarter breakdown ─── -->
            <div class="yb-grid">
                <?php for ($q = 1; $q <= 4; $q++):
                    $is_cur = ($q === $current_quarter && $selected_year === $current_year);
                ?>
                <div class="yb-card<?= $is_cur ? ' cur' : '' ?>">
                    <div class="qc-head">
                        <span class="qc-q">Q<?= $q ?></span>
                        <span class="qc-mon"><?= $quarter_label[$q] ?></span>
                    </div>
                    <div class="qc-value"><?= mc_fmt_short($quarter_yb[$q]) ?></div>
                    <div class="qc-sub">EBT quý: <strong><?= mc_fmt_short($quarter_ebt[$q]) ?></strong></div>
                    <div class="qc-sub">DT thu: <strong><?= mc_fmt_short($quarter_revenue[$q]) ?></strong> · <?= $quarter_count[$q] ?> HĐ</div>
                    <span class="qc-tag<?= $is_cur ? ' cur-tag' : '' ?>"><?= $is_cur ? 'Quý hiện tại' : 'Đã thanh toán' ?></span>
                </div>
                <?php endfor; ?>
            </div>

            <!-- ─── Rules ─── -->
            <div class="yb-rules">
                <strong>Cách tính Yearly Bonus</strong> (theo EBT - lợi nhuận trước thuế, không phân biệt khách New/Old):
                <div style="margin-top:6px;">
                    <strong>S_EBT</strong> = tổng EBT năm = Σ (Doanh thu × %EBT) của các HĐ đã thu &nbsp;·&nbsp;
                    <strong>%A_EBT</strong> = tỷ lệ EBT trung bình năm = S_EBT / Tổng doanh thu.
                </div>
                <div style="margin-top:6px;">
                    Chọn <strong>một</strong> tỉ lệ theo %A_EBT của cả năm, rồi nhân vào S_EBT:
                    <span class="chip">%A_EBT ≥ 12.5% → 2% × S_EBT</span>
                    <span class="chip">%A_EBT ≥ 20% → 4% × S_EBT</span>
                    <span class="chip">&lt; 12.5% → 0</span>
                </div>
                <div style="margin-top:6px;">
                    HĐ xếp vào quý theo <strong>ngày thanh toán</strong>. EBT lấy từ PAKD liên kết hoặc nhập tay (chỉnh tại các tab quý).
                    <em>Đây là ước tính gross, chưa nhân hệ số KPI.</em>
                </div>
            </div>

            <!-- ─── Contributing invoices ─── -->
            <div class="t-head">
                <h3>Hoá đơn đóng góp EBT năm (<?= $selected_year ?>)</h3>
                <span class="t-note"><?= $ebt_inv_count ?> HĐ có EBT<?= $no_ebt_count > 0 ? ' · ' . $no_ebt_count . ' HĐ chưa gán EBT (không tính)' : '' ?></span>
            </div>
            <div class="t-card">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Ngày thanh toán</th>
                            <th style="text-align:center;">Quý</th>
                            <th style="text-align:right;">Doanh thu (VND)</th>
                            <th style="text-align:center;">%EBT</th>
                            <th style="text-align:right;">EBT (VND)</th>
                            <th style="text-align:right;">YB (× <?= $yearly_rate > 0 ? number_format($yearly_rate * 100, 0) : 0 ?>%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inv_rows)): ?>
                            <tr><td colspan="9" class="empty">Chưa có hoá đơn nào có dữ liệu EBT trong năm <?= $selected_year ?> (cần gán PAKD hoặc nhập EBT tại các tab quý, và đã thanh toán).</td></tr>
                        <?php else: $i = 1; foreach ($inv_rows as $r): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['customer']) ?></td>
                                <td><?= htmlspecialchars($r['pay_date']) ?></td>
                                <td style="text-align:center;"><span class="b-q">Q<?= $r['quarter'] ?></span></td>
                                <td class="amt"><?= mc_fmt($r['amount_vnd']) ?></td>
                                <td style="text-align:center;"><?= number_format($r['ebt'], 1) ?>%</td>
                                <td class="amt"><?= mc_fmt($r['ebt_amount']) ?></td>
                                <td class="amt yb-amt"><?= mc_fmt($r['ebt_amount'] * $yearly_rate) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($inv_rows)): ?>
                    <tfoot>
                        <tr style="font-weight:bold;background:#fffbeb;">
                            <td colspan="5" style="text-align:right;">Tổng cộng (S_EBT)</td>
                            <td class="amt"><?= mc_fmt($total_revenue_ebt) ?></td>
                            <td style="text-align:center;"><?= $avg_ebt_pct === null ? '–' : number_format($avg_ebt_pct, 1) . '%' ?></td>
                            <td class="amt"><?= mc_fmt($total_ebt) ?></td>
                            <td class="amt yb-amt"><?= mc_fmt($total_yb) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
