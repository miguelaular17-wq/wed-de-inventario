import json
from datetime import datetime
from database.sqlserver import SQLServerConnection
from database.postgres import PostgresConnection
from config.config_manager import ConfigManager
from config.state_manager import StateManager
from utils.logger import AppLogger
from utils.helpers import get_sql_query
from utils.dates import sanitize_business_date
import psycopg2.extras

class SnapshotService:
    @staticmethod
    def execute(ui_callback=None):
        logger = AppLogger.get_logger()
        config = ConfigManager()
        state = StateManager()
        billing_conn = None
        web_conn = None
        
        try:
            sede = config.get("sede", "JRZ")
            logger.info("[Snapshot] 1. Conectando a Supabase para ver qué productos ya existen...")
            
            web_conn = PostgresConnection.get_connection()
            wc = web_conn.cursor()
            
            wc.execute("SELECT codigo, nombre, id FROM inventario_v2.productos;")
            supabase_rows = wc.fetchall()
            
            prod_map = {}
            for r in supabase_rows:
                db_id = r[2]
                if r[0]:
                    full_code = str(r[0]).strip()
                    prod_map[full_code] = db_id
                    for part in full_code.split(' / '):
                        clean_part = part.strip()
                        if clean_part:
                            prod_map[clean_part] = db_id
            
            name_map = {}
            for r in supabase_rows:
                if r[1]:
                    name_map[str(r[1]).strip().lower()] = (r[2], str(r[0]).strip() if r[0] else "")
            
            if not prod_map:
                logger.info("[Snapshot] No hay ningún producto registrado en la web todavía. Omitiendo actualización masiva.")
                return True

            logger.info(f"[Snapshot] La web tiene {len(prod_map)} productos registrados. Consultando SQL Server...")
            
            billing_conn = SQLServerConnection.get_connection()
            snapshot_query = get_sql_query("snapshot.sql")
            
            cursor = billing_conn.cursor()
            cursor.execute(snapshot_query)
            rows = cursor.fetchall()
            logger.info(f"[Snapshot] {len(rows)} totales obtenidos de SQL Server.")
            
            if not rows:
                return True
                
            stock_tuples = []
            ventas_tuples = []
            skipped = 0
            
            pid_stock_map = {}
            pid_ventas_map = {}
            pid_updates_map = {}
            
            for row in rows:
                codigo = str(row[0]).strip()
                nombre_local  = str(row[6]).strip() if len(row) > 6 and row[6] else None
                precio_unidad = float(row[7]) if len(row) > 7 and row[7] else 0.0
                precio_mayor  = float(row[8]) if len(row) > 8 and row[8] else 0.0
                categoria_str = str(row[9]).strip() if len(row) > 9 and row[9] else None
                subcategoria_str = str(row[10]).strip() if len(row) > 10 and row[10] else ''
                costo_actual = float(row[11]) if len(row) > 11 and row[11] else 0.0
                
                if not categoria_str:
                    categoria_str = subcategoria_str
                    subcategoria_str = ''
                if not categoria_str:
                    categoria_str = 'Sin categoría'
                
                existencia    = max(0, int(row[1]) if row[1] else 0)
                ventas_15d    = float(row[2]) if row[2] else 0.0
                ventas_60d    = float(row[3]) if row[3] else 0.0
                ultima_venta  = sanitize_business_date(row[4]) if len(row) > 4 else None
                ultima_compra = sanitize_business_date(row[5]) if len(row) > 5 else None

                if codigo in prod_map:
                    pid = prod_map[codigo]
                elif nombre_local and nombre_local.lower() in name_map:
                    pid, current_codigo = name_map[nombre_local.lower()]
                    parts = [p.strip() for p in current_codigo.split(' / ')]
                    if codigo not in parts and codigo not in current_codigo:
                        new_codigo = f"{current_codigo} / {codigo}" if current_codigo else codigo
                        logger.info(f"[Snapshot Auto-Heal] Producto '{nombre_local}' encontrado por nombre. Agregando código '{codigo}' a la web.")
                        try:
                            wc.execute("UPDATE inventario_v2.productos SET codigo = %s, updated_at = NOW() WHERE id = %s;", (new_codigo, pid))
                            name_map[nombre_local.lower()] = (pid, new_codigo)
                            web_conn.commit()
                        except Exception as e:
                            logger.error(f"[Snapshot Auto-Heal] Error actualizando código: {e}")
                            web_conn.rollback()
                    prod_map[codigo] = pid
                else:
                    tiene_actividad = existencia > 0 or ventas_15d > 0 or ventas_60d > 0
                    if tiene_actividad:
                        try:
                            wc.execute("SAVEPOINT auto_reg_sp;")
                            nombre_insercion = str(nombre_local).strip() if nombre_local and str(nombre_local).strip() else codigo
                            logger.info(f"[Snapshot Auto-Registro] Producto '{codigo}' no existe en la web (stock={existencia}, ventas60d={ventas_60d}). Subiéndolo como '{nombre_insercion}'...")
                            wc.execute(
                                """
                                INSERT INTO inventario_v2.productos (codigo, nombre, categoria, subcategoria, proveedor, precio_unidad, precio_mayor, activo, created_at, updated_at)
                                VALUES (%s, %s, %s, %s, '', %s, %s, true, NOW(), NOW())
                                ON CONFLICT (codigo) DO UPDATE
                                    SET activo = true,
                                        precio_unidad = GREATEST(inventario_v2.productos.precio_unidad, EXCLUDED.precio_unidad),
                                        precio_mayor  = GREATEST(inventario_v2.productos.precio_mayor,  EXCLUDED.precio_mayor),
                                        categoria     = CASE WHEN inventario_v2.productos.categoria = 'Sin categoría' OR inventario_v2.productos.categoria IS NULL OR inventario_v2.productos.categoria = '' THEN EXCLUDED.categoria ELSE inventario_v2.productos.categoria END,
                                        subcategoria  = CASE WHEN inventario_v2.productos.subcategoria IS NULL OR inventario_v2.productos.subcategoria = '' THEN EXCLUDED.subcategoria ELSE inventario_v2.productos.subcategoria END,
                                        updated_at = NOW()
                                RETURNING id;
                                """,
                                (codigo, nombre_insercion, categoria_str, subcategoria_str, precio_unidad, precio_mayor)
                            )
                            new_pid_row = wc.fetchone()
                            if new_pid_row:
                                pid = new_pid_row[0]
                                prod_map[codigo] = pid
                                if nombre_local:
                                    name_map[nombre_local.lower()] = (pid, codigo)
                                wc.execute("RELEASE SAVEPOINT auto_reg_sp;")
                                web_conn.commit()
                            else:
                                wc.execute("ROLLBACK TO SAVEPOINT auto_reg_sp;")
                                skipped += 1
                                continue
                        except Exception as e:
                            logger.error(f"[Snapshot Auto-Registro] Error subiendo producto '{codigo}': {e}")
                            wc.execute("ROLLBACK TO SAVEPOINT auto_reg_sp;")
                            skipped += 1
                            continue
                    else:
                        skipped += 1
                        continue

                if pid not in pid_updates_map or precio_unidad > pid_updates_map[pid][0]:
                    pid_updates_map[pid] = (precio_unidad, precio_mayor, categoria_str, subcategoria_str, costo_actual)

                pid_stock_map[pid] = pid_stock_map.get(pid, 0) + existencia
                
                if pid not in pid_ventas_map:
                    pid_ventas_map[pid] = {
                        'ventas_60d': ventas_60d,
                        'ventas_15d': ventas_15d,
                        'ultima_venta': None,
                        'ultima_compra': None
                    }
                else:
                    pid_ventas_map[pid]['ventas_60d'] = max(pid_ventas_map[pid]['ventas_60d'], ventas_60d)
                    pid_ventas_map[pid]['ventas_15d'] = max(pid_ventas_map[pid]['ventas_15d'], ventas_15d)
                
                v_data = pid_ventas_map[pid]
                if ultima_venta:
                    if not v_data['ultima_venta'] or ultima_venta > v_data['ultima_venta']:
                        v_data['ultima_venta'] = ultima_venta
                if ultima_compra:
                    if not v_data['ultima_compra'] or ultima_compra > v_data['ultima_compra']:
                        v_data['ultima_compra'] = ultima_compra

            for pid, stock in pid_stock_map.items():
                stock_tuples.append((pid, sede, stock))
            
            for pid, v_data in pid_ventas_map.items():
                venta_promedio_15d = round(v_data['ventas_15d'] / 15.0, 4) if v_data['ventas_15d'] else 0.0
                ventas_tuples.append((
                    pid, sede, v_data['ventas_60d'], venta_promedio_15d, 
                    v_data['ultima_venta'], v_data['ultima_compra']
                ))

            def batch_execute_and_commit(query, data_list, chunk_size=1000):
                for i in range(0, len(data_list), chunk_size):
                    chunk = data_list[i:i + chunk_size]
                    psycopg2.extras.execute_batch(wc, query, chunk, page_size=chunk_size)
                    web_conn.commit()

            if not stock_tuples:
                logger.info(f"[Snapshot] No hay datos conocidos para actualizar (omitidos {skipped} desconocidos).")
                return True

            logger.info(f"[Snapshot] Preparados {len(stock_tuples)} productos conocidos. Enviando...")
            
            updates_tuples = []
            for pid_r, (pu, pm, cat, subcat, costo) in pid_updates_map.items():
                updates_tuples.append((pu, pm, cat, subcat, costo, costo, pid_r, pu, pm, cat, subcat, costo))
                
            if updates_tuples:
                batch_update_query = """
                    UPDATE inventario_v2.productos
                    SET precio_unidad = CASE WHEN precio_unidad IS NULL OR precio_unidad <= 0 THEN %s ELSE precio_unidad END,
                        precio_mayor  = CASE WHEN precio_mayor  IS NULL OR precio_mayor  <= 0 THEN %s ELSE precio_mayor  END,
                        categoria     = CASE WHEN categoria = 'Sin categoría' OR categoria IS NULL OR categoria = '' THEN %s ELSE categoria END,
                        subcategoria  = CASE WHEN subcategoria IS NULL OR subcategoria = '' THEN %s ELSE subcategoria END,
                        costo_actual  = CASE WHEN %s > 0 THEN %s ELSE costo_actual END,
                        updated_at = NOW()
                    WHERE id = %s AND (
                        (%s > 0 AND (precio_unidad IS NULL OR precio_unidad <= 0)) OR
                        (%s > 0 AND (precio_mayor  IS NULL OR precio_mayor  <= 0)) OR
                        (%s != 'Sin categoría' AND (categoria = 'Sin categoría' OR categoria IS NULL OR categoria = '')) OR
                        (%s != '' AND (subcategoria IS NULL OR subcategoria = '')) OR
                        (%s > 0)
                    );
                """
                batch_execute_and_commit(batch_update_query, updates_tuples)
                
            logger.info(f"[Snapshot] Atributos evaluados y actualizados para {len(updates_tuples)} productos en la web.")

            upsert_stock_query = """
                INSERT INTO inventario_v2.stock_actual (producto_id, sede, existencia, updated_at)
                VALUES (%s, %s, %s, NOW())
                ON CONFLICT (producto_id, sede) DO UPDATE
                    SET existencia = EXCLUDED.existencia, updated_at = NOW();
            """
            batch_execute_and_commit(upsert_stock_query, stock_tuples)

            upsert_ventas_query = """
                INSERT INTO inventario_v2.ventas_historicas
                    (producto_id, sede, ventas_60d, venta_promedio, ultima_venta, ultima_compra, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, NOW())
                ON CONFLICT (producto_id, sede) DO UPDATE
                    SET ventas_60d      = EXCLUDED.ventas_60d,
                        venta_promedio  = EXCLUDED.venta_promedio,
                        ultima_venta    = CASE
                            WHEN EXCLUDED.ultima_venta IS NOT NULL THEN EXCLUDED.ultima_venta
                            WHEN inventario_v2.ventas_historicas.ultima_venta >= DATE '1990-01-01'
                             AND inventario_v2.ventas_historicas.ultima_venta <= CURRENT_DATE + 1
                            THEN inventario_v2.ventas_historicas.ultima_venta
                            ELSE NULL
                        END,
                        ultima_compra   = CASE
                            WHEN EXCLUDED.ultima_compra IS NOT NULL THEN EXCLUDED.ultima_compra
                            WHEN inventario_v2.ventas_historicas.ultima_compra >= DATE '1990-01-01'
                             AND inventario_v2.ventas_historicas.ultima_compra <= CURRENT_DATE + 1
                            THEN inventario_v2.ventas_historicas.ultima_compra
                            ELSE NULL
                        END,
                        updated_at      = NOW();
            """
            batch_execute_and_commit(upsert_ventas_query, ventas_tuples)
            
            updated = len(stock_tuples)
            meta_json = json.dumps({"omitidos": skipped})
            wc.execute(
                """
                INSERT INTO inventario_v2.sync_logs (sede, tipo, registros_procesados, metadata, created_at)
                VALUES (%s, 'APERTURA', %s, %s, NOW())
                """,
                (sede, updated, meta_json)
            )
            web_conn.commit()
            
            logger.info(f"[Snapshot] ✓ Reporte de apertura cargado: {updated} productos actualizados, {skipped} omitidos.")
            
            new_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S.000")
            state.set_last_processed_timestamp(new_ts)
            logger.info(f"[Snapshot] Punto de inicio de movimientos: {new_ts}")
            
            return True

        except Exception as e:
            logger.error(f"[Snapshot] Error al subir reporte de apertura: {str(e)}")
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
