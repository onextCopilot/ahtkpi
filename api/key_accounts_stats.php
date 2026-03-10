<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['can_view_invoice'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../config/config.php';

try {
    $odoo = new OdooAPI();

    // 1. Get Key Account IDs and Metadata from DB (including latest note from history)
    $key_account_metadata = [];
    $sql = "SELECT cm.odoo_id, cm.am_bd_id, cm.delivery_owners, 
                   COALESCE(latest_note.note_content, cm.account_note) as account_note,
                   latest_note.author_name,
                   latest_note.created_at as note_time,
                   cm.company_source, cm.active_projects 
            FROM customers_metadata cm
            LEFT JOIN (
                SELECT cn1.odoo_id, cn1.note_content, cn1.created_at, u.full_name as author_name
                FROM customer_notes cn1
                JOIN users u ON cn1.user_id = u.id
                WHERE cn1.id = (SELECT MAX(id) FROM customer_notes cn2 WHERE cn2.odoo_id = cn1.odoo_id)
            ) latest_note ON cm.odoo_id = latest_note.odoo_id
            WHERE cm.is_key_account = 1";

    $res = $conn->query($sql);
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

    foreach ($all_invoices as $inv) {
        $partner_id = isset($inv['partner_id']) && is_array($inv['partner_id']) ? $inv['partner_id'][0] : null;

        if ($partner_id && isset($key_accounts_map[$partner_id])) {
            // Only count posted/paid invoices for revenue
            if ($inv['state'] !== 'posted')
                continue;

            $amount = (float) ($inv['amount_total_signed'] ?? 0);
            $date_str = $inv['invoice_date'] ?: $inv['date'];
            if (!$date_str)
                continue;

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
            $key_accounts_map[$partner_id]['stats']['monthly'][$month_key] += $amount;

            // Quarterly stats
            if (!isset($key_accounts_map[$partner_id]['stats']['quarterly'][$quarter_key])) {
                $key_accounts_map[$partner_id]['stats']['quarterly'][$quarter_key] = 0;
            }
            $key_accounts_map[$partner_id]['stats']['quarterly'][$quarter_key] += $amount;
        }
    }

    // 4. Calculate total volume per year from 'debts' table (The "All Debts" module source)
    $total_volume_vnd_by_year = [];
    $debt_res = $conn->query("SELECT amount, currency, invoice_date, odoo_invoice_id FROM debts");
    if ($debt_res) {
        $odoo_map = $odoo->getInvoiceMap();
        while ($d_row = $debt_res->fetch_assoc()) {
            $amount = (float) $d_row['amount'];
            $curr = $d_row['currency'] ?: 'USD';
            $date_str = $d_row['invoice_date'];
            $year = $date_str ? date('Y', strtotime($date_str)) : 'Unknown';
            $oid = $d_row['odoo_invoice_id'];

            $vnd_value = 0;
            if (!empty($oid) && isset($odoo_map[$oid])) {
                $odoo_inv = $odoo_map[$oid];
                $odoo_total = (float) $odoo_inv['amount_total'];
                $odoo_signed = abs((float) $odoo_inv['amount_total_signed']);
                if ($odoo_total > 0) {
                    $vnd_value = $amount * ($odoo_signed / $odoo_total);
                }
            }
            if ($vnd_value <= 0) {
                $rate = $odoo->getRate($curr, $date_str ?: date('Y-m-d'));
                $vnd_value = ($rate > 0) ? ($amount / $rate) : $amount;
            }

            if (!isset($total_volume_vnd_by_year[$year]))
                $total_volume_vnd_by_year[$year] = 0;
            $total_volume_vnd_by_year[$year] += $vnd_value;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'am_bd_list' => $am_bd_list,
        'total_volume_vnd_by_year' => $total_volume_vnd_by_year
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
