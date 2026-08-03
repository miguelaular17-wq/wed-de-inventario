import psycopg2
from datetime import datetime
from database.sqlserver import SQLServerConnection
from database.postgres import PostgresConnection
from config.config_manager import ConfigManager
from utils.logger import AppLogger

class CobranzasService:
    @staticmethod
    def execute():
        logger = AppLogger.get_logger()
        config = ConfigManager()
        billing_conn = None
        web_conn = None
        
        try:
            sede = config.get("sede", "JRZ")
            web = config.get("web_db")
            
            logger.info("[Cobranzas] 1. Ejecutando consulta de cuentas por cobrar en SQL Server...")
            billing_conn = SQLServerConnection.get_connection()
            
            query = """
            SELECT 
                cx.codigo_cliente AS [CODIGO CLIENTE],
                c.descripcion AS [NOMBRE CLIENTE],
                cx.id AS [ID],
                CAST(cx.fecha_emision AS DATE) AS [FECHA EMISION],
                cx.tipo_documento AS [TIPO CXC],
                cx.numero_documento AS [NUMERO DOCUMENTO], 
                cx.monto_neto_moneda2 AS [MONTO NETO $],
                cx.saldo_actual_moneda2 AS [SALDO $],
                DATEDIFF(day, cx.fecha_emision, GETDATE()) AS [DIAS DE DEUDA],
                CASE 
                    WHEN DATEDIFF(day, cx.fecha_emision, GETDATE()) >= 300 THEN 'CRITICO'
                    WHEN DATEDIFF(day, cx.fecha_emision, GETDATE()) > 60 THEN 'MOROSO'
                    ELSE 'RECIENTE'
                END AS [ESTADO CALCULADO],
                cx.usuario AS [USUARIO],
                cx.estacion AS [ESTACION],
                cx.codigo_caja AS [CODIGO CAJA]
            FROM cuentas_cobrar cx WITH (NOLOCK)
            JOIN clientes c WITH (NOLOCK) ON cx.codigo_cliente = c.codigo
            WHERE cx.saldo_actual_moneda2 > 0.5
              AND cx.tipo_documento IN ('FAC', 'NDD')
              AND cx.codigo_cliente NOT LIKE 'EXP%'
              AND (cx.estado <> 'Anulado' OR cx.estado IS NULL)
            """
            
            cursor = billing_conn.cursor()
            cursor.execute(query)
            rows = cursor.fetchall()
            logger.info(f"[Cobranzas] {len(rows)} documentos por cobrar obtenidos.")
            
            if not rows:
                return True

            logger.info("[Cobranzas] 2. Conectando a Supabase para subir historial de cobranzas...")
            web_conn = PostgresConnection.get_connection()
            wc = web_conn.cursor()
            
            today = datetime.now().strftime("%Y-%m-%d")
            
            today = datetime.now().strftime("%Y-%m-%d")
            
            # Borrar los datos de la sede para la fecha actual
            wc.execute("DELETE FROM inventario_v2.historial_cobranzas WHERE sede_nombre = %s AND fecha_registro = %s;", (sede, today))
            
            insert_query = """
                INSERT INTO inventario_v2.historial_cobranzas (
                    fecha_registro, sede_nombre, codigo_cliente, nombre_cliente, 
                    id_documento, fecha_emision, tipo_cxc, numero_documento, 
                    monto_neto, saldo, dias_deuda, estatus, 
                    usuario, estacion, codigo_caja, created_at, updated_at
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
                )
            """
            
            now = datetime.now()
            for row in rows:
                wc.execute(insert_query, (
                    today,
                    sede,
                    str(row[0]).strip() if row[0] else "",
                    str(row[1]).strip() if row[1] else "",
                    str(row[2]).strip() if row[2] else "",
                    row[3],
                    str(row[4]).strip() if row[4] else "",
                    str(row[5]).strip() if row[5] else "",
                    float(row[6]) if row[6] else 0.0,
                    float(row[7]) if row[7] else 0.0,
                    int(row[8]) if row[8] else 0,
                    str(row[9]).strip() if row[9] else "",
                    str(row[10]).strip() if row[10] else "",
                    str(row[11]).strip() if row[11] else "",
                    str(row[12]).strip() if row[12] else "",
                    now,
                    now
                ))
            
            web_conn.commit()
            logger.info(f"[Cobranzas] ✓ {len(rows)} registros subidos correctamente.")
            return True
            
        except Exception as e:
            logger.error(f"[Cobranzas] Error en subida: {str(e)}")
            if web_conn:
                try: web_conn.rollback()
                except Exception: pass
            return False
        finally:
            if billing_conn:
                try: billing_conn.close()
                except Exception: pass
            if web_conn:
                try: web_conn.close()
                except Exception: pass
