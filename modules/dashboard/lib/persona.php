<?php
/**
 * Resolve a "dashboard persona" from the current session flags.
 *
 * Returns one of: 'ceo' | 'manager' | 'am_bd' | 'member'.
 *
 * Rules (in priority order):
 *   - is_am_bd users get the sales-focused view — even if they are also an
 *     admin, since the sales dashboard is what's relevant to a salesperson.
 *   - admin role (not flagged as sales) gets the company-wide (CEO) view.
 *   - manager role gets the team view.
 *   - everyone else gets the personal (member) view.
 *
 * An admin/owner who is also is_am_bd can still reach the company-wide
 * overview via /dashboard?view=ceo (see dashboard.php).
 */
function resolveDashboardPersona(): string
{
    $role = $_SESSION['role'] ?? 'user';

    if (!empty($_SESSION['is_am_bd'])) {
        return 'am_bd';
    }
    if ($role === 'admin') {
        return 'ceo';
    }
    if ($role === 'manager') {
        return 'manager';
    }
    return 'member';
}
