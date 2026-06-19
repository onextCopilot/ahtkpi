<?php
/**
 * Dependency-free .xlsx reader (ZipArchive + SimpleXML) - no PhpSpreadsheet.
 * hrm_xlsx_rows($path): array of rows; each row is an array indexed by 0-based
 * column number with string values. Sparse cells are filled in.
 */

function hrm_xlsx_col_index(string $ref): int
{
    // "B12" -> 1 ; "AA3" -> 26
    $letters = preg_replace('/[0-9]+/', '', $ref);
    $n = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

function hrm_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) { throw new RuntimeException('ZipArchive không khả dụng'); }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) { throw new RuntimeException('Không mở được file xlsx'); }

    // Shared strings.
    $shared = [];
    if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $x = simplexml_load_string($ss);
        if ($x !== false) {
            foreach ($x->si as $si) {
                $shared[] = hrm_xlsx_si_text($si);
            }
        }
    }

    // First worksheet.
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) { throw new RuntimeException('Không tìm thấy sheet1'); }
    $sx = simplexml_load_string($sheetXml);
    if ($sx === false) { throw new RuntimeException('Lỗi đọc sheet'); }

    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $cells = [];
        $maxCol = -1;
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $idx = $ref !== '' ? hrm_xlsx_col_index($ref) : count($cells);
            $type = (string)$c['t'];
            if ($type === 's') {
                $val = isset($shared[(int)$c->v]) ? $shared[(int)$c->v] : '';
            } elseif ($type === 'inlineStr') {
                $val = hrm_xlsx_si_text($c->is);
            } else {
                $val = isset($c->v) ? (string)$c->v : '';
            }
            $cells[$idx] = trim($val);
            if ($idx > $maxCol) { $maxCol = $idx; }
        }
        $out = [];
        for ($i = 0; $i <= $maxCol; $i++) { $out[$i] = $cells[$i] ?? ''; }
        $rows[] = $out;
    }
    return $rows;
}

function hrm_xlsx_si_text($si): string
{
    $t = '';
    if (isset($si->t)) { $t .= (string)$si->t; }
    foreach ($si->r as $r) { $t .= (string)$r->t; }
    return $t;
}
