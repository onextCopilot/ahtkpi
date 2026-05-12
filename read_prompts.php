<?php
require_once 'config/config.php';
$res = $conn->query("SELECT action_key, system_prompt FROM presale_prompts");
while($row = $res->fetch_assoc()) {
    echo "KEY: " . $row['action_key'] . "\n";
    echo "PROMPT: " . $row['system_prompt'] . "\n";
    echo "------------------\n";
}
