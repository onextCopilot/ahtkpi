<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../libs/OdooAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$odoo = null;
$error_message = '';
$success_message = '';

try {
    $odoo = new OdooAPI();
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Handle Sync
if (isset($_POST['action']) && $_POST['action'] === 'sync') {
    try {
        if ($odoo) {
            $count = $odoo->refreshCurrencyRates();
            $success_message = "Successfully synced $count rate entries from Odoo.";
        }
    } catch (Exception $e) {
        $error_message = "Sync failed: " . $e->getMessage();
    }
}

// Fetch Rates from Cache
$ratesByCurrency = [];
$cacheFile = __DIR__ . '/../../../cache/rates.cache.php';

if (file_exists($cacheFile)) {
    $content = file_get_contents($cacheFile);
    $json = str_replace('<?php exit; ?>', '', $content);
    $ratesByCurrency = json_decode($json, true);
}

// Fallback to refresh if cache empty
if (empty($ratesByCurrency) && $odoo && !$error_message) {
    try {
        $odoo->refreshCurrencyRates();
        $content = file_get_contents($cacheFile);
        $json = str_replace('<?php exit; ?>', '', $content);
        $ratesByCurrency = json_decode($json, true);
    } catch (Exception $e) {
        $error_message = "Failed to auto-refresh rates: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odoo Currency Rates - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .rates-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .currency-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .currency-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-light);
        }

        .currency-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .rate-table {
            width: 100%;
            font-size: 0.9rem;
        }

        .rate-table th {
            text-align: left;
            color: var(--text-secondary);
            font-weight: 600;
            padding: 0.5rem;
        }

        .rate-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .rate-value {
            font-family: 'Inter', monospace;
            font-weight: 500;
            color: var(--text-primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .btn-sync {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-sync:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 16px;
            border: 1px dashed var(--border-color);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Odoo Currency Rates';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                    <a href="/settings" style="color:var(--text-secondary); text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Settings
                    </a>

                    <div style="display:flex; gap:1rem;">
                        <form method="POST">
                            <input type="hidden" name="action" value="sync">
                            <button type="submit" class="btn-sync" style="background:#64748b;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                                Sync Latest
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="sync">
                            <input type="hidden" name="force" value="1">
                            <button type="submit" class="btn-sync">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"></path>
                                </svg>
                                Force Full Reload
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Specific Date Checker -->
                <div class="settings-card" style="margin-bottom: 2rem; background: #f0f9ff; border-color: #bae6fd;">
                    <form method="GET" style="display: flex; align-items: flex-end; gap: 1.5rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #0369a1;">Check Rates for Specific Date</label>
                            <input type="date" name="check_date" value="<?php echo $_GET['check_date'] ?? date('Y-m-d'); ?>" 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid #7dd3fc; border-radius: 8px;">
                        </div>
                        <button type="submit" class="btn-sync" style="background: #0ea5e9;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Check Rate
                        </button>
                        <?php if (isset($_GET['check_date'])): ?>
                            <a href="/settings/odoo-rates" style="padding: 0.75rem; color: #0369a1; text-decoration: none; font-size: 0.9rem;">Clear</a>
                        <?php endif; ?>
                    </form>

                    <?php if (isset($_GET['check_date']) && !empty($ratesByCurrency)): 
                        $targetDate = $_GET['check_date'];
                        
                        // Extract unique company names from cache data
                        $companies = [];
                        foreach ($ratesByCurrency as $curr => $entries) {
                            foreach ($entries as $e) {
                                if (isset($e['company'])) $companies[$e['company']] = true;
                            }
                        }
                        $companyList = array_keys($companies);
                        if (empty($companyList)) $companyList = ['Global'];
                        
                        foreach ($companyList as $companyName):
                    ?>
                        <div style="margin-top: 2rem;">
                            <h4 style="font-size: 0.9rem; color: #64748b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7M4 21V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v17"></path>
                                </svg>
                                Company: <strong><?php echo $companyName; ?></strong>
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem;">
                                <?php 
                                foreach ($ratesByCurrency as $currency => $rates): 
                                    $rateOnDate = $odoo ? $odoo->getRate($currency, $targetDate, $companyName) : 0;
                                ?>
                                    <div style="background: white; padding: 1rem; border-radius: 12px; border: 1px solid #bae6fd; display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-weight: 600; color: #475569; font-size: 0.85rem;"><?php echo $currency; ?></span>
                                        <span style="font-family: monospace; font-weight: 700; color: #0369a1;"><?php echo number_format($rateOnDate, 6); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                        <p style="font-size: 0.8rem; color: #0369a1; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #bae6fd;">
                            * Showing the most recent rate available on or before <strong><?php echo date('M d, Y', strtotime($targetDate)); ?></strong> for each company.
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (empty($ratesByCurrency)): ?>
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1" style="margin-bottom:1rem;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <h3>No Currency Rates Found</h3>
                        <p style="color:var(--text-secondary);">Click the Sync button to fetch rates from Odoo.</p>
                    </div>
                <?php else: ?>
                    <div class="rates-container">
                        <?php foreach ($ratesByCurrency as $currency => $rates): ?>
                            <div class="currency-card">
                                <div class="currency-header">
                                    <span class="currency-name"><?php echo htmlspecialchars($currency); ?></span>
                                    <span style="font-size:0.8rem; color:var(--text-secondary);"><?php echo count($rates); ?> entries</span>
                                </div>
                                <table class="rate-table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Rate (vs Base)</th>
                                            <th style="text-align:right;">Company</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Filter to only show the most recent rate for each month and company
                                        $monthsSeen = [];
                                        $monthlyRates = [];
                                        foreach ($rates as $r) {
                                            $compId = $r['company'] ?? 'Global';
                                            $month = date('Y-m', strtotime($r['date']));
                                            $key = $compId . '_' . $month;
                                            
                                            if (!isset($monthsSeen[$key])) {
                                                $monthsSeen[$key] = true;
                                                $monthlyRates[] = $r;
                                            }
                                        }

                                        // Show 15 most recent entries
                                        $displayRates = array_slice($monthlyRates, 0, 15);
                                        foreach ($displayRates as $r): 
                                        ?>
                                            <tr>
                                                <td style="color:var(--text-secondary); font-weight:500;">
                                                    <?php echo date('M Y', strtotime($r['date'])); ?>
                                                </td>
                                                <td class="rate-value"><?php echo number_format($r['rate'], 6); ?></td>
                                                <td style="font-size:0.75rem; color:var(--text-secondary); text-align:right;">
                                                    <?php echo htmlspecialchars($r['company'] ?? 'Global'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if (count($monthlyRates) > 15): ?>
                                    <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:1rem; text-align:center;">Showing most recent entries</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top:3rem; padding:2rem; background:#f8fafc; border-radius:16px; border:1px solid #e2e8f0;">
                    <h3 style="font-size:1rem; margin-bottom:1rem; color:#475569;">Information</h3>
                    <ul style="font-size:0.9rem; color:#64748b; line-height:1.6;">
                        <li>Rates are fetched directly from Odoo's <code>res.currency.rate</code> model.</li>
                        <li>The rates shown are relative to your company's base currency in Odoo.</li>
                        <li>The system automatically refreshes these rates in the background once every 24 hours.</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
