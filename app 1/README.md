# Palacio Sync — Sincronizador de productos WooCommerce

Aplicacion de escritorio (Electron) para sincronizar el catalogo local de **Palacio de los Detalles** con su tienda WooCommerce. Compara un archivo JSON exportado del sistema de gestion interno contra el estado guardado localmente y aplica solo los cambios detectados: creaciones, actualizaciones, eliminaciones e imagenes.

---

## Requisitos previos

| Herramienta | Version minima |
|---|---|
| Node.js | 18+ |
| npm | 9+ |
| WooCommerce | 8.0+ (con REST API activada) |
| WooCommerce Brands | Recomendado para sync de marcas |

---

## Instalacion

```bash
npm install
```

Crea el archivo `.env` en la raiz del proyecto copiando `.env.ejemplo` y completando las credenciales:

```bash
copy .env.ejemplo .env
```

Edita `.env` con tus datos reales (ver seccion **Configuracion**).

---

## Uso

### Iniciar la aplicacion

```bash
npm start
```

Se abre la ventana principal. El badge en el header muestra si la conexion esta activa y cuantos productos tiene el state guardado.

### Flujo normal de sincronizacion

1. Arrastra o selecciona el JSON exportado del sistema de gestion.
2. El panel de **pre-analisis** compara la cantidad de productos del JSON contra el state guardado y advierte si el JSON parece incompleto.
3. Selecciona los campos a comparar: precio, stock, categorias, marca, descripcion, imagenes.
4. Ajusta el porcentaje de descuento (por defecto 30%).
5. Haz clic en **Sincronizar**.

El log en tiempo real muestra cada operacion. Al terminar aparece el panel de resultados con creados, actualizados, eliminados, imagenes y errores.

### Primera vez (sin state)

Si es la primera sincronizacion o el `woo-state.json` no existe:

- **Opcion A - Reconstruir state**: pulsa "Reconstruir State". La app descarga todos los productos existentes en WooCommerce y construye el state local. Tarda varios minutos dependiendo del catalogo.
- **Opcion B - Importar state**: si ya tienes un `woo-state.json` de una instalacion anterior, pulsa "Importar estado" y seleccionalo.
- **Opcion C - Sincronizacion inicial directa**: sin state, el motor tratara todos los productos del JSON como nuevos y los creara en WooCommerce.

---

## Configuracion (.env)

| Variable | Descripcion |
|---|---|
| `TEST_MODE` | `true` usa credenciales `_TEST`, `false` usa produccion |
| `URLP` | URL de la tienda en produccion |
| `WOOCOMMERCE_CONSUMER_KEYP` | Consumer Key de WooCommerce (produccion) |
| `WOOCOMMERCE_CONSUMER_SECRETP` | Consumer Secret de WooCommerce (produccion) |
| `WP_MEDIA_USER` | Usuario de WordPress para subir imagenes (REST Media API) |
| `WP_MEDIA_PASSWORD` | Contrasena de aplicacion de WordPress |
| `URL_TEST` | URL de la tienda de prueba/staging |
| `WOOCOMMERCE_CONSUMER_KEY_TEST` | Consumer Key de WooCommerce (test) |
| `WOOCOMMERCE_CONSUMER_SECRET_TEST` | Consumer Secret de WooCommerce (test) |
| `WP_MEDIA_USER_TEST` | Usuario WordPress del entorno test |
| `WP_MEDIA_PASSWORD_TEST` | Contrasena de aplicacion del entorno test |

Las credenciales de WooCommerce se generan en: **WordPress Admin -> WooCommerce -> Ajustes -> Avanzado -> REST API**.

La contrasena de aplicacion de WordPress se genera en: **WordPress Admin -> Usuarios -> Tu perfil -> Contrasenas de aplicacion**.

---

## Formato del JSON de entrada

El archivo JSON debe ser un **array de objetos**:

```json
[
  {
    "codigo": "ABC123",
    "descripcion": "Nombre del producto",
    "precio1": 100.00,
    "precio2": 85.00,
    "existencia": 50,
    "categories": "ELECTRONICA,CELULARES",
    "marca": "SAMSUNG",
    "url_imagen": "http://192.168.0.100/fotos/ABC123.jpg",
    "descripcion_ampliada": "Texto largo del producto..."
  }
]
```

### Campos

| Campo | Requerido | Descripcion |
|---|---|---|
| `codigo` | SI | SKU del producto. Se convierte a ASCII puro (N->N, acentos eliminados) |
| `descripcion` | SI | Nombre visible del producto en WooCommerce |
| `precio1` | SI | Precio de detal (regular_price). Debe ser mayor a 0 |
| `precio2` | Opcional | Precio al mayor. Si es menor que precio1, activa regla de porcentaje |
| `existencia` | Opcional | Stock disponible |
| `categories` | Opcional | Categorias separadas por coma. Primer valor = padre, segundo = hijo |
| `marca` | Opcional | Nombre de la marca (requiere WooCommerce Brands) |
| `url_imagen` | Opcional | URL de la imagen del producto |
| `descripcion_ampliada` | Opcional | Descripcion larga (sincronizada como short_description) |
| `codigo_padre` | Opcional | SKU del producto variable padre (indica que este es una variacion) |
| `atributo` | Requerido si es variacion | Nombre del atributo (ej: "Talla", "Color") |
| `termino` | Requerido si es variacion | Valor del atributo (ej: "L", "Rojo") |

### Productos variables

Un producto variable se compone de:

1. **Un registro padre**: `codigo_padre` vacio, con el mismo `codigo` que aparece como `codigo_padre` en las variaciones.
2. **Registros de variacion**: tienen `codigo_padre`, `atributo` y `termino` completos.

Si el padre no existe en el JSON, el motor lo crea automaticamente como producto variable sintetico.

### Exclusiones automaticas

Los productos con las siguientes categorias son ignorados durante la sincronizacion:
- `USO INTERNO`
- `INSUMO INTERNO`

Los productos marcados con la categoria `SKIP` en el state estan protegidos de eliminacion aunque no aparezcan en el JSON.

---

## Opciones de sincronizacion

| Opcion | Por defecto | Descripcion |
|---|---|---|
| Precio | SI | Compara precio1 y actualiza regular_price / sale_price |
| Stock | SI | Compara existencia y actualiza stock_quantity |
| Categorias | SI | Compara y actualiza categorias en WooCommerce |
| Marca | NO | Compara marca y actualiza brands |
| Descripcion | NO | Compara hash de descripcion_ampliada y actualiza short_description |
| Imagenes | NO | Sube imagenes faltantes desde url_imagen o carpeta local |
| Eliminar ausentes | NO | Elimina de WooCommerce los productos que no esten en el JSON |
| Forzar (sin limite 40%) | NO | Desactiva el limite de seguridad de eliminacion masiva |

### Calculo de precios

- `regular_price` = `precio1`
- `sale_price` = `precio1 x (1 - descuento%)` por defecto 30%
- Si `precio2 < precio1`, se activa una regla de porcentaje al mayor para 3+ unidades

---

## State local (woo-state.json)

El motor mantiene un archivo `uploads/woo-state.json` que es el espejo local de WooCommerce. Cada sincronizacion:

1. Lee el state.
2. Compara contra el JSON local (diff).
3. Aplica los cambios en WooCommerce.
4. Actualiza el state de forma incremental (despues de cada lote, no solo al final).

El guardado incremental garantiza que si el proceso se interrumpe (cierre de ventana, corte de luz), el state refleje lo que ya se proceso. Al reiniciar la app, detecta la sesion incompleta y muestra un banner de advertencia.

### Backups automaticos

Antes de cada guardado completo, el state anterior se guarda en `uploads/state-backups/`. Se conservan los ultimos 10 backups.

---

## Reportes

Cada sincronizacion genera un archivo JSON en `uploads/sync-reports/` con nombre tipo:

```
sync-report-2026-07-19-14-30-00.json
```

El reporte incluye:

- Totales actuales en WooCommerce (simples, variables, variaciones, con imagen)
- Productos creados en esta sesion (SKU + WooID)
- Errores detallados (SKU + mensaje)
- Productos omitidos (SKU + motivo)
- Duracion de la sesion
- Configuracion usada (descuento, campos activos, modo test/produccion)

---

## Imagenes

### Fuentes de imagen (en orden de prioridad)

1. **Carpeta local de red** (`relleno-datos-local/`): la app busca el SKU en `imagen_map.json`. Si existe, lee el archivo del disco y lo convierte a WebP antes de subirlo.
2. **`imagen_map_web.json`**: mapa alternativo para imagenes en URLs web.
3. **`url_imagen` del producto**: descarga desde la URL indicada y convierte a WebP si `sharp` esta disponible.

Las imagenes se convierten a **WebP con calidad 82%** para reducir el peso antes de subirlas a la Media Library de WordPress.

### Delay entre imagenes

Para no saturar el servidor, hay una pausa de **3 segundos** entre cada subida de imagen.

---

## Estructura de archivos

```
palacio-sync/
|-- main.js              (Proceso principal Electron - IPC handlers)
|-- preload.js           (Bridge contextIsolation entre main y renderer)
|-- renderer.js          (Logica de la UI, sin acceso a Node)
|-- index.html           (Estructura del UI)
|-- styles.css           (Estilos)
|-- package.json
|-- .env                 (Credenciales - NO subir a git)
|-- .env.ejemplo         (Plantilla sin credenciales reales)
|-- uploads/             (Archivos generados en tiempo de ejecucion)
|   |-- productoslocal.json     (JSON subido en la ultima sync)
|   |-- woo-state.json          (State de WooCommerce)
|   |-- state-backups/          (Backups automaticos del state)
|   +-- sync-reports/           (Reportes de cada sesion)
+-- scripts/
    |-- sync-engine.js       (Orquestador principal del flujo de sync)
    |-- diff-engine.js       (Comparacion JSON local vs woo-state)
    |-- woo-api.js           (Wrapper REST API WooCommerce con retry y rate limiting)
    |-- woo-state.js         (Lectura y escritura del state local)
    |-- env-config.js        (Resolucion de credenciales segun TEST_MODE)
    |-- price-calculator.js  (Calculo de precios y reglas de mayor)
    |-- rebuild-state.js     (Descarga de WooCommerce para reconstruir el state)
    |-- category-manager.js  (Gestion de categorias padre/hijo)
    |-- brand-manager.js     (Gestion de marcas - WooCommerce Brands)
    +-- image-uploader.js    (Subida de imagenes con fallback por SKU)
```

---

## Errores comunes

**`wooId not found (404)` al subir imagenes**
El state tiene un ID desincronizado con WooCommerce. El motor lo detecta automaticamente, busca el producto por SKU y corrige el ID. No requiere accion manual.

**`Sin respuesta de WooCommerce (respuesta parcial)`**
WooCommerce devolvio menos productos de los enviados en el batch. Los SKUs faltantes se registran como error en el reporte. Volver a sincronizar los procesa.

**`Variacion incompleta - falta "atributo" y/o "termino"`**
El JSON tiene un producto con `codigo_padre` pero sin los campos de variacion completos. Corregir el JSON de origen.

**`SKU duplicado tras normalizacion`**
Dos codigos distintos en el JSON producen el mismo SKU al eliminar caracteres especiales (ej: `PN123` y `PN123` con Ñ). Corregir los SKUs en el sistema de origen.

**Rate limiting / errores 429**
El servidor esta limitando peticiones. El rate limiting adaptativo ajusta el delay automaticamente. Si persiste, aumentar `THROTTLE_DELAY_MS` en `.env`.

---

## Empaquetado para distribucion

```bash
npm run make
```

Genera el instalador en la carpeta `out/`. En Windows produce un `.exe` con Squirrel.
