<?php
// Ensure session and database connection are available
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// Track last online activity
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if ($check_col && $check_col->num_rows == 0) {
    // Suppress error in case of concurrent execution
    @$conn->query("ALTER TABLE users ADD COLUMN last_active DATETIME NULL DEFAULT NULL");
}
$stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch latest avatar from DB to ensure it's up to date
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $avatar = $row['avatar'];
    $_SESSION['avatar'] = $avatar; // Sync session
} else {
    $avatar = $_SESSION['avatar'] ?? null;
}

// Default title/subtitle if not set
if (!isset($page_title))
    $page_title = 'Management System';
if (!isset($page_subtitle))
    $page_subtitle = '';

?>
<header class="top-bar">
    <div class="page-title">
        <h1>
            <?php echo htmlspecialchars($page_title); ?>
        </h1>
        <?php if ($page_subtitle): ?>
            <p>
                <?php echo $page_subtitle; ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="user-menu" style="display: flex; align-items: center; gap: 12px;">
        <div class="user-info" style="text-align: right; display: flex; flex-direction: column;">
            <span style="font-weight: 600; font-size: 0.9rem; color: #1e293b; line-height: 1.2;">
                <?php echo htmlspecialchars($full_name); ?>
            </span>
            <span style="font-size: 0.75rem; color: #64748b; text-transform: capitalize;">
                <?php echo htmlspecialchars($role); ?>
            </span>
        </div>
        <a href="/modules/profile" class="user-avatar"
            style="text-decoration: none; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; overflow: hidden; background: #3b82f6; color: white; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <?php if ($avatar): ?>
                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar"
                    style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <span style="font-size: 14px;">
                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</header>