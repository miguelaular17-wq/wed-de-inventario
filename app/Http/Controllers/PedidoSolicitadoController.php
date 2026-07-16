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
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $pedido = PedidoSolicitado::create([
            'producto_id' => $data['producto_id'] ?? null,
            'codigo' => $data['codigo'],
            'producto' => $data['producto'],
            'categoria' => $data['categoria'] ?? null,
            'proveedor' => $data['proveedor'] ?? null,
            'solicitante' => $data['solicitante'] ?? null,
            'notas' => $data['notas'] ?? null,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Producto solicitado correctamente. El equipo de compras lo revisará.',
            'pedido' => $pedido,
        ]);
    }

    public function marcarAtendido(PedidoSolicitado $pedido): RedirectResponse
    {
        $pedido->marcarAtendido();

        return back()->with('success', 'Pedido marcado como atendido.');
    }

    public function destroy(PedidoSolicitado $pedido): RedirectResponse
    {
        $pedido->delete();

        return back()->with('success', 'Pedido eliminado.');
    }
}
