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
                        style="padding:2px 4px;background:#EFF6FF;vertical-align:middle;border-left:2px solid #BFDBFE;border-right:2px solid #BFDBFE;text-align:center">
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
                                            <?= number_format($baseNum, 0, ',', '.') ?></div>
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
                        if ($qPlan === null && $baseNum)
                            $qPlan = $baseNum / 4;
                        $qProg = ($tot && !$tot['mixed'] && $qPlan) ? calcProgress($tot['sum'], $qPlan) : null;
                        ?>
                        <td style="padding:2px 4px;background:<?= $qc['bg'] ?>;vertical-align:top">
                            <?php if ($tot && !$tot['mixed']): ?>
                                <div style="font-size:12px;font-weight:700;color:<?= $qc['head'] ?>;margin-bottom:3px">
                                    <?= $tot['fmt'] ?>
                                    <?php if ($tot['count'] < 3): ?><span style="font-size:9px;font-weight:400;color:#9CA3AF">
                                            (<?= $tot['count'] ?>/3T)</span><?php endif; ?>
                                </div>
                                <div style="height:5px;background:#E5E7EB;border-radius:3px;overflow:hidden;margin-bottom:2px">
                                    <div
                                        style="height:100%;width:<?= $qProg ? $qProg['barPct'] : 0 ?>%;background:<?= $qProg ? $qProg['bar'] : '#E5E7EB' ?>;border-radius:3px;transition:width .4s">
                                    </div>
                                </div>
                                <?php if ($qProg): ?>
                                    <div style="display:flex;justify-content:space-between">
                                        <span style="font-size:9px;color:#9CA3AF">KH: <?= number_format($qPlan, 0, ',', '.') ?></span>
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
