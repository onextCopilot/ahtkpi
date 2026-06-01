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
$conn->query("ALTER TABLE odoo_webhook_logs ADD COLUMN IF NOT EXISTS result_notes TEXT DEFAULT NULL");

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
            $debug['action'] = 'update_stage';

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
