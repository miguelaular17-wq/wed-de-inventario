<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaDiaLibre;
use App\Services\Nomina\DiasLibresService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiasLibresController extends Controller
{
    public function __construct(private DiasLibresService $diasLibres) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $esRrhh = $this->diasLibres->esRrhh($user);

        [$defaultDesde, $defaultHasta] = $this->diasLibres->quincenaActual();
        $desde = Carbon::parse($request->query('desde', $defaultDesde->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->query('hasta', $defaultHasta->toDateString()))->startOfDay();
        $fechas = $this->diasLibres->fechasDelRango($desde, $hasta);

        $sedeId = $esRrhh && $request->filled('sede_id')
            ? (int) $request->query('sede_id')
            : null;

        $empleados = $this->diasLibres->empleadosVisibles($user, $sedeId);
        $empleadoIds = $empleados->pluck('id')->map(fn ($id) => (int) $id)->all();
        $mapa = $this->diasLibres->mapaDias($empleadoIds, $desde, $hasta);

        $pendientes = $mapa->filter(fn (NominaDiaLibre $d) => $d->esPendiente())->count();
        $aprobados = $mapa->filter(fn (NominaDiaLibre $d) => $d->esAprobado())->count();

        return view('nomina.dias_libres.index', [
            'esRrhh' => $esRrhh,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'fechas' => $fechas,
            'empleados' => $empleados,
            'mapa' => $mapa,
            'sedeId' => $sedeId,
            'sedes' => $esRrhh ? $this->diasLibres->sedes() : collect(),
            'pendientes' => $pendientes,
            'aprobados' => $aprobados,
        ]);
    }

    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer'],
            'fecha' => ['required', 'date'],
        ]);

        $result = $this->diasLibres->toggle(
            $request->user(),
            (int) $data['empleado_id'],
            $data['fecha']
        );

        if ($request->expectsJson()) {
            if (is_array($result) && ($result['removed'] ?? false)) {
                return response()->json([
                    'ok' => true,
                    'libre' => false,
                    'estado' => null,
                ]);
            }

            /** @var NominaDiaLibre $result */
            return response()->json([
                'ok' => true,
                'libre' => true,
                'estado' => $result->estado,
            ]);
        }

        return back()->with('success', 'Día libre actualizado.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'celdas' => ['required', 'array'],
            'celdas.*.empleado_id' => ['required', 'integer'],
            'celdas.*.fecha' => ['required', 'date'],
            'celdas.*.libre' => ['required', 'boolean'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'sede_id' => ['nullable', 'integer'],
        ]);

        $stats = $this->diasLibres->syncCeldas($request->user(), $data['celdas']);

        return redirect()
            ->route('nomina.dias_libres.index', array_filter([
                'desde' => $data['desde'] ?? null,
                'hasta' => $data['hasta'] ?? null,
                'sede_id' => $data['sede_id'] ?? null,
            ]))
            ->with('success', "Guardado: {$stats['added']} marcados, {$stats['removed']} quitados.");
    }

    public function aprobar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date'],
            'sede_id' => ['nullable', 'integer'],
        ]);

        $n = $this->diasLibres->aprobarRango(
            $request->user(),
            Carbon::parse($data['desde'])->startOfDay(),
            Carbon::parse($data['hasta'])->startOfDay(),
            isset($data['sede_id']) ? (int) $data['sede_id'] : null
        );

        return redirect()
            ->route('nomina.dias_libres.index', array_filter([
                'desde' => $data['desde'],
                'hasta' => $data['hasta'],
                'sede_id' => $data['sede_id'] ?? null,
            ]))
            ->with('success', $n > 0
                ? "Se aprobaron {$n} día(s) libre(s)."
                : 'No había días pendientes en ese rango.');
    }
}
