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
            if (! Schema::hasColumn('nomina_comision_descuentos', 'decision')) {
                $table->string('decision', 16)->nullable()->after('estado');
                $table->index(['tipo', 'decision', 'estado']);
            }
        });

        // Ya registrados: se mantienen descontables (comportamiento anterior).
        // Los nuevos faltantes nacen en PENDIENTE de decisión.
        if (Schema::hasColumn('nomina_comision_descuentos', 'decision')) {
            DB::table('nomina_comision_descuentos')
                ->where('tipo', 'FALTANTE')
                ->whereNull('decision')
                ->update(['decision' => 'DESCONTAR']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return;
        }

        Schema::table('nomina_comision_descuentos', function (Blueprint $table) {
            if (Schema::hasColumn('nomina_comision_descuentos', 'decision')) {
                $table->dropIndex(['tipo', 'decision', 'estado']);
                $table->dropColumn('decision');
            }
        });
    }
};
