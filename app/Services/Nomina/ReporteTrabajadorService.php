<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaComisionRegistro;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReporteTrabajadorService
{
    public function __construct(
        private EmployeeSalesService $sales,
        private CommissionCategoryService $categorias,
        private BcvRateService $bcv,
    ) {
    }

    public function liquidacion(NominaPeriodo $periodo, NominaEmpleado $empleado): ?NominaLiquidacionComision
    {
        return NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->where('empleado_id', $empleado->id)
            ->with(['empleado.cliente', 'empleado.empresa', 'empleado.sedeCatalogo', 'empleado.cargoCatalogo', 'periodo'])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(NominaLiquidacionComision $liq): array
    {
        $periodo = $liq->periodo;
        $empleado = $liq->empleado;
        $modo = (string) $liq->modo;
        $esSt = $liq->esServicioTecnico();
        $alcance = $this->alcance($liq);

        $documentos = $this->contarDocumentos($periodo, $alcance);
        $soloVenta = $esSt || $modo === NominaEmpleado::COMISION_MOVISTAR;
        $facturas = $soloVenta ? $documentos['FAC']['sin_st'] : $documentos['FAC']['total'];
        $devoluciones = $soloVenta ? $documentos['DEV']['sin_st'] : $documentos['DEV']['total'];

        $telefonia = round((float) $liq->base_telefonia, 2);
        $otros = round((float) $liq->base_otros, 2);
        $ventaTotal = $esSt
            ? round($liq->ventasSt() + $telefonia + $otros, 2)
            : $liq->totalVentas();

        $repartoEstimado = false;
        if ($alcance['tipo'] !== 'propio' && $telefonia == 0.0 && $otros == 0.0) {
            [$telefonia, $otros] = $this->repartoPorCategoria($periodo, $alcance, $ventaTotal);
            $repartoEstimado = true;
        }

        $deducciones = array_map(fn (array $l) => [
            'tipo' => $l['tipo'],
            'comentario' => $l['comentario'],
            'monto' => $l['monto'],
        ], $liq->lineasDescuento());
        $totalDeducciones = round((float) $liq->descuentos + (float) $liq->prestamos, 2);
        $diferencia = round($totalDeducciones - array_sum(array_column($deducciones, 'monto')), 2);
        if (abs($diferencia) >= 0.01) {
            $deducciones[] = ['tipo' => 'Descuentos / préstamos', 'comentario' => 'Sin detalle', 'monto' => $diferencia];
        }

        $tasa = $this->bcv->tasaParaPeriodo($periodo);
        $totalPagar = round((float) $liq->total_pagar, 2);

        return [
            'empleado' => [
                'nombre' => $empleado?->nombre() ?? 'Sin nombre',
                'cedula' => $empleado?->cedula() ?: '—',
                'cargo' => $empleado?->nombreCargo() ?? '—',
                'sede' => $empleado?->nombreSede() ?? '—',
                'empresa' => $empleado?->nombreEmpresa() ?? '—',
            ],
            'modo' => str_replace('−', '-', NominaEmpleado::modosComision()[$modo] ?? $modo),
            'alcance' => $alcance['etiqueta'],
            'es_st' => $esSt,
            'facturas' => $facturas,
            'devoluciones' => $devoluciones,
            'facturas_st' => $esSt ? $documentos['FAC']['con_st'] : 0,
            'venta_total' => $ventaTotal,
            'telefonia' => $telefonia,
            'otros' => $otros,
            'reparto_estimado' => $repartoEstimado,
            'st' => $esSt ? [
                'ventas' => $liq->ventasSt(),
                'egresos' => $liq->egresos058(),
                'base' => $liq->baseStNeta(),
                'comision' => $liq->comisionSt(),
            ] : null,
            'comision' => [
                'telefonia' => round((float) $liq->comision_telefonia, 2),
                'otros' => round((float) $liq->comision_otros, 2),
                'pct_telefonia' => (float) $liq->pct_telefonia,
                'pct_otros' => (float) $liq->pct_otros,
                'total' => round((float) $liq->comision_total, 2),
            ],
            'bonos' => round((float) $liq->abonos, 2),
            'retencion' => round((float) $liq->retencion, 2),
            'retencion_pct' => (float) $liq->retencion_pct,
            'exento_retencion' => (bool) $empleado?->exentoRetencionComision(),
            'deducciones' => $deducciones,
            'total_deducciones' => $totalDeducciones,
            'total_pagar' => $totalPagar,
            'tasa' => $tasa,
            'total_pagar_bs' => round($totalPagar * $tasa, 2),
        ];
    }

    /**
     * Qué ventas forman la base de la comisión: las propias, las de una o varias sedes, o las del equipo.
     *
     * @return array{tipo: string, etiqueta: string, claves: list<string>, sedes: list<string>}
     */
    private function alcance(NominaLiquidacionComision $liq): array
    {
        $empleado = $liq->empleado;
        $modo = (string) $liq->modo;

        if (in_array($modo, NominaEmpleado::modosComisionAgregadosSede(), true)) {
            $sedes = $this->sedesDeLiquidacion($liq);

            return [
                'tipo' => 'sede',
                'etiqueta' => (count($sedes) === 1 ? 'Venta de la sede ' : 'Venta de las sedes ').implode(', ', $sedes),
                'claves' => [],
                'sedes' => $sedes,
            ];
        }

        if (in_array($modo, NominaEmpleado::modosComisionAgregadosEquipo(), true)) {
            $subordinados = $this->subordinados($empleado);
            $claves = [];
            foreach ($subordinados as $subordinado) {
                $claves = array_merge($claves, $this->sales->claves($subordinado));
            }

            return [
                'tipo' => 'equipo',
                'etiqueta' => 'Venta de su equipo ('.$subordinados->count().' trabajadores)',
                'claves' => array_values(array_unique($claves)),
                'sedes' => [],
            ];
        }

        return [
            'tipo' => 'propio',
            'etiqueta' => 'Ventas propias',
            'claves' => $empleado ? $this->sales->claves($empleado) : [],
            'sedes' => [],
        ];
    }

    /** @return list<string> */
    private function sedesDeLiquidacion(NominaLiquidacionComision $liq): array
    {
        $registro = Schema::hasTable('nomina_comision_registros')
            ? NominaComisionRegistro::query()
                ->where('periodo_id', $liq->periodo_id)
                ->where('empleado_id', $liq->empleado_id)
                ->where('tipo_documento', 'AJUSTE')
                ->first()
            : null;

        $snapshot = $registro?->regla_snapshot;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }
        if (is_array($snapshot) && ! empty($snapshot['sedes']) && is_array($snapshot['sedes'])) {
            return array_values(array_map('strval', array_keys($snapshot['sedes'])));
        }
        if ($registro && $registro->sede && $registro->sede !== 'MULTI') {
            return [mb_strtoupper(trim($registro->sede), 'UTF-8')];
        }

        return match ($liq->modo) {
            NominaEmpleado::COMISION_COLABORADOR => NominaEmpleado::sedesComisionColaborador(),
            NominaEmpleado::COMISION_NUNES => ['NUNES'],
            default => array_values(array_filter([
                mb_strtoupper(trim((string) ($liq->empleado?->sedeCatalogo?->codigo ?? $liq->empleado?->sede)), 'UTF-8'),
            ])),
        };
    }

    private function subordinados(?NominaEmpleado $empleado)
    {
        if (! $empleado) {
            return collect();
        }

        $ids = NominaEmpleado::query()->where('supervisor_id', $empleado->id)->pluck('id');
        if (Schema::hasTable('nomina_empleado_supervisores')) {
            $ids = $ids->merge(
                DB::table('nomina_empleado_supervisores')->where('supervisor_id', $empleado->id)->pluck('empleado_id')
            )->unique()->values();
        }

        return NominaEmpleado::query()->with('vendedores')->whereIn('id', $ids)->get();
    }

    /**
     * @param  array{tipo: string, claves: list<string>, sedes: list<string>}  $alcance
     * @return array{FAC: array{total:int, sin_st:int, con_st:int}, DEV: array{total:int, sin_st:int, con_st:int}}
     */
    private function contarDocumentos(NominaPeriodo $periodo, array $alcance): array
    {
        $vacio = ['total' => 0, 'sin_st' => 0, 'con_st' => 0];
        $out = ['FAC' => $vacio, 'DEV' => $vacio];

        $query = $this->lineas($periodo, $alcance);
        if (! $query) {
            return $out;
        }

        $doc = "vd.sede || '|' || vd.numero_documento";
        $esSt = $this->sqlEsServicioTecnico();
        $rows = $query
            ->selectRaw("CASE WHEN UPPER(vd.tipo_documento) = 'DEV' THEN 'DEV' ELSE 'FAC' END as tipo")
            ->selectRaw("COUNT(DISTINCT {$doc}) as total")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$esSt} THEN NULL ELSE {$doc} END) as sin_st")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$esSt} THEN {$doc} END) as con_st")
            ->groupByRaw("CASE WHEN UPPER(vd.tipo_documento) = 'DEV' THEN 'DEV' ELSE 'FAC' END")
            ->get();

        foreach ($rows as $row) {
            $out[$row->tipo] = [
                'total' => (int) $row->total,
                'sin_st' => (int) $row->sin_st,
                'con_st' => (int) $row->con_st,
            ];
        }

        return $out;
    }

    /**
     * Para supervisores la liquidación no separa telefonía y otros: se reparte su venta
     * según la categoría de los productos vendidos en su sede o equipo.
     *
     * @param  array{tipo: string, claves: list<string>, sedes: list<string>}  $alcance
     * @return array{0: float, 1: float}
     */
    private function repartoPorCategoria(NominaPeriodo $periodo, array $alcance, float $ventaTotal): array
    {
        $query = $this->lineas($periodo, $alcance);
        if (! $query || ! Schema::hasTable('productos') || ! Schema::hasColumn('ventas_detalle', 'producto_id')) {
            return [0.0, round($ventaTotal, 2)];
        }

        $precio = Schema::hasColumn('ventas_detalle', 'precio_neto')
            ? 'COALESCE(vd.precio_neto, vd.precio_venta, 0)'
            : 'COALESCE(vd.precio_venta, 0)';
        $rows = $query
            ->selectRaw('p.categoria as categoria')
            ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento) = 'DEV' THEN -ABS(vd.cantidad) ELSE ABS(vd.cantidad) END * {$precio}) as neto")
            ->groupBy('p.categoria')
            ->get();

        $telefonia = 0.0;
        $otros = 0.0;
        foreach ($rows as $row) {
            if ($this->categorias->esTelefonia($row->categoria)) {
                $telefonia += (float) $row->neto;
            } else {
                $otros += (float) $row->neto;
            }
        }

        $sumaLineas = $telefonia + $otros;
        if ($ventaTotal > 0 && $sumaLineas > 0) {
            $telefonia = round($telefonia * $ventaTotal / $sumaLineas, 2);

            return [$telefonia, round($ventaTotal - $telefonia, 2)];
        }

        return [round($telefonia, 2), round($otros, 2)];
    }

    /**
     * @param  array{tipo: string, claves: list<string>, sedes: list<string>}  $alcance
     */
    private function lineas(NominaPeriodo $periodo, array $alcance): ?Builder
    {
        if (! Schema::hasTable('ventas_detalle')) {
            return null;
        }
        if ($alcance['tipo'] === 'sede' ? $alcance['sedes'] === [] : $alcance['claves'] === []) {
            return null;
        }

        $query = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()]);

        if (Schema::hasTable('productos') && Schema::hasColumn('ventas_detalle', 'producto_id')) {
            $query->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id');
        }
        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('vd.anulado', false);
        }

        $excluidas = $this->sedesExcluidas();
        if ($excluidas !== []) {
            $query->whereNotIn(DB::raw('UPPER(TRIM(vd.sede))'), $excluidas);
        }

        $valores = $alcance['tipo'] === 'sede' ? $alcance['sedes'] : $alcance['claves'];
        $columna = $alcance['tipo'] === 'sede' ? 'vd.sede' : 'vd.vendedor';
        $query->whereRaw(
            'UPPER(TRIM('.$columna.')) IN ('.implode(',', array_fill(0, count($valores), '?')).')',
            $valores
        );

        return $query;
    }

    private function sqlEsServicioTecnico(): string
    {
        $campos = ["COALESCE(vd.nombre_producto, '')", "COALESCE(vd.codigo_producto, '')"];
        if (Schema::hasTable('productos') && Schema::hasColumn('ventas_detalle', 'producto_id')) {
            $campos[] = "COALESCE(p.categoria, '')";
            $campos[] = "COALESCE(p.subcategoria, '')";
        }
        $texto = 'UPPER('.implode(" || ' ' || ", $campos).')';

        return "({$texto} LIKE '%SERVICIO TECNICO%' OR {$texto} LIKE '%SERVICIO TÉCNICO%')";
    }

    /** @return list<string> */
    private function sedesExcluidas(): array
    {
        if (! Schema::hasTable('nomina_sedes') || ! Schema::hasColumn('nomina_sedes', 'excluir_comision')) {
            return [];
        }

        return DB::table('nomina_sedes')
            ->where('excluir_comision', true)
            ->pluck('codigo')
            ->map(fn ($codigo) => mb_strtoupper(trim((string) $codigo), 'UTF-8'))
            ->filter()
            ->values()
            ->all();
    }
}
