<?php
/**
 * /hrm/dashboard — redirect về trang Tuyển dụng (đã hợp nhất).
 */
require_once __DIR__ . '/lib/core.php';
hrm_require_login();
$period = $_GET['period'] ?? '';
header('Location: /hrm/recruitment' . ($period ? '?period=' . urlencode($period) : ''));
exit;
