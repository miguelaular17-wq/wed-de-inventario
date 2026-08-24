<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_comision_registros') || ! Schema::hasColumn('nomina_comision_registros', 'cantidad')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $schemas = DB::select("
                SELECT table_schema
                FROM information_schema.columns
                WHERE table_name = 'nomina_comision_registros'
                  AND column_name = 'cantidad'
                  AND data_type = 'integer'
            ");

            foreach ($schemas as $row) {
                $schema = $row->table_schema;
                DB::statement("
                    ALTER TABLE {$schema}.nomina_comision_registros
                    ALTER COLUMN cantidad TYPE NUMERIC(18,4)
                    USING cantidad::NUMERIC(18,4)
                ");
            }

            return;
        }

        DB::statement('ALTER TABLE nomina_comision_registros ALTER COLUMN cantidad TYPE NUMERIC(18,4)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_comision_registros')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE inventario_v2.nomina_comision_registros
                ALTER COLUMN cantidad TYPE INTEGER
                USING ROUND(cantidad)::INTEGER
            ');
        }
    }
};
