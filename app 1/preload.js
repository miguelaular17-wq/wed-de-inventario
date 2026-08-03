const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('api', {
  uploadJSON: async (file) => {
    try {
      return await ipcRenderer.invoke('upload-json', file);
    } catch (error) {
      console.error('Error al subir JSON:', error);
      return { success: false, summary: 'Error al subir JSON' };
    }
  },
  receiveProgressUpdate: (callback) => {
    ipcRenderer.on('progress-update', (event, message) => {
      callback(message);
    });
  },
  getStateInfo: () => ipcRenderer.invoke('get-state-info'),
  getEnvMode: () => ipcRenderer.invoke('get-env-mode'),
  importState: () => ipcRenderer.invoke('import-state'),
  rebuildState: () => ipcRenderer.invoke('rebuild-state'),
  restartApp: () => ipcRenderer.invoke('restart-app')
});