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
CREATE TABLE IF NOT EXISTS inventario_v2.nomina_empleados (
    id                  BIGSERIAL PRIMARY KEY,
    cliente_id          BIGINT NOT NULL REFERENCES clientes(id) ON DELETE RESTRICT,
    user_id             BIGINT REFERENCES users(id) ON DELETE SET NULL,
    email               VARCHAR(255),
    telefono            VARCHAR(64),
    fecha_ingreso       DATE,
    cargo               VARCHAR(128),
    sede                VARCHAR(16),
    salario_base        NUMERIC(12,2) NOT NULL DEFAULT 0,
    tipo_salario        VARCHAR(24) NOT NULL DEFAULT 'QUINCENAL',
    estado              VARCHAR(16) NOT NULL DEFAULT 'ACTIVO',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_nomina_empleados_tipo_salario
        CHECK (tipo_salario IN ('MENSUAL', 'QUINCENAL', 'SOLO_COMISION')),
    CONSTRAINT ck_nomina_empleados_estado
        CHECK (estado IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT uq_nomina_empleados_cliente UNIQUE (cliente_id)
);

CREATE INDEX IF NOT EXISTS idx_nomina_empleados_user
    ON inventario_v2.nomina_empleados (user_id);
CREATE INDEX IF NOT EXISTS idx_nomina_empleados_estado
    ON inventario_v2.nomina_empleados (estado);
CREATE INDEX IF NOT EXISTS idx_nomina_empleados_sede
    ON inventario_v2.nomina_empleados (sede);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_empleado_vendedores (
    id                      BIGSERIAL PRIMARY KEY,
    empleado_id             BIGINT NOT NULL REFERENCES inventario_v2.nomina_empleados(id) ON DELETE CASCADE,
    nombre_vendedor         VARCHAR(255) NOT NULL,
    nombre_normalizado      VARCHAR(255) NOT NULL,
    codigo_profit           VARCHAR(64),
    sede                    VARCHAR(16),
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_nomina_vendedor_alias UNIQUE (nombre_normalizado)
);

CREATE INDEX IF NOT EXISTS idx_nomina_vendedor_empleado
    ON inventario_v2.nomina_empleado_vendedores (empleado_id);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_reglas_comision (
    id                  BIGSERIAL PRIMARY KEY,
    nombre              VARCHAR(255) NOT NULL,
    nivel               VARCHAR(24) NOT NULL,
    producto_id         BIGINT REFERENCES inventario_v2.productos(id) ON DELETE SET NULL,
    codigo_producto     VARCHAR(64),
    categoria           VARCHAR(256),
    subcategoria        VARCHAR(256),
    porcentaje          NUMERIC(8,4) NOT NULL,
    base_comisionable   VARCHAR(24) NOT NULL DEFAULT 'NETO',
    fecha_inicio        DATE NOT NULL,
    fecha_fin           DATE,
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_nomina_reglas_nivel
        CHECK (nivel IN ('PRODUCTO', 'SUBCATEGORIA', 'CATEGORIA', 'GENERAL')),
    CONSTRAINT ck_nomina_reglas_base
        CHECK (base_comisionable IN ('NETO', 'MARGEN', 'TOTAL')),
    CONSTRAINT ck_nomina_reglas_porcentaje
        CHECK (porcentaje >= 0 AND porcentaje <= 100),
    CONSTRAINT ck_nomina_reglas_vigencia
        CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
);

CREATE INDEX IF NOT EXISTS idx_nomina_reglas_vigencia
    ON inventario_v2.nomina_reglas_comision (activo, fecha_inicio, fecha_fin);
CREATE INDEX IF NOT EXISTS idx_nomina_reglas_producto
    ON inventario_v2.nomina_reglas_comision (producto_id);
CREATE INDEX IF NOT EXISTS idx_nomina_reglas_codigo
    ON inventario_v2.nomina_reglas_comision (codigo_producto);
CREATE INDEX IF NOT EXISTS idx_nomina_reglas_categoria
    ON inventario_v2.nomina_reglas_comision (categoria, subcategoria);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_config (
    clave               VARCHAR(64) PRIMARY KEY,
    valor               TEXT NOT NULL,
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO inventario_v2.nomina_config (clave, valor)
VALUES
    ('base_comisionable_default', 'NETO'),
    ('moneda', 'USD')
ON CONFLICT (clave) DO NOTHING;

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_periodos (
    id                  BIGSERIAL PRIMARY KEY,
    fecha_inicio        DATE NOT NULL,
    fecha_fin           DATE NOT NULL,
    etiqueta            VARCHAR(32) NOT NULL,
    estado              VARCHAR(16) NOT NULL DEFAULT 'ABIERTO',
    calculado_at        TIMESTAMPTZ,
    calculado_por       BIGINT REFERENCES users(id) ON DELETE SET NULL,
    aprobado_at         TIMESTAMPTZ,
    aprobado_por        BIGINT REFERENCES users(id) ON DELETE SET NULL,
    pagado_at           TIMESTAMPTZ,
    pagado_por          BIGINT REFERENCES users(id) ON DELETE SET NULL,
    cerrado_at          TIMESTAMPTZ,
    cerrado_por         BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_nomina_periodos_fechas
        CHECK (fecha_fin >= fecha_inicio),
    CONSTRAINT ck_nomina_periodos_estado
        CHECK (estado IN ('ABIERTO', 'CALCULADO', 'APROBADO', 'PAGADO', 'CERRADO')),
    CONSTRAINT uq_nomina_periodos_rango UNIQUE (fecha_inicio, fecha_fin)
);

CREATE INDEX IF NOT EXISTS idx_nomina_periodos_estado
    ON inventario_v2.nomina_periodos (estado);
CREATE INDEX IF NOT EXISTS idx_nomina_periodos_fechas
    ON inventario_v2.nomina_periodos (fecha_inicio, fecha_fin);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_comision_registros (
    id                      BIGSERIAL PRIMARY KEY,
    periodo_id              BIGINT NOT NULL REFERENCES inventario_v2.nomina_periodos(id) ON DELETE CASCADE,
    empleado_id             BIGINT NOT NULL REFERENCES inventario_v2.nomina_empleados(id) ON DELETE RESTRICT,
    ventas_detalle_id       BIGINT REFERENCES inventario_v2.ventas_detalle(id) ON DELETE SET NULL,
    sede                    VARCHAR(16),
    tipo_documento          VARCHAR(8) NOT NULL,
    numero_documento        VARCHAR(32) NOT NULL,
    factura_origen          VARCHAR(32),
    fecha                   DATE NOT NULL,
    cliente                 VARCHAR(255),
    vendedor                VARCHAR(255),
    producto_id             BIGINT REFERENCES inventario_v2.productos(id) ON DELETE SET NULL,
    codigo_producto         VARCHAR(64),
    nombre_producto         TEXT,
    categoria               VARCHAR(256),
    subcategoria            VARCHAR(256),
    cantidad                NUMERIC(18,4) NOT NULL DEFAULT 0,
    precio_unitario         NUMERIC(12,2) NOT NULL DEFAULT 0,
    base_monto              NUMERIC(12,2) NOT NULL DEFAULT 0,
    base_tipo               VARCHAR(24) NOT NULL DEFAULT 'NETO',
    porcentaje              NUMERIC(8,4) NOT NULL,
    monto_comision          NUMERIC(12,2) NOT NULL DEFAULT 0,
    regla_id                BIGINT REFERENCES inventario_v2.nomina_reglas_comision(id) ON DELETE SET NULL,
    regla_snapshot          JSONB,
    origen                  VARCHAR(24) NOT NULL DEFAULT 'CALCULO',
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_nomina_comision_tipo_doc
        CHECK (tipo_documento IN ('FAC', 'DEV', 'AJUSTE')),
    CONSTRAINT ck_nomina_comision_base
        CHECK (base_tipo IN ('NETO', 'MARGEN', 'TOTAL')),
    CONSTRAINT ck_nomina_comision_origen
        CHECK (origen IN ('CALCULO', 'DEVOLUCION', 'ANULACION', 'MANUAL'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_nomina_comision_linea
    ON inventario_v2.nomina_comision_registros (periodo_id, ventas_detalle_id)
    WHERE ventas_detalle_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_nomina_comision_periodo_empleado
    ON inventario_v2.nomina_comision_registros (periodo_id, empleado_id);
CREATE INDEX IF NOT EXISTS idx_nomina_comision_fecha
    ON inventario_v2.nomina_comision_registros (fecha);
CREATE INDEX IF NOT EXISTS idx_nomina_comision_documento
    ON inventario_v2.nomina_comision_registros (sede, tipo_documento, numero_documento);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_registros (
    id                      BIGSERIAL PRIMARY KEY,
    periodo_id              BIGINT NOT NULL REFERENCES inventario_v2.nomina_periodos(id) ON DELETE CASCADE,
    empleado_id             BIGINT NOT NULL REFERENCES inventario_v2.nomina_empleados(id) ON DELETE RESTRICT,
    salario_base            NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_comisiones        NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_bonificaciones    NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_otros_ingresos    NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_deducciones       NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_ajustes           NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_pagar             NUMERIC(12,2) NOT NULL DEFAULT 0,
    observaciones           TEXT,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_nomina_registro_empleado_periodo UNIQUE (periodo_id, empleado_id)
);

CREATE INDEX IF NOT EXISTS idx_nomina_registros_empleado
    ON inventario_v2.nomina_registros (empleado_id);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_ajustes (
    id                      BIGSERIAL PRIMARY KEY,
    nomina_registro_id      BIGINT NOT NULL REFERENCES inventario_v2.nomina_registros(id) ON DELETE CASCADE,
    tipo                    VARCHAR(24) NOT NULL,
    concepto                VARCHAR(255) NOT NULL,
    monto                   NUMERIC(12,2) NOT NULL,
    usuario_id              BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_nomina_ajustes_tipo
        CHECK (tipo IN ('BONIFICACION', 'OTRO_INGRESO', 'DEDUCCION', 'AJUSTE'))
);

CREATE INDEX IF NOT EXISTS idx_nomina_ajustes_registro
    ON inventario_v2.nomina_ajustes (nomina_registro_id);

CREATE TABLE IF NOT EXISTS inventario_v2.nomina_audit_logs (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT REFERENCES users(id) ON DELETE SET NULL,
    accion                  VARCHAR(64) NOT NULL,
    entidad                 VARCHAR(64) NOT NULL,
    entidad_id              BIGINT,
    valores_anteriores      JSONB,
    valores_nuevos          JSONB,
    ip                      VARCHAR(45),
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_nomina_audit_entidad
    ON inventario_v2.nomina_audit_logs (entidad, entidad_id);
CREATE INDEX IF NOT EXISTS idx_nomina_audit_user
    ON inventario_v2.nomina_audit_logs (user_id);
CREATE INDEX IF NOT EXISTS idx_nomina_audit_created
    ON inventario_v2.nomina_audit_logs (created_at);
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TABLE IF EXISTS inventario_v2.nomina_audit_logs CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_ajustes CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_registros CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_comision_registros CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_periodos CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_config CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_reglas_comision CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_empleado_vendedores CASCADE;
DROP TABLE IF EXISTS inventario_v2.nomina_empleados CASCADE;
SQL);
    }
};
