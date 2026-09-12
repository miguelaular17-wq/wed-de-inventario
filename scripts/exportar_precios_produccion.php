<?php

/**
 * Exporta precios con descuento desde producción (solo lectura).
 * No requiere Laravel ni Python. Solo PHP + extensión zip + pdo_pgsql.
 *
 * Uso:
 *   php scripts/exportar_precios_produccion.php
 *   php scripts/exportar_precios_produccion.php --descuento=25 --output=C:\Users\freyg\Downloads\precios.xlsx
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ejecutar solo por CLI.\n");
    exit(1);
}

if (! extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "Falta la extensión pdo_pgsql.\n");
    exit(1);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Falta la extensión zip.\n");
    exit(1);
}

// ── Conexión producción (solo SELECT) ───────────────────────────────────────
$config = [
    'host' => getenv('EXPORT_DB_HOST') ?: 'aws-1-us-east-2.pooler.supabase.com',
    'port' => getenv('EXPORT_DB_PORT') ?: '5432',
    'database' => getenv('EXPORT_DB_DATABASE') ?: 'postgres',
    'username' => getenv('EXPORT_DB_USERNAME') ?: 'postgres.hbhqbmzixgcvxkilwsau',
    'password' => getenv('EXPORT_DB_PASSWORD') ?: 'Wamqkdhf#snWa68',
    // Esquema real del inventario (NO public)
    'schema' => getenv('EXPORT_DB_SCHEMA') ?: 'inventario_v2',
    'sslmode' => getenv('EXPORT_DB_SSLMODE') ?: 'require',
    'descuento' => 25.0,
    'output' => null,
    'solo_directo' => false,
];

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--descuento=')) {
        $config['descuento'] = (float) substr($arg, 12);
    } elseif (str_starts_with($arg, '--output=')) {
        $config['output'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--schema=')) {
        $config['schema'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--port=')) {
        $config['port'] = substr($arg, 7);
    } elseif (str_starts_with($arg, '--sslmode=')) {
        $config['sslmode'] = substr($arg, 10);
    } elseif (str_starts_with($arg, '--host=')) {
        $config['host'] = substr($arg, 7);
    } elseif ($arg === '--directo') {
        $config['solo_directo'] = true;
    }
}

if ($config['descuento'] < 0 || $config['descuento'] > 100) {
    fwrite(STDERR, "El descuento debe estar entre 0 y 100.\n");
    exit(1);
}

$categoriasExcluidas = [
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

$schema = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $config['schema']) ?: 'inventario_v2';
$sslmode = preg_replace('/[^a-z]/', '', strtolower((string) $config['sslmode'])) ?: 'require';
$projectRef = 'hbhqbmzixgcvxkilwsau';
if (preg_match('/^postgres\.([a-z0-9]+)$/i', (string) $config['username'], $m)) {
    $projectRef = $m[1];
}

$intentos = [];
if (! $config['solo_directo']) {
    // Pooler session (5432) y transaction (6543)
    $intentos[] = [
        'label' => 'pooler:5432',
        'dsn' => sprintf('pgsql:host=%s;port=5432;dbname=%s;sslmode=%s', $config['host'], $config['database'], $sslmode),
        'user' => $config['username'],
    ];
    $intentos[] = [
        'label' => 'pooler:6543',
        'dsn' => sprintf('pgsql:host=%s;port=6543;dbname=%s;sslmode=%s', $config['host'], $config['database'], $sslmode),
        'user' => $config['username'],
    ];
}
// Conexión directa a la instancia (suele funcionar mejor desde Windows)
$intentos[] = [
    'label' => 'directo:5432',
    'dsn' => sprintf(
        'pgsql:host=db.%s.supabase.co;port=5432;dbname=%s;sslmode=%s',
        $projectRef,
        $config['database'],
        $sslmode
    ),
    'user' => 'postgres',
];

$pdo = null;
$errores = [];
foreach ($intentos as $intento) {
    try {
        echo "Probando conexion {$intento['label']}...\n";
        $candidate = new PDO($intento['dsn'], $intento['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 15,
        ]);
        $candidate->exec('SET search_path TO '.$schema.', public');
        $candidate->exec('SET default_transaction_read_only = on');
        $candidate->query('SELECT 1')->fetchColumn();
        $pdo = $candidate;
        echo "Conectado por {$intento['label']} (schema {$schema}, solo lectura).\n";
        break;
    } catch (Throwable $e) {
        $errores[] = $intento['label'].': '.$e->getMessage();
        echo "  falló {$intento['label']}\n";
    }
}

if (! $pdo instanceof PDO) {
    fwrite(STDERR, "No se pudo conectar a producción.\n");
    foreach ($errores as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    fwrite(STDERR, "\nPrueba: exportar_precios.bat --directo\n");
    fwrite(STDERR, "O confirma en Supabase que la IP no esté bloqueada y que la clave sea correcta.\n");
    exit(1);
}

$placeholders = implode(',', array_fill(0, count($categoriasExcluidas), '?'));
$sql = "
    SELECT
        p.nombre,
        COALESCE(p.precio_unidad, 0) AS precio_unidad
    FROM {$schema}.productos p
    WHERE p.activo = true
      AND (p.oculto IS NULL OR p.oculto = false)
      AND EXISTS (
            SELECT 1
            FROM {$schema}.stock_actual sa
            WHERE sa.producto_id = p.id
              AND sa.existencia > 0
      )
      AND UPPER(TRIM(COALESCE(p.categoria, ''))) NOT IN ({$placeholders})
    ORDER BY p.nombre ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($categoriasExcluidas);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al consultar (solo lectura): '.$e->getMessage()."\n");
    exit(1);
}

$rows = [];
while ($row = $stmt->fetch()) {
    $precio = round((float) $row['precio_unidad'], 2);
    $rows[] = [
        (string) $row['nombre'],
        $precio,
        round($precio * (1 - $config['descuento'] / 100), 2),
    ];
}

$total = count($rows);
if ($total === 0) {
    fwrite(STDERR, "No se encontraron productos con existencia bajo esos filtros.\n");
    exit(1);
}

$output = $config['output'];
if ($output === null || $output === '') {
    $home = getenv('USERPROFILE') ?: getenv('HOME') ?: sys_get_temp_dir();
    $output = rtrim(str_replace('\\', '/', $home), '/').'/Downloads/Productos_Con_Existencia_Descuento_25_'.date('Ymd_His').'.xlsx';
}
if (! str_ends_with(strtolower($output), '.xlsx')) {
    $output .= '.xlsx';
}

$dir = dirname($output);
if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
    fwrite(STDERR, "No se pudo crear el directorio de salida: {$dir}\n");
    exit(1);
}

$descuentoTxt = rtrim(rtrim(number_format((float) $config['descuento'], 2, '.', ''), '0'), '.');
$headers = [
    'Nombre del Producto',
    'Precio 1 (Costo por Unidad)',
    'Precio 3 (Descuento '.$descuentoTxt.'%)',
];

try {
    writeXlsx($output, $headers, $rows);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error creando Excel: '.$e->getMessage()."\n");
    exit(1);
}

echo "Excel creado con {$total} productos (schema {$schema}, solo lectura).\n";
echo $output."\n";
exit(0);

function writeXlsx(string $path, array $headers, array $rows): void
{
    $total = count($rows);
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
        fwrite($sheet, textCell(columnName($index + 1).'1', $header, 1));
    }
    fwrite($sheet, '</row>');

    $rowNumber = 2;
    foreach ($rows as $row) {
        fwrite($sheet, '<row r="'.$rowNumber.'">');
        fwrite($sheet, textCell('A'.$rowNumber, (string) $row[0]));
        fwrite($sheet, numberCell('B'.$rowNumber, (float) $row[1]));
        fwrite($sheet, numberCell('C'.$rowNumber, (float) $row[2]));
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

    $zip->addFromString('[Content_Types].xml', contentTypesXml());
    $zip->addFromString('_rels/.rels', rootRelationshipsXml());
    $zip->addFromString('xl/workbook.xml', workbookXml());
    $zip->addFromString('xl/_rels/workbook.xml.rels', workbookRelationshipsXml());
    $zip->addFromString('xl/styles.xml', stylesXml());
    $zip->addFile($temporarySheet, 'xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($temporarySheet);
}

function textCell(string $reference, string $value, int $style = 0): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

    return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'
        .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
        .'</t></is></c>';
}

function numberCell(string $reference, float $value): string
{
    return '<c r="'.$reference.'" s="2"><v>'.number_format($value, 2, '.', '').'</v></c>';
}

function columnName(int $column): string
{
    $name = '';
    while ($column > 0) {
        $column--;
        $name = chr(65 + ($column % 26)).$name;
        $column = intdiv($column, 26);
    }

    return $name;
}

function contentTypesXml(): string
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

function rootRelationshipsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';
}

function workbookXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Productos" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>';
}

function workbookRelationshipsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';
}

function stylesXml(): string
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
