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
        $cal = $this->calendarioDelPeriodo($meta?->periodo_label, $fecha);
        $calc = $this->metaVentaDiaria((float) ($meta?->venta_meta_mes ?? 0), $cal['dias'], $cal['domingos']);
        $calcProd = $this->metaVentaDiaria((float) ($meta?->productos_meta_mes ?? 0), $cal['dias'], $cal['domingos']);
        $venta = $esDomingo ? $calc['domingo'] : $calc['diaria'];
        $prod = $esDomingo ? $calcProd['domingo'] : $calcProd['diaria'];

        return [
            'meta' => $meta,
            'es_domingo' => $esDomingo,
            'meta_venta' => $venta,
            'meta_productos' => $prod,
            'meta_venta_lv_sab' => $calc['diaria'],
            'meta_venta_domingo' => $calc['domingo'],
            'meta_prod_lv_sab' => $calcProd['diaria'],
            'meta_prod_domingo' => $calcProd['domingo'],
        ];
    }

    /**
     * @return array{dias: int, domingos: int}
     */
    public function calendarioDelPeriodo(?string $periodoLabel, ?Carbon $fallback = null): array
    {
        $label = mb_strtoupper(trim((string) $periodoLabel), 'UTF-8');
        $meses = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
            'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
            'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        ];
        $base = $fallback ?? now();
        $anio = (int) $base->year;
        $mes = (int) $base->month;
        if (preg_match('/(20\d{2})/', $label, $coincidencias)) {
            $anio = (int) $coincidencias[1];
        }
        foreach ($meses as $nombre => $numero) {
            if (str_contains($label, $nombre)) {
                $mes = $numero;
                break;
            }
        }

        $inicio = Carbon::create($anio, $mes, 1);
        $domingos = 0;
        $cursor = $inicio->copy();
        while ((int) $cursor->month === $mes) {
            if ($cursor->isSunday()) {
                $domingos++;
            }
            $cursor->addDay();
        }

        return [
            'dias' => $inicio->daysInMonth,
            'domingos' => $domingos,
        ];
    }

    public function diasDelPeriodo(?string $periodoLabel, ?Carbon $fallback = null): int
    {
        return $this->calendarioDelPeriodo($periodoLabel, $fallback)['dias'];
    }

    /**
     * Reparte la meta del mes: los domingos salen a la mitad de un día parejo
     * y el resto se divide entre los días que no son domingo. El domingo de la
     * tabla es la mitad de esa meta diaria.
     *
     * @return array{diaria: float, domingo: float}
     */
    public function metaVentaDiaria(float $ventaMetaMes, int $dias, int $domingos = 0): array
    {
        $dias = max(0, $dias);
        $domingos = max(0, min($domingos, max(0, $dias - 1)));
        $habiles = $dias - $domingos;
        if ($dias < 1 || $habiles < 1) {
            return ['diaria' => 0.0, 'domingo' => 0.0];
        }

        $diaParejo = $ventaMetaMes / $dias;
        $resto = $ventaMetaMes - ($domingos * ($diaParejo / 2));
        $diaria = round($resto / $habiles, 4);

        return [
            'diaria' => $diaria,
            'domingo' => round($diaria / 2, 4),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnasCaja(): array
    {
        return [
            'efectivo_usd' => 'EFECTIVO $',
            'efectivo_bs' => 'EFECTIVO BS',
            'punto_venta' => 'PUNTO BS',
            'pago_movil' => 'PAGO MÓVIL BS',
            'transferencias' => 'TRANSFERENCIAS BS',
            'zelle' => 'ZELLE $',
            'binance' => 'BINANCE $',
            'mercantil_panama' => 'MERCANTIL PANAMÁ $',
            'cashea' => 'CASHEA $',
            'flaexpay' => 'FLEXPAY $',
            'krece' => 'KRECE $',
            'fact_credito' => 'CRÉDITO $',
            'abonos' => 'ABONOS $',
            'iphone' => 'IPHONE $',
            'preventa' => 'PREVENTA $',
            'gift_card' => 'GIFT CARD $',
        ];
    }

    /**
     * @return list<string>
     */
    public function camposCaja(): array
    {
        return array_keys($this->columnasCaja());
    }

    /**
     * @param  object|array<string, mixed>  $caja
     * @return array<string, float>
     */
    public function leerCaja(object|array $caja): array
    {
        $valor = function (string $key) use ($caja): float {
            if (is_array($caja)) {
                return (float) ($caja[$key] ?? 0);
            }

            return (float) ($caja->{$key} ?? 0);
        };

        $row = [];
        foreach ($this->camposCaja() as $campo) {
            $row[$campo] = $valor($campo);
        }
        if ($row['zelle'] == 0.0 && $row['binance'] == 0.0) {
            $row['zelle'] = $valor('zelle_binance');
        }
        if ($row['pago_movil'] == 0.0 && $row['transferencias'] == 0.0) {
            $row['pago_movil'] = $valor('transf_pm');
        }

        return $row;
    }

    public function calcularTotales(VentaDiariaReporte $r, ?array $metaCtx = null): array
    {
        $tasa = (float) $r->tasa;
        $div = $tasa > 0 ? $tasa : 1.0;
        $montos = $this->montosReporte($r);

        $bsUsd = function (float $bolivares) use ($div): float {
            return $bolivares / $div;
        };

        $lineas = [
            $this->lineaUsd('Efectivo divisas', $montos['divisas_efectivo']),
            $this->lineaUsd('Zelle', $montos['zelle']),
            $this->lineaUsd('Binance', $montos['binance']),
            $this->lineaUsd('Mercantil Panamá', $montos['mercantil_panama']),
            $this->lineaUsd('iPhone', $montos['iphone']),
            $this->lineaUsd('Preventa', $montos['preventa']),
            $this->lineaUsd('Abono deuda/apartado', $montos['abonos']),
            $this->lineaBs('Efectivo Bs', $montos['efectivo_bs'], $bsUsd($montos['efectivo_bs'])),
            $this->lineaBs('Punto de venta', $montos['punto_venta_bs'], $bsUsd($montos['punto_venta_bs'])),
            $this->lineaBs('Pago móvil', $montos['pago_movil_bs'], $bsUsd($montos['pago_movil_bs'])),
            $this->lineaBs('Transferencias', $montos['transferencias_bs'], $bsUsd($montos['transferencias_bs'])),
            $this->lineaEnBolivares('Cashea financiamiento', $montos['cashea'], $div),
            $this->lineaEnBolivares('Flexpay financiamiento', $montos['flaexpay'], $div),
            $this->lineaEnBolivares('Krece financiamiento', $montos['krece'], $div),
            $this->lineaEnBolivares('Gift card', $montos['gift_card'], $div),
            $this->lineaEnBolivares('Facturas a crédito', $montos['total_creditos'], $div, true),
        ];

        $totalBs = 0.0;
        $totalCobros = 0.0;
        foreach ($lineas as $linea) {
            if ($linea['bs'] !== null) {
                $totalBs += $linea['bs'];
            }
            if (! $linea['rojo']) {
                $totalCobros += $linea['usd'];
            }
        }
        $totalCreditos = $montos['total_creditos'];
        $totalVentas = $totalCobros + $totalCreditos;
        $factFiscalUsd = (float) $r->z_fiscal_bs / $div;
        $productos = (float) $r->productos_vendidos;

        $metaVenta = (float) ($metaCtx['meta_venta'] ?? 0);
        $metaProd = (float) ($metaCtx['meta_productos'] ?? 0);

        return [
            'lineas' => $lineas,
            'efectivo_bs_usd' => round($bsUsd($montos['efectivo_bs']), 4),
            'punto_venta_usd' => round($bsUsd($montos['punto_venta_bs']), 4),
            'transf_pm_usd' => round($bsUsd($montos['pago_movil_bs'] + $montos['transferencias_bs']), 4),
            'total_bs' => round($totalBs, 4),
            'total_cobros' => round($totalCobros, 4),
            'total_creditos' => round($totalCreditos, 4),
            'total_ventas' => round($totalVentas, 4),
            'facturacion_fiscal_usd' => round($factFiscalUsd, 4),
            'pct_vs_meta_venta' => $metaVenta > 0 ? ($totalVentas / $metaVenta) - 1 : null,
            'pct_vs_meta_prod' => $metaProd > 0 ? ($productos / $metaProd) - 1 : null,
            'cumpl_venta' => $metaVenta > 0 ? $totalVentas / $metaVenta : null,
            'cumpl_prod' => $metaProd > 0 ? $productos / $metaProd : null,
            'pct_fiscal' => $totalVentas > 0 ? $factFiscalUsd / $totalVentas : null,
            'meta_venta' => $metaVenta,
            'meta_productos' => $metaProd,
        ];
    }

    public function totalesCajas(Collection $cajas): array
    {
        $keys = $this->camposCaja();
        $tot = array_fill_keys($keys, 0.0);
        foreach ($cajas as $c) {
            $row = $this->leerCaja($c);
            foreach ($keys as $k) {
                $tot[$k] += $row[$k];
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

            // Desglose = suma de cajas (fuente de verdad). Cada forma se cuenta una sola vez.
            $sum = array_fill_keys($this->camposCaja(), 0.0);
            $cajasLimpias = [];
            foreach ($cajas as $caja) {
                $nombre = trim((string) ($caja['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $row = $this->leerCaja($caja);
                $row['nombre'] = $nombre;
                foreach ($this->camposCaja() as $k) {
                    $sum[$k] += $row[$k];
                }
                $cajasLimpias[] = $row;
            }

            $reporte->tasa = (float) ($data['tasa'] ?? 0);
            $reporte->divisas_efectivo = round($sum['efectivo_usd'], 2);
            $reporte->efectivo_bs = round($sum['efectivo_bs'], 2);
            $reporte->punto_venta_bs = round($sum['punto_venta'], 2);
            $reporte->pago_movil_bs = round($sum['pago_movil'], 2);
            $reporte->transferencias_bs = round($sum['transferencias'], 2);
            $reporte->transf_pm_bs = round($sum['pago_movil'] + $sum['transferencias'], 2);
            $reporte->zelle = round($sum['zelle'], 2);
            $reporte->binance = round($sum['binance'], 2);
            $reporte->zelle_binance = round($sum['zelle'] + $sum['binance'], 2);
            $reporte->mercantil_panama = round($sum['mercantil_panama'], 2);
            $reporte->cashea = round($sum['cashea'], 2);
            $reporte->flaexpay = round($sum['flaexpay'], 2);
            $reporte->krece = round($sum['krece'], 2);
            $reporte->abonos = round($sum['abonos'], 2);
            $reporte->iphone = round($sum['iphone'], 2);
            $reporte->preventa = round($sum['preventa'], 2);
            $reporte->gift_card = round($sum['gift_card'], 2);
            $reporte->total_creditos = round($sum['fact_credito'], 2);
            $reporte->z_fiscal_bs = (float) ($data['z_fiscal_bs'] ?? 0);
            $reporte->productos_vendidos = (float) ($data['productos_vendidos'] ?? 0);
            $reporte->deliverys_pendientes = (float) ($data['deliverys_pendientes'] ?? 0);
            $reporte->fondo_bs = (float) ($data['fondo_bs'] ?? 0);
            $reporte->fondo_divisas = (float) ($data['fondo_divisas'] ?? 0);
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
                    'pago_movil' => $row['pago_movil'],
                    'transferencias' => $row['transferencias'],
                    'transf_pm' => $row['pago_movil'] + $row['transferencias'],
                    'zelle' => $row['zelle'],
                    'binance' => $row['binance'],
                    'zelle_binance' => $row['zelle'] + $row['binance'],
                    'mercantil_panama' => $row['mercantil_panama'],
                    'cashea' => $row['cashea'],
                    'flaexpay' => $row['flaexpay'],
                    'krece' => $row['krece'],
                    'fact_credito' => $row['fact_credito'],
                    'abonos' => $row['abonos'],
                    'iphone' => $row['iphone'],
                    'preventa' => $row['preventa'],
                    'gift_card' => $row['gift_card'],
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

    /**
     * @return array<string, float>
     */
    private function montosReporte(VentaDiariaReporte $r): array
    {
        $n = fn (string $key): float => (float) ($r->{$key} ?? 0);
        $zelle = $n('zelle');
        $binance = $n('binance');
        if ($zelle == 0.0 && $binance == 0.0) {
            $zelle = $n('zelle_binance');
        }
        $pago = $n('pago_movil_bs');
        $transf = $n('transferencias_bs');
        if ($pago == 0.0 && $transf == 0.0) {
            $pago = $n('transf_pm_bs');
        }

        return [
            'divisas_efectivo' => $n('divisas_efectivo'),
            'zelle' => $zelle,
            'binance' => $binance,
            'mercantil_panama' => $n('mercantil_panama'),
            'iphone' => $n('iphone'),
            'preventa' => $n('preventa'),
            'abonos' => $n('abonos'),
            'efectivo_bs' => $n('efectivo_bs'),
            'punto_venta_bs' => $n('punto_venta_bs'),
            'pago_movil_bs' => $pago,
            'transferencias_bs' => $transf,
            'cashea' => $n('cashea'),
            'flaexpay' => $n('flaexpay'),
            'krece' => $n('krece'),
            'gift_card' => $n('gift_card'),
            'total_creditos' => $n('total_creditos'),
        ];
    }

    /**
     * @return array{etiqueta: string, bs: ?float, usd: float, rojo: bool}
     */
    private function lineaUsd(string $etiqueta, float $usd, bool $rojo = false): array
    {
        return [
            'etiqueta' => $etiqueta,
            'bs' => null,
            'usd' => round($usd, 4),
            'rojo' => $rojo,
        ];
    }

    /**
     * @return array{etiqueta: string, bs: ?float, usd: float, rojo: bool}
     */
    private function lineaBs(string $etiqueta, float $bolivares, float $usd, bool $rojo = false): array
    {
        return [
            'etiqueta' => $etiqueta,
            'bs' => round($bolivares, 4),
            'usd' => round($usd, 4),
            'rojo' => $rojo,
        ];
    }

    /**
     * Monto guardado en dólares. En el desglose se muestra en bolívares y su equivalente.
     *
     * @return array{etiqueta: string, bs: ?float, usd: float, rojo: bool}
     */
    private function lineaEnBolivares(string $etiqueta, float $usd, float $tasa, bool $rojo = false): array
    {
        return $this->lineaBs($etiqueta, $usd * $tasa, $usd, $rojo);
    }
}
