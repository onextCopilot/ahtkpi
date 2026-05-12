<?php
require_once __DIR__ . '/config/config.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS presale_chat_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS presale_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        role ENUM('system', 'user', 'assistant') NOT NULL,
        content LONGTEXT NOT NULL,
        prompt_tokens INT DEFAULT 0,
        completion_tokens INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES presale_chat_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS presale_prompts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action_key VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        system_prompt TEXT NOT NULL,
        user_prompt_template TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Successfully executed query: " . substr($query, 0, 50) . "...\n";
    } else {
        echo "Error executing query: " . $conn->error . "\n";
    }
}

// Seed basic prompts
$seed_prompts = [
    [
        'action_key' => 'qna',
        'title' => 'Q&A Presale',
        'system_prompt' => 'Bạn là một chuyên gia Presale. Hãy trả lời các câu hỏi dựa trên thông tin được cung cấp ngắn gọn, chuyên nghiệp và thuyết phục.',
        'user_prompt_template' => 'Câu hỏi từ khách hàng: {question}\nNgữ cảnh dự án: {context}'
    ],
    [
        'action_key' => 'create_sow',
        'title' => 'Tạo SOW / Proposal Draft',
        'system_prompt' => 'Bạn là một chuyên gia lập giải pháp (Solution Architect) và Presale. Hãy viết một bản nháp Scope of Work (SOW) chuyên nghiệp dựa trên requirement sau.',
        'user_prompt_template' => 'Yêu cầu của khách hàng:\n{requirement}\n\nHãy tạo bản SOW với các phần: 1. Mục tiêu, 2. Phạm vi công việc, 3. Các tính năng chính, 4. Tech Stack đề xuất.'
    ]
];

foreach ($seed_prompts as $prompt) {
    $stmt = $conn->prepare("INSERT IGNORE INTO presale_prompts (action_key, title, system_prompt, user_prompt_template) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $prompt['action_key'], $prompt['title'], $prompt['system_prompt'], $prompt['user_prompt_template']);
    if ($stmt->execute()) {
        echo "Seeded prompt: {$prompt['action_key']}\n";
    } else {
        echo "Error seeding prompt {$prompt['action_key']}: " . $stmt->error . "\n";
    }
}

echo "Database setup complete.\n";
