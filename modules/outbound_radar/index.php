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

// Trang động — không cho trình duyệt cache (tránh hiện bản cũ khi xem ?id=).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Nạp engine (các hàm run_pipeline, analyze, ...) mà không kích hoạt CLI/dev-web.
define('OUTBOUND_RADAR_LIB', true);
require_once __DIR__ . '/radar.php';
require_once __DIR__ . '/store.php';

radar_ensure_table($conn);

$user_id = (int) $_SESSION['user_id'];

// Nạp API key từ DB (system_settings) cho engine dùng — hoạt động trên live.
$dbKey = radar_get_setting($conn, 'anthropic_api_key');
if ($dbKey) {
    $GLOBALS['RADAR_API_KEY'] = $dbKey;
}

// --- Xử lý POST (xoá / lưu ghi chú / lưu API key) trước khi xuất HTML ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = (int) ($_POST['id'] ?? 0);
    if ($action === 'save_key') {
        radar_set_setting($conn, 'anthropic_api_key', trim((string) ($_POST['api_key'] ?? '')),
            'Anthropic API key cho Outbound Radar');
        header('Location: /outbound-radar');
        exit();
    }
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

$scan          = null;   // kết quả run_pipeline
$scanId     = null;   // id bản ghi để xem lại
$scanTime   = null;   // thời điểm quét
$fromCache  = false;  // lấy từ DB thay vì fetch mới
$noteCurrent = '';    // ghi chú hiện tại của bản đang xem

if ($viewId) {
    // Xem lại bản đã lưu (không fetch).
    $saved = radar_get_by_id($conn, $viewId);
    if ($saved) {
        $scan = $saved['data']; $scanId = $saved['id']; $scanTime = $saved['created_at'];
        $fromCache = true; $noteCurrent = $saved['note'] ?? '';
    } else {
        $scan = ['ok' => false, 'error' => 'Không tìm thấy bản quét #' . $viewId . '.'];
    }
} elseif ($url !== '') {
    if (!$refresh) {
        $cached = radar_find_recent($conn, $url, 7); // cache 7 ngày
        if ($cached) {
            $scan = $cached['data']; $scanId = $cached['id']; $scanTime = $cached['created_at'];
            $fromCache = true; $noteCurrent = $cached['note'] ?? '';
        }
    }
    if ($scan === null) {
        $scan = run_pipeline($url);
        if (!empty($scan['ok'])) {
            $scanId   = radar_save_scan($conn, $user_id, $url, $scan);
            $scanTime = date('Y-m-d H:i:s');
        }
    }
}

$history = radar_history($conn, 50, $q);

// Trạng thái API key (để hiển thị che bớt).
$effectiveKey  = anthropic_api_key();
$keyConfigured = !empty($effectiveKey);
$keyMasked     = $keyConfigured
    ? substr((string) $effectiveKey, 0, 8) . '...' . substr((string) $effectiveKey, -4)
    : '';
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
        /* layout */
        .radar-container { padding: 28px 28px 64px; max-width: 960px; margin: 0 auto; }
        .radar-head { margin-bottom: 22px; }
        .radar-head h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; letter-spacing: -.01em; }
        .radar-head p { color: #6b7280; margin: 0; font-size: 14px; max-width: 660px; line-height: 1.55; }
        .radar-head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .gear-btn { flex: none; width: 40px; height: 40px; padding: 0 !important; border-radius: 10px;
            background: #fff !important; border: 1px solid #d8dce3 !important; color: #6b7280 !important;
            font-size: 18px; line-height: 1; cursor: pointer; }
        .gear-btn:hover { background: #f3f4f6 !important; color: #111827 !important; }
        .gear-btn.need { border-color: #fcd34d !important; color: #d97706 !important; background: #fffbeb !important; }
        .settings-pop { display: none; }
        .settings-pop.open { display: block; }

        /* inputs & buttons (dùng chung) */
        .radar-container input[type=text],
        .radar-container input[type=password],
        .radar-container textarea {
            border: 1px solid #d8dce3; border-radius: 10px; font: inherit; background: #fff; color: #1f2937;
            padding: 11px 14px; transition: border-color .15s, box-shadow .15s;
        }
        .radar-container input[type=text]:focus,
        .radar-container input[type=password]:focus,
        .radar-container textarea:focus {
            outline: 0; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }
        .radar-container button {
            padding: 11px 18px; border: 0; border-radius: 10px; background: #2563eb; color: #fff;
            font-weight: 600; font-size: 14px; cursor: pointer; white-space: nowrap;
            transition: background .15s, transform .05s;
        }
        .radar-container button:hover { background: #1d4ed8; }
        .radar-container button:active { transform: translateY(1px); }
        .btn-danger { background: #dc2626 !important; }
        .btn-danger:hover { background: #b91c1c !important; }
        .btn-link-danger { background: none !important; border: 0 !important; color: #9ca3af !important;
            cursor: pointer; font-size: 16px; line-height: 1; padding: 2px 6px !important; }
        .btn-link-danger:hover { color: #b91c1c !important; }

        /* form */
        .radar-form { display: flex; gap: 10px; margin-bottom: 22px; }
        .radar-form input { flex: 1; }

        /* card */
        .radar-card { background: #fff; border: 1px solid #e7e9ee; border-radius: 14px; padding: 20px 22px;
            margin-bottom: 16px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .radar-card.err { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; box-shadow: none; }
        .radar-card.settings { background: #fafbfc; border-style: dashed; box-shadow: none; }
        .radar-card h3 { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #8a93a2;
            margin: 0 0 12px; font-weight: 600; }

        /* result header + stats */
        .radar-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
        .radar-score { font-size: 34px; font-weight: 800; color: #2563eb; line-height: 1; }
        .radar-stats { display: flex; flex-wrap: wrap; gap: 28px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f3f4; }
        .radar-stats .n { font-size: 26px; font-weight: 700; color: #111827; }
        .radar-stats .l { color: #8a93a2; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }

        /* chips, jobs, badge */
        .chip { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 999px;
            padding: 4px 11px; margin: 3px 5px 0 0; font-size: 13px; font-weight: 500; }
        .job { display: flex; gap: 12px; padding: 9px 0; border-bottom: 1px solid #f1f3f4; font-size: 14px; align-items: baseline; }
        .job:last-child { border: 0; }
        .job .days { min-width: 52px; color: #d97706; font-weight: 600; font-size: 13px; }
        .job .loc { color: #8a93a2; }
        .badge { display: inline-block; background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5;
            border-radius: 8px; padding: 5px 12px; font-weight: 600; font-size: 13px; }

        /* pitch */
        .pitch { white-space: pre-wrap; background: #f9fafb; border: 1px solid #eceef2; border-radius: 10px;
            padding: 16px; font: 14px/1.65 inherit; color: #1f2937; margin: 0; }

        /* misc */
        .tag { font-size: 12px; color: #8a93a2; }
        .radar-meta { font-size: 13px; color: #6b7280; margin: -6px 0 16px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .radar-meta a { color: #2563eb; text-decoration: none; font-weight: 500; }
        .radar-meta a:hover { text-decoration: underline; }

        /* history */
        .radar-hist { width: 100%; border-collapse: collapse; font-size: 14px; }
        .radar-hist th { text-align: left; color: #8a93a2; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;
            padding: 8px; border-bottom: 1px solid #e7e9ee; font-weight: 600; }
        .radar-hist td { padding: 10px 8px; border-bottom: 1px solid #f4f5f7; vertical-align: top; }
        .radar-hist tr:hover td { background: #fafbfc; }
        .radar-hist tr.cur td { background: #eff6ff; }
        .radar-hist a { color: #111827; text-decoration: none; font-weight: 600; }
        .radar-hist a:hover { color: #2563eb; }

        /* note + search */
        .radar-note { width: 100%; resize: vertical; }
        .radar-search { min-width: 240px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../includes/topbar.php'; ?>
        <div class="radar-container">
            <div class="radar-head">
                <div class="radar-head-row">
                    <div>
                        <h1>📡 Outbound Radar</h1>
                        <p>Dò tín hiệu thiếu năng lực dev của một công ty → chấm điểm outsourcing → gợi ý hợp tác + pitch. Dán website hoặc URL job board.</p>
                    </div>
                    <button type="button" class="gear-btn<?= $keyConfigured ? '' : ' need' ?>" title="Cấu hình API key"
                        onclick="document.getElementById('radarSettings').classList.toggle('open')">⚙</button>
                </div>
                <div id="radarSettings" class="settings-pop">
                    <div class="radar-card settings" style="margin:14px 0 0;">
                        <h3>Cấu hình API key (Anthropic)</h3>
                        <p class="tag" style="margin:0 0 10px;">
                            Trạng thái:
                            <?php if ($keyConfigured): ?>
                                <strong style="color:#059669;">Đã cấu hình</strong> (<?= h($keyMasked) ?>)
                            <?php else: ?>
                                <strong style="color:#d97706;">Chưa cấu hình</strong> — pitch dùng template, fallback careers-AI không chạy
                            <?php endif; ?>
                        </p>
                        <form method="post" action="/outbound-radar" style="display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="hidden" name="action" value="save_key">
                            <input type="password" name="api_key" class="radar-search" style="flex:1; min-width:280px;" placeholder="sk-ant-api03-..." autocomplete="off">
                            <button type="submit">Lưu key</button>
                        </form>
                        <p class="tag" style="margin:10px 0 0;">Lưu vào DB (system_settings) → dùng được cả trên live. Đổi/rotate bất cứ lúc nào.</p>
                    </div>
                </div>
            </div>
            <form class="radar-form" method="get" action="/outbound-radar">
                <input type="text" name="url" autofocus
                       placeholder="vd: jobs.ashbyhq.com/Ashby · boards.greenhouse.io/gitlab · acme.com"
                       value="<?= h($url) ?>">
                <button type="submit">Quét</button>
            </form>

            <?php if ($scan && !empty($scan['ok'])): ?>
                <div class="radar-meta">
                    <?= $fromCache ? '🗄️ Bản lưu' : '✨ Vừa quét' ?>
                    <?php if ($scanTime): ?> · <?= h($scanTime) ?><?php endif; ?>
                    <?php if ($url !== ''): ?>
                        · <a href="/outbound-radar?url=<?= h(urlencode($url)) ?>&amp;refresh=1">↻ Quét lại (gọi Claude)</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($scan && !$scan['ok']): ?>
                <div class="radar-card err">✗ <?= h($scan['error']) ?></div>
            <?php elseif ($scan && $scan['ok']):
                $a = $scan['analysis']; $d = $scan['detect']; ?>
                <div class="radar-card">
                    <div class="radar-top">
                        <div>
                            <div style="font-size:19px;font-weight:700;"><?= h($scan['company']) ?></div>
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
                    <span class="badge"><?= h($scan['eng']['model']) ?></span>
                    <p class="tag" style="margin:10px 0 0;"><?= h($scan['eng']['why']) ?></p>
                    <h3 style="margin-top:18px;">Pitch (nháp · <?= !empty($scan['ai_pitch']) ? 'viết bởi Claude' : 'template' ?>)</h3>
                    <pre class="pitch"><?= h(sanitize_pitch((string)($scan['pitch'] ?? ''))) ?></pre>
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
