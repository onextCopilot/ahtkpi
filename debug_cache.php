<?php
$cacheFile = __DIR__ . '/cache/customers.cache.php';
$content = file_get_contents($cacheFile);

// Check if content exists
if (empty($content)) {
    echo "Cache file empty.\n";
    exit;
}

$json = str_replace('<?php exit; ?>', '', $content);
$customers = json_decode($json, true);

if (!is_array($customers)) {
    echo "Invalid JSON.\n";
    exit;
}

echo "Total customers: " . count($customers) . "\n";
foreach ($customers as $c) {
    if (stripos($c['name'], 'lolli') !== false) {
        echo "Found 'lolli': " . $c['name'] . " (Company=" . ($c['is_company'] ? 'True' : 'False') . ")\n";
    }
}
