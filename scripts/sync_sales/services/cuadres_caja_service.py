"""Sube lo que cobró cada caja en el día por forma de pago (cajas_movimientos de Suite). El cuadre se concilia en la web.

Un cuadre es un día de una caja con una cajera. No espera al cierre de Suite, que en las sedes se registra
uno a tres días después: sale de los cobros del día. El día en curso se sube a partir del día siguiente.
"""
from datetime import datetime, timedelta

from psycopg2.extras import execute_values

from config.config_manager import ConfigManager
from database.postgres import PostgresConnection
from database.sqlserver import SQLServerConnection
from utils.logger import AppLogger

DIAS_POR_DEFECTO = 40

MOVIMIENTOS_QUERY = """
SELECT
    CAST(m.fecha_emision AS DATE)                  AS dia,
    LTRIM(RTRIM(m.codigo_caja))                    AS codigo_caja,
    LTRIM(RTRIM(MAX(c.descripcion)))               AS descripcion_caja,
    LTRIM(RTRIM(m.usuario))                        AS usuario,
    MAX(m.id_cierre_caja)                          AS id_cierre,
    MAX(m.fecha_emision)                           AS ultimo_cobro,
    MAX(m.tasa_cambio)                             AS tasa,
    LTRIM(RTRIM(m.codigo_metodo_pago))             AS codigo_metodo,
    LTRIM(RTRIM(MAX(mp.descripcion)))              AS forma_pago,
    LTRIM(RTRIM(m.codigo_cuenta))                  AS codigo_cuenta,
    LTRIM(RTRIM(MAX(cb.descripcion)))              AS cuenta,
    MAX(COALESCE(mp.tipo_moneda, m.tipo_moneda))   AS tipo_moneda,
    SUM(CASE WHEN m.tipo_movimiento = 'EGRESO' THEN -m.monto ELSE m.monto END)                 AS ingresos,
    SUM(CASE WHEN m.tipo_movimiento = 'EGRESO' THEN -m.monto_moneda2 ELSE m.monto_moneda2 END) AS ingresos_usd
FROM cajas_movimientos m WITH (NOLOCK)
LEFT JOIN cajas c WITH (NOLOCK) ON c.codigo = m.codigo_caja
LEFT JOIN metodo_pago mp WITH (NOLOCK) ON mp.codigo = m.codigo_metodo_pago
LEFT JOIN cuentas_bancarias cb WITH (NOLOCK) ON cb.codigo = m.codigo_cuenta
WHERE m.fecha_emision >= ? AND m.fecha_emision < ?
  AND m.estado <> 'ANULADO'
GROUP BY CAST(m.fecha_emision AS DATE), LTRIM(RTRIM(m.codigo_caja)), LTRIM(RTRIM(m.usuario)),
         LTRIM(RTRIM(m.codigo_metodo_pago)), LTRIM(RTRIM(m.codigo_cuenta))
ORDER BY 1, 2, 4, 8, 10
"""

UPSERT_CUADRES = """
    INSERT INTO inventario_v2.cuadres_caja (
        sede, fecha_cierre, codigo_caja, usuario, id_cierre, registrado_at, descripcion_caja,
        usuario_nombre, factor_cambio, created_at, updated_at
    ) VALUES %s
    ON CONFLICT (sede, fecha_cierre, codigo_caja, usuario) DO UPDATE SET
        id_cierre        = EXCLUDED.id_cierre,
        registrado_at    = EXCLUDED.registrado_at,
        descripcion_caja = EXCLUDED.descripcion_caja,
        usuario_nombre   = COALESCE(EXCLUDED.usuario_nombre, inventario_v2.cuadres_caja.usuario_nombre),
        factor_cambio    = EXCLUDED.factor_cambio,
        updated_at       = NOW()
    RETURNING id, fecha_cierre, codigo_caja, usuario
"""

FACTURAS_QUERY = """
SELECT x.dia, x.caja, x.usuario, COUNT(*) AS facturas, SUM(v.total_neto_moneda2) AS total_usd
FROM (
    SELECT DISTINCT
        CAST(m.fecha_emision AS DATE) AS dia,
        LTRIM(RTRIM(m.codigo_caja)) AS caja,
        LTRIM(RTRIM(m.usuario)) AS usuario,
        LTRIM(RTRIM(m.numero_documento_afectado)) AS doc
    FROM cajas_movimientos m WITH (NOLOCK)
    WHERE m.origen = 'FACVEN' AND m.estado <> 'ANULADO'
      AND m.fecha_emision >= ? AND m.fecha_emision < ?
) x
JOIN documentos_venta v WITH (NOLOCK)
  ON v.tipo_documento = 'FAC' AND v.numero_documento = x.doc
 AND LOWER(LTRIM(RTRIM(v.estado))) = 'registrado'
GROUP BY x.dia, x.caja, x.usuario
"""

CIERRES_QUERY = """
SELECT id, LTRIM(RTRIM(observaciones)), total_diferencia, total_diferencia_moneda2, detalle_totales_json
FROM cierres_cajas WITH (NOLOCK)
WHERE id IN ({ids})
"""


def totales_cierre(detalle_json):
    """(documentos, total $) de las ventas del cierre según Suite, o (None, None) si no se puede leer."""
    import json

    try:
        datos = json.loads(detalle_json or "")
        bloque = datos.get("TOTALES") or datos.get("VENTAS") or {}
        docs = bloque.get("numero_documentos")
        total = bloque.get("total_neto_moneda2")
        return (int(docs) if docs is not None else None, round(float(total), 2) if total is not None else None)
    except Exception:
        return (None, None)

ENSURE_COLUMNAS = """
ALTER TABLE inventario_v2.cuadres_caja
    ADD COLUMN IF NOT EXISTS observacion_suite TEXT,
    ADD COLUMN IF NOT EXISTS diferencia_suite_bs NUMERIC(18,2),
    ADD COLUMN IF NOT EXISTS diferencia_suite_usd NUMERIC(18,2),
    ADD COLUMN IF NOT EXISTS facturas_suite INTEGER,
    ADD COLUMN IF NOT EXISTS total_venta_usd NUMERIC(18,2);
"""

INSERT_PAGOS = """
    INSERT INTO inventario_v2.cuadre_caja_pagos (
        cuadre_caja_id, codigo_metodo, forma_pago, codigo_cuenta, cuenta,
        en_dolares, ingresos, ingresos_usd, lote
    ) VALUES %s
"""


def _texto(valor):
    texto = str(valor).strip() if valor is not None else ""
    return texto or None


def _num(valor):
    return float(valor) if valor is not None else 0.0


def _fecha(valor):
    """El driver {SQL Server} devuelve DATE como texto; psycopg2 como date."""
    return valor.isoformat()[:10] if hasattr(valor, "isoformat") else str(valor)[:10]


def _clave(fecha, caja, usuario):
    return (_fecha(fecha), caja or "", usuario or "")


def _solo_digitos(valor):
    return "".join(ch for ch in str(valor or "") if ch.isdigit())


def _nombre_de(nombres, usuario):
    """Resuelve el nombre de Suite por código o por cédula (solo dígitos)."""
    if not usuario:
        return None
    clave = str(usuario).strip().upper()
    nombre = nombres.get(clave) or nombres.get(_solo_digitos(clave))
    if not nombre:
        return None
    # Suite a veces pone la misma cédula como "descripción".
    if _solo_digitos(nombre) == _solo_digitos(clave) and len(_solo_digitos(nombre)) >= 6:
        return None
    return nombre


def agrupar_cuadres(rows, sede, nombres):
    """Filas del query (una por día, caja, cajera y forma de pago) → {(día, caja, cajera): {"cuadre": dict, "pagos": [tuplas]}}."""
    cuadres = {}
    for r in rows:
        caja, usuario = _texto(r[1]) or "", _texto(r[3]) or ""
        clave = _clave(r[0], caja, usuario)
        cuadre = cuadres.setdefault(clave, {
            "cuadre": {
                "sede": sede, "fecha": clave[0], "caja": caja, "usuario": usuario,
                "id_cierre": None, "registrado": None, "descripcion": _texto(r[2]),
                "nombre": _nombre_de(nombres, usuario), "tasa": 0.0,
            },
            "pagos": [],
        })["cuadre"]
        if r[4] is not None:
            cuadre["id_cierre"] = max(int(r[4]), cuadre["id_cierre"] or 0)
        if r[5] is not None and (cuadre["registrado"] is None or r[5] > cuadre["registrado"]):
            cuadre["registrado"] = r[5]
        cuadre["tasa"] = max(cuadre["tasa"], _num(r[6]))

        en_dolares = str(r[11] or "").strip().lower() == "en-us"
        ingresos_usd = _num(r[13])
        cuadres[clave]["pagos"].append((
            _texto(r[7]), _texto(r[8]), _texto(r[9]), _texto(r[10]), en_dolares,
            ingresos_usd if en_dolares else _num(r[12]),
            ingresos_usd,
            None,
        ))
    return cuadres


class CuadresCajaService:
    last_result = None

    @staticmethod
    def _nombres_usuarios(billing_conn, logger):
        """Cédula → nombre del usuario de Suite (suiteMasterDB.usuarios)."""
        def leer(conn, sql):
            cur = conn.cursor()
            cur.execute(sql)
            mapa = {}
            for codigo, descripcion in cur.fetchall():
                if not codigo or not descripcion:
                    continue
                clave = str(codigo).strip().upper()
                nombre = str(descripcion).strip()
                if not clave or not nombre:
                    continue
                # Ignorar "nombres" que son solo la cédula.
                if _solo_digitos(nombre) == _solo_digitos(clave) and len(_solo_digitos(nombre)) >= 6:
                    continue
                mapa[clave] = nombre
                digitos = _solo_digitos(clave)
                if digitos:
                    mapa[digitos] = nombre
            return mapa

        try:
            master = SQLServerConnection._master_connection()
            try:
                mapa = leer(master, "SELECT codigo, descripcion FROM usuarios")
                if mapa:
                    logger.info(f"[Cuadres] {len(mapa)} nombres de usuarios leídos desde suiteMasterDB.")
                    return mapa
            finally:
                master.close()
        except Exception as e:
            logger.warning(f"[Cuadres] suiteMasterDB.usuarios no disponible: {e}")
        try:
            mapa = leer(billing_conn, "SELECT codigo, descripcion FROM suiteMasterDB.dbo.usuarios")
            if mapa:
                logger.info(f"[Cuadres] {len(mapa)} nombres de usuarios leídos vía linked suiteMasterDB.")
                return mapa
        except Exception:
            pass
        try:
            # Algunas sedes tienen la tabla local.
            mapa = leer(billing_conn, "SELECT codigo, descripcion FROM usuarios")
            if mapa:
                logger.info(f"[Cuadres] {len(mapa)} nombres de usuarios leídos desde usuarios local.")
                return mapa
        except Exception:
            pass
        logger.warning("[Cuadres] No se pudieron leer los nombres de usuarios de Suite; se sube solo la cédula.")
        return {}

    @staticmethod
    def _rellenar_nombres_faltantes(wc, sede, desde, hasta, nombres):
        """Completa usuario_nombre en cuadres donde Suite solo mandó la cédula."""
        if not nombres:
            return 0
        actualizados = 0
        vistos = set()
        for clave, nombre in nombres.items():
            if not nombre or clave in vistos:
                continue
            digitos = _solo_digitos(clave)
            if not digitos or digitos in vistos:
                continue
            vistos.add(digitos)
            wc.execute(
                """
                UPDATE inventario_v2.cuadres_caja
                SET usuario_nombre = %s,
                    updated_at = NOW()
                WHERE sede = %s
                  AND fecha_cierre >= %s
                  AND fecha_cierre < %s
                  AND regexp_replace(COALESCE(usuario, ''), '\\D', '', 'g') = %s
                  AND (
                        usuario_nombre IS NULL
                     OR TRIM(usuario_nombre) = ''
                     OR regexp_replace(COALESCE(usuario_nombre, ''), '\\D', '', 'g')
                        = regexp_replace(COALESCE(usuario, ''), '\\D', '', 'g')
                  )
                """,
                (nombre, sede, desde, hasta, digitos),
            )
            actualizados += wc.rowcount or 0
        return actualizados

    @staticmethod
    def _subir_cierres(billing_conn, web_conn, sede, desde, hasta, cuadres, web_id):
        """Facturas y total vendido de cada día de caja, y la observación y diferencia del cierre de Suite."""
        wc = web_conn.cursor()
        wc.execute(ENSURE_COLUMNAS)

        cur = billing_conn.cursor()
        cur.execute(FACTURAS_QUERY, (desde, hasta))
        por_dia = [
            (int(r[3] or 0), round(_num(r[4]), 2), web_id[_clave(r[0], _texto(r[1]) or "", _texto(r[2]) or "")])
            for r in cur.fetchall()
            if _clave(r[0], _texto(r[1]) or "", _texto(r[2]) or "") in web_id
        ]
        if por_dia:
            execute_values(
                wc,
                "UPDATE inventario_v2.cuadres_caja c SET facturas_suite = v.f, total_venta_usd = v.t "
                "FROM (VALUES %s) AS v(f, t, id) WHERE c.id = v.id",
                por_dia,
                template="(%s::int, %s::numeric, %s::bigint)",
            )

        ids_cierre = sorted({c["cuadre"]["id_cierre"] for c in cuadres.values() if c["cuadre"]["id_cierre"]})
        cierres = []
        for i in range(0, len(ids_cierre), 500):
            lote = ids_cierre[i:i + 500]
            cur.execute(CIERRES_QUERY.format(ids=",".join("?" * len(lote))), lote)
            for r in cur.fetchall():
                docs, total = totales_cierre(r[4])
                cierres.append((_texto(r[1]), round(_num(r[2]), 2), round(_num(r[3]), 2), docs, total, int(r[0]), sede))
        if cierres:
            # Con cierre registrado manda lo que dice Suite (documentos y total del cierre).
            execute_values(
                wc,
                "UPDATE inventario_v2.cuadres_caja c SET observacion_suite = v.o, diferencia_suite_bs = v.b, "
                "diferencia_suite_usd = v.u, facturas_suite = COALESCE(v.f, c.facturas_suite), "
                "total_venta_usd = COALESCE(v.t, c.total_venta_usd) "
                "FROM (VALUES %s) AS v(o, b, u, f, t, id, sede) "
                "WHERE c.sede = v.sede AND c.id_cierre = v.id",
                cierres,
                template="(%s::text, %s::numeric, %s::numeric, %s::int, %s::numeric, %s::bigint, %s::text)",
            )
        web_conn.commit()

    @staticmethod
    def execute(dias=None, ui_callback=None):
        logger = AppLogger.get_logger()
        config = ConfigManager()
        billing_conn = None
        web_conn = None
        CuadresCajaService.last_result = None

        try:
            sede = config.get("sede", "JRZ")
            dias = int(dias or config.get("dias_cuadres_caja", DIAS_POR_DEFECTO) or DIAS_POR_DEFECTO)
            hoy = datetime.now().date()
            desde = (hoy - timedelta(days=dias)).isoformat()
            hasta = hoy.isoformat()

            logger.info(f"[Cuadres] 1. Leyendo cobros de cada caja desde {desde} hasta ayer en SQL Server...")
            billing_conn = SQLServerConnection.get_connection()
            cursor = billing_conn.cursor()
            cursor.execute(MOVIMIENTOS_QUERY, (desde, hasta))
            rows = cursor.fetchall()
            nombres = CuadresCajaService._nombres_usuarios(billing_conn, logger)
            cuadres = agrupar_cuadres(rows, sede, nombres)
            logger.info(f"[Cuadres] {len(cuadres)} días de caja leídos.")
            if not cuadres:
                CuadresCajaService.last_result = {"ok": True, "mensaje": "Sin cobros en el período."}
                return True

            logger.info("[Cuadres] 2. Subiendo ingresos a Supabase...")
            web_conn = PostgresConnection.get_connection()
            wc = web_conn.cursor()
            wc.execute("SELECT to_regclass('inventario_v2.cuadres_caja') IS NOT NULL;")
            if not wc.fetchone()[0]:
                raise RuntimeError("Falta la tabla cuadres_caja en la web (correr las migraciones).")

            # Dos subidas de la misma sede a la vez no se pisan: van en fila y en una sola transacción.
            wc.execute("SELECT pg_advisory_xact_lock(hashtext(%s));", (f"cuadres_caja:{sede}",))

            ids = execute_values(
                wc, UPSERT_CUADRES,
                [
                    (c["sede"], c["fecha"], c["caja"], c["usuario"], c["id_cierre"], c["registrado"],
                     c["descripcion"], c["nombre"], c["tasa"])
                    for c in (v["cuadre"] for v in cuadres.values())
                ],
                template="(%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())",
                page_size=500, fetch=True,
            )
            web_id = {_clave(fecha, caja, usuario): int(pk) for pk, fecha, caja, usuario in ids}

            relleno = CuadresCajaService._rellenar_nombres_faltantes(wc, sede, desde, hasta, nombres)
            if relleno:
                logger.info(f"[Cuadres] {relleno} cajas con nombre de cajera rellenado desde Suite.")

            # Un día que ya no tiene cobros (se anularon) desaparece, salvo que Tesorería ya lo haya cuadrado.
            wc.execute(
                """
                DELETE FROM inventario_v2.cuadres_caja
                WHERE sede = %s AND fecha_cierre >= %s AND fecha_cierre < %s
                  AND conciliado_at IS NULL AND NOT (id = ANY(%s));
                """,
                (sede, desde, hasta, list(web_id.values())),
            )
            wc.execute(
                "DELETE FROM inventario_v2.cuadre_caja_pagos WHERE cuadre_caja_id = ANY(%s);",
                (list(web_id.values()),),
            )
            pagos = [
                (web_id[clave],) + pago
                for clave, c in cuadres.items()
                for pago in c["pagos"]
            ]
            if pagos:
                execute_values(wc, INSERT_PAGOS, pagos, page_size=1000)
            web_conn.commit()

            try:
                CuadresCajaService._subir_cierres(billing_conn, web_conn, sede, desde, hasta, cuadres, web_id)
            except Exception as e:
                web_conn.rollback()
                logger.warning(f"[Cuadres] No se pudo subir el resumen del cierre de Suite: {e}")

            mensaje = f"{len(cuadres)} días de caja y {len(pagos)} formas de pago subidos (desde {desde})."
            logger.info(f"[Cuadres] ✓ {mensaje}")
            CuadresCajaService.last_result = {"ok": True, "mensaje": mensaje}
            return True

        except Exception as e:
            logger.error(f"[Cuadres] Error: {e}")
            CuadresCajaService.last_result = {"ok": False, "mensaje": str(e)}
            if web_conn:
                try:
                    web_conn.rollback()
                except Exception:
                    pass
            return False
        finally:
            if ui_callback:
                try:
                    ui_callback()
                except Exception:
                    pass
            for conn in (billing_conn, web_conn):
                if conn:
                    try:
                        conn.close()
                    except Exception:
                        pass
