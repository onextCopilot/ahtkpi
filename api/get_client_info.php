<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/OdooAPI.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$clientName = $_GET['client_name'] ?? '';

if (empty($clientName)) {
    echo '<div style="padding:10px; color: #de350b;">Missing client name</div>';
    exit;
}

// Initialize Odoo API for rates
$odoo = new OdooAPI();
// Company-safe per-currency rates (số ngoại tệ / 1 VND). KHÔNG dùng getRate() vì
// rates.cache bị lẫn tỉ giá giữa các công ty (cross-company).
$currencyMap = $odoo->getCurrencies();

// Fetch debts for this client
// Use prepared statements for security
$stmt = $conn->prepare("SELECT * FROM debts WHERE client_name = ? ORDER BY invoice_date DESC");
if (!$stmt) {
    echo '<div style="padding:10px; color: #de350b;">Database error</div>';
    exit;
}
$stmt->bind_param("s", $clientName);
$stmt->execute();
$result = $stmt->get_result();

$totalAmount = 0;
$paidAmount = 0;
$pendingAmount = 0;
$totalInvoices = 0;
$paidInvoices = 0;
$pendingInvoices = 0;
$earliestDate = null;
$latestDate = null;
$projects = []; // List unique projects

while ($row = $result->fetch_assoc()) {
    $amount = (float) $row['amount'];
    $currency = $row['currency'] ?: 'USD';
    $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');

    // Currency conversion (company-safe) matching /debt: VND = amount / rate (rate = ngoại tệ / 1 VND).
    if ($currency === 'VND') {
        $vndValue = $amount;
    } else {
        $cr = isset($currencyMap[$currency]['rate']) ? (float) $currencyMap[$currency]['rate'] : 0;
        $vndValue = ($cr > 0) ? ($amount / $cr) : $amount;
    }

    $totalAmount += $vndValue;
    $totalInvoices++;

    $status = $row['payment_status'] ?: '';
    if (strcasecmp(trim($status), 'Paid') === 0) {
        $paidAmount += $vndValue;
        $paidInvoices++;
    } else {
        $pendingAmount += $vndValue;
        $pendingInvoices++;
    }

    if ($earliestDate === null || $date < $earliestDate)
        $earliestDate = $date;
    if ($latestDate === null || $date > $latestDate)
        $latestDate = $date;

    if (!empty($row['project_name']) && !in_array($row['project_name'], $projects)) {
        $projects[] = $row['project_name'];
    }
}

// Format numbers helper
function formatMoney($amount)
{
    if ($amount >= 1000000000) {
        return number_format($amount / 1000000000, 1) . 'B';
    } elseif ($amount >= 1000000) {
        return number_format($amount / 1000000, 1) . 'M';
    } else {
        return number_format($amount); // Full number if small
    }
}

// Derived/Mock Contact Info (Since we don't have a clients table yet)
// Placeholder for now as per user feedback that mock data is incorrect
$website = '';
$email = '';
$phone = '';
$address = '';

// HTML Output
?>
<div class="client-tooltip-content"
    style="width: 600px; padding: 20px; position: relative; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <span onclick="document.getElementById('client-info-tooltip').style.display='none'; event.stopPropagation();"
        style="position: absolute; top: 12px; right: 12px; cursor: pointer; color: #6b778c; font-weight: bold; font-size: 20px; line-height: 1; z-index: 10;">&times;</span>

    <div class="client-tooltip-header"
        style="border-bottom: 1px solid #dfe1e6; padding-bottom: 15px; margin-bottom: 15px; cursor: move; display: flex; align-items: center;">
        <div
            style="width: 40px; height: 40px; background: #0052cc; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; font-size: 18px; pointer-events: none;">
            <?php echo strtoupper(substr($clientName, 0, 1)); ?>
        </div>
        <div style="flex: 1; pointer-events: none;">
            <div style="font-weight: 700; color: #172b4d; font-size: 18px; line-height: 1.2; margin-bottom: 4px;">
                <?php echo htmlspecialchars($clientName); ?>
            </div>
            <div style="font-size: 12px; color: #6b778c; display: flex; align-items: center;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                </svg>
                <a href="http://<?php echo $website; ?>" target="_blank"
                    style="color: #0052cc; text-decoration: none;"><?php echo $website; ?></a>
            </div>
        </div>
    </div>

    <!-- Contact Info (Only show if available) -->
    <?php if ($website || $email || $phone || $address): ?>
        <div
            style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #dfe1e6; font-size: 13px;">
            <?php if ($email): ?>
                <div style="display: flex; align-items: start;">
                    <span style="color: #6b778c; margin-right: 6px; min-width: 20px;"><svg width="14" height="14"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg></span>
                    <span style="color: #172b4d; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                        title="<?php echo $email; ?>"><?php echo $email; ?></span>
                </div>
            <?php endif; ?>
            <?php if ($phone): ?>
                <div style="display: flex; align-items: start;">
                    <span style="color: #6b778c; margin-right: 6px; min-width: 20px;"><svg width="14" height="14"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.12 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                            </path>
                        </svg></span>
                    <span style="color: #172b4d;"><?php echo $phone; ?></span>
                </div>
            <?php endif; ?>
            <?php if ($address): ?>
                <div style="display: flex; align-items: start; grid-column: span 2;">
                    <span style="color: #6b778c; margin-right: 6px; min-width: 20px; margin-top:2px;"><svg width="14"
                            height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg></span>
                    <span style="color: #172b4d; line-height: 1.4;"><?php echo $address; ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
        <div style="background: #f4f5f7; padding: 10px; border-radius: 4px; text-align: center;">
            <div style="font-size: 16px; font-weight: 700; color: #0052cc;"><?php echo formatMoney($totalAmount); ?>
            </div>
            <div style="font-size: 10px; color: #6b778c; text-transform: uppercase; font-weight: 600; margin-top: 2px;">
                Total Revenue (VND)</div>
        </div>
        <div style="background: #f4f5f7; padding: 10px; border-radius: 4px; text-align: center;">
            <div style="font-size: 16px; font-weight: 700; color: #172b4d;"><?php echo $totalInvoices; ?></div>
            <div style="font-size: 10px; color: #6b778c; text-transform: uppercase; font-weight: 600; margin-top: 2px;">
                Total Invoices</div>
        </div>
        <div style="background: #e3fcef; padding: 10px; border-radius: 4px; text-align: center;">
            <div style="font-size: 16px; font-weight: 700; color: #006644;"><?php echo formatMoney($paidAmount); ?>
            </div>
            <div style="font-size: 10px; color: #006644; text-transform: uppercase; font-weight: 600; margin-top: 2px;">
                Paid Amount</div>
        </div>
        <div style="background: #ffebe6; padding: 10px; border-radius: 4px; text-align: center;">
            <div style="font-size: 16px; font-weight: 700; color: #de350b;"><?php echo formatMoney($pendingAmount); ?>
            </div>
            <div style="font-size: 10px; color: #de350b; text-transform: uppercase; font-weight: 600; margin-top: 2px;">
                Pending Amount</div>
        </div>
    </div>

    <div style="background: #f4f5f7; padding: 10px; border-radius: 4px; font-size: 12px; color: #172b4d;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <span style="color: #6b778c;">First Invoice:</span>
            <span
                style="font-weight: 600;"><?php echo $earliestDate ? date('d/m/Y', strtotime($earliestDate)) : 'N/A'; ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <span style="color: #6b778c;">Last Invoice:</span>
            <span
                style="font-weight: 600;"><?php echo $latestDate ? date('d/m/Y', strtotime($latestDate)) : 'N/A'; ?></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #6b778c;">Active Projects:</span>
            <span style="font-weight: 600;"><?php echo count($projects); ?></span>
        </div>
    </div>
</div>