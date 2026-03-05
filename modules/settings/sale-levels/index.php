<?php
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$error_msg = $success_msg = '';

// ── AUTO MIGRATE TABLE ───────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS sale_levels (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    position_type       VARCHAR(100) NOT NULL DEFAULT 'BDE/BCE',
    level_name          VARCHAR(255) NOT NULL,
    fixed_monthly       BIGINT        DEFAULT 0,
    total_salary_yearly BIGINT        DEFAULT 0,
    kpi_yearly_vnd      BIGINT        DEFAULT 0,
    kpi_quarter_vnd     BIGINT        DEFAULT 0,
    kpi_yearly_usd      DECIMAL(15,2) DEFAULT 0,
    kpi_quarter_usd     DECIMAL(15,2) DEFAULT 0,
    notes               TEXT,
    color_badge         VARCHAR(50)   DEFAULT '#1a73e8',
    order_num           INT           DEFAULT 0,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
)");

// Auto-add missing columns (for live migration)
$needed_cols = [
    'position_type' => "VARCHAR(100) NOT NULL DEFAULT 'BDE/BCE'",
    'fixed_monthly' => "BIGINT DEFAULT 0",
    'total_salary_yearly' => "BIGINT DEFAULT 0",
    'kpi_yearly_vnd' => "BIGINT DEFAULT 0",
    'kpi_quarter_vnd' => "BIGINT DEFAULT 0",
    'kpi_yearly_usd' => "DECIMAL(15,2) DEFAULT 0",
    'kpi_quarter_usd' => "DECIMAL(15,2) DEFAULT 0",
    'notes' => "TEXT",
];
foreach ($needed_cols as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM sale_levels LIKE '$col'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE sale_levels ADD COLUMN `$col` $def");
    }
}

// ── MIGRATE NOTES for ALL levels by position_type ────────────────────────────
$note_bde = "1. BD đảm bảo tỷ lệ doanh thu từ khách hàng mới (new accounts) chiếm tỷ trọng > 70%\n2. Nếu tỷ lệ tổng doanh thu từ khách hàng cũ > 70% tổng doanh thu và tổng doanh thu từ khách hàng cũ >= KPI AM/CSM Level 1 thì BD sẽ được move sang vị trí là AM/CSM\n3. Tổng kết 2 quý 1 lần, nếu BD không đạt 80% KPI tại Level của mình thì BD sẽ bị giảm level về Level tương ứng với KPI đã đạt trong 2 quý trước. Nếu BD đạt KPI của 2 quý vượt KPI của Level cao hơn thì BD sẽ được cân nhắc và đánh giá để tăng lên level cao hơn và KPI cao hơn tương ứng.";

$note_am = "1. AM/CSM đảm bảo tỷ lệ doanh thu từ khách hàng cũ (Existed Accounts) chiếm tỷ trọng > 50% tổng doanh thu\n2. AM/CSM vẫn có nhiệm vụ tìm kiếm thêm khách hàng mới thông qua các mối quan hệ khách hàng cũ/được gắn thêm khách mới/tự tìm thêm các khách mới\n3. Tổng kết 2 quý 1 lần, nếu AM/CSM không đạt 80% KPI tại Level của mình thì AM/CSM sẽ bị giảm level về Level tương ứng với KPI đã đạt trong 2 quý trước. Nếu AM/CSM đạt KPI của 2 quý vượt KPI của Level cao hơn thì AM/CSM sẽ được cân nhắc và đánh giá để tăng lên level cao hơn và KPI cao hơn tương ứng.";

$note_ss = "1. Sales Support KPI được tính theo KPI tổng doanh thu các dự án/PO mà Sales support tham gia hỗ trợ các BD/AM chính khác\n2. Sales Support là vị trí kiêm nhiệm thêm của các BD/AM\n3. KPI của Sales support được đánh giá 2 quý / 1 lần. --> Lương của add on sales support cũng sẽ thay đổi khi KPI được update.";

$note_so = "1. Sales Operation KPI được tính theo KPI tổng của các Team BD/AM hoặc tổng KPI của các BD/AM gộp lại\n2. Sales Operation có thể được bổ nhiệm làm BD/AM/CSM, nếu Sales Operation mong muốn thay đổi vị trí và được BD leader phê duyệt, hoặc do BD leader yêu cầu và Sales Operation chấp nhận\n3. Sales Operation được tăng level theo kỳ Performance của công ty và tính trên tổng KPI 2 quý của tổng các BD/AM mà mình support gộp lại\n4. Sales Operation sẽ được thưởng 10% * Lương tháng, nếu tổng kết KPI quý của các team mình phụ trách đạt >=100% KPI";

$note_migrations = [
    "BDE/BCE"                     => $note_bde,
    "AM/CSM"                      => $note_am,
    "Sales Support"               => $note_ss,
    "Sales Operation"             => $note_so,
];
foreach ($note_migrations as $pos => $note) {
    $upd = $conn->prepare("UPDATE sale_levels SET notes=? WHERE position_type=? AND (notes IS NULL OR notes='' OR notes LIKE '%&#10;%')");
    if ($upd) { $upd->bind_param("ss", $note, $pos); $upd->execute(); }
}

// ── SEED DEFAULT DATA if table is empty ──────────────────────────────────────
$cnt = $conn->query("SELECT COUNT(*) c FROM sale_levels")->fetch_assoc()['c'];
if ((int) $cnt === 0) {
    $seed = [
        // position_type, level_name, fixed_monthly, total_salary_yearly, kpi_yearly_vnd, kpi_quarter_vnd, kpi_yearly_usd, kpi_quarter_usd, notes, color, order
        ['BDE/BCE', 'BDE/BCE Level 1', 7000000, 91000000, 2600000000, 650000000, 101961, 25490, '', '#4285F4', 1],
        ['BDE/BCE', 'BDE/BCE Level 2', 9000000, 117000000, 3342857143, 835714286, 131092, 32773, '', '#4285F4', 2],
        ['BDE/BCE', 'BDE/BCE Level 3', 11000000, 143000000, 4085714286, 1021428571, 160224, 40056, '', '#4285F4', 3],
        ['BDE/BCE', 'BDE/BCE Level 4', 13000000, 169000000, 4828571429, 1207142857, 189356, 47339, '', '#4285F4', 4],
        ['BDE/BCE', 'BDE/BCE Level 5', 15000000, 195000000, 5571428571, 1392857143, 218487, 54622, '1. BD đảm bảo tỷ lệ doanh thu từ khách hàng mới (new accounts) chiếm tỷ trọng > 70%&#10;2. Nếu tỷ lệ tổng doanh thu từ khách hàng cũ > 70% và tổng doanh thu từ khách hàng cũ >= KPI AM/CSM Level 1 thì BD sẽ được move sang vị trí là AM/CSM&#10;3. Tổng kết 2 quý 1 lần, nếu BD không đạt 80% KPI tại Level của mình thì BD sẽ bị giảm level về Level tương ứng với KPI đã đạt trong 2 quý trước. Nếu BD đạt KPI của 2 quý vượt KPI của Level cao hơn thì BD sẽ được cân nhắc và đánh giá để tăng lên level cao hơn và KPI cao hơn tương ứng.', '#4285F4', 5],
        ['BDE/BCE', 'BDE/BCE Level 6', 18000000, 234000000, 6685714286, 1671428571, 262185, 65546, '', '#4285F4', 6],
        ['BDE/BCE', 'BDE/BCE Level 7', 21000000, 273000000, 7800000000, 1950000000, 305882, 76471, '', '#4285F4', 7],
        ['BDE/BCE', 'BDE/BCE Level 8', 25000000, 325000000, 9285714286, 2321428571, 364146, 91036, '', '#4285F4', 8],
        ['BDE/BCE', 'BDE/BCE Level 9', 30000000, 390000000, 11142857143, 2785714286, 436975, 109244, '', '#4285F4', 9],
        ['BDE/BCE', 'BDE/BCE Level 10', 35000000, 455000000, 13000000000, 3250000000, 509804, 127451, '', '#4285F4', 10],
        ['BDE/BCE', 'BDE/BCE Level 11', 40000000, 520000000, 14857142857, 3714285714, 582633, 145658, '', '#4285F4', 11],

        ['AM/CSM', 'AM/CSM Level 1', 16000000, 208000000, 8320000000, 2080000000, 326275, 81569, '1. AM/CSM đảm bảo tỷ lệ doanh thu từ khách hàng cũ (Existed Accounts) chiếm tỷ trọng > 50% tổng doanh thu&#10;2. AM/CSM vẫn có nhiệm vụ tìm kiếm thêm khách hàng mới thông qua các mối quan hệ khách hàng cũ/được gắn thêm khách mới/tự tìm thêm các khách mới&#10;3. Tổng kết 2 quý 1 lần, nếu AM/CSM không đạt 80% KPI tại Level của mình thì AM/CSM sẽ bị giảm level về Level tương ứng với KPI đã đạt trong 2 quý trước. Nếu AM/CSM đạt KPI của 2 quý vượt KPI của Level cao hơn thì AM/CSM sẽ được cân nhắc và đánh giá để tăng lên level cao hơn và KPI cao hơn tương ứng.', '#0F9D58', 1],
        ['AM/CSM', 'AM/CSM Level 2', 20000000, 260000000, 10400000000, 2600000000, 407843, 101961, '', '#0F9D58', 2],
        ['AM/CSM', 'AM/CSM Level 3', 25000000, 325000000, 13000000000, 3250000000, 509804, 127451, '', '#0F9D58', 3],
        ['AM/CSM', 'AM/CSM Level 4', 30000000, 390000000, 15600000000, 3900000000, 611765, 152941, '', '#0F9D58', 4],
        ['AM/CSM', 'AM/CSM Level 5', 35000000, 455000000, 18200000000, 4550000000, 713725, 178431, '', '#0F9D58', 5],
        ['AM/CSM', 'AM/CSM Level 6', 40000000, 520000000, 20800000000, 5200000000, 815686, 203922, '', '#0F9D58', 6],
        ['AM/CSM', 'AM/CSM Level 7', 45000000, 585000000, 23400000000, 5850000000, 917647, 229412, '', '#0F9D58', 7],
        ['AM/CSM', 'AM/CSM Level 8', 50000000, 650000000, 26000000000, 6500000000, 1019608, 254902, '', '#0F9D58', 8],

        ['Sales Support', 'Sales Support Level 1', 2000000, 26000000, 13000000000, 3250000000, 509804, 127451, '1. Sales Support KPI được tính theo KPI tổng doanh thu các dự án/PO mà Sales support tham gia hỗ trợ các BD/AM chính khác&#10;2. Sales Support là vị trí kiêm nhiệm thêm của các BD/AM&#10;3. KPI của Sales support được đánh giá 2 quý / 1 lần. --> Lương của add on sales support cũng sẽ thay đổi khi KPI được update.', '#F4B400', 1],
        ['Sales Support', 'Sales Support Level 2', 3000000, 39000000, 19500000000, 4875000000, 764706, 191176, '', '#F4B400', 2],
        ['Sales Support', 'Sales Support Level 3', 4000000, 52000000, 26000000000, 6500000000, 1019608, 254902, '', '#F4B400', 3],
        ['Sales Support', 'Sales Support Level 4', 5000000, 65000000, 32500000000, 8125000000, 1274510, 318627, '', '#F4B400', 4],
        ['Sales Support', 'Sales Support Level 5', 6000000, 78000000, 39000000000, 9750000000, 1529412, 382353, '', '#F4B400', 5],

        ['Sales Operation', 'Sales Operation Level 1', 9000000, 117000000, 39000000000, 9750000000, 1529412, 382353, '1. Sales Operation KPI được tính theo KPI tổng của các Team BD/AM hoặc tổng KPI của các BD/AM gộp lại&#10;2. Sales Operation có thể được bổ nhiệm làm BD/AM/CSM, nếu Sales Operation mong muốn thay đổi vị trí và được BD leader phê duyệt, hoặc do BD leader yêu cầu và Sales Operation chấp nhận&#10;3. Sales Operation được tăng level theo kỳ Performance của công ty và tính trên tổng KPI 2 quý của tổng các BD/AM mà mình support gộp lại&#10;4. Sales Operation sẽ được thưởng 10% * Lương tháng, nếu tổng kết KPI quý của các team mình phụ trách đạt >=100% KPI', '#DB4437', 1],
        ['Sales Operation', 'Sales Operation Level 2', 12000000, 156000000, 52000000000, 13000000000, 2039216, 509804, '', '#DB4437', 2],
        ['Sales Operation', 'Sales Operation Level 3', 15000000, 195000000, 65000000000, 16250000000, 2549020, 637255, '', '#DB4437', 3],
        ['Sales Operation', 'Sales Operation Level 4', 18000000, 234000000, 78000000000, 19500000000, 3058824, 764706, '', '#DB4437', 4],
        ['Sales Operation', 'Sales Operation Level 5', 21000000, 273000000, 91000000000, 22750000000, 3568627, 892157, '', '#DB4437', 5],

        ['Pre-sales/Senior Consultant', 'Pre-sales Level 1', 40000000, 520000000, 26000000000, 6500000000, 1019608, 254902, '', '#9C27B0', 1],
        ['Pre-sales/Senior Consultant', 'Pre-sales Level 2', 45000000, 585000000, 29250000000, 7312500000, 1147059, 286765, '', '#9C27B0', 2],
        ['Pre-sales/Senior Consultant', 'Pre-sales Level 3', 50000000, 650000000, 32500000000, 8125000000, 1274510, 318627, '', '#9C27B0', 3],
        ['Pre-sales/Senior Consultant', 'Pre-sales Level 4', 55000000, 715000000, 35750000000, 8937500000, 1401961, 350490, '', '#9C27B0', 4],
        ['Pre-sales/Senior Consultant', 'Pre-sales Level 5', 60000000, 780000000, 39000000000, 9750000000, 1529412, 382353, '', '#9C27B0', 5],
    ];
    $ins = $conn->prepare("INSERT INTO sale_levels (position_type,level_name,fixed_monthly,total_salary_yearly,kpi_yearly_vnd,kpi_quarter_vnd,kpi_yearly_usd,kpi_quarter_usd,notes,color_badge,order_num) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($seed as $r) {
        $ins->bind_param("ssiiiiiddsi", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $r[8], $r[9], $r[10]);
        $ins->execute();
    }
}

// ── HANDLE POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];
    if ($act === 'add' || $act === 'edit') {
        $level_name = trim($_POST['level_name'] ?? '');
        $position_type = trim($_POST['position_type'] ?? 'BDE/BCE');
        $fixed_monthly = intval($_POST['fixed_monthly'] ?? 0);
        $total_salary_yearly = intval($_POST['total_salary_yearly'] ?? 0);
        $kpi_yearly_vnd = intval($_POST['kpi_yearly_vnd'] ?? 0);
        $kpi_quarter_vnd = intval($_POST['kpi_quarter_vnd'] ?? 0);
        $kpi_yearly_usd = floatval($_POST['kpi_yearly_usd'] ?? 0);
        $kpi_quarter_usd = floatval($_POST['kpi_quarter_usd'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $color_badge = trim($_POST['color_badge'] ?? '#1a73e8');
        $order_num = intval($_POST['order_num'] ?? 0);

        if ($act === 'add') {
            $s = $conn->prepare("INSERT INTO sale_levels (position_type,level_name,fixed_monthly,total_salary_yearly,kpi_yearly_vnd,kpi_quarter_vnd,kpi_yearly_usd,kpi_quarter_usd,notes,color_badge,order_num) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param("ssiiiiiddsi", $position_type, $level_name, $fixed_monthly, $total_salary_yearly, $kpi_yearly_vnd, $kpi_quarter_vnd, $kpi_yearly_usd, $kpi_quarter_usd, $notes, $color_badge, $order_num);
            $s->execute() ? $success_msg = 'Thêm level thành công!' : $error_msg = $conn->error;
        } else {
            $id = intval($_POST['id'] ?? 0);
            $s = $conn->prepare("UPDATE sale_levels SET position_type=?,level_name=?,fixed_monthly=?,total_salary_yearly=?,kpi_yearly_vnd=?,kpi_quarter_vnd=?,kpi_yearly_usd=?,kpi_quarter_usd=?,notes=?,color_badge=?,order_num=? WHERE id=?");
            $s->bind_param("ssiiiiiddsii", $position_type, $level_name, $fixed_monthly, $total_salary_yearly, $kpi_yearly_vnd, $kpi_quarter_vnd, $kpi_yearly_usd, $kpi_quarter_usd, $notes, $color_badge, $order_num, $id);
            $s->execute() ? $success_msg = 'Cập nhật thành công!' : $error_msg = $conn->error;
        }
    } elseif ($act === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $s = $conn->prepare("DELETE FROM sale_levels WHERE id=?");
            $s->bind_param("i", $id);
            $s->execute() ? $success_msg = 'Đã xoá!' : $error_msg = $conn->error;
        }
    }
}

// ── FETCH DATA ───────────────────────────────────────────────────────────────
$levels = [];
$r = $conn->query("SELECT * FROM sale_levels ORDER BY position_type, order_num, id");
if ($r)
    while ($row = $r->fetch_assoc())
        $levels[] = $row;

// Group by position_type
$grouped = [];
foreach ($levels as $l)
    $grouped[$l['position_type']][] = $l;

// Position colors map
$POSITION_COLORS = [
    'BDE/BCE' => ['bg' => '#EFF6FF', 'head' => '#1D4ED8', 'badge' => '#3B82F6'],
    'AM/CSM' => ['bg' => '#F0FDF4', 'head' => '#065F46', 'badge' => '#10B981'],
    'Sales Support' => ['bg' => '#FFFBEB', 'head' => '#92400E', 'badge' => '#F59E0B'],
    'Sales Operation' => ['bg' => '#FEF2F2', 'head' => '#991B1B', 'badge' => '#EF4444'],
    'Pre-sales/Senior Consultant' => ['bg' => '#FAF5FF', 'head' => '#6B21A8', 'badge' => '#A855F7'],
];

function fmtVND($n)
{
    return number_format((float) $n, 0, ',', '.') . ' ₫';
}
function fmtUSD($n)
{
    return '$' . number_format((float) $n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sale Level Setup — Settings</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .content-wrapper {
            padding: 16px;
            height: calc(100vh - 64px);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            gap: 12px;
            overflow: hidden;
        }

        /* ── Toolbar ── */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            flex-shrink: 0;
        }

        .toolbar h2 {
            margin: 0;
            font-size: 15px;
            color: #111827;
            flex: 1;
        }

        .tbtn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
            border: 1px solid #D1D5DB;
            background: #fff;
            color: #374151;
            transition: all .15s;
        }

        .tbtn:hover {
            background: #F9FAFB;
        }

        .tbtn-blue {
            background: #1D4ED8;
            color: #fff;
            border-color: #1D4ED8;
        }

        .tbtn-blue:hover {
            background: #1e40af;
        }

        /* ── Sheet ── */
        .sheet-wrap {
            flex: 1;
            overflow: auto;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            background: #fff;
        }

        /* ── Position header row ── */
        .pos-header {
            padding: 8px 14px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: .5px;
            text-transform: uppercase;
            position: sticky;
            left: 0;
        }

        /* ── Table ── */
        table.sl-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            white-space: nowrap;
        }

        table.sl-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #F8FAFC;
            color: #374151;
            font-weight: 600;
            font-size: 11.5px;
            padding: 7px 10px;
            text-align: left;
            border-bottom: 2px solid #E5E7EB;
            border-right: 1px solid #E5E7EB;
        }

        table.sl-table tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #F0F0F0;
            border-right: 1px solid #F0F0F0;
            color: #111827;
        }

        table.sl-table tbody tr:hover td {
            background: #F8FAFC;
        }

        .col-num {
            width: 36px;
            text-align: center;
            color: #9CA3AF;
            font-weight: 600;
        }

        .col-act {
            width: 80px;
            text-align: center;
        }

        .num-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .pos-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
        }

        /* ── Notes cell ── */
        .notes-cell {
            max-width: 260px;
            white-space: normal;
            font-size: 11px;
            color: #6B7280;
            line-height: 1.4;
        }

        /* ── Modal ── */
        .modal-bg {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(0, 0, 0, .45);
            align-items: center;
            justify-content: center;
        }

        .modal-bg.show {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 10px;
            width: 680px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        }

        .modal-box h3 {
            margin: 0 0 20px;
            font-size: 17px;
            color: #111827;
        }

        .fg {
            margin-bottom: 14px;
        }

        .fg label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }

        .fg input,
        .fg select,
        .fg textarea {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 13px;
            box-sizing: border-box;
            color: #111827;
        }

        .fg textarea {
            resize: vertical;
            min-height: 80px;
        }

        .fg input:focus,
        .fg select:focus,
        .fg textarea:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 2px #BFDBFE;
        }

        .fgrow {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .fgrow3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Sale Level Setup';
            $page_subtitle = 'Quản lý KPI & lương theo cấp bậc';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <?php if ($success_msg): ?>
                    <div class="alert-success">✅ <?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert-error">❌ <?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

                <!-- Toolbar -->
                <div class="toolbar">
                    <h2>📊 Sale Level KPI Setup</h2>
                    <button class="tbtn tbtn-blue" onclick="openAdd()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Thêm Level
                    </button>
                </div>

                <!-- Table -->
                <div class="sheet-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th class="col-num">#</th>
                                <th style="min-width:180px">Tên Level</th>
                                <th style="min-width:130px" class="num-cell">Khung lương (tháng)</th>
                                <th style="min-width:140px" class="num-cell">Total Salary (năm)</th>
                                <th style="min-width:150px" class="num-cell">KPI Năm (VND)</th>
                                <th style="min-width:150px" class="num-cell">KPI Quý (VND)</th>
                                <th style="min-width:120px" class="num-cell">KPI Năm (USD)</th>
                                <th style="min-width:120px" class="num-cell">KPI Quý (USD)</th>
                                <th style="min-width:280px">Ghi chú</th>
                                <th class="col-act">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $posOrder = ['BDE/BCE', 'AM/CSM', 'Sales Support', 'Sales Operation', 'Pre-sales/Senior Consultant'];
                            $stt = 0;
                            // Merge both posOrder + any extra groups
                            $allGroups = [];
                            foreach ($posOrder as $p) { if (!empty($grouped[$p])) $allGroups[$p] = $grouped[$p]; }
                            foreach ($grouped as $p => $rows) { if (!in_array($p, $posOrder)) $allGroups[$p] = $rows; }

                            foreach ($allGroups as $pos => $rows):
                                $pc = $POSITION_COLORS[$pos] ?? ['bg'=>'#F9FAFB','head'=>'#374151','badge'=>'#6B7280'];
                                $groupCount = count($rows);
                                $groupNote  = $rows[0]['notes'] ?? ''; // same note for group
                            ?>
                            <!-- Group header -->
                            <tr>
                                <td colspan="10" style="background:<?= $pc['bg'] ?>;padding:5px 12px;">
                                    <span class="pos-badge" style="background:<?= $pc['badge'] ?>"><?= htmlspecialchars($pos) ?></span>
                                </td>
                            </tr>
                            <?php foreach ($rows as $idx => $l): $stt++; $isFirst = ($idx === 0); ?>
                            <tr>
                                <td class="col-num"><?= $stt ?></td>
                                <td style="font-weight:600"><?= htmlspecialchars($l['level_name']) ?></td>
                                <td class="num-cell"><?= fmtVND($l['fixed_monthly']) ?></td>
                                <td class="num-cell"><?= fmtVND($l['total_salary_yearly']) ?></td>
                                <td class="num-cell" style="color:#1D4ED8;font-weight:600"><?= fmtVND($l['kpi_yearly_vnd']) ?></td>
                                <td class="num-cell"><?= fmtVND($l['kpi_quarter_vnd']) ?></td>
                                <td class="num-cell" style="color:#065F46;font-weight:600"><?= fmtUSD($l['kpi_yearly_usd']) ?></td>
                                <td class="num-cell"><?= fmtUSD($l['kpi_quarter_usd']) ?></td>
                                <?php if ($isFirst && $groupNote): ?>
                                <td class="notes-cell" rowspan="<?= $groupCount ?>"
                                    style="vertical-align:top;border-left:3px solid <?= $pc['badge'] ?>;background:<?= $pc['bg'] ?>">
                                    <?= nl2br(htmlspecialchars($groupNote)) ?>
                                </td>
                                <?php elseif ($isFirst): ?>
                                <td class="notes-cell" rowspan="<?= $groupCount ?>">—</td>
                                <?php endif; ?>
                                <td class="col-act">
                                    <div style="display:flex;justify-content:center;gap:6px">
                                        <button onclick='openEdit(<?= json_encode($l) ?>)' style="border:none;background:none;cursor:pointer;color:#3B82F6" title="Sửa">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Xác nhận xoá?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                            <button type="submit" style="border:none;background:none;cursor:pointer;color:#EF4444" title="Xoá">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (empty($levels)): ?>
                                <tr><td colspan="11" style="text-align:center;padding:40px;color:#9CA3AF">Chưa có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="slModal" class="modal-bg">
        <div class="modal-box">
            <h3 id="modalTitle">Thêm Sale Level</h3>
            <form method="POST" id="slForm">
                <input type="hidden" name="action" id="slAction" value="add">
                <input type="hidden" name="id" id="slId">

                <div class="fgrow">
                    <div class="fg">
                        <label>Vị trí *</label>
                        <select name="position_type" id="slPositionType">
                            <option value="BDE/BCE">BDE/BCE</option>
                            <option value="AM/CSM">AM/CSM</option>
                            <option value="Sales Support">Sales Support</option>
                            <option value="Sales Operation">Sales Operation</option>
                            <option value="Pre-sales/Senior Consultant">Pre-sales/Senior Consultant</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Tên Level *</label>
                        <input type="text" name="level_name" id="slName" required placeholder="e.g. BDE/BCE Level 1">
                    </div>
                </div>

                <div class="fgrow">
                    <div class="fg">
                        <label>Khung lương (Fixed monthly – VND)</label>
                        <input type="number" name="fixed_monthly" id="slFixed" value="0" placeholder="7000000">
                    </div>
                    <div class="fg">
                        <label>Total Salary (Yearly Fixed Cost – VND)</label>
                        <input type="number" name="total_salary_yearly" id="slTotalYearly" value="0"
                            placeholder="91000000">
                    </div>
                </div>

                <div class="fgrow">
                    <div class="fg">
                        <label>KPI Năm (VND)</label>
                        <input type="number" name="kpi_yearly_vnd" id="slKpiYrVND" value="0" placeholder="2600000000">
                    </div>
                    <div class="fg">
                        <label>KPI Quý (VND)</label>
                        <input type="number" name="kpi_quarter_vnd" id="slKpiQtVND" value="0" placeholder="650000000">
                    </div>
                </div>

                <div class="fgrow">
                    <div class="fg">
                        <label>KPI Năm (USD)</label>
                        <input type="number" step="0.01" name="kpi_yearly_usd" id="slKpiYrUSD" value="0"
                            placeholder="101961">
                    </div>
                    <div class="fg">
                        <label>KPI Quý (USD)</label>
                        <input type="number" step="0.01" name="kpi_quarter_usd" id="slKpiQtUSD" value="0"
                            placeholder="25490">
                    </div>
                </div>

                <div class="fg">
                    <label>Ghi chú</label>
                    <textarea name="notes" id="slNotes" rows="3" placeholder="Mô tả quy định, điều kiện..."></textarea>
                </div>

                <div class="fgrow">
                    <div class="fg">
                        <label>Badge Color</label>
                        <input type="color" name="color_badge" id="slColor" value="#1D4ED8"
                            style="height:36px;padding:2px">
                    </div>
                    <div class="fg">
                        <label>Thứ tự hiển thị</label>
                        <input type="number" name="order_num" id="slOrder" value="0">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="tbtn" onclick="closeModal()">Huỷ</button>
                    <button type="submit" class="tbtn tbtn-blue">💾 Lưu</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('slModal');

        function openAdd() {
            document.getElementById('modalTitle').textContent = '➕ Thêm Sale Level';
            document.getElementById('slAction').value = 'add';
            document.getElementById('slId').value = '';
            document.getElementById('slForm').reset();
            modal.classList.add('show');
        }

        function openEdit(d) {
            document.getElementById('modalTitle').textContent = '✏️ Sửa Sale Level';
            document.getElementById('slAction').value = 'edit';
            document.getElementById('slId').value = d.id;
            document.getElementById('slPositionType').value = d.position_type || 'BDE/BCE';
            document.getElementById('slName').value = d.level_name || '';
            document.getElementById('slFixed').value = d.fixed_monthly || 0;
            document.getElementById('slTotalYearly').value = d.total_salary_yearly || 0;
            document.getElementById('slKpiYrVND').value = d.kpi_yearly_vnd || 0;
            document.getElementById('slKpiQtVND').value = d.kpi_quarter_vnd || 0;
            document.getElementById('slKpiYrUSD').value = d.kpi_yearly_usd || 0;
            document.getElementById('slKpiQtUSD').value = d.kpi_quarter_usd || 0;
            document.getElementById('slNotes').value = d.notes || '';
            document.getElementById('slColor').value = d.color_badge || '#1D4ED8';
            document.getElementById('slOrder').value = d.order_num || 0;
            modal.classList.add('show');
        }

        function closeModal() { modal.classList.remove('show'); }
        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    </script>
</body>

</html>