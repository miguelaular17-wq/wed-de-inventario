<?php

namespace App\Services;

use App\Models\User;
use App\Models\VentaDiariaCaja;
use App\Models\VentaDiariaMeta;
use App\Models\VentaDiariaReporte;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentasDiariasService
{
    /** @return list<string> */
    public function sedesDisponibles(): array
    {
        $sedes = config('inventario.sedes_gerencial', config('inventario.sedes_locales', []));

        return array_values(array_unique(array_map(
            fn ($s) => mb_strtoupper(trim((string) $s), 'UTF-8'),
            $sedes
        )));
    }

    public function puedeVerTodas(User $user): bool
    {
        return $user->canAccess('tesoreria')
            || $user->isAdmin()
            || $user->isGerente();
    }

    public function puedeEditar(User $user, string $sede): bool
    {
        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        if ($this->puedeVerTodas($user) && $user->canAccess('tesoreria')) {
            return true;
        }
        if ($user->isAdmin() || $user->isGerente()) {
            return true;
        }

        $userSede = mb_strtoupper(trim((string) ($user->sede ?: session('sede_local'))), 'UTF-8');

        return $user->canAccess('ventas.diarias') && $userSede !== '' && $userSede === $sede;
    }

    public function sedeDelUsuario(User $user): ?string
    {
        $sede = mb_strtoupper(trim((string) ($user->sede ?: session('sede_local'))), 'UTF-8');

        return $sede !== '' ? $sede : null;
    }

    public function metaPara(string $sede, Carbon $fecha): array
    {
        $meta = VentaDiariaMeta::query()
            ->where('sede', mb_strtoupper(trim($sede), 'UTF-8'))
            ->first();

        $esDomingo = (int) $fecha->dayOfWeek === Carbon::SUNDAY;
        $venta = $meta
            ? (float) ($esDomingo ? $meta->meta_venta_domingo : $meta->meta_venta_lv_sab)
            : 0.0;
        $prod = $meta
            ? (float) ($esDomingo ? $meta->meta_prod_domingo : $meta->meta_prod_lv_sab)
            : 0.0;

        return [
            'meta' => $meta,
            'es_domingo' => $esDomingo,
            'meta_venta' => $venta,
            'meta_productos' => $prod,
            'meta_venta_lv_sab' => (float) ($meta->meta_venta_lv_sab ?? 0),
            'meta_venta_domingo' => (float) ($meta->meta_venta_domingo ?? 0),
            'meta_prod_lv_sab' => (float) ($meta->meta_prod_lv_sab ?? 0),
            'meta_prod_domingo' => (float) ($meta->meta_prod_domingo ?? 0),
        ];
    }

    public function calcularTotales(VentaDiariaReporte $r, ?array $metaCtx = null): array
    {
        $tasa = (float) $r->tasa;
        $div = $tasa > 0 ? $tasa : 1.0;

        $efectivoBsUsd = (float) $r->efectivo_bs / $div;
        $puntoUsd = (float) $r->punto_venta_bs / $div;
        $transfUsd = (float) $r->transf_pm_bs / $div;

        $totalCobros = (float) $r->divisas_efectivo
            + $efectivoBsUsd
            + $puntoUsd
            + $transfUsd
            + (float) $r->zelle_binance
            + (float) $r->cashea
            + (float) $r->abonos;

        $totalCreditos = (float) $r->total_creditos;
        $totalVentas = $totalCobros + $totalCreditos;
        $factFiscalUsd = (float) $r->z_fiscal_bs / $div;
        $productos = (float) $r->productos_vendidos;

        $metaVenta = (float) ($metaCtx['meta_venta'] ?? 0);
        $metaProd = (float) ($metaCtx['meta_productos'] ?? 0);

        $pctVenta = $metaVenta > 0 ? ($totalVentas / $metaVenta) - 1 : null;
        $pctProd = $metaProd > 0 ? ($productos / $metaProd) - 1 : null;
        $pctFiscal = $totalVentas > 0 ? $factFiscalUsd / $totalVentas : null;

        return [
            'efectivo_bs_usd' => round($efectivoBsUsd, 4),
            'punto_venta_usd' => round($puntoUsd, 4),
            'transf_pm_usd' => round($transfUsd, 4),
            'total_cobros' => round($totalCobros, 4),
            'total_creditos' => round($totalCreditos, 4),
            'total_ventas' => round($totalVentas, 4),
            'facturacion_fiscal_usd' => round($factFiscalUsd, 4),
            'pct_vs_meta_venta' => $pctVenta,
            'pct_vs_meta_prod' => $pctProd,
            'pct_fiscal' => $pctFiscal,
            'meta_venta' => $metaVenta,
            'meta_productos' => $metaProd,
        ];
    }

    public function totalesCajas(Collection $cajas): array
    {
        $keys = ['efectivo_usd', 'efectivo_bs', 'punto_venta', 'transf_pm', 'zelle_binance', 'cashea', 'fact_credito', 'abonos'];
        $tot = array_fill_keys($keys, 0.0);
        foreach ($cajas as $c) {
            foreach ($keys as $k) {
                $tot[$k] += (float) $c->{$k};
            }
        }

        return $tot;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $cajas
     */
    public function guardar(User $user, array $data, array $cajas): VentaDiariaReporte
    {
        $sede = mb_strtoupper(trim((string) ($data['sede'] ?? '')), 'UTF-8');
        $fecha = (string) ($data['fecha'] ?? '');

        if ($sede === '' || $fecha === '') {
            throw ValidationException::withMessages(['sede' => 'Sede y fecha son obligatorias.']);
        }
        if (! $this->puedeEditar($user, $sede)) {
            throw ValidationException::withMessages(['sede' => 'No puedes editar el reporte de esta sede.']);
        }

        return DB::transaction(function () use ($user, $data, $cajas, $sede, $fecha) {
            $reporte = VentaDiariaReporte::query()->firstOrNew([
                'sede' => $sede,
                'fecha' => $fecha,
            ]);

            // Desglose = suma de cajas (fuente de verdad)
            $sum = [
                'efectivo_usd' => 0.0,
                'efectivo_bs' => 0.0,
                'punto_venta' => 0.0,
                'transf_pm' => 0.0,
                'zelle_binance' => 0.0,
                'cashea' => 0.0,
                'fact_credito' => 0.0,
                'abonos' => 0.0,
            ];
            $cajasLimpias = [];
            foreach ($cajas as $caja) {
                $nombre = trim((string) ($caja['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $row = [
                    'nombre' => $nombre,
                    'efectivo_usd' => (float) ($caja['efectivo_usd'] ?? 0),
                    'efectivo_bs' => (float) ($caja['efectivo_bs'] ?? 0),
                    'punto_venta' => (float) ($caja['punto_venta'] ?? 0),
                    'transf_pm' => (float) ($caja['transf_pm'] ?? 0),
                    'zelle_binance' => (float) ($caja['zelle_binance'] ?? 0),
                    'cashea' => (float) ($caja['cashea'] ?? 0),
                    'fact_credito' => (float) ($caja['fact_credito'] ?? 0),
                    'abonos' => (float) ($caja['abonos'] ?? 0),
                ];
                foreach ($sum as $k => $_) {
                    $sum[$k] += $row[$k];
                }
                $cajasLimpias[] = $row;
            }

            $reporte->tasa = (float) ($data['tasa'] ?? 0);
            $reporte->divisas_efectivo = round($sum['efectivo_usd'], 2);
            $reporte->efectivo_bs = round($sum['efectivo_bs'], 2);
            $reporte->punto_venta_bs = round($sum['punto_venta'], 2);
            $reporte->transf_pm_bs = round($sum['transf_pm'], 2);
            $reporte->zelle_binance = round($sum['zelle_binance'], 2);
            $reporte->cashea = round($sum['cashea'], 2);
            $reporte->abonos = round($sum['abonos'], 2);
            $reporte->total_creditos = round($sum['fact_credito'], 2);
            $reporte->z_fiscal_bs = (float) ($data['z_fiscal_bs'] ?? 0);
            $reporte->productos_vendidos = (float) ($data['productos_vendidos'] ?? 0);
            $reporte->deliverys_pendientes = (float) ($data['deliverys_pendientes'] ?? 0);
            $reporte->observaciones = $data['observaciones'] ?? null;
            $reporte->updated_by = $user->id;
            if (! $reporte->exists) {
                $reporte->created_by = $user->id;
            }
            $reporte->save();

            $reporte->cajas()->delete();
            foreach ($cajasLimpias as $orden => $row) {
                VentaDiariaCaja::create([
                    'reporte_id' => $reporte->id,
                    'nombre' => $row['nombre'],
                    'orden' => $orden,
                    'efectivo_usd' => $row['efectivo_usd'],
                    'efectivo_bs' => $row['efectivo_bs'],
                    'punto_venta' => $row['punto_venta'],
                    'transf_pm' => $row['transf_pm'],
                    'zelle_binance' => $row['zelle_binance'],
                    'cashea' => $row['cashea'],
                    'fact_credito' => $row['fact_credito'],
                    'abonos' => $row['abonos'],
                ]);
            }

            return $reporte->fresh(['cajas']);
        });
    }

    public function listar(?string $sede, ?string $desde, ?string $hasta): Collection
    {
        return VentaDiariaReporte::query()
            ->with('cajas')
            ->when($sede, fn ($q) => $q->where('sede', mb_strtoupper(trim($sede), 'UTF-8')))
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderBy('sede')
            ->get();
    }
}
