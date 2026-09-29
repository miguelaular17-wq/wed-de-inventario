const { app, BrowserWindow, shell, Menu, nativeImage } = require('electron');
const path = require('path');
const fs = require('fs');

const DEFAULT_URL = app.isPackaged
  ? 'https://wed-de-inventario.onrender.com'
  : 'http://127.0.0.1:8000';

/** URL de la app web (Laravel). Override: ELECTRON_START_URL=https://tu-dominio.com */
const START_URL = process.env.ELECTRON_START_URL || DEFAULT_URL;

const LOGO_CANDIDATES = [
  path.join(__dirname, 'assets', 'icon.png'),
  path.join(__dirname, '..', 'public', 'logo.png'),
];

function loadAppIcon() {
  for (const logoPath of LOGO_CANDIDATES) {
    if (!fs.existsSync(logoPath)) {
      continue;
    }
    const img = nativeImage.createFromPath(logoPath);
    if (!img.isEmpty()) {
      return img;
    }
  }
  return undefined;
}

/** Orígenes permitidos dentro de la ventana (el resto se abre en el navegador). */
function allowedOrigins() {
  try {
    const u = new URL(START_URL);
    return new Set([u.origin]);
  } catch {
    return new Set(['http://127.0.0.1:8000', 'http://localhost:8000']);
  }
}

let mainWindow = null;

function createWindow() {
  const icon = loadAppIcon();

  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 900,
    minHeight: 600,
    show: false,
    title: 'Palacio de los Detalles',
    ...(icon ? { icon } : {}),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  if (icon) {
    mainWindow.setIcon(icon);
  }

  mainWindow.once('ready-to-show', () => mainWindow.show());

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  const allowed = allowedOrigins();
  mainWindow.webContents.on('will-navigate', (event, url) => {
    try {
      const origin = new URL(url).origin;
      if (!allowed.has(origin)) {
        event.preventDefault();
        shell.openExternal(url);
      }
    } catch {
      event.preventDefault();
    }
  });

  mainWindow.loadURL(START_URL).catch((err) => {
    console.error('No se pudo cargar', START_URL, err);
    mainWindow.loadURL(
      `data:text/html;charset=utf-8,${encodeURIComponent(
        `<!doctype html><html><body style="font-family:system-ui;padding:2rem">
          <h1>No se pudo abrir la app</h1>
          <p>URL: <code>${START_URL}</code></p>
          <p>Arranca Laravel (<code>php artisan serve</code>) o define <code>ELECTRON_START_URL</code>.</p>
          <pre>${String(err)}</pre>
        </body></html>`
      )}`
    );
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

function buildMenu() {
  const isMac = process.platform === 'darwin';
  const template = [
    ...(isMac
      ? [{ role: 'appMenu' }]
      : [
          {
            label: 'Archivo',
            submenu: [{ role: 'quit', label: 'Salir' }],
          },
        ]),
    {
      label: 'Ver',
      submenu: [
        { role: 'reload', label: 'Recargar' },
        { role: 'forceReload', label: 'Recargar forzado' },
        { type: 'separator' },
        { role: 'zoomIn', label: 'Acercar' },
        { role: 'zoomOut', label: 'Alejar' },
        { role: 'resetZoom', label: 'Zoom normal' },
        { type: 'separator' },
        { role: 'togglefullscreen', label: 'Pantalla completa' },
        { type: 'separator' },
        { role: 'toggleDevTools', label: 'DevTools' },
      ],
    },
    {
      label: 'Navegación',
      submenu: [
        {
          label: 'Ir al inicio',
          accelerator: 'Home',
          click: () => mainWindow?.loadURL(START_URL),
        },
        {
          label: 'Atrás',
          accelerator: 'Alt+Left',
          click: () => {
            if (mainWindow?.webContents.canGoBack()) {
              mainWindow.webContents.goBack();
            }
          },
        },
        {
          label: 'Adelante',
          accelerator: 'Alt+Right',
          click: () => {
            if (mainWindow?.webContents.canGoForward()) {
              mainWindow.webContents.goForward();
            }
          },
        },
      ],
    },
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

app.whenReady().then(() => {
  if (process.platform === 'win32') {
    app.setAppUserModelId('com.palacio.detalles');
  }

  const icon = loadAppIcon();
  if (icon && process.platform === 'darwin' && app.dock) {
    app.dock.setIcon(icon);
  }

  buildMenu();
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
