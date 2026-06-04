<?php
/**
 * Notification center page — the full unified list of a user's notifications,
 * powered by NotificationCenter (same source as the topbar bell and the email
 * digest).
 */
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}
require_once __DIR__ . '/../../includes/NotificationCenter.php';

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role      = $_SESSION['role'];
$avatar    = $_SESSION['avatar'] ?? null;

$items = NotificationCenter::items($conn, $_SESSION);

$sev_style = [
    'danger'  => ['#fef2f2', '#dc2626', '#fecaca'],
    'warning' => ['#fffbeb', '#d97706', '#fde68a'],
    'success' => ['#f0fdf4', '#16a34a', '#bbf7d0'],
    'info'    => ['#eff6ff', '#2563eb', '#bfdbfe'],
];
$kind_label = ['pasx' => 'PASX', 'debt' => 'Công nợ', 'manual' => 'Cảnh báo', 'kpi' => 'KPI'];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .nc-wrap { max-width: 820px; }
        .nc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .nc-item { display: flex; gap: 14px; background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid var(--ac, #2563eb); border-radius: 12px; padding: 16px 18px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .nc-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; padding: 2px 8px; border-radius: 99px; align-self: flex-start; }
        .nc-title { font-weight: 700; color: #0f172a; font-size: 14px; margin-bottom: 3px; }
        .nc-body { font-size: 13px; color: #475569; }
        .nc-meta { font-size: 11px; color: #94a3b8; margin-top: 6px; }
        .nc-actions { margin-left: auto; display: flex; gap: 8px; align-items: flex-start; white-space: nowrap; }
        .nc-btn { font-size: 12px; font-weight: 600; text-decoration: none; padding: 5px 12px; border-radius: 7px; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #334155; }
        .nc-btn.primary { background: #0f172a; color: #fff; border-color: #0f172a; }
        .nc-empty { text-align: center; color: #94a3b8; padding: 48px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Thông báo';
            $page_subtitle = 'Tất cả thông báo cần chú ý của bạn';
            include __DIR__ . '/../includes/topbar.php';
            ?>
            <div class="content-wrapper">
                <div class="nc-wrap">
                    <div class="nc-head">
                        <h2 style="margin:0; font-size:1.15rem; color:#0f172a;"><?php echo count($items); ?> thông báo</h2>
                        <?php if (array_filter($items, fn($i) => !empty($i['mark']))): ?>
                            <button class="nc-btn" onclick="ncMarkAll(this)">Đánh dấu đã đọc tất cả</button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="nc-empty">🎉 Không có thông báo nào. Bạn đã xử lý hết!</div>
                    <?php else: ?>
                        <?php foreach ($items as $it):
                            [$bg, $fg, $bd] = $sev_style[$it['severity']] ?? $sev_style['info'];
                            $domid = 'nc-' . preg_replace('/[^a-z0-9]+/i', '-', $it['key']); ?>
                            <div class="nc-item" id="<?php echo $domid; ?>" style="--ac:<?php echo $fg; ?>;">
                                <span class="nc-tag" style="background:<?php echo $bg; ?>; color:<?php echo $fg; ?>;"><?php echo $kind_label[$it['kind']] ?? $it['kind']; ?></span>
                                <div style="min-width:0;">
                                    <div class="nc-title"><?php echo htmlspecialchars($it['title']); ?></div>
                                    <div class="nc-body"><?php echo htmlspecialchars($it['body']); ?></div>
                                    <?php if (!empty($it['created_at'])): ?>
                                        <div class="nc-meta"><?php echo date('d/m/Y H:i', strtotime($it['created_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="nc-actions">
                                    <?php if (!empty($it['link'])): ?>
                                        <a class="nc-btn primary" href="<?php echo htmlspecialchars($it['link']); ?>"><?php echo htmlspecialchars($it['link_label'] ?: 'Xem'); ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($it['mark'])): ?>
                                        <button class="nc-btn" onclick='ncMarkOne(<?php echo json_encode($it['mark']); ?>, "<?php echo $domid; ?>")'>Đã đọc</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        function ncMarkOne(payload, domid) {
            fetch('/api/notif_read.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(d => {
                if (d.ok) {
                    const el = document.getElementById(domid);
                    if (el) { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
                }
            });
        }
        function ncMarkAll(btn) {
            btn.disabled = true;
            fetch('/api/notif_read.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ all: true })
            }).then(r => r.json()).then(() => location.reload());
        }
    </script>
    <script src="/assets/js/dashboard.js"></script>
</body>

</html>
