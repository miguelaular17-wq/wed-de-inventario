// Puente seguro (vacío por ahora). Si más adelante quieres APIs nativas
// (impresora, atajos, notificaciones), expónlas aquí con contextBridge.
const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('desktopApp', {
  platform: process.platform,
  isDesktop: true,
});
