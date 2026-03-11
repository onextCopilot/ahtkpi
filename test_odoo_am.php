<?php
require 'config/config.php';
require 'libs/OdooAPI.php';
$odoo = new OdooAPI();
$odoo->getInvoices(10, 0, []); 
$map = $odoo->getInvoiceMap();
$first = reset($map);
print_r(array_keys($first));
