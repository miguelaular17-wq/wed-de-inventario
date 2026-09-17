<?php

namespace App\Http\Controllers;

use App\Models\MetaQuincenaProducto;
use App\Services\MetaQuincenaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MetaQuincenaController extends Controller
{
    public function index(Request $request, MetaQuincenaService $metas): View
    {
        $user = $request->user();
        abort_unless($metas->puedeVerMetas($user), 403);

        $quincenas = $metas->quincenasParaSelector();
        $quincena = $metas->resolverQuincenaListado($request->query('inicio'));
        $filas = $metas->listarParaUsuario($user, $quincena['inicio']);
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
            'quincenas' => $quincenas,
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
            'total' => round(array_sum($stock), 2),
            'sedes_meta' => array_values($activas),
            'sedes_marcables' => $metas->sedesMarcables(),
            'sede_central' => mb_strtoupper(trim((string) config('inventario.sede_central', 'JRZ')), 'UTF-8'),
        ]);
    }

    public function reporteAvances(Request $request, MetaQuincenaService $metas): Response
    {
        $user = $request->user();
        abort_unless($metas->puedeVerMetas($user), 403);

        $quincena = $metas->resolverQuincenaListado($request->query('inicio'));
        $filas = $metas->listarParaUsuario($user, $quincena['inicio']);

        $sedeFiltro = mb_strtoupper(trim((string) $request->query('sede', '')), 'UTF-8');
        if ($sedeFiltro !== '') {
            if (! $user->canAccess('meta') && ! $user->isAdmin() && ! $user->isGerente()) {
                $permitidas = $metas->sedesDelSupervisor($user);
                abort_unless(in_array($sedeFiltro, $permitidas, true), 403);
            }
            $filas = $filas->where('sede', $sedeFiltro)->values();
        }

        $porSede = $filas
            ->groupBy('sede')
            ->sortKeys()
            ->map(function ($grupo, $sede) {
                $inicial = (float) $grupo->sum('cantidad_inicial');
                $vendido = (float) $grupo->sum('vendido');

                return [
                    'sede' => $sede,
                    'productos' => $grupo->sortBy('producto')->values(),
                    'totales' => [
                        'productos' => $grupo->count(),
                        'cantidad_inicial' => $inicial,
                        'cantidad_actual' => (float) $grupo->sum('cantidad_actual'),
                        'vendido' => $vendido,
                        'avance_pct' => $inicial > 0
                            ? round(min(100, ($vendido / $inicial) * 100), 1)
                            : ($vendido > 0 ? 100.0 : 0.0),
                    ],
                ];
            })
            ->values();

        $titulo = $sedeFiltro !== ''
            ? 'Avance productos meta · '.$sedeFiltro
            : 'Avance productos meta por sede';

        $pdf = Pdf::loadView('metas.pdf.avances', [
            'titulo' => $titulo,
            'quincena' => $quincena,
            'porSede' => $porSede,
            'sedeFiltro' => $sedeFiltro !== '' ? $sedeFiltro : null,
            'logoPath' => $this->logoMetasPdf(),
            'generadoPor' => $user->name,
        ])->setPaper('a4', 'portrait');

        $slugSede = $sedeFiltro !== ''
            ? preg_replace('/[^A-Za-z0-9]+/', '_', $sedeFiltro)
            : 'todas';
        $nombre = 'metas_avances_'.$slugSede.'_'.$quincena['inicio']->format('Ymd').'.pdf';

        return $pdf->download($nombre);
    }

    private function logoMetasPdf(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($raw);
    }
}
