<?php
/**
 * Chẩn đoán tải CV (dùng trên live). Route: /hrm/cv-diag
 * Kiểm tra outbound + quyền ghi uploads + thử tải 1 URL.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();

$root = realpath(__DIR__ . '/../../');
$updir = $root . '/uploads/hrm/cvs';
$testUrl = trim($_GET['url'] ?? '');
$result = null;
if ($testUrl !== '') {
    $GLOBALS['hrm_cv_last_err'] = '';
    $t0 = microtime(true);
    $path = hrm_download_cv($testUrl, 'diag_test');
    $ms = round((microtime(true) - $t0) * 1000);
    $result = [
        'path' => $path,
        'err'  => $GLOBALS['hrm_cv_last_err'] ?? '',
        'ms'   => $ms,
        'size' => $path ? @filesize($root . $path) : 0,
    ];
}

hrm_header('Chẩn đoán tải CV', 'Kiểm tra outbound & quyền ghi file', 'candidates');
?>
<div class="rc-toolbar"><a href="/hrm/candidates/import" class="rc-tab">← Import</a><div></div></div>

<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Môi trường</h3>
    <table class="rc-table"><tbody>
        <tr><td>PHP version</td><td><b><?= PHP_VERSION ?></b></td></tr>
        <tr><td>curl khả dụng</td><td><b style="color:<?= function_exists('curl_init')?'#16a34a':'#dc2626' ?>"><?= function_exists('curl_init')?'Có':'KHÔNG' ?></b></td></tr>
        <tr><td>allow_url_fopen</td><td><?= ini_get('allow_url_fopen') ? 'Bật' : 'Tắt' ?></td></tr>
        <tr><td>Thư mục uploads</td><td><code><?= h($updir) ?></code></td></tr>
        <tr><td>Tồn tại</td><td><?= is_dir($updir) ? 'Có' : 'Chưa (sẽ tự tạo)' ?></td></tr>
        <tr><td>Ghi được</td><?php $w = is_dir($updir) ? is_writable($updir) : is_writable(dirname($updir, 3)); ?><td><b style="color:<?= $w?'#16a34a':'#dc2626' ?>"><?= $w?'Có':'KHÔNG ghi được' ?></b></td></tr>
        <tr><td>display_errors</td><td><?= ini_get('display_errors') ? 'Bật (nên TẮT trên live)' : 'Tắt' ?></td></tr>
    </tbody></table>
</div>

<div class="rc-card">
    <h3 style="font-size:14px;margin-bottom:10px">Thử tải 1 link CV</h3>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
        <input name="url" value="<?= h($testUrl) ?>" placeholder="Dán link CV (https://data-gcdn.basecdn.net/...)" style="flex:1;min-width:320px;padding:9px 12px;border:1px solid var(--bd);border-radius:8px;font-size:13px">
        <button class="rc-btn">Thử tải</button>
    </form>
    <?php if ($result !== null): ?>
        <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:<?= $result['path']?'#f0fdf4':'#fef2f2' ?>;border:1px solid <?= $result['path']?'#bbf7d0':'#fecaca' ?>">
            <?php if ($result['path']): ?>
                <b style="color:#16a34a">✓ Tải thành công</b> trong <?= $result['ms'] ?>ms · <?= number_format($result['size']) ?> bytes<br>
                Lưu tại: <a href="<?= h($result['path']) ?>" target="_blank" rel="noopener"><?= h($result['path']) ?></a>
            <?php else: ?>
                <b style="color:#dc2626">✗ Tải thất bại</b> (<?= $result['ms'] ?>ms)<br>
                Lý do: <code><?= h($result['err'] ?: 'không rõ') ?></code>
                <div class="rc-muted" style="margin-top:6px">Nếu lỗi liên quan timeout/connect/resolve → server live đang <b>chặn outbound</b> tới Base CDN (cần mở firewall/whitelist domain <code>basecdn.net</code>). Nếu "ghi được = KHÔNG" → cấp quyền ghi cho thư mục <code>uploads/</code>.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php
hrm_footer();
