<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaComisionRegistro;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommissionCalculationService
{
    private const GASTO_SERVICIO_TECNICO = '058 - SERVICIO TECNICO (GARANTIAS)';

    public function __construct(
        private EmployeeSalesService $sales,
        private CommissionCategoryService $categorias,
    ) {
    }

    public function limpiarPeriodo(NominaPeriodo $periodo): void
    {
        if (Schema::hasTable('nomina_comision_registros')) {
            NominaComisionRegistro::query()->where('periodo_id', $periodo->id)->delete();
        }
    }

    /**
     * @return array{
     *     total:float,modo:string,base:float,gastos:float,lineas:int,
     *     base_telefonia:float,base_otros:float,comision_telefonia:float,comision_otros:float,
     *     pct_telefonia:float,pct_otros:float
     * }
     */
    public function calcular(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $vacio = $this->resultado($empleado, 0, 0, 0, 0);

        if (! Schema::hasTable('ventas_detalle') || $this->sedeExcluida($empleado)) {
            return $vacio;
        }

        return match ($empleado->modo_comision) {
            NominaEmpleado::COMISION_VENTAS_PROPIAS => $this->ventasPropias($periodo, $empleado),
            NominaEmpleado::COMISION_SUPERVISOR_SEDE => $this->supervisorSede($periodo, $empleado),
            NominaEmpleado::COMISION_SUPERVISOR_EQUIPO => $this->supervisorEquipo($periodo, $empleado),
            NominaEmpleado::COMISION_SERVICIO_TECNICO => $this->servicioTecnico($periodo, $empleado),
            default => $vacio,
        };
    }

    private function ventasPropias(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $claves = $this->sales->claves($empleado);
        if ($claves === []) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $pctTelefonia = NominaConfig::getDecimal('comision_telefonia_pct', 0.20);
        $pctOtros = NominaConfig::getDecimal('comision_otros_pct', 1);
        $baseTelefonia = 0.0;
        $baseOtros = 0.0;
        $comisionTelefonia = 0.0;
        $comisionOtros = 0.0;
        $lineasCalculadas = 0;

        foreach ($this->lineasVentas($periodo, $claves)->get() as $linea) {
            if ($this->codigoSedeExcluido($linea->sede ?? null)) {
                continue;
            }

            $base = $this->baseTotal($linea);
            $categoria = $linea->producto_categoria ?? null;
            $grupo = $this->categorias->grupo($categoria);
            $porcentaje = $grupo === CommissionCategoryService::TELEFONIA ? $pctTelefonia : $pctOtros;
            $comision = round($base * $porcentaje / 100, 2);

            if ($grupo === CommissionCategoryService::TELEFONIA) {
                $baseTelefonia += $base;
                $comisionTelefonia += $comision;
            } else {
                $baseOtros += $base;
                $comisionOtros += $comision;
            }

            $lineasCalculadas++;
            $this->registrarLinea($periodo, $empleado, $linea, $grupo, $base, $porcentaje, $comision);
        }

        return $this->resultado(
            $empleado,
            $comisionTelefonia + $comisionOtros,
            $baseTelefonia + $baseOtros,
            0,
            $lineasCalculadas,
            $baseTelefonia,
            $baseOtros,
            $comisionTelefonia,
            $comisionOtros,
            $pctTelefonia,
            $pctOtros
        );
    }

    private function supervisorSede(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $codigoSede = $empleado->sedeCatalogo?->codigo ?? $empleado->sede;
        if (! $codigoSede || $this->codigoSedeExcluido($codigoSede)) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $lineas = $this->lineasVentas($periodo)
            ->whereRaw('UPPER(TRIM(vd.sede)) = ?', [mb_strtoupper(trim($codigoSede), 'UTF-8')])
            ->get()
            ->reject(fn ($linea) => $this->codigoSedeExcluido($linea->sede ?? null))
            ->values();
        $base = round($lineas->sum(fn ($linea) => $this->baseTotal($linea)), 2);
        $porcentaje = NominaConfig::getDecimal('comision_supervisor_pct', 0.10);
        $total = round($base * $porcentaje / 100, 2);

        if ($lineas->isNotEmpty()) {
            $this->registrarAgregado($periodo, $empleado, 'SUPERVISOR_SEDE', $codigoSede, $base, $porcentaje, $total, [
                'lineas_venta' => $lineas->count(),
                'formula' => 'ventas_totales_sede * porcentaje_supervisor',
            ]);
        }

        return $this->resultado($empleado, $total, $base, 0, $lineas->count());
    }

    private function supervisorEquipo(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $subordinados = NominaEmpleado::query()
            ->with('vendedores')
            ->where('supervisor_id', $empleado->id)
            ->get();

        $claves = [];
        foreach ($subordinados as $subordinado) {
            $claves = array_merge($claves, $this->sales->claves($subordinado));
        }
        $claves = array_values(array_unique($claves));

        if ($claves === []) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $lineas = $this->lineasVentas($periodo, $claves)->get()
            ->reject(fn ($linea) => $this->codigoSedeExcluido($linea->sede ?? null))
            ->values();
        $base = round($lineas->sum(fn ($linea) => $this->baseTotal($linea)), 2);
        $porcentaje = NominaConfig::getDecimal('comision_marketing_pct', 0.10);
        $total = round($base * $porcentaje / 100, 2);

        if ($lineas->isNotEmpty()) {
            $this->registrarAgregado($periodo, $empleado, 'SUPERVISOR_EQUIPO', $empleado->sede, $base, $porcentaje, $total, [
                'lineas_venta' => $lineas->count(),
                'subordinados' => $subordinados->count(),
                'formula' => 'ventas_totales_equipo * porcentaje_marketing',
            ]);
        }

        return $this->resultado($empleado, $total, $base, 0, $lineas->count());
    }

    private function servicioTecnico(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        if (! $empleado->es_servicio_tecnico) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $claves = $this->sales->claves($empleado);
        $lineas = $claves === []
            ? collect()
            : $this->lineasVentas($periodo, $claves)->get()
                ->reject(fn ($linea) => $this->codigoSedeExcluido($linea->sede ?? null))
                ->values();
        $ventas = round($lineas->sum(fn ($linea) => $this->baseLinea($linea, 'NETO')), 2);
        $gastos = $this->gastosServicioTecnico($periodo, $empleado);
        $base = max(0, round($ventas - $gastos, 2));
        $porcentaje = NominaConfig::getDecimal('comision_servicio_tecnico_pct', 50);
        $total = round($base * $porcentaje / 100, 2);

        if ($lineas->isNotEmpty() || $gastos > 0) {
            $this->registrarAgregado($periodo, $empleado, 'SERVICIO_TECNICO', $empleado->sede, $base, $porcentaje, $total, [
                'ventas_usd' => $ventas,
                'egresos_058_usd' => $gastos,
                'lineas_venta' => $lineas->count(),
                'formula' => 'max(0, ventas - egresos_058_usd) * porcentaje_servicio_tecnico',
            ]);
        }

        return $this->resultado($empleado, $total, $base, $gastos, $lineas->count());
    }

    private function gastosServicioTecnico(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! Schema::hasTable('flujo_cajas') || ! Schema::hasColumn('flujo_cajas', 'nomina_empleado_id')) {
            return 0.0;
        }

        $row = DB::table('flujo_cajas')
            ->where('nomina_empleado_id', $empleado->id)
            ->where('tipo_gasto', self::GASTO_SERVICIO_TECNICO)
            ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE
                WHEN monto_usd IS NOT NULL AND monto_usd <> 0 THEN ABS(monto_usd)
                WHEN tasa_cambio IS NOT NULL AND tasa_cambio > 0 THEN ABS(monto_bs) / tasa_cambio
                ELSE 0 END), 0) AS total')
            ->first();

        return round((float) ($row->total ?? 0), 2);
    }

    private function lineasVentas(NominaPeriodo $periodo, array $claves = []): Builder
    {
        $query = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
            ->select('vd.*');

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('vd.anulado', false);
        }
        if ($claves !== []) {
            $placeholders = implode(',', array_fill(0, count($claves), '?'));
            $query->whereRaw('UPPER(TRIM(vd.vendedor)) IN ('.$placeholders.')', $claves);
        }
        if (Schema::hasTable('productos') && Schema::hasColumn('ventas_detalle', 'producto_id')) {
            $query->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id')
                ->addSelect('p.categoria as producto_categoria', 'p.subcategoria as producto_subcategoria');
        }

        return $query->orderBy('vd.id');
    }

    private function baseTotal(object $linea): float
    {
        return $this->baseLinea($linea, 'TOTAL');
    }

    private function baseLinea(object $linea, string $tipo): float
    {
        $signo = strtoupper((string) ($linea->tipo_documento ?? 'FAC')) === 'DEV' ? -1 : 1;
        $total = $signo * abs((float) ($linea->cantidad ?? 0) * (float) ($linea->precio_venta ?? 0));
        $netoReal = isset($linea->precio_neto)
            ? $signo * abs((float) ($linea->cantidad ?? 0) * (float) $linea->precio_neto)
            : null;

        return round(match ($tipo) {
            'MARGEN' => Schema::hasColumn('ventas_detalle', 'ganancia')
                ? $signo * abs((float) ($linea->ganancia ?? 0))
                : $total,
            'TOTAL' => $total,
            default => $netoReal ?? $total * (1 - $this->sales->porcentajeDescuento() / 100),
        }, 2);
    }

    private function registrarLinea(
        NominaPeriodo $periodo,
        NominaEmpleado $empleado,
        object $linea,
        string $grupo,
        float $base,
        float $porcentaje,
        float $comision
    ): void {
        if (! Schema::hasTable('nomina_comision_registros')) {
            return;
        }

        NominaComisionRegistro::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $empleado->id,
            'ventas_detalle_id' => $linea->id,
            'sede' => $linea->sede,
            'tipo_documento' => $linea->tipo_documento,
            'numero_documento' => $linea->numero_documento,
            'factura_origen' => $linea->factura_origen ?? null,
            'fecha' => $linea->fecha,
            'cliente' => $linea->cliente ?? null,
            'vendedor' => $linea->vendedor ?? null,
            'producto_id' => $linea->producto_id ?? null,
            'codigo_producto' => $linea->codigo_producto ?? null,
            'nombre_producto' => $linea->nombre_producto ?? null,
            'categoria' => $linea->producto_categoria ?? null,
            'subcategoria' => $linea->producto_subcategoria ?? null,
            'cantidad' => $linea->cantidad ?? 0,
            'precio_unitario' => $linea->precio_venta ?? 0,
            'base_monto' => $base,
            'base_tipo' => 'TOTAL',
            'porcentaje' => $porcentaje,
            'monto_comision' => $comision,
            'regla_id' => null,
            'regla_snapshot' => [
                'modo' => 'VENTAS_PROPIAS',
                'grupo' => $grupo,
            ],
            'origen' => strtoupper((string) $linea->tipo_documento) === 'DEV' ? 'DEVOLUCION' : 'CALCULO',
        ]);
    }

    private function registrarAgregado(
        NominaPeriodo $periodo,
        NominaEmpleado $empleado,
        string $modo,
        ?string $sede,
        float $base,
        float $porcentaje,
        float $total,
        array $detalle
    ): void {
        if (! Schema::hasTable('nomina_comision_registros')) {
            return;
        }

        NominaComisionRegistro::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $empleado->id,
            'ventas_detalle_id' => null,
            'sede' => $sede,
            'tipo_documento' => 'AJUSTE',
            'numero_documento' => 'PERIODO-'.$periodo->id,
            'fecha' => $periodo->fecha_fin,
            'cantidad' => 1,
            'precio_unitario' => $base,
            'base_monto' => $base,
            'base_tipo' => $modo === 'SERVICIO_TECNICO' ? 'NETO' : 'TOTAL',
            'porcentaje' => $porcentaje,
            'monto_comision' => $total,
            'regla_snapshot' => ['modo' => $modo] + $detalle,
            'origen' => 'CALCULO',
        ]);
    }

    private function sedeExcluida(NominaEmpleado $empleado): bool
    {
        return (bool) $empleado->sedeCatalogo?->excluir_comision;
    }

    private function codigoSedeExcluido(?string $codigo): bool
    {
        if (! $codigo || ! Schema::hasTable('nomina_sedes') || ! Schema::hasColumn('nomina_sedes', 'excluir_comision')) {
            return false;
        }

        return DB::table('nomina_sedes')
            ->whereRaw('UPPER(TRIM(codigo)) = ?', [mb_strtoupper(trim($codigo), 'UTF-8')])
            ->where('excluir_comision', true)
            ->exists();
    }

    private function resultado(
        NominaEmpleado $empleado,
        float $total,
        float $base,
        float $gastos,
        int $lineas,
        float $baseTelefonia = 0,
        float $baseOtros = 0,
        float $comisionTelefonia = 0,
        float $comisionOtros = 0,
        float $pctTelefonia = 0,
        float $pctOtros = 0
    ): array {
        return [
            'total' => round($total, 2),
            'modo' => $empleado->modo_comision ?? NominaEmpleado::COMISION_NINGUNA,
            'base' => round($base, 2),
            'gastos' => round($gastos, 2),
            'lineas' => $lineas,
            'base_telefonia' => round($baseTelefonia, 2),
            'base_otros' => round($baseOtros, 2),
            'comision_telefonia' => round($comisionTelefonia, 2),
            'comision_otros' => round($comisionOtros, 2),
            'pct_telefonia' => $pctTelefonia,
            'pct_otros' => $pctOtros,
        ];
    }
}
