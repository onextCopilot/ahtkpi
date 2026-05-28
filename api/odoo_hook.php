<?php
/**
 * Odoo Webhook Handler
 * Receives CRM, Sale, Invoice events from Odoo and logs them to DB.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read raw body (Odoo sends JSON)
$raw_body = file_get_contents('php://input');
$payload  = json_decode($raw_body, true);

// Detect event type from payload or headers
$event_type = 'unknown';
if (isset($payload['event_type'])) {
    $event_type = $payload['event_type'];
} elseif (isset($payload['model'])) {
    $model = $payload['model'] ?? '';
    if (str_contains($model, 'crm'))          $event_type = 'crm';
    elseif (str_contains($model, 'sale'))     $event_type = 'sale';
    elseif (str_contains($model, 'account'))  $event_type = 'invoice';
    else                                       $event_type = $model;
}

// Also accept X-Odoo-Event or X-Event-Type header overrides
$header_event = $_SERVER['HTTP_X_ODOO_EVENT'] ?? $_SERVER['HTTP_X_EVENT_TYPE'] ?? null;
if ($header_event) $event_type = $header_event;

$source_ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$payload_json = $raw_body ?: '{}';

// Ensure table exists (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS odoo_webhook_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type  VARCHAR(100) NOT NULL DEFAULT 'unknown',
    payload     LONGTEXT     NOT NULL,
    source_ip   VARCHAR(45)  NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmt = $conn->prepare(
    "INSERT INTO odoo_webhook_logs (event_type, payload, source_ip) VALUES (?, ?, ?)"
);
$stmt->bind_param('sss', $event_type, $payload_json, $source_ip);
$stmt->execute();
$log_id = $conn->insert_id;
$stmt->close();

http_response_code(200);
echo json_encode(['ok' => true, 'log_id' => $log_id, 'event_type' => $event_type]);
