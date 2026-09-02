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

    /** @var array<string, bool>|null */
    private ?array $flags = null;

    /** @var list<string>|null */
    private ?array $sedesExcluidas = null;

    /** @var list<array<string, mixed>> */
    private array $registrosPendientes = [];

    public function __construct(
        private EmployeeSalesService $sales,
        private CommissionCategoryService $categorias,
    ) {
    }

    public function limpiarPeriodo(NominaPeriodo $periodo): void
    {
        if ($this->flag('nomina_comision_registros')) {
            NominaComisionRegistro::query()->where('periodo_id', $periodo->id)->delete();
        }
    }

    /**
     * @return array{
     *     total:float,modo:string,base:float,gastos:float,lineas:int,
     *     base_telefonia:float,base_otros:float,comision_telefonia:float,comision_otros:float,
     *     pct_telefonia:float,pct_otros:float,
     *     ventas_st?:float,base_st?:float,comision_st?:float,lineas_st?:int,pct_st?:float
     * }
     */
    public function calcular(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $vacio = $this->resultado($empleado, 0, 0, 0, 0);

        if (! $this->flag('ventas_detalle') || $this->sedeExcluida($empleado)) {
            return $vacio;
        }

        try {
            return match ($empleado->modo_comision) {
                NominaEmpleado::COMISION_VENTAS_PROPIAS => $this->ventasPropias($periodo, $empleado),
                NominaEmpleado::COMISION_SUPERVISOR_SEDE => $this->supervisorSede($periodo, $empleado),
                NominaEmpleado::COMISION_SUPERVISOR_EQUIPO => $this->supervisorEquipo($periodo, $empleado),
                NominaEmpleado::COMISION_SERVICIO_TECNICO => $this->servicioTecnico($periodo, $empleado),
                default => $vacio,
            };
        } finally {
            $this->flushRegistros();
        }
    }

    private function ventasPropias(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $claves = $this->sales->claves($empleado);
        if ($claves === []) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $lineas = $this->lineasVentas($periodo, $claves)->get();

        $propias = $this->aplicarVentasPropias($periodo, $empleado, $lineas);
        if ($this->flag('ventas_documentos') && $this->flag('ventas_documentos_vendedor')) {
            $netoDocumentos = $this->ventaNetaVendedor($periodo, $claves);
            if ($netoDocumentos > 0) {
                $propias = $this->ajustarBasesAVentaDocumento($propias, $netoDocumentos);
            }
        }

        return $this->resultado(
            $empleado,
            $propias['comision_telefonia'] + $propias['comision_otros'],
            $propias['base_telefonia'] + $propias['base_otros'],
            0,
            $propias['lineas'],
            $propias['base_telefonia'],
            $propias['base_otros'],
            $propias['comision_telefonia'],
            $propias['comision_otros'],
            $propias['pct_telefonia'],
            $propias['pct_otros']
        );
    }

    private function supervisorSede(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $codigoSede = $empleado->sedeCatalogo?->codigo ?? $empleado->sede;
        if (! $codigoSede || $this->codigoSedeExcluido($codigoSede)) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $codigoSede = mb_strtoupper(trim($codigoSede), 'UTF-8');
        $lineas = $this->lineasVentas($periodo)
            ->whereRaw('UPPER(TRIM(vd.sede)) = ?', [$codigoSede])
            ->get();
        $base = $this->ventaNetaSede($periodo, $codigoSede, $lineas);
        $porcentaje = NominaConfig::getDecimal('comision_supervisor_pct', 0.05);
        $total = round($base * $porcentaje / 100, 2);

        if ($base > 0 || $lineas->isNotEmpty()) {
            $this->registrarAgregado($periodo, $empleado, 'SUPERVISOR_SEDE', $codigoSede, $base, $porcentaje, $total, [
                'lineas_venta' => $lineas->count(),
                'formula' => 'venta_neta_sede * porcentaje_supervisor',
                'fuente' => $this->flag('ventas_documentos') ? 'ventas_documentos' : 'ventas_detalle',
            ]);
        }

        return $this->resultado($empleado, $total, $base, 0, $lineas->count());
    }

    /**
     * Venta neta de la sede: misma lógica que el dashboard gerencial sin filtros.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $lineas
     */
    private function ventaNetaSede(NominaPeriodo $periodo, string $codigoSede, $lineas): float
    {
        if ($this->flag('ventas_documentos')) {
            $row = DB::table('ventas_documentos')
                ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
                ->whereRaw('UPPER(TRIM(sede)) = ?', [$codigoSede])
                ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
                ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as venta_neta")
                ->first();

            return round((float) ($row->venta_neta ?? 0), 2);
        }

        return round($lineas->sum(fn ($linea) => $this->baseVentaNeta($linea)), 2);
    }

    /**
     * Venta neta del vendedor según cabeceras de documento (misma fuente que Profit).
     *
     * @param  list<string>  $claves
     */
    private function ventaNetaVendedor(NominaPeriodo $periodo, array $claves): float
    {
        if ($claves === []) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, count($claves), '?'));
        $query = DB::table('ventas_documentos')
            ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
            ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$placeholders.')', $claves)
            ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'");

        $excluidas = $this->sedesExcluidas();
        if ($excluidas !== []) {
            $query->whereNotIn(DB::raw('UPPER(TRIM(sede))'), $excluidas);
        }

        $row = $query
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as venta_neta")
            ->first();

        return round((float) ($row->venta_neta ?? 0), 2);
    }

    /**
     * Si la suma de líneas no coincide con ventas_documentos, reparte la diferencia
     * proporcionalmente entre telefonía y otros (misma base que Profit).
     *
     * @param  array{
     *     base_telefonia:float,base_otros:float,comision_telefonia:float,comision_otros:float,
     *     pct_telefonia:float,pct_otros:float,lineas:int
     * }  $propias
     * @return array{
     *     base_telefonia:float,base_otros:float,comision_telefonia:float,comision_otros:float,
     *     pct_telefonia:float,pct_otros:float,lineas:int
     * }
     */
    private function ajustarBasesAVentaDocumento(array $propias, float $netoDocumentos): array
    {
        $netoLineas = round($propias['base_telefonia'] + $propias['base_otros'], 2);
        if ($netoLineas <= 0 || abs($netoDocumentos - $netoLineas) < 0.01) {
            return $propias;
        }

        $factor = $netoDocumentos / $netoLineas;
        $baseTelefonia = round($propias['base_telefonia'] * $factor, 2);
        $baseOtros = round($netoDocumentos - $baseTelefonia, 2);
        $pctTelefonia = $propias['pct_telefonia'];
        $pctOtros = $propias['pct_otros'];

        return [
            'base_telefonia' => $baseTelefonia,
            'base_otros' => $baseOtros,
            'comision_telefonia' => round($baseTelefonia * $pctTelefonia / 100, 2),
            'comision_otros' => round($baseOtros * $pctOtros / 100, 2),
            'pct_telefonia' => $pctTelefonia,
            'pct_otros' => $pctOtros,
            'lineas' => $propias['lineas'],
        ];
    }

    private function supervisorEquipo(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $idsEquipo = NominaEmpleado::query()
            ->where('supervisor_id', $empleado->id)
            ->pluck('id');

        if ($this->flag('nomina_empleado_supervisores')) {
            $idsEquipo = $idsEquipo->merge(
                DB::table('nomina_empleado_supervisores')
                    ->where('supervisor_id', $empleado->id)
                    ->pluck('empleado_id')
            )->unique()->values();
        }

        $subordinados = NominaEmpleado::query()
            ->with('vendedores')
            ->whereIn('id', $idsEquipo)
            ->get();

        $claves = [];
        foreach ($subordinados as $subordinado) {
            $claves = array_merge($claves, $this->sales->claves($subordinado));
        }
        $claves = array_values(array_unique($claves));

        if ($claves === []) {
            return $this->resultado($empleado, 0, 0, 0, 0);
        }

        $lineas = $this->lineasVentas($periodo, $claves)->get();
        $base = round($lineas->sum(fn ($linea) => $this->baseVentaNeta($linea)), 2);
        $porcentaje = NominaConfig::getDecimal('comision_marketing_pct', 0.10);
        $total = round($base * $porcentaje / 100, 2);

        if ($lineas->isNotEmpty()) {
            $this->registrarAgregado($periodo, $empleado, 'SUPERVISOR_EQUIPO', $empleado->sede, $base, $porcentaje, $total, [
                'lineas_venta' => $lineas->count(),
                'subordinados' => $subordinados->count(),
                'formula' => 'venta_neta_equipo * porcentaje_marketing',
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
            : $this->lineasVentas($periodo, $claves)->get();

        $lineasSt = $lineas->filter(fn ($linea) => $this->esLineaServicioTecnico($linea))->values();
        $lineasVenta = $lineas->reject(fn ($linea) => $this->esLineaServicioTecnico($linea))->values();

        $ventasSt = round($lineasSt->sum(fn ($linea) => $this->baseLinea($linea, 'NETO')), 2);
        $gastos = $this->gastosServicioTecnico($periodo, $empleado);
        $baseSt = max(0, round($ventasSt - $gastos, 2));
        $pctSt = NominaConfig::getDecimal('comision_servicio_tecnico_pct', 50);
        $comisionSt = round($baseSt * $pctSt / 100, 2);

        if ($lineasSt->isNotEmpty() || $gastos > 0) {
            $this->registrarAgregado($periodo, $empleado, 'SERVICIO_TECNICO', $empleado->sede, $baseSt, $pctSt, $comisionSt, [
                'ventas_usd' => $ventasSt,
                'egresos_058_usd' => $gastos,
                'lineas_venta' => $lineasSt->count(),
                'formula' => 'max(0, ventas_st - egresos_058_usd) * porcentaje_servicio_tecnico',
            ]);
        }

        $propias = $this->aplicarVentasPropias($periodo, $empleado, $lineasVenta);
        if ($this->flag('ventas_documentos') && $this->flag('ventas_documentos_vendedor') && $claves !== []) {
            $netoDocumentos = $this->ventaNetaVendedor($periodo, $claves);
            if ($netoDocumentos > 0) {
                $propias = $this->ajustarBasesAVentaDocumento($propias, $netoDocumentos);
            }
        }
        $total = round($comisionSt + $propias['comision_telefonia'] + $propias['comision_otros'], 2);
        $base = round($baseSt + $propias['base_telefonia'] + $propias['base_otros'], 2);
        $lineasCount = $lineasSt->count() + $propias['lineas'];

        return $this->resultado(
            $empleado,
            $total,
            $base,
            $gastos,
            $lineasCount,
            $propias['base_telefonia'],
            $propias['base_otros'],
            $propias['comision_telefonia'],
            $propias['comision_otros'],
            $propias['pct_telefonia'],
            $propias['pct_otros']
        ) + [
            'ventas_st' => $ventasSt,
            'base_st' => $baseSt,
            'comision_st' => $comisionSt,
            'lineas_st' => $lineasSt->count(),
            'pct_st' => $pctSt,
        ];
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @return array{
     *     base_telefonia:float,base_otros:float,comision_telefonia:float,comision_otros:float,
     *     pct_telefonia:float,pct_otros:float,lineas:int
     * }
     */
    private function aplicarVentasPropias(NominaPeriodo $periodo, NominaEmpleado $empleado, Collection $lineas): array
    {
        $pctTelefonia = NominaConfig::getDecimal('comision_telefonia_pct', 0.20);
        $pctOtros = NominaConfig::getDecimal('comision_otros_pct', 1);
        $baseTelefonia = 0.0;
        $baseOtros = 0.0;
        $comisionTelefonia = 0.0;
        $comisionOtros = 0.0;

        foreach ($lineas as $linea) {
            $base = $this->baseVentaNeta($linea);
            $grupo = $this->categorias->grupo($linea->producto_categoria ?? null);
            $porcentaje = $grupo === CommissionCategoryService::TELEFONIA ? $pctTelefonia : $pctOtros;
            $comision = round($base * $porcentaje / 100, 2);

            if ($grupo === CommissionCategoryService::TELEFONIA) {
                $baseTelefonia += $base;
                $comisionTelefonia += $comision;
            } else {
                $baseOtros += $base;
                $comisionOtros += $comision;
            }

            $this->registrarLinea($periodo, $empleado, $linea, $grupo, $base, $porcentaje, $comision);
        }

        return [
            'base_telefonia' => round($baseTelefonia, 2),
            'base_otros' => round($baseOtros, 2),
            'comision_telefonia' => round($comisionTelefonia, 2),
            'comision_otros' => round($comisionOtros, 2),
            'pct_telefonia' => $pctTelefonia,
            'pct_otros' => $pctOtros,
            'lineas' => $lineas->count(),
        ];
    }

    private function esLineaServicioTecnico(object $linea): bool
    {
        foreach (['nombre_producto', 'codigo_producto', 'producto_categoria', 'producto_subcategoria'] as $campo) {
            $normalizado = $this->categorias->normalizar($linea->{$campo} ?? null);
            if ($normalizado !== 'SIN CATEGORIA' && str_contains($normalizado, 'SERVICIO TECNICO')) {
                return true;
            }
        }

        return false;
    }

    private function gastosServicioTecnico(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! $this->flag('flujo_cajas_st')) {
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

        $excluidas = $this->sedesExcluidas();
        if ($excluidas !== []) {
            $query->whereNotIn(DB::raw('UPPER(TRIM(vd.sede))'), $excluidas);
        }
        if ($this->flag('ventas_anulado')) {
            $query->where('vd.anulado', false);
        }
        if ($claves !== []) {
            $placeholders = implode(',', array_fill(0, count($claves), '?'));
            $query->whereRaw('UPPER(TRIM(vd.vendedor)) IN ('.$placeholders.')', $claves);
        }
        if ($this->flag('productos') && $this->flag('ventas_producto_id')) {
            $query->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id')
                ->addSelect('p.categoria as producto_categoria', 'p.subcategoria as producto_subcategoria');
        }

        return $query->orderBy('vd.id');
    }

    /** Misma base que el dashboard gerencial: cantidad × precio_neto (o precio_venta si no hay neto). */
    private function baseVentaNeta(object $linea): float
    {
        $signo = strtoupper((string) ($linea->tipo_documento ?? 'FAC')) === 'DEV' ? -1 : 1;
        $cantidad = abs((float) ($linea->cantidad ?? 0));
        $precio = ($this->flag('ventas_precio_neto') && $linea->precio_neto !== null)
            ? (float) $linea->precio_neto
            : (float) ($linea->precio_venta ?? 0);

        return round($signo * $cantidad * $precio, 2);
    }

    private function baseLinea(object $linea, string $tipo): float
    {
        $signo = strtoupper((string) ($linea->tipo_documento ?? 'FAC')) === 'DEV' ? -1 : 1;
        $total = $signo * abs((float) ($linea->cantidad ?? 0) * (float) ($linea->precio_venta ?? 0));
        $netoReal = isset($linea->precio_neto)
            ? $signo * abs((float) ($linea->cantidad ?? 0) * (float) $linea->precio_neto)
            : null;

        return round(match ($tipo) {
            'MARGEN' => $this->flag('ventas_ganancia')
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
        if (! $this->flag('nomina_comision_registros')) {
            return;
        }

        $this->registrosPendientes[] = [
            'periodo_id' => $periodo->id,
            'empleado_id' => $empleado->id,
            'ventas_detalle_id' => $linea->id,
            'sede' => $linea->sede,
            'tipo_documento' => $linea->tipo_documento,
            'numero_documento' => $linea->numero_documento,
            'factura_origen' => $linea->factura_origen ?? null,
            'fecha' => $linea->fecha instanceof \DateTimeInterface
                ? $linea->fecha->format('Y-m-d')
                : $linea->fecha,
            'cliente' => $linea->cliente ?? null,
            'vendedor' => $linea->vendedor ?? null,
            'producto_id' => $linea->producto_id ?? null,
            'codigo_producto' => $linea->codigo_producto ?? null,
            'nombre_producto' => $linea->nombre_producto ?? null,
            'categoria' => $linea->producto_categoria ?? null,
            'subcategoria' => $linea->producto_subcategoria ?? null,
            'cantidad' => round((float) ($linea->cantidad ?? 0), 4),
            'precio_unitario' => ($this->flag('ventas_precio_neto') && $linea->precio_neto !== null)
                ? $linea->precio_neto
                : ($linea->precio_venta ?? 0),
            'base_monto' => $base,
            'base_tipo' => 'NETO',
            'porcentaje' => $porcentaje,
            'monto_comision' => $comision,
            'regla_id' => null,
            'regla_snapshot' => [
                'modo' => 'VENTAS_PROPIAS',
                'grupo' => $grupo,
            ],
            'origen' => strtoupper((string) $linea->tipo_documento) === 'DEV' ? 'DEVOLUCION' : 'CALCULO',
        ];

        if (count($this->registrosPendientes) >= 200) {
            $this->flushRegistros();
        }
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
        if (! $this->flag('nomina_comision_registros')) {
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
        if (! $codigo) {
            return false;
        }

        return in_array(mb_strtoupper(trim($codigo), 'UTF-8'), $this->sedesExcluidas(), true);
    }

    private function flag(string $key): bool
    {
        $this->flags ??= [
            'ventas_detalle' => Schema::hasTable('ventas_detalle'),
            'ventas_documentos' => Schema::hasTable('ventas_documentos'),
            'ventas_documentos_vendedor' => Schema::hasTable('ventas_documentos') && Schema::hasColumn('ventas_documentos', 'vendedor'),
            'ventas_anulado' => Schema::hasTable('ventas_detalle') && Schema::hasColumn('ventas_detalle', 'anulado'),
            'ventas_producto_id' => Schema::hasTable('ventas_detalle') && Schema::hasColumn('ventas_detalle', 'producto_id'),
            'ventas_ganancia' => Schema::hasTable('ventas_detalle') && Schema::hasColumn('ventas_detalle', 'ganancia'),
            'ventas_precio_neto' => Schema::hasTable('ventas_detalle') && Schema::hasColumn('ventas_detalle', 'precio_neto'),
            'productos' => Schema::hasTable('productos'),
            'nomina_comision_registros' => Schema::hasTable('nomina_comision_registros'),
            'nomina_empleado_supervisores' => Schema::hasTable('nomina_empleado_supervisores'),
            'flujo_cajas_st' => Schema::hasTable('flujo_cajas') && Schema::hasColumn('flujo_cajas', 'nomina_empleado_id'),
            'nomina_sedes_excluir' => Schema::hasTable('nomina_sedes') && Schema::hasColumn('nomina_sedes', 'excluir_comision'),
        ];

        return $this->flags[$key] ?? false;
    }

    /**
     * @return list<string>
     */
    private function sedesExcluidas(): array
    {
        if ($this->sedesExcluidas !== null) {
            return $this->sedesExcluidas;
        }
        if (! $this->flag('nomina_sedes_excluir')) {
            return $this->sedesExcluidas = [];
        }

        $this->sedesExcluidas = DB::table('nomina_sedes')
            ->where('excluir_comision', true)
            ->pluck('codigo')
            ->map(fn ($codigo) => mb_strtoupper(trim((string) $codigo), 'UTF-8'))
            ->filter()
            ->values()
            ->all();

        return $this->sedesExcluidas;
    }

    private function flushRegistros(): void
    {
        if ($this->registrosPendientes === []) {
            return;
        }

        $now = now();
        $filas = [];
        foreach ($this->registrosPendientes as $fila) {
            if (isset($fila['regla_snapshot']) && is_array($fila['regla_snapshot'])) {
                $fila['regla_snapshot'] = json_encode($fila['regla_snapshot']);
            }
            $fila['created_at'] = $now;
            $fila['updated_at'] = $now;
            $filas[] = $fila;
        }
        $this->registrosPendientes = [];

        foreach (array_chunk($filas, 200) as $lote) {
            NominaComisionRegistro::insert($lote);
        }
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
