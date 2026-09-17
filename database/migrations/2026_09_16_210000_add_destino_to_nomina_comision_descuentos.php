<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return;
        }

        Schema::table('nomina_comision_descuentos', function (Blueprint $table) {
            if (! Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
                $table->string('destino', 16)->nullable()->after('decision');
            }
        });

        // Faltantes ya marcados para descontar: destino comisión (comportamiento anterior).
        if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
            DB::table('nomina_comision_descuentos')
                ->where('tipo', 'FALTANTE')
                ->where('decision', 'DESCONTAR')
                ->whereNull('destino')
                ->update(['destino' => 'COMISION']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return;
        }

        Schema::table('nomina_comision_descuentos', function (Blueprint $table) {
            if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
                $table->dropColumn('destino');
            }
        });
    }
};
