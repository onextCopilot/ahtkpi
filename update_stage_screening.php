<?php
/**
 * update_stage_screening.php
 * Doi ten giai doan code=SCREENING thanh "Screening" tren live.
 * Chay 1 lan: web (can dang nhap admin) hoac CLI.
 */

require __DIR__ . '/config/config.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<h3>Unauthorized: can dang nhap bang tai khoan admin.</h3>');
    }
}

if (!isset($conn) || $conn->connect_errno) {
    die('Khong ket noi duoc database: ' . ($conn->connect_error ?? 'unknown'));
}
$conn->set_charset('utf8mb4');

$st = $conn->prepare("UPDATE hrm_pipeline_stages SET name = 'Screening' WHERE code = 'SCREENING'");
if ($st->execute()) {
    echo 'OK: da cap nhat ' . $conn->affected_rows . ' giai doan thanh "Screening".';
} else {
    echo 'LOI: ' . $st->error;
}
