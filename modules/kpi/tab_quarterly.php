<?php // Tab 2: Quarterly plan — Auto-save + monthly totals + progress bar
$q_labels = [1 => 'Q1 (T1-T3)', 2 => 'Q2 (T4-T6)', 3 => 'Q3 (T7-T9)', 4 => 'Q4 (T10-T12)'];
$q_months = [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8, 9], 4 => [10, 11, 12]];
$q_colors = [
    1 => ['bg' => '#EFF6FF', 'head' => '#1D4ED8', 'border' => '#BFDBFE'],
    2 => ['bg' => '#F0FDF4', 'head' => '#065F46', 'border' => '#A7F3D0'],
    3 => ['bg' => '#FFFBEB', 'head' => '#92400E', 'border' => '#FDE68A'],
    4 => ['bg' => '#FEF2F2', 'head' => '#991B1B', 'border' => '#FECACA'],
];

/** Strip thousand-separator dots: "12.000.000" → "12000000" */
function stripThousands($val)
{
    $v = trim($val);
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v))
        return str_replace('.', '', $v);
    return $v;
}

/**
 * Try to extract numeric value from a target string.
 * "12.000.000.000" → 12000000000
 * "135000000"      → 135000000
 * "135 tỷ"         → null (ambiguous unit)
 */
function parseTarget($val)
{
    if (!$val || trim($val) === '')
        return null;
    $stripped = stripThousands(trim($val));
    if (is_numeric($stripped))
        return (float) $stripped;
    return null;
}

/**
 * Calculate 3-month actual total for a quarter.
 * Returns ['sum'=>float,'fmt'=>string,'count'=>int,'mixed'=>bool] or null
 */
function quarterTotal($monthly_map, $def_id, $qmonths)
{
    $sum = 0;
    $count = 0;
    $mixed = false;
    foreach ($qmonths as $m) {
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
    return ['sum' => $sum, 'fmt' => number_format($sum, 0, ',', '.'), 'count' => $count, 'mixed' => $mixed];
}

/** Format numeric target for display: "12000000" → "12.000.000", text unchanged */
function fmtDisplayTarget($val)
{
    if ($val === null || $val === '')
        return '';
    $s = stripThousands(trim($val));
    if (is_numeric($s) && $s !== '')
        return number_format((float) $s, 0, ',', '.');
    return $val;
}
?>
<style>
    .q-progress-wrap {
        height: 7px;
        background: #E5E7EB;
        border-radius: 4px;
        overflow: hidden;
    }

    .q-progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width .5s ease;
    }
</style>

<div class="toolbar">
    <span style="font-size:13px;color:#6B7280">
        💡 Nhập kế hoạch từng quý — <b>tự động lưu</b>. Tổng thực tế &amp; % hoàn thành tính từ số liệu tháng.
    </span>
    <div style="flex:1"></div>
    <input type="text" id="qSearch" placeholder="🔍 Tìm KPI..." onkeyup="filterTbl('qTable','qSearch')"
        style="padding:6px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px;width:180px">
</div>

<div class="sheet-wrap">
    <table class="sheet" id="qTable">
        <thead>
            <tr>
                <th class="col-no">STT</th>
                <th style="min-width:200px">KPI</th>
                <th style="min-width:100px">Target năm</th>
                <th style="text-align:right;width:80px">Tỷ trọng</th>
                <?php foreach ($q_labels as $qi => $ql): ?>
                    <th
                        style="min-width:210px;background:<?= $q_colors[$qi]['bg'] ?>;color:<?= $q_colors[$qi]['head'] ?>;border-bottom:2px solid <?= $q_colors[$qi]['border'] ?>">
                        <?= $ql ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $cur_group = null;
            $stt = 1;
            foreach ($defs as $d):
                if ($d['kpi_group'] !== $cur_group):
                    $cur_group = $d['kpi_group']; ?>
                    <tr class="group-row">
                        <td colspan="<?= 4 + count($q_labels) ?>"><?= htmlspecialchars($cur_group ?? '(Chưa phân nhóm)') ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="col-no"><?= $stt++ ?></td>
                    <td style="white-space:normal;font-weight:500">
                        <?= htmlspecialchars($d['kpi_name']) ?>
                        <?php if ($d['is_condition']): ?><span class="badge badge-cond">ĐK</span><?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#6B7280;white-space:nowrap">
                        <?= htmlspecialchars($d['target_base'] ?? '—') ?>     <?php if (!empty($d['unit'])): ?><span
                                style="font-weight: 500;"><?= htmlspecialchars($d['unit']) ?></span><?php endif; ?>
                    </td>
                    <td style="text-align:right;font-weight:600"><?= number_format($d['weight'], 1) ?>%</td>

                    <?php foreach ($q_labels as $qi => $ql):
                        $qrow = $quarterly_map[$d['id']][$qi] ?? null;
                        $bgCol = $q_colors[$qi]['bg'];
                        $headCol = $q_colors[$qi]['head'];
                        $total = quarterTotal($monthly_map, $d['id'], $q_months[$qi]);

                        // Progress calculation
                        $targetNum = parseTarget($qrow['target_value'] ?? '');
                        $pct = null;
                        $barColor = '#E5E7EB';
                        $textColor = '#9CA3AF';
                        if ($total && !$total['mixed'] && $targetNum > 0) {
                            $pct = min(999, round($total['sum'] / $targetNum * 100, 1));
                            if ($pct >= 100) {
                                $barColor = '#10B981';
                                $textColor = '#065F46';
                            } elseif ($pct >= 80) {
                                $barColor = '#3B82F6';
                                $textColor = '#1D4ED8';
                            } elseif ($pct >= 60) {
                                $barColor = '#F59E0B';
                                $textColor = '#B45309';
                            } else {
                                $barColor = '#EF4444';
                                $textColor = '#B91C1C';
                            }
                        }
                        $barPct = $pct !== null ? min(100, $pct) : 0;
                        ?>
                        <td style="padding:2px 4px;background:<?= $bgCol ?>;vertical-align:top">
                            <div style="display:flex;flex-direction:column;gap:2px">

                                <!-- Label -->
                                <div
                                    style="font-size:9px;color:<?= $headCol ?>;font-weight:700;text-transform:uppercase;letter-spacing:.05em">
                                    Kế hoạch Q<?= $qi ?></div>

                                <!-- Target input -->
                                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $d['kpi_owner_id'] || $_SESSION['user_id'] == $d['dept_owner_id'] || $_SESSION['user_id'] == $d['dept_manager_id']): ?>
                                    <input type="text" class="qs-input" data-def="<?= $d['id'] ?>" data-quarter="<?= $qi ?>"
                                        data-year="<?= $year ?>" placeholder="Nhập target Q<?= $qi ?>"
                                        value="<?= htmlspecialchars(fmtDisplayTarget($qrow['target_value'] ?? '')) ?>"
                                        style="width:100%;padding:2px 5px;border:1px solid #D1D5DB;border-radius:3px;font-size:11px;background:#fff;box-sizing:border-box">
                                <?php else: ?>
                                    <div
                                        style="width:100%;padding:2px 5px;border:1px solid transparent;font-size:11px;box-sizing:border-box;color:#111827;font-weight:600">
                                        <?= htmlspecialchars(fmtDisplayTarget($qrow['target_value'] ?? '')) ?: '—' ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Divider -->
                                <div style="height:1px;background:rgba(0,0,0,.08);margin:1px 0"></div>

                                <!-- Actual + Progress -->
                                <div
                                    style="padding:2px 4px;background:rgba(0,0,0,.04);border-radius:4px;border-left:3px solid <?= $headCol ?>">
                                    <div
                                        style="font-size:9px;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px">
                                        Thực tế (<?= $total ? $total['count'] : 0 ?>/3 tháng)
                                    </div>
                                    <?php if ($total && !$total['mixed']): ?>
                                        <div style="font-size:13px;font-weight:700;color:<?= $headCol ?>;margin-bottom:5px">
                                            <?= htmlspecialchars($total['fmt']) ?>
                                        </div>
                                    <?php elseif ($total && $total['mixed']): ?>
                                        <div style="font-size:11px;color:#9CA3AF;margin-bottom:5px">Dữ liệu hỗn hợp</div>
                                    <?php else: ?>
                                        <div style="font-size:11px;color:#D1D5DB;margin-bottom:5px">Chưa có số liệu</div>
                                    <?php endif; ?>

                                    <!-- Progress bar -->
                                    <div class="q-progress-wrap">
                                        <div class="q-progress-fill" style="width:<?= $barPct ?>%;background:<?= $barColor ?>">
                                        </div>
                                    </div>

                                    <!-- % label row -->
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:3px">
                                        <?php if ($pct !== null): ?>
                                            <span style="font-size:10px;color:#6B7280">
                                                <?= htmlspecialchars($total['fmt'] ?? '0') ?> /
                                                <?= number_format($targetNum, 0, ',', '.') ?>
                                                <?php if (!empty($d['unit'])): ?><span><?= htmlspecialchars($d['unit']) ?></span><?php endif; ?>
                                            </span>
                                            <span style="font-size:12px;font-weight:800;color:<?= $textColor ?>"><?= $pct ?>%</span>
                                        <?php elseif ($targetNum === null && ($qrow['target_value'] ?? '') !== ''): ?>
                                            <span style="font-size:10px;color:#D1D5DB">Target không phải số</span>
                                        <?php else: ?>
                                            <span style="font-size:10px;color:#D1D5DB">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Save badge -->
                                <span class="qs-badge" data-def="<?= $d['id'] ?>" data-quarter="<?= $qi ?>"
                                    style="font-size:10px;color:#6B7280;display:none;text-align:right"></span>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($defs)): ?>
                <tr>
                    <td colspan="<?= 4 + count($q_labels) ?>" style="text-align:center;padding:40px;color:#9CA3AF">
                        Chưa có KPI. <a href="?tab=definitions&year=<?= $year ?>">→ Tạo bộ KPI trước</a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // ── Number format helpers ─────────────────────────────────
    function stripFmt(val) {
        if (!val && val !== 0) return '';
        const s = String(val).trim();
        // "12.000.000" → "12000000"
        if (/^\d{1,3}(\.\d{3})+$/.test(s)) return s.replace(/\./g, '');
        if (/^\d+$/.test(s)) return s;
        return s; // free-text unchanged
    }
    function fmtNumber(val) {
        if (!val) return '';
        const raw = stripFmt(val.trim());
        if (/^\d+$/.test(raw) && raw.length > 0)
            return raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return val; // non-numeric: unchanged
    }

    function qsSaveBadge(defId, quarter, msg, isErr) {
        const b = document.querySelector(`.qs-badge[data-def="${defId}"][data-quarter="${quarter}"]`);
        if (!b) return;
        b.style.display = 'block'; b.style.color = isErr ? '#EF4444' : '#10B981'; b.textContent = msg;
        if (!isErr) setTimeout(() => b.style.display = 'none', 1500);
    }
    function qsSaveCell(defId, quarter, year) {
        const td = document.querySelector(`.qs-input[data-def="${defId}"][data-quarter="${quarter}"]`)?.closest('td');
        if (!td) return;
        const inp = td.querySelector('.qs-input');
        const rawVal = stripFmt(inp?.value ?? ''); // strip formatting before saving
        fetch('/api/kpi_quarterly_save', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kpi_def_id: +defId, quarter: +quarter, year: +year, target_value: rawVal, weight_q: 0, status: 'active', notes: '' })
        }).then(r => r.json()).then(d => qsSaveBadge(defId, quarter, d.success ? '✓ Đã lưu' : '✗ Lỗi', !d.success))
            .catch(() => qsSaveBadge(defId, quarter, '✗ Lỗi kết nối', true));
    }
    document.querySelectorAll('.qs-input').forEach(inp => {
        inp.addEventListener('blur', function () {
            // Format number on blur; free text (e.g. "135 tỷ") left unchanged
            if (this.value.trim()) this.value = fmtNumber(this.value);
            qsSaveCell(this.dataset.def, this.dataset.quarter, this.dataset.year);
        });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
        });
    });
    function filterTbl(tblId, inputId) {
        const f = document.getElementById(inputId).value.toUpperCase();
        document.querySelectorAll('#' + tblId + ' tbody tr:not(.group-row)').forEach(r => {
            r.style.display = r.innerText.toUpperCase().includes(f) ? '' : 'none';
        });
    }
</script>