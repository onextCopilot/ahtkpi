<?php
require_once __DIR__ . '/config/config.php';

// Chạy lệnh xoá lịch sử online
$sql = "UPDATE users SET last_active = NULL";
if ($conn->query($sql) === TRUE) {
    echo "<h1>Đã reset thành công trạng thái Online của tất cả người dùng!</h1>";
} else {
    echo "Lỗi: " . $conn->error;
}
?>