<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$log = function($msg) {
    file_put_contents(__DIR__ . '/../debug_pakd_api.log', date('H:i:s') . " $msg\n", FILE_APPEND);
};

$log("Method={$_SERVER['REQUEST_METHOD']} Session=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NONE'));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// One-time migration: ai_addon was TINYINT, must be VARCHAR to store 'aihive'/'ai_solutions'
$conn->query("ALTER TABLE invoice_pakd_map MODIFY COLUMN ai_addon VARCHAR(50) DEFAULT ''");
// Lead to Oppty: column stores a user attribution ('me' / user id) — ensure it's VARCHAR.
$oppty_col = $conn->query("SHOW COLUMNS FROM invoice_pakd_map LIKE 'lead_oppty'");
if ($oppty_col && $oppty_col->num_rows === 0) {
    $conn->query("ALTER TABLE invoice_pakd_map ADD COLUMN lead_oppty VARCHAR(100) DEFAULT NULL");
} else {
    $conn->query("ALTER TABLE invoice_pakd_map MODIFY COLUMN lead_oppty VARCHAR(100) DEFAULT NULL");
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $log("POST body=$raw");
    $data = json_decode($raw, true);
    $invoice_id = (int) ($data['invoice_id'] ?? 0);
    $pakd_id = (int) ($data['pakd_id'] ?? 0);
    $pakd_link = trim($data['pakd_link'] ?? '');
    $manual_ebt = isset($data['manual_ebt']) && $data['manual_ebt'] !== '' && $data['manual_ebt'] !== null
        ? (float) $data['manual_ebt'] : null;
    $com2_tier = isset($data['com2_tier']) && $data['com2_tier'] !== '' && $data['com2_tier'] !== null
        ? (float) $data['com2_tier'] : null;
    $com2_hv = isset($data['com2_hv']) && $data['com2_hv'] !== '' && $data['com2_hv'] !== null
        ? (float) $data['com2_hv'] : null;
    // Accept any ISO-style 3-letter currency code (the currency list is driven by Odoo).
    $com2_hv_currency = isset($data['com2_hv_currency']) && preg_match('/^[A-Z]{3}$/', $data['com2_hv_currency'])
        ? $data['com2_hv_currency'] : 'VND';
    // Market to Lead attribution: '' (clear) · 'self' (My Lead) · 'other' (ngoài công ty) · numeric user id
    $lv = isset($data['lead_source']) ? trim((string) $data['lead_source']) : '';
    $lead_source = ($lv === 'self' || $lv === 'other') ? $lv : (ctype_digit($lv) ? $lv : null);
    // Lead to Oppty attribution: '' (clear) · 'me' (Converted By Me) · 'other' (ngoài công ty) · numeric user id
    $ov = isset($data['lead_oppty']) ? trim((string) $data['lead_oppty']) : '';
    $lead_oppty = ($ov === 'me' || $ov === 'other') ? $ov : (ctype_digit($ov) ? $ov : null);
    // AI Add-on: '', 'ai_solutions', 'aihive'
    $ai_addon = isset($data['ai_addon']) && in_array($data['ai_addon'], ['ai_solutions', 'aihive'], true)
        ? $data['ai_addon'] : null;
    $ai_revenue = isset($data['ai_revenue']) && $data['ai_revenue'] !== '' && $data['ai_revenue'] !== null
        ? (float) $data['ai_revenue'] : null;
    $ai_revenue_currency = isset($data['ai_revenue_currency']) && preg_match('/^[A-Z]{3}$/', $data['ai_revenue_currency'])
        ? $data['ai_revenue_currency'] : 'VND';

    if (!$invoice_id) {
        $log("ERROR: missing invoice_id");
        echo json_encode(['error' => 'Missing invoice_id']);
        exit;
    }

    // Update com2_tier only
    if (isset($data['update_tier'])) {
        // Ensure a row exists so the tier can be stored even without a linked PAKD.
        // pakd_id is NOT NULL with no default, so supply 0 for new rows; existing rows keep theirs.
        $zero = 0;
        $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, user_id, pakd_id, com2_tier) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE com2_tier = VALUES(com2_tier)");
        $stmt->bind_param("iiid", $invoice_id, $user_id, $zero, $com2_tier);
        $ok = $stmt->execute();
        $log("TIER_UPDATED inv=$invoice_id tier=" . var_export($com2_tier, true) . " ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
        echo json_encode(['ok' => $ok, 'action' => 'tier_updated', 'error' => $stmt->error ?: null]);
        exit;
    }

    // Update HV (High Value) + its currency only
    if (isset($data['update_hv'])) {
        $zero = 0;
        $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, user_id, pakd_id, com2_hv, com2_hv_currency) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE com2_hv = VALUES(com2_hv), com2_hv_currency = VALUES(com2_hv_currency)");
        $stmt->bind_param("iiids", $invoice_id, $user_id, $zero, $com2_hv, $com2_hv_currency);
        $ok = $stmt->execute();
        $log("HV_UPDATED inv=$invoice_id hv=" . var_export($com2_hv, true) . " cur=$com2_hv_currency ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
        echo json_encode(['ok' => $ok, 'action' => 'hv_updated', 'error' => $stmt->error ?: null]);
        exit;
    }

    // Update Market-to-Lead source only
    if (isset($data['update_lead'])) {
        $zero = 0;
        $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, user_id, pakd_id, lead_source) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE lead_source = VALUES(lead_source)");
        $stmt->bind_param("iiis", $invoice_id, $user_id, $zero, $lead_source);
        $ok = $stmt->execute();
        $log("LEAD_UPDATED inv=$invoice_id lead=" . var_export($lead_source, true) . " ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
        echo json_encode(['ok' => $ok, 'action' => 'lead_updated', 'error' => $stmt->error ?: null]);
        exit;
    }

    // Update Lead-to-Oppty attribution only
    if (isset($data['update_oppty'])) {
        $zero = 0;
        $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, user_id, pakd_id, lead_oppty) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE lead_oppty = VALUES(lead_oppty)");
        $stmt->bind_param("iiis", $invoice_id, $user_id, $zero, $lead_oppty);
        $ok = $stmt->execute();
        $log("OPPTY_UPDATED inv=$invoice_id oppty=" . var_export($lead_oppty, true) . " ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
        echo json_encode(['ok' => $ok, 'action' => 'oppty_updated', 'error' => $stmt->error ?: null]);
        exit;
    }

    // Update AI Add-on type only
    if (isset($data['update_ai_addon'])) {
        $zero = 0;
        $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, user_id, pakd_id, ai_addon) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ai_addon = VALUES(ai_addon)");
        $stmt->bind_param("iiis", $invoice_id, $user_id, $zero, $ai_addon);
        $ok = $stmt->execute();
        $log("AI_ADDON_UPDATED inv=$invoice_id addon=" . var_export($ai_addon, true) . " ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
        echo json_encode(['ok' => $ok, 'action' => 'ai_addon_updated', 'error' => $stmt->error ?: null]);
        exit;
    }

    // Update AI Revenue + its currency only
    if (isset($data['update_ai_revenue'])) {
        $zero = 0;
        $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, user_id, pakd_id, ai_revenue, ai_revenue_currency) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE ai_revenue = VALUES(ai_revenue), ai_revenue_currency = VALUES(ai_revenue_currency)");
        $stmt->bind_param("iiids", $invoice_id, $user_id, $zero, $ai_revenue, $ai_revenue_currency);
        $ok = $stmt->execute();
        $log("AI_REV_UPDATED inv=$invoice_id rev=" . var_export($ai_revenue, true) . " cur=$ai_revenue_currency ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
        echo json_encode(['ok' => $ok, 'action' => 'ai_revenue_updated', 'error' => $stmt->error ?: null]);
        exit;
    }

    // Clear action
    if ($pakd_id === 0 && $pakd_link === '' && !isset($data['update_ebt'])) {
        $stmt = $conn->prepare("DELETE FROM invoice_pakd_map WHERE invoice_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $invoice_id, $user_id);
        $stmt->execute();
        $log("DELETED inv=$invoice_id");
        echo json_encode(['ok' => true, 'action' => 'removed']);
        exit;
    }

    // Update manual_ebt only
    if (isset($data['update_ebt'])) {
        $stmt = $conn->prepare("UPDATE invoice_pakd_map SET manual_ebt = ? WHERE invoice_id = ? AND user_id = ?");
        $stmt->bind_param("dii", $manual_ebt, $invoice_id, $user_id);
        $stmt->execute();
        $log("EBT_UPDATED inv=$invoice_id ebt=$manual_ebt affected=" . $stmt->affected_rows);
        echo json_encode(['ok' => true, 'action' => 'ebt_updated']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO invoice_pakd_map (invoice_id, pakd_id, user_id, pakd_link, manual_ebt) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE pakd_id = VALUES(pakd_id), pakd_link = VALUES(pakd_link), manual_ebt = VALUES(manual_ebt)");
    if (!$stmt) {
        $log("PREPARE ERROR: " . $conn->error);
        echo json_encode(['error' => $conn->error]);
        exit;
    }
    $stmt->bind_param("iiisd", $invoice_id, $pakd_id, $user_id, $pakd_link, $manual_ebt);
    $ok = $stmt->execute();
    $log("SAVED inv=$invoice_id pakd=$pakd_id link=$pakd_link ok=" . ($ok ? 'Y' : 'N') . " err=" . $stmt->error);
    echo json_encode(['ok' => $ok, 'action' => 'saved', 'error' => $stmt->error ?: null]);
    exit;
}

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT invoice_id, pakd_id, pakd_link, manual_ebt, com2_tier, com2_hv, com2_hv_currency, lead_source, lead_oppty, ai_addon, ai_revenue, ai_revenue_currency FROM invoice_pakd_map WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($r = $res->fetch_assoc()) {
        $map[$r['invoice_id']] = $r;
    }
    echo json_encode($map);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
