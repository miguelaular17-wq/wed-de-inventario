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
        $cuadre->load(['pagos', 'conteos', 'lotes.ingreso', 'conciliador', 'reabridor']);
        $this->cuadres->completarNombres([$cuadre]);
        $resumen = $this->cuadres->resumen($cuadre);

        return view('cuadre_caja.show', $resumen + [
            'cuadre' => $cuadre,
            'etiquetas' => config('inventario.display', []),
            'motivos' => config('cuadre_caja.motivos_cierre', []),
            'bancosPos' => config('cuadre_caja.bancos_punto_venta', []),
            'titularesPos' => config('cuadre_caja.titulares_punto_venta', []),
            'puedeConciliar' => (bool) $request->user()?->canAccess('cuadre_caja.conciliar'),
            'volver' => $request->query('volver'),
        ]);
    }

    public function conciliar(Request $request, CuadreCaja $cuadre): RedirectResponse
    {
        $data = $request->validate([
            'accion' => ['required', 'in:procesar,cerrar'],
            'motivo' => ['nullable', 'required_if:accion,cerrar', 'in:'.implode(',', array_keys(config('cuadre_caja.motivos_cierre', [])))],
            'montos' => ['nullable', 'array'],
            'montos.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lotes' => ['nullable', 'array'],
            'lotes.*' => ['array'],
            'lotes.*.*.banco' => ['nullable', 'string', 'max:32'],
            'lotes.*.*.titular' => ['nullable', 'string', 'max:32'],
            'lotes.*.*.lote' => ['nullable', 'string', 'max:64'],
            'lotes.*.*.monto' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'observacion' => ['required', 'string', 'max:2000'],
        ], [
            'lotes.*.*.monto.numeric' => 'El monto de cada lote debe ser un número.',
            'lotes.*.*.lote.max' => 'El número de lote es demasiado largo.',
            'motivo.required_if' => 'Elige el motivo del cierre.',
            'motivo.in' => 'Elige un motivo de cierre de la lista.',
            'observacion.required' => 'Escribe una observación sobre el cierre de la caja.',
            'montos.*.numeric' => 'Los montos deben ser números.',
            'montos.*.min' => 'Los montos no pueden ser negativos.',
        ]);

        $cuadre->load(['pagos', 'conteos', 'lotes.ingreso']);
        $lotes = $data['lotes'] ?? [];
        try {
            if ($data['accion'] === 'cerrar') {
                $this->cuadres->cerrar($cuadre, $data['montos'] ?? null, $lotes, $data['motivo'], $data['observacion'], $request->user());
                $mensaje = 'Caja cerrada.';
            } else {
                $this->cuadres->procesar($cuadre, $data['montos'] ?? [], $lotes, $data['observacion'], $request->user());
                $mensaje = 'Caja procesada. Se puede reabrir si hay que corregirla.';
            }
        } catch (\DomainException $e) {
            return $this->alCuadre($request, $cuadre)->withInput()->with('error', $e->getMessage());
        }
        if (collect($lotes)->flatten(1)->filter(fn ($l) => trim((string) ($l['lote'] ?? '')) !== '')->isNotEmpty()) {
            $mensaje .= ' Los lotes de punto de venta quedaron registrados en Tesorería.';
        }

        return $this->alCuadre($request, $cuadre)->with('status', $mensaje);
    }

    public function reabrir(Request $request, CuadreCaja $cuadre): RedirectResponse
    {
        try {
            $this->cuadres->reabrir($cuadre, $request->user());
        } catch (\DomainException $e) {
            return $this->alCuadre($request, $cuadre)->with('error', $e->getMessage());
        }

        return $this->alCuadre($request, $cuadre)->with('status', 'Caja reabierta: ya se pueden corregir los montos.');
    }

    private function alCuadre(Request $request, CuadreCaja $cuadre): RedirectResponse
    {
        return redirect()->route('cuadre_caja.show', array_filter(['cuadre' => $cuadre->id, 'volver' => $request->input('volver')]));
    }

    private function fecha(mixed $valor, Carbon $defecto): Carbon
    {
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return Carbon::parse($valor)->startOfDay();
        }

        return $defecto->copy()->startOfDay();
    }
}
