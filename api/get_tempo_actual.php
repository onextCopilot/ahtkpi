<?php
/**
 * API: Get Tempo actual hours for a single project
 * GET /api/get_tempo_actual.php?project=PROJKEY&year=2026
 */
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
set_time_limit(20);

$projectKey = trim($_GET['project'] ?? '');
if (!$projectKey) {
    echo json_encode(['error' => 'Missing project key', 'hours' => 0, 'entry_count' => 0]);
    exit();
}

$year     = (int)($_GET['year'] ?? date('Y'));
$dateFrom = $year . '-01-01';
$dateTo   = $year . '-12-31';

$configFile = __DIR__ . '/../config/jira_config.json';
if (!file_exists($configFile)) {
    echo json_encode(['error' => 'Jira not configured', 'hours' => 0, 'entry_count' => 0]);
    exit();
}

$cfg   = json_decode(file_get_contents($configFile), true);
$base  = rtrim($cfg['jira_url'] ?? '', '/');
$email = $cfg['jira_email'] ?? '';
$token = $cfg['jira_token'] ?? '';

if (!$base || !$token) {
    echo json_encode(['error' => 'Jira not configured', 'hours' => 0, 'entry_count' => 0]);
    exit();
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

// ── Caching Logic for Tempo Data ──
$totalSeconds = 0;
$entryCount = 0;
$tempoCached = false;

// Try to load from cache first
if (!$forceRefresh) {
    $tRes = $conn->query("SELECT * FROM folio_tempo_cache WHERE jira_key = '" . $conn->real_escape_string($projectKey) . "' AND year = $year");
    if ($tRes && $tRes->num_rows > 0) {
        $tRow = $tRes->fetch_assoc();
        $totalSeconds = intval($tRow['seconds']);
        $entryCount = intval($tRow['entry_count']);
        $tempoCached = true;
    }
}

if (!$tempoCached || $forceRefresh) {
    $auth     = base64_encode("$email:$token");
    $endpoint = $base . '/rest/tempo-timesheets/3/worklogs'
        . '?projectKey=' . urlencode($projectKey)
        . '&dateFrom='   . urlencode($dateFrom)
        . '&dateTo='     . urlencode($dateTo);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Basic $auth", "Accept: application/json"],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $tempoError = null;
    if ($code !== 200) {
        $tempoError = "Tempo HTTP $code";
        $totalSeconds = 0;
        $entryCount = 0;
    } else {
        $worklogs     = json_decode($body, true);
        $totalSeconds = 0;
        if (is_array($worklogs)) {
            foreach ($worklogs as $wl) {
                $totalSeconds += intval($wl['timeSpentSeconds'] ?? $wl['timeSpent'] ?? 0);
            }
        }
        $entryCount = is_array($worklogs) ? count($worklogs) : 0;
        $hours = round($totalSeconds / 3600, 1);
        
        $jk  = $conn->real_escape_string($projectKey);
        $conn->query("INSERT INTO folio_tempo_cache (jira_key, year, hours, seconds, entry_count) 
            VALUES ('$jk', $year, $hours, $totalSeconds, $entryCount)
            ON DUPLICATE KEY UPDATE hours=$hours, seconds=$totalSeconds, entry_count=$entryCount");
    }
}

// ── Caching Logic for Folio Data ──
$folioData    = null;

// Try to load from cache first
$cacheRes = $conn->query("SELECT * FROM folio_budget_cache WHERE jira_key = '" . $conn->real_escape_string($projectKey) . "'");
if ($cacheRes && $cacheRes->num_rows > 0 && !$forceRefresh) {
    $row = $cacheRes->fetch_assoc();
    $folioData = [
        'id'          => $row['folio_id'],
        'name'        => $row['folio_name'],
        'budget'      => floatval($row['budget']),
        'plan_cost'   => floatval($row['plan']),
        'actual_cost' => floatval($row['actual']),
        'currency'    => $row['currency'],
        'cached_at'   => $row['synced_at']
    ];
}

// Fetch fresh if needed
if (!$folioData || $forceRefresh) {
    require_once __DIR__ . '/../libs/JiraAPI.php';
    $jira = new JiraAPI();
    
    // Try searching by project key first, then by project name if provided
    $projectName = trim($_GET['name'] ?? '');
    $freshData = $jira->getFolioFinancials($projectKey);
    
    if (!$freshData && $projectName) {
        $freshData = $jira->getFolioFinancials($projectName);
    }
    
    if ($freshData) {
        $folioData = $freshData;
        // Update cache
        $jk  = $conn->real_escape_string($projectKey);
        $fid = $conn->real_escape_string($freshData['folio_id'] ?? '');
        $fn  = $conn->real_escape_string($freshData['name'] ?? '');
        $bgt = floatval($freshData['budget'] ?? 0);
        $plc = floatval($freshData['plan_cost'] ?? 0);
        $act = floatval($freshData['actual_cost'] ?? 0);
        $cur = $conn->real_escape_string($freshData['currency'] ?? 'USD');
        
        $conn->query("INSERT INTO folio_budget_cache (jira_key, folio_id, folio_name, budget, plan, actual, currency)
            VALUES ('$jk', '$fid', '$fn', $bgt, $plc, $act, '$cur')
            ON DUPLICATE KEY UPDATE folio_id='$fid', folio_name='$fn', budget=$bgt, plan=$plc, actual=$act, currency='$cur'");
    }
}

echo json_encode([
    'project'     => $projectKey,
    'hours'       => round($totalSeconds / 3600, 1),
    'seconds'     => $totalSeconds,
    'entry_count' => $entryCount,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'folio_data'  => $folioData,
    'error'       => $tempoError,
    'is_cached'   => ($tempoCached && !isset($freshData))
]);
