<?php
include __DIR__ . '/../../config/config.php';
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo $row[0] . "\n";
}
?>
