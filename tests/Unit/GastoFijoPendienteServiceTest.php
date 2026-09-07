<?php

namespace Tests\Unit;

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
}
