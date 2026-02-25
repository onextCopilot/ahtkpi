<?php
require_once __DIR__ . '/../../../config/config.php';
// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$avatar = $_SESSION['avatar'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Core Members - Settings</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php
            $page_title = 'Core Members';
            $page_subtitle = 'Manage key personnel and core keys';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div
                    style="background: white; padding: 2rem; border-radius: 12px; border: 1px solid var(--border-color); text-align: center;">
                    <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Core Member Management</h3>
                    <p style="color: var(--text-secondary);">This module is for managing core members separate from
                        general users.</p>
                </div>
            </div>
        </main>
    </div>
</body>

</html>