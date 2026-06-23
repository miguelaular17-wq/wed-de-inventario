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
CREATE TABLE IF NOT EXISTS inventario_v2.publicidad_productos (
    id BIGSERIAL PRIMARY KEY,
    producto_id BIGINT NOT NULL REFERENCES inventario_v2.productos(id) ON DELETE CASCADE,
    fecha_publicidad TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ultima_venta_original DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_publicidad_producto UNIQUE (producto_id)
);
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TABLE IF EXISTS inventario_v2.publicidad_productos CASCADE');
    }
};
