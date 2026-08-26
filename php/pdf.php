<?php
function download_simple_pdf(string $filename, string $title, array $headers, array $rows): never
{
    $lines = [$title, 'Gerado em ' . date('d/m/Y H:i'), ''];
    $lines[] = implode(' | ', $headers);
    foreach ($rows as $row) {
        $values = array_map(static fn($value) => str_replace(['|', "\n", "\r"], ['/', ' ', ' '], (string) $value), array_values($row));
        $lines[] = implode(' | ', $values);
    }
    $pages = array_chunk($lines, 42);
    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $pageIds = [];
    foreach ($pages as $pageLines) {
        $stream = "BT\n/F1 10 Tf\n50 800 Td\n";
        foreach ($pageLines as $index => $line) {
            $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $line);
            $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
            $stream .= ($index ? "0 -17 Td\n" : '') . "($text) Tj\n";
        }
        $stream .= 'ET';
        $contentId = count($objects) + 1;
        $pageId = $contentId + 1;
        $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream";
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
        $pageIds[] = $pageId;
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($id) => "$id 0 R", $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$object\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($objects as $id => $_) $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf; exit;
}
