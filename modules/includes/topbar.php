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

// Calculate notifications for AM BD
$am_notifications = [];
if (isset($_SESSION['is_am_bd']) && $_SESSION['is_am_bd'] == 1) {
    // get debts that are not paid and belong to this AM
    $am_name = $_SESSION['full_name'];
    $stmt_notif = $conn->prepare("
        SELECT d.id, d.client_name, d.project_name, d.expected_payment_date 
        FROM debts d 
        WHERE d.am = ? AND d.payment_status = 'Not paid' 
        AND d.expected_payment_date IS NOT NULL AND d.expected_payment_date > '2000-01-01'
    ");
    $stmt_notif->bind_param("s", $am_name);
    $stmt_notif->execute();
    $res_notif = $stmt_notif->get_result();
    $now_notif = new DateTime();
    $now_notif->setTime(0, 0, 0);
    $raw_notifs = [];
    while ($r_notif = $res_notif->fetch_assoc()) {
        $exp_date = new DateTime($r_notif['expected_payment_date']);
        $exp_date->setTime(0, 0, 0);
        $diff = $now_notif->diff($exp_date);

        if ($diff->invert) {
            if ($diff->days > 60) {
                // > 60 days
                $raw_notifs[] = ['debt' => $r_notif, 'level' => 60];
            } elseif ($diff->days > 30) {
                // > 30 days
                $raw_notifs[] = ['debt' => $r_notif, 'level' => 30];
            }
        }
    }
    $stmt_notif->close();

    // Now filter out the ones already read
    if (count($raw_notifs) > 0) {
        // Auto-create table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS debt_notifications_read (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            debt_id INT NOT NULL,
            warning_level INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (user_id, debt_id, warning_level),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE
        )");

        $read_stmt = $conn->prepare("SELECT debt_id, warning_level FROM debt_notifications_read WHERE user_id = ?");
        $read_stmt->bind_param("i", $current_user_id);
        $read_stmt->execute();
        $read_res = $read_stmt->get_result();
        $read_set = [];
        while ($r = $read_res->fetch_assoc()) {
            $read_set[$r['debt_id'] . '_' . $r['warning_level']] = true;
        }
        $read_stmt->close();

        foreach ($raw_notifs as $n) {
            $key = $n['debt']['id'] . '_' . $n['level'];
            if (!isset($read_set[$key])) {
                $am_notifications[] = $n;
            }
        }
    }

    // Auto-create manual warnings table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS debt_manual_warnings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        debt_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Fetch manual warnings
    $stmt_manual = $conn->prepare("
        SELECT mw.id as warning_id, mw.created_at as warned_at, mw.warning_type, d.id, d.client_name, d.project_name, d.expected_payment_date, u.full_name as sender_name
        FROM debt_manual_warnings mw
        JOIN debts d ON mw.debt_id = d.id
        JOIN users u ON mw.sender_id = u.id
        WHERE mw.receiver_id = ? AND mw.is_read = 0
    ");
    if ($stmt_manual) {
        $stmt_manual->bind_param("i", $current_user_id);
        $stmt_manual->execute();
        $res_manual = $stmt_manual->get_result();
        while ($rm = $res_manual->fetch_assoc()) {
            $am_notifications[] = [
                'debt' => $rm,
                'level' => 99, // Custom level for manual warnings
                'sender' => $rm['sender_name'],
                'at' => $rm['warned_at'],
                'warning_type' => $rm['warning_type']
            ];
        }
        $stmt_manual->close();
    }
}
$notif_count = count($am_notifications);
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

        <!-- Notification Bell dropdown logic -->
        <div class="notification-container" style="position: relative;">
            <a href="#" class="notification-bell" onclick="toggleNotifications(event)"
                style="position: relative; color: #64748b; margin-right: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; transition: background-color 0.2s;"
                onmouseover="this.style.backgroundColor='#f1f5f9'"
                onmouseout="this.style.backgroundColor='transparent'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($notif_count > 0): ?>
                    <span id="notif-badge"
                        style="position: absolute; top: 4px; right: 4px; width: 14px; height: 14px; background-color: #ef4444; color: white; border-radius: 50%; border: 2px solid white; font-size: 8px; font-weight: bold; display: flex; align-items: center; justify-content: center;"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
                <?php endif; ?>
            </a>

            <!-- Dropdown Menu -->
            <div id="notification-dropdown"
                style="display: none; position: absolute; top: 45px; right: 0; width: 320px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 1000; overflow: hidden;">
                <div
                    style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                    <span style="font-weight: 600; font-size: 0.95rem; color: #1e293b;">Thông báo</span>
                    <?php if ($notif_count > 0): ?>
                        <a href="#" onclick="markAllAsRead(event)"
                            style="font-size: 0.8rem; color: #3b82f6; text-decoration: none;">Đánh dấu đã đọc tất cả</a>
                    <?php endif; ?>
                </div>
                <div style="max-height: 350px; overflow-y: auto;">
                    <?php if ($notif_count > 0): ?>
                        <?php foreach ($am_notifications as $notif):
                            $d = $notif['debt'];
                            $lvl = $notif['level'];
                            $is_critical = ($lvl == 60);
                            $bg_col = $is_critical ? '#fef2f2' : '#fffbeb';
                            $text_col = $is_critical ? '#dc2626' : '#d97706';
                            $title = $is_critical ? 'Quá hạn > 60 ngày' : 'Cảnh báo quá hạn > 30 ngày';

                            if ($lvl == 99) {
                                $wt = $notif['warning_type'] ?? 'manual';
                                $wt_label = 'CẢNH BÁO';
                                if ($wt == '60_days') {
                                    $wt_label = 'QUÁ HẠN > 60 NGÀY';
                                    $bg_col = '#fef2f2';
                                    $text_col = '#dc2626';
                                } elseif ($wt == '30_days') {
                                    $wt_label = 'QUÁ HẠN > 30 NGÀY';
                                    $bg_col = '#fffbeb';
                                    $text_col = '#d97706';
                                } elseif ($wt == 'empty') {
                                    $wt_label = 'CHƯA CÓ NGÀY TT';
                                    $bg_col = '#f0f9ff';
                                    $text_col = '#0284c7';
                                } else {
                                    $bg_col = '#fff7ed';
                                    $text_col = '#ea580c';
                                }
                                $title = '🔔 ' . $wt_label . ' TỪ ' . strtoupper($notif['sender']);
                            }
                            ?>
                            <div class="notif-item" id="notif-item-<?php echo $d['id'] . '-' . $lvl; ?>"
                                style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: <?php echo $bg_col; ?>; transition: background 0.2s; position: relative;"
                                onmouseover="this.style.filter='brightness(0.98)'" onmouseout="this.style.filter='none'">
                                <div
                                    style="font-size: 0.85rem; font-weight: 700; color: <?php echo $text_col; ?>; margin-bottom: 4px;">
                                    <?php echo $title; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #475569; margin-bottom: 8px;">
                                    <strong><?php echo htmlspecialchars($d['client_name'] ?? ''); ?></strong> -
                                    <?php echo htmlspecialchars($d['project_name'] ?? ''); ?>
                                    <br><span style="color: #94a3b8; font-size: 0.75rem;">Hạn thanh toán:
                                        <?php echo date('d/m/Y', strtotime($d['expected_payment_date'])); ?></span>
                                </div>
                                <button
                                    onclick="markNotificationRead(<?php echo $d['id']; ?>, <?php echo $lvl; ?>, 'notif-item-<?php echo $d['id'] . '-' . $lvl; ?>')"
                                    style="background: none; border: 1px solid <?php echo $text_col; ?>; color: <?php echo $text_col; ?>; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; cursor: pointer; transition: all 0.2s; float: right;">Đánh
                                    dấu đã đọc</button>
                                <div style="clear: both;"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                            Không có thông báo mới.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
    <script>
        function toggleNotifications(e) {
            e.preventDefault();
            const dropdown = document.getElementById('notification-dropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            const container = document.querySelector('.notification-container');
            const dropdown = document.getElementById('notification-dropdown');
            if (container && dropdown && !container.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        function markNotificationRead(debtId, level, elemId) {
            fetch('/api/mark_notification_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ debt_id: debtId, warning_level: level })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const elem = document.getElementById(elemId);
                        if (elem) {
                            elem.style.height = elem.offsetHeight + 'px';
                            elem.style.transition = 'all 0.3s';
                            elem.style.opacity = '0';
                            setTimeout(() => { elem.style.display = 'none'; }, 300);
                        }
                        // decrease badge
                        decreaseBadge();
                    }
                });
        }

        function markAllAsRead(e) {
            e.preventDefault();
            const notifs = [
                <?php
                if ($notif_count > 0) {
                    $notif_arr = [];
                    foreach ($am_notifications as $n) {
                        $notif_arr[] = "{debt_id: " . $n['debt']['id'] . ", warning_level: " . $n['level'] . "}";
                    }
                    echo implode(", ", $notif_arr);
                }
                ?>
            ];
            if (notifs.length === 0) return;

            fetch('/api/mark_notification_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all', notifications: notifs })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // hide all items
                        document.querySelectorAll('.notif-item').forEach(el => el.style.display = 'none');
                        const badge = document.getElementById('notif-badge');
                        if (badge) badge.style.display = 'none';
                    }
                });
        }

        function decreaseBadge() {
            const badge = document.getElementById('notif-badge');
            if (badge) {
                let text = badge.innerText;
                if (text === '9+') return; // simplify if 9+
                let count = parseInt(text);
                if (!isNaN(count) && count > 1) {
                    badge.innerText = count - 1;
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    </script>
</header>