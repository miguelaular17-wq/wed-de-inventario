/**
 * brand-manager.js — Gestión de marcas en WooCommerce
 *
 * Usa el endpoint nativo de WooCommerce Brands (disponible desde WooCommerce 9.6+):
 *   GET/POST /wp-json/wc/v3/products/brands
 *
 * Si la tienda usa Woodmart + WooCommerce Brands (o WooCommerce 9.6+), las marcas
 * se asignan al producto con el campo "brands": [{ "id": X }] en el payload.
 *
 * Mantiene un cache en memoria para evitar peticiones repetidas.
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

// Cache en memoria: "nombre_lower" → { id, name }
const brandCache = new Map();
let cacheLoaded = false;

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

/**
 * Carga todas las marcas existentes en WooCommerce al cache.
 */
async function loadAllBrands(progressCallback) {
  if (cacheLoaded) return;

  if (progressCallback) progressCallback('🏷️ Cargando marcas de WooCommerce...');

  let page = 1;
  let hasMore = true;

  while (hasMore) {
    try {
      const res = await api.get('products/brands', {
        per_page: 100,
        page: page
      });

      const brands = Array.isArray(res.data) ? res.data : [];
      if (brands.length === 0) {
        hasMore = false;
      } else {
        for (const brand of brands) {
          brandCache.set(brand.name.trim().toLowerCase(), {
            id: brand.id,
            name: brand.name
          });
        }
        page++;
        if (brands.length < 100) hasMore = false;
      }
    } catch (e) {
      // El endpoint puede no existir si WooCommerce < 9.6 sin plugin de Brands
      console.warn(`[brand-manager] Error cargando marcas (página ${page}):`, e.message);
      console.warn('[brand-manager] ¿Está instalado WooCommerce Brands o WooCommerce 9.6+?');
      hasMore = false;
    }
  }

  cacheLoaded = true;
  if (progressCallback) progressCallback(`🏷️ ${brandCache.size} marcas cargadas`);
}

/**
 * Busca o crea una marca en WooCommerce.
 * @param {string} name - Nombre de la marca
 * @returns {number|null} ID de la marca o null si falla
 */
async function findOrCreateBrand(name) {
  if (!name || !name.trim()) return null;

  const key = name.trim().toLowerCase();

  // 1. Buscar en cache local
  if (brandCache.has(key)) {
    return brandCache.get(key).id;
  }

  // 2. Buscar en WooCommerce
  try {
    const res = await api.get('products/brands', {
      per_page: 100,
      search: name.trim()
    });

    const found = (res.data || []).find(b =>
      b.name.trim().toLowerCase() === key
    );

    if (found) {
      brandCache.set(key, { id: found.id, name: found.name });
      return found.id;
    }
  } catch (e) {
    console.warn(`[brand-manager] Error buscando marca "${name}":`, e.message);
    return null; // Si el endpoint no existe, no bloquear la sincronización
  }

  // 3. Crear la marca
  try {
    const created = await api.post('products/brands', { name: name.trim() });
    if (created && created.data && created.data.id) {
      brandCache.set(key, { id: created.data.id, name: created.data.name });
      console.log(`[brand-manager] Marca creada: "${name}" (id: ${created.data.id})`);
      await sleep(300);
      return created.data.id;
    }
  } catch (e) {
    console.error(`[brand-manager] Error creando marca "${name}":`, e.message);
  }

  return null;
}

/**
 * Resuelve el nombre de una marca a un array [{ id }] para usar en el payload del producto.
 * @param {string} marcaName - Nombre de la marca (ej: "SAMSUNG")
 * @returns {Array<{id: number}>} Array con el id de la marca, o [] si no hay marca
 */
async function resolveBrand(marcaName) {
  if (!marcaName || !marcaName.trim()) return [];

  const id = await findOrCreateBrand(marcaName.trim());
  if (id) return [{ id }];
  return [];
}

/**
 * Resetea el cache (útil para pruebas o reiniciar).
 */
function resetCache() {
  brandCache.clear();
  cacheLoaded = false;
}

module.exports = {
  loadAllBrands,
  findOrCreateBrand,
  resolveBrand,
  resetCache
};
