import json
from datetime import datetime
from database.sqlserver import SQLServerConnection
from database.postgres import PostgresConnection
from config.config_manager import ConfigManager
from utils.logger import AppLogger

# ─────────────────────────────────────────────────────────────────────────────
# Query principal: trae artículos vendidos (tipo_fila=1) y abonos (tipo_fila=2)
# para todas las facturas con saldo pendiente > $0.50
# ─────────────────────────────────────────────────────────────────────────────
COBRANZAS_QUERY = """
WITH FacturasActivas AS (
    SELECT id, codigo_cliente, fecha_emision, estacion, tipo_documento,
           numero_documento, monto_neto_moneda2, saldo_actual_moneda2
    FROM cuentas_cobrar WITH(NOLOCK)
    WHERE saldo_actual_moneda2 > 0.5
      AND tipo_documento IN ('FAC', 'NDD', 'ND')
      AND (estado <> 'Anulado' OR estado IS NULL)
),
RenglonesArticulos AS (
    SELECT
        f.codigo_cliente,
        f.fecha_emision       AS fecha_factura,
        f.numero_documento    AS factura_padre,
        f.fecha_emision       AS fecha_doc,
        f.estacion,
        f.tipo_documento,
        f.numero_documento,
        a.descripcion         AS detalle,
        vi.cantidad,
        (vi.total_neto_moneda2 / NULLIF(vi.cantidad, 0)) AS precio_unitario,
        vi.total_neto_moneda2 AS total_renglon,
        f.monto_neto_moneda2  AS total_factura,
        f.saldo_actual_moneda2 AS saldo_pendiente,
        1                     AS tipo_fila,
        vi.item_numero
    FROM FacturasActivas f
    JOIN documentos_venta_items vi WITH(NOLOCK)
        ON f.numero_documento = vi.numero_documento
       AND f.tipo_documento   = vi.tipo_documento
    LEFT JOIN articulos a WITH(NOLOCK)
        ON vi.id_articulo = a.id
),
RenglonesPagos AS (
    SELECT
        f.codigo_cliente,
        f.fecha_emision        AS fecha_factura,
        f.numero_documento     AS factura_padre,
        pago.fecha_emision     AS fecha_doc,
        pago.estacion,
        pago.tipo_documento,
        pago.numero_documento,
        pago.descripcion       AS detalle,
        NULL                   AS cantidad,
        NULL                   AS precio_unitario,
        pp.monto_moneda2       AS total_renglon,
        NULL                   AS total_factura,
        NULL                   AS saldo_pendiente,
        2                      AS tipo_fila,
        0                      AS item_numero
    FROM FacturasActivas f
    JOIN pagos_cuentas_cobrar pp WITH(NOLOCK) ON f.id = pp.id_factura
    JOIN cuentas_cobrar pago WITH(NOLOCK)     ON pp.id_pago = pago.id
)
SELECT
    r.codigo_cliente,
    c.descripcion             AS nombre_cliente,
    CAST(r.fecha_doc AS DATE) AS fecha_doc,
    r.estacion,
    CASE WHEN r.tipo_fila = 2 THEN 'ABONO' ELSE r.tipo_documento END AS tipo_documento,
    r.numero_documento,
    CASE WHEN r.tipo_fila = 2
         THEN 'Aplica a FAC: ' + r.factura_padre
         ELSE '' END          AS referencia,
    r.factura_padre,
    r.detalle,
    r.cantidad,
    r.precio_unitario,
    CASE WHEN r.tipo_fila = 1 THEN r.total_renglon ELSE NULL END AS total_renglon,
    CASE WHEN r.tipo_fila = 2 THEN r.total_renglon ELSE NULL END AS total_abono,
    CASE WHEN r.tipo_fila = 1
              AND ROW_NUMBER() OVER(
                    PARTITION BY r.codigo_cliente, r.factura_padre, r.tipo_fila
                    ORDER BY r.item_numero) = 1
         THEN r.total_factura ELSE NULL END AS total_factura,
    CASE WHEN r.tipo_fila = 1
              AND ROW_NUMBER() OVER(
                    PARTITION BY r.codigo_cliente, r.factura_padre, r.tipo_fila
                    ORDER BY r.item_numero) = 1
         THEN r.saldo_pendiente ELSE NULL END AS saldo_pendiente,
    r.tipo_fila,
    CAST(r.fecha_factura AS DATE) AS fecha_factura,
    DATEDIFF(day, r.fecha_factura, GETDATE()) AS dias_deuda
FROM (
    SELECT * FROM RenglonesArticulos
    UNION ALL
    SELECT * FROM RenglonesPagos
) r
JOIN clientes c WITH(NOLOCK) ON r.codigo_cliente = c.codigo
ORDER BY
    r.codigo_cliente ASC,
    r.fecha_factura  ASC,
    r.factura_padre  ASC,
    r.tipo_fila      ASC,
    r.fecha_doc      ASC,
    r.item_numero    ASC;
"""

INSERT_QUERY = """
    INSERT INTO inventario_v2.historial_cobranzas (
        fecha_registro, sede_nombre, codigo_cliente, nombre_cliente,
        id_documento, fecha_emision, tipo_cxc, numero_documento,
        monto_neto, saldo, dias_deuda, estatus,
        usuario, estacion, codigo_caja,
        tipo_fila, factura_padre, referencia, detalle,
        cantidad, precio_unitario, total_renglon,
        total_abono, total_factura, saldo_pendiente,
        created_at, updated_at
    ) VALUES (
        %s, %s, %s, %s,
        %s, %s, %s, %s,
        %s, %s, %s, %s,
        NULL, %s, NULL,
        %s, %s, %s, %s,
        %s, %s, %s,
        %s, %s, %s,
        NOW(), NOW()
    )
"""


def _calcular_estatus(dias: int) -> str:
    if dias >= 300:
        return 'CRITICO'
    if dias > 60:
        return 'MOROSO'
    return 'RECIENTE'


class CobranzasService:
    @staticmethod
    def execute(ui_callback=None):
        logger = AppLogger.get_logger()
        config = ConfigManager()
        billing_conn = None
        web_conn = None

        try:
            sede = config.get("sede", "JRZ")

            logger.info("[Cobranzas] 1. Ejecutando consulta detallada en SQL Server...")
            billing_conn = SQLServerConnection.get_connection()
            cursor = billing_conn.cursor()
            cursor.execute(COBRANZAS_QUERY)
            rows = cursor.fetchall()
            logger.info(f"[Cobranzas] {len(rows)} renglones obtenidos (artículos + abonos).")

            if not rows:
                logger.info("[Cobranzas] Sin facturas pendientes. Nada que subir.")
                return True

            logger.info("[Cobranzas] 2. Conectando a Supabase...")
            web_conn = PostgresConnection.get_connection()
            wc = web_conn.cursor()

            today = datetime.now().strftime("%Y-%m-%d")

            # Reemplazar snapshot completo de la sede para hoy
            wc.execute(
                "DELETE FROM inventario_v2.historial_cobranzas "
                "WHERE sede_nombre = %s AND fecha_registro = %s;",
                (sede, today)
            )

            # ── Mapeo de columnas del SELECT ──────────────────────────────
            # 0  codigo_cliente
            # 1  nombre_cliente
            # 2  fecha_doc        (DATE)
            # 3  estacion
            # 4  tipo_documento   ('FAC','ND','NDD','ABONO')
            # 5  numero_documento
            # 6  referencia
            # 7  factura_padre
            # 8  detalle
            # 9  cantidad         (INT or None)
            # 10 precio_unitario  (DECIMAL or None)
            # 11 total_renglon    (DECIMAL or None)  → artículos
            # 12 total_abono      (DECIMAL or None)  → pagos
            # 13 total_factura    (DECIMAL or None)
            # 14 saldo_pendiente  (DECIMAL or None)
            # 15 tipo_fila        (1 or 2)
            # 16 fecha_factura    (DATE)
            # 17 dias_deuda       (INT)

            batch = []
            # Para no duplicar saldo/monto_neto, rastreamos qué facturas ya tuvieron su fila principal
            facturas_con_saldo = set()

            for row in rows:
                codigo_cliente   = str(row[0]).strip()  if row[0]  else ''
                nombre_cliente   = str(row[1]).strip()  if row[1]  else ''
                fecha_doc        = row[2]
                estacion         = str(row[3]).strip()  if row[3]  else ''
                tipo_documento   = str(row[4]).strip()  if row[4]  else ''
                numero_documento = str(row[5]).strip()  if row[5]  else ''
                referencia       = str(row[6]).strip()  if row[6]  else ''
                factura_padre    = str(row[7]).strip()  if row[7]  else ''
                detalle          = str(row[8]).strip()  if row[8]  else ''
                cantidad         = int(row[9])           if row[9]  is not None else None
                precio_unitario  = float(row[10])        if row[10] is not None else None
                total_renglon    = float(row[11])        if row[11] is not None else None
                total_abono      = float(row[12])        if row[12] is not None else None
                total_factura    = float(row[13])        if row[13] is not None else None
                saldo_pendiente  = float(row[14])        if row[14] is not None else None
                tipo_fila        = int(row[15])          if row[15] is not None else 1
                fecha_factura    = row[16]
                dias_deuda       = int(row[17])          if row[17] is not None else 0
                estatus          = _calcular_estatus(dias_deuda)
                id_documento     = factura_padre or numero_documento

                # Clave única por factura para asignar saldo/monto solo una vez
                clave_factura = (codigo_cliente, numero_documento if tipo_fila == 1 else factura_padre)

                if tipo_fila == 1 and clave_factura not in facturas_con_saldo:
                    # Primera fila de artículo para esta factura → lleva el saldo y monto
                    monto_neto_row = total_factura   or 0.0
                    saldo_row      = saldo_pendiente or 0.0
                    facturas_con_saldo.add(clave_factura)
                else:
                    # Renglones adicionales o abonos → sin saldo duplicado
                    monto_neto_row = 0.0
                    saldo_row      = 0.0

                batch.append((
                    today,            # fecha_registro
                    sede,             # sede_nombre
                    codigo_cliente,   # codigo_cliente
                    nombre_cliente,   # nombre_cliente
                    id_documento,     # id_documento (número de factura, estable)
                    fecha_doc,        # fecha_emision (del renglón)
                    tipo_documento,   # tipo_cxc
                    numero_documento, # numero_documento
                    monto_neto_row,   # monto_neto  ← ahora correcto
                    saldo_row,        # saldo       ← ahora correcto
                    dias_deuda,       # dias_deuda
                    estatus,          # estatus
                    estacion,         # estacion
                    tipo_fila,        # tipo_fila
                    factura_padre,    # factura_padre
                    referencia,       # referencia
                    detalle,          # detalle
                    cantidad,         # cantidad
                    precio_unitario,  # precio_unitario
                    total_renglon,    # total_renglon
                    total_abono,      # total_abono
                    total_factura,    # total_factura
                    saldo_pendiente,  # saldo_pendiente
                ))

            # Insertar en lotes de 500 filas con commit intermedio
            chunk_size = 500
            total_insertados = 0
            for i in range(0, len(batch), chunk_size):
                chunk = batch[i:i + chunk_size]
                for record in chunk:
                    wc.execute(INSERT_QUERY, record)
                web_conn.commit()
                total_insertados += len(chunk)
                logger.info(f"[Cobranzas]   → {total_insertados}/{len(batch)} renglones subidos...")

            logger.info(f"[Cobranzas] ✓ {total_insertados} renglones subidos correctamente.")
            return True

        except Exception as e:
            logger.error(f"[Cobranzas] Error: {str(e)}")
            if web_conn:
                try: web_conn.rollback()
                except Exception: pass
            return False
        finally:
            if ui_callback:
                try: ui_callback()
                except Exception: pass
            if billing_conn:
                try: billing_conn.close()
                except Exception: pass
            if web_conn:
                try: web_conn.close()
                except Exception: pass

