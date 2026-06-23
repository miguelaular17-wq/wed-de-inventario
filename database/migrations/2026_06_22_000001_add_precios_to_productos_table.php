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
ALTER TABLE inventario_v2.productos
    ADD COLUMN IF NOT EXISTS precio_unidad NUMERIC(12,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS precio_mayor  NUMERIC(12,2) NOT NULL DEFAULT 0
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE inventario_v2.productos
    DROP COLUMN IF EXISTS precio_unidad,
    DROP COLUMN IF EXISTS precio_mayor
SQL);
    }
};
