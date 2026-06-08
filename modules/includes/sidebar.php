<?php
$current_uri = $_SERVER['REQUEST_URI'];

// Backfill is_marketer into the session for users who logged in before this flag existed
// (permissions are session-based; this avoids forcing a re-login after the flag is granted).
if (isset($_SESSION['user_id']) && isset($conn) && !isset($_SESSION['is_marketer'])) {
    $_SESSION['is_marketer'] = 0; // safe default if the column/query is unavailable
    try {
        if ($mkStmt = $conn->prepare("SELECT is_marketer FROM users WHERE id = ?")) {
            $mkStmt->bind_param("i", $_SESSION['user_id']);
            $mkStmt->execute();
            if ($mkRow = $mkStmt->get_result()->fetch_assoc()) $_SESSION['is_marketer'] = (int) ($mkRow['is_marketer'] ?? 0);
            $mkStmt->close();
        }
    } catch (Throwable $e) { /* column may not exist yet on older DBs */ }
}

// Marketer-only user (no other debt privilege): under Debts Management they may see ONLY My Com.
$_sidebar_mc_only = !empty($_SESSION['is_marketer'])
    && empty($_SESSION['is_am_bd'])
    && empty($_SESSION['can_view_invoice'])
    && (($_SESSION['role'] ?? '') !== 'admin');

// Check if current user is a CEO approver
$_sidebar_is_ceo_approver = false;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $caRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='pasx_ceo_approvers' LIMIT 1");
    if ($caRes && $caRow = $caRes->fetch_assoc()) {
        $caList = array_map('intval', json_decode($caRow['setting_value'] ?? '[]', true) ?: []);
        $_sidebar_is_ceo_approver = in_array((int)$_SESSION['user_id'], $caList);
    }
}

function isMenuItemActive($path, $current_uri)
{
    // Exact match for dashboard
    if ($path === '/dashboard' || $path === '/') {
        return ($current_uri === '/dashboard' || $current_uri === '/' || strpos($current_uri, '/modules/dashboard') !== false) ? 'active' : '';
    }
    // Partial match for others
    return strpos($current_uri, $path) !== false ? 'active' : '';
}
?>
<aside class="sidebar">
    <div class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle Sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <path d="M9 3v18"/>
            <path d="m14 15-3-3 3-3"/>
        </svg>
    </div>
    <div class="sidebar-header">
        <a href="/dashboard" class="logo">
            <img src="https://www.arrowhitech.com/wp-content/uploads/2025/06/Logo.svg" alt="ArrowHitech Logo"
                class="logo-image">
        </a>
    </div>

    <nav class="sidebar-nav">
        <!-- 1. Dashboard -->
        <a href="/dashboard" class="nav-item <?php echo isMenuItemActive('/dashboard', $current_uri); ?>">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
                <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
                <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
                <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- 2. Planning & Budgeting -->
        <a href="/plan-budgeting"
            class="nav-item <?php echo (strpos($current_uri, '/plan-budgeting') !== false) ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v20"></path>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <span>Planning & Budgeting</span>
        </a>

        <!-- 3. Debts Management Dropdown -->
        <?php if (!empty($_SESSION['can_view_invoice']) || !empty($_SESSION['is_am_bd']) || !empty($_SESSION['is_marketer']) || $_SESSION['role'] === 'admin'): ?>
            <div class="nav-item nav-item-parent <?php
            $is_debt_open = strpos($current_uri, '/debt') !== false ||
                strpos($current_uri, '/my-debt') !== false ||
                strpos($current_uri, '/my-com') !== false ||
                strpos($current_uri, '/commission-board') !== false ||
                strpos($current_uri, '/debt-warning') !== false ||
                (strpos($current_uri, '/my-reports') !== false && strpos($current_uri, '/sale-reports-admin') === false);
            echo $is_debt_open ? 'active open' : ''; ?>" onclick="toggleSubmenu(this)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Debts Management</span>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?php echo $is_debt_open ? 'open' : ''; ?>">
                <?php if (!$_sidebar_mc_only): ?>
                <a href="/debt" class="submenu-item <?php echo ($current_uri === '/debt') ? 'active' : ''; ?>">
                    <span>All Debts</span>
                </a>
                <a href="/my-debt"
                    class="submenu-item <?php echo ($current_uri === '/my-debt' || strpos($current_uri, '/my-debt') !== false) ? 'active' : ''; ?>">
                    <span>My Debts</span>
                </a>
                <a href="/debt-warning"
                    class="submenu-item <?php echo ($current_uri === '/debt-warning' || strpos($current_uri, '/debt-warning') !== false) ? 'active' : ''; ?>">
                    <span>Debts Warning</span>
                </a>
                <a href="/my-reports"
                    class="submenu-item <?php echo (strpos($current_uri, '/my-reports') !== false && strpos($current_uri, '/sale-reports-admin') === false) ? 'active' : ''; ?>">
                    <span>My Reports</span>
                </a>
                <?php endif; ?>
                <?php if (!empty($_SESSION['is_am_bd']) || !empty($_SESSION['is_marketer']) || $_SESSION['role'] === 'admin'): ?>
                <a href="/my-com"
                    class="submenu-item <?php echo strpos($current_uri, '/my-com') !== false ? 'active' : ''; ?>">
                    <span>My Com</span>
                </a>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin' || ($_SESSION['full_name'] ?? '') === 'Hyun Cao'): ?>
                <a href="/commission-board"
                    class="submenu-item <?php echo strpos($current_uri, '/commission-board') !== false ? 'active' : ''; ?>">
                    <span>Commission Board</span>
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 4. Accounting Dropdown -->
        <?php if (!empty($_SESSION['can_view_invoice']) || $_SESSION['role'] === 'admin'): ?>
            <div class="nav-item nav-item-parent <?php
            $is_acc_open = strpos($current_uri, '/customers') !== false ||
                strpos($current_uri, '/invoices') !== false ||
                strpos($current_uri, '/sale-orders') !== false ||
                strpos($current_uri, '/sale-reports-admin') !== false;
            echo $is_acc_open ? 'active open' : ''; ?>" onclick="toggleSubmenu(this)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 1v22m10-17L12 1 2 6l10 5 10-5Z"></path>
                    <path d="m2 18 10 5 10-5"></path>
                    <path d="m2 12 10 5 10-5"></path>
                </svg>
                <span>Accounting</span>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?php echo $is_acc_open ? 'open' : ''; ?>">
                <a href="/customers" class="submenu-item <?php echo ($current_uri === '/customers') ? 'active' : ''; ?>">
                    <span>Customers</span>
                </a>
                <a href="/invoices" class="submenu-item <?php echo ($current_uri === '/invoices') ? 'active' : ''; ?>">
                    <span>Invoices</span>
                </a>
                <a href="/sale-orders"
                    class="submenu-item <?php echo (strpos($current_uri, '/sale-orders') !== false) ? 'active' : ''; ?>">
                    <span>Sale Orders</span>
                </a>
                <a href="/sale-reports-admin"
                    class="submenu-item <?php echo (strpos($current_uri, '/sale-reports-admin') !== false) ? 'active' : ''; ?>">
                    <span>Sale Reports</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- 5. Business centers (BC) Dropdown -->
        <?php
        $has_bc_access = false;
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $has_bc_access = true;
        } else {
            // Check bc accesses manually
            $bc_chk_stmt = $conn->prepare("SELECT COUNT(*) as count FROM bc_permissions WHERE user_id = ?");
            if ($bc_chk_stmt) {
                $bc_chk_stmt->bind_param("i", $_SESSION['user_id']);
                $bc_chk_stmt->execute();
                $bc_res = $bc_chk_stmt->get_result();
                if ($bc_row = $bc_res->fetch_assoc()) {
                    $has_bc_access = $bc_row['count'] > 0;
                }
                $bc_chk_stmt->close();
            }
        }
        
        // Folio is now part of BC section
        $is_folio_active = strpos($current_uri, '/folio') !== false;
        
        if ($has_bc_access || $is_folio_active || true): // Always show if we want Folio visible here ?>
            <div class="nav-item nav-item-parent <?php
            $is_bc_open = strpos($current_uri, '/bc-reports') !== false || $is_folio_active;
            echo $is_bc_open ? 'active open' : ''; ?>" onclick="toggleSubmenu(this)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>
                </svg>
                <span>Business centers (BC)</span>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="submenu <?php echo $is_bc_open ? 'open' : ''; ?>">
                <?php if ($has_bc_access): ?>
                <a href="/bc-reports"
                    class="submenu-item <?php echo (strpos($current_uri, '/bc-reports') !== false) ? 'active' : ''; ?>">
                    <span>BC Reports</span>
                </a>
                <?php endif; ?>
                
                <a href="/folio" class="submenu-item <?php echo $is_folio_active ? 'active' : ''; ?>">
                    <span>Folio – Jira Projects</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- 6. KPI Management Dropdown -->
        <div class="nav-item nav-item-parent <?php
        $is_kpi_open = ($current_uri === '/kpi') || strpos($current_uri, '/core-key-kpi') !== false || strpos($current_uri, '/guides') !== false;
        echo $is_kpi_open ? 'active open' : ''; ?>" onclick="toggleSubmenu(this)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span>KPI Management</span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>
        <div class="submenu <?php echo $is_kpi_open ? 'open' : ''; ?>">
            <a href="/kpi" class="submenu-item <?php echo ($current_uri === '/kpi') ? 'active' : ''; ?>">
                <span>General KPI Management</span>
            </a>
            <a href="/core-key-kpi"
                class="submenu-item <?php echo ($current_uri === '/core-key-kpi') ? 'active' : ''; ?>">
                <span>Core & Key KPI</span>
            </a>
            <a href="/guides" class="submenu-item <?php echo ($current_uri === '/guides') ? 'active' : ''; ?>">
                <span>Guides</span>
            </a>
        </div>

        <!-- 7. OKR Management -->
        <a href="/modules/okr" class="nav-item <?php echo isMenuItemActive('/modules/okr', $current_uri); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <circle cx="12" cy="12" r="6"></circle>
                <circle cx="12" cy="12" r="2"></circle>
            </svg>
            <span>OKR Management</span>
        </a>

        <!-- 8. Projects Dropdown (AM/BD + Admin only) -->
        <?php if (!empty($_SESSION['is_am_bd']) || ($_SESSION['role'] ?? '') === 'admin'): ?>
        <div class="nav-item nav-item-parent <?php
        $is_projects_open = strpos($current_uri, '/projects/phuong-an-kinh-doanh') !== false ||
            strpos($current_uri, '/projects/du-an') !== false ||
            strpos($current_uri, '/projects/ceo-review') !== false;
        echo $is_projects_open ? 'active open' : ''; ?>" onclick="toggleSubmenu(this)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                <line x1="12" y1="11" x2="12" y2="17"></line>
                <line x1="9" y1="14" x2="15" y2="14"></line>
            </svg>
            <span>Projects</span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>
        <div class="submenu <?php echo $is_projects_open ? 'open' : ''; ?>">
            <a href="/projects/phuong-an-kinh-doanh"
                class="submenu-item <?php echo (strpos($current_uri, '/projects/phuong-an-kinh-doanh') !== false) ? 'active' : ''; ?>">
                <span>Business Plans</span>
            </a>
            <a href="/projects/du-an"
                class="submenu-item <?php echo (strpos($current_uri, '/projects/du-an') !== false) ? 'active' : ''; ?>">
                <span>My Project</span>
            </a>
            <?php if ($_sidebar_is_ceo_approver): ?>
            <a href="/projects/ceo-review"
                class="submenu-item <?php echo (strpos($current_uri, '/projects/ceo-review') !== false) ? 'active' : ''; ?>"
                style="display:flex;align-items:center;gap:6px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:0.7;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                    <polyline points="9 11 12 14 22 4"/>
                </svg>
                <span>CEO Review</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 8. HRM -->
        <a href="/hrm" class="nav-item <?php echo isMenuItemActive('/hrm', $current_uri); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span>HRM</span>
        </a>

        <!-- 9. Documents Dropdown -->
        <div class="nav-item nav-item-parent <?php
        $is_docs_open = strpos($current_uri, '/documents') !== false ||
            strpos($current_uri, '/tai-lieu-quy-trinh') !== false;
        echo $is_docs_open ? 'active open' : ''; ?>" onclick="toggleSubmenu(this)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                <polyline points="13 2 13 9 20 9"></polyline>
            </svg>
            <span>Documents</span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </div>
        <div class="submenu <?php echo $is_docs_open ? 'open' : ''; ?>">
            <a href="/tai-lieu-quy-trinh" class="submenu-item <?php echo (strpos($current_uri, '/tai-lieu-quy-trinh') !== false) ? 'active' : ''; ?>">
                <span>Tài Liệu - Quy Trình</span>
            </a>
            <a href="/documents" class="submenu-item <?php echo ($current_uri === '/documents' || strpos($current_uri, '/documents') !== false) ? 'active' : ''; ?>">
                <span>Drive</span>
            </a>
        </div>

        <!-- 10. Sale Assistant -->
        <?php if ($_SESSION['role'] === 'admin' || ($_SESSION['full_name'] ?? '') === 'Hyun Cao'): ?>
        <a href="/presale" class="nav-item <?php echo isMenuItemActive('/presale', $current_uri); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                <path d="M14 8h-4a2 2 0 0 0-2 2v4"></path>
                <circle cx="12" cy="14" r="1"></circle>
            </svg>
            <span>Sale Assistant</span>
        </a>
        <?php endif; ?>

        <a href="/profile" class="nav-item <?php echo isMenuItemActive('/profile', $current_uri); ?>"
            style="margin-top: 2rem;">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path
                    d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>My Profile</span>
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="/settings" class="nav-item <?php echo isMenuItemActive('/settings', $current_uri); ?>">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                    <path
                        d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.258 9.77251 19.9887C9.5799 19.7194 9.31074 19.5143 9 19.4C8.69838 19.2669 8.36381 19.2272 8.03941 19.286C7.71502 19.3448 7.41568 19.4995 7.18 19.73L7.12 19.79C6.93425 19.976 6.71368 20.1235 6.47088 20.2241C6.22808 20.3248 5.96783 20.3766 5.705 20.3766C5.44217 20.3766 5.18192 20.3248 4.93912 20.2241C4.69632 20.1235 4.47575 19.976 4.29 19.79C4.10405 19.6043 3.95653 19.3837 3.85588 19.1409C3.75523 18.8981 3.70343 18.6378 3.70343 18.375C3.70343 18.1122 3.75523 17.8519 3.85588 17.6091C3.95653 17.3663 4.10405 17.1457 4.29 16.96L4.35 16.9C4.58054 16.6643 4.73519 16.365 4.794 16.0406C4.85282 15.7162 4.81312 15.3816 4.68 15.08C4.55324 14.7842 4.34276 14.532 4.07447 14.3543C3.80618 14.1766 3.49179 14.0813 3.17 14.08H3C2.46957 14.08 1.96086 13.8693 1.58579 13.4942C1.21071 13.1191 1 12.6104 1 12.08C1 11.5496 1.21071 11.0409 1.58579 10.6658C1.96086 10.2907 2.46957 10.08 3 10.08H3.09C3.42099 10.0723 3.742 9.96512 4.0113 9.77251C4.28059 9.5799 4.48572 9.31074 4.6 9C4.73312 8.69838 4.77282 8.36381 4.714 8.03941C4.65519 7.71502 4.50054 7.41568 4.27 7.18L4.21 7.12C4.02405 6.93425 3.87653 6.71368 3.77588 6.47088C3.67523 6.22808 3.62343 5.96783 3.62343 5.705C3.62343 5.44217 3.67523 5.18192 3.77588 4.93912C3.87653 4.69632 4.02405 4.47575 4.21 4.29C4.39575 4.10405 4.61632 3.95653 4.85912 3.85588C5.10192 3.75523 5.36217 3.70343 5.625 3.70343C5.88783 3.70343 6.14808 3.75523 6.39088 3.85588C6.63368 3.95653 6.85425 4.10405 7.04 4.29L7.1 4.35C7.33568 4.58054 7.63502 4.73519 7.95941 4.794C8.28381 4.85282 8.61838 4.81312 8.92 4.68H9C9.29577 4.55324 9.54802 4.34276 9.72569 4.07447C9.90337 3.80618 9.99872 3.49179 10 3.17V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15Z"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>Settings</span>
            </a>
        <?php endif; ?>

        <a href="/logout" class="nav-item logout"
            style="margin-top: auto; border-top: 1px solid #334155; padding-top: 1.5rem; border-radius: 0;">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
                <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        if (!sidebar) return;

        const isCollapsed = sidebar.classList.toggle('collapsed');
        if (mainContent) mainContent.classList.toggle('collapsed');
        
        localStorage.setItem('sidebar-collapsed', isCollapsed);
    }

    // Initialize sidebar state on load
    (function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        
        if (isCollapsed) {
            if (sidebar) sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('collapsed');
        }

        // Expand sidebar on click if it's collapsed
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (sidebarNav) {
            sidebarNav.addEventListener('click', function(e) {
                if (sidebar && sidebar.classList.contains('collapsed')) {
                    const navItem = e.target.closest('.nav-item');
                    if (navItem) {
                        toggleSidebar();
                    }
                }
            }, true); // Use capture phase to catch the click before navigation or submenu toggle
        }
    })();

    function toggleSubmenu(element) {
        const isOpening = !element.classList.contains('open');

        if (isOpening) {
            // Close all other open submenus
            document.querySelectorAll('.nav-item-parent.open').forEach(el => {
                if (el !== element) {
                    el.classList.remove('open');
                    const sub = el.nextElementSibling;
                    if (sub && sub.classList.contains('submenu')) {
                        sub.classList.remove('open');
                    }
                }
            });
        }

        // Toggle the clicked menu
        element.classList.toggle('open');
        const submenu = element.nextElementSibling;
        if (submenu && submenu.classList.contains('submenu')) {
            submenu.classList.toggle('open');
        }
    }
</script>