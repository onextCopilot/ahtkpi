<?php
require_once __DIR__ . '/../config/config.php';

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
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

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
}
