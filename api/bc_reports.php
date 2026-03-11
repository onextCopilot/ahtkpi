<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../libs/OdooAPI.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : 0; // 0 = all
$quarter = isset($_GET['quarter']) ? (int) $_GET['quarter'] : 0; // 0 = all
$bc_filter = isset($_GET['bc']) ? $_GET['bc'] : '';

$api = new OdooAPI();

try {
    // 1. Fetch move lines that have a BC branch
    // Because we might have a lot of lines, we should perhaps filter by year in Odoo?
    // Move lines have date `date` matching the invoice `date` (accounting date) or `move_id.invoice_date`?
    // Let's just fetch all lines that match 'BC' and 'out_invoice'. Since there might be thousands, 
    // maybe it's better to fetch by year.
    $domain = [
        ['move_id.move_type', '=', 'out_invoice'],
        ['branch_id.name', 'ilike', 'bc']
    ];

    if ($year) {
        // filter by date in year
        // invoice_date or date ? usually `date`
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        $domain[] = ['date', '>=', $start_date];
        $domain[] = ['date', '<=', $end_date];
    }

    // if ($month) { ... could filter here, but we can do it in memory easily }

    $fields = ['move_id', 'branch_id'];
    $lines = $api->searchRead('account.move.line', $domain, $fields, 0, 0);

    // Group branches by move_id
    $move_to_branches = [];
    foreach ($lines as $line) {
        $move_id = $line['move_id'][0];
        $branch_name = $line['branch_id'][1];

        if (!isset($move_to_branches[$move_id])) {
            $move_to_branches[$move_id] = [];
        }
        if (!in_array($branch_name, $move_to_branches[$move_id])) {
            $move_to_branches[$move_id][] = $branch_name;
        }
    }

    $move_ids = array_keys($move_to_branches);

    // Fetch actual invoice data directly from Odoo for freshness
    $invoices = [];
    if (!empty($move_ids)) {
        $invoices = $api->searchRead('account.move', [
            ['id', 'in', $move_ids]
        ], ['id', 'name', 'invoice_date', 'date', 'partner_id', 'amount_total', 'amount_total_signed', 'currency_id', 'state', 'payment_state', 'x_studio_invoice_type_1'], 0, 0);
    }

    $allInvoices = [];
    foreach ($invoices as $inv) {
        $allInvoices[$inv['id']] = $inv;
    }

    $ratesData = $api->getCurrencies();
    $rateVnd = $ratesData['VND']['rate'] ?? 25000;

    $grouped_data = [];

    foreach ($move_to_branches as $move_id => $branches) {
        if (!isset($allInvoices[$move_id])) {
            continue;
        }
        $inv = $allInvoices[$move_id];

        // Exclude internal invoices like in sale_reports_admin? Yes, usually.
        $isInternal = false;
        if (!empty($inv['x_studio_invoice_type_1'])) {
            $typeStr = is_array($inv['x_studio_invoice_type_1']) ? $inv['x_studio_invoice_type_1'][1] : $inv['x_studio_invoice_type_1'];
            if (stripos($typeStr, 'internal') !== false) {
                $isInternal = true;
            }
        }
        if ($isInternal)
            continue;

        $state = $inv['state'] ?? '';
        if ($state === 'cancel')
            continue;

        // Apply Month/Quarter Filters
        $inv_date_str = $inv['invoice_date'] ?: ($inv['date'] ?: '');
        if (empty($inv_date_str))
            continue;

        $inv_time = strtotime($inv_date_str);
        $inv_year = (int) date('Y', $inv_time);
        $inv_month = (int) date('m', $inv_time);
        $inv_quarter = ceil($inv_month / 3);

        if ($year && $inv_year !== $year)
            continue;
        if ($month && $inv_month !== $month)
            continue;
        if ($quarter && $inv_quarter !== $quarter)
            continue;

        // Calculate VND amount
        $currencyName = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
        $rateSource = $ratesData[$currencyName]['rate'] ?? 1;

        $amount_vnd = isset($inv['amount_total_signed']) ? (float) $inv['amount_total_signed'] : 0;
        if ($amount_vnd == 0 && ($inv['amount_total'] ?? 0) > 0) {
            $amount_vnd = $inv['amount_total'] * ($rateVnd / $rateSource);
        }

        // We assign the entire invoice amount to this BC branch
        foreach ($branches as $b) {
            // Check specific BC filter if any
            if ($bc_filter && stripos($b, $bc_filter) === false) {
                continue;
            }

            if (!isset($grouped_data[$b])) {
                $grouped_data[$b] = [
                    'branch' => $b,
                    'totalVnd' => 0,
                    'invoices' => []
                ];
            }

            $grouped_data[$b]['invoices'][] = [
                'id' => $inv['id'],
                'name' => $inv['name'],
                'date' => $inv_date_str,
                'customer' => is_array($inv['partner_id']) ? $inv['partner_id'][1] : '',
                'state' => $state,
                'payment_state' => $inv['payment_state'] ?? '',
                'currency' => $currencyName,
                'amount_total' => $inv['amount_total'],
                'amount_total_signed' => $amount_vnd,
            ];
            $grouped_data[$b]['totalVnd'] += $amount_vnd;
        }
    }

    // Sort the response alphabetically by Branch name
    ksort($grouped_data);
    $response = array_values($grouped_data);

    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
