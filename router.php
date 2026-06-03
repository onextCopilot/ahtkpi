<?php
/**
 * Router for PHP Built-in Server
 * 
 * This file handles routing when using PHP's built-in web server
 * Usage: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = strtolower(urldecode($uri));

// Strip any script filename prefix (e.g. /router.php, /index.php)
// This happens when PHP built-in server is invoked with a router script
$uri = preg_replace('#^/(router|index)\.php#', '', $uri);
if (empty($uri)) $uri = '/';

// Remove trailing slash except for root
if ($uri !== '/') {
    $uri = rtrim($uri, '/');
}
if (empty($uri)) $uri = '/';

// Route mapping
$routes = [
    '/' => 'index.php',
    '/login' => 'modules/auth/login.php',
    '/logout' => 'modules/auth/logout.php',
    '/auth/webauthn/register-options' => 'modules/auth/webauthn/register_options.php',
    '/auth/webauthn/register'         => 'modules/auth/webauthn/register.php',
    '/auth/webauthn/login-options'    => 'modules/auth/webauthn/login_options.php',
    '/auth/webauthn/login'            => 'modules/auth/webauthn/login.php',
    '/auth/webauthn/list'             => 'modules/auth/webauthn/list.php',
    '/auth/webauthn/delete'           => 'modules/auth/webauthn/delete.php',
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
    '/settings/arrowhitech' => 'modules/settings/arrowhitech/index.php',
    '/debt' => 'modules/debt/index.php',
    '/my-debt' => 'modules/my_debt/index.php',
    '/debt-warning' => 'modules/debt_warning/index.php',
    '/customers' => 'modules/customers/index.php',
    '/invoices' => 'modules/invoices/index.php',
    '/kpi' => 'modules/kpi/index.php',
    '/my-com' => 'modules/my_com/index.php',
    '/my-com/yearly-bonus' => 'modules/my_com/yearly_bonus.php',
    '/commission-board' => 'modules/commission_board/index.php',
    '/api/invoice_pakd_map' => 'api/invoice_pakd_map.php',
    '/api/invoice_pakd_map.php' => 'api/invoice_pakd_map.php',
    '/api/so_first_po' => 'api/so_first_po.php',
    '/api/so_first_po.php' => 'api/so_first_po.php',
    '/api/my_com_confirm' => 'api/my_com_confirm.php',
    '/api/my_com_confirm.php' => 'api/my_com_confirm.php',
    '/api/quarter_kpi' => 'api/quarter_kpi.php',
    '/api/quarter_kpi.php' => 'api/quarter_kpi.php',
    '/api/add_debt_from_invoice' => 'api/add_debt_from_invoice.php',
    '/api/request_production_plan' => 'api/request_production_plan.php',
    '/odoo/hook'                   => 'api/odoo_hook.php',
    '/odoo/logs'                   => 'modules/odoo_logs/index.php',
    '/api/odoo_log_detail'         => 'api/odoo_log_detail.php',
    '/api/odoo_log_clear'          => 'api/odoo_log_clear.php',
    '/api/pasx/callback'           => 'api/pasx_callback.php',
    '/api/pasx/message'            => 'api/pasx_message_receive.php',
    '/api/kpi_tab_order' => 'api/kpi_tab_order.php',
    '/api/kpi_sort' => 'api/kpi_sort.php',
    '/api/kpi_monthly_save' => 'api/kpi_monthly_save.php',
    '/api/kpi_quarterly_save' => 'api/kpi_quarterly_save.php',
    '/api/mark_notification_read' => 'api/mark_notification_read.php',
    '/api/notifications/mark_read' => 'api/notifications_mark_read.php',
    '/sale-orders' => 'modules/sale_orders/index.php',
    '/api/sale_orders' => 'api/sale_orders.php',
    '/my-reports' => 'modules/sale_reports/index.php',
    '/detail-report' => 'modules/sale_reports/detail.php',
    '/detail_report' => 'modules/sale_reports/detail.php',
    '/sale-reports' => 'modules/sale_reports/index.php',
    '/sale_reports' => 'modules/sale_reports/index.php',
    '/sale-reports-admin' => 'modules/sale_reports_admin/index.php',
    '/guides' => 'modules/guides/index.php',
    '/core-key-kpi' => 'modules/core_kpi/index.php',
    '/folio' => 'modules/folio/index.php',
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
    '/presale' => 'modules/presale/index.php',
    '/presale/ajax-handler' => 'modules/presale/ajax_handler.php',
    '/tai-lieu-quy-trinh' => 'modules/documents/tai_lieu_quy_trinh.php',
    '/projects/phuong-an-kinh-doanh' => 'modules/projects/phuong_an_kinh_doanh.php',
    '/projects/du-an' => 'modules/projects/du_an.php',
    '/projects/du-an/detail' => 'modules/projects/du_an_detail.php',
    '/projects/pakd/create' => 'modules/projects/pakd_create.php',
    '/projects/pakd/edit' => 'modules/projects/pakd_detail.php',
    '/projects/pakd/sync-odoo' => 'modules/projects/sync_pakd_odoo.php',
    '/projects/pakd/settings' => 'modules/projects/pakd_sync_settings.php',
    '/projects/ceo-review'    => 'modules/projects/ceo_review.php',
];

// DEBUG: log URI + match result
file_put_contents(__DIR__ . '/debug_router.log', date('H:i:s') . " URI=[$uri] IN_ROUTES=" . (array_key_exists($uri, $routes) ? 'YES' : 'NO') . "\n", FILE_APPEND);

// Check if route exists
if (array_key_exists($uri, $routes)) {
    $file = __DIR__ . '/' . $routes[$uri];
    file_put_contents(__DIR__ . '/debug_router.log', date('H:i:s') . " DEBUG: __DIR__=[" . __DIR__ . "] FILE=[" . $file . "] EXISTS=" . (file_exists($file) ? 'YES' : 'NO') . "\n", FILE_APPEND);

    if (file_exists($file)) {
        file_put_contents(__DIR__ . '/debug_router.log', date('H:i:s') . " MATCHED! Requiring: [$file]\n", FILE_APPEND);
        // Set the script filename for proper path resolution
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME'] = '/' . $routes[$uri];

        require $file;
        return true;
    }
}

// Check if it's a static file (CSS, JS, images, etc.)
$filePath = __DIR__ . $uri;

if (file_exists($filePath) && !is_dir($filePath)) {
    // Serve static files
    return false;
}

// 404 - Not found
http_response_code(404);
echo "<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 4rem;
            margin: 0;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            font-size: 1.2rem;
            color: #94a3b8;
        }
        a {
            color: #818cf8;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>404</h1>
        <p>Page not found</p>
        <p><a href='/'>Go to Home</a></p>
    </div>
</body>
</html>";
return true;
