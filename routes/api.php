<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\RequisitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

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
