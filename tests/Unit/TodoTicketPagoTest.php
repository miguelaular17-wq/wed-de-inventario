<?php

namespace Tests\Unit;

use App\Services\TodoTicketPago;
use PHPUnit\Framework\TestCase;

class TodoTicketPagoTest extends TestCase
{
    public function test_total_real_del_detalle_todoticket(): void
    {
        $total = TodoTicketPago::totalReal([
            'recarga' => 903127.29,
            'comision' => 9031.27,
            'iva' => 1445.00,
            'ret_islr' => 451.56,
            'ret_iva' => 1083.75,
            'ret_1x1000' => 0,
            'ret_resp_social' => 0,
            'ret_isae' => 0,
        ]);

        $this->assertSame(912068.25, $total);
    }
}
