<!-- =============================== MODALS ============================== -->

<!-- Modal: Chọn user từ hệ thống để thêm vào nhóm Core/Key -->
<div class="modal" id="m-add-member">
<div class="mc" style="width:480px;">
  <h3>👥 Thêm thành viên Core/Key</h3>
  <p style="color:#6B7280;font-size:13px;margin:-8px 0 16px;">Chọn nhân viên từ danh sách tài khoản hệ thống để thêm vào nhóm Core/Key và quản lý KPI.</p>
  <form method="POST">
  <input type="hidden" name="action" value="add_member">
  <div class="fg">
    <label>Chọn nhân viên *</label>
    <select name="pick_user_id" required style="font-size:14px;padding:10px;">
      <option value="">-- Chọn từ danh sách --</option>
      <?php foreach($non_members as $u): ?>
      <option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name'])?><?=$u['job_title']?' — '.htmlspecialchars($u['job_title']):''?> &lt;<?=htmlspecialchars($u['email']??'')?>&gt;</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Loại thành viên</label>
    <select name="member_type" style="font-size:14px;padding:10px;">
      <option value="Key">Key Member (Chủ chốt)</option>
      <option value="Core">Core Member (Cốt lõi)</option>
    </select>
  </div>
  <?php if(empty($non_members)): ?>
  <div style="text-align:center;padding:20px;color:#9CA3AF;background:#F9FAFB;border-radius:8px;">
    ✅ Tất cả user trong hệ thống đã được thêm vào nhóm Core/Key
  </div>
  <?php endif; ?>
  <div class="mf">
    <button type="button" class="btn" onclick="document.getElementById('m-add-member').classList.remove('show')">Huỷ</button>
    <?php if(!empty($non_members)): ?><button type="submit" class="btn btn-primary">➕ Thêm vào nhóm</button><?php endif; ?>
  </div>
  </form>
</div>
</div>

<!-- Modal: Add/Edit Cycle -->
<div class="modal" id="m-cycle">
<div class="mc">
  <h3 id="m-cycle-title">📅 Thêm chu kỳ đánh giá</h3>
  <form method="POST">
  <input type="hidden" name="action" id="cycle-action" value="add_cycle">
  <input type="hidden" name="id" id="cycle-id" value="">
  <div class="fg">
    <label>Tên chu kỳ *</label>
    <input type="text" name="name" id="cycle-name" required placeholder="Q4/2025">
  </div>
  <div class="fg2">
    <div class="fg"><label>Năm *</label><input type="number" name="year" id="cycle-year" value="<?=date('Y')?>" required min="2020" max="2035"></div>
    <div class="fg"><label>Quý (bỏ trống = cả năm)</label><select name="quarter" id="cycle-quarter"><option value="">-- Cả năm --</option><option>1</option><option>2</option><option>3</option><option>4</option></select></div>
  </div>
  <div class="fg2">
    <div class="fg"><label>Từ ngày</label><input type="date" name="start_date" id="cycle-start"></div>
    <div class="fg"><label>Đến ngày</label><input type="date" name="end_date" id="cycle-end"></div>
  </div>
  <div class="fg">
    <label>Trạng thái</label>
    <select name="status" id="cycle-status">
      <option value="planning">Lên kế hoạch</option>
      <option value="active">Đang chạy</option>
      <option value="reviewing">Đang đánh giá</option>
      <option value="closed">Đã kết thúc</option>
    </select>
  </div>
  <div class="mf">
    <button type="button" class="btn" onclick="document.getElementById('m-cycle').classList.remove('show')">Huỷ</button>
    <button type="submit" class="btn btn-primary">💾 Lưu</button>
  </div>
  </form>
</div>
</div>

<!-- Modal: Add/Edit KPI Definition -->
<div class="modal" id="m-def">
<div class="mc">
  <h3 id="m-def-title">⚙️ Thêm định nghĩa KPI</h3>
  <form method="POST">
  <input type="hidden" name="action" id="def-action" value="add_kpidef">
  <input type="hidden" name="id" id="def-id" value="">
  <div class="fg">
    <label>Tên KPI *</label>
    <input type="text" name="kpi_name" id="def-name" required placeholder="Doanh thu theo tháng">
  </div>
  <div class="fg2">
    <div class="fg"><label>Nhóm / Danh mục</label><input type="text" name="category" id="def-cat" value="General" placeholder="Kinh doanh, Vận hành..."></div>
    <div class="fg"><label>Đơn vị đo</label><input type="text" name="default_unit" id="def-unit" placeholder="%, VNĐ, lần..."></div>
  </div>
  <div class="fg">
    <label>Loại tính KPI</label>
    <select name="calc_type" id="def-calctype">
      <option value="maximize">↑ Tối đa (càng cao càng tốt)</option>
      <option value="minimize">↓ Tối thiểu (càng thấp càng tốt)</option>
      <option value="target">→ Đích (đạt đúng target)</option>
    </select>
  </div>
  <div class="fg">
    <label>Mô tả</label>
    <textarea name="description" id="def-desc" rows="2" placeholder="Mô tả cách tính, ý nghĩa KPI..."></textarea>
  </div>
  <div class="mf">
    <button type="button" class="btn" onclick="document.getElementById('m-def').classList.remove('show')">Huỷ</button>
    <button type="submit" class="btn btn-primary">💾 Lưu</button>
  </div>
  </form>
</div>
</div>

<!-- Modal: Gán KPI cho thành viên -->
<div class="modal" id="m-assign">
<div class="mc">
  <h3>📋 Gán KPI cho thành viên</h3>
  <form method="POST">
  <input type="hidden" name="action" value="save_assign">
  <div class="fg">
    <label>Nhân viên *</label>
    <select name="user_id" required>
      <option value="">-- Chọn nhân viên --</option>
      <?php foreach($members as $m): ?>
      <option value="<?=$m['id']?>" <?=$sel_user==$m['id']?'selected':''?>>
        <?=htmlspecialchars($m['full_name'])?><?=$m['job_title']?' — '.htmlspecialchars($m['job_title']):''?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Chọn các KPI * (Có thể chọn nhiều)</label>
    <select name="kpi_def_ids[]" multiple required style="height:200px;font-size:14px;">
      <?php $cur_cat=''; foreach($kpi_defs as $d): if($cur_cat!==$d['category']): $cur_cat=$d['category']; ?>
      <optgroup label="── <?=htmlspecialchars($cur_cat)?> ──"></optgroup>
      <?php endif; ?>
      <option value="<?=$d['id']?>"><?=htmlspecialchars($d['kpi_name'])?> (<?=htmlspecialchars($d['default_unit']??'')?>)</option>
      <?php endforeach; ?>
    </select>
    <small style="color:#6B7280;margin-top:4px;display:block;">Nhấn giữ Ctrl/Cmd để chọn nhiều mục cùng lúc.</small>
  </div>
  <div class="fg">
    <label>Chu kỳ *</label>
    <select name="cycle_id" required>
      <?php foreach($cycles as $c): ?>
      <option value="<?=$c['id']?>" <?=$sel_cycle==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg2">
    <div class="fg"><label>Mục tiêu</label><input type="number" step="0.01" name="target_value" placeholder="100"></div>
    <div class="fg"><label>Đơn vị</label><input type="text" name="unit" placeholder="%"></div>
  </div>
  <div class="fg">
    <label>Trọng số (%)</label>
    <input type="number" step="0.1" min="0" max="100" name="weight" value="10" placeholder="10">
  </div>
  <div class="fg"><label>Ghi chú</label><textarea name="notes" rows="2"></textarea></div>
  <div class="mf">
    <button type="button" class="btn" onclick="document.getElementById('m-assign').classList.remove('show')">Huỷ</button>
    <button type="submit" class="btn btn-primary">💾 Gán KPI</button>
  </div>
  </form>
</div>
</div>

<!-- Modal: Review/Đánh giá cuối kỳ -->
<div class="modal" id="m-review">
<div class="mc">
  <h3 id="m-review-title">🏆 Đánh giá tổng kết</h3>
  <form method="POST">
  <input type="hidden" name="action" value="save_review">
  <input type="hidden" name="user_id" id="rv-user-id" value="">
  <input type="hidden" name="cycle_id" value="<?=$sel_cycle?>">
  <div class="fg">
    <label>Nhân viên</label>
    <input type="text" id="rv-emp-name" readonly style="background:#F9FAFB;color:#6B7280;">
  </div>
  <div class="fg2">
    <div class="fg"><label>Điểm tổng hợp (0–100)</label><input type="number" name="overall_score" id="rv-score" step="0.1" min="0" max="100" placeholder="85.5" <?=$is_admin?'':'readonly'?>></div>
    <div class="fg"><label>Xếp loại</label>
      <select name="rating" id="rv-rating" <?=$is_admin?'':'disabled'?>>
        <option value="">-- Chưa xếp loại --</option>
        <option value="A">A — Xuất sắc</option>
        <option value="B+">B+ — Tốt</option>
        <option value="B">B — Khá</option>
        <option value="C+">C+ — Trung bình khá</option>
        <option value="C">C — Trung bình</option>
        <option value="D">D — Yếu</option>
      </select>
    </div>
  </div>
  <div class="fg"><label>Nhận xét từ quản lý</label><textarea name="comment_mgr" id="rv-cmgr" rows="3" placeholder="Điểm mạnh, điểm cần cải thiện..." <?=$is_admin?'':'readonly'?>></textarea></div>
  <div class="fg"><label>Ý kiến nhân viên</label><textarea name="comment_emp" id="rv-cemp" rows="2" placeholder="Phản hồi từ nhân viên..."></textarea></div>
  <div class="fg"><label>Trạng thái</label>
    <select name="status" id="rv-status" <?=$is_admin?'':'disabled'?>>
      <option value="draft">Nháp</option>
      <option value="submitted">Chờ duyệt</option>
      <option value="approved">Đã duyệt</option>
      <option value="rejected">Từ chối</option>
    </select>
  </div>
  <div class="mf">
    <button type="button" class="btn" onclick="document.getElementById('m-review').classList.remove('show')">Huỷ</button>
    <button type="submit" class="btn btn-primary">💾 Lưu đánh giá</button>
  </div>
  </form>
</div>
</div>

<script>
// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); }));

function openEmpModal(data) {
  const m = document.getElementById('m-emp');
  if(data) {
    document.getElementById('m-emp-title').textContent = '✏️ Sửa nhân viên';
    document.getElementById('emp-action').value = 'edit_emp';
    document.getElementById('emp-id').value = data.id;
    document.getElementById('emp-fullname').value = data.full_name||'';
    document.getElementById('emp-position').value = data.position||'';
    document.getElementById('emp-dept').value = data.department||'';
    document.getElementById('emp-email').value = data.email||'';
    document.getElementById('emp-startdate').value = data.start_date||'';
    document.getElementById('emp-notes').value = data.notes||'';
    document.getElementById('emp-userid').value = data.user_id||'';
    document.getElementById('emp-active-row').style.display='block';
    document.getElementById('emp-active').checked = data.is_active==1;
  } else {
    document.getElementById('m-emp-title').textContent = '➕ Thêm nhân viên';
    document.getElementById('emp-action').value = 'add_emp';
    document.getElementById('emp-id').value = '';
    document.getElementById('m-emp').querySelector('form').reset();
    document.getElementById('emp-active-row').style.display='none';
  }
  m.classList.add('show');
}

function openCycleModal(data) {
  const m = document.getElementById('m-cycle');
  if(data) {
    document.getElementById('m-cycle-title').textContent = '✏️ Sửa chu kỳ';
    document.getElementById('cycle-action').value = 'edit_cycle';
    document.getElementById('cycle-id').value = data.id;
    document.getElementById('cycle-name').value = data.name||'';
    document.getElementById('cycle-year').value = data.year||'';
    document.getElementById('cycle-quarter').value = data.quarter||'';
    document.getElementById('cycle-start').value = data.start_date||'';
    document.getElementById('cycle-end').value = data.end_date||'';
    document.getElementById('cycle-status').value = data.status||'planning';
  } else {
    document.getElementById('m-cycle-title').textContent = '📅 Thêm chu kỳ';
    document.getElementById('cycle-action').value = 'add_cycle';
    document.getElementById('cycle-id').value = '';
  }
  m.classList.add('show');
}

function openDefModal(data) {
  const m = document.getElementById('m-def');
  if(data) {
    document.getElementById('m-def-title').textContent = '✏️ Sửa KPI';
    document.getElementById('def-action').value = 'edit_kpidef';
    document.getElementById('def-id').value = data.id;
    document.getElementById('def-name').value = data.kpi_name||'';
    document.getElementById('def-cat').value = data.category||'General';
    document.getElementById('def-unit').value = data.default_unit||'';
    document.getElementById('def-calctype').value = data.calc_type||'maximize';
    document.getElementById('def-desc').value = data.description||'';
  } else {
    document.getElementById('m-def-title').textContent = '⚙️ Thêm KPI';
    document.getElementById('def-action').value = 'add_kpidef';
    document.getElementById('def-id').value = '';
    document.getElementById('m-def').querySelector('form').reset();
  }
  m.classList.add('show');
}

function openReviewModal(userId, data, empName) {
  document.getElementById('rv-user-id').value = userId;
  document.getElementById('rv-emp-name').value = empName || ('Nhân viên #' + userId);
  document.getElementById('rv-score').value = (data && data.overall_score) ? data.overall_score : '';
  document.getElementById('rv-rating').value = (data && data.rating) ? data.rating : '';
  document.getElementById('rv-cmgr').value = (data && data.comment_mgr) ? data.comment_mgr : '';
  document.getElementById('rv-cemp').value = (data && data.comment_emp) ? data.comment_emp : '';
  document.getElementById('rv-status').value = (data && data.status) ? data.status : 'draft';
  document.getElementById('m-review').classList.add('show');
}
</script>
