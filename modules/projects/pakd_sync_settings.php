<?php
/**
 * pakd_sync_settings.php
 *
 * Handles PAKD sync stage settings:
 *   GET  ?action=get_stages   → fetch all CRM stages from Odoo + saved settings
 *   POST action=save_stages   → save selected stage IDs to DB
 */

header('Content-Type: application/json');

$old_error_level = error_reporting(0);
require_once __DIR__ . '/../../config/config.php';
error_reporting($old_error_level);
require_once __DIR__ . '/../../libs/OdooAPI.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// ── Ensure settings table ─────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS pakd_settings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_stages');

// ── GET stages from Odoo ──────────────────────────────────────────────────────
if ($action === 'get_stages') {
    try {
        $odoo = new OdooAPI();

        // Fetch all CRM stages from Odoo
        $stages = $odoo->searchRead(
            'crm.stage',
            [],
            ['id', 'name', 'sequence', 'is_won'],
            0, 0
        );

        if (!is_array($stages)) {
            throw new Exception('Không lấy được stages từ Odoo.');
        }

        // Sort by sequence
        usort($stages, fn($a, $b) => ($a['sequence'] ?? 99) - ($b['sequence'] ?? 99));

        // Load saved settings
        $savedIds = [];
        $savedWonId = null;
        $res = $conn->query("SELECT setting_key, setting_value FROM pakd_settings WHERE setting_key IN ('sync_stage_ids', 'sync_won_stage_id')");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ($row['setting_key'] === 'sync_stage_ids') {
                    $savedIds = json_decode($row['setting_value'], true) ?: [];
                } elseif ($row['setting_key'] === 'sync_won_stage_id') {
                    $savedWonId = (int)$row['setting_value'];
                }
            }
        }

        echo json_encode([
            'success'   => true,
            'stages'    => $stages,
            'saved_ids' => array_map('intval', $savedIds),
            'saved_won_stage_id' => $savedWonId,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// ── POST: save selected stage IDs ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_stages') {
    $role = $_SESSION['role'] ?? 'user';
    if ($role !== 'admin' && $role !== 'manager') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit();
    }

    $rawIds = $_POST['stage_ids'] ?? [];
    if (is_string($rawIds)) {
        $rawIds = json_decode($rawIds, true) ?: [];
    }
    $stageIds    = array_map('intval', $rawIds);
    $stageNames  = $_POST['stage_names'] ?? [];
    if (is_string($stageNames)) {
        $stageNames = json_decode($stageNames, true) ?: [];
    }
    
    $wonStageId = isset($_POST['won_stage_id']) ? (int)$_POST['won_stage_id'] : null;

    $userId = (int)$_SESSION['user_id'];
    $idsJson   = json_encode($stageIds,  JSON_UNESCAPED_UNICODE);
    $namesJson = json_encode($stageNames, JSON_UNESCAPED_UNICODE);

    // Upsert sync_stage_ids
    $s1 = $conn->prepare("INSERT INTO pakd_settings (setting_key, setting_value, updated_by)
                           VALUES ('sync_stage_ids', ?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
    $s1->bind_param('si', $idsJson, $userId);
    $s1->execute();
    $s1->close();

    // Upsert sync_stage_names
    $s2 = $conn->prepare("INSERT INTO pakd_settings (setting_key, setting_value, updated_by)
                           VALUES ('sync_stage_names', ?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
    $s2->bind_param('si', $namesJson, $userId);
    $s2->execute();
    $s2->close();
    
    // Upsert sync_won_stage_id
    if ($wonStageId !== null) {
        $s3 = $conn->prepare("INSERT INTO pakd_settings (setting_key, setting_value, updated_by)
                               VALUES ('sync_won_stage_id', ?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        $strWonId = (string)$wonStageId;
        $s3->bind_param('si', $strWonId, $userId);
        $s3->execute();
        $s3->close();
    }

    echo json_encode([
        'success' => true,
        'saved_count' => count($stageIds),
        'message' => count($stageIds) > 0
            ? 'Đã lưu ' . count($stageIds) . ' stage(s) sẽ được sync.'
            : 'Cảnh báo: Không có stage nào được chọn — sync sẽ không lấy được dữ liệu.',
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
