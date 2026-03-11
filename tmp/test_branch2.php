<?php
require_once __DIR__ . '/../libs/OdooAPI.php';
$api = new OdooAPI();

// Call native jsonRpcCall manually or just object->fields_get
$params = [
    $api->getDatabase(),
    $api->authenticate(), // Need to make authenticate public or do something else. Wait I can't.
];
