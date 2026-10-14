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

    public function show(string $modulo): View
    {
        $modulos = config('incentivos.modulos', []);
        abort_unless(isset($modulos[$modulo]), 404);

        return view('incentivos.show', [
            'clave' => $modulo,
            'modulo' => $modulos[$modulo],
            'modulos' => $modulos,
            'evaluacion' => $this->evaluacion->evaluar($modulo, $modulos[$modulo]),
            'cruce' => in_array($modulo, ['telefonia', 'arreglos'], true) ? $this->cruce->panel($modulo) : null,
        ]);
    }

    public function buscarCruce(Request $request, string $modulo): JsonResponse
    {
        abort_unless(in_array($modulo, ['telefonia', 'arreglos'], true), 404);

        return response()->json([
            'productos' => $this->cruce->buscarProductos($modulo, (string) $request->query('q', '')),
        ]);
    }

    public function guardarCruce(Request $request, string $modulo): JsonResponse
    {
        abort_unless(in_array($modulo, ['telefonia', 'arreglos'], true), 404);

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
