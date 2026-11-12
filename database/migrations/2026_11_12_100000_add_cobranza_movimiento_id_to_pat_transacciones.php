<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pat_transacciones') && ! Schema::hasColumn('pat_transacciones', 'cobranza_movimiento_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                // Parte de un gasto o ingreso del flujo de caja de Cobranza repartido entre varias propiedades.
                $table->unsignedBigInteger('cobranza_movimiento_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pat_transacciones') && Schema::hasColumn('pat_transacciones', 'cobranza_movimiento_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->dropIndex(['cobranza_movimiento_id']);
                $table->dropColumn('cobranza_movimiento_id');
            });
        }
    }
};
