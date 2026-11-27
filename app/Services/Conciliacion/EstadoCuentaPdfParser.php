<?php

namespace App\Services\Conciliacion;

use Smalot\PdfParser\Parser;

/**
 * Estados de cuenta en PDF (Citizens, Amerant, Wells Fargo, Mercantil Panamá).
 * Se lee el texto del PDF y se comparan las sumas con los totales del propio estado.
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
            $this->esWellsFargo($texto) => $this->wellsFargo($texto),
            $this->esMercantilPanama($texto) => $this->mercantilPanama($texto),
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

    private function esWellsFargo(string $t): bool
    {
        return stripos($t, 'wells fargo') !== false
            && (str_contains($t, 'Historial de transacciones') || stripos($t, 'Account transaction history') !== false);
    }

    private function esMercantilPanama(string $t): bool
    {
        return (stripos($t, 'mercantilbanco.com.pa') !== false || stripos($t, 'torre mercantil') !== false)
            && (str_contains($t, 'Detalle de Transacciones') || str_contains($t, 'Total Débitos') || str_contains($t, 'Total Debitos'));
    }

    /**
     * Estado de cuenta Mercantil Banco (Panamá), moneda USD.
     * Filas: DD/MMM/YYYY + descripción + (ref) + monto + saldo. El signo sale del cambio de saldo.
     * En débitos a veces el PDF pega monto y referencia (p. ej. 60.00598534).
     */
    private function mercantilPanama(string $t): array
    {
        $hasta = null;
        if (preg_match('#Fecha:\s*(\d{2})/([A-Za-z]{3})/(\d{4})#', $t, $m)) {
            $hasta = sprintf('%04d-%02d-%02d', (int) $m[3], self::MESES[strtolower($m[2])] ?? 12, (int) $m[1]);
        }

        $saldoIni = null;
        $totDeb = null;
        $totCre = null;
        $saldoFin = null;
        if (preg_match('/([\d,]+\.\d{2})\s+([\d,]+\.\d{2})\s+([\d,]+\.\d{2})\s+([\d,]+\.\d{2})\s*Nombre\s*:/u', $t, $m)) {
            $saldoIni = $this->num($m[1]);
            $a = $this->num($m[2]);
            $b = $this->num($m[3]);
            $saldoFin = $this->num($m[4]);
            // Labels: Débitos luego Créditos, pero a veces el extracto los deja en orden inverso.
            if (abs(($saldoIni + $a - $b) - $saldoFin) <= abs(($saldoIni + $b - $a) - $saldoFin)) {
                $totCre = $a;
                $totDeb = $b;
            } else {
                $totCre = $b;
                $totDeb = $a;
            }
        }
        // El ITBMS suele ir suelto después de «Saldo Final :» (antes de Moneda:USD).
        $itbms = 0.0;
        if (preg_match('/Saldo Final\s*:\s*([\d,]+\.\d{2})/u', $t, $m)) {
            $itbms = $this->num($m[1]);
        } elseif (preg_match('/Saldo Final\s*:\s*\R+\s*([\d,]+\.\d{2})/u', $t, $m)) {
            $itbms = $this->num($m[1]);
        }

        $mov = [];
        $prevBal = $saldoIni;
        $vistos = [];
        foreach (preg_split('/\R/', $t) as $raw) {
            $l = trim($raw);
            if (! preg_match('#^(\d{2})/([A-Za-z]{3})/(\d{4})(.+)$#u', $l, $m)) {
                continue;
            }
            $mes = self::MESES[strtolower($m[2])] ?? 0;
            if ($mes < 1) {
                continue;
            }
            $rest = trim($m[4]);
            if (! preg_match_all('/([\d,]+\.\d{2})/', $rest, $mm) || $mm[1] === []) {
                continue;
            }
            $amts = $mm[1];
            $balance = $this->num((string) end($amts));
            $sinBal = (string) preg_replace('/\s*'.preg_quote((string) end($amts), '/').'\s*$/', '', $rest);
            $montoAbs = null;
            $desc = $sinBal;
            if (preg_match('/([\d,]+\.\d{2})\s*$/', $sinBal, $a)) {
                $montoAbs = $this->num($a[1]);
                $desc = trim((string) preg_replace('/([\d,]+\.\d{2})\s*$/', '', $sinBal));
                $desc = trim((string) preg_replace('/[\t ]+\d{3,}$/', '', $desc));
            } elseif (preg_match('/([\d,]+\.\d{2})(\d{4,})\s*$/', $sinBal, $a)) {
                // Débito con referencia pegada: 60.00598534 / 25,000.00976999
                $montoAbs = $this->num($a[1]);
                $desc = trim((string) preg_replace('/([\d,]+\.\d{2})\d{4,}\s*$/', '', $sinBal));
            }
            if ($montoAbs === null || $montoAbs < 0.005) {
                continue;
            }

            $fecha = sprintf('%04d-%02d-%02d', (int) $m[3], $mes, (int) $m[1]);
            $signo = 1;
            if ($prevBal !== null) {
                $delta = round($balance - $prevBal, 2);
                $signo = $delta >= 0 ? 1 : -1;
                if (abs(abs($delta) - $montoAbs) > 0.02 && abs($delta) >= 0.01) {
                    $montoAbs = abs($delta);
                }
            }
            $desc = trim((string) preg_replace('/\s+/', ' ', $desc));
            $clave = $fecha.'|'.$desc.'|'.round($signo * $montoAbs, 2).'|'.$balance;
            if (isset($vistos[$clave])) {
                continue; // el PDF repite encabezado/movimientos en cada página
            }
            $vistos[$clave] = true;
            $mov[] = [
                'fecha' => $fecha,
                'descripcion' => $desc,
                'monto' => round($signo * $montoAbs, 2),
            ];
            $prevBal = $balance;
        }

        $fechas = array_column($mov, 'fecha');
        $cargosTot = $totDeb !== null ? round($totDeb + max(0, (float) $itbms), 2) : null;

        return [
            'banco' => 'Mercantil Panamá',
            'cuenta' => preg_match('/\b(\d{9})\b/', $t, $m) ? $m[1] : null,
            'desde' => $fechas ? min($fechas) : null,
            'hasta' => $hasta,
            'movimientos' => $this->limpiar($mov),
            'totales' => [
                'abonos' => $totCre,
                'cargos' => $cargosTot,
            ],
        ];
    }

    /**
     * Wells Fargo Business Checking (estado en español): Historial con Fecha MM/D, descripción y
     * monto en Depósitos/Créditos o Retiros/Débitos (a veces + saldo diario final).
     */
    private function wellsFargo(string $t): array
    {
        $hasta = null;
        if (preg_match('/(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)\s+de\s+(\d{4})/iu', $t, $m)) {
            $mesesEs = [
                'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
                'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
            ];
            $hasta = sprintf('%04d-%02d-%02d', (int) $m[3], $mesesEs[mb_strtolower($m[2])] ?? 12, (int) $m[1]);
        } elseif (preg_match('#Saldo final al\s*(\d{1,2})/(\d{1,2})#iu', $t, $m)) {
            $anio = preg_match('/\b(20\d{2})\b/', $t, $y) ? (int) $y[1] : (int) date('Y');
            $hasta = sprintf('%04d-%02d-%02d', $anio, (int) $m[1], (int) $m[2]);
        }

        $desde = null;
        if (preg_match('#Saldo inicial al\s*(\d{1,2})/(\d{1,2})#iu', $t, $m)) {
            $desde = $this->fecha((int) $m[1], (int) $m[2], $hasta);
        }

        $mov = [];
        $enHistorial = false;
        $cur = null;
        foreach (preg_split('/\R/', $t) as $raw) {
            $l = trim($raw);
            if ($l === '') {
                continue;
            }
            if (preg_match('/^Historial de transacciones\b/u', $l) || stripos($l, 'Account transaction history') === 0) {
                // "(continuación)" repite el encabezado en cada página: no cortes el movimiento abierto.
                $enHistorial = true;

                continue;
            }
            if (! $enHistorial) {
                continue;
            }
            if (preg_match('/^(Totales\b|El Saldo Diario Final|Resumen de saldo|Important information|Information about|Account information)/iu', $l)) {
                if ($cur) {
                    $this->cerrarWells($mov, $cur);
                    $cur = null;
                }
                $enHistorial = false;

                continue;
            }
            if (preg_match('/^(Historial de transacciones|Fecha|Número|de cheque|Descripción|Depósitos\/|Créditos|Retiros\/|Débitos|Saldo|diario final|Página\b|Traducciones|•)/u', $l)
                || preg_match('/^\d{1,2}\s+de\s+\p{L}+\s+de\s+\d{4}\b/u', $l)
            ) {
                continue;
            }
            if (preg_match('#^(\d{1,2})/(\d{1,2})\s+(.*)$#u', $l, $m)) {
                if ($cur) {
                    $this->cerrarWells($mov, $cur);
                }
                $cur = [
                    'fecha' => $this->fecha((int) $m[1], (int) $m[2], $hasta),
                    'lineas' => [trim($m[3])],
                ];

                continue;
            }
            if ($cur) {
                $cur['lineas'][] = $l;
            }
        }
        if ($cur) {
            $this->cerrarWells($mov, $cur);
        }

        $abonos = preg_match('/Depósitos\/\s*Créditos\s+([\d,]+\.\d{2})/u', $t, $m)
            ? $this->num($m[1])
            : (preg_match('/Depósitos\/Créditos\s+([\d,]+\.\d{2})/u', $t, $m) ? $this->num($m[1]) : null);
        if ($abonos === null && preg_match('/Totales\s*\$?\s*([\d,]+\.\d{2})\s*\$?\s*([\d,]+\.\d{2})/u', $t, $m)) {
            $abonos = $this->num($m[1]);
        }
        $cargos = null;
        if (preg_match('/Retiros\/\s*Débitos\s+-?\s*([\d,]+\.\d{2})/u', $t, $m)
            || preg_match('/Retiros\/Débitos\s+-?\s*([\d,]+\.\d{2})/u', $t, $m)
        ) {
            $cargos = $this->num($m[1]);
        } elseif (preg_match('/Totales\s*\$?\s*([\d,]+\.\d{2})\s*\$?\s*([\d,]+\.\d{2})/u', $t, $m)) {
            $cargos = $this->num($m[2]);
        }

        return [
            'banco' => 'Wells Fargo',
            'cuenta' => preg_match('/Número de cuenta:\s*(\d+)/u', $t, $m) ? $m[1] : null,
            'desde' => $desde,
            'hasta' => $hasta,
            'movimientos' => $this->limpiar($mov),
            'totales' => ['abonos' => $abonos, 'cargos' => $cargos],
        ];
    }

    /**
     * @param  list<array{fecha: string, descripcion: string, monto: float|null}>  $mov
     * @param  array{fecha: string, lineas: list<string>}  $cur
     */
    private function cerrarWells(array &$mov, array $cur): void
    {
        $lineas = $cur['lineas'];
        $montoAbs = null;
        for ($i = count($lineas) - 1; $i >= 0; $i--) {
            if (! preg_match_all('/([\d,]+\.\d{2})/', $lineas[$i], $mm) || $mm[1] === []) {
                continue;
            }
            $amts = $mm[1];
            // 1 monto = movimiento; 2+ = movimiento (+ saldo diario). Si hay más de 2, los dos últimos.
            if (count($amts) >= 2) {
                $montoAbs = $this->num($amts[count($amts) - 2]);
            } else {
                $montoAbs = $this->num($amts[0]);
            }
            // Quita los importes de la línea de monto para no ensuciar la descripción.
            $lineas[$i] = trim(preg_replace('/(?:\s*[\d,]+\.\d{2})+\s*$/', '', $lineas[$i]) ?? '');
            if ($lineas[$i] === '') {
                unset($lineas[$i]);
            }
            break;
        }
        if ($montoAbs === null || $montoAbs < 0.005) {
            return;
        }

        $desc = trim(preg_replace('/\s+/', ' ', implode(' ', $lineas)) ?? '');
        $d = mb_strtolower($desc);
        $esCredito = (bool) preg_match('/\bzelle from\b|\bdeposit\b|\binterest payment\b|\bdirect deposit\b|\bcredit\b/i', $d);
        $esDebito = (bool) preg_match('/\bzelle to\b|\bwt\b|\bwire\b|\bpurchase\b|\bwithdrawal\b|\bfee\b|\batm\b|\bonline transfer\b|\bbill pay\b|\bach debit\b|\brecurring\b|\bcheck\b/i', $d);
        if ($esCredito && $esDebito) {
            $esDebito = ! preg_match('/\bzelle from\b|\binterest payment\b/i', $d);
            $esCredito = ! $esDebito;
        }
        if (! $esCredito && ! $esDebito) {
            // Sin palabra clave: si la línea de monto venía muy indentada suele ser retiro; por defecto crédito.
            $esCredito = true;
        }
        $signo = $esDebito ? -1 : 1;

        $mov[] = [
            'fecha' => $cur['fecha'],
            'descripcion' => $desc,
            'monto' => $signo * $montoAbs,
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
