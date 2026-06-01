<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Check permission
if (empty($_SESSION['can_view_invoice'])) {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;

// Initialize Odoo API
try {
    $odoo = new OdooAPI();

    // Get parameters
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    // Default grouping to 'month' if not specified
    $groupBy = isset($_GET['groupby']) ? $_GET['groupby'] : 'month';

    $limit = 20;
    // Show all records for Quarter/Year grouping as requested
    if ($groupBy === 'quarter' || $groupBy === 'year') {
        $limit = 5000;
    }

    $offset = ($page - 1) * $limit;

    $filters = [
        'search' => $search,
        'status' => $status
    ];

    // Enforce "My Invoices" for ALL users (including admins)
    // Fetch email dynamically from DB if not in session to avoid forcing relog
    $u_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $filters['owner_email'] = $row['email'];
    }

    // Get ALL matching invoices for calculations (totals per group/month)
    $fullResult = $odoo->getInvoices(10000, 0, $filters);
    $allInvoices = $fullResult['invoices'] ?? [];
    $total = $fullResult['total'] ?? 0;

    // Get only the paginated slice for actual display
    $invoices = array_slice($allInvoices, $offset, $limit);

    $totalPages = ceil($total / $limit);

} catch (Exception $e) {
    $error = $e->getMessage();
    $invoices = [];
    $total = 0;
    $totalPages = 0;
}

// Fetch current user's sales teams
$userTeams = [];
$u_id = $_SESSION['user_id'];
$team_res = $conn->query("SELECT st.id, st.name 
                         FROM user_sale_teams ust 
                         JOIN sale_teams st ON ust.team_id = st.id 
                         WHERE ust.user_id = $u_id");
if ($team_res) {
    while ($tr = $team_res->fetch_assoc()) {
        $userTeams[] = $tr;
    }
}

// Helper for formatting currency
function formatMoney($amount, $currency_id)
{
    // currency_id is [id, name] e.g. [1, "VND"] or [2, "USD"]
    $currency = is_array($currency_id) ? $currency_id[1] : $currency_id;
    return number_format($amount, 2) . ' ' . $currency;
}

function formatDate($date)
{
    return date('d/m/Y', strtotime($date));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - Odoo Integration</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .invoice-container {
            padding: 1rem;
            max-width: 100%;
            margin: 0;
        }

        .controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            width: 300px;
        }

        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            margin-left: 0.5rem;
            font-size: 14px;
        }

        .btn-refresh {
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-refresh:hover {
            border-color: #cbd5e1;
            color: #0f172a;
        }

        /* Google Sheets Style Table */
        .table-card {
            background: white;
            border: 1px solid #c0c0c0;
            border-radius: 0;
            /* No radius */
            box-shadow: none;
            overflow: auto;
            /* Allow scroll */
            max-height: calc(100vh - 200px);
            /* Fill screen */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            /* Google Sheets font */
            font-size: 13px;
            /* Slightly smaller */
            color: #000;
        }

        th {
            background: #f8f9fa;
            /* Light grey header */
            color: #5f6368;
            font-weight: bold;
            text-align: left;
            padding: 4px 8px;
            /* Dense padding */
            border: 1px solid #e0e0e0;
            white-space: nowrap;
            height: 30px;
        }

        td {
            padding: 4px 8px;
            /* Dense padding */
            border: 1px solid #e0e0e0;
            /* Visible grid lines */
            color: #202124;
            white-space: nowrap;
            /* Keep single line usually */
            height: 25px;
            vertical-align: middle;
        }

        tr:hover td {
            background-color: #f1f3f4;
            /* Subtle hover */
        }

        /* Specific column styles */
        .amount {
            font-family: 'Inconsolata', monospace;
            text-align: right;
            font-weight: normal;
        }

        /* Overwrite badges for sheet look */
        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: normal;
            border: 1px solid transparent;
        }

        .badge-posted {
            background: #e6f4ea;
            color: #137333;
            border-color: #ceead6;
        }

        .badge-draft {
            background: #f1f3f4;
            color: #5f6368;
            border-color: #dadce0;
        }

        .badge-cancel {
            background: #fce8e6;
            color: #c5221f;
            border-color: #fad2cf;
        }

        .badge-paid {
            background: #e6f4ea;
            color: #137333;
            border-color: #ceead6;
        }

        .badge-not_paid {
            background: #fce8e6;
            color: #c5221f;
            border-color: #fad2cf;
        }

        .badge-in_payment {
            background: #e8f0fe;
            color: #1967d2;
            border-color: #d2e3fc;
        }

        /* Group Header Style */
        .group-header td {
            background-color: #e8f0fe;
            font-weight: bold;
            color: #1a73e8;
            border-top: 2px solid #aecbfa;
            border-bottom: 1px solid #aecbfa;
            padding-top: 8px;
            padding-bottom: 8px;
        }

        /* Invoice Number Link Style */
        .invoice-link {
            color: #1155cc;
            text-decoration: underline;
            cursor: pointer;
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.25rem;
            margin-top: 1.5rem;
            margin-bottom: 2rem;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 0.5rem;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            background: white;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-link:hover:not(.active):not(.disabled) {
            border-color: #cbd5e1;
            color: #0f172a;
            background-color: #f8fafc;
        }

        .page-link.active {
            background-color: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .page-link.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background-color: #f1f5f9;
            color: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php
            $page_title = 'Invoices';
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="invoice-container">
                <!-- ... (error omitted) ... -->

                <div class="controls-bar">
                    <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex: 1;">
                        <div class="search-box">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" name="search" placeholder="Search invoice #"
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <select name="status"
                            style="padding: 0.5rem; border-radius: 8px; border: 1px solid #e2e8f0; outline: none;"
                            onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Posted</option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>

                        <select name="groupby"
                            style="padding: 0.5rem; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; color: #475569;"
                            onchange="this.form.submit()">
                            <option value="">No Grouping</option>
                            <option value="month" <?php echo $groupBy === 'month' ? 'selected' : ''; ?>>Group by Month
                            </option>
                            <option value="quarter" <?php echo $groupBy === 'quarter' ? 'selected' : ''; ?>>Group by
                                Quarter</option>
                            <option value="year" <?php echo $groupBy === 'year' ? 'selected' : ''; ?>>Group by Year
                            </option>
                        </select>
                    </form>

                    <button class="btn-refresh" onclick="refreshInvoices(this)">
                        <svg class="icon-refresh" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6"></path>
                            <path d="M1 20v-6h6"></path>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Sync from Odoo
                    </button>
                    <button class="btn-refresh" onclick="refreshRates(this)">
                        <svg class="icon-refresh" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        Sync Rates
                    </button>
                </div>

                <div class="table-card">
                    <!-- ... table ... -->
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th style="width: 60px; text-align: center;">Action</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Salesperson</th>
                                <th>Source</th>
                                <th>Total</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 3rem; color: #64748b;">
                                        No invoices found. Try syncing from Odoo.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                // Fetch existing debts to check for duplicates
                                $existingInvoiceNames = [];
                                $existingInvoiceIds = [];
                                try {
                                    if (isset($conn)) {
                                        // Chỉ tính là "đã add" khi debt có am_email — nếu thiếu email, AM vẫn có thể add lại
                                        $checkDebtSql = "SELECT vat_invoice, odoo_invoice_id, am_email FROM debts WHERE odoo_invoice_id IS NOT NULL";
                                        $debtRes = $conn->query($checkDebtSql);
                                        if ($debtRes) {
                                            while ($row = $debtRes->fetch_assoc()) {
                                                $hasEmail = !empty($row['am_email']);
                                                if (!empty($row['vat_invoice']) && $hasEmail) {
                                                    $existingInvoiceNames[] = $row['vat_invoice'];
                                                }
                                                if (!empty($row['odoo_invoice_id']) && $hasEmail) {
                                                    $existingInvoiceIds[] = $row['odoo_invoice_id'];
                                                }
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Ignore
                                }

                                // Pre-calculate GLOBALLY matching group totals (across all pages)
                                $allGroupInfo = [];
                                foreach ($allInvoices as $inv) {
                                    $date = $inv['date'] ?: $inv['invoice_date'];
                                    $timestamp = strtotime($date);

                                    if ($groupBy === 'month') {
                                        $key = date('F Y', $timestamp);
                                        $sortKey = date('Y-m', $timestamp);
                                    } elseif ($groupBy === 'quarter') {
                                        $quarter = ceil(date('n', $timestamp) / 3);
                                        $key = "Q{$quarter} " . date('Y', $timestamp);
                                        $sortKey = date('Y', $timestamp) . $quarter;
                                    } elseif ($groupBy === 'year') {
                                        $key = date('Y', $timestamp);
                                        $sortKey = $key;
                                    } else {
                                        $key = 'All';
                                        $sortKey = 0;
                                    }

                                    // Get VND for total
                                    $amountVnd = isset($inv['amount_total_signed']) ? (float) $inv['amount_total_signed'] : 0;
                                    if ($amountVnd == 0 && $inv['amount_total'] > 0) {
                                        $currencyCode = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';
                                        $invoiceDate = $inv['date'] ?: $inv['invoice_date'];
                                        $rateSource = $odoo->getRate($currencyCode, $invoiceDate) ?: 1.0;
                                        $rateVnd = $odoo->getRate('VND', $invoiceDate) ?: 1.0;
                                        $amountVnd = $inv['amount_total'] * ($rateVnd / $rateSource);
                                    }

                                    if (!isset($allGroupInfo[$sortKey])) {
                                        $allGroupInfo[$sortKey] = ['label' => $key, 'total_vnd' => 0, 'count' => 0];
                                    }
                                    $allGroupInfo[$sortKey]['total_vnd'] += $amountVnd;
                                    $allGroupInfo[$sortKey]['count']++;
                                }

                                // Now prepare labels and totals for current page rendering
                                $groupedInvoices = [];
                                if ($groupBy) {
                                    foreach ($invoices as $inv) {
                                        $date = $inv['date'] ?: $inv['invoice_date'];
                                        $timestamp = strtotime($date);
                                        if ($groupBy === 'month')
                                            $sortKey = date('Y-m', $timestamp);
                                        elseif ($groupBy === 'quarter')
                                            $sortKey = date('Y', $timestamp) . ceil(date('n', $timestamp) / 3);
                                        elseif ($groupBy === 'year')
                                            $sortKey = date('Y', $timestamp);
                                        else
                                            $sortKey = 0;

                                        $groupedInvoices[$sortKey]['label'] = $allGroupInfo[$sortKey]['label'];
                                        $groupedInvoices[$sortKey]['items'][] = $inv;
                                        // Set GLOBAL totals from the pre-calculation
                                        $groupedInvoices[$sortKey]['total_vnd'] = $allGroupInfo[$sortKey]['total_vnd'];
                                        $groupedInvoices[$sortKey]['global_count'] = $allGroupInfo[$sortKey]['count'];
                                    }
                                    krsort($groupedInvoices);
                                } else {
                                    $groupedInvoices['all']['items'] = $invoices;
                                    $groupedInvoices['all']['label'] = 'Summary';
                                    $groupedInvoices['all']['total_vnd'] = $allGroupInfo[0]['total_vnd'] ?? 0;
                                    $groupedInvoices['all']['global_count'] = $total;
                                }
                                ?>

                                <?php foreach ($groupedInvoices as $groupKey => $group): ?>
                                    <?php if ($groupBy): ?>
                                        <tr class="group-header">
                                            <td colspan="10">
                                                <?php echo $group['label']; ?>
                                                <span
                                                    style="font-weight: normal; font-size: 0.9em; color: #5f6368; margin-left: 0.5rem;">
                                                    (Showing <?php echo count($group['items']); ?> of <?php echo $group['global_count']; ?>)
                                                </span>
                                                <span style="float: right; margin-right: 2rem; color: #333;">
                                                    Total: <strong>
                                                        <?php
                                                        echo formatMoney($group['total_vnd'] ?? 0, 'VND');
                                                        ?>
                                                    </strong>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($group['items'] as $inv): ?>
                                        <tr>
                                            <td class="invoice-link">
                                                <?php echo htmlspecialchars($inv['name'] ?: 'Draft Invoice'); ?>
                                                <?php if ($inv['ref']): ?>
                                                    <span
                                                        style="color: #666; font-size: 11px;">(<?php echo htmlspecialchars($inv['ref']); ?>)</span>
                                                  
                                                <?php endif; ?>
                                    (<?php echo $inv['id']; ?>)
                                            </td>
                                            <td style="text-align: center;">
                                                <?php
                                                // Calculate Payment Date
                                                $paymentDate = $inv['write_date'] ?? '';
                                                if (isset($inv['invoice_payments_widget'])) {
                                                    $widget = $inv['invoice_payments_widget'];
                                                    if (is_string($widget))
                                                        $widget = json_decode($widget, true);
                                                    if (!empty($widget['content'])) {
                                                        $dates = array_column($widget['content'], 'date');
                                                        if ($dates)
                                                            $paymentDate = max($dates);
                                                    }
                                                }
                                                ?>
                                                <?php
                                                $isAlreadyAdded = in_array($inv['id'], $existingInvoiceIds);
                                                if ($isAlreadyAdded):
                                                    ?>
                                                    <button class="btn-icon" title="Already added to My Debts" disabled
                                                        style="background:none; border:none; cursor:default; color:#16a34a; padding: 4px;">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2">
                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                        </svg>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="addToDebts(this)"
                                                        data-name="<?php echo htmlspecialchars($inv['name'] ?: 'Draft Invoice'); ?>"
                                                        data-amount="<?php echo $inv['amount_total']; ?>"
                                                        data-currency="<?php echo is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND'; ?>"
                                                        data-desc="<?php echo htmlspecialchars(($inv['name'] ?: 'Draft Invoice') . ' - ' . (is_array($inv['partner_id']) ? $inv['partner_id'][1] : '')); ?>"
                                                        data-payment-state="<?php echo $inv['payment_state']; ?>"
                                                        data-write-date="<?php echo htmlspecialchars($paymentDate); ?>"
                                                        data-ref="<?php echo htmlspecialchars($inv['x_studio_project_code'] ?: ($inv['x_studio_project_code_0'] ?? '')); ?>"
                                                        data-invoice-date="<?php echo $inv['invoice_date'] ?: $inv['date']; ?>"
                                                        data-odoo-id="<?php echo $inv['id']; ?>"
                                                        style="background:none; border:none; cursor:pointer; color:#2563eb; padding: 4px; border-radius: 4px;"
                                                        onmouseover="this.style.backgroundColor='#eff6ff'"
                                                        onmouseout="this.style.backgroundColor='transparent'" title="Add to My Debts">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2">
                                                            <path d="M12 5v14M5 12h14" />
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                echo htmlspecialchars(is_array($inv['partner_id']) ? $inv['partner_id'][1] : '');
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo formatDate($inv['invoice_date'] ?: $inv['date']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                echo htmlspecialchars(is_array($inv['invoice_user_id']) ? $inv['invoice_user_id'][1] : '');
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($inv['invoice_origin'] ?? ''); ?>
                                            </td>

                                            <td class="amount">
                                                <?php echo formatMoney($inv['amount_total'], $inv['currency_id']); ?>
                                            </td>
                                            <td class="amount" style="color: #ef4444;">
                                                <?php echo formatMoney($inv['amount_residual'], $inv['currency_id']); ?>
                                            </td>

                                            <td>
                                                <span class="badge badge-<?php echo $inv['state']; ?>">
                                                    <?php echo ucfirst($inv['state']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $inv['payment_state']; ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($inv['payment_state'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $queryParams = "&search=" . urlencode($search) . "&status=" . urlencode($status) . "&groupby=" . urlencode($groupBy);

                            // Previous Button
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $queryParams . '" class="page-link" title="Previous">&lsaquo;</a>';
                            } else {
                                echo '<span class="page-link disabled">&lsaquo;</span>';
                            }

                            $lastPrinted = 0;
                            // Number of pages to show around the current page
                            $range = 2; // Show 2 pages before and after current
                        
                            for ($i = 1; $i <= $totalPages; $i++) {
                                // Logic: Always show first, last, and pages within range of current
                                $shouldPrint = ($i == 1) || ($i == $totalPages) || ($i >= $page - $range && $i <= $page + $range);

                                if ($shouldPrint) {
                                    // If we skipped some pages, print dots
                                    if ($lastPrinted > 0 && $i > $lastPrinted + 1) {
                                        echo '<span class="page-link disabled" style="border:none; background:none;">...</span>';
                                    }

                                    $active = ($i == $page) ? 'active' : '';
                                    echo '<a href="?page=' . $i . $queryParams . '" class="page-link ' . $active . '">' . $i . '</a>';
                                    $lastPrinted = $i;
                                }
                            }

                            // Next Button
                            if ($page < $totalPages) {
                                echo '<a href="?page=' . ($page + 1) . $queryParams . '" class="page-link" title="Next">&rsaquo;</a>';
                            } else {
                                echo '<span class="page-link disabled">&rsaquo;</span>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Team Selection Modal -->
    <div id="teamSelectModal"
        style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div
            style="background: white; padding: 2rem; border-radius: 12px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; color: #0f172a;">Select Sale Team</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">You belong to multiple teams. Please
                select which team this debt should be assigned to:</p>
            <select id="modalTeamSelect"
                style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 1.5rem; outline: none;">
                <?php foreach ($userTeams as $team): ?>
                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button onclick="closeTeamModal()"
                    style="padding: 0.6rem 1.2rem; background: #f1f5f9; border: none; border-radius: 6px; color: #475569; cursor: pointer; font-weight: 500;">Cancel</button>
                <button onclick="confirmTeamSelection()"
                    style="padding: 0.6rem 1.2rem; background: #2563eb; border: none; border-radius: 6px; color: white; cursor: pointer; font-weight: 600;">Push
                    to Debt</button>
            </div>
        </div>
    </div>

    <script>
        const userTeams = <?php echo json_encode($userTeams); ?>;
        let pendingBtn = null;

        function refreshInvoices(btn) {
            const icon = btn.querySelector('.icon-refresh');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            icon.classList.add('spinner');
            btn.innerHTML = '<svg class="icon-refresh spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> Syncing...';

            fetch('/api/refresh_odoo_invoices.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Failed to sync: ' + (data.error || 'Unknown error'));
                        resetBtn();
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    resetBtn();
                });

            function resetBtn() {
                btn.disabled = false;
                icon.classList.remove('spinner');
                btn.innerHTML = originalText;
            }
        }

        function refreshRates(btn) {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner" style="display:inline-block; border: 2px solid #ccc; border-top-color: #333; border-radius: 50%; width: 14px; height: 14px; animation: spin 1s linear infinite;"></span> Syncing...';

            fetch('/api/sync_rates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Rates synced successfully.');
                        window.location.reload();
                    } else {
                        alert('Failed to sync rates: ' + (data.error || 'Unknown error'));
                        resetBtn();
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    resetBtn();
                });

            function resetBtn() {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function addToDebts(btn) {
            if (userTeams.length > 1) {
                pendingBtn = btn;
                document.getElementById('teamSelectModal').style.display = 'flex';
            } else {
                const teamId = userTeams.length === 1 ? userTeams[0].id : '';
                submitPushToDebt(btn, teamId);
            }
        }

        function closeTeamModal() {
            document.getElementById('teamSelectModal').style.display = 'none';
            pendingBtn = null;
        }

        function confirmTeamSelection() {
            const teamId = document.getElementById('modalTeamSelect').value;
            if (pendingBtn) {
                submitPushToDebt(pendingBtn, teamId);
            }
            closeTeamModal();
        }

        function submitPushToDebt(btn, teamId) {
            const name = btn.getAttribute('data-name');
            const amount = btn.getAttribute('data-amount');
            const currency = btn.getAttribute('data-currency');
            const desc = btn.getAttribute('data-desc');
            const paymentState = btn.getAttribute('data-payment-state');
            const writeDate = btn.getAttribute('data-write-date');
            const ref = btn.getAttribute('data-ref');
            const invoiceDate = btn.getAttribute('data-invoice-date');
            const odooId = btn.getAttribute('data-odoo-id');

            const formData = new FormData();
            formData.append('invoice_name', name);
            formData.append('description', desc);
            formData.append('amount', amount);
            formData.append('currency', currency);
            formData.append('payment_state', paymentState);
            formData.append('write_date', writeDate);
            formData.append('project_code', ref);
            formData.append('invoice_date', invoiceDate);
            formData.append('odoo_invoice_id', odooId);
            formData.append('team_id', teamId);
            formData.append('status', 'Planning');

            btn.disabled = true;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span style="display:inline-block; width:14px; height:14px; border:2px solid #ccc; border-top-color:#333; border-radius:50%; animation:spin 1s linear infinite;"></span>';

            fetch('/api/add_debt_from_invoice', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.disabled = true;
                        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                        btn.title = 'Added';
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = originalHTML;
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    alert('Error: ' + err.message);
                });
        }
    </script>
</body>

</html>
