/**
 * rebuild-state.js — Reconstruye el woo-state.json desde WooCommerce
 *
 * Lee TODOS los productos existentes en WooCommerce (simples + variables + variaciones)
 * y los registra en el woo-state.json local, cruzando con el JSON local para obtener
 * precio1, precio2, existencia, categories y marca originales.
 *
 * Uso: node scripts/rebuild-state.js
 *
 * ✅ Seguro: solo LEE de WooCommerce, no modifica nada.
 * ✅ Reanudable: si se interrumpe, vuelve a correr y completa.
 */
const WooCommerceRestApi = require('@woocommerce/woocommerce-rest-api').default;
const fs = require('fs');
const path = require('path');
const { getConfig } = require('./env-config');
const wooState = require('./woo-state');
const { normalizeSku } = require('./diff-engine');

// Resolver ruta base — LAZY para evitar que app.getPath() se llame antes
// de que Electron inicialice (problema con asar: true en npm run make)
function getBaseDir() {
  if (process.versions.electron) {
    const { app } = require('electron');
    return app.isPackaged ? app.getPath('userData') : path.join(__dirname, '..');
  }
  return path.join(__dirname, '..');
}

function getLocalJsonPath() { return path.join(getBaseDir(), 'uploads', 'productoslocal.json'); }
function getReportsDir() { return path.join(getBaseDir(), 'uploads', 'sync-reports'); }

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// ─── Cargar JSON local para cruzar datos ─────────────────────────────────────

function loadLocalJson() {
  const LOCAL_JSON_PATH = getLocalJsonPath();
  if (!fs.existsSync(LOCAL_JSON_PATH)) {
    console.warn('[rebuild-state] ⚠️  No se encontró productoslocal.json — se usarán datos de WooCommerce únicamente.');
    return new Map();
  }
  try {
    let rawContent = fs.readFileSync(LOCAL_JSON_PATH, 'utf8');
    // Eliminar BOM (\uFEFF) si existe — Windows puede agregarlo al guardar JSON
    if (rawContent.charCodeAt(0) === 0xFEFF) rawContent = rawContent.slice(1);
    const data = JSON.parse(rawContent);
    const map = new Map();
    for (const p of data) {
      if (p && p.codigo) {
        map.set(normalizeSku(p.codigo), p);
      }
    }
    console.log(`[rebuild-state] JSON local cargado: ${map.size} productos para cruzar datos.`);
    return map;
  } catch (e) {
    console.warn('[rebuild-state] ⚠️  Error leyendo JSON local:', e.message);
    return new Map();
  }
}

// ─── Obtener todos los productos de WooCommerce ───────────────────────────────

async function fetchAllProducts(log = console.log, api) {
  const allProducts = [];
  let page = 1;
  let totalPages = null;
  const startTime = Date.now();

  log('  Conectando con WooCommerce API...');

  while (true) {
    try {
      const res = await api.get('products', {
        per_page: 100,
        page: page,
        status: 'any',
        _fields: 'id,sku,type,regular_price,stock_quantity,categories,images,parent_id'
      });

      const products = Array.isArray(res.data) ? res.data : [];
      totalPages = totalPages || parseInt(res.headers['x-wp-totalpages'] || '1');
      const total = res.headers['x-wp-total'] || '?';

      allProducts.push(...products);

      const elapsed = ((Date.now() - startTime) / 1000).toFixed(0);
      const pct = Math.round((page / totalPages) * 100);
      // Progreso a la UI cada 5 páginas para no saturar
      if (page === 1 || page % 5 === 0 || page === totalPages) {
        log(`  📄 Página ${page}/${totalPages} (${pct}%) | ${allProducts.length}/${total} productos | ${elapsed}s`);
      }

      if (page >= totalPages || products.length === 0) break;
      page++;
      await sleep(300);

    } catch (e) {
      const errMsg = e.response
        ? `HTTP ${e.response.status}: ${JSON.stringify(e.response.data || '').substring(0, 200)}`
        : e.message;
      log(`  ⚠️ Error en página ${page}: ${errMsg} — reintentando...`);
      await sleep(2000);
      // Reintentar la misma página
      try {
        const res = await api.get('products', {
          per_page: 100,
          page: page,
          status: 'any',
          _fields: 'id,sku,type,regular_price,sale_price,stock_quantity,categories,images,parent_id'
        });
        const products = Array.isArray(res.data) ? res.data : [];
        if (products.length > 0) {
          allProducts.push(...products);
          page++;
        } else {
          log(`  ⚠️ Reintento en página ${page} devolvió 0 productos — deteniendo.`);
          break;
        }
      } catch (e2) {
        const errMsg2 = e2.response
          ? `HTTP ${e2.response.status}: ${JSON.stringify(e2.response.data || '').substring(0, 200)}`
          : e2.message;
        log(`  ❌ Error definitivo en página ${page}: ${errMsg2}`);
        break;
      }
    }
  }

  log(`\n  ✅ Descargados ${allProducts.length} productos en total.`);
  return allProducts;
}

// ─── Obtener variaciones de un producto variable ──────────────────────────────

async function fetchVariations(parentId, api, log = console.log) {
  const variations = [];
  let page = 1;

  while (true) {
    try {
      const res = await api.get(`products/${parentId}/variations`, {
        per_page: 100,
        page: page,
        _fields: 'id,sku,regular_price,stock_quantity,image,parent_id'
      });
      const vars = Array.isArray(res.data) ? res.data : [];
      variations.push(...vars);
      const totalPages = parseInt(res.headers['x-wp-totalpages'] || '1');
      if (page >= totalPages || vars.length === 0) break;
      page++;
      await sleep(200);
    } catch (e) {
      log(`  ⚠️ Error variaciones del padre ${parentId}: ${e.message}`);
      break;
    }
  }

  return variations;
}

// ─── Principal ────────────────────────────────────────────────────────────────

/**
 * Reconstruye el woo-state desde WooCommerce.
 * @param {Function|null} progressCallback - Función para emitir progreso (ej: desde IPC).
 *   Si es null, se usa console.log/process.stdout.write.
 * @returns {Object} { simples, variables, variations, total, duration }
 */
async function run(progressCallback = null) {
  const log = (msg) => {
    if (progressCallback) progressCallback(msg);
    else console.log(msg);
  };

  const startTime = Date.now();

  // Crear cliente API fresco en cada ejecución (evita problemas de caché del módulo)
  const config = getConfig();
  log(`\n  Servidor: ${config.WP_API_URL}`);
  log(`  Modo: ${config.label}`);

  const api = new WooCommerceRestApi({
    url: config.WP_API_URL,
    consumerKey: config.CONSUMER_KEY,
    consumerSecret: config.CONSUMER_SECRET,
    version: 'wc/v3',
    queryStringAuth: true,
    axiosConfig: { timeout: 60000 }
  });

  // Funciones de descarga usan el api y log locales
  async function fetchAllProductsLocal() {
    return fetchAllProducts(log, api);
  }
  async function fetchVariationsLocal(parentId) {
    return fetchVariations(parentId, api, log);
  }

  // Cargar JSON local para cruzar datos
  const localMap = loadLocalJson();

  // Empezar con un state en blanco para que el resultado refleje
  // EXACTAMENTE lo que hay en WooCommerce (sin datos viejos acumulados).
  // Conservamos solo las categorías cacheadas para no tener que recargarlas.
  const oldState = wooState.loadState();
  const state = wooState.createEmptyState();
  state.categories = oldState.categories || {};  // reutilizar caché de categorías
  const existingSkus = new Set();
  log(`[rebuild-state] Iniciando reconstrucción desde cero (state previo tenía ${Object.keys(oldState.products).length} entradas).`);

  // Descargar todos los productos de WooCommerce
  log('📥 1. Descargando productos de WooCommerce...');
  const wcProducts = await fetchAllProductsLocal();

  const variableProducts = wcProducts.filter(p => p.type === 'variable');
  const simpleProducts = wcProducts.filter(p => p.type !== 'variable');

  log(`  Simples: ${simpleProducts.length} | Variables (padres): ${variableProducts.length}`);
  log('\n📦 2. Procesando productos simples...');

  let registered = 0;
  let skipped = 0;
  let noSku = 0;

  // Procesar simples
  for (const p of simpleProducts) {
    const sku = String(p.sku || '').trim();
    if (!sku) { noSku++; continue; }
    if (existingSkus.has(sku)) { skipped++; continue; }

    const local = localMap.get(sku);

    wooState.updateProduct(state, sku, {
      wooId: p.id,
      type: 'simple',
      precio1: local ? parseFloat(local.precio1) || 0 : parseFloat(p.regular_price) || 0,
      precio2: local ? parseFloat(local.precio2) || 0 : 0,
      // sale_price se calcula localmente (precio1 * 70%) para mantener consistencia USD.
      // No se usa el valor de WooCommerce porque está en VES (moneda base del servidor).
      sale_price: (() => { const p1 = local ? parseFloat(local.precio1) : parseFloat(p.regular_price); return (p1 > 0) ? parseFloat((p1 * 0.7).toFixed(2)) : 0; })(),
      existencia: local ? parseFloat(local.existencia) || 0 : parseInt(p.stock_quantity) || 0,
      categories: local ? (local.categories || '') : (Array.isArray(p.categories) ? p.categories.map(c => c.name).join(',') : ''),
      marca: local ? (local.marca || '') : '',
      hasImage: Array.isArray(p.images) && p.images.length > 0,
      parentWooId: null
    });

    existingSkus.add(sku);
    registered++;

    if (registered % 100 === 0) {
      if (!progressCallback) process.stdout.write(`\r  Simples registrados: ${registered}/${simpleProducts.length}    `);
      else log(`  💾 Simples registrados: ${registered}/${simpleProducts.length}...`);
      wooState.saveStateIncremental(state);
    }
  }

  log(`  ✅ Simples registrados: ${registered} nuevos, ${skipped} ya existían, ${noSku} sin SKU`);

  // Procesar variables + sus variaciones
  log(`\n🔀 3. Procesando ${variableProducts.length} productos variables y sus variaciones...`);

  let variableRegistered = 0;
  let variationRegistered = 0;

  for (let i = 0; i < variableProducts.length; i++) {
    const parent = variableProducts[i];
    const parentSku = String(parent.sku || '').trim();

    if (!progressCallback) process.stdout.write(`\r  Variable ${i + 1}/${variableProducts.length} (SKU: ${parentSku || 'sin-sku'})    `);
    else if ((i + 1) % 5 === 0 || i === 0) log(`  🔀 Variable ${i + 1}/${variableProducts.length} (SKU: ${parentSku || 'sin-sku'})`);

    // Registrar el padre variable
    if (parentSku && !existingSkus.has(parentSku)) {
      const local = localMap.get(parentSku);
      wooState.updateProduct(state, parentSku, {
        wooId: parent.id,
        type: 'variable',
        precio1: local ? parseFloat(local.precio1) || 0 : parseFloat(parent.regular_price) || 0,
        precio2: local ? parseFloat(local.precio2) || 0 : 0,
        sale_price: (() => { const p1 = local ? parseFloat(local.precio1) : parseFloat(parent.regular_price); return (p1 > 0) ? parseFloat((p1 * 0.7).toFixed(2)) : 0; })(),
        existencia: 0,
        categories: local ? (local.categories || '') : '',
        marca: local ? (local.marca || '') : '',
        hasImage: Array.isArray(parent.images) && parent.images.length > 0,
        parentWooId: null
      });
      existingSkus.add(parentSku);
      variableRegistered++;

      wooState.updateVariableParent(state, parentSku, parent.id, []);
    }

    // Obtener y registrar variaciones
    const variations = await fetchVariationsLocal(parent.id);

    for (const v of variations) {
      const vSku = String(v.sku || '').trim();
      if (!vSku) continue;
      if (existingSkus.has(vSku)) continue;

      const localV = localMap.get(vSku);
      wooState.updateProduct(state, vSku, {
        wooId: v.id,
        type: 'variation',
        precio1: localV ? parseFloat(localV.precio1) || 0 : parseFloat(v.regular_price) || 0,
        precio2: localV ? parseFloat(localV.precio2) || 0 : 0,
        sale_price: (() => { const p1 = localV ? parseFloat(localV.precio1) : parseFloat(v.regular_price); return (p1 > 0) ? parseFloat((p1 * 0.7).toFixed(2)) : 0; })(),
        existencia: localV ? parseFloat(localV.existencia) || 0 : parseInt(v.stock_quantity) || 0,
        categories: localV ? (localV.categories || '') : '',
        marca: localV ? (localV.marca || '') : '',
        hasImage: !!(v.image && (v.image.id || v.image.src)),
        parentWooId: parent.id
      });
      existingSkus.add(vSku);
      variationRegistered++;
    }

    // Guardar incremental cada 5 productos variables
    if ((i + 1) % 5 === 0) {
      wooState.saveStateIncremental(state);
    }

    await sleep(200);
  }

  log(`  ✅ Variables registrados: ${variableRegistered} padres, ${variationRegistered} variaciones`);

  // Guardar estado final — limpiar sesión previa para que no aparezca
  // el banner "sesión interrumpida" al reabrir la app después del rebuild
  log('\n💾 4. Guardando woo-state.json final...');
  state.currentSession = null;
  wooState.saveState(state);

  const summary = wooState.getStateSummary(state);
  const duration = ((Date.now() - startTime) / 1000).toFixed(0);

  // Generar reporte
  const REPORTS_DIR = getReportsDir();
  if (!fs.existsSync(REPORTS_DIR)) fs.mkdirSync(REPORTS_DIR, { recursive: true });
  const dateStr = new Date().toISOString().split('T')[0];
  const timeStr = new Date().toTimeString().substring(0, 8).replace(/:/g, '-');
  const reportPath = path.join(REPORTS_DIR, `rebuild-state-${dateStr}-${timeStr}.txt`);

  const report = [
    '══════════════════════════════════════════════════════════════════',
    '  REPORTE: RECONSTRUCCIÓN DE WOO-STATE',
    `  Fecha: ${new Date().toLocaleString('es-VE')}`,
    `  Servidor: ${config.WP_API_URL}`,
    '══════════════════════════════════════════════════════════════════',
    '',
    '  TOTALES EN WOO-STATE RECONSTRUIDO:',
    `  Simples:              ${summary.simples.toLocaleString('es-VE')}`,
    `  Variables (padres):   ${summary.variables.toLocaleString('es-VE')}`,
    `  Variaciones:          ${summary.variations.toLocaleString('es-VE')}`,
    `  ─────────────────────────────────────`,
    `  TOTAL REGISTRADO:     ${summary.totalEntradas.toLocaleString('es-VE')}`,
    `  Con imagen:           ${summary.conImagen.toLocaleString('es-VE')}`,
    '',
    `  Duración: ${(duration / 60).toFixed(1)} minutos`,
    '══════════════════════════════════════════════════════════════════',
  ].join('\n');

  fs.writeFileSync(reportPath, report, 'utf8');

  log('\n══════════════════════════════════════════════════════════');
  log('  RESULTADO FINAL');
  log('══════════════════════════════════════════════════════════');
  log(`  📦 Simples:            ${summary.simples.toLocaleString('es-VE')}`);
  log(`  🔀 Variables (padres): ${summary.variables.toLocaleString('es-VE')}`);
  log(`  🎛️  Variaciones:        ${summary.variations.toLocaleString('es-VE')}`);
  log(`  ─────────────────────────────────────`);
  log(`  ✅ TOTAL EN STATE:     ${summary.totalEntradas.toLocaleString('es-VE')}`);
  log(`  🖼️  Con imagen:         ${summary.conImagen.toLocaleString('es-VE')}`);
  log(`  ⏱️  Duración:           ${(duration / 60).toFixed(1)} minutos`);
  log(`  📄 Reporte:            ${reportPath}`);
  log('══════════════════════════════════════════════════════════');
  log('  Ahora puedes ejecutar el sync normalmente.');
  log('  Solo se crearán los productos que faltan.');

  return {
    simples: summary.simples,
    variables: summary.variables,
    variations: summary.variations,
    total: summary.totalEntradas,
    conImagen: summary.conImagen,
    duration: Number(duration),
    reportPath
  };
}

// Exportar para uso como módulo (desde main.js vía IPC)
module.exports = { run };

// Si se ejecuta directamente como script standalone: node scripts/rebuild-state.js
if (require.main === module) {
  run().catch(e => {
    console.error('\n❌ Error fatal:', e);
    process.exit(1);
  });
}

