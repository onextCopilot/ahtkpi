<?php // Tab 3: Monthly actuals — Auto-save on blur, number formatting, NO score field
$current_month = intval(date('n'));

// Display formatted number (raw "12000000" → "12.000.000")
function fmtDisplay($val)
{
    if ($val === null || $val === '')
        return '';
    $raw = preg_replace('/\D/', '', $val); // digits only
    if ($raw !== '' && is_numeric($val) || preg_match('/^\d{1,3}(\.\d{3})*$/', trim($val))) {
        // already formatted or pure numeric
        $num = str_replace('.', '', trim($val)); // strip existing dots
        if (is_numeric($num))
            return number_format((float) $num, 0, ',', '.');
    }
    if (is_numeric(trim($val)))
        return number_format((float) $val, 0, ',', '.');
    return htmlspecialchars($val); // text like "135 tỷ" → display as-is
}

$q_bg = [1 => '#EFF6FF', 2 => '#EFF6FF', 3 => '#EFF6FF', 4 => '#F0FDF4', 5 => '#F0FDF4', 6 => '#F0FDF4', 7 => '#FFFBEB', 8 => '#FFFBEB', 9 => '#FFFBEB', 10 => '#FEF2F2', 11 => '#FEF2F2', 12 => '#FEF2F2'];
$q_head = [1 => '#1D4ED8', 2 => '#1D4ED8', 3 => '#1D4ED8', 4 => '#065F46', 5 => '#065F46', 6 => '#065F46', 7 => '#92400E', 8 => '#92400E', 9 => '#92400E', 10 => '#991B1B', 11 => '#991B1B', 12 => '#991B1B'];
$q_border = [1 => '#BFDBFE', 2 => '#BFDBFE', 3 => '#BFDBFE', 4 => '#A7F3D0', 5 => '#A7F3D0', 6 => '#A7F3D0', 7 => '#FDE68A', 8 => '#FDE68A', 9 => '#FDE68A', 10 => '#FECACA', 11 => '#FECACA', 12 => '#FECACA'];
?>

<style>
    .as-cell {
        padding: 3px 4px;
        position: relative;
    }

    .as-input {
        width: 100%;
        padding: 4px 7px;
        border: 1px solid #D1D5DB;
        border-radius: 4px;
        font-size: 12px;
        font-family: 'Roboto', sans-serif;
        background: #fff;
        color: #111827;
        box-sizing: border-box;
        transition: border-color .12s, box-shadow .12s;
    }

    .as-input:focus {
        border-color: #1D4ED8;
        outline: none;
        box-shadow: 0 0 0 2px rgba(29, 78, 216, .1);
    }

    .as-input.saving {
        border-color: #F59E0B !important;
    }

    .as-input.saved {
        border-color: #10B981 !important;
    }

    .as-input.err {
        border-color: #EF4444 !important;
    }

    .cur-month-col {
        outline: 2px solid #1D4ED8;
        outline-offset: -1px;
    }
</style>

<div class="toolbar" style="justify-content:space-between">
    <span style="font-size:13px;color:#6B7280">
        💡 Nhập số liệu thực tế — <b>tự động lưu</b> khi rời ô &nbsp;|&nbsp; Số tự động format: <code>12000000</code> →
        <code>12.000.000</code>
    </span>
    <input id="mSearch" type="text" placeholder="🔍 Tìm KPI..." onkeyup="filterTbl('mTable','mSearch')"
        style="padding:6px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px;width:180px">
</div>

<div class="sheet-wrap">
    <table class="sheet" id="mTable" style="min-width:1600px">
        <thead>
            <tr>
                <th class="col-no" rowspan="2">STT</th>
                <th style="min-width:200px" rowspan="2">KPI</th>
                <th style="min-width:80px;text-align:right" rowspan="2">Tỷ trọng</th>
                <th colspan="3"
                    style="text-align:center;background:#EFF6FF;color:#1D4ED8;border-bottom:1px solid #BFDBFE">Q1</th>
                <th colspan="3"
                    style="text-align:center;background:#F0FDF4;color:#065F46;border-bottom:1px solid #A7F3D0">Q2</th>
                <th colspan="3"
                    style="text-align:center;background:#FFFBEB;color:#92400E;border-bottom:1px solid #FDE68A">Q3</th>
                <th colspan="3"
                    style="text-align:center;background:#FEF2F2;color:#991B1B;border-bottom:1px solid #FECACA">Q4</th>
            </tr>
            <tr>
                <?php for ($m = 1; $m <= 12; $m++):
                    $isCur = ($m === $current_month);
                    ?>
                    <th style="min-width:110px;text-align:center;background:<?= $q_bg[$m] ?>;color:<?= $q_head[$m] ?>;
        <?= $isCur ? 'font-weight:800;border-bottom:2px solid ' . $q_head[$m] : '' ?>">
                        T<?= $m ?><?= $isCur ? ' ●' : '' ?>
                    </th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php $cur_group = null;
            $stt = 1;
            foreach ($defs as $d):
                $did = $d['id'];
                if ($d['kpi_group'] !== $cur_group):
                    $cur_group = $d['kpi_group']; ?>
                    <tr class="group-row">
                        <td colspan="15"><?= htmlspecialchars($cur_group ?? '(Chưa phân nhóm)') ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="col-no"><?= $stt++ ?></td>
                    <td style="white-space:normal;font-weight:500">
                        <?= htmlspecialchars($d['kpi_name']) ?>
                        <?php if ($d['is_condition']): ?><span class="badge badge-cond"
                                style="margin-left:4px">ĐK</span><?php endif; ?>
                    </td>
                    <td style="text-align:right;font-weight:600"><?= number_format($d['weight'], 1) ?>%</td>

                    <?php for ($m = 1; $m <= 12; $m++):
                        $mrow = $monthly_map[$did][$m] ?? null;
                        $act = $mrow['actual_value'] ?? '';
                        $isCur = ($m === $current_month);
                        ?>
                        <td class="as-cell <?= $isCur ? 'cur-month-col' : '' ?>"
                            style="background:<?= $q_bg[$m] ?>;padding:3px 4px">
                            <?php if ($is_kpi_admin || $_SESSION['user_id'] == $d['kpi_owner_id'] || $_SESSION['user_id'] == $d['dept_owner_id'] || $_SESSION['user_id'] == $d['dept_manager_id'] || in_array($d['department_id'], $viewable_depts)): ?>
                                <input type="text" class="as-input" data-def="<?= $did ?>" data-year="<?= $year ?>"
                                    data-month="<?= $m ?>" placeholder="Thực tế T<?= $m ?>" value="<?= fmtDisplay($act) ?>">
                            <?php else: ?>
                                <div style="font-size:12px;padding:4px 7px;font-weight:600;color:#111827">
                                    <?= fmtDisplay($act) ?: '—' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($defs)): ?>
                <tr>
                    <td colspan="15" style="text-align:center;padding:40px;color:#9CA3AF">
                        Chưa có KPI. <a href="?tab=definitions&year=<?= $year ?>">→ Tạo bộ KPI trước</a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // ─── Number helpers ──────────────────────────────────────
    function stripFmt(val) {
        if (!val && val !== 0) return '';
        const s = String(val).trim();
        // "12.000.000" → "12000000" (thousand-sep dots)
        if (/^\d{1,3}(\.\d{3})+$/.test(s)) return s.replace(/\./g, '');
        if (/^\d+$/.test(s)) return s;
        return s;
    }
    function fmtNumber(val) {
        if (!val) return '';
        const raw = stripFmt(val.trim());
        if (/^\d+$/.test(raw) && raw.length > 0) {
            return raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        return val;
    }

    // ─── Auto-save logic ─────────────────────────────────────
    const pending = {};

    function saveActual(input) {
        const defId = input.dataset.def;
        const year = input.dataset.year;
        const month = input.dataset.month;
        const raw = stripFmt(input.value);

        input.classList.add('saving');

        fetch('/api/kpi_monthly_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kpi_def_id: +defId,
                year: +year,
                month: +month,
                actual_value: raw,
                score: null,
                notes: ''
            })
        })
            .then(r => r.json())
            .then(d => {
                input.classList.remove('saving');
                if (d.success) {
                    input.classList.add('saved');
                    setTimeout(() => input.classList.remove('saved'), 1200);
                } else {
                    input.classList.add('err');
                }
            })
            .catch(() => {
                input.classList.remove('saving');
                input.classList.add('err');
            });
    }

    // Event delegation on table
    document.getElementById('mTable').addEventListener('blur', function (e) {
        const inp = e.target;
        if (!inp.classList.contains('as-input')) return;

        // Format on blur
        if (inp.value.trim()) inp.value = fmtNumber(inp.value);

        // Debounced save
        const key = `${inp.dataset.def}_${inp.dataset.year}_${inp.dataset.month}`;
        clearTimeout(pending[key]);
        pending[key] = setTimeout(() => saveActual(inp), 60);
    }, true);

    // Enter → move to next row same col
    document.getElementById('mTable').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const inp = e.target;
        if (!inp.classList.contains('as-input')) return;
        e.preventDefault();
        inp.blur();
        const row = inp.closest('tr');
        const nextRow = row.nextElementSibling;
        if (nextRow && !nextRow.classList.contains('group-row')) {
            const idx = [...row.querySelectorAll('td')].indexOf(inp.closest('td'));
            const nextTd = nextRow.querySelectorAll('td')[idx];
            if (nextTd) nextTd.querySelector('.as-input')?.focus();
        }
    });

    function filterTbl(tblId, inputId) {
        const f = document.getElementById(inputId).value.toUpperCase();
        document.querySelectorAll('#' + tblId + ' tbody tr:not(.group-row)').forEach(r => {
            r.style.display = r.innerText.toUpperCase().includes(f) ? '' : 'none';
        });
    }
</script>