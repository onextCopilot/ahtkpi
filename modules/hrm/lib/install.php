<?php
/**
 * HRM rebuild installer - DROPS every existing hrm_* table, then creates the
 * clean schema + seeds. A full SQL backup is taken before this is ever run
 * (see backups/hrm_pre_rebuild_*.sql). Idempotent: safe to re-run.
 *
 * Run:  php modules/hrm/lib/install.php      (CLI)
 *   or  open  /hrm/lib/install.php           (browser, one-off)
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/schema.php';

$cli = (php_sapi_name() === 'cli');
$log = function (string $m) use ($cli) { echo $m . ($cli ? "\n" : "<br>\n"); };

$conn->query('SET FOREIGN_KEY_CHECKS=0');

// 1) Drop every legacy hrm_* table.
$res = $conn->query("SHOW TABLES LIKE 'hrm\\_%'");
$drop = [];
while ($row = $res->fetch_array()) { $drop[] = $row[0]; }
foreach ($drop as $tbl) {
    $conn->query("DROP TABLE IF EXISTS `$tbl`");
}
$log('Dropped ' . count($drop) . ' legacy hrm_* tables.');

// 2) Create the clean schema.
$created = 0;
foreach (hrm_schema() as $name => $sql) {
    if ($conn->query($sql)) { $created++; }
    else { $log("ERROR creating $name: " . $conn->error); }
}
$log("Created $created tables.");

// 3) Seed.
hrm_seeds($conn);
$log('Seeded stages, approval flows, settings, master data, HRF email templates.');

$conn->query('SET FOREIGN_KEY_CHECKS=1');
$log('DONE.');
