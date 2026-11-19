<?php

namespace App\Http\Controllers;

use App\Models\FinanzasCatalogo;
use App\Models\FlujoCaja;
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
                $conTipoGasto = Schema::hasColumn('tesoreria_caja_movimientos', 'tipo_gasto');
                $query->where(function ($w) use ($q, $like, $conTipoGasto) {
                    $w->where('concepto', $like, '%'.$q.'%')
                        ->orWhere('referencia', $like, '%'.$q.'%')
                        ->when($conTipoGasto, fn ($x) => $x->orWhere('tipo_gasto', $like, '%'.$q.'%'));
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
            'tiposGasto' => FinanzasCatalogo::tiposGasto(),
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
            'tipo_gasto' => ['nullable', 'string', 'max:120'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'monto_usd' => ['nullable', 'numeric', 'min:0'],
            'monto_bs' => ['nullable', 'numeric', 'min:0'],
            'monto_divisa' => ['nullable', 'numeric', 'min:0'],
            'moneda_divisa' => ['nullable', 'in:USD,EUR'],
            'concepto' => ['required', 'string', 'min:3', 'max:300'],
            'referencia' => ['nullable', 'string', 'max:120'],
        ]);
        $data['tipo'] = $data['tipo'] === TesoreriaCajaMovimiento::INGRESO
            ? TesoreriaCajaMovimiento::INGRESO
            : TesoreriaCajaMovimiento::EGRESO;
        $data['categoria'] = TesoreriaCajaMovimiento::categoriaDesdeTipo($data['tipo']);
        $tipoGasto = trim((string) ($data['tipo_gasto'] ?? ''));
        if ($data['tipo'] === TesoreriaCajaMovimiento::EGRESO && $tipoGasto === '') {
            throw ValidationException::withMessages([
                'tipo_gasto' => 'Selecciona el tipo de gasto.',
            ]);
        }
        $data['tipo_gasto'] = $tipoGasto !== '' ? $tipoGasto : null;

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

            $payload = [
                'caja_id' => $caja->id,
                'tipo' => $data['tipo'],
                'origen' => TesoreriaCajaMovimiento::ORIGEN_MANUAL,
                'fecha' => $data['fecha'],
                'monto' => $monto,
                'moneda_divisa' => $caja->esBolivares() ? null : $monedaDivisa,
                'concepto' => trim($data['concepto']),
                'referencia' => ($data['referencia'] ?? null) ? trim($data['referencia']) : null,
                'user_id' => $request->user()?->id,
            ];
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'categoria')) {
                $payload['categoria'] = $data['categoria'];
            }
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'tipo_gasto')) {
                $payload['tipo_gasto'] = $data['tipo_gasto'];
            }
            $mov = TesoreriaCajaMovimiento::query()->create($payload);
            $caja->update(['saldo' => round((float) $caja->saldo + $delta, 2)]);
            $this->sincronizarEspejoFinanzas($mov->fresh(['caja']), app(BcvRateService::class));
        });

        $etiqueta = $data['tipo'] === TesoreriaCajaMovimiento::INGRESO ? 'Ingreso' : 'Egreso';

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
        if ($movimiento->esDeCobranza()) {
            return redirect()->route('tesoreria.caja.index')
                ->withErrors(['movimiento' => 'Ese ingreso viene de Cobranza (efectivo). Corrígelo o elimínalo desde Cobranza.']);
        }

        DB::transaction(function () use ($movimiento) {
            /** @var TesoreriaCaja $caja */
            $caja = TesoreriaCaja::query()->lockForUpdate()->findOrFail($movimiento->caja_id);
            $delta = $movimiento->esIngreso() ? -$movimiento->monto : $movimiento->monto;
            $nuevo = round((float) $caja->saldo + $delta, 2);
            $this->borrarEspejoFinanzas($movimiento);
            $movimiento->delete();
            $caja->update(['saldo' => $nuevo]);
        });

        return redirect()->route('tesoreria.caja.index')->with('success', 'Movimiento eliminado y saldo actualizado.');
    }

    /**
     * Egresos (e ingresos manuales) de Caja de Tesorería → copia visible en Flujo de Finanzas.
     */
    private function sincronizarEspejoFinanzas(TesoreriaCajaMovimiento $mov, BcvRateService $bcv): void
    {
        if (! Schema::hasTable('flujo_cajas') || ! Schema::hasColumn('flujo_cajas', 'origen')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_caja_movimientos', 'flujo_caja_id')) {
            return;
        }
        if ($mov->esDeCuadre()) {
            return;
        }

        $caja = $mov->caja;
        $tasa = max(0.0001, (float) $bcv->getRateForToday());
        $esBs = $caja?->esBolivares() ?? true;
        if ($esBs) {
            $montoBs = round((float) $mov->monto, 2);
            $montoUsd = round($montoBs / $tasa, 2);
        } else {
            $montoUsd = round((float) $mov->monto, 2);
            $montoBs = round($montoUsd * $tasa, 2);
        }

        $titular = $esBs ? 'BOLÍVARES' : 'DIVISAS';
        $datos = [
            'fecha' => $mov->fecha->toDateString(),
            'tipo' => $mov->tipo,
            'categoria_egreso' => $mov->esIngreso() ? 'ingresos' : 'egreso_realizado',
            'banco' => 'CAJA PRINCIPAL',
            'titular' => $titular,
            'categoria_cuenta' => 'TESORERÍA',
            'referencia' => $mov->referencia,
            'monto_usd' => $montoUsd,
            'tasa_cambio' => $tasa,
            'diferencial_cambiario' => 0,
            'monto_bs' => $montoBs,
            'comision' => 0,
            'motivo' => mb_substr('Tesorería · Caja · '.$mov->concepto, 0, 255),
            'oculto' => false,
            'origen' => FlujoCaja::ORIGEN_TESORERIA_CAJA,
        ];
        if (Schema::hasColumn('flujo_cajas', 'tipo_gasto')) {
            $datos['tipo_gasto'] = $mov->esIngreso() ? null : ($mov->tipo_gasto ?? null);
        }

        $espejo = $mov->flujo_caja_id ? FlujoCaja::query()->find($mov->flujo_caja_id) : null;
        if ($espejo) {
            $espejo->update($datos);
        } else {
            $espejo = FlujoCaja::query()->create($datos);
            $espejo->forceFill(['es_conciliado' => false])->save();
            $mov->forceFill(['flujo_caja_id' => $espejo->id])->saveQuietly();
        }
    }

    private function borrarEspejoFinanzas(TesoreriaCajaMovimiento $mov): void
    {
        if (! $mov->flujo_caja_id || ! Schema::hasTable('flujo_cajas')) {
            return;
        }
        FlujoCaja::query()
            ->whereKey($mov->flujo_caja_id)
            ->where('origen', FlujoCaja::ORIGEN_TESORERIA_CAJA)
            ->delete();
        $mov->forceFill(['flujo_caja_id' => null])->saveQuietly();
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
