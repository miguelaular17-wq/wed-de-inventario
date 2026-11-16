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
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'moneda_divisa')) {
                $table->string('moneda_divisa', 8)->nullable()->after('monto'); // USD | EUR (solo caja divisas)
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }
        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'moneda_divisa')) {
                $table->dropColumn('moneda_divisa');
            }
        });
    }
};
