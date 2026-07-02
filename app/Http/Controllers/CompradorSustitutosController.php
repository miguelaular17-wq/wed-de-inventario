<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class CompradorSustitutosController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('q');
        $categoria = $request->input('categoria');

        $categorias = \Illuminate\Support\Facades\Cache::remember('filter_categorias', 86400, function () {
            return DB::table('inventario_v2.productos')->whereNotNull('categoria')->where('categoria', '!=', '')->distinct()->pluck('categoria');
        });

        if (empty($categoria)) {
            $productos = collect();
        } else {
            $query = DB::table('inventario_v2.productos as p')
                ->select(
                    'p.id',
                    'p.codigo',
                    'p.nombre',
                    'p.categoria',
                    'p.subcategoria',
                    'p.proveedor',
                    'p.excluir_compras',
                    DB::raw("COALESCE(SUM(sa.existencia), 0) as stock_total"),
                    DB::raw("MAX(vh.ultima_compra) as ultima_compra")
                )
                ->leftJoin('inventario_v2.stock_actual as sa', 'p.id', '=', 'sa.producto_id')
                ->leftJoin('inventario_v2.ventas_historicas as vh', 'p.id', '=', 'vh.producto_id')
                ->where('p.activo', true)
                ->whereNotNull('p.subcategoria')
                ->where('p.subcategoria', '!=', '');

            if ($q) {
                $query->where(function($sq) use ($q) {
                    $sq->where('p.nombre', 'ILIKE', "%{$q}%")
                       ->orWhere('p.codigo', 'ILIKE', "%{$q}%")
                       ->orWhere('p.subcategoria', 'ILIKE', "%{$q}%");
                });
            }
            $query->where('p.categoria', $categoria);

            $query->groupBy('p.id', 'p.codigo', 'p.nombre', 'p.categoria', 'p.subcategoria', 'p.proveedor', 'p.excluir_compras');
            $query->orderBy('p.subcategoria');
            $query->orderBy('p.nombre');

            $productos = $query->get();
        }

        // Group by subcategory and a generic name prefix (first word)
        $grouped = [];
        foreach ($productos as $p) {
            // Extraer la primera palabra clave del nombre para agrupar similitudes
            $nombre_parts = explode(' ', trim($p->nombre));
            $keyword = count($nombre_parts) > 0 ? strtoupper($nombre_parts[0]) : 'OTROS';
            
            // Si el nombre es muy corto o común, agrupamos solo por subcategoría
            $group_key = $p->subcategoria . ' | ' . $keyword;

            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = [
                    'subcategoria' => $p->subcategoria,
                    'keyword' => $keyword,
                    'productos' => []
                ];
            }
            
            $grouped[$group_key]['productos'][] = $p;
        }

        // Filtrar grupos que tengan más de 1 producto (para que sea una comparativa real)
        $sustitutos = array_filter($grouped, function($group) {
            return count($group['productos']) > 1;
        });

        if ($request->has('export_pdf')) {
            $pdf = Pdf::loadView('comprador.pdf.sustitutos', compact('sustitutos'));
            return $pdf->download('Analisis_Sustitutos.pdf');
        }

        return view('comprador.sustitutos', compact('sustitutos', 'categorias', 'q', 'categoria'));
    }
}
