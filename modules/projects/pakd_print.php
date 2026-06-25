<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/app_settings.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit(); }
$user_id      = $_SESSION['user_id'];
$role         = $_SESSION['role'] ?? 'user';
$my_full_name = $_SESSION['full_name'] ?? '';
$is_admin     = ($role === 'admin');
if (empty($_SESSION['is_am_bd']) && !$is_admin) { header('Location: /dashboard'); exit(); }
$pakd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$pakd_id) { echo '<p>ID không hợp lệ.</p>'; exit; }
$stmt = $conn->prepare("SELECT * FROM pakd WHERE id = ?");
$stmt->bind_param("i", $pakd_id); $stmt->execute();
$pakd = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$pakd) { echo '<p>Không tìm thấy PAKD.</p>'; exit; }
if (!$is_admin) {
    $owner = (!empty($pakd['am_user_id']) && (int)$pakd['am_user_id'] === $user_id)
          || (!empty($pakd['am_name'])    && $pakd['am_name'] === $my_full_name);
    if (!$owner) { echo '<p>Bạn không có quyền xem tài liệu này.</p>'; exit; }
}
$fin_saved        = !empty($pakd['fin_data']) ? (json_decode($pakd['fin_data'],true)??[]) : [];
$fin_rev_gross    = (float)($pakd['revenue']??0);
$fin_human_cost   = (float)($fin_saved['human_cost']??0);
$fin_overtime     = (float)($fin_saved['overtime_cost']??0);
$fin_rev_net      = !empty($fin_saved['rev_net']) ? (float)$fin_saved['rev_net'] : $fin_rev_gross;
$pasx_has_data    = ($fin_human_cost>0||$fin_overtime>0);
$fin_prod_cost    = $pasx_has_data ? ($fin_human_cost+$fin_overtime) : (float)($pakd['pasx_value']??0);
$fin_sales_pct    = (float)($fin_saved['r421_pct']??0);
$fin_presales_pct = (float)($fin_saved['r422_pct']??0);
$fin_mkt_pct      = (float)($fin_saved['r423_pct']??0);
$fin_sales_comm   = (int)round(max(0,$fin_rev_net)*$fin_sales_pct/100);
$fin_presales_comm= (int)round(max(0,$fin_rev_net)*$fin_presales_pct/100);
$fin_mkt_comm     = (int)round(max(0,$fin_rev_net)*$fin_mkt_pct/100);
$fin_sales_total  = $fin_sales_comm+$fin_presales_comm+$fin_mkt_comm;
$fin_mgmt_pct     = (float)($fin_saved['r43_pct']??12.0);
$fin_mgmt         = (int)round(max(0,$fin_rev_net)*$fin_mgmt_pct/100);
$fin_pasx_note    = trim($fin_saved['pasx_note']??'');
$r424_val  = (float)($fin_saved['r424_val']??0);
$r424_name = $fin_saved['r424_name']??'Chi phí bán hàng khác';
$r424_desc = $fin_saved['r424_desc']??'';
$other_keys = ['4.4.1'=>['label'=>'Công tác phí','key'=>'r441'],'4.4.2'=>['label'=>'Chi phí đào tạo','key'=>'r442'],'4.4.3'=>['label'=>'Chi phí teambuilding','key'=>'r443'],'4.4.4'=>['label'=>'Chi phí tiếp khách','key'=>'r444'],'4.4.5'=>['label'=>'Chi phí hội thảo, truyền thông','key'=>'r445']];
$fin_other_cost=0;
foreach($other_keys as $num=>&$item){$item['val']=(float)($fin_saved[$item['key'].'_val']??0);$item['name']=$fin_saved[$item['key'].'_name']??$item['label'];$item['desc']=$fin_saved[$item['key'].'_desc']??'';$fin_other_cost+=$item['val'];}unset($item);
$fin_total_cost  = $fin_prod_cost+$fin_sales_total+$fin_mgmt+$fin_other_cost+$r424_val;
$fin_gp_db       = (float)($pakd['gross_profit']??0);
$fin_gp          = $fin_gp_db!=0?$fin_gp_db:($fin_rev_net-$fin_total_cost);
$fin_margin      = $fin_rev_net>0?($fin_gp/$fin_rev_net*100):0;
$rev_rows=$fin_saved['rev_rows']??[];$cr_rows=$fin_saved['cr_rows']??[];$ded_rows=$fin_saved['ded_rows']??[];$cog_rows=$fin_saved['cog_rows']??[];
$cr_total=0;foreach($cr_rows as $r)$cr_total+=(float)($r['amount']??0);
$ded_total=0;foreach($ded_rows as $r)$ded_total+=(float)($r['amount']??0);
function fv($n){return number_format((float)$n,0,',','.');}
function fp($v,$b,$d=2){if(!$b)return '—';return number_format($v/$b*100,$d).'%';}
function fpc($v,$t,$d=2){if(!$t)return '—';return number_format(max(0,$v/$t*100),$d).'%';}
function scp($cx,$cy,$ro,$ri,$n){$p=[];for($i=0;$i<$n*2;$i++){$a=$i*M_PI/$n-M_PI/2;$r=($i%2===0)?$ro:$ri;$p[]=round($cx+$r*cos($a),2).','.round($cy+$r*sin($a),2);}return 'M '.implode(' L ',$p).' Z';}
$scallopD=scp(100,100,97,90,28);$sid='pps_'.$pakd_id;
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>PAKD #<?php echo $pakd_id; ?> - <?php echo htmlspecialchars($pakd['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',Arial,sans-serif;font-size:11pt;color:#1e293b;background:#fff;}
.screen-bar{background:#1e293b;color:#fff;padding:10px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;font-size:13px;position:sticky;top:0;z-index:99;}
.screen-bar .stitle{font-weight:700;flex:1;}
.screen-bar .hint{font-size:11px;color:#94a3b8;}
.btn-print{display:inline-flex;align-items:center;gap:7px;padding:8px 20px;border:none;border-radius:8px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 2px 12px rgba(99,102,241,.4);}
.btn-close{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #475569;border-radius:8px;background:transparent;color:#cbd5e1;font-size:13px;cursor:pointer;font-family:inherit;}
.doc{max-width:297mm;margin:16px auto;background:#fff;padding:14mm 14mm 12mm;box-shadow:0 4px 24px rgba(0,0,0,.12);}
.dh{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2.5px solid #1e40af;padding-bottom:10px;margin-bottom:12px;}
.dh .co{font-size:18pt;font-weight:800;color:#1e40af;line-height:1.1;}
.dh .cosub{font-size:8.5pt;color:#64748b;margin-top:4px;}
.dh .dtb{text-align:right;}
.dh .dtype{font-size:10.5pt;font-weight:700;color:#1e293b;text-transform:uppercase;letter-spacing:.06em;}
.dh .did{font-size:8.5pt;color:#64748b;margin-top:3px;}
.stitle{font-size:8.5pt;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.07em;margin:12px 0 6px;border-left:3px solid #1e40af;padding-left:8px;}
.igrid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px 16px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:10px 14px;margin-bottom:10px;}
.ilabel{font-size:7.5pt;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.ivalue{font-size:9.5pt;font-weight:600;color:#1e293b;}
.sumbox{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}
.scard{border:1px solid #cbd5e1;border-radius:6px;padding:8px 12px;}
.scard .sl{font-size:8pt;color:#64748b;margin-bottom:2px;}
.scard .sv{font-size:12pt;font-weight:800;color:#1e293b;}
.scard.hi{background:#eff6ff;border-color:#bfdbfe;} .scard.hi .sv{color:#1e40af;}
.scard.ok{background:#f0fdf4;border-color:#bbf7d0;} .scard.ok .sv{color:#15803d;}
.scard.no{background:#fff1f2;border-color:#fecdd3;} .scard.no .sv{color:#b91c1c;}
table.ft{width:100%;border-collapse:collapse;font-size:9pt;}
table.ft thead th{background:#1e293b;color:#e2e8f0;padding:7px 9px;text-align:left;font-size:7.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid #334155;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
table.ft thead th.r{text-align:right;} table.ft thead th.c{text-align:center;}
table.ft td{padding:5px 9px;vertical-align:middle;border-bottom:1px solid #f1f5f9;border-left:1px solid #f1f5f9;border-right:1px solid #f1f5f9;}
table.ft td.r{text-align:right;} table.ft td.c{text-align:center;} table.ft td.b{font-weight:700;}
.rrev{background:#dcfce7;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.rrev td{color:#166534;font-weight:600;}
.rcost{background:#ffedd5;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.rcost td{color:#9a3412;font-weight:600;}
.rcat{background:#f1f5f9;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.rcat td{font-weight:700;color:#1e293b;}
.rform td{font-weight:700;color:#1e40af;}
.rsub{background:#f8fafc;} .rsub td{font-weight:600;color:#334155;}
.rlock td{color:#475569;font-style:italic;}
.rgross{background:#eff6ff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.rgross td{font-weight:800;color:#1e40af;font-size:10pt;}
.i1{padding-left:20px!important;} .i2{padding-left:36px!important;} .i3{padding-left:52px!important;}
.tstt{color:#94a3b8;font-size:8pt;text-align:center;width:40px;}
.blue{color:#1e40af!important;} .green{color:#15803d!important;} .red{color:#b91c1c!important;}
.stamp-sec{display:flex;justify-content:flex-end;align-items:center;margin-top:24px;gap:20px;}
.stamp-sig{text-align:center;}
.stamp-sl{font-size:8pt;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;}
.stamp-sn{font-size:19pt;font-weight:800;color:#1e293b;letter-spacing:-.02em;}
.stamp-sd{font-size:9pt;color:#64748b;margin-top:3px;}
.docfooter{margin-top:20px;padding-top:8px;border-top:1px solid #cbd5e1;display:flex;justify-content:space-between;font-size:7.5pt;color:#94a3b8;}
@media print{
  .screen-bar{display:none!important;}
  body{background:#fff!important;}
  .doc{max-width:100%!important;margin:0!important;padding:10mm 8mm 8mm!important;box-shadow:none!important;}
  table.ft{font-size:8.5pt!important;page-break-inside:auto;}
  table.ft thead th{font-size:7pt!important;}
  table.ft td{padding:4px 7px!important;}
  table.ft tr{page-break-inside:avoid;}
  .sumbox{page-break-inside:avoid;}
  .stamp-sec{page-break-inside:avoid;}
  .rrev,.rcost,.rcat,.rform,.rsub,.rlock,.rgross,.scard.hi,.scard.ok,.scard.no{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
  @page{size:A4 landscape;margin:10mm 8mm;}
}
</style>
</head>
<body>
<div class="screen-bar">
  <span class="stitle">📋 Xem trước: Phương án Kinh doanh — <?php echo htmlspecialchars($pakd['name']); ?></span>
  <span class="hint">Chọn "Save as PDF" trong hộp thoại in để xuất file PDF chuẩn</span>
  <button class="btn-close" onclick="window.close()">✕ Đóng</button>
  <button class="btn-print" onclick="window.print()">🖨 In / Xuất PDF</button>
</div>
<div class="doc">
  <!-- Header -->
  <div class="dh">
    <div><div class="co">ArrowHitech</div><div class="cosub">AHT KPI System · Phương án Kinh doanh</div></div>
    <div class="dtb">
      <div class="dtype">Phương án Kinh doanh</div>
      <div class="did">ID #<?php echo $pakd_id; ?> · <?php echo date('d/m/Y'); ?></div>
      <div class="did">Trạng thái: <strong><?php echo htmlspecialchars(ucfirst($pakd['status']??'')); ?></strong></div>
    </div>
  </div>
  <!-- Info -->
  <div class="stitle">Thông tin dự án</div>
  <div class="igrid">
    <div><div class="ilabel">Tên PAKD / Opportunity</div><div class="ivalue"><?php echo htmlspecialchars($pakd['opportunity_name']?:$pakd['name']); ?></div></div>
    <div><div class="ilabel">Khách hàng</div><div class="ivalue"><?php echo htmlspecialchars($pakd['company_name']?:'—'); ?></div></div>
    <div><div class="ilabel">AM phụ trách</div><div class="ivalue"><?php echo htmlspecialchars($pakd['am_name']?:'—'); ?></div></div>
    <div><div class="ilabel">Department</div><div class="ivalue"><?php echo htmlspecialchars($pakd['department']?:'—'); ?></div></div>
    <div><div class="ilabel">Số hợp đồng</div><div class="ivalue"><?php echo htmlspecialchars($pakd['contract_no']?:'—'); ?></div></div>
    <div><div class="ilabel">Sales Order No</div><div class="ivalue"><?php echo htmlspecialchars($pakd['sales_order_no']?:'—'); ?></div></div>
    <div><div class="ilabel">Currency</div><div class="ivalue"><?php echo htmlspecialchars($pakd['currency']?:'VND'); ?></div></div>
    <div><div class="ilabel">Loại dự án</div><div class="ivalue"><?php echo htmlspecialchars(ucfirst($pakd['project_type']??'—')); ?></div></div>
  </div>
  <!-- Summary -->
  <div class="stitle">Tổng quan tài chính</div>
  <div class="sumbox">
    <div class="scard"><div class="sl">Doanh thu gộp</div><div class="sv"><?php echo fv($fin_rev_gross); ?> <small style="font-size:9pt;font-weight:400;">VND</small></div></div>
    <div class="scard hi"><div class="sl">Doanh thu thuần</div><div class="sv"><?php echo fv($fin_rev_net); ?> <small style="font-size:9pt;font-weight:400;">VND</small></div></div>
    <div class="scard <?php echo $fin_gp>=0?'ok':'no'; ?>"><div class="sl">Lợi nhuận gộp (Margin)</div><div class="sv"><?php echo fv($fin_gp); ?> VND <span style="font-size:9pt;font-weight:600;">(<?php echo number_format($fin_margin,2); ?>%)</span></div></div>
  </div>
  <!-- Table -->
  <div class="stitle">Phương án Kinh doanh chi tiết</div>
  <table class="ft">
    <thead><tr>
      <th style="width:40px;" class="c">STT</th>
      <th style="width:210px;">Hạng mục</th>
      <th>Diễn giải</th>
      <th style="width:90px;" class="r">Tỷ lệ</th>
      <th style="width:150px;" class="r">Số tiền (VND)</th>
      <th style="width:44px;" class="c">CCY</th>
    </tr></thead>
    <tbody>
      <!-- 1 -->
      <tr class="rrev"><td class="tstt">1</td><td class="b">Doanh thu</td><td></td><td class="r b">100.00%</td><td class="r b"><?php echo fv($fin_rev_gross); ?></td><td class="c">VND</td></tr>
      <?php foreach($rev_rows as $rr): ?><tr><td class="tstt">1.x</td><td class="i1"><?php echo htmlspecialchars($rr['name']??''); ?></td><td><?php echo htmlspecialchars($rr['desc']??''); ?></td><td class="r"><?php echo fp((float)($rr['amount']??0),$fin_rev_gross); ?></td><td class="r"><?php echo fv((float)($rr['amount']??0)); ?></td><td class="c">VND</td></tr><?php endforeach; ?>
      <!-- 1.3 CR -->
      <tr class="rsub"><td class="tstt">1.3</td><td class="i1 b">Change Requests</td><td style="font-size:8pt;color:#64748b;">Các khoản thu thêm từ CR</td><td class="r"><?php echo $cr_total>0?fp($cr_total,$fin_rev_gross):''; ?></td><td class="r b"><?php echo fv($cr_total); ?></td><td class="c">VND</td></tr>
      <?php foreach($cr_rows as $cr): ?><tr><td class="tstt">CR</td><td class="i2"><?php echo htmlspecialchars($cr['name']??''); ?></td><td><?php echo htmlspecialchars($cr['desc']??''); ?></td><td class="r"></td><td class="r"><?php echo fv((float)($cr['amount']??0)); ?></td><td class="c">VND</td></tr><?php endforeach; ?>
      <!-- 2 -->
      <tr class="rcat"><td class="tstt">2</td><td class="b">Khoản giảm trừ doanh thu</td><td></td><td class="r"></td><td class="r b"><?php echo fv($ded_total); ?></td><td class="c">VND</td></tr>
      <?php foreach($ded_rows as $dr): ?><tr><td class="tstt">2.x</td><td class="i1"><?php echo htmlspecialchars($dr['name']??''); ?></td><td><?php echo htmlspecialchars($dr['desc']??''); ?></td><td class="r"><?php echo fp((float)($dr['amount']??0),$fin_rev_gross); ?></td><td class="r"><?php echo fv((float)($dr['amount']??0)); ?></td><td class="c">VND</td></tr><?php endforeach; ?>
      <!-- 3 -->
      <tr class="rform"><td class="tstt">3</td><td class="b">Doanh thu thuần</td><td style="color:#64748b;">= Doanh thu - Khoản giảm trừ</td><td class="r b">100%</td><td class="r b blue"><?php echo fv($fin_rev_net); ?></td><td class="c">VND</td></tr>
      <!-- 4 -->
      <tr class="rcost"><td class="tstt">4</td><td class="b">Tổng chi phí</td><td></td><td class="r b"><?php echo fp($fin_total_cost,$fin_rev_net); ?></td><td class="r b"><?php echo fv($fin_total_cost); ?></td><td class="c">VND</td></tr>
      <!-- 4.1 -->
      <tr class="rlock rsub"><td class="tstt">4.1</td><td class="i1 b">Chi phí sản xuất<?php echo $pasx_has_data?'':' (chờ PASX)'; ?></td><td style="font-size:8pt;">Từ Phương án sản xuất<?php echo $fin_pasx_note?' · '.htmlspecialchars($fin_pasx_note):''; ?></td><td class="r"><?php echo fpc($fin_prod_cost,$fin_total_cost); ?></td><td class="r b"><?php echo fv($fin_prod_cost); ?></td><td class="c">VND</td></tr>
      <?php if($fin_human_cost>0): ?><tr class="rlock"><td class="tstt">4.1.1</td><td class="i2">Human Cost / Chi phí nhân công</td><td style="font-size:8pt;color:#64748b;">Từ Phương án sản xuất</td><td class="r"><?php echo fpc($fin_human_cost,$fin_total_cost); ?></td><td class="r"><?php echo fv($fin_human_cost); ?></td><td class="c">VND</td></tr><?php endif; ?>
      <?php if($fin_overtime>0): ?><tr class="rlock"><td class="tstt">4.1.2</td><td class="i2">Chi phí làm việc ngoài giờ / Overtime</td><td style="font-size:8pt;color:#64748b;">Từ Phương án sản xuất</td><td class="r"><?php echo fpc($fin_overtime,$fin_total_cost); ?></td><td class="r"><?php echo fv($fin_overtime); ?></td><td class="c">VND</td></tr><?php endif; ?>
      <?php foreach($cog_rows as $cg): ?><tr><td class="tstt">4.1.x</td><td class="i2"><?php echo htmlspecialchars($cg['name']??''); ?></td><td><?php echo htmlspecialchars($cg['desc']??''); ?></td><td class="r"></td><td class="r"><?php echo fv((float)($cg['amount']??0)); ?></td><td class="c">VND</td></tr><?php endforeach; ?>
      <!-- 4.2 -->
      <tr class="rsub"><td class="tstt">4.2</td><td class="i1 b">Chi phí bán hàng (kinh doanh)</td><td style="font-size:8pt;">Tuân thủ theo bảng phân bổ tùy thị trường</td><td class="r"><?php echo number_format($fin_sales_pct+$fin_presales_pct+$fin_mkt_pct,2); ?>%</td><td class="r b"><?php echo fv($fin_sales_total); ?></td><td class="c">VND</td></tr>
      <tr><td class="tstt">4.2.1</td><td class="i2"><?php echo htmlspecialchars($fin_saved['r421_name']??'Sales Commission'); ?></td><td style="font-size:8pt;color:#64748b;">% doanh thu</td><td class="r"><?php echo number_format($fin_sales_pct,2); ?> %</td><td class="r"><?php echo fv($fin_sales_comm); ?></td><td class="c">VND</td></tr>
      <tr><td class="tstt">4.2.2</td><td class="i2"><?php echo htmlspecialchars($fin_saved['r422_name']??'Presales Commission'); ?></td><td style="font-size:8pt;color:#64748b;">% doanh thu</td><td class="r"><?php echo number_format($fin_presales_pct,2); ?> %</td><td class="r"><?php echo fv($fin_presales_comm); ?></td><td class="c">VND</td></tr>
      <tr><td class="tstt">4.2.3</td><td class="i2"><?php echo htmlspecialchars($fin_saved['r423_name']??'MKT Commission'); ?></td><td style="font-size:8pt;color:#64748b;">% doanh thu</td><td class="r"><?php echo number_format($fin_mkt_pct,2); ?> %</td><td class="r"><?php echo fv($fin_mkt_comm); ?></td><td class="c">VND</td></tr>
      <?php if($r424_val>0||$r424_name): ?><tr><td class="tstt">4.2.4</td><td class="i2"><?php echo htmlspecialchars($r424_name); ?></td><td><?php echo htmlspecialchars($r424_desc); ?></td><td class="r"></td><td class="r"><?php echo fv($r424_val); ?></td><td class="c">VND</td></tr><?php endif; ?>
      <!-- 4.3 -->
      <tr class="rsub"><td class="tstt">4.3</td><td class="i1 b">Chi phí quản lý + back office</td><td style="font-size:8pt;"><?php echo number_format($fin_mgmt_pct,2); ?>% doanh thu thuần - Tuân thủ bảng phân bổ</td><td class="r"><?php echo number_format($fin_mgmt_pct,2); ?>%</td><td class="r b"><?php echo fv($fin_mgmt); ?></td><td class="c">VND</td></tr>
      <!-- 4.4 -->
      <tr class="rsub"><td class="tstt">4.4</td><td class="i1 b">Chi phí khác</td><td></td><td class="r"></td><td class="r b"><?php echo fv($fin_other_cost); ?></td><td class="c">VND</td></tr>
      <?php foreach($other_keys as $num=>$oi): ?><tr><td class="tstt"><?php echo $num; ?></td><td class="i2"><?php echo htmlspecialchars($oi['name']); ?></td><td><?php echo htmlspecialchars($oi['desc']); ?></td><td class="r"></td><td class="r"><?php echo fv($oi['val']); ?></td><td class="c">VND</td></tr><?php endforeach; ?>
      <!-- 5 -->
      <tr class="rgross"><td class="tstt b <?php echo $fin_gp<0?'red':'blue'; ?>">5</td><td class="b">Lợi nhuận gộp (margin)</td><td style="font-size:8.5pt;">= Doanh thu thuần − Tổng chi phí</td><td class="r b <?php echo $fin_gp<0?'red':'blue'; ?>"><?php echo number_format($fin_margin,2); ?>%</td><td class="r b <?php echo $fin_gp<0?'red':'blue'; ?>"><?php echo fv($fin_gp); ?></td><td class="c">VND</td></tr>
    </tbody>
  </table>
  <!-- Stamp -->
  <?php if(($pakd['status']??'')==='approved'&&!empty($pakd['approved_by_name'])): ?>
  <div class="stamp-sec">
    <div class="stamp-sig">
      <div class="stamp-sl">Phê duyệt bởi</div>
      <div class="stamp-sn"><?php echo htmlspecialchars($pakd['approved_by_name']); ?></div>
      <?php if(!empty($pakd['approved_at'])): ?><div class="stamp-sd"><?php echo date('d/m/Y H:i',strtotime($pakd['approved_at'])); ?></div><?php endif; ?>
    </div>
    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" width="130" height="130" opacity="0.85" style="transform:rotate(-8deg);overflow:visible;flex-shrink:0;">
      <defs>
        <path id="<?php echo $sid; ?>_t" d="M 35,100 A 65,65 0 0,1 165,100"/>
        <path id="<?php echo $sid; ?>_b" d="M 165,100 A 65,65 0 0,1 35,100"/>
      </defs>
      <path d="<?php echo $scallopD; ?>" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round"/>
      <circle cx="100" cy="100" r="75" fill="none" stroke="#2563eb" stroke-width="1.2" stroke-dasharray="2.8,3.2"/>
      <text font-size="10" fill="#2563eb" font-weight="700" font-family="Inter,Arial,sans-serif" letter-spacing="2"><textPath href="#<?php echo $sid; ?>_t" startOffset="50%" text-anchor="middle">ĐÃ PHÊ DUYỆT</textPath></text>
      <text font-size="10" fill="#2563eb" font-weight="700" font-family="Inter,Arial,sans-serif" letter-spacing="2"><textPath href="#<?php echo $sid; ?>_b" startOffset="50%" text-anchor="middle">AUTHORIZED</textPath></text>
      <line x1="26" y1="88" x2="174" y2="88" stroke="#2563eb" stroke-width="1.8"/>
      <line x1="26" y1="114" x2="174" y2="114" stroke="#2563eb" stroke-width="1.8"/>
      <text x="36" y="106" text-anchor="middle" font-size="10" fill="#2563eb">★</text>
      <text x="164" y="106" text-anchor="middle" font-size="10" fill="#2563eb">★</text>
      <text x="100" y="108" text-anchor="middle" font-size="17" font-weight="800" fill="#2563eb" letter-spacing="1.5" font-family="Inter,Arial,sans-serif">APPROVED</text>
    </svg>
  </div>
  <?php endif; ?>
  <!-- Footer -->
  <div class="docfooter">
    <span>AHT KPI System · Phương án Kinh doanh #<?php echo $pakd_id; ?></span>
    <span>In lúc: <?php echo date('d/m/Y H:i'); ?></span>
    <span>Tài liệu nội bộ — Bảo mật</span>
  </div>
</div>
<script>
window.addEventListener('load',function(){setTimeout(function(){window.print();},700);});
</script>
</body>
</html>
