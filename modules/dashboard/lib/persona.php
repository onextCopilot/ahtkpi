<?php
/**
 * Resolve a "dashboard persona" from the current session + DB.
 *
 * Returns one of: 'ceo' | 'manager' | 'am_bd' | 'member'.
 *
 * Rules (in priority order):
 *   - is_am_bd users get the sales-focused view — even if they are also an
 *     admin/manager, since the sales dashboard is what's relevant to them.
 *   - admin role gets the company-wide (CEO) view.
 *   - department heads get the team (Manager) view. A user is a department head
 *     if they are listed as departments.manager_id of at least one department
 *     (auto-detected — no special role needs to be assigned). role='manager'
 *     is also honoured as a fallback.
 *   - everyone else gets the personal (member) view.
 *
 * Pass $conn to enable the department-head auto-detection; without it the
 * function falls back to session-only rules.
 *
 * An admin/owner who is also is_am_bd can still reach the company-wide
 * overview via /dashboard?view=ceo (see dashboard.php).
 */
function resolveDashboardPersona($conn = null): string
{
    $role = $_SESSION['role'] ?? 'user';
    $uid  = (int) ($_SESSION['user_id'] ?? 0);

    if (!empty($_SESSION['is_am_bd'])) {
        return 'am_bd';
    }
    if ($role === 'admin') {
        return 'ceo';
    }
    if ($role === 'manager') {
        return 'manager';
    }
    if ($conn && $uid && dashboardUserManagesDepartment($conn, $uid)) {
        return 'manager';
    }
    return 'member';
}

/**
 * True if the user is set as the manager (head) of any department.
 */
function dashboardUserManagesDepartment($conn, int $user_id): bool
{
    // Guard: the manager_id column may not exist on older schemas.
    $col = $conn->query("SHOW COLUMNS FROM departments LIKE 'manager_id'");
    if (!$col || $col->num_rows === 0) {
        return false;
    }
    $res = $conn->query("SELECT 1 FROM departments WHERE manager_id = " . (int) $user_id . " LIMIT 1");
    return $res && $res->num_rows > 0;
}
