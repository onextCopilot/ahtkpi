<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$is_admin = ($_SESSION['role'] === 'admin');
$uid = intval($_SESSION['user_id']);

require_once __DIR__ . '/migrate.php';

// ── Check if current user is a Core/Key member ───────────────────────────
$chk_member = $conn->prepare("SELECT id FROM core_kpi_members WHERE user_id=? AND is_active=1");
$chk_member->bind_param("i", $uid); $chk_member->execute();
$is_member = ($chk_member->get_result()->num_rows > 0);

// ── URL params ───────────────────────────────────────────
$tab       = $_GET['tab']   ?? 'dashboard';
$sel_cycle = intval($_GET['cycle'] ?? 0);
$sel_month = intval($_GET['month'] ?? date('n'));
$sel_year  = intval($_GET['year']  ?? date('Y'));

// Non-admin members can only see their own data
if (!$is_admin && $is_member) {
    $sel_user = $uid;   // force to self
} else {
    $sel_user = intval($_GET['emp'] ?? 0);
}

$msg_ok = $msg_err = '';
if (isset($_SESSION['ck_ok']))  { $msg_ok  = $_SESSION['ck_ok'];  unset($_SESSION['ck_ok']); }
if (isset($_SESSION['ck_err'])) { $msg_err = $_SESSION['ck_err']; unset($_SESSION['ck_err']); }

// ── Global lookups ───────────────────────────────────────
// Members = users that are in core_kpi_members
$members = [];
$r = $conn->query("
    SELECT u.id, u.full_name, u.email, u.job_title, u.avatar,
           u.join_date, u.status as user_status,
           d.name as dept_name,
           m.is_active, m.id as member_id, m.member_type
    FROM core_kpi_members m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE m.is_active = 1
    ORDER BY m.member_type, u.full_name
");
if ($r) while ($row = $r->fetch_assoc()) $members[] = $row;

// For non-admin members: restrict visible members list to self only
if (!$is_admin && $is_member) {
    $members = array_values(array_filter($members, fn($m) => $m['id'] == $uid));
}

// All system users not yet members (for dropdown when adding — admin only)
$non_members = [];
if ($is_admin) {
    $existing_member_uids = array_column($members, 'id');
    // Re-fetch full members to get all (admin sees all)
    $all_members_ids_r = $conn->query("SELECT user_id FROM core_kpi_members WHERE is_active=1");
    $all_mids = []; if ($all_members_ids_r) while ($row = $all_members_ids_r->fetch_assoc()) $all_mids[] = $row['user_id'];
    $r2 = $conn->query("SELECT id, full_name, email, job_title FROM users ORDER BY full_name");
    if ($r2) while ($row = $r2->fetch_assoc()) {
        if (!in_array($row['id'], $all_mids)) $non_members[] = $row;
    }
}

// Cycles
$cycles = [];
$r = $conn->query("SELECT * FROM core_kpi_cycles ORDER BY year DESC, quarter ASC");
if ($r) while ($row = $r->fetch_assoc()) $cycles[] = $row;

// Auto-select active cycle if none selected
if (!$sel_cycle) {
    foreach ($cycles as $c) { if ($c['status'] === 'active') { $sel_cycle = $c['id']; break; } }
    if (!$sel_cycle && !empty($cycles)) $sel_cycle = $cycles[0]['id'];
}
$cur_cycle = null;
foreach ($cycles as $c) { if ($c['id'] == $sel_cycle) { $cur_cycle = $c; break; } }

// KPI definitions
$kpi_defs = [];
$r = $conn->query("SELECT * FROM core_kpi_definitions WHERE is_active=1 ORDER BY category, sort_order, kpi_name");
if ($r) while ($row = $r->fetch_assoc()) $kpi_defs[] = $row;

// ── POST Handler ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Add member from system user ---
    if ($action === 'add_member' && $is_admin) {
        $pick_uid = intval($_POST['pick_user_id'] ?? 0);
        $mtype = $_POST['member_type'] ?? 'Key';
        if ($pick_uid > 0) {
            $stmt = $conn->prepare("INSERT INTO core_kpi_members (user_id, member_type, added_by, is_active) 
                                   VALUES (?,?,?,1) 
                                   ON DUPLICATE KEY UPDATE member_type=VALUES(member_type), is_active=1, added_by=VALUES(added_by)");
            $stmt->bind_param("isi", $pick_uid, $mtype, $uid);
            $stmt->execute() ? $_SESSION['ck_ok'] = "Đã thêm vào nhóm $mtype Member!" : $_SESSION['ck_err'] = $conn->error;
        }
        header("Location: ?tab=employees&cycle=$sel_cycle"); exit();
    }

    // --- Remove member ---
    if ($action === 'del_member' && $is_admin) {
        $mid = intval($_POST['member_id']);
        $stmt = $conn->prepare("UPDATE core_kpi_members SET is_active=0 WHERE id=?");
        $stmt->bind_param("i", $mid);
        $stmt->execute() ? $_SESSION['ck_ok'] = "Đã xoá khỏi nhóm!" : $_SESSION['ck_err'] = $conn->error;
        header("Location: ?tab=employees&cycle=$sel_cycle"); exit();
    }

    // --- Cycle CRUD ---
    if ($action === 'add_cycle' && $is_admin) {
        $cn  = trim($_POST['name'] ?? '');
        $cy  = intval($_POST['year'] ?? date('Y'));
        $cq  = !empty($_POST['quarter']) ? intval($_POST['quarter']) : null;
        $csd = $_POST['start_date'] ?? null; $ced = $_POST['end_date'] ?? null;
        $cst = $_POST['status'] ?? 'planning';
        $stmt = $conn->prepare("INSERT INTO core_kpi_cycles (name,year,quarter,start_date,end_date,status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sissss", $cn, $cy, $cq, $csd, $ced, $cst);
        $stmt->execute() ? $_SESSION['ck_ok'] = "Đã thêm chu kỳ!" : $_SESSION['ck_err'] = $conn->error;
    }
    if ($action === 'edit_cycle' && $is_admin) {
        $cid=$intid=$stmt=null; $cid=intval($_POST['id']); $cn=trim($_POST['name']??''); $cy=intval($_POST['year']??date('Y'));
        $cq=!empty($_POST['quarter'])?intval($_POST['quarter']):null; $csd=$_POST['start_date']??null; $ced=$_POST['end_date']??null; $cst=$_POST['status']??'planning';
        $stmt=$conn->prepare("UPDATE core_kpi_cycles SET name=?,year=?,quarter=?,start_date=?,end_date=?,status=? WHERE id=?");
        $stmt->bind_param("sissssi",$cn,$cy,$cq,$csd,$ced,$cst,$cid);
        $stmt->execute() ? $_SESSION['ck_ok']="Đã cập nhật chu kỳ!" : $_SESSION['ck_err']=$conn->error;
    }

    // --- KPI Definition CRUD ---
    if ($action === 'add_kpidef' && $is_admin) {
        $cat=trim($_POST['category']??'General'); $kn=trim($_POST['kpi_name']??'');
        $desc=trim($_POST['description']??''); $unit=trim($_POST['default_unit']??''); $ct=$_POST['calc_type']??'maximize';
        if ($kn) { $stmt=$conn->prepare("INSERT INTO core_kpi_definitions (category,kpi_name,description,default_unit,calc_type) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss",$cat,$kn,$desc,$unit,$ct);
            $stmt->execute() ? $_SESSION['ck_ok']="Đã thêm KPI!" : $_SESSION['ck_err']=$conn->error; }
    }
    if ($action === 'edit_kpidef' && $is_admin) {
        $did=intval($_POST['id']); $cat=trim($_POST['category']??'General'); $kn=trim($_POST['kpi_name']??'');
        $desc=trim($_POST['description']??''); $unit=trim($_POST['default_unit']??''); $ct=$_POST['calc_type']??'maximize';
        $stmt=$conn->prepare("UPDATE core_kpi_definitions SET category=?,kpi_name=?,description=?,default_unit=?,calc_type=? WHERE id=?");
        $stmt->bind_param("sssssi",$cat,$kn,$desc,$unit,$ct,$did);
        $stmt->execute() ? $_SESSION['ck_ok']="Đã cập nhật!" : $_SESSION['ck_err']=$conn->error;
    }
    if ($action === 'del_kpidef' && $is_admin) {
        $did=intval($_POST['id']); $stmt=$conn->prepare("UPDATE core_kpi_definitions SET is_active=0 WHERE id=?");
        $stmt->bind_param("i",$did); $stmt->execute() ? $_SESSION['ck_ok']="Đã ẩn!" : $_SESSION['ck_err']=$conn->error;
    }

    // --- Assign KPI to user ---
    if ($action === 'save_assign' && $is_admin) {
        $tuid=intval($_POST['user_id']); $defids=$_POST['kpi_def_ids']??[]; $cyc=intval($_POST['cycle_id']);
        if(isset($_POST['kpi_def_id'])) $defids[] = $_POST['kpi_def_id']; // Backward compat if needed
        $target=$_POST['target_value']!==''?floatval($_POST['target_value']):null;
        $unit=trim($_POST['unit']??''); $weight=floatval($_POST['weight']??1); $note=trim($_POST['notes']??'');
        $success=0; $total=count($defids);
        foreach($defids as $defid) {
            $defid=intval($defid);
            $stmt=$conn->prepare("INSERT INTO core_kpi_assignments (user_id,kpi_def_id,cycle_id,target_value,unit,weight,notes,created_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_value=VALUES(target_value),unit=VALUES(unit),weight=VALUES(weight),notes=VALUES(notes)");
            $stmt->bind_param("iiidsdsi",$tuid,$defid,$cyc,$target,$unit,$weight,$note,$uid);
            if($stmt->execute()) $success++;
        }
        if($success>0) $_SESSION['ck_ok']="Đã gán $success/$total KPI thành công!";
        else $_SESSION['ck_err']="Không thể gán KPI: " . $conn->error;
    }
    if ($action === 'del_assign' && $is_admin) {
        $aid=intval($_POST['id']); $stmt=$conn->prepare("DELETE FROM core_kpi_assignments WHERE id=?");
        $stmt->bind_param("i",$aid); $stmt->execute() ? $_SESSION['ck_ok']="Đã xoá!" : $_SESSION['ck_err']=$conn->error;
    }

    // --- Toggle Lock (Admin only) ---
    if ($action === 'toggle_lock' && $is_admin) {
        header('Content-Type: application/json');
        $aid = intval($_POST['assignment_id'] ?? 0);
        $yr  = intval($_POST['year'] ?? 0);
        $mo  = intval($_POST['month'] ?? 0);
        $lock = intval($_POST['lock'] ?? 0);
        if ($aid > 0 && $yr > 0 && $mo > 0) {
            $stmt = $conn->prepare("INSERT INTO core_kpi_results (assignment_id, year, month, is_locked, updated_by) 
                                   VALUES (?,?,?,?,?) 
                                   ON DUPLICATE KEY UPDATE is_locked=VALUES(is_locked), updated_by=VALUES(updated_by)");
            $stmt->bind_param("iiiii", $aid, $yr, $mo, $lock, $uid);
            if($stmt->execute()) echo json_encode(['status'=>'ok','locked'=>$lock]);
            else echo json_encode(['status'=>'error','message'=>$conn->error]);
        }
        exit();
    }

    // --- Save result AJAX ---
    if ($action === 'save_result_ajax') {
        header('Content-Type: application/json');
        $aid=intval($_POST['assignment_id']??0); $yr=intval($_POST['year']??0); $mo=intval($_POST['month']??0);
        $own = $conn->prepare("SELECT a.user_id, r.is_locked FROM core_kpi_assignments a LEFT JOIN core_kpi_results r ON a.id=r.assignment_id AND r.year=? AND r.month=? WHERE a.id=?");
        $own->bind_param("iii",$yr,$mo,$aid); $own->execute();
        $own_row = $own->get_result()->fetch_assoc();
        
        $is_locked = $own_row['is_locked']??0;
        $can_save = $is_admin || ($own_row && intval($own_row['user_id']) === $uid && !$is_locked);
        
        if (!$can_save) {
            $msg = $is_locked ? 'Bản ghi đã bị khoá bởi Admin' : 'No permission';
            echo json_encode(['status'=>'error','message'=>$msg]); exit();
        }
        if ($is_admin) {
            $utarg = $_POST['target_value']!==''?floatval($_POST['target_value']):null;
            $uunit = trim($_POST['unit']??'');
            $uweight = $_POST['weight']!==''?floatval($_POST['weight']):1;
            $upstmt = $conn->prepare("UPDATE core_kpi_assignments SET target_value=?, unit=?, weight=? WHERE id=?");
            $upstmt->bind_param("dsdi", $utarg, $uunit, $uweight, $aid);
            $upstmt->execute();
        }
        $actual=$_POST['actual_value']!==''?floatval($_POST['actual_value']):null;
        $score=$_POST['score']!==''?floatval($_POST['score']):null; $note=trim($_POST['note']??'');
        $ach=null;
        if ($actual!==null) {
            $ar=$conn->prepare("SELECT a.target_value,d.calc_type FROM core_kpi_assignments a JOIN core_kpi_definitions d ON a.kpi_def_id=d.id WHERE a.id=?");
            $ar->bind_param("i",$aid); $ar->execute(); $ar=$ar->get_result()->fetch_assoc();
            if ($ar && $ar['target_value']>0) {
                $ach=$ar['calc_type']==='minimize'?round((2-$actual/$ar['target_value'])*100,2):round(($actual/$ar['target_value'])*100,2);
            }
        }
        $stmt=$conn->prepare("INSERT INTO core_kpi_results (assignment_id,year,month,actual_value,achievement_pct,score,note,updated_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE actual_value=VALUES(actual_value),achievement_pct=VALUES(achievement_pct),score=VALUES(score),note=VALUES(note),updated_by=VALUES(updated_by)");
        $stmt->bind_param("iiidddsi",$aid,$yr,$mo,$actual,$ach,$score,$note,$uid);
        if($stmt->execute()) {
            echo json_encode(['status'=>'ok','achievement'=>round($ach??0,1)]);
        } else {
            echo json_encode(['status'=>'error','message'=>$conn->error]);
        }
        exit();
    }

    // --- Save result (chỉ admin hoặc chính user sở hữu mới được lưu) ---
    if ($action === 'save_result') {
        $aid=intval($_POST['assignment_id']); $yr=intval($_POST['year']); $mo=intval($_POST['month']);
        // Verify ownership and lock
        $own = $conn->prepare("SELECT a.user_id, r.is_locked FROM core_kpi_assignments a LEFT JOIN core_kpi_results r ON a.id=r.assignment_id AND r.year=? AND r.month=? WHERE a.id=?");
        $own->bind_param("iii",$yr,$mo,$aid); $own->execute();
        $own_row = $own->get_result()->fetch_assoc();
        
        $is_locked = $own_row['is_locked']??0;
        $can_save = $is_admin || ($own_row && intval($own_row['user_id']) === $uid && !$is_locked);
        
        if (!$can_save) {
            $_SESSION['ck_err'] = $is_locked ? "Bản ghi đã bị khoá bởi Admin!" : "Bạn không có quyền cập nhật KPI này!";
        } else {
            // Update assignment fields if admin (inline edit)
            if ($is_admin) {
                $utarg = $_POST['target_value']!==''?floatval($_POST['target_value']):null;
                $uunit = trim($_POST['unit']??'');
                $uweight = $_POST['weight']!==''?floatval($_POST['weight']):1;
                $upstmt = $conn->prepare("UPDATE core_kpi_assignments SET target_value=?, unit=?, weight=? WHERE id=?");
                $upstmt->bind_param("dsdi", $utarg, $uunit, $uweight, $aid);
                $upstmt->execute();
            }
            $actual=$_POST['actual_value']!==''?floatval($_POST['actual_value']):null;
            $score=$_POST['score']!==''?floatval($_POST['score']):null; $note=trim($_POST['note']??'');
            $ach=null;
            if ($actual!==null) {
                $ar=$conn->prepare("SELECT a.target_value,d.calc_type FROM core_kpi_assignments a JOIN core_kpi_definitions d ON a.kpi_def_id=d.id WHERE a.id=?");
                $ar->bind_param("i",$aid); $ar->execute(); $ar=$ar->get_result()->fetch_assoc();
                if ($ar && $ar['target_value']>0) {
                    $ach=$ar['calc_type']==='minimize'?round((2-$actual/$ar['target_value'])*100,2):round(($actual/$ar['target_value'])*100,2);
                }
            }
            $stmt=$conn->prepare("INSERT INTO core_kpi_results (assignment_id,year,month,actual_value,achievement_pct,score,note,updated_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE actual_value=VALUES(actual_value),achievement_pct=VALUES(achievement_pct),score=VALUES(score),note=VALUES(note),updated_by=VALUES(updated_by)");
            $stmt->bind_param("iiidddsi",$aid,$yr,$mo,$actual,$ach,$score,$note,$uid);
            $stmt->execute() ? $_SESSION['ck_ok']="Đã lưu!" : $_SESSION['ck_err']=$conn->error;
        }
    }

    // --- Review (Admin can edit all, User can only edit their own comment_emp) ---
    if ($action === 'save_review') {
        $ruid=intval($_POST['user_id']); $cyc=intval($_POST['cycle_id']);
        $can_edit = $is_admin || ($ruid === $uid);
        if (!$can_edit) {
            $_SESSION['ck_err'] = "Bạn không có quyền đánh giá user này!";
        } else {
            if ($is_admin) {
                // Admin full update
                $os=$_POST['overall_score']!==''?floatval($_POST['overall_score']):null;
                $rating=$_POST['rating']??null; $cmgr=trim($_POST['comment_mgr']??''); $cemp=trim($_POST['comment_emp']??''); $st=$_POST['status']??'draft';
                $ra=($st==='approved')?date('Y-m-d H:i:s'):null;
                $stmt=$conn->prepare("INSERT INTO core_kpi_reviews (user_id,cycle_id,overall_score,rating,comment_mgr,comment_emp,status,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE overall_score=VALUES(overall_score),rating=VALUES(rating),comment_mgr=VALUES(comment_mgr),comment_emp=VALUES(comment_emp),status=VALUES(status),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at)");
                $stmt->bind_param("iidssssis",$ruid,$cyc,$os,$rating,$cmgr,$cemp,$st,$uid,$ra);
                $stmt->execute() ? $_SESSION['ck_ok']="Đã lưu đánh giá!" : $_SESSION['ck_err']=$conn->error;
            } else {
                // Member update only their own comment
                $cemp=trim($_POST['comment_emp']??'');
                $stmt=$conn->prepare("INSERT INTO core_kpi_reviews (user_id,cycle_id,comment_emp) VALUES (?,?,?) ON DUPLICATE KEY UPDATE comment_emp=VALUES(comment_emp)");
                $stmt->bind_param("iis",$ruid,$cyc,$cemp);
                $stmt->execute() ? $_SESSION['ck_ok']="Đã gửi phản hồi!" : $_SESSION['ck_err']=$conn->error;
            }
        }
    }

    header("Location: ?tab=" . urlencode($tab) . "&cycle=$sel_cycle&emp=$sel_user&month=$sel_month"); exit();
}

// ── Data for display ─────────────────────────────────────
// Assignments for current cycle
$assignments = [];
if ($sel_cycle) {
    $q = "SELECT a.*, u.full_name, u.job_title, u.avatar, m.member_type, d.kpi_name, d.category, d.calc_type, d.description def_desc
          FROM core_kpi_assignments a
          JOIN users u ON a.user_id = u.id
          JOIN core_kpi_members m ON a.user_id = m.user_id AND m.is_active = 1
          JOIN core_kpi_definitions d ON a.kpi_def_id = d.id
          WHERE a.cycle_id=$sel_cycle " . ($sel_user ? "AND a.user_id=$sel_user" : "") . "
          ORDER BY m.member_type, u.full_name, d.category, d.kpi_name";
    $r = $conn->query($q);
    if ($r) while ($row = $r->fetch_assoc()) $assignments[] = $row;
}

// Results map
$results_map = [];
if ($sel_cycle && $cur_cycle) {
    $cy_year = $cur_cycle['year'];
    $r = $conn->query("SELECT * FROM core_kpi_results WHERE year=$cy_year");
    if ($r) while ($row = $r->fetch_assoc()) $results_map[$row['assignment_id']][$row['month']] = $row;
}

// Reviews map
$reviews_map = [];
if ($sel_cycle) {
    $r = $conn->query("SELECT rv.*, u.full_name reviewer_name FROM core_kpi_reviews rv LEFT JOIN users u ON rv.reviewed_by=u.id WHERE rv.cycle_id=$sel_cycle");
    if ($r) while ($row = $r->fetch_assoc()) $reviews_map[$row['user_id']] = $row;
}

// Dashboard stats per member
$dash_stats = [];
foreach ($members as $m) {
    $muid = $m['id'];
    $cy_year = $cur_cycle['year'] ?? date('Y');
    $r = $conn->query("SELECT a.id, a.weight FROM core_kpi_assignments a WHERE a.cycle_id=$sel_cycle AND a.user_id=$muid");
    $mAssigns = []; if ($r) while ($row = $r->fetch_assoc()) $mAssigns[] = $row;
    $total_w = array_sum(array_column($mAssigns,'weight'));
    $weighted_score = 0; $filled = 0;
    foreach ($mAssigns as $as) {
        $lat = $conn->query("SELECT score FROM core_kpi_results WHERE assignment_id={$as['id']} AND year=$cy_year AND score IS NOT NULL ORDER BY month DESC LIMIT 1")->fetch_assoc();
        if ($lat) { $weighted_score += ($lat['score'] * $as['weight']); $filled++; }
    }
    $overall = ($total_w > 0 && $filled > 0) ? round($weighted_score / $total_w, 1) : null;
    $dash_stats[$muid] = ['count' => count($mAssigns), 'score' => $overall, 'review' => $reviews_map[$muid] ?? null, 'total_weight' => $total_w];
}

// ── Yearly Summary data ──────────────────────────────────
$yearly_scores = [];
if ($tab === 'yearly') {
    $r = $conn->query("SELECT a.user_id, r.month, r.score, a.weight 
                       FROM core_kpi_results r JOIN core_kpi_assignments a ON r.assignment_id = a.id 
                       WHERE r.year = $sel_year AND r.score IS NOT NULL");
    $raw = [];
    if($r) while($row = $r->fetch_assoc()){
        $raw[$row['user_id']][$row['month']][] = $row;
    }
    foreach($raw as $uid_key => $months_data){
        foreach($months_data as $mo_key => $items){
            $tw = array_sum(array_column($items, 'weight'));
            $ws = 0; foreach($items as $i) $ws += ($i['score'] * $i['weight']);
            $yearly_scores[$uid_key][$mo_key] = ($tw > 0) ? round($ws / $tw, 1) : 0;
        }
    }
}

// Helpers
$status_badge = ['planning'=>['#E0F2FE','#0369A1','Lên kế hoạch'],'active'=>['#DCFCE7','#15803D','Đang chạy'],'reviewing'=>['#FEF9C3','#A16207','Đang đánh giá'],'closed'=>['#F3F4F6','#6B7280','Đã kết thúc']];
$rating_color = ['A'=>'#22c55e','B+'=>'#84cc16','B'=>'#3b82f6','C+'=>'#f59e0b','C'=>'#ef4444','D'=>'#dc2626'];
$months_vi = ['','T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

include __DIR__ . '/view_header.php';
include __DIR__ . '/view_tabs.php';
include __DIR__ . '/view_modals.php';
