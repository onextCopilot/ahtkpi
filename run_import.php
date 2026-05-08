<?php
require_once __DIR__ . '/config/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Import Openings</title></head><body>";
echo "<h1>Importing Job Openings...</h1>";

function readXlsx($filename) {
    $zip = new ZipArchive();
    if ($zip->open($filename) === true) {
        $sharedStrings = [];
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = simplexml_load_string($data);
            foreach ($xml->si as $val) {
                if (isset($val->t)) {
                    $sharedStrings[] = (string)$val->t;
                } elseif (isset($val->r)) {
                    $str = '';
                    foreach ($val->r as $r) {
                        $str .= (string)$r->t;
                    }
                    $sharedStrings[] = $str;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
        
        $sheetData = [];
        if (($index = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $xml = simplexml_load_string($data);
            foreach ($xml->sheetData->row as $row) {
                $rowIndex = (int)$row['r'];
                $rowData = [];
                foreach ($row->c as $cell) {
                    $cellRef = (string)$cell['r'];
                    $colStr = preg_replace('/[0-9]/', '', $cellRef);
                    // Convert column string to index (A => 0, B => 1, etc.)
                    $colIndex = 0;
                    for ($i = 0; $i < strlen($colStr); $i++) {
                        $colIndex = $colIndex * 26 + (ord($colStr[$i]) - 64);
                    }
                    $colIndex--;
                    
                    $val = (string)$cell->v;
                    if (isset($cell['t']) && $cell['t'] == 's') {
                        $val = $sharedStrings[(int)$val] ?? $val;
                    }
                    $rowData[$colIndex] = $val;
                }
                // Fill missing indices with null
                if (!empty($rowData)) {
                    $maxKey = max(array_keys($rowData));
                    $cleanRow = [];
                    for ($i = 0; $i <= $maxKey; $i++) {
                        $cleanRow[$i] = $rowData[$i] ?? null;
                    }
                    $sheetData[$rowIndex] = $cleanRow;
                }
            }
        }
        $zip->close();
        return $sheetData;
    } else {
        return false;
    }
}

$file = __DIR__ . '/backups/sys4386-openings-08042010-08052026.report.09.11.08.05.26.xlsx';
if (!file_exists($file)) {
    die("<p style='color:red'>File not found: " . htmlspecialchars($file) . "</p></body></html>");
}

$data = readXlsx($file);
if (!$data) {
    die("<p style='color:red'>Failed to open or parse the XLSX file.</p></body></html>");
}

$count = 0;
$errors = [];

foreach ($data as $rowIndex => $row) {
    if ($rowIndex < 5) continue; // Skip headers
    if (empty($row[0])) continue; // Empty ID

    $id = (int)$row[0];
    $title = $conn->real_escape_string($row[2] ?? '');
    $dept_name = $conn->real_escape_string($row[4] ?? '');
    $office = $conn->real_escape_string($row[6] ?? '');
    $status_raw = strtolower(trim($row[7] ?? ''));
    
    $status = 'draft';
    if (strpos($status_raw, 'publish') !== false) $status = 'public';
    elseif (strpos($status_raw, 'close') !== false) $status = 'closed';
    elseif (strpos($status_raw, 'draft') !== false) $status = 'draft';

    $job_code = $conn->real_escape_string($row[10] ?? '');
    $quantity = (int)($row[11] ?? 0);
    $managers = $conn->real_escape_string($row[12] ?? '');
    
    // date parsing
    $created_at = null;
    if (!empty($row[14]) && $row[14] !== '---') {
        $d = DateTime::createFromFormat('d/m/Y', trim($row[14]));
        if($d) $created_at = $d->format('Y-m-d 00:00:00');
    }
    
    $deadline = null;
    if (!empty($row[16]) && $row[16] !== '---') {
        $d = DateTime::createFromFormat('d/m/Y', trim($row[16]));
        if($d) $deadline = $d->format('Y-m-d');
    }

    // handle department
    $dept_id = 0;
    if ($dept_name) {
        $dept_res = $conn->query("SELECT id FROM hrm_departments WHERE name = '$dept_name' LIMIT 1");
        if ($dept_res && $dept_res->num_rows > 0) {
            $dept_id = $dept_res->fetch_assoc()['id'];
        } else {
            $conn->query("INSERT INTO hrm_departments (name) VALUES ('$dept_name')");
            $dept_id = $conn->insert_id;
        }
    }

    $created_at_val = $created_at ? "'$created_at'" : "NOW()";
    $deadline_val = $deadline ? "'$deadline'" : "NULL";

    $sql = "INSERT INTO hrm_job_posts (id, title, job_code, department_id, office, status, quantity, managers, created_at, deadline, created_by) 
            VALUES ($id, '$title', '$job_code', $dept_id, '$office', '$status', $quantity, '$managers', $created_at_val, $deadline_val, 1)
            ON DUPLICATE KEY UPDATE 
            title='$title', job_code='$job_code', department_id=$dept_id, office='$office', status='$status', quantity=$quantity, managers='$managers', deadline=$deadline_val";
    
    if ($conn->query($sql)) {
        $count++;
    } else {
        $errors[] = "Error row $rowIndex (ID: $id): " . $conn->error;
    }
}

echo "<p style='color:green; font-weight:bold;'>Imported/Updated $count openings successfully.</p>";
if (!empty($errors)) {
    echo "<h3>Errors:</h3><ul style='color:red'>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}
echo "</body></html>";
?>
