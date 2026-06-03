<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/OdooAPI.php';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$my_only = ($_GET['my_only'] ?? '1') === '1';
$year = intval($_GET['year'] ?? 0);
$month = intval($_GET['month'] ?? 0);

try {
    $odoo = new OdooAPI();

    // Get current user's email to match against Odoo user
    $current_user_email = null;
    $stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $current_user_email = $row['email'];
    }

    // Build Odoo domain
    $domain = [['state', '!=', 'cancel']]; // Exclude cancelled by default

    // Filter by status
    if ($status === 'draft') {
        $domain[] = ['state', '=', 'draft'];
    } elseif ($status === 'sale') {
        $domain[] = ['state', '=', 'sale'];
    } elseif ($status === 'done') {
        $domain[] = ['state', '=', 'done'];
    }

    // Filter by year / month on date_order (server-side range)
    if ($year > 0) {
        if ($month >= 1 && $month <= 12) {
            $startY = $year;
            $startM = $month;
            $endY = $month === 12 ? $year + 1 : $year;
            $endM = $month === 12 ? 1 : $month + 1;
        } else {
            $startY = $year;
            $startM = 1;
            $endY = $year + 1;
            $endM = 1;
        }
        $start = sprintf('%04d-%02d-01 00:00:00', $startY, $startM);
        $end = sprintf('%04d-%02d-01 00:00:00', $endY, $endM);
        $domain[] = ['date_order', '>=', $start];
        $domain[] = ['date_order', '<', $end];
    }

    // Filter by current Odoo user (salesperson)
    if ($my_only && $current_user_email) {
        $odoo_users = $odoo->searchRead('res.users', [['login', '=', $current_user_email]], ['id'], 1);
        if (!empty($odoo_users[0]['id'])) {
            $domain[] = ['user_id', '=', $odoo_users[0]['id']];
        }
    }

    $fields = [
        'id',
        'name',
        'partner_id',
        'date_order',
        'user_id',
        'amount_total',
        'currency_id',
        'state',
        'client_order_ref',
        'validity_date',
        'commitment_date',
        'team_id',
        'note'
    ];

    // Fetch with limit — Odoo handles ordering server-side
    $orders = $odoo->searchRead('sale.order', $domain, $fields, 0, 0);

    if (!is_array($orders))
        $orders = [];

    // Apply search in memory
    if ($search !== '') {
        $sl = strtolower($search);
        $orders = array_values(array_filter($orders, function ($o) use ($sl) {
            $name = strtolower($o['name'] ?? '');
            $partner = strtolower(is_array($o['partner_id']) ? ($o['partner_id'][1] ?? '') : '');
            $ref = strtolower($o['client_order_ref'] ?? '');
            return str_contains($name, $sl) || str_contains($partner, $sl) || str_contains($ref, $sl);
        }));
    }

    // Sort by date DESC in memory
    usort($orders, fn($a, $b) => strcmp($b['date_order'] ?? '', $a['date_order'] ?? ''));

    echo json_encode([
        'success' => true,
        'data' => $orders,
        'total' => count($orders),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
