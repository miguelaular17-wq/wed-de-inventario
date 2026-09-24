<?php

namespace App\Http\Controllers;

use App\Support\VentaDescuento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MyDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $desde = $this->parseFecha($request->query('desde'), now()->startOfMonth()->toDateString());
        $hasta = $this->parseFecha($request->query('hasta'), now()->toDateString());

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $porSede = collect();
        $totales = [
            'facturas' => 0,
            'monto' => 0.0,
            'unidades' => 0.0,
        ];
        $descuentoPct = VentaDescuento::porcentaje();
        $factorNeto = VentaDescuento::factorNeto();

        if (Schema::hasTable('ventas_detalle')) {
            $anulado = Schema::hasColumn('ventas_detalle', 'anulado')
                ? 'AND COALESCE(vd.anulado, false) = false'
                : '';

            // Si precio_neto viene vacío o igual al bruto, aplicar descuento estándar.
            $precioLinea = Schema::hasColumn('ventas_detalle', 'precio_neto')
                ? "CASE
                        WHEN COALESCE(vd.precio_neto, 0) > 0
                             AND COALESCE(vd.precio_neto, 0) < COALESCE(vd.precio_venta, 0)
                        THEN vd.precio_neto
                        ELSE vd.precio_venta * {$factorNeto}
                   END"
                : "vd.precio_venta * {$factorNeto}";

            $rows = DB::select("
                SELECT
                    UPPER(TRIM(vd.sede)) AS sede,
                    COUNT(DISTINCT CASE
                        WHEN UPPER(vd.tipo_documento) = 'FAC'
                        THEN vd.sede || '|' || vd.numero_documento || '|' || vd.fecha::text
                    END) AS facturas,
                    ROUND(SUM(
                        CASE
                            WHEN UPPER(vd.tipo_documento) = 'DEV'
                                THEN -ABS(vd.cantidad)
                            ELSE ABS(vd.cantidad)
                        END
                    )::numeric, 2) AS unidades,
                    ROUND(SUM(
                        CASE
                            WHEN UPPER(vd.tipo_documento) = 'DEV'
                                THEN -ABS(vd.cantidad * ({$precioLinea}))
                            ELSE ABS(vd.cantidad * ({$precioLinea}))
                        END
                    )::numeric, 2) AS monto
                FROM ventas_detalle vd
                WHERE vd.fecha BETWEEN ? AND ?
                  AND UPPER(vd.tipo_documento) IN ('FAC', 'DEV')
                  AND UPPER(COALESCE(vd.nombre_producto, '')) LIKE '%MY DELIVERY%'
                  {$anulado}
                GROUP BY UPPER(TRIM(vd.sede))
                ORDER BY monto DESC, sede
            ", [$desde, $hasta]);

            $porSede = collect($rows)->map(function ($row) {
                return [
                    'sede' => (string) $row->sede,
                    'facturas' => (int) $row->facturas,
                    'unidades' => round((float) $row->unidades, 2),
                    'monto' => round((float) $row->monto, 2),
                ];
            });

            $totales = [
                'facturas' => (int) $porSede->sum('facturas'),
                'monto' => round((float) $porSede->sum('monto'), 2),
                'unidades' => round((float) $porSede->sum('unidades'), 2),
            ];
        }

        return view('finanzas.my_delivery', [
            'desde' => $desde,
            'hasta' => $hasta,
            'porSede' => $porSede,
            'totales' => $totales,
            'descuentoPct' => $descuentoPct,
        ]);
    }

    private function parseFecha(?string $raw, string $fallback): string
    {
        try {
            return $raw ? Carbon::parse($raw)->toDateString() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
