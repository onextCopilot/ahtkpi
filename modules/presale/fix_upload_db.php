<?php
require_once __DIR__ . '/../../config/config.php'; 

if (!$conn) {
    die("Connection failed");
}

// Cho phép session_id nhận NULL
$sql = "ALTER TABLE presale_session_files MODIFY COLUMN session_id INT(11) NULL";
if ($conn->query($sql)) {
    echo "Successfully modified presale_session_files table.";
} else {
    echo "Error: " . $conn->error;
}
?>
