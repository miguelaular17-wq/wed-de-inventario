/**
 * main.js — Proceso principal Electron v2
 */
const { app, BrowserWindow, ipcMain, dialog } = require('electron');
const path = require('path');
const fs = require('fs');
const { runSync } = require('./scripts/sync-engine');
const { loadState, importState, stateExists, getIncompleteSessionInfo } = require('./scripts/woo-state');
const { getConfig } = require('./scripts/env-config');

// Squirrel startup
if (require('electron-squirrel-startup')) app.quit();

// ─── Flag de proceso crítico en curso ─────────────────────────
// Cuando hay un rebuild o sync activo, bloqueamos el cierre accidental.
let criticalProcessActive = false;
let mainWindow = null;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1000,
    height: 700,
    minWidth: 800,
    minHeight: 600,
    icon: path.join(__dirname, 'app-icon.ico'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true
    },
    backgroundColor: '#0f0f1a'
  });

  mainWindow.removeMenu();
  mainWindow.loadFile('index.html');

  // Interceptar cierre de ventana para proteger procesos críticos
  mainWindow.on('close', async (e) => {
    if (!criticalProcessActive) return; // Sin proceso activo → cerrar normalmente

    e.preventDefault(); // Evitar el cierre inmediato

    const choice = await dialog.showMessageBox(mainWindow, {
      type: 'warning',
      buttons: ['Esperar', 'Cerrar de todas formas'],
      defaultId: 0,
      cancelId: 0,
      title: 'Proceso en curso',
      message: 'Hay un proceso activo (Reconstruir State)',
      detail: 'Si cierras ahora, el woo-state.json podría quedar incompleto o sin los últimos cambios.\n\n¿Deseas esperar a que termine?'
    });

    if (choice.response === 1) {
      // El usuario elige cerrar de todas formas
      criticalProcessActive = false;
      mainWindow.close();
    }
    // Si elige esperar, no se hace nada → la ventana permanece abierta
  });
}

app.whenReady().then(createWindow);
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

// ─── IPC Handlers ─────────────────────────────────────────────

ipcMain.handle('restart-app', () => {
  app.relaunch();
  app.exit();
});

ipcMain.handle('get-env-mode', () => {
  const config = getConfig();
  return {
    testMode: config.testMode,
    label: config.label,
    url: config.WP_API_URL
  };
});

ipcMain.handle('get-state-info', () => {
  try {
    const config = getConfig();
    if (!stateExists()) {
      return { exists: false, productCount: 0, lastSync: null, testMode: config.testMode, url: config.WP_API_URL };
    }
    const state = loadState();
    const incompleteSession = getIncompleteSessionInfo(state);
    return {
      exists: true,
      productCount: Object.keys(state.products).length,
      variableParents: Object.keys(state.variableParents).length,
      categoryCount: Object.keys(state.categories).length,
      lastSync: state.lastSync,
      testMode: config.testMode,
      url: config.WP_API_URL,
      incompleteSession  // { incomplete, sessionId, created, errors, startedAt }
    };
  } catch (e) {
    return { exists: false, productCount: 0, lastSync: null, error: e.message };
  }
});

ipcMain.handle('rebuild-state', async (event) => {
  criticalProcessActive = true;
  try {
    const sendProgress = (message) => {
      // Verificar que la ventana sigue abierta antes de enviar
      if (event.sender && !event.sender.isDestroyed()) {
        event.sender.send('progress-update', message);
      }
    };

    sendProgress('🔄 Iniciando reconstrucción del state desde WooCommerce...');

    // Importar dinámicamente para no cargar la API de WC hasta que se necesite
    const { run: runRebuild } = require('./scripts/rebuild-state');
    const result = await runRebuild(sendProgress);

    sendProgress(`\n✅ State reconstruido: ${result.simples} simples, ${result.variables} variables, ${result.variations} variaciones`);
    sendProgress(`💾 Total registrado en state: ${result.total}`);

    return { success: true, ...result };
  } catch (e) {
    if (event.sender && !event.sender.isDestroyed()) {
      event.sender.send('progress-update', `❌ Error reconstruyendo state: ${e.message}`);
    }
    return { success: false, error: e.message };
  } finally {
    criticalProcessActive = false;
  }
});

ipcMain.handle('import-state', async (event) => {
  const result = await dialog.showOpenDialog({
    title: 'Importar estado de WooCommerce',
    filters: [{ name: 'JSON', extensions: ['json'] }],
    properties: ['openFile']
  });

  if (result.canceled || !result.filePaths[0]) {
    return { success: false, error: 'Cancelado' };
  }

  return importState(result.filePaths[0]);
});

ipcMain.handle('upload-json', async (event, file) => {
  try {
    // Guardar el archivo JSON subido
    const uploadPathPro = path.join(app.getPath('userData'), 'uploads', 'productoslocal.json');
    const uploadPathDev = path.join(__dirname, 'uploads', 'productoslocal.json');
    const uploadPath = app.isPackaged ? uploadPathPro : uploadPathDev;

    const uploadDir = path.dirname(uploadPath);
    if (!fs.existsSync(uploadDir)) {
      fs.mkdirSync(uploadDir, { recursive: true });
    }

    const buffer = Buffer.from(file.data, 'base64');
    fs.writeFileSync(uploadPath, buffer);

    const options = file.options || {};

    const sendProgress = (message) => {
      event.sender.send('progress-update', message);
    };

    const envConfig = getConfig();
    sendProgress(`${envConfig.label} → ${envConfig.WP_API_URL}`);
    sendProgress(`📂 Archivo guardado: ${file.name}`);

    const syncOpts = {
      discountPercent: parseInt(options.discountPercent) || 30,
      syncPrecio: options.syncPrecio !== false,
      syncStock: options.syncStock !== false,
      syncCategorias: options.syncCategorias !== false,
      syncMarca: !!options.syncMarca,
      syncDescripcion: !!options.syncDescripcion,
      imagesEnabled: !!options.imagesEnabled,
      syncDelete: !!options.syncDelete,          // default false
      forceDelete: !!options.forceDelete          // omite límite del 40%
    };

    const activeFields = [
      syncOpts.syncPrecio ? '💰 Precio' : null,
      syncOpts.syncStock ? '📦 Stock' : null,
      syncOpts.syncCategorias ? '🏷️ Categorías' : null,
      syncOpts.syncMarca ? '🏪 Marca' : null,
      syncOpts.syncDescripcion ? '📝 Descripción' : null,
      syncOpts.imagesEnabled ? '🖼️ Imágenes' : null,
      syncOpts.syncDelete ? '🗑️ ELIMINAR' : null
    ].filter(Boolean).join(' | ');
    sendProgress(`⚙️ Campos activos: ${activeFields || 'ninguno'}`);

    // Ejecutar sincronización
    const result = await runSync(syncOpts, sendProgress);

    return result;

  } catch (error) {
    console.error('Error en sincronización:', error);
    return { success: false, summary: `Error: ${error.message}`, errors: [{ sku: 'FATAL', error: error.message }] };
  }
});
