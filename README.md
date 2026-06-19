# Inventario Multisede — Laravel

Réplica web de `requisiciones.py` (Ventas + Inventario + exportación de requisiciones), **sin pestaña Compra ni sede local JRZ**.

## Características

- **Selector de sede** al entrar (DORAL, VIRTUDES, ZAMORA, CENTRO, SAMBIL)
- **Base de datos** con estructura equivalente a `ExelMultiSede.xlsx`:
  - `products` — producto, categoría, proveedor
  - `product_sede_metrics` — existencia, ventas 60d, última venta, promedio 15d por sede (incluye JRZ como almacén)
  - `stock_movements` — historial de traslados
- **Ventas**: acciones, OPC, sugerido (reglas de `ventas/acciones.py`)
- **Inventario**: existencia local
- **Exportar requisición**: CSV + **actualización inmediata** de stock en BD (resta origen, suma sede local)
- **Sync**: la vista Ventas recarga si detecta cambios de stock (polling 15s)

## Requisitos

- PHP 8.2+
- Composer
- Python 3 + pandas (para importar Excel; usa el `.venv` del proyecto padre si existe)

## Instalación

```bash
cd laravel_app
copy .env.example .env
composer install
php artisan key:generate
type nul > database\database.sqlite
php artisan migrate
php artisan inventario:import "..\ExelMultiSede (2).xlsx"
php artisan serve
```

Abrir http://127.0.0.1:8000 → elegir sede → Ventas / Inventario / Exportar requisición.

## PostgreSQL (`inventario_pg_config copy.json`)

Base de datos local: **prueba** @ localhost (postgres / ver JSON).

### 1. Sincronizar `.env` y migrar (Laravel)

```bash
php artisan inventario:sync-pg-env
php artisan config:clear
php artisan migrate
```

> Requiere extensión PHP `pdo_pgsql`. Si no está instalada, use el script Python abajo.

### 2. Setup completo con Python (migración + muestra + export)

```bash
python scripts/setup_pg_inventario.py 25
```

Crea esquema `inventario_v2` en PostgreSQL, importa **25 productos** desde `ExelMultiSede (2).xlsx` y exporta:

- `storage/app/exports/productos_ejemplo.json`
- `storage/app/exports/productos_ejemplo.csv` (columnas tipo ExelMultiSede)

### 3. Comandos Artisan

```bash
php artisan inventario:import "..\ExelMultiSede (2).xlsx" --sample=25
php artisan inventario:export-ejemplo --limit=25 --import
php artisan db:seed
```

### Esquema `inventario_v2` (sin compra)

| Tabla | Uso |
|-------|-----|
| `productos` | Catálogo (codigo, nombre, categoría…) |
| `stock_actual` | Existencia por sede |
| `ventas_historicas` | Promedio 15d, ventas 60d, última venta |
| `movimientos` | Traslados requisición en tiempo real |
| `reposicion` / `inventario_derivado` | Derivados UI ventas/inventario |
| `config_sede` | Pronóstico por sucursal |

## Notas

- JRZ **no** es sede local seleccionable (como en Python: JRZ = Compra, no Ventas). JRZ sigue en BD como origen de requisiciones.
- No incluye módulo **Compra** (`compra/tabla.py`).
- Corrección relacionada en Python: `compra/reporte_final_excel.py` ahora escribe JRZ existencia desde la columna `JRZ`, no desde `EXIST` local.
