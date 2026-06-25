<?php
/**
 * HRM Dashboard - tổng quan điều hành nhân sự.
 * Số liệu toàn hệ thống + phễu tuyển dụng + cảnh báo SLA + tải theo phòng ban + hoạt động gần đây.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/kpi.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$preset = $_GET['period'] ?? 'month';
[$from, $to, $periodLabel] = hrm_kpi_period($preset);

$counts   = hrm_dashboard_counts($conn);
$metrics  = hrm_kpi_metrics($conn, $from, $to);
$alerts   = hrm_dashboard_alerts($conn);
$deptLoad = hrm_dashboard_dept_load($conn);
$activity = hrm_dashboard_activity($conn, 12);

/** Nhãn dễ đọc cho action trong audit log. */
$actionLabel = function (string $a): string {
    $map = [
        'create' => 'Tạo', 'update' => 'Cập nhật', 'delete' => 'Xóa',
        'approve' => 'Phê duyệt', 'reject' => 'Từ chối', 'submit' => 'Gửi duyệt',
        'import' => 'Nhập dữ liệu', 'export' => 'Xuất dữ liệu', 'merge' => 'Gộp',
        'move_stage' => 'Chuyển giai đoạn', 'hire' => 'Tuyển dụng',
    ];
    return $map[$a] ?? ucfirst(str_replace('_', ' ', $a));
};
$entityLabel = function (string $e): string {
    $map = ['hrf' => 'HRF', 'request' => 'HRF', 'job' => 'Tin tuyển dụng', 'candidate' => 'Ứng viên',
            'application' => 'Hồ sơ', 'offer' => 'Offer', 'onboarding' => 'Onboarding',
            'interview' => 'Phỏng vấn', 'pool' => 'Talent pool'];
    return $map[$e] ?? $e;
};

hrm_header('Tổng quan', 'Dashboard điều hành HRM - ' . $periodLabel, 'dashboard');
?>
<div class="rc-toolbar">
    <div class="rc-tabs">
        <?php foreach (['month' => 'Tháng này', 'quarter' => 'Quý này', 'all' => 'Toàn bộ'] as $k => $v): ?>
            <a href="/hrm/dashboard?period=<?= $k ?>" class="rc-tab <?= $preset === $k ? 'active' : '' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <a class="rc-btn ghost" href="/hrm/kpi?period=<?= h($preset) ?>">KPI chi tiết</a>
</div>

<!-- ① Thẻ số liệu toàn hệ thống (point-in-time) -->
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:18px">
    <?php
    $cards = [
        ['HRF chờ duyệt',       $counts['hrf_pending'],   '#b45309', '/hrm/requests'],
        ['Vị trí đang mở',      $counts['jobs_open'],     '#0e6b5c', '/hrm/jobs'],
        ['Ứng viên đang xử lý', $counts['apps_active'],   '#2563eb', '/hrm/candidates'],
        ['Offer chờ phản hồi',  $counts['offers_out'],    '#7c3aed', '/hrm/candidates'],
        ['Đang onboarding',     $counts['onb_active'],    '#0891b2', '/hrm/onboarding'],
        ['Sắp tới hạn TV',      $counts['probation_due'], '#dc2626', '/hrm/probation'],
    ];
    foreach ($cards as $c): ?>
        <a href="<?= $c[3] ?>" class="rc-card" style="margin:0;text-decoration:none;display:block">
            <div class="rc-muted" style="min-height:30px"><?= h($c[0]) ?></div>
            <div style="font-size:30px;font-weight:700;color:<?= $c[2] ?>"><?= (int)$c[1] ?></div>
        </a>
    <?php endforeach; ?>
</div>

<!-- ② Phễu tuyển dụng -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:14px">Phễu tuyển dụng (<?= h($periodLabel) ?>)</h3>
    <?php
    $funnel = [
        ['Vị trí đang tuyển', $metrics['funnel']['open'], '#0e6b5c'],
        ['CV nhận', $metrics['funnel']['cv'], '#2563eb'],
        ['Đã phỏng vấn', $metrics['funnel']['interviewed'], '#7c3aed'],
        ['Offer', $metrics['funnel']['offers'], '#b45309'],
        ['Đã nhận việc', $metrics['funnel']['joined'], '#16a34a'],
    ];
    $max = max(1, $metrics['funnel']['cv'], $metrics['funnel']['open']);
    foreach ($funnel as $f):
        $w = max(4, round($f[1] * 100 / $max)); ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <div style="width:140px;font-size:13px;color:#475569"><?= h($f[0]) ?></div>
            <div style="flex:1;background:#f1f5f9;border-radius:6px;overflow:hidden"><div style="width:<?= $w ?>%;background:<?= $f[2] ?>;color:#fff;font-size:12px;font-weight:700;padding:5px 10px;border-radius:6px;min-width:32px"><?= (int)$f[1] ?></div></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ③ Cảnh báo SLA / điểm tắc nghẽn -->
<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Cảnh báo cần xử lý (<?= count($alerts) ?>)</h3>
    <?php if (!$alerts): ?>
        <div class="rc-muted">Không có cảnh báo nào. Mọi việc đang đúng tiến độ. ✓</div>
    <?php else: ?>
        <table class="rc-table">
            <thead><tr><th style="width:160px">Loại</th><th>Nội dung</th><th style="width:130px">Thời điểm</th><th style="width:90px"></th></tr></thead>
            <tbody>
            <?php foreach ($alerts as $a): ?>
                <tr>
                    <td><span class="rc-badge" style="background:<?= $a['color'] ?>1a;color:<?= $a['color'] ?>"><?= h($a['type']) ?></span></td>
                    <td><?= h($a['text']) ?></td>
                    <td style="color:#dc2626"><?= $a['due'] ? date('d/m/Y H:i', strtotime($a['due'])) : '-' ?></td>
                    <td><a href="<?= h($a['link']) ?>" class="rc-btn ghost" style="padding:5px 12px">Xử lý</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ④ Hai cột: tải theo phòng ban + hoạt động gần đây -->
<div class="rc-grid2">
    <div class="rc-card">
        <h3 style="font-size:14px;margin-bottom:10px">Tuyển dụng theo phòng ban</h3>
        <?php if (!$deptLoad): ?>
            <div class="rc-muted">Chưa có dữ liệu.</div>
        <?php else: ?>
            <table class="rc-table">
                <thead><tr><th>Phòng ban</th><th style="width:90px;text-align:right">Vị trí mở</th><th style="width:90px;text-align:right">Ứng viên</th></tr></thead>
                <tbody>
                <?php foreach ($deptLoad as $name => $d): ?>
                    <tr>
                        <td><?= h($name) ?></td>
                        <td style="text-align:right"><b><?= $d['open'] ?></b></td>
                        <td style="text-align:right"><?= $d['active'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="rc-card">
        <h3 style="font-size:14px;margin-bottom:10px">Hoạt động gần đây</h3>
        <?php if (!$activity): ?>
            <div class="rc-muted">Chưa có hoạt động nào được ghi nhận.</div>
        <?php else: ?>
            <?php foreach ($activity as $a): ?>
                <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
                    <div style="flex:1;color:#0f172a">
                        <b><?= h($actionLabel($a['action'])) ?></b>
                        <span class="rc-muted"><?= h($entityLabel($a['entity_type'] ?: '')) ?></span>
                        <?php if (!empty($a['detail'])): ?><span style="color:#475569"><?= h(mb_strimwidth($a['detail'], 0, 60, '…')) ?></span><?php endif; ?>
                        <div class="rc-muted"><?= h($a['full_name'] ?: 'Hệ thống') ?> · <?= date('d/m H:i', strtotime($a['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
hrm_footer();
