<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega fecha_registro a cobranza_resumenes para que cada
     * "Guardar Resumen" inserte un registro nuevo (en lugar de actualizar),
     * permitiendo la Comparativa Semanal de Efectividad.
     */
    public function up(): void
    {
        Schema::table('cobranza_resumenes', function (Blueprint $table) {
            // Agrega la columna fecha_registro (si no existe)
            if (!Schema::hasColumn('cobranza_resumenes', 'fecha_registro')) {
                $table->date('fecha_registro')->nullable()->after('id');
            }
        });

        // Indice compuesto para búsquedas rápidas por fecha + sede
        Schema::table('cobranza_resumenes', function (Blueprint $table) {
            $table->index(['fecha_registro', 'sede_nombre'], 'idx_cobranza_resumenes_fecha_sede');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranza_resumenes', function (Blueprint $table) {
            $table->dropIndex('idx_cobranza_resumenes_fecha_sede');
            $table->dropColumn('fecha_registro');
        });
    }
};
