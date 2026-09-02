<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeSalesService
{
    /**
     * @return list<string>
     */
    public function claves(NominaEmpleado $empleado): array
    {
        $claves = [];

        if ($codigo = $empleado->codigoVendedor()) {
            $claves[] = $codigo;
        }

        if (Schema::hasTable('nomina_empleado_vendedores')) {
            $aliases = $empleado->vendedores()
                ->get(['nombre_normalizado', 'codigo_profit'])
                ->flatMap(fn ($alias) => [$alias->codigo_profit, $alias->nombre_normalizado]);
            foreach ($aliases as $alias) {
                if ($normalizado = NominaEmpleado::normalizarVendedor($alias)) {
                    $claves[] = $normalizado;
                }
            }
        }

        return array_values(array_unique(array_filter($claves)));
    }

    public function porcentajeDescuento(): float
    {
        return NominaConfig::getDecimal('descuento_venta_pct', 25);
    }

    /**
     * @return array{total:float, descuento:float, neto:float, descuento_pct:float, facturas:int, lineas:int, claves:list<string>}
     */
    public function resumen(NominaEmpleado $empleado, Carbon $inicio, Carbon $fin): array
    {
        $claves = $this->claves($empleado);
        $pct = $this->porcentajeDescuento();
        $vacio = [
            'total' => 0.0,
            'descuento' => 0.0,
            'neto' => 0.0,
            'descuento_pct' => $pct,
            'facturas' => 0,
            'lineas' => 0,
            'claves' => $claves,
        ];

        if ($claves === [] || ! Schema::hasTable('ventas_detalle')) {
            return $vacio;
        }

        $row = $this->baseQuery($claves)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->selectRaw('COUNT(*) as lineas')
            ->selectRaw("COUNT(DISTINCT sede || '-' || tipo_documento || '-' || numero_documento || '-' || fecha) as facturas")
            ->selectRaw($this->sumImporteSql().' as total')
            ->selectRaw($this->sumNetoSql($pct).' as neto')
            ->first();

        return $this->montosReales(
            (float) ($row->total ?? 0),
            (float) ($row->neto ?? 0),
            $pct
        ) + [
            'facturas' => (int) ($row->facturas ?? 0),
            'lineas' => (int) ($row->lineas ?? 0),
            'claves' => $claves,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function facturas(NominaEmpleado $empleado, Carbon $inicio, Carbon $fin): Collection
    {
        $claves = $this->claves($empleado);
        if ($claves === [] || ! Schema::hasTable('ventas_detalle')) {
            return collect();
        }

        $pct = $this->porcentajeDescuento();

        return $this->baseQuery($claves)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->select([
                'sede',
                'tipo_documento',
                'numero_documento',
                'fecha',
                'vendedor',
            ])
            ->selectRaw($this->sumImporteSql().' as total')
            ->selectRaw($this->sumNetoSql($pct).' as neto')
            ->selectRaw('COUNT(*) as lineas')
            ->selectRaw('MAX(cliente) as cliente')
            ->groupBy('sede', 'tipo_documento', 'numero_documento', 'fecha', 'vendedor')
            ->orderByDesc('fecha')
            ->orderByDesc('numero_documento')
            ->limit(300)
            ->get()
            ->map(function ($fac) use ($pct) {
                $montos = $this->montosReales((float) $fac->total, (float) $fac->neto, $pct);
                foreach ($montos as $key => $value) {
                    $fac->{$key} = $value;
                }

                return $fac;
            });
    }

    /**
     * @return array{factura:?object, lineas:Collection<int, object>}|null
     */
    public function detalle(
        NominaEmpleado $empleado,
        string $sede,
        string $tipo,
        string $numero,
        string $fecha
    ): ?array {
        $claves = $this->claves($empleado);
        if ($claves === [] || ! Schema::hasTable('ventas_detalle')) {
            return null;
        }

        $lineas = $this->baseQuery($claves)
            ->where('sede', $sede)
            ->where('tipo_documento', $tipo)
            ->where('numero_documento', $numero)
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->get();

        if ($lineas->isEmpty()) {
            return null;
        }

        $pct = $this->porcentajeDescuento();
        $bruto = 0.0;
        $neto = 0.0;
        $lineas = $lineas->map(function ($linea) use (&$bruto, &$neto, $pct) {
            $signo = $linea->tipo_documento === 'DEV' ? -1 : 1;
            $importe = round($signo * abs((float) $linea->cantidad * (float) $linea->precio_venta), 2);
            $importeNeto = isset($linea->precio_neto)
                ? round($signo * abs((float) $linea->cantidad * (float) $linea->precio_neto), 2)
                : round($importe * (1 - $pct / 100), 2);
            $linea->importe = $importe;
            $linea->importe_neto = $importeNeto;
            $bruto += $importe;
            $neto += $importeNeto;

            return $linea;
        });

        $primera = $lineas->first();
        $montos = $this->montosReales($bruto, $neto, $pct);

        return [
            'factura' => (object) ([
                'sede' => $primera->sede,
                'tipo_documento' => $primera->tipo_documento,
                'numero_documento' => $primera->numero_documento,
                'fecha' => $primera->fecha,
                'vendedor' => $primera->vendedor,
                'cliente' => $primera->cliente ?? null,
            ] + $montos),
            'lineas' => $lineas,
        ];
    }

    /**
     * @return array{total:float, descuento:float, neto:float, descuento_pct:float}
     */
    private function montosReales(float $bruto, float $neto, float $pctRespaldo): array
    {
        $bruto = round($bruto, 2);
        $neto = round($neto, 2);
        $descuento = round($bruto - $neto, 2);
        $porcentaje = abs($bruto) > 0
            ? round(abs($descuento / $bruto) * 100, 4)
            : $pctRespaldo;

        return [
            'total' => $bruto,
            'descuento' => $descuento,
            'neto' => $neto,
            'descuento_pct' => $porcentaje,
        ];
    }

    private function sumImporteSql(): string
    {
        return "COALESCE(SUM(CASE WHEN tipo_documento = 'DEV' THEN -ABS(cantidad * precio_venta) ELSE cantidad * precio_venta END), 0)";
    }

    private function sumNetoSql(float $pctRespaldo): string
    {
        $factor = round(1 - $pctRespaldo / 100, 6);

        return "COALESCE(SUM(CASE WHEN tipo_documento = 'DEV' "
            ."THEN -ABS(cantidad * COALESCE(precio_neto, precio_venta * {$factor})) "
            ."ELSE cantidad * COALESCE(precio_neto, precio_venta * {$factor}) END), 0)";
    }

    private function baseQuery(array $claves)
    {
        $placeholders = implode(',', array_fill(0, count($claves), '?'));

        $query = DB::table('ventas_detalle')
            ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$placeholders.')', $claves);

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('anulado', false);
        }

        return $query;
    }
}
