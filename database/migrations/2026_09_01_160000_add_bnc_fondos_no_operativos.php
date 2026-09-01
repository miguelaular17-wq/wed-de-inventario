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

        $cat = 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS';
        $filas = [
            ['BNC', 'LNACEH', 4],
            ['BNC', 'GRUPO JRZ', 5],
            ['BNC', 'DORAL', 6],
        ];

        foreach ($filas as [$banco, $titular, $orden]) {
            CuentaBancaria::query()->firstOrCreate(
                [
                    'banco' => $banco,
                    'titular' => $titular,
                    'categoria_reporte' => $cat,
                ],
                [
                    'orden' => $orden,
                    'mostrar_en_principal' => false,
                    'reporte_usd' => 0,
                    'reporte_bs' => 0,
                ]
            );
        }
    }

    public function down(): void
    {
        //
    }
};
