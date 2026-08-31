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

        $pv = '#f4b183';
        $terceros = '#ff0000';
        $cashea = '#ffff00';
        $avances = '#0070c0';

        $visibles = [
            ['BANESCO', 'GRUPO JRZ', $pv],
            ['BANESCO', 'DORAL', $pv],
            ['BANESCO', 'LNACEH', $pv],
            ['BANESCO', 'NUNES', $terceros],
            ['BANESCO', 'EURONISSI', $pv],
            ['BANESCO', 'GRUPO JENU', $avances],
            ['MERCANTIL', 'GRUPO JENU', $avances],
            ['MERCANTIL', 'JRZ', $pv],
            ['BNC', 'GRUPO JRZ', $pv],
            ['BNC', 'LNACEH', $pv],
            ['BNC', 'L.S. CASHEA', $cashea],
            ['BANCARIBE', 'GRUPO JRZ', $pv],
            ['BANCARIBE', 'DORAL', $pv],
            ['VENEZUELA', 'GRUPO JRZ', $pv],
            ['VENEZUELA', 'DORAL', $pv],
            ['VENEZUELA', 'LNACEH', $pv],
            ['BBVA', 'LNACEH', $pv],
            ['BANCAMIGA', 'DORAL', $pv],
        ];

        foreach ($visibles as [$banco, $titular, $color]) {
            CuentaBancaria::query()
                ->whereIn('categoria_reporte', [
                    'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                    'BANCA NACIONAL - BAJO MOVIMIENTO',
                ])
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update(['color_tc' => $color]);
        }
    }

    public function down(): void
    {
        //
    }
};
