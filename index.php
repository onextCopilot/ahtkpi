<?php
/**
 * AHT KPI Management System
 * Front Controller & Router
 * 
 * Handles all requests when using standard web servers (Nginx/Apache)
 * with a single entry point (e.g., try_files $uri $uri/ /index.php?$query_string;)
 */

// Basic error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep disabled in production

// Parse the current URI
$request_uri = $_SERVER['REQUEST_URI'];
$parsed_uri = parse_url($request_uri, PHP_URL_PATH);
$uri = strtolower(urldecode($parsed_uri));
$base_path = '/'; // Define base path for the application

// Strip any script filename prefix (e.g. /router.php, /index.php)
$uri = preg_replace('#^/(router|index)\.php#', '', $uri);
if (empty($uri)) $uri = '/';

// Clean up URI
$uri = rtrim($uri, '/');
if ($uri === '' || $uri === false) {
    $uri = '/';
}

// ── Serve static files under /public/ directly ───────────────────────────
if (strpos($uri, '/public/') === 0) {
    $file_path = __DIR__ . $uri;
    if (file_exists($file_path) && is_file($file_path)) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',  'gif' => 'image/gif',
            'webp'=> 'image/webp', 'svg' => 'image/svg+xml',
            'css' => 'text/css',   'js'  => 'application/javascript',
            'pdf' => 'application/pdf',
        ];
        header('Content-Type: ' . ($mime_map[$ext] ?? 'application/octet-stream'));
        readfile($file_path);
        exit();
    }
}

// If root, redirect to login or dashboard based on session
if ($uri === '/') {
    session_start();
    if (isset($_SESSION['user_id'])) {
        header("Location: " . ($base_path === '/' || $base_path === '\\' ? '' : $base_path) . "/dashboard");
        exit();
    } else {
        header("Location: " . ($base_path === '/' || $base_path === '\\' ? '' : $base_path) . "/login");
        exit();
    }
}

// Route mapping
$routes = [
    '/login' => 'modules/auth/login.php',
    '/logout' => 'modules/auth/logout.php',
    '/dashboard' => 'modules/dashboard/dashboard.php',
    '/profile' => 'modules/profile/index.php',
    '/settings' => 'modules/settings/index.php',
    '/settings/users' => 'modules/settings/users/index.php',
    '/settings/users/edit' => 'modules/settings/users/edit.php',
    '/settings/departments' => 'modules/settings/departments/index.php',
    '/settings/core_members' => 'modules/settings/core_members/index.php',
    '/settings/smtp' => 'modules/settings/smtp/index.php',
    '/settings/odoo' => 'modules/settings/odoo/index.php',
    '/settings/jira' => 'modules/settings/jira/index.php',
    '/settings/aihive' => 'modules/settings/aihive/index.php',
    '/settings/presale-prompts' => 'modules/settings/presale-prompts/index.php',
    '/settings/teams' => 'modules/settings/teams/index.php',
    '/settings/sale-levels' => 'modules/settings/sale-levels/index.php',
    '/settings/odoo-rates' => 'modules/settings/odoo_rates/index.php',
    '/settings/backup' => 'modules/settings/backup/index.php',
    '/settings/workflow' => 'modules/settings/workflow/index.php',
    '/debt' => 'modules/debt/index.php',
    '/my-debt' => 'modules/my_debt/index.php',
    '/debt-warning' => 'modules/debt_warning/index.php',
    '/customers' => 'modules/customers/index.php',
    '/invoices' => 'modules/invoices/index.php',
    '/kpi' => 'modules/kpi/index.php',
    '/api/kpi_tab_order' => 'api/kpi_tab_order.php',
    '/api/kpi_sort' => 'api/kpi_sort.php',
    '/api/kpi_monthly_save' => 'api/kpi_monthly_save.php',
    '/api/kpi_quarterly_save' => 'api/kpi_quarterly_save.php',
    '/api/mark_notification_read' => 'api/mark_notification_read.php',
    '/sale-orders' => 'modules/sale_orders/index.php',
    '/api/sale_orders' => 'api/sale_orders.php',
    '/my-reports' => 'modules/sale_reports/index.php',
    '/detail-report' => 'modules/sale_reports/detail.php',
    '/detail_report' => 'modules/sale_reports/detail.php',
    '/sale-reports' => 'modules/sale_reports/index.php',
    '/sale_reports' => 'modules/sale_reports/index.php',
    '/sale-reports-admin' => 'modules/sale_reports_admin/index.php',
    '/bc-reports' => 'modules/bc_reports/index.php',
    '/guides' => 'modules/guides/index.php',
    '/core-key-kpi' => 'modules/core_kpi/index.php',
    '/plan-budgeting' => 'modules/plan_budgeting/index.php',
    '/plan-budgeting/report' => 'modules/plan_budgeting/report.php',
    '/documents' => 'modules/documents/index.php',
    '/folio'     => 'modules/folio/index.php',
    '/hrm' => 'modules/hrm/index.php',
    '/hrm/e-hiring' => 'modules/hrm/e_hiring.php',
    '/hrm/company-info' => 'modules/hrm/company_info.php',
    '/hrm/permissions' => 'modules/hrm/permissions.php',
    '/hrm/proposal-settings' => 'modules/hrm/proposal_settings.php',
    '/hrm/other-settings' => 'modules/hrm/other_settings.php',
    '/hrm/candidate-sources' => 'modules/hrm/candidate_sources.php',
    '/hrm/rejection-reasons' => 'modules/hrm/rejection_reasons.php',
    '/hrm/expired-job-settings' => 'modules/hrm/expired_job_settings.php',
    '/hrm/evaluation-criteria' => 'modules/hrm/evaluation_criteria.php',
    '/hrm/job-post-create' => 'modules/hrm/job_post_create.php',
    '/hrm/system-settings' => 'modules/hrm/system_settings.php',
    '/hrm/ajax-handler' => 'modules/hrm/ajax_handler.php',
    '/hrm/email-templates' => 'modules/hrm/email_templates.php',
    '/hrm/email-template-detail' => 'modules/hrm/email_template_detail.php',
    '/hrm/interview-templates' => 'modules/hrm/interview_templates.php',
    '/hrm/interview-template-detail' => 'modules/hrm/interview_template_detail.php',
    '/hrm/openings' => 'modules/hrm/openings.php',
    '/hrm/job-detail' => 'modules/hrm/job_detail.php',
    '/hrm/job-edit' => 'modules/hrm/job_edit.php',
    '/hrm/candidates' => 'modules/hrm/candidates.php',
    '/presale' => 'modules/presale/index.php',
    '/presale/ajax-handler' => 'modules/presale/ajax_handler.php',
    '/tai-lieu-quy-trinh' => 'modules/documents/tai_lieu_quy_trinh.php',
    '/projects/phuong-an-kinh-doanh' => 'modules/projects/phuong_an_kinh_doanh.php',
    '/projects/du-an' => 'modules/projects/du_an.php',
    '/projects/pakd/create' => 'modules/projects/pakd_create.php',
    '/projects/pakd/edit' => 'modules/projects/pakd_detail.php',
    '/projects/pakd/sync-odoo' => 'modules/projects/sync_pakd_odoo.php',
    '/projects/pakd/settings' => 'modules/projects/pakd_sync_settings.php',
];

// DEBUG: log URI + match result
file_put_contents(__DIR__ . '/debug_index.log', date('H:i:s') . " URI=[$uri] IN_ROUTES=" . (array_key_exists($uri, $routes) ? 'YES' : 'NO') . "\n", FILE_APPEND);

// Check if route exists in our mapping
if (array_key_exists($uri, $routes)) {
    $file = __DIR__ . '/' . $routes[$uri];
    if (file_exists($file)) {
        require $file;
        exit();
    }
}

// 404 - Not found
http_response_code(404);
echo "<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-container { text-align: center; padding: 2rem; }
        h1 { font-size: 4rem; margin: 0; color: #8b5cf6; }
        p { font-size: 1.2rem; color: #94a3b8; }
        a { color: #818cf8; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>404</h1>
        <p>Page not found</p>
        <p><a href='" . ($base_path === '/' || $base_path === '\\' ? '/' : $base_path . '/') . "'>Go to Home</a></p>
    </div>
</body>
</html>";
exit();