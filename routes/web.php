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
use App\Http\Controllers\PedidoSolicitadoController;
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
        if ($user->isFinanzas() || $user->isAuditor()) {
            return redirect()->route('finanzas.flujo_caja');
        }
        if ($user->isCobranza()) {
            return redirect()->route('cobranza.index');
        }
        if ($user->isContabilidad()) {
            return redirect()->route('finanzas.conciliaciones');
        }
        if ($user->isVendedor()) {
            return redirect()->route('vendedor.dashboard');
        }

        if ($user->isTesoreria()) {
            return redirect()->route('tesoreria.dashboard');
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

    Route::get('/pedidos/buscar', [PedidoSolicitadoController::class, 'search'])->name('pedidos.search');
    Route::get('/pedidos/categorias', [PedidoSolicitadoController::class, 'categorias'])->name('pedidos.categorias');
    Route::post('/pedidos', [PedidoSolicitadoController::class, 'store'])->name('pedidos.store');
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
    Route::post('/clear-data', [DashboardController::class, 'clearData'])->name('clear-data');
    
    // Login logs
    Route::get('/inicios-sesion', [UserController::class, 'loginLogs'])->name('users.login-logs');

    // Movimientos
    Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    Route::get('/movimientos/sync', [MovimientoController::class, 'sync'])->name('movimientos.sync');

    // Sync Logs
    Route::get('/sync-logs', [\App\Http\Controllers\Admin\SyncLogController::class, 'index'])->name('sync_logs.index');

    // Admin Products
    Route::get('/productos', [\App\Http\Controllers\Admin\ProductController::class, 'index'])->name('productos.index');
    Route::post('/productos/export-json', [\App\Http\Controllers\Admin\ProductController::class, 'exportJson'])->name('productos.export_json');
    Route::delete('/productos/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy'])->name('productos.destroy');

    // Catalog Automator
    Route::get('/catalogo-auto', [\App\Http\Controllers\Admin\CatalogoAutoController::class, 'index'])->name('catalogo-auto.index');
    Route::post('/catalogo-auto/config', [\App\Http\Controllers\Admin\CatalogoAutoController::class, 'store'])->name('catalogo-auto.config');
    Route::post('/catalogo-auto/{id}/generar', [\App\Http\Controllers\Admin\CatalogoAutoController::class, 'generate'])->name('catalogo-auto.generate');
    Route::delete('/catalogo-auto/{id}', [\App\Http\Controllers\Admin\CatalogoAutoController::class, 'destroy'])->name('catalogo-auto.destroy');
});

// Sede change views accessible by roles with sede access
Route::middleware(['auth', 'role:admin,gerente,supervisor,telefonia,sede,comprador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sedes', [\App\Http\Controllers\Admin\SedeController::class, 'index'])->name('sedes.index');
    Route::post('/sedes/{sede}/usar', [\App\Http\Controllers\Admin\SedeController::class, 'use'])->name('sedes.use');
});

// Sede views restricted by role
Route::middleware(['auth', EnsureSedeSelected::class, 'role:admin,gerente,supervisor,telefonia,sede,comprador'])->group(function () {
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
Route::middleware(['auth', 'role:admin,gerente,comprador,marketing'])->prefix('compras')->group(function () {
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

    // Pedidos solicitados (Q pedir)
    Route::post('/pedidos/comprado', [PedidoSolicitadoController::class, 'marcarComprado'])->name('comprador.pedidos.comprado');
    Route::post('/pedidos/fuera-mercado', [PedidoSolicitadoController::class, 'marcarFueraMercado'])->name('comprador.pedidos.fuera_mercado');
    Route::get('/pedidos/reporte-excel', [PedidoSolicitadoController::class, 'reporteExcel'])->name('comprador.pedidos.excel');
    Route::post('/pedidos/reporte-pdf', [PedidoSolicitadoController::class, 'reportePdf'])->name('comprador.pedidos.pdf');
    Route::get('/pedidos/reporte-diario', [PedidoSolicitadoController::class, 'reporteDiarioPdf'])->name('comprador.pedidos.diario');

    // Route for supervisors to download daily report by sede
    Route::get('/pedidos/reporte-diario-sede', [PedidoSolicitadoController::class, 'reporteDiarioSedePdf'])
        ->withoutMiddleware('role:admin,gerente,comprador,marketing')
        ->middleware('role:supervisor,admin,gerente')
        ->name('comprador.pedidos.diario_sede');

    // Existencias (antes edurar)
    Route::get('/existencias', [\App\Http\Controllers\ExistenciasController::class, 'index'])->name('comprador.existencias');
    Route::get('/existencias/subcategorias', [\App\Http\Controllers\ExistenciasController::class, 'getSubcategorias'])->name('comprador.existencias.subcategorias');
});

// Vendedor specific routes (Now public for inventory checking)
Route::prefix('vendedor')->group(function () {
    Route::get('/', [\App\Http\Controllers\VendedorController::class, 'index'])->name('vendedor.dashboard');
});

// Tesoreria routes
Route::middleware(['auth', 'role:admin,gerente,tesoreria'])->prefix('tesoreria')->name('tesoreria.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TesoreriaController::class, 'dashboard'])->name('dashboard');
    Route::post('/ingreso-banco', [\App\Http\Controllers\TesoreriaController::class, 'storeIngresoBanco'])->name('ingreso_banco.store');
    Route::post('/lote-punto-venta', [\App\Http\Controllers\TesoreriaController::class, 'storeLotePuntoVenta'])->name('lote_pos.store');
    Route::put('/lote-punto-venta/{id}', [\App\Http\Controllers\TesoreriaController::class, 'updateLotePuntoVenta'])->name('lote_pos.update');
    Route::delete('/lote-punto-venta/{id}', [\App\Http\Controllers\TesoreriaController::class, 'destroyLotePuntoVenta'])->name('lote_pos.destroy');
});

// Notifications routes for all authenticated users
// Catálogo público - accesible sin login
Route::get('/catalogo', [\App\Http\Controllers\CatalogoController::class, 'index'])->name('catalogo.index');

Route::middleware('auth')->group(function () {
    // Catálogo Gráfico (solo acciones protegidas)
    Route::get('/catalogo/pdf', [\App\Http\Controllers\CatalogoController::class, 'exportPdf'])->name('catalogo.pdf');
    Route::post('/catalogo/upload-image', [\App\Http\Controllers\CatalogoController::class, 'uploadImageByUrl'])->name('catalogo.upload_image');

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
Route::middleware(['auth', 'role:admin,gerente,finanzas,auditor'])->prefix('finanzas')->group(function () {
    Route::get('/flujo-caja', [FinanzasController::class, 'flujoCaja'])->name('finanzas.flujo_caja');
    Route::post('/flujo-caja/parse-desglose', [FinanzasController::class, 'parseArchivoDesglose'])->name('finanzas.parse_desglose');
    Route::get('/flujo-caja/reporte', [FinanzasController::class, 'reporteFlujoCajaBusqueda'])->name('finanzas.flujo_caja.reporte');
    Route::get('/flujo-caja/reporte-diario', [FinanzasController::class, 'reporteDiarioCaja'])->name('finanzas.reporte_diario_caja');
    Route::get('/flujo-caja/api/bcv', [FinanzasController::class, 'fetchBcvApi'])->name('finanzas.api_bcv');
    Route::get('/gastos-fijos', [FinanzasController::class, 'gastosFijos'])->name('finanzas.gastos_fijos');

    Route::middleware(['role:admin,gerente,finanzas'])->group(function () {
        Route::post('/flujo-caja/reset', [FinanzasController::class, 'resetDaily'])->name('finanzas.reset_daily');
        Route::post('/flujo-caja/egreso', [FinanzasController::class, 'storeEgreso'])->name('finanzas.store_egreso');
        Route::post('/flujo-caja/egreso/{id}', [FinanzasController::class, 'updateEgreso'])->name('finanzas.update_egreso');
        Route::post('/flujo-caja/egresos-bulk', [FinanzasController::class, 'storeEgresosBulk'])->name('finanzas.store_egresos_bulk');
        Route::post('/flujo-caja/ocr-receipt', [FinanzasController::class, 'ocrReceipt'])->name('finanzas.ocr_receipt');
        Route::post('/flujo-caja/ocr-saldos', [FinanzasController::class, 'ocrSaldos'])->name('finanzas.ocr_saldos');
        Route::post('/flujo-caja/cuenta/{id}', [FinanzasController::class, 'updateCuenta'])->name('finanzas.update_cuenta');
        Route::post('/flujo-caja/resumen/{id}', [FinanzasController::class, 'updateResumen'])->name('finanzas.update_resumen');
        Route::post('/flujo-caja/planificacion/{id}', [FinanzasController::class, 'updatePlanificacion'])->name('finanzas.update_planificacion');
        Route::post('/gastos-fijos/monto', [FinanzasController::class, 'updateGastoFijoMonto'])->name('finanzas.gastos_fijos.monto');
        Route::post('/gastos-fijos/pagado', [FinanzasController::class, 'marcarGastoFijoPagado'])->name('finanzas.gastos_fijos.pagado');
        Route::post('/gastos-fijos/fecha', [FinanzasController::class, 'updateGastoFijoFecha'])->name('finanzas.gastos_fijos.fecha');
        Route::post('/gastos-fijos/costo', [FinanzasController::class, 'updateGastoFijoCosto'])->name('finanzas.gastos_fijos.costo');
        Route::post('/gastos-fijos/agregar', [FinanzasController::class, 'agregarGastoFijo'])->name('finanzas.gastos_fijos.agregar');
        Route::post('/gastos-fijos/eliminar', [FinanzasController::class, 'eliminarGastoFijoFila'])->name('finanzas.gastos_fijos.eliminar');
    });
});

// Conciliaciones routes - solo admin y contabilidad
Route::middleware(['auth', 'role:admin,gerente,contabilidad'])->prefix('finanzas')->group(function () {
    Route::get('/conciliaciones', [FinanzasController::class, 'conciliaciones'])->name('finanzas.conciliaciones');
    Route::post('/conciliaciones/upload', [FinanzasController::class, 'uploadConciliacion'])->name('finanzas.conciliaciones.upload');
    Route::post('/conciliaciones/process', [FinanzasController::class, 'processConciliacion'])->name('finanzas.conciliaciones.process');
    Route::post('/conciliaciones/add-missing', [App\Http\Controllers\FinanzasController::class, 'addMissingConciliacion'])->name('finanzas.conciliaciones.add_missing');
    Route::post('/conciliaciones/ignore', [App\Http\Controllers\FinanzasController::class, 'ignoreConciliacion'])->name('finanzas.conciliaciones.ignore');
    Route::post('/conciliaciones/manual', [App\Http\Controllers\FinanzasController::class, 'manualConciliacion'])->name('finanzas.conciliaciones.manual');
    Route::post('/conciliaciones/clear', [App\Http\Controllers\FinanzasController::class, 'clearConciliacion'])->name('finanzas.conciliaciones.clear');
    Route::get('/conciliaciones/reporte', [App\Http\Controllers\FinanzasController::class, 'reporteConciliacion'])->name('finanzas.conciliaciones.reporte');
    Route::get('/conciliaciones/reporte-banco', [App\Http\Controllers\FinanzasController::class, 'reporteBancoPdf'])->name('finanzas.conciliaciones.reporte-banco');
});

// Cobranza routes
Route::middleware(['auth', 'role:admin,gerente,cobranza'])->prefix('cobranza')->group(function () {
    Route::get('/', [CobranzaController::class, 'index'])->name('cobranza.index');
    Route::get('/pdf', [CobranzaController::class, 'descargarReportePdf'])->name('cobranza.pdf');
    Route::post('/importar', [CobranzaController::class, 'importarExcel'])->name('cobranza.importar');
    Route::post('/limpiar', [CobranzaController::class, 'limpiarClientes'])->name('cobranza.limpiar');
    Route::post('/guardar-resumen', [CobranzaController::class, 'guardarResumen'])->name('cobranza.guardar_resumen');
    Route::post('/marcar-personal', [CobranzaController::class, 'marcarPersonal'])->name('cobranza.marcar_personal');
    Route::post('/marcar-pagado-manualmente', [CobranzaController::class, 'marcarPagadoManualmente'])->name('cobranza.marcar_pagado');
    Route::post('/guardar-nota', [CobranzaController::class, 'guardarNota'])->name('cobranza.guardar_nota');
    Route::get('/{codigo_cliente}/llamadas', [CobranzaController::class, 'obtenerLlamadas'])->name('cobranza.llamadas.get');
    Route::post('/{codigo_cliente}/llamadas', [CobranzaController::class, 'guardarLlamada'])->name('cobranza.llamadas.store');
});
Route::get('/finanzas/reporte-consolidado', [App\Http\Controllers\FinanzasController::class, 'reporteConsolidado'])->name('finanzas.reporte_consolidado');

// Contratos / Seguimiento de cobranza routes
Route::middleware(['auth', 'role:admin,gerente,finanzas,cobranza'])->prefix('contratos')->group(function () {
    Route::get('/', [App\Http\Controllers\ContratoController::class, 'index'])->name('contratos.index');
    Route::get('/lista', [App\Http\Controllers\ContratoController::class, 'listar'])->name('contratos.lista');
    Route::get('/calendario', [App\Http\Controllers\ContratoController::class, 'calendario'])->name('contratos.calendario');
    Route::get('/crear', [App\Http\Controllers\ContratoController::class, 'create'])->name('contratos.create');
    Route::post('/', [App\Http\Controllers\ContratoController::class, 'store'])->name('contratos.store');
    Route::post('/importar', [App\Http\Controllers\ContratoController::class, 'importarExcel'])->name('contratos.importar');
    Route::post('/seguimiento', [App\Http\Controllers\ContratoController::class, 'agregarSeguimiento'])->name('contratos.seguimiento');
    Route::get('/{id}', [App\Http\Controllers\ContratoController::class, 'show'])->name('contratos.show');
    Route::get('/{id}/editar', [App\Http\Controllers\ContratoController::class, 'edit'])->name('contratos.edit');
    Route::post('/{id}', [App\Http\Controllers\ContratoController::class, 'update'])->name('contratos.update');
    Route::get('/{id}/liquidar', [App\Http\Controllers\ContratoController::class, 'liquidar'])->name('contratos.liquidar');
    Route::post('/{id}/liquidar', [App\Http\Controllers\ContratoController::class, 'liquidarStore'])->name('contratos.liquidar.store');
    Route::post('/cuota/{id}/pagar', [App\Http\Controllers\ContratoController::class, 'registrarPago'])->name('contratos.pagar');
    Route::put('/cuota/{id}/pagar', [App\Http\Controllers\ContratoController::class, 'actualizarPagoCuota'])->name('contratos.actualizar_pago');
    Route::post('/{id}/generar-cuota', [App\Http\Controllers\ContratoController::class, 'generarSiguienteCuota'])->name('contratos.generarCuota');
    Route::post('/{id}/aumentar-capital', [App\Http\Controllers\ContratoController::class, 'aumentarCapital'])->name('contratos.aumentarCapital');
    Route::get('/{id}/reporte', [App\Http\Controllers\ContratoController::class, 'reporte'])->name('contratos.reporte');
});

Route::get('/ping', function () { return 'OK'; });

// Endpoint para disparar el scheduler desde Render/GitHub Actions
Route::get('/scheduler-run', function (\Illuminate\Http\Request $request) {
    $expectedToken = config('app.scheduler_token', env('SCHEDULER_TOKEN', 'token-seguro-123456'));
    if ($request->query('token') !== $expectedToken) {
        abort(403, 'Unauthorized');
    }
    
    // Run the scheduler
    \Illuminate\Support\Facades\Artisan::call('schedule:run');
    
    return response()->json([
        'status' => 'success',
        'time' => now()->toDateTimeString(),
        'output' => \Illuminate\Support\Facades\Artisan::output()
    ]);
});
Route::get('/pure', function () {
    $inicio = microtime(true);
    return response('Tiempo: ' . round((microtime(true) - $inicio) * 1000, 2) . ' ms');
});


// ─────────────────────────────────────────────────────────────────────────────
// GESTIÓN PATRIMONIAL Y ALQUILERES
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin,gerente,cobranza'])->prefix('patrimonial')->name('patrimonial.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Patrimonial\DashboardPatrimonialController::class, 'index'])->name('dashboard');

    Route::resource('propiedades', \App\Http\Controllers\Patrimonial\PropiedadController::class)->parameters(['propiedades' => 'propiedad']);
    Route::delete('propiedades/{propiedad}/foto', [\App\Http\Controllers\Patrimonial\PropiedadController::class, 'deleteFoto'])->name('propiedades.delete_foto');
    Route::resource('reservas',    \App\Http\Controllers\Patrimonial\ReservaController::class)->parameters(['reservas' => 'reserva'])->except(['create', 'edit', 'show']);
    Route::resource('inventario',  \App\Http\Controllers\Patrimonial\InventarioItemController::class)->parameters(['inventario' => 'inventario'])->except(['create', 'edit', 'show']);
    Route::resource('llaves',      \App\Http\Controllers\Patrimonial\LlaveController::class)->parameters(['llaves' => 'llave'])->except(['create', 'edit', 'show']);
    Route::resource('documentos',  \App\Http\Controllers\Patrimonial\DocumentoController::class)->parameters(['documentos' => 'documento'])->except(['create', 'edit', 'show']);

    // Alquileres
    Route::get('/alquileres/calendario', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'calendario'])->name('alquileres.calendario');
    Route::get('/alquileres',           [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'index'])->name('alquileres.index');
    Route::get('/alquileres/crear',     [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'create'])->name('alquileres.create');
    Route::post('/alquileres',          [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'store'])->name('alquileres.store');
    Route::get('/alquileres/{alquiler}/editar', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'edit'])->name('alquileres.edit');
    Route::put('/alquileres/{alquiler}', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'update'])->name('alquileres.update');
    Route::delete('/alquileres/{alquiler}', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'destroy'])->name('alquileres.destroy');
    Route::post('/alquileres/{alquiler}/pago', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'registrarPago'])->name('alquileres.pago');
    Route::put('/alquileres/pago/{pago}', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'actualizarPago'])->name('alquileres.actualizar_pago');
    Route::get('/alquileres/{alquiler}', [\App\Http\Controllers\Patrimonial\AlquilerController::class, 'show'])->name('alquileres.show');

    // Transacciones
    Route::get('/transacciones',  [\App\Http\Controllers\Patrimonial\TransaccionController::class, 'index'])->name('transacciones.index');
    Route::post('/transacciones', [\App\Http\Controllers\Patrimonial\TransaccionController::class, 'store'])->name('transacciones.store');
    Route::delete('/transacciones/{transaccion}', [\App\Http\Controllers\Patrimonial\TransaccionController::class, 'destroy'])->name('transacciones.destroy');
    Route::get('/reportes/mensual', [\App\Http\Controllers\Patrimonial\TransaccionController::class, 'reporteMensual'])->name('reportes.mensual');

    // Reservas extra
    Route::post('/reservas/bloquear', [\App\Http\Controllers\Patrimonial\ReservaController::class, 'bloquear'])->name('reservas.bloquear');
    Route::post('/reservas/{reserva}/pago', [\App\Http\Controllers\Patrimonial\ReservaController::class, 'registrarPago'])->name('reservas.pago');
});


