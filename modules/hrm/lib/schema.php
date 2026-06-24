<?php
/**
 * HRM / Recruitment - clean schema (rebuild 2026).
 * Tables follow the 2026 hiring + onboarding SOPs 1:1.
 *
 * hrm_schema(): ordered [table => CREATE SQL]  (utf8mb4 / InnoDB)
 * hrm_seeds():  rows to seed after creation
 *
 * Run via modules/hrm/lib/install.php (drops every hrm_* first).
 */

function hrm_schema(): array
{
    $t = [];

    /* ── config & roles ─────────────────────────────────────────────── */
    $t['hrm_settings'] = "CREATE TABLE hrm_settings (
        skey VARCHAR(64) PRIMARY KEY,
        sval TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Maps a real user to a recruitment role (TA, BC Director, CEO ...).
    $t['hrm_role_assignments'] = "CREATE TABLE hrm_role_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        rec_role VARCHAR(32) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_role (user_id, rec_role),
        KEY idx_role (rec_role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Ordered approval steps per entity_type (+ optional condition).
    $t['hrm_approval_flows'] = "CREATE TABLE hrm_approval_flows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(32) NOT NULL,
        condition_key VARCHAR(32) NOT NULL DEFAULT '',
        step_order INT NOT NULL DEFAULT 1,
        approver_role VARCHAR(32) NOT NULL,
        sla_hours INT NOT NULL DEFAULT 48,
        active TINYINT DEFAULT 1,
        KEY idx_flow (entity_type, condition_key, step_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── master data ────────────────────────────────────────────────── */
    $t['hrm_departments']      = "CREATE TABLE hrm_departments (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t['hrm_offices']          = "CREATE TABLE hrm_offices (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, address VARCHAR(400) DEFAULT '', sort_order INT DEFAULT 0, active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t['hrm_candidate_sources']= "CREATE TABLE hrm_candidate_sources (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t['hrm_rejection_reasons']= "CREATE TABLE hrm_rejection_reasons (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, active TINYINT DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Pipeline stage taxonomy (9-step SOP).
    $t['hrm_pipeline_stages'] = "CREATE TABLE hrm_pipeline_stages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(32) NOT NULL UNIQUE,
        name VARCHAR(80) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        stage_type VARCHAR(32) NOT NULL DEFAULT 'standard',
        sla_hours INT NOT NULL DEFAULT 0,
        active TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── B1: Hiring Request Form (HRF) ──────────────────────────────── */
    $t['hrm_requests'] = "CREATE TABLE hrm_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(32) NOT NULL,
        title VARCHAR(200) NOT NULL,
        department_id INT DEFAULT 0,
        office_id INT DEFAULT 0,
        level VARCHAR(50) DEFAULT '',
        quantity INT NOT NULL DEFAULT 1,
        salary_min DECIMAL(14,2) DEFAULT 0,
        salary_max DECIMAL(14,2) DEFAULT 0,
        currency VARCHAR(8) DEFAULT 'VND',
        need_by_date DATE NULL,
        reason TEXT,
        jd MEDIUMTEXT,
        request_type ENUM('replacement','new_hc') NOT NULL DEFAULT 'replacement',
        employment_type VARCHAR(32) DEFAULT '',
        experience_required VARCHAR(50) DEFAULT '',
        priority VARCHAR(16) DEFAULT 'Trung bình',
        approver_role VARCHAR(32) DEFAULT '',
        status ENUM('draft','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
        job_id INT DEFAULT 0,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status), KEY idx_creator (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Generic approval ledger - reused by HRF and Offer.
    $t['hrm_approvals'] = "CREATE TABLE hrm_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(32) NOT NULL,
        entity_id INT NOT NULL,
        step_order INT NOT NULL DEFAULT 1,
        approver_role VARCHAR(32) NOT NULL,
        approver_user_id INT DEFAULT 0,
        status ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
        due_at DATETIME NULL,
        acted_at DATETIME NULL,
        acted_by INT DEFAULT 0,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_entity (entity_type, entity_id),
        KEY idx_pending (status, approver_role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── Kế hoạch tuyển dụng (headcount planning) ──────────────────── */
    $t['hrm_plan_cycles'] = "CREATE TABLE hrm_plan_cycles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(64) NOT NULL,
        year INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Per-department headcount plan within a cycle.
    // months_plan / months_actual = JSON array of 12 ints (ĐỊNH BIÊN / THỰC TẾ per month).
    $t['hrm_plan_lines'] = "CREATE TABLE hrm_plan_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cycle_id INT NOT NULL,
        department_id INT NOT NULL,
        dinh_bien_chot INT DEFAULT 0,
        nhan_su INT DEFAULT 0,
        months_plan TEXT,
        months_actual TEXT,
        removed TINYINT DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cycle_dept (cycle_id, department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Per-department, per-stage owners (BC + TA). owner_type: 'bc' | 'ta'.
    $t['hrm_stage_owners'] = "CREATE TABLE hrm_stage_owners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        stage_id INT NOT NULL,
        owner_type VARCHAR(8) NOT NULL,
        user_id INT NOT NULL,
        UNIQUE KEY uq_owner (department_id, stage_id, owner_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── B2-3: Jobs / JD ────────────────────────────────────────────── */
    $t['hrm_jobs'] = "CREATE TABLE hrm_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT DEFAULT 0,
        external_id VARCHAR(32) DEFAULT '',
        code VARCHAR(32) DEFAULT '',
        title VARCHAR(200) NOT NULL,
        department_id INT DEFAULT 0,
        office_id INT DEFAULT 0,
        level VARCHAR(50) DEFAULT '',
        salary_min DECIMAL(14,2) DEFAULT 0,
        salary_max DECIMAL(14,2) DEFAULT 0,
        currency VARCHAR(8) DEFAULT 'VND',
        salary_band VARCHAR(100) DEFAULT '',
        description MEDIUMTEXT,
        jd_skills TEXT,
        probation_kpi TEXT,
        headcount INT DEFAULT 1,
        deadline DATE NULL,
        managers VARCHAR(255) DEFAULT '',
        poster VARCHAR(100) DEFAULT '',
        source_created DATE NULL,
        source_start DATE NULL,
        note VARCHAR(255) DEFAULT '',
        status ENUM('draft','open','on_hold','closed') NOT NULL DEFAULT 'draft',
        channel_id INT DEFAULT 0,
        channel_url VARCHAR(255) DEFAULT '',
        channel_synced_at DATETIME NULL,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status), KEY idx_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── candidates & applications ──────────────────────────────────── */
    $t['hrm_candidates'] = "CREATE TABLE hrm_candidates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        external_id VARCHAR(32) DEFAULT '',
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) DEFAULT '',
        phone VARCHAR(30) DEFAULT '',
        source_id INT DEFAULT 0,
        cv_path VARCHAR(500) DEFAULT '',
        current_position VARCHAR(200) DEFAULT '',
        gender VARCHAR(20) DEFAULT '',
        dob VARCHAR(20) DEFAULT '',
        score DECIMAL(5,2) DEFAULT 0,
        applied_job VARCHAR(255) DEFAULT '',
        applied_stage VARCHAR(100) DEFAULT '',
        classification VARCHAR(50) DEFAULT '',
        campaign VARCHAR(150) DEFAULT '',
        id_card VARCHAR(30) DEFAULT '',
        tags VARCHAR(255) DEFAULT '',
        applied_date DATE NULL,
        interview_date VARCHAR(40) DEFAULT '',
        office_text VARCHAR(200) DEFAULT '',
        reject_reason VARCHAR(255) DEFAULT '',
        updated_src VARCHAR(40) DEFAULT '',
        expected_salary VARCHAR(50) DEFAULT '',
        notice_period VARCHAR(50) DEFAULT '',
        languages VARCHAR(150) DEFAULT '',
        notes TEXT,
        talent_pool TINYINT DEFAULT 0,
        created_by INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_email (email), KEY idx_ext (external_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_applications'] = "CREATE TABLE hrm_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        job_id INT NOT NULL,
        stage_id INT DEFAULT 0,
        status ENUM('active','hired','rejected','hold','withdrawn') NOT NULL DEFAULT 'active',
        reject_reason VARCHAR(255) DEFAULT '',
        score DECIMAL(5,2) DEFAULT 0,
        rating TINYINT DEFAULT 0,
        owner_id INT DEFAULT 0,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_job (job_id), KEY idx_candidate (candidate_id), KEY idx_stage (stage_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Per-application, per-stage assignee (who handles the candidate at that stage).
    $t['hrm_application_assignees'] = "CREATE TABLE hrm_application_assignees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        stage_id INT NOT NULL,
        user_id INT NOT NULL,
        UNIQUE KEY uq_app_stage (application_id, stage_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // TA screening review (BƯỚC 4: SCREENING - TA đánh giá Text/Phone call).
    $t['hrm_screening_reviews'] = "CREATE TABLE hrm_screening_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        background TEXT,
        experience TEXT,
        salary TEXT,
        orientation TEXT,
        notice_period VARCHAR(150) DEFAULT '',
        languages VARCHAR(255) DEFAULT '',
        reference_check TEXT,
        result VARCHAR(20) DEFAULT '',
        note TEXT,
        reviewed_by INT DEFAULT 0,
        reviewed_at DATETIME NULL,
        UNIQUE KEY uq_app (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── B5 test · B6 interview · B7 evaluation · B8 offer ──────────── */
    $t['hrm_tests'] = "CREATE TABLE hrm_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        test_type VARCHAR(50) DEFAULT '',
        score DECIMAL(5,2) DEFAULT 0,
        max_score DECIMAL(5,2) DEFAULT 100,
        passed TINYINT DEFAULT 0,
        evaluator_id INT DEFAULT 0,
        taken_at DATETIME NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_app (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_interviews'] = "CREATE TABLE hrm_interviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        round INT DEFAULT 1,
        interview_type VARCHAR(30) DEFAULT 'technical',
        scheduled_at DATETIME NULL,
        interviewer_id INT DEFAULT 0,
        location VARCHAR(150) DEFAULT '',
        status ENUM('scheduled','done','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
        result VARCHAR(20) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_app (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_evaluations'] = "CREATE TABLE hrm_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        interview_id INT DEFAULT 0,
        evaluator_id INT DEFAULT 0,
        kind VARCHAR(30) DEFAULT 'interview',
        total_score DECIMAL(5,2) DEFAULT 0,
        detail JSON NULL,
        recommendation VARCHAR(20) DEFAULT '',
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_app (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_offers'] = "CREATE TABLE hrm_offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        salary DECIMAL(14,2) DEFAULT 0,
        currency VARCHAR(8) DEFAULT 'VND',
        start_date DATE NULL,
        offer_letter_path VARCHAR(255) DEFAULT '',
        status ENUM('draft','pending_approval','sent','accepted','declined','expired') NOT NULL DEFAULT 'draft',
        sent_at DATETIME NULL,
        responded_at DATETIME NULL,
        created_by INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_app (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── Onboarding 60 days ─────────────────────────────────────────── */
    $t['hrm_onboarding'] = "CREATE TABLE hrm_onboarding (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT DEFAULT 0,
        user_id INT DEFAULT 0,
        candidate_name VARCHAR(150) NOT NULL,
        job_title VARCHAR(150) DEFAULT '',
        level VARCHAR(50) DEFAULT '',
        manager_id INT DEFAULT 0,
        buddy_id INT DEFAULT 0,
        ta_id INT DEFAULT 0,
        bc_director_id INT DEFAULT 0,
        start_date DATE NULL,
        plan_template VARCHAR(50) DEFAULT 'default',
        status ENUM('preboarding','active','completed','left') NOT NULL DEFAULT 'preboarding',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_onboarding_tasks'] = "CREATE TABLE hrm_onboarding_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        onboarding_id INT NOT NULL,
        phase VARCHAR(30) DEFAULT 'preboarding',
        title VARCHAR(200) NOT NULL,
        owner_role VARCHAR(32) DEFAULT '',
        due_date DATE NULL,
        done TINYINT DEFAULT 0,
        done_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_onb (onboarding_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_checkpoints'] = "CREATE TABLE hrm_checkpoints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        onboarding_id INT NOT NULL,
        checkpoint_key VARCHAR(20) NOT NULL,
        due_date DATE NULL,
        owner_role VARCHAR(32) DEFAULT '',
        status ENUM('pending','done','overdue') NOT NULL DEFAULT 'pending',
        score_attitude INT DEFAULT 0,
        score_skill INT DEFAULT 0,
        score_integration INT DEFAULT 0,
        result_grade VARCHAR(5) DEFAULT '',
        notes TEXT,
        done_at DATETIME NULL,
        KEY idx_onb (onboarding_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_probation_reviews'] = "CREATE TABLE hrm_probation_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        onboarding_id INT DEFAULT 0,
        application_id INT DEFAULT 0,
        score_kpi DECIMAL(5,2) DEFAULT 0,
        score_competency DECIMAL(5,2) DEFAULT 0,
        score_attitude DECIMAL(5,2) DEFAULT 0,
        score_culture DECIMAL(5,2) DEFAULT 0,
        total DECIMAL(5,2) DEFAULT 0,
        decision ENUM('confirm','extend','reject') NULL,
        reviewed_by INT DEFAULT 0,
        reviewed_at DATETIME NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_onb (onboarding_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    /* ── cross-cutting: email · notif · sla · audit ─────────────────── */
    $t['hrm_email_templates'] = "CREATE TABLE hrm_email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_key VARCHAR(64) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        subject VARCHAR(255) DEFAULT '',
        body_html MEDIUMTEXT,
        audience VARCHAR(20) DEFAULT 'candidate',
        enabled TINYINT DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_email_log'] = "CREATE TABLE hrm_email_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_key VARCHAR(64) DEFAULT '',
        to_email VARCHAR(150) DEFAULT '',
        subject VARCHAR(255) DEFAULT '',
        entity_type VARCHAR(32) DEFAULT '',
        entity_id INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'sent',
        error TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_event (event_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // Source table for the NotificationCenter aggregator (kind = 'hrm').
    $t['hrm_notifications'] = "CREATE TABLE hrm_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_key VARCHAR(64) DEFAULT '',
        title VARCHAR(200) NOT NULL,
        body VARCHAR(500) DEFAULT '',
        severity VARCHAR(20) DEFAULT 'info',
        link VARCHAR(255) DEFAULT '',
        entity_type VARCHAR(32) DEFAULT '',
        entity_id INT DEFAULT 0,
        is_read TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_sla_events'] = "CREATE TABLE hrm_sla_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(32) NOT NULL,
        entity_id INT NOT NULL,
        event_key VARCHAR(64) DEFAULT '',
        due_at DATETIME NULL,
        satisfied_at DATETIME NULL,
        breached TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['hrm_audit_log'] = "CREATE TABLE hrm_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 0,
        action VARCHAR(64) NOT NULL,
        entity_type VARCHAR(32) DEFAULT '',
        entity_id INT DEFAULT 0,
        detail TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_entity (entity_type, entity_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $t;
}

/** Seed rows inserted after the tables are (re)created. */
function hrm_seeds(mysqli $conn): void
{
    // Pipeline stages (9-step SOP).
    $stages = [
        ['SOURCED',    'Nguồn / Mới',        1, 'standard',  0],
        ['SCREENING',  'Screening',          2, 'standard', 48],
        ['TEST',       'Test đầu vào',       3, 'standard',  0],
        ['INTERVIEW',  'Phỏng vấn',          4, 'standard',  0],
        ['REFERENCE',  'Reference check',    5, 'standard',  0],
        ['EVALUATION', 'Đánh giá sau PV',    6, 'standard', 24],
        ['OFFER',      'Offer',              7, 'offered',  72],
        ['HIRED',      'Đã tuyển',           8, 'hired',     0],
        ['REJECTED',   'Từ chối',            9, 'rejected',  0],
    ];
    $st = $conn->prepare("INSERT INTO hrm_pipeline_stages (code,name,sort_order,stage_type,sla_hours) VALUES (?,?,?,?,?)");
    foreach ($stages as $s) { $st->bind_param('ssisi', $s[0], $s[1], $s[2], $s[3], $s[4]); $st->execute(); }

    // Approval flows. HRF: BC Director -> CEO. Offer: Hiring Manager -> BC Director (CEO step added in Phase 2 for mgr+).
    $flows = [
        // HRF: requester (Delivery Manager / BC Director / Head of Department) -> approved by CEO or CDO.
        ['hrf',   '', 1, 'ceo,cdo',        48],
        ['offer', '', 1, 'hiring_manager', 48],
        ['offer', '', 2, 'bc_director',    48],
    ];
    $sf = $conn->prepare("INSERT INTO hrm_approval_flows (entity_type,condition_key,step_order,approver_role,sla_hours) VALUES (?,?,?,?,?)");
    foreach ($flows as $f) { $sf->bind_param('ssisi', $f[0], $f[1], $f[2], $f[3], $f[4]); $sf->execute(); }

    // Settings (channel toggles + retention + SLA defaults).
    $settings = [
        'company_name'    => 'AHT TECH JSC',
        'email_enabled'   => '1',
        'notif_enabled'   => '1',
        'retention_months'=> '24',
        'hrf_sla_hours'   => '48',
    ];
    $ss = $conn->prepare("INSERT INTO hrm_settings (skey,sval) VALUES (?,?)");
    foreach ($settings as $k => $v) { $ss->bind_param('ss', $k, $v); $ss->execute(); }

    // Master data defaults.
    foreach (['LinkedIn','ITviec','Vietnamworks','TopCV','Facebook','Referral','Talent Pool','University'] as $name) {
        $conn->query("INSERT INTO hrm_candidate_sources (name) VALUES ('" . $conn->real_escape_string($name) . "')");
    }
    foreach (['Không đạt kỹ năng','Lương không phù hợp','Không phù hợp văn hóa','Ứng viên từ chối','Quá hạn phản hồi'] as $name) {
        $conn->query("INSERT INTO hrm_rejection_reasons (name) VALUES ('" . $conn->real_escape_string($name) . "')");
    }

    // Phase-1 email templates (HRF internal). {{vars}} merged at send time.
    $tpls = [
        ['hrf_approval_request', 'HRF chờ duyệt', 'Đề nghị phê duyệt yêu cầu tuyển dụng {{request_code}}',
            '<p>Kính gửi {{approver_name}},</p><p>Phòng Tuyển dụng trân trọng đề nghị Anh/Chị xem xét và phê duyệt yêu cầu tuyển dụng với các thông tin như sau:</p><p><b>Thông tin yêu cầu</b><br>Vị trí: <b>{{request_title}}</b><br>Số lượng: {{quantity}} vị trí<br>Cấp bậc: {{level}}<br>Loại tuyển dụng: {{request_type}}<br>Hình thức làm việc: {{employment_type}}<br>Yêu cầu kinh nghiệm: {{experience_required}}<br>Khoảng lương: {{salary_range}}<br>Mức độ ưu tiên: {{priority}}<br>Lý do tuyển dụng: {{reason}}<br>Ngày cần onboard: {{need_by_date}}</p><p><b>Mô tả công việc (JD)</b></p><div style="border-left:3px solid #ddd;padding-left:12px;color:#444">{{jd}}</div><p>Kính mong Anh/Chị xem xét và cho ý kiến phê duyệt trước {{due_at}}.</p><p><a href="{{link}}">Xem chi tiết và phê duyệt</a></p><p>Trân trọng cảm ơn.</p>', 'internal'],
        ['hrf_approved', 'HRF đã được duyệt', 'Yêu cầu tuyển dụng {{request_code}} đã được duyệt',
            '<p>Xin chào {{requester_name}},</p><p>Yêu cầu tuyển dụng <b>{{request_title}}</b> đã được phê duyệt đầy đủ. Bạn có thể tạo tin tuyển dụng từ yêu cầu này.</p><p><a href="{{link}}">Mở yêu cầu</a></p>', 'internal'],
        ['hrf_rejected', 'HRF bị từ chối', 'Yêu cầu tuyển dụng {{request_code}} bị từ chối',
            '<p>Xin chào {{requester_name}},</p><p>Yêu cầu tuyển dụng <b>{{request_title}}</b> đã bị từ chối tại bước {{approver_role}}.</p><p>Lý do: {{note}}</p>', 'internal'],
        // Phase-2 templates (candidate-facing + offer approval).
        ['cv_received', 'Xác nhận đã nhận CV', 'Cảm ơn bạn đã ứng tuyển {{job_title}}',
            '<p>Xin chào {{candidate_name}},</p><p>AHT đã nhận hồ sơ ứng tuyển vị trí <b>{{job_title}}</b> của bạn. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.</p><p>Trân trọng,<br>Phòng Tuyển dụng AHT</p>', 'candidate'],
        ['reject_screening', 'Kết quả ứng tuyển', 'Kết quả ứng tuyển vị trí {{job_title}}',
            '<p>Xin chào {{candidate_name}},</p><p>Cảm ơn bạn đã quan tâm vị trí <b>{{job_title}}</b>. Rất tiếc hồ sơ của bạn chưa phù hợp ở thời điểm này. Chúng tôi sẽ lưu hồ sơ cho các cơ hội phù hợp hơn.</p><p>Trân trọng,<br>Phòng Tuyển dụng AHT</p>', 'candidate'],
        ['test_invitation', 'Mời làm bài test đầu vào', 'Mời làm bài đánh giá - {{job_title}}',
            '<p>Xin chào {{candidate_name}},</p><p>Bạn được mời tham gia bài đánh giá đầu vào cho vị trí <b>{{job_title}}</b>. Vui lòng làm bài theo hướng dẫn đính kèm.</p><p>Trân trọng,<br>Phòng Tuyển dụng AHT</p>', 'candidate'],
        ['interview_invitation', 'Thư mời phỏng vấn', 'Mời phỏng vấn vị trí {{job_title}}',
            '<p>Xin chào {{candidate_name}},</p><p>AHT mời bạn tham gia phỏng vấn vị trí <b>{{job_title}}</b>.</p><p>Thời gian: <b>{{interview_time}}</b><br>Hình thức: {{interview_type}}<br>Địa điểm: {{location}}</p><p>Trân trọng,<br>Phòng Tuyển dụng AHT</p>', 'candidate'],
        ['offer_letter', 'Thư mời nhận việc', 'Thư mời nhận việc - {{job_title}}',
            '<p>Xin chào {{candidate_name}},</p><p>Chúc mừng! AHT trân trọng gửi tới bạn lời mời nhận việc vị trí <b>{{job_title}}</b>.</p><p>Mức lương: <b>{{salary}} {{currency}}</b><br>Ngày bắt đầu dự kiến: {{start_date}}</p><p>Vui lòng phản hồi trước {{due_at}}.</p><p>Trân trọng,<br>Phòng Tuyển dụng AHT</p>', 'candidate'],
        ['offer_approval_request', 'Offer chờ duyệt', 'Offer cho {{candidate_name}} cần bạn phê duyệt',
            '<p>Xin chào {{approver_name}},</p><p>Có offer cho ứng viên <b>{{candidate_name}}</b> (vị trí {{job_title}}, lương {{salary}} {{currency}}) cần bạn phê duyệt.</p><p><a href="{{link}}">Xem &amp; phê duyệt</a> - hạn xử lý: {{due_at}}.</p>', 'internal'],
    ];
    $stp = $conn->prepare("INSERT INTO hrm_email_templates (event_key,name,subject,body_html,audience) VALUES (?,?,?,?,?)");
    foreach ($tpls as $x) { $stp->bind_param('sssss', $x[0], $x[1], $x[2], $x[3], $x[4]); $stp->execute(); }
}
