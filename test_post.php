<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user_id'] = 1;
require_once "config/config.php";
$body = '{"order": [2, 1, 3]}';
// We have to mock file_get_contents('php://input').
// Let's just modify the API script temporarily to read a variable if we want.
