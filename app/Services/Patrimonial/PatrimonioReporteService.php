<?php

namespace App\Services\Patrimonial;

use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PatrimonioReporteService
{
    public function __construct(
        private readonly ReservaComisionSync $comisionSync,
    ) {}

    /**
     * @return array{
     *     filas: Collection<int, array<string, mixed>>,
     *     totales: array{ingresos:float,gastos:float,comisiones:float,balance:float},
     *     ingresosPorPropiedad: list<array{nombre:string,codigo:string,monto:float}>,
     *     gastosPorCategoria: list<array{categoria:string,monto:float}>,
     *     comisionesPorCategoria: list<array{categoria:string,monto:float}>
     * }
     */
    public function mensual(int $mes, int $anio, bool $conTransacciones = false): array
    {
        $this->comisionSync->asegurarDelMes($mes, $anio);

        $propiedades = Propiedad::query()
            ->with(['transacciones' => function ($q) use ($mes, $anio) {
                $q->where('mes', $mes)->where('anio', $anio)->orderBy('fecha');
            }])
            ->orderBy('nombre')
            ->get();
        $filas = $propiedades->map(function (Propiedad $p) use ($conTransacciones) {
            $txs = $p->transacciones
                ->reject(fn (PatTransaccion $tx) => $this->esRemodelacion($tx))
                ->values();
            $desglose = $this->desgloseDe($txs);

            $fila = array_merge([
                'propiedad' => $p->nombre,
                'tipo' => $p->tipo,
                'codigo' => $p->codigo,
                'valor_inversion' => $p->valor_inversion,
            ], $desglose);

            if ($conTransacciones) {
                $fila['transacciones'] = $this->anotarNeto($txs);
                $fila['totalesTx'] = $desglose;
            }

            return $fila;
        });

        $conMovimiento = $filas->filter(fn (array $row) => $this->tieneMovimiento($row))->values();

        return [
            'filas' => $filas,
            'totales' => $this->totalesDe($filas),
            'ingresosPorPropiedad' => $conMovimiento
                ->map(fn (array $row) => [
                    'nombre' => $row['propiedad'],
                    'codigo' => $row['codigo'],
                    'monto' => round((float) $row['ingresos'], 2),
                ])
                ->all(),
            'gastosPorCategoria' => $this->sumarCategorias($conMovimiento, 'gastosPorCategoria'),
            'comisionesPorCategoria' => $this->sumarCategorias($conMovimiento, 'comisionesPorCategoria'),
        ];
    }

    /**
     * Reporte acumulado hasta el cierre del mes indicado.
     *
     * @return array{
     *     filas: Collection<int, array{
     *         propiedad:Propiedad,valor_anterior:float,inversion_remodelaciones:float,
     *         valor_aproximado:float,remodelaciones:Collection<int,PatTransaccion>
     *     }>,
     *     totales: array{valor_anterior:float,inversion_remodelaciones:float,valor_aproximado:float},
     *     fecha_corte: Carbon
     * }
     */
    public function incrementoValor(int $mes, int $anio): array
    {
        $fechaCorte = Carbon::create($anio, $mes, 1)->endOfMonth();

        $filas = Propiedad::query()
            ->with(['transacciones' => function ($query) use ($fechaCorte) {
                $query
                    ->where('tipo', 'gasto')
                    ->whereDate('fecha', '<=', $fechaCorte->toDateString())
                    ->orderBy('fecha')
                    ->orderBy('id');
            }])
            ->orderBy('nombre')
            ->get()
            ->map(function (Propiedad $propiedad) {
                $remodelaciones = $propiedad->transacciones
                    ->filter(fn (PatTransaccion $tx) => $this->esRemodelacion($tx))
                    ->values();
                $valorAnterior = round((float) ($propiedad->valor_inversion ?? 0), 2);
                $invertido = round((float) $remodelaciones->sum('monto'), 2);

                return [
                    'propiedad' => $propiedad,
                    'valor_anterior' => $valorAnterior,
                    'inversion_remodelaciones' => $invertido,
                    'valor_aproximado' => round($valorAnterior + $invertido, 2),
                    'remodelaciones' => $remodelaciones,
                ];
            })
            ->filter(fn (array $fila) => $fila['remodelaciones']->isNotEmpty())
            ->values();

        return [
            'filas' => $filas,
            'totales' => [
                'valor_anterior' => round((float) $filas->sum('valor_anterior'), 2),
                'inversion_remodelaciones' => round((float) $filas->sum('inversion_remodelaciones'), 2),
                'valor_aproximado' => round((float) $filas->sum('valor_aproximado'), 2),
            ],
            'fecha_corte' => $fechaCorte,
        ];
    }

    /**
     * @return array{
     *     mesResumen: array<string, mixed>,
     *     historial: Collection<int, array<string, mixed>>,
     *     totales: array{ingresos:float,gastos:float,comisiones:float,balance:float},
     *     alquilerActivo: mixed
     * }
     */
    public function propiedad(Propiedad $propiedad, int $mes, int $anio, int $anioInicio, int $anioFin): array
    {
        $txsMes = $propiedad->transacciones()
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->orderBy('fecha')
            ->get();

        $historial = collect();
        for ($y = $anioInicio; $y <= $anioFin; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                if ($y === now()->year && $m > now()->month) {
                    break;
                }
                $balance = $propiedad->balanceMes($m, $y);
                if ($this->tieneMovimiento($balance)) {
                    $historial->push(array_merge(['mes' => $m, 'anio' => $y], $balance));
                }
            }
        }

        return [
            'mesResumen' => $this->desgloseDe($txsMes),
            'historial' => $historial,
            'totales' => $this->totalesDe($historial),
            'alquilerActivo' => $propiedad->alquilerActivo(),
        ];
    }

    /**
     * @param  Collection<int, PatTransaccion>  $txs
     * @return array{
     *     ingresos:float,gastos:float,comisiones:float,balance:float,
     *     ingresosPorCategoria: list<array{categoria:string,monto:float}>,
     *     gastosPorCategoria: list<array{categoria:string,monto:float}>,
     *     comisionesPorCategoria: list<array{categoria:string,monto:float}>
     * }
     */
    public function desgloseDe(Collection $txs): array
    {
        $ingresos = round((float) $txs->where('tipo', 'ingreso')->sum('monto'), 2);
        $gastos = round((float) $txs->where('tipo', 'gasto')->sum('monto'), 2);
        $comisiones = round((float) $txs->where('tipo', 'comision')->sum('monto'), 2);

        return [
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'comisiones' => $comisiones,
            'balance' => round($ingresos - $gastos - $comisiones, 2),
            'ingresosPorCategoria' => $this->agruparPorCategoria($txs, 'ingreso'),
            'gastosPorCategoria' => $this->agruparPorCategoria($txs, 'gasto'),
            'comisionesPorCategoria' => $this->agruparPorCategoria($txs, 'comision'),
        ];
    }

    /**
     * @param  Collection<int, PatTransaccion>  $txs
     * @return Collection<int, PatTransaccion>
     */
    public function anotarNeto(Collection $txs): Collection
    {
        $comisionPorReserva = $txs
            ->where('tipo', 'comision')
            ->groupBy(fn (PatTransaccion $tx) => (string) ($tx->reserva_id ?: ''))
            ->map(fn ($grupo) => round((float) $grupo->sum('monto'), 2));

        $comisionPorCuota = $txs
            ->where('tipo', 'comision')
            ->groupBy(fn (PatTransaccion $tx) => (string) ($tx->alquiler_pago_id ?: ''))
            ->map(fn ($grupo) => round((float) $grupo->sum('monto'), 2));

        $acumulado = 0.0;

        return $txs->values()->map(function (PatTransaccion $tx) use (&$acumulado, $comisionPorReserva, $comisionPorCuota) {
            $signo = $tx->tipo === 'ingreso' ? 1 : -1;
            $efecto = round($signo * (float) $tx->monto, 2);
            $acumulado = round($acumulado + $efecto, 2);
            $tx->setAttribute('efecto', $efecto);
            $tx->setAttribute('neto_acumulado', $acumulado);

            $netoReserva = null;
            if ($tx->tipo === 'ingreso' && $tx->reserva_id) {
                $netoReserva = round((float) $tx->monto - (float) ($comisionPorReserva[(string) $tx->reserva_id] ?? 0), 2);
            } elseif ($tx->tipo === 'ingreso' && $tx->alquiler_pago_id) {
                $netoReserva = round((float) $tx->monto - (float) ($comisionPorCuota[(string) $tx->alquiler_pago_id] ?? 0), 2);
            }
            $tx->setAttribute('neto_reserva', $netoReserva);

            return $tx;
        });
    }

    /**
     * @param  Collection<int, mixed>  $filas
     * @return array{ingresos:float,gastos:float,comisiones:float,balance:float}
     */
    public function totalesDe($filas): array
    {
        $ingresos = round((float) $filas->sum('ingresos'), 2);
        $gastos = round((float) $filas->sum('gastos'), 2);
        $comisiones = round((float) $filas->sum('comisiones'), 2);

        return [
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'comisiones' => $comisiones,
            'balance' => round($ingresos - $gastos - $comisiones, 2),
        ];
    }

    /**
     * @param  array{ingresos:float,gastos:float,comisiones:float}  $row
     */
    public function tieneMovimiento(array $row): bool
    {
        return ($row['ingresos'] ?? 0) > 0
            || ($row['gastos'] ?? 0) > 0
            || ($row['comisiones'] ?? 0) > 0;
    }

    /**
     * @param  Collection<int, PatTransaccion>  $txs
     * @return list<array{categoria:string,monto:float}>
     */
    private function agruparPorCategoria(Collection $txs, string $tipo): array
    {
        return $txs->where('tipo', $tipo)
            ->groupBy(fn (PatTransaccion $tx) => trim((string) $tx->categoria) !== '' ? $tx->categoria : 'Sin categoría')
            ->map(fn ($grupo, $categoria) => [
                'categoria' => (string) $categoria,
                'monto' => round((float) $grupo->sum('monto'), 2),
            ])
            ->filter(fn (array $row) => $row['monto'] > 0)
            ->sortByDesc('monto')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return list<array{categoria:string,monto:float}>
     */
    private function sumarCategorias(Collection $filas, string $clave): array
    {
        $acumulado = [];
        foreach ($filas as $fila) {
            foreach ($fila[$clave] ?? [] as $linea) {
                $cat = $linea['categoria'];
                $acumulado[$cat] = round(($acumulado[$cat] ?? 0) + (float) $linea['monto'], 2);
            }
        }

        arsort($acumulado);

        return collect($acumulado)
            ->map(fn ($monto, $categoria) => ['categoria' => (string) $categoria, 'monto' => (float) $monto])
            ->values()
            ->all();
    }

    private function esRemodelacion(PatTransaccion $transaccion): bool
    {
        return Str::lower(Str::ascii(trim((string) $transaccion->categoria))) === 'remodelacion';
    }
}
