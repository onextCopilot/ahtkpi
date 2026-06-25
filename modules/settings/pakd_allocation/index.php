<?php
/**
 * Settings: Tỷ lệ phân bổ giao khoán theo doanh thu thuần của dự án.
 * Ma trận: loại dự án × nhóm phân bổ (Sales&MKT, BO+Management, BO Chi nhánh,
 * Delivery max, Gross Profit min). Lưu JSON trong system_settings.
 * Trang chi tiết PAKD đọc dữ liệu này để auto-fill % chi phí.
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/app_settings.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: /'); exit(); }

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// ── Định nghĩa loại dự án & nhóm phân bổ (cố định cấu trúc, giá trị admin sửa) ──
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

// ── Giá trị seed (đọc từ bảng phân bổ; admin nên kiểm tra lại) ──
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

// ── Load dữ liệu đã lưu, fallback về seed ──
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

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matrix = [];
    foreach ($PROJECT_TYPES as $ptKey => $_) {
        foreach ($cat_keys as $cKey) {
            $val = $_POST['rate'][$ptKey][$cKey] ?? '0';
            $matrix[$ptKey][$cKey] = max(0, min(100, (float)$val));
        }
    }
    app_setting_set($conn, 'pakd_allocation_rates', json_encode($matrix, JSON_UNESCAPED_UNICODE), (int)$_SESSION['user_id']);
    $saved = true;
}

$rates = alloc_load($conn, $PROJECT_TYPES, $cat_keys, $SEED);

// Persist seed lần đầu nếu chưa có dữ liệu (để trang chi tiết PAKD đọc được ngay).
if (!$saved && app_setting_get($conn, 'pakd_allocation_rates', '') === '') {
    app_setting_set($conn, 'pakd_allocation_rates', json_encode($rates, JSON_UNESCAPED_UNICODE), (int)$_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tỷ lệ phân bổ giao khoán - AHT KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .al-wrap { padding: 1.5rem; }
        .al-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .al-title h1 { font-size:20px; font-weight:700; color:#0f172a; margin:0 0 4px; }
        .al-title p { font-size:13px; color:#64748b; margin:0; max-width:680px; }
        .al-ok { background:#dcfce7; color:#166534; border:1px solid #86efac; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-weight:600; }
        .al-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
        .al-table-wrap { overflow-x:auto; }
        table.al-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .al-table th, .al-table td { padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:center; }
        .al-table thead th { background:#0f172a; color:#fff; font-size:11px; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; position:sticky; top:0; }
        .al-table td.pt-name { text-align:left; font-weight:600; color:#334155; min-width:260px; }
        .al-input { width:64px; padding:7px 8px; border:1px solid #cbd5e1; border-radius:6px; text-align:right; font-size:13px; outline:none; font-family:inherit; }
        .al-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
        .al-suffix { color:#94a3b8; font-size:12px; margin-left:2px; }
        .al-table tbody tr:nth-child(even) { background:#f8fafc; }
        .al-btn { margin-top:18px; background:#4f46e5; color:#fff; border:none; padding:11px 22px; border-radius:8px; font-weight:600; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:8px; }
        .al-btn:hover { background:#4338ca; }
        .al-note { font-size:12px; color:#b45309; background:#fef3c7; border:1px solid #fde68a; padding:10px 14px; border-radius:8px; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php $page_title = 'Tỷ lệ phân bổ giao khoán'; include __DIR__ . '/../../includes/topbar.php'; ?>
        <div class="al-wrap">
            <?php if ($saved): ?><div class="al-ok"><i class="fas fa-check-circle"></i> Đã lưu tỷ lệ phân bổ.</div><?php endif; ?>
            <div class="al-head">
                <div class="al-title">
                    <a href="/projects/settings" style="display:inline-flex;align-items:center;gap:7px;font-size:12px;color:#64748b;text-decoration:none;margin-bottom:8px;"><i class="fas fa-arrow-left"></i> Project Settings</a>
                    <h1><i class="fas fa-percent" style="color:#6366f1;"></i> Tỷ lệ phân bổ giao khoán theo doanh thu thuần</h1>
                    <p>Tỷ lệ % phân bổ cho từng loại dự án. Trang chi tiết Phương án Kinh doanh dùng các tỷ lệ này để tự điền chi phí Sales&amp;MKT (mục 4.2) và BO+Management (mục 4.3).</p>
                </div>
            </div>
            <div class="al-note"><i class="fas fa-triangle-exclamation"></i> Giá trị khởi tạo được nhập theo bảng phân bổ tham khảo — vui lòng kiểm tra & điều chỉnh cho chính xác trước khi áp dụng.</div>

            <form method="POST">
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
                                    <td class="pt-name"><?= h($ptLabel) ?></td>
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
        </div>
    </main>
</div>
</body>
</html>
