<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correcciones del Excel: renombrar cargos, borrar los sin empleados
     * (se conserva Marketing id=8 para el mapeo del rol marketing).
     */
    public function up(): void
    {
        if (! Schema::hasTable('nomina_cargos')) {
            return;
        }

        $renombres = [
            1 => 'Supervisor de tienda',
            14 => 'Contador',
            15 => 'Analista de Tesoreria',
            38 => 'Analista de Cuentas por Cobrar',
            43 => 'Asesor de Arreglos',
        ];

        foreach ($renombres as $id => $nombre) {
            DB::table('nomina_cargos')->where('id', $id)->update([
                'nombre' => $nombre,
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('nomina_empleados') && Schema::hasColumn('nomina_empleados', 'cargo')) {
                DB::table('nomina_empleados')
                    ->where('cargo_id', $id)
                    ->update(['cargo' => $nombre, 'updated_at' => now()]);
            }
        }

        // Marketing (rol → cargo 8): recrear si faltaba.
        $mkt = DB::table('nomina_cargos')->where('id', 8)->first();
        if (! $mkt) {
            DB::table('nomina_cargos')->insert([
                'id' => 8,
                'nombre' => 'Marketing',
                'estado' => 'ACTIVO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('nomina_cargos', 'id'), (SELECT COALESCE(MAX(id), 1) FROM nomina_cargos))");
            }
        } else {
            DB::table('nomina_cargos')->where('id', 8)->update([
                'nombre' => 'Marketing',
                'estado' => 'ACTIVO',
                'updated_at' => now(),
            ]);
        }

        // Borrar cargos sin empleados (excepto Marketing id=8).
        if (! Schema::hasTable('nomina_empleados')) {
            return;
        }

        $conEmpleados = DB::table('nomina_empleados')
            ->whereNotNull('cargo_id')
            ->distinct()
            ->pluck('cargo_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $vacios = DB::table('nomina_cargos')
            ->where('id', '!=', 8)
            ->when($conEmpleados !== [], fn ($q) => $q->whereNotIn('id', $conEmpleados))
            ->when($conEmpleados === [], fn ($q) => $q->whereRaw('1=1'))
            ->pluck('id');

        if ($vacios->isNotEmpty()) {
            DB::table('nomina_cargos')->whereIn('id', $vacios->all())->delete();
        }
    }

    public function down(): void
    {
        // No se revierten borrados ni renombres (datos de catálogo).
    }
};
