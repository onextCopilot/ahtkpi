<?php // Tab 2: Quarterly plan — Apple Minimalist Style Overhaul
$q_labels = [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'];
$q_months = [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8, 9], 4 => [10, 11, 12]];
$q_accents = [1 => '#0071E3', 2 => '#28CD41', 3 => '#FF9F0A', 4 => '#FF3B30'];

function stripThousands($val) {
    if (!$val) return '';
    $v = trim($val);
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) return str_replace('.', '', $v);
    return $v;
}

function parseTarget($val) {
    if (!$val || trim($val) === '') return null;
    $stripped = stripThousands(trim($val));
    return is_numeric($stripped) ? (float)$stripped : null;
}

function quarterTotal($monthly_map, $def_id, $qmonths, $calc_method = 'sum') {
    $sum = 0; $count = 0; $mixed = false;
    foreach ($qmonths as $m) {
        $val = $monthly_map[$def_id][$m]['actual_value'] ?? null;
        if ($val === null || $val === '') continue;
        $raw = stripThousands($val);
        if (is_numeric($raw)) { $sum += (float)$raw; $count++; } else { $mixed = true; $count++; }
    }
    if ($count === 0) return null;
    $display = ($calc_method === 'avg' && !$mixed && $count > 0) ? $sum / $count : $sum;
    return ['sum' => $display, 'fmt' => number_format($display, 0, ',', '.'), 'count' => $count, 'mixed' => $mixed];
}

function fmtDisplayTarget($val) {
    if ($val === null || $val === '') return '';
    $s = stripThousands(trim($val));
    if (is_numeric($s) && $s !== '') return number_format((float)$s, 0, ',', '.');
    return $val;
}
?>

<style>
    :root {
        --apple-bg: #F5F5F7;
        --apple-card: #FFFFFF;
        --apple-text: #1D1D1F;
        --apple-text-secondary: #86868B;
        --apple-blue: #0071E3;
        --apple-green: #28CD41;
        --apple-orange: #FF9F0A;
        --apple-red: #FF3B30;
    }

    body {
        background-color: var(--apple-bg) !important;
        color: var(--apple-text);
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .toolbar {
        padding: 24px 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Professional Grid Layout */
    .sheet-wrap {
        padding: 0 10px 40px 10px;
    }

    #qTable {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 4px; /* Ultra-compact vertical gap */
    }

    #qTable thead th {
        text-align: left;
        padding: 12px 12px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--apple-text);
        opacity: 0.9;
        border-bottom: 2px solid #E8E8ED;
    }

    /* Group Headers */
    .group-row td {
        padding: 16px 12px 4px 12px !important; /* Scaled down */
        font-size: 16px !important;
        font-weight: 700 !important;
        color: var(--apple-text) !important;
        letter-spacing: -0.02em !important;
        background: transparent !important;
        border: none !important;
    }

    /* The "Airy" but Condensed Rows */
    #qTable tbody tr:not(.group-row) td {
        background: var(--apple-card);
        padding: 8px 12px !important; /* Denser padding */
        border: none;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        vertical-align: middle;
        transition: background 0.15s, box-shadow 0.15s ease;
    }

    /* Card Rounding */
    #qTable tbody tr:not(.group-row) td:first-child { border-radius: 12px 0 0 12px; }
    #qTable tbody tr:not(.group-row) td:last-child { border-radius: 0 12px 12px 0; }

    #qTable tbody tr:not(.group-row):hover td {
        background-color: #FAFAFB;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        z-index: 10;
        position: relative;
    }

    /* KPI Name Column */
    .kpi-name-cell {
        font-size: 14px;
        font-weight: 700;
        color: var(--apple-text);
        letter-spacing: -0.01em;
        line-height: 1.2;
    }
    .kpi-meta-badge {
        display: inline-flex;
        padding: 2px 6px;
        background: #F2F2F7;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        color: var(--apple-text-secondary);
        margin-top: 4px;
        gap: 5px;
    }

    /* Quarterly Info Section */
    .q-box {
        display: flex;
        flex-direction: column;
        gap: 6px; /* Micro-gap */
    }

    .q-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .q-label-badge {
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--apple-text-secondary);
        letter-spacing: 0.05em;
    }

    /* Editable Target */
    .editable-target {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .val-text {
        font-size: 15px;
        font-weight: 800;
        color: var(--apple-text);
        letter-spacing: -0.03em;
    }
    .edit-trigger {
        opacity: 0;
        cursor: pointer;
        color: var(--apple-blue);
        transition: opacity 0.2s, transform 0.2s;
    }
    #qTable tr:hover .edit-trigger { opacity: 0.5; }
    .edit-trigger:hover { opacity: 1 !important; transform: scale(1.2); }

    /* Progress - Apple Style */
    .progress-track {
        height: 4px;
        background: #F2F2F7;
        border-radius: 10px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 1.2s cubic-bezier(0.2, 1, 0.2, 1);
    }

    /* Results */
    .actual-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }
    .actual-num {
        font-size: 13px;
        font-weight: 700;
        color: var(--apple-text);
    }
    .pct-val {
        font-size: 12px;
        font-weight: 800;
    }

    /* Input Overlays */
    .qs-input {
        display: none;
        width: 100%;
        border: 2px solid var(--apple-blue) !important;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 16px;
        font-weight: 700;
        background: #fff;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        outline: none;
    }
    .editing .val-text, .editing .edit-trigger, .editing .q-label-badge { display: none; }
    .editing .qs-input { display: block; }

    /* Micro-Notifications */
    .qs-badge {
        font-size: 10px;
        font-weight: 700;
        color: var(--apple-blue);
        animation: appleFadeIn 0.3s ease;
    }
    @keyframes appleFadeIn {
        from { opacity: 0; transform: translateY(2px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="toolbar">
    <div style="font-size:28px; font-weight:800; letter-spacing:-0.04em;">Quarterly Performance</div>
    <input type="text" id="qSearch" placeholder="Search KPI..." onkeyup="filterTbl('qTable','qSearch')"
        style="padding:12px 20px; border:none; border-radius:14px; background:#E8E8ED; font-size:15px; width:240px; outline:none; font-weight:500;">
</div>

<div class="sheet-wrap">
    <table id="qTable">
        <thead>
            <tr>
                <th style="width:60px; text-align:center;">Pos</th>
                <th style="min-width:300px;">Metric Definition</th>
                <?php foreach ($q_labels as $qi => $ql): ?>
                    <th style="min-width:240px;"><?= $ql ?> Planning</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $cur_group = null; $stt = 1;
            foreach ($defs as $d):
                if ($d['kpi_group'] !== $cur_group):
                    $cur_group = $d['kpi_group']; ?>
                    <tr class="group-row">
                        <td colspan="6"><?= htmlspecialchars($cur_group ?? 'Overview') ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="text-align:center; font-weight:700; color:#AEAEB2;"><?= sprintf('%02d', $stt++) ?></td>
                    <td class="kpi-name-cell">
                        <div><?= htmlspecialchars($d['kpi_name']) ?></div>
                        <div class="kpi-meta-badge">
                            <?php if ($d['is_condition']): ?><span>Condition Only</span><?php endif; ?>
                            <span>Weight: <?= number_format($d['weight'], 1) ?>%</span>
                            <span>Target: <?= htmlspecialchars($d['target_base'] ?? '—') ?></span>
                        </div>
                    </td>

                    <?php foreach ($q_labels as $qi => $ql):
                        $qrow = $quarterly_map[$d['id']][$qi] ?? null;
                        $accent = $q_accents[$qi];
                        $calc_method = $d['calc_method'] ?? 'sum';
                        $total = quarterTotal($monthly_map, $d['id'], $q_months[$qi], $calc_method);

                        $targetNum = parseTarget($qrow['target_value'] ?? '');
                        $pct = null; $statusColor = '#AEAEB2';
                        if ($total && !$total['mixed'] && $targetNum > 0) {
                            $pct = round($total['sum'] / $targetNum * 100, 0);
                            $statusColor = ($pct >= 100) ? '#248A3D' : (($pct >= 70) ? '#0071E3' : (($pct >= 40) ? '#F2994A' : '#EB5757'));
                            $statusColor = ($pct >= 100) ? 'var(--apple-green)' : (($pct >= 70) ? 'var(--apple-blue)' : (($pct >= 40) ? 'var(--apple-orange)' : 'var(--apple-red)'));
                        }
                        $barPct = $pct !== null ? min(100, $pct) : 0;
                        ?>
                        <td>
                            <div class="q-box">
                                <div class="q-header-row">
                                    <div class="q-label-badge"><?= $ql ?> Goal</div>
                                    <div class="qs-badge" data-def="<?= $d['id'] ?>" data-quarter="<?= $qi ?>" style="display:none;">Saved ✓</div>
                                    <div class="edit-trigger" onclick="const box = this.closest('.q-box'); if(box) startEdit(box.querySelector('.editable-target'))">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </div>
                                </div>

                                <div class="editable-target">
                                    <div class="val-text"><?= htmlspecialchars(fmtDisplayTarget($qrow['target_value'] ?? '')) ?: '—' ?></div>
                                    <input type="text" class="qs-input" data-def="<?= $d['id'] ?>" data-quarter="<?= $qi ?>" data-year="<?= $year ?>" 
                                        value="<?= htmlspecialchars(stripThousands($qrow['target_value'] ?? '')) ?>">
                                </div>

                                <div class="progress-track">
                                    <div class="progress-fill" style="width: <?= $barPct ?>%; background: <?= $statusColor ?>;"></div>
                                </div>

                                <div class="actual-row">
                                    <div class="actual-num"><?= $total ? $total['fmt'] : '0' ?> <span style="font-size:10px; opacity:0.5;"><?= htmlspecialchars($d['unit'] ?? '') ?></span></div>
                                    <?php if ($pct !== null): ?>
                                        <div class="pct-val" style="color: <?= $statusColor ?>;"><?= $pct ?>%</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function stripFmt(val) {
        if (!val && val !== 0) return '';
        const s = String(val).trim();
        if (/^\d{1,3}(\.\d{3})+$/.test(s)) return s.replace(/\./g, '');
        return s;
    }
    function fmtNumber(val) {
        if (!val) return '';
        const raw = stripFmt(val.trim());
        if (/^\d+$/.test(raw)) {
            return raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        return val;
    }

    function qsSaveCell(defId, quarter, year) {
        const inp = document.querySelector(`.qs-input[data-def="${defId}"][data-quarter="${quarter}"]`);
        const rawVal = stripFmt(inp?.value ?? '');
        fetch('/api/kpi_quarterly_save.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kpi_def_id: +defId, quarter: +quarter, year: +year, target_value: rawVal, weight_q: 0, status: 'active', notes: '' })
        }).then(r => r.json()).then(d => {
            const badge = document.querySelector(`.qs-badge[data-def="${defId}"][data-quarter="${quarter}"]`);
            if (badge && d.success) {
                badge.style.display = 'block';
                setTimeout(() => { badge.style.display = 'none'; }, 2000);
            }
        }).catch(e => console.error(e));
    }

    function startEdit(container) {
        if (!container) return;
        container.classList.add('editing');
        const inp = container.querySelector('.qs-input');
        if (inp) { inp.focus(); inp.select(); }
    }

    document.querySelectorAll('.qs-input').forEach(inp => {
        inp.addEventListener('blur', function () {
            const container = this.closest('.editable-target');
            if (container) container.classList.remove('editing');
            const val = this.value.trim();
            const raw = stripFmt(val);
            this.value = raw;
            const display = container?.querySelector('.val-text');
            if (display) display.textContent = fmtNumber(raw) || '—';
            qsSaveCell(this.dataset.def, this.dataset.quarter, this.dataset.year);
        });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
            if (e.key === 'Escape') {
                const container = this.closest('.editable-target');
                if (container) {
                    const display = container.querySelector('.val-text');
                    this.value = display.textContent === '—' ? '' : display.textContent;
                    container.classList.remove('editing');
                }
            }
        });
    });

    function filterTbl(tblId, inputId) {
        const f = document.getElementById(inputId).value.toUpperCase();
        document.querySelectorAll('#' + tblId + ' tbody tr:not(.group-row)').forEach(r => {
            r.style.display = r.innerText.toUpperCase().includes(f) ? '' : 'none';
        });
    }
</script>