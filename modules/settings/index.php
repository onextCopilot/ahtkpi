<?php
require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Check if user has admin privileges
if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;

// Fetch online users (active within the last 15 minutes)
$online_users = [];
$res = $conn->query("SELECT id, full_name, email, role, avatar, last_active FROM users WHERE last_active >= NOW() - INTERVAL 15 MINUTE ORDER BY last_active DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $online_users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .setting-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            text-decoration: none;
            color: inherit;
        }

        .setting-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.05);
            border-color: var(--primary-color);
        }

        .setting-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .setting-card:hover .setting-icon {
            background: var(--primary-color);
            color: white;
        }

        .setting-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .setting-info p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .setting-arrow {
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-color);
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s ease;
        }

        .setting-card:hover .setting-arrow {
            opacity: 1;
            transform: translateX(0);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $page_title = 'System Settings';
            $page_subtitle = 'Manage system configurations and resources';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div class="settings-grid">
                    <!-- Departments Module -->
                    <a href="/settings/departments" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Departments Management</h3>
                            <p>Create, edit, and organize company departments and heirarchy.</p>
                        </div>
                        <div class="setting-arrow">
                            Manage Departments
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Core Members Module -->
                    <a href="/settings/core-members" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Core Key Members</h3>
                            <p>Manage key personnel, assign roles, and track core member activities.</p>
                        </div>
                        <div class="setting-arrow">
                            Manage Members
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Users Module -->
                    <a href="/settings/users" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Users Management</h3>
                            <p>Manage all users, IT roles, profiles and account status.</p>
                        </div>
                        <div class="setting-arrow">
                            Manage Users
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- SMTP Module -->
                    <a href="/settings/smtp" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                </path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>SMTP Settings</h3>
                            <p>Configure email server settings for system notifications.</p>
                        </div>
                        <div class="setting-arrow">
                            Configure Email
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Odoo API Module -->
                    <a href="/settings/odoo" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                                </path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Odoo API Configuration</h3>
                            <p>Configure Odoo ERP connection for customer data integration.</p>
                        </div>
                        <div class="setting-arrow">
                            Configure API
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Jira Software Module -->
                    <a href="/settings/jira" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                                </path>
                                <polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline>
                                <polyline points="7.5 19.79 7.5 14.6 3 12"></polyline>
                                <polyline points="21 12 16.5 14.6 16.5 19.79"></polyline>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Jira Software</h3>
                            <p>Connect to Jira (cyclethis.com) for issue tracking integration.</p>
                        </div>
                        <div class="setting-arrow">
                            Configure Jira
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Sale Teams Module -->
                    <a href="/settings/teams" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Setup Sale Team setup</h3>
                            <p>Configure sales teams, ordering, and team membership.</p>
                        </div>
                        <div class="setting-arrow">
                            Manage Sale Teams
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Sale Level Setup Module -->
                    <a href="/settings/sale-levels" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="20" x2="12" y2="10"></line>
                                <line x1="18" y1="20" x2="18" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="16"></line>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Sale Level Setup</h3>
                            <p>Configure sale levels, targets, and criteria.</p>
                        </div>
                        <div class="setting-arrow">
                            Manage Sale Levels
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Auto Backup Module -->
                    <a href="/settings/backup" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"></path>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Database Autobackup</h3>
                            <p>Configure automatic database backups, frequency, and retention policy.</p>
                        </div>
                        <div class="setting-arrow">
                            Configure Backup
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Odoo Currency Rates Module -->
                    <a href="/settings/odoo-rates" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Odoo Currency Rates</h3>
                            <p>View and synchronize exchange rates directly from Odoo ERP.</p>
                        </div>
                        <div class="setting-arrow">
                            View Rates
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- AI Workflow Module -->
                    <a href="/settings/workflow" class="setting-card">
                        <div class="setting-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"></path>
                                <path d="M12 6v6l4 2"></path>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>AI Workflow Settings</h3>
                            <p>Configure API Key for AI Agent workflows and OKR suggestions.</p>
                        </div>
                        <div class="setting-arrow">
                            Configure Workflow
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- AI Hive Configuration Module -->
                    <a href="/settings/aihive" class="setting-card" style="border-color: #6366f1;">
                        <div class="setting-icon" style="color: #6366f1; background: #eef2ff;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>AI Hive (Presale)</h3>
                            <p>Configure API Key and Model for the Sale/Presale AI Assistant.</p>
                        </div>
                        <div class="setting-arrow" style="color: #6366f1;">
                            Configure AI Hive
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>

                    <!-- Presale Prompts Management Module -->
                    <a href="/settings/presale-prompts" class="setting-card" style="border-color: #f59e0b;">
                        <div class="setting-icon" style="color: #f59e0b; background: #fef3c7;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                <line x1="9" y1="10" x2="15" y2="10"></line>
                                <line x1="9" y1="14" x2="15" y2="14"></line>
                            </svg>
                        </div>
                        <div class="setting-info">
                            <h3>Presale Prompts</h3>
                            <p>Manage system prompts and quick actions for the Presale AI Assistant.</p>
                        </div>
                        <div class="setting-arrow" style="color: #f59e0b;">
                            Manage Prompts
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                    </a>
                </div>

                <!-- Online Users Block -->
                <div
                    style="margin-top: 3rem; background: white; border-radius: 16px; padding: 1.5rem; border: 1px solid var(--border-color);">
                    <h3
                        style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                        <span
                            style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); animation: pulse 2s infinite;"></span>
                        Online Users (<?php echo count($online_users); ?>)
                        <span
                            style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary); margin-left: auto;">(Active
                            in the last 15m)</span>
                    </h3>

                    <style>
                        @keyframes pulse {
                            0% {
                                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
                            }

                            70% {
                                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
                            }

                            100% {
                                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
                            }
                        }

                        .online-user-card {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                            padding: 10px 14px;
                            background: var(--bg-secondary);
                            border-radius: 12px;
                            border: 1px solid var(--border-color);
                        }

                        .ou-avatar {
                            width: 36px;
                            height: 36px;
                            border-radius: 50%;
                            object-fit: cover;
                            background: var(--primary-color);
                            color: white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-weight: 600;
                            font-size: 14px;
                        }

                        .ou-info {
                            display: flex;
                            flex-direction: column;
                        }

                        .ou-name {
                            font-size: 0.95rem;
                            font-weight: 500;
                            color: var(--text-primary);
                        }

                        .ou-role {
                            font-size: 0.75rem;
                            color: var(--text-secondary);
                            text-transform: capitalize;
                        }
                    </style>

                    <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                        <?php foreach ($online_users as $ou): ?>
                            <div class="online-user-card">
                                <?php if (!empty($ou['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($ou['avatar']); ?>" class="ou-avatar" alt="Avatar">
                                <?php else: ?>
                                    <div class="ou-avatar">
                                        <?php echo strtoupper(substr($ou['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ou-info">
                                    <span class="ou-name"><?php echo htmlspecialchars($ou['full_name']); ?></span>
                                    <span class="ou-role"><?php echo htmlspecialchars($ou['role']); ?> &bull; Active just
                                        now</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($online_users)): ?>
                            <span style="color: var(--text-secondary); font-size: 0.9rem;">No users online right now.</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>