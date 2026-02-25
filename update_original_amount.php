<?php
require_once __DIR__ . '/config/config.php';

// Kiểm tra và tạo cột nếu trên Live Server chưa có
$checkCol = $conn->query("SHOW COLUMNS FROM debts LIKE 'original_amount'");
if ($checkCol && $checkCol->num_rows == 0) {
    if (!$conn->query("ALTER TABLE debts ADD original_amount DECIMAL(15,2) DEFAULT NULL")) {
        die("Lỗi tạo cột original_amount: " . $conn->error);
    }
}

$checkColCurr = $conn->query("SHOW COLUMNS FROM debts LIKE 'original_currency'");
if ($checkColCurr && $checkColCurr->num_rows == 0) {
    if (!$conn->query("ALTER TABLE debts ADD original_currency VARCHAR(10) DEFAULT NULL")) {
        die("Lỗi tạo cột original_currency: " . $conn->error);
    }
}

// Cập nhật original_amount cho các khoản nợ đã có odoo_invoice_id
$sql1 = "UPDATE debts 
         SET original_amount = amount, original_currency = currency 
         WHERE odoo_invoice_id IS NOT NULL 
           AND original_amount IS NULL";

// Cập nhật thêm dựa theo vat_invoice nếu trước đây odoo_invoice_id chưa được lưu 
// (đối với các dòng đồng bộ từ hoá đơn trước đó tạo bằng mã VAT)
$sql2 = "UPDATE debts 
         SET original_amount = amount, original_currency = currency 
         WHERE vat_invoice IS NOT NULL 
           AND vat_invoice != '' 
           AND original_amount IS NULL";

$updated = 0;

if ($conn->query($sql1) === TRUE) {
    $updated += $conn->affected_rows;
}

if ($conn->query($sql2) === TRUE) {
    $updated += $conn->affected_rows;
}

echo "<h1>Đã cập nhật thành công Cột Số Tiền Ban Đầu (original_amount)!</h1>";
echo "<p>Số dòng đã được thêm dữ liệu: <strong>" . $updated . "</strong></p>";
echo "<p>Vui lòng quay lại làm mới trang My Debts để xem thay đổi.</p>";
?>