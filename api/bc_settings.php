<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/OdooAPI.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Ensure table exists
$sql_bc_perm = "CREATE TABLE IF NOT EXISTS bc_permissions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    bc_name VARCHAR(100) NOT NULL,
    UNIQUE KEY user_bc (user_id, bc_name)
)";
$conn->query($sql_bc_perm);

if ($action === 'get_settings') {
    // 1. Get all users
    $users = [];
    $stmt = $conn->prepare("SELECT id, username, full_name, email FROM users ORDER BY full_name ASC");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    // 2. Get existing permissions
    $permissions = [];
    $p_stmt = $conn->prepare("SELECT user_id, bc_name FROM bc_permissions");
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    while ($row = $p_res->fetch_assoc()) {
        $permissions[] = $row;
    }
    $p_stmt->close();

    // 3. Get list of all BCs by querying current move line branch_id mapping with 'BC'
    // To be lightweight, just search account.move.line for branch_id where name like 'BC' and group it.
    // However, searching thousands of lines each time is heavy.
    // Since we just want the list of branch names, we can search the "res.branch" model directly!
    $api = new OdooAPI();
    $bcs = [];
    try {
        $all_branches = $api->searchRead('res.branch', [['name', 'ilike', 'bc']], ['id', 'name'], 0, 0);
        foreach ($all_branches as $br) {
            $bcs[] = $br['name'];
        }
    } catch (Exception $e) {
        // Fallback to empty if Odoo is unreachable
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'permissions' => $permissions,
        'bcs' => $bcs
    ]);
} elseif ($action === 'save_settings') {
    // Save permissions logic
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['permissions'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Truncate/Clear all existing permissions to insert fresh
        $conn->query("DELETE FROM bc_permissions");

        $insert_stmt = $conn->prepare("INSERT INTO bc_permissions (user_id, bc_name) VALUES (?, ?)");

        foreach ($data['permissions'] as $p) {
            $u_id = (int) $p['user_id'];
            $b_name = $p['bc_name'];
            $insert_stmt->bind_param("is", $u_id, $b_name);
            $insert_stmt->execute();
        }

        $insert_stmt->close();
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
