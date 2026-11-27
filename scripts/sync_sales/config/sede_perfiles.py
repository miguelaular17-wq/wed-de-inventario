"""Conexión Profit de cada sede. La nube no va aquí: es la misma para todas."""

from utils.constants import SQL_SYNC_PASSWORD, SQL_SYNC_USER, codigo_almacen

_QUERY = (
    "SELECT h.fecha_emision, i.articulo, i.cantidad "
    "FROM [dbo].[documentos_venta] h WITH (NOLOCK) "
    "INNER JOIN [dbo].[documentos_venta_items] i WITH (NOLOCK) "
    "ON h.tipo_documento = i.tipo_documento AND h.numero_documento = i.numero_documento "
    "WHERE h.tipo_documento = 'FAC' AND h.fecha_emision > ? "
    "ORDER BY h.fecha_emision ASC"
)

# servidor, base, intervalo en segundos, precios, compras
_SEDES = {
    "CENTRO": ("PD-CENTRO", "suitedb_centro", 60, False, False),
    "DORAL": ("DORAL-DATA", "suitedb_doral", 60, True, False),
    "JRZ": (".", "suitedb_jrz", 60, False, True),
    "MOVISTAR": (".", "suitedb", 60, False, False),
    "NUNES": (".", "suitedb_nunes", 1800, False, False),
    "SAMBIL": ("PD-SAMBIL", "suitedb_sambil", 60, False, False),
    "TECNOHOUSE": (".", "suitedb_techno", 60, False, False),
    "VIRTUDES": ("PD-VIR-DATA", "suitedb_virtudes", 60, False, False),
    "ZAMORA": ("PD-ZAMORA", "suitedb_zamora", 60, False, False),
}


def cargar_perfil(sede: str):
    from utils.helpers import es_sede_maestra_catalogo

    nombre = (sede or "").strip().upper()
    fila = _SEDES.get(nombre)
    if not fila:
        return None
    servidor, base, intervalo, precios, compras = fila
    # Doral: catálogo (categoria/subcategoria). Nunes también sube su stock (almacén 02 Virtudes).
    es_doral = es_sede_maestra_catalogo(nombre)
    return {
        "sede": nombre,
        "interval_seconds": intervalo,
        "interval_detalle": 3600,
        "sync_cobranzas": True,
        "sync_stock": es_doral or nombre == "NUNES",
        "sync_stock_periodico": False,
        # Categoría/subcategoría: Doral ON por defecto; el resto lo activa en la UI si quiere.
        "sync_categorias": es_doral,
        "almacen": codigo_almacen(nombre),
        "sync_precios": precios,
        "sync_compras": compras,
        # JRZ (compras=True): también periódico para refrescar CxP Suite.
        "sync_compras_periodico": compras,
        "interval_compras_horas": 3,
        "inicio_dia_ventas_detalle": True,
        "inicio_dia_ajustes": True,
        "sync_ventas_detalle": True,
        "sync_ajustes": True,
        "sync_cuadres_caja": True,
        "billing_db": {
            "driver": "{SQL Server}",
            "server": servidor,
            "database": base,
            "trusted_connection": False,
            "query": _QUERY,
            "user": SQL_SYNC_USER,
            "password": SQL_SYNC_PASSWORD,
        },
    }
