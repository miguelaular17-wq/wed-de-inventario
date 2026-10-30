<?php

namespace App\Services\Conciliacion;

/**
 * Excel del «Balance de Transacciones en el período» de Mercantil: una fila por lote con Lote, Importe Bruto
 * y Total (o Importe) Abonado, y al final «Total General». Si trae Importe de Descuento y Retención ISLR se usan;
 * si no, el descuento es Bruto − Abonado.
 */
class MercantilBalanceExcel
{
    public function __construct(private ExtractoParser $parser) {}

    /**
     * @return array{lotes: list<array{lote: string, bruto: float, descuento: float, retencion: float, abonado: float}>, total: array<string, float>}
     */
    public function leer(string $path, string $ext): array
    {
        $cols = null;
        $lotes = [];
        $total = [];
        foreach ($this->parser->filasCrudas($path, $ext) as $fila) {
            $celdas = array_map(fn ($c) => trim((string) $c), (array) $fila);
            if ($cols === null) {
                $cols = $this->columnas($celdas);

                continue;
            }
            $primera = mb_strtoupper($celdas[$cols['lote']] ?? '');
            if ($primera === '' && implode('', $celdas) === '') {
                continue;
            }
            $num = fn (?int $c) => $c === null ? null : $this->parser->normalizarMonto($celdas[$c] ?? '');
            $bruto = (float) ($num($cols['bruto']) ?? 0);
            $abonado = (float) ($num($cols['abonado']) ?? 0);
            if (str_starts_with($primera, 'TOTAL')) {
                $total = ['bruto' => round($bruto, 2), 'abonado' => round($abonado, 2)];
                break;
            }
            $lote = ltrim(preg_replace('/\D/', '', $primera) ?? '', '0');
            if ($lote === '' || $bruto <= 0) {
                continue;
            }
            $retencion = (float) ($num($cols['retencion']) ?? 0);
            $descuento = $cols['descuento'] !== null ? (float) ($num($cols['descuento']) ?? 0) : $bruto - $abonado - $retencion;
            $lotes[] = ['lote' => $lote, 'bruto' => round($bruto, 2), 'descuento' => round($descuento, 2), 'retencion' => round($retencion, 2), 'abonado' => round($abonado, 2)];
        }
        if ($cols === null) {
            throw new \RuntimeException('No se encontró la cabecera del balance (columnas «Lote», «Importe Bruto» y «Total Abonado»).');
        }

        return $this->validar($lotes, $total);
    }

    /**
     * @param  list<array{lote: string, bruto: float, descuento: float, retencion: float, abonado: float}>  $lotes
     */
    public function validar(array $lotes, array $total): array
    {
        if ($lotes === []) {
            throw new \RuntimeException('El archivo no trae lotes con importe bruto.');
        }
        foreach ($lotes as $l) {
            if ($l['abonado'] > $l['bruto'] + 0.02 || abs($l['bruto'] - $l['descuento'] - $l['retencion'] - $l['abonado']) > 0.02) {
                throw new \RuntimeException(sprintf(
                    'Lote %s: no cuadra (bruto %s − descuento %s − retención %s ≠ abonado %s).',
                    $l['lote'], number_format($l['bruto'], 2, ',', '.'), number_format($l['descuento'], 2, ',', '.'),
                    number_format($l['retencion'], 2, ',', '.'), number_format($l['abonado'], 2, ',', '.'),
                ));
            }
        }
        $suma = round(array_sum(array_column($lotes, 'bruto')), 2);
        if (($total['bruto'] ?? 0) > 0 && abs($suma - $total['bruto']) > 0.02) {
            throw new \RuntimeException(sprintf(
                'La suma de los lotes (Bs %s) no da el Total General del archivo (Bs %s): falta o sobra algún lote.',
                number_format($suma, 2, ',', '.'), number_format($total['bruto'], 2, ',', '.'),
            ));
        }

        return ['lotes' => $lotes, 'total' => $total];
    }

    /** @return array{lote: int, bruto: int, abonado: int, descuento: ?int, retencion: ?int}|null */
    private function columnas(array $celdas): ?array
    {
        $norm = array_map(fn ($c) => mb_strtolower(strtr($c, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']), 'UTF-8'), $celdas);
        $buscar = function (array $opciones) use ($norm): ?int {
            foreach ($norm as $i => $c) {
                foreach ($opciones as $o) {
                    if ($c === $o) {
                        return $i;
                    }
                }
            }

            return null;
        };
        $lote = $buscar(['lote']);
        $bruto = $buscar(['importe bruto', 'bruto']);
        $abonado = $buscar(['total abonado', 'importe abonado', 'abonado']);
        if ($lote === null || $bruto === null || $abonado === null) {
            return null;
        }

        return [
            'lote' => $lote, 'bruto' => $bruto, 'abonado' => $abonado,
            'descuento' => $buscar(['importe de descuento', 'descuento']),
            'retencion' => $buscar(['retencion islr', 'retencion']),
        ];
    }
}
