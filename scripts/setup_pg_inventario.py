#!/usr/bin/env python3
"""Aplica esquema inventario_v2 en PostgreSQL e importa/exporta productos de ejemplo."""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LARAVEL = ROOT / "laravel_app"
SCRIPTS = LARAVEL / "scripts"


def load_pg_config() -> dict:
    for name in ("inventario_pg_config copy.json", "inventario_pg_config.json"):
        p = ROOT / name
        if p.is_file():
            return json.loads(p.read_text(encoding="utf-8"))
    raise FileNotFoundError("No se encontró inventario_pg_config copy.json")


def connect(cfg: dict):
    import psycopg2

    return psycopg2.connect(
        host=cfg["PGHOST"],
        port=cfg.get("PGPORT", 5432),
        dbname=cfg["PGDATABASE"],
        user=cfg["PGUSER"],
        password=cfg["PGPASSWORD"],
    )


def run_migration(conn) -> None:
    sql_path = LARAVEL / "database" / "sql" / "inventario_v2_setup.sql"
    sql = sql_path.read_text(encoding="utf-8")
    with conn.cursor() as cur:
        cur.execute(sql)
    conn.commit()
    print("Migración inventario_v2 aplicada.")


def import_sample(conn, excel: Path, limit: int = 25) -> int:
    import importlib.util

    spec = importlib.util.spec_from_file_location(
        "export_multisede_json", SCRIPTS / "export_multisede_json.py"
    )
    mod = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(mod)

    rows = mod.export_excel(excel)[:limit]
    with conn.cursor() as cur:
        cur.execute("TRUNCATE inventario_v2.movimientos, inventario_v2.ventas_historicas, inventario_v2.stock_actual, inventario_v2.productos RESTART IDENTITY CASCADE")

        for row in rows:
            cur.execute(
                """
                INSERT INTO inventario_v2.productos (codigo, nombre, categoria, subcategoria, proveedor)
                VALUES (%s, %s, %s, %s, %s)
                RETURNING id
                """,
                (
                    row["cod_centro"],
                    row["producto"],
                    row.get("categoria", ""),
                    row.get("subcategoria", ""),
                    row.get("proveedor", ""),
                ),
            )
            pid = cur.fetchone()[0]
            for sede, m in row.get("sedes", {}).items():
                cur.execute(
                    """
                    INSERT INTO inventario_v2.stock_actual (producto_id, sede, existencia)
                    VALUES (%s, %s, %s)
                    """,
                    (pid, sede, max(0, int(m.get("existencia", 0)))),
                )
                cur.execute(
                    """
                    INSERT INTO inventario_v2.ventas_historicas
                        (producto_id, sede, venta_promedio, ventas_60d, ultima_venta)
                    VALUES (%s, %s, %s, %s, %s)
                    """,
                    (
                        pid,
                        sede,
                        int(m.get("promedio_15d", 0)),
                        float(m.get("ventas_60d", 0)),
                        m.get("ultima_venta"),
                    ),
                )
    conn.commit()
    print(f"Importados {len(rows)} productos de ejemplo.")
    return len(rows)


def export_sample(conn, out_dir: Path, limit: int = 25) -> None:
    out_dir.mkdir(parents=True, exist_ok=True)
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT p.codigo, p.nombre, p.categoria, p.subcategoria, p.proveedor,
                   s.sede, s.existencia, v.venta_promedio, v.ventas_60d
            FROM inventario_v2.productos p
            LEFT JOIN inventario_v2.stock_actual s ON s.producto_id = p.id
            LEFT JOIN inventario_v2.ventas_historicas v
                ON v.producto_id = p.id AND v.sede = s.sede
            ORDER BY p.codigo, s.sede
            LIMIT %s
            """,
            (limit * 6,),
        )
        rows_raw = cur.fetchall()

    products: dict[str, dict] = {}
    for cod, nom, cat, sub, prov, sede, ex, prom, v60 in rows_raw:
        if cod not in products:
            products[cod] = {
                "cod_centro": cod,
                "producto": nom,
                "categoria": cat,
                "subcategoria": sub,
                "proveedor": prov or "",
                "sedes": {},
            }
        if sede:
            products[cod]["sedes"][sede] = {
                "existencia": ex or 0,
                "promedio_15d": int(prom or 0),
                "ventas_60d": float(v60 or 0),
            }

    sample = list(products.values())[:limit]
    json_path = out_dir / "productos_ejemplo.json"
    json_path.write_text(json.dumps(sample, ensure_ascii=False, indent=2), encoding="utf-8")

    display = {
        "JRZ": "JRZ",
        "DORAL": "DORAL",
        "VIRTUDES": "Virtude",
        "ZAMORA": "Zamora",
        "CENTRO": "Centro",
        "SAMBIL": "Sambil",
    }
    headers = ["PRODUCTO", "COD CENTRO", "Categoría", "Subcategoría", "Proveedor"]
    for label in display.values():
        headers.append(f"{label} existencia")
        if label != "JRZ":
            headers.append(f"{label} ventas")
        headers.append(f"{label} promedio 15 días (60d)")

    csv_lines = [";".join(headers)]
    sede_keys = list(display.keys())
    for row in sample:
        line = [
            row["producto"],
            row["cod_centro"],
            row["categoria"],
            row["subcategoria"],
            row["proveedor"],
        ]
        for sk in sede_keys:
            m = row["sedes"].get(sk, {})
            line.append(str(m.get("existencia", 0)))
            if sk != "JRZ":
                line.append(str(m.get("ventas_60d", 0)))
            line.append(str(m.get("promedio_15d", 0)))
        csv_lines.append(";".join(line))

    csv_path = out_dir / "productos_ejemplo.csv"
    csv_path.write_text("\n".join(csv_lines) + "\n", encoding="utf-8")
    print(f"Exportado JSON: {json_path}")
    print(f"Exportado CSV:  {csv_path}")


def main() -> int:
    limit = int(sys.argv[1]) if len(sys.argv) > 1 else 25
    excel = ROOT / "ExelMultiSede (2).xlsx"
    if not excel.is_file():
        print(f"No existe {excel}", file=sys.stderr)
        return 1

    cfg = load_pg_config()
    conn = connect(cfg)
    try:
        run_migration(conn)
        import_sample(conn, excel, limit)
        export_sample(conn, LARAVEL / "storage" / "app" / "exports", limit)
    finally:
        conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
