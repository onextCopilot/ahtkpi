<?php
// debug_paid_invoice.php
$cacheFile = __DIR__ . '/cache/invoices.cache.php';

if (!file_exists($cacheFile)) {
    die("Cache file not found.\n");
}

$content = file_get_contents($cacheFile);
$json = str_replace('<?php exit; ?>', '', $content);
$invoices = json_decode($json, true);

if (!$invoices) {
    die("Failed to decode JSON.\n");
}

$found = null;
foreach ($invoices as $inv) {
    if ($inv['payment_state'] === 'paid' || $inv['payment_state'] === 'in_payment') {
        $found = $inv;
        break;
    }
}

if ($found) {
    echo "Found Paid Invoice:\n";
    print_r($found);
} else {
    echo "No paid invoices found in cache.\n";
}
