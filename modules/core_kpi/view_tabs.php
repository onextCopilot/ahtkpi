<?php
// Roles available to all tab filters
$roles = array_unique(array_filter(array_column($members, 'job_title')));
sort($roles);
?>
<?php /* ═══════════════════════════════ TAB: DASHBOARD ═══════════════════════════════ */ if($tab==='dashboard'): ?>

<?php
$tot_emp = count($members);
$scores_all = array_filter(array_column($dash_stats,'score'), fn($s)=>$s!==null);
$avg_score = count($scores_all) ? round(array_sum($scores_all)/count($scores_all),1) : null;
$reviewed_count = count(array_filter($dash_stats, fn($d)=>!empty($d['review'])));
?>

<!-- Stats strip -->
<div class="panel" style="overflow:hidden;">
  <div style="display:flex;border-bottom:1px solid #E5E7EB;flex-wrap:wrap;">
    <div class="stat-mini"><div class="val"><?=$tot_emp?></div><div class="lbl">Core/Key Members</div></div>
    <div class="stat-mini"><div class="val"><?=count($assignments)?></div><div class="lbl">KPI được gán</div></div>
    <div class="stat-mini"><div class="val" style="color:<?=$avg_score>=80?'#16A34A':($avg_score>=60?'#D97706':'#DC2626')?>"><?=$avg_score!==null?$avg_score.'%':'N/A'?></div><div class="lbl">Điểm TB</div></div>
    <div class="stat-mini"><div class="val"><?=$reviewed_count?>/<?=$tot_emp?></div><div class="lbl">Đã đánh giá</div></div>
    <?php if($cur_cycle): $sb=$status_badge[$cur_cycle['status']]??['#eee','#333','?']; ?>
    <div class="stat-mini"><div class="val" style="font-size:13px;color:<?=$sb[1]?>;padding-top:4px;"><?=$sb[2]?></div><div class="lbl"><?=htmlspecialchars($cur_cycle['name'])?></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- Filter Bar -->
<div class="panel" style="padding:12px; margin-bottom:16px;">
  <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <div style="position:relative; flex:1; min-width:200px;">
      <input type="text" class="kpi-filter-input" data-target="dashboard-table" placeholder="Tìm kiếm tên nhân viên..." style="width:100%; padding:8px 12px 8px 32px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px;">
      <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9CA3AF;">🔍</span>
    </div>
    <select class="kpi-filter-type" data-target="dashboard-table" style="padding:8px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; background:#fff;">
      <option value="">Tất cả loại member</option>
      <option value="Core">Core Member</option>
      <option value="Key">Key Member</option>
    </select>
    <select class="kpi-filter-role" data-target="dashboard-table" style="padding:8px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; background:#fff;">
      <option value="">Tất cả vai trò</option>
      <?php foreach($roles as $r) echo "<option value='".htmlspecialchars($r)."'>".htmlspecialchars($r)."</option>"; ?>
    </select>
  </div>
</div>

<!-- Member scoreCards/List -->
<div class="panel">
  <div class="tbl-wrap">
  <table class="tbl tbl-sortable" id="dashboard-table">
    <thead>
      <tr>
        <th style="padding-left:16px; width:40px; text-align:center;">#</th>
        <th>Thành viên</th>
        <th style="text-align:center;">KPI</th>
        <th style="text-align:center;">Trọng số</th>
        <th style="text-align:center;">Điểm TB</th>
        <th>Xếp loại</th>
        <th>KPI tiêu biểu</th>
        <th style="text-align:center;padding-right:16px;">Thao tác</th>
      </tr>
    </thead>
    <?php 
    $cur_mtype = '';
    $cur_group = '';
    $grp_idx = 0;
    foreach($members as $mem):
      $muid=$mem['id']; $ds=$dash_stats[$muid]??['count'=>0,'score'=>null,'review'=>null,'total_weight'=>0];
      $score=$ds['score']; $review=$ds['review'];
      $ini=mb_strtoupper(mb_substr($mem['full_name']??'?',0,1,'UTF-8'),'UTF-8');
      $sc_color=$score===null?'#9CA3AF':($score>=80?'#16A34A':($score>=60?'#D97706':'#DC2626'));
      $rating_label=$review['rating']??null;

      if ($cur_mtype !== $mem['member_type']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_mtype = $mem['member_type'];
        $cur_group = '';
        echo "<tbody><tr class='mtype-separator'><td colspan='8'>".htmlspecialchars($cur_mtype)." Members</td></tr></tbody>";
      }

      if ($cur_group !== $mem['job_title']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_group = $mem['job_title'];
        $gid = md5($cur_group ?: 'N/A');
        $grp_idx = 0;
        echo "<tbody class='group-section' data-id='$gid'>";
        echo "<tr class='group-header' onclick='toggleGroup(this)' data-group-id='$gid'><td colspan='8' style='border-bottom:1px solid #E2E8F0;'><div style='display:flex; align-items:center; justify-content:space-between;'><strong style='color:#1E293B; font-size:13px; text-transform:uppercase; letter-spacing:1px;'>".htmlspecialchars($cur_group ?: 'N/A')."</strong><span class='chevron'>▼</span></div></td></tr>";
      }
      $grp_idx++;
    ?>
    <tr class="group-item" data-group="<?=md5($cur_group ?: 'N/A')?>" data-role="<?=htmlspecialchars($mem['job_title']??'')?>" data-type="<?=htmlspecialchars($mem['member_type']??'')?>">
      <td style="text-align:center; color:#9CA3AF; padding-left:16px;"><?=$grp_idx?></td>
      <td style="padding-left:16px;">
        <div style="display:flex;gap:10px;align-items:center;">
          <?php if($mem['avatar']): ?>
          <img src="<?=htmlspecialchars($mem['avatar'])?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid #E5E7EB;">
          <?php else: ?>
          <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;"><?=$ini?></div>
          <?php endif; ?>
          <div>
            <div style="font-weight:700;color:#111827;font-size:13px;">
              <?=htmlspecialchars($mem['full_name'])?>
              <span class="badge" style="font-size:9px;padding:1px 5px;background:<?=$mem['member_type']==='Core'?'#FEF3C7':'#E0F2FE'?>;color:<?=$mem['member_type']==='Core'?'#92400E':'#0369A1'?>;margin-left:4px;vertical-align:middle;"><?=$mem['member_type']?> Member</span>
            </div>
            <div style="font-size:11px;color:#6B7280;"><?=htmlspecialchars($mem['job_title']??'')?></div>
          </div>
        </div>
      </td>
      <td style="text-align:center;"><span class="badge" style="background:#EDE9FE;color:#5B21B6;"><?=$ds['count']?></span></td>
      <td style="text-align:center;color:#6B7280;"><?=$ds['total_weight']??0?>%</td>
      <td style="text-align:center;">
        <?php if($score!==null): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;font-weight:800;color:<?=$sc_color?>;font-size:14px;">
           <div style="width:8px;height:8px;border-radius:50%;background:<?=$sc_color?>"></div>
           <?=$score?>%
        </div>
        <?php else: ?><span style="color:#9CA3AF;">—</span><?php endif; ?>
      </td>
      <td>
        <?php if($rating_label): ?>
        <span class="badge" style="background:<?=$rating_color[$rating_label]??'#ccc'?>22;color:<?=$rating_color[$rating_label]??'#333'?>;border:1px solid <?=$rating_color[$rating_label]??'#ccc'?>44;">⭐ <?=$rating_label?></span>
        <?php else: ?><span style="color:#9CA3AF;font-size:12px;">Chưa xếp loại</span><?php endif; ?>
      </td>
      <td style="max-width:260px;">
        <?php $mem_assigns=array_filter($assignments,fn($a)=>$a['user_id']==$muid);
        foreach(array_slice($mem_assigns,0,2) as $a):
          $latest_res=isset($results_map[$a['id']])?end($results_map[$a['id']]):null;
          $ach=$latest_res['achievement_pct']??null;
          $ach_w=min(100,max(0,(float)($ach??0)));
          $bc=$ach===null?'#D1D5DB':($ach>=100?'#16A34A':($ach>=60?'#F59E0B':'#EF4444'));
        ?>
        <div style="margin-bottom:6px;">
          <div style="display:flex;justify-content:space-between;font-size:10px;color:#6B7280;margin-bottom:2px;">
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;" title="<?=htmlspecialchars($a['kpi_name'])?>"><?=htmlspecialchars($a['kpi_name'])?></span>
            <span style="font-weight:600;color:<?=$bc?>;"><?=$ach!==null?round($ach).'%':'—'?></span>
          </div>
          <div class="prog-bar" style="height:4px;"><div class="prog-fill" style="width:<?=$ach_w?>%;background:<?=$bc?>;"></div></div>
        </div>
        <?php endforeach; ?>
      </td>
      <td style="text-align:center;padding-right:16px;">
        <div style="display:flex;gap:4px;justify-content:center;">
          <a href="?tab=data&cycle=<?=$sel_cycle?>&emp=<?=$muid?>" class="btn btn-sm btn-primary" title="Nhập liệu">📝</a>
          <?php if($is_admin || $muid===$uid): ?>
          <a href="?tab=reviews&cycle=<?=$sel_cycle?>&emp=<?=$muid?>" class="btn btn-sm btn-success" title="Đánh giá/Phản hồi">🏆</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($members)): ?>
    <tbody>
    <tr><td colspan="8" style="text-align:center;padding:60px;">
      <div style="font-size:48px;margin-bottom:12px;">👥</div>
      <h3 style="color:#374151;margin-bottom:8px;">Chưa có Core/Key Member nào</h3>
      <?php if($is_admin): ?><a href="?tab=employees&cycle=<?=$sel_cycle?>" class="btn btn-primary">+ Thêm thành viên</a><?php endif; ?>
    </td></tr>
    </tbody>
    <?php else: echo "</tbody>"; endif; ?>
  </table>
  </div>
</div>

<?php /* ═══════════════════════════════ TAB: DATA ENTRY ═══════════════════════════════ */ elseif($tab==='data'): ?>
<div class="panel">
  <div class="panel-hd">
    <h3>📝 Nhập dữ liệu KPI&nbsp;&nbsp;<small style="font-weight:500;color:#6B7280;"><?=$cur_cycle?'— '.htmlspecialchars($cur_cycle['name']):''?></small></h3>
    <?php if($is_admin): ?><button class="btn btn-primary btn-sm" onclick="document.getElementById('m-assign').classList.add('show')">+ Gán KPI</button><?php endif; ?>
  </div>
  <?php if(empty($assignments)): ?>
  <div style="padding:40px;text-align:center;color:#9CA3AF;">
    <div style="font-size:36px;margin-bottom:10px;">📋</div>
    Chưa có KPI nào được gán. <?php if($is_admin): ?><a href="#" onclick="document.getElementById('m-assign').classList.add('show');return false;">Gán KPI ngay</a><?php endif; ?>
  </div>
  <?php else: ?>
  <!-- Month selector -->
  <div style="padding:8px 16px;border-bottom:1px solid #F3F4F6;display:flex;gap:5px;flex-wrap:wrap;align-items:center;background:#FAFAFA;">
    <span style="font-size:12px;color:#6B7280;font-weight:600;margin-right:4px;">Tháng:</span>
    <?php for($m=1;$m<=12;$m++): ?>
    <a href="?tab=data&cycle=<?=$sel_cycle?>&emp=<?=$sel_user?>&month=<?=$m?>" class="btn btn-sm <?=$sel_month==$m?'btn-primary':''?>"><?=$months_vi[$m]?></a>
    <?php endfor; ?>
  </div>
  <div class="tbl-wrap">
  <table class="tbl">
    <thead>
      <tr>
        <th style="min-width:140px;">Nhân viên</th>
        <th style="width:25px;text-align:center;"></th>
        <th style="min-width:160px;">KPI</th>
        <th style="width:80px;">Nhóm</th>
        <th style="text-align:right;width:100px;">Mục tiêu</th>
        <th style="text-align:center;width:60px;">Đơn vị</th>
        <th style="text-align:center;width:80px;">Trọng số</th>
        <th style="text-align:right;width:110px;">Thực tế (<?=$months_vi[$sel_month]?>)</th>
        <th style="text-align:center;width:70px;">% Đạt</th>
        <th style="text-align:center;width:80px;">Điểm</th>
        <th>Ghi chú</th>
        <?php if($is_admin): ?><th style="width:40px;"></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $cur_name=''; foreach($assignments as $a):
      $aid=$a['id'];
      $cy_year=$cur_cycle['year']??date('Y');
      $res=$results_map[$aid][$sel_month]??null;
      $ach=$res['achievement_pct']??null;
      $ach_c=$ach===null?'#9CA3AF':($ach>=100?'#16A34A':($ach>=60?'#F59E0B':'#EF4444'));
      if($cur_name!==$a['full_name']): $cur_name=$a['full_name'];
    ?>
    <tr><td colspan="11" style="background:#EEF2FF!important;font-weight:700;color:#3730A3;padding:5px 10px;font-size:12px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span>👤 <?=htmlspecialchars($a['full_name'])?><?=$a['job_title']?' — '.htmlspecialchars($a['job_title']):''?></span>
        <?php $mt=$a['member_type']??'Key'; $mt_c=($mt==='Core')?'#F59E0B':'#2563EB'; ?>
        <span class="badge" style="background:<?=$mt_c?>;color:#fff;font-size:9px;text-transform:uppercase;"><?=$mt?></span>
      </div>
    </td></tr>
    <?php endif; ?>
    <tr class="<?=($res['is_locked']??0)?'is-locked':''?>">
      <td></td>
      <td style="text-align:center;">
        <?php if($is_admin): ?>
        <span class="lock-toggle" onclick="toggleRecordLock(this, <?=$aid?>, <?=$cy_year?>, <?=$sel_month?>, <?=($res['is_locked']??0)?>)" title="Bấm để Khoá/Mở khoá">
          <?=($res['is_locked']??0)?'🔒':'🔓'?>
        </span>
        <?php elseif($res['is_locked']??0): ?>
        <span title="Dòng này đã bị khoá bởi Admin">🔒</span>
        <?php endif; ?>
      </td>
      <td><span title="<?=htmlspecialchars($a['def_desc']??'')?>" style="cursor:help;"><?=htmlspecialchars($a['kpi_name'])?></span></td>
      <td><span class="badge" style="background:#EDE9FE;color:#5B21B6;font-size:10px;"><?=htmlspecialchars($a['category'])?></span></td>
      <td colspan="8" style="padding:0;">
        <form method="POST" style="display:table;width:100%;table-layout:fixed;">
        <input type="hidden" name="action" value="save_result">
        <input type="hidden" name="assignment_id" value="<?=$aid?>">
        <input type="hidden" name="year" value="<?=$cy_year?>">
        <input type="hidden" name="month" value="<?=$sel_month?>">
        <?php 
          $row_locked = ($res['is_locked']??0) && !$is_admin; 
          $ro = $row_locked ? 'readonly' : '';
          $ev = $row_locked ? '' : 'onchange="autoSaveKpi(this)"';
          $cls = $is_admin ? 'admin-edit' : '';
        ?>
        <div style="display:table-row;">
          <div style="display:table-cell;padding:8px;text-align:right;width:100px;border-right:1px solid #F3F4F6;">
            <input type="number" step="0.01" name="target_value" value="<?=htmlspecialchars($a['target_value']??'')?>" class="inline-edit <?=$cls?>" style="text-align:right;font-weight:700;width:100%;color:#2563EB;" <?=$is_admin?'':'readonly'?> <?=$is_admin?'onchange="autoSaveKpi(this)"':''?>>
          </div>
          <div style="display:table-cell;padding:8px;text-align:center;width:60px;border-right:1px solid #F3F4F6;">
            <input type="text" name="unit" value="<?=htmlspecialchars($a['unit']??'')?>" class="inline-edit <?=$cls?>" style="width:100%;font-size:11px;color:#6B7280;text-align:center;" <?=$is_admin?'':'readonly'?> <?=$is_admin?'onchange="autoSaveKpi(this)"':''?> placeholder="đv">
          </div>
          <div style="display:table-cell;padding:8px;text-align:center;width:80px;border-right:1px solid #F3F4F6;">
            <input type="number" step="0.5" name="weight" value="<?=htmlspecialchars($a['weight']??'')?>" class="inline-edit <?=$cls?>" style="text-align:center;color:#6B7280;width:100%;" <?=$is_admin?'':'readonly'?> <?=$is_admin?'onchange="autoSaveKpi(this)"':''?>>
          </div>
          <div style="display:table-cell;padding:8px;width:110px;border-right:1px solid #F3F4F6;">
            <input type="number" step="0.1" name="actual_value" value="<?=htmlspecialchars($res['actual_value']??'')?>" class="inline-edit <?=$cls?>" style="text-align:right;font-weight:700;background:#F0FDF4;width:100%;color:#16A34A;" <?=$ev?> <?=$ro?> placeholder="0">
          </div>
          <div style="display:table-cell;padding:8px;text-align:center;width:70px;font-weight:700;color:<?=$ach_c?>;border-right:1px solid #F3F4F6;" class="ach-val"><?=$ach!==null?round($ach,1).'%':'—'?></div>
          <div style="display:table-cell;padding:8px;width:80px;border-right:1px solid #F3F4F6;">
            <input type="number" step="0.1" min="0" max="100" name="score" value="<?=htmlspecialchars($res['score']??'')?>" class="inline-edit <?=$cls?>" style="text-align:center;background:#FEFCE8;width:100%;font-weight:700;" <?=$ev?> <?=$ro?> placeholder="—">
          </div>
          <div style="display:table-cell;padding:8px;min-width:150px;">
            <input type="text" name="note" value="<?=htmlspecialchars($res['note']??'')?>" class="inline-edit <?=$cls?>" style="width:100%;color:#64748b;" <?=$ev?> <?=$ro?> placeholder="...">
          </div>
        </div>
        </form>
      </td>
      <?php if($is_admin): ?>
      <td style="text-align:center;width:40px;">
        <form method="POST" onsubmit="return confirm('Xoá KPI này?')"><input type="hidden" name="action" value="del_assign"><input type="hidden" name="id" value="<?=$aid?>"><button type="submit" class="btn btn-sm btn-danger" style="padding:2px 5px;font-size:10px;">×</button></form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php /* ═══════════════════════════════ TAB: EMPLOYEES ═══════════════════════════════ */ elseif($tab==='employees'): ?>
<div class="panel">
  <div class="panel-hd">
    <h3>👥 Core/Key Members</h3>
    <?php if($is_admin && !empty($non_members)): ?>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('m-add-member').classList.add('show')">+ Thêm từ danh sách user</button>
    <?php endif; ?>
  </div>
  
  <div style="padding:12px 20px; border-bottom:1px solid #F3F4F6;">
    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
      <div style="position:relative; flex:1; min-width:200px;">
        <input type="text" class="kpi-filter-input" data-target="employees-table" placeholder="Tìm kiếm tên hoặc email..." style="width:100%; padding:8px 12px 8px 32px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px;">
        <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9CA3AF;">🔍</span>
      </div>
      <select class="kpi-filter-type" data-target="employees-table" style="padding:8px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; background:#fff;">
        <option value="">Tất cả loại member</option>
        <option value="Core">Core Member</option>
        <option value="Key">Key Member</option>
      </select>
    </div>
  </div>

  <?php if(empty($members) && empty($non_members)): ?>
  <div style="padding:30px;text-align:center;color:#9CA3AF;">Không có user nào trong hệ thống.</div>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="tbl tbl-sortable" id="employees-table">
    <thead><tr><th style="width:40px; text-align:center;">#</th><th>Họ tên</th><th>Loại Member</th><th>Chức danh</th><th>Phòng ban</th><th>Email</th><th>Trạng thái</th><?php if($is_admin): ?><th>Xoá khỏi nhóm</th><?php endif; ?></tr></thead>
    <?php 
    $cur_mtype = '';
    $cur_group = '';
    $grp_idx = 0;
    foreach($members as $m): 
      if ($cur_mtype !== $m['member_type']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_mtype = $m['member_type'];
        $cur_group = '';
        echo "<tbody><tr class='mtype-separator'><td colspan='".($is_admin?8:7)."'>".htmlspecialchars($cur_mtype)." Members</td></tr></tbody>";
      }

      if ($cur_group !== $m['job_title']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_group = $m['job_title'];
        $gid = md5($cur_group ?: 'N/A');
        $grp_idx = 0;
        echo "<tbody class='group-section' data-id='$gid'>";
        echo "<tr class='group-header' onclick='toggleGroup(this)' data-group-id='$gid'><td colspan='".($is_admin?8:7)."' style='border-bottom:1px solid #E2E8F0;'><div style='display:flex; align-items:center; justify-content:space-between;'><strong style='color:#1E293B; font-size:13px; text-transform:uppercase; letter-spacing:1px;'>".htmlspecialchars($cur_group ?: 'N/A')."</strong><span class='chevron'>▼</span></div></td></tr>";
      }
      $grp_idx++;
    ?>
    <tr class="group-item" data-group="<?=md5($cur_group ?: 'N/A')?>" data-role="<?=htmlspecialchars($m['job_title']??'')?>" data-type="<?=htmlspecialchars($m['member_type']??'')?>">
      <td style="color:#9CA3AF;"><?=$grp_idx?></td>

      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <?php if($m['avatar']): ?><img src="<?=htmlspecialchars($m['avatar'])?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
          <?php else: $in=mb_strtoupper(mb_substr($m['full_name']??'?',0,1,'UTF-8'),'UTF-8'); ?>
          <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;"><?=$in?></div>
          <?php endif; ?>
          <strong><?=htmlspecialchars($m['full_name'])?></strong>
        </div>
      </td>
      <td><span class="badge" style="background:<?=$m['member_type']==='Core'?'#FEF3C7':'#E0F2FE'?>;color:<?=$m['member_type']==='Core'?'#92400E':'#0369A1'?>;"><?=$m['member_type']?></span></td>
      <td><?=htmlspecialchars($m['job_title']??'—')?></td>
      <td><?=htmlspecialchars($m['dept_name']??'—')?></td>
      <td style="color:#2563EB;"><a href="mailto:<?=htmlspecialchars($m['email']??'')?>"><?=htmlspecialchars($m['email']??'—')?></a></td>
      <td><span class="badge" style="background:<?=$m['user_status']==='active'?'#DCFCE7':'#FEE2E2'?>;color:<?=$m['user_status']==='active'?'#15803D':'#B91C1C'?>;"><?=$m['user_status']??'—'?></span></td>
      <?php if($is_admin): ?>
      <td>
        <form method="POST" onsubmit="return confirm('Xoá <?=htmlspecialchars($m["full_name"])?> khỏi nhóm Core/Key?')"><input type="hidden" name="action" value="del_member"><input type="hidden" name="member_id" value="<?=$m['member_id']?>"><button type="submit" class="btn btn-danger btn-sm">✕ Xoá khỏi nhóm</button></form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; if($cur_group !== '') echo "</tbody>"; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php /* ═══════════════════════════════ TAB: CYCLES ═══════════════════════════════ */ elseif($tab==='cycles'): ?>
<div class="panel">
  <div class="panel-hd">
    <div style="display:flex;align-items:center;gap:15px;">
      <h3>📅 Chu kỳ đánh giá</h3>
      <form method="GET" style="margin:0;">
        <input type="hidden" name="tab" value="cycles">
        <select name="year" onchange="this.form.submit()" style="padding:3px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:12px;background:#fff;">
          <?php for($y=date('Y')+1;$y>=2021;$y--): ?>
          <option value="<?=$y?>" <?=$sel_year==$y?'selected':''?>>Năm <?=$y?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>
    <?php if($is_admin): ?><button class="btn btn-primary btn-sm" onclick="document.getElementById('m-cycle').classList.add('show')">+ Thêm chu kỳ</button><?php endif; ?>
  </div>
  <div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Tên</th><th>Năm</th><th>Quý</th><th>Từ ngày</th><th>Đến ngày</th><th>Trạng thái</th><?php if($is_admin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach($cycles as $c): 
      if($c['year'] != $sel_year) continue;
      $sb=$status_badge[$c['status']]??['#eee','#333','?']; 
    ?>
    <tr>
      <td><strong><?=htmlspecialchars($c['name'])?></strong></td>
      <td><?=$c['year']?></td><td><?=$c['quarter']?'Q'.$c['quarter']:'Cả năm'?></td>
      <td><?=$c['start_date']?date('d/m/Y',strtotime($c['start_date'])):'—'?></td>
      <td><?=$c['end_date']?date('d/m/Y',strtotime($c['end_date'])):'—'?></td>
      <td><span class="badge" style="background:<?=$sb[0]?>;color:<?=$sb[1]?>;"><?=$sb[2]?></span></td>
      <?php if($is_admin): ?><td><button class="btn btn-sm" onclick="openCycleModal(<?=htmlspecialchars(json_encode($c),ENT_QUOTES)?>)">✏️</button></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php /* ═══════════════════════════════ TAB: KPI DEFINITIONS ═══════════════════════════════ */ elseif($tab==='kpidefs'): ?>
<div class="panel">
  <div class="panel-hd">
    <h3>⚙️ Danh mục KPI</h3>
    <?php if($is_admin): ?><button class="btn btn-primary btn-sm" onclick="openDefModal()">+ Thêm KPI</button><?php endif; ?>
  </div>
  <div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Nhóm</th><th>Tên KPI</th><th>Mô tả</th><th>Đơn vị</th><th>Loại tính</th><?php if($is_admin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php $ad=$conn->query("SELECT * FROM core_kpi_definitions ORDER BY is_active DESC,category,sort_order,kpi_name");
    if($ad) while($row=$ad->fetch_assoc()): ?>
    <tr style="<?=!$row['is_active']?'opacity:0.35':''?>">
      <td><span class="badge" style="background:#EDE9FE;color:#5B21B6;"><?=htmlspecialchars($row['category'])?></span></td>
      <td><strong><?=htmlspecialchars($row['kpi_name'])?></strong></td>
      <td style="color:#6B7280;font-size:12px;"><?=htmlspecialchars(mb_strimwidth($row['description']??'',0,60,'…','UTF-8'))?></td>
      <td><?=htmlspecialchars($row['default_unit']??'—')?></td>
      <td><span class="badge" style="background:<?=$row['calc_type']==='maximize'?'#DCFCE7':($row['calc_type']==='minimize'?'#FEE2E2':'#FEF3C7')?>;color:<?=$row['calc_type']==='maximize'?'#15803D':($row['calc_type']==='minimize'?'#B91C1C':'#92400E')?>;"><?=$row['calc_type']==='maximize'?'↑ Tối đa':($row['calc_type']==='minimize'?'↓ Tối thiểu':'→ Đích')?></span></td>
      <?php if($is_admin): ?>
      <td style="display:flex;gap:4px;">
        <button class="btn btn-sm" onclick="openDefModal(<?=htmlspecialchars(json_encode($row),ENT_QUOTES)?>)">✏️</button>
        <form method="POST" onsubmit="return confirm('Ẩn KPI này?')"><input type="hidden" name="action" value="del_kpidef"><input type="hidden" name="id" value="<?=$row['id']?>"><button type="submit" class="btn btn-danger btn-sm">🗑</button></form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
</div>

<?php /* ═══════════════════════════════ TAB: REVIEWS (Quarterly Summary) ═══════════════════════════════ */ elseif($tab==='reviews'): ?>
<div class="panel">
  <div class="panel-hd">
    <h3>🏆 Tổng kết quý&nbsp;<small style="font-weight:500;color:#6B7280;"><?=$cur_cycle?'— '.htmlspecialchars($cur_cycle['name']):''?></small></h3>
  </div>
  <div style="padding:12px 20px; border-bottom:1px solid #F3F4F6;">
    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
      <div style="position:relative; flex:1; min-width:200px;">
        <input type="text" class="kpi-filter-input" data-target="reviews-table" placeholder="Tìm kiếm tên nhân viên..." style="width:100%; padding:8px 12px 8px 32px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px;">
        <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9CA3AF;">🔍</span>
      </div>
      <select class="kpi-filter-role" data-target="reviews-table" style="padding:8px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; background:#fff;">
        <option value="">Tất cả vai trò</option>
        <?php foreach($roles as $r) echo "<option value='".htmlspecialchars($r)."'>".htmlspecialchars($r)."</option>"; ?>
      </select>
    </div>
  </div>
  <div class="tbl-wrap">
  <table class="tbl tbl-sortable" id="reviews-table">
    <thead><tr><th style="width:40px; text-align:center;">#</th><th>Nhân viên</th><th style="text-align:center;">Điểm tổng</th><th style="text-align:center;">Xếp loại</th><th>Nhận xét</th><th>Trạng thái</th><th>Người đánh giá</th><?php if($is_admin): ?><th></th><?php endif; ?></tr></thead>
    <?php 
    $cur_mtype = '';
    $cur_group = '';
    $grp_idx = 0;
    foreach($members as $mem):
      $muid=$mem['id']; $rv=$reviews_map[$muid]??null;
      $ds=$dash_stats[$muid]??['score'=>null];
      $sc_color=$ds['score']===null?'#9CA3AF':($ds['score']>=80?'#16A34A':($ds['score']>=60?'#D97706':'#DC2626'));

      if ($cur_mtype !== $mem['member_type']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_mtype = $mem['member_type'];
        $cur_group = '';
        echo "<tbody><tr class='mtype-separator'><td colspan='".($is_admin?8:7)."'>".htmlspecialchars($cur_mtype)." Members</td></tr></tbody>";
      }

      if ($cur_group !== $mem['job_title']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_group = $mem['job_title'];
        $gid = md5($cur_group ?: 'N/A');
        $grp_idx = 0;
        echo "<tbody class='group-section' data-id='$gid'>";
        echo "<tr class='group-header' onclick='toggleGroup(this)' data-group-id='$gid'><td colspan='".($is_admin?8:7)."' style='border-bottom:1px solid #E2E8F0;'><div style='display:flex; align-items:center; justify-content:space-between;'><strong style='color:#1E293B; font-size:13px; text-transform:uppercase; letter-spacing:1px;'>".htmlspecialchars($cur_group ?: 'N/A')."</strong><span class='chevron'>▼</span></div></td></tr>";
      }
      $grp_idx++;
    ?>
    <tr class="group-item" data-group="<?=md5($cur_group ?: 'N/A')?>" data-role="<?=htmlspecialchars($mem['job_title']??'')?>" data-type="<?=htmlspecialchars($mem['member_type']??'')?>">
      <td style="text-align:center; color:#9CA3AF;"><?=$grp_idx?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;"><?=mb_strtoupper(mb_substr($mem['full_name'],0,1,'UTF-8'),'UTF-8')?></div>
          <div><strong><?=htmlspecialchars($mem['full_name'])?></strong><br><small style="color:#9CA3AF;"><?=htmlspecialchars($mem['job_title']??'')?></small></div>
        </div>
      </td>
      <td style="text-align:center;font-size:20px;font-weight:800;color:<?=$sc_color?>"><?=$ds['score']!==null?$ds['score'].'%':'—'?></td>
      <td style="text-align:center;">
        <?php if($rv && $rv['rating']): ?>
        <span class="badge" style="font-size:14px;padding:4px 12px;background:<?=$rating_color[$rv['rating']]??'#ccc'?>22;color:<?=$rating_color[$rv['rating']]??'#333'?>;border:1px solid <?=$rating_color[$rv['rating']]??'#ccc'?>55;"><?=$rv['rating']?></span>
        <?php else: ?><span style="color:#9CA3AF;">—</span><?php endif; ?>
      </td>
      <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:#6B7280;" title="<?=htmlspecialchars($rv['comment_mgr']??'')?>"><?=htmlspecialchars($rv['comment_mgr']??'—')?></td>
      <td>
        <?php if($rv): $sb=$rv['status']==='approved'?['#DCFCE7','#15803D','✅ Đã duyệt']:($rv['status']==='submitted'?['#FEF9C3','#A16207','⏳ Chờ duyệt']:($rv['status']==='rejected'?['#FEE2E2','#B91C1C','❌ Từ chối']:['#F3F4F6','#6B7280','📄 Nháp'])); ?>
        <span class="badge" style="background:<?=$sb[0]?>;color:<?=$sb[1]?>;"><?=$sb[2]?></span>
        <?php else: ?><span style="color:#9CA3AF;font-size:12px;">Chưa đánh giá</span><?php endif; ?>
      </td>
      <td style="font-size:12px;color:#6B7280;"><?=htmlspecialchars($rv['reviewer_name']??'—')?></td>
      <?php if($is_admin || $muid === $uid): ?>
      <td><button class="btn <?=($muid===$uid && !$is_admin)?'btn-success':'btn-primary'?> btn-sm" onclick="openReviewModal(<?=$muid?>,<?=htmlspecialchars(json_encode($rv??new stdClass),ENT_QUOTES)?>, '<?=htmlspecialchars($mem['full_name'],ENT_QUOTES)?>')">✏️ <?=($muid===$uid && !$is_admin)?'Xem/Phản hồi':'Đánh giá'?></button></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; if($cur_group !== '') echo "</tbody>"; ?>
  </table>
  </div>
</div>

<?php /* ═══════════════════════════════ TAB: YEARLY ═══════════════════════════════ */ elseif($tab==='yearly'): ?>
<div class="panel">
  <div class="panel-hd">
    <div style="display:flex;align-items:center;gap:10px;">
      <h3>📅 Tổng kết năm <?=$sel_year?></h3>
      <span style="font-size:12px;color:#6B7280;">(Điểm trung bình trọng số theo tháng)</span>
    </div>
    <div class="filter-bar">
       <form method="GET">
         <input type="hidden" name="tab" value="yearly">
         <select name="year" onchange="this.form.submit()">
           <?php for($y=date('Y');$y>=2021;$y--): ?>
           <option value="<?=$y?>" <?=$sel_year==$y?'selected':''?>>Năm <?=$y?></option>
           <?php endfor; ?>
         </select>
       </form>
    </div>
  </div>
  <div style="padding:12px 20px; border-bottom:1px solid #F3F4F6;">
    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
      <div style="position:relative; flex:1; min-width:200px;">
        <input type="text" class="kpi-filter-input" data-target="yearly-table" placeholder="Tìm kiếm tên nhân viên..." style="width:100%; padding:8px 12px 8px 32px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px;">
        <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9CA3AF;">🔍</span>
      </div>
      <select class="kpi-filter-role" data-target="yearly-table" style="padding:8px 12px; border:1px solid #D1D5DB; border-radius:6px; font-size:13px; background:#fff;">
        <option value="">Tất cả vai trò</option>
        <?php foreach($roles as $r) echo "<option value='".htmlspecialchars($r)."'>".htmlspecialchars($r)."</option>"; ?>
      </select>
    </div>
  </div>
  <div class="tbl-wrap">
  <table class="tbl tbl-sortable" id="yearly-table">
    <thead>
      <tr>
        <th style="width:40px; text-align:center;">#</th>
        <th style="min-width:180px;">Nhân viên</th>
        <th style="width:70px;text-align:center;">Phân loại</th>
        <?php for($m=1;$m<=12;$m++): ?><th style="text-align:center;width:60px;">T<?=$m?></th><?php endfor; ?>
        <th style="text-align:center;width:80px;background:#EEF2FF;color:#3730A3;">TB Năm</th>
      </tr>
    </thead>
    <?php 
    $cur_mtype = '';
    $cur_group = '';
    $grp_idx = 0;
    foreach($members as $m): 
      $muid=$m['id']; 
      $scores=$yearly_scores[$muid]??[];
      $mo_count=0; $mo_sum=0;
      for($i=1;$i<=12;$i++) if(isset($scores[$i])) { $mo_count++; $mo_sum+=$scores[$i]; }
      $avg_y = ($mo_count>0) ? round($mo_sum/$mo_count,1) : null;

      if ($cur_mtype !== $m['member_type']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_mtype = $m['member_type'];
        $cur_group = '';
        echo "<tbody><tr class='mtype-separator'><td colspan='16'>".htmlspecialchars($cur_mtype)." Members</td></tr></tbody>";
      }

      if ($cur_group !== $m['job_title']) {
        if ($cur_group !== '') echo "</tbody>";
        $cur_group = $m['job_title'];
        $gid = md5($cur_group ?: 'N/A');
        $grp_idx = 0;
        echo "<tbody class='group-section' data-id='$gid'>";
        echo "<tr class='group-header' onclick='toggleGroup(this)' data-group-id='$gid'><td colspan='16' style='border-bottom:1px solid #E2E8F0;'><div style='display:flex; align-items:center; justify-content:space-between;'><strong style='color:#1E293B; font-size:13px; text-transform:uppercase; letter-spacing:1px;'>".htmlspecialchars($cur_group ?: 'N/A')."</strong><span class='chevron'>▼</span></div></td></tr>";
      }
      $grp_idx++;
    ?>
    <tr class="group-item" data-group="<?=md5($cur_group ?: 'N/A')?>" data-role="<?=htmlspecialchars($m['job_title']??'')?>" data-type="<?=htmlspecialchars($m['member_type']??'')?>">
      <td style="text-align:center; color:#9CA3AF;"><?=$grp_idx?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="emp-av" style="width:28px;height:28px;font-size:12px;"><?=mb_substr($m['full_name'],0,1)?></div>
          <div style="line-height:1.2;">
            <div style="font-weight:700;font-size:13px;"><?=htmlspecialchars($m['full_name'])?></div>
            <div style="font-size:10px;color:#9CA3AF;"><?=htmlspecialchars($m['job_title'])?></div>
          </div>
        </div>
      </td>
      <td style="text-align:center;">
        <?php $mt=$m['member_type']??'Key'; $mt_c=($mt==='Core')?'#F59E0B':'#2563EB'; ?>
        <span class="badge" style="background:<?=$mt_c?>;color:#fff;font-size:8px;padding:1px 5px;"><?=strtoupper($mt)?></span>
      </td>
      <?php for($mo=1;$mo<=12;$mo++): 
        $s=$scores[$mo]??null;
        $sc='#9CA3AF'; if($s!==null) $sc=($s>=80?'#16A34A':($s>=60?'#D97706':'#DC2626'));
      ?>
      <td style="text-align:center;font-weight:600;color:<?=$sc?>;"><?=$s!==null?$s:'—'?></td>
      <?php endfor; ?>
      <td style="text-align:center;font-weight:800;background:#F8FAFF;color:#1E1B4B;font-size:14px;"><?=$avg_y!==null?$avg_y:'—'?></td>
    </tr>
    <?php endforeach; if($cur_group !== '') echo "</tbody>"; ?>
  </table>
  </div>
</div>
<?php endif; ?>

</div><!-- .ck-body -->
</div><!-- .ck-wrap -->
</main>
</div><!-- .dashboard-container -->
