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
        Cache::forget('dashboard.requisiciones_hoy');
    }

    /**
     * Estado diario de requisiciones por sede, indicando si el supervisor y telefonía
     * ya realizaron sus requisiciones del día.
     */
    public function estadoRequisicionesHoy(): array
    {
        return Profiler::measure('DashboardStats::estadoRequisicionesHoy', fn() =>
            Cache::remember('dashboard.requisiciones_hoy', self::TTL, function () {
                // Ajustar al timezone local (Venezuela) para obtener qué día es "hoy" para el usuario
                $fechaLocal = \Illuminate\Support\Carbon::now('America/Caracas')->toDateString();

                // Obtener requisiciones manuales de hoy
                $requisiciones = \App\Models\RequisicionManual::query()
                    ->whereDate('created_at', $fechaLocal)
                    ->get();

                // Obtener movimientos de requisiciones automáticas de hoy
                $movimientosAut = collect();
                if (config('database.default') === 'pgsql') {
                    $movimientosAut = \App\Models\V2\Movimiento::query()
                        ->whereDate('created_at', $fechaLocal)
                        ->where('tipo', 'REQUISICION')
                        ->get(['usuario', 'destino as sede_local']);
                } else {
                    $movimientosAut = \App\Models\StockMovement::query()
                        ->whereDate('created_at', $fechaLocal)
                        ->where('tipo', 'requisicion')
                        ->get(['usuario', 'sede_destino as sede_local']);
                }

                $todasRequisiciones = $requisiciones->concat($movimientosAut);

                // Obtener nombres de usuario para buscar sus roles
                $nombres = $todasRequisiciones->pluck('usuario')->filter()->unique();

                // Buscar usuarios (puede coincidir por name o email)
                $usuarios = \App\Models\User::query()
                    ->whereIn('name', $nombres)
                    ->orWhereIn('email', $nombres)
                    ->get()
                    ->keyBy(function ($u) {
                        return strtolower($u->name);
                    });
                
                $usuariosPorEmail = \App\Models\User::query()
                    ->whereIn('name', $nombres)
                    ->orWhereIn('email', $nombres)
                    ->get()
                    ->keyBy(function ($u) {
                        return strtolower($u->email);
                    });

                $sedes = config('inventario.sedes_stock', ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL']);
                $estado = [];

                foreach ($sedes as $sede) {
                    $estado[$sede] = [
                        'supervisor' => null,
                        'telefonia' => null,
                    ];
                }

                foreach ($todasRequisiciones as $req) {
                    $sede = strtoupper($req->sede_local);
                    if (!isset($estado[$sede])) {
                        continue;
                    }

                    $nombreUser = $req->usuario;
                    if (!$nombreUser) continue;
                    
                    $nombreKey = strtolower($nombreUser);
                    $user = $usuarios->get($nombreKey) ?? $usuariosPorEmail->get($nombreKey);
                    
                    if ($user) {
                        if ($user->role === 'supervisor') {
                            $estado[$sede]['supervisor'] = $user->name;
                        } elseif ($user->role === 'telefonia') {
                            $estado[$sede]['telefonia'] = $user->name;
                        } else {
                            // Si el rol es otro pero hizo la requisición, lo asignamos a supervisor por defecto si está vacío para no dejarlo en pendiente
                            if (!$estado[$sede]['supervisor']) {
                                $estado[$sede]['supervisor'] = $user->name . ' ('.$user->role.')';
                            }
                        }
                    } else {
                        // Si no se encuentra el rol, se anota como supervisor genérico (suele ser admin o fallback)
                        if (!$estado[$sede]['supervisor']) {
                            $estado[$sede]['supervisor'] = $nombreUser;
                        }
                    }
                }

                return $estado;
            })
        );
    }
}
