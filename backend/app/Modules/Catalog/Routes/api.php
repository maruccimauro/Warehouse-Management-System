<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductSupplierController;
use App\Modules\Catalog\Http\Controllers\ProductWarehouseSettingController;
use App\Modules\Catalog\Http\Controllers\SupplierController;
use App\Modules\Catalog\Http\Controllers\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

// Identificadores BIGINT: un valor no numérico o fuera de rango responde 404
// en lugar de un error de conversión de PostgreSQL.
$id = '[1-9][0-9]{0,17}';

Route::middleware('auth:sanctum')->group(function () use ($id) {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->where('category', $id)->name('categories.show');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->where('category', $id)->name('categories.update');

    Route::get('/units', [UnitOfMeasureController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitOfMeasureController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}', [UnitOfMeasureController::class, 'show'])->where('unit', $id)->name('units.show');
    Route::patch('/units/{unit}', [UnitOfMeasureController::class, 'update'])->where('unit', $id)->name('units.update');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->where('product', $id)->name('products.show');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->where('product', $id)->name('products.update');

    Route::get('/products/{product}/warehouse-settings', [ProductWarehouseSettingController::class, 'index'])
        ->where('product', $id)->name('products.warehouse-settings.index');
    Route::put('/products/{product}/warehouse-settings/{warehouse}', [ProductWarehouseSettingController::class, 'update'])
        ->where(['product' => $id, 'warehouse' => $id])->name('products.warehouse-settings.update');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->where('supplier', $id)->name('suppliers.show');
    Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])->where('supplier', $id)->name('suppliers.update');

    Route::get('/suppliers/{supplier}/products', [ProductSupplierController::class, 'supplierProducts'])
        ->where('supplier', $id)->name('suppliers.products.index');
    Route::put('/suppliers/{supplier}/products/{product}', [ProductSupplierController::class, 'update'])
        ->where(['supplier' => $id, 'product' => $id])->name('suppliers.products.update');
    Route::delete('/suppliers/{supplier}/products/{product}', [ProductSupplierController::class, 'destroy'])
        ->where(['supplier' => $id, 'product' => $id])->name('suppliers.products.destroy');
    Route::get('/products/{product}/suppliers', [ProductSupplierController::class, 'productSuppliers'])
        ->where('product', $id)->name('products.suppliers.index');
});
