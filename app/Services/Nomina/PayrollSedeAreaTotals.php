<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaRegistro;
use App\Services\GerencialDashboardService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollSedeAreaTotals
{
    /** Áreas que se muestran aparte; el resto de áreas van a Administración. */
    public const AREA_CALL_CENTER = 'Call Center';

    public const AREA_DIGITAL_MANAGE = 'Digital Manage';

    public const AREA_ADMINISTRACION = 'Administración';

    /**
     * @return array{tipo: string, nombre: string, clave: string, etiqueta: string}
     */
    public function grupoDeEmpleado(?NominaEmpleado $empleado): array
    {
        $sede = $empleado?->sedeCatalogo;
        $esArea = $sede?->tipo === 'AREA';
        $tipo = $esArea ? 'AREA' : 'SEDE';
        $nombre = $sede?->nombre ?? (trim((string) ($empleado?->sede ?? '')) !== '' ? $empleado->sede : 'Sin sede / área');

        // Sin catálogo: si el texto de sede es un área conocida, tratarla como área.
        if (! $esArea && $this->pareceArea($nombre)) {
            $esArea = true;
            $tipo = 'AREA';
        }

        if ($esArea) {
            $nombre = $this->nombreAreaConsolidada(
                (string) ($sede?->codigo ?? $nombre),
                (string) ($sede?->nombre ?? $nombre)
            );
        }

        $clave = $tipo.'|'.mb_strtoupper((string) ($esArea ? $nombre : ($sede?->codigo ?? $nombre)), 'UTF-8');

        return [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'clave' => $clave,
            'etiqueta' => $esArea ? 'Área' : 'Sede',
        ];
    }

    /**
     * Call Center y Digital Manage quedan aparte; cualquier otra área → Administración.
     */
    public function nombreAreaConsolidada(string $codigo, ?string $nombre = null): string
    {
        foreach ([$codigo, (string) $nombre] as $valor) {
            $destacada = $this->areaDestacadaDe($valor);
            if ($destacada !== null) {
                return $destacada;
            }
        }

        return self::AREA_ADMINISTRACION;
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
     * Totales con venta neta de la quincena.
     * Omite sedes sin venta. Áreas Call Center / Digital Manage usan venta por vendedor;
     * Administración se incluye aunque no tenga venta propia.
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
                // Alias por nombre de área consolidada
                if ($ventaNeta <= 0 && ($grupo['tipo'] ?? '') === 'AREA') {
                    $ventaNeta = (float) ($ventas[mb_strtoupper((string) $grupo['nombre'], 'UTF-8')] ?? 0);
                }
                $pagarUsd = (float) ($grupo['pagar_usd'] ?? 0);
                $grupo['venta_neta'] = round($ventaNeta, 2);
                $grupo['pct_nomina_sobre_venta'] = $ventaNeta > 0
                    ? round(($pagarUsd / $ventaNeta) * 100, 2)
                    : null;

                return $grupo;
            })
            ->filter(function (array $grupo) {
                if ((float) ($grupo['venta_neta'] ?? 0) > 0) {
                    return true;
                }

                // Administración: mostrar nómina aunque no tenga venta atribuida
                return ($grupo['tipo'] ?? '') === 'AREA'
                    && $this->esAdministracion((string) ($grupo['nombre'] ?? ''));
            })
            ->values();
    }

    /**
     * @return array<string, float> codigo sede/área => venta neta USD
     */
    public function ventasNetasPorCodigo(NominaPeriodo $periodo): array
    {
        $inicio = $periodo->fecha_inicio?->toDateString();
        $fin = $periodo->fecha_fin?->toDateString();
        if (! $inicio || ! $fin) {
            return [];
        }

        $out = [];

        if (Schema::hasTable('ventas_documentos')) {
            $rows = DB::table('ventas_documentos')
                ->whereBetween('fecha', [$inicio, $fin])
                ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
                ->selectRaw('UPPER(TRIM(sede)) as sede')
                ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as venta_neta")
                ->groupBy(DB::raw('UPPER(TRIM(sede))'))
                ->get();

            foreach ($rows as $row) {
                $sede = mb_strtoupper(trim((string) $row->sede), 'UTF-8');
                if ($sede === '') {
                    continue;
                }
                $out[$sede] = round((float) $row->venta_neta, 2);
            }
        } elseif (Schema::hasTable('ventas_detalle')) {
            $campo = Schema::hasColumn('ventas_detalle', 'precio_neto')
                ? 'COALESCE(vd.precio_neto, vd.precio_venta)'
                : 'vd.precio_venta';

            $rows = DB::table('ventas_detalle as vd')
                ->whereBetween('vd.fecha', [$inicio, $fin])
                ->when(Schema::hasColumn('ventas_detalle', 'anulado'), function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereNull('vd.anulado')->orWhere('vd.anulado', false);
                    });
                })
                ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
                ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS({$campo} * vd.cantidad) ELSE ABS({$campo} * vd.cantidad) END) as venta_neta")
                ->groupBy(DB::raw('UPPER(TRIM(vd.sede))'))
                ->get();

            foreach ($rows as $row) {
                $sede = mb_strtoupper(trim((string) $row->sede), 'UTF-8');
                if ($sede === '') {
                    continue;
                }
                $out[$sede] = round((float) $row->venta_neta, 2);
            }
        }

        // Call Center / Digital Manage: venta neta por vendedores del área
        $gerencial = app(GerencialDashboardService::class);
        foreach ([self::AREA_CALL_CENTER, self::AREA_DIGITAL_MANAGE] as $area) {
            $claves = $gerencial->clavesVendedoresArea($area);
            $neta = $this->ventaNetaPorVendedores($inicio, $fin, $claves);
            $key = mb_strtoupper($area, 'UTF-8');
            $out[$key] = $neta;
        }

        return $out;
    }

    /**
     * @param  list<string>  $clavesVendedor
     */
    public function ventaNetaPorVendedores(string $inicio, string $fin, array $clavesVendedor): float
    {
        if ($clavesVendedor === [] || ! Schema::hasTable('ventas_detalle')) {
            return 0.0;
        }

        $campo = Schema::hasColumn('ventas_detalle', 'precio_neto')
            ? 'COALESCE(precio_neto, precio_venta)'
            : 'precio_venta';
        $placeholders = implode(',', array_fill(0, count($clavesVendedor), '?'));

        $row = DB::table('ventas_detalle')
            ->whereBetween('fecha', [$inicio, $fin])
            ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$placeholders.')', $clavesVendedor)
            ->when(Schema::hasColumn('ventas_detalle', 'anulado'), function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('anulado')->orWhere('anulado', false);
                });
            })
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(cantidad * {$campo}) ELSE ABS(cantidad * {$campo}) END) as venta_neta")
            ->first();

        return round((float) ($row->venta_neta ?? 0), 2);
    }

    private function areaDestacadaDe(string $valor): ?string
    {
        $n = mb_strtoupper(trim($valor), 'UTF-8');
        $n = str_replace(['_', '-'], ' ', $n);
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        if ($n === '' || $this->esAdministracion($n)) {
            return $n !== '' && $this->esAdministracion($n) ? self::AREA_ADMINISTRACION : null;
        }
        if (str_contains($n, 'CALL') && str_contains($n, 'CENTER')) {
            return self::AREA_CALL_CENTER;
        }
        if (str_contains($n, 'DIGITAL') && (str_contains($n, 'MANAGE') || str_contains($n, 'MANAGER') || str_contains($n, 'ECOMMERCE') || str_contains($n, 'E COMMERCE'))) {
            return self::AREA_DIGITAL_MANAGE;
        }

        return null;
    }

    private function pareceArea(string $nombre): bool
    {
        if ($this->areaDestacadaDe($nombre) !== null) {
            return true;
        }

        return $this->esAdministracion($nombre);
    }

    private function esAdministracion(string $nombre): bool
    {
        $n = mb_strtoupper(trim($nombre), 'UTF-8');
        $n = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $n);

        return str_contains($n, 'ADMINISTRAC');
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
        $ordenArea = [
            mb_strtoupper(self::AREA_CALL_CENTER, 'UTF-8') => 0,
            mb_strtoupper(self::AREA_DIGITAL_MANAGE, 'UTF-8') => 1,
            mb_strtoupper(self::AREA_ADMINISTRACION, 'UTF-8') => 2,
        ];

        return collect(array_values($grupos))->sortBy([
            fn ($a, $b) => ($a['tipo'] === 'AREA' ? 1 : 0) <=> ($b['tipo'] === 'AREA' ? 1 : 0),
            function ($a, $b) use ($ordenArea) {
                if (($a['tipo'] ?? '') !== 'AREA' || ($b['tipo'] ?? '') !== 'AREA') {
                    return 0;
                }
                $oa = $ordenArea[mb_strtoupper((string) $a['nombre'], 'UTF-8')] ?? 99;
                $ob = $ordenArea[mb_strtoupper((string) $b['nombre'], 'UTF-8')] ?? 99;

                return $oa <=> $ob;
            },
            fn ($a, $b) => strcmp(mb_strtoupper((string) $a['nombre'], 'UTF-8'), mb_strtoupper((string) $b['nombre'], 'UTF-8')),
        ])->values();
    }
}
