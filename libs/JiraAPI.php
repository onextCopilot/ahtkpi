<?php
// Suppress potential session warnings from config.php on live server
$old_error_level = error_reporting(0);
require_once __DIR__ . '/../config/config.php';
error_reporting($old_error_level);

class JiraAPI
{
    private $jiraUrl;
    private $email;
    private $token;
    private $cacheFile = __DIR__ . '/../cache/jira_projects.cache.php';
    private $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $configFile = __DIR__ . '/../config/jira_config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->jiraUrl = rtrim($config['jira_url'] ?? '', '/');
            $this->email = $config['jira_email'] ?? '';
            $this->token = $config['jira_token'] ?? '';
        }
    }

    public function getProjects($forceRefresh = false)
    {
        // Check cache validity
        if (!$forceRefresh && file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < $this->cacheDuration)) {
            $content = file_get_contents($this->cacheFile);
            // Remove security header
            $json = str_replace('<?php exit; ?>', '', $content);
            $projects = json_decode($json, true);
            if (is_array($projects)) {
                return $projects;
            }
        }

        // Cache expired or missing or forced refresh
        return $this->refreshProjectCache();
    }

    public function refreshProjectCache()
    {
        if (empty($this->jiraUrl) || empty($this->token)) {
            throw new Exception("Jira configuration missing.");
        }

        $endpoint = $this->jiraUrl . '/rest/api/2/project';
        $response = $this->callApi($endpoint);

        if (!is_array($response)) {
            throw new Exception("Invalid response from Jira API");
        }

        // Process and simplify data to save space
        $projects = array_map(function ($p) {
            return [
                'id' => $p['id'],
                'key' => $p['key'],
                'name' => $p['name'],
                'avatar' => $p['avatarUrls']['48x48'] ?? ($p['avatarUrls']['32x32'] ?? ''),
                'projectTypeKey' => $p['projectTypeKey'] ?? ''
            ];
        }, $response);

        // Save to cache with security header
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0777, true);
        }

        $content = '<?php exit; ?>' . json_encode($projects);
        file_put_contents($this->cacheFile, $content);

        return $projects;
    }

    private function callApi($url, $method = 'GET', $data = null)
    {
        $ch = curl_init();
        $auth = base64_encode($this->email . ':' . $this->token);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic $auth",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // curl_close($ch); // Deprecated in PHP 8.0+ but safe to omit

        if ($error) {
            throw new Exception("Curl Error: $error");
        }

        if ($httpCode >= 400) {
            throw new Exception("Jira API Error ($httpCode): $result");
        }

        return json_decode($result, true);
    }

    public function getCacheInfo()
    {
        if (file_exists($this->cacheFile)) {
            return [
                'exists' => true,
                'time' => filemtime($this->cacheFile),
                'size' => filesize($this->cacheFile)
            ];
        }
        return ['exists' => false];
    }
    public function getProjectDetails($keyOrId)
    {
        if (empty($this->jiraUrl) || empty($this->token)) {
            // Try to load config if not loaded
            $configFile = __DIR__ . '/../config/jira_config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                $this->jiraUrl = rtrim($config['jira_url'] ?? '', '/');
                $this->email = $config['jira_email'] ?? '';
                $this->token = $config['jira_token'] ?? '';
            }
        }

        if (empty($this->jiraUrl) || empty($this->token)) {
            return null;
        }

        $endpoint = $this->jiraUrl . '/rest/api/2/project/' . $keyOrId;
        try {
            return $this->callApi($endpoint);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getIssueCounts($key)
    {
        $stats = [
            'total' => 0,
            'unresolved' => 0,
            'log_seconds' => 0
        ];

        if (empty($this->jiraUrl))
            return $stats;

        // 1. Total Issues + partial worklog sum
        try {
            $jql = "project = '$key'";
            $endpoint = $this->jiraUrl . '/rest/api/2/search?jql=' . urlencode($jql) . '&maxResults=1000&fields=timespent';
            $res = $this->callApi($endpoint);
            if (isset($res['total'])) {
                $stats['total'] = $res['total'];
                if (isset($res['issues']) && is_array($res['issues'])) {
                    foreach ($res['issues'] as $issue) {
                        $stats['log_seconds'] += ($issue['fields']['timespent'] ?? 0);
                    }
                }
            }

            // 2. Unresolved
            $jqlUn = "project = '$key' AND resolution = Unresolved";
            $endpointUn = $this->jiraUrl . '/rest/api/2/search?jql=' . urlencode($jqlUn) . '&maxResults=0';
            $resUn = $this->callApi($endpointUn);
            if (isset($resUn['total'])) {
                $stats['unresolved'] = $resUn['total'];
            }

        } catch (Exception $e) {
            // silent fail
        }

        return $stats;
    }

    public function getAssignableUserCount($key)
    {
        if (empty($this->jiraUrl))
            return 0;
        try {
            $endpoint = $this->jiraUrl . '/rest/api/2/user/assignable/search?project=' . $key;
            $users = $this->callApi($endpoint);
            return is_array($users) ? count($users) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getAssignableUsers($key)
    {
        if (empty($this->jiraUrl))
            return [];
        try {
            $endpoint = $this->jiraUrl . '/rest/api/2/user/assignable/search?project=' . $key;
            $users = $this->callApi($endpoint);
            return is_array($users) ? $users : [];
        } catch (Exception $e) {
            return [];
        }
    }

    // ─── Tempo Budgets API ─────────────────────────────────────────────────

    /**
     * Get all Tempo Folios (Budget portfolios).
     * Endpoint: GET /rest/tempo-budgets/1/folio
     */
    public function getTempoFolios()
    {
        if (empty($this->jiraUrl)) return [];
        try {
            $endpoint = $this->jiraUrl . '/rest/tempo-budgets/1/folio';
            $res = $this->callApi($endpoint);
            return is_array($res) ? $res : [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get a single Folio by ID.
     */
    public function getTempoFolioById($folioId)
    {
        if (empty($this->jiraUrl)) return null;
        try {
            $endpoint = $this->jiraUrl . '/rest/tempo-budgets/1/folio/' . urlencode($folioId);
            return $this->callApi($endpoint);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get budget summary for a Folio (budget, plannedCost, actualCost).
     */
    public function getTempoFolioSummary($folioId)
    {
        if (empty($this->jiraUrl)) return null;
        try {
            $endpoint = $this->jiraUrl . '/rest/tempo-budgets/1/folio/' . urlencode($folioId) . '/budget';
            $res = $this->callApi($endpoint);
            if (is_array($res)) return $res;
        } catch (Exception $e) { /* fallback */ }
        return $this->getTempoFolioById($folioId);
    }

    /**
     * Build a map of Jira Project Key → Tempo Folio budget data.
     */
    public function getTempoBudgetsByProject()
    {
        $folios = $this->getTempoFolios();
        if (empty($folios)) return [];

        $map = [];
        foreach ($folios as $folio) {
            $folioId = $folio['id'] ?? $folio['folioId'] ?? null;
            if (!$folioId) continue;

            $projectKeys = [];
            if (!empty($folio['projects']) && is_array($folio['projects'])) {
                foreach ($folio['projects'] as $proj) {
                    $k = $proj['key'] ?? $proj['projectKey'] ?? null;
                    if ($k) $projectKeys[] = $k;
                }
            }
            if (empty($projectKeys) && !empty($folio['projectKey'])) {
                $projectKeys[] = $folio['projectKey'];
            }
            if (empty($projectKeys)) continue;

            $budget   = floatval($folio['budget']        ?? $folio['totalBudget']    ?? $folio['budgetedAmount']     ?? 0);
            $plan     = floatval($folio['plannedCost']   ?? $folio['plannedAmount']  ?? $folio['totalPlannedCost']   ?? 0);
            $actual   = floatval($folio['actualCost']    ?? $folio['usedAmount']     ?? $folio['totalActualCost']    ?? $folio['expenditureAmount'] ?? 0);
            $currency = $folio['currency'] ?? $folio['currencyCode'] ?? 'USD';
            $name     = $folio['name'] ?? $folio['folioName'] ?? '';

            $entry = compact('budget', 'plan', 'actual', 'currency', 'name', 'folioId');
            foreach ($projectKeys as $pk) {
                $map[$pk] = $entry;
            }
        }
        return $map;
    }
    /**
     * Get total ACTUAL logged hours per project via Tempo Timesheets v3.
     * Endpoint: GET /rest/tempo-timesheets/3/worklogs?projectKey=X&dateFrom=Y&dateTo=Z
     * Returns: ['PROJKEY' => ['hours' => float, 'seconds' => int, 'entry_count' => int]]
     */
    public function getTempoActualHoursByProject(array $projectKeys, string $dateFrom, string $dateTo): array
    {
        if (empty($this->jiraUrl) || empty($projectKeys)) return [];

        $result = [];
        foreach ($projectKeys as $key) {
            $endpoint = $this->jiraUrl
                . '/rest/tempo-timesheets/3/worklogs'
                . '?projectKey=' . urlencode($key)
                . '&dateFrom=' . urlencode($dateFrom)
                . '&dateTo='   . urlencode($dateTo);
            try {
                $ch = curl_init();
                $auth = base64_encode($this->email . ':' . $this->token);
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $endpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: Basic $auth",
                        "Accept: application/json",
                    ],
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $body    = curl_exec($ch);
                $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($code !== 200) {
                    $result[$key] = ['hours' => 0, 'seconds' => 0, 'entry_count' => 0, 'error' => "HTTP $code"];
                    continue;
                }

                $worklogs = json_decode($body, true);
                if (!is_array($worklogs)) {
                    $result[$key] = ['hours' => 0, 'seconds' => 0, 'entry_count' => 0];
                    continue;
                }

                $totalSeconds = 0;
                foreach ($worklogs as $wl) {
                    // Tempo v3 field: timeSpentSeconds
                    $totalSeconds += intval($wl['timeSpentSeconds'] ?? $wl['timeSpent'] ?? 0);
                }

                $result[$key] = [
                    'hours'       => round($totalSeconds / 3600, 2),
                    'seconds'     => $totalSeconds,
                    'entry_count' => count($worklogs),
                ];
            } catch (Exception $e) {
                $result[$key] = ['hours' => 0, 'seconds' => 0, 'entry_count' => 0, 'error' => $e->getMessage()];
            }
        }
        return $result;
    }

    /**
     * Get automated Plan Cost and Actual Cost from Tempo Planning (Folio) API.
     * Requires Global API Token.
     */
    public function getFolioFinancials($projectKey)
    {
        if (empty($this->jiraUrl)) return null;

        $tempoToken = '88889431-ed02-4d4f-92bb-0b27457d7cc4';
        
        try {
            // Find Folio ID by project key using portfolio/search
            $findUrl = $this->jiraUrl . '/rest/tempo-planning/1/portfolio/search?query=' . urlencode($projectKey) . '&tempoApiToken=' . $tempoToken;
            $res = $this->callApi($findUrl);
            
            $foundFolio = null;
            $items = [];
            if (isset($res['success']['folios']) && is_array($res['success']['folios'])) {
                $items = array_merge($items, $res['success']['folios']);
            }
            if (isset($res['success']['portfolios']) && is_array($res['success']['portfolios'])) {
                $items = array_merge($items, $res['success']['portfolios']);
            }
            if (empty($items) && isset($res['success']) && isset($res['success']['id'])) {
                $items[] = $res['success'];
            }

            foreach ($items as $item) {
                $folioName = $item['name'] ?? '';
                $filterQuery = $item['currentFilter']['query'] ?? '';
                
                $match = (strcasecmp($folioName, $projectKey) === 0 || stripos($folioName, $projectKey) !== false || stripos($projectKey, $folioName) !== false);
                if (!$match && $filterQuery) {
                    $pq = strtolower($projectKey);
                    if (stripos($filterQuery, "project = " . $pq) !== false || 
                        stripos($filterQuery, "project=" . $pq) !== false ||
                        stripos($filterQuery, "project in (" . $pq) !== false ||
                        stripos($filterQuery, "'" . $pq . "'") !== false) {
                        $match = true;
                    }
                }
                if ($match) {
                    $foundFolio = $item;
                    break;
                }
            }

            if (!$foundFolio || !isset($foundFolio['id'])) {
                return null;
            }
            
            $folioId = $foundFolio['id'];
            
            // Get Overview data
            $overviewUrl = $this->jiraUrl . '/rest/tempo-planning/1/folio/' . $folioId . '/overview?tempoApiToken=' . $tempoToken;
            $overview = $this->callApi($overviewUrl);
            
            if (isset($overview['success']['costs'])) {
                return [
                    'folio_id' => $folioId,
                    'name' => $foundFolio['name'] ?? '',
                    'plan_cost' => $overview['success']['costs']['totalPlanned'] ?? 0,
                    'actual_cost' => $overview['success']['costs']['toDate'] ?? 0,
                    'currency' => $overview['success']['currency']['code'] ?? 'USD',
                ];
            }
        } catch (Exception $e) {
            // Return null if not found or unauthorized
            return null;
        }
        
        return null;
    }

    /**
     * Get all folios - uses file cache for the full list.
     * When a specific $query is given, calls Jira API directly for accurate results.
     * Cache duration: 2 hours.
     */
    public function searchAllFolios($query = '', $forceRefresh = false)
    {
        if (empty($this->jiraUrl)) return [];

        $tempoToken   = '88889431-ed02-4d4f-92bb-0b27457d7cc4';
        $cacheFile    = __DIR__ . '/../cache/jira_folios.cache.php';
        $cacheDuration = 7200; // 2 hours

        // ── Case 1: specific search query → call Jira directly (bypasses 1000 limit) ──
        if (!empty($query) && !$forceRefresh) {
            try {
                $url = $this->jiraUrl . '/rest/tempo-planning/1/portfolio/search?query=' . urlencode($query) . '&tempoApiToken=' . $tempoToken;
                $res = $this->callApi($url);
                $raw = [];
                if (isset($res['success']['folios']) && is_array($res['success']['folios'])) {
                    $raw = array_merge($raw, $res['success']['folios']);
                }
                if (isset($res['success']['portfolios']) && is_array($res['success']['portfolios'])) {
                    $raw = array_merge($raw, $res['success']['portfolios']);
                }
                if (empty($raw) && isset($res['success']['id'])) {
                    $raw[] = $res['success'];
                }
                
                $searchResults = $this->mapFolios($raw);

                // MERGE into existing cache so they appear in wishlist mode
                if (file_exists($cacheFile)) {
                    $json = str_replace('<?php exit; ?>', '', file_get_contents($cacheFile));
                    $currentCache = json_decode($json, true);
                    if (is_array($currentCache)) {
                        $merged = $currentCache;
                        $existingIds = array_column($currentCache, 'id');
                        foreach ($searchResults as $item) {
                            if (!in_array($item['id'], $existingIds)) {
                                $merged[] = $item;
                            }
                        }
                        file_put_contents($cacheFile, '<?php exit; ?>' . json_encode($merged));
                    }
                }

                return $searchResults;
            } catch (Exception $e) {
                return [];
            }
        }

        // ── Case 2: no query → load from cache (or fetch if cache miss/expired/forceRefresh) ──
        $allFolios = null;
        if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
            $json    = str_replace('<?php exit; ?>', '', file_get_contents($cacheFile));
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $allFolios = $decoded;
            }
        }

        if ($allFolios === null) {
            try {
                $url = $this->jiraUrl . '/rest/tempo-planning/1/portfolio/search?query=*&tempoApiToken=' . $tempoToken;
                $res = $this->callApi($url);
                $raw = [];
                if (isset($res['success']['folios']) && is_array($res['success']['folios'])) {
                    $raw = array_merge($raw, $res['success']['folios']);
                }
                if (isset($res['success']['portfolios']) && is_array($res['success']['portfolios'])) {
                    $raw = array_merge($raw, $res['success']['portfolios']);
                }
                if (empty($raw) && isset($res['success']['id'])) {
                    $raw[] = $res['success'];
                }

                $allFolios = $this->mapFolios($raw);

                if (!is_dir(dirname($cacheFile))) mkdir(dirname($cacheFile), 0777, true);
                file_put_contents($cacheFile, '<?php exit; ?>' . json_encode($allFolios));
            } catch (Exception $e) {
                return [];
            }
        }

        return $allFolios;
    }

    private function mapFolios(array $raw): array
    {
        return array_map(function($f) {
            // Extract JIRA key ONLY if it is in brackets [KEY]
            $key = '';
            if (preg_match('/^\[([a-zA-Z0-9_-]+)\]/', $f['name'] ?? '', $matches)) {
                $key = $matches[1];
            }
            return [
                'id'             => $f['id'],
                'key'            => $key ?: ('FOLIO-' . $f['id']),
                'name'           => $f['name'] ?? ('Folio ' . $f['id']),
                'avatar'         => $f['avatar'] ?? '',
                'projectTypeKey' => $f['folioType'] ?? 'FOLIO',
                'status'         => $f['status'] ?? '',
                'startDate'      => $f['startDate'] ?? '',
                'endDate'        => $f['endDate'] ?? '',
                'currency'       => $f['currency']['code'] ?? ($f['currency'] ?? 'USD'),
            ];
        }, $raw);
    }

    public function refreshFolioCache()
    {
        return $this->searchAllFolios('', true);
    }
    public function getFolioDetailedBreakdown($folioId, $tab = 'all') {
        $tempoToken = '88889431-ed02-4d4f-92bb-0b27457d7cc4';
        $result = [];

        // 1. Overview
        try {
            $ovUrl = $this->jiraUrl . '/rest/tempo-planning/1/folio/' . $folioId . '/overview?tempoApiToken=' . $tempoToken;
            $ov = $this->callApi($ovUrl);
            file_put_contents(__DIR__ . '/../api_debug_overview.json', json_encode($ov, JSON_PRETTY_PRINT));
            if (isset($ov['success'])) {
                $costs = $ov['success']['costs'] ?? [];
                $result['overview'] = [
                    'planned_cost' => $costs['totalPlanned'] ?? 0,
                    'actual_cost'  => $costs['toDate'] ?? 0,
                    'currency'     => $ov['success']['currency'] ?? ['code' => 'USD']
                ];
            }
        } catch (Exception $e) {
            $result['overview_error'] = $e->getMessage();
            file_put_contents(__DIR__ . '/../api_debug_overview_error.txt', $e->getMessage());
        }

        $cb = time(); // Cache buster

        // 2. Planned (Merge Budget + Positions)
        if ($tab === 'planned' || $tab === 'all') {
            $finalData = [];
            
            // Try Budget/Expenses
            try {
                $budgetUrl = $this->jiraUrl . '/rest/tempo-planning/1/budget/' . $folioId . '?_=' . $cb;
                $budget = $this->callApi($budgetUrl);
                file_put_contents(__DIR__ . '/../api_debug_budget.json', json_encode($budget, JSON_PRETTY_PRINT));
                if (isset($budget['success'])) {
                    $finalData = $budget;
                }
            } catch (Exception $e) { 
                file_put_contents(__DIR__ . '/../api_debug_budget_error.txt', $e->getMessage());
            }
            
            // Try Positions/Staff
            try {
                $posUrl = $this->jiraUrl . '/rest/tempo-planning/1/positions/' . $folioId . '?_=' . $cb;
                $positions = $this->callApi($posUrl);
                file_put_contents(__DIR__ . '/../api_debug_pos.json', json_encode($positions, JSON_PRETTY_PRINT));
                if (isset($positions['success'])) {
                    if (empty($finalData)) {
                        $finalData = $positions;
                    } else {
                        // Merge staff into budget data
                        if (isset($positions['success']['positionRoles'])) {
                            $finalData['success']['positionRoles'] = $positions['success']['positionRoles'];
                        }
                        if (isset($positions['success']['positionsCount'])) {
                            $finalData['success']['positionsCount'] = $positions['success']['positionsCount'];
                        }
                        $finalData['success']['totalCost'] = ($finalData['success']['totalCost'] ?? 0) + ($positions['success']['totalCost'] ?? 0);
                    }
                }
            } catch (Exception $e) { 
                file_put_contents(__DIR__ . '/../api_debug_pos_error.txt', $e->getMessage());
            }

            $result['planned'] = [
                'code' => 200,
                'data' => !empty($finalData) ? $finalData : ['success' => ['totalCost' => 0]]
            ];
        }

        // 3. Actual
        if ($tab === 'actual' || $tab === 'all') {
            try {
                $acUrl = $this->jiraUrl . '/rest/tempo-planning/1/actual/' . $folioId . '?_=' . $cb;
                $ac = $this->callApi($acUrl);
                $result['actual'] = [
                    'code' => 200,
                    'data' => $ac ?? []
                ];
            } catch (Exception $e) {
                $result['actual'] = ['code' => 404, 'error' => $e->getMessage()];
            }
        }

        return $result;
    }
}