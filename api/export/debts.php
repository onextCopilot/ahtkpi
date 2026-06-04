<?php
/**
 * Export debts to Excel (.xls).
 *
 * Respects the same access rule as the Debt module:
 *   - admin or can_view_all_debts → all debts
 *   - otherwise → only debts of the user's sale teams
 *
 * Optional GET filters: year, month (by invoice_date), status (payment_status).
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Exporter.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$can_view_all = ($role === 'admin') || !empty($_SESSION['can_view_all_debts']);

$where = [];

if (!$can_view_all) {
    $teams = [];
    $st = $conn->prepare("SELECT team_id FROM user_sale_teams WHERE user_id = ?");
    $st->bind_param("i", $user_id);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $teams[] = (int) $r['team_id'];
    $where[] = $teams ? ('d.sale_team_id IN (' . implode(',', $teams) . ')') : '1=0';
}

$year  = (int) ($_GET['year'] ?? 0);
$month = (int) ($_GET['month'] ?? 0);
$status = trim($_GET['status'] ?? '');
if ($year > 0)  $where[] = "YEAR(d.invoice_date) = $year";
if ($month > 0) $where[] = "MONTH(d.invoice_date) = $month";
if ($status !== '') $where[] = "d.payment_status = '" . $conn->real_escape_string($status) . "'";

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT d.company, d.am, d.client_name, d.project_name, d.payment_milestone,
               d.amount, d.currency, d.invoice_date, d.expected_payment_date,
               d.payment_status, d.invoice_status, st.name AS team_name
        FROM debts d
        LEFT JOIN sale_teams st ON d.sale_team_id = st.id
        $where_sql
        ORDER BY d.invoice_date DESC, d.id DESC";

$res = $conn->query($sql);

$headers = ['Công ty', 'AM', 'Khách hàng', 'Dự án', 'Mốc thanh toán', 'Số tiền', 'Tiền tệ',
            'Ngày hóa đơn', 'Hạn thanh toán', 'Trạng thái TT', 'Trạng thái HĐ', 'Sale Team'];
$rows = [];
$count = 0;
$by_currency = [];   // currency => summed amount
$fmtDate = fn($d) => ($d && $d > '1000-01-01') ? date('d/m/Y', strtotime($d)) : '';
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $amt = (float) $r['amount'];
        $cur = $r['currency'] ?: 'VND';
        $count++;
        $by_currency[$cur] = ($by_currency[$cur] ?? 0) + $amt;
        $rows[] = [
            $r['company'], $r['am'], $r['client_name'], $r['project_name'], $r['payment_milestone'],
            ['v' => $amt, 'num' => true], $cur,
            $fmtDate($r['invoice_date']), $fmtDate($r['expected_payment_date']),
            $r['payment_status'], $r['invoice_status'], $r['team_name'],
        ];
    }
}

// Subtotal per currency + grand total (record count). 12 columns; amount at idx 5.
ksort($by_currency);
foreach ($by_currency as $cur => $sum) {
    $cells = array_fill(0, 12, '');
    $cells[4] = 'Tổng ' . $cur;
    $cells[5] = ['v' => $sum, 'num' => true];
    $cells[6] = $cur;
    $rows[] = ['type' => 'subtotal', 'cells' => $cells];
}
$total_cells = array_fill(0, 12, '');
$total_cells[0] = 'TỔNG CỘNG';
$total_cells[4] = $count . ' bản ghi';
$rows[] = ['type' => 'total', 'cells' => $total_cells];

$stamp = date('Ymd_His');
$title = 'Báo cáo công nợ' . ($year ? " - Năm $year" : '') . ($month ? " - Tháng $month" : '');
Exporter::streamXls("cong-no_$stamp", $title, $headers, $rows);
