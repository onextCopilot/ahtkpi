<?php
/**
 * Candidate pool - shared query/filter layer (dùng chung cho list + export).
 */
require_once __DIR__ . '/core.php';

/** Đọc bộ lọc từ $_GET. */
function hrm_candidate_filters(): array
{
    return [
        'q'        => trim($_GET['q'] ?? ''),
        'source'   => (int)($_GET['source'] ?? 0),
        'event'    => (int)($_GET['event'] ?? 0),
        'status'   => trim($_GET['status'] ?? ''),
        'owner'    => (int)($_GET['owner'] ?? 0),
        'tag'      => trim($_GET['tag'] ?? ''),
        'skill'    => trim($_GET['skill'] ?? ''),
        'pool'     => isset($_GET['pool']) && $_GET['pool'] !== '' ? (int)$_GET['pool'] : -1,
        'pool_id'  => (int)($_GET['pool_id'] ?? 0),
        'has_cv'   => trim($_GET['has_cv'] ?? ''),
        'from'     => trim($_GET['from'] ?? ''),
        'to'       => trim($_GET['to'] ?? ''),
        'ids'      => trim($_GET['ids'] ?? ''), // export selection
    ];
}

/** Trả về danh sách ứng viên + cột phụ trợ theo bộ lọc. */
function hrm_candidate_query(mysqli $conn, array $f, int $limit = 500): array
{
    $where = []; $params = []; $types = '';

    if ($f['ids'] !== '') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $f['ids']))));
        if ($ids) { $where[] = 'c.id IN (' . implode(',', $ids) . ')'; }
    }
    if ($f['q'] !== '') {
        $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.current_position LIKE ?)';
        $like = '%' . $f['q'] . '%'; array_push($params, $like, $like, $like, $like); $types .= 'ssss';
    }
    if ($f['source'] > 0) { $where[] = 'c.source_id = ?'; $params[] = $f['source']; $types .= 'i'; }
    if ($f['event'] > 0)  { $where[] = 'c.event_id = ?';  $params[] = $f['event'];  $types .= 'i'; }
    if ($f['owner'] > 0)  { $where[] = 'c.owner_id = ?';  $params[] = $f['owner'];  $types .= 'i'; }
    if ($f['status'] !== '' && isset(hrm_candidate_statuses()[$f['status']])) { $where[] = 'c.status = ?'; $params[] = $f['status']; $types .= 's'; }
    if ($f['pool'] === 0 || $f['pool'] === 1) { $where[] = 'c.talent_pool = ?'; $params[] = $f['pool']; $types .= 'i'; }
    if (!empty($f['pool_id'])) { $where[] = 'EXISTS (SELECT 1 FROM hrm_candidate_pools cp WHERE cp.candidate_id=c.id AND cp.pool_id=?)'; $params[] = $f['pool_id']; $types .= 'i'; }
    if ($f['has_cv'] === '1') { $where[] = "c.cv_path <> ''"; }
    if ($f['has_cv'] === '0') { $where[] = "c.cv_path = ''"; }
    if ($f['from'] !== '') { $where[] = 'DATE(c.created_at) >= ?'; $params[] = $f['from']; $types .= 's'; }
    if ($f['to'] !== '')   { $where[] = 'DATE(c.created_at) <= ?'; $params[] = $f['to'];   $types .= 's'; }
    if ($f['tag'] !== '')   { $where[] = 'EXISTS (SELECT 1 FROM hrm_candidate_tags t WHERE t.candidate_id=c.id AND t.tag=?)'; $params[] = $f['tag']; $types .= 's'; }
    if ($f['skill'] !== '') { $where[] = 'EXISTS (SELECT 1 FROM hrm_candidate_skills sk WHERE sk.candidate_id=c.id AND sk.skill LIKE ?)'; $params[] = '%' . $f['skill'] . '%'; $types .= 's'; }

    // Mặc định ẩn ứng viên đã lưu trữ trừ khi lọc đúng trạng thái đó.
    if ($f['status'] !== 'archived') { $where[] = "c.status <> 'archived'"; }

    $sql = "SELECT c.*, s.name AS source_name, ev.name AS event_name, u.full_name AS owner_name,
            (SELECT GROUP_CONCAT(t.tag SEPARATOR ',') FROM hrm_candidate_tags t WHERE t.candidate_id=c.id) AS tag_list,
            (SELECT GROUP_CONCAT(p.name SEPARATOR ',') FROM hrm_candidate_pools cp JOIN hrm_pools p ON p.id=cp.pool_id WHERE cp.candidate_id=c.id AND p.active=1) AS pool_list,
            (SELECT GROUP_CONCAT(sk.skill SEPARATOR ', ') FROM hrm_candidate_skills sk WHERE sk.candidate_id=c.id) AS skill_list,
            (SELECT j.title FROM hrm_applications a JOIN hrm_jobs j ON j.id=a.job_id WHERE a.candidate_id=c.id ORDER BY a.id DESC LIMIT 1) AS app_job,
            (SELECT ps.name FROM hrm_applications a LEFT JOIN hrm_pipeline_stages ps ON ps.id=a.stage_id WHERE a.candidate_id=c.id ORDER BY a.id DESC LIMIT 1) AS app_stage
            FROM hrm_candidates c
            LEFT JOIN hrm_candidate_sources s ON s.id=c.source_id
            LEFT JOIN hrm_events ev ON ev.id=c.event_id
            LEFT JOIN users u ON u.id=c.owner_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY c.id DESC LIMIT ' . (int)$limit;
    $st = $conn->prepare($sql);
    if ($types) { $st->bind_param($types, ...$params); }
    $st->execute();
    return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Các option cho thanh lọc. */
function hrm_candidate_filter_options(mysqli $conn): array
{
    $sources = $conn->query('SELECT id,name FROM hrm_candidate_sources WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $events  = $conn->query('SELECT id,name FROM hrm_events WHERE active=1 ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
    $owners  = $conn->query("SELECT DISTINCT u.id, u.full_name FROM users u JOIN hrm_candidates c ON c.owner_id=u.id ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);
    $tags    = $conn->query("SELECT tag, COUNT(*) n FROM hrm_candidate_tags GROUP BY tag ORDER BY n DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
    $pools   = $conn->query("SELECT id,name,color FROM hrm_pools WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    return ['sources' => $sources, 'events' => $events, 'owners' => $owners, 'tags' => $tags, 'pools' => $pools];
}
