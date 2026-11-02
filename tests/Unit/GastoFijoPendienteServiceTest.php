<?php

namespace Tests\Unit;

use App\Models\GastoFijo;
use App\Models\GastoFijoPago;
use App\Services\GastoFijoPendienteService;
use Carbon\Carbon;
use Tests\TestCase;

class GastoFijoPendienteServiceTest extends TestCase
{
    private GastoFijoPendienteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GastoFijoPendienteService;
    }

    public function test_unpaid_bill_stays_after_due_date(): void
    {
        $now = Carbon::create(2026, 9, 12);

        $periodos = $this->service->periodosPendientes('8', 100, [], $now);

        $this->assertCount(2, $periodos);
        $actual = collect($periodos)->last();
        $this->assertSame('vencido', $actual['tipo']);
        $this->assertSame(8, $actual['mes_idx']);
        $this->assertTrue($actual['urgente']);
    }

    public function test_paid_current_month_is_hidden(): void
    {
        $now = Carbon::create(2026, 9, 12);
        $pago = new GastoFijoPago([
            'mes_idx' => 8,
            'pagado' => true,
            'pagado_at' => $now->copy(),
        ]);

        $periodos = $this->service->periodosPendientes('8', 100, [$pago], $now);
        $deSeptiembre = collect($periodos)->firstWhere('mes_idx', 8);

        $this->assertNull($deSeptiembre);
    }

    public function test_upcoming_within_seven_days_still_shows_as_proximo(): void
    {
        $now = Carbon::create(2026, 9, 7);

        $periodos = $this->service->periodosPendientes('8', 50, [], $now);
        $deSeptiembre = collect($periodos)->firstWhere('mes_idx', 8);

        $this->assertNotNull($deSeptiembre);
        $this->assertSame('proximo', $deSeptiembre['tipo']);
        $this->assertSame(8, $deSeptiembre['dia']);
    }

    public function test_far_future_due_date_this_month_is_not_listed_yet(): void
    {
        $now = Carbon::create(2026, 9, 7);

        $periodos = $this->service->periodosPendientes('25', 50, [], $now);
        $deSeptiembre = collect($periodos)->firstWhere('mes_idx', 8);

        $this->assertNull($deSeptiembre);
    }

    public function test_con_un_mes_de_atraso_avisa_la_cuota_del_mes_anterior(): void
    {
        // 6 de noviembre, se paga el 8: lo que vence es la cuota de octubre, no la de noviembre.
        $now = Carbon::create(2026, 11, 6);

        $periodos = $this->service->periodosPendientes('8-15 de cada mes', 55, [], $now, 1);

        $this->assertNull(collect($periodos)->firstWhere('mes_idx', 10));
        $octubre = collect($periodos)->firstWhere('mes_idx', 9);
        $this->assertNotNull($octubre);
        $this->assertSame('proximo', $octubre['tipo']);
        $this->assertSame(2026, $octubre['anio']);
    }

    public function test_con_atraso_en_enero_la_cuota_es_diciembre_del_ano_anterior(): void
    {
        $now = Carbon::create(2027, 1, 10);
        $pagoDiciembreOtroAnio = new GastoFijoPago(['mes_idx' => 11, 'anio' => 2027, 'pagado' => true]);

        $periodos = $this->service->periodosPendientes('8', 55, [$pagoDiciembreOtroAnio], $now, 1);
        $diciembre = collect($periodos)->firstWhere('mes_idx', 11);

        $this->assertNotNull($diciembre, 'Un pago de diciembre de otro año no cuenta.');
        $this->assertSame(2026, $diciembre['anio']);
        $this->assertSame('vencido', $diciembre['tipo']);
    }

    public function test_gasto_desactivado_no_tiene_pendientes(): void
    {
        $gasto = new GastoFijo(['fecha' => '8', 'costo' => 100, 'activo' => false]);
        $gasto->setRelation('pagos', collect());

        $this->assertSame([], $this->service->pendientesDeGasto($gasto, Carbon::create(2026, 9, 12)));

        $gasto->activo = true;
        $this->assertNotEmpty($this->service->pendientesDeGasto($gasto, Carbon::create(2026, 9, 12)));
    }
}
