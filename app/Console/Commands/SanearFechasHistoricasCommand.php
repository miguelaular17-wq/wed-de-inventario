<?php

namespace App\Console\Commands;

use App\Services\InventarioHistoricoDateSanitizer;
use Illuminate\Console\Command;

class SanearFechasHistoricasCommand extends Command
{
    protected $signature = 'inventario:sanear-fechas-historicas';

    protected $description = 'Corrige ultima_venta/ultima_compra imposibles (p. ej. año 2626) copiadas desde Profit.';

    public function handle(InventarioHistoricoDateSanitizer $sanitizer): int
    {
        $result = $sanitizer->run();

        $this->info('Fechas históricas saneadas.');
        $this->line('Últ. venta reconstruida desde facturas: '.$result['ventas_corregidas']);
        $this->line('Últ. venta imposible puesta en vacío: '.$result['ventas_nulas']);
        $this->line('Últ. compra imposible puesta en vacío: '.$result['compras_nulas']);

        return self::SUCCESS;
    }
}
