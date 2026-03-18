<?php
require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Core/Key KPI Management</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $page_title = 'Core KPI Management';
            $page_subtitle = 'Quản lý KPI dành cho đội ngũ Core/Key Members';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div style="background: white; padding: 40px; border-radius: 16px; border: 1px solid #e2e8f0; text-align: center;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <h2 style="color: #1e293b; margin-bottom: 10px;">Tính năng đang hoàn thiện</h2>
                    <p style="color: #64748b;">Mô-đun quản lý KPI dành riêng cho Core/Key Members đang được xây dựng.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/dashboard.js"></script>
</body>

</html>
