<?php
/**
 * Recruitment KPI dashboard + report (SOP §4 KPI, §8 reports).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/kpi.php';

$preset = $_GET['period'] ?? 'month';
[$from, $to, $periodLabel] = hrm_kpi_period($preset);

/* Export (Excel) — must run before any HTML output. */
if (($_GET['export'] ?? '') === 'xlsx') {
    require_once __DIR__ . '/../../config/config.php';
    if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit(); }
    require_once __DIR__ . '/../../includes/Exporter.php';
    $m = hrm_kpi_metrics($conn, $from, $to);
    $headers = ['Chỉ số', 'Giá trị', 'Mục tiêu'];
    $rows = [];
    foreach ($m['kpi'] as $k) { $rows[] = [$k['label'], ($k['value'] === null ? 'N/A' : $k['value'] . $k['unit']), $k['target']]; }
    $rows[] = ['', '', ''];
    $rows[] = ['Vị trí đang tuyển', $m['funnel']['open'], ''];
    $rows[] = ['CV nhận', $m['funnel']['cv'], ''];
    $rows[] = ['Đã phỏng vấn', $m['funnel']['interviewed'], ''];
    $rows[] = ['Offer', $m['funnel']['offers'], ''];
    $rows[] = ['Đã nhận việc', $m['funnel']['joined'], ''];
    Exporter::streamXls('recruitment_kpi_' . date('Ymd'), 'Recruitment KPI - ' . $periodLabel, $headers, $rows);
    exit;
}

require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
$m = hrm_kpi_metrics($conn, $from, $to);

hrm_header('KPI & Báo cáo', 'Chỉ số tuyển dụng - ' . $periodLabel, 'kpi');
?>
<div class="rc-toolbar">
    <div class="rc-tabs">
        <?php foreach (['month' => 'Tháng này', 'quarter' => 'Quý này', 'all' => 'Toàn bộ'] as $k => $v): ?>
            <a href="/hrm/kpi?period=<?= $k ?>" class="rc-tab <?= $preset===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <a class="rc-btn ghost" href="/hrm/kpi?period=<?= h($preset) ?>&export=xlsx">Xuất Excel</a>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px">
    <?php foreach ($m['kpi'] as $k):
        $color = $k['ok'] === null ? '#64748b' : ($k['ok'] ? '#16a34a' : '#dc2626'); ?>
        <div class="rc-card" style="margin:0">
            <div class="rc-muted"><?= h($k['label']) ?></div>
            <div style="font-size:28px;font-weight:700;color:<?= $color ?>"><?= $k['value'] === null ? '—' : h($k['value'] . $k['unit']) ?></div>
            <div class="rc-muted">Mục tiêu: <?= h($k['target']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:14px">Phễu tuyển dụng (<?= h($periodLabel) ?>)</h3>
    <?php
    $funnel = [
        ['Vị trí đang tuyển', $m['funnel']['open'], '#0e6b5c'],
        ['CV nhận', $m['funnel']['cv'], '#2563eb'],
        ['Đã phỏng vấn', $m['funnel']['interviewed'], '#7c3aed'],
        ['Offer', $m['funnel']['offers'], '#b45309'],
        ['Đã nhận việc', $m['funnel']['joined'], '#16a34a'],
    ];
    $max = max(1, $m['funnel']['cv'], $m['funnel']['open']);
    foreach ($funnel as $f):
        $w = max(4, round($f[1] * 100 / $max)); ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <div style="width:140px;font-size:13px;color:#475569"><?= h($f[0]) ?></div>
            <div style="flex:1;background:#f1f5f9;border-radius:6px;overflow:hidden"><div style="width:<?= $w ?>%;background:<?= $f[2] ?>;color:#fff;font-size:12px;font-weight:700;padding:5px 10px;border-radius:6px;min-width:32px"><?= (int)$f[1] ?></div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Báo cáo nhanh</h3>
    <table class="rc-table">
        <tbody>
        <tr><td>Vị trí đang tuyển</td><td style="text-align:right"><b><?= (int)$m['funnel']['open'] ?></b></td></tr>
        <tr><td>CV nhận (<?= h($periodLabel) ?>)</td><td style="text-align:right"><b><?= (int)$m['funnel']['cv'] ?></b></td></tr>
        <tr><td>Đã phỏng vấn</td><td style="text-align:right"><b><?= (int)$m['funnel']['interviewed'] ?></b></td></tr>
        <tr><td>Offer đã tạo</td><td style="text-align:right"><b><?= (int)$m['funnel']['offers'] ?></b></td></tr>
        <tr><td>Đã nhận việc (joined)</td><td style="text-align:right"><b><?= (int)$m['funnel']['joined'] ?></b></td></tr>
    </tbody></table>
    <div class="rc-muted" style="margin-top:10px">Satisfaction &amp; Attrition cần nguồn dữ liệu khảo sát/nghỉ việc - bổ sung sau khi có nguồn.</div>
</div>
<?php
hrm_footer();
