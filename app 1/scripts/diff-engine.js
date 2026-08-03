/**
 * diff-engine.js — Motor de comparación local vs estado de WooCommerce
 * 
 * Compara el JSON enriquecido del sistema local contra woo-state.json
 * y clasifica cada producto en: crear, actualizar, eliminar, imagen, omitir.
 */
const { pricesEqual, normalizeRules, calculatePrices } = require('./price-calculator');
const crypto = require('crypto');

/**
 * Normaliza un SKU para que sea compatible con WooCommerce.
 * WooCommerce convierte automáticamente caracteres no-ASCII (ej: Ñ→N, á→a)
 * al guardar en la base de datos. Para evitar desync entre el state local
 * y WooCommerce, aplicamos la misma normalización antes de enviar.
 * 
 * Conversiones aplicadas:
 *   - Ñ → N, ñ → n (caso más común en español)
 *   - Caracteres con acento (á,é,í,ó,ú,ü) → sin acento
 *   - El resto de caracteres no-ASCII se elimina
 * 
 * @param {string|number} rawSku - SKU original del JSON local
 * @returns {string} SKU normalizado, listo para usar en WooCommerce
 */
function normalizeSku(rawSku) {
  if (!rawSku && rawSku !== 0) return '';
  return String(rawSku)
    .trim()
    // Normalización Unicode NFD: separa letras base de diacríticos
    .normalize('NFD')
    // Eliminar marcas diacríticas (acentos, tildes, etc.)
    .replace(/[\u0300-\u036f]/g, '')
    // Caso especial: Ñ/ñ no se descompone por NFD, tratarlo explícitamente
    .replace(/Ñ/g, 'N')
    .replace(/ñ/g, 'n')
    // Eliminar cualquier otro carácter no-ASCII que pudiera quedar
    .replace(/[^\x00-\x7F]/g, '');
}

/**
 * Calcula un hash MD5 corto (8 chars) de un string.
 * Muy rápido para comparar textos largos como descripciones.
 */
function shortHash(text) {
  if (!text) return '';
  return crypto.createHash('md5').update(String(text)).digest('hex').substring(0, 8);
}

/**
 * @param {Array} localProducts - Array del JSON enriquecido (productoslocal.json)
 * @param {Object} state - Estado de woo-state.js (loadState())
 * @param {Object} options - { discountPercent, syncPrecio, syncStock, syncCategorias, syncMarca, syncDescripcion, imagesEnabled }
 * @returns {Object} Resultado del diff
 */
function computeDiff(localProducts, state, options = {}) {
  const {
    discountPercent = 30,
    specialCategories,
    syncPrecio = true,
    syncStock = true,
    syncCategorias = true,
    syncMarca = false,
    syncDescripcion = false,
    imagesEnabled = false
  } = options;

  const result = {
    toCreate: [],
    toUpdate: [],
    toDelete: [],
    toUploadImage: [],
    unchanged: [],
    skipped: [],
    stats: {
      total: localProducts.length,
      new: 0,
      modified: 0,
      deleted: 0,
      images: 0,
      skipped: 0,
      unchanged: 0
    }
  };

  // Pre-pase: detectar códigos de padre a partir de variaciones COMPLETAS
  // (con atributo Y termino), para identificar padres explícitos en el JSON
  const completedParentCodes = new Set(
    localProducts
      .filter(p => p && p.codigo_padre && p.atributo && p.termino)
      .map(p => normalizeSku(p.codigo_padre))
  );

  // Map de SKU normalizado → codigo original, para detectar duplicados post-normalización.
  // Ej: si el JSON tiene "PN4039" y "PÑ4039", ambos normalizan a "PN4039" y el segundo se omite.
  const localSkus = new Map();

  for (const product of localProducts) {
    if (!product || !product.codigo) {
      result.skipped.push({ sku: '?', reason: 'Sin código/SKU' });
      result.stats.skipped++;
      continue;
    }

    // normalizeSku convierte Ñ→N y elimina caracteres no-ASCII para que el SKU
    // coincida exactamente con cómo WooCommerce lo almacenará en la DB.
    const sku = normalizeSku(product.codigo);

    // ─── Detectar duplicados post-normalización ───
    // Si ya procesamos un producto con el mismo SKU normalizado, omitir este.
    if (localSkus.has(sku)) {
      const codigoOriginalPrevio = localSkus.get(sku);
      result.skipped.push({
        sku,
        reason: `⚠️ SKU duplicado tras normalización: "${product.codigo}" y "${codigoOriginalPrevio}" producen el mismo SKU "${sku}". Corrija el JSON eliminando uno de los dos.`
      });
      result.stats.skipped++;
      continue;
    }
    localSkus.set(sku, String(product.codigo));

    // ─── Validación de variación incompleta ───
    // Un producto con codigo_padre DEBE tener atributo Y termino.
    // Si le falta alguno, se omite con un aviso claro para que el usuario lo corrija.
    if (product.codigo_padre) {
      const faltaAtributo = !product.atributo;
      const faltaTermino = !product.termino;
      if (faltaAtributo || faltaTermino) {
        const camposFaltantes = [
          faltaAtributo ? '"atributo"' : null,
          faltaTermino ? '"termino"' : null
        ].filter(Boolean).join(' y ');
        result.skipped.push({
          sku,
          reason: `⚠️ Variación incompleta — falta ${camposFaltantes} (padre: ${product.codigo_padre}). Corrija el JSON y vuelva a sincronizar.`
        });
        result.stats.skipped++;
        continue;
      }
    }

    // ─── Filtros de exclusión ───
    const categories = (product.categories || '').toUpperCase();

    // USO INTERNO / INSUMO INTERNO → siempre skip
    if (categories.includes('USO INTERNO') || categories.includes('INSUMO INTERNO')) {
      result.skipped.push({ sku, reason: 'Categoría USO INTERNO / INSUMO INTERNO — excluido de sincronización' });
      result.stats.skipped++;
      continue;
    }

    // Precio inválido → skip
    const precio1 = parseFloat(product.precio1);
    if (isNaN(precio1) || precio1 <= 0) {
      result.skipped.push({ sku, reason: `⚠️ Precio inválido (${product.precio1}) — corrija el JSON.` });
      result.stats.skipped++;
      continue;
    }

    // ─── ¿Existe en el estado? ───
    // Buscar primero en products, luego en variableParents (padres variables guardados en formato anterior)
    let existing = state.products[sku];

    if (!existing && state.variableParents && state.variableParents[sku]) {
      // Padre variable guardado solo en variableParents (formato antiguo)
      // Lo tratamos como existente para no recrearlo
      const vp = state.variableParents[sku];
      existing = {
        wooId: vp.wooId,
        type: 'variable',
        precio1: 0,
        precio2: 0,
        existencia: 0,
        categories: '',
        marca: '',
        hasImage: false,
        parentWooId: null,
        _fromVariableParents: true  // marca para migración al state.products
      };
    }

    if (!existing) {
      // PRODUCTO NUEVO
      // Validar que tenga datos mínimos para crear
      if (!product.descripcion || String(product.descripcion).trim().length < 3) {
        result.skipped.push({ sku, reason: `⚠️ Descripción demasiado corta o vacía — corrija el JSON.` });
        result.stats.skipped++;
        continue;
      }

      // Detectar si es un padre variable explícito:
      // Su propio codigo coincide con el codigo_padre de alguna variación completa
      // y él mismo NO tiene codigo_padre.
      const isExplicitParent = !product.codigo_padre && completedParentCodes.has(sku);

      result.toCreate.push({
        sku,
        product,
        isVariation: !!(product.codigo_padre && product.atributo && product.termino),
        isExplicitParent
      });
      result.stats.new++;

      // También necesita imagen si tiene URL
      if (product.url_imagen && String(product.url_imagen).trim().length > 10) {
        result.toUploadImage.push({ sku, product, wooId: null }); // wooId se asigna post-creación
        result.stats.images++;
      }
      continue;
    }


    // ─── PRODUCTO EXISTENTE: Detectar cambios ───
    const changes = [];
    const prices = calculatePrices(product, discountPercent, specialCategories);

    // Precio
    if (syncPrecio && !pricesEqual(existing.precio1, precio1)) {
      changes.push({ field: 'precio', old: existing.precio1, new: precio1 });
    }

    // Sale price: comparar el calculado localmente (precio1 * descuento%) vs lo guardado en el state.
    // Si el precio regular cambia, el sale_price se recalcula automáticamente y se actualiza también.
    if (syncPrecio && prices.sale_price != null) {
      const desiredSale = parseFloat(prices.sale_price);
      const stateSale = parseFloat(existing.sale_price) || 0;
      if (!pricesEqual(stateSale, desiredSale)) {
        changes.push({ field: 'sale_price', old: stateSale, new: desiredSale });
      }
    }

    // Stock
    if (syncStock) {
      const stockLocal = parseFloat(product.existencia) || 0;
      const stockRemote = parseFloat(existing.existencia) || 0;
      if (Math.floor(stockLocal) !== Math.floor(stockRemote)) {
        changes.push({ field: 'stock', old: stockRemote, new: stockLocal });
      }
    }

    // Categorías
    if (syncCategorias) {
      const catLocal = (product.categories || '').trim().toLowerCase();
      const catRemote = (existing.categories || '').trim().toLowerCase();
      if (catLocal !== catRemote) {
        changes.push({ field: 'categorias', old: catRemote, new: catLocal });
      }
    }

    // Marca
    if (syncMarca) {
      const marcaLocal = (product.marca || '').trim().toLowerCase();
      const marcaRemote = (existing.marca || '').trim().toLowerCase();
      if (marcaLocal !== marcaRemote) {
        changes.push({ field: 'marca', old: marcaRemote, new: marcaLocal });
      }
    }

    // Descripción ampliada (comparación por hash MD5 corto → muy rápida)
    if (syncDescripcion) {
      const descLocal = (product.descripcion_ampliada || '').trim();
      const hashLocal = shortHash(descLocal);
      const hashRemote = existing.descripcionHash || '';
      if (descLocal && hashLocal !== hashRemote) {
        changes.push({ field: 'descripcion', old: hashRemote, new: hashLocal, value: descLocal });
      }
    }

    if (changes.length > 0) {
      result.toUpdate.push({
        sku,
        product,
        existing,
        changes,
        prices,
        isVariation: !!(product.codigo_padre),
        isExplicitParent: false
      });
      result.stats.modified++;
    } else {
      result.unchanged.push(sku);
      result.stats.unchanged++;
    }

    // Imagen: si el producto existe en Woo pero no tiene imagen, y el local sí tiene URL
    // Solo se añade a la cola si imagesEnabled está activo (evita recorrer la lista en vano)
    if (imagesEnabled && !existing.hasImage && product.url_imagen && String(product.url_imagen).trim().length > 10) {
      result.toUploadImage.push({ sku, product, wooId: existing.wooId });
      result.stats.images++;
    }
  }

  // ─── Detectar eliminados ───
  for (const sku of Object.keys(state.products)) {
    if (!localSkus.has(sku)) {
      const stateProduct = state.products[sku];

      // Verificar categoría SKIP
      const stateCats = (stateProduct.categories || '').toUpperCase();
      if (stateCats.includes('SKIP')) {
        result.skipped.push({ sku, reason: 'Categoría SKIP — protegido de eliminación' });
        result.stats.skipped++;
        continue;
      }

      // Proteger padres variables sintéticos:
      // El padre (ej: "840268907235-P") no existe en el JSON pero sí es
      // referenciado como codigo_padre por variaciones activas en el JSON.
      // Eliminarlo rompería las variaciones en WooCommerce.
      // Se protege silenciosamente — no se muestra en el progreso.
      if (completedParentCodes.has(sku)) {
        continue;
      }

      result.toDelete.push({
        sku,
        wooId: stateProduct.wooId,
        type: stateProduct.type,
        parentWooId: stateProduct.parentWooId
      });
      result.stats.deleted++;
    }
  }

  return result;
}

/**
 * Genera un resumen legible del diff.
 */
function formatDiffSummary(diff) {
  let summary = '═══ RESUMEN DE CAMBIOS ═══\n';
  summary += `📊 Total productos en JSON: ${diff.stats.total}\n`;
  summary += `🆕 Nuevos: ${diff.stats.new}\n`;
  summary += `✏️ Modificados: ${diff.stats.modified}\n`;
  summary += `🗑️ A eliminar: ${diff.stats.deleted}\n`;
  summary += `🖼️ Imágenes pendientes: ${diff.stats.images}\n`;
  summary += `⏭️ Omitidos: ${diff.stats.skipped}\n`;
  summary += `✅ Sin cambios: ${diff.stats.unchanged}\n`;

  if (diff.toUpdate.length > 0 && diff.toUpdate.length <= 50) {
    summary += '\n─── Detalle de modificaciones ───\n';
    for (const u of diff.toUpdate.slice(0, 30)) {
      const changeStr = u.changes.map(c => `${c.field}: ${c.old} → ${c.new}`).join(', ');
      summary += `  ${u.sku}: ${changeStr}\n`;
    }
    if (diff.toUpdate.length > 30) {
      summary += `  ... y ${diff.toUpdate.length - 30} más\n`;
    }
  }

  if (diff.skipped.length > 0) {
    summary += '\n─── Productos omitidos ───\n';
    for (const s of diff.skipped) {
      summary += `  ⏭️ ${s.sku}: ${s.reason}\n`;
    }
  }

  return summary;
}

module.exports = { computeDiff, formatDiffSummary, normalizeSku };
