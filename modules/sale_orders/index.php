<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
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

        /* ---------- Group header ---------- */
        tr.group-row td {
            padding: 0 !important;
            background: #F1F5F9;
            border-bottom: 1px solid #E2E8F0;
            position: sticky;
            left: 0;
        }

        .group-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            cursor: pointer;
            user-select: none;
            font-size: 13px;
            color: #1E293B;
        }

        .group-header:hover {
            background: #E2E8F0;
        }

        .group-caret {
            display: inline-block;
            transition: transform .15s;
            color: #64748B;
            font-size: 11px;
        }

        .group-row.collapsed .group-caret {
            transform: rotate(-90deg);
        }

        .group-title {
            font-weight: 700;
        }

        .group-count {
            font-size: 11px;
            color: #64748B;
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 1px 9px;
        }

        .group-total {
            margin-left: auto;
            font-weight: 600;
            color: #0F172A;
            font-size: 12px;
        }

        .group-total span {
            color: #64748B;
            font-weight: 500;
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

                    <select class="so-select" id="soYear" onchange="loadOrders()">
                        <option value="">Tất cả năm</option>
                        <?php
                        $cur_year = (int) date('Y');
                        for ($y = $cur_year; $y >= $cur_year - 6; $y--) {
                            $sel = $y === $cur_year ? ' selected' : '';
                            echo "<option value=\"$y\"$sel>Năm $y</option>";
                        }
                        ?>
                    </select>

                    <select class="so-select" id="soMonth" onchange="loadOrders()">
                        <option value="">Tất cả tháng</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>">Tháng <?= $m ?></option>
                        <?php endfor; ?>
                    </select>

                    <select class="so-select" id="soStatus" onchange="loadOrders()">
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

                    <button class="ctrl-btn" onclick="loadOrders()" title="Làm mới">
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
                </div>
            </div>
        </main>
    </div>

    <script>
        const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;
        let myOnly = <?= $is_admin ? 'true' : 'false' ?>; // admins start with "my only", non-admins always see own
        let searchTimer = null;
        const collapsed = {}; // group key -> true if collapsed

        const ODOO_URL = 'https://erp18.merket.io';

        // Labels
        const STATUS_MAP = {
            draft: { label: 'Nháp', cls: 'so-draft' },
            sale: { label: 'Đã xác nhận', cls: 'so-sale' },
            done: { label: 'Hoàn tất', cls: 'so-done' },
            cancel: { label: 'Đã huỷ', cls: 'so-cancel' },
        };

        document.addEventListener('DOMContentLoaded', () => loadOrders());

        function debounceLoad() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadOrders(), 400);
        }

        function toggleMyOnly() {
            myOnly = !myOnly;
            const btn = document.getElementById('myOnlyBtn');
            btn.classList.toggle('active', myOnly);
            btn.textContent = myOnly ? '👤 Của tôi' : '🌐 Tất cả';
            loadOrders();
        }

        function loadOrders() {
            const search = document.getElementById('soSearch').value.trim();
            const status = document.getElementById('soStatus').value;
            const year = document.getElementById('soYear').value;
            const month = document.getElementById('soMonth').value;

            const params = new URLSearchParams({
                search,
                status,
                year,
                month,
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
                    if (data.success) renderOrders(data.data, data.total);
                    else showError(data.error);
                })
                .catch(e => showError(e.message));
        }

        function groupLabel(key) {
            if (!key || key === '—') return 'Không rõ ngày';
            const [y, m] = key.split('-');
            return `Tháng ${parseInt(m, 10)}/${y}`;
        }

        function renderOrders(orders, total) {
            const tbody = document.getElementById('soBody');

            document.getElementById('showCount').textContent = orders.length;
            document.getElementById('totalCount').textContent = total;

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

            // Group by YYYY-MM, preserving the (date DESC) order
            const groups = [];
            const idxMap = {};
            orders.forEach(o => {
                const key = (o.date_order || '').slice(0, 7) || '—';
                if (idxMap[key] === undefined) {
                    idxMap[key] = groups.length;
                    groups.push({ key, items: [] });
                }
                groups[idxMap[key]].items.push(o);
            });

            let html = '';
            let counter = 0;

            groups.forEach(g => {
                const isCollapsed = !!collapsed[g.key];

                // Subtotal per currency (avoid mixing different currencies)
                const totals = {};
                g.items.forEach(o => {
                    const cur = Array.isArray(o.currency_id) ? o.currency_id[1] : (o.currency_id || '');
                    const amt = typeof o.amount_total === 'number' ? o.amount_total : 0;
                    totals[cur] = (totals[cur] || 0) + amt;
                });
                const totalStr = Object.entries(totals)
                    .map(([cur, v]) => `${new Intl.NumberFormat('vi-VN').format(v)} ${esc(cur)}`)
                    .join(' · ') || '—';

                html += `<tr class="group-row${isCollapsed ? ' collapsed' : ''}" data-key="${esc(g.key)}">
                    <td colspan="11">
                        <div class="group-header" onclick="toggleGroup('${esc(g.key)}')">
                            <span class="group-caret">▼</span>
                            <span class="group-title">${groupLabel(g.key)}</span>
                            <span class="group-count">${g.items.length} SO</span>
                            <span class="group-total"><span>Tổng:</span> ${totalStr}</span>
                        </div>
                    </td>
                </tr>`;

                g.items.forEach(o => {
                    counter++;
                    const st = STATUS_MAP[o.state] ?? { label: o.state, cls: 'so-draft' };
                    const partner = Array.isArray(o.partner_id) ? o.partner_id[1] : '—';
                    const currency = Array.isArray(o.currency_id) ? o.currency_id[1] : (o.currency_id || '');
                    const team = Array.isArray(o.team_id) ? o.team_id[1] : (o.team_id || '—');
                    const salesperson = Array.isArray(o.user_id) ? o.user_id[1] : (o.user_id || '—');
                    const dateStr = o.date_order ? o.date_order.slice(0, 10) : '—';
                    const amount = typeof o.amount_total === 'number'
                        ? new Intl.NumberFormat('vi-VN').format(o.amount_total)
                        : '—';
                    const ref = esc(o.client_order_ref || '—');
                    const hidden = isCollapsed ? ' style="display:none"' : '';

                    html += `<tr data-group="${esc(g.key)}"${hidden}>
                    <td class="col-stt">${counter}</td>
                    <td><a class="so-link" href="${ODOO_URL}/odoo/sales/${o.id}" target="_blank">${esc(o.name)}</a></td>
                    <td title="${esc(partner)}">${esc(partner)}</td>
                    <td>${dateStr}</td>
                    <td title="${esc(salesperson)}">${esc(salesperson)}</td>
                    <td title="${esc(team)}">${esc(team)}</td>
                    <td></td>
                    <td style="text-align:right;font-weight:600">${amount}</td>
                    <td>${esc(currency)}</td>
                    <td><span class="so-status ${st.cls}">${st.label}</span></td>
                    <td title="${ref}">${ref}</td>
                </tr>`;
                });
            });

            tbody.innerHTML = html;
        }

        function toggleGroup(key) {
            collapsed[key] = !collapsed[key];
            const groupRow = document.querySelector(`tr.group-row[data-key="${key}"]`);
            if (groupRow) groupRow.classList.toggle('collapsed', collapsed[key]);
            document.querySelectorAll(`tr[data-group="${key}"]`).forEach(r => {
                r.style.display = collapsed[key] ? 'none' : '';
            });
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
