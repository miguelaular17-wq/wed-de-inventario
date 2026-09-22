<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\RequisitionController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::prefix('servicio/celulares')->group(function () {
            Route::get('/opciones', [ServiceOrderController::class, 'options']);
            Route::get('/ordenes', [ServiceOrderController::class, 'index']);
            Route::post('/ordenes', [ServiceOrderController::class, 'store']);
            Route::get('/ordenes/{orden}', [ServiceOrderController::class, 'show'])->whereNumber('orden');
            Route::post('/ordenes/{orden}/evidencias', [ServiceOrderController::class, 'uploadEvidence'])->whereNumber('orden');
            Route::get('/ordenes/{orden}/pdf/recepcion', [ServiceOrderController::class, 'receptionPdf'])->whereNumber('orden');
            Route::post('/ordenes/{orden}/estado', [ServiceOrderController::class, 'changeStatus'])->whereNumber('orden');
        });

        Route::middleware('permission:operacion')->group(function () {
            Route::get('/sedes', [InventoryController::class, 'sedes']);
            Route::get('/inventario', [InventoryController::class, 'index']);
            Route::get('/inventario/productos/{codigo}/metricas', [InventoryController::class, 'metrics'])
                ->where('codigo', '.*');

            Route::get('/requisiciones', [RequisitionController::class, 'index']);
            Route::post('/requisiciones', [RequisitionController::class, 'store']);
            Route::delete('/requisiciones/{requisicion}', [RequisitionController::class, 'destroy'])
                ->whereNumber('requisicion');
        });
    });
});
