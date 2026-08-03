/**
 * sync-engine.js — Motor de sincronización principal
 *
 * Orquesta todo el flujo:
 * 1. Leer JSON local → filtrar inválidos
 * 2. Leer woo-state → comparar (diff)
 * 3. Crear categorías faltantes
 * 4. Crear productos nuevos (padres variables primero, luego variaciones, luego simples)
 * 5. Actualizar productos modificados
 * 6. Eliminar productos desaparecidos (excepto SKIP)
 * 7. Subir imágenes
 * 8. Actualizar woo-state.json  ← guardado INCREMENTAL después de cada lote
 * 9. Generar archivo de reporte completo
 *
 * FIX CRÍTICO v2: el state se guarda después de CADA lote, no solo al final.
 * Si el proceso se interrumpe, la siguiente corrida retomará desde donde quedó.
 */
const fs = require('fs');
const path = require('path');
const wooState = require('./woo-state');
const { computeDiff, formatDiffSummary, normalizeSku } = require('./diff-engine');
const { calculatePrices } = require('./price-calculator');
const catManager = require('./category-manager');
const brandManager = require('./brand-manager');
const wooApi = require('./woo-api');
const { uploadImages } = require('./image-uploader');

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

// ─── Generador de reporte de sesión ──────────────────────────────────────────

/**
 * Genera el archivo de reporte de la sesión de sync.
 * Incluye TODOS los datos necesarios para diagnóstico:
 * - Totales reales en WooCommerce (desde el state)
 * - Productos creados en esta sesión con sus WooIDs
 * - Errores detallados con SKU y mensaje
 * - Productos omitidos con razón
 * - Imágenes procesadas
 */
function generateSyncReport(state, report, sessionId, startTime, options) {
  const REPORTS_DIR = getReportsDir();
  try {
    if (!fs.existsSync(REPORTS_DIR)) {
      fs.mkdirSync(REPORTS_DIR, { recursive: true });
    }

    const endTime = new Date();
    const duration = ((endTime - startTime) / 1000).toFixed(0);
    const durationMin = (duration / 60).toFixed(1);
    const stateSummary = wooState.getStateSummary(state);
    const session = state.currentSession || {};

    const dateStr = endTime.toISOString().split('T')[0];
    const timeStr = endTime.toTimeString().substring(0, 8).replace(/:/g, '-');
    const reportFileName = `sync-report-${dateStr}-${timeStr}.json`;
    const reportFilePath = path.join(REPORTS_DIR, reportFileName);

    // ── Reporte en JSON (completo, para diagnóstico programático) ──
    const jsonReport = {
      meta: {
        sessionId,
        generadoEl: endTime.toISOString(),
        duracionSegundos: Number(duration),
        duracionMinutos: Number(durationMin),
        servidor: options.serverUrl || 'N/A',
        modoTest: options.testMode || false,
        descuentoPct: options.discountPercent || 30,
        imagenesHabilitadas: options.imagesEnabled || false
      },
      totalesEnWooCommerce: {
        simples: stateSummary.simples,
        productosVariables: stateSummary.variables,
        variaciones: stateSummary.variations,
        totalVisible: stateSummary.totalEnWooCommerce,
        conImagen: stateSummary.conImagen,
        totalEntradas_state: stateSummary.totalEntradas
      },
      estaSesion: {
        creados: (session.createdDetails || []).length,
        actualizados: (session.updatedDetails || []).length,
        eliminados: (session.deletedDetails || []).length,
        pendientesEliminacion: (session.pendingDeleteDetails || []).length,
        imagenesSubidas: session.imagesUploaded || 0,
        omitidos: session.skipped || 0,
        errores: (session.errorDetails || []).length
      },
      // ── Listas detalladas por categoría ──────────────────────────────
      productosNuevos: (session.createdDetails || []),
      productosActualizados: (session.updatedDetails || []).map(u => ({
        sku: u.sku,
        wooId: u.wooId,
        tipo: u.type,
        nombre: u.name,
        cambios: u.cambios   // [{ campo, de, a }]
      })),
      productosEliminados: (session.deletedDetails || []).map(d => ({
        sku: d.sku,
        wooId: d.wooId,
        tipo: d.type,
        nombre: d.name
      })),
      productosPendientesEliminacion: (session.pendingDeleteDetails || []).map(d => ({
        sku: d.sku,
        wooId: d.wooId,
        tipo: d.type,
        nota: 'Ausente del JSON — se eliminará si activas el switch "Eliminar ausentes"'
      })),
      errores: session.errorDetails || [],
      omitidos: session.skippedDetails || [],
      pendientes: {
        totalEnJSON: session.totalInJson || 0,
        yaEnWooCommerce: stateSummary.totalEntradas,
        faltanPorSubir: Math.max(0, (session.totalInJson || 0) - stateSummary.totalEntradas)
      }
    };

    fs.writeFileSync(reportFilePath, JSON.stringify(jsonReport, null, 2), 'utf8');

    // ── Reporte en TXT (legible por humanos) ──
    const txtFileName = `sync-report-${dateStr}-${timeStr}.txt`;
    const txtFilePath = path.join(REPORTS_DIR, txtFileName);

    const nl = '\n';
    let txt = '';
    txt += '══════════════════════════════════════════════════════════════════' + nl;
    txt += '  REPORTE DE SINCRONIZACIÓN — PALACIO DE LOS DETALLES' + nl;
    txt += `  Sesión: ${sessionId}` + nl;
    txt += `  Generado: ${endTime.toLocaleString('es-VE')}` + nl;
    txt += `  Duración: ${durationMin} minutos (${duration} segundos)` + nl;
    txt += `  Servidor: ${options.serverUrl || 'N/A'}` + nl;
    txt += '══════════════════════════════════════════════════════════════════' + nl;
    txt += nl;

    txt += '━━━ TOTALES EN WOOCOMMERCE (estado acumulado) ━━━━━━━━━━━━━━━━━━━' + nl;
    txt += `  Productos simples:        ${stateSummary.simples.toLocaleString('es-VE')}` + nl;
    txt += `  Productos variables:      ${stateSummary.variables.toLocaleString('es-VE')}  (padres)` + nl;
    txt += `  Variaciones:              ${stateSummary.variations.toLocaleString('es-VE')}` + nl;
    txt += `  ─────────────────────────────────────────────────` + nl;
    txt += `  TOTAL VISIBLE EN WC:      ${stateSummary.totalEnWooCommerce.toLocaleString('es-VE')}` + nl;
    txt += `  Con imagen:               ${stateSummary.conImagen.toLocaleString('es-VE')}` + nl;
    txt += nl;

    txt += '━━━ ESTA SESIÓN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' + nl;
    txt += `  🆕 Creados:              ${(session.created || 0).toLocaleString('es-VE')}` + nl;
    txt += `  ✏️  Actualizados:         ${(report.updated || 0).toLocaleString('es-VE')}` + nl;
    txt += `  🗑️  Eliminados:           ${(report.deleted || 0).toLocaleString('es-VE')}` + nl;
    txt += `  🖼️  Imágenes subidas:     ${(session.imagesUploaded || 0).toLocaleString('es-VE')}` + nl;
    txt += `  ⏭️  Omitidos:             ${(session.skipped || 0).toLocaleString('es-VE')}` + nl;
    txt += `  ❌ Errores:              ${(session.errorDetails || []).length.toLocaleString('es-VE')}` + nl;
    txt += nl;

    txt += '━━━ PENDIENTES ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' + nl;
    txt += `  Total en JSON:            ${(session.totalInJson || 0).toLocaleString('es-VE')}` + nl;
    txt += `  Ya en WooCommerce:        ${stateSummary.totalEntradas.toLocaleString('es-VE')}` + nl;
    txt += `  Faltan por subir:         ${Math.max(0, (session.totalInJson || 0) - stateSummary.totalEntradas).toLocaleString('es-VE')}` + nl;
    txt += nl;

    // Errores
    const errores = session.errorDetails || [];
    txt += `━━━ ERRORES (${errores.length}) ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━` + nl;
    if (errores.length === 0) {
      txt += '  ✅ Ningún error registrado.' + nl;
    } else {
      for (const err of errores) {
        txt += `  [${err.sku}] ${err.error}` + nl;
      }
    }
    txt += nl;

    // Omitidos
    const omitidos = session.skippedDetails || [];
    txt += `━━━ OMITIDOS (${omitidos.length}) ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━` + nl;
    if (omitidos.length === 0) {
      txt += '  ✅ Ningún producto omitido.' + nl;
    } else {
      // Agrupar por razón
      const byReason = {};
      for (const s of omitidos) {
        const key = s.reason.substring(0, 60);
        if (!byReason[key]) byReason[key] = [];
        byReason[key].push(s.sku);
      }
      for (const [reason, skus] of Object.entries(byReason)) {
        txt += `  ${reason}: ${skus.length} productos` + nl;
        if (skus.length <= 10) {
          txt += `    SKUs: ${skus.join(', ')}` + nl;
        }
      }
    }
    txt += nl;

    // Creados en esta sesión (muestra los primeros 100)
    const creados = session.createdDetails || [];
    txt += `━━━ 🆕 CREADOS ESTA SESIÓN (${creados.length}) ━━━━━━━━━━━━━━━━━━━━━━━━━━━━` + nl;
    if (creados.length === 0) {
      txt += '  (ninguno creado en esta sesión)' + nl;
    } else {
      const simpleCreados = creados.filter(c => c.type === 'simple').length;
      const varCreados = creados.filter(c => c.type === 'variable').length;
      const variationCreados = creados.filter(c => c.type === 'variation').length;
      txt += `  Simples: ${simpleCreados}  |  Variables (padres): ${varCreados}  |  Variaciones: ${variationCreados}` + nl;
      txt += nl;
      const sample = creados.slice(0, 100);
      for (const c of sample) {
        txt += `  [${c.type[0].toUpperCase()}] SKU:${c.sku} → WooID:${c.wooId}  ${c.name}` + nl;
      }
      if (creados.length > 100) {
        txt += `  ... y ${creados.length - 100} más (ver archivo JSON para lista completa)` + nl;
      }
    }
    txt += nl;

    // Actualizados en esta sesión
    const actualizados = session.updatedDetails || [];
    txt += `━━━ ✏️  ACTUALIZADOS ESTA SESIÓN (${actualizados.length}) ━━━━━━━━━━━━━━━━━━━━` + nl;
    if (actualizados.length === 0) {
      txt += '  (ninguno actualizado en esta sesión)' + nl;
    } else {
      const sample = actualizados.slice(0, 200);
      for (const u of sample) {
        const tipo = u.type ? `[${u.type[0].toUpperCase()}]` : '[?]';
        const cambioStr = (u.cambios || []).map(c => {
          if (c.campo === 'precio') return `precio: ${c.de} → ${c.a}`;
          if (c.campo === 'precio_oferta') return `precio oferta: ${c.de} → ${c.a}`;
          if (c.campo === 'stock') return `stock: ${c.de} → ${c.a}`;
          if (c.campo === 'categorias') return `categorías: actualizadas`;
          if (c.campo === 'marca') return `marca: ${c.de} → ${c.a}`;
          if (c.campo === 'descripcion') return `descripción: actualizada`;
          return `${c.campo}: ${c.de} → ${c.a}`;
        }).join(' | ');
        txt += `  ${tipo} SKU:${u.sku}  ${u.name}` + nl;
        txt += `       ${cambioStr}` + nl;
      }
      if (actualizados.length > 200) {
        txt += `  ... y ${actualizados.length - 200} más (ver archivo JSON para lista completa)` + nl;
      }
    }
    txt += nl;

    // Pendientes de eliminación (detectados pero switch OFF)
    const pendingDel = session.pendingDeleteDetails || [];
    if (pendingDel.length > 0) {
      txt += `━━━ ⚠️  PENDIENTES DE ELIMINACIÓN (${pendingDel.length}) ━━━━━━━━━━━━━━━━━━━━` + nl;
      txt += `  Estos productos NO están en el JSON pero SÍ están en WooCommerce.` + nl;
      txt += `  Activa "Eliminar ausentes" en el próximo sync para eliminarlos.` + nl;
      txt += nl;
      const sample = pendingDel.slice(0, 100);
      for (const d of sample) {
        txt += `  [${(d.tipo || d.type || '?')[0].toUpperCase()}] SKU:${d.sku}  WooID:${d.wooId}` + nl;
      }
      if (pendingDel.length > 100) {
        txt += `  ... y ${pendingDel.length - 100} más (ver archivo JSON para lista completa)` + nl;
      }
      txt += nl;
    }

    // Eliminados en esta sesión
    const eliminados = session.deletedDetails || [];
    txt += `━━━ 🗑️  ELIMINADOS ESTA SESIÓN (${eliminados.length}) ━━━━━━━━━━━━━━━━━━━━━━━` + nl;
    if (eliminados.length === 0) {
      txt += '  (ninguno eliminado en esta sesión)' + nl;
    } else {
      const sample = eliminados.slice(0, 100);
      for (const d of sample) {
        txt += `  [${(d.type || '?')[0].toUpperCase()}] SKU:${d.sku}  WooID:${d.wooId}  ${d.name}` + nl;
      }
      if (eliminados.length > 100) {
        txt += `  ... y ${eliminados.length - 100} más (ver archivo JSON para lista completa)` + nl;
      }
    }
    txt += nl;

    txt += '══════════════════════════════════════════════════════════════════' + nl;
    txt += `  Reporte completo (JSON): ${reportFilePath}` + nl;
    txt += '══════════════════════════════════════════════════════════════════' + nl;

    fs.writeFileSync(txtFilePath, txt, 'utf8');

    return { jsonPath: reportFilePath, txtPath: txtFilePath };
  } catch (e) {
    console.error('[sync-engine] Error generando reporte:', e.message);
    return { jsonPath: null, txtPath: null };
  }
}

/**
 * Ejecuta la sincronización completa.
 * @param {Object} options - { discountPercent, syncPrecio, syncStock, syncCategorias, syncMarca, syncDescripcion, imagesEnabled }
 * @param {Function} progressCallback - Función para reportar progreso a la UI
 * @returns {Object} Resultado de la sincronización
 */
async function runSync(options = {}, progressCallback) {
  const startTime = new Date();
  const {
    discountPercent = 30,
    imagesEnabled = false,
    syncPrecio = true,
    syncStock = true,
    syncCategorias = true,
    syncMarca = false,
    syncDescripcion = false,
    syncDelete = false,    // default FALSE — protege contra borrados accidentales
    forceDelete = false     // si true, omite el límite de seguridad del 40%
  } = options;
  const sessionId = startTime.toISOString().replace(/[:.]/g, '-');

  const report = {
    success: true,
    created: 0,
    updated: 0,
    deleted: 0,
    imagesUploaded: 0,
    errors: [],
    skipped: 0,
    duration: 0,
    summary: '',
    reportPath: null
  };

  try {
    const LOCAL_JSON_PATH = getLocalJsonPath();
    // ─── Paso 1: Leer JSON local ───
    if (progressCallback) progressCallback('📂 Leyendo archivo de productos local...');

    if (!fs.existsSync(LOCAL_JSON_PATH)) {
      throw new Error(`No se encontró el archivo: ${LOCAL_JSON_PATH}`);
    }

    let rawContent = fs.readFileSync(LOCAL_JSON_PATH, 'utf8');
    // Eliminar BOM (Byte Order Mark \uFEFF) si existe — Windows puede agregarlo
    // y rompe JSON.parse en la app empaquetada con error "Unexpected token ''"
    if (rawContent.charCodeAt(0) === 0xFEFF) rawContent = rawContent.slice(1);
    let localProducts = JSON.parse(rawContent);

    // Soporte para JSON con wrapper: { articulos: [...] } o { products: [...] }
    if (!Array.isArray(localProducts)) {
      if (localProducts && Array.isArray(localProducts.articulos)) {
        if (progressCallback) progressCallback(`📂 JSON con wrapper detectado (clave "articulos"): ${localProducts.articulos.length} artículos`);
        localProducts = localProducts.articulos;
      } else if (localProducts && Array.isArray(localProducts.products)) {
        if (progressCallback) progressCallback(`📂 JSON con wrapper detectado (clave "products"): ${localProducts.products.length} productos`);
        localProducts = localProducts.products;
      } else {
        throw new Error('El archivo debe contener un array de productos, o un objeto con clave "articulos" o "products"');
      }
    }

    if (progressCallback) progressCallback(`📂 ${localProducts.length} productos leídos del archivo local`);

    // ─── Paso 2: Cargar estado ───
    if (progressCallback) progressCallback('💾 Cargando estado de WooCommerce...');
    const state = wooState.loadState();
    const productCount = Object.keys(state.products).length;
    if (progressCallback) progressCallback(`💾 Estado cargado: ${productCount} productos en registro`);

    // Inicializar sesión
    wooState.initSession(state, sessionId, localProducts.length);

    // ─── Paso 3: Diff ───
    if (progressCallback) progressCallback('🔍 Comparando productos locales vs WooCommerce...');


    const diff = computeDiff(localProducts, state, {
      discountPercent,
      syncPrecio,
      syncStock,
      syncCategorias,
      syncMarca,
      syncDescripcion,
      imagesEnabled
    });

    const summary = formatDiffSummary(diff);
    if (progressCallback) progressCallback(summary);

    report.skipped = diff.stats.skipped;

    // Registrar omitidos en sesión
    for (const s of diff.skipped) {
      wooState.addSessionSkipped(state, s.sku, s.reason);
    }

    // Si no hay nada que hacer, terminar temprano
    if (diff.stats.new === 0 && diff.stats.modified === 0 && diff.stats.deleted === 0 && diff.stats.images === 0) {
      if (progressCallback) progressCallback('✅ No hay cambios pendientes. Todo está sincronizado.');
      report.summary = 'Sin cambios pendientes.';
      report.duration = ((new Date() - startTime) / 1000).toFixed(2);
      const paths = generateSyncReport(state, report, sessionId, startTime, options);
      if (paths.txtPath && progressCallback) progressCallback(`\n📄 Reporte guardado en: ${paths.txtPath}`);
      report.reportPath = paths.txtPath;
      state.currentSession = null;  // sync terminó limpiamente
      wooState.saveState(state);
      return report;
    }

    // ─── Paso 4: Cargar categorías y marcas de WooCommerce ───
    if (diff.stats.new > 0 || diff.toUpdate.some(u => u.changes.some(c => c.field === 'categorias'))) {
      await catManager.loadAllCategories(progressCallback);
    }
    if (diff.stats.new > 0 || diff.toUpdate.some(u => u.changes.some(c => c.field === 'marca'))) {
      await brandManager.loadAllBrands(progressCallback);
    }

    // ─── Paso 5: Crear productos nuevos ───
    if (diff.toCreate.length > 0) {
      if (progressCallback) progressCallback(`\n🆕 Creando ${diff.toCreate.length} productos nuevos...`);

      // Separar variaciones de simples y padres explícitos
      const simples = diff.toCreate.filter(c => !c.isVariation && !c.isExplicitParent);
      const variations = diff.toCreate.filter(c => c.isVariation);
      const explicitParentMap = new Map(
        diff.toCreate
          .filter(c => c.isExplicitParent)
          .map(c => [c.sku, c])
      );

      // Agrupar variaciones por codigo_padre (normalizado para consistencia con SKUs)
      const varGroups = new Map();
      for (const v of variations) {
        const parent = normalizeSku(v.product.codigo_padre);
        if (!varGroups.has(parent)) varGroups.set(parent, []);
        varGroups.get(parent).push(v);
      }

      // 5a. Crear productos variables (padres) y sus variaciones
      for (const [codigoPadre, children] of varGroups) {
        if (progressCallback) progressCallback(`📦 Creando producto variable: ${codigoPadre} (${children.length} variaciones)`);

        try {
          const attrName = children[0].product.atributo;
          const attrOptions = children.map(c => c.product.termino);

          const explicitParent = explicitParentMap.get(codigoPadre);
          const sourceProduct = explicitParent ? explicitParent.product : children[0].product;

          const prices = calculatePrices(sourceProduct, discountPercent);
          const categories = await catManager.resolveCategories(sourceProduct.categories);
          const brands = await brandManager.resolveBrand(sourceProduct.marca);

          const parentName = explicitParent
            ? (sourceProduct.descripcion || '').trim()
            : _cleanProductName(children[0].product.descripcion, children[0].product.termino);

          const parentPayload = {
            name: parentName,
            type: 'variable',
            sku: codigoPadre,
            description: '',
            short_description: sourceProduct.descripcion_ampliada || '',
            categories: categories,
            ...(brands.length > 0 && { brands }),
            attributes: [{
              name: attrName,
              visible: true,
              variation: true,
              options: attrOptions
            }],
            manage_stock: false
          };

          const parentResult = await wooApi.createSingleProduct(parentPayload);

          if (parentResult && parentResult.id) {
            const parentWooId = parentResult.id;
            const sourceProduct = explicitParentMap.get(codigoPadre)?.product || children[0].product;
            // ★ CRÍTICO: guardar en AMBOS lugares
            // 1) variableParents: para tracking de hijos
            wooState.updateVariableParent(state, codigoPadre, parentWooId, children.map(c => c.sku));
            // 2) products: para que el diff lo reconozca en la próxima sync (evita recrearlo)
            wooState.updateProduct(state, codigoPadre, {
              wooId: parentWooId,
              type: 'variable',
              precio1: sourceProduct.precio1 || 0,
              precio2: sourceProduct.precio2 || 0,
              existencia: 0,
              categories: sourceProduct.categories || '',
              marca: sourceProduct.marca || '',
              hasImage: false,
              parentWooId: null
            });
            report.created++;
            wooState.addSessionCreated(state, codigoPadre, parentWooId, 'variable', parentName);

            if (progressCallback) progressCallback(`  ✅ Padre "${parentPayload.name}" (SKU: ${codigoPadre}) → WooID ${parentWooId}`);


            // Crear cada variación
            for (const child of children) {
              try {
                const childPrices = calculatePrices(child.product, discountPercent);
                const varPayload = {
                  sku: child.sku,
                  regular_price: String(childPrices.regular_price),
                  sale_price: childPrices.sale_price ? String(childPrices.sale_price) : '',
                  manage_stock: true,
                  stock_quantity: Math.max(0, parseInt(child.product.existencia) || 0),
                  attributes: [{
                    name: attrName,
                    option: child.product.termino
                  }]
                };

                if (!childPrices.isSpecial && Object.keys(childPrices.percentage_rules).length > 0) {
                  varPayload.meta_data = [
                    { key: '_tiered_price_rules_type', value: 'percentage' },
                    { key: '_percentage_price_rules', value: childPrices.percentage_rules }
                  ];
                }

                const varResult = await wooApi.createVariation(parentWooId, varPayload);

                if (varResult && varResult.id) {
                  const childPrices2 = calculatePrices(child.product, discountPercent);
                  wooState.updateProduct(state, child.sku, {
                    wooId: varResult.id,
                    type: 'variation',
                    precio1: child.product.precio1,
                    precio2: child.product.precio2,
                    sale_price: childPrices2.sale_price != null ? childPrices2.sale_price : 0,
                    existencia: child.product.existencia,
                    categories: child.product.categories,
                    hasImage: false,
                    parentWooId: parentWooId
                  });
                  report.created++;
                  wooState.addSessionCreated(state, child.sku, varResult.id, 'variation', child.product.termino);
                }

                await wooApi.rateLimitedPause();
              } catch (ve) {
                const errMsg = `Error creando variación: ${ve.message}`;
                report.errors.push({ sku: child.sku, error: errMsg });
                wooState.addSessionError(state, child.sku, errMsg);
              }
            }

            // ★ GUARDAR STATE INCREMENTAL después de cada producto variable completo
            wooState.saveStateIncremental(state);

          }
        } catch (pe) {
          const errMsg = `Error creando padre variable: ${pe.message}`;
          report.errors.push({ sku: codigoPadre, error: errMsg });
          wooState.addSessionError(state, codigoPadre, errMsg);
        }
      }

      // 5b. Crear productos simples en batch
      if (simples.length > 0) {
        if (progressCallback) progressCallback(`📦 Creando ${simples.length} productos simples en lotes de ${wooApi.BATCH_SIZE}...`);

        const simplePayloads = [];
        for (const item of simples) {
          const p = item.product;
          const prices = calculatePrices(p, discountPercent);
          const categories = await catManager.resolveCategories(p.categories);
          const brands = await brandManager.resolveBrand(p.marca);

          const payload = {
            name: (p.descripcion || '').trim(),
            type: 'simple',
            sku: item.sku,
            regular_price: String(prices.regular_price),
            sale_price: prices.sale_price ? String(prices.sale_price) : '',
            description: '',
            short_description: p.descripcion_ampliada || '',
            manage_stock: true,
            stock_quantity: Math.max(0, parseInt(p.existencia) || 0),
            categories: categories,
            ...(brands.length > 0 && { brands })
          };

          if (!prices.isSpecial && Object.keys(prices.percentage_rules).length > 0) {
            payload.meta_data = [
              { key: '_tiered_price_rules_type', value: 'percentage' },
              { key: '_percentage_price_rules', value: prices.percentage_rules }
            ];
          }

          simplePayloads.push(payload);
        }

        // Procesar en lotes con guardado incremental por lote
        const BATCH = wooApi.BATCH_SIZE;
        let totalCreatedSimples = 0;

        for (let i = 0; i < simplePayloads.length; i += BATCH) {
          const chunk = simplePayloads.slice(i, i + BATCH);
          const chunkSkus = simples.slice(i, i + BATCH);
          const batchNum = Math.floor(i / BATCH) + 1;
          const totalBatches = Math.ceil(simplePayloads.length / BATCH);

          if (progressCallback) progressCallback(`\n🆕 Lote simples ${batchNum}/${totalBatches} (${chunk.length} productos)...`);

          try {
            const createResult = await wooApi.batchCreateProducts([...chunk], progressCallback);

            // Construir mapa de SKU → item original para lookup O(1)
            // Normalizar SKU a string trimmed para evitar problemas de tipo (number vs string)
            // especialmente con SKUs numéricos largos (EAN-13, etc.)
            const skuMap = new Map();
            for (const item of chunkSkus) {
              skuMap.set(String(item.sku).trim(), item);
            }

            // Actualizar estado con los productos creados
            for (const created of createResult.created) {
              const normalizedSku = String(created.sku || '').trim();
              const originalItem = skuMap.get(normalizedSku);
              const createdPrices = originalItem
                ? calculatePrices(originalItem.product, discountPercent)
                : { sale_price: 0 };

              // Guardar siempre con el SKU normalizado que devuelve WooCommerce
              wooState.updateProduct(state, normalizedSku, {
                wooId: created.id,
                type: 'simple',
                precio1: originalItem?.product?.precio1 || 0,
                precio2: originalItem?.product?.precio2 || 0,
                sale_price: createdPrices.sale_price != null ? createdPrices.sale_price : 0,
                existencia: originalItem?.product?.existencia || 0,
                categories: originalItem?.product?.categories || '',
                marca: originalItem?.product?.marca || '',
                hasImage: false,
                parentWooId: null
              });
              report.created++;
              totalCreatedSimples++;
              wooState.addSessionCreated(state, normalizedSku, created.id, 'simple', created.name);
            }

            for (const err of createResult.errors) {
              // AUTO-RECUPERACIÓN: si el SKU ya existe en WooCommerce (desync de state),
              // buscarlo y registrarlo para que el próximo sync no intente recrearlo.
              const errMsg = (err.error || '').toLowerCase();
              const isDuplicateSku = err.sku && err.sku !== '?' && (
                errMsg.includes('tabla de búsqueda') ||
                errMsg.includes('sku_unique') ||
                errMsg.includes('invalid or duplicate')
              );

              if (isDuplicateSku && err.sku && err.sku !== '?') {
                try {
                  const wooProduct = await wooApi.getProductBySku(err.sku);
                  if (wooProduct && wooProduct.id) {
                    const originalItem = skuMap.get(String(err.sku).trim());
                    wooState.updateProduct(state, String(err.sku).trim(), {
                      wooId: wooProduct.id,
                      type: wooProduct.parent_id > 0 ? 'variation' : (wooProduct.type || 'simple'),
                      precio1: originalItem?.product?.precio1 || parseFloat(wooProduct.regular_price) || 0,
                      precio2: originalItem?.product?.precio2 || 0,
                      existencia: originalItem?.product?.existencia || parseInt(wooProduct.stock_quantity) || 0,
                      categories: originalItem?.product?.categories || '',
                      marca: originalItem?.product?.marca || '',
                      hasImage: Array.isArray(wooProduct.images) ? wooProduct.images.length > 0 : !!(wooProduct.image && wooProduct.image.id),
                      parentWooId: wooProduct.parent_id > 0 ? wooProduct.parent_id : null
                    });
                    if (progressCallback) progressCallback(`  🔧 SKU ${err.sku} ya existía en WooCommerce (ID ${wooProduct.id}) → registrado en state automáticamente`);
                    // No registrar como error — el producto ya existe y está ahora en state
                    continue;
                  }
                } catch (_) { /* fallo silencioso — se registra como error normal */ }
              }

              report.errors.push({ sku: err.sku, error: err.error });
              wooState.addSessionError(state, err.sku, err.error);
            }

            // ★ GUARDAR STATE INCREMENTAL después de cada lote
            wooState.saveStateIncremental(state);

            if (progressCallback) {
              const total = Object.keys(state.products).length;
              progressCallback(`  💾 State guardado: ${total} productos registrados en total`);
            }

          } catch (batchErr) {
            const errMsg = `Error fatal en lote ${batchNum}: ${batchErr.message}`;
            if (progressCallback) progressCallback(`❌ ${errMsg}`);
            for (const item of chunkSkus) {
              report.errors.push({ sku: item.sku, error: errMsg });
              wooState.addSessionError(state, item.sku, errMsg);
            }
            // Guardar state aunque haya error para no perder lo anterior
            wooState.saveStateIncremental(state);
          }
        }

        if (progressCallback) progressCallback(`✅ ${totalCreatedSimples} simples creados en esta sesión`);
      }
    }

    // ─── Paso 6: Actualizar productos modificados ───
    if (diff.toUpdate.length > 0) {
      if (progressCallback) progressCallback(`\n✏️ Actualizando ${diff.toUpdate.length} productos...`);

      const normalUpdates = diff.toUpdate.filter(u => !u.isVariation);
      const variationUpdates = diff.toUpdate.filter(u => u.isVariation);

      // 6a. Productos normales en batch
      if (normalUpdates.length > 0) {
        const updatePayloads = [];

        for (const u of normalUpdates) {
          const payload = { id: u.existing.wooId };
          const prices = calculatePrices(u.product, discountPercent);
          let hasChanges = false;

          for (const change of u.changes) {
            switch (change.field) {
              case 'precio':
                payload.regular_price = String(prices.regular_price);
                payload.sale_price = prices.sale_price ? String(prices.sale_price) : '';
                if (Object.keys(prices.percentage_rules).length > 0) {
                  payload.meta_data = [
                    { key: '_tiered_price_rules_type', value: 'percentage' },
                    { key: '_percentage_price_rules', value: prices.percentage_rules }
                  ];
                }
                hasChanges = true;
                break;
              case 'sale_price':
                // Actualizar solo el sale_price (sin tocar regular_price a menos que ya esté en el payload)
                if (!payload.regular_price) {
                  payload.regular_price = String(prices.regular_price);
                }
                payload.sale_price = prices.sale_price ? String(prices.sale_price) : '';
                hasChanges = true;
                break;
              case 'stock':
                payload.manage_stock = true;
                payload.stock_quantity = Math.max(0, parseInt(u.product.existencia) || 0);
                hasChanges = true;
                break;
              case 'categorias':
                const cats = await catManager.resolveCategories(u.product.categories);
                if (cats.length > 0) payload.categories = cats;
                hasChanges = true;
                break;
              case 'marca': {
                const brandArr = await brandManager.resolveBrand(u.product.marca);
                if (brandArr.length > 0) payload.brands = brandArr;
                hasChanges = true;
                break;
              }
              case 'descripcion':
                // change.value contiene el texto real de la descripción ampliada
                payload.short_description = change.value || '';
                hasChanges = true;
                break;
            }
          }

          if (hasChanges) {
            updatePayloads.push(payload);
          }
        }

        if (updatePayloads.length > 0) {
          // Construir mapa ID → SKU para poder encontrar el SKU cuando falla por ID
          const idToSkuMap = new Map();
          for (const u of normalUpdates) {
            if (u.existing && u.existing.wooId) idToSkuMap.set(u.existing.wooId, u.sku);
          }

          // Log diagnóstico: primer payload con sale_price para verificar
          const salePriceSample = updatePayloads.find(p => p.sale_price != null && p.sale_price !== '');
          if (salePriceSample && progressCallback) {
            progressCallback(`💲 Ejemplo payload sale_price: ID=${salePriceSample.id} regular_price=${salePriceSample.regular_price} sale_price=${salePriceSample.sale_price}`);
          }

          const updateResult = await wooApi.batchUpdateProducts(updatePayloads, progressCallback);
          report.updated += updateResult.updated;
          for (const err of updateResult.errors) {
            const errMsg = (err.error || '').toLowerCase();
            const isInvalidId = errMsg.includes('id no válido') ||
              errMsg.includes('invalid id') ||
              errMsg.includes('invalid_id');

            if (isInvalidId && err.id) {
              // El producto fue eliminado manualmente en WooCommerce.
              // Eliminar del state para que el próximo sync lo recree como nuevo.
              const skuToReset = idToSkuMap.get(err.id);
              if (skuToReset) {
                wooState.removeProduct(state, skuToReset);
                if (progressCallback) progressCallback(`  🔄 WooID ${err.id} (SKU: ${skuToReset}) eliminado de WooCommerce externamente → removido del state para recrearlo en el próximo sync`);
                // No registrar como error — se resolverá en la próxima sincronización
                continue;
              }
            }

            report.errors.push({ sku: `ID:${err.id}`, error: err.error });
            wooState.addSessionError(state, `ID:${err.id}`, err.error);
          }
        }

        for (const u of normalUpdates) {
          const prices = calculatePrices(u.product, discountPercent);
          wooState.updateProduct(state, u.sku, {
            precio1: u.product.precio1,
            precio2: u.product.precio2,
            existencia: u.product.existencia,
            categories: u.product.categories,
            marca: u.product.marca || '',
            sale_price: prices.sale_price != null ? prices.sale_price : 0,
            // Actualizar hash de descripción si fue enviada
            ...(u.changes.some(c => c.field === 'descripcion') && {
              descripcionHash: u.changes.find(c => c.field === 'descripcion').new
            })
          });
          // Registrar en sesión con detalle de cada campo cambiado
          const cambios = u.changes.map(ch => {
            if (ch.field === 'precio') {
              return { campo: 'precio', de: ch.old, a: ch.new };
            }
            if (ch.field === 'sale_price') {
              return { campo: 'precio_oferta', de: ch.old, a: ch.new };
            }
            if (ch.field === 'stock') {
              return { campo: 'stock', de: ch.old, a: ch.new };
            }
            if (ch.field === 'categorias') {
              return { campo: 'categorias', de: ch.old || '', a: u.product.categories || '' };
            }
            if (ch.field === 'marca') {
              return { campo: 'marca', de: ch.old || '', a: u.product.marca || '' };
            }
            if (ch.field === 'descripcion') {
              return { campo: 'descripcion', de: '(anterior)', a: '(actualizada)' };
            }
            return { campo: ch.field, de: ch.old, a: ch.new };
          });
          wooState.addSessionUpdated(state, u.sku, u.existing.wooId, u.existing.type || 'simple',
            u.product.descripcion || '', cambios);
        }

        wooState.saveStateIncremental(state);
      }

      // 6b. Variaciones por parentId
      if (variationUpdates.length > 0) {
        const byParent = new Map();
        for (const v of variationUpdates) {
          const pid = v.existing.parentWooId;
          if (!byParent.has(pid)) byParent.set(pid, []);
          byParent.get(pid).push(v);
        }

        for (const [parentId, vars] of byParent) {
          const varPayloads = vars.map(v => {
            const prices = calculatePrices(v.product, discountPercent);
            const payload = { id: v.existing.wooId };
            for (const change of v.changes) {
              if (change.field === 'precio') {
                payload.regular_price = String(prices.regular_price);
                payload.sale_price = prices.sale_price ? String(prices.sale_price) : '';
              }
              if (change.field === 'stock') {
                payload.manage_stock = true;
                payload.stock_quantity = Math.max(0, parseInt(v.product.existencia) || 0);
              }
              if (change.field === 'sale_price') {
                payload.sale_price = String(change.new);
              }
            }
            return payload;
          });

          if (progressCallback) progressCallback(`✏️ Actualizando ${varPayloads.length} variaciones del padre ${parentId}...`);

          const varResult = await wooApi.batchUpdateVariations(parentId, varPayloads, progressCallback);
          report.updated += varResult.updated;

          for (const v of vars) {
            const prices = calculatePrices(v.product, discountPercent);
            wooState.updateProduct(state, v.sku, {
              precio1: v.product.precio1,
              precio2: v.product.precio2,
              existencia: v.product.existencia,
              categories: v.product.categories,
              marca: v.product.marca || '',
              sale_price: prices.sale_price != null ? prices.sale_price : 0
            });
            // Registrar variaciones actualizadas en sesión
            const cambiosVar = v.changes.map(ch => {
              if (ch.field === 'precio') return { campo: 'precio', de: ch.old, a: ch.new };
              if (ch.field === 'sale_price') return { campo: 'precio_oferta', de: ch.old, a: ch.new };
              if (ch.field === 'stock') return { campo: 'stock', de: ch.old, a: ch.new };
              return { campo: ch.field, de: ch.old, a: ch.new };
            });
            wooState.addSessionUpdated(state, v.sku, v.existing.wooId, 'variation',
              v.product.descripcion || '', cambiosVar);
          }

          wooState.saveStateIncremental(state);
        }
      }
    }

    // ─── Paso 7: Eliminar productos ───
    if (diff.toDelete.length > 0) {
      const totalInState = Object.keys(state.products).length;
      const deleteCount = diff.toDelete.length;
      const deletePct = totalInState > 0 ? (deleteCount / totalInState) * 100 : 0;

      // Siempre informar cuántos serían eliminados
      if (progressCallback) progressCallback(
        `\n🗑️ Detectados ${deleteCount} productos ausentes del JSON (${deletePct.toFixed(1)}% del total en WooCommerce)`
      );

      if (!syncDelete) {
        // Switch apagado — no eliminar, solo informar y registrar como pendientes
        if (progressCallback) progressCallback(
          `⏭️ Eliminación omitida (switch "Eliminar ausentes" está OFF). Activa el switch si deseas eliminarlos.`
        );
        for (const d of diff.toDelete) {
          wooState.addSessionPendingDelete(state, d.sku, d.wooId, d.type || 'simple');
        }
      } else if (deletePct > 40 && !forceDelete) {
        // UMBRAL DE SEGURIDAD: más del 40% — bloquear, a menos que forceDelete esté activo
        const bloqueMsg = [
          `🚨 ELIMINACIÓN BLOQUEADA POR SEGURIDAD`,
          `   Se intentarían eliminar ${deleteCount} productos (${deletePct.toFixed(1)}% del total).`,
          `   El límite de seguridad es 40%. Esto sugiere que el JSON cargado está incompleto.`,
          `   Activa "⚡ Forzar (sin límite del 40%)" si realmente quieres proceder.`
        ].join('\n');
        if (progressCallback) progressCallback(bloqueMsg);
        report.errors.push({ sku: 'BLOQUEO_SEGURIDAD', error: `Eliminación bloqueada: ${deletePct.toFixed(1)}% supera umbral 40%` });
      } else {
        // Eliminación autorizada
        if (progressCallback) progressCallback(`\n🗑️ Eliminando ${deleteCount} productos...`);

        const normalDeletes = diff.toDelete.filter(d => d.type !== 'variation');
        const varDeletes = diff.toDelete.filter(d => d.type === 'variation');

        if (normalDeletes.length > 0) {
          const ids = normalDeletes.map(d => d.wooId).filter(Boolean);
          if (ids.length > 0) {
            const delResult = await wooApi.batchDeleteProducts(ids, progressCallback);
            report.deleted += delResult.deleted;
          }
        }

        if (varDeletes.length > 0) {
          const byParent = new Map();
          for (const v of varDeletes) {
            const pid = v.parentWooId;
            if (pid) {
              if (!byParent.has(pid)) byParent.set(pid, []);
              byParent.get(pid).push(v.wooId);
            }
          }
          for (const [parentId, ids] of byParent) {
            try {
              await wooApi.deleteVariations(parentId, ids);
              report.deleted += ids.length;
            } catch (e) {
              report.errors.push({ sku: `Parent:${parentId}`, error: `Error eliminando variaciones: ${e.message}` });
            }
          }
        }

        for (const d of diff.toDelete) {
          wooState.removeProduct(state, d.sku);
          wooState.addSessionDeleted(state, d.sku, d.wooId, d.type || 'simple', d.name || '');
        }

        wooState.saveStateIncremental(state);
      }
    } else if (syncDelete) {
      if (progressCallback) progressCallback(`✅ Sin productos a eliminar — todos los del state están en el JSON.`);
    }

    // ─── Paso 8: Subir imágenes ───
    if (imagesEnabled && diff.toUploadImage.length > 0) {
      if (progressCallback) progressCallback(`\n🖼️ Subiendo ${diff.toUploadImage.length} imágenes...`);

      const imageItems = diff.toUploadImage.map(item => {
        const stateProduct = state.products[item.sku];
        // Preservar el tipo exacto: 'variation', 'variable', o 'simple'
        // Antes se colapsaba todo lo que no era 'variation' a 'simple', perdiendo los padres variables
        const productType = stateProduct?.type || 'simple'; // 'simple' | 'variable' | 'variation'
        return {
          sku: item.sku,
          product: item.product,
          wooId: stateProduct ? stateProduct.wooId : item.wooId,
          type: productType,
          parentWooId: stateProduct?.parentWooId || null
        };
      }).filter(item => item.wooId);


      const imgResult = await uploadImages(imageItems, progressCallback);
      report.imagesUploaded = imgResult.uploaded;

      for (const detail of imgResult.details) {
        if (detail.status === 'ok') {
          const sp = state.products[detail.sku];
          if (sp) {
            sp.hasImage = true;
            wooState.addSessionImage(state, detail.sku);
          }
        }
      }

      // Aplicar correcciones de wooId al state (cuando el fallback por SKU corrigió IDs)
      if (imgResult.wooIdCorrections && imgResult.wooIdCorrections.length > 0) {
        for (const correction of imgResult.wooIdCorrections) {
          const sp = state.products[correction.sku];
          if (sp) {
            sp.wooId = correction.newId;
            if (progressCallback) {
              progressCallback(`  💾 State corregido: ${correction.sku} → wooId ${correction.oldId} → ${correction.newId}`);
            }
          }
        }
      }

      // Actualizar contador de imágenes en sesión
      if (state.currentSession) {
        state.currentSession.imagesUploaded = imgResult.uploaded;
      }

      wooState.saveStateIncremental(state);
    }


    // ─── Paso 9: Guardar estado final ───
    if (progressCallback) progressCallback('\n💾 Guardando estado final...');

    const catExport = catManager.exportCacheForState();
    for (const [name, id] of Object.entries(catExport)) {
      wooState.updateCategory(state, name, id);
    }

    // ── Migración automática: padres variables de variableParents → products ──
    // Los padres creados con la versión anterior solo existen en state.variableParents.
    // Los movemos a state.products para que el diff los reconozca correctamente.
    const varParentsToMigrate = state.variableParents || {};
    let migrated = 0;
    for (const [sku, vp] of Object.entries(varParentsToMigrate)) {
      if (!state.products[sku] && vp.wooId) {
        state.products[sku] = {
          wooId: vp.wooId,
          type: 'variable',
          precio1: 0,
          precio2: 0,
          existencia: 0,
          categories: '',
          marca: '',
          hasImage: false,
          parentWooId: null
        };
        migrated++;
      }
    }
    if (migrated > 0 && progressCallback) {
      progressCallback(`🔄 Migración: ${migrated} padres variables movidos a state.products`);
    }

    // Limpiar sesión antes de guardar — si llegamos aquí, la sync terminó correctamente
    // (si el proceso muere antes de este punto, currentSession quedará para detectar la interrupción)
    // IMPORTANTE: guardar snapshot ANTES de limpiar — generateSyncReport lo necesita para el reporte
    const sessionSnapshot = state.currentSession ? { ...state.currentSession } : null;
    state.currentSession = null;
    wooState.saveState(state);
    if (progressCallback) progressCallback(`💾 Estado guardado: ${Object.keys(state.products).length} productos registrados`);


    // ─── Paso 10: Generar reporte de sesión ───
    const stateSummary = wooState.getStateSummary(state);
    const duration = ((new Date() - startTime) / 1000).toFixed(2);
    report.duration = duration;

    const { getConfig } = require('./env-config');
    const envConfig = getConfig();

    // Usar el snapshot de la sesión para el reporte (state.currentSession ya es null)
    const stateForReport = { ...state, currentSession: sessionSnapshot };
    const reportPaths = generateSyncReport(stateForReport, report, sessionId, startTime, {
      ...options,
      serverUrl: envConfig.WP_API_URL,
      testMode: envConfig.testMode
    });

    report.reportPath = reportPaths.txtPath;

    // ─── Paso 11: Resumen final en consola/UI ───
    let finalSummary = '\n══════════════════════════════════════\n';
    finalSummary += '  TOTALES EN WOOCOMMERCE\n';
    finalSummary += '══════════════════════════════════════\n';
    finalSummary += `📦 Simples:              ${stateSummary.simples.toLocaleString('es-VE')}\n`;
    finalSummary += `🔀 Variables (padres):   ${stateSummary.variables.toLocaleString('es-VE')}\n`;
    finalSummary += `🎛️  Variaciones:          ${stateSummary.variations.toLocaleString('es-VE')}\n`;
    finalSummary += `──────────────────────────────────────\n`;
    finalSummary += `🛒 TOTAL VISIBLE EN WC:  ${stateSummary.totalEnWooCommerce.toLocaleString('es-VE')}\n`;
    finalSummary += `🖼️  Con imagen:           ${stateSummary.conImagen.toLocaleString('es-VE')}\n`;
    finalSummary += '\n══════════════════════════════════════\n';
    finalSummary += '  ESTA SESIÓN\n';
    finalSummary += '══════════════════════════════════════\n';
    finalSummary += `⏱️  Tiempo: ${(duration / 60).toFixed(1)} min\n`;
    finalSummary += `🆕 Creados: ${report.created.toLocaleString('es-VE')}\n`;
    finalSummary += `✏️  Actualizados: ${report.updated.toLocaleString('es-VE')}\n`;
    finalSummary += `🗑️  Eliminados: ${report.deleted.toLocaleString('es-VE')}\n`;
    finalSummary += `🖼️  Imágenes: ${report.imagesUploaded.toLocaleString('es-VE')}\n`;
    finalSummary += `⏭️  Omitidos: ${report.skipped.toLocaleString('es-VE')}\n`;
    finalSummary += `❌ Errores: ${report.errors.length.toLocaleString('es-VE')}\n`;

    const pendientes = Math.max(0, localProducts.length - Object.keys(state.products).length);
    if (pendientes > 0) {
      finalSummary += `\n⚠️  Pendientes por subir: ${pendientes.toLocaleString('es-VE')} (ejecutar sync nuevamente)\n`;
    }

    if (reportPaths.txtPath) {
      finalSummary += `\n📄 Reporte detallado: ${reportPaths.txtPath}\n`;
    }

    report.summary = finalSummary;
    if (progressCallback) progressCallback(finalSummary);

    return report;

  } catch (error) {
    report.success = false;
    report.summary = `Error fatal: ${error.message}`;
    report.duration = ((new Date() - startTime) / 1000).toFixed(2);
    if (progressCallback) progressCallback(`❌ Error fatal: ${error.message}`);
    console.error('[sync-engine]', error);
    return report;
  }
}

function _cleanProductName(descripcion, termino) {
  let name = (descripcion || '').trim();
  if (termino) {
    const term = termino.trim();
    const regex = new RegExp(`\\s*${_escapeRegex(term)}\\s*$`, 'i');
    name = name.replace(regex, '').trim();
  }
  return name || descripcion;
}

function _escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

module.exports = { runSync };
