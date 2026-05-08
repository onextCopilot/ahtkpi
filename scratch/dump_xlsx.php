<?php
function dump_xlsx_header($filename) {
    $zip = new ZipArchive();
    if ($zip->open($filename) === TRUE) {
        // Read shared strings
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $xml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string)($si->t ?: $si->r->t);
            }
        }

        // Read sheet1
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        $i = 0;
        foreach ($xml->sheetData->row as $row) {
            $cols = [];
            foreach ($row->c as $c) {
                $val = (string)$c->v;
                if ((string)$c['t'] == 's') {
                    $val = $sharedStrings[$val] ?? $val;
                }
                $cols[] = $val;
            }
            $rows[] = $cols;
            if (++$i >= 5) break;
        }
        $zip->close();
        return $rows;
    }
    return null;
}

$file = '/Users/hyuncao/Onext Digital/GitHub_Projects/ahtkpi/modules/hrm/data/sys4386-candidates-01032023-01042023.report.15.16.08.05.26.xlsx';
$data = dump_xlsx_header($file);
header('Content-Type: application/json');
echo json_encode(array_slice($data, 0, 10), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
