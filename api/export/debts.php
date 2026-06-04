<?php
/**
 * Export debts to Excel (.xls). Access + grouping live in _debts_data.php.
 * Optional GET filters: year, month (by invoice_date), status (payment_status).
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Exporter.php';
require_once __DIR__ . '/_debts_data.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$data = build_debts_export($conn, $_SESSION, $_GET);
Exporter::streamXls('cong-no_' . date('Ymd_His'), $data['title'], $data['headers'], $data['rows']);
