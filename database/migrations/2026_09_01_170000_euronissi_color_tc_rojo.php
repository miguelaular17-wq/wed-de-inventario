<?php

use App\Models\CuentaBancaria;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cuentas_bancarias')) {
            return;
        }

        CuentaBancaria::query()
            ->whereRaw('upper(banco) = ?', ['BANESCO'])
            ->whereRaw('upper(titular) = ?', ['EURONISSI'])
            ->update(['color_tc' => '#ff0000']);
    }

    public function down(): void
    {
        CuentaBancaria::query()
            ->whereRaw('upper(banco) = ?', ['BANESCO'])
            ->whereRaw('upper(titular) = ?', ['EURONISSI'])
            ->whereIn('categoria_reporte', [
                'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                'BANCA NACIONAL - BAJO MOVIMIENTO',
            ])
            ->update(['color_tc' => '#f4b183']);
    }
};
