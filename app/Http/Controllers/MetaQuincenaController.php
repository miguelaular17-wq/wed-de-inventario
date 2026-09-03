<?php

namespace App\Http\Controllers;

use App\Models\MetaQuincenaProducto;
use App\Services\MetaQuincenaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetaQuincenaController extends Controller
{
    public function index(Request $request, MetaQuincenaService $metas): View
    {
        $user = $request->user();
        abort_unless($metas->puedeVerMetas($user), 403);

        $quincena = $metas->quincenaActual();
        $filas = $metas->listarParaUsuario($user);
        $puedeMarcar = $user->canAccess('meta');
        $equiposPorSede = [];

        foreach ($filas->pluck('sede')->unique() as $sede) {
            $equiposPorSede[$sede] = $metas->responsablesDisponibles($user, $sede)
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'nombre' => $e->nombre(),
                    'cargo' => $e->nombreCargo(),
                ])
                ->values()
                ->all();
        }

        return view('metas.index', [
            'quincena' => $quincena,
            'filas' => $filas,
            'puedeMarcar' => $puedeMarcar,
            'equiposPorSede' => $equiposPorSede,
            'sedesDisponibles' => $metas->sedesDisponibles(),
        ]);
    }

    public function store(Request $request, MetaQuincenaService $metas): JsonResponse
    {
        abort_unless($request->user()->canAccess('meta'), 403);

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'sede' => ['required', 'string', 'max:32'],
        ]);

        try {
            $meta = $metas->marcar((int) $data['producto_id'], $data['sede'], $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'No se pudo marcar la meta.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'status' => 'added',
            'message' => 'Producto marcado como meta de la quincena en '.$meta->sede.'.',
            'meta' => [
                'id' => $meta->id,
                'producto_id' => $meta->producto_id,
                'sede' => $meta->sede,
                'cantidad_inicial' => (float) $meta->cantidad_inicial,
            ],
        ]);
    }

    public function destroy(Request $request, MetaQuincenaService $metas): JsonResponse
    {
        abort_unless($request->user()->canAccess('meta'), 403);

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'sede' => ['required', 'string', 'max:32'],
        ]);

        $ok = $metas->desmarcar((int) $data['producto_id'], $data['sede']);

        return response()->json([
            'success' => true,
            'status' => $ok ? 'removed' : 'noop',
            'message' => $ok
                ? 'Producto quitado de la meta de la quincena.'
                : 'No había meta activa para ese producto y sede.',
        ]);
    }

    public function asignarResponsable(Request $request, MetaQuincenaProducto $meta, MetaQuincenaService $metas): JsonResponse
    {
        $user = $request->user();
        abort_unless($metas->puedeVerMetas($user), 403);

        if (! $user->canAccess('meta') && ! $user->isAdmin() && ! $user->isGerente()) {
            $sedes = $metas->sedesDelSupervisor($user);
            abort_unless(in_array(mb_strtoupper(trim($meta->sede), 'UTF-8'), $sedes, true), 403);
        }

        $data = $request->validate([
            'responsable_empleado_id' => ['nullable', 'integer'],
        ]);

        $actualizado = $metas->asignarResponsable(
            $meta,
            isset($data['responsable_empleado_id']) ? (int) $data['responsable_empleado_id'] : null,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Responsable actualizado.',
            'responsable_empleado_id' => $actualizado->responsable_empleado_id,
            'responsable_nombre' => $actualizado->responsable?->nombre(),
        ]);
    }

    public function sedesMeta(Request $request, MetaQuincenaService $metas): JsonResponse
    {
        abort_unless($request->user()->canAccess('meta'), 403);

        return response()->json([
            'success' => true,
            'quincena' => $metas->quincenaActual(),
            'sedes' => $metas->sedesDisponibles(),
            'por_producto' => $metas->sedesMetaPorProducto(),
        ]);
    }

    public function stockProducto(Request $request, int $producto, MetaQuincenaService $metas): JsonResponse
    {
        abort_unless($request->user()->canAccess('meta'), 403);

        $stock = $metas->stockPorSedes($producto);
        $activas = $metas->sedesMetaPorProducto()[$producto] ?? [];

        return response()->json([
            'success' => true,
            'producto_id' => $producto,
            'stock' => $stock,
            'sedes_meta' => array_values($activas),
        ]);
    }
}
