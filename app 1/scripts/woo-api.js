/**
 * woo-api.js — Wrapper WooCommerce REST API con rate limiting adaptativo
 *
 * Centraliza todas las operaciones WooCommerce con:
 * - Retry con backoff exponencial
 * - Rate limiting adaptativo
 * - Batch API para create/update/delete
 * - Soporte TEST_MODE automático via env-config.js
 * - Subida de imágenes locales WebP (desde imagenes_productos/)
 */
const WooCommerceRestApi = require('@woocommerce/woocommerce-rest-api').default;
const axios = require('axios');
const path = require('path');
const fs = require('fs');
const http = require('http');
const https = require('https');
const { getConfig } = require('./env-config');

// ─── Mapa de imágenes locales ──────────────────────────────────
// Ruta base de las imágenes locales (relativa a este archivo → ../)
const IMAGENES_BASE = path.resolve(__dirname, '..', '..', '..', 'relleno-datos-local');
const IMAGEN_MAP_PATH = path.join(IMAGENES_BASE, 'imagen_map.json');
const IMAGEN_MAP_WEB_PATH = path.join(IMAGENES_BASE, 'imagen_map_web.json');

let _localImageMap = null;
let _webImageMap = null;

function getLocalImageMap() {
  if (!_localImageMap) {
    try { _localImageMap = JSON.parse(fs.readFileSync(IMAGEN_MAP_PATH, 'utf8')); }
    catch (e) { _localImageMap = {}; console.warn('[woo-api] imagen_map.json no encontrado:', e.message); }
  }
  return _localImageMap;
}

function getWebImageMap() {
  if (!_webImageMap) {
    try { _webImageMap = JSON.parse(fs.readFileSync(IMAGEN_MAP_WEB_PATH, 'utf8')); }
    catch (e) { _webImageMap = {}; }
  }
  return _webImageMap;
}

/**
 * Busca la ruta local de una imagen para un SKU dado.
 * Primero busca en imagen_map.json (servidor interno), luego en imagen_map_web.json.
 * @param {string} sku
 * @returns {string|null} ruta absoluta al archivo local, o null
 */
function findLocalImage(sku) {
  if (!sku) return null;
  const localMap = getLocalImageMap();
  const webMap = getWebImageMap();
  const relPath = localMap[sku] || webMap[sku] || null;
  if (!relPath) return null;
  const absPath = path.join(IMAGENES_BASE, relPath);
  if (fs.existsSync(absPath)) return absPath;
  return null;
}

const config = getConfig();
const WP_API_URL = config.WP_API_URL;
const CONSUMER_KEY = config.CONSUMER_KEY;
const CONSUMER_SECRET = config.CONSUMER_SECRET;
const WP_MEDIA_USER = config.WP_MEDIA_USER;
const WP_MEDIA_PASSWORD = config.WP_MEDIA_PASSWORD;

console.log(`[woo-api] ${config.label} → ${WP_API_URL}`);

// Configuración — optimizado para Cloudways/Vultr
const MAX_RETRIES = 5;
const BATCH_SIZE = 100;
const REQUEST_TIMEOUT = 60000; // 60s para lotes grandes

// Rate limiting adaptativo
let currentDelay = 800; // Base: 800ms (Cloudways es más rápido)
const MIN_DELAY = 300;
const MAX_DELAY = 8000;
let consecutiveSuccess = 0;

const api = new WooCommerceRestApi({
  url: WP_API_URL,
  consumerKey: CONSUMER_KEY,
  consumerSecret: CONSUMER_SECRET,
  version: 'wc/v3',
  queryStringAuth: true,
  axiosConfig: {
    timeout: REQUEST_TIMEOUT,
    httpAgent: new http.Agent({ keepAlive: true, maxSockets: 10 }),
    httpsAgent: new https.Agent({ keepAlive: true, maxSockets: 10 })
  }
});

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// ─── Retry con backoff ────────────────────────────────────────

function isRetryable(error) {
  if (!error) return true;
  const status = error.response && error.response.status;
  if ([408, 429].includes(status)) return true;
  if (status >= 500 && status <= 599) return true;
  const code = error.code || '';
  return ['ECONNRESET', 'ENOTFOUND', 'ETIMEDOUT', 'ECONNABORTED', 'EAI_AGAIN'].includes(code);
}

async function retryOperation(operation, maxRetries = MAX_RETRIES) {
  let lastError;
  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      return await operation();
    } catch (error) {
      lastError = error;
      if (!isRetryable(error) || attempt >= maxRetries) break;
      const delay = 1000 * Math.pow(2, attempt - 1) + Math.floor(Math.random() * 500);
      console.warn(`[woo-api] Intento ${attempt} fallido: ${error.message}. Reintentando en ${delay}ms...`);
      await sleep(delay);
    }
  }
  throw lastError;
}

// ─── Rate limiting adaptativo ─────────────────────────────────

function onBatchSuccess() {
  consecutiveSuccess++;
  if (consecutiveSuccess >= 3) {
    currentDelay = Math.max(MIN_DELAY, currentDelay - 250);
    consecutiveSuccess = 0;
  }
}

function onBatchError() {
  consecutiveSuccess = 0;
  currentDelay = Math.min(MAX_DELAY, currentDelay * 2);
}

async function rateLimitedPause() {
  await sleep(currentDelay);
}

function getCurrentDelay() {
  return currentDelay;
}

// ─── Productos: Batch Create ──────────────────────────────────

async function batchCreateProducts(payloads, progressCallback) {
  const results = { created: [], errors: [] };

  for (let i = 0; i < payloads.length; i += BATCH_SIZE) {
    const chunk = payloads.slice(i, i + BATCH_SIZE);
    const batchNum = Math.floor(i / BATCH_SIZE) + 1;
    const totalBatches = Math.ceil(payloads.length / BATCH_SIZE);

    if (progressCallback) progressCallback(`🆕 Creando lote ${batchNum}/${totalBatches} (${chunk.length} productos)...`);

    try {
      const response = await retryOperation(async () => {
        return await api.post('products/batch', { create: chunk });
      });

      const data = response.data || {};
      const created = Array.isArray(data.create) ? data.create : [];

      // Detectar respuestas parciales: si WC devuelve menos de los enviados
      const expectedCount = chunk.length;
      const receivedCount = created.length;
      if (receivedCount < expectedCount) {
        const receivedSkus = new Set(created.map(c => c.sku));
        for (const p of chunk) {
          if (!receivedSkus.has(p.sku)) {
            results.errors.push({ sku: p.sku || '?', error: `Sin respuesta de WooCommerce (respuesta parcial: ${receivedCount}/${expectedCount})` });
          }
        }
      }

      // FIX: WooCommerce no devuelve el SKU en respuestas de error del batch.
      // Se recupera del payload enviado usando el mismo índice de posición.
      for (let idx = 0; idx < created.length; idx++) {
        const item = created[idx];
        const originalItem = chunk[idx];
        // Preferir SKU del item; si está vacío (error), usar el del payload enviado
        const sku = (item.sku && String(item.sku).trim()) || (originalItem ? String(originalItem.sku).trim() : '?');

        if (item.error) {
          results.errors.push({ sku: sku, error: item.error.message || JSON.stringify(item.error) });
          onBatchError();
        } else {
          results.created.push({ id: item.id, sku: sku, name: item.name, type: item.type });
          onBatchSuccess();
        }
      }

      const ok = created.filter(c => !c.error).length;
      if (progressCallback) progressCallback(`✓ Lote ${batchNum}/${totalBatches}: ${ok} creados (delay actual: ${currentDelay}ms)`);
    } catch (e) {
      if (progressCallback) progressCallback(`❌ Error lote ${batchNum}: ${e.message}`);
      for (const p of chunk) {
        results.errors.push({ sku: p.sku || '?', error: e.message });
      }
      onBatchError();
    }

    await rateLimitedPause();
  }

  return results;
}

// ─── Productos: Batch Update ──────────────────────────────────

async function batchUpdateProducts(payloads, progressCallback) {
  const results = { updated: 0, errors: [] };

  for (let i = 0; i < payloads.length; i += BATCH_SIZE) {
    const chunk = payloads.slice(i, i + BATCH_SIZE);
    const batchNum = Math.floor(i / BATCH_SIZE) + 1;
    const totalBatches = Math.ceil(payloads.length / BATCH_SIZE);

    if (progressCallback) progressCallback(`✏️ Actualizando lote ${batchNum}/${totalBatches} (${chunk.length} productos)...`);

    try {
      const response = await retryOperation(async () => {
        return await api.post('products/batch', { update: chunk });
      });

      const data = response.data || {};
      const updated = Array.isArray(data.update) ? data.update : [];

      for (const item of updated) {
        if (item.error) {
          results.errors.push({ id: item.id, error: item.error.message || JSON.stringify(item.error) });
          onBatchError();
        } else {
          results.updated++;
          onBatchSuccess();
        }
      }
    } catch (e) {
      if (progressCallback) progressCallback(`❌ Error lote ${batchNum}: ${e.message}`);
      for (const p of chunk) results.errors.push({ id: p.id, error: e.message });
      onBatchError();
    }

    await rateLimitedPause();
  }

  return results;
}

// ─── Productos: Batch Delete ──────────────────────────────────

async function batchDeleteProducts(ids, progressCallback) {
  const results = { deleted: 0, errors: [] };

  for (let i = 0; i < ids.length; i += BATCH_SIZE) {
    const chunk = ids.slice(i, i + BATCH_SIZE);
    const batchNum = Math.floor(i / BATCH_SIZE) + 1;
    const totalBatches = Math.ceil(ids.length / BATCH_SIZE);

    if (progressCallback) progressCallback(`🗑️ Eliminando lote ${batchNum}/${totalBatches} (${chunk.length} productos)...`);

    try {
      const response = await retryOperation(async () => {
        return await api.post('products/batch', { delete: chunk });
      });

      const data = response.data || {};
      const deleted = Array.isArray(data.delete) ? data.delete : [];

      for (const item of deleted) {
        if (item.error) {
          results.errors.push({ id: item.id, error: item.error.message || JSON.stringify(item.error) });
        } else {
          results.deleted++;
        }
      }
    } catch (e) {
      if (progressCallback) progressCallback(`❌ Error eliminando lote ${batchNum}: ${e.message}`);
      for (const id of chunk) results.errors.push({ id, error: e.message });
      onBatchError();
    }

    await rateLimitedPause();
  }

  return results;
}

// ─── Variaciones: CRUD ────────────────────────────────────────

async function createVariation(parentId, payload) {
  return retryOperation(async () => {
    const res = await api.post(`products/${parentId}/variations`, payload);
    return res.data;
  });
}

async function batchUpdateVariations(parentId, payloads, progressCallback) {
  const results = { updated: 0, errors: [] };

  for (let i = 0; i < payloads.length; i += BATCH_SIZE) {
    const chunk = payloads.slice(i, i + BATCH_SIZE);

    try {
      const response = await retryOperation(async () => {
        return await api.post(`products/${parentId}/variations/batch`, { update: chunk });
      });

      const data = response.data || {};
      const updated = Array.isArray(data.update) ? data.update : [];

      for (const item of updated) {
        if (item.error) {
          results.errors.push({ id: item.id, error: item.error.message });
        } else {
          results.updated++;
        }
      }
    } catch (e) {
      for (const p of chunk) results.errors.push({ id: p.id, error: e.message });
      onBatchError();
    }

    await rateLimitedPause();
  }

  return results;
}

async function deleteVariations(parentId, ids) {
  return retryOperation(async () => {
    return await api.post(`products/${parentId}/variations/batch`, { delete: ids });
  });
}

// ─── Imágenes ─────────────────────────────────────────────────

/**
 * Sube una imagen a WordPress.
 * Prioridad:
 *   1. Imagen local WebP/JPG del disco (imagen_map.json) → convertir a WebP si no lo es
 *   2. URL externa (https://) → descargar, convertir a WebP y subir
 *   3. URL interna (192.168.x.x) → solo si no hay local, intentar descarga
 *
 * @param {string} srcUrl  - url_imagen del producto (puede ser URL o ignorarse si hay local)
 * @param {Function} progressCallback
 * @param {string} sku     - código del producto para buscar imagen local
 * @returns {number|null} mediaId de WordPress o null
 */
async function uploadImageToWordPress(srcUrl, progressCallback, sku) {
  if (!WP_MEDIA_USER || !WP_MEDIA_PASSWORD || !WP_API_URL) {
    console.warn('[woo-api] Credenciales de media no configuradas');
    return null;
  }

  let buf = null;
  let contentType = 'image/jpeg';
  let useWebp = false;
  let sourceLabel = '';

  // ── 1. Buscar imagen local primero ─────────────────────────
  const localPath = findLocalImage(sku);
  if (localPath) {
    try {
      const rawBuf = fs.readFileSync(localPath);
      const ext = path.extname(localPath).toLowerCase();
      const sizeKB = (rawBuf.length / 1024).toFixed(1);
      sourceLabel = `local (${path.basename(localPath)})`;

      if (ext === '.webp') {
        // Ya es WebP, subir directo
        buf = rawBuf;
        contentType = 'image/webp';
        useWebp = true;
        if (progressCallback) progressCallback(`  📁 Imagen local WebP encontrada (${sizeKB}KB)`);
      } else {
        // Convertir JPG/PNG → WebP con sharp
        try {
          const sharp = require('sharp');
          const webpBuf = await sharp(rawBuf).webp({ quality: 82 }).toBuffer();
          const webpKB = (webpBuf.length / 1024).toFixed(1);
          const saved = (((rawBuf.length - webpBuf.length) / rawBuf.length) * 100).toFixed(0);
          if (progressCallback) progressCallback(`  📁 Local ${ext.toUpperCase()} → WebP: ${sizeKB}KB → ${webpKB}KB (ahorro ${saved}%)`);
          buf = webpBuf;
          contentType = 'image/webp';
          useWebp = true;
        } catch (sharpErr) {
          // Sin sharp: subir formato original
          buf = rawBuf;
          if (ext === '.png') contentType = 'image/png';
          if (progressCallback) progressCallback(`  📁 Local ${ext.toUpperCase()} (${sizeKB}KB) — sharp no disponible, subiendo original`);
        }
      }
    } catch (readErr) {
      console.warn('[woo-api] Error leyendo imagen local:', readErr.message);
      buf = null; // Caer a URL
    }
  }

  // ── 2. Si no hay imagen local, usar URL (http:// o https://) ───
  if (!buf) {
    const urlStr = String(srcUrl || '').trim();
    // Aceptar tanto https:// como http:// (URLs internas de red local 192.168.x.x)
    if (/^https?:\/\//i.test(urlStr)) {
      try {
        const isInternal = /^http:\/\//i.test(urlStr);
        sourceLabel = isInternal ? 'URL interna (red local)' : 'URL externa';
        if (progressCallback) progressCallback(`  🌐 Descargando desde ${sourceLabel}...`);
        const imgResp = await axios.get(urlStr, { responseType: 'arraybuffer', timeout: 15000 });
        const rawBuf = Buffer.from(imgResp.data);
        const sizeKB = (rawBuf.length / 1024).toFixed(1);
        const ct = (imgResp.headers['content-type'] || '').toLowerCase();

        if (ct.includes('webp')) {
          buf = rawBuf; contentType = 'image/webp'; useWebp = true;
          if (progressCallback) progressCallback(`  ✅ URL ya es WebP (${sizeKB}KB)`);
        } else {
          try {
            const sharp = require('sharp');
            const webpBuf = await sharp(rawBuf).webp({ quality: 82 }).toBuffer();
            const webpKB = (webpBuf.length / 1024).toFixed(1);
            const saved = (((rawBuf.length - webpBuf.length) / rawBuf.length) * 100).toFixed(0);
            if (progressCallback) progressCallback(`  🔄 URL → WebP: ${sizeKB}KB → ${webpKB}KB (ahorro ${saved}%)`);
            buf = webpBuf; contentType = 'image/webp'; useWebp = true;
          } catch {
            buf = rawBuf;
            if (ct.includes('png')) contentType = 'image/png';
            if (progressCallback) progressCallback(`  ⚠️ sharp no disponible, subiendo original (${sizeKB}KB)`);
          }
        }
      } catch (dlErr) {
        console.warn('[woo-api] Error descargando imagen URL:', dlErr.message);
        if (progressCallback) progressCallback(`  ⚠️ Error descargando imagen: ${dlErr.message}`);
      }
    } else {
      if (progressCallback) progressCallback(`  ⚠️ Sin imagen local y URL no válida — omitiendo`);
      return null;
    }
  }

  if (!buf) return null;

  // ── 3. Subir a WordPress Media Library ─────────────────────
  try {
    const b64 = Buffer.from(`${WP_MEDIA_USER}:${WP_MEDIA_PASSWORD}`).toString('base64');
    const ext = useWebp ? 'webp' : (contentType.includes('png') ? 'png' : 'jpg');
    const filename = `img-${sku || Date.now()}.${ext}`;
    const mediaUrl = `${WP_API_URL.replace(/\/+$/, '')}/wp-json/wp/v2/media`;

    const res = await axios.post(mediaUrl, buf, {
      headers: {
        'Authorization': `Basic ${b64}`,
        'Content-Type': contentType,
        'Content-Disposition': `attachment; filename="${filename}"`
      },
      maxContentLength: Infinity,
      maxBodyLength: Infinity,
      timeout: 30000
    });

    if (res && res.data && res.data.id) {
      if (progressCallback) progressCallback(`  ✅ Subida como ${filename} (mediaId: ${res.data.id}) [${sourceLabel}]`);
      return res.data.id;
    }
  } catch (e) {
    console.warn('[woo-api] Error subiendo imagen a WP Media:', e.message);
  }

  return null;
}

async function setProductImage(productId, mediaId) {
  // 404 = producto no existe en WooCommerce → no reintentar
  return retryOperation(async () => {
    const res = await api.put(`products/${productId}`, { images: [{ id: mediaId }] });
    return res;
  }, MAX_RETRIES);
}

async function setVariationImage(parentId, variationId, mediaId) {
  // 404 = variación no existe → no reintentar
  return retryOperation(async () => {
    const res = await api.put(`products/${parentId}/variations/${variationId}`, { image: { id: mediaId } });
    return res;
  }, MAX_RETRIES);
}

/**
 * Busca un producto en WooCommerce por SKU.
 * Útil para recuperar el wooId real cuando el state está desincronizado.
 * @param {string} sku
 * @returns {Object|null} Producto de WooCommerce o null si no existe
 */
async function getProductBySku(sku) {
  try {
    const res = await api.get('products', { sku: String(sku).trim(), per_page: 1 });
    const items = res.data;
    if (Array.isArray(items) && items.length > 0) return items[0];
    return null;
  } catch (e) {
    return null;
  }
}

// ─── Producto individual ──────────────────────────────────────

async function createSingleProduct(payload) {
  return retryOperation(async () => {
    const res = await api.post('products', payload);
    return res.data;
  });
}

async function getProductById(id) {
  return retryOperation(async () => {
    const res = await api.get(`products/${id}`);
    return res.data;
  });
}

module.exports = {
  batchCreateProducts,
  batchUpdateProducts,
  batchDeleteProducts,
  createVariation,
  batchUpdateVariations,
  deleteVariations,
  uploadImageToWordPress,
  setProductImage,
  setVariationImage,
  createSingleProduct,
  getProductById,
  getProductBySku,
  rateLimitedPause,
  getCurrentDelay,
  BATCH_SIZE
};
