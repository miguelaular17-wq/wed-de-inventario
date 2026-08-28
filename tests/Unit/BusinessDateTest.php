<?php

namespace Tests\Unit;

use App\Support\BusinessDate;
use Carbon\Carbon;
use Tests\TestCase;

class BusinessDateTest extends TestCase
{
    public function test_rechaza_anios_imposibles_del_sincronizador(): void
    {
        $today = Carbon::parse('2026-08-27');

        $this->assertNull(BusinessDate::parseOrNull('2626-03-03', $today));
        $this->assertNull(BusinessDate::parseOrNull('03/03/2626', $today));
        $this->assertNull(BusinessDate::parseOrNull('1900-01-01', $today));
    }

    public function test_acepta_fechas_reales(): void
    {
        $today = Carbon::parse('2026-08-27');
        $parsed = BusinessDate::parseOrNull('2026-08-25', $today);

        $this->assertNotNull($parsed);
        $this->assertSame('25/08/2026', $parsed->format('d/m/Y'));
    }
}
