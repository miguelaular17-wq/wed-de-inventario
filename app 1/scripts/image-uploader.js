/**
 * image-uploader.js — Subida de imágenes a WooCommerce
 * 
 * Sube imágenes individuales con delay entre cada una para no saturar el servidor.
 * Soporta imágenes desde URL local (192.168.0.212) y URLs externas.
 *
 * Fix: cuando setProductImage falla con 404 (wooId desincronizado del state),
 * se busca el wooId real por SKU en WooCommerce antes de rendirse.
 */
const { uploadImageToWordPress, setProductImage, setVariationImage, getProductBySku, rateLimitedPause } = require('./woo-api');

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// Delay entre subidas de imágenes (más conservador que batches de texto)
const IMAGE_DELAY = 3000; // 3 segundos entre imágenes

/**
 * Determina si un item es una variación (necesita endpoint diferente).
 * Tipo 'variation' → PUT /products/{parentId}/variations/{id} con image: {id}
 * Tipo 'simple' o 'variable' → PUT /products/{id} con images: [{id}]
 */
function isVariationType(item) {
  return item.type === 'variation' && !!item.parentWooId;
}

/**
 * Intenta asignar una imagen a un producto. Si falla con 404 (wooId desincronizado),
 * busca el producto por SKU en WooCommerce y reintenta con el ID correcto.
 * 
 * Diferencia importante por tipo de producto:
 *   - variation  → setVariationImage(parentId, varId, mediaId)   [image singular]
 *   - simple/variable → setProductImage(productId, mediaId)      [images array]
 * 
 * @returns {{ success: boolean, resolvedWooId: number }}
 */
async function assignImageWithFallback(item, mediaId, progressCallback) {
  const is404 = (e) => {
    const status = e && e.response && e.response.status;
    return status === 404;
  };

  const doAssign = async (wooId) => {
    if (isVariationType(item)) {
      // Variación: endpoint especial, campo "image" (singular)
      await setVariationImage(item.parentWooId, wooId, mediaId);
    } else {
      // Simple o variable (padre): endpoint estándar, campo "images" (array)
      await setProductImage(wooId, mediaId);
    }
  };

  // Intento 1: usar el wooId del state
  try {
    await doAssign(item.wooId);
    return { success: true, resolvedWooId: item.wooId };
  } catch (e) {
    if (!is404(e)) throw e; // Error distinto a 404 → propagar

    // 404: el wooId del state no existe → buscar por SKU en WooCommerce
    if (progressCallback) {
      progressCallback(`  ⚠️ wooId ${item.wooId} no encontrado (404) para ${item.type} "${item.sku}". Buscando por SKU...`);
    }

    const wooProduct = await getProductBySku(item.sku);
    if (!wooProduct || !wooProduct.id) {
      throw new Error(`Producto SKU "${item.sku}" (${item.type}) no encontrado en WooCommerce (wooId ${item.wooId} → 404)`);
    }

    const realWooId = wooProduct.id;
    if (progressCallback) {
      progressCallback(`  🔄 wooId corregido: ${item.wooId} → ${realWooId} (${item.type})`);
    }

    // Intento 2: con el ID real recuperado de WooCommerce
    await doAssign(realWooId);
    return { success: true, resolvedWooId: realWooId };
  }
}


/**
 * Sube imágenes para una lista de productos.
 * @param {Array} imageItems - Array de { sku, product, wooId, type, parentWooId }
 * @param {Function} progressCallback
 * @returns {Object} { uploaded, failed, skipped, wooIdCorrections }
 */
async function uploadImages(imageItems, progressCallback) {
  const results = { uploaded: 0, failed: 0, skipped: 0, details: [], wooIdCorrections: [] };
  const parentImagesAssigned = new Set();

  if (!imageItems || imageItems.length === 0) return results;

  if (progressCallback) progressCallback(`🖼️ Iniciando subida de ${imageItems.length} imágenes...`);

  for (let i = 0; i < imageItems.length; i++) {
    const item = imageItems[i];
    const imgUrl = item.product && item.product.url_imagen
      ? String(item.product.url_imagen).trim()
      : '';

    if (!imgUrl || imgUrl.length < 10) {
      results.skipped++;
      continue;
    }

    if (progressCallback) {
      progressCallback(`🖼️ [${i + 1}/${imageItems.length}] Subiendo imagen para ${item.sku}...`);
    }

    try {
      const mediaId = await uploadImageToWordPress(imgUrl, progressCallback, item.sku);

      if (!mediaId) {
        results.failed++;
        results.details.push({ sku: item.sku, status: 'upload_failed' });
        continue;
      }

      if (!item.wooId) {
        results.details.push({ sku: item.sku, status: 'no_woo_id', mediaId });
        results.failed++;
        continue;
      }

      // Asignar imagen con fallback por SKU si el wooId falla con 404
      const assignResult = await assignImageWithFallback(item, mediaId, progressCallback);

      if (assignResult.resolvedWooId !== item.wooId) {
        // Registrar la corrección para actualizar el state
        results.wooIdCorrections.push({ sku: item.sku, oldId: item.wooId, newId: assignResult.resolvedWooId });
      }

      // Asignar imagen al padre variable si es variación
      if (item.type === 'variation' && item.parentWooId && !parentImagesAssigned.has(item.parentWooId)) {
        try {
          await setProductImage(item.parentWooId, mediaId);
          parentImagesAssigned.add(item.parentWooId);
        } catch (e) {
          console.warn(`[image-uploader] No se pudo asignar imagen al padre ${item.parentWooId}:`, e.message);
        }
      }

      results.uploaded++;
      results.details.push({ sku: item.sku, status: 'ok', mediaId, resolvedWooId: assignResult.resolvedWooId });

      if (progressCallback) {
        progressCallback(`✅ [${i + 1}/${imageItems.length}] Imagen subida para ${item.sku} (mediaId: ${mediaId})`);
      }
    } catch (e) {
      results.failed++;
      results.details.push({ sku: item.sku, status: 'error', error: e.message });
      if (progressCallback) {
        progressCallback(`❌ [${i + 1}/${imageItems.length}] Error imagen ${item.sku}: ${e.message}`);
      }
    }

    // Pausa entre imágenes
    await sleep(IMAGE_DELAY);
  }

  if (progressCallback) {
    progressCallback(`🖼️ Imágenes: ${results.uploaded} subidas, ${results.failed} errores, ${results.skipped} omitidas`);
    if (results.wooIdCorrections.length > 0) {
      progressCallback(`🔄 ${results.wooIdCorrections.length} wooIds corregidos automáticamente (state desincronizado)`);
    }
  }

  return results;
}

module.exports = { uploadImages, IMAGE_DELAY };
