<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** CREATE INDEX CONCURRENTLY no puede ir dentro de una transacción. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Los filtros reales usan LOWER(TRIM(banco)) + rango de fechas (idsExtractoVisibles y la deduplicación del importador).
        if (Schema::hasTable('conciliacion_lineas')) {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_conc_lineas_banco_norm_fecha ON conciliacion_lineas (LOWER(TRIM(banco)), fecha)');
            if (Schema::hasColumn('conciliacion_lineas', 'importacion_id')) {
                DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_conc_lineas_importacion ON conciliacion_lineas (importacion_id) WHERE importacion_id IS NOT NULL');
            }
        }

        // Egresos candidatos: tipo = 'egreso' + rango de fechas en cada carga de la conciliación.
        if (Schema::hasTable('flujo_cajas')) {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_flujo_cajas_fecha ON flujo_cajas (fecha)');
        }

        if (Schema::hasTable('tesoreria_ingresos')) {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tesoreria_ingresos_fecha ON tesoreria_ingresos (fecha)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_conc_lineas_banco_norm_fecha');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_conc_lineas_importacion');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_flujo_cajas_fecha');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_tesoreria_ingresos_fecha');
    }
};
