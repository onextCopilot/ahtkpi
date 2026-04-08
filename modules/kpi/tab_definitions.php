<?php // Tab 1: Annual KPI Definitions — quarterly actuals + annual progress

if (!function_exists('stripThousands')) {
    function stripThousands($val)
    {
        $v = trim($val);
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v))
            return str_replace('.', '', $v);
        return $v;
    }
}
if (!function_exists('qTotalForDef')) {
    function qTotalForDef($monthly_map, $def_id, array $months, $calc_method = 'sum')
    {
        $sum = 0;
        $count = 0;
        $mixed = false;
        foreach ($months as $m) {
            $val = $monthly_map[$def_id][$m]['actual_value'] ?? null;
            if ($val === null || $val === '')
                continue;
            $raw = stripThousands($val);
            if (is_numeric($raw)) {
                $sum += (float) $raw;
                $count++;
            } else {
                $mixed = true;
                $count++;
            }
        }
        if ($count === 0)
            return null;
        $display = ($calc_method === 'avg' && !$mixed && $count > 0) ? $sum / $count : $sum;
        return ['sum' => $display, 'fmt' => number_format($display, 0, ',', '.'), 'count' => $count, 'mixed' => $mixed, 'raw_sum' => $sum];
    }
}

$q_months_def = [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8, 9], 4 => [10, 11, 12]];
$q_colors_def = [
    1 => ['bg' => '#EFF6FF', 'head' => '#1D4ED8', 'border' => '#BFDBFE'],
    2 => ['bg' => '#F0FDF4', 'head' => '#065F46', 'border' => '#A7F3D0'],
    3 => ['bg' => '#FFFBEB', 'head' => '#92400E', 'border' => '#FDE68A'],
    4 => ['bg' => '#FEF2F2', 'head' => '#991B1B', 'border' => '#FECACA'],
];

function fmtTargetBase($val)
{
    if ($val === null || $val === '')
        return '—';
    $s = stripThousands(trim($val));
    if (is_numeric($s) && $s !== '')
        return number_format((float) $s, 0, ',', '.');
    return htmlspecialchars($val);
}

// Pre-compute quarterly + annual totals
$def_q_totals = [];
$def_yr_totals = [];
foreach ($defs as $d) {
    $method = $d['calc_method'] ?? 'sum';
    $yrRawSum = 0;
    $yrCount = 0;
    $yrMixed = false;
    foreach ($q_months_def as $qi => $months) {
        $t = qTotalForDef($monthly_map, $d['id'], $months, $method);
        $def_q_totals[$d['id']][$qi] = $t;
        if ($t) {
            if ($t['mixed'])
                $yrMixed = true;
            else {
                $yrRawSum += $t['raw_sum'] ?? $t['sum'];
                $yrCount++;
            }
        }
    }
    if ($yrCount > 0 || $yrMixed) {
        // For annual: if avg method, average across all 12 months that have data
        $allMonths = [1,2,3,4,5,6,7,8,9,10,11,12];

        // For annual: average/sum across months that have data.
        // If calc_method is avg and it's the current year, only count up to the current month as requested.
      //  $maxMonth = 12;
        //if ($method === 'avg' && $year == (int)date('Y')) {
         //   $maxMonth = (int)date('n');
     //   }
      //  $allMonths = range(1, $maxMonth);

        // ket thuc doan sua moi

        
        $allSum = 0; $allMonthCount = 0; $allMixed = false;
        foreach ($allMonths as $m) {
            $val = $monthly_map[$d['id']][$m]['actual_value'] ?? null;
            if ($val === null || $val === '') continue;
            $raw = stripThousands($val);
            if (is_numeric($raw)) { $allSum += (float)$raw; $allMonthCount++; }
            else $allMixed = true;
        }
        $yrDisplay = ($method === 'avg' && !$allMixed && $allMonthCount > 0) ? $allSum / $allMonthCount : $allSum;
        $def_yr_totals[$d['id']] = ['sum' => $yrDisplay, 'fmt' => number_format($yrDisplay, 0, ',', '.'), 'count' => $allMonthCount, 'mixed' => $yrMixed];
       
      //  $def_yr_totals[$d['id']] = ['sum' => $yrDisplay, 'fmt' => number_format($yrDisplay, 0, ',', '.'), 'count' => $allMonthCount, 'mixed' => $allMixed];

        
    } else {
        $def_yr_totals[$d['id']] = null;
    }
}

// Helper: progress data from actual sum + target num
function calcProgress($actualSum, $targetNum)
{
    if ($targetNum === null || $targetNum <= 0)
        return null;
    $pct = min(999, round($actualSum / $targetNum * 100, 1));
    if ($pct >= 100)
        $c = ['bar' => '#10B981', 'txt' => '#065F46'];
    elseif ($pct >= 80)
        $c = ['bar' => '#3B82F6', 'txt' => '#1D4ED8'];
    elseif ($pct >= 60)
        $c = ['bar' => '#F59E0B', 'txt' => '#B45309'];
    else
        $c = ['bar' => '#EF4444', 'txt' => '#B91C1C'];
    return ['pct' => $pct, 'barPct' => min(100, $pct), 'bar' => $c['bar'], 'txt' => $c['txt']];
}

$COLS = 14; // STT+Nhóm+Tên+TargetNăm+CảNăm+Tỷtrọng+Q1+Q2+Q3+Q4+Owner+Dept+Ghichú+Act
?>
<div class="toolbar">
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <button class="btn btn-blue" onclick="openAddDef()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Thêm KPI
    </button>
    <?php endif; ?>
    <button class="btn" onclick="window.print()">🖨 In</button>
    <button class="btn" onclick="exportCSV('defTable')">⬇ CSV</button>
    <div style="flex:1"></div>
    <input id="defSearch" type="text" placeholder="🔍 Tìm KPI..." onkeyup="filterTbl('defTable','defSearch')"
        style="padding:6px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px;width:200px">
</div>

<div class="sheet-wrap">
    <table class="sheet" id="defTable">
        <thead>
            <tr>
                <th class="col-no" rowspan="2">STT</th>
                <th style="min-width:80px" rowspan="2">Nhóm</th>
                <th style="min-width:220px" rowspan="2">Tên KPI</th>
                <th style="min-width:130px" rowspan="2">Target năm</th>
                <!-- Annual progress — right after Target năm -->
                <th style="min-width:160px;background:#1D4ED8;color:#fff;text-align:center;border-bottom:2px solid #1E40AF"
                    rowspan="2">
                    🏁 Cả năm <?= $year ?>
                </th>
                <th style="width:70px;text-align:right" rowspan="2">Tỷ trọng</th>
                <!-- Quarterly group -->
                <th colspan="4"
                    style="text-align:center;background:#F3F4F6;color:#374151;font-size:11px;border-bottom:2px solid #D1D5DB">
                    📊 Thực tế theo Quý &amp; Tiến độ
                </th>
                <th style="min-width:130px" rowspan="2">KPI Owner</th>
                <th style="min-width:100px" rowspan="2">Phòng ban</th>
                <th style="min-width:80px" rowspan="2">Ghi chú</th>
                <th class="col-act" rowspan="2">Thao tác</th>
            </tr>
            <tr>
                <?php foreach ($q_colors_def as $qi => $qc): ?>
                    <th
                        style="min-width:130px;text-align:right;background:<?= $qc['bg'] ?>;color:<?= $qc['head'] ?>;font-size:11px;border-bottom:2px solid <?= $qc['border'] ?>">
                        Q<?= $qi ?> (T<?= ($qi - 1) * 3 + 1 ?>–T<?= $qi * 3 ?>)
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <?php if (empty($defs)): ?>
            <tbody>
                <tr>
                    <td colspan="<?= $COLS ?>" style="text-align:center;padding:40px;color:#9CA3AF">
                        Chưa có KPI nào cho năm <?= $year ?>. Nhấn <b>+ Thêm KPI</b> để bắt đầu.
                    </td>
                </tr>
            </tbody>
        <?php else: ?>
            <?php $cur_group = null;
            $stt = 1;
            foreach ($defs as $d):
                if ($d['kpi_group'] !== $cur_group):
                    if ($cur_group !== null) echo '</tbody>'; // close previous group's tbody
                    $cur_group = $d['kpi_group']; ?>
                    <tbody class="kpi-group-tbody" data-group="<?= htmlspecialchars($cur_group ?: '') ?>">
                    <tr class="group-row" style="background:#F9FAFB">
                        <td colspan="<?= $COLS ?>" style="font-weight:700">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <span class="group-drag-handle" style="cursor:move;margin-right:8px;color:#9CA3AF" title="Kéo thả nhóm">☰</span>
                            <?php endif; ?>
                            <?= htmlspecialchars($cur_group ?: '(Chưa phân nhóm)') ?>
                        </td>
                    </tr>
                <?php endif;

                // Annual data
                $yrTot = $def_yr_totals[$d['id']] ?? null;
                $baseRaw = stripThousands(trim($d['target_base'] ?? ''));
                $baseNum = (is_numeric($baseRaw) && (float) $baseRaw > 0) ? (float) $baseRaw : null;
                $yrProg = ($yrTot && !$yrTot['mixed'] && $baseNum) ? calcProgress($yrTot['sum'], $baseNum) : null;

                // Prepare 12-month data for chart drilldown
                $yr_months_detail = [];
                for ($m_num = 1; $m_num <= 12; $m_num++) {
                    $m_row = $monthly_map[$d['id']][$m_num] ?? null;
                    $yr_months_detail[$m_num] = [
                        'val' => $m_row ? (is_numeric(stripThousands($m_row['actual_value'])) ? (float)stripThousands($m_row['actual_value']) : 0) : null,
                        'fmt' => $m_row ? $m_row['actual_value'] : '—',
                        'notes' => $m_row['notes'] ?? '',
                        'by' => $m_row['updater_name'] ?? ($m_row ? 'User' : '—'),
                        'at' => ($m_row && !empty($m_row['updated_at'])) ? date('H:i d/m', strtotime($m_row['updated_at'])) : ''
                    ];
                }
                $yr_data_json = json_encode([
                    'kpi_name' => $d['kpi_name'],
                    'def_id' => $d['id'],
                    'year' => $year,
                    'unit' => $d['unit'] ?? '',
                    'months' => $yr_months_detail,
                    'total_actual' => $yrTot ? $yrTot['fmt'] : '—',
                    'progress' => $yrProg ? $yrProg['pct'] . '%' : '—',
                    'target' => $d['target_base'] ?? '—'
                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                <tr class="kpi-item-row" data-id="<?= $d['id'] ?>">
                    <td class="col-no">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <span class="item-drag-handle" style="cursor:move;margin-right:4px;color:#9CA3AF" title="Kéo thả">☰</span>
                        <?php endif; ?>
                        <?= $stt++ ?>
                    </td>
                    <td class="item-group-col" style="font-size:11px;color:#6B7280"><?= htmlspecialchars($d['kpi_group'] ?? '') ?></td>
                    <td style="font-weight:500;white-space:normal">
                        <?= htmlspecialchars($d['kpi_name']) ?>
                        <?php if ($d['is_condition']): ?><span class="badge badge-cond">KPI ĐK</span><?php endif; ?>
                    </td>

                    <!-- Target năm -->
                    <td style="font-size:12px;white-space:nowrap"><?= fmtTargetBase($d['target_base'] ?? '') ?><?php if(!empty($d['unit'])): ?><span style="color:#6B7280; font-size:11px; font-weight: 500;"><?= htmlspecialchars($d['unit']) ?></span><?php endif; ?></td>

                    <!-- 🏁 Cả năm — donut chart -->
                    <?php
                    // SVG donut params
                    $r = 16;           // radius
                    $cx = 20;
                    $cy = 20; // center
                    $sw = 5;            // stroke-width
                    $circ = round(2 * M_PI * $r, 2); // circumference
                    ?>
                    <td
                        class="yr-drilldown-cell"
                        onclick='openYearDetailDraw(<?= $yr_data_json ?>)'
                        style="padding:2px 4px;background:#EFF6FF;vertical-align:middle;border-left:2px solid #BFDBFE;border-right:2px solid #BFDBFE;text-align:center;cursor:pointer">
                        <?php if ($yrTot && !$yrTot['mixed']): ?>
                            <div style="display:flex;align-items:center;gap:6px">
                                <!-- SVG donut -->
                                <svg width="40" height="40" viewBox="0 0 40 40" style="flex-shrink:0">
                                    <!-- track -->
                                    <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#DBEAFE"
                                        stroke-width="<?= $sw ?>" />
                                    <?php if ($yrProg): ?>
                                        <!-- arc -->
                                        <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="<?= $yrProg['bar'] ?>"
                                            stroke-width="<?= $sw ?>" stroke-linecap="round"
                                            stroke-dasharray="<?= round($yrProg['barPct'] / 100 * $circ, 2) ?> <?= $circ ?>"
                                            transform="rotate(-90 <?= $cx ?> <?= $cy ?>)" />
                                        <!-- % text -->
                                        <text x="<?= $cx ?>" y="<?= $cy + 1 ?>" text-anchor="middle" dominant-baseline="central"
                                            font-size="10" font-weight="800" fill="<?= $yrProg['txt'] ?>"><?= $yrProg['pct'] ?>%</text>
                                    <?php else: ?>
                                        <text x="<?= $cx ?>" y="<?= $cy + 1 ?>" text-anchor="middle" dominant-baseline="central"
                                            font-size="9" fill="#9CA3AF">N/A</text>
                                    <?php endif; ?>
                                </svg>
                                <!-- Values -->
                                <div style="text-align:left;min-width:0">
                                    <div
                                        style="font-size:12px;font-weight:800;color:#1D4ED8;line-height:1.2;word-break:break-all">
                                        <?= $yrTot['fmt'] ?></div>
                                    <?php if ($baseNum): ?>
                                        <div style="font-size:9px;color:#6B7280;margin-top:2px">/
                                            <?= number_format($baseNum, 0, ',', '.') ?><?= htmlspecialchars($d['unit']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($yrTot && $yrTot['mixed']): ?>
                            <span style="font-size:10px;color:#9CA3AF">Hỗn hợp</span>
                        <?php else: ?>
                            <!-- Empty donut -->
                            <svg width="40" height="40" viewBox="0 0 40 40">
                                <circle cx="20" cy="20" r="<?= $r ?>" fill="none" stroke="#E5E7EB" stroke-width="<?= $sw ?>" />
                                <text x="20" y="21" text-anchor="middle" dominant-baseline="central" font-size="9"
                                    fill="#D1D5DB">—</text>
                            </svg>
                        <?php endif; ?>
                    </td>

                    <!-- Tỷ trọng -->
                    <td style="text-align:right;font-weight:600"><?= number_format($d['weight'], 1) ?>%</td>

                    <!-- Q1–Q4 actual + progress -->
                    <?php foreach ($q_colors_def as $qi => $qc):
                        $tot = $def_q_totals[$d['id']][$qi] ?? null;
                        $qPlanR = $quarterly_map[$d['id']][$qi]['target_value'] ?? null;
                        $qPlan = null;
                        if ($qPlanR !== null && $qPlanR !== '') {
                            $s = stripThousands(trim($qPlanR));
                            if (is_numeric($s))
                                $qPlan = (float) $s;
                        }
                        if ($qPlan === null && $baseNum) {
                            $qPlan = ($d['calc_method'] === 'avg') ? $baseNum : $baseNum / 4;
                        }

                        
                        $qProg = ($tot && !$tot['mixed'] && $qPlan) ? calcProgress($tot['sum'], $qPlan) : null;
                        ?>
                        <?php
                            $months_detail = [];
                            $has_q_notes = false;
                            foreach ($q_months_def[$qi] as $m_num) {
                                $m_row = $monthly_map[$d['id']][$m_num] ?? null;
                                if (!empty($m_row['notes'])) $has_q_notes = true;
                                $months_detail[$m_num] = [
                                    'val' => $m_row['actual_value'] ?? '—',
                                    'notes' => $m_row['notes'] ?? '',
                                    'by' => $m_row['updater_name'] ?? ($m_row ? 'User' : '—'),
                                    'at' => ($m_row && !empty($m_row['updated_at'])) ? date('H:i d/m', strtotime($m_row['updated_at'])) : ''
                                ];
                            }
                            $q_data_json = json_encode([
                                'kpi_name' => $d['kpi_name'],
                                'q_label' => 'QUÝ ' . $qi,
                                'def_id' => $d['id'],
                                'quarter' => $qi,
                                'year' => $year,
                                'target' => $qPlanR ?: ($qPlan ? number_format($qPlan, 0, ',', '.') : '—'),
                                'unit' => $d['unit'] ?? '',
                                'months' => $months_detail,
                                'total_actual' => $tot ? $tot['fmt'] : '—',
                                'progress' => $qProg ? $qProg['pct'].'%' : '—',
                                'color' => $qc['head']
                            ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <td class="q-drilldown-cell" 
                            style="padding:5px 8px;background:<?= $qc['bg'] ?>;vertical-align:top;cursor:pointer;position:relative;"
                            onclick='openQDetailDraw(<?= $q_data_json ?>)'>
                            
                            <?php if ($has_q_notes): ?>
                                <div style="position:absolute; top:4px; right:4px; color:#F59E0B; opacity:1;" title="Có nội dung giải trình">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                </div>
                            <?php endif; ?>

                            <?php if ($tot && !$tot['mixed']): ?>
                                <div style="font-size:12px;font-weight:700;color:<?= $qc['head'] ?>;margin-bottom:3px">
                                    <?= $tot['fmt'] ?>
                                    <?php if ($tot['count'] < 3): ?><span style="font-size:9px;font-weight:400;color:#9CA3AF">
                                            (<?= $tot['count'] ?>/3T)</span><?php endif; ?> <?= htmlspecialchars($d['unit']) ?>
                                </div>
                                <div style="height:5px;background:#E5E7EB;border-radius:3px;overflow:hidden;margin-bottom:2px">
                                    <div
                                        style="height:100%;width:<?= $qProg ? $qProg['barPct'] : 0 ?>%;background:<?= $qProg ? $qProg['bar'] : '#E5E7EB' ?>;border-radius:3px;transition:width .4s">
                                    </div>
                                </div>
                                <?php if ($qProg): ?>
                                    <div style="display:flex;justify-content:space-between">
                                        <span style="font-size:9px;color:#9CA3AF">KH: <?= number_format($qPlan, 0, ',', '.') ?><?= htmlspecialchars($d['unit']) ?></span>
                                        <span
                                            style="font-size:11px;font-weight:800;color:<?= $qProg['txt'] ?>"><?= $qProg['pct'] ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:9px;color:#D1D5DB">Chưa có target</div><?php endif; ?>
                            <?php elseif ($tot && $tot['mixed']): ?>
                                <span style="font-size:10px;color:#9CA3AF">Hỗn hợp</span>
                            <?php else: ?>
                                <span style="color:#D1D5DB">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>

                    <!-- Owner -->
                    <td>
                        <?php if (!empty($d['owner_name'])): ?>
                            <span class="badge-owner">
                                <?php if (!empty($d['owner_avatar'])): ?><img src="<?= htmlspecialchars($d['owner_avatar']) ?>"
                                        class="av-img">
                                <?php else: ?><span
                                        class="av-init"><?= strtoupper(substr($d['owner_name'], 0, 1)) ?></span><?php endif; ?>
                                <?= htmlspecialchars($d['owner_name']) ?>
                            </span>
                        <?php else: ?><span style="color:#9CA3AF">—</span><?php endif; ?>
                    </td>
                    <!-- Dept -->
                    <td><?php if (!empty($d['dept_name'])): ?><span
                                class="badge badge-dept"><?= htmlspecialchars($d['dept_name']) ?></span><?php else: ?>—<?php endif; ?>
                    </td>
                    <!-- Notes -->
                    <td style="font-size:12px;color:#6B7280;white-space:normal"><?= htmlspecialchars($d['notes'] ?? '') ?>
                    </td>
                    <!-- Actions -->
                    <td class="col-act">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div style="display:flex;justify-content:center;gap:3px">
                            <button onclick='openEditDef(<?= htmlspecialchars(json_encode($d)) ?>)' class="btn btn-sm"
                                title="Sửa">✏️</button>
                            <form method="POST" onsubmit="return confirm('Xoá KPI này?')" style="margin:0">
                                <input type="hidden" name="action" value="del_def">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="color:#EF4444" title="Xoá">🗑</button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span style="color:#9CA3AF">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!empty($defs)): ?></tbody><?php endif; ?>
        <?php endif; ?>
        <tfoot>
            <tr>
                <td class="col-no"></td>
                <td colspan="2" style="color:#6B7280;font-size:12px">Tổng cộng (<?= count($defs) ?> KPI)</td>
                <td></td><!-- Target năm -->

                <!-- Cả năm: no footer total -->
                <td style="background:#EFF6FF;border-left:2px solid #BFDBFE;border-right:2px solid #BFDBFE"></td>

                <!-- Tỷ trọng total -->
                <?php $wcolor = (abs($total_weight - 100) < .01) ? '#059669' : '#D97706'; ?>
                <td style="text-align:right;color:<?= $wcolor ?>;font-weight:700">
                    <?= number_format($total_weight, 1) ?>%</td>

                <!-- Q totals footer -->
                <?php foreach ($q_colors_def as $qi => $qc):
                    $qFtSum = 0;
                    $qFtMixed = false;
                    $qFtHas = false;
                    foreach ($defs as $d) {
                        $t = $def_q_totals[$d['id']][$qi] ?? null;
                        if (!$t)
                            continue;
                        $qFtHas = true;
                        if ($t['mixed'])
                            $qFtMixed = true;
                        else
                            $qFtSum += $t['sum'];
                    }
                    ?>
                    <td
                        style="text-align:right;background:<?= $qc['bg'] ?>;font-weight:700;color:<?= $qc['head'] ?>;padding:4px 8px">
                        <?php if ($qFtHas && !$qFtMixed): ?>          <?php else: ?><span
                                style="color:#9CA3AF;font-weight:400">—</span><?php endif; ?>
                    </td>
                <?php endforeach; ?>

                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
function filterTbl(tblId, inputId) {
    const f = document.getElementById(inputId).value.toUpperCase();
    document.querySelectorAll('#'+tblId+' tbody tr:not(.group-row)').forEach(r=>{
        r.style.display = r.innerText.toUpperCase().includes(f)?'':'none';
    });
}
function exportCSV(tblId) {
    const t = document.getElementById(tblId);
    let csv = [];
    t.querySelectorAll('thead tr').forEach(r=>{
        csv.push([...r.querySelectorAll('th')].map(c=>'"'+c.innerText.trim()+'"').join(','));
    });
    t.querySelectorAll('tbody tr:not(.group-row)').forEach(r=>{
        if(r.style.display==='none') return;
        csv.push([...r.querySelectorAll('td')].map(c=>'"'+c.innerText.trim().replace(/"/g,'""')+'"').join(','));
    });
    const b = new Blob(['\uFEFF'+csv.join('\n')],{type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(b);
    a.download = 'KPI_<?= $year ?>_'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
}
</script>

<?php if ($_SESSION['role'] === 'admin'): ?>
<!-- SortableJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('defTable');
    if (!table) return;

    // 1. Sortable groups
    new Sortable(table, {
        draggable: 'tbody.kpi-group-tbody',
        handle: '.group-drag-handle',
        animation: 150,
        onEnd: function(evt) {
            saveSortOrder();
        }
    });

    // 2. Sortable items within groups
    document.querySelectorAll('tbody.kpi-group-tbody').forEach(tbody => {
        new Sortable(tbody, {
            draggable: 'tr.kpi-item-row',
            handle: '.item-drag-handle',
            group: 'shared', // allows moving between groups
            animation: 150,
            onEnd: function(evt) {
                // If it moved between groups, let's update the visual group name inside the row:
                const row = evt.item;
                const newTbody = row.closest('tbody');
                const newGroup = newTbody.dataset.group;
                const groupCol = row.querySelector('.item-group-col');
                if(groupCol) groupCol.textContent = newGroup;
                
                // Re-sort and save to backend
                saveSortOrder();
            }
        });
    });

    function saveSortOrder() {
        let data = [];
        let groupOrder = 1;
        document.querySelectorAll('tbody.kpi-group-tbody').forEach(tbody => {
            const groupName = tbody.dataset.group;
            let sortOrder = 1;
            tbody.querySelectorAll('tr.kpi-item-row').forEach(tr => {
                data.push({
                    id: tr.dataset.id,
                    group: groupName,
                    group_order: groupOrder,
                    sort_order: sortOrder++
                });
            });
            groupOrder++;
        });

        fetch('/api/kpi_sort.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sort_data: data})
        }).then(r => r.json()).then(res => {
            if (res.success) {
                // optional toast
            } else {
                console.error('Save failed:', res);
                alert('Lỗi lưu thứ tự KPI: ' + (res.error || 'Server error'));
            }
        }).catch(err => {
            console.error(err);
        });
    }
});
</script>

<?php endif; ?>

<!-- Quill Rich Text Editor Assets -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<!-- Drilldown Sidebar Styles -->
<style>
    /* Custom Quill adjustments */
    #quillExEditor {
        height: 100%;
        border: none !important;
        background: #fff;
        font-family: 'Roboto', sans-serif;
        font-size: 14px;
    }
    .ql-toolbar.ql-snow {
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px 12px 0 0;
        background: #fff;
    }
    .ql-container.ql-snow {
        border: 1px solid #e2e8f0 !important;
        border-radius: 0 0 12px 12px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
.q-drilldown-cell:hover {
    filter: brightness(0.96);
    box-shadow: inset 0 0 0 2px rgba(0,0,0,0.05);
}
#qDetailLayout {
    position: fixed; top: 0; right: -1000px; width: 450px; height: 100vh;
    background: #fff; box-shadow: -5px 0 25px rgba(0,0,0,0.1);
    z-index: 10001; transition: right 0.3s ease-out, width 0.3s ease-out;
    display: flex; flex-direction: row; font-family: 'Roboto', sans-serif;
}
#qDetailLayout.open { right: 0; }
#qDetailOverlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.3); z-index: 10000;
    display: none; opacity: 0; transition: opacity 0.3s;
}
#qDetailOverlay.open { display: block; opacity: 1; }

.q-detail-head { padding: 24px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
.q-detail-body { padding: 24px; flex: 1; overflow-y: auto; }
.q-month-card { 
    background: #fff; border: 1px solid #edf2f7; border-radius: 12px; 
    padding: 16px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;
    transition: transform 0.2s;
}
.q-month-card:hover { transform: translateY(-2px); border-color: #e2e8f0; }
.q-data-pill {
    padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 14px;
}
</style>

<!-- Drilldown Sidebar HTML -->
<div id="qDetailOverlay" class="q-detail-overlay" onclick="closeQDetailDraw()"></div>
<div id="qDetailLayout" class="q-detail-layout" style="display: flex; overflow: hidden; height: 100vh;">
    <!-- Main Content Panel (450px) -->
    <div style="width: 450px; flex-shrink: 0; display: flex; flex-direction: column; background: #fff; height: 100%;">
        <div class="q-detail-head">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div id="drawQText" style="font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; margin-bottom:4px;"></div>
                    <h3 id="drawKPIName" style="margin:0; font-size:18px; color:#1a202c; line-height:1.4;"></h3>
                </div>
                <button onclick="closeQDetailDraw()" style="background:none; border:none; cursor:pointer; color:#a0aec0; padding:4px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div class="q-detail-body">
            <!-- Summary Card -->
            <div style="background:#f8fafc; border-radius:16px; padding:20px; margin-bottom:24px; border:1px solid #e2e8f0;">
                <div style="display:flex; gap:12px; margin-bottom:16px;">
                    <div style="flex:1;">
                        <div style="font-size:11px; color:#718096; margin-bottom:4px; font-weight:600;">MỤC TIÊU</div>
                        <div id="drawTarget" style="font-size:18px; font-weight:700; color:#2d3748;"></div>
                    </div>
                    <div style="flex:1; text-align:right;">
                        <div style="font-size:11px; color:#718096; margin-bottom:4px; font-weight:600;">THỰC TẾ</div>
                        <div id="drawActual" style="font-size:18px; font-weight:700; color:#1d4ed8;"></div>
                    </div>
                </div>
                <div style="height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; margin-bottom:8px;">
                    <div id="drawBar" style="height:100%; transition:width 0.6s ease;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <span style="font-size:12px; color:#718096;">Hoàn thành kế hoạch</span>
                    <span id="drawPct" style="font-size:20px; font-weight:800; color:#2d3748;"></span>
                </div>
            </div>

            <h4 style="font-size:13px; color:#4a5568; margin-top:0; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
                <span id="drillTitle">BIẾN ĐỘNG QUÝ</span>
            </h4>
            
            <!-- Chart Section (Annual view only) -->
            <div id="chartSection" style="margin-bottom:24px; height:200px; display:none;">
                <canvas id="drillChart"></canvas>
            </div>

            <div id="drawMonthsList" style="margin-bottom:32px;"></div>

            <h4 style="font-size:13px; color:#4a5568; margin-top:0; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                LỊCH SỬ CẬP NHẬT
            </h4>
            <div id="drawAuditList" style="display:flex; flex-direction:column; gap:8px;"></div>
        </div>
    </div>

    <!-- Explanation Panel (450px) -->
    <div id="explanationPanel" style="width: 450px; flex-shrink: 0; background: #f8fafc; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; height: 100%;">
        <div style="padding: 24px; background: #fff; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <h3 id="exMonthTitle" style="margin: 0; font-size: 16px; color: #1e293b;">Nội dung giải trình</h3>
                <button onclick="closeKPIExplanation()" style="background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer;">&times;</button>
            </div>
            <div id="exKPIName" style="font-size: 12px; color: #64748b;">—</div>
        </div>
        <div style="flex: 1; padding: 24px; display: flex; flex-direction: column; gap: 12px; min-height: 0;">
            <div id="quillExEditor" placeholder="Nhập nội dung giải trình cho tháng này..."></div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px;">
                <button onclick="deleteKPIExplanation()" id="btnDeleteEx" class="btn" style="background: #fff; border: 1px solid #fee2e2; color: #ef4444; padding: 4px 12px; font-size: 11px; font-weight: 600;">Xóa giải trình</button>
                <div style="display: flex; gap: 12px;">
                    <button onclick="closeKPIExplanation()" class="btn" style="background: #fff; border: 1px solid #e2e8f0; color: #64748b; padding: 8px 16px;">Hủy</button>
                    <button onclick="saveKPIExplanation()" id="btnSaveEx" class="btn btn-primary" style="min-width: 120px; padding: 8px 24px; font-weight: 600;">Lưu giải trình</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteKPIExplanation() {
    if (!confirm('Bạn có chắc chắn muốn xóa nội dung giải trình này?')) return;
    
    const btn = document.getElementById('btnDeleteEx');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Đang xóa...';

    const payload = {
        kpi_def_id: currentKPIData.def_id,
        year: currentKPIData.year,
        month: currentExMonth,
        notes: '', // Clear the notes
        actual_value: currentKPIData.months[currentExMonth]?.val || '',
        score: null 
    };

    fetch('api/kpi_monthly_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            if(!currentKPIData.months[currentExMonth]) currentKPIData.months[currentExMonth] = {};
            currentKPIData.months[currentExMonth].notes = '';
            
            if(currentKPIData.quarter) {
                openQDetailDraw(currentKPIData);
            } else {
                openYearDetailDraw(currentKPIData);
            }
            closeKPIExplanation();
        } else {
            alert('Lỗi: ' + (res.error || 'Không rõ lỗi'));
        }
    })
    .catch(err => alert('Lỗi kết nối: ' + err))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
}
function fmtNum(val) {
    if (!val || val === '—') return '—';
    const s = String(val).trim().replace(/\./g, '');
    if (/^\d+$/.test(s)) return s.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return val;
}

let historyPage = 1;
let currentKPIData = null;

function loadKPIHistory(isNew = false) {
    if (isNew) {
        historyPage = 1;
        document.getElementById('drawAuditList').innerHTML = '<div style="font-size:12px; color:#a0aec0; text-align:center; padding:12px;">Đang tải lịch sử...</div>';
    }
    const data = currentKPIData;
    fetch(`/api/kpi_history.php?def_id=${data.def_id}&quarter=${data.quarter}&year=${data.year}&page=${historyPage}`)
        .then(r => r.json())
        .then(res => {
            if (isNew) document.getElementById('drawAuditList').innerHTML = '';
            
            // Remove existing Load More btn
            const oldMore = document.getElementById('btnHistoryMore');
            if (oldMore) oldMore.remove();

            if (res.success && res.logs.length > 0) {
                let logHtml = '';
                res.logs.forEach(log => {
                    const oldF = fmtNum(log.old_value);
                    const newF = fmtNum(log.new_value);
                    logHtml += `
                        <div style="padding:12px 0; border-bottom:1px dashed #edf2f7; position:relative;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <span style="font-size:12px; color:#2d3748; font-weight:700;">${log.updater_name}</span>
                                <span style="font-size:11px; color:#a0aec0;">${log.updated_at_fmt}</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px; font-size:12px;">
                                <span style="color:#718096; font-weight:600;">${log.kpi_name} - ${log.month > 0 ? 'Tháng ' + log.month : 'Quý ' + log.quarter} (${log.field_name}):</span>
                                <span style="color:#a0aec0; text-decoration:line-through;">${oldF}</span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#a0aec0" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                <span style="color:${data.color}; font-weight:700;">${newF}</span>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('drawAuditList').innerHTML += logHtml;

                if (res.has_more) {
                    historyPage++;
                    document.getElementById('drawAuditList').innerHTML += `
                        <button id="btnHistoryMore" onclick="loadKPIHistory()" style="width:100%; padding:10px; margin-top:10px; background:#f7fafc; border:1px solid #edf2f7; color:#718096; font-size:12px; font-weight:600; cursor:pointer; border-radius:8px; transition:0.2s;">
                            Xem thêm lịch sử...
                        </button>
                    `;
                }
            } else if (isNew) {
                document.getElementById('drawAuditList').innerHTML = '<div style="font-size:12px; color:#a0aec0; padding:12px; text-align:center; background:#f7fafc; border-radius:8px;">Chưa có lịch sử thay đổi</div>';
            }
        }).catch(() => {
            if (isNew) document.getElementById('drawAuditList').innerHTML = '<div style="font-size:12px; color:#ef4444; padding:12px; text-align:center;">Lỗi tải lịch sử</div>';
        });
}

function openQDetailDraw(data) {
    document.getElementById('drawQText').style.color = data.color;
    document.getElementById('drawQText').textContent = data.q_label;
    document.getElementById('drawKPIName').textContent = data.kpi_name;
    document.getElementById('drawTarget').textContent = fmtNum(data.target) + ' ' + data.unit;
    document.getElementById('drawActual').textContent = fmtNum(data.total_actual) + ' ' + data.unit;
    document.getElementById('drawActual').style.color = data.color;
    document.getElementById('drawPct').textContent = data.progress;
    
    // Determine dynamic color based on progress percentage
    let statusColor = '#EF4444'; // default red
    const pctVal = data.progress === '—' ? 0 : parseFloat(data.progress);
    if (pctVal >= 100) statusColor = '#10B981';
    else if (pctVal >= 80) statusColor = '#3B82F6';
    else if (pctVal >= 60) statusColor = '#F59E0B';

    const bar = document.getElementById('drawBar');
    bar.style.width = data.progress === '—' ? '0%' : (pctVal > 100 ? '100%' : pctVal + '%');
    bar.style.background = statusColor;
    document.getElementById('drawPct').style.color = statusColor;

    let monthsHtml = '';
    let auditHtml = '';
    for (const [m, info] of Object.entries(data.months)) {
        const fmtVal = fmtNum(info.val);
        const hasEx = info.notes && info.notes.trim().length > 0;
        monthsHtml += `
            <div class="q-month-card" style="margin-bottom:8px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:10px; color:#a0aec0; font-weight:700; text-transform:uppercase; margin-bottom:2px">Tháng ${m}</div>
                    <div style="font-size:15px; font-weight:700; color:#2d3748; margin-bottom:6px">${fmtVal} <small style="font-weight:400; font-size:11px; color:#718096;">${data.unit}</small></div>
                    <button onclick="toggleKPIExplanation(${m}, event)" 
                        data-notes="${encodeURIComponent(info.notes || '')}"
                        style="font-size:11px; color:#2563eb; background:#eff6ff; border:1px solid #dbeafe; padding:4px 10px; border-radius:6px; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:4px; transition:all .2s; margin-bottom: 4px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        ${hasEx ? 'Sửa giải trình' : 'Thêm giải trình'}
                    </button>
                    ${hasEx ? `
                    <div style="font-size:11px; color:#92400e; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:8px 12px; margin-top:10px; display:flex; gap:8px; align-items:flex-start;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div style="overflow:hidden;">
                            <strong>Giải trình:</strong> ${info.notes}
                        </div>
                    </div>` : ''}
                </div>
                <div style="width:4px; height:40px; border-radius:2px; background:${data.color || '#3b82f6'}; opacity:0.3;"></div>
            </div>
        `;
        if (info.at) {
            auditHtml += `
                <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px dashed #edf2f7;">
                    <div style="width:8px; height:8px; border-radius:50%; background:${data.color}; flex-shrink:0;"></div>
                    <div style="flex:1;">
                        <div style="font-size:12px; color:#2d3748; font-weight:600;">${info.by} <span style="font-weight:400; color:#718096;">cập nhật KPI này tháng ${m}</span></div>
                        <div style="font-size:11px; color:#a0aec0;">lúc ${info.at}</div>
                    </div>
                </div>
            `;
        }
    }
    document.getElementById('drawMonthsList').innerHTML = monthsHtml;
    
    currentKPIData = data;
    loadKPIHistory(true);

    document.getElementById('qDetailOverlay').classList.add('open');
    document.getElementById('qDetailLayout').classList.add('open');
}

function closeQDetailDraw() {
    document.getElementById('qDetailOverlay').classList.remove('open');
    document.getElementById('qDetailLayout').classList.remove('open');
}
</script>

<!-- Chart.js and Sidebar Enhancements -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.yr-drilldown-cell:hover { filter: brightness(0.96); box-shadow: inset 0 0 0 2px rgba(0,0,0,0.05); }
.q-detail-head { padding: 20px 24px; }
.q-month-card { transition: background 0.2s; }
.q-month-card:hover { background: #f8fafc !important; }
</style>

<script>
let kpiChartInstance = null;
let quillEx = null;

// Initialize Quill once
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('quillExEditor')) {
        quillEx = new Quill('#quillExEditor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['clean']
                ]
            },
            placeholder: 'Nhập nội dung giải trình cho tháng này...'
        });
    }
});

function openYearDetailDraw(data) {
    if(!document.getElementById('chartSection')) {
        // Inject sections dynamically if not present
        const bodyContent = document.querySelector('.q-detail-body');
        const chartHtml = `
            <div id="chartSection" style="margin-bottom:24px;">
                <h4 style="font-size:12px; color:#64748b; margin-top:0; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21H3V3"/><path d="M18 9l-5 5-3-3-4 4"/></svg>
                    BIẾN ĐỘNG THEO CÁC THÁNG
                </h4>
                <div style="height:200px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px;">
                    <canvas id="kpiTrendChart"></canvas>
                </div>
            </div>
        `;
        bodyContent.insertAdjacentHTML('afterbegin', chartHtml);
        
        // Find "Chi tiết dữ liệu" title and inject before it
        const monthsList = document.getElementById('drawMonthsList');
        const drillTitle = monthsList.previousElementSibling;
        drillTitle.id = 'drillTitle';
    }

    document.getElementById('chartSection').style.display = 'block';
    document.getElementById('drawQText').style.color = '#1D4ED8';
    document.getElementById('drawQText').textContent = 'BIẾN ĐỘNG CẢ NĂM ' + data.year;
    document.getElementById('drawKPIName').textContent = data.kpi_name;
    document.getElementById('drawTarget').textContent = fmtNum(data.target) + ' ' + data.unit;
    document.getElementById('drawActual').textContent = fmtNum(data.total_actual) + ' ' + data.unit;
    document.getElementById('drawActual').style.color = '#1D4ED8';
    document.getElementById('drawPct').textContent = data.progress;
    document.getElementById('drillTitle').innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
        BẢNG SỐ LIỆU 12 THÁNG
    `;

    let statusColor = '#EF4444';
    const pctVal = data.progress === '—' ? 0 : parseFloat(data.progress);
    if (pctVal >= 100) statusColor = '#10B981';
    else if (pctVal >= 80) statusColor = '#3B82F6';
    else if (pctVal >= 60) statusColor = '#F59E0B';

    const bar = document.getElementById('drawBar');
    bar.style.width = data.progress === '—' ? '0%' : (pctVal > 100 ? '100%' : pctVal + '%');
    bar.style.background = statusColor;
    document.getElementById('drawPct').style.color = statusColor;

    // Build Chart Data & Months list
    let listHtml = '';
    const labels = [];
    const values = [];

    for (let m = 1; m <= 12; m++) {
        const info = data.months[m];
        const val = info && info.val !== null ? info.val : 0;
        const fmt = info && info.fmt ? info.fmt : '—';
        labels.push('T' + m);
        values.push(val);

        const hasEx = info && info.notes && info.notes.trim().length > 0;
        listHtml += `
            <div class="q-month-card" style="margin-bottom:8px; padding:12px 18px; border:1px solid #f1f5f9; border-radius:12px; display:block;">
                <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                    <div>
                        <div style="font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase;">THÁNG ${m}</div>
                        <div style="font-size:15px; font-weight:700; color:#334155; margin-bottom:6px;">${fmt} <small style="font-weight:400; font-size:11px;">${data.unit}</small></div>
                    </div>
                    <div style="width:4px; height:40px; border-radius:2px; background:#e2e8f0;"></div>
                </div>
                ${hasEx ? `
                <div style="font-size:11px; color:#92400e; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:8px 12px; margin-top:10px; display:flex; gap:8px; align-items:flex-start;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div style="overflow:hidden;">
                        <strong>Giải trình:</strong> ${info.notes}
                    </div>
                </div>` : ''}
            </div>
        `;
    }
    document.getElementById('drawMonthsList').innerHTML = listHtml;

    // Render Chart
    setTimeout(() => {
        const canvas = document.getElementById('drillChart');
        if (!canvas) return; 
        const ctx = canvas.getContext('2d');
        if (kpiChartInstance) kpiChartInstance.destroy();
        kpiChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    borderColor: '#1D4ED8',
                    backgroundColor: 'rgba(29, 78, 216, 0.08)',
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f8fafc' }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } }
                }
            }
        });
    }, 100);

    currentKPIData = data;
    loadKPIHistory(true);
    document.getElementById('qDetailOverlay').classList.add('open');
    document.getElementById('qDetailLayout').classList.add('open');
}

// Intercept existing function to reset view
const oldOpenQ = openQDetailDraw;
openQDetailDraw = function(data) {
    if(document.getElementById('chartSection')) document.getElementById('chartSection').style.display = 'none';
    if(document.getElementById('drillTitle')) document.getElementById('drillTitle').textContent = 'BIẾN ĐỘNG QUÝ';
    oldOpenQ(data);
}

// --- KPI Explanation Functions ---
let currentExMonth = null;

function toggleKPIExplanation(month, event) {
    const btn = event.currentTarget;
    const notes = decodeURIComponent(btn.getAttribute('data-notes') || '');
    
    currentExMonth = month;
    document.getElementById('exMonthTitle').textContent = `Giải trình Tháng ${month}`;
    document.getElementById('exKPIName').textContent = currentKPIData.kpi_name;
    
    if (quillEx) {
        quillEx.root.innerHTML = notes;
    }
    
    // Show/hide delete button based on content
    const btnDel = document.getElementById('btnDeleteEx');
    if (btnDel) {
        btnDel.style.display = (notes && notes !== '<p><br></p>') ? 'block' : 'none';
    }
    
    // Expand Sidebar
    const layout = document.getElementById('qDetailLayout');
    layout.style.width = '900px';
}

function closeKPIExplanation() {
    const layout = document.getElementById('qDetailLayout');
    layout.style.width = '450px'; // Original width
}

function saveKPIExplanation() {
    const notes = quillEx ? quillEx.root.innerHTML.trim() : '';
    // If user deleted all, innerHTML might be <p><br></p>
    const cleanNotes = (notes === '<p><br></p>') ? '' : notes;
    
    const btn = document.getElementById('btnSaveEx');
    const originalText = btn.textContent;
    
    btn.disabled = true;
    btn.textContent = 'Đang lưu...';

    const payload = {
        kpi_def_id: currentKPIData.def_id,
        year: currentKPIData.year,
        month: currentExMonth,
        notes: notes,
        actual_value: currentKPIData.months[currentExMonth]?.val || '',
        score: null 
    };

    fetch('api/kpi_monthly_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            // Update local state so it reflects in the UI immediately
            if(!currentKPIData.months[currentExMonth]) currentKPIData.months[currentExMonth] = {};
            currentKPIData.months[currentExMonth].notes = notes;
            
            // Re-render the month list
            if(currentKPIData.quarter) {
                openQDetailDraw(currentKPIData);
            } else {
                openYearDetailDraw(currentKPIData);
            }
            
            closeKPIExplanation();
            // Optional: toast success
        } else {
            alert('Lỗi: ' + (res.error || 'Không rõ lỗi'));
        }
    })
    .catch(err => alert('Lỗi kết nối: ' + err))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

// Override close side draw to reset explanation
const originCloseSide = closeQDetailDraw;
closeQDetailDraw = function() {
    closeKPIExplanation();
    originCloseSide();
}
</script>
