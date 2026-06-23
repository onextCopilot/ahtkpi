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

// ── Serve static files (public + uploads) ────────────────────────────────
$_static_prefixes = ['/public/', '/uploads/'];
foreach ($_static_prefixes as $_pfx) {
    if (strpos($uri, $_pfx) === 0) {
        $file_path = __DIR__ . $uri;
        if (file_exists($file_path) && is_file($file_path)) {
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $mime_map = [
                'jpg'  => 'image/jpeg',       'jpeg' => 'image/jpeg',
                'png'  => 'image/png',         'gif'  => 'image/gif',
                'webp' => 'image/webp',        'svg'  => 'image/svg+xml',
                'css'  => 'text/css',          'js'   => 'application/javascript',
                'pdf'  => 'application/pdf',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls'  => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt'  => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'zip'  => 'application/zip',
                'txt'  => 'text/plain; charset=utf-8',
                'csv'  => 'text/csv; charset=utf-8',
            ];
            $mime = $mime_map[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($file_path));
            header('X-Content-Type-Options: nosniff');
            readfile($file_path);
            exit();
        }
        break;
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
    '/auth/webauthn/register-options' => 'modules/auth/webauthn/register_options.php',
    '/auth/webauthn/register'         => 'modules/auth/webauthn/register.php',
    '/auth/webauthn/login-options'    => 'modules/auth/webauthn/login_options.php',
    '/auth/webauthn/login'            => 'modules/auth/webauthn/login.php',
    '/auth/webauthn/list'             => 'modules/auth/webauthn/list.php',
    '/auth/webauthn/delete'           => 'modules/auth/webauthn/delete.php',
    '/dashboard' => 'modules/dashboard/dashboard.php',
    '/outbound-radar' => 'modules/outbound_radar/index.php',
    '/notifications' => 'modules/notifications/index.php',
    '/profile' => 'modules/profile/index.php',
    '/settings' => 'modules/settings/index.php',
    '/settings/users' => 'modules/settings/users/index.php',
    '/settings/users/edit' => 'modules/settings/users/edit.php',
    '/settings/departments' => 'modules/settings/departments/index.php',
    '/settings/core_members' => 'modules/settings/core_members/index.php',
    '/settings/smtp' => 'modules/settings/smtp/index.php',
    '/settings/debt-warning' => 'modules/settings/debt_warning/index.php',
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
    '/api/invoice_pakd_map' => 'api/invoice_pakd_map.php',
    '/api/invoice_pakd_map.php' => 'api/invoice_pakd_map.php',
    '/api/so_first_po' => 'api/so_first_po.php',
    '/api/so_first_po.php' => 'api/so_first_po.php',
    '/api/my_com_confirm' => 'api/my_com_confirm.php',
    '/api/my_com_confirm.php' => 'api/my_com_confirm.php',
    '/api/quarter_kpi' => 'api/quarter_kpi.php',
    '/api/quarter_kpi.php' => 'api/quarter_kpi.php',
    '/api/add_debt_from_invoice' => 'api/add_debt_from_invoice.php',
    '/api/add_debt_from_invoice.php' => 'api/add_debt_from_invoice.php',
    '/api/update_debt_inline' => 'api/update_debt_inline.php',
    '/api/update_debt_inline.php' => 'api/update_debt_inline.php',
    '/api/sync_debt' => 'api/sync_debt.php',
    '/api/sync_debt.php' => 'api/sync_debt.php',
    '/api/notif_read' => 'api/notif_read.php',
    '/api/notif_read.php' => 'api/notif_read.php',
    '/api/export/debts' => 'api/export/debts.php',
    '/api/export/debts.php' => 'api/export/debts.php',
    '/api/export/debts_pdf' => 'api/export/debts_pdf.php',
    '/api/export/debts_pdf.php' => 'api/export/debts_pdf.php',
    '/api/sync_rates' => 'api/sync_rates.php',
    '/api/sync_rates.php' => 'api/sync_rates.php',
    '/api/send_debt_warning' => 'api/send_debt_warning.php',
    '/api/send_debt_warning.php' => 'api/send_debt_warning.php',
    '/api/realtime_check_paid' => 'api/realtime_check_paid.php',
    '/api/realtime_check_paid.php' => 'api/realtime_check_paid.php',
    '/api/debt_warning_ack' => 'api/debt_warning_ack.php',
    '/api/debt_warning_ack.php' => 'api/debt_warning_ack.php',
    '/api/debt_week_confirm' => 'api/debt_week_confirm.php',
    '/api/debt_week_confirm.php' => 'api/debt_week_confirm.php',
    '/api/customers' => 'api/customers.php',
    '/api/customers.php' => 'api/customers.php',
    '/api/customer_notes' => 'api/customer_notes.php',
    '/api/customer_notes.php' => 'api/customer_notes.php',
    '/api/invoices' => 'api/invoices.php',
    '/api/invoices.php' => 'api/invoices.php',
    '/api/projects' => 'api/projects.php',
    '/api/projects.php' => 'api/projects.php',
    '/api/get_client_info' => 'api/get_client_info.php',
    '/api/get_client_info.php' => 'api/get_client_info.php',
    '/api/get_folio_detail' => 'api/get_folio_detail.php',
    '/api/get_folio_detail.php' => 'api/get_folio_detail.php',
    '/api/get_jira_project_info' => 'api/get_jira_project_info.php',
    '/api/get_jira_project_info.php' => 'api/get_jira_project_info.php',
    '/api/get_tempo_actual' => 'api/get_tempo_actual.php',
    '/api/get_tempo_actual.php' => 'api/get_tempo_actual.php',
    '/api/kpi_history' => 'api/kpi_history.php',
    '/api/kpi_history.php' => 'api/kpi_history.php',
    '/api/key_accounts_stats' => 'api/key_accounts_stats.php',
    '/api/key_accounts_stats.php' => 'api/key_accounts_stats.php',
    '/api/key_accounts_stats_v2' => 'api/key_accounts_stats_v2.php',
    '/api/key_accounts_stats_v2.php' => 'api/key_accounts_stats_v2.php',
    '/api/refresh_jira_cache' => 'api/refresh_jira_cache.php',
    '/api/refresh_jira_cache.php' => 'api/refresh_jira_cache.php',
    '/api/refresh_odoo_cache' => 'api/refresh_odoo_cache.php',
    '/api/refresh_odoo_cache.php' => 'api/refresh_odoo_cache.php',
    '/api/refresh_odoo_invoices' => 'api/refresh_odoo_invoices.php',
    '/api/refresh_odoo_invoices.php' => 'api/refresh_odoo_invoices.php',
    '/api/save_dept_order' => 'api/save_dept_order.php',
    '/api/save_dept_order.php' => 'api/save_dept_order.php',
    '/api/toggle_wishlist' => 'api/toggle_wishlist.php',
    '/api/toggle_wishlist.php' => 'api/toggle_wishlist.php',
    '/api/pasx/message' => 'api/pasx_message_receive.php',
    '/api/request_production_plan' => 'api/request_production_plan.php',
    '/api/request_production_plan.php' => 'api/request_production_plan.php',
    '/api/pasx/callback'           => 'api/pasx_callback.php',
    '/api/pasx/callback.php'       => 'api/pasx_callback.php',
    '/integrations/hrm/milestones/sync' => 'api/milestones_sync.php',
    '/projects/milestones/logs'         => 'modules/projects/milestone_logs.php',
    '/api/milestone_log_clear'          => 'api/milestone_log_clear.php',
    '/api/milestone_log_clear.php'      => 'api/milestone_log_clear.php',
    '/api/milestone_push_payment'       => 'api/milestone_push_payment.php',
    '/api/milestone_push_payment.php'   => 'api/milestone_push_payment.php',
    '/odoo/hook'                   => 'api/odoo_hook.php',
    '/odoo/hook.php'               => 'api/odoo_hook.php',
    '/odoo/logs'                   => 'modules/odoo_logs/index.php',
    '/api/odoo_log_detail'         => 'api/odoo_log_detail.php',
    '/api/odoo_log_detail.php'     => 'api/odoo_log_detail.php',
    '/api/odoo_log_clear'          => 'api/odoo_log_clear.php',
    '/api/odoo_log_clear.php'      => 'api/odoo_log_clear.php',
    '/debt' => 'modules/debt/index.php',
    '/my-debt' => 'modules/my_debt/index.php',
    '/debts-check' => 'modules/debts_check/index.php',
    '/my-com' => 'modules/my_com/index.php',
    '/my-com/yearly-bonus' => 'modules/my_com/yearly_bonus.php',
    '/commission-board' => 'modules/commission_board/index.php',
    '/debt-warning' => 'modules/debt_warning/index.php',
    '/customers' => 'modules/customers/index.php',
    '/invoices' => 'modules/invoices/index.php',
    '/kpi' => 'modules/kpi/index.php',
    '/api/kpi_tab_order' => 'api/kpi_tab_order.php',
    '/api/kpi_tab_order.php' => 'api/kpi_tab_order.php',
    '/api/kpi_sort' => 'api/kpi_sort.php',
    '/api/kpi_sort.php' => 'api/kpi_sort.php',
    '/api/kpi_monthly_save' => 'api/kpi_monthly_save.php',
    '/api/kpi_monthly_save.php' => 'api/kpi_monthly_save.php',
    '/api/kpi_quarterly_save' => 'api/kpi_quarterly_save.php',
    '/api/kpi_quarterly_save.php' => 'api/kpi_quarterly_save.php',
    '/api/mark_notification_read' => 'api/mark_notification_read.php',
    '/api/mark_notification_read.php' => 'api/mark_notification_read.php',
    '/api/notifications/mark_read' => 'api/notifications_mark_read.php',
    '/api/notifications/mark_read.php' => 'api/notifications_mark_read.php',
    '/sale-orders' => 'modules/sale_orders/index.php',
    '/api/sale_orders' => 'api/sale_orders.php',
    '/api/sale_orders.php' => 'api/sale_orders.php',
    '/my-reports' => 'modules/sale_reports/index.php',
    '/detail-report' => 'modules/sale_reports/detail.php',
    '/detail_report' => 'modules/sale_reports/detail.php',
    '/sale-reports' => 'modules/sale_reports/index.php',
    '/sale_reports' => 'modules/sale_reports/index.php',
    '/sale-reports-admin' => 'modules/sale_reports_admin/index.php',
    '/bc-reports' => 'modules/bc_reports/index.php',
    '/api/bc_reports' => 'api/bc_reports.php',
    '/api/bc_reports.php' => 'api/bc_reports.php',
    '/api/bc_settings' => 'api/bc_settings.php',
    '/api/bc_settings.php' => 'api/bc_settings.php',
    '/guides' => 'modules/guides/index.php',
    '/core-key-kpi' => 'modules/core_kpi/index.php',
    '/plan-budgeting' => 'modules/plan_budgeting/index.php',
    '/plan-budgeting/report' => 'modules/plan_budgeting/report.php',
    '/documents' => 'modules/documents/index.php',
    '/folio'     => 'modules/folio/index.php',
    // ── HRM / Recruitment (rebuilt 2026, SOP-driven) ──────────────────
    '/hrm' => 'modules/hrm/index.php',
    '/hrm/recruitment' => 'modules/hrm/recruitment.php',
    '/hrm/plan' => 'modules/hrm/plan.php',
    '/hrm/requests' => 'modules/hrm/requests.php',
    '/hrm/jobs' => 'modules/hrm/jobs.php',
    '/hrm/job' => 'modules/hrm/job.php',
    '/hrm/application' => 'modules/hrm/application.php',
    '/hrm/candidates' => 'modules/hrm/candidates.php',
    '/hrm/candidate' => 'modules/hrm/candidate.php',
    '/hrm/onboarding' => 'modules/hrm/onboarding.php',
    '/hrm/onboarding-detail' => 'modules/hrm/onboarding_detail.php',
    '/hrm/probation' => 'modules/hrm/probation.php',
    '/hrm/kpi' => 'modules/hrm/kpi.php',
    '/hrm/settings' => 'modules/hrm/settings.php',
    '/hrm/api' => 'modules/hrm/api.php',
    '/hrm/linkedin-oauth' => 'modules/hrm/linkedin_oauth.php',
    '/hrm/webhook.php' => 'modules/hrm/webhook.php',
    '/hrm/webhook' => 'modules/hrm/webhook.php',
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