<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }
        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'beneficiario')) {
                $table->string('beneficiario', 255)->nullable()->after('referencia');
            }
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'placa_vehiculo')) {
                $after = Schema::hasColumn('tesoreria_caja_movimientos', 'sede') ? 'sede' : 'beneficiario';
                $table->string('placa_vehiculo', 40)->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }
        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            foreach (['placa_vehiculo', 'beneficiario'] as $col) {
                if (Schema::hasColumn('tesoreria_caja_movimientos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
