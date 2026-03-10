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

    // 1. Get Key Account IDs from DB
    $key_account_ids = [];
    $res = $conn->query("SELECT odoo_id FROM customers_metadata WHERE is_key_account = 1");
    while ($row = $res->fetch_assoc()) {
        $key_account_ids[] = (int) $row['odoo_id'];
    }

    if (empty($key_account_ids)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // 2. Get Customer Details from Cache
    $customers_res = $odoo->getCustomers(100000);
    $all_customers = $customers_res['customers'];
    $key_accounts_map = [];
    foreach ($all_customers as $c) {
        if (in_array((int) $c['id'], $key_account_ids)) {
            $key_accounts_map[$c['id']] = [
                'id' => $c['id'],
                'name' => $c['name'],
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

    // Convert keys to array and sort if needed
    $data = array_values($key_accounts_map);

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
