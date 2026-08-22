<?php

namespace Tests\Unit;

use App\Services\CobranzaIndicatorService;
use Tests\TestCase;

class CobranzaIndicatorServiceTest extends TestCase
{
    public function test_cuenta_clientes_unicos_y_no_facturas(): void
    {
        $registros = collect([
            (object) [
                'id' => 1,
                'sede_nombre' => 'DORAL',
                'codigo_cliente' => 'C-001',
                'id_documento' => 'FAC-1',
                'saldo' => 100,
                'estatus' => 'MOROSO',
            ],
            (object) [
                'id' => 2,
                'sede_nombre' => 'DORAL',
                'codigo_cliente' => 'C-001',
                'id_documento' => 'FAC-2',
                'saldo' => 50,
                'estatus' => 'CRITICO',
            ],
            (object) [
                'id' => 3,
                'sede_nombre' => 'DORAL',
                'codigo_cliente' => 'C-002',
                'id_documento' => 'FAC-3',
                'saldo' => 25,
                'estatus' => 'RECIENTE',
            ],
        ]);

        $resultado = (new CobranzaIndicatorService())->calcular($registros);
        $porEstatus = collect($resultado['por_estatus'])->keyBy('estatus');

        $this->assertSame(2, $resultado['total_clientes']);
        $this->assertSame(2, $resultado['por_sede'][0]->total_clientes);
        $this->assertSame(175.0, $resultado['total_saldo']);
        $this->assertSame(1, $porEstatus['CRITICO']->total_clientes);
        $this->assertSame(150.0, $porEstatus['CRITICO']->total_saldo);
        $this->assertSame(1, $porEstatus['RECIENTE']->total_clientes);
    }
}
