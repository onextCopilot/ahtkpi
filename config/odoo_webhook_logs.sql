-- Migration: Odoo Webhook Logs table
-- Run once, or let odoo_hook.php create it automatically on first call.

CREATE TABLE IF NOT EXISTS odoo_webhook_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type  VARCHAR(100) NOT NULL DEFAULT 'unknown'  COMMENT 'crm | sale | invoice | …',
    payload     LONGTEXT     NOT NULL                     COMMENT 'Raw JSON from Odoo',
    source_ip   VARCHAR(45)  NOT NULL DEFAULT ''          COMMENT 'Caller IP',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
