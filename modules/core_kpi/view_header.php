<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Core &amp; Key KPI Management</title>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--c-blue:#2563EB;--c-blue-light:#EFF6FF;--c-green:#16A34A;--c-red:#DC2626;--c-amber:#D97706;--c-purple:#7C3AED;--c-gray:#6B7280;--radius:8px;}
.ck-wrap{display:flex;flex-direction:column;height:calc(100vh - 64px);overflow:hidden;background:#F8FAFC;}
.ck-header{display:flex;align-items:center;gap:10px;padding:10px 20px;background:#fff;border-bottom:1px solid #E5E7EB;flex-wrap:wrap;}
.ck-tabs{display:flex;gap:2px;background:#F1F5F9;border-radius:8px;padding:3px;}
.ck-tab{padding:6px 16px;border-radius:6px;font-size:13px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;transition:all .15s;}
.ck-tab:hover{color:#1D4ED8;background:#fff;}
.ck-tab.active{background:#fff;color:#1D4ED8;font-weight:700;box-shadow:0 1px 4px rgba(0,0,0,.12);}
.ck-body{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:14px;}
.panel{background:#fff;border:1px solid #E5E7EB;border-radius:var(--radius);display:flex;flex-direction:column;overflow:hidden;}
.panel-hd{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid #F3F4F6;background:#FAFAFA;flex-shrink:0;}
.panel-hd h3{font-size:14px;font-weight:700;color:#111827;margin:0;}
.panel-bd{padding:14px 16px;}
.btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:1px solid #D1D5DB;background:#fff;color:#374151;transition:all .15s;text-decoration:none;}
.btn:hover{background:#F9FAFB;}
.btn-primary{background:var(--c-blue);color:#fff;border-color:var(--c-blue);}
.btn-primary:hover{background:#1D4ED8;}
.btn-danger{background:#FEE2E2;color:#B91C1C;border-color:#FECACA;}
.btn-success{background:#DCFCE7;color:#15803D;border-color:#BBF7D0;}
.btn-sm{padding:3px 9px;font-size:12px;}
.badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;}
/* Table */
.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:13px;}
.tbl th{background:#F8FAFC;padding:8px 10px;text-align:left;font-weight:600;color:#374151;border-bottom:2px solid #E5E7EB;white-space:nowrap;position:sticky;top:0;z-index:10;box-shadow:0 2px 0 0 #E5E7EB;}
.tbl td{padding:7px 10px;border-bottom:1px solid #F3F4F6;color:#374151;vertical-align:middle;background:#fff;}
.tbl tr:hover td{background:#F8FAFF;}
.tbl tfoot td{background:#F8FAFC;font-weight:700;border-top:2px solid #E5E7EB;}
.tbl-wrap{overflow:auto;flex:1;max-height:calc(100vh - 220px);}
/* Cards grid */
.emp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}
.emp-card{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:14px;transition:box-shadow .15s;}
.emp-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.09);}
.emp-av{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;font-weight:700;flex-shrink:0;}
.emp-info{flex:1;min-width:0;}
.emp-name{font-size:15px;font-weight:700;color:#111827;margin-bottom:2px;}
.emp-pos{font-size:12px;color:#6B7280;}
.score-ring{width:52px;height:52px;position:relative;flex-shrink:0;}
.score-ring svg{transform:rotate(-90deg);}
.score-ring .score-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#111;}
/* Progress bar */
.prog-bar{height:6px;background:#E5E7EB;border-radius:3px;overflow:hidden;}
.prog-fill{height:100%;border-radius:3px;transition:width .4s;}
/* Month grid for data entry */
.month-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;}
.month-cell{background:#F8FAFC;border:1px solid #E5E7EB;border-radius:6px;padding:8px 10px;font-size:12px;}
.month-cell.has-data{background:#EFF6FF;border-color:#BFDBFE;}
.month-cell.has-score-good{background:#DCFCE7;border-color:#BBF7D0;}
.month-cell.has-score-bad{background:#FEE2E2;border-color:#FECACA;}
/* Modal */
.modal{display:none;position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal.show{display:flex;}
.mc{background:#fff;border-radius:12px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:24px;box-shadow:0 8px 40px rgba(0,0,0,.2);}
.mc h3{font-size:16px;font-weight:700;margin:0 0 16px;color:#111827;}
.fg{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;}
.fg label{font-size:11px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.03em;}
.fg input,.fg select,.fg textarea{padding:8px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px;font-family:inherit;color:#111;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--c-blue);outline:none;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.mf{display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid #F3F4F6;}
/* Stats bar */
.stat-mini{text-align:center;padding:10px 18px;border-right:1px solid #E5E7EB;}
.stat-mini:last-child{border-right:none;}
.stat-mini .val{font-size:22px;font-weight:800;color:var(--c-blue);}
.stat-mini .lbl{font-size:11px;color:#9CA3AF;margin-top:2px;}
/* Notif */
.notif{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:8px;}
.notif.ok{background:#DCFCE7;color:#15803D;border:1px solid #BBF7D0;}
.notif.err{background:#FEE2E2;color:#B91C1C;border:1px solid #FECACA;}
/* Cycle status */
.cycle-pill{display:inline-block;padding:2px 10px;border-radius:12px;font-size:10px;font-weight:700;}
/* Filter bar */
.filter-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.filter-bar select,.filter-bar input{padding:6px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px;background:#fff;}
/* Inline Edit Styles */
.inline-edit{border:1px solid transparent;background:transparent;border-radius:4px;padding:4px;transition:all .2s;font-family:inherit;color:inherit;width:100%;}
.inline-edit:hover{border-color:#E5E7EB;background:#F9FAFB;}
.inline-edit:focus{border-color:var(--c-blue);background:#fff;outline:none;box-shadow:0 0 0 2px rgba(37,99,235,0.1);}
.inline-edit::-webkit-inner-spin-button, .inline-edit::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.lock-toggle{opacity:0;cursor:pointer;transition:opacity .2s, transform .2s;font-size:14px;padding:4px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;}
.lock-toggle:hover{background:#F1F5F9;transform:scale(1.2);}
.tbl tr:hover .lock-toggle{opacity:1;}
.is-locked .inline-edit:not(.admin-edit){background:#F8FAFC!important;color:#94A3B8!important;cursor:not-allowed;pointer-events:none;}
.is-locked-badge{background:#F1F5F9;color:#64748B;font-size:9px;padding:1px 4px;border-radius:4px;margin-left:4px;display:inline-flex;align-items:center;gap:2px;}
.group-header td{cursor:pointer;user-select:none;transition:background 0.2s;background:#F1F5F9!important;padding:10px 16px!important;}
.group-header:hover td{background:#E2E8F0!important;}
.group-header .chevron{transition:transform 0.3s;font-size:12px;color:#94A3B8;}

.group-header.collapsed .chevron{transform:rotate(-90deg);}
.group-item{transition:all 0.3s ease-in-out;}
.group-item.hidden{display:none;}
  .group-header { cursor: grab; }
  .group-header:active { cursor: grabbing; }
  .sortable-ghost { opacity: 0.4; background: #E0F2FE !important; }
  .sortable-chosen { background: #F1F5F9 !important; }
  .mtype-separator td { background: #1E293B !important; color: #fff !important; padding: 12px 16px !important; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1.5px; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
async function autoSaveKpi(input) {
  const row = input.closest('tr');
  const form = input.closest('form');
  if(!form) return;
  
  const formData = new FormData(form);
  formData.set('action', 'save_result_ajax');

  // Visual feedback
  const originalColor = input.style.color;
  input.style.color = '#9CA3AF';

  try {
    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    const text = await res.text();
    let data; 
    try { data = JSON.parse(text); } catch(e) { console.error('Server response:', text); throw e; }
    
    if (data.status === 'ok') {
      input.style.color = '#16A34A';
      setTimeout(() => { input.style.color = originalColor; window.focus(); }, 800);
      
      // Update achievement cell
      const achCell = row.querySelector('.ach-val');
      if (achCell) {
        achCell.textContent = data.achievement + '%';
        const pct = parseFloat(data.achievement);
        achCell.style.color = pct >= 100 ? '#16A34A' : (pct >= 60 ? '#D97706' : '#DC2626');
      }
    } else {
      input.style.color = '#DC2626';
      console.error(data.message);
    }
  } catch (err) {
    console.error("Save error:", err);
    input.style.color = '#EF4444';
  }
}

async function toggleRecordLock(btn, aid, year, month, currentLock) {
  const nextLock = currentLock ? 0 : 1;
  const formData = new FormData();
  formData.append('action', 'toggle_lock');
  formData.append('assignment_id', aid);
  formData.append('year', year);
  formData.append('month', month);
  formData.append('lock', nextLock);

  btn.style.opacity = '0.5';
  btn.style.pointerEvents = 'none';
  try {
    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    const data = await res.json();
    if (data.status === 'ok') {
      btn.innerHTML = data.locked ? '🔒' : '🔓';
      btn.setAttribute('onclick', `toggleRecordLock(this, ${aid}, ${year}, ${month}, ${data.locked})`);
      const row = btn.closest('tr');
      if(data.locked) row.classList.add('is-locked'); else row.classList.remove('is-locked');
    } else {
        alert("Lỗi: " + data.message);
    }
  } catch (e) { console.error(e); }
  btn.style.opacity = '1';
  btn.style.pointerEvents = 'auto';
}

function toggleGroup(header) {
  const gid = header.getAttribute('data-group-id');
  const items = document.querySelectorAll(`.group-item[data-group="${gid}"]`);
  const chevron = header.querySelector('.chevron');
  
  items.forEach(item => {
    item.classList.toggle('hidden');
  });
  
  if (chevron) {
    chevron.innerText = items[0].classList.contains('hidden') ? '▶' : '▼';
  }
}

function saveGroupOrder(sortable) {
  const order = sortable.toArray();
  const formData = new FormData();
  formData.append('action', 'save_group_order');
  formData.append('order', JSON.stringify(order));
  
  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log('Order saved');
    }
  });
}

function applyKpiFilters(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return;

  const searchInput = document.querySelector(`.kpi-filter-input[data-target="${tableId}"]`);
  const typeSelect = document.querySelector(`.kpi-filter-type[data-target="${tableId}"]`);
  const roleSelect = document.querySelector(`.kpi-filter-role[data-target="${tableId}"]`);

  const query = (searchInput?.value || "").toLowerCase();
  const typeFilter = typeSelect?.value || "";
  const roleFilter = roleSelect?.value || "";

  const sections = table.querySelectorAll('tbody.group-section');
  
  sections.forEach(section => {
    const items = section.querySelectorAll('.group-item');
    let sectionVisibleCount = 0;

    items.forEach(item => {
      const text = item.innerText.toLowerCase();
      const rowType = (item.getAttribute('data-type') || "").trim();
      const rowRole = (item.getAttribute('data-role') || "").trim();
      
      // Fallback: search for role text in the row if data-role is missing
      const roleText = item.querySelector('small, div[style*="color:#6B7280"]')?.innerText || "";

      const matchesSearch = text.includes(query);
      const matchesType = !typeFilter || rowType === typeFilter.trim() || (item.innerText.includes(typeFilter));
      const matchesRole = !roleFilter || rowRole === roleFilter.trim() || (roleText.includes(roleFilter));

      if (matchesSearch && matchesType && matchesRole) {
        item.classList.remove('hidden');
        sectionVisibleCount++;
      } else {
        item.classList.add('hidden');
      }
    });

    const header = section.querySelector('.group-header');
    if (sectionVisibleCount > 0) {
      header?.classList.remove('hidden');
      section.style.display = '';
      console.log(`Section shown: ${section.getAttribute('data-id')} (matches: ${sectionVisibleCount})`);
    } else {
      header?.classList.add('hidden');
      section.style.display = 'none';
    }
  });

  // Handle mtype-separator visibility
  const separators = table.querySelectorAll('.mtype-separator');
  separators.forEach(sep => {
    const sepTbody = sep.closest('tbody');
    let hasVisibleContent = false;
    let next = sepTbody.nextElementSibling;
    
    while (next && !next.querySelector('.mtype-separator')) {
      if (next.style.display !== 'none' && next.classList.contains('group-section')) {
        hasVisibleContent = true;
        break;
      }
      next = next.nextElementSibling;
    }
    
    sepTbody.style.display = hasVisibleContent ? '' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  // Existing Sortable init
  const table = document.querySelector('.tbl-sortable');
  if (table && "<?=($_SESSION['role'] === 'admin') ? '1' : '0'?>" === '1') {
    new Sortable(table, {
      draggable: "tbody.group-section",
      handle: ".group-header",
      animation: 150,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      onEnd: function(evt) {
        saveGroupOrder(this);
      }
    });
  }

  // Filter Event Listeners
  document.querySelectorAll('.kpi-filter-input, .kpi-filter-type, .kpi-filter-role').forEach(el => {
    el.addEventListener('input', () => {
      const target = el.getAttribute('data-target');
      applyKpiFilters(target);
    });
  });
});
</script>
</head>
<body>
<div class="dashboard-container">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main-content" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
<?php $page_title='Core & Key KPI'; $page_subtitle='Quản lý đánh giá hiệu suất nhân viên Core/Key'; include __DIR__ . '/../includes/topbar.php'; ?>

<div class="ck-wrap">
<!-- Header / Tab Bar -->
<div class="ck-header">
  <div class="ck-tabs">
    <?php 
    $tabs=[['dashboard','📊 Tổng quan'],['data','📝 Nhập dữ liệu'],['employees','👤 Nhân viên'],['cycles','📅 Chu kỳ'],['kpidefs','⚙️ Định nghĩa KPI'],['reviews','🏆 Tổng kết quý'],['yearly','📅 Tổng kết năm']]; 
    if (!$is_admin) {
        $tabs = array_values(array_filter($tabs, fn($t) => in_array($t[0], ['dashboard','data','reviews','yearly'])));
    }
    ?>
    <?php foreach($tabs as [$tv,$tl]): ?>
    <a href="?tab=<?=$tv?>&year=<?=$sel_year?>&cycle=<?=$sel_cycle?>&emp=<?=$sel_user?>" class="ck-tab <?=$tab===$tv?'active':''?>"><?=$tl?></a>
    <?php endforeach; ?>
  </div>
  <!-- Cycle & Year selector -->
  <form method="GET" style="display:flex;gap:6px;align-items:center;margin-left:auto;">
    <input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>">
    <select name="cycle" onchange="this.form.submit()" style="padding:5px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px;">
      <option value="0">-- Chọn chu kỳ --</option>
      <?php foreach($cycles as $c): $st=$status_badge[$c['status']]??['#eee','#333','?']; ?>
      <option value="<?=$c['id']?>" <?=$sel_cycle==$c['id']?'selected':''?>>
        <?=htmlspecialchars($c['name'])?> [<?=$st[2]?>]
      </option>
      <?php endforeach; ?>
    </select>
    
    <input type="hidden" name="emp" value="<?=$sel_user?>">
    <input type="hidden" name="year" value="<?=$sel_year?>">
  </form>
</div>

<div class="ck-body">
<?php if($msg_ok): ?><div class="notif ok">✅ <?=htmlspecialchars($msg_ok)?></div><?php endif; ?>
<?php if($msg_err): ?><div class="notif err">❌ <?=htmlspecialchars($msg_err)?></div><?php endif; ?>
