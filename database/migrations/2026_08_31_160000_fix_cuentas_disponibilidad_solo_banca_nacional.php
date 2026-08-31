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

        $categoriasNacionales = [
            'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
            'BANCA NACIONAL - BAJO MOVIMIENTO',
        ];

        $visibles = [
            ['BANESCO', 'GRUPO JRZ', '#f4b183', 0],
            ['BANESCO', 'DORAL', '#ffff00', 1],
            ['BANESCO', 'LNACEH', '#0070c0', 2],
            ['BANESCO', 'NUNES', '#ff0000', 3],
            ['BANESCO', 'EURONISSI', '#ff0000', 4],
            ['BANESCO', 'GRUPO JENU', '#f4b183', 5],
            ['MERCANTIL', 'GRUPO JENU', '#f4b183', 6],
            ['MERCANTIL', 'JRZ', '#f4b183', 7],
            ['BNC', 'GRUPO JRZ', '#f4b183', 8],
            ['BNC', 'LNACEH', '#ffff00', 9],
            ['BNC', 'L.S. CASHEA', '#ff0000', 10],
            ['BANCARIBE', 'GRUPO JRZ', null, 11],
            ['BANCARIBE', 'DORAL', null, 12],
            ['VENEZUELA', 'GRUPO JRZ', null, 13],
            ['VENEZUELA', 'DORAL', null, 14],
            ['VENEZUELA', 'LNACEH', null, 15],
            ['BBVA', 'LNACEH', null, 16],
            ['BANCAMIGA', 'DORAL', null, 17],
        ];

        CuentaBancaria::query()->update(['mostrar_en_principal' => false]);

        foreach ($visibles as [$banco, $titular, $color, $orden]) {
            CuentaBancaria::query()
                ->whereIn('categoria_reporte', $categoriasNacionales)
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update([
                    'color_tc' => $color,
                    'mostrar_en_principal' => true,
                    'orden' => $orden,
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
