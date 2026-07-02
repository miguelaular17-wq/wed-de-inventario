<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompradorVentasController extends Controller
{
    public function index(Request $request)
    {
        // Default to last 6 months if no range provided
        $startMonth = $request->input('start_month', Carbon::now()->subMonths(5)->format('Y-m'));
        $endMonth = $request->input('end_month', Carbon::now()->format('Y-m'));

        $q = $request->input('q');
        $proveedor = $request->input('proveedor');
        $categoria = $request->input('categoria');
        $subcategoria = $request->input('subcategoria');

        // Generate list of months for headers
        $months = [];
        $current = Carbon::parse($startMonth . '-01');
        $end = Carbon::parse($endMonth . '-01')->endOfMonth();
        
        while ($current <= $end) {
            $months[] = $current->format('Y-m');
            $current->addMonth();
        }

        // Fetch distinct filters options from productos
        $proveedores = \Illuminate\Support\Facades\Cache::remember('filter_proveedores', 86400, function () {
            return DB::table('inventario_v2.productos')->whereNotNull('proveedor')->where('proveedor', '!=', '')->distinct()->pluck('proveedor');
        });
        $categorias = \Illuminate\Support\Facades\Cache::remember('filter_categorias', 86400, function () {
            return DB::table('inventario_v2.productos')->whereNotNull('categoria')->where('categoria', '!=', '')->distinct()->pluck('categoria');
        });
        $subcategorias = \Illuminate\Support\Facades\Cache::remember('filter_subcategorias', 86400, function () {
            return DB::table('inventario_v2.productos')->whereNotNull('subcategoria')->where('subcategoria', '!=', '')->distinct()->pluck('subcategoria');
        });

        // Main Query (Paginated by products)
        $query = DB::table('inventario_v2.productos as p')
            ->leftJoin('inventario_v2.historial_ventas_mensuales as hv', function($join) use ($startMonth, $endMonth) {
                $join->on('hv.producto_id', '=', 'p.id')
                     ->whereBetween('hv.anio_mes', [
                         Carbon::parse($startMonth . '-01')->format('Y-m'), 
                         Carbon::parse($endMonth . '-01')->format('Y-m')
                     ]);
            })
            ->selectRaw("
                p.id, 
                p.codigo, 
                p.nombre as producto, 
                p.categoria, 
                p.subcategoria, 
                p.proveedor,
                SUM(hv.cantidad) as total_general
            ")
            ->where('p.activo', true);

        // Filters
        if ($q) {
            $query->where(function($sq) use ($q) {
                $sq->where('p.nombre', 'ILIKE', "%{$q}%")
                   ->orWhere('p.codigo', 'ILIKE', "%{$q}%");
            });
        }
        if ($proveedor) {
            $query->where('p.proveedor', $proveedor);
        }
        if ($categoria) {
            $query->where('p.categoria', $categoria);
        }
        if ($subcategoria) {
            $query->where('p.subcategoria', $subcategoria);
        }

        // Apply groupBy
        $query->groupBy('p.id', 'p.codigo', 'p.nombre', 'p.categoria', 'p.subcategoria', 'p.proveedor');
        $query->orderByRaw('SUM(hv.cantidad) DESC NULLS LAST');
        $query->orderBy('p.nombre');

        $paginatedProducts = $query->paginate(50)->appends($request->all());
        $productIds = $paginatedProducts->pluck('id')->toArray();

        // Fetch history details for these 50 products
        $history = [];
        if (!empty($productIds)) {
            $history = DB::table('inventario_v2.historial_ventas_mensuales')
                ->whereIn('producto_id', $productIds)
                ->whereBetween('anio_mes', [
                     Carbon::parse($startMonth . '-01')->format('Y-m'), 
                     Carbon::parse($endMonth . '-01')->format('Y-m')
                ])
                ->select('producto_id', 'anio_mes', DB::raw('SUM(cantidad) as total_cantidad'))
                ->groupBy('producto_id', 'anio_mes')
                ->get();
        }

        $pivoted = [];
        foreach ($paginatedProducts as $p) {
            $pivoted[$p->id] = [
                'codigo' => $p->codigo,
                'producto' => $p->producto,
                'categoria' => $p->categoria,
                'subcategoria' => $p->subcategoria,
                'proveedor' => $p->proveedor,
                'total_general' => $p->total_general ?? 0,
                'meses' => array_fill_keys($months, 0)
            ];
        }

        foreach ($history as $h) {
            if (isset($pivoted[$h->producto_id]) && in_array($h->anio_mes, $months)) {
                $pivoted[$h->producto_id]['meses'][$h->anio_mes] += $h->total_cantidad;
            }
        }

        return view('comprador.historico', compact(
            'pivoted', 'months', 'startMonth', 'endMonth', 
            'q', 'proveedor', 'categoria', 'subcategoria',
            'proveedores', 'categorias', 'subcategorias',
            'paginatedProducts'
        ));
    }

    public function export(Request $request)
    {
        $startMonth = $request->input('start_month', Carbon::now()->subMonths(5)->format('Y-m'));
        $endMonth = $request->input('end_month', Carbon::now()->format('Y-m'));

        $q = $request->input('q');
        $proveedor = $request->input('proveedor');
        $categoria = $request->input('categoria');
        $subcategoria = $request->input('subcategoria');

        $months = [];
        $current = Carbon::parse($startMonth . '-01');
        $end = Carbon::parse($endMonth . '-01')->endOfMonth();
        
        while ($current <= $end) {
            $months[] = $current->format('Y-m');
            $current->addMonth();
        }

        $query = DB::table('inventario_v2.historial_ventas_mensuales as hv')
            ->join('inventario_v2.productos as p', 'hv.producto_id', '=', 'p.id')
            ->selectRaw("
                p.id, p.codigo, p.nombre as producto, p.categoria, p.subcategoria, p.proveedor,
                hv.anio_mes as mes, SUM(hv.cantidad) as total_cantidad
            ")
            ->whereBetween('hv.anio_mes', [
                Carbon::parse($startMonth . '-01')->format('Y-m'), 
                Carbon::parse($endMonth . '-01')->format('Y-m')
            ]);

        if ($q) {
            $query->where(function($sq) use ($q) {
                $sq->where('p.nombre', 'ILIKE', "%{$q}%")->orWhere('p.codigo', 'ILIKE', "%{$q}%");
            });
        }
        if ($proveedor) { $query->where('p.proveedor', $proveedor); }
        if ($categoria) { $query->where('p.categoria', $categoria); }
        if ($subcategoria) { $query->where('p.subcategoria', $subcategoria); }

        $query->groupBy('p.id', 'p.codigo', 'p.nombre', 'p.categoria', 'p.subcategoria', 'p.proveedor', 'hv.anio_mes');
        $results = $query->get();

        $pivoted = [];
        foreach ($results as $row) {
            $pid = $row->id;
            if (!isset($pivoted[$pid])) {
                $pivoted[$pid] = [
                    'codigo' => $row->codigo, 'producto' => $row->producto,
                    'categoria' => $row->categoria, 'subcategoria' => $row->subcategoria,
                    'proveedor' => $row->proveedor, 'total_general' => 0, 'meses' => array_fill_keys($months, 0)
                ];
            }
            if (in_array($row->mes, $months)) {
                $pivoted[$pid]['meses'][$row->mes] += $row->total_cantidad;
                $pivoted[$pid]['total_general'] += $row->total_cantidad;
            }
        }
        usort($pivoted, fn($a, $b) => $b['total_general'] <=> $a['total_general']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('comprador.pdf.historico', compact('pivoted', 'months', 'startMonth', 'endMonth'))
               ->setPaper('a4', 'landscape');
        
        return $pdf->download('Reporte_Ventas_Historicas.pdf');
    }
}
