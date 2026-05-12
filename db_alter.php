<?php
require_once 'includes/db.php';
$conn->query("ALTER TABLE presale_session_files ADD COLUMN ai_file_id VARCHAR(255) NULL AFTER extracted_text;");
echo "Done";
