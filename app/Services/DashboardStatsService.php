<?php

namespace App\Services;

use App\Models\V2\Movimiento;
use App\Models\V2\Producto;
use App\Models\StockMovement;
use App\Models\Product;
use App\Services\Profiler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /**
     * TTL estándar para estadísticas operativas del Dashboard.
     * 60 segundos: los movimientos cambian con frecuencia operativa real.
     */
    private const TTL = 60;

    /**
     * Cantidad de productos activos.
     * Cambia únicamente cuando se importa un Excel de inventario.
     */
    public function productosActivos(): int
    {
        return Profiler::measure('DashboardStats::productosActivos', fn() =>
            Cache::remember('dashboard.productos_activos', self::TTL, function () {
                if (config('database.default') === 'pgsql') {
                    return Producto::query()->where('activo', true)->count();
                }

                return Product::query()->count();
            })
        );
    }

    /**
     * Estadísticas de movimientos: total, requisiciones, sincronizaciones.
     * Cambia con cada requisición registrada por los vendedores.
     */
    public function movimientosStats(): array
    {
        return Profiler::measure('DashboardStats::movimientosStats', fn() =>
            Cache::remember('dashboard.movimientos_stats', self::TTL, function () {
                if (config('database.default') === 'pgsql') {
                    $total           = Movimiento::query()->count();
                    $requisiciones   = Movimiento::query()->where('tipo', 'REQUISICION')->count();
                    $sincronizaciones = Movimiento::query()->where('usuario', 'sistema_sync')->count();

                    return compact('total', 'requisiciones', 'sincronizaciones');
                }

                $total         = StockMovement::query()->count();
                $requisiciones = StockMovement::query()->where('tipo', 'requisicion')->count();

                return compact('total', 'requisiciones');
            })
        );
    }

    /**
     * Stock total agrupado por sede.
     * Cambia únicamente cuando se importa un Excel de inventario.
     */
    public function existenciaPorSede(): array
    {
        return Profiler::measure('DashboardStats::existenciaPorSede', fn() =>
            Cache::remember('dashboard.existencia_por_sede', self::TTL, function () {
                if (config('database.default') === 'pgsql') {
                    return DB::connection('pgsql')
                        ->table('stock_actual')
                        ->select('sede', DB::raw('SUM(existencia) as total_stock'))
                        ->groupBy('sede')
                        ->pluck('total_stock', 'sede')
                        ->toArray();
                }

                return DB::table('product_sede_metrics')
                    ->select('sede', DB::raw('SUM(existencia) as total_stock'))
                    ->groupBy('sede')
                    ->pluck('total_stock', 'sede')
                    ->toArray();
            })
        );
    }

    /**
     * Invalida todo el caché del Dashboard.
     * Debe llamarse después de una importación de stock.
     */
    public function invalidate(): void
    {
        Cache::forget('dashboard.productos_activos');
        Cache::forget('dashboard.movimientos_stats');
        Cache::forget('dashboard.existencia_por_sede');
    }
}
