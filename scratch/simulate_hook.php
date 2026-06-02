<?php
$json = '{
  "campaign_id": false,
  "source_id": {"id": 15, "name": "AHT Tech Website"},
  "medium_id": false,
  "sequence_prefix": "",
  "sequence_number": 0,
  "name": false,
  "highest_name": "INV/2026/00314",
  "state": "draft",
  "move_type": "out_invoice",
  "invoice_date": false,
  "invoice_date_due": false,
  "partner_id": {"id": 2049, "name": "AGRILINK AGRICULTURAL CO., LTD"},
  "currency_id": {"id": 1, "name": "USD"},
  "company_currency_id": {"id": 23, "name": "VND"},
  "invoice_user_id": {"id": 167, "name": "Hyun Cao"},
  "team_id": {"id": 16, "name": "AHT BD Global"},
  "journal_id": {"id": 35, "name": "Customer Invoices - VINA"},
  "payment_state": "not_paid",
  "invoice_origin": "S00436",
  "ref": false,
  "amount_untaxed": 100,
  "amount_tax": 0,
  "amount_total": 100,
  "amount_residual": 100,
  "amount_total_signed": 2625400
}';
$p = json_decode($json, true);

$amt_orig     = (float)($p['amount_total'] ?? 0);
$amt_vnd      = abs((float)($p['amount_total_signed'] ?? $amt_orig));
echo "amt_orig: $amt_orig\n";
echo "amt_vnd: $amt_vnd\n";
