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

    public function test_formas_nuevas_suman_una_vez_y_el_bolivar_no_incluye_dolares(): void
    {
        $reporte = new VentaDiariaReporte([
            'tasa' => 100,
            'divisas_efectivo' => 0,
            'efectivo_bs' => 100,
            'punto_venta_bs' => 0,
            'pago_movil_bs' => 0,
            'transferencias_bs' => 0,
            'transf_pm_bs' => 200,
            'zelle' => 5,
            'binance' => 3,
            'zelle_binance' => 100,
            'mercantil_panama' => 1,
            'preventa' => 2,
            'flaexpay' => 3,
            'krece' => 4,
            'cashea' => 0,
            'abonos' => 0,
            'iphone' => 0,
            'gift_card' => 0,
            'total_creditos' => 0,
            'z_fiscal_bs' => 0,
            'productos_vendidos' => 0,
        ]);

        $totales = (new VentasDiariasService)->calcularTotales($reporte);

        $this->assertEqualsWithDelta(21.0, $totales['total_cobros'], 0.0001);
        $this->assertEqualsWithDelta(21.0, $totales['total_ventas'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, $totales['total_bs'], 0.0001);
        $cashea = collect($totales['lineas'])->firstWhere('etiqueta', 'Cashea financiamiento');
        $this->assertNotNull($cashea['bs']);
    }

    public function test_zelle_binance_legado_cuenta_como_zelle_si_el_split_esta_en_cero(): void
    {
        $reporte = new VentaDiariaReporte([
            'tasa' => 100,
            'zelle' => 0,
            'binance' => 0,
            'zelle_binance' => 8,
            'total_creditos' => 0,
        ]);

        $totales = (new VentasDiariasService)->calcularTotales($reporte);
        $zelle = collect($totales['lineas'])->firstWhere('etiqueta', 'Zelle');

        $this->assertEqualsWithDelta(8.0, $totales['total_cobros'], 0.0001);
        $this->assertEqualsWithDelta(8.0, $zelle['usd'], 0.0001);
        $this->assertNull($zelle['bs']);
    }

    public function test_meta_diaria_divide_la_venta_del_mes_y_el_domingo_es_la_mitad(): void
    {
        $servicio = new VentasDiariasService;

        $cal = $servicio->calendarioDelPeriodo('META MES OCTUBRE 2026');
        $this->assertSame(31, $cal['dias']);
        $this->assertSame(4, $cal['domingos']);

        $calc = $servicio->metaVentaDiaria(335477.623, $cal['dias'], $cal['domingos']);

        $this->assertEqualsWithDelta(11623.4780, $calc['diaria'], 0.0001);
        $this->assertEqualsWithDelta(5811.7390, $calc['domingo'], 0.0001);
    }
}
