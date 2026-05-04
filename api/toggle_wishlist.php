<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$folio_id = $_POST['folio_id'] ?? '';
$action = $_POST['action'] ?? 'toggle'; // toggle, add, remove

if (empty($folio_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing Folio ID']);
    exit;
}

// Ensure table exists (safeguard)
$conn->query("CREATE TABLE IF NOT EXISTS folio_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    folio_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_folio (user_id, folio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($action === 'toggle') {
    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM folio_wishlist WHERE user_id = ? AND folio_id = ?");
    $stmt->bind_param("is", $user_id, $folio_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        // Remove
        $stmt = $conn->prepare("DELETE FROM folio_wishlist WHERE user_id = ? AND folio_id = ?");
        $stmt->bind_param("is", $user_id, $folio_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'status' => 'removed']);
    } else {
        // Add
        $stmt = $conn->prepare("INSERT INTO folio_wishlist (user_id, folio_id) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $folio_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'status' => 'added']);
    }
} else if ($action === 'check') {
    $stmt = $conn->prepare("SELECT id FROM folio_wishlist WHERE user_id = ? AND folio_id = ?");
    $stmt->bind_param("is", $user_id, $folio_id);
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode(['success' => true, 'is_wishlisted' => $res->num_rows > 0]);
}
?>
