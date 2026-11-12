import psycopg2.extras
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

            if not rows:
                return True

            logger.info("[Compras] 2. Conectando a Supabase para actualizar compras y proveedores...")
            web_conn = PostgresConnection.get_connection()
            wc = web_conn.cursor()

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
