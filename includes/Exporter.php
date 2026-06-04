<?php
/**
 * Exporter — dependency-free spreadsheet / print helpers.
 *
 * No external library required:
 *   - streamXls(): emits an HTML <table> with an .xls Content-Type. Excel,
 *     LibreOffice and Google Sheets all open it; UTF-8 (Vietnamese) safe.
 *   - streamCsv(): UTF-8 (BOM) CSV for raw data.
 *   - renderPrintable(): a clean printable HTML page that auto-opens the
 *     browser print dialog (→ "Save as PDF").
 *
 * Rows are arrays of cells. A cell may be a scalar, or an array:
 *   ['v' => value, 'num' => bool, 'align' => left|right|center, 'bold' => bool]
 *
 * A row itself may be a plain list of cells (normal data row) or a styled row:
 *   ['type' => 'total'|'section'|'subtotal', 'cells' => [...]]
 *   ['type' => 'section', 'label' => 'Group title']   // spans all columns
 */
class Exporter
{
    private static function cellHtml($cell, string $rowStyle = ''): string
    {
        $num = false; $align = 'left'; $bold = false; $v = $cell;
        if (is_array($cell)) {
            $v = $cell['v'] ?? '';
            $num = !empty($cell['num']);
            $align = $cell['align'] ?? ($num ? 'right' : 'left');
            $bold = !empty($cell['bold']);
        }
        $fmt = $num ? "mso-number-format:'\\#\\,\\#\\#0';" : "mso-number-format:'\\@';";
        $style = $fmt . 'text-align:' . $align . ';' . ($bold ? 'font-weight:bold;' : '') . $rowStyle;
        $text = $num ? number_format((float) $v, 0, ',', '.') : nl2br(htmlspecialchars((string) $v));
        return '<td style="' . $style . '">' . $text . '</td>';
    }

    /** Stream an .xls download built from a styled HTML table. */
    public static function streamXls(string $filename, string $title, array $headers, array $rows): void
    {
        if (!preg_match('/\.xls$/i', $filename)) $filename .= '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><style>td,th{border:1px solid #cfd8e3;padding:5px 9px;font-family:Calibri,Arial,sans-serif;font-size:11pt;vertical-align:middle;}</style></head><body>';
        echo self::tableHtml($headers, $rows, $title);
        echo '</body></html>';
        exit;
    }

    /**
     * Build the styled <table> HTML (title bar + header + section/subtotal/
     * total/data rows). Reused by streamXls and the printable PDF view.
     */
    public static function tableHtml(array $headers, array $rows, string $title = ''): string
    {
        $ncol = max(1, count($headers));
        $h = '<table border="1" cellspacing="0" cellpadding="5" class="exp-table" style="border-collapse:collapse;width:100%;">';
        if ($title !== '') {
            $h .= '<tr><td colspan="' . $ncol . '" style="background:#1e3a5f;color:#fff;font-weight:bold;font-size:15pt;text-align:center;height:34px;">' . htmlspecialchars($title) . '</td></tr>';
        }
        $h .= '<tr>';
        foreach ($headers as $col) {
            $h .= '<th style="background:#0f172a;color:#ffffff;font-weight:bold;text-align:center;height:28px;">' . htmlspecialchars((string) $col) . '</th>';
        }
        $h .= '</tr>';

        $zebra = 0;
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['type'])) {
                if ($row['type'] === 'section') {
                    $label = $row['label'] ?? '';
                    $lvl = (int) ($row['level'] ?? 1);
                    $bgByLevel = [1 => '#bcd0ea', 2 => '#d2e0f2', 3 => '#e3ecf8', 4 => '#f0f5fb'];
                    $bg = $bgByLevel[$lvl] ?? '#dbe6f3';
                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;', max(0, $lvl - 1));
                    $h .= '<tr><td colspan="' . $ncol . '" style="background:' . $bg . ';color:#1e3a5f;font-weight:bold;">' . $indent . htmlspecialchars($label) . '</td></tr>';
                    continue;
                }
                $bg = $row['type'] === 'total' ? '#fde68a' : '#eef2f7';
                $rowStyle = 'background:' . $bg . ';font-weight:bold;';
                $h .= '<tr>';
                foreach (($row['cells'] ?? []) as $cell) {
                    $c = is_array($cell) ? $cell : ['v' => $cell];
                    $c['bold'] = true;
                    $h .= self::cellHtml($c, $rowStyle);
                }
                $h .= '</tr>';
                continue;
            }
            $bg = ($zebra++ % 2 === 1) ? 'background:#f6f9fc;' : '';
            $h .= '<tr>';
            foreach ($row as $cell) $h .= self::cellHtml($cell, $bg);
            $h .= '</tr>';
        }
        $h .= '</table>';
        return $h;
    }

    /** Stream a UTF-8 CSV download. */
    public static function streamCsv(string $filename, array $headers, array $rows): void
    {
        if (!preg_match('/\.csv$/i', $filename)) $filename .= '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $flat = array_map(fn($c) => is_array($c) ? ($c['v'] ?? '') : $c, $row);
            fputcsv($out, $flat);
        }
        fclose($out);
        exit;
    }

    /**
     * Render a printable HTML page (for "Save as PDF"). $bodyHtml is the inner
     * content; the page auto-triggers window.print() unless ?noprint=1.
     */
    public static function renderPrintable(string $title, string $bodyHtml): void
    {
        $auto = empty($_GET['noprint']);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
        echo '<style>
            * { box-sizing: border-box; }
            body { font-family: "Times New Roman", serif; color: #111; margin: 32px; }
            h1 { font-size: 20px; text-align: center; margin: 0 0 4px; }
            .sub { text-align: center; color: #555; font-size: 13px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { border: 1px solid #888; padding: 6px 8px; text-align: left; }
            th { background: #eee; }
            td.num, th.num { text-align: right; }
            .toolbar { text-align: center; margin-bottom: 16px; }
            .toolbar button { padding: 8px 18px; font-size: 14px; cursor: pointer; border: 1px solid #333; border-radius: 6px; background:#0f172a; color:#fff; }
            @media print { .toolbar { display: none; } body { margin: 0; } }
        </style></head><body>';
        echo '<div class="toolbar"><button onclick="window.print()">🖨️ In / Lưu PDF</button></div>';
        echo $bodyHtml;
        if ($auto) echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},400);});</script>';
        echo '</body></html>';
        exit;
    }
}
