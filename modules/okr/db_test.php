<?php
$conn = new mysqli("localhost", "root", "", "ahtkpi");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Try reading tables
$res = $conn->query("SHOW CREATE TABLE okr_results");
if($res) print_r($res->fetch_assoc());
echo "\n";
$res2 = $conn->query("SHOW CREATE TABLE okr_key_activities");
if($res2) print_r($res2->fetch_assoc());

// check explanations
echo "\n";
$res3 = $conn->query("SHOW CREATE TABLE okr_explanations");
if($res3) print_r($res3->fetch_assoc());
