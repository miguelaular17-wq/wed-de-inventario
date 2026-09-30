<?php

namespace Tests\Unit;

use App\Models\VentaDiariaCaja;
use App\Models\VentaDiariaReporte;
use App\Services\VentasDiariasService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class VentasDiariasIphoneGiftCardTest extends TestCase
{
    public function test_iphone_y_gift_card_entran_en_el_total_de_cobros(): void
    {
        $reporte = new VentaDiariaReporte([
            'tasa' => 100,
            'divisas_efectivo' => 10,
            'efectivo_bs' => 0,
            'punto_venta_bs' => 0,
            'transf_pm_bs' => 0,
            'zelle_binance' => 0,
            'cashea' => 0,
            'abonos' => 0,
            'iphone' => 50,
            'gift_card' => 25,
            'total_creditos' => 5,
            'z_fiscal_bs' => 0,
            'productos_vendidos' => 0,
        ]);

        $totales = (new VentasDiariasService)->calcularTotales($reporte);

        $this->assertSame(85.0, $totales['total_cobros']);
        $this->assertSame(90.0, $totales['total_ventas']);
    }

    public function test_totales_de_cajas_suman_iphone_y_gift_card(): void
    {
        $cajas = new Collection([
            new VentaDiariaCaja(['iphone' => 10, 'gift_card' => 4, 'efectivo_usd' => 0, 'efectivo_bs' => 0, 'punto_venta' => 0, 'transf_pm' => 0, 'zelle_binance' => 0, 'cashea' => 0, 'fact_credito' => 0, 'abonos' => 0]),
            new VentaDiariaCaja(['iphone' => 3, 'gift_card' => 1, 'efectivo_usd' => 0, 'efectivo_bs' => 0, 'punto_venta' => 0, 'transf_pm' => 0, 'zelle_binance' => 0, 'cashea' => 0, 'fact_credito' => 0, 'abonos' => 0]),
        ]);

        $tot = (new VentasDiariasService)->totalesCajas($cajas);

        $this->assertSame(13.0, $tot['iphone']);
        $this->assertSame(5.0, $tot['gift_card']);
    }
}
