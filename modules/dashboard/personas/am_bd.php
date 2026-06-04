<?php
/**
 * AM/BD dashboard persona.
 *
 * Self-contained page rendered for is_am_bd users from
 * modules/dashboard/dashboard.php. Mirrors the data model of the My Com /
 * Sale Reports modules so the numbers cross-check:
 *   - VND rates : getCurrencies() (VND-base context) — NEVER getRate(), which
 *                 is not company-scoped (see memory: odoo-getrate-company-contamination).
 *   - revenue   : posted, non-internal, non-excluded Odoo invoices filtered by
 *                 the user's email (read from the local invoices cache).
 *   - SO KPI    : signed Sale Order revenue (state in sent/sale/done) ÷
 *                 sale_levels.so_kpi_quarter_usd — the AM/BD "Lương KPI quý".
 *   - target    : sale_levels resolved through user_sale_level_history.
 *   - status    : latest sale_report_confirmations event for the quarter.
 *
 * Expects from the including scope: $conn, $user_id, $full_name, $avatar.
 */
require_once __DIR__ . '/../../../libs/OdooAPI.php';

$odoo = new OdooAPI();

// ── VND rate map via getCurrencies() (VND-base) — the correct, company-safe way
function ambd_build_vnd_rates($odoo): array
{
    $rates = ['VND' => 1.0];
    try {
        if ($odoo) {
            $curs = $odoo->getCurrencies();
            $r_vnd = (is_array($curs) && isset($curs['VND']['rate'])) ? (float) $curs['VND']['rate'] : 0.0;
            if ($r_vnd > 0) {
                foreach ($curs as $name => $info) {
                    $r = isset($info['rate']) ? (float) $info['rate'] : 0.0;
                    if ($r > 0) $rates[$name] = $r_vnd / $r;
                }
            }
        }
    } catch (Throwable $e) { /* VND-only fallback keeps the page working */ }
    return $rates;
}

// Convert an Odoo invoice / SO amount_total (in its own currency) to VND
// using the live getCurrencies() rate map. Used for Sale Orders and debts.
function ambd_to_vnd($amount_total, $currency_id, array $rates): float
{
    $cur = is_array($currency_id) ? ($currency_id[1] ?? 'VND') : ($currency_id ?: 'VND');
    return (float) $amount_total * ($rates[$cur] ?? 1.0);
}

// Sum signed Sale Order revenue (VND) for a quarter — basis of the salary KPI.
function ambd_so_revenue_quarter($odoo, $odoo_user_id, int $year, int $q, array $rates): ?float
{
    if (!$odoo || !$odoo_user_id) return null;
    $qm = [1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12]];
    if (!isset($qm[$q])) return null;
    $from = sprintf('%04d-%02d-01', $year, $qm[$q][0]);
    $to   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $qm[$q][1])));
    try {
        $rows = $odoo->searchRead('sale.order', [
            ['user_id', '=', $odoo_user_id],
            ['state', 'in', ['sent', 'sale', 'done']],
            ['date_order', '>=', $from],
            ['date_order', '<=', $to],
        ], ['amount_total', 'currency_id'], 0, 0);
    } catch (Throwable $e) {
        return null;
    }
    $sum = 0.0;
    foreach ((array) $rows as $so) {
        $sum += ambd_to_vnd($so['amount_total'] ?? 0, $so['currency_id'] ?? null, $rates);
    }
    return $sum;
}

$vnd_rates = ambd_build_vnd_rates($odoo);
$usd_rate  = (float) ($vnd_rates['USD'] ?? 0);   // VND per 1 USD

// ── Period (selectable via ?quarter=Q3_2026, defaults to current) ─────────────
$cur_year    = (int) date('Y');
$cur_month   = (int) date('n');
$today_quarter = (int) ceil($cur_month / 3);

$sel_q = $today_quarter;
$sel_y = $cur_year;
if (!empty($_GET['quarter']) && preg_match('/^Q([1-4])_(\d{4})$/', $_GET['quarter'], $mm)) {
    $sel_q = (int) $mm[1];
    $sel_y = (int) $mm[2];
}
$quarter_tab = "Q{$sel_q}_{$sel_y}";

$range = function (int $y, int $q): array {
    $sm = ($q - 1) * 3 + 1;
    $em = $q * 3;
    return [
        sprintf('%d-%02d-01', $y, $sm),
        date('Y-m-t', strtotime(sprintf('%d-%02d-01', $y, $em))),
        $sm,
    ];
};
[$q_start_date, $q_end_date, $q_start_month] = $range($sel_y, $sel_q);

// Previous quarter (for growth comparison)
$prev_q = $sel_q - 1; $prev_y = $sel_y;
if ($prev_q < 1) { $prev_q = 4; $prev_y--; }
[$pq_start, $pq_end] = $range($prev_y, $prev_q);
$ytd_start = "$sel_y-01-01";

// Dropdown: last 8 quarters (descending)
$quarter_options = [];
for ($i = 0; $i < 8; $i++) {
    $qq = $today_quarter - $i; $yy = $cur_year;
    while ($qq < 1) { $qq += 4; $yy--; }
    $quarter_options[] = "Q{$qq}_{$yy}";
}

// ── User email + Odoo user id ─────────────────────────────────────────────────
$user_email = '';
$stmt_e = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt_e->bind_param("i", $user_id);
$stmt_e->execute();
if ($row = $stmt_e->get_result()->fetch_assoc()) $user_email = $row['email'] ?? '';

$odoo_user_id = null;
if ($user_email) {
    try {
        $ou = $odoo->searchRead('res.users', [['login', '=', $user_email]], ['id'], 1);
        $odoo_user_id = !empty($ou[0]['id']) ? (int) $ou[0]['id'] : null;
    } catch (Throwable $e) { $odoo_user_id = null; }
}

// ── Revenue from the user's deals in the `debts` table ────────────────────────
// Source = same identity rule as the My Debt page (debts.am_email = my email).
// "Doanh thu Quý" = sum of every deal (amount → VND) whose invoice_date falls in
// the quarter, regardless of payment status. Also compute previous quarter
// (for growth) and YTD using the same basis.
$rev_q = 0.0; $rev_prev = 0.0; $rev_ytd = 0.0; $invoice_count_q = 0;
$monthly_rev = [$q_start_month => 0.0, $q_start_month + 1 => 0.0, $q_start_month + 2 => 0.0];

// Lower bound covers both the previous quarter and the YTD start.
$scan_from = min($pq_start, $ytd_start);

if ($user_email) {
    $email_esc = $conn->real_escape_string($user_email);
    $from_esc  = $conn->real_escape_string($scan_from);
    $to_esc    = $conn->real_escape_string($q_end_date);
    $res_rev = $conn->query("SELECT amount, currency, invoice_date
                             FROM debts
                             WHERE am_email = '$email_esc'
                               AND invoice_date IS NOT NULL
                               AND invoice_date >= '$from_esc'
                               AND invoice_date <= '$to_esc'");
    if ($res_rev) {
        while ($d = $res_rev->fetch_assoc()) {
            $dt  = $d['invoice_date'];
            $vnd = ambd_to_vnd((float) $d['amount'], $d['currency'] ?: 'USD', $vnd_rates);

            if ($dt >= $q_start_date && $dt <= $q_end_date) {
                $rev_q += $vnd;
                $invoice_count_q++;
                $m = (int) date('n', strtotime($dt));
                if (isset($monthly_rev[$m])) $monthly_rev[$m] += $vnd;
            }
            if ($dt >= $pq_start && $dt <= $pq_end) $rev_prev += $vnd;
            if ($dt >= $ytd_start && $dt <= $q_end_date) $rev_ytd += $vnd;
        }
    }
}

$growth_pct = $rev_prev > 0 ? round(($rev_q - $rev_prev) / $rev_prev * 100) : null;

// ── KPI target from sale level (effective for the selected quarter) ───────────
$eff_level_id = null;
$stmt_hist = $conn->prepare("
    SELECT sale_level_id FROM user_sale_level_history
    WHERE user_id = ? AND (apply_year < ? OR (apply_year = ? AND apply_quarter <= ?))
    ORDER BY apply_year DESC, apply_quarter DESC LIMIT 1
");
if ($stmt_hist) {
    $stmt_hist->bind_param("iiii", $user_id, $sel_y, $sel_y, $sel_q);
    $stmt_hist->execute();
    if ($r = $stmt_hist->get_result()->fetch_assoc()) $eff_level_id = $r['sale_level_id'];
}

$cols = "level_name, position_type, color_badge, kpi_quarter_vnd, kpi_yearly_vnd, kpi_quarter_usd, kpi_yearly_usd, so_kpi_quarter_usd";
$kpi = null;
if ($eff_level_id) {
    $stmt_k = $conn->prepare("SELECT $cols FROM sale_levels WHERE id = ?");
    if ($stmt_k) $stmt_k->bind_param("i", $eff_level_id);
} else {
    $stmt_k = $conn->prepare("SELECT " . str_replace(', ', ', sl.', "sl.$cols") . " FROM users u LEFT JOIN sale_levels sl ON u.sale_level_id = sl.id WHERE u.id = ?");
    if ($stmt_k) $stmt_k->bind_param("i", $user_id);
}
if ($stmt_k) {
    $stmt_k->execute();
    $krow = $stmt_k->get_result()->fetch_assoc();
    if ($krow && !empty($krow['level_name'])) $kpi = $krow;
}

// Quarter target in VND — fall back to USD target × rate if no VND target set.
$target_q_vnd = (float) ($kpi['kpi_quarter_vnd'] ?? 0);
$target_q_src = 'vnd';
if ($target_q_vnd <= 0 && (float) ($kpi['kpi_quarter_usd'] ?? 0) > 0 && $usd_rate > 0) {
    $target_q_vnd = (float) $kpi['kpi_quarter_usd'] * $usd_rate;
    $target_q_src = 'usd';
}
$target_y_vnd = (float) ($kpi['kpi_yearly_vnd'] ?? 0);
if ($target_y_vnd <= 0 && (float) ($kpi['kpi_yearly_usd'] ?? 0) > 0 && $usd_rate > 0) {
    $target_y_vnd = (float) $kpi['kpi_yearly_usd'] * $usd_rate;
}

// Real percentages (NOT capped — show over-achievement); bar width is capped at 100.
$pct_q = $target_q_vnd > 0 ? round($rev_q / $target_q_vnd * 100) : 0;
$pct_y = $target_y_vnd > 0 ? round($rev_ytd / $target_y_vnd * 100) : 0;

// Expected linear progress through the quarter (by elapsed days, current quarter only)
$is_current_quarter = ($sel_q === $today_quarter && $sel_y === $cur_year);
$days_total   = (strtotime($q_end_date) - strtotime($q_start_date)) / 86400 + 1;
$days_elapsed = $is_current_quarter ? min($days_total, max(0, (time() - strtotime($q_start_date)) / 86400)) : $days_total;
$quarter_progress = (int) round($days_elapsed / $days_total * 100);

function ambd_status(float $pct, int $expected): array
{
    if ($pct >= 100) return ['Đạt', '#d1fae5', '#065f46'];
    if ($pct >= $expected) return ['Đúng tiến độ', '#dbeafe', '#1d4ed8'];
    if ($pct >= $expected - 20) return ['Cần đẩy', '#fef3c7', '#92400e'];
    return ['Chậm', '#fee2e2', '#991b1b'];
}
[$status_label, $status_bg, $status_fg] = ambd_status((float) $pct_q, $quarter_progress);

// ── Salary KPI (Sale Order revenue ÷ so_kpi_quarter_usd) ──────────────────────
$so_target_usd = (float) ($kpi['so_kpi_quarter_usd'] ?? 0);
$so_rev_vnd = ambd_so_revenue_quarter($odoo, $odoo_user_id, $sel_y, $sel_q, $vnd_rates);
$has_so = ($so_rev_vnd !== null);
$so_rev_vnd = (float) ($so_rev_vnd ?? 0);
$so_rev_usd = $usd_rate > 0 ? ($so_rev_vnd / $usd_rate) : 0;
$salary_kpi_pct = $so_target_usd > 0 ? ($so_rev_usd / $so_target_usd * 100) : 0;
if ($salary_kpi_pct < 60)        $salary_label = '0%';
elseif ($salary_kpi_pct < 80)    $salary_label = '50%';
elseif ($salary_kpi_pct <= 150)  $salary_label = '100%';
else                             $salary_label = '150%';
$salary_band_color = $salary_label === '0%' ? '#dc2626' : ($salary_label === '50%' ? '#f59e0b' : '#059669');

// ── Commission / KPI confirmation status for the selected quarter ─────────────
$conf_label = 'Chưa xác nhận'; $conf_bg = '#f1f5f9'; $conf_fg = '#64748b'; $conf_detail = '';
$res_c = $conn->query("SELECT type, confirmed_at FROM sale_report_confirmations WHERE user_id = $user_id AND quarter = '$quarter_tab' ORDER BY confirmed_at DESC LIMIT 1");
if ($res_c && ($c = $res_c->fetch_assoc())) {
    $when = date('H:i d/m/Y', strtotime($c['confirmed_at']));
    if ($c['type'] === 'commission_confirmed')      { $conf_label = 'Đã chốt hoa hồng'; $conf_bg = '#d1fae5'; $conf_fg = '#065f46'; $conf_detail = $when; }
    elseif ($c['type'] === 'confirmed')             { $conf_label = 'Đã xác nhận KPI';  $conf_bg = '#dbeafe'; $conf_fg = '#1d4ed8'; $conf_detail = $when; }
    elseif ($c['type'] === 'reset')                 { $conf_label = 'Đã mở lại (nháp)'; $conf_bg = '#fef3c7'; $conf_fg = '#92400e'; $conf_detail = $when; }
}

// ── Debt to collect (my deals, unpaid) — same am_email identity as My Debt ────
$my_pending_vnd = 0.0; $my_pending_cnt = 0;
if ($user_email) {
    $email_esc = $conn->real_escape_string($user_email);
    $res_d = $conn->query("SELECT amount, currency, payment_status FROM debts WHERE am_email = '$email_esc'");
    if ($res_d) {
        while ($d = $res_d->fetch_assoc()) {
            if (strcasecmp(trim($d['payment_status'] ?? ''), 'Paid') === 0) continue;
            $my_pending_vnd += ambd_to_vnd((float) $d['amount'], $d['currency'] ?: 'USD', $vnd_rates);
            $my_pending_cnt++;
        }
    }
}

// ── Action items: deals to collect (overdue / due soon) + top deals ──────────
$today = date('Y-m-d');
$due_soon_cutoff = date('Y-m-d', strtotime('+30 days'));
$collect_list = []; // unpaid deals with an expected payment date, soonest first
$top_deals = [];    // biggest deals invoiced this quarter
if ($user_email) {
    $email_esc = $conn->real_escape_string($user_email);

    $res_col = $conn->query("SELECT client_name, project_name, amount, currency, expected_payment_date, payment_status
                             FROM debts
                             WHERE am_email = '$email_esc'
                               AND expected_payment_date IS NOT NULL
                               AND expected_payment_date >= '1000-01-01'
                               AND expected_payment_date <= '$due_soon_cutoff'
                             ORDER BY expected_payment_date ASC");
    if ($res_col) {
        while ($d = $res_col->fetch_assoc()) {
            if (strcasecmp(trim($d['payment_status'] ?? ''), 'Paid') === 0) continue;
            $d['_vnd'] = ambd_to_vnd((float) $d['amount'], $d['currency'] ?: 'USD', $vnd_rates);
            $d['_overdue'] = ($d['expected_payment_date'] < $today);
            $collect_list[] = $d;
            if (count($collect_list) >= 8) break;
        }
    }

    $qs = $conn->real_escape_string($q_start_date);
    $qe = $conn->real_escape_string($q_end_date);
    $res_top = $conn->query("SELECT client_name, project_name, amount, currency, payment_status
                             FROM debts
                             WHERE am_email = '$email_esc'
                               AND invoice_date >= '$qs' AND invoice_date <= '$qe'
                             ORDER BY amount DESC LIMIT 5");
    if ($res_top) {
        while ($d = $res_top->fetch_assoc()) {
            $d['_vnd'] = ambd_to_vnd((float) $d['amount'], $d['currency'] ?: 'USD', $vnd_rates);
            $top_deals[] = $d;
        }
    }
}

// ── Formatters ────────────────────────────────────────────────────────────────
$fmtVnd = fn($v) => number_format($v, 0, ',', '.') . ' ₫';
$badge_color = $kpi['color_badge'] ?? '#2563eb';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sales</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .ambd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(225px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .ambd-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); position: relative; overflow: hidden; }
        .ambd-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .ambd-card.blue::before { background: #3b82f6; } .ambd-card.green::before { background: #10b981; }
        .ambd-card.amber::before { background: #f59e0b; } .ambd-card.rose::before { background: #f43f5e; }
        .ambd-card.violet::before { background: #7c3aed; }
        .ambd-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .ambd-value { font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.2; word-break: break-word; }
        .ambd-sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .ambd-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 99px; font-size: 13px; font-weight: 700; }
        .ambd-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
        .pbar { height: 9px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin-top: 10px; }
        .pfill { height: 100%; border-radius: 99px; transition: width .6s ease; }
        .ambd-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); margin-bottom: 24px; }
        .ambd-panel h3 { margin: 0 0 4px; font-size: 1.05rem; color: #0f172a; }
        .ambd-row { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
        @media (max-width: 880px) { .ambd-row { grid-template-columns: 1fr; } }
        .ambd-links { display: flex; gap: 12px; flex-wrap: wrap; }
        .ambd-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; transition: .2s; }
        .ambd-link.alt { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
        .ambd-link:hover { transform: translateY(-1px); }
        .ambd-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .ambd-select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; font-weight: 600; color: #1e293b; cursor: pointer; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Dashboard';
            $page_subtitle = 'Xin chào, <strong>' . htmlspecialchars($full_name) . '</strong>';
            include __DIR__ . '/../../includes/topbar.php';
            ?>
            <div class="content-wrapper">

                <?php $dash_view = 'am_bd'; include __DIR__ . '/_view_switch.php'; ?>

                <!-- Header + quarter selector -->
                <div class="ambd-panel ambd-toolbar">
                    <div>
                        <h3 style="margin:0;">Hiệu suất kinh doanh của bạn</h3>
                        <p style="margin:4px 0 0; font-size:13px; color:#64748b;">Doanh thu các deal bạn phụ trách (My Debt) so với mục tiêu cấp bậc · Quý <?php echo $sel_q . '/' . $sel_y; ?>.</p>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <?php if ($kpi): ?>
                        <span class="ambd-badge" style="background:<?php echo htmlspecialchars($badge_color); ?>; color:#fff;">
                            <?php echo htmlspecialchars($kpi['level_name']); ?><?php if (!empty($kpi['position_type'])): ?> · <?php echo htmlspecialchars($kpi['position_type']); ?><?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span class="ambd-badge" style="background:#f1f5f9; color:#64748b;">Chưa gán cấp bậc Sale</span>
                        <?php endif; ?>
                        <form method="GET" action="/dashboard" style="margin:0;">
                            <select name="quarter" class="ambd-select" onchange="this.form.submit()">
                                <?php foreach ($quarter_options as $opt):
                                    [$oq, $oy] = sscanf($opt, "Q%d_%d"); ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $opt === $quarter_tab ? 'selected' : ''; ?>>Quý <?php echo "$oq / $oy"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="ambd-grid">
                    <div class="ambd-card green">
                        <div class="ambd-label">Doanh thu Quý <?php echo $sel_q; ?></div>
                        <div class="ambd-value" style="color:#059669;"><?php echo $fmtVnd($rev_q); ?></div>
                        <div class="ambd-sub">
                            <?php echo $invoice_count_q; ?> deal
                            <?php if ($growth_pct !== null): ?>
                                · <span class="ambd-chip" style="background:<?php echo $growth_pct >= 0 ? '#d1fae5' : '#fee2e2'; ?>; color:<?php echo $growth_pct >= 0 ? '#065f46' : '#991b1b'; ?>;"><?php echo $growth_pct >= 0 ? '▲' : '▼'; ?> <?php echo abs($growth_pct); ?>% so Q trước</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ambd-card blue">
                        <div class="ambd-label">Mục tiêu Quý<?php echo $target_q_src === 'usd' ? ' (từ USD)' : ''; ?></div>
                        <div class="ambd-value" style="color:#2563eb;"><?php echo $target_q_vnd > 0 ? $fmtVnd($target_q_vnd) : '—'; ?></div>
                        <?php if ($target_q_vnd > 0): ?>
                        <div class="pbar"><div class="pfill" style="width:<?php echo min(100, max(0, $pct_q)); ?>%; background:<?php echo $status_fg; ?>;"></div></div>
                        <div class="ambd-sub"><strong style="color:<?php echo $status_fg; ?>;"><?php echo $pct_q; ?>%</strong> · kỳ vọng ~<?php echo $quarter_progress; ?>%</div>
                        <?php else: ?>
                        <div class="ambd-sub"><?php echo $kpi ? 'Cấp bậc chưa đặt mục tiêu' : 'Chưa có cấp bậc Sale'; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="ambd-card amber">
                        <div class="ambd-label">Trạng thái tiến độ</div>
                        <div style="margin:4px 0 8px;"><span class="ambd-badge" style="background:<?php echo $status_bg; ?>; color:<?php echo $status_fg; ?>;"><?php echo $status_label; ?></span></div>
                        <div class="ambd-sub">Lũy kế năm: <?php echo $target_y_vnd > 0 ? ('<strong>' . $pct_y . '%</strong> mục tiêu năm') : $fmtVnd($rev_ytd); ?></div>
                    </div>

                    <div class="ambd-card violet">
                        <div class="ambd-label">Lương KPI Quý (Sale Order)</div>
                        <?php if ($so_target_usd > 0): ?>
                        <div class="ambd-value" style="color:<?php echo $salary_band_color; ?>;"><?php echo $salary_label; ?> <span style="font-size:13px; color:#94a3b8; font-weight:600;">(<?php echo round($salary_kpi_pct); ?>%)</span></div>
                        <div class="ambd-sub">SO $<?php echo number_format($so_rev_usd, 0); ?> / $<?php echo number_format($so_target_usd, 0); ?><?php echo !$has_so ? ' · không lấy được SO' : ''; ?></div>
                        <?php else: ?>
                        <div class="ambd-value" style="color:#94a3b8;">—</div>
                        <div class="ambd-sub" style="color:#dc2626;">Cấp bậc chưa set KPI SO</div>
                        <?php endif; ?>
                    </div>

                    <div class="ambd-card rose">
                        <div class="ambd-label">Công nợ cần thu</div>
                        <div class="ambd-value" style="color:#e11d48;"><?php echo $fmtVnd($my_pending_vnd); ?></div>
                        <div class="ambd-sub"><?php echo $my_pending_cnt; ?> deal chưa thu (bạn phụ trách)</div>
                    </div>
                </div>

                <!-- Monthly revenue + confirmation -->
                <div class="ambd-row">
                    <div class="ambd-panel" style="margin-bottom:0;">
                        <h3>Doanh thu theo tháng — Quý <?php echo $sel_q; ?></h3>
                        <div id="ambd-monthly"></div>
                    </div>
                    <div class="ambd-panel" style="margin-bottom:0;">
                        <h3>Xác nhận Quý <?php echo $sel_q; ?></h3>
                        <p style="margin:4px 0 14px; font-size:13px; color:#64748b;">Trạng thái chốt KPI / hoa hồng quý này.</p>
                        <span class="ambd-badge" style="background:<?php echo $conf_bg; ?>; color:<?php echo $conf_fg; ?>;"><?php echo $conf_label; ?></span>
                        <?php if ($conf_detail): ?><div class="ambd-sub" style="margin-top:10px;">Lần cuối: <?php echo $conf_detail; ?></div><?php endif; ?>
                    </div>
                </div>

                <!-- Action items: collect & top deals -->
                <div class="ambd-row">
                    <div class="ambd-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:12px;">⏰ Cần thu (quá hạn &amp; sắp tới 30 ngày)</h3>
                        <?php if (empty($collect_list)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:0;">Không có khoản nào quá hạn hoặc đến hạn trong 30 ngày tới. 🎉</p>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <?php foreach ($collect_list as $d): ?>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 12px; border:1px solid #e2e8f0; border-left:3px solid <?php echo $d['_overdue'] ? '#dc2626' : '#f59e0b'; ?>; border-radius:8px;">
                                        <div style="min-width:0;">
                                            <div style="font-weight:600; color:#0f172a; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($d['client_name'] ?: ($d['project_name'] ?: '—')); ?></div>
                                            <div style="font-size:11px; color:<?php echo $d['_overdue'] ? '#dc2626' : '#64748b'; ?>;">
                                                <?php echo $d['_overdue'] ? '⚠ Quá hạn ' : 'Hạn '; ?><?php echo date('d/m/Y', strtotime($d['expected_payment_date'])); ?>
                                            </div>
                                        </div>
                                        <div style="font-weight:700; color:#e11d48; font-size:13px; white-space:nowrap;"><?php echo $fmtVnd($d['_vnd']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="text-align:right; margin-top:10px;"><a href="/my-debt" style="font-size:12px; color:#3b82f6; text-decoration:none;">→ Xem tất cả công nợ</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="ambd-panel" style="margin-bottom:0;">
                        <h3 style="margin-bottom:12px;">🏆 Top deal Quý <?php echo $sel_q; ?></h3>
                        <?php if (empty($top_deals)): ?>
                            <p style="font-size:13px; color:#94a3b8; margin:0;">Chưa có deal nào trong quý.</p>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <?php foreach ($top_deals as $i => $d): ?>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                                        <div style="min-width:0; display:flex; align-items:center; gap:8px;">
                                            <span style="flex-shrink:0; width:20px; height:20px; border-radius:99px; background:#eef2ff; color:#4338ca; font-size:11px; font-weight:700; display:inline-flex; align-items:center; justify-content:center;"><?php echo $i + 1; ?></span>
                                            <span style="font-size:13px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($d['client_name'] ?: ($d['project_name'] ?: '—')); ?></span>
                                        </div>
                                        <span style="font-weight:700; color:#059669; font-size:13px; white-space:nowrap;"><?php echo $fmtVnd($d['_vnd']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="ambd-panel">
                    <h3 style="margin-bottom:14px;">Đi tới chi tiết</h3>
                    <div class="ambd-links">
                        <a class="ambd-link" href="/sale-reports?quarter=<?php echo $quarter_tab; ?>">📊 Báo cáo bán hàng &amp; KPI</a>
                        <a class="ambd-link alt" href="/my-com">💰 Hoa hồng của tôi</a>
                        <a class="ambd-link alt" href="/my-debt">📌 Công nợ của tôi</a>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        (function () {
            const fmt = v => new Intl.NumberFormat('vi-VN').format(v) + ' đ';
            const months = <?php echo json_encode(array_map(fn($m) => 'Tháng ' . $m, array_keys($monthly_rev))); ?>;
            const data = <?php echo json_encode(array_map('floatval', array_values($monthly_rev))); ?>;
            new ApexCharts(document.querySelector('#ambd-monthly'), {
                series: [{ name: 'Doanh thu', data: data }],
                chart: { type: 'bar', height: 300, toolbar: { show: false } },
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', dataLabels: { position: 'top' } } },
                dataLabels: { enabled: true, formatter: v => v > 0 ? (v / 1e6).toFixed(0) + 'M' : '', offsetY: -18, style: { fontSize: '11px', colors: ['#475569'] } },
                colors: ['#10b981'],
                xaxis: { categories: months },
                yaxis: { labels: { formatter: v => (v / 1e6).toFixed(0) + 'M' } },
                grid: { borderColor: '#f1f5f9' },
                tooltip: { y: { formatter: v => fmt(v) } }
            }).render();
        })();
    </script>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>
