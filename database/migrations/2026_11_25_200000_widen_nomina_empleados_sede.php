<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * syncDenormalized copia nomina_sedes.codigo a nomina_empleados.sede.
 * En prod la columna quedó en varchar(16) y códigos como "Doral Feria de Juguetes" fallan al crear empleado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_empleados') || ! Schema::hasColumn('nomina_empleados', 'sede')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE nomina_empleados ALTER COLUMN sede TYPE varchar(128)');

            return;
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE nomina_empleados MODIFY sede VARCHAR(128) NULL');
        }
        // sqlite (tests): CreatesNominaSchema ya define sede suficientemente ancho.
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_empleados') || ! Schema::hasColumn('nomina_empleados', 'sede')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE nomina_empleados ALTER COLUMN sede TYPE varchar(16)');
        }
    }
};
