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
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    $odoo = new OdooAPI();

    // Build filters array for cache-based filtering
    $filters = [
        'search' => $search,
        'status' => $status
    ];

    // Calculate offset
    $offset = ($page - 1) * $limit;

    // Get invoices with pagination from cache
    $result = $odoo->getInvoices($limit, $offset, $filters);

    $invoices = $result['invoices'];
    $totalCount = $result['total'];

    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'totalPages' => ceil($totalCount / $limit)
        ]
    ]);
} catch (Exception $e) {
    // Return empty list on error instead of breaking everything, but log it
    // Or return 500
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
