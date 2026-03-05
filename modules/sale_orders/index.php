<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/config.php';

// Get current user email
$current_email = '';
$stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$current_email = $row['email'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';
$is_admin = ($_SESSION['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Order Management</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .so-container {
            padding: 16px;
            height: calc(100vh - 64px);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            gap: 12px;
            overflow: hidden;
        }

        /* ---------- Controls ---------- */
        .page-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #F3F4F6;
            border-radius: 6px;
            padding: 6px 12px;
            min-width: 240px;
            transition: background .15s;
        }

        .search-box:focus-within {
            background: #fff;
            box-shadow: 0 0 0 2px #BFDBFE;
        }

        .search-box svg {
            color: #6B7280;
            flex-shrink: 0;
        }

        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            flex: 1;
            font-size: 13px;
            color: #111827;
        }

        .so-select {
            padding: 6px 28px 6px 10px;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 13px;
            color: #374151;
            background: #fff;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236B7280'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .ctrl-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            background: #fff;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            transition: all .15s;
        }

        .ctrl-btn:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }

        .ctrl-btn.active {
            background: #EFF6FF;
            border-color: #3B82F6;
            color: #1D4ED8;
        }

        .badge-count {
            font-size: 12px;
            color: #6B7280;
            padding: 4px 10px;
            background: #F3F4F6;
            border-radius: 20px;
            margin-left: auto;
            white-space: nowrap;
        }

        /* ---------- Loading ---------- */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #E5E7EB;
            border-top-color: #2563EB;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        /* ---------- Table ---------- */
        .data-wrapper {
            flex: 1;
            overflow: auto;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            background: #fff;
        }

        table.so-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            white-space: nowrap;
        }

        table.so-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #F8FAFC;
            color: #374151;
            font-weight: 600;
            font-size: 12px;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 2px solid #E5E7EB;
            border-right: 1px solid #E5E7EB;
            user-select: none;
        }

        table.so-table tbody td {
            padding: 7px 12px;
            border-bottom: 1px solid #F0F0F0;
            border-right: 1px solid #F0F0F0;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }

        table.so-table tbody tr:hover td {
            background: #EFF6FF !important;
        }

        .col-stt {
            width: 44px;
            text-align: center;
            background: #F8FAFC !important;
            color: #9CA3AF;
            font-weight: 600;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid #E5E7EB !important;
        }

        thead th.col-stt {
            z-index: 15;
        }

        /* Status badges */
        .so-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .so-draft {
            background: #F3F4F6;
            color: #374151;
        }

        .so-sale {
            background: #D1FAE5;
            color: #065F46;
        }

        .so-done {
            background: #DBEAFE;
            color: #1D4ED8;
        }

        .so-cancel {
            background: #FEE2E2;
            color: #991B1B;
        }

        .so-link {
            color: #1D4ED8;
            text-decoration: none;
            font-weight: 500;
        }

        .so-link:hover {
            text-decoration: underline;
        }

        /* ---------- Pagination ---------- */
        .pag-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #F8FAFC;
            border-top: 1px solid #E5E7EB;
            border-radius: 0 0 8px 8px;
        }

        .pag-info {
            font-size: 12px;
            color: #6B7280;
        }

        .pag-spacer {
            flex: 1;
        }

        .pag-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #E5E7EB;
            background: #fff;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #374151;
            transition: all .15s;
        }

        .pag-btn:hover:not(:disabled) {
            background: #EFF6FF;
            border-color: #3B82F6;
            color: #1D4ED8;
        }

        .pag-btn:disabled {
            opacity: .3;
            cursor: default;
        }

        .pag-num {
            font-size: 12px;
            color: #6B7280;
            padding: 0 8px;
        }

        /* Empty / Error */
        .empty-state {
            padding: 48px;
            text-align: center;
            color: #9CA3AF;
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            display: block;
        }

        .empty-state h3 {
            font-size: 15px;
            color: #374151;
            margin-bottom: 4px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Sale Order Management';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="so-container">

                <!-- Controls -->
                <div class="page-controls">
                    <div class="search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="soSearch" placeholder="Tìm SO, khách hàng, ref..."
                            oninput="debounceLoad()">
                    </div>

                    <select class="so-select" id="soStatus" onchange="loadOrders(1)">
                        <option value="">Tất cả trạng thái</option>
                        <option value="draft">Nháp (Quotation)</option>
                        <option value="sale">Đã xác nhận</option>
                        <option value="done">Hoàn tất</option>
                    </select>

                    <?php if ($is_admin): ?>
                        <button class="ctrl-btn active" id="myOnlyBtn" onclick="toggleMyOnly()">
                            👤 Của tôi
                        </button>
                    <?php endif; ?>

                    <button class="ctrl-btn" onclick="loadOrders(1)" title="Làm mới">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M23 4v6h-6" />
                            <path d="M1 20v-6h6" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                        </svg>
                        Làm mới
                    </button>

                    <div class="badge-count">
                        📋 <span id="showCount">0</span> / <span id="totalCount">0</span> Sale Orders
                    </div>
                </div>

                <!-- Table -->
                <div class="data-wrapper">
                    <table class="so-table">
                        <thead>
                            <tr>
                                <th class="col-stt">STT</th>
                                <th style="min-width:130px">Mã SO</th>
                                <th style="min-width:220px">Khách hàng</th>
                                <th style="min-width:120px">Ngày tạo</th>
                                <th style="min-width:200px">Salesperson</th>
                                <th style="min-width:120px">Team</th>
                                <th style="min-width:130px">Project Code</th>
                                <th style="min-width:140px;text-align:right">Tổng giá trị</th>
                                <th style="min-width:90px">Tiền tệ</th>
                                <th style="min-width:100px">Trạng thái</th>
                                <th style="min-width:150px">Tham chiếu</th>
                            </tr>
                        </thead>
                        <tbody id="soBody">
                            <tr>
                                <td colspan="11" style="padding:40px;text-align:center">
                                    <div style="display:flex;align-items:center;justify-content:center;gap:10px">
                                        <div class="spinner"></div> Đang tải dữ liệu từ Odoo...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination inside wrapper -->
                    <div class="pag-bar" id="pagBar">
                        <span class="pag-info">Hiển thị <b id="pagFrom">0</b>–<b id="pagTo">0</b> trong <b
                                id="pagTotal">0</b></span>
                        <div class="pag-spacer"></div>
                        <button class="pag-btn" id="pagFirst" onclick="loadOrders(1)" title="Trang đầu">«</button>
                        <button class="pag-btn" id="pagPrev" onclick="loadOrders(currentPage-1)"
                            title="Trang trước">‹</button>
                        <span class="pag-num">Trang <b id="pagCurrent">1</b> / <b id="pagLast">1</b></span>
                        <button class="pag-btn" id="pagNext" onclick="loadOrders(currentPage+1)"
                            title="Trang sau">›</button>
                        <button class="pag-btn" id="pagLastBtn" onclick="loadOrders(totalPages)"
                            title="Trang cuối">»</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;
        let currentPage = 1;
        let totalPages = 1;
        let myOnly = <?= $is_admin ? 'true' : 'false' ?>; // admins start with "my only", non-admins always see own
        let searchTimer = null;

        const ODOO_URL = 'https://erp18.merket.io';

        // Labels
        const STATUS_MAP = {
            draft: { label: 'Nháp', cls: 'so-draft' },
            sale: { label: 'Đã xác nhận', cls: 'so-sale' },
            done: { label: 'Hoàn tất', cls: 'so-done' },
            cancel: { label: 'Đã huỷ', cls: 'so-cancel' },
        };

        document.addEventListener('DOMContentLoaded', () => loadOrders(1));

        function debounceLoad() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadOrders(1), 400);
        }

        function toggleMyOnly() {
            myOnly = !myOnly;
            const btn = document.getElementById('myOnlyBtn');
            btn.classList.toggle('active', myOnly);
            btn.textContent = myOnly ? '👤 Của tôi' : '🌐 Tất cả';
            loadOrders(1);
        }

        function loadOrders(page) {
            currentPage = Math.max(1, page);
            const search = document.getElementById('soSearch').value.trim();
            const status = document.getElementById('soStatus').value;

            const params = new URLSearchParams({
                page: currentPage,
                limit: 25,
                search,
                status,
                my_only: myOnly || !IS_ADMIN ? '1' : '0'
            });

            document.getElementById('soBody').innerHTML = `
            <tr><td colspan="11" style="padding:36px;text-align:center">
                <div style="display:flex;align-items:center;justify-content:center;gap:10px">
                    <div class="spinner"></div> Đang tải...
                </div>
            </td></tr>`;

            fetch(`/api/sale_orders?${params}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) renderOrders(data.data, data.pagination);
                    else showError(data.error);
                })
                .catch(e => showError(e.message));
        }

        function renderOrders(orders, pag) {
            const tbody = document.getElementById('soBody');
            const start = (pag.page - 1) * pag.limit;

            totalPages = pag.totalPages || 1;

            document.getElementById('showCount').textContent = orders.length;
            document.getElementById('totalCount').textContent = pag.total;
            document.getElementById('pagFrom').textContent = pag.total ? start + 1 : 0;
            document.getElementById('pagTo').textContent = Math.min(start + pag.limit, pag.total);
            document.getElementById('pagTotal').textContent = pag.total;
            document.getElementById('pagCurrent').textContent = pag.page;
            document.getElementById('pagLast').textContent = totalPages;

            // Pagination buttons
            document.getElementById('pagFirst').disabled = pag.page <= 1;
            document.getElementById('pagPrev').disabled = pag.page <= 1;
            document.getElementById('pagNext').disabled = pag.page >= totalPages;
            document.getElementById('pagLastBtn').disabled = pag.page >= totalPages;

            if (!orders.length) {
                tbody.innerHTML = `
                <tr><td colspan="11">
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                            <rect x="9" y="3" width="6" height="4" rx="1"/>
                        </svg>
                        <h3>Không có Sale Order nào</h3>
                        <p>Thử thay đổi bộ lọc hoặc chọn "Tất cả"</p>
                    </div>
                </td></tr>`;
                return;
            }

            tbody.innerHTML = orders.map((o, idx) => {
                const st = STATUS_MAP[o.state] ?? { label: o.state, cls: 'so-draft' };
                const partner = Array.isArray(o.partner_id) ? o.partner_id[1] : '—';
                const currency = Array.isArray(o.currency_id) ? o.currency_id[1] : (o.currency_id || '');
                const team = Array.isArray(o.team_id) ? o.team_id[1] : (o.team_id || '—');
                const salesperson = Array.isArray(o.user_id) ? o.user_id[1] : (o.user_id || '—');
                const dateStr = o.date_order ? o.date_order.slice(0, 10) : '—';
                const amount = typeof o.amount_total === 'number'
                    ? new Intl.NumberFormat('vi-VN').format(o.amount_total)
                    : '—';
                const projCode = esc(o.x_studio_project_code || '—');
                const ref = esc(o.client_order_ref || '—');

                return `<tr>
                <td class="col-stt">${start + idx + 1}</td>
                <td><a class="so-link" href="${ODOO_URL}/odoo/sales/${o.id}" target="_blank">${esc(o.name)}</a></td>
                <td title="${esc(partner)}">${esc(partner)}</td>
                <td>${dateStr}</td>
                <td title="${esc(salesperson)}">${esc(salesperson)}</td>
                <td title="${esc(team)}">${esc(team)}</td>
                <td>${projCode}</td>
                <td style="text-align:right;font-weight:600">${amount}</td>
                <td>${esc(currency)}</td>
                <td><span class="so-status ${st.cls}">${st.label}</span></td>
                <td title="${ref}">${ref}</td>
            </tr>`;
            }).join('');
        }

        function showError(msg) {
            document.getElementById('soBody').innerHTML = `
            <tr><td colspan="11">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <h3 style="color:#B91C1C">Lỗi tải dữ liệu</h3>
                    <p>${esc(msg)}</p>
                    <p style="margin-top:6px"><a href="/settings/odoo" style="color:#1D4ED8">→ Kiểm tra cấu hình Odoo API</a></p>
                </div>
            </td></tr>`;
        }

        function esc(text) {
            const d = document.createElement('div');
            d.textContent = String(text ?? '');
            return d.innerHTML;
        }
    </script>
</body>

</html>