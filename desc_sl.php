<?php
require 'config/config.php';
$r = $conn->query("DESCRIBE sale_levels");
if($r) while($row = $r->fetch_assoc()) echo json_encode($row)."\n";
