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

        $tarjeta = 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA';

        $operativos = [
            ['BNC', 'LNACEH', 0],
            ['BNC', 'DORAL', 1],
            ['BNC', 'GRUPO JRZ', 2],
            ['BANCARIBE', 'DORAL', 3],
            ['BANCARIBE', 'GRUPO JRZ', 4],
            ['TESORO', 'JRZ', 5],
            ['TESORO', 'DORAL', 6],
            ['TESORO', 'LNACEH', 7],
            ['BANCAMIGA', 'DORAL', 8],
        ];

        $noOperativos = [
            ['BANESCO', 'DORAL', 0],
            ['BANESCO', 'GRUPO JENU', 1],
            ['BANESCO', 'EURONISSI', 2],
            ['BANESCO', 'NUNES', 3],
        ];

        foreach ($operativos as [$banco, $titular, $orden]) {
            CuentaBancaria::query()
                ->where('categoria_reporte', $tarjeta)
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update([
                    'categoria_reporte' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS',
                    'orden' => $orden,
                    'mostrar_en_principal' => false,
                ]);
        }

        foreach ($noOperativos as [$banco, $titular, $orden]) {
            $updated = CuentaBancaria::query()
                ->where('categoria_reporte', $tarjeta)
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update([
                    'categoria_reporte' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS',
                    'orden' => $orden,
                    'mostrar_en_principal' => false,
                ]);

            if ($updated === 0 && $banco === 'BANESCO' && $titular === 'EURONISSI') {
                CuentaBancaria::query()->create([
                    'banco' => 'BANESCO',
                    'titular' => 'EURONISSI',
                    'categoria_reporte' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS',
                    'orden' => $orden,
                    'mostrar_en_principal' => false,
                    'reporte_usd' => 0,
                    'reporte_bs' => 0,
                ]);
            }
        }

        $cerradas = [
            ['CITIZENS MM', 'INV. DORAL', 0],
            ['CITIZENS CH', 'INV. DORAL', 1],
        ];

        foreach ($cerradas as [$banco, $titular, $orden]) {
            CuentaBancaria::query()
                ->where('categoria_reporte', 'BANCA INTERNACIONAL / BILLETERAS')
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update([
                    'categoria_reporte' => 'CUENTAS INTERNACIONALES CERRADAS (FONDOS POR LIBERAR)',
                    'orden' => $orden,
                    'mostrar_en_principal' => false,
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
