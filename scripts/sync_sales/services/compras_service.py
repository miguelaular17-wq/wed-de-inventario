from datetime import datetime

import psycopg2.extras
from config.config_manager import ConfigManager
from database.sqlserver import SQLServerConnection
from database.postgres import PostgresConnection
from utils.logger import AppLogger

# Nombres típicos en Profit / Suite para el código de proveedor en la factura de compra.
_COLS_PROVEEDOR_DOC = (
    "codigo_proveedor",
    "cod_proveedor",
    "proveedor",
    "id_proveedor",
)

# Solo JRZ tiene saldos de FAC compra confiables para Cuentas por Pagar.
_SEDES_CXP = frozenset({"JRZ"})

_DDL_CXP = """
CREATE TABLE IF NOT EXISTS inventario_v2.cuentas_por_pagar_suite (
    id bigserial PRIMARY KEY,
    sede varchar(32) NOT NULL,
    tipo_documento varchar(16) NOT NULL DEFAULT 'FAC',
    numero_documento varchar(64) NOT NULL,
    fecha date NULL,
    codigo_proveedor varchar(64) NULL,
    proveedor varchar(255) NULL,
    total_usd numeric(18,2) NOT NULL DEFAULT 0,
    total_bs numeric(18,2) NOT NULL DEFAULT 0,
    saldo_usd numeric(18,2) NOT NULL DEFAULT 0,
    saldo_bs numeric(18,2) NOT NULL DEFAULT 0,
    estado_suite varchar(64) NULL,
    credito boolean NOT NULL DEFAULT false,
    cuenta_por_pagar_id bigint NULL,
    synced_at timestamp NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (sede, tipo_documento, numero_documento)
);
ALTER TABLE inventario_v2.cuentas_por_pagar_suite
    ADD COLUMN IF NOT EXISTS cuenta_por_pagar_id bigint NULL;
ALTER TABLE inventario_v2.cuentas_por_pagar
    ADD COLUMN IF NOT EXISTS origen varchar(32) DEFAULT 'manual';
ALTER TABLE inventario_v2.cuentas_por_pagar
    ADD COLUMN IF NOT EXISTS referencia varchar(64) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS cuentas_por_pagar_origen_referencia_unique
    ON inventario_v2.cuentas_por_pagar (origen, referencia)
    WHERE referencia IS NOT NULL;
"""


class ComprasService:
    @staticmethod
    def execute():
        logger = AppLogger.get_logger()
        billing_conn = None
        web_conn = None

        try:
            logger.info("[Compras] 1. Ejecutando consulta de compras en SQL Server...")
            billing_conn = SQLServerConnection.get_connection()
            cursor = billing_conn.cursor()

            col_prov = ComprasService._columna_proveedor_doc(cursor)
            join_prov, select_prov = ComprasService._join_nombre_proveedor(cursor, col_prov)

            # Última FAC por artículo: cantidad, costo y nombre de proveedor.
            query = f"""
            SELECT
                ISNULL(a.codigo, t.articulo_raw) AS codigo,
                t.cantidad,
                t.costo,
                t.proveedor
            FROM (
                SELECT
                    i.articulo AS articulo_raw,
                    i.cantidad AS cantidad,
                    i.costo_unitario_moneda2 AS costo,
                    {select_prov} AS proveedor,
                    ROW_NUMBER() OVER (
                        PARTITION BY i.articulo
                        ORDER BY h.fecha_emision DESC
                    ) AS rn
                FROM documentos_compra h WITH (NOLOCK)
                JOIN documentos_compra_items i WITH (NOLOCK)
                    ON h.numero_documento = i.numero_documento
                   AND h.tipo_documento = i.tipo_documento
                {join_prov}
                WHERE h.tipo_documento = 'FAC'
                  AND (h.estado <> 'Anulado' OR h.estado IS NULL)
            ) t
            LEFT JOIN articulos_codigos ac WITH (NOLOCK) ON t.articulo_raw = ac.codigo
            LEFT JOIN articulos a WITH (NOLOCK) ON ac.articulo = a.id
            WHERE t.rn = 1
            """

            cursor.execute(query)
            rows = cursor.fetchall()
            logger.info(f"[Compras] {len(rows)} productos con historial de compra obtenidos.")
            if col_prov:
                logger.info(f"[Compras] Proveedor leído desde documentos_compra.{col_prov}.")
            else:
                logger.info("[Compras] No hay columna de proveedor en documentos_compra; solo cantidad/costo.")

            logger.info("[Compras] 2. Conectando a Supabase para actualizar compras y proveedores...")
            web_conn = PostgresConnection.get_connection()
            wc = web_conn.cursor()

            if rows:
                wc.execute(
                    "SELECT id, COALESCE(codigo, ''), COALESCE(proveedor, '') "
                    "FROM inventario_v2.productos"
                )
                # tramo de código → id
                prod_map = {}
                # id → proveedor actual en la web
                proveedor_web = {}
                for pid, codigo, proveedor_actual in wc.fetchall():
                    full = str(codigo).strip()
                    if not full:
                        continue
                    proveedor_web[pid] = str(proveedor_actual or "").strip()
                    prod_map[full] = pid
                    for part in full.split(" / "):
                        clean = part.strip()
                        if clean:
                            prod_map[clean] = pid

                compra_by_pid = {}
                proveedor_suite_by_pid = {}
                sin_match = 0

                for row in rows:
                    codigo = str(row[0]).strip() if row[0] else ""
                    if not codigo:
                        continue
                    pid = prod_map.get(codigo)
                    if not pid:
                        sin_match += 1
                        continue

                    compra_by_pid[pid] = (row[1], row[2])
                    proveedor = str(row[3]).strip() if len(row) > 3 and row[3] else ""
                    if proveedor and proveedor.upper() != "PRUEBA":
                        proveedor_suite_by_pid[pid] = proveedor[:256]

                compra_data = [
                    (cantidad, precio, pid)
                    for pid, (cantidad, precio) in compra_by_pid.items()
                ]
                proveedor_data = [
                    (prov, pid, prov)
                    for pid, prov in proveedor_suite_by_pid.items()
                    if proveedor_web.get(pid, "") != prov
                ]

                logger.info(
                    f"[Compras] 3. Actualizando {len(compra_data)} compras"
                    f" y {len(proveedor_data)} proveedores"
                    f" (sin match en web: {sin_match})..."
                )

                if compra_data:
                    psycopg2.extras.execute_batch(
                        wc,
                        """
                        UPDATE inventario_v2.productos
                        SET ultima_cantidad_compra = %s,
                            ultimo_costo_compra = %s,
                            updated_at = NOW()
                        WHERE id = %s
                        """,
                        compra_data,
                        page_size=2000,
                    )

                if proveedor_data:
                    psycopg2.extras.execute_batch(
                        wc,
                        """
                        UPDATE inventario_v2.productos
                        SET proveedor = %s,
                            updated_at = NOW()
                        WHERE id = %s
                          AND COALESCE(proveedor, '') IS DISTINCT FROM %s
                        """,
                        proveedor_data,
                        page_size=2000,
                    )

                web_conn.commit()
                logger.info("[Compras] 4. Sincronización de compras/proveedores finalizada.")
            else:
                logger.info("[Compras] Sin historial de compra por artículo; se continúa con CxP.")

            sede = str(ConfigManager().get("sede", "") or "").strip().upper()
            ComprasService._sync_cuentas_por_pagar(cursor, web_conn, sede)
            return True

        except Exception as e:
            logger.error(f"[Compras] Error en sincronización: {str(e)}")
            if web_conn:
                web_conn.rollback()
            return False

        finally:
            if web_conn:
                web_conn.close()
            if billing_conn:
                billing_conn.close()

    @staticmethod
    def _sync_cuentas_por_pagar(billing_cursor, web_conn, sede):
        """Sube FAC de compra con saldo a cuentas_por_pagar_suite (solo JRZ)."""
        logger = AppLogger.get_logger()
        if sede not in _SEDES_CXP:
            logger.info(
                f"[Compras/CxP] Sede {sede or '?'} omitida: "
                "cuentas por pagar Suite solo se sincronizan desde JRZ."
            )
            return

        cols = ComprasService._columnas(billing_cursor, "documentos_compra")
        if "saldo" not in cols and "saldo_moneda2" not in cols:
            logger.warning(
                "[Compras/CxP] documentos_compra no tiene columnas saldo/saldo_moneda2; omitido."
            )
            return

        saldo_usd_expr = (
            "CAST(ISNULL(h.saldo_moneda2, 0) AS decimal(18,2))"
            if "saldo_moneda2" in cols
            else "CAST(0 AS decimal(18,2))"
        )
        saldo_bs_expr = (
            "CAST(ISNULL(h.saldo, 0) AS decimal(18,2))"
            if "saldo" in cols
            else "CAST(0 AS decimal(18,2))"
        )
        total_usd_expr = (
            "CAST(ISNULL(h.total_moneda2, 0) AS decimal(18,2))"
            if "total_moneda2" in cols
            else "CAST(0 AS decimal(18,2))"
        )
        total_bs_expr = (
            "CAST(ISNULL(h.total, 0) AS decimal(18,2))"
            if "total" in cols
            else "CAST(0 AS decimal(18,2))"
        )
        credito_expr = (
            "CAST(CASE WHEN ISNULL(h.credito, 0) <> 0 THEN 1 ELSE 0 END AS bit)"
            if "credito" in cols
            else "CAST(0 AS bit)"
        )
        estado_expr = "h.estado" if "estado" in cols else "CAST(NULL AS varchar(64))"

        col_prov = ComprasService._columna_proveedor_doc(billing_cursor)
        join_prov, select_prov = ComprasService._join_nombre_proveedor(
            billing_cursor, col_prov
        )
        codigo_prov_expr = (
            f"NULLIF(LTRIM(RTRIM(CAST(h.[{col_prov}] AS VARCHAR(64)))), '')"
            if col_prov
            else "CAST(NULL AS VARCHAR(64))"
        )

        query = f"""
        SELECT
            LTRIM(RTRIM(CAST(h.numero_documento AS VARCHAR(64)))) AS numero_documento,
            CAST(h.fecha_emision AS date) AS fecha,
            {codigo_prov_expr} AS codigo_proveedor,
            {select_prov} AS proveedor,
            {total_usd_expr} AS total_usd,
            {total_bs_expr} AS total_bs,
            {saldo_usd_expr} AS saldo_usd,
            {saldo_bs_expr} AS saldo_bs,
            {estado_expr} AS estado_suite,
            {credito_expr} AS credito
        FROM documentos_compra h WITH (NOLOCK)
        {join_prov}
        WHERE h.tipo_documento = 'FAC'
          AND (h.estado <> 'Anulado' OR h.estado IS NULL)
          AND (
                ABS({saldo_usd_expr}) > 0.01
             OR ABS({saldo_bs_expr}) > 0.01
          )
        """

        logger.info("[Compras/CxP] Leyendo FAC con saldo pendiente en Suite JRZ...")
        billing_cursor.execute(query)
        rows = billing_cursor.fetchall()
        logger.info(f"[Compras/CxP] {len(rows)} facturas con saldo.")

        ahora = datetime.utcnow()
        batch = []
        for row in rows:
            numero = str(row[0]).strip() if row[0] is not None else ""
            if not numero:
                continue
            fecha = row[1]
            if hasattr(fecha, "date"):
                fecha = fecha.date()
            codigo = str(row[2]).strip() if row[2] else None
            proveedor = str(row[3]).strip() if row[3] else None
            if proveedor and len(proveedor) > 255:
                proveedor = proveedor[:255]
            total_usd = float(row[4] or 0)
            total_bs = float(row[5] or 0)
            saldo_usd = float(row[6] or 0)
            saldo_bs = float(row[7] or 0)
            estado = str(row[8]).strip() if row[8] else None
            credito = bool(row[9]) if row[9] is not None else False
            batch.append(
                (
                    sede,
                    "FAC",
                    numero,
                    fecha,
                    codigo,
                    proveedor,
                    total_usd,
                    total_bs,
                    saldo_usd,
                    saldo_bs,
                    estado,
                    credito,
                    ahora,
                    ahora,
                    ahora,
                )
            )

        wc = web_conn.cursor()
        wc.execute(_DDL_CXP)
        web_conn.commit()

        if batch:
            psycopg2.extras.execute_batch(
                wc,
                """
                INSERT INTO inventario_v2.cuentas_por_pagar_suite (
                    sede, tipo_documento, numero_documento, fecha,
                    codigo_proveedor, proveedor,
                    total_usd, total_bs, saldo_usd, saldo_bs,
                    estado_suite, credito, synced_at, created_at, updated_at
                ) VALUES (
                    %s, %s, %s, %s,
                    %s, %s,
                    %s, %s, %s, %s,
                    %s, %s, %s, %s, %s
                )
                ON CONFLICT (sede, tipo_documento, numero_documento) DO UPDATE SET
                    fecha = EXCLUDED.fecha,
                    codigo_proveedor = EXCLUDED.codigo_proveedor,
                    proveedor = EXCLUDED.proveedor,
                    total_usd = EXCLUDED.total_usd,
                    total_bs = EXCLUDED.total_bs,
                    -- Si ya se paga desde la web, el saldo lo manda Finanzas.
                    saldo_usd = CASE
                        WHEN cuentas_por_pagar_suite.cuenta_por_pagar_id IS NOT NULL
                        THEN cuentas_por_pagar_suite.saldo_usd
                        ELSE EXCLUDED.saldo_usd
                    END,
                    saldo_bs = CASE
                        WHEN cuentas_por_pagar_suite.cuenta_por_pagar_id IS NOT NULL
                        THEN cuentas_por_pagar_suite.saldo_bs
                        ELSE EXCLUDED.saldo_bs
                    END,
                    estado_suite = EXCLUDED.estado_suite,
                    credito = EXCLUDED.credito,
                    synced_at = EXCLUDED.synced_at,
                    updated_at = EXCLUDED.updated_at
                """,
                batch,
                page_size=500,
            )

        # Abre cuenta por pagar en Finanzas (solo si aún no existe) para pagar desde la web.
        vinculadas = ComprasService._asegurar_cuentas_finanzas(wc, sede, ahora)
        web_conn.commit()

        # Quitar espejo Suite solo si ya no debe en Suite y tampoco queda saldo abierto en la web.
        wc.execute(
            """
            DELETE FROM inventario_v2.cuentas_por_pagar_suite s
            WHERE s.sede = %s
              AND (s.synced_at IS NULL OR s.synced_at < %s)
              AND (
                    s.cuenta_por_pagar_id IS NULL
                 OR NOT EXISTS (
                        SELECT 1
                        FROM inventario_v2.cuentas_por_pagar c
                        WHERE c.id = s.cuenta_por_pagar_id
                          AND c.estado = 'abierta'
                          AND c.saldo > 0.01
                    )
              )
            """,
            (sede, ahora),
        )
        borradas = wc.rowcount if wc.rowcount is not None else 0
        web_conn.commit()
        logger.info(
            f"[Compras/CxP] ✓ {len(batch)} FAC con saldo; "
            f"{vinculadas} listas para pagar en web; "
            f"{borradas} cerradas/eliminadas."
        )

    @staticmethod
    def _asegurar_cuentas_finanzas(wc, sede, ahora):
        """Crea cuentas_por_pagar para FAC Suite sin vínculo (pago desde la web)."""
        logger = AppLogger.get_logger()
        wc.execute(
            """
            SELECT id, numero_documento, fecha, proveedor, codigo_proveedor,
                   GREATEST(COALESCE(saldo_usd, 0), 0) AS deuda
            FROM inventario_v2.cuentas_por_pagar_suite
            WHERE sede = %s
              AND synced_at >= %s
              AND cuenta_por_pagar_id IS NULL
              AND GREATEST(COALESCE(saldo_usd, 0), 0) > 0.01
            """,
            (sede, ahora),
        )
        pendientes = wc.fetchall()
        if not pendientes:
            return 0

        creadas = 0
        for sid, numero, fecha, proveedor, codigo, deuda in pendientes:
            numero = str(numero).strip()
            beneficiario = (proveedor or codigo or "Proveedor Suite")[:255]
            motivo = f"FAC {numero} Suite JRZ"

            wc.execute(
                """
                SELECT id FROM inventario_v2.cuentas_por_pagar
                WHERE origen = 'suite_jrz' AND referencia = %s
                LIMIT 1
                """,
                (numero,),
            )
            row = wc.fetchone()
            if row:
                cuenta_id = row[0]
            else:
                wc.execute(
                    """
                    INSERT INTO inventario_v2.cuentas_por_pagar (
                        fecha, beneficiario, tipo_gasto, motivo, sede, moneda,
                        monto_total, monto_pagado, saldo, estado,
                        origen, referencia, created_at, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, 'USD',
                        %s, 0, %s, 'abierta',
                        'suite_jrz', %s, %s, %s
                    )
                    RETURNING id
                    """,
                    (
                        fecha or ahora.date(),
                        beneficiario,
                        "087 - PAGO PROVEEDORES",
                        motivo,
                        sede,
                        float(deuda),
                        float(deuda),
                        numero,
                        ahora,
                        ahora,
                    ),
                )
                cuenta_id = wc.fetchone()[0]

            wc.execute(
                """
                UPDATE inventario_v2.cuentas_por_pagar_suite
                SET cuenta_por_pagar_id = %s, updated_at = %s
                WHERE id = %s
                """,
                (cuenta_id, ahora, sid),
            )
            creadas += 1

        logger.info(f"[Compras/CxP] {creadas} cuentas de Finanzas vinculadas para pago web.")
        return creadas

    @staticmethod
    def _columnas(cursor, tabla):
        cursor.execute(
            """
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
              AND TABLE_SCHEMA = 'dbo'
            """,
            (tabla,),
        )
        return {str(r[0]).lower() for r in cursor.fetchall()}

    @staticmethod
    def _columna_proveedor_doc(cursor):
        cols = ComprasService._columnas(cursor, "documentos_compra")
        for nombre in _COLS_PROVEEDOR_DOC:
            if nombre.lower() in cols:
                return nombre
        return None

    @staticmethod
    def _join_nombre_proveedor(cursor, col_prov):
        """Devuelve (JOIN SQL, expresión SELECT) para el nombre del proveedor."""
        if not col_prov:
            return "", "CAST(NULL AS VARCHAR(256))"

        cursor.execute(
            """
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE = 'BASE TABLE'
              AND TABLE_SCHEMA = 'dbo'
              AND LOWER(TABLE_NAME) IN ('proveedores', 'proveedor')
            """
        )
        tablas = [str(r[0]) for r in cursor.fetchall()]
        for tabla in tablas:
            cols = ComprasService._columnas(cursor, tabla)
            codigo_col = next(
                (c for c in ("codigo", "cod_proveedor", "codigo_proveedor") if c in cols),
                None,
            )
            nombre_col = next(
                (c for c in ("descripcion", "nombre", "razon_social", "proveedor") if c in cols),
                None,
            )
            if codigo_col and nombre_col:
                join = (
                    f"LEFT JOIN [{tabla}] pv WITH (NOLOCK) "
                    f"ON h.[{col_prov}] = pv.[{codigo_col}]"
                )
                select = (
                    f"COALESCE(NULLIF(LTRIM(RTRIM(pv.[{nombre_col}])), ''), "
                    f"NULLIF(LTRIM(RTRIM(CAST(h.[{col_prov}] AS VARCHAR(256)))), ''))"
                )
                return join, select

        select = f"NULLIF(LTRIM(RTRIM(CAST(h.[{col_prov}] AS VARCHAR(256)))), '')"
        return "", select
