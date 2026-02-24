<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
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
            height: calc(100vh - 60px);
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

                <div class="data-table-wrapper">
                    <table class="customer-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Tên công ty</th>
                                <th>Email</th>
                                <th>Điện thoại</th>
                                <th>Di động</th>
                                <th>Thành phố</th>
                                <th>Quốc gia</th>
                                <th>Ngành nghề</th>
                                <th>Trạng thái</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <tr>
                                <td colspan="10">
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

    <script>
        const ITEMS_PER_PAGE = 20;
        let currentPage = 1;
        let totalPages = 1;
        let searchTimeout = null;

        // Load customers on page load
        document.addEventListener('DOMContentLoaded', function () {
            loadCustomers(1);
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

            // Build query string
            const params = new URLSearchParams({
                page: page,
                limit: ITEMS_PER_PAGE,
                search: search,
                city: city,
                country: country,
                status: status
            });

            // Show loading
            document.getElementById('customerTableBody').innerHTML = `
                <tr>
                    <td colspan="10">
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

        function renderCustomers(customers, pagination) {
            const tbody = document.getElementById('customerTableBody');

            if (customers.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10">
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
                    <td colspan="10">
                        <div class="empty-state">
                            <h3>Không thể tải dữ liệu</h3>
                            <p>Vui lòng kiểm tra cấu hình Odoo API</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>