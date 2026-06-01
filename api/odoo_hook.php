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
$header_event = $_SERVER['HTTP_X_ODOO_EVENT'] ?? $_SERVER['HTTP_X_EVENT_TYPE'] ?? null;
if ($header_event) $event_type = $header_event;

// ── Log webhook ───────────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS odoo_webhook_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type  VARCHAR(100) NOT NULL DEFAULT 'unknown',
    payload     LONGTEXT     NOT NULL,
    source_ip   VARCHAR(45)  NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmt = $conn->prepare("INSERT INTO odoo_webhook_logs (event_type, payload, source_ip) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $event_type, $payload_json, $source_ip);
$stmt->execute();
$log_id = $conn->insert_id;
$stmt->close();

// ── CRM: update stage + auto-create pakd ─────────────────────────────────────
$pakd_updated = false;
$pakd_created = false;

if ($payload && str_contains($event_type, 'crm')) {

    // Extract opportunity ID (support multiple payload formats)
    $opp_id = null;
    if (isset($payload['ids']) && is_array($payload['ids']) && count($payload['ids']) === 1) {
        $opp_id = (int)$payload['ids'][0];
    } elseif (isset($payload['id'])) {
        $opp_id = (int)$payload['id'];
    } elseif (isset($payload['record']['id'])) {
        $opp_id = (int)$payload['record']['id'];
    } elseif (isset($payload['data'][0]['id'])) {
        $opp_id = (int)$payload['data'][0]['id'];
    }

    // Extract stage_id / stage_name ([id, name] tuple, {id, name} object, or plain int)
    $stage_raw = $payload['stage_id']
        ?? $payload['record']['stage_id']
        ?? ($payload['data'][0]['stage_id'] ?? null);

    $stage_id   = null;
    $stage_name = null;
    if (is_array($stage_raw)) {
        if (isset($stage_raw['id'])) {
            $stage_id   = (int)$stage_raw['id'];
            $stage_name = $stage_raw['name'] ?? null;
        } elseif (count($stage_raw) >= 2 && is_int($stage_raw[0])) {
            $stage_id   = (int)$stage_raw[0];
            $stage_name = (string)$stage_raw[1];
        }
    } elseif (is_numeric($stage_raw)) {
        $stage_id   = (int)$stage_raw;
        $stage_name = $payload['stage_name'] ?? $payload['record']['stage_name'] ?? ($payload['data'][0]['stage_name'] ?? null);
    }

    if ($opp_id) {
        // Load configured sync stage IDs from pakd_settings
        $syncStageIds = [];
        $sr = $conn->query("SELECT setting_value FROM pakd_settings WHERE setting_key = 'sync_stage_ids'");
        if ($sr && $row = $sr->fetch_assoc()) {
            $syncStageIds = array_map('intval', json_decode($row['setting_value'], true) ?: []);
        }

        // Check if pakd record already exists for this opportunity
        $chk = $conn->prepare("SELECT id FROM pakd WHERE odoo_opp_id = ?");
        $chk->bind_param('i', $opp_id);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($existing) {
            // ── Record exists: update stage only ─────────────────────────────
            if ($stage_id || $stage_name) {
                $upd = $conn->prepare(
                    "UPDATE pakd SET odoo_stage_id = ?, odoo_stage_name = ?, updated_at = NOW()
                     WHERE odoo_opp_id = ?"
                );
                $upd->bind_param('isi', $stage_id, $stage_name, $opp_id);
                $upd->execute();
                $pakd_updated = $upd->affected_rows > 0;
                $upd->close();
            }

        } elseif ($stage_id && in_array($stage_id, $syncStageIds, true)) {
            // ── Record does NOT exist + stage is in sync list: auto-create ───
            try {
                require_once __DIR__ . '/../libs/OdooAPI.php';
                $odoo = new OdooAPI();

                $oppData = $odoo->searchRead('crm.lead', [['id', '=', $opp_id]], [
                    'id', 'name', 'partner_id', 'partner_name', 'user_id',
                    'team_id', 'stage_id', 'probability', 'expected_revenue',
                    'date_open', 'date_deadline', 'division_ids', 'description',
                    'active', 'type',
                ], 1);

                if (empty($oppData[0])) throw new Exception('Opportunity not found in Odoo');
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
                    $uq  = strtolower($amEmail);
                    $us  = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
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

                // Stage from fetched data (more reliable than webhook payload)
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
                // i s s s i | s s d d | i s s | s s s s | s
                $ins->bind_param(
                    'isssissddisssssss',
                    $opp_id, $name, $amName, $amEmail, $localUserId,
                    $department, $companyName, $oppValue, $probability,
                    $fStageId, $fStageName, $name,
                    $internalNote, $odooUrl, $assignmentDate, $expectedClosing,
                    $divisionNames
                );
                $ins->execute();
                $pakd_created = $ins->affected_rows > 0;
                $ins->close();

            } catch (Exception $e) {
                error_log('[odoo_hook] auto-create pakd failed for opp #' . $opp_id . ': ' . $e->getMessage());
            }
        }
    }
}

http_response_code(200);
echo json_encode([
    'ok'           => true,
    'log_id'       => $log_id,
    'event_type'   => $event_type,
    'pakd_updated' => $pakd_updated,
    'pakd_created' => $pakd_created,
]);
