/**
 * category-manager.js — Gestión de categorías en WooCommerce
 * 
 * Soporta categorías padre/hijo:
 *   "TELEFONIA,SAMSUNG" → TELEFONIA (padre) → SAMSUNG (hijo de TELEFONIA)
 *   "ARTICULOS ESCOLARES,PAPELERIA" → padre/hijo
 * 
 * Mantiene cache local y sincroniza con woo-state.
 */
const WooCommerceRestApi = require('@woocommerce/woocommerce-rest-api').default;
const { getConfig } = require('./env-config');

const config = getConfig();

const api = new WooCommerceRestApi({
  url: config.WP_API_URL,
  consumerKey: config.CONSUMER_KEY,
  consumerSecret: config.CONSUMER_SECRET,
  version: 'wc/v3',
  queryStringAuth: true
});

// Cache en memoria durante la ejecución
// CLAVE NORMALIZADA: "NOMBRE_EN_UPPER|parentId" → evita duplicados por case o parentId mixto
const categoryCache = new Map(); // "NOMBRE|parentId" → { id, parentId }
let cacheLoaded = false;

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

function _cacheKey(name, parentId = 0) {
  return `${String(name).trim().toUpperCase()}|${parentId}`;
}

/**
 * Cargar todas las categorías de WooCommerce al cache.
 */
async function loadAllCategories(progressCallback) {
  if (cacheLoaded) return;

  if (progressCallback) progressCallback('📁 Cargando categorías de WooCommerce...');

  let page = 1;
  let hasMore = true;

  while (hasMore) {
    try {
      const res = await api.get('products/categories', {
        per_page: 100,
        page: page
      });

      const cats = Array.isArray(res.data) ? res.data : [];
      if (cats.length === 0) {
        hasMore = false;
      } else {
        for (const cat of cats) {
          const key = _cacheKey(cat.name, cat.parent || 0);
          categoryCache.set(key, {
            id: cat.id,
            name: cat.name.trim(),
            parentId: cat.parent || 0
          });
        }
        const totalPages = parseInt(res.headers['x-wp-totalpages'] || '1');
        if (page >= totalPages) hasMore = false;
        else page++;
      }
    } catch (e) {
      console.error(`[cat-manager] Error cargando categorías página ${page}:`, e.message);
      hasMore = false;
    }
  }

  cacheLoaded = true;
  if (progressCallback) progressCallback(`📁 ${categoryCache.size} categorías cargadas`);
}

/**
 * Busca o crea una categoría.
 * @param {string} name - Nombre de la categoría
 * @param {number} parentId - ID del padre (0 = nivel raíz)
 * @returns {number|null} ID de la categoría
 */
async function findOrCreateCategory(name, parentId = 0) {
  const key = _cacheKey(name, parentId);

  // 1. Buscar en cache (O(1), clave consistente)
  if (categoryCache.has(key)) {
    return categoryCache.get(key).id;
  }

  // 2. No en cache → buscar en WooCommerce por nombre
  try {
    const res = await api.get('products/categories', {
      per_page: 100,
      search: name.trim()
    });

    const nameUpper = String(name).trim().toUpperCase();
    const found = (res.data || []).find(c =>
      c.name.trim().toUpperCase() === nameUpper && (c.parent || 0) === parentId
    );

    if (found) {
      // Guardar en cache con clave normalizada
      categoryCache.set(key, {
        id: found.id,
        name: found.name.trim(),
        parentId: found.parent || 0
      });
      return found.id;
    }
  } catch (e) {
    console.warn(`[cat-manager] Error buscando categoría "${name}":`, e.message);
  }

  // 3. Crear la categoría (no existe en WC)
  try {
    const payload = { name: name.trim() };
    if (parentId > 0) payload.parent = parentId;

    const created = await api.post('products/categories', payload);
    if (created && created.data && created.data.id) {
      // Guardar en cache con clave normalizada
      categoryCache.set(key, {
        id: created.data.id,
        name: created.data.name.trim(),
        parentId: parentId
      });
      console.log(`[cat-manager] ✅ Creada categoría "${name.trim()}" (id: ${created.data.id}, padre: ${parentId})`);
      await sleep(300);
      return created.data.id;
    }
  } catch (e) {
    // Si el error es por nombre duplicado (WC a veces lo detecta tarde), intentar buscar de nuevo
    if (e.message && e.message.includes('term_exists')) {
      try {
        const res2 = await api.get('products/categories', { per_page: 100, search: name.trim() });
        const nameUpper = String(name).trim().toUpperCase();
        const found2 = (res2.data || []).find(c =>
          c.name.trim().toUpperCase() === nameUpper && (c.parent || 0) === parentId
        );
        if (found2) {
          categoryCache.set(key, { id: found2.id, name: found2.name.trim(), parentId });
          return found2.id;
        }
      } catch (_) { }
    }
    console.error(`[cat-manager] Error creando categoría "${name}":`, e.message);
  }

  return null;
}

/**
 * Resuelve una string de categorías "PADRE,HIJO" a array de { id }.
 * Siempre la primera es padre y la segunda es hijo del padre.
 * @param {string} categoryString - Ej: "TELEFONIA,SAMSUNG"
 * @returns {Array<{id: number}>} Array de IDs de categorías
 */
async function resolveCategories(categoryString) {
  if (!categoryString || typeof categoryString !== 'string') return [];

  const parts = categoryString.split(',').map(s => s.trim()).filter(Boolean);
  if (parts.length === 0) return [];

  const result = [];

  // Primera = padre (nivel raíz)
  const parentId = await findOrCreateCategory(parts[0], 0);
  if (parentId) {
    result.push({ id: parentId });

    // Segunda = hijo del padre
    if (parts[1]) {
      const childId = await findOrCreateCategory(parts[1], parentId);
      if (childId) {
        result.push({ id: childId });
      }
    }
  }

  return result;
}

/**
 * Exporta el cache actual como mapa nombre → id (para woo-state).
 */
function exportCacheForState() {
  const out = {};
  for (const [key, val] of categoryCache.entries()) {
    out[val.name] = val.id;
  }
  return out;
}

/**
 * Resetea el cache (útil para reiniciar).
 */
function resetCache() {
  categoryCache.clear();
  cacheLoaded = false;
}

module.exports = {
  loadAllCategories,
  findOrCreateCategory,
  resolveCategories,
  exportCacheForState,
  resetCache
};
