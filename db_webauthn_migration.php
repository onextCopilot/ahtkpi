<?php
/**
 * Run once: creates user_passkeys table for WebAuthn / passkey login.
 * Usage: php db_webauthn_migration.php  OR  open in browser once.
 */
require_once __DIR__ . '/config/config.php';

$sql = "
CREATE TABLE IF NOT EXISTS user_passkeys (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    credential_id  VARCHAR(512) NOT NULL,
    public_key     TEXT NOT NULL,
    sign_count     INT DEFAULT 0,
    device_name    VARCHAR(255) DEFAULT 'Passkey',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at   TIMESTAMP NULL,
    UNIQUE KEY uk_credential_id (credential_id(255)),
    KEY idx_user_id (user_id),
    CONSTRAINT fk_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "OK: user_passkeys table created (or already exists).\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
