<?php

namespace App\Services;

use App\Models\ConciliacionCierre;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConciliacionCalendarioService
{
    private BankMovementClassifier $classifier;

    public function __construct(
        private BankReconciliationMatcher $matcher,
        ?BankMovementClassifier $classifier = null,
    ) {
        $this->classifier = $classifier ?? new BankMovementClassifier;
    }

    /**
     * @param  array<string, list<string>>  $titularesPorBanco
     * @return array<string, mixed>
     */
    public function armar(string $mes, array $titularesPorBanco, bool $conMovimientos = false): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $diasMes = $inicio->daysInMonth;

        $cuentas = [];
        foreach ($titularesPorBanco as $banco => $titulares) {
            foreach ($titulares as $titular) {
                [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($banco, $titular);
                if ($bancoCanon === '' || $titularCanon === '' || ! $this->cuentaIncluida($bancoCanon, $titularCanon)) {
                    continue;
                }
                $cuentas[$bancoCanon.'|'.$titularCanon] = [
                    'banco' => $bancoCanon,
                    'titular' => $titularCanon,
                ];
            }
        }

        $cierres = collect();
        if (Schema::hasTable('conciliacion_cierres')) {
            $cierres = ConciliacionCierre::query()
                ->whereDate('fecha_desde', '<=', $fin->toDateString())
                ->whereDate('fecha_hasta', '>=', $inicio->toDateString())
                ->with('usuario')
                ->orderBy('fecha_desde')
                ->get();
        }

        foreach ($cierres as $cierre) {
            [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($cierre->banco, $cierre->titular);
            if ($bancoCanon === '' || $titularCanon === '' || ! $this->cuentaIncluida($bancoCanon, $titularCanon)) {
                continue;
            }
            $cuentas[$bancoCanon.'|'.$titularCanon] = [
                'banco' => $bancoCanon,
                'titular' => $titularCanon,
            ];
        }

        // Tarda (hay cuentas con decenas de miles de movimientos): la página los pide aparte.
        $pendientes = $conMovimientos ? $this->pendientesPorCuenta($inicio, $fin) : null;

        $filas = [];
        $sinMovimientos = [];
        foreach ($cuentas as $cuenta) {
            $marcaSinMov = $cierres->first(function ($cierre) use ($cuenta) {
                if (empty($cierre->sin_movimientos)) {
                    return false;
                }
                [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($cierre->banco, $cierre->titular);

                return $bancoCanon === $cuenta['banco'] && $titularCanon === $cuenta['titular'];
            });
            if ($marcaSinMov) {
                $sinMovimientos[] = $cuenta + [
                    'cierre_id' => $marcaSinMov->id,
                    'marcado_por' => $marcaSinMov->usuario?->name,
                    'marcado_el' => $marcaSinMov->updated_at?->format('d/m/Y H:i'),
                ];

                continue;
            }

            $dias = array_fill(1, $diasMes, false);
            $rangos = [];
            foreach ($cierres as $cierre) {
                [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($cierre->banco, $cierre->titular);
                if ($bancoCanon !== $cuenta['banco'] || $titularCanon !== $cuenta['titular']) {
                    continue;
                }
                $desde = $cierre->fecha_desde->greaterThan($inicio)
                    ? $cierre->fecha_desde->copy()->startOfDay()
                    : $inicio->copy()->startOfDay();
                $hasta = $cierre->fecha_hasta->lessThan($fin)
                    ? $cierre->fecha_hasta->copy()->startOfDay()
                    : $fin->copy()->startOfDay();
                for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
                    $dias[$dia->day] = true;
                }
                $rangos[] = [
                    'id' => $cierre->id,
                    'desde' => $cierre->fecha_desde->toDateString(),
                    'hasta' => $cierre->fecha_hasta->toDateString(),
                    'marcado_por' => $cierre->usuario?->name,
                    'marcado_el' => $cierre->created_at?->format('d/m/Y H:i'),
                ];
            }
            $cubiertos = count(array_filter($dias));
            $filas[] = [
                'banco' => $cuenta['banco'],
                'titular' => $cuenta['titular'],
                'dias' => $dias,
                'rangos' => $rangos,
                'cubiertos' => $cubiertos,
                'completo' => $cubiertos === $diasMes,
                'clave' => $this->matcher->claveCuenta($cuenta['banco'], $cuenta['titular']),
                'movimientos' => $pendientes === null
                    ? null
                    : ($pendientes[$this->matcher->claveCuenta($cuenta['banco'], $cuenta['titular'])] ?? $this->movimientosVacios()),
            ];
        }

        usort($filas, function (array $a, array $b) {
            return [$a['banco'], $a['titular']] <=> [$b['banco'], $b['titular']];
        });
        usort($sinMovimientos, function (array $a, array $b) {
            return [$a['banco'], $a['titular']] <=> [$b['banco'], $b['titular']];
        });

        $celdas = [];
        $cursor = $inicio->copy()->startOfWeek(Carbon::MONDAY);
        $finCalendario = $fin->copy()->endOfWeek(Carbon::SUNDAY);
        while ($cursor->lte($finCalendario)) {
            $enMes = $cursor->month === $inicio->month && $cursor->year === $inicio->year;
            $nombres = [];
            if ($enMes) {
                foreach ($filas as $fila) {
                    if (! empty($fila['dias'][$cursor->day])) {
                        $nombres[] = $fila['banco'].' · '.$fila['titular'];
                    }
                }
            }
            $celdas[] = [
                'dia' => $cursor->day,
                'en_mes' => $enMes,
                'cuentas' => $nombres,
            ];
            $cursor->addDay();
        }

        $nombresMes = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return [
            'mes' => $inicio->format('Y-m'),
            'titulo' => $nombresMes[$inicio->month].' '.$inicio->year,
            'inicio' => $inicio->toDateString(),
            'fin' => $fin->toDateString(),
            'anterior' => $inicio->copy()->subMonth()->format('Y-m'),
            'siguiente' => $inicio->copy()->addMonth()->format('Y-m'),
            'filas' => $filas,
            'sin_movimientos' => $sinMovimientos,
            'celdas' => $celdas,
            'completas' => count(array_filter($filas, fn (array $fila) => $fila['completo'])),
            'total' => count($filas),
        ];
    }

    /**
     * Por cuenta y mes, con el mismo criterio que el PDF de conciliación del banco:
     * - banco_*: movimientos del extracto (sin comisiones, compras de divisas, pagos de crédito,
     *   lotes de punto de venta ni cargos anulados por devolución) y cuántos siguen sin conciliar.
     * - transito*: egresos registrados que el banco todavía no refleja.
     *
     * @return array<string, array{banco_total:int, banco_pendientes:int, banco_pendientes_bs:float, transito:int, transito_bs:float}>
     */
    public function pendientesPorCuenta(Carbon $inicio, Carbon $fin): array
    {
        $out = [];
        $desde = $inicio->toDateString();
        $hasta = $fin->toDateString();

        if (Schema::hasTable('conciliacion_lineas')) {
            $conDevolucion = Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id');
            $conForzar = Schema::hasColumn('conciliacion_lineas', 'forzar_comision');
            $columnas = array_merge(
                ['id', 'banco', 'titular', 'fecha', 'descripcion', 'referencia', 'monto', 'estado', 'tipo'],
                $conDevolucion ? ['devolucion_linea_id'] : [],
                $conForzar ? ['forzar_comision'] : [],
            );

            // Fila por fila y sin modelos: algunas cuentas (Cashea) traen decenas de miles de movimientos al mes.
            $lineas = DB::table((new ConciliacionLinea)->getTable())
                ->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta)
                ->orderByRaw("CASE WHEN estado = 'conciliado' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->select($columnas)
                ->cursor();

            // Si el extracto se subió varias veces, cada movimiento cuenta una sola vez (y vale la copia conciliada).
            $vistos = [];
            foreach ($lineas as $linea) {
                $copia = implode('|', [
                    strtoupper(trim((string) $linea->banco)),
                    strtoupper(trim((string) ($linea->titular ?? ''))),
                    substr((string) $linea->fecha, 0, 10),
                    number_format(round((float) $linea->monto, 2), 2, '.', ''),
                    (string) ($linea->referencia ?? ''),
                    (string) ($linea->descripcion ?? ''),
                    (string) ($linea->tipo ?? ''),
                ]);
                if (isset($vistos[$copia])) {
                    continue;
                }
                $vistos[$copia] = true;

                if ($conDevolucion && $linea->devolucion_linea_id) {
                    continue;
                }
                $descripcion = (string) $linea->descripcion;
                if (($conForzar && $linea->forzar_comision)
                    || $this->classifier->esComision($descripcion, (string) $linea->banco)
                    || $this->classifier->esCompraDivisas($descripcion)
                    || $this->classifier->esPagoCredito($descripcion)
                    || $this->matcher->esAbonoLotePuntoVenta($descripcion, $linea->referencia)) {
                    continue;
                }

                $clave = $this->matcher->claveCuenta($linea->banco, $linea->titular);
                $out[$clave] ??= $this->movimientosVacios();
                $out[$clave]['banco_total']++;
                if ($linea->estado === 'pendiente') {
                    $out[$clave]['banco_pendientes']++;
                    $out[$clave]['banco_pendientes_bs'] += abs((float) $linea->monto);
                }
            }
        }

        if (Schema::hasTable('flujo_cajas')) {
            $egresos = FlujoCaja::query()
                ->where('tipo', 'egreso')
                ->where('es_conciliado', false)
                ->when(Schema::hasColumn('flujo_cajas', 'anulado'), fn ($q) => $q->where('anulado', false))
                ->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta)
                ->get(['id', 'banco', 'titular', 'monto_bs']);

            foreach ($egresos as $egreso) {
                $clave = $this->matcher->claveCuenta($egreso->banco, $egreso->titular);
                $out[$clave] ??= $this->movimientosVacios();
                $out[$clave]['transito']++;
                $out[$clave]['transito_bs'] += abs((float) $egreso->monto_bs);
            }
        }

        foreach ($out as &$fila) {
            $fila['banco_pendientes_bs'] = round($fila['banco_pendientes_bs'], 2);
            $fila['transito_bs'] = round($fila['transito_bs'], 2);
        }
        unset($fila);

        return $out;
    }

    /**
     * @return array{banco_total:int, banco_pendientes:int, banco_pendientes_bs:float, transito:int, transito_bs:float}
     */
    private function movimientosVacios(): array
    {
        return ['banco_total' => 0, 'banco_pendientes' => 0, 'banco_pendientes_bs' => 0.0, 'transito' => 0, 'transito_bs' => 0.0];
    }

    /**
     * Cuentas que no se concilian: José Jerez en cualquier banco,
     * y las tarjetas de terceros en Venezuela.
     */
    public function cuentaIncluida(?string $banco, ?string $titular): bool
    {
        $banco = $this->sinAcento(trim((string) $banco));
        $titular = $this->sinAcento(trim((string) $titular));
        if ($titular === '') {
            return false;
        }
        if ($titular === 'JOSE JEREZ' || str_contains($titular, 'JOSE JEREZ')) {
            return false;
        }

        $ocultas = [
            'BANCARIBE|CURAZAO',
            'BANCARIBE|EURONISSI',
            'BNC|EURONISSI',
        ];
        if (in_array($banco.'|'.$titular, $ocultas, true)) {
            return false;
        }

        $tercerosVenezuela = ['DAYANA LOPEZ', 'EDWARD MAVO', 'JOSE SEMECO', 'MARIA NUNEZ'];

        return ! ($banco === 'VENEZUELA' && in_array($titular, $tercerosVenezuela, true));
    }

    private function sinAcento(string $texto): string
    {
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);

        return strtoupper($texto);
    }
}
