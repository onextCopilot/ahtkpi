<?php // Tab: Settings — KPI name templates management ?>
<style>
    .tpl-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px
    }

    .tpl-table th {
        background: #F3F4F6;
        color: #374151;
        font-weight: 600;
        padding: 8px 12px;
        text-align: left;
        border-bottom: 2px solid #E5E7EB;
    }

    .tpl-table td {
        padding: 7px 12px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: middle
    }

    .tpl-table tr:hover td {
        background: #FAFAFA
    }

    .tpl-table tr.editing td {
        background: #EFF6FF
    }

    .tpl-add-row td {
        background: #F0FDF4;
        border-bottom: 2px solid #A7F3D0
    }

    .tpl-input {
        width: 100%;
        padding: 5px 8px;
        border: 1px solid #D1D5DB;
        border-radius: 5px;
        font-size: 13px;
        box-sizing: border-box
    }

    .tpl-input:focus {
        border-color: #1D4ED8;
        outline: none;
        box-shadow: 0 0 0 2px rgba(29, 78, 216, .1)
    }

    .group-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        background: #E0E7FF;
        color: #3730A3
    }
</style>

<div class="toolbar" style="justify-content:space-between">
    <div>
        <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827">⚙️ Cài đặt — Danh sách tên KPI mẫu</h3>
        <p style="margin:3px 0 0;font-size:12px;color:#6B7280">
            Các tên KPI ở đây sẽ hiện trong danh sách gợi ý khi thêm KPI mới.
        </p>
    </div>
    <button class="btn btn-blue" onclick="showAddTplRow()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Thêm KPI mẫu
    </button>
</div>

<div class="sheet-wrap">
    <table class="tpl-table" id="tplTable">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th>Tên KPI</th>
                <th style="width:160px">Nhóm / Danh mục</th>
                <th style="width:120px;text-align:center">Thao tác</th>
            </tr>
            <!-- Add row (hidden by default) -->
            <tr class="tpl-add-row" id="addTplRow" style="display:none">
                <td style="color:#059669;font-weight:700;text-align:center">+</td>
                <td>
                    <form method="POST" id="addTplForm" style="display:contents">
                        <input type="hidden" name="action" value="add_tpl">
                        <input type="hidden" name="tab" value="settings">
                        <input type="hidden" name="year" value="<?= $year ?>">
                        <input class="tpl-input" name="tpl_name" id="newTplName" placeholder="Nhập tên KPI mẫu..."
                            required autocomplete="off">
                </td>
                <td>
                    <input class="tpl-input" name="tpl_group" id="newTplGroup" placeholder="VD: Tài chính, Hiệu suất..."
                        list="tplGroupList" autocomplete="off">
                    <datalist id="tplGroupList">
                        <?php
                        $groups = array_unique(array_filter(array_column($kpi_templates, 'kpi_group')));
                        foreach ($groups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </td>
                <td style="text-align:center">
                    <button type="submit" class="btn btn-blue btn-sm">✓ Thêm</button>
                    <button type="button" class="btn btn-sm" onclick="hideAddTplRow()">✕</button>
                    </form>
                </td>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($kpi_templates)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;padding:40px;color:#9CA3AF">
                        Chưa có KPI mẫu nào. Nhấn <b>+ Thêm KPI mẫu</b> để bắt đầu.
                    </td>
                </tr>
            <?php endif; ?>

            <?php $cur_g = null;
            $i = 1;
            foreach ($kpi_templates as $t):
                if ($t['kpi_group'] !== $cur_g):
                    $cur_g = $t['kpi_group']; ?>
                    <tr>
                        <td colspan="4"
                            style="background:#F9FAFB;font-size:11px;font-weight:700;color:#6B7280;padding:5px 12px;letter-spacing:.05em;text-transform:uppercase">
                            <?= htmlspecialchars($cur_g ?: '(Chưa phân nhóm)') ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr id="tplRow_<?= $t['id'] ?>"
                    ondblclick="editTpl(<?= $t['id'] ?>,'<?= addslashes(htmlspecialchars($t['name'])) ?>','<?= addslashes(htmlspecialchars($t['kpi_group'] ?? '')) ?>')">
                    <!-- View mode -->
                    <td id="tplView_<?= $t['id'] ?>_no" style="color:#9CA3AF;text-align:center">
                        <?= $i++ ?>
                    </td>
                    <td id="tplView_<?= $t['id'] ?>_name" style="font-weight:500;color:#111827">
                        <?= htmlspecialchars($t['name']) ?>
                    </td>
                    <td id="tplView_<?= $t['id'] ?>_group">
                        <?php if ($t['kpi_group']): ?><span class="group-badge">
                                <?= htmlspecialchars($t['kpi_group']) ?>
                            </span>
                        <?php else: ?><span style="color:#D1D5DB">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <div id="tplViewAct_<?= $t['id'] ?>" style="display:flex;justify-content:center;gap:4px">
                            <button
                                onclick="editTpl(<?= $t['id'] ?>,'<?= addslashes($t['name']) ?>','<?= addslashes($t['kpi_group'] ?? '') ?>')"
                                class="btn btn-sm" title="Sửa">✏️</button>
                            <form method="POST" onsubmit="return confirm('Xoá KPI mẫu này?')" style="margin:0">
                                <input type="hidden" name="action" value="del_tpl">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="tab" value="settings">
                                <input type="hidden" name="year" value="<?= $year ?>">
                                <button type="submit" class="btn btn-sm" style="color:#EF4444" title="Xoá">🗑</button>
                            </form>
                        </div>
                        <!-- Edit form (hidden) -->
                        <form method="POST" id="editTplForm_<?= $t['id'] ?>"
                            style="display:none;gap:4px;justify-content:center">
                            <input type="hidden" name="action" value="edit_tpl">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="tab" value="settings">
                            <input type="hidden" name="year" value="<?= $year ?>">
                            <button type="submit" class="btn btn-blue btn-sm">✓</button>
                            <button type="button" class="btn btn-sm" onclick="cancelEditTpl(<?= $t['id'] ?>)">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="padding:8px 12px;font-size:12px;color:#9CA3AF">
                    <?= count($kpi_templates) ?> KPI mẫu &nbsp;·&nbsp; Gợi ý tự động hiện khi nhập tên KPI ở form thêm/sửa
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    function showAddTplRow() {
        document.getElementById('addTplRow').style.display = '';
        document.getElementById('newTplName').focus();
    }
    function hideAddTplRow() {
        document.getElementById('addTplRow').style.display = 'none';
        document.getElementById('addTplForm').reset();
    }

    function editTpl(id, name, group) {
        // Show inline edit inputs within the row
        const nameTd = document.getElementById('tplView_' + id + '_name');
        const groupTd = document.getElementById('tplView_' + id + '_group');
        const noTd = document.getElementById('tplView_' + id + '_no');
        const viewAct = document.getElementById('tplViewAct_' + id);
        const editForm = document.getElementById('editTplForm_' + id);
        const row = document.getElementById('tplRow_' + id);

        nameTd.innerHTML = `<input class="tpl-input" name="tpl_name" form="editTplForm_${id}" value="${name.replace(/"/g, '&quot;')}" required autocomplete="off">`;
        groupTd.innerHTML = `<input class="tpl-input" name="tpl_group" form="editTplForm_${id}" value="${group.replace(/"/g, '&quot;')}" list="tplGroupList" autocomplete="off">`;
        noTd.textContent = '✍️';
        viewAct.style.display = 'none';
        editForm.style.display = 'flex';
        row.classList.add('editing');
        nameTd.querySelector('input').focus();
    }

    function cancelEditTpl(id) {
        // Reload to restore original values cleanly
        location.reload();
    }

    // Keyboard shortcut: Esc cancels add row
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hideAddTplRow();
    });
</script>