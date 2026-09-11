<?php

namespace App\Services\ServicioTecnico;

use App\Models\StFactura;
use App\Models\StOrden;
use App\Models\StRepuesto;
use App\Models\User;
use App\Services\MetaQuincenaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StDashboardService
{
    private const GASTO_058 = '058 - SERVICIO TECNICO (GARANTIAS)';

    public function __construct(
        private readonly MetaQuincenaService $quincenas,
        private readonly StVentasFacturaService $ventasSt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metricas(User $user, ?string $sede = null, ?string $desde = null, ?string $hasta = null): array
    {
        $sedeFiltro = $user->scopesServicioToOwnSede()
            ? strtoupper((string) $user->sede)
            : ($sede ? strtoupper($sede) : null);

        $quincena = $this->quincenas->quincenaActual();
        $desdeQ = $desde ?: ($quincena['inicio'] ?? null);
        $hastaQ = $hasta ?: ($quincena['fin'] ?? null);
        if ($desdeQ) {
            $desdeQ = Carbon::parse($desdeQ)->toDateString();
        }
        if ($hastaQ) {
            $hastaQ = Carbon::parse($hastaQ)->toDateString();
        }

        $ordenesQuery = StOrden::query()->visiblePara($user);
        $facturasQuery = StFactura::query()->visiblePara($user);
        $repuestosQuery = StRepuesto::query()->visiblePara($user)->activos();

        if ($sedeFiltro) {
            $ordenesQuery->where('sede', $sedeFiltro);
            $facturasQuery->where('sede', $sedeFiltro);
            $repuestosQuery->where('sede', $sedeFiltro);
        }

        $this->aplicarRango($ordenesQuery, $desdeQ, $hastaQ, 'fecha_ingreso');
        $this->aplicarRango($facturasQuery, $desdeQ, $hastaQ, 'fecha');

        $ordenes = (clone $ordenesQuery)->get();
        $facturas = (clone $facturasQuery)->with('tecnico')->get();

        $ingresosOrdenes = $ordenes
            ->where('estado', StOrden::ESTADO_ENTREGADO)
            ->sum(fn (StOrden $o) => (float) ($o->presupuesto ?: $o->costoTotal()));

        $ingresosFacturas = $facturas
            ->where('estado_pago', 'pagado')
            ->sum(fn (StFactura $f) => (float) $f->total);

        $porCobrarOrdenes = $ordenes
            ->where('estado', StOrden::ESTADO_LISTO)
            ->sum(fn (StOrden $o) => (float) ($o->presupuesto ?: $o->costoTotal()));

        $porCobrarFacturas = $facturas
            ->where('estado_pago', 'pendiente')
            ->sum(fn (StFactura $f) => (float) $f->total);

        $stockBajo = (clone $repuestosQuery)
            ->whereColumn('stock', '<=', 'stock_min')
            ->where('stock_min', '>', 0)
            ->count();

        $porEstado = collect(StOrden::ESTADOS)->mapWithKeys(function ($label, $estado) use ($ordenes) {
            return [$estado => $ordenes->where('estado', $estado)->count()];
        });

        $porRecibirQuery = StOrden::query()
            ->visiblePara($user)
            ->where('transfer_estado', StOrden::TRANSFER_PENDIENTE)
            ->with('equipoCelular');
        if ($user->veSoloSusFacturasTaller()) {
            $porRecibirQuery->where('tecnico_id', $user->id);
        }
        if ($user->scopesServicioToOwnSede()) {
            $porRecibirQuery->where('sede', strtoupper((string) $user->sede));
        } elseif ($sedeFiltro) {
            $porRecibirQuery->where('sede', $sedeFiltro);
        }
        $porRecibir = $porRecibirQuery->orderByDesc('updated_at')->limit(10)->get();

        $facturasPorTrabajador = $this->ventasSt->resumenPorTrabajador(
            $user,
            $desdeQ,
            $hastaQ,
            $user->scopesServicioToOwnSede() ? null : $sedeFiltro,
        );
        if ($facturasPorTrabajador === []) {
            $facturasPorTrabajador = $facturas
                ->groupBy(fn (StFactura $f) => $f->tecnico_id ?: 0)
                ->map(function ($group) {
                    /** @var \Illuminate\Support\Collection<int, StFactura> $group */
                    $first = $group->first();

                    return [
                        'nombre' => $first?->tecnico?->name ?: 'Sin técnico',
                        'cantidad' => $group->count(),
                        'total' => round((float) $group->sum('total'), 2),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();
        }

        $actividad = collect()
            ->merge($ordenes->map(fn (StOrden $o) => [
                'tipo' => 'orden',
                'fecha' => $o->created_at,
                'titulo' => $o->codigo().' · '.$o->cliente_nombre,
                'estado' => $o->etiquetaEstado(),
                'url' => route('servicio.ordenes.show', $o),
            ]))
            ->merge($facturas->map(fn (StFactura $f) => [
                'tipo' => 'factura',
                'fecha' => $f->created_at,
                'titulo' => $f->codigo().' · '.$f->cliente_nombre,
                'estado' => $f->etiquetaEstadoPago(),
                'url' => route('servicio.facturas.show', $f),
            ]))
            ->sortByDesc('fecha')
            ->take(15)
            ->values();

        return [
            'total_ordenes' => $ordenes->count(),
            'pendientes' => $ordenes->where('estado', StOrden::ESTADO_PENDIENTE)->count(),
            'por_recibir_count' => $porRecibir->count(),
            'por_recibir' => $porRecibir,
            'ingresos_cobrados' => $ingresosOrdenes + $ingresosFacturas,
            'por_cobrar' => $porCobrarOrdenes + $porCobrarFacturas,
            'stock_bajo' => $stockBajo,
            'por_estado' => $porEstado,
            'actividad' => $actividad,
            'sede_filtro' => $sedeFiltro,
            'quincena' => $quincena,
            'egresos_058' => $this->egresos058Quincena($desdeQ, $hastaQ, $sedeFiltro, $user),
            'facturas_por_trabajador' => $facturasPorTrabajador,
            'rango' => [
                'desde' => $desdeQ,
                'hasta' => $hastaQ,
            ],
        ];
    }

    /**
     * @return list<array{nombre:string,monto:float,cantidad:int}>
     */
    private function egresos058Quincena(?string $desde, ?string $hasta, ?string $sede, User $user): array
    {
        if (! $desde || ! $hasta || ! Schema::hasTable('flujo_cajas')) {
            return [];
        }

        $nombreExpr = Schema::hasColumn('flujo_cajas', 'descripcion')
            ? "COALESCE(c.nombre, fc.descripcion, 'Sin asignar')"
            : "COALESCE(c.nombre, fc.motivo, fc.titular_receptor, 'Sin asignar')";

        $query = DB::table('flujo_cajas as fc')
            ->leftJoin('nomina_empleados as ne', 'ne.id', '=', 'fc.nomina_empleado_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'ne.cliente_id')
            ->where('fc.tipo_gasto', self::GASTO_058)
            ->whereBetween('fc.fecha', [$desde, $hasta])
            ->selectRaw($nombreExpr.' as nombre')
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw('COALESCE(SUM(CASE
                WHEN fc.monto_usd IS NOT NULL AND fc.monto_usd <> 0 THEN ABS(fc.monto_usd)
                WHEN fc.tasa_cambio IS NOT NULL AND fc.tasa_cambio > 0 THEN ABS(fc.monto_bs) / fc.tasa_cambio
                ELSE 0 END), 0) as monto')
            ->groupBy(DB::raw($nombreExpr))
            ->orderByDesc('monto');

        if ($user->veSoloSusFacturasTaller()) {
            $empleado = $user->empleadoServicioTecnico();
            if ($empleado) {
                $query->where('fc.nomina_empleado_id', $empleado->id);
            } else {
                return [];
            }
        } elseif ($sede && Schema::hasColumn('flujo_cajas', 'sede')) {
            $sedeNorm = strtoupper($sede);
            $query->where(function ($inner) use ($sedeNorm) {
                $inner->whereRaw('UPPER(TRIM(fc.sede)) = ?', [$sedeNorm]);
                if (Schema::hasColumn('nomina_empleados', 'sede')) {
                    $inner->orWhere(function ($blank) use ($sedeNorm) {
                        $blank->where(function ($sedeVacia) {
                            $sedeVacia->whereNull('fc.sede')->orWhereRaw("TRIM(fc.sede) = ''");
                        })->whereRaw('UPPER(TRIM(ne.sede)) = ?', [$sedeNorm]);
                    });
                }
            });
        }

        return $query->get()->map(fn ($row) => [
            'nombre' => (string) $row->nombre,
            'monto' => round((float) $row->monto, 2),
            'cantidad' => (int) $row->cantidad,
        ])->all();
    }

    private function aplicarRango($query, ?string $desde, ?string $hasta, string $column = 'created_at'): void
    {
        if ($desde) {
            $query->whereDate($column, '>=', Carbon::parse($desde)->toDateString());
        }
        if ($hasta) {
            $query->whereDate($column, '<=', Carbon::parse($hasta)->toDateString());
        }
    }
}
