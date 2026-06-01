<?php
/**
 * sync_pakd_odoo.php
 * 
 * Sync PAKD (Phương Án Kinh Doanh) from Odoo CRM Opportunities.
 * Automatically creates PAKD records for opportunities at "Proposal" stage.
 *
 * This file is standalone - it includes OdooAPI.php but does NOT modify it.
 * Called via POST /projects/pakd/sync-odoo
 */

header('Content-Type: application/json');

$old_error_level = error_reporting(0);
require_once __DIR__ . '/../../config/config.php';
error_reporting($old_error_level);
require_once __DIR__ . '/../../libs/OdooAPI.php';

// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// ── Ensure pakd table exists ──────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    odoo_opp_id     INT NOT NULL UNIQUE COMMENT 'Odoo CRM opportunity ID',
    name            VARCHAR(500) NOT NULL COMMENT 'Tên phương án (= opportunity name)',
    department      VARCHAR(100) DEFAULT NULL,
    am_name         VARCHAR(255) DEFAULT NULL,
    am_email        VARCHAR(255) DEFAULT NULL,
    am_user_id      INT DEFAULT NULL COMMENT 'Local user id if matched',
    project_type    VARCHAR(50) DEFAULT 'external',
    currency        VARCHAR(10) DEFAULT 'VND',
    status          ENUM('draft','pending','approved','rejected') DEFAULT 'draft',
    opportunity_name VARCHAR(500) DEFAULT NULL,
    company_name    VARCHAR(500) DEFAULT NULL,
    opp_value       DECIMAL(20,2) DEFAULT 0,
    opp_probability DECIMAL(5,2) DEFAULT 0,
    odoo_stage_name VARCHAR(255) DEFAULT NULL,
    contract_no     VARCHAR(255) DEFAULT NULL,
    sales_order_no  VARCHAR(255) DEFAULT NULL,
    timeline        VARCHAR(500) DEFAULT NULL,
    internal_notes  TEXT DEFAULT NULL,
    odoo_url        VARCHAR(500) DEFAULT NULL COMMENT 'Direct link to Odoo opportunity',
    synced_at       DATETIME DEFAULT NULL,
    created_by      INT DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Migrate: add new columns if the table already existed without them ────────
foreach ([
    'assignment_date' => 'DATETIME DEFAULT NULL',
    'expected_closing'=> 'DATE DEFAULT NULL',
    'odoo_stage_id'   => 'INT DEFAULT NULL',
    'division_names'  => 'VARCHAR(500) DEFAULT NULL',
] as $_col => $_def) {
    $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pakd' AND COLUMN_NAME='$_col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE pakd ADD COLUMN `$_col` $_def");
}
unset($_col, $_def, $r);

// ── Ensure pakd_settings table exists ────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_by    INT DEFAULT NULL,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Load stage filter settings from DB ───────────────────────────────────────
// Admin can configure this via Settings modal on the PAKD list page
$savedStageIds = [];
$settingsRes = $conn->query("SELECT setting_value FROM pakd_settings WHERE setting_key = 'sync_stage_ids'");
if ($settingsRes && $sr = $settingsRes->fetch_assoc()) {
    $savedStageIds = array_map('intval', json_decode($sr['setting_value'], true) ?: []);
}

// Fallback keyword list used ONLY when no DB config is saved yet
define('PROPOSAL_STAGE_KEYWORDS', ['proposal', 'đề xuất', 'pasx', 'phương án', 'quotation', 'quote', 'sent']);

// ── Fetch from Odoo CRM ───────────────────────────────────────────────────────

try {
    $odoo = new OdooAPI();

    // Fields to fetch from crm.lead (CRM opportunities in Odoo)
    $fields = [
        'id',
        'name',
        'partner_id',
        'partner_name',
        'user_id',
        'team_id',
        'stage_id',
        'probability',
        'expected_revenue',
        'date_open',
        'date_deadline',
        'division_ids',
        'description',
        'active',
        'type',
        'won_status',            // "won" | "lost" | false
        'lost_reason_id',        // Many2one: {id, name}
    ];

    // Fetch ALL active opportunities (not archived)
    $domain = [
        ['type', '=', 'opportunity'],
        ['active', '=', true],
    ];

    $opportunities = $odoo->searchRead('crm.lead', $domain, $fields, 0, 0);

    if (!is_array($opportunities)) {
        throw new Exception('Không nhận được dữ liệu từ Odoo CRM.');
    }

    // Fetch Odoo base URL for building deep links
    $odooBaseUrl = rtrim($odoo->getUrl(), '/');

    // ── Filter: by configured stage IDs (DB) or fallback to keywords ─────────
    if (!empty($savedStageIds)) {
        // Use exact stage IDs saved in settings — most accurate
        $proposalOpps = array_filter($opportunities, function ($opp) use ($savedStageIds) {
            if (empty($opp['stage_id']) || !is_array($opp['stage_id'])) return false;
            return in_array((int)$opp['stage_id'][0], $savedStageIds, true);
        });
    } else {
        // Fallback: match stage name against keywords
        $proposalOpps = array_filter($opportunities, function ($opp) {
            $stageName = '';
            if (!empty($opp['stage_id']) && is_array($opp['stage_id'])) {
                $stageName = strtolower($opp['stage_id'][1] ?? '');
            }
            foreach (PROPOSAL_STAGE_KEYWORDS as $kw) {
                if (strpos($stageName, strtolower($kw)) !== false) return true;
            }
            return false;
        });
    }

    // If user explicitly passed stage filter via POST (for future flexibility)
    $forceAll = ($_POST['force_all'] ?? '0') === '1';
    if ($forceAll) {
        $proposalOpps = $opportunities; // sync all regardless of stage
    }

    // ── Load Lead/Opp Divisions map (model: lead.opp.divisions) ─────────────
    $divisionMap = [];
    try {
        $accounts = $odoo->searchRead('lead.opp.divisions', [], ['id', 'name'], 0, 0);
        foreach ($accounts as $a) $divisionMap[(int)$a['id']] = $a['name'];
    } catch (Exception $e) {}

    // ── Match local users by email ────────────────────────────────────────────
    $localUserMap = [];
    $usersRes = $conn->query("SELECT id, email, full_name FROM users");
    if ($usersRes) {
        while ($u = $usersRes->fetch_assoc()) {
            if (!empty($u['email'])) {
                $localUserMap[strtolower($u['email'])] = $u;
            }
        }
    }

    // Ensure won_status / lost_reason columns exist
    foreach ([
        "ALTER TABLE pakd ADD COLUMN won_status  VARCHAR(20)  DEFAULT NULL",
        "ALTER TABLE pakd ADD COLUMN lost_reason VARCHAR(255) DEFAULT NULL",
    ] as $_sql) { $conn->query($_sql); }

    // ── Sync each opportunity ─────────────────────────────────────────────────
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors  = [];

    foreach ($proposalOpps as $opp) {
        try {
            $odooId      = (int)$opp['id'];
            $name        = trim($opp['name'] ?? '');
            $companyName = '';
            if (!empty($opp['partner_name'])) {
                $companyName = $opp['partner_name'];
            } elseif (!empty($opp['partner_id']) && is_array($opp['partner_id'])) {
                $parts = explode(',', $opp['partner_id'][1] ?? '');
                $companyName = trim($parts[0]);
            }

            // AM
            $amName  = '';
            $amEmail = '';
            if (!empty($opp['user_id']) && is_array($opp['user_id'])) {
                $amName = $opp['user_id'][1] ?? '';
            }

            // Fetch AM email from Odoo (res.users)
            try {
                if (!empty($opp['user_id']) && is_array($opp['user_id'])) {
                    $odooUserId = $opp['user_id'][0];
                    $userData = $odoo->searchRead('res.users', [['id', '=', $odooUserId]], ['login'], 1);
                    if (!empty($userData[0]['login'])) {
                        $amEmail = $userData[0]['login'];
                    }
                }
            } catch (Exception $e) {
                // ignore, keep empty
            }

            // Match local user
            $localUserId = null;
            if ($amEmail && isset($localUserMap[strtolower($amEmail)])) {
                $localUserId = $localUserMap[strtolower($amEmail)]['id'];
            }

            // Department from team
            $department = '';
            if (!empty($opp['team_id']) && is_array($opp['team_id'])) {
                $department = $opp['team_id'][1] ?? '';
            }

            // Stage
            $stageName = '';
            $stageId   = null;
            if (!empty($opp['stage_id']) && is_array($opp['stage_id'])) {
                $stageId   = (int)$opp['stage_id'][0];
                $stageName = $opp['stage_id'][1] ?? '';
            }

            // Currency — crm.lead does not expose currency_id directly; default to VND
            $currency = 'VND';

            // Won / Lost status
            $wonStatus  = null;
            $ws = $opp['won_status'] ?? null;
            if (is_string($ws) && $ws !== '') $wonStatus = $ws;

            $lostReason = null;
            $lr = $opp['lost_reason_id'] ?? null;
            if (is_array($lr)) {
                $lostReason = $lr['name'] ?? ($lr[1] ?? null);
            }

            $probability      = (float)($opp['probability'] ?? 0);
            $oppValue         = (float)($opp['expected_revenue'] ?? 0);
            $internalNote     = $opp['description'] ?? '';
            $odooUrl          = $odooBaseUrl . '/odoo/crm/' . $odooId;
            $assignmentDate   = (!empty($opp['date_open']) && $opp['date_open'] !== false) ? $opp['date_open'] : null;
            $expectedClosing  = (!empty($opp['date_deadline']) && $opp['date_deadline'] !== false) ? $opp['date_deadline'] : null;
            $divNames = [];
            foreach (($opp['division_ids'] ?? []) as $did) {
                if (isset($divisionMap[(int)$did])) $divNames[] = $divisionMap[(int)$did];
            }
            $divisionNames = $divNames ? implode(', ', $divNames) : null;

            // ── Upsert into pakd table ────────────────────────────────────────
            $existing = $conn->prepare("SELECT id, status FROM pakd WHERE odoo_opp_id = ?");
            $existing->bind_param("i", $odooId);
            $existing->execute();
            $row = $existing->get_result()->fetch_assoc();
            $existing->close();

            if ($row) {
                // UPDATE: refresh metadata but do NOT overwrite manual edits (status, contract_no, etc.)
                $upd = $conn->prepare("
                    UPDATE pakd SET
                        name             = ?,
                        am_name          = ?,
                        am_email         = ?,
                        am_user_id       = ?,
                        department       = ?,
                        company_name     = ?,
                        opp_value        = ?,
                        opp_probability  = ?,
                        odoo_stage_id    = ?,
                        odoo_stage_name  = ?,
                        currency         = ?,
                        odoo_url         = ?,
                        assignment_date  = ?,
                        expected_closing = ?,
                        division_names   = ?,
                        won_status       = ?,
                        lost_reason      = ?,
                        synced_at        = NOW(),
                        updated_at       = NOW()
                    WHERE odoo_opp_id = ?
                ");
                $upd->bind_param(
                    "sssissddisssssssi",
                    $name, $amName, $amEmail, $localUserId,
                    $department, $companyName, $oppValue, $probability,
                    $stageId, $stageName, $currency, $odooUrl,
                    $assignmentDate, $expectedClosing, $divisionNames,
                    $wonStatus, $lostReason, $odooId
                );
                $upd->execute();
                $upd->close();
                $updated++;
            } else {
                // INSERT new PAKD
                $ins = $conn->prepare("
                    INSERT INTO pakd (
                        odoo_opp_id, name, am_name, am_email, am_user_id,
                        department, company_name, opp_value, opp_probability,
                        odoo_stage_id, odoo_stage_name, currency, opportunity_name,
                        internal_notes, odoo_url, assignment_date, expected_closing,
                        division_names, status, created_by, synced_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, 'draft', ?, NOW(), NOW()
                    )
                ");
                $oppName   = $name;
                $createdBy = (int)$_SESSION['user_id'];
                $ins->bind_param(
                    "isssissddissssssssi",
                    $odooId, $name, $amName, $amEmail, $localUserId,
                    $department, $companyName, $oppValue, $probability,
                    $stageId, $stageName, $currency, $oppName,
                    $internalNote, $odooUrl, $assignmentDate, $expectedClosing,
                    $divisionNames, $createdBy
                );
                $ins->execute();
                $ins->close();
                $created++;
            }
        } catch (Exception $e) {
            $errors[] = "Opp #{$opp['id']} ({$opp['name']}): " . $e->getMessage();
            $skipped++;
        }
    }

    // ── Pass 2: sync won_status for ALL pakd opps (won=active, lost=archived) ─
    // Won opps stay active but leave the proposal stage → missed by main filter.
    // Lost opps are archived (active=false) → missed by main domain.
    // Fetch ALL known opp IDs with active_test=false to cover both cases.
    $wonUpdated = 0;
    try {
        $res = $conn->query("SELECT odoo_opp_id FROM pakd WHERE odoo_opp_id IS NOT NULL");
        $knownIds = [];
        if ($res) while ($r = $res->fetch_row()) $knownIds[] = (int)$r[0];

        if ($knownIds) {
            // Use explicit OR domain so Odoo returns both active and archived records
            $allOpps = $odoo->searchRead(
                'crm.lead',
                ['|', ['active', '=', true], ['active', '=', false], ['id', 'in', $knownIds]],
                ['id', 'won_status', 'lost_reason_id', 'stage_id'],
                0, 0,
                ['active_test' => false]
            );
            foreach ((array)$allOpps as $ao) {
                $aoId        = (int)$ao['id'];
                $aoWonStatus = (is_string($ao['won_status'] ?? null) && $ao['won_status'] !== '') ? $ao['won_status'] : null;
                $aoLostRaw   = $ao['lost_reason_id'] ?? null;
                $aoLostReason = null;
                if (is_array($aoLostRaw)) $aoLostReason = $aoLostRaw['name'] ?? ($aoLostRaw[1] ?? null);

                // Extract stage from pass-2 data
                $aoStageId   = null;
                $aoStageName = null;
                if (!empty($ao['stage_id']) && is_array($ao['stage_id'])) {
                    $aoStageId   = (int)($ao['stage_id']['id'] ?? $ao['stage_id'][0] ?? null);
                    $aoStageName = $ao['stage_id']['name'] ?? ($ao['stage_id'][1] ?? null);
                }

                // Update won_status (+ stage) for won/lost records
                if ($aoWonStatus) {
                    if ($aoWonStatus === 'won') $aoLostReason = null;
                    $u = $conn->prepare(
                        "UPDATE pakd
                         SET won_status       = ?,
                             lost_reason      = ?,
                             odoo_stage_id    = COALESCE(?, odoo_stage_id),
                             odoo_stage_name  = COALESCE(?, odoo_stage_name),
                             updated_at       = NOW()
                         WHERE odoo_opp_id = ?"
                    );
                    $u->bind_param('ssisi', $aoWonStatus, $aoLostReason, $aoStageId, $aoStageName, $aoId);
                    $u->execute();
                    if ($u->affected_rows > 0) $wonUpdated++;
                    $u->close();
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = 'won_status sync: ' . $e->getMessage();
    }

    // ── Response ──────────────────────────────────────────────────────────────
    echo json_encode([
        'success'        => true,
        'total_fetched'  => count($opportunities),
        'proposal_count' => count($proposalOpps),
        'created'        => $created,
        'updated'        => $updated,
        'won_status_updated' => $wonUpdated,
        'skipped'        => $skipped,
        'errors'         => $errors,
        'message'        => "Đồng bộ thành công: {$created} tạo mới, {$updated} cập nhật, {$wonUpdated} cập nhật won/loss" . ($skipped > 0 ? ", {$skipped} lỗi" : '') . '.',
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
