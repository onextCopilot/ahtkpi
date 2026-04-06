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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
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

        .report-content {
            padding: 2rem;
            overflow-y: auto;
            background-color: #f8fafc;
            flex: 1;
        }

        .filter-panel {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #475569;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            min-width: 150px;
        }

        .btn-filter {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: 0.2s;
        }

        .btn-filter:hover {
            background: #2563eb;
        }

        .bc-group {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .bc-header {
            background: #f1f5f9;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bc-title {
            font-weight: 700;
            font-size: 1.125rem;
            color: #0f172a;
        }

        .bc-total {
            font-weight: 700;
            color: #0ea5e9;
            font-size: 1.25rem;
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
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 0.5rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
            font-size: 0.875rem;
        }

        tr:hover td {
            background-color: #f8fafc;
        }

        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-posted {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-draft {
            background-color: #f1f5f9;
            color: #475569;
        }

        #loading {
            text-align: center;
            padding: 3rem;
            color: #64748b;
            display: none;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
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
            margin-bottom: 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .tab-button {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            border: 1px solid #cbd5e1;
            background: white;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }

        .tab-button:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .tab-button.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/topbar.php'; ?>

            <div class="report-content">
                <div class="filter-panel">
                    <div class="filter-group">
                        <label>Year</label>
                        <select id="filter-year">
                            <?php
                            $curr = date('Y');
                            for ($i = $curr + 1; $i >= $curr - 2; $i--) {
                                echo "<option value='$i' " . ($i == $curr ? 'selected' : '') . ">$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Quarter</label>
                        <select id="filter-quarter">
                            <option value="0">All</option>
                            <option value="1">Q1</option>
                            <option value="2">Q2</option>
                            <option value="3">Q3</option>
                            <option value="4">Q4</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Month</label>
                        <select id="filter-month">
                            <option value="0">All</option>
                            <?php for ($i = 1; $i <= 12; $i++)
                                echo "<option value='$i'>Tháng $i</option>"; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Payment State</label>
                        <select id="filter-payment">
                            <option value="all">All</option>
                            <option value="paid">Paid</option>
                            <option value="not_paid">Not Paid</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>BC Filter</label>
                        <input type="text" id="filter-bc" placeholder="E.g. BC1, BC ITO">
                    </div>
                    <div>
                        <button class="btn-filter" onclick="fetchData()">Filter / Fetch Data</button>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <button style="background: transparent; border: none; cursor: pointer; font-size: 1.5rem; margin-left: 0.5rem; transition: transform 0.2s;"
                                onclick="openSettings()" title="Tab Settings" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">⚙️</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="loading">
                    <div class="spinner"></div>
                    <p>Fetching invoices and branches from Odoo... This might take a few seconds.</p>
                </div>

                <div id="results-container">
                    <!-- Results injected here -->
                </div>
            </div>
        </main>
    </div>

    <!-- Settings Modal (Admin Only) -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div id="settingsModal"
            style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100; justify-content:center; align-items:center;">
            <div
                style="background:white; padding:2rem; border-radius:8px; width:90%; max-width:600px; max-height:80vh; overflow-y:auto; position:relative;">
                <button onclick="closeSettings()"
                    style="position:absolute; top:10px; right:15px; background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
                <h2 style="font-size:1.5rem; font-weight:700; margin-bottom:1rem;">BC Access Settings</h2>
                <div id="settings-loading" style="text-align:center; padding:2rem;">
                    <div class="spinner"></div>
                    <p>Loading Users & BCs...</p>
                </div>
                <div id="settings-content" style="display:none;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f1f5f9;">
                                <th style="padding:0.5rem; border:1px solid #e2e8f0;">User</th>
                                <th style="padding:0.5rem; border:1px solid #e2e8f0;">Allowed BCs</th>
                            </tr>
                        </thead>
                        <tbody id="settings-tbody">
                        </tbody>
                    </table>
                    <div style="margin-top:1.5rem; text-align:right;">
                        <button class="btn-filter" onclick="saveSettings()">Save Settings</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function formatMoney(amount) {
            return new Intl.NumberFormat('en-US').format(Math.round(amount)) + ' VND';
        }

        async function fetchData() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('results-container').innerHTML = '';

            const year = document.getElementById('filter-year').value;
            const quarter = document.getElementById('filter-quarter').value;
            const month = document.getElementById('filter-month').value;
            const payment = document.getElementById('filter-payment').value;
            const bc = document.getElementById('filter-bc').value;

            try {
                const url = `/api/bc_reports.php?year=${year}&quarter=${quarter}&month=${month}&payment_state=${payment}&bc=${encodeURIComponent(bc)}`;
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
            if (data.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding: 2rem; background: white; border-radius: 8px;">No data found for the selected filters.</div>';
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
                        const paymentClass = (inv.payment_state === 'paid' || inv.payment_state === 'in_payment') ? 'badge-posted' : 'badge-draft';
                        const paymentText = (inv.payment_state === 'paid' || inv.payment_state === 'in_payment') ? 'Paid' : 'Not Paid';
                        const invType = inv.type ? String(inv.type) : '';
                        const typeBadge = invType.toLowerCase().includes('internal') ? `<span class="badge" style="background:#fef08a; color:#854d0e;">${invType}</span>` : `<span style="color:#64748b;">${invType}</span>`;
                        
                        rowsHtml += `
                            <tr>
                                <td>${i + 1}</td>
                                <td style="font-weight: 500;">${inv.name || 'Draft'}</td>
                                <td>${inv.customer}</td>
                                <td>${inv.date}</td>
                                <td>${typeBadge}</td>
                                <td><span class="badge ${badgeClass}">${inv.state}</span></td>
                                <td><span class="badge ${paymentClass}">${paymentText}</span></td>
                                <td style="text-align: right; font-weight: 500;">${formatMoney(inv.amount_total_signed)}</td>
                            </tr>
                        `;
                    });

                    monthsHtml += `
                    <div class="bc-month-block" style="margin-bottom: 2.5rem;">
                        <div style="background: #f1f5f9; padding: 0.75rem 1.25rem; border-left: 4px solid #3b82f6; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0px; border-radius: 4px 4px 0 0;">
                            <div style="font-weight: 700; color: #1e293b; font-size: 1.05rem;">${mGroup.label}</div>
                            <div style="font-weight: 700; color: #0ea5e9;">Total: ${formatMoney(mGroup.totalVnd)}</div>
                        </div>
                        <div class="table-wrap" style="background: white; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 4px 4px;">
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
                                        <th style="text-align: right;">Amount (VND)</th>
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
                    <div class="bc-group" style="box-shadow: none; background: transparent; margin-bottom: 0;">
                        <div class="bc-header" style="background: white; padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 1.5rem;">
                            <div class="bc-title" style="font-size: 1.5rem;">${group.branch} Overview</div>
                            <div class="bc-total" style="font-size: 1.5rem;">Year Total: ${formatMoney(group.totalVnd)}</div>
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
                    optionsHtml += `<label style="display:inline-block; margin-right:10px; font-size:12px; white-space:nowrap; background:#f8fafc; padding:3px 6px; border-radius:4px; border:1px solid #e2e8f0;"><input type="checkbox" class="perm-chk" data-userid="${user.id}" value="${bc}" ${hasPerm ? 'checked' : ''}> ${bc}</label>`;
                });

                html += `
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:0.75rem; vertical-align:top; font-weight:600;">${user.full_name}<br><small style="color:#64748b; font-weight:400;">(${user.username})</small></td>
                    <td style="padding:0.75rem;">${optionsHtml}</td>
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
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                console.error(e);
                alert('Network error saving settings.');
            }
        }

        // Fetch on initial load
        document.addEventListener('DOMContentLoaded', () => {
            fetchData();
        });
    </script>
</body>

</html>