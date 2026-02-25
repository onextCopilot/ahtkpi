<?php
// Called from kpi/index.php — $conn already exists
// Level 1: Annual KPI definitions
$conn->query("CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add sort_order to departments if missing
if ($conn->query("SHOW COLUMNS FROM departments LIKE 'sort_order'")->num_rows === 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN sort_order INT DEFAULT 0");
    // Init existing rows with their id as default order
    $conn->query("UPDATE departments SET sort_order = id WHERE sort_order = 0");
}
$conn->query("CREATE TABLE IF NOT EXISTS kpi_definitions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    year          SMALLINT NOT NULL DEFAULT 2025,
    department_id INT,
    kpi_group     VARCHAR(100),
    kpi_name      VARCHAR(255) NOT NULL,
    target_base   VARCHAR(255),
    unit          VARCHAR(50) DEFAULT NULL,
    weight        DECIMAL(5,2) DEFAULT 0,
    kpi_owner_id  INT,
    is_condition  TINYINT(1) DEFAULT 0,
    notes         TEXT,
    created_by    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (kpi_owner_id)  REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add unit column to kpi_definitions if missing
if ($conn->query("SHOW COLUMNS FROM kpi_definitions LIKE 'unit'")->num_rows === 0) {
    $conn->query("ALTER TABLE kpi_definitions ADD COLUMN unit VARCHAR(50) DEFAULT NULL AFTER target_base");
}

// Level 2: Quarterly targets
$conn->query("CREATE TABLE IF NOT EXISTS kpi_quarterly (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    kpi_def_id    INT NOT NULL,
    quarter       TINYINT NOT NULL COMMENT '1=Q1,2=Q2,3=Q3,4=Q4',
    year          SMALLINT NOT NULL,
    target_value  VARCHAR(255),
    weight_q      DECIMAL(5,2) DEFAULT 0 COMMENT 'weight for this quarter',
    status        ENUM('draft','active','completed','cancelled') DEFAULT 'draft',
    notes         TEXT,
    UNIQUE KEY uq_def_qy (kpi_def_id, quarter, year),
    FOREIGN KEY (kpi_def_id) REFERENCES kpi_definitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Level 3: Monthly actuals
$conn->query("CREATE TABLE IF NOT EXISTS kpi_monthly (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    kpi_def_id    INT NOT NULL,
    year          SMALLINT NOT NULL,
    month         TINYINT NOT NULL COMMENT '1-12',
    actual_value  VARCHAR(255),
    score         DECIMAL(5,2) COMMENT '0-100',
    updated_by    INT,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes         TEXT,
    UNIQUE KEY uq_def_ym (kpi_def_id, year, month),
    FOREIGN KEY (kpi_def_id) REFERENCES kpi_definitions(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Drop old kpi_items if empty (migration)
$old = $conn->query("SHOW TABLES LIKE 'kpi_items'");
if ($old && $old->num_rows > 0) {
    $cnt = $conn->query("SELECT COUNT(*) AS c FROM kpi_items")->fetch_assoc()['c'];
    if ($cnt == 0)
        $conn->query("DROP TABLE IF EXISTS kpi_items");
}

// KPI name templates (settings)
$conn->query("CREATE TABLE IF NOT EXISTS kpi_templates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    kpi_group  VARCHAR(100) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default templates if table is empty
$tplCount = $conn->query("SELECT COUNT(*) AS c FROM kpi_templates")->fetch_assoc()['c'];
if ($tplCount == 0) {
    $seeds = [
        ['Doanh thu BC', 'Tài chính'],
        ['EBT BC', 'Tài chính'],
        ['DT từ Key Accounts', 'Tài chính'],
        ['Gross Margin', 'Tài chính'],
        ['Lãi / 1 NS SX / tháng', 'Hiệu suất'],
        ['Utilization (billable)', 'Hiệu suất'],
        ['Dự án đúng ngân sách / tiến độ', 'Dự án'],
        ['SLA nhân sự / vendor', 'Vận hành'],
        ['DSO bình quân', 'Tài chính'],
        ['Attrition Key L1, L2', 'Nhân sự'],
    ];
    $ins = $conn->prepare("INSERT INTO kpi_templates (name, kpi_group, sort_order) VALUES (?,?,?)");
    foreach ($seeds as $i => $s) {
        $ins->bind_param("ssi", $s[0], $s[1], $i);
        $ins->execute();
    }
}
