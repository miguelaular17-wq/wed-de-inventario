<?php

namespace App\Services\Conciliacion;

use App\Models\TesoreriaIngreso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte de Cashea (export: Fecha, Referencia, Debito bs, Usd, Status, Tipo pago, Nro cuota, Orden, Factura, Sucursal):
 * cada cuota que el cliente pagó por pago móvil a la cuenta de Cashea (BNC · L.S. CASHEA). Un mismo pago móvil puede
 * cubrir varias cuotas, así que se agrupa por fecha + referencia y se busca el abono con esa referencia y ese monto.
 * Cashea agrega filas de ajuste de $0,0033 que el banco no recibe: si con ellas no cuadra, se prueba sin ellas.
 */
class CasheaConciliador
{
    /** Filas de ajuste de redondeo de Cashea (USD casi cero). */
    private const USD_AJUSTE = 0.01;

    private const TOLERANCIA = 0.05;

    private const DIAS = 3;

    /** Diferencia aceptada con la referencia exacta: lo menor entre Bs 50 y el 1 % del pago. */
    private const DIFERENCIA_MAX_BS = 50.0;

    private const DIFERENCIA_MAX_PCT = 0.01;

    public function __construct(private ExtractoParser $parser) {}

    /**
     * @return list<array{fecha: string, ref: string, bs: float, usd: float, status: string, tipo: string, cuota: string, orden: string, factura: string, sucursal: string}>
     */
    public function leer(string $path, string $ext): array
    {
        $norm = fn ($v) => mb_strtoupper(trim((string) $v), 'UTF-8');
        $cols = null;
        $out = [];
        foreach ($this->parser->filasCrudas($path, $ext) as $fila) {
            $fila = array_values((array) $fila);
            if ($cols === null) {
                $celdas = array_map($norm, $fila);
                $c = fn (string $n) => ($i = array_search($n, $celdas, true)) === false ? null : (int) $i;
                $cols = ['fecha' => $c('FECHA'), 'ref' => $c('REFERENCIA'), 'bs' => $c('DEBITO BS'), 'usd' => $c('USD'), 'status' => $c('STATUS'),
                    'tipo' => $c('TIPO PAGO'), 'cuota' => $c('NRO CUOTA PAGADA'), 'orden' => $c('ORDEN'), 'factura' => $c('FACTURA'), 'sucursal' => $c('SUCURSAL')];
                if (in_array(null, [$cols['fecha'], $cols['ref'], $cols['bs']], true)) {
                    $cols = null;
                }

                continue;
            }
            $v = fn (string $k) => $cols[$k] === null ? '' : ($fila[$cols[$k]] ?? '');
            $raw = $v('fecha');
            $fecha = $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d')
                : (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $raw, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : $this->parser->normalizarFecha((string) $raw));
            $ref = ltrim(preg_replace('/\D/', '', (string) $v('ref')) ?? '', '0');
            $bs = (float) ($this->parser->normalizarMonto($v('bs')) ?? 0);
            if (! $fecha || $ref === '' || $bs <= 0) {
                continue;
            }
            $out[] = [
                'fecha' => $fecha, 'ref' => $ref, 'bs' => round($bs, 2), 'usd' => (float) ($this->parser->normalizarMonto($v('usd')) ?? 0),
                'status' => trim((string) $v('status')), 'tipo' => trim((string) $v('tipo')), 'cuota' => trim((string) $v('cuota')),
                'orden' => trim((string) $v('orden')), 'factura' => trim((string) $v('factura')), 'sucursal' => trim((string) $v('sucursal')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    public function verificar(array $filas): array
    {
        $pagos = $this->agrupar($filas);
        if ($pagos === []) {
            return ['pagos' => [], 'filas' => 0, 'cuentas' => [], 'copias' => 0, 'desde' => null, 'hasta' => null, 'abonos_sin_cashea' => []];
        }
        $fechas = array_column($pagos, 'fecha');
        $desde = date('Y-m-d', strtotime(min($fechas) . ' -1 day'));
        $hasta = date('Y-m-d', strtotime(max($fechas) . ' +' . self::DIAS . ' days'));

        [$porRef, $copias, $cuentas, $porCola] = $this->abonos($desde, $hasta);
        $usadas = [];
        foreach ($pagos as &$p) {
            $p['estado'] = null;
        }
        unset($p);
        // De la regla más segura a la menos: cada una recorre todos los pagos antes de pasar a la siguiente,
        // así una pareja dudosa nunca se queda con el abono exacto de otro pago.
        foreach (['exacto', 'sin_ajuste', 'partido', 'diferencia', 'cola'] as $regla) {
            foreach ($pagos as &$p) {
                if ($p['estado'] === null && ($r = $this->regla($regla, $p, $porRef, $porCola, $usadas))) {
                    $p = $r + $p;
                }
            }
            unset($p);
        }
        foreach ($pagos as &$p) {
            if ($p['estado'] === null) {
                $p = $this->sinPareja($p, $porRef[$p['ref']] ?? []) + $p;
            }
            $p['monto_banco'] = round(array_sum(array_map(fn ($l) => abs((float) $l->monto), $p['lineas'])), 2);
        }
        unset($p);

        // Lo que sobra del lado del banco: abonos pendientes del período del reporte que ningún pago de Cashea tomó.
        $finReporte = max($fechas);
        $enRevision = [];
        foreach ($pagos as $p) {
            if ($p['estado'] === 'revisar' && $p['linea']) {
                $enRevision[$p['linea']->id] = true;
            }
        }
        $sobrantes = [];
        foreach ($porRef as $lineas) {
            foreach ($lineas as $l) {
                if ($this->libre($l) && ! isset($usadas[$l->id]) && ! isset($enRevision[$l->id]) && $l->fecha >= min($fechas) && $l->fecha <= $finReporte) {
                    $sobrantes[] = $l;
                }
            }
        }
        usort($sobrantes, fn ($a, $b) => [$a->fecha, $a->id] <=> [$b->fecha, $b->id]);

        return ['pagos' => $pagos, 'filas' => count($filas), 'cuentas' => $cuentas, 'copias' => $copias, 'desde' => $desde, 'hasta' => $hasta,
            'abonos_sin_cashea' => $sobrantes];
    }

    /**
     * Un ingreso de Tesorería por día y cuenta («Cobros Cashea 14/08/2026 · 812 pagos»), ligado a cada abono.
     *
     * @param  list<array<string, mixed>>  $pagos
     * @return array{ingresos: int, lineas: int, monto: float}
     */
    public function aplicar(array $pagos, string $archivo, ?int $userId): array
    {
        $grupos = [];
        foreach ($pagos as $p) {
            if ($p['estado'] === 'conciliar') {
                foreach ($p['lineas'] as $l) {
                    $grupos[$l->banco . '|' . $l->titular . '|' . $l->fecha][] = $l;
                }
            }
        }
        $hecho = ['ingresos' => 0, 'lineas' => 0, 'monto' => 0.0];
        foreach ($grupos as $lineas) {
            DB::transaction(function () use ($lineas, $archivo, $userId, &$hecho) {
                $l0 = $lineas[0];
                $ingreso = TesoreriaIngreso::create([
                    'tipo' => 'banco', 'banco' => $l0->banco, 'titular' => $l0->titular, 'fecha' => $l0->fecha, 'monto' => 0,
                    'lote_referencia' => 'CASHEA-' . str_replace('-', '', $l0->fecha),
                    'descripcion' => mb_substr('Cobros Cashea ' . date('d/m/Y', strtotime($l0->fecha)) . ' · ' . $archivo, 0, 255),
                    'user_id' => $userId, 'es_conciliado' => true,
                ]);
                $ligadas = 0;
                foreach (array_chunk(array_map(fn ($l) => $l->id, $lineas), 500) as $ids) {
                    $ligadas += DB::table('conciliacion_lineas')->whereIn('id', $ids)->where('estado', 'pendiente')
                        ->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id')
                        ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
                        ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $ingreso->id, 'updated_at' => now()]);
                }
                if ($ligadas === 0) {
                    $ingreso->delete();

                    return;
                }
                // Lo que otro proceso haya tomado mientras tanto no entra en el monto.
                $monto = (float) DB::table('conciliacion_lineas')->where('tesoreria_ingreso_id', $ingreso->id)->sum('monto');
                $ingreso->update(['monto' => round(abs($monto), 2), 'descripcion' => mb_substr("Cobros Cashea " . date('d/m/Y', strtotime($l0->fecha)) . " · {$ligadas} pagos · {$archivo}", 0, 255)]);
                $hecho['ingresos']++;
                $hecho['lineas'] += $ligadas;
                $hecho['monto'] += round(abs($monto), 2);
            });
        }

        return $hecho;
    }

    /** @return list<array<string, mixed>> un elemento por pago móvil (fecha + referencia) */
    private function agrupar(array $filas): array
    {
        $out = [];
        foreach ($filas as $f) {
            $k = $f['fecha'] . '|' . $f['ref'];
            $out[$k] ??= ['fecha' => $f['fecha'], 'ref' => $f['ref'], 'bs' => 0.0, 'bs_sin_ajuste' => 0.0, 'filas' => 0, 'ajustes' => 0, 'detalle' => []];
            $out[$k]['bs'] = round($out[$k]['bs'] + $f['bs'], 2);
            if (abs($f['usd']) > self::USD_AJUSTE) {
                $out[$k]['bs_sin_ajuste'] = round($out[$k]['bs_sin_ajuste'] + $f['bs'], 2);
            } else {
                $out[$k]['ajustes']++;
            }
            $out[$k]['filas']++;
            if (count($out[$k]['detalle']) < 4) {
                $out[$k]['detalle'][] = trim("{$f['tipo']} cuota {$f['cuota']} · orden {$f['orden']} · {$f['sucursal']}");
            }
        }

        return array_values($out);
    }

    /**
     * Abonos de las cuentas de Cashea. El mismo movimiento cargado varias veces (extracto importado de nuevo)
     * cuenta una sola vez: se usa la copia pendiente y las demás se reportan.
     *
     * @return array{0: array<string, list<object>>, 1: int, 2: array<string, int>}
     */
    private function abonos(string $desde, string $hasta): array
    {
        $filas = DB::table('conciliacion_lineas')
            ->whereRaw('UPPER(titular) LIKE ?', ['%CASHEA%'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(fn ($q) => $q->where('tipo', 'abono')->orWhere(fn ($w) => $w->whereNull('tipo')->where('monto', '>', 0)))
            ->orderBy('id')
            ->get(['id', 'banco', 'titular', 'fecha', 'referencia', 'descripcion', 'monto', 'estado', 'flujo_caja_id', 'tesoreria_ingreso_id']);

        $movimientos = [];
        foreach ($filas as $l) {
            $l->fecha = substr((string) $l->fecha, 0, 10);
            $k = $l->banco . '|' . $l->titular . '|' . $l->fecha . '|' . $l->referencia . '|' . round((float) $l->monto, 2) . '|' . $l->descripcion;
            $movimientos[$k][] = $l;
        }
        $porRef = [];
        $porCola = [];
        $copias = 0;
        $cuentas = [];
        foreach ($movimientos as $copiasMov) {
            $copias += count($copiasMov) - 1;
            // Si una copia ya está conciliada, el movimiento ya se usó: las demás copias no se vuelven a conciliar.
            $tomada = collect($copiasMov)->first(fn ($l) => ! $this->libre($l));
            $l = $tomada ?? $copiasMov[0];
            $l->copias = count($copiasMov);
            $ref = ltrim(preg_replace('/\D/', '', (string) $l->referencia) ?? '', '0');
            $porRef[$ref][] = $l;
            if (strlen($ref) >= 6) {
                $porCola[substr($ref, -6)][] = $l;
            }
            $cuentas[$l->banco . ' · ' . $l->titular] = ($cuentas[$l->banco . ' · ' . $l->titular] ?? 0) + 1;
        }

        return [$porRef, $copias, $cuentas, $porCola];
    }

    /**
     * @param  array<string, list<object>>  $porRef
     * @param  array<string, list<object>>  $porCola
     * @param  array<int, true>  $usadas
     * @return array<string, mixed>|null
     */
    private function regla(string $regla, array $p, array $porRef, array $porCola, array &$usadas): ?array
    {
        $cerca = fn (array $ls) => array_values(array_filter($ls, fn ($l) => abs((strtotime($l->fecha) - strtotime($p['fecha'])) / 86400) <= self::DIAS));
        $libres = fn (array $ls) => array_values(array_filter($ls, fn ($l) => $this->libre($l) && ! isset($usadas[$l->id])));
        $igual = fn (object $l, float $monto) => $monto > 0 && abs(abs((float) $l->monto) - $monto) <= self::TOLERANCIA;
        $tomar = function (array $lineas, ?string $nota) use (&$usadas) {
            foreach ($lineas as $l) {
                $usadas[$l->id] = true;
            }

            return ['estado' => 'conciliar', 'lineas' => $lineas, 'linea' => $lineas[0], 'nota' => $nota];
        };
        $conRef = $cerca($porRef[$p['ref']] ?? []);
        $ajuste = 'sin las ' . $p['ajustes'] . ' filas de ajuste de Cashea ($0,0033)';

        switch ($regla) {
            case 'exacto':
            case 'sin_ajuste':
                $monto = $regla === 'exacto' ? $p['bs'] : ($p['ajustes'] > 0 ? $p['bs_sin_ajuste'] : 0);
                $match = array_values(array_filter($conRef, fn ($l) => $igual($l, $monto)));
                if ($match === []) {
                    return null;
                }
                $libre = $libres($match);
                if ($libre) {
                    return $tomar([$libre[0]], $regla === 'exacto' ? null : $ajuste);
                }
                // Conciliado en una carga anterior; si solo lo tomó otro pago de este mismo archivo, se sigue buscando.
                $anterior = array_values(array_filter($match, fn ($l) => ! $this->libre($l)));

                return $anterior ? ['estado' => 'ya_conciliado', 'lineas' => [], 'linea' => $anterior[0], 'nota' => 'El abono ya está conciliado'] : null;

            case 'partido':
                // El cliente hizo varios pagos móviles con la misma referencia y Cashea los registró juntos.
                $libre = $libres($conRef);
                if (count($libre) < 2) {
                    return null;
                }
                $suma = array_sum(array_map(fn ($l) => abs((float) $l->monto), $libre));
                foreach ([[$p['bs'], null], [$p['ajustes'] > 0 ? $p['bs_sin_ajuste'] : 0, $ajuste]] as [$monto, $nota]) {
                    if ($monto > 0 && abs($suma - $monto) <= self::TOLERANCIA) {
                        return $tomar($libre, trim('pagado en ' . count($libre) . ' pagos móviles con la misma referencia ' . ($nota ? '· ' . $nota : '')));
                    }
                }

                return null;

            case 'diferencia':
                // Misma referencia y día: Cashea calcula USD × tasa y el cliente pagó un poco más o menos.
                $libre = $libres($conRef);
                if ($libre === []) {
                    return null;
                }
                usort($libre, fn ($a, $b) => abs(abs((float) $a->monto) - $p['bs']) <=> abs(abs((float) $b->monto) - $p['bs']));
                $dif = $p['bs'] - abs((float) $libre[0]->monto);
                if (abs($dif) > min(self::DIFERENCIA_MAX_BS, $p['bs'] * self::DIFERENCIA_MAX_PCT)) {
                    return null;
                }

                return $tomar([$libre[0]], sprintf('misma referencia, diferencia de Bs %s con Cashea', number_format($dif, 2, ',', '.')));

            case 'cola':
                // Transferencias (no pago móvil): el banco trae una referencia larga y Cashea guarda otra con la misma cola.
                if ($conRef !== [] || strlen($p['ref']) < 6) {
                    return null;
                }
                $match = $libres(array_filter($cerca($porCola[substr($p['ref'], -6)] ?? []),
                    fn ($l) => $igual($l, $p['bs']) || ($p['ajustes'] > 0 && $igual($l, $p['bs_sin_ajuste']))));

                return $match ? $tomar([$match[0]], 'por los últimos 6 dígitos de la referencia (banco: ' . $match[0]->referencia . ')') : null;
        }

        return null;
    }

    /** @param  list<object>  $candidatas  abonos con la misma referencia */
    private function sinPareja(array $p, array $candidatas): array
    {
        $cerca = array_values(array_filter($candidatas, fn ($l) => abs((strtotime($l->fecha) - strtotime($p['fecha'])) / 86400) <= self::DIAS));
        if ($cerca === []) {
            return ['estado' => 'no_encontrado', 'lineas' => [], 'linea' => null, 'nota' => $candidatas ? 'La referencia está en el banco en otra fecha' : null];
        }
        $l = collect($cerca)->sortBy(fn ($l) => abs(abs((float) $l->monto) - $p['bs']))->first();

        return ['estado' => 'revisar', 'lineas' => [], 'linea' => $l, 'nota' => sprintf('Misma referencia, el banco tiene Bs %s (diferencia Bs %s)%s',
            number_format(abs((float) $l->monto), 2, ',', '.'), number_format($p['bs'] - abs((float) $l->monto), 2, ',', '.'),
            $this->libre($l) ? '' : ' y ese abono ya está conciliado')];
    }

    private function libre(object $l): bool
    {
        return $l->estado === 'pendiente' && ! $l->flujo_caja_id && ! $l->tesoreria_ingreso_id;
    }
}
