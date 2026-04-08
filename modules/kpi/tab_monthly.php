<?php // Tab 3: Monthly actuals — Apple Minimalist & Ultra-Dense Style
$current_month = intval(date('n'));

function stripThousands($val) {
    if (!$val) return '';
    $v = trim($val);
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) return str_replace('.', '', $v);
    return $v;
}

function fmtDisplay($val) {
    if ($val === null || $val === '') return '';
    $s = stripThousands(trim($val));
    if (is_numeric($s) && $s !== '') return number_format((float)$s, 0, ',', '.');
    return htmlspecialchars($val);
}
?>

<style>
    :root {
        --apple-bg: #F5F5F7;
        --apple-card: #FFFFFF;
        --apple-text: #1D1D1F;
        --apple-text-secondary: #86868B;
        --apple-blue: #0071E3;
    }

    /* Professional Grid Layout */
    .sheet-wrap {
        padding: 0 8px 12px 8px;
        overflow-x: auto;
    }

    #mTable {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 2px;
        min-width: 1900px;
    }

    #mTable thead th {
        text-align: left;
        padding: 8px 12px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--apple-text);
        background: var(--apple-bg);
        border-bottom: 2px solid #E8E8ED;
        position: sticky;
        top: 0;
        z-index: 20;
    }

    /* Group Headers */
    .group-row td {
        padding: 8px 12px !important;
        background: #E8E8ED !important;
        font-size: 14px !important;
        font-weight: 800 !important;
        color: var(--apple-text) !important;
        border: none !important;
    }
    .group-row td:first-child { border-radius: 8px 0 0 8px; }
    .group-row td:last-child { border-radius: 0 8px 8px 0; }

    /* Data Rows */
    #mTable tbody tr:not(.group-row) td {
        background: var(--apple-card);
        padding: 4px 10px !important;
        border: none;
        box-shadow: 0 1px 1px rgba(0,0,0,0.01);
        vertical-align: middle;
        transition: background 0.1s;
    }

    #mTable tbody tr:not(.group-row) td:first-child { border-radius: 10px 0 0 10px; }
    #mTable tbody tr:not(.group-row) td:last-child { border-radius: 0 10px 10px 0; }

    #mTable tbody tr:not(.group-row):hover td {
        background-color: #FAFAFB;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }

    /* KPI Name */
    .kpi-name-cell {
        font-size: 13px;
        font-weight: 700;
        color: var(--apple-text);
        line-height: 1.1;
    }

    /* Editable Components */
    .editable-target {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 4px;
        min-height: 20px;
    }
    .val-text {
        font-size: 12px;
        font-weight: 700;
        color: var(--apple-text);
        white-space: nowrap;
    }
    .edit-trigger {
        opacity: 0;
        cursor: pointer;
        color: var(--apple-blue);
        transition: opacity 0.2s;
    }
    td:hover .edit-trigger { opacity: 0.5; }
    .edit-trigger:hover { opacity: 1 !important; transform: scale(1.1); }

    .as-input {
        display: none;
        width: 100%;
        border: 2px solid var(--apple-blue) !important;
        border-radius: 8px;
        padding: 4px 8px;
        font-size: 13px;
        font-weight: 700;
        background: #fff;
        outline: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .editing .val-text, .editing .edit-trigger { display: none; }
    .editing .as-input { display: block; }

    /* Current Month Highlight */
    .cur-month-indicator {
        background: rgba(0, 113, 227, 0.03) !important;
        border-left: 1px solid rgba(0, 113, 227, 0.1) !important;
        border-right: 1px solid rgba(0, 113, 227, 0.1) !important;
    }

    .qs-badge {
        font-size: 9px;
        font-weight: 800;
        color: var(--apple-blue);
        position: absolute;
        top: -6px;
        right: 4px;
    }
</style>

<div class="toolbar" style="padding:12px 8px; display:flex; align-items:center; justify-content:space-between;">
    <div style="font-size:20px; font-weight:800; letter-spacing:-0.03em;">Monthly Real-time</div>
    <input type="text" id="mSearch" placeholder="Filter metrics..." onkeyup="filterTbl('mTable','mSearch')"
        style="padding:8px 16px; border:none; border-radius:10px; background:#E8E8ED; font-size:14px; width:200px; outline:none; font-weight:500;">
</div>

<div class="sheet-wrap">
    <table id="mTable">
        <thead>
            <tr>
                <th style="width:40px; text-align:center;">#</th>
                <th style="min-width:200px;">KPI Metric</th>
                <th style="width:70px; text-align:right;">Wgt</th>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <th style="text-align:center; min-width:140px; <?= ($m === $current_month) ? 'color:var(--apple-blue);' : '' ?>">
                        Month <?= $m ?><?= ($m === $current_month) ? ' •' : '' ?>
                    </th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php $cur_group = null; $stt = 1;
            foreach ($defs as $d):
                if ($d['kpi_group'] !== $cur_group):
                    $cur_group = $d['kpi_group']; ?>
                    <tr class="group-row">
                        <td colspan="15"><?= htmlspecialchars($cur_group ?? 'General') ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="text-align:center; font-weight:700; color:#AEAEB2;"><?= sprintf('%02d', $stt++) ?></td>
                    <td class="kpi-name-cell"><?= htmlspecialchars($d['kpi_name']) ?></td>
                    <td style="text-align:right; font-weight:700; opacity:0.6; font-size:12px;"><?= number_format($d['weight'], 1) ?>%</td>

                    <?php for ($m = 1; $m <= 12; $m++):
                        $mrow = $monthly_map[$d['id']][$m] ?? null;
                        $act = $mrow['actual_value'] ?? '';
                        $isCur = ($m === $current_month);
                        ?>
                        <td class="<?= $isCur ? 'cur-month-indicator' : '' ?>" style="position:relative;">
                            <div class="editable-target">
                                <div class="val-text">
                                    <span class="v-num"><?= fmtDisplay($act) ?: '—' ?></span>
                                    <span class="v-unit" style="font-size: 9px; opacity: 0.5; font-weight: 600; margin-left: 2px;"><?= htmlspecialchars($d['unit'] ?? '') ?></span>
                                </div>
                                <?php if ($is_kpi_admin || $_SESSION['user_id'] == $d['kpi_owner_id'] || $_SESSION['user_id'] == $d['dept_owner_id'] || $_SESSION['user_id'] == $d['dept_manager_id'] || in_array($d['department_id'], $viewable_depts)): ?>
                                    <div class="edit-trigger" onclick="startEditMonthly(this.closest('.editable-target'))">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </div>
                                    <input type="text" class="as-input" data-def="<?= $d['id'] ?>" data-year="<?= $year ?>" data-month="<?= $m ?>" value="<?= htmlspecialchars(stripThousands($act)) ?>">
                                    <div class="qs-badge" style="display:none;">Saved ✓</div>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endfor; ?>
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
        if (/^\d+$/.test(raw)) return raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return val;
    }

    function saveMonthly(input) {
        const badge = input.closest('.editable-target').querySelector('.qs-badge');
        fetch('/api/kpi_monthly_save.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kpi_def_id: +input.dataset.def, year: +input.dataset.year, month: +input.dataset.month, actual_value: stripFmt(input.value) })
        }).then(r => r.json()).then(d => {
            if (d.success && badge) {
                badge.style.display = 'block';
                setTimeout(() => badge.style.display = 'none', 1500);
            }
        });
    }

    function startEditMonthly(container) {
        if (!container) return;
        container.classList.add('editing');
        const inp = container.querySelector('.as-input');
        if (inp) { inp.focus(); inp.select(); }
    }

    document.querySelectorAll('.as-input').forEach(inp => {
        inp.addEventListener('blur', function () {
            const container = this.closest('.editable-target');
            if (container) container.classList.remove('editing');
            const raw = stripFmt(this.value);
            this.value = raw;
            const display = container?.querySelector('.v-num');
            if (display) display.textContent = fmtNumber(raw) || '—';
            saveMonthly(this);
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