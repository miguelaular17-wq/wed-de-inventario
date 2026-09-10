<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiInventoryContext;
use App\Services\ProductRepository;
use App\Services\RequisicionPersonalizadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private ApiInventoryContext $context,
        private ProductRepository $products,
        private RequisicionPersonalizadaService $requisitions,
    ) {
    }

    public function sedes(Request $request): JsonResponse
    {
        $sede = null;
        try {
            $sede = $this->context->sede($request);
        } catch (\Illuminate\Validation\ValidationException) {
            // La selección es opcional al abrir esta pantalla.
        }

        return response()->json([
            'data' => $this->context->sedesLocales(),
            'active' => $sede,
            'locked' => (bool) $request->user()?->sedeIsLocked(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:200'],
        ]);
        $sede = $this->context->sede($request);
        $scope = $this->context->usuarioScope($request->user());
        $manuales = $this->requisitions->loadManuales($sede, false, $scope)->groupBy('codigo');

        $rows = $this->products->loadForSede($sede);
        $categories = $rows->pluck('categoria')->filter()->unique()->sort()->values();

        $q = mb_strtolower(trim((string) ($data['q'] ?? '')), 'UTF-8');
        if ($q !== '') {
            $rows = $rows->filter(function (array $row) use ($q) {
                return str_contains(mb_strtolower((string) ($row['producto'] ?? ''), 'UTF-8'), $q)
                    || str_contains(mb_strtolower((string) ($row['cod_centro'] ?? ''), 'UTF-8'), $q);
            });
        }

        $category = trim((string) ($data['categoria'] ?? ''));
        if ($category !== '') {
            $rows = $rows->where('categoria', $category);
        }

        $rows = $rows->values();
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 30);
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $items = $rows->forPage($page, $perPage)->values()->map(function (array $row) use ($manuales) {
            $codigo = (string) ($row['cod_centro'] ?? '');
            $reqs = $manuales->get($codigo, collect())->map(fn ($req) => $this->requisitionPayload($req))->values();

            return [
                'id' => $row['id'] ?? null,
                'codigo' => $codigo,
                'producto' => $row['producto'] ?? '',
                'categoria' => $row['categoria'] ?? null,
                'subcategoria' => $row['subcategoria'] ?? null,
                'proveedor' => $row['proveedor'] ?? null,
                'existencia_local' => (int) ($row['existencia'] ?? 0),
                'stocks' => collect($row['stocks'] ?? [])->map(fn ($qty) => (int) $qty)->all(),
                'imagen_url' => $row['url_imagen'] ?? $row['imagen_url'] ?? null,
                'requisiciones' => $reqs,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'filters' => [
                'sede' => $sede,
                'categories' => $categories,
            ],
            'sedes_origen' => $this->context->sedesOrigen($sede),
            'updated_at' => $this->products->lastStockUpdate(),
        ]);
    }

    public function metrics(Request $request, string $codigo): JsonResponse
    {
        $data = $request->validate([
            'sede_origen' => ['required', 'string'],
            'cantidad' => ['nullable', 'integer', 'min:1'],
        ]);
        $sede = $this->context->sede($request);
        $origen = strtoupper($data['sede_origen']);
        $cantidad = (int) ($data['cantidad'] ?? 1);

        if (! in_array($origen, array_column($this->context->sedesOrigen($sede), 'codigo'), true)) {
            return response()->json(['message' => 'Sede origen inválida.'], 422);
        }

        $product = $this->products->findForSedeByCodigo($sede, urldecode($codigo));
        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $metrics = $this->requisitions->metricasOrigen(
            $product,
            $origen,
            (float) config('inventario.tiempo_pronostico_default', 15)
        );
        [$message, $excess] = $this->requisitions->mensajeValidacion($cantidad, $metrics['excedente']);

        return response()->json([
            'data' => $metrics + [
                'cantidad' => $cantidad,
                'message' => $message,
                'exceso' => $excess,
                'safe' => $excess === null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requisitionPayload($req): array
    {
        return [
            'id' => $req->id,
            'codigo' => $req->codigo,
            'producto' => $req->producto,
            'sede' => $req->sede_local,
            'sede_origen' => $req->sede_origen,
            'cantidad' => (int) $req->cantidad,
            'estado' => $req->isPendiente() ? 'PENDIENTE' : 'APLICADA',
            'pendiente' => $req->isPendiente(),
            'aplicada_at' => $req->aplicada_at?->toIso8601String(),
            'created_at' => $req->created_at?->toIso8601String(),
            'updated_at' => $req->updated_at?->toIso8601String(),
        ];
    }
}
