<?php
// api/realtime_check_paid.php
// Realtime: tính tổng TIỀN ĐÃ THU TỪ KHÁCH (cash collected) trong 1 khoảng PAID DATE (ngày thu),
// lấy trực tiếp từ Odoo qua các lần thanh toán (invoice_payments_widget) — chỉ tính phần đã thu.
header('Content-Type: application/json');

$old_error_level = error_reporting(0);
require_once __DIR__ . '/../config/config.php';
error_reporting($old_error_level);
require_once __DIR__ . '/../libs/OdooAPI.php';

// Phải đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Chỉ cho phép Hyun Cao
$fullName = $_SESSION['full_name'] ?? '';
if (stripos($fullName, 'Hyun Cao') === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

// Khoảng PAID DATE: from/to (YYYY-MM-DD). Nếu không có -> dùng cả năm (year).
$from = trim((string) ($_GET['from'] ?? $_POST['from'] ?? ''));
$to   = trim((string) ($_GET['to'] ?? $_POST['to'] ?? ''));
$validDate = function ($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1; };

if (!$validDate($from) || !$validDate($to)) {
    $year = intval($_GET['year'] ?? $_POST['year'] ?? date('Y'));
    if ($year < 2000 || $year > 2100) $year = (int) date('Y');
    $from = sprintf('%04d-01-01', $year);
    $to   = sprintf('%04d-12-31', $year);
}
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

try {
    $odoo = new OdooAPI();

    // Map currency_id (số) -> rate (đơn vị ngoại tệ trên 1 VND) để quy đổi mọi tiền tệ về VND.
    $currencies = $odoo->getCurrencies();
    $idRate = [];
    foreach ($currencies as $c) {
        if (isset($c['id'])) $idRate[(int) $c['id']] = (float) $c['rate'];
    }

    // Lấy hóa đơn KH posted có phát sinh thanh toán; chặn dưới = đầu năm trước của 'from'
    // để bao gồm cả HĐ phát hành năm trước nhưng thu trong kỳ.
    $lowerYear = (int) substr($from, 0, 4) - 1;
    $domain = [
        ['move_type', '=', 'out_invoice'],
        ['state', '=', 'posted'],
        ['payment_state', 'in', ['paid', 'partial', 'in_payment']],
        ['invoice_date', '>=', sprintf('%04d-01-01', $lowerYear)],
        ['invoice_date', '<=', $to],
    ];

    $invoices = $odoo->searchRead('account.move', $domain, ['invoice_payments_widget'], 100000, 0);
    if (!is_array($invoices)) {
        $invoices = [];
    }

    $paid_vnd = 0.0;          // tổng tiền đã thu trong kỳ (VND)
    $invoice_count = 0;       // số hóa đơn có thu trong kỳ
    $payment_count = 0;       // số lượt thu trong kỳ

    foreach ($invoices as $inv) {
        $w = $inv['invoice_payments_widget'] ?? null;
        if (is_string($w)) $w = json_decode($w, true);
        if (empty($w['content']) || !is_array($w['content'])) continue;

        $hit = false;
        foreach ($w['content'] as $p) {
            // Bỏ qua dòng chênh lệch tỉ giá (không phải tiền thu thực)
            if (!empty($p['is_exchange'])) continue;

            $d = $p['date'] ?? '';
            if ($d < $from || $d > $to) continue;

            $amt = (float) ($p['amount'] ?? 0);
            if ($amt == 0) continue;

            $cid = is_array($p['currency_id'] ?? null) ? (int) ($p['currency_id'][0] ?? 0) : (int) ($p['currency_id'] ?? 0);
            $rate = $idRate[$cid] ?? 0.0;
            if ($rate <= 0) continue;

            $paid_vnd += $amt / $rate;
            $payment_count++;
            $hit = true;
        }
        if ($hit) $invoice_count++;
    }

    echo json_encode([
        'success'        => true,
        'from'           => $from,
        'to'             => $to,
        'paid_vnd'       => round($paid_vnd),
        'invoice_count'  => $invoice_count,
        'payment_count'  => $payment_count,
        'checked_at'     => date('Y-m-d H:i:s'),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
