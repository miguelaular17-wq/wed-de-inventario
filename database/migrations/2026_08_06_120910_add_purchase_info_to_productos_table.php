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
    ADD COLUMN IF NOT EXISTS ultima_cantidad_compra NUMERIC(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ultimo_costo_compra NUMERIC(12,2) DEFAULT NULL
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE inventario_v2.productos
    DROP COLUMN IF EXISTS ultima_cantidad_compra,
    DROP COLUMN IF EXISTS ultimo_costo_compra
SQL);
    }
};
