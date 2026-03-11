<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();

echo "Fetching Currencies...\n";
$currencies = $odoo->getCurrencies();
foreach ($currencies as $name => $c) {
    echo "Currency: $name | ID: {$c['id']} | Rate: {$c['rate']} | Symbol: {$c['symbol']}\n";
}

echo "\nFetching USD Rates (last 5)...\n";
// Manual searchRead to avoid cache issues
$reflector = new ReflectionClass('OdooAPI');
$method = $reflector->getMethod('searchRead');
$method->setAccessible(true);
$rates = $method->invoke($odoo, 'res.currency.rate', [['currency_id.name', '=', 'USD']], ['name', 'rate'], 5, 0);

foreach ($rates as $r) {
    echo "Date: {$r['name']} | Rate: {$r['rate']}\n";
}
