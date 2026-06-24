<?php
/**
 * Kho ứng viên - danh sách + lọc nâng cao + thao tác hàng loạt + xuất + thêm thủ công.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/candidates.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$f = hrm_candidate_filters();
$opts = hrm_candidate_filter_options($conn);
$rows = hrm_candidate_query($conn, $f, 500);
$statuses = hrm_candidate_statuses();
$total = (int) ($conn->query("SELECT COUNT(*) c FROM hrm_candidates WHERE status<>'archived'")->fetch_assoc()['c'] ?? 0);
$qs = http_build_query(array_filter($f, fn($v) => $v !== '' && $v !== 0 && $v !== -1));

// Pipeline cho cột "Giai đoạn hiện tại" (track = các bước không phải "Từ chối").
$pipe = $conn->query("SELECT name,code,stage_type,sort_order FROM hrm_pipeline_stages ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$track = array_values(array_filter($pipe, fn($s) => $s['stage_type'] !== 'rejected'));
$trackTotal = count($track);
$posByName = [];
foreach ($track as $i => $s) {
    $posByName[mb_strtolower(trim($s['name']))] = $i + 1;
}
$typeByName = [];
foreach ($pipe as $s) {
    $typeByName[mb_strtolower(trim($s['name']))] = $s['stage_type'];
}
$stageCell = function ($name) use ($posByName, $typeByName, $trackTotal) {
    $name = trim((string) $name);
    if ($name === '') {
        return '<span class="cd-mut">-</span>';
    }
    $key = mb_strtolower($name);
    $type = $typeByName[$key] ?? '';
    if ($type === 'rejected') {
        return '<div class="cd-stage rej"><div class="cd-stage-lbl">' . h($name) . '</div>'
            . '<div class="cd-track"><i class="dot rej"></i></div></div>';
    }
    $pos = $posByName[$key] ?? 0;
    $dots = '';
    for ($i = 1; $i <= $trackTotal; $i++) {
        if ($i > 1) {
            $dots .= '<i class="line' . ($pos > 0 && $i <= $pos ? ' done' : '') . '"></i>';
        }
        $dots .= '<i class="dot' . ($pos > 0 && $i <= $pos ? ' done' : '') . ($i === $pos ? ' cur' : '') . '"></i>';
    }
    $num = $pos > 0 ? ' <span class="cd-stage-num">(' . $pos . '/' . $trackTotal . ')</span>' : '';
    return '<div class="cd-stage"><div class="cd-stage-lbl">' . h($name) . $num . '</div><div class="cd-track">' . $dots . '</div></div>';
};
// Icon nhỏ cho cột liên hệ.
$icMail = '<svg viewBox="0 0 24 24" class="cd-ic"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>';
$icPhone = '<svg viewBox="0 0 24 24" class="cd-ic"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';

hrm_header('Ứng viên', 'Kho ứng viên (' . $total . ')', 'candidates');
?>
<!-- ── Toolbar ──────────────────────────────────────────────────────── -->
<div class="cdf-wrap">
    <!-- Hàng 1: Tìm kiếm + bộ lọc chính + actions -->
    <form class="cdf-row cdf-row1" method="get" id="filterForm">
        <div class="cdf-search-wrap">
            <svg class="cdf-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input name="q" value="<?= h($f['q']) ?>" placeholder="Tìm tên, email, SĐT, vị trí..." class="cdf-search" id="cdf-q" autocomplete="off">
        </div>
        <select name="status" class="cdf-sel">
            <option value="">Mọi trạng thái</option>
            <?php foreach ($statuses as $k => $lbl): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?>
        </select>
        <select name="source" class="cdf-sel">
            <option value="0">Tất cả nguồn</option>
            <?php foreach ($opts['sources'] as $s): ?><option value="<?= $s['id'] ?>"<?= $f['source'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="owner" class="cdf-sel">
            <option value="0">Mọi phụ trách</option>
            <?php foreach ($opts['owners'] as $o): ?><option value="<?= $o['id'] ?>"<?= $f['owner'] === (int)$o['id'] ? ' selected' : '' ?>><?= h($o['full_name']) ?></option><?php endforeach; ?>
        </select>
        <button class="cdf-btn-filter" type="submit" title="Lọc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Lọc
        </button>
        <?php if (array_filter($f, fn($v) => $v !== '' && $v !== 0 && $v !== -1)): ?>
        <a href="/hrm/candidates" class="cdf-btn-clear" title="Xóa bộ lọc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </a>
        <?php endif; ?>
        <button type="button" class="cdf-btn-more" id="cdfMoreBtn" onclick="toggleMoreFilters()" title="Bộ lọc nâng cao">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
            Nâng cao
        </button>

        <!-- Spacer -->
        <div style="flex:1;min-width:8px"></div>

        <!-- Actions -->
        <div class="cdf-actions">
            <button type="button" class="cdf-btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Thêm ứng viên
            </button>
            <div class="cdf-menu-wrap">
                <button type="button" class="cdf-btn-icon" id="cdfActBtn" onclick="toggleActMenu()" title="Thêm thao tác">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
                <div class="cdf-menu" id="cdfActMenu">
                    <a href="/hrm/candidates/import" class="cdf-menu-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Import Excel
                    </a>
                    <a href="/hrm/candidates/export?fmt=xls&<?= h($qs) ?>" class="cdf-menu-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Xuất Excel
                    </a>
                    <a href="/hrm/candidates/export?fmt=csv&<?= h($qs) ?>" class="cdf-menu-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Xuất CSV
                    </a>
                    <div class="cdf-menu-divider"></div>
                    <button type="button" class="cdf-menu-item" onclick="linkPipeline();document.getElementById('cdfActMenu').classList.remove('open')" title="Gắn ứng viên đã có 'Tin ứng tuyển' vào pipeline tin tương ứng">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Đồng bộ pipeline
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Hàng 2: Bộ lọc nâng cao (ẩn/hiện) -->
    <div class="cdf-row cdf-more" id="cdfMore" style="display:none">
        <form method="get" class="cdf-more-inner" id="filterFormMore">
            <?php /* Giữ lại các filter chính khi submit form nâng cao */ ?>
            <input type="hidden" name="q" id="cdfMoreQ">
            <input type="hidden" name="status" id="cdfMoreStatus">
            <input type="hidden" name="source" id="cdfMoreSource">
            <input type="hidden" name="owner" id="cdfMoreOwner">

            <div class="cdf-more-group">
                <label class="cdf-lbl">Sự kiện</label>
                <select name="event" class="cdf-sel">
                    <option value="0">Tất cả</option>
                    <?php foreach ($opts['events'] as $e): ?><option value="<?= $e['id'] ?>"<?= $f['event'] === (int)$e['id'] ? ' selected' : '' ?>><?= h($e['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="cdf-more-group">
                <label class="cdf-lbl">Pool</label>
                <select name="pool_id" class="cdf-sel">
                    <option value="0">Mọi pool</option>
                    <?php foreach ($opts['pools'] as $p): ?><option value="<?= $p['id'] ?>"<?= $f['pool_id'] === (int)$p['id'] ? ' selected' : '' ?>><?= h($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="cdf-more-group">
                <label class="cdf-lbl">Kỹ năng</label>
                <input name="skill" value="<?= h($f['skill']) ?>" placeholder="VD: React" class="cdf-sel">
            </div>
            <div class="cdf-more-group">
                <label class="cdf-lbl">Thẻ</label>
                <input name="tag" value="<?= h($f['tag']) ?>" placeholder="VD: senior" class="cdf-sel">
            </div>
            <div class="cdf-more-group">
                <label class="cdf-lbl">CV</label>
                <select name="has_cv" class="cdf-sel">
                    <option value="">Tất cả</option>
                    <option value="1"<?= $f['has_cv'] === '1' ? ' selected' : '' ?>>Có CV</option>
                    <option value="0"<?= $f['has_cv'] === '0' ? ' selected' : '' ?>>Chưa có CV</option>
                </select>
            </div>
            <div class="cdf-more-group">
                <label class="cdf-lbl">Từ ngày</label>
                <input type="date" name="from" value="<?= h($f['from']) ?>" class="cdf-sel">
            </div>
            <div class="cdf-more-group">
                <label class="cdf-lbl">Đến ngày</label>
                <input type="date" name="to" value="<?= h($f['to']) ?>" class="cdf-sel">
            </div>
            <button type="submit" class="cdf-btn-filter">Áp dụng</button>
        </form>
    </div>
</div>

<style>
/* ── Candidate Filter Toolbar ──────────────────────────── */
.cdf-wrap{background:#fff;border:1px solid #e8ecf0;border-radius:14px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.cdf-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.cdf-row1{}
/* Search */
.cdf-search-wrap{position:relative;flex:1;min-width:220px;max-width:340px}
.cdf-search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;stroke:#94a3b8;pointer-events:none}
.cdf-search{width:100%;padding:8px 12px 8px 34px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;background:#f8fafc;outline:none;transition:.15s;color:#0f172a}
.cdf-search:focus{border-color:#0a252a;background:#fff;box-shadow:0 0 0 3px rgba(10,37,42,.08)}
/* Select */
.cdf-sel{padding:8px 11px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;background:#f8fafc;color:#334155;outline:none;cursor:pointer;transition:.15s}
.cdf-sel:focus{border-color:#0a252a;background:#fff}
/* Buttons */
.cdf-btn-filter{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid #0a252a;border-radius:8px;background:#0a252a;color:#fff;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;transition:.15s}
.cdf-btn-filter:hover{background:#0e3640}
.cdf-btn-filter svg{width:14px;height:14px}
.cdf-btn-clear{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#dc2626;cursor:pointer;text-decoration:none;transition:.15s;flex-shrink:0}
.cdf-btn-clear:hover{background:#fee2e2}
.cdf-btn-clear svg{width:14px;height:14px}
.cdf-btn-more{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#475569;font-size:13px;font-weight:500;font-family:inherit;cursor:pointer;transition:.15s}
.cdf-btn-more:hover,.cdf-btn-more.active{border-color:#0a252a;color:#0a252a;background:#f0f9f8}
.cdf-btn-more svg{width:14px;height:14px}
/* Actions */
.cdf-actions{display:flex;align-items:center;gap:6px;flex-shrink:0}
.cdf-btn-primary{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:8px;background:#0a252a;color:#fff;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;transition:.15s}
.cdf-btn-primary:hover{background:#0e3640}
.cdf-btn-primary svg{width:14px;height:14px}
.cdf-btn-icon{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#475569;cursor:pointer;transition:.15s}
.cdf-btn-icon:hover{border-color:#0a252a;color:#0a252a;background:#f0f9f8}
.cdf-btn-icon svg{width:16px;height:16px}
/* Dropdown menu */
.cdf-menu-wrap{position:relative}
.cdf-menu{position:absolute;top:calc(100% + 6px);right:0;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:180px;z-index:200;display:none}
.cdf-menu.open{display:block}
.cdf-menu-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;font-size:13px;color:#334155;text-decoration:none;background:none;border:none;width:100%;font-family:inherit;cursor:pointer;transition:.12s}
.cdf-menu-item:hover{background:#f1f5f9;color:#0f172a}
.cdf-menu-item svg{width:14px;height:14px;flex-shrink:0;stroke:#94a3b8}
.cdf-menu-item:hover svg{stroke:#0a252a}
.cdf-menu-divider{height:1px;background:#f1f5f9;margin:4px 0}
/* Advanced filter row */
.cdf-more{margin-top:12px;padding-top:12px;border-top:1px solid #f1f5f9}
.cdf-more-inner{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end}
.cdf-more-group{display:flex;flex-direction:column;gap:4px}
.cdf-lbl{font-size:11px;font-weight:600;color:#64748b;letter-spacing:.3px;text-transform:uppercase}
</style>
<script>
function toggleMoreFilters(){
    const el=document.getElementById('cdfMore');
    const btn=document.getElementById('cdfMoreBtn');
    const open=el.style.display==='none';
    el.style.display=open?'block':'none';
    btn.classList.toggle('active',open);
    // Sync hidden fields từ form chính
    if(open){
        document.getElementById('cdfMoreQ').value=document.getElementById('cdf-q').value;
        document.getElementById('cdfMoreStatus').value=document.querySelector('[name=status]').value;
        document.getElementById('cdfMoreSource').value=document.querySelector('[name=source]').value;
        document.getElementById('cdfMoreOwner').value=document.querySelector('[name=owner]').value;
    }
}
function toggleActMenu(){
    const m=document.getElementById('cdfActMenu');
    m.classList.toggle('open');
}
document.addEventListener('click',function(e){
    if(!e.target.closest('.cdf-menu-wrap')){
        const m=document.getElementById('cdfActMenu');
        if(m)m.classList.remove('open');
    }
});
// Mở sẵn advanced filter nếu có filter nâng cao đang áp dụng
<?php if ($f['event'] || $f['pool_id'] || $f['skill'] || $f['tag'] || $f['has_cv'] !== '' || $f['from'] || $f['to']): ?>
document.addEventListener('DOMContentLoaded',function(){ toggleMoreFilters(); });
<?php endif; ?>
</script>

<!-- Thanh thao tác hàng loạt -->
<div id="bulkBar" style="display:none" class="cd-bulk">
    <span><b id="bulkCount">0</b> đã chọn</span>
    <input id="bulkTag" class="cd-in" placeholder="Thêm thẻ..." style="width:140px">
    <button class="rc-btn ghost" onclick="bulk('tag', document.getElementById('bulkTag').value)">Gắn thẻ</button>
    <select id="bulkStatus" class="cd-in">
        <option value="">Đổi trạng thái...</option>
        <?php foreach ($statuses as $k => $lbl): ?>
            <option value="<?= $k ?>"><?= h($lbl) ?></option><?php endforeach; ?>
    </select>
    <button class="rc-btn ghost" onclick="bulk('status', document.getElementById('bulkStatus').value)">Áp dụng</button>
    <select id="bulkPool" class="cd-in">
        <option value="">Thêm vào pool...</option>
        <?php foreach ($opts['pools'] as $p): ?>
            <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="rc-btn ghost" onclick="bulk('add_pool', document.getElementById('bulkPool').value)">Thêm
        pool</button>
    <button class="rc-btn ghost" id="mergeBtn" style="display:none" onclick="mergeTwo()">Gộp 2 hồ sơ</button>
    <button class="rc-btn ghost" style="color:#dc2626"
        onclick="if(confirm('Lưu trữ các ứng viên đã chọn?'))bulk('delete','')">Lưu trữ</button>
</div>

<?php
$pal = ['#0071e3', '#34c759', '#ff9500', '#af52de', '#ff2d55', '#5ac8fa', '#ffcc00', '#ff3b30', '#30b0c7', '#a2845e'];
$avatar = function ($name) use ($pal) {
    $p = preg_split('/\s+/', trim($name));
    $ini = mb_strtoupper(mb_substr(end($p) ?: $name, 0, 1) . (count($p) > 1 ? mb_substr($p[0], 0, 1) : ''));
    return [$ini, $pal[abs(crc32($name)) % count($pal)]];
};
$stCol = ['new' => '#0071e3', 'active' => '#b45309', 'pooled' => '#7c3aed', 'hired' => '#16a34a', 'blacklist' => '#dc2626', 'archived' => '#64748b'];
?>
<?php if (!$rows): ?>
    <div class="rc-empty">
        <?= $total ? 'Không có ứng viên khớp bộ lọc.' : 'Chưa có ứng viên. Bấm "+ Thêm ứng viên" hoặc "Import Excel".' ?>
    </div>
<?php else: ?>
    <div class="cd-scroll">
        <table class="cd-table">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                    <th>Họ và tên</th>
                    <th>Thông tin liên hệ</th>
                    <th>Phân loại</th>
                    <th>Tin tuyển dụng</th>
                    <th>Giai đoạn hiện tại</th>
                    <th>Trạng thái</th>
                    <th>Nguồn</th>
                    <th>Sự kiện</th>
                    <th>Phụ trách</th>
                    <th>Đánh giá</th>
                    <th>CV</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $c):
                    [$ini, $col] = $avatar($c['full_name']);
                    $stg = $c['app_stage'] ?: $c['applied_stage']; ?>
                    <tr data-id="<?= $c['id'] ?>">
                        <td onclick="event.stopPropagation()"><input type="checkbox" class="rowChk" value="<?= $c['id'] ?>"
                                onclick="onCheck()"></td>
                        <td class="cd-go">
                            <div class="cd-name-cell">
                                <span class="cd-av" style="background:<?= $col ?>"><?= h($ini) ?></span>
                                <div style="min-width:0">
                                    <div class="cd-nm"><?= h($c['full_name']) ?></div>
                                    <div class="cd-sub"><?= h($c['current_position'] ?: 'Không có chức danh') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="cd-go">
                            <?php if ($c['email']): ?>
                                <div class="cd-contact"><?= $icMail ?><span><?= h($c['email']) ?></span></div><?php endif; ?>
                            <?php if ($c['phone']): ?>
                                <div class="cd-contact"><?= $icPhone ?><span><?= h($c['phone']) ?></span></div><?php endif; ?>
                            <?php if (!$c['email'] && !$c['phone']): ?><span class="cd-mut">-</span><?php endif; ?>
                        </td>
                        <td class="cd-go"><?= h($c['classification'] ?: 'Ứng viên') ?></td>
                        <td class="cd-go cd-job"><?= h($c['app_job'] ?: ($c['applied_job'] ?: '-')) ?></td>
                        <td><?= $stageCell($stg) ?></td>
                        <td><span class="cd-badge"
                                style="background:<?= ($stCol[$c['status']] ?? '#64748b') ?>1a;color:<?= $stCol[$c['status']] ?? '#64748b' ?>"><?= h($statuses[$c['status']] ?? $c['status']) ?></span><?php if (!empty($c['pool_list'])):
                                            foreach (explode(',', $c['pool_list']) as $pn): ?>
                                    <span class="cd-badge"
                                        style="background:#f3e8ff;color:#7c3aed"><?= h($pn) ?></span><?php endforeach; endif; ?></td>
                        <td><?= h($c['source_name'] ?: '-') ?></td>
                        <td><?= h($c['event_name'] ?: '-') ?></td>
                        <td><?= h($c['owner_name'] ?: '-') ?></td>
                        <td><?= (int) $c['rating'] ? '<span style="color:#f59e0b">' . str_repeat('★', (int) $c['rating']) . '</span>' : '<span class="cd-mut">-</span>' ?>
                        </td>
                        <td><?= $c['cv_path'] ? '<a href="' . h($c['cv_path']) . '" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="cd-cv">Xem CV</a>' : '<span class="cd-mut">-</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<script>
    document.querySelectorAll('.cd-go').forEach(td => td.addEventListener('click', function () {
        const tr = this.closest('tr'); if (tr) location.href = '/hrm/candidate?id=' + tr.dataset.id;
    }));
</script>

<style>
    .cd-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-bottom: 12px
    }

    .cd-in {
        padding: 8px 11px;
        border: 1px solid var(--bd);
        border-radius: 8px;
        font-size: 13px;
        background: #fff
    }

    .cd-bulk {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        background: #eef6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 12px;
        font-size: 13px
    }

    .cd-scroll {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 0 0 1px rgba(0, 0, 0, .04);
        overflow-x: auto;
        scrollbar-width: none
    }

    .cd-scroll::-webkit-scrollbar {
        display: none
    }

    .cd-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        color: #1d1d1f
    }

    .cd-table th {
        background: #f6f8f7;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .3px;
        color: #0e9f6e;
        text-transform: uppercase;
        padding: 12px 16px;
        border-bottom: 1px solid #eef0f2;
        white-space: nowrap
    }

    .cd-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
        white-space: nowrap
    }

    .cd-table .cd-go {
        cursor: pointer
    }

    .cd-table tbody tr:hover td {
        background: #f7faf9
    }

    .cd-name-cell {
        display: flex;
        align-items: center;
        gap: 11px
    }

    .cd-av {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 12px
    }

    .cd-nm {
        font-weight: 600;
        color: #0f172a;
        font-size: 13.5px
    }

    .cd-sub {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 2px
    }

    .cd-mut {
        color: #cbd5e1
    }

    .cd-job {
        font-weight: 600;
        color: #0e7490;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .cd-contact {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        color: #475569;
        margin: 1px 0
    }

    .cd-ic {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
        fill: none;
        stroke: #94a3b8;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round
    }

    .cd-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 980px;
        background: #eef6ff;
        color: #0071e3;
        margin: 1px
    }

    .cd-cv {
        color: #0e9f6e;
        font-weight: 600;
        text-decoration: none
    }

    .cd-cv:hover {
        text-decoration: underline
    }

    /* Giai đoạn hiện tại - thanh chấm tiến trình */
    .cd-stage {
        min-width: 150px
    }

    .cd-stage-lbl {
        font-size: 12.5px;
        color: #0f172a;
        margin-bottom: 6px
    }

    .cd-stage-num {
        color: #94a3b8
    }

    .cd-stage.rej .cd-stage-lbl {
        color: #dc2626;
        font-weight: 600
    }

    .cd-track {
        display: flex;
        align-items: center
    }

    .cd-track .dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #e2e8f0;
        flex-shrink: 0
    }

    .cd-track .dot.done {
        background: #16a34a
    }

    .cd-track .dot.cur {
        box-shadow: 0 0 0 3px rgba(22, 163, 74, .18)
    }

    .cd-track .dot.rej {
        background: #dc2626
    }

    .cd-track .line {
        height: 2px;
        flex: 1;
        min-width: 14px;
        background: #e2e8f0
    }

    .cd-track .line.done {
        background: #16a34a
    }
</style>

<!-- Modal thêm ứng viên -->
<div id="addModal" class="cd-modal">
    <div class="rc-card" style="width:520px;max-width:94vw">
        <h3 style="font-size:15px;margin-bottom:12px">Thêm ứng viên vào kho</h3>
        <form id="addForm" onsubmit="return false">
            <div class="rc-grid2">
                <div class="rc-field"><label>Họ tên *</label><input name="full_name" required></div>
                <div class="rc-field"><label>Email</label><input name="email" type="email"></div>
                <div class="rc-field"><label>Điện thoại</label><input name="phone"></div>
                <div class="rc-field"><label>Vị trí gần nhất</label><input name="current_position"></div>
                <div class="rc-field"><label>Nguồn</label><select name="source_id">
                        <option value="0">-</option>
                        <?php foreach ($opts['sources'] as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="rc-field"><label>Sự kiện</label><select name="event_id">
                        <option value="0">-</option>
                        <?php foreach ($opts['events'] as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= h($e['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div id="addErr" class="rc-muted" style="color:#dc2626;margin:6px 0"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="rc-btn ghost"
                    onclick="document.getElementById('addModal').style.display='none'">Hủy</button>
                <button type="button" class="rc-btn" id="addBtn" onclick="addCand(false)">Lưu</button>
            </div>
        </form>
    </div>
</div>

<style>
    .cd-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .4);
        z-index: 999;
        align-items: center;
        justify-content: center
    }
</style>
<script>
    function selectedIds() { return Array.from(document.querySelectorAll('.rowChk:checked')).map(c => c.value); }
    function onCheck() { const n = selectedIds().length; document.getElementById('bulkCount').textContent = n; document.getElementById('bulkBar').style.display = n ? 'flex' : 'none'; document.getElementById('mergeBtn').style.display = n === 2 ? 'inline-flex' : 'none'; }
    function mergeTwo() { const ids = selectedIds(); if (ids.length !== 2) { alert('Chọn đúng 2 hồ sơ để gộp'); return; } location.href = '/hrm/candidates/merge?a=' + ids[0] + '&b=' + ids[1]; }
    function linkPipeline() {
        if (!confirm('Gắn các ứng viên (có "Tin ứng tuyển" gốc) vào pipeline của tin tương ứng?')) return;
        const fd = new FormData(); fd.append('action', 'cand_link_pipeline');
        fetch('/hrm/api', { method: 'POST', body: fd }).then(r => r.json()).then(j => {
            if (j.ok) alert('Đã gắn ' + j.linked + ' ứng viên vào pipeline.' + (j.no_job ? (' ' + j.no_job + ' ứng viên không tìm thấy tin khớp tên.') : ''));
            else alert(j.error || 'Lỗi');
            if (j.ok) location.reload();
        }).catch(() => alert('Lỗi kết nối'));
    }
    function toggleAll(cb) { document.querySelectorAll('.rowChk').forEach(c => c.checked = cb.checked); onCheck(); }
    function bulk(op, value) {
        const ids = selectedIds(); if (!ids.length) { alert('Chưa chọn ứng viên'); return; }
        if ((op === 'tag' || op === 'status' || op === 'add_pool') && !value) { alert('Nhập/chọn giá trị'); return; }
        const fd = new FormData(); fd.append('action', 'cand_bulk'); fd.append('op', op); fd.append('value', value); fd.append('ids', ids.join(','));
        fetch('/hrm/api', { method: 'POST', body: fd }).then(r => r.json()).then(j => { j.ok ? location.reload() : alert(j.error || 'Lỗi'); });
    }
    function addCand(force) {
        const f = document.getElementById('addForm'); if (!f.full_name.value.trim()) { alert('Nhập họ tên'); return; }
        const fd = new FormData(f); fd.append('action', 'cand_create'); if (force) fd.append('force', '1');
        document.getElementById('addBtn').disabled = true; document.getElementById('addErr').textContent = '';
        fetch('/hrm/api', { method: 'POST', body: fd }).then(r => r.json()).then(j => {
            document.getElementById('addBtn').disabled = false;
            if (j.ok) { location.href = '/hrm/candidate?id=' + j.id; return; }
            if (j.dup_id) { document.getElementById('addErr').innerHTML = j.error + ' <a href="/hrm/candidate?id=' + j.dup_id + '">Mở hồ sơ</a> · <a href="#" onclick="addCand(true);return false;">Vẫn tạo</a>'; }
            else document.getElementById('addErr').textContent = j.error || 'Lỗi';
        }).catch(() => { document.getElementById('addBtn').disabled = false; document.getElementById('addErr').textContent = 'Lỗi kết nối'; });
    }
</script>
<?php
hrm_footer();
