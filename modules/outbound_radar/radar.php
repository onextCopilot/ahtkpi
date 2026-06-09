<?php
/**
 * Outbound Radar — MVP
 * --------------------------------------------------------------------------
 * Phát hiện tín hiệu "thiếu hụt năng lực dev" ở một công ty (US/EU/AU),
 * chấm điểm độ phù hợp outsourcing, và sinh pitch theo hình thức hợp tác.
 *
 * Nguồn dữ liệu THẬT: ATS public boards (Greenhouse / Lever / Ashby /
 * SmartRecruiters / Recruitee). Không scrape LinkedIn.
 *
 * Usage (CLI):
 *   php modules/outbound_radar/radar.php <website-hoặc-ats-url> [--json]
 *
 * Ví dụ:
 *   php modules/outbound_radar/radar.php https://www.netlify.com
 *   php modules/outbound_radar/radar.php jobs.lever.co/leadgenius
 *
 * Lớp AI viết pitch sẽ cắm sau (set ANTHROPIC_API_KEY). Hiện dùng template
 * để chạy end-to-end không cần key.
 */

declare(strict_types=1);

/* ===========================================================================
 * 1. HTTP helper
 * ======================================================================== */
function http_get(string $url, int $timeout = 20): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OutboundRadar/1.0)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json, text/html;q=0.8'],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    unset($ch); // PHP 8.0+ tự đóng handle; curl_close() đã bị deprecate ở 8.5
    return ['status' => $status, 'body' => $body === false ? '' : $body, 'error' => $err];
}

function http_post_json(string $url, array $payload, array $headers, int $timeout = 60): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    unset($ch);
    return ['status' => $status, 'body' => $body === false ? '' : $body, 'error' => $err];
}

/* ===========================================================================
 * 2. ATS detection — nhận provider + token từ input hoặc từ HTML trang careers
 * ======================================================================== */

/** Các pattern host của từng ATS. Token = nhóm bắt được. */
const ATS_PATTERNS = [
    'greenhouse'     => [
        '~boards\.greenhouse\.io/embed/job_board\?for=([a-z0-9_\-]+)~i',
        '~(?:boards|job-boards)\.greenhouse\.io/([a-z0-9_\-]+)~i',
        '~greenhouse\.io/embed/job_board/js\?for=([a-z0-9_\-]+)~i',
    ],
    'lever'          => ['~jobs(?:\.eu)?\.lever\.co/([a-z0-9_\-]+)~i'],
    'ashby'          => ['~jobs\.ashbyhq\.com/([a-z0-9_.\-]+)~i'],
    'smartrecruiters'=> ['~(?:careers|jobs)\.smartrecruiters\.com/([a-z0-9_\-]+)~i'],
    'recruitee'      => ['~([a-z0-9\-]+)\.recruitee\.com~i'],
];

function detect_ats_from_text(string $text): ?array
{
    foreach (ATS_PATTERNS as $provider => $patterns) {
        foreach ($patterns as $re) {
            if (preg_match($re, $text, $m) && !empty($m[1])) {
                // recruitee: bỏ các subdomain rác như "www"
                if ($provider === 'recruitee' && in_array(strtolower($m[1]), ['www', 'api'], true)) {
                    continue;
                }
                return ['provider' => $provider, 'token' => $m[1]];
            }
        }
    }
    return null;
}

function detect_ats(string $input): ?array
{
    // a) Input đã là URL của chính ATS?
    if ($hit = detect_ats_from_text($input)) {
        return $hit;
    }

    // b) Coi input là website công ty → quét homepage + vài trang careers phổ biến.
    $base = normalize_url($input);
    $candidates = [
        $base,
        rtrim($base, '/') . '/careers',
        rtrim($base, '/') . '/careers/',
        rtrim($base, '/') . '/jobs',
        rtrim($base, '/') . '/company/careers',
        rtrim($base, '/') . '/about/careers',
    ];
    foreach ($candidates as $url) {
        $res = http_get($url, 15);
        if ($res['status'] >= 200 && $res['status'] < 400 && $res['body'] !== '') {
            if ($hit = detect_ats_from_text($res['body'])) {
                return $hit;
            }
        }
    }
    return null;
}

function normalize_url(string $input): string
{
    $input = trim($input);
    if (!preg_match('~^https?://~i', $input)) {
        $input = 'https://' . $input;
    }
    return $input;
}

/* ===========================================================================
 * 3. Job fetch theo provider → chuẩn hoá về schema chung
 *    job = [title, location, team, commitment, posted_ts(int|null), url, text]
 * ======================================================================== */

function fetch_jobs(string $provider, string $token): array
{
    switch ($provider) {
        case 'greenhouse':      return fetch_greenhouse($token);
        case 'lever':           return fetch_lever($token);
        case 'ashby':           return fetch_ashby($token);
        case 'smartrecruiters': return fetch_smartrecruiters($token);
        case 'recruitee':       return fetch_recruitee($token);
        default:                return [];
    }
}

function decode_json(string $body): ?array
{
    $d = json_decode($body, true);
    return is_array($d) ? $d : null;
}

function plain(string $html): string
{
    // strip_tags KHÔNG xoá nội dung trong <script>/<style> → phải bỏ trước,
    // nếu không text sẽ toàn JS/CSS (nhất là site WordPress).
    $html = preg_replace('~<(script|style|noscript|svg|template)\b[^>]*>.*?</\1>~is', ' ', $html);
    $html = strip_tags((string) $html);
    return trim(preg_replace('/\s+/', ' ', html_entity_decode($html, ENT_QUOTES | ENT_HTML5)));
}

function fetch_greenhouse(string $token): array
{
    $res = http_get("https://boards-api.greenhouse.io/v1/boards/{$token}/jobs?content=true");
    $d = decode_json($res['body']);
    if (!$d || empty($d['jobs'])) return [];
    $out = [];
    foreach ($d['jobs'] as $j) {
        $out[] = [
            'title'      => $j['title'] ?? '',
            'location'   => $j['location']['name'] ?? '',
            'team'       => $j['departments'][0]['name'] ?? '',
            'commitment' => '',
            'posted_ts'  => isset($j['updated_at']) ? strtotime($j['updated_at']) : null,
            'url'        => $j['absolute_url'] ?? '',
            'text'       => plain((string)($j['content'] ?? '')),
        ];
    }
    return $out;
}

function fetch_lever(string $token): array
{
    $res = http_get("https://api.lever.co/v0/postings/{$token}?mode=json");
    $d = decode_json($res['body']);
    if (!$d) return [];
    $out = [];
    foreach ($d as $j) {
        $out[] = [
            'title'      => $j['text'] ?? '',
            'location'   => $j['categories']['location'] ?? '',
            'team'       => $j['categories']['team'] ?? '',
            'commitment' => $j['categories']['commitment'] ?? '',
            'posted_ts'  => isset($j['createdAt']) ? (int) floor(((int)$j['createdAt']) / 1000) : null,
            'url'        => $j['hostedUrl'] ?? '',
            'text'       => plain((string)($j['descriptionPlain'] ?? $j['description'] ?? '')),
        ];
    }
    return $out;
}

function fetch_ashby(string $token): array
{
    $res = http_get("https://api.ashbyhq.com/posting-api/job-board/{$token}?includeCompensation=false");
    $d = decode_json($res['body']);
    if (!$d || empty($d['jobs'])) return [];
    $out = [];
    foreach ($d['jobs'] as $j) {
        $posted = $j['publishedDate'] ?? $j['publishedAt'] ?? null;
        $out[] = [
            'title'      => $j['title'] ?? '',
            'location'   => $j['location'] ?? ($j['address']['postalAddress']['addressLocality'] ?? ''),
            'team'       => $j['department'] ?? $j['team'] ?? '',
            'commitment' => $j['employmentType'] ?? '',
            'posted_ts'  => $posted ? strtotime((string)$posted) : null,
            'url'        => $j['jobUrl'] ?? $j['applyUrl'] ?? '',
            'text'       => plain((string)($j['descriptionPlain'] ?? $j['descriptionHtml'] ?? '')),
        ];
    }
    return $out;
}

function fetch_smartrecruiters(string $token): array
{
    $res = http_get("https://api.smartrecruiters.com/v1/companies/{$token}/postings?limit=100");
    $d = decode_json($res['body']);
    if (!$d || empty($d['content'])) return [];
    $out = [];
    foreach ($d['content'] as $j) {
        $loc = trim(($j['location']['city'] ?? '') . ' ' . ($j['location']['country'] ?? ''));
        $out[] = [
            'title'      => $j['name'] ?? '',
            'location'   => $loc,
            'team'       => $j['department']['label'] ?? '',
            'commitment' => $j['typeOfEmployment']['label'] ?? '',
            'posted_ts'  => isset($j['releasedDate']) ? strtotime($j['releasedDate']) : null,
            'url'        => $j['applyUrl'] ?? ($j['ref'] ?? ''),
            'text'       => plain((string)($j['jobAd']['sections']['jobDescription']['text'] ?? '')),
        ];
    }
    return $out;
}

function fetch_recruitee(string $token): array
{
    $res = http_get("https://{$token}.recruitee.com/api/offers/");
    $d = decode_json($res['body']);
    if (!$d || empty($d['offers'])) return [];
    $out = [];
    foreach ($d['offers'] as $j) {
        $out[] = [
            'title'      => $j['title'] ?? '',
            'location'   => $j['location'] ?? '',
            'team'       => $j['department'] ?? '',
            'commitment' => $j['employment_type'] ?? '',
            'posted_ts'  => isset($j['created_at']) ? strtotime($j['created_at']) : null,
            'url'        => $j['careers_url'] ?? '',
            'text'       => plain((string)($j['description'] ?? '')),
        ];
    }
    return $out;
}

/* ===========================================================================
 * 4. Phân tích tín hiệu
 * ======================================================================== */

const DEV_ROLE_KEYWORDS = [
    'engineer', 'developer', 'programmer', 'full stack', 'full-stack', 'fullstack',
    'frontend', 'front-end', 'front end', 'backend', 'back-end', 'back end',
    'software', 'mobile', 'ios', 'android', 'devops', 'sre', 'qa', 'tester',
    'architect', 'platform', 'web developer', 'data engineer', 'ml engineer',
];

// loại nhiễu: các role "engineer/developer" không phải dev thực thi
const DEV_ROLE_EXCLUDE = [
    'sales engineer', 'solutions engineer', 'customer engineer', 'support engineer',
    'customer success', 'account', 'recruit', 'marketing', 'sales development',
    'pre-sales', 'presales',
];

// stack → capability của Onext
const STACK_MAP = [
    'web/ecommerce' => ['php', 'laravel', 'symfony', 'magento', 'shopify', 'woocommerce',
        'wordpress', 'drupal', 'shopware', 'vue', 'angular', 'react', 'next.js', 'nextjs',
        'typescript', 'javascript', 'tailwind', 'headless'],
    'mobile/app'    => ['ios', 'swift', 'android', 'kotlin', 'react native', 'react-native',
        'flutter', 'mobile'],
    'backend/custom'=> ['node', 'nodejs', 'node.js', 'python', 'django', 'fastapi', 'golang',
        'go', 'java', 'spring', 'ruby', 'rails', '.net', 'c#', 'graphql', 'microservices',
        'aws', 'kubernetes', 'devops', 'api'],
];

function is_dev_role(string $title): bool
{
    $t = ' ' . strtolower($title) . ' ';
    foreach (DEV_ROLE_EXCLUDE as $ex) {
        if (str_contains($t, $ex)) return false;
    }
    foreach (DEV_ROLE_KEYWORDS as $kw) {
        if (str_contains($t, $kw)) return true;
    }
    return false;
}

function extract_skills(string $haystack): array
{
    $h = strtolower($haystack);
    $found = [];
    foreach (STACK_MAP as $cap => $skills) {
        foreach ($skills as $s) {
            // Match theo ranh giới chữ-số để tránh false positive
            // (vd "go" không khớp "golang"/"category", "api" không khớp "apiece").
            $q = preg_quote($s, '~');
            if (preg_match('~(?<![a-z0-9])' . $q . '(?![a-z0-9])~', $h)) {
                $found[$cap][$s] = true;
            }
        }
    }
    return $found;
}

function analyze(array $jobs): array
{
    $now = time();
    $devJobs = [];
    foreach ($jobs as $j) {
        if (is_dev_role($j['title'])) {
            $j['days_open'] = $j['posted_ts'] ? (int) floor(($now - $j['posted_ts']) / 86400) : null;
            $devJobs[] = $j;
        }
    }

    $longOpen = array_filter($devJobs, fn($j) => ($j['days_open'] ?? 0) >= 45);
    $contract = array_filter($devJobs, fn($j) => stripos($j['commitment'] ?? '', 'contract') !== false
        || stripos($j['title'], 'contract') !== false || stripos($j['title'], 'freelance') !== false);

    // gom skill + capability
    $skillFreq = [];
    $capHits = [];
    foreach ($devJobs as $j) {
        $skills = extract_skills($j['title'] . ' ' . $j['text']);
        foreach ($skills as $cap => $set) {
            $capHits[$cap] = ($capHits[$cap] ?? 0) + 1;
            foreach (array_keys($set) as $s) {
                $skillFreq[$s] = ($skillFreq[$s] ?? 0) + 1;
            }
        }
    }
    arsort($skillFreq);

    // điểm phù hợp outsourcing (0–100)
    $score = 0;
    $score += min(count($devJobs) * 8, 40);                       // số lượng nhu cầu
    if (count($devJobs) > 0) {
        $score += (int) round(count($longOpen) / count($devJobs) * 25); // tỉ lệ mở lâu
    }
    $coreMatch = 0;
    foreach (['react native', 'react', 'node', 'php', 'shopify', 'magento', 'laravel', 'flutter', 'vue'] as $core) {
        if (isset($skillFreq[$core])) $coreMatch++;
    }
    $score += min($coreMatch * 5, 20);                            // khớp stack Onext
    if (count($contract) > 0) $score += 10;                       // có contract/freelance
    $score = min($score, 100);

    return [
        'total_jobs'   => count($jobs),
        'dev_jobs'     => $devJobs,
        'dev_count'    => count($devJobs),
        'long_open'    => array_values($longOpen),
        'contract'     => array_values($contract),
        'skill_freq'   => $skillFreq,
        'cap_hits'     => $capHits,
        'score'        => $score,
    ];
}

/* ===========================================================================
 * 5. Chọn hình thức hợp tác + pitch (template; AI cắm sau)
 * ======================================================================== */

function recommend_engagement(array $a): array
{
    $dev = $a['dev_count'];
    $contractHeavy = count($a['contract']) >= max(1, (int) round($dev * 0.4));

    if ($contractHeavy) {
        $model = 'Staff Augmentation / Contract';
        $why = 'Họ đang mở vị trí contract/freelance — dấu hiệu cần bổ sung năng lực ngắn hạn.';
    } elseif ($dev >= 5) {
        $model = 'Dedicated Team';
        $why = 'Khối lượng tuyển dev lớn — phù hợp dựng squad chuyên trách mở rộng team của họ.';
    } elseif ($dev >= 1) {
        $model = 'Project-based';
        $why = 'Số role ít nhưng cụ thể — phù hợp nhận trọn gói từng hạng mục.';
    } else {
        $model = 'N/A';
        $why = 'Chưa thấy tín hiệu tuyển dev rõ ràng.';
    }
    return ['model' => $model, 'why' => $why];
}

function template_pitch(string $company, array $a, array $eng): string
{
    $topSkills = array_slice(array_keys($a['skill_freq']), 0, 4);
    $skillStr  = $topSkills ? implode(', ', $topSkills) : 'engineering';
    $longN     = count($a['long_open']);
    $devN      = $a['dev_count'];

    $hookLong = $longN > 0 ? " ({$longN} vị trí mở 45+ ngày chưa lấp)" : '';

    return <<<TXT
Chào [Tên],

Bọn mình thấy {$company} đang mở {$devN} vị trí engineering{$hookLong}, tập trung vào {$skillStr} — đúng những mảng team mình triển khai hằng ngày cho khách US/EU/AU.

{$eng['why']}

Onext (VN) có thể đề xuất hình thức: {$eng['model']}, giúp {$company} lấp năng lực trong ~2 tuần thay vì chờ tuyển nhiều tháng, với chi phí offshore tối ưu mà vẫn giữ chất lượng.

Mình gửi anh/chị vài case tương tự được không?

[Sale], Onext Digital
TXT;
}

/* ===========================================================================
 * 6. Output
 * ======================================================================== */

function render_report(string $company, array $detect, array $a, array $eng, string $pitch): void
{
    $line = str_repeat('─', 66);
    echo "\n$line\n";
    echo "  OUTBOUND RADAR · {$company}\n";
    echo "$line\n";
    echo "  ATS         : {$detect['provider']} (token: {$detect['token']})\n";
    echo "  Tổng job    : {$a['total_jobs']}   |   Job dev: {$a['dev_count']}\n";
    echo "  Mở 45+ ngày : " . count($a['long_open']) . "   |   Contract/freelance: " . count($a['contract']) . "\n";
    echo "  ĐIỂM PHÙ HỢP: {$a['score']}/100\n";
    echo "$line\n";

    if ($a['cap_hits']) {
        echo "  Khớp năng lực Onext:\n";
        foreach ($a['cap_hits'] as $cap => $n) {
            echo "    · $cap  ($n job)\n";
        }
    }
    $top = array_slice($a['skill_freq'], 0, 8, true);
    if ($top) {
        echo "  Skill nổi bật: ";
        $parts = [];
        foreach ($top as $s => $n) $parts[] = "$s($n)";
        echo implode(', ', $parts) . "\n";
    }
    echo "$line\n";
    echo "  Vị trí dev đang mở (tối đa 10):\n";
    foreach (array_slice($a['dev_jobs'], 0, 10) as $j) {
        $d = $j['days_open'] !== null ? "{$j['days_open']}d" : '?';
        $loc = $j['location'] !== '' ? " · {$j['location']}" : '';
        echo "    [{$d}] {$j['title']}{$loc}\n";
    }
    echo "$line\n";
    echo "  ĐỀ XUẤT HỢP TÁC: {$eng['model']}\n";
    echo "$line\n";
    echo "  PITCH (nháp):\n\n";
    foreach (explode("\n", $pitch) as $l) echo "  $l\n";
    echo "\n$line\n\n";
}

/* ===========================================================================
 * 6b. Lớp Claude — fallback bóc job từ careers page tùy biến + viết pitch
 * ======================================================================== */

const CLAUDE_MODEL = 'claude-opus-4-8'; // đổi 'claude-sonnet-4-6' để rẻ hơn

function anthropic_api_key(): ?string
{
    // Thứ tự: biến môi trường → DB (set bởi index.php qua GLOBALS) → file local.
    $env = getenv('ANTHROPIC_API_KEY');
    if ($env) return $env;
    if (!empty($GLOBALS['RADAR_API_KEY'])) {
        return (string) $GLOBALS['RADAR_API_KEY'];
    }
    $f = __DIR__ . '/../../config/anthropic_key.php';
    if (is_file($f)) {
        $k = require $f;
        return is_string($k) && $k !== '' ? $k : null;
    }
    return null;
}

/** Gọi Claude Messages API (raw HTTP). $schema != null => ép JSON theo schema. */
function claude_messages(string $userText, ?array $schema = null, int $maxTokens = 4000): ?array
{
    $key = anthropic_api_key();
    if (!$key) return null;

    $payload = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'messages'   => [['role' => 'user', 'content' => $userText]],
    ];
    if ($schema) {
        $payload['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
    }
    $res = http_post_json('https://api.anthropic.com/v1/messages', $payload, [
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ], 90);

    if ($res['status'] !== 200) {
        return ['_error' => "Claude HTTP {$res['status']}: " . substr($res['body'], 0, 300)];
    }
    $d = decode_json($res['body']);
    $text = '';
    foreach ($d['content'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'text') $text .= $b['text'];
    }
    if ($schema) {
        $j = json_decode($text, true);
        return is_array($j) ? $j : ['_error' => 'JSON không hợp lệ', 'raw' => $text];
    }
    return ['text' => $text];
}

/** Lấy HTML trang careers (thử nhiều path), chọn trang nhiều tín hiệu job nhất. */
function fetch_careers_html(string $input): array
{
    $base = normalize_url($input);
    $paths = ['', '/careers', '/careers/', '/career', '/jobs', '/company/careers',
        '/about/careers', '/join-us', '/work-with-us', '/about/jobs'];
    $best = null;
    foreach ($paths as $p) {
        $u = $p === '' ? $base : rtrim($base, '/') . $p;
        $res = http_get($u, 15);
        if ($res['status'] >= 200 && $res['status'] < 400 && $res['body'] !== '') {
            $text  = plain($res['body']);
            $score = preg_match_all('~\b(engineer|developer|hiring|vacanc|position|apply now|join our|we\W?re looking|open role)~i', $text);
            // Trang có "career/job/join" trong URL được ưu tiên mạnh hơn homepage,
            // tránh chọn nhầm homepage chỉ vì nó dài & nhiều từ khoá.
            $isCareer = (bool) preg_match('~career|/jobs?|join|work-with~i', $u);
            $weight   = $score + ($isCareer ? 1000 : 0);
            if ($best === null || $weight > $best['weight']) {
                $best = ['url' => $u, 'text' => $text, 'score' => $score, 'weight' => $weight];
            }
            if ($isCareer && $score >= 2) break; // đã thấy trang careers có job → đủ
        }
    }
    return $best ?? [];
}

/** Bóc job từ text trang careers bằng Claude → danh sách job chuẩn hoá. */
function claude_extract_jobs(string $company, string $careersText, string $url): array
{
    $careersText = mb_substr($careersText, 0, 16000);
    $schema = [
        'type' => 'object', 'additionalProperties' => false,
        'properties' => [
            'jobs' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'title'           => ['type' => 'string'],
                    'location'        => ['type' => 'string'],
                    'employment_type' => ['type' => 'string'],
                    'skills'          => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['title', 'location', 'employment_type', 'skills'],
            ]],
        ],
        'required' => ['jobs'],
    ];
    $prompt = "Đây là nội dung trang tuyển dụng của công ty \"{$company}\". "
        . "Trích các VỊ TRÍ ĐANG TUYỂN (ưu tiên kỹ thuật/dev nhưng lấy hết). "
        . "Bỏ qua text marketing/điều hướng. Nếu không có vị trí nào, trả jobs rỗng. "
        . "Với mỗi job: title, location, employment_type, và skills (mảng từ khoá công nghệ nhắc trong mô tả).\n\n--- NỘI DUNG ---\n"
        . $careersText;

    $r = claude_messages($prompt, $schema, 4000);
    if (!$r || isset($r['_error'])) return [];

    $jobs = [];
    foreach ($r['jobs'] ?? [] as $j) {
        $skills = is_array($j['skills'] ?? null) ? implode(' ', $j['skills']) : '';
        $jobs[] = [
            'title'      => (string)($j['title'] ?? ''),
            'location'   => (string)($j['location'] ?? ''),
            'team'       => '',
            'commitment' => (string)($j['employment_type'] ?? ''),
            'posted_ts'  => null,
            'url'        => $url,
            'text'       => trim(((string)($j['title'] ?? '')) . ' ' . $skills),
        ];
    }
    return $jobs;
}

/**
 * Loại bỏ "dấu vân tay AI": em/en dash, smart quotes, ellipsis, NBSP, bullet...
 * Đảm bảo email chỉ còn ký tự ASCII thường, kể cả khi model lỡ dùng.
 * Giữ nguyên xuống dòng (chỉ gộp space/tab).
 */
function sanitize_pitch(string $t): string
{
    // Em/en dash dùng làm dấu ngắt câu (có khoảng trắng) -> dấu phẩy.
    $t = preg_replace('/[ \t]*[\x{2014}\x{2013}\x{2015}][ \t]*/u', ', ', $t);
    // Các ký tự "AI" còn lại -> ASCII tương đương.
    $t = strtr($t, [
        "\u{201C}" => '"',  "\u{201D}" => '"',
        "\u{2018}" => "'",  "\u{2019}" => "'",
        "\u{2026}" => '...',
        "\u{00A0}" => ' ',  "\u{202F}" => ' ', "\u{2009}" => ' ',
        "\u{2022}" => '-',  "\u{2011}" => '-', "\u{2012}" => '-',
        "\u{200B}" => '',   "\u{FEFF}" => '',
    ]);
    // Dọn lỗi do thay thế.
    $t = preg_replace('/[ \t]+,/', ',', $t);   // " ,"  -> ","
    $t = preg_replace('/,\s*,/', ',', $t);     // ", ," -> ","
    $t = preg_replace('/,\s*\./', '.', $t);    // ", ." -> "."
    $t = preg_replace('/[ \t]{2,}/', ' ', $t); // gộp space (giữ \n)
    return trim($t);
}

/** Viết pitch + chọn hình thức hợp tác bằng Claude. Email tiếng Anh (khách US/EU/AU). */
function claude_pitch(string $company, array $a, array $engHint): ?array
{
    $signals = [
        'company'        => $company,
        'dev_roles'      => $a['dev_count'],
        'long_open_45d'  => count($a['long_open']),
        'contract_roles' => count($a['contract']),
        'top_skills'     => array_slice(array_keys($a['skill_freq']), 0, 6),
        'capabilities'   => array_keys($a['cap_hits']),
        'sample_titles'  => array_map(fn($j) => $j['title'], array_slice($a['dev_jobs'], 0, 8)),
    ];
    $schema = [
        'type' => 'object', 'additionalProperties' => false,
        'properties' => [
            'engagement_model' => ['type' => 'string',
                'enum' => ['Dedicated Team', 'Staff Augmentation / Contract', 'Project-based', 'White-label']],
            'why'   => ['type' => 'string'],
            'pitch' => ['type' => 'string'],
        ],
        'required' => ['engagement_model', 'why', 'pitch'],
    ];
    $prompt = "Bạn là chuyên gia phát triển kinh doanh của Onext Digital — công ty Việt Nam làm "
        . "web/ecommerce, mobile app, custom development, nhận OUTSOURCING cho khách US/EU/AU "
        . "(lợi thế chi phí offshore, giữ chất lượng).\n\n"
        . "Dựa trên tín hiệu tuyển dụng của một công ty mục tiêu (JSON bên dưới), hãy:\n"
        . "1) Chọn engagement_model phù hợp nhất.\n"
        . "2) why: giải thích NGẮN bằng TIẾNG VIỆT cho team sale nội bộ.\n"
        . "3) pitch: viết EMAIL OUTBOUND bằng TIẾNG ANH (khách ở US/EU/AU), ngắn gọn, "
        . "nhắc số liệu cụ thể (số role, role mở lâu, skill), đúng nỗi đau, có CTA nhẹ.\n\n"
        . "VĂN PHONG BẮT BUỘC (rất quan trọng):\n"
        . "- Viết như người thật gõ nhanh, mộc mạc, đồng nghiệp với đồng nghiệp. Câu ngắn.\n"
        . "- CHỈ dùng ký tự ASCII thường. TUYỆT ĐỐI KHÔNG dùng em dash (—), en dash (–), "
        . "dấu ngoặc kép cong, dấu ba chấm …. Nếu cần ngắt câu thì dùng dấu phẩy hoặc chấm.\n"
        . "- KHÔNG dùng từ/cụm dễ vào hòm spam: free, guarantee, risk-free, limited time, "
        . "act now, click here, cheap, lowest price, 100%, no obligation, urgent, buy now, save big.\n"
        . "- KHÔNG sáo rỗng kiểu AI: 'I hope this email finds you well', 'in today's fast-paced world', "
        . "'reach out', 'leverage', 'seamless', 'cutting-edge', 'world-class', 'unlock', 'supercharge', "
        . "'delve', 'game-changer', 'tailored solutions'.\n"
        . "- Subject ngắn, không hype.\n\n"
        . "Tín hiệu:\n" . json_encode($signals, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $r = claude_messages($prompt, $schema, 2000);
    if (!$r || isset($r['_error'])) return null;
    return [
        'model' => $r['engagement_model'] ?? $engHint['model'],
        'why'   => $r['why'] ?? $engHint['why'],
        'pitch' => $r['pitch'] ?? '',
    ];
}

/* ===========================================================================
 * 7. Pipeline dùng chung (CLI + Web)
 * ======================================================================== */

function run_pipeline(string $input): array
{
    $company   = explode('/', preg_replace('~^https?://(www\.)?~i', '', trim($input)))[0];
    $jobSource = 'ats';

    $detect = detect_ats($input);
    if ($detect) {
        $host = strtolower((string) parse_url(normalize_url($input), PHP_URL_HOST));
        if (preg_match('~greenhouse\.io|lever\.co|ashbyhq\.com|smartrecruiters\.com|recruitee\.com~', $host)) {
            $company = $detect['token'];
        }
        $jobs = fetch_jobs($detect['provider'], $detect['token']);
        if (!$jobs) {
            return ['ok' => false, 'error' => 'Dò ra ATS nhưng không kéo được job (board rỗng hoặc API đổi).'];
        }
    } else {
        // Fallback: không có ATS công khai → đọc trang careers + Claude bóc job.
        if (!anthropic_api_key()) {
            return ['ok' => false, 'error' =>
                'Không có ATS công khai và chưa cấu hình ANTHROPIC_API_KEY để bóc job từ trang careers tùy biến.'];
        }
        $careers = fetch_careers_html($input);
        if (!$careers || ($careers['score'] ?? 0) < 1) {
            return ['ok' => false, 'error' => 'Không tìm thấy trang careers hoặc trang không có tín hiệu tuyển dụng.'];
        }
        $jobs = claude_extract_jobs($company, $careers['text'], $careers['url']);
        if (!$jobs) {
            return ['ok' => false, 'error' => 'Đã đọc trang careers nhưng AI không trích được vị trí nào (có thể họ không đang tuyển).'];
        }
        $detect    = ['provider' => 'careers-page (AI)', 'token' => $company];
        $jobSource = 'ai';
    }

    $a   = analyze($jobs);
    $eng = recommend_engagement($a);

    // Pitch: ưu tiên Claude, không có key thì template.
    $aiPitch = anthropic_api_key() ? claude_pitch($company, $a, $eng) : null;
    if ($aiPitch) {
        $eng   = ['model' => $aiPitch['model'], 'why' => $aiPitch['why']];
        $pitch = $aiPitch['pitch'];
    } else {
        $pitch = template_pitch($company, $a, $eng);
    }
    $pitch = sanitize_pitch($pitch); // bỏ ký tự AI dù nguồn nào

    return ['ok' => true, 'company' => $company, 'detect' => $detect,
            'analysis' => $a, 'eng' => $eng, 'pitch' => $pitch,
            'job_source' => $jobSource, 'ai_pitch' => (bool) $aiPitch];
}

/* ===========================================================================
 * 8. CLI
 * ======================================================================== */

function cli_main(array $argv): int
{
    $args = array_slice($argv, 1);
    $json = in_array('--json', $args, true);
    $args = array_values(array_filter($args, fn($x) => $x !== '--json'));

    if (empty($args[0])) {
        fwrite(STDERR, "Usage: php radar.php <website-hoặc-ats-url> [--json]\n");
        return 1;
    }
    fwrite(STDERR, "→ Đang dò ATS cho: {$args[0]}\n");
    $r = run_pipeline($args[0]);
    if (!$r['ok']) {
        fwrite(STDERR, "✗ {$r['error']}\n");
        return 2;
    }
    if ($json) {
        echo json_encode([
            'company' => $r['company'], 'ats' => $r['detect'],
            'score' => $r['analysis']['score'], 'dev_count' => $r['analysis']['dev_count'],
            'long_open' => count($r['analysis']['long_open']),
            'contract' => count($r['analysis']['contract']),
            'cap_hits' => $r['analysis']['cap_hits'], 'skill_freq' => $r['analysis']['skill_freq'],
            'engagement' => $r['eng'], 'pitch' => $r['pitch'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        render_report($r['company'], $r['detect'], $r['analysis'], $r['eng'], $r['pitch']);
    }
    return 0;
}

/* ===========================================================================
 * 9. Web UI
 * ======================================================================== */

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function web_main(): void
{
    $url = trim((string) ($_GET['url'] ?? ''));
    $r = $url !== '' ? run_pipeline($url) : null;

    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Outbound Radar</title>
<style>
  :root{--bg:#f4f5f7;--card:#fff;--line:#e4e7ec;--ink:#1f2937;--mut:#6b7280;--accent:#2563eb;--good:#059669;--warn:#d97706}
  *{box-sizing:border-box}
  body{margin:0;font:15px/1.55 -apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--ink)}
  .wrap{max-width:860px;margin:0 auto;padding:28px 18px 60px}
  h1{font-size:22px;margin:0 0 4px}.sub{color:var(--mut);margin:0 0 22px}
  form{display:flex;gap:8px;margin-bottom:24px}
  input[type=text]{flex:1;padding:11px 13px;border:1px solid var(--line);border-radius:9px;font-size:15px}
  button{padding:11px 20px;border:0;border-radius:9px;background:var(--accent);color:#fff;font-weight:600;cursor:pointer}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:16px}
  .err{border-color:#fca5a5;background:#fef2f2;color:#b91c1c}
  .stats{display:flex;flex-wrap:wrap;gap:18px;margin:0}
  .stat .n{font-size:24px;font-weight:700}.stat .l{color:var(--mut);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  .score{font-size:30px;font-weight:800;color:var(--accent)}
  .chip{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:3px 10px;margin:3px 4px 0 0;font-size:13px}
  .job{display:flex;gap:10px;padding:7px 0;border-bottom:1px solid var(--line);font-size:14px}
  .job:last-child{border:0}.days{min-width:54px;color:var(--warn);font-weight:600}
  .loc{color:var(--mut)}
  h3{font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--mut);margin:0 0 10px}
  pre.pitch{white-space:pre-wrap;background:#f9fafb;border:1px dashed var(--line);border-radius:9px;padding:14px;font:14px/1.6 inherit}
  .badge{display:inline-block;background:var(--good);color:#fff;border-radius:7px;padding:4px 10px;font-weight:600;font-size:13px}
  .tag{font-size:12px;color:var(--mut)}
</style></head><body><div class="wrap">
  <h1>📡 Outbound Radar</h1>
  <p class="sub">Dò tín hiệu thiếu năng lực dev → chấm điểm outsourcing → pitch. Dán website hoặc URL job board.</p>
  <form method="get">
    <input type="text" name="url" placeholder="vd: jobs.ashbyhq.com/Ashby  ·  boards.greenhouse.io/gitlab  ·  acme.com" value="<?= h($url) ?>" autofocus>
    <button type="submit">Quét</button>
  </form>
<?php if ($r && !$r['ok']): ?>
  <div class="card err">✗ <?= h($r['error']) ?></div>
<?php elseif ($r && $r['ok']):
    $a = $r['analysis']; $d = $r['detect']; ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:19px;font-weight:700"><?= h($r['company']) ?></div>
        <div class="tag">ATS: <?= h($d['provider']) ?> · token <?= h($d['token']) ?></div>
      </div>
      <div style="text-align:right"><div class="score"><?= (int)$a['score'] ?><span style="font-size:15px;color:var(--mut)">/100</span></div><div class="l tag">điểm phù hợp</div></div>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
    <div class="stats">
      <div class="stat"><div class="n"><?= (int)$a['total_jobs'] ?></div><div class="l">tổng job</div></div>
      <div class="stat"><div class="n"><?= (int)$a['dev_count'] ?></div><div class="l">job dev</div></div>
      <div class="stat"><div class="n"><?= count($a['long_open']) ?></div><div class="l">mở 45+ ngày</div></div>
      <div class="stat"><div class="n"><?= count($a['contract']) ?></div><div class="l">contract/freelance</div></div>
    </div>
  </div>

  <div class="card">
    <h3>Khớp năng lực Onext</h3>
    <?php foreach ($a['cap_hits'] as $cap => $n): ?><span class="chip"><?= h($cap) ?> · <?= (int)$n ?></span><?php endforeach; ?>
    <?php if (!$a['cap_hits']): ?><span class="tag">—</span><?php endif; ?>
    <h3 style="margin-top:16px">Skill nổi bật</h3>
    <?php foreach (array_slice($a['skill_freq'], 0, 12, true) as $s => $n): ?><span class="chip"><?= h($s) ?> (<?= (int)$n ?>)</span><?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Vị trí dev đang mở (tối đa 12)</h3>
    <?php foreach (array_slice($a['dev_jobs'], 0, 12) as $j):
        $days = $j['days_open'] !== null ? $j['days_open'].'d' : '?'; ?>
      <div class="job"><span class="days">[<?= h($days) ?>]</span>
        <span><a href="<?= h($j['url']) ?>" target="_blank" style="color:var(--ink);text-decoration:none"><?= h($j['title']) ?></a>
        <?php if ($j['location']): ?><span class="loc">· <?= h($j['location']) ?></span><?php endif; ?></span></div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Đề xuất hợp tác</h3>
    <span class="badge"><?= h($r['eng']['model']) ?></span>
    <p class="tag" style="margin:10px 0 0"><?= h($r['eng']['why']) ?></p>
    <h3 style="margin-top:18px">Pitch (nháp · template — AI cắm sau)</h3>
    <pre class="pitch"><?= h($r['pitch']) ?></pre>
  </div>
<?php endif; ?>
</div></body></html><?php
}

/* ===========================================================================
 * 10. Dispatch
 * ======================================================================== */

// Cho phép include như thư viện (trang app /outbound-radar) mà không tự render.
if (!defined('OUTBOUND_RADAR_LIB')) {
    if (PHP_SAPI === 'cli') {
        exit(cli_main($argv));
    }
    web_main();
}
