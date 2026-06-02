<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id      = $_SESSION['user_id'];
$role         = $_SESSION['role'] ?? 'user';
$my_full_name = $_SESSION['full_name'] ?? '';
$is_admin     = ($role === 'admin');
$pakd_id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pakd_id) {
    header('Location: /projects/du-an');
    exit();
}

// ── Ensure docs table exists ──────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd_documents (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pakd_id          INT NOT NULL,
    file_name        VARCHAR(255)  NOT NULL,
    original_name    VARCHAR(500)  NOT NULL,
    file_size        BIGINT        DEFAULT 0,
    mime_type        VARCHAR(200)  DEFAULT NULL,
    file_path        VARCHAR(1000) NOT NULL,
    doc_label        VARCHAR(100)  DEFAULT NULL,
    uploaded_by      INT           DEFAULT NULL,
    uploaded_by_name VARCHAR(255)  DEFAULT NULL,
    created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pakd (pakd_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── AJAX: Update gross_profit ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_gp') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['pakd_id'] ?? 0);
    $gp  = (float)preg_replace('/[^\d.]/', '', $_POST['gross_profit'] ?? '0');
    $rev = (float)preg_replace('/[^\d.]/', '', $_POST['revenue']      ?? '0');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'Invalid pakd_id']); exit; }
    $ok  = $conn->query("UPDATE pakd SET gross_profit = $gp, revenue = $rev WHERE id = $pid");
    echo json_encode(['ok' => (bool)$ok, 'gross_profit' => $gp, 'revenue' => $rev, 'err' => $conn->error ?: null]);
    exit;
}

// ── AJAX: Upload file ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_doc') {
    header('Content-Type: application/json; charset=utf-8');
    $pid   = (int)($_POST['pakd_id'] ?? $pakd_id);
    $label = trim($_POST['doc_label'] ?? '');
    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID']); exit; }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false,'msg'=>'Lỗi upload file (code: '.($_FILES['file']['error'] ?? -1).')']); exit;
    }
    $file = $_FILES['file'];
    if ($file['size'] > 50 * 1024 * 1024) { echo json_encode(['ok'=>false,'msg'=>'File quá lớn (tối đa 50MB)']); exit; }
    $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','png','jpg','jpeg','gif','webp','zip','txt','csv'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) { echo json_encode(['ok'=>false,'msg'=>'Loại file không được phép: .'.$ext]); exit; }
    $upload_dir = __DIR__ . '/../../uploads/pakd_docs/' . $pid . '/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $stored = uniqid('doc_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $stored)) {
        echo json_encode(['ok'=>false,'msg'=>'Không thể lưu file']); exit;
    }
    $rel = '/uploads/pakd_docs/' . $pid . '/' . $stored;
    $stmt = $conn->prepare("INSERT INTO pakd_documents (pakd_id,file_name,original_name,file_size,mime_type,file_path,doc_label,uploaded_by,uploaded_by_name) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("issssssis", $pid, $stored, $file['name'], $file['size'], $file['type'], $rel, $label, $user_id, $my_full_name);
    $stmt->execute();
    echo json_encode(['ok'=>true,'id'=>$conn->insert_id,'name'=>$file['name'],'path'=>$rel,'size'=>$file['size'],'ext'=>$ext,'label'=>$label,'uploader'=>$my_full_name]);
    $stmt->close();
    exit;
}

// ── AJAX: Delete file ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_doc') {
    header('Content-Type: application/json; charset=utf-8');
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    $pid    = (int)($_POST['pakd_id'] ?? $pakd_id);
    $stmt   = $conn->prepare("SELECT file_path, uploaded_by FROM pakd_documents WHERE id=? AND pakd_id=?");
    $stmt->bind_param("ii", $doc_id, $pid);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$doc) { echo json_encode(['ok'=>false,'msg'=>'Không tìm thấy file']); exit; }
    if (!$is_admin && (int)$doc['uploaded_by'] !== $user_id) { echo json_encode(['ok'=>false,'msg'=>'Không có quyền']); exit; }
    $full = __DIR__ . '/../../' . ltrim($doc['file_path'], '/');
    if (file_exists($full)) unlink($full);
    $conn->prepare("DELETE FROM pakd_documents WHERE id=?")->bind_param("i",$doc_id)->execute();
    echo json_encode(['ok'=>true]);
    exit;
}

// Ensure columns exist
foreach ([
    'assignment_date'  => 'DATETIME DEFAULT NULL',
    'expected_closing' => 'DATE DEFAULT NULL',
    'odoo_stage_id'    => 'INT DEFAULT NULL',
    'division_names'   => 'VARCHAR(500) DEFAULT NULL',
    'won_status'       => 'VARCHAR(20) DEFAULT NULL',
    'lost_reason'      => 'VARCHAR(255) DEFAULT NULL',
    'revenue'          => 'DECIMAL(20,2) DEFAULT 0',
    'gross_profit'     => 'DECIMAL(20,2) DEFAULT 0',
    'pasx_value'       => 'DECIMAL(20,2) DEFAULT 0',
    'contract_no'      => 'VARCHAR(255) DEFAULT NULL',
    'sales_order_no'   => 'VARCHAR(255) DEFAULT NULL',
    'purchase_order_no'=> 'VARCHAR(255) DEFAULT NULL',
    'timeline'         => 'TEXT DEFAULT NULL',
    'fin_data'         => 'JSON DEFAULT NULL',
    'approved_by_name' => 'VARCHAR(255) DEFAULT NULL',
    'approved_at'      => 'DATETIME DEFAULT NULL',
    'pasx_id'          => 'VARCHAR(64) DEFAULT NULL',
    'pasx_status'      => 'VARCHAR(32) DEFAULT NULL',
    'pasx_requested_at'=> 'DATETIME DEFAULT NULL',
    'project_type'     => "VARCHAR(50) DEFAULT 'external'",
] as $_col => $_def) {
    $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pakd' AND COLUMN_NAME='$_col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE pakd ADD COLUMN `$_col` $_def");
}
unset($_col, $_def, $r);

// Fetch the project
$stmt = $conn->prepare("SELECT * FROM pakd WHERE id = ? AND won_status = 'won'");
$stmt->bind_param("i", $pakd_id);
$stmt->execute();
$pakd = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pakd) {
    header('Location: /projects/du-an');
    exit();
}

// Access control: AM only sees their own projects
if (!$is_admin) {
    $owner_match = (!empty($pakd['am_user_id']) && (int)$pakd['am_user_id'] === $user_id)
                || (!empty($pakd['am_name'])    && $pakd['am_name'] === $my_full_name);
    if (!$owner_match) {
        header('HTTP/1.1 403 Forbidden');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;">
            <h2 style="color:#dc2626;">⛔ Bạn không có quyền xem dự án này</h2>
            <p style="color:#64748b;">Chỉ AM phụ trách mới có thể truy cập.</p>
            <a href="/projects/du-an" style="color:#6366f1;">← Quay lại danh sách</a>
        </body></html>';
        exit;
    }
}

// User avatar
$userAvatarMap = [];
$uRes = $conn->query("SELECT email, full_name, avatar FROM users WHERE email IS NOT NULL AND email != ''");
if ($uRes) while ($u = $uRes->fetch_assoc()) $userAvatarMap[strtolower($u['email'])] = $u;

// Financial data
$fin_saved     = !empty($pakd['fin_data']) ? (json_decode($pakd['fin_data'], true) ?? []) : [];
$fin_rev_gross = (float)($pakd['revenue']      ?? 0);
$fin_rev_net   = !empty($fin_saved['rev_net']) ? (float)$fin_saved['rev_net'] : $fin_rev_gross;
$fin_human_cost   = (float)($fin_saved['human_cost']    ?? 0);
$fin_overtime     = (float)($fin_saved['overtime_cost'] ?? 0);
$pasx_has_data    = ($fin_human_cost > 0 || $fin_overtime > 0);
$fin_prod_cost    = $pasx_has_data ? ($fin_human_cost + $fin_overtime) : (float)($pakd['pasx_value'] ?? 0);
$fin_gross_profit = (float)($pakd['gross_profit'] ?? 0);
$fin_margin_pct   = $fin_rev_net > 0 ? ($fin_gross_profit / $fin_rev_net * 100) : 0;
$fin_total_cost   = $fin_rev_net - $fin_gross_profit;

// Fetch Sale Orders từ odoo_sale_orders (được upsert bởi webhook hook)
$sale_orders    = [];
$so_fetch_error = null;
try {
    $opp_id = (int)($pakd['odoo_opp_id'] ?? 0);
    if ($opp_id) {
        $soRes = $conn->prepare(
            "SELECT * FROM odoo_sale_orders WHERE opportunity_id = ? AND state != 'cancel' ORDER BY date_order DESC"
        );
        $soRes->bind_param('i', $opp_id);
        $soRes->execute();
        $soRows = $soRes->get_result();
        while ($row = $soRows->fetch_assoc()) {
            // Normalise invoice_ids và order_line_ids từ JSON string → array
            $row['invoice_ids']    = !empty($row['invoice_ids'])    ? json_decode($row['invoice_ids'],    true) : [];
            $row['order_line_ids'] = !empty($row['order_line_ids']) ? json_decode($row['order_line_ids'], true) : [];
            $row['_lines']         = []; // sẽ fetch bên dưới
            // Map field names để tương thích với view code cũ
            $row['id']             = $row['odoo_id'];
            $sale_orders[]         = $row;
        }
        $soRes->close();
    }

    // Fetch order lines từ Odoo API cho các SO đã có trong DB
    if (!empty($sale_orders)) {
        $all_line_ids = [];
        foreach ($sale_orders as $so) {
            foreach ($so['order_line_ids'] as $lid) {
                $lid = (int)$lid;
                if ($lid > 0) $all_line_ids[$lid] = true;
            }
        }
        if (!empty($all_line_ids)) {
            require_once __DIR__ . '/../../libs/OdooAPI.php';
            $odoo      = new OdooAPI();
            $line_ids  = array_keys($all_line_ids);
            $lines     = $odoo->searchRead('sale.order.line',
                [['id', 'in', $line_ids]],
                ['id','order_id','name','product_id','product_uom_qty','product_uom',
                 'price_unit','price_subtotal','price_total','discount',
                 'qty_invoiced','qty_to_invoice'],
                0, 0
            ) ?: [];
            $so_lines_map = [];
            foreach ($lines as $line) {
                $soid = is_array($line['order_id']) ? (int)$line['order_id'][0] : (int)$line['order_id'];
                $so_lines_map[$soid][] = $line;
            }
            foreach ($sale_orders as &$so) {
                $so['_lines'] = $so_lines_map[$so['odoo_id']] ?? [];
            }
            unset($so);
        }
    }
} catch (\Throwable $e) {
    $so_fetch_error = $e->getMessage();
}

// Fetch Invoices từ odoo_invoices: dùng invoice_ids từ các SO
$invoices        = [];
$inv_fetch_error = null;
try {
    // Hướng 1: invoice_ids từ SO (đã được sync bởi hook)
    $all_invoice_ids = [];
    foreach ($sale_orders as $so) {
        foreach ((array)$so['invoice_ids'] as $iid) {
            $iid = (int)$iid;
            if ($iid > 0) $all_invoice_ids[$iid] = true;
        }
    }

    // Hướng 2: invoice_origin = SO name (phòng trường hợp SO chưa kịp cập nhật invoice_ids)
    $so_names = array_values(array_filter(array_column($sale_orders, 'name')));

    // Build query kết hợp cả 2 hướng
    $whereParts = [];
    if (!empty($all_invoice_ids)) {
        $idList = implode(',', array_keys($all_invoice_ids));
        $whereParts[] = "odoo_id IN ($idList)";
    }
    if (!empty($so_names)) {
        $escapedNames = implode(',', array_map(fn($n) => "'" . $conn->real_escape_string($n) . "'", $so_names));
        $whereParts[] = "invoice_origin IN ($escapedNames)";
    }

    if (!empty($whereParts)) {
        $whereSQL = implode(' OR ', $whereParts);
        $invRes = $conn->query(
            "SELECT * FROM odoo_invoices
             WHERE ($whereSQL) AND state != 'cancel'
             ORDER BY invoice_date DESC, odoo_id DESC"
        );
        $seen = [];
        if ($invRes) {
            while ($row = $invRes->fetch_assoc()) {
                if (isset($seen[$row['odoo_id']])) continue; // dedup
                $seen[$row['odoo_id']] = true;
                $row['invoice_line_ids'] = !empty($row['invoice_line_ids']) ? json_decode($row['invoice_line_ids'], true) : [];
                $invoices[] = $row;
            }
        }
    }
} catch (\Throwable $e) {
    $inv_fetch_error = $e->getMessage();
}

// PASX history (latest 5)
$pasx_logs = [];
try {
    $wl = $conn->prepare("SELECT id, event, status, http_status, received_at FROM pasx_webhook_logs WHERE pakd_id=? ORDER BY received_at DESC LIMIT 5");
    $wl->bind_param("i", $pakd_id);
    $wl->execute();
    $wlRes = $wl->get_result();
    while ($wRow = $wlRes->fetch_assoc()) $pasx_logs[] = $wRow;
    $wl->close();
} catch (\Throwable $e) {}

// Fetch documents
$doc_list = [];
$dRes = $conn->prepare("SELECT * FROM pakd_documents WHERE pakd_id=? ORDER BY created_at DESC");
$dRes->bind_param("i", $pakd_id);
$dRes->execute();
$dRows = $dRes->get_result();
while ($dRow = $dRows->fetch_assoc()) $doc_list[] = $dRow;
$dRes->close();

$site_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

function formatFileSize($bytes) {
    if ($bytes >= 1024*1024) return number_format($bytes/1024/1024, 1).' MB';
    if ($bytes >= 1024)      return number_format($bytes/1024, 0).' KB';
    return $bytes . ' B';
}

function fileIcon($ext) {
    $map = [
        'pdf'  => ['fas fa-file-pdf',       '#dc2626'],
        'doc'  => ['fas fa-file-word',       '#2563eb'],
        'docx' => ['fas fa-file-word',       '#2563eb'],
        'xls'  => ['fas fa-file-excel',      '#16a34a'],
        'xlsx' => ['fas fa-file-excel',      '#16a34a'],
        'ppt'  => ['fas fa-file-powerpoint', '#ea580c'],
        'pptx' => ['fas fa-file-powerpoint', '#ea580c'],
        'png'  => ['fas fa-file-image',      '#7c3aed'],
        'jpg'  => ['fas fa-file-image',      '#7c3aed'],
        'jpeg' => ['fas fa-file-image',      '#7c3aed'],
        'gif'  => ['fas fa-file-image',      '#7c3aed'],
        'webp' => ['fas fa-file-image',      '#7c3aed'],
        'zip'  => ['fas fa-file-zipper',     '#d97706'],
        'txt'  => ['fas fa-file-lines',      '#64748b'],
        'csv'  => ['fas fa-file-csv',        '#16a34a'],
    ];
    return $map[$ext] ?? ['fas fa-file', '#94a3b8'];
}

function canPreview($ext) {
    return in_array($ext, ['pdf','png','jpg','jpeg','gif','webp','doc','docx','ppt','pptx','xls','xlsx']);
}

function formatVND3($num) {
    if ($num >= 1e9)  return number_format($num/1e9, 2, ',', '.').' tỷ';
    if ($num >= 1e6)  return number_format($num/1e6, 1, ',', '.').' triệu';
    if ($num >= 1e3)  return number_format($num/1e3, 0, ',', '.').'K';
    return number_format($num, 0, ',', '.');
}

function formatVNDFull($num) {
    return number_format($num, 0, ',', '.');
}

function invStateBadge($state) {
    $map = [
        'draft'  => ['label' => 'Nháp',     'bg' => '#f1f5f9', 'fg' => '#64748b'],
        'posted' => ['label' => 'Đã ghi sổ','bg' => '#dcfce7', 'fg' => '#16a34a'],
        'cancel' => ['label' => 'Đã hủy',   'bg' => '#fee2e2', 'fg' => '#dc2626'],
    ];
    $s = $map[$state] ?? ['label' => strtoupper($state ?: '—'), 'bg' => '#f1f5f9', 'fg' => '#64748b'];
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:5px;font-size:11px;font-weight:700;background:' . $s['bg'] . ';color:' . $s['fg'] . ';">'
         . htmlspecialchars($s['label']) . '</span>';
}

function invPaymentBadge($state) {
    $map = [
        'not_paid'   => ['label' => 'Chưa TT',   'bg' => '#fee2e2', 'fg' => '#dc2626'],
        'in_payment' => ['label' => 'Đang TT',    'bg' => '#fef9c3', 'fg' => '#d97706'],
        'paid'       => ['label' => 'Đã TT',      'bg' => '#dcfce7', 'fg' => '#16a34a'],
        'partial'    => ['label' => 'TT một phần','bg' => '#eff6ff', 'fg' => '#2563eb'],
        'reversed'   => ['label' => 'Đã hoàn',    'bg' => '#f5f3ff', 'fg' => '#7c3aed'],
    ];
    $s = $map[$state] ?? ['label' => $state ?: '—', 'bg' => '#f1f5f9', 'fg' => '#64748b'];
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:5px;font-size:11px;font-weight:700;background:' . $s['bg'] . ';color:' . $s['fg'] . ';">'
         . htmlspecialchars($s['label']) . '</span>';
}

function invTypeBadge($type) {
    if ($type === 'out_refund') {
        return '<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:5px;font-size:10px;font-weight:700;background:#f5f3ff;color:#7c3aed;">Credit Note</span>';
    }
    return '';
}

function soStateBadge($state) {
    $map = [
        'draft'  => ['label' => 'Nháp',        'bg' => '#f1f5f9', 'fg' => '#64748b'],
        'sent'   => ['label' => 'Đã gửi',       'bg' => '#eff6ff', 'fg' => '#2563eb'],
        'sale'   => ['label' => 'Đã xác nhận',  'bg' => '#dcfce7', 'fg' => '#16a34a'],
        'done'   => ['label' => 'Hoàn thành',   'bg' => '#f0fdf4', 'fg' => '#15803d'],
        'cancel' => ['label' => 'Đã hủy',       'bg' => '#fee2e2', 'fg' => '#dc2626'],
    ];
    $s = $map[$state] ?? ['label' => strtoupper($state), 'bg' => '#f1f5f9', 'fg' => '#64748b'];
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:5px;font-size:11px;font-weight:700;background:' . $s['bg'] . ';color:' . $s['fg'] . ';">'
         . htmlspecialchars($s['label']) . '</span>';
}

function invoiceStatusBadge($status) {
    $map = [
        'nothing'    => ['label' => 'Chưa xuất HĐ',    'bg' => '#f1f5f9', 'fg' => '#64748b'],
        'to invoice' => ['label' => 'Cần xuất HĐ',     'bg' => '#fef9c3', 'fg' => '#d97706'],
        'invoiced'   => ['label' => 'Đã xuất HĐ',      'bg' => '#dcfce7', 'fg' => '#16a34a'],
        'no'         => ['label' => 'Không cần xuất HĐ','bg' => '#f1f5f9', 'fg' => '#94a3b8'],
    ];
    $s = $map[$status] ?? ['label' => $status, 'bg' => '#f1f5f9', 'fg' => '#64748b'];
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:5px;font-size:11px;font-weight:600;background:' . $s['bg'] . ';color:' . $s['fg'] . ';">'
         . htmlspecialchars($s['label']) . '</span>';
}

function avatarColor3($name) {
    $palette = [
        ['bg' => '#ddd6fe', 'fg' => '#5b21b6'],
        ['bg' => '#bfdbfe', 'fg' => '#1e40af'],
        ['bg' => '#bbf7d0', 'fg' => '#166534'],
        ['bg' => '#fed7aa', 'fg' => '#9a3412'],
        ['bg' => '#fde68a', 'fg' => '#92400e'],
        ['bg' => '#fbcfe8', 'fg' => '#9d174d'],
        ['bg' => '#a5f3fc', 'fg' => '#164e63'],
        ['bg' => '#d9f99d', 'fg' => '#3f6212'],
    ];
    return $palette[abs(crc32($name ?: '?')) % count($palette)];
}

$statusLabels = ['draft'=>'Nháp','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];
$statusColors = ['draft'=>'#64748b','pending'=>'#d97706','approved'=>'#16a34a','rejected'=>'#dc2626'];
$statusBg     = ['draft'=>'#f1f5f9','pending'=>'#fef9c3','approved'=>'#dcfce7','rejected'=>'#fee2e2'];
$st = $pakd['status'] ?? 'draft';

$pasx_status_labels = [
    'created'      => 'Đã gửi — Đang làm PASX',
    'processing'   => 'Đang xử lý',
    'completed'    => 'Hoàn thành',
    'approved'     => 'Đã duyệt',
    'rejected'     => 'Đã từ chối — Chờ rebuild',
    'pending_ceo'  => 'Chờ CEO duyệt',
    'cancelled'    => 'Đã hủy',
];
$pasx_status_colors = [
    'created'     => '#7c3aed',
    'processing'  => '#2563eb',
    'completed'   => '#16a34a',
    'approved'    => '#16a34a',
    'rejected'    => '#dc2626',
    'pending_ceo' => '#d97706',
    'cancelled'   => '#94a3b8',
];
$pst = $pakd['pasx_status'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pakd['name']) ?> — My Project</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5;
            --success: #16a34a; --warning: #d97706; --danger: #dc2626;
            --bg: #f8fafc; --card: #ffffff; --slate: #1e293b;
            --gray: #64748b; --lgray: #94a3b8; --border: #e2e8f0;
            --r-xl: 18px; --r-lg: 12px; --r-md: 8px; --r-sm: 6px;
            --sh-sm: 0 1px 3px rgba(0,0,0,.06); --sh-md: 0 4px 16px rgba(0,0,0,.08);
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--slate); margin: 0; }
        .main-content { flex: 1; padding: 0; min-height: 100vh; }

        /* ── Breadcrumb + Actions Bar ── */
        .top-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 32px; background: white; border-bottom: 1px solid var(--border);
            gap: 12px; flex-wrap: wrap;
        }
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--gray); }
        .breadcrumb a { color: var(--gray); text-decoration: none; }
        .breadcrumb a:hover { color: var(--primary); }
        .breadcrumb .sep { color: var(--lgray); }
        .breadcrumb .current { color: var(--slate); font-weight: 600; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .top-actions { display: flex; align-items: center; gap: 8px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: var(--r-md); font-size: 13px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; text-decoration: none; transition: all .15s; white-space: nowrap; }
        .btn-outline { background: white; color: var(--gray); border: 1px solid var(--border); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-green { background: #16a34a; color: white; }
        .btn-green:hover { background: #15803d; }

        /* ── Won Banner ── */
        .won-banner {
            display: flex; align-items: center; gap: 16px;
            padding: 14px 32px;
            background: linear-gradient(90deg, #fef3c7 0%, #fde68a 60%, #fef9c3 100%);
            border-bottom: 2px solid #f59e0b;
        }
        .won-banner-icon { font-size: 26px; flex-shrink: 0; }
        .won-banner-body { flex: 1; }
        .won-banner-title { font-size: 14px; font-weight: 800; color: #92400e; }
        .won-banner-sub   { font-size: 12px; color: #b45309; margin-top: 2px; }
        .won-badge { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff; font-size:12px; font-weight:800; padding:5px 14px; border-radius:6px; box-shadow:0 2px 8px rgba(245,158,11,.4); }

        /* ── Page Content ── */
        .page-content { padding: 28px 32px 48px; }

        /* ── Page header ── */
        .page-header { display: flex; align-items: flex-start; gap: 18px; margin-bottom: 28px; }
        .page-icon { width: 52px; height: 52px; background: linear-gradient(135deg, #16a34a, #15803d); border-radius: var(--r-lg); display: flex; align-items: center; justify-content: center; color: white; font-size: 22px; box-shadow: 0 4px 12px rgba(22,163,74,.3); flex-shrink: 0; }
        .page-header-body { flex: 1; min-width: 0; }
        .page-header-body h1 { font-size: 22px; font-weight: 800; margin: 0 0 6px; line-height: 1.2; }
        .page-header-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .status-chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .odoo-link { font-size: 12px; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 500; }
        .odoo-link:hover { text-decoration: underline; }
        .dot-sep { color: var(--lgray); }

        /* ── Metric Cards Row ── */
        .metrics-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .metric-card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--sh-sm); padding: 18px 20px; }
        .metric-card.highlight { border-color: #bbf7d0; background: linear-gradient(135deg, #f0fdf4, #dcfce7); }
        .metric-card.highlight-warn { border-color: #fde68a; background: linear-gradient(135deg, #fffbeb, #fef9c3); }
        .metric-card.highlight-danger { border-color: #fecaca; background: linear-gradient(135deg, #fef2f2, #fee2e2); }
        .metric-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray); margin-bottom: 8px; }
        .metric-val { font-size: 24px; font-weight: 800; color: var(--slate); line-height: 1; }
        .metric-val.green { color: #16a34a; }
        .metric-val.blue  { color: #2563eb; }
        .metric-val.amber { color: #d97706; }
        .metric-sub { font-size: 12px; color: var(--gray); margin-top: 5px; }

        /* ── Two-column layout ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }

        /* ── Cards ── */
        .card { background: var(--card); border-radius: var(--r-lg); border: 1px solid var(--border); box-shadow: var(--sh-sm); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .card-header h3 { font-size: 14px; font-weight: 700; color: var(--slate); margin: 0; }
        .card-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .card-icon.green  { background: rgba(22,163,74,.1);  color: #16a34a; }
        .card-icon.blue   { background: rgba(37,99,235,.1);  color: #2563eb; }
        .card-icon.amber  { background: rgba(217,119,6,.1);  color: #d97706; }
        .card-icon.purple { background: rgba(124,58,237,.1); color: #7c3aed; }
        .card-icon.gray   { background: #f1f5f9;             color: #64748b; }
        .card-body { padding: 16px 20px; }

        /* ── Info Grid ── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .info-item { display: flex; flex-direction: column; gap: 4px; }
        .info-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray); }
        .info-value { font-size: 13px; font-weight: 500; color: var(--slate); display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .info-value.empty { color: var(--lgray); font-style: italic; }
        .info-grid.col-3 { grid-template-columns: 1fr 1fr 1fr; }

        /* ── AM Cell ── */
        .am-cell { display: flex; align-items: center; gap: 8px; }
        .am-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; }
        .am-initials { width: 28px; height: 28px; border-radius: 50%; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        /* ── Financial table ── */
        .fin-row { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); gap: 12px; }
        .fin-row:last-child { border-bottom: none; }
        .fin-row.total { background: #f8fafc; margin: 0 -20px; padding: 10px 20px; }
        .fin-row-label { flex: 1; font-size: 13px; color: var(--slate); }
        .fin-row-label .fin-sub { font-size: 11px; color: var(--lgray); margin-top: 1px; }
        .fin-row-val { font-size: 13px; font-weight: 700; text-align: right; white-space: nowrap; }
        .fin-row-pct { font-size: 11px; color: var(--gray); text-align: right; width: 56px; }
        .fin-row.cat .fin-row-label { font-weight: 700; color: #1e293b; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .fin-row.cat { background: #f8fafc; margin: 0 -20px; padding: 8px 20px; }
        .divider { border: none; border-top: 2px solid var(--border); margin: 4px 0; }

        /* ── PASX Status ── */
        .pasx-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .pasx-log-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .pasx-log-item:last-child { border-bottom: none; }
        .pasx-log-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
        .pasx-log-body { flex: 1; min-width: 0; }
        .pasx-log-event { font-size: 12px; font-weight: 600; color: var(--slate); }
        .pasx-log-date  { font-size: 11px; color: var(--lgray); margin-top: 2px; }

        /* ── Stage timeline ── */
        .stage-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border); }
        .stage-row:last-child { border-bottom: none; }
        .stage-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .stage-body { flex: 1; min-width: 0; }
        .stage-label { font-size: 12px; font-weight: 600; color: var(--slate); }
        .stage-date  { font-size: 11px; color: var(--lgray); margin-top: 1px; }
        .stage-val   { font-size: 12px; color: var(--gray); text-align: right; }

        /* ── Probability stars ── */
        .star-rating { display: inline-flex; gap: 1px; }
        .star-rating .fa-star     { font-size: 13px; color: #f59e0b; }
        .star-rating .fa-star.off { color: #d1d5db; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 28px 20px; color: var(--gray); }
        .empty-state i { font-size: 24px; color: var(--lgray); margin-bottom: 8px; display: block; }
        .empty-state p { font-size: 12px; margin: 0; }

        /* ── Documents ── */
        .drop-zone {
            border: 2px dashed var(--border); border-radius: 10px; padding: 28px 20px;
            text-align: center; cursor: pointer; transition: all .2s; background: #fafafa;
        }
        .drop-zone:hover, .drop-zone.drag-over { border-color: var(--primary); background: rgba(99,102,241,.04); }
        .drop-zone i { font-size: 28px; color: var(--lgray); margin-bottom: 8px; display: block; }
        .drop-zone p { margin: 0; font-size: 13px; color: var(--gray); }
        .drop-zone small { font-size: 11px; color: var(--lgray); }
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 14px; }
        .doc-card {
            border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px;
            background: #fff; display: flex; flex-direction: column; gap: 8px;
            transition: box-shadow .15s; cursor: pointer; position: relative;
        }
        .doc-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); border-color: #cbd5e1; }
        .doc-card-icon { font-size: 28px; }
        .doc-card-name { font-size: 12.5px; font-weight: 600; color: var(--slate); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .doc-card-meta { font-size: 11px; color: var(--lgray); }
        .doc-card-label { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 4px; background: #eff6ff; color: #2563eb; margin-bottom: 2px; }
        .doc-card-actions { display: flex; gap: 6px; margin-top: auto; }
        .doc-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: #f8fafc; color: var(--gray); text-decoration: none; transition: all .15s; }
        .doc-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .doc-btn.danger { color: #dc2626; border-color: #fecaca; background: #fff; }
        .doc-btn.danger:hover { background: #fee2e2; }
        .doc-btn.primary { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
        .doc-btn.primary:hover { background: #dbeafe; }
        /* Preview modal */
        .preview-modal { display:none; position:fixed; inset:0; z-index:9000; background:rgba(15,23,42,.7); align-items:center; justify-content:center; }
        .preview-modal.open { display:flex; }
        .preview-box { background:#fff; border-radius:14px; width:90vw; max-width:1100px; height:88vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.35); }
        .preview-bar { display:flex; align-items:center; gap:12px; padding:12px 18px; border-bottom:1px solid var(--border); flex-shrink:0; }
        .preview-bar-title { flex:1; font-size:13px; font-weight:600; color:var(--slate); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .preview-close { width:30px; height:30px; border-radius:50%; border:none; background:#f1f5f9; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--gray); font-size:14px; }
        .preview-close:hover { background:#e2e8f0; }
        .preview-body { flex:1; overflow:hidden; }
        .preview-body iframe, .preview-body img { width:100%; height:100%; border:none; object-fit:contain; }
        .preview-body .no-preview { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; gap:12px; color:var(--gray); }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .detail-grid { grid-template-columns: 1fr; }
            .metrics-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 680px) {
            .metrics-row { grid-template-columns: 1fr; }
            .page-content { padding: 16px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/mammoth@1.7.1/mammoth.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
    (function() {
        var _prev = localStorage.getItem('sidebar-collapsed');
        if (_prev !== 'true') {
            localStorage.setItem('sidebar-collapsed', 'true');
            window.addEventListener('beforeunload', function() {
                localStorage.setItem('sidebar-collapsed', _prev === null ? 'false' : _prev);
            }, { once: true });
        }
    })();
    </script>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = htmlspecialchars($pakd['name']); include __DIR__ . '/../includes/topbar.php'; ?>

        <!-- Top Bar -->
        <div class="top-bar">
            <div class="breadcrumb">
                <a href="/projects/du-an"><i class="fas fa-trophy" style="font-size:11px;"></i> My Project</a>
                <span class="sep">/</span>
                <span class="current"><?= htmlspecialchars($pakd['name']) ?></span>
            </div>
            <div class="top-actions">
                <a href="/projects/du-an" class="btn btn-outline">
                    <i class="fas fa-arrow-left" style="font-size:11px;"></i> Quay lại danh sách
                </a>
                <?php if ($is_admin || (!empty($pakd['am_user_id']) && (int)$pakd['am_user_id'] === $user_id)): ?>
                <a href="/projects/pakd/edit?id=<?= $pakd_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit" style="font-size:11px;"></i> Chỉnh sửa PAKD
                </a>
                <?php endif; ?>
            </div>
        </div>



        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-icon"><i class="fas fa-trophy"></i></div>
                <div class="page-header-body">
                    <h1><?= htmlspecialchars($pakd['name']) ?></h1>
                    <div class="page-header-meta">
                        <span class="status-chip" style="background:<?= htmlspecialchars($statusBg[$st] ?? '#f1f5f9') ?>;color:<?= htmlspecialchars($statusColors[$st] ?? '#64748b') ?>;">
                            <i class="fas fa-circle" style="font-size:7px;"></i>
                            <?= $statusLabels[$st] ?? $st ?>
                        </span>
                        <?php if (!empty($pakd['department'])): ?>
                        <span style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($pakd['department']) ?></span>
                        <span class="dot-sep">·</span>
                        <?php endif; ?>
                        <?php if (!empty($pakd['company_name'])): ?>
                        <span style="font-size:12px;color:var(--gray);"><i class="fas fa-building" style="font-size:10px;"></i> <?= htmlspecialchars($pakd['company_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($pakd['odoo_url'])): ?>
                        <a href="<?= htmlspecialchars($pakd['odoo_url']) ?>" target="_blank" class="odoo-link">
                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i> Odoo #<?= htmlspecialchars($pakd['odoo_opp_id'] ?? '') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="metrics-row">
                <div class="metric-card">
                    <div class="metric-label"><i class="fas fa-sack-dollar" style="margin-right:4px;color:#d97706;"></i> Giá trị Opp</div>
                    <div class="metric-val blue"><?= formatVND3($pakd['opp_value']) ?></div>
                    <div class="metric-sub"><?= htmlspecialchars($pakd['currency'] ?? 'VND') ?></div>
                </div>
                <div class="metric-card <?= $fin_rev_gross > 0 ? 'highlight' : '' ?>">
                    <div class="metric-label"><i class="fas fa-chart-line" style="margin-right:4px;color:#16a34a;"></i> Doanh thu</div>
                    <div class="metric-val green"><?= $fin_rev_gross > 0 ? formatVND3($fin_rev_gross) : '—' ?></div>
                    <div class="metric-sub">VND</div>
                </div>
                <?php
                $gpSet    = ($fin_gross_profit != 0);
                $gpColor  = !$gpSet ? 'var(--lgray)' : ($fin_gross_profit < 0 ? '#dc2626' : ($fin_margin_pct >= 20 ? '#16a34a' : '#d97706'));
                $gpClass  = !$gpSet ? '' : ($fin_gross_profit < 0 ? 'highlight-danger' : ($fin_margin_pct >= 20 ? 'highlight' : 'highlight-warn'));
                $gpLabel  = !$gpSet ? 'Chưa cập nhật' : ('Margin: ' . number_format($fin_margin_pct, 1) . '%');
                $gpText   = !$gpSet ? '—' : (($fin_gross_profit < 0 ? '-' : '') . formatVND3(abs($fin_gross_profit)));
                ?>
                <div class="metric-card <?= $gpClass ?>" id="gp-card" style="position:relative;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <div class="metric-label"><i class="fas fa-coins" style="margin-right:4px;color:#7c3aed;"></i> Lợi nhuận gộp</div>
                        <button onclick="openFinModal()" title="Cập nhật"
                            style="border:none;background:none;cursor:pointer;color:var(--lgray);padding:2px 4px;border-radius:4px;font-size:12px;line-height:1;"
                            onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--lgray)'">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <div class="metric-val" id="gp-val" style="color:<?= $gpColor ?>">
                        <?= $gpText ?>
                    </div>
                    <div class="metric-sub" id="gp-margin"><?= $gpLabel ?></div>
                </div>
                <?php
                $prob = (int)($pakd['opp_probability'] ?? 0);
                $stars = max(0, min(5, (int)round($prob / 20)));
                ?>
                <div class="metric-card">
                    <div class="metric-label"><i class="fas fa-percent" style="margin-right:4px;color:#2563eb;"></i> Xác suất</div>
                    <div class="metric-val"><?= $prob ?>%</div>
                    <div class="metric-sub" style="display:flex;align-items:center;gap:2px;margin-top:6px;">
                        <span class="star-rating">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <i class="fas fa-star<?= $i<=$stars?'':' off' ?>"></i>
                            <?php endfor; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Two-column detail grid -->
            <div class="detail-grid">

                <!-- Left column -->
                <div>
                    <!-- Project Info -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green"><i class="fas fa-folder-open"></i></div>
                            <h3>Thông tin dự án</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-grid col-3">
                                <div class="info-item">
                                    <div class="info-label">AM / Sales</div>
                                    <div class="info-value">
                                        <?php
                                        $amEmail = $pakd['am_email'] ?? '';
                                        $amUser  = !empty($amEmail) ? ($userAvatarMap[strtolower($amEmail)] ?? null) : null;
                                        $c       = avatarColor3($pakd['am_name'] ?? '');
                                        ?>
                                        <div class="am-cell">
                                            <?php if ($amUser && !empty($amUser['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($amUser['avatar']) ?>" class="am-avatar" alt="">
                                            <?php else:
                                                $parts = array_filter(explode(' ', $pakd['am_name'] ?? ''));
                                                $ini   = strtoupper(($parts[0][0] ?? '') . (count($parts) > 1 ? end($parts)[0] : ''));
                                            ?>
                                                <div class="am-initials" style="background:<?= $c['bg'] ?>;color:<?= $c['fg'] ?>;"><?= htmlspecialchars($ini ?: '?') ?></div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($pakd['am_name'] ?: '—') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Bộ phận</div>
                                    <div class="info-value <?= empty($pakd['department']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['department'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Division</div>
                                    <div class="info-value <?= empty($pakd['division_names']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['division_names'] ?: '—') ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Loại dự án</div>
                                    <div class="info-value">
                                        <?php $pt = strtolower($pakd['project_type'] ?? 'external'); ?>
                                        <i class="fas <?= $pt === 'internal' ? 'fa-building' : 'fa-desktop' ?>" style="font-size:12px;color:var(--gray);"></i>
                                        <?= $pt === 'internal' ? 'Internal' : 'External' ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Ngày assign</div>
                                    <div class="info-value <?= empty($pakd['assignment_date']) ? 'empty' : '' ?>">
                                        <?= !empty($pakd['assignment_date']) ? date('d/m/Y', strtotime($pakd['assignment_date'])) : '—' ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Dự kiến đóng</div>
                                    <div class="info-value <?= empty($pakd['expected_closing']) ? 'empty' : '' ?>">
                                        <?= !empty($pakd['expected_closing']) ? date('d/m/Y', strtotime($pakd['expected_closing'])) : '—' ?>
                                    </div>
                                </div>
                                <div class="info-item" style="grid-column: span 3;">
                                    <div class="info-label">Opportunity</div>
                                    <div class="info-value <?= empty($pakd['opportunity_name']) ? 'empty' : '' ?>">
                                        <?= htmlspecialchars($pakd['opportunity_name'] ?: '—') ?>
                                        <?php if (!empty($pakd['odoo_url'])): ?>
                                        &nbsp;<a href="<?= htmlspecialchars($pakd['odoo_url']) ?>" target="_blank" class="odoo-link">
                                            <i class="fas fa-external-link-alt"></i> Mở Odoo
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <?php if ($fin_rev_gross > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon blue"><i class="fas fa-chart-pie"></i></div>
                            <h3>Tổng quan tài chính</h3>
                        </div>
                        <div class="card-body" style="padding: 0 20px;">
                            <div class="fin-row cat">
                                <div class="fin-row-label">Doanh thu</div>
                                <div class="fin-row-pct">100%</div>
                                <div class="fin-row-val" style="color:#16a34a;"><?= formatVNDFull($fin_rev_gross) ?> VND</div>
                            </div>
                            <?php if ($fin_rev_net !== $fin_rev_gross): ?>
                            <div class="fin-row">
                                <div class="fin-row-label" style="padding-left:12px;">Doanh thu thuần
                                    <div class="fin-sub">Sau giảm trừ</div>
                                </div>
                                <div class="fin-row-pct"></div>
                                <div class="fin-row-val" style="color:#2563eb;"><?= formatVNDFull($fin_rev_net) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($fin_prod_cost > 0): ?>
                            <div class="fin-row">
                                <div class="fin-row-label" style="padding-left:12px;">Chi phí sản xuất (PASX)
                                    <div class="fin-sub"><?= $pasx_has_data ? 'Human + Overtime cost' : 'Từ Phương án sản xuất' ?></div>
                                </div>
                                <div class="fin-row-pct"><?= $fin_rev_net > 0 ? number_format($fin_prod_cost/$fin_rev_net*100,1).'%' : '' ?></div>
                                <div class="fin-row-val" style="color:#dc2626;">-<?= formatVNDFull($fin_prod_cost) ?></div>
                            </div>
                            <?php endif; ?>
                            <hr class="divider">
                            <?php
                            $gpRowBg    = !$gpSet ? '' : ($fin_gross_profit < 0 ? 'background:#fef2f2;' : ($fin_margin_pct >= 20 ? 'background:#f0fdf4;' : 'background:#fffbeb;'));
                            $gpRowColor = !$gpSet ? 'var(--lgray)' : ($fin_gross_profit < 0 ? '#dc2626' : ($fin_margin_pct >= 20 ? '#16a34a' : '#d97706'));
                            $gpRowPct   = $gpSet ? number_format($fin_margin_pct, 1).'%' : '—';
                            $gpRowVal   = $gpSet ? (($fin_gross_profit < 0 ? '-' : '') . formatVNDFull(abs($fin_gross_profit)) . ' VND') : '—';
                            ?>
                            <div class="fin-row cat" style="<?= $gpRowBg ?>">
                                <div class="fin-row-label">Lợi nhuận gộp</div>
                                <div class="fin-row-pct" style="color:<?= $gpRowColor ?>;"><?= $gpRowPct ?></div>
                                <div class="fin-row-val" style="color:<?= $gpRowColor ?>;"><?= $gpRowVal ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sale Orders -->
                    <?php
                    $odooBaseUrl = '';
                    try {
                        $odooSettings = $conn->query("SELECT odoo_url FROM odoo_settings ORDER BY id DESC LIMIT 1")->fetch_assoc();
                        $odooBaseUrl  = rtrim($odooSettings['odoo_url'] ?? '', '/');
                    } catch (\Throwable $e) {}
                    ?>
                    <div class="card">
                        <div class="card-header" style="justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="card-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
                                <h3>Sale Orders
                                    <?php if (!empty($sale_orders)): ?>
                                    <span style="font-size:12px;font-weight:500;color:var(--gray);margin-left:4px;">(<?= count($sale_orders) ?>)</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <?php if (!empty($pakd['odoo_opp_id'])): ?>
                            <span style="font-size:11px;color:var(--lgray);">Opp #<?= htmlspecialchars($pakd['odoo_opp_id']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <?php if ($so_fetch_error): ?>
                                <div style="padding:16px 20px;font-size:12px;color:#dc2626;display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Không thể kết nối Odoo: <?= htmlspecialchars($so_fetch_error) ?>
                                </div>
                            <?php elseif (empty($sale_orders)): ?>
                                <div class="empty-state" style="padding:28px;">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <p>Chưa có Sale Order nào liên kết với opportunity này</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($sale_orders as $soIdx => $so):
                                    // Fields từ odoo_sale_orders table (flat strings, not Odoo API arrays)
                                    $ccy          = $so['currency_name'] ?? 'VND';
                                    $soUrl        = $odooBaseUrl ? $odooBaseUrl . '/web#id=' . $so['odoo_id'] . '&model=sale.order&view_type=form' : '#';
                                    $lines        = $so['_lines'] ?? [];
                                    $amtInvoiced  = (float)($so['amount_invoiced']   ?? 0);
                                    $amtToInvoice = (float)($so['amount_to_invoice'] ?? 0);
                                    $amtTotal     = (float)($so['amount_total']      ?? 0);
                                    $amtUntaxed   = (float)($so['amount_untaxed']    ?? 0);
                                    $amtTax       = (float)($so['amount_tax']        ?? 0);
                                    $invoicedPct  = $amtTotal > 0 ? min(100, round($amtInvoiced / $amtTotal * 100)) : 0;
                                    $salesperson  = $so['user_name']         ?? '—';
                                    $team         = $so['team_name']         ?? '—';
                                    $payTerm      = $so['payment_term_name'] ?? '—';
                                    $bordered     = $soIdx > 0 ? 'border-top:2px solid var(--border);' : '';
                                ?>
                                <!-- SO block -->
                                <div style="<?= $bordered ?>padding:14px 18px;">

                                    <!-- SO header row -->
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                                        <a href="<?= htmlspecialchars($soUrl) ?>" target="_blank"
                                           style="font-size:13px;font-weight:700;color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                                            <i class="fas fa-external-link-alt" style="font-size:9px;"></i>
                                            <?= htmlspecialchars($so['name']) ?>
                                        </a>
                                        <?= soStateBadge($so['state'] ?? 'draft') ?>
                                        <?= invoiceStatusBadge($so['invoice_status'] ?? 'nothing') ?>
                                        <!-- Meta inline: ngày, salesperson, team, nguồn -->
                                        <?php if (!empty($so['date_order'])): ?>
                                        <span style="font-size:11px;color:var(--lgray);">· <?= date('d/m/Y', strtotime($so['date_order'])) ?></span>
                                        <?php endif; ?>
                                        <?php if ($salesperson !== '—'): ?>
                                        <span style="font-size:11px;color:var(--lgray);">· <?= htmlspecialchars($salesperson) ?></span>
                                        <?php endif; ?>
                                        <div style="margin-left:auto;font-size:15px;font-weight:800;color:#1e293b;white-space:nowrap;">
                                            <?= number_format($amtTotal, 0, ',', '.') ?>
                                            <span style="font-size:10px;font-weight:500;color:var(--lgray);margin-left:2px;"><?= htmlspecialchars($ccy) ?></span>
                                        </div>
                                    </div>

                                    <!-- Amounts + progress inline -->
                                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:<?= !empty($lines) ? '10px' : '0' ?>;">
                                        <span style="font-size:11px;color:var(--gray);">Chưa thuế: <strong style="color:var(--slate);"><?= number_format($amtUntaxed, 0, ',', '.') ?></strong></span>
                                        <span style="color:var(--lgray);">·</span>
                                        <span style="font-size:11px;color:#16a34a;">Đã XHĐ: <strong><?= number_format($amtInvoiced, 0, ',', '.') ?></strong></span>
                                        <span style="color:var(--lgray);">·</span>
                                        <span style="font-size:11px;color:<?= $amtToInvoice > 0 ? '#d97706' : '#94a3b8' ?>;">Còn XHĐ: <strong><?= number_format($amtToInvoice, 0, ',', '.') ?></strong></span>
                                        <?php if ($amtTotal > 0): ?>
                                        <div style="flex:1;display:flex;align-items:center;gap:6px;min-width:100px;">
                                            <div style="flex:1;background:#e2e8f0;border-radius:99px;height:5px;overflow:hidden;">
                                                <div style="width:<?= $invoicedPct ?>%;background:<?= $invoicedPct >= 100 ? '#16a34a' : '#3b82f6' ?>;height:100%;"></div>
                                            </div>
                                            <span style="font-size:10px;font-weight:700;color:<?= $invoicedPct >= 100 ? '#16a34a' : '#2563eb' ?>;white-space:nowrap;"><?= $invoicedPct ?>% · <?= $so['invoice_count'] ?? 0 ?> HĐ</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Order lines -->
                                    <?php if (!empty($lines)): ?>
                                    <div style="border:1px solid var(--border);border-radius:7px;overflow:hidden;">
                                    <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                                        <thead>
                                            <tr style="background:#f8fafc;border-bottom:1px solid var(--border);">
                                                <th style="padding:6px 12px;text-align:left;font-size:10px;font-weight:700;color:var(--gray);">Sản phẩm / Dịch vụ</th>
                                                <th style="padding:6px 10px;text-align:right;font-size:10px;font-weight:700;color:var(--gray);">SL</th>
                                                <th style="padding:6px 10px;text-align:right;font-size:10px;font-weight:700;color:var(--gray);">Đơn giá</th>
                                                <th style="padding:6px 10px;text-align:right;font-size:10px;font-weight:700;color:var(--gray);">CK%</th>
                                                <th style="padding:6px 10px;text-align:right;font-size:10px;font-weight:700;color:var(--gray);">Thành tiền</th>
                                                <th style="padding:6px 10px;text-align:right;font-size:10px;font-weight:700;color:#16a34a;">Đã XHĐ</th>
                                                <th style="padding:6px 10px;text-align:right;font-size:10px;font-weight:700;color:#d97706;">Còn XHĐ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($lines as $line):
                                            $prod = is_array($line['product_id']) ? ($line['product_id'][1] ?? $line['name']) : $line['name'];
                                            $uom  = is_array($line['product_uom']) ? ($line['product_uom'][1] ?? '') : '';
                                            $disc = (float)($line['discount'] ?? 0);
                                        ?>
                                        <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                                            <td style="padding:6px 12px;max-width:260px;">
                                                <div style="font-weight:600;color:var(--slate);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($line['name'] ?? '') ?>"><?= htmlspecialchars($prod) ?></div>
                                            </td>
                                            <td style="padding:6px 10px;text-align:right;color:var(--gray);"><?= number_format((float)($line['product_uom_qty'] ?? 0), 2, ',', '.') ?><span style="font-size:9px;color:var(--lgray);margin-left:1px;"><?= htmlspecialchars($uom) ?></span></td>
                                            <td style="padding:6px 10px;text-align:right;font-variant-numeric:tabular-nums;"><?= number_format((float)($line['price_unit'] ?? 0), 0, ',', '.') ?></td>
                                            <td style="padding:6px 10px;text-align:right;color:<?= $disc > 0 ? '#d97706' : 'var(--lgray)' ?>;"><?= $disc > 0 ? number_format($disc, 1).'%' : '—' ?></td>
                                            <td style="padding:6px 10px;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;"><?= number_format((float)($line['price_subtotal'] ?? 0), 0, ',', '.') ?></td>
                                            <td style="padding:6px 10px;text-align:right;color:#16a34a;font-variant-numeric:tabular-nums;"><?= number_format((float)($line['qty_invoiced'] ?? 0), 2, ',', '.') ?></td>
                                            <td style="padding:6px 10px;text-align:right;color:<?= (float)($line['qty_to_invoice'] ?? 0) > 0 ? '#d97706' : 'var(--lgray)' ?>;font-variant-numeric:tabular-nums;"><?= number_format((float)($line['qty_to_invoice'] ?? 0), 2, ',', '.') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="background:#f8fafc;border-top:1px solid var(--border);">
                                                <td colspan="4" style="padding:6px 12px;font-size:11px;font-weight:700;color:var(--gray);">Tổng</td>
                                                <td style="padding:6px 10px;text-align:right;font-weight:800;color:#2563eb;font-variant-numeric:tabular-nums;">
                                                    <?= number_format($amtUntaxed, 0, ',', '.') ?> <span style="font-size:9px;color:var(--lgray);"><?= htmlspecialchars($ccy) ?></span>
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($so['note'])): ?>
                                    <div style="margin-top:8px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:11.5px;color:#92400e;">
                                        <i class="fas fa-sticky-note" style="margin-right:4px;color:#d97706;"></i><?= nl2br(htmlspecialchars($so['note'])) ?>
                                    </div>
                                    <?php endif; ?>

                                </div><!-- /SO block -->
                                <?php endforeach; ?>

                                <!-- Footer tổng nếu nhiều SO -->
                                <?php if (count($sale_orders) > 1):
                                    $soGrandTotal    = array_sum(array_column($sale_orders, 'amount_total'));
                                    $soGrandInvoiced = array_sum(array_column($sale_orders, 'amount_invoiced'));
                                    $soGrandPending  = array_sum(array_column($sale_orders, 'amount_to_invoice'));
                                    $ccy0 = $sale_orders[0]['currency_name'] ?? 'VND';
                                ?>
                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;background:#1e293b;padding:12px 20px;gap:16px;">
                                    <div>
                                        <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;">Tổng <?= count($sale_orders) ?> SO</div>
                                        <div style="font-size:15px;font-weight:800;color:#fff;"><?= number_format($soGrandTotal, 0, ',', '.') ?> <span style="font-size:10px;color:#94a3b8;"><?= htmlspecialchars($ccy0) ?></span></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px;color:#86efac;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;">Đã xuất HĐ</div>
                                        <div style="font-size:15px;font-weight:800;color:#4ade80;"><?= number_format($soGrandInvoiced, 0, ',', '.') ?> <span style="font-size:10px;color:#94a3b8;"><?= htmlspecialchars($ccy0) ?></span></div>
                                    </div>
                                    <div>
                                        <div style="font-size:10px;color:#fde68a;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;">Còn cần xuất HĐ</div>
                                        <div style="font-size:15px;font-weight:800;color:#fbbf24;"><?= number_format($soGrandPending, 0, ',', '.') ?> <span style="font-size:10px;color:#94a3b8;"><?= htmlspecialchars($ccy0) ?></span></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Invoices -->
                    <div class="card">
                        <div class="card-header" style="justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="card-icon" style="background:rgba(220,38,38,.1);color:#dc2626;"><i class="fas fa-receipt"></i></div>
                                <h3>Hoá đơn (Odoo)
                                    <?php if (!empty($invoices)): ?>
                                    <span style="font-size:12px;font-weight:500;color:var(--gray);margin-left:4px;">(<?= count($invoices) ?>)</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <?php if ($inv_fetch_error): ?>
                            <span style="font-size:11px;color:#dc2626;"><i class="fas fa-exclamation-circle"></i> Lỗi</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <?php if ($inv_fetch_error): ?>
                                <div style="padding:16px 20px;font-size:12px;color:#dc2626;display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($inv_fetch_error) ?>
                                </div>
                            <?php elseif (empty($invoices)): ?>
                                <div class="empty-state" style="padding:28px;">
                                    <i class="fas fa-receipt"></i>
                                    <p>Chưa có hoá đơn nào được liên kết với dự án này</p>
                                </div>
                            <?php else: ?>
                                <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                                    <thead>
                                        <tr style="background:#f8fafc;border-bottom:1px solid var(--border);">
                                            <th style="padding:9px 14px;text-align:left;font-size:10.5px;font-weight:700;color:var(--gray);white-space:nowrap;">Số hoá đơn</th>
                                            <th style="padding:9px 10px;text-align:left;font-size:10.5px;font-weight:700;color:var(--gray);white-space:nowrap;">Trạng thái</th>
                                            <th style="padding:9px 10px;text-align:left;font-size:10.5px;font-weight:700;color:var(--gray);white-space:nowrap;">Thanh toán</th>
                                            <th style="padding:9px 10px;text-align:left;font-size:10.5px;font-weight:700;color:var(--gray);white-space:nowrap;">Ngày HĐ</th>
                                            <th style="padding:9px 10px;text-align:left;font-size:10.5px;font-weight:700;color:var(--gray);white-space:nowrap;">Hạn TT</th>
                                            <th style="padding:9px 10px;text-align:right;font-size:10.5px;font-weight:700;color:var(--gray);white-space:nowrap;">Tổng</th>
                                            <th style="padding:9px 10px;text-align:right;font-size:10.5px;font-weight:700;color:#dc2626;white-space:nowrap;">Còn nợ</th>
                                            <th style="padding:9px 10px;text-align:left;font-size:10.5px;font-weight:700;color:var(--gray);min-width:100px;">Tiến độ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($invoices as $inv):
                                        $invName     = $inv['name'] ?: ($inv['highest_name'] ?? 'Draft');
                                        $invId       = (int)($inv['odoo_id'] ?? 0);
                                        $invUrl      = $odooBaseUrl ? $odooBaseUrl . '/web#id=' . $invId . '&model=account.move&view_type=form' : '#';
                                        $invCcy      = $inv['currency_name'] ?? 'VND';
                                        $amtTotal    = (float)($inv['amount_total']    ?? 0);
                                        $amtResidual = (float)($inv['amount_residual'] ?? 0);
                                        $paidPct     = $amtTotal > 0 ? min(100, round(($amtTotal - $amtResidual) / $amtTotal * 100)) : 0;
                                        $isRefund    = ($inv['move_type'] ?? '') === 'out_refund';
                                    ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                                        <td style="padding:9px 14px;">
                                            <a href="<?= htmlspecialchars($invUrl) ?>" target="_blank"
                                               style="font-weight:700;color:#dc2626;text-decoration:none;display:inline-flex;align-items:center;gap:5px;font-size:12.5px;">
                                                <i class="fas fa-external-link-alt" style="font-size:9px;"></i>
                                                <?= htmlspecialchars($invName) ?>
                                            </a>
                                            <?php if ($isRefund): ?>
                                            <span style="margin-left:4px;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;background:#f5f3ff;color:#7c3aed;">CR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:9px 10px;"><?= invStateBadge($inv['state'] ?? 'draft') ?></td>
                                        <td style="padding:9px 10px;"><?= invPaymentBadge($inv['payment_state'] ?? 'not_paid') ?></td>
                                        <td style="padding:9px 10px;color:var(--gray);font-size:12px;white-space:nowrap;">
                                            <?= !empty($inv['invoice_date']) ? date('d/m/Y', strtotime($inv['invoice_date'])) : '—' ?>
                                        </td>
                                        <td style="padding:9px 10px;color:var(--gray);font-size:12px;white-space:nowrap;">
                                            <?= !empty($inv['invoice_date_due']) ? date('d/m/Y', strtotime($inv['invoice_date_due'])) : '—' ?>
                                        </td>
                                        <td style="padding:9px 10px;text-align:right;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;">
                                            <?= number_format($amtTotal, 0, ',', '.') ?>
                                            <span style="font-size:9px;color:var(--lgray);margin-left:2px;"><?= htmlspecialchars($invCcy) ?></span>
                                        </td>
                                        <td style="padding:9px 10px;text-align:right;font-weight:700;color:<?= $amtResidual > 0 ? '#dc2626' : '#16a34a' ?>;font-variant-numeric:tabular-nums;white-space:nowrap;">
                                            <?= number_format($amtResidual, 0, ',', '.') ?>
                                            <span style="font-size:9px;color:var(--lgray);margin-left:2px;"><?= htmlspecialchars($invCcy) ?></span>
                                        </td>
                                        <td style="padding:9px 10px;min-width:100px;">
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <div style="flex:1;background:#e2e8f0;border-radius:99px;height:5px;overflow:hidden;">
                                                    <div style="width:<?= $paidPct ?>%;background:<?= $paidPct >= 100 ? '#16a34a' : '#dc2626' ?>;height:100%;"></div>
                                                </div>
                                                <span style="font-size:10px;font-weight:700;color:<?= $paidPct >= 100 ? '#16a34a' : '#dc2626' ?>;white-space:nowrap;"><?= $paidPct ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <?php if (count($invoices) > 1):
                                        $invGrandTotal    = array_sum(array_column($invoices, 'amount_total'));
                                        $invGrandResidual = array_sum(array_column($invoices, 'amount_residual'));
                                        $ccy0inv = $invoices[0]['currency_name'] ?? 'VND';
                                    ?>
                                    <tfoot>
                                        <tr style="background:#f8fafc;border-top:2px solid var(--border);">
                                            <td colspan="5" style="padding:8px 14px;font-size:12px;font-weight:700;color:var(--gray);">Tổng <?= count($invoices) ?> hoá đơn</td>
                                            <td style="padding:8px 10px;text-align:right;font-weight:800;color:var(--slate);font-variant-numeric:tabular-nums;white-space:nowrap;">
                                                <?= number_format($invGrandTotal, 0, ',', '.') ?>
                                                <span style="font-size:9px;color:var(--lgray);margin-left:2px;"><?= htmlspecialchars($ccy0inv) ?></span>
                                            </td>
                                            <td style="padding:8px 10px;text-align:right;font-weight:800;color:#dc2626;font-variant-numeric:tabular-nums;white-space:nowrap;">
                                                <?= number_format($invGrandResidual, 0, ',', '.') ?>
                                                <span style="font-size:9px;color:var(--lgray);margin-left:2px;"><?= htmlspecialchars($ccy0inv) ?></span>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </table>
                                </div>

                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Documents -->
                    <div class="card" id="docs-card">
                        <div class="card-header" style="justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="card-icon" style="background:rgba(99,102,241,.1);color:#6366f1;"><i class="fas fa-paperclip"></i></div>
                                <h3>Tài liệu dự án
                                    <span id="doc-count-badge" style="font-size:12px;font-weight:500;color:var(--gray);margin-left:4px;">(<?= count($doc_list) ?>)</span>
                                </h3>
                            </div>
                            <button onclick="document.getElementById('doc-file-input').click()"
                                style="display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:7px;border:1px solid #c7d2fe;background:#eff6ff;color:#4f46e5;font-size:12px;font-weight:600;cursor:pointer;">
                                <i class="fas fa-upload" style="font-size:10px;"></i> Upload
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Drop zone -->
                            <div class="drop-zone" id="drop-zone" onclick="document.getElementById('doc-file-input').click()">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <p>Kéo thả file vào đây hoặc <strong style="color:var(--primary);">click để chọn</strong></p>
                                <small>PDF · DOCX · XLSX · PPTX · PNG · JPG · ZIP &nbsp;|&nbsp; Tối đa 50MB</small>
                            </div>
                            <input type="file" id="doc-file-input" style="display:none" multiple
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.webp,.zip,.txt,.csv">

                            <!-- Upload progress -->
                            <div id="upload-progress" style="display:none;margin-top:10px;">
                                <div style="height:4px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
                                    <div id="progress-bar" style="height:100%;background:var(--primary);border-radius:99px;width:0%;transition:width .3s;"></div>
                                </div>
                                <div id="progress-text" style="font-size:11px;color:var(--gray);margin-top:4px;"></div>
                            </div>

                            <!-- File list -->
                            <div class="doc-grid" id="doc-grid">
                            <?php foreach ($doc_list as $doc):
                                $dExt  = strtolower(pathinfo($doc['original_name'], PATHINFO_EXTENSION));
                                [$dIcon, $dColor] = fileIcon($dExt);
                                $dUrl  = $site_host . $doc['file_path'];
                            ?>
                            <div class="doc-card" id="doc-<?= $doc['id'] ?>">
                                <?php if ($doc['doc_label']): ?>
                                <span class="doc-card-label"><?= htmlspecialchars($doc['doc_label']) ?></span>
                                <?php endif; ?>
                                <div class="doc-card-icon" style="color:<?= $dColor ?>;"><i class="<?= $dIcon ?>"></i></div>
                                <div class="doc-card-name" title="<?= htmlspecialchars($doc['original_name']) ?>"><?= htmlspecialchars($doc['original_name']) ?></div>
                                <div class="doc-card-meta">
                                    <?= formatFileSize($doc['file_size']) ?> &nbsp;·&nbsp;
                                    <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                                    <?php if ($doc['uploaded_by_name']): ?><br><?= htmlspecialchars($doc['uploaded_by_name']) ?><?php endif; ?>
                                </div>
                                <div class="doc-card-actions">
                                    <?php if (canPreview($dExt)): ?>
                                    <button class="doc-btn primary" onclick="previewDoc('<?= htmlspecialchars($dUrl, ENT_QUOTES) ?>','<?= htmlspecialchars($doc['original_name'], ENT_QUOTES) ?>','<?= $dExt ?>')">
                                        <i class="fas fa-eye" style="font-size:10px;"></i> Xem
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($dUrl) ?>" download="<?= htmlspecialchars($doc['original_name']) ?>" class="doc-btn">
                                        <i class="fas fa-download" style="font-size:10px;"></i>
                                    </a>
                                    <?php if ($is_admin || (int)$doc['uploaded_by'] === $user_id): ?>
                                    <button class="doc-btn danger" onclick="deleteDoc(<?= $doc['id'] ?>, <?= $pakd_id ?>)">
                                        <i class="fas fa-trash" style="font-size:10px;"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($doc_list)): ?>
                            <div id="doc-empty" style="grid-column:1/-1;text-align:center;padding:20px;color:var(--lgray);font-size:13px;">
                                <i class="fas fa-folder-open" style="font-size:22px;display:block;margin-bottom:6px;"></i>
                                Chưa có tài liệu nào
                            </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right column -->
                <div>
                    <!-- PASX Status -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon purple"><i class="fas fa-cogs"></i></div>
                            <h3>Phương án sản xuất (PASX)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pakd['pasx_id'])): ?>
                            <div style="margin-bottom:12px;">
                                <div class="info-label" style="margin-bottom:6px;">Trạng thái</div>
                                <?php
                                $pstColor = $pasx_status_colors[$pst] ?? '#64748b';
                                $pstLabel = $pasx_status_labels[$pst] ?? strtoupper($pst);
                                ?>
                                <span class="pasx-status-badge" style="background:<?= $pstColor ?>1a;color:<?= $pstColor ?>;border:1px solid <?= $pstColor ?>44;">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    <?= htmlspecialchars($pstLabel) ?>
                                </span>
                            </div>
                            <div style="margin-bottom:12px;">
                                <div class="info-label" style="margin-bottom:4px;">PASX ID</div>
                                <code style="font-size:11px;background:#f1f5f9;padding:3px 7px;border-radius:4px;color:#475569;"><?= htmlspecialchars($pakd['pasx_id']) ?></code>
                            </div>
                            <?php if (!empty($pakd['pasx_requested_at'])): ?>
                            <div style="margin-bottom:12px;">
                                <div class="info-label" style="margin-bottom:4px;">Gửi yêu cầu lúc</div>
                                <div style="font-size:12px;color:var(--gray);"><?= date('d/m/Y H:i', strtotime($pakd['pasx_requested_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($pasx_has_data): ?>
                            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:4px;">
                                <div style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Chi phí nhận từ PASX</div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                    <span style="color:#64748b;">Human cost</span>
                                    <span style="font-weight:600;"><?= formatVNDFull($fin_human_cost) ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:12px;">
                                    <span style="color:#64748b;">Overtime cost</span>
                                    <span style="font-weight:600;"><?= formatVNDFull($fin_overtime) ?></span>
                                </div>
                                <hr style="border:none;border-top:1px solid #bbf7d0;margin:8px 0;">
                                <div style="display:flex;justify-content:space-between;font-size:13px;">
                                    <span style="font-weight:700;color:#15803d;">Tổng chi phí SX</span>
                                    <span style="font-weight:800;color:#15803d;"><?= formatVNDFull($fin_prod_cost) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Chưa gửi yêu cầu Production Plan</p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($pasx_logs)): ?>
                            <div style="margin-top:14px;">
                                <div class="info-label" style="margin-bottom:8px;">Lịch sử PASX gần đây</div>
                                <?php foreach ($pasx_logs as $log):
                                    $logColor = $log['status'] === 'completed' || $log['status'] === 'approved' ? '#16a34a'
                                              : ($log['status'] === 'rejected' ? '#dc2626' : '#7c3aed');
                                ?>
                                <div class="pasx-log-item">
                                    <div class="pasx-log-dot" style="background:<?= $logColor ?>;"></div>
                                    <div class="pasx-log-body">
                                        <div class="pasx-log-event"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($log['event'] ?? ''))) ?></div>
                                        <div class="pasx-log-date"><?= !empty($log['received_at']) ? date('d/m/Y H:i', strtotime($log['received_at'])) : '—' ?></div>
                                    </div>
                                    <div style="font-size:11px;color:<?= $logColor ?>;font-weight:600;"><?= strtoupper($log['status'] ?? '') ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Approval info -->
                    <?php if ($st === 'approved' && !empty($pakd['approved_by_name'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon green"><i class="fas fa-certificate"></i></div>
                            <h3>Thông tin duyệt</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Phê duyệt bởi</div>
                                    <div class="info-value"><?= htmlspecialchars($pakd['approved_by_name']) ?></div>
                                </div>
                                <?php if (!empty($pakd['approved_at'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Ngày duyệt</div>
                                    <div class="info-value"><?= date('d/m/Y H:i', strtotime($pakd['approved_at'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Key Dates -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon gray"><i class="fas fa-calendar-alt"></i></div>
                            <h3>Mốc thời gian</h3>
                        </div>
                        <div class="card-body" style="padding: 8px 20px;">
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-star" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Deal Won</div>
                                    <div class="stage-date">Cập nhật từ Odoo CRM</div>
                                </div>
                                <div class="stage-val"><?= !empty($pakd['assignment_date']) ? date('d/m/Y', strtotime($pakd['assignment_date'])) : '—' ?></div>
                            </div>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-calendar" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Dự kiến đóng</div>
                                    <div class="stage-date">Expected closing</div>
                                </div>
                                <div class="stage-val"><?= !empty($pakd['expected_closing']) ? date('d/m/Y', strtotime($pakd['expected_closing'])) : '—' ?></div>
                            </div>
                            <?php if (!empty($pakd['pasx_requested_at'])): ?>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-cog" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Gửi PASX</div>
                                    <div class="stage-date">Yêu cầu Production Plan</div>
                                </div>
                                <div class="stage-val"><?= date('d/m/Y', strtotime($pakd['pasx_requested_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($pakd['approved_at'])): ?>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-check" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Phê duyệt</div>
                                    <div class="stage-date">Bởi <?= htmlspecialchars($pakd['approved_by_name'] ?? '—') ?></div>
                                </div>
                                <div class="stage-val"><?= date('d/m/Y', strtotime($pakd['approved_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($pakd['created_at'])): ?>
                            <div class="stage-row">
                                <div class="stage-icon" style="background:#f8fafc;color:#64748b;"><i class="fas fa-clock" style="font-size:11px;"></i></div>
                                <div class="stage-body">
                                    <div class="stage-label">Tạo PAKD</div>
                                    <div class="stage-date">Trong hệ thống</div>
                                </div>
                                <div class="stage-val"><?= date('d/m/Y', strtotime($pakd['created_at'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </div><!-- /page-content -->
    </div>
</div>

<!-- ── Financial Edit Modal ── -->
<div id="fin-modal" style="display:none;position:fixed;inset:0;z-index:8000;background:rgba(15,23,42,.6);align-items:center;justify-content:center;" onclick="if(event.target===this)closeFinModal()">
    <div style="background:#fff;border-radius:14px;width:420px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-size:16px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-coins" style="color:#7c3aed;"></i> Cập nhật tài chính
            </div>
            <button onclick="closeFinModal()" style="border:none;background:#f1f5f9;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:13px;color:#64748b;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:6px;">Doanh thu (VND)</label>
                <input id="fin-revenue" type="number" min="0" step="1000000" placeholder="vd: 280000000"
                    style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;"
                    onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                    value="<?= (int)$pakd['revenue'] ?>">
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:6px;">Lợi nhuận gộp (VND)</label>
                <input id="fin-gp" type="number" min="0" step="1000000" placeholder="vd: 83000000"
                    style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;"
                    onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                    value="<?= (int)$pakd['gross_profit'] ?>">
            </div>
            <!-- Live margin preview -->
            <div id="fin-preview" style="background:#f8fafc;border-radius:8px;padding:10px 14px;font-size:13px;color:#64748b;text-align:center;">
                Margin: <strong id="fin-margin-live" style="color:#1e293b;">—</strong>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button onclick="closeFinModal()" style="flex:1;padding:9px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;color:#64748b;">Hủy</button>
            <button onclick="saveFinancials()" style="flex:2;padding:9px;border:none;border-radius:8px;background:#7c3aed;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">
                <i class="fas fa-save" style="margin-right:5px;"></i> Lưu
            </button>
        </div>
    </div>
</div>

<script>
function openFinModal() {
    document.getElementById('fin-modal').style.display = 'flex';
    calcMarginLive();
}
function closeFinModal() {
    document.getElementById('fin-modal').style.display = 'none';
}
function calcMarginLive() {
    const rev = parseFloat(document.getElementById('fin-revenue').value) || 0;
    const gp  = parseFloat(document.getElementById('fin-gp').value) || 0;
    const pct = rev > 0 ? (gp / rev * 100).toFixed(1) : '—';
    const el  = document.getElementById('fin-margin-live');
    el.textContent = pct !== '—' ? pct + '%' : '—';
    el.style.color = pct !== '—' ? (parseFloat(pct) >= 20 ? '#16a34a' : '#d97706') : '#1e293b';
}
document.getElementById('fin-revenue')?.addEventListener('input', calcMarginLive);
document.getElementById('fin-gp')?.addEventListener('input', calcMarginLive);

async function saveFinancials() {
    const revenue     = parseFloat(document.getElementById('fin-revenue').value)  || 0;
    const grossProfit = parseFloat(document.getElementById('fin-gp').value)       || 0;

    const fd = new FormData();
    fd.append('action',       'update_gp');
    fd.append('pakd_id',      '<?= $pakd_id ?>');
    fd.append('revenue',      revenue.toString());
    fd.append('gross_profit', grossProfit.toString());

    try {
        const res  = await fetch("/projects/du-an/detail?id=<?= $pakd_id ?>", { method: 'POST', body: fd });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { console.error('Non-JSON response:', text); showToast('Lỗi server', 'error'); return; }

        if (data.ok) {
            const gp     = data.gross_profit;
            const rev    = data.revenue;
            const margin = rev > 0 ? (gp / rev * 100) : 0;
            const fmt    = n => { const a = Math.abs(n); return (n<0?'-':'') + (a>=1e9?(a/1e9).toFixed(2)+' tỷ':a>=1e6?(a/1e6).toFixed(1)+' triệu':a>=1e3?Math.round(a/1e3)+'K':String(a)); };
            const gpSet  = gp !== 0;
            document.getElementById('gp-val').textContent = gpSet ? fmt(gp) : '—';
            document.getElementById('gp-val').style.color = !gpSet ? 'var(--lgray)' : (gp < 0 ? '#dc2626' : (margin >= 20 ? '#16a34a' : '#d97706'));
            document.getElementById('gp-margin').textContent = gpSet ? 'Margin: ' + margin.toFixed(1) + '%' : 'Chưa cập nhật';
            closeFinModal();
            showToast('Đã lưu thành công', 'success');
        } else {
            showToast('Lỗi: ' + (data.msg || data.err || 'Không rõ'), 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối: ' + e.message, 'error');
    }
}
</script>

<!-- ── Preview Modal ── -->
<div class="preview-modal" id="preview-modal" onclick="closePreview(event)">
    <div class="preview-box" onclick="event.stopPropagation()">
        <div class="preview-bar">
            <i class="fas fa-file" style="color:var(--lgray);font-size:14px;" id="preview-icon"></i>
            <div class="preview-bar-title" id="preview-title">—</div>
            <a id="preview-download" href="#" download class="doc-btn" style="flex-shrink:0;">
                <i class="fas fa-download" style="font-size:11px;"></i> Tải về
            </a>
            <button class="preview-close" onclick="closePreview()"><i class="fas fa-times"></i></button>
        </div>
        <div class="preview-body" id="preview-body"></div>
    </div>
</div>

<script>
// ── File upload ──────────────────────────────────────────────────────────────
const fileInput   = document.getElementById('doc-file-input');
const dropZone    = document.getElementById('drop-zone');
const pakdId      = <?= $pakd_id ?>;

fileInput.addEventListener('change', () => uploadFiles(fileInput.files));

['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('drag-over'); }));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('drag-over'); }));
dropZone.addEventListener('drop', ev => uploadFiles(ev.dataTransfer.files));

async function uploadFiles(files) {
    if (!files || files.length === 0) return;
    const prog = document.getElementById('upload-progress');
    const bar  = document.getElementById('progress-bar');
    const txt  = document.getElementById('progress-text');
    prog.style.display = 'block';

    for (let i = 0; i < files.length; i++) {
        const f   = files[i];
        const pct = Math.round((i / files.length) * 100);
        bar.style.width = pct + '%';
        txt.textContent = `Đang upload: ${f.name} (${i+1}/${files.length})`;

        const fd = new FormData();
        fd.append('action',   'upload_doc');
        fd.append('pakd_id',  pakdId);
        fd.append('file',     f);

        try {
            const res  = await fetch(window.location.pathname + '?id=' + pakdId, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                appendDocCard(data);
                document.getElementById('doc-empty')?.remove();
                updateDocCount(1);
            } else {
                showToast(data.msg || 'Lỗi upload', 'error');
            }
        } catch(e) {
            showToast('Lỗi kết nối', 'error');
        }
    }
    bar.style.width = '100%';
    txt.textContent = 'Hoàn thành!';
    setTimeout(() => { prog.style.display = 'none'; bar.style.width = '0%'; }, 1500);
    fileInput.value = '';
}

function appendDocCard(data) {
    const grid   = document.getElementById('doc-grid');
    const extMap = {
        pdf:'fa-file-pdf', doc:'fa-file-word', docx:'fa-file-word',
        xls:'fa-file-excel', xlsx:'fa-file-excel', ppt:'fa-file-powerpoint', pptx:'fa-file-powerpoint',
        png:'fa-file-image', jpg:'fa-file-image', jpeg:'fa-file-image', gif:'fa-file-image', webp:'fa-file-image',
        zip:'fa-file-zipper', txt:'fa-file-lines', csv:'fa-file-csv'
    };
    const colorMap = {
        pdf:'#dc2626', doc:'#2563eb', docx:'#2563eb', xls:'#16a34a', xlsx:'#16a34a',
        ppt:'#ea580c', pptx:'#ea580c', png:'#7c3aed', jpg:'#7c3aed', jpeg:'#7c3aed',
        gif:'#7c3aed', webp:'#7c3aed', zip:'#d97706', txt:'#64748b', csv:'#16a34a'
    };
    const previewExts = ['pdf','png','jpg','jpeg','gif','webp','doc','docx','ppt','pptx','xls','xlsx'];
    const icon  = extMap[data.ext]  || 'fa-file';
    const color = colorMap[data.ext] || '#94a3b8';
    const url   = '<?= $site_host ?>' + data.path;
    const size  = data.size >= 1024*1024 ? (data.size/1024/1024).toFixed(1)+' MB' : Math.round(data.size/1024)+' KB';
    const canPrev = previewExts.includes(data.ext);
    const today = new Date().toLocaleDateString('vi-VN');

    const card = document.createElement('div');
    card.className = 'doc-card';
    card.id = 'doc-' + data.id;
    card.innerHTML = `
        ${data.label ? `<span class="doc-card-label">${escHtml(data.label)}</span>` : ''}
        <div class="doc-card-icon" style="color:${color};"><i class="fas ${icon}"></i></div>
        <div class="doc-card-name" title="${escHtml(data.name)}">${escHtml(data.name)}</div>
        <div class="doc-card-meta">${size} · ${today}<br>${escHtml(data.uploader || '')}</div>
        <div class="doc-card-actions">
            ${canPrev ? `<button class="doc-btn primary" onclick="previewDoc('${escHtml(url)}','${escHtml(data.name)}','${data.ext}')"><i class="fas fa-eye" style="font-size:10px;"></i> Xem</button>` : ''}
            <a href="${escHtml(url)}" download="${escHtml(data.name)}" class="doc-btn"><i class="fas fa-download" style="font-size:10px;"></i></a>
            <button class="doc-btn danger" onclick="deleteDoc(${data.id}, ${pakdId})"><i class="fas fa-trash" style="font-size:10px;"></i></button>
        </div>`;
    grid.prepend(card);
}

function updateDocCount(delta) {
    const badge = document.getElementById('doc-count-badge');
    const cur   = parseInt(badge.textContent.replace(/\D/g, '')) || 0;
    badge.textContent = '(' + Math.max(0, cur + delta) + ')';
}

// ── Delete doc ───────────────────────────────────────────────────────────────
async function deleteDoc(docId, pid) {
    if (!confirm('Xóa file này?')) return;
    const fd = new FormData();
    fd.append('action',   'delete_doc');
    fd.append('doc_id',   docId);
    fd.append('pakd_id',  pid);
    const res  = await fetch(window.location.pathname + '?id=' + pakdId, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
        document.getElementById('doc-' + docId)?.remove();
        updateDocCount(-1);
        showToast('Đã xóa file', 'success');
        if (document.getElementById('doc-grid').children.length === 0) {
            document.getElementById('doc-grid').innerHTML = `<div id="doc-empty" style="grid-column:1/-1;text-align:center;padding:20px;color:var(--lgray);font-size:13px;"><i class="fas fa-folder-open" style="font-size:22px;display:block;margin-bottom:6px;"></i>Chưa có tài liệu nào</div>`;
        }
    } else {
        showToast(data.msg || 'Lỗi xóa file', 'error');
    }
}

// ── Preview ──────────────────────────────────────────────────────────────────
function previewDoc(url, name, ext) {
    const modal = document.getElementById('preview-modal');
    const body  = document.getElementById('preview-body');
    const title = document.getElementById('preview-title');
    const dl    = document.getElementById('preview-download');
    title.textContent = name;
    dl.href = url; dl.download = name;
    body.innerHTML = '';

    const images   = ['png','jpg','jpeg','gif','webp'];
    const officeFmt = ['doc','docx','xls','xlsx','ppt','pptx'];

    if (images.includes(ext)) {
        body.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:contain;background:#1e293b;">`;
    } else if (ext === 'pdf') {
        body.innerHTML = `<iframe src="${url}" style="width:100%;height:100%;border:none;" type="application/pdf"></iframe>`;
    } else if (ext === 'docx' || ext === 'doc') {
        // Render DOCX client-side bằng mammoth.js
        body.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:center;height:100%;gap:10px;color:#64748b;font-size:13px;">
                <i class="fas fa-spinner fa-spin"></i> Đang tải tài liệu...
            </div>`;
        fetch(url)
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.arrayBuffer(); })
            .then(buf => mammoth.convertToHtml({ arrayBuffer: buf }))
            .then(result => {
                body.innerHTML = `
                    <div style="height:100%;overflow-y:auto;background:#f8fafc;padding:0;">
                        <div style="max-width:820px;margin:0 auto;background:#fff;padding:48px 56px;min-height:100%;box-shadow:0 0 0 1px #e2e8f0;font-family:'Times New Roman',serif;font-size:14px;line-height:1.7;color:#1e293b;">
                            ${result.value || '<p style="color:#94a3b8;text-align:center;">Tài liệu trống hoặc không đọc được nội dung.</p>'}
                        </div>
                    </div>`;
            })
            .catch(err => {
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:14px;background:#f8fafc;">
                        <i class="fas fa-exclamation-circle" style="font-size:40px;color:#dc2626;"></i>
                        <div style="font-size:13px;color:#64748b;">Không thể đọc file (${err.message})</div>
                        <a href="${url}" download="${escHtml(name)}" style="display:inline-flex;align-items:center;gap:7px;padding:9px 20px;background:#2563eb;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                            <i class="fas fa-download"></i> Tải về máy
                        </a>
                    </div>`;
            });
    } else if (ext === 'xls' || ext === 'xlsx') {
        // Render Excel client-side bằng SheetJS
        body.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:center;height:100%;gap:10px;color:#64748b;font-size:13px;">
                <i class="fas fa-spinner fa-spin"></i> Đang tải file Excel...
            </div>`;
        fetch(url)
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.arrayBuffer(); })
            .then(buf => {
                const wb = XLSX.read(buf, { type: 'array', cellStyles: true });
                // Tạo tab cho từng sheet
                const sheets = wb.SheetNames;
                let tabsHtml = sheets.length > 1
                    ? `<div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;background:#f8fafc;padding:0 16px;flex-shrink:0;">
                        ${sheets.map((s,i) => `<button onclick="xlsShowSheet(this,'sheet-${i}')" data-active="${i===0?'1':'0'}"
                            style="padding:8px 16px;border:none;background:${i===0?'#fff':'transparent'};font-size:12px;font-weight:${i===0?'700':'500'};color:${i===0?'#16a34a':'#64748b'};cursor:pointer;border-bottom:${i===0?'2px solid #16a34a':'2px solid transparent'};margin-bottom:-2px;font-family:inherit;">${escHtml(s)}</button>`).join('')}
                       </div>`
                    : '';
                let sheetsHtml = sheets.map((s, i) => {
                    const ws  = wb.Sheets[s];
                    const html = XLSX.utils.sheet_to_html(ws, { editable: false, header: '' });
                    return `<div id="sheet-${i}" style="display:${i===0?'block':'none'};height:100%;overflow:auto;padding:12px 16px;">${html}</div>`;
                }).join('');
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;height:100%;overflow:hidden;">
                        ${tabsHtml}
                        <div style="flex:1;overflow:hidden;position:relative;">
                            ${sheetsHtml}
                        </div>
                    </div>`;
                // Inject table styles
                const style = document.createElement('style');
                style.textContent = `
                    .preview-body table { border-collapse: collapse; font-size: 12px; white-space: nowrap; }
                    .preview-body table td, .preview-body table th { border: 1px solid #e2e8f0; padding: 4px 10px; min-width: 60px; }
                    .preview-body table tr:first-child td, .preview-body table tr:first-child th { background: #f1f5f9; font-weight: 700; position: sticky; top: 0; z-index: 1; }
                    .preview-body table tr:nth-child(even) td { background: #f8fafc; }
                `;
                document.head.appendChild(style);
            })
            .catch(err => {
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:14px;">
                        <i class="fas fa-exclamation-circle" style="font-size:40px;color:#dc2626;"></i>
                        <div style="font-size:13px;color:#64748b;">Không thể đọc file (${err.message})</div>
                        <a href="${url}" download="${escHtml(name)}" style="display:inline-flex;align-items:center;gap:7px;padding:9px 20px;background:#16a34a;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                            <i class="fas fa-download"></i> Tải về máy
                        </a>
                    </div>`;
            });
    } else if (['ppt','pptx'].includes(ext)) {
        body.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:16px;background:#f8fafc;">
                <i class="fas fa-file-powerpoint" style="font-size:56px;color:#ea580c;"></i>
                <div style="font-size:15px;font-weight:700;color:#1e293b;">${escHtml(name)}</div>
                <div style="font-size:13px;color:#64748b;text-align:center;max-width:320px;">
                    File PowerPoint chưa hỗ trợ xem trực tiếp.<br>Vui lòng tải về để mở bằng ứng dụng phù hợp.
                </div>
                <a href="${url}" download="${escHtml(name)}"
                   style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:#ea580c;color:#fff;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">
                    <i class="fas fa-download"></i> Tải về máy
                </a>
            </div>`;
    }
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePreview(e) {
    if (e && e.target !== document.getElementById('preview-modal')) return;
    document.getElementById('preview-modal').classList.remove('open');
    document.getElementById('preview-body').innerHTML = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview({ target: document.getElementById('preview-modal') }); });

// ── Helpers ──────────────────────────────────────────────────────────────────
function xlsShowSheet(btn, sheetId) {
    // Ẩn tất cả sheets
    btn.closest('div[style]').nextElementSibling.querySelectorAll('[id^="sheet-"]').forEach(el => el.style.display = 'none');
    document.getElementById(sheetId).style.display = 'block';
    // Update tab styles
    btn.parentElement.querySelectorAll('button').forEach(b => {
        b.style.background = 'transparent';
        b.style.fontWeight = '500';
        b.style.color = '#64748b';
        b.style.borderBottom = '2px solid transparent';
    });
    btn.style.background = '#fff';
    btn.style.fontWeight = '700';
    btn.style.color = '#16a34a';
    btn.style.borderBottom = '2px solid #16a34a';
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>

<style>
/* mammoth docx render styles */
.preview-body div[style*="overflow-y:auto"] table { border-collapse: collapse; width: 100%; margin: 12px 0; }
.preview-body div[style*="overflow-y:auto"] table td,
.preview-body div[style*="overflow-y:auto"] table th { border: 1px solid #cbd5e1; padding: 6px 10px; }
.preview-body div[style*="overflow-y:auto"] h1 { font-size: 22px; margin: 20px 0 10px; }
.preview-body div[style*="overflow-y:auto"] h2 { font-size: 18px; margin: 16px 0 8px; }
.preview-body div[style*="overflow-y:auto"] h3 { font-size: 15px; margin: 12px 0 6px; }
.preview-body div[style*="overflow-y:auto"] p  { margin: 6px 0; }
.toast { position:fixed; top:20px; right:20px; z-index:9999; padding:11px 18px; border-radius:9px; font-size:13px; font-weight:600; color:#fff; display:flex; align-items:center; gap:7px; box-shadow:0 6px 20px rgba(0,0,0,.18); animation:toastIn .25s ease; font-family:inherit; }
.toast.success { background:#16a34a; }
.toast.error   { background:#dc2626; }
@keyframes toastIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
</style>

</body>
</html>
