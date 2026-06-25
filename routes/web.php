<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MovimientoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\RequisicionController;
use App\Http\Controllers\SedeController;
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
    Route::post('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/config/cashea', [UserController::class, 'updateCashea'])->name('config.cashea.update');
    Route::post('/clear-cache', [DashboardController::class, 'clearCache'])->name('clear-cache');
    
    // Login logs
    Route::get('/inicios-sesion', [UserController::class, 'loginLogs'])->name('users.login-logs');

    // Movimientos
    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/sync', [MovimientoController::class, 'sync'])->name('movimientos.sync');
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
