<?php
$cacheFile = '/Users/hyuncao/Onext Digital/GitHub_Projects/ahtkpi/cache/invoices.cache.php';
$content = file_get_contents($cacheFile);
$start = strpos($content, '[');
$data = json_decode(substr($content, $start), true);
foreach ($data as $inv) {
    if ($inv['id'] == 4032) {
        echo "FOUND INVOICE 4032:\n";
        print_r($inv);
        break;
    }
}
