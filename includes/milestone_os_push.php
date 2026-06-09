<?php
/**
 * Helper dùng chung: đẩy thông tin thanh toán 1 hoá đơn của 1 milestone sang hệ thống sản xuất (OS),
 * cập nhật bảng pakd_milestone_invoices + trạng thái milestone, ghi log.
 *
 * Dùng bởi: api/milestone_push_payment.php (AM bấm tay) và api/odoo_hook.php (auto khi invoice đổi).
 */

if (!function_exists('ms_os_log')) {
    function ms_os_log($conn, $pakd_id, $os_milestone_id, $event, $status, $payload_json, $http_code, $note) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS milestone_webhook_logs (
                id INT AUTO_INCREMENT PRIMARY KEY, pakd_id INT DEFAULT NULL, os_milestone_id VARCHAR(64) DEFAULT NULL,
                event VARCHAR(64) DEFAULT NULL, status VARCHAR(32) DEFAULT NULL, payload JSON DEFAULT NULL,
                http_status INT DEFAULT 200, note TEXT DEFAULT NULL, received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pakd (pakd_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $st = $conn->prepare("INSERT INTO milestone_webhook_logs (pakd_id, os_milestone_id, event, status, payload, http_status, note) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param("issssis", $pakd_id, $os_milestone_id, $event, $status, $payload_json, $http_code, $note);
            $st->execute(); $st->close();
        } catch (\Throwable $e) {}
    }
}

if (!function_exists('ms_os_ensure_link_table')) {
    function ms_os_ensure_link_table($conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS pakd_milestone_invoices (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            pakd_id           INT          DEFAULT NULL,
            milestone_id      INT          NOT NULL,
            os_milestone_id   VARCHAR(64)  DEFAULT NULL,
            invoice_odoo_id   INT          NOT NULL,
            invoice_code      VARCHAR(64)  DEFAULT NULL,
            invoice_status    VARCHAR(16)  DEFAULT NULL,
            payment_state     VARCHAR(16)  DEFAULT NULL,
            amount            DECIMAL(20,2) DEFAULT NULL,
            production_price  DECIMAL(20,2) DEFAULT NULL,
            currency          VARCHAR(10)  DEFAULT NULL,
            paid_at           DATE         DEFAULT NULL,
            note              VARCHAR(500) DEFAULT NULL,
            payment_pushed_at DATETIME     DEFAULT NULL,
            created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ms_inv (milestone_id, invoice_odoo_id),
            INDEX idx_ms (milestone_id),
            INDEX idx_inv (invoice_odoo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $conn->query("ALTER TABLE pakd_milestone_invoices ADD INDEX idx_inv (invoice_odoo_id)"); } catch (\Throwable $e) {}
    }
}

if (!function_exists('ms_os_config')) {
    function ms_os_config() {
        $f = __DIR__ . '/../config/arrowhitech_config.json';
        if (!file_exists($f)) return null;
        $cfg = json_decode(file_get_contents($f), true) ?: [];
        $api_url = rtrim($cfg['api_url'] ?? '', '/');
        $api_token = $cfg['api_token'] ?? '';
        if (!$api_url || !$api_token) return null;
        return ['api_url' => $api_url, 'api_token' => $api_token];
    }
}

/**
 * Đẩy 1 hoá đơn của 1 milestone sang OS.
 * @return array ['ok'=>bool,'code'=>string,'msg'=>?string,'data'=>?array,'paymentStatus'=>?string]
 */
if (!function_exists('ms_os_push_invoice')) {
    function ms_os_push_invoice($conn, int $milestone_id, int $invoice_odoo_id, ?string $note = null): array {
        ms_os_ensure_link_table($conn);

        // Milestone + project
        $ms = null;
        try {
            $st = $conn->prepare("SELECT m.*, p.project_code AS pakd_project_code FROM pakd_milestones m
                                  LEFT JOIN pakd p ON p.id = m.pakd_id WHERE m.id = ? LIMIT 1");
            $st->bind_param("i", $milestone_id); $st->execute();
            $ms = $st->get_result()->fetch_assoc(); $st->close();
        } catch (\Throwable $e) {}
        if (!$ms) return ['ok' => false, 'code' => null, 'msg' => 'Không tìm thấy milestone'];

        $pakd_id         = (int)$ms['pakd_id'];
        $os_milestone_id = (string)$ms['os_milestone_id'];
        $project_code    = $ms['project_code'] ?: ($ms['pakd_project_code'] ?? null);
        if (!$os_milestone_id) return ['ok' => false, 'code' => null, 'msg' => 'Milestone thiếu os_milestone_id'];

        // Invoice
        $inv = null;
        try {
            $st = $conn->prepare("SELECT odoo_id, name, highest_name, ref, state, amount_total, currency_name, payment_state, invoice_date, payment_date
                                  FROM odoo_invoices WHERE odoo_id = ? LIMIT 1");
            $st->bind_param("i", $invoice_odoo_id); $st->execute();
            $inv = $st->get_result()->fetch_assoc(); $st->close();
        } catch (\Throwable $e) {}
        if (!$inv) return ['ok' => false, 'code' => null, 'msg' => 'Không tìm thấy hoá đơn'];

        $cfg = ms_os_config();
        if (!$cfg) return ['ok' => false, 'code' => null, 'msg' => 'Chưa cấu hình ArrowHitech API'];

        $odooBaseUrl = '';
        try { $os = $conn->query("SELECT odoo_url FROM odoo_settings ORDER BY id DESC LIMIT 1")->fetch_assoc(); $odooBaseUrl = rtrim($os['odoo_url'] ?? '', '/'); } catch (\Throwable $e) {}

        $invoice_code   = $inv['name'] ?: ($inv['highest_name'] ?: ($inv['ref'] ?: ('#' . $inv['odoo_id'])));
        $invoice_status = strtoupper((string)$inv['state']);
        $payment_state  = (strtolower((string)$inv['payment_state']) === 'paid') ? 'PAID' : 'UNPAID';
        $paid_at        = $inv['payment_date'] ?: ($inv['invoice_date'] ?: date('Y-m-d'));
        $amount         = (float)$inv['amount_total'];
        $prod_price     = $amount;
        $currency       = $inv['currency_name'] ?: 'VND';
        $invoice_url    = $odooBaseUrl ? ($odooBaseUrl . '/web#id=' . (int)$inv['odoo_id'] . '&model=account.move&view_type=form') : null;

        $payload = [
            'occurredAt'  => gmdate('Y-m-d\TH:i:s') . '.000Z',
            'projectCode' => $project_code,
            'invoice'     => [
                'invoiceCode'     => $invoice_code,
                'invoiceStatus'   => $invoice_status,
                'amount'          => $amount,
                'productionPrice' => $prod_price,
                'currency'        => $currency,
                'paidAt'          => $paid_at,
                'paymentState'    => $payment_state,
                'invoiceUrl'      => $invoice_url,
                'note'            => ($note !== null && $note !== '') ? $note : null,
            ],
        ];

        $endpoint   = $cfg['api_url'] . '/integrations/os/milestones/' . rawurlencode($os_milestone_id) . '/payment';
        $timestamp  = (int)(microtime(true) * 1000);
        $request_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: '    . $cfg['api_token'],
                'X-Timestamp: '  . $timestamp,
                'X-Request-Id: ' . $request_id,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response  = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        $resp        = json_decode($response, true);
        $log_payload = json_encode(['request' => $payload, 'response' => $response ? ($resp ?: $response) : null], JSON_UNESCAPED_UNICODE);

        if ($curl_err) {
            ms_os_log($conn, $pakd_id, $os_milestone_id, 'payment_pushed', 'error', $log_payload, $http_code ?: 0, 'curl: ' . $curl_err . ' | inv ' . $invoice_code);
            return ['ok' => false, 'code' => $invoice_code, 'msg' => 'Lỗi kết nối: ' . $curl_err];
        }

        if ($http_code >= 200 && $http_code < 300 && (($resp['success'] ?? false) === true || $http_code === 200)) {
            $data          = $resp['data'] ?? [];
            $resp_pay_stat = $data['paymentStatus'] ?? null;
            $resp_currency = $data['currency'] ?? $currency;

            try {
                $up = $conn->prepare("INSERT INTO pakd_milestone_invoices
                    (pakd_id, milestone_id, os_milestone_id, invoice_odoo_id, invoice_code, invoice_status, payment_state, amount, production_price, currency, paid_at, note, payment_pushed_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                    ON DUPLICATE KEY UPDATE
                      invoice_code=VALUES(invoice_code), invoice_status=VALUES(invoice_status), payment_state=VALUES(payment_state),
                      amount=VALUES(amount), production_price=VALUES(production_price), currency=VALUES(currency),
                      paid_at=VALUES(paid_at), note=VALUES(note), payment_pushed_at=NOW()");
                $up->bind_param("iisisssddsss",
                    $pakd_id, $milestone_id, $os_milestone_id, $invoice_odoo_id, $invoice_code, $invoice_status, $payment_state,
                    $amount, $prod_price, $resp_currency, $paid_at, $note);
                $up->execute(); $up->close();
            } catch (\Throwable $e) {}

            if ($resp_pay_stat) {
                try { $um = $conn->prepare("UPDATE pakd_milestones SET payment_status=? WHERE id=?"); $um->bind_param("si", $resp_pay_stat, $milestone_id); $um->execute(); $um->close(); } catch (\Throwable $e) {}
            }

            ms_os_log($conn, $pakd_id, $os_milestone_id, 'payment_pushed', 'ok', $log_payload, $http_code, 'invoice ' . $invoice_code);
            return ['ok' => true, 'code' => $invoice_code, 'data' => $data, 'paymentStatus' => $resp_pay_stat, 'currency' => $resp_currency];
        }

        // Lỗi từ OS (có thể object lồng nhau)
        $err_msg = 'HTTP ' . $http_code;
        if (is_array($resp)) {
            $cand = $resp['message'] ?? $resp['error'] ?? null;
            if (is_array($cand)) $cand = $cand['message'] ?? json_encode($cand, JSON_UNESCAPED_UNICODE);
            if ($cand) $err_msg = (string)$cand;
        }
        ms_os_log($conn, $pakd_id, $os_milestone_id, 'payment_pushed', 'error', $log_payload, $http_code, $err_msg . ' | inv ' . $invoice_code);
        return ['ok' => false, 'code' => $invoice_code, 'msg' => $err_msg, 'http_code' => $http_code];
    }
}

/**
 * Tự đồng bộ lại MỌI milestone đang gắn hoá đơn này sang OS (gọi khi invoice đổi qua webhook).
 * @return int số lần đẩy
 */
if (!function_exists('ms_os_resync_invoice')) {
    function ms_os_resync_invoice($conn, int $invoice_odoo_id, ?string $note = null): int {
        $mids = [];
        try {
            $r = $conn->query("SELECT DISTINCT milestone_id FROM pakd_milestone_invoices WHERE invoice_odoo_id = " . (int)$invoice_odoo_id);
            if ($r) while ($row = $r->fetch_assoc()) $mids[] = (int)$row['milestone_id'];
        } catch (\Throwable $e) { return 0; } // bảng chưa tồn tại / chưa gắn HĐ nào
        $n = 0;
        foreach ($mids as $mid) {
            $res = ms_os_push_invoice($conn, $mid, $invoice_odoo_id, $note);
            if (!empty($res['ok'])) $n++;
        }
        return $n;
    }
}

/**
 * Gỡ liên kết hoá đơn khỏi mọi milestone (gọi khi invoice bị delete/cancel).
 * @return int số liên kết đã gỡ
 */
if (!function_exists('ms_os_unlink_invoice')) {
    function ms_os_unlink_invoice($conn, int $invoice_odoo_id): int {
        try {
            $rows = [];
            $r = $conn->query("SELECT milestone_id, pakd_id, os_milestone_id, invoice_code FROM pakd_milestone_invoices WHERE invoice_odoo_id = " . (int)$invoice_odoo_id);
            if ($r) while ($row = $r->fetch_assoc()) $rows[] = $row;
            if (!$rows) return 0;
            $conn->query("DELETE FROM pakd_milestone_invoices WHERE invoice_odoo_id = " . (int)$invoice_odoo_id);
            foreach ($rows as $row) {
                ms_os_log($conn, $row['pakd_id'], $row['os_milestone_id'], 'payment_unlinked', 'ok', null, 200, 'gỡ hoá đơn ' . ($row['invoice_code'] ?? ('#' . $invoice_odoo_id)) . ' (invoice delete/cancel)');
            }
            return count($rows);
        } catch (\Throwable $e) { return 0; }
    }
}
