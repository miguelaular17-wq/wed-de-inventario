<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCollections;
use App\Services\ProductRepository;
use App\Services\RequisicionPersonalizadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventarioController extends Controller
{
    use PaginatesCollections;

    public function __construct(
        private ProductRepository $products,
        private RequisicionPersonalizadaService $reqPersonalizada,
    ) {}

    public function index(Request $request): View
    {
        $viewData = $this->buildIndexData($request);

        if ($request->header('X-Partial') === 'content') {
            return view('inventario._content', $viewData);
        }

        return view('inventario.index', $viewData);
    }

    private function buildIndexData(Request $request): array
    {
        $sede = (string) $request->session()->get('sede_local');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
        ];

        $products = $this->products->loadForSede($sede);
        if ($filters['q'] !== '' || $filters['categoria'] !== 'Ninguno' || $filters['subcategoria'] !== 'Ninguno') {
            $qLower = mb_strtolower($filters['q']);
            $products = $products->filter(function (array $row) use ($filters, $qLower) {
                if ($filters['categoria'] !== 'Ninguno' && ($row['categoria'] ?? '') !== $filters['categoria']) {
                    return false;
                }
                if ($filters['subcategoria'] !== 'Ninguno' && ($row['subcategoria'] ?? '') !== $filters['subcategoria']) {
                    return false;
                }
                if ($qLower === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
            })->values();
        }

        $sedesStock = collect(config('inventario.sedes_stock'))
            ->reject(fn ($s) => $s === $sede)
            ->values()
            ->all();

        $base = $this->reqPersonalizada->buildRows(
            $products,
            $sede,
            $this->reqPersonalizada->loadManuales($sede),
        );
        $rows = $this->paginateCollection(
            $this->reqPersonalizada->applyFilters($base, $filters),
            $request
        );
        $stockUpdatedAt = $this->products->lastStockUpdate();
        $manualUpdatedAt = $this->reqPersonalizada->lastUpdatedAt($sede);
        $updatedAt = $stockUpdatedAt && $manualUpdatedAt
            ? max($stockUpdatedAt, $manualUpdatedAt)
            : ($stockUpdatedAt ?: $manualUpdatedAt);

        return [
            'sede' => $sede,
            'rows' => $rows,
            'filters' => $filters,
            'categorias' => $this->reqPersonalizada->categorias($base),
            'subcategorias' => $this->reqPersonalizada->subcategorias(
                $base,
                $filters['categoria'] !== 'Ninguno' ? $filters['categoria'] : null
            ),
            'sedesOrigen' => $this->reqPersonalizada->sedesOrigen($sede),
            'sedesStock' => $sedesStock,
            'totalManual' => $this->reqPersonalizada->countPendientes($sede),
            'stockUpdatedAt' => $updatedAt,
        ];
    }

    public function storeManual(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');

        $data = $request->validate([
            'codigo' => ['required', 'string'],
            'producto' => ['required', 'string'],
            'sede_origen' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->reqPersonalizada->confirmar(
                $sede,
                $data['codigo'],
                $data['producto'],
                $data['sede_origen'],
                (int) $data['cantidad'],
                auth()->user()?->email,
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['manual' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Requisición guardada. El stock se aplicará al exportar el CSV.',
                'total_manual' => $this->reqPersonalizada->countPendientes($sede),
            ]);
        }

        return redirect()
            ->route('inventario.index', [
                'q' => $request->query('q', $request->input('q')),
                'categoria' => $request->query('categoria', $request->input('categoria', 'Ninguno')),
                'subcategoria' => $request->query('subcategoria', $request->input('subcategoria', 'Ninguno')),
                'page' => $request->query('page', $request->input('page', 1)),
            ])
            ->with('status', 'Requisición guardada. El stock se aplicará al exportar el CSV.');
    }

    public function destroyManual(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');

        $data = $request->validate([
            'codigo'      => ['required', 'string'],
            'sede_origen' => ['required', 'string'],
        ]);

        $deleted = $this->reqPersonalizada->eliminar(
            $sede,
            $data['codigo'],
            $data['sede_origen'],
        );

        if (! $deleted) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No se encontró la requisición pendiente para eliminar.'], 422);
            }
            return back()->withErrors(['manual' => 'No se encontró la requisición pendiente para eliminar.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Requisición eliminada correctamente.',
                'total_manual' => $this->reqPersonalizada->countPendientes($sede),
            ]);
        }

        return redirect()
            ->route('inventario.index', [
                'q'           => $request->query('q', $request->input('q')),
                'categoria'   => $request->query('categoria', $request->input('categoria', 'Ninguno')),
                'subcategoria' => $request->query('subcategoria', $request->input('subcategoria', 'Ninguno')),
                'page'         => $request->query('page', $request->input('page', 1)),
            ])
            ->with('status', 'Requisición eliminada correctamente.');
    }

    public function metricasManual(Request $request): JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $codigo = (string) $request->query('codigo');
        $sedeOrigen = (string) $request->query('sede_origen');
        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));

        $product = $this->products->findForSedeByCodigo($sede, $codigo);
        if (! $product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $m = $this->reqPersonalizada->metricasOrigen($product, $sedeOrigen, $tp);
        [$msg, $faltante] = $this->reqPersonalizada->mensajeValidacion(
            (int) $request->query('cantidad', 1),
            $m['excedente'],
        );

        return response()->json([
            'metricas' => $m,
            'mensaje' => $msg,
            'faltante' => $faltante,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $since = (string) $request->query('since', '');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
        ];

        $updatedAt = collect([
                $this->products->lastStockUpdate(),
                $this->reqPersonalizada->lastUpdatedAt($sede),
            ])
            ->filter()
            ->map(fn ($value) => is_string($value) ? $value : (string) $value)
            ->max();

        $changed = $since && $updatedAt !== $since;
        $rows = collect();
        $totalManual = $this->reqPersonalizada->countPendientes($sede);

        if ($changed) {
            $products = $this->products->loadForSede($sede);
            $base = $this->reqPersonalizada->buildRows(
                $products,
                $sede,
                $this->reqPersonalizada->loadManuales($sede),
            );
            $filtered = $this->reqPersonalizada->applyFilters($base, $filters);

            $rows = $filtered->map(function (array $row) {
                return [
                    'cod_centro' => $row['cod_centro'],
                    'producto' => $row['producto'],
                    'existencia' => $row['existencia'],
                    'stocks' => $row['stocks'],
                    'req_manual' => $row['req_manual'] ?? false,
                    'origen_manual' => $row['origen_manual'] ?? '',
                    'cantidad_manual' => $row['cantidad_manual'] ?? 0,
                    'accion_manual' => $row['accion_manual'] ?? '',
                    'manual_pendiente' => $row['manual_pendiente'] ?? false,
                    'manuales_list'   => $row['manuales_list'] ?? [],
                ];
            })->values();
        }

        return response()->json([
            'updated_at' => $updatedAt,
            'changed' => $changed,
            'rows' => $rows,
            'total_manual' => $totalManual,
            'row_count' => $rows->count(),
        ]);
    }
}
