<?php

namespace App\Http\Controllers;

use App\Models\CuentaPorPagar;
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
                ->orderByRaw("CASE WHEN estado = 'abierta' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->get();
        }

        return view('finanzas.cuentas_por_pagar', compact('cuentas_por_pagar'));
    }
}
