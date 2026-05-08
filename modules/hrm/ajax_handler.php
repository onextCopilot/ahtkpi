<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Absolute path to config
require_once __DIR__ . '/../../config/config.php';
global $conn;

// Ensure connection is established
if (!isset($conn) || $conn->connect_error) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Auto-create/Update tables silently
$conn->query("CREATE TABLE IF NOT EXISTS hrm_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    manager VARCHAR(255),
    creators TEXT,
    followers TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_company_settings (
    id INT PRIMARY KEY DEFAULT 1,
    company_name VARCHAR(255),
    company_website VARCHAR(255),
    company_phone VARCHAR(50),
    company_address TEXT,
    recruit_title VARCHAR(255),
    recruit_url VARCHAR(255),
    recruit_desc TEXT,
    favicon VARCHAR(255),
    logo VARCHAR(255),
    sla_mode VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Initialize settings if empty
$checkSettings = $conn->query("SELECT COUNT(*) as count FROM hrm_company_settings");
if ($checkSettings) {
    $rowSettings = $checkSettings->fetch_assoc();
    if ($rowSettings && $rowSettings['count'] == 0) {
        $conn->query("INSERT INTO hrm_company_settings (id, company_name, company_website, company_phone, company_address, recruit_title, recruit_url, recruit_desc, sla_mode) VALUES 
            (1, 'AHT TECH JSC', 'https://www.arrowhitech.com', '(024)32025289', 'Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Yên Hòa, Quận Cầu Giấy, TP Hà Nội', 'AHT TECH JSC - Tuyển dụng', 'https://aht.talent.vn', 'tuyển dụng, AHT TECH JSC, hiring, talent, vn, candidate, ứng viên, hồ sơ, nộp đơn', 'Dựa trên giai đoạn')");
    }
}

// Initialize offices if empty
$checkOffices = $conn->query("SELECT COUNT(*) as count FROM hrm_offices");
if ($checkOffices) {
    $rowOffices = $checkOffices->fetch_assoc();
    if ($rowOffices && $rowOffices['count'] == 0) {
        $conn->query("INSERT INTO hrm_offices (name, address, sort_order) VALUES 
            ('AHT TECH HEAD OFFICE', 'Tầng 8, Tòa nhà MITEC, Lô E2, KĐTM Cầu Giấy, Phường Yên Hòa, Quận Cầu Giấy, TP Hà Nội', 1),
            ('AHT TECH - Văn phòng TP. Hồ Chí Minh', 'Tầng 7, Tòa nhà Jea Building, 112 Lê Chính Thống, Phường Xuân Hiệu, Thành Phố Hồ Chí Minh', 2),
            ('AHT Phú Thọ', 'Số 18 Ngõ 11, Đường Nguyễn Du, Phường Nông Trang, TP Việt Trì - Phú Thọ', 3),
            ('Remote/Hybrid', 'Remote/Hybrid', 4)");
    }
}

// Initialize departments if empty
$checkDepts = $conn->query("SELECT COUNT(*) as count FROM hrm_departments");
if ($checkDepts) {
    $rowDepts = $checkDepts->fetch_assoc();
    if ($rowDepts && $rowDepts['count'] == 0) {
        $conn->query("INSERT INTO hrm_departments (name, description, manager, sort_order) VALUES 
            ('Sales/Marketing', 'Phòng kinh doanh và Marketing', 'Phùng Thị Thu Hà', 1),
            ('Hành chính nhân sự', 'Phòng hành chính và quản trị nguồn nhân lực', 'Lê Thị Hạnh', 2),
            ('Phòng Kế toán', 'Quản lý tài chính và kế toán doanh nghiệp', '', 3),
            ('Phòng Sản xuất', 'Phòng phát triển phần mềm và sản xuất', '', 4),
            ('Phòng Công nghệ', 'Nghiên cứu và phát triển công nghệ mới', '', 5)");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS hrm_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, role)
)");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_candidate_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('internal', 'external') DEFAULT 'external',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_rejection_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reason_text VARCHAR(255) NOT NULL,
    reason_code VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sort_order INT DEFAULT 0
)");

$checkMandatory = $conn->query("SHOW COLUMNS FROM hrm_company_settings LIKE 'rejection_reason_mandatory'");
if ($checkMandatory && $checkMandatory->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN rejection_reason_mandatory TINYINT(1) DEFAULT 0");
}

$checkExpiredCols = $conn->query("SHOW COLUMNS FROM hrm_company_settings LIKE 'auto_close_expired'");
if ($checkExpiredCols && $checkExpiredCols->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN auto_close_expired TINYINT(1) DEFAULT 1");
    $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN auto_hide_expired TINYINT(1) DEFAULT 1");
    $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN email_before_expiry TINYINT(1) DEFAULT 1");
}

$conn->query("CREATE TABLE IF NOT EXISTS hrm_evaluation_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_evaluation_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT,
    criterion_text VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (group_id) REFERENCES hrm_evaluation_groups(id) ON DELETE CASCADE
)");

$checkGroups = $conn->query("SELECT COUNT(*) as count FROM hrm_evaluation_groups");
if ($checkGroups) {
    $rowG = $checkGroups->fetch_assoc();
    if ($rowG && $rowG['count'] == 0) {
        $groups = ['Khác', 'Kỹ năng chuyên môn', 'Kỹ năng mềm', 'Chưa phân loại'];
        foreach ($groups as $idx => $g) {
            $conn->query("INSERT INTO hrm_evaluation_groups (name, sort_order) VALUES ('$g', $idx)");
        }
        
        $gMap = [];
        $res = $conn->query("SELECT id, name FROM hrm_evaluation_groups");
        while($r = $res->fetch_assoc()) { $gMap[$r['name']] = $r['id']; }
        
        $criteria = [
            'Khác' => [
                'Có bằng cấp chứng chỉ liên quan',
                'Khả năng thích nghi với môi trường và văn hóa công ty',
                'Thái độ',
                'Tinh thần cầu tiến và sẵn sàng học hỏi'
            ],
            'Kỹ năng chuyên môn' => [
                'Android (Kotlin/ Java)', 'ASP.Net', 'Automation-Testing', 'Business Analyst (BA)',
                'Có cách tiếp cận ứng viên linh hoạt/sáng tạo', 'Có kinh nghiệm tuyển non-IT', 'Flutter',
                'HTML-CSS', 'iOS (Object-C/Swift)', 'Java-web', 'Khả năng chuyên môn nghiệp vụ về Pháp chế',
                'Kiến thức kỹ thuật liên quan đến dự án', 'Kinh nghiệm PM các dự án Outsourcing',
                'Kinh nghiệm Sales IT phù hợp', 'Kinh nghiệm sử dụng các tool Quản lý dự án (Jira, Asana, ...)',
                'Kinh nghiệm với các Recruitment tool/System', 'Kỹ năng chuyên môn về IT Helpdesk/IT Support',
                'Magento', 'Manual-Testing', 'Microservices', 'NodeJS', 'Odoo', 'PHP', 'PHP - Framework',
                'Python', 'ReactJS/Angular/Vuejs', 'React Native', 'Salesforce', 'Shopify',
                'Software Architect (SA)', 'Thực hiện quy trình tuyển dụng từ đầu tới cuối', 'UI/UX', 'Wordpress'
            ],
            'Kỹ năng mềm' => [
                'Kỹ năng giao tiếp', 'Kỹ năng làm việc nhóm', 'Kỹ năng quản lý', 'Network trong ngành IT', 'Ngoại ngữ (Tiếng Anh, Tiếng Nhật)'
            ],
            'Chưa phân loại' => [
                'Kinh nghiệm tuyển dụng các vị trí tương đương', 'Tư duy và kinh nghiệm về Process'
            ]
        ];
        
        foreach ($criteria as $gn => $clist) {
            $gid = $gMap[$gn];
            foreach ($clist as $cidx => $ctext) {
                $ctext_esc = $conn->real_escape_string($ctext);
                $conn->query("INSERT INTO hrm_evaluation_criteria (group_id, criterion_text, sort_order) VALUES ($gid, '$ctext_esc', $cidx)");
            }
        }
    }
}
$conn->query("CREATE TABLE IF NOT EXISTS hrm_job_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    job_code VARCHAR(100),
    template_id INT,
    department_id INT,
    office VARCHAR(255),
    salary_from DECIMAL(15,2),
    salary_to DECIMAL(15,2),
    currency VARCHAR(10) DEFAULT 'VND',
    show_salary TINYINT(1) DEFAULT 1,
    quantity INT,
    job_type VARCHAR(100),
    deadline DATE,
    job_description TEXT,
    talent_pool_id INT,
    managers TEXT,
    notes TEXT,
    completion_time VARCHAR(255),
    city VARCHAR(100),
    district VARCHAR(100),
    address TEXT,
    postal_code VARCHAR(20),
    status VARCHAR(50) DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$checkJobCreator = $conn->query("SHOW COLUMNS FROM hrm_job_posts LIKE 'created_by'");
if ($checkJobCreator && $checkJobCreator->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_job_posts ADD COLUMN created_by INT");
}

$conn->query("CREATE TABLE IF NOT EXISTS hrm_job_evaluation_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    evaluation_criterion_id INT NOT NULL,
    expected_score VARCHAR(10) DEFAULT '3/5',
    weight INT DEFAULT 1,
    FOREIGN KEY (job_id) REFERENCES hrm_job_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_criterion_id) REFERENCES hrm_evaluation_criteria(id) ON DELETE CASCADE
)");

// Check if column criterion_id exists and rename it to evaluation_criterion_id
$checkCol = $conn->query("SHOW COLUMNS FROM hrm_job_evaluation_criteria LIKE 'criterion_id'");
if ($checkCol && $checkCol->num_rows > 0) {
    $conn->query("ALTER TABLE hrm_job_evaluation_criteria CHANGE criterion_id evaluation_criterion_id INT NOT NULL");
}

$conn->query("CREATE TABLE IF NOT EXISTS hrm_job_mandatory_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    requirement_text TEXT NOT NULL,
    FOREIGN KEY (job_id) REFERENCES hrm_job_posts(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_talent_pools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_hiring_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100),
    sort_order INT DEFAULT 0,
    email_count INT DEFAULT 0,
    duration DECIMAL(10,2) DEFAULT 0.00,
    stage_type VARCHAR(50) DEFAULT 'standard', -- standard, offered, hired, rejected
    email_template_id INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure column stage_type exists for existing tables
$checkStageCol = $conn->query("SHOW COLUMNS FROM hrm_hiring_steps LIKE 'stage_type'");
if ($checkStageCol && $checkStageCol->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_hiring_steps ADD COLUMN stage_type VARCHAR(50) DEFAULT 'standard'");
    $conn->query("ALTER TABLE hrm_hiring_steps ADD COLUMN email_template_id INT DEFAULT 0");
}

$checkSteps = $conn->query("SELECT COUNT(*) as count FROM hrm_hiring_steps");
if ($checkSteps) {
    $rowSteps = $checkSteps->fetch_assoc();
    if ($rowSteps && $rowSteps['count'] == 0) {
        $conn->query("INSERT INTO hrm_hiring_steps (name, code, sort_order, stage_type) VALUES 
            ('Nhận hồ sơ', 'NHAN_HO_SO', 1, 'standard'),
            ('Sơ loại', 'SO_LOAI', 2, 'standard'),
            ('Phỏng vấn', 'PHONG_VAN', 3, 'standard'),
            ('Offered', 'OFFERED', 4, 'offered'),
            ('Hired', 'HIRED', 5, 'hired'),
            ('Rejected', 'REJECTED', 6, 'rejected')");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS hrm_proposal_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_type ENUM('recruitment', 'hiring') NOT NULL,
    approval_flow VARCHAR(50) DEFAULT 'sequential',
    role_priority VARCHAR(50) DEFAULT 'last',
    hrm_edit_after_approval TINYINT(1) DEFAULT 0,
    UNIQUE KEY (proposal_type)
)");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_proposal_approvers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_type ENUM('recruitment', 'hiring') NOT NULL,
    approver_type VARCHAR(50) NOT NULL,
    block_name VARCHAR(255),
    user_id INT,
    sla_hours INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_proposal_followers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_type ENUM('recruitment', 'hiring') NOT NULL,
    user_id INT NOT NULL,
    UNIQUE KEY (proposal_type, user_id)
)");

// Initialize proposal settings
$conn->query("INSERT IGNORE INTO hrm_proposal_settings (proposal_type) VALUES ('recruitment'), ('hiring')");

// Interview templates table
$conn->query("CREATE TABLE IF NOT EXISTS hrm_interview_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    interview_type VARCHAR(100) DEFAULT 'onsite',
    participants TEXT,
    location TEXT,
    email_subject VARCHAR(500),
    email_body LONGTEXT,
    questions LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Email templates table
$conn->query("CREATE TABLE IF NOT EXISTS hrm_email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email_subject VARCHAR(500),
    email_body LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    is_favorite TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_job_hiring_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    email_template_id INT DEFAULT 0,
    interview_template_id INT DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    stage_type VARCHAR(50) DEFAULT 'standard', -- standard, offered, hired, rejected
    duration INT DEFAULT 0,
    manual_review TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES hrm_job_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Ensure column stage_type exists for existing job steps table
$checkJobStageCol = $conn->query("SHOW COLUMNS FROM hrm_job_hiring_steps LIKE 'stage_type'");
if ($checkJobStageCol && $checkJobStageCol->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_job_hiring_steps ADD COLUMN stage_type VARCHAR(50) DEFAULT 'standard'");
}
$checkDurCol = $conn->query("SHOW COLUMNS FROM hrm_job_hiring_steps LIKE 'duration'");
if ($checkDurCol && $checkDurCol->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_job_hiring_steps ADD COLUMN duration INT DEFAULT 0");
    $conn->query("ALTER TABLE hrm_job_hiring_steps ADD COLUMN manual_review TINYINT(1) DEFAULT 0");
}

$conn->query("CREATE TABLE IF NOT EXISTS hrm_job_application_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    is_show TINYINT(1) DEFAULT 1,
    is_required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (job_id) REFERENCES hrm_job_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_job_custom_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type VARCHAR(50) DEFAULT 'text',
    options TEXT,
    is_required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (job_id) REFERENCES hrm_job_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Safe column addition
$cols = $conn->query("SHOW COLUMNS FROM hrm_company_settings");
$existing_cols = [];
while($c = $cols->fetch_assoc()) { $existing_cols[] = $c['Field']; }

if (!in_array('require_job_code', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN require_job_code TINYINT(1) DEFAULT 0");
if (!in_array('evaluation_method', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN evaluation_method VARCHAR(50) DEFAULT 'general'");
if (!in_array('auto_create_from_email', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN auto_create_from_email TINYINT(1) DEFAULT 0");
if (!in_array('min_delete_permission', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN min_delete_permission VARCHAR(50) DEFAULT 'admin'");
if (!in_array('min_export_permission', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN min_export_permission VARCHAR(50) DEFAULT 'admin'");
if (!in_array('enable_captcha', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN enable_captcha TINYINT(1) DEFAULT 0");
if (!in_array('email_interview_invitation', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN email_interview_invitation INT DEFAULT 0");
if (!in_array('email_interview_update', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN email_interview_update INT DEFAULT 0");
if (!in_array('email_interview_cancel', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN email_interview_cancel INT DEFAULT 0");
if (!in_array('email_interview_bulk', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN email_interview_bulk INT DEFAULT 0");
if (!in_array('interview_cv_display', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN interview_cv_display VARCHAR(50) DEFAULT 'restricted'");
if (!in_array('onboard_integration_permission', $existing_cols)) $conn->query("ALTER TABLE hrm_company_settings ADD COLUMN onboard_integration_permission VARCHAR(50) DEFAULT 'manager'");

$checkApproverMeta = $conn->query("SHOW COLUMNS FROM hrm_proposal_approvers LIKE 'metadata'");
if ($checkApproverMeta && $checkApproverMeta->num_rows == 0) {
    $conn->query("ALTER TABLE hrm_proposal_approvers ADD COLUMN metadata TEXT");
}

// ── Candidate & Application Tables ──────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS hrm_candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    avatar VARCHAR(255),
    address TEXT,
    gender VARCHAR(20),
    dob DATE,
    source_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    job_id INT NOT NULL,
    current_step_id INT,
    status VARCHAR(50) DEFAULT 'active',
    rating INT DEFAULT 0,
    owner_id INT,
    rejection_reason_id INT,
    rejection_note TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES hrm_candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES hrm_job_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Ensure extra columns for extended candidate list
$candCols = $conn->query("SHOW COLUMNS FROM hrm_candidates");
$existingCand = []; while($c = $candCols->fetch_assoc()) { $existingCand[] = $c['Field']; }
if (!in_array('reference_contact', $existingCand)) $conn->query("ALTER TABLE hrm_candidates ADD COLUMN reference_contact TEXT");

$appCols = $conn->query("SHOW COLUMNS FROM hrm_applications");
$existingApp = []; while($c = $appCols->fetch_assoc()) { $existingApp[] = $c['Field']; }
if (!in_array('candidate_type', $existingApp)) $conn->query("ALTER TABLE hrm_applications ADD COLUMN candidate_type VARCHAR(100)");
if (!in_array('campaign', $existingApp)) $conn->query("ALTER TABLE hrm_applications ADD COLUMN campaign VARCHAR(255)");
if (!in_array('medium', $existingApp)) $conn->query("ALTER TABLE hrm_applications ADD COLUMN medium VARCHAR(255)");
if (!in_array('interview_date', $existingApp)) $conn->query("ALTER TABLE hrm_applications ADD COLUMN interview_date DATETIME");
if (!in_array('email_tracking_status', $existingApp)) $conn->query("ALTER TABLE hrm_applications ADD COLUMN email_tracking_status VARCHAR(100)");
if (!in_array('last_email_sent_at', $existingApp)) $conn->query("ALTER TABLE hrm_applications ADD COLUMN last_email_sent_at DATETIME");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_application_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    user_id INT,
    action_type VARCHAR(100),
    note TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES hrm_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_application_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    file_name VARCHAR(255),
    file_path TEXT,
    file_type VARCHAR(50),
    file_size INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES hrm_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$attCols = $conn->query("SHOW COLUMNS FROM hrm_application_attachments");
$existingAtt = []; while($c = $attCols->fetch_assoc()) { $existingAtt[] = $c['Field']; }
if (!in_array('file_size', $existingAtt)) $conn->query("ALTER TABLE hrm_application_attachments ADD COLUMN file_size INT DEFAULT 0");

$conn->query("CREATE TABLE IF NOT EXISTS hrm_application_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    tag_name VARCHAR(100),
    FOREIGN KEY (application_id) REFERENCES hrm_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = $_GET['action'] ?? '';
$response = null;

error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    $response = ['success' => false, 'message' => 'Unauthorized'];
} else {
    // Ensure upload directory exists and is writable
    $uploadDir = __DIR__ . '/../../uploads/hrm/cvs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    if (is_dir($uploadDir) && !is_writable($uploadDir)) {
        @chmod($uploadDir, 0777);
    }

    if ($action === 'get_depts') {
        $result = $conn->query("SELECT * FROM hrm_departments ORDER BY sort_order ASC, id DESC");
        $depts = [];
        if ($result) { while ($row = $result->fetch_assoc()) { $depts[] = $row; } }
        $response = $depts;
    } elseif ($action === 'get_offices') {
        $result = $conn->query("SELECT * FROM hrm_offices ORDER BY sort_order ASC, id DESC");
        $offices = [];
        if ($result) { while ($row = $result->fetch_assoc()) { $offices[] = $row; } }
        $response = $offices;
    } elseif ($action === 'get_job_steps') {
        $job_id = (int)($_GET['job_id'] ?? 0);
        $res = $conn->query("SELECT id, name FROM hrm_job_hiring_steps WHERE job_id = $job_id ORDER BY sort_order ASC");
        $steps = [];
        while($row = $res->fetch_assoc()) { $steps[] = $row; }
        $response = $steps;
    } elseif ($action === 'get_users') {
        $res = $conn->query("SELECT id, full_name, avatar FROM users ORDER BY full_name ASC");
        $users = [];
        while($row = $res->fetch_assoc()) { $users[] = $row; }
        $response = $users;
    } elseif ($action === 'get_candidate_sources') {
        $res = $conn->query("SELECT * FROM hrm_candidate_sources ORDER BY sort_order ASC, id ASC");
        $data = [];
        while($row = $res->fetch_assoc()) { $data[] = $row; }
        $response = $data;
    } elseif ($action === 'get_all_tags') {
        $res = $conn->query("SELECT DISTINCT tag_name FROM hrm_application_tags ORDER BY tag_name ASC");
        $tags = [];
        while($row = $res->fetch_assoc()) { $tags[] = $row['tag_name']; }
        $response = $tags;
    } elseif ($action === 'save_candidate_source' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $name = $conn->real_escape_string($data['name']);
        $type = $conn->real_escape_string($data['type'] ?? 'external');
        $is_active = (int)($data['is_active'] ?? 1);
        if ($id) { $sql = "UPDATE hrm_candidate_sources SET name='$name', type='$type', is_active=$is_active WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_candidate_sources (name, type, is_active) VALUES ('$name', '$type', $is_active)"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_candidate_source' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        if ($conn->query("DELETE FROM hrm_candidate_sources WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'seed_sources') {
        $conn->query("TRUNCATE TABLE hrm_candidate_sources");
        $raw_sources = [
            'ADJOB', 'THREADS', 'METWORKING', 'SKYPE', 'LLINKEDIN', 'NO', 'WEB-TRAINING-AHT', 'BAN', 'GETBEE', 'NETWORKINH', 'ANDROID', 'FORUM', 'VIRTUAL-CAREER-FAIR-2021', 'JAVA', 'GITHUB', 'LINKDIN', 'TESTER', 'HTTPSWWW.FACEBOOK.COMPROFILE.PHPID100013392615640', 'SKYPE-TOMCLANCY1234', 'HTTPSWWW.FACEBOOK.COMELDESPERADO305', 'HTTPSWWW.FACEBOOK.COMCONGNTIT', 'LINKEIN', 'LUONGNT-REFER', 'WEB-ONNET', 'RECO', 'LINHEB', 'HTTPSWWW.FACEBOOK.COMEUGENE.NGUYEN.XB', 'NW', 'CAREER-LINK', 'FACEBOOK.-LUONGNT', 'ACCOUNT', 'RYTHEMYGMAIL.COM', 'SALES', 'BD', 'NULO-2022', 'NGALT', 'GMAIL', 'REFER-NETWORKING', 'SALES-IT', 'THAOPT', 'CL', 'APTECH', 'DH-FPT', 'DHCNHN', 'DHBK-TOPCV', 'DHCNHN-TOPCV', 'CD-CONG-NGHE-THUONG-MAI', 'DH-SU-HAM-HN', 'DH-MO-HN', 'WORKVN', 'T3H', 'NETWRKING', 'TOPCV-FACEBOOK', 'FAECEBOOK', 'AGENCY-WATAJOB', 'NETWOKING', 'NGOCBTT,SALES-IT', 'TOP-CV', 'REFER-HOANG-ANH-LEAD', 'NETWORL', 'HEADHUNT-GETBEE', 'MAIL-HR', 'REFERAL', 'WEBINAR', 'TOPCV-APPLY', 'PAGE', 'VN', 'NETWROK', 'REFER-CHINH-ASIA', 'ITO', 'PHUONGDM-REFER', 'GLINT', 'FACKFRUIT', 'HEADHUNTER', 'HEADHUNT,JACKFRUIT', 'FPT-POLYTECHNIC', 'DHHN', 'QHDN', 'TRANGLD', 'FACEBOOK,TOPCV', 'HUNTER-GLINTS', 'VIETNAMWORK', 'VNWS', 'TOPCV,ACCOUNT', 'PREFER-NOI-BO', 'NGOCBTT', 'VNW', 'REFER-JESSIE', 'PAGE-AHT', 'MAIL', 'USTH', 'FACEBOOK,LINKEDIN', 'BEHANCE', 'REFER-UYEN-QA', 'REFER-OHIO', 'NETWORING', 'WATAJOB', 'DEVWORK', 'JACKFRUIT', 'LINH-EB', 'OHIO-JESSIE', 'DUNGIC', 'FACEBOOK,LINHEB', 'FACEBOOOK', 'OHIO', 'ONNET', 'REFER-HUYEN-MINH', 'DH-CNHN', 'GREENWICH', 'DH-DIEN-LUC', 'GOOGLE_JOBS_APPLY', 'PTIT', 'REFER-PHUONGDM', 'MAILHR', 'NGALT-ANGULAR', 'ZALO', 'VIEN-NGOAI-NGU-DH-BK-HN', 'FB', 'NGALT,LINKEDIN', 'SHARECV', 'LUONGNT', 'HEADHUNT', 'HRMAIL', 'DEVPRO', 'PHUONGDM', 'REFER-LUONGNT', 'LINKEIDN', 'REFER-NOI-BO', 'REFER', 'HAUI', 'NETWORK', 'DH-CNHN,UPLOAD', 'WEBSITE,UPLOAD', 'NETWORKING', 'VIECLAM123', 'JOBOKO', 'YBOX', 'TIMVIEC365', '123JOB', 'INDEED', 'VIECTOTNHAT', 'TOPDEV', 'MYWORK', 'TOPCV', 'CAREERLINK', 'CAREERBUILDER.VN', 'JOBSTREET.VN', 'VIETNAMWORKS', 'TIMVIECNHANH.COM', 'JOBSGO', 'VIECLAM24H', 'ITVIEC.COM', 'TALENT POOL', 'EMAIL', 'UPLOAD', 'RECRUITER', 'REFERRAL', 'LINKEDIN', 'FACEBOOK', 'WEBSITE', 'OTHER'
        ];
        
        $sources = array_unique($raw_sources);
        $idx = 1;
        foreach ($sources as $name) {
            $type = (strpos(strtolower($name), 'refer') !== false || strpos(strtolower($name), 'noi-bo') !== false || strpos(strtolower($name), 'website') !== false) ? 'internal' : 'external';
            $esc_name = $conn->real_escape_string($name);
            $conn->query("INSERT INTO hrm_candidate_sources (name, type, sort_order) VALUES ('$esc_name', '$type', $idx)");
            $idx++;
        }
        $response = ['success' => true, 'message' => 'Seed complete with ' . count($sources) . ' items'];
    } elseif ($action === 'update_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? '';
        $order = $data['order'] ?? [];
        if ($type === 'sources') {
            foreach ($order as $item) {
                $id = (int)$item['id']; $sort = (int)$item['sort_order'];
                $conn->query("UPDATE hrm_candidate_sources SET sort_order=$sort WHERE id=$id");
            }
            $response = ['success' => true];
        } else { $response = ['success' => false, 'message' => 'Invalid type']; }
    } elseif ($action === 'get_candidates') {
        $search = $conn->real_escape_string($_GET['search'] ?? '');
        $job_id = (int)($_GET['job_id'] ?? 0);
        $source_id = (int)($_GET['source_id'] ?? 0);
        $owner_id = (int)($_GET['owner_id'] ?? 0);
        $tag = $conn->real_escape_string($_GET['tag'] ?? '');
        $date_from = $conn->real_escape_string($_GET['date_from'] ?? '');
        $date_to = $conn->real_escape_string($_GET['date_to'] ?? '');
        $sort = $_GET['sort'] ?? 'newest';
        $status = $conn->real_escape_string($_GET['status'] ?? 'all');
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $where = "WHERE 1=1";
        if (!empty($search)) {
            $where .= " AND (c.full_name LIKE '%$search%' OR c.email LIKE '%$search%' OR c.phone LIKE '%$search%')";
        }
        if ($job_id > 0) $where .= " AND a.job_id = $job_id";
        if ($source_id > 0) $where .= " AND c.source_id = $source_id";
        if ($owner_id > 0) $where .= " AND a.owner_id = $owner_id";
        if ($status !== 'all') $where .= " AND a.status = '$status'";
        if (!empty($tag)) {
            $where .= " AND EXISTS (SELECT 1 FROM hrm_application_tags WHERE application_id = a.id AND tag_name = '$tag')";
        }
        if (!empty($date_from)) $where .= " AND a.applied_at >= '$date_from 00:00:00'";
        if (!empty($date_to)) $where .= " AND a.applied_at <= '$date_to 23:59:59'";

        $orderBy = "ORDER BY a.applied_at DESC";
        if ($sort === 'oldest') $orderBy = "ORDER BY a.applied_at ASC";
        if ($sort === 'rating') $orderBy = "ORDER BY a.rating DESC, a.applied_at DESC";

        // Fetch candidates with primary info
        $sql = "SELECT a.id as application_id, a.status, a.rating, a.applied_at, a.owner_id, a.job_id, a.current_step_id,
                       a.candidate_type, a.campaign, a.medium, a.interview_date, a.email_tracking_status, a.last_email_sent_at, a.updated_at as last_updated,
                       a.rejection_note,
                       c.id as candidate_id, c.full_name, c.email, c.phone, c.avatar, c.reference_contact,
                       j.title as job_title, j.office as job_office,
                       s.name as step_name, s.stage_type, s.sort_order as curr_step_order,
                       src.name as source_name,
                       u.full_name as owner_name, u.avatar as owner_avatar,
                       (SELECT COUNT(*) FROM hrm_job_hiring_steps WHERE job_id = a.job_id) as total_steps,
                       (SELECT GROUP_CONCAT(tag_name) FROM hrm_application_tags WHERE application_id = a.id) as tags_str
                FROM hrm_applications a
                JOIN hrm_candidates c ON a.candidate_id = c.id
                LEFT JOIN hrm_job_posts j ON a.job_id = j.id
                LEFT JOIN hrm_job_hiring_steps s ON a.current_step_id = s.id
                LEFT JOIN hrm_candidate_sources src ON c.source_id = src.id
                LEFT JOIN users u ON a.owner_id = u.id
                $where $orderBy LIMIT $limit OFFSET $offset";

        $res = $conn->query($sql);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['tags'] = $row['tags_str'] ? explode(',', $row['tags_str']) : [];
                $row['progress_index'] = (int)$row['curr_step_order'] + 1;
                $row['total_steps'] = (int)$row['total_steps'];
                unset($row['tags_str'], $row['curr_step_order']);
                $data[] = $row;
            }
        }

        $totalCount = $conn->query("SELECT COUNT(*) FROM hrm_applications a JOIN hrm_candidates c ON a.candidate_id = c.id $where")->fetch_row()[0];
        
        $response = ['success' => true, 'data' => $data, 'total' => $totalCount, 'page' => $page, 'limit' => $limit];

    } elseif ($action === 'get_candidate_stats') {
        $job_id = (int)($_GET['job_id'] ?? 0);
        $where = "WHERE 1=1";
        if ($job_id > 0) $where .= " AND a.job_id = $job_id";

        $total = $conn->query("SELECT COUNT(*) FROM hrm_applications a $where")->fetch_row()[0];
        $active = $conn->query("SELECT COUNT(*) FROM hrm_applications a $where AND status='active'")->fetch_row()[0];
        $hired = $conn->query("SELECT COUNT(*) FROM hrm_applications a $where AND status='hired'")->fetch_row()[0];
        $rejected = $conn->query("SELECT COUNT(*) FROM hrm_applications a $where AND status='rejected'")->fetch_row()[0];

        $response = ['total' => $total, 'active' => $active, 'hired' => $hired, 'rejected' => $rejected];

    } elseif ($action === 'bulk_move_stage') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];
        $step_id = (int)($data['step_id'] ?? 0);
        if (empty($ids) || $step_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit;
        }
        $ids_str = implode(',', array_map('intval', $ids));
        $conn->query("UPDATE hrm_applications SET current_step_id = $step_id, status = 'active' WHERE id IN ($ids_str)");
        
        // Log activities
        foreach ($ids as $id) {
            $conn->query("INSERT INTO hrm_application_activities (application_id, user_id, action_type, note) 
                          VALUES ($id, {$_SESSION['user_id']}, 'move_stage', 'Di chuyển hàng loạt sang bước mới')");
        }
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'update_rating' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $rating = (int)$data['rating'];
        if ($conn->query("UPDATE hrm_applications SET rating=$rating WHERE id=$aid")) {
            $conn->query("INSERT INTO hrm_application_activities (application_id, action_type, note) VALUES ($aid, 'update_rating', 'Đã cập nhật đánh giá: $rating sao')");
            $response = ['success' => true];
        } else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'add_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $name = $conn->real_escape_string($data['tag_name']);
        $conn->query("INSERT IGNORE INTO hrm_application_tags (application_id, tag_name) VALUES ($aid, '$name')");
        $response = ['success' => true];
    } elseif ($action === 'remove_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $name = $conn->real_escape_string($data['tag_name']);
        $conn->query("DELETE FROM hrm_application_tags WHERE application_id = $aid AND tag_name = '$name'");
        $response = ['success' => true];
    } elseif ($action === 'bulk_reject') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];
        $reason = $conn->real_escape_string($data['reason'] ?? '');
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit;
        }
        $ids_str = implode(',', array_map('intval', $ids));
        $conn->query("UPDATE hrm_applications SET status = 'rejected' WHERE id IN ($ids_str)");
        
        foreach ($ids as $id) {
            $conn->query("INSERT INTO hrm_application_activities (application_id, user_id, action_type, note) 
                          VALUES ($id, {$_SESSION['user_id']}, 'reject', 'Từ chối hàng loạt: $reason')");
        }
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'bulk_delete') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit;
        }
        $ids_str = implode(',', array_map('intval', $ids));
        $conn->query("DELETE FROM hrm_applications WHERE id IN ($ids_str)");
        // Note: In real app, we might want to keep history, but here we follow delete request.
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'update_rating' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $rating = (int)$data['rating'];
        $conn->query("UPDATE hrm_applications SET rating = $rating WHERE id = $aid");
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'add_activity' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $note = $conn->real_escape_string($data['note']);
        $type = $conn->real_escape_string($data['type'] ?? 'comment');
        $conn->query("INSERT INTO hrm_application_activities (application_id, user_id, action_type, note) 
                      VALUES ($aid, {$_SESSION['user_id']}, '$type', '$note')");
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'move_stage' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $step_id = (int)$data['step_id'];
        $conn->query("UPDATE hrm_applications SET current_step_id = $step_id, status = 'active' WHERE id = $aid");
        $conn->query("INSERT INTO hrm_application_activities (application_id, user_id, action_type, note) 
                      VALUES ($aid, {$_SESSION['user_id']}, 'move_stage', 'Đã chuyển sang bước tuyển dụng mới')");
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'reject_candidate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $aid = (int)$data['application_id'];
        $reason = $conn->real_escape_string($data['reason'] ?? '');
        $conn->query("UPDATE hrm_applications SET status = 'rejected' WHERE id = $aid");
        $conn->query("INSERT INTO hrm_application_activities (application_id, user_id, action_type, note) 
                      VALUES ($aid, {$_SESSION['user_id']}, 'reject', 'Từ chối hồ sơ. Lý do: $reason')");
        echo json_encode(['success' => true]); exit;

    } elseif ($action === 'upload_import_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['file'])) { echo json_encode(['success' => false, 'message' => 'Không có file nào được tải lên']); exit; }
        $tempDir = __DIR__ . '/../../uploads/hrm/temp/';
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
        
        $fileName = 'import_' . time() . '_' . $_FILES['file']['name'];
        $dest = $tempDir . $fileName;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            echo json_encode(['success' => true, 'file_path' => $fileName]); exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu file tạm']); exit;
        }

    } elseif ($action === 'get_import_total') {
        $fileName = $_GET['file'] ?? '';
        $file = __DIR__ . '/../../uploads/hrm/temp/' . $fileName;
        if (empty($fileName) || !file_exists($file)) { echo json_encode(['success' => false, 'message' => 'File không tồn tại: ' . $fileName]); exit; }
        
        $zip = new ZipArchive();
        if ($zip->open($file) !== TRUE) { echo json_encode(['success' => false, 'message' => 'Không thể mở file Excel']); exit; }
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $totalRows = count($xml->sheetData->row) - 3; // Subtract headers
        echo json_encode(['success' => true, 'total' => max(0, $totalRows)]); exit;

    } elseif ($action === 'import_candidates') {
        set_time_limit(300);
        $start = (int)($_GET['start'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 20);
        $fileName = $_GET['file'] ?? '';
        $file = __DIR__ . '/../../uploads/hrm/temp/' . $fileName;

        if (empty($fileName) || !file_exists($file)) { echo json_encode(['success' => false, 'message' => 'File không tồn tại']); exit; }
        $zip = new ZipArchive();
        if ($zip->open($file) !== TRUE) { echo json_encode(['success' => false, 'message' => 'Không thể mở file Excel']); exit; }

        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $xmlSS = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
            foreach ($xmlSS->si as $si) { $sharedStrings[] = (string)($si->t ?: $si->r->t); }
        }

        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $count = 0;
        $rowIdx = 0;
        $processed = 0;
        
        foreach ($xml->sheetData->row as $row) {
            if ($rowIdx < 3) { $rowIdx++; continue; } // Skip headers
            
            // Handle Start/Limit
            if ($processed < $start) { $processed++; $rowIdx++; continue; }
            if ($processed >= $start + $limit) break;

            $cols = [];
            foreach ($row->c as $c) {
                $r = (string)$c['r']; 
                $colLetter = preg_replace('/[0-9]/', '', $r);
                $val = (string)$c->v;
                if ((string)$c['t'] == 's') { $val = $sharedStrings[$val] ?? $val; }
                $cols[$colLetter] = trim($val);
            }

            // Mapping based on column letters (A=0, B=1, C=2, D=3, E=4, F=5, G=6, H=7, I=8...)
            // Col E (4) = Name, F (5) = Email, G (6) = Phone, I (8) = Source, D (3) = Job Title, AB (27) = CV URL
            $name = $conn->real_escape_string($cols['E'] ?? '');
            $email = $conn->real_escape_string($cols['F'] ?? '');
            $phone = $conn->real_escape_string($cols['G'] ?? '');
            $source_name = $conn->real_escape_string($cols['I'] ?? 'Other');
            $job_title = $conn->real_escape_string($cols['D'] ?? '');
            $raw_cv_url = $cols['AB'] ?? '';
            $cv_url = $conn->real_escape_string($raw_cv_url);

            if (empty($name) || empty($email)) continue;

            // 1. Ensure Source exists
            $srcRes = $conn->query("SELECT id FROM hrm_candidate_sources WHERE name = '$source_name'");
            if ($srcRes && $srcRow = $srcRes->fetch_assoc()) {
                $source_id = $srcRow['id'];
            } else {
                $conn->query("INSERT INTO hrm_candidate_sources (name, type) VALUES ('$source_name', 'external')");
                $source_id = $conn->insert_id;
            }

            // 2. Ensure Candidate exists
            $candRes = $conn->query("SELECT id FROM hrm_candidates WHERE email = '$email'");
            if ($candRes && $candRow = $candRes->fetch_assoc()) {
                $candidate_id = $candRow['id'];
            } else {
                $conn->query("INSERT INTO hrm_candidates (full_name, email, phone, source_id) VALUES ('$name', '$email', '$phone', $source_id)");
                $candidate_id = $conn->insert_id;
            }

            // 3. Find Job ID
            $job_id = 0;
            $job_office = '';
            if (!empty($job_title)) {
                $jobRes = $conn->query("SELECT id, office FROM hrm_job_posts WHERE title LIKE '%$job_title%' LIMIT 1");
                if ($jobRes && $jRow = $jobRes->fetch_assoc()) { 
                    $job_id = $jRow['id']; 
                    $job_office = $jRow['office'];
                }
            }

            // Extended fields from Excel (Assume some columns or just use defaults for now)
            $campaign = $conn->real_escape_string($cols['H'] ?? ''); // Campaign
            $medium = $conn->real_escape_string($cols['K'] ?? ''); // Medium
            $candidate_type = 'External';

            // 4. Create or Update Application
            $appCheck = $conn->query("SELECT id FROM hrm_applications WHERE candidate_id = $candidate_id AND job_id = $job_id");
            if ($appCheck->num_rows == 0) {
                $conn->query("INSERT INTO hrm_applications (candidate_id, job_id, status, campaign, medium, candidate_type) 
                              VALUES ($candidate_id, $job_id, 'active', '$campaign', '$medium', '$candidate_type')");
                $application_id = $conn->insert_id;
                $count++;
            } else {
                $appRow = $appCheck->fetch_assoc();
                $application_id = $appRow['id'];
                // Update existing record with new metadata if needed
                $conn->query("UPDATE hrm_applications SET campaign='$campaign', medium='$medium' WHERE id=$application_id");
            }

            // Check if we have a LOCAL CV file already
            $localCheck = $conn->query("SELECT id FROM hrm_application_attachments WHERE application_id = $application_id AND file_path LIKE '/uploads%'");
            if ($localCheck->num_rows == 0 && !empty($raw_cv_url)) {
                $fileExt = pathinfo(parse_url($raw_cv_url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'pdf';
                $localFileName = 'cv_app_' . $application_id . '.' . $fileExt;
                $localFilePath = $uploadDir . $localFileName;

                if (file_exists($localFilePath)) {
                    // File already exists on disk
                    $fileSize = filesize($localFilePath);
                    $dbPath = '/uploads/hrm/cvs/' . $localFileName;
                    $conn->query("INSERT INTO hrm_application_attachments (application_id, file_name, file_path, file_size) 
                                  VALUES ($application_id, 'CV_Imported.".$fileExt."', '$dbPath', $fileSize)");
                } else {
                    // Use CURL to bypass simple blocks
                    $ch = curl_init($raw_cv_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    $fileData = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    if ($fileData && $httpCode == 200) {
                        $fileSize = strlen($fileData);
                        if (@file_put_contents($localFilePath, $fileData)) {
                            $dbPath = '/uploads/hrm/cvs/' . $localFileName;
                            $conn->query("INSERT INTO hrm_application_attachments (application_id, file_name, file_path, file_size) 
                                          VALUES ($application_id, 'CV_Imported.".$fileExt."', '$dbPath', $fileSize)");
                        }
                    }
                }
            }
            $processed++;
            $rowIdx++;
        }
        echo json_encode(['success' => true, 'message' => "Đã import thành công $count ứng viên"]); exit;

    } elseif ($action === 'get_candidate_detail') {
        $aid = (int)$_GET['application_id'];
        $sql = "SELECT a.id as application_id, a.job_id, a.candidate_id, a.status, a.applied_at, a.owner_id, a.current_step_id, a.rating,
                       c.full_name, c.email, c.phone, c.avatar, c.address, c.gender, c.dob,
                       j.title as job_title, j.job_code,
                       s.name as step_name, s.stage_type,
                       src.name as source_name,
                       u.full_name as owner_name, u.avatar as owner_avatar
                FROM hrm_applications a
                JOIN hrm_candidates c ON a.candidate_id = c.id
                LEFT JOIN hrm_job_posts j ON a.job_id = j.id
                LEFT JOIN hrm_job_hiring_steps s ON a.current_step_id = s.id
                LEFT JOIN hrm_candidate_sources src ON c.source_id = src.id
                LEFT JOIN users u ON a.owner_id = u.id
                WHERE a.id = $aid";
        
        $res = $conn->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            // Activities
            $actRes = $conn->query("SELECT act.*, u.full_name as user_name, u.avatar as user_avatar 
                                    FROM hrm_application_activities act 
                                    LEFT JOIN users u ON act.user_id = u.id 
                                    WHERE act.application_id = $aid ORDER BY act.created_at DESC");
            $activities = [];
            while($ar = $actRes->fetch_assoc()) { $activities[] = $ar; }
            $row['activities'] = $activities;

            // Attachments
            $attRes = $conn->query("SELECT * FROM hrm_application_attachments WHERE application_id = $aid");
            $attachments = [];
            while($attr = $attRes->fetch_assoc()) { $attachments[] = $attr; }
            $row['attachments'] = $attachments;

            // Tags
            $tagRes = $conn->query("SELECT tag_name FROM hrm_application_tags WHERE application_id = $aid");
            $tags = [];
            while($tr = $tagRes->fetch_assoc()) { $tags[] = $tr['tag_name']; }
            $row['tags'] = $tags;

            // Job Criteria for Evaluation
            $jid = (int)$row['job_id'];
            $critRes = $conn->query("SELECT jc.*, c.criterion_text, g.name as group_name 
                                     FROM hrm_job_evaluation_criteria jc 
                                     JOIN hrm_evaluation_criteria c ON jc.evaluation_criterion_id = c.id 
                                     JOIN hrm_evaluation_groups g ON c.group_id = g.id 
                                     WHERE jc.job_id = $jid ORDER BY g.id, c.id");
            $criteria = [];
            while($cr = $critRes->fetch_assoc()) { $criteria[] = $cr; }
            $row['job_criteria'] = $criteria;

            $response = ['success' => true, 'data' => $row];
        } else {
            $response = ['success' => false, 'message' => 'Candidate not found'];
        }

    } elseif ($action === 'seed_test_candidates') {
        $names = ['Nguyễn Văn A', 'Trần Thị B', 'Lê Văn C', 'Phạm Minh D', 'Hoàng Anh E', 'Đỗ Thị F', 'Bùi Văn G', 'Vũ Thị H', 'Lý Minh I', 'Đặng Văn K'];
        $emails = ['vana@gmail.com', 'thib@yahoo.com', 'vanc@outlook.com', 'minhd@aht.tech', 'anhe@gmail.com', 'thif@hotmail.com', 'vang@gmail.com', 'thih@aht.tech', 'minhi@gmail.com', 'vank@gmail.com'];
        $phones = ['0987654321', '0912345678', '0909090909', '0888888888', '0777777777', '0666666666', '0555555555', '0444444444', '0333333333', '0222222222'];
        
        $jobsRes = $conn->query("SELECT id FROM hrm_job_posts LIMIT 3");
        $jobIds = [];
        while($rj = $jobsRes->fetch_assoc()) { $jobIds[] = $rj['id']; }
        
        if (empty($jobIds)) {
             $response = ['success' => false, 'message' => 'No jobs found. Please create a job post first.'];
        } else {
            foreach ($names as $idx => $name) {
                $email = $emails[$idx];
                $phone = $phones[$idx];
                $jid = $jobIds[array_rand($jobIds)];
                
                $conn->query("INSERT INTO hrm_candidates (full_name, email, phone, source_id) VALUES ('$name', '$email', '$phone', " . rand(1, 10) . ")");
                $cid = $conn->insert_id;
                
                $stepRes = $conn->query("SELECT id FROM hrm_job_hiring_steps WHERE job_id = $jid ORDER BY sort_order ASC LIMIT 1");
                $sid = $stepRes ? $stepRes->fetch_assoc()['id'] : 0;
                
                $conn->query("INSERT INTO hrm_applications (candidate_id, job_id, current_step_id, rating, status) VALUES ($cid, $jid, $sid, " . rand(1, 5) . ", 'active')");
                $aid = $conn->insert_id;
                
                $tags = ['Potential', 'Experienced', 'Quick Learner', 'Good Communication'];
                $randTag = $tags[array_rand($tags)];
                $conn->query("INSERT INTO hrm_application_tags (application_id, tag_name) VALUES ($aid, '$randTag')");
                
                $conn->query("INSERT INTO hrm_application_activities (application_id, action_type, content) VALUES ($aid, 'apply', 'Ứng viên nộp hồ sơ trực tuyến')");
            }
            $response = ['success' => true, 'message' => 'Seed 10 test candidates complete'];
        }
    } elseif ($action === 'get_permissions') {
        $res = $conn->query("
            SELECT p.*, u.full_name, u.avatar, u.email 
            FROM hrm_permissions p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY p.role, p.created_at DESC
        ");
        $data = ['manager' => [], 'executive' => []];
        while($row = $res->fetch_assoc()) {
            $data[$row['role']][] = $row;
        }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'add_permission' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = (int)($data['user_id'] ?? 0);
        $role = $conn->real_escape_string($data['role'] ?? '');
        $stmt = $conn->prepare("INSERT IGNORE INTO hrm_permissions (user_id, role) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $role);
        $stmt->execute();
        $response = ['success' => true];
    } elseif ($action === 'remove_permission' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = (int)($data['user_id'] ?? 0);
        $role = $conn->real_escape_string($data['role'] ?? '');
        $conn->query("DELETE FROM hrm_permissions WHERE user_id = $user_id AND role = '$role'");
        $response = ['success' => true];
    } elseif ($action === 'search_users') {
        $q = $_GET['q'] ?? '';
        if (strpos($q, '@') === 0) { $q = substr($q, 1); }
        $q = $conn->real_escape_string($q);
        $res = $conn->query("
            SELECT DISTINCT u.id, u.full_name, u.avatar, u.email 
            FROM users u
            JOIN hrm_permissions p ON u.id = p.user_id
            WHERE u.full_name LIKE '%$q%' OR u.email LIKE '%$q%' 
            LIMIT 10
        ");
        $data = [];
        while($row = $res->fetch_assoc()) { $data[] = $row; }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'get_hiring_steps') {
        $res = $conn->query("SELECT * FROM hrm_hiring_steps ORDER BY sort_order ASC");
        $data = [];
        while($row = $res->fetch_assoc()) { $data[] = $row; }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'save_hiring_steps' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        foreach($data['steps'] as $idx => $id) {
            $conn->query("UPDATE hrm_hiring_steps SET sort_order = $idx WHERE id = " . (int)$id);
        }
        $response = ['success' => true];
    } elseif ($action === 'add_hiring_step' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $conn->real_escape_string($data['name']);
        $code = $conn->real_escape_string($data['code'] ?? '');
        $conn->query("INSERT INTO hrm_hiring_steps (name, code) VALUES ('$name', '$code')");
        $response = ['success' => true];
    } elseif ($action === 'delete_hiring_step' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $conn->query("DELETE FROM hrm_hiring_steps WHERE id = " . (int)$data['id']);
        $response = ['success' => true];
    } elseif ($action === 'save_other_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $require_job_code = (int)$data['require_job_code'];
        $evaluation_method = $conn->real_escape_string($data['evaluation_method']);
        $auto_create_from_email = (int)$data['auto_create_from_email'];
        $min_delete_permission = $conn->real_escape_string($data['min_delete_permission']);
        $min_export_permission = $conn->real_escape_string($data['min_export_permission']);
        $enable_captcha = (int)$data['enable_captcha'];
        $email_inv = (int)$data['email_interview_invitation'];
        $email_upd = (int)$data['email_interview_update'];
        $email_can = (int)$data['email_interview_cancel'];
        $email_blk = (int)$data['email_interview_bulk'];
        $interview_cv = $conn->real_escape_string($data['interview_cv_display']);
        $onboard_perm = $conn->real_escape_string($data['onboard_integration_permission']);
        
        $conn->query("UPDATE hrm_company_settings SET 
            require_job_code = $require_job_code,
            evaluation_method = '$evaluation_method',
            auto_create_from_email = $auto_create_from_email,
            min_delete_permission = '$min_delete_permission',
            min_export_permission = '$min_export_permission',
            enable_captcha = $enable_captcha,
            email_interview_invitation = $email_inv,
            email_interview_update = $email_upd,
            email_interview_cancel = $email_can,
            email_interview_bulk = $email_blk,
            interview_cv_display = '$interview_cv',
            onboard_integration_permission = '$onboard_perm'
            WHERE id = 1");
        $response = ['success' => true];
    } elseif ($action === 'get_settings') {
        $result = $conn->query("SELECT * FROM hrm_company_settings WHERE id = 1");
        $response = $result ? $result->fetch_assoc() : null;
    } elseif ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;
        if (empty($data)) { $data = json_decode(file_get_contents('php://input'), true); }
        $c_name = $conn->real_escape_string($data['company_name'] ?? '');
        $c_web = $conn->real_escape_string($data['company_website'] ?? '');
        $c_phone = $conn->real_escape_string($data['company_phone'] ?? '');
        $c_addr = $conn->real_escape_string($data['company_address'] ?? '');
        $r_title = $conn->real_escape_string($data['recruit_title'] ?? '');
        $r_url = $conn->real_escape_string($data['recruit_url'] ?? '');
        $r_desc = $conn->real_escape_string($data['recruit_desc'] ?? '');
        $sla = $conn->real_escape_string($data['sla_mode'] ?? '');
        $upload_dir = __DIR__ . '/../../public/uploads/hrm/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $favicon_sql = "";
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
            $filename = 'favicon_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_dir . $filename)) { $favicon_sql = ", favicon = '/public/uploads/hrm/$filename'"; }
        }
        $logo_sql = "";
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) { $logo_sql = ", logo = '/public/uploads/hrm/$filename'"; }
        }
        $sql = "UPDATE hrm_company_settings SET company_name='$c_name', company_website='$c_web', company_phone='$c_phone', company_address='$c_addr', recruit_title='$r_title', recruit_url='$r_url', recruit_desc='$r_desc', sla_mode='$sla' $favicon_sql $logo_sql WHERE id=1";
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_office' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $name = $conn->real_escape_string($data['name'] ?? '');
        $address = $conn->real_escape_string($data['address'] ?? '');
        if ($id) { $sql = "UPDATE hrm_offices SET name='$name', address='$address' WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_offices (name, address) VALUES ('$name', '$address')"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_office' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($conn->query("DELETE FROM hrm_offices WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_dept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $name = $conn->real_escape_string($data['name'] ?? '');
        $desc = $conn->real_escape_string($data['description'] ?? '');
        $mgr = $conn->real_escape_string($data['manager'] ?? '');
        $cre = $conn->real_escape_string($data['creators'] ?? '');
        $fol = $conn->real_escape_string($data['followers'] ?? '');
        if ($id) { $sql = "UPDATE hrm_departments SET name='$name', description='$desc', manager='$mgr', creators='$cre', followers='$fol' WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_departments (name, description, manager, creators, followers) VALUES ('$name', '$desc', '$mgr', '$cre', '$fol')"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'toggle_active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        $active = (int)$data['active'];
        if ($conn->query("UPDATE hrm_candidate_sources SET is_active=$active WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'get_rejection_reasons') {
        $status = (int)($_GET['status'] ?? 1);
        $res = $conn->query("SELECT * FROM hrm_rejection_reasons WHERE is_active = $status ORDER BY sort_order ASC, id DESC");
        $data = [];
        while($row = $res->fetch_assoc()) { $data[] = $row; }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'save_rejection_reason' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $text = $conn->real_escape_string($data['reason_text']);
        $code = $conn->real_escape_string($data['reason_code'] ?? '');
        $by = $_SESSION['full_name'];
        if ($id) { $sql = "UPDATE hrm_rejection_reasons SET reason_text='$text', reason_code='$code' WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_rejection_reasons (reason_text, reason_code, created_by) VALUES ('$text', '$code', '$by')"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'toggle_rejection_reason' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        $active = (int)$data['active'];
        if ($conn->query("UPDATE hrm_rejection_reasons SET is_active=$active WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_rejection_reason' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        if ($conn->query("DELETE FROM hrm_rejection_reasons WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_rejection_mandatory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $val = (int)$data['mandatory'];
        if ($conn->query("UPDATE hrm_company_settings SET rejection_reason_mandatory = $val WHERE id = 1")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_expired_job_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $close = (int)$data['auto_close_expired'];
        $hide = (int)$data['auto_hide_expired'];
        $email = (int)$data['email_before_expiry'];
        if ($conn->query("UPDATE hrm_company_settings SET auto_close_expired=$close, auto_hide_expired=$hide, email_before_expiry=$email WHERE id=1")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_job_post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $title = $conn->real_escape_string($data['title']);
        $code = $conn->real_escape_string($data['job_code'] ?? '');
        $tid = (int)($data['template_id'] ?? 0);
        $did = (int)($data['department_id'] ?? 0);
        $office = $conn->real_escape_string($data['office'] ?? '');
        $sfrom = (float)($data['salary_from'] ?? 0);
        $sto = (float)($data['salary_to'] ?? 0);
        $curr = $conn->real_escape_string($data['currency'] ?? 'VND');
        $show_s = (int)($data['show_salary'] ?? 1);
        $qty = (int)($data['quantity'] ?? 0);
        $type = $conn->real_escape_string($data['job_type'] ?? '');
        $deadline = $conn->real_escape_string($data['deadline'] ?? '');
        $desc = $conn->real_escape_string($data['job_description'] ?? '');
        $tpid = (int)($data['talent_pool_id'] ?? 0);
        $mgrs = $conn->real_escape_string($data['managers'] ?? '');
        $notes = $conn->real_escape_string($data['notes'] ?? '');
        $ctime = $conn->real_escape_string($data['completion_time'] ?? '');
        $city = $conn->real_escape_string($data['city'] ?? '');
        $dist = $conn->real_escape_string($data['district'] ?? '');
        $addr = $conn->real_escape_string($data['address'] ?? '');
        $pcode = $conn->real_escape_string($data['postal_code'] ?? '');
        $uid = $_SESSION['user_id'];

        $deadline_val = !empty($deadline) ? "'$deadline'" : "NULL";

        if ($id) {
            $sql = "UPDATE hrm_job_posts SET title='$title', job_code='$code', template_id=$tid, department_id=$did, office='$office', salary_from=$sfrom, salary_to=$sto, currency='$curr', show_salary=$show_s, quantity=$qty, job_type='$type', deadline=$deadline_val, job_description='$desc', talent_pool_id=$tpid, managers='$mgrs', notes='$notes', completion_time='$ctime', city='$city', district='$dist', address='$addr', postal_code='$pcode' WHERE id=$id";
        } else {
            $sql = "INSERT INTO hrm_job_posts (title, job_code, template_id, department_id, office, salary_from, salary_to, currency, show_salary, quantity, job_type, deadline, job_description, talent_pool_id, managers, notes, completion_time, city, district, address, postal_code, created_by) VALUES ('$title', '$code', $tid, $did, '$office', $sfrom, $sto, '$curr', $show_s, $qty, '$type', $deadline_val, '$desc', $tpid, '$mgrs', '$notes', '$ctime', '$city', '$dist', '$addr', '$pcode', $uid)";
        }
        
        if ($conn->query($sql)) { $response = ['success' => true, 'id' => $id ?: $conn->insert_id]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_job_criteria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $job_id = (int)$data['job_id'];
        $criteria = $data['criteria'] ?? [];
        $mandatory = $data['mandatory'] ?? [];

        $conn->begin_transaction();
        try {
            // Update criteria
            $conn->query("DELETE FROM hrm_job_evaluation_criteria WHERE job_id = $job_id");
            foreach ($criteria as $c) {
                $cid = (int)$c['id'];
                $weight = (int)($c['weight'] ?? 1);
                $expected = $conn->real_escape_string($c['expected_score'] ?? '');
                $conn->query("INSERT INTO hrm_job_evaluation_criteria (job_id, evaluation_criterion_id, weight, expected_score) VALUES ($job_id, $cid, $weight, '$expected')");
            }

            // Update mandatory requirements
            $conn->query("DELETE FROM hrm_job_mandatory_requirements WHERE job_id = $job_id");
            foreach ($mandatory as $m) {
                $text = $conn->real_escape_string($m['requirement_text']);
                if (!empty($text)) {
                    $conn->query("INSERT INTO hrm_job_mandatory_requirements (job_id, requirement_text) VALUES ($job_id, '$text')");
                }
            }

            $conn->commit();
            $response = ['success' => true];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    } elseif ($action === 'get_job_criteria') {
        $job_id = (int)$_GET['job_id'];
        
        $resC = $conn->query("SELECT jc.*, c.criterion_text, g.name as group_name 
            FROM hrm_job_evaluation_criteria jc
            JOIN hrm_evaluation_criteria c ON jc.evaluation_criterion_id = c.id
            JOIN hrm_evaluation_groups g ON c.group_id = g.id
            WHERE jc.job_id = $job_id");
        $criteria = [];
        if ($resC) {
            while($row = $resC->fetch_assoc()) { 
                $row['id'] = $row['evaluation_criterion_id'];
                $criteria[] = $row; 
            }
        }

        $resM = $conn->query("SELECT * FROM hrm_job_mandatory_requirements WHERE job_id = $job_id");
        $mandatory = [];
        while($row = $resM->fetch_assoc()) { $mandatory[] = $row; }

        $response = ['success' => true, 'criteria' => $criteria, 'mandatory' => $mandatory];
    } elseif ($action === 'get_evaluation_data') {
        $groups = [];
        $resG = $conn->query("SELECT * FROM hrm_evaluation_groups ORDER BY sort_order ASC");
        while($g = $resG->fetch_assoc()) {
            $gid = $g['id'];
            $resC = $conn->query("SELECT * FROM hrm_evaluation_criteria WHERE group_id = $gid ORDER BY sort_order ASC, id ASC");
            $g['criteria'] = [];
            while($c = $resC->fetch_assoc()) { $g['criteria'][] = $c; }
            $groups[] = $g;
        }
        $response = ['success' => true, 'data' => $groups];
    } elseif ($action === 'save_evaluation_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $name = $conn->real_escape_string($data['name']);
        if ($id) { $sql = "UPDATE hrm_evaluation_groups SET name='$name' WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_evaluation_groups (name) VALUES ('$name')"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_evaluation_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        if ($conn->query("DELETE FROM hrm_evaluation_groups WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_evaluation_criterion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $gid = (int)$data['group_id'];
        $text = $conn->real_escape_string($data['criterion_text']);
        if ($id) { $sql = "UPDATE hrm_evaluation_criteria SET group_id=$gid, criterion_text='$text' WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_evaluation_criteria (group_id, criterion_text) VALUES ($gid, '$text')"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_evaluation_criterion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        if ($conn->query("DELETE FROM hrm_evaluation_criteria WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'move_criterion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        $gid = (int)$data['group_id'];
        if ($conn->query("UPDATE hrm_evaluation_criteria SET group_id=$gid WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_dept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($conn->query("DELETE FROM hrm_departments WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'add_proposal_approver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ptype = $conn->real_escape_string($data['proposal_type']);
        $atype = $conn->real_escape_string($data['approver_type']);
        $bname = $conn->real_escape_string($data['block_name'] ?? '');
        $uid = (int)($data['user_id'] ?? 0);
        $sla = (int)($data['sla_hours'] ?? 0);
        $meta = $conn->real_escape_string($data['metadata'] ?? '');
        
        $sql = "INSERT INTO hrm_proposal_approvers (proposal_type, approver_type, block_name, user_id, sla_hours, metadata) VALUES ('$ptype', '$atype', '$bname', $uid, $sla, '$meta')";
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'get_proposal_approvers') {
        $ptype = $conn->real_escape_string($_GET['type'] ?? 'recruitment');
        $res = $conn->query("
            SELECT a.*, u.full_name, u.avatar 
            FROM hrm_proposal_approvers a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.proposal_type = '$ptype'
            ORDER BY a.sort_order ASC, a.id ASC
        ");
        $data = [];
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'delete_proposal_approver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        if ($conn->query("DELETE FROM hrm_proposal_approvers WHERE id = $id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'save_proposal_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ptype = $conn->real_escape_string($data['proposal_type']);
        $aflow = $conn->real_escape_string($data['approval_flow']);
        $rprio = $conn->real_escape_string($data['role_priority']);
        $hrmed = (int)$data['hrm_edit_after_approval'];
        
        $sql = "UPDATE hrm_proposal_settings SET approval_flow = '$aflow', role_priority = '$rprio', hrm_edit_after_approval = $hrmed WHERE proposal_type = '$ptype'";
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'get_proposal_settings') {
        $ptype = $conn->real_escape_string($_GET['type'] ?? 'recruitment');
        $res = $conn->query("SELECT * FROM hrm_proposal_settings WHERE proposal_type = '$ptype'");
        $response = ['success' => true, 'data' => ($res ? $res->fetch_assoc() : null)];
    } elseif ($action === 'add_proposal_follower' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $ptype = $conn->real_escape_string($data['proposal_type']);
        $uid = (int)$data['user_id'];
        $sql = "INSERT IGNORE INTO hrm_proposal_followers (proposal_type, user_id) VALUES ('$ptype', $uid)";
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'get_proposal_followers') {
        $ptype = $conn->real_escape_string($_GET['type'] ?? 'recruitment');
        $res = $conn->query("
            SELECT f.*, u.full_name, u.avatar, u.email
            FROM hrm_proposal_followers f
            JOIN users u ON f.user_id = u.id
            WHERE f.proposal_type = '$ptype'
        ");
        $data = [];
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'delete_proposal_follower' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        if ($conn->query("DELETE FROM hrm_proposal_followers WHERE id = $id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'get_talent_pools') {
        $res = $conn->query("
            SELECT tp.*, u.full_name as creator_name, u.username as creator_username
            FROM hrm_talent_pools tp
            LEFT JOIN users u ON tp.created_by = u.id
            ORDER BY tp.created_at DESC
        ");
        $data = [];
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        $response = ['success' => true, 'data' => $data];
    } elseif ($action === 'save_talent_pool' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $name = $conn->real_escape_string($data['name']);
        $desc = $conn->real_escape_string($data['description'] ?? '');
        $uid = $_SESSION['user_id'];
        if ($id) { $sql = "UPDATE hrm_talent_pools SET name='$name', description='$desc' WHERE id=$id"; }
        else { $sql = "INSERT INTO hrm_talent_pools (name, description, created_by) VALUES ('$name', '$desc', $uid)"; }
        if ($conn->query($sql)) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'delete_talent_pool' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($conn->query("DELETE FROM hrm_talent_pools WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }

    // ── Interview Templates ──────────────────────────────────────────────
    } elseif ($action === 'get_interview_templates') {
        $filter = $conn->real_escape_string($_GET['filter'] ?? 'active');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($filter === 'mine') {
            $where = "WHERE t.created_by = $uid";
        } elseif ($filter === 'inactive') {
            $where = "WHERE t.is_active = 0";
        } else {
            $where = "WHERE t.is_active = 1";
        }
        $res = $conn->query("
            SELECT t.*, u.full_name as creator_name, u.username as creator_username
            FROM hrm_interview_templates t
            LEFT JOIN users u ON t.created_by = u.id
            $where ORDER BY t.created_at DESC
        ");
        $data = [];
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        $response = ['success' => true, 'data' => $data];

    } elseif ($action === 'get_interview_template') {
        $id = (int)($_GET['id'] ?? 0);
        $res = $conn->query("SELECT t.*, u.full_name as creator_name, u.username as creator_username
            FROM hrm_interview_templates t LEFT JOIN users u ON t.created_by = u.id WHERE t.id = $id LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $response = ['success' => true, 'data' => $row];
        } else {
            $response = ['success' => false, 'message' => 'Not found'];
        }

    } elseif ($action === 'save_interview_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id          = (int)($data['id'] ?? 0);
        $name        = $conn->real_escape_string($data['name'] ?? '');
        $type        = $conn->real_escape_string($data['interview_type'] ?? 'onsite');
        $participants= $conn->real_escape_string($data['participants'] ?? '');
        $location    = $conn->real_escape_string($data['location'] ?? '');
        $subject     = $conn->real_escape_string($data['email_subject'] ?? '');
        $body        = $conn->real_escape_string($data['email_body'] ?? '');
        $questions   = $conn->real_escape_string($data['questions'] ?? '');
        $is_active   = (int)($data['is_active'] ?? 1);
        $uid         = (int)($_SESSION['user_id'] ?? 0);

        if (!$name) { $response = ['success' => false, 'message' => 'Tên không được để trống']; }
        elseif ($id > 0) {
            $sql = "UPDATE hrm_interview_templates SET name='$name', interview_type='$type', participants='$participants',
                location='$location', email_subject='$subject', email_body='$body', questions='$questions',
                is_active=$is_active WHERE id=$id";
            if ($conn->query($sql)) { $response = ['success' => true, 'id' => $id]; }
            else { $response = ['success' => false, 'message' => $conn->error]; }
        } else {
            $sql = "INSERT INTO hrm_interview_templates (name, interview_type, participants, location, email_subject,
                email_body, questions, is_active, created_by) VALUES ('$name','$type','$participants','$location',
                '$subject','$body','$questions',$is_active,$uid)";
            if ($conn->query($sql)) { $response = ['success' => true, 'id' => $conn->insert_id]; }
            else { $response = ['success' => false, 'message' => $conn->error]; }
        }

    } elseif ($action === 'toggle_interview_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $is_active = (int)($data['is_active'] ?? 0);
        if ($conn->query("UPDATE hrm_interview_templates SET is_active=$is_active WHERE id=$id")) {
            $response = ['success' => true];
        } else { $response = ['success' => false, 'message' => $conn->error]; }

    } elseif ($action === 'delete_interview_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($conn->query("DELETE FROM hrm_interview_templates WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }

    // ── Email Templates ──────────────────────────────────────────────
    } elseif ($action === 'get_email_templates') {
        $filter = $conn->real_escape_string($_GET['filter'] ?? 'active');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($filter === 'mine') {
            $where = "WHERE t.created_by = $uid";
        } elseif ($filter === 'inactive') {
            $where = "WHERE t.is_active = 0";
        } else {
            $where = "WHERE t.is_active = 1";
        }
        $res = $conn->query("
            SELECT t.*, u.full_name as creator_name, u.username as creator_username
            FROM hrm_email_templates t
            LEFT JOIN users u ON t.created_by = u.id
            $where ORDER BY t.is_favorite DESC, t.created_at DESC
        ");
        $data = [];
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        $response = ['success' => true, 'data' => $data];

    } elseif ($action === 'get_email_template') {
        $id = (int)($_GET['id'] ?? 0);
        $res = $conn->query("SELECT t.*, u.full_name as creator_name, u.username as creator_username
            FROM hrm_email_templates t LEFT JOIN users u ON t.created_by = u.id WHERE t.id = $id LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $response = ['success' => true, 'data' => $row];
        } else {
            $response = ['success' => false, 'message' => 'Not found'];
        }

    } elseif ($action === 'save_email_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id          = (int)($data['id'] ?? 0);
        $name        = $conn->real_escape_string($data['name'] ?? '');
        $subject     = $conn->real_escape_string($data['email_subject'] ?? '');
        $body        = $conn->real_escape_string($data['email_body'] ?? '');
        $is_active   = (int)($data['is_active'] ?? 1);
        $is_favorite = (int)($data['is_favorite'] ?? 0);
        $uid         = (int)($_SESSION['user_id'] ?? 0);

        if (!$name) { $response = ['success' => false, 'message' => 'Tên không được để trống']; }
        elseif ($id > 0) {
            $sql = "UPDATE hrm_email_templates SET name='$name', email_subject='$subject', email_body='$body', 
                is_active=$is_active, is_favorite=$is_favorite WHERE id=$id";
            if ($conn->query($sql)) { $response = ['success' => true, 'id' => $id]; }
            else { $response = ['success' => false, 'message' => $conn->error]; }
        } else {
            $sql = "INSERT INTO hrm_email_templates (name, email_subject, email_body, is_active, is_favorite, created_by) 
                VALUES ('$name','$subject','$body',$is_active,$is_favorite,$uid)";
            if ($conn->query($sql)) { $response = ['success' => true, 'id' => $conn->insert_id]; }
            else { $response = ['success' => false, 'message' => $conn->error]; }
        }

    } elseif ($action === 'toggle_email_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;
        $is_favorite = isset($data['is_favorite']) ? (int)$data['is_favorite'] : null;

        $updates = [];
        if ($is_active !== null) $updates[] = "is_active=$is_active";
        if ($is_favorite !== null) $updates[] = "is_favorite=$is_favorite";
        
        if (count($updates) > 0) {
            $setStr = implode(", ", $updates);
            if ($conn->query("UPDATE hrm_email_templates SET $setStr WHERE id=$id")) {
                $response = ['success' => true];
            } else { $response = ['success' => false, 'message' => $conn->error]; }
        }

    } elseif ($action === 'delete_email_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($conn->query("DELETE FROM hrm_email_templates WHERE id=$id")) { $response = ['success' => true]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
    } elseif ($action === 'get_jobs') {
        $ownership = $_GET['ownership'] ?? 'all';
        $search = $conn->real_escape_string($_GET['search'] ?? '');
        $dept = (int)($_GET['dept'] ?? 0);
        $office = $conn->real_escape_string($_GET['office'] ?? '');
        $status = $conn->real_escape_string($_GET['status'] ?? '');
        $tabStatus = $_GET['tabStatus'] ?? 'all';
        $uid = (int)($_SESSION['user_id'] ?? 0);

        $where = "WHERE 1=1";
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $where .= " AND j.id = $id";
        }
        if ($ownership === 'managed') {
            $where .= " AND (FIND_IN_SET('$uid', j.managers) OR j.created_by = $uid)";
        } elseif ($ownership === 'created') {
            $where .= " AND j.created_by = $uid";
        }

        if (!empty($search)) {
            $where .= " AND (j.title LIKE '%$search%' OR j.job_code LIKE '%$search%')";
        }
        if ($dept > 0) $where .= " AND j.department_id = $dept";
        if (!empty($office)) $where .= " AND j.office = '$office'";
        if (!empty($status)) $where .= " AND j.status = '$status'";

        if ($tabStatus === 'active') {
            $where .= " AND j.status IN ('public', 'private') AND (j.deadline >= CURDATE() OR j.deadline IS NULL)";
        } elseif ($tabStatus === 'closed') {
            $where .= " AND (j.status = 'closed' OR (j.deadline < CURDATE() AND j.deadline IS NOT NULL))";
        } elseif ($tabStatus === 'draft') {
            $where .= " AND j.status = 'draft'";
        }

        $sql = "SELECT j.*, d.name as dept_name, u.full_name as creator_name 
                FROM hrm_job_posts j
                LEFT JOIN hrm_departments d ON j.department_id = d.id
                LEFT JOIN users u ON j.created_by = u.id
                $where ORDER BY j.created_at DESC";
        
        $res = $conn->query($sql);
        $jobs = [];
        if ($res) {
            while($row = $res->fetch_assoc()) {
                // Mock stats for now - in real app would join with candidates table
                $row['total_candidates'] = rand(0, 100);
                $row['hired_candidates'] = rand(0, 5);
                $row['in_process'] = rand(0, 20);
                $row['interviews'] = rand(0, 10);
                $jobs[] = $row;
            }
        }

        // Simplified counts
        $counts = [
            'all' => $conn->query("SELECT COUNT(*) FROM hrm_job_posts")->fetch_row()[0],
            'active' => $conn->query("SELECT COUNT(*) FROM hrm_job_posts WHERE status IN ('public', 'private') AND (deadline >= CURDATE() OR deadline IS NULL)")->fetch_row()[0],
            'closed' => $conn->query("SELECT COUNT(*) FROM hrm_job_posts WHERE status = 'closed' OR (deadline < CURDATE() AND deadline IS NOT NULL)")->fetch_row()[0],
            'draft' => $conn->query("SELECT COUNT(*) FROM hrm_job_posts WHERE status = 'draft'")->fetch_row()[0],
        ];
        
        $response = ['success' => true, 'data' => $jobs, 'counts' => $counts];

    } elseif ($action === 'delete_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM hrm_job_evaluation_criteria WHERE job_id = $id");
            $conn->query("DELETE FROM hrm_job_mandatory_requirements WHERE job_id = $id");
            if ($conn->query("DELETE FROM hrm_job_posts WHERE id = $id")) {
                $response = ['success' => true];
            } else { $response = ['success' => false, 'message' => $conn->error]; }
        } else { $response = ['success' => false, 'message' => 'Invalid job ID']; }

    } elseif ($action === 'get_job_hiring_steps') {
        $job_id = (int)$_GET['job_id'];
        $res = $conn->query("SELECT * FROM hrm_job_hiring_steps WHERE job_id = $job_id ORDER BY sort_order ASC, id ASC");
        $steps = [];
        if ($res && $res->num_rows > 0) { 
            while($row = $res->fetch_assoc()) { 
                $name = strtolower($row['name']);
                if ($name === 'offered' || $name === 'đề nghị tuyển dụng' || $name === 'mời nhận việc') $row['stage_type'] = 'offered';
                else if ($name === 'hired' || $name === 'tiếp nhận' || $name === 'tuyển dụng' || $name === 'đạt yêu cầu') $row['stage_type'] = 'hired';
                else if ($name === 'rejected' || $name === 'từ chối' || $name === 'loại' || $name === 'đã từ chối') $row['stage_type'] = 'rejected';
                $steps[] = $row; 
            } 
        }
        
        if (empty($steps)) {
            // Load from global settings
            $resGlobal = $conn->query("SELECT * FROM hrm_hiring_steps ORDER BY sort_order ASC, id ASC");
            $defaults = [];
            if ($resGlobal && $resGlobal->num_rows > 0) {
                while($rowG = $resGlobal->fetch_assoc()) {
                    $nameG = strtolower($rowG['name']);
                    $typeG = $rowG['stage_type'] ?? 'standard';
                    
                    if ($nameG === 'offered' || $nameG === 'đề nghị tuyển dụng' || $nameG === 'mời nhận việc') $typeG = 'offered';
                    else if ($nameG === 'hired' || $nameG === 'tiếp nhận' || $nameG === 'tuyển dụng' || $nameG === 'đạt yêu cầu') $typeG = 'hired';
                    else if ($nameG === 'rejected' || $nameG === 'từ chối' || $nameG === 'loại' || $nameG === 'đã từ chối') $typeG = 'rejected';

                    $defaults[] = [
                        'name' => $rowG['name'], 
                        'type' => $typeG, 
                        'locked' => ($typeG !== 'standard' ? 1 : 0),
                        'email_id' => $rowG['email_template_id'] ?? 0,
                        'duration' => $rowG['duration'] ?? 0
                    ];
                }
            } else {
                $defaults = [
                    ['name' => 'Nhận hồ sơ', 'type' => 'standard', 'locked' => 0],
                    ['name' => 'Phòng vấn', 'type' => 'standard', 'locked' => 0],
                    ['name' => 'Offered', 'type' => 'offered', 'locked' => 1],
                    ['name' => 'Hired', 'type' => 'hired', 'locked' => 1],
                    ['name' => 'Rejected', 'type' => 'rejected', 'locked' => 1]
                ];
            }
            
            foreach($defaults as $idx => $s) {
                $name = $conn->real_escape_string($s['name']);
                $type = $s['type'];
                $locked = $s['locked'] ?? 0;
                $email_id = (int)($s['email_id'] ?? 0);
                $duration = (int)($s['duration'] ?? 0);
                
                $conn->query("INSERT INTO hrm_job_hiring_steps (job_id, name, sort_order, stage_type, is_locked, email_template_id, duration) 
                              VALUES ($job_id, '$name', $idx, '$type', $locked, $email_id, $duration)");
            }
            $res = $conn->query("SELECT * FROM hrm_job_hiring_steps WHERE job_id = $job_id ORDER BY sort_order ASC, id ASC");
            $steps = [];
            if ($res) { while($row = $res->fetch_assoc()) { $steps[] = $row; } }
        }
        $response = ['success' => true, 'data' => $steps];

    } elseif ($action === 'save_job_hiring_steps' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $job_id = (int)$data['job_id'];
        $steps = $data['steps'] ?? [];

        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM hrm_job_hiring_steps WHERE job_id = $job_id");
            foreach ($steps as $idx => $s) {
                $name = $conn->real_escape_string($s['name']);
                $email_id = (int)($s['email_template_id'] ?? 0);
                $interview_id = (int)($s['interview_template_id'] ?? 0);
                $is_locked = (int)($s['is_locked'] ?? 0);
                $type = $conn->real_escape_string($s['stage_type'] ?? 'standard');
                $duration = (int)($s['duration'] ?? 0);
                $manual = (int)($s['manual_review'] ?? 0);
                $conn->query("INSERT INTO hrm_job_hiring_steps (job_id, name, email_template_id, interview_template_id, sort_order, is_locked, stage_type, duration, manual_review) 
                              VALUES ($job_id, '$name', $email_id, $interview_id, $idx, $is_locked, '$type', $duration, $manual)");
            }
            $conn->commit();
            $response = ['success' => true];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    } elseif ($action === 'reset_job_hiring_steps' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $job_id = (int)$data['job_id'];
        $conn->query("DELETE FROM hrm_job_hiring_steps WHERE job_id = $job_id");
        $response = ['success' => true];
    } elseif ($action === 'get_job_application_form') {
        $job_id = (int)$_GET['job_id'];
        $resF = $conn->query("SELECT * FROM hrm_job_application_fields WHERE job_id = $job_id ORDER BY sort_order ASC");
        $fields = [];
        if ($resF) { while($row = $resF->fetch_assoc()) { $fields[] = $row; } }
        
        $resQ = $conn->query("SELECT * FROM hrm_job_custom_questions WHERE job_id = $job_id ORDER BY sort_order ASC");
        $questions = [];
        if ($resQ) { while($row = $resQ->fetch_assoc()) { $questions[] = $row; } }
        
        $response = ['success' => true, 'fields' => $fields, 'questions' => $questions];
    } elseif ($action === 'save_job_application_form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $job_id = (int)$data['job_id'];
        $fields = $data['fields'] ?? [];
        $questions = $data['questions'] ?? [];

        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM hrm_job_application_fields WHERE job_id = $job_id");
            foreach ($fields as $idx => $f) {
                $fname = $conn->real_escape_string($f['field_name']);
                $ishow = (int)$f['is_show'];
                $ireq = (int)$f['is_required'];
                $conn->query("INSERT INTO hrm_job_application_fields (job_id, field_name, is_show, is_required, sort_order) 
                              VALUES ($job_id, '$fname', $ishow, $ireq, $idx)");
            }

            $conn->query("DELETE FROM hrm_job_custom_questions WHERE job_id = $job_id");
            foreach ($questions as $idx => $q) {
                $qtext = $conn->real_escape_string($q['question_text']);
                $qtype = $conn->real_escape_string($q['question_type']);
                $opts = $conn->real_escape_string($q['options'] ?? '');
                $ireq = (int)$q['is_required'];
                $conn->query("INSERT INTO hrm_job_custom_questions (job_id, question_text, question_type, options, is_required, sort_order) 
                              VALUES ($job_id, '$qtext', '$qtype', '$opts', $ireq, $idx)");
            }
            $conn->commit();
            $response = ['success' => true];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
    } elseif ($action === 'update_job_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$data['id'];
        $status = $conn->real_escape_string($data['status']);
        if ($conn->query("UPDATE hrm_job_posts SET status = '$status' WHERE id = $id")) {
            $response = ['success' => true];
        } else { $response = ['success' => false, 'message' => $conn->error]; }
    }
}

// Clean buffer and send clean JSON
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit();
