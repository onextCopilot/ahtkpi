<?php
/**
 * Module-level access control for restricted roles.
 *
 * Most roles (admin, user, …) are unrestricted by this mechanism — their access
 * is governed by the existing per-feature flags + sidebar conditions.
 *
 * A restricted role (currently only 'hr') may reach ONLY an allow-list of path
 * prefixes; any other URL is hard-blocked (redirect for pages, 403 for APIs).
 * Add new restricted roles by extending app_restricted_role_prefixes().
 */

/** Allow-list of path prefixes for a restricted role, or null = unrestricted. */
function app_restricted_role_prefixes(string $role): ?array
{
    switch ($role) {
        case 'hr':
            return [
                '/hrm',                       // toàn bộ module HRM (gồm /hrm/api)
                '/tai-lieu-quy-trinh',        // Tài liệu - Quy trình
                '/documents',                 // Drive
                '/profile', '/modules/profile',
                '/notifications',
                '/api/hrm',                   // mark-read notif HRM
                '/api/notifications',         // mark-read chung
                '/api/mark_notification_read',
                '/api/kpi_alert_dismiss',
                '/login', '/logout',
            ];
        default:
            return null; // không giới hạn
    }
}

/** Trang mặc định của một role bị giới hạn (đích redirect khi vào trang cấm). */
function app_restricted_role_home(string $role): string
{
    return $role === 'hr' ? '/hrm' : '/dashboard';
}

/** Enforce access for the current request. No-op for guests / unrestricted roles. */
function app_enforce_module_access(): void
{
    if (empty($_SESSION['user_id'])) { return; }              // chưa đăng nhập -> để luồng login xử lý
    $role = (string) ($_SESSION['role'] ?? '');
    $allowed = app_restricted_role_prefixes($role);
    if ($allowed === null) { return; }                        // role không bị giới hạn

    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = strtolower(urldecode($path));
    $path = preg_replace('#^/(router|index)\.php#', '', $path);
    $path = rtrim($path, '/');
    if ($path === '') { $path = '/'; }

    foreach ($allowed as $pfx) {
        if ($path === $pfx || strpos($path, $pfx . '/') === 0) { return; } // được phép
    }

    // Bị cấm: API -> 403 JSON; trang -> redirect về trang chủ của role.
    $isApi = strpos($path, '/api/') === 0
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if ($isApi) {
        if (!headers_sent()) { http_response_code(403); header('Content-Type: application/json'); }
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
    if (!headers_sent()) { header('Location: ' . app_restricted_role_home($role)); }
    exit;
}

app_enforce_module_access();
