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

        $quincenas = $metas->quincenasParaSelector(null, $user);
        $quincena = $metas->resolverQuincenaListado($request->query('inicio'), null, $user);
        $filas = $metas->listarParaUsuario($user, $quincena['inicio']);
        $puedeMarcar = $user->canAccess('meta')
            && ($metas->veTodasLasSedes($user) || $metas->areaDelUsuario($user) !== null);
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
            'sedesDisponibles' => $metas->veTodasLasSedes($user)
                ? array_merge($metas->sedesDisponibles(), $metas->areasDisponibles())
                : $metas->sedesDelSupervisor($user),
            'soloSuSede' => ! $metas->veTodasLasSedes($user),
        ]);
    }

    public function store(Request $request, MetaQuincenaService $metas): JsonResponse
    {
        abort_unless($request->user()->canAccess('meta'), 403);

        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
            'sede' => ['required', 'string', 'max:32'],
            'cantidad' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ]);

        abort_unless($metas->puedeOperarSede($request->user(), $data['sede']), 403);

        try {
            $meta = $metas->marcar(
                (int) $data['producto_id'],
                $data['sede'],
                $request->user(),
                isset($data['cantidad']) ? (float) $data['cantidad'] : null
            );
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
            'cantidad' => (float) $meta->cantidad_inicial,
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

        abort_unless($metas->puedeOperarSede($request->user(), $data['sede']), 403);

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

        abort_unless($metas->puedeOperarSede($user, (string) $meta->sede), 403);

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
            'sedes' => array_values(array_intersect($metas->sedesDisponibles(), $metas->sedesOperables($request->user()))),
            'por_producto' => $metas->sedesMetaPorProducto(),
        ]);
    }

    public function stockProducto(Request $request, int $producto, MetaQuincenaService $metas): JsonResponse
    {
        abort_unless($request->user()->canAccess('meta'), 403);

        $stock = $metas->stockPorSedes($producto);
        $operables = $metas->sedesOperables($request->user());
        $activas = array_values(array_intersect($metas->sedesMetaPorProducto()[$producto] ?? [], $operables));

        return response()->json([
            'success' => true,
            'producto_id' => $producto,
            'stock' => $stock,
            'total' => round(array_sum($stock), 2),
            'sedes_meta' => array_values($activas),
            'sedes_marcables' => array_values(array_intersect($metas->sedesMarcables(), $operables)),
            'areas' => array_values(array_intersect($metas->areasDisponibles(), $operables)),
            'areas_cantidad' => $metas->cantidadesAreaDelProducto($producto),
            'sede_central' => mb_strtoupper(trim((string) config('inventario.sede_central', 'JRZ')), 'UTF-8'),
        ]);
    }

    public function reporteAvances(Request $request, MetaQuincenaService $metas): Response
    {
        $user = $request->user();
        abort_unless($metas->puedeVerMetas($user), 403);

        $quincena = $metas->resolverQuincenaListado($request->query('inicio'), null, $user);
        $filas = $metas->listarParaUsuario($user, $quincena['inicio']);

        $sedeFiltro = mb_strtoupper(trim((string) $request->query('sede', '')), 'UTF-8');
        if ($sedeFiltro !== '') {
            abort_unless($metas->puedeOperarSede($user, $sedeFiltro), 403);
            $filas = $filas->where('sede', $sedeFiltro)->values();
        }

        $soloCompletados = $request->boolean('completados');
        if ($soloCompletados) {
            $filas = $filas
                ->filter(fn ($fila) => (float) ($fila['avance_pct'] ?? 0) >= 100)
                ->values();
        }

        $porSede = $filas
            ->groupBy('sede')
            ->sortKeys()
            ->map(function ($grupo, $sede) {
                $inicial = (float) $grupo->sum('cantidad_inicial');
                $vendido = (float) $grupo->sum('vendido');

                return [
                    'sede' => $sede,
                    'productos' => $grupo->sortBy('producto')->values()->map(fn ($f) => (array) $f)->all(),
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
            ->values()
            ->all();

        $titulo = $soloCompletados
            ? ($sedeFiltro !== ''
                ? 'Productos meta completados · '.$sedeFiltro
                : 'Productos meta completados')
            : ($sedeFiltro !== ''
                ? 'Avance productos meta · '.$sedeFiltro
                : 'Avance productos meta por sede');

        $pdf = Pdf::loadView('metas.pdf.avances', [
            'titulo' => $titulo,
            'quincena' => $quincena,
            'porSede' => $porSede,
            'sedeFiltro' => $sedeFiltro !== '' ? $sedeFiltro : null,
            'soloCompletados' => $soloCompletados,
            'logoPath' => $this->logoMetasPdf(),
            'generadoPor' => $user->name,
        ])->setPaper('a4', 'portrait');

        $slugSede = $sedeFiltro !== ''
            ? preg_replace('/[^A-Za-z0-9]+/', '_', $sedeFiltro)
            : 'todas';
        $prefijo = $soloCompletados ? 'metas_completados_' : 'metas_avances_';
        $nombre = $prefijo.$slugSede.'_'.$quincena['inicio']->format('Ymd').'.pdf';

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
