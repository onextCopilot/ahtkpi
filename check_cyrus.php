<?php
require_once 'libs/OdooAPI.php';
$odoo = new OdooAPI();

$fields = ['id', 'name', 'partner_id', 'commercial_partner_id', 'date', 'invoice_date', 'amount_total'];
$domain = [
    ['invoice_date', '>=', '2025-12-01'],
    ['invoice_date', '<=', '2025-12-31'],
    ['partner_id', 'ilike', 'Cyrus']
];
$invoices = $odoo->searchRead('account.move', $domain, $fields, 10);
echo "Invoices with Cyrus:\n";
echo json_encode($invoices, JSON_PRETTY_PRINT) . "\n\n";

$domain2 = [
    ['invoice_date', '>=', '2025-12-01'],
    ['invoice_date', '<=', '2025-12-31'],
    ['partner_id', 'ilike', 'Lolli']
];
$invoices2 = $odoo->searchRead('account.move', $domain2, $fields, 10);
echo "Invoices with Lolli:\n";
echo json_encode($invoices2, JSON_PRETTY_PRINT) . "\n";
