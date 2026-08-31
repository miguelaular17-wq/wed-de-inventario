<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $fks = DB::select("
            SELECT
                n.nspname AS schema,
                c.conname,
                rel.relname AS tabla,
                a.attname AS columna
            FROM pg_constraint c
            JOIN pg_class rel ON rel.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = rel.relnamespace
            JOIN pg_class ref ON ref.oid = c.confrelid
            JOIN unnest(c.conkey) WITH ORDINALITY AS cols(attnum, ord) ON true
            JOIN pg_attribute a ON a.attrelid = rel.oid AND a.attnum = cols.attnum
            WHERE c.contype = 'f'
              AND ref.relname = 'nomina_empleados'
              AND array_length(c.conkey, 1) = 1
        ");

        foreach ($fks as $fk) {
            $tabla = '"'.$fk->schema.'"."'.$fk->tabla.'"';
            $columna = '"'.$fk->columna.'"';
            $nombre = '"'.$fk->conname.'"';
            $ref = '"'.$fk->schema.'"."nomina_empleados"';
            $onDelete = in_array($fk->columna, ['supervisor_id', 'nomina_empleado_id'], true)
                ? 'SET NULL'
                : 'CASCADE';

            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$nombre}");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} FOREIGN KEY ({$columna}) REFERENCES {$ref}(id) ON DELETE {$onDelete}");
        }
    }

    public function down(): void
    {
        // No se restaura RESTRICT: borrar un empleado debe seguir siendo posible.
    }
};
