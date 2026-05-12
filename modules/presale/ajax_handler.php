<?php
require_once __DIR__ . '/../../config/config.php';
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Tăng thời gian timeout cho các request AI dài
set_time_limit(300);
ini_set('memory_limit', '512M');

header('Content-Type: application/json');

// Tự động sửa cấu trúc bảng nếu chưa cho phép NULL (fix lỗi upload)
try {
    $conn->query("ALTER TABLE presale_session_files MODIFY COLUMN session_id INT(11) NULL");
    $conn->query("ALTER TABLE presale_session_files ADD COLUMN ai_file_id VARCHAR(255) NULL AFTER extracted_text");
} catch (Exception $e) {}

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

    // --- BƯỚC 1: Lấy cấu hình System Prompt từ Database ---
    // Giả sử client gửi kèm action_key, nếu không mặc định là 'qna'
    $action_key = $_POST['action_key'] ?? 'qna';
    $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $system_prompt = "Bạn là trợ lý AI Sale/Presale thông minh."; // Fallback
    
    // --- Xử lý file đính kèm (nếu có) ---
    $file_context = "";
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
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
            // Tự động sử dụng pdfparser nếu đã được cài đặt qua Composer
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
        } else {
            $extracted_text = "[Định dạng file chưa được hỗ trợ trích xuất text]";
        }
        
        // Cắt bớt text nếu quá dài để tránh vượt giới hạn Token của AI
        $extracted_text = mb_substr($extracted_text, 0, 5000); 

        // Không nối trực tiếp vào $content để tránh làm xấu giao diện và DB
        // $content .= $file_context; 
    }
    
    // Lấy thông tin ai_conversation_id của session hiện tại
    $ai_conversation_id = null;
    $is_first_message = true;
    if ($session_id) {
        try {
            $stmt_s = $conn->prepare("SELECT ai_conversation_id FROM presale_chat_sessions WHERE id = ?");
            $stmt_s->bind_param("i", $session_id);
            $stmt_s->execute();
            $ai_conversation_id = $stmt_s->get_result()->fetch_assoc()['ai_conversation_id'] ?? null;

            // Kiểm tra xem đã có tin nhắn nào trong session chưa
            $stmt_count = $conn->prepare("SELECT COUNT(*) FROM presale_chat_messages WHERE session_id = ?");
            $stmt_count->bind_param("i", $session_id);
            $stmt_count->execute();
            $msg_count = $stmt_count->get_result()->fetch_row()[0];
            if ($msg_count > 0) $is_first_message = false;
        } catch (Exception $e) {}
    }

    // ĐÃ LOẠI BỎ: Không nối trực tiếp vào $content ở đây. 
    // Việc truyền dữ liệu tài liệu đã được xử lý chuyên nghiệp qua mảng $messages (RAG logic) ở phía dưới.


    try {
        $stmt = $conn->prepare("SELECT system_prompt FROM presale_prompts WHERE action_key = ?");
        $stmt->bind_param("s", $action_key);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $system_prompt = $row['system_prompt'];
        }
    } catch (Exception $e) {}

    // --- BƯỚC 1.5: RAG - Tích hợp kiến thức từ tài liệu dự án ---
    $project_context = "";
    $ai_files = [];
    if ($project_id) {
        $has_ai_file_col = true;
        try {
            $conn->query("SELECT ai_file_id FROM presale_session_files LIMIT 1");
        } catch (Exception $e) { $has_ai_file_col = false; }

        if ($has_ai_file_col) {
            $stmt_files = $conn->prepare("SELECT file_name, extracted_text, ai_file_id FROM presale_session_files WHERE project_id = ?");
        } else {
            $stmt_files = $conn->prepare("SELECT file_name, extracted_text, NULL as ai_file_id FROM presale_session_files WHERE project_id = ?");
        }
        $stmt_files->bind_param("i", $project_id);
        $stmt_files->execute();
        $res_files = $stmt_files->get_result();
        
        $knowledge_base = [];
        $ai_files = [];
        while ($f = $res_files->fetch_assoc()) {
            $knowledge_base[] = "--- TÀI LIỆU: {$f['file_name']} ---\n{$f['extracted_text']}";
            if (!empty($f['ai_file_id'])) {
                $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    $ai_files[] = ['type' => 'image', 'transfer_method' => 'local_file', 'upload_file_id' => $f['ai_file_id']];
                }
            }
        }
        
        $hourly_rate = $_POST['hourly_rate'] ?? '15';
        $platform = $_POST['platform'] ?? 'N/A';
        $project_type = $_POST['project_type'] ?? 'N/A';
        $budget = $_POST['budget'] ?? '0';
        $customer_name = $_POST['customer_name'] ?? 'N/A';
        $doc_type = $_POST['doc_type'] ?? 'Xây dựng tài liệu báo giá';
        $figma_summary = $_POST['figma_summary'] ?? '';
        $vision_summary = $_POST['vision_summary'] ?? '';

        $project_context = "### CẤU HÌNH DỰ ÁN (PROJECT CONFIGURATION):\n";
        $project_context .= "- Khách hàng (Client): $customer_name\n";
        $project_context .= "- Loại tài liệu/Mục tiêu: **$doc_type**\n";
        
        if (!empty($figma_summary)) {
            $figma = json_decode($figma_summary, true);
            $project_context .= "- DỮ LIỆU THIẾT KẾ (FIGMA): Dự án \"{$figma['name']}\"\n";
            foreach ($figma['pages'] as $page) {
                $project_context .= "  + Trang (Page): {$page['name']}\n";
                foreach ($page['screens'] as $screen) {
                    $sections = !empty($screen['sections']) ? " (Gồm các phần: " . implode(', ', $screen['sections']) . ")" : "";
                    $project_context .= "    - Màn hình (Screen): {$screen['name']}$sections\n";
                }
            }
        }

        if (!empty($vision_summary)) {
            $vision = json_decode($vision_summary, true);
            $project_context .= "- DỮ LIỆU PHÂN TÍCH ẢNH MOCKUP (LOCAL VISION):\n";
            foreach ($vision as $v) {
                $project_context .= "  + " . $v['name'] . ": " . $v['summary'] . "\n";
            }
            $project_context .= "  + CHỈ THỊ: Đây là cấu trúc bóc tách từ ảnh thiết kế thực tế. Hãy lập SOW dựa trên các khối nội dung này.\n";
        }
        $project_context .= "- Nền tảng: $platform | Loại hình: $project_type | Rate: $hourly_rate USD/h\n";
        if (floatval($budget) > 0) {
            $project_context .= "- NGÂN SÁCH CỦA KHÁCH HÀNG: $budget USD\n";
            $project_context .= "- CHỈ THỊ QUAN TRỌNG: Bạn PHẢI cân đối khối lượng công việc và số giờ sao cho tổng chi phí (Total Project Cost) xấp xỉ hoặc KHÔNG VƯỢT QUÁ ngân sách $budget USD. Nếu yêu cầu vượt quá khả năng tài chính này, hãy chủ động tư vấn cắt giảm các tính năng không quan trọng hoặc chia nhỏ giai đoạn.\n";
        }
        $project_context .= "\n";

        // Specific instructions based on Document Type
        if ($doc_type === 'Xây dựng tài liệu báo giá') {
            $project_context .= "### CHỈ THỊ VỀ TÀI LIỆU BÁO GIÁ:\n";
            $project_context .= "Tập trung vào tính chính xác của Estimation và SOW. Đảm bảo các bảng từ I đến VI đầy đủ và chuyên nghiệp theo định dạng quy chuẩn.\n\n";
        } else if ($doc_type === 'Xây dựng Sale Pitch') {
             $project_context .= "### CHỈ THỊ VỀ SALE PITCH:\n";
             $project_context .= "Tập trung vào lợi ích (benefits), giải pháp giá trị (value proposition), và tại sao khách hàng nên chọn AHT cho dự án này. Giọng văn nên thuyết phục, tự tin và nhấn mạnh vào thế mạnh cạnh tranh.\n\n";
        } else if ($doc_type === 'Xây dựng Tài liệu giải pháp') {
             $project_context .= "### CHỈ THỊ VỀ GIẢI PHÁP KỸ THUẬT:\n";
             $project_context .= "Tập trung vào kiến trúc hệ thống (Architecture), sơ đồ luồng dữ liệu (Data Flow), và các giải pháp kỹ thuật chi tiết để giải quyết triệt để các bài toán của khách hàng.\n\n";
        } else if ($doc_type === 'Phân tích & Tóm tắt RFP') {
             $project_context .= "### CHỈ THỊ VỀ PHÂN TÍCH RFP:\n";
             $project_context .= "Tập trung vào việc trích xuất các yêu cầu cốt lõi, các điểm cần làm rõ (clarifications), các rủi ro tiềm ẩn và các mốc thời gian quan trọng từ tài liệu RFP của khách hàng.\n\n";
        }

        $project_context .= "### QUY TẮC PHẢI TUÂN THỦ:\n";
        $project_context .= "1. Đọc kỹ dữ liệu 'KNOWLEDGE BASE' bên dưới để trích xuất scope. KHÔNG hỏi lại những gì đã có.\n";
        $project_context .= "2. Bắt buộc sử dụng đúng Platform ($platform) để tư vấn Tech Stack và rủi ro.\n";
        $project_context .= "3. Nếu là tài liệu báo giá, Output PHẢI luôn bao gồm đủ 6 phần (I đến VI) theo định dạng bảng Markdown.\n\n";

        $project_context .= "#### I. Development Scope\n[# | Process | Apply or Not | Remarks]\n\n";
        $project_context .= "#### II. Development Estimation\n[No | Screen/Function | Children function | Note | Time (h) | AHT's questions | Client's answers]\n\n";
        $project_context .= "#### III. Project Summary\nBảng dọc. Các hàng: [Total development time | Testing and QA | Project Management | Total time | Total working days | Hourly rate ($hourly_rate) | Total project cost].\n\n";
        $project_context .= "#### IV. Tech Stack Recommendation\nBảng: [Category | Component | Rationale] (Tối ưu cho $platform).\n\n";
        $project_context .= "#### V. Risk Assessment & Mitigation\nBảng: [Risk Type | Potential Impact | Mitigation Strategy]. Phân tích ít nhất 3 rủi ro kỹ thuật hoặc vận hành.\n\n";
        $project_context .= "#### VI. Implementation Roadmap\nBảng: [Phase | Estimated Duration | Key Deliverables]. Ước tính các giai đoạn dựa trên khối lượng công việc.\n\n";

        if (!empty($knowledge_base)) {
            $project_context .= "<knowledge_base>\n" . implode("\n\n", $knowledge_base) . "\n</knowledge_base>";
        }

        if (mb_strlen($project_context) > 15000) {
            $project_context = mb_substr($project_context, 0, 15000) . "... [Dữ liệu cắt bớt]";
        }
    }

    if (!empty($project_context)) {
        $system_prompt .= "\n\n" . $project_context;
    }

    // --- BƯỚC 2: Gọi AI Agent API ---
    require_once __DIR__ . '/../../libs/AiHiveService.php';
    $aiService = new AiHiveService();
    
    $messages = [['role' => 'system', 'content' => $system_prompt]];

    if ($session_id && !$ai_conversation_id) {
        try {
            // Chỉ lấy 5 tin nhắn gần nhất để tránh quá tải Token/Dung lượng
            $stmt_hist = $conn->prepare("SELECT role, content FROM presale_chat_messages WHERE session_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt_hist->bind_param("i", $session_id);
            $stmt_hist->execute();
            $res_hist = $stmt_hist->get_result();
            
            $history_msgs = [];
            while ($row_hist = $res_hist->fetch_assoc()) {
                // Cắt bớt mỗi tin nhắn cũ nếu quá dài (max 2000 ký tự mỗi tin)
                $truncated_content = mb_strlen($row_hist['content']) > 2000 ? mb_substr($row_hist['content'], 0, 2000) . "..." : $row_hist['content'];
                $history_msgs[] = ['role' => $row_hist['role'], 'content' => $truncated_content];
            }
            // Đảo ngược lại để đúng thứ tự thời gian
            $messages = array_merge($messages, array_reverse($history_msgs));
            
        } catch (Exception $e) {}
    }

    // Luôn thêm tin nhắn hiện tại của người dùng
    $messages[] = [
        'role' => 'user',
        'content' => $content
    ];

    // ------------------------------------------------
    // ------------------------------------------------

    $use_real_api = $aiService->isConfigured();
    $is_streaming = isset($_POST['stream']) && $_POST['stream'] === 'true';

    if ($is_streaming) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable buffering for Nginx

        if (!$use_real_api) {
            $reply = "Hệ thống AI chưa được cấu hình. Vui lòng kiểm tra API Key trong Database (bảng aihive_settings).";
            echo "data: " . json_encode(['text' => $reply]) . "\n\n";
            echo "data: [DONE]\n\n";
            saveChatToDb($conn, $session_id, $project_id, $content, $reply, $ai_conversation_id);
            exit;
        }

        $full_reply = "";
        $current_conversation_id = $ai_conversation_id;

        $aiService->streamChatCompletion($messages, function($chunk) use (&$full_reply, &$current_conversation_id) {
            $lines = explode("\n", $chunk);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data:') === 0) {
                    $json_str = trim(substr($line, 5));
                    if ($json_str === '[DONE]' || empty($json_str)) continue;
                    
                    $data = json_decode($json_str, true);
                    if ($data) {
                        $text = "";
                        // 1. Dify/AiHive Chat format
                        if (isset($data['answer'])) {
                            $text = $data['answer'];
                        } 
                        // 2. Dify/AiHive Workflow/Node format
                        elseif (isset($data['data']['text'])) {
                            $text = $data['data']['text'];
                        }
                        // 3. OpenAI format
                        elseif (isset($data['choices'][0]['delta']['content'])) {
                            $text = $data['choices'][0]['delta']['content'];
                        }
                        // 4. Generic text field
                        elseif (isset($data['text'])) {
                            $text = $data['text'];
                        }
                        
                        if ($text !== "") {
                            $full_reply .= $text;
                            echo "data: " . json_encode(['text' => $text]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                        
                        if (isset($data['conversation_id'])) {
                            $current_conversation_id = $data['conversation_id'];
                        }
                    }
                } else {
                    // Nếu nhận được raw JSON lỗi từ API (không có prefix data:)
                    if (!empty($line) && strpos($line, '{') === 0) {
                        $err_data = json_decode($line, true);
                        $err_msg = $err_data['message'] ?? $err_data['code'] ?? $line;
                        echo "data: " . json_encode(['text' => "\n[API Error: $err_msg]"]) . "\n\n";
                        ob_flush(); flush();
                    }
                }
            }
        }, $action_key, $ai_conversation_id, $ai_files);

        // Sau khi xong stream, lưu vào DB
        saveChatToDb($conn, $session_id, $project_id, $content, $full_reply, $current_conversation_id);
        
        echo "data: [DONE]\n\n";
        exit;
    }

    if ($use_real_api) {
        $apiResponse = $aiService->chatCompletion($messages, $action_key, $ai_conversation_id, $ai_files);
        if ($apiResponse['success']) {
            if (isset($apiResponse['data']['conversation_id'])) {
                $ai_conversation_id = $apiResponse['data']['conversation_id'];
            }
            if (isset($apiResponse['data']['data']['outputs']) && is_array($apiResponse['data']['data']['outputs'])) {
                $reply = reset($apiResponse['data']['data']['outputs']);
            } elseif (isset($apiResponse['data']['answer'])) {
                $reply = $apiResponse['data']['answer'];
            } elseif (isset($apiResponse['data']['choices'][0]['message']['content'])) {
                $reply = $apiResponse['data']['choices'][0]['message']['content'];
            } else {
                $reply = "AI không trả về nội dung hợp lệ.";
            }
        } else {
            $reply = "Lỗi kết nối AI: " . ($apiResponse['message'] ?? 'Unknown error');
        }
    } else {
        sleep(1);
        $lower_content = mb_strtolower($content);
        if (strpos($lower_content, 'sow') !== false || strpos($lower_content, 'proposal') !== false) {
            $reply = "Dưới đây là một bản nháp SOW sơ bộ dựa trên yêu cầu của bạn...\n\n*Lưu ý: Đây chỉ là bản nháp tự động.*";
        } else {
            $reply = "Hệ thống đang trong giai đoạn Mockup. Bạn đã hỏi: " . htmlspecialchars($content);
        }
    }

    $db_res = saveChatToDb($conn, $session_id, $project_id, $content, $reply, $ai_conversation_id);

    echo json_encode([
        'success' => true,
        'reply' => $reply,
        'session_id' => $db_res['session_id'],
        'project_id' => $db_res['project_id'],
        'new_chat' => $db_res['new_chat']
    ]);
    exit;
}

/**
 * Helper function to save chat messages and manage sessions/projects
 */
function saveChatToDb($conn, $session_id, $project_id, $content, $reply, $ai_conversation_id) {
    $new_chat = false;
    try {
        $user_id = (int)$_SESSION['user_id'];
        if (!$project_id) {
            $title = mb_substr($content, 0, 50) . "...";
            $stmt_p = $conn->prepare("INSERT INTO presale_projects (user_id, name) VALUES (?, ?)");
            $stmt_p->bind_param("is", $user_id, $title);
            if ($stmt_p->execute()) $project_id = $conn->insert_id;
        }

        if (!$session_id && $project_id) {
            $title = mb_substr($content, 0, 50) . "...";
            $stmt = $conn->prepare("INSERT INTO presale_chat_sessions (project_id, user_id, title) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $project_id, $user_id, $title);
            if ($stmt->execute()) {
                $session_id = $conn->insert_id;
                $new_chat = true;
            }
        } else if ($session_id) {
            $stmt_check = $conn->prepare("SELECT title FROM presale_chat_sessions WHERE id = ?");
            $stmt_check->bind_param("i", $session_id);
            $stmt_check->execute();
            $current_title = $stmt_check->get_result()->fetch_assoc()['title'] ?? '';
            if ($current_title === 'Chat mới' || $current_title === 'Chat mặc định') {
                $new_title = mb_substr($content, 0, 50) . "...";
                $stmt_up = $conn->prepare("UPDATE presale_chat_sessions SET title = ? WHERE id = ?");
                $stmt_up->bind_param("si", $new_title, $session_id);
                $stmt_up->execute();
                $new_chat = true;
            }
        }

        if ($session_id) {
            $stmt_u = $conn->prepare("INSERT INTO presale_chat_messages (session_id, role, content) VALUES (?, 'user', ?)");
            $stmt_u->bind_param("is", $session_id, $content);
            $stmt_u->execute();

            $stmt_a = $conn->prepare("INSERT INTO presale_chat_messages (session_id, role, content) VALUES (?, 'assistant', ?)");
            $stmt_a->bind_param("is", $session_id, $reply);
            $stmt_a->execute();

            if ($ai_conversation_id) {
                $stmt_up_conv = $conn->prepare("UPDATE presale_chat_sessions SET ai_conversation_id = ? WHERE id = ?");
                $stmt_up_conv->bind_param("si", $ai_conversation_id, $session_id);
                $stmt_up_conv->execute();
            }
        }
    } catch(Exception $e) {}
    return ['session_id' => $session_id, 'project_id' => $project_id, 'new_chat' => $new_chat];
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

if ($action === 'fetch_figma_data') {
    $figma_url = $_POST['figma_url'] ?? '';
    $figma_token = $_POST['figma_token'] ?? '';
    
    if (empty($figma_url) || empty($figma_token)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp URL và Token.']);
        exit;
    }
    
    preg_match('/(?:file|design)\/([^\/]+)/', $figma_url, $matches);
    $file_key = $matches[1] ?? '';
    
    if (empty($file_key)) {
        echo json_encode(['success' => false, 'message' => 'URL Figma không hợp lệ.']);
        exit;
    }
    
    $ch = curl_init("https://api.figma.com/v1/files/$file_key?depth=3");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Figma-Token: $figma_token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        $err = json_decode($response, true);
        echo json_encode(['success' => false, 'message' => $err['err'] ?? "Lỗi API Figma (HTTP $http_code)"]);
        exit;
    }
    
    $data = json_decode($response, true);
    $summary = [
        'name' => $data['name'] ?? 'Dự án Figma',
        'pages' => []
    ];
    
    if (isset($data['document']['children'])) {
        foreach ($data['document']['children'] as $page) {
            if ($page['type'] === 'CANVAS') {
                $pageData = [
                    'name' => $page['name'],
                    'screens' => []
                ];
                
                if (isset($page['children'])) {
                    foreach ($page['children'] as $frame) {
                        // Only capture large frames that are likely screens
                        if (($frame['type'] === 'FRAME' || $frame['type'] === 'GROUP') && ($frame['visible'] ?? true) !== false) {
                            $screenData = [
                                'name' => $frame['name'],
                                'sections' => []
                            ];
                            
                            // Look for sub-sections inside the screen
                            if (isset($frame['children'])) {
                                foreach ($frame['children'] as $sub) {
                                    if (($sub['type'] === 'FRAME' || $sub['type'] === 'GROUP' || $sub['type'] === 'COMPONENT') && ($sub['visible'] ?? true) !== false) {
                                        $screenData['sections'][] = $sub['name'];
                                    }
                                }
                            }
                            $pageData['screens'][] = $screenData;
                        }
                    }
                }
                
                if (!empty($pageData['screens'])) {
                    $summary['pages'][] = $pageData;
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'data' => $summary]);
    exit;
}

if ($action === 'analyze_design_image') {
    if (!isset($_FILES['mockup_image'])) {
        echo json_encode(['success' => false, 'message' => 'Không có ảnh được tải lên']);
        exit;
    }

    $tmpPath = $_FILES['mockup_image']['tmp_name'];
    $pythonPath = __DIR__ . "/vision/venv/bin/python3";
    $scriptPath = __DIR__ . "/vision/ui_analyzer.py";

    $command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($tmpPath) . " 2>&1";
    $output = shell_exec($command);
    
    $res = json_decode($output, true);
    if ($res) {
        echo json_encode($res);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi thực thi script Python: ' . $output]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
