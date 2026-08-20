<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('
            -- 1. Tabla unificada de Ventas Detalladas + Devoluciones
            CREATE TABLE IF NOT EXISTS inventario_v2.ventas_detalle (
                id BIGSERIAL PRIMARY KEY,
                sede VARCHAR(16) NOT NULL,
                tipo_documento VARCHAR(8) NOT NULL,       -- \'FAC\' o \'DEV\'
                numero_documento VARCHAR(32) NOT NULL,
                fecha DATE NOT NULL,
                producto_id BIGINT REFERENCES inventario_v2.productos(id),
                codigo_producto VARCHAR(64),
                nombre_producto TEXT,
                cantidad INTEGER NOT NULL,
                precio_venta NUMERIC(12,2) DEFAULT 0,
                costo_unitario NUMERIC(12,2) DEFAULT 0,
                ganancia NUMERIC(12,2) DEFAULT 0,
                cliente VARCHAR(255),
                vendedor VARCHAR(255),
                factura_origen VARCHAR(32),
                motivo_devolucion TEXT,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                UNIQUE(sede, tipo_documento, numero_documento, codigo_producto, fecha)
            );

            CREATE INDEX IF NOT EXISTS idx_ventas_detalle_fecha ON inventario_v2.ventas_detalle(fecha);
            CREATE INDEX IF NOT EXISTS idx_ventas_detalle_sede ON inventario_v2.ventas_detalle(sede);
            CREATE INDEX IF NOT EXISTS idx_ventas_detalle_tipo ON inventario_v2.ventas_detalle(tipo_documento);
            CREATE INDEX IF NOT EXISTS idx_ventas_detalle_producto ON inventario_v2.ventas_detalle(producto_id);

            -- 2. Tabla de Ajustes de Inventario
            CREATE TABLE IF NOT EXISTS inventario_v2.ajustes_inventario (
                id BIGSERIAL PRIMARY KEY,
                sede VARCHAR(16) NOT NULL,
                tipo_movimiento VARCHAR(8) NOT NULL,       -- \'AJU\', \'TRA\', \'ENT\', \'SAL\'
                numero_documento VARCHAR(32) NOT NULL,
                fecha DATE NOT NULL,
                producto_id BIGINT REFERENCES inventario_v2.productos(id),
                codigo_producto VARCHAR(64),
                nombre_producto TEXT,
                cantidad INTEGER NOT NULL,
                costo_unitario NUMERIC(12,2) DEFAULT 0,
                motivo TEXT,
                usuario VARCHAR(128),
                created_at TIMESTAMPTZ DEFAULT NOW(),
                UNIQUE(sede, tipo_movimiento, numero_documento, codigo_producto)
            );

            CREATE INDEX IF NOT EXISTS idx_ajustes_fecha ON inventario_v2.ajustes_inventario(fecha);
            CREATE INDEX IF NOT EXISTS idx_ajustes_sede ON inventario_v2.ajustes_inventario(sede);

            -- 3. Agregar columna de costo actual a productos
            ALTER TABLE inventario_v2.productos 
            ADD COLUMN IF NOT EXISTS costo_actual NUMERIC(12,2) DEFAULT 0;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('
            ALTER TABLE inventario_v2.productos DROP COLUMN IF EXISTS costo_actual;
            DROP TABLE IF EXISTS inventario_v2.ajustes_inventario CASCADE;
            DROP TABLE IF EXISTS inventario_v2.ventas_detalle CASCADE;
        ');
    }
};
