<?php
/**
 * Export debts to PDF (printable HTML → browser "Save as PDF").
 * Same access/grouping as the Excel export (shared _debts_data.php).
 * Optional GET: year, month, status. ?noprint=1 to skip the auto print dialog.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Exporter.php';
require_once __DIR__ . '/_debts_data.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$data = build_debts_export($conn, $_SESSION, $_GET);

$head = '<h1>' . htmlspecialchars($data['title']) . '</h1>'
    . '<div class="sub">Xuất ngày ' . date('d/m/Y H:i') . ' · ' . (int) $data['count'] . ' bản ghi</div>';
$body = $head . Exporter::tableHtml($data['headers'], $data['rows']);

Exporter::renderPrintable($data['title'], $body);
