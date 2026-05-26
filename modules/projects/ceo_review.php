<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'user';

// ── Access control: admin hoặc trong danh sách CEO Approver ──────────────────
$isCeoApprover = ($role === 'admin');
if (!$isCeoApprover) {
    $caRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='pasx_ceo_approvers' LIMIT 1");
    if ($caRes && $caRow = $caRes->fetch_assoc()) {
        $isCeoApprover = in_array($userId, array_map('intval', json_decode($caRow['setting_value'] ?? '[]', true) ?: []));
    }
}
if (!$isCeoApprover) { header('Location: /dashboard'); exit; }

// ── Helper: load SMTP settings ───────────────────────────────────────────────
function loadSmtp($conn) {
    $s = [];
    $r = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
    if ($r) while ($row = $r->fetch_assoc()) $s[$row['setting_key']] = $row['setting_value'];
    return $s;
}

// ── Helper: gọi Profile API approve/reject ───────────────────────────────────
function callProfileApi($conn, $endpoint, $body) {
    $cfgFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($cfgFile)) return [false, 'Chưa cấu hình ArrowHitech API'];
    $cfg = json_decode(file_get_contents($cfgFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token']       ?? '';
    if (!$api_url || !$api_token) return [false, 'Thiếu URL hoặc Token'];

    $ts  = (int)(microtime(true) * 1000);
    $rid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

    $ch = curl_init($api_url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['X-API-Key: '.$api_token, 'X-Timestamp: '.$ts, 'X-Request-Id: '.$rid, 'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)            return [false, 'Lỗi kết nối: '.$err];
    if ($code >= 200 && $code < 300) return [true, 'OK'];
    $e = json_decode($resp, true);
    $m = $e['message'] ?? $e['error'] ?? ('HTTP '.$code);
    return [false, 'API lỗi: '.(is_array($m) ? json_encode($m, JSON_UNESCAPED_UNICODE) : $m)];
}

// ── AJAX: CEO Approve ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ceo_approve') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID không hợp lệ']); exit; }

    $pr = $conn->prepare("SELECT opportunity_name, am_name, odoo_opp_id, pasx_id FROM pakd WHERE id=? LIMIT 1");
    $pr->bind_param("i", $pid); $pr->execute();
    $pk = $pr->get_result()->fetch_assoc(); $pr->close();

    $body = [
        'message' => 'PASX đã được CEO phê duyệt. Sale/AM/BD sẽ chuyển báo giá cho khách hàng.',
        'oppName' => $pk['opportunity_name'] ?? null,
        'amName'  => $pk['am_name']          ?? null,
        'oppId'   => $pk['odoo_opp_id']      ?? null,
        'pasxId'  => $pk['pasx_id']          ?? null,
    ];
    [$ok, $msg] = callProfileApi($conn, '/integrations/os/pakd/'.$pid.'/approve', $body);
    if ($ok) {
        $st = $conn->prepare("UPDATE pakd SET status='approved', pasx_status='approved' WHERE id=?");
        $st->bind_param("i", $pid); $st->execute(); $st->close();
        echo json_encode(['ok'=>true, 'msg'=>'Đã approve thành công']);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>$msg]);
    }
    exit;
}

// ── AJAX: CEO Reject ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ceo_reject') {
    header('Content-Type: application/json; charset=utf-8');
    $pid    = (int)($_POST['id']     ?? 0);
    $reason = trim($_POST['reason']  ?? '');
    if (!$pid)    { echo json_encode(['ok'=>false,'msg'=>'ID không hợp lệ']); exit; }
    if (!$reason) { echo json_encode(['ok'=>false,'msg'=>'Vui lòng nhập lý do']); exit; }

    [$ok, $msg] = callProfileApi($conn, '/integrations/os/pakd/'.$pid.'/reject', ['reason' => $reason]);
    if ($ok) {
        $st = $conn->prepare("UPDATE pakd SET pasx_status='rejected' WHERE id=?");
        $st->bind_param("i", $pid); $st->execute(); $st->close();
        echo json_encode(['ok'=>true, 'msg'=>'Đã từ chối PASX']);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>$msg]);
    }
    exit;
}

// ── Load danh sách PAKD đang chờ CEO duyệt ──────────────────────────────────
$pendingList = [];
$res = $conn->query(
    "SELECT id, name, opportunity_name, company_name, am_name, department,
            division_names, opp_value, currency, revenue, gross_profit,
            pasx_id, pasx_status, updated_at, created_at
     FROM pakd WHERE pasx_status = 'pending_ceo'
     ORDER BY updated_at DESC"
);
if ($res) while ($row = $res->fetch_assoc()) $pendingList[] = $row;

function fmtVND($n) {
    if ($n >= 1e9) return number_format($n/1e9,1).'B';
    if ($n >= 1e6) return number_format($n/1e6,1).'M';
    if ($n >= 1e3) return number_format($n/1e3,0).'K';
    return number_format($n,0);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Review — AHT KPI</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#6366f1; --success:#16a34a; --warning:#d97706; --danger:#dc2626; --border:#e2e8f0; --gray:#64748b; --slate:#1e293b; }
        body { background:#f8fafc; font-family:'Inter',sans-serif; color:var(--slate); margin:0; }
        .main-content { flex:1; padding:28px; min-height:100vh; }

        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:12px; }
        .page-header-left { display:flex; align-items:center; gap:14px; }
        .page-icon { width:48px; height:48px; background:linear-gradient(135deg,#d97706,#b45309); border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-size:20px; box-shadow:0 4px 12px rgba(217,119,6,.3); }
        .page-title h1 { font-size:22px; font-weight:700; margin:0 0 3px; }
        .page-title p  { font-size:13px; color:var(--gray); margin:0; }

        /* Empty state */
        .empty-box { background:white; border-radius:16px; border:1px solid var(--border); padding:64px 32px; text-align:center; }
        .empty-box .ei { width:72px; height:72px; border-radius:50%; background:#fef3c7; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:30px; color:#d97706; }
        .empty-box h3  { font-size:18px; font-weight:700; margin:0 0 8px; }
        .empty-box p   { font-size:13px; color:var(--gray); margin:0; }

        /* Cards grid */
        .review-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(480px,1fr)); gap:18px; }

        .review-card {
            background:white; border-radius:14px; border:1px solid var(--border);
            box-shadow:0 2px 8px rgba(0,0,0,.06); overflow:hidden;
            transition:box-shadow .2s, transform .2s;
        }
        .review-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.1); transform:translateY(-2px); }

        .rc-header {
            padding:16px 20px 12px;
            border-bottom:1px solid #f1f5f9;
            display:flex; align-items:flex-start; gap:12px;
        }
        .rc-badge { width:36px; height:36px; border-radius:9px; background:#fef3c7; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:15px; flex-shrink:0; }
        .rc-title { font-size:.92rem; font-weight:700; color:var(--slate); margin-bottom:3px; }
        .rc-company { font-size:.78rem; color:var(--gray); }
        .rc-time { margin-left:auto; font-size:.72rem; color:#94a3b8; white-space:nowrap; }

        .rc-body { padding:14px 20px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
        .rc-stat { }
        .rc-stat-lbl { font-size:.7rem; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3px; }
        .rc-stat-val { font-size:.9rem; font-weight:700; color:var(--slate); }
        .rc-stat-val.amber  { color:#d97706; }
        .rc-stat-val.green  { color:var(--success); }
        .rc-stat-val.red    { color:var(--danger); }

        .rc-footer {
            padding:12px 20px; background:#f8fafc; border-top:1px solid #f1f5f9;
            display:flex; align-items:center; gap:8px;
        }
        .rc-link { font-size:.78rem; color:var(--primary); text-decoration:none; display:inline-flex; align-items:center; gap:4px; font-weight:500; margin-right:auto; }
        .rc-link:hover { text-decoration:underline; }

        .btn-approve {
            display:inline-flex; align-items:center; gap:6px;
            padding:7px 16px; border:none; border-radius:7px;
            background:#16a34a; color:white; font-size:.82rem; font-weight:600;
            cursor:pointer; font-family:inherit; transition:background .15s;
        }
        .btn-approve:hover { background:#15803d; }
        .btn-approve:disabled { opacity:.6; cursor:not-allowed; }

        .btn-reject {
            display:inline-flex; align-items:center; gap:6px;
            padding:7px 14px; border:1px solid #fecaca; border-radius:7px;
            background:white; color:#dc2626; font-size:.82rem; font-weight:600;
            cursor:pointer; font-family:inherit; transition:all .15s;
        }
        .btn-reject:hover { background:#fef2f2; border-color:#dc2626; }

        /* Toast */
        .toast { position:fixed; top:20px; right:20px; z-index:9999; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; color:white; display:flex; align-items:center; gap:8px; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:toastIn .3s ease; }
        .toast.success { background:#16a34a; }
        .toast.error   { background:#dc2626; }
        @keyframes toastIn { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }

        /* Reject dialog */
        .dialog-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:2000; display:flex; align-items:center; justify-content:center; }
        .dialog-box { background:#fff; border-radius:12px; width:440px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden; }
        .dialog-header { padding:18px 20px 14px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; }
        .dialog-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dialog-title { font-weight:700; color:#0f172a; font-size:.95rem; }
        .dialog-sub   { font-size:.78rem; color:#64748b; margin-top:2px; }
        .dialog-body  { padding:16px 20px; }
        .dialog-footer { padding:0 20px 18px; display:flex; justify-content:flex-end; gap:8px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php $page_title = 'CEO Review — PASX'; include __DIR__ . '/../includes/topbar.php'; ?>

        <div class="page-header">
            <div class="page-header-left">
                <div class="page-icon"><i class="fas fa-user-tie"></i></div>
                <div class="page-title">
                    <h1>CEO Review</h1>
                    <p>Danh sách PASX cần CEO phê duyệt · <?= count($pendingList) ?> đang chờ</p>
                </div>
            </div>
        </div>

        <?php if (empty($pendingList)): ?>
        <div class="empty-box">
            <div class="ei"><i class="fas fa-check-circle"></i></div>
            <h3>Không có PASX nào đang chờ</h3>
            <p>Tất cả PASX đã được xử lý. Khi AM gửi yêu cầu, chúng sẽ xuất hiện ở đây.</p>
        </div>
        <?php else: ?>
        <div class="review-grid" id="reviewGrid">
            <?php foreach ($pendingList as $p):
                $margin = ($p['revenue'] > 0) ? round($p['gross_profit'] / $p['revenue'] * 100, 1) : 0;
                $marginCls = $margin >= 20 ? 'green' : ($margin >= 10 ? 'amber' : 'red');
                $timeAgo = !empty($p['updated_at']) ? date('d/m/Y H:i', strtotime($p['updated_at'])) : '—';
            ?>
            <div class="review-card" id="card-<?= $p['id'] ?>">
                <div class="rc-header">
                    <div class="rc-badge"><i class="fas fa-file-contract"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div class="rc-title" title="<?= htmlspecialchars($p['opportunity_name'] ?: $p['name']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($p['opportunity_name'] ?: $p['name'], 0, 60, '…')) ?>
                        </div>
                        <div class="rc-company"><?= htmlspecialchars($p['company_name'] ?: '—') ?></div>
                    </div>
                    <div class="rc-time"><i class="fas fa-clock" style="margin-right:3px;"></i><?= $timeAgo ?></div>
                </div>

                <div class="rc-body">
                    <div class="rc-stat">
                        <div class="rc-stat-lbl">AM / Sales</div>
                        <div class="rc-stat-val"><?= htmlspecialchars($p['am_name'] ?: '—') ?></div>
                    </div>
                    <div class="rc-stat">
                        <div class="rc-stat-lbl">Doanh thu</div>
                        <div class="rc-stat-val"><?= fmtVND($p['revenue'] ?: $p['opp_value']) ?> <?= htmlspecialchars($p['currency'] ?: 'VND') ?></div>
                    </div>
                    <div class="rc-stat">
                        <div class="rc-stat-lbl">Margin</div>
                        <div class="rc-stat-val <?= $marginCls ?>"><?= $margin ?>%</div>
                    </div>
                    <div class="rc-stat">
                        <div class="rc-stat-lbl">Bộ phận</div>
                        <div class="rc-stat-val" style="font-size:.82rem;"><?= htmlspecialchars($p['department'] ?: '—') ?></div>
                    </div>
                    <div class="rc-stat">
                        <div class="rc-stat-lbl">Division</div>
                        <div class="rc-stat-val" style="font-size:.78rem;color:var(--gray);"><?= htmlspecialchars($p['division_names'] ?: '—') ?></div>
                    </div>
                    <div class="rc-stat">
                        <div class="rc-stat-lbl">PASX ID</div>
                        <div class="rc-stat-val" style="font-size:.75rem;color:#94a3b8;font-family:monospace;"><?= htmlspecialchars(substr($p['pasx_id'] ?? '—', 0, 12)).'…' ?></div>
                    </div>
                </div>

                <div class="rc-footer">
                    <a href="/projects/pakd/edit?id=<?= $p['id'] ?>" class="rc-link" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Xem chi tiết
                    </a>
                    <button class="btn-reject" onclick="openRejectDialog(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['opportunity_name'] ?: $p['name'])) ?>')">
                        <i class="fas fa-times"></i> Từ chối
                    </button>
                    <button class="btn-approve" id="approve-btn-<?= $p['id'] ?>" onclick="doApprove(<?= $p['id'] ?>)">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject dialog -->
<div class="dialog-overlay" id="rejectDialog" style="display:none;">
    <div class="dialog-box">
        <div class="dialog-header">
            <div class="dialog-icon" style="background:#fef2f2;"><i class="fas fa-times" style="color:#dc2626;font-size:14px;"></i></div>
            <div>
                <div class="dialog-title">Từ chối PASX</div>
                <div class="dialog-sub" id="rejectDialogSub"></div>
            </div>
        </div>
        <div class="dialog-body">
            <label style="display:block;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                Lý do từ chối <span style="color:#dc2626;">*</span>
            </label>
            <textarea id="rejectReason" rows="3" placeholder="Nhập lý do cụ thể để bên Profile biết cần điều chỉnh gì..."
                style="width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:9px 11px;font-size:.85rem;font-family:inherit;color:#0f172a;resize:vertical;outline:none;box-sizing:border-box;"></textarea>
            <div id="rejectErr" style="display:none;color:#dc2626;font-size:.75rem;margin-top:4px;"><i class="fas fa-exclamation-circle"></i> Vui lòng nhập lý do</div>
        </div>
        <div class="dialog-footer">
            <button onclick="closeRejectDialog()" style="padding:7px 16px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;color:#64748b;font-size:.85rem;cursor:pointer;font-family:inherit;">Huỷ</button>
            <button id="rejectConfirmBtn" onclick="submitReject()"
                style="padding:7px 16px;border:none;border-radius:6px;background:#dc2626;color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:inherit;">
                <i class="fas fa-times"></i> Xác nhận từ chối
            </button>
        </div>
    </div>
</div>

<script>
let _rejectId = null;

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'toast ' + (type || 'success');
    t.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = 0; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 3000);
}

function doApprove(id) {
    const btn = document.getElementById('approve-btn-' + id);
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';

    fetch('/projects/ceo-review', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'ceo_approve', id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.msg, 'success');
            const card = document.getElementById('card-' + id);
            if (card) {
                card.style.transition = 'opacity .4s, transform .4s';
                card.style.opacity = 0; card.style.transform = 'scale(.96)';
                setTimeout(() => card.remove(), 400);
            }
        } else {
            showToast(data.msg, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Approve';
        }
    })
    .catch(() => { showToast('Lỗi kết nối', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Approve'; });
}

function openRejectDialog(id, name) {
    _rejectId = id;
    document.getElementById('rejectDialogSub').textContent = name;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectErr').style.display = 'none';
    document.getElementById('rejectDialog').style.display = 'flex';
    setTimeout(() => document.getElementById('rejectReason').focus(), 80);
}
function closeRejectDialog() {
    document.getElementById('rejectDialog').style.display = 'none';
    _rejectId = null;
}

function submitReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { document.getElementById('rejectErr').style.display = 'block'; return; }
    document.getElementById('rejectErr').style.display = 'none';

    const btn = document.getElementById('rejectConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

    fetch('/projects/ceo-review', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'ceo_reject', id: _rejectId, reason })
    })
    .then(r => r.json())
    .then(data => {
        closeRejectDialog();
        if (data.ok) {
            showToast(data.msg, 'success');
            const card = document.getElementById('card-' + _rejectId);
            if (card) { card.style.opacity = 0; card.style.transition = 'opacity .4s'; setTimeout(() => card.remove(), 400); }
        } else {
            showToast(data.msg, 'error');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-times"></i> Xác nhận từ chối';
    })
    .catch(() => { closeRejectDialog(); showToast('Lỗi kết nối', 'error'); });
}

// Close on backdrop click
document.getElementById('rejectDialog').addEventListener('click', function(e) {
    if (e.target === this) closeRejectDialog();
});
</script>
</body>
</html>
