<?php

use App\Models\CuentaBancaria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentas_bancarias')) {
            return;
        }

        $cat = 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS';

        $tesoro = CuentaBancaria::query()
            ->where('categoria_reporte', $cat)
            ->whereRaw('upper(banco) = ?', ['TESORO'])
            ->whereRaw('upper(titular) = ?', ['LNACEH'])
            ->first();

        $orden = $tesoro ? ((int) $tesoro->orden + 1) : 16;

        $cuenta = CuentaBancaria::query()
            ->where('categoria_reporte', $cat)
            ->whereRaw('upper(banco) = ?', ['BBVA'])
            ->whereRaw('upper(titular) = ?', ['LNACEH'])
            ->first();

        if ($cuenta) {
            $cuenta->update([
                'banco' => 'BBVA',
                'titular' => 'LNACEH',
                'orden' => $orden,
                'mostrar_en_principal' => false,
            ]);

            return;
        }

        CuentaBancaria::query()->create([
            'banco' => 'BBVA',
            'titular' => 'LNACEH',
            'categoria_reporte' => $cat,
            'mostrar_en_principal' => false,
            'reporte_usd' => 0,
            'reporte_bs' => 0,
            'orden' => $orden,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuentas_bancarias')) {
            return;
        }

        CuentaBancaria::query()
            ->where('categoria_reporte', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS')
            ->whereRaw('upper(banco) = ?', ['BBVA'])
            ->whereRaw('upper(titular) = ?', ['LNACEH'])
            ->delete();
    }
};
