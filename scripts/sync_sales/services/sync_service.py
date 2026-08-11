import time
import json
from datetime import datetime
from database.sqlserver import SQLServerConnection
from database.postgres import PostgresConnection
from config.config_manager import ConfigManager
from config.state_manager import StateManager
from utils.logger import AppLogger
from utils.helpers import get_sql_query
from utils.product_matcher import buscar_producto_web
from services.snapshot_service import SnapshotService
from services.heartbeat_service import HeartbeatService
from services.command_poller import CommandPoller

class SyncService:
    def __init__(self):
        self.stop_event = None
        self.is_syncing = False

    def start(self, stop_event):
        self.stop_event = stop_event
        self.is_syncing = True
        logger = AppLogger.get_logger()
        config = ConfigManager()
        
        interval_seconds = config.get("interval_seconds", 1800)
        last_snapshot_date = None
        
        while not self.stop_event.is_set():
            today = datetime.now().strftime("%Y-%m-%d")
            success = True

            if last_snapshot_date != today:
                logger.info("=" * 60)
                logger.info(f"NUEVO DÍA DETECTADO ({today}). Iniciando carga de apertura...")
                logger.info("=" * 60)

                # ── Módulo 1: Stock / Inventario ─────────────────────────
                if config.get("sync_stock", True):
                    logger.info("[Apertura] ▶ Módulo: Stock / Inventario")
                    success = SnapshotService.execute()
                else:
                    logger.info("[Apertura] ⏭ Módulo Stock/Inventario desactivado. Saltando.")

                # ── Módulo 2: Precios ─────────────────────────────────────
                if config.get("sync_precios", True):
                    logger.info("[Apertura] ▶ Módulo: Actualización de Precios")
                    try:
                        from services.price_service import PriceService
                        PriceService.execute()
                    except Exception as e:
                        logger.error(f"[Apertura] Error en PriceService: {e}")
                else:
                    logger.info("[Apertura] ⏭ Módulo Precios desactivado. Saltando.")

                # ── Módulo 3: Cobranzas ───────────────────────────────────
                if config.get("sync_cobranzas", True):
                    logger.info("[Apertura] ▶ Módulo: Cobranzas")
                    try:
                        from services.cobranzas_service import CobranzasService
                        CobranzasService.execute()
                    except Exception as e:
                        logger.error(f"[Apertura] Error en CobranzasService: {e}")
                else:
                    logger.info("[Apertura] ⏭ Módulo Cobranzas desactivado. Saltando.")

                # ── Módulo 4: Compras ─────────────────────────────────────
                if config.get("sync_compras", True):
                    logger.info("[Apertura] ▶ Módulo: Compras")
                    try:
                        from services.compras_service import ComprasService
                        ComprasService.execute()
                    except Exception as e:
                        logger.error(f"[Apertura] Error en ComprasService: {e}")
                else:
                    logger.info("[Apertura] ⏭ Módulo Compras desactivado. Saltando.")

                if success:
                    last_snapshot_date = today

            if success:
                success = self._execute_sync_cycle()
            
            # ── Espera inteligente: heartbeat + comandos cada 60 s ────────────
            current_interval = interval_seconds if success else 60
            elapsed = 0
            POLL_INTERVAL = 60  # cada cuántos segundos verificar comandos

            while elapsed < current_interval and not self.stop_event.is_set():
                # Dormir en bloques de 1 segundo para reaccionar rápido al stop
                sleep_chunk = min(POLL_INTERVAL, current_interval - elapsed)
                for _ in range(sleep_chunk):
                    if self.stop_event.is_set():
                        break
                    time.sleep(1)
                elapsed += sleep_chunk

                if self.stop_event.is_set():
                    break

                # Heartbeat + poll de comandos remotos cada minuto
                try:
                    web_conn = PostgresConnection.get_connection()
                    web_cursor = web_conn.cursor()
                    HeartbeatService.ping(web_cursor, web_conn)
                    CommandPoller.poll(web_cursor, web_conn)
                    web_cursor.close()
                    web_conn.close()
                except Exception as poll_err:
                    logger.warning(f"[Loop] Error en heartbeat/poll: {poll_err}")

        self.is_syncing = False

    def _execute_sync_cycle(self):
        logger = AppLogger.get_logger()
        config = ConfigManager()
        state = StateManager()
        billing_conn = None
        web_conn = None
        
        try:
            last_time = state.get_last_processed_timestamp()
            if not last_time:
                last_time = datetime.now().strftime("%Y-%m-%d 00:00:00.000")
            logger.info(f"Consultando ventas locales registradas después de: {last_time}")
            
            billing_conn = SQLServerConnection.get_connection()
            web_conn = PostgresConnection.get_connection()
            
            billing_cursor = billing_conn.cursor()
            web_cursor = web_conn.cursor()

            # ── Heartbeat: avisar al servidor que este sincronizador está vivo ──
            HeartbeatService.ping(web_cursor, web_conn)
            
            query = get_sql_query("ventas.sql")
            
            if '?' in query:
                query = query.replace('?', f"'{last_time}'")
                billing_cursor.execute(query)
            else:
                billing_cursor.execute(query, (last_time,))
            
            rows = billing_cursor.fetchall()
            
            if not rows:
                logger.info("No se encontraron nuevas ventas.")
                return True
                
            logger.info(f"Se encontraron {len(rows)} ventas nuevas para procesar.")
            sede = config.get("sede")
            new_last_time = last_time
            
            for row in rows:
                if len(row) >= 4:
                    fecha_venta, codigo, cantidad, nombre_local = row[0:4]
                else:
                    fecha_venta, codigo, cantidad = row[0:3]
                    nombre_local = None
                    
                if isinstance(fecha_venta, datetime):
                    fecha_str = fecha_venta.strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
                else:
                    fecha_str = str(fecha_venta)
                    
                try:
                    raw_cant = float(cantidad)
                    int_cantidad = max(1, int(round(raw_cant)))
                except (ValueError, TypeError):
                    int_cantidad = 1
                    
                logger.info(f"Procesando: Código={codigo}, Cant={float(cantidad):.2f} (Web: {int_cantidad}), Fecha={fecha_str}")
                
                prod_row, updated_via_heal = buscar_producto_web(web_cursor, codigo, nombre_local)

                if not prod_row:
                    if nombre_local and str(nombre_local).strip():
                        nombre_insercion = str(nombre_local).strip()
                    else:
                        nombre_insercion = str(codigo).strip()
                    logger.info(f"  [Auto-Registro] Código '{codigo}' no existe. Creando producto '{nombre_insercion}'...")
                    web_cursor.execute(
                        """
                        INSERT INTO inventario_v2.productos (codigo, nombre, categoria, subcategoria, proveedor, precio_unidad, precio_mayor, activo, created_at, updated_at)
                        VALUES (%s, %s, 'Sin categoría', '', '', 0, 0, true, NOW(), NOW())
                        ON CONFLICT (codigo) DO UPDATE
                            SET activo = true,
                                nombre = CASE WHEN inventario_v2.productos.nombre LIKE '[Auto]%%' OR inventario_v2.productos.nombre = inventario_v2.productos.codigo
                                              THEN EXCLUDED.nombre
                                              ELSE inventario_v2.productos.nombre END,
                            updated_at = NOW()
                        RETURNING id, activo;
                        """,
                        (str(codigo).strip(), nombre_insercion)
                    )
                    prod_row = web_cursor.fetchone()

                    if prod_row:
                        logger.info(f"  [Auto-Registro] Producto creado con ID={prod_row[0]}.")
                    else:
                        logger.error(f"  [Error] No se pudo crear el producto '{codigo}'. Saltando.")
                        continue

                prod_id = prod_row[0]
                prod_activo = prod_row[1]

                if not prod_activo:
                    logger.info(f"  [Info] Producto '{codigo}' estaba inactivo. Reactivando...")
                    web_cursor.execute(
                        "UPDATE inventario_v2.productos SET activo = true, updated_at = NOW() WHERE id = %s;",
                        (prod_id,)
                    )
                
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.stock_actual (producto_id, sede, existencia, updated_at)
                    VALUES (%s, %s, 0, NOW())
                    ON CONFLICT (producto_id, sede) DO NOTHING;
                    """,
                    (prod_id, sede)
                )
                
                web_cursor.execute(
                    """
                    UPDATE inventario_v2.stock_actual
                    SET existencia = GREATEST(0, existencia - %s), updated_at = NOW()
                    WHERE producto_id = %s AND sede = %s;
                    """,
                    (int_cantidad, prod_id, sede)
                )
                
                metadata = json.dumps({
                    "motivo": "Sincronizacion automatica de venta",
                    "fecha_venta_local": fecha_str
                })
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.movimientos (producto_id, origen, destino, tipo, cantidad, usuario, metadata, created_at)
                    VALUES (%s, %s, NULL, 'AJUSTE', %s, 'sistema_sync', %s, NOW());
                    """,
                    (prod_id, sede, int_cantidad, metadata)
                )
                
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.ventas_historicas (producto_id, sede, ultima_venta, updated_at, ventas_60d, venta_promedio)
                    VALUES (%s, %s, %s, NOW(), 0, 0)
                    ON CONFLICT (producto_id, sede) DO UPDATE
                        SET ultima_venta = EXCLUDED.ultima_venta, updated_at = NOW();
                    """,
                    (prod_id, sede, fecha_str)
                )

                anio_mes = fecha_str[:7]
                web_cursor.execute(
                    """
                    INSERT INTO inventario_v2.historial_ventas_mensuales (producto_id, sede, anio_mes, cantidad, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW())
                    ON CONFLICT (sede, producto_id, anio_mes) DO UPDATE
                        SET cantidad = inventario_v2.historial_ventas_mensuales.cantidad + EXCLUDED.cantidad,
                            updated_at = NOW();
                    """,
                    (prod_id, sede, anio_mes, int_cantidad)
                )
                
                new_last_time = max(new_last_time, fecha_str)
                
            meta_json = json.dumps({"timestamp": new_last_time})
            web_cursor.execute(
                """
                INSERT INTO inventario_v2.sync_logs (sede, tipo, registros_procesados, metadata, created_at)
                VALUES (%s, 'VENTA', %s, %s, NOW())
                """,
                (sede, len(rows), meta_json)
            )
            
            web_conn.commit()
            
            state.set_last_processed_timestamp(new_last_time)
            
            logger.info(f"Sincronización completada. Último registro procesado: {new_last_time}")

            # ── Heartbeat + polling de comandos remotos ────────────────
            HeartbeatService.ping(web_cursor, web_conn)
            CommandPoller.poll(web_cursor, web_conn)
            # ──────────────────────────────────────────────────────────

            return True
            
        except Exception as e:
            logger.error(f"Error en ciclo de sincronización: {str(e)}")
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
