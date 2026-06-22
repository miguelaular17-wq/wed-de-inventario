#!/usr/bin/env python3
"""Exporta ExelMultiSede.xlsx a JSON para importación Laravel."""

from __future__ import annotations

import json
import math
import sys
from datetime import date, datetime
from pathlib import Path

import pandas as pd

SEDE_MAP = {
    "JRZ": "JRZ",
    "DORAL": "DORAL",
    "Virtude": "VIRTUDES",
    "Zamora": "ZAMORA",
    "Centro": "CENTRO",
    "Sambil": "SAMBIL",
}


def norm_cod(val) -> str:
    if val is None or (isinstance(val, float) and math.isnan(val)):
        return ""
    if isinstance(val, int):
        return str(val)
    if isinstance(val, float):
        r = round(val)
        if abs(val - r) < 1e-9:
            return str(int(r))
    s = str(val).strip()
    if s.endswith(".0") and s[:-2].lstrip("-").isdigit():
        return s[:-2]
    return s


def _norm_cod_series(series: pd.Series) -> pd.Series:
    return series.map(norm_cod)


def _parse_date_series(series: pd.Series) -> pd.Series:
    parsed = pd.to_datetime(series, dayfirst=True, errors="coerce")
    out = parsed.dt.strftime("%Y-%m-%d")
    return out.where(parsed.notna(), None)


def export_excel(path: Path) -> list[dict]:
    xl = pd.ExcelFile(path)
    sheet = "BDD" if "BDD" in xl.sheet_names else xl.sheet_names[0]
    df = pd.read_excel(path, sheet_name=sheet)

    if "COD CENTRO" not in df.columns or "PRODUCTO" not in df.columns:
        return []

    cat_col = "Categoría" if "Categoría" in df.columns else ("Categoria" if "Categoria" in df.columns else None)
    sub_col = "Subcategoría" if "Subcategoría" in df.columns else (
        "Subcategoria" if "Subcategoria" in df.columns else None
    )
    prov_col = "Proveedor" if "Proveedor" in df.columns else None
    precio1_col = "Precio 1" if "Precio 1" in df.columns else ("precio 1" if "precio 1" in df.columns else None)
    precio2_col = "Precio 2" if "Precio 2" in df.columns else ("precio 2" if "precio 2" in df.columns else None)

    df = df.copy()
    df["_cod"] = _norm_cod_series(df["COD CENTRO"])
    df["_producto"] = df["PRODUCTO"].fillna("").astype(str).str.strip()
    df = df[(df["_cod"] != "") & (df["_producto"] != "")]

    sede_fields: dict[str, dict[str, str | None]] = {}
    for label, sede_key in SEDE_MAP.items():
        ex_col = f"{label} existencia"
        prom_col = f"{label} promedio 15 días (60d)"
        vent_col = f"{label} ventas"
        uv_col = f"{label} última venta"
        uc_col = f"{label} última compra"
        prefix = f"_{sede_key}_"
        if ex_col in df.columns:
            df[prefix + "ex"] = pd.to_numeric(df[ex_col], errors="coerce").fillna(0).astype(int)
        else:
            df[prefix + "ex"] = 0
        if prom_col in df.columns:
            df[prefix + "prom"] = pd.to_numeric(df[prom_col], errors="coerce").fillna(0).astype(int)
        else:
            df[prefix + "prom"] = 0
        if vent_col in df.columns:
            df[prefix + "vent"] = pd.to_numeric(df[vent_col], errors="coerce").fillna(0.0)
        else:
            df[prefix + "vent"] = 0.0
        if uv_col in df.columns:
            df[prefix + "uv"] = _parse_date_series(df[uv_col])
        else:
            df[prefix + "uv"] = None
        if uc_col in df.columns:
            df[prefix + "uc"] = _parse_date_series(df[uc_col])
        else:
            df[prefix + "uc"] = None
        sede_fields[sede_key] = {"prefix": prefix}

    if cat_col:
        df["_categoria"] = df[cat_col].fillna("").astype(str).str.strip()
    else:
        df["_categoria"] = ""
    if sub_col:
        df["_subcategoria"] = df[sub_col].fillna("").astype(str).str.strip()
    else:
        df["_subcategoria"] = ""
    if prov_col:
        df["_proveedor"] = df[prov_col].fillna("").astype(str).str.strip()
    else:
        df["_proveedor"] = ""
    if precio1_col:
        df["_precio_unidad"] = pd.to_numeric(df[precio1_col], errors="coerce").fillna(0.0).round(2)
    else:
        df["_precio_unidad"] = 0.0
    if precio2_col:
        df["_precio_mayor"] = pd.to_numeric(df[precio2_col], errors="coerce").fillna(0.0).round(2)
    else:
        df["_precio_mayor"] = 0.0

    rows: list[dict] = []
    codes = df["_cod"].tolist()
    productos = df["_producto"].tolist()
    categorias = df["_categoria"].tolist()
    subcategorias = df["_subcategoria"].tolist()
    proveedores = df["_proveedor"].tolist()
    precios_unidad = df["_precio_unidad"].tolist()
    precios_mayor = df["_precio_mayor"].tolist()

    sede_arrays: dict[str, dict[str, list]] = {}
    for sede_key, meta in sede_fields.items():
        prefix = meta["prefix"]
        sede_arrays[sede_key] = {
            "ex": df[prefix + "ex"].tolist(),
            "prom": df[prefix + "prom"].tolist(),
            "vent": df[prefix + "vent"].tolist(),
            "uv": df[prefix + "uv"].tolist(),
            "uc": df[prefix + "uc"].tolist(),
        }

    n = len(df)
    for i in range(n):
        sedes: dict = {}
        for sede_key, arr in sede_arrays.items():
            existencia = int(arr["ex"][i])
            promedio = int(arr["prom"][i])
            ventas = float(arr["vent"][i])
            uv = arr["uv"][i]
            uc = arr["uc"][i]
            if uv is not None and isinstance(uv, float) and math.isnan(uv):
                uv = None
            if uc is not None and isinstance(uc, float) and math.isnan(uc):
                uc = None
            if existencia or promedio or ventas or uv or uc:
                sedes[sede_key] = {
                    "existencia": existencia,
                    "ventas_60d": ventas,
                    "ultima_venta": uv,
                    "ultima_compra": uc,
                    "promedio_15d": promedio,
                }

        rows.append(
            {
                "cod_centro": codes[i],
                "producto": productos[i],
                "categoria": categorias[i],
                "subcategoria": subcategorias[i],
                "proveedor": proveedores[i],
                "precio_unidad": float(precios_unidad[i]),
                "precio_mayor": float(precios_mayor[i]),
                "sedes": sedes,
            }
        )

    return rows


def main() -> int:
    if len(sys.argv) < 3:
        print("Uso: export_multisede_json.py <entrada.xlsx> <salida.json>", file=sys.stderr)
        return 1
    src = Path(sys.argv[1])
    dst = Path(sys.argv[2])
    if not src.is_file():
        print(f"No existe: {src}", file=sys.stderr)
        return 1
    data = export_excel(src)
    with dst.open("w", encoding="utf-8") as fh:
        json.dump(data, fh, ensure_ascii=False)
    print(len(data))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
