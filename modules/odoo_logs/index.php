<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

// Ensure table exists (in case hook hasn't been called yet)
$conn->query("CREATE TABLE IF NOT EXISTS odoo_webhook_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type   VARCHAR(100) NOT NULL DEFAULT 'unknown',
    payload      LONGTEXT     NOT NULL,
    source_ip    VARCHAR(45)  NOT NULL DEFAULT '',
    result_notes TEXT         DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add result_notes column if table already existed without it
$col = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='odoo_webhook_logs' AND COLUMN_NAME='result_notes'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE odoo_webhook_logs ADD COLUMN result_notes TEXT DEFAULT NULL");
}

// Filters
$filter_type = trim($_GET['type'] ?? '');
$filter_date = trim($_GET['date'] ?? '');
$search      = trim($_GET['q']    ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 50;
$offset      = ($page - 1) * $per_page;

// Build WHERE
$where  = [];
$params = [];
$types  = '';

if ($filter_type !== '') {
    $where[]  = 'event_type = ?';
    $params[] = $filter_type;
    $types   .= 's';
}
if ($filter_date !== '') {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filter_date;
    $types   .= 's';
}
if ($search !== '') {
    $where[]  = 'payload LIKE ?';
    $params[] = '%' . $search . '%';
    $types   .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$count_sql = "SELECT COUNT(*) FROM odoo_webhook_logs $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch rows
$sql  = "SELECT id, event_type, source_ip, created_at, result_notes, LEFT(payload, 500) AS payload_preview
         FROM odoo_webhook_logs $where_sql
         ORDER BY id DESC
         LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$fetch_params = $params;
$fetch_types  = $types . 'ii';
$fetch_params[] = $per_page;
$fetch_params[] = $offset;
$stmt->bind_param($fetch_types, ...$fetch_params);
$stmt->execute();
$result = $stmt->get_result();
$logs   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct event types for filter dropdown
$types_res = $conn->query("SELECT DISTINCT event_type FROM odoo_webhook_logs ORDER BY event_type");
$event_types = [];
while ($r = $types_res->fetch_assoc()) $event_types[] = $r['event_type'];

$full_name = $_SESSION['full_name'];
$avatar    = $_SESSION['avatar'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odoo Webhook Logs</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .logs-wrap { padding: 24px; }
        .logs-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .logs-title { font-size: 1.4rem; font-weight: 700; color: var(--text-primary, #f1f5f9); }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-bar input, .filter-bar select {
            background: var(--bg-card, #1e293b);
            border: 1px solid var(--border, #334155);
            color: var(--text-primary, #f1f5f9);
            border-radius: 8px; padding: 7px 12px; font-size: 0.85rem;
        }
        .btn-filter {
            background: #6366f1; color: #fff; border: none; border-radius: 8px;
            padding: 7px 16px; cursor: pointer; font-size: 0.85rem; font-weight: 600;
        }
        .btn-clear { background: #475569; color: #fff; border: none; border-radius: 8px; padding: 7px 12px; cursor: pointer; font-size: 0.85rem; }
        .stats-row { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card {
            background: var(--bg-card, #1e293b);
            border: 1px solid var(--border, #334155);
            border-radius: 10px; padding: 14px 20px; min-width: 140px;
        }
        .stat-card .label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: #6366f1; }
        .logs-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .logs-table th {
            text-align: left; padding: 10px 14px; background: var(--bg-card, #1e293b);
            color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 0.72rem;
            letter-spacing: .05em; border-bottom: 1px solid var(--border, #334155);
        }
        .logs-table td { padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.05); vertical-align: top; }
        .logs-table tr:hover td { background: rgba(99,102,241,.06); }
        .badge {
            display: inline-block; padding: 2px 10px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-crm      { background: rgba(16,185,129,.15); color: #10b981; }
        .badge-sale     { background: rgba(245,158,11,.15); color: #f59e0b; }
        .badge-invoice  { background: rgba(99,102,241,.15); color: #818cf8; }
        .badge-unknown  { background: rgba(148,163,184,.15); color: #94a3b8; }
        .payload-preview {
            font-family: 'Fira Mono', 'Courier New', monospace;
            font-size: 0.75rem; color: #94a3b8;
            white-space: pre-wrap; word-break: break-all;
            max-width: 480px; max-height: 80px; overflow: hidden;
            position: relative; cursor: pointer;
        }
        .payload-preview::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 24px; background: linear-gradient(transparent, var(--bg-main, #0f172a));
        }
        .btn-detail {
            background: none; border: 1px solid #334155; color: #94a3b8;
            border-radius: 6px; padding: 3px 10px; font-size: 0.75rem; cursor: pointer;
        }
        .btn-detail:hover { border-color: #6366f1; color: #6366f1; }
        .pagination { display: flex; gap: 6px; margin-top: 20px; align-items: center; flex-wrap: wrap; }
        .pagination a, .pagination span {
            padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; text-decoration: none;
            background: var(--bg-card, #1e293b); color: #94a3b8; border: 1px solid #334155;
        }
        .pagination a:hover { border-color: #6366f1; color: #6366f1; }
        .pagination .active { background: #6366f1; color: #fff; border-color: #6366f1; }
        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            width: 90%; max-width: 800px; max-height: 85vh; display: flex; flex-direction: column;
        }
        .modal-head { padding: 16px 20px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .modal-head h3 { margin: 0; font-size: 1rem; color: #f1f5f9; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.3rem; cursor: pointer; line-height: 1; }
        .modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
        .modal-body pre {
            font-family: 'Fira Mono', 'Courier New', monospace;
            font-size: 0.8rem; color: #e2e8f0; white-space: pre-wrap; word-break: break-all;
            margin: 0;
        }
        .empty-state { text-align: center; padding: 60px 20px; color: #475569; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }
        .hook-url-box {
            background: rgba(99,102,241,.08); border: 1px dashed #6366f1;
            border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;
            font-size: 0.85rem; color: #a5b4fc;
        }
        .hook-url-box strong { color: #e0e7ff; }
        .hook-url-box code { background: rgba(99,102,241,.2); padding: 2px 8px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
<?php
// Try to include the sidebar partial if it exists
$sidebar_file = __DIR__ . '/../../modules/includes/sidebar.php';
if (!file_exists($sidebar_file)) {
    $sidebar_file = __DIR__ . '/../../includes/sidebar.php';
}
if (file_exists($sidebar_file)) {
    include $sidebar_file;
}
?>
<div class="main-content" style="padding: 0;">
<div class="logs-wrap">

    <div class="logs-header">
        <div class="logs-title">Odoo Webhook Logs</div>
        <a href="/odoo/logs?<?php echo http_build_query(array_filter(['type'=>$filter_type,'date'=>$filter_date,'q'=>$search])); ?>" style="font-size:0.82rem;color:#6366f1;text-decoration:none;" onclick="location.reload();return false;">&#8635; Refresh</a>
    </div>

    <!-- Webhook endpoint info -->
    <div class="hook-url-box">
        <strong>Webhook URL:</strong>
        <code><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/odoo/hook'); ?></code>
        &nbsp;&mdash;&nbsp; Odoo đang POST CRM / Sale / Invoice tới đây. Method: <strong>POST</strong>, Content-Type: <strong>application/json</strong>
    </div>

    <!-- Stats -->
    <?php
    $stats = [];
    $sr = $conn->query("SELECT event_type, COUNT(*) AS cnt FROM odoo_webhook_logs GROUP BY event_type ORDER BY cnt DESC");
    while ($row = $sr->fetch_assoc()) $stats[] = $row;
    $total_all = array_sum(array_column($stats, 'cnt'));
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="label">Total Logs</div>
            <div class="value"><?php echo number_format($total_all); ?></div>
        </div>
        <?php foreach ($stats as $s): ?>
        <div class="stat-card">
            <div class="label"><?php echo htmlspecialchars($s['event_type']); ?></div>
            <div class="value"><?php echo number_format($s['cnt']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" action="/odoo/logs">
        <div class="filter-bar" style="margin-bottom:16px;">
            <select name="type">
                <option value="">All types</option>
                <?php foreach ($event_types as $et): ?>
                <option value="<?php echo htmlspecialchars($et); ?>" <?php echo $filter_type === $et ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($et); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" placeholder="Date">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search payload..." style="min-width:200px;">
            <button type="submit" class="btn-filter">Filter</button>
            <a href="/odoo/logs" class="btn-clear">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <?php if (empty($logs)): ?>
    <div class="empty-state">
        <div class="icon">&#128268;</div>
        <div>No webhook logs found<?php echo $total_all ? ' matching the current filter' : ' yet'; ?>.</div>
        <?php if (!$total_all): ?>
        <div style="margin-top:8px;font-size:0.82rem;">Odoo sẽ gửi dữ liệu tới <code style="background:#1e293b;padding:2px 6px;border-radius:4px;">/odoo/hook</code> khi có event.</div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="logs-table">
        <thead>
            <tr>
                <th>#ID</th>
                <th>Event Type</th>
                <th>Source IP</th>
                <th>Received At</th>
                <th>Payload Preview</th>
                <th>Result</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log):
            $badge_class = match(true) {
                str_contains($log['event_type'], 'crm')     => 'badge-crm',
                str_contains($log['event_type'], 'sale')    => 'badge-sale',
                str_contains($log['event_type'], 'invoice') => 'badge-invoice',
                default                                     => 'badge-unknown',
            };
            $preview = htmlspecialchars(substr($log['payload_preview'], 0, 300));
        ?>
        <tr>
            <td style="color:#64748b;">#<?php echo $log['id']; ?></td>
            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['event_type']); ?></span></td>
            <td style="color:#64748b;font-size:0.78rem;"><?php echo htmlspecialchars($log['source_ip']); ?></td>
            <td style="color:#94a3b8;white-space:nowrap;"><?php echo $log['created_at']; ?></td>
            <td><div class="payload-preview"><?php echo $preview; ?></div></td>
            <td style="font-size:0.72rem;max-width:220px;">
                <?php if (!empty($log['result_notes'])):
                    $rn = json_decode($log['result_notes'], true) ?: [];
                    $action = $rn['action'] ?? null;
                    $actionColor = match($action) {
                        'auto_create'  => '#10b981',
                        'update_stage' => '#f59e0b',
                        'skip'         => '#94a3b8',
                        'no_opp_id'    => '#ef4444',
                        default        => '#94a3b8',
                    };
                    $actionLabel = match($action) {
                        'auto_create'  => '✓ Tạo PAKD mới',
                        'update_stage' => '↻ Update stage',
                        'skip'         => '— Stage không sync',
                        'no_opp_id'    => '✗ Thiếu opp_id',
                        default        => htmlspecialchars($action ?? '—'),
                    };
                    $opp = $rn['opp_id_extracted'] ?? null;
                    $stg = ($rn['stage_id'] ?? '') . ' ' . ($rn['stage_name'] ?? '');
                    if (!empty($rn['error'])) $actionLabel .= ' ✗ ' . htmlspecialchars(substr($rn['error'], 0, 60));
                ?>
                <div style="color:<?php echo $actionColor; ?>;font-weight:600;"><?php echo $actionLabel; ?></div>
                <?php if ($opp): ?><div style="color:#64748b;">opp #<?php echo $opp; ?></div><?php endif; ?>
                <?php if (trim($stg)): ?><div style="color:#64748b;"><?php echo htmlspecialchars(trim($stg)); ?></div><?php endif; ?>
                <?php if (!empty($rn['sync_stage_ids'])): ?>
                <div style="color:#64748b;">sync: [<?php echo implode(',', $rn['sync_stage_ids']); ?>]</div>
                <?php endif; ?>
                <?php endif; ?>
            </td>
            <td>
                <button class="btn-detail" onclick="showDetail(<?php echo $log['id']; ?>)">View JSON</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++): ?>
            <?php if ($p === $page): ?>
                <span class="active"><?php echo $p; ?></span>
            <?php else: ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
        <?php endif; ?>
        <span style="color:#64748b;font-size:0.78rem;margin-left:8px;"><?php echo number_format($total_rows); ?> records &bull; page <?php echo $page; ?>/<?php echo $total_pages; ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div><!-- /.logs-wrap -->
</div><!-- /.main-content -->

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle">Payload Detail</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="modalContent">Loading...</pre>
        </div>
    </div>
</div>

<script>
function showDetail(id) {
    document.getElementById('modalTitle').textContent = 'Payload #' + id;
    document.getElementById('modalContent').textContent = 'Loading...';
    document.getElementById('detailModal').classList.add('open');

    fetch('/api/odoo_log_detail?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.payload) {
                try {
                    const pretty = JSON.stringify(JSON.parse(data.payload), null, 2);
                    document.getElementById('modalContent').textContent = pretty;
                } catch(e) {
                    document.getElementById('modalContent').textContent = data.payload;
                }
            } else {
                document.getElementById('modalContent').textContent = data.error || 'Not found';
            }
        })
        .catch(e => { document.getElementById('modalContent').textContent = 'Error: ' + e; });
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('open');
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>
