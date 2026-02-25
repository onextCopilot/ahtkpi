<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

require_once __DIR__ . '/migrate.php';

$tab = $_GET['tab'] ?? 'definitions';
$year = intval($_GET['year'] ?? date('Y'));
$msg_ok = '';
$msg_err = '';

// Retrieve flash messages from PRG redirect
if (isset($_SESSION['flash_msg_ok'])) {
    $msg_ok = $_SESSION['flash_msg_ok'];
    unset($_SESSION['flash_msg_ok']);
}
if (isset($_SESSION['flash_msg_err'])) {
    $msg_err = $_SESSION['flash_msg_err'];
    unset($_SESSION['flash_msg_err']);
}

// ── Lookups ───────────────────────────────────────
$departments = [];
$r = $conn->query("SELECT id, name FROM departments ORDER BY sort_order ASC, id ASC");
if ($r)
    while ($row = $r->fetch_assoc())
        $departments[] = $row;

$users_list = [];
$r = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
if ($r)
    while ($row = $r->fetch_assoc())
        $users_list[] = $row;

$years_list = [];
$r = $conn->query("SELECT DISTINCT year FROM kpi_definitions ORDER BY year DESC");
if ($r)
    while ($row = $r->fetch_assoc())
        $years_list[] = $row['year'];
if (!in_array($year, $years_list))
    $years_list[] = $year;
if (!in_array(date('Y'), $years_list))
    $years_list[] = intval(date('Y'));
rsort($years_list);

$kpi_groups = ['Business', 'Business / Finance', 'Technology', 'People & Culture', 'Operations', 'Customer', 'Other'];
$r_grp = $conn->query("SELECT DISTINCT kpi_group FROM kpi_templates WHERE kpi_group IS NOT NULL AND kpi_group != '' UNION SELECT DISTINCT kpi_group FROM kpi_definitions WHERE kpi_group IS NOT NULL AND kpi_group != ''");
if ($r_grp) {
    while ($row = $r_grp->fetch_assoc()) {
        if (!in_array($row['kpi_group'], $kpi_groups)) {
            $kpi_groups[] = $row['kpi_group'];
        }
    }
}

// ── POST handler ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- DEFINITIONS ---
    if ($action === 'add_def' || $action === 'edit_def') {
        $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        if ($_SESSION['role'] !== 'admin') {
            $dept_id = $_SESSION['department_id'] ?? null;
        }
        $yr = intval($_POST['year'] ?? $year);
        $group = trim($_POST['kpi_group'] ?? '');
        $name = trim($_POST['kpi_name'] ?? '');
        $target = trim($_POST['target_base'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);
        $owner_id = !empty($_POST['kpi_owner_id']) ? intval($_POST['kpi_owner_id']) : null;
        $is_cond = isset($_POST['is_condition']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            $msg_err = "Tên KPI không được để trống.";
        } else {
            if ($action === 'add_def') {
                $stmt = $conn->prepare("INSERT INTO kpi_definitions (year,department_id,kpi_group,kpi_name,target_base,weight,kpi_owner_id,is_condition,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                // i=year, i=dept, s=group, s=name, s=target, d=weight, i=owner, i=is_cond, s=notes, i=created_by
                $stmt->bind_param("iisssdisis", $yr, $dept_id, $group, $name, $target, $weight, $owner_id, $is_cond, $notes, $_SESSION['user_id']);
                $stmt->execute() ? $msg_ok = "Đã lưu KPI!" : $msg_err = $conn->error;
            } else {
                $id = intval($_POST['id']);
                // Auth check
                $chk = $conn->query("SELECT kpi_owner_id FROM kpi_definitions WHERE id = $id");
                $curr_owner = $chk && $chk->num_rows > 0 ? $chk->fetch_assoc()['kpi_owner_id'] : null;
                if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $curr_owner) {
                    $msg_err = "Bạn không có quyền sửa KPI này.";
                } else {
                    $stmt = $conn->prepare("UPDATE kpi_definitions SET year=?,department_id=?,kpi_group=?,kpi_name=?,target_base=?,weight=?,kpi_owner_id=?,is_condition=?,notes=? WHERE id=?");
                    // i=year, i=dept, s=group, s=name, s=target, d=weight, i=owner, i=is_cond, s=notes, i=id
                    $stmt->bind_param("iisssdiisi", $yr, $dept_id, $group, $name, $target, $weight, $owner_id, $is_cond, $notes, $id);
                    $stmt->execute() ? $msg_ok = "Đã lưu KPI!" : $msg_err = $conn->error;
                }
            }
        }
    }
    if ($action === 'del_def') {
        $id = intval($_POST['id']);
        // Auth check
        $chk = $conn->query("SELECT kpi_owner_id FROM kpi_definitions WHERE id = $id");
        $curr_owner = $chk && $chk->num_rows > 0 ? $chk->fetch_assoc()['kpi_owner_id'] : null;
        if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $curr_owner) {
            $msg_err = "Bạn không có quyền xoá KPI này.";
        } else {
            $stmt = $conn->prepare("DELETE FROM kpi_definitions WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute() ? $msg_ok = "Đã xoá!" : $msg_err = $conn->error;
        }
    }

    // --- QUARTERLY ---
    if ($action === 'save_quarterly') {
        $def_id = intval($_POST['kpi_def_id']);
        $quarter = intval($_POST['quarter']);
        $yr = intval($_POST['year'] ?? $year);
        $target = trim($_POST['target_value'] ?? '');
        $wq = floatval($_POST['weight_q'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        $notes = trim($_POST['notes'] ?? '');
        $stmt = $conn->prepare("INSERT INTO kpi_quarterly (kpi_def_id,quarter,year,target_value,weight_q,status,notes) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_value=VALUES(target_value),weight_q=VALUES(weight_q),status=VALUES(status),notes=VALUES(notes)");
        $stmt->bind_param("iiisdss", $def_id, $quarter, $yr, $target, $wq, $status, $notes);
        $stmt->execute() ? $msg_ok = "Đã lưu kế hoạch quý!" : $msg_err = $conn->error;
    }

    // --- MONTHLY ---
    if ($action === 'save_monthly') {
        $def_id = intval($_POST['kpi_def_id']);
        $yr = intval($_POST['year'] ?? $year);
        $month = intval($_POST['month']);
        $actual = trim($_POST['actual_value'] ?? '');
        $score = $_POST['score'] !== '' ? floatval($_POST['score']) : null;
        $notes = trim($_POST['notes'] ?? '');
        $uid = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO kpi_monthly (kpi_def_id,year,month,actual_value,score,notes,updated_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE actual_value=VALUES(actual_value),score=VALUES(score),notes=VALUES(notes),updated_by=VALUES(updated_by)");
        $stmt->bind_param("iiisdsi", $def_id, $yr, $month, $actual, $score, $notes, $uid);
        $stmt->execute() ? $msg_ok = "Đã lưu số liệu!" : $msg_err = $conn->error;
    }

    // --- KPI TEMPLATES (settings) ---
    if ($action === 'add_tpl') {
        $tname = trim($_POST['tpl_name'] ?? '');
        $tgroup = trim($_POST['tpl_group'] ?? '');
        if ($tname) {
            $stmt = $conn->prepare("INSERT INTO kpi_templates (name,kpi_group,sort_order) VALUES (?,?,(SELECT IFNULL(MAX(sort_order),0)+1 FROM kpi_templates t2))");
            $stmt->bind_param("ss", $tname, $tgroup);
            $stmt->execute() ? $msg_ok = "Đã thêm template!" : $msg_err = $conn->error;
        }
    }
    if ($action === 'edit_tpl') {
        $tid = intval($_POST['id']);
        $tname = trim($_POST['tpl_name'] ?? '');
        $tgroup = trim($_POST['tpl_group'] ?? '');
        if ($tname) {
            $stmt = $conn->prepare("UPDATE kpi_templates SET name=?,kpi_group=? WHERE id=?");
            $stmt->bind_param("ssi", $tname, $tgroup, $tid);
            $stmt->execute() ? $msg_ok = "Đã cập nhật!" : $msg_err = $conn->error;
        }
    }
    if ($action === 'del_tpl') {
        $tid = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM kpi_templates WHERE id=?");
        $stmt->bind_param("i", $tid);
        $stmt->execute() ? $msg_ok = "Đã xoá template!" : $msg_err = $conn->error;
    }

    // PRG Pattern
    if ($msg_ok)
        $_SESSION['flash_msg_ok'] = $msg_ok;
    if ($msg_err)
        $_SESSION['flash_msg_err'] = $msg_err;
    $redirect = "?tab=" . urlencode($tab) . "&year=" . urlencode($year);
    if (isset($_GET['dept']))
        $redirect .= "&dept=" . urlencode($_GET['dept']);
    header("Location: " . $redirect);
    exit();
}

// ── Fetch KPI definitions for selected year ───────
$defs = [];
$filter_dept = isset($_GET['dept']) ? intval($_GET['dept']) : (!empty($departments) ? $departments[0]['id'] : 0);
$where = "WHERE k.year = $year" . ($filter_dept ? " AND k.department_id = $filter_dept" : "");
$r = $conn->query("SELECT k.*, d.name dept_name, u.full_name owner_name, u.avatar owner_avatar
    FROM kpi_definitions k
    LEFT JOIN departments d ON k.department_id=d.id
    LEFT JOIN users u ON k.kpi_owner_id=u.id
    $where ORDER BY k.kpi_group, k.id");
if ($r)
    while ($row = $r->fetch_assoc())
        $defs[] = $row;

// ── Fetch KPI templates (settings) ───────────────────────
$kpi_templates = [];
$r = $conn->query("SELECT * FROM kpi_templates ORDER BY kpi_group, sort_order, name");
if ($r)
    while ($row = $r->fetch_assoc())
        $kpi_templates[] = $row;

// ── Fetch quarterly for year ──────────────────────
$quarterly_map = []; // [def_id][quarter] => row
if ($tab === 'quarterly') {
    $r = $conn->query("SELECT * FROM kpi_quarterly WHERE year=$year");
    if ($r)
        while ($row = $r->fetch_assoc())
            $quarterly_map[$row['kpi_def_id']][$row['quarter']] = $row;
}

// Fetch monthly actuals for monthly + quarterly + definitions tabs
$monthly_map = []; // [def_id][month] => row
$r = $conn->query("SELECT * FROM kpi_monthly WHERE year=$year");
if ($r)
    while ($row = $r->fetch_assoc())
        $monthly_map[$row['kpi_def_id']][$row['month']] = $row;

$total_weight = array_sum(array_column($defs, 'weight'));
$months_vi = ['', 'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
$status_map = ['draft' => ['#F1F5F9', '#64748B'], 'active' => ['#DBEAFE', '#1D4ED8'], 'completed' => ['#D1FAE5', '#065F46'], 'cancelled' => ['#FEE2E2', '#991B1B']];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Quản lý KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        .content-wrapper {
            padding: .6rem 1rem;
            /* 64px topbar + 40px sheet-footer */
            height: calc(100vh - 64px - 40px);
            display: flex;
            flex-direction: column;
            gap: .5rem
        }

        /* Tab bar */
        .tab-bar {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #E5E7EB;
            background: #fff;
            border-radius: 8px 8px 0 0;
            overflow: hidden
        }

        .tab-btn {
            padding: 10px 22px;
            font-size: 13px;
            font-weight: 600;
            color: #6B7280;
            background: transparent;
            border: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px
        }

        .tab-btn:hover {
            color: #1D4ED8;
            background: #F8FAFF
        }

        .tab-btn.active {
            color: #1D4ED8;
            border-bottom-color: #1D4ED8;
            background: #EFF6FF
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: .4rem 0
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid #D1D5DB;
            background: #fff;
            color: #374151;
            transition: all .15s
        }

        .btn:hover {
            background: #F9FAFB
        }

        .btn-blue {
            background: #1D4ED8;
            color: #fff;
            border-color: #1D4ED8
        }

        .btn-blue:hover {
            background: #1e40af
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 12px
        }

        /* Sheet */
        .sheet-wrap {
            flex: 1;
            overflow: auto;
            border: 1px solid #E5E7EB;
            border-radius: 0 0 8px 8px;
            background: #fff
        }

        table.sheet {
            border-collapse: collapse;
            width: 100%;
            font-size: 13px;
            font-family: 'Roboto', sans-serif;
            min-width: 900px
        }

        table.sheet th,
        table.sheet td {
            border: 1px solid #E5E7EB;
            padding: 5px 9px;
            height: 33px;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        table.sheet th {
            background: #F8FAFC;
            font-weight: 600;
            font-size: 12px;
            color: #374151;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #E5E7EB
        }

        table.sheet tbody tr:hover td {
            background: #EFF6FF !important
        }

        table.sheet tbody tr:nth-child(even) td {
            background: #FAFAFA
        }

        .col-no {
            width: 42px;
            text-align: center;
            background: #F8FAFC !important;
            color: #9CA3AF;
            font-weight: 600;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid #E5E7EB !important
        }

        thead th.col-no {
            z-index: 15
        }

        .col-act {
            width: 72px;
            text-align: center
        }

        .group-row td {
            background: #EBF3FE !important;
            font-weight: 700;
            font-size: 11px;
            color: #1557b0;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 3px 9px;
            height: 24px
        }

        tfoot td {
            background: #F8FAFC;
            font-weight: 700;
            font-size: 13px;
            position: sticky;
            bottom: 0;
            z-index: 10;
            border-top: 2px solid #E5E7EB !important
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500
        }

        .badge-owner {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 8px 2px 3px;
            border-radius: 12px;
            background: #FDF4E7;
            color: #8F550C;
            border: 1px solid #F8E3C3;
            font-size: 11px;
            font-weight: 500
        }

        .badge-dept {
            background: #EDE9FE;
            color: #4C1D95;
            border: 1px solid #DDD6FE
        }

        .badge-cond {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px
        }

        .av-init {
            width: 19px;
            height: 19px;
            border-radius: 50%;
            background: #F59E0B;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700
        }

        .av-img {
            width: 19px;
            height: 19px;
            border-radius: 50%;
            object-fit: cover
        }

        /* Stats bar */
        .stats-bar {
            display: flex;
            gap: 16px;
            padding: 7px 14px;
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 13px;
            align-items: center;
            flex-wrap: wrap
        }

        .sp {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #374151
        }

        .sp strong {
            color: #111827
        }

        .w-ok {
            color: #059669;
            font-weight: 700
        }

        .w-warn {
            color: #D97706;
            font-weight: 700
        }

        /* Score bar */
        .s-bar-w {
            width: 44px;
            height: 5px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle
        }

        .s-bar-f {
            height: 100%;
            border-radius: 3px
        }

        /* Quarterly grid */
        .q-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 1px;
            background: #E5E7EB
        }

        .q-cell {
            background: #fff;
            padding: 4px 6px;
            font-size: 12px;
            text-align: center;
            min-width: 90px
        }

        .q-head {
            background: #F8FAFC;
            font-weight: 700;
            font-size: 11px;
            color: #1D4ED8;
            padding: 3px 6px;
            text-align: center
        }

        /* Monthly table extra */
        .month-input {
            width: 90px;
            padding: 3px 5px;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            font-size: 12px;
            text-align: right
        }

        .month-input:focus {
            border-color: #1D4ED8;
            outline: none
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px)
        }

        .modal.show {
            display: flex
        }

        .mc {
            background: #fff;
            border-radius: 10px;
            width: 600px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 40px rgba(0, 0, 0, .2);
            padding: 26px
        }

        .mc h2 {
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 18px;
            color: #111827
        }

        .fg {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 14px
        }

        .fg label {
            font-size: 11px;
            font-weight: 600;
            color: #374151
        }

        .fg input,
        .fg select,
        .fg textarea {
            padding: 7px 9px;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Roboto', sans-serif;
            color: #111827
        }

        .fg input:focus,
        .fg select:focus,
        .fg textarea:focus {
            border-color: #1D4ED8;
            outline: none;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, .12)
        }

        .fg2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px
        }

        .mf {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #F3F4F6
        }

        .notif {
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 4px
        }

        .notif.ok {
            background: #E6F4EA;
            color: #1E8E3E;
            border: 1px solid #CEEAD6
        }

        .notif.err {
            background: #FCE8E6;
            color: #C5221F;
            border: 1px solid #FAD2CF
        }

        /* ── Google Sheets–style sheet footer ─────────────────────── */
        .sheet-footer {
            position: fixed;
            bottom: 0;
            left: 280px;
            /* --sidebar-width */
            right: 0;
            height: 40px;
            background: #F1F3F4;
            border-top: 1px solid #C7C8CA;
            display: flex;
            align-items: stretch;
            z-index: 200;
            user-select: none;
        }

        /* Left nav area: scroll arrows + add btn */
        .sf-nav {
            display: flex;
            align-items: center;
            padding: 0 4px;
            gap: 0;
            border-right: 1px solid #C7C8CA;
            flex-shrink: 0;
        }

        .sf-nav-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            border-radius: 3px;
            cursor: pointer;
            color: #5F6368;
            font-size: 14px;
            transition: background .1s;
            flex-shrink: 0;
        }

        .sf-nav-btn:hover {
            background: #E8EAED;
            color: #202124;
        }

        .sf-nav-btn:disabled {
            color: #BDC1C6;
            cursor: default;
        }

        .sf-nav-btn:disabled:hover {
            background: transparent;
        }

        .sf-divider {
            width: 1px;
            height: 20px;
            background: #C7C8CA;
            margin: 0 3px;
        }

        /* Scrollable tab strip */
        .sf-strip {
            flex: 1;
            overflow: hidden;
            /* JS-controlled, NOT CSS scroll */
            display: flex;
            align-items: flex-end;
            padding: 0 4px;
            position: relative;
        }

        .sf-tabs {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            transition: transform .2s cubic-bezier(.4, 0, .2, 1);
            will-change: transform;
        }

        .sheet-tab {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0 16px;
            height: 32px;
            border-radius: 5px 5px 0 0;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            background: #E1E3E5;
            color: #5F6368;
            border: 1px solid #C7C8CA;
            border-bottom: none;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background .12s, color .12s;
        }

        .sheet-tab:hover {
            background: #DADCE0;
            color: #202124;
        }

        .sheet-tab.active {
            background: #fff;
            color: #1A73E8;
            font-weight: 700;
            border-color: #C7C8CA;
            border-top: 3px solid #1A73E8;
            padding-top: 0;
            height: 34px;
        }

        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 15px;
            height: 15px;
            padding: 0 3px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
            background: rgba(0, 0, 0, .08);
            color: inherit;
        }

        .sheet-tab.active .tab-badge {
            background: #E8F0FE;
            color: #1A73E8;
        }

        /* Drag indicator line between tabs */
        .sf-drop-indicator {
            width: 3px;
            min-width: 3px;
            background: #1A73E8;
            border-radius: 2px;
            height: 28px;
            align-self: center;
            pointer-events: none;
        }

        .sheet-tab.dragging {
            opacity: .4;
            cursor: grabbing !important;
        }

        @media print {

            .toolbar,
            .tab-bar,
            .stats-bar,
            .sheet-footer,
            .col-act {
                display: none !important
            }

            .sheet-wrap {
                overflow: visible;
                border: none
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php $page_title = 'Quản lý KPI';
            $page_subtitle = 'Năm ' . $year;
            include __DIR__ . '/../../modules/includes/topbar.php'; ?>

            <div class="content-wrapper">
                <?php if ($msg_ok): ?>
                    <div class="notif ok">✅ <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
                <?php if ($msg_err): ?>
                    <div class="notif err">❌ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

                <!-- Stats bar (year selector only) -->
                <div class="stats-bar">
                    <div class="sp">📅 Năm:</div>
                    <?php
                    // Build year options including next year
                    $all_years = $years_list;
                    if (!in_array(date('Y') + 1, $all_years))
                        $all_years[] = date('Y') + 1;
                    rsort($all_years);
                    ?>
                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                        <?php foreach ($all_years as $y): ?>
                            <a href="?tab=<?= $tab ?>&year=<?= $y ?>&dept=<?= $filter_dept ?>"
                                style="padding:3px 12px;border-radius:5px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid <?= $y == $year ? '#1D4ED8' : '#E5E7EB' ?>;background:<?= $y == $year ? '#1D4ED8' : '#fff' ?>;color:<?= $y == $year ? '#fff' : '#6B7280' ?>;transition:all .15s">
                                <?= $y ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-left:auto;display:flex;gap:16px;align-items:center;font-size:13px">
                        <div class="sp">📋 <?= count($defs) ?> KPI</div>
                        <?php $wc = (abs($total_weight - 100) < .01) ? 'w-ok' : 'w-warn'; ?>
                        <div class="sp">Tỷ trọng: <span class="<?= $wc ?>"><?= number_format($total_weight, 1) ?>%
                                <?= (abs($total_weight - 100) < .01) ? '✓' : '⚠' ?></span></div>
                    </div>
                </div>


                <!-- Content tabs -->
                <div class="tab-bar">
                    <a href="?tab=definitions&year=<?= $year ?>&dept=<?= $filter_dept ?>"
                        class="tab-btn <?= $tab === 'definitions' ? 'active' : '' ?>">
                        📊 Bộ KPI năm <?= $year ?>
                    </a>
                    <a href="?tab=quarterly&year=<?= $year ?>&dept=<?= $filter_dept ?>"
                        class="tab-btn <?= $tab === 'quarterly' ? 'active' : '' ?>">
                        📆 Kế hoạch theo Quý
                    </a>
                    <a href="?tab=monthly&year=<?= $year ?>&dept=<?= $filter_dept ?>"
                        class="tab-btn <?= $tab === 'monthly' ? 'active' : '' ?>">
                        📈 Số liệu theo Tháng
                    </a>
                    <a href="?tab=settings&year=<?= $year ?>&dept=<?= $filter_dept ?>"
                        class="tab-btn <?= $tab === 'settings' ? 'active' : '' ?>"
                        style="margin-left:auto;color:#6B7280">
                        ⚙️ Cài đặt
                    </a>
                </div>

                <!-- datalist for KPI name autocomplete -->
                <datalist id="kpiNameOptions">
                    <?php foreach ($kpi_templates as $tpl): ?>
                        <option value="<?= htmlspecialchars($tpl['name']) ?>"
                            data-group="<?= htmlspecialchars($tpl['kpi_group'] ?? '') ?>">
                        <?php endforeach; ?>
                </datalist>

                <?php if ($tab === 'definitions'): include __DIR__ . '/tab_definitions.php';
                elseif ($tab === 'quarterly'): include __DIR__ . '/tab_quarterly.php';
                elseif ($tab === 'monthly'): include __DIR__ . '/tab_monthly.php';
                elseif ($tab === 'settings'):
                    include __DIR__ . '/tab_settings.php';
                endif; ?>

            </div><!-- /content-wrapper -->

            <!-- Google Sheets–style dept footer -->
            <?php
            $dept_counts_q = $conn->query("SELECT department_id, COUNT(*) AS cnt FROM kpi_definitions WHERE year=$year GROUP BY department_id");
            $dept_kpi_count = [];
            $all_year_total = 0;
            if ($dept_counts_q)
                while ($dr = $dept_counts_q->fetch_assoc()) {
                    $dept_kpi_count[$dr['department_id']] = $dr['cnt'];
                    $all_year_total += $dr['cnt'];
                }
            ?>
            <div class="sheet-footer" id="sheetFooter">
                <!-- Left: Scroll nav + add btn -->
                <div class="sf-nav">
                    <button class="sf-nav-btn" id="sfPrev" title="Scroll left" onclick="sfScroll(-1)" disabled>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <button class="sf-nav-btn" id="sfNext" title="Scroll right" onclick="sfScroll(1)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
                <!-- Tab strip (JS-scrolled) -->
                <div class="sf-strip" id="sfStrip">
                    <div class="sf-tabs" id="sfTabs">
                        <?php foreach ($departments as $dpt):
                            $cnt = $dept_kpi_count[$dpt['id']] ?? 0;
                            ?>
                            <a href="?tab=<?= $tab ?>&year=<?= $year ?>&dept=<?= $dpt['id'] ?>"
                                class="sheet-tab <?= $filter_dept == $dpt['id'] ? 'active' : '' ?>"
                                data-id="<?= $dpt['id'] ?>" draggable="true">
                                <?= htmlspecialchars($dpt['name']) ?>
                                <span class="tab-badge"><?= $cnt ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Add/Edit KPI Definition -->
    <div id="defModal" class="modal">
        <div class="mc">
            <h2 id="defModalTitle">Thêm KPI</h2>
            <form method="POST" id="defForm">
                <input type="hidden" name="action" id="defAction" value="add_def">
                <input type="hidden" name="id" id="defId">
                <div class="fg2">
                    <div class="fg">
                        <label>Năm</label>
                        <input type="number" name="year" id="def_year" value="<?= $year ?>" min="2020" max="2035">
                    </div>
                    <div class="fg">
                        <label>Phòng ban</label>
                        <select name="department_id" id="def_dept">
                            <option value="">-- Chọn --</option>
                            <?php foreach ($departments as $d):
                                $is_disabled = ($_SESSION['role'] !== 'admin' && $_SESSION['department_id'] != $d['id']);
                                ?>
                                <option value="<?= $d['id'] ?>" <?= $is_disabled ? 'disabled style="color:#D1D5DB;background:#F9FAFB"' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="fg2">
                    <div class="fg">
                        <label>Nhóm KPI</label>
                        <select name="kpi_group" id="def_group">
                            <option value="">-- Chọn nhóm --</option>
                            <?php
                            $template_groups = array_unique(array_filter(array_column($kpi_templates, 'kpi_group')));
                            foreach ($template_groups as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>KPI Owner</label>
                        <select name="kpi_owner_id" id="def_owner">
                            <option value="">-- Chọn Owner --</option>
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="fg">
                    <label>Tên KPI <span style="color:red">*</span></label>
                    <input type="text" name="kpi_name" id="def_name" required placeholder="VD: Tổng doanh thu hợp nhất"
                        list="kpiNameOptions" autocomplete="off">
                </div>
                <div class="fg2">
                    <div class="fg">
                        <label>Target BASE</label>
                        <input type="text" name="target_base" id="def_target" placeholder="VD: 135 tỷ, ≥15% DT">
                    </div>
                    <div class="fg">
                        <label>Tỷ trọng (%)</label>
                        <input type="number" name="weight" id="def_weight" step="0.1" min="0" max="100"
                            placeholder="12">
                    </div>
                </div>
                <div class="fg">
                    <label>Ghi chú / Top-line</label>
                    <textarea name="notes" id="def_notes" rows="2" placeholder="Top-line, KPI điều kiện..."></textarea>
                </div>
                <div class="fg">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="is_condition" id="def_cond"
                            style="width:16px;height:16px;accent-color:#1D4ED8">
                        ⚑ KPI điều kiện (phải đạt mới tính thưởng)
                    </label>
                </div>
                <div class="mf">
                    <button type="button" class="btn" onclick="closeDefModal()">Huỷ</button>
                    <button type="submit" class="btn btn-blue">💾 Lưu</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddDef() {
            document.getElementById('defModalTitle').textContent = '➕ Thêm KPI mới';
            document.getElementById('defAction').value = 'add_def';
            document.getElementById('defId').value = '';
            document.getElementById('defForm').reset();
            document.getElementById('def_year').value = <?= $year ?>;
            document.getElementById('def_dept').value = '<?= $_SESSION['role'] !== 'admin' ? ($_SESSION['department_id'] ?? '') : '' ?>';
            document.getElementById('defModal').classList.add('show');
        }
        function openEditDef(d) {
            document.getElementById('defModalTitle').textContent = '✏️ Sửa KPI';
            document.getElementById('defAction').value = 'edit_def';
            document.getElementById('defId').value = d.id;
            document.getElementById('def_year').value = d.year;
            document.getElementById('def_dept').value = d.department_id || '';
            document.getElementById('def_group').value = d.kpi_group || '';
            document.getElementById('def_name').value = d.kpi_name || '';
            document.getElementById('def_target').value = d.target_base || '';
            document.getElementById('def_weight').value = d.weight || '';
            document.getElementById('def_owner').value = d.kpi_owner_id || '';
            document.getElementById('def_notes').value = d.notes || '';
            document.getElementById('def_cond').checked = d.is_condition == 1;
            document.getElementById('defModal').classList.add('show');
        }
        function closeDefModal() { document.getElementById('defModal').classList.remove('show') }
        document.getElementById('defModal').addEventListener('click', function (e) { if (e.target === this) closeDefModal() });

        // Filter KPI name datalist based on selected group
        const allKpiTemplates = <?= json_encode($kpi_templates) ?>;
        document.getElementById('def_group').addEventListener('change', function () {
            const selectedGroup = this.value;
            const datalist = document.getElementById('kpiNameOptions');
            datalist.innerHTML = ''; // clear current options

            allKpiTemplates.forEach(tpl => {
                if (!selectedGroup || tpl.kpi_group === selectedGroup) {
                    const opt = document.createElement('option');
                    opt.value = tpl.name;
                    opt.setAttribute('data-group', tpl.kpi_group || '');
                    datalist.appendChild(opt);
                }
            });
        });

        // Auto-select KPI group based on template
        document.getElementById('def_name').addEventListener('input', function (e) {
            const val = this.value;
            const options = document.querySelectorAll('#kpiNameOptions option');
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === val) {
                    const group = options[i].getAttribute('data-group');
                    if (group) {
                        let select = document.getElementById('def_group');
                        let exists = false;
                        for (let j = 0; j < select.options.length; j++) {
                            if (select.options[j].value === group) {
                                exists = true;
                                break;
                            }
                        }
                        if (!exists) {
                            let newOpt = document.createElement('option');
                            newOpt.value = group;
                            newOpt.textContent = group;
                            select.appendChild(newOpt);
                        }
                        select.value = group;
                    }
                    break;
                }
            }
        });

        // Generic inline save (quarterly & monthly)
        function saveInline(form) { form.submit() }

        // ── Google Sheets tab-strip scroll logic ────────────────────
        (function () {
            const strip = document.getElementById('sfStrip');
            const tabs = document.getElementById('sfTabs');
            const prev = document.getElementById('sfPrev');
            const next = document.getElementById('sfNext');
            if (!strip || !tabs) return;

            let offset = 0; // current translateX (negative = scrolled right)

            function maxOffset() {
                return Math.max(0, tabs.scrollWidth - strip.clientWidth);
            }
            function applyOffset(v) {
                offset = Math.max(-maxOffset(), Math.min(0, v));
                tabs.style.transform = 'translateX(' + offset + 'px)';
                prev.disabled = offset >= 0;
                next.disabled = offset <= -maxOffset();
            }
            function sfScroll(dir) {
                // scroll by ~3 tab widths
                const stepPx = strip.clientWidth * 0.4;
                applyOffset(offset + dir * -stepPx);
            }
            window.sfScroll = sfScroll;

            // Scroll active tab into view on load
            function sfScrollToActive() {
                const active = tabs.querySelector('.sheet-tab.active');
                if (!active) return;
                const tabLeft = active.offsetLeft;
                const tabRight = tabLeft + active.offsetWidth;
                const visible = strip.clientWidth;
                if (tabLeft + offset < 0) {
                    applyOffset(-tabLeft + 8);
                } else if (tabRight + offset > visible) {
                    applyOffset(-(tabRight - visible + 8));
                }
            }
            window.sfScrollToActive = sfScrollToActive;

            // Mouse-wheel horizontal scroll on footer
            document.getElementById('sheetFooter').addEventListener('wheel', function (e) {
                e.preventDefault();
                applyOffset(offset + (e.deltaY || e.deltaX) * -1);
            }, { passive: false });

            // Recalculate on resize
            window.addEventListener('resize', () => applyOffset(offset));

            // Auto scroll active into view on load
            setTimeout(sfScrollToActive, 50);
        })();

        // ── Drag-and-drop tab reorder ──────────────────────────────
        (function () {
            const tabsEl = document.getElementById('sfTabs');
            if (!tabsEl) return;

            let dragSrc = null;       // the tab being dragged
            let indicator = null;     // blue drop line element

            function createIndicator() {
                const el = document.createElement('span');
                el.className = 'sf-drop-indicator';
                return el;
            }
            function removeIndicator() {
                if (indicator && indicator.parentNode) indicator.parentNode.removeChild(indicator);
                indicator = null;
            }

            tabsEl.addEventListener('dragstart', function (e) {
                const tab = e.target.closest('.sheet-tab[draggable="true"]');
                if (!tab) { e.preventDefault(); return; }
                dragSrc = tab;
                setTimeout(() => tab.classList.add('dragging'), 0);
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', tab.dataset.id);
            });

            tabsEl.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!dragSrc) return;

                // Find which tab we're over
                const overTab = e.target.closest('.sheet-tab');
                removeIndicator();
                indicator = createIndicator();

                if (!overTab || overTab === dragSrc) {
                    tabsEl.appendChild(indicator);
                    return;
                }
                // Determine left/right half
                const rect = overTab.getBoundingClientRect();
                const mid = rect.left + rect.width / 2;
                if (e.clientX < mid) {
                    tabsEl.insertBefore(indicator, overTab);
                } else {
                    overTab.nextSibling
                        ? tabsEl.insertBefore(indicator, overTab.nextSibling)
                        : tabsEl.appendChild(indicator);
                }
            });

            tabsEl.addEventListener('dragleave', function (e) {
                if (!tabsEl.contains(e.relatedTarget)) removeIndicator();
            });

            tabsEl.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragSrc || !indicator) return;

                // Insert dragSrc before indicator position
                tabsEl.insertBefore(dragSrc, indicator);
                removeIndicator();
                dragSrc.classList.remove('dragging');

                // Collect new order (skip data-id=0, that's "Tất cả" pinned)
                const newOrder = [...tabsEl.querySelectorAll('.sheet-tab[draggable="true"]')]
                    .map(t => parseInt(t.dataset.id))
                    .filter(id => id > 0);

                // Save via AJAX
                fetch('/api/kpi_tab_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: newOrder })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) console.error('Save order failed:', data);
                        // Show brief toast
                        const t = document.createElement('div');
                        t.textContent = '✓ Đã lưu thứ tự';
                        t.style.cssText = 'position:fixed;bottom:50px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:6px 16px;border-radius:4px;font-size:13px;z-index:9999;transition:opacity .4s';
                        document.body.appendChild(t);
                        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 1500);
                    });

                dragSrc = null;
            });

            tabsEl.addEventListener('dragend', function () {
                removeIndicator();
                if (dragSrc) { dragSrc.classList.remove('dragging'); dragSrc = null; }
            });
        })();
    </script>
</body>

</html>