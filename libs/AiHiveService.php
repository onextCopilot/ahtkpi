<?php

class AiHiveService {
    private $apiKey;
    private $baseUrl;
    private $agentId;
    private $visionApiKey;
    private $visionBaseUrl;

    public function __construct() {
        global $conn;

        $this->apiKey  = 'YOUR_AIHIVE_API_KEY';
        $this->baseUrl = 'https://api.aihive.ai/v1';
        $this->agentId = 'gpt-3.5-turbo';

        // Vision-specific defaults (fallback to main settings)
        $this->visionApiKey  = null;
        $this->visionBaseUrl = null;

        if ($conn) {
            $result = $conn->query("SELECT * FROM aihive_settings ORDER BY id DESC LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $settings = $result->fetch_assoc();
                if (!empty($settings['api_key']))        $this->apiKey       = $settings['api_key'];
                if (!empty($settings['base_url']))       $this->baseUrl      = $settings['base_url'];
                if (!empty($settings['vision_api_key'])) $this->visionApiKey = $settings['vision_api_key'];
                if (!empty($settings['vision_base_url']))$this->visionBaseUrl= $settings['vision_base_url'];
            }
        }
    }

    /**
     * Kiểm tra xem đã cấu hình API Key chưa
     */
    public function isConfigured() {
        return !empty($this->apiKey) && $this->apiKey !== 'YOUR_AIHIVE_API_KEY';
    }

    /**
     * Kiểm tra Vision AI đã được cấu hình riêng chưa
     */
    public function isVisionConfigured() {
        return !empty($this->visionApiKey) || $this->isConfigured();
    }

    /**
     * Upload ảnh lên Dify Vision App (dùng vision_api_key riêng)
     */
    public function uploadVisionFile($filePath, $filename, $mimeType) {
        $apiKey  = !empty($this->visionApiKey) ? $this->visionApiKey : $this->apiKey;
        $baseUrl = !empty($this->visionBaseUrl) ? $this->visionBaseUrl : 'https://api.dify.ai/v1/chat-messages';

        // Extract base (strip /chat-messages path để lấy /v1)
        $uploadUrl = preg_replace('#/chat-messages.*$#', '', $baseUrl) . '/files/upload';

        $cfile = new CURLFile($filePath, $mimeType, $filename);
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => $cfile, 'user' => 'vision_user'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        if ($error) return ['success' => false, 'message' => 'Upload CURL: ' . $error];
        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($data['id'])) {
            return ['success' => true, 'data' => $data];
        }
        return ['success' => false, 'message' => "Upload HTTP $httpCode - " . ($data['message'] ?? mb_substr($response, 0, 200))];
    }

    /**
     * Gửi ảnh + prompt và tự động parse JSON kết quả
     */
    public function runVisionAnalysis($fileId, $prompt) {
        $res = $this->callVisionChat($prompt, $fileId);
        if (!$res['success']) return $res;

        $aiText = "";
        $d = $res['data'] ?? [];
        
        // Trích xuất text từ các định dạng Dify/AiHive khác nhau
        if (isset($d['data']['outputs']['text']))       $aiText = $d['data']['outputs']['text'];
        elseif (isset($d['data']['outputs']['answer'])) $aiText = $d['data']['outputs']['answer'];
        elseif (isset($d['outputs']['text']))           $aiText = $d['outputs']['text'];
        elseif (isset($d['outputs']['answer']))         $aiText = $d['outputs']['answer'];
        elseif (isset($d['answer']))                    $aiText = $d['answer'];
        else                                            $aiText = json_encode($d);

        // Parse JSON từ Markdown hoặc chuỗi text
        preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $aiText, $matches);
        $jsonStr = $matches[1] ?? $aiText;
        $p = strpos($jsonStr, '{');
        if ($p !== false) $jsonStr = substr($jsonStr, $p);
        
        $parsed = json_decode($jsonStr, true);
        if (!$parsed) {
            return ['success' => false, 'message' => 'AI trả về không phải JSON: ' . mb_substr($aiText, 0, 200)];
        }

        return ['success' => true, 'data' => $parsed];
    }

    /**
     * Gửi ảnh + prompt đến Dify Vision App (Core)
     */
    public function callVisionChat($prompt, $fileId) {
        $apiKey  = !empty($this->visionApiKey)  ? $this->visionApiKey  : $this->apiKey;
        $baseUrl = !empty($this->visionBaseUrl) ? $this->visionBaseUrl : '';

        // Nếu không có vision_base_url, fallback sang main base_url
        if (empty($baseUrl)) {
            $baseUrl = $this->baseUrl;
        }

        $isWorkflow = strpos($baseUrl, '/workflows/run') !== false;
        $isChat     = strpos($baseUrl, '/chat-messages') !== false;

        // Nếu URL chưa có path cụ thể, mặc định dùng workflow (theo screenshot)
        if (!$isWorkflow && !$isChat) {
            $baseUrl = rtrim($baseUrl, '/') . '/workflows/run';
            $isWorkflow = true;
        }

        // === WORKFLOW format ===
        if ($isWorkflow) {
            $payload = [
                'inputs'        => ['query' => $prompt],
                'files'         => [[
                    'type'            => 'image',
                    'transfer_method' => 'local_file',
                    'upload_file_id'  => $fileId
                ]],
                'response_mode' => 'blocking',
                'user'          => 'vision_user'
            ];
        }
        // === CHAT format ===
        else {
            $payload = [
                'query'         => $prompt,
                'inputs'        => [],
                'files'         => [[
                    'type'            => 'image',
                    'transfer_method' => 'local_file',
                    'upload_file_id'  => $fileId
                ]],
                'response_mode' => 'blocking',
                'user'          => 'vision_user'
            ];
        }

        $ch = curl_init($baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($error) return ['success' => false, 'message' => 'CURL: ' . $error];

        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $data, 'mode' => $isWorkflow ? 'workflow' : 'chat'];
        }
        return ['success' => false, 'message' => "HTTP $httpCode - " . ($data['message'] ?? mb_substr($response, 0, 200))];
    }

    /**
     * Gửi request chat tới AI Agent
     * 
     * @param array $messages Mảng lịch sử tin nhắn (role: system, user, assistant)
     * @param string $actionKey (tuỳ chọn) Mã tác vụ để AI biết context
     * @param string $conversationId (tuỳ chọn) ID cuộc hội thoại để Agent tự nhớ history
     * @return array Trả về response từ API
     */
    public function chatCompletion($messages, $actionKey = 'qna', $conversationId = null, $files = []) {
        // Phân tách riêng System Prompt và User Query
        $systemPrompt = "";
        $userQuery = "";
        
        // Nếu có conversationId, ta chỉ cần gửi tin nhắn cuối cùng (user)
        // Nếu không, ta gửi toàn bộ history (messages)
        if ($conversationId) {
            $lastMsg = end($messages);
            $userQuery = $lastMsg['content'];
            // Vẫn có thể gửi system_prompt nếu cần cập nhật context
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') $systemPrompt .= $msg['content'] . "\n\n";
            }
        } else {
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $systemPrompt .= $msg['content'] . "\n\n";
                } else {
                    $userQuery .= $msg['role'] . ": " . $msg['content'] . "\n\n";
                }
            }
        }

        // Đóng gói payload cho AiHive Workflow/Chat API
        $payload = [
            'inputs' => [
                'query' => trim($userQuery),
                'system_prompt' => trim($systemPrompt)
            ],
            'response_mode' => 'blocking',
            'user' => 'user_aht'
        ];

        if (!empty($files)) {
            $payload['files'] = $files;
        }

        if ($conversationId) {
            $payload['conversation_id'] = $conversationId;
        }

        // URL endpoint chuẩn của AiHive (Có thể là chat-messages hoặc workflows/run)
        $endpoint = rtrim($this->baseUrl, '/');
        // Nếu là chat mode thì dùng /chat-messages để hỗ trợ conversation_id tốt hơn
        if ($conversationId || strpos($endpoint, 'chat-messages') !== false) {
             if (strpos($endpoint, '/chat-messages') === false) {
                 // Nếu baseUrl chưa có path chat, ta thử chuyển hướng sang chat-messages
                 // Giả sử Dify-like structure
                 $endpoint = str_replace('/workflows/run', '/chat-messages', $endpoint);
                 if (strpos($endpoint, '/chat-messages') === false) $endpoint .= '/chat-messages';
             }
             // Chat API thường dùng 'query' thay vì 'inputs'
             $payload['query'] = trim($userQuery);
        } else {
            if (strpos($endpoint, '/workflows/run') === false) {
                $endpoint .= '/workflows/run';
            }
        }

        return $this->sendRequest('POST', $endpoint, $payload);
    }

    /**
     * Hàm gọi RAG / Knowledge Base (Nếu API aihive tách biệt việc search)
     * 
     * @param string $query Câu hỏi của người dùng
     * @return array Danh sách tài liệu liên quan
     */
    public function searchKnowledgeBase($query) {
        $payload = [
            'query' => $query,
            'top_k' => 3
        ];
        
        $endpoint = $this->baseUrl . '/knowledge/search';
        return $this->sendRequest('POST', $endpoint, $payload);
    }

    /**
     * Gửi request chat tới AI Agent dạng Stream
     */
    public function streamChatCompletion($messages, $callback, $actionKey = 'qna', $conversationId = null, $files = []) {
        $systemPrompt = "";
        $userQuery = "";
        
        if ($conversationId) {
            $lastMsg = end($messages);
            $userQuery = $lastMsg['content'];
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') $systemPrompt .= $msg['content'] . "\n\n";
            }
        } else {
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $systemPrompt .= $msg['content'] . "\n\n";
                } else {
                    $userQuery .= $msg['role'] . ": " . $msg['content'] . "\n\n";
                }
            }
        }

        $payload = [
            'inputs' => [
                'query' => trim($userQuery),
                'system_prompt' => trim($systemPrompt)
            ],
            'response_mode' => 'streaming',
            'user' => 'user_aht'
        ];

        if (!empty($files)) {
            $payload['files'] = $files;
        }

        if ($conversationId) {
            $payload['conversation_id'] = $conversationId;
        }

        $endpoint = rtrim($this->baseUrl, '/');
        if ($conversationId || strpos($endpoint, 'chat-messages') !== false) {
             if (strpos($endpoint, '/chat-messages') === false) {
                 $endpoint = str_replace('/workflows/run', '/chat-messages', $endpoint);
                 if (strpos($endpoint, '/chat-messages') === false) $endpoint .= '/chat-messages';
             }
             $payload['query'] = trim($userQuery);
        } else {
            if (strpos($endpoint, '/workflows/run') === false) {
                $endpoint .= '/workflows/run';
            }
        }

        return $this->sendRequest('POST', $endpoint, $payload, $callback);
    }

    /**
     * Upload file lên AiHive (Dify) để dùng cho Vision / Document analysis
     * 
     * @param string $filePath Đường dẫn tuyệt đối tới file cần upload
     * @param string $filename Tên file
     * @param string $mimeType Loại MIME
     * @return array Kết quả trả về chứa ID của file trên AiHive
     */
    public function uploadFile($filePath, $filename, $mimeType) {
        $endpoint = rtrim($this->baseUrl, '/') . '/files/upload';
        
        $cfile = new CURLFile($filePath, $mimeType, $filename);
        $postParams = [
            'file' => $cfile,
            'user' => 'user_aht'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Authorization cho multipart/form-data
        $headers = [
            'Authorization: Bearer ' . $this->apiKey
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        // curl_close($ch); // Deprecated in PHP 8.5+

        if ($error) {
            return ['success' => false, 'message' => 'CURL Error: ' . $error];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $data];
        } else {
            return ['success' => false, 'message' => "HTTP $httpCode - " . (isset($data['message']) ? $data['message'] : 'Upload failed')];
        }
    }

    /**
     * Hàm dùng chung để gửi cURL request (Hỗ trợ Stream)
     */
    private function sendRequest($method, $url, $payload = null, $streamCallback = null) {
        $ch = curl_init($url);
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }

        if ($streamCallback && is_callable($streamCallback)) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($streamCallback) {
                $streamCallback($data);
                return strlen($data);
            });
            $res = curl_exec($ch);
            if ($res === false) {
                $err = curl_error($ch);
                $streamCallback("data: " . json_encode(['text' => "\n[Lỗi kết nối API: $err]"]) . "\n\n");
            }
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        // curl_close($ch); // Deprecated in PHP 8.5+

        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL Error: ' . $error
            ];
        }

        if ($streamCallback) {
            return ['success' => true];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $data
            ];
        } else {
            $errorMsg = 'Unknown API error';
            if (isset($data['error']['message'])) {
                $errorMsg = $data['error']['message'];
            } elseif (is_string($response) && !empty($response)) {
                $errorMsg = mb_substr($response, 0, 200);
            }

            return [
                'success' => false,
                'message' => "HTTP $httpCode - " . $errorMsg,
                'status_code' => $httpCode,
                'raw_response' => $response
            ];
        }
    }
}
