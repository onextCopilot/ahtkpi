<?php
/**
 * Xuất kho ứng viên (.xls / .csv) theo đúng bộ lọc hiện tại hoặc danh sách đã chọn.
 * Route: /hrm/candidates/export
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/candidates.php';
require_once __DIR__ . '/../../includes/Exporter.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$fmt = strtolower($_GET['fmt'] ?? 'xls');
$rows = hrm_candidate_query($conn, hrm_candidate_filters(), 5000);
$statuses = hrm_candidate_statuses();

$headers = ['Họ tên','Email','Điện thoại','Trạng thái','Vị trí gần nhất','Kỹ năng','Khu vực',
            'Số năm KN','Nguồn','Sự kiện','Thẻ','Người phụ trách','Talent pool','Vị trí ứng tuyển',
            'Giai đoạn','Lương kỳ vọng','Đánh giá','Ngày tạo','Hoạt động gần nhất'];
$data = [];
foreach ($rows as $c) {
    $data[] = [
        $c['full_name'], $c['email'], $c['phone'],
        $statuses[$c['status']] ?? $c['status'],
        $c['current_position'], $c['skill_list'], $c['location'],
        (float)$c['years_exp'] ?: '', $c['source_name'], $c['event_name'], $c['tag_list'],
        $c['owner_name'], $c['talent_pool'] ? 'Có' : '', $c['app_job'], $c['app_stage'],
        $c['expected_salary'], (int)$c['rating'] ?: '',
        $c['created_at'] ? date('d/m/Y', strtotime($c['created_at'])) : '',
        $c['last_activity_at'] ? date('d/m/Y H:i', strtotime($c['last_activity_at'])) : '',
    ];
}

$fname = 'ung-vien-' . date('Ymd-His');
if ($fmt === 'csv') {
    Exporter::streamCsv($fname . '.csv', $headers, $data);
} else {
    Exporter::streamXls($fname . '.xls', 'Kho ứng viên (' . count($data) . ')', $headers, $data);
}
