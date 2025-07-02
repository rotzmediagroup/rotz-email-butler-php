/**
 * ROTZ Email Butler - Desktop Application
 * Main Electron Process
 */

const { app, BrowserWindow, Menu, Tray, ipcMain, dialog, shell, nativeTheme } = require('electron');
const { autoUpdater } = require('electron-updater');
const windowStateKeeper = require('electron-window-state');
const Store = require('electron-store');
const path = require('path');
const isDev = require('electron-is-dev');
const { menubar } = require('menubar');
const AutoLaunch = require('auto-launch');
const contextMenu = require('electron-context-menu');
const unhandled = require('electron-unhandled');
const debug = require('electron-debug');

// Enable live reload for Electron in development
if (isDev) {
  require('electron-reload')(__dirname, {
    electron: path.join(__dirname, '..', 'node_modules', '.bin', 'electron'),
    hardResetMethod: 'exit'
  });
}

// Initialize store for app settings
const store = new Store({
  defaults: {
    windowBounds: { width: 1200, height: 800 },
    theme: 'system',
    autoLaunch: false,
    notifications: true,
    minimizeToTray: true,
    closeToTray: true,
    autoSync: true,
    syncInterval: 15,
    serverUrl: 'http://localhost',
    websocketUrl: 'ws://localhost:8080'
  }
});

// Global variables
let mainWindow;
let tray;
let menubarApp;
let isQuitting = false;

// Auto launcher
const autoLauncher = new AutoLaunch({
  name: 'ROTZ Email Butler',
  path: app.getPath('exe')
});

// Enable context menu and debug in development
contextMenu();
if (isDev) {
  debug();
}

// Handle unhandled errors
unhandled();

// Prevent multiple instances
const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    // Someone tried to run a second instance, focus our window instead
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });
}

// App event handlers
app.whenReady().then(() => {
  createMainWindow();
  createTray();
  createMenubar();
  setupAutoUpdater();
  setupIpcHandlers();
  
  // Set app user model ID for Windows
  if (process.platform === 'win32') {
    app.setAppUserModelId('com.rotzmediagroup.email-butler');
  }
  
  // Handle auto launch setting
  if (store.get('autoLaunch')) {
    autoLauncher.enable();
  }
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createMainWindow();
  }
});

app.on('before-quit', () => {
  isQuitting = true;
});

// Create main window
function createMainWindow() {
  // Load window state
  let mainWindowState = windowStateKeeper({
    defaultWidth: 1200,
    defaultHeight: 800
  });

  // Create the browser window
  mainWindow = new BrowserWindow({
    x: mainWindowState.x,
    y: mainWindowState.y,
    width: mainWindowState.width,
    height: mainWindowState.height,
    minWidth: 800,
    minHeight: 600,
    show: false,
    icon: path.join(__dirname, 'assets/icons/icon.png'),
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      enableRemoteModule: false,
      preload: path.join(__dirname, 'preload.js'),
      webSecurity: !isDev
    }
  });

  // Let windowStateKeeper manage the window
  mainWindowState.manage(mainWindow);

  // Load the app
  const startUrl = isDev 
    ? 'http://localhost:3000' 
    : `file://${path.join(__dirname, '../build/index.html')}`;
  
  mainWindow.loadURL(startUrl);

  // Show window when ready
  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    
    if (isDev) {
      mainWindow.webContents.openDevTools();
    }
  });

  // Handle window close
  mainWindow.on('close', (event) => {
    if (!isQuitting && store.get('closeToTray')) {
      event.preventDefault();
      mainWindow.hide();
      
      if (process.platform === 'darwin') {
        app.dock.hide();
      }
    }
  });

  // Handle window minimize
  mainWindow.on('minimize', (event) => {
    if (store.get('minimizeToTray')) {
      event.preventDefault();
      mainWindow.hide();
    }
  });

  // Set up menu
  const template = createMenuTemplate();
  const menu = Menu.buildFromTemplate(template);
  Menu.setApplicationMenu(menu);
}

// Create system tray
function createTray() {
  const iconPath = path.join(__dirname, 'assets/icons', 
    process.platform === 'win32' ? 'icon.ico' : 'icon.png'
  );
  
  tray = new Tray(iconPath);
  
  const contextMenu = Menu.buildFromTemplate([
    {
      label: 'Show ROTZ Email Butler',
      click: () => {
        mainWindow.show();
        if (process.platform === 'darwin') {
          app.dock.show();
        }
      }
    },
    {
      label: 'Sync Emails',
      click: () => {
        mainWindow.webContents.send('trigger-sync');
      }
    },
    {
      label: 'Process with AI',
      click: () => {
        mainWindow.webContents.send('trigger-ai-processing');
      }
    },
    { type: 'separator' },
    {
      label: 'Settings',
      click: () => {
        mainWindow.show();
        mainWindow.webContents.send('navigate-to', '/settings');
      }
    },
    { type: 'separator' },
    {
      label: 'Quit',
      click: () => {
        isQuitting = true;
        app.quit();
      }
    }
  ]);
  
  tray.setToolTip('ROTZ Email Butler');
  tray.setContextMenu(contextMenu);
  
  // Double click to show window
  tray.on('double-click', () => {
    mainWindow.show();
    if (process.platform === 'darwin') {
      app.dock.show();
    }
  });
}

// Create menubar app
function createMenubar() {
  menubarApp = menubar({
    icon: path.join(__dirname, 'assets/icons/menubar.png'),
    index: isDev ? 'http://localhost:3000/menubar' : `file://${path.join(__dirname, '../build/menubar.html')}`,
    browserWindow: {
      width: 400,
      height: 500,
      resizable: false,
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        preload: path.join(__dirname, 'preload.js')
      }
    },
    preloadWindow: true,
    showDockIcon: false
  });

  menubarApp.on('ready', () => {
    console.log('Menubar app is ready');
  });
}

// Create menu template
function createMenuTemplate() {
  const template = [
    {
      label: 'File',
      submenu: [
        {
          label: 'New Email',
          accelerator: 'CmdOrCtrl+N',
          click: () => {
            mainWindow.webContents.send('new-email');
          }
        },
        { type: 'separator' },
        {
          label: 'Sync All Emails',
          accelerator: 'CmdOrCtrl+R',
          click: () => {
            mainWindow.webContents.send('sync-all-emails');
          }
        },
        {
          label: 'Process with AI',
          accelerator: 'CmdOrCtrl+P',
          click: () => {
            mainWindow.webContents.send('process-with-ai');
          }
        },
        { type: 'separator' },
        {
          label: 'Settings',
          accelerator: 'CmdOrCtrl+,',
          click: () => {
            mainWindow.webContents.send('open-settings');
          }
        },
        { type: 'separator' },
        {
          role: 'quit'
        }
      ]
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' },
        { role: 'redo' },
        { type: 'separator' },
        { role: 'cut' },
        { role: 'copy' },
        { role: 'paste' },
        { role: 'selectall' }
      ]
    },
    {
      label: 'View',
      submenu: [
        { role: 'reload' },
        { role: 'forceReload' },
        { role: 'toggleDevTools' },
        { type: 'separator' },
        { role: 'resetZoom' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { type: 'separator' },
        { role: 'togglefullscreen' }
      ]
    },
    {
      label: 'AI Providers',
      submenu: [
        {
          label: 'Manage Providers',
          click: () => {
            mainWindow.webContents.send('navigate-to', '/ai-providers');
          }
        },
        {
          label: 'Test All Providers',
          click: () => {
            mainWindow.webContents.send('test-ai-providers');
          }
        },
        { type: 'separator' },
        {
          label: 'Enable All',
          click: () => {
            mainWindow.webContents.send('toggle-all-ai-providers', true);
          }
        },
        {
          label: 'Disable All',
          click: () => {
            mainWindow.webContents.send('toggle-all-ai-providers', false);
          }
        }
      ]
    },
    {
      label: 'Window',
      submenu: [
        { role: 'minimize' },
        { role: 'close' },
        {
          label: 'Hide to Tray',
          accelerator: 'CmdOrCtrl+H',
          click: () => {
            mainWindow.hide();
          }
        }
      ]
    },
    {
      role: 'help',
      submenu: [
        {
          label: 'About ROTZ Email Butler',
          click: () => {
            dialog.showMessageBox(mainWindow, {
              type: 'info',
              title: 'About ROTZ Email Butler',
              message: 'ROTZ Email Butler',
              detail: `Version: ${app.getVersion()}\nAI-Powered Email Management System\n\n© 2024 ROTZ Media Group`
            });
          }
        },
        {
          label: 'Check for Updates',
          click: () => {
            autoUpdater.checkForUpdatesAndNotify();
          }
        },
        { type: 'separator' },
        {
          label: 'Documentation',
          click: () => {
            shell.openExternal('https://github.com/rotzmediagroup/rotz-email-butler-php');
          }
        },
        {
          label: 'Report Issue',
          click: () => {
            shell.openExternal('https://github.com/rotzmediagroup/rotz-email-butler-php/issues');
          }
        }
      ]
    }
  ];

  // macOS specific menu adjustments
  if (process.platform === 'darwin') {
    template.unshift({
      label: app.getName(),
      submenu: [
        { role: 'about' },
        { type: 'separator' },
        { role: 'services' },
        { type: 'separator' },
        { role: 'hide' },
        { role: 'hideothers' },
        { role: 'unhide' },
        { type: 'separator' },
        { role: 'quit' }
      ]
    });

    // Window menu
    template[5].submenu = [
      { role: 'close' },
      { role: 'minimize' },
      { role: 'zoom' },
      { type: 'separator' },
      { role: 'front' }
    ];
  }

  return template;
}

// Setup auto updater
function setupAutoUpdater() {
  if (isDev) return;

  autoUpdater.checkForUpdatesAndNotify();

  autoUpdater.on('update-available', () => {
    dialog.showMessageBox(mainWindow, {
      type: 'info',
      title: 'Update Available',
      message: 'A new version is available. It will be downloaded in the background.',
      buttons: ['OK']
    });
  });

  autoUpdater.on('update-downloaded', () => {
    dialog.showMessageBox(mainWindow, {
      type: 'info',
      title: 'Update Ready',
      message: 'Update downloaded. The application will restart to apply the update.',
      buttons: ['Restart Now', 'Later']
    }).then((result) => {
      if (result.response === 0) {
        autoUpdater.quitAndInstall();
      }
    });
  });
}

// Setup IPC handlers
function setupIpcHandlers() {
  // Settings
  ipcMain.handle('get-setting', (event, key) => {
    return store.get(key);
  });

  ipcMain.handle('set-setting', (event, key, value) => {
    store.set(key, value);
    
    // Handle special settings
    if (key === 'autoLaunch') {
      if (value) {
        autoLauncher.enable();
      } else {
        autoLauncher.disable();
      }
    }
    
    return true;
  });

  // Theme
  ipcMain.handle('get-theme', () => {
    return nativeTheme.themeSource;
  });

  ipcMain.handle('set-theme', (event, theme) => {
    nativeTheme.themeSource = theme;
    store.set('theme', theme);
    return true;
  });

  // Notifications
  ipcMain.handle('show-notification', (event, options) => {
    const notification = new Notification({
      title: options.title,
      body: options.body,
      icon: path.join(__dirname, 'assets/icons/icon.png'),
      silent: !store.get('notifications')
    });

    notification.show();
    return true;
  });

  // File operations
  ipcMain.handle('show-save-dialog', async (event, options) => {
    const result = await dialog.showSaveDialog(mainWindow, options);
    return result;
  });

  ipcMain.handle('show-open-dialog', async (event, options) => {
    const result = await dialog.showOpenDialog(mainWindow, options);
    return result;
  });

  // Window operations
  ipcMain.handle('minimize-window', () => {
    mainWindow.minimize();
    return true;
  });

  ipcMain.handle('maximize-window', () => {
    if (mainWindow.isMaximized()) {
      mainWindow.unmaximize();
    } else {
      mainWindow.maximize();
    }
    return true;
  });

  ipcMain.handle('close-window', () => {
    mainWindow.close();
    return true;
  });

  // App info
  ipcMain.handle('get-app-version', () => {
    return app.getVersion();
  });

  ipcMain.handle('get-app-path', (event, name) => {
    return app.getPath(name);
  });

  // External links
  ipcMain.handle('open-external', (event, url) => {
    shell.openExternal(url);
    return true;
  });

  // Update badge count (macOS/Linux)
  ipcMain.handle('set-badge-count', (event, count) => {
    if (process.platform !== 'win32') {
      app.setBadgeCount(count);
    }
    return true;
  });
}

// Export for testing
module.exports = { app, mainWindow };

