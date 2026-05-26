<?php
require_once __DIR__ . '/config/config.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Forbidden');
}
$res = $conn->query("SELECT id, event, status, received_at, LEFT(payload, 1200) as p FROM pasx_webhook_logs ORDER BY id DESC LIMIT 5");
echo '<pre style="font-size:12px;background:#0f172a;color:#e2e8f0;padding:20px;border-radius:8px;">';
while ($row = $res->fetch_assoc()) {
    echo "=== ID:{$row['id']}  event:{$row['event']}  status:{$row['status']}  at:{$row['received_at']} ===\n";
    $decoded = json_decode($row['p'], true);
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}
echo '</pre>';
