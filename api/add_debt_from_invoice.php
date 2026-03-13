<?php
// api/add_debt_from_invoice.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../libs/JiraAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Auto-migrate column if missing
$check = $conn->query("SHOW COLUMNS FROM debts LIKE 'sale_team_id'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE debts ADD sale_team_id INT DEFAULT NULL AFTER am");
}
$check2 = $conn->query("SHOW COLUMNS FROM debts LIKE 'odoo_invoice_id'");
if ($check2 && $check2->num_rows == 0) {
    $conn->query("ALTER TABLE debts ADD odoo_invoice_id INT DEFAULT NULL");
}

$check3 = $conn->query("SHOW COLUMNS FROM debts LIKE 'original_amount'");
if ($check3 && $check3->num_rows == 0) {
    $conn->query("ALTER TABLE debts ADD original_amount DECIMAL(15,2) DEFAULT NULL");
}

$check4 = $conn->query("SHOW COLUMNS FROM debts LIKE 'original_currency'");
if ($check4 && $check4->num_rows == 0) {
    $conn->query("ALTER TABLE debts ADD original_currency VARCHAR(10) DEFAULT NULL");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$invoiceName = $_POST['invoice_name'] ?? '';
if (empty($invoiceName) || $invoiceName === '/') {
    $invoiceName = 'Draft Invoice';
}

$description = $_POST['description'] ?? '';
$amount = $_POST['amount'] ?? 0;
$currency = $_POST['currency'] ?? 'VND';
$status = $_POST['status'] ?? 'Planning';
$paymentState = $_POST['payment_state'] ?? '';
$writeDate = $_POST['write_date'] ?? '';
$projectCode = $_POST['project_code'] ?? '';
$invoiceDateVal = $_POST['invoice_date'] ?? null;
$teamId = !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
$odooInvoiceId = !empty($_POST['odoo_invoice_id']) ? intval($_POST['odoo_invoice_id']) : null;

// Basic validation
if (empty($amount)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid invoice amount']);
    exit();
}

// Extract Partner Name
$parts = explode(' - ', $description);
$clientName = isset($parts[1]) ? trim($parts[1]) : 'Unknown Client';

// Determine Project Name from Jira
$projectName = '';
if (!empty($projectCode)) {
    try {
        $jira = new JiraAPI();
        $projects = $jira->getProjects();
        foreach ($projects as $p) {
            // Check key matches projectCode (case-insensitive)
            if (isset($p['key']) && strcasecmp($p['key'], $projectCode) === 0) {
                // Keep the exact name from Jira
                $projectName = $p['name'];
                break;
            }
        }
    } catch (Exception $e) {
        error_log("Jira lookup failed: " . $e->getMessage());
    }
}

// Logic for custom fields
$plClass = 'Xấu';
$invoiceStatusClass = '';
$paymentMonth = '';
$weeklyUpdate = '';

if ($paymentState === 'paid' || $paymentState === 'in_payment') {
    if (!empty($writeDate)) {
        $ts = strtotime($writeDate);
        $currentMonth = date('Y-m');
        $paidMonth = date('Y-m', $ts);
        $paymentMonth = date('m/Y', $ts);

        // Calculate week of month
        $dayOfMonth = date('j', $ts);
        $weekOfMonth = ceil($dayOfMonth / 7);
        $weeklyUpdate = "Tuần " . $weekOfMonth;

        if ($paidMonth === $currentMonth) {
            $invoiceStatusClass = 'Tím';
        } else {
            $invoiceStatusClass = 'Done';
        }
    } else {
        $invoiceStatusClass = 'Done';
    }
} else {
    // Not paid - check if overdue (> 60 days from invoice date)
    if (!empty($invoiceDateVal)) {
        $invTs = strtotime($invoiceDateVal);
        $daysSinceInvoice = floor((time() - $invTs) / 86400);
        if ($daysSinceInvoice > 60) {
            $invoiceStatusClass = 'Đỏ';
        }
    }
}

$amName = $_SESSION['full_name'];
$amEmail = $_SESSION['email'] ?? null;

// Fetch email from DB if not in session
if (!$amEmail && isset($_SESSION['user_id'])) {
    $uStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $uStmt->bind_param("i", $_SESSION['user_id']);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    if ($uRow = $uRes->fetch_assoc()) {
        $amEmail = $uRow['email'];
        $_SESSION['email'] = $amEmail;
    }
}


try {
    $stmt = $conn->prepare("INSERT INTO debts 
              (company, am, am_email, sale_team_id, client_name, project_name, amount, original_amount, currency, original_currency, vat_invoice, invoice_date, payment_status, am_notes, pl_class, invoice_status_class, payment_month, weekly_update, odoo_invoice_id, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $defaultCompany = 'AHT TECH';
    $paymentStatus = 'Not paid';

    if ($paymentState === 'paid') {
        $paymentStatus = 'Paid';
    }

    $notes = "Added from Invoice: " . $invoiceName . " (" . $currency . ")";

    $stmt->bind_param(
        "sssissddssssssssssi",
        $defaultCompany,
        $amName,
        $teamId,
        $clientName,
        $projectName,
        $amount,
        $amount,
        $currency,
        $currency,
        $invoiceName,
        $invoiceDateVal,
        $paymentStatus,
        $notes,
        $plClass,
        $invoiceStatusClass,
        $paymentMonth,
        $weeklyUpdate,
        $odooInvoiceId
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to create debt: ' . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
