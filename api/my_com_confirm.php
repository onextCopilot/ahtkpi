<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$u_id = (int) $_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['year'], $body['quarter'], $body['action'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

$year    = (int) $body['year'];
$quarter = (int) $body['quarter'];
$action  = $body['action']; // 'confirm' | 'reset'

if (!in_array($action, ['confirm', 'reset'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS my_com_confirmation (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    year         SMALLINT NOT NULL,
    quarter      TINYINT NOT NULL,
    status       ENUM('draft','confirmed') DEFAULT 'draft',
    confirmed_at DATETIME DEFAULT NULL,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_yq (user_id, year, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure snapshot columns exist (idempotent — CREATE IF NOT EXISTS won't alter an existing table).
function mc_ensure_col($conn, $col, $ddl) {
    $col = $conn->real_escape_string($col);
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='my_com_confirmation' AND COLUMN_NAME='$col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE my_com_confirmation ADD COLUMN $col $ddl");
}
mc_ensure_col($conn, 'snap_total',      "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_com1',       "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_com2',       "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_ai',         "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_so_com',     "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_license',    "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_yb',         "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_kpi_pct',    "DECIMAL(8,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_revenue',    "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_kpi_target', "DECIMAL(20,2) DEFAULT 0");
mc_ensure_col($conn, 'snap_position',   "VARCHAR(50) DEFAULT ''");
mc_ensure_col($conn, 'snap_level',      "VARCHAR(100) DEFAULT ''");

if ($action === 'confirm') {
    $snap = is_array($body['snapshot'] ?? null) ? $body['snapshot'] : [];
    $total      = (float) ($snap['total']      ?? 0);
    $com1       = (float) ($snap['com1']       ?? 0);
    $com2       = (float) ($snap['com2']       ?? 0);
    $ai         = (float) ($snap['ai']         ?? 0);
    $so_com     = (float) ($snap['so_com']     ?? 0);
    $license    = (float) ($snap['license']    ?? 0);
    $yb         = (float) ($snap['yb']         ?? 0);
    $kpi_pct    = (float) ($snap['kpi_pct']    ?? 0);
    $revenue    = (float) ($snap['revenue']    ?? 0);
    $kpi_target = (float) ($snap['kpi_target'] ?? 0);
    $position   = (string) ($snap['position']  ?? '');
    $level      = (string) ($snap['level']     ?? '');
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO my_com_confirmation
        (user_id, year, quarter, status, confirmed_at,
         snap_total, snap_com1, snap_com2, snap_ai, snap_so_com, snap_license, snap_yb,
         snap_kpi_pct, snap_revenue, snap_kpi_target, snap_position, snap_level)
        VALUES (?,?,?,'confirmed',?, ?,?,?,?,?,?,?, ?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status='confirmed', confirmed_at=VALUES(confirmed_at),
         snap_total=VALUES(snap_total), snap_com1=VALUES(snap_com1), snap_com2=VALUES(snap_com2),
         snap_ai=VALUES(snap_ai), snap_so_com=VALUES(snap_so_com), snap_license=VALUES(snap_license),
         snap_yb=VALUES(snap_yb), snap_kpi_pct=VALUES(snap_kpi_pct), snap_revenue=VALUES(snap_revenue),
         snap_kpi_target=VALUES(snap_kpi_target), snap_position=VALUES(snap_position), snap_level=VALUES(snap_level)");
    if (!$stmt) { echo json_encode(['ok' => false, 'error' => $conn->error]); exit; }
    $stmt->bind_param(
        "iiisddddddddddss",
        $u_id, $year, $quarter, $now,
        $total, $com1, $com2, $ai, $so_com, $license, $yb,
        $kpi_pct, $revenue, $kpi_target, $position, $level
    );
} else {
    $stmt = $conn->prepare("INSERT INTO my_com_confirmation (user_id, year, quarter, status)
        VALUES (?,?,?,'draft')
        ON DUPLICATE KEY UPDATE status='draft', confirmed_at=NULL");
    if (!$stmt) { echo json_encode(['ok' => false, 'error' => $conn->error]); exit; }
    $stmt->bind_param("iii", $u_id, $year, $quarter);
}

$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

echo json_encode(['ok' => $ok, 'error' => $ok ? null : $err]);
