<?php

namespace App\Support;

use ZipArchive;

class SimpleXlsxWriter
{
    /**
     * @param  array<string, list<list<string|int|float|null>>>  $sheets
     */
    public static function toString(array $sheets): string
    {
        if ($sheets === []) {
            $sheets = ['Hoja1' => [['Sin datos']]];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo Excel.');
        }

        $sheetFiles = [];
        $i = 1;
        foreach ($sheets as $name => $rows) {
            $safe = self::sheetName((string) $name, $i);
            $sheetFiles[] = $safe;
            $zip->addFromString('xl/worksheets/sheet'.$i.'.xml', self::sheetXml($rows));
            $i++;
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheetFiles)));
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheetFiles));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($sheetFiles)));
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->close();

        $bin = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return $bin;
    }

    private static function sheetName(string $name, int $index): string
    {
        $name = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $name);
        $name = trim($name);
        if ($name === '') {
            $name = 'Hoja'.$index;
        }

        return mb_substr($name, 0, 31);
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

        $r = 1;
        foreach ($rows as $row) {
            $xml .= '<row r="'.$r.'">';
            $c = 0;
            foreach ($row as $value) {
                $ref = self::col($c).$r;
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'"><v>'.self::number($value).'</v></c>';
                } else {
                    $text = self::xml((string) ($value ?? ''));
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
                }
                $c++;
            }
            $xml .= '</row>';
            $r++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function col(int $index): string
    {
        $name = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $name = chr(65 + ($n % 26)).$name;
            $n = intdiv($n, 26);
        }

        return $name;
    }

    private static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(int $sheets): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheets; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .$overrides
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<string>  $names
     */
    private static function workbookXml(array $names): string
    {
        $sheets = '';
        foreach ($names as $i => $name) {
            $id = $i + 1;
            $sheets .= '<sheet name="'.self::xml($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private static function workbookRels(int $sheets): string
    {
        $rels = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        for ($i = 1; $i <= $sheets; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }
}
