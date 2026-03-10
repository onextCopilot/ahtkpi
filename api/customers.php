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

// Handle Toggle Key Account (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['odoo_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing Odoo ID']);
        exit;
    }

    $odoo_id = (int) $data['odoo_id'];

    // Build update dynamic
    $fields = [];
    $params = [];
    $types = "";

    if (isset($data['is_key_account'])) {
        $fields[] = "is_key_account = ?";
        $params[] = (int) $data['is_key_account'];
        $types .= "i";
    }
    if (isset($data['am_bd_id'])) {
        $fields[] = "am_bd_id = ?";
        $params[] = $data['am_bd_id'] ? (int) $data['am_bd_id'] : null;
        $types .= "i";
    }
    if (isset($data['delivery_owners'])) {
        $fields[] = "delivery_owners = ?";
        $params[] = $data['delivery_owners']; // comma separated string or single code
        $types .= "s";
    }
    if (isset($data['account_note'])) {
        $fields[] = "account_note = ?";
        $params[] = $data['account_note'];
        $types .= "s";
    }

    if (empty($fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $sql = "INSERT INTO customers_metadata (odoo_id, " . implode(", ", array_map(fn($f) => explode(" = ", $f)[0], $fields)) . ") 
            VALUES (?, " . implode(", ", array_fill(0, count($fields), "?")) . ") 
            ON DUPLICATE KEY UPDATE " . implode(", ", $fields);

    $stmt = $conn->prepare($sql);

    // Merge parameters for INSERT AND UPDATE
    $full_params = array_merge([$odoo_id], $params, $params);
    $full_types = "i" . $types . $types;

    $stmt->bind_param($full_types, ...$full_params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// Get parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$country = isset($_GET['country']) ? $_GET['country'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$is_key_account_filter = isset($_GET['is_key_account']) ? $_GET['is_key_account'] : '';

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
    $result = $odoo->getCustomers(100000, 0, $filters); // Get ALL filtered customers for Key Account filter logic

    $customers = $result['customers'];
    $totalCount = $result['total'];

    // Get key accounts from DB
    $key_accounts = [];
    $metadata_res = $conn->query("SELECT odoo_id, is_key_account FROM customers_metadata WHERE is_key_account = 1");
    while ($row = $metadata_res->fetch_assoc()) {
        $key_accounts[] = (int) $row['odoo_id'];
    }

    // Append flag and filter locally
    foreach ($customers as &$customer) {
        $customer['is_key_account'] = in_array((int) $customer['id'], $key_accounts);
    }

    if ($is_key_account_filter === '1') {
        $customers = array_values(array_filter($customers, function ($c) {
            return $c['is_key_account'];
        }));
        $totalCount = count($customers);
    }

    // Apply pagination manually after all filtering
    $paginatedCustomers = array_slice($customers, $offset, $limit);

    echo json_encode([
        'success' => true,
        'data' => $paginatedCustomers,
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
