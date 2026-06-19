<?php
/**
 * HRM Webhook Endpoint
 * Route: /hrm/webhook.php
 * Handles external data ingestion (e.g., job applications from WordPress).
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/events.php';

header('Content-Type: application/json; charset=utf-8');

// Allowed methods
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Authenticate via API Key
$headers = getallheaders();
$apiKey = $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? $_POST['api_key'] ?? '';
$systemKey = hrm_setting($conn, 'aht_api_key', '');

if ($systemKey === '' || $apiKey !== $systemKey) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized or missing API Key']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'receive_application': {
        $externalJobId = trim($_POST['external_job_id'] ?? '');
        $name = trim($_POST['applicant_name'] ?? '');
        $email = trim($_POST['applicant_email'] ?? '');
        $phone = trim($_POST['applicant_phone'] ?? '');

        if (!$externalJobId || $name === '' || $email === '' || $phone === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // 1. Find local job
        $stJob = $conn->prepare('SELECT id, title FROM hrm_jobs WHERE external_id = ? LIMIT 1');
        $stJob->bind_param('s', $externalJobId);
        $stJob->execute();
        $job = $stJob->get_result()->fetch_assoc();

        if (!$job) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Job not found']);
            exit;
        }

        $jobId = (int)$job['id'];

        // 2. Handle CV Upload
        $cvPath = '';
        if (!empty($_FILES['applicant_cv']['tmp_name']) && $_FILES['applicant_cv']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['applicant_cv']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx'];
            if (in_array($ext, $allowed) && $_FILES['applicant_cv']['size'] <= 5 * 1024 * 1024) {
                $dir = __DIR__ . '/../../uploads/hrm/cvs/' . date('Y/m');
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                // safe filename
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) . '_' . time() . '.' . $ext;
                $target = $dir . '/' . $safeName;
                if (move_uploaded_file($_FILES['applicant_cv']['tmp_name'], $target)) {
                    // Save relative path for serving
                    $cvPath = '/uploads/hrm/cvs/' . date('Y/m') . '/' . $safeName;
                }
            }
        }

        // 3. Find or Create Source "Website"
        $srcId = 0;
        $srcRes = $conn->query("SELECT id FROM hrm_candidate_sources WHERE name = 'Website' LIMIT 1");
        if ($srcRow = $srcRes->fetch_assoc()) {
            $srcId = (int)$srcRow['id'];
        } else {
            $conn->query("INSERT INTO hrm_candidate_sources (name) VALUES ('Website')");
            $srcId = $conn->insert_id;
        }

        // 4. Create Candidate
        $stCand = $conn->prepare('INSERT INTO hrm_candidates (full_name, email, phone, source_id, created_by, cv_path) VALUES (?, ?, ?, ?, 0, ?)');
        $stCand->bind_param('sssis', $name, $email, $phone, $srcId, $cvPath);
        if (!$stCand->execute()) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to create candidate']);
            exit;
        }
        $cid = $stCand->insert_id;

        // 5. Create Application at SCREENING stage
        $stage = $conn->query("SELECT id, sla_hours FROM hrm_pipeline_stages WHERE code='SCREENING'")->fetch_assoc();
        $sid = (int)($stage['id'] ?? 0);
        $stApp = $conn->prepare('INSERT INTO hrm_applications (candidate_id, job_id, stage_id, owner_id) VALUES (?, ?, ?, 0)');
        $stApp->bind_param('iii', $cid, $jobId, $sid);
        $stApp->execute();
        $aid = $stApp->insert_id;

        // 6. SLA & Email
        if (!empty($stage['sla_hours'])) {
            hrm_sla_open($conn, 'application', $aid, 'screening', date('Y-m-d H:i:s', strtotime('+' . (int)$stage['sla_hours'] . ' hours')));
        }
        if ($email) { 
            hrm_send_email($conn, 'cv_received', $email, ['candidate_name' => $name, 'job_title' => $job['title'] ?? ''], 'application', $aid); 
        }
        
        // Audit log (user 0 = System)
        hrm_audit($conn, 0, 'candidate_add_webhook', 'application', $aid, $name);

        echo json_encode(['ok' => true, 'application_id' => $aid, 'candidate_id' => $cid]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid action']);
        break;
}
