<?php
// Auto-migrate: Core Key KPI tables
// Called from core_kpi/index.php — $conn already exists

// --- core_kpi_members: mapping chọn user hệ thống làm nhân viên Core/Key ---
$conn->query("CREATE TABLE IF NOT EXISTS core_kpi_members (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL UNIQUE COMMENT 'Reference to users.id',
    member_type  ENUM('Core','Key') DEFAULT 'Key',
    is_active    TINYINT(1) DEFAULT 1,
    added_by     INT DEFAULT NULL,
    added_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migration to add member_type if not exists
$chk = $conn->query("SHOW COLUMNS FROM core_kpi_members LIKE 'member_type'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE core_kpi_members ADD COLUMN member_type ENUM('Core','Key') DEFAULT 'Key' AFTER user_id");
}

// Keep backward-compat alias view (use old table name too if exists)
$old_tbl = $conn->query("SHOW TABLES LIKE 'core_kpi_employees'");
if ($old_tbl && $old_tbl->num_rows === 0) {
    // Nothing to migrate
}

// --- core_kpi_cycles: Chu kỳ đánh giá (năm + quý, hoặc tùy chỉnh) ---
$conn->query("CREATE TABLE IF NOT EXISTS core_kpi_cycles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL COMMENT 'Ví dụ: Q1/2025',
    year          SMALLINT NOT NULL,
    quarter       TINYINT DEFAULT NULL COMMENT '1-4 hoặc NULL nếu là năm',
    start_date    DATE,
    end_date      DATE,
    status        ENUM('planning','active','reviewing','closed') DEFAULT 'planning',
    notes         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- core_kpi_definitions: KPI template/definitions dùng lại ---
$conn->query("CREATE TABLE IF NOT EXISTS core_kpi_definitions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category      VARCHAR(100) DEFAULT 'General' COMMENT 'Nhóm KPI',
    kpi_name      VARCHAR(255) NOT NULL,
    description   TEXT,
    default_unit  VARCHAR(50) DEFAULT NULL,
    calc_type     ENUM('maximize','minimize','target') DEFAULT 'maximize' COMMENT 'maximize = càng cao càng tốt',
    sort_order    INT DEFAULT 0,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- core_kpi_assignments: Gán KPI definition cho user theo chu kỳ ---
$conn->query("CREATE TABLE IF NOT EXISTS core_kpi_assignments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL COMMENT 'references users.id',
    kpi_def_id    INT NOT NULL,
    cycle_id      INT NOT NULL,
    target_value  DECIMAL(14,2) DEFAULT NULL,
    unit          VARCHAR(50) DEFAULT NULL,
    weight        DECIMAL(5,2) DEFAULT 1.00,
    notes         TEXT,
    created_by    INT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_def_cycle (user_id, kpi_def_id, cycle_id),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (kpi_def_id)  REFERENCES core_kpi_definitions(id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id)    REFERENCES core_kpi_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Migration: rename employee_id → user_id in assignments if old column exists ──
$chk = $conn->query("SHOW COLUMNS FROM core_kpi_assignments LIKE 'employee_id'");
if ($chk && $chk->num_rows > 0) {
    // Drop old FK referencing core_kpi_employees if it exists
    $fk_res = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'core_kpi_assignments'
        AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    if ($fk_res) {
        while ($fk_row = $fk_res->fetch_assoc()) {
            $conn->query("ALTER TABLE core_kpi_assignments DROP FOREIGN KEY `{$fk_row['CONSTRAINT_NAME']}`");
        }
    }
    // Drop old unique key if exists
    $uk_res = $conn->query("SHOW INDEX FROM core_kpi_assignments WHERE Key_name='uq_emp_def_cycle'");
    if ($uk_res && $uk_res->num_rows > 0) {
        $conn->query("ALTER TABLE core_kpi_assignments DROP INDEX uq_emp_def_cycle");
    }
    // Rename column employee_id → user_id
    $conn->query("ALTER TABLE core_kpi_assignments CHANGE employee_id user_id INT NOT NULL COMMENT 'references users.id'");
    // Add new unique key
    $uk2 = $conn->query("SHOW INDEX FROM core_kpi_assignments WHERE Key_name='uq_user_def_cycle'");
    if ($uk2 && $uk2->num_rows === 0) {
        $conn->query("ALTER TABLE core_kpi_assignments ADD UNIQUE KEY uq_user_def_cycle (user_id, kpi_def_id, cycle_id)");
    }
    // Add FK to users if not already pointing there
    $new_fk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'core_kpi_assignments'
        AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users'");
    if ($new_fk && $new_fk->num_rows === 0) {
        $conn->query("ALTER TABLE core_kpi_assignments ADD CONSTRAINT fk_cka_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }
}

// --- core_kpi_results: Kết quả thực tế hàng tháng ---
$conn->query("CREATE TABLE IF NOT EXISTS core_kpi_results (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id   INT NOT NULL,
    year            SMALLINT NOT NULL,
    month           TINYINT NOT NULL COMMENT '1-12',
    actual_value    DECIMAL(14,2) DEFAULT NULL,
    achievement_pct DECIMAL(6,2) DEFAULT NULL COMMENT 'Tỷ lệ hoàn thành % (auto-calc)',
    score           DECIMAL(5,2) DEFAULT NULL COMMENT 'Điểm 0-100',
    note            TEXT,
    updated_by      INT DEFAULT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assign_ym (assignment_id, year, month),
    FOREIGN KEY (assignment_id) REFERENCES core_kpi_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migration for locking mechanism in core_kpi_results
$chk3 = $conn->query("SHOW COLUMNS FROM core_kpi_results LIKE 'is_locked'");
if ($chk3 && $chk3->num_rows === 0) {
    $conn->query("ALTER TABLE core_kpi_results ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER note");
}

// --- core_kpi_reviews: Đánh giá tổng thể cuối kỳ ---
$conn->query("CREATE TABLE IF NOT EXISTS core_kpi_reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    cycle_id       INT NOT NULL,
    overall_score  DECIMAL(5,2) DEFAULT NULL,
    rating         ENUM('A','B+','B','C+','C','D') DEFAULT NULL,
    comment_mgr    TEXT,
    comment_emp    TEXT,
    status         ENUM('draft','submitted','approved','rejected') DEFAULT 'draft',
    reviewed_by    INT DEFAULT NULL,
    reviewed_at    DATETIME DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_cycle (user_id, cycle_id),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id)    REFERENCES core_kpi_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Migration: rename employee_id → user_id in reviews if old column exists ──
$chk2 = $conn->query("SHOW COLUMNS FROM core_kpi_reviews LIKE 'employee_id'");
if ($chk2 && $chk2->num_rows > 0) {
    $fk_r2 = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'core_kpi_reviews'
        AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    if ($fk_r2) {
        while ($fkr = $fk_r2->fetch_assoc()) {
            $conn->query("ALTER TABLE core_kpi_reviews DROP FOREIGN KEY `{$fkr['CONSTRAINT_NAME']}`");
        }
    }
    $uk_r = $conn->query("SHOW INDEX FROM core_kpi_reviews WHERE Key_name='uq_emp_cycle'");
    if ($uk_r && $uk_r->num_rows > 0) {
        $conn->query("ALTER TABLE core_kpi_reviews DROP INDEX uq_emp_cycle");
    }
    $conn->query("ALTER TABLE core_kpi_reviews CHANGE employee_id user_id INT NOT NULL");
    $uk_r2 = $conn->query("SHOW INDEX FROM core_kpi_reviews WHERE Key_name='uq_user_cycle'");
    if ($uk_r2 && $uk_r2->num_rows === 0) {
        $conn->query("ALTER TABLE core_kpi_reviews ADD UNIQUE KEY uq_user_cycle (user_id, cycle_id)");
    }
    $new_fk2 = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'core_kpi_reviews'
        AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users'");
    if ($new_fk2 && $new_fk2->num_rows === 0) {
        $conn->query("ALTER TABLE core_kpi_reviews ADD CONSTRAINT fk_ckr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }
}

// Seed default KPI definitions nếu chưa có
$defCount = $conn->query("SELECT COUNT(*) AS c FROM core_kpi_definitions")->fetch_assoc()['c'];
if ($defCount == 0) {
    $seeds = [
        ['Hiệu suất công việc', 'Tỷ lệ hoàn thành công việc đúng hạn và đúng chất lượng', '%', 'maximize', 'KPI Cơ bản'],
        ['Chất lượng công việc', 'Số lỗi / sai sót phát sinh trong kỳ', 'lỗi', 'minimize', 'KPI Cơ bản'],
        ['Chủ động, sáng tạo', 'Số sáng kiến/cải tiến được đề xuất và áp dụng', 'ý tưởng', 'maximize', 'KPI Cơ bản'],
        ['Doanh thu', 'Doanh thu đóng góp trong kỳ', 'VNĐ', 'maximize', 'Kinh doanh'],
        ['Số khách hàng mới', 'Số khách hàng mới phát triển được', 'KH', 'maximize', 'Kinh doanh'],
        ['Tỷ lệ giữ chân KH', 'Customer retention rate', '%', 'maximize', 'Kinh doanh'],
        ['Utilization Rate', 'Tỷ lệ billable hours trên tổng giờ làm việc', '%', 'maximize', 'Vận hành'],
        ['Đào tạo hoàn thành', 'Số chứng chỉ / khóa đào tạo hoàn thành', 'chứng chỉ', 'maximize', 'Phát triển'],
        ['Phản hồi khách hàng (CSAT)', 'Chỉ số hài lòng khách hàng', 'điểm', 'maximize', 'Dịch vụ'],
        ['Tỷ lệ deliver đúng hạn', 'On-time delivery rate của dự án/task', '%', 'maximize', 'Dự án'],
    ];
    $ins = $conn->prepare("INSERT INTO core_kpi_definitions (kpi_name, description, default_unit, calc_type, category, sort_order) VALUES (?,?,?,?,?,?)");
    foreach ($seeds as $i => $s) {
        $ins->bind_param("sssssi", $s[0], $s[1], $s[2], $s[3], $s[4], $i);
        $ins->execute();
    }
}

// Seed default cycle nếu chưa có
$cycleCount = $conn->query("SELECT COUNT(*) AS c FROM core_kpi_cycles")->fetch_assoc()['c'];
if ($cycleCount == 0) {
    $year = date('Y');
    $quarters = [
        ['Q1/' . $year, $year, 1, $year . '-01-01', $year . '-03-31', 'closed'],
        ['Q2/' . $year, $year, 2, $year . '-04-01', $year . '-06-30', 'closed'],
        ['Q3/' . $year, $year, 3, $year . '-07-01', $year . '-09-30', 'active'],
        ['Q4/' . $year, $year, 4, $year . '-10-01', $year . '-12-31', 'planning'],
    ];
    $ins = $conn->prepare("INSERT IGNORE INTO core_kpi_cycles (name, year, quarter, start_date, end_date, status) VALUES (?,?,?,?,?,?)");
    foreach ($quarters as $q) {
        $ins->bind_param("sissss", $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]);
        $ins->execute();
    }
}
