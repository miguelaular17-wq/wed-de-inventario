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
ALTER TABLE inventario_v2.ventas_detalle
    ADD COLUMN IF NOT EXISTS anulado BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS anulado_at TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_vendedor
    ON inventario_v2.ventas_detalle (vendedor);

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_vendedor_fecha
    ON inventario_v2.ventas_detalle (vendedor, fecha);

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_fecha_sede
    ON inventario_v2.ventas_detalle (fecha, sede);

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_factura_origen
    ON inventario_v2.ventas_detalle (factura_origen)
    WHERE factura_origen IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_ventas_detalle_anulado
    ON inventario_v2.ventas_detalle (anulado)
    WHERE anulado = TRUE;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP INDEX IF EXISTS inventario_v2.idx_ventas_detalle_anulado;
DROP INDEX IF EXISTS inventario_v2.idx_ventas_detalle_factura_origen;
DROP INDEX IF EXISTS inventario_v2.idx_ventas_detalle_fecha_sede;
DROP INDEX IF EXISTS inventario_v2.idx_ventas_detalle_vendedor_fecha;
DROP INDEX IF EXISTS inventario_v2.idx_ventas_detalle_vendedor;

ALTER TABLE inventario_v2.ventas_detalle
    DROP COLUMN IF EXISTS anulado_at,
    DROP COLUMN IF EXISTS anulado;
SQL);
    }
};
