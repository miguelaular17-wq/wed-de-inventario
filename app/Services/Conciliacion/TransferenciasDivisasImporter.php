<?php

namespace App\Services\Conciliacion;

use App\Models\CompraDivisa;
use App\Models\ConciliacionLinea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;

/**
 * Reporte de transferencias en divisas del proveedor de cambio (PDF "Dashboard | Cloud Mai Services"):
 * cada envío en USD se pagó con un débito en Bs del banco por monto × tasa "Interna".
 * Se empareja con los débitos pendientes del extracto y se registra como compra de divisas conciliada.
 */
class TransferenciasDivisasImporter
{
    private const COLUMNAS = [
        'NRO' => 'nro', 'BANCO' => 'banco', 'TÍTULAR' => 'titular', 'TITULAR' => 'titular', 'CORREO' => 'correo',
        'REFERENCIA' => 'referencia', 'MONTO' => 'monto', 'FECHA' => 'fecha', 'BCV' => 'bcv', 'BINANCE' => 'binance',
        'INTERNA' => 'tasa', 'BINA' => 'dif_bina',
    ];

    /**
     * @return list<array{nro: int, banco: string, titular: string, correo: string, referencia: string, monto_usd: float, fecha: string, tasa: float, monto_bs: float}>
     */
    public function leerPdf(string $path): array
    {
        $pdf = (new Parser())->parseFile($path);
        $filas = [];

        foreach ($pdf->getPages() as $page) {
            $lineas = [];
            foreach ($page->getDataTm() as [$tm, $txt]) {
                $txt = (string) $txt;
                if ($txt === '') {
                    continue;
                }
                $lineas[(string) round((float) $tm[5])][] = ['x' => (float) $tm[4], 't' => $txt];
            }
            krsort($lineas, SORT_NUMERIC);

            $cols = null;
            $actual = null;
            foreach ($lineas as $chars) {
                usort($chars, fn ($a, $b) => $a['x'] <=> $b['x']);
                $palabras = $this->palabras($chars);

                if ($cols === null) {
                    $cols = $this->columnasDeEncabezado($palabras);
                    continue;
                }

                $celdas = $this->celdas($chars, $cols);
                if (preg_match('/^\d{1,5}$/', $celdas['nro'] ?? '')) {
                    if ($actual) {
                        $filas[] = $actual;
                    }
                    $actual = $celdas;
                } elseif ($actual && ! str_contains(mb_strtoupper(implode(' ', $celdas)), 'TOTAL')) {
                    foreach (['titular', 'correo', 'banco'] as $c) {
                        if (($celdas[$c] ?? '') !== '') {
                            $actual[$c] = trim(($actual[$c] ?? '') . ' ' . $celdas[$c]);
                        }
                    }
                }
            }
            if ($actual) {
                $filas[] = $actual;
            }
        }

        $out = [];
        foreach ($filas as $f) {
            $monto = $this->numero($f['monto'] ?? '');
            $tasa = $this->numero($f['tasa'] ?? '');
            $ref = preg_replace('/\D/', '', (string) ($f['referencia'] ?? ''));
            if ($monto <= 0 || $tasa <= 0 || $ref === '' || ! preg_match('#(\d{2})/(\d{2})/(\d{4})#', (string) ($f['fecha'] ?? ''), $d)) {
                continue;
            }
            $titular = trim(preg_replace('/\s*-?\d{2}\/\d{2}\/\d{2}\s*$/', '', (string) ($f['titular'] ?? '')) ?? '');
            $out[] = [
                'nro' => (int) $f['nro'],
                'banco' => trim((string) ($f['banco'] ?? '')),
                'titular' => $titular,
                'correo' => trim((string) ($f['correo'] ?? '')),
                'referencia' => $ref,
                'monto_usd' => $monto,
                'fecha' => "{$d[3]}-{$d[2]}-{$d[1]}",
                'tasa' => $tasa,
                'monto_bs' => round($monto * $tasa, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $transferencias
     * @return array{pares: list<array{t: array<string, mixed>, linea: ConciliacionLinea, como: string}>, sin_pareja: list<array<string, mixed>>, lineas_pendientes: int}
     */
    public function emparejar(array $transferencias, string $banco, string $titular, ?string $desde = null, ?string $hasta = null, array $simularDeshechas = []): array
    {
        $fechas = array_column($transferencias, 'fecha');
        $desde ??= $fechas ? date('Y-m-d', strtotime(min($fechas) . ' -3 days')) : null;
        $hasta ??= $fechas ? date('Y-m-d', strtotime(max($fechas) . ' +3 days')) : null;

        $lineas = $this->lineasPendientes($banco, $titular, $desde, $hasta);
        if ($simularDeshechas !== []) {
            // Vista previa de "rehacer": esos débitos cuentan como pendientes sin tocar la base.
            $lineas = $lineas->concat(ConciliacionLinea::query()->whereIn('compra_divisa_id', $simularDeshechas)->get());
        }
        $lineas = $lineas
            ->filter(fn (ConciliacionLinea $l) => $l->esCargo() && ! str_contains(mb_strtoupper((string) $l->descripcion), 'COMISION'))
            ->values();

        // Las ya registradas en una corrida anterior no se vuelven a emparejar (con otro débito del mismo monto).
        $registradas = $this->transferenciasYaRegistradas($banco, $titular, $desde, $hasta, $simularDeshechas);
        $yaRegistradas = [];
        $transferencias = array_values(array_filter($transferencias, function ($t) use ($registradas, &$yaRegistradas) {
            $clave = $t['referencia'] . '|' . number_format($t['monto_usd'], 2, '.', '');
            if (($registradas[$clave] ?? 0) > 0) {
                $registradas[$clave]--;
                $yaRegistradas[] = $t;

                return false;
            }

            return true;
        }));

        $dig = fn ($r) => preg_replace('/\D/', '', (string) $r);
        $diasEntre = fn ($l, $t) => abs((strtotime($l->fecha->format('Y-m-d')) - strtotime($t['fecha'])) / 86400);
        $montoOk = fn ($l, $t, float $tol) => abs(abs((float) $l->monto) - $t['monto_bs']) <= $tol;
        $refDe = fn ($l, $t) => strlen($t['referencia']) >= 6 && str_ends_with($dig($l->referencia), $t['referencia']);

        // Un débito cuya referencia es de otra transferencia del PDF no se le da a nadie más por monto
        // (ref 492736 de Jhonny Arias no puede ir a otra transferencia de $100 × 842).
        $refsDelPdf = [];
        foreach (array_merge($transferencias, $yaRegistradas) as $t) {
            if (strlen($t['referencia']) >= 6) {
                $refsDelPdf[$t['referencia']] = true;
            }
        }
        $reservada = function ($l, $t) use ($dig, $refsDelPdf) {
            $d = $dig($l->referencia);
            foreach ([6, strlen($d)] as $n) {
                $suf = substr($d, -$n);
                if ($suf !== '' && isset($refsDelPdf[$suf]) && $suf !== $t['referencia']) {
                    return true;
                }
            }

            return false;
        };

        $usadas = [];
        $pares = [];
        $pendientes = $transferencias;

        // Pasadas en orden de confianza; cada una recorre todas las transferencias que siguen sin pareja.
        $pasadas = [
            'referencia' => fn ($l, $t) => $dig($l->referencia) === $t['referencia'] && $montoOk($l, $t, max(1.0, $t['monto_bs'] * 0.005)),
            'referencia_final' => fn ($l, $t) => $refDe($l, $t) && $montoOk($l, $t, 1.0),
            // El banco cobró con otra tasa (ref 071187: $50 × 885 = 44.250 en el PDF, 43.750 = × 875 en el banco).
            'referencia_otra_tasa' => fn ($l, $t) => $refDe($l, $t) && $diasEntre($l, $t) <= 5
                && abs(abs((float) $l->monto) - $t['monto_bs']) / max(1, $t['monto_bs']) <= 0.05,
        ];
        foreach ($pasadas as $como => $coincide) {
            $quedan = [];
            foreach ($pendientes as $t) {
                $mejor = null;
                foreach ($lineas as $l) {
                    if (isset($usadas[$l->id]) || ! $coincide($l, $t)) {
                        continue;
                    }
                    $dif = abs(abs((float) $l->monto) - $t['monto_bs']);
                    if ($mejor === null || $dif < $mejor[1]) {
                        $mejor = [$l, $dif];
                    }
                }
                if ($mejor) {
                    $usadas[$mejor[0]->id] = true;
                    $pares[] = ['t' => $t, 'linea' => $mejor[0], 'como' => $como];
                } else {
                    $quedan[] = $t;
                }
            }
            $pendientes = $quedan;
        }

        // Pago en varios débitos del mismo día (uno con la referencia).
        $quedan = [];
        foreach ($pendientes as $t) {
            if ($partido = $this->pagoPartido($t, $lineas, $usadas, $dig, $reservada)) {
                foreach ($partido as $l) {
                    $usadas[$l->id] = true;
                }
                $pares[] = ['t' => $t, 'linea' => $partido[0], 'extras' => array_slice($partido, 1), 'como' => 'pago_partido'];
            } else {
                $quedan[] = $t;
            }
        }
        $pendientes = $quedan;

        // Último recurso: monto exacto y fecha cercana, sin tocar débitos con referencia de otra transferencia.
        $quedan = [];
        foreach ($pendientes as $t) {
            $mejor = null;
            foreach ($lineas as $l) {
                if (isset($usadas[$l->id]) || ! $montoOk($l, $t, 0.02) || $reservada($l, $t)) {
                    continue;
                }
                $dias = $diasEntre($l, $t);
                if ($dias <= 3 && ($mejor === null || $dias < $mejor[1])) {
                    $mejor = [$l, $dias];
                }
            }
            if ($mejor) {
                $usadas[$mejor[0]->id] = true;
                $pares[] = ['t' => $t, 'linea' => $mejor[0], 'como' => 'monto_fecha'];
            } else {
                $quedan[] = $t;
            }
        }

        return ['pares' => $pares, 'sin_pareja' => $quedan, 'ya_registradas' => $yaRegistradas, 'lineas_pendientes' => $lineas->count()];
    }

    /**
     * El envío se pagó en varios débitos del mismo día: uno lleva la referencia y el resto completa el monto
     * (ref 062614: 4.000.000 + 1.307.000 = $5.800 × 915).
     *
     * @param  Collection<int, ConciliacionLinea>  $lineas
     * @param  array<int, true>  $usadas
     * @return list<ConciliacionLinea>|null  [débito con la referencia, ...débitos que completan]
     */
    private function pagoPartido(array $t, Collection $lineas, array $usadas, callable $dig, callable $reservada): ?array
    {
        $ref = $t['referencia'];
        if (strlen($ref) < 6) {
            return null;
        }
        $cent = fn ($v) => (int) round(abs((float) $v) * 100);
        $esperado = $cent($t['monto_bs']);
        $diasDe = fn ($l) => abs((strtotime($l->fecha->format('Y-m-d')) - strtotime($t['fecha'])) / 86400);

        foreach ($lineas as $base) {
            if (isset($usadas[$base->id]) || ! str_ends_with($dig($base->referencia), $ref) || $diasDe($base) > 5) {
                continue;
            }
            $falta = $esperado - $cent($base->monto);
            if ($falta <= 100) {
                continue;
            }
            $diaBase = strtotime($base->fecha->format('Y-m-d'));
            $candidatas = $lineas->filter(fn ($l) => $l->id !== $base->id
                && ! isset($usadas[$l->id])
                && ! $reservada($l, $t)
                && abs((strtotime($l->fecha->format('Y-m-d')) - $diaBase) / 86400) <= 1
                && $cent($l->monto) < $falta + 100)->values();

            foreach ($candidatas as $a) {
                if (abs($cent($a->monto) - $falta) <= 100) {
                    return [$base, $a];
                }
            }
            foreach ($candidatas as $i => $a) {
                foreach ($candidatas->slice($i + 1) as $b) {
                    if (abs($cent($a->monto) + $cent($b->monto) - $falta) <= 100) {
                        return [$base, $a, $b];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Compras de este importador emparejadas solo por monto (ninguno de sus débitos lleva la referencia
     * de la transferencia). Son las únicas que pueden haber tomado el débito de otra transferencia.
     *
     * @return list<array{id: int, motivo: string, lineas: list<object>}>
     */
    public function comprasPorMonto(string $banco, string $titular, ?string $desde, ?string $hasta): array
    {
        $compras = CompraDivisa::query()
            ->whereRaw('UPPER(TRIM(banco)) LIKE ?', ['%' . mb_strtoupper(trim($banco)) . '%'])
            ->whereRaw('UPPER(TRIM(titular)) LIKE ?', ['%' . mb_strtoupper(trim($titular)) . '%'])
            ->when($desde, fn ($q) => $q->where('fecha', '>=', date('Y-m-d', strtotime($desde . ' -10 days'))))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', date('Y-m-d', strtotime($hasta . ' +10 days'))))
            ->where('motivo', 'like', 'Transferencia %(ref %')
            ->get(['id', 'motivo']);
        $lineas = DB::table('conciliacion_lineas')->whereIn('compra_divisa_id', $compras->pluck('id'))
            ->get(['id', 'compra_divisa_id', 'referencia', 'monto', 'fecha'])->groupBy('compra_divisa_id');

        $out = [];
        foreach ($compras as $c) {
            if (! preg_match('/\(ref (\d+)\)/', (string) $c->motivo, $m)) {
                continue;
            }
            $suyas = $lineas[$c->id] ?? collect();
            $conRef = $suyas->contains(fn ($l) => str_ends_with(preg_replace('/\D/', '', (string) $l->referencia), $m[1]));
            if (! $conRef) {
                $out[] = ['id' => (int) $c->id, 'motivo' => (string) $c->motivo, 'lineas' => $suyas->values()->all()];
            }
        }

        return $out;
    }

    /**
     * Deshace las compras indicadas: sus débitos vuelven a pendiente y la compra se borra.
     *
     * @param  list<int>  $ids
     */
    public function deshacerCompras(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids) {
            DB::table('conciliacion_lineas')->whereIn('compra_divisa_id', $ids)
                ->update(['estado' => 'pendiente', 'compra_divisa_id' => null, 'updated_at' => now()]);

            return CompraDivisa::query()->whereIn('id', $ids)->delete();
        });
    }

    /**
     * Compras de divisas creadas por este importador: el motivo guarda "$USD x tasa (ref REF)".
     *
     * @return array<string, int>  "ref|usd" => cantidad
     */
    private function transferenciasYaRegistradas(string $banco, string $titular, ?string $desde, ?string $hasta, array $excluir = []): array
    {
        $motivos = CompraDivisa::query()
            ->when($excluir !== [], fn ($q) => $q->whereNotIn('id', $excluir))
            ->whereRaw('UPPER(TRIM(banco)) LIKE ?', ['%' . mb_strtoupper(trim($banco)) . '%'])
            ->whereRaw('UPPER(TRIM(titular)) LIKE ?', ['%' . mb_strtoupper(trim($titular)) . '%'])
            ->when($desde, fn ($q) => $q->where('fecha', '>=', date('Y-m-d', strtotime($desde . ' -10 days'))))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', date('Y-m-d', strtotime($hasta . ' +10 days'))))
            ->where('motivo', 'like', 'Transferencia %(ref %')
            ->pluck('motivo');

        $out = [];
        foreach ($motivos as $motivo) {
            if (preg_match('/\$(\d+(?:\.\d+)?) x [\d.]+ \(ref (\d+)\)/', (string) $motivo, $m)) {
                $clave = $m[2] . '|' . number_format((float) $m[1], 2, '.', '');
                $out[$clave] = ($out[$clave] ?? 0) + 1;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{t: array<string, mixed>, linea: ConciliacionLinea}>  $pares
     */
    public function aplicar(array $pares): int
    {
        $hechas = 0;
        foreach ($pares as $par) {
            try {
                $this->aplicarPar($par['t'], $par['linea'], $par['extras'] ?? []);
                $hechas++;
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== 'linea_ocupada') {
                    throw $e;
                }
            }
        }

        return $hechas;
    }

    /**
     * @param  array<string, mixed>  $t
     * @param  list<ConciliacionLinea>  $extras  otros débitos del mismo pago partido
     */
    private function aplicarPar(array $t, ConciliacionLinea $l, array $extras = []): void
    {
        DB::transaction(function () use ($t, $l, $extras) {
            $lineas = array_merge([$l], $extras);
            $montoBanco = array_sum(array_map(fn ($x) => abs((float) $x->monto), $lineas));
            $tasaEfectiva = $t['monto_usd'] > 0 ? round($montoBanco / $t['monto_usd'], 6) : $t['tasa'];
            $otraTasa = abs($tasaEfectiva - $t['tasa']) >= 0.01;
            $fmtTasa = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

            $compra = CompraDivisa::create([
                'fecha' => $l->fecha,
                'banco' => $l->banco,
                'titular' => $l->titular,
                'referencia' => $l->referencia,
                'concepto' => 'COMPRA DE DIVISAS',
                'motivo' => mb_substr(sprintf(
                    'Transferencia %s · %s · $%s x %s (ref %s)%s',
                    mb_strtoupper($t['banco']),
                    $t['titular'],
                    number_format($t['monto_usd'], 2, '.', ''),
                    $fmtTasa($tasaEfectiva),
                    $t['referencia'],
                    ($otraTasa ? ' · tasa en el reporte ' . $fmtTasa($t['tasa']) : '')
                        . ($extras !== [] ? ' · pagado en ' . count($lineas) . ' débitos' : ''),
                ), 0, 255),
                'monto_bs' => $montoBanco,
                'monto_usd' => $t['monto_usd'],
                'tasa_cambio' => $tasaEfectiva,
                'es_conciliado' => true,
            ]);

            $ids = array_map(fn ($x) => $x->id, $lineas);
            $ok = DB::table('conciliacion_lineas')
                ->whereIn('id', $ids)
                ->where('estado', 'pendiente')
                ->whereNull('flujo_caja_id')
                ->whereNull('tesoreria_ingreso_id')
                ->whereNull('compra_divisa_id')
                ->update(['estado' => 'conciliado', 'compra_divisa_id' => $compra->id, 'updated_at' => now()]);

            if ($ok !== count($ids)) {
                // Otro proceso concilió la línea mientras tanto: no se deja la compra suelta.
                throw new \RuntimeException('linea_ocupada');
            }
        });
    }

    /** @return Collection<int, ConciliacionLinea> */
    private function lineasPendientes(string $banco, string $titular, ?string $desde, ?string $hasta): Collection
    {
        return ConciliacionLinea::query()
            ->whereRaw('UPPER(TRIM(banco)) LIKE ?', ['%' . mb_strtoupper(trim($banco)) . '%'])
            ->whereRaw('UPPER(TRIM(titular)) LIKE ?', ['%' . mb_strtoupper(trim($titular)) . '%'])
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta))
            ->where('estado', 'pendiente')
            ->whereNull('flujo_caja_id')
            ->whereNull('tesoreria_ingreso_id')
            ->whereNull('compra_divisa_id')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array{x: float, t: string}>  $chars
     * @return list<array{x: float, t: string}>
     */
    private function palabras(array $chars): array
    {
        $out = [];
        $prev = null;
        foreach ($chars as $c) {
            if (trim($c['t']) === '') {
                $prev = null;
                continue;
            }
            if ($prev === null || $c['x'] - $prev > 9) {
                $out[] = ['x' => $c['x'], 't' => ''];
            }
            $out[count($out) - 1]['t'] .= $c['t'];
            $prev = $c['x'];
        }

        return $out;
    }

    /**
     * @param  list<array{x: float, t: string}>  $palabras
     * @return array<string, float>|null  columna => x de inicio
     */
    private function columnasDeEncabezado(array $palabras): ?array
    {
        $cols = [];
        foreach ($palabras as $p) {
            $k = self::COLUMNAS[mb_strtoupper(trim($p['t']), 'UTF-8')] ?? null;
            if ($k === 'bcv' && isset($cols['bcv'])) {
                $k = 'dif_bcv';
            }
            if ($k !== null && ! isset($cols[$k])) {
                $cols[$k] = $p['x'];
            }
        }

        return isset($cols['nro'], $cols['referencia'], $cols['monto'], $cols['tasa']) ? $cols : null;
    }

    /**
     * @param  list<array{x: float, t: string}>  $chars
     * @param  array<string, float>  $cols
     * @return array<string, string>
     */
    private function celdas(array $chars, array $cols): array
    {
        asort($cols);
        $limites = array_values($cols);
        $nombres = array_keys($cols);
        $out = array_fill_keys($nombres, '');
        foreach ($chars as $c) {
            $i = 0;
            foreach ($limites as $j => $x) {
                if ($c['x'] >= $x - 3) {
                    $i = $j;
                }
            }
            $out[$nombres[$i]] .= $c['t'];
        }

        return array_map(fn ($v) => trim(preg_replace('/\s+/u', ' ', $v) ?? ''), $out);
    }

    private function numero(string $v): float
    {
        $v = str_replace(',', '', trim($v));

        return is_numeric($v) ? (float) $v : 0.0;
    }
}
