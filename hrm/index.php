<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

if ($path === '/hrm/e-hiring' || $path === '/hrm/e-hiring.php') {
    require_once __DIR__ . '/../modules/hrm/e_hiring.php';
} elseif ($path === '/hrm/company-info' || $path === '/hrm/company-info.php') {
    require_once __DIR__ . '/../modules/hrm/company_info.php';
} elseif ($path === '/hrm/ajax-handler' || $path === '/hrm/ajax-handler.php') {
    require_once __DIR__ . '/../modules/hrm/ajax_handler.php';
} elseif ($path === '/hrm/permissions' || $path === '/hrm/permissions.php') {
    require_once __DIR__ . '/../modules/hrm/permissions.php';
} elseif ($path === '/hrm/other-settings' || $path === '/hrm/other-settings.php') {
    require_once __DIR__ . '/../modules/hrm/other_settings.php';
} elseif ($path === '/hrm/proposal-settings' || $path === '/hrm/proposal-settings.php') {
    require_once __DIR__ . '/../modules/hrm/proposal_settings.php';
} else {
    require_once __DIR__ . '/../modules/hrm/index.php';
}
