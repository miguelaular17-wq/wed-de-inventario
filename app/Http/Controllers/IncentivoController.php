<?php

namespace App\Http\Controllers;

use App\Services\IncentivoCruceService;
use App\Services\IncentivoEvaluacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncentivoController extends Controller
{
    public function __construct(
        private IncentivoEvaluacionService $evaluacion,
        private IncentivoCruceService $cruce,
    ) {}

    public function show(Request $request, string $modulo): View
    {
        $modulos = config('incentivos.modulos', []);
        abort_unless(isset($modulos[$modulo]), 404);
        $user = $request->user();
        $permitidos = array_filter(
            $modulos,
            fn (array $m) => $user->canAccess('incentivos') || (! empty($m['permiso']) && $user->canAccess($m['permiso']))
        );
        abort_unless(isset($permitidos[$modulo]), 403);
        $periodo = $this->evaluacion->periodo($request);

        return view('incentivos.show', [
            'clave' => $modulo,
            'modulo' => $modulos[$modulo],
            'modulos' => $permitidos,
            'periodo' => $periodo,
            'evaluacion' => $this->evaluacion->evaluar($modulo, $modulos[$modulo], $periodo['inicio'], $periodo['fin']),
            'cruce' => in_array($modulo, ['telefonia', 'arreglos'], true) ? $this->cruce->panel($modulo) : null,
        ]);
    }

    public function buscarCruce(Request $request, string $modulo): JsonResponse
    {
        abort_unless(in_array($modulo, ['telefonia', 'arreglos'], true), 404);
        abort_unless($request->user()->canAccess('incentivos'), 403);

        return response()->json([
            'productos' => $this->cruce->buscarProductos($modulo, (string) $request->query('q', '')),
        ]);
    }

    public function guardarCruce(Request $request, string $modulo): JsonResponse
    {
        abort_unless(in_array($modulo, ['telefonia', 'arreglos'], true), 404);
        abort_unless($request->user()->canAccess('incentivos'), 403);

        $data = $request->validate([
            'tipo' => ['required', 'in:categoria,producto'],
            'clave' => ['required', 'string', 'max:200'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        $item = $this->cruce->guardar($modulo, $data);

        return response()->json([
            'clave' => $item->clave,
            'nombre' => $item->nombre,
            'activo' => $item->activo,
            'etiqueta' => $item->activo ? 'Activo' : 'Desactivado',
        ]);
    }
}
