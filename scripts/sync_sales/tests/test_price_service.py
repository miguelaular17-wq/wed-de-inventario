"""Prueba las reglas de elección de precio sin conectar a Suite ni a la web."""
import importlib.util
import sys
import types
from pathlib import Path


def _load():
    root = Path(__file__).resolve().parents[1]
    # Stubs de dependencias de red / DB
    for name in ("database", "database.sqlserver", "database.postgres", "utils", "utils.logger"):
        sys.modules.setdefault(name, types.ModuleType(name))
    sys.modules["database.sqlserver"].SQLServerConnection = object
    sys.modules["database.postgres"].PostgresConnection = object

    class AppLogger:
        @staticmethod
        def get_logger():
            return types.SimpleNamespace(info=lambda *a, **k: None, error=lambda *a, **k: None)

    sys.modules["utils.logger"].AppLogger = AppLogger
    sys.modules["psycopg2"] = types.ModuleType("psycopg2")
    sys.modules["psycopg2.extras"] = types.ModuleType("psycopg2.extras")

    path = root / "services" / "price_service.py"
    spec = importlib.util.spec_from_file_location("price_service_under_test", path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod.PriceService


def test_elige_primer_tramo_con_precio():
    P = _load()
    assert P._elegir_precio([(0.0, 0.0), (206.67, 206.67)]) == (206.67, 206.67)
    assert P._elegir_precio([(100.0, 100.0), (200.0, 200.0)]) == (100.0, 100.0)
    assert P._elegir_precio([(0.0, 0.0), (0.0, 0.0)]) == (0.0, 0.0)
    assert P._elegir_precio([]) is None


def test_precio_util():
    P = _load()
    assert P._precio_util(0, 0) is False
    assert P._precio_util(0.005, 0) is False
    assert P._precio_util(0.01, 0) is True
    assert P._precio_util(0, 233.33) is True


def test_prefiere_activo_con_stock_ante_duplicados():
    P = _load()
    # Caso máscara: 7886 activo+stock+5.30 vs 36377 activo+sin stock+4.41
    bueno = (P._rank(1, 184, 5.30, 4.80, 7886), (5.30, 4.80))
    malo = (P._rank(1, 0, 4.41, 3.27, 36377), (4.41, 3.27))
    assert P._mejor_que(bueno, malo) is True
    assert P._mejor_que(malo, bueno) is False

    # Sin stock: activo gana a inactivo
    activo = (P._rank(1, 0, 4.41, 3.27, 10), (4.41, 3.27))
    inactivo = (P._rank(0, 100, 5.30, 4.80, 1), (5.30, 4.80))
    assert P._mejor_que(activo, inactivo) is True


def test_precios_distintos_tolera_centavos():
    P = _load()
    assert P._precios_distintos(5.30, 4.80, 5.30, 4.80) is False
    assert P._precios_distintos(5.30035, 4.80, 5.30, 4.80) is False
    assert P._precios_distintos(4.41, 3.27, 5.30, 4.80) is True


if __name__ == "__main__":
    test_elige_primer_tramo_con_precio()
    test_precio_util()
    test_prefiere_activo_con_stock_ante_duplicados()
    test_precios_distintos_tolera_centavos()
    print("OK")
