<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/OdooAPI.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || (empty($_SESSION['can_view_invoice']) && empty($_SESSION['is_am_bd']) && $_SESSION['role'] !== 'admin')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    set_time_limit(300); // Allow up to 5 minutes for Odoo sync
    $odoo = new OdooAPI();
    $cache_file = __DIR__ . '/../cache/key_accounts_snapshot.cache.php';
    $is_refresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] == '1';

    // Auto-migrate if column is missing (handles live DB update) - Always run this
    $check_order_index = $conn->query("SHOW COLUMNS FROM customers_metadata LIKE 'order_index'");
    if ($check_order_index && $check_order_index->num_rows == 0) {
        $conn->query("ALTER TABLE customers_metadata ADD COLUMN order_index INT DEFAULT 0");
    }

    // If not refreshing, try to serve from cache
    if (!$is_refresh && file_exists($cache_file)) {
        $cache_content = file_get_contents($cache_file);
        $cache_data = json_decode(str_replace('<?php exit; ?>', '', $cache_content), true);
        if ($cache_data) {
            // ALWAYS refresh metadata from DB specifically, even when using cached stats
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
            
            $db_res = $conn->query($sql);
            $db_meta = [];
            while($row = $db_res->fetch_assoc()) {
                $db_meta[(int)$row['odoo_id']] = $row;
            }

            // Merge DB Metadata into Cached Stats.
            // Only keep customers that are still key accounts (present in $db_meta),
            // so toggling a customer OFF removes it from the cached list immediately.
            $merged = [];
            $cached_ids = [];
            foreach ($cache_data['data'] as $customer) {
                $cached_ids[(int)$customer['id']] = true;
                if (isset($db_meta[(int)$customer['id']])) {
                    $m = $db_meta[(int)$customer['id']];
                    $customer['am_bd_id'] = $m['am_bd_id'];
                    $customer['delivery_owners'] = $m['delivery_owners'];
                    $customer['company_source'] = $m['company_source'];
                    $customer['active_projects'] = $m['active_projects'];
                    $customer['order_index'] = $m['order_index'];
                    $customer['account_note'] = !empty($m['account_note']) ? $m['account_note'] : $customer['account_note'];
                    $customer['author_name'] = $m['author_name'] ?? $customer['author_name'];
                    $customer['note_time'] = $m['note_time'] ?? $customer['note_time'];
                    $merged[] = $customer;
                }
            }

            // Detect key accounts turned ON since the cache was built (in DB but not in cache).
            // If any exist, the cache is stale for the list: fall through to rebuild
            // (from local caches, no Odoo refresh) so the new accounts appear with stats.
            $missing_new = array_diff(array_keys($db_meta), array_keys($cached_ids));

            if (empty($missing_new)) {
                // Re-sort by order_index to match the DB ordering
                usort($merged, fn($a, $b) => ((int)($a['order_index'] ?? 0)) <=> ((int)($b['order_index'] ?? 0)));
                $cache_data['data'] = $merged;

                $cache_data['from_cache'] = true;
                $cache_data['cache_time'] = date('Y-m-d H:i:s', filemtime($cache_file));
                echo json_encode($cache_data);
                exit;
            }
            // else: drop through to full rebuild below
        }
    }

    if ($is_refresh) {
        $odoo->refreshInvoiceCache();
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
        $response = ['success' => true, 'data' => [], 'am_bd_list' => $am_bd_list];
        echo json_encode($response);
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

            if (($inv['currency_id'][1] ?? '') === 'USD') {
                $amount_usd = (float) ($inv['amount_total'] ?? 0);
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

    $final_response = [
        'success' => true,
        'data' => $data,
        'am_bd_list' => $am_bd_list,
        'total_volume_vnd_by_year' => $total_volume_vnd_by_year,
        'total_volume_usd_by_year' => $total_volume_usd_by_year,
        'internal_total_res' => $internal_revenue_vnd_by_year,
        'internal_total_usd_res' => $internal_revenue_usd_by_year,
        'usd_rate' => $display_rate,
        'api_version' => '2.8'
    ];

    // Save to Cache
    if (!is_dir(dirname($cache_file))) {
        mkdir(dirname($cache_file), 0777, true);
    }
    file_put_contents($cache_file, '<?php exit; ?>' . json_encode($final_response));
    
    echo json_encode($final_response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
