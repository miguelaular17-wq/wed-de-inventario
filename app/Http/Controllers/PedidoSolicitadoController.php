<?php

namespace App\Http\Controllers;

use App\Models\PedidoSolicitado;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PedidoSolicitadoController extends Controller
{
    public function categorias(): JsonResponse
    {
        if (config('database.default') === 'pgsql') {
            $categorias = DB::connection('pgsql')
                ->table('inventario_v2.productos')
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->orderBy('categoria')
                ->pluck('categoria');
        } else {
            $categorias = Product::whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->orderBy('categoria')
                ->pluck('categoria');
        }

        return response()->json(['categorias' => $categorias]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['productos' => []]);
        }

        $limit = 20;
        $search = '%' . mb_strtolower($q) . '%';

        if (config('database.default') === 'pgsql') {
            $rows = DB::connection('pgsql')
                ->table('inventario_v2.productos as p')
                ->leftJoin(
                    DB::raw('(SELECT producto_id, COALESCE(SUM(existencia), 0) AS total_stock FROM inventario_v2.stock_actual GROUP BY producto_id) AS sa'),
                    'p.id', '=', 'sa.producto_id'
                )
                ->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(p.codigo) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(p.nombre) LIKE ?', [$search]);
                })
                ->orderByDesc(DB::raw('COALESCE(sa.total_stock, 0)'))
                ->orderBy('p.nombre')
                ->limit($limit)
                ->get([
                    'p.id', 'p.codigo', 'p.nombre', 'p.categoria', 'p.proveedor',
                    DB::raw('COALESCE(sa.total_stock, 0) AS total_stock'),
                ]);

            $productos = $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'codigo' => $row->codigo,
                'producto' => $row->nombre,
                'categoria' => $row->categoria,
                'proveedor' => $row->proveedor,
                'stock' => (int) $row->total_stock,
            ]);
        } else {
            $productos = Product::query()
                ->withSum('sedeMetrics as total_stock', 'existencia')
                ->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(cod_centro) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(producto) LIKE ?', [$search]);
                })
                ->orderByDesc('total_stock')
                ->orderBy('producto')
                ->limit($limit)
                ->get(['id', 'cod_centro', 'producto', 'categoria', 'proveedor'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'codigo' => $row->cod_centro,
                    'producto' => $row->producto,
                    'categoria' => $row->categoria,
                    'proveedor' => $row->proveedor,
                    'stock' => (int) ($row->total_stock ?? 0),
                ]);
        }

        return response()->json(['productos' => $productos]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            return response()->json([
                'ok' => false,
                'message' => 'La tabla de pedidos no está configurada. Ejecuta las migraciones.',
            ], 503);
        }

        $data = $request->validate([
            'producto_id' => ['nullable', 'integer'],
            'codigo' => ['required', 'string', 'max:64'],
            'producto' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'proveedor' => ['nullable', 'string', 'max:255'],
            'solicitante' => ['nullable', 'string', 'max:120'],
            'sede' => ['nullable', 'string', 'max:50'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $pedido = PedidoSolicitado::create([
            'producto_id' => $data['producto_id'] ?? null,
            'codigo' => $data['codigo'],
            'producto' => $data['producto'],
            'categoria' => $data['categoria'] ?? null,
            'proveedor' => $data['proveedor'] ?? null,
            'solicitante' => $data['solicitante'] ?? null,
            'sede' => $data['sede'] ?? null,
            'notas' => $data['notas'] ?? null,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Producto solicitado correctamente. El equipo de compras lo revisará.',
            'pedido' => $pedido,
        ]);
    }

    public function marcarComprado(Request $request): RedirectResponse
    {
        $producto = $request->input('producto');
        PedidoSolicitado::where('producto', $producto)
            ->where('estado', 'pendiente')
            ->update([
                'estado' => 'comprado',
                'atendido_at' => now(),
            ]);

        return back()->with('success', 'Pedidos marcados como comprados.');
    }

    public function marcarFueraMercado(Request $request): RedirectResponse
    {
        $producto = $request->input('producto');
        PedidoSolicitado::where('producto', $producto)
            ->where('estado', 'pendiente')
            ->update([
                'estado' => 'fuera_de_mercado',
                'atendido_at' => now(),
            ]);

        return back()->with('success', 'Pedidos marcados como fuera de mercado.');
    }

    public function reporteExcel()
    {
        $pedidos = PedidoSolicitado::where('estado', 'pendiente')
            ->selectRaw('producto, MAX(codigo) as codigo, MAX(categoria) as categoria, MAX(proveedor) as proveedor, COUNT(*) as frecuencia, MAX(created_at) as created_at')
            ->groupBy('producto')
            ->orderByDesc('frecuencia')
            ->get();

        $csvData = mb_convert_encoding("Producto;Código;Categoría;Proveedor;Frecuencia;Última Solicitud\n", 'UTF-8', 'auto');
        foreach($pedidos as $p) {
            $csvData .= sprintf(
                "\"%s\";\"%s\";\"%s\";\"%s\";%d;\"%s\"\n",
                str_replace('"', '""', $p->producto),
                str_replace('"', '""', $p->codigo),
                str_replace('"', '""', $p->categoria),
                str_replace('"', '""', $p->proveedor),
                $p->frecuencia,
                $p->created_at
            );
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="reporte_qpedir_'.date('Ymd').'.csv"');
    }

    public function reporteDiarioPdf()
    {
        $pedidos = PedidoSolicitado::where('estado', 'pendiente')
            ->whereDate('created_at', now()->toDateString())
            ->selectRaw('producto, MAX(codigo) as codigo, MAX(categoria) as categoria, COUNT(*) as frecuencia, MAX(created_at) as created_at')
            ->groupBy('producto')
            ->orderByDesc('frecuencia')
            ->get();
            
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('comprador.reporte_diario', ['pedidos' => $pedidos]);
        return $pdf->download('reporte_diario_qpedir_'.date('Ymd').'.pdf');
    }

    public function reporteDiarioSedePdf(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->sede) {
            return redirect()->back()->with('error', 'No tienes una sede asignada para generar el reporte.');
        }

        $pedidos = PedidoSolicitado::where('estado', 'pendiente')
            ->whereDate('created_at', now()->toDateString())
            ->where('sede', $user->sede)
            ->selectRaw('producto, MAX(codigo) as codigo, MAX(categoria) as categoria, COUNT(*) as frecuencia, MAX(created_at) as created_at')
            ->groupBy('producto')
            ->orderByDesc('frecuencia')
            ->get();
            
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reporte_diario_sede', [
            'pedidos' => $pedidos,
            'sede' => $user->sede
        ]);
        return $pdf->download('reporte_diario_sede_'.$user->sede.'_'.date('Ymd').'.pdf');
    }

    public function reportePdf(Request $request)
    {
        $chartPie = $request->input('chart_pie');
        $chartBar = $request->input('chart_bar');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('comprador.reporte_graficos', [
            'chartPie' => $chartPie,
            'chartBar' => $chartBar,
        ]);
        return $pdf->download('reporte_graficos_qpedir_'.date('Ymd').'.pdf');
    }
}
