<?php
/**
 * Shared debts export data builder — used by both the Excel (debts.php) and
 * PDF (debts_pdf.php) endpoints so the access control, filters and the nested
 * grouping (Team → Year → Quarter → Month, per-currency subtotals) stay in one
 * place.
 *
 * Returns ['headers' => [...], 'rows' => [...], 'title' => '...', 'count' => N].
 * $rows uses Exporter's row format (plain cell lists + section/subtotal/total).
 */
function build_debts_export(mysqli $conn, array $session, array $get): array
{
    // VND rate map (company-safe getCurrencies) for the converted totals.
    $vnd_rates = ['VND' => 1.0];
    try {
        require_once __DIR__ . '/../../libs/OdooAPI.php';
        $odoo = new OdooAPI();
        $curs = $odoo->getCurrencies();
        $r_vnd = (is_array($curs) && isset($curs['VND']['rate'])) ? (float) $curs['VND']['rate'] : 0.0;
        if ($r_vnd > 0) {
            foreach ($curs as $cname => $cinfo) {
                $cr = isset($cinfo['rate']) ? (float) $cinfo['rate'] : 0.0;
                if ($cr > 0) $vnd_rates[$cname] = $r_vnd / $cr;
            }
        }
    } catch (\Throwable $e) { /* VND-only fallback */ }

    $user_id = (int) ($session['user_id'] ?? 0);
    $role = $session['role'] ?? 'user';
    $can_view_all = ($role === 'admin') || !empty($session['can_view_all_debts']);

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

    $year  = (int) ($get['year'] ?? 0);
    $month = (int) ($get['month'] ?? 0);
    $status = trim($get['status'] ?? '');
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
    $fmtDate = fn($d) => ($d && $d > '1000-01-01') ? date('d/m/Y', strtotime($d)) : '';

    // Build nested tree: team → year → quarter → month → records
    $tree = [];
    $count = 0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $count++;
            $amt = (float) $r['amount'];
            $cur = $r['currency'] ?: 'VND';
            $team = trim($r['team_name'] ?? '') ?: 'Chưa gán team';
            $d = $r['invoice_date'];
            if ($d && $d > '1000-01-01') {
                $ts = strtotime($d); $y = (int) date('Y', $ts); $mo = (int) date('n', $ts);
                $q = (int) ceil($mo / 3);
            } else { $y = 0; $q = 0; $mo = 0; }
            $tree[$team][$y][$q][$mo][] = [
                'amt' => $amt, 'cur' => $cur,
                'cells' => [
                    $r['company'], $r['am'], $r['client_name'], $r['project_name'], $r['payment_milestone'],
                    ['v' => $amt, 'num' => true], $cur,
                    $fmtDate($r['invoice_date']), $fmtDate($r['expected_payment_date']),
                    $r['payment_status'], $r['invoice_status'], $team,
                ],
            ];
        }
    }

    $sortKeys = function (array $keys, bool $desc = false) {
        $hasZero = in_array(0, $keys, true) || in_array('0', $keys, true);
        $keys = array_values(array_filter($keys, fn($k) => (string) $k !== '0'));
        sort($keys);
        if ($desc) $keys = array_reverse($keys);
        if ($hasZero) $keys[] = 0;
        return $keys;
    };
    // Per-currency subtotal rows + one "quy đổi VND" row summing everything.
    $subtotalRows = function (array $acc, string $label, string $type) use ($vnd_rates) {
        ksort($acc);
        $out = [];
        $vndTotal = 0.0;
        foreach ($acc as $cur => $sum) {
            $cells = array_fill(0, 12, '');
            $cells[0] = $label;
            $cells[5] = ['v' => $sum, 'num' => true];
            $cells[6] = $cur;
            $out[] = ['type' => $type, 'cells' => $cells];
            $vndTotal += $sum * ($vnd_rates[$cur] ?? 0);
        }
        // Add the converted-VND line unless the group is VND-only (would duplicate).
        if (!(count($acc) === 1 && isset($acc['VND']))) {
            $cells = array_fill(0, 12, '');
            $cells[0] = $label . ' (quy đổi VND)';
            $cells[5] = ['v' => $vndTotal, 'num' => true];
            $cells[6] = 'VND';
            $out[] = ['type' => $type, 'cells' => $cells];
        }
        return $out;
    };
    $addSum = function (array &$acc, string $cur, float $amt) { $acc[$cur] = ($acc[$cur] ?? 0) + $amt; };
    $qLabel = fn($q) => $q ? "Quý $q" : 'Không rõ quý';
    $yLabel = fn($y) => $y ? "Năm $y" : 'Không có ngày';
    $mLabel = fn($m, $y) => $m ? sprintf('Tháng %02d/%d', $m, $y) : 'Không có tháng';

    $rows = [];
    $grand = [];
    foreach ($sortKeys(array_keys($tree)) as $team) {
        $rows[] = ['type' => 'section', 'level' => 1, 'label' => '▦ TEAM: ' . $team];
        $teamSum = [];
        foreach ($sortKeys(array_keys($tree[$team]), true) as $y) {
            $rows[] = ['type' => 'section', 'level' => 2, 'label' => $yLabel($y)];
            $yearSum = [];
            foreach ($sortKeys(array_keys($tree[$team][$y])) as $q) {
                $rows[] = ['type' => 'section', 'level' => 3, 'label' => $qLabel($q)];
                $qSum = [];
                foreach ($sortKeys(array_keys($tree[$team][$y][$q])) as $m) {
                    $rows[] = ['type' => 'section', 'level' => 4, 'label' => $mLabel($m, $y)];
                    $mSum = [];
                    foreach ($tree[$team][$y][$q][$m] as $rec) {
                        $rows[] = $rec['cells'];
                        $addSum($mSum, $rec['cur'], $rec['amt']);
                        $addSum($qSum, $rec['cur'], $rec['amt']);
                        $addSum($yearSum, $rec['cur'], $rec['amt']);
                        $addSum($teamSum, $rec['cur'], $rec['amt']);
                        $addSum($grand, $rec['cur'], $rec['amt']);
                    }
                    $rows = array_merge($rows, $subtotalRows($mSum, '↳ Tổng ' . $mLabel($m, $y), 'subtotal'));
                }
                $rows = array_merge($rows, $subtotalRows($qSum, '↳ Tổng ' . $qLabel($q), 'subtotal'));
            }
            $rows = array_merge($rows, $subtotalRows($yearSum, '↳ Tổng ' . $yLabel($y), 'subtotal'));
        }
        $rows = array_merge($rows, $subtotalRows($teamSum, '■ TỔNG TEAM: ' . $team, 'total'));
    }
    $rows = array_merge($rows, $subtotalRows($grand, '■ TỔNG CỘNG TOÀN BỘ', 'total'));
    $cnt = array_fill(0, 12, '');
    $cnt[0] = 'Tổng số bản ghi'; $cnt[4] = $count;
    $rows[] = ['type' => 'total', 'cells' => $cnt];

    $title = 'Báo cáo công nợ' . ($year ? " - Năm $year" : '') . ($month ? " - Tháng $month" : '');
    return ['headers' => $headers, 'rows' => $rows, 'title' => $title, 'count' => $count];
}
