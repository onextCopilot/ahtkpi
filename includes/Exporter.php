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
 * Rows are arrays of cells. A cell may be a scalar, or an array
 * ['v' => value, 'num' => true] to hint Excel to treat it as a number.
 */
class Exporter
{
    private static function cell($cell): string
    {
        if (is_array($cell)) {
            $v = $cell['v'] ?? '';
            if (!empty($cell['num'])) {
                return '<td style="mso-number-format:\'\#\,\#\#0\';">' . htmlspecialchars((string) $v) . '</td>';
            }
            return '<td>' . nl2br(htmlspecialchars((string) $v)) . '</td>';
        }
        // Force text for things that look like long IDs / leading zeros
        return '<td style="mso-number-format:\'\@\';">' . nl2br(htmlspecialchars((string) $cell)) . '</td>';
    }

    /** Stream an .xls download built from an HTML table. */
    public static function streamXls(string $filename, string $title, array $headers, array $rows): void
    {
        if (!preg_match('/\.xls$/i', $filename)) $filename .= '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1" cellspacing="0" cellpadding="4">';
        if ($title !== '') {
            $span = max(1, count($headers));
            echo '<tr><td colspan="' . $span . '" style="font-weight:bold;font-size:14px;">' . htmlspecialchars($title) . '</td></tr>';
        }
        echo '<tr>';
        foreach ($headers as $h) echo '<th style="background:#0f172a;color:#fff;font-weight:bold;">' . htmlspecialchars((string) $h) . '</th>';
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo self::cell($cell);
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
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
