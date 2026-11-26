<?php

use App\Modules\Inventory\Http\Controllers\TransferController;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Support\Facades\Route;

// Las operaciones que modifican stock son idempotentes (ADR-007): el
// middleware va después de auth:sanctum y exige Idempotency-Key.
Route::middleware(['auth:sanctum', Idempotency::MIDDLEWARE])->prefix('/inventory')->name('inventory.')->group(function () {
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
});
