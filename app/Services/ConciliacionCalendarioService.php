<?php

namespace App\Services;

use App\Models\ConciliacionCierre;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ConciliacionCalendarioService
{
    public function __construct(private BankReconciliationMatcher $matcher) {}

    /**
     * @param  array<string, list<string>>  $titularesPorBanco
     * @return array<string, mixed>
     */
    public function armar(string $mes, array $titularesPorBanco): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $diasMes = $inicio->daysInMonth;

        $cuentas = [];
        foreach ($titularesPorBanco as $banco => $titulares) {
            foreach ($titulares as $titular) {
                [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($banco, $titular);
                if ($bancoCanon === '' || $titularCanon === '') {
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
                ->orderBy('fecha_desde')
                ->get();
        }

        foreach ($cierres as $cierre) {
            [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($cierre->banco, $cierre->titular);
            $cuentas[$bancoCanon.'|'.$titularCanon] = [
                'banco' => $bancoCanon,
                'titular' => $titularCanon,
            ];
        }

        $filas = [];
        foreach ($cuentas as $cuenta) {
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
            ];
        }

        usort($filas, function (array $a, array $b) {
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
            'celdas' => $celdas,
            'completas' => count(array_filter($filas, fn (array $fila) => $fila['completo'])),
            'total' => count($filas),
        ];
    }
}
