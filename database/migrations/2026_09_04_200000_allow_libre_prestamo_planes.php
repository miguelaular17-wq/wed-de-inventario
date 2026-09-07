<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_prestamo_planes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE nomina_prestamo_planes ALTER COLUMN cuota_id DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE nomina_prestamo_planes MODIFY cuota_id BIGINT UNSIGNED NULL');
        } else {
            // sqlite / tests: recreated via CreatesNominaSchema
        }

        try {
            Schema::table('nomina_prestamo_planes', function (Blueprint $table) {
                $table->unique(['prestamo_id', 'quincena_inicio'], 'nomina_prestamo_planes_prestamo_quincena_unique');
            });
        } catch (\Throwable) {
            // índice ya existe
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_prestamo_planes')) {
            return;
        }

        try {
            Schema::table('nomina_prestamo_planes', function (Blueprint $table) {
                $table->dropUnique('nomina_prestamo_planes_prestamo_quincena_unique');
            });
        } catch (\Throwable) {
        }
    }
};
