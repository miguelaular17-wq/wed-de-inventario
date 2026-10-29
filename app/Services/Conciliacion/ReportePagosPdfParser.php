<?php

namespace App\Services\Conciliacion;

use Smalot\PdfParser\Parser;

/**
 * Reporte de pagos recibidos de Cloud exportado a PDF ("Dashboard | Cloud Mai Services"):
 * NRO, Banco, Titular, Correo, Referencia, Monto, Fecha, BCV, Binance, Interna, Dif BCV, Dif Binance.
 *
 * Trae los cobros de todos los bancos (Wells Fargo, Citizens, Amerant, Mercantil Panamá, efectivo…);
 * se devuelve como filas con la misma cabecera que el reporte en Excel para importarlo igual.
 */
class ReportePagosPdfParser
{
    public const CABECERA = ['NRO', 'BANCO', 'TITULAR', 'CORREO', 'REFERENCIA', 'MONTO', 'FECHA'];

    public function texto(string $path): string
    {
        return (new Parser)->parseFile($path)->getText();
    }

    public function esReporte(string $texto): bool
    {
        $inicio = mb_strtolower(mb_substr($texto, 0, 600));

        return str_contains($inicio, 'cloud mai') && str_contains($inicio, 'correo')
            && str_contains($inicio, 'referencia') && str_contains($inicio, 'monto');
    }

    /**
     * @return array{filas: list<list<string>>, rechazadas: list<string>, total_reporte: ?float, total_leido: float, cuadra: bool}
     */
    public function leer(string $texto): array
    {
        $filas = [self::CABECERA];
        $rechazadas = [];
        $suma = 0.0;
        // Cada cobro empieza al inicio de línea con su número y el banco en minúsculas ("19 wellsfargo JESUS…");
        // el nombre puede seguir en las líneas siguientes y a veces viene pegado al banco ("proveedormercancEudoro").
        $bloques = preg_split('/\n(?=\d{1,5} [a-z])/', "\n" . str_replace("\r", '', $texto)) ?: [];
        foreach ($bloques as $bloque) {
            $plano = trim(preg_replace('/\s+/u', ' ', $bloque) ?? '');
            if (! preg_match('/^\d{1,5} [a-z]/', $plano)) {
                continue;
            }
            // El correo puede venir pegado a la referencia ("…@gmail.com767301") y la referencia al monto
            // ("20406570565100.00"); después de la fecha vienen BCV, Binance, Interna y Dif BCV.
            $ok = preg_match(
                '~^(\d{1,5}) ([a-z]+)\s*(.*?)\s*([^\s@]+@(?:[A-Za-z0-9-]+\.)+[A-Za-z]{2,3})\s*([\d ,.]+?)\s*(\d{2}/\d{2}/\d{4})\s*([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+(-?[\d.,]+)~u',
                $plano, $m,
            );
            $partes = $ok ? $this->referenciaYMonto($m[5], (float) $m[7], (float) $m[9], (float) $m[10]) : null;
            if ($partes === null) {
                if (count($rechazadas) < 15) {
                    $rechazadas[] = 'PDF: no se pudo leer «' . mb_substr($plano, 0, 90) . '»';
                }

                continue;
            }
            [$referencia, $monto] = $partes;
            $suma += $monto;
            // "ROSSANA MARTINEZ -31/07/26": la nota de la fecha original no es parte del nombre.
            $titular = trim(preg_replace('~\s*-\d{2}/\d{2}/\d{2,4}\s*$~', '', $m[3]) ?? $m[3]);
            $filas[] = [$m[1], $m[2], $titular, $m[4], $referencia, number_format($monto, 2, '.', ''), $m[6]];
        }

        $total = preg_match('/TOTAL:.*?([\d,]+\.\d{2})/s', $texto, $t) ? (float) str_replace(',', '', $t[1]) : null;

        return [
            'filas' => $filas,
            'rechazadas' => $rechazadas,
            'total_reporte' => $total,
            'total_leido' => round($suma, 2),
            'cuadra' => $total !== null && abs($total - $suma) < 0.01,
        ];
    }

    /**
     * "637333 117.50" o pegados "20406570565100.00". Pegados, se prueba cada corte y gana el monto que
     * reproduce la columna Dif BCV del mismo reporte: monto × (1 − Interna / BCV).
     *
     * @return array{0: string, 1: float}|null
     */
    private function referenciaYMonto(string $crudo, float $bcv, float $interna, float $difBcv): ?array
    {
        $crudo = trim(str_replace(',', '', $crudo));
        if (preg_match('/^(\d+)\s+(\d+\.\d{2})$/', $crudo, $m)) {
            return [$m[1], (float) $m[2]];
        }
        if (! preg_match('/^(\d+)(\.\d{2})$/', $crudo, $m)) {
            return null;
        }
        $digitos = $m[1];
        $mejor = null;
        for ($k = 1; $k <= min(6, strlen($digitos) - 4); $k++) {
            $monto = (float) (substr($digitos, -$k) . $m[2]);
            $referencia = substr($digitos, 0, -$k);
            if ($monto <= 0 || $digitos[strlen($digitos) - $k] === '0') {
                continue;
            }
            $error = $bcv > 0 ? abs($monto * (1 - $interna / $bcv) - $difBcv) : 0.0;
            // Sin diferencia (Interna = BCV) no hay con qué desempatar: referencias de 6 u 11 dígitos, las usuales.
            $largoUsual = in_array(strlen($referencia), [6, 10, 11], true) ? 0 : 1;
            $clave = [round($error, 2) > 0.05 ? 1 : 0, abs($difBcv) < 0.005 ? $largoUsual : 0, round($error, 4)];
            if ($mejor === null || $clave < $mejor[0]) {
                $mejor = [$clave, $referencia, $monto];
            }
        }

        return $mejor && $mejor[0][0] === 0 ? [$mejor[1], $mejor[2]] : null;
    }
}
