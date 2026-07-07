<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MovimientoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\RequisicionController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\FinanzasController;
use App\Http\Controllers\CobranzaController;
use App\Http\Controllers\VentasController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSedeSelected;
use App\Http\Controllers\CompradorController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->isComprador() || $user->isMarketing()) {
            return redirect()->route('comprador.dashboard');
        }
        if ($user->isFinanzas()) {
            return redirect()->route('finanzas.flujo_caja');
        }
        if ($user->isCobranza()) {
            return redirect()->route('cobranza.index');
        }
        if ($user->isVendedor()) {
            return redirect()->route('vendedor.dashboard');
        }

        return redirect()->route('ventas.index');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::get('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function () {
    Route::get('/sede', [SedeController::class, 'select'])->name('sede.select');
    Route::post('/sede', [SedeController::class, 'store'])->name('sede.store');
    Route::post('/sede/cambiar', [SedeController::class, 'change'])->name('sede.change');
    Route::post('/tutorial/avanzar', [\App\Http\Controllers\TutorialController::class, 'advance'])->name('tutorial.advance');
    Route::post('/tutorial/completar', [\App\Http\Controllers\TutorialController::class, 'complete'])->name('tutorial.complete');
    Route::post('/tutorial/reiniciar', [\App\Http\Controllers\TutorialController::class, 'restart'])->name('tutorial.restart');
});

Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/importar', [ImportController::class, 'create'])->name('import.create');
    Route::post('/importar', [ImportController::class, 'store'])->name('import.store');
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('/usuarios/export', [UserController::class, 'export'])->name('users.export');
    Route::post('/usuarios/import', [UserController::class, 'import'])->name('users.import');
    Route::post('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/config/cashea', [UserController::class, 'updateCashea'])->name('config.cashea.update');
    Route::post('/clear-cache', [DashboardController::class, 'clearCache'])->name('clear-cache');
    
    // Login logs
    Route::get('/inicios-sesion', [UserController::class, 'loginLogs'])->name('users.login-logs');

    // Movimientos
    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/sync', [MovimientoController::class, 'sync'])->name('movimientos.sync');

    // Sync Logs
    Route::get('/sync-logs', [\App\Http\Controllers\Admin\SyncLogController::class, 'index'])->name('sync_logs.index');

    // Admin Products
    Route::get('/productos', [\App\Http\Controllers\Admin\ProductController::class, 'index'])->name('productos.index');
    Route::delete('/productos/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy'])->name('productos.destroy');
});

// Sede change views accessible by roles with sede access
Route::middleware(['auth', 'role:admin,supervisor,telefonia,sede,comprador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sedes', [\App\Http\Controllers\Admin\SedeController::class, 'index'])->name('sedes.index');
    Route::post('/sedes/{sede}/usar', [\App\Http\Controllers\Admin\SedeController::class, 'use'])->name('sedes.use');
});

// Sede views restricted by role
Route::middleware(['auth', EnsureSedeSelected::class, 'role:admin,supervisor,telefonia,sede,comprador'])->group(function () {
    Route::get('/ventas', [VentasController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/sync', [VentasController::class, 'sync'])->name('ventas.sync');
    Route::get('/ventas/mayor-demanda', [VentasController::class, 'mayorDemanda'])->name('ventas.mayor_demanda');
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::post('/inventario/requisicion-manual', [InventarioController::class, 'storeManual'])->name('inventario.manual.store');
    Route::post('/inventario/requisicion-manual/batch', [InventarioController::class, 'storeManualBatch'])->name('inventario.manual.store_batch');
    Route::delete('/inventario/requisicion-manual', [InventarioController::class, 'destroyManual'])->name('inventario.manual.destroy');
    Route::get('/inventario/metricas-manual', [InventarioController::class, 'metricasManual'])->name('inventario.manual.metricas');
    Route::get('/inventario/sync', [InventarioController::class, 'sync'])->name('inventario.sync');
    Route::get('/requisicion', [RequisicionController::class, 'form'])->name('requisicion.form');
    Route::post('/requisicion/exportar', [RequisicionController::class, 'export'])->name('requisicion.export');
});



// Comprador & Marketing specific routes
Route::middleware(['auth', 'role:admin,comprador,marketing'])->prefix('compras')->group(function () {
    Route::get('/', [CompradorController::class, 'index'])->name('comprador.dashboard');
    Route::get('/exportar', [CompradorController::class, 'export'])->name('comprador.export');
    Route::post('/notificar', [CompradorController::class, 'notifyRedistribution'])->name('comprador.notify');
    Route::post('/publicidad/toggle', [CompradorController::class, 'togglePublicidad'])->name('comprador.publicidad.toggle');
    
    // Histórico de ventas mensuales
    Route::get('/historico-ventas', [\App\Http\Controllers\CompradorVentasController::class, 'index'])->name('comprador.historico');
    Route::get('/historico-ventas/export', [\App\Http\Controllers\CompradorVentasController::class, 'export'])->name('comprador.historico.export');

    // Análisis de Sustitutos
    Route::get('/sustitutos', [\App\Http\Controllers\CompradorSustitutosController::class, 'index'])->name('comprador.sustitutos');

    // Toggle Exclusión de Compras
    Route::post('/productos/{id}/toggle-exclusion', [\App\Http\Controllers\CompradorController::class, 'toggleExclusion'])->name('comprador.productos.toggle_exclusion');
});

// Vendedor specific routes
Route::middleware(['auth', 'role:vendedor'])->prefix('vendedor')->group(function () {
    Route::get('/', [\App\Http\Controllers\VendedorController::class, 'index'])->name('vendedor.dashboard');
});

// Notifications routes for all authenticated users
Route::middleware('auth')->group(function () {
    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificaciones/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notificaciones/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');
});

// User profile: change sede
Route::middleware('auth')->group(function () {
    Route::get('/perfil/sede', [\App\Http\Controllers\UserSedeController::class, 'edit'])->name('user.sede.edit');
    Route::post('/perfil/sede', [\App\Http\Controllers\UserSedeController::class, 'update'])->name('user.sede.update');
});

// Finanzas routes
Route::middleware(['auth', 'role:admin,finanzas'])->prefix('finanzas')->group(function () {
    Route::get('/flujo-caja', [FinanzasController::class, 'flujoCaja'])->name('finanzas.flujo_caja');
    Route::post('/flujo-caja/reset', [FinanzasController::class, 'resetDaily'])->name('finanzas.reset_daily');
    Route::post('/flujo-caja/egreso', [FinanzasController::class, 'storeEgreso'])->name('finanzas.store_egreso');
    Route::post('/flujo-caja/egresos-bulk', [FinanzasController::class, 'storeEgresosBulk'])->name('finanzas.store_egresos_bulk');
    Route::post('/flujo-caja/ocr-receipt', [FinanzasController::class, 'ocrReceipt'])->name('finanzas.ocr_receipt');
    Route::post('/flujo-caja/ocr-saldos', [FinanzasController::class, 'ocrSaldos'])->name('finanzas.ocr_saldos');
    Route::get('/flujo-caja/reporte-diario', [FinanzasController::class, 'reporteDiarioCaja'])->name('finanzas.reporte_diario_caja');
    Route::post('/flujo-caja/cuenta/{id}', [FinanzasController::class, 'updateCuenta'])->name('finanzas.update_cuenta');
    Route::post('/flujo-caja/resumen/{id}', [FinanzasController::class, 'updateResumen'])->name('finanzas.update_resumen');
    Route::post('/flujo-caja/planificacion/{id}', [FinanzasController::class, 'updatePlanificacion'])->name('finanzas.update_planificacion');
    Route::get('/conciliaciones', [FinanzasController::class, 'conciliaciones'])->name('finanzas.conciliaciones');
    Route::post('/conciliaciones/upload', [FinanzasController::class, 'uploadConciliacion'])->name('finanzas.conciliaciones.upload');
    Route::post('/conciliaciones/process', [FinanzasController::class, 'processConciliacion'])->name('finanzas.conciliaciones.process');
    Route::post('/conciliaciones/add-missing', [App\Http\Controllers\FinanzasController::class, 'addMissingConciliacion'])->name('finanzas.conciliaciones.add_missing');
    Route::post('/conciliaciones/ignore', [App\Http\Controllers\FinanzasController::class, 'ignoreConciliacion'])->name('finanzas.conciliaciones.ignore');
    Route::post('/conciliaciones/clear', [App\Http\Controllers\FinanzasController::class, 'clearConciliacion'])->name('finanzas.conciliaciones.clear');
    Route::get('/conciliaciones/reporte', [App\Http\Controllers\FinanzasController::class, 'reporteConciliacion'])->name('finanzas.conciliaciones.reporte');
});

// Cobranza routes
Route::middleware(['auth', 'role:admin,cobranza'])->prefix('cobranza')->group(function () {
    Route::get('/', [CobranzaController::class, 'index'])->name('cobranza.index');
    Route::post('/importar', [CobranzaController::class, 'importarExcel'])->name('cobranza.importar');
    Route::post('/limpiar', [CobranzaController::class, 'limpiarClientes'])->name('cobranza.limpiar');
});
Route::get('/finanzas/reporte-consolidado', [App\Http\Controllers\FinanzasController::class, 'reporteConsolidado'])->name('finanzas.reporte_consolidado');

Route::get('/ping', function () { return 'OK'; });

Route::get('/pure', function () {
    $inicio = microtime(true);
    return response('Tiempo: ' . round((microtime(true) - $inicio) * 1000, 2) . ' ms');
});
