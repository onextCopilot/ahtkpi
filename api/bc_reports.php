<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../libs/OdooAPI.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');

// Ensure db config is included for manual permission check
require_once __DIR__ . '/../config/config.php';

$allowed_bcs = [];
if (!$is_admin) {
    $bc_chk_stmt = $conn->prepare("SELECT bc_name FROM bc_permissions WHERE user_id = ?");
    if ($bc_chk_stmt) {
        $bc_chk_stmt->bind_param("i", $_SESSION['user_id']);
        $bc_chk_stmt->execute();
        $bc_res = $bc_chk_stmt->get_result();
        while ($bc_row = $bc_res->fetch_assoc()) {
            $allowed_bcs[] = $bc_row['bc_name'];
        }
        $bc_chk_stmt->close();
    }

    if (empty($allowed_bcs)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have access to any BCs.']);
        exit();
    }
}

$years = isset($_GET['year']) ? array_filter(explode(',', $_GET['year'])) : [];
$months = isset($_GET['month']) ? array_filter(explode(',', $_GET['month'])) : [];
$quarters = isset($_GET['quarter']) ? array_filter(explode(',', $_GET['quarter'])) : [];
$payment_filter = isset($_GET['payment_state']) ? array_filter(explode(',', $_GET['payment_state'])) : [];
$bc_filter = isset($_GET['bc']) ? array_filter(explode(',', $_GET['bc'])) : [];
$state_filter = isset($_GET['state']) ? array_filter(explode(',', $_GET['state'])) : [];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$api = new OdooAPI();

try {
    // 1. Fetch move lines that have a BC branch
    $domain = [
        ['move_id.move_type', '=', 'out_invoice'],
        ['branch_id.name', 'ilike', 'bc']
    ];

    if (!empty($bc_filter)) {
        $branch_domain = ['|'];
        foreach ($bc_filter as $b) {
            $branch_domain[] = ['branch_id.name', '=', $b];
        }
        if (count($bc_filter) > 1) {
            // Add more ORs if needed, actually 'in' operator is better
            $domain[] = ['branch_id.name', 'in', $bc_filter];
        } else {
            $domain[] = ['branch_id.name', '=', $bc_filter[0]];
        }
    }

    if (!empty($years)) {
        $min_year = min($years);
        $max_year = max($years);
        $start_date = "$min_year-01-01";
        $end_date = "$max_year-12-31";
        $domain[] = ['date', '>=', $start_date];
        $domain[] = ['date', '<=', $end_date];
    }

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
        $inv_domain = [['id', 'in', $move_ids]];
        if (!empty($search)) {
            $inv_domain[] = ['|', ['name', 'ilike', $search], ['partner_id.name', 'ilike', $search]];
        }
        $invoices = $api->searchRead('account.move', $inv_domain, ['id', 'name', 'invoice_date', 'date', 'partner_id', 'amount_total', 'amount_total_signed', 'currency_id', 'state', 'payment_state', 'x_studio_invoice_type_1'], 0, 0);
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

        $state = $inv['state'] ?? '';
        if ($state === 'cancel')
            continue;

        // Apply State Filter
        if (!empty($state_filter) && !in_array($state, $state_filter)) {
            continue;
        }

        // Apply Month/Quarter Filters
        $inv_date_str = $inv['invoice_date'] ?: ($inv['date'] ?: '');
        if (empty($inv_date_str))
            continue;

        $inv_time = strtotime($inv_date_str);
        $inv_year = (int) date('Y', $inv_time);
        $inv_month = (int) date('m', $inv_time);
        $inv_quarter = (int) ceil($inv_month / 3);

        if (!empty($years) && !in_array($inv_year, $years))
            continue;
        if (!empty($months) && !in_array($inv_month, $months))
            continue;
        if (!empty($quarters) && !in_array($inv_quarter, $quarters))
            continue;

        if (!empty($payment_filter) && !in_array('all', $payment_filter)) {
            $current_payment_state = $inv['payment_state'] ?? '';
            $is_paid = in_array($current_payment_state, ['paid', 'in_payment']);
            
            $match_payment = false;
            if (in_array('paid', $payment_filter) && $is_paid) $match_payment = true;
            if (in_array('not_paid', $payment_filter) && !$is_paid) $match_payment = true;
            
            if (!$match_payment) continue;
        }

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
            if (!empty($bc_filter) && !in_array($b, $bc_filter)) {
                continue;
            }

            // Check permissions for non-admin
            if (!$is_admin && !in_array($b, $allowed_bcs)) {
                continue;
            }

            if (!isset($grouped_data[$b])) {
                $grouped_data[$b] = [
                    'branch' => $b,
                    'totalVnd' => 0,
                    'month_groups' => []
                ];
            }

            if (!isset($grouped_data[$b]['month_groups'][$inv_month])) {
                $grouped_data[$b]['month_groups'][$inv_month] = [
                    'month' => $inv_month,
                    'label' => "Tháng " . $inv_month,
                    'totalVnd' => 0,
                    'invoices' => []
                ];
            }

            $grouped_data[$b]['month_groups'][$inv_month]['invoices'][] = [
                'id' => $inv['id'],
                'name' => $inv['name'],
                'type' => is_array($inv['x_studio_invoice_type_1']) ? $inv['x_studio_invoice_type_1'][1] : ($inv['x_studio_invoice_type_1'] ?? ''),
                'date' => $inv_date_str,
                'customer' => is_array($inv['partner_id']) ? $inv['partner_id'][1] : '',
                'state' => $state,
                'payment_state' => $inv['payment_state'] ?? '',
                'currency' => $currencyName,
                'amount_total' => $inv['amount_total'],
                'amount_total_signed' => $amount_vnd,
            ];
            $grouped_data[$b]['month_groups'][$inv_month]['totalVnd'] += $amount_vnd;
            $grouped_data[$b]['totalVnd'] += $amount_vnd;
        }
    }

    // Sort branches by name
    ksort($grouped_data);
    $response = [];
    foreach ($grouped_data as $b_name => $b_data) {
        // Sort months within each branch (Descending: latest month first)
        krsort($b_data['month_groups']);
        $b_data['month_groups'] = array_values($b_data['month_groups']);
        $response[] = $b_data;
    }

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
