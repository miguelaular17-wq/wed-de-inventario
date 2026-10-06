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
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user || ! $user->canAccessComprasTab('qpedir')) {
                abort(403, 'Acceso denegado. Permisos insuficientes.');
            }

            return $next($request);
        })->only([
            'marcarComprado',
            'marcarFueraMercado',
            'reporteExcel',
            'reportePdf',
            'reporteDiarioPdf',
        ]);
    }

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

            $porSede = $this->existenciasPorProducto($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
            $productos = $rows->map(function ($row) use ($porSede) {
                $existencias = $porSede[(int) $row->id] ?? [];

                return [
                    'id' => (int) $row->id,
                    'codigo' => $row->codigo,
                    'producto' => $row->nombre,
                    'categoria' => $row->categoria,
                    'proveedor' => $row->proveedor,
                    'stock' => (int) $row->total_stock,
                    'existencias' => $existencias,
                ];
            });
        } else {
            $productos = Product::query()
                ->with('sedeMetrics')
                ->withSum('sedeMetrics as total_stock', 'existencia')
                ->where(function ($query) use ($search) {
                    $query->whereRaw('LOWER(cod_centro) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(producto) LIKE ?', [$search]);
                })
                ->orderByDesc('total_stock')
                ->orderBy('producto')
                ->limit($limit)
                ->get(['id', 'cod_centro', 'producto', 'categoria', 'proveedor'])
                ->map(function ($row) {
                    $existencias = $row->sedeMetrics
                        ->filter(fn ($m) => (int) $m->existencia > 0)
                        ->map(fn ($m) => [
                            'sede' => (string) $m->sede,
                            'nombre' => (string) config('inventario.display.'.strtoupper((string) $m->sede), $m->sede),
                            'cantidad' => (int) $m->existencia,
                        ])
                        ->values()
                        ->all();

                    return [
                        'id' => (int) $row->id,
                        'codigo' => $row->cod_centro,
                        'producto' => $row->producto,
                        'categoria' => $row->categoria,
                        'proveedor' => $row->proveedor,
                        'stock' => (int) ($row->total_stock ?? 0),
                        'existencias' => $this->ordenarExistencias($existencias),
                    ];
                });
        }

        $productos = collect($productos)->concat($this->manualesEnBusqueda($search, $productos))->values();

        return response()->json(['productos' => $productos]);
    }

    /**
     * Productos pedidos a mano que no están en el catálogo.
     *
     * @param  iterable<int, array<string, mixed>>  $catalogo
     */
    private function manualesEnBusqueda(string $search, iterable $catalogo): array
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            return [];
        }

        $ya = [];
        foreach ($catalogo as $item) {
            $ya[mb_strtolower(trim((string) ($item['producto'] ?? '')))] = true;
        }

        $rows = DB::table('pedidos_solicitados')
            ->whereRaw("UPPER(TRIM(codigo)) = 'MANUAL'")
            ->whereRaw('LOWER(producto) LIKE ?', [$search])
            ->selectRaw('producto, MAX(categoria) as categoria, MAX(proveedor) as proveedor')
            ->groupBy('producto')
            ->orderBy('producto')
            ->limit(20)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $nombre = trim((string) $row->producto);
            if ($nombre === '' || isset($ya[mb_strtolower($nombre)])) {
                continue;
            }
            $out[] = [
                'id' => null,
                'codigo' => 'MANUAL',
                'producto' => $nombre,
                'categoria' => $row->categoria,
                'proveedor' => $row->proveedor,
                'existencias' => [],
                'manual' => true,
            ];
        }

        return $out;
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
            'codigo' => ['required', 'string', 'max:255'],
            'producto' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'proveedor' => ['nullable', 'string', 'max:255'],
            'solicitante' => ['nullable', 'string', 'max:120'],
            'sede' => ['nullable', 'string', 'max:50'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'codigo.max' => 'El código del producto es demasiado largo.',
            'codigo.required' => 'Falta el código del producto.',
            'producto.required' => 'Falta el nombre del producto.',
        ]);

        $codigo = $this->normalizarCodigoPedido((string) $data['codigo']);
        $existencias = $this->existenciasDeProducto(
            isset($data['producto_id']) ? (int) $data['producto_id'] : null,
            $codigo
        );
        if ($existencias !== []) {
            $donde = collect($existencias)
                ->map(fn ($e) => $e['nombre'].' ('.$e['cantidad'].')')
                ->implode(', ');

            return response()->json([
                'ok' => false,
                'message' => 'No se puede solicitar. Ya hay existencia en: '.$donde.'.',
                'existencias' => $existencias,
            ], 422);
        }

        $pedido = PedidoSolicitado::create([
            'producto_id' => $data['producto_id'] ?? null,
            'codigo' => $codigo,
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
        $data = $request->validate([
            'producto' => ['required', 'string', 'max:255'],
            'compra_proveedor' => ['required', 'string', 'max:255'],
            'fecha_compra' => ['required', 'date'],
        ], [
            'compra_proveedor.required' => 'Indica el proveedor al que se le compró.',
            'fecha_compra.required' => 'Indica la fecha de compra.',
        ]);

        $updated = PedidoSolicitado::where('producto', $data['producto'])
            ->where('estado', 'pendiente')
            ->update([
                'estado' => 'comprado',
                'compra_proveedor' => trim($data['compra_proveedor']),
                'fecha_compra' => $data['fecha_compra'],
                'fecha_despacho_estimada' => null,
                'atendido_at' => now(),
                'atendido_por' => $request->user()->id,
            ]);

        if ($updated === 0) {
            return back()->withErrors(['pedido' => 'No hay solicitudes pendientes de ese producto.']);
        }

        return back()->with('success', 'Pedidos marcados como comprados.');
    }

    public function marcarFueraMercado(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'producto' => ['required', 'string', 'max:255'],
            'motivo_fuera_mercado' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'motivo_fuera_mercado.required' => 'Indica por qué está fuera de mercado.',
            'motivo_fuera_mercado.min' => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $updated = PedidoSolicitado::where('producto', $data['producto'])
            ->where('estado', 'pendiente')
            ->update([
                'estado' => 'fuera_de_mercado',
                'motivo_fuera_mercado' => trim($data['motivo_fuera_mercado']),
                'atendido_at' => now(),
                'atendido_por' => $request->user()->id,
            ]);

        if ($updated === 0) {
            return back()->withErrors(['pedido' => 'No hay solicitudes pendientes de ese producto.']);
        }

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
        $sede = strtoupper(trim((string) ($user?->sede ?: $request->session()->get('sede_local') ?: '')));
        if (! $user || $sede === '') {
            return redirect()->route('sede.select')
                ->with('error', 'No tienes una sede asignada para generar el reporte.');
        }

        $pedidos = PedidoSolicitado::where('estado', 'pendiente')
            ->whereDate('created_at', now()->toDateString())
            ->whereRaw('UPPER(TRIM(COALESCE(sede, \'\'))) = ?', [$sede])
            ->selectRaw('producto, MAX(codigo) as codigo, MAX(categoria) as categoria, COUNT(*) as frecuencia, MAX(created_at) as created_at')
            ->groupBy('producto')
            ->orderByDesc('frecuencia')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reporte_diario_sede', [
            'pedidos' => $pedidos,
            'sede' => $sede,
        ]);

        return $pdf->download('reporte_diario_sede_'.$sede.'_'.date('Ymd').'.pdf');
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

    /**
     * Algunos productos traen varios códigos unidos ("A / B / C").
     * Guardamos el primero y limitamos longitud.
     */
    /**
     * @param  list<int>  $productoIds
     * @return array<int, list<array{sede: string, nombre: string, cantidad: int}>>
     */
    private function existenciasPorProducto(array $productoIds): array
    {
        $productoIds = array_values(array_filter($productoIds));
        if ($productoIds === [] || config('database.default') !== 'pgsql') {
            return [];
        }

        $rows = DB::connection('pgsql')
            ->table('inventario_v2.stock_actual')
            ->whereIn('producto_id', $productoIds)
            ->where('existencia', '>', 0)
            ->get(['producto_id', 'sede', 'existencia']);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->producto_id;
            $sede = strtoupper(trim((string) $row->sede));
            $out[$id][] = [
                'sede' => $sede,
                'nombre' => (string) config('inventario.display.'.$sede, $row->sede),
                'cantidad' => (int) $row->existencia,
            ];
        }

        foreach ($out as $id => $items) {
            $out[$id] = $this->ordenarExistencias($items);
        }

        return $out;
    }

    /**
     * @return list<array{sede: string, nombre: string, cantidad: int}>
     */
    private function existenciasDeProducto(?int $productoId, string $codigo): array
    {
        if (strtoupper($codigo) === 'MANUAL' && ! $productoId) {
            return [];
        }

        if (config('database.default') !== 'pgsql') {
            return [];
        }

        $query = DB::connection('pgsql')
            ->table('inventario_v2.stock_actual as sa')
            ->join('inventario_v2.productos as p', 'p.id', '=', 'sa.producto_id')
            ->where('sa.existencia', '>', 0);

        if ($productoId) {
            $query->where('p.id', $productoId);
        } elseif ($codigo !== '' && strtoupper($codigo) !== 'MANUAL') {
            $query->whereRaw('UPPER(TRIM(p.codigo)) = ?', [strtoupper(trim($codigo))]);
        } else {
            return [];
        }

        $items = $query->get(['sa.sede', 'sa.existencia'])->map(function ($row) {
            $sede = strtoupper(trim((string) $row->sede));

            return [
                'sede' => $sede,
                'nombre' => (string) config('inventario.display.'.$sede, $row->sede),
                'cantidad' => (int) $row->existencia,
            ];
        })->all();

        return $this->ordenarExistencias($items);
    }

    /**
     * @param  list<array{sede: string, nombre: string, cantidad: int}>  $items
     * @return list<array{sede: string, nombre: string, cantidad: int}>
     */
    private function ordenarExistencias(array $items): array
    {
        $orden = array_flip(array_map('strtoupper', config('inventario.sedes_gerencial', [])));
        usort($items, function ($a, $b) use ($orden) {
            $ia = $orden[$a['sede']] ?? 99;
            $ib = $orden[$b['sede']] ?? 99;

            return $ia <=> $ib;
        });

        return array_values($items);
    }

    private function normalizarCodigoPedido(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return 'MANUAL';
        }

        if (str_contains($codigo, ' / ')) {
            $codigo = trim(explode(' / ', $codigo, 2)[0]);
        } elseif (str_contains($codigo, '/')) {
            $codigo = trim(explode('/', $codigo, 2)[0]);
        }

        return mb_substr($codigo, 0, 255);
    }
}
