<?php

namespace App\Services\ServicioTecnico;

use App\Models\User;
use App\Services\Nomina\EmployeeSalesService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StVentasFacturaService
{
    public function __construct(
        private readonly EmployeeSalesService $sales,
    ) {}

    /**
     * @return list<array{nombre:string,cantidad:int,total:float}>
     */
    public function resumenPorTrabajador(User $user, ?string $desde, ?string $hasta, ?string $sede = null): array
    {
        if (! $desde || ! $hasta || ! Schema::hasTable('ventas_detalle')) {
            return [];
        }

        $query = $this->lineasSt($user, $sede);
        if ($query === null) {
            return [];
        }

        $query->whereBetween('vd.fecha', [$desde, $hasta])
            ->selectRaw("COALESCE(MAX(vd.vendedor), 'Sin vendedor') as nombre")
            ->selectRaw('COUNT(DISTINCT vd.sede || \'-\' || vd.tipo_documento || \'-\' || vd.numero_documento || \'-\' || CAST(vd.fecha AS TEXT)) as cantidad')
            ->selectRaw($this->sumNetoSql().' as total')
            ->groupBy(DB::raw('UPPER(TRIM(vd.vendedor))'))
            ->orderByDesc('total');

        return $query->get()->map(fn ($row) => [
            'nombre' => (string) $row->nombre,
            'cantidad' => (int) $row->cantidad,
            'total' => round((float) $row->total, 2),
        ])->all();
    }

    public function documentos(User $user, ?string $desde, ?string $hasta, ?string $sede, ?string $q = null): LengthAwarePaginator
    {
        $vacio = new Paginator([], 0, 30, 1, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);

        if (! Schema::hasTable('ventas_detalle')) {
            return $vacio;
        }

        $query = $this->lineasSt($user, $sede);
        if ($query === null) {
            return $vacio;
        }

        if ($desde) {
            $query->whereDate('vd.fecha', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('vd.fecha', '<=', $hasta);
        }

        if ($q = trim((string) $q)) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('vd.cliente', $like, '%'.$q.'%')
                    ->orWhere('vd.nombre_producto', $like, '%'.$q.'%')
                    ->orWhere('vd.numero_documento', $like, '%'.$q.'%');
            });
        }

        return $query
            ->select([
                'vd.sede',
                'vd.tipo_documento',
                'vd.numero_documento',
                'vd.fecha',
                'vd.vendedor',
            ])
            ->selectRaw('MAX(vd.cliente) as cliente')
            ->selectRaw("MAX(vd.nombre_producto) as descripcion")
            ->selectRaw($this->sumNetoSql().' as total')
            ->groupBy('vd.sede', 'vd.tipo_documento', 'vd.numero_documento', 'vd.fecha', 'vd.vendedor')
            ->orderByDesc('vd.fecha')
            ->orderByDesc('vd.numero_documento')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * @return \Illuminate\Database\Query\Builder|null
     */
    private function lineasSt(User $user, ?string $sede)
    {
        $query = DB::table('ventas_detalle as vd');

        if (Schema::hasTable('productos') && Schema::hasColumn('ventas_detalle', 'producto_id')) {
            $query->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id');
        }

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('vd.anulado', false);
        }

        $this->aplicarFiltroServicioTecnico($query);

        if ($sede) {
            $query->whereRaw('UPPER(TRIM(vd.sede)) = ?', [strtoupper($sede)]);
        }

        if ($user->veSoloSusFacturasTaller()) {
            $empleado = $user->empleadoServicioTecnico();
            if (! $empleado) {
                return null;
            }
            $claves = $this->sales->claves($empleado);
            if ($claves === []) {
                return null;
            }
            $placeholders = implode(',', array_fill(0, count($claves), '?'));
            $query->whereRaw('UPPER(TRIM(vd.vendedor)) IN ('.$placeholders.')', $claves);
        }

        return $query;
    }

    private function aplicarFiltroServicioTecnico($query): void
    {
        $query->where(function ($inner) {
            $inner->whereRaw("UPPER(COALESCE(vd.nombre_producto, '')) LIKE ?", ['%SERVICIO TECNICO%'])
                ->orWhereRaw("UPPER(COALESCE(vd.codigo_producto, '')) LIKE ?", ['%SERVICIO TECNICO%']);

            if (Schema::hasTable('productos')) {
                $inner->orWhereRaw("UPPER(COALESCE(p.categoria, '')) LIKE ?", ['%SERVICIO TECNICO%'])
                    ->orWhereRaw("UPPER(COALESCE(p.subcategoria, '')) LIKE ?", ['%SERVICIO TECNICO%']);
            }
        });
    }

    private function sumNetoSql(): string
    {
        return "COALESCE(SUM(CASE WHEN UPPER(TRIM(vd.tipo_documento)) = 'DEV' "
            .'THEN -ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta)) '
            .'ELSE vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta) END), 0)';
    }
}
