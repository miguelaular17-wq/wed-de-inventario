<?php

namespace App\Http\Controllers;

use App\Models\CuadreCaja;
use App\Services\CuadreCajaService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuadreCajaController extends Controller
{
    public function __construct(private CuadreCajaService $cuadres) {}

    public function index(Request $request): View
    {
        $desde = $this->fecha($request->query('desde'), now()->startOfMonth());
        $hasta = $this->fecha($request->query('hasta'), now());
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $sede = strtoupper(trim((string) $request->query('sede', '')));
        $cajera = trim((string) $request->query('cajera', ''));
        $estado = (string) $request->query('estado', '');
        if (! array_key_exists($estado, CuadreCajaService::ESTADOS)) {
            $estado = '';
        }

        return view('cuadre_caja.index', $this->cuadres->consolidado($desde, $hasta, $sede ?: null, $cajera, $estado ?: null) + [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'sede' => $sede,
            'cajera' => $cajera,
            'estado' => $estado,
            'estados' => CuadreCajaService::ESTADOS,
            'sedes' => config('inventario.sedes_gerencial', []),
            'etiquetas' => config('inventario.display', []),
            'puedeConciliar' => (bool) $request->user()?->canAccess('cuadre_caja.conciliar'),
        ]);
    }

    public function show(Request $request, CuadreCaja $cuadre): View
    {
        $cuadre->load(['pagos', 'conteos', 'conciliador']);
        $this->cuadres->completarNombres([$cuadre]);
        $resumen = $this->cuadres->resumen($cuadre);
        $enCierre = array_column($resumen['lineas'], 'clave');

        return view('cuadre_caja.show', $resumen + [
            'cuadre' => $cuadre,
            'formasExtra' => array_values(array_filter(
                $this->cuadres->formasDePago($cuadre->sede),
                fn (array $f) => ! in_array($f['clave'], $enCierre, true)
            )),
            'etiquetas' => config('inventario.display', []),
            'puedeConciliar' => (bool) $request->user()?->canAccess('cuadre_caja.conciliar'),
            'volver' => $request->query('volver'),
        ]);
    }

    public function conciliar(Request $request, CuadreCaja $cuadre): RedirectResponse
    {
        $data = $request->validate([
            'montos' => ['nullable', 'array'],
            'montos.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'montos.*.numeric' => 'Los montos deben ser números.',
            'montos.*.min' => 'Los montos no pueden ser negativos.',
        ]);

        $cuadre->load(['pagos', 'conteos']);
        $this->cuadres->conciliar($cuadre, $data['montos'] ?? [], $data['observacion'] ?? null, $request->user());

        return redirect()
            ->route('cuadre_caja.show', array_filter(['cuadre' => $cuadre->id, 'volver' => $request->input('volver')]))
            ->with('status', 'Cuadre guardado.');
    }

    private function fecha(mixed $valor, Carbon $defecto): Carbon
    {
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return Carbon::parse($valor)->startOfDay();
        }

        return $defecto->copy()->startOfDay();
    }
}
