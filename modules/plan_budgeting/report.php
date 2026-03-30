<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$current_year = intval($_GET['year'] ?? date('Y'));
$current_quarter = intval($_GET['quarter'] ?? ceil(date('n') / 3));
$months_map = [1=>[1,2,3], 2=>[4,5,6], 3=>[7,8,9], 4=>[10,11,12]];
$curr_months = $months_map[$current_quarter];

function format_vnd($val) {
    if (!$val) return '0 đ';
    return number_format($val, 0, ',', '.') . ' đ';
}

// 1. Fetch Aggregated Monthly Data
$monthly_data = [];
$months_full = [1,2,3,4,5,6,7,8,9,10,11,12];
foreach($months_full as $m) $monthly_data[$m] = ['planned' => 0, 'actual' => 0];

// Get planned totals per quarter
$res_pq = $conn->query("SELECT quarter, SUM(amount) as total FROM budget_values WHERE year = $current_year AND value_type='planned' GROUP BY quarter");
$planned_q = [1=>0, 2=>0, 3=>0, 4=>0];
while($r = $res_pq->fetch_assoc()) $planned_q[$r['quarter']] = $r['total'];

// Get actuals and top over-budget items
$over_budget_items = [];
$sql = "SELECT * FROM (
            SELECT s.id, s.item_name, s.division, 
                   (SELECT amount FROM budget_values WHERE item_id = s.id AND year = $current_year AND quarter = $current_quarter AND value_type = 'planned') as planned_val,
                   SUM(CASE WHEN v.value_type != 'planned' THEN v.amount ELSE 0 END) as actual_val
            FROM budget_structure s
            LEFT JOIN budget_values v ON s.id = v.item_id AND v.year = $current_year AND v.quarter = $current_quarter
            WHERE s.type = 'item'
            GROUP BY s.id
        ) as sub
        WHERE actual_val > planned_val AND planned_val > 0
        ORDER BY (actual_val - planned_val) DESC LIMIT 5";
$res_over = $conn->query($sql);
while($r = $res_over->fetch_assoc()) $over_budget_items[] = $r;

// Monthly Trend
$res_v = $conn->query("SELECT month, SUM(amount) as total FROM budget_values WHERE year = $current_year AND value_type != 'planned' GROUP BY month");
while($r = $res_v->fetch_assoc()) $monthly_data[$r['month']]['actual'] = $r['total'];

// Quarter Actuals for Summary Table
$actual_q = [1=>0, 2=>0, 3=>0, 4=>0];
foreach($months_full as $m) {
    $q = ceil($m/3);
    $actual_q[$q] += $monthly_data[$m]['actual'];
}

// Division Breakdown
$division_data = [];
$sql_div = "SELECT s.division, SUM(v.amount) as total 
            FROM budget_values v 
            JOIN budget_structure s ON v.item_id = s.id 
            WHERE v.year = $current_year AND v.quarter = $current_quarter AND v.value_type != 'planned'
            GROUP BY s.division";
$res_div = $conn->query($sql_div);
while ($row = $res_div->fetch_assoc()) if($row['division']) $division_data[] = ['label' => $row['division'], 'value' => $row['total']];

$total_q_planned = $planned_q[$current_quarter];
$total_q_actual = $actual_q[$current_quarter];

$exec_rate = ($total_q_planned > 0) ? ($total_q_actual / $total_q_planned) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Budget Dashboard</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        .report-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
        .report-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-banner { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-item { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .stat-label { color: #64748b; font-size: 13px; margin-bottom: 8px; font-weight: 600; }
        .stat-value { font-size: 18px; font-weight: 700; color: #0f172a; }
        .progress-container { height: 8px; background: #f1f5f9; border-radius: 4px; margin-top: 10px; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 4px; }
        .over-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .over-item:last-child { border-bottom: none; }
        
        .summary-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .summary-table th { text-align: left; background: #f8fafc; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 13px; }
        .summary-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../includes/topbar.php'; ?>
            <div style="padding: 1.5rem;">
                <div style="display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                    <a href="/plan-budgeting" style="padding: 12px 24px; border-radius: 8px 8px 0 0; background: transparent; color: #64748b; text-decoration: none;">Bảng quản lý</a>
                    <a href="/plan-budgeting/report" style="padding: 12px 24px; border-radius: 8px 8px 0 0; background: white; border: 1px solid #e2e8f0; border-bottom: none; font-weight: 700; color: #0f172a; text-decoration: none; position: relative; bottom: -1px;">Báo cáo / Dashboard</a>
                </div>

                <div style="display: flex; gap: 1rem; margin-bottom: 24px; background: white; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; align-items: center;">
                    <div style="font-weight: 700; color: #475569; white-space: nowrap;">XEM BÁO CÁO:</div>
                    <select style="padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;" onchange="location.href='?year=' + this.value + '&quarter=<?php echo $current_quarter; ?>'">
                        <option value="2024" <?php if($current_year == 2024) echo 'selected'; ?>>Năm 2024</option>
                        <option value="2025" <?php if($current_year == 2025) echo 'selected'; ?>>Năm 2025</option>
                        <option value="2026" <?php if($current_year == 2026) echo 'selected'; ?>>Năm 2026</option>
                    </select>
                    <select style="padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;" onchange="location.href='?year=<?php echo $current_year; ?>&quarter=' + this.value">
                        <option value="1" <?php if($current_quarter == 1) echo 'selected'; ?>>Quý 1</option>
                        <option value="2" <?php if($current_quarter == 2) echo 'selected'; ?>>Quý 2</option>
                        <option value="3" <?php if($current_quarter == 3) echo 'selected'; ?>>Quý 3</option>
                        <option value="4" <?php if($current_quarter == 4) echo 'selected'; ?>>Quý 4</option>
                    </select>
                </div>

                <div class="stat-banner">
                    <div class="stat-item">
                        <div class="stat-label">TỔNG KẾ HOẠCH Q<?php echo $current_quarter; ?></div>
                        <div class="stat-value"><?php echo format_vnd($total_q_planned); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">TỔNG THỰC TẾ Q<?php echo $current_quarter; ?></div>
                        <div class="stat-value" style="color: #0f172a;"><?php echo format_vnd($total_q_actual); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">TỶ LỆ THỰC THI (%)</div>
                        <div class="stat-value" style="display:flex; justify-content:space-between; align-items:center;">
                            <span><?php echo round($exec_rate, 1); ?>%</span>
                            <span style="font-size:12px; color:<?php echo $exec_rate > 100 ? '#ef4444' : '#10b981'; ?>"><?php echo $exec_rate > 100 ? 'Vượt định mức' : 'Trong tầm kiểm soát'; ?></span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width:<?php echo min(100, $exec_rate); ?>%; background:<?php echo $exec_rate > 100 ? '#ef4444' : '#10b981'; ?>;"></div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">DỰ BÁO TIẾT KIỆM</div>
                        <div class="stat-value" style="color:<?php echo ($total_q_planned - $total_q_actual >= 0) ? '#10b981' : '#ef4444'; ?>">
                            <?php echo format_vnd($total_q_planned - $total_q_actual); ?>
                        </div>
                    </div>
                </div>

                <div class="report-card" style="margin-bottom:24px;">
                    <h3 style="margin:0; font-size:16px;">Bảng tổng kết ngân sách năm <?php echo $current_year; ?></h3>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th class="text-right">Kế hoạch (Planned)</th>
                                <th class="text-right">Thực tế chi (Actual)</th>
                                <th class="text-right">Chênh lệch</th>
                                <th class="text-right">Tỷ lệ sử dụng (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $year_planned = 0;
                            $year_actual = 0;
                            for($q=1; $q<=4; $q++): 
                                $diff = $planned_q[$q] - $actual_q[$q];
                                $rate = ($planned_q[$q]>0) ? ($actual_q[$q]/$planned_q[$q]*100) : 0;
                                $year_planned += $planned_q[$q];
                                $year_actual += $actual_q[$q];
                            ?>
                                <tr>
                                    <td style="font-weight:600;">Quý <?php echo $q; ?></td>
                                    <td class="text-right"><?php echo format_vnd($planned_q[$q]); ?></td>
                                    <td class="text-right"><?php echo format_vnd($actual_q[$q]); ?></td>
                                    <td class="text-right" style="color:<?php echo $diff >= 0 ? '#10b981' : '#ef4444'; ?>">
                                        <?php echo ($diff > 0 ? '+' : '') . format_vnd($diff); ?>
                                    </td>
                                    <td class="text-right">
                                        <span style="padding:4px 8px; border-radius:12px; font-size:12px; font-weight:700; background:<?php echo $rate>100 ? '#fee2e2' : '#f0f9ff'; ?>; color:<?php echo $rate>100 ? '#ef4444' : '#0369a1'; ?>">
                                            <?php echo round($rate, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot style="background:#f8fafc; font-weight:800;">
                            <tr>
                                <td>TỔNG CẢ NĂM</td>
                                <td class="text-right"><?php echo format_vnd($year_planned); ?></td>
                                <td class="text-right"><?php echo format_vnd($year_actual); ?></td>
                                <td class="text-right" style="color:<?php echo ($year_planned - $year_actual >= 0) ? '#10b981' : '#ef4444'; ?>">
                                    <?php echo format_vnd($year_planned - $year_actual); ?>
                                </td>
                                <td class="text-right"><?php echo round(($year_planned>0 ? ($year_actual/$year_planned*100) : 0), 1); ?>%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="report-grid">
                    <div class="report-card">
                        <h3 style="margin:0 0 20px 0; font-size:16px;">Xu hướng chi phí hàng tháng (<?php echo $current_year; ?>)</h3>
                        <div id="chart-trend"></div>
                    </div>
                    <div class="report-card" style="display:flex; flex-direction:column;">
                        <h3 style="margin:0 0 20px 0; font-size:16px;">Top 5 Mục vượt ngân sách Q<?php echo $current_quarter; ?></h3>
                        <div style="flex-grow:1;">
                            <?php if(empty($over_budget_items)): ?>
                                <p style="text-align:center; color:#94a3b8; margin-top:40px;">Chưa có mục nào vượt ngân sách. Tuyệt vời!</p>
                            <?php else: ?>
                                <?php foreach($over_budget_items as $item): 
                                    $over = $item['actual_val'] - $item['planned_val'];
                                ?>
                                    <div class="over-item">
                                        <div>
                                            <div style="font-weight:600; font-size:13px;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($item['division']); ?></div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div style="color:#ef4444; font-weight:700; font-size:13px;">+<?php echo format_vnd($over); ?></div>
                                            <div style="font-size:11px; color:#94a3b8;">Vượt <?php echo round(($over/$item['planned_val'])*100, 1); ?>%</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="report-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="report-card">
                        <h3 style="margin:0 0 20px 0; font-size:16px;">Chi phí thực tế theo Khối (Q<?php echo $current_quarter; ?>)</h3>
                        <div id="chart-division"></div>
                    </div>
                    <div class="report-card">
                        <h3 style="margin:0 0 20px 0; font-size:16px;">Phân bổ chi phí Kế hoạch vs Thực tế</h3>
                        <div id="chart-compare"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Charts Initialization
        new ApexCharts(document.querySelector("#chart-trend"), {
            series: [{ name: 'Thực tế chi', data: <?php echo json_encode(array_values(array_column($monthly_data, 'actual'))); ?> }],
            chart: { type: 'area', height: 300, toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 3 },
            colors: ['#0f172a'],
            xaxis: { categories: ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'] },
            yaxis: { labels: { formatter: v => (v/1000000).toFixed(0) + 'M đ' } },
            tooltip: { y: { formatter: val => val.toLocaleString('vi-VN') + ' đ' } },
            fill: { gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } }
        }).render();

        new ApexCharts(document.querySelector("#chart-division"), {
            series: <?php echo json_encode(array_map('floatval', array_column($division_data, 'value'))); ?>,
            chart: { type: 'donut', height: 300 },
            labels: <?php echo json_encode(array_column($division_data, 'label')); ?>,
            colors: ['#0f172a', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
            legend: { position: 'bottom' },
            tooltip: { y: { formatter: val => val.toLocaleString('vi-VN') + ' đ' } }
        }).render();

        new ApexCharts(document.querySelector("#chart-compare"), {
            series: [{ name: 'Kế hoạch', data: [<?php echo $total_q_planned; ?>] }, { name: 'Thực tế', data: [<?php echo $total_q_actual; ?>] }],
            chart: { type: 'bar', height: 300 },
            plotOptions: { bar: { columnWidth: '50%' } },
            colors: ['#e2e8f0', '#0f172a'],
            xaxis: { categories: ['Tổng Quý <?php echo $current_quarter; ?>'] },
            yaxis: { labels: { formatter: v => (v/1000000).toFixed(0) + 'M đ' } },
            tooltip: { y: { formatter: val => val.toLocaleString('vi-VN') + ' đ' } }
        }).render();
    </script>
</body>
</html>
