<?php
/**
 * Local Script Generator
 * Run this at http://localhost:8000/generate_import.php
 */
require_once __DIR__ . '/config/config.php';

$year = 2026;
$quarter = 1;

// 1. Fetch Structure
$structure = [];
$res = $conn->query("SELECT * FROM budget_structure WHERE year=$year AND quarter=$quarter");
while ($r = $res->fetch_assoc()) $structure[] = $r;

// 2. Fetch Values
$values = [];
$res = $conn->query("SELECT * FROM budget_values WHERE year=$year AND quarter=$quarter");
while ($r = $res->fetch_assoc()) $values[] = $r;

// 3. Generate the PHP Content for Live
$php_code = '<?php
/**
 * Budget Data Importer for Live Site
 * Run this on your LIVE server to import Q1 2026 data.
 * DELETE THIS FILE AFTER RUNNING.
 */
require_once __DIR__ . "/config/config.php";

echo "<h2>Importing Budget Data for Q1 2026...</h2>";

$structure_data = ' . var_export($structure, true) . ';
$values_data = ' . var_export($values, true) . ';

// Disable FK checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

// Clear existing Q1 data on live to avoid duplicates
$conn->query("DELETE FROM budget_values WHERE year=2026 AND quarter=1");
$conn->query("DELETE FROM budget_structure WHERE year=2026 AND quarter=1");

$id_map = []; // Map old ID to new ID if needed, but since we use INSERT with IDs, it should be fine.

// 1. Insert Structure
echo "<h3>1. Importing Structure...</h3>";
foreach ($structure_data as $row) {
    $keys = array_keys($row);
    $vals = array_map(function($v) use ($conn) { return is_null($v) ? "NULL" : "\'" . $conn->real_escape_string($v) . "\'"; }, array_values($row));
    
    // Simple manual SQL for import script safety
    $sql = "INSERT INTO budget_structure (" . implode(",", $keys) . ") VALUES (" . implode(",", $vals) . ")";
    if ($conn->query($sql)) {
        echo "✅ Imported: " . ($row["item_name"] ?: "No name") . "<br>";
    } else {
        echo "❌ Error: " . $conn->error . "<br>";
    }
}

// 2. Insert Values
echo "<h3>2. Importing Values...</h3>";
foreach ($values_data as $row) {
    $keys = array_keys($row);
    $vals = array_map(function($v) use ($conn) { return is_null($v) ? "NULL" : "\'" . $conn->real_escape_string($v) . "\'"; }, array_values($row));
    
    $sql = "INSERT INTO budget_values (" . implode(",", $keys) . ") VALUES (" . implode(",", $vals) . ")";
    if ($conn->query($sql)) {
        // success
    } else {
        echo "❌ Error (Values): " . $conn->error . "<br>";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
echo "<hr><p style=\'color:green; font-weight:bold;\'>DONE! Data imported successfully.</p>";
echo "<p>Please delete this file from your live server now.</p>";
?>';

// Fix single quote escaping for the var_export
$php_code = str_replace("\\'", "'", $php_code);

if (file_put_contents(__DIR__ . '/import_data_to_live.php', $php_code)) {
    echo "<h2>Success!</h2>";
    echo "<p>The file <b>import_data_to_live.php</b> has been created successfully.</p>";
    echo "<p>1. Copy <b>import_data_to_live.php</b> to your LIVE server.</p>";
    echo "<p>2. Run it at: <b>yourdomain.com/import_data_to_live.php</b></p>";
} else {
    echo "<h2>Error!</h2>";
    echo "<p>Could not write file. Check directory permissions.</p>";
}
?>
