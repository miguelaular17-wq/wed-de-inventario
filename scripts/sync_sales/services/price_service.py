from database.sqlserver import SQLServerConnection
from database.postgres import PostgresConnection
from utils.logger import AppLogger
import psycopg2.extras


class PriceService:
    """Sincroniza precio_unidad / precio_mayor desde Suite (SQL Server) hacia la web.

    Reglas:
    - En códigos compuestos ("A / B / C") se usa el primer tramo con precio > 0 en Suite.
      Así un alias o SKU viejo en 0 no pisa el precio real de la barra.
    - Si el mismo código aparece varias veces en Suite, se prefiere:
      1) activo, 2) con stock > 0, 3) con precio > 0, 4) id más bajo (artículo original).
    - Nunca se sobrescribe un precio > 0 de la web con un 0 de Suite.
      (El snapshot ya rellena huecos; aquí solo actualizamos con precios reales.)
    """

    @staticmethod
    def _precio_util(p1, p2) -> bool:
        return float(p1 or 0) > 0.009 or float(p2 or 0) > 0.009

    @staticmethod
    def _rank(activo, stock, p1, p2, articulo_id=0):
        """Mayor rank = mejor candidato cuando hay códigos duplicados."""
        activo_i = 1 if int(activo or 0) else 0
        stock_i = 1 if float(stock or 0) > 0.0001 else 0
        precio_i = 1 if PriceService._precio_util(p1, p2) else 0
        # Id más bajo gana el empate (suele ser el artículo original).
        return (activo_i, stock_i, precio_i, -int(articulo_id or 0))

    @staticmethod
    def _mejor_que(nuevo, actual) -> bool:
        """nuevo/actual: (rank_tuple, prices_tuple)."""
        if actual is None:
            return True
        return nuevo[0] > actual[0]

    @staticmethod
    def _elegir_precio(candidatos):
        """candidatos: list[(p1, p2)]. Prefiere el primero con precio > 0."""
        if not candidatos:
            return None
        for precios in candidatos:
            if PriceService._precio_util(*precios):
                return precios
        return candidatos[0]

    @staticmethod
    def _precios_distintos(sp1, sp2, wp1, wp2) -> bool:
        return abs(float(sp1) - float(wp1)) > 0.009 or abs(float(sp2) - float(wp2)) > 0.009

    @staticmethod
    def execute(ui_callback=None):
        logger = AppLogger.get_logger()
        billing_conn = None
        web_conn = None

        try:
            logger.info("Iniciando actualizacion de precios...")

            billing_conn = SQLServerConnection.get_connection()
            web_conn = PostgresConnection.get_connection()

            web_cursor = web_conn.cursor()
            billing_cursor = billing_conn.cursor()

            # ── 1. Leer productos de Supabase (id, codigo, nombre, precios) ──────
            logger.info("Consultando productos en Supabase...")
            web_cursor.execute(
                "SELECT id, COALESCE(codigo,''), nombre, "
                "COALESCE(precio_unidad, 0), COALESCE(precio_mayor, 0) "
                "FROM inventario_v2.productos WHERE nombre IS NOT NULL"
            )
            web_prods = web_cursor.fetchall()   # (id, codigo, nombre, p1, p2)

            # ── 2. Leer precios de SQL Server (+ activo/stock para desempate) ──
            logger.info("Consultando precios en SQL Server...")
            billing_cursor.execute(
                """
                SELECT
                    a.codigo,
                    a.descripcion,
                    ISNULL(a.precio1_moneda2_uni1, 0),
                    ISNULL(a.precio2_moneda2_uni1, ISNULL(a.precio1_moneda2_uni1, 0)),
                    ISNULL(a.activo, 0),
                    ISNULL((
                        SELECT SUM(ISNULL(ex.actual, 0))
                        FROM existencias ex WITH (NOLOCK)
                        WHERE ex.id_articulo = a.id
                    ), 0) AS stock_total,
                    a.id
                FROM articulos a WITH (NOLOCK)
                WHERE a.descripcion IS NOT NULL
                """
            )
            sql_prods = billing_cursor.fetchall()

            # ── 3. Diccionarios: por código / nombre, eligiendo el mejor candidato ─
            logger.info("Procesando comparaciones...")

            # codigo -> (rank, (p1, p2))
            sql_by_code = {}
            # nombre lower -> (rank, (p1, p2))
            sql_by_name = {}
            dup_codigos = 0

            for scode, sdesc, p1, p2, activo, stock, aid in sql_prods:
                prices = (round(float(p1 or 0), 2), round(float(p2 or 0), 2))
                rank = PriceService._rank(activo, stock, prices[0], prices[1], aid)
                cand = (rank, prices)

                if scode:
                    key = str(scode).strip()
                    if key in sql_by_code:
                        dup_codigos += 1
                    if PriceService._mejor_que(cand, sql_by_code.get(key)):
                        sql_by_code[key] = cand

                if sdesc:
                    nkey = str(sdesc).strip().lower()
                    if PriceService._mejor_que(cand, sql_by_name.get(nkey)):
                        sql_by_name[nkey] = cand

            if dup_codigos:
                logger.info(
                    f"Códigos duplicados en Suite: {dup_codigos} colisiones; "
                    "se prefirió activo + stock > 0 + precio > 0."
                )

            # ── 4. Buscar coincidencia para cada producto de Supabase ────────────
            updates = []
            matched_by_code = 0
            matched_by_name = 0
            no_match = 0
            skipped_zero = 0

            for wid, wcodigo, wname, wp1, wp2 in web_prods:
                candidatos = []

                # 4a. Por código(s) — maneja SKUs múltiples "A / B / C"
                if wcodigo:
                    partes = [p.strip() for p in str(wcodigo).split(' / ') if p.strip()]
                    for parte in partes:
                        if parte in sql_by_code:
                            candidatos.append(sql_by_code[parte][1])

                elegido = PriceService._elegir_precio(candidatos)
                if elegido is not None:
                    matched_by_code += 1
                else:
                    # 4b. Fallback: nombre exacto
                    clean_name = str(wname).strip().lower()
                    if clean_name in sql_by_name:
                        elegido = sql_by_name[clean_name][1]
                        matched_by_name += 1

                if elegido is None:
                    no_match += 1
                    continue

                sp1, sp2 = elegido

                # No pisar un precio bueno de la web con un 0 de Suite
                if not PriceService._precio_util(sp1, sp2) and PriceService._precio_util(wp1, wp2):
                    skipped_zero += 1
                    continue

                if PriceService._precios_distintos(sp1, sp2, wp1, wp2):
                    updates.append((sp1, sp2, wid))

            logger.info(
                f"Coincidencias → por código: {matched_by_code}, "
                f"por nombre: {matched_by_name}, sin coincidencia: {no_match}, "
                f"omitidos (Suite en 0 y web con precio): {skipped_zero}"
            )

            # ── 5. Aplicar actualizaciones ───────────────────────────────────────
            if not updates:
                logger.info("Todos los precios estan actualizados en la web.")
                return True

            logger.info(f"Se actualizaran los precios de {len(updates)} productos. Subiendo a Supabase...")
            psycopg2.extras.execute_batch(
                web_cursor,
                "UPDATE inventario_v2.productos "
                "SET precio_unidad = %s, precio_mayor = %s, updated_at = NOW() "
                "WHERE id = %s",
                updates
            )
            web_conn.commit()
            logger.info(f"Actualizacion completada! {len(updates)} precios actualizados.")
            return True

        except Exception as e:
            import traceback
            logger.error(f"Error en actualizacion de precios: {traceback.format_exc()}")
            if web_conn:
                try:
                    web_conn.rollback()
                except Exception:
                    pass
            return False
        finally:
            if ui_callback:
                ui_callback()
            if billing_conn:
                try:
                    billing_conn.close()
                except Exception:
                    pass
            if web_conn:
                try:
                    web_conn.close()
                except Exception:
                    pass
