<?php
/**
 * Content partial: Tỷ lệ phân bổ giao khoán (tab trong Project Settings).
 * Được include bởi modules/projects/settings_index.php — KHÔNG tự render sidebar/topbar.
 * Yêu cầu sẵn có: $conn, app_setting_get/set(), h().
 */
if (!isset($_SESSION['user_id'])) { return; }

$PROJECT_TYPES = [
    'ito_global'          => 'Dự án từ ITO Global',
    'ito_vn_inhouse'      => 'Dự án từ ITO Việt Nam (dedicated/project base) in-house',
    'headcount_onsite_vn' => 'Dự án thuê Headcounts và Onsite tại Việt Nam',
    'consulting_hn'       => 'Consulting ERP, eCom… tại Hà Nội / CSM tự sales HN',
    'consulting_hcm'      => 'Consulting ERP, eCom… BC7 HCM / CSM tự sales HCM',
    'bc_onext_phutho'     => 'Dự án cho BC Onext Phú Thọ',
    'consulting_malaysia' => 'Consulting ERP, eCom… tại Malaysia',
    'ito_japan'           => 'Dự án ITO Japan',
    'csm_delivery_hn'     => 'CSM do Delivery chuyển xuống — CSM Hà Nội',
    'csm_delivery_hcm'    => 'CSM do Delivery chuyển xuống — CSM HCM',
    'trading_license'     => 'Dự án trading về license (Odoo, Salesforce…)',
    'thue_vendors'        => 'Thuê Vendors',
];
$CATEGORIES = [
    'sales_mkt'        => 'Sales & MKT',
    'bo_management'    => 'BO + Management',
    'bo_branch'        => 'BO Chi nhánh',
    'delivery_max'     => 'Delivery (Max)',
    'gross_profit_min' => 'Gross Profit (min)',
];
$SEED = [
    //                       sm  bo  br  dev gp
    'ito_global'          => [15, 15, 0,  55, 15],
    'ito_vn_inhouse'      => [10, 15, 0,  60, 15],
    'headcount_onsite_vn' => [10, 5,  0,  65, 20],
    'consulting_hn'       => [15, 15, 0,  55, 15],
    'consulting_hcm'      => [15, 5,  5,  60, 15],
    'bc_onext_phutho'     => [10, 5,  5,  60, 20],
    'consulting_malaysia' => [15, 15, 0,  55, 15],
    'ito_japan'           => [15, 15, 0,  60, 15],
    'csm_delivery_hn'     => [0,  15, 0,  60, 25],
    'csm_delivery_hcm'    => [0,  10, 5,  60, 25],
    'trading_license'     => [15, 15, 0,  65, 70],
    'thue_vendors'        => [15, 5,  0,  0,  15],
];
$cat_keys = array_keys($CATEGORIES);

if (!function_exists('alloc_load')) {
    function alloc_load(mysqli $conn, array $pt, array $catKeys, array $seed): array {
        $raw = app_setting_get($conn, 'pakd_allocation_rates', '');
        $data = $raw ? (json_decode($raw, true) ?: []) : [];
        $out = [];
        foreach ($pt as $ptKey => $_) {
            foreach ($catKeys as $i => $cKey) {
                $out[$ptKey][$cKey] = isset($data[$ptKey][$cKey])
                    ? (float)$data[$ptKey][$cKey]
                    : (float)($seed[$ptKey][$i] ?? 0);
            }
        }
        return $out;
    }
}

// Load nhãn loại dự án (admin có thể sửa) — fallback về nhãn mặc định.
function alloc_labels(mysqli $conn, array $defaults): array {
    $raw = app_setting_get($conn, 'pakd_allocation_labels', '');
    $data = $raw ? (json_decode($raw, true) ?: []) : [];
    $out = [];
    foreach ($defaults as $k => $def) {
        $out[$k] = (isset($data[$k]) && trim($data[$k]) !== '') ? $data[$k] : $def;
    }
    return $out;
}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate'])) {
    $matrix = [];
    $labels = [];
    foreach ($PROJECT_TYPES as $ptKey => $defLabel) {
        foreach ($cat_keys as $cKey) {
            $val = $_POST['rate'][$ptKey][$cKey] ?? '0';
            $matrix[$ptKey][$cKey] = max(0, min(100, (float)$val));
        }
        $lbl = trim($_POST['label'][$ptKey] ?? '');
        $labels[$ptKey] = ($lbl !== '') ? mb_substr($lbl, 0, 200) : $defLabel;
    }
    app_setting_set($conn, 'pakd_allocation_rates', json_encode($matrix, JSON_UNESCAPED_UNICODE), (int)$_SESSION['user_id']);
    app_setting_set($conn, 'pakd_allocation_labels', json_encode($labels, JSON_UNESCAPED_UNICODE), (int)$_SESSION['user_id']);
    $saved = true;
}

$rates = alloc_load($conn, $PROJECT_TYPES, $cat_keys, $SEED);
$labels = alloc_labels($conn, $PROJECT_TYPES);
if (!$saved && app_setting_get($conn, 'pakd_allocation_rates', '') === '') {
    app_setting_set($conn, 'pakd_allocation_rates', json_encode($rates, JSON_UNESCAPED_UNICODE), (int)$_SESSION['user_id']);
}
?>
<div class="ps-content-head">
    <h2><i class="fas fa-percent" style="color:#6366f1;"></i> Tỷ lệ phân bổ giao khoán theo doanh thu thuần</h2>
    <p>Tỷ lệ % phân bổ cho từng loại dự án. Trang chi tiết Phương án Kinh doanh dùng các tỷ lệ này để tự điền chi phí Sales&amp;MKT (mục 4.2) và BO+Management (mục 4.3).</p>
</div>

<?php if ($saved): ?><div class="al-ok"><i class="fas fa-check-circle"></i> Đã lưu tỷ lệ phân bổ.</div><?php endif; ?>
<div class="al-note"><i class="fas fa-triangle-exclamation"></i> Giá trị khởi tạo nhập theo bảng phân bổ tham khảo — vui lòng kiểm tra &amp; điều chỉnh trước khi áp dụng.</div>

<form method="POST" action="/projects/settings/allocation">
    <div class="al-card">
        <div class="al-table-wrap">
            <table class="al-table">
                <thead>
                    <tr>
                        <th class="pt-name" style="text-align:left;">Loại dự án</th>
                        <?php foreach ($CATEGORIES as $cLabel): ?>
                        <th><?= h($cLabel) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($PROJECT_TYPES as $ptKey => $ptLabel): ?>
                    <tr>
                        <td class="pt-name">
                            <input type="text" class="al-name-input" name="label[<?= h($ptKey) ?>]"
                                value="<?= h($labels[$ptKey] ?? $ptLabel) ?>" placeholder="Tên loại dự án...">
                        </td>
                        <?php foreach ($cat_keys as $cKey): ?>
                        <td>
                            <input type="number" class="al-input" min="0" max="100" step="0.1"
                                name="rate[<?= h($ptKey) ?>][<?= h($cKey) ?>]"
                                value="<?= rtrim(rtrim(number_format($rates[$ptKey][$cKey], 2, '.', ''), '0'), '.') ?>"><span class="al-suffix">%</span>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <button type="submit" class="al-btn"><i class="fas fa-save"></i> Lưu tỷ lệ phân bổ</button>
</form>
