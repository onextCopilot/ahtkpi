<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$full_name = $_SESSION['full_name'] ?? '';

// Fetch users for AM dropdown
$users_list = [];
$res = $conn->query("SELECT id, full_name FROM users ORDER BY full_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo Phương Án Kinh Doanh - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --success: #16a34a;
            --success-bg: #dcfce7;
            --warning: #d97706;
            --warning-bg: #fef9c3;
            --danger: #dc2626;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --slate: #1e293b;
            --gray: #64748b;
            --light-gray: #94a3b8;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --radius-xl: 16px;
            --radius-lg: 10px;
            --radius-md: 7px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.07);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--slate);
            margin: 0;
        }

        .main-content {
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }

        /* ── Top Stats Bar ── */
        .top-stats-bar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 8px 28px;
            display: flex;
            align-items: center;
            gap: 32px;
            justify-content: flex-end;
            font-size: 13px;
        }

        .top-stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--gray);
        }

        .top-stat-item .stat-label {
            font-weight: 500;
        }

        .top-stat-item .stat-value {
            font-weight: 700;
            font-size: 14px;
        }

        .top-stat-item .stat-value.green  { color: #16a34a; }
        .top-stat-item .stat-value.blue   { color: #2563eb; }
        .top-stat-item .stat-value.purple { color: #7c3aed; }

        .top-stat-sep {
            width: 1px;
            height: 16px;
            background: var(--border);
        }

        /* ── Status Bar ── */
        .status-bar {
            background: #f0fdf4;
            border-bottom: 1px solid #bbf7d0;
            padding: 10px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .status-bar .status-icon {
            width: 22px;
            height: 22px;
            background: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            flex-shrink: 0;
        }

        .status-bar .status-text {
            font-weight: 600;
            color: #15803d;
        }

        .status-bar .status-meta {
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 500;
            background: #e0e7ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }

        /* ── Navigation Bar ── */
        .nav-bar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            display: flex;
            align-items: center;
            gap: 0;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 2px solid transparent;
            transition: all 0.15s;
            text-decoration: none;
        }

        .nav-btn:hover { color: var(--primary); }

        .nav-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }

        .nav-btn-back {
            color: var(--gray);
            font-size: 13px;
            margin-right: 8px;
        }

        .history-badge {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Page Body ── */
        .page-body {
            padding: 28px;
            flex: 1;
        }

        /* ── Project Title ── */
        .project-title-wrap {
            margin-bottom: 24px;
        }

        .project-title-input {
            font-size: 22px;
            font-weight: 700;
            color: var(--slate);
            border: none;
            outline: none;
            background: transparent;
            width: 100%;
            padding: 4px 0;
            border-bottom: 2px solid transparent;
            transition: border-color 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .project-title-input:focus {
            border-bottom-color: var(--primary);
        }

        .project-title-input::placeholder {
            color: #cbd5e1;
            font-weight: 400;
        }

        /* ── Meta Grid (top fields row) ── */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .meta-field {
            padding: 14px 18px;
            border-right: 1px solid var(--border);
        }

        .meta-field:last-child {
            border-right: none;
        }

        .meta-field-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--light-gray);
            margin-bottom: 6px;
        }

        .meta-field-value {
            font-size: 13px;
            font-weight: 500;
            color: var(--slate);
        }

        .meta-field-value select,
        .meta-field-value input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 13px;
            font-weight: 500;
            color: var(--slate);
            font-family: 'Inter', sans-serif;
            width: 100%;
            cursor: pointer;
            padding: 0;
        }

        .meta-field-value select:focus,
        .meta-field-value input:focus {
            color: var(--primary);
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-chip.draft     { background: #f1f5f9; color: #64748b; }
        .status-chip.pending   { background: #fef9c3; color: #d97706; }
        .status-chip.approved  { background: #dcfce7; color: #15803d; }
        .status-chip.rejected  { background: #fee2e2; color: #dc2626; }

        /* ── Opportunity Row ── */
        .opportunity-row {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: var(--shadow-sm);
        }

        .opp-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--light-gray);
            white-space: nowrap;
        }

        .opp-input {
            flex: 1;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 7px 12px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--slate);
            outline: none;
            min-width: 200px;
            transition: all 0.2s;
        }

        .opp-input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }

        .opp-sep {
            width: 1px;
            height: 18px;
            background: var(--border);
        }

        .opp-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 600;
            background: #f8fafc;
            border: 1px solid var(--border);
            color: var(--slate);
            white-space: nowrap;
        }

        .opp-badge i { color: var(--primary); }

        .opp-link {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .opp-link:hover { text-decoration: underline; }

        /* ── Section Cards ── */
        .section-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background: #fafbfc;
            cursor: pointer;
            user-select: none;
        }

        .section-header-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            background: rgba(99,102,241,0.1);
            color: var(--primary);
        }

        .section-header-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--slate);
            flex: 1;
        }

        .section-chevron {
            color: var(--light-gray);
            font-size: 12px;
            transition: transform 0.2s;
        }

        .section-chevron.open { transform: rotate(180deg); }

        .section-body {
            padding: 22px;
        }

        /* ── Form Grid ── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group.span-2 {
            grid-column: span 2;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--light-gray);
        }

        .form-control {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 9px 12px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--slate);
            outline: none;
            background: white;
            transition: all 0.2s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.08);
        }

        .form-control::placeholder { color: #cbd5e1; }

        .form-control-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin-top: 4px;
        }

        .form-control-link:hover { text-decoration: underline; }

        /* ── PAKD Detail Table ── */
        .pakd-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .pakd-detail-table thead th {
            background: #f8fafc;
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--light-gray);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .pakd-detail-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .pakd-detail-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pakd-detail-table tbody tr:hover {
            background: rgba(99,102,241,0.02);
        }

        .pakd-detail-table td input,
        .pakd-detail-table td select {
            border: 1px solid transparent;
            border-radius: var(--radius-md);
            padding: 6px 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--slate);
            outline: none;
            background: transparent;
            width: 100%;
            transition: all 0.15s;
        }

        .pakd-detail-table td input:hover,
        .pakd-detail-table td select:hover {
            background: #f8fafc;
            border-color: var(--border);
        }

        .pakd-detail-table td input:focus,
        .pakd-detail-table td select:focus {
            background: white;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 2px rgba(99,102,241,0.08);
        }

        .add-row-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: 1px dashed var(--border);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            background: none;
            margin-top: 12px;
            transition: all 0.15s;
            font-family: 'Inter', sans-serif;
        }

        .add-row-btn:hover {
            border-color: var(--primary-light);
            color: var(--primary);
            background: rgba(99,102,241,0.04);
        }

        /* ── Footer Actions ── */
        .footer-actions {
            background: white;
            border-top: 1px solid var(--border);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: var(--radius-lg);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
        }

        .btn-ghost {
            background: transparent;
            color: var(--gray);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--bg);
            color: var(--slate);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 3px 10px rgba(99,102,241,0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(99,102,241,0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            box-shadow: 0 3px 10px rgba(22,163,74,0.3);
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(22,163,74,0.4);
        }

        /* ── Summary Numbers Row (in PAKD detail) ── */
        .pakd-summary-row {
            background: #f8fafc;
            border-top: 2px solid var(--border);
        }

        .pakd-summary-row td {
            padding: 12px 14px !important;
            font-weight: 700;
            color: var(--slate);
        }

        .text-right { text-align: right; }
        .text-green { color: #16a34a; font-weight: 700; }
        .text-red   { color: #dc2626; font-weight: 700; }
        .text-blue  { color: #2563eb; font-weight: 700; }

        /* ── Responsive ── */
        @media (max-width: 1200px) {
            .meta-grid { grid-template-columns: repeat(3, 1fr); }
            .form-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .page-body { padding: 16px; }
            .meta-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
            .top-stats-bar { flex-wrap: wrap; gap: 12px; padding: 10px 16px; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">

        <!-- Top Stats Bar -->
        <div class="top-stats-bar">
            <div class="top-stat-item">
                <span class="stat-label">Doanh thu thuần:</span>
                <span class="stat-value green" id="stat-dtt">0 VNĐ</span>
            </div>
            <div class="top-stat-sep"></div>
            <div class="top-stat-item">
                <span class="stat-label">Lợi nhuận gộp:</span>
                <span class="stat-value blue" id="stat-lng">0 VNĐ</span>
            </div>
            <div class="top-stat-sep"></div>
            <div class="top-stat-item">
                <span class="stat-label">PASX:</span>
                <span class="stat-value purple" id="stat-pasx">0 VNĐ <span style="font-size:11px;color:var(--gray);">(0%)</span></span>
            </div>
        </div>

        <!-- Status Bar (shown after save) -->
        <div class="status-bar" id="statusBar" style="display:none;">
            <div class="status-icon"><i class="fas fa-check"></i></div>
            <span class="status-text">PAKD đã được approve</span>
            <div class="status-meta">
                <span>·</span>
                <span>PASX: <strong id="statusPasx">0%</strong></span>
                <span>·</span>
                <span class="tag-pill"><i class="fas fa-building"></i> Trong công ty</span>
            </div>
        </div>

        <!-- Navigation Bar -->
        <div class="nav-bar">
            <a href="/projects/phuong-an-kinh-doanh" class="nav-btn nav-btn-back">
                <i class="fas fa-arrow-left"></i> Danh sách PAKD
            </a>
            <div style="width:1px;height:20px;background:var(--border);margin: 0 4px;"></div>
            <button class="nav-btn active" onclick="switchTab('form')">
                <span class="history-badge"><i class="fas fa-history"></i> Lịch sử (1 version)</span>
            </button>
        </div>

        <!-- Page Body -->
        <div class="page-body">

            <!-- Project Title -->
            <div class="project-title-wrap">
                <input
                    type="text"
                    class="project-title-input"
                    id="projectTitle"
                    placeholder="Nhập tên phương án kinh doanh..."
                    oninput="syncPasxTitle()"
                >
            </div>

            <!-- Meta Fields Grid -->
            <div class="meta-grid">
                <div class="meta-field">
                    <div class="meta-field-label">Department</div>
                    <div class="meta-field-value">
                        <select id="department" onchange="updateStats()">
                            <option value="">-- Chọn --</option>
                            <option value="BC ITO">BC ITO</option>
                            <option value="BC SAP">BC SAP</option>
                            <option value="BC AI">BC AI</option>
                            <option value="BC ERP">BC ERP</option>
                            <option value="Presale">Presale</option>
                        </select>
                    </div>
                </div>
                <div class="meta-field">
                    <div class="meta-field-label">AM (BỐ/AM)</div>
                    <div class="meta-field-value">
                        <select id="amUser">
                            <option value="">-- Chọn AM --</option>
                            <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="meta-field">
                    <div class="meta-field-label">Loại dự án</div>
                    <div class="meta-field-value">
                        <select id="projectType">
                            <option value="internal">Trong công ty</option>
                            <option value="external">Khách hàng ngoài</option>
                            <option value="partner">Đối tác</option>
                        </select>
                    </div>
                </div>
                <div class="meta-field">
                    <div class="meta-field-label">PASX ước</div>
                    <div class="meta-field-value" id="pasxUocDisplay" style="color:var(--gray);">—</div>
                </div>
                <div class="meta-field">
                    <div class="meta-field-label">Currency</div>
                    <div class="meta-field-value">
                        <select id="currency">
                            <option value="VND">VND</option>
                            <option value="USD">USD</option>
                            <option value="JPY">JPY</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                </div>
                <div class="meta-field">
                    <div class="meta-field-label">Status</div>
                    <div class="meta-field-value">
                        <select id="status" onchange="updateStatusBadge()">
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Opportunity Row -->
            <div class="opportunity-row">
                <span class="opp-label">Opportunity</span>
                <input type="text" class="opp-input" id="opportunityName" placeholder="Nhập tên opportunity từ Odoo...">
                <div class="opp-sep"></div>
                <div class="opp-badge">
                    <i class="fas fa-building"></i>
                    <input type="text" id="companyName" placeholder="Tên công ty / khách hàng" style="border:none;outline:none;background:transparent;font-weight:600;min-width:180px;font-family:inherit;font-size:13px;color:var(--slate);">
                </div>
                <div class="opp-sep"></div>
                <div class="opp-badge">
                    <i class="fas fa-dollar-sign" style="color:#16a34a;"></i>
                    <input type="text" id="oppValue" placeholder="Giá trị" style="border:none;outline:none;background:transparent;font-weight:600;width:100px;font-family:inherit;font-size:13px;color:var(--slate);" oninput="updateStats()">
                </div>
                <div class="opp-badge">
                    <i class="fas fa-percent" style="color:#d97706;"></i>
                    <input type="number" id="oppProb" placeholder="%" min="0" max="100" style="border:none;outline:none;background:transparent;font-weight:600;width:50px;font-family:inherit;font-size:13px;color:var(--slate);" value="100" oninput="updateStats()">
                    <span style="color:var(--gray);">%</span>
                </div>
                <a href="#" class="opp-link" target="_blank" id="odooLink" style="display:none;">
                    <i class="fas fa-external-link-alt"></i> Mở trong Odoo
                </a>
            </div>

            <!-- Thông tin hợp đồng -->
            <div class="section-card">
                <div class="section-header" onclick="toggleSection('contract')">
                    <div class="section-header-icon"><i class="fas fa-file-contract"></i></div>
                    <div class="section-header-title">Thông tin hợp đồng</div>
                    <i class="fas fa-chevron-down section-chevron open" id="chevron-contract"></i>
                </div>
                <div class="section-body" id="body-contract">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Khách hàng</label>
                            <input type="text" class="form-control" id="customer" placeholder="VD: CÔNG TY CỔ PHẦN RT HOLDINGS">
                            <a href="#" class="form-control-link" id="customerOdooLink" style="display:none;">
                                <i class="fas fa-external-link-alt"></i> Mở khách hàng trong Odoo
                            </a>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Số Hợp Đồng</label>
                            <input type="text" class="form-control" id="contractNo" placeholder="VD: HĐ-2026-001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sales Order No</label>
                            <select class="form-control" id="salesOrder">
                                <option value="">-- Chọn SO --</option>
                                <option value="S00411">S00411</option>
                                <option value="S00412">S00412</option>
                                <option value="S00500">S00500</option>
                            </select>
                            <a href="#" class="form-control-link" id="soOdooLink" style="display:none;">
                                <i class="fas fa-external-link-alt"></i> Mở SO trong Odoo
                            </a>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Purchase Order No</label>
                            <select class="form-control" id="purchaseOrder">
                                <option value="">Tìm PO từ Odoo...</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Thời gian triển khai</label>
                            <input type="text" class="form-control" id="timeline" placeholder="VD: Q3-Q4/2026, 6 tháng từ 01/06/2026...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phương án Kinh doanh chi tiết -->
            <div class="section-card">
                <div class="section-header" onclick="toggleSection('pakd')">
                    <div class="section-header-icon" style="background:rgba(245,158,11,0.1);color:#d97706;">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="section-header-title">Phương án Kinh doanh chi tiết</div>
                    <i class="fas fa-chevron-down section-chevron open" id="chevron-pakd"></i>
                </div>
                <div class="section-body" id="body-pakd" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table class="pakd-detail-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Hạng mục / Dịch vụ</th>
                                    <th>Đơn vị</th>
                                    <th>SL</th>
                                    <th>Đơn giá bán (VNĐ)</th>
                                    <th>Thành tiền bán</th>
                                    <th>Đơn giá vốn (VNĐ)</th>
                                    <th>Thành tiền vốn</th>
                                    <th>Lợi nhuận gộp</th>
                                    <th>% LN gộp</th>
                                    <th>Ghi chú</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="pakdRows">
                                <!-- rows added by JS -->
                            </tbody>
                            <tfoot>
                                <tr class="pakd-summary-row" id="summaryRow">
                                    <td colspan="4" style="font-weight:700;">Tổng cộng</td>
                                    <td></td>
                                    <td class="text-blue" id="totalRevenue">0</td>
                                    <td></td>
                                    <td class="text-red"  id="totalCost">0</td>
                                    <td class="text-green" id="totalProfit">0</td>
                                    <td id="avgMargin">0%</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div style="padding:16px 20px;">
                        <button class="add-row-btn" onclick="addPakdRow()">
                            <i class="fas fa-plus"></i> Thêm hạng mục
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ghi chú nội bộ -->
            <div class="section-card">
                <div class="section-header" onclick="toggleSection('notes')">
                    <div class="section-header-icon" style="background:rgba(14,165,233,0.1);color:#0284c7;">
                        <i class="fas fa-sticky-note"></i>
                    </div>
                    <div class="section-header-title">Ghi chú nội bộ</div>
                    <i class="fas fa-chevron-down section-chevron open" id="chevron-notes"></i>
                </div>
                <div class="section-body" id="body-notes">
                    <textarea class="form-control" rows="4" id="internalNotes"
                        placeholder="Thêm ghi chú nội bộ (chỉ hiển thị với team nội bộ)..."
                        style="resize:vertical;"></textarea>
                </div>
            </div>

        </div><!-- /.page-body -->

        <!-- Footer Actions -->
        <div class="footer-actions">
            <a href="/projects/phuong-an-kinh-doanh" class="btn btn-ghost">
                <i class="fas fa-times"></i> Huỷ
            </a>
            <button class="btn btn-ghost" onclick="saveDraft()">
                <i class="fas fa-save"></i> Lưu nháp
            </button>
            <button class="btn btn-primary" onclick="submitForApproval()">
                <i class="fas fa-paper-plane"></i> Gửi duyệt
            </button>
        </div>

    </div><!-- /.main-content -->
</div>

<script>
/* ── Section Toggle ── */
function toggleSection(id) {
    const body = document.getElementById('body-' + id);
    const chev = document.getElementById('chevron-' + id);
    const isOpen = !body.classList.contains('hidden');
    if (isOpen) {
        body.style.display = 'none';
        chev.classList.remove('open');
    } else {
        body.style.display = '';
        chev.classList.add('open');
    }
}

/* ── PAKD Rows ── */
let rowCount = 0;

function addPakdRow(data = {}) {
    rowCount++;
    const tbody = document.getElementById('pakdRows');
    const tr = document.createElement('tr');
    tr.id = 'row-' + rowCount;
    tr.innerHTML = `
        <td style="color:var(--light-gray);font-size:12px;">${rowCount}</td>
        <td><input type="text" placeholder="VD: Triển khai phần mềm ERP" value="${data.name||''}"></td>
        <td><select style="width:90px;">
            <option>người/tháng</option><option>giờ</option><option>ngày</option><option>gói</option><option>lần</option>
        </select></td>
        <td><input type="number" min="0" value="${data.qty||1}" oninput="recalcRow(${rowCount})" style="width:60px;"></td>
        <td><input type="text" placeholder="0" value="${data.price||''}" oninput="recalcRow(${rowCount})" class="num-input"></td>
        <td class="text-blue rev-cell-${rowCount}" style="font-weight:600;">0</td>
        <td><input type="text" placeholder="0" value="${data.cost||''}" oninput="recalcRow(${rowCount})" class="num-input"></td>
        <td class="text-red cost-cell-${rowCount}" style="font-weight:600;">0</td>
        <td class="profit-cell-${rowCount}" style="font-weight:700;">0</td>
        <td class="margin-cell-${rowCount}" style="font-size:12px;">0%</td>
        <td><input type="text" placeholder="Ghi chú..."></td>
        <td>
            <button onclick="removeRow(${rowCount})" style="background:none;border:none;cursor:pointer;color:#cbd5e1;font-size:14px;padding:4px 8px;border-radius:6px;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#cbd5e1'">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRow(id) {
    const row = document.getElementById('row-' + id);
    if (row) row.remove();
    recalcTotal();
}

function parseNum(val) {
    return parseFloat(String(val).replace(/[^0-9.-]/g, '')) || 0;
}

function formatNum(n) {
    if (n === 0) return '0';
    return new Intl.NumberFormat('vi-VN').format(Math.round(n));
}

function recalcRow(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;
    const inputs = row.querySelectorAll('input.num-input');
    const qtyInput = row.querySelectorAll('input[type=number]')[0];
    const qty = parseFloat(qtyInput?.value) || 0;
    const price = parseNum(inputs[0]?.value);
    const cost  = parseNum(inputs[1]?.value);
    const revenue = qty * price;
    const totalCost = qty * cost;
    const profit = revenue - totalCost;
    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(1) : 0;

    const revCell    = row.querySelector('.rev-cell-' + id);
    const costCell   = row.querySelector('.cost-cell-' + id);
    const profitCell = row.querySelector('.profit-cell-' + id);
    const marginCell = row.querySelector('.margin-cell-' + id);

    if (revCell)    revCell.textContent    = formatNum(revenue);
    if (costCell)   costCell.textContent   = formatNum(totalCost);
    if (profitCell) { profitCell.textContent = formatNum(profit); profitCell.className = 'profit-cell-' + id + (profit >= 0 ? ' text-green' : ' text-red'); }
    if (marginCell) marginCell.textContent = margin + '%';

    recalcTotal();
}

function recalcTotal() {
    let totalRev = 0, totalCost = 0;
    const rows = document.querySelectorAll('#pakdRows tr');
    rows.forEach(row => {
        const id = row.id?.replace('row-', '');
        if (!id) return;
        const revCell    = row.querySelector('[class*="rev-cell-"]');
        const costCell   = row.querySelector('[class*="cost-cell-"]');
        if (revCell)  totalRev  += parseNum(revCell.textContent);
        if (costCell) totalCost += parseNum(costCell.textContent);
    });

    const profit = totalRev - totalCost;
    const margin = totalRev > 0 ? ((profit / totalRev) * 100).toFixed(1) : 0;

    document.getElementById('totalRevenue').textContent = formatNum(totalRev);
    document.getElementById('totalCost').textContent    = formatNum(totalCost);
    document.getElementById('totalProfit').textContent  = formatNum(profit);
    document.getElementById('avgMargin').textContent    = margin + '%';

    // Update top stats
    document.getElementById('stat-dtt').innerHTML  = formatNum(totalRev) + ' VNĐ';
    document.getElementById('stat-lng').innerHTML  = formatNum(profit) + ' VNĐ';
    const pasxPct = parseNum(document.getElementById('oppProb')?.value) || 100;
    const pasx = totalRev * (pasxPct / 100);
    document.getElementById('stat-pasx').innerHTML = formatNum(pasx) + ' VNĐ <span style="font-size:11px;color:var(--gray);">(' + pasxPct + '%)</span>';
    document.getElementById('statusPasx').textContent = margin + '%';
}

function updateStats() {
    recalcTotal();
    syncPasxTitle();
}

function syncPasxTitle() {
    const title = document.getElementById('projectTitle').value;
    document.getElementById('pasxUocDisplay').textContent = title || '—';
}

function updateStatusBadge() { /* can animate status bar */ }

function saveDraft() {
    const data = collectFormData();
    data.status = 'draft';
    console.log('Draft:', data);
    showToast('Đã lưu nháp thành công!', 'success');
}

function submitForApproval() {
    const title = document.getElementById('projectTitle').value.trim();
    if (!title) {
        showToast('Vui lòng nhập tên phương án!', 'error');
        document.getElementById('projectTitle').focus();
        return;
    }
    const data = collectFormData();
    data.status = 'pending';
    console.log('Submit:', data);
    showToast('Đã gửi phương án để duyệt!', 'success');
    document.getElementById('statusBar').style.display = 'flex';
}

function collectFormData() {
    return {
        title:        document.getElementById('projectTitle').value,
        department:   document.getElementById('department').value,
        am:           document.getElementById('amUser').value,
        project_type: document.getElementById('projectType').value,
        currency:     document.getElementById('currency').value,
        status:       document.getElementById('status').value,
        opportunity:  document.getElementById('opportunityName').value,
        company:      document.getElementById('companyName').value,
        opp_value:    document.getElementById('oppValue').value,
        opp_prob:     document.getElementById('oppProb').value,
        customer:     document.getElementById('customer').value,
        contract_no:  document.getElementById('contractNo').value,
        sales_order:  document.getElementById('salesOrder').value,
        timeline:     document.getElementById('timeline').value,
        notes:        document.getElementById('internalNotes').value,
    };
}

/* ── Toast Notification ── */
function showToast(message, type = 'success') {
    const existing = document.querySelector('.toast-notif');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast-notif';
    toast.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 9999;
        background: ${type === 'success' ? '#16a34a' : '#dc2626'};
        color: white; padding: 12px 20px; border-radius: 10px;
        font-size: 13px; font-weight: 600; font-family: Inter, sans-serif;
        display: flex; align-items: center; gap: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
    `;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Init: add 3 default rows
addPakdRow({ name: 'Tư vấn triển khai', qty: 1 });
addPakdRow({ name: 'Phát triển phần mềm', qty: 1 });
addPakdRow({ name: 'Đào tạo & hỗ trợ', qty: 1 });

// Sync SO link visibility
document.getElementById('salesOrder')?.addEventListener('change', function() {
    const link = document.getElementById('soOdooLink');
    link.style.display = this.value ? 'inline-flex' : 'none';
});

// Sync customer link visibility
document.getElementById('customer')?.addEventListener('input', function() {
    const link = document.getElementById('customerOdooLink');
    link.style.display = this.value ? 'inline-flex' : 'none';
});
</script>
<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}
</style>
</body>
</html>
