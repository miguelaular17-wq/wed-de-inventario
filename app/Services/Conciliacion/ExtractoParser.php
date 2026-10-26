<?php

namespace App\Services\Conciliacion;

use Carbon\Carbon;
use Generator;
use Shuchkin\SimpleXLS;
use Shuchkin\SimpleXLSX;

/**
 * Lee extractos bancarios (xlsx / xls / csv / txt) fila a fila con el mapeo fijo de cada banco.
 *
 * Cada elemento generado es uno de:
 *   ['tipo' => 'linea',   'fila' => n, 'datos' => [fecha, descripcion, referencia, monto, tipo]]
 *   ['tipo' => 'rechazo', 'fila' => n, 'motivo' => '...']
 * Las filas de cabecera, vacÃ­as o de saldo se omiten sin contarse (igual que antes).
 */
class ExtractoParser
{
    /** Filas que se revisan para ubicar la cabecera en bancos con detect_header. */
    private const FILAS_BUSCAR_CABECERA = 100;

    /**
     * Mapeo fijo de columnas por banco (0-based). Basado en los archivos reales de cada banco.
     * col_monto: columna Ãºnica (negativo = cargo); col_cargo / col_abono: Debe / Haber.
     */
    public const MAPEOS = [
        // F[5]: cabeceras | F[6]: datos. [1]=Fecha [2]=Ref [3]=DescripciÃ³n [4]=DÃ©bito [5]=CrÃ©dito
        'BANCAMIGA' => [
            'start_row' => 6, 'col_fecha' => 1, 'col_referencia' => 2, 'col_descripcion' => 3,
            'col_monto' => null, 'col_cargo' => 4, 'col_abono' => 5,
            'skip_desc' => ['saldo inicial', 'saldo final', 'totales'],
        ],
        // CSV ';': Fecha;Referencia;DescripciÃ³n;Cargo;Abono
        'BANCARIBE' => [
            'start_row' => 1, 'col_fecha' => 0, 'col_referencia' => 1, 'col_descripcion' => 2,
            'col_monto' => null, 'col_cargo' => 3, 'col_abono' => 4,
            'skip_desc' => ['saldo inicial', 'saldo final'],
        ],
        // Fecha|Referencia|DescripciÃ³n|Monto|Balance
        'BANESCO' => [
            'start_row' => 1, 'col_fecha' => 0, 'col_referencia' => 1, 'col_descripcion' => 2,
            'col_monto' => 3, 'col_cargo' => null, 'col_abono' => null,
            'skip_desc' => ['saldo inicial', 'saldo final'],
        ],
        // CSV ';' con comillas: Fecha;Ref;Desc;Importe;Saldo
        'BBVA' => [
            'start_row' => 1, 'col_fecha' => 0, 'col_referencia' => 1, 'col_descripcion' => 2,
            'col_monto' => 3, 'col_cargo' => null, 'col_abono' => null,
            'skip_desc' => ['saldo inicial', 'saldo final'], 'csv_semicolon' => true,
        ],
        // F[15]: cabecera | F[16..]: datos. [1]=Fecha [12]=Ref [7]=DescripciÃ³n [13]=Debe [15]=Haber
        'BNC' => [
            'start_row' => 16, 'col_fecha' => 1, 'col_referencia' => 12, 'col_descripcion' => 7,
            'col_monto' => null, 'col_cargo' => 13, 'col_abono' => 15,
            'skip_desc' => ['saldo inicial', 'saldo final'],
        ],
        // Export actual: F[0]=cabecera. Export viejo: cabecera mÃ¡s abajo (detect_header).
        'TESORO' => [
            'start_row' => 1, 'detect_header' => true, 'col_fecha' => 1, 'col_referencia' => 2, 'col_descripcion' => 4,
            'col_monto' => null, 'col_cargo' => 5, 'col_abono' => 6,
            'skip_desc' => ['saldo inicial', 'saldo final'],
        ],
        // [0]=fecha [1]=referencia [2]=concepto [3]=saldo [4]=monto (negativo=dÃ©bito)
        'VENEZUELA' => [
            'start_row' => 1, 'col_fecha' => 0, 'col_referencia' => 1, 'col_descripcion' => 2,
            'col_monto' => 4, 'col_cargo' => null, 'col_abono' => null,
            'skip_desc' => ['saldo inicial', 'saldo final'],
        ],
        // F[5]: cabecera | F[6]: SALDO INICIAL (skip). [0]=Fecha [1]=Ref [2]=DescripciÃ³n [3]=Monto
        'MERCANTIL' => [
            'start_row' => 6, 'col_fecha' => 0, 'col_referencia' => 1, 'col_descripcion' => 2,
            'col_monto' => 3, 'col_cargo' => null, 'col_abono' => null,
            'skip_desc' => ['saldo inicial', 'saldo final'],
        ],
    ];

    public function soportaBanco(string $banco): bool
    {
        return isset(self::MAPEOS[$banco]);
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function filas(string $path, string $ext, string $banco): Generator
    {
        $map = self::MAPEOS[$banco] ?? null;
        if ($map === null) {
            throw new FormatoExtractoException("No hay mapeo de columnas para {$banco}.");
        }

        $filas = $this->leerFilas($path, strtolower($ext), (bool) ($map['csv_semicolon'] ?? false));

        if (! empty($map['detect_header'])) {
            [$map, $filas] = $this->ubicarCabecera($map, $filas);
        }

        foreach ($filas as $idx => $data) {
            $resultado = $this->procesarFila($map, (int) $idx, is_array($data) ? $data : []);
            if ($resultado !== null) {
                yield $resultado;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $map
     * @param  list<mixed>  $data
     * @return array<string, mixed>|null
     */
    public function procesarFila(array $map, int $idx, array $data): ?array
    {
        $fila = $idx + 1;
        if ($idx < $map['start_row'] || ! isset($data[$map['col_fecha']])) {
            return null;
        }

        $fechaRaw = trim((string) $data[$map['col_fecha']]);
        if ($fechaRaw === '') {
            return null;
        }

        $desc = isset($data[$map['col_descripcion']]) ? trim((string) $data[$map['col_descripcion']]) : '';
        $descLower = mb_strtolower($desc, 'UTF-8');
        foreach ($map['skip_desc'] ?? [] as $sw) {
            if (str_contains($descLower, $sw)) {
                return null;
            }
        }

        $fecha = $this->normalizarFecha($fechaRaw);
        if ($fecha === null) {
            // Texto que no es fecha (tÃ­tulos, pies de pÃ¡gina): se omite como antes.
            if (! preg_match('/\d/', $fechaRaw)) {
                return null;
            }

            return ['tipo' => 'rechazo', 'fila' => $fila, 'motivo' => 'Fecha inválida'];
        }

        $esCargo = false;
        $monto = '0.00';
        if ($map['col_monto'] !== null) {
            $valor = $this->normalizarMonto($data[$map['col_monto']] ?? null);
            if ($valor === null) {
                return ['tipo' => 'rechazo', 'fila' => $fila, 'motivo' => 'Monto con formato inválido'];
            }
            $esCargo = str_starts_with($valor, '-');
            $monto = ltrim($valor, '-');
        } else {
            $cargo = $this->normalizarMonto($data[$map['col_cargo']] ?? null);
            $abono = $this->normalizarMonto($data[$map['col_abono']] ?? null);
            if ($cargo === null || $abono === null) {
                return ['tipo' => 'rechazo', 'fila' => $fila, 'motivo' => 'Monto con formato inválido'];
            }
            if ($this->noEsCero($cargo)) {
                $monto = ltrim($cargo, '-');
                $esCargo = true;
            } elseif ($this->noEsCero($abono)) {
                $monto = ltrim($abono, '-');
            }
        }

        if (! $this->noEsCero($monto)) {
            return ['tipo' => 'rechazo', 'fila' => $fila, 'motivo' => 'Sin monto'];
        }

        $referencia = isset($data[$map['col_referencia']]) ? trim((string) $data[$map['col_referencia']]) : '';
        // Referencias en notaciÃ³n cientÃ­fica (ej: 3.103E+10)
        if (preg_match('/^\d[\d\.]+E[+\-]\d+$/i', $referencia)) {
            $referencia = number_format((float) $referencia, 0, '', '');
        }

        return [
            'tipo' => 'linea',
            'fila' => $fila,
            'datos' => [
                'fecha' => $fecha,
                'descripcion' => mb_substr($desc, 0, 255),
                'referencia' => mb_substr($referencia, 0, 255),
                'monto' => $monto,
                'tipo' => $esCargo ? 'cargo' : 'abono',
            ],
        ];
    }

    /**
     * Devuelve el importe como texto decimal exacto con 2 decimales ("-1234.56"), sin pasar por float.
     * null = no se pudo interpretar. VacÃ­o = "0.00".
     */
    public function normalizarMonto(mixed $raw): ?string
    {
        if ($raw === null) {
            return '0.00';
        }
        if (is_int($raw)) {
            return $this->centavosATexto($raw * 100);
        }
        if (is_float($raw)) {
            return $this->centavosATexto((int) round($raw * 100));
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return '0.00';
        }

        $negativo = false;
        if (preg_match('/^\((.*)\)$/', $s, $m)) {
            $negativo = true;
            $s = $m[1];
        }
        $s = str_replace(['$', 'Bs.', 'Bs', 'BS', 'USD', ' ', "\u{00A0}"], '', $s);
        if (str_ends_with($s, '-')) {
            $negativo = true;
            $s = substr($s, 0, -1);
        }
        if (str_starts_with($s, '-')) {
            $negativo = ! $negativo;
            $s = substr($s, 1);
        } elseif (str_starts_with($s, '+')) {
            $s = substr($s, 1);
        }

        // NotaciÃ³n cientÃ­fica que a veces devuelve Excel.
        if (preg_match('/^\d+(\.\d+)?E[+\-]?\d+$/i', $s)) {
            $cent = (int) round(((float) $s) * 100);

            return $this->centavosATexto($negativo ? -$cent : $cent);
        }

        if (preg_match('/^[\d.]+,\d+$/', $s)) {
            // Formato europeo: 1.234.567,89
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (substr_count($s, '.') > 1 && ! str_contains($s, ',')) {
            // 1.234.567 sin decimales: los puntos son separadores de miles.
            $s = str_replace('.', '', $s);
        } else {
            // Formato americano: 1,234,567.89
            $s = str_replace(',', '', $s);
        }

        if (! preg_match('/^(\d+)(?:\.(\d*))?$/', $s, $m)) {
            return null;
        }

        $entero = ltrim($m[1], '0') ?: '0';
        $frac = str_pad($m[2] ?? '', 3, '0');
        if (strlen($entero) > 15) {
            return null;
        }
        $cent = ((int) $entero) * 100 + (int) substr($frac, 0, 2);
        if ((int) $frac[2] >= 5) {
            $cent++;
        }

        return $this->centavosATexto($negativo ? -$cent : $cent);
    }

    public function normalizarFecha(mixed $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $solo = explode(' ', str_replace('/', '-', $raw))[0];
        try {
            if (is_numeric($solo) && $solo > 20000) {
                // NÃºmero serial de Excel
                $fecha = Carbon::createFromTimestampUTC(((float) $solo - 25569) * 86400);
            } else {
                $fecha = Carbon::parse($solo);
            }
        } catch (\Throwable) {
            return null;
        }

        if ($fecha->year < 2000 || $fecha->year > 2100) {
            return null;
        }

        return $fecha->format('Y-m-d');
    }

    private function noEsCero(string $monto): bool
    {
        return ltrim($monto, '-') !== '0.00';
    }

    private function centavosATexto(int $cent): string
    {
        $signo = $cent < 0 ? '-' : '';
        $cent = abs($cent);

        return $signo.intdiv($cent, 100).'.'.str_pad((string) ($cent % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Filas tal cual vienen en el archivo (Excel o CSV), sin interpretar columnas.
     *
     * @return iterable<int, list<mixed>>
     */
    public function filasCrudas(string $path, string $ext): iterable
    {
        return $this->leerFilas($path, strtolower($ext), false);
    }

    /**
     * @return iterable<int, list<mixed>>
     */
    private function leerFilas(string $path, string $ext, bool $csvSemicolon): iterable
    {
        // Banesco (y otros portales) exportan una tabla HTML con extensión .xls: Excel la abre igual.
        if ($ext !== 'xlsx' && $this->esTablaHtml($path)) {
            return $this->leerTablaHtml($path);
        }

        if ($ext === 'xlsx') {
            $xlsx = SimpleXLSX::parse($path);
            if (! $xlsx) {
                throw new FormatoExtractoException('El archivo no es un Excel válido o está dañado.');
            }

            return $xlsx->readRows();
        }

        if ($ext === 'xls') {
            if (class_exists(SimpleXLS::class) && ($xls = LectorXls::parse($path))) {
                return $xls->rows();
            }
            if ($xlsx = SimpleXLSX::parse($path)) {
                return $xlsx->readRows();
            }
            throw new FormatoExtractoException('El archivo Excel (.xls) no se pudo leer.');
        }

        return $this->leerCsv($path, $csvSemicolon);
    }

    private function esTablaHtml(string $path): bool
    {
        $inicio = (string) @file_get_contents($path, false, null, 0, 4096);
        $inicio = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $inicio) ?? $inicio);

        return str_starts_with($inicio, '<') && preg_match('/<table\b/i', $inicio) === 1;
    }

    /**
     * Filas de una tabla HTML (<tr>/<td>), con el texto de cada celda limpio.
     *
     * @return list<list<string>>
     */
    private function leerTablaHtml(string $path): array
    {
        $html = (string) file_get_contents($path);
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
        }

        $dom = new \DOMDocument();
        $previo = libxml_use_internal_errors(true);
        try {
            $ok = $dom->loadHTML('<?xml encoding="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
        }
        if (! $ok) {
            throw new FormatoExtractoException('El archivo (.xls) es una tabla HTML que no se pudo leer.');
        }

        $filas = [];
        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $fila = [];
            foreach ($tr->childNodes as $celda) {
                if (! $celda instanceof \DOMElement || ! in_array(strtolower($celda->nodeName), ['td', 'th'], true)) {
                    continue;
                }
                $texto = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $celda->textContent) ?? '');
                $fila[] = $texto;
                for ($c = (int) $celda->getAttribute('colspan'); $c > 1; $c--) {
                    $fila[] = '';
                }
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @return Generator<int, list<string>>
     */
    private function leerCsv(string $path, bool $csvSemicolon): Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new FormatoExtractoException('No se pudo abrir el archivo.');
        }
        try {
            $i = 0;
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($i === 0) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                }
                if ($line === '') {
                    yield $i++ => [];

                    continue;
                }
                $sep = ',';
                if ($csvSemicolon || (str_contains($line, ';') && substr_count($line, ';') > substr_count($line, ','))) {
                    $sep = ';';
                }
                $row = array_map(fn ($v) => trim((string) $v, " \t\"'"), str_getcsv($line, $sep));
                yield $i++ => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Ubica la fila de cabecera (Tesoro) dentro de las primeras filas y reubica columnas.
     * Si no aparece, se usa el mapeo por defecto.
     *
     * @param  array<string, mixed>  $map
     * @param  iterable<int, list<mixed>>  $filas
     * @return array{0: array<string, mixed>, 1: iterable<int, list<mixed>>}
     */
    private function ubicarCabecera(array $map, iterable $filas): array
    {
        $iter = $filas instanceof \Iterator ? $filas : new \ArrayIterator(is_array($filas) ? $filas : iterator_to_array($filas));
        $iter->rewind();
        $buffer = [];
        while ($iter->valid() && count($buffer) < self::FILAS_BUSCAR_CABECERA) {
            $buffer[$iter->key()] = $iter->current();
            $iter->next();
        }

        foreach ($buffer as $idx => $row) {
            $cells = array_map(
                fn ($c) => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $c) ?? '')),
                is_array($row) ? $row : []
            );
            $joined = implode(' ', $cells);
            if (! str_contains($joined, 'FECHA')
                || ! (str_contains($joined, 'DEBITO') || str_contains($joined, 'DÉBITO') || str_contains($joined, 'CREDITO') || str_contains($joined, 'CRÉDITO'))) {
                continue;
            }
            foreach ($cells as $ci => $label) {
                if ($label === 'FECHA') {
                    $map['col_fecha'] = $ci;
                } elseif (str_contains($label, 'REFEREN')) {
                    $map['col_referencia'] = $ci;
                } elseif (str_contains($label, 'CONCEPTO') || str_contains($label, 'DESCRIP')) {
                    $map['col_descripcion'] = $ci;
                } elseif (str_contains($label, 'DEBIT') || str_contains($label, 'DÉBIT')) {
                    $map['col_cargo'] = $ci;
                    $map['col_monto'] = null;
                } elseif (str_contains($label, 'CREDIT') || str_contains($label, 'CRÉDIT')) {
                    $map['col_abono'] = $ci;
                    $map['col_monto'] = null;
                }
            }
            $map['start_row'] = $idx + 1;
            break;
        }

        $todas = (function () use ($buffer, $iter) {
            foreach ($buffer as $k => $v) {
                yield $k => $v;
            }
            while ($iter->valid()) {
                yield $iter->key() => $iter->current();
                $iter->next();
            }
        })();

        return [$map, $todas];
    }
}

