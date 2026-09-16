<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaRegistro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollSedeAreaTotals
{
    /**
     * @return array{tipo: string, nombre: string, clave: string, etiqueta: string}
     */
    public function grupoDeEmpleado(?NominaEmpleado $empleado): array
    {
        $sede = $empleado?->sedeCatalogo;
        $esArea = $sede?->tipo === 'AREA';
        $tipo = $esArea ? 'AREA' : 'SEDE';
        $nombre = $sede?->nombre ?? (trim((string) ($empleado?->sede ?? '')) !== '' ? $empleado->sede : 'Sin sede / área');
        $clave = $tipo.'|'.mb_strtoupper((string) ($sede?->codigo ?? $nombre), 'UTF-8');

        return [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'clave' => $clave,
            'etiqueta' => $esArea ? 'Área' : 'Sede',
        ];
    }

    /**
     * @param  iterable<NominaRegistro>  $registros
     * @return Collection<int, array<string, mixed>>
     */
    public function deRegistros(iterable $registros, float $tasaBcv): Collection
    {
        $grupos = [];
        foreach ($registros as $registro) {
            $grupo = $this->grupoDeEmpleado($registro->empleado);
            $desglose = $registro->desglose();
            $asignaciones = round(
                (float) $registro->salario_base
                + (float) ($desglose['horas_extras'] ?? $registro->total_otros_ingresos ?? 0)
                + $registro->montoBonificaciones(),
                2
            );
            $deducciones = round((float) $registro->total_deducciones, 2);
            $pagarUsd = round((float) $registro->total_pagar, 2);
            $this->acumular($grupos, $grupo, $asignaciones, $deducciones, $pagarUsd, $tasaBcv);
        }

        return $this->ordenar($grupos);
    }

    /**
     * @param  iterable<NominaLiquidacionComision>  $liquidaciones
     * @return Collection<int, array<string, mixed>>
     */
    public function deLiquidaciones(iterable $liquidaciones, float $tasaBcv): Collection
    {
        $grupos = [];
        foreach ($liquidaciones as $liq) {
            $grupo = $this->grupoDeEmpleado($liq->empleado);
            $asignaciones = round((float) $liq->comision_total + (float) $liq->abonos, 2);
            $deducciones = round((float) $liq->retencion + (float) $liq->descuentos + (float) $liq->prestamos, 2);
            $pagarUsd = round((float) $liq->total_pagar, 2);
            $this->acumular($grupos, $grupo, $asignaciones, $deducciones, $pagarUsd, $tasaBcv);
        }

        return $this->ordenar($grupos);
    }

    /**
     * Totales con venta neta de la quincena. Omite grupos con venta neta <= 0.
     *
     * @param  Collection<int, array<string, mixed>>  $grupos
     * @return Collection<int, array<string, mixed>>
     */
    public function conVentasNetas(Collection $grupos, NominaPeriodo $periodo): Collection
    {
        $ventas = $this->ventasNetasPorCodigo($periodo);

        return $grupos
            ->map(function (array $grupo) use ($ventas) {
                $codigo = $this->codigoDeClave((string) $grupo['clave']);
                $ventaNeta = (float) ($ventas[$codigo] ?? 0);
                $pagarUsd = (float) ($grupo['pagar_usd'] ?? 0);
                $grupo['venta_neta'] = round($ventaNeta, 2);
                $grupo['pct_nomina_sobre_venta'] = $ventaNeta > 0
                    ? round(($pagarUsd / $ventaNeta) * 100, 2)
                    : null;

                return $grupo;
            })
            ->filter(fn (array $grupo) => (float) ($grupo['venta_neta'] ?? 0) > 0)
            ->values();
    }

    /**
     * @return array<string, float> codigo sede => venta neta USD
     */
    public function ventasNetasPorCodigo(NominaPeriodo $periodo): array
    {
        $inicio = $periodo->fecha_inicio?->toDateString();
        $fin = $periodo->fecha_fin?->toDateString();
        if (! $inicio || ! $fin) {
            return [];
        }

        if (Schema::hasTable('ventas_documentos')) {
            $rows = DB::table('ventas_documentos')
                ->whereBetween('fecha', [$inicio, $fin])
                ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
                ->selectRaw('UPPER(TRIM(sede)) as sede')
                ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as venta_neta")
                ->groupBy(DB::raw('UPPER(TRIM(sede))'))
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $sede = mb_strtoupper(trim((string) $row->sede), 'UTF-8');
                if ($sede === '') {
                    continue;
                }
                $out[$sede] = round((float) $row->venta_neta, 2);
            }

            return $out;
        }

        if (! Schema::hasTable('ventas_detalle')) {
            return [];
        }

        $hasNeto = Schema::hasColumn('ventas_detalle', 'total_neto_usd');
        $hasImporte = Schema::hasColumn('ventas_detalle', 'importe_usd');
        $campo = $hasNeto ? 'vd.total_neto_usd' : ($hasImporte ? 'vd.importe_usd' : '0');

        $rows = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$inicio, $fin])
            ->when(Schema::hasColumn('ventas_detalle', 'anulado'), function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('vd.anulado')->orWhere('vd.anulado', false);
                });
            })
            ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
            ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(COALESCE({$campo}, 0)) ELSE ABS(COALESCE({$campo}, 0)) END) as venta_neta")
            ->groupBy(DB::raw('UPPER(TRIM(vd.sede))'))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $sede = mb_strtoupper(trim((string) $row->sede), 'UTF-8');
            if ($sede === '') {
                continue;
            }
            $out[$sede] = round((float) $row->venta_neta, 2);
        }

        return $out;
    }

    private function codigoDeClave(string $clave): string
    {
        $partes = explode('|', $clave, 2);

        return mb_strtoupper(trim((string) ($partes[1] ?? $clave)), 'UTF-8');
    }

    /**
     * @param  array<string, array<string, mixed>>  $grupos
     * @param  array{tipo: string, nombre: string, clave: string, etiqueta: string}  $grupo
     */
    private function acumular(array &$grupos, array $grupo, float $asignaciones, float $deducciones, float $pagarUsd, float $tasaBcv): void
    {
        $clave = $grupo['clave'];
        if (! isset($grupos[$clave])) {
            $grupos[$clave] = [
                'clave' => $clave,
                'tipo' => $grupo['tipo'],
                'etiqueta' => $grupo['etiqueta'],
                'nombre' => $grupo['nombre'],
                'empleados' => 0,
                'asignaciones' => 0.0,
                'deducciones' => 0.0,
                'pagar_usd' => 0.0,
                'pagar_bs' => 0.0,
            ];
        }

        $grupos[$clave]['empleados']++;
        $grupos[$clave]['asignaciones'] = round($grupos[$clave]['asignaciones'] + $asignaciones, 2);
        $grupos[$clave]['deducciones'] = round($grupos[$clave]['deducciones'] + $deducciones, 2);
        $grupos[$clave]['pagar_usd'] = round($grupos[$clave]['pagar_usd'] + $pagarUsd, 2);
        $grupos[$clave]['pagar_bs'] = round($grupos[$clave]['pagar_usd'] * $tasaBcv, 2);
    }

    /**
     * @param  array<string, array<string, mixed>>  $grupos
     * @return Collection<int, array<string, mixed>>
     */
    private function ordenar(array $grupos): Collection
    {
        return collect(array_values($grupos))->sortBy([
            fn ($a, $b) => ($a['tipo'] === 'AREA' ? 1 : 0) <=> ($b['tipo'] === 'AREA' ? 1 : 0),
            fn ($a, $b) => strcmp(mb_strtoupper((string) $a['nombre'], 'UTF-8'), mb_strtoupper((string) $b['nombre'], 'UTF-8')),
        ])->values();
    }
}
