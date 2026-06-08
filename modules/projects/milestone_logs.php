<?php
/**
 * Trang xem log webhook milestone (đồng bộ từ hệ thống sản xuất / OS)
 * Route: /projects/milestones/logs
 * Đọc bảng milestone_webhook_logs
 */
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin' && empty($_SESSION['can_view_odoo_logs'])) {
    header("Location: /dashboard"); exit();
}

// Bảng có thể chưa tồn tại nếu chưa nhận webhook nào
$conn->query("CREATE TABLE IF NOT EXISTS milestone_webhook_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id         INT          DEFAULT NULL,
    os_milestone_id VARCHAR(64)  DEFAULT NULL,
    event           VARCHAR(64)  DEFAULT NULL,
    status          VARCHAR(32)  DEFAULT NULL,
    payload         JSON         DEFAULT NULL,
    http_status     INT          DEFAULT 200,
    note            TEXT         DEFAULT NULL,
    received_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pakd (pakd_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Filters
$filter_event = trim($_GET['event'] ?? '');
$filter_date  = trim($_GET['date'] ?? '');
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 50;
$offset       = ($page - 1) * $per_page;

$filter_pakd = (int)($_GET['pakd'] ?? 0);

$where = []; $params = []; $types = '';
if ($filter_pakd  > 0)    { $where[] = 'pakd_id = ?';          $params[] = $filter_pakd;  $types .= 'i'; }
if ($filter_event !== '') { $where[] = 'event = ?';            $params[] = $filter_event; $types .= 's'; }
if ($filter_date  !== '') { $where[] = 'DATE(received_at) = ?'; $params[] = $filter_date;  $types .= 's'; }
if ($search       !== '') { $where[] = '(payload LIKE ? OR os_milestone_id LIKE ? OR note LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'sss'; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM milestone_webhook_logs $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Rows
$stmt = $conn->prepare("SELECT id, pakd_id, os_milestone_id, event, status, http_status, note, received_at, payload
                        FROM milestone_webhook_logs $where_sql ORDER BY id DESC LIMIT ? OFFSET ?");
$fp = $params; $ft = $types . 'ii'; $fp[] = $per_page; $fp[] = $offset;
$stmt->bind_param($ft, ...$fp);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats by event
$stats = [];
$sr = $conn->query("SELECT event, COUNT(*) AS cnt FROM milestone_webhook_logs GROUP BY event ORDER BY cnt DESC");
while ($r = $sr->fetch_assoc()) $stats[] = $r;
$total_all = array_sum(array_column($stats, 'cnt'));

// Distinct events for dropdown
$events = [];
$er = $conn->query("SELECT DISTINCT event FROM milestone_webhook_logs WHERE event IS NOT NULL ORDER BY event");
while ($r = $er->fetch_assoc()) $events[] = $r['event'];

$hook_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/integrations/hrm/milestones/sync';

function ev_badge_color($ev) {
    return match(true) {
        $ev === 'created'      => ['#ecfdf5', '#047857'],
        $ev === 'updated'      => ['#eef2ff', '#4f46e5'],
        $ev === 'deleted'      => ['#fef2f2', '#b91c1c'],
        $ev === 'auth_failed'  => ['#fef2f2', '#b91c1c'],
        $ev === 'invalid_json' => ['#fffbeb', '#b45309'],
        default                => ['#f1f5f9', '#64748b'],
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log đồng bộ Milestone</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#4f46e5; --slate:#0f172a; --gray:#5b6678; --lgray:#94a3b8; --border:#e6e8ec; }
        body { font-family:'Inter',sans-serif; color:var(--slate); }
        .logs-wrap { padding:24px 32px; }
        .logs-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; gap:12px; }
        .logs-title { font-size:1.3rem; font-weight:800; display:flex; align-items:center; gap:10px; }
        .logs-title i { color:#0d9488; }
        .hook-url-box { background:#f6f7f9; border:1px dashed #c7cdd6; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:.85rem; color:var(--gray); }
        .hook-url-box code { background:#eef2ff; color:#4338ca; padding:2px 8px; border-radius:5px; font-family:monospace; }
        .stats-row { display:flex; gap:14px; margin-bottom:18px; flex-wrap:wrap; }
        .stat-card { background:#fff; border:1px solid var(--border); border-radius:10px; padding:12px 18px; min-width:120px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
        .stat-card .label { font-size:.7rem; color:var(--gray); text-transform:uppercase; letter-spacing:.05em; font-weight:700; }
        .stat-card .value { font-size:1.4rem; font-weight:800; color:var(--slate); margin-top:2px; }
        .filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
        .filter-bar input, .filter-bar select { background:#fff; border:1px solid var(--border); color:var(--slate); border-radius:8px; padding:7px 12px; font-size:.85rem; font-family:inherit; }
        .btn-filter { background:var(--primary); color:#fff; border:none; border-radius:8px; padding:8px 16px; cursor:pointer; font-size:.85rem; font-weight:600; }
        .btn-clear { background:#fff; color:var(--gray); border:1px solid var(--border); border-radius:8px; padding:8px 14px; cursor:pointer; font-size:.85rem; text-decoration:none; }
        .logs-table { width:100%; border-collapse:collapse; font-size:.85rem; background:#fff; border:1px solid var(--border); border-radius:10px; overflow:hidden; }
        .logs-table th { text-align:left; padding:10px 14px; background:#fcfcfd; color:var(--gray); font-weight:700; text-transform:uppercase; font-size:.7rem; letter-spacing:.05em; border-bottom:1px solid var(--border); }
        .logs-table td { padding:10px 14px; border-bottom:1px solid #f1f3f6; vertical-align:top; }
        .logs-table tr:hover td { background:#fafbfc; }
        .badge { display:inline-block; padding:2px 10px; border-radius:6px; font-size:.72rem; font-weight:700; }
        .mono { font-family:'Fira Mono',monospace; font-size:.78rem; color:var(--gray); }
        .pill-ok { color:#047857; font-weight:700; }
        .pill-err { color:#b91c1c; font-weight:700; }
        .btn-detail { background:none; border:1px solid var(--border); color:var(--gray); border-radius:6px; padding:4px 10px; font-size:.75rem; cursor:pointer; }
        .btn-detail:hover { border-color:var(--primary); color:var(--primary); }
        .pagination { display:flex; gap:6px; margin-top:18px; align-items:center; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:6px 12px; border-radius:6px; font-size:.82rem; text-decoration:none; background:#fff; color:var(--gray); border:1px solid var(--border); }
        .pagination a:hover { border-color:var(--primary); color:var(--primary); }
        .pagination .active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .empty-state { text-align:center; padding:60px 20px; color:var(--lgray); background:#fff; border:1px solid var(--border); border-radius:10px; }
        .empty-state i { font-size:2.4rem; margin-bottom:12px; display:block; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:12px; width:90%; max-width:760px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 50px rgba(0,0,0,.25); }
        .modal-head { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .modal-head h3 { margin:0; font-size:1rem; }
        .modal-close { background:none; border:none; color:var(--gray); font-size:1.4rem; cursor:pointer; line-height:1; }
        .modal-body { padding:16px 20px; overflow:auto; }
        .modal-body pre { font-family:'Fira Mono',monospace; font-size:.8rem; color:#1e293b; white-space:pre-wrap; word-break:break-word; margin:0; }
        .btn-clear-logs { background:#fef2f2; color:#b91c1c; border:1px solid #f3d2d2; border-radius:8px; padding:8px 14px; cursor:pointer; font-size:.82rem; font-weight:600; }
        .btn-clear-logs:hover { background:#fee2e2; border-color:#b91c1c; }
        .clear-box { background:#fff; border-radius:12px; width:90%; max-width:440px; box-shadow:0 20px 50px rgba(0,0,0,.25); }
        .clear-body { padding:20px; }
        .clear-body label { display:block; font-size:.8rem; color:var(--gray); margin-bottom:5px; font-weight:600; }
        .clear-body input[type=date] { width:100%; background:#fff; border:1px solid var(--border); color:var(--slate); border-radius:8px; padding:8px 12px; font-size:.85rem; box-sizing:border-box; font-family:inherit; }
        .clear-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
        .btn-do-clear { background:#b91c1c; color:#fff; border:none; border-radius:8px; padding:8px 18px; cursor:pointer; font-size:.85rem; font-weight:600; }
        .btn-do-clear:disabled { opacity:.5; cursor:not-allowed; }
        #clearResult { margin-top:12px; font-size:.82rem; padding:8px 12px; border-radius:6px; display:none; }
    </style>
</head>
<body>
<?php
$sidebar_file = __DIR__ . '/../../modules/includes/sidebar.php';
if (!file_exists($sidebar_file)) $sidebar_file = __DIR__ . '/../../includes/sidebar.php';
if (file_exists($sidebar_file)) include $sidebar_file;
?>
<div class="main-content" style="padding:0;">
<div class="logs-wrap">

    <div class="logs-header">
        <div class="logs-title"><i class="fas fa-flag-checkered"></i> Log đồng bộ Milestone</div>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="/projects/milestones/logs?<?= http_build_query(array_filter(['pakd'=>$filter_pakd,'event'=>$filter_event,'date'=>$filter_date,'q'=>$search])) ?>"
               class="btn-clear"><i class="fas fa-sync-alt"></i> Refresh</a>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <button class="btn-clear-logs" onclick="openClearModal()"><i class="fas fa-trash-alt"></i> Xoá log</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="hook-url-box">
        <strong>Webhook URL:</strong> <code><?= htmlspecialchars($hook_url) ?></code>
        &nbsp;—&nbsp; Hệ thống sản xuất POST event milestone tới đây. Method <strong>POST</strong>, header <strong>X-Api-Key</strong>.
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="label">Tổng log</div><div class="value"><?= number_format($total_all) ?></div></div>
        <?php foreach ($stats as $s): ?>
        <div class="stat-card"><div class="label"><?= htmlspecialchars($s['event'] ?? '—') ?></div><div class="value"><?= number_format($s['cnt']) ?></div></div>
        <?php endforeach; ?>
    </div>

    <form method="GET" action="/projects/milestones/logs">
        <?php if ($filter_pakd): ?><input type="hidden" name="pakd" value="<?= $filter_pakd ?>"><?php endif; ?>
        <div class="filter-bar">
            <?php if ($filter_pakd): ?>
            <span style="font-size:.82rem;color:var(--gray);background:#eef2ff;border:1px solid #dfe3f5;padding:6px 10px;border-radius:8px;">
                Lọc theo PAKD #<?= $filter_pakd ?> <a href="/projects/milestones/logs" style="color:var(--primary);text-decoration:none;margin-left:4px;">&times;</a>
            </span>
            <?php endif; ?>
            <select name="event">
                <option value="">Tất cả event</option>
                <?php foreach ($events as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $filter_event === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm payload / milestoneId / note..." style="min-width:220px;">
            <button type="submit" class="btn-filter">Lọc</button>
            <a href="/projects/milestones/logs" class="btn-clear">Xóa lọc</a>
        </div>
    </form>

    <?php if (empty($logs)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <div>Chưa có log nào<?= $total_all ? ' khớp bộ lọc' : '' ?>.</div>
        <?php if (!$total_all): ?>
        <div style="margin-top:8px;font-size:.82rem;">Hệ thống sản xuất sẽ gửi event tới <code style="background:#eef2ff;padding:2px 6px;border-radius:4px;">/integrations/hrm/milestones/sync</code>.</div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="logs-table">
        <thead>
            <tr>
                <th>#ID</th><th>Event</th><th>Milestone ID</th><th>PAKD</th><th>Status</th>
                <th>HTTP</th><th>Nhận lúc</th><th>Ghi chú</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log):
            [$bg, $fg] = ev_badge_color($log['event'] ?? '');
            $http = (int)$log['http_status'];
        ?>
        <tr>
            <td style="color:var(--lgray);">#<?= $log['id'] ?></td>
            <td><span class="badge" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= htmlspecialchars($log['event'] ?? '—') ?></span></td>
            <td class="mono"><?= htmlspecialchars($log['os_milestone_id'] ?? '—') ?></td>
            <td>
                <?php if (!empty($log['pakd_id'])): ?>
                <a href="/projects/du-an/detail?id=<?= (int)$log['pakd_id'] ?>" style="color:var(--primary);text-decoration:none;">#<?= (int)$log['pakd_id'] ?></a>
                <?php else: ?><span style="color:var(--lgray);">orphan</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($log['status'] ?? '—') ?></td>
            <td><span class="<?= $http >= 200 && $http < 300 ? 'pill-ok' : 'pill-err' ?>"><?= $http ?></span></td>
            <td style="color:var(--gray);white-space:nowrap;"><?= htmlspecialchars($log['received_at']) ?></td>
            <td style="font-size:.78rem;color:var(--gray);max-width:200px;"><?= htmlspecialchars($log['note'] ?? '') ?></td>
            <td><button class="btn-detail" onclick="showDetail(<?= $log['id'] ?>)">JSON</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">&laquo; Trước</a><?php endif; ?>
        <?php for ($p = max(1,$page-3); $p <= min($total_pages,$page+3); $p++): ?>
            <?php if ($p === $page): ?><span class="active"><?= $p ?></span>
            <?php else: ?><a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Sau &raquo;</a><?php endif; ?>
        <span style="color:var(--lgray);font-size:.78rem;margin-left:8px;"><?= number_format($total_rows) ?> bản ghi • trang <?= $page ?>/<?= $total_pages ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div></div>

<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle">Payload</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body"><pre id="modalContent"></pre></div>
    </div>
</div>

<script>
const MS_LOGS = <?= json_encode(array_column($logs, 'payload', 'id'), JSON_UNESCAPED_UNICODE) ?>;
function showDetail(id) {
    document.getElementById('modalTitle').textContent = 'Payload #' + id;
    let raw = MS_LOGS[id] || '';
    try { raw = JSON.stringify(JSON.parse(raw), null, 2); } catch(e) {}
    document.getElementById('modalContent').textContent = raw || '(rỗng)';
    document.getElementById('detailModal').classList.add('open');
}
function closeModal() { document.getElementById('detailModal').classList.remove('open'); }
document.getElementById('detailModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); if (typeof closeClearModal === 'function') closeClearModal(); } });
</script>

<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<div class="modal-overlay" id="clearModal">
    <div class="clear-box">
        <div class="modal-head">
            <h3>Xoá log theo khoảng ngày</h3>
            <button class="modal-close" onclick="closeClearModal()">&times;</button>
        </div>
        <div class="clear-body">
            <p style="font-size:.82rem;color:var(--gray);margin:0 0 16px;">Xoá vĩnh viễn log trong khoảng ngày đã chọn (theo ngày nhận). Không thể hoàn tác.</p>
            <div style="display:flex;gap:12px;">
                <div style="flex:1;"><label>Từ ngày</label><input type="date" id="clearFrom"></div>
                <div style="flex:1;"><label>Đến ngày</label><input type="date" id="clearTo"></div>
            </div>
            <div id="clearResult"></div>
        </div>
        <div class="clear-foot">
            <button class="btn-clear" onclick="closeClearModal()">Huỷ</button>
            <button class="btn-do-clear" id="btnDoClear" onclick="doClear()">Xoá log</button>
        </div>
    </div>
</div>
<script>
function openClearModal() {
    const today = new Date();
    const from  = new Date(today); from.setDate(from.getDate() - 30);
    document.getElementById('clearFrom').value = from.toISOString().slice(0,10);
    document.getElementById('clearTo').value   = today.toISOString().slice(0,10);
    const res = document.getElementById('clearResult'); res.style.display = 'none';
    document.getElementById('btnDoClear').disabled = false;
    document.getElementById('clearModal').classList.add('open');
}
function closeClearModal() {
    const m = document.getElementById('clearModal');
    if (m) m.classList.remove('open');
}
const _cm = document.getElementById('clearModal');
if (_cm) _cm.addEventListener('click', function(e) { if (e.target === this) closeClearModal(); });

function showClearResult(type, msg) {
    const el = document.getElementById('clearResult');
    el.style.display = 'block';
    if (type === 'success') { el.style.background = '#ecfdf5'; el.style.color = '#047857'; el.style.border = '1px solid #cde9da'; }
    else { el.style.background = '#fef2f2'; el.style.color = '#b91c1c'; el.style.border = '1px solid #f3d2d2'; }
    el.textContent = msg;
}
function doClear() {
    const from = document.getElementById('clearFrom').value;
    const to   = document.getElementById('clearTo').value;
    if (!from || !to) { showClearResult('error', 'Vui lòng chọn đủ ngày bắt đầu và kết thúc.'); return; }
    if (from > to)    { showClearResult('error', 'Ngày bắt đầu phải ≤ ngày kết thúc.'); return; }
    if (!confirm(`Xoá toàn bộ log từ ${from} đến ${to}?\nKhông thể hoàn tác.`)) return;
    const btn = document.getElementById('btnDoClear');
    btn.disabled = true; btn.textContent = 'Đang xoá...';
    fetch('/api/milestone_log_clear', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({date_from: from, date_to: to})
    })
    .then(r => r.json())
    .then(data => {
        btn.textContent = 'Xoá log';
        if (data.ok) {
            showClearResult('success', `Đã xoá ${Number(data.deleted).toLocaleString()} bản ghi (${data.date_from} → ${data.date_to}).`);
            setTimeout(() => { closeClearModal(); location.reload(); }, 1500);
        } else { btn.disabled = false; showClearResult('error', data.error || 'Có lỗi xảy ra.'); }
    })
    .catch(e => { btn.disabled = false; btn.textContent = 'Xoá log'; showClearResult('error', 'Lỗi kết nối: ' + e); });
}
</script>
<?php endif; ?>
</body>
</html>
