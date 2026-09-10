<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RequisicionManual;
use App\Services\ApiInventoryContext;
use App\Services\ProductRepository;
use App\Services\RequisicionPersonalizadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequisitionController extends Controller
{
    public function __construct(
        private ApiInventoryContext $context,
        private ProductRepository $products,
        private RequisicionPersonalizadaService $requisitions,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'APLICADA'])],
        ]);
        $sede = $this->context->sede($request);
        $scope = $this->context->usuarioScope($request->user());
        $query = RequisicionManual::query()
            ->where('sede_local', $sede)
            ->when($scope, fn ($q) => $q->where('usuario', $scope));

        if (($data['estado'] ?? null) === 'PENDIENTE') {
            $query->whereNull('aplicada_at');
        } elseif (($data['estado'] ?? null) === 'APLICADA') {
            $query->whereNotNull('aplicada_at');
        }

        $rows = $query->orderByDesc('updated_at')->orderByDesc('id')->get();

        return response()->json([
            'data' => $rows->map(fn (RequisicionManual $row) => $this->payload($row))->values(),
            'meta' => [
                'total' => $rows->count(),
                'pendientes' => $rows->whereNull('aplicada_at')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:255'],
            'producto' => ['nullable', 'string', 'max:500'],
            'sede_origen' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);
        $sede = $this->context->sede($request);
        $origen = strtoupper($data['sede_origen']);
        if (! in_array($origen, array_column($this->context->sedesOrigen($sede), 'codigo'), true)) {
            return response()->json([
                'message' => 'Sede origen inválida.',
                'errors' => ['sede_origen' => ['Selecciona una sede origen válida.']],
            ], 422);
        }
        $product = $this->products->findForSedeByCodigo($sede, $data['codigo']);

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $this->requisitions->confirmar(
            $sede,
            (string) $product['cod_centro'],
            (string) $product['producto'],
            $origen,
            (int) $data['cantidad'],
            $request->user()?->email,
        );

        $row = RequisicionManual::query()
            ->where('sede_local', $sede)
            ->where('codigo', $product['cod_centro'])
            ->where('sede_origen', $origen)
            ->firstOrFail();
        $metrics = $this->requisitions->metricasOrigen(
            $product,
            $origen,
            (float) config('inventario.tiempo_pronostico_default', 15)
        );
        [$message, $excess] = $this->requisitions->mensajeValidacion((int) $data['cantidad'], $metrics['excedente']);

        return response()->json([
            'message' => 'Requisición guardada.',
            'data' => $this->payload($row),
            'metrics' => $metrics + [
                'message' => $message,
                'exceso' => $excess,
                'safe' => $excess === null,
            ],
        ], 201);
    }

    public function destroy(Request $request, RequisicionManual $requisicion): JsonResponse
    {
        $sede = $this->context->sede($request);
        $scope = $this->context->usuarioScope($request->user());

        if (
            $requisicion->sede_local !== $sede
            || ($scope !== null && $requisicion->usuario !== $scope)
        ) {
            return response()->json(['message' => 'Requisición no encontrada.'], 404);
        }

        if (! $requisicion->isPendiente()) {
            return response()->json([
                'message' => 'Una requisición aplicada ya no se puede cancelar.',
            ], 422);
        }

        $this->requisitions->eliminar(
            $sede,
            $requisicion->codigo,
            $requisicion->sede_origen,
            $scope,
        );

        return response()->json(['message' => 'Requisición cancelada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RequisicionManual $row): array
    {
        return [
            'id' => $row->id,
            'codigo' => $row->codigo,
            'producto' => $row->producto,
            'sede' => $row->sede_local,
            'sede_origen' => $row->sede_origen,
            'cantidad' => (int) $row->cantidad,
            'estado' => $row->isPendiente() ? 'PENDIENTE' : 'APLICADA',
            'pendiente' => $row->isPendiente(),
            'usuario' => $row->usuario,
            'aplicada_at' => $row->aplicada_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
