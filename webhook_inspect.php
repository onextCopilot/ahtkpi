<?php
require_once __DIR__ . '/config/config.php';
$res = $conn->query("SELECT id, event, status, received_at, payload FROM pasx_webhook_logs ORDER BY id DESC LIMIT 3");
echo '<pre style="font-size:11px;background:#0f172a;color:#e2e8f0;padding:20px;word-break:break-all;">';
while ($row = $res->fetch_assoc()) {
    echo "=== ID:{$row['id']}  event:{$row['event']}  status:{$row['status']}  at:{$row['received_at']} ===\n";
    $decoded = json_decode($row['payload'], true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo "(raw) " . htmlspecialchars(substr($row['payload'] ?? 'NULL', 0, 3000));
    }
    echo "\n\n";
}
echo '</pre>';
