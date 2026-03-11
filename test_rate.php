<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();
echo "USD rate: " . $odoo->getRate('USD', date('Y-m-d')) . "\n";
echo "VND rate: " . $odoo->getRate('VND', date('Y-m-d')) . "\n";
