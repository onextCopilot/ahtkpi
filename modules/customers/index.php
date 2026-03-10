<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
if (empty($_SESSION['can_view_invoice'])) {
    header('Location: /dashboard');
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'User';
$avatar = $_SESSION['avatar'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Khách hàng</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Reset & Typography */
        body {
            overflow-x: hidden;
            font-family: 'Roboto', 'Inter', arial, sans-serif;
            color: #202124;
        }

        .main-content {
            overflow-x: hidden;
            background-color: #f8f9fa;
        }

        .customer-container {
            padding: 16px;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        /* Toolbar & Controls */
        .page-controls {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 12px;
            flex-wrap: wrap;
            background: white;
            padding: 8px 12px;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f1f3f4;
            border-radius: 4px;
            padding: 6px 12px;
            min-width: 250px;
            transition: background 0.2s;
        }

        .search-box:focus-within {
            background: white;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .search-box svg {
            color: #5f6368;
            width: 18px;
            height: 18px;
        }

        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            flex: 1;
            font-size: 14px;
            color: #202124;
        }

        .filter-controls {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            appearance: none;
            padding: 6px 30px 6px 12px;
            border: 1px solid transparent;
            border-radius: 4px;
            font-size: 13px;
            color: #3c4043;
            background: #f1f3f4 url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2210%22%20height%3D%225%22%20viewBox%3D%220%200%2010%205%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M0%200l5%205%205-5z%22%20fill%3D%22%233c4043%22%2F%3E%3C%2Fsvg%3E') no-repeat right 10px center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .filter-select:hover {
            background-color: #e8eaed;
        }

        .filter-select:focus {
            background-color: white;
            border-color: #1a73e8;
            outline: none;
        }

        .btn-clear {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 13px;
            color: #3c4043;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-clear:hover {
            background-color: #f1f3f4;
            color: #202124;
        }

        .info-badge {
            margin-left: auto;
            font-size: 12px;
            color: #5f6368;
            padding: 6px 12px;
            background: #f1f3f4;
            border-radius: 16px;
        }

        /* Table Styles (Google Sheets-like) */
        .data-table-wrapper {
            flex: 1;
            overflow: auto;
            position: relative;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            /* Optional: adds a slight corner */
        }

        table.customer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            white-space: nowrap;
            table-layout: fixed;
            /* Ensures columns respect widths if set, otherwise distributes */
        }

        /* Column Widths (optional specific sizing) */
        table.customer-table th:nth-child(1) {
            width: 50px;
        }

        /* # */
        table.customer-table th:nth-child(2) {
            width: 250px;
        }

        /* Name */
        table.customer-table th:nth-child(3) {
            width: 200px;
        }

        /* Email */

        table.customer-table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            color: #5f6368;
            font-weight: 600;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 2px solid #dadce0;
            border-right: 1px solid #dadce0;
            z-index: 10;
            font-size: 12px;
            letter-spacing: 0.2px;
            user-select: none;
        }

        table.customer-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }

        table.customer-table tbody td {
            padding: 8px 12px;
            border-right: 1px solid #e0e0e0;
            color: #202124;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Number Column Style */
        table.customer-table tbody td:first-child {
            background: #f8f9fa;
            text-align: center;
            font-weight: 600;
            color: #5f6368;
            border-right: 2px solid #dadce0;
            position: sticky;
            left: 0;
            z-index: 5;
        }

        /* Highlight row on hover */
        table.customer-table tbody tr:hover td {
            background-color: #e8f0fe;
        }

        /* Ensure the first column stays gray on hover but gets slightly darker to indicate row selection intent if needed, or matches the row highlight */
        table.customer-table tbody tr:hover td:first-child {
            background-color: #d2e3fc;
        }

        /* Links behaving like cell data */
        .odoo-link {
            color: #1a73e8;
            font-size: 12px;
            margin-left: 8px;
            text-decoration: none;
        }

        .odoo-link:hover {
            text-decoration: underline;
        }

        /* Links behaving like cell data */
        .odoo-link {
            color: #202124;
            /* Black text */
            font-size: 13px;
            /* Match table font size */
            text-decoration: none;
            font-weight: 500;
            display: block;
            /* Make it block to fill cell slightly better if needed, or keep inline */
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .odoo-link:hover {
            color: #1a73e8;
            /* Blue on hover */
            text-decoration: underline;
        }

        /* Status Pills */
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #e6f4ea;
            color: #137333;
        }

        .status-inactive {
            background: #fce8e6;
            color: #c5221f;
        }

        /* Pagination Bottom Bar */
        /* Pagination Bottom Bar */
        .pagination-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 12px 16px;
            background: #f8f9fa;
            border-top: 1px solid #dadce0;
            gap: 16px;
        }

        .pagination-info {
            font-size: 12px;
            color: #5f6368;
            margin-right: auto;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-btn {
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            color: #5f6368;
            padding: 6px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f1f3f4;
            color: #202124;
        }

        .pagination-btn:disabled {
            opacity: 0.3;
            cursor: default;
        }

        .page-numbers {
            display: flex;
            gap: 4px;
        }

        .page-number {
            font-size: 13px;
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 4px;
            color: #3c4043;
            border: 1px solid transparent;
        }

        .page-number:hover {
            background-color: #f1f3f4;
            color: #202124;
        }

        .page-number.active {
            background-color: #e8f0fe;
            color: #1967d2;
            font-weight: 500;
            border-color: #d2e3fc;
        }

        /* Loading & Empty States */
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #1a73e8;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto 8px;
        }

        .empty-state {
            padding: 40px;
            color: #5f6368;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Quản lý Khách hàng';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="customer-container">
                <div id="errorContainer"></div>

                <!-- Key Accounts Section -->
                <div class="key-accounts-section"
                    style="margin-bottom: 32px; background: white; border: 1px solid #dadce0; border-radius: 4px; padding: 16px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h2 style="font-size: 18px; color: #1a73e8; margin: 0;">Thống kê Doanh thu Key Accounts (Odoo)
                        </h2>
                        <div class="filter-controls">
                            <select id="statsYearFilter" onchange="loadKeyAccountStats()" class="filter-select">
                                <option value="2026">Năm 2026</option>
                                <option value="2025">Năm 2025</option>
                                <option value="2024">Năm 2024</option>
                            </select>
                        </div>
                    </div>
                    <div class="data-table-wrapper" style="max-height: none;">
                        <table class="customer-table" style="min-width: 1000px;">
                            <thead id="statsTableHeader">
                                <!-- Headers will be generated dynamically -->
                            </thead>
                            <tbody id="keyAccountsStatsBody">
                                <!-- Stats will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="page-controls">
                    <div class="search-box">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="searchInput" placeholder="Tìm kiếm khách hàng..."
                            onkeyup="debounceSearch()">
                    </div>
                    <div class="filter-controls">
                        <select id="cityFilter" onchange="loadCustomers(1)" class="filter-select">
                            <option value="">Tất cả thành phố</option>
                        </select>
                        <select id="countryFilter" onchange="loadCustomers(1)" class="filter-select">
                            <option value="">Tất cả quốc gia</option>
                        </select>
                        <select id="statusFilter" onchange="loadCustomers(1)" class="filter-select">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                        <select id="keyAccountFilter" onchange="loadCustomers(1)" class="filter-select">
                            <option value="">Key Account (Tất cả)</option>
                            <option value="1">Chỉ Key Account</option>
                            <option value="0">Không phải Key Account</option>
                        </select>
                        <button class="btn-clear" onclick="clearFilters()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Xóa bộ lọc
                        </button>
                    </div>
                    <div class="info-badge">
                        📊 <span id="displayCount">0</span> / <span id="totalCount">0</span> khách hàng
                    </div>
                </div>

                <div class="data-table-wrapper" style="flex: none;">
                    <table class="customer-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 100px;">Key Account</th>
                                <th>Tên công ty</th>
                                <th>Email</th>
                                <th>Điện thoại</th>
                                <th style="width: 100px;">Di động</th>
                                <th style="width: 100px;">Thành phố</th>
                                <th style="width: 100px;">Quốc gia</th>
                                <th style="width: 150px;">Ngành nghề</th>
                                <th style="width: 120px;">Trạng thái</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <tr>
                                <td colspan="11">
                                    <div class="loading-state">
                                        <div class="loading-spinner"></div>
                                        <p>Đang tải dữ liệu...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        Hiển thị <span id="pageStart">0</span> - <span id="pageEnd">0</span> của <span
                            id="totalFiltered">0</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="firstPage" onclick="goToPage(1)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="11 17 6 12 11 7"></polyline>
                                <polyline points="18 17 13 12 18 7"></polyline>
                            </svg>
                        </button>
                        <button class="pagination-btn" id="prevPage" onclick="previousPage()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <div class="page-numbers" id="pageNumbers"></div>
                        <button class="pagination-btn" id="nextPage" onclick="nextPage()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                        <button class="pagination-btn" id="lastPage" onclick="goToLastPage()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="13 17 18 12 13 7"></polyline>
                                <polyline points="6 17 11 12 6 7"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="noteModalTitle" style="margin:0; font-size:18px;">Ghi chú khách hàng</h3>
                <span class="close" onclick="closeNoteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Filter Controls for History -->
                <div class="note-history-filters" style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                    <select id="noteYearFilter" class="filter-select" style="padding: 4px 8px; font-size: 12px; height: auto;" onchange="loadNotesHistory(currentEditingOdooId, 1)">
                        <option value="">Năm (Tất cả)</option>
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                        <option value="2024">2024</option>
                    </select>
                    <select id="noteQuarterFilter" class="filter-select" style="padding: 4px 8px; font-size: 12px; height: auto;" onchange="loadNotesHistory(currentEditingOdooId, 1)">
                        <option value="">Quý (Tất cả)</option>
                        <option value="1">Quý 1</option>
                        <option value="2">Quý 2</option>
                        <option value="3">Quý 3</option>
                        <option value="4">Quý 4</option>
                    </select>
                    <select id="noteMonthFilter" class="filter-select" style="padding: 4px 8px; font-size: 12px; height: auto;" onchange="loadNotesHistory(currentEditingOdooId, 1)">
                        <option value="">Tháng (Tất cả)</option>
                        ${Array.from({length: 12}, (_, i) => `<option value="${i+1}">Tháng ${i+1}</option>`).join('')}
                    </select>
                    <button class="btn-clear" style="padding: 4px 8px; font-size: 12px;" onclick="clearNoteFilters()">Xóa lọc</button>
                </div>

                <div id="notesHistory" style="max-height: 300px; overflow-y: auto; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <!-- History logs here -->
                </div>
                
                <div id="notePagination" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <!-- Pagination buttons here -->
                </div>
                <div class="note-input-area">
                    <textarea id="newNoteContent" placeholder="Viết ghi chú mới..."
                        style="width: 100%; min-height: 80px; padding: 10px; border: 1px solid #dadce0; border-radius: 4px; resize: vertical; margin-bottom: 10px; font-family: inherit; font-size: 14px; box-sizing: border-box;"></textarea>
                    <div style="text-align: right;">
                        <button class="btn btn-primary" onclick="saveNewNote()">Lưu ghi chú</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Switch Toggle Style */
        .switch {
            position: relative;
            display: inline-block;
            width: 34px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #1a73e8;
        }

        input:focus+.slider {
            box-shadow: 0 0 1px #1a73e8;
        }

        input:checked+.slider:before {
            -webkit-transform: translateX(14px);
            -ms-transform: translateX(14px);
            transform: translateX(14px);
        }

        .revenue-cell {
            text-align: right;
            font-family: 'Roboto Mono', monospace;
            font-size: 11px;
        }

        .revenue-total {
            font-weight: 600;
            background: #f8f9fa;
        }

        .inline-edit-select {
            width: 100%;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 4px;
            font-size: 12px;
            background: white;
        }

        .inline-edit-note {
            width: 100%;
            height: 40px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 4px;
            font-size: 11px;
            resize: vertical;
            font-family: inherit;
        }

        .bc-dropdown-container {
            position: relative;
            width: 100%;
        }

        .bc-dropdown-btn {
            width: 100%;
            padding: 6px 12px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 12px;
            text-align: left;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .bc-dropdown-btn:after {
            content: '▼';
            font-size: 8px;
            float: right;
            margin-top: 3px;
            color: #5f6368;
        }

        .bc-dropdown-content {
            display: none;
            position: fixed;
            /* Use fixed to avoid clipping by scrollable table wrappers */
            background-color: white;
            min-width: 180px;
            box-shadow: 0px 8px 32px rgba(0, 0, 0, 0.15);
            z-index: 99999;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 8px;
            max-height: 250px;
            overflow-y: auto;
        }

        .bc-dropdown-content.show {
            display: block;
        }

        .company-source-select {
            width: 100%;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 4px;
            font-size: 11px;
            background: white;
            color: #202124;
        }

        .project-input-wrapper {
            position: relative;
            width: 100%;
        }

        .project-input {
            width: 100%;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 4px;
            font-size: 11px;
            min-height: 40px;
        }

        .avg-revenue-cell {
            text-align: right;
            font-weight: 600;
            color: #1e8e3e;
            white-space: nowrap;
        }

        .currency-unit {
            font-size: 10px;
            color: #5f6368;
            margin-left: 2px;
            font-weight: normal;
        }

        /* Stats Table Toggle fix */
        .stats-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border: 1px solid #888;
            width: 80%;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .modal-header {
            padding: 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #dadce0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .close {
            color: #5f6368;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #202124;
        }

        .note-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #eee;
        }

        .note-content-text {
            font-size: 13px;
            color: #202124;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .note-meta {
            margin-top: 8px;
            font-size: 11px;
            color: #5f6368;
            display: flex;
            justify-content: space-between;
        }

        .note-author {
            font-weight: 600;
            color: #1a73e8;
        }

        .take-note-btn {
            background: none;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            color: #5f6368;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            transition: all 0.2s;
        }

        .take-note-btn:hover {
            border-color: #1a73e8;
            color: #1a73e8;
            background: #f1f8ff;
        }

        .note-preview-text {
            font-size: 11px;
            color: #5f6368;
            max-width: 250px;
            white-space: normal !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 3 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden;
            line-height: 1.4;
            margin-bottom: 4px;
        }
    </style>

    <script>
        const ITEMS_PER_PAGE = 20;
        let currentPage = 1;
        let totalPages = 1;
        let searchTimeout = null;

        // Load customers on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadCustomers(1);
            loadKeyAccountStats();
        });

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadCustomers(1);
            }, 500); // Wait 500ms after user stops typing
        }

        function loadCustomers(page) {
            currentPage = page;
            const search = document.getElementById('searchInput').value;
            const city = document.getElementById('cityFilter').value;
            const country = document.getElementById('countryFilter').value;
            const status = document.getElementById('statusFilter').value;
            const is_key_account = document.getElementById('keyAccountFilter').value;

            // Build query string
            const params = new URLSearchParams({
                page: page,
                limit: ITEMS_PER_PAGE,
                search: search,
                city: city,
                country: country,
                status: status,
                is_key_account: is_key_account
            });

            // Show loading
            document.getElementById('customerTableBody').innerHTML = `
                <tr>
                    <td colspan="11">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <p>Đang tải dữ liệu...</p>
                        </div>
                    </td>
                </tr>
            `;

            // Fetch data
            fetch(`/api/customers.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderCustomers(data.data, data.pagination);
                    } else {
                        showError(data.error);
                    }
                })
                .catch(error => {
                    showError('Lỗi kết nối: ' + error.message);
                });
        }

        function toggleKeyAccount(odooId, isKeyAccount) {
            fetch('/api/customers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    odoo_id: odooId,
                    is_key_account: isKeyAccount ? 1 : 0
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Reload both sections to reflect changes
                        loadKeyAccountStats();
                    } else {
                        alert('Lỗi: ' + data.error);
                    }
                });
        }

        let globalAmBdList = [];
        const BC_LIST = ['BC1', 'BC2', 'BC3', 'BC4', 'BC5', 'BC6', 'BC7', 'BC8', 'BC9', 'BC10'];

        function loadKeyAccountStats() {
            const year = document.getElementById('statsYearFilter').value;
            const container = document.getElementById('keyAccountsStatsBody');
            const header = document.getElementById('statsTableHeader');

            container.innerHTML = `<tr><td colspan="20" style="text-align:center; padding: 20px;">Đang tính toán thống kê...</td></tr>`;

            fetch(`/api/key_accounts_stats.php`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        globalAmBdList = data.am_bd_list || [];
                        renderStats(data.data, year);
                    }
                });
        }

        function updateKeyAccountMetadata(odooId, field, value) {
            const payload = {
                odoo_id: odooId,
                [field]: value
            };

            fetch('/api/customers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) alert('Lỗi: ' + data.error);
                });
        }

        function renderStats(data, year) {
            const header = document.getElementById('statsTableHeader');
            const body = document.getElementById('keyAccountsStatsBody');

            // Generate Headers 
            header.innerHTML = `
                <tr>
                    <th style="width: 80px; text-align: center;">Bật/Tắt</th>
                    <th style="width: 200px; position: sticky; left: 0; background: #f8f9fa; z-index: 20; text-align: left;">Khách hàng (Key Account)</th>
                    <th style="width: 120px;">Thuộc Cty</th>
                    <th style="width: 130px;">AM/BD</th>
                    <th style="width: 150px;">Delivery Owner (BCs)</th>
                    <th style="width: 150px;">Dự án đang chạy</th>
                    <th style="width: 150px; text-align: right;">Doanh Thu TB (6th)</th>
                    <th class="revenue-cell" style="width: 130px;">Tổng Năm</th>
                    <th style="min-width: 400px;">Ghi chú</th>
                </tr>
            `;

            if (data.length === 0) {
                body.innerHTML = `<tr><td colspan="10" style="text-align:center; padding: 40px; color: #5f6368;">Chưa có Key Account nào được thiết lập</td></tr>`;
                return;
            }

            // Get Current Date context for "Last 6 Months Average"
            const now = new Date();

            body.innerHTML = data.map(customer => {
                const stats = customer.stats;
                const formatVND = (val) => val ? new Intl.NumberFormat('vi-VN').format(Math.round(Math.abs(val))) : '-';

                // 1. Calculate Yearly Total for the SELECTED year
                let yearlyTotal = 0;
                for (let m = 1; m <= 12; m++) {
                    const mk = `${year}-${m.toString().padStart(2, '0')}`;
                    yearlyTotal += (stats.monthly[mk] || 0);
                }

                // 2. Calculate Average Revenue (TB) of the LAST 6 COMPLETED MONTHS
                let last6MonthsTotal = 0;
                for (let i = 1; i <= 6; i++) {
                    let d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                    const mk = `${d.getFullYear()}-${(d.getMonth() + 1).toString().padStart(2, '0')}`;
                    last6MonthsTotal += (stats.monthly[mk] || 0);
                }
                const avgRevenue = last6MonthsTotal / 6;

                const qs = [
                    stats.quarterly[`${year}-Q1`] || 0,
                    stats.quarterly[`${year}-Q2`] || 0,
                    stats.quarterly[`${year}-Q3`] || 0,
                    stats.quarterly[`${year}-Q4`] || 0
                ];

                // Toggle Switch (Column 1)
                const toggleSwitch = `
                    <div class="stats-toggle">
                        <label class="switch">
                            <input type="checkbox" checked onchange="toggleKeyAccount(${customer.id}, this.checked)">
                            <span class="slider round"></span>
                        </label>
                    </div>
                `;

                // Company Source select
                const companies = ['AHT TECH', 'AC1 VN', 'AC1 MY', 'AHT Japan'];
                const companySelect = `
                    <select class="company-source-select" onchange="updateKeyAccountMetadata(${customer.id}, 'company_source', this.value)">
                        <option value="">-- Chọn --</option>
                        ${companies.map(c => `<option value="${c}" ${customer.company_source === c ? 'selected' : ''}>${c}</option>`).join('')}
                    </select>
                `;

                // AM/BD Select
                const amSelect = `
                    <select class="inline-edit-select" style="font-size: 11px;" onchange="updateKeyAccountMetadata(${customer.id}, 'am_bd_id', this.value)">
                        <option value="">-- AM/BD --</option>
                        ${globalAmBdList.map(am => `<option value="${am.id}" ${customer.am_bd_id == am.id ? 'selected' : ''}>${escapeHtml(am.full_name)}</option>`).join('')}
                    </select>
                `;

                // BC Multi-select Dropdown
                const currentBCs = (customer.delivery_owners || '').split(',').filter(b => b.trim() !== '');
                const bcDropdown = `
                    <div class="bc-dropdown-container">
                        <button class="bc-dropdown-btn" onclick="toggleBCDropdown(event, this)">
                            ${currentBCs.length > 0 ? currentBCs.join(', ') : 'Chọn BCs...'}
                        </button>
                        <div class="bc-dropdown-content">
                            ${BC_LIST.map(bc => `
                                <label class="bc-option" style="display:flex; align-items:center; gap:8px; padding:4px 8px; cursor:pointer;">
                                    <input type="checkbox" value="${bc}" ${currentBCs.includes(bc) ? 'checked' : ''} 
                                        onchange="handleBCChange(${customer.id}, this)">
                                    <span style="font-size:12px;">${bc}</span>
                                </label>
                            `).join('')}
                        </div>
                    </div>
                `;

                // Project Field (Jira or manual)
                const projectField = `
                    <textarea class="project-input" placeholder="Dự án..." 
                        onblur="updateKeyAccountMetadata(${customer.id}, 'active_projects', this.value)">${escapeHtml(customer.active_projects || '')}</textarea>
                `;

                // Ghi chú Button with last note preview
                const noteBtn = `
                    <div class="note-container" style="display: flex; flex-direction: column; align-items: flex-start; gap: 8px;">
                        ${customer.account_note ? `<div class="note-preview-text" style="color: #202124; font-size: 13px; -webkit-line-clamp: 5 !important;" title="${escapeHtml(customer.account_note)}">${escapeHtml(customer.account_note)}</div>` : '<span style="color: #999; font-style: italic; font-size: 12px; margin-bottom: 4px;">Chưa có ghi chú...</span>'}
                        <button class="take-note-btn" onclick="openNoteModal(${customer.id}, '${escapeHtml(customer.name)}')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            ${customer.account_note ? 'Xem lịch sử' : 'Thêm ghi chú'}
                        </button>
                    </div>
                `;

                return `
                    <tr>
                        <td style="text-align: center;">${toggleSwitch}</td>
                        <td style="font-weight: 500; position: sticky; left: 0; background: white; z-index: 10; border-right: 2px solid #dadce0; text-align: left;">
                            ${escapeHtml(customer.name)}
                        </td>
                        <td>${companySelect}</td>
                        <td>${amSelect}</td>
                        <td style="padding: 4px;">${bcDropdown}</td>
                        <td style="padding: 4px;">${projectField}</td>
                        <td class="avg-revenue-cell">
                             ${formatVND(avgRevenue)} <span class="currency-unit">VND</span>
                        </td>
                        <td class="revenue-cell revenue-total">${formatVND(yearlyTotal)}</td>
                        <td style="padding: 8px; white-space: normal;">${noteBtn}</td>
                    </tr>
                `;
            }).join('');
        }

        let currentEditingOdooId = null;

        function openNoteModal(odooId, customerName) {
            currentEditingOdooId = odooId;
            document.getElementById('noteModalTitle').textContent = `Lịch sử ghi chú: ${customerName}`;
            document.getElementById('newNoteContent').value = '';
            
            // Reset filters when opening
            document.getElementById('noteYearFilter').value = '';
            document.getElementById('noteQuarterFilter').value = '';
            document.getElementById('noteMonthFilter').value = '';
            
            document.getElementById('noteModal').style.display = 'block';
            loadNotesHistory(odooId, 1);
        }

        function closeNoteModal() {
            document.getElementById('noteModal').style.display = 'none';
            currentEditingOdooId = null;
        }

        function clearNoteFilters() {
            document.getElementById('noteYearFilter').value = '';
            document.getElementById('noteQuarterFilter').value = '';
            document.getElementById('noteMonthFilter').value = '';
            loadNotesHistory(currentEditingOdooId, 1);
        }

        function loadNotesHistory(odooId, page = 1) {
            const historyContainer = document.getElementById('notesHistory');
            const paginationContainer = document.getElementById('notePagination');
            
            const year = document.getElementById('noteYearFilter').value;
            const quarter = document.getElementById('noteQuarterFilter').value;
            const month = document.getElementById('noteMonthFilter').value;

            historyContainer.innerHTML = '<p style="text-align:center; padding:10px;">Đang tải lịch sử...</p>';
            paginationContainer.innerHTML = '';

            const params = new URLSearchParams({
                odoo_id: odooId,
                page: page,
                limit: 5,
                year: year,
                quarter: quarter,
                month: month
            });

            fetch(`/api/customer_notes.php?${params}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.data.length === 0) {
                            historyContainer.innerHTML = '<p style="text-align:center; color:#999; padding:20px;">Không có ghi chú nào được tìm thấy.</p>';
                        } else {
                            historyContainer.innerHTML = data.data.map(note => `
                                <div class="note-item">
                                    <div class="note-content-text">${escapeHtml(note.note_content)}</div>
                                    <div class="note-meta">
                                        <span class="note-author">Người viết: ${escapeHtml(note.author)}</span>
                                        <span class="note-time">${note.created_at}</span>
                                    </div>
                                </div>
                            `).join('');
                            
                            // Render Simple Pagination
                            if (data.pagination.totalPages > 1) {
                                let paginationHtml = '';
                                if (data.pagination.page > 1) {
                                    paginationHtml += `<button class="pagination-btn" onclick="loadNotesHistory(${odooId}, ${data.pagination.page - 1})">Quay lại</button>`;
                                }
                                paginationHtml += `<span style="font-size: 13px; color: #5f6368;">Trang ${data.pagination.page} / ${data.pagination.totalPages}</span>`;
                                if (data.pagination.page < data.pagination.totalPages) {
                                    paginationHtml += `<button class="pagination-btn" onclick="loadNotesHistory(${odooId}, ${data.pagination.page + 1})">Tiếp theo</button>`;
                                }
                                paginationContainer.innerHTML = paginationHtml;
                            }
                        }
                    }
                });
        }

        function saveNewNote() {
            const content = document.getElementById('newNoteContent').value.trim();
            if (!content) return;

            fetch('/api/customer_notes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    odoo_id: currentEditingOdooId,
                    content: content
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        loadNotesHistory(currentEditingOdooId);
                        document.getElementById('newNoteContent').value = '';
                        loadKeyAccountStats();
                    } else {
                        alert('Lỗi: ' + data.error);
                    }
                });
        }

        function toggleBCDropdown(event, btn) {
            event.stopPropagation();
            const content = btn.nextElementSibling;

            // Close other dropdowns
            document.querySelectorAll('.bc-dropdown-content.show').forEach(el => {
                if (el !== content) el.classList.remove('show');
            });

            const isShowing = content.classList.toggle('show');

            if (isShowing) {
                const rect = btn.getBoundingClientRect();
                content.style.left = rect.left + 'px';

                // Check space below
                const spaceBelow = window.innerHeight - rect.bottom;
                if (spaceBelow < 250 && rect.top > 250) {
                    // Show above
                    content.style.top = 'auto';
                    content.style.bottom = (window.innerHeight - rect.top) + 'px';
                } else {
                    // Show below
                    content.style.top = rect.bottom + 'px';
                    content.style.bottom = 'auto';
                }

                // Final check to ensure it doesn't go off left/right
                const contentRect = content.getBoundingClientRect();
                if (rect.left + contentRect.width > window.innerWidth) {
                    content.style.left = (window.innerWidth - contentRect.width - 10) + 'px';
                }
            }
        }

        // Close dropdowns on click outside
        window.onclick = function (event) {
            if (!event.target.closest('.bc-dropdown-container')) {
                document.querySelectorAll('.bc-dropdown-content').forEach(el => el.classList.remove('show'));
            }
        };

        function handleBCChange(odooId, checkbox) {
            const container = checkbox.closest('.bc-dropdown-content');
            const btn = container.previousElementSibling;
            const checked = Array.from(container.querySelectorAll('input:checked')).map(i => i.value);

            btn.textContent = checked.length > 0 ? checked.join(', ') : 'Chọn BCs...';
            updateKeyAccountMetadata(odooId, 'delivery_owners', checked.join(','));
        }

        function renderCustomers(customers, pagination) {
            const tbody = document.getElementById('customerTableBody');

            if (customers.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <h3>Chưa có khách hàng</h3>
                                <p>Không tìm thấy khách hàng nào</p>
                            </div>
                        </td>
                    </tr>
                `;
                updatePaginationInfo(0, 0, 0, 0);
                return;
            }

            // Render rows
            const startIndex = (pagination.page - 1) * pagination.limit;
            tbody.innerHTML = customers.map((customer, idx) => `
                <tr>
                    <td>${startIndex + idx + 1}</td>
                    <td style="text-align: center;">
                        <label class="switch">
                            <input type="checkbox" ${customer.is_key_account ? 'checked' : ''} onchange="toggleKeyAccount(${customer.id}, this.checked)">
                            <span class="slider"></span>
                        </label>
                    </td>
                    <td>
                        <a href="https://erp18.merket.io/odoo/web#id=${customer.id}&model=res.partner&view_type=form"
                            target="_blank" class="company-name" style="color: #1a73e8; text-decoration: none;">
                            ${escapeHtml(customer.name || 'N/A')}
                        </a>
                    </td>
                    <td>${escapeHtml(customer.email || '')}</td>
                    <td>${escapeHtml(customer.phone || '')}</td>
                    <td>${escapeHtml(customer.mobile || '')}</td>
                    <td>${escapeHtml(customer.city || '')}</td>
                    <td>${customer.country_id && Array.isArray(customer.country_id) ? escapeHtml(customer.country_id[1]) : ''}</td>
                    <td>${customer.industry_id && Array.isArray(customer.industry_id) ? escapeHtml(customer.industry_id[1]) : ''}</td>
                    <td>
                        <span class="status-badge ${customer.active ? 'status-active' : 'status-inactive'}">
                            ${customer.active ? 'Hoạt động' : 'Ngừng hoạt động'}
                        </span>
                    </td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${escapeHtml(customer.comment || '')}
                    </td>
                </tr>
            `).join('');

            // Update pagination
            totalPages = pagination.totalPages;
            const startItem = (pagination.page - 1) * pagination.limit + 1;
            const endItem = Math.min(pagination.page * pagination.limit, pagination.total);
            updatePaginationInfo(startItem, endItem, pagination.total, pagination.total);
            renderPageNumbers(pagination.totalPages);
        }

        function updatePaginationInfo(start, end, filtered, total) {
            document.getElementById('pageStart').textContent = start;
            document.getElementById('pageEnd').textContent = end;
            document.getElementById('totalFiltered').textContent = filtered;
            document.getElementById('displayCount').textContent = filtered;
            document.getElementById('totalCount').textContent = total;

            // Update button states
            document.getElementById('firstPage').disabled = currentPage === 1;
            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
            document.getElementById('lastPage').disabled = currentPage === totalPages || totalPages === 0;
        }

        function renderPageNumbers(total) {
            const container = document.getElementById('pageNumbers');
            container.innerHTML = '';

            if (total <= 7) {
                for (let i = 1; i <= total; i++) {
                    container.appendChild(createPageButton(i));
                }
            } else {
                container.appendChild(createPageButton(1));

                if (currentPage > 3) {
                    container.appendChild(createEllipsis());
                }

                const startPage = Math.max(2, currentPage - 1);
                const endPage = Math.min(total - 1, currentPage + 1);

                for (let i = startPage; i <= endPage; i++) {
                    container.appendChild(createPageButton(i));
                }

                if (currentPage < total - 2) {
                    container.appendChild(createEllipsis());
                }

                container.appendChild(createPageButton(total));
            }
        }

        function createPageButton(pageNum) {
            const button = document.createElement('div');
            button.className = 'page-number' + (pageNum === currentPage ? ' active' : '');
            button.textContent = pageNum;
            button.onclick = () => goToPage(pageNum);
            return button;
        }

        function createEllipsis() {
            const ellipsis = document.createElement('div');
            ellipsis.className = 'page-number';
            ellipsis.textContent = '...';
            ellipsis.style.cursor = 'default';
            return ellipsis;
        }

        function goToPage(page) {
            loadCustomers(page);
        }

        function previousPage() {
            if (currentPage > 1) {
                loadCustomers(currentPage - 1);
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                loadCustomers(currentPage + 1);
            }
        }

        function goToLastPage() {
            if (totalPages > 0) {
                loadCustomers(totalPages);
            }
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('cityFilter').value = '';
            document.getElementById('countryFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('keyAccountFilter').value = '';
            loadCustomers(1);
        }

        function showError(message) {
            const errorContainer = document.getElementById('errorContainer');
            errorContainer.innerHTML = `
                <div class="alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <strong>Lỗi:</strong> ${escapeHtml(message)}
                        <br>
                        <a href="/settings/odoo">→ Cấu hình Odoo API</a>
                    </div>
                </div>
            `;

            document.getElementById('customerTableBody').innerHTML = `
                <tr>
                    <td colspan="11">
                        <div class="empty-state">
                            <h3>Không thể tải dữ liệu</h3>
                            <p>Vui lòng kiểm tra cấu hình Odoo API</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>