<?php
/**
 * Outbound Radar — lưu trữ lịch sử & cache (mysqli).
 * Lưu nguyên kết quả run_pipeline ($r) dạng JSON để render lại không cần fetch.
 */

declare(strict_types=1);

function radar_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS outbound_radar_scans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            input_url VARCHAR(512) NOT NULL,
            url_key VARCHAR(255) NOT NULL,
            company VARCHAR(255) NULL,
            ats_provider VARCHAR(64) NULL,
            job_source VARCHAR(16) NULL,
            score INT NULL,
            dev_count INT NULL,
            ai_pitch TINYINT(1) NOT NULL DEFAULT 0,
            data_json MEDIUMTEXT NOT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_urlkey (url_key),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    // Migration cho bảng đã tồn tại trước khi thêm cột note.
    $res = $conn->query("SHOW COLUMNS FROM outbound_radar_scans LIKE 'note'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE outbound_radar_scans ADD COLUMN note TEXT NULL AFTER data_json");
    }
}

/** Chuẩn hoá URL thành khoá cache (bỏ scheme/www/dấu / cuối, hạ chữ). */
function radar_url_key(string $url): string
{
    $u = strtolower(trim($url));
    $u = preg_replace('~^https?://~', '', $u);
    $u = preg_replace('~^www\.~', '', $u);
    return substr(rtrim($u, '/'), 0, 255);
}

/** Lưu một lần quét. Trả về id. */
function radar_save_scan(mysqli $conn, int $userId, string $url, array $r): int
{
    $key      = radar_url_key($url);
    $json     = json_encode($r, JSON_UNESCAPED_UNICODE);
    $company  = (string) ($r['company'] ?? '');
    $provider = (string) ($r['detect']['provider'] ?? '');
    $source   = (string) ($r['job_source'] ?? '');
    $score    = (int) ($r['analysis']['score'] ?? 0);
    $devCount = (int) ($r['analysis']['dev_count'] ?? 0);
    $aiPitch  = !empty($r['ai_pitch']) ? 1 : 0;
    $now      = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "INSERT INTO outbound_radar_scans
            (user_id, input_url, url_key, company, ats_provider, job_source, score, dev_count, ai_pitch, data_json, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        'isssssiiiss',
        $userId, $url, $key, $company, $provider, $source, $score, $devCount, $aiPitch, $json, $now
    );
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/** Tìm bản quét gần đây của cùng URL trong $maxAgeDays ngày (cache). */
function radar_find_recent(mysqli $conn, string $url, int $maxAgeDays = 7): ?array
{
    $key = radar_url_key($url);
    $stmt = $conn->prepare(
        "SELECT id, created_at, data_json, note FROM outbound_radar_scans
         WHERE url_key = ? AND created_at >= (NOW() - INTERVAL ? DAY)
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('si', $key, $maxAgeDays);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $data = json_decode($row['data_json'], true);
    if (!is_array($data)) return null;
    return ['id' => (int) $row['id'], 'created_at' => $row['created_at'], 'data' => $data, 'note' => $row['note'] ?? ''];
}

/** Lấy 1 bản quét theo id. */
function radar_get_by_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT id, created_at, data_json, note FROM outbound_radar_scans WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $data = json_decode($row['data_json'], true);
    if (!is_array($data)) return null;
    return ['id' => (int) $row['id'], 'created_at' => $row['created_at'], 'data' => $data, 'note' => $row['note'] ?? ''];
}

/** Danh sách lịch sử gần đây, kèm người quét; lọc theo $q (công ty/url/note). */
function radar_history(mysqli $conn, int $limit = 30, string $q = ''): array
{
    $limit  = max(1, min(200, $limit));
    $where  = '';
    $params = [];
    $types  = '';
    if ($q !== '') {
        $where    = "WHERE (s.company LIKE ? OR s.input_url LIKE ? OR s.note LIKE ?)";
        $like     = '%' . $q . '%';
        $params   = [$like, $like, $like];
        $types    = 'sss';
    }
    $sql = "SELECT s.id, s.company, s.input_url, s.score, s.dev_count, s.ats_provider,
                   s.job_source, s.ai_pitch, s.created_at, s.note, u.full_name
            FROM outbound_radar_scans s
            LEFT JOIN users u ON u.id = s.user_id
            {$where}
            ORDER BY s.id DESC LIMIT ?";
    $params[] = $limit;
    $types   .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/** Đọc 1 giá trị cấu hình từ system_settings. */
function radar_get_setting(mysqli $conn, string $key): ?string
{
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['setting_value'] ?? null;
}

/** Lưu/cập nhật 1 giá trị cấu hình vào system_settings. */
function radar_set_setting(mysqli $conn, string $key, string $val, string $desc = ''): void
{
    $stmt = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param('ss', $val, $key);
    } else {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?,?,?)");
        $stmt->bind_param('sss', $key, $val, $desc);
    }
    $stmt->execute();
    $stmt->close();
}

/** Xoá một bản quét. */
function radar_delete(mysqli $conn, int $id): void
{
    $stmt = $conn->prepare("DELETE FROM outbound_radar_scans WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/** Lưu/cập nhật ghi chú cho một bản quét. */
function radar_save_note(mysqli $conn, int $id, string $note): void
{
    $stmt = $conn->prepare("UPDATE outbound_radar_scans SET note = ? WHERE id = ?");
    $stmt->bind_param('si', $note, $id);
    $stmt->execute();
    $stmt->close();
}
