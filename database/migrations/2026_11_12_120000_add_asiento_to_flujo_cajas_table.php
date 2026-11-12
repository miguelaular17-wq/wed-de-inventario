<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Asiento contable del egreso (en $): un debe y uno o varios haber. Reemplaza al desglose. */
    public function up(): void
    {
        if (Schema::hasTable('flujo_cajas') && ! Schema::hasColumn('flujo_cajas', 'asiento')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->json('asiento')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('flujo_cajas', 'asiento')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->dropColumn('asiento');
            });
        }
    }
};
