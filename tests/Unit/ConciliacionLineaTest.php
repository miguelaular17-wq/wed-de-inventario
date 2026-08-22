<?php

namespace Tests\Unit;

use App\Models\ConciliacionLinea;
use PHPUnit\Framework\TestCase;

class ConciliacionLineaTest extends TestCase
{
    public function test_distingue_cargos_y_abonos_aunque_el_monto_se_guarde_positivo(): void
    {
        $cargo = new ConciliacionLinea(['monto' => 1316.77, 'tipo' => 'cargo']);
        $abono = new ConciliacionLinea(['monto' => 1316.77, 'tipo' => 'abono']);

        $this->assertTrue($cargo->esCargo());
        $this->assertFalse($cargo->esAbono());
        $this->assertTrue($abono->esAbono());
        $this->assertFalse($abono->esCargo());
    }

    public function test_conserva_compatibilidad_con_movimientos_antiguos_sin_tipo(): void
    {
        $cargo = new ConciliacionLinea(['monto' => -50]);
        $abono = new ConciliacionLinea(['monto' => 50]);

        $this->assertTrue($cargo->esCargo());
        $this->assertTrue($abono->esAbono());
    }
}
