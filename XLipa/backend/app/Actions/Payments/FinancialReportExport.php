<?php

namespace App\Actions\Payments;

/** Bounded text-only PDF: no HTML, scripts, remote resources, file access or rendering process. */
final class FinancialReportExport
{
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
            $text = iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $text);

            return preg_replace('/[^\x20-\x7e]/', ' ', $text === false ? '' : $text);
        };
        $wrapped = [];
        foreach ($lines as $line) {
            array_push($wrapped, ...explode("\n", wordwrap($clean((string) $line), 94, "\n", true)));
        }
        $pages = array_chunk($wrapped === [] ? ['No matching records.'] : $wrapped, 43);
        $escape = static fn (string $text): string => str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $text);
        $objects = [1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];
        foreach ($pages as $page => $content) {
            $pageId = 4 + $page*2;
            $streamId = $pageId+1;
            $kids[] = "$pageId 0 R";
            $commands = "0.04 0.10 0.24 rg 0 752 595 90 re f\nBT /F1 21 Tf 1 1 1 rg 40 795 Td (XLipa) Tj ET\nBT /F1 13 Tf 1 1 1 rg 40 771 Td (".$escape($clean($title)).") Tj ET\nBT /F1 10 Tf 0.05 0.10 0.20 rg 40 725 Td 15 TL\n";
            foreach ($content as $line) {
                $commands .= '('.$escape($line).") Tj T*\n";
            }
            $commands .= "ET\nBT /F1 9 Tf 0.2 0.3 0.4 rg 40 35 Td (XLipa | TZS | EAT | Page ".($page+1).' of '.count($pages).") Tj ET\n";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
            $objects[$streamId] = '<< /Length '.strlen($commands).">>\nstream\n".$commands.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' ."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets,1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n",$offset);
        }

        return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
