<?php

namespace Tests\Unit;

use App\Services\ComisionBancariaUsd;
use Tests\TestCase;

class ComisionBancariaUsdTest extends TestCase
{
    public function test_convierte_cada_dia_con_la_tasa_del_egreso_y_suma(): void
    {
        $servicio = new ComisionBancariaUsd;
        $tasas = $servicio->tasasPorDia(collect([
            (object) ['fecha' => '2026-08-25', 'banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'tasa_cambio' => 100],
            (object) ['fecha' => '2026-08-30', 'banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'tasa_cambio' => 200],
            (object) ['fecha' => '2026-08-31', 'banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'tasa_cambio' => 50],
        ]));

        $lineas = collect([
            (object) ['fecha' => '2026-08-25', 'descripcion' => 'Comision transferencia', 'referencia' => '1', 'monto' => 1000],
            (object) ['fecha' => '2026-08-25', 'descripcion' => 'Comision transferencia', 'referencia' => '2', 'monto' => 142.62],
            (object) ['fecha' => '2026-08-30', 'descripcion' => 'Mantenimiento de Cuenta', 'referencia' => '15711', 'monto' => 54],
            (object) ['fecha' => '2026-08-31', 'descripcion' => 'Envio de SMS', 'referencia' => '247725', 'monto' => 54],
        ]);

        $resultado = $servicio->convertir($lineas, $tasas, 'BANCAMIGA', 'DORAL');
        $porDescripcion = $resultado['filas']->keyBy('descripcion');

        $this->assertEquals(11.43, $porDescripcion['Comision transferencia']['monto_usd']);
        $this->assertEquals(0.27, $porDescripcion['Mantenimiento de Cuenta']['monto_usd']);
        $this->assertEquals(1.08, $porDescripcion['Envio de SMS']['monto_usd']);
        $this->assertEquals(12.78, $resultado['total_usd']);
    }
}
