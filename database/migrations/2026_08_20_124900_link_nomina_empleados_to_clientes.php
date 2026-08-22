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

        DB::unprepared(<<<'SQL'
ALTER TABLE inventario_v2.nomina_empleados
    ADD COLUMN IF NOT EXISTS cliente_id BIGINT;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'inventario_v2'
          AND table_name = 'nomina_empleados'
          AND column_name = 'nombre_completo'
    ) THEN
        IF NOT EXISTS (
            SELECT 1
            FROM pg_constraint c
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
            WHERE c.conrelid = 'inventario_v2.nomina_empleados'::regclass
              AND c.contype = 'f'
              AND a.attname = 'cliente_id'
        ) THEN
            ALTER TABLE inventario_v2.nomina_empleados
                ADD CONSTRAINT fk_nomina_empleados_cliente
                FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT;
        END IF;
    END IF;
END $$;

DROP INDEX IF EXISTS inventario_v2.uq_nomina_empleados_documento;

CREATE UNIQUE INDEX IF NOT EXISTS uq_nomina_empleados_cliente_idx
    ON inventario_v2.nomina_empleados (cliente_id);

ALTER TABLE inventario_v2.nomina_empleados
    DROP COLUMN IF EXISTS nombre_completo,
    DROP COLUMN IF EXISTS documento;

ALTER TABLE inventario_v2.nomina_empleados
    ALTER COLUMN cliente_id SET NOT NULL;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE inventario_v2.nomina_empleados
    ALTER COLUMN cliente_id DROP NOT NULL;

ALTER TABLE inventario_v2.nomina_empleados
    ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(255),
    ADD COLUMN IF NOT EXISTS documento VARCHAR(64);

DROP INDEX IF EXISTS inventario_v2.uq_nomina_empleados_cliente_idx;

ALTER TABLE inventario_v2.nomina_empleados
    DROP CONSTRAINT IF EXISTS fk_nomina_empleados_cliente;
SQL);
    }
};
