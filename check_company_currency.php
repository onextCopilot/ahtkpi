<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();

$reflector = new ReflectionClass('OdooAPI');
$method = $reflector->getMethod('searchRead');
$method->setAccessible(true);
$companies = $method->invoke($odoo, 'res.company', [], ['name', 'currency_id'], 1, 0);

foreach ($companies as $c) {
    echo "Company: {$c['name']} | Currency: " . $c['currency_id'][1] . " (ID: " . $c['currency_id'][0] . ")\n";
}
