<?php
require_once __DIR__ . '/../config/config.php';

// Clear existing departments
$conn->query("TRUNCATE TABLE hrm_departments");

// Insert the list from the screenshot
$depts = [
    ['Sales/Marketing', 'Sales/Marketing'],
    ['Backoffice', ''],
    ['AHT Thái Nguyên', 'Số 259 Quang Trung, Phường Tân Thịnh, Thái Nguyên'],
    ['AHT Phú Thọ', 'Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ'],
    ['IT', 'IT'],
    ['BFSI', ''],
    ['Remote/Hybrid', 'Remote/Hybrid'],
    ['Akdemy', 'Akdemy']
];

foreach ($depts as $d) {
    $name = $conn->real_escape_string($d[0]);
    $desc = $conn->real_escape_string($d[1]);
    $conn->query("INSERT INTO hrm_departments (name, description) VALUES ('$name', '$desc')");
}

echo "Departments updated successfully.";
?>
