<?php
/**
 * Import ứng viên - wizard: upload -> map cột -> preview -> commit (có chống trùng).
 * Route: /hrm/candidates/import
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$sources = $conn->query("SELECT id,name FROM hrm_candidate_sources WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$events  = $conn->query("SELECT id,name FROM hrm_events WHERE active=1 ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Các field đích + từ khóa để auto-đoán mapping.
$fields = [
    'full_name'        => ['Họ tên *', ['tên','họ tên','ho ten','full name','name','ứng viên','candidate']],
    'email'            => ['Email', ['email','e-mail','thư']],
    'phone'            => ['Điện thoại', ['số điện thoại','điện thoại','dien thoai','phone','sđt','sdt','mobile']],
    'current_position' => ['Vị trí gần nhất', ['công việc gần nhất','vị trí gần nhất','chức danh','position','title']],
    'skills'           => ['Kỹ năng', ['kỹ năng','ky nang','skill']],
    'years_exp'        => ['Số năm KN', ['năm kinh nghiệm','kinh nghiệm','experience','years','exp']],
    'location'         => ['Khu vực', ['khu vực','địa điểm','location','city','tỉnh']],
    'expected_salary'  => ['Lương kỳ vọng', ['lương','salary','mong muốn']],
    'languages'        => ['Ngôn ngữ', ['ngôn ngữ','language','ngoại ngữ']],
    'dob'              => ['Ngày sinh', ['ngày tháng năm sinh','ngày sinh','dob','birth']],
    'gender'           => ['Giới tính', ['giới tính','gender','sex']],
    'id_card'          => ['Số CMND/CCCD', ['số cmt','cmt','cccd','cmnd','id card','căn cước']],
    'score'            => ['Điểm', ['điểm','score']],
    'classification'   => ['Phân loại', ['phân loại','classification']],
    'campaign'         => ['Chiến dịch', ['chiến dịch','campaign','medium']],
    'tags'             => ['Thẻ', ['thẻ','tag']],
    'job_code'         => ['Mã tin tuyển dụng (gắn vào tin)', ['mã tin tuyển dụng','mã tin','job code','mã job']],
    'applied_job'      => ['Vị trí ứng tuyển (gốc)', ['tên tin tuyển dụng','vị trí ứng tuyển','applied job']],
    'applied_stage'    => ['Giai đoạn (gốc)', ['giai đoạn','stage']],
    'applied_date'     => ['Ngày ứng tuyển', ['ngày ứng tuyển','applied date']],
    'office_text'      => ['Văn phòng', ['văn phòng','office']],
    'reject_reason'    => ['Lý do từ chối', ['lý do từ chối','thông tin từ chối','reject reason','reject']],
    'cv_path'          => ['Đường dẫn CV', ['đường dẫn cv','link cv','cv url','cv link','đường dẫn']],
    'external_id'      => ['ID ngoài (Base)', ['id']],
    'linkedin_url'     => ['LinkedIn', ['linkedin']],
    'notes'            => ['Ghi chú', ['ghi chú','note','mô tả']],
];

hrm_header('Import ứng viên', 'Nhập hàng loạt từ Excel / CSV', 'candidates');
?>
<div class="rc-toolbar"><a href="/hrm/candidates" class="rc-tab">← Kho ứng viên</a><div></div></div>

<!-- BƯỚC 1: upload -->
<div class="rc-card" id="step1">
    <h3 style="font-size:15px;margin-bottom:8px">Bước 1 · Chọn file</h3>
    <div class="rc-muted" style="margin-bottom:12px">Hỗ trợ .xlsx và .csv. Dòng đầu (có ≥2 ô) được coi là tiêu đề cột. Tối đa 2000 dòng/lần.</div>
    <form id="upForm" onsubmit="return false">
        <div class="rc-field"><input type="file" name="file" accept=".xlsx,.csv" required></div>
        <div id="upErr" class="rc-muted" style="color:#dc2626"></div>
        <button class="rc-btn" id="upBtn" onclick="parseFile()">Đọc file →</button>
    </form>
</div>

<!-- BƯỚC 2: mapping + options -->
<div class="rc-card" id="step2" style="display:none">
    <h3 style="font-size:15px;margin-bottom:8px">Bước 2 · Gán cột (<span id="rowCount">0</span> dòng)</h3>
    <div class="rc-muted" style="margin-bottom:12px">Hệ thống đã tự đoán; chỉnh lại nếu cần. Bắt buộc gán <b>Họ tên</b>.</div>
    <div class="imp-grid" id="mapGrid"></div>

    <div class="rc-grid2" style="margin-top:16px;border-top:1px solid #f1f5f9;padding-top:14px">
        <div class="rc-field"><label>Nguồn mặc định (áp cho mọi dòng)</label>
            <select id="defSource"><option value="0">- Không -</option>
                <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
        <div class="rc-field"><label>Sự kiện mặc định</label>
            <select id="defEvent"><option value="0">- Không -</option>
                <?php foreach ($events as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="rc-field"><label>Khi trùng (email/SĐT đã có)</label>
        <select id="mode"><option value="skip">Bỏ qua dòng trùng</option><option value="update">Cập nhật hồ sơ đã có</option><option value="create">Vẫn tạo mới</option></select></div>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#334155;margin:4px 0 4px">
        <input type="checkbox" id="dlCv" checked> Tải CV từ link về server (lưu bản sao + tạo tệp đính kèm)</label>

    <h4 style="font-size:13px;margin:14px 0 8px">Xem trước (5 dòng đầu)</h4>
    <div style="overflow-x:auto"><table class="rc-table" id="preview" style="white-space:nowrap"></table></div>

    <!-- Progress -->
    <div id="progWrap" style="display:none;margin-top:14px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;color:#475569;margin-bottom:5px">
            <span id="progLabel">Đang import...</span><span id="progPct">0%</span></div>
        <div style="height:10px;background:#eef2f6;border-radius:99px;overflow:hidden"><div id="progBar" style="height:100%;width:0;background:linear-gradient(135deg,#0e9f6e,#057a55);transition:width .2s"></div></div>
    </div>

    <div id="commitErr" class="rc-muted" style="color:#dc2626;margin-top:10px"></div>
    <div style="display:flex;gap:8px;margin-top:12px">
        <button class="rc-btn ghost" onclick="location.reload()">Chọn file khác</button>
        <button class="rc-btn" id="commitBtn" onclick="commit()">Import ngay</button>
    </div>
</div>

<!-- KẾT QUẢ -->
<div class="rc-card" id="step3" style="display:none">
    <h3 style="font-size:15px;margin-bottom:8px">Hoàn tất</h3>
    <div id="result" style="font-size:14px"></div>
    <div style="margin-top:14px;display:flex;gap:8px"><a class="rc-btn" href="/hrm/candidates">Về kho ứng viên</a><button class="rc-btn ghost" onclick="location.reload()">Import tiếp</button></div>
</div>

<style>
.imp-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.imp-row{display:flex;align-items:center;gap:10px}
.imp-row label{font-size:13px;width:150px;flex-shrink:0;color:#475569}
.imp-row select{flex:1;padding:8px 11px;border:1px solid var(--bd);border-radius:8px;font-size:13px;background:#fff}
#preview th,#preview td{padding:8px 12px;border-bottom:1px solid #f1f5f9;font-size:12.5px;text-align:left}
#preview th{color:#86868b;font-weight:600}
</style>
<script>
const FIELDS = <?= json_encode($fields, JSON_UNESCAPED_UNICODE) ?>;
let HEADERS = [], ROWS = [];

function parseFile(){
    const f=document.getElementById('upForm');
    if(!f.file.files.length){alert('Chọn file');return;}
    const fd=new FormData(f);fd.append('action','cand_import_parse');
    document.getElementById('upBtn').disabled=true;document.getElementById('upErr').textContent='Đang đọc...';
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        document.getElementById('upBtn').disabled=false;
        if(!j.ok){document.getElementById('upErr').textContent=j.error||'Lỗi';return;}
        HEADERS=j.headers;ROWS=j.rows;document.getElementById('upErr').textContent='';
        buildMapping();document.getElementById('step1').style.display='none';document.getElementById('step2').style.display='block';
        document.getElementById('rowCount').textContent=j.total;
    }).catch(()=>{document.getElementById('upBtn').disabled=false;document.getElementById('upErr').textContent='Lỗi kết nối';});
}
function guess(field){
    const kws=FIELDS[field][1];
    const H=HEADERS.map(h=>(h||'').toLowerCase().trim());
    // Ưu tiên khớp CHÍNH XÁC tiêu đề (vd "Tên" != "Tên tin tuyển dụng").
    for(const k of kws){const i=H.indexOf(k);if(i>-1)return i;}
    // Sau đó mới khớp chứa.
    for(let i=0;i<H.length;i++){for(const k of kws){if(H[i].indexOf(k)>-1)return i;}}
    return '';
}
function buildMapping(){
    const g=document.getElementById('mapGrid');g.innerHTML='';
    for(const f in FIELDS){
        const sel=HEADERS.map((h,i)=>'<option value="'+i+'"'+(guess(f)===i?' selected':'')+'>'+escapeHtml(h||('Cột '+(i+1)))+'</option>').join('');
        g.insertAdjacentHTML('beforeend','<div class="imp-row"><label>'+FIELDS[f][0]+'</label><select data-f="'+f+'"><option value="">- Bỏ qua -</option>'+sel+'</select></div>');
    }
    g.querySelectorAll('select').forEach(s=>s.addEventListener('change',renderPreview));
    renderPreview();
}
function currentMap(){const m={};document.querySelectorAll('#mapGrid select').forEach(s=>{if(s.value!=='')m[s.dataset.f]=parseInt(s.value,10);});return m;}
function renderPreview(){
    const m=currentMap();const fs=Object.keys(m);
    let html='<thead><tr>'+fs.map(f=>'<th>'+FIELDS[f][0]+'</th>').join('')+'</tr></thead><tbody>';
    ROWS.slice(0,5).forEach(r=>{html+='<tr>'+fs.map(f=>'<td>'+escapeHtml(String(r[m[f]]??''))+'</td>').join('')+'</tr>';});
    document.getElementById('preview').innerHTML=html+'</tbody>';
}
async function commit(){
    const m=currentMap();
    if(!('full_name' in m)){document.getElementById('commitErr').textContent='Phải gán cột Họ tên';return;}
    const dlCv=document.getElementById('dlCv').checked;
    const mode=document.getElementById('mode').value;
    const defSource=document.getElementById('defSource').value;
    const defEvent=document.getElementById('defEvent').value;
    // Tải CV chậm -> lô nhỏ hơn để feedback mượt và tránh timeout.
    const chunk=dlCv?8:50;
    const total=ROWS.length;
    document.getElementById('commitBtn').disabled=true;
    document.getElementById('commitErr').textContent='';
    document.getElementById('progWrap').style.display='block';
    const sum={inserted:0,updated:0,skipped:0,cv_ok:0,cv_fail:0,linked:0};
    for(let off=0; off<total; off+=chunk){
        const part=ROWS.slice(off,off+chunk);
        const fd=new FormData();fd.append('action','cand_import_commit');
        fd.append('map',JSON.stringify(m));fd.append('rows',JSON.stringify(part));
        fd.append('mode',mode);fd.append('default_source',defSource);fd.append('default_event',defEvent);
        if(dlCv)fd.append('download_cv','1');
        let j;
        try{ j=await (await fetch('/hrm/api',{method:'POST',body:fd})).json(); }
        catch(e){ document.getElementById('commitErr').textContent='Lỗi kết nối ở dòng '+(off+1); document.getElementById('commitBtn').disabled=false; return; }
        if(!j.ok){ document.getElementById('commitErr').textContent=j.error||'Lỗi'; document.getElementById('commitBtn').disabled=false; return; }
        sum.inserted+=j.inserted||0; sum.updated+=j.updated||0; sum.skipped+=j.skipped||0; sum.cv_ok+=j.cv_ok||0; sum.cv_fail+=j.cv_fail||0; sum.linked+=j.linked||0;
        const done=Math.min(off+chunk,total); const pct=Math.round(done/total*100);
        document.getElementById('progBar').style.width=pct+'%';
        document.getElementById('progPct').textContent=pct+'%';
        document.getElementById('progLabel').textContent='Đã xử lý '+done+'/'+total+' dòng'+(dlCv?(' · CV: '+sum.cv_ok):'');
    }
    document.getElementById('step2').style.display='none';document.getElementById('step3').style.display='block';
    let html='✓ Thêm mới: <b>'+sum.inserted+'</b> · Cập nhật: <b>'+sum.updated+'</b> · Bỏ qua (trùng/thiếu tên): <b>'+sum.skipped+'</b>';
    if(dlCv)html+='<br>CV tải về: <b>'+sum.cv_ok+'</b>'+(sum.cv_fail?(' · lỗi tải: <b>'+sum.cv_fail+'</b>'):'');
    if(sum.linked)html+='<br>Gắn vào tin tuyển dụng: <b>'+sum.linked+'</b>';
    document.getElementById('result').innerHTML=html;
}
function escapeHtml(s){return s.replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
</script>
<?php
hrm_footer();
