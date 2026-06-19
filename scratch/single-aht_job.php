<?php
if (!have_posts()) {
    wp_redirect(home_url('/'));
    exit;
}
the_post();

$job_id    = get_the_ID();
$title     = get_the_title();
$salary    = get_post_meta($job_id, 'aht_job_salary', true) ?: 'Thương lượng';
$location  = get_post_meta($job_id, 'aht_job_location', true);
$deadline  = get_post_meta($job_id, 'aht_job_deadline', true);
$apply_url = get_post_meta($job_id, 'aht_job_apply_url', true);
$dept_id   = (int) get_post_meta($job_id, 'aht_job_department', true);
$content   = get_the_content();

$dept_names = array(
    989  => 'IT',
    1134 => 'BFSI',
    346  => 'Sales/Marketing',
    374  => 'BackOffice',
    1274 => 'Akdemy',
    1135 => 'Remote/Hybrid/Expat',
);
$dept_name = isset($dept_names[$dept_id]) ? $dept_names[$dept_id] : '';

// Other jobs (excluding current)
$other_jobs = new WP_Query(array(
    'post_type'      => 'aht_job',
    'posts_per_page' => 6,
    'post_status'    => 'publish',
    'post__not_in'   => array($job_id),
    'orderby'        => 'date',
    'order'          => 'DESC',
));

// ============================================================
// APPLICATION FORM PROCESSING (before headers sent)
// ============================================================
$form_error   = '';
$form_success = isset($_GET['applied']) && $_GET['applied'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aht_apply_nonce'])) {
    if (!wp_verify_nonce($_POST['aht_apply_nonce'], 'aht_apply_' . $job_id)) {
        $form_error = 'Lỗi bảo mật. Vui lòng thử lại.';
    } else {
        $applicant_name  = sanitize_text_field($_POST['applicant_name'] ?? '');
        $applicant_email = sanitize_email($_POST['applicant_email'] ?? '');
        $applicant_phone = sanitize_text_field($_POST['applicant_phone'] ?? '');
        $captcha_input   = trim($_POST['captcha_code'] ?? '');
        $captcha_uuid    = sanitize_text_field($_POST['captcha_uuid'] ?? '');

        // Validate required fields
        if (!$applicant_name || !$applicant_email || !$applicant_phone) {
            $form_error = 'Vui lòng điền đầy đủ thông tin.';
        } elseif (!is_email($applicant_email)) {
            $form_error = 'Email không hợp lệ.';
        } else {
            // Verify captcha via transient
            $stored_answer = get_transient('aht_captcha_' . $captcha_uuid);
            if ($stored_answer === false || intval($captcha_input) !== intval($stored_answer)) {
                $form_error = 'Mã bảo mật không đúng. Vui lòng thử lại.';
            } else {
                delete_transient('aht_captcha_' . $captcha_uuid);

                // Handle CV upload
                $cv_path = '';
                if (!empty($_FILES['applicant_cv']['name'])) {
                    $allowed_ext = array('pdf', 'doc', 'docx');
                    $ext = strtolower(pathinfo($_FILES['applicant_cv']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_ext)) {
                        $form_error = 'CV phải là file PDF, DOC hoặc DOCX.';
                    } elseif ($_FILES['applicant_cv']['size'] > 5 * 1024 * 1024) {
                        $form_error = 'CV không được vượt quá 5MB.';
                    } else {
                        $upload_dir = wp_upload_dir();
                        $safe_name  = sanitize_file_name($applicant_name . '_' . time() . '.' . $ext);
                        $target     = $upload_dir['path'] . '/' . $safe_name;
                        if (move_uploaded_file($_FILES['applicant_cv']['tmp_name'], $target)) {
                            $cv_path = $target;
                        } else {
                            $form_error = 'Upload CV thất bại. Vui lòng thử lại.';
                        }
                    }
                }

                if (!$form_error) {
                    // Send email
                    $to      = get_theme_mod('aht_contact_email', 'recruitment@arrowhitech.com');
                    $subject = '[Ứng tuyển] ' . $title . ' — ' . $applicant_name;
                    $body    = "Ứng viên mới đã nộp đơn:\n\n"
                             . "Họ & tên: $applicant_name\n"
                             . "Email: $applicant_email\n"
                             . "Điện thoại: $applicant_phone\n\n"
                             . "Vị trí: $title\n"
                             . "Link: " . get_permalink($job_id);
                    $headers = array(
                        'Content-Type: text/plain; charset=UTF-8',
                        'Reply-To: ' . $applicant_name . ' <' . $applicant_email . '>',
                    );
                    $attachments = $cv_path ? array($cv_path) : array();
                    $sent = wp_mail($to, $subject, $body, $headers, $attachments);
                    
                    // --- SEND TO AHT KPI WEBHOOK ---
                    $kpi_api_key = get_option('aht_api_key', '');
                    // Default webhook URL if not defined
                    $kpi_webhook_url = get_option('aht_kpi_webhook_url', 'https://os.arrowhitech.com/hrm/webhook.php');
                    
                    if ($kpi_api_key && $kpi_webhook_url) {
                        $ch = curl_init();
                        $post_data = array(
                            'action' => 'receive_application',
                            'external_job_id' => $job_id,
                            'applicant_name' => $applicant_name,
                            'applicant_email' => $applicant_email,
                            'applicant_phone' => $applicant_phone
                        );
                        if ($cv_path && file_exists($cv_path)) {
                            $post_data['applicant_cv'] = new CURLFile($cv_path);
                        }
                        
                        curl_setopt($ch, CURLOPT_URL, $kpi_webhook_url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'X-API-Key: ' . $kpi_api_key
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                    }
                    // -------------------------------

                    // Clean up uploaded file after email and webhook
                    if ($cv_path && file_exists($cv_path)) {
                        @unlink($cv_path);
                    }

                    if ($sent) {
                        wp_redirect(add_query_arg('applied', '1', get_permalink($job_id)));
                        exit;
                    } else {
                        $form_error = 'Gửi đơn thất bại. Vui lòng thử lại sau hoặc liên hệ trực tiếp qua email.';
                    }
                }
            }
        }
    }
}

// Generate captcha for form display
$cap_uuid = wp_generate_uuid4();
$cap_n1   = rand(2, 9);
$cap_n2   = rand(1, 9);
set_transient('aht_captcha_' . $cap_uuid, $cap_n1 + $cap_n2, HOUR_IN_SECONDS);

get_header();
?>

<!-- BREADCRUMB -->
<div class="top-article">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?php echo home_url('/'); ?>" class="active">
                <span class="-ap icon-keyboard_arrow_left"></span> Back to Home
            </a>
        </div>
    </div>
</div>

<!-- JOB DETAIL -->
<section class="sec- job-detail">
    <div class="container">

        <!-- HEAD: left info + right CTA -->
        <div class="head-article">
            <div class="left head-desc">
                <h1 class="title"><?php echo esc_html($title); ?></h1>
                <div class="desc-job">
                    <p class="desc salary">
                        <strong>Salary:</strong> <?php echo esc_html($salary); ?>
                    </p>
                    <?php if ($location) : ?>
                    <p class="desc location">
                        <strong>Location:</strong> <?php echo esc_html($location); ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($dept_name) : ?>
                    <p class="desc location">
                        <strong>Department:</strong> <?php echo esc_html($dept_name); ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($deadline) : ?>
                    <p class="desc date">
                        <strong>Deadline:</strong> <?php echo esc_html($deadline); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right">
                <div class="ctas">
                    <?php if ($apply_url) : ?>
                        <a href="<?php echo esc_url($apply_url); ?>" target="_blank" class="btn-apply">Apply now</a>
                    <?php else : ?>
                        <a href="#apply-form" class="btn-apply">Apply now</a>
                    <?php endif; ?>
                </div>
                <div class="box-share art">
                    <span>Share:</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>"
                       target="_blank" class="-fb" rel="noopener">
                        <span class="-ap icon-facebook2"></span> Facebook
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(get_permalink()); ?>&title=<?php echo urlencode($title); ?>"
                       target="_blank" class="-in" rel="noopener">
                        <span class="-ap icon-linkedin2"></span> Linkedin
                    </a>
                </div>
            </div>
        </div>

        <hr>

        <!-- JOB DESCRIPTION -->
        <article class="article">
            <div class="content-article">
                <h2 class="title-h2">Job Description</h2>
                <?php if ($content) : ?>
                    <?php echo apply_filters('the_content', $content); ?>
                <?php else : ?>
                    <p><em>Job description coming soon.</em></p>
                <?php endif; ?>
            </div>

            <div class="ctas" style="margin-top: 32px;">
                <a href="#apply-form" class="btn-apply">Apply now</a>
            </div>
        </article>

        <!-- MORE POSITIONS -->
        <?php if ($other_jobs->have_posts()) : ?>
        <div class="more-positions">
            <h3 class="title-h3">More positions</h3>
            <div class="list-jobs">
                <div class="jobs-row">
                    <?php while ($other_jobs->have_posts()) : $other_jobs->the_post(); ?>
                        <?php
                        $oj_id   = get_the_ID();
                        $oj_loc  = get_post_meta($oj_id, 'aht_job_location', true);
                        $oj_dead = get_post_meta($oj_id, 'aht_job_deadline', true);
                        $oj_dept = (int) get_post_meta($oj_id, 'aht_job_department', true);
                        ?>
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="item-job item-job--link" data-dept="<?php echo esc_attr($oj_dept); ?>">
                            <h4 class="title-h4"><?php the_title(); ?></h4>
                            <?php if ($oj_loc) : ?>
                            <p class="desc-job">
                                <span class="-ap icon-location3"></span>
                                <?php echo esc_html($oj_loc); ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($oj_dead) : ?>
                            <p class="desc-job">
                                <span class="-ap icon-access_time"></span>
                                <?php echo esc_html($oj_dead); ?>
                            </p>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- APPLICATION FORM -->
        <div class="apply-form-wrap" id="apply-form">
            <h2 class="apply-form-title">Nộp đơn ứng tuyển công việc này</h2>

            <?php if ($form_success) : ?>
                <div class="apply-notice apply-success">
                    ✅ Đơn ứng tuyển của bạn đã được gửi thành công! Chúng tôi sẽ liên hệ sớm nhất.
                </div>
            <?php elseif ($form_error) : ?>
                <div class="apply-notice apply-error">⚠️ <?php echo esc_html($form_error); ?></div>
            <?php endif; ?>

            <?php if (!$form_success) : ?>
            <form class="apply-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('aht_apply_' . $job_id, 'aht_apply_nonce'); ?>
                <input type="hidden" name="captcha_uuid" value="<?php echo esc_attr($cap_uuid); ?>">

                <div class="form-group">
                    <label for="applicant_name">Họ &amp; tên bạn <span class="form-req">*</span></label>
                    <input type="text" id="applicant_name" name="applicant_name"
                           class="form-control" placeholder="Họ &amp; tên bạn"
                           value="<?php echo esc_attr($_POST['applicant_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="applicant_email">Địa chỉ email <span class="form-req">*</span></label>
                    <input type="email" id="applicant_email" name="applicant_email"
                           class="form-control" placeholder="Địa chỉ email"
                           value="<?php echo esc_attr($_POST['applicant_email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="applicant_phone">Số điện thoại <span class="form-req">*</span></label>
                    <input type="tel" id="applicant_phone" name="applicant_phone"
                           class="form-control" placeholder="Số điện thoại"
                           value="<?php echo esc_attr($_POST['applicant_phone'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>CV của bạn <span class="form-req">*</span></label>
                    <div class="file-upload-box">
                        <input type="file" id="applicant_cv" name="applicant_cv"
                               accept=".pdf,.doc,.docx" style="display:none">
                        <label for="applicant_cv" class="file-upload-label" id="cv-label">
                            Click để chọn &amp; tải lên CV của bạn
                        </label>
                    </div>
                    <p class="file-hint">Định dạng: PDF, DOC, DOCX · Tối đa 5MB</p>
                </div>

                <div class="form-group">
                    <label for="captcha_code">Mã bảo mật <span class="form-req">*</span></label>
                    <input type="text" id="captcha_code" name="captcha_code"
                           class="form-control captcha-input" placeholder="Nhập kết quả phép tính" required>
                    <div class="captcha-display">
                        <?php echo $cap_n1; ?> + <?php echo $cap_n2; ?> = ?
                    </div>
                </div>

                <button type="submit" class="btn-apply-submit">Nộp đơn ứng tuyển</button>
            </form>
            <?php endif; ?>
        </div>

    </div>
</section>

<script>
// Show selected filename in CV upload
document.getElementById('applicant_cv').addEventListener('change', function() {
    var label = document.getElementById('cv-label');
    label.textContent = this.files[0] ? this.files[0].name : 'Click để chọn & tải lên CV của bạn';
});
// Scroll to form when Apply now clicked
document.querySelectorAll('a[href="#apply-form"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        var el = document.getElementById('apply-form');
        if (el) el.scrollIntoView({ behavior: 'smooth' });
    });
});
</script>

<?php get_footer(); ?>
