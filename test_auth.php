<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
try {
    $odoo = new OdooAPI();
    // Use reflection to call private authenticate
    $reflector = new ReflectionClass('OdooAPI');
    $method = $reflector->getMethod('authenticate');
    $method->setAccessible(true);
    $uid = $method->invoke($odoo);
    echo "AUTH SUCCESS: UID $uid\n";
} catch (Exception $e) {
    echo "AUTH FAILED: " . $e->getMessage() . "\n";
}
