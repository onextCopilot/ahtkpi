<?php
require_once __DIR__ . '/../../config/config.php'; 

if (!$conn) {
    die("Connection failed");
}

$sql = "ALTER TABLE presale_chat_sessions ADD COLUMN ai_conversation_id VARCHAR(255) NULL";
if ($conn->query($sql)) {
    echo "Successfully added ai_conversation_id column.";
} else {
    echo "Error or already exists: " . $conn->error;
}
?>
