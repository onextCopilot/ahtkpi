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

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 25)));
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$my_only = ($_GET['my_only'] ?? '1') === '1';

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

    // Filter by current Odoo user (salesperson)
    if ($my_only && $_SESSION['role'] !== 'admin' && $current_user_email) {
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
        'note',
        'x_studio_project_code'
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
            $proj = strtolower($o['x_studio_project_code'] ?? '');
            return str_contains($name, $sl) || str_contains($partner, $sl) || str_contains($ref, $sl) || str_contains($proj, $sl);
        }));
    }

    // Sort by date DESC in memory
    usort($orders, fn($a, $b) => strcmp($b['date_order'] ?? '', $a['date_order'] ?? ''));

    $total = count($orders);
    $offset = ($page - 1) * $limit;
    $paged_orders = array_slice($orders, $offset, $limit);

    echo json_encode([
        'success' => true,
        'data' => $paged_orders,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
