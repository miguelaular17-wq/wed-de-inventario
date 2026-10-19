<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Estado de cuenta Bancaribe en PDF ("Detalles de Movimientos de la Cuenta").
 *
 * Cada movimiento empieza con: FECHA REFERENCIA CARGO ABONO SALDO[OFICINA]
 * y la descripción sigue en las líneas siguientes, mezclada con la oficina.
 */
class BancaribePdfParser
{
    private const MESES = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];

    private const OFICINAS = ['ADMINISTRACION', 'CARACAS', 'INTERNET', 'BANKING'];

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
                    $movimientos[] = $this->cerrar($actual);
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
            $movimientos[] = $this->cerrar($actual);
        }

        return array_values(array_filter($movimientos, fn (array $mov) => $mov['monto'] > 0));
    }

    private function esEncabezado(string $linea): bool
    {
        return str_starts_with($linea, 'Fecha Oficina')
            || preg_match('/^P[aá]gina\s+\d+/iu', $linea) === 1
            || preg_match('/^--\s*\d+\s+of\s+\d+\s*--$/', $linea) === 1;
    }

    /**
     * @param  array{fecha: string, referencia: string, cargo: float, abono: float, partes: list<string>}  $mov
     * @return array{fecha: string, referencia: string, descripcion: string, monto: float, tipo: string}
     */
    private function cerrar(array $mov): array
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
