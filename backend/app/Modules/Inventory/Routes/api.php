<?php

use App\Modules\Inventory\Http\Controllers\AdjustmentController;
use App\Modules\Inventory\Http\Controllers\InitialLoadController;
use App\Modules\Inventory\Http\Controllers\TransferController;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Support\Facades\Route;

// Identificadores BIGINT: un valor no numérico o fuera de rango responde 404
// en lugar de un error de conversión de PostgreSQL.
$id = '[1-9][0-9]{0,17}';

Route::middleware('auth:sanctum')->prefix('/inventory')->name('inventory.')->group(function () use ($id) {
    Route::get('/adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
    Route::get('/adjustments/{adjustment}', [AdjustmentController::class, 'show'])->where('adjustment', $id)->name('adjustments.show');
});

// Las operaciones que modifican stock o deciden sobre él son idempotentes
// (ADR-007): el middleware va después de auth:sanctum y exige Idempotency-Key.
Route::middleware(['auth:sanctum', Idempotency::MIDDLEWARE])->prefix('/inventory')->name('inventory.')->group(function () use ($id) {
    Route::post('/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
    Route::post('/adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve'])->where('adjustment', $id)->name('adjustments.approve');
    Route::post('/adjustments/{adjustment}/reject', [AdjustmentController::class, 'reject'])->where('adjustment', $id)->name('adjustments.reject');
    Route::post('/initial-loads', [InitialLoadController::class, 'store'])->name('initial-loads.store');
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
});
