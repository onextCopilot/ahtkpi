<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || (empty($_SESSION['can_view_invoice']) && empty($_SESSION['is_am_bd']) && $_SESSION['role'] !== 'admin')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../config/config.php';

try {
    $odoo = new OdooAPI();
    if (isset($_GET['force_refresh'])) {
        $odoo->refreshInvoiceCache();
    }

    // Auto-migrate if column is missing (handles live DB update)
    $check_order_index = $conn->query("SHOW COLUMNS FROM customers_metadata LIKE 'order_index'");
    if ($check_order_index && $check_order_index->num_rows == 0) {
        $conn->query("ALTER TABLE customers_metadata ADD COLUMN order_index INT DEFAULT 0");
    }

    // 1. Get Key Account IDs and Metadata from DB (including latest note from history)
    $key_account_metadata = [];
    $sql = "SELECT cm.odoo_id, cm.am_bd_id, cm.delivery_owners, 
                   COALESCE(latest_note.note_content, cm.account_note) as account_note,
                   latest_note.author_name,
                   latest_note.created_at as note_time,
                   cm.company_source, cm.active_projects, cm.order_index
            FROM customers_metadata cm
            LEFT JOIN (
                SELECT cn1.odoo_id, cn1.note_content, cn1.created_at, u.full_name as author_name
                FROM customer_notes cn1
                JOIN users u ON cn1.user_id = u.id
                WHERE cn1.id = (SELECT MAX(id) FROM customer_notes cn2 WHERE cn2.odoo_id = cn1.odoo_id)
            ) latest_note ON cm.odoo_id = latest_note.odoo_id
            WHERE cm.is_key_account = 1
            ORDER BY cm.order_index ASC";

    $res = $conn->query($sql);
    if (!$res) {
        throw new Exception($conn->error);
    }

    while ($row = $res->fetch_assoc()) {
        $key_account_metadata[(int) $row['odoo_id']] = $row;
    }
    $key_account_ids = array_keys($key_account_metadata);

    // 2. Get AM/BD list
    $am_bd_list = [];
    $am_res = $conn->query("SELECT id, full_name FROM users WHERE is_am_bd = 1 OR role = 'admin' ORDER BY full_name ASC");
    while ($row = $am_res->fetch_assoc()) {
        $am_bd_list[] = $row;
    }

    if (empty($key_account_ids)) {
        echo json_encode(['success' => true, 'data' => [], 'am_bd_list' => $am_bd_list]);
        exit;
    }

    // 3. Get Customer Details from Cache
    $customers_res = $odoo->getCustomers(100000);
    $all_customers = $customers_res['customers'];
    $key_accounts_map = [];
    foreach ($all_customers as $c) {
        if (isset($key_account_metadata[(int) $c['id']])) {
            $meta = $key_account_metadata[(int) $c['id']];
            $key_accounts_map[$c['id']] = [
                'id' => $c['id'],
                'name' => $c['name'],
                'am_bd_id' => $meta['am_bd_id'],
                'delivery_owners' => $meta['delivery_owners'],
                'account_note' => !empty($meta['account_note']) ? $meta['account_note'] : ($c['comment'] ?? ''),
                'author_name' => $meta['author_name'] ?? '',
                'note_time' => $meta['note_time'] ?? '',
                'company_source' => $meta['company_source'],
                'active_projects' => $meta['active_projects'],
                'order_index' => $meta['order_index'],
                'stats' => [
                    'monthly' => [],
                    'quarterly' => []
                ]
            ];
        }
    }

    // 3. Get Invoices from Cache
    $invoices_res = $odoo->getInvoices(100000);
    $all_invoices = $invoices_res['invoices'];

    $current_usd_rate = $odoo->getRate('USD', date('Y-m-d'));

    foreach ($all_invoices as $inv) {
        $commercial_partner_id = isset($inv['commercial_partner_id']) && is_array($inv['commercial_partner_id']) ? $inv['commercial_partner_id'][0] : null;
        $direct_partner_id = isset($inv['partner_id']) && is_array($inv['partner_id']) ? $inv['partner_id'][0] : null;
        $partner_id = $commercial_partner_id ?: $direct_partner_id;

        if ($partner_id && isset($key_accounts_map[$partner_id])) {
            // Only count posted invoices for revenue
            if ($inv['state'] !== 'posted')
                continue;

            // EXCLUDE internal invoices as requested
            if (($inv['x_studio_invoice_type_1'] ?? '') === 'Internal')
                continue;

            $date_str = $inv['invoice_date'] ?: ($inv['date'] ?? null);

            $amount_vnd = (float) ($inv['amount_total_signed'] ?? 0);

            // ACCURACY FIX: 
            // 1. If invoice is already USD, use amount_total for exact decimals.
            // 2. If NOT USD (VND, EUR, etc.), Odoo converts to VND in amount_total_signed.
            //    We convert that VND to USD using the Odoo exchange rate on the INVOICE DATE.
            if (($inv['currency_id'][1] ?? '') === 'USD') {
                $amount_usd = (float) ($inv['amount_total'] ?? 0);
                // Adjust sign for credit notes if necessary
                if (($inv['move_type'] ?? '') === 'out_refund') {
                    $amount_usd = -$amount_usd;
                }
            } else {
                $historical_usd_rate = $odoo->getRate('USD', $date_str);
                $historical_vnd_rate = $odoo->getRate('VND', $date_str);
                $amount_usd = $historical_vnd_rate > 0 ? $amount_vnd * ($historical_usd_rate / $historical_vnd_rate) : 0;
            }

            $date = new DateTime($date_str);
            $year = $date->format('Y');
            $month = $date->format('m');
            $month_key = "$year-$month";

            $q = ceil((int) $month / 3);
            $quarter_key = "$year-Q$q";

            // Monthly stats
            if (!isset($key_accounts_map[$partner_id]['stats']['monthly'][$month_key])) {
                $key_accounts_map[$partner_id]['stats']['monthly'][$month_key] = 0;
            }
            $key_accounts_map[$partner_id]['stats']['monthly'][$month_key] += $amount_vnd;

            // USD Monthly stats
            if (!isset($key_accounts_map[$partner_id]['stats']['monthly_usd'][$month_key])) {
                $key_accounts_map[$partner_id]['stats']['monthly_usd'][$month_key] = 0;
            }
            $key_accounts_map[$partner_id]['stats']['monthly_usd'][$month_key] += $amount_usd;

            // Quarterly stats
            if (!isset($key_accounts_map[$partner_id]['stats']['quarterly'][$quarter_key])) {
                $key_accounts_map[$partner_id]['stats']['quarterly'][$quarter_key] = 0;
            }
            $key_accounts_map[$partner_id]['stats']['quarterly'][$quarter_key] += $amount_vnd;

            // USD Quarterly stats
            if (!isset($key_accounts_map[$partner_id]['stats']['quarterly_usd'][$quarter_key])) {
                $key_accounts_map[$partner_id]['stats']['quarterly_usd'][$quarter_key] = 0;
            }
            $key_accounts_map[$partner_id]['stats']['quarterly_usd'][$quarter_key] += $amount_usd;
        }
    }

    // 4. Calculate total volume per year from ALL Odoo invoices (Global Revenue)
    $total_volume_vnd_by_year = [];
    $total_volume_usd_by_year = [];
    $internal_revenue_vnd_by_year = [];
    $internal_revenue_usd_by_year = [];

    foreach ($all_invoices as $inv) {
        // Only count posted customer invoices
        if ($inv['state'] !== 'posted' || ($inv['move_type'] ?? '') !== 'out_invoice')
            continue;

        $date_str = $inv['invoice_date'] ?: ($inv['date'] ?? null);
        if (!$date_str)
            continue;

        $amount_vnd = abs((float) ($inv['amount_total_signed'] ?? 0));

        if (($inv['currency_id'][1] ?? '') === 'USD') {
            $amount_usd = (float) ($inv['amount_total'] ?? 0);
        } else {
            $historical_usd_rate = $odoo->getRate('USD', $date_str);
            $historical_vnd_rate = $odoo->getRate('VND', $date_str);
            $amount_usd = $historical_vnd_rate > 0 ? $amount_vnd * ($historical_usd_rate / $historical_vnd_rate) : 0;
        }

        $inv_year = date('Y', strtotime($date_str));

        // EXCLUDE internal invoices as requested for Total Volume
        if (($inv['x_studio_invoice_type_1'] ?? '') === 'Internal') {
            if (!isset($internal_revenue_vnd_by_year[$inv_year])) {
                $internal_revenue_vnd_by_year[$inv_year] = 0;
                $internal_revenue_usd_by_year[$inv_year] = 0;
            }
            $internal_revenue_vnd_by_year[$inv_year] += $amount_vnd;
            $internal_revenue_usd_by_year[$inv_year] += $amount_usd;
            continue;
        }

        if (!isset($total_volume_vnd_by_year[$inv_year])) {
            $total_volume_vnd_by_year[$inv_year] = 0;
            $total_volume_usd_by_year[$inv_year] = 0;
        }
        $total_volume_vnd_by_year[$inv_year] += $amount_vnd;
        $total_volume_usd_by_year[$inv_year] += $amount_usd;
    }

    $data = array_values($key_accounts_map);

    $current_usd = $odoo->getRate('USD', date('Y-m-d'));
    $current_vnd = $odoo->getRate('VND', date('Y-m-d'));
    $display_rate = $current_usd > 0 ? $current_vnd / $current_usd : 25400;

    echo json_encode([
        'success' => true,
        'data' => $data,
        'am_bd_list' => $am_bd_list,
        'total_volume_vnd_by_year' => $total_volume_vnd_by_year,
        'total_volume_usd_by_year' => $total_volume_usd_by_year,
        'internal_total_res' => $internal_revenue_vnd_by_year,
        'internal_total_usd_res' => $internal_revenue_usd_by_year,
        'usd_rate' => $display_rate,
        'api_version' => '2.6'
        ], JSON_FORCE_OBJECT);
   // ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
