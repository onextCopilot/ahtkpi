<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

if ($path === '/hrm/e-hiring' || $path === '/hrm/e-hiring.php') {
    require_once __DIR__ . '/../modules/hrm/e_hiring.php';
} elseif ($path === '/hrm/openings' || $path === '/hrm/openings.php') {
    require_once __DIR__ . '/../modules/hrm/openings.php';
} elseif ($path === '/hrm/company-info' || $path === '/hrm/company-info.php') {
    require_once __DIR__ . '/../modules/hrm/company_info.php';
} elseif ($path === '/hrm/ajax-handler' || $path === '/hrm/ajax-handler.php') {
    require_once __DIR__ . '/../modules/hrm/ajax_handler.php';
} elseif ($path === '/hrm/permissions' || $path === '/hrm/permissions.php') {
    require_once __DIR__ . '/../modules/hrm/permissions.php';
} elseif ($path === '/hrm/other-settings' || $path === '/hrm/other-settings.php') {
    require_once __DIR__ . '/../modules/hrm/other_settings.php';
} elseif ($path === '/hrm/proposal-settings' || $path === '/hrm/proposal-settings.php') {
    require_once __DIR__ . '/../modules/hrm/proposal_settings.php';
} elseif ($path === '/hrm/candidate-sources' || $path === '/hrm/candidate-sources.php') {
    require_once __DIR__ . '/../modules/hrm/candidate_sources.php';
} elseif ($path === '/hrm/rejection-reasons' || $path === '/hrm/rejection-reasons.php') {
    require_once __DIR__ . '/../modules/hrm/rejection_reasons.php';
} elseif ($path === '/hrm/expired-job-settings' || $path === '/hrm/expired-job-settings.php') {
    require_once __DIR__ . '/../modules/hrm/expired_job_settings.php';
} elseif ($path === '/hrm/evaluation-criteria' || $path === '/hrm/evaluation-criteria.php') {
    require_once __DIR__ . '/../modules/hrm/evaluation_criteria.php';
} elseif ($path === '/hrm/job-post-create' || $path === '/hrm/job-post-create.php') {
    require_once __DIR__ . '/../modules/hrm/job_post_create.php';
} elseif ($path === '/hrm/system-settings' || $path === '/hrm/system-settings.php') {
    require_once __DIR__ . '/../modules/hrm/system_settings.php';
} elseif ($path === '/hrm/email-templates' || $path === '/hrm/email-templates.php') {
    require_once __DIR__ . '/../modules/hrm/email_templates.php';
} elseif ($path === '/hrm/email-template-detail' || $path === '/hrm/email-template-detail.php') {
    require_once __DIR__ . '/../modules/hrm/email_template_detail.php';
} elseif ($path === '/hrm/interview-templates' || $path === '/hrm/interview-templates.php') {
    require_once __DIR__ . '/../modules/hrm/interview_templates.php';
} elseif ($path === '/hrm/interview-template-detail' || $path === '/hrm/interview-template-detail.php') {
    require_once __DIR__ . '/../modules/hrm/interview_template_detail.php';
} elseif ($path === '/hrm/job-detail' || $path === '/hrm/job-detail.php') {
    require_once __DIR__ . '/../modules/hrm/job_detail.php';
} elseif ($path === '/hrm/job-edit' || $path === '/hrm/job-edit.php') {
    require_once __DIR__ . '/../modules/hrm/job_edit.php';
} else {
    require_once __DIR__ . '/../modules/hrm/index.php';
}
