<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../libs/OdooAPI.php';

// Get parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$country = isset($_GET['country']) ? $_GET['country'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    $odoo = new OdooAPI();

    // Build filters array for cache-based filtering
    $filters = [
        'search' => $search,
        'city' => $city,
        'country' => $country,
        'status' => $status
    ];

    // Calculate offset
    $offset = ($page - 1) * $limit;

    // Get customers with pagination from cache
    $result = $odoo->getCustomers($limit, $offset, $filters);

    $customers = $result['customers'];
    $totalCount = $result['total'];

    echo json_encode([
        'success' => true,
        'data' => $customers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'totalPages' => ceil($totalCount / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
