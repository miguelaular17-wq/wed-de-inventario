const fileInput = document.getElementById('fileInput');
const uploadZone = document.getElementById('uploadZone');
const uploadLabel = document.getElementById('uploadLabel');
const fileName = document.getElementById('fileName');
const btnSync = document.getElementById('btnSync');
const btnImportState = document.getElementById('btnImportState');
const btnRebuildState = document.getElementById('btnRebuildState');
const btnRebuildFromAlert = document.getElementById('btnRebuildFromAlert');
const btnRestart = document.getElementById('btnRestart');
const progressCard = document.getElementById('progressCard');
const logContainer = document.getElementById('logContainer');
const progressBar = document.getElementById('progressBar');
const resultCard = document.getElementById('resultCard');
const uploadCard = document.getElementById('uploadCard');
const stateBadge = document.getElementById('stateBadge');
const stateText = document.getElementById('stateText');
const discountInput = document.getElementById('discountInput');
// Switches de sincronización
const switchPrecio = document.getElementById('switchPrecio');
const switchStock = document.getElementById('switchStock');
const switchCategorias = document.getElementById('switchCategorias');
const switchMarca = document.getElementById('switchMarca');
const switchDescripcion = document.getElementById('switchDescripcion');
const switchImagenes = document.getElementById('switchImagenes');
const switchEliminar = document.getElementById('switchEliminar');
const switchForzar = document.getElementById('switchForzar');
const forceDeleteRow = document.getElementById('forceDeleteRow');
// Alias legacy
const imagesSwitch = switchImagenes;
// Preflight alert
const preflightAlert = document.getElementById('preflightAlert');
const preflightIcon = document.getElementById('preflightIcon');
const preflightTitle = document.getElementById('preflightTitle');
const preflightDetail = document.getElementById('preflightDetail');
// Interrupted sync banner
const interruptedAlert = document.getElementById('interruptedAlert');
const interruptedDetail = document.getElementById('interruptedDetail');


// ─── State ────────────────────────────────────
let selectedFile = null;
let currentProductCount = 0;  // Cantidad de productos en el state (0 = primera vez)

// ─── Preferencias persistentes (localStorage) ─────────────────────────────
const PREFS_KEY = 'palacio-sync-prefs';

// Defaults: imágenes activo por defecto, eliminar/forzar apagados por seguridad
const DEFAULTS = {
  discountPercent: 30,
  syncPrecio: true,
  syncStock: true,
  syncCategorias: true,
  syncMarca: false,
  syncDescripcion: false,
  imagesEnabled: true,   // ← activo por defecto
  syncDelete: false,
  forceDelete: false
};

function loadPrefs() {
  try {
    const raw = localStorage.getItem(PREFS_KEY);
    return raw ? { ...DEFAULTS, ...JSON.parse(raw) } : { ...DEFAULTS };
  } catch { return { ...DEFAULTS }; }
}

function savePrefs() {
  try {
    const prefs = {
      discountPercent: parseInt(discountInput.value) || DEFAULTS.discountPercent,
      syncPrecio: switchPrecio.checked,
      syncStock: switchStock.checked,
      syncCategorias: switchCategorias.checked,
      syncMarca: switchMarca.checked,
      syncDescripcion: switchDescripcion.checked,
      imagesEnabled: switchImagenes.checked,
      syncDelete: switchEliminar.checked,
      forceDelete: switchForzar.checked
    };
    localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
  } catch { /* ignorar errores de storage */ }
}

function applyPrefs(prefs) {
  discountInput.value = prefs.discountPercent;
  switchPrecio.checked = prefs.syncPrecio;
  switchStock.checked = prefs.syncStock;
  switchCategorias.checked = prefs.syncCategorias;
  switchMarca.checked = prefs.syncMarca;
  switchDescripcion.checked = prefs.syncDescripcion;
  switchImagenes.checked = prefs.imagesEnabled;
  switchEliminar.checked = prefs.syncDelete;
  switchForzar.checked = prefs.forceDelete;
  // Sincronizar visibilidad del botón Forzar
  if (prefs.syncDelete) {
    forceDeleteRow.classList.remove('hidden');
  } else {
    forceDeleteRow.classList.add('hidden');
  }
}

// Aplicar preferencias guardadas al iniciar
applyPrefs(loadPrefs());

// Guardar preferencias al cambiar cualquier control
[switchPrecio, switchStock, switchCategorias, switchMarca,
  switchDescripcion, switchImagenes, switchEliminar, switchForzar
].forEach(sw => sw.addEventListener('change', savePrefs));
discountInput.addEventListener('input', savePrefs);

// ─── Toggle de "Eliminar ausentes" — controla visibilidad de "Forzar" ─────────
switchEliminar.addEventListener('change', () => {
  if (switchEliminar.checked) {
    forceDeleteRow.classList.remove('hidden');
  } else {
    forceDeleteRow.classList.add('hidden');
    switchForzar.checked = false; // Resetear forzar cuando eliminar se apaga
  }
});

// ─── Init ─────────────────────────────────────
async function init() {
  try {
    const info = await window.api.getStateInfo();

    // Show mode badge in header
    const header = document.querySelector('.header p');
    if (info.testMode) {
      header.innerHTML = '<span style="color: var(--warning); font-weight: 600;">🧪 MODO TEST</span> — ' + (info.url || '');
      document.querySelector('.header h1').style.background = 'linear-gradient(135deg, #ffa726, #ff7043)';
      document.querySelector('.header h1').style.webkitBackgroundClip = 'text';
    } else {
      header.innerHTML = '<span style="color: var(--success); font-weight: 600;">🏪 PRODUCCIÓN</span> — ' + (info.url || '');
    }

    if (info.exists) {
      stateBadge.className = 'state-badge connected';
      const lastSync = info.lastSync
        ? new Date(info.lastSync).toLocaleString('es-VE')
        : 'N/A';
      currentProductCount = info.productCount || 0;
      stateText.textContent = `${info.productCount} productos | Última sync: ${lastSync}`;

      // Detectar sesión interrumpida
      const incomplete = info.incompleteSession;
      if (incomplete && incomplete.incomplete) {
        const fechaSession = incomplete.startedAt
          ? new Date(incomplete.startedAt).toLocaleString('es-VE')
          : 'fecha desconocida';
        interruptedDetail.textContent =
          `Sync del ${fechaSession} no completó — ${incomplete.created} creados, ${incomplete.errors} errores registrados. ` +
          `El state puede estar desincronizado con WooCommerce. Se recomienda reconstruir el state antes de sincronizar.`;
        interruptedAlert.classList.remove('hidden');
      } else {
        interruptedAlert.classList.add('hidden');
      }
    } else {
      currentProductCount = 0;
      stateBadge.className = 'state-badge disconnected';
      stateText.textContent = 'Sin estado — importa uno o sincroniza por primera vez';
      interruptedAlert.classList.add('hidden');
    }
  } catch (e) {
    console.error('Error al obtener estado:', e);
  }
}

init();

// ─── File Upload ──────────────────────────────

/**
 * Cuenta productos en el JSON (soporta array directo o wrapper { articulos: [...] })
 */
function countJsonProducts(text) {
  try {
    const parsed = JSON.parse(text);
    if (Array.isArray(parsed)) return parsed.length;
    if (parsed && Array.isArray(parsed.articulos)) return parsed.articulos.length;
    if (parsed && Array.isArray(parsed.products)) return parsed.products.length;
    return null;
  } catch { return null; }
}

/**
 * Muestra el panel de pre-análisis comparando el JSON con el state actual.
 */
async function runPreflight(file) {
  try {
    const text = await file.text();
    const jsonCount = countJsonProducts(text);
    if (jsonCount === null) {
      showPreflight('warn', '⚠️ No se pudo contar productos', 'El JSON no tiene el formato esperado.');
      return;
    }

    const info = await window.api.getStateInfo();
    const stateCount = info.productCount || 0;

    if (stateCount === 0) {
      showPreflight('ok', '✅ Primera carga',
        `${jsonCount.toLocaleString('es-VE')} productos en el JSON. No hay estado previo — todo se creará.`);
      return;
    }

    const ratio = jsonCount / stateCount;
    const toDeleteEst = Math.max(0, stateCount - jsonCount);
    const deletePercent = ((toDeleteEst / stateCount) * 100).toFixed(0);

    if (ratio >= 0.85) {
      // JSON completo o casi completo
      showPreflight('ok', '✅ JSON completo',
        `${jsonCount.toLocaleString('es-VE')} productos en el JSON vs ${stateCount.toLocaleString('es-VE')} en WooCommerce. ` +
        (toDeleteEst > 0 ? `Se detectarán ~${toDeleteEst.toLocaleString('es-VE')} posibles eliminaciones (${deletePercent}%).` : 'Sin eliminaciones esperadas.'));
    } else if (ratio >= 0.60) {
      // JSON incompleto pero tolerable
      showPreflight('warn', '⚠️ JSON incompleto',
        `El JSON tiene ${jsonCount.toLocaleString('es-VE')} productos pero WooCommerce tiene ${stateCount.toLocaleString('es-VE')}. ` +
        `Si “Eliminar ausentes” está activo, se eliminarán ~${toDeleteEst.toLocaleString('es-VE')} productos (${deletePercent}%). Verifica que el JSON sea completo.`);
    } else {
      // JSON muy incompleto — zona de peligro
      showPreflight('danger', '🚨 JSON MUY INCOMPLETO',
        `El JSON solo tiene ${jsonCount.toLocaleString('es-VE')} productos (${(ratio * 100).toFixed(0)}% de los ${stateCount.toLocaleString('es-VE')} en WooCommerce). ` +
        `Activar “Eliminar ausentes” eliminaría ~${toDeleteEst.toLocaleString('es-VE')} productos (${deletePercent}%). ` +
        `El motor BLOQUEARÁ automáticamente la eliminación si supera el 40%.`);
    }
  } catch (e) {
    showPreflight('warn', '⚠️ Error en pre-análisis', e.message);
  }
}

function showPreflight(variant, title, detail) {
  preflightAlert.className = `preflight-alert ${variant}`;
  preflightAlert.classList.remove('hidden');
  preflightIcon.textContent = variant === 'ok' ? '✅' : variant === 'danger' ? '🚨' : '⚠️';
  preflightTitle.textContent = title;
  preflightDetail.textContent = detail;
}

async function handleFileSelected(file) {
  if (!file || !file.name.endsWith('.json')) return;
  selectedFile = file;
  uploadZone.classList.add('has-file');
  uploadLabel.textContent = 'Archivo seleccionado:';
  fileName.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
  fileName.classList.remove('hidden');
  btnSync.disabled = false;
  // Lanzar pre-análisis
  await runPreflight(file);
}

fileInput.addEventListener('change', (e) => {
  const file = e.target.files[0];
  handleFileSelected(file);
});

// Drag and drop
uploadZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
  uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file && file.name.endsWith('.json')) {
    // Update the fileInput too
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    handleFileSelected(file);
  }
});

// ─── Import State ───────────────────────────
btnImportState.addEventListener('click', async () => {
  const result = await window.api.importState();
  if (result.success) {
    addLogEntry(`✅ Estado importado: ${result.productCount} productos`, 'success');
    init(); // Refresh state badge
  } else if (result.error !== 'Cancelado') {
    addLogEntry(`❌ Error importando estado: ${result.error}`, 'error');
  }
});

// ─── Rebuild State ───────────────────────────
async function doRebuildState() {
  // Mostrar progreso
  progressCard.classList.add('active');
  logContainer.innerHTML = '';
  progressBar.classList.add('indeterminate');

  // Deshabilitar botones durante el proceso
  btnRebuildState.disabled = true;
  btnRebuildFromAlert.disabled = true;
  btnSync.disabled = true;
  btnImportState.disabled = true;

  // Escuchar progreso
  window.api.receiveProgressUpdate((message) => {
    addLogEntry(message);
  });

  try {
    const result = await window.api.rebuildState();
    progressBar.classList.remove('indeterminate');
    progressBar.style.width = '100%';

    if (result.success) {
      addLogEntry(`✅ State reconstruido exitosamente`, 'success');
      addLogEntry(`📦 ${result.simples} simples | 🔀 ${result.variables} variables | 🎛️ ${result.variations} variaciones`, 'info');
      interruptedAlert.classList.add('hidden'); // Ocultar banner de alerta
      currentProductCount = (result.simples || 0) + (result.variables || 0) + (result.variations || 0);
      init(); // Refrescar badge
    } else {
      addLogEntry(`❌ Error reconstruyendo state: ${result.error}`, 'error');
    }
  } catch (e) {
    addLogEntry(`❌ Error: ${e.message || e}`, 'error');
    progressBar.classList.remove('indeterminate');
    progressBar.style.background = 'var(--danger)';
  } finally {
    btnRebuildState.disabled = false;
    btnRebuildFromAlert.disabled = false;
    btnImportState.disabled = false;
    if (selectedFile) btnSync.disabled = false;
  }
}

btnRebuildState.addEventListener('click', doRebuildState);
btnRebuildFromAlert.addEventListener('click', doRebuildState);

// ─── Modal: primera vez / state vacío ─────────────────────
const firstRunModal = document.getElementById('firstRunModal');
const btnModalRebuildAndSync = document.getElementById('btnModalRebuildAndSync');
const btnModalSyncAnyway = document.getElementById('btnModalSyncAnyway');

// Al reconstruir desde el modal, luego de terminar lanza la sync automáticamente
btnModalRebuildAndSync.addEventListener('click', async () => {
  firstRunModal.classList.add('hidden');
  // Ejecutar reconstrucción
  await doRebuildState();
  // Si después de reconstruir hay productos y tenemos archivo, lanzar sync
  if (currentProductCount > 0 && selectedFile) {
    addLogEntry('\n🔄 Iniciando sincronización automática tras reconstrucción...', 'info');
    await doSync();
  }
});

// Al elegir "sincronizar de todas formas", cerrar modal y lanzar sync
btnModalSyncAnyway.addEventListener('click', async () => {
  firstRunModal.classList.add('hidden');
  await doSync();
});

// ─── Sync ─────────────────────────────────────
btnSync.addEventListener('click', async () => {
  if (!selectedFile) return;
  // Si el state está vacío, mostrar el modal de primera vez en vez de lanzar sync
  if (currentProductCount === 0) {
    firstRunModal.classList.remove('hidden');
    return;
  }
  await doSync();
});

// Función central de sincronización (reutilizable)
async function doSync() {

  // Disable controls
  btnSync.disabled = true;
  fileInput.disabled = true;
  btnImportState.classList.add('hidden');
  discountInput.disabled = true;
  switchPrecio.disabled = true;
  switchStock.disabled = true;
  switchCategorias.disabled = true;
  switchMarca.disabled = true;
  switchDescripcion.disabled = true;
  switchImagenes.disabled = true;
  switchEliminar.disabled = true;
  switchForzar.disabled = true;

  // Show progress
  progressCard.classList.add('active');
  logContainer.innerHTML = '';

  // Listen for progress updates
  window.api.receiveProgressUpdate((message) => {
    addLogEntry(message);
  });

  // Read file and send
  const reader = new FileReader();
  reader.onload = async () => {
    const base64Data = reader.result.split(',')[1];
    const options = {
      discountPercent: parseInt(discountInput.value) || 30,
      syncPrecio: switchPrecio.checked,
      syncStock: switchStock.checked,
      syncCategorias: switchCategorias.checked,
      syncMarca: switchMarca.checked,
      syncDescripcion: switchDescripcion.checked,
      imagesEnabled: switchImagenes.checked,
      syncDelete: switchEliminar.checked,
      forceDelete: switchForzar.checked
    };

    const activeFields = [
      options.syncPrecio ? '💰 Precio' : null,
      options.syncStock ? '📦 Stock' : null,
      options.syncCategorias ? '🏷️ Categorías' : null,
      options.syncMarca ? '🏪 Marca' : null,
      options.syncDescripcion ? '📝 Descripción' : null,
      options.imagesEnabled ? '🖼️ Imágenes' : null,
      options.syncDelete ? '🗑️ ELIMINAR' : null,
      options.forceDelete ? '⚡ FORZADO' : null
    ].filter(Boolean).join(' | ');

    addLogEntry(`⚙️ Descuento: ${options.discountPercent}% | Activos: ${activeFields || 'ninguno'}`, 'info');

    try {
      const result = await window.api.uploadJSON({
        data: base64Data,
        name: selectedFile.name,
        options
      });

      // Stop indeterminate progress
      progressBar.classList.remove('indeterminate');
      progressBar.style.width = '100%';

      // Show results
      if (result && typeof result === 'object') {
        showResults(result);
      } else {
        addLogEntry(String(result), 'info');
      }
    } catch (error) {
      addLogEntry(`❌ Error: ${error.message || error}`, 'error');
      progressBar.classList.remove('indeterminate');
      progressBar.style.width = '100%';
      progressBar.style.background = 'var(--danger)';
    }
  };

  reader.readAsDataURL(selectedFile);
}


// ─── Show Results ─────────────────────────────
function showResults(result) {
  resultCard.classList.add('active');

  document.getElementById('statCreated').textContent = result.created || 0;
  document.getElementById('statUpdated').textContent = result.updated || 0;
  document.getElementById('statDeleted').textContent = result.deleted || 0;
  document.getElementById('statImages').textContent = result.imagesUploaded || 0;
  document.getElementById('statSkipped').textContent = result.skipped || 0;
  document.getElementById('statErrors').textContent = (result.errors || []).length;

  // Mostrar ruta del reporte si está disponible
  if (result.reportPath) {
    const existing = document.getElementById('reportPathInfo');
    if (existing) existing.remove();

    const reportDiv = document.createElement('div');
    reportDiv.id = 'reportPathInfo';
    reportDiv.style.cssText = 'margin-top: 12px; padding: 10px 14px; background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.3); border-radius: 8px; font-size: 0.78rem; color: var(--text-secondary);';
    reportDiv.innerHTML = `📄 <strong>Reporte generado:</strong><br><code style="font-size:0.72rem; word-break:break-all;">${result.reportPath}</code>`;
    resultCard.appendChild(reportDiv);
  }

  addLogEntry('\n' + (result.summary || 'Sincronización completada.'), 'summary');

  // Refresh state badge
  init();
}

// ─── Restart ──────────────────────────────────
btnRestart.addEventListener('click', () => {
  window.api.restartApp();
});

// ─── Log Helpers ──────────────────────────────
function addLogEntry(message, type = '') {
  const entry = document.createElement('div');
  entry.className = `log-entry ${type}`;
  entry.textContent = message;
  logContainer.appendChild(entry);
  logContainer.scrollTop = logContainer.scrollHeight;
}