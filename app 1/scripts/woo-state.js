/**
 * woo-state.js — Estado local persistente (espejo de WooCommerce)
 * 
 * Mantiene un archivo woo-state.json que refleja lo que hay en WooCommerce,
 * evitando tener que descargar miles de productos en cada sincronización.
 */
const fs = require('fs');
const path = require('path');

// Resolver ruta base según entorno — LAZY para evitar que app.getPath() se llame
// antes de que Electron inicialice completamente (problema con asar: true)
function getBaseDir() {
  if (process.versions.electron) {
    const { app } = require('electron');
    return app.isPackaged ? app.getPath('userData') : path.join(__dirname, '..');
  }
  return path.join(__dirname, '..');
}

function getStatePath() { return path.join(getBaseDir(), 'uploads', 'woo-state.json'); }
function getBackupDir() { return path.join(getBaseDir(), 'uploads', 'state-backups'); }

const MAX_BACKUPS = 10;


/**
 * Estructura del estado:
 * {
 *   version: 2,
 *   lastSync: "ISO date",
 *   products: { "SKU": { wooId, type, precio1, precio2, existencia, categories, marca, hasImage, parentWooId, descripcionHash } },
 *   variableParents: { "CODIGO_PADRE": { wooId, children: ["SKU1", "SKU2"] } },
 *   categories: { "NOMBRE": wooId }
 * }
 */

function createEmptyState() {
  return {
    version: 2,
    lastSync: null,
    products: {},
    variableParents: {},
    categories: {},
    // Sesión actual de sync
    currentSession: null
  };
}

/**
 * Inicializa una nueva sesión de sync en el estado.
 * @param {Object} state
 * @param {string} sessionId  - Identificador único (ej: ISO timestamp)
 * @param {number} totalInJson - Total de productos en el JSON de entrada
 */
function initSession(state, sessionId, totalInJson) {
  state.currentSession = {
    id: sessionId,
    startedAt: new Date().toISOString(),
    totalInJson,
    created: 0,
    updated: 0,
    deleted: 0,
    imagesUploaded: 0,
    errors: [],
    skipped: 0,
    createdDetails: [],   // [{ sku, wooId, type, name }]
    updatedDetails: [],   // [{ sku, wooId, type, name, cambios: [{campo, de, a}] }]
    deletedDetails: [],   // [{ sku, wooId, type, name, razon }]
    pendingDeleteDetails: [], // [{ sku, wooId, type }] — detectados pero switch OFF
    errorDetails: [],     // [{ sku, error }]
    skippedDetails: []    // [{ sku, reason }]
  };
}

/**
 * Registra un evento en la sesión actual.
 */
function addSessionCreated(state, sku, wooId, type, name) {
  if (!state.currentSession) return;
  state.currentSession.created++;
  state.currentSession.createdDetails.push({ sku, wooId, type, name: (name || '').substring(0, 80) });
}

function addSessionError(state, sku, error) {
  if (!state.currentSession) return;
  state.currentSession.errors.push({ sku, error });
  state.currentSession.errorDetails.push({ sku, error });
}

function addSessionSkipped(state, sku, reason) {
  if (!state.currentSession) return;
  state.currentSession.skipped++;
  state.currentSession.skippedDetails.push({ sku, reason });
}

function addSessionImage(state, sku) {
  if (!state.currentSession) return;
  state.currentSession.imagesUploaded++;
}

/**
 * Registra un producto actualizado con detalle de cada campo modificado.
 * @param {Object} state
 * @param {string} sku
 * @param {number} wooId
 * @param {string} type  — 'simple' | 'variable' | 'variation'
 * @param {string} name  — descripción / nombre del producto
 * @param {Array}  cambios — [{ campo, de, a }]  (valores antes y después)
 */
function addSessionUpdated(state, sku, wooId, type, name, cambios) {
  if (!state.currentSession) return;
  state.currentSession.updated++;
  state.currentSession.updatedDetails.push({
    sku,
    wooId,
    type,
    name: (name || '').substring(0, 80),
    cambios: cambios || []
  });
}

/**
 * Registra un producto eliminado en esta sesión.
 * @param {Object} state
 * @param {string} sku
 * @param {number} wooId
 * @param {string} type
 * @param {string} name
 */
function addSessionDeleted(state, sku, wooId, type, name) {
  if (!state.currentSession) return;
  state.currentSession.deleted++;
  state.currentSession.deletedDetails.push({ sku, wooId, type, name: (name || '').substring(0, 80) });
}

/**
 * Registra un producto que SERÍA eliminado pero el switch de eliminación está OFF.
 * Aparecerá en el reporte como "pendiente de eliminación".
 */
function addSessionPendingDelete(state, sku, wooId, type) {
  if (!state.currentSession) return;
  if (!state.currentSession.pendingDeleteDetails) state.currentSession.pendingDeleteDetails = [];
  state.currentSession.pendingDeleteDetails.push({ sku, wooId, type });
}


function loadState() {
  const STATE_FILE = getStatePath();
  try {
    if (fs.existsSync(STATE_FILE)) {
      const raw = fs.readFileSync(STATE_FILE, 'utf8');
      const data = JSON.parse(raw);
      if (data && data.version === 2) {
        console.log(`[woo-state] State leído desde: ${STATE_FILE}`);
        return data;
      }
      // Migrar si es versión antigua
      console.warn('[woo-state] Versión antigua detectada, creando estado nuevo');
    }
  } catch (e) {
    console.error('[woo-state] Error leyendo estado:', e.message);
  }
  return createEmptyState();
}

function saveState(state) {
  const STATE_FILE = getStatePath();
  try {
    // Asegurar directorio
    const dir = path.dirname(STATE_FILE);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    // Backup del estado anterior
    if (fs.existsSync(STATE_FILE)) {
      _createBackup();
    }

    state.lastSync = new Date().toISOString();
    fs.writeFileSync(STATE_FILE, JSON.stringify(state, null, 2), 'utf8');
    console.log(`[woo-state] Estado guardado en: ${STATE_FILE} (${Object.keys(state.products).length} productos)`);
  } catch (e) {
    console.error('[woo-state] Error guardando estado:', e.message);
    throw e;
  }
}

/**
 * Guarda el estado de forma incremental (sin backup para mayor velocidad).
 * Llamar después de CADA lote para no perder progreso si el proceso se interrumpe.
 * @param {Object} state
 */
function saveStateIncremental(state) {
  const STATE_FILE = getStatePath();
  try {
    const dir = path.dirname(STATE_FILE);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    state.lastSync = new Date().toISOString();
    // Escritura atómica: escribir a temporal y renombrar
    const tmpFile = STATE_FILE + '.tmp';
    fs.writeFileSync(tmpFile, JSON.stringify(state, null, 2), 'utf8');
    fs.renameSync(tmpFile, STATE_FILE);
  } catch (e) {
    // Fallo silencioso en incremental — el estado completo se guardará al final de todas formas
    console.warn('[woo-state] Advertencia en guardado incremental:', e.message);
  }
}

function _createBackup() {
  const STATE_FILE = getStatePath();
  const BACKUP_DIR = getBackupDir();
  try {
    if (!fs.existsSync(BACKUP_DIR)) {
      fs.mkdirSync(BACKUP_DIR, { recursive: true });
    }

    // Limpiar backups viejos
    const files = fs.readdirSync(BACKUP_DIR).sort();
    while (files.length >= MAX_BACKUPS) {
      const oldest = files.shift();
      fs.unlinkSync(path.join(BACKUP_DIR, oldest));
    }

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const backupPath = path.join(BACKUP_DIR, `woo-state-${timestamp}.json`);
    fs.copyFileSync(STATE_FILE, backupPath);
  } catch (e) {
    console.warn('[woo-state] Error creando backup:', e.message);
  }
}

/**
 * Importar estado desde un archivo externo (para la primera carga).
 * El archivo puede ser un JSON con la estructura de woo-state o un export simplificado.
 */
function importState(filePath) {
  try {
    const raw = fs.readFileSync(filePath, 'utf8');
    const data = JSON.parse(raw);

    if (data.version === 2 && data.products) {
      // Es un woo-state.json directamente
      saveState(data);
      return { success: true, productCount: Object.keys(data.products).length };
    }

    // Si es un array de productos exportados desde la primera carga
    if (Array.isArray(data)) {
      const state = createEmptyState();
      for (const p of data) {
        if (p.sku) {
          state.products[p.sku] = {
            wooId: p.id || p.wooId,
            type: p.type || 'simple',
            precio1: p.precio1 || p.regular_price || 0,
            precio2: p.precio2 || 0,
            existencia: p.existencia || p.stock_quantity || 0,
            categories: p.categories || '',
            marca: p.marca || '',
            hasImage: !!(p.hasImage || p.imageCount > 0),
            parentWooId: p.parentWooId || p.parent_id || null,
            descripcionHash: null
          };
        }
      }
      saveState(state);
      return { success: true, productCount: Object.keys(state.products).length };
    }

    return { success: false, error: 'Formato no reconocido' };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function stateExists() {
  return fs.existsSync(getStatePath());
}

function getProductBySku(state, sku) {
  return state.products[sku] || null;
}

function updateProduct(state, sku, data) {
  state.products[sku] = { ...state.products[sku], ...data };
}

function removeProduct(state, sku) {
  delete state.products[sku];
}

function updateCategory(state, name, wooId) {
  state.categories[name] = wooId;
}

function getCategoryId(state, name) {
  return state.categories[name] || null;
}

function updateVariableParent(state, codigoPadre, wooId, children) {
  state.variableParents[codigoPadre] = { wooId, children };
}

function getVariableParent(state, codigoPadre) {
  return state.variableParents[codigoPadre] || null;
}

// getStatePath ya está definida arriba como función lazy

/**
 * Detecta si la última sync quedó incompleta (crash / cierre abrupto).
 * Una sesión incompleta es aquella que tiene currentSession con datos
 * pero que nunca fue limpiada al finalizar (el guardado final llama saveState
 * que no limpia currentSession, así que verificamos si tiene un id activo).
 * 
 * La sesión se considera "incompleta" si:
 *   - Existe currentSession con un id
 *   - Y algún producto fue creado o actualizado en ella (started processing)
 *   - Pero nunca se generó el reporte final (indicado por tener session.id
 *     con la misma fecha que lastSync o más reciente)
 * 
 * Forma simple y confiable: si currentSession existe Y tiene created > 0 OR errors > 0,
 * significa que el proceso llegó a procesar algo pero no terminó limpiamente.
 * @returns {{ incomplete: boolean, sessionId?: string, created?: number, errors?: number }}
 */
function getIncompleteSessionInfo(state) {
  if (!state || !state.currentSession) return { incomplete: false };
  const s = state.currentSession;
  // Si tiene id y hubo actividad (creados, errores, imágenes, etc.)
  const hadActivity = (s.created || 0) > 0 || (s.updated || 0) > 0 ||
    (s.imagesUploaded || 0) > 0 || (s.errorDetails || []).length > 0;
  if (s.id && hadActivity) {
    return {
      incomplete: true,
      sessionId: s.id,
      created: s.created || 0,
      errors: (s.errorDetails || []).length,
      startedAt: s.startedAt
    };
  }
  return { incomplete: false };
}

/**
 * Retorna un resumen de lo que hay en el state para el reporte final.
 */
function getStateSummary(state) {
  const prods = state.products;
  const keys = Object.keys(prods);
  const simples = keys.filter(k => prods[k].type === 'simple').length;
  const variations = keys.filter(k => prods[k].type === 'variation').length;
  const parentIds = new Set(keys.filter(k => prods[k].type === 'variation').map(k => prods[k].parentWooId));
  const variables = parentIds.size;
  const conImagen = keys.filter(k => prods[k].hasImage).length;
  return {
    totalEntradas: keys.length,
    simples,
    variables,
    variations,
    totalEnWooCommerce: simples + variables + variations,
    conImagen
  };
}

module.exports = {
  createEmptyState,
  loadState,
  saveState,
  saveStateIncremental,
  importState,
  stateExists,
  getProductBySku,
  updateProduct,
  removeProduct,
  updateCategory,
  getCategoryId,
  updateVariableParent,
  getVariableParent,
  getStatePath,
  getStateSummary,
  getIncompleteSessionInfo,
  initSession,
  addSessionCreated,
  addSessionUpdated,
  addSessionDeleted,
  addSessionPendingDelete,
  addSessionError,
  addSessionSkipped,
  addSessionImage
};
