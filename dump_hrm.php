<?php
require_once __DIR__ . '/config/config.php';
global $conn;

if (!isset($conn) || $conn->connect_error) {
    die("Connection failed");
}

$sqls = [];

$result = $conn->query("SHOW TABLES LIKE 'hrm_%'");
if (!$result) die("Query failed: " . $conn->error);

while ($row = $result->fetch_array()) {
    $table = $row[0];
    
    // Get Create Table syntax
    $createRes = $conn->query("SHOW CREATE TABLE `$table`");
    $createRow = $createRes->fetch_row();
    $createTableSql = $createRow[1];
    
    // Add create table IF NOT EXISTS
    $createTableSql = str_replace("CREATE TABLE", "CREATE TABLE IF NOT EXISTS", $createTableSql);
    $sqls[] = $createTableSql . ";";
    
    $sqls[] = "DELETE FROM `$table`;"; // Clear old data first

    // Check if table has rows
    $countRes = $conn->query("SELECT COUNT(*) FROM `$table`");
    $count = $countRes->fetch_row()[0];
    if ($count == 0) continue;
    
    $dataRes = $conn->query("SELECT * FROM `$table`");
    while ($dataRow = $dataRes->fetch_assoc()) {
        $keys = array_keys($dataRow);
        $values = array_values($dataRow);
        
        $escapedValues = array_map(function($val) use ($conn) {
            if ($val === null) return "NULL";
            return "'" . $conn->real_escape_string($val) . "'";
        }, $values);
        
        $keysStr = implode(", ", array_map(function($k) { return "`$k`"; }, $keys));
        $valsStr = implode(", ", $escapedValues);
        
        $sqls[] = "INSERT INTO `$table` ($keysStr) VALUES ($valsStr);";
    }
}

// Generate the import_hrm.php script
$importScript = "<?php\n"
    . "require_once __DIR__ . '/config/config.php';\n"
    . "global \$conn;\n"
    . "if (!isset(\$conn) || \$conn->connect_error) { die('Connection failed'); }\n\n"
    . "\$conn->query('SET FOREIGN_KEY_CHECKS=0;');\n"
    . "echo '<h2>Bắt đầu import dữ liệu HRM...</h2><ul>';\n\n";

foreach ($sqls as $sql) {
    // Escape single quotes for PHP string
    $safeSql = str_replace("'", "\\'", $sql);
    $importScript .= "if (\$conn->query('$safeSql')) {\n"
        . "    // echo '<li>Thành công</li>';\n"
        . "} else {\n"
        . "    echo '<li style=\"color:red\">Lỗi: ' . \$conn->error . ' <br><small>Query: " . htmlspecialchars($safeSql, ENT_QUOTES) . "</small></li>';\n"
        . "}\n";
}

$importScript .= "\n\$conn->query('SET FOREIGN_KEY_CHECKS=1;');\n";
$importScript .= "echo '</ul><h3>✅ HOÀN TẤT IMPORT! Bạn có thể xoá file import_hrm.php này khỏi server.</h3>';\n";

file_put_contents(__DIR__ . '/import_hrm.php', $importScript);

echo "<h3>Đã tạo thành công file <b>import_hrm.php</b> (Phiên bản bao gồm CREATE TABLE)!</h3>";
echo "<p>Vui lòng Commit file <b>import_hrm.php</b> mới lên Git hoặc upload đè lên file cũ trên Live server.<br>";
echo "Sau đó truy cập lại đường dẫn: <b>http://[Tên miền Live của bạn]/import_hrm.php</b> để thực hiện import.</p>";
