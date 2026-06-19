<?php

namespace App\Http\Controllers;

use App\Services\ProductRepository;
use App\Services\RequisicionPersonalizadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function __construct(
        private ProductRepository $products,
        private RequisicionPersonalizadaService $reqPersonalizada,
    ) {}

    public function index(Request $request): View
    {
        $sede = (string) $request->session()->get('sede_local');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
        ];

        $products = $this->products->loadForSede($sede);
        $sedesStock = collect(config('inventario.sedes_stock'))
            ->reject(fn ($s) => $s === $sede)
            ->values()
            ->all();

        $base = $this->reqPersonalizada->buildRows(
            $products,
            $sede,
            $this->reqPersonalizada->loadManuales($sede),
        );
        $rows = $this->reqPersonalizada->applyFilters($base, $filters);
        $stockUpdatedAt = $this->products->lastStockUpdate();
        $manualUpdatedAt = $this->reqPersonalizada->lastUpdatedAt($sede);
        $updatedAt = $stockUpdatedAt && $manualUpdatedAt
            ? max($stockUpdatedAt, $manualUpdatedAt)
            : ($stockUpdatedAt ?: $manualUpdatedAt);

        return view('inventario.index', [
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
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
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
            return back()->withErrors(['manual' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventario.index', [
                'q' => $request->query('q', $request->input('q')),
                'categoria' => $request->query('categoria', $request->input('categoria', 'Ninguno')),
                'subcategoria' => $request->query('subcategoria', $request->input('subcategoria', 'Ninguno')),
            ])
            ->with('status', 'Requisición guardada. El stock se aplicará al exportar el CSV.');
    }

    public function metricasManual(Request $request): \Illuminate\Http\JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $codigo = (string) $request->query('codigo');
        $sedeOrigen = (string) $request->query('sede_origen');
        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));

        $product = $this->products->loadForSede($sede)->firstWhere('cod_centro', $codigo);
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
