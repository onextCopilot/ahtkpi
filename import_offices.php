<?php
/**
 * import_offices.php
 * Insert/cập nhật danh sách văn phòng (hrm_offices) cho live site.
 *
 * An toàn để chạy lại nhiều lần: dùng INSERT ... ON DUPLICATE KEY UPDATE
 * theo id cố định, KHÔNG xoá dữ liệu sẵn có.
 *
 * Cách chạy:
 *   - Web : truy cập /import_offices.php trên trình duyệt
 *   - CLI : php import_offices.php
 */

require __DIR__ . '/config/config.php';

$isCli = (PHP_SAPI === 'cli');
$nl    = $isCli ? "\n" : "<br>\n";

// Bao ve: chay qua web phai dang nhap voi quyen admin.
// Chay qua CLI (da co quyen shell) thi cho phep.
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

// 1) Tạo bảng nếu chưa tồn tại (khớp cấu trúc live)
$createSql = "CREATE TABLE IF NOT EXISTS `hrm_offices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createSql)) {
    die('Loi tao bang hrm_offices: ' . $conn->error);
}

// 2) Dữ liệu văn phòng: [id, name, address, sort_order]
$createdAt = '2026-05-06 13:42:49';
$offices = [
    [1,  'AHT TECH HEAD OFFICE - Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Cầu Giấy, TP Hà Nội', 'AHT TECH HEAD OFFICE - Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Cầu Giấy, TP Hà Nội', 0],
    [2,  'AHT TECH - Văn phòng TP. Hồ Chí Minh - Tầng 7, Tòa nhà Jea Building, 112 Lý Chính Thắng, Phường Xuân Hoà, Thành Phố Hồ Chí Minh', 'AHT TECH - Văn phòng TP. Hồ Chí Minh - Tầng 7, Tòa nhà Jea Building, 112 Lý Chính Thắng, Phường Xuân Hoà, Thành Phố Hồ Chí Minh', 1],
    [3,  'AHT Phú Thọ - Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ', 'Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì – Phú Thọ', 2],
    [4,  'Văn phòng đối tác', '', 16],
    [5,  'Malaysia', '', 15],
    [6,  'Remote/hybrid', '', 14],
    [7,  'Văn phòng Đối tác – Lê Ngọc Hân, Hai Bà Trưng', 'Văn phòng Đối tác – Lê Ngọc Hân, Hai Bà Trưng', 13],
    [8,  'Văn phòng Đối tác – Trần Quang Khải, Hoàn Kiếm', 'Văn phòng Đối tác – Trần Quang Khải, Hoàn Kiếm', 12],
    [9,  'Văn phòng Đối tác – Trần Hưng Đạo, Hoàn Kiếm', 'Văn phòng Đối tác – Trần Hưng Đạo, Hoàn Kiếm', 11],
    [10, 'Văn phòng Đối tác – Mỹ Đình, Phường Yên Hòa, Hà Nội', 'Văn phòng Đối tác – Mỹ Đình, Phường Yên Hòa, Hà Nội', 10],
    [11, 'Văn phòng Đối tác – Nguyễn Tuân, Thanh Xuân', 'Văn phòng Đối tác – Nguyễn Tuân, Thanh Xuân', 9],
    [12, 'Văn phòng Đối tác – Xuân Thủy, Cầu Giấy', 'Văn phòng Đối tác – Xuân Thủy, Cầu Giấy', 8],
    [13, 'Văn phòng Đối tác – Nguyễn Chí Thanh, Hà Nội', 'Văn phòng Đối tác – Nguyễn Chí Thanh, Hà Nội', 7],
    [14, 'Văn phòng Đối tác – Láng Hạ, Đống Đa', 'Văn phòng Đối tác – Láng Hạ, Đống Đa', 6],
    [15, 'Văn phòng Đối tác – Huỳnh Thúc Kháng, Hà Nội', 'Văn phòng Đối tác – Huỳnh Thúc Kháng, Hà Nội', 5],
    [16, 'Văn phòng Đối tác – Định Vương Hậu, Hà Nội', 'Văn phòng Đối tác – Định Vương Hậu, Hà Nội', 4],
    [17, 'Văn phòng Đối tác – Nguyễn Phong Sắc, Hà Nội', 'Văn phòng Đối tác – Nguyễn Phong Sắc, Hà Nội', 3],
];

// 3) Upsert theo id - chạy lại an toan, khong mat du lieu khac
$sql = 'INSERT INTO `hrm_offices` (`id`, `name`, `address`, `created_at`, `sort_order`)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `address` = VALUES(`address`),
            `sort_order` = VALUES(`sort_order`)';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Loi prepare: ' . $conn->error);
}

$ok = 0;
$fail = 0;
foreach ($offices as $o) {
    $stmt->bind_param('isssi', $o[0], $o[1], $o[2], $createdAt, $o[3]);
    if ($stmt->execute()) {
        $ok++;
        echo 'OK  #' . $o[0] . ' - ' . $o[1] . $nl;
    } else {
        $fail++;
        echo 'LOI #' . $o[0] . ': ' . $stmt->error . $nl;
    }
}
$stmt->close();

// 4) Dong bo lai AUTO_INCREMENT
$conn->query('ALTER TABLE `hrm_offices` AUTO_INCREMENT = ' . (count($offices) + 1));

echo $nl . "Hoan tat: $ok thanh cong, $fail loi (tong " . count($offices) . " van phong)." . $nl;
