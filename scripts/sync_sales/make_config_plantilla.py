"""Genera config.json plantilla para el instalador (sin password; nube por bootstrap)."""
from __future__ import annotations

import json
import os
import sys

from utils.constants import APP_VERSION, DEFAULT_SYNC_TOKEN, DEFAULT_UPDATE_URL


def main() -> int:
    dest = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "dist_instalador", "config.json")
    os.makedirs(os.path.dirname(dest) or ".", exist_ok=True)
    cfg = {
        "sede": "JRZ",
        "interval_seconds": 1800,
        "update_url": DEFAULT_UPDATE_URL,
        "sync_token": DEFAULT_SYNC_TOKEN,
        "app_version": APP_VERSION,
        "sync_stock": True,
        "sync_precios": True,
        "sync_cobranzas": True,
        "sync_compras": True,
        "sync_ventas_detalle": True,
        "sync_ajustes": True,
        "inicio_dia_ventas_detalle": True,
        "inicio_dia_ajustes": True,
        "interval_detalle": 10800,
        "billing_db": {
            "driver": "{SQL Server}",
            "server": r"localhost\SQLEXPRESS",
            "database": "suitedb_centro",
            "trusted_connection": True,
            "query": (
                "SELECT h.fecha_emision, i.articulo, i.cantidad "
                "FROM [dbo].[documentos_venta] h WITH (NOLOCK) "
                "INNER JOIN [dbo].[documentos_venta_items] i WITH (NOLOCK) "
                "ON h.tipo_documento = i.tipo_documento AND h.numero_documento = i.numero_documento "
                "WHERE h.tipo_documento = 'FAC' AND h.fecha_emision > ? "
                "ORDER BY h.fecha_emision ASC"
            ),
        },
        "web_db": {
            "host": "",
            "port": 5432,
            "database": "postgres",
            "user": "",
            "password": "",
        },
    }
    with open(dest, "w", encoding="utf-8") as f:
        json.dump(cfg, f, indent=2)
    print(f"OK config: {dest}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
