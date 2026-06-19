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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
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
    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/sync', [MovimientoController::class, 'sync'])->name('movimientos.sync');
    Route::get('/sedes', [\App\Http\Controllers\Admin\SedeController::class, 'index'])->name('sedes.index');
    Route::post('/sedes/{sede}/usar', [\App\Http\Controllers\Admin\SedeController::class, 'use'])->name('sedes.use');
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::post('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
});

Route::middleware(['auth', EnsureSedeSelected::class])->group(function () {
    Route::get('/ventas', [VentasController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/sync', [VentasController::class, 'sync'])->name('ventas.sync');
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::post('/inventario/requisicion-manual', [InventarioController::class, 'storeManual'])->name('inventario.manual.store');
    Route::get('/inventario/metricas-manual', [InventarioController::class, 'metricasManual'])->name('inventario.manual.metricas');
    Route::get('/inventario/sync', [InventarioController::class, 'sync'])->name('inventario.sync');
    Route::get('/requisicion', [RequisicionController::class, 'form'])->name('requisicion.form');
    Route::post('/requisicion/exportar', [RequisicionController::class, 'export'])->name('requisicion.export');
});

// User profile: change sede
Route::middleware('auth')->group(function () {
    Route::get('/perfil/sede', [\App\Http\Controllers\UserSedeController::class, 'edit'])->name('user.sede.edit');
    Route::post('/perfil/sede', [\App\Http\Controllers\UserSedeController::class, 'update'])->name('user.sede.update');
});
