<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $odoo_id = isset($_GET['odoo_id']) ? (int) $_GET['odoo_id'] : 0;
    if (!$odoo_id) {
        echo json_encode(['success' => false, 'error' => 'Missing Odoo ID']);
        exit;
    }

    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
    $offset = ($page - 1) * $limit;

    $year = isset($_GET['year']) ? (int) $_GET['year'] : '';
    $month = isset($_GET['month']) ? (int) $_GET['month'] : '';
    $quarter = isset($_GET['quarter']) ? (int) $_GET['quarter'] : '';

    $where = ["n.odoo_id = ?"];
    $params = [$odoo_id];
    $types = "i";

    if ($year) {
        $where[] = "YEAR(n.created_at) = ?";
        $params[] = $year;
        $types .= "i";
    }
    if ($month) {
        $where[] = "MONTH(n.created_at) = ?";
        $params[] = $month;
        $types .= "i";
    }
    if ($quarter) {
        $where[] = "QUARTER(n.created_at) = ?";
        $params[] = $quarter;
        $types .= "i";
    }

    $where_clause = implode(" AND ", $where);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM customer_notes n WHERE $where_clause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];

    // Get paginated notes
    $sql = "SELECT n.*, u.full_name as author 
            FROM customer_notes n 
            JOIN users u ON n.user_id = u.id 
            WHERE $where_clause 
            ORDER BY n.created_at DESC 
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $notes,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]
    ]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $odoo_id = (int) $data['odoo_id'];
    $content = trim($data['content']);
    $user_id = $_SESSION['user_id'];

    if (!$odoo_id || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO customer_notes (odoo_id, user_id, note_content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $odoo_id, $user_id, $content);

    if ($stmt->execute()) {
        // Also update the account_note "cache" in customers_metadata
        $updateStmt = $conn->prepare("UPDATE customers_metadata SET account_note = ? WHERE odoo_id = ?");
        $updateStmt->bind_param("si", $content, $odoo_id);
        $updateStmt->execute();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}
