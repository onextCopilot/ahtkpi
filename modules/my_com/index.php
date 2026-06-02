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

// ── User's Sale Level & KPI Target ──
$user_level = null;
$kpi_quarter_target = 0;
$kpi_yearly_target = 0;
$position_type = '';

/**
 * Resolve the sale level "in effect" for a given quarter using NEAREST-quarter rule:
 *  1. The most recent level applied ON OR BEFORE that quarter (level in effect at the time)
 *  2. If none exists before it, the EAREST level applied AFTER it (nearest future quarter)
 * Returns the sale_levels row (joined) or null if the user has no history at all.
 */
function mc_level_for_quarter($conn, $u_id, $y, $q) {
    // 1) Most recent applied on/before
    $stmt = $conn->prepare("SELECT sl.*, h.apply_year AS h_year, h.apply_quarter AS h_quarter FROM user_sale_level_history h JOIN sale_levels sl ON h.sale_level_id = sl.id WHERE h.user_id = ? AND (h.apply_year < ? OR (h.apply_year = ? AND h.apply_quarter <= ?)) ORDER BY h.apply_year DESC, h.apply_quarter DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("iiii", $u_id, $y, $y, $q);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return $row;
    }
    // 2) Nearest applied after (earliest future)
    $stmt = $conn->prepare("SELECT sl.*, h.apply_year AS h_year, h.apply_quarter AS h_quarter FROM user_sale_level_history h JOIN sale_levels sl ON h.sale_level_id = sl.id WHERE h.user_id = ? AND (h.apply_year > ? OR (h.apply_year = ? AND h.apply_quarter > ?)) ORDER BY h.apply_year ASC, h.apply_quarter ASC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("iiii", $u_id, $y, $y, $q);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return $row;
    }
    return null;
}

// Check history first for the selected quarter, fallback to users.sale_level_id
$current_year = (int) date('Y');
$current_month = (int) date('n');
$current_quarter = (int) ceil($current_month / 3);
$selected_year = isset($_GET['year']) ? (int) $_GET['year'] : $current_year;
$selected_quarter = isset($_GET['quarter']) ? (int) $_GET['quarter'] : $current_quarter;
if ($selected_quarter < 1 || $selected_quarter > 4) $selected_quarter = $current_quarter;

// Level for the selected quarter using NEAREST-quarter rule (prior preferred, else nearest future)
$user_level = mc_level_for_quarter($conn, $u_id, $selected_year, $selected_quarter);

// Only if the user has NO history at all → fall back to users.sale_level_id
if (!$user_level) {
    $sl_stmt = $conn->prepare("SELECT sl.* FROM users u JOIN sale_levels sl ON u.sale_level_id = sl.id WHERE u.id = ?");
    $sl_stmt->bind_param("i", $u_id);
    $sl_stmt->execute();
    $sl_res = $sl_stmt->get_result();
    if ($r = $sl_res->fetch_assoc()) $user_level = $r;
    $sl_stmt->close();
}

$level_borrowed_from = '';
if ($user_level) {
    $kpi_quarter_target = (float) $user_level['kpi_quarter_vnd'];
    $kpi_yearly_target = (float) $user_level['kpi_yearly_vnd'];
    $position_type = $user_level['position_type'] ?? '';
    // Note when the selected quarter's level was borrowed from another quarter (nearest-quarter rule)
    if (isset($user_level['h_year'], $user_level['h_quarter']) &&
        ((int)$user_level['h_year'] !== $selected_year || (int)$user_level['h_quarter'] !== $selected_quarter)) {
        $level_borrowed_from = "Q{$user_level['h_quarter']}/{$user_level['h_year']}";
    }
}

// ── Quarter date range ──
$quarter_months = [1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12]];
[$q_start_month, $q_end_month] = $quarter_months[$selected_quarter];
$date_from = sprintf('%04d-%02d-01', $selected_year, $q_start_month);
$date_to = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $selected_year, $q_end_month)));

// ── Fetch invoices from Odoo cache ──
$invoices = [];
$all_invoices = [];
$collected_invoices = [];
$error = null;
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

try {
    $odoo = new OdooAPI();
    $filters = ['owner_email' => $user_email, 'search' => $search, 'status' => $status_filter];
    $all_result = $odoo->getInvoices(10000, 0, $filters);
    $all_invoices = $all_result['invoices'] ?? [];

    $excluded_types = ['Internal', 'Commission', 'License'];

    foreach ($all_invoices as $inv) {
        $inv_type = $inv['x_studio_invoice_type_1'] ?? '';
        if (in_array($inv_type, $excluded_types)) continue;
        $inv_date = $inv['invoice_date'] ?: $inv['date'] ?? '';
        if (empty($inv_date) || $inv_date < $date_from || $inv_date > $date_to) continue;
        if (($inv['state'] ?? '') === 'cancel') continue;
        $invoices[] = $inv;
    }
    // ── Invoices from PREVIOUS quarters but PAID in this quarter ──
    $invoiced_ids = array_map(fn($i) => $i['id'], $invoices);
    foreach ($all_invoices as $inv) {
        $inv_type = $inv['x_studio_invoice_type_1'] ?? '';
        if (in_array($inv_type, $excluded_types)) continue;
        if (in_array($inv['id'], $invoiced_ids)) continue;
        if (($inv['state'] ?? '') === 'cancel') continue;
        $ps = $inv['payment_state'] ?? '';
        if ($ps !== 'paid' && $ps !== 'in_payment') continue;

        // Determine payment date from invoice_payments_widget or write_date
        $pay_date = null;
        if (!empty($inv['invoice_payments_widget'])) {
            $widget = $inv['invoice_payments_widget'];
            if (is_string($widget)) $widget = json_decode($widget, true);
            if (!empty($widget['content'])) {
                $dates = array_column($widget['content'], 'date');
                if ($dates) $pay_date = max($dates);
            }
        }
        if (!$pay_date) $pay_date = $inv['write_date'] ?? null;
        if (!$pay_date) continue;

        $pay_date_ymd = substr($pay_date, 0, 10);
        if ($pay_date_ymd >= $date_from && $pay_date_ymd <= $date_to) {
            $inv['_payment_date'] = $pay_date_ymd;
            $collected_invoices[] = $inv;
        }
    }

    // ── License-type invoices (separate License Bonus, NOT counted in KPI revenue) ──
    // These are deliberately excluded from $invoices/$collected_invoices above so they
    // never affect KPI. They get their own section + bonus = 10% × EBT(License).
    $license_invoices = [];
    foreach ($all_invoices as $inv) {
        if (($inv['x_studio_invoice_type_1'] ?? '') !== 'License') continue;
        if (($inv['state'] ?? '') === 'cancel') continue;
        $inv_date = $inv['invoice_date'] ?: $inv['date'] ?? '';
        if (empty($inv_date) || $inv_date < $date_from || $inv_date > $date_to) continue;
        $license_invoices[] = $inv;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
$license_invoices = $license_invoices ?? [];

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
foreach ($pakd_list as $p) {
    $pakd_map[$p['id']] = $p;
}

// Ensure invoice_pakd_map table exists
$conn->query("CREATE TABLE IF NOT EXISTS invoice_pakd_map (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    invoice_id           INT NOT NULL,
    pakd_id              INT DEFAULT NULL,
    pakd_link            VARCHAR(500) DEFAULT NULL,
    manual_ebt           DECIMAL(20,2) DEFAULT NULL,
    com2_tier            VARCHAR(50) DEFAULT NULL,
    com2_hv              DECIMAL(20,2) DEFAULT NULL,
    com2_hv_currency     VARCHAR(10) DEFAULT 'VND',
    lead_source          VARCHAR(100) DEFAULT NULL,
    ai_addon             TINYINT(1) DEFAULT 0,
    ai_revenue           DECIMAL(20,2) DEFAULT NULL,
    ai_revenue_currency  VARCHAR(10) DEFAULT 'VND',
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_invoice (user_id, invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load existing invoice->PAKD mappings (pakd_id, pakd_link, manual_ebt)
$inv_pakd_map = [];
$map_stmt = $conn->prepare("SELECT invoice_id, pakd_id, pakd_link, manual_ebt, com2_tier, com2_hv, com2_hv_currency, lead_source, ai_addon, ai_revenue, ai_revenue_currency FROM invoice_pakd_map WHERE user_id = ?");
if ($map_stmt) {
    $map_stmt->bind_param("i", $u_id);
    $map_stmt->execute();
    $map_res = $map_stmt->get_result();
    while ($mr = $map_res->fetch_assoc()) $inv_pakd_map[(int)$mr['invoice_id']] = $mr;
    $map_stmt->close();
}

// Currency → VND conversion rates for HV — sourced entirely from Odoo.
// The available currencies AND their symbols come from Odoo (res.currency).
// VND is the base (rate 1). Each rate = how many VND for 1 unit of that currency:
//   rate(cur→VND) = getRate('VND') / getRate(cur)   (both relative to Odoo company currency)
$hv_currencies = ['VND'];   // ordered list shown in the dropdown
$hv_symbols    = ['VND' => '₫'];
$hv_rates      = ['VND' => 1.0];
try {
    if (isset($odoo) && $odoo) {
        $r_vnd = (float) $odoo->getRate('VND', date('Y-m-d'));
        $odoo_currencies = $odoo->getCurrencies();   // [name => ['symbol'=>..,'rate'=>..], ...]
        if ($r_vnd > 0 && is_array($odoo_currencies)) {
            // Preferred display order; any other Odoo currency is appended after.
            $preferred = ['USD', 'EUR', 'GBP', 'AUD', 'JPY', 'KRW', 'MYR'];
            $names = array_keys($odoo_currencies);
            usort($names, function ($a, $b) use ($preferred) {
                $ia = array_search($a, $preferred); $ib = array_search($b, $preferred);
                if ($ia === false) $ia = PHP_INT_MAX;
                if ($ib === false) $ib = PHP_INT_MAX;
                return $ia === $ib ? strcmp($a, $b) : $ia - $ib;
            });
            foreach ($names as $cur) {
                if ($cur === 'VND') continue;   // already the base
                $r_cur = (float) $odoo->getRate($cur, date('Y-m-d'));
                if ($r_cur <= 0) continue;       // skip currencies Odoo has no rate for
                $rate = $r_vnd / $r_cur;
                if ($rate <= 0) continue;
                $hv_currencies[] = $cur;
                $hv_symbols[$cur] = $odoo_currencies[$cur]['symbol'] ?? $cur;
                $hv_rates[$cur]   = $rate;
            }
        }
    }
} catch (Throwable $e) { /* VND-only fallback keeps the page working */ }
// Back-compat single USD rate (used in a few spots / data attributes)
$usd_to_vnd = $hv_rates['USD'] ?? 26000;

// Helper: convert an amount in a given currency to VND using the rate map.
function mc_to_vnd($amount, $cur, $hv_rates) {
    if ($amount === null || $amount === '') return null;
    $rate = isset($hv_rates[$cur]) ? (float) $hv_rates[$cur] : 1.0;
    return (float) $amount * $rate;
}

// Helper: HV value converted to VND using the multi-currency rate map
function mc_hv_to_vnd($row_map, $hv_rates) {
    if (!$row_map || !isset($row_map['com2_hv']) || $row_map['com2_hv'] === null || $row_map['com2_hv'] === '') return null;
    $cur = $row_map['com2_hv_currency'] ?? 'VND';
    return mc_to_vnd((float) $row_map['com2_hv'], $cur, $hv_rates);
}

// Helper: AI Revenue value converted to VND.
function mc_ai_rev_to_vnd($row_map, $hv_rates) {
    if (!$row_map || !isset($row_map['ai_revenue']) || $row_map['ai_revenue'] === null || $row_map['ai_revenue'] === '') return null;
    $cur = $row_map['ai_revenue_currency'] ?? 'VND';
    return mc_to_vnd((float) $row_map['ai_revenue'], $cur, $hv_rates);
}

// ── Sale Orders — First PO Commission (1/1000) ──
define('SO_COM_RATE', 0.001);
define('SO_MIN_VND',  1_000_000_000); // chỉ tính commission khi SO ≥ 1 tỷ VND

$conn->query("CREATE TABLE IF NOT EXISTS so_first_po_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    so_odoo_id INT NOT NULL,
    is_first_po TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_so (user_id, so_odoo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$so_list   = [];
$so_error  = null;
try {
    if (isset($odoo) && $odoo && !empty($user_email)) {
        $ou = $odoo->searchRead('res.users', [['login', '=', $user_email]], ['id'], 1);
        $odoo_user_id = !empty($ou[0]['id']) ? (int)$ou[0]['id'] : null;
        if ($odoo_user_id) {
            // Show confirmed/done SOs created within the selected quarter
            $so_domain = [
                ['user_id', '=', $odoo_user_id],
                ['state', 'in', ['sale', 'done']],
                ['date_order', '>=', $date_from],
                ['date_order', '<=', $date_to],
            ];
            $so_fields = ['id', 'name', 'partner_id', 'date_order', 'amount_total', 'currency_id', 'state', 'client_order_ref'];
            $raw_sos = $odoo->searchRead('sale.order', $so_domain, $so_fields, 0, 0);

            // Build per-currency rates for SO conversion.
            // $hv_rates uses r_vnd/r_cur which picks up cross-company VND entries
            // from non-VND-base companies (e.g. MYR-base → VND≈6622). For VN context
            // VND is base, so the correct factor is simply 1/r_cur (inverse factor).
            $so_currency_rates = ['VND' => 1.0];
            foreach ((array)$raw_sos as $so) {
                $cur = is_array($so['currency_id']) ? ($so['currency_id'][1] ?? 'USD') : 'USD';
                if ($cur !== 'VND' && !isset($so_currency_rates[$cur])) {
                    $r = (float)$odoo->getRate($cur, date('Y-m-d'));
                    $so_currency_rates[$cur] = ($r > 0) ? (1.0 / $r) : 1.0;
                }
            }

            foreach ((array)$raw_sos as $so) {
                $cur        = is_array($so['currency_id']) ? ($so['currency_id'][1] ?? 'USD') : 'USD';
                $rate       = $so_currency_rates[$cur] ?? 1.0;
                $amount_vnd = (float)$so['amount_total'] * $rate;
                $so['_cur']          = $cur;
                $so['_amount_vnd']   = $amount_vnd;
                $so['_partner_name'] = is_array($so['partner_id']) ? ($so['partner_id'][1] ?? '') : '';
                $so_list[] = $so;
            }
            usort($so_list, fn($a, $b) => strcmp($b['date_order'] ?? '', $a['date_order'] ?? ''));
        }
    }
} catch (Throwable $e) {
    $so_error = $e->getMessage();
}

// Load saved First PO flags
$so_first_po_flags = [];
if (!empty($so_list)) {
    $so_ids = array_map(fn($s) => (int)$s['id'], $so_list);
    $ph     = implode(',', array_fill(0, count($so_ids), '?'));
    $params = array_merge([$u_id], $so_ids);
    $types  = 'i' . str_repeat('i', count($so_ids));
    $fp_stmt = $conn->prepare("SELECT so_odoo_id, is_first_po FROM so_first_po_map WHERE user_id = ? AND so_odoo_id IN ($ph)");
    if ($fp_stmt) {
        $fp_stmt->bind_param($types, ...$params);
        $fp_stmt->execute();
        $fp_res = $fp_stmt->get_result();
        while ($r = $fp_res->fetch_assoc()) $so_first_po_flags[(int)$r['so_odoo_id']] = (bool)$r['is_first_po'];
        $fp_stmt->close();
    }
}

// Pre-compute total First PO commission
$total_so_com = 0.0;
foreach ($so_list as $so) {
    if ($so_first_po_flags[$so['id']] ?? false) $total_so_com += $so['_amount_vnd'] * SO_COM_RATE;
}

// ── Market to Lead source ──
// If the salesperson sourced the lead themselves ("My Lead"), Com1 gets +1%.
$lead_options = [
    ''      => '— Nguồn lead —',
    'self'  => 'My Lead',
    'mkt'   => 'MKT',
    'refer' => 'Refer',
];
const MC_SELF_LEAD_BONUS = 0.01;   // +1% to Com1 when self-sourced (New clients only)

// Render a Market-to-Lead <select> for a given invoice + selected value.
// Only New-client invoices may pick a lead source (the +1% bonus is New-only).
function mc_lead_select($inv_id, $selected, $lead_options, $is_new = true) {
    $dis = $is_new ? '' : ' disabled title="Chỉ áp dụng cho khách New"';
    $h = '<select class="lead-select" data-inv="' . (int)$inv_id . '" onchange="saveLead(this)"' . $dis . '>';
    if (!$is_new) {
        // Old clients: show a single inert placeholder.
        $h .= '<option value="">—</option></select>';
        return $h;
    }
    foreach ($lead_options as $val => $label) {
        $sel = ((string)$selected === (string)$val) ? ' selected' : '';
        $h .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    $h .= '</select>';
    return $h;
}

// Format a commission rate (e.g. 0.015) as a tidy percent string ("1.5").
function mc_rate_pct($rate) {
    return rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.');
}

// ── AI Add-on ──
// AIHive Solutions: EBT ≥ 20% → 5%, EBT ≥ 10% → 2% (else 0)
// AI Solutions:     EBT ≥ 15% → 2% (else 0)
// Both also require KPI ≥ 60% and Collection = 1 (paid). AI Com = Revenue(VND) × rate.
$ai_options = [
    ''             => '— AI Add-on —',
    'ai_solutions' => 'AI Solutions',
    'aihive'       => 'AIHive Solutions',
];

// Commission rate for the AI add-on given the add-on type and EBT %.
function mc_ai_rate($addon, $ebt_pct) {
    if ($ebt_pct === null) return 0;
    if ($addon === 'aihive') {
        if ($ebt_pct >= 20) return 0.05;
        if ($ebt_pct >= 10) return 0.02;
        return 0;
    }
    if ($addon === 'ai_solutions') {
        if ($ebt_pct >= 15) return 0.02;
        return 0;
    }
    return 0;
}

// Minimum EBT to qualify for any AI add-on commission (AIHive Tier 2).
const MC_AI_MIN_EBT = 10;
// Tooltip shown when the AI add-on can't be chosen yet.
const MC_AI_GATE_MSG = 'Chỉ chọn được khi KPI ≥ 60%, EBT ≥ 10% & đã thanh toán (Collection)';

// ── Yearly Bonus (end-of-year settlement) ──
// EBT ≥ 20% → 4% of revenue · EBT ≥ 12.5% → 2% · else 0.
// Shown as a per-quarter estimate (revenue realized this quarter × rate).
function mc_yb_rate($ebt_pct) {
    if ($ebt_pct === null) return 0;
    if ($ebt_pct >= 20)   return 0.04;
    if ($ebt_pct >= 12.5) return 0.02;
    return 0;
}

// ── Com2 Tier (auto) ──
// Tier% is derived from the Revenue/Base ratio, NOT chosen by hand.
//   Base = Invoice(VND) − HV(VND)   (the minimum/floor price)
//   ratio = Invoice / Base
//   ratio > 1.5 → 5% · (>1.3–1.5] → 3% · (>1–1.3] → 2% · else 0 (no Com2)
// HV null/≤0 → 0. If Base ≤ 0 (HV ≥ Invoice) the spread is maximal → top tier 5%.
function mc_auto_tier($amount_vnd, $hv_vnd) {
    if ($hv_vnd === null || $hv_vnd <= 0 || $amount_vnd === null || $amount_vnd <= 0) return 0;
    $base = $amount_vnd - $hv_vnd;
    if ($base <= 0) return 5;
    $ratio = $amount_vnd / $base;
    if ($ratio > 1.5) return 5;
    if ($ratio > 1.3) return 3;
    if ($ratio > 1.0) return 2;
    return 0;
}

// Rank label of the Revenue/Base ratio band (matches mc_auto_tier).
function mc_tier_rank($amount_vnd, $hv_vnd) {
    if ($hv_vnd === null || $hv_vnd <= 0 || $amount_vnd === null || $amount_vnd <= 0) return '';
    $base = $amount_vnd - $hv_vnd;
    if ($base <= 0) return '(&gt;1.5)';
    $ratio = $amount_vnd / $base;
    if ($ratio > 1.5) return '(&gt;1.5)';
    if ($ratio > 1.3) return '(&gt;1.3–1.5)';
    if ($ratio > 1.0) return '(&gt;1–1.3)';
    return '(≤1)';
}

// Small sub-line under the Com2 amount: auto Tier % · ratio rank band.
function mc_com2_meta($tier, $amount_vnd, $hv_vnd) {
    return '<div class="com2-meta" style="font-size:10px;color:#94a3b8;font-weight:500;line-height:1.2;">' . (int)$tier . '% · ' . mc_tier_rank($amount_vnd, $hv_vnd) . '</div>';
}

// ── License Bonus ──
// License-type invoices: Bonus = 10% × EBT(License) when quarter KPI ≥ 80%.
// License revenue does NOT count toward KPI revenue. EBT(License) = Revenue × EBT%.
const MC_LICENSE_RATE = 0.10;
const MC_LICENSE_MIN_KPI = 80;

// Render the AI Add-on <select>.
// $base_ok = the EBT-independent conditions (KPI ≥ 60% AND paid/Collection = 1).
// $ebt     = the row's EBT % (null if unknown). The select is only enabled when
//            $base_ok AND EBT ≥ MC_AI_MIN_EBT — i.e. the conditions in the rule table.
function mc_ai_select($inv_id, $selected, $ai_options, $base_ok = true, $ebt = null) {
    $enabled = $base_ok && $ebt !== null && $ebt >= MC_AI_MIN_EBT;
    $dis = $enabled ? '' : ' disabled title="' . htmlspecialchars(MC_AI_GATE_MSG) . '"';
    $h = '<select class="ai-select" data-inv="' . (int)$inv_id . '" data-baseok="' . ($base_ok ? 1 : 0) . '" onchange="saveAiAddon(this)"' . $dis . '>';
    foreach ($ai_options as $val => $label) {
        $sel = ((string)$selected === (string)$val) ? ' selected' : '';
        $h .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    $h .= '</select>';
    return $h;
}

// Render the AI Revenue cell (amount + currency selector), mirroring the HV cell.
// Disabled (greyed) unless the row meets the AI add-on conditions.
function mc_ai_rev_cell($inv_id, $rev, $cur, $rev_vnd, $hv_currencies, $hv_symbols, $base_ok = true, $ebt = null) {
    $enabled = $base_ok && $ebt !== null && $ebt >= MC_AI_MIN_EBT;
    $dis = $enabled ? '' : ' disabled';
    $wrap_title = $enabled ? '' : ' title="' . htmlspecialchars(MC_AI_GATE_MSG) . '"';
    ob_start(); ?>
    <td class="airev-cell" data-inv="<?= (int)$inv_id ?>">
        <div class="hv-wrap"<?= $wrap_title ?>>
            <input type="number" class="airev-input" value="<?= $rev !== null ? $rev : '' ?>" step="any" placeholder="AI Rev" onchange="saveAiRev(this)" onblur="saveAiRev(this)"<?= $dis ?>>
            <select class="airev-cur" onchange="saveAiRev(this)"<?= $dis ?>>
                <?php foreach ($hv_currencies as $c): ?>
                <option value="<?= $c ?>"<?= $cur === $c ? ' selected' : '' ?>><?= $hv_symbols[$c] . ' ' . $c ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($rev !== null && $cur !== 'VND'): ?><div class="hv-vnd" title="Quy đổi VND"><?= mc_fmt_short($rev_vnd) ?></div><?php endif; ?>
    </td>
    <?php return ob_get_clean();
}

// ── Commission calculations ──
$total_invoiced = 0;
$total_com1 = 0;
$invoice_details = [];

foreach ($invoices as $inv) {
    $inv_id = (int) $inv['id'];
    $amount = (float) ($inv['amount_total'] ?? 0);
    $currency = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
    $inv_date = $inv['invoice_date'] ?: $inv['date'] ?? date('Y-m-d');

    $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));
    if ($amount_vnd == 0) $amount_vnd = $amount;

    $client_type_raw = $inv['x_studio_client_type'] ?? '';
    $is_new = (stripos($client_type_raw, 'new') !== false);

    // EBT from linked PAKD or manual entry
    $inv_map_row = $inv_pakd_map[$inv_id] ?? null;
    $linked_pakd_id = $inv_map_row ? (int)$inv_map_row['pakd_id'] : 0;
    $linked_pakd_link = $inv_map_row ? ($inv_map_row['pakd_link'] ?? '') : '';
    $linked_manual_ebt = $inv_map_row && $inv_map_row['manual_ebt'] !== null ? (float)$inv_map_row['manual_ebt'] : null;
    $linked_ebt_pct = null;
    if ($linked_manual_ebt !== null) {
        $linked_ebt_pct = $linked_manual_ebt;
    } elseif ($linked_pakd_id && isset($pakd_map[$linked_pakd_id])) {
        $lp = $pakd_map[$linked_pakd_id];
        $linked_ebt_pct = $lp['revenue'] > 0 ? ($lp['gross_profit'] / $lp['revenue'] * 100) : 0;
    }

    // Payment date
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
    $pay_date_ymd = $pay_date ? substr($pay_date, 0, 10) : '';

    // Com1: only for paid invoices with payment date in this quarter
    $is_paid = ($inv['payment_state'] ?? '') === 'paid';
    $paid_in_quarter = $is_paid && $pay_date_ymd >= $date_from && $pay_date_ymd <= $date_to;

    if (!$is_paid || !$paid_in_quarter) {
        $com1_base_rate = 0;
    } elseif ($linked_ebt_pct !== null && $linked_ebt_pct < 5) {
        $com1_base_rate = 0;
    } else {
        $com1_base_rate = $is_new ? 0.01 : 0.005;
    }
    // Market to Lead: +1% to Com1 when the lead was self-sourced (only if a Com1 applies).
    $lead_source = $inv_map_row && !empty($inv_map_row['lead_source']) ? $inv_map_row['lead_source'] : '';
    $lead_bonus = ($lead_source === 'self' && $is_new && $com1_base_rate > 0) ? MC_SELF_LEAD_BONUS : 0;
    $com1_rate = $com1_base_rate + $lead_bonus;
    $com1_amount = $amount_vnd * $com1_rate;

    // Com2 (High Value): chỉ áp dụng khi EBT >= 20% (bắt buộc). Com2 = HV(VND) × Tier %, paid-in-quarter.
    // Tier % is auto-derived from the Revenue/Base ratio (Base = Invoice − HV), NOT chosen manually.
    $hv_vnd = mc_hv_to_vnd($inv_map_row, $hv_rates);
    $tier_pct = mc_auto_tier($amount_vnd, $hv_vnd);
    $com2_ebt_ok = ($linked_ebt_pct !== null && $linked_ebt_pct >= 20);
    $com2_gross = ($tier_pct > 0 && $hv_vnd !== null && $com2_ebt_ok && $is_paid && $paid_in_quarter) ? $hv_vnd * ($tier_pct / 100) : 0;

    // AI Add-on: AI Com = AI Revenue(VND) × rate (rate depends on add-on type + EBT).
    // Requires Collection (paid) here; the KPI ≥ 60% gate is applied after the loop.
    $ai_addon = $inv_map_row && !empty($inv_map_row['ai_addon']) ? $inv_map_row['ai_addon'] : '';
    $ai_rev = $inv_map_row && isset($inv_map_row['ai_revenue']) && $inv_map_row['ai_revenue'] !== null ? (float)$inv_map_row['ai_revenue'] : null;
    $ai_rev_cur = $inv_map_row && !empty($inv_map_row['ai_revenue_currency']) ? $inv_map_row['ai_revenue_currency'] : 'VND';
    $ai_rev_vnd = mc_ai_rev_to_vnd($inv_map_row, $hv_rates);
    $ai_rate = mc_ai_rate($ai_addon, $linked_ebt_pct);
    $ai_paid_ok = ($is_paid && $paid_in_quarter);
    $ai_com_pre = ($ai_addon !== '' && $ai_rev_vnd !== null && $ai_paid_ok) ? $ai_rev_vnd * $ai_rate : 0;

    $total_invoiced += $amount_vnd;
    $total_com1 += $com1_amount;
    $total_com2 = ($total_com2 ?? 0) + $com2_gross;

    $invoice_details[] = [
        'inv' => $inv,
        'amount_vnd' => $amount_vnd,
        'client_type' => $is_new ? 'New' : 'Old',
        'com1_rate' => $com1_rate,
        'com1_base_rate' => $com1_base_rate,
        'lead_source' => $lead_source,
        'com1_amount' => $com1_amount,
        'payment_date' => $pay_date_ymd,
        'is_paid' => $is_paid,
        'paid_in_quarter' => $paid_in_quarter,
        'ebt_pct' => $linked_ebt_pct,
        'com2_tier' => $tier_pct,
        'com2_gross' => $com2_gross,
        'com2_ebt_ok' => $com2_ebt_ok,
        'com2_hv_vnd' => $hv_vnd,
        'ai_addon' => $ai_addon,
        'ai_rev' => $ai_rev,
        'ai_rev_cur' => $ai_rev_cur,
        'ai_rev_vnd' => $ai_rev_vnd,
        'ai_rate' => $ai_rate,
        'ai_paid_ok' => $ai_paid_ok,
        'ai_com_pre' => $ai_com_pre,
    ];
}
$total_com2 = $total_com2 ?? 0;

// ── KPI calculation ──
$kpi_pct = $kpi_quarter_target > 0 ? ($total_invoiced / $kpi_quarter_target * 100) : 0;
$kpi_adj = 0;
$kpi_adj_label = '';
if ($kpi_pct < 60) {
    $kpi_adj = 0;
    $kpi_adj_label = '0% (KPI < 60%)';
} elseif ($kpi_pct < 80) {
    $kpi_adj = 0.7;
    $kpi_adj_label = '70% (60% ≤ KPI < 80%)';
} else {
    $kpi_adj = 1.0;
    $kpi_adj_label = '100% (KPI ≥ 80%)';
}

$net_com1 = $total_com1 * $kpi_adj;
$net_com2 = $total_com2 * $kpi_adj;

// AI Add-on: KPI ≥ 60% is a binary gate (not the 0.7/1.0 multiplier).
$ai_kpi_ok = ($kpi_pct >= 60);
$total_ai_com = 0;
// Yearly Bonus = rate × S_EBT, where the rate is chosen from the period's AVERAGE EBT %
// (S_EBT = Σ profit = Σ revenue × EBT%; %A_EBT = S_EBT / S_revenue). Accumulate the
// EBT (profit) and revenue of paid-this-quarter invoices; the recovery loop adds its share
// and the rate + totals are finalised after both loops.
$yb_ebt_main = 0;   // S_EBT this quarter (profit, VND)
$yb_rev_main = 0;   // revenue of EBT-known invoices this quarter
foreach ($invoice_details as $d) {
    if ($ai_kpi_ok) $total_ai_com += $d['ai_com_pre'];
    if ($d['paid_in_quarter'] && $d['ebt_pct'] !== null) {
        $yb_ebt_main += $d['amount_vnd'] * $d['ebt_pct'] / 100;
        $yb_rev_main += $d['amount_vnd'];
    }
}

// ── Com2 placeholder: needs Revenue Base per project ──
// Com2 only applies when EBT >= 20%
// For now aggregate from PAKD data
$pakd_ebt_data = [];
foreach ($pakd_list as $p) {
    if ($p['revenue'] > 0 && $p['gross_profit'] > 0) {
        $ebt_pct = $p['gross_profit'] / $p['revenue'] * 100;
        $pakd_ebt_data[] = [
            'name' => $p['name'],
            'revenue' => $p['revenue'],
            'ebt' => $p['gross_profit'],
            'ebt_pct' => $ebt_pct,
        ];
    }
}

// ── Yearly totals for Yearly Bonus estimate ──
$yearly_date_from = $selected_year . '-01-01';
$yearly_date_to = $selected_year . '-12-31';
$yearly_invoiced = 0;
foreach ($all_invoices as $inv) {
    $inv_date = $inv['invoice_date'] ?: $inv['date'] ?? '';
    if (empty($inv_date) || $inv_date < $yearly_date_from || $inv_date > $yearly_date_to) continue;
    if (($inv['state'] ?? '') === 'cancel') continue;
    $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));
    if ($amount_vnd == 0) $amount_vnd = (float) ($inv['amount_total'] ?? 0);
    $yearly_invoiced += $amount_vnd;
}

// KPI salary multiplier
$kpi_salary_label = '';
if ($kpi_pct < 60) $kpi_salary_label = '0%';
elseif ($kpi_pct < 80) $kpi_salary_label = '50%';
elseif ($kpi_pct <= 150) $kpi_salary_label = '100%';
else $kpi_salary_label = '150%';

// ── Collected invoices details (paid this quarter, created earlier) ──
$collected_details = [];
$total_collected_vnd = 0;
foreach ($collected_invoices as $inv) {
    $amount = (float) ($inv['amount_total'] ?? 0);
    $currency = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
    $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));
    if ($amount_vnd == 0) $amount_vnd = $amount;
    $total_collected_vnd += $amount_vnd;
    $idate = $inv['invoice_date'] ?: $inv['date'] ?? '';
    $oy = $idate ? (int) substr($idate, 0, 4) : 0;
    $oq = $idate ? (int) ceil(((int) substr($idate, 5, 2)) / 3) : 0;
    $collected_details[] = [
        'inv' => $inv,
        'amount_vnd' => $amount_vnd,
        'payment_date' => $inv['_payment_date'] ?? '',
        'origin_year' => $oy,
        'origin_quarter' => $oq,
        'origin_key' => ($oy && $oq) ? "$oy-$oq" : '',
    ];
}

// ── Historical KPI adjustment per origin quarter (for Thu hồi công nợ) ──
// Each collected invoice gets its Com adjusted by the KPI% of the quarter it was INVOICED in,
// not the current quarter. If that quarter's KPI can't be computed → user enters manually.
$hist_kpi = []; // key "Y-Q" => ['pct','adj','target','invoiced','computed','manual_pct','year','quarter']

// Ensure user_quarter_kpi table exists
$conn->query("CREATE TABLE IF NOT EXISTS user_quarter_kpi (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    year           INT NOT NULL,
    quarter        INT NOT NULL,
    manual_kpi_pct DECIMAL(5,2) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_yq (user_id, year, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load any manual KPI overrides for this user
$manual_kpi_map = [];
$mk_stmt = $conn->prepare("SELECT year, quarter, manual_kpi_pct FROM user_quarter_kpi WHERE user_id = ?");
if ($mk_stmt) {
    $mk_stmt->bind_param("i", $u_id);
    $mk_stmt->execute();
    $mk_res = $mk_stmt->get_result();
    while ($mr = $mk_res->fetch_assoc()) {
        if ($mr['manual_kpi_pct'] !== null) $manual_kpi_map["{$mr['year']}-{$mr['quarter']}"] = (float) $mr['manual_kpi_pct'];
    }
    $mk_stmt->close();
}

// Identify origin quarters from collected invoices
$needed_q = [];
foreach ($collected_details as $cd) {
    if ($cd['origin_key'] !== '') $needed_q[$cd['origin_key']] = ['year' => $cd['origin_year'], 'quarter' => $cd['origin_quarter']];
}

foreach ($needed_q as $qk => $qi) {
    $hy = $qi['year'];
    $hq = $qi['quarter'];

    // KPI target = LEVEL for that quarter using NEAREST-quarter rule (prior preferred, else nearest future)
    $htarget = 0;
    $hlevel_name = '';
    $hrow = mc_level_for_quarter($conn, $u_id, $hy, $hq);
    if ($hrow) {
        $htarget = (float) $hrow['kpi_quarter_vnd'];
        $hlevel_name = $hrow['level_name'];
        // Mark when the level was actually borrowed from a different quarter
        if ((int)$hrow['h_year'] !== $hy || (int)$hrow['h_quarter'] !== $hq) {
            $hlevel_name .= " (theo Q{$hrow['h_quarter']}/{$hrow['h_year']})";
        }
    }
    // Fallback: current users.sale_level_id (only if no history at all)
    if ($htarget == 0 && $user_level) { $htarget = (float) $user_level['kpi_quarter_vnd']; $hlevel_name = ($user_level['level_name'] ?? '') . ' (hiện tại)'; }

    // Total invoiced in that quarter (by invoice_date), excluding the same excluded types
    [$qsm, $qem] = $quarter_months[$hq];
    $qfrom = sprintf('%04d-%02d-01', $hy, $qsm);
    $qto = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $hy, $qem)));
    $hinv = 0;
    foreach ($all_invoices as $iv) {
        $it = $iv['x_studio_invoice_type_1'] ?? '';
        if (in_array($it, $excluded_types)) continue;
        if (($iv['state'] ?? '') === 'cancel') continue;
        $ivd = $iv['invoice_date'] ?: $iv['date'] ?? '';
        if (!$ivd || $ivd < $qfrom || $ivd > $qto) continue;
        $av = abs((float) ($iv['amount_total_signed'] ?? 0));
        if ($av == 0) $av = (float) ($iv['amount_total'] ?? 0);
        $hinv += $av;
    }

    $manual_pct = $manual_kpi_map[$qk] ?? null;
    $computed = ($htarget > 0);
    if ($manual_pct !== null) {
        $hpct = $manual_pct;
    } elseif ($computed) {
        $hpct = $hinv / $htarget * 100;
    } else {
        $hpct = null;
    }

    if ($hpct === null) {
        $hadj = 0;
    } elseif ($hpct < 60) {
        $hadj = 0;
    } elseif ($hpct < 80) {
        $hadj = 0.7;
    } else {
        $hadj = 1.0;
    }

    $hist_kpi[$qk] = [
        'pct'        => $hpct,
        'adj'        => $hadj,
        'target'     => $htarget,
        'invoiced'   => $hinv,
        'computed'   => $computed,
        'manual_pct' => $manual_pct,
        'level_name' => $hlevel_name,
        'year'       => $hy,
        'quarter'    => $hq,
    ];
}

// ── Pre-compute KPI-adjusted Com1 for each collected invoice (per origin quarter) ──
$total_collected_com1 = 0;
$total_collected_com2 = 0;
$total_collected_ai_com = 0;
$yb_ebt_rec = 0;   // S_EBT recovered this quarter (profit, VND)
$yb_rev_rec = 0;   // revenue of EBT-known recovered invoices
foreach ($collected_details as $idx => $cd) {
    $ci = $cd['inv'];
    $cci_id = (int) $ci['id'];
    $cci_is_new = (stripos($ci['x_studio_client_type'] ?? '', 'new') !== false);
    $cci_map = $inv_pakd_map[$cci_id] ?? null;
    $cci_pakd_id = $cci_map ? (int) $cci_map['pakd_id'] : 0;
    $cci_manual_ebt = $cci_map && $cci_map['manual_ebt'] !== null ? (float) $cci_map['manual_ebt'] : null;
    $cci_ebt = null;
    if ($cci_manual_ebt !== null) {
        $cci_ebt = $cci_manual_ebt;
    } elseif ($cci_pakd_id && isset($pakd_map[$cci_pakd_id])) {
        $cpp = $pakd_map[$cci_pakd_id];
        $cci_ebt = $cpp['revenue'] > 0 ? ($cpp['gross_profit'] / $cpp['revenue'] * 100) : 0;
    }
    $cci_base_rate = ($cci_ebt !== null && $cci_ebt < 5) ? 0 : ($cci_is_new ? 0.01 : 0.005);
    $cci_lead = $cci_map && !empty($cci_map['lead_source']) ? $cci_map['lead_source'] : '';
    $cci_rate = $cci_base_rate + (($cci_lead === 'self' && $cci_is_new && $cci_base_rate > 0) ? MC_SELF_LEAD_BONUS : 0);
    $cci_hk = $cd['origin_key'] !== '' ? ($hist_kpi[$cd['origin_key']] ?? null) : null;
    $cci_adj = $cci_hk ? $cci_hk['adj'] : 0;
    $cci_net = $cd['amount_vnd'] * $cci_rate * $cci_adj;
    $collected_details[$idx]['com1_net'] = $cci_net;
    $total_collected_com1 += $cci_net;
    // Com2 (recovered): chỉ khi EBT >= 20%. Com2 = HV(VND) × tier % × origin-quarter KPI adj.
    // Tier % is auto-derived from the Revenue/Base ratio (Base = Invoice − HV).
    $cci_hv_vnd = mc_hv_to_vnd($cci_map, $hv_rates);
    $cci_tier = mc_auto_tier($cd['amount_vnd'], $cci_hv_vnd);
    if ($cci_tier > 0 && $cci_hv_vnd !== null && $cci_ebt !== null && $cci_ebt >= 20) $total_collected_com2 += $cci_hv_vnd * ($cci_tier / 100) * $cci_adj;
    // AI Com (recovered): AI Revenue(VND) × ai rate, gated on origin-quarter KPI >= 60% (binary)
    $cci_ai_addon = $cci_map && !empty($cci_map['ai_addon']) ? $cci_map['ai_addon'] : '';
    $cci_ai_rev_vnd = mc_ai_rev_to_vnd($cci_map, $hv_rates);
    $cci_ai_rate = mc_ai_rate($cci_ai_addon, $cci_ebt);
    $cci_ai_kpi_ok = ($cci_hk && $cci_hk['pct'] !== null && $cci_hk['pct'] >= 60);
    $cci_ai_net = ($cci_ai_addon !== '' && $cci_ai_rev_vnd !== null && $cci_ai_kpi_ok) ? $cci_ai_rev_vnd * $cci_ai_rate : 0;
    $collected_details[$idx]['ai_com_net'] = $cci_ai_net;
    $collected_details[$idx]['ai_addon'] = $cci_ai_addon;
    $collected_details[$idx]['ai_rev'] = $cci_map && isset($cci_map['ai_revenue']) && $cci_map['ai_revenue'] !== null ? (float) $cci_map['ai_revenue'] : null;
    $collected_details[$idx]['ai_rev_cur'] = $cci_map && !empty($cci_map['ai_revenue_currency']) ? $cci_map['ai_revenue_currency'] : 'VND';
    $collected_details[$idx]['ai_rev_vnd'] = $cci_ai_rev_vnd;
    $collected_details[$idx]['ai_kpi_ok'] = $cci_ai_kpi_ok;
    $total_collected_ai_com += $cci_ai_net;
    // Yearly Bonus (recovered): accumulate EBT (profit) + revenue; rate applied after the loop.
    if ($cci_ebt !== null) {
        $yb_ebt_rec += $cd['amount_vnd'] * $cci_ebt / 100;
        $yb_rev_rec += $cd['amount_vnd'];
    }
}

// ── Finalise Yearly Bonus estimate: rate from the quarter's average EBT %, × S_EBT ──
$yb_ebt_total = $yb_ebt_main + $yb_ebt_rec;
$yb_rev_total = $yb_rev_main + $yb_rev_rec;
$yb_avg_ebt = $yb_rev_total > 0 ? ($yb_ebt_total / $yb_rev_total * 100) : null;
$yb_rate = mc_yb_rate($yb_avg_ebt);
$total_yb = $yb_rate * $yb_ebt_main;
$total_collected_yb = $yb_rate * $yb_ebt_rec;

// ── License Bonus: 10% × EBT(License). Binary gate on quarter KPI ≥ 80%. ──
// License revenue is NOT part of $total_invoiced (so it never affects KPI).
$license_kpi_ok = ($kpi_pct >= MC_LICENSE_MIN_KPI);
$total_license_bonus = 0;
$license_details = [];
foreach ($license_invoices as $inv) {
    $inv_id = (int) $inv['id'];
    $amount = (float) ($inv['amount_total'] ?? 0);
    $currency = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
    $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));
    if ($amount_vnd == 0) $amount_vnd = $amount;

    // EBT from linked PAKD or manual entry (same mechanism as the main rows)
    $lmap = $inv_pakd_map[$inv_id] ?? null;
    $l_pakd_id = $lmap ? (int) $lmap['pakd_id'] : 0;
    $l_manual_ebt = $lmap && $lmap['manual_ebt'] !== null ? (float) $lmap['manual_ebt'] : null;
    $l_ebt = null;
    if ($l_manual_ebt !== null) {
        $l_ebt = $l_manual_ebt;
    } elseif ($l_pakd_id && isset($pakd_map[$l_pakd_id])) {
        $lp = $pakd_map[$l_pakd_id];
        $l_ebt = $lp['revenue'] > 0 ? ($lp['gross_profit'] / $lp['revenue'] * 100) : 0;
    }

    // Payment date
    $l_pay_date = null;
    if (!empty($inv['invoice_payments_widget'])) {
        $w = $inv['invoice_payments_widget'];
        if (is_string($w)) $w = json_decode($w, true);
        if (!empty($w['content'])) { $ds = array_column($w['content'], 'date'); if ($ds) $l_pay_date = max($ds); }
    }
    if (!$l_pay_date && ($inv['payment_state'] ?? '') === 'paid') $l_pay_date = $inv['write_date'] ?? null;
    $l_pay_ymd = $l_pay_date ? substr($l_pay_date, 0, 10) : '';
    $l_is_paid = ($inv['payment_state'] ?? '') === 'paid';
    $l_paid_in_quarter = $l_is_paid && $l_pay_ymd >= $date_from && $l_pay_ymd <= $date_to;

    $l_ebt_vnd = $l_ebt !== null ? $amount_vnd * $l_ebt / 100 : null;
    $l_bonus_pre = ($l_ebt !== null && $l_paid_in_quarter) ? $l_ebt_vnd * MC_LICENSE_RATE : 0;
    $l_bonus = $license_kpi_ok ? $l_bonus_pre : 0;
    $total_license_bonus += $l_bonus;

    $license_details[] = [
        'inv' => $inv,
        'amount' => $amount,
        'currency' => $currency,
        'amount_vnd' => $amount_vnd,
        'ebt_pct' => $l_ebt,
        'ebt_vnd' => $l_ebt_vnd,
        'is_paid' => $l_is_paid,
        'paid_in_quarter' => $l_paid_in_quarter,
        'payment_date' => $l_pay_ymd,
        'bonus_pre' => $l_bonus_pre,
        'bonus' => $l_bonus,
    ];
}

// Grand total commission for this quarter = current-quarter net + recovered (thu hồi)
$grand_com1 = $net_com1 + $total_collected_com1;
$grand_com2 = $net_com2 + $total_collected_com2;
$grand_ai_com = $total_ai_com + $total_collected_ai_com;
$grand_yb = $total_yb + $total_collected_yb;
$grand_total_com = $grand_com1 + $grand_com2 + $grand_ai_com + $total_so_com;

// Available years
$available_years = [$current_year, $current_year - 1, $current_year - 2];

// ── Helpers ──
function mc_fmt($n) { return number_format($n, 0, '.', ','); }
function mc_fmt_short($n) {
    if (abs($n) >= 1e9) return number_format($n / 1e9, 2) . ' tỷ';
    if (abs($n) >= 1e6) return number_format($n / 1e6, 1) . ' tr';
    return number_format($n, 0, '.', ',');
}
function mc_pct($n) { return number_format($n, 1) . '%'; }

// Group invoices by month
$grouped = [];
foreach ($invoice_details as $d) {
    $inv_date = $d['inv']['invoice_date'] ?: $d['inv']['date'] ?? '';
    $mk = $inv_date ? date('Y-m', strtotime($inv_date)) : 'unknown';
    $grouped[$mk][] = $d;
}
krsort($grouped);

$paid_count = count(array_filter($invoices, fn($i) => ($i['payment_state'] ?? '') === 'paid'));
$unpaid_count = count($invoices) - $paid_count;
$month_names_vn = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Com - Q<?= $selected_quarter ?> <?= $selected_year ?></title>
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
        .qt.cur { border-color:#93c5fd; }
        .qt-yb { border-color:#fcd34d; color:#b45309; background:#fffbeb; font-weight:600; }
        .qt-yb:hover { border-color:#f59e0b; color:#92400e; background:#fef3c7; }
        .qt-yb.active { background:#d97706; color:#fff; border-color:#d97706; }
        .q-label { font-size:12px; color:#94a3b8; margin-left:0.5rem; }

        /* KPI Section */
        .kpi-section { background:linear-gradient(135deg,#1e293b 0%,#334155 100%); border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1rem; color:#fff; }
        .kpi-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; }
        .kpi-title { font-size:14px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; }
        .kpi-level { font-size:12px; background:rgba(59,130,246,.3); color:#93c5fd; padding:3px 10px; border-radius:20px; }
        .kpi-main { display:flex; align-items:baseline; gap:0.75rem; margin-bottom:0.5rem; }
        .kpi-pct { font-size:36px; font-weight:800; }
        .kpi-pct-good { color:#4ade80; }
        .kpi-pct-warn { color:#fbbf24; }
        .kpi-pct-bad { color:#f87171; }
        .kpi-vals { font-size:13px; color:#cbd5e1; }
        .kpi-bar-bg { background:rgba(255,255,255,.15); border-radius:8px; height:10px; overflow:hidden; margin-bottom:0.5rem; }
        .kpi-bar { height:100%; border-radius:8px; transition:width .5s ease; }
        .kpi-bar-good { background:linear-gradient(90deg,#22c55e,#4ade80); }
        .kpi-bar-warn { background:linear-gradient(90deg,#eab308,#fbbf24); }
        .kpi-bar-bad { background:linear-gradient(90deg,#ef4444,#f87171); }
        .kpi-footer { display:flex; justify-content:space-between; font-size:12px; color:#94a3b8; }
        .kpi-adj-tag { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; }
        .adj-green { background:rgba(74,222,128,.2); color:#4ade80; }
        .adj-yellow { background:rgba(251,191,36,.2); color:#fbbf24; }
        .adj-red { background:rgba(248,113,113,.2); color:#f87171; }

        /* Commission cards */
        .com-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; margin-bottom:1rem; }
        .com-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.1rem; position:relative; overflow:hidden; }
        .com-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .com-card.c1::before { background:#3b82f6; }
        .com-card.c2::before { background:#8b5cf6; }
        .com-card.yb::before { background:#f59e0b; }
        .com-card.ai::before { background:#06b6d4; }
        .com-card.lic::before { background:#b45309; }
        .com-card.net::before { background:#22c55e; height:4px; }
        .com-card.net { background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%); border:1.5px solid #86efac; }
        .com-card .cc-label { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
        .com-card .cc-value { font-size:20px; font-weight:700; color:#0f172a; }
        .com-card .cc-sub { font-size:11px; color:#64748b; margin-top:4px; }
        .com-card .cc-tag { font-size:10px; padding:2px 6px; border-radius:4px; display:inline-block; margin-top:6px; }
        .tag-ok { background:#dcfce7; color:#16a34a; }
        .tag-na { background:#f1f5f9; color:#94a3b8; }
        .tag-warn { background:#fef3c7; color:#92400e; }

        /* KPI Salary card */
        .salary-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem; margin-bottom:1rem; }
        .sal-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem 1rem; }
        .sal-card .sl { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; }
        .sal-card .sv { font-size:16px; font-weight:700; color:#0f172a; margin-top:2px; }

        /* Controls */
        .ctrl { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem; flex-wrap:wrap; gap:0.5rem; }
        .s-box { display:flex; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:0.35rem 0.6rem; width:240px; }
        .s-box input { border:none; outline:none; width:100%; margin-left:0.4rem; font-size:13px; }
        .s-sel { padding:0.35rem 0.6rem; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; outline:none; color:#374151; }

        /* Table */
        .t-card { background:#fff; border:1px solid #c0c0c0; overflow:auto; max-height:calc(100vh - 420px); }
        table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:13px; color:#000; }
        th { background:#f8f9fa; color:#5f6368; font-weight:bold; text-align:left; padding:4px 8px; border:1px solid #e0e0e0; white-space:nowrap; height:30px; position:sticky; top:0; z-index:1; }
        td { padding:4px 8px; border:1px solid #e0e0e0; color:#202124; white-space:nowrap; height:25px; vertical-align:middle; }
        tr:hover td { background-color:#f1f3f4; }
        .amt { font-family:'Inconsolata',monospace; text-align:right; }
        .badge { padding:2px 6px; border-radius:4px; font-size:11px; border:1px solid transparent; }
        .b-paid { background:#e6f4ea; color:#137333; border-color:#ceead6; }
        .b-unpaid { background:#fce8e6; color:#c5221f; border-color:#fad2cf; }
        .b-inp { background:#e8f0fe; color:#1967d2; border-color:#d2e3fc; }
        .b-new { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
        .b-old { background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
        .mh td { background:#e8f0fe; font-weight:bold; color:#1a73e8; border-top:2px solid #aecbfa; border-bottom:1px solid #aecbfa; padding:6px 8px; }

        /* Market to Lead */
        .lead-select { padding:3px 5px; border:1px solid #e2e8f0; border-radius:5px; font-size:11px; background:#fff; cursor:pointer; outline:none; color:#374151; max-width:130px; }
        .lead-select:focus { border-color:#93c5fd; }
        .lead-bonus { color:#f59e0b; font-weight:700; }

        /* AI Add-on */
        .ai-select { padding:3px 5px; border:1px solid #e2e8f0; border-radius:5px; font-size:11px; background:#fff; cursor:pointer; outline:none; color:#374151; max-width:140px; }
        .ai-select:focus { border-color:#06b6d4; }
        .airev-input { width:78px; padding:2px 4px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; text-align:right; font-family:'Inconsolata',monospace; outline:none; }
        .airev-input:focus { border-color:#06b6d4; }
        .airev-cur { padding:2px 2px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; background:#fff; cursor:pointer; outline:none; }

        /* PAKD dropdown */
        .pakd-wrap { position:relative; min-width:160px; }
        .pakd-btn { display:flex; align-items:center; gap:4px; padding:3px 6px; border:1px solid #e2e8f0; border-radius:5px; background:#fff; cursor:pointer; font-size:11px; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; min-height:22px; }
        .pakd-btn:hover { border-color:#93c5fd; }
        .pakd-btn.has-val { border-color:#3b82f6; background:#eff6ff; color:#1d4ed8; font-weight:500; }
        .pakd-dd { display:none; position:absolute; top:100%; left:0; z-index:50; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.15); width:320px; max-height:260px; overflow:hidden; }
        .pakd-dd.open { display:block; }
        .pakd-dd input { width:100%; padding:8px 10px; border:none; border-bottom:1px solid #e5e7eb; font-size:12px; outline:none; box-sizing:border-box; }
        .pakd-dd ul { list-style:none; margin:0; padding:0; max-height:200px; overflow-y:auto; }
        .pakd-dd li { padding:6px 10px; cursor:pointer; font-size:12px; border-bottom:1px solid #f3f4f6; }
        .pakd-dd li:hover { background:#eff6ff; }
        .pakd-dd li.sel { background:#dbeafe; font-weight:600; }
        .pakd-dd li .p-name { color:#1f2937; }
        .pakd-dd li .p-ebt { font-size:10px; color:#6b7280; margin-left:4px; }
        .pakd-dd li.p-clear { color:#ef4444; font-style:italic; }
        .pakd-link-input { border-top:1px solid #e5e7eb; padding:6px 8px; display:flex; gap:4px; }
        .pakd-link-input input { flex:1; padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:11px; outline:none; }
        .pakd-link-input button { padding:4px 10px; background:#2563eb; color:#fff; border:none; border-radius:4px; font-size:11px; cursor:pointer; }
        .pakd-link-input button:hover { background:#1d4ed8; }
        .pakd-link-display { color:#1d4ed8; font-size:11px; text-decoration:none; overflow:hidden; text-overflow:ellipsis; max-width:160px; display:inline-block; vertical-align:middle; }
        .pakd-link-display:hover { text-decoration:underline; }
        .pakd-link-clear { color:#94a3b8; cursor:pointer; font-size:14px; margin-left:4px; vertical-align:middle; }
        .pakd-link-clear:hover { color:#ef4444; }
        .ebt-cell { text-align:center !important; width:60px; min-width:60px; max-width:60px; box-sizing:border-box; }
        .ebt-val { cursor:default; }
        .ebt-val.editable { cursor:pointer; }
        .ebt-edit { display:none; width:100%; padding:2px 3px; border:none; border-bottom:1px solid #3b82f6; background:transparent; font-size:11px; text-align:center; font-family:'Inconsolata',monospace; outline:none; box-sizing:border-box; }
        .ebt-cell:hover .ebt-edit.can-edit { display:inline-block; }
        .ebt-cell:hover .ebt-val.editable { display:none; }
        /* Tier (Com2) dropdown cell */
        .tier-cell { width:64px; min-width:64px; }
        .tier-select { width:100%; padding:2px 4px; border:1px solid #e2e8f0; border-radius:4px; background:#fff; font-size:11px; text-align:center; font-family:'Inter',sans-serif; color:#475569; cursor:pointer; outline:none; }
        .tier-select:focus { border-color:#3b82f6; }
        .tier-select:disabled { background:#f1f5f9; color:#cbd5e1; cursor:not-allowed; opacity:.7; }
        /* HV (Giá chênh) cell */
        .hv-cell { width:130px; min-width:130px; }
        .hv-wrap { display:flex; gap:2px; align-items:center; }
        .hv-input { width:78px; padding:2px 4px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; text-align:right; font-family:'Inconsolata',monospace; outline:none; }
        .hv-input:focus { border-color:#3b82f6; }
        .hv-input:disabled { background:#f1f5f9; color:#cbd5e1; cursor:not-allowed; }
        .hv-cur { padding:2px 2px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; background:#fff; cursor:pointer; outline:none; }
        .hv-cur:disabled { background:#f1f5f9; color:#cbd5e1; cursor:not-allowed; }
        .hv-vnd { font-size:10px; color:#94a3b8; text-align:right; margin-top:1px; }
        /* Per-quarter KPI cell (Thu hồi công nợ) */
        .kpi-q-cell { white-space:nowrap; }
        .kpi-q-badge { display:inline-block; font-size:10px; font-weight:600; cursor:pointer; }
        .kpi-q-badge.editable:hover { text-decoration:underline; }
        .kpi-q-edit { display:none; width:70px; padding:2px 3px; border:none; border-bottom:1px solid #3b82f6; background:transparent; font-size:11px; text-align:right; outline:none; box-sizing:border-box; }
        .kpi-q-edit.always { display:inline-block; border-bottom:1px dashed #f59e0b; }
        .kpi-q-cell:hover .kpi-q-edit.can-edit { display:inline-block; }
        .kpi-q-cell:hover .kpi-q-badge.editable { display:none; }

        /* Responsive */
        @media (max-width:768px) {
            .com-grid { grid-template-columns:1fr 1fr; }
            .kpi-pct { font-size:28px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'My Com'; include __DIR__ . '/../includes/topbar.php'; ?>

        <div class="mycom">
            <?php if ($error): ?>
                <div style="background:#fce8e6;color:#c5221f;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:13px;">
                    Error: <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- ─── Quarter Navigation ─── -->
            <div class="q-nav">
                <select class="year-sel" onchange="location.href='/my-com?year='+this.value+'&quarter=<?= $selected_quarter ?>'">
                    <?php foreach ($available_years as $yr): ?>
                        <option value="<?= $yr ?>" <?= $yr === $selected_year ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="q-tabs">
                    <?php for ($q = 1; $q <= 4; $q++):
                        $cls = 'qt';
                        if ($q === $selected_quarter) $cls .= ' active';
                        elseif ($q === $current_quarter && $selected_year === $current_year) $cls .= ' cur';
                        $href = '/my-com?year=' . $selected_year . '&quarter=' . $q
                            . ($search ? '&search=' . urlencode($search) : '')
                            . ($status_filter ? '&status=' . urlencode($status_filter) : '');
                    ?>
                        <a href="<?= $href ?>" class="<?= $cls ?>">Q<?= $q ?></a>
                    <?php endfor; ?>
                    <a href="/my-com/yearly-bonus?year=<?= $selected_year ?>" class="qt qt-yb" title="Yearly Bonus cả năm <?= $selected_year ?>">★ Yearly Bonus</a>
                </div>
                <span class="q-label">
                    <?= $month_names_vn[$q_start_month-1] ?> – <?= $month_names_vn[$q_end_month-1] ?> <?= $selected_year ?>
                </span>
            </div>

            <!-- ─── KPI Progress ─── -->
            <?php if ($user_level):
                $pct_cls = $kpi_pct >= 80 ? 'good' : ($kpi_pct >= 60 ? 'warn' : 'bad');
                $bar_w = min($kpi_pct, 100);
            ?>
            <div class="kpi-section">
                <div class="kpi-header">
                    <span class="kpi-title">KPI Progress - Q<?= $selected_quarter ?>/<?= $selected_year ?></span>
                    <span class="kpi-level"<?= $level_borrowed_from ? ' title="Quý này chưa set level — lấy theo quý gần nhất ' . htmlspecialchars($level_borrowed_from) . '"' : '' ?>><?= htmlspecialchars($user_level['level_name']) ?><?= $level_borrowed_from ? ' · theo ' . htmlspecialchars($level_borrowed_from) : '' ?></span>
                </div>
                <div class="kpi-main">
                    <span class="kpi-pct kpi-pct-<?= $pct_cls ?>"><?= mc_pct($kpi_pct) ?></span>
                    <span class="kpi-vals"><?= mc_fmt_short($total_invoiced) ?> / <?= mc_fmt_short($kpi_quarter_target) ?> VND</span>
                </div>
                <div class="kpi-bar-bg"><div class="kpi-bar kpi-bar-<?= $pct_cls ?>" style="width:<?= $bar_w ?>%"></div></div>
                <div class="kpi-footer">
                    <span>Invoiced: <?= count($invoices) ?> invoices (<?= $paid_count ?> paid, <?= $unpaid_count ?> unpaid)</span>
                    <span>
                        Com adj:
                        <span class="kpi-adj-tag adj-<?= $pct_cls ?>"><?= $kpi_adj_label ?></span>
                    </span>
                </div>
            </div>
            <?php else: ?>
                <div class="ebt-info">
                    <strong>Chưa được gán Sale Level.</strong> Liên hệ admin để thiết lập level và KPI target.
                </div>
            <?php endif; ?>

            <!-- ─── KPI Salary & Stats ─── -->
            <div class="salary-grid">
                <div class="sal-card">
                    <div class="sl">Lương KPI quý</div>
                    <div class="sv"><?= $kpi_salary_label ?></div>
                </div>
                <div class="sal-card">
                    <div class="sl">Invoiced (Quý)</div>
                    <div class="sv"><?= mc_fmt_short($total_invoiced) ?></div>
                </div>
                <div class="sal-card">
                    <div class="sl">Invoiced (Năm <?= $selected_year ?>)</div>
                    <div class="sv"><?= mc_fmt_short($yearly_invoiced) ?></div>
                </div>
                <div class="sal-card">
                    <div class="sl">KPI Yearly Target</div>
                    <div class="sv"><?= mc_fmt_short($kpi_yearly_target) ?></div>
                </div>
            </div>

            <!-- ─── Commission Estimate ─── -->
            <div class="com-grid">
                <!-- Com1 -->
                <div class="com-card c1">
                    <div class="cc-label">Com1 (Revenue)</div>
                    <div class="cc-value" id="ccCom1Gross"><?= mc_fmt_short($total_com1) ?></div>
                    <div class="cc-sub">New: 1% · Old: 0.5% · My Lead: +1%</div>
                    <div class="cc-sub">After KPI adj: <strong id="ccCom1Net"><?= mc_fmt_short($net_com1) ?></strong></div>
                    <span class="cc-tag tag-ok">Calculated</span>
                </div>

                <!-- Com2 -->
                <div class="com-card c2">
                    <div class="cc-label">Com2 (High Value)</div>
                    <div class="cc-value" style="color:#7c3aed;" id="ccCom2Gross"><?= $grand_com2 > 0 ? mc_fmt_short($grand_com2) : '–' ?></div>
                    <div class="cc-sub">HV × Tier% (tự tính 2/3/5) · EBT ≥ 20%</div>
                    <div class="cc-sub">Quý này: <strong id="ccCom2ThisQ"><?= mc_fmt_short($net_com2) ?></strong> · Thu hồi: <strong id="ccCom2Recovery"><?= mc_fmt_short($total_collected_com2) ?></strong></div>
                    <span class="cc-tag <?= $grand_com2 > 0 ? 'tag-ok' : 'tag-na' ?>" id="ccCom2Tag"><?= $grand_com2 > 0 ? 'Calculated' : 'Chưa đủ điều kiện' ?></span>
                </div>

                <!-- Yearly Bonus -->
                <div class="com-card yb">
                    <div class="cc-label">Yearly Bonus (est.)</div>
                    <div class="cc-value" style="color:#d97706;" id="ccYbGross"><?= mc_fmt_short($grand_yb) ?></div>
                    <div class="cc-sub">Tỉ lệ × S_EBT · %A_EBT ≥ 12.5%: 2% · ≥ 20%: 4% · <a href="/my-com/yearly-bonus?year=<?= $selected_year ?>" style="color:#d97706;font-weight:600;">cả năm →</a></div>
                    <div class="cc-sub">Quý này: <strong id="ccYbThisQ"><?= mc_fmt_short($total_yb) ?></strong> · Thu hồi: <strong id="ccYbRecovery"><?= mc_fmt_short($total_collected_yb) ?></strong></div>
                    <span class="cc-tag <?= $grand_yb > 0 ? 'tag-ok' : 'tag-na' ?>" id="ccYbTag"><?= $grand_yb > 0 ? 'Ước tính (est.)' : 'Tổng kết năm' ?></span>
                </div>

                <!-- AI Add-on -->
                <div class="com-card ai">
                    <div class="cc-label">AI Add-on</div>
                    <div class="cc-value" style="color:#0891b2;" id="ccAiGross"><?= mc_fmt_short($grand_ai_com) ?></div>
                    <div class="cc-sub">AIHive: 5%/2% · AI Solutions: 2% · KPI ≥ 60%</div>
                    <div class="cc-sub">Quý này: <strong id="ccAiThisQ"><?= mc_fmt_short($total_ai_com) ?></strong> · Thu hồi: <strong id="ccAiRecovery"><?= mc_fmt_short($total_collected_ai_com) ?></strong></div>
                    <span class="cc-tag <?= $grand_ai_com > 0 ? 'tag-ok' : 'tag-na' ?>" id="ccAiTag"><?= $grand_ai_com > 0 ? 'Calculated' : 'Cần tag AI Revenue' ?></span>
                </div>

                <!-- License Bonus -->
                <div class="com-card lic">
                    <div class="cc-label">License Bonus</div>
                    <div class="cc-value" style="color:#b45309;" id="ccLicenseGross"><?= $total_license_bonus > 0 ? mc_fmt_short($total_license_bonus) : '–' ?></div>
                    <div class="cc-sub">10% × EBT (HĐ License) · KPI quý ≥ 80%</div>
                    <div class="cc-sub"><?= count($license_details) ?> HĐ License · <?= $license_kpi_ok ? '<span style="color:#16a34a;">Đạt KPI ≥ 80%</span>' : '<span style="color:#dc2626;">Chưa đạt KPI ≥ 80%</span>' ?></div>
                    <span class="cc-tag <?= $total_license_bonus > 0 ? 'tag-ok' : 'tag-na' ?>" id="ccLicenseTag"><?= $total_license_bonus > 0 ? 'Calculated' : (empty($license_details) ? 'Không có HĐ License' : ($license_kpi_ok ? 'Cần EBT & thanh toán' : 'Chưa đạt KPI')) ?></span>
                </div>

                <!-- First PO Commission -->
                <div class="com-card" style="border-top-color:#7c3aed;">
                    <div class="cc-label">First PO Commission</div>
                    <div class="cc-value" style="color:#7c3aed;" id="ccFirstPoCom"><?= $total_so_com > 0 ? mc_fmt_short($total_so_com) : '–' ?></div>
                    <div class="cc-sub">Contract (VND) × 1/1000 · SO ≥ 1 tỷ</div>
                    <div class="cc-sub"><?= count($so_list) ?> SO trong quý · <strong id="ccFirstPoCount"><?= count(array_filter($so_list, fn($s) => ($so_first_po_flags[$s['id']] ?? false))) ?></strong> First PO</div>
                    <span class="cc-tag <?= $total_so_com > 0 ? 'tag-ok' : 'tag-na' ?>" id="ccFirstPoTag"><?= $total_so_com > 0 ? 'Calculated' : (empty($so_list) ? 'Không có SO' : 'Chọn First PO') ?></span>
                </div>

                <!-- Net Commission -->
                <div class="com-card net">
                    <div class="cc-label">Ước tính Commission Q<?= $selected_quarter ?></div>
                    <div class="cc-value" style="color:#16a34a;" id="ccGrandTotal"><?= mc_fmt_short($grand_total_com) ?></div>
                    <div class="cc-sub">Com1: <strong id="ccCom1Grand"><?= mc_fmt_short($grand_com1) ?></strong><span id="ccCom2Part"<?= $grand_com2 > 0 ? '' : ' style="display:none;"' ?>> · <span style="color:#7c3aed;">Com2: <strong id="ccCom2"><?= mc_fmt_short($grand_com2) ?></strong></span></span><span id="ccAiPart"<?= $grand_ai_com > 0 ? '' : ' style="display:none;"' ?>> · <span style="color:#0891b2;">AI: <strong id="ccAi"><?= mc_fmt_short($grand_ai_com) ?></strong></span></span><span id="ccFirstPoPart"<?= $total_so_com > 0 ? '' : ' style="display:none;"' ?>> · <span style="color:#7c3aed;">1st PO: <strong id="ccFirstPoGrandVal"><?= mc_fmt_short($total_so_com) ?></strong></span></span></div>
                    <div class="cc-sub">Quý này: <strong id="ccThisQ"><?= mc_fmt_short($net_com1 + $net_com2 + $total_ai_com) ?></strong> (× KPI <?= $kpi_adj * 100 ?>%)</div>
                    <div class="cc-sub" id="ccRecoveryPart" style="color:#16a34a;<?= ($total_collected_com1 + $total_collected_com2 + $total_collected_ai_com) > 0 ? '' : 'display:none;' ?>">+ Thu hồi công nợ: <strong id="ccRecovery"><?= mc_fmt_short($total_collected_com1 + $total_collected_com2 + $total_collected_ai_com) ?></strong> (KPI từng quý gốc)</div>
                    <div class="cc-sub" style="color:#94a3b8;">Chưa gồm YB</div>
                </div>
            </div>


            <!-- ─── Search / Filter ─── -->
            <form method="GET" class="ctrl">
                <input type="hidden" name="year" value="<?= $selected_year ?>">
                <input type="hidden" name="quarter" value="<?= $selected_quarter ?>">
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <div class="s-box">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input name="search" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="status" class="s-sel" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Posted</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    </select>
                    <?php if ($search || $status_filter): ?>
                        <a href="?year=<?= $selected_year ?>&quarter=<?= $selected_quarter ?>" style="font-size:12px;color:#64748b;">Clear</a>
                    <?php endif; ?>
                </div>
                <span style="font-size:12px;color:#94a3b8;"><?= count($invoices) ?> invoices</span>
            </form>

            <!-- ─── Invoice Table ─── -->
            <div class="t-card">
                <table>
                    <thead><tr>
                        <th>#</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Client</th>
                        <th>Invoice Date</th>
                        <th>Payment Date</th>
                        <th>Project</th>
                        <th>Market to Lead</th>
                        <th>PAKD</th>
                        <th style="text-align:center;">EBT</th>
                        <th style="text-align:right;">Amount</th>
                        <th style="text-align:right;">VND</th>
                        <th>Payment</th>
                        <th style="text-align:right;">Com1 Rate</th>
                        <th style="text-align:right;">Com1</th>
                        <th style="text-align:right;">High Value</th>
                        <th style="text-align:right;">Com2</th>
                        <th>AI Add-on</th>
                        <th style="text-align:right;">AI Revenue</th>
                        <th style="text-align:right;">AI Com</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($invoice_details)): ?>
                        <tr><td colspan="20" style="text-align:center;padding:3rem;color:#64748b;">No invoices for Q<?= $selected_quarter ?> <?= $selected_year ?>.</td></tr>
                    <?php else:
                        $n = 0;
                        foreach ($grouped as $mk => $month_items):
                            $ml = ($mk !== 'unknown') ? date('F Y', strtotime($mk . '-01')) : 'Unknown';
                            $m_com1 = array_sum(array_column($month_items, 'com1_amount'));
                            $m_com2 = array_sum(array_column($month_items, 'com2_gross')) * $kpi_adj;
                            $m_aicom = $ai_kpi_ok ? array_sum(array_column($month_items, 'ai_com_pre')) : 0;
                            $m_vnd = array_sum(array_column($month_items, 'amount_vnd'));
                    ?>
                        <tr class="mh">
                            <td colspan="11"><?= $ml ?> <span style="font-weight:normal;font-size:.88em;color:#5f6368;margin-left:.5rem;">(<?= count($month_items) ?> inv)</span></td>
                            <td class="amt" style="color:#1a73e8;"><?= mc_fmt_short($m_vnd) ?></td>
                            <td></td>
                            <td></td>
                            <td class="amt m-com1-sub" data-month="<?= htmlspecialchars($mk) ?>" style="color:#1a73e8;"><?= mc_fmt_short($m_com1) ?></td>
                            <td></td>
                            <td class="amt m-com2-sub" data-month="<?= htmlspecialchars($mk) ?>" style="color:#7c3aed;"><?= $m_com2 > 0 ? mc_fmt_short($m_com2) : '' ?></td>
                            <td></td>
                            <td></td>
                            <td class="amt m-aicom-sub" data-month="<?= htmlspecialchars($mk) ?>" style="color:#0891b2;"><?= $m_aicom > 0 ? mc_fmt_short($m_aicom) : '' ?></td>
                        </tr>
                        <?php foreach ($month_items as $d):
                            $n++;
                            $inv = $d['inv'];
                            $inv_id = (int) $inv['id'];
                            $inv_name = htmlspecialchars($inv['name'] ?: 'Draft');
                            $partner = is_array($inv['partner_id']) ? htmlspecialchars($inv['partner_id'][1]) : '—';
                            $inv_date = $inv['invoice_date'] ? date('d/m/Y', strtotime($inv['invoice_date'])) : '—';
                            $pay_dt = !empty($d['payment_date']) ? date('d/m/Y', strtotime($d['payment_date'])) : '—';
                            $proj = htmlspecialchars($inv['x_studio_project_code'] ?? '');
                            $amt_orig = (float)($inv['amount_total'] ?? 0);
                            $curr = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
                            $ps = $inv['payment_state'] ?? '';
                            $ps_cls = $ps === 'paid' ? 'b-paid' : ($ps === 'in_payment' ? 'b-inp' : 'b-unpaid');
                            $ps_label = $ps === 'paid' ? 'Paid' : ($ps === 'in_payment' ? 'In Payment' : ($ps === 'not_paid' ? 'Not Paid' : ucfirst($ps)));
                            $row_map = $inv_pakd_map[$inv_id] ?? null;
                            $sel_pakd_id = $row_map ? (int)$row_map['pakd_id'] : 0;
                            $sel_pakd_link = $row_map ? ($row_map['pakd_link'] ?? '') : '';
                            $sel_manual_ebt = $row_map && $row_map['manual_ebt'] !== null ? (float)$row_map['manual_ebt'] : null;
                            $sel_tier = $row_map && isset($row_map['com2_tier']) && $row_map['com2_tier'] !== null ? (float)$row_map['com2_tier'] : null;
                            $sel_hv = $row_map && isset($row_map['com2_hv']) && $row_map['com2_hv'] !== null ? (float)$row_map['com2_hv'] : null;
                            $sel_hv_cur = $row_map && !empty($row_map['com2_hv_currency']) ? $row_map['com2_hv_currency'] : 'VND';
                            $sel_pakd_name = $sel_pakd_id && isset($pakd_map[$sel_pakd_id]) ? htmlspecialchars($pakd_map[$sel_pakd_id]['name']) : '';
                            $is_link_mode = (!$sel_pakd_id && $sel_pakd_link !== '');
                            $sel_ebt = '';
                            if ($sel_manual_ebt !== null) {
                                $sel_ebt = $sel_manual_ebt;
                            } elseif ($sel_pakd_id && isset($pakd_map[$sel_pakd_id])) {
                                $sp = $pakd_map[$sel_pakd_id];
                                $sel_ebt = $sp['revenue'] > 0 ? round($sp['gross_profit'] / $sp['revenue'] * 100, 1) : 0;
                            }
                        ?>
                            <tr<?php if (!$d['is_paid']): ?> style="background:#f1f5f9;opacity:.8;"<?php elseif (!$d['paid_in_quarter']): ?> style="background:#fefce8;"<?php endif; ?>>
                                <td style="color:#94a3b8;font-size:11px;"><?= $n ?></td>
                                <td style="color:#1155cc;"><?= $inv_name ?></td>
                                <td><?= $partner ?></td>
                                <td><span class="badge <?= $d['client_type'] === 'New' ? 'b-new' : 'b-old' ?>"><?= $d['client_type'] ?></span></td>
                                <td><?= $inv_date ?></td>
                                <td style="<?= $pay_dt !== '—' ? 'color:#16a34a;font-weight:600;' : '' ?>"><?= $pay_dt ?></td>
                                <td><?= $proj ?></td>
                                <td class="lead-cell"><?= mc_lead_select($inv_id, $d['lead_source'], $lead_options, $d['client_type'] === 'New') ?></td>
                                <td>
                                    <div class="pakd-wrap" data-inv="<?= $inv_id ?>" data-pakd-id="<?= $sel_pakd_id ?>" data-link="<?= htmlspecialchars($sel_pakd_link) ?>">
                                        <?php if ($is_link_mode): ?>
                                            <a href="<?= htmlspecialchars($sel_pakd_link) ?>" target="_blank" class="pakd-link-display" title="<?= htmlspecialchars($sel_pakd_link) ?>"><?= htmlspecialchars(mb_strimwidth($sel_pakd_link, 0, 30, '…')) ?></a>
                                            <span class="pakd-link-clear" onclick="clearPakd(this)" title="Xóa">&times;</span>
                                        <?php else: ?>
                                            <div class="pakd-btn <?= $sel_pakd_id ? 'has-val' : '' ?>" onclick="togglePakd(this)"><?= $sel_pakd_name ?: '— Chọn PAKD —' ?></div>
                                        <?php endif; ?>
                                        <div class="pakd-dd">
                                            <input type="text" placeholder="Tìm PAKD..." oninput="filterPakd(this)">
                                            <ul></ul>
                                            <div class="pakd-link-input">
                                                <input type="text" placeholder="Hoặc dán link PAKD..." class="link-input" onkeydown="if(event.key==='Enter'){savePakdLink(this);event.preventDefault();}">
                                                <button onclick="savePakdLink(this.previousElementSibling)">OK</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ebt-cell" data-inv="<?= $inv_id ?>" data-mode="<?= $is_link_mode ? 'manual' : 'auto' ?>">
                                    <?php if ($is_link_mode && $sel_manual_ebt !== null): ?>
                                        <span class="ebt-val editable" style="color:<?= $sel_manual_ebt >= 20 ? '#16a34a' : ($sel_manual_ebt >= 5 ? '#2563eb' : '#dc2626') ?>;font-weight:600;"><?= $sel_manual_ebt ?>%</span>
                                        <input type="number" class="ebt-edit can-edit" value="<?= $sel_manual_ebt ?>" step="0.1" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)">
                                    <?php elseif ($is_link_mode): ?>
                                        <input type="number" class="ebt-edit can-edit" value="" step="0.1" placeholder="%" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)" style="display:inline-block;">
                                    <?php elseif ($sel_ebt !== ''): ?>
                                        <span class="ebt-val" style="color:<?= $sel_ebt >= 20 ? '#16a34a' : ($sel_ebt >= 5 ? '#2563eb' : '#dc2626') ?>;font-weight:600;"><?= $sel_ebt ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td class="amt"><?= mc_fmt($amt_orig) ?> <?= $curr ?></td>
                                <td class="amt"><?= mc_fmt($d['amount_vnd']) ?></td>
                                <td><span class="badge <?= $ps_cls ?>"><?= $ps_label ?></span></td>
                                <td class="amt com1-rate-cell"><?php
                                    if (!$d['is_paid']): ?><span style="color:#94a3b8;" title="Chưa thanh toán">—</span><?php
                                    elseif (!$d['paid_in_quarter']): ?><span style="color:#f59e0b;" title="Thanh toán ngoài quý này">—</span><?php
                                    elseif ($d['com1_rate'] == 0 && $d['ebt_pct'] !== null): ?><span style="color:#dc2626;" title="EBT < 5%">0%</span><?php
                                    elseif ($d['ebt_pct'] === null && empty($sel_pakd_id) && !$is_link_mode): ?><span style="color:#94a3b8;" title="Chưa chọn PAKD"><?= mc_rate_pct($d['com1_rate']) ?>%</span><?php
                                    else: ?><?= mc_rate_pct($d['com1_rate']) ?>%<?php if ($d['lead_source'] === 'self' && $d['client_type'] === 'New' && $d['com1_base_rate'] > 0): ?><span class="lead-bonus" title="+1% My Lead"> ★</span><?php endif; ?><?php endif; ?></td>
                                <td class="amt com1-cell" data-section="main" data-month="<?= htmlspecialchars($mk) ?>" data-amount="<?= $d['amount_vnd'] ?>" data-baserate="<?= $d['com1_base_rate'] ?>" data-isnew="<?= $d['client_type'] === 'New' ? 1 : 0 ?>" data-paidok="<?= ($d['is_paid'] && $d['paid_in_quarter']) ? 1 : 0 ?>" data-ebt="<?= $d['ebt_pct'] !== null ? $d['ebt_pct'] : '' ?>" style="color:<?= $d['com1_rate'] == 0 ? ($d['is_paid'] && $d['paid_in_quarter'] ? '#dc2626' : '#94a3b8') : '#1d4ed8' ?>;font-weight:600;"><?= $d['com1_rate'] == 0 && (!$d['is_paid'] || !$d['paid_in_quarter']) ? '—' : mc_fmt($d['com1_amount']) ?></td>
                                <?php $row_ebt_ok = !empty($d['com2_ebt_ok']); $row_elig = ($d['is_paid'] && $d['paid_in_quarter'] && $row_ebt_ok) ? 1 : 0; $row_com2 = ($d['com2_gross'] ?? 0) * $kpi_adj; $row_hv_vnd = $d['com2_hv_vnd'] ?? null; $row_tier = (float) ($d['com2_tier'] ?? 0); ?>
                                <td class="hv-cell" data-inv="<?= $inv_id ?>">
                                    <div class="hv-wrap"<?= $row_ebt_ok ? '' : ' title="Com2 yêu cầu EBT ≥ 20%"' ?>>
                                        <input type="number" class="hv-input" value="<?= $sel_hv !== null ? $sel_hv : '' ?>" step="any" placeholder="HV" onchange="saveHv(this)" onblur="saveHv(this)"<?= $row_ebt_ok ? '' : ' disabled' ?>>
                                        <select class="hv-cur" onchange="saveHv(this)"<?= $row_ebt_ok ? '' : ' disabled' ?>>
                                            <?php foreach ($hv_currencies as $cur_opt): ?>
                                            <option value="<?= $cur_opt ?>"<?= $sel_hv_cur === $cur_opt ? ' selected' : '' ?>><?= $hv_symbols[$cur_opt] . ' ' . $cur_opt ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if ($sel_hv !== null && $sel_hv_cur !== 'VND'): ?><div class="hv-vnd" title="Quy đổi VND"><?= mc_fmt_short($row_hv_vnd) ?></div><?php endif; ?>
                                </td>
                                <td class="amt com2-cell" data-section="main" data-month="<?= htmlspecialchars($mk) ?>" data-amount="<?= $d['amount_vnd'] ?>" data-base="<?= $row_hv_vnd !== null ? $row_hv_vnd : 0 ?>" data-adj="<?= $kpi_adj ?>" data-paidok="<?= ($d['is_paid'] && $d['paid_in_quarter']) ? 1 : 0 ?>" data-ebtok="<?= $row_ebt_ok ? 1 : 0 ?>" style="color:<?= $row_com2 > 0 ? '#7c3aed' : '#94a3b8' ?>;font-weight:600;" title="<?= !$row_ebt_ok ? 'Com2 yêu cầu EBT ≥ 20%' : ($row_tier > 0 && $row_hv_vnd !== null ? 'HV ' . mc_fmt($row_hv_vnd) . ' × Tier ' . mc_rate_pct($row_tier/100) . '% (auto) × KPI ' . ($kpi_adj * 100) . '%' : 'Nhập HV (Tier tự tính theo ratio Revenue/Base)') ?>"><?= (!$row_ebt_ok || $row_tier <= 0 || $row_hv_vnd === null || !$row_elig) ? '—' : mc_fmt($row_com2) . mc_com2_meta($row_tier, $d['amount_vnd'], $row_hv_vnd) ?></td>
                                <?php $ai_base_ok = ($ai_kpi_ok && $d['ai_paid_ok']); ?>
                                <td class="ai-cell"><?= mc_ai_select($inv_id, $d['ai_addon'], $ai_options, $ai_base_ok, $d['ebt_pct']) ?></td>
                                <?= mc_ai_rev_cell($inv_id, $d['ai_rev'], $d['ai_rev_cur'], $d['ai_rev_vnd'], $hv_currencies, $hv_symbols, $ai_base_ok, $d['ebt_pct']) ?>
                                <?php $row_ai_com = $ai_kpi_ok ? $d['ai_com_pre'] : 0; ?>
                                <td class="amt aicom-cell" data-section="main" data-month="<?= htmlspecialchars($mk) ?>" data-rev="<?= $d['ai_rev_vnd'] !== null ? $d['ai_rev_vnd'] : 0 ?>" data-ebt="<?= $d['ebt_pct'] !== null ? $d['ebt_pct'] : '' ?>" data-paidok="<?= $d['ai_paid_ok'] ? 1 : 0 ?>" data-kpiok="<?= $ai_kpi_ok ? 1 : 0 ?>" style="color:<?= $row_ai_com > 0 ? '#0891b2' : '#94a3b8' ?>;font-weight:600;" title="<?= $d['ai_addon'] === '' ? 'Chưa chọn AI Add-on' : (!$ai_kpi_ok ? 'AI Com yêu cầu KPI ≥ 60%' : ($d['ai_rev_vnd'] !== null ? 'AI Rev ' . mc_fmt($d['ai_rev_vnd']) . ' × ' . mc_rate_pct($d['ai_rate']) . '%' : 'Nhập AI Revenue')) ?>"><?= $row_ai_com > 0 ? mc_fmt($row_ai_com) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; endif; ?>

                    <!-- ── Thu hồi công nợ (paid this quarter, invoiced earlier) ── -->
                    <?php if (!empty($collected_details)):
                        $col_com1_total = 0;
                        $col_com2_total = 0;
                        $col_ai_com_total = 0;
                        $cn = 0;
                    ?>
                        <tr class="mh">
                            <td colspan="11" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;">
                                Thu hồi công nợ Q<?= $selected_quarter ?>/<?= $selected_year ?>
                                <span style="font-weight:normal;font-size:.88em;color:#15803d;margin-left:.5rem;">
                                    (<?= count($collected_details) ?> inv · tạo từ quý trước, thanh toán trong quý này)
                                </span>
                            </td>
                            <td class="amt" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;"><?= mc_fmt_short($total_collected_vnd) ?></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                        </tr>
                        <?php foreach ($collected_details as $cd):
                            $cn++;
                            $ci = $cd['inv'];
                            $ci_id = (int) $ci['id'];
                            $ci_name = htmlspecialchars($ci['name'] ?: 'Draft');
                            $ci_partner = is_array($ci['partner_id']) ? htmlspecialchars($ci['partner_id'][1]) : '—';
                            $ci_date = $ci['invoice_date'] ? date('d/m/Y', strtotime($ci['invoice_date'])) : '—';
                            $ci_pay = !empty($cd['payment_date']) ? date('d/m/Y', strtotime($cd['payment_date'])) : '—';
                            $ci_proj = htmlspecialchars($ci['x_studio_project_code'] ?? '');
                            $ci_amt = (float)($ci['amount_total'] ?? 0);
                            $ci_curr = is_array($ci['currency_id']) ? $ci['currency_id'][1] : 'VND';
                            $ci_ps = $ci['payment_state'] ?? '';
                            $ci_cls = $ci_ps === 'paid' ? 'b-paid' : ($ci_ps === 'in_payment' ? 'b-inp' : 'b-unpaid');
                            $ci_label = $ci_ps === 'paid' ? 'Paid' : ($ci_ps === 'in_payment' ? 'In Payment' : ucfirst($ci_ps));
                            $ci_client_raw = $ci['x_studio_client_type'] ?? '';
                            $ci_is_new = (stripos($ci_client_raw, 'new') !== false);
                            $ci_row_map = $inv_pakd_map[$ci_id] ?? null;
                            $ci_sel_pakd_id = $ci_row_map ? (int)$ci_row_map['pakd_id'] : 0;
                            $ci_sel_link = $ci_row_map ? ($ci_row_map['pakd_link'] ?? '') : '';
                            $ci_sel_manual_ebt = $ci_row_map && $ci_row_map['manual_ebt'] !== null ? (float)$ci_row_map['manual_ebt'] : null;
                            $ci_sel_tier = $ci_row_map && isset($ci_row_map['com2_tier']) && $ci_row_map['com2_tier'] !== null ? (float)$ci_row_map['com2_tier'] : null;
                            $ci_sel_hv = $ci_row_map && isset($ci_row_map['com2_hv']) && $ci_row_map['com2_hv'] !== null ? (float)$ci_row_map['com2_hv'] : null;
                            $ci_sel_hv_cur = $ci_row_map && !empty($ci_row_map['com2_hv_currency']) ? $ci_row_map['com2_hv_currency'] : 'VND';
                            $ci_hv_vnd = mc_hv_to_vnd($ci_row_map, $hv_rates);
                            $ci_is_link = (!$ci_sel_pakd_id && $ci_sel_link !== '');
                            $ci_linked_ebt = null;
                            if ($ci_sel_manual_ebt !== null) {
                                $ci_linked_ebt = $ci_sel_manual_ebt;
                            } elseif ($ci_sel_pakd_id && isset($pakd_map[$ci_sel_pakd_id])) {
                                $clp = $pakd_map[$ci_sel_pakd_id];
                                $ci_linked_ebt = $clp['revenue'] > 0 ? ($clp['gross_profit'] / $clp['revenue'] * 100) : 0;
                            }
                            if ($ci_linked_ebt !== null && $ci_linked_ebt < 5) {
                                $ci_com1_base_rate = 0;
                            } else {
                                $ci_com1_base_rate = $ci_is_new ? 0.01 : 0.005;
                            }
                            $ci_lead_source = $ci_row_map && !empty($ci_row_map['lead_source']) ? $ci_row_map['lead_source'] : '';
                            $ci_lead_bonus = ($ci_lead_source === 'self' && $ci_is_new && $ci_com1_base_rate > 0) ? MC_SELF_LEAD_BONUS : 0;
                            $ci_com1_rate = $ci_com1_base_rate + $ci_lead_bonus;
                            $ci_com1_gross = $cd['amount_vnd'] * $ci_com1_rate;
                            // KPI adjustment from the quarter this invoice was INVOICED in (not current quarter)
                            $ci_hk = $cd['origin_key'] !== '' ? ($hist_kpi[$cd['origin_key']] ?? null) : null;
                            $ci_kpi_adj = $ci_hk ? $ci_hk['adj'] : 0;
                            $ci_kpi_pct = $ci_hk ? $ci_hk['pct'] : null;       // null = chưa tính được
                            $ci_kpi_computed = $ci_hk ? $ci_hk['computed'] : false;
                            $ci_kpi_manual = $ci_hk ? ($ci_hk['manual_pct'] !== null) : false;
                            $ci_kpi_level = $ci_hk ? ($ci_hk['level_name'] ?? '') : '';
                            $ci_kpi_target = $ci_hk ? ($ci_hk['target'] ?? 0) : 0;
                            $ci_com1 = $cd['com1_net'] ?? ($ci_com1_gross * $ci_kpi_adj);
                            $col_com1_total += $ci_com1;
                            $ci_sel_pakd_name = $ci_sel_pakd_id && isset($pakd_map[$ci_sel_pakd_id]) ? htmlspecialchars($pakd_map[$ci_sel_pakd_id]['name']) : '';
                            $ci_sel_ebt = '';
                            if ($ci_sel_manual_ebt !== null) {
                                $ci_sel_ebt = $ci_sel_manual_ebt;
                            } elseif ($ci_sel_pakd_id && isset($pakd_map[$ci_sel_pakd_id])) {
                                $csp = $pakd_map[$ci_sel_pakd_id];
                                $ci_sel_ebt = $csp['revenue'] > 0 ? round($csp['gross_profit'] / $csp['revenue'] * 100, 1) : 0;
                            }
                        ?>
                            <tr>
                                <td style="color:#94a3b8;font-size:11px;"><?= $cn ?></td>
                                <td style="color:#1155cc;"><?= $ci_name ?></td>
                                <td><?= $ci_partner ?></td>
                                <td><span class="badge <?= $ci_is_new ? 'b-new' : 'b-old' ?>"><?= $ci_is_new ? 'New' : 'Old' ?></span></td>
                                <td><?= $ci_date ?></td>
                                <td style="color:#16a34a;font-weight:600;"><?= $ci_pay ?></td>
                                <td><?= $ci_proj ?></td>
                                <td class="lead-cell"><?= mc_lead_select($ci_id, $ci_lead_source, $lead_options, $ci_is_new) ?></td>
                                <?php $ci_ai_addon = $cd['ai_addon'] ?? ''; $ci_ai_rev = $cd['ai_rev'] ?? null; $ci_ai_rev_cur = $cd['ai_rev_cur'] ?? 'VND'; $ci_ai_rev_vnd = $cd['ai_rev_vnd'] ?? null; $ci_ai_kpi_ok = !empty($cd['ai_kpi_ok']); ?>
                                <td>
                                    <div class="pakd-wrap" data-inv="<?= $ci_id ?>" data-pakd-id="<?= $ci_sel_pakd_id ?>" data-link="<?= htmlspecialchars($ci_sel_link) ?>">
                                        <?php if ($ci_is_link): ?>
                                            <a href="<?= htmlspecialchars($ci_sel_link) ?>" target="_blank" class="pakd-link-display" title="<?= htmlspecialchars($ci_sel_link) ?>"><?= htmlspecialchars(mb_strimwidth($ci_sel_link, 0, 30, '…')) ?></a>
                                            <span class="pakd-link-clear" onclick="clearPakd(this)" title="Xóa">&times;</span>
                                        <?php else: ?>
                                            <div class="pakd-btn <?= $ci_sel_pakd_id ? 'has-val' : '' ?>" onclick="togglePakd(this)"><?= $ci_sel_pakd_name ?: '— Chọn PAKD —' ?></div>
                                        <?php endif; ?>
                                        <div class="pakd-dd">
                                            <input type="text" placeholder="Tìm PAKD..." oninput="filterPakd(this)">
                                            <ul></ul>
                                            <div class="pakd-link-input">
                                                <input type="text" placeholder="Hoặc dán link PAKD..." class="link-input" onkeydown="if(event.key==='Enter'){savePakdLink(this);event.preventDefault();}">
                                                <button onclick="savePakdLink(this.previousElementSibling)">OK</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="ebt-cell" data-inv="<?= $ci_id ?>" data-mode="<?= $ci_is_link ? 'manual' : 'auto' ?>">
                                    <?php if ($ci_is_link && $ci_sel_manual_ebt !== null): ?>
                                        <span class="ebt-val editable" style="color:<?= $ci_sel_manual_ebt >= 20 ? '#16a34a' : ($ci_sel_manual_ebt >= 5 ? '#2563eb' : '#dc2626') ?>;font-weight:600;"><?= $ci_sel_manual_ebt ?>%</span>
                                        <input type="number" class="ebt-edit can-edit" value="<?= $ci_sel_manual_ebt ?>" step="0.1" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)">
                                    <?php elseif ($ci_is_link): ?>
                                        <input type="number" class="ebt-edit can-edit" value="" step="0.1" placeholder="%" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)" style="display:inline-block;">
                                    <?php elseif ($ci_sel_ebt !== ''): ?>
                                        <span class="ebt-val" style="color:<?= $ci_sel_ebt >= 20 ? '#16a34a' : ($ci_sel_ebt >= 5 ? '#2563eb' : '#dc2626') ?>;font-weight:600;"><?= $ci_sel_ebt ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td class="amt"><?= mc_fmt($ci_amt) ?> <?= $ci_curr ?></td>
                                <td class="amt"><?= mc_fmt($cd['amount_vnd']) ?></td>
                                <td><span class="badge <?= $ci_cls ?>"><?= $ci_label ?></span></td>
                                <td class="amt"><?php if ($ci_com1_rate == 0 && $ci_linked_ebt !== null): ?><span style="color:#dc2626;" title="EBT < 5% → không tính Com">0%</span><?php elseif ($ci_linked_ebt === null && !$ci_sel_pakd_id && !$ci_is_link): ?><span style="color:#94a3b8;"><?= mc_rate_pct($ci_com1_rate) ?>%</span><?php else: ?><?= mc_rate_pct($ci_com1_rate) ?>%<?php if ($ci_lead_source === 'self' && $ci_is_new && $ci_com1_base_rate > 0): ?><span class="lead-bonus" title="+1% My Lead"> ★</span><?php endif; ?><?php endif; ?></td>
                                <td class="amt kpi-q-cell com1-cell-rec" data-year="<?= $cd['origin_year'] ?>" data-quarter="<?= $cd['origin_quarter'] ?>" data-amount="<?= $cd['amount_vnd'] ?>" data-baserate="<?= $ci_com1_base_rate ?>" data-isnew="<?= $ci_is_new ? 1 : 0 ?>" data-adj="<?= $ci_kpi_adj ?>" data-ebt="<?= $ci_linked_ebt !== null ? $ci_linked_ebt : '' ?>">
                                    <div class="com1-rec-val" style="font-weight:600;color:<?= $ci_com1 == 0 ? '#94a3b8' : '#1d4ed8' ?>;"><?= mc_fmt($ci_com1) ?></div>
                                    <?php if ($cd['origin_key'] !== ''):
                                        $kpi_color = $ci_kpi_adj == 0 ? '#dc2626' : ($ci_kpi_adj < 1 ? '#f59e0b' : '#16a34a');
                                    ?>
                                        <?php if ($ci_kpi_pct !== null): ?>
                                            <span class="kpi-q-badge editable" style="color:<?= $kpi_color ?>;" title="Q<?= $cd['origin_quarter'] ?>/<?= $cd['origin_year'] ?><?= $ci_kpi_level ? ' · Level: ' . $ci_kpi_level : '' ?><?= $ci_kpi_target > 0 ? ' · Target: ' . mc_fmt_short($ci_kpi_target) : '' ?> · KPI <?= round($ci_kpi_pct, 1) ?>% → Com ×<?= $ci_kpi_adj ?><?= $ci_kpi_manual ? ' (nhập tay)' : '' ?>">Q<?= $cd['origin_quarter'] ?>/<?= substr($cd['origin_year'], 2) ?>: <?= round($ci_kpi_pct, 1) ?>%<?= $ci_kpi_manual ? ' ✎' : '' ?></span>
                                            <input type="number" class="kpi-q-edit can-edit" value="<?= round($ci_kpi_pct, 1) ?>" step="0.1" onchange="saveQuarterKpi(this)" onblur="saveQuarterKpi(this)">
                                        <?php else: ?>
                                            <input type="number" class="kpi-q-edit always" value="" step="0.1" placeholder="KPI Q<?= $cd['origin_quarter'] ?> %" title="Quý Q<?= $cd['origin_quarter'] ?>/<?= $cd['origin_year'] ?> chưa có số liệu KPI — nhập tay" onchange="saveQuarterKpi(this)" onblur="saveQuarterKpi(this)">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php $ci_ebt_ok = ($ci_linked_ebt !== null && $ci_linked_ebt >= 20); ?>
                                <td class="hv-cell" data-inv="<?= $ci_id ?>">
                                    <div class="hv-wrap"<?= $ci_ebt_ok ? '' : ' title="Com2 yêu cầu EBT ≥ 20%"' ?>>
                                        <input type="number" class="hv-input" value="<?= $ci_sel_hv !== null ? $ci_sel_hv : '' ?>" step="any" placeholder="HV" onchange="saveHv(this)" onblur="saveHv(this)"<?= $ci_ebt_ok ? '' : ' disabled' ?>>
                                        <select class="hv-cur" onchange="saveHv(this)"<?= $ci_ebt_ok ? '' : ' disabled' ?>>
                                            <?php foreach ($hv_currencies as $cur_opt): ?>
                                            <option value="<?= $cur_opt ?>"<?= $ci_sel_hv_cur === $cur_opt ? ' selected' : '' ?>><?= $hv_symbols[$cur_opt] . ' ' . $cur_opt ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if ($ci_sel_hv !== null && $ci_sel_hv_cur !== 'VND'): ?><div class="hv-vnd" title="Quy đổi VND"><?= mc_fmt_short($ci_hv_vnd) ?></div><?php endif; ?>
                                </td>
                                <?php $ci_tier = mc_auto_tier($cd['amount_vnd'], $ci_hv_vnd); $ci_com2 = ($ci_tier > 0 && $ci_hv_vnd !== null && $ci_ebt_ok) ? $ci_hv_vnd * ($ci_tier / 100) * $ci_kpi_adj : 0; $col_com2_total += $ci_com2; ?>
                                <td class="amt com2-cell" data-section="recovery" data-amount="<?= $cd['amount_vnd'] ?>" data-base="<?= $ci_hv_vnd !== null ? $ci_hv_vnd : 0 ?>" data-adj="<?= $ci_kpi_adj ?>" data-paidok="1" data-ebtok="<?= $ci_ebt_ok ? 1 : 0 ?>" style="color:<?= $ci_com2 > 0 ? '#7c3aed' : '#94a3b8' ?>;font-weight:600;" title="<?= !$ci_ebt_ok ? 'Com2 yêu cầu EBT ≥ 20%' : ($ci_tier > 0 && $ci_hv_vnd !== null ? 'HV ' . mc_fmt($ci_hv_vnd) . ' × Tier ' . mc_rate_pct($ci_tier/100) . '% (auto) × KPI Q' . $cd['origin_quarter'] . '/' . $cd['origin_year'] . ' (×' . $ci_kpi_adj . ')' : 'Nhập HV (Tier tự tính theo ratio Revenue/Base)') ?>"><?= (!$ci_ebt_ok || $ci_tier <= 0 || $ci_hv_vnd === null) ? '—' : mc_fmt($ci_com2) . mc_com2_meta($ci_tier, $cd['amount_vnd'], $ci_hv_vnd) ?></td>
                                <?php $ci_ai_base_ok = $ci_ai_kpi_ok; // recovery rows are always paid (Collection = 1) ?>
                                <td class="ai-cell"><?= mc_ai_select($ci_id, $ci_ai_addon, $ai_options, $ci_ai_base_ok, $ci_linked_ebt) ?></td>
                                <?= mc_ai_rev_cell($ci_id, $ci_ai_rev, $ci_ai_rev_cur, $ci_ai_rev_vnd, $hv_currencies, $hv_symbols, $ci_ai_base_ok, $ci_linked_ebt) ?>
                                <?php $ci_ai_rate = mc_ai_rate($ci_ai_addon, $ci_linked_ebt); $ci_ai_com = $cd['ai_com_net'] ?? 0; $col_ai_com_total += $ci_ai_com; ?>
                                <td class="amt aicom-cell" data-section="recovery" data-rev="<?= $ci_ai_rev_vnd !== null ? $ci_ai_rev_vnd : 0 ?>" data-ebt="<?= $ci_linked_ebt !== null ? $ci_linked_ebt : '' ?>" data-paidok="1" data-kpiok="<?= $ci_ai_kpi_ok ? 1 : 0 ?>" style="color:<?= $ci_ai_com > 0 ? '#0891b2' : '#94a3b8' ?>;font-weight:600;" title="<?= $ci_ai_addon === '' ? 'Chưa chọn AI Add-on' : (!$ci_ai_kpi_ok ? 'AI Com yêu cầu KPI ≥ 60%' : ($ci_ai_rev_vnd !== null ? 'AI Rev ' . mc_fmt($ci_ai_rev_vnd) . ' × ' . mc_rate_pct($ci_ai_rate) . '%' : 'Nhập AI Revenue')) ?>"><?= $ci_ai_com > 0 ? mc_fmt($ci_ai_com) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="mh">
                            <td colspan="14" style="background:#dcfce7;color:#15803d;border-color:#bbf7d0;text-align:right;font-weight:600;">
                                Tổng Com1 thu hồi (đã áp KPI từng quý gốc)
                            </td>
                            <td class="amt" id="recCom1Total" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;font-weight:700;"><?= mc_fmt($col_com1_total) ?></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td class="amt" id="recCom2Total" style="background:#dcfce7;color:#7c3aed;border-color:#bbf7d0;font-weight:700;"><?= $col_com2_total > 0 ? mc_fmt($col_com2_total) : '' ?></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td style="background:#dcfce7;border-color:#bbf7d0;"></td>
                            <td class="amt" id="recAiComTotal" style="background:#dcfce7;color:#0891b2;border-color:#bbf7d0;font-weight:700;"><?= $col_ai_com_total > 0 ? mc_fmt($col_ai_com_total) : '' ?></td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <!-- ─── License Bonus (HĐ type License) ─── -->
            <div style="margin-top:1.75rem;">
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                    <h3 style="margin:0;font-size:15px;color:#b45309;font-weight:700;">License Bonus</h3>
                    <span style="font-size:12px;color:#94a3b8;">HĐ type <strong>License</strong> · Bonus = 10% × EBT · KPI quý ≥ 80% · không tính vào doanh thu KPI</span>
                </div>
                <div class="table-wrap" style="border:1px solid #fde68a;border-radius:10px;overflow:auto;">
                <table>
                    <thead><tr>
                        <th>#</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Invoice Date</th>
                        <th>Payment Date</th>
                        <th>PAKD</th>
                        <th style="text-align:center;">EBT</th>
                        <th style="text-align:right;">Amount</th>
                        <th style="text-align:right;">VND</th>
                        <th>Payment</th>
                        <th style="text-align:right;">EBT (VND)</th>
                        <th style="text-align:right;">License Bonus</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($license_details)): ?>
                        <tr><td colspan="12" style="text-align:center;padding:2.5rem;color:#64748b;">Không có HĐ License nào trong Q<?= $selected_quarter ?> <?= $selected_year ?>.</td></tr>
                    <?php else:
                        $ln = 0;
                        foreach ($license_details as $d):
                            $ln++;
                            $inv = $d['inv'];
                            $inv_id = (int) $inv['id'];
                            $inv_name = htmlspecialchars($inv['name'] ?: 'Draft');
                            $partner = is_array($inv['partner_id']) ? htmlspecialchars($inv['partner_id'][1]) : '—';
                            $inv_date = $inv['invoice_date'] ? date('d/m/Y', strtotime($inv['invoice_date'])) : '—';
                            $pay_dt = !empty($d['payment_date']) ? date('d/m/Y', strtotime($d['payment_date'])) : '—';
                            $amt_orig = $d['amount']; $curr = $d['currency'];
                            $ps = $inv['payment_state'] ?? '';
                            $ps_cls = $ps === 'paid' ? 'b-paid' : ($ps === 'in_payment' ? 'b-inp' : 'b-unpaid');
                            $ps_label = $ps === 'paid' ? 'Paid' : ($ps === 'in_payment' ? 'In Payment' : ($ps === 'not_paid' ? 'Not Paid' : ucfirst($ps)));
                            $row_map = $inv_pakd_map[$inv_id] ?? null;
                            $sel_pakd_id = $row_map ? (int)$row_map['pakd_id'] : 0;
                            $sel_pakd_link = $row_map ? ($row_map['pakd_link'] ?? '') : '';
                            $sel_manual_ebt = $row_map && $row_map['manual_ebt'] !== null ? (float)$row_map['manual_ebt'] : null;
                            $sel_pakd_name = $sel_pakd_id && isset($pakd_map[$sel_pakd_id]) ? htmlspecialchars($pakd_map[$sel_pakd_id]['name']) : '';
                            $is_link_mode = (!$sel_pakd_id && $sel_pakd_link !== '');
                            $sel_ebt = '';
                            if ($sel_manual_ebt !== null) { $sel_ebt = $sel_manual_ebt; }
                            elseif ($sel_pakd_id && isset($pakd_map[$sel_pakd_id])) {
                                $sp = $pakd_map[$sel_pakd_id];
                                $sel_ebt = $sp['revenue'] > 0 ? round($sp['gross_profit'] / $sp['revenue'] * 100, 1) : 0;
                            }
                            $l_show = ($d['bonus'] > 0);
                    ?>
                        <tr<?php if (!$d['is_paid']): ?> style="background:#f1f5f9;opacity:.8;"<?php elseif (!$d['paid_in_quarter']): ?> style="background:#fefce8;"<?php endif; ?>>
                            <td style="color:#94a3b8;font-size:11px;"><?= $ln ?></td>
                            <td style="color:#1155cc;"><?= $inv_name ?></td>
                            <td><?= $partner ?></td>
                            <td><?= $inv_date ?></td>
                            <td style="<?= $pay_dt !== '—' ? 'color:#16a34a;font-weight:600;' : '' ?>"><?= $pay_dt ?></td>
                            <td>
                                <div class="pakd-wrap" data-inv="<?= $inv_id ?>" data-pakd-id="<?= $sel_pakd_id ?>" data-link="<?= htmlspecialchars($sel_pakd_link) ?>">
                                    <?php if ($is_link_mode): ?>
                                        <a href="<?= htmlspecialchars($sel_pakd_link) ?>" target="_blank" class="pakd-link-display" title="<?= htmlspecialchars($sel_pakd_link) ?>"><?= htmlspecialchars(mb_strimwidth($sel_pakd_link, 0, 30, '…')) ?></a>
                                        <span class="pakd-link-clear" onclick="clearPakd(this)" title="Xóa">&times;</span>
                                    <?php else: ?>
                                        <div class="pakd-btn <?= $sel_pakd_id ? 'has-val' : '' ?>" onclick="togglePakd(this)"><?= $sel_pakd_name ?: '— Chọn PAKD —' ?></div>
                                    <?php endif; ?>
                                    <div class="pakd-dd">
                                        <input type="text" placeholder="Tìm PAKD..." oninput="filterPakd(this)">
                                        <ul></ul>
                                        <div class="pakd-link-input">
                                            <input type="text" placeholder="Hoặc dán link PAKD..." class="link-input" onkeydown="if(event.key==='Enter'){savePakdLink(this);event.preventDefault();}">
                                            <button onclick="savePakdLink(this.previousElementSibling)">OK</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="ebt-cell" data-inv="<?= $inv_id ?>" data-mode="<?= $is_link_mode ? 'manual' : 'auto' ?>">
                                <?php if ($is_link_mode && $sel_manual_ebt !== null): ?>
                                    <span class="ebt-val editable" style="color:<?= $sel_manual_ebt >= 20 ? '#16a34a' : ($sel_manual_ebt >= 5 ? '#2563eb' : '#dc2626') ?>;font-weight:600;"><?= $sel_manual_ebt ?>%</span>
                                    <input type="number" class="ebt-edit can-edit" value="<?= $sel_manual_ebt ?>" step="0.1" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)">
                                <?php elseif ($is_link_mode): ?>
                                    <input type="number" class="ebt-edit can-edit" value="" step="0.1" placeholder="%" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)" style="display:inline-block;">
                                <?php elseif ($sel_ebt !== ''): ?>
                                    <span class="ebt-val" style="color:<?= $sel_ebt >= 20 ? '#16a34a' : ($sel_ebt >= 5 ? '#2563eb' : '#dc2626') ?>;font-weight:600;"><?= $sel_ebt ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td class="amt"><?= mc_fmt($amt_orig) ?> <?= $curr ?></td>
                            <td class="amt"><?= mc_fmt($d['amount_vnd']) ?></td>
                            <td><span class="badge <?= $ps_cls ?>"><?= $ps_label ?></span></td>
                            <td class="amt" style="color:#64748b;"><?= $d['ebt_vnd'] !== null ? mc_fmt($d['ebt_vnd']) : '—' ?></td>
                            <td class="amt license-bonus-cell" data-amount="<?= $d['amount_vnd'] ?>" data-paidok="<?= $d['paid_in_quarter'] ? 1 : 0 ?>" style="color:<?= $l_show ? '#b45309' : '#94a3b8' ?>;font-weight:600;" title="<?= !$d['paid_in_quarter'] ? 'Chưa thanh toán trong quý' : (!$license_kpi_ok ? 'License Bonus yêu cầu KPI quý ≥ 80%' : ($d['ebt_pct'] !== null ? '10% × EBT ' . mc_fmt($d['ebt_vnd']) : 'Chọn PAKD / nhập EBT')) ?>"><?= $l_show ? mc_fmt($d['bonus']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="mh">
                            <td colspan="11" style="background:#fffbeb;color:#b45309;border-color:#fde68a;text-align:right;font-weight:600;">Tổng License Bonus<?= $license_kpi_ok ? '' : ' (chưa đạt KPI ≥ 80%)' ?></td>
                            <td class="amt" id="licenseTotal" style="background:#fffbeb;color:#b45309;border-color:#fde68a;font-weight:700;"><?= $total_license_bonus > 0 ? mc_fmt($total_license_bonus) : '' ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- ─── Sale Orders — First PO Commission ─── -->
            <div style="margin-top:1.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <h3 style="margin:0;font-size:15px;color:#7c3aed;font-weight:700;">Sale Orders — First PO Commission</h3>
                    <span style="font-size:11px;color:#64748b;">Q<?= $selected_quarter ?>/<?= $selected_year ?> · Confirmed &amp; Done · Tỷ lệ 1/1000 khi là First PO</span>
                </div>
                <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="background:#f5f3ff;color:#5b21b6;">
                            <th style="padding:7px 10px;text-align:left;border:1px solid #e2e8f0;white-space:nowrap;">SO #</th>
                            <th style="padding:7px 10px;text-align:left;border:1px solid #e2e8f0;">Khách hàng</th>
                            <th style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0;white-space:nowrap;">Ngày</th>
                            <th style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0;">Trạng thái</th>
                            <th style="padding:7px 10px;text-align:right;border:1px solid #e2e8f0;">Amount</th>
                            <th style="padding:7px 10px;text-align:right;border:1px solid #e2e8f0;">VND</th>
                            <th style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0;white-space:nowrap;">First PO?</th>
                            <th style="padding:7px 10px;text-align:right;border:1px solid #e2e8f0;white-space:nowrap;">Commission (1/1000)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($so_error)): ?>
                        <tr><td colspan="8" style="padding:12px;text-align:center;color:#dc2626;border:1px solid #e2e8f0;">Lỗi tải Sale Orders: <?= htmlspecialchars($so_error) ?></td></tr>
                    <?php elseif (empty($so_list)): ?>
                        <tr><td colspan="8" style="padding:12px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;">Không có Sale Order (Confirmed/Done) trong Q<?= $selected_quarter ?>/<?= $selected_year ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($so_list as $so):
                            $so_id        = (int)$so['id'];
                            $amount_orig  = (float)$so['amount_total'];
                            $amount_vnd   = $so['_amount_vnd'];
                            $qualifies    = $amount_vnd >= SO_MIN_VND; // chỉ tính commission nếu ≥ 1 tỷ
                            $is_first     = $qualifies && ($so_first_po_flags[$so_id] ?? false);
                            $so_com       = $is_first ? $amount_vnd * SO_COM_RATE : 0;
                            $state_map    = ['draft' => ['Nháp', '#94a3b8'], 'sale' => ['Confirmed', '#16a34a'], 'done' => ['Done', '#2563eb']];
                            [$state_label, $state_color] = $state_map[$so['state']] ?? [$so['state'], '#64748b'];
                        ?>
                        <tr class="so-row" data-soid="<?= $so_id ?>" data-vnd="<?= (int)$amount_vnd ?>" data-qualifies="<?= $qualifies ? 1 : 0 ?>">
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;font-weight:600;color:#5b21b6;white-space:nowrap;">
                                <?= htmlspecialchars($so['name']) ?>
                                <?php if (!empty($so['client_order_ref'])): ?>
                                <div style="font-size:10px;color:#94a3b8;font-weight:400;"><?= htmlspecialchars($so['client_order_ref']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;"><?= htmlspecialchars($so['_partner_name']) ?></td>
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;text-align:center;color:#64748b;white-space:nowrap;"><?= substr($so['date_order'], 0, 10) ?></td>
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;text-align:center;">
                                <span style="background:<?= $state_color ?>20;color:<?= $state_color ?>;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;"><?= $state_label ?></span>
                            </td>
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;color:#374151;">
                                <?= $so['_cur'] !== 'VND' ? number_format($amount_orig, 0) . ' ' . htmlspecialchars($so['_cur']) : mc_fmt($amount_orig) ?>
                            </td>
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;font-weight:600;color:<?= $qualifies ? '#1d4ed8' : '#94a3b8' ?>;"><?= mc_fmt($amount_vnd) ?></td>
                            <td style="padding:7px 10px;border:1px solid #e2e8f0;text-align:center;">
                                <?php if ($qualifies): ?>
                                <select class="so-first-po-sel" data-soid="<?= $so_id ?>" onchange="saveFirstPo(this)"
                                        style="padding:3px 6px;border:1px solid #e2e8f0;border-radius:5px;font-size:11px;background:#fff;cursor:pointer;">
                                    <option value="0"<?= !$is_first ? ' selected' : '' ?>>— Chọn —</option>
                                    <option value="1"<?= $is_first ? ' selected' : '' ?>>Yes — First PO</option>
                                </select>
                                <?php else: ?>
                                <span style="font-size:10px;color:#94a3b8;" title="Chỉ áp dụng khi SO ≥ 1 tỷ VND">< 1 tỷ</span>
                                <?php endif; ?>
                            </td>
                            <td class="so-com-cell" style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;font-weight:700;color:<?= $so_com > 0 ? '#7c3aed' : '#94a3b8' ?>;">
                                <?= $so_com > 0 ? mc_fmt($so_com) : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f5f3ff;">
                            <td colspan="7" style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;font-weight:600;color:#5b21b6;">Tổng First PO Commission</td>
                            <td class="amt" id="soComTotal" style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;font-weight:700;color:#7c3aed;"><?= $total_so_com > 0 ? mc_fmt($total_so_com) : '—' ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Bonus = Contract value (VND) × 1/1000 · Chỉ áp dụng cho hợp đồng đầu tiên của khách (trong vòng 1 năm kể từ ký kết)</div>
            </div>

            <!-- ─── Commission Rules Summary ─── -->
            <details style="margin-top:1rem;font-size:12px;color:#64748b;">
                <summary style="cursor:pointer;font-weight:600;padding:0.5rem 0;">Quy tắc tính Commission (tham khảo)</summary>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;margin-top:0.5rem;line-height:1.8;">
                    <strong>Com1 (Revenue):</strong> New Client = 1% · Old Client = 0.5% · <span style="color:#f59e0b;">Market to Lead = "My Lead" → +1% Com1 ★ (chỉ khách New)</span> · <span style="color:#dc2626;">EBT &lt; 5% → 0 com (chỉ tính KPI)</span><br>
                    <strong>Com2 (High Value):</strong> Chỉ khi EBT ≥ 20%. HV = Giá chênh = Revenue − Revenue Base (nhập tay, ₫ hoặc $ → quy đổi VND). Tier <strong>tự tính</strong> theo ratio = Revenue / Base (Base = Revenue − HV): &gt;1.5 → 5% · (&gt;1.3–1.5] → 3% · (&gt;1–1.3] → 2% · ≤1 → không có Com2. Com2 = HV(VND) × Tier%. VD: Revenue 2 tỷ, HV 500tr → Base 1.5 tỷ, ratio 1.33 → rank (&gt;1.3–1.5) → Tier 3% → Com2 = 500tr × 3% = 15tr<br>
                    <strong>KPI Adj:</strong> < 60%: 0 com · 60-80%: 70% com · ≥ 80%: 100% com<br>
                    <strong>Yearly Bonus:</strong> 2% × S_EBT (nếu %A_EBT ≥ 12.5%) hoặc 4% × S_EBT (nếu ≥ 20%)<br>
                    <strong>AI Add-on:</strong> <span style="color:#0891b2;">AI Com = AI Revenue(VND, ₫/$ → quy đổi) × rate.</span> AIHive Solutions: 5% (EBT≥20%) / 2% (EBT≥10%) · AI Solutions: 2% (EBT≥15%) · Bắt buộc KPI ≥ 60% & Collection = 1 (đã thanh toán)<br>
                    <strong>Lương KPI:</strong> < 60%: 0 · 60-80%: 50% · 80-150%: 100% · >150%: 150%<br>
                    <strong style="color:#b45309;">License Bonus:</strong> HĐ type License (bảng riêng). Bonus = 10% × EBT(License) khi <strong>KPI quý ≥ 80%</strong> & đã thanh toán. Doanh thu License <strong>không</strong> tính vào KPI.
                </div>
            </details>

        </div>
    </main>
</div>
<style>
.save-toast { position:fixed; top:20px; right:20px; z-index:9999; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; display:none; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(0,0,0,.15); animation:fadeIn .2s; }
.save-toast.ok { background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0; }
.save-toast.err { background:#fce8e6; color:#c5221f; border:1px solid #fad2cf; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
</style>
<div class="save-toast" id="saveToast"></div>
<script>
const pakdList = <?= json_encode(array_values(array_map(function($p) {
    $ebt = $p['revenue'] > 0 ? round($p['gross_profit'] / $p['revenue'] * 100, 1) : 0;
    return ['id' => (int)$p['id'], 'name' => $p['name'], 'company' => $p['company_name'] ?? '', 'ebt' => $ebt];
}, $pakd_list)), JSON_UNESCAPED_UNICODE) ?>;

function showToast(msg, ok) {
    const t = document.getElementById('saveToast');
    t.className = 'save-toast ' + (ok ? 'ok' : 'err');
    t.innerHTML = (ok ? '&#10003; ' : '&#10007; ') + msg;
    t.style.display = 'flex';
    clearTimeout(t._tm);
    t._tm = setTimeout(() => t.style.display = 'none', 2000);
}

function savePakdApi(body) {
    return fetch('/api/invoice_pakd_map.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) showToast('Saved', true);
        else showToast(data.error || 'Lỗi', false);
        return data;
    })
    .catch(err => {
        console.error('PAKD API error:', err);
        showToast('Lỗi kết nối: ' + err.message, false);
    });
}

function togglePakd(btn) {
    document.querySelectorAll('.pakd-dd.open').forEach(d => { if (d !== btn.nextElementSibling) d.classList.remove('open'); });
    const dd = btn.closest('.pakd-wrap').querySelector('.pakd-dd');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) {
        const input = dd.querySelector('input:first-child');
        input.value = '';
        input.focus();
        renderPakdList(dd, '');
    }
}

function renderPakdList(dd, q) {
    const ul = dd.querySelector('ul');
    const wrap = dd.closest('.pakd-wrap');
    const curId = parseInt(wrap.dataset.pakdId || '0');
    const lower = q.toLowerCase();
    let html = '<li class="p-clear" data-id="0">Bỏ chọn</li>';
    pakdList.forEach(p => {
        if (lower && !p.name.toLowerCase().includes(lower) && !(p.company||'').toLowerCase().includes(lower)) return;
        const sel = p.id === curId ? ' sel' : '';
        html += `<li class="${sel}" data-id="${p.id}"><span class="p-name">${esc(p.name)}</span><span class="p-ebt">EBT: ${p.ebt}%</span></li>`;
    });
    ul.innerHTML = html;
    ul.querySelectorAll('li').forEach(li => li.addEventListener('click', () => selectPakd(li)));
}

function filterPakd(input) {
    renderPakdList(input.closest('.pakd-dd'), input.value);
}

function selectPakd(li) {
    const dd = li.closest('.pakd-dd');
    const wrap = dd.closest('.pakd-wrap');
    const pakdId = parseInt(li.dataset.id);
    const invId = parseInt(wrap.dataset.inv);
    const ebtCell = wrap.closest('tr').querySelector('.ebt-cell');

    // Remove link display if switching to PAKD select mode
    const linkEl = wrap.querySelector('.pakd-link-display');
    const clearEl = wrap.querySelector('.pakd-link-clear');
    if (linkEl) linkEl.remove();
    if (clearEl) clearEl.remove();

    let btn = wrap.querySelector('.pakd-btn');
    if (!btn) {
        btn = document.createElement('div');
        btn.className = 'pakd-btn';
        btn.onclick = function() { togglePakd(this); };
        wrap.insertBefore(btn, dd);
    }

    if (pakdId === 0) {
        btn.textContent = '— Chọn PAKD —';
        btn.classList.remove('has-val');
        wrap.dataset.pakdId = '0';
        wrap.dataset.link = '';
        if (ebtCell) { ebtCell.innerHTML = ''; ebtCell.dataset.mode = 'auto'; }
    } else {
        const p = pakdList.find(x => x.id === pakdId);
        btn.textContent = p ? p.name : '';
        btn.classList.add('has-val');
        wrap.dataset.pakdId = pakdId;
        wrap.dataset.link = '';
        if (ebtCell && p) {
            ebtCell.dataset.mode = 'auto';
            const color = p.ebt >= 20 ? '#16a34a' : (p.ebt >= 5 ? '#2563eb' : '#dc2626');
            ebtCell.innerHTML = `<span class="ebt-val" style="color:${color};font-weight:600;">${p.ebt}%</span>`;
        }
    }
    dd.classList.remove('open');

    // Sync Tier availability with the PAKD's EBT (Com2 requires EBT >= 20%)
    const p2 = pakdId === 0 ? null : pakdList.find(x => x.id === pakdId);
    syncTierAvailability(wrap.closest('tr'), p2 ? p2.ebt : null);
    recomputeLicense();

    savePakdApi({invoice_id: invId, pakd_id: pakdId, pakd_link: ''});
}

function savePakdLink(input) {
    const link = input.value.trim();
    if (!link) return;
    const dd = input.closest('.pakd-dd');
    const wrap = dd.closest('.pakd-wrap');
    const invId = parseInt(wrap.dataset.inv);
    const ebtCell = wrap.closest('tr').querySelector('.ebt-cell');

    // Remove existing btn
    const btn = wrap.querySelector('.pakd-btn');
    if (btn) btn.remove();

    // Remove existing link display
    const oldLink = wrap.querySelector('.pakd-link-display');
    const oldClear = wrap.querySelector('.pakd-link-clear');
    if (oldLink) oldLink.remove();
    if (oldClear) oldClear.remove();

    // Create link display
    const a = document.createElement('a');
    a.href = link; a.target = '_blank'; a.className = 'pakd-link-display';
    a.title = link; a.textContent = link.length > 30 ? link.substring(0, 30) + '...' : link;
    const x = document.createElement('span');
    x.className = 'pakd-link-clear'; x.innerHTML = '&times;'; x.title = 'Xóa';
    x.onclick = function() { clearPakd(this); };
    wrap.insertBefore(a, dd);
    wrap.insertBefore(x, dd);

    wrap.dataset.pakdId = '0';
    wrap.dataset.link = link;
    dd.classList.remove('open');

    // Switch EBT to manual input
    if (ebtCell) {
        ebtCell.dataset.mode = 'manual';
        ebtCell.innerHTML = `<input type="number" class="ebt-edit can-edit" value="" step="0.1" placeholder="%" onchange="saveManualEbt(this)" onblur="saveManualEbt(this)" style="display:inline-block;">`;
    }

    // EBT now unknown (manual, empty) → disable Tier until user enters EBT >= 20%
    syncTierAvailability(wrap.closest('tr'), null);
    recomputeLicense();

    savePakdApi({invoice_id: invId, pakd_id: 0, pakd_link: link});
}

function clearPakd(el) {
    const wrap = el.closest('.pakd-wrap');
    const dd = wrap.querySelector('.pakd-dd');
    const invId = parseInt(wrap.dataset.inv);
    const ebtCell = wrap.closest('tr').querySelector('.ebt-cell');

    // Remove link display
    const linkEl = wrap.querySelector('.pakd-link-display');
    const clearEl = wrap.querySelector('.pakd-link-clear');
    if (linkEl) linkEl.remove();
    if (clearEl) clearEl.remove();

    // Re-create button
    const btn = document.createElement('div');
    btn.className = 'pakd-btn';
    btn.textContent = '— Chọn PAKD —';
    btn.onclick = function() { togglePakd(this); };
    wrap.insertBefore(btn, dd);

    wrap.dataset.pakdId = '0';
    wrap.dataset.link = '';
    if (ebtCell) { ebtCell.innerHTML = ''; ebtCell.dataset.mode = 'auto'; }

    // EBT cleared → disable Tier (Com2 requires EBT >= 20%)
    syncTierAvailability(wrap.closest('tr'), null);
    recomputeLicense();

    savePakdApi({invoice_id: invId, pakd_id: 0, pakd_link: ''});
}

function saveManualEbt(input) {
    const val = input.value.trim();
    const ebtCell = input.closest('.ebt-cell');
    const invId = parseInt(ebtCell.dataset.inv);
    const numVal = val === '' ? null : parseFloat(val);

    savePakdApi({invoice_id: invId, update_ebt: true, manual_ebt: numVal}).then(() => {
        if (numVal !== null) {
            const color = numVal >= 20 ? '#16a34a' : (numVal >= 5 ? '#2563eb' : '#dc2626');
            let span = ebtCell.querySelector('.ebt-val');
            if (!span) {
                span = document.createElement('span');
                span.className = 'ebt-val editable';
                ebtCell.insertBefore(span, ebtCell.firstChild);
                input.classList.add('can-edit');
                input.style.display = '';
            }
            span.style.color = color;
            span.style.fontWeight = '600';
            span.textContent = numVal + '%';
        }
    });
    // Com2 requires EBT >= 20% — toggle the Tier dropdown for this row
    syncTierAvailability(ebtCell.closest('tr'), numVal);
    // License Bonus rows also key off EBT
    recomputeLicense();
}

// Com1 components — mutable because the Market-to-Lead source can add +1% live.
let NET_COM1 = <?= json_encode((float)$net_com1) ?>;
let COLLECTED_COM1 = <?= json_encode((float)$total_collected_com1) ?>;
let GRAND_COM1 = <?= json_encode((float)$grand_com1) ?>;
const KPI_ADJ = <?= json_encode((float)$kpi_adj) ?>;            // current-quarter KPI adjustment
const SELF_LEAD_BONUS = <?= json_encode((float)MC_SELF_LEAD_BONUS) ?>;
const HV_RATES = <?= json_encode(array_map('floatval', $hv_rates)) ?>;

// License Bonus: 10% × EBT(License) for paid-in-quarter rows, gated on quarter KPI ≥ 80% (binary).
const LICENSE_RATE = <?= json_encode((float)MC_LICENSE_RATE) ?>;
const LICENSE_KPI_OK = <?= json_encode((bool)$license_kpi_ok) ?>;

// Read a row's current EBT % from its .ebt-cell (manual input if present, else the displayed value).
function rowEbtVal(tr) {
    if (!tr) return null;
    const cell = tr.querySelector('.ebt-cell');
    if (!cell) return null;
    const input = cell.querySelector('.ebt-edit');
    if (input && input.value.trim() !== '') { const v = parseFloat(input.value); return isNaN(v) ? null : v; }
    const span = cell.querySelector('.ebt-val');
    if (span) { const v = parseFloat(span.textContent); return isNaN(v) ? null : v; }
    return null;
}

// Recompute License Bonus across all License rows + the summary card/footer.
function recomputeLicense() {
    let total = 0;
    document.querySelectorAll('.license-bonus-cell').forEach(cell => {
        const tr = cell.closest('tr');
        const amount = parseFloat(cell.dataset.amount) || 0;
        const paidok = cell.dataset.paidok === '1';
        const ebt = rowEbtVal(tr);
        let val = 0;
        if (ebt !== null && paidok && LICENSE_KPI_OK) val = amount * ebt / 100 * LICENSE_RATE;
        if (val > 0) { cell.textContent = fmtFull(val); cell.style.color = '#b45309'; }
        else { cell.textContent = '—'; cell.style.color = '#94a3b8'; }
        total += val;
    });
    const foot = document.getElementById('licenseTotal'); if (foot) foot.textContent = total > 0 ? fmtFull(total) : '';
    const card = document.getElementById('ccLicenseGross'); if (card) card.textContent = total > 0 ? fmtShort(total) : '–';
    const tag = document.getElementById('ccLicenseTag');
    if (tag) { tag.textContent = total > 0 ? 'Calculated' : (LICENSE_KPI_OK ? 'Cần EBT & thanh toán' : 'Chưa đạt KPI'); tag.className = 'cc-tag ' + (total > 0 ? 'tag-ok' : 'tag-na'); }
}

// AI Com components — mutable because the AI add-on type / revenue can change live.
let NET_AI = <?= json_encode((float)$total_ai_com) ?>;
let COLLECTED_AI = <?= json_encode((float)$total_collected_ai_com) ?>;
let GRAND_AI     = <?= json_encode((float)$grand_ai_com) ?>;
let GRAND_COM2   = <?= json_encode((float)$grand_com2) ?>;
let GRAND_SO_COM = <?= json_encode((float)$total_so_com) ?>;

// AI commission rate given add-on type + EBT % (mirrors PHP mc_ai_rate).
function aiRate(addon, ebt) {
    if (ebt === null || ebt === '' || isNaN(ebt)) return 0;
    if (addon === 'aihive') { if (ebt >= 20) return 0.05; if (ebt >= 10) return 0.02; return 0; }
    if (addon === 'ai_solutions') { if (ebt >= 15) return 0.02; return 0; }
    return 0;
}

// Yearly Bonus rate chosen from the AVERAGE EBT % (mirrors PHP mc_yb_rate / yb_rate_from_avg).
function ybRate(avgEbt) {
    if (avgEbt === null || avgEbt === '' || isNaN(avgEbt)) return 0;
    if (avgEbt >= 20) return 0.04;
    if (avgEbt >= 12.5) return 0.02;
    return 0;
}

// Recompute the Yearly Bonus estimate: rate(quarter avg EBT %) × S_EBT (Σ revenue × EBT%).
function recomputeYearlyBonus() {
    let ebtMain = 0, revMain = 0, ebtRec = 0, revRec = 0;
    document.querySelectorAll('.com1-cell').forEach(cell => {
        if (cell.dataset.paidok !== '1') return;
        const ebt = cell.dataset.ebt === '' ? null : parseFloat(cell.dataset.ebt);
        if (ebt === null || isNaN(ebt)) return;
        const amount = parseFloat(cell.dataset.amount) || 0;
        ebtMain += amount * ebt / 100;
        revMain += amount;
    });
    document.querySelectorAll('.com1-cell-rec').forEach(cell => {
        const ebt = cell.dataset.ebt === '' ? null : parseFloat(cell.dataset.ebt);
        if (ebt === null || isNaN(ebt)) return;
        const amount = parseFloat(cell.dataset.amount) || 0;
        ebtRec += amount * ebt / 100;   // recovered invoices are always paid this quarter
        revRec += amount;
    });
    const ebtTotal = ebtMain + ebtRec, revTotal = revMain + revRec;
    const avgEbt = revTotal > 0 ? (ebtTotal / revTotal * 100) : null;
    const rate = ybRate(avgEbt);
    const main = rate * ebtMain, rec = rate * ebtRec, grand = rate * ebtTotal;
    const v = document.getElementById('ccYbGross'); if (v) v.textContent = fmtShort(grand);
    const tq = document.getElementById('ccYbThisQ'); if (tq) tq.textContent = fmtShort(main);
    const rc = document.getElementById('ccYbRecovery'); if (rc) rc.textContent = fmtShort(rec);
    const tag = document.getElementById('ccYbTag');
    if (tag) { tag.textContent = grand > 0 ? 'Ước tính (est.)' : 'Tổng kết năm'; tag.className = 'cc-tag ' + (grand > 0 ? 'tag-ok' : 'tag-na'); }
}

function fmtFull(n) { return Math.round(n).toLocaleString('en-US'); }
function fmtShort(n) {
    const a = Math.abs(n);
    if (a >= 1e9) return (n / 1e9).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' tỷ';
    if (a >= 1e6) return (n / 1e6).toLocaleString('en-US', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' tr';
    return Math.round(n).toLocaleString('en-US');
}

// Market to Lead: persist the source and (if self-sourced) re-apply the +1% Com1 bonus live.
function saveLead(select) {
    const invId = parseInt(select.dataset.inv);
    const val = select.value;
    recomputeCom1();
    savePakdApi({invoice_id: invId, update_lead: true, lead_source: val});
}

// Recompute Com1 across both sections after a lead-source change, then refresh Com2 totals.
function recomputeCom1() {
    const monthSums = {};
    let grossMain = 0;
    // Current-quarter rows
    document.querySelectorAll('.com1-cell').forEach(cell => {
        const row = cell.closest('tr');
        const leadSel = row.querySelector('.lead-select');
        const isNew = cell.dataset.isnew === '1';
        const self = isNew && leadSel && leadSel.value === 'self';
        const amount = parseFloat(cell.dataset.amount) || 0;
        const baseRate = parseFloat(cell.dataset.baserate) || 0;
        const paidok = cell.dataset.paidok === '1';
        const effRate = baseRate > 0 ? baseRate + (self ? SELF_LEAD_BONUS : 0) : 0;
        const gross = amount * effRate;
        // Amount cell
        if (effRate === 0 && !paidok) { cell.textContent = '—'; }
        else { cell.textContent = fmtFull(gross); cell.style.color = effRate === 0 ? '#dc2626' : '#1d4ed8'; }
        // Rate cell (only refresh the percentage when a base Com1 applies)
        if (baseRate > 0) {
            const rateCell = row.querySelector('.com1-rate-cell');
            if (rateCell) {
                rateCell.textContent = ratePct(effRate);
                if (self) {
                    const star = document.createElement('span');
                    star.className = 'lead-bonus'; star.title = '+1% My Lead'; star.textContent = ' ★';
                    rateCell.appendChild(star);
                }
            }
        }
        grossMain += gross;
        const m = cell.dataset.month || '';
        monthSums[m] = (monthSums[m] || 0) + gross;
    });
    // Month Com1 subtotals (gross, matching the per-row gross display)
    document.querySelectorAll('.m-com1-sub').forEach(c => {
        c.textContent = fmtShort(monthSums[c.dataset.month || ''] || 0);
    });
    NET_COM1 = grossMain * KPI_ADJ;

    // Recovery rows (each with its own origin-quarter KPI adj)
    let collected = 0;
    document.querySelectorAll('.com1-cell-rec').forEach(cell => {
        const row = cell.closest('tr');
        const leadSel = row.querySelector('.lead-select');
        const isNew = cell.dataset.isnew === '1';
        const self = isNew && leadSel && leadSel.value === 'self';
        const amount = parseFloat(cell.dataset.amount) || 0;
        const baseRate = parseFloat(cell.dataset.baserate) || 0;
        const adj = parseFloat(cell.dataset.adj) || 0;
        const effRate = baseRate > 0 ? baseRate + (self ? SELF_LEAD_BONUS : 0) : 0;
        const net = amount * effRate * adj;
        const valDiv = cell.querySelector('.com1-rec-val');
        if (valDiv) { valDiv.textContent = fmtFull(net); valDiv.style.color = net === 0 ? '#94a3b8' : '#1d4ed8'; }
        collected += net;
    });
    const recTot = document.getElementById('recCom1Total');
    if (recTot) recTot.textContent = fmtFull(collected);
    COLLECTED_COM1 = collected;
    GRAND_COM1 = NET_COM1 + COLLECTED_COM1;

    // Com1 cards
    const gr = document.getElementById('ccCom1Gross'); if (gr) gr.textContent = fmtShort(grossMain);
    const g = document.getElementById('ccCom1Net'); if (g) g.textContent = fmtShort(NET_COM1);
    const gg = document.getElementById('ccCom1Grand'); if (gg) gg.textContent = fmtShort(GRAND_COM1);

    // Refresh combined totals (Com1 + Com2) on the summary card
    recomputeCom2();
}

// Format a commission rate fraction (0.015) as a tidy percent string ("1.5%").
function ratePct(rate) {
    let s = (rate * 100).toFixed(2).replace(/\.?0+$/, '');
    return s + '%';
}

// Auto Tier % from the Revenue/Base ratio. Base = amount(VND) − HV(VND). ratio = amount/Base.
// >1.5 → 5% · (>1.3–1.5] → 3% · (>1–1.3] → 2% · else 0. HV null/≤0 → 0. Base ≤ 0 → 5%.
function autoTier(amount, hvVnd) {
    if (!hvVnd || hvVnd <= 0 || !amount || amount <= 0) return 0;
    const base = amount - hvVnd;
    if (base <= 0) return 5;
    const ratio = amount / base;
    if (ratio > 1.5) return 5;
    if (ratio > 1.3) return 3;
    if (ratio > 1.0) return 2;
    return 0;
}

// Rank label of the Revenue/Base ratio band (matches autoTier / PHP mc_tier_rank).
function tierRank(amount, hvVnd) {
    if (!hvVnd || hvVnd <= 0 || !amount || amount <= 0) return '';
    const base = amount - hvVnd;
    if (base <= 0) return '(>1.5)';
    const ratio = amount / base;
    if (ratio > 1.5) return '(>1.5)';
    if (ratio > 1.3) return '(>1.3–1.5)';
    if (ratio > 1.0) return '(>1–1.3)';
    return '(≤1)';
}

function recomputeCom2() {
    const monthSums = {};
    let netCom2Main = 0, com2Recovery = 0;
    document.querySelectorAll('.com2-cell').forEach(cell => {
        const base = parseFloat(cell.dataset.base) || 0;   // HV in VND
        const amount = parseFloat(cell.dataset.amount) || 0;
        const tier = autoTier(amount, base);
        const adj = parseFloat(cell.dataset.adj) || 0;
        const eligible = cell.dataset.paidok === '1' && cell.dataset.ebtok === '1';
        let val = 0;
        if (tier > 0 && eligible) val = base * tier / 100 * adj;
        if (tier <= 0 || !eligible) { cell.textContent = '—'; cell.style.color = '#94a3b8'; }
        else {
            cell.innerHTML = fmtFull(val) + '<div class="com2-meta" style="font-size:10px;color:#94a3b8;font-weight:500;line-height:1.2;">' + tier + '% · ' + tierRank(amount, base) + '</div>';
            cell.style.color = '#7c3aed';
        }
        if (cell.dataset.section === 'recovery') {
            com2Recovery += val;
        } else {
            netCom2Main += val;
            const m = cell.dataset.month || '';
            monthSums[m] = (monthSums[m] || 0) + val;
        }
    });
    // Month subtotals
    document.querySelectorAll('.m-com2-sub').forEach(c => {
        const v = monthSums[c.dataset.month || ''] || 0;
        c.textContent = v > 0 ? fmtShort(v) : '';
    });
    // Recovery footer total
    const recTot = document.getElementById('recCom2Total');
    if (recTot) recTot.textContent = com2Recovery > 0 ? fmtFull(com2Recovery) : '';
    // Commission summary card
    GRAND_COM2 = netCom2Main + com2Recovery;
    const grandCom2 = GRAND_COM2;
    // Com2 (High Value) card
    const c2Gross = document.getElementById('ccCom2Gross');
    if (c2Gross) c2Gross.textContent = grandCom2 > 0 ? fmtShort(grandCom2) : '–';
    const c2ThisQ = document.getElementById('ccCom2ThisQ'); if (c2ThisQ) c2ThisQ.textContent = fmtShort(netCom2Main);
    const c2Rec = document.getElementById('ccCom2Recovery'); if (c2Rec) c2Rec.textContent = fmtShort(com2Recovery);
    const c2Tag = document.getElementById('ccCom2Tag');
    if (c2Tag) { c2Tag.textContent = grandCom2 > 0 ? 'Calculated' : 'Chưa đủ điều kiện'; c2Tag.className = 'cc-tag ' + (grandCom2 > 0 ? 'tag-ok' : 'tag-na'); }
    const com2Part = document.getElementById('ccCom2Part');
    if (com2Part) {
        com2Part.style.display = grandCom2 > 0 ? '' : 'none';
        const c2 = document.getElementById('ccCom2');
        if (c2) c2.textContent = fmtShort(grandCom2);
    }
    const thisQ = document.getElementById('ccThisQ');
    if (thisQ) thisQ.textContent = fmtShort(NET_COM1 + netCom2Main + NET_AI);
    const recPart = document.getElementById('ccRecoveryPart');
    if (recPart) {
        const recSum = COLLECTED_COM1 + com2Recovery + COLLECTED_AI;
        recPart.style.display = recSum > 0 ? '' : 'none';
        const rc = document.getElementById('ccRecovery');
        if (rc) rc.textContent = fmtShort(recSum);
    }
    refreshGrandTotal();
}

// Recompute AI Com across both sections after an add-on / revenue change, then refresh summary.
function recomputeAiCom() {
    const monthSums = {};
    let netMain = 0, recovery = 0;
    document.querySelectorAll('.aicom-cell').forEach(cell => {
        const row = cell.closest('tr');
        const aiSel = row.querySelector('.ai-select');
        const addon = aiSel ? aiSel.value : '';
        const rev = parseFloat(cell.dataset.rev) || 0;
        const ebt = cell.dataset.ebt === '' ? null : parseFloat(cell.dataset.ebt);
        const paidok = cell.dataset.paidok === '1';
        const kpiok = cell.dataset.kpiok === '1';
        const rate = aiRate(addon, ebt);
        let val = 0;
        if (addon !== '' && rev > 0 && paidok && kpiok) val = rev * rate;
        if (val > 0) { cell.textContent = fmtFull(val); cell.style.color = '#0891b2'; }
        else { cell.textContent = '—'; cell.style.color = '#94a3b8'; }
        if (cell.dataset.section === 'recovery') {
            recovery += val;
        } else {
            netMain += val;
            const m = cell.dataset.month || '';
            monthSums[m] = (monthSums[m] || 0) + val;
        }
    });
    document.querySelectorAll('.m-aicom-sub').forEach(c => {
        const v = monthSums[c.dataset.month || ''] || 0;
        c.textContent = v > 0 ? fmtShort(v) : '';
    });
    const recTot = document.getElementById('recAiComTotal');
    if (recTot) recTot.textContent = recovery > 0 ? fmtFull(recovery) : '';
    NET_AI = netMain;
    COLLECTED_AI = recovery;
    GRAND_AI = netMain + recovery;
    // AI Add-on card
    const aiGross = document.getElementById('ccAiGross');
    if (aiGross) aiGross.textContent = fmtShort(GRAND_AI);
    const aiThisQ = document.getElementById('ccAiThisQ'); if (aiThisQ) aiThisQ.textContent = fmtShort(netMain);
    const aiRec = document.getElementById('ccAiRecovery'); if (aiRec) aiRec.textContent = fmtShort(recovery);
    const aiTag = document.getElementById('ccAiTag');
    if (aiTag) { aiTag.textContent = GRAND_AI > 0 ? 'Calculated' : 'Cần tag AI Revenue'; aiTag.className = 'cc-tag ' + (GRAND_AI > 0 ? 'tag-ok' : 'tag-na'); }
    // Net Commission card AI part
    const aiPart = document.getElementById('ccAiPart');
    if (aiPart) {
        aiPart.style.display = GRAND_AI > 0 ? '' : 'none';
        const ai = document.getElementById('ccAi');
        if (ai) ai.textContent = fmtShort(GRAND_AI);
    }
    recomputeCom2();
}

// AI Add-on: persist the chosen add-on and re-apply AI Com live.
function saveAiAddon(select) {
    const invId = parseInt(select.dataset.inv);
    recomputeAiCom();
    savePakdApi({invoice_id: invId, update_ai_addon: true, ai_addon: select.value});
}

// AI Revenue: amount + currency (→VND like HV). Updates the row's AI Com base then recomputes.
function saveAiRev(el) {
    const cell = el.closest('.airev-cell');
    if (!cell) return;
    const input = cell.querySelector('.airev-input');
    const curSel = cell.querySelector('.airev-cur');
    const invId = parseInt(cell.dataset.inv);
    const raw = input.value.trim();
    const rev = raw === '' ? null : parseFloat(raw);
    const cur = curSel ? curSel.value : 'VND';
    const rate = (HV_RATES && HV_RATES[cur]) ? HV_RATES[cur] : 1;
    const revVnd = rev === null ? 0 : rev * rate;
    // Update the row's AI Com base + the →VND hint
    const row = cell.closest('tr');
    const aiComCell = row ? row.querySelector('.aicom-cell') : null;
    if (aiComCell) aiComCell.dataset.rev = revVnd;
    let hint = cell.querySelector('.hv-vnd');
    if (rev !== null && cur !== 'VND') {
        if (!hint) { hint = document.createElement('div'); hint.className = 'hv-vnd'; hint.title = 'Quy đổi VND'; cell.appendChild(hint); }
        hint.textContent = fmtShort(revVnd);
    } else if (hint) { hint.remove(); }
    recomputeAiCom();
    savePakdApi({invoice_id: invId, update_ai_revenue: true, ai_revenue: rev, ai_revenue_currency: cur});
}

// HV (Giá chênh / High Value): user enters amount + currency (₫/$), converted to VND.
// Com2 = HV(VND) × Tier %, where Tier is auto-derived from the Revenue/Base ratio.
function saveHv(el) {
    const cell = el.closest('.hv-cell');
    if (!cell) return;
    const input = cell.querySelector('.hv-input');
    const curSel = cell.querySelector('.hv-cur');
    if (input.disabled) return;
    const invId = parseInt(cell.dataset.inv);
    const raw = input.value.trim();
    const hv = raw === '' ? null : parseFloat(raw);
    const cur = curSel ? curSel.value : 'VND';
    const rate = (HV_RATES && HV_RATES[cur]) ? HV_RATES[cur] : 1;
    const hvVnd = hv === null ? 0 : hv * rate;

    // Update the row's Com2 base + the →VND conversion hint
    const com2Cell = cell.closest('tr').querySelector('.com2-cell');
    if (com2Cell) com2Cell.dataset.base = hvVnd;
    let hint = cell.querySelector('.hv-vnd');
    if (hv !== null && cur !== 'VND') {
        if (!hint) { hint = document.createElement('div'); hint.className = 'hv-vnd'; hint.title = 'Quy đổi VND'; cell.appendChild(hint); }
        hint.textContent = fmtShort(hvVnd);
    } else if (hint) {
        hint.remove();
    }

    recomputeCom2();
    savePakdApi({invoice_id: invId, update_hv: true, com2_hv: hv, com2_hv_currency: cur});
}

// Enable/disable a row's HV input based on its current EBT (Com2 requires EBT >= 20%).
// ebtVal: number or null. Tier is auto-computed from the ratio; Com2 stops counting until EBT >= 20%.
function syncTierAvailability(tr, ebtVal) {
    if (!tr) return;
    const ok = ebtVal !== null && !isNaN(ebtVal) && ebtVal >= 20;
    const hvInput = tr.querySelector('.hv-input');
    const hvCur = tr.querySelector('.hv-cur');
    if (hvInput) hvInput.disabled = !ok;
    if (hvCur) hvCur.disabled = !ok;
    const hvWrap = tr.querySelector('.hv-wrap');
    if (hvWrap) { if (ok) hvWrap.removeAttribute('title'); else hvWrap.title = 'Com2 yêu cầu EBT ≥ 20%'; }
    const cell = tr.querySelector('.com2-cell');
    if (cell) {
        cell.dataset.ebtok = ok ? '1' : '0';
        if (!ok) cell.title = 'Com2 yêu cầu EBT ≥ 20%';
    }
    recomputeCom2();
    // AI Add-on shares the same EBT input — keep its gate + commission in sync.
    syncAiAvailability(tr, ebtVal);
}

// AI Add-on may only be chosen when KPI ≥ 60% (static, in data-baseok), Collection = 1
// (paid), AND EBT ≥ 10% — the conditions from the rule table. Toggle live with EBT.
const AI_MIN_EBT = <?= json_encode((int)MC_AI_MIN_EBT) ?>;
const AI_GATE_MSG = <?= json_encode(MC_AI_GATE_MSG) ?>;
function syncAiAvailability(tr, ebtVal) {
    if (!tr) return;
    const aiSel = tr.querySelector('.ai-select');
    const baseOk = aiSel && aiSel.dataset.baseok === '1';
    const ebtOk = ebtVal !== null && !isNaN(ebtVal) && ebtVal >= AI_MIN_EBT;
    const ok = baseOk && ebtOk;
    if (aiSel) {
        aiSel.disabled = !ok;
        if (ok) aiSel.removeAttribute('title'); else aiSel.title = AI_GATE_MSG;
    }
    const wrap = tr.querySelector('.airev-cell .hv-wrap');
    const revInput = tr.querySelector('.airev-input');
    const revCur = tr.querySelector('.airev-cur');
    if (revInput) revInput.disabled = !ok;
    if (revCur) revCur.disabled = !ok;
    if (wrap) { if (ok) wrap.removeAttribute('title'); else wrap.title = AI_GATE_MSG; }
    // Keep the AI Com cell's EBT in sync so its rate recomputes correctly.
    const aiComCell = tr.querySelector('.aicom-cell');
    if (aiComCell) aiComCell.dataset.ebt = (ebtVal === null || isNaN(ebtVal)) ? '' : ebtVal;
    recomputeAiCom();
    // Yearly Bonus also keys off EBT — keep the Com1 cells' EBT + baserate in sync and refresh.
    const ebtStr = (ebtVal === null || isNaN(ebtVal)) ? '' : ebtVal;
    const ebtKnownLow = ebtVal !== null && !isNaN(ebtVal) && ebtVal < 5;
    [tr.querySelector('.com1-cell'), tr.querySelector('.com1-cell-rec')].forEach(cell => {
        if (!cell) return;
        cell.dataset.ebt = ebtStr;
        if (cell.dataset.paidok === '1') {
            cell.dataset.baserate = ebtKnownLow ? 0 : (cell.dataset.isnew === '1' ? 0.01 : 0.005);
        }
    });
    recomputeCom1();
    recomputeYearlyBonus();
}

function saveQuarterKpi(input) {
    const cell = input.closest('.kpi-q-cell');
    const year = parseInt(cell.dataset.year);
    const quarter = parseInt(cell.dataset.quarter);
    const val = input.value.trim();
    const numVal = val === '' ? null : parseFloat(val);
    if (!year || !quarter) return;

    fetch('/api/quarter_kpi.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({year: year, quarter: quarter, manual_kpi_pct: numVal})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast('KPI Q' + quarter + '/' + year + ' đã lưu — đang tính lại…', true);
            // Reload so all invoices in this quarter use the new KPI% in their Com total
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.error || 'Lỗi lưu KPI', false);
        }
    })
    .catch(err => showToast('Lỗi kết nối: ' + err.message, false));
}

// ── Sale Orders First PO Commission ──
const SO_COM_RATE = <?= SO_COM_RATE ?>;

const SO_MIN_VND_JS = <?= SO_MIN_VND ?>;

function saveFirstPo(select) {
    const soId    = parseInt(select.dataset.soid);
    const isFirst = select.value === '1';
    const row     = select.closest('tr.so-row');
    const comCell = row ? row.querySelector('.so-com-cell') : null;
    const amtVnd  = row ? (parseFloat(row.dataset.vnd) || 0) : 0;
    const qualifies = row && row.dataset.qualifies === '1';

    if (comCell) {
        const com = (isFirst && qualifies) ? amtVnd * SO_COM_RATE : 0;
        comCell.textContent = com > 0 ? fmtFull(com) : '—';
        comCell.style.color = com > 0 ? '#7c3aed' : '#94a3b8';
    }
    recomputeSoCom();

    fetch('/api/so_first_po.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({so_odoo_id: soId, is_first_po: isFirst ? 1 : 0})
    }).then(r => r.json()).then(d => {
        if (d.ok) showToast('Đã lưu', true); else showToast(d.error || 'Lỗi', false);
    }).catch(err => showToast('Lỗi kết nối', false));
}

function recomputeSoCom() {
    let total = 0, firstPoCount = 0;
    document.querySelectorAll('tr.so-row').forEach(row => {
        if (row.dataset.qualifies !== '1') return;
        const sel = row.querySelector('.so-first-po-sel');
        if (sel && sel.value === '1') {
            total += (parseFloat(row.dataset.vnd) || 0) * SO_COM_RATE;
            firstPoCount++;
        }
    });
    // Footer row in SO table
    const foot = document.getElementById('soComTotal');
    if (foot) { foot.textContent = total > 0 ? fmtFull(total) : '—'; foot.style.color = total > 0 ? '#7c3aed' : '#94a3b8'; }
    GRAND_SO_COM = total;
    // First PO summary card
    const card = document.getElementById('ccFirstPoCom');
    if (card) { card.textContent = total > 0 ? fmtShort(total) : '–'; }
    const cnt = document.getElementById('ccFirstPoCount');
    if (cnt) cnt.textContent = firstPoCount;
    const tag = document.getElementById('ccFirstPoTag');
    if (tag) { tag.textContent = total > 0 ? 'Calculated' : 'Chọn First PO'; tag.className = 'cc-tag ' + (total > 0 ? 'tag-ok' : 'tag-na'); }
    // 1st PO breakdown in grand total card
    const firstPoPart = document.getElementById('ccFirstPoPart');
    if (firstPoPart) firstPoPart.style.display = total > 0 ? '' : 'none';
    const firstPoGrandVal = document.getElementById('ccFirstPoGrandVal');
    if (firstPoGrandVal) firstPoGrandVal.textContent = fmtShort(total);
    refreshGrandTotal();
}

function refreshGrandTotal() {
    const grandTotal = document.getElementById('ccGrandTotal');
    if (grandTotal) grandTotal.textContent = fmtShort(GRAND_COM1 + GRAND_COM2 + GRAND_AI + GRAND_SO_COM);
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

document.addEventListener('click', e => {
    if (!e.target.closest('.pakd-wrap')) document.querySelectorAll('.pakd-dd.open').forEach(d => d.classList.remove('open'));
});
</script>
</body>
</html>
