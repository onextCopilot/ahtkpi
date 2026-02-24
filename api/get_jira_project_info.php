<?php
require_once __DIR__ . '/../libs/JiraAPI.php';
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$projectName = $_GET['project_name'] ?? '';

if (empty($projectName)) {
    http_response_code(400);
    die('Missing project name');
}

// Initialize Jira API
try {
    $jira = new JiraAPI();
    // First, try to find the project key from the cache
    $projects = $jira->getProjects(); // This uses cache if available
} catch (Exception $e) {
    http_response_code(500);
    die('Jira API Error: ' . $e->getMessage());
}

$projectKey = null;

foreach ($projects as $p) {
    if (strcasecmp($p['name'], $projectName) === 0 || strcasecmp($p['key'], $projectName) === 0) {
        $projectKey = $p['key'];
        break;
    }
}

if (!$projectKey) {
    // Project not found in Jira check
    http_response_code(404);
    die('Project not found in Jira');
}

// Fetch detailed info
$details = $jira->getProjectDetails($projectKey);

if (!$details) {
    http_response_code(404);
    die('Could not fetch project details');
}

// Format the tooltip content
$avatarUrl = $details['avatarUrls']['48x48'] ?? '';
$name = htmlspecialchars($details['name'] ?? 'Unknown');
$key = htmlspecialchars($details['key'] ?? '');

$lead = $details['lead'] ?? [];
$leadName = htmlspecialchars($lead['displayName'] ?? 'N/A');
$leadAvatar = $lead['avatarUrls']['24x24'] ?? '';

$categoryName = $details['projectCategory']['name'] ?? 'None';
$category = htmlspecialchars($categoryName);

$descriptionRaw = $details['description'] ?? 'No description available.';
$description = nl2br(htmlspecialchars($descriptionRaw));

$url = htmlspecialchars($details['self'] ?? '#');

// Fetch additional stats
$issueStats = $jira->getIssueCounts($projectKey);
// Fetch users
$users = $jira->getAssignableUsers($projectKey);
$memberCount = count($users);

// Filter users with avatars and sort/prioritize if needed
// For now just take first N users
$displayUsers = array_slice($users, 0, 7);
$remainingUsers = $memberCount - count($displayUsers);

// Calculate worklog in hours
$totalHours = ($issueStats['log_seconds'] > 0) ? round($issueStats['log_seconds'] / 3600, 1) : 0;

?>
<div class="jira-tooltip-content"
    style="width: 320px; padding: 15px; position: relative; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <span onclick="document.getElementById('jira-project-tooltip').style.display='none'; event.stopPropagation();"
        style="position: absolute; top: 10px; right: 10px; cursor: pointer; color: #6b778c; font-weight: bold; font-size: 18px; line-height: 1; z-index: 10;">&times;</span>

    <div class="jira-tooltip-header"
        style="display: flex; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dfe1e6; cursor: move;">
        <?php if ($avatarUrl): ?>
            <img src="<?php echo $avatarUrl; ?>" alt="Icon"
                style="width: 40px; height: 40px; border-radius: 4px; margin-right: 12px; pointer-events: none;">
        <?php endif; ?>
        <div style="flex: 1; min-width: 0; pointer-events: none;">
            <div style="font-weight: 600; color: #172b4d; font-size: 15px; margin-bottom: 2px; line-height: 1.2;">
                <?php echo $name; ?>
            </div>
            <div style="font-size: 12px; color: #6b778c;">
                <span
                    style="background:#dfe1e5; padding: 1px 4px; border-radius: 3px; color:#42526e; font-weight:500; font-size: 11px;"><?php echo $key; ?></span>
                &bull; <?php echo $category; ?>
            </div>
        </div>
    </div>

    <!-- Description -->
    <?php if (!empty($descriptionRaw)): ?>
        <div
            style="font-size: 13px; color: #42526e; margin-bottom: 15px; line-height: 1.5; max-height: 60px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
            <?php echo $description; ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
        <div style="background: #f4f5f7; padding: 8px; border-radius: 4px; text-align: center;">
            <div style="font-size: 18px; font-weight: 600; color: #0052cc;"><?php echo $issueStats['total']; ?></div>
            <div style="font-size: 11px; color: #6b778c; text-transform: uppercase; font-weight: 600;">Total Issues
            </div>
        </div>
        <div style="background: #f4f5f7; padding: 8px; border-radius: 4px; text-align: center;">
            <div style="font-size: 18px; font-weight: 600; color: #00875a;">
                <?php echo $issueStats['total'] - $issueStats['unresolved']; ?>
            </div>
            <div style="font-size: 11px; color: #6b778c; text-transform: uppercase; font-weight: 600;">Resolved</div>
        </div>
        <div style="background: #f4f5f7; padding: 8px; border-radius: 4px; text-align: center;">
            <div style="font-size: 18px; font-weight: 600; color: #ff991f;"><?php echo $issueStats['unresolved']; ?>
            </div>
            <div style="font-size: 11px; color: #6b778c; text-transform: uppercase; font-weight: 600;">Open Issues</div>
        </div>
        <div style="background: #f4f5f7; padding: 8px; border-radius: 4px; text-align: center;">
            <div style="font-size: 18px; font-weight: 600; color: #42526e;"><?php echo $totalHours; ?>h</div>
            <div style="font-size: 11px; color: #6b778c; text-transform: uppercase; font-weight: 600;">Logged Time</div>
        </div>
    </div>

    <div style="padding-top: 10px; border-top: 1px solid #dfe1e6;">
        <!-- Members -->
        <div style="margin-bottom: 8px;">
            <div
                style="font-size: 11px; color: #6b778c; text-transform: uppercase; font-weight: 600; margin-bottom: 6px;">
                Team Members (<?php echo $memberCount; ?>)
            </div>
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                <?php foreach ($displayUsers as $u):
                    $uAvatar = $u['avatarUrls']['24x24'] ?? '';
                    $uName = htmlspecialchars($u['displayName']);
                    ?>
                    <img src="<?php echo $uAvatar; ?>" title="<?php echo $uName; ?>"
                        style="width: 24px; height: 24px; border-radius: 50%; border: 1px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                <?php endforeach; ?>
                <?php if ($remainingUsers > 0): ?>
                    <span
                        style="display: flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; padding: 0 4px; border-radius: 12px; background: #ebecf0; color: #505f79; font-size: 10px; font-weight: 600; border: 1px solid #fff;">+<?php echo $remainingUsers; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; align-items: center; font-size: 12px; color: #505f79; margin-top: 8px;">
            <span style="margin-right: 5px;">Lead:</span>
            <?php if ($leadAvatar): ?>
                <img src="<?php echo $leadAvatar; ?>" alt="Lead"
                    style="width: 20px; height: 20px; border-radius: 50%; margin-right: 5px; vertical-align: middle;">
            <?php endif; ?>
            <span style="font-weight: 600;"><?php echo $leadName; ?></span>
        </div>
    </div>
</div>