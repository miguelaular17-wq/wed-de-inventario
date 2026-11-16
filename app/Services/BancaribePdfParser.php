<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Extractos Bancaribe en PDF.
 *
 * Formatos soportados:
 * 1) Estado de cuenta clásico ("Detalles de Movimientos"):
 *    FECHA REFERENCIA CARGO ABONO SALDO[OFICINA] + descripción en líneas siguientes.
 * 2) Conexión Bancaribe Personas (impresión de Movimientos):
 *    FECHA REFERENCIA → oficina/descripción → línea "+/- MONTO IMP DISPONIBLE REAL".
 */
class BancaribePdfParser
{
    private const MESES = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];

    private const OFICINAS = ['ADMINISTRACION', 'CARACAS', 'INTERNET', 'BANKING', 'TACHIRA'];

    private const MONTO = '(\d{1,3}(?:\.\d{3})*,\d{2})';

    /**
     * @return list<array{fecha: string, referencia: string, descripcion: string, monto: float, tipo: string}>
     */
    public function parse(string $path): array
    {
        $texto = (new Parser())->parseFile($path)->getText();

        return $this->parseTexto($texto);
    }

    /**
     * @return list<array{fecha: string, referencia: string, descripcion: string, monto: float, tipo: string}>
     */
    public function parseTexto(string $texto): array
    {
        $texto = $this->normalizar($texto);

        if ($this->esConexionPersonas($texto)) {
            return $this->parseConexionPersonas($texto);
        }

        return $this->parseEstadoCuenta($texto);
    }

    private function normalizar(string $texto): string
    {
        $texto = str_replace(["\u{00A0}", "\xC2\xA0"], ' ', $texto);

        return preg_replace("/[ \t]+/u", ' ', $texto) ?? $texto;
    }

    private function esConexionPersonas(string $texto): bool
    {
        return str_contains($texto, 'Conexión Bancaribe Personas')
            || str_contains($texto, 'Conexion Bancaribe Personas')
            || (str_contains($texto, 'Detalles de la Cuenta') && str_contains($texto, 'Transacción'))
            || (str_contains($texto, 'Detalles de la Cuenta') && str_contains($texto, 'Signo'));
    }

    /**
     * @return list<array{fecha: string, referencia: string, descripcion: string, monto: float, tipo: string}>
     */
    private function parseEstadoCuenta(string $texto): array
    {
        $inicio = '/^(\d{2})-([A-Z]{3})-(\d{4})\s+(\S+)\s+'.self::MONTO.'\s+'.self::MONTO.'\s+'.self::MONTO.'(.*)$/u';
        $fechaLinea = '/^\d{2}-[A-Z]{3}-\d{4}\b/';

        $movimientos = [];
        $actual = null;

        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $this->esEncabezado($linea)) {
                continue;
            }

            if (preg_match($fechaLinea, $linea)) {
                if ($actual !== null) {
                    $movimientos[] = $this->cerrarEstado($actual);
                    $actual = null;
                }

                // "SALDO FIN DIA" y similares no traen cargo/abono: no son movimientos.
                if (! preg_match($inicio, $linea, $m)) {
                    continue;
                }

                $mes = self::MESES[$m[2]] ?? null;
                if ($mes === null) {
                    continue;
                }

                $actual = [
                    'fecha' => sprintf('%04d-%02d-%02d', (int) $m[3], $mes, (int) $m[1]),
                    'referencia' => $m[4],
                    'cargo' => $this->monto($m[5]),
                    'abono' => $this->monto($m[6]),
                    'partes' => [$m[8]],
                ];

                continue;
            }

            if ($actual !== null) {
                $actual['partes'][] = $linea;
            }
        }

        if ($actual !== null) {
            $movimientos[] = $this->cerrarEstado($actual);
        }

        return array_values(array_filter($movimientos, fn (array $mov) => $mov['monto'] > 0));
    }

    /**
     * Impresión de Movimientos desde Conexión Bancaribe Personas.
     *
     * @return list<array{fecha: string, referencia: string, descripcion: string, monto: float, tipo: string}>
     */
    private function parseConexionPersonas(string $texto): array
    {
        $fechaRef = '/^(\d{2})-([A-Z]{3})-(\d{4})\s+(\S+)\s*$/u';
        $montoLinea = '/^([+\-])\s*'.self::MONTO.'\s+'.self::MONTO.'\s+'.self::MONTO.'\s+'.self::MONTO.'\s*$/u';

        $movimientos = [];
        $actual = null;

        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $this->esEncabezado($linea) || $this->esRuidoPersonas($linea)) {
                continue;
            }

            if (preg_match($fechaRef, $linea, $m)) {
                // Movimiento incompleto (sin monto): se descarta.
                $mes = self::MESES[$m[2]] ?? null;
                if ($mes === null) {
                    $actual = null;
                    continue;
                }
                $actual = [
                    'fecha' => sprintf('%04d-%02d-%02d', (int) $m[3], $mes, (int) $m[1]),
                    'referencia' => $m[4],
                    'partes' => [],
                ];
                continue;
            }

            if ($actual === null) {
                continue;
            }

            if (preg_match($montoLinea, $linea, $m)) {
                $esCargo = $m[1] === '-';
                $movimientos[] = [
                    'fecha' => $actual['fecha'],
                    'referencia' => $actual['referencia'],
                    'descripcion' => $this->descripcionPersonas($actual['partes']),
                    'monto' => $this->monto($m[2]),
                    'tipo' => $esCargo ? 'cargo' : 'abono',
                ];
                $actual = null;
                continue;
            }

            $actual['partes'][] = $linea;
        }

        return array_values(array_filter($movimientos, fn (array $mov) => $mov['monto'] > 0));
    }

    private function esEncabezado(string $linea): bool
    {
        return str_starts_with($linea, 'Fecha Oficina')
            || str_starts_with($linea, 'Fecha Referencia')
            || preg_match('/^P[aá]gina\s+\d+/iu', $linea) === 1
            || preg_match('/^--\s*\d+\s+of\s+\d+\s*--$/', $linea) === 1
            || preg_match('/^(Signo|\+\/\-|Monto\b|IMP\b|Disponible\b|Real\b|Movimientos\b|Transacci[oó]n\b)/iu', $linea) === 1;
    }

    private function esRuidoPersonas(string $linea): bool
    {
        return str_contains($linea, 'Conexión Bancaribe')
            || str_contains($linea, 'Conexion Bancaribe')
            || str_starts_with($linea, 'http')
            || str_starts_with($linea, 'Imprimir')
            || str_starts_with($linea, 'Detalles de la Cuenta')
            || str_starts_with($linea, 'Agencia/Oficina')
            || preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}/', $linea) === 1;
    }

    /**
     * @param  list<string>  $partes
     */
    private function descripcionPersonas(array $partes): string
    {
        $texto = trim(implode(' ', $partes));
        $texto = preg_replace('/\bPunto fijo av\.?\s*tachira\b/iu', '', $texto) ?? $texto;
        $texto = preg_replace('/\bAdministraci[oó]n\s+caracas\b/iu', '', $texto) ?? $texto;
        $texto = preg_replace('/\bInternet\s+banking\b/iu', '', $texto) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/^(?:N[DC]\s*)+/u', '', $texto) ?? $texto;

        return mb_substr(trim($texto), 0, 255);
    }

    /**
     * @param  array{fecha: string, referencia: string, cargo: float, abono: float, partes: list<string>}  $mov
     * @return array{fecha: string, referencia: string, descripcion: string, monto: float, tipo: string}
     */
    private function cerrarEstado(array $mov): array
    {
        $tokens = preg_split('/\s+/u', trim(implode(' ', $mov['partes']))) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $t) => $t !== '' && ! in_array(mb_strtoupper($t), self::OFICINAS, true)
        ));

        $descripcion = implode(' ', $tokens);
        // Código de transacción pegado al inicio: "NDNDMECANISMO", "NCTRANSFERENCIA".
        $descripcion = preg_replace('/^(?:N[DC]\s*)+/u', '', $descripcion) ?? $descripcion;
        $descripcion = str_replace('ESTADO DECUENTA', 'ESTADO DE CUENTA', $descripcion);

        $esCargo = $mov['cargo'] > 0;

        return [
            'fecha' => $mov['fecha'],
            'referencia' => $mov['referencia'],
            'descripcion' => mb_substr(trim($descripcion), 0, 255),
            'monto' => $esCargo ? $mov['cargo'] : $mov['abono'],
            'tipo' => $esCargo ? 'cargo' : 'abono',
        ];
    }

    private function monto(string $raw): float
    {
        return (float) str_replace(',', '.', str_replace('.', '', $raw));
    }
}
