<?php

use App\Modules\Warehouse\Http\Controllers\LocationController;
use App\Modules\Warehouse\Http\Controllers\WarehouseController;
use App\Modules\Warehouse\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

// Identificadores BIGINT: un valor no numérico o fuera de rango responde 404
// en lugar de un error de conversión de PostgreSQL.
$id = '[1-9][0-9]{0,17}';

Route::middleware('auth:sanctum')->group(function () use ($id) {
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->where('warehouse', $id)->name('warehouses.show');
    Route::patch('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->where('warehouse', $id)->name('warehouses.update');

    Route::prefix('/warehouses/{warehouse}')->where(['warehouse' => $id])->name('warehouses.')->group(function () {
        Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');
        Route::post('/zones', [ZoneController::class, 'store'])->name('zones.store');
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::post('/locations/bulk', [LocationController::class, 'generate'])->name('locations.bulk');
    });

    Route::get('/zones/{zone}', [ZoneController::class, 'show'])->where('zone', $id)->name('zones.show');
    Route::patch('/zones/{zone}', [ZoneController::class, 'update'])->where('zone', $id)->name('zones.update');
    Route::get('/locations/{location}', [LocationController::class, 'show'])->where('location', $id)->name('locations.show');
    Route::patch('/locations/{location}', [LocationController::class, 'update'])->where('location', $id)->name('locations.update');
    Route::post('/locations/{location}/block', [LocationController::class, 'block'])->where('location', $id)->name('locations.block');
    Route::post('/locations/{location}/unblock', [LocationController::class, 'unblock'])->where('location', $id)->name('locations.unblock');
});
