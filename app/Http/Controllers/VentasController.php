<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCollections;
use App\Services\ProductRepository;
use App\Services\VentasCalculator;
use App\Services\VentasFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VentasController extends Controller
{
    use PaginatesCollections;

    public function __construct(
        private ProductRepository $products,
        private VentasCalculator $ventas,
        private VentasFilterService $filters,
    ) {}

    public function index(Request $request): View
    {
        $viewData = $this->buildIndexData($request);

        if ($request->header('X-Partial') === 'content') {
            return view('ventas._content', $viewData);
        }

        return view('ventas.index', $viewData);
    }

    private function buildIndexData(Request $request): array
    {
        $sede = (string) $request->session()->get('sede_local');

        if ($request->filled('tiempo_pronostico')) {
            $request->session()->put(
                'tiempo_pronostico',
                max(1, (int) $request->input('tiempo_pronostico'))
            );
        }

        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));

        $filterInput = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
            'accion' => (string) $request->query('accion', 'Ninguno'),
            'req_opc' => (string) $request->query('req_opc', 'Todos'),
            'req_color' => (string) $request->query('req_color', 'Todos'),
        ];

        $products = $this->products->loadForSede($sede);
        if ($filterInput['q'] !== '' || $filterInput['categoria'] !== 'Ninguno' || $filterInput['subcategoria'] !== 'Ninguno') {
            $qLower = mb_strtolower($filterInput['q']);
            $products = $products->filter(function (array $row) use ($filterInput, $qLower) {
                if ($filterInput['categoria'] !== 'Ninguno' && ($row['categoria'] ?? '') !== $filterInput['categoria']) {
                    return false;
                }
                if ($filterInput['subcategoria'] !== 'Ninguno' && ($row['subcategoria'] ?? '') !== $filterInput['subcategoria']) {
                    return false;
                }
                if ($qLower === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
            })->values();
        }

        $calculated = $this->ventas->calcular($products, $sede, $tp);
        $rows = $this->paginateCollection(
            $this->filters->apply($calculated, $filterInput),
            $request
        );

        return [
            'sede' => $sede,
            'rows' => $rows,
            'calculatedCount' => $calculated->count(),
            'filters' => $filterInput,
            'categorias' => $this->filters->categorias($calculated),
            'subcategorias' => $this->filters->subcategorias(
                $calculated,
                $filterInput['categoria'] !== 'Ninguno' ? $filterInput['categoria'] : null
            ),
            'accionesCombo' => $this->filters->accionesCombo(),
            'sedesOpc' => $this->filters->sedesOpc($calculated),
            'reqFiltersVisible' => $filterInput['accion'] === 'HACER REQUISICION',
            'tiempoPronostico' => (int) $tp,
            'minimoInv' => (int) config('inventario.minimo_inv_solicitar', 6),
            'sedesStock' => collect(config('inventario.sedes_stock'))
                ->reject(fn ($s) => $s === $sede)
                ->values()
                ->all(),
            'stockUpdatedAt' => $this->products->lastStockUpdate(),
        ];
    }

    public function sync(Request $request): JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $since = (string) $request->query('since', '');

        $filterInput = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
            'accion' => (string) $request->query('accion', 'Ninguno'),
            'req_opc' => (string) $request->query('req_opc', 'Todos'),
            'req_color' => (string) $request->query('req_color', 'Todos'),
        ];

        $updatedAt = collect([$this->products->lastStockUpdate()])
            ->filter()
            ->map(fn ($value) => is_string($value) ? $value : (string) $value)
            ->max();
        $changed = $since && $updatedAt !== $since;
        $rows = collect();

        if ($changed) {
            $calculated = $this->ventas->calcular($this->products->loadForSede($sede), $sede, (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default')));
            $filtered = $this->filters->apply($calculated, $filterInput);

            $rows = $filtered->map(function (array $row) {
                return [
                    'cod_centro' => $row['cod_centro'],
                    'producto' => $row['producto'],
                    'existencia' => $row['existencia'],
                    'categoria' => $row['categoria'],
                    'subcategoria' => $row['subcategoria'],
                    'venta' => $row['venta'],
                    'stocks' => $row['stocks'],
                    'sugerido' => $row['sugerido'] ?? null,
                    'opc' => $row['opc'] ?? null,
                    'accion' => $row['accion'],
                    'req_tag' => $row['req_tag'] ?? null,
                ];
            })->values();
        }

        return response()->json([
            'updated_at' => $updatedAt,
            'changed' => $changed,
            'rows' => $rows,
        ]);
    }
}
