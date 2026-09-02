<?php

namespace App\Services\ServicioTecnico;

use App\Models\StFactura;
use App\Models\StOrden;
use App\Models\StReparacion;
use App\Models\StRepuesto;
use App\Models\User;
use Illuminate\Support\Carbon;

class StDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metricas(User $user, ?string $sede = null, ?string $desde = null, ?string $hasta = null): array
    {
        $sedeFiltro = $user->scopesServicioToOwnSede()
            ? strtoupper((string) $user->sede)
            : ($sede ? strtoupper($sede) : null);

        $ordenesQuery = StOrden::query()->visiblePara($user);
        $reparacionesQuery = StReparacion::query()->visiblePara($user);
        $facturasQuery = StFactura::query()->visiblePara($user);
        $repuestosQuery = StRepuesto::query()->visiblePara($user)->activos();

        if ($sedeFiltro) {
            $ordenesQuery->where('sede', $sedeFiltro);
            $reparacionesQuery->where('sede', $sedeFiltro);
            $facturasQuery->where('sede', $sedeFiltro);
            $repuestosQuery->where('sede', $sedeFiltro);
        }

        $this->aplicarRango($ordenesQuery, $desde, $hasta);
        $this->aplicarRango($reparacionesQuery, $desde, $hasta);
        $this->aplicarRango($facturasQuery, $desde, $hasta, 'fecha');

        $ordenes = (clone $ordenesQuery)->get();
        $facturas = (clone $facturasQuery)->get();

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

        $actividad = collect()
            ->merge($ordenes->map(fn (StOrden $o) => [
                'tipo' => 'orden',
                'fecha' => $o->created_at,
                'titulo' => $o->codigo().' · '.$o->cliente_nombre,
                'estado' => $o->etiquetaEstado(),
                'url' => route('servicio.ordenes.show', $o),
            ]))
            ->merge((clone $reparacionesQuery)->orderByDesc('created_at')->limit(15)->get()->map(fn (StReparacion $r) => [
                'tipo' => 'garantía',
                'fecha' => $r->created_at,
                'titulo' => $r->producto.' · '.($r->cliente_nombre ?: 'Sin cliente'),
                'estado' => $r->etiquetaEstado(),
                'url' => route('servicio.reparaciones.show', $r),
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
            'reparaciones' => (clone $reparacionesQuery)->count(),
            'ingresos_cobrados' => $ingresosOrdenes + $ingresosFacturas,
            'por_cobrar' => $porCobrarOrdenes + $porCobrarFacturas,
            'stock_bajo' => $stockBajo,
            'por_estado' => $porEstado,
            'actividad' => $actividad,
            'sede_filtro' => $sedeFiltro,
        ];
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
