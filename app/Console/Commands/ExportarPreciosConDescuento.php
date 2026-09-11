<?php

namespace App\Console\Commands;

use App\Models\V2\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class ExportarPreciosConDescuento extends Command
{
    protected $signature = 'inventario:exportar-precios
        {--descuento=25 : Porcentaje de descuento}
        {--output= : Ruta del archivo XLSX}';

    protected $description = 'Exporta productos con existencia, precio unitario y precio con descuento';

    private const CATEGORIAS_EXCLUIDAS = [
        'ACCESORIOS DE BEBE',
        'ACCESORIOS DE NIÑO',
        'BEBE',
        'BIOSEGURIDAD',
        'BISUTERIA',
        'BODYS',
        'CARTERAS Y BOLSOS',
        'CONJUNTOS',
        'DECORACION',
        'DECORACION Y ARREGLOS',
        'LENCERIA',
        'LIQUIDACION',
        'MOVISTAR',
        'PANTALONES',
        'PRODUCTOS DE CONSUMO',
        'SIN CATEGORÍA',
        'TEXTILES',
        'UNIFORMES',
        '',
    ];

    public function handle(): int
    {
        $descuento = (float) $this->option('descuento');
        if ($descuento < 0 || $descuento > 100) {
            $this->error('El descuento debe estar entre 0 y 100.');

            return self::FAILURE;
        }

        $output = $this->outputPath();
        File::ensureDirectoryExists(dirname($output));

        $query = Producto::query()
            ->select(['id', 'nombre', 'precio_unidad'])
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('oculto')->orWhere('oculto', false);
            })
            ->whereHas('stock', function ($query) {
                $query->where('existencia', '>', 0);
            })
            ->whereNotIn(
                DB::raw("UPPER(TRIM(COALESCE(categoria, '')))"),
                self::CATEGORIAS_EXCLUIDAS
            )
            ->orderBy('nombre');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->error('No se encontraron productos que cumplan los filtros.');

            return self::FAILURE;
        }

        $headers = [
            'Nombre del Producto',
            'Precio 1 (Costo por Unidad)',
            'Precio 3 (Descuento '.$this->formatPercentage($descuento).'%)',
        ];

        $this->writeXlsx(
            $output,
            $headers,
            $query->cursor()->map(function (Producto $producto) use ($descuento) {
                $precio = round((float) $producto->precio_unidad, 2);

                return [
                    (string) $producto->nombre,
                    $precio,
                    round($precio * (1 - $descuento / 100), 2),
                ];
            }),
            $total
        );

        $this->info("Excel creado con {$total} productos.");
        $this->line($output);

        return self::SUCCESS;
    }

    private function outputPath(): string
    {
        $output = trim((string) $this->option('output'));
        if ($output === '') {
            return storage_path('app/exports/Productos_Precio_Descuento_25_'.now()->format('Ymd_His').'.xlsx');
        }

        if (! preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output)) {
            $output = base_path($output);
        }

        return str_ends_with(strtolower($output), '.xlsx') ? $output : $output.'.xlsx';
    }

    private function formatPercentage(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function writeXlsx(string $path, array $headers, iterable $rows, int $total): void
    {
        $temporarySheet = tempnam(sys_get_temp_dir(), 'productos_xlsx_');
        if ($temporarySheet === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }

        $sheet = fopen($temporarySheet, 'wb');
        if ($sheet === false) {
            throw new RuntimeException('No se pudo abrir el archivo temporal.');
        }

        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($sheet, '<dimension ref="A1:C'.($total + 1).'"/>');
        fwrite($sheet, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($sheet, '<cols><col min="1" max="1" width="65" customWidth="1"/><col min="2" max="3" width="30" customWidth="1"/></cols>');
        fwrite($sheet, '<sheetData>');
        fwrite($sheet, '<row r="1">');
        foreach ($headers as $index => $header) {
            fwrite($sheet, $this->textCell($this->columnName($index + 1).'1', $header, 1));
        }
        fwrite($sheet, '</row>');

        $rowNumber = 2;
        foreach ($rows as $row) {
            fwrite($sheet, '<row r="'.$rowNumber.'">');
            fwrite($sheet, $this->textCell('A'.$rowNumber, (string) $row[0]));
            fwrite($sheet, $this->numberCell('B'.$rowNumber, (float) $row[1]));
            fwrite($sheet, $this->numberCell('C'.$rowNumber, (float) $row[2]));
            fwrite($sheet, '</row>');
            $rowNumber++;
        }

        fwrite($sheet, '</sheetData>');
        fwrite($sheet, '<autoFilter ref="A1:C'.($total + 1).'"/>');
        fwrite($sheet, '</worksheet>');
        fclose($sheet);

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporarySheet);
            throw new RuntimeException('No se pudo crear el archivo XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFile($temporarySheet, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($temporarySheet);
    }

    private function textCell(string $reference, string $value, int $style = 0): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'
            .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</t></is></c>';
    }

    private function numberCell(string $reference, float $value): string
    {
        return '<c r="'.$reference.'" s="2"><v>'.number_format($value, 2, '.', '').'</v></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + $column % 26).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Productos" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
