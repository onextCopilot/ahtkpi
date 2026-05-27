<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id      = $_SESSION['user_id'];
$role         = $_SESSION['role'] ?? 'user';
$my_full_name = $_SESSION['full_name'] ?? '';
$is_admin     = ($role === 'admin');
$pakd_id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── AJAX: Request Production Plan ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
    header('Content-Type: application/json; charset=utf-8');
    $body    = json_decode(file_get_contents('php://input'), true);
    $pid     = (int)($body['pakdId'] ?? 0);

    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'pakdId không hợp lệ']); exit; }

    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API']); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token'] ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình']); exit; }

    $st = $conn->prepare("SELECT id, odoo_opp_id, department, division_names, opportunity_name, am_name, company_name, opp_value, project_type FROM pakd WHERE id=?");
    $st->bind_param("i", $pid);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$p) { echo json_encode(['ok'=>false,'msg'=>'Không tìm thấy PAKD #'.$pid]); exit; }

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $payload = [
        'pakdId'         => $p['id'],
        'oppId'          => $p['odoo_opp_id'],
        'departmentName' => $p['division_names'],
        'saleTeam'       => $p['department'],
        'oppName'        => $p['opportunity_name'],
        'amName'         => $p['am_name'],
        'companyName'    => $p['company_name'],
        'oppValue'       => (float)$p['opp_value'],
        'projectType'    => strtolower($p['project_type'] ?? 'external'),
    ];

    $ch = curl_init($api_url . '/integrations/os/pakd-created');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: '    . $api_token,
            'X-Timestamp: '  . $timestamp,
            'X-Request-Id: ' . $request_id,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err]); exit; }
    if ($http_code >= 200 && $http_code < 300) {
        $res  = json_decode($response, true);
        $data = $res['data'] ?? [];
        $pasx_id     = $data['pasxId']    ?? null;
        $pasx_status = $data['status']    ?? null;
        $idempotent  = !empty($data['idempotent']);

        // Ensure columns exist (ignore duplicate column errors)
        foreach ([
            "ALTER TABLE pakd ADD COLUMN pasx_id VARCHAR(64) DEFAULT NULL",
            "ALTER TABLE pakd ADD COLUMN pasx_status VARCHAR(32) DEFAULT NULL",
            "ALTER TABLE pakd ADD COLUMN pasx_requested_at DATETIME DEFAULT NULL",
        ] as $sql) { try { $conn->query($sql); } catch (Exception $e) {} }

        // Save to DB
        $st2 = $conn->prepare("UPDATE pakd SET pasx_id=?, pasx_status=?, pasx_requested_at=NOW() WHERE id=?");
        $st2->bind_param("ssi", $pasx_id, $pasx_status, $pid);
        $st2->execute();
        $st2->close();

        $msg = $idempotent ? 'Yêu cầu đã tồn tại (idempotent).' : 'Đã gửi yêu cầu Production Plan thành công!';
        echo json_encode(['ok'=>true,'msg'=>$msg,'data'=>$res]);
    } else {
        $err = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        $err_msg = $err_val ?? ('HTTP ' . $http_code);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.$err_msg,'http_code'=>$http_code,'raw'=>$err]);
    }
    exit;
}

// ── AJAX: Save Division ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_division') {
    header('Content-Type: application/json; charset=utf-8');
    $pid      = (int)($_POST['id'] ?? 0);
    $division = trim($_POST['division_names'] ?? '');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']); exit; }
    $st = $conn->prepare("UPDATE pakd SET division_names=? WHERE id=?");
    $st->bind_param("si", $division, $pid);
    $ok = $st->execute();
    $st->close();
    echo json_encode(['ok' => $ok]);
    exit;
}

// ── AJAX: Approve PASX → tạo Project bên Profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_pasx') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID không hợp lệ']); exit; }

    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API']); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token']        ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình']); exit; }

    // Lấy thông tin pakd để gửi kèm
    $pr = $conn->prepare("SELECT opportunity_name, am_name, odoo_opp_id, pasx_id FROM pakd WHERE id=? LIMIT 1");
    $pr->bind_param("i", $pid);
    $pr->execute();
    $pk = $pr->get_result()->fetch_assoc();
    $pr->close();

    $body = [
        'message'  => 'Cảm ơn, PASX đã được approve, Sale/AM/BD sẽ chuyển báo giá cho khách hàng. Vui lòng chờ.. ',
        'oppName'  => $pk['opportunity_name'] ?? null,
        'amName'   => $pk['am_name']          ?? null,
        'oppId'    => $pk['odoo_opp_id']      ?? null,
        'pasxId'   => $pk['pasx_id']          ?? null,
    ];

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $ch = curl_init($api_url . '/integrations/os/pakd/' . $pid . '/approve');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: '    . $api_token,
            'X-Timestamp: '  . $timestamp,
            'X-Request-Id: ' . $request_id,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err]); exit; }

    if ($http_code >= 200 && $http_code < 300) {
        // Cập nhật cả status và pasx_status = approved trong DB
        $st = $conn->prepare("UPDATE pakd SET status='approved', pasx_status='approved' WHERE id=?");
        $st->bind_param("i", $pid);
        $st->execute();
        $st->close();
        echo json_encode(['ok'=>true,'msg'=>'Approve thành công — Profile đang tạo Project']);
    } else {
        $err     = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.($err_val ?? 'HTTP '.$http_code)]);
    }
    exit;
}

// ── AJAX: Yêu cầu CEO duyệt PASX ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_ceo_approve') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID không hợp lệ']); exit; }

    $ceo_message = trim($_POST['message'] ?? '');

    // Đảm bảo cột message tồn tại (compatible MySQL 5.7+)
    try { $conn->query("ALTER TABLE pasx_notifications ADD COLUMN message TEXT DEFAULT NULL"); } catch (\Throwable $e) {}

    // Cập nhật DB: status=pending, pasx_status=pending_ceo
    $st = $conn->prepare("UPDATE pakd SET status='pending', pasx_status='pending_ceo' WHERE id=?");
    $st->bind_param("i", $pid);
    $ok = $st->execute();
    $st->close();

    // Lấy danh sách CEO Approver từ config (fallback: admin role)
    try {
        // Đọc CEO Approvers từ DB (system_settings)
        $ceoIds = [];
        $caRes2 = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'pasx_ceo_approvers' LIMIT 1");
        if ($caRes2 && $caRow = $caRes2->fetch_assoc()) {
            $ceoIds = array_values(array_filter(array_map('intval', json_decode($caRow['setting_value'] ?? '[]', true) ?: [])));
        }

        if ($ceoIds) {
            $inList = implode(',', $ceoIds);
            $admins = $conn->query("SELECT id FROM users WHERE id IN ($inList)");
        } else {
            // Fallback: tất cả admin nếu chưa cấu hình
            $admins = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 10");
        }

        $pr2 = $conn->prepare("SELECT opportunity_name, am_name, pasx_id FROM pakd WHERE id=? LIMIT 1");
        $pr2->bind_param("i", $pid);
        $pr2->execute();
        $pk2 = $pr2->get_result()->fetch_assoc();
        $pr2->close();

        if ($admins && $pk2) {
            $ni = $conn->prepare("INSERT IGNORE INTO pasx_notifications
                (user_id, pakd_id, pasx_id, event, status, opp_name, submitted_by, message)
                VALUES (?, ?, ?, 'ceo_approve_request', 'pending_ceo', ?, ?, ?)");
            while ($adm = $admins->fetch_assoc()) {
                $ni->bind_param("iissss", $adm['id'], $pid, $pk2['pasx_id'], $pk2['opportunity_name'], $pk2['am_name'], $ceo_message);
                $ni->execute();
            }
            $ni->close();
        }
    } catch (\Throwable $e) {}

    // ── Gửi email cho từng CEO Approver ──────────────────────────────────────
    if ($ok) {
        try {
            require_once __DIR__ . '/../includes/SimpleMailer.php';
            $smtp = [];
            $smtpRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
            if ($smtpRes) while ($sr = $smtpRes->fetch_assoc()) $smtp[$sr['setting_key']] = $sr['setting_value'];

            if (!empty($smtp['smtp_host']) && !empty($smtp['smtp_user']) && !empty($smtp['smtp_pass'])) {
                // Lấy thông tin pakd + margin
                $pe = $conn->prepare("SELECT opportunity_name, company_name, am_name, revenue, gross_profit FROM pakd WHERE id=? LIMIT 1");
                $pe->bind_param("i", $pid); $pe->execute();
                $pkInfo = $pe->get_result()->fetch_assoc(); $pe->close();
                $margin = ($pkInfo['revenue'] > 0) ? round($pkInfo['gross_profit'] / $pkInfo['revenue'] * 100, 1) : 0;
                $detailUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/projects/pakd/edit?id=' . $pid;
                $reviewUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/projects/ceo-review';

                // Lấy email của từng CEO Approver
                if ($ceoIds) {
                    $inStr = implode(',', $ceoIds);
                    $emailRes = $conn->query("SELECT full_name, email FROM users WHERE id IN ($inStr) AND email IS NOT NULL AND email != ''");
                } else {
                    $emailRes = $conn->query("SELECT full_name, email FROM users WHERE role='admin' AND email IS NOT NULL AND email != '' LIMIT 10");
                }

                $mailer = new SimpleMailer(
                    $smtp['smtp_host'],
                    (int)($smtp['smtp_port'] ?? 587),
                    $smtp['smtp_user'],
                    $smtp['smtp_pass']
                );
                $fromName   = $smtp['smtp_from_name'] ?? 'AHT KPI System';
                $subject    = '[Cần phê duyệt] PASX – ' . ($pkInfo['opportunity_name'] ?? 'PAKD #'.$pid);
                $marginColor = $margin >= 20 ? '#16a34a' : ($margin >= 10 ? '#d97706' : '#dc2626');
                $marginBg    = $margin >= 20 ? '#f0fdf4' : ($margin >= 10 ? '#fffbeb' : '#fef2f2');
                $oppName     = htmlspecialchars($pkInfo['opportunity_name'] ?? '—');
                $companyName = htmlspecialchars($pkInfo['company_name']     ?? '—');
                $amName      = htmlspecialchars($pkInfo['am_name']          ?? '—');
                $reqDate     = date('d/m/Y H:i');
                $msgBlock    = '';
                if (!empty($ceo_message)) {
                    $msgEsc   = nl2br(htmlspecialchars($ceo_message));
                    $msgBlock = '
        <!-- AM message -->
        <div style="margin-bottom:24px;">
          <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">Ghi chú từ AM</div>
          <div style="background:#fffbeb;border:1px solid #fde68a;border-left:3px solid #d97706;border-radius:8px;padding:14px 16px;font-size:13px;color:#78350f;line-height:1.65;">'.$msgEsc.'</div>
        </div>';
                }

                $emailBody = '<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%;">

      <!-- Header -->
      <tr><td style="background:#b45309;border-radius:12px 12px 0 0;padding:32px 36px 28px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td>
              <div style="display:inline-block;background:rgba(255,255,255,.18);border-radius:10px;padding:8px 12px;margin-bottom:16px;">
                <span style="color:white;font-size:13px;font-weight:700;letter-spacing:.04em;">AHT OS SYSTEM</span>
              </div>
              <div style="color:white;font-size:22px;font-weight:700;line-height:1.3;margin-bottom:6px;">
                Yêu cầu phê duyệt PASX
              </div>
              <div style="color:rgba(255,255,255,.8);font-size:13px;line-height:1.5;">
                AM <strong style="color:white;">'.$amName.'</strong> đã gửi Phương án sản xuất lên để CEO xem xét và phê duyệt đặc biệt.
              </div>
            </td>
            <td width="64" valign="top" style="padding-left:16px;">
              <div style="width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:50%;text-align:center;line-height:56px;font-size:24px;">
                &#128203;
              </div>
            </td>
          </tr>
        </table>
      </td></tr>

      <!-- Alert banner -->
      <tr><td style="background:#92400e;padding:10px 36px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="color:#fef3c7;font-size:12px;font-weight:600;letter-spacing:.03em;">
              &#9888;&#65039; Margin dưới 20% — Cần CEO phê duyệt trước khi AM gửi báo giá cho khách hàng
            </td>
          </tr>
        </table>
      </td></tr>

      <!-- Body -->
      <tr><td style="background:white;padding:32px 36px;">

        <!-- Opportunity info -->
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;">Thông tin Opportunity</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:24px;">
          <tr style="background:#f8fafc;">
            <td style="padding:12px 16px;font-size:12px;color:#64748b;font-weight:600;width:160px;border-bottom:1px solid #e2e8f0;">Opportunity</td>
            <td style="padding:12px 16px;font-size:13px;color:#0f172a;font-weight:700;border-bottom:1px solid #e2e8f0;">'.$oppName.'</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:12px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">Khách hàng</td>
            <td style="padding:12px 16px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;">'.$companyName.'</td>
          </tr>
          <tr style="background:#f8fafc;">
            <td style="padding:12px 16px;font-size:12px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">AM / Sales</td>
            <td style="padding:12px 16px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;">'.$amName.'</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:12px;color:#64748b;font-weight:600;">Ngày gửi</td>
            <td style="padding:12px 16px;font-size:13px;color:#1e293b;">'.$reqDate.'</td>
          </tr>
        </table>

        '.$msgBlock.'

        <!-- Margin highlight -->
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;">Chỉ số tài chính</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
          <tr>
            <td width="50%" style="padding-right:8px;">
              <div style="background:'.$marginBg.';border:1px solid '.$marginColor.'33;border-radius:10px;padding:16px 20px;text-align:center;">
                <div style="font-size:11px;color:'.$marginColor.';font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Gross Margin</div>
                <div style="font-size:28px;font-weight:800;color:'.$marginColor.';line-height:1;">'.$margin.'%</div>
                <div style="font-size:11px;color:'.$marginColor.';opacity:.7;margin-top:4px;">'.($margin < 20 ? 'Dưới ngưỡng tự approve' : 'Đạt ngưỡng').'</div>
              </div>
            </td>
            <td width="50%" style="padding-left:8px;">
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;text-align:center;">
                <div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Doanh thu</div>
                <div style="font-size:20px;font-weight:800;color:#1e293b;line-height:1;">'.number_format($pkInfo['revenue'],0,',','.').'</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">VND</div>
              </div>
            </td>
          </tr>
        </table>

        <!-- CTA -->
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td align="center" style="padding-bottom:12px;">
              <a href="'.$reviewUrl.'" style="display:inline-block;padding:14px 32px;background:#d97706;color:#ffffff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:.02em;">
                ✓ Xem &amp; Phê duyệt PASX
              </a>
            </td>
          </tr>
          <tr>
            <td align="center">
              <a href="'.$detailUrl.'" style="display:inline-block;padding:10px 24px;background:white;color:#475569;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
                Xem chi tiết PAKD
              </a>
            </td>
          </tr>
        </table>

      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#1e293b;border-radius:0 0 12px 12px;padding:20px 36px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="color:#94a3b8;font-size:11px;line-height:1.6;">
              <strong style="color:#cbd5e1;">AHT OS System</strong> · ArrowHitech<br>
              Email này được gửi tự động — vui lòng không reply trực tiếp.<br>
              Để hủy nhận thông báo, liên hệ quản trị viên hệ thống.
            </td>
            <td align="right" valign="middle" style="padding-left:16px;">
              <div style="width:36px;height:36px;background:rgba(255,255,255,.08);border-radius:8px;text-align:center;line-height:36px;font-size:16px;">&#128312;</div>
            </td>
          </tr>
        </table>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>';

                if ($emailRes) {
                    while ($eu = $emailRes->fetch_assoc()) {
                        if ($eu['email']) $mailer->send($eu['email'], $subject, $emailBody, $fromName);
                    }
                }
            }
        } catch (\Throwable $e) {} // Email thất bại không ảnh hưởng response
    }

    echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Đã gửi yêu cầu lên CEO' : 'Lỗi cập nhật DB']);
    exit;
}

// ── AJAX: Reject PASX ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_pasx') {
    header('Content-Type: application/json; charset=utf-8');
    $pid    = (int)($_POST['id']     ?? 0);
    $reason = trim($_POST['reason']  ?? '');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']); exit; }

    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API']); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token']        ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình']); exit; }

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $ch = curl_init($api_url . '/integrations/os/pakd/' . $pid . '/reject');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['reason' => $reason]),
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: '    . $api_token,
            'X-Timestamp: '  . $timestamp,
            'X-Request-Id: ' . $request_id,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err]); exit; }

    if ($http_code >= 200 && $http_code < 300) {
        // Cập nhật pasx_status = rejected trong DB
        $st = $conn->prepare("UPDATE pakd SET pasx_status='rejected' WHERE id=?");
        $st->bind_param("i", $pid);
        $st->execute();
        $st->close();
        echo json_encode(['ok'=>true,'msg'=>'Đã gửi yêu cầu Reject / Rebuild PASX thành công']);
    } else {
        $err     = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.($err_val ?? 'HTTP '.$http_code)]);
    }
    exit;
}

// ── AJAX: PASX History ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_pasx_history') {
    header('Content-Type: application/json; charset=utf-8');
    $pid    = (int)($_POST['pakd_id'] ?? 0);
    $opp_id = trim($_POST['opp_id'] ?? '');

    // Đảm bảo cột opp_id tồn tại
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN opp_id VARCHAR(64) DEFAULT NULL AFTER pakd_id"); } catch (\Throwable $e) {}

    // Đảm bảo các cột mới tồn tại
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN submitted_by  VARCHAR(255) DEFAULT NULL AFTER note"); }       catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN submitted_at  DATETIME     DEFAULT NULL AFTER submitted_by"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE pasx_webhook_logs ADD COLUMN meta          JSON         DEFAULT NULL AFTER submitted_at"); } catch (\Throwable $e) {}

    $logs = [];
    if ($pid || $opp_id) {
        $stmt = $conn->prepare(
            "SELECT id, pakd_id, opp_id, pasx_id, event, status, payload,
                    http_status, note, submitted_by, submitted_at, received_at
             FROM pasx_webhook_logs
             WHERE pakd_id = ? OR opp_id = ?
             ORDER BY received_at DESC LIMIT 50"
        );
        $stmt->bind_param("is", $pid, $opp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // Parse payload để lấy humanCost, overtimeCost, pasxCost, _meta
            $pl = !empty($row['payload']) ? json_decode($row['payload'], true) : [];
            $row['humanCost']    = $pl['humanCost']    ?? null;
            $row['overtimeCost'] = $pl['overtimeCost'] ?? null;
            $row['pasxCost']     = (isset($pl['pasxCost']) && is_array($pl['pasxCost'])) ? $pl['pasxCost'] : null;
            // submittedBy: ưu tiên cột DB, fallback payload _meta
            if (empty($row['submitted_by']) && !empty($pl['_meta']['submittedBy']['fullName'])) {
                $row['submitted_by'] = $pl['_meta']['submittedBy']['fullName'];
            }
            if (empty($row['submitted_at']) && !empty($pl['_meta']['submittedAt'])) {
                $row['submitted_at'] = $pl['_meta']['submittedAt'];
            }
            unset($row['payload']); // không gửi toàn bộ payload
            $logs[] = $row;
        }
        $stmt->close();
    }
    echo json_encode(['ok' => true, 'logs' => $logs]);
    exit;
}

// ── AJAX: Send Chat Message ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_chat_message') {
    header('Content-Type: application/json; charset=utf-8');
    $pid     = (int)($_POST['id']      ?? 0);
    $message = trim($_POST['message']  ?? '');
    $sender  = $_SESSION['full_name']  ?? $_SESSION['username'] ?? 'AM';

    if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID không hợp lệ']); exit; }
    $has_images = !empty($_FILES['images']['name'][0]) && $_FILES['images']['name'][0] !== '';
    if (!$message && !$has_images) { echo json_encode(['ok'=>false,'msg'=>'Vui lòng nhập tin nhắn hoặc đính kèm ảnh']); exit; }

    // Lấy pasx_id từ DB
    $st = $conn->prepare("SELECT pasx_id FROM pakd WHERE id=? LIMIT 1");
    $st->bind_param("i", $pid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $pasx_id = $row['pasx_id'] ?? null;
    if (!$pasx_id) { echo json_encode(['ok'=>false,'msg'=>'PASX chưa được tạo cho PAKD này. Vui lòng gửi Request Production Plan trước.']); exit; }

    // Upload ảnh
    $image_urls = [];
    if ($has_images) {
        $upload_dir = __DIR__ . '/../../uploads/chat/' . $pid . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $host = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $names  = (array)$_FILES['images']['name'];
        $tmps   = (array)$_FILES['images']['tmp_name'];
        $errors = (array)$_FILES['images']['error'];
        foreach ($tmps as $i => $tmp) {
            if (!$tmp || ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
            $fname = uniqid('chat_') . '.' . $ext;
            if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                $image_urls[] = $host . '/uploads/chat/' . $pid . '/' . $fname;
            }
        }
    }

    // Gọi API ArrowHitech
    $configFile = __DIR__ . '/../../config/arrowhitech_config.json';
    if (!file_exists($configFile)) { echo json_encode(['ok'=>false,'msg'=>'Chưa cấu hình ArrowHitech API','images'=>$image_urls]); exit; }
    $cfg       = json_decode(file_get_contents($configFile), true);
    $api_url   = rtrim($cfg['api_url']   ?? '', '/');
    $api_token = $cfg['api_token']        ?? '';
    if (!$api_url || !$api_token) { echo json_encode(['ok'=>false,'msg'=>'Thiếu URL hoặc Token trong cấu hình','images'=>$image_urls]); exit; }

    $body = ['pasxId'=>$pasx_id,'message'=>$message,'senderName'=>$sender,'pakdId'=>$pid];
    if ($image_urls) $body['images'] = $image_urls;

    $timestamp  = (int)(microtime(true) * 1000);
    $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));

    $ch = curl_init($api_url . '/integrations/os/pasx/' . urlencode($pasx_id) . '/message');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['X-API-Key: '.$api_token,'X-Timestamp: '.$timestamp,'X-Request-Id: '.$request_id,'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) { echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối: '.$curl_err,'images'=>$image_urls]); exit; }

    $api_ok = ($http_code >= 200 && $http_code < 300);

    // Lưu vào DB dù API thành công hay thất bại (để lịch sử đồng bộ)
    $conn->query("CREATE TABLE IF NOT EXISTS pakd_chat_messages (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        pakd_id      INT NOT NULL,
        pasx_id      VARCHAR(64)             DEFAULT NULL,
        direction    ENUM('sent','received') NOT NULL DEFAULT 'sent',
        sender_name  VARCHAR(255)            DEFAULT NULL,
        message      TEXT                    DEFAULT NULL,
        images       JSON                    DEFAULT NULL,
        created_at   DATETIME                DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pakd_time (pakd_id, created_at)
    )");
    $imgs_json = $image_urls ? json_encode($image_urls, JSON_UNESCAPED_UNICODE) : null;
    $ins = $conn->prepare("INSERT INTO pakd_chat_messages (pakd_id, pasx_id, direction, sender_name, message, images) VALUES (?,?,'sent',?,?,?)");
    $ins->bind_param("issss", $pid, $pasx_id, $sender, $message, $imgs_json);
    $ins->execute();
    $msg_id = $conn->insert_id;
    $ins->close();

    if ($api_ok) {
        echo json_encode(['ok'=>true,'msg'=>'Đã gửi tin nhắn','images'=>$image_urls,'pasxId'=>$pasx_id,'id'=>$msg_id]);
    } else {
        $err     = json_decode($response, true);
        $err_val = $err['message'] ?? $err['error'] ?? null;
        if (is_array($err_val)) $err_val = json_encode($err_val, JSON_UNESCAPED_UNICODE);
        echo json_encode(['ok'=>false,'msg'=>'API lỗi: '.($err_val ?? 'HTTP '.$http_code),'images'=>$image_urls,'id'=>$msg_id]);
    }
    exit;
}

// ── AJAX: Get Chat Messages ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_chat_messages') {
    header('Content-Type: application/json; charset=utf-8');
    $pid      = (int)($_POST['id']      ?? 0);
    $after_id = (int)($_POST['after_id'] ?? 0); // polling: chỉ lấy message mới hơn ID này
    if (!$pid) { echo json_encode(['ok'=>false,'msgs',[]]); exit; }

    // Tạo bảng nếu chưa có
    $conn->query("CREATE TABLE IF NOT EXISTS pakd_chat_messages (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        pakd_id      INT NOT NULL,
        pasx_id      VARCHAR(64)             DEFAULT NULL,
        direction    ENUM('sent','received') NOT NULL DEFAULT 'sent',
        sender_name  VARCHAR(255)            DEFAULT NULL,
        message      TEXT                    DEFAULT NULL,
        images       JSON                    DEFAULT NULL,
        created_at   DATETIME                DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pakd_time (pakd_id, created_at)
    )");

    $st = $conn->prepare(
        "SELECT id, direction, sender_name, message, images, created_at
         FROM pakd_chat_messages
         WHERE pakd_id = ? AND id > ?
         ORDER BY created_at ASC, id ASC
         LIMIT 100"
    );
    $st->bind_param("ii", $pid, $after_id);
    $st->execute();
    $res  = $st->get_result();
    $msgs = [];
    while ($row = $res->fetch_assoc()) {
        $row['images'] = !empty($row['images']) ? json_decode($row['images'], true) : [];
        $msgs[] = $row;
    }
    $st->close();
    echo json_encode(['ok'=>true,'msgs'=>$msgs]);
    exit;
}

// ── AJAX: Save Project Type ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_project_type') {
    header('Content-Type: application/json; charset=utf-8');
    $type = strtolower(trim($_POST['project_type'] ?? ''));
    if (!in_array($type, ['internal', 'external'])) { echo json_encode(['ok'=>false,'msg'=>'Giá trị không hợp lệ']); exit; }
    $st = $conn->prepare("UPDATE pakd SET project_type=? WHERE id=?");
    $st->bind_param("si", $type, $pakd_id);
    $ok = $st->execute();
    $st->close();
    echo json_encode(['ok'=>$ok]);
    exit;
}

// ── AJAX Save Handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pakd') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']); exit; }

    // Ensure columns exist before UPDATE
    foreach ([
        "ALTER TABLE pakd ADD COLUMN fin_data    JSON          DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN contract_no VARCHAR(255)  DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN timeline    TEXT          DEFAULT NULL",
    ] as $sql) { try { $conn->query($sql); } catch (Exception $e) {} }

    // Merge fin_data: giữ lại các field từ callback (human_cost, overtime_cost) không bị ghi đè
    $fin_data_post = json_decode($_POST['fin_data'] ?? '{}', true) ?: [];
    $existing_row  = $conn->query("SELECT fin_data FROM pakd WHERE id=$pid")->fetch_assoc();
    $fin_data_db   = !empty($existing_row['fin_data']) ? (json_decode($existing_row['fin_data'], true) ?: []) : [];
    // Các field từ PASX callback - ưu tiên giữ giá trị DB nếu POST không có
    foreach (['human_cost', 'overtime_cost'] as $protected) {
        if (isset($fin_data_db[$protected]) && !isset($fin_data_post[$protected])) {
            $fin_data_post[$protected] = $fin_data_db[$protected];
        }
    }
    $fin_data     = json_encode($fin_data_post);
    $revenue      = (float)($_POST['revenue']      ?? 0);
    $gross_profit = (float)($_POST['gross_profit'] ?? 0);
    $contract_no  = trim($_POST['contract_no']  ?? '');
    $timeline     = trim($_POST['timeline']     ?? '');

    $stmt = $conn->prepare(
        "UPDATE pakd SET fin_data=?, revenue=?, gross_profit=?, contract_no=?, timeline=? WHERE id=?"
    );
    $stmt->bind_param("sddssi", $fin_data, $revenue, $gross_profit, $contract_no, $timeline, $pid);
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'msg' => 'Đã lưu thành công']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Lỗi DB: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// Update schema with missing fields for the UI
try {
    $conn->query("ALTER TABLE pakd ADD COLUMN revenue      DECIMAL(20,2) DEFAULT 0    AFTER opp_value");
    $conn->query("ALTER TABLE pakd ADD COLUMN gross_profit DECIMAL(20,2) DEFAULT 0    AFTER revenue");
    $conn->query("ALTER TABLE pakd ADD COLUMN pasx_value   DECIMAL(20,2) DEFAULT 0    AFTER gross_profit");
    $conn->query("ALTER TABLE pakd ADD COLUMN purchase_order_no VARCHAR(255) DEFAULT NULL AFTER sales_order_no");
    $conn->query("ALTER TABLE pakd ADD COLUMN contract_no  VARCHAR(255)  DEFAULT NULL");
    $conn->query("ALTER TABLE pakd ADD COLUMN timeline     TEXT          DEFAULT NULL");
    $conn->query("ALTER TABLE pakd ADD COLUMN fin_data     JSON          DEFAULT NULL");
} catch (Exception $e) {
    // Ignore duplicate column errors on subsequent loads
}

// Fetch data
$stmt = $conn->prepare("SELECT * FROM pakd WHERE id = ?");
$stmt->bind_param("i", $pakd_id);
$stmt->execute();
$pakd = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Kiểm tra quyền truy cập: AM chỉ xem được PAKD của mình ──
if ($pakd && !$is_admin) {
    $owner_match = (!empty($pakd['am_user_id']) && (int)$pakd['am_user_id'] === $user_id)
                || (!empty($pakd['am_name'])    && $pakd['am_name'] === $my_full_name);
    if (!$owner_match) {
        header('HTTP/1.1 403 Forbidden');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;">
            <h2 style="color:#dc2626;">⛔ Bạn không có quyền xem PAKD này</h2>
            <p style="color:#64748b;">Chỉ AM phụ trách mới có thể truy cập.</p>
            <a href="/projects/phuong-an-kinh-doanh" style="color:#6366f1;">← Quay lại danh sách</a>
        </body></html>';
        exit;
    }
}

// Lấy danh sách divisions để làm dropdown
$division_options = [];
$dres = $conn->query("SELECT DISTINCT division_names FROM pakd WHERE division_names IS NOT NULL AND division_names != '' ORDER BY division_names");
if ($dres) while ($dr = $dres->fetch_row()) $division_options[] = $dr[0];

// Parse saved financial detail data
$fin_saved = !empty($pakd['fin_data']) ? (json_decode($pakd['fin_data'], true) ?? []) : [];

if (!$pakd) {
    // Provide default dummy data if not found so UI can be seen perfectly matching the screenshot
    $pakd = [
        'id' => 0,
        'name' => 'Tư vấn triển khai ERP - Huy Ngoc Phuong',
        'department' => 'BC ITQ',
        'am_name' => 'Nguyễn Thị Huyền Trâm',
        'project_type' => 'Trong công ty',
        'currency' => 'VND',
        'status' => 'approved',
        'opportunity_name' => 'RT ERP - CR điều chỉnh hoá đơn VAT',
        'company_name' => 'CÔNG TY CỔ PHẦN RT HOLDINGS',
        'opp_value' => 56000000,
        'opp_probability' => 100,
        'odoo_url' => '#',
        'contract_no' => '',
        'sales_order_no' => 'S00411',
        'purchase_order_no' => '',
        'timeline' => '',
        'revenue' => 280000000,
        'gross_profit' => 83300000,
        'pasx_value' => 157500000
    ];
}

// Calculate percentages
$pasx_percent = $pakd['revenue'] > 0 ? ($pakd['pasx_value'] / $pakd['revenue']) * 100 : ($pakd['id'] == 0 ? 56.25 : 0);

function formatVND($num) {
    return number_format($num, 0, ',', '.');
}

function pct($val, $base, $dec = 2) {
    if (!$base) return '—';
    return number_format($val / $base * 100, $dec);
}
// % of total cost: always ≥ 0, divides by totalCost not revNet
function pctCost($val, $total, $dec = 2) {
    if (!$total) return '—';
    return number_format(max(0, $val / $total * 100), $dec);
}

// Financial table calculations
$fin_rev_gross    = (float)($pakd['revenue'] ?? 0);
$fin_human_cost   = (float)($fin_saved['human_cost']    ?? 0); // cập nhật từ ArrowHitech callback
$fin_overtime     = (float)($fin_saved['overtime_cost'] ?? 0); // cập nhật từ ArrowHitech callback
$fin_pasx_note    = trim($fin_saved['pasx_note'] ?? '');       // ghi chú từ Profile
// Fallback: đọc resubmitNote từ webhook log mới nhất nếu fin_data chưa có
if ($fin_pasx_note === '' && !empty($pakd['id'])) {
    try {
        $wl = $conn->prepare("SELECT meta FROM pasx_webhook_logs WHERE pakd_id=? AND meta IS NOT NULL ORDER BY id DESC LIMIT 1");
        $wl->bind_param("i", $pakd['id']); $wl->execute();
        $wlRow = $wl->get_result()->fetch_assoc(); $wl->close();
        if ($wlRow) {
            $wlMeta = json_decode($wlRow['meta'], true);
            $fin_pasx_note = trim($wlMeta['resubmitNote'] ?? '');
        }
    } catch (\Throwable $e) {}
}
// Dùng rev_net đã lưu từ JS (doanh thu thuần sau giảm trừ); fallback về revenue gross
$fin_rev_net      = !empty($fin_saved['rev_net']) ? (float)$fin_saved['rev_net'] : $fin_rev_gross;
// Nếu đã nhận được data từ callback thì dùng tổng human+overtime, không thì dùng pasx_value
$pasx_has_data    = ($fin_human_cost > 0 || $fin_overtime > 0);
$fin_prod_cost    = $pasx_has_data ? ($fin_human_cost + $fin_overtime) : (float)($pakd['pasx_value'] ?? 0);
$fin_sales_pct    = (float)($fin_saved['r421_pct'] ?? 2.0);
$fin_presales_pct = (float)($fin_saved['r422_pct'] ?? 0.0);
$fin_mkt_pct      = (float)($fin_saved['r423_pct'] ?? 0.0);
$fin_sales_comm   = (int)round(max(0, $fin_rev_gross) * $fin_sales_pct    / 100);
$fin_presales_comm= (int)round(max(0, $fin_rev_gross) * $fin_presales_pct / 100);
$fin_mkt_comm     = (int)round(max(0, $fin_rev_gross) * $fin_mkt_pct      / 100);
$fin_sales_total  = $fin_sales_comm + $fin_presales_comm + $fin_mkt_comm;
$fin_mgmt_pct     = (float)($fin_saved['r43_pct'] ?? 12.0);
$fin_mgmt         = (int)round(max(0, $fin_rev_net) * $fin_mgmt_pct / 100);
$fin_other_cost   = 0;
$fin_total_cost   = $fin_prod_cost + $fin_sales_total + $fin_mgmt + $fin_other_cost;
// Dùng gross_profit đã lưu từ DB (JS tính đủ tất cả các field nên chính xác hơn)
$fin_gross_profit_db = (float)($pakd['gross_profit'] ?? 0);
$fin_gross_profit    = $fin_gross_profit_db != 0 ? $fin_gross_profit_db : ($fin_rev_net - $fin_total_cost);
$fin_margin_pct      = $fin_rev_net > 0 ? ($fin_gross_profit / $fin_rev_net * 100) : 0;

// ── Kiểm tra user hiện tại có phải CEO Approver không ──
// Chỉ check danh sách pasx_ceo_approvers — không auto-include admin
$isCeoApprover = false;
$caRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='pasx_ceo_approvers' LIMIT 1");
if ($caRes && $caRow = $caRes->fetch_assoc()) {
    $isCeoApprover = in_array($user_id, array_map('intval', json_decode($caRow['setting_value'] ?? '[]', true) ?: []));
}

$statusLabels = [
    'draft' => 'Nháp',
    'pending' => 'Chờ duyệt',
    'approved' => 'AM tiến hành gửi báo giá và xác nhận',
    'rejected' => 'Từ chối'
];
$statusLabel = $statusLabels[$pakd['status']] ?? 'Không xác định';

$statusColors = [
    'draft' => '#64748b',
    'pending' => '#d97706',
    'approved' => '#16a34a',
    'rejected' => '#dc2626'
];
$statusColor = $statusColors[$pakd['status']] ?? '#64748b';
$statusBgColor = [
    'draft' => '#f1f5f9',
    'pending' => '#fef3c7',
    'approved' => '#dcfce7',
    'rejected' => '#fee2e2'
];
$statusBg = $statusBgColor[$pakd['status']] ?? '#f1f5f9';
$statusBorderColor = [
    'draft' => 'rgba(100,116,139,0.2)',
    'pending' => 'rgba(217,119,6,0.3)',
    'approved' => 'rgba(22,163,74,0.3)',
    'rejected' => 'rgba(220,38,38,0.3)'
];
$statusBorder = $statusBorderColor[$pakd['status']] ?? 'rgba(100,116,139,0.2)';
$statusTextColor = [
    'draft' => '#1e293b',
    'pending' => '#92400e',
    'approved' => '#15803d',
    'rejected' => '#991b1b'
];
$statusText = $statusTextColor[$pakd['status']] ?? '#1e293b';
$statusIcon = [
    'draft' => 'fa-file',
    'pending' => 'fa-clock',
    'approved' => 'fa-check-square',
    'rejected' => 'fa-times-circle'
];
$iconClass = $statusIcon[$pakd['status']] ?? 'fa-circle';

// Override status bar khi PASX đang được xử lý
$pasx_done_statuses = ['completed', 'approved', 'cancelled']; // 'rejected' xử lý riêng bên dưới
$pasx_active = !empty($pakd['pasx_id'])
    && !in_array($pakd['pasx_status'] ?? '', $pasx_done_statuses)
    && ($pakd['status'] ?? '') !== 'approved';

if ($pasx_active) {
    if (($pakd['pasx_status'] ?? '') === 'rejected') {
        $statusLabel = 'Đã Reject PASX · Đang chờ Profile rebuild';
        $statusColor = '#b45309'; // amber-700
        $iconClass   = 'fa-clock';
    } elseif (($pakd['pasx_status'] ?? '') === 'pending_ceo') {
        $statusLabel = 'Chờ CEO duyệt · PASX đang chờ phê duyệt';
        $statusColor = '#d97706'; // amber-600
        $iconClass   = 'fa-user-tie';
    } else {
        $statusLabel = 'Đang làm PASX · ' . strtoupper($pakd['pasx_status'] ?? 'CREATED');
        $statusColor = '#7c3aed';
        $iconClass   = 'fa-cog fa-spin';
    }
}

function getProjectTypeIcon($type) {
    return strtolower(trim($type)) === 'external' ? 'fa-desktop' : 'fa-building';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết PAKD - AHT KPI</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --bg: #f8fafc;
            --card: #ffffff;
            --slate: #1e293b;
            --gray: #64748b;
            --lgray: #94a3b8;
            --border: #e2e8f0;
            --r-md: 4px;
        }

        /* Reset gradient from dashboard.css */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            margin: 0; padding: 0;
            display: flex;
            color: var(--slate);
            font-size: 13px;
            line-height: 1.5;
        }

        /* Override dashboard.css main-content to match actual sidebar width */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 80px; }

        /* ── Top Metrics Bar ── */
        /* ── Combined status + metrics bar ── */
        .top-metrics-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 9px 32px;
            background: <?= $statusColor ?>;
            box-shadow: 0 2px 8px <?= $statusColor ?>44;
            font-size: 12.5px;
            color: #fff;
            flex-shrink: 0;
            transition: background .35s, box-shadow .35s;
        }
        .tmb-left  { display: flex; align-items: center; gap: 10px; }
        .tmb-right { display: flex; align-items: center; gap: 22px; }

        /* Status part (left) */
        .sb-icon {
            background: rgba(255,255,255,.2); color: #fff;
            width: 26px; height: 26px; border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .sb-text { display: flex; align-items: center; gap: 7px; font-weight: 600; font-size: 13px; color: #fff; }
        .sb-text .sb-sep { color: rgba(255,255,255,.65); font-weight: 400; }
        .sb-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 2px 8px; background: rgba(255,255,255,.2);
            border-radius: 4px; font-size: 12px; color: #fff; font-weight: 500;
        }

        /* Metrics part (right) */
        .metric-item { display: flex; align-items: center; gap: 0; }
        .metric-item .m-label { color: rgba(255,255,255,.75); margin-right: 5px; font-weight: 400; font-size: 12px; }
        .metric-item strong { font-size: 13px; font-weight: 700; color: #fff; }
        .metric-item .m-unit { font-size: 10px; color: rgba(255,255,255,.7); margin-left: 3px; font-weight: 400; }
        .metric-item .m-pct  { font-size: 11.5px; color: rgba(255,255,255,.8); margin-left: 4px; }
        .tmb-divider { width: 1px; height: 18px; background: rgba(255,255,255,.3); }

        /* ── Detail Container ── */
        .detail-container { padding: 28px 40px 40px; flex: 1; }

        /* ── Page locked (approved) ── */
        .page-locked input,
        .page-locked select,
        .page-locked textarea {
            pointer-events: none !important;
            background: #f8fafc !important;
            color: #64748b !important;
            cursor: default !important;
        }
        .page-locked .btn-add-cr,
        .page-locked .btn-del-cr,
        .page-locked .btn-req-pasx,
        .page-locked .btn-resend-pasx { display: none !important; }
        .page-locked .project-type-select { pointer-events: none !important; opacity: .7; }


        /* ── Actions Row ── */
        .actions-row { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn-outline {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border: 1px solid var(--border); border-radius: var(--r-md);
            background: #fff; color: var(--slate); font-size: 12.5px; font-weight: 500;
            cursor: pointer; text-decoration: none; transition: background 0.15s, border-color 0.15s;
            line-height: 1.4;
        }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-outline.yellow {
            background: #fef9c3; border-color: #fde047; color: #a16207;
        }
        .btn-outline.yellow:hover { background: #fef08a; }

        /* ── Page Title ── */
        .page-title { font-size: 22px; font-weight: 700; color: var(--slate); margin: 0 0 20px 0; letter-spacing: -0.01em; }

        /* ── Metadata Box ── */
        .metadata-box {
            border: 1px solid var(--border); border-radius: var(--r-md);
            background: #ffffff;
            display: grid; grid-template-columns: repeat(6, 1fr);
            padding: 14px 20px; margin-bottom: 20px; gap: 16px;
        }
        .meta-item { display: flex; flex-direction: column; gap: 5px; }
        .meta-label {
            font-size: 10px; font-weight: 700; color: var(--gray);
            text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.2;
        }
        .meta-value {
            font-size: 12.5px; font-weight: 500; color: var(--slate);
            word-break: break-word; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
        }
        .meta-value a {
            color: var(--primary); text-decoration: none;
            display: inline-flex; align-items: center; gap: 3px; font-size: 12px;
        }
        .meta-value a:hover { text-decoration: underline; }

        /* ── Opportunity Row ── */
        .opp-row { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
        .opp-label {
            font-size: 10px; font-weight: 700; color: var(--gray);
            text-transform: uppercase; letter-spacing: 0.06em; width: 96px; flex-shrink: 0;
        }
        .opp-input-group {
            display: flex; align-items: stretch; border: 1px solid var(--border);
            border-radius: var(--r-md); background: #fff; width: 340px; overflow: hidden;
        }
        .opp-input-group input {
            border: none; padding: 8px 12px; font-size: 13px; flex: 1;
            color: var(--slate); outline: none; font-family: inherit; background: transparent;
        }
        .opp-input-group .clear-btn {
            background: none; border: none; border-left: 1px solid var(--border);
            padding: 0 12px; color: var(--lgray); cursor: pointer; display: flex; align-items: center;
        }
        .opp-info { display: flex; align-items: center; gap: 14px; font-size: 12px; color: var(--gray); }
        .opp-info-item { display: flex; align-items: center; gap: 5px; }
        .opp-info-item a { color: var(--primary); text-decoration: none; }
        .opp-info-item a:hover { text-decoration: underline; }

        /* ── Section Title ── */
        .section-title {
            font-size: 14px; font-weight: 700; color: var(--slate); margin: 0 0 12px 0;
            display: flex; align-items: center; gap: 7px;
        }

        /* ── Contract Box ── */
        .contract-box {
            background: #fefce8; border: 1px solid #fef08a; border-radius: var(--r-md); padding: 20px 22px;
        }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label {
            font-size: 10px; font-weight: 700; color: var(--gray);
            text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.2;
        }
        .form-control {
            padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--r-md);
            font-size: 13px; color: var(--slate); width: 100%; box-sizing: border-box;
            background: #fff; font-family: inherit; outline: none; line-height: 1.4;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        .form-select {
            appearance: none; padding-right: 32px;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px;
        }
        .field-link {
            font-size: 11px; color: var(--primary); text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .field-link:hover { text-decoration: underline; }
        .timeline-group { margin-top: 16px; }

        /* ── Financial Table ── */
        .pakd-table-section { margin-top: 28px; }
        .fin-table-wrap {
            border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden;
        }
        .fin-table {
            width: 100%; border-collapse: collapse; font-size: 12.5px;
            color: var(--slate); table-layout: fixed;
        }
        .fin-table colgroup .col-stt    { width: 64px; }
        .fin-table colgroup .col-item   { width: 320px; }
        .fin-table colgroup .col-desc   { }
        .fin-table colgroup .col-rate   { width: 96px; }
        .fin-table colgroup .col-amount { width: 170px; }
        .fin-table colgroup .col-ccy    { width: 52px; }
        .fin-table colgroup .col-action { width: 80px; }

        .fin-table thead th {
            background: #1e293b; color: #e2e8f0;
            padding: 9px 12px; text-align: left;
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            border: none; white-space: nowrap;
        }
        .fin-table thead th.r { text-align: right; }
        .fin-table thead th.c { text-align: center; }

        .fin-table td { padding: 7px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; overflow: hidden; }
        .fin-table tbody tr:last-child td { border-bottom: none; }

        /* Row type: main category */
        .fin-table tr.row-cat { background: #f1f5f9; }
        .fin-table tr.row-cat td { font-weight: 600; padding: 9px 12px; }
        /* Row 1: Doanh thu — xanh lá nhạt */
        .fin-table tr.row-rev { background: #dcfce7; }
        .fin-table tr.row-rev td { color: #166534; }
        /* Row 4: Tổng chi phí — cam nhạt */
        .fin-table tr.row-cost { background: #ffedd5; }
        .fin-table tr.row-cost td { color: #9a3412; }

        /* Row type: computed formula */
        .fin-table tr.row-formula { background: #fff; }
        .fin-table tr.row-formula td {
            font-weight: 700; padding: 10px 12px;
            border-top: 2px solid #e2e8f0; border-bottom: 2px solid #e2e8f0;
        }

        /* Row type: sub-item */
        .fin-table tr.row-sub { background: #fff; }

        /* Row type: locked (from production plan) */
        .fin-table tr.row-lock { background: #fafafa; }
        .fin-table tr.row-lock td { color: var(--gray); }

        /* Row type: detail (sub-sub) */
        .fin-table tr.row-detail { background: #fafafa; }

        /* Row type: section header (4.1, 4.2, 4.3, 4.4) */
        .fin-table tr.row-section { background: #eef4fb; }
        .fin-table tr.row-section td { font-weight: 500; color: #334155; }
        .fin-table tr.row-section .td-stt { font-weight: 600 !important; color: #475569; }

        /* Cell type helpers */
        .td-stt    { text-align: center; color: var(--lgray); font-size: 11px; font-weight: 400 !important; }
        .td-desc   { color: var(--gray); font-size: 12px; }
        .td-rate   { text-align: right; color: var(--gray); white-space: nowrap; }
        .td-amount { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .td-ccy    { text-align: center; color: var(--lgray); font-size: 11px; }
        .td-action { text-align: center; overflow: visible; }

        /* Indentation */
        .ind-1 { padding-left: 20px !important; }
        .ind-2 { padding-left: 36px !important; }

        /* Colored amounts */
        .amt-blue  { color: #3b82f6; }
        .amt-green { color: #16a34a; }

        /* Inline editable inputs */
        .fin-input {
            width: 100%; border: 1px solid transparent; border-radius: 4px;
            padding: 2px 6px; font-size: 12.5px; font-family: inherit;
            color: var(--slate); background: transparent; outline: none; box-sizing: border-box;
        }
        .fin-input:hover, .fin-input:focus { border-color: var(--border); background: #fff; }
        .fin-input::placeholder { color: var(--lgray); }
        .fin-input.r { text-align: right; }

        /* Percentage inline input */
        .pct-wrap  { display: inline-flex; align-items: center; gap: 3px; }
        .pct-inp   {
            width: 60px; text-align: right; border: 1px solid var(--border); border-radius: 4px;
            padding: 2px 6px; font-size: 12px; font-family: inherit;
            color: var(--slate); background: #fff; outline: none;
        }
        .pct-inp:focus { border-color: var(--primary); }
        .pct-sfx   { font-size: 11px; color: var(--gray); }
        .pct-res   { font-size: 12px; color: var(--gray); margin-left: 2px; }

        /* ── Inline save indicator ── */
        .field-saved {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            color: #16a34a; font-size: 11px; pointer-events: none;
            opacity: 0; transition: opacity 0.2s;
        }
        .field-saved.show { opacity: 1; }
        /* Cells need relative so indicator positions correctly */
        #fin-table td,
        .contract-box .form-group { position: relative; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php $page_title = ($pakd['opportunity_name'] ?? 'Chi tiết PAKD'); include __DIR__ . '/../includes/topbar.php'; ?>

        <!-- Combined status + metrics bar -->
        <div class="top-metrics-bar">
            <div class="tmb-left">
                <div class="sb-icon"><i class="fas <?= $iconClass ?>"></i></div>
                <div class="sb-text">
                    <span id="sb-label-text"><?= $statusLabel ?></span>
                    <span class="sb-sep">·</span>
                    <div class="sb-badge"><i class="fas <?= getProjectTypeIcon($pakd['project_type']) ?>"></i> <?= htmlspecialchars($pakd['project_type']) ?></div>
                </div>
            </div>
            <div class="tmb-right">
                <div class="metric-item">
                    <span class="m-label">Doanh thu thuần:</span>
                    <strong id="tmb-rev-net"><?= formatVND($fin_rev_net) ?></strong>
                    <span class="m-unit">VND</span>
                </div>
                <div class="tmb-divider"></div>
                <div class="metric-item">
                    <span class="m-label">Lợi nhuận gộp:</span>
                    <strong id="tmb-gross-profit"><?= formatVND($fin_gross_profit) ?></strong>
                    <span class="m-unit">VND</span>
                </div>
                <div class="tmb-divider"></div>
                <div class="metric-item">
                    <span class="m-label">PASX:</span>
                    <strong id="tmb-pasx-amt"><?= formatVND($fin_prod_cost) ?></strong>
                    <span class="m-unit">VND</span>
                    <span class="m-pct" id="tmb-pasx-pct">(<?= number_format($fin_rev_net > 0 ? ($fin_prod_cost / $fin_rev_net * 100) : 0, 2) ?>%)</span>
                </div>
            </div>
        </div>

        <?php if (($pakd['pasx_status'] ?? '') === 'pending_ceo' && $isCeoApprover): ?>
        <!-- CEO Action Banner -->
        <div id="ceo-action-banner" style="display:flex;align-items:center;gap:14px;padding:13px 28px;background:#fffbeb;border-bottom:1px solid #fde68a;">
            <div style="width:36px;height:36px;border-radius:9px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-user-tie" style="color:#d97706;font-size:15px;"></i>
            </div>
            <div style="flex:1;">
                <div style="font-size:.88rem;font-weight:700;color:#92400e;">Yêu cầu phê duyệt từ AM</div>
                <div style="font-size:.78rem;color:#b45309;margin-top:1px;">PASX này có margin &lt; 20%. AM đã gửi lên để CEO xem xét và phê duyệt.</div>
            </div>
            <button id="ceo-approve-direct-btn" onclick="ceoBannerApprove()"
                style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border:none;border-radius:8px;background:#16a34a;color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;font-family:inherit;">
                <i class="fas fa-check"></i> Approve
            </button>
            <button id="ceo-reject-direct-btn" onclick="ceoBannerReject()"
                style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid #fca5a5;border-radius:8px;background:#fff;color:#dc2626;font-size:.85rem;font-weight:600;cursor:pointer;font-family:inherit;">
                <i class="fas fa-times"></i> Từ chối
            </button>
        </div>
        <?php endif; ?>

        <div class="detail-container<?= ($pakd['status'] ?? '') === 'approved' ? ' page-locked' : '' ?>">

            <!-- Actions Row -->
            <div class="actions-row">
                <a href="/projects/phuong-an-kinh-doanh" class="btn-outline">
                    <i class="fas fa-arrow-left" style="font-size:11px;"></i> Danh sách PAKD
                </a>
                <a href="#" class="btn-outline yellow">
                    <i class="fas fa-history" style="font-size:11px;"></i> Lịch sử (1 version)
                </a>
            </div>

            <!-- Title -->
            <h1 class="page-title"><?= htmlspecialchars($pakd['name']) ?></h1>

            <!-- Metadata Box -->
            <div class="metadata-box">
                <div class="meta-item">
                    <div class="meta-label">Department</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['department'] ?: '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">AM (SD/AM)</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['am_name'] ?: '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Lead/Opp Divisions</div>
                    <div class="meta-value">
                        <select id="sel-division" class="project-type-select" onchange="saveDivision(this.value)" style="max-width:200px;">
                            <option value="">— Chọn Division —</option>
                            <?php foreach ($division_options as $div): ?>
                                <option value="<?= htmlspecialchars($div) ?>" <?= $pakd['division_names'] === $div ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($div) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($pakd['division_names'] && !in_array($pakd['division_names'], $division_options)): ?>
                                <option value="<?= htmlspecialchars($pakd['division_names']) ?>" selected>
                                    <?= htmlspecialchars($pakd['division_names']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Loại dự án</div>
                    <div class="meta-value">
                        <select id="sel-project-type" class="project-type-select" onchange="saveProjectType(this.value)">
                            <option value="external" <?= strtolower($pakd['project_type']) === 'external' ? 'selected' : '' ?>>🖥 External</option>
                            <option value="internal" <?= strtolower($pakd['project_type']) === 'internal' ? 'selected' : '' ?>>🏢 Internal</option>
                        </select>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">PAKD Gốc</div>
                    <div class="meta-value" style="display:flex;align-items:center;gap:6px;">
                        <?= htmlspecialchars($pakd['name']) ?>
                        <a href="<?= htmlspecialchars($pakd['odoo_url'] ?: '#') ?>" target="_blank" title="Mở trong Odoo"><i class="fas fa-link" style="font-size:11px;"></i> Mở</a>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Currency</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['currency']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Status</div>
                    <div class="meta-value"><?= htmlspecialchars($pakd['status']) ?></div>
                </div>
            </div>

            <!-- Opportunity Row -->
            <div class="opp-row">
                <div class="opp-label">Opportunity</div>
                <div class="opp-input-group">
                    <input type="text" value="<?= htmlspecialchars($pakd['opportunity_name'] ?: '') ?>" placeholder="Chọn Opportunity...">
                    <button class="clear-btn"><i class="fas fa-times"></i></button>
                </div>
                <div class="opp-info">
                    <div class="opp-info-item"><i class="fas fa-building"></i> <?= htmlspecialchars($pakd['company_name'] ?: '—') ?></div>
                    <div class="opp-info-item"><i class="fas fa-sack-dollar" style="color:#d97706;"></i> <?= formatVND($pakd['opp_value']) ?></div>
                    <div class="opp-info-item"><i class="fas fa-chart-line" style="color:#dc2626;"></i> <?= number_format($pakd['opp_probability'], 0) ?>%</div>
                    <div class="opp-info-item">
                        <a href="<?= htmlspecialchars($pakd['odoo_url'] ?: '#') ?>" target="_blank"><i class="fas fa-link" style="font-size:11px;"></i> Mở trong Odoo</a>
                    </div>
                </div>
            </div>

            <!-- Contract Box -->
            <h3 class="section-title"><i class="fas fa-file-contract" style="color:var(--gray);"></i> Thông tin hợp đồng</h3>
            <div class="contract-box">
                <div class="form-grid">
                    <div class="form-group col-span-1">
                        <label class="form-label">Khách hàng</label>
                        <select class="form-control form-select">
                            <option><?= htmlspecialchars($pakd['company_name'] ?: 'Chọn khách hàng...') ?></option>
                        </select>
                        <a href="#" class="field-link"><i class="fas fa-link"></i> Mở khách hàng trong Odoo</a>
                    </div>
                    <div class="form-group col-span-1">
                        <label class="form-label">Số hợp đồng</label>
                        <input type="text" id="inp-contract-no" class="form-control" placeholder="vd: HD-2026-001" value="<?= htmlspecialchars($pakd['contract_no'] ?: '') ?>">
                    </div>
                    <div class="form-group col-span-1">
                        <label class="form-label">Sales Order No</label>
                        <select class="form-control form-select">
                            <option><?= htmlspecialchars($pakd['sales_order_no'] ?: 'Tìm SO từ Odoo...') ?></option>
                        </select>
                        <a href="#" class="field-link"><i class="fas fa-link"></i> Mở SO trong Odoo</a>
                    </div>
                    <div class="form-group col-span-1">
                        <label class="form-label">Purchase Order No</label>
                        <select class="form-control form-select">
                            <option><?= htmlspecialchars($pakd['purchase_order_no'] ?: 'Tìm PO từ Odoo...') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group timeline-group">
                    <label class="form-label">Thời gian triển khai</label>
                    <input type="text" id="inp-timeline" class="form-control" placeholder="vd: Q3-Q4/2026, 6 tháng từ 01/06/2026..." value="<?= htmlspecialchars($pakd['timeline'] ?: '') ?>">
                </div>
            </div>

            <!-- ── Financial Detail Table ── -->
            <div class="pakd-table-section">
                <h3 class="section-title" style="margin-bottom:12px;">
                    <i class="fas fa-table-cells-large" style="color:var(--gray);"></i>
                    Phương án Kinh doanh chi tiết
                </h3>
                <div class="fin-table-wrap">
                    <table class="fin-table" id="fin-table">
                        <colgroup>
                            <col class="col-stt">
                            <col class="col-item">
                            <col class="col-desc">
                            <col class="col-rate">
                            <col class="col-amount">
                            <col class="col-ccy">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="c">STT</th>
                                <th>Hạng mục</th>
                                <th>Diễn giải</th>
                                <th class="r">Tỷ lệ</th>
                                <th class="r">Số tiền</th>
                                <th class="c">CCY</th>
                                <th class="c">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 1. Doanh thu -->
                            <tr class="row-cat row-rev">
                                <td class="td-stt">1</td>
                                <td>Doanh thu</td>
                                <td></td>
                                <td class="td-rate">100.00%</td>
                                <td class="td-amount" id="r1-amt"><?= formatVND($fin_rev_gross) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">1.1</td>
                                <td class="ind-1"><input class="fin-input" value="Doanh thu dịch vụ - gói triển khai" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate" id="r11-rate">100.00%</td>
                                <td class="td-amount"><input class="fin-input r" id="r11-inp" value="<?= number_format($fin_rev_gross, 0, '', '') ?>" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">1.2</td>
                                <td class="ind-1"><input class="fin-input" value="Doanh thu khác" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate" id="r12-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r12-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 1.3 Change Requests -->
                            <tr class="row-sub row-cr-header">
                                <td class="td-stt">1.3</td>
                                <td class="ind-1">Change Requests</td>
                                <td><span style="font-size:11px;color:#64748b;">Các khoản thu thêm từ CR</span></td>
                                <td class="td-rate" id="r13-rate"></td>
                                <td class="td-amount" id="r13-amt" style="font-weight:600;color:#0f172a;">0</td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action">
                                    <button class="btn-add-cr" onclick="addCrRow()" title="Thêm Change Request">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                            <tbody id="cr-rows"></tbody>

                            <!-- 2. Khoản giảm trừ -->
                            <tr class="row-cat">
                                <td class="td-stt">2</td>
                                <td>Khoản giảm trừ doanh thu</td>
                                <td></td>
                                <td class="td-rate"></td>
                                <td class="td-amount" id="r2-amt">0</td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">2.1</td>
                                <td class="ind-1"><input class="fin-input" value="Chi hoa hồng cho đối tác" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" value="Chi phí feedback, hoa hồng cho CTV, đối tác" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r21-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-sub">
                                <td class="td-stt">2.2</td>
                                <td class="ind-1"><input class="fin-input" value="Khoản giảm trừ khác" placeholder="Hạng mục..."></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r22-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 3. Doanh thu thuần (FORMULA) -->
                            <tr class="row-formula">
                                <td class="td-stt">3</td>
                                <td>Doanh thu thuần <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;" title="= Doanh thu - Khoản giảm trừ"></i></td>
                                <td class="td-desc">= Doanh thu - Khoản giảm trừ</td>
                                <td class="td-rate">100%</td>
                                <td class="td-amount amt-blue" id="r3-amt"><?= formatVND($fin_rev_net) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4. Tổng chi phí -->
                            <tr class="row-cat row-cost">
                                <td class="td-stt">4</td>
                                <td>Tổng chi phí <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;"></i></td>
                                <td></td>
                                <td class="td-rate" id="r4-rate"><?= pct($fin_total_cost, $fin_rev_net) ?>%</td>
                                <td class="td-amount" id="r4-amt"><?= formatVND($fin_total_cost) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.1 Chi phí sản xuất (LOCKED) -->
                            <tr class="row-lock row-section">
                                <td class="td-stt">4.1</td>
                                <td class="ind-1">
                                    Chi phí sản xuất
                                    <i class="fas fa-lock" style="color:var(--lgray);font-size:9px;margin-left:4px;" title="Khóa – từ Phương án sản xuất"></i>
                                    <?php if ($fin_pasx_note): ?>
                                    <i class="fas fa-circle-info pasx-note-icon" data-note="<?= htmlspecialchars($fin_pasx_note) ?>" style="color:#d97706;font-size:10px;margin-left:2px;cursor:help;"></i>
                                    <?php else: ?>
                                    <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;margin-left:2px;"></i>
                                    <?php endif; ?>
                                    <button class="btn-pasx-history" onclick="openPasxHistory()" title="Xem lịch sử cập nhật từ Profile">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </td>
                                <td class="td-desc">
                                    Lấy thông tin từ Phương án sản xuất (locked)
                                    <?php if (!empty($pakd['pasx_id'])): ?>
                                        <?php if ($pasx_has_data): ?>
                                            <span id="pasx-action-btns">
                                            <?php if (($pakd['status'] ?? '') === 'approved'): ?>
                                                <span style="font-size:12px;color:rgba(255,255,255,.8);"><i class="fas fa-check-circle"></i> Đã Approve</span>
                                            <?php elseif (($pakd['pasx_status'] ?? '') === 'pending_ceo'): ?>
                                                <span style="font-size:12px;color:#d97706;font-weight:500;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-user-tie"></i> Đã gửi lên CEO — đang chờ phê duyệt</span>
                                            <?php elseif (($pakd['pasx_status'] ?? '') === 'rejected'): ?>
                                                <span style="font-size:12px;color:#b45309;font-weight:500;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-clock"></i> Đã Reject — đang chờ Profile rebuild</span>
                                            <?php elseif ($fin_margin_pct >= 20): ?>
                                                <button class="btn-pasx-action btn-pasx-approve" onclick="pasxApprove()">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-pasx-action btn-pasx-ceo" onclick="pasxGetApproveCEO()">
                                                    <i class="fas fa-user-tie"></i> Get Approve (CEO)
                                                </button>
                                                <button class="btn-pasx-action btn-pasx-reject" onclick="pasxRejectRebuild()">
                                                    <i class="fas fa-redo"></i> Reject / Rebuild PASX
                                                </button>
                                            <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="pasx-processing-label">
                                                <i class="fas fa-clock"></i> Processing...
                                            </span>
                                            <button id="btn-resend-pasx" class="btn-resend-pasx" onclick="resendPasxRequest()" title="Gửi lại yêu cầu PASX">
                                                <i class="fas fa-redo"></i> Resend
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button id="btn-req-pasx"
                                                class="btn-req-pasx"
                                                onclick="requestProductionPlan()"
                                                title="Gửi yêu cầu tạo Phương án sản xuất">
                                            <i class="fas fa-paper-plane"></i>
                                            Request production plan
                                        </button>
                                        <span class="pasx-sent-badge" id="pasx-sent-badge" style="display:none"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-rate" id="r41-rate"><?= pctCost($fin_prod_cost, $fin_total_cost) ?>%</td>
                                <td class="td-amount"><?= formatVND($fin_prod_cost) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action">
                                    <button class="btn-pasx-history" onclick="openPasxHistory()" title="Review lịch sử từ ArrowHitech Profile">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="row-detail row-lock">
                                <td class="td-stt">4.1.1</td>
                                <td class="ind-2">Human Cost / Chi phí nhân công <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;"></i></td>
                                <td class="td-desc pasx-sub-desc">
                                    Từ Phương án sản xuất
                                    <?php if (!empty($pakd['pasx_id']) && !$pasx_has_data): ?>
                                        <span class="pasx-processing-label">
                                            <i class="fas fa-clock"></i> Processing...
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-rate" id="r411-rate"><?= $fin_human_cost > 0 ? pctCost($fin_human_cost, $fin_total_cost) . '%' : '' ?></td>
                                <td class="td-amount"><?= $fin_human_cost > 0 ? formatVND($fin_human_cost) : '' ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail row-lock">
                                <td class="td-stt">4.1.2</td>
                                <td class="ind-2">Chi phí làm việc ngoài giờ / Overtime cost <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;"></i></td>
                                <td class="td-desc pasx-sub-desc">
                                    Từ Phương án sản xuất
                                    <?php if (!empty($pakd['pasx_id']) && !$pasx_has_data): ?>
                                        <span class="pasx-processing-label">
                                            <i class="fas fa-clock"></i> Processing...
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-rate" id="r412-rate"><?= $fin_overtime > 0 ? pctCost($fin_overtime, $fin_total_cost) . '%' : '' ?></td>
                                <td class="td-amount"><?= $fin_overtime > 0 ? formatVND($fin_overtime) : '' ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.2 Chi phí bán hàng -->
                            <tr class="row-sub row-section">
                                <td class="td-stt">4.2</td>
                                <td class="ind-1">Chi phí bán hàng (kinh doanh)</td>
                                <td class="td-desc">Tuân thủ theo bảng phân bổ tùy thị trường</td>
                                <td class="td-rate" id="r42-rate"><?= number_format($fin_sales_pct, 2) ?>%</td>
                                <td class="td-amount" id="r42-amt"><?= formatVND($fin_sales_total) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.1</td>
                                <td class="ind-2"><input class="fin-input" id="r421-name" value="<?= htmlspecialchars($fin_saved['r421_name'] ?? 'Sales Commission') ?>"></td>
                                <td class="td-desc"><input class="fin-input" id="r421-desc" value="<?= htmlspecialchars($fin_saved['r421_desc'] ?? '2% doanh thu') ?>"></td>
                                <td class="td-rate" id="r421-rate"></td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r421-pct" value="<?= $fin_sales_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r421-res">= <?= formatVND($fin_sales_comm) ?></span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.2</td>
                                <td class="ind-2"><input class="fin-input" id="r422-name" value="<?= htmlspecialchars($fin_saved['r422_name'] ?? 'Presales Commission') ?>"></td>
                                <td class="td-desc"><input class="fin-input" id="r422-desc" value="<?= htmlspecialchars($fin_saved['r422_desc'] ?? '% doanh thu') ?>"></td>
                                <td class="td-rate" id="r422-rate"></td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r422-pct" value="<?= $fin_presales_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r422-res">= 0</span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.3</td>
                                <td class="ind-2"><input class="fin-input" id="r423-name" value="<?= htmlspecialchars($fin_saved['r423_name'] ?? 'MKT Commission') ?>"></td>
                                <td class="td-desc"><input class="fin-input" id="r423-desc" value="<?= htmlspecialchars($fin_saved['r423_desc'] ?? '% doanh thu') ?>"></td>
                                <td class="td-rate" id="r423-rate"></td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r423-pct" value="<?= $fin_mkt_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r423-res">= 0</span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <tr class="row-detail">
                                <td class="td-stt">4.2.4</td>
                                <td class="ind-2"><input class="fin-input" id="r424-name" value="<?= htmlspecialchars($fin_saved['r424_name'] ?? 'Chi phí bán hàng khác') ?>"></td>
                                <td class="td-desc"><input class="fin-input" id="r424-desc" placeholder="Diễn giải..." value="<?= htmlspecialchars($fin_saved['r424_desc'] ?? '') ?>"></td>
                                <td class="td-rate"></td>
                                <td class="td-amount"><input class="fin-input r" id="r424-inp" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.3 Chi phí quản lý -->
                            <tr class="row-sub row-section">
                                <td class="td-stt">4.3</td>
                                <td class="ind-1">Chi phí quản lý + back office</td>
                                <td class="td-desc">12% doanh thu thuần — Tuân thủ bằng phân bổ</td>
                                <td class="td-rate" id="r43-rate"><?= number_format($fin_mgmt_pct, 2) ?>%</td>
                                <td class="td-amount">
                                    <div class="pct-wrap">
                                        <input type="number" class="pct-inp" id="r43-pct" value="<?= $fin_mgmt_pct ?>" min="0" max="100" step="0.1" oninput="fin_calc()">
                                        <span class="pct-sfx">%</span>
                                        <span class="pct-res" id="r43-res">= <?= formatVND($fin_mgmt) ?></span>
                                    </div>
                                </td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>

                            <!-- 4.4 Chi phí khác -->
                            <tr class="row-sub row-section">
                                <td class="td-stt">4.4</td>
                                <td class="ind-1">Chi phí khác</td>
                                <td></td>
                                <td class="td-rate" id="r44-rate"></td>
                                <td class="td-amount amt-blue" id="r44-amt">0</td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <?php
                            $other_items = ['4.4.1'=>'Công tác phí','4.4.2'=>'Chi phí đào tạo','4.4.3'=>'Chi phí teambuilding','4.4.4'=>'Chi phí tiếp khách','4.4.5'=>'Chi phí hội thảo, truyền thông'];
                            foreach ($other_items as $num => $label): ?>
                            <tr class="row-detail">
                                <td class="td-stt"><?= $num ?></td>
                                <td class="ind-2"><input class="fin-input" value="<?= $label ?>"></td>
                                <td><input class="fin-input" placeholder="Diễn giải..."></td>
                                <td class="td-rate"></td>
                                <td class="td-amount other-cost-cell"><input class="fin-input r" placeholder="0" oninput="fin_calc()"></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- 5. Lợi nhuận gộp (FORMULA) -->
                            <tr class="row-formula">
                                <td class="td-stt">5</td>
                                <td>Lợi nhuận gộp (margin) <i class="fas fa-circle-info" style="color:var(--lgray);font-size:10px;" title="= Doanh thu thuần - Tổng chi phí"></i></td>
                                <td class="td-desc">= Doanh thu thuần - Tổng chi phí</td>
                                <td class="td-rate" id="r5-rate"><?= pct($fin_gross_profit, $fin_rev_net) ?>%</td>
                                <td class="td-amount amt-green" id="r5-amt"><?= formatVND($fin_gross_profit) ?></td>
                                <td class="td-ccy">VND</td>
                                <td class="td-action"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }

        const FIN_PROD       = <?= (int)$fin_prod_cost ?>;
        const FIN_HUMAN      = <?= (int)$fin_human_cost ?>;
        const FIN_OVERTIME   = <?= (int)$fin_overtime ?>;
        const PAKD_ID        = <?= $pakd_id ?>;
        const PASX_HAS_DATA  = <?= $pasx_has_data ? 'true' : 'false' ?>;
        const PAKD_STATUS    = <?= json_encode($pakd['status'] ?? 'draft') ?>;
        const PASX_STATUS    = <?= json_encode($pakd['pasx_status'] ?? '') ?>;

        // ── Helpers ──
        function fin_fmt(n) {
            return Math.round(n).toLocaleString('vi-VN');
        }

        function fin_parse(val) {
            if (!val) return 0;
            return parseFloat(String(val).replace(/\./g, '').replace(',', '.')) || 0;
        }

        // Helper: % of total cost, always ≥ 0
        function costPct(val, total) {
            if (!total) return '—';
            return Math.max(0, val / total * 100).toFixed(2) + '%';
        }

        function fin_calc() {
            // ── Row 1: Doanh thu = sum(1.1 + 1.2 + 1.3 CR) ──
            const v11 = fin_parse(document.getElementById('r11-inp').value);
            const v12 = fin_parse(document.getElementById('r12-inp').value);
            const v13 = getCrTotal();
            const revGross = v11 + v12 + v13;
            document.getElementById('r1-amt').textContent = fin_fmt(revGross);
            document.getElementById('r11-rate').textContent = revGross > 0 ? (v11 / revGross * 100).toFixed(2) + '%' : '';
            document.getElementById('r13-amt').textContent = fin_fmt(v13);
            document.getElementById('r13-rate').textContent = v13 > 0 && revGross > 0 ? (v13 / revGross * 100).toFixed(2) + '%' : '';

            // ── Row 2: Khoản giảm trừ = sum(2.1 + 2.2) ──
            const v21 = fin_parse(document.getElementById('r21-inp').value);
            const v22 = fin_parse(document.getElementById('r22-inp').value);
            const deductions = v21 + v22;
            document.getElementById('r2-amt').textContent = fin_fmt(deductions);

            // ── Row 3: Doanh thu thuần ──
            const revNet = revGross - deductions;
            document.getElementById('r3-amt').textContent = fin_fmt(revNet);

            // ── Row 4.2: Sales commissions (base = revGross, always ≥ 0) ──
            const p421 = parseFloat(document.getElementById('r421-pct').value) || 0;
            const p422 = parseFloat(document.getElementById('r422-pct').value) || 0;
            const p423 = parseFloat(document.getElementById('r423-pct').value) || 0;
            const v421 = Math.max(0, revGross * p421 / 100);
            const v422 = Math.max(0, revGross * p422 / 100);
            const v423 = Math.max(0, revGross * p423 / 100);
            const v424 = fin_parse(document.getElementById('r424-inp').value);

            document.getElementById('r421-res').textContent = '= ' + fin_fmt(v421);
            document.getElementById('r422-res').textContent = '= ' + fin_fmt(v422);
            document.getElementById('r423-res').textContent = '= ' + fin_fmt(v423);

            const salesTotal = v421 + v422 + v423 + v424;
            document.getElementById('r42-amt').textContent = fin_fmt(salesTotal);

            // ── Row 4.3: Management (% of revNet) ──
            const p43 = parseFloat(document.getElementById('r43-pct').value) || 0;
            const v43 = Math.max(0, revNet * p43 / 100);
            document.getElementById('r43-res').textContent = '= ' + fin_fmt(v43);

            // ── Row 4.4: Other costs — collect individual values first ──
            let otherTotal = 0;
            const otherRows = []; // [{rateCell, val}] for rate update after totalCost
            document.querySelectorAll('.other-cost-cell input').forEach(function(inp) {
                const val = fin_parse(inp.value);
                otherTotal += val;
                const row = inp.closest('tr');
                const rateCell = row ? row.querySelector('.td-rate') : null;
                otherRows.push({ rateCell, val });
            });
            document.getElementById('r44-amt').textContent = fin_fmt(otherTotal);

            // ── Row 4: Total cost ──
            const totalCost = FIN_PROD + salesTotal + v43 + otherTotal;
            const totalPct = revNet > 0 ? (totalCost / revNet * 100).toFixed(2) : '0.00';
            document.getElementById('r4-rate').textContent = totalPct + '%';
            document.getElementById('r4-amt').textContent = fin_fmt(totalCost);

            // ── All cost sub-row rates: % of totalCost, always ≥ 0 ──
            const r41 = document.getElementById('r41-rate');
            if (r41) r41.textContent = costPct(FIN_PROD, totalCost);
            const r411 = document.getElementById('r411-rate');
            if (r411) r411.textContent = FIN_HUMAN > 0 ? costPct(FIN_HUMAN, totalCost) : '';
            const r412 = document.getElementById('r412-rate');
            if (r412) r412.textContent = FIN_OVERTIME > 0 ? costPct(FIN_OVERTIME, totalCost) : '';
            // 4.2 sub-items: % of totalCost
            document.getElementById('r421-rate').textContent = v421 > 0 ? costPct(v421, totalCost) : '';
            document.getElementById('r422-rate').textContent = v422 > 0 ? costPct(v422, totalCost) : '';
            document.getElementById('r423-rate').textContent = v423 > 0 ? costPct(v423, totalCost) : '';
            // 4.2 total, 4.3, 4.4 totals
            document.getElementById('r42-rate').textContent = costPct(salesTotal, totalCost);
            document.getElementById('r43-rate').textContent = costPct(v43, totalCost);
            const r44el = document.getElementById('r44-rate');
            if (r44el) r44el.textContent = otherTotal > 0 ? costPct(otherTotal, totalCost) : '';
            // 4.4 sub-item rates
            otherRows.forEach(function({ rateCell, val }) {
                if (rateCell) rateCell.textContent = val > 0 ? costPct(val, totalCost) : '';
            });

            // ── Row 5: Gross profit ──
            const grossProfit = revNet - totalCost;
            const grossPct = revNet > 0 ? (grossProfit / revNet * 100).toFixed(2) : '0.00';
            document.getElementById('r5-rate').textContent = grossPct + '%';
            document.getElementById('r5-amt').textContent = fin_fmt(grossProfit);

            // ── Cập nhật stats bar (top metrics) ──
            const tmbRev = document.getElementById('tmb-rev-net');
            if (tmbRev) tmbRev.textContent = fin_fmt(revNet);
            const tmbGp = document.getElementById('tmb-gross-profit');
            if (tmbGp) tmbGp.textContent = fin_fmt(grossProfit);
            const tmbPasxPct = document.getElementById('tmb-pasx-pct');
            if (tmbPasxPct && revNet > 0) tmbPasxPct.textContent = '(' + (FIN_PROD / revNet * 100).toFixed(2) + '%)';

            // ── Cập nhật PASX action buttons theo margin realtime ──
            if (PASX_HAS_DATA && PAKD_STATUS !== 'approved' && PASX_STATUS !== 'pending_ceo' && PASX_STATUS !== 'rejected') {
                const marginPct = revNet > 0 ? (grossProfit / revNet * 100) : 0;
                const container = document.getElementById('pasx-action-btns');
                if (container) {
                    if (marginPct >= 20) {
                        container.innerHTML = `
                            <button class="btn-pasx-action btn-pasx-approve" onclick="pasxApprove()">
                                <i class="fas fa-check"></i> Approve
                            </button>`;
                    } else {
                        container.innerHTML = `
                            <button class="btn-pasx-action btn-pasx-ceo" onclick="pasxGetApproveCEO()">
                                <i class="fas fa-user-tie"></i> Get Approve (CEO)
                            </button>
                            <button class="btn-pasx-action btn-pasx-reject" onclick="pasxRejectRebuild()">
                                <i class="fas fa-redo"></i> Reject / Rebuild PASX
                            </button>`;
                    }
                }
            }
        }

        // ── Inline save indicator ──
        function showFieldSaved(input) {
            const cell = input.closest('td') || input.closest('.form-group') || input.parentElement;
            if (!cell) return; // element removed from DOM (e.g. after row deletion)
            cell.querySelectorAll('.field-saved').forEach(e => e.remove());
            const badge = document.createElement('span');
            badge.className = 'field-saved';
            badge.innerHTML = '<i class="fas fa-check-circle"></i>';
            cell.appendChild(badge);
            requestAnimationFrame(() => badge.classList.add('show'));
            setTimeout(() => {
                badge.classList.remove('show');
                setTimeout(() => badge.remove(), 250);
            }, 2000);
        }

        // ── Autosave (triggered on blur of any editable field) ──
        function autosave(triggerInput) {
            fin_calc();
            const el = id => document.getElementById(id);
            const finData = {
                r11_amt:     fin_parse(el('r11-inp')?.value),
                r12_amt:     fin_parse(el('r12-inp')?.value),
                r21_amt:     fin_parse(el('r21-inp')?.value),
                r22_amt:     fin_parse(el('r22-inp')?.value),
                r421_pct:    parseFloat(el('r421-pct')?.value) || 0,
                r422_pct:    parseFloat(el('r422-pct')?.value) || 0,
                r423_pct:    parseFloat(el('r423-pct')?.value) || 0,
                r424_amt:    fin_parse(el('r424-inp')?.value),
                r43_pct:     parseFloat(el('r43-pct')?.value) || 0,
                // Labels (persisted so edits survive F5)
                r421_name: (el('r421-name')?.value || '').trim(),
                r421_desc: (el('r421-desc')?.value || '').trim(),
                r422_name: (el('r422-name')?.value || '').trim(),
                r422_desc: (el('r422-desc')?.value || '').trim(),
                r423_name: (el('r423-name')?.value || '').trim(),
                r423_desc: (el('r423-desc')?.value || '').trim(),
                r424_name: (el('r424-name')?.value || '').trim(),
                r424_desc: (el('r424-desc')?.value || '').trim(),
                other_costs: Array.from(document.querySelectorAll('.other-cost-cell input'))
                                  .map(i => fin_parse(i.value)),
                rev_net:         fin_parse(el('r3-amt')?.textContent), // doanh thu thuần (sau giảm trừ)
                change_requests: getCrData(),
            };
            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:       'save_pakd',
                    id:           PAKD_ID,
                    fin_data:     JSON.stringify(finData),
                    revenue:      fin_parse(el('r1-amt')?.textContent),
                    gross_profit: fin_parse(el('r5-amt')?.textContent),
                    contract_no:  (el('inp-contract-no')?.value || '').trim(),
                    timeline:     (el('inp-timeline')?.value    || '').trim(),
                })
            })
            .then(r => r.json())
            .then(data => { if (data.ok) showFieldSaved(triggerInput); })
            .catch(() => {});
        }

        // ── Load saved fin_data on page open ──
        (function fin_load() {
            const saved = <?= json_encode($fin_saved) ?>;
            if (!saved || !Object.keys(saved).length) return;
            const el = id => document.getElementById(id);
            const set = (id, val) => { const e = el(id); if (e && val !== undefined) e.value = val; };
            set('r11-inp',  saved.r11_amt  || '');
            set('r12-inp',  saved.r12_amt  || '');
            set('r21-inp',  saved.r21_amt  || '');
            set('r22-inp',  saved.r22_amt  || '');
            set('r421-pct', saved.r421_pct !== undefined ? saved.r421_pct : 2);
            set('r422-pct', saved.r422_pct !== undefined ? saved.r422_pct : 0);
            set('r423-pct', saved.r423_pct !== undefined ? saved.r423_pct : 0);
            set('r424-inp', saved.r424_amt || '');
            set('r43-pct',  saved.r43_pct  !== undefined ? saved.r43_pct : 12);
            if (Array.isArray(saved.other_costs)) {
                const cells = document.querySelectorAll('.other-cost-cell input');
                saved.other_costs.forEach((v, i) => { if (cells[i]) cells[i].value = v || ''; });
            }
            if (Array.isArray(saved.change_requests)) {
                saved.change_requests.forEach(function(cr) {
                    if (cr && (cr.name || cr.amount)) {
                        addCrRow(cr.name || '', cr.amount || 0);
                    }
                });
            }
        })();

        // ── Wire up autosave + init on DOM ready ──
        document.addEventListener('DOMContentLoaded', function () {
            if (PAKD_STATUS === 'approved') {
                // Khoá toàn bộ field khi đã approved
                document.querySelectorAll('.detail-container input, .detail-container select, .detail-container textarea').forEach(function (el) {
                    el.disabled = true;
                });
                fin_calc(); // vẫn tính để hiển thị đúng số liệu
                return;     // không wire autosave
            }

            // Attach blur → autosave to all financial table inputs
            document.querySelectorAll('#fin-table .fin-input, #fin-table .pct-inp').forEach(function (inp) {
                inp.addEventListener('blur', function () { autosave(this); });
                inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') this.blur(); });
            });
            // Contract box inputs
            ['inp-contract-no', 'inp-timeline'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('blur', function () { autosave(this); });
                    el.addEventListener('keydown', function (e) { if (e.key === 'Enter') this.blur(); });
                }
            });
            fin_calc();
        });

        // ── Save Project Type ──
        function saveProjectType(val) {
            fetch('/projects/pakd/edit?id=<?= (int)$pakd['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=save_project_type&project_type=' + encodeURIComponent(val)
            })
            .then(r => r.json())
            .then(d => { if (d.ok) showToast('Đã lưu loại dự án: ' + val, 'success'); });
        }

        // ── Request Production Plan ──
        function requestProductionPlan() {
            const btn = document.getElementById('btn-req-pasx');
            if (!btn || btn.disabled) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=<?= (int)$pakd['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pakdId: <?= (int)$pakd['id'] ?> })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showToast(data.msg, 'success');
                    const pasxId = data.data?.data?.pasxId ?? '';
                    const now = new Date();
                    const fmt = now.toLocaleDateString('vi-VN',{day:'2-digit',month:'2-digit',year:'numeric'})
                              + ' ' + now.toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'});
                    // Replace button with Processing label + badge
                    btn.outerHTML = `
                        <span class="pasx-processing-label">
                            <i class="fas fa-clock"></i> Processing...
                        </span>
                        <span class="pasx-sent-badge" style="display:inline-flex">
                            PASX: <code>${pasxId}</code>
                            <span class="pasx-sent-at">${fmt}</span>
                        </span>`;
                    // Add Processing label to 4.1.1 and 4.1.2
                    document.querySelectorAll('.pasx-sub-desc').forEach(el => {
                        el.insertAdjacentHTML('beforeend', `<span class="pasx-processing-label"><i class="fas fa-clock"></i> Processing...</span>`);
                    });
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Request production plan';
                }
            })
            .catch(err => {
                showToast('Lỗi kết nối: ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Request production plan';
            });
        }

        function showToast(msg, type) {
            const existing = document.getElementById('pasx-toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.id = 'pasx-toast';
            toast.style.cssText = [
                'position:fixed', 'bottom:24px', 'right:24px', 'z-index:9999',
                'padding:12px 20px', 'border-radius:8px', 'font-size:14px',
                'font-weight:500', 'box-shadow:0 4px 12px rgba(0,0,0,.15)',
                'display:flex', 'align-items:center', 'gap:10px',
                'max-width:420px', 'animation:toastIn .25s ease',
                type === 'success'
                    ? 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0'
                    : 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca'
            ].join(';');

            const icon = type === 'success'
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

            toast.innerHTML = icon + msg;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        function saveDivision(val) {
            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'save_division', id: PAKD_ID, division_names: val })
            })
            .then(r => r.json())
            .then(d => {
                const sel = document.getElementById('sel-division');
                if (d.ok) {
                    showToast('Đã lưu Division', 'success');
                    sel.style.borderColor = '#16a34a';
                    setTimeout(() => sel.style.borderColor = '', 2000);
                } else {
                    showToast('Lỗi lưu Division', 'error');
                }
            });
        }

        // ── Update status banner dynamically (không cần F5) ──
        function updateStatusBanner(label, color, iconCls) {
            const bar = document.querySelector('.top-metrics-bar');
            if (!bar) return;
            bar.style.background = color;
            bar.style.boxShadow  = '0 2px 8px ' + color + '44';
            const icon = bar.querySelector('.sb-icon i');
            if (icon) icon.className = 'fas ' + iconCls;
            const labelEl = document.getElementById('sb-label-text');
            if (labelEl) labelEl.textContent = label;
        }

        // ── Change Request helpers ──
        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function getCrTotal() {
            let total = 0;
            document.querySelectorAll('#cr-rows .cr-amt-inp').forEach(function(inp) {
                total += fin_parse(inp.value);
            });
            return total;
        }

        function getCrData() {
            const items = [];
            document.querySelectorAll('#cr-rows tr.cr-row').forEach(function(row) {
                const name = row.querySelector('.cr-name-inp')?.value || '';
                const amt  = fin_parse(row.querySelector('.cr-amt-inp')?.value || '0');
                items.push({ name: name, amount: amt });
            });
            return items;
        }

        function addCrRow(name, amount) {
            name   = name   !== undefined ? name   : '';
            amount = amount !== undefined ? amount : 0;
            const idx = Date.now() + '_' + Math.floor(Math.random() * 10000);
            const tr  = document.createElement('tr');
            tr.className = 'row-detail cr-row';
            tr.dataset.idx = idx;
            tr.innerHTML =
                '<td class="td-stt" style="color:#94a3b8;font-size:11px;">CR</td>' +
                '<td class="ind-2">' +
                    '<input class="cr-name-inp" value="' + escHtml(name) + '" placeholder="Tên Change Request..." ' +
                           'oninput="fin_calc()" onblur="autosave(this)">' +
                '</td>' +
                '<td><input class="fin-input" placeholder="Diễn giải..."></td>' +
                '<td class="td-rate"></td>' +
                '<td class="td-amount">' +
                    '<input class="cr-amt-inp" value="' + (amount || '') + '" placeholder="0" ' +
                           'oninput="fin_calc()" onblur="autosave(this)">' +
                '</td>' +
                '<td class="td-ccy">VND</td>' +
                '<td class="td-action">' +
                    '<button class="btn-del-cr" onclick="deleteCrRow(this)" title="Xóa CR này">' +
                        '<i class="fas fa-trash-alt"></i>' +
                    '</button>' +
                '</td>';
            document.getElementById('cr-rows').appendChild(tr);
            fin_calc();
            tr.querySelector('.cr-name-inp')?.focus();
        }

        function deleteCrRow(btn) {
            btn.closest('tr')?.remove();
            fin_calc();
            autosave(btn);
        }

        // ── Resend PASX Request ──
        function resendPasxRequest() {
            const btn = document.getElementById('btn-resend-pasx');
            if (!btn || btn.disabled) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=<?= (int)$pakd['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pakdId: <?= (int)$pakd['id'] ?> })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showToast(data.msg || 'Đã gửi lại yêu cầu PASX!', 'success');
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra khi gửi lại', 'error');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Resend';
            })
            .catch(function(err) {
                showToast('Lỗi kết nối: ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Resend';
            });
        }

        // ── CEO Banner: Approve trực tiếp từ pakd_detail ──
        function ceoBannerApprove() {
            const btn = document.getElementById('ceo-approve-direct-btn');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'approve_pasx', id: PAKD_ID })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showToast('Đã approve PASX thành công!', 'success');
                    document.getElementById('ceo-action-banner')?.remove();
                    updateStatusBanner('AM tiến hành gửi báo giá và xác nhận', '#16a34a', 'fa-check-square');
                    const container = document.getElementById('pasx-action-btns');
                    if (container) container.innerHTML = '<span style="font-size:12px;color:rgba(255,255,255,.8);"><i class="fas fa-check-circle"></i> Đã Approve</span>';
                    document.querySelectorAll('.detail-container input,.detail-container select,.detail-container textarea').forEach(el => { el.disabled = true; });
                    document.querySelector('.detail-container')?.classList.add('page-locked');
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Approve';
                }
            })
            .catch(() => { showToast('Lỗi kết nối', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Approve'; });
        }

        function ceoBannerReject() {
            // Dùng lại pasxRejectRebuild() đang có
            pasxRejectRebuild();
        }

        function pasxApprove() {
            // Dialog xác nhận
            const overlay = document.createElement('div');
            overlay.id = 'approve-dialog-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2000;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;width:400px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
                    <div style="padding:18px 20px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
                        <span style="width:32px;height:32px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-check" style="color:#16a34a;font-size:14px;"></i>
                        </span>
                        <div>
                            <div style="font-weight:700;font-size:15px;color:#0f172a;">Xác nhận Approve PASX</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">Profile sẽ tạo Project sau khi Approve</div>
                        </div>
                    </div>
                    <div style="padding:16px 20px;">
                        <p style="font-size:12.5px;color:#475569;margin:0 0 16px;">
                            Sau khi Approve, Profile sẽ tạo Project và trạng thái PAKD chuyển sang <strong>Approved</strong>.
                            Hành động này không thể hoàn tác.
                        </p>
                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button onclick="document.getElementById('approve-dialog-overlay').remove()"
                                style="padding:8px 16px;border:1px solid #e2e8f0;border-radius:7px;background:#fff;color:#475569;font-size:13px;cursor:pointer;font-weight:500;">
                                Hủy
                            </button>
                            <button id="btn-confirm-approve"
                                style="padding:8px 18px;border:none;border-radius:7px;background:#16a34a;color:#fff;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

            document.getElementById('btn-confirm-approve').addEventListener('click', function() {
                submitPasxApprove(this, overlay);
            });
        }

        function submitPasxApprove(btn, overlay) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'approve_pasx',
                    id:     PAKD_ID,
                })
            })
            .then(r => r.json())
            .then(data => {
                overlay.remove();
                if (data.ok) {
                    showToast(data.msg || 'Approve thành công!', 'success');
                    updateStatusBanner('AM tiến hành gửi báo giá và xác nhận', '#16a34a', 'fa-check-square');
                    // Ẩn nút approve, không cho bấm lại
                    const container = document.getElementById('pasx-action-btns');
                    if (container) container.innerHTML =
                        '<span style="font-size:12px;color:rgba(255,255,255,.8);"><i class="fas fa-check-circle"></i> Đã Approve</span>';
                    // Khoá toàn bộ field ngay lập tức
                    document.querySelectorAll('.detail-container input, .detail-container select, .detail-container textarea').forEach(function (el) {
                        el.disabled = true;
                    });
                    document.querySelector('.detail-container')?.classList.add('page-locked');
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Approve';
                }
            })
            .catch(() => {
                overlay.remove();
                showToast('Lỗi kết nối, vui lòng thử lại', 'error');
            });
        }

        function pasxGetApproveCEO() {
            // Hiện dialog xác nhận trước khi gửi
            const overlay = document.createElement('div');
            overlay.id = 'ceo-approve-dialog-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2000;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
                    <div style="padding:18px 20px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-user-tie" style="color:#d97706;font-size:14px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:#0f172a;font-size:.95rem;">Yêu cầu CEO phê duyệt</div>
                            <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Gửi PASX này lên CEO để xét duyệt đặc biệt (margin &lt; 20%)</div>
                        </div>
                    </div>
                    <div style="padding:16px 20px 12px;font-size:.85rem;color:#475569;line-height:1.65;">
                        PASX này có <strong>margin &lt; 20%</strong>, không đủ điều kiện tự approve.<br>
                        Bạn muốn gửi yêu cầu phê duyệt lên <strong>CEO</strong>?<br>
                        <span style="color:#94a3b8;font-size:.8rem;">CEO sẽ nhận được thông báo và có thể approve hoặc reject.</span>
                    </div>
                    <div style="padding:0 20px 14px;">
                        <label style="display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px;">
                            Ghi chú gửi CEO <span style="color:#94a3b8;font-weight:400;">(tuỳ chọn)</span>
                        </label>
                        <textarea id="ceo-approve-message"
                            placeholder="Nhập lý do hoặc thông tin bổ sung cho CEO..."
                            rows="3"
                            style="width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:.83rem;font-family:inherit;color:#1e293b;resize:vertical;outline:none;transition:border-color .15s;line-height:1.55;"
                            onfocus="this.style.borderColor='#d97706';this.style.boxShadow='0 0 0 2px #fef3c7'"
                            onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'"
                        ></textarea>
                    </div>
                    <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:8px;">
                        <button onclick="document.getElementById('ceo-approve-dialog-overlay').remove()"
                            style="padding:7px 16px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;color:#64748b;font-size:.85rem;cursor:pointer;font-family:inherit;">
                            Huỷ
                        </button>
                        <button id="ceo-approve-confirm-btn" onclick="submitCeoApproveRequest()"
                            style="padding:7px 16px;border:none;border-radius:6px;background:#d97706;color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:inherit;">
                            <i class="fas fa-user-tie"></i> Gửi lên CEO
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
        }

        function submitCeoApproveRequest() {
            const btn = document.getElementById('ceo-approve-confirm-btn');
            if (!btn) return;
            const message = (document.getElementById('ceo-approve-message')?.value || '').trim();
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_ceo_approve', id: PAKD_ID, message: message })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('ceo-approve-dialog-overlay')?.remove();
                if (data.ok) {
                    showToast('Đã gửi yêu cầu lên CEO thành công', 'success');
                    // Cập nhật action buttons
                    const container = document.getElementById('pasx-action-btns');
                    if (container) {
                        container.innerHTML = '<span style="font-size:12px;color:#d97706;font-weight:500;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-user-tie"></i> Đã gửi lên CEO — đang chờ phê duyệt</span>';
                    }
                    // Cập nhật status banner
                    updateStatusBanner('Chờ CEO duyệt · PASX đang chờ phê duyệt', '#d97706', 'fa-user-tie');
                } else {
                    showToast(data.msg || 'Gửi yêu cầu thất bại', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-user-tie"></i> Gửi lên CEO';
                }
            })
            .catch(function() {
                document.getElementById('ceo-approve-dialog-overlay')?.remove();
                showToast('Lỗi kết nối, vui lòng thử lại', 'error');
            });
        }

        function pasxRejectRebuild() {
            // Hiện dialog nhập lý do
            const overlay = document.createElement('div');
            overlay.id = 'reject-dialog-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:2000;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;width:440px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">
                    <div style="padding:18px 20px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:8px;background:#fef2f2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-redo" style="color:#dc2626;font-size:14px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:#0f172a;font-size:.95rem;">Reject / Rebuild PASX</div>
                            <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Yêu cầu hệ thống Profile làm lại Phương án sản xuất</div>
                        </div>
                    </div>
                    <div style="padding:16px 20px;">
                        <label style="display:block;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                            Lý do từ chối <span style="color:#dc2626;">*</span>
                        </label>
                        <textarea id="reject-reason-inp" rows="3" placeholder="Nhập lý do cụ thể để bên Profile biết cần điều chỉnh gì..."
                            style="width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:9px 11px;font-size:.85rem;font-family:inherit;color:#0f172a;resize:vertical;outline:none;box-sizing:border-box;"></textarea>
                        <div id="reject-reason-err" style="display:none;color:#dc2626;font-size:.75rem;margin-top:4px;"><i class="fas fa-exclamation-circle"></i> Vui lòng nhập lý do</div>
                    </div>
                    <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:8px;">
                        <button onclick="document.getElementById('reject-dialog-overlay').remove()"
                            style="padding:7px 16px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;color:#64748b;font-size:.85rem;cursor:pointer;">
                            Huỷ
                        </button>
                        <button id="reject-confirm-btn" onclick="submitPasxReject()"
                            style="padding:7px 16px;border:none;border-radius:6px;background:#dc2626;color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-redo"></i> Xác nhận Reject
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
            setTimeout(() => document.getElementById('reject-reason-inp')?.focus(), 50);
        }

        function submitPasxReject() {
            const reason = (document.getElementById('reject-reason-inp')?.value || '').trim();
            const errEl  = document.getElementById('reject-reason-err');
            if (!reason) {
                errEl.style.display = 'block';
                document.getElementById('reject-reason-inp').style.borderColor = '#dc2626';
                return;
            }
            const btn = document.getElementById('reject-confirm-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

            fetch('/projects/pakd/edit?id=' + PAKD_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'reject_pasx', id: PAKD_ID, reason })
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('reject-dialog-overlay')?.remove();
                if (data.ok) {
                    showToast(data.msg, 'success');
                    // Cập nhật action buttons
                    const container = document.getElementById('pasx-action-btns');
                    if (container) {
                        container.innerHTML = '<span style="font-size:12px;color:#b45309;font-weight:500;"><i class="fas fa-clock"></i> Đã Reject — đang chờ Profile rebuild</span>';
                    }
                    // Cập nhật status banner ngay lập tức
                    updateStatusBanner('Đã Reject PASX · Đang chờ Profile rebuild', '#b45309', 'fa-clock');
                } else {
                    showToast(data.msg || 'Có lỗi xảy ra', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo"></i> Xác nhận Reject';
                }
            })
            .catch(err => {
                showToast('Lỗi kết nối: ' + err.message, 'error');
                document.getElementById('reject-dialog-overlay')?.remove();
            });
        }
    </script>
    <style>
        @keyframes toastIn { from { transform: translateY(16px); opacity: 0; } to { transform: none; opacity: 1; } }

        .pasx-processing-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            padding: 2px 8px;
            background: #fefce8;
            border: 1px solid #fde047;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            color: #a16207;
        }
        .pasx-processing-label i { font-size: 10px; }

        .project-type-select {
            padding: 3px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13px;
            color: #334155;
            background: #fff;
            cursor: pointer;
            outline: none;
        }
        .project-type-select:focus { border-color: #3b82f6; }

        .pasx-sent-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            padding: 2px 8px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            font-size: 11px;
            color: #15803d;
            font-weight: 500;
        }
        .pasx-sent-badge i { color: #16a34a; }
        .pasx-sent-badge code {
            font-family: monospace;
            font-size: 11px;
            color: #166534;
            background: none;
        }
        .pasx-sent-badge .pasx-sent-at {
            color: #6b7280;
            font-weight: 400;
        }

        .btn-req-pasx {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 16px;
            padding: 4px 12px;
            background: #0ea5e9;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background .2s;
        }
        .btn-req-pasx:hover:not(:disabled) { background: #0284c7; }
        .btn-req-pasx:disabled { opacity: .55; cursor: not-allowed; }

        /* PASX action buttons (after data received) */
        .btn-pasx-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
            padding: 4px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background .2s, opacity .2s;
        }
        .btn-pasx-approve {
            background: #16a34a;
            color: #fff;
        }
        .btn-pasx-approve:hover { background: #15803d; }

        .btn-pasx-ceo {
            background: #7c3aed;
            color: #fff;
        }
        .btn-pasx-ceo:hover { background: #6d28d9; }

        .btn-pasx-reject {
            background: #fff;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .btn-pasx-reject:hover { background: #fef2f2; }

        /* History icon button */
        .btn-pasx-history {
            background: none; border: none; cursor: pointer;
            color: #94a3b8; padding: 0 3px; font-size: 11px;
            vertical-align: middle; transition: color .2s;
        }
        .btn-pasx-history:hover { color: #6366f1; }

        /* History sidebar/drawer */
        .pasx-history-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.35); z-index: 1000;
            opacity: 0; transition: opacity .55s cubic-bezier(.25,.46,.45,.94);
        }
        .pasx-history-overlay.open { display: block; }
        .pasx-history-overlay.open-active { opacity: 1; }

        .pasx-history-modal {
            position: fixed; top: 0; right: 0;
            width: 50%; height: 100vh;
            background: #fff;
            box-shadow: -8px 0 40px rgba(15,23,42,.18);
            display: flex; flex-direction: column;
            transform: translateX(100%);
            transition: transform .55s cubic-bezier(.25,.46,.45,.94);
            border-radius: 0;
        }
        .pasx-history-overlay.open .pasx-history-modal {
            transform: translateX(0);
        }

        .phm-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 1.5rem; border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0; background: #fff;
            position: sticky; top: 0; z-index: 1;
        }
        .phm-header h3 { margin: 0; font-size: .95rem; font-weight: 600; color: #0f172a; }
        .phm-close {
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 6px;
            background: none; border: 1px solid #e2e8f0; cursor: pointer;
            color: #64748b; font-size: 1rem; line-height: 1; transition: background .15s;
        }
        .phm-close:hover { background: #f1f5f9; color: #0f172a; }
        .phm-body { padding: 1rem 1.5rem; overflow-y: auto; flex: 1; }

        /* PASX ID info strip in sidebar header */
        .phm-pasx-info {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            padding: 5px 10px; border-radius: 6px;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            font-size: .75rem;
        }
        .phm-pasx-label {
            font-size: .68rem; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .phm-pasx-code {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: .78rem; color: #4f46e5; font-weight: 600;
            background: #ede9fe; padding: 1px 6px; border-radius: 4px;
            letter-spacing: .02em;
        }
        .phm-pasx-at {
            display: flex; align-items: center; gap: 4px;
            color: #64748b; font-size: .73rem;
        }
        .phm-pasx-status {
            padding: 1px 7px; border-radius: 10px; font-size: .7rem; font-weight: 700;
            background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;
            letter-spacing: .04em;
        }
        .phm-loading { text-align: center; padding: 2rem; color: #94a3b8; font-size: .9rem; }
        .phm-empty  { text-align: center; padding: 2rem; color: #94a3b8; font-size: .875rem; }
        .phm-table  { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .phm-table th {
            background: #f8fafc; padding: 8px 10px; text-align: left;
            font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .phm-table td {
            padding: 8px 10px; border-bottom: 1px solid #f1f5f9;
            vertical-align: top; color: #334155;
        }
        .phm-table tr:hover td { background: #f8fafc; }
        .phm-badge {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: .75rem; font-weight: 500;
        }
        .phm-badge-ok     { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .phm-badge-err    { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .phm-badge-warn   { background: #fefce8; color: #a16207; border: 1px solid #fde047; }
        .phm-currency     { text-align: right; font-variant-numeric: tabular-nums; color: #1e40af; }

        /* Toggle button for PASX cost detail */
        .phm-toggle-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 12px; font-size: .75rem; font-weight: 500;
            background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
            cursor: pointer; white-space: nowrap; transition: background .15s;
        }
        .phm-toggle-btn:hover { background: #dbeafe; border-color: #93c5fd; }
        .phm-chevron { font-size: .65rem; transition: transform .2s; margin-left: 2px; }

        /* ── Change Request rows ── */
        .btn-add-cr {
            background: none; border: 1px solid #cbd5e1; border-radius: 4px;
            color: #64748b; cursor: pointer; padding: 3px 8px; font-size: 11px;
            transition: background .15s, border-color .15s, color .15s;
        }
        .btn-add-cr:hover { background: #6366f1; border-color: #6366f1; color: #fff; }

        .cr-name-inp {
            width: 100%; border: 1px solid transparent; border-radius: 4px;
            padding: 2px 6px; font-size: 12.5px; font-family: inherit;
            color: var(--slate); background: transparent; outline: none; box-sizing: border-box;
        }
        .cr-name-inp:hover, .cr-name-inp:focus { border-color: var(--border); background: #fff; }
        .cr-name-inp::placeholder { color: var(--lgray); }

        .cr-amt-inp {
            border: 1px solid transparent; border-radius: 4px;
            padding: 2px 6px; font-size: 12.5px; font-family: inherit;
            color: var(--slate); background: transparent; outline: none;
            text-align: right; box-sizing: border-box; width: 100%;
        }
        .cr-amt-inp:hover, .cr-amt-inp:focus { border-color: var(--border); background: #fff; }
        .cr-amt-inp::placeholder { color: var(--lgray); }

        .btn-del-cr {
            background: none; border: none; cursor: pointer;
            color: #94a3b8; padding: 2px 6px; font-size: 11px;
            transition: color .15s;
        }
        .btn-del-cr:hover { color: #dc2626; }

        /* Resend button */
        .btn-resend-pasx {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 8px;
            padding: 3px 10px;
            background: #fff;
            color: #6366f1;
            border: 1px solid #a5b4fc;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s, border-color .15s;
        }
        .btn-resend-pasx:hover:not(:disabled) { background: #eef2ff; border-color: #6366f1; }
        .btn-resend-pasx:disabled { opacity: .55; cursor: not-allowed; }
    </style>

<!-- PASX History Sidebar -->
<div class="pasx-history-overlay" id="pasxHistoryOverlay" onclick="closePasxHistory(event)">
    <div class="pasx-history-modal">
        <div class="phm-header">
            <div style="flex:1;min-width:0;">
                <h3 style="margin:0 0 6px 0;">
                    <i class="fas fa-history" style="color:#6366f1;margin-right:8px;"></i>Lịch sử cập nhật từ ArrowHitech Profile
                </h3>
                <?php if (!empty($pakd['pasx_id'])): ?>
                <div class="phm-pasx-info">
                    <span class="phm-pasx-label">PASX ID</span>
                    <code class="phm-pasx-code"><?= htmlspecialchars($pakd['pasx_id']) ?></code>
                    <?php if ($pakd['pasx_requested_at']): ?>
                        <span class="phm-pasx-at"><i class="fas fa-clock" style="font-size:10px;"></i> <?= date('d/m/Y H:i', strtotime($pakd['pasx_requested_at'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pakd['pasx_status'])): ?>
                        <span class="phm-pasx-status"><?= htmlspecialchars(strtoupper($pakd['pasx_status'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <button class="phm-close" onclick="closePasxHistory()" style="flex-shrink:0;margin-left:12px;">&times;</button>
        </div>
        <div class="phm-body" id="pasxHistoryBody">
            <div class="phm-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
        </div>
    </div>
</div>

<script>
function openPasxHistory() {
    const overlay = document.getElementById('pasxHistoryOverlay');
    overlay.classList.add('open');
    requestAnimationFrame(() => requestAnimationFrame(() => overlay.classList.add('open-active')));
    loadPasxHistory();
}

function closePasxHistory(e) {
    const overlay = document.getElementById('pasxHistoryOverlay');
    const modal   = overlay?.querySelector('.pasx-history-modal');
    if (!e || (e.target === overlay && modal && !modal.contains(e.target))) {
        overlay.classList.remove('open-active');
        setTimeout(() => overlay.classList.remove('open'), 550);
    }
}

function loadPasxHistory() {
    const body = document.getElementById('pasxHistoryBody');
    body.innerHTML = '<div class="phm-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

    fetch('/projects/pakd/edit?id=' + PAKD_ID, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action:   'get_pasx_history',
            pakd_id:  PAKD_ID,
            opp_id:   '<?= htmlspecialchars($pakd['odoo_opp_id'] ?? '') ?>',
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok || !data.logs || data.logs.length === 0) {
            body.innerHTML = '<div class="phm-empty"><i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>Chưa có lịch sử cập nhật</div>';
            return;
        }

        const fmtNum = n => n != null ? Number(n).toLocaleString('vi-VN') : '—';
        const fmtDate = s => {
            if (!s) return '—';
            const d = new Date(s.replace(' ', 'T'));
            return d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        };
        const statusBadge = (s, http) => {
            if (s === 'auth_failed' || (http && http >= 400)) return `<span class="phm-badge phm-badge-err">${s || 'error'}</span>`;
            if (!s) return '<span class="phm-badge phm-badge-warn">—</span>';
            return `<span class="phm-badge phm-badge-ok">${s}</span>`;
        };

        const buildPathHtml = (path, name) => {
            const raw    = path || name || '—';
            const parts  = raw.split('>').map(s => s.trim()).filter(Boolean);
            if (parts.length <= 1) {
                return `<div style="font-weight:600;color:#0f172a;">${parts[0] || '—'}</div>`;
            }
            return parts.map((part, i) => {
                const isLast   = i === parts.length - 1;
                const indent   = i * 14;
                const arrow    = i > 0 ? '<span style="color:#cbd5e1;margin-right:5px;font-size:.7rem;">↳</span>' : '';
                const style    = isLast
                    ? `font-weight:600;color:#0f172a;`
                    : `color:#94a3b8;font-size:.75rem;`;
                return `<div style="padding-left:${indent}px;${style}line-height:1.5;">${arrow}${part}</div>`;
            }).join('');
        };

        const buildCostRows = (pasxCost) => {
            const active = (pasxCost || []).filter(c => (c.total || 0) > 0);
            if (!active.length) return '';
            const grandTotal = active.reduce((s, c) => s + (c.total || 0), 0);
            const rows = active.map(c => `
                <tr style="background:#fff;">
                    <td style="padding:8px 12px;">${buildPathHtml(c.path, c.name)}</td>
                    <td style="padding:8px 12px;text-align:right;color:#475569;white-space:nowrap;">${fmtNum(c.unit)}${c.unitType ? ' <span style="font-size:.7rem;color:#94a3b8;">'+c.unitType+'</span>' : ''}</td>
                    <td style="padding:8px 12px;text-align:right;color:#475569;white-space:nowrap;">${fmtNum(c.amount)}</td>
                    <td style="padding:8px 12px;text-align:right;font-weight:600;color:#1e40af;white-space:nowrap;">${fmtNum(c.total)} ₫</td>
                </tr>`).join('');
            const thStyle = 'padding:7px 12px;font-weight:600;color:#475569;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;';
            return `
                <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                    <thead><tr style="background:#f1f5f9;">
                        <th style="${thStyle}text-align:left;">Hạng mục / Path</th>
                        <th style="${thStyle}text-align:right;">Đơn giá</th>
                        <th style="${thStyle}text-align:right;">Số lượng</th>
                        <th style="${thStyle}text-align:right;">Thành tiền</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot><tr style="background:#f8fafc;border-top:2px solid #e2e8f0;">
                        <td colspan="3" style="padding:8px 12px;text-align:right;font-weight:700;color:#0f172a;">Tổng cộng</td>
                        <td style="padding:8px 12px;text-align:right;font-weight:700;color:#1d4ed8;white-space:nowrap;">${fmtNum(grandTotal)} ₫</td>
                    </tr></tfoot>
                </table>`;
        };

        let rowIdx = 0;
        let html = `
        <table class="phm-table">
            <thead>
                <tr>
                    <th>Thời gian nhận</th>
                    <th>Người gửi</th>
                    <th>Event / Status</th>
                    <th style="text-align:right">Human Cost</th>
                    <th style="text-align:right">Overtime Cost</th>
                    <th style="text-align:center">Chi tiết</th>
                </tr>
            </thead>
            <tbody>`;

        data.logs.forEach(log => {
            const activeCost = (log.pasxCost || []).filter(c => (c.total || 0) > 0);
            const hasCost = activeCost.length > 0;
            const rid = 'phm-cost-' + (rowIdx++);

            const submittedBy = log.submitted_by || '—';
            const submittedAt = log.submitted_at
                ? `<div style="font-size:.71rem;color:#94a3b8;margin-top:2px;">${fmtDate(log.submitted_at)}</div>`
                : '';

            const costBtn = hasCost
                ? `<button class="phm-toggle-btn" onclick="phmToggle('${rid}')">
                       <i class="fas fa-layer-group"></i> ${activeCost.length} hạng mục
                       <i class="fas fa-chevron-down phm-chevron" id="${rid}-icon"></i>
                   </button>`
                : `<span style="color:#cbd5e1;font-size:.78rem;">—</span>`;

            html += `
            <tr>
                <td style="white-space:nowrap;color:#64748b;vertical-align:top;">${fmtDate(log.received_at)}</td>
                <td style="vertical-align:top;">
                    <div style="font-weight:500;color:#334155;">${submittedBy}</div>${submittedAt}
                </td>
                <td style="vertical-align:top;">
                    <code style="font-size:.78rem;color:#6366f1;">${log.event || '—'}</code>
                    <div style="margin-top:4px;">${statusBadge(log.status, log.http_status)}</div>
                </td>
                <td class="phm-currency" style="vertical-align:top;">${log.humanCost != null ? fmtNum(log.humanCost) + ' ₫' : '—'}</td>
                <td class="phm-currency" style="vertical-align:top;">${log.overtimeCost != null ? fmtNum(log.overtimeCost) + ' ₫' : '—'}</td>
                <td style="text-align:center;vertical-align:top;">${costBtn}</td>
            </tr>
            ${hasCost ? `<tr id="${rid}" style="display:none;">
                <td colspan="6" style="padding:0;background:#f8fafc;border-bottom:2px solid #6366f1;">
                    ${buildCostRows(log.pasxCost)}
                </td>
            </tr>` : ''}`;
        });

        html += '</tbody></table>';
        body.innerHTML = html;
    })
    .catch(() => {
        body.innerHTML = '<div class="phm-empty" style="color:#dc2626;">Lỗi khi tải lịch sử</div>';
    });
}

function phmToggle(rid) {
    const row  = document.getElementById(rid);
    const icon = document.getElementById(rid + '-icon');
    if (!row) return;
    const open = row.style.display !== 'none';
    row.style.display  = open ? 'none' : 'table-row';
    if (icon) icon.style.transform = open ? '' : 'rotate(180deg)';
}

// Đóng bằng phím Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closePasxHistory({target: document.getElementById('pasxHistoryOverlay')});
        if (document.getElementById('pakd-chat-panel')?.classList.contains('open')) chatToggle();
    }
});

// ── Tooltip ghi chú Profile (position:fixed tránh overflow clip) ──
(function() {
    const tip = document.createElement('div');
    tip.id = 'pasx-note-tooltip';
    tip.style.cssText = 'display:none;position:fixed;z-index:9999;background:#1e293b;color:#f1f5f9;font-size:11px;line-height:1.6;padding:10px 13px;border-radius:8px;max-width:300px;box-shadow:0 4px 16px rgba(0,0,0,.3);pointer-events:none;';
    document.body.appendChild(tip);

    document.querySelectorAll('.pasx-note-icon').forEach(function(icon) {
        icon.addEventListener('mouseenter', function(e) {
            const note = icon.dataset.note || '';
            if (!note) return;
            tip.innerHTML = '<strong style="display:block;color:#fbbf24;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;">Ghi chú từ Profile</strong>' + note.replace(/\n/g,'<br>');
            tip.style.display = 'block';
            const r = icon.getBoundingClientRect();
            const tw = tip.offsetWidth;
            let left = r.left + r.width / 2 - tw / 2;
            if (left < 8) left = 8;
            if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
            tip.style.left = left + 'px';
            tip.style.top  = (r.top - tip.offsetHeight - 8) + 'px';
        });
        icon.addEventListener('mouseleave', function() { tip.style.display = 'none'; });
    });
})();
</script>

<!-- ══════════════════════════════════════════════
     PAKD Chat Widget
══════════════════════════════════════════════ -->
<style>
#pakd-chat-btn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 1200;
    width: 52px; height: 52px;
    border-radius: 50%;
    background: #6366f1;
    border: none;
    box-shadow: 0 4px 18px rgba(99,102,241,.45);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 20px;
    transition: background .2s, transform .2s, box-shadow .2s;
}
#pakd-chat-btn:hover { background: #4f46e5; transform: scale(1.08); box-shadow: 0 6px 24px rgba(99,102,241,.55); }
#pakd-chat-btn .chat-unread {
    position: absolute; top: 2px; right: 2px;
    background: #dc2626; color: #fff;
    width: 17px; height: 17px; border-radius: 50%;
    font-size: 10px; font-weight: 700;
    display: none; align-items: center; justify-content: center;
    border: 2px solid #fff;
}

#pakd-chat-panel {
    position: fixed;
    bottom: 90px; right: 28px;
    z-index: 1200;
    width: 360px;
    max-height: 520px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 40px rgba(15,23,42,.18);
    display: flex; flex-direction: column;
    overflow: hidden;
    transform: scale(.9) translateY(20px);
    opacity: 0;
    pointer-events: none;
    transition: transform .22s cubic-bezier(.34,1.56,.64,1), opacity .18s;
}
#pakd-chat-panel.open {
    transform: scale(1) translateY(0);
    opacity: 1;
    pointer-events: auto;
}

.chat-header {
    background: #6366f1;
    padding: 13px 16px;
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
}
.chat-header-icon {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; color: #fff; flex-shrink: 0;
}
.chat-header-info { flex: 1; min-width: 0; }
.chat-header-title { font-size: 13px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-header-sub   { font-size: 11px; color: rgba(255,255,255,.75); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-close-btn {
    background: rgba(255,255,255,.15); border: none; border-radius: 6px;
    width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
    color: #fff; cursor: pointer; font-size: 12px;
    transition: background .15s;
}
.chat-close-btn:hover { background: rgba(255,255,255,.3); }

.chat-body {
    flex: 1; overflow-y: auto; padding: 14px 14px 8px;
    display: flex; flex-direction: column; gap: 10px;
    min-height: 0;
    scroll-behavior: smooth;
}
.chat-body::-webkit-scrollbar { width: 4px; }
.chat-body::-webkit-scrollbar-track { background: transparent; }
.chat-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.chat-msg { display: flex; flex-direction: column; max-width: 82%; }
.chat-msg.sent  { align-self: flex-end; align-items: flex-end; }
.chat-msg.recv  { align-self: flex-start; align-items: flex-start; }
.chat-msg.system { align-self: center; align-items: center; max-width: 100%; }

.chat-bubble {
    padding: 8px 12px; border-radius: 12px;
    font-size: 12.5px; line-height: 1.55; word-break: break-word;
}
.chat-msg.sent  .chat-bubble { background: #6366f1; color: #fff; border-bottom-right-radius: 4px; }
.chat-msg.recv  .chat-bubble { background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px; }
.chat-msg.system .chat-bubble {
    background: #f8fafc; color: #64748b; font-size: 11px;
    padding: 5px 10px; border-radius: 8px; border: 1px solid #e2e8f0;
}

.chat-meta { font-size: 10.5px; color: #94a3b8; margin-top: 3px; }

.chat-imgs { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
.chat-imgs img {
    width: 80px; height: 80px; object-fit: cover; border-radius: 8px;
    cursor: zoom-in; border: 1px solid rgba(0,0,0,.08);
    transition: opacity .15s;
}
.chat-imgs img:hover { opacity: .85; }

.chat-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: #94a3b8; font-size: 12px; gap: 6px; padding: 20px;
}
.chat-empty i { font-size: 2rem; color: #e2e8f0; }

.chat-preview-bar {
    padding: 8px 12px 0;
    display: flex; flex-wrap: wrap; gap: 6px;
    border-top: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.preview-thumb {
    position: relative; width: 52px; height: 52px;
}
.preview-thumb img {
    width: 52px; height: 52px; object-fit: cover;
    border-radius: 7px; border: 1px solid #e2e8f0;
}
.preview-thumb .rm-img {
    position: absolute; top: -5px; right: -5px;
    background: #dc2626; color: #fff; border: none;
    border-radius: 50%; width: 16px; height: 16px;
    font-size: 9px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}

.chat-footer {
    padding: 10px 12px 12px;
    border-top: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.chat-input-row {
    display: flex; align-items: flex-end; gap: 7px;
}
.chat-textarea {
    flex: 1; resize: none; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 8px 11px; font-size: 12.5px; font-family: inherit; color: #1e293b;
    outline: none; line-height: 1.5; max-height: 110px; min-height: 38px;
    overflow-y: auto; background: #f8fafc;
    transition: border-color .15s, background .15s;
}
.chat-textarea:focus { border-color: #6366f1; background: #fff; }
.chat-textarea::placeholder { color: #94a3b8; }

.chat-attach-btn, .chat-send-btn {
    flex-shrink: 0; border: none; border-radius: 9px;
    width: 36px; height: 36px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; transition: background .15s, transform .15s;
}
.chat-attach-btn {
    background: #f1f5f9; color: #64748b;
}
.chat-attach-btn:hover { background: #e2e8f0; color: #334155; }
.chat-send-btn {
    background: #6366f1; color: #fff;
}
.chat-send-btn:hover:not(:disabled) { background: #4f46e5; transform: scale(1.05); }
.chat-send-btn:disabled { background: #c7d2fe; cursor: not-allowed; }

.chat-pasx-tag {
    font-size: 10px; color: #94a3b8; text-align: right;
    margin-top: 5px; padding-right: 2px;
}
.chat-pasx-tag code { color: #6366f1; font-size: 10px; }

/* Image lightbox */
#chat-lightbox {
    display: none;
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.85);
    align-items: center; justify-content: center;
}
#chat-lightbox.show { display: flex; }
#chat-lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 10px; box-shadow: 0 8px 40px rgba(0,0,0,.5); }
#chat-lightbox-close {
    position: absolute; top: 20px; right: 24px;
    background: rgba(255,255,255,.15); border: none; border-radius: 8px;
    color: #fff; font-size: 20px; cursor: pointer; padding: 6px 10px;
}
</style>

<!-- Chat toggle button -->
<button id="pakd-chat-btn" onclick="chatToggle()" title="Chat với Profile">
    <i class="fas fa-comments" id="chat-btn-icon"></i>
    <span class="chat-unread" id="chat-unread-badge"></span>
</button>

<!-- Chat panel -->
<div id="pakd-chat-panel">
    <div class="chat-header">
        <div class="chat-header-icon"><i class="fas fa-headset"></i></div>
        <div class="chat-header-info">
            <div class="chat-header-title">Thảo luận với Delivery về opp <?= htmlspecialchars($pakd['opportunity_name'] ?? '') ?></div>
            <div class="chat-header-sub" id="chat-header-pasx">
                <?php if (!empty($pakd['pasx_id'])): ?>
                    PASX: <strong><?= htmlspecialchars($pakd['pasx_id']) ?></strong>
                <?php else: ?>
                    Chưa có PASX ID
                <?php endif; ?>
            </div>
        </div>
        <button class="chat-close-btn" onclick="chatToggle()"><i class="fas fa-times"></i></button>
    </div>

    <div class="chat-body" id="chat-body">
        <div class="chat-empty" id="chat-empty-state">
            <i class="fas fa-comments"></i>
            <div>Chưa có tin nhắn nào</div>
            <div style="font-size:11px;">Gửi tin nhắn để trao đổi với ArrowHitech Profile về PASX này</div>
        </div>
    </div>

    <div class="chat-preview-bar" id="chat-preview-bar" style="display:none"></div>

    <div class="chat-footer">
        <div class="chat-input-row">
            <textarea id="chat-textarea" class="chat-textarea" rows="1"
                placeholder="Nhập tin nhắn..." maxlength="2000"
                onkeydown="chatKeydown(event)" oninput="chatAutoResize(this)"></textarea>
            <label class="chat-attach-btn" title="Đính kèm ảnh" for="chat-file-input">
                <i class="fas fa-paperclip"></i>
            </label>
            <input type="file" id="chat-file-input" multiple accept="image/*"
                style="display:none" onchange="chatPreviewImages(this)">
            <button class="chat-send-btn" id="chat-send-btn" onclick="chatSend()" title="Gửi (Enter)">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <?php if (!empty($pakd['pasx_id'])): ?>
        <div class="chat-pasx-tag">
            Gửi tới PASX <code><?= htmlspecialchars($pakd['pasx_id']) ?></code>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image lightbox -->
<div id="chat-lightbox" onclick="chatLightboxClose()">
    <button id="chat-lightbox-close" onclick="chatLightboxClose()"><i class="fas fa-times"></i></button>
    <img id="chat-lightbox-img" src="" alt="">
</div>

<script>
(function() {
    const PAKD_ID_CHAT = <?= (int)($pakd['id'] ?? 0) ?>;
    const PASX_ID_CHAT = <?= json_encode($pakd['pasx_id'] ?? null) ?>;
    const SENDER_NAME  = <?= json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'AM') ?>;

    let chatOpen      = false;
    let pendingFiles  = []; // {file, url}
    let lastMsgId     = 0;
    let pollTimer     = null;
    let unreadCount   = 0;
    let initialLoaded = false;

    // ── Toggle panel ──
    window.chatToggle = function() {
        chatOpen = !chatOpen;
        const panel = document.getElementById('pakd-chat-panel');
        const icon  = document.getElementById('chat-btn-icon');
        panel.classList.toggle('open', chatOpen);
        icon.className = chatOpen ? 'fas fa-times' : 'fas fa-comments';
        if (chatOpen) {
            unreadCount = 0;
            const badge = document.getElementById('chat-unread-badge');
            badge.style.display = 'none';
            badge.textContent = '';
            if (!initialLoaded) loadMessages(true);
            startPolling();
            setTimeout(() => document.getElementById('chat-textarea')?.focus(), 220);
        } else {
            stopPolling();
        }
    };

    // ── Load messages from DB ──
    async function loadMessages(initial = false) {
        try {
            const fd = new FormData();
            fd.append('action', 'get_chat_messages');
            fd.append('id', PAKD_ID_CHAT);
            fd.append('after_id', initial ? 0 : lastMsgId);
            const res  = await fetch('/projects/pakd/edit?id=' + PAKD_ID_CHAT, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok || !data.msgs?.length) return;

            data.msgs.forEach(m => {
                // Bỏ qua nếu đã render (optimistic sent)
                if (document.getElementById('db-msg-' + m.id)) return;
                renderDbMsg(m, !initial);
                if (m.id > lastMsgId) lastMsgId = parseInt(m.id);
            });
            initialLoaded = true;
        } catch(e) {}
    }

    function renderDbMsg(m, isNew) {
        const body = document.getElementById('chat-body');
        document.getElementById('chat-empty-state')?.remove();

        const isSent = m.direction === 'sent';
        const div    = document.createElement('div');
        div.className = 'chat-msg ' + (isSent ? 'sent' : 'recv');
        div.id = 'db-msg-' + m.id;

        const time = m.created_at ? new Date(m.created_at.replace(' ','T')) : new Date();
        let imgHtml = '';
        if (m.images?.length) {
            imgHtml = '<div class="chat-imgs">' +
                m.images.map(u => `<img src="${escAttr(u)}" loading="lazy" onclick="chatLightboxOpen('${escAttr(u)}')" alt="">`).join('') +
            '</div>';
        }
        const bubbleHtml = m.message ? `<div class="chat-bubble">${escHtml(m.message)}</div>` : '';
        const sentMark   = isSent ? ' · <span style="color:#86efac">✓</span>' : '';
        div.innerHTML = bubbleHtml + imgHtml +
            `<div class="chat-meta">${escHtml(m.sender_name || '')} · ${chatFmtTime(time)}${sentMark}</div>`;

        body.appendChild(div);
        if (isNew && !chatOpen) {
            unreadCount++;
            const badge = document.getElementById('chat-unread-badge');
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            badge.style.display = 'flex';
        }
        if (chatOpen) body.scrollTop = body.scrollHeight;
    }

    // ── Polling ──
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(() => loadMessages(false), 5000);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    // ── Input helpers ──
    window.chatKeydown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chatSend(); }
    };
    window.chatAutoResize = function(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 110) + 'px';
    };
    window.chatPreviewImages = function(input) {
        const bar = document.getElementById('chat-preview-bar');
        Array.from(input.files).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            pendingFiles.push({file, url});
            const thumb = document.createElement('div');
            thumb.className = 'preview-thumb';
            thumb.dataset.url = url;
            thumb.innerHTML = `<img src="${url}"><button class="rm-img" onclick="chatRemoveImg(this)"><i class="fas fa-times"></i></button>`;
            bar.appendChild(thumb);
        });
        bar.style.display = pendingFiles.length ? 'flex' : 'none';
        input.value = '';
    };
    window.chatRemoveImg = function(btn) {
        const thumb = btn.closest('.preview-thumb');
        const url   = thumb.dataset.url;
        pendingFiles = pendingFiles.filter(f => f.url !== url);
        URL.revokeObjectURL(url);
        thumb.remove();
        if (!pendingFiles.length) document.getElementById('chat-preview-bar').style.display = 'none';
    };

    // ── Send ──
    window.chatSend = async function() {
        const ta  = document.getElementById('chat-textarea');
        const msg = ta.value.trim();
        if (!msg && !pendingFiles.length) return;

        const sendBtn = document.getElementById('chat-send-btn');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        // Optimistic UI
        const tempId = 'tmp-' + Date.now();
        renderOptimistic(tempId, msg, pendingFiles.map(f=>f.url));

        ta.value = '';
        ta.style.height = 'auto';
        const sentFiles = [...pendingFiles];
        pendingFiles = [];
        document.getElementById('chat-preview-bar').innerHTML = '';
        document.getElementById('chat-preview-bar').style.display = 'none';

        const fd = new FormData();
        fd.append('action', 'send_chat_message');
        fd.append('id', PAKD_ID_CHAT);
        fd.append('message', msg);
        sentFiles.forEach(f => fd.append('images[]', f.file));

        try {
            const res  = await fetch('/projects/pakd/edit?id=' + PAKD_ID_CHAT, { method: 'POST', body: fd });
            const data = await res.json();
            const tmpEl = document.getElementById(tempId);

            if (data.ok) {
                // Đổi ID sang ID thật, cập nhật ảnh server URL
                if (tmpEl) {
                    tmpEl.id = 'db-msg-' + data.id;
                    if (data.images?.length) {
                        tmpEl.querySelectorAll('.chat-imgs img').forEach((img, i) => {
                            if (data.images[i]) img.src = data.images[i];
                        });
                    }
                    tmpEl.querySelector('.chat-meta').innerHTML =
                        SENDER_NAME + ' · ' + chatFmtTime(new Date()) + ' · <span style="color:#86efac">✓ Đã gửi</span>';
                }
                if (data.id > lastMsgId) lastMsgId = data.id;
            } else {
                if (tmpEl) {
                    tmpEl.querySelector('.chat-bubble').style.cssText = 'background:#fee2e2;color:#991b1b;';
                    tmpEl.querySelector('.chat-meta').innerHTML =
                        '<span style="color:#dc2626"><i class="fas fa-exclamation-circle"></i> ' + escHtml(data.msg || 'Gửi thất bại') + '</span>';
                }
                showToast(data.msg || 'Gửi thất bại', 'error');
            }
        } catch (err) {
            const tmpEl = document.getElementById(tempId);
            if (tmpEl) tmpEl.querySelector('.chat-meta').innerHTML =
                '<span style="color:#dc2626"><i class="fas fa-exclamation-circle"></i> Lỗi kết nối</span>';
            showToast('Lỗi kết nối: ' + err.message, 'error');
        }

        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        sentFiles.forEach(f => URL.revokeObjectURL(f.url));
    };

    function renderOptimistic(id, text, blobUrls) {
        const body = document.getElementById('chat-body');
        document.getElementById('chat-empty-state')?.remove();
        const div = document.createElement('div');
        div.className = 'chat-msg sent';
        div.id = id;
        let imgHtml = '';
        if (blobUrls?.length) {
            imgHtml = '<div class="chat-imgs">' +
                blobUrls.map(u => `<img src="${u}" loading="lazy" onclick="chatLightboxOpen('${u}')" alt="">`).join('') +
            '</div>';
        }
        div.innerHTML =
            (text ? `<div class="chat-bubble">${escHtml(text)}</div>` : '') + imgHtml +
            `<div class="chat-meta">${escHtml(SENDER_NAME)} · ${chatFmtTime(new Date())} · <span style="color:#94a3b8">⏳</span></div>`;
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }

    function chatFmtTime(d) {
        return d.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
    }
    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }
    function escAttr(s) {
        return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    // ── Lightbox ──
    window.chatLightboxOpen = function(url) {
        document.getElementById('chat-lightbox-img').src = url;
        document.getElementById('chat-lightbox').classList.add('show');
    };
    window.chatLightboxClose = function() {
        document.getElementById('chat-lightbox').classList.remove('show');
    };
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') chatLightboxClose();
    });
})();
</script>
</body>
</html>
