<?php
/**
 * AM/BD dashboard persona.
 *
 * Self-contained page rendered for is_am_bd users (non-admin) from
 * modules/dashboard/dashboard.php. It reuses the exact data model the
 * Sale Reports module already relies on:
 *   - revenue  : posted, non-internal Odoo invoices filtered by the user's email
 *                (read from the local invoices cache, so this is cheap)
 *   - target   : sale_levels resolved through user_sale_level_history
 *   - status   : latest sale_report_confirmations event for the quarter
 *
 * Expects from the including scope: $conn, $user_id, $full_name, $avatar.
 */
require_once __DIR__ . '/../../../libs/OdooAPI.php';

$odoo = new OdooAPI();

// ── Current period ──────────────────────────────────────────────────────────
$cur_year    = (int) date('Y');
$cur_month   = (int) date('n');
$cur_quarter = (int) ceil($cur_month / 3);
$quarter_tab = "Q{$cur_quarter}_{$cur_year}";

$q_start_month = ($cur_quarter - 1) * 3 + 1;
$q_end_month   = $cur_quarter * 3;
$q_start_date  = sprintf('%d-%02d-01', $cur_year, $q_start_month);
$q_end_date    = date('Y-m-t', strtotime(sprintf('%d-%02d-01', $cur_year, $q_end_month)));
$ytd_start     = "$cur_year-01-01";

// ── User email (needed to filter Odoo invoices) ───────────────────────────────
$user_email = '';
$stmt_e = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt_e->bind_param("i", $user_id);
$stmt_e->execute();
if ($row = $stmt_e->get_result()->fetch_assoc()) {
    $user_email = $row['email'] ?? '';
}

/**
 * Convert one Odoo invoice to VND using the same robust ratio method as
 * modules/sale_reports/index.php (respects the accountant's manual rate
 * stamped on the invoice via amount_total_signed).
 */
function ambd_invoice_vnd(OdooAPI $odoo, array $inv): float
{
    $currencyCode = is_array($inv['currency_id'] ?? null) ? $inv['currency_id'][1] : 'VND';
    $odoo_total   = (float) ($inv['amount_total'] ?? 0);
    $odoo_signed  = abs((float) ($inv['amount_total_signed'] ?? 0));
    $inv_date     = $inv['invoice_date'] ?: ($inv['date'] ?? date('Y-m-d'));

    if ($currencyCode === 'VND') {
        return $odoo_total;
    }

    $compName = is_array($inv['company_id'] ?? null) ? $inv['company_id'][1] : null;
    $amountVnd = 0.0;

    if ($odoo_total > 0 && $odoo_signed > 0) {
        $ratio = $odoo_signed / $odoo_total;
        if ($ratio > 100) {
            // Odoo base currency already VND
            $amountVnd = $odoo_total * $ratio;
        } else {
            // Intermediate base currency (e.g. MYR) — scale into VND
            $amountVnd = $odoo_total * $ratio * ($odoo->getRate('VND', $inv_date, $compName) ?: 1.0);
        }
    }
    if ($amountVnd == 0 && $odoo_total > 0) {
        $vnd_multiplier = $odoo->getRate('VND', $inv_date, $compName) ?: 1.0;
        $rateSource     = $odoo->getRate($currencyCode, $inv_date, $compName) ?: 1.0;
        $amountVnd = ($rateSource > 0) ? (($odoo_total / $rateSource) * $vnd_multiplier) : $odoo_total;
    }
    return $amountVnd;
}

// ── Revenue (this quarter + YTD) from cached Odoo invoices ─────────────────────
$rev_quarter_vnd = 0.0;
$rev_ytd_vnd     = 0.0;
$invoice_count_q = 0;
$monthly_rev     = [$q_start_month => 0.0, $q_start_month + 1 => 0.0, $q_start_month + 2 => 0.0];

// Local exclusion flags (an invoice can be excluded from KPI in Sale Reports)
$excluded = [];
$res_excl = $conn->query("SELECT odoo_invoice_id FROM sale_reports WHERE is_excluded = 1");
if ($res_excl) {
    while ($r = $res_excl->fetch_assoc()) $excluded[(int) $r['odoo_invoice_id']] = true;
}

$invoices = [];
if ($user_email) {
    try {
        $result = $odoo->getInvoices(5000, 0, ['owner_email' => $user_email]);
        $invoices = $result['invoices'] ?? [];
    } catch (Exception $e) {
        $invoices = [];
    }
}

foreach ($invoices as $inv) {
    if (($inv['state'] ?? '') !== 'posted') continue;
    if (($inv['x_studio_invoice_type_1'] ?? '') === 'Internal') continue;
    if (!empty($excluded[(int) ($inv['id'] ?? 0)])) continue;

    $inv_date = $inv['invoice_date'] ?: ($inv['date'] ?? '');
    if (!$inv_date) continue;

    // YTD uses Odoo's own signed total (matches Sale Reports YTD logic)
    if ($inv_date >= $ytd_start && $inv_date <= $q_end_date) {
        $rev_ytd_vnd += isset($inv['amount_total_signed']) ? (float) $inv['amount_total_signed'] : 0;
    }

    // This-quarter detail uses the robust VND conversion
    if ($inv_date >= $q_start_date && $inv_date <= $q_end_date) {
        $vnd = ambd_invoice_vnd($odoo, $inv);
        $rev_quarter_vnd += $vnd;
        $invoice_count_q++;
        $m = (int) date('n', strtotime($inv_date));
        if (isset($monthly_rev[$m])) $monthly_rev[$m] += $vnd;
    }
}

// ── KPI target from sale level (effective for this quarter) ────────────────────
$eff_level_id = null;
$stmt_hist = $conn->prepare("
    SELECT sale_level_id FROM user_sale_level_history
    WHERE user_id = ? AND (apply_year < ? OR (apply_year = ? AND apply_quarter <= ?))
    ORDER BY apply_year DESC, apply_quarter DESC LIMIT 1
");
if ($stmt_hist) {
    $stmt_hist->bind_param("iiii", $user_id, $cur_year, $cur_year, $cur_quarter);
    $stmt_hist->execute();
    if ($r = $stmt_hist->get_result()->fetch_assoc()) $eff_level_id = $r['sale_level_id'];
}

$kpi = null;
if ($eff_level_id) {
    $stmt_k = $conn->prepare("SELECT level_name, position_type, color_badge, kpi_quarter_vnd, kpi_yearly_vnd, kpi_quarter_usd, kpi_yearly_usd FROM sale_levels WHERE id = ?");
    $stmt_k->bind_param("i", $eff_level_id);
} else {
    $stmt_k = $conn->prepare("SELECT sl.level_name, sl.position_type, sl.color_badge, sl.kpi_quarter_vnd, sl.kpi_yearly_vnd, sl.kpi_quarter_usd, sl.kpi_yearly_usd FROM users u LEFT JOIN sale_levels sl ON u.sale_level_id = sl.id WHERE u.id = ?");
    $stmt_k->bind_param("i", $user_id);
}
if ($stmt_k) {
    $stmt_k->execute();
    $krow = $stmt_k->get_result()->fetch_assoc();
    if ($krow && !empty($krow['level_name'])) $kpi = $krow;
}

$target_q_vnd = (float) ($kpi['kpi_quarter_vnd'] ?? 0);
$target_y_vnd = (float) ($kpi['kpi_yearly_vnd'] ?? 0);
$pct_q = $target_q_vnd > 0 ? min(100, round($rev_quarter_vnd / $target_q_vnd * 100)) : 0;
$pct_y = $target_y_vnd > 0 ? min(100, round($rev_ytd_vnd / $target_y_vnd * 100)) : 0;

// Progress status helper
function ambd_status(float $pct, int $quarter_progress): array
{
    if ($pct >= 100) return ['Đạt', '#d1fae5', '#065f46'];
    if ($pct >= $quarter_progress) return ['Đúng tiến độ', '#dbeafe', '#1d4ed8'];
    if ($pct >= $quarter_progress - 20) return ['Cần đẩy', '#fef3c7', '#92400e'];
    return ['Chậm', '#fee2e2', '#991b1b'];
}
// Expected linear progress through the quarter (by elapsed days)
$days_total   = (strtotime($q_end_date) - strtotime($q_start_date)) / 86400 + 1;
$days_elapsed = min($days_total, max(0, (time() - strtotime($q_start_date)) / 86400));
$quarter_progress = (int) round($days_elapsed / $days_total * 100);
[$status_label, $status_bg, $status_fg] = ambd_status($pct_q, $quarter_progress);

// ── Commission / KPI confirmation status for this quarter ─────────────────────
$conf_label = 'Chưa xác nhận';
$conf_bg = '#f1f5f9'; $conf_fg = '#64748b'; $conf_detail = '';
$res_c = $conn->query("SELECT type, confirmed_at, confirmed_by_name FROM sale_report_confirmations WHERE user_id = $user_id AND quarter = '$quarter_tab' ORDER BY confirmed_at DESC LIMIT 1");
if ($res_c && ($c = $res_c->fetch_assoc())) {
    $when = date('H:i d/m/Y', strtotime($c['confirmed_at']));
    if ($c['type'] === 'commission_confirmed') {
        $conf_label = 'Đã chốt hoa hồng'; $conf_bg = '#d1fae5'; $conf_fg = '#065f46'; $conf_detail = $when;
    } elseif ($c['type'] === 'confirmed') {
        $conf_label = 'Đã xác nhận KPI'; $conf_bg = '#dbeafe'; $conf_fg = '#1d4ed8'; $conf_detail = $when;
    } elseif ($c['type'] === 'reset') {
        $conf_label = 'Đã mở lại (nháp)'; $conf_bg = '#fef3c7'; $conf_fg = '#92400e'; $conf_detail = $when;
    }
}

// ── Debt to collect (this user's sale teams, pending) ─────────────────────────
$my_pending_vnd = 0.0; $my_pending_cnt = 0;
$team_ids = [];
$st = $conn->prepare("SELECT team_id FROM user_sale_teams WHERE user_id = ?");
$st->bind_param("i", $user_id);
$st->execute();
$tr = $st->get_result();
while ($r = $tr->fetch_assoc()) $team_ids[] = (int) $r['team_id'];
if ($team_ids) {
    $in = implode(',', $team_ids);
    $res_d = $conn->query("SELECT amount, currency, payment_status, invoice_date, odoo_invoice_id FROM debts WHERE sale_team_id IN ($in)");
    if ($res_d) {
        while ($d = $res_d->fetch_assoc()) {
            if (strcasecmp(trim($d['payment_status'] ?? ''), 'Paid') === 0) continue;
            $amt = (float) $d['amount'];
            $curr = $d['currency'] ?: 'USD';
            $date = $d['invoice_date'] ?: date('Y-m-d');
            if ($curr === 'VND') {
                $my_pending_vnd += $amt;
            } else {
                $rate = $odoo->getRate($curr, $date) ?: 0;
                $my_pending_vnd += ($rate > 0) ? ($amt / $rate) : $amt;
            }
            $my_pending_cnt++;
        }
    }
}

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
        .ambd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .ambd-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); position: relative; overflow: hidden; }
        .ambd-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .ambd-card.blue::before { background: #3b82f6; } .ambd-card.green::before { background: #10b981; }
        .ambd-card.amber::before { background: #f59e0b; } .ambd-card.rose::before { background: #f43f5e; }
        .ambd-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .ambd-value { font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.15; }
        .ambd-sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .ambd-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 99px; font-size: 13px; font-weight: 700; }
        .pbar { height: 9px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin-top: 10px; }
        .pfill { height: 100%; border-radius: 99px; transition: width .6s ease; }
        .ambd-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); margin-bottom: 24px; }
        .ambd-panel h3 { margin: 0 0 4px; font-size: 1.05rem; color: #0f172a; }
        .ambd-links { display: flex; gap: 12px; flex-wrap: wrap; }
        .ambd-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; transition: .2s; }
        .ambd-link.alt { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
        .ambd-link:hover { transform: translateY(-1px); }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Dashboard';
            $page_subtitle = 'Xin chào, <strong>' . htmlspecialchars($full_name) . '</strong> · Quý ' . $cur_quarter . '/' . $cur_year;
            include __DIR__ . '/../../includes/topbar.php';
            ?>
            <div class="content-wrapper">

                <!-- Sale level banner -->
                <div class="ambd-panel" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="margin:0;">Hiệu suất kinh doanh của bạn</h3>
                        <p style="margin:4px 0 0; font-size:13px; color:#64748b;">Doanh thu hóa đơn (posted) trong Quý <?php echo $cur_quarter; ?>/<?php echo $cur_year; ?> so với mục tiêu cấp bậc.</p>
                    </div>
                    <?php if ($kpi): ?>
                    <span class="ambd-badge" style="background:<?php echo htmlspecialchars($badge_color); ?>; color:#fff;">
                        <?php echo htmlspecialchars($kpi['level_name']); ?>
                        <?php if (!empty($kpi['position_type'])): ?>· <?php echo htmlspecialchars($kpi['position_type']); ?><?php endif; ?>
                    </span>
                    <?php else: ?>
                    <span class="ambd-badge" style="background:#f1f5f9; color:#64748b;">Chưa gán cấp bậc Sale</span>
                    <?php endif; ?>
                </div>

                <!-- Stat cards -->
                <div class="ambd-grid">
                    <div class="ambd-card green">
                        <div class="ambd-label">Doanh thu Quý <?php echo $cur_quarter; ?></div>
                        <div class="ambd-value" style="color:#059669;"><?php echo $fmtVnd($rev_quarter_vnd); ?></div>
                        <div class="ambd-sub"><?php echo $invoice_count_q; ?> hóa đơn (đã loại trừ mục bị exclude)</div>
                    </div>

                    <div class="ambd-card blue">
                        <div class="ambd-label">Mục tiêu Quý</div>
                        <div class="ambd-value" style="color:#2563eb;"><?php echo $target_q_vnd > 0 ? $fmtVnd($target_q_vnd) : '—'; ?></div>
                        <?php if ($target_q_vnd > 0): ?>
                        <div class="pbar"><div class="pfill" style="width:<?php echo $pct_q; ?>%; background:<?php echo $status_fg; ?>;"></div></div>
                        <div class="ambd-sub"><?php echo $pct_q; ?>% · kỳ vọng ~<?php echo $quarter_progress; ?>% theo thời gian</div>
                        <?php else: ?>
                        <div class="ambd-sub"><?php echo $kpi ? 'Cấp bậc chưa đặt mục tiêu VND' : 'Chưa có cấp bậc Sale'; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="ambd-card amber">
                        <div class="ambd-label">Trạng thái tiến độ</div>
                        <div style="margin:4px 0 8px;"><span class="ambd-badge" style="background:<?php echo $status_bg; ?>; color:<?php echo $status_fg; ?>;"><?php echo $status_label; ?></span></div>
                        <div class="ambd-sub">Lũy kế năm: <?php echo $target_y_vnd > 0 ? ($pct_y . '% mục tiêu năm') : $fmtVnd($rev_ytd_vnd); ?></div>
                    </div>

                    <div class="ambd-card rose">
                        <div class="ambd-label">Công nợ cần thu</div>
                        <div class="ambd-value" style="color:#e11d48;"><?php echo $fmtVnd($my_pending_vnd); ?></div>
                        <div class="ambd-sub"><?php echo $my_pending_cnt; ?> hóa đơn chưa thanh toán (team của bạn)</div>
                    </div>
                </div>

                <!-- Monthly revenue + confirmation -->
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-bottom:24px;">
                    <div class="ambd-panel">
                        <h3>Doanh thu theo tháng — Quý <?php echo $cur_quarter; ?></h3>
                        <div id="ambd-monthly"></div>
                    </div>
                    <div class="ambd-panel">
                        <h3>Xác nhận Quý <?php echo $cur_quarter; ?></h3>
                        <p style="margin:4px 0 14px; font-size:13px; color:#64748b;">Trạng thái chốt KPI / hoa hồng quý này.</p>
                        <span class="ambd-badge" style="background:<?php echo $conf_bg; ?>; color:<?php echo $conf_fg; ?>;"><?php echo $conf_label; ?></span>
                        <?php if ($conf_detail): ?><div class="ambd-sub" style="margin-top:10px;">Lần cuối: <?php echo $conf_detail; ?></div><?php endif; ?>
                    </div>
                </div>

                <!-- Quick links to detailed modules -->
                <div class="ambd-panel">
                    <h3 style="margin-bottom:14px;">Đi tới chi tiết</h3>
                    <div class="ambd-links">
                        <a class="ambd-link" href="/sale-reports">📊 Báo cáo bán hàng &amp; KPI</a>
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
