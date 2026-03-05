<?php
/**
 * Router for PHP Built-in Server
 * 
 * This file handles routing when using PHP's built-in web server
 * Usage: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// Remove query string
$uri = strtok($uri, '?');

// Remove trailing slash except for root
if ($uri !== '/') {
    $uri = rtrim($uri, '/');
}

// Route mapping
$routes = [
    '/' => 'index.php',
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
    '/settings/teams' => 'modules/settings/teams/index.php',
    '/settings/sale-levels' => 'modules/settings/sale-levels/index.php',
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
    '/sale-reports' => 'modules/sale_reports/index.php',
];

// Check if route exists
if (array_key_exists($uri, $routes)) {
    $file = __DIR__ . '/' . $routes[$uri];

    if (file_exists($file)) {
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
