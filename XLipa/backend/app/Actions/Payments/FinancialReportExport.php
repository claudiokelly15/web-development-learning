<?php

namespace App\Actions\Payments;

/** Bounded text-only PDF: no HTML, scripts, remote resources, file access or rendering process. */
final class FinancialReportExport
{
    /** Minimal OOXML workbook. Strings are explicit inline strings, never formulas or links. */
    public static function xlsx(array $rows): string
    {
        abort_unless(count($rows) <= 40000, 422);
        $xml = static fn (string $value): string => htmlspecialchars(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="42" customWidth="1"/><col min="2" max="9" width="28" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $index => $row) {
            $sheet .= '<row r="'.($index + 1).'">';
            foreach (array_values($row) as $column => $value) {
                abort_unless($column < 26, 422);
                $ref = chr(65 + $column).($index + 1);
                if (is_int($value)) {
                    $sheet .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $text = (string) ($value ?? '');
                    abort_unless(mb_strlen($text) <= 32767, 422);
                    $sheet .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($text).'</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="XLipa financial report" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
        $archive = '';
        $directory = '';
        foreach ($files as $name => $content) {
            $size = strlen($content);
            $crc = crc32($content);
            $offset = strlen($archive);
            $archive .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0).$name.$content;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }

        return $archive.$directory.pack('VvvvvVVv', 0x06054B50, 0, 0, count($files), count($files), strlen($directory), strlen($archive), 0);
    }

    public static function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(function (mixed $value): string {
                $cell = (string) ($value ?? '');
                $cell = str_replace(["\0"], '', $cell);
                if (preg_match('/\A[\s\x00-\x20\x{FEFF}\x{200B}]*[=+@-]/u', $cell) === 1) {
                    $cell = "'".$cell;
                }

                return $cell;
            }, $row), ',', '"', '', "\r\n");
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /** Core PDF 1.4 with a fixed font, repeat header and numbered A4 pages. */
    public static function pdf(string $title, array $lines): string
    {
        abort_unless(count($lines) <= 20000, 422);
        $clean = static function (string $text): string {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

            return preg_replace('/[^\x20-\x7e]/', ' ', $text === false ? '' : $text);
        };
        $wrapped = [];
        foreach ($lines as $line) {
            array_push($wrapped, ...explode("\n", wordwrap($clean((string) $line), 84, "\n", true)));
        }
        $pages = array_chunk($wrapped === [] ? ['No matching records.'] : $wrapped, 43);
        $escape = static fn (string $text): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>'];
        $kids = [];
        foreach ($pages as $page => $content) {
            $pageId = 4 + $page * 2;
            $streamId = $pageId + 1;
            $kids[] = "$pageId 0 R";
            $commands = "0.04 0.10 0.24 rg 0 752 595 90 re f\nBT /F1 21 Tf 1 1 1 rg 40 795 Td (XLipa) Tj ET\nBT /F1 13 Tf 1 1 1 rg 40 771 Td (".$escape($clean($title)).") Tj ET\nBT /F1 10 Tf 0.05 0.10 0.20 rg 40 725 Td 15 TL\n";
            foreach ($content as $line) {
                $commands .= '('.$escape($line).") Tj T*\n";
            }
            $commands .= "ET\nBT /F1 9 Tf 0.2 0.3 0.4 rg 40 35 Td (XLipa | TZS | EAT | Page ".($page + 1).' of '.count($pages).") Tj ET\n";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
            $objects[$streamId] = '<< /Length '.strlen($commands).">>\nstream\n".$commands.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
