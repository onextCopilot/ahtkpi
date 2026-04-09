<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/config.php';

// Check role and permissions
if ($_SESSION['role'] !== 'admin') {
    $has_bc_access = false;
    $bc_chk_stmt = $conn->prepare("SELECT COUNT(*) as count FROM bc_permissions WHERE user_id = ?");
    if ($bc_chk_stmt) {
        $bc_chk_stmt->bind_param("i", $_SESSION['user_id']);
        $bc_chk_stmt->execute();
        $bc_res = $bc_chk_stmt->get_result();
        if ($bc_row = $bc_res->fetch_assoc()) {
            $has_bc_access = $bc_row['count'] > 0;
        }
        $bc_chk_stmt->close();
    }

    if (!$has_bc_access) {
        header('Location: /dashboard');
        exit;
    }
}

$page_title = 'BC Reports';

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BC Reports</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .report-layout {
            display: flex;
            gap: 2rem;
            padding: 2rem;
            flex: 1;
            overflow: hidden;
        }

        .filter-sidebar {
            width: 320px;
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 2rem;
            border: 1px solid #e2e8f0;
        }

        .results-main {
            flex: 1;
            overflow-y: auto;
            min-width: 0;
            /* Prevent flex items from overflowing */
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.8125rem;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Tom Select Overrides */
        .ts-control {
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            border-color: #e2e8f0;
            box-shadow: none;
        }

        .ts-wrapper.focus .ts-control {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-filter {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-filter:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-filter:active {
            transform: translateY(0);
        }

        .bc-group {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .bc-header {
            background: #f8fafc;
            padding: 1.25rem 1.75rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bc-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: #0f172a;
        }

        .bc-total {
            font-weight: 700;
            color: #0ea5e9;
            font-size: 1.375rem;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8fafc;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            font-size: 0.875rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: #f8fafc;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-posted {
            background-color: #dcfce7;
            color: #15803d;
        }

        .badge-draft {
            background-color: #f1f5f9;
            color: #475569;
        }

        #loading {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            color: #64748b;
            display: none;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.25rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Tab Styles */
        .tabs-container {
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            background: white;
            padding: 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .tab-button {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            background: transparent;
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .tab-button:hover {
            color: #0f172a;
            background: #f1f5f9;
        }

        .tab-button.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .sidebar-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .settings-btn {
            color: #94a3b8;
            transition: all 0.2s;
            padding: 0.5rem;
            border-radius: 0.375rem;
        }

        .settings-btn:hover {
            color: #1e293b;
            background: #f1f5f9;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/topbar.php'; ?>

            <div class="report-layout">
                <aside class="filter-sidebar">
                    <div class="sidebar-header-flex">
                        <h2 style="font-weight: 700; color: #0f172a; font-size: 1.125rem;">Filters</h2>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <button class="settings-btn" onclick="openSettings()" title="BC Access Settings">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" id="filter-search" placeholder="Invoice #, Customer..." style="padding: 0.625rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.875rem; outline: none;" onkeyup="if(event.key === 'Enter') fetchData()">
                    </div>

                    <div class="filter-group">
                        <label>Years</label>
                        <select id="filter-year" multiple placeholder="Select Years">
                            <?php
                            $curr = date('Y');
                            for ($i = $curr + 1; $i >= $curr - 2; $i--) {
                                echo "<option value='$i' " . ($i == $curr ? 'selected' : '') . ">$i</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Quarters</label>
                        <select id="filter-quarter" multiple placeholder="All Quarters">
                            <option value="1">Q1</option>
                            <option value="2">Q2</option>
                            <option value="3">Q3</option>
                            <option value="4">Q4</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Months</label>
                        <select id="filter-month" multiple placeholder="All Months">
                            <?php for ($i = 1; $i <= 12; $i++)
                                echo "<option value='$i'>Tháng $i</option>"; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Branches (BC)</label>
                        <select id="filter-bc" multiple placeholder="All BCs">
                            <!-- Populated via JS -->
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Payment State</label>
                        <select id="filter-payment" multiple placeholder="All States">
                            <option value="paid">Paid</option>
                            <option value="not_paid">Not Paid</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Invoice Status</label>
                        <select id="filter-status" multiple placeholder="All Status">
                            <option value="posted">Posted</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>

                    <button class="btn-filter" onclick="fetchData()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                        </svg>
                        Apply Filters
                    </button>
                </aside>

                <div class="results-main">
                    <div id="loading">
                        <div class="spinner"></div>
                        <p style="font-weight: 500;">Fetching data from Odoo...</p>
                        <p style="font-size: 0.8125rem; margin-top: 0.5rem; opacity: 0.8;">This might take a few seconds.</p>
                    </div>

                    <div id="results-container">
                        <!-- Results injected here -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Settings Modal (Admin Only) -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div id="settingsModal"
            style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:100; justify-content:center; align-items:center;">
            <div
                style="background:white; padding:2rem; border-radius:1rem; width:90%; max-width:800px; max-height:85vh; overflow-y:auto; position:relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <button onclick="closeSettings()"
                    style="position:absolute; top:1.25rem; right:1.5rem; color: #94a3b8; transition: color 0.2s; background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
                <h2 style="font-size:1.5rem; font-weight:800; color: #0f172a; margin-bottom:1.5rem;">BC Access Settings</h2>
                <div id="settings-loading" style="text-align:center; padding:3rem;">
                    <div class="spinner"></div>
                    <p style="color: #64748b; font-weight: 500;">Loading configurations...</p>
                </div>
                <div id="settings-content" style="display:none;">
                    <div class="table-wrap" style="border: 1px solid #e2e8f0; border-radius: 0.75rem; overflow: hidden;">
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="padding:1rem; border-bottom:1px solid #e2e8f0;">User</th>
                                    <th style="padding:1rem; border-bottom:1px solid #e2e8f0;">Allowed BCs</th>
                                </tr>
                            </thead>
                            <tbody id="settings-tbody">
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:2rem; display: flex; justify-content: flex-end;">
                        <button class="btn-filter" style="width: auto; min-width: 160px;" onclick="saveSettings()">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Global variables for Tom Select instances
        let tsYear, tsQuarter, tsMonth, tsBc, tsPayment, tsStatus;

        function formatMoney(amount) {
            return new Intl.NumberFormat('en-US').format(Math.round(amount)) + ' VND';
        }

        async function fetchData() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('results-container').innerHTML = '';

            const years = tsYear.getValue().join(',');
            const quarters = tsQuarter.getValue().join(',');
            const months = tsMonth.getValue().join(',');
            const bcs = tsBc.getValue().join(',');
            const payments = tsPayment.getValue().join(',');
            const statuses = tsStatus.getValue().join(',');
            const search = document.getElementById('filter-search').value;

            try {
                const url = `/api/bc_reports.php?year=${years}&quarter=${quarters}&month=${months}&bc=${encodeURIComponent(bcs)}&payment_state=${payments}&state=${statuses}&search=${encodeURIComponent(search)}`;
                const response = await fetch(url);
                const result = await response.json();

                document.getElementById('loading').style.display = 'none';

                if (result.success) {
                    renderData(result.data);
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                alert('Network error');
                console.error(error);
            }
        }

        function switchTab(idx) {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            document.getElementById(`tab-btn-${idx}`).classList.add('active');
            document.getElementById(`tab-content-${idx}`).classList.add('active');
        }

        function renderData(data) {
            const container = document.getElementById('results-container');
            if (!data || data.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding: 4rem 2rem; background: white; border-radius: 1rem; border: 1px solid #e2e8f0; color: #64748b; font-weight: 500;">No data found for the selected filters.</div>';
                return;
            }

            let html = '<div class="tabs-container">';

            // Render Tab Buttons (Teams)
            data.forEach((group, idx) => {
                const activeClass = idx === 0 ? 'active' : '';
                html += `<button id="tab-btn-${idx}" class="tab-button ${activeClass}" onclick="switchTab(${idx})">${group.branch}</button>`;
            });
            html += '</div>';

            // Render Tab Contents
            data.forEach((group, idx) => {
                const activeClass = idx === 0 ? 'active' : '';
                let monthsHtml = '';

                group.month_groups.forEach(mGroup => {
                    let rowsHtml = '';
                    mGroup.invoices.forEach((inv, i) => {
                        const badgeClass = inv.state === 'posted' ? 'badge-posted' : 'badge-draft';
                        const isPaid = (inv.payment_state === 'paid' || inv.payment_state === 'in_payment');
                        const paymentClass = isPaid ? 'badge-posted' : 'badge-draft';
                        const paymentText = isPaid ? 'Paid' : 'Not Paid';
                        const invType = inv.type ? String(inv.type) : '';
                        const typeBadge = invType.toLowerCase().includes('internal') ? `<span class="badge" style="background:#fef9c3; color:#854d0e;">${invType}</span>` : `<span style="color:#64748b; font-size: 0.75rem;">${invType}</span>`;

                        rowsHtml += `
                            <tr>
                                <td style="color: #94a3b8; width: 40px;">${i + 1}</td>
                                <td style="font-weight: 600; color: #0f172a;">${inv.name || 'Draft'}</td>
                                <td>${inv.customer}</td>
                                <td style="color: #64748b;">${inv.date}</td>
                                <td>${typeBadge}</td>
                                <td><span class="badge ${badgeClass}">${inv.state}</span></td>
                                <td><span class="badge ${paymentClass}">${paymentText}</span></td>
                                <td style="text-align: right; font-weight: 700; color: #0f172a;">${formatMoney(inv.amount_total_signed)}</td>
                            </tr>
                        `;
                    });

                    monthsHtml += `
                    <div class="bc-month-block" style="margin-bottom: 2rem;">
                        <div style="background: white; padding: 1rem 1.75rem; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; border-radius: 0.75rem 0.75rem 0 0; border-bottom: none;">
                            <div style="font-weight: 800; color: #1e293b; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 4px; height: 16px; background: #3b82f6; border-radius: 2px;"></div>
                                ${mGroup.label}
                            </div>
                            <div style="font-weight: 700; color: #0ea5e9; font-size: 1rem;">Total: <span style="color:#0f172a">${formatMoney(mGroup.totalVnd)}</span></div>
                        </div>
                        <div class="table-wrap" style="background: white; border: 1px solid #e2e8f0; border-radius: 0 0 0.75rem 0.75rem;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th style="text-align: right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                });

                html += `
                <div id="tab-content-${idx}" class="tab-content ${activeClass}">
                    <div class="bc-group" style="box-shadow: none; background: transparent; margin-bottom: 0; border: none;">
                        <div class="bc-header" style="background: white; padding: 1.5rem 2rem; border: 1px solid #e2e8f0; border-radius: 1rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                            <div>
                                <h3 style="font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Branch Overview</h3>
                                <div class="bc-title" style="font-size: 1.75rem; font-weight: 800;">${group.branch}</div>
                            </div>
                            <div style="text-align: right;">
                                <h3 style="font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.25rem;">Year Total</h3>
                                <div class="bc-total" style="font-size: 1.75rem; font-weight: 800; color: #3b82f6;">${formatMoney(group.totalVnd)}</div>
                            </div>
                        </div>
                        ${monthsHtml}
                    </div>
                </div>`;
            });

            container.innerHTML = html;
        }

        let allUsers = [];
        let allBcs = [];
        let allPerms = [];

        async function openSettings() {
            document.getElementById('settingsModal').style.display = 'flex';
            document.getElementById('settings-loading').style.display = 'block';
            document.getElementById('settings-content').style.display = 'none';
            document.getElementById('settings-tbody').innerHTML = '';

            try {
                const response = await fetch('/api/bc_settings.php?action=get_settings');
                const result = await response.json();
                if (result.success) {
                    allUsers = result.users;
                    allBcs = result.bcs;
                    allPerms = result.permissions;

                    renderSettings();
                    document.getElementById('settings-loading').style.display = 'none';
                    document.getElementById('settings-content').style.display = 'block';
                } else {
                    alert('Error: ' + result.error);
                    closeSettings();
                }
            } catch (e) {
                console.error(e);
                alert('Network error accessing settings.');
                closeSettings();
            }
        }

        function closeSettings() {
            document.getElementById('settingsModal').style.display = 'none';
        }

        function renderSettings() {
            const tbody = document.getElementById('settings-tbody');
            let html = '';

            allUsers.forEach(user => {
                let optionsHtml = '';
                allBcs.forEach(bc => {
                    const hasPerm = allPerms.some(p => p.user_id == user.id && p.bc_name === bc);
                    optionsHtml += `<label style="display:inline-flex; align-items:center; gap:0.5rem; margin-right:1rem; margin-bottom:0.5rem; font-size:0.75rem; white-space:nowrap; background:#f8fafc; padding:0.4rem 0.75rem; border-radius:0.5rem; border:1px solid #e2e8f0; cursor:pointer;" class="hover:bg-slate-100 transition-colors"><input type="checkbox" class="perm-chk rounded text-blue-600" data-userid="${user.id}" value="${bc}" ${hasPerm ? 'checked' : ''}> ${bc}</label>`;
                });

                html += `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:1.25rem; vertical-align:top;">
                        <div style="font-weight:700; color: #1e293b;">${user.full_name}</div>
                        <div style="color:#64748b; font-size: 0.75rem;">@${user.username}</div>
                    </td>
                    <td style="padding:1.25rem;">${optionsHtml}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        async function saveSettings() {
            const checkboxes = document.querySelectorAll('.perm-chk:checked');
            let newPerms = [];
            checkboxes.forEach(chk => {
                newPerms.push({
                    user_id: chk.getAttribute('data-userid'),
                    bc_name: chk.value
                });
            });

            try {
                const response = await fetch('/api/bc_settings.php?action=save_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ permissions: newPerms })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Settings saved successfully!');
                    closeSettings();
                    // Refetch BCs for the filter just in case
                    loadBcs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                console.error(e);
                alert('Network error saving settings.');
            }
        }

        async function loadBcs() {
            try {
                const response = await fetch('/api/bc_settings.php?action=get_settings');
                const result = await response.json();
                if (result.success) {
                    tsBc.clearOptions();
                    result.bcs.forEach(bc => {
                        tsBc.addOption({ value: bc, text: bc });
                    });
                    tsBc.refreshOptions(false);
                }
            } catch (e) {
                console.error('Error loading BCs:', e);
            }
        }

        // Initialize Tom Select and fetch initial data
        document.addEventListener('DOMContentLoaded', async () => {
            const tsOptions = {
                plugins: ['remove_button'],
                maxItems: null,
                allowEmptyOption: true,
            };

            tsYear = new TomSelect('#filter-year', tsOptions);
            tsQuarter = new TomSelect('#filter-quarter', tsOptions);
            tsMonth = new TomSelect('#filter-month', tsOptions);
            tsBc = new TomSelect('#filter-bc', tsOptions);
            tsPayment = new TomSelect('#filter-payment', tsOptions);
            tsStatus = new TomSelect('#filter-status', tsOptions);

            // Load BC options
            await loadBcs();

            // Initial fetch
            fetchData();
        });
    </script>
</body>

</html>