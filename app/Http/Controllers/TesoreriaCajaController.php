<?php

namespace App\Http\Controllers;

use App\Models\TesoreriaCaja;
use App\Models\TesoreriaCajaMovimiento;
use App\Services\BcvRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TesoreriaCajaController extends Controller
{
    public function index(Request $request, BcvRateService $bcv): View
    {
        abort_unless(Schema::hasTable('tesoreria_cajas'), 503, 'Falta migrar las tablas de caja de tesorería.');

        $this->asegurarCajas();

        $cajaId = $request->filled('caja_id') ? (int) $request->query('caja_id') : null;
        $desde = $this->fecha($request->query('desde')) ?? now()->startOfMonth()->toDateString();
        $hasta = $this->fecha($request->query('hasta')) ?? now()->toDateString();
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $q = trim((string) $request->query('q', ''));

        $cajas = TesoreriaCaja::query()->orderBy('orden')->orderBy('id')->get();

        $base = TesoreriaCajaMovimiento::query()
            ->with(['caja', 'usuario:id,name'])
            ->when($cajaId, fn ($query) => $query->where('caja_id', $cajaId))
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($q !== '', function ($query) use ($q) {
                $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($w) use ($q, $like) {
                    $w->where('concepto', $like, '%'.$q.'%')
                        ->orWhere('referencia', $like, '%'.$q.'%');
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $egresos = (clone $base)->where('tipo', TesoreriaCajaMovimiento::EGRESO)->get();
        $ingresos = (clone $base)->where('tipo', TesoreriaCajaMovimiento::INGRESO)
            ->with(['detalles' => fn ($q) => $q->orderBy('sede')->orderBy('codigo_caja')])
            ->get();

        return view('tesoreria.caja', [
            'cajas' => $cajas,
            'egresos' => $egresos,
            'ingresos' => $ingresos,
            'totalesEgreso' => $egresos->groupBy('caja_id')->map(fn ($g) => round($g->sum('monto'), 2)),
            'totalesIngreso' => $ingresos->groupBy('caja_id')->map(fn ($g) => round($g->sum('monto'), 2)),
            'tasa_bcv' => (float) $bcv->getRateForToday(),
            'etiquetas' => config('inventario.display', []),
            'filtros' => [
                'caja_id' => $cajaId,
                'desde' => $desde,
                'hasta' => $hasta,
                'q' => $q,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('tesoreria_caja_movimientos'), 503);

        $data = $request->validate([
            'caja_id' => ['required', 'integer', 'exists:tesoreria_cajas,id'],
            'tipo' => ['required', 'in:ingreso,egreso'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'monto_usd' => ['nullable', 'numeric', 'min:0'],
            'monto_bs' => ['nullable', 'numeric', 'min:0'],
            'monto_divisa' => ['nullable', 'numeric', 'min:0'],
            'moneda_divisa' => ['nullable', 'in:USD,EUR'],
            'concepto' => ['required', 'string', 'min:3', 'max:300'],
            'referencia' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var TesoreriaCaja $cajaPreview */
        $cajaPreview = TesoreriaCaja::query()->findOrFail((int) $data['caja_id']);
        $monedaDivisa = null;
        if ($cajaPreview->esBolivares()) {
            $monto = $this->numero($request->input('monto_bs'));
            if ($monto < 0.01) {
                $monto = $this->numero($request->input('monto'));
            }
        } else {
            $monedaDivisa = strtoupper((string) ($data['moneda_divisa'] ?? TesoreriaCajaMovimiento::DIVISA_USD));
            if (! in_array($monedaDivisa, [TesoreriaCajaMovimiento::DIVISA_USD, TesoreriaCajaMovimiento::DIVISA_EUR], true)) {
                $monedaDivisa = TesoreriaCajaMovimiento::DIVISA_USD;
            }
            $monto = $this->numero($request->input('monto_divisa'));
            if ($monto < 0.01) {
                $monto = $this->numero($request->input('monto_usd'));
            }
            if ($monto < 0.01) {
                $monto = $this->numero($request->input('monto'));
            }
        }
        $monto = round($monto, 2);
        if ($monto < 0.01) {
            throw ValidationException::withMessages([
                'monto' => $cajaPreview->esBolivares()
                    ? 'Indica el Monto BS del movimiento.'
                    : 'Indica el monto en divisas.',
            ]);
        }

        DB::transaction(function () use ($data, $monto, $monedaDivisa, $request) {
            /** @var TesoreriaCaja $caja */
            $caja = TesoreriaCaja::query()->lockForUpdate()->findOrFail((int) $data['caja_id']);
            $delta = $data['tipo'] === TesoreriaCajaMovimiento::INGRESO ? $monto : -$monto;

            if (! $caja->esBolivares() && $monedaDivisa) {
                $saldos = $caja->saldosDivisa();
                $disponible = (float) ($saldos[$monedaDivisa] ?? 0);
                if ($data['tipo'] === TesoreriaCajaMovimiento::EGRESO && round($disponible - $monto, 2) < -0.009) {
                    $sim = $monedaDivisa === TesoreriaCajaMovimiento::DIVISA_EUR ? '€' : '$';
                    throw ValidationException::withMessages([
                        'monto' => 'El egreso dejaría '.$monedaDivisa.' en negativo (disponible '.$sim.number_format($disponible, 2, ',', '.').').',
                    ]);
                }
            } else {
                $nuevo = round((float) $caja->saldo + $delta, 2);
                if ($nuevo < -0.009) {
                    throw ValidationException::withMessages([
                        'monto' => 'El egreso dejaría la caja en negativo (saldo actual '.$caja->formatoSaldo().').',
                    ]);
                }
            }

            TesoreriaCajaMovimiento::query()->create([
                'caja_id' => $caja->id,
                'tipo' => $data['tipo'],
                'origen' => TesoreriaCajaMovimiento::ORIGEN_MANUAL,
                'fecha' => $data['fecha'],
                'monto' => $monto,
                'moneda_divisa' => $caja->esBolivares() ? null : $monedaDivisa,
                'concepto' => trim($data['concepto']),
                'referencia' => ($data['referencia'] ?? null) ? trim($data['referencia']) : null,
                'user_id' => $request->user()?->id,
            ]);
            $caja->update(['saldo' => round((float) $caja->saldo + $delta, 2)]);
        });

        $etiqueta = $data['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso';

        return redirect()->route('tesoreria.caja.index', array_filter([
            'desde' => $request->input('volver_desde'),
            'hasta' => $request->input('volver_hasta'),
        ]))->with('success', $etiqueta.' registrado.');
    }

    public function destroy(TesoreriaCajaMovimiento $movimiento): RedirectResponse
    {
        abort_unless(Schema::hasTable('tesoreria_caja_movimientos'), 503);

        if ($movimiento->esDeCuadre()) {
            return redirect()->route('tesoreria.caja.index')
                ->withErrors(['movimiento' => 'Ese ingreso viene del cuadre de caja. Reabre el cuadre para quitarlo.']);
        }

        DB::transaction(function () use ($movimiento) {
            /** @var TesoreriaCaja $caja */
            $caja = TesoreriaCaja::query()->lockForUpdate()->findOrFail($movimiento->caja_id);
            $delta = $movimiento->esIngreso() ? -$movimiento->monto : $movimiento->monto;
            $nuevo = round((float) $caja->saldo + $delta, 2);
            $movimiento->delete();
            $caja->update(['saldo' => $nuevo]);
        });

        return redirect()->route('tesoreria.caja.index')->with('success', 'Movimiento eliminado y saldo actualizado.');
    }

    private function asegurarCajas(): void
    {
        foreach ([
            ['codigo' => TesoreriaCaja::CODIGO_BS, 'nombre' => 'Caja Principal Bolívares', 'moneda' => 'VES', 'orden' => 1],
            ['codigo' => TesoreriaCaja::CODIGO_USD, 'nombre' => 'Caja Principal Divisas', 'moneda' => 'USD', 'orden' => 2],
        ] as $def) {
            TesoreriaCaja::query()->firstOrCreate(
                ['codigo' => $def['codigo']],
                $def + ['saldo' => 0]
            );
        }
    }

    private function fecha(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        return $valor;
    }

    private function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        if (is_string($valor)) {
            $valor = str_replace([' ', ','], ['', '.'], $valor);
        }

        return (float) $valor;
    }
}
