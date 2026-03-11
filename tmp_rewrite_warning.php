<?php
$file = '/Users/hyuncao/AHT KPI/modules/debt_warning/index.php';
$content = file_get_contents($file);

// 1. Change Title
$content = str_replace("\$page_title = 'All Debts Overview';", "\$page_title = 'Debts Warning';", $content);

// 2. Change Logic
$old_logic = '/\$groupedDebts = \[\];.*?\$debts = \[\];.*?\}\s*\}/s';

$new_logic = <<<'PHP'
$warningLevel30 = [];
$warningLevel60 = [];
$warningEmpty = [];
$total_warning_30 = 0;
$total_warning_60 = 0;
$total_warning_empty = 0;

$where_clauses[] = "d.payment_status = 'Not paid'";

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Ensure acl
if ($_SESSION['role'] !== 'admin' && empty($_SESSION['is_am_bd'])) {
    die("Access Denied");
}

$res = $conn->query("SELECT d.*, st.name as team_name 
                    FROM debts d 
                    LEFT JOIN sale_teams st ON d.sale_team_id = st.id 
                    $where_sql 
                    ORDER BY d.expected_payment_date ASC, d.id DESC");
if ($res) {
    $now = new DateTime();
    $now->setTime(0,0,0);
    while ($row = $res->fetch_assoc()) {
        $amount = (float) $row['amount'];
        $curr = $row['currency'] ?: 'USD';
        $date = !empty($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d');

        // Convert to VND
        $rate = $odoo->getRate($curr, $date);
        $vnd_value = ($rate > 0) ? ($amount / $rate) : $amount;

        $row['amount_original'] = $amount;
        $row['currency_original'] = $curr;
        $row['amount'] = $vnd_value;
        $row['currency'] = 'VND';

        $exp_date_str = $row['expected_payment_date'];
        if (empty($exp_date_str) || $exp_date_str === '0000-00-00') {
            $warningEmpty[] = $row;
            $total_warning_empty += $vnd_value;
        } else {
            $exp_date = new DateTime($exp_date_str);
            $exp_date->setTime(0,0,0);
            $diff = $now->diff($exp_date);

            // if invert == 1, $exp_date is earlier than $now (quá hạn)
            if ($diff->invert && $diff->days > 60) {
                $warningLevel60[] = $row;
                $total_warning_60 += $vnd_value;
            } elseif ($diff->invert && $diff->days > 30) {
                $warningLevel30[] = $row;
                $total_warning_30 += $vnd_value;
            }
        }
    }
}
$total_amount_vnd = $total_warning_30 + $total_warning_60 + $total_warning_empty;
PHP;

$content = preg_replace($old_logic, $new_logic, $content);

// 3. Remove team tabs and dashboard analytics HTML
$old_html = '/<div class="team-tabs" id="sortable-tabs">.*?<\?php else:\s*\?>/s';
$content = preg_replace($old_html, '', $content);

// 4. End script replacement
$old_end = "/<\?php endif; \?>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/main>\s*<\/div>\s*<script src=\"https:\/\/cdnjs\.cloudflare\.com\/ajax\/libs\/Sortable\/1\.15\.0\/Sortable\.min\.js\"><\/script>\s*<\/body>\s*<\/html>/is";
$content = preg_replace($old_end, "</div>\n</div>\n</div>\n</main>\n</div>\n</body>\n</html>", $content);
$content = preg_replace("/<\?php endif; \?>\s*<\/div>\s*<\/div>\s*<\/main>\s*<\/div>\s*<\/body>\s*<\/html>/is", "</div>\n</div>\n</main>\n</div>\n</body>\n</html>", $content);

// 5. Replace tbody
$old_tbody = '/<tbody>.*?<\/tbody>/s';
$new_tbody = <<<'PHP'
<tbody>
<?php
$warning_categories = [
    'Nợ xấu > 60 ngày' => ['data' => $warningLevel60, 'total' => $total_warning_60, 'color' => '#dc2626', 'bg' => '#fef2f2'],
    'Quá hạn 30 ngày' => ['data' => $warningLevel30, 'total' => $total_warning_30, 'color' => '#ea580c', 'bg' => '#fff7ed'],
    'Chưa có ngày thanh toán' => ['data' => $warningEmpty, 'total' => $total_warning_empty, 'color' => '#475569', 'bg' => '#f8fafc'],
];
$globalIdx = 1;
?>

<?php foreach ($warning_categories as $cat_title => $cat_info): ?>
    <?php if (count($cat_info['data']) > 0): ?>
        <tr class="group-header" style="background-color: <?php echo $cat_info['bg']; ?> !important;">
            <td colspan="22" style="color: <?php echo $cat_info['color']; ?>; font-size: 14px; font-weight: 800; padding: 12px 16px;">
                <?php echo $cat_title; ?> <span style="font-weight: 500; font-size: 12px;">(<?php echo count($cat_info['data']); ?> debts)</span> 
                <span class="group-total" style="color: <?php echo $cat_info['color']; ?>; margin-left: 20px;">(Total Amount: <?php echo formatVND($cat_info['total']); ?>)</span>
            </td>
        </tr>
        <?php foreach ($cat_info['data'] as $item): ?>
            <tr style="user-select: none;">
                <td style="text-align: center; color: #94a3b8; font-weight: 500;"><?php echo $globalIdx++; ?></td>
                <td class="cell-company"><?php echo htmlspecialchars($item['company']); ?></td>
                <td>
                    <?php
                    $am = $item['am'] ?? '';
                    $cls = 'am-emily';
                    if ($am === 'Ryan') $cls = 'am-ryan';
                    else if ($am === 'Hyun') $cls = 'am-hyun';
                    ?>
                    <span class="badge am-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($am); ?></span>
                </td>
                <td><?php echo htmlspecialchars($item['team_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($item['client_name']); ?></td>
                <td><?php echo htmlspecialchars($item['project_name']); ?></td>
                <td><?php echo formatDate($item['invoice_date']); ?></td>
                <td><?php echo htmlspecialchars($item['payment_milestone']); ?></td>
                <td><?php echo formatDate($item['expected_prod_date']); ?></td>
                <td style="font-weight: bold; color: #dc2626;"><?php echo formatDate($item['expected_payment_date']); ?></td>
                <td>
                    <?php
                    $sc = $item['invoice_status_class'];
                    $scc = 'status-chuaxacdinh';
                    if ($sc == 'Done') $scc = 'status-done';
                    elseif ($sc == 'Tím') $scc = 'status-tim';
                    elseif ($sc == 'Xanh' || $sc == 'Tốt') $scc = 'status-xanh';
                    elseif ($sc == 'Trắng') $scc = 'status-trang';
                    elseif ($sc == 'Đỏ') $scc = 'status-do';
                    ?>
                    <span class="<?php echo $scc; ?>"><?php echo htmlspecialchars($sc ?: ''); ?></span>
                </td>
                <td class="cell-amount">
                    <?php echo formatVND($item['amount']); ?>
                    <?php if ($item['currency_original'] === 'USD' && $item['amount_original'] > 0): ?>
                        <div style="font-size: 10px; color: #64748b; font-weight: normal; margin-top: 2px;">
                            ($<?php echo number_format($item['amount_original'], 2); ?>)
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $pl = $item['pl_class'];
                    $plc = 'pl-tb'; // Default
                    if ($pl === 'Tốt') $plc = 'pl-tot';
                    elseif ($pl === 'Xấu') $plc = 'pl-xau';
                    ?>
                    <span class="badge <?php echo $plc; ?>"><?php echo htmlspecialchars($pl ?: 'TB'); ?></span>
                </td>
                <td style="color: #64748b; font-size: 0.85rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($item['invoice_status']); ?>">
                    <?php echo htmlspecialchars($item['invoice_status']); ?>
                </td>
                <td style="color: #64748b; font-size: 0.85rem;"><?php echo htmlspecialchars($item['vat_invoice']); ?></td>
                <td>
                    <?php 
                    $ps = $item['payment_status'];
                    $psc = 'pay-not-paid';
                    ?>
                    <span class="<?php echo $psc; ?>"><?php echo htmlspecialchars($ps); ?></span>
                </td>
                <td><?php echo htmlspecialchars($item['payment_month']); ?></td>
                <td><?php echo htmlspecialchars($item['weekly_update']); ?></td>
                <td style="max-width: 200px; white-space: normal; font-size: 0.8rem; color: #475569; line-height: 1.4;">
                    <?php echo nl2br(htmlspecialchars($item['am_notes'])); ?>
                </td>
                <td style="max-width: 200px; white-space: normal; font-size: 0.8rem; color: #475569; line-height: 1.4;">
                    <?php echo nl2br(htmlspecialchars($item['delivery_notes'])); ?>
                </td>
                <td>
                    <?php
                    $prs = $item['production_status'];
                    $prsc = 'prod-dc1'; // default
                    if (strpos($prs, 'DC5') !== false) $prsc = 'prod-dc5';
                    elseif (strpos($prs, 'Thêm') !== false) $prsc = 'prod-them';
                    ?>
                    <span class="badge <?php echo $prsc; ?> text-xs"><?php echo htmlspecialchars($prs); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endforeach; ?>
</tbody>
PHP;

$content = preg_replace($old_tbody, $new_tbody, $content);

file_put_contents($file, $content);
echo "Successfully rewrote debts_warning/index.php\n";
