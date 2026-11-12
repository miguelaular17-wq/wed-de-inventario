<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tipo de gasto de los egresos del flujo de caja de Cobranza (la misma lista de Finanzas). */
    public function up(): void
    {
        if (Schema::hasTable('cobranza_movimientos') && ! Schema::hasColumn('cobranza_movimientos', 'tipo_gasto')) {
            Schema::table('cobranza_movimientos', function (Blueprint $table) {
                $table->string('tipo_gasto', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cobranza_movimientos', 'tipo_gasto')) {
            Schema::table('cobranza_movimientos', function (Blueprint $table) {
                $table->dropColumn('tipo_gasto');
            });
        }
    }
};
