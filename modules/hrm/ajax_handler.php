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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$checkSteps = $conn->query("SELECT COUNT(*) as count FROM hrm_hiring_steps");
if ($checkSteps) {
    $rowSteps = $checkSteps->fetch_assoc();
    if ($rowSteps && $rowSteps['count'] == 0) {
        $conn->query("INSERT INTO hrm_hiring_steps (name, code, sort_order) VALUES 
            ('Nhận hồ sơ', 'NHAN_HO_SO', 1),
            ('Sơ loại', 'SO_LOAI', 2),
            ('Phỏng vấn', 'PHONG_VAN', 3),
            ('Đề nghị tuyển dụng', 'DE_NGHI', 4),
            ('Tiếp nhận', 'TIEP_NHAN', 5)");
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

$action = $_GET['action'] ?? '';
$response = null;

if (!isset($_SESSION['user_id'])) {
    $response = ['success' => false, 'message' => 'Unauthorized'];
} else {
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
    } elseif ($action === 'get_candidate_sources') {
        $res = $conn->query("SELECT * FROM hrm_candidate_sources ORDER BY sort_order ASC, id ASC");
        $data = [];
        while($row = $res->fetch_assoc()) { $data[] = $row; }
        $response = ['success' => true, 'data' => $data];
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
        if ($type === 'dept' || $type === 'office' || $type === 'source') {
            $table = ($type === 'dept') ? 'hrm_departments' : (($type === 'office') ? 'hrm_offices' : 'hrm_candidate_sources');
            foreach ($order as $index => $id) {
                $id = (int)$id;
                $idx = (int)$index;
                $conn->query("UPDATE $table SET sort_order = $idx WHERE id = $id");
            }
            $response = ['success' => true];
        } else { $response = ['success' => false, 'message' => 'Invalid type']; }
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

        $deadline_val = !empty($deadline) ? "'$deadline'" : "NULL";

        if ($id) {
            $sql = "UPDATE hrm_job_posts SET title='$title', job_code='$code', template_id=$tid, department_id=$did, office='$office', salary_from=$sfrom, salary_to=$sto, currency='$curr', show_salary=$show_s, quantity=$qty, job_type='$type', deadline=$deadline_val, job_description='$desc', talent_pool_id=$tpid, managers='$mgrs', notes='$notes', completion_time='$ctime', city='$city', district='$dist', address='$addr', postal_code='$pcode' WHERE id=$id";
        } else {
            $sql = "INSERT INTO hrm_job_posts (title, job_code, template_id, department_id, office, salary_from, salary_to, currency, show_salary, quantity, job_type, deadline, job_description, talent_pool_id, managers, notes, completion_time, city, district, address, postal_code) VALUES ('$title', '$code', $tid, $did, '$office', $sfrom, $sto, '$curr', $show_s, $qty, '$type', $deadline_val, '$desc', $tpid, '$mgrs', '$notes', '$ctime', '$city', '$dist', '$addr', '$pcode')";
        }
        
        if ($conn->query($sql)) { $response = ['success' => true, 'id' => $id ?: $conn->insert_id]; }
        else { $response = ['success' => false, 'message' => $conn->error]; }
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
    }
}

// Clean buffer and send clean JSON
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit();
