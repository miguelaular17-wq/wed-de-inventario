<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MovimientoQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovimientoController extends Controller
{
    public function index(Request $request, MovimientoQueryService $movimientos): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'sede' => (string) $request->query('sede', ''),
            'tipo' => (string) $request->query('tipo', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];

        return view('admin.movimientos.index', [
            'rows' => $movimientos->list($filters),
            'filters' => $filters,
            'sedes' => config('inventario.sedes_stock'),
            'tipos' => config('inventario.tipos_movimiento'),
            'lastUpdatedAt' => $movimientos->lastUpdatedAt(),
        ]);
    }

    public function sync(Request $request, MovimientoQueryService $movimientos): \Illuminate\Http\JsonResponse
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'sede' => (string) $request->query('sede', ''),
            'tipo' => (string) $request->query('tipo', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];

        $since = (string) $request->query('since', '');
        $result = $movimientos->listSince($since, $filters);

        return response()->json([
            'updated_at' => $movimientos->lastUpdatedAt(),
            'rows' => $result['rows'],
            'removed' => $result['removed'],
        ]);
    }
}
