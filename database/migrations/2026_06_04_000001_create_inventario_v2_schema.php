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

        DB::unprepared("CREATE SCHEMA IF NOT EXISTS inventario_v2");

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.productos (
    id              BIGSERIAL PRIMARY KEY,
    codigo          VARCHAR(64) NOT NULL,
    nombre          TEXT NOT NULL DEFAULT '',
    categoria       VARCHAR(256) NOT NULL DEFAULT '',
    subcategoria    VARCHAR(256) NOT NULL DEFAULT '',
    proveedor       VARCHAR(256) NOT NULL DEFAULT '',
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_productos_codigo UNIQUE (codigo)
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.stock_actual (
    id              BIGSERIAL PRIMARY KEY,
    producto_id     BIGINT NOT NULL REFERENCES inventario_v2.productos(id) ON DELETE CASCADE,
    sede            VARCHAR(16) NOT NULL,
    existencia      INTEGER NOT NULL DEFAULT 0 CHECK (existencia >= 0),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_stock_actual_producto_sede UNIQUE (producto_id, sede),
    CONSTRAINT ck_stock_actual_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'))
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.ventas_historicas (
    id              BIGSERIAL PRIMARY KEY,
    producto_id     BIGINT NOT NULL REFERENCES inventario_v2.productos(id) ON DELETE CASCADE,
    sede            VARCHAR(16) NOT NULL,
    venta_promedio  NUMERIC(12,4) NOT NULL DEFAULT 0,
    ventas_60d      NUMERIC(12,4) NOT NULL DEFAULT 0,
    ultima_venta    DATE,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_ventas_historicas UNIQUE (producto_id, sede),
    CONSTRAINT ck_ventas_historicas_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'))
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.reposicion (
    id              BIGSERIAL PRIMARY KEY,
    producto_id     BIGINT NOT NULL REFERENCES inventario_v2.productos(id) ON DELETE CASCADE,
    sede            VARCHAR(16) NOT NULL,
    demanda         NUMERIC(12,4) NOT NULL DEFAULT 0,
    accion          VARCHAR(64) NOT NULL DEFAULT '',
    opcion1         VARCHAR(32) NOT NULL DEFAULT '',
    opcion2         VARCHAR(32) NOT NULL DEFAULT '',
    sugerido        INTEGER NOT NULL DEFAULT 0,
    sugerido_nec    INTEGER NOT NULL DEFAULT 0,
    req_tag         VARCHAR(32) NOT NULL DEFAULT '',
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_reposicion UNIQUE (producto_id, sede),
    CONSTRAINT ck_reposicion_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'))
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.inventario_derivado (
    id              BIGSERIAL PRIMARY KEY,
    producto_id     BIGINT NOT NULL REFERENCES inventario_v2.productos(id) ON DELETE CASCADE,
    sede            VARCHAR(16) NOT NULL,
    accion          VARCHAR(128) NOT NULL DEFAULT '',
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_inventario_derivado UNIQUE (producto_id, sede),
    CONSTRAINT ck_inventario_derivado_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'))
)
SQL);

        DB::unprepared(<<<'SQL'
DO $$ BEGIN
    CREATE TYPE inventario_v2.tipo_movimiento AS ENUM (
        'REQUISICION', 'PEDIDO', 'SURTIDO', 'AJUSTE', 'IMPORT'
    );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$
SQL);

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.movimientos (
    id              BIGSERIAL PRIMARY KEY,
    producto_id     BIGINT NOT NULL REFERENCES inventario_v2.productos(id),
    origen          VARCHAR(16),
    destino         VARCHAR(16),
    tipo            inventario_v2.tipo_movimiento NOT NULL DEFAULT 'REQUISICION',
    cantidad        INTEGER NOT NULL CHECK (cantidad > 0),
    usuario         VARCHAR(128) NOT NULL DEFAULT '',
    metadata        JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.config_sede (
    sede                VARCHAR(16) PRIMARY KEY,
    tiempo_pronostico   INTEGER NOT NULL DEFAULT 15,
    minimo_compra       INTEGER NOT NULL DEFAULT 6,
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_config_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'))
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE INDEX IF NOT EXISTS ix_stock_actual_sede_updated ON inventario_v2.stock_actual (sede, updated_at);
CREATE INDEX IF NOT EXISTS ix_ventas_historicas_sede_updated ON inventario_v2.ventas_historicas (sede, updated_at);
CREATE INDEX IF NOT EXISTS ix_movimientos_created ON inventario_v2.movimientos (created_at DESC);
SQL);

        DB::unprepared(<<<'SQL'
INSERT INTO inventario_v2.config_sede (sede, tiempo_pronostico)
VALUES ('DORAL', 15), ('VIRTUDES', 15), ('ZAMORA', 15), ('CENTRO', 15), ('SAMBIL', 15)
ON CONFLICT (sede) DO NOTHING
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP SCHEMA IF EXISTS inventario_v2 CASCADE');
    }
};
