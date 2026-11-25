<?php

namespace App\Http\Controllers;

use App\Models\CuentaPorPagar;
use App\Models\CuentaPorPagarSuite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CuentaPorPagarController extends Controller
{
    public function index(): View
    {
        $cuentas_por_pagar = collect();
        if (Schema::hasTable('cuentas_por_pagar')) {
            $cuentas_por_pagar = CuentaPorPagar::query()
                ->with(['pagos' => function ($query) {
                    $query->orderBy('fecha')->orderBy('id');
                }])
                ->when(Schema::hasColumn('cuentas_por_pagar', 'origen'), function ($q) {
                    // Las FAC Suite se listan en el bloque JRZ (con botón Pagar).
                    $q->where(function ($inner) {
                        $inner->whereNull('origen')
                            ->orWhere('origen', '!=', CuentaPorPagarSuite::ORIGEN_CUENTA);
                    });
                })
                ->orderByRaw("CASE WHEN estado = 'abierta' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->get();
        }

        $cuentas_suite = collect();
        $suite_synced_at = null;
        if (Schema::hasTable('cuentas_por_pagar_suite')) {
            $cuentas_suite = CuentaPorPagarSuite::query()
                ->with([
                    'items',
                    'pagosSuite',
                    'cuentaPorPagar.pagos' => function ($query) {
                        $query->orderBy('fecha')->orderBy('id');
                    },
                ])
                ->where('sede', 'JRZ')
                ->where(function ($q) {
                    $q->where('saldo_usd', '>', 0.01)
                        ->orWhere('saldo_bs', '>', 0.01)
                        ->orWhereHas('cuentaPorPagar', function ($c) {
                            $c->where('estado', 'abierta')->where('saldo', '>', 0.01);
                        });
                })
                ->orderByDesc('saldo_usd')
                ->orderByDesc('fecha')
                ->orderByDesc('numero_documento')
                ->get();
            $suite_synced_at = $cuentas_suite->max('synced_at');
        }

        $proveedores = collect();
        try {
            $proveedores = DB::table('inventario_v2.productos')
                ->whereNotNull('proveedor')
                ->where('proveedor', '!=', '')
                ->distinct()
                ->orderBy('proveedor')
                ->pluck('proveedor');
        } catch (\Throwable) {
            // Catálogo de proveedores no disponible
        }

        $beneficiariosCxp = $cuentas_por_pagar
            ->pluck('beneficiario')
            ->filter(fn ($nombre) => filled($nombre))
            ->unique()
            ->values();

        $beneficiariosSuite = $cuentas_suite
            ->pluck('proveedor')
            ->filter(fn ($nombre) => filled($nombre))
            ->unique()
            ->values();

        $beneficiarios = $proveedores
            ->merge($beneficiariosCxp)
            ->merge($beneficiariosSuite)
            ->unique()
            ->sort()
            ->values();

        return view('finanzas.cuentas_por_pagar', [
            'cuentas_por_pagar' => $cuentas_por_pagar,
            'cuentas_suite' => $cuentas_suite,
            'suite_synced_at' => $suite_synced_at,
            'tiposGasto' => \App\Models\FinanzasCatalogo::tiposGasto(),
            'sedes' => config('inventario.sedes_locales', []),
            'beneficiarios' => $beneficiarios,
            'puedeEditar' => auth()->user()?->canAccess('finanzas.editar') && ! auth()->user()?->isAuditor(),
        ]);
    }

    /**
     * Abre una cuenta por pagar sin registrar egreso (solo el gasto pendiente).
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('cuentas_por_pagar')) {
            return back()->with('error', 'La tabla de cuentas por pagar no está disponible.');
        }

        if (! auth()->user()?->canAccess('finanzas.editar') || auth()->user()?->isAuditor()) {
            abort(403, 'No tienes permiso para abrir cuentas por pagar.');
        }

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'beneficiario' => ['required', 'string', 'max:255'],
            'tipo_gasto' => ['required', 'string', 'max:255'],
            'motivo' => ['nullable', 'string', 'max:1000'],
            'sede' => ['nullable', 'string', 'max:50'],
            'moneda' => ['required', 'in:USD,BS'],
            'monto_total' => ['required', 'numeric', 'min:0.01'],
        ], [
            'beneficiario.required' => 'Indica el beneficiario.',
            'tipo_gasto.required' => 'Selecciona el tipo de gasto.',
            'monto_total.required' => 'Indica el monto total del gasto.',
            'monto_total.min' => 'El monto total debe ser mayor a cero.',
        ]);

        $total = round((float) $data['monto_total'], 2);

        CuentaPorPagar::query()->create([
            'fecha' => $data['fecha'],
            'beneficiario' => trim($data['beneficiario']),
            'tipo_gasto' => $data['tipo_gasto'],
            'motivo' => trim((string) ($data['motivo'] ?? '')) ?: null,
            'sede' => $data['sede'] ?: null,
            'moneda' => $data['moneda'],
            'monto_total' => $total,
            'monto_pagado' => 0,
            'saldo' => $total,
            'estado' => 'abierta',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('finanzas.cuentas_por_pagar')
            ->with('success', 'Cuenta por pagar abierta. Aún no se generó ningún egreso; podrás pagarla desde Flujo de Caja.');
    }

    /**
     * Abre Flujo de Caja listo para pagar una FAC Suite JRZ.
     */
    public function pagarSuite(int $id): RedirectResponse
    {
        if (! Schema::hasTable('cuentas_por_pagar_suite')) {
            return back()->with('error', 'Las facturas Suite aún no están disponibles.');
        }

        if (! auth()->user()?->canAccess('finanzas.editar') || auth()->user()?->isAuditor()) {
            abort(403, 'No tienes permiso para pagar cuentas por pagar.');
        }

        $fac = CuentaPorPagarSuite::query()->findOrFail($id);
        $cuenta = $fac->ensureCuentaPorPagar(auth()->id());

        if (! $cuenta->estaAbierta()) {
            return redirect()
                ->route('finanzas.cuentas_por_pagar')
                ->with('error', 'Esa factura ya está saldada en la web.');
        }

        return redirect()->route('finanzas.flujo_caja', ['pagar_cuenta' => $cuenta->id]);
    }
}
