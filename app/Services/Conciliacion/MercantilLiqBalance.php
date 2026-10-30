<?php

namespace App\Services\Conciliacion;

use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lotes del Excel del balance de Mercantil como liquidaciones del banco: el abono «CANCELACION RESUMEN DE VENTAS»
 * no se importa, así que cada lote del balance entra como su LIQ por el importe bruto (lo que registra Tesorería).
 * La que tiene su lote en Tesorería (mismo número y bruto) queda conciliada; la otra va a «LIQ del banco sin lote».
 * El Excel no trae fecha: se toma la del lote de Tesorería o la de las filas vecinas (el archivo va en orden de cierre).
 */
class MercantilLiqBalance
{
    public const MARCA = 'BALANCE MERCANTIL';

    public const TOLERANCIA = 0.05;

    private const DIAS_ATRAS = 120;

    public function __construct(private BankReconciliationMatcher $matcher) {}

    /**
     * @param  list<array{lote: string, bruto: float, descuento: float, retencion: float, abonado: float}>  $lotes
     * @return list<array{lote: string, bruto: float, abonado: float, comision: float, accion: string, tesoreria: ?TesoreriaIngreso, fecha: ?string}>
     *     accion: ligar (se concilia con su lote) · sin_lote (va a LIQ sin lote) · conciliado (el lote ya estaba) · cargada (ya se subió)
     */
    public function plan(array $lotes, string $titular): array
    {
        $desde = now()->subDays(self::DIAS_ATRAS)->toDateString();
        $tesoreria = TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('fecha', '>=', $desde)
            ->orderBy('fecha')->orderBy('id')
            ->get()
            ->filter(fn ($i) => $this->matcher->mismoTitular($i->titular, $titular, $i->banco, 'MERCANTIL'))
            ->values();
        $cargadas = DB::table('conciliacion_lineas')
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('descripcion', 'like', '%'.self::MARCA.'%')
            ->where('fecha', '>=', $desde)
            ->get(['titular', 'referencia', 'monto', 'fecha'])
            ->filter(fn ($l) => $this->matcher->mismoTitular($l->titular, $titular, 'MERCANTIL', 'MERCANTIL'))
            ->values();

        $usados = [];
        $usadasLiq = [];
        $filas = [];
        foreach ($lotes as $b) {
            $numero = $this->numero($b['lote']);
            $bruto = round((float) $b['bruto'], 2);
            $fila = [
                'lote' => $numero, 'bruto' => $bruto, 'abonado' => round((float) $b['abonado'], 2),
                'comision' => round($bruto - (float) $b['abonado'], 2), 'accion' => 'sin_lote', 'tesoreria' => null, 'fecha' => null,
            ];
            $mismo = fn ($n, $m) => $this->numero($n) === $numero && abs(abs((float) $m) - $bruto) <= self::TOLERANCIA;

            $clave = $cargadas->search(fn ($l, $k) => ! isset($usadasLiq[$k]) && $mismo($l->referencia, $l->monto));
            if ($clave !== false) {
                $usadasLiq[$clave] = true;
                $filas[] = ['accion' => 'cargada', 'fecha' => substr((string) $cargadas[$clave]->fecha, 0, 10)] + $fila;

                continue;
            }
            $lote = $tesoreria->first(fn ($i) => ! isset($usados[$i->id]) && ! $i->es_conciliado && $mismo($i->lote_referencia, $i->monto))
                ?? $tesoreria->first(fn ($i) => ! isset($usados[$i->id]) && $mismo($i->lote_referencia, $i->monto));
            if ($lote) {
                $usados[$lote->id] = true;
                $fila['tesoreria'] = $lote;
                $fila['fecha'] = substr((string) $lote->fecha, 0, 10);
                $fila['accion'] = $lote->es_conciliado ? 'conciliado' : 'ligar';
            }
            $filas[] = $fila;
        }

        return $this->fechasVecinas($filas);
    }

    /**
     * Crea las LIQ y concilia las que tienen su lote.
     *
     * @return array{ligadas: int, sin_lote: int}
     */
    public function aplicar(array $filas, string $titular): array
    {
        $banco = 'MERCANTIL';
        $titular = mb_strtoupper(trim($titular));
        $ligadas = 0;
        $sinLote = 0;
        DB::transaction(function () use ($filas, $banco, $titular, &$ligadas, &$sinLote) {
            $ahora = now();
            foreach ($filas as $f) {
                if (! in_array($f['accion'], ['ligar', 'sin_lote'], true)) {
                    continue;
                }
                $loteId = $f['accion'] === 'ligar' ? (int) $f['tesoreria']->id : null;
                if ($loteId && DB::table('tesoreria_ingresos')->where('id', $loteId)->where('es_conciliado', false)
                    ->update(['es_conciliado' => true, 'updated_at' => $ahora]) !== 1) {
                    $loteId = null;
                }
                DB::table('conciliacion_lineas')->insert([
                    'banco' => $banco,
                    'titular' => $titular,
                    'fecha' => $f['fecha'],
                    'descripcion' => self::descripcion($f['lote'], $f['abonado']),
                    'referencia' => $f['lote'],
                    'monto' => $f['bruto'],
                    'tipo' => 'abono',
                    'estado' => $loteId ? 'conciliado' : 'pendiente',
                    'tesoreria_ingreso_id' => $loteId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
                $loteId ? $ligadas++ : $sinLote++;
            }
        });

        return ['ligadas' => $ligadas, 'sin_lote' => $sinLote];
    }

    /**
     * LIQ del balance que quedaron sin lote: se concilian solas cuando el lote se registra en Tesorería
     * (mismo número y bruto, hasta 10 días antes o después, porque la fecha de la LIQ puede ser aproximada).
     */
    public function conciliarPendientes(string $desde, string $hasta, ?string $titular = null): int
    {
        $liqs = DB::table('conciliacion_lineas')
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('descripcion', 'like', '%'.self::MARCA.'%')
            ->where('estado', 'pendiente')
            ->whereNull('tesoreria_ingreso_id')->whereNull('flujo_caja_id')
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')->orderBy('id')
            ->get(['id', 'titular', 'fecha', 'referencia', 'monto'])
            ->filter(fn ($l) => ! $titular || $this->matcher->mismoTitular($l->titular, $titular, 'MERCANTIL', 'MERCANTIL'));
        if ($liqs->isEmpty()) {
            return 0;
        }
        $lotes = TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('es_conciliado', false)
            ->whereBetween('fecha', [$this->sumarDias($desde, -10), $this->sumarDias($hasta, 10)])
            ->orderBy('fecha')->orderBy('id')
            ->get();

        $n = 0;
        foreach ($liqs as $l) {
            $dia = strtotime(substr((string) $l->fecha, 0, 10));
            $lote = $lotes->first(fn ($i) => $this->numero($i->lote_referencia) === $this->numero($l->referencia)
                && abs(abs((float) $i->monto) - abs((float) $l->monto)) <= self::TOLERANCIA
                && abs(strtotime(substr((string) $i->fecha, 0, 10)) - $dia) <= 10 * 86400
                && $this->matcher->mismoTitular($i->titular, $l->titular, $i->banco, 'MERCANTIL'));
            if (! $lote) {
                continue;
            }
            try {
                DB::transaction(function () use ($l, $lote) {
                    $ahora = now();
                    $ok = DB::table('tesoreria_ingresos')->where('id', $lote->id)->where('es_conciliado', false)
                        ->update(['es_conciliado' => true, 'updated_at' => $ahora]) === 1
                        && DB::table('conciliacion_lineas')->where('id', $l->id)->where('estado', 'pendiente')->whereNull('tesoreria_ingreso_id')
                            ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $lote->id, 'updated_at' => $ahora]) === 1;
                    if (! $ok) {
                        throw new VinculoOcupadoException;
                    }
                });
            } catch (VinculoOcupadoException) {
                continue;
            }
            $n++;
            $lotes = $lotes->reject(fn ($i) => $i->id === $lote->id);
        }

        return $n;
    }

    public static function descripcion(string $lote, float $abonado): string
    {
        return 'LIQ LOTE '.$lote.' · '.self::MARCA.' · ABONADO '.number_format($abonado, 2, '.', '');
    }

    /** Lo que de verdad abonó el banco por ese lote (bruto − descuento), si la LIQ salió del balance. */
    public static function abonado(?string $descripcion): ?float
    {
        return preg_match('/'.self::MARCA.' · ABONADO (\d+(?:\.\d+)?)/u', (string) $descripcion, $m) === 1 ? (float) $m[1] : null;
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function fechasVecinas(array $filas): array
    {
        $n = count($filas);
        foreach ($filas as $i => $f) {
            if ($f['fecha'] !== null) {
                continue;
            }
            $fecha = null;
            for ($d = 1; $d < $n && $fecha === null; $d++) {
                $fecha = $filas[$i - $d]['fecha'] ?? $filas[$i + $d]['fecha'] ?? null;
            }
            $filas[$i]['fecha'] = $fecha ?? now()->toDateString();
            $filas[$i]['fecha_estimada'] = true;
        }

        return array_map(fn ($f) => $f + ['fecha_estimada' => false], $filas);
    }

    private function sumarDias(string $fecha, int $dias): string
    {
        return gmdate('Y-m-d', strtotime(substr($fecha, 0, 10).' 00:00:00 UTC') + $dias * 86400);
    }

    private function numero(?string $lote): string
    {
        return ltrim(preg_replace('/\D/', '', (string) $lote) ?? '', '0');
    }
}
