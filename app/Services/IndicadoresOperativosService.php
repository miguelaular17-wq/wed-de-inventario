<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IndicadoresOperativosService
{
    /**
     * @return array{kpis: array<string, int|float>, personas: list<array<string, mixed>>}
     */
    public function compras(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $vacio = [
            'kpis' => [
                'pendientes' => 0,
                'comprados' => 0,
                'fuera' => 0,
                'productos' => 0,
                'dias' => 0,
            ],
            'personas' => [],
        ];

        if (! Schema::hasTable('pedidos_solicitados')) {
            return $vacio;
        }

        $base = DB::table('pedidos_solicitados as ps');
        if ($sede) {
            $base->whereRaw('UPPER(TRIM(ps.sede)) = ?', [$sede]);
        }

        $pendientes = (clone $base)->where('ps.estado', 'pendiente')->count();

        $cerrados = (clone $base)
            ->whereIn('ps.estado', ['comprado', 'fuera_de_mercado'])
            ->whereBetween('ps.atendido_at', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->selectRaw('ps.atendido_por')
            ->selectRaw("COUNT(*) FILTER (WHERE ps.estado = 'comprado') as comprados")
            ->selectRaw("COUNT(DISTINCT ps.producto) FILTER (WHERE ps.estado = 'comprado') as productos")
            ->selectRaw("COUNT(*) FILTER (WHERE ps.estado = 'fuera_de_mercado') as fuera")
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (ps.atendido_at - ps.created_at)) / 86400) FILTER (WHERE ps.atendido_at IS NOT NULL) as dias')
            ->groupBy('ps.atendido_por')
            ->get();

        $ids = $cerrados->pluck('atendido_por')->filter()->map(fn ($id) => (int) $id)->all();
        $nombres = User::query()
            ->where(function ($q) use ($ids) {
                $q->where('role', User::ROLE_COMPRADOR);
                if ($ids !== []) {
                    $q->orWhereIn('id', $ids);
                }
            })
            ->get(['id', 'name'])
            ->keyBy('id');

        $porId = $cerrados->keyBy(fn ($row) => (string) ($row->atendido_por ?? ''));
        $personas = [];
        $vistos = [];

        foreach ($nombres as $user) {
            $row = $porId->get((string) $user->id);
            $vistos[(string) $user->id] = true;
            $personas[] = $this->filaCompra($user->name, $row);
        }

        foreach ($cerrados as $row) {
            $id = (string) ($row->atendido_por ?? '');
            if (isset($vistos[$id])) {
                continue;
            }
            $personas[] = $this->filaCompra($id === '' ? 'Sin usuario' : 'Usuario '.$id, $row);
        }

        usort($personas, fn ($a, $b) => $b['cerrados'] <=> $a['cerrados']);

        $comprados = array_sum(array_column($personas, 'comprados'));
        $fuera = array_sum(array_column($personas, 'fuera'));
        $productos = array_sum(array_column($personas, 'productos'));
        $conDias = array_values(array_filter($personas, fn ($p) => $p['dias'] > 0));
        $dias = $conDias === []
            ? 0
            : array_sum(array_column($conDias, 'dias')) / count($conDias);

        return [
            'kpis' => [
                'pendientes' => (int) $pendientes,
                'comprados' => (int) $comprados,
                'fuera' => (int) $fuera,
                'productos' => (int) $productos,
                'dias' => round($dias, 1),
            ],
            'personas' => $personas,
        ];
    }

    /**
     * @return array{kpis: array<string, int|float>, porCaja: list<array<string, mixed>>}
     */
    public function cajas(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $vacio = [
            'kpis' => ['facturas' => 0, 'monto' => 0, 'ticket' => 0],
            'porCaja' => [],
        ];

        if (! Schema::hasTable('ventas_detalle') || ! Schema::hasColumn('ventas_detalle', 'cajera')) {
            return $vacio;
        }

        $monto = Schema::hasColumn('ventas_detalle', 'precio_neto')
            ? 'COALESCE(vd.precio_neto, 0)'
            : 'COALESCE(vd.precio_venta, 0) * COALESCE(vd.cantidad, 0)';

        $query = DB::table('ventas_detalle as vd')
            ->where('vd.tipo_documento', 'FAC')
            ->whereBetween('vd.fecha', [$inicio->toDateString(), $fin->toDateString()]);
        if ($sede) {
            $query->whereRaw('UPPER(TRIM(vd.sede)) = ?', [$sede]);
        }

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        $rows = $query
            ->selectRaw("UPPER(TRIM(vd.sede)) as sede")
            ->selectRaw("COALESCE(NULLIF(TRIM(vd.cajera), ''), 'Sin cajera') as cajera")
            ->selectRaw('COUNT(DISTINCT vd.numero_documento) as facturas')
            ->selectRaw("SUM({$monto}) as monto")
            ->groupBy(DB::raw('UPPER(TRIM(vd.sede))'), DB::raw("COALESCE(NULLIF(TRIM(vd.cajera), ''), 'Sin cajera')"))
            ->orderBy('sede')
            ->orderByDesc('monto')
            ->get();

        $porSede = [];
        foreach ($rows as $row) {
            $facturas = (int) $row->facturas;
            $importe = round((float) $row->monto, 2);
            $porSede[$row->sede]['sede'] = $row->sede;
            $porSede[$row->sede]['facturas'] = ($porSede[$row->sede]['facturas'] ?? 0) + $facturas;
            $porSede[$row->sede]['monto'] = ($porSede[$row->sede]['monto'] ?? 0) + $importe;
            $porSede[$row->sede]['cajeras'][] = [
                'cajera' => $row->cajera,
                'facturas' => $facturas,
                'monto' => $importe,
                'ticket' => $facturas > 0 ? round($importe / $facturas, 2) : 0,
            ];
        }

        $sedesOut = array_values($porSede);
        $facturas = array_sum(array_column($sedesOut, 'facturas'));
        $totalMonto = array_sum(array_column($sedesOut, 'monto'));

        return [
            'kpis' => [
                'facturas' => (int) $facturas,
                'monto' => round($totalMonto, 2),
                'ticket' => $facturas > 0 ? round($totalMonto / $facturas, 2) : 0,
            ],
            'porCaja' => $sedesOut,
        ];
    }

    /**
     * Traslados de inventario (TRA) con signo: positivo sube existencia, negativo la baja.
     *
     * @return array{kpis: array<string, int|float>, porSede: list<array<string, mixed>>}
     */
    public function traslados(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $vacio = [
            'kpis' => [
                'documentos' => 0,
                'entrada' => 0,
                'salida' => 0,
                'neto' => 0,
                'utilidad' => 0,
                'perdida' => 0,
                'valor' => 0,
            ],
            'porSede' => [],
        ];

        if (! Schema::hasTable('ajustes_inventario')) {
            return $vacio;
        }

        $valor = Schema::hasColumn('ajustes_inventario', 'costo_unitario')
            ? 'cantidad * COALESCE(costo_unitario, 0)'
            : '0';

        $query = DB::table('ajustes_inventario')
            ->whereRaw("UPPER(TRIM(tipo_movimiento)) = 'TRA'")
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        if ($sede) {
            $query->whereRaw('UPPER(TRIM(sede)) = ?', [$sede]);
        }

        $rows = $query
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw("COUNT(DISTINCT TRIM(sede) || '-' || TRIM(tipo_movimiento) || '-' || TRIM(numero_documento)) as documentos")
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entrada')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN cantidad ELSE 0 END) as salida')
            ->selectRaw('SUM(cantidad) as neto')
            ->selectRaw("SUM(CASE WHEN cantidad > 0 THEN {$valor} ELSE 0 END) as utilidad")
            ->selectRaw("SUM(CASE WHEN cantidad < 0 THEN {$valor} ELSE 0 END) as perdida")
            ->selectRaw("SUM({$valor}) as valor")
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->orderBy('sede')
            ->get();

        $porSede = [];
        foreach ($rows as $row) {
            $porSede[] = [
                'sede' => (string) $row->sede,
                'documentos' => (int) $row->documentos,
                'entrada' => round((float) $row->entrada, 2),
                'salida' => round((float) $row->salida, 2),
                'neto' => round((float) $row->neto, 2),
                'utilidad' => round((float) $row->utilidad, 2),
                'perdida' => round((float) $row->perdida, 2),
                'valor' => round((float) $row->valor, 2),
            ];
        }

        return [
            'kpis' => [
                'documentos' => (int) array_sum(array_column($porSede, 'documentos')),
                'entrada' => round((float) array_sum(array_column($porSede, 'entrada')), 2),
                'salida' => round((float) array_sum(array_column($porSede, 'salida')), 2),
                'neto' => round((float) array_sum(array_column($porSede, 'neto')), 2),
                'utilidad' => round((float) array_sum(array_column($porSede, 'utilidad')), 2),
                'perdida' => round((float) array_sum(array_column($porSede, 'perdida')), 2),
                'valor' => round((float) array_sum(array_column($porSede, 'valor')), 2),
            ],
            'porSede' => $porSede,
        ];
    }

    /**
     * @return array{nombre: string, comprados: int, productos: int, fuera: int, cerrados: int, dias: float}
     */
    private function filaCompra(string $nombre, ?object $row): array
    {
        $comprados = (int) ($row->comprados ?? 0);
        $fuera = (int) ($row->fuera ?? 0);

        return [
            'nombre' => $nombre,
            'comprados' => $comprados,
            'productos' => (int) ($row->productos ?? 0),
            'fuera' => $fuera,
            'cerrados' => $comprados + $fuera,
            'dias' => round((float) ($row->dias ?? 0), 1),
        ];
    }
}
