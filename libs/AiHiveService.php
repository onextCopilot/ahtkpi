<?php

class AiHiveService {
    private $apiKey;
    private $baseUrl;
    private $agentId; // If using a specific Agent ID on aihive

    public function __construct() {
        global $conn; // Dùng connection global của hệ thống

        // Lấy từ config trong Database
        $this->apiKey = 'YOUR_AIHIVE_API_KEY';
        $this->baseUrl = 'https://api.aihive.ai/v1';
        $this->agentId = 'gpt-3.5-turbo';

        if ($conn) {
            $result = $conn->query("SELECT * FROM aihive_settings ORDER BY id DESC LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $settings = $result->fetch_assoc();
                if (!empty($settings['api_key'])) $this->apiKey = $settings['api_key'];
                if (!empty($settings['base_url'])) $this->baseUrl = $settings['base_url'];
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
