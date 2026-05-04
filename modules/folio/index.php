<?php
if (function_exists('opcache_reset')) { opcache_reset(); }
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'] ?? 'user';
$full_name = $_SESSION['full_name'] ?? '';

// Fetch user wishlist
$wishlist = [];
$conn->query("CREATE TABLE IF NOT EXISTS folio_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    folio_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_folio (user_id, folio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$wish_stmt = $conn->prepare("SELECT folio_id FROM folio_wishlist WHERE user_id = ?");
if ($wish_stmt) {
    $wish_stmt->bind_param("i", $user_id);
    $wish_stmt->execute();
    $wish_res = $wish_stmt->get_result();
    while ($w_row = $wish_res->fetch_assoc()) {
        $wishlist[] = $w_row['folio_id'];
    }
    $wish_stmt->close();
}

// ── Fetch manual budget/plan from DB ──
$manualMap = [];
$mr = $conn->query("SELECT * FROM folio_project_manual");
if ($mr) while ($row = $mr->fetch_assoc()) {
    $manualMap[strtoupper($row['jira_key'])] = $row;
}

// ── Fetch folio budget cache (Plan/Actual Cost) from DB ──
$folioCache = [];
$fc = $conn->query("SELECT * FROM folio_budget_cache");
if ($fc) while ($fcRow = $fc->fetch_assoc()) {
    $folioCache[strtoupper($fcRow['jira_key'])] = $fcRow;
}

// Load Jira config
$configFile = __DIR__ . '/../../config/jira_config.json';
$jiraConfigured = false;
$jiraUrl = '';
if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true);
    $jiraUrl = rtrim($cfg['jira_url'] ?? '', '/');
    $jiraConfigured = !empty($jiraUrl) && !empty($cfg['jira_token']);
}

// Fetch projects from cache/API
$projects = [];
$errorMsg = '';
if ($jiraConfigured) {
    try {
        require_once __DIR__ . '/../../libs/JiraAPI.php';
        $jira = new JiraAPI();
        $projects = $jira->getProjects();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

// ── Hybrid budget data ──
// Auto-migrate tables
$conn->query("CREATE TABLE IF NOT EXISTS folio_budget_cache (
    jira_key VARCHAR(50) NOT NULL,
    folio_id VARCHAR(100),
    folio_name VARCHAR(255),
    budget DECIMAL(20,2) DEFAULT 0,
    plan DECIMAL(20,2) DEFAULT 0,
    actual DECIMAL(20,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'USD',
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (jira_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$checkCol = $conn->query("SHOW COLUMNS FROM folio_budget_cache LIKE 'folio_id'");
if ($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE folio_budget_cache ADD COLUMN folio_id VARCHAR(100) AFTER jira_key");
}

$conn->query("CREATE TABLE IF NOT EXISTS folio_project_manual (
    jira_key VARCHAR(50) NOT NULL,
    budget DECIMAL(20,2) DEFAULT 0,
    plan_hours DECIMAL(10,2) DEFAULT 0,
    plan_cost DECIMAL(20,2) DEFAULT 0,
    cost_rate DECIMAL(10,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'USD',
    note TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (jira_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS folio_tempo_cache (
    jira_key VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    hours DECIMAL(10,2) DEFAULT 0,
    seconds INT DEFAULT 0,
    entry_count INT DEFAULT 0,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (jira_key, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Auto-add columns if they don't exist
try {
    $conn->query("ALTER TABLE folio_project_manual ADD COLUMN plan_cost DECIMAL(20,2) DEFAULT 0");
} catch (Exception $e) {}
try {
    $conn->query("ALTER TABLE folio_project_manual ADD COLUMN cost_rate DECIMAL(10,2) DEFAULT 0");
} catch (Exception $e) {}
try {
    $conn->query("ALTER TABLE folio_project_manual ADD COLUMN folio_id VARCHAR(100) AFTER jira_key");
} catch (Exception $e) {}

$cur_year = (int)date('Y');
$cur_q    = (int)ceil(date('n') / 3);

// Year date range for Tempo worklogs
$date_from = $cur_year . '-01-01';
$date_to   = $cur_year . '-12-31';

// ── Handle manual budget save (admin) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folio_save']) && $role === 'admin') {
    header('Content-Type: application/json');
    $jk  = $conn->real_escape_string($_POST['jira_key'] ?? '');
    $fid = $conn->real_escape_string($_POST['folio_id'] ?? '');
    $bgt = floatval($_POST['budget'] ?? 0);
    $plc = floatval($_POST['plan_cost'] ?? 0);
    $crt = floatval($_POST['cost_rate'] ?? 0);
    $cur = $conn->real_escape_string($_POST['currency'] ?? 'USD');
    $nt  = $conn->real_escape_string($_POST['note'] ?? '');
    $conn->query("INSERT INTO folio_project_manual (jira_key,folio_id,budget,plan_cost,cost_rate,currency,note)
        VALUES ('$jk','$fid',$bgt,$plc,$crt,'$cur','$nt')
        ON DUPLICATE KEY UPDATE folio_id='$fid',budget=$bgt,plan_cost=$plc,cost_rate=$crt,currency='$cur',note='$nt'");
    echo json_encode(['success' => true]);
    exit();
}

// ── Debug endpoint ──
if (isset($_GET['debug']) && $_GET['debug'] === 'worklogs' && $role === 'admin' && $jiraConfigured) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../../libs/JiraAPI.php';
    $dbgJira = new JiraAPI();
    $dbgProj = $_GET['project'] ?? 'MAJEF2601ECP';
    $res = $dbgJira->getTempoActualHoursByProject([$dbgProj], $date_from, $date_to);
    echo json_encode(['project' => $dbgProj, 'date_from' => $date_from, 'date_to' => $date_to, 'result' => $res], JSON_PRETTY_PRINT);
    exit();
}

// ── Search / filter ──
$search       = trim($_GET['search'] ?? '');
$typeFilter   = trim($_GET['type'] ?? '');
$wishlistOnly = (isset($_GET['wishlist']) && $_GET['wishlist'] == '1');
$forceRefresh = isset($_GET['refresh_folio']) && $role === 'admin';

// ── Fetch folios ──
$projects = [];
$errorMsg = '';
if ($jiraConfigured) {
    try {
        require_once __DIR__ . '/../../libs/JiraAPI.php';
        $jira     = new JiraAPI();
        
        if ($wishlistOnly) {
            // If wishlist only, load ALL and filter by user wishlist IDs
            $projects = $jira->searchAllFolios('', $forceRefresh);
            $projects = array_filter($projects, function($p) use ($wishlist, $manualMap, $folioCache, $search) {
                if (empty($p['key'])) return false;

                // If search query is provided, filter by it first
                if (!empty($search)) {
                    $searchLower = mb_strtolower($search);
                    $pName = mb_strtolower($p['name'] ?? '');
                    $pKey  = mb_strtolower($p['key'] ?? '');
                    if (strpos($pName, $searchLower) === false && strpos($pKey, $searchLower) === false) {
                        return false;
                    }
                }

                $key = strtoupper($p['key']);
                
                // Get potential folio_id for this project (maps are now uppercase)
                $mn = $manualMap[$key] ?? null;
                $fc = $folioCache[$key] ?? null;
                $fid = $mn['folio_id'] ?? ($fc['folio_id'] ?? null);
                
                // Check if key or folio_id is in user wishlist (case-insensitive)
                foreach ($wishlist as $w) {
                    if (strcasecmp($w, $key) === 0 || 
                        ($fid && strcasecmp($w, $fid) === 0) || 
                        strcasecmp($w, $p['id']) === 0 || 
                        strcasecmp($w, $p['name']) === 0) {
                        return true;
                    }
                }
                return false;
            });
        } else {
            // 1. Try to search directly via Jira API if search query exists (most accurate)
            if ($search) {
                $projects = $jira->searchAllFolios($search, $forceRefresh);
            }
            
            // 2. If nothing found or no search, load the full list (from cache)
            if (empty($projects)) {
                $allProjects = $jira->searchAllFolios('', $forceRefresh);
                
                if ($search) {
                    $searchLower = mb_strtolower($search);
                    $projects = array_filter($allProjects, function($p) use ($searchLower) {
                        if (empty($p['key'])) return false;
                        $name = mb_strtolower($p['name'] ?? '');
                        $key  = mb_strtolower($p['key']);
                        return (strpos($name, $searchLower) !== false || strpos($key, $searchLower) !== false);
                    });
                } else {
                    $projects = array_filter($allProjects, function($p) {
                        return !empty($p['key']);
                    });
                }
            }
        }
        
        // Sort by ID descending (newest first)
        usort($projects, function($a, $b) {
            return ($b['id'] ?? 0) - ($a['id'] ?? 0);
        });
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

// Actual hours are loaded asynchronously via AJAX (/api/get_tempo_actual.php)
// to avoid blocking the page load (30s timeout with many projects)
$actualMap  = [];
$tempoError = '';


$filtered = array_filter($projects, function($p) use ($typeFilter) {
    // Search is already handled by the API, so we only need to filter by type locally
    $matchType = empty($typeFilter) || ($p['projectTypeKey'] ?? '') === $typeFilter;
    return $matchType;
});
$filtered = array_values($filtered);

// Collect unique project types
$types = array_unique(array_filter(array_column($projects, 'projectTypeKey')));
sort($types);

$page_title    = 'Folio – Jira Projects';
$page_subtitle = 'Tempo Actual · Budget & Plan';

function fmt_money($v, $cur = 'USD') {
    if (!$v) return '<span style="color:#475569">—</span>';
    return ($cur === 'VND') ? number_format($v,0,',','.') . ' đ' : '$' . number_format($v,2,'.',',');
}
function fmt_hours($h) {
    if ($h === null || $h === 0 || $h === 0.0) return '<span style="color:#475569">—</span>';
    return number_format((float)$h, 1) . 'h';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folio – Jira Projects | AHT KPI</title>
    <meta name="description" content="Xem toàn bộ Jira projects dưới dạng portfolio đẹp mắt.">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── Design tokens ── */
        :root {
            --folio-bg:        #0f1117;
            --folio-surface:   #1a1d27;
            --folio-card:      #1e2130;
            --folio-border:    rgba(255,255,255,0.07);
            --folio-accent:    #6366f1;
            --folio-accent2:   #818cf8;
            --folio-green:     #22c55e;
            --folio-orange:    #f97316;
            --folio-red:       #ef4444;
            --folio-text:      #f1f5f9;
            --folio-muted:     #94a3b8;
            --folio-radius:    16px;
            --folio-radius-sm: 10px;
            --transition:      0.25s cubic-bezier(.4,0,.2,1);
        }

        body { background: var(--folio-bg) !important; }
        #folioSidebar {
        position: fixed;
        top: 0;
        right: -600px;
        width: 600px;
        height: 100%;
        background: #0f172a;
        box-shadow: -4px 0 15px rgba(0,0,0,0.5);
        z-index: 1051;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
        color: #f1f5f9;
        font-family: 'Inter', sans-serif;
    }
    #folioSidebar.open { right: 0; }
    
    .detail-avatar { display: none !important; }
        .main-content {
            flex: 1;
            padding: 32px 36px;
            background: var(--folio-bg);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        /* ── Header strip ── */
        .folio-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
        }
        .folio-hero-left h2 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 4px;
        }
        .folio-hero-left p {
            font-size: 0.875rem;
            color: var(--folio-muted);
            margin: 0;
        }
        .folio-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .stat-chip {
            background: var(--folio-surface);
            border: 1px solid var(--folio-border);
            border-radius: 12px;
            padding: 10px 20px;
            text-align: center;
            min-width: 90px;
        }
        .stat-chip .num {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--folio-accent2);
            display: block;
        }
        .stat-chip .lbl {
            font-size: 0.7rem;
            color: var(--folio-muted);
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ── Toolbar ── */
        .folio-toolbar {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            position: sticky;
            top: 72px;
            z-index: 100;
            background: var(--folio-bg);
            padding: 12px 0;
        }
        .search-box {
            flex: 1;
            min-width: 220px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            background: var(--folio-surface);
            border: 1px solid var(--folio-border);
            border-radius: var(--folio-radius-sm);
            padding: 10px 16px 10px 40px;
            color: var(--folio-text);
            font-size: 0.875rem;
            outline: none;
            transition: border var(--transition);
            box-sizing: border-box;
        }
        .search-box input::placeholder { color: var(--folio-muted); }
        .search-box input:focus { border-color: var(--folio-accent); }
        .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--folio-muted);
            font-size: 0.8rem;
        }
        .search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--folio-muted);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s;
        }
        .search-clear:hover { color: var(--folio-text); }
        .filter-select {
            background: var(--folio-surface);
            border: 1px solid var(--folio-border);
            border-radius: var(--folio-radius-sm);
            padding: 10px 16px;
            color: var(--folio-text);
            font-size: 0.875rem;
            outline: none;
            cursor: pointer;
            transition: border var(--transition);
        }
        .filter-select:focus { border-color: var(--folio-accent); }

        .view-toggle { display: flex; gap: 4px; }
        .view-btn {
            background: var(--folio-surface);
            border: 1px solid var(--folio-border);
            border-radius: 8px;
            padding: 8px 10px;
            color: var(--folio-muted);
            cursor: pointer;
            transition: all var(--transition);
            font-size: 0.85rem;
            line-height: 1;
        }
        .view-btn.active, .view-btn:hover {
            background: var(--folio-accent);
            border-color: var(--folio-accent);
            color: #fff;
        }

        .count-badge {
            background: rgba(99,102,241,0.15);
            color: var(--folio-accent2);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Grid view ── */
        .project-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding-top: 10px;
        }
        .auto-badge {
            font-size: 0.6rem;
            background: var(--folio-accent2);
            color: #fff;
            padding: 1px 6px;
            border-radius: 4px;
            margin-left: 6px;
            font-weight: 600;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 4px rgba(99,102,241,0.2);
        }
        .project-card {
            background: var(--folio-card);
            border: 1px solid var(--folio-border);
            border-radius: var(--folio-radius);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: all var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }
        .project-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--folio-accent), #c084fc);
            opacity: 0;
            transition: opacity var(--transition);
        }
        
        /* Wishlist Styling */
        .wishlist-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.05);
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--folio-muted);
            font-size: 0.8rem;
        }
        .wishlist-btn:hover {
            background: rgba(255,255,255,0.1);
            transform: scale(1.1);
        }
        .wishlist-btn.active {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
        }
        .wishlist-filter-btn {
            background: var(--folio-surface);
            border: 1px solid var(--folio-border);
            color: var(--folio-text);
            padding: 0 16px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .wishlist-filter-btn:hover {
            border-color: var(--folio-accent);
        }
        .wishlist-filter-btn.active {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #ef4444;
        }

        .project-card:hover {
            border-color: rgba(99,102,241,0.4);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .project-card:hover::before { opacity: 1; }

        .card-header { display: flex; align-items: flex-start; gap: 14px; }
        .project-avatar {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            background: var(--folio-surface);
        }
        .project-avatar-fallback {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--folio-accent), #c084fc);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }
        .card-meta { flex: 1; min-width: 0; }
        .card-meta h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--folio-text);
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .card-key {
            font-size: 0.72rem;
            font-weight: 600;
            background: rgba(99,102,241,0.15);
            color: var(--folio-accent2);
            border-radius: 6px;
            padding: 2px 8px;
            display: inline-block;
        }
        .card-type {
            font-size: 0.7rem;
            color: var(--folio-muted);
            margin-top: 4px;
            text-transform: capitalize;
        }

        .card-open-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 0.7rem;
            color: var(--folio-accent2);
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--folio-border);
            padding-top: 14px;
            gap: 6px;
            flex-wrap: wrap;
        }
        .view-detail-btn {
            font-size: 0.75rem;
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.25);
            border-radius: 8px;
            padding: 4px 10px;
            color: var(--folio-accent2);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
        }
        .view-detail-btn:hover { background: rgba(99,102,241,0.22); }
        .open-link {\n            font-size: 0.78rem;\n            color: var(--folio-accent2);\n            text-decoration: none;\n            display: flex;\n            align-items: center;\n            gap: 4px;\n            font-weight: 500;\n            transition: color var(--transition);\n        }
        .open-link:hover { color: #fff; }

        /* ── Folio Detail Sidebar ── */
        .folio-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        .folio-sidebar-overlay.open { display: block; }
        .folio-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 480px;
            max-width: 95vw;
            height: 100vh;
            background: #13152b;
            border-left: 1px solid var(--folio-border);
            z-index: 1001;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            box-shadow: -4px 0 32px rgba(0,0,0,0.4);
        }
        .folio-sidebar.open { transform: translateX(0); }
        .sidebar-head {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--folio-border);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }
        .sidebar-title { font-size: 1.05rem; font-weight: 700; color: var(--folio-text); }
        .sidebar-subtitle { font-size: 0.75rem; color: var(--folio-muted); margin-top: 3px; }
        .sidebar-close {
            background: none;
            border: none;
            color: var(--folio-muted);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 4px;
            border-radius: 6px;
            flex-shrink: 0;
        }
        .sidebar-close:hover { color: var(--folio-text); background: rgba(255,255,255,0.06); }
        .sidebar-tabs {
            display: flex;
            border-bottom: 1px solid var(--folio-border);
            flex-shrink: 0;
        }
        .sidebar-tab {
            flex: 1;
            padding: 11px 0;
            text-align: center;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--folio-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .sidebar-tab.active {
            color: var(--folio-accent2);
            border-color: var(--folio-accent2);
            background: rgba(99,102,241,0.06);
        }
        .sidebar-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .sidebar-loading {
            text-align: center;
            padding: 48px 0;
            color: var(--folio-muted);
            font-size: 0.85rem;
        }
        .sidebar-loading i { font-size: 1.5rem; display: block; margin-bottom: 12px; opacity: 0.5; }
        .detail-section { margin-bottom: 24px; }
        .detail-section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 12px;
            padding: 0 10px;
        }

        /* Folio Node CSS */
        .folio-node {
            border-bottom: 1px solid rgba(255,255,255,0.03);
            transition: background 0.2s;
        }
        .node-main {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
        }
        .node-main:hover { background: rgba(255,255,255,0.04); }
        .node-toggle {
            width: 18px;
            color: #64748b;
            font-size: 10px;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
        }
        .node-name { flex: 1; color: #e2e8f0; font-weight: 500; }
        .node-spacer {
            flex: 0 1 40px;
            border-bottom: 1px dotted rgba(255,255,255,0.08);
            margin: 0 12px;
        }
        .node-value { font-weight: 600; color: #f8fafc; font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; }
        
        /* Level Indentation */
        .level-0 .node-name { font-weight: 700; color: #fff; }
        .level-1 .node-main { padding-left: 28px; }
        .level-2 .node-main { padding-left: 44px; }
        .level-3 .node-main { padding-left: 60px; }
        .level-4 .node-main { padding-left: 76px; }
        .level-5 .node-main { padding-left: 92px; }

        /* Collapsible State */
        .folio-node.collapsed .node-toggle { transform: rotate(-90deg); }
        .folio-node.collapsed + .node-children { display: none; }
        
        .sidebar-summary {
            background: #1a1d3d;
            padding: 16px 24px;
            border-top: 1px solid var(--folio-border);
            display: none;
            justify-content: space-between;
            align-items: center;
        }
        .summary-label { font-size: 0.8rem; font-weight: 600; color: var(--folio-muted); }
        .summary-value { font-size: 1.1rem; font-weight: 800; color: #fff; }
        .detail-group-body { display: none; }
        .detail-group-body.open { display: block; }
        .detail-row {
            display: grid;
            grid-template-columns: 1fr 70px 65px 80px;
            gap: 4px;
            padding: 5px 10px 5px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            align-items: center;
            font-size: 0.75rem;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-person { color: var(--folio-text); }
        .detail-rate { color: var(--folio-muted); text-align: right; font-size: 0.7rem; }
        .detail-hours { color: var(--folio-accent); text-align: right; }
        .detail-amount { color: #a5b4fc; text-align: right; font-weight: 600; }
        .sidebar-summary-bar {
            padding: 14px 20px;
            border-top: 1px solid var(--folio-border);
            display: flex;
            gap: 16px;
            flex-shrink: 0;
            background: rgba(0,0,0,0.15);
        }
        .summary-item { flex: 1; }
        .summary-label { font-size: 0.68rem; color: var(--folio-muted); margin-bottom: 3px; }
        .summary-value { font-size: 0.88rem; font-weight: 700; color: var(--folio-text); }
        .jira-link {
            font-size: 0.75rem;
            color: var(--folio-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color var(--transition);
        }
        .jira-link:hover { color: var(--folio-text); }

        /* ── List view ── */
        .project-list { display: flex; flex-direction: column; gap: 10px; }
        .list-item {
            background: var(--folio-card);
            border: 1px solid var(--folio-border);
            border-radius: var(--folio-radius-sm);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            transition: all var(--transition);
        }
        .list-item:hover {
            border-color: rgba(99,102,241,0.4);
            background: rgba(30,33,48,0.9);
            transform: translateX(4px);
        }
        .list-avatar {
            width: 40px; height: 40px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .list-avatar-fallback {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--folio-accent), #c084fc);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }
        .list-name {
            flex: 1;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--folio-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .list-key {
            font-size: 0.72rem;
            font-weight: 600;
            background: rgba(99,102,241,0.12);
            color: var(--folio-accent2);
            border-radius: 6px;
            padding: 2px 8px;
            flex-shrink: 0;
        }
        .list-type {
            font-size: 0.75rem;
            color: var(--folio-muted);
            flex-shrink: 0;
            width: 90px;
            text-align: right;
            text-transform: capitalize;
        }
        .list-ext {
            font-size: 0.75rem;
            color: var(--folio-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        /* ── Empty / error state ── */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--folio-muted);
        }
        .empty-state .icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
            opacity: .4;
        }
        .empty-state h3 { color: var(--folio-text); font-size: 1.2rem; margin: 0 0 8px; }
        .empty-state p  { font-size: 0.875rem; margin: 0; }

        /* ── Loading skeleton ── */
        @keyframes shimmer {
            0%   { background-position: -600px 0; }
            100% { background-position: 600px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #1e2130 25%, #252839 50%, #1e2130 75%);
            background-size: 1200px 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
        }

        /* ── Budget section ── */
        .card-budget {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .budget-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
        }
        .budget-label {
            color: var(--folio-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            min-width: 56px;
        }
        .budget-value {
            font-weight: 700;
            color: var(--folio-text);
            font-size: 0.82rem;
        }
        .budget-bar-wrap {
            flex: 1;
            height: 4px;
            background: rgba(255,255,255,0.08);
            border-radius: 4px;
            margin: 0 10px;
            overflow: hidden;
        }
        .budget-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }
        .bar-plan  { background: linear-gradient(90deg,#6366f1,#818cf8); }
        .bar-actual{ background: linear-gradient(90deg,#22c55e,#4ade80); }
        .bar-over  { background: linear-gradient(90deg,#ef4444,#f87171); }
        .no-budget-hint {
            font-size: 0.72rem;
            color: var(--folio-muted);
            text-align: center;
            opacity: .6;
            padding: 4px 0;
        }
        /* edit budget modal */
        .budget-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        .budget-modal-overlay.open { display: flex; }
        .budget-modal {
            background: #1e2130;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 28px;
            width: 400px;
            max-width: 95vw;
        }
        .budget-modal h4 {
            margin: 0 0 20px;
            color: var(--folio-text);
            font-size: 1rem;
            font-weight: 700;
        }
        .bm-field { margin-bottom: 14px; }
        .bm-field label {
            display: block;
            font-size: 0.78rem;
            color: var(--folio-muted);
            margin-bottom: 4px;
        }
        .bm-field input, .bm-field select {
            width: 100%; box-sizing: border-box;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--folio-text);
            font-size: 0.875rem;
            outline: none;
        }
        .bm-field input:focus { border-color: var(--folio-accent); }
        .bm-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .bm-btn {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .bm-btn-save { background: var(--folio-accent); color: #fff; }
        .bm-btn-cancel { background: rgba(255,255,255,0.07); color: var(--folio-muted); }

        /* ── Topbar text override for dark bg ── */
        .top-bar { background: var(--folio-bg) !important; border-bottom: 1px solid var(--folio-border) !important; }
        .page-title h1 { color: var(--folio-text) !important; }
        .page-title p  { color: var(--folio-muted) !important; }

        /* Pagination */
        .folio-pager {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
            margin-bottom: 16px;
        }
        .folio-pager button {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--folio-muted);
            width: 32px; height: 32px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
        }
        .folio-pager button:hover { background: rgba(255,255,255,0.1); color: var(--folio-text); }
        .folio-pager button.active {
            background: var(--folio-accent);
            color: #fff;
            border-color: var(--folio-accent);
        }
        .folio-pager span { color: var(--folio-muted); padding: 0 4px; }

        /* Responsive */
        @media (max-width: 640px) {
            .main-content { padding: 16px; }
            .project-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/../../modules/includes/topbar.php'; ?>

        <!-- Hero -->
        <div class="folio-hero">
            <div class="folio-hero-left">
                <h2><i class="fa-solid fa-layer-group" style="margin-right:10px;font-size:1.4rem;"></i>Folio</h2>
                <p>Portfolio tất cả Jira Projects · <?php echo htmlspecialchars($jiraUrl ?: 'Chưa cấu hình'); ?></p>
            </div>
            <div class="folio-stats">
                <div class="stat-chip">
                    <span class="num"><?php echo count($projects); ?></span>
                    <span class="lbl">Projects</span>
                </div>
                <?php
                $typeCounts = array_count_values(array_filter(array_column($projects, 'projectTypeKey')));
                foreach ($typeCounts as $t => $c):
                ?>
                <div class="stat-chip">
                    <span class="num"><?php echo $c; ?></span>
                    <span class="lbl"><?php echo htmlspecialchars(ucfirst($t)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$jiraConfigured): ?>
        <div class="empty-state">
            <div class="icon"><i class="fa-brands fa-jira"></i></div>
            <h3>Chưa cấu hình Jira</h3>
            <p>Vui lòng vào <a href="/settings/jira" style="color:var(--folio-accent2)">Settings → Jira</a> để thiết lập kết nối.</p>
        </div>
        <?php elseif ($errorMsg): ?>
        <div class="empty-state">
            <div class="icon">⚠️</div>
            <h3>Không thể tải dữ liệu</h3>
            <p><?php echo htmlspecialchars($errorMsg); ?></p>
        </div>
        <?php else: ?>

        <!-- Toolbar -->
        <div class="folio-toolbar">
            <form id="folioSearchForm" method="GET" action="" style="display:contents">
            <div class="search-box">
                <i class="fa fa-search search-icon"></i>
                <input id="searchInput" name="search" type="text"
                       placeholder="Tìm dự án..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       oninput="handleSearchInput()">
                <?php if(!empty($search)): ?>
                <a href="javascript:void(0)" onclick="clearSearchAjax()" class="search-clear" title="Xoá tìm kiếm"><i class="fa fa-times"></i></a>
                <?php endif; ?>
            </div>
            </form>

            <select id="typeFilter" class="filter-select" onchange="handleTypeFilter()">
                <option value="">Tất cả loại</option>
                <?php foreach ($types as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>"
                    <?php echo $typeFilter === $t ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst($t)); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <button id="wishlistFilterBtn" class="wishlist-filter-btn <?php echo $wishlistOnly ? 'active' : ''; ?>" onclick="toggleWishlistFilter()">
                <i class="fa-solid fa-heart"></i> My Wishlist
            </button>

            <div class="view-toggle">
                <button class="view-btn active" id="btnGrid" onclick="setView('grid')" title="Grid">
                    <i class="fa fa-grid-2"></i>
                </button>
                <button class="view-btn" id="btnList" onclick="setView('list')" title="List">
                    <i class="fa fa-list"></i>
                </button>
            </div>

            <span class="count-badge" id="countBadge">0 projects</span>

            <?php if ($role === 'admin'): ?>
            <a href="?refresh_folio=1"
               style="background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);border-radius:8px;padding:6px 12px;color:var(--folio-accent2);cursor:pointer;font-size:0.75rem;display:flex;align-items:center;gap:6px;flex-shrink:0;text-decoration:none;">
                <i class="fa fa-sync"></i> Refresh Cache
            </a>
            <a href="?debug=folios" target="_blank"
               style="font-size:0.75rem;color:var(--folio-muted);text-decoration:none;display:flex;align-items:center;gap:4px;"
               title="Xem raw Tempo Folios JSON">
                <i class="fa fa-bug"></i> Debug
            </a>
            <?php endif; ?>
        </div>

        <?php if ($tempoError && $role === 'admin'): ?>
        <div style="background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:0.82rem;color:#fbbf24;display:flex;align-items:center;gap:8px;">
            <i class="fa fa-triangle-exclamation"></i>
            <?php echo htmlspecialchars($tempoError); ?>
            <?php if (!$tempoLoaded): ?> (Hiển thị dữ liệu cache cũ nếu có.)<?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div id="folioContentArea">

        <!-- Grid view -->
        <div id="viewGrid" class="project-grid">
            <?php foreach ($filtered as $p):
                if (empty($p['key'])) continue;
                $key    = htmlspecialchars($p['key']);
                $name   = htmlspecialchars($p['name']);
                $type   = htmlspecialchars($p['projectTypeKey'] ?? 'software');
                $avatar = htmlspecialchars($p['avatar'] ?? '');
                $jiraProjectUrl = $jiraUrl . '/browse/' . $p['key'];
                $initial = strtoupper(substr($p['name'], 0, 1));
            ?>
            <?php
            // ─ Tempo actual hours ─
            $actData   = $actualMap[$p['key']] ?? null;
            $act_hours = floatval($actData['hours'] ?? 0);
            $act_count = intval($actData['entry_count'] ?? 0);

            // ─ Manual budget/plan ─
            $mn        = $manualMap[$p['key']] ?? null;
            $b_budget  = floatval($mn['budget'] ?? 0);
            $b_plan    = floatval($mn['plan_cost'] ?? 0);
            $b_crate   = floatval($mn['cost_rate'] ?? 0);
            $b_cur     = $mn['currency'] ?? 'USD';

            // ── Load Plan/Actual Cost from DB cache (no AJAX) ──
            $fc_row    = $folioCache[$p['key']] ?? null;
            $fc_plan   = $fc_row ? floatval($fc_row['plan']) : $b_plan;
            $fc_actual = $fc_row ? floatval($fc_row['actual']) : 0;
            $fc_cur    = $fc_row['currency'] ?? $b_cur;
            $fc_cached = $fc_row ? date('d/m/Y H:i', strtotime($fc_row['synced_at'] ?? 'now')) : null;

            // Progress bars
            $plan_pct   = $b_budget > 0 ? min(100, round($fc_plan / $b_budget * 100)) : 0;
            $actual_pct = $fc_plan > 0  ? min(100, round($fc_actual / $fc_plan * 100)) : 0;
            $over_plan  = $fc_plan > 0 && $fc_actual > $fc_plan;
            $fc_folio_id = $mn['folio_id'] ?? ($fc_row['folio_id'] ?? 0);
            
            $wish_id = $fc_folio_id && $fc_folio_id != '0' ? $fc_folio_id : $p['id'];
            $matched_id = $wish_id; 
            $is_wishlisted = false;
            foreach ($wishlist as $w) {
                if (strcasecmp($w, $key) === 0 || 
                    ($fc_folio_id && strcasecmp($w, $fc_folio_id) === 0) || 
                    strcasecmp($w, $p['id']) === 0 || 
                    strcasecmp($w, $p['name']) === 0) {
                    $is_wishlisted = true;
                    $matched_id = $w; 
                    break;
                }
            }
            ?>
            <div class="project-card"
                 data-name="<?php echo strtolower($p['name']); ?>"
                 data-project-name="<?php echo htmlspecialchars($p['name']); ?>"
                 data-key="<?php echo strtolower($p['key']); ?>"
                 data-type="<?php echo $p['projectTypeKey'] ?? ''; ?>"
                 data-folio-id="<?php echo $matched_id; ?>"
                 data-is-wishlisted="<?php echo $is_wishlisted ? 'true' : 'false'; ?>">
                
                <div class="card-header">
                    <?php if ($avatar): ?>
                        <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>" class="project-avatar"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="project-avatar-fallback" style="display:none"><?php echo $initial; ?></div>
                    <?php else: ?>
                        <div class="project-avatar-fallback"><?php echo $initial; ?></div>
                    <?php endif; ?>
                    <div class="card-meta">
                        <h3 title="<?php echo $name; ?>"><?php echo $name; ?></h3>
                        <span class="card-key"><?php echo $key; ?></span>
                        <div class="card-type"><i class="fa fa-tag" style="margin-right:4px;"></i><?php echo $type; ?></div>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;align-items:center;">
                        <?php if ($role === 'admin'): ?>
                        <button onclick="event.stopPropagation(); reloadSingleFolio(this, '<?php echo addslashes($p['key']); ?>')" title="Refresh Data"
                            style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:8px;padding:4px 8px;color:var(--folio-green);cursor:pointer;font-size:0.7rem;">
                            <i class="fa fa-sync"></i>
                        </button>
                        <?php endif; ?>

                        <div class="wishlist-btn <?php echo $is_wishlisted ? 'active' : ''; ?>" 
                             onclick="toggleWishlist(this, '<?php echo $matched_id; ?>')" 
                             title="Add to Wishlist">
                            <i class="fa-<?php echo $is_wishlisted ? 'solid' : 'regular'; ?> fa-heart"></i>
                        </div>
                    </div>
                </div>



                <!-- Budget / Plan / Actual -->
                <div class="card-budget">
                    <!-- Budget (manual) -->
                    <div class="budget-row">
                        <span class="budget-label"><i class="fa fa-wallet"></i> Budget</span>
                        <div class="budget-bar-wrap"><div class="budget-bar" style="width:100%;background:rgba(99,102,241,0.2);"></div></div>
                        <span class="budget-value"><?php echo $b_budget ? fmt_money($b_budget, $b_cur) : '<span style="color:#475569;font-size:0.72rem;">chưa nhập</span>'; ?></span>
                    </div>
                    <!-- Plan cost -->
                    <div class="budget-row">
                        <span class="budget-label"><i class="fa fa-chart-line"></i> Plan Cost</span>
                        <div class="budget-bar-wrap"><div class="budget-bar bar-plan" style="width:<?php echo $plan_pct; ?>%;"></div></div>
                        <span class="budget-value plan-val">
                            <?php
                            if ($fc_row) {
                                echo fmt_money($fc_plan, $fc_cur);
                                echo ' <span title="Auto synced from Jira Folio: ' . htmlspecialchars($fc_cached) . '" class="auto-badge">AUTO</span>';
                            } else {
                                echo fmt_money($b_plan, $b_cur);
                            }
                            ?>
                        </span>
                        <span class="plan-data-holder" data-plan="<?php echo $fc_plan; ?>" data-rate="<?php echo $b_crate; ?>" data-cur="<?php echo htmlspecialchars($fc_cur); ?>" style="display:none;"></span>
                    </div>
                    <!-- Actual cost — from DB cache, no AJAX -->
                    <div class="budget-row">
                        <span class="budget-label"><i class="fa fa-circle-check"></i> Actual Cost</span>
                        <div class="budget-bar-wrap"><div class="budget-bar <?php echo $over_plan ? 'bar-over' : 'bar-actual'; ?> actual-bar" style="width:<?php echo $actual_pct; ?>%;"></div></div>
                        <span class="budget-value actual-val" style="color:<?php echo $over_plan ? '#ef4444' : ($fc_actual > 0 ? '#22c55e' : 'var(--folio-muted)'); ?>;">
                            <?php
                            if ($fc_actual > 0) {
                                echo fmt_money($fc_actual, $fc_cur);
                            } else {
                                echo '<span style="color:#475569;font-size:0.72rem;">chưa có</span>';
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($over_plan): ?>
                    <div class="over-plan-warn" style="font-size:0.7rem;color:#ef4444;text-align:right;">
                        <?php
                        $pct = round(($fc_actual - $fc_plan) / $fc_plan * 100);
                        echo "⚠ Over plan {$pct}%";
                        ?>
                    </div>
                    <?php else: ?>
                    <div class="over-plan-warn" style="display:none;"></div>
                    <?php endif; ?>
                </div>


                <div class="card-footer">
                    <button class="view-detail-btn" onclick="event.stopPropagation(); openFolioDetail(this)" title="View Detail">
                        <i class="fa fa-chart-bar"></i> View Detail
                    </button>

                    <span style="font-size:0.72rem;color:var(--folio-muted);">
                        Q<?php echo $cur_q; ?>/<?php echo $cur_year; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($filtered)): ?>
            <div class="empty-state" style="grid-column:1/-1">
                <div class="icon"><i class="fa fa-magnifying-glass"></i></div>
                <h3>Không tìm thấy project</h3>
                <p>Thử thay đổi từ khoá hoặc bộ lọc.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- List view (hidden by default) -->
        <div id="viewList" class="project-list" style="display:none">
            <?php foreach ($filtered as $p):
                if (empty($p['key'])) continue;
                $key    = htmlspecialchars($p['key']);
                $name   = htmlspecialchars($p['name']);
                $type   = htmlspecialchars($p['projectTypeKey'] ?? 'software');
                $avatar = htmlspecialchars($p['avatar'] ?? '');
                $jiraProjectUrl = $jiraUrl . '/browse/' . $p['key'];
                $initial = strtoupper(substr($p['name'], 0, 1));
            ?>
            <?php 
                // Need to compute fc_folio_id and wish_id for list view too
                $mn_list = $manualMap[$p['key']] ?? null;
                $fc_row_list = $folioCache[$p['key']] ?? null;
                $fc_fid_list = $mn_list['folio_id'] ?? ($fc_row_list['folio_id'] ?? 0);
                $wish_id_list = $fc_fid_list && $fc_fid_list != '0' ? $fc_fid_list : $p['id'];
                $matched_id_list = $wish_id_list;
                $is_wish_list = false;
                foreach ($wishlist as $w) {
                    if (strcasecmp($w, $key) === 0 || 
                        ($fc_fid_list && strcasecmp($w, $fc_fid_list) === 0) || 
                        strcasecmp($w, $p['id']) === 0 || 
                        strcasecmp($w, $p['name']) === 0) {
                        $is_wish_list = true;
                        $matched_id_list = $w;
                        break;
                    }
                }
            ?>
            <a class="list-item"
               href="<?php echo htmlspecialchars($jiraProjectUrl); ?>"
               target="_blank"
               data-name="<?php echo strtolower($p['name']); ?>"
               data-key="<?php echo strtolower($p['key']); ?>"
               data-type="<?php echo $p['projectTypeKey'] ?? ''; ?>"
               data-folio-id="<?php echo $matched_id_list; ?>"
               data-is-wishlisted="<?php echo $is_wish_list ? 'true' : 'false'; ?>">
                <?php if ($avatar): ?>
                    <img src="<?php echo $avatar; ?>" alt="<?php echo $name; ?>" class="list-avatar"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="list-avatar-fallback" style="display:none"><?php echo $initial; ?></div>
                <?php else: ?>
                    <div class="list-avatar-fallback"><?php echo $initial; ?></div>
                <?php endif; ?>
                <span class="list-name"><?php echo $name; ?></span>
                <span class="list-key"><?php echo $key; ?></span>
                <span class="list-type"><?php echo $type; ?></span>
                
                <div class="wishlist-btn <?php echo $is_wish_list ? 'active' : ''; ?>" 
                     onclick="event.preventDefault(); toggleWishlist(this, '<?php echo $matched_id_list; ?>')" 
                     style="margin-left:auto; margin-right: 15px;">
                    <i class="fa-<?php echo $is_wish_list ? 'solid' : 'regular'; ?> fa-heart"></i>
                </div>

                <span class="list-ext">
                    <i class="fa-brands fa-jira"></i>
                    <i class="fa fa-arrow-up-right-from-square"></i>
                </span>
            </a>

            <?php endforeach; ?>

            <?php if (empty($filtered)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fa fa-magnifying-glass"></i></div>
                <h3>Không tìm thấy project</h3>
                <p>Thử thay đổi từ khoá hoặc bộ lọc.</p>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </main>
</div>

<!-- Folio Detail Sidebar (Right Drawer) -->
<div class="folio-sidebar-overlay" id="folioSidebarOverlay" onclick="closeFolioDetail()"></div>
<div class="folio-sidebar" id="folioSidebar">
    <div class="sidebar-head">
        <div>
            <div class="sidebar-title" id="sbTitle">Project Detail</div>
            <div class="sidebar-subtitle" id="sbSubtitle">Breakdown from Jira Tempo Folio</div>
        </div>
        <button class="sidebar-close" onclick="closeFolioDetail()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="sidebar-tabs">
        <div class="sidebar-tab active" id="tabActual" onclick="switchDetailTab('actual')">Actual</div>
        <div class="sidebar-tab" id="tabPlanned" onclick="switchDetailTab('planned')">Planned</div>
    </div>
    <div class="sidebar-body" id="sbBody">
        <div class="sidebar-loading">
            <i class="fa fa-spinner fa-spin"></i>
            Loading project details...
        </div>
    </div>
    <div class="sidebar-summary-bar" id="sbSummary" style="display:none;">
        <div class="summary-item">
            <div class="summary-label">Total Cost</div>
            <div class="summary-value" id="sbTotalVal">—</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Currency</div>
            <div class="summary-value" id="sbCurrency">—</div>
        </div>
    </div>
</div>

<?php if ($role === 'admin'): ?>
<!-- Budget Edit Modal -->
<div class="budget-modal-overlay" id="budgetModalOverlay">
    <div class="budget-modal">
        <h4><i class="fa fa-wallet" style="margin-right:8px;color:var(--folio-accent2);"></i>Budget & Plan</h4>
        <div style="font-size:0.8rem;color:var(--folio-muted);margin:-12px 0 16px;" id="bmProjectName"></div>
        <div style="font-size:0.72rem;background:rgba(99,102,241,0.1);border-radius:8px;padding:8px 12px;margin-bottom:16px;color:var(--folio-accent2);">
            <i class="fa fa-circle-info"></i> <strong>Actual</strong> được lấy tự động từ <strong>Tempo Timesheets</strong>
        </div>
        <input type="hidden" id="bmKey">
        <div class="bm-field">
            <label>Currency (cho Budget)</label>
            <select id="bmCurrency"><option value="USD">USD ($)</option><option value="VND">VND (đ)</option></select>
        </div>
        <div class="bm-field">
            <label>Budget — tổng ngân sách phê duyệt</label>
            <input type="number" id="bmBudget" min="0" step="0.01" placeholder="0">
        </div>
        <div class="bm-field">
            <label>Plan Cost — chi phí kế hoạch (bằng tiền)</label>
            <input type="number" id="bmPlanCost" min="0" step="0.01" placeholder="0">
        </div>
        <div class="bm-field">
            <label>Jira Folio ID — (vd: 2068) Để liên kết chi tiết</label>
            <input type="text" id="bmFolioId" placeholder="Nhập ID từ Jira Tempo Folio">
        </div>
        <div class="bm-field">
            <label>Hourly Rate — chi phí trung bình 1 giờ (bằng tiền)</label>
            <input type="number" id="bmCostRate" min="0" step="0.01" placeholder="0.00">
        </div>
        <div class="bm-actions">
            <button class="bm-btn bm-btn-cancel" onclick="closeBudgetModal()">Hủy</button>
            <button class="bm-btn bm-btn-save" onclick="saveBudget()"><i class="fa fa-save"></i> Lưu</button>
        </div>
    </div>
</div>
<?php endif; ?>
</div>

<script>
    const ALL_PROJECTS = <?php echo json_encode(array_values($projects), JSON_UNESCAPED_UNICODE); ?>;
    const JIRA_BASE    = <?php echo json_encode($jiraUrl); ?>;
    const IS_ADMIN     = <?php echo $role === 'admin' ? 'true' : 'false'; ?>;

    let searchTimeout = null;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(async () => {
            const q = document.getElementById('searchInput').value.trim();
            const url = new URL(window.location.href);
            if (q) url.searchParams.set('search', q);
            else url.searchParams.delete('search');
            
            // If searching, we might want to stay in wishlist mode if it's already active
            // but usually search is global. Let's keep the current wishlist state.
            
            window.history.pushState({}, '', url.toString());
            await loadFolioContent(url.toString());
        }, 500);
    }

    function clearSearchAjax() {
        document.getElementById('searchInput').value = '';
        handleSearchInput();
    }

    function handleTypeFilter() {
        const type = document.getElementById('typeFilter').value;
        const url = new URL(window.location.href);
        if (type) url.searchParams.set('type', type);
        else url.searchParams.delete('type');
        
        window.history.pushState({}, '', url.toString());
        loadFolioContent(url.toString());
    }

    let isWishlistOnly = false;

    async function toggleWishlistFilter() {
        const btn = document.getElementById('wishlistFilterBtn');
        const isActive = btn.classList.contains('active');
        const nextState = !isActive;
        
        const url = new URL(window.location.href);
        if (nextState) {
            url.searchParams.set('wishlist', '1');
            url.searchParams.delete('search'); // Clear search when entering wishlist
            document.getElementById('searchInput').value = '';
        } else {
            url.searchParams.delete('wishlist');
        }
        
        // Update URL
        window.history.pushState({}, '', url.toString());
        
        // AJAX Load
        await loadFolioContent(url.toString());
    }

    async function loadFolioContent(url) {
        const area = document.getElementById('folioContentArea');
        if (!area) return;
        
        area.style.opacity = '0.4';
        area.style.pointerEvents = 'none';
        
        try {
            const res = await fetch(url);
            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newContent = doc.getElementById('folioContentArea');
            if (newContent) {
                area.innerHTML = newContent.innerHTML;
                
                // Update Badge
                const newBadge = doc.getElementById('countBadge');
                if (newBadge) document.getElementById('countBadge').innerHTML = newBadge.innerHTML;
                
                // Update Button State
                const newBtn = doc.getElementById('wishlistFilterBtn');
                if (newBtn) document.getElementById('wishlistFilterBtn').className = newBtn.className;
                
                // Re-init view
                filterProjects(true);
                
                // Load actuals for visible cards
                const visibleGrid = document.querySelectorAll('#viewGrid .project-card');
                visibleGrid.forEach(c => {
                    if (c.style.display !== 'none') loadActualForCard(c);
                });
            }
        } catch (e) {
            console.error("AJAX Load Error:", e);
            window.location.href = url; // Fallback to normal reload
        } finally {
            area.style.opacity = '1';
            area.style.pointerEvents = 'auto';
        }
    }

    async function toggleWishlist(btn, folioId) {
        if (typeof event !== 'undefined') {
            event.stopPropagation();
            if (event.preventDefault) event.preventDefault();
        }
        
        const card = btn.closest('.project-card') || btn.closest('.list-item');
        if (!card) return;
        
        const isActive = btn.classList.contains('active');
        
        // Optimistic UI update for all views based on folioId
        const allElements = document.querySelectorAll(`[data-folio-id="${folioId}"]`);
        
        allElements.forEach(el => {
            const elBtn = el.querySelector('.wishlist-btn');
            if (elBtn) {
                const elIcon = elBtn.querySelector('i');
                if (isActive) {
                    elBtn.classList.remove('active');
                    if (elIcon) elIcon.className = 'fa-regular fa-heart';
                    el.dataset.isWishlisted = 'false';
                } else {
                    elBtn.classList.add('active');
                    if (elIcon) elIcon.className = 'fa-solid fa-heart';
                    el.dataset.isWishlisted = 'true';
                }
            }
        });

        try {
            const formData = new FormData();
            formData.append('folio_id', folioId);
            formData.append('action', 'toggle');

            const res = await fetch('/api/toggle_wishlist.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!data.success) {
                alert("Lỗi khi lưu wishlist: " + data.message);
                // Rollback all related elements
                allElements.forEach(el => {
                    const elBtn = el.querySelector('.wishlist-btn');
                    if (elBtn) {
                        const elIcon = elBtn.querySelector('i');
                        if (isActive) {
                            elBtn.classList.add('active');
                            if (elIcon) elIcon.className = 'fa-solid fa-heart';
                            el.dataset.isWishlisted = 'true';
                        } else {
                            elBtn.classList.remove('active');
                            if (elIcon) elIcon.className = 'fa-regular fa-heart';
                            el.dataset.isWishlisted = 'false';
                        }
                    }
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    function openBudgetModal(key, name, budget, planCost, costRate, currency, folioId) {
        document.getElementById('bmKey').value         = key;
        document.getElementById('bmProjectName').textContent = name;
        document.getElementById('bmBudget').value      = budget     || '';
        document.getElementById('bmPlanCost').value    = planCost   || '';
        document.getElementById('bmFolioId').value     = folioId    || '';
        document.getElementById('bmCostRate').value    = costRate   || '';
        document.getElementById('bmCurrency').value    = currency   || 'USD';
        document.getElementById('budgetModalOverlay').classList.add('open');
    }
    function closeBudgetModal() {
        document.getElementById('budgetModalOverlay').classList.remove('open');
    }
    function saveBudget() {
        const fd = new FormData();
        fd.append('folio_save',  '1');
        fd.append('jira_key',    document.getElementById('bmKey').value);
        fd.append('folio_id',    document.getElementById('bmFolioId').value);
        fd.append('budget',      document.getElementById('bmBudget').value);
        fd.append('plan_cost',   document.getElementById('bmPlanCost').value);
        fd.append('cost_rate',   document.getElementById('bmCostRate').value);
        fd.append('currency',    document.getElementById('bmCurrency').value);
        fetch('/folio', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); })
            .catch(console.error);
    }
    // close on overlay click
    document.getElementById('budgetModalOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeBudgetModal();
    });

    let currentView = 'grid';

    function setView(v) {
        currentView = v;
        document.getElementById('viewGrid').style.display = v === 'grid' ? 'grid' : 'none';
        document.getElementById('viewList').style.display = v === 'list' ? 'flex' : 'none';
        document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
        document.getElementById('btnList').classList.toggle('active', v === 'list');
        localStorage.setItem('folio-view', v);
    }

    let currentPage = 1;
    const itemsPerPage = 8;
    let filteredGridCards = [];
    let filteredListItems = [];

    function filterProjects(resetPage = true) {
        if (resetPage) currentPage = 1;

        const q    = document.getElementById('searchInput').value.toLowerCase().trim();
        const type = document.getElementById('typeFilter').value;

        const gridCards = document.querySelectorAll('#viewGrid .project-card');
        const listItems = document.querySelectorAll('#viewList .list-item');
        
        filteredGridCards = [];
        filteredListItems = [];

        gridCards.forEach(el => {
            const match = matchEl(el, q, type);
            if (match) filteredGridCards.push(el);
            else el.style.display = 'none';
        });

        listItems.forEach(el => {
            const match = matchEl(el, q, type);
            if (match) filteredListItems.push(el);
            else el.style.display = 'none';
        });

        renderPagination();
    }

    function renderPagination() {
        const total = filteredGridCards.length;
        const totalPages = Math.ceil(total / itemsPerPage);
        
        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        // Hide all filtered first, show only the current page
        filteredGridCards.forEach((el, idx) => {
            el.style.display = (idx >= start && idx < end) ? '' : 'none';
        });
        filteredListItems.forEach((el, idx) => {
            el.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        document.getElementById('countBadge').textContent = total + ' projects';
        
        renderPaginationUI(totalPages);
        // Note: AJAX loading is NOT triggered automatically on page change.
        // Data is loaded from DB cache and displayed server-side.
        // Use the Sync button on each card to refresh individual project data.
    }

    function renderPaginationUI(totalPages) {
        let pager = document.getElementById('folioPager');
        if (!pager) {
            pager = document.createElement('div');
            pager.id = 'folioPager';
            pager.className = 'folio-pager';
            // append to main container (after list views)
            document.querySelector('.folio-hero').parentNode.appendChild(pager);
        }
        
        if (totalPages <= 1) {
            pager.innerHTML = '';
            return;
        }

        let html = '';
        if (currentPage > 1) {
            html += `<button onclick="goToPage(${currentPage - 1})"><i class="fa fa-chevron-left"></i></button>`;
        }
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += `<button class="${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += `<span>...</span>`;
            }
        }
        
        if (currentPage < totalPages) {
            html += `<button onclick="goToPage(${currentPage + 1})"><i class="fa fa-chevron-right"></i></button>`;
        }
        
        pager.innerHTML = html;
    }

    function goToPage(p) {
        currentPage = p;
        renderPagination();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function matchEl(el, q, type) {
        const name = (el.dataset.name || '');
        const key  = (el.dataset.key  || '');
        const pType = (el.dataset.type || '');
        const isWish = (el.dataset.isWishlisted === 'true');
        
        const matchQ = !q || name.toLowerCase().includes(q) || key.toLowerCase().includes(q);
        const matchType = !type || pType === type;
        const isWishlistActive = document.getElementById('wishlistFilterBtn').classList.contains('active');
        const matchWish = !isWishlistActive || isWish;
        
        return matchQ && matchType && matchWish;
    }

    // Restore view preference
    (function() {
        const saved = localStorage.getItem('folio-view') || 'grid';
        setView(saved);
    })();

    // ── Async Tempo actual hours loader ──
    const CUR_YEAR = <?php echo $cur_year; ?>;

    function fmtHours(h) {
        if (!h || h === 0) return '—';
        return parseFloat(h).toFixed(1) + 'h';
    }

    function fmtMoney(v, cur = 'USD') {
        if (!v) return '<span style="color:#475569">—</span>';
        if (cur === 'VND') return parseFloat(v).toLocaleString('vi-VN') + ' đ';
        return '$' + parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function refreshAllFolios() {
        if (!confirm('Làm mới dữ liệu từ Jira Tempo Folio? Việc này có thể mất vài giây.')) return;
        const gridCards = document.querySelectorAll('#viewGrid .project-card');
        gridCards.forEach(card => {
            delete card.dataset.loaded;
            loadActualForCard(card, true);
        });
    }

    function reloadSingleFolio(btn, key) {
        const card = btn.closest('.project-card');
        if (card) {
            const icon = btn.querySelector('i');
            if (icon) icon.classList.add('fa-spin');
            
            delete card.dataset.loaded;
            loadActualForCard(card, true).finally(() => {
                if (icon) icon.classList.remove('fa-spin');
            });
        }
    }

    async function loadActualForCard(card, forceRefresh = false) {
        if (card.dataset.loaded && !forceRefresh) return;
        card.dataset.loaded = "true";

        const projKey = card.dataset.key;
        const projName = card.dataset.projectName || '';
        const year    = <?php echo $cur_year; ?>;
        
        const actualVal = card.querySelector('.actual-val');
        try {
            let url = `/api/get_tempo_actual.php?project=${projKey}&name=${encodeURIComponent(projName)}&year=${year}`;
            if (forceRefresh) url += '&refresh=1';
            
            const res = await fetch(url);
            const data = await res.json();

            const actualBar = card.querySelector('.actual-bar');
            const planDataEl = card.querySelector('.plan-data-holder');
            
            const hours      = data.hours || 0;
            const folioData  = data.folio_data || null;
            const isCached   = data.is_cached || false;
            
            let planCost   = parseFloat(planDataEl ? planDataEl.dataset.plan : 0);
            let costRate   = parseFloat(planDataEl ? planDataEl.dataset.rate : 0);
            let cur        = planDataEl ? planDataEl.dataset.cur : 'USD';
            let actualCost = hours * costRate;
            let isFolioAutomated = false;

            if (folioData) {
                const fid = folioData.folio_id || folioData.id;
                planCost = folioData.plan_cost || planCost;
                actualCost = folioData.actual_cost || actualCost;
                cur = folioData.currency || cur;
                isFolioAutomated = true;
                if (fid) card.dataset.folioId = fid;
            }

            // Update text
            let txt = fmtMoney(actualCost, cur);
            if (hours > 0) txt += ` <span style="font-size:0.68rem;color:var(--folio-muted);margin-left:4px;">(${fmtHours(hours)})</span>`;
            actualVal.innerHTML = txt;

            // Update Plan Cost text to show AUTO indicator
            const planVal = card.querySelector('.plan-val');
            if (planVal && isFolioAutomated) {
                const cacheInfo = isCached ? ` (Cached: ${folioData.cached_at})` : ' (Fresh)';
                planVal.innerHTML = fmtMoney(planCost, cur) + ` <span title="Auto synced from Jira Tempo Budgets${cacheInfo}" class="auto-badge">AUTO</span>`;
            }

            // Update color and progress bars based on Cost
            if (actualCost > 0 || hours > 0) {
                const overPlan = planCost > 0 && actualCost > planCost;
                actualVal.style.color = overPlan ? '#ef4444' : '#22c55e';

                if (actualBar && planCost > 0) {
                    const pct = Math.min(100, Math.round(actualCost / planCost * 100));
                    actualBar.style.width = pct + '%';
                    actualBar.className = 'budget-bar ' + (overPlan ? 'bar-over' : 'bar-actual');
                } else if (actualBar && actualCost > 0) {
                    actualBar.style.width = '30%';
                    actualBar.className = 'budget-bar bar-actual';
                }

                // Over-plan warning
                if (planCost > 0 && actualCost > planCost) {
                    const warn = card.querySelector('.over-plan-warn');
                    if (warn) {
                        const pct = Math.round((actualCost - planCost) / planCost * 100);
                        warn.textContent = `⚠ Over plan ${pct}%`;
                        warn.style.display = 'block';
                    }
                }
            }
        } catch(e) {
            if (actualVal) actualVal.innerHTML = '<span style="color:#475569;font-size:0.7rem;">err</span>';
        }
    }

    // ── Folio Detail Sidebar Logic ──
    let currentFolioId = null;
    let currentDetailTab = 'planned';
    let detailCache = {};

    function openFolioDetail(btn) {
        const card = btn.closest('.project-card');
        const folioId = card.dataset.folioId;
        const key = card.dataset.key;
        const name = card.querySelector('h3').textContent;

        if (!folioId || folioId == "0") {
            // Smart link: if ID is missing, try to load it first
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Linking...';
            loadActualForCard(card).then(() => {
                const newId = card.dataset.folioId;
                btn.innerHTML = '<i class="fa fa-chart-bar"></i> View Detail';
                if (!newId || newId == "0") {
                    alert("Không tìm thấy Folio tương ứng trên Jira cho dự án này.");
                } else {
                    openFolioDetail(btn); // Retry with the new ID
                }
            });
            return;
        }

        currentFolioId = folioId;
        document.getElementById('sbTitle').textContent = name;
        document.getElementById('sbSubtitle').textContent = "Jira Folio: " + key + " (#" + folioId + ")";
        
        document.getElementById('folioSidebarOverlay').classList.add('open');
        document.getElementById('folioSidebar').classList.add('open');
        
        switchDetailTab('actual');
    }

    function closeFolioDetail() {
        document.getElementById('folioSidebarOverlay').classList.remove('open');
        document.getElementById('folioSidebar').classList.remove('open');
    }

    async function switchDetailTab(tab) {
        currentDetailTab = tab;
        document.querySelectorAll('.sidebar-tab').forEach(el => el.classList.remove('active'));
        document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');

        const body = document.getElementById('sbBody');
        body.innerHTML = `<div class="sidebar-loading"><i class="fa fa-spinner fa-spin"></i> Loading ${tab} data...</div>`;
        document.getElementById('sbSummary').style.display = 'none';

        try {
            const res = await fetch(`/api/get_folio_detail.php?folio_id=${currentFolioId}&tab=${tab}`);
            if (!res.ok) throw new Error("HTTP error " + res.status);
            const data = await res.json();
            renderDetailData(tab, data);
        } catch (e) {
            console.error(e);
            body.innerHTML = `<div class="sidebar-loading" style="color:#ef4444;"><i class="fa fa-circle-exclamation"></i> Error loading data: ${e.message}</div>`;
        }
    }

    function renderDetailData(tab, data) {
        const body = document.getElementById('sbBody');
        const summary = document.getElementById('sbSummary');
        const tabData = data[tab] || {};
        const overview = data.overview || {};
        let currency = overview.currency ? overview.currency.code : 'USD';

        if (tabData.code !== 200) {
            body.innerHTML = `<div class="sidebar-loading">No ${tab} data available for this folio.</div>`;
            return;
        }

        const items = (tabData.data && tabData.data.success) ? tabData.data.success : (tabData.data || []);
        
        function renderNodes(node, level = 0) {
            if (!node || typeof node !== 'object' || level > 12) return '';
            let out = '';
            
            if (Array.isArray(node)) {
                node.forEach(item => { out += renderNodes(item, level); });
                return out;
            }

            const name = node.displayName || node.name || node.title || '';
            const count = node.expensesCount !== undefined ? node.expensesCount : (node.positionsCount || 0);
            const totalCost = node.totalCost || node.amount || node.convertedAmount || node.actualCost || 0;
            const id = node.id || '';

            if (name || totalCost > 0) {
                out += `
                <div class="folio-node level-${level} ${level > 1 ? 'collapsed' : ''}" data-node-id="${id}">
                    <div class="node-main" onclick="this.parentElement.classList.toggle('collapsed')">
                        <span class="node-toggle"><i class="fa fa-chevron-down"></i></span>
                        <span class="node-name">${name} ${count > 0 ? `<small style="color:var(--folio-muted);margin-left:4px;">(${count})</small>` : ''}</span>
                        <span class="node-spacer"></span>
                        <span class="node-value">${fmtMoney(totalCost, currency)}</span>
                    </div>
                </div>`;
            }

            const childrenKeys = ['opexCategory', 'expenseTypes', 'expenses', 'positionRoles', 'positions', 'paymentList', 'success'];
            let childrenHtml = '';
            childrenKeys.forEach(key => {
                if (node[key]) {
                    childrenHtml += renderNodes(node[key], level + 1);
                }
            });

            if (childrenHtml) {
                out += `<div class="node-children">${childrenHtml}</div>`;
            }

            return out;
        }

        body.innerHTML = renderNodes(items);
        
        summary.style.display = 'flex';
        const total = tab === 'actual' ? (overview.actual_cost || 0) : (overview.planned_cost || 0);
        document.getElementById('sbTotalVal').innerHTML = fmtMoney(total, currency);
        document.getElementById('sbCurrency').textContent = currency;
    }

    // Load all visible cards with a small concurrency limit (5 at a time)
    async function loadAllActuals() {
        // Only select project cards that are currently visible (not display: none)
        const cards = [...document.querySelectorAll('#viewGrid .project-card')].filter(c => c.style.display !== 'none');
        const BATCH = 5;
        for (let i = 0; i < cards.length; i += BATCH) {
            await Promise.all(cards.slice(i, i + BATCH).map(loadActualForCard));
        }
    }

    // Initialize pagination on page load
    document.addEventListener('DOMContentLoaded', () => {
        filterProjects(true);
    });

</script>
</body>
</html>
