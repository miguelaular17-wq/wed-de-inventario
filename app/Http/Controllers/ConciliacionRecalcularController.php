<?php

namespace App\Http\Controllers;

use App\Models\ConciliacionLinea;
use App\Services\BankReconciliationMatcher;
use App\Services\Conciliacion\ComisionPagoMovilBanesco;
use App\Services\Conciliacion\ConciliacionAutoMatcher;
use App\Services\Conciliacion\LotesPuntoVentaAutoMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * «Recalcular» de una cuenta en Conciliaciones: vuelve a pasar el emparejamiento automático
 * sobre todo lo pendiente de esa cuenta en el período (lo mismo que corre al subir un extracto).
 */
class ConciliacionRecalcularController extends Controller
{
    public function __invoke(
        Request $request,
        BankReconciliationMatcher $matcher,
        ConciliacionAutoMatcher $autoMatcher,
        LotesPuntoVentaAutoMatcher $lotes,
        ComisionPagoMovilBanesco $comisiones,
    ) {
        $data = $request->validate([
            'banco' => ['required', 'string', 'max:120'],
            'titular' => ['required', 'string', 'max:120'],
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
        ]);
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $desde = \Carbon\Carbon::parse($data['fecha_desde'])->toDateString();
        $hasta = \Carbon\Carbon::parse($data['fecha_hasta'])->toDateString();
        $cuenta = $matcher->claveCuenta($data['banco'], $data['titular']);
        $variantes = array_map('strtolower', $matcher->variantesBanco($data['banco']));

        $nComisiones = $comisiones->marcar($desde, $hasta);

        $pendientes = fn () => ConciliacionLinea::query()
            ->where(function ($q) use ($variantes) {
                foreach ($variantes as $v) {
                    $q->orWhereRaw('LOWER(TRIM(banco)) = ?', [$v]);
                }
            })
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'pendiente')
            ->whereNull('flujo_caja_id')
            ->whereNull('tesoreria_ingreso_id')
            ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'));
        $antes = $pendientes()->get(['id', 'banco', 'titular'])
            ->filter(fn ($l) => $matcher->claveCuenta($l->banco, $l->titular) === $cuenta)->count();

        // Dos pasadas, como al importar: la segunda recoge el otro lado de traslados que quedó libre.
        $conciliados = 0;
        for ($pasada = 0; $pasada < 2; $pasada++) {
            $enPasada = 0;
            $pendientes()->chunkById(2000, function ($lineas) use ($autoMatcher, $matcher, $cuenta, $desde, $hasta, $data, &$enPasada) {
                $deLaCuenta = $lineas->filter(fn ($l) => $matcher->claveCuenta($l->banco, $l->titular) === $cuenta)->values();
                if ($deLaCuenta->isNotEmpty()) {
                    $enPasada += $autoMatcher->conciliar($deLaCuenta, $desde, $hasta, $data['banco'], $data['titular'], $deLaCuenta->count());
                }
            });
            $conciliados += $enPasada;
            if ($enPasada === 0) {
                break;
            }
        }
        $nLotes = $lotes->conciliar($desde, $hasta, $data['banco'], $data['titular']);

        $despues = $pendientes()->get(['id', 'banco', 'titular'])
            ->filter(fn ($l) => $matcher->claveCuenta($l->banco, $l->titular) === $cuenta)->count();

        $partes = [];
        if ($conciliados > 0) {
            $partes[] = $conciliados.' movimientos conciliados con egresos/ingresos';
        }
        if ($nLotes > 0) {
            $partes[] = $nLotes.' lotes de punto de venta';
        }
        if ($nComisiones > 0) {
            $partes[] = $nComisiones.' cargos pasados a comisiones';
        }
        $titulo = strtoupper($data['banco']).' · '.strtoupper($data['titular']);
        $mensaje = $partes === []
            ? "Recalculado {$titulo}: no apareció ninguna coincidencia nueva. Quedan {$despues} movimientos pendientes."
            : "Recalculado {$titulo}: ".implode(', ', $partes).". Pendientes: {$antes} → {$despues}.";

        return redirect()->route('finanzas.conciliaciones', array_filter([
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'banco_filtro' => $request->input('banco_filtro') ?: $data['banco'],
            'titular_filtro' => $request->input('titular_filtro'),
        ]))->with('success', $mensaje);
    }
}
