<?php

namespace Tests\Unit;

use App\Support\Sku;
use PHPUnit\Framework\TestCase;

class SkuTest extends TestCase
{
    public function test_muestra_solo_el_codigo_principal(): void
    {
        $this->assertSame('#102055', Sku::principal('#102055 / 2025090105518 / 2025815205585 / 2025808205516'));
        $this->assertSame('6800000662048', Sku::principal('6800000662048'));
        $this->assertSame('3150/3250', Sku::principal('3150/3250'));
        $this->assertSame('ABC', Sku::principal(' / ABC / DEF'));
        $this->assertSame('', Sku::principal(null));
    }
}
