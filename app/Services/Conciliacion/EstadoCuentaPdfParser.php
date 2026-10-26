<?php

namespace App\Services\Conciliacion;

use Smalot\PdfParser\Parser;

/**
 * Estados de cuenta en PDF de los bancos en EE.UU. (Citizens, Amerant). Se lee el texto del PDF y se
 * comparan las sumas con los totales que trae el propio estado para saber si se leyó completo.
 */
class EstadoCuentaPdfParser
{
    private const MESES = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /**
     * @return array{banco: string, cuenta: ?string, desde: ?string, hasta: ?string, movimientos: list<array{fecha: string, descripcion: string, monto: float}>, totales: array{abonos: ?float, cargos: ?float}, cuadra: bool}|null
     */
    public function leer(string $path): ?array
    {
        return $this->leerTexto((new Parser)->parseFile($path)->getText());
    }

    /** @return array<string, mixed>|null mismo formato que leer() */
    public function leerTexto(string $texto): ?array
    {
        $r = match (true) {
            str_contains($texto, 'Deposits&Credit') && stripos($texto, 'citizens') !== false => $this->citizens($texto),
            str_contains($texto, 'Account Activity') && stripos($texto, 'amerant') !== false => $this->amerant($texto),
            default => null,
        };
        if ($r === null) {
            return null;
        }

        $abonos = round(array_sum(array_map(fn ($m) => max(0, $m['monto']), $r['movimientos'])), 2);
        $cargos = round(-array_sum(array_map(fn ($m) => min(0, $m['monto']), $r['movimientos'])), 2);
        $r['cuadra'] = $r['totales']['abonos'] !== null && $r['totales']['cargos'] !== null
            && abs($abonos - $r['totales']['abonos']) < 0.005 && abs($cargos - $r['totales']['cargos']) < 0.005;

        return $r;
    }

    /** Texto sin espacios entre palabras; fechas MM/DD y secciones de débitos y créditos. */
    private function citizens(string $t): array
    {
        $hasta = preg_match('/through\s*([A-Za-z]{3})[a-z]*\s*(\d{1,2}),\s*(\d{4})/', $t, $m)
            ? sprintf('%04d-%02d-%02d', $m[3], self::MESES[strtolower($m[1])] ?? 12, $m[2]) : null;
        $mov = [];
        $signo = null;
        $cur = null;
        foreach (preg_split('/\R/', $t) as $l) {
            $l = trim($l);
            if (preg_match('/^(Other)?Debits(\(Continued\))?\*?$/', $l)) {
                $signo = -1;
            } elseif (preg_match('/^Deposits&Credits(\(Continued\))?$/', $l)) {
                $signo = 1;
            } elseif (preg_match('/^(Daily ?Balance|Balance ?Summary)/i', $l)) {
                $signo = null;
            }
            if ($signo !== null && preg_match('#^(\d{2})/(\d{2}) ([\d,]+\.\d{2})(.*)$#', $l, $m)) {
                if ($cur) {
                    $mov[] = $cur;
                }
                $cur = ['fecha' => $this->fecha((int) $m[1], (int) $m[2], $hasta), 'descripcion' => trim($m[4]), 'monto' => $signo * $this->num($m[3])];

                continue;
            }
            if ($cur && $l !== '' && ! preg_match('/^(PleaseSee|Page\d|ClearlyBetter|Date Amount|Deposits&Credits|TotalDeposits|Total|Daily ?Balance|Current ?Balance|Previous ?Balance|Balance ?Calculation)/i', $l)) {
                $cur['descripcion'] .= ' ' . $l;
            }
        }
        if ($cur) {
            $mov[] = $cur;
        }

        return [
            'banco' => 'Citizens',
            'cuenta' => preg_match('/X{4,}-?(\d{3}-?\d)/', $t, $m) ? str_replace('-', '', $m[1]) : null,
            'desde' => preg_match('/Beginning\s*([A-Za-z]{3})[a-z]*\s*(\d{1,2}),\s*(\d{4})/', $t, $m)
                ? sprintf('%04d-%02d-%02d', $m[3], self::MESES[strtolower($m[1])] ?? 1, $m[2]) : null,
            'hasta' => $hasta,
            'movimientos' => $this->limpiar($mov),
            'totales' => [
                'abonos' => preg_match('/Deposits&Credits?\s*\+\s*([\d,]+\.\d{2})/', $t, $m) ? $this->num($m[1]) : null,
                'cargos' => $this->sumaCargosCitizens($t),
            ],
        ];
    }

    private function sumaCargosCitizens(string $t): ?float
    {
        $debitos = preg_match('/^Debits\s*-\s*([\d,]+\.\d{2})/m', $t, $m) ? $this->num($m[1]) : null;
        $cheques = preg_match('/^Checks\s*-\s*([\d,]*\.\d{2})/m', $t, $m) ? $this->num($m[1]) : 0.0;

        return $debitos === null ? null : round($debitos + $cheques, 2);
    }

    /**
     * La actividad viene en orden de fecha ("Account Activity") y después se repite separada en débitos y créditos;
     * solo se lee la primera para no contar dos veces.
     */
    private function amerant(string $t): array
    {
        $hasta = preg_match('#Statement Date:\s*(\d{2})/(\d{2})/(\d{4})#', $t, $m) ? "{$m[3]}-{$m[1]}-{$m[2]}" : null;
        $mov = [];
        $cur = null;
        $enActividad = false;
        foreach (preg_split('/\R/', $t) as $l) {
            $l = trim($l);
            if (str_starts_with($l, 'Account Activity')) {
                $enActividad = true;

                continue;
            }
            if (preg_match('/^(Debits|Credits)( ?\(Continued\))?$|^Daily Ledger/', $l)) {
                $enActividad = false;
                $cur = null;

                continue;
            }
            if (! $enActividad) {
                continue;
            }
            if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) ?(\d{2})(.*)$/', $l, $m)) {
                $desc = trim($m[3]);
                if (preg_match('/^(Beginning|Ending) Balance/i', $desc)) {
                    $cur = null;

                    continue;
                }
                $cur = ['fecha' => $this->fecha(self::MESES[strtolower($m[1])], (int) $m[2], $hasta), 'descripcion' => $desc, 'monto' => null];
                if (preg_match('/^(.*?)\t?(-?)\$([\d,]+\.\d{2})$/', $desc, $a)) {
                    $mov[] = ['descripcion' => trim($a[1]), 'monto' => ($a[2] === '-' ? -1 : 1) * $this->num($a[3])] + $cur;
                    $cur = null;
                }

                continue;
            }
            if ($cur && preg_match('/^(-?)\$([\d,]+\.\d{2})$/', $l, $a)) {
                $mov[] = ['monto' => ($a[1] === '-' ? -1 : 1) * $this->num($a[2])] + $cur;
                $cur = null;

                continue;
            }
            if ($cur && $l !== '' && ! preg_match('/^(Account Number|Statement Date|Page\s*:|Debits Credits Balance|Date Description|\d{4} \d{7}|[A-Z]{18,}$)/', $l)) {
                $cur['descripcion'] .= ' ' . $l;
            }
        }

        return [
            'banco' => 'Amerant',
            'cuenta' => preg_match('/Account Number:\s*(\d+)/', $t, $m) ? $m[1] : null,
            'desde' => preg_match('#Beginning Balance as of\s*(\d{2})/(\d{2})/(\d{2,4})#', $t, $m)
                ? sprintf('%04d-%02d-%02d', strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3], $m[1], $m[2]) : null,
            'hasta' => $hasta,
            'movimientos' => $this->limpiar($mov),
            'totales' => [
                'abonos' => preg_match('/Deposits and Credits\s*\(\d+\)\s*\$([\d,]+\.\d{2})/', $t, $m) ? $this->num($m[1]) : null,
                'cargos' => preg_match('/Withdrawals and Debits\s*\(\d+\)\s*\$([\d,]+\.\d{2})/', $t, $m) ? $this->num($m[1]) : null,
            ],
        ];
    }

    /** El estado trae día y mes; el año sale de la fecha de cierre (un mes posterior al cierre es del año anterior). */
    private function fecha(int $mes, int $dia, ?string $hasta): string
    {
        $anio = $hasta ? (int) substr($hasta, 0, 4) : (int) date('Y');
        if ($hasta && $mes > (int) substr($hasta, 5, 2)) {
            $anio--;
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /** @return list<array{fecha: string, descripcion: string, monto: float}> */
    private function limpiar(array $mov): array
    {
        return array_values(array_map(fn ($m) => [
            'fecha' => $m['fecha'],
            'descripcion' => trim(preg_replace('/\s+/', ' ', $m['descripcion']) ?? ''),
            'monto' => round((float) $m['monto'], 2),
        ], array_filter($mov, fn ($m) => $m['monto'] !== null && abs((float) $m['monto']) >= 0.005)));
    }

    private function num(string $s): float
    {
        return (float) str_replace([',', '$', ' '], '', $s);
    }
}
