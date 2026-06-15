<?php
// api/realtime_check_paid.php
// Realtime: lấy TẤT CẢ invoice khách hàng (out_invoice, posted) trong 1 năm trực tiếp từ Odoo
// và tính tổng số tiền ĐÃ THU (chỉ phần paid; invoice thu một phần chỉ tính phần đã thu).
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

$year = intval($_GET['year'] ?? $_POST['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

try {
    $odoo = new OdooAPI();

    $from = sprintf('%04d-01-01', $year);
    $to   = sprintf('%04d-12-31', $year);

    $domain = [
        ['move_type', '=', 'out_invoice'],
        ['state', '=', 'posted'],
        ['invoice_date', '>=', $from],
        ['invoice_date', '<=', $to],
    ];
    $fields = [
        'amount_total',         // tổng theo tiền hóa đơn
        'amount_total_signed',  // tổng theo tiền công ty (VND), có dấu
        'amount_residual',      // còn nợ theo tiền hóa đơn
        'payment_state',
        'invoice_date',
    ];

    // limit cao để lấy hết hóa đơn trong năm (an toàn hơn limit=0)
    $invoices = $odoo->searchRead('account.move', $domain, $fields, 100000, 0);
    if (!is_array($invoices)) {
        $invoices = [];
    }

    $paid_vnd = 0.0;          // tổng phần đã thu (VND)
    $total_vnd = 0.0;         // tổng giá trị hóa đơn (VND)
    $invoice_count = 0;       // số hóa đơn posted trong năm
    $paid_invoice_count = 0;  // số hóa đơn có thu được tiền (>0)

    foreach ($invoices as $inv) {
        $invoice_count++;

        $amount_total = abs((float) ($inv['amount_total'] ?? 0));
        $residual     = abs((float) ($inv['amount_residual'] ?? 0));
        $total_signed = abs((float) ($inv['amount_total_signed'] ?? 0)); // VND

        $total_vnd += $total_signed;

        if ($amount_total > 0) {
            // Tỉ lệ đã thu (cùng đơn vị tiền hóa đơn nên không phụ thuộc tỉ giá)
            $paid_fraction = max(0.0, min(1.0, ($amount_total - $residual) / $amount_total));
            $paid_row = $total_signed * $paid_fraction; // quy ra VND theo giá trị công ty
        } else {
            $paid_row = 0.0;
        }

        $paid_vnd += $paid_row;
        if ($paid_row > 0.01) {
            $paid_invoice_count++;
        }
    }

    echo json_encode([
        'success'             => true,
        'year'                => $year,
        'paid_vnd'            => round($paid_vnd),
        'total_vnd'           => round($total_vnd),
        'invoice_count'       => $invoice_count,
        'paid_invoice_count'  => $paid_invoice_count,
        'checked_at'          => date('Y-m-d H:i:s'),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
