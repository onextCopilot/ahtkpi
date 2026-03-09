<?php
// api/sync_debt.php
header('Content-Type: application/json');

// Suppress potential session warnings from config.php on live server
$old_error_level = error_reporting(0);
require_once __DIR__ . '/../config/config.php';
error_reporting($old_error_level);
require_once __DIR__ . '/../libs/OdooAPI.php';
require_once __DIR__ . '/../libs/JiraAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$debtId = $_POST['id'] ?? 0;
$invoiceName = $_POST['vat_invoice'] ?? '';

if (empty($debtId) || empty($invoiceName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID or Invoice Name']);
    exit();
}

try {
    $odoo = new OdooAPI();

    // 1. Fetch Invoice from Odoo
    $domain = [['name', '=', $invoiceName]];
    $fields = [
        'name',
        'payment_state',
        'write_date',
        'amount_total',
        'invoice_payments_widget',
        'x_studio_project_code',
        'invoice_date',
        'date',
        'currency_id'
    ];

    // We don't use cache here, we want fresh data, 
    // BUT OdooAPI::searchRead hits Odoo directly.
    $invoices = $odoo->searchRead('account.move', $domain, $fields, 1);

    if (empty($invoices)) {
        throw new Exception("Invoice $invoiceName not found in Odoo.");
    }

    $inv = $invoices[0];

    // 2. Calculate Fields
    $paymentState = $inv['payment_state'];
    $writeDate = $inv['write_date'];
    $amount = $inv['amount_total'];
    $invoiceDateVal = $inv['invoice_date'] ?: $inv['date'];
    $currency = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'VND';

    // Calculate Payment Date/Month
    $paymentDate = $writeDate;
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

    // Calculate Project Name from Jira
    $projectName = '';
    $projectCode = $inv['x_studio_project_code'] ?? '';
    if (!empty($projectCode)) {
        try {
            $jira = new JiraAPI();
            $projects = $jira->getProjects();
            foreach ($projects as $p) {
                if (isset($p['key']) && strcasecmp($p['key'], $projectCode) === 0) {
                    $projectName = $p['name'];
                    break;
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
    }

    $paymentMonth = '';
    $weeklyUpdate = '';
    $invoiceStatusClass = '';

    if ($paymentState === 'paid' || $paymentState === 'in_payment') {
        if (!empty($paymentDate)) {
            $ts = strtotime($paymentDate);
            $currentMonth = date('Y-m');
            $paidMonth = date('Y-m', $ts);

            $paymentMonth = date('m/Y', $ts);

            // Calculate week of month (1-5)
            $dayOfMonth = date('j', $ts);
            $weekOfMonth = ceil($dayOfMonth / 7);
            $weeklyUpdate = "Tuần " . $weekOfMonth;

            if ($paidMonth === $currentMonth) {
                $invoiceStatusClass = 'Tím';
            } else {
                if ($paidMonth < $currentMonth) {
                    $invoiceStatusClass = 'Done';
                } else {
                    $invoiceStatusClass = 'Tím';
                }
            }
        } else {
            $invoiceStatusClass = 'Done';
        }
    } else {
        // Not paid - check if overdue (> 60 days from invoice date)
        $invoiceDate = $inv['invoice_date'] ?? $inv['date'] ?? '';
        if (!empty($invoiceDate)) {
            $invTs = strtotime($invoiceDate);
            $daysSinceInvoice = floor((time() - $invTs) / 86400);
            if ($daysSinceInvoice > 60) {
                $invoiceStatusClass = 'Đỏ';
            }
        }
    }

    $paymentStatus = ($paymentState === 'paid' || $paymentState === 'in_payment') ? 'Paid' : 'Not paid';

    // Update P&L based on status
    $plClass = ($paymentStatus === 'Paid') ? 'Tốt' : 'Xấu';

    // 3. Update Debt Record
    // We update: payment_status, payment_month, invoice_status_class, amount, pl_class, project_name

    $sql = "UPDATE debts SET 
        payment_status = ?, 
        payment_month = ?, 
        invoice_status_class = ?, 
        amount = ?,
        pl_class = ?, 
        weekly_update = ?,
        invoice_date = ?,
        currency = ?,
        updated_at = NOW()";

    $params = [$paymentStatus, $paymentMonth, $invoiceStatusClass, $amount, $plClass, $weeklyUpdate, $invoiceDateVal, $currency];
    $types = "ssssdsss";

    if (!empty($projectName)) {
        $sql .= ", project_name = ?";
        $params[] = $projectName;
        $types .= "s";
    }

    $sql .= " WHERE id = ?";
    $params[] = $debtId;
    $types .= "i";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Database Error: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update debt: ' . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
