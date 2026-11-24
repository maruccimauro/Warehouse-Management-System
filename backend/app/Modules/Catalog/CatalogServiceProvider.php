<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Policies\CatalogPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Catalog: Productos, categorías, unidades de medida y proveedores.
 */
class CatalogServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Category::class => CatalogPolicy::class,
        UnitOfMeasure::class => CatalogPolicy::class,
        Product::class => CatalogPolicy::class,
    ];

    protected array $uniqueConstraintFields = [
        'categories_parent_id_name_unique' => 'name',
        'units_of_measure_code_unique' => 'code',
        'products_sku_unique' => 'sku',
        'products_barcode_unique' => 'barcode',
    ];

    public function boot(): void
    {
        parent::boot();

        // Vinculación explícita: los Form Requests autorizan y validan con el
        // modelo de la ruta (ver WarehouseServiceProvider).
        Route::model('category', Category::class);
        Route::model('unit', UnitOfMeasure::class);
        Route::model('product', Product::class);

        // Alias para audit_logs.auditable_type: product.status_changed.
        Relation::morphMap([
            'product' => Product::class,
        ]);
    }
}
