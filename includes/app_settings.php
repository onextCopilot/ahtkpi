<?php
/**
 * app_settings — key/value config store dùng chung.
 * Bảng: app_settings(skey VARCHAR PK, svalue TEXT, updated_at, updated_by)
 */
function app_settings_ensure($conn)
{
    static $done = false;
    if ($done) return;
    $conn->query("CREATE TABLE IF NOT EXISTS app_settings (
        skey VARCHAR(100) PRIMARY KEY,
        svalue TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function app_setting_get($conn, $key, $default = null)
{
    app_settings_ensure($conn);
    if ($st = $conn->prepare("SELECT svalue FROM app_settings WHERE skey = ?")) {
        $st->bind_param("s", $key);
        $st->execute();
        $r = $st->get_result();
        if ($row = $r->fetch_assoc()) {
            $st->close();
            return $row['svalue'];
        }
        $st->close();
    }
    return $default;
}

function app_setting_set($conn, $key, $value, $uid = null)
{
    app_settings_ensure($conn);
    if ($st = $conn->prepare("INSERT INTO app_settings (skey, svalue, updated_by) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_by = VALUES(updated_by)")) {
        $st->bind_param("ssi", $key, $value, $uid);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
    return false;
}
