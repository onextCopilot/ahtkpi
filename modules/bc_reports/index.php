<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../config/config.php';

// Check role if it should be restricted to admin?
if ($_SESSION['role'] !== 'admin') {
    header('Location: /dashboard');
    exit;
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
        .tabs-container { margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 0.5rem; padding-bottom: 0.5rem; }
        .tab-button { padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 500; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .tab-button:hover { background: #f8fafc; color: #0f172a; }
        .tab-button.active { background: #3b82f6; color: white; border-color: #3b82f6; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
                        <label>BC Filter</label>
                        <input type="text" id="filter-bc" placeholder="E.g. BC1, BC ITO">
                    </div>
                    <div>
                        <button class="btn-filter" onclick="fetchData()">Filter / Fetch Data</button>
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
            const bc = document.getElementById('filter-bc').value;

            try {
                const url = `/api/bc_reports.php?year=${year}&quarter=${quarter}&month=${month}&bc=${encodeURIComponent(bc)}`;
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
            
            // Render Tab Buttons
            data.forEach((group, idx) => {
                const activeClass = idx === 0 ? 'active' : '';
                html += `<button id="tab-btn-${idx}" class="tab-button ${activeClass}" onclick="switchTab(${idx})">${group.branch}</button>`;
            });
            html += '</div>';

            // Render Tab Contents
            data.forEach((group, idx) => {
                const activeClass = idx === 0 ? 'active' : '';
                html += `
                <div id="tab-content-${idx}" class="tab-content ${activeClass}">
                    <div class="bc-group">
                        <div class="bc-header">
                            <div class="bc-title">${group.branch}</div>
                            <div class="bc-total">Total: ${formatMoney(group.totalVnd)}</div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>State</th>
                                        <th style="text-align: right;">Amount (VND)</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                group.invoices.forEach((inv, i) => {
                    const badgeClass = inv.state === 'posted' ? 'badge-posted' : 'badge-draft';
                    const invType = inv.type ? String(inv.type) : '';
                    const typeBadge = invType.toLowerCase().includes('internal') ? `<span class="badge" style="background:#fef08a; color:#854d0e;">${invType}</span>` : `<span style="color:#64748b;">${invType}</span>`;
                    html += `
                        <tr>
                            <td>${i + 1}</td>
                            <td style="font-weight: 500;">${inv.name || 'Draft'}</td>
                            <td>${inv.customer}</td>
                            <td>${inv.date}</td>
                            <td>${typeBadge}</td>
                            <td><span class="badge ${badgeClass}">${inv.state}</span></td>
                            <td style="text-align: right; font-weight: 500;">${formatMoney(inv.amount_total_signed)}</td>
                        </tr>
                    `;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>`;
            });

            container.innerHTML = html;
        }

        // Fetch on initial load
        document.addEventListener('DOMContentLoaded', () => {
            fetchData();
        });
    </script>
</body>

</html>