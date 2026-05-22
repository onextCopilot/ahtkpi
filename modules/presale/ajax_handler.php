<?php
require_once __DIR__ . '/../../config/config.php';
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Tăng thời gian timeout cho các request AI dài
set_time_limit(300);
ini_set('memory_limit', '512M');

// === Serve thumbnail image ===
if (isset($_GET['action']) && $_GET['action'] === 'serve_thumb') {
    $file = basename($_GET['file'] ?? '');
    if (!$file) {
        http_response_code(404);
        exit;
    }
    $thumbPath = __DIR__ . '/vision/thumbnails/' . $file;
    if (!file_exists($thumbPath)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/jpeg');
    readfile($thumbPath);
    exit;
}

header('Content-Type: application/json');

try {
    $conn->query("ALTER TABLE presale_session_files MODIFY COLUMN session_id INT(11) NULL");
    $conn->query("ALTER TABLE presale_session_files ADD COLUMN ai_file_id VARCHAR(255) NULL AFTER extracted_text");
} catch (Exception $e) {
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'send_message') {
    $content = $_POST['content'] ?? '';
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Empty message']);
        exit;
    }

    $action_key = $_POST['action_key'] ?? 'qna';
    $session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $system_prompt = "Bạn là một Senior Presale Consultant chuyên nghiệp của công ty Onext Digital (AHT Tech). 
Nhiệm vụ của bạn là tư vấn giải pháp kỹ thuật và lập các tài liệu báo giá (SOW, Estimation, Proposal) chất lượng cao cho khách hàng quốc tế.

QUY TẮC PHẢN HỒI:
1. LUÔN trình bày chuyên nghiệp, sử dụng bảng biểu (markdown) cho các danh mục báo giá.
2. Ngôn ngữ: Nếu khách hàng hỏi tiếng Việt, trả lời tiếng Việt nhưng các thuật ngữ kỹ thuật có thể dùng tiếng Anh.
3. Độ chính xác: Dựa sát vào DỮ LIỆU THIẾT KẾ (nếu có) và KNOWLEDGE BASE để đưa ra giải pháp. 
4. Nếu thông tin chưa đủ, hãy chủ động đặt câu hỏi tư vấn thêm cho khách hàng.
5. Luôn đề xuất các giải pháp tối ưu về công nghệ (Shopify, Magento, React...) phù hợp với yêu cầu.";

    if ($project_id) {
        $stmt_files = $conn->prepare("SELECT file_name, extracted_text FROM presale_session_files WHERE project_id = ?");
        $stmt_files->bind_param("i", $project_id);
        $stmt_files->execute();
        $res_files = $stmt_files->get_result();
        $knowledge_base = [];
        while ($f = $res_files->fetch_assoc()) {
            $knowledge_base[] = "--- TÀI LIỆU: {$f['file_name']} ---\n{$f['extracted_text']}";
        }

        $hourly_rate = $_POST['hourly_rate'] ?? '15';
        $platform = $_POST['platform'] ?? 'N/A';
        $doc_type = $_POST['doc_type'] ?? 'Xây dựng tài liệu báo giá';
        $vision_summary = $_POST['vision_summary'] ?? '';

        $project_context = "### BỐI CẢNH DỰ ÁN HIỆN TẠI:\n";
        $project_context .= "- Loại tài liệu: $doc_type\n";
        $project_context .= "- Nền tảng: $platform\n";
        $project_context .= "- Đơn giá (Rate): $hourly_rate USD/h\n";

        if (!empty($vision_summary)) {
            $vision = json_decode($vision_summary, true);
            $project_context .= "\n### DỮ LIỆU TỪ THIẾT KẾ (AI Vision):\n";
            foreach ($vision as $v) {
                $project_context .= "- **" . ($v['name'] ?? 'Section') . "**: " . ($v['description'] ?? '') . "\n";
                if (!empty($v['features'])) $project_context .= "  + Features: " . implode(', ', $v['features']) . "\n";
                if (!empty($v['style'])) $project_context .= "  + Style: " . $v['style'] . "\n";
            }
        }

        if (!empty($knowledge_base)) {
            $project_context .= "\n### KNOWLEDGE BASE (Tài liệu đính kèm):\n" . implode("\n\n", $knowledge_base);
        }

        $system_prompt .= "\n\n" . $project_context;
    }

    require_once __DIR__ . '/../../libs/AiHiveService.php';
    $aiService = new AiHiveService();
    $messages = [['role' => 'system', 'content' => $system_prompt]];

    if ($session_id) {
        $stmt_hist = $conn->prepare("SELECT role, content FROM presale_chat_messages WHERE session_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt_hist->bind_param("i", $session_id);
        $stmt_hist->execute();
        $history_msgs = [];
        $res_hist = $stmt_hist->get_result();
        while ($row_hist = $res_hist->fetch_assoc()) {
            $history_msgs[] = ['role' => $row_hist['role'], 'content' => $row_hist['content']];
        }
        $messages = array_merge($messages, array_reverse($history_msgs));
    }
    $messages[] = ['role' => 'user', 'content' => $content];

    $apiResponse = $aiService->chatCompletion($messages, $action_key);
    if ($apiResponse['success']) {
        $reply = $apiResponse['data']['answer'] ?? $apiResponse['data']['choices'][0]['message']['content'] ?? "AI không trả về nội dung.";
        $db_res = saveChatToDb($conn, $session_id, $project_id, $content, $reply, null);
        echo json_encode(['success' => true, 'reply' => $reply, 'session_id' => $db_res['session_id'], 'new_chat' => $db_res['new_chat']]);
    } else {
        echo json_encode(['success' => false, 'message' => $apiResponse['message']]);
    }
    exit;
}

function saveChatToDb($conn, $session_id, $project_id, $content, $reply, $ai_conversation_id)
{
    $new_chat = false;
    $user_id = (int) $_SESSION['user_id'];
    if (!$project_id) {
        $title = mb_substr($content, 0, 50) . "...";
        $stmt_p = $conn->prepare("INSERT INTO presale_projects (user_id, name) VALUES (?, ?)");
        $stmt_p->bind_param("is", $user_id, $title);
        $stmt_p->execute();
        $project_id = $conn->insert_id;
    }
    if (!$session_id) {
        $title = mb_substr($content, 0, 50) . "...";
        $stmt = $conn->prepare("INSERT INTO presale_chat_sessions (project_id, user_id, title) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $project_id, $user_id, $title);
        $stmt->execute();
        $session_id = $conn->insert_id;
        $new_chat = true;
    }
    $stmt_u = $conn->prepare("INSERT INTO presale_chat_messages (session_id, role, content) VALUES (?, 'user', ?)");
    $stmt_u->bind_param("is", $session_id, $content);
    $stmt_u->execute();
    $stmt_a = $conn->prepare("INSERT INTO presale_chat_messages (session_id, role, content) VALUES (?, 'assistant', ?)");
    $stmt_a->bind_param("is", $session_id, $reply);
    $stmt_a->execute();
    return ['session_id' => $session_id, 'project_id' => $project_id, 'new_chat' => $new_chat];
}

// === ACTION: ANALYZE DESIGN IMAGE (DIRECT SINGLE IMAGE) ===
if ($action === 'analyze_design_image') {
    if (!isset($_FILES['mockup_image'])) {
        echo json_encode(['success' => false, 'message' => 'Không có ảnh được tải lên']);
        exit;
    }

    $uploadDir = __DIR__ . '/vision/uploads/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);
    $originalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['mockup_image']['name']);
    $savedPath = $uploadDir . $originalName;
    move_uploaded_file($_FILES['mockup_image']['tmp_name'], $savedPath);

    require_once __DIR__ . '/../../libs/AiHiveService.php';
    $ai = new AiHiveService();
    if (!$ai->isVisionConfigured()) {
        echo json_encode(['success' => false, 'message' => 'Vision AI chưa cấu hình.']);
        exit;
    }

    // Upload file lên AI
    $uploadRes = $ai->uploadVisionFile($savedPath, $originalName, mime_content_type($savedPath));
    if (!$uploadRes['success']) {
        echo json_encode(['success' => false, 'message' => 'Upload thất bại: ' . $uploadRes['message']]);
        exit;
    }

    // Prompt "Kỷ luật sắt" - Mechanical OCR Scanner
    $prompt = <<<PROMPT
OBJECTIVE: PRESALE ESTIMATION SCANNER.
You are a senior analyst breaking down a design into work packages for a technical quote (SOW/Estimation).

TASK:
Identify every logical block/part of the webpage and list all functional elements inside them.

FOR EACH BLOCK:
1. name: A clear functional name (e.g., "Header", "Hero Section", "Stay With Us Intro", "Room Listing", "Footer"). If there is a prominent heading text, include it in parentheses, e.g., "Intro (STAY WITH US)".
2. description: Short summary of what this part does.
3. features: LIST EVERY VISIBLE ELEMENT/WIDGET. Be very detailed (e.g., "Search bar", "Language switcher", "Social icons", "Image slider", "Booking form").
4. components: [image, text, button, input, icon, social_link, map, form].

STRICT RULES:
- SCAN TOP TO BOTTOM. Do not skip any section.
- Focus on ELEMENTS that require development effort.
- Output MUST be valid JSON only.

OUTPUT FORMAT:
{
  "sections": [
    {
      "name": "Functional Name (Visible Heading)",
      "description": "...",
      "features": ["Element 1", "Element 2", "Element 3"],
      "components": ["image", "button"],
      "crop": {"x": 0, "y": 0, "width": 100, "height": 10}
    }
  ]
}
PROMPT;

    $visionRes = $ai->runVisionAnalysis($uploadRes['data']['id'], $prompt);

    if (!$visionRes['success']) {
        echo json_encode(['success' => false, 'message' => $visionRes['message']]);
        exit;
    }

    $sections = $visionRes['data']['sections'] ?? [];
    foreach ($sections as $idx => &$sec) {
        $sec['index'] = $idx + 1;
        $sec['summary'] = ($sec['name'] ?? 'Section') . ': ' . ($sec['description'] ?? '');
    }

    // Lưu vào database
    $projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    if ($projectId > 0) {
        try {
            $conn->query("ALTER TABLE presale_projects ADD COLUMN vision_data LONGTEXT NULL");
        } catch (Exception $e) {
        }
        $stmt = $conn->prepare("UPDATE presale_projects SET vision_data = ? WHERE id = ?");
        $json = json_encode($sections);
        $stmt->bind_param("si", $json, $projectId);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'data' => $sections]);
    exit;
}

if ($action === 'get_project_data') {
    $projectId = (int) $_POST['project_id'];
    $stmt = $conn->prepare("SELECT * FROM presale_projects WHERE id = ?");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    echo json_encode([
        'success' => true,
        'data' => $project,
        'vision_data' => !empty($project['vision_data']) ? json_decode($project['vision_data'], true) : null
    ]);
    exit;
}

if ($action === 'get_file_content') {
    $file_id = (int)$_POST['file_id'];
    try {
        $stmt = $conn->prepare("SELECT file_name, extracted_text FROM presale_session_files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode(['success' => true, 'file_name' => $row['file_name'], 'content' => $row['extracted_text']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'export_word') {
    $html = $_POST['html_content'] ?? '';
    // Xoá các nút bấm khỏi nội dung export
    $html = preg_replace('/<button.*?>.*?<\/button>/si', '', $html);
    
    header("Content-Type: application/vnd.ms-word");
    header("Content-Disposition: attachment; filename=AI_Presale_Proposal.doc");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: 'Times New Roman', serif; line-height: 1.5; padding: 20px; }
            table { border-collapse: collapse; width: 100%; border: 1px solid #000; margin-bottom: 15px; }
            th, td { border: 1px solid #000; padding: 10px; text-align: left; vertical-align: top; }
            th { background-color: #f3f4f6; font-weight: bold; }
            h1, h2, h3 { color: #1e3a8a; }
            p { margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <h1 style='text-align: center;'>BẢN ĐỀ XUẤT GIẢI PHÁP (AI DRAFT)</h1>
        <p style='text-align: right; font-style: italic;'>Ngày tạo: " . date('d/m/Y H:i') . "</p>
        <hr>
        <div class='content'>
            $html
        </div>
        <br>
        <p style='font-size: 10pt; color: #666;'>Tài liệu được tạo tự động bởi AI Presale Assistant.</p>
    </body>
    </html>";
    exit;
}

if ($action === 'load_history') {
    $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid session_id']);
        exit;
    }

    $messages = [];
    try {
        $stmt = $conn->prepare("SELECT role, content FROM presale_chat_messages WHERE session_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $messages[] = $row;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    exit;
}

if ($action === 'create_project') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Tên dự án không được để trống']);
        exit;
    }
    try {
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO presale_projects (user_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $name);
        $stmt->execute();
        $project_id = $conn->insert_id;
        
        $stmt2 = $conn->prepare("INSERT INTO presale_chat_sessions (project_id, user_id, title) VALUES (?, ?, 'Chat mặc định')");
        $stmt2->bind_param("ii", $project_id, $user_id);
        $stmt2->execute();

        echo json_encode(['success' => true, 'project_id' => $project_id, 'title' => $name]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'create_chat') {
    $project_id = (int)$_POST['project_id'];
    try {
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO presale_chat_sessions (project_id, user_id, title) VALUES (?, ?, 'Chat mới')");
        $stmt->bind_param("ii", $project_id, $user_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'session_id' => $conn->insert_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_chat') {
    $session_id = (int)$_POST['session_id'];
    try {
        // Xoá tin nhắn
        $stmt_m = $conn->prepare("DELETE FROM presale_chat_messages WHERE session_id = ?");
        $stmt_m->bind_param("i", $session_id);
        $stmt_m->execute();

        // Xoá session
        $stmt_s = $conn->prepare("DELETE FROM presale_chat_sessions WHERE id = ?");
        $stmt_s->bind_param("i", $session_id);
        $stmt_s->execute();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_project') {
    $project_id = (int)$_POST['project_id'];
    try {
        $stmtF = $conn->prepare("DELETE FROM presale_session_files WHERE project_id = ?");
        $stmtF->bind_param("i", $project_id);
        $stmtF->execute();

        // Xoá tin nhắn
        $stmtM = $conn->prepare("DELETE FROM presale_chat_messages WHERE session_id IN (SELECT id FROM presale_chat_sessions WHERE project_id = ?)");
        $stmtM->bind_param("i", $project_id);
        $stmtM->execute();

        // Xoá chat sessions
        $stmtC = $conn->prepare("DELETE FROM presale_chat_sessions WHERE project_id = ?");
        $stmtC->bind_param("i", $project_id);
        $stmtC->execute();

        $stmtS = $conn->prepare("DELETE FROM presale_project_shares WHERE project_id = ?");
        $stmtS->bind_param("i", $project_id);
        $stmtS->execute();

        $stmt = $conn->prepare("DELETE FROM presale_projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_users') {
    $users = [];
    try {
        // Try selecting username or full_name, adjust dynamically based on schema if needed
        // Assuming 'username' and 'id' are standard columns in your 'users' table
        $res = $conn->query("SELECT id, username FROM users WHERE id != " . (int)$_SESSION['user_id']);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $users[] = $r;
            }
        }
    } catch(Exception $e){}
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

if ($action === 'share_project') {
    $project_id = (int)$_POST['project_id'];
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $conn->prepare("SELECT id FROM presale_project_shares WHERE project_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt2 = $conn->prepare("INSERT INTO presale_project_shares (project_id, user_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $project_id, $user_id);
            $stmt2->execute();
        }
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'upload_project_file') {
    $project_id = (int)$_POST['project_id'];
    
    if (!$project_id) {
        echo json_encode(['success' => false, 'message' => 'Thiếu Project ID']);
        exit;
    }

    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy file gửi lên']);
        exit;
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'Lỗi upload: ' . $_FILES['file']['error'];
        if ($_FILES['file']['error'] === UPLOAD_ERR_INI_SIZE) $error_msg = 'File quá lớn so với cấu hình server (upload_max_filesize)';
        if ($_FILES['file']['error'] === UPLOAD_ERR_FORM_SIZE) $error_msg = 'File quá lớn so với giới hạn của Form';
        if ($_FILES['file']['error'] === UPLOAD_ERR_PARTIAL) $error_msg = 'File chỉ được tải lên một phần';
        if ($_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) $error_msg = 'Không có file nào được tải lên';
        
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit;
    }

    $filename = $_FILES['file']['name'];
    $tmpName = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $extracted_text = "";
    if ($ext === 'txt') {
        $extracted_text = file_get_contents($tmpName);
    } elseif ($ext === 'docx') {
        $zip = new ZipArchive;
        if ($zip->open($tmpName) === true) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $xmlContent = $zip->getFromIndex($index);
                $extracted_text = strip_tags(str_replace(['<w:p>', '</w:p>'], ["\n", "\n"], $xmlContent));
            }
            $zip->close();
        }
    } elseif ($ext === 'pdf') {
        if (class_exists('Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($tmpName);
                $extracted_text = $pdf->getText();
            } catch (Exception $e) {
                $extracted_text = "[Lỗi đọc PDF: " . $e->getMessage() . "]";
            }
        } else {
            $extracted_text = "[Hệ thống chưa cài đặt thư viện đọc PDF. Vui lòng chạy lệnh: composer require smalot/pdfparser]";
        }
    }

    if (!$extracted_text) {
        $extracted_text = "[Không trích xuất được text từ file này]";
    }

    $ai_file_id = null;
    require_once __DIR__ . '/../../libs/AiHiveService.php';
    $aiService = new AiHiveService();
    if ($aiService->isConfigured()) {
        $mimeType = mime_content_type($tmpName);
        if (!$mimeType) $mimeType = 'application/octet-stream';
        $uploadRes = $aiService->uploadFile($tmpName, $filename, $mimeType);
        if ($uploadRes['success'] && isset($uploadRes['data']['id'])) {
            $ai_file_id = $uploadRes['data']['id'];
        }
    }

    try {
        if ($ai_file_id) {
            $stmt = $conn->prepare("INSERT INTO presale_session_files (project_id, session_id, file_name, extracted_text, ai_file_id) VALUES (?, NULL, ?, ?, ?)");
            $stmt->bind_param("isss", $project_id, $filename, $extracted_text, $ai_file_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO presale_session_files (project_id, session_id, file_name, extracted_text) VALUES (?, NULL, ?, ?)");
            $stmt->bind_param("iss", $project_id, $filename, $extracted_text);
        }
        $stmt->execute();
        echo json_encode(['success' => true, 'file_id' => $conn->insert_id, 'file_name' => $filename]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_project_files') {
    $project_id = (int)$_POST['project_id'];
    $files = [];
    try {
        $stmt = $conn->prepare("SELECT id, file_name FROM presale_session_files WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $files[] = $row;
        }
    } catch (Exception $e) {}
    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

if ($action === 'delete_project_file') {
    $file_id = (int)$_POST['file_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM presale_session_files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// Fallback for other actions
echo json_encode(['success' => false, 'message' => 'Invalid action']);
