<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionDivisaLinea;
use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte de pagos manuales de Cloud (lo que el cajero cargó a mano: Banco Receptor, Banco emisor, Ref, Monto Bs / USD).
 * El reporte no dice a qué cuenta llegó el dinero: cada pago se busca en los abonos de TODAS las cuentas
 * (en bolívares si el receptor es "BANCO NACIONAL"; en divisas si es Binance, Zelle, Mercantil Panamá…).
 */
class PagosManualesVerificador
{
    /** Días que puede tardar el abono en aparecer en el extracto después del pago (fin de semana, feriado). */
    private const DIAS_DESPUES = 5;

    private const DIAS_ANTES = 2;

    public function __construct(
        private ExtractoParser $parser,
        private BankReconciliationMatcher $matcher,
        private ConciliacionDivisasService $divisas,
    ) {}

    /**
     * @return list<array{nro: string, receptor: string, emisor: string, caja: string, cajero: string, ref: string, moneda: string, monto: float, fecha: string, registro: ?string, avisos: list<string>}>
     */
    public function leer(string $path, string $ext): array
    {
        $norm = fn ($v) => mb_strtoupper(trim(strtr((string) $v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Ó' => 'O'])), 'UTF-8');
        $cols = null;
        $out = [];
        foreach ($this->parser->filasCrudas($path, $ext) as $fila) {
            $fila = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y H:i:s') : trim((string) $v), array_values((array) $fila));
            if ($cols === null) {
                $celdas = array_map($norm, $fila);
                $c = fn (string $n) => ($i = array_search($n, $celdas, true)) === false ? null : (int) $i;
                $cols = ['nro' => $c('NRO'), 'receptor' => $c('BANCO RECEPTOR'), 'emisor' => $c('BANCO'), 'caja' => $c('TERMINAL'), 'cajero' => $c('CAJERO'),
                    'ref' => $c('REF'), 'bs' => $c('MONTO BS.') ?? $c('MONTO BS'), 'usd' => $c('MONTO USD / USDT') ?? $c('MONTO USD'),
                    'fecha_op' => $c('FECHA OPERACION'), 'fecha' => $c('FECHA')];
                if (in_array(null, [$cols['nro'], $cols['receptor'], $cols['ref'], $cols['bs'], $cols['fecha']], true)) {
                    $cols = null;
                }

                continue;
            }
            $v = fn (string $k) => $cols[$k] === null ? '' : ($fila[$cols[$k]] ?? '');
            if (! ctype_digit($v('nro'))) {
                continue;
            }
            $receptor = $v('receptor');
            $bs = (float) ($this->parser->normalizarMonto($v('bs')) ?? 0);
            $usd = (float) ($this->parser->normalizarMonto($v('usd')) ?? 0);
            $registro = $this->fecha($v('fecha'));
            $operacion = $this->fecha($v('fecha_op'));
            $esNacional = $this->esBolivares($receptor);
            $avisos = [];
            if ($esNacional) {
                $moneda = 'bs';
                $monto = $bs;
            } else {
                $moneda = 'usd';
                $monto = $usd > 0 ? $usd : $bs;
                if ($usd <= 0 && $bs > 0) {
                    $avisos[] = 'El monto se cargó en «Monto Bs.» aunque el pago es en divisas';
                }
            }
            $ref = $v('ref');
            if (preg_match('/^\d(\.\d+)?E\+\d+$/i', $ref)) {
                $avisos[] = 'Referencia en notación científica: Excel perdió los últimos dígitos';
            }
            if ($operacion && $registro && abs(strtotime($operacion) - strtotime($registro)) > 10 * 86400) {
                $avisos[] = 'La fecha de operación (' . date('d/m/Y', strtotime($operacion)) . ') está lejos de la del registro';
            }
            $out[] = [
                'nro' => $v('nro'), 'receptor' => $receptor, 'emisor' => $v('emisor'), 'caja' => $v('caja'), 'cajero' => $v('cajero'),
                'ref' => $ref, 'moneda' => $moneda, 'monto' => round($monto, 2),
                'fecha' => $operacion && (! $registro || abs(strtotime($operacion) - strtotime($registro)) <= 10 * 86400) ? $operacion : ($registro ?? $operacion ?? ''),
                'registro' => $registro, 'avisos' => $avisos,
            ];
        }

        return $out;
    }

    /**
     * Cada pago queda en uno de: conciliar (abono pendiente con su ref. y monto), ya_conciliado (el abono existe
     * pero ya está ligado a otro registro), revisar (candidato dudoso) o no_encontrado.
     *
     * @param  list<array<string, mixed>>  $pagos
     * @return array{items: list<array<string, mixed>>, desde: ?string, hasta: ?string, cuentas_bs: int, cuentas_usd: int, abonos_bs: int, abonos_usd: int}
     */
    public function verificar(array $pagos): array
    {
        $fechas = array_filter(array_column($pagos, 'fecha'));
        if ($fechas === []) {
            return ['items' => [], 'desde' => null, 'hasta' => null, 'cuentas_bs' => 0, 'cuentas_usd' => 0, 'abonos_bs' => 0, 'abonos_usd' => 0];
        }
        $desde = date('Y-m-d', strtotime(min($fechas) . ' -' . self::DIAS_ANTES . ' days'));
        $hasta = date('Y-m-d', strtotime(max($fechas) . ' +' . self::DIAS_DESPUES . ' days'));

        $bs = ConciliacionLinea::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(fn ($q) => $q->where('tipo', 'abono')->orWhere(fn ($w) => $w->whereNull('tipo')->where('monto', '>', 0)))
            ->get(['id', 'banco', 'titular', 'fecha', 'referencia', 'descripcion', 'monto', 'tipo', 'estado', 'flujo_caja_id', 'tesoreria_ingreso_id']);
        $usd = Schema::hasTable('conciliacion_divisas_lineas')
            ? ConciliacionDivisaLinea::query()->whereBetween('fecha', [$desde, $hasta])->where('monto', '>', 0)->get()
            : collect();
        $conPago = Schema::hasTable('conciliacion_divisas_pagos')
            ? DB::table('conciliacion_divisas_pagos')->whereNotNull('linea_id')->pluck('linea_id')->flip()
            : collect();

        $indice = ['bs' => $this->porMonto($bs), 'usd' => $this->porMonto($usd)];
        $usadas = [];
        $items = [];
        // Primero los que tienen la referencia completa: así un pago dudoso no se queda con el abono de otro.
        $orden = $pagos;
        usort($orden, fn ($a, $b) => strlen(preg_replace('/\D/', '', $b['ref'])) <=> strlen(preg_replace('/\D/', '', $a['ref'])));
        foreach ($orden as $p) {
            $items[$p['nro']] = $this->clasificar($p, $indice[$p['moneda']], $usadas, $conPago);
        }
        $items = array_values(array_map(fn ($p) => $items[$p['nro']], $pagos));

        return [
            'items' => $items, 'desde' => $desde, 'hasta' => $hasta,
            'cuentas_bs' => $bs->map(fn ($l) => $l->banco . '|' . $l->titular)->unique()->count(),
            'cuentas_usd' => $usd->map(fn ($l) => $l->banco . '|' . $l->titular)->unique()->count(),
            'abonos_bs' => $bs->count(), 'abonos_usd' => $usd->count(),
        ];
    }

    /**
     * @param  array<int, list<object>>  $porMonto
     * @param  array<string, true>  $usadas
     */
    private function clasificar(array $p, array $porMonto, array &$usadas, Collection $conPago): array
    {
        $cent = (int) round($p['monto'] * 100);
        $refOk = fn ($l) => $this->refCoincide($l, $p['ref']);
        $dias = fn ($l) => (int) round((strtotime($l->fecha->format('Y-m-d')) - strtotime($p['fecha'])) / 86400);
        $enVentana = fn ($l, int $antes = self::DIAS_ANTES, int $despues = self::DIAS_DESPUES) => $dias($l) >= -$antes && $dias($l) <= $despues;
        $cerca = fn (array $ls) => collect($ls)->sortBy(fn ($l) => abs($dias($l)))->values()->all();

        $mismoMonto = $cent > 0 ? ($porMonto[$cent] ?? []) : [];
        $conRef = array_values(array_filter($mismoMonto, fn ($l) => $refOk($l) && $enVentana($l, 7, 10)));
        // Extracto cargado dos veces: la copia pendiente de un movimiento ya conciliado no se vuelve a usar.
        $copia = fn ($l) => $l->banco . '|' . $l->titular . '|' . $l->fecha->format('Y-m-d') . '|' . $l->referencia . '|' . $l->descripcion;
        $tomadas = array_flip(array_map($copia, array_filter($conRef, fn ($l) => ! $this->pendiente($l, $conPago))));
        $libre = fn ($l) => ! isset($usadas[$this->clave($l)]) && $this->pendiente($l, $conPago) && ! isset($tomadas[$copia($l)]);

        $exacta = $cerca(array_filter($conRef, $libre))[0] ?? null;
        if ($exacta) {
            $usadas[$this->clave($exacta)] = true;

            return $p + ['estado' => 'conciliar', 'linea' => $exacta, 'nota' => null];
        }
        $yaLigada = $cerca(array_filter($conRef, fn ($l) => ! $this->pendiente($l, $conPago)))[0] ?? null;
        if ($yaLigada) {
            return $p + ['estado' => 'ya_conciliado', 'linea' => $yaLigada, 'nota' => 'El abono ya está conciliado con otro registro'];
        }

        // Monto cargado ×100 en Cloud (21405056 por 214.050,56).
        foreach ([intdiv($cent, 100) => $cent % 100 === 0, $cent * 100 => true] as $otro => $ok) {
            if (! $ok) {
                continue;
            }
            $x = $cerca(array_filter($porMonto[$otro] ?? [], fn ($l) => $refOk($l) && $enVentana($l, 7, 10)))[0] ?? null;
            if ($x) {
                return $p + ['estado' => 'revisar', 'linea' => $x, 'nota' => 'Misma referencia; el monto en Cloud está ×100 o ÷100'];
            }
        }

        $mismoDia = $cerca(array_filter($mismoMonto, fn ($l) => $enVentana($l, 1, 3)))[0] ?? null;
        if ($mismoDia) {
            return $p + ['estado' => 'revisar', 'linea' => $mismoDia, 'nota' => $this->pendiente($mismoDia, $conPago)
                ? 'Mismo monto y fecha; la referencia no coincide (¿se tipeó mal?)'
                : 'Mismo monto y fecha, pero ese abono ya está conciliado con otro registro'];
        }

        return $p + ['estado' => 'no_encontrado', 'linea' => null, 'nota' => null];
    }

    /**
     * Registra los encontrados: en bolívares, como ingreso de Tesorería ligado al abono; en divisas, como cobro
     * de caja cruzado con el abono del extracto en divisas.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function aplicar(array $items, string $archivo, ?int $userId): array
    {
        $hechos = ['bs' => 0, 'usd' => 0, 'ocupados' => 0];
        foreach ($items as $it) {
            if ($it['estado'] !== 'conciliar' || ! $it['linea']) {
                continue;
            }
            $ok = $it['moneda'] === 'bs' ? $this->aplicarBs($it, $userId) : $this->aplicarUsd($it, $archivo, $userId);
            $ok ? $hechos[$it['moneda']]++ : $hechos['ocupados']++;
        }

        return $hechos;
    }

    private function aplicarBs(array $it, ?int $userId): bool
    {
        try {
            return DB::transaction(function () use ($it, $userId) {
                /** @var ConciliacionLinea $l */
                $l = $it['linea'];
                $ingreso = TesoreriaIngreso::create([
                    'tipo' => 'banco',
                    'banco' => $l->banco,
                    'titular' => $l->titular,
                    'fecha' => $l->fecha->format('Y-m-d'),
                    'monto' => abs((float) $l->monto),
                    'lote_referencia' => $l->referencia ?: $it['ref'],
                    'descripcion' => mb_substr(sprintf('Pago manual Cloud #%s · ref %s · %s · %s', $it['nro'], $it['ref'], $it['caja'], $it['cajero']), 0, 255),
                    'user_id' => $userId,
                    'es_conciliado' => true,
                ]);
                $n = DB::table('conciliacion_lineas')->where('id', $l->id)->where('estado', 'pendiente')
                    ->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id')
                    ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
                    ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $ingreso->id, 'updated_at' => now()]);
                if ($n !== 1) {
                    throw new VinculoOcupadoException;
                }

                return true;
            });
        } catch (VinculoOcupadoException) {
            return false;
        }
    }

    private function aplicarUsd(array $it, string $archivo, ?int $userId): bool
    {
        /** @var ConciliacionDivisaLinea $l */
        $l = $it['linea'];
        $base = implode('|', ['MANUAL', mb_strtoupper($l->banco), mb_strtoupper($l->titular), $it['nro'], $it['registro'], $it['ref'], number_format($it['monto'], 2, '.', '')]);
        $pagoId = DB::table('conciliacion_divisas_pagos')->insertGetId([
            'banco' => $l->banco, 'titular' => $l->titular, 'nro' => $it['nro'],
            'fecha' => $it['fecha'], 'fecha_hora' => null, 'monto' => $it['monto'],
            'terminal' => mb_substr($it['caja'], 0, 160) ?: null, 'usuario' => mb_substr($it['cajero'], 0, 160) ?: null,
            'referencia' => mb_substr($it['ref'], 0, 160) ?: null,
            'huella' => hash('sha256', $base), 'archivo' => mb_substr($archivo, 0, 255), 'user_id' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($this->divisas->vincularPago((int) $pagoId, (int) $l->id)) {
            return true;
        }
        DB::table('conciliacion_divisas_pagos')->where('id', $pagoId)->whereNull('linea_id')->delete();

        return false;
    }

    /** Ref. del cajero dentro de la del banco (BDV y Banesco anteponen dígitos); en divisas, también nombres o códigos. */
    private function refCoincide(object $l, string $ref): bool
    {
        $dig = preg_replace('/\D/', '', $ref) ?? '';
        if (strlen(ltrim($dig, '0')) >= 4 && preg_match('/^\d+$/', trim($ref))) {
            return $this->matcher->referenciaPagoContenida($l->referencia, $dig)
                || str_ends_with(preg_replace('/\D/', '', (string) $l->referencia) ?? '', ltrim($dig, '0'))
                || ($l instanceof ConciliacionDivisaLinea && str_contains(preg_replace('/\D/', '', (string) $l->descripcion) ?? '', $dig));
        }
        if (! $l instanceof ConciliacionDivisaLinea) {
            return false;
        }
        $texto = ConciliacionDivisasService::nombreNormalizado($ref);

        return strlen($texto) >= 4 && str_contains(ConciliacionDivisasService::nombreNormalizado($l->referencia . ' ' . $l->descripcion), $texto);
    }

    private function pendiente(object $l, Collection $conPago): bool
    {
        if ($l instanceof ConciliacionDivisaLinea) {
            return $l->estado === 'pendiente' && ! isset($conPago[$l->id]) && ! $l->flujo_caja_id;
        }

        return $l->estado === 'pendiente' && ! $l->flujo_caja_id && ! $l->tesoreria_ingreso_id;
    }

    private function clave(object $l): string
    {
        return ($l instanceof ConciliacionDivisaLinea ? 'usd:' : 'bs:') . $l->id;
    }

    /** @return array<int, list<object>> */
    private function porMonto(Collection $lineas): array
    {
        $out = [];
        foreach ($lineas as $l) {
            $out[(int) round(abs((float) $l->monto) * 100)][] = $l;
        }

        return $out;
    }

    private function esBolivares(string $receptor): bool
    {
        $r = mb_strtolower(trim($receptor));

        return $r === '' || $r === '0' || str_contains($r, 'nacional') || preg_match('/^\d+$/', $r) === 1;
    }

    private function fecha(string $raw): ?string
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', trim($raw), $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return trim($raw) === '' ? null : $this->parser->normalizarFecha($raw);
    }
}
