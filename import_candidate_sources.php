<?php
/**
 * import_candidate_sources.php
 * Seed danh sach nguon ung vien (hrm_candidate_sources) cho live site.
 *
 * An toan de chay lai: chi them nguon CHUA co (so khop theo name),
 * khong tao trung, khong xoa du lieu san co.
 *
 * Cach chay:
 *   - Web : truy cap /import_candidate_sources.php (can dang nhap admin)
 *   - CLI : php import_candidate_sources.php
 */

require __DIR__ . '/config/config.php';

$isCli = (PHP_SAPI === 'cli');
$nl    = $isCli ? "\n" : "<br>\n";

// Bao ve: chay qua web phai dang nhap voi quyen admin.
if (!$isCli) {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<h3>Unauthorized: can dang nhap bang tai khoan admin.</h3>');
    }
}

if (!isset($conn) || $conn->connect_errno) {
    die('Khong ket noi duoc database: ' . ($conn->connect_error ?? 'unknown'));
}
$conn->set_charset('utf8mb4');

// Tao bang neu chua co (khop schema modules/hrm/lib/schema.php)
$conn->query("CREATE TABLE IF NOT EXISTS hrm_candidate_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Danh sach nguon. Dung ten hien thi (chu hoa). THREADS = vo hieu hoa.
$disabled = ['THREADS'];
$names = [
    'ADJOB', 'THREADS', 'METWORKING', 'SKYPE', 'LLINKEDIN', 'NO', 'WEB-TRAINING-AHT', 'BAN',
    'GETBEE', 'NETWORKINH', 'ANDROID', 'FORUM', 'VIRTUAL-CAREER-FAIR-2021', 'JAVA', 'GITHUB',
    'LINKDIN', 'TESTER', 'HTTPSWWW.FACEBOOK.COMPROFILE.PHPID100013392615640', 'SKYPE-TOMCLANCY1234',
    'HTTPSWWW.FACEBOOK.COMELDESPERADO305', 'HTTPSWWW.FACEBOOK.COMCONGNTIT', 'LINKEIN', 'LUONGNT-REFER',
    'WEB-ONNET', 'RECO', 'LINHEB', 'HTTPSWWW.FACEBOOK.COMEUGENE.NGUYEN.XB', 'NW', 'CAREER-LINK',
    'FACEBOOK.-LUONGNT', 'ACCOUNT', 'RYTHEMYGMAIL.COM', 'SALES', 'BD', 'NULO-2022', 'NGALT', 'GMAIL',
    'REFER-NETWORKING', 'SALES-IT', 'THAOPT', 'CL', 'APTECH', 'DH-FPT', 'DHCNHN', 'DHBK-TOPCV',
    'DHCNHN-TOPCV', 'CD-CONG-NGHE-THUONG-MAI', 'DH-SU-HAM-HN', 'DH-MO-HN', 'WORKVN', 'T3H', 'NETWRKING',
    'TOPCV-FACEBOOK', 'FAECEBOOK', 'AGENCY-WATAJOB', 'NETWOKING', 'NGOCBTT,SALES-IT', 'TOP-CV',
    'REFER-HOANG-ANH-LEAD', 'NETWORL', 'HEADHUNT-GETBEE', 'MAIL-HR', 'REFERAL', 'WEBINAR', 'TOPCV-APPLY',
    'PAGE', 'VN', 'NETWROK', 'REFER-CHINH-ASIA', 'ITO', 'PHUONGDM-REFER', 'GLINT', 'FACKFRUIT',
    'HEADHUNTER', 'HEADHUNT,JACKFRUIT', 'FPT-POLYTECHNIC', 'DHHN', 'QHDN', 'TRANGLD', 'FACEBOOK,TOPCV',
    'HUNTER-GLINTS', 'VIETNAMWORK', 'VNWS', 'TOPCV,ACCOUNT', 'PREFER-NOI-BO', 'NGOCBTT', 'VNW',
    'REFER-JESSIE', 'PAGE-AHT', 'MAIL', 'USTH', 'FACEBOOK,LINKEDIN', 'BEHANCE', 'REFER-UYEN-QA',
    'REFER-OHIO', 'NETWORING', 'WATAJOB', 'DEVWORK', 'JACKFRUIT', 'LINH-EB', 'OHIO-JESSIE', 'DUNGIC',
    'FACEBOOK,LINHEB', 'FACEBOOOK', 'OHIO', 'ONNET', 'REFER-HUYEN-MINH', 'DH-CNHN', 'GREENWICH',
    'DH-DIEN-LUC', 'GOOGLE_JOBS_APPLY', 'PTIT', 'REFER-PHUONGDM', 'MAILHR', 'NGALT-ANGULAR', 'ZALO',
    'VIEN-NGOAI-NGU-DH-BK-HN', 'FB', 'NGALT,LINKEDIN', 'SHARECV', 'LUONGNT', 'HEADHUNT', 'HRMAIL',
    'DEVPRO', 'PHUONGDM', 'REFER-LUONGNT', 'LINKEIDN', 'REFER-NOI-BO', 'REFER', 'HAUI', 'NETWORK',
    'DH-CNHN,UPLOAD', 'WEBSITE,UPLOAD', 'NETWORKING', 'VIECLAM123', 'JOBOKO', 'YBOX', 'TIMVIEC365',
    '123JOB', 'INDEED', 'VIECTOTNHAT', 'TOPDEV', 'MYWORK', 'TOPCV', 'CAREERLINK', 'CAREERBUILDER.VN',
    'JOBSTREET.VN', 'VIETNAMWORKS', 'TIMVIECNHANH.COM', 'JOBSGO', 'VIECLAM24H', 'ITVIEC.COM',
    'TALENT POOL', 'EMAIL', 'UPLOAD', 'RECRUITER', 'REFERRAL', 'LINKEDIN', 'FACEBOOK', 'WEBSITE', 'OTHER',
];

$check  = $conn->prepare('SELECT id FROM hrm_candidate_sources WHERE name = ? LIMIT 1');
$insert = $conn->prepare('INSERT INTO hrm_candidate_sources (name, active) VALUES (?, ?)');

$added = 0; $skipped = 0; $fail = 0;
foreach ($names as $name) {
    $check->bind_param('s', $name);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        $skipped++;
        continue;   // da ton tai, bo qua
    }
    $active = in_array($name, $disabled, true) ? 0 : 1;
    $insert->bind_param('si', $name, $active);
    if ($insert->execute()) {
        $added++;
        echo 'ADD  ' . $name . ($active ? '' : ' (vo hieu hoa)') . $nl;
    } else {
        $fail++;
        echo 'LOI  ' . $name . ': ' . $insert->error . $nl;
    }
}

echo $nl . "Hoan tat: $added them moi, $skipped da co (bo qua), $fail loi (tong " . count($names) . " nguon)." . $nl;
