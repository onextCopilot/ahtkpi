<?php
/**
 * Outbound Radar — trang tích hợp trong app ahtkpi.
 * Route: /outbound-radar  (đăng ký trong index.php $routes)
 */
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Chỉ account hyun.cao được dùng Outbound Radar.
$radar_allowed = (($_SESSION['username'] ?? '') === 'hyun.cao')
    || (($_SESSION['full_name'] ?? '') === 'Hyun Cao');
if (!$radar_allowed) {
    header("Location: /dashboard");
    exit();
}

// Nạp engine (các hàm run_pipeline, analyze, ...) mà không kích hoạt CLI/dev-web.
define('OUTBOUND_RADAR_LIB', true);
require_once __DIR__ . '/radar.php';
require_once __DIR__ . '/store.php';

radar_ensure_table($conn);

$user_id = (int) $_SESSION['user_id'];

// --- Xử lý POST (xoá / lưu ghi chú) trước khi xuất HTML ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete_scan' && $pid) {
        radar_delete($conn, $pid);
        header('Location: /outbound-radar');
        exit();
    }
    if ($action === 'save_note' && $pid) {
        radar_save_note($conn, $pid, trim((string) ($_POST['note'] ?? '')));
        header('Location: /outbound-radar?id=' . $pid);
        exit();
    }
}

$url     = trim((string) ($_GET['url'] ?? ''));
$viewId  = (int) ($_GET['id'] ?? 0);
$refresh = !empty($_GET['refresh']);
$q       = trim((string) ($_GET['q'] ?? ''));

$r          = null;   // kết quả run_pipeline
$scanId     = null;   // id bản ghi để xem lại
$scanTime   = null;   // thời điểm quét
$fromCache  = false;  // lấy từ DB thay vì fetch mới
$noteCurrent = '';    // ghi chú hiện tại của bản đang xem

if ($viewId) {
    // Xem lại bản đã lưu (không fetch).
    $saved = radar_get_by_id($conn, $viewId);
    if ($saved) {
        $r = $saved['data']; $scanId = $saved['id']; $scanTime = $saved['created_at'];
        $fromCache = true; $noteCurrent = $saved['note'] ?? '';
    } else {
        $r = ['ok' => false, 'error' => 'Không tìm thấy bản quét #' . $viewId . '.'];
    }
} elseif ($url !== '') {
    if (!$refresh) {
        $cached = radar_find_recent($conn, $url, 7); // cache 7 ngày
        if ($cached) {
            $r = $cached['data']; $scanId = $cached['id']; $scanTime = $cached['created_at'];
            $fromCache = true; $noteCurrent = $cached['note'] ?? '';
        }
    }
    if ($r === null) {
        $r = run_pipeline($url);
        if (!empty($r['ok'])) {
            $scanId   = radar_save_scan($conn, $user_id, $url, $r);
            $scanTime = date('Y-m-d H:i:s');
        }
    }
}

$history = radar_history($conn, 50, $q);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Outbound Radar</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .radar-container { padding: 24px; max-width: 920px; }
        .radar-head h1 { font-size: 22px; margin: 0 0 4px; }
        .radar-head p { color: #6b7280; margin: 0 0 20px; }
        .radar-form { display: flex; gap: 8px; margin-bottom: 22px; }
        .radar-form input { flex: 1; padding: 11px 13px; border: 1px solid #e4e7ec; border-radius: 9px; font: inherit; }
        .radar-form button { padding: 11px 20px; border: 0; border-radius: 9px; background: #2563eb; color: #fff; font-weight: 600; cursor: pointer; }
        .radar-card { background: #fff; border: 1px solid #e4e7ec; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
        .radar-card.err { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
        .radar-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
        .radar-score { font-size: 30px; font-weight: 800; color: #2563eb; }
        .radar-stats { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 16px; }
        .radar-stats .n { font-size: 24px; font-weight: 700; }
        .radar-stats .l { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .radar-card h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 0 0 10px; }
        .chip { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 999px; padding: 3px 10px; margin: 3px 4px 0 0; font-size: 13px; }
        .job { display: flex; gap: 10px; padding: 7px 0; border-bottom: 1px solid #f1f3f4; font-size: 14px; }
        .job:last-child { border: 0; }
        .job .days { min-width: 54px; color: #d97706; font-weight: 600; }
        .job .loc { color: #6b7280; }
        .badge { display: inline-block; background: #059669; color: #fff; border-radius: 7px; padding: 4px 10px; font-weight: 600; font-size: 13px; }
        .pitch { white-space: pre-wrap; background: #f9fafb; border: 1px dashed #e4e7ec; border-radius: 9px; padding: 14px; font: 14px/1.6 inherit; }
        .tag { font-size: 12px; color: #6b7280; }
        .radar-meta { font-size: 13px; color: #6b7280; margin: -8px 0 16px; }
        .radar-meta a { color: #2563eb; text-decoration: none; }
        .radar-hist { width: 100%; border-collapse: collapse; font-size: 14px; }
        .radar-hist th { text-align: left; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; padding: 6px 8px; border-bottom: 1px solid #e4e7ec; }
        .radar-hist td { padding: 8px; border-bottom: 1px solid #f1f3f4; }
        .radar-hist tr.cur { background: #eff6ff; }
        .radar-hist a { color: #1f2937; text-decoration: none; font-weight: 600; }
        .radar-note { width: 100%; padding: 11px 13px; border: 1px solid #e4e7ec; border-radius: 9px; font: inherit; resize: vertical; }
        .radar-search { padding: 8px 11px; border: 1px solid #e4e7ec; border-radius: 8px; font: inherit; min-width: 220px; }
        .btn-danger { background: #dc2626; }
        .btn-link-danger { background: none; border: 0; color: #b91c1c; cursor: pointer; font-size: 15px; padding: 2px 6px; }
        .btn-link-danger:hover { color: #7f1d1d; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="radar-container">
            <div class="radar-head">
                <h1>📡 Outbound Radar</h1>
                <p>Dò tín hiệu thiếu năng lực dev của một công ty → chấm điểm outsourcing → gợi ý hợp tác + pitch. Dán website hoặc URL job board.</p>
            </div>
            <form class="radar-form" method="get" action="/outbound-radar">
                <input type="text" name="url" autofocus
                       placeholder="vd: jobs.ashbyhq.com/Ashby · boards.greenhouse.io/gitlab · acme.com"
                       value="<?= h($url) ?>">
                <button type="submit">Quét</button>
            </form>

            <?php if ($r && !empty($r['ok'])): ?>
                <div class="radar-meta">
                    <?= $fromCache ? '🗄️ Bản lưu' : '✨ Vừa quét' ?>
                    <?php if ($scanTime): ?> · <?= h($scanTime) ?><?php endif; ?>
                    <?php if ($url !== ''): ?>
                        · <a href="/outbound-radar?url=<?= h(urlencode($url)) ?>&amp;refresh=1">↻ Quét lại (gọi Claude)</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($r && !$r['ok']): ?>
                <div class="radar-card err">✗ <?= h($r['error']) ?></div>
            <?php elseif ($r && $r['ok']):
                $a = $r['analysis']; $d = $r['detect']; ?>
                <div class="radar-card">
                    <div class="radar-top">
                        <div>
                            <div style="font-size:19px;font-weight:700;"><?= h($r['company']) ?></div>
                            <div class="tag">ATS: <?= h($d['provider']) ?> · token <?= h($d['token']) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div class="radar-score"><?= (int) $a['score'] ?><span style="font-size:15px;color:#6b7280;">/100</span></div>
                            <div class="tag">điểm phù hợp</div>
                        </div>
                    </div>
                    <div class="radar-stats">
                        <div><div class="n"><?= (int) $a['total_jobs'] ?></div><div class="l">tổng job</div></div>
                        <div><div class="n"><?= (int) $a['dev_count'] ?></div><div class="l">job dev</div></div>
                        <div><div class="n"><?= count($a['long_open']) ?></div><div class="l">mở 45+ ngày</div></div>
                        <div><div class="n"><?= count($a['contract']) ?></div><div class="l">contract/freelance</div></div>
                    </div>
                </div>

                <div class="radar-card">
                    <h3>Khớp năng lực Onext</h3>
                    <?php foreach ($a['cap_hits'] as $cap => $n): ?><span class="chip"><?= h($cap) ?> · <?= (int) $n ?></span><?php endforeach; ?>
                    <?php if (!$a['cap_hits']): ?><span class="tag">—</span><?php endif; ?>
                    <h3 style="margin-top:16px;">Skill nổi bật</h3>
                    <?php foreach (array_slice($a['skill_freq'], 0, 12, true) as $s => $n): ?><span class="chip"><?= h($s) ?> (<?= (int) $n ?>)</span><?php endforeach; ?>
                </div>

                <div class="radar-card">
                    <h3>Vị trí dev đang mở (tối đa 12)</h3>
                    <?php foreach (array_slice($a['dev_jobs'], 0, 12) as $j):
                        $days = $j['days_open'] !== null ? $j['days_open'] . 'd' : '?'; ?>
                        <div class="job">
                            <span class="days">[<?= h($days) ?>]</span>
                            <span><a href="<?= h($j['url']) ?>" target="_blank" rel="noopener" style="color:#1f2937;text-decoration:none;"><?= h($j['title']) ?></a>
                                <?php if ($j['location']): ?><span class="loc">· <?= h($j['location']) ?></span><?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="radar-card">
                    <h3>Đề xuất hợp tác</h3>
                    <span class="badge"><?= h($r['eng']['model']) ?></span>
                    <p class="tag" style="margin:10px 0 0;"><?= h($r['eng']['why']) ?></p>
                    <h3 style="margin-top:18px;">Pitch (nháp · <?= !empty($r['ai_pitch']) ? 'viết bởi Claude' : 'template' ?>)</h3>
                    <pre class="pitch"><?= h(sanitize_pitch((string)($r['pitch'] ?? ''))) ?></pre>
                </div>

                <?php if ($scanId): ?>
                <div class="radar-card">
                    <h3>Ghi chú lead</h3>
                    <form method="post" action="/outbound-radar">
                        <input type="hidden" name="action" value="save_note">
                        <input type="hidden" name="id" value="<?= (int) $scanId ?>">
                        <textarea name="note" rows="3" class="radar-note" placeholder="Ghi chú nội bộ: đã liên hệ? phản hồi? người phụ trách?..."><?= h($noteCurrent) ?></textarea>
                        <div style="margin-top:10px; display:flex; gap:10px; align-items:center;">
                            <button type="submit">Lưu ghi chú</button>
                            <span class="tag">Lead #<?= (int) $scanId ?></span>
                        </div>
                    </form>
                    <form method="post" action="/outbound-radar" onsubmit="return confirm('Xoá bản quét này khỏi lịch sử?');" style="margin-top:12px;">
                        <input type="hidden" name="action" value="delete_scan">
                        <input type="hidden" name="id" value="<?= (int) $scanId ?>">
                        <button type="submit" class="btn-danger">🗑 Xoá lead này</button>
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="radar-card">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <h3 style="margin:0;">Lịch sử quét (<?= count($history) ?><?= $q !== '' ? ' · lọc' : '' ?>)</h3>
                    <form method="get" action="/outbound-radar" style="display:flex; gap:6px;">
                        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Tìm công ty / url / ghi chú" class="radar-search">
                        <button type="submit">Tìm</button>
                        <?php if ($q !== ''): ?><a href="/outbound-radar" class="tag" style="align-self:center;">Xoá lọc</a><?php endif; ?>
                    </form>
                </div>
                <?php if (!$history): ?>
                    <span class="tag" style="display:block; margin-top:12px;"><?= $q !== '' ? 'Không có kết quả khớp.' : 'Chưa có lần quét nào.' ?></span>
                <?php else: ?>
                    <table class="radar-hist" style="margin-top:12px;">
                        <thead>
                            <tr><th>Công ty</th><th>Điểm</th><th>Dev</th><th>Nguồn</th><th>Người quét</th><th>Lúc</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr class="<?= ((int)$row['id'] === (int)$scanId) ? 'cur' : '' ?>">
                                <td>
                                    <a href="/outbound-radar?id=<?= (int)$row['id'] ?>"><?= h($row['company'] ?: $row['input_url']) ?></a>
                                    <?php if (!empty($row['note'])): ?>
                                        <div class="tag" style="font-weight:400; margin-top:2px;">📝 <?= h(mb_strimwidth($row['note'], 0, 70, '…')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><b><?= (int)$row['score'] ?></b></td>
                                <td><?= (int)$row['dev_count'] ?></td>
                                <td><span class="tag"><?= $row['job_source'] === 'ai' ? 'AI' : 'ATS' ?><?= $row['ai_pitch'] ? ' · pitch AI' : '' ?></span></td>
                                <td class="tag"><?= h($row['full_name'] ?? '—') ?></td>
                                <td class="tag"><?= h($row['created_at']) ?></td>
                                <td>
                                    <form method="post" action="/outbound-radar" onsubmit="return confirm('Xoá lead này?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete_scan">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn-link-danger" title="Xoá">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
