<?php
/**
 * Odoo Webhook Handler
 * Receives CRM, Sale, Invoice events from Odoo and logs them to DB.
 *
 * CRM events additionally:
 *  - Update odoo_stage_name / odoo_stage_id on existing pakd record
 *  - Auto-create a new pakd record if the stage is in the configured sync list
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/notify_pakd_result.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw_body     = file_get_contents('php://input');
$payload      = json_decode($raw_body, true);
$source_ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$payload_json = $raw_body ?: '{}';

// ── Detect event type ────────────────────────────────────────────────────────
$event_type = 'unknown';
if (isset($payload['event_type'])) {
    $event_type = $payload['event_type'];
} elseif (isset($payload['model'])) {
    $model = $payload['model'] ?? '';
    if (str_contains($model, 'crm'))         $event_type = 'crm';
    elseif (str_contains($model, 'sale'))    $event_type = 'sale';
    elseif (str_contains($model, 'account')) $event_type = 'invoice';
    else                                      $event_type = $model;
}
// If payload has type=opportunity and no model, treat as crm
if ($event_type === 'unknown' && isset($payload['type']) && $payload['type'] === 'opportunity') {
    $event_type = 'crm';
}
$header_event = $_SERVER['HTTP_X_ODOO_EVENT'] ?? $_SERVER['HTTP_X_EVENT_TYPE'] ?? null;
if ($header_event) $event_type = $header_event;

// ── Ensure log table (with result_notes for debugging) ───────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS odoo_webhook_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type   VARCHAR(100) NOT NULL DEFAULT 'unknown',
    payload      LONGTEXT     NOT NULL,
    source_ip    VARCHAR(45)  NOT NULL DEFAULT '',
    result_notes TEXT         DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add result_notes column if table already existed without it
$col = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='odoo_webhook_logs' AND COLUMN_NAME='result_notes'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE odoo_webhook_logs ADD COLUMN result_notes TEXT DEFAULT NULL");
}

$stmt = $conn->prepare("INSERT INTO odoo_webhook_logs (event_type, payload, source_ip) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $event_type, $payload_json, $source_ip);
$stmt->execute();
$log_id = $conn->insert_id;
$stmt->close();

// ── CRM: update stage + auto-create pakd ─────────────────────────────────────
$pakd_updated = false;
$pakd_created = false;
$debug        = ['event_type' => $event_type];

if ($payload && str_contains($event_type, 'crm')) {

    // Extract opportunity ID — support multiple Odoo payload formats
    $opp_id = null;
    if (isset($payload['ids']) && is_array($payload['ids']) && !empty($payload['ids'])) {
        // ids[] — take first (webhook usually fires per-record)
        $opp_id = (int)$payload['ids'][0];
    } elseif (isset($payload['id']) && is_numeric($payload['id'])) {
        $opp_id = (int)$payload['id'];
    } elseif (isset($payload['record']['id'])) {
        $opp_id = (int)$payload['record']['id'];
    } elseif (isset($payload['data'][0]['id'])) {
        $opp_id = (int)$payload['data'][0]['id'];
    }

    $debug['opp_id_extracted'] = $opp_id;

    // Extract stage_id / stage_name
    // Odoo sends many2one as {"id": X, "name": "..."} object OR [id, name] tuple
    $stage_raw = $payload['stage_id']
        ?? $payload['record']['stage_id']
        ?? ($payload['data'][0]['stage_id'] ?? null);

    $stage_id   = null;
    $stage_name = null;
    if (is_array($stage_raw)) {
        if (isset($stage_raw['id'])) {
            // Object format: {"id": 3, "name": "L2 Proposals"}
            $stage_id   = (int)$stage_raw['id'];
            $stage_name = $stage_raw['name'] ?? null;
        } elseif (count($stage_raw) >= 2 && is_numeric($stage_raw[0])) {
            // Tuple format: [3, "L2 Proposals"]
            $stage_id   = (int)$stage_raw[0];
            $stage_name = (string)$stage_raw[1];
        }
    } elseif (is_numeric($stage_raw)) {
        $stage_id   = (int)$stage_raw;
        $stage_name = $payload['stage_name'] ?? $payload['record']['stage_name'] ?? null;
    }

    $debug['stage_id']   = $stage_id;
    $debug['stage_name'] = $stage_name;

    // Extract won_status ("won" | "lost" | false/null)
    $won_status_raw = $payload['won_status'] ?? $payload['record']['won_status'] ?? ($payload['data'][0]['won_status'] ?? null);
    $won_status = (is_string($won_status_raw) && $won_status_raw !== '') ? $won_status_raw : null;

    // Extract lost_reason_id — object {"id":1,"name":"..."} or tuple [1,"..."]
    $lost_reason_raw = $payload['lost_reason_id'] ?? $payload['record']['lost_reason_id'] ?? ($payload['data'][0]['lost_reason_id'] ?? null);
    $lost_reason = null;
    if (is_array($lost_reason_raw)) {
        $lost_reason = $lost_reason_raw['name'] ?? ($lost_reason_raw[1] ?? null);
    } elseif (is_string($lost_reason_raw) && $lost_reason_raw !== '') {
        $lost_reason = $lost_reason_raw;
    }

    $debug['won_status']  = $won_status;
    $debug['lost_reason'] = $lost_reason;

    if ($opp_id) {
        // Ensure pakd_settings table exists before querying
        $conn->query("CREATE TABLE IF NOT EXISTS pakd_settings (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            setting_key   VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT DEFAULT NULL,
            updated_by    INT DEFAULT NULL,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Load configured sync stage IDs
        $syncStageIds = [];
        $sr = $conn->query("SELECT setting_value FROM pakd_settings WHERE setting_key = 'sync_stage_ids'");
        if ($sr && $row = $sr->fetch_assoc()) {
            $syncStageIds = array_map('intval', json_decode($row['setting_value'], true) ?: []);
        }
        $debug['sync_stage_ids'] = $syncStageIds;
        $debug['stage_in_list']  = $stage_id ? in_array($stage_id, $syncStageIds, true) : false;

        // Check if pakd record already exists for this opportunity
        $chk = $conn->prepare("SELECT id FROM pakd WHERE odoo_opp_id = ?");
        $chk->bind_param('i', $opp_id);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        $chk->close();

        $debug['pakd_existed'] = (bool)$existing;

        if ($existing) {
            // ── Record exists: update stage + won_status + lost_reason ────────
            // Ensure columns exist (MySQL 5.7 compatible)
            foreach (['won_status' => 'VARCHAR(20)', 'lost_reason' => 'VARCHAR(255)'] as $_col => $_def) {
                $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pakd' AND COLUMN_NAME='$_col'");
                if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE pakd ADD COLUMN `$_col` $_def DEFAULT NULL");
            }

            $upd = $conn->prepare(
                "UPDATE pakd
                 SET odoo_stage_id   = COALESCE(?, odoo_stage_id),
                     odoo_stage_name = COALESCE(?, odoo_stage_name),
                     won_status       = COALESCE(?, won_status),
                     lost_reason      = ?,
                     updated_at       = NOW()
                 WHERE odoo_opp_id = ?"
            );
            $upd->bind_param('isssi', $stage_id, $stage_name, $won_status, $lost_reason, $opp_id);
            $upd->execute();
            $pakd_updated = $upd->affected_rows > 0;
            $upd->close();
            $debug['action'] = 'update_stage';

            // Notify production if won_status changed to won/lost
            if ($pakd_updated && in_array($won_status, ['won', 'lost'], true)) {
                notifyPakdResult($conn, (int)$existing['id'], $won_status, $lost_reason);
                $debug['notified_result'] = true;
            }

        } elseif ($stage_id && in_array($stage_id, $syncStageIds, true)) {
            // ── Record does NOT exist + stage is in sync list: auto-create ───
            $debug['action'] = 'auto_create';
            try {
                require_once __DIR__ . '/../libs/OdooAPI.php';
                $odoo = new OdooAPI();

                $oppData = $odoo->searchRead('crm.lead', [['id', '=', $opp_id]], [
                    'id', 'name', 'partner_id', 'partner_name', 'user_id',
                    'team_id', 'stage_id', 'probability', 'expected_revenue',
                    'date_open', 'date_deadline', 'division_ids', 'description',
                    'active', 'type',
                ], 1);

                if (empty($oppData[0])) throw new Exception('Opportunity #' . $opp_id . ' not found in Odoo');
                $opp = $oppData[0];

                // Company name
                $companyName = '';
                if (!empty($opp['partner_name'])) {
                    $companyName = $opp['partner_name'];
                } elseif (!empty($opp['partner_id']) && is_array($opp['partner_id'])) {
                    $companyName = trim(explode(',', $opp['partner_id'][1] ?? '')[0]);
                }

                // AM name & email
                $amName  = '';
                $amEmail = '';
                if (!empty($opp['user_id']) && is_array($opp['user_id'])) {
                    $amName = $opp['user_id'][1] ?? '';
                    try {
                        $ud = $odoo->searchRead('res.users', [['id', '=', $opp['user_id'][0]]], ['login'], 1);
                        $amEmail = $ud[0]['login'] ?? '';
                    } catch (Exception $e) {}
                }

                // Match local user by email
                $localUserId = null;
                if ($amEmail) {
                    $uq = strtolower($amEmail);
                    $us = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
                    $us->bind_param('s', $uq);
                    $us->execute();
                    $ur = $us->get_result()->fetch_assoc();
                    $us->close();
                    if ($ur) $localUserId = (int)$ur['id'];
                }

                // Department from sales team
                $department = '';
                if (!empty($opp['team_id']) && is_array($opp['team_id'])) {
                    $department = $opp['team_id'][1] ?? '';
                }

                // Stage from fetched data (authoritative)
                $fStageId   = null;
                $fStageName = null;
                if (!empty($opp['stage_id']) && is_array($opp['stage_id'])) {
                    $fStageId   = (int)$opp['stage_id'][0];
                    $fStageName = $opp['stage_id'][1] ?? '';
                }

                $probability     = (float)($opp['probability'] ?? 0);
                $oppValue        = (float)($opp['expected_revenue'] ?? 0);
                $internalNote    = is_string($opp['description'] ?? null) ? $opp['description'] : '';
                $odooUrl         = rtrim($odoo->getUrl(), '/') . '/odoo/crm/' . $opp_id;
                $assignmentDate  = (!empty($opp['date_open'])     && $opp['date_open']     !== false) ? $opp['date_open']     : null;
                $expectedClosing = (!empty($opp['date_deadline']) && $opp['date_deadline'] !== false) ? $opp['date_deadline'] : null;
                $name            = trim($opp['name'] ?? '');

                // Division names
                $divisionNames = null;
                if (!empty($opp['division_ids'])) {
                    try {
                        $divs = $odoo->searchRead('lead.opp.divisions', [['id', 'in', $opp['division_ids']]], ['name'], 0);
                        $dn   = implode(', ', array_column($divs, 'name'));
                        if ($dn !== '') $divisionNames = $dn;
                    } catch (Exception $e) {}
                }

                $ins = $conn->prepare("
                    INSERT INTO pakd (
                        odoo_opp_id, name, am_name, am_email, am_user_id,
                        department, company_name, opp_value, opp_probability,
                        odoo_stage_id, odoo_stage_name, currency, opportunity_name,
                        internal_notes, odoo_url, assignment_date, expected_closing,
                        division_names, status, synced_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, 'VND', ?,
                        ?, ?, ?, ?,
                        ?, 'draft', NOW(), NOW()
                    )
                ");
                $ins->bind_param(
                    'isssissddisssssss',
                    $opp_id, $name, $amName, $amEmail, $localUserId,
                    $department, $companyName, $oppValue, $probability,
                    $fStageId, $fStageName, $name,
                    $internalNote, $odooUrl, $assignmentDate, $expectedClosing,
                    $divisionNames
                );
                $ins->execute();
                $pakd_created       = $ins->affected_rows > 0;
                $debug['pakd_name'] = $name;
                $ins->close();

            } catch (Exception $e) {
                $debug['error'] = $e->getMessage();
                error_log('[odoo_hook] auto-create pakd failed for opp #' . $opp_id . ': ' . $e->getMessage());
            }
        } else {
            $debug['action'] = 'skip';
        }
    } else {
        $debug['action'] = 'no_opp_id';
    }
}

// ── SALE ORDER: upsert vào odoo_sale_orders ──────────────────────────────────
if ($payload && $event_type === 'sale') {
    $conn->query("CREATE TABLE IF NOT EXISTS odoo_sale_orders (
        id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        odoo_id           INT NOT NULL,
        name              VARCHAR(100)  DEFAULT NULL,
        state             VARCHAR(50)   DEFAULT NULL,
        date_order        DATETIME      DEFAULT NULL,
        commitment_date   DATETIME      DEFAULT NULL,
        validity_date     DATE          DEFAULT NULL,
        effective_date    DATE          DEFAULT NULL,
        partner_id        INT           DEFAULT NULL,
        partner_name      VARCHAR(500)  DEFAULT NULL,
        user_id           INT           DEFAULT NULL,
        user_name         VARCHAR(255)  DEFAULT NULL,
        team_id           INT           DEFAULT NULL,
        team_name         VARCHAR(255)  DEFAULT NULL,
        amount_untaxed    DECIMAL(20,2) DEFAULT 0,
        amount_tax        DECIMAL(20,2) DEFAULT 0,
        amount_total      DECIMAL(20,2) DEFAULT 0,
        amount_invoiced   DECIMAL(20,2) DEFAULT 0,
        amount_to_invoice DECIMAL(20,2) DEFAULT 0,
        currency_id       INT           DEFAULT NULL,
        currency_name     VARCHAR(10)   DEFAULT NULL,
        invoice_status    VARCHAR(50)   DEFAULT NULL,
        invoice_count     INT           DEFAULT 0,
        invoice_ids       JSON          DEFAULT NULL,
        order_line_ids    JSON          DEFAULT NULL,
        client_order_ref  VARCHAR(500)  DEFAULT NULL,
        origin            VARCHAR(500)  DEFAULT NULL,
        opportunity_id    INT           DEFAULT NULL,
        payment_term_id   INT           DEFAULT NULL,
        payment_term_name VARCHAR(255)  DEFAULT NULL,
        note              TEXT          DEFAULT NULL,
        created_at        DATETIME      DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_odoo_id (odoo_id),
        INDEX idx_opp_id (opportunity_id),
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $so_odoo_id = (int)($payload['id'] ?? 0);
    if ($so_odoo_id) {
        try {
            require_once __DIR__ . '/../libs/OdooAPI.php';
            $odoo   = new OdooAPI();
            $soData = $odoo->searchRead('sale.order', [['id', '=', $so_odoo_id]], [
                'id','name','state','date_order','commitment_date','validity_date','effective_date',
                'partner_id','user_id','team_id','amount_untaxed','amount_tax','amount_total',
                'amount_invoiced','amount_to_invoice','currency_id','invoice_status','invoice_count',
                'invoice_ids','order_line','client_order_ref','origin','opportunity_id',
                'payment_term_id','note',
            ], 1);

            if (!empty($soData[0])) {
                $so = $soData[0];

                $f = []; // safe field extraction helper
                $f['partner_id']        = is_array($so['partner_id'])       ? (int)($so['partner_id'][0] ?? 0)         : null;
                $f['partner_name']      = is_array($so['partner_id'])       ? ($so['partner_id'][1] ?? null)            : null;
                $f['user_id']           = is_array($so['user_id'])          ? (int)($so['user_id'][0] ?? 0)            : null;
                $f['user_name']         = is_array($so['user_id'])          ? ($so['user_id'][1] ?? null)               : null;
                $f['team_id']           = is_array($so['team_id'])          ? (int)($so['team_id'][0] ?? 0)            : null;
                $f['team_name']         = is_array($so['team_id'])          ? ($so['team_id'][1] ?? null)               : null;
                $f['currency_id']       = is_array($so['currency_id'])      ? (int)($so['currency_id'][0] ?? 0)        : null;
                $f['currency_name']     = is_array($so['currency_id'])      ? ($so['currency_id'][1] ?? null)           : null;
                $f['opportunity_id']    = is_array($so['opportunity_id'])   ? (int)($so['opportunity_id'][0] ?? 0)     : (is_numeric($so['opportunity_id'] ?? null) ? (int)$so['opportunity_id'] : null);
                $f['payment_term_id']   = is_array($so['payment_term_id'])  ? (int)($so['payment_term_id'][0] ?? 0)    : null;
                $f['payment_term_name'] = is_array($so['payment_term_id'])  ? ($so['payment_term_id'][1] ?? null)       : null;
                $f['date_order']        = ($so['date_order']       && $so['date_order']       !== false) ? $so['date_order']       : null;
                $f['commitment_date']   = ($so['commitment_date']  && $so['commitment_date']  !== false) ? $so['commitment_date']  : null;
                $f['validity_date']     = ($so['validity_date']    && $so['validity_date']    !== false) ? $so['validity_date']    : null;
                $f['effective_date']    = ($so['effective_date']   && $so['effective_date']   !== false) ? $so['effective_date']   : null;
                $f['invoice_ids']       = !empty($so['invoice_ids'])  ? json_encode($so['invoice_ids'])  : null;
                $f['order_line_ids']    = !empty($so['order_line'])   ? json_encode($so['order_line'])   : null;
                $f['client_order_ref']  = $so['client_order_ref']  ?: null;
                $f['origin']            = $so['origin']            ?: null;
                $f['note']              = is_string($so['note'] ?? null) ? $so['note'] : null;
                $f['name']              = $so['name']              ?? null;
                $f['state']             = $so['state']             ?? null;
                $f['invoice_status']    = $so['invoice_status']    ?? null;
                $f['invoice_count']     = (int)($so['invoice_count'] ?? 0);
                $f['amount_untaxed']    = (float)($so['amount_untaxed']    ?? 0);
                $f['amount_tax']        = (float)($so['amount_tax']        ?? 0);
                $f['amount_total']      = (float)($so['amount_total']      ?? 0);
                $f['amount_invoiced']   = (float)($so['amount_invoiced']   ?? 0);
                $f['amount_to_invoice'] = (float)($so['amount_to_invoice'] ?? 0);

                $conn->query("INSERT INTO odoo_sale_orders
                    (odoo_id,name,state,date_order,commitment_date,validity_date,effective_date,
                     partner_id,partner_name,user_id,user_name,team_id,team_name,
                     amount_untaxed,amount_tax,amount_total,amount_invoiced,amount_to_invoice,
                     currency_id,currency_name,invoice_status,invoice_count,invoice_ids,
                     order_line_ids,client_order_ref,origin,opportunity_id,
                     payment_term_id,payment_term_name,note)
                    VALUES (
                     $so_odoo_id,
                     " . ($f['name']              === null ? 'NULL' : "'" . $conn->real_escape_string($f['name']) . "'") . ",
                     " . ($f['state']             === null ? 'NULL' : "'" . $conn->real_escape_string($f['state']) . "'") . ",
                     " . ($f['date_order']        === null ? 'NULL' : "'" . $conn->real_escape_string($f['date_order']) . "'") . ",
                     " . ($f['commitment_date']   === null ? 'NULL' : "'" . $conn->real_escape_string($f['commitment_date']) . "'") . ",
                     " . ($f['validity_date']     === null ? 'NULL' : "'" . $conn->real_escape_string($f['validity_date']) . "'") . ",
                     " . ($f['effective_date']    === null ? 'NULL' : "'" . $conn->real_escape_string($f['effective_date']) . "'") . ",
                     " . ($f['partner_id']        === null ? 'NULL' : (int)$f['partner_id']) . ",
                     " . ($f['partner_name']      === null ? 'NULL' : "'" . $conn->real_escape_string($f['partner_name']) . "'") . ",
                     " . ($f['user_id']           === null ? 'NULL' : (int)$f['user_id']) . ",
                     " . ($f['user_name']         === null ? 'NULL' : "'" . $conn->real_escape_string($f['user_name']) . "'") . ",
                     " . ($f['team_id']           === null ? 'NULL' : (int)$f['team_id']) . ",
                     " . ($f['team_name']         === null ? 'NULL' : "'" . $conn->real_escape_string($f['team_name']) . "'") . ",
                     {$f['amount_untaxed']}, {$f['amount_tax']}, {$f['amount_total']},
                     {$f['amount_invoiced']}, {$f['amount_to_invoice']},
                     " . ($f['currency_id']       === null ? 'NULL' : (int)$f['currency_id']) . ",
                     " . ($f['currency_name']     === null ? 'NULL' : "'" . $conn->real_escape_string($f['currency_name']) . "'") . ",
                     " . ($f['invoice_status']    === null ? 'NULL' : "'" . $conn->real_escape_string($f['invoice_status']) . "'") . ",
                     {$f['invoice_count']},
                     " . ($f['invoice_ids']       === null ? 'NULL' : "'" . $conn->real_escape_string($f['invoice_ids']) . "'") . ",
                     " . ($f['order_line_ids']    === null ? 'NULL' : "'" . $conn->real_escape_string($f['order_line_ids']) . "'") . ",
                     " . ($f['client_order_ref']  === null ? 'NULL' : "'" . $conn->real_escape_string($f['client_order_ref']) . "'") . ",
                     " . ($f['origin']            === null ? 'NULL' : "'" . $conn->real_escape_string($f['origin']) . "'") . ",
                     " . ($f['opportunity_id']    === null ? 'NULL' : (int)$f['opportunity_id']) . ",
                     " . ($f['payment_term_id']   === null ? 'NULL' : (int)$f['payment_term_id']) . ",
                     " . ($f['payment_term_name'] === null ? 'NULL' : "'" . $conn->real_escape_string($f['payment_term_name']) . "'") . ",
                     " . ($f['note']              === null ? 'NULL' : "'" . $conn->real_escape_string($f['note']) . "'") . "
                    )
                    ON DUPLICATE KEY UPDATE
                     name              = VALUES(name),
                     state             = VALUES(state),
                     date_order        = VALUES(date_order),
                     commitment_date   = VALUES(commitment_date),
                     validity_date     = VALUES(validity_date),
                     effective_date    = VALUES(effective_date),
                     partner_id        = VALUES(partner_id),
                     partner_name      = VALUES(partner_name),
                     user_id           = VALUES(user_id),
                     user_name         = VALUES(user_name),
                     team_id           = VALUES(team_id),
                     team_name         = VALUES(team_name),
                     amount_untaxed    = VALUES(amount_untaxed),
                     amount_tax        = VALUES(amount_tax),
                     amount_total      = VALUES(amount_total),
                     amount_invoiced   = VALUES(amount_invoiced),
                     amount_to_invoice = VALUES(amount_to_invoice),
                     currency_id       = VALUES(currency_id),
                     currency_name     = VALUES(currency_name),
                     invoice_status    = VALUES(invoice_status),
                     invoice_count     = VALUES(invoice_count),
                     invoice_ids       = VALUES(invoice_ids),
                     order_line_ids    = VALUES(order_line_ids),
                     client_order_ref  = VALUES(client_order_ref),
                     origin            = VALUES(origin),
                     opportunity_id    = VALUES(opportunity_id),
                     payment_term_id   = VALUES(payment_term_id),
                     payment_term_name = VALUES(payment_term_name),
                     note              = VALUES(note),
                     updated_at        = NOW()
                ");
                $debug['so_upserted'] = $so_odoo_id;
                $debug['so_error']    = $conn->error ?: null;

                // ── So sánh invoice_ids cũ vs mới → xóa invoice bị remove ──
                $oldRow = $conn->query("SELECT invoice_ids FROM odoo_sale_orders WHERE odoo_id = $so_odoo_id LIMIT 1");
                if ($oldRow && $oldData = $oldRow->fetch_assoc()) {
                    $oldIds = !empty($oldData['invoice_ids']) ? (json_decode($oldData['invoice_ids'], true) ?: []) : [];
                    $newIds = $so['invoice_ids'] ?? [];
                    $removedIds = array_values(array_diff(
                        array_map('intval', $oldIds),
                        array_map('intval', $newIds)
                    ));
                    if (!empty($removedIds)) {
                        $removeList = implode(',', $removedIds);
                        $conn->query("DELETE FROM odoo_invoices WHERE odoo_id IN ($removeList)");
                        $debug['invoices_deleted'] = $removedIds;
                    }
                }
            }
        } catch (Exception $e) {
            $debug['so_error'] = $e->getMessage();
        }
    }
}

// ── INVOICE: upsert vào odoo_invoices ────────────────────────────────────────
if ($payload && $event_type === 'invoice') {
    $conn->query("CREATE TABLE IF NOT EXISTS odoo_invoices (
        id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        odoo_id                INT NOT NULL,
        name                   VARCHAR(255)  DEFAULT NULL,
        highest_name           VARCHAR(255)  DEFAULT NULL,
        state                  VARCHAR(50)   DEFAULT NULL,
        move_type              VARCHAR(50)   DEFAULT NULL,
        invoice_date           DATE          DEFAULT NULL,
        invoice_date_due       DATE          DEFAULT NULL,
        partner_id             INT           DEFAULT NULL,
        partner_name           VARCHAR(500)  DEFAULT NULL,
        currency_id            INT           DEFAULT NULL,
        currency_name          VARCHAR(10)   DEFAULT NULL,
        company_currency_name  VARCHAR(10)   DEFAULT NULL,
        amount_untaxed         DECIMAL(20,2) DEFAULT 0,
        amount_tax             DECIMAL(20,2) DEFAULT 0,
        amount_total           DECIMAL(20,2) DEFAULT 0,
        amount_residual        DECIMAL(20,2) DEFAULT 0,
        amount_total_signed    DECIMAL(20,2) DEFAULT 0,
        amount_residual_signed DECIMAL(20,2) DEFAULT 0,
        payment_state          VARCHAR(50)   DEFAULT NULL,
        invoice_origin         VARCHAR(500)  DEFAULT NULL,
        invoice_user_id        INT           DEFAULT NULL,
        invoice_user_name      VARCHAR(255)  DEFAULT NULL,
        team_id                INT           DEFAULT NULL,
        team_name              VARCHAR(255)  DEFAULT NULL,
        journal_id             INT           DEFAULT NULL,
        journal_name           VARCHAR(255)  DEFAULT NULL,
        ref                    VARCHAR(500)  DEFAULT NULL,
        l10n_vn_e_invoice_number VARCHAR(255) DEFAULT NULL,
        sale_order_count       INT           DEFAULT 0,
        invoice_line_ids       JSON          DEFAULT NULL,
        created_at             DATETIME      DEFAULT CURRENT_TIMESTAMP,
        updated_at             DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_odoo_id (odoo_id),
        INDEX idx_origin (invoice_origin),
        INDEX idx_state (state)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $inv_odoo_id = (int)($payload['id'] ?? $payload['record_id'] ?? 0);
    if ($inv_odoo_id) {
        $g = []; // field extractor
        $g['name']              = $payload['name'] ?: null;
        $g['highest_name']      = $payload['highest_name'] ?: null;
        $g['state']             = $payload['state'] ?? null;
        $g['move_type']         = $payload['move_type'] ?? null;
        $g['invoice_date']      = ($payload['invoice_date']     && $payload['invoice_date']     !== false) ? $payload['invoice_date']     : null;
        $g['invoice_date_due']  = ($payload['invoice_date_due'] && $payload['invoice_date_due'] !== false) ? $payload['invoice_date_due'] : null;
        $g['partner_id']        = is_array($payload['partner_id'])           ? (int)($payload['partner_id']['id']           ?? 0) : null;
        $g['partner_name']      = is_array($payload['partner_id'])           ? ($payload['partner_id']['name']               ?? null) : null;
        $g['currency_id']       = is_array($payload['currency_id'])          ? (int)($payload['currency_id']['id']           ?? 0) : null;
        $g['currency_name']     = is_array($payload['currency_id'])          ? ($payload['currency_id']['name']               ?? null) : null;
        $g['company_currency']  = is_array($payload['company_currency_id'])  ? ($payload['company_currency_id']['name']       ?? 'VND') : 'VND';
        $g['invoice_user_id']   = is_array($payload['invoice_user_id'])      ? (int)($payload['invoice_user_id']['id']        ?? 0) : null;
        $g['invoice_user_name'] = is_array($payload['invoice_user_id'])      ? ($payload['invoice_user_id']['name']            ?? null) : null;
        $g['team_id']           = is_array($payload['team_id'])              ? (int)($payload['team_id']['id']                ?? 0) : null;
        $g['team_name']         = is_array($payload['team_id'])              ? ($payload['team_id']['name']                   ?? null) : null;
        $g['journal_id']        = is_array($payload['journal_id'])           ? (int)($payload['journal_id']['id']             ?? 0) : null;
        $g['journal_name']      = is_array($payload['journal_id'])           ? ($payload['journal_id']['name']                ?? null) : null;
        $g['payment_state']     = $payload['payment_state'] ?? null;
        $g['invoice_origin']    = $payload['invoice_origin'] ?: null;
        $g['ref']               = $payload['ref'] ?: null;
        $g['e_invoice']         = $payload['l10n_vn_e_invoice_number'] ?: null;
        $g['so_count']          = (int)($payload['sale_order_count'] ?? 0);
        $g['line_ids']          = !empty($payload['invoice_line_ids']) ? json_encode($payload['invoice_line_ids']) : null;
        $g['amount_untaxed']    = (float)($payload['amount_untaxed']    ?? 0);
        $g['amount_tax']        = (float)($payload['amount_tax']        ?? 0);
        $g['amount_total']      = (float)($payload['amount_total']      ?? 0);
        $g['amount_residual']   = (float)($payload['amount_residual']   ?? 0);
        $g['amount_total_signed']    = (float)($payload['amount_total_signed']    ?? 0);
        $g['amount_residual_signed'] = (float)($payload['amount_residual_signed'] ?? 0);

        $esc = fn($v) => $v === null ? 'NULL' : "'" . $conn->real_escape_string((string)$v) . "'";
        $escInt = fn($v) => $v === null ? 'NULL' : (int)$v;

        $conn->query("INSERT INTO odoo_invoices
            (odoo_id,name,highest_name,state,move_type,invoice_date,invoice_date_due,
             partner_id,partner_name,currency_id,currency_name,company_currency_name,
             amount_untaxed,amount_tax,amount_total,amount_residual,
             amount_total_signed,amount_residual_signed,payment_state,invoice_origin,
             invoice_user_id,invoice_user_name,team_id,team_name,
             journal_id,journal_name,ref,l10n_vn_e_invoice_number,sale_order_count,invoice_line_ids)
            VALUES (
             $inv_odoo_id,
             {$esc($g['name'])},{$esc($g['highest_name'])},{$esc($g['state'])},{$esc($g['move_type'])},
             {$esc($g['invoice_date'])},{$esc($g['invoice_date_due'])},
             {$escInt($g['partner_id'])},{$esc($g['partner_name'])},
             {$escInt($g['currency_id'])},{$esc($g['currency_name'])},{$esc($g['company_currency'])},
             {$g['amount_untaxed']},{$g['amount_tax']},{$g['amount_total']},{$g['amount_residual']},
             {$g['amount_total_signed']},{$g['amount_residual_signed']},
             {$esc($g['payment_state'])},{$esc($g['invoice_origin'])},
             {$escInt($g['invoice_user_id'])},{$esc($g['invoice_user_name'])},
             {$escInt($g['team_id'])},{$esc($g['team_name'])},
             {$escInt($g['journal_id'])},{$esc($g['journal_name'])},
             {$esc($g['ref'])},{$esc($g['e_invoice'])},{$g['so_count']},{$esc($g['line_ids'])}
            )
            ON DUPLICATE KEY UPDATE
             name=VALUES(name), highest_name=VALUES(highest_name), state=VALUES(state),
             invoice_date=VALUES(invoice_date), invoice_date_due=VALUES(invoice_date_due),
             partner_id=VALUES(partner_id), partner_name=VALUES(partner_name),
             currency_id=VALUES(currency_id), currency_name=VALUES(currency_name),
             company_currency_name=VALUES(company_currency_name),
             amount_untaxed=VALUES(amount_untaxed), amount_tax=VALUES(amount_tax),
             amount_total=VALUES(amount_total), amount_residual=VALUES(amount_residual),
             amount_total_signed=VALUES(amount_total_signed),
             amount_residual_signed=VALUES(amount_residual_signed),
             payment_state=VALUES(payment_state), invoice_origin=VALUES(invoice_origin),
             invoice_user_id=VALUES(invoice_user_id), invoice_user_name=VALUES(invoice_user_name),
             team_id=VALUES(team_id), team_name=VALUES(team_name),
             journal_id=VALUES(journal_id), journal_name=VALUES(journal_name),
             ref=VALUES(ref), l10n_vn_e_invoice_number=VALUES(l10n_vn_e_invoice_number),
             sale_order_count=VALUES(sale_order_count), invoice_line_ids=VALUES(invoice_line_ids),
             updated_at=NOW()
        ");
        $debug['inv_upserted'] = $inv_odoo_id;
        $debug['inv_error']    = $conn->error ?: null;

        // ── Cập nhật ngược invoice_ids của SO tương ứng ──────────────────────
        // invoice_origin = SO name (e.g. "S00436") → tìm SO, thêm invoice_id vào mảng
        if ($inv_odoo_id && !empty($g['invoice_origin'])) {
            $soLookup = $conn->query(
                "SELECT odoo_id, invoice_ids, invoice_count FROM odoo_sale_orders
                 WHERE name = '" . $conn->real_escape_string($g['invoice_origin']) . "' LIMIT 1"
            );
            if ($soLookup && $soRow = $soLookup->fetch_assoc()) {
                $existingIds = !empty($soRow['invoice_ids']) ? (json_decode($soRow['invoice_ids'], true) ?: []) : [];
                if (!in_array($inv_odoo_id, $existingIds, true)) {
                    $existingIds[] = $inv_odoo_id;
                    $newJson  = $conn->real_escape_string(json_encode(array_values($existingIds)));
                    $newCount = count($existingIds);
                    $conn->query(
                        "UPDATE odoo_sale_orders
                         SET invoice_ids = '$newJson', invoice_count = $newCount, updated_at = NOW()
                         WHERE odoo_id = " . (int)$soRow['odoo_id']
                    );
                    $debug['so_invoice_ids_updated'] = $soRow['odoo_id'];
                }
            }
        }
    }
}

// ── Store debug notes back into the log row ───────────────────────────────────
$debug['pakd_updated'] = $pakd_updated;
$debug['pakd_created'] = $pakd_created;
$notesJson = json_encode($debug, JSON_UNESCAPED_UNICODE);
$upNotes = $conn->prepare("UPDATE odoo_webhook_logs SET result_notes = ? WHERE id = ?");
$upNotes->bind_param('si', $notesJson, $log_id);
$upNotes->execute();
$upNotes->close();

http_response_code(200);
echo json_encode([
    'ok'           => true,
    'log_id'       => $log_id,
    'event_type'   => $event_type,
    'pakd_updated' => $pakd_updated,
    'pakd_created' => $pakd_created,
    'debug'        => $debug,
]);
