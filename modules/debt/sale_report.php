<?php
// modules/debt/sale_report.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../libs/OdooAPI.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$is_am_bd = $_SESSION['is_am_bd'] ?? 0;
$email = $_SESSION['email'] ?? '';

// Fallback if email is missing from session (user didn't re-login after update)
if (empty($email) && isset($current_user_id)) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $email = $row['email'];
        $_SESSION['email'] = $email;
    }
}

// Access control: Only AM/BD or Admin
if (!$is_am_bd && $role !== 'admin') {
    die("Access denied. Only AM/BD members or Admins can view this report.");
}

// --- DB INIT / MIGRATION ---
$table_check = $conn->query("SHOW TABLES LIKE 'sale_report_details'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE sale_report_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNIQUE,
        contract_date DATE,
        contract_type VARCHAR(50),
        presales VARCHAR(50),
        client_type VARCHAR(50),
        com_lead_source TINYINT(1) DEFAULT 0,
        bonus_license_trading TINYINT(1) DEFAULT 0,
        com_2 VARCHAR(20),
        note TEXT,
        profit_pakd TEXT,
        net_profit DECIMAL(15, 2),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
}

// --- HANDLE AJAX UPDATES ---
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_field') {
    header('Content-Type: application/json');
    $inv_id = intval($_POST['invoice_id']);
    $field = $_POST['field'];
    $value = $_POST['value'];

    // Allowed fields to update
    $allowed_fields = [
        'contract_date',
        'contract_type',
        'presales',
        'client_type',
        'com_lead_source',
        'bonus_license_trading',
        'com_2',
        'note',
        'profit_pakd',
        'net_profit'
    ];

    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field']);
        exit;
    }

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    }

    // Use INSERT ... ON DUPLICATE KEY UPDATE
    $stmt = $conn->prepare("INSERT INTO sale_report_details (invoice_id, $field) VALUES (?, ?) ON DUPLICATE KEY UPDATE $field = ?");
    $stmt->bind_param("iss", $inv_id, $value, $value);

    if ($stmt->execute()) {
        $com1 = null;
        if ($field === 'client_type') {
            $com1 = ($value === 'Old client') ? '0.5%' : (($value === 'New client') ? '1%' : '');
        }
        echo json_encode(['success' => true, 'com1' => $com1]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

// --- FETCH DATA ---
$odoo = new OdooAPI();
$filters = [];
// If not admin, filter by salesperson email
if ($role !== 'admin') {
    $filters['owner_email'] = $email;
}

try {
    // Get invoices from Odoo (limited to 500 for performance)
    $result = $odoo->getInvoices(500, 0, $filters);
    $invoices = $result['invoices'];

    // Fetch local details
    $local_details = [];
    $details_res = $conn->query("SELECT * FROM sale_report_details");
    while ($row = $details_res->fetch_assoc()) {
        $local_details[$row['invoice_id']] = $row;
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$page_title = "Sale Reports";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Management System</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS for basic classes if needed -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .main-content {
            background-color: #f8fafc;
        }

        .table-container {
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin: 20px;
        }

        .editable-field {
            border: 1px solid transparent;
            border-radius: 4px;
            padding: 4px 8px;
            width: 100%;
            transition: 0.2s;
            font-size: 13px;
        }

        .editable-field:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .editable-field:focus {
            border-color: #3b82f6;
            outline: none;
            background: white;
        }

        .text-end {
            text-align: right;
        }

        .fw-bold {
            font-weight: 700;
        }

        .com1-cell {
            font-weight: 600;
            color: #1e293b;
        }

        .form-select-sm,
        .form-control-sm {
            font-size: 13px;
        }

        /* Matching dashboard.css style */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php
            $page_subtitle = "Invoice evaluation and KPI calculation";
            include __DIR__ . '/../includes/topbar.php';
            ?>

            <div class="content-wrapper">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mx-4 mt-3"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="min-width: 1600px; font-size: 13px;">
                            <thead class="bg-light">
                                <tr class="text-secondary">
                                    <th style="width: 40px;">STT</th>
                                    <th>Khách hàng</th>
                                    <th>Dự án</th>
                                    <th>Mã dự án</th>
                                    <th>Ngày ký HĐ</th>
                                    <th>Loại HĐ</th>
                                    <th>Presales</th>
                                    <th>Loại KH</th>
                                    <th class="text-end">Giá trị (Untaxed)</th>
                                    <th>%Profit PAKD</th>
                                    <th class="text-end">Net Profit</th>
                                    <th>% Com (Lead)</th>
                                    <th>% Bonus</th>
                                    <th>% Com 1</th>
                                    <th>% Com 2</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stt = 1;
                                $total_amount = 0;
                                foreach ($invoices as $inv):
                                    $inv_id = $inv['id'];
                                    $detail = $local_details[$inv_id] ?? [];
                                    $amount = abs($inv['amount_total_signed'] ?? 0);
                                    $total_amount += $amount;

                                    $client_type = $detail['client_type'] ?? '';
                                    $com1 = ($client_type === 'Old client') ? '0.5%' : (($client_type === 'New client') ? '1%' : '');
                                    ?>
                                    <tr data-id="<?php echo $inv_id; ?>">
                                        <td><?php echo $stt++; ?></td>
                                        <td class="text-truncate" style="max-width: 150px;"
                                            title="<?php echo htmlspecialchars($inv['partner_id'][1] ?? ''); ?>">
                                            <?php echo htmlspecialchars($inv['partner_id'][1] ?? '-'); ?>
                                        </td>
                                        <td class="text-truncate" style="max-width: 150px;"
                                            title="<?php echo htmlspecialchars($inv['invoice_origin'] ?: ($inv['ref'] ?: '-')); ?>">
                                            <?php echo htmlspecialchars($inv['invoice_origin'] ?: ($inv['ref'] ?: '-')); ?>
                                        </td>
                                        <td><span
                                                class="badge bg-secondary-subtle text-secondary border"><?php echo htmlspecialchars($inv['x_studio_project_code'] ?? '-'); ?></span>
                                        </td>

                                        <td>
                                            <input type="date" class="editable-field" data-field="contract_date"
                                                value="<?php echo $detail['contract_date'] ?? ''; ?>">
                                        </td>
                                        <td>
                                            <select class="editable-field" data-field="contract_type">
                                                <option value="">--</option>
                                                <?php foreach (['Service', 'Trading', 'Dedicated', 'License'] as $opt): ?>
                                                    <option value="<?php echo $opt; ?>" <?php echo ($detail['contract_type'] ?? '') === $opt ? 'selected' : ''; ?>>
                                                        <?php echo $opt; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="editable-field" data-field="presales">
                                                <option value="">--</option>
                                                <?php foreach (['No presales', '0%', '0.25%', '0.5%'] as $opt): ?>
                                                    <option value="<?php echo $opt; ?>" <?php echo ($detail['presales'] ?? '') === $opt ? 'selected' : ''; ?>>
                                                        <?php echo $opt; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="editable-field" data-field="client_type">
                                                <option value="">--</option>
                                                <?php foreach (['New client', 'Old client'] as $opt): ?>
                                                    <option value="<?php echo $opt; ?>" <?php echo ($detail['client_type'] ?? '') === $opt ? 'selected' : ''; ?>>
                                                        <?php echo $opt; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>

                                        <td class="text-end fw-bold">
                                            <?php echo number_format($amount, 2); ?>
                                        </td>

                                        <td>
                                            <input type="text" class="editable-field" data-field="profit_pakd"
                                                value="<?php echo htmlspecialchars($detail['profit_pakd'] ?? ''); ?>"
                                                placeholder="...">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="editable-field text-end"
                                                data-field="net_profit" value="<?php echo $detail['net_profit'] ?? ''; ?>">
                                        </td>
                                        <td>
                                            <select class="editable-field" data-field="com_lead_source">
                                                <option value="0" <?php echo ($detail['com_lead_source'] ?? 0) == 0 ? 'selected' : ''; ?>>No</option>
                                                <option value="1" <?php echo ($detail['com_lead_source'] ?? 0) == 1 ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="editable-field" data-field="bonus_license_trading">
                                                <option value="0" <?php echo ($detail['bonus_license_trading'] ?? 0) == 0 ? 'selected' : ''; ?>>No</option>
                                                <option value="1" <?php echo ($detail['bonus_license_trading'] ?? 0) == 1 ? 'selected' : ''; ?>>Yes</option>
                                            </select>
                                        </td>
                                        <td class="com1-cell text-center"><?php echo $com1; ?></td>
                                        <td>
                                            <select class="editable-field" data-field="com_2">
                                                <option value="">--</option>
                                                <?php foreach (['0.5%', '1%', '1.5%', '2%', '2.5%', '3%'] as $opt): ?>
                                                    <option value="<?php echo $opt; ?>" <?php echo ($detail['com_2'] ?? '') === $opt ? 'selected' : ''; ?>>
                                                        <?php echo $opt; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <textarea class="editable-field" data-field="note" rows="1"
                                                style="height: 32px;"><?php echo htmlspecialchars($detail['note'] ?? ''); ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light fw-bold">
                                <tr>
                                    <td colspan="8" class="text-end">TOTAL VALUE:</td>
                                    <td class="text-end text-primary"><?php echo number_format($total_amount, 2); ?>
                                    </td>
                                    <td colspan="7"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.editable-field').forEach(el => {
            el.addEventListener('change', function () {
                const row = this.closest('tr');
                const invId = row.dataset.id;
                const field = this.dataset.field;
                const value = this.value;

                this.style.opacity = '0.5';

                const formData = new FormData();
                formData.append('ajax_action', 'update_field');
                formData.append('invoice_id', invId);
                formData.append('field', field);
                formData.append('value', value);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        this.style.opacity = '1';
                        if (data.success) {
                            this.parentElement.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => { this.parentElement.style.backgroundColor = 'transparent'; }, 1000);

                            if (data.com1 !== null) {
                                row.querySelector('.com1-cell').textContent = data.com1;
                            }
                        } else {
                            alert('Error: ' + (data.error || 'Server error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.style.opacity = '1';
                        alert('Request failed');
                    });
            });
        });
    </script>
</body>

</html>